#!/bin/bash
# Melepas serangan sungguhan ke instans EQOHSEE yang sedang berjalan,
# lalu mencatat kode balasan HTTP tiap permintaan apa adanya.
#
# ── Kenapa ini ada ──
#
# Uji PHPUnit membuktikan pertahanannya TERTULIS. Berkas ini membuktikan
# pertahanannya BEKERJA — lewat jalur yang sama persis dengan yang
# dilewati penyerang: soket, middleware, sesi, basis data. Keduanya
# dibutuhkan, dan yang satu tidak menggantikan yang lain.
#
# ── Menjalankan ──
#
#   php artisan serve --host=127.0.0.1 --port=8899 &
#   php artisan demo:pasang
#   bash deploy/uji-serangan.sh
#
# JANGAN dijalankan terhadap basis data produksi: ia menulis satu baris
# PJP bermuatan XSS, dan ia menghabiskan jatah batas laju alamat yang
# menjalankannya selama satu menit.
#
# Sasaran dapat diganti lewat lingkungan:
#
#   BASE=http://127.0.0.1:8000 bash deploy/uji-serangan.sh
set -u

BASE="${BASE:-http://127.0.0.1:8899}"
AKAR="$(cd "$(dirname "$0")/.." && pwd)"
D="${TMPDIR:-/tmp}/eqohsee-uji-serangan"
mkdir -p "$D"
J="$D/kuki.txt"
HASIL="$D/hasil-serangan.tsv"
rm -f "$J" "$HASIL"
cd "$AKAR"
php artisan cache:clear >/dev/null 2>&1

catat() { printf '%s\t%s\t%s\t%s\n' "$1" "$2" "$3" "$4" >> "$HASIL"; }

# Inertia tidak menaruh _token di formulir; ia memakai kuki XSRF-TOKEN
# lalu mengirimnya kembali sebagai tajuk. Nilainya ter-URL-encode.
xsrf() { grep -oP 'XSRF-TOKEN\s+\K\S+' "$J" | tail -1 | sed 's/%3D/=/g'; }

# ── masuk sebagai pengguna contoh ──────────────────────────────────────
curl -s -c "$J" -o /dev/null "$BASE/login"
curl -s -b "$J" -c "$J" -o /dev/null -X POST "$BASE/login" \
  -H "X-XSRF-TOKEN: $(xsrf)" -H "Accept: application/json" \
  -d "email=ktt.cdi@contoh.test&password=rahasia123"

SESI=$(curl -s -b "$J" -o /dev/null -w '%{http_code}' "$BASE/pjp/daftar")
[ "$SESI" = "200" ] || { echo "GAGAL MASUK (kode $SESI) — uji dihentikan"; exit 1; }

# ── 1 · injeksi SQL pada penyaring pencarian ───────────────────────────
for muatan in "' OR '1'='1" "1; DROP TABLE users--" "' UNION SELECT null,null,null--" \
              "' OR 1=1 /*" "admin'--" "%' OR '1'='1"; do
  kode=$(curl -s -b "$J" -o "$D/sqli.out" -w '%{http_code}' -G "$BASE/pjp/daftar" \
         --data-urlencode "cari=$muatan")
  bocor=$(grep -ciE "SQLSTATE|syntax error|mysqli|PDOException|SQLite3" "$D/sqli.out")
  if [ "$kode" = "200" ] && [ "$bocor" = "0" ]; then
    catat "Injeksi SQL" "$muatan" "DITAHAN" "200, tanpa galat basis data"
  else
    catat "Injeksi SQL" "$muatan" "PERIKSA" "kode=$kode bocor=$bocor"
  fi
done

SEBELUM=$(php artisan tinker --execute='echo App\Models\User::withoutGlobalScopes()->count();' 2>/dev/null | tail -1 | tr -d '[:space:]')

# ── 2 · XSS tersimpan ──────────────────────────────────────────────────
XSS='<script>alert(1)</script>'
curl -s -b "$J" -c "$J" -o /dev/null "$BASE/pjp/daftar/baru"
curl -s -b "$J" -c "$J" -o /dev/null -X POST "$BASE/pjp/daftar" \
  -H "X-XSRF-TOKEN: $(xsrf)" -H "Accept: application/json" \
  --data-urlencode "nama=$XSS" --data-urlencode "nama_perusahaan=$XSS" \
  -d "status=aktif"
mentah=$(curl -s -b "$J" "$BASE/pjp/daftar" | grep -cF "<script>alert(1)</script>")
if [ "$mentah" = "0" ]; then
  catat "XSS tersimpan" "script alert di nama PJP" "DITAHAN" "tidak pernah tergambar mentah"
else
  catat "XSS tersimpan" "script alert di nama PJP" "PERIKSA" "tergambar mentah $mentah kali"
fi

# ── 3 · CSRF: POST tanpa token ─────────────────────────────────────────
kode=$(curl -s -b "$J" -o /dev/null -w '%{http_code}' -X POST "$BASE/pjp/daftar" \
       -d "nama=Tanpa Token&status=aktif")
[ "$kode" = "419" ] \
  && catat "CSRF" "POST tanpa token" "DITAHAN" "419 Page Expired" \
  || catat "CSRF" "POST tanpa token" "PERIKSA" "kode=$kode"

# ── 4 · CSRF: token milik sesi lain ────────────────────────────────────
curl -s -c "$D/kuki2.txt" -o /dev/null "$BASE/login"
LAIN=$(grep -oP 'XSRF-TOKEN\s+\K\S+' "$D/kuki2.txt" | tail -1 | sed 's/%3D/=/g')
kode=$(curl -s -b "$J" -o /dev/null -w '%{http_code}' -X POST "$BASE/pjp/daftar" \
       -H "X-XSRF-TOKEN: $LAIN" -d "nama=Token Sesi Lain&status=aktif")
[ "$kode" = "419" ] \
  && catat "CSRF" "token dari sesi lain" "DITAHAN" "419 Page Expired" \
  || catat "CSRF" "token dari sesi lain" "PERIKSA" "kode=$kode"

# ── 5 · akses tanpa sesi ───────────────────────────────────────────────
for jalur in "/pjp/daftar" "/dashboard" "/admin/companies" "/admin/keamanan"; do
  kode=$(curl -s -o /dev/null -w '%{http_code}' "$BASE$jalur")
  [ "$kode" = "302" ] || [ "$kode" = "401" ] \
    && catat "Autentikasi" "$jalur tanpa sesi" "DITAHAN" "$kode ke halaman masuk" \
    || catat "Autentikasi" "$jalur tanpa sesi" "PERIKSA" "kode=$kode"
done

# ── 6 · otorisasi: panel admin dengan sesi pengguna biasa ──────────────
for jalur in "/admin/companies" "/admin/keamanan" "/admin/ai"; do
  kode=$(curl -s -b "$J" -o /dev/null -w '%{http_code}' "$BASE$jalur")
  [ "$kode" = "403" ] || [ "$kode" = "302" ] \
    && catat "Otorisasi" "$jalur sebagai KTT" "DITAHAN" "$kode" \
    || catat "Otorisasi" "$jalur sebagai KTT" "PERIKSA" "kode=$kode"
done

# ── 7 · IDOR lintas perusahaan ─────────────────────────────────────────
ASING=$(php artisan tinker --execute='
$u = App\Models\User::withoutGlobalScopes()->where("email","ktt.cdi@contoh.test")->first();
$p = App\Models\Pjp::withoutGlobalScopes()->where("company_id","!=",$u->company_id)->first();
echo $p?->id ?? 0;' 2>/dev/null | tail -1 | tr -d '[:space:]')
if [ "$ASING" != "0" ] && [ -n "$ASING" ]; then
  kode=$(curl -s -b "$J" -o /dev/null -w '%{http_code}' "$BASE/pjp/$ASING")
  [ "$kode" = "404" ] || [ "$kode" = "403" ] \
    && catat "IDOR lintas tenant" "buka PJP #$ASING milik perusahaan lain" "DITAHAN" "$kode" \
    || catat "IDOR lintas tenant" "buka PJP #$ASING milik perusahaan lain" "PERIKSA" "kode=$kode"
else
  catat "IDOR lintas tenant" "tidak ada data pembanding" "LEWATI" "-"
fi

# ── 8 · berkas rahasia dan penjelajahan jalur ──────────────────────────
for jalur in "/.env" "/.git/config" "/storage/logs/laravel.log" "/vendor/phpunit/phpunit/phpunit" \
             "/storage/../../.env" "/storage/..%2f..%2f.env" "/wp-admin" "/phpmyadmin"; do
  kode=$(curl -s -o /dev/null -w '%{http_code}' "$BASE$jalur")
  case "$kode" in
    404|403|400|302) catat "Berkas/jalur rahasia" "$jalur" "DITAHAN" "$kode" ;;
    *)               catat "Berkas/jalur rahasia" "$jalur" "PERIKSA" "kode=$kode" ;;
  esac
done

# ── 9 · tajuk keamanan ─────────────────────────────────────────────────
T=$(curl -s -D - -b "$J" -o /dev/null "$BASE/pjp/daftar")
for h in "Content-Security-Policy" "X-Frame-Options" "X-Content-Type-Options" \
         "Referrer-Policy" "Permissions-Policy"; do
  nilai=$(echo "$T" | grep -i "^$h:" | head -1 | cut -d: -f2- | tr -d '\r' | cut -c1-52)
  [ -n "$nilai" ] \
    && catat "Tajuk keamanan" "$h" "TERPASANG" "${nilai# }" \
    || catat "Tajuk keamanan" "$h" "PERIKSA" "tidak ada"
done
echo "$T" | grep -qi "^Set-Cookie:.*HttpOnly" \
  && catat "Kuki sesi" "HttpOnly" "TERPASANG" "tidak terbaca JavaScript" \
  || catat "Kuki sesi" "HttpOnly" "-" "tidak ada Set-Cookie pada permintaan ini"

# ── 10 · nonce CSP berganti tiap permintaan ────────────────────────────
n1=$(curl -s -D - -b "$J" -o /dev/null "$BASE/pjp/daftar" | grep -io "nonce-[A-Za-z0-9+/=]*" | head -1)
n2=$(curl -s -D - -b "$J" -o /dev/null "$BASE/pjp/daftar" | grep -io "nonce-[A-Za-z0-9+/=]*" | head -1)
if [ -n "$n1" ] && [ "$n1" != "$n2" ]; then
  catat "CSP nonce" "berganti tiap permintaan" "DITAHAN" "nonce tidak dapat ditebak/dipakai ulang"
else
  catat "CSP nonce" "berganti tiap permintaan" "PERIKSA" "n1=$n1 n2=$n2"
fi

# ── 11 · jumlah baris users tidak berubah sesudah semua percobaan ──────
SESUDAH=$(php artisan tinker --execute='echo App\Models\User::withoutGlobalScopes()->count();' 2>/dev/null | tail -1 | tr -d '[:space:]')
[ "$SEBELUM" = "$SESUDAH" ] \
  && catat "Keutuhan data" "tabel users sesudah semua muatan" "UTUH" "$SEBELUM baris, tidak berubah" \
  || catat "Keutuhan data" "tabel users sesudah semua muatan" "PERIKSA" "$SEBELUM -> $SESUDAH"

# ── 12 · batas laju halaman masuk ──────────────────────────────────────
#
# HARUS memakai token CSRF yang sah. Tanpa token, VerifyCsrfToken di grup
# web menolak permintaannya dengan 419 SEBELUM middleware rute sempat
# jalan — sehingga throttle:masuk tidak pernah melihat satu pun tebakan,
# dan uji yang lupa ini akan melaporkan "tidak ada yang ditolak" pada
# pembatas yang sebenarnya bekerja.
rm -f "$D/kuki3.txt"
curl -s -c "$D/kuki3.txt" -o /dev/null "$BASE/login"
TOK=$(grep -oP 'XSRF-TOKEN\s+\K\S+' "$D/kuki3.txt" | tail -1 | sed 's/%3D/=/g')
tolak=0; lolos=0
for i in $(seq 1 26); do
  k=$(curl -s -b "$D/kuki3.txt" -o /dev/null -w '%{http_code}' -X POST "$BASE/login" \
      -H "X-XSRF-TOKEN: $TOK" -H "Accept: application/json" \
      -d "email=penebak@contoh.test&password=salah$i")
  [ "$k" = "429" ] && tolak=$((tolak+1)) || lolos=$((lolos+1))
done
[ "$tolak" -gt 0 ] \
  && catat "Batas laju masuk" "26 tebakan sandi, token CSRF sah" "DITAHAN" "$lolos dilayani, $tolak ditolak 429" \
  || catat "Batas laju masuk" "26 tebakan sandi, token CSRF sah" "PERIKSA" "tidak satu pun ditolak"

# ── 13 · banjir yang tidak dilihat pembatas pintu ──────────────────────
#
# Inilah celah yang ditutup atap throttle:web. Permintaan tanpa token
# ditolak VerifyCsrfToken dengan 419 — tetapi 419 itu BUKAN gratis: tiap
# satunya sudah menyalakan kerangka, membuka sesi, dan memakai satu
# proses PHP-FPM. throttle:masuk tidak pernah melihatnya sama sekali,
# sebab middleware rute berjalan sesudah grup web. Sebelum atap ini ada,
# banjir semacam itu tidak dibatasi apa pun di tingkat aplikasi.
php artisan cache:clear >/dev/null 2>&1
b419=0; b429=0; blain=0
for i in $(seq 1 210); do
  k=$(curl -s -o /dev/null -w '%{http_code}' -X POST "$BASE/login" -d "email=banjir@x.c&password=y")
  case "$k" in 419) b419=$((b419+1));; 429) b429=$((b429+1));; *) blain=$((blain+1));; esac
done
[ "$b429" -gt 0 ] \
  && catat "Atap throttle:web" "210 POST tanpa token (tak terlihat throttle:masuk)" "DITAHAN" "$b419 sampai ke CSRF, $b429 dipotong 429 lebih awal" \
  || catat "Atap throttle:web" "210 POST tanpa token (tak terlihat throttle:masuk)" "PERIKSA" "419=$b419 429=$b429 lain=$blain"

awk -F"\t" '{printf "%-22s %-44s %-10s %s\n",$1,$2,$3,$4}' "$HASIL"

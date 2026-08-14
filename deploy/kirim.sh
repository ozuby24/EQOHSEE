#!/usr/bin/env bash
# EQOHSEE — kirim perubahan ke VPS.
#
# Dijalankan DI MESIN LOKAL (Git Bash, WSL, Termux, atau Linux/macOS),
# bukan di VPS. Pasangannya adalah deploy/deploy.sh yang berjalan di sisi
# server; berkas ini yang memeriksa, mengirim, lalu memanggilnya.
#
# Alurnya sengaja lewat Git, bukan salin berkas langsung. Menyalin berkas
# membuat isi VPS perlahan menyimpang dari repo — berkas yang pernah
# tersalin dan tidak pernah terhapus, suntingan darurat di server yang
# tidak pernah kembali ke repo — dan penyimpangan itu baru terasa saat
# keadaan sudah sulit ditelusuri. Dengan Git, isi VPS selalu sama persis
# dengan satu commit yang bisa disebut namanya.
#
#   bash deploy/kirim.sh                     # kirim cabang yang sedang aktif
#   bash deploy/kirim.sh main                # kirim cabang tertentu
#   VPS_HOST=root@103.89.4.246 bash deploy/kirim.sh
#
# Pengaturan (lewat variabel lingkungan, semuanya ada nilai bawaan):
#   VPS_HOST    pengguna@alamat        (bawaan: root@103.89.4.246)
#   VPS_PORT    porta SSH              (bawaan: 22)
#   VPS_DIR     letak repo di VPS      (bawaan: /var/www/EQOHSEE)
#   VPS_SSL     1 untuk menyalakan HTTPS di deploy.sh
#
# Pilihan:
#   --tanpa-tes   lewati uji lokal (dipakai saat perbaikan mendesak)
#   --kering      tampilkan yang akan dikerjakan, tanpa mengubah apa pun
#   --paksa       teruskan meski worktree lokal masih kotor

set -euo pipefail

# Pengaturan dibaca dari deploy/vps.conf bila ada. Berkas itu tidak masuk
# repo: alamat server dan porta SSH berbeda-beda tiap orang, dan yang
# tersimpan di repo cepat atau lambat akan dipakai orang lain tanpa
# sengaja. Contohnya ada di deploy/vps.conf.example.
#
# Isinya memakai bentuk `: "${VPS_HOST:=...}"` — hanya mengisi yang belum
# diset, sehingga variabel lingkungan tetap menang bila diberikan saat
# pemanggilan.
KONFIG="$(dirname "$0")/vps.conf"
# shellcheck source=/dev/null
[ -f "$KONFIG" ] && . "$KONFIG"

VPS_HOST="${VPS_HOST:-root@103.89.4.246}"
VPS_PORT="${VPS_PORT:-22}"
VPS_DIR="${VPS_DIR:-/var/www/EQOHSEE}"
VPS_SSL="${VPS_SSL:-0}"

TANPA_TES=0
KERING=0
PAKSA=0
CABANG=""

for arg in "$@"; do
    case "$arg" in
        --tanpa-tes) TANPA_TES=1 ;;
        --kering)    KERING=1 ;;
        --paksa)     PAKSA=1 ;;
        -h|--help)   sed -n '2,30p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        -*)          echo "Pilihan tidak dikenal: $arg" >&2; exit 2 ;;
        *)           CABANG="$arg" ;;
    esac
done

merah()  { printf '\033[31m%s\033[0m\n' "$*"; }
hijau()  { printf '\033[32m%s\033[0m\n' "$*"; }
kuning() { printf '\033[33m%s\033[0m\n' "$*"; }
tahap()  { printf '\n\033[1m==> %s\033[0m\n' "$*"; }

gagal() { merah "GAGAL: $*"; exit 1; }

cd "$(dirname "$0")/.."
AKAR="$(pwd)"

# ── 1. Prasyarat ──────────────────────────────────────────────────
tahap "Memeriksa prasyarat"
for alat in git ssh; do
    command -v "$alat" >/dev/null 2>&1 || gagal "'$alat' tidak ditemukan di mesin ini."
done
git rev-parse --git-dir >/dev/null 2>&1 || gagal "Bukan repo Git: $AKAR"

CABANG="${CABANG:-$(git rev-parse --abbrev-ref HEAD)}"
[ "$CABANG" != "HEAD" ] || gagal "HEAD sedang lepas. Sebutkan cabangnya: bash deploy/kirim.sh <cabang>"
echo "    cabang  : $CABANG"
echo "    tujuan  : $VPS_HOST:$VPS_PORT  →  $VPS_DIR"

# ── 2. Keadaan kerja lokal ────────────────────────────────────────
tahap "Memeriksa worktree lokal"
if [ -n "$(git status --porcelain)" ]; then
    if [ "$PAKSA" = "1" ]; then
        kuning "    Worktree kotor, diteruskan karena --paksa."
        kuning "    Perubahan yang belum di-commit TIDAK akan ikut terkirim."
    else
        git status --short | sed 's/^/      /'
        gagal "Masih ada perubahan yang belum di-commit. Commit dulu, atau pakai --paksa."
    fi
else
    hijau "    Bersih."
fi

# Berkas rahasia tidak boleh pernah terlacak. Diperiksa di sini, bukan
# hanya diserahkan ke .gitignore: berkas yang terlanjur ter-commit tetap
# terlacak walau kemudian namanya dimasukkan ke .gitignore.
for rahasia in .env database/database.sqlite; do
    if git ls-files --error-unmatch "$rahasia" >/dev/null 2>&1; then
        gagal "'$rahasia' terlacak Git. Keluarkan dulu: git rm --cached $rahasia"
    fi
done

# ── 3. Uji lokal ──────────────────────────────────────────────────
if [ "$TANPA_TES" = "1" ]; then
    tahap "Uji lokal dilewati (--tanpa-tes)"
else
    tahap "Menjalankan uji lokal"
    if [ ! -d vendor ]; then
        kuning "    vendor/ belum ada — uji dilewati. Jalankan 'composer install' bila ingin diuji."
    else
        php artisan test || gagal "Uji tidak lulus. Perbaiki dulu, atau pakai --tanpa-tes bila memang mendesak."
        hijau "    Uji lulus."
    fi
fi

# ── 4. Kirim ke GitHub ────────────────────────────────────────────
tahap "Mengirim '$CABANG' ke GitHub"
if [ "$KERING" = "1" ]; then
    echo "    (kering) git push -u origin $CABANG"
else
    git push -u origin "$CABANG"
fi

COMMIT="$(git rev-parse HEAD)"
echo "    commit  : ${COMMIT:0:8}  $(git log -1 --pretty=%s)"

# ── 5. Sambungan ke VPS ───────────────────────────────────────────
tahap "Menguji sambungan SSH"
SSH="ssh -p $VPS_PORT -o ConnectTimeout=15 -o BatchMode=yes"
if ! $SSH "$VPS_HOST" "true" 2>/dev/null; then
    merah "    Tidak dapat masuk ke $VPS_HOST tanpa kata sandi."
    echo  "    Pasang kunci SSH lebih dulu:"
    echo  "      ssh-keygen -t ed25519          # bila belum punya kunci"
    echo  "      ssh-copy-id -p $VPS_PORT $VPS_HOST"
    exit 1
fi
hijau "    Tersambung."

# Repo di VPS harus bersih sebelum di-reset. Bila ada yang pernah
# menyunting berkas langsung di server, reset --hard akan membuangnya
# tanpa jejak — dan justru suntingan semacam itu yang biasanya berisi
# perbaikan mendesak yang belum sempat masuk repo.
tahap "Memeriksa keadaan repo di VPS"
KOTOR="$($SSH "$VPS_HOST" "cd '$VPS_DIR' 2>/dev/null && git status --porcelain || echo '__TIDAK_ADA__'")"
if [ "$KOTOR" = "__TIDAK_ADA__" ]; then
    gagal "Direktori '$VPS_DIR' tidak ada atau bukan repo Git di VPS."
elif [ -n "$KOTOR" ]; then
    echo "$KOTOR" | sed 's/^/      /'
    if [ "$PAKSA" != "1" ]; then
        merah "    Ada suntingan langsung di VPS yang belum masuk repo."
        echo  "    Selamatkan dulu, atau buang dengan sengaja lewat --paksa."
        exit 1
    fi
    kuning "    Diteruskan karena --paksa — suntingan di atas akan hilang."
else
    hijau "    Bersih."
fi

# ── 6. Pasang di VPS ──────────────────────────────────────────────
tahap "Memasang di VPS"
if [ "$KERING" = "1" ]; then
    echo "    (kering) fetch → checkout $CABANG → reset ke $COMMIT → deploy.sh"
    hijau "Selesai (kering). Tidak ada yang diubah."
    exit 0
fi

$SSH -t "$VPS_HOST" "bash -s" <<REMOTE
set -euo pipefail
cd '$VPS_DIR'

echo '--> Menarik $CABANG'
git fetch origin '$CABANG'
git checkout '$CABANG' 2>/dev/null || git checkout -b '$CABANG' 'origin/$CABANG'
git reset --hard '$COMMIT'

echo '--> Menjalankan deploy.sh'
EQOHSEE_SSL='$VPS_SSL' bash deploy/deploy.sh
REMOTE

# ── 7. Pemeriksaan akhir ──────────────────────────────────────────
tahap "Memeriksa hasil"
TERPASANG="$($SSH "$VPS_HOST" "cd '$VPS_DIR' && git rev-parse HEAD")"
if [ "$TERPASANG" = "$COMMIT" ]; then
    hijau "    VPS berada di ${COMMIT:0:8} — sama dengan yang dikirim."
else
    gagal "VPS berada di ${TERPASANG:0:8}, bukan ${COMMIT:0:8}. Deploy tidak tuntas."
fi

ALAMAT="$($SSH "$VPS_HOST" "grep -E '^APP_URL=' '$VPS_DIR/.env' | cut -d= -f2- | tr -d '\"'" || true)"
if [ -n "$ALAMAT" ] && command -v curl >/dev/null 2>&1; then
    KODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$ALAMAT" || echo '000')"
    case "$KODE" in
        200|302) hijau "    $ALAMAT menjawab $KODE." ;;
        000)     kuning "    $ALAMAT tidak terjangkau dari sini (bisa jadi hanya soal jaringan lokal)." ;;
        *)       kuning "    $ALAMAT menjawab $KODE — periksa /var/log/nginx/eqohsee.error.log." ;;
    esac
fi

echo
hijau "Selesai. $CABANG @ ${COMMIT:0:8} sudah terpasang di $VPS_HOST."

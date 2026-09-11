#!/bin/bash
# EQOHSEE VPS deploy helper.
# Jalankan DI VPS dari dalam repo: bash deploy/deploy.sh [server_name]
#
# Dari mesin lokal, tidak perlu masuk ke VPS sendiri: deploy/kirim.sh
# memeriksa, mengirim ke GitHub, lalu memanggil berkas ini lewat SSH.
#
# server_name memuat domain DAN alamat IP sekaligus. IP-nya sengaja tetap
# didaftarkan: selama DNS eqohsee.id belum menyebar — atau bila nanti
# bermasalah — situsnya tetap dapat dibuka lewat IP, sehingga tidak ada
# jendela waktu ketika aplikasi sama sekali tak terjangkau.
#
# HTTPS: jalankan dengan EQOHSEE_SSL=1 setelah DNS benar-benar mengarah
# ke VPS ini. Certbot menolak menerbitkan sertifikat bila domainnya belum
# menunjuk ke sini, jadi ini tidak dijalankan otomatis.
set -e

REPO_DIR="/var/www/EQOHSEE"
SERVER_NAME="${1:-eqohsee.id www.eqohsee.id 103.89.4.246}"
DOMAIN_UTAMA="$(echo "$SERVER_NAME" | awk '{print $1}')"

echo "==> Pulling latest code"
cd "$REPO_DIR"

# Izin berkas di server diatur dari luar Git — lihat `chmod -R 775` pada
# langkah "Setting permissions" di bawah. Chmod itu ikut mengenai berkas
# yang DILACAK Git: sebelas berkas .gitignore di dalam storage/ dan
# bootstrap/cache/ tercatat 100644 di index, dan sesudah chmod menjadi
# 775 di disk.
#
# Git membaca selisih itu sebagai perubahan isi. Akibatnya deploy yang
# berhasil membuat worktree server kotor secara permanen, dan deploy
# BERIKUTNYA ditolak kirim.sh dengan "Masih ada perubahan yang belum
# di-commit" — daftar berkas yang tidak pernah disunting siapa pun.
# Kegagalannya menuduh orang menyunting di server, padahal skrip inilah
# yang mengubahnya.
#
# core.fileMode=false membuat Git mengabaikan bit izin pada klon ini
# saja. Aman di sini justru karena izinnya memang bukan urusan Git:
# yang menentukan adalah chmod di bawah, tiap kali deploy berjalan.
git config core.fileMode false

# Bash membaca skrip sambil menjalankannya, berdasarkan posisi byte. `git pull`
# di sini dapat mengganti isi berkas yang sedang dibaca itu juga — dan bila
# panjangnya bergeser, perintah berikutnya dibaca dari tengah baris lain.
# Gejalanya galat sintaks yang tidak cocok dengan isi berkas mana pun, atau
# lebih buruk: separuh langkah terlewat tanpa satu pun pesan.
#
# Karena itu sidik jarinya dibandingkan sebelum dan sesudah pull; bila skrip
# ini ikut berubah, prosesnya diganti dengan versi baru dari awal.
SIDIK_SEBELUM="$(sha256sum "$0" | cut -d' ' -f1)"

CABANG="$(git rev-parse --abbrev-ref HEAD)"
git fetch --prune origin "$CABANG"

# Git harus menjadi satu-satunya sumber isi berkas yang dilacaknya.
#
# Sebelumnya langkah ini `git pull` biasa, dan itu punya lubang yang
# diam. Berkas yang dilacak Git tetapi diganti langsung di server —
# lewat panel, scp, atau FTP — TIDAK akan pernah ditimpa `git pull`
# selama tidak ada commit baru yang kebetulan menyentuh berkas yang
# sama. Git menganggapnya perubahan lokal yang belum diputuskan, dan
# membiarkannya. Situs pun menyajikan berkas yang tidak ada di repo
# mana pun, tanpa satu pun pesan.
#
# Itu benar-benar terjadi: seluruh berkas .mp4 di server berbeda dari
# yang ada di repo, sedangkan seluruh .jpg sama persis. Videonya pernah
# diunggah langsung ke server, lalu menetap di sana berbulan-bulan.
# Setiap commit yang memperbarui video "berhasil" tanpa pernah tampak.
#
# Lebih buruk lagi, lubang ini punya sisi kedua: begitu ada commit yang
# menyentuh berkas yang menyimpang itu, `git pull` menolak MELEBUR SAMA
# SEKALI ("local changes would be overwritten"). Dengan `set -e` di atas,
# seluruh deploy berhenti — bukan hanya videonya yang gagal, melainkan
# semuanya, karena berkas yang tak seorang pun ingat pernah menggantinya.
#
# `reset --hard` menutup keduanya. Yang menyimpang disalin dulu ke
# storage/ (di luar jangkauan reset karena diabaikan Git), supaya
# menegakkan Git tidak berarti kehilangan berkas yang mungkin satu-satunya
# salinannya ada di server.
# Yang terlacak DAN yang tak terlacak, keduanya.
#
# Semula hanya `git diff` yang dibaca, dan itu melewatkan separuh
# masalahnya. `git diff` hanya melihat berkas yang DILACAK Git; berkas
# yang diunggah langsung ke server dan namanya belum pernah ada di repo
# — public/media/galeri/energi.mp4, misalnya — tidak muncul di situ sama
# sekali.
#
# Berkas semacam itu memang selamat dari `reset --hard`, jadi ia tidak
# hilang hari ini. Tetapi ia juga tidak pernah tercatat di mana pun, dan
# justru itu bahayanya: satu-satunya salinan sesuatu yang dipakai situs,
# hidup di luar Git, tanpa seorang pun tahu ia ada sampai server diganti.
# Menyalinnya ke cadangan tidak memindahkannya ke repo, tetapi membuatnya
# TERSEBUT — dan yang tersebut dapat diputuskan nasibnya.
#
# `--exclude-standard` menjaga agar isi .gitignore tidak ikut tersalin;
# tanpa itu, seluruh vendor/, node_modules/, dan storage/ ikut masuk
# cadangan tiap deploy.
MENYIMPANG="$( { git diff --name-only "origin/$CABANG" -- .
                 git ls-files --others --exclude-standard; } | sort -u | head -200)"

if [ -n "$MENYIMPANG" ]; then
    # Nama diawali `backup-` supaya tercakup aturan /storage/backup-* di
    # .gitignore — kalau tidak, cadangannya sendiri menjadi berkas tak
    # terlacak yang membuat kirim.sh menolak deploy berikutnya.
    SIMPAN="$REPO_DIR/storage/backup-berkas-server/$(date +%Y%m%d-%H%M%S)"
    echo "==> Berkas yang menyimpang dari Git — disalin ke $SIMPAN"
    while IFS= read -r berkas; do
        [ -f "$berkas" ] || continue
        mkdir -p "$SIMPAN/$(dirname "$berkas")"
        cp -p "$berkas" "$SIMPAN/$berkas"
        echo "    $berkas"
    done <<< "$MENYIMPANG"
fi

git reset --hard "origin/$CABANG"

if [ -z "${EQOHSEE_DIMUAT_ULANG:-}" ] \
   && [ "$SIDIK_SEBELUM" != "$(sha256sum "$0" | cut -d' ' -f1)" ]; then
    echo "==> deploy.sh ikut diperbarui — menjalankan ulang versi barunya"
    export EQOHSEE_DIMUAT_ULANG=1
    exec bash "$0" "$@"
fi

echo "==> Installing PHP dependencies"
composer install --no-dev --optimize-autoloader

if ! command -v npm >/dev/null 2>&1; then
    echo "==> npm not found, installing nodejs/npm"
    apt-get install -y nodejs npm
fi

echo "==> Memasang dependensi Node"
# `npm ci` dipakai, bukan `npm install`: `install` boleh menulis ulang
# package-lock.json (versi npm/Node yang beda antara mesin dev dan server
# bisa meregenerasi lockfile-nya sedikit berbeda meski paketnya sama),
# meninggalkan working tree kotor. `ci` memasang persis apa yang tercatat
# di lockfile dan tidak pernah mengubahnya — begitu pula, ia gagal keras
# kalau package.json dan lockfile tidak sinkron, alih-alih diam-diam
# menambal keduanya. Deploy berikutnya jadi tidak lagi bentrok dengan
# `git pull` gara-gara berkas yang sebenarnya tidak ada yang menyunting.
#
# Dijalankan SEBELUM mode perawatan, bukan sesudahnya. Memasang paket Node
# tidak menyentuh apa pun yang dipakai melayani permintaan — PHP tidak
# membaca node_modules — jadi menurunkan situs untuk itu hanya memperpanjang
# padamnya tanpa menukar apa pun.
npm ci

# ── Pemeriksaan tipe, sebelum apa pun diturunkan ──────────────────
#
# Vite TIDAK memeriksa tipe sama sekali; ia membuang anotasinya lalu
# membundel. Berkas dengan `const x: number = "teks"` tetap menghasilkan
# "built in 5s" tanpa satu pun peringatan. Artinya `npm run build` yang
# hijau bukan bukti bahwa kodenya benar — ia hanya bukti bahwa kodenya
# dapat dibundel.
#
# Letaknya di sini, SEBELUM `php artisan down`, dan itu yang terpenting
# dari langkah ini. Ditaruh sesudahnya, galat tipe akan menurunkan situs
# lebih dulu lalu menghentikan deploy di tengah — meninggalkan kode PHP
# yang baru berdampingan dengan aset yang lama. Di sini, kegagalannya
# berhenti sebelum satu pengunjung pun terganggu, dan yang berjalan tetap
# versi lama yang utuh.
#
# node_modules baru saja dipasang ulang oleh `npm ci` di atas, jadi yang
# diperiksa adalah tipe kode baru terhadap dependensi barunya — bukan
# terhadap sisa pemasangan sebelumnya.
# ── Jatah memori pemeriksa tipe ──
#
# vue-tsc memuat SELURUH grafik tipe proyek sekaligus — 150-an halaman Vue
# beserta seluruh d.ts dependensinya. Node memilih batas old-space-nya dari
# memori yang terlihat saat ia mulai, dan pada VPS kecil batas itu jatuh di
# sekitar 480 MB: cukup untuk proyek yang lebih kecil, tidak cukup untuk
# yang ini. Yang terjadi kemudian bukan galat tipe melainkan
# "FATAL ERROR: Reached heap limit — JavaScript heap out of memory", dan
# prosesnya mati dengan status bukan-nol persis seperti galat tipe.
#
# Jatahnya dihitung dari memori yang BENAR-BENAR tersedia, bukan angka
# tetap: angka tetap yang terlalu besar membuat kernel membunuh prosesnya
# (OOM killer) alih-alih membuat Node menyerah dengan rapi, dan yang
# terbunuh bisa saja PHP-FPM yang sedang melayani pengunjung.
TIPE_TERSEDIA_MB=$(( $(awk '/^MemAvailable:/ {print $2}' /proc/meminfo 2>/dev/null || echo 0) / 1024 ))

# 70% dari yang tersedia, disisakan untuk kernel dan proses lain — di
# antaranya PHP-FPM yang masih melayani pengunjung, sebab langkah ini
# sengaja berjalan SEBELUM situs diturunkan.
TIPE_MB=$(( TIPE_TERSEDIA_MB * 7 / 10 ))
[ "$TIPE_MB" -gt 4096 ] && TIPE_MB=4096

TIPE_LOG=$(mktemp)

# Jatahnya hanya DINAIKKAN, tidak pernah diturunkan.
#
# Tanpa NODE_OPTIONS, Node memilih batasnya sendiri dari memori yang
# terlihat. Memasang angka yang lebih kecil daripada pilihannya sendiri
# akan MEMPERBURUK keadaan — mesin sempit yang sudah nyaris tidak cukup
# dibuat lebih sempit lagi oleh perintah yang dimaksudkan menolong.
# Karena itu batasnya hanya dipasang bila memang lebih lapang daripada
# yang biasa dipilih Node pada mesin sekelas ini.
if [ "$TIPE_MB" -ge 768 ]; then
    echo "==> Memeriksa tipe (tersedia ${TIPE_TERSEDIA_MB} MB, jatah ${TIPE_MB} MB)"
    export NODE_OPTIONS="--max-old-space-size=${TIPE_MB}"
else
    echo "==> Memeriksa tipe (tersedia ${TIPE_TERSEDIA_MB} MB — sempit, memakai bawaan Node)"
fi

if npm run --silent typecheck >"$TIPE_LOG" 2>&1; then
    unset NODE_OPTIONS
    echo "==> Tipe TypeScript & Vue bersih"
    rm -f "$TIPE_LOG"
else
    unset NODE_OPTIONS
    # ── Kehabisan memori BUKAN galat tipe ──
    #
    # Keduanya keluar dengan status bukan-nol, dan sebelum pembedaan ini
    # keduanya dilaporkan sebagai "ada galat tipe". Yang membacanya lalu
    # mencari kesalahan tipe yang tidak pernah ada — sementara yang
    # sebenarnya kurang adalah memori, dan tidak satu pun baris kode yang
    # perlu disentuh.
    if grep -qE "heap out of memory|Reached heap limit|JavaScript heap" "$TIPE_LOG"; then
        echo "==> GAGAL: pemeriksa tipe kehabisan memori — BUKAN galat tipe"
        echo
        echo "    vue-tsc berhenti sebelum sempat memeriksa apa pun, jadi tidak"
        echo "    ada yang perlu diperbaiki pada kodenya. Yang kurang memori"
        echo "    mesin ini — tersedia ${TIPE_TERSEDIA_MB} MB saat deploy berjalan."
        echo
        echo "    Yang biasanya menolong, berurutan:"
        echo "      1. Tambahkan swap bila belum ada — cukup sekali, permanen:"
        echo "           fallocate -l 2G /swapfile && chmod 600 /swapfile"
        echo "           mkswap /swapfile && swapon /swapfile"
        echo "           echo '/swapfile none swap sw 0 0' >> /etc/fstab"
        echo "      2. Hentikan sebentar yang memakan memori, lalu deploy lagi."
        echo "      3. Periksa tipenya di komputer Anda sendiri, lalu deploy"
        echo "         dengan EQOHSEE_LEWATI_TIPE=1 — aman selama sudah bersih"
        echo "         di tempat lain."
    else
        echo "==> GAGAL: ada galat tipe TypeScript/Vue"
        echo
        cat "$TIPE_LOG"
    fi

    echo
    echo "    Deploy dihentikan SEBELUM situs diturunkan; yang berjalan"
    echo "    masih versi lama yang utuh."
    echo
    echo "    Bila memang mendesak dan Anda menerima risikonya:"
    echo "        EQOHSEE_LEWATI_TIPE=1 bash deploy/deploy.sh"

    if [ "${EQOHSEE_LEWATI_TIPE:-0}" != "1" ]; then
        rm -f "$TIPE_LOG"
        exit 1
    fi

    rm -f "$TIPE_LOG"
    echo "    Dilanjutkan karena EQOHSEE_LEWATI_TIPE=1."
fi

# Mulai dari sini kode di disk sudah kode baru, sementara singgahan config,
# rute, dan tampilan masih milik kode lama, dan migrasinya belum jalan.
# Melayani pengunjung dalam keadaan setengah itu memunculkan galat yang
# menyesatkan — bukan gejala kerusakan, hanya deploy yang belum selesai.
# `up` dipasang sebagai jebakan EXIT supaya situs tetap kembali menyala
# walau ada langkah di bawah yang gagal dan skrip berhenti mendadak.
echo "==> Memasuki mode perawatan"
php artisan down --retry=60 2>/dev/null || true
trap 'php artisan up >/dev/null 2>&1 || true' EXIT

echo "==> Building frontend assets"
npm run build

echo "==> Detecting PHP-FPM socket"
PHP_SOCK=$(ls /run/php/*.sock 2>/dev/null | head -n1)
if [ -z "$PHP_SOCK" ]; then
    echo "ERROR: no PHP-FPM socket found in /run/php/. Is php-fpm running?"
    exit 1
fi
echo "    Using socket: $PHP_SOCK"

# ── Vhost Nginx ───────────────────────────────────────────────────
# Templatnya dipilih menurut ada tidaknya sertifikat di disk, bukan menurut
# EQOHSEE_SSL. Sebelumnya vhost selalu dibangkitkan dari templat yang hanya
# mengenal `listen 80`, sementara certbot menambahkan blok 443-nya ke berkas
# yang sama — jadi setiap deploy biasa (tanpa EQOHSEE_SSL=1) menghapus HTTPS
# yang sudah jalan. Tidak ada yang mendengarkan di 443 lagi, dan peramban
# yang sudah pernah dialihkan ke https menerima ERR_CONNECTION_REFUSED.
# Karena Laravel tidak pernah dijalankan, lognya bersih dan penyebabnya tidak
# terlihat sama sekali dari sisi aplikasi.
BERKAS_VHOST="/etc/nginx/sites-available/eqohsee"
SERTIFIKAT="/etc/letsencrypt/live/$DOMAIN_UTAMA/fullchain.pem"

if [ -f "$SERTIFIKAT" ]; then
    TEMPLAT="$REPO_DIR/deploy/nginx-eqohsee-ssl.conf"
    echo "==> Installing Nginx vhost + HTTPS (server_name: $SERVER_NAME)"
else
    TEMPLAT="$REPO_DIR/deploy/nginx-eqohsee.conf"
    echo "==> Installing Nginx vhost, HTTP saja (server_name: $SERVER_NAME)"
    echo "    Belum ada sertifikat di $SERTIFIKAT."
    echo "    Terbitkan sekali dengan: EQOHSEE_SSL=1 bash deploy/deploy.sh"
fi

# Ditulis ke berkas sementara lalu diuji, bukan langsung ke tempatnya.
# `nginx -t` menguji seluruh konfigurasi sekaligus, jadi templat yang rusak
# akan menahan `systemctl restart` — dan nginx yang berhenti membawa serta
# seluruh situs, bukan hanya perubahan yang salah.
sed -e "s#__SERVER_NAME__#${SERVER_NAME}#g" \
    -e "s#__PHP_FPM_SOCK__#${PHP_SOCK}#g" \
    -e "s#__DOMAIN__#${DOMAIN_UTAMA}#g" \
    "$TEMPLAT" > "$BERKAS_VHOST.baru"

[ -f "$BERKAS_VHOST" ] && cp "$BERKAS_VHOST" "$BERKAS_VHOST.sebelumnya"
mv "$BERKAS_VHOST.baru" "$BERKAS_VHOST"

ln -sf "$BERKAS_VHOST" /etc/nginx/sites-enabled/eqohsee
rm -f /etc/nginx/sites-enabled/default

mkdir -p /var/www/html/.well-known/acme-challenge

echo "==> Testing Nginx config"
if ! nginx -t; then
    echo "!!  Konfigurasi baru ditolak nginx — vhost dikembalikan ke yang lama."
    if [ -f "$BERKAS_VHOST.sebelumnya" ]; then
        mv "$BERKAS_VHOST.sebelumnya" "$BERKAS_VHOST"
        nginx -t && systemctl reload nginx
    fi
    exit 1
fi

echo "==> Restarting services"
systemctl restart nginx
PHP_SERVICE=$(systemctl list-units --type=service --all | grep -o 'php[0-9.]*-fpm.service' | head -n1)
if [ -n "$PHP_SERVICE" ]; then
    systemctl restart "$PHP_SERVICE"
else
    systemctl restart php-fpm 2>/dev/null || true
fi

echo "==> Setting permissions"
chown -R www-data:www-data "$REPO_DIR"
chmod -R 775 "$REPO_DIR/storage" "$REPO_DIR/bootstrap/cache"

# Cadangan diambil sebelum migrasi, bukan sesudah. Migrasi yang menghapus
# atau mengubah kolom tidak punya jalan pulang: `down()` mengembalikan
# bentuk tabelnya, bukan isinya. Untuk aplikasi yang menyimpan rekaman
# audit SMKP dan ISO, isi itulah yang tidak tergantikan.
echo "==> Mencadangkan basis data"
CADANGAN="$REPO_DIR/storage/backup-otomatis"
mkdir -p "$CADANGAN"
STEMPEL="$(date +%Y%m%d-%H%M%S)"
DB_CONN="$(grep -E '^DB_CONNECTION=' "$REPO_DIR/.env" | cut -d= -f2- | tr -d '"' || echo sqlite)"

case "$DB_CONN" in
    sqlite)
        DB_BERKAS="$(grep -E '^DB_DATABASE=' "$REPO_DIR/.env" | cut -d= -f2- | tr -d '"' || true)"
        DB_BERKAS="${DB_BERKAS:-$REPO_DIR/database/database.sqlite}"
        if [ -f "$DB_BERKAS" ]; then
            cp "$DB_BERKAS" "$CADANGAN/db-$STEMPEL.sqlite"
            echo "    $CADANGAN/db-$STEMPEL.sqlite"
        else
            echo "    Berkas sqlite belum ada — dilewati."
        fi
        ;;
    pgsql)
        if command -v pg_dump >/dev/null 2>&1; then
            DB_NAMA="$(grep -E '^DB_DATABASE=' "$REPO_DIR/.env" | cut -d= -f2- | tr -d '"')"
            DB_USER="$(grep -E '^DB_USERNAME=' "$REPO_DIR/.env" | cut -d= -f2- | tr -d '"')"
            DB_HOST="$(grep -E '^DB_HOST=' "$REPO_DIR/.env" | cut -d= -f2- | tr -d '"')"
            PGPASSWORD="$(grep -E '^DB_PASSWORD=' "$REPO_DIR/.env" | cut -d= -f2- | tr -d '"')" \
                pg_dump -h "${DB_HOST:-127.0.0.1}" -U "$DB_USER" "$DB_NAMA" \
                > "$CADANGAN/db-$STEMPEL.sql" \
                && echo "    $CADANGAN/db-$STEMPEL.sql" \
                || echo "!!  pg_dump gagal — migrasi tetap dilanjutkan tanpa cadangan."
        else
            echo "!!  pg_dump tidak terpasang — migrasi berjalan tanpa cadangan."
        fi
        ;;
    *)
        echo "    DB_CONNECTION '$DB_CONN' tidak dikenal — dilewati."
        ;;
esac

# Sepuluh cadangan terakhir disimpan; selebihnya dibuang supaya disk VPS
# tidak diam-diam penuh oleh berkas yang tidak pernah ada yang menghapus.
ls -1t "$CADANGAN"/db-* 2>/dev/null | tail -n +11 | xargs -r rm -f

echo "==> Running database migrations"
php artisan migrate --force

# Master data modul Investigasi — matriks risiko 5x5, klasifikasi menurut
# Kepmen ESDM 1827/2018 dan Kepdirjen Minerba 185/2019, hierarki
# pengendalian, kamus penyebab SCAT 252 butir, dan bank soal wawancara.
#
# Dijalankan pada SETIAP deploy, bukan sekali saat pemasangan, dan itu
# disengaja: isinya bertambah bersama kode — kamus yang panjangnya
# berubah, klasifikasi yang direvisi mengikuti regulasi baru — dan
# perintah yang hanya dijalankan sekali akan membuat server berjalan
# dengan kamus versi lama tanpa satu pun tanda.
#
# Aman diulang: penyimpanannya idempoten berdasar kode, sehingga matriks
# tetap 25 sel dan kamus tetap 252 butir berapa kali pun ia dipanggil.
#
# TANPA LANGKAH INI triase tidak menghasilkan level apa pun — dan
# kegagalannya diam: layarnya terbuka, matriksnya kosong, dan insiden
# tersimpan tanpa level seolah memang belum ditriase.
echo "==> Installing Investigasi master data"
php artisan investigasi:pasang

# Daftar periksa prakualifikasi SMKP modul PJP — 17 kategori, 126 butir.
#
# Aman diulang, dan itu bukan sekadar kerapian: penyimpanannya
# MEMPERBARUI, tidak pernah menghapus. Jawaban tiap mitra menunjuk ke
# butirnya lewat kunci asing yang cascade on delete, sehingga penyemai
# yang mengosongkan tabelnya lebih dahulu akan menghapus seluruh
# jawaban daftar periksa setiap mitra — dan yang tersisa hanyalah
# daftar periksa yang kembali kosong tanpa satu pun galat.
#
# TANPA LANGKAH INI halaman daftar periksa terbuka tanpa satu
# pertanyaan pun, dan skor kepatuhannya menjawab 0% bagi setiap mitra.
echo "==> Installing PJP checklist master data"
php artisan pjp:pasang

# Daftar awal bersama modul Miners — departemen, jabatan, lokasi kerja,
# golongan unit beserta kelas SIMPOL-nya, jenis permit, dan hasil MCU.
#
# Aman diulang: baris yang sudah ada hanya DIPERBARUI pada kolom yang
# berasal dari SOP — kelas SIM, kewajiban SIO, masa berlaku tipe permit
# — dan tidak pada namanya, sebab nama itulah yang tercetak pada kartu
# yang sudah terbit. Tidak satu baris pun dihapus: `mnr_pekerja`,
# `mnr_permit`, dan `mnr_simper_unit` menunjuk ke daftar ini, dan
# penyemai yang mengosongkannya lebih dahulu akan memutus rujukan
# ribuan dokumen yang sudah berjalan.
#
# TANPA LANGKAH INI modulnya tidak dapat dipakai sama sekali, dan
# kegagalannya diam: formulir MCU terbuka dengan daftar hasil yang
# kosong, formulir permit tanpa satu jenis permit pun, lalu
# penyimpanannya gagal di tingkat basis data. Yang terlihat pengguna
# hanyalah galat 500 tanpa sebab.
echo "==> Installing Miners master data"
php artisan miners:pasang

# Katalog jual — paket website dan tiap aplikasi di dalamnya.
#
# Dijalankan tiap deploy dengan alasan yang sama: modul baru yang
# ditambahkan ke aplikasi harus ikut muncul di katalog, dan yang lupa
# ditambahkan tidak dapat dijual sama sekali.
#
# TIDAK menimpa harga maupun keaktifan yang sudah diisi — butir yang
# sudah ada hanya diperbarui nama dan urutannya. Butir baru lahir
# berharga nol dan tidak aktif, sehingga tidak dapat terjual sebelum
# harganya ditetapkan orang lewat layar Pembelian → Daftar Harga.
#
# Arah sebaliknya juga dikerjakan di sini, dan itu yang lebih penting:
# butir yang modulnya sudah tidak ada lagi berhenti dijual. Yang belum
# pernah dipesan dibuang; yang pernah dipesan hanya dinonaktifkan,
# supaya tagihan yang sudah terbit tidak kehilangan nama barangnya.
php artisan pembelian:katalog

echo "==> Linking public storage"
# `--quiet` menekan ERROR merah "link already exists" yang muncul pada
# SETIAP deploy sesudah yang pertama. Tautannya memang sudah ada dan
# memang sudah benar; yang salah hanyalah kata "ERROR" di keluarannya.
#
# Bukan soal kerapian. Keluaran deploy adalah satu-satunya tempat orang
# melihat ada tidaknya yang gagal, dan baris merah yang selalu muncul
# tanpa pernah berarti apa-apa melatih mata melewati warna merah — lalu
# baris merah yang sungguh-sungguh penting ikut terlewat.
php artisan storage:link --quiet 2>/dev/null || true

# Berkas tertutup dipindahkan keluar dari disk publik.
#
# Kodenya sudah menyimpan ke tempat yang benar, tetapi berkas yang
# terlanjur ada tidak berpindah sendiri — dan justru itulah yang penting:
# dokumen terkendali, foto insiden, dan gambar tanda tangan yang selama
# ini terbaca dari /storage/… tanpa login. Selama berkas lama masih di
# sana, perbaikan kodenya belum menutup apa pun.
#
# Aman diulang, dan tidak menghentikan deploy bila ada yang gagal: berkas
# yang gagal pindah tetap di tempat lamanya, dan itu keadaan yang sama
# dengan sebelum deploy — bukan lebih buruk.
echo "==> Mengamankan berkas unggahan"
php artisan berkas:amankan || echo "    Sebagian berkas gagal dipindah; jalankan ulang: php artisan berkas:amankan"

echo "==> Laravel optimize"
cd "$REPO_DIR"
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ── HTTPS (opsional) ──────────────────────────────────────────────
if [ "${EQOHSEE_SSL:-0}" = "1" ]; then
    echo "==> Menerbitkan sertifikat HTTPS untuk $DOMAIN_UTAMA"

    if ! command -v certbot >/dev/null 2>&1; then
        apt-get update -y && apt-get install -y certbot python3-certbot-nginx
    fi

    # `certonly --webroot`, bukan `--nginx`. Plugin nginx menyunting vhost
    # yang setiap deploy ditulis ulang dari repo, sehingga suntingannya hilang
    # pada deploy berikutnya — persis cacat yang diperbaiki di atas. Dengan
    # certonly, certbot hanya mengurus sertifikatnya; bentuk vhost sepenuhnya
    # ditentukan nginx-eqohsee-ssl.conf, dan itu ikut versi bersama kode.
    #
    # Hanya nama yang berupa domain yang diajukan; certbot menolak alamat IP.
    DOMAIN_ARGS=""
    for n in $SERVER_NAME; do
        case "$n" in
            *[a-zA-Z]*) DOMAIN_ARGS="$DOMAIN_ARGS -d $n" ;;
        esac
    done

    if certbot certonly --webroot -w /var/www/html $DOMAIN_ARGS \
        --non-interactive --agree-tos --keep-until-expiring \
        --deploy-hook "systemctl reload nginx" \
        -m "${EQOHSEE_EMAIL:-admin@${DOMAIN_UTAMA}}"; then

        # Sertifikatnya baru ada sesudah vhost dipasang di atas, jadi
        # vhost-nya dibangun ulang sekarang — kali ini dengan blok 443.
        if [ -f "$SERTIFIKAT" ]; then
            echo "==> Memasang ulang vhost dengan HTTPS"
            cp "$BERKAS_VHOST" "$BERKAS_VHOST.sebelumnya"
            sed -e "s#__SERVER_NAME__#${SERVER_NAME}#g" \
                -e "s#__PHP_FPM_SOCK__#${PHP_SOCK}#g" \
                -e "s#__DOMAIN__#${DOMAIN_UTAMA}#g" \
                "$REPO_DIR/deploy/nginx-eqohsee-ssl.conf" > "$BERKAS_VHOST"

            if nginx -t; then
                systemctl reload nginx
            else
                echo "!!  Vhost HTTPS ditolak nginx — dikembalikan ke HTTP."
                mv "$BERKAS_VHOST.sebelumnya" "$BERKAS_VHOST"
                nginx -t && systemctl reload nginx
            fi
        fi
    else
        echo "!!  Certbot gagal. Situs tetap berjalan lewat HTTP."
        echo "!!  Pastikan DNS $DOMAIN_UTAMA sudah mengarah ke server ini, lalu ulangi."
    fi
fi

# ── Pemeriksaan APP_URL ───────────────────────────────────────────
# APP_URL dipakai untuk menyusun alamat mutlak: tautan di surel, dan
# alamat aset. Bila ia masih menunjuk IP sementara situsnya sudah diakses
# lewat domain, tautan pada surel yang keluar akan mengarah ke IP —
# terlihat mencurigakan bagi penerimanya, dan gagal begitu IP berganti.
#
# Skemanya ditentukan oleh ada tidaknya sertifikat, bukan oleh EQOHSEE_SSL.
# Bendera itu hanya menyatakan "terbitkan sertifikat pada jalannya kali ini";
# memakainya di sini membuat setiap deploy biasa pada situs ber-HTTPS
# menyarankan APP_URL kembali ke http:// — saran yang justru merusak.
APP_URL_KINI="$(grep -E '^APP_URL=' "$REPO_DIR/.env" | cut -d= -f2- | tr -d '"' || true)"

if [ -f "$SERTIFIKAT" ]; then
    SKEMA="https"
else
    SKEMA="http"
fi
APP_URL_HARAP="${SKEMA}://${DOMAIN_UTAMA}"

if [ "$APP_URL_KINI" != "$APP_URL_HARAP" ]; then
    echo "!!  APP_URL di .env masih '$APP_URL_KINI'."
    echo "!!  Sebaiknya '$APP_URL_HARAP' — lalu jalankan: php artisan config:cache"
fi

echo "==> Keluar dari mode perawatan"
php artisan up

echo "==> Selesai. Buka: ${SKEMA}://$DOMAIN_UTAMA"
echo "    Nama yang dilayani: $SERVER_NAME"
if [ "$SKEMA" = "http" ]; then
    echo "    HTTPS belum aktif. Sekali saja: EQOHSEE_SSL=1 bash deploy/deploy.sh"
fi

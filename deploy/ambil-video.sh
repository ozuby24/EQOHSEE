#!/usr/bin/env bash
#
# Ambil video dari Google Drive dan pasang ke public/media.
#
# MENGAPA SKRIP, BUKAN LANGSUNG DIMASUKKAN KE GIT
#
# Wadah tempat perubahan ini dibuat tidak dapat menjangkau satu pun
# alamat unduhan Google — drive.usercontent.google.com ditolak proksi
# jaringannya dengan 403, dan Drive API menuntut kunci. Satu-satunya
# jalur yang tersedia di sana memulangkan berkas sebagai teks base64 ke
# dalam percakapan, dan tiga puluh lima megabita video tidak muat di
# sana. Server Anda tidak punya batasan itu.
#
# CARA PAKAI
#
#   bash deploy/ambil-video.sh              # unduh + kecilkan + pasang
#   bash deploy/ambil-video.sh --tanpa-kecil # unduh dan pasang apa adanya
#
# Sesudah menjalankannya, periksa hasilnya di situs, lalu:
#
#   git add public/media && git commit -m "Ganti video dengan rekaman baru"
#
# Berkas Drive-nya berbagi "siapa pun dengan tautan", jadi tidak perlu
# masuk akun. Bila suatu saat berbaginya ditutup, unduhan akan
# memulangkan halaman HTML alih-alih video — skrip ini memeriksanya dan
# berhenti, bukan menimpa video yang ada dengan berkas rusak.

set -euo pipefail

AKAR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TUJUAN="$AKAR/public/media"
KERJA="$(mktemp -d)"
trap 'rm -rf "$KERJA"' EXIT

KECILKAN=1
[[ "${1:-}" == "--tanpa-kecil" ]] && KECILKAN=0

# ── Peta berkas ───────────────────────────────────────────────────────
#
# Kolom: id-drive|tujuan|nama-di-drive
#
# Dua berkas di Drive sama-sama bernama "QUALITY EQOHSEE.mp4" padahal
# isinya berbeda, dan tidak ada berkas bernama SAFETY maupun
# ENVIRONMENT — sementara situsnya memakai keduanya. Penetapan di bawah
# karena itu MENEBAK untuk dua baris yang ditandai (?), dan tebakan itu
# harus diperiksa dengan mata sesudah unduhan. Skrip ini menyimpan
# setiap berkas dengan nama aslinya juga, di public/media/asal-drive/,
# supaya penukarannya cukup dengan menyalin ulang bila tebakannya salah.
PETA=(
  "1UrX1yhhMxh5J8i36PEyoNjy_uvFrmBZk|hero/tambang.mp4|LANDING PAGE"
  "1R0gvO71TfHcWrZUvhGpCM-kM8pkKcPpD|hero/masuk.mp4|REGISTER PAGE"
  "1LXXOzOAzgf4rZpns7PxQXLyH56_XEMOB|galeri/engineering.mp4|ENGINEERING"
  "1fStWWOhrfccv71CWXd9pCG0bkuJzU2Fd|galeri/hygiene.mp4|HYGIENE"
  "1iOX7tcPj9CXNriJY74B4_mK2dA-C0lM3|galeri/occhealth.mp4|OCCUPATIONAL_HEALTH"
  "1OsbL2teIrqE3k5PhAhvGENsp513AnViO|galeri/operasional.mp4|MINING"
  "1KFqCcjOlVotqFGNtcnGffsdOVHG12--f|galeri/quality.mp4|QUALITY (1)"
  "1I6FgpTb70q6ONGi3rwQ2B6UrDQouSfs9|galeri/safety.mp4|QUALITY (2) — (?) diduga SAFETY"
  "13BjNDH9MiFXkunbppbck4iG_-NC_v2BA|galeri/environment.mp4|EBT — (?) diduga ENVIRONMENT"
  "1XL0_GBQgzHuwh7Nbl7Zxyw_Eb2G3ZpyN|galeri/energi.mp4|ENERGY MINING — belum dipakai halaman mana pun"
)

unduh() {   # $1 = id, $2 = keluaran
  local id="$1" keluar="$2"

  # Berkas besar disela halaman "tidak dapat dipindai virus". Token
  # konfirmasinya diambil dari jawaban pertama lalu dikirim ulang.
  curl -sSL -c "$KERJA/kuki" -o "$keluar" \
    "https://drive.usercontent.google.com/download?id=${id}&export=download&confirm=t" || return 1

  # Jawaban yang berupa HTML berarti unduhannya tidak jadi — berbagi
  # ditutup, atau kuotanya habis. Jangan sampai ini menimpa video.
  if head -c 512 "$keluar" | grep -qi '<html\|<!doctype'; then
    return 2
  fi

  # Video yang sah diawali kotak ftyp pada byte kelima.
  if ! head -c 12 "$keluar" | tail -c 8 | grep -q 'ftyp'; then
    return 3
  fi
}

kecilkan() {   # $1 = masukan, $2 = keluaran
  # 1280 lebar, CRF 28, tanpa suara: cukup untuk latar halaman, dan
  # itulah satu-satunya tempat video ini dipakai. Video 11 MB yang
  # dimuat pada tiap kunjungan halaman depan mahal bagi pengunjung di
  # jaringan site.
  ffmpeg -y -loglevel error -i "$1" \
    -vf "scale='min(1280,iw)':-2" \
    -c:v libx264 -crf 28 -preset slow -profile:v high -pix_fmt yuv420p \
    -movflags +faststart -an "$2"
}

command -v curl >/dev/null || { echo "curl tidak ada."; exit 1; }

if [[ $KECILKAN -eq 1 ]] && ! command -v ffmpeg >/dev/null; then
  echo "ffmpeg tidak ada — dipasang apa adanya tanpa dikecilkan."
  echo "  (pasang dengan: sudo apt install -y ffmpeg, lalu jalankan lagi)"
  KECILKAN=0
fi

mkdir -p "$TUJUAN/hero" "$TUJUAN/galeri" "$TUJUAN/asal-drive"

GAGAL=0
for baris in "${PETA[@]}"; do
  IFS='|' read -r id tujuan asal <<< "$baris"

  printf '  %-28s ← %s\n' "$tujuan" "$asal"

  mentah="$KERJA/$(basename "$tujuan")"

  if ! unduh "$id" "$mentah"; then
    case $? in
      2) echo "      GAGAL: Drive memulangkan halaman, bukan video."
         echo "             Periksa berbagi folder masih 'siapa pun dengan tautan'." ;;
      3) echo "      GAGAL: berkasnya bukan MP4 yang sah." ;;
      *) echo "      GAGAL: unduhan tidak selesai." ;;
    esac
    GAGAL=1
    continue
  fi

  # Simpan salinan mentahnya, supaya penetapan yang salah dapat ditukar
  # tanpa mengunduh ulang.
  cp "$mentah" "$TUJUAN/asal-drive/$(basename "$tujuan")"

  mkdir -p "$(dirname "$TUJUAN/$tujuan")"

  if [[ $KECILKAN -eq 1 ]]; then
    if kecilkan "$mentah" "$TUJUAN/$tujuan"; then
      printf '      %s → %s\n' \
        "$(du -h "$mentah" | cut -f1)" "$(du -h "$TUJUAN/$tujuan" | cut -f1)"
    else
      echo "      ffmpeg gagal; dipasang apa adanya."
      cp "$mentah" "$TUJUAN/$tujuan"
    fi
  else
    cp "$mentah" "$TUJUAN/$tujuan"
    printf '      %s\n' "$(du -h "$TUJUAN/$tujuan" | cut -f1)"
  fi
done

echo
if [[ $GAGAL -eq 1 ]]; then
  echo "Sebagian gagal. Video yang lama TIDAK ditimpa untuk yang gagal itu."
  exit 1
fi

cat <<'CATATAN'
Selesai. Dua hal yang perlu diperiksa dengan mata sebelum commit:

  1. galeri/safety.mp4      — diambil dari berkas Drive kedua yang juga
                              bernama "QUALITY EQOHSEE.mp4". Bila isinya
                              ternyata soal mutu, bukan keselamatan,
                              tukar dengan berkas yang benar.

  2. galeri/environment.mp4 — diambil dari "EBT EQOHSEE.mp4".

Salinan mentah tiap berkas ada di public/media/asal-drive/ bila perlu
ditukar. Buang folder itu sebelum commit — ia hanya alat bantu:

  rm -rf public/media/asal-drive
  git add public/media && git commit -m "Ganti video dengan rekaman baru"
CATATAN

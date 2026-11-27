# Media halaman depan

Halaman depan dibangun di atas rekaman tambang sungguhan. Selama berkasnya
belum ada, halaman memakai panorama SVG sebagai gantinya — jadi tampilannya
tetap utuh dan tidak pernah menunjukkan gambar rusak.

Menambah atau mengganti media cukup menyalin berkas ke folder di bawah ini
dengan **nama yang sama persis**. Tidak ada kode yang perlu disunting.

## Yang sudah terpasang

| Berkas | Isi |
|---|---|
| `hero/tambang.mp4` · `.jpg` | Excavator memuat dump truck — latar hero |
| `galeri/inspeksi.mp4` · `.jpg` | Pemeriksaan unit dan area kerja |
| `galeri/operasional.mp4` · `.jpg` | Gali-muat-angkut harian |
| `galeri/risiko.mp4` · `.jpg` | Pengamatan bahaya di lapangan |
| `galeri/budaya.mp4` · `.jpg` | Pemakaian alat pelindung diri |

Kartu **Energi & Karbon** (`galeri/energi.*`) sementara memakai foto jalan
angkut dan klip gerak pelan dari foto itu. Rekaman aslinya, "ENERGY MINING"
di Google Drive, terpasang dengan `bash deploy/ambil-video.sh` di server;
skrip itu sekaligus mengganti `galeri/energi.jpg` dengan bingkai dari
rekamannya supaya gambar diam dan videonya adegan yang sama.

## Hero

| Berkas | Isi | Anjuran |
|---|---|---|
| `hero/tambang.mp4` | Video latar hero, 8–15 detik, berputar mulus | 1920×1080, H.264, **tanpa suara**, di bawah 6 MB |
| `hero/tambang.jpg` | Gambar diam hero | 1920×1080, JPG mutu 80, di bawah 400 KB |

Video hanya dipasang di layar lebar. Di ponsel dan pada perangkat yang
menyetel *reduce motion*, gambar diamlah yang dipakai — memutar video
belasan megabita lewat jaringan site tambang bukan kesan mewah, melainkan
halaman yang tidak kunjung muncul.

Bila `hero/tambang.jpg` ada tetapi videonya tidak, hero memakai foto itu.
Bila keduanya belum ada, panorama SVG yang dipakai.

## Galeri lapangan

Semua di folder `galeri/`. Gambar 16∶9, anjuran 1280×720.

Rekaman dari Drive ditutup kartu judul teks ("SAFETY — Zero Harm Starts
With Us", logo EQOHSEE, dan sebagainya). Di kartu yang dipotong mengikuti
bingkainya teks besar itu terpotong dan terbaca sebagai video rusak, jadi
video di sini sudah dipotong di bingkai bersih terakhir sebelum teksnya
muncul. `deploy/ambil-video.sh` memotong di detik yang sama (kolom
keempat petanya); video pengganti juga harus berakhir sebelum teks penutup.

| Berkas | Kartu |
|---|---|
| `inspeksi.jpg` · `inspeksi.mp4` | Inspeksi & Observasi |
| `operasional.jpg` · `operasional.mp4` | Operasional Tambang |
| `risiko.jpg` · `risiko.mp4` | Pengendalian Risiko |
| `budaya.jpg` · `budaya.mp4` | Budaya Keselamatan |
| `energi.jpg` · `energi.mp4` | Energi & Karbon |
| `environment.jpg` · `environment.mp4` | Reklamasi & Lingkungan |

Berkas `.mp4` bersifat pilihan. Kartu yang punya video ditandai tombol
putar dan videonya terbuka di jendela; kartu tanpa video tampil sebagai
foto biasa.

Kartu yang belum punya berkas apa pun tidak ditampilkan selama masih ada
kartu lain yang berkasnya lengkap — menyandingkan rekaman sungguhan dengan
kotak kosong membuat galerinya terbaca sebagai rusak, bukan sebagai belum
lengkap. Bila belum ada satu pun berkas, semua kartu muncul sebagai tempat
foto agar bagian ini tidak hilang sama sekali.

## Aplikasi lapangan — laptop dan ponsel

Bagian "04 — Aplikasi lapangan" memajang satu laptop dan dua ponsel dari
modul yang sama, dengan tab per modul. Semuanya tangkapan layar asli dari
data contoh platform; layar ponselnya digeser ke bagian yang berisi angka.

| Tab | Laptop | Ponsel |
|---|---|---|
| MERANTI | `terbaru/meranti.webp` | `aplikasi/meranti-a.webp`, `aplikasi/meranti-b.webp` |
| Rehab DAS | `terbaru/rdas.webp` | `aplikasi/rdas-a.webp`, `aplikasi/rdas-b.webp` |
| Lapor & P2H | `aplikasi/lapangan-laptop.webp` | `aplikasi/lapangan-a.webp`, `aplikasi/lapangan-b.webp` |
| Energi & Karbon | `terbaru/energi.webp` | `aplikasi/energi-a.webp`, `aplikasi/energi-b.webp` |
| Lifting & Scaffolding | `terbaru/angkat.webp` | `aplikasi/angkat-a.webp`, `aplikasi/angkat-b.webp` |

Laptop 1280×800 (layar 1440×900 diperkecil), ponsel 600×1298 (layar
390×844), WebP mutu 80. Sebuah tab tampil hanya bila ketiga berkasnya ada.

## Logo perusahaan pengguna

Taruh di `klien/`, satu berkas per perusahaan — `adaro.svg`,
`bukit-asam.png`, dan seterusnya. Nama berkas menjadi teks alternatifnya.
Anjuran: SVG atau PNG latar tembus pandang, tinggi 80 px.

**Folder ini sengaja dibiarkan kosong.** Memajang logo sebuah perusahaan
berarti menyatakan mereka memakai platform ini, dan pernyataan itu hanya
boleh dibuat oleh yang menaruh logonya — bukan oleh kode yang menebak.
Selama folder ini kosong, bagian tersebut menampilkan standar dan regulasi
yang diacu, yang memang dapat diperiksa kebenarannya.

## Menyiapkan berkas

```bash
# Video hero: buang suara — video latar tidak pernah berbunyi — lalu tekan
# ukurannya. faststart menaruh indeksnya di depan supaya pemutaran mulai
# sebelum seluruh berkas terunduh.
ffmpeg -i sumber.mp4 -t 12 -an -c:v libx264 -crf 28 -preset slow \
       -pix_fmt yuv420p -movflags +faststart public/media/hero/tambang.mp4

# Gambar diam hero, diambil dari video yang sama supaya keduanya menyatu
# saat video mulai memudar masuk
ffmpeg -i public/media/hero/tambang.mp4 -ss 2 -frames:v 1 -q:v 4 \
       public/media/hero/tambang.jpg

# Video galeri: suaranya dipertahankan, sebab pemutarnya punya kendali
ffmpeg -i sumber.mp4 -c:v libx264 -crf 29 -preset slow -pix_fmt yuv420p \
       -c:a aac -b:a 96k -movflags +faststart public/media/galeri/energi.mp4
ffmpeg -i public/media/galeri/energi.mp4 -ss 2 -frames:v 1 -q:v 4 \
       public/media/galeri/energi.jpg
```

Setelah menyalin berkas, jalankan `php artisan optimize:clear` bila
halaman masih menampilkan versi lama.

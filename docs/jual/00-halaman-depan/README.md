# Halaman publik

Tiga halaman yang dilihat calon pembeli **sebelum** ia masuk: beranda,
halaman delapan aspek, dan etalase harga. Folder ini dipisahkan dari
folder fitur karena isinya bukan aplikasi yang dijual satuan, melainkan
etalasenya sendiri.

Tiap halaman diambil pada dua lebar: **1440px** untuk proposal dan
paparan, **390px** untuk memeriksa bagaimana ia terbaca di ponsel —
yang dipakai sebagian besar orang yang membuka tautannya dari WhatsApp.

| Berkas | Halaman | Alamat |
|---|---|---|
| `01-beranda-lebar.jpg` · `01-beranda-ponsel.jpg` | Beranda: pilar, modul, harga, cara kerja | `/` |
| `02-pilar-lebar.jpg` · `02-pilar-ponsel.jpg` | Delapan aspek EQOHSEE | `/pilar` |
| `03-etalase-harga-lebar.jpg` · `03-etalase-harga-ponsel.jpg` | Etalase: paket, layanan tahunan, aplikasi satuan | `/katalog` |
| `04-pilar-panel-samping.jpg` | Rincian aspek terbuka di kolom kanan | `/#pilar` |

## Kenapa folder ini terpisah dari 23 folder fitur

Folder fitur berisi layar **di dalam** aplikasi, diambil dengan akun
admin dan data contoh. Halaman di sini terbuka untuk umum dan tidak
menampilkan data siapa pun.

Keduanya juga berubah pada waktu yang berbeda: layar di dalam aplikasi
ikut berubah ketika modulnya dikerjakan, sedangkan halaman ini berubah
ketika harga atau cara menjualnya yang berubah. Menyatukannya berarti
menerbitkan ulang 188 gambar hanya karena satu kalimat pada beranda
diperbaiki.

## Menerbitkan ulang

Halaman ini tidak ikut `tools/tembak-fitur.mjs` — pembuat itu membaca
daftar halamannya dari `App\Support\Menu`, dan beranda bukan bagian dari
menu aplikasi. Ambil ulang dengan Playwright pada ketiga alamat di atas,
gulir sampai bawah lebih dulu supaya bagian yang muncul saat digulir
ikut tergambar.

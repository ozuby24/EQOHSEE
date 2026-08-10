# Media halaman depan

Halaman depan dirancang untuk foto dan video tambang sungguhan. Selama
berkasnya belum ada, halaman memakai panorama SVG sebagai gantinya — jadi
tampilannya tetap utuh dan tidak pernah menunjukkan gambar rusak.

Menambah media cukup menyalin berkas ke folder di bawah ini dengan **nama
yang sama persis**. Tidak ada kode yang perlu disunting.

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

| Berkas | Kartu |
|---|---|
| `inspeksi.jpg` · `inspeksi.mp4` | Inspeksi & Observasi |
| `operasional.jpg` · `operasional.mp4` | Operasional Tambang |
| `risiko.jpg` | Pengendalian Risiko |
| `briefing.jpg` | Safety Briefing |
| `energi.jpg` | Kinerja Energi |
| `lingkungan.jpg` | Reklamasi & Lingkungan |

Berkas `.mp4` bersifat pilihan. Kartu yang punya video ditandai tombol
putar dan videonya terbuka di jendela; kartu tanpa video tampil sebagai
foto biasa.

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
# Video hero: potong 12 detik, buang suara, kecilkan
ffmpeg -i sumber.mp4 -t 12 -an -vf scale=1920:-2 -c:v libx264 -crf 26 -preset slow \
       public/media/hero/tambang.mp4

# Gambar diam hero, diambil dari detik ke-1 video yang sama
ffmpeg -i public/media/hero/tambang.mp4 -ss 1 -frames:v 1 -q:v 3 \
       public/media/hero/tambang.jpg
```

Setelah menyalin berkas, jalankan `php artisan optimize:clear` bila
halaman masih menampilkan versi lama.

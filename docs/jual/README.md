# Bahan jual per fitur

Satu folder per aplikasi. Tiap folder berisi tangkapan layar SELURUH
halaman fitur itu, sebuah `README.md` yang mendaftarnya, dan
`daftar.json` bila ingin diolah perkakas lain.

Dipakai untuk menawarkan aplikasi **satuan** — calon pembeli yang hanya
butuh SMKP tidak perlu dibawa berkeliling seluruh website.

## Datanya data contoh, bukan data pelanggan

Seluruh gambar diambil dari `php artisan demo:pasang` — enam perusahaan
karangan dengan angka yang masuk akal. Tidak ada satu pun data pelanggan
sungguhan di dalamnya, dan itu disengaja: bahan jual yang memuat data
pelanggan adalah kebocoran yang dibagikan dengan sukarela.

## Menerbitkan ulang

Tampilan berubah. Kumpulan gambar yang dibuat sekali lalu dipakai
bertahun akan memajang layar yang sudah tidak ada kepada calon pembeli,
dan yang pertama menyadarinya adalah orang yang baru saja membayar.

```bash
php artisan serve --port=8123          # di jendela lain
node tools/tembak-fitur.mjs            # PETA=… TUJUAN=docs/jual
```

Lihat kepala `tools/tembak-fitur.mjs` untuk pilihan `HANYA`, `MUTU`,
`ALAMAT`, `SUREL`, dan `SANDI`. Daftar halamannya diturunkan dari
`App\Support\Menu`, sumber yang sama dengan bilah samping — jadi modul
baru ikut terpotret tanpa ada yang perlu mengingat menambahkannya.

**Catatan ukuran:** kumpulan ini sekitar 47 MB untuk 188 gambar.
Menerbitkan ulang sebaiknya MENGGANTI folder ini dalam satu commit, bukan
menumpuk versi baru di sampingnya — riwayat git menyimpan tiap salinan
selamanya. Kalau kelak terasa berat, pindahkan folder ini ke git LFS atau
keluarkan dari repositori.

## Etalasenya sendiri

| Folder | Isi |
|---|---|
| [`00-halaman-depan`](./00-halaman-depan/) | Beranda, halaman delapan aspek, dan etalase harga — pada lebar layar dan ponsel |

Dipisahkan dari folder fitur dengan sengaja: yang di sana layar **di
dalam** aplikasi, yang di sini etalasenya. Keduanya juga berubah pada
waktu yang berbeda, dan menyatukannya berarti menerbitkan ulang 188
gambar hanya karena satu kalimat pada beranda diperbaiki.

## Isinya

| Folder | Aplikasi | Halaman | Harga katalog |
|---|---|---|---|
| [`air`](./air/) | Water & Dewatering | 4 | Rp 6.500.000 |
| [`angkutan`](./angkutan/) | Dispatch & Hauling | 5 | Rp 10.500.000 |
| [`biaya`](./biaya/) | Pengendalian Biaya | 5 | Rp 8.500.000 |
| [`dokumen`](./dokumen/) | ISO & Dokumen | 5 | Rp 5.500.000 |
| [`energi`](./energi/) | Energy Performance Center | 13 | Rp 7.500.000 |
| [`geoteknik`](./geoteknik/) | Kestabilan Lereng | 4 | Rp 9.500.000 |
| [`gudang`](./gudang/) | Sistem Informasi Gudang & Penyimpanan | 7 | Rp 6.500.000 |
| [`hazrep`](./hazrep/) | Hazard Report & Inspeksi | 10 | Rp 2.500.000 |
| [`hris`](./hris/) | HRIS — Tenaga Kerja Tambang | 19 | Rp 2.500.000 |
| [`investigasi`](./investigasi/) | Investigasi Insiden | 4 | Rp 2.500.000 |
| [`izin`](./izin/) | Izin Kerja Aman | 5 | Rp 7.500.000 |
| [`ko`](./ko/) | Keselamatan Operasi (KO) | 10 | Rp 2.500.000 |
| [`konservasi`](./konservasi/) | Konservasi Minerba | 3 | Rp 7.500.000 |
| [`lingkungan`](./lingkungan/) | Lingkungan & Reklamasi | 5 | Rp 7.500.000 |
| [`lms`](./lms/) | LMS — Learning Center | 7 | Rp 2.500.000 |
| [`maintenance`](./maintenance/) | Maintenance & Reliability | 4 | Rp 10.500.000 |
| [`meh`](./meh/) | Mining Engineering Hub | 10 | Rp 11.500.000 |
| [`miners`](./miners/) | Authority — Kelayakan Kerja | 21 | Rp 2.500.000 |
| [`operasi`](./operasi/) | Mine Operations & GIS | 4 | Rp 13.500.000 |
| [`peledakan`](./peledakan/) | Drill & Blast | 5 | Rp 9.500.000 |
| [`pjp`](./pjp/) | Pemantauan Perusahaan Jasa (PJP) | 7 | Rp 2.500.000 |
| [`smkp`](./smkp/) | SMKP Audit | 20 | Rp 2.500.000 |
| [`tpkkp`](./tpkkp/) | Safety Maturity Level | 11 | Rp 2.500.000 |

**23 aplikasi · 188 halaman.**
Paket menyeluruh dan harga website tambahan ada di
**Pembelian → Daftar Harga**.

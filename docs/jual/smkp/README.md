# SMKP Audit

_Systems That Hold_

Audit 7 elemen SMKP Minerba sesuai Kepdirjen 185.K/2019: penilaian per kriteria, temuan berjenjang, dan laporan siap cetak.

**Harga katalog:** Rp 2.500.000
**Halaman:** 21

Tangkapan layar di bawah memakai data contoh (`php artisan demo:pasang`),
bukan data pelanggan.

| Berkas | Halaman | Kelompok | Catatan |
|---|---|---|---|
| `01-smkp.jpg` | Daftar Audit | — |  |
| `02-smkp-buat.jpg` | Audit Baru | — |  |
| `03-smkp-tindak-lanjut.jpg` | Tindak Lanjut Temuan | Tindak Lanjut |  |
| `04-smkp-acuan.jpg` | Kriteria & Acuan | — |  |
| `05-smkp-11.jpg` | Ringkasan Audit | Tahap Audit |  |
| `06-smkp-11-tahap-1.jpg` | Tahap I: Permulaan Audit | Tahap Audit |  |
| `07-smkp-11-rencana.jpg` | Rencana Audit | Tahap Audit |  |
| `08-smkp-11-elemen-I.jpg` | Penilaian Elemen I | Tahap Audit |  |
| `09-smkp-11-rapat.jpg` | Rapat dan Daftar Hadir | Tahap Audit |  |
| `10-smkp-11-temuan.jpg` | Temuan Audit | Tahap Audit |  |
| `11-smkp-11-laporan.jpg` | Laporan Audit | Tahap Audit |  |
| `12-smkp-11-rencana-cetak.jpg` | Cetak Rencana Audit | Berkas Resmi |  |
| `13-smkp-11-berita-acara.jpg` | Berita Acara Tahap I | Berkas Resmi |  |
| `14-smkp-11-daftar-hadir-pembukaan.jpg` | Daftar Hadir Rapat Pembukaan | Berkas Resmi |  |
| `15-smkp-11-daftar-hadir-penutupan.jpg` | Daftar Hadir Rapat Penutupan | Berkas Resmi |  |
| `16-smkp-11-formulir-penilaian-cetak.jpg` | Penilaian SMKP | Berkas Resmi |  |
| `17-smkp-11-formulir-kesesuaian-cetak.jpg` | Formulir Kesesuaian | Berkas Resmi |  |
| `18-smkp-11-formulir-ketidaksesuaian-cetak.jpg` | Formulir Ketidaksesuaian | Berkas Resmi |  |
| `19-smkp-11-formulir-rekapitulasi-cetak.jpg` | Rekapitulasi Ketidaksesuaian | Berkas Resmi |  |
| `20-smkp-11-formulir-rekomendasi-cetak.jpg` | Rekomendasi dan OFI | Berkas Resmi |  |
| `21-smkp-11-formulir-tindak-lanjut-cetak.jpg` | Rencana Tindak Lanjut | Berkas Resmi |  |

## Deck promosi

| Berkas | Isi |
|---|---|
| `Promosi-Audit-SMKP-PTPKKP-EQOHSEE.pptx` | 19 slide penawaran Audit SMKP dan PTPKKP, dengan catatan pembicara |
| `Promosi-Audit-SMKP-PTPKKP-EQOHSEE.pdf` | Salinan PDF untuk dikirim lewat WhatsApp atau surel |

**Deck ini BELUM sesuai dengan aplikasi.** Ia dibuat sebelum modul SMKP
diganti dan menggambarkan modul yang lama: alur 8 tahap, sembilan berkas
audit, tangkapan layar layar lama, serta angka **98 kriteria dinilai dan
nilai maksimal 333** (dari matriks resmi Kepdirjen 185.K/37.04/DJB/2019,
Formulir Kriteria Audit hal. 605-608). Aplikasi sekarang memakai alur
empat tahap (lihat tabel di atas) dan data kriteria
`resources/data/smkp/elemen.json`: **7 elemen, 51 sub-elemen, 100 butir
dinilai, nilai maksimal 339**. Deck belum diubah dan menunggu keputusan
pemilik: diperbarui mengikuti aplikasi, atau ditarik dari peredaran.
Sampai itu diputuskan, jangan mengirimnya sebagai gambaran aplikasi yang
berjalan.

Bagian PTPKKP di deck tidak terpengaruh penggantian ini:

- **Kepdirjen 10.K/MB.01/DJB.T/2023** (PTPKKP) — 4 indikator, 24 parameter,
  194 item; bobot dan tingkat pencapaian hal. 15–17.

Tangkapan layar di dalam deck diambil dari folder ini sebelum diterbitkan
ulang dan dari `docs/jual/tpkkp` (data contoh). Harga mengikuti katalog
saat deck dibuat — periksa **Pembelian → Daftar Harga** sebelum mengirim
penawaran.

---
Diterbitkan ulang dengan `node tools/tembak-fitur.mjs` — lihat `docs/jual/README.md`.

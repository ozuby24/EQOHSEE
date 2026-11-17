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

Deck ini sudah mengikuti modul SMKP yang baru: alur empat babak (Tahap I
Permulaan Audit, Tahap I·B Rencana Audit, Tahap II Audit Lapangan, Tahap III
Pelaporan), sepuluh berkas cetak, dan tangkapan layar dari folder ini.
Angka kriterianya dihitung dari `resources/data/smkp/elemen.json` dengan
aturan `App\Support\Smkp::butirSub()`: **7 elemen, 51 sub-elemen, 62 sub-sub
elemen, 100 butir dinilai, nilai maksimal 339** (`meta.total_nilai`). Deck
menyebut angka ini "berdasarkan Lampiran II Kepdirjen 185.K/37.04/DJB/2019",
bukan "persis seperti Kepdirjen": Formulir Kriteria Audit hal. 605-608
mencantumkan 98 butir dan nilai maksimal 333, sedangkan data aplikasi
memakai Daftar Koreksi dan Klarifikasi Lampiran II (lihat `meta.selisih`).

Bagian PTPKKP di deck tidak terpengaruh penggantian modul SMKP:

- **Kepdirjen 10.K/MB.01/DJB.T/2023** (PTPKKP) — 4 indikator, 24 parameter,
  194 item; bobot dan tingkat pencapaian hal. 15–17.

Tangkapan layar SMKP di dalam deck dipotong dari `05`, `06`, `07`, `08`,
`10`, `11`, dan `19` di folder ini; tangkapan layar PTPKKP dari
`docs/jual/tpkkp` (data contoh). Bila gambar-gambar itu diterbitkan ulang,
potongan di deck tidak ikut berubah. Harga mengikuti katalog saat deck
dibuat — periksa **Pembelian → Daftar Harga** sebelum mengirim penawaran.

---
Diterbitkan ulang dengan `node tools/tembak-fitur.mjs` — lihat `docs/jual/README.md`.

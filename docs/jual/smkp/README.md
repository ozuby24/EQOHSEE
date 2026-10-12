# SMKP Audit

_Systems That Hold_

Audit 7 elemen SMKP Minerba sesuai Kepdirjen 185.K/2019: penilaian per kriteria, temuan berjenjang, dan laporan siap cetak.

**Harga katalog:** Rp 2.500.000
**Halaman:** 20

Tangkapan layar di bawah memakai data contoh (`php artisan demo:pasang`),
bukan data pelanggan.

| Berkas | Halaman | Kelompok | Catatan |
|---|---|---|---|
| `01-smkp-dasbor.jpg` | Performa SMKP | — |  |
| `02-smkp.jpg` | Daftar Audit | Periode |  |
| `03-smkp-buat.jpg` | Buat Periode | Periode |  |
| `04-smkp-lanjut-tahap-1.jpg` | Permulaan Audit | Tahap Audit |  |
| `05-smkp-lanjut-rencana.jpg` | Rencana Audit | Tahap Audit |  |
| `06-smkp-lanjut-metode-sampel.jpg` | Metode & Sampel | Tahap Audit |  |
| `07-smkp-lanjut-penilaian.jpg` | Form Penilaian Audit | Tahap Audit |  |
| `08-smkp-lanjut-rapat.jpg` | Rapat & Daftar Hadir | Tahap Audit |  |
| `09-smkp-lanjut-temuan.jpg` | Temuan & Tindakan | Tahap Audit |  |
| `10-smkp-lanjut-susun-laporan.jpg` | Penyusunan Laporan | Tahap Audit |  |
| `11-smkp-lanjut-kriteria.jpg` | 1 · Formulir Kriteria | Berkas Resmi |  |
| `12-smkp-lanjut-rekap-nc.jpg` | 2 · Rekap Ketidaksesuaian | Berkas Resmi |  |
| `13-smkp-lanjut-respon.jpg` | 3 · Respon Manajemen | Berkas Resmi |  |
| `14-smkp-lanjut-rencana-tindak.jpg` | 4 · Rencana Tindak Lanjut | Berkas Resmi |  |
| `15-smkp-lanjut-berita-acara.jpg` | 5 · Berita Acara Tahap I | Berkas Resmi |  |
| `16-smkp-lanjut-laporan-rencana.jpg` | 6 · Laporan Rencana Audit | Berkas Resmi |  |
| `17-smkp-lanjut-laporan-audit.jpg` | 7 · Laporan Audit | Berkas Resmi |  |
| `18-smkp-lanjut-nc-tindak.jpg` | 8 · NC & Tindak Lanjut | Berkas Resmi |  |
| `19-smkp-lanjut-ofi.jpg` | Peluang Perbaikan (OFI) | Berkas Resmi |  |
| `20-smkp-acuan.jpg` | Kriteria Kepdirjen | Acuan |  |

## Deck promosi

| Berkas | Isi |
|---|---|
| `Promosi-Audit-SMKP-PTPKKP-EQOHSEE.pptx` | 19 slide penawaran Audit SMKP dan PTPKKP: struktur kriteria, alur 8 tahap, rumus dan kategori temuan, berkas audit, PTPKKP, harga, cara membeli — dengan catatan pembicara |
| `Promosi-Audit-SMKP-PTPKKP-EQOHSEE.pdf` | Salinan PDF untuk dikirim lewat WhatsApp atau surel |

Angka regulasi di deck dicocokkan langsung dengan dokumen resminya:

- **Kepdirjen 185.K/37.04/DJB/2019** — Formulir Kriteria Audit hal. 605–608:
  7 elemen, 51 sub-elemen, 60 sub-sub elemen, **98 kriteria dinilai**,
  **nilai maksimal 333**; bobot elemen hal. 401; kategori temuan hal. 411.
- **Kepdirjen 10.K/MB.01/DJB.T/2023** (PTPKKP) — 4 indikator, 24 parameter,
  194 item; bobot dan tingkat pencapaian hal. 15–17.

Catatan: data kriteria aplikasi (`resources/data/smkp/elemen.json`) saat
deck dibuat masih berisi 100 butir dengan nilai maksimal 349 — berbeda
dari matriks resmi di 14 butir. Tangkapan layar SMKP di deck masih
menampilkan angka lama itu dalam huruf kecil. Ambil ulang tangkapan
layarnya setelah data aplikasi diselaraskan.

Tangkapan layar di dalamnya diambil dari folder ini dan `docs/jual/tpkkp`
(data contoh). Harga mengikuti katalog saat deck dibuat — periksa
**Pembelian → Daftar Harga** sebelum mengirim penawaran.

---
Diterbitkan ulang dengan `node tools/tembak-fitur.mjs` — lihat `docs/jual/README.md`.

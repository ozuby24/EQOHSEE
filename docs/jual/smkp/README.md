# SMKP Audit

_Systems That Hold_

Audit 7 elemen SMKP Minerba sesuai Kepdirjen 185.K/2019: penilaian per kriteria, temuan berjenjang, dan laporan siap cetak.

**Harga katalog:** belum ditetapkan
**Halaman:** 4

Tangkapan layar di bawah memakai data contoh (`php artisan demo:pasang`),
bukan data pelanggan.

| Berkas | Halaman | Kelompok | Catatan |
|---|---|---|---|
| `01-smkp.jpg` | Daftar Audit | — |  |
| `02-smkp-buat.jpg` | Audit Baru | — |  |
| `03-smkp-tindak-lanjut.jpg` | Tindak Lanjut Temuan | — |  |
| `04-smkp-acuan.jpg` | Kriteria & Acuan | — |  |

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

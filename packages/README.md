# packages/

Modul EQOHSEE yang diangkat menjadi paket berdiri sendiri, agar dapat dipasang
pada aplikasi Laravel lain atau dibaca sebagai referensi implementasi.

| Paket | Isi |
|---|---|
| [`smkp-audit`](smkp-audit/README.md) | Audit Internal SMKP Minerba — tujuh elemen, dua tahap, dan berkas cetaknya (Kepdirjen 185.K/37.04/DJB/2019). |

Paket di sini **tidak dipakai** oleh aplikasi EQOHSEE sendiri: aplikasi tetap
menjalankan salinannya di `app/`, `routes/`, dan `resources/`. Pemisahan itu
disengaja — mengganti modul yang sedang berjalan dengan paket adalah pekerjaan
tersendiri yang menyentuh basis data dan rute produksi, sedangkan yang
dibutuhkan di sini adalah bentuk yang dapat dipasang di tempat lain.

Cara memasangnya ada pada README masing-masing paket.

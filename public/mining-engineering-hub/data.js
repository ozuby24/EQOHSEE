/**
 * Mining Engineering Hub — sumber data.
 *
 * Seluruh angka situs ini berasal dari berkas ini. Yang disimpan adalah
 * besaran mentah — liter, jam, ton, BCM — dan angka turunannya (rasio,
 * intensitas, ketersediaan) dihitung saat dibaca oleh app.js.
 *
 * Alasannya sederhana: kalau rasio ikut disimpan, mengubah satu angka
 * produksi akan membuat kartu KPI dan tabel di halaman lain saling
 * bertentangan, dan tidak ada yang tahu mana yang benar. Dengan dihitung,
 * satu suntingan di sini merambat ke seluruh halaman.
 *
 * Struktur ini sengaja dibuat menyerupai bentuk respons API, sehingga
 * kelak dapat diganti oleh Laravel/Supabase tanpa menyentuh app.js:
 * cukup ganti berkas ini dengan pengambil data yang mengembalikan bentuk
 * objek yang sama.
 */

/* ══════════════════════════════════════════════════════════════
   Faktor konversi
   Diletakkan di satu tempat karena faktor emisi dan harga bahan
   bakar berubah dari tahun ke tahun; yang berubah harus satu
   berkas, bukan belasan pemanggilan yang tersebar.
   ══════════════════════════════════════════════════════════════ */
const FAKTOR = {
  GJ_PER_LITER:    0.0358,   // solar, ±35,8 MJ per liter
  GJ_PER_KWH:      0.0036,   // 1 kWh = 3,6 MJ, tepat menurut definisinya
  TON_CO2_PER_L:   0.00268,  // 2,68 kg CO₂e per liter solar
  TON_CO2_PER_KWH: 0.00087,  // faktor jaringan listrik Indonesia
  RP_PER_LITER:    14500,
  RP_PER_KWH:      1450,
};

/* ══════════════════════════════════════════════════════════════
   Data operasi
   ══════════════════════════════════════════════════════════════ */
const engineeringData = {

  perusahaan: {
    nama:     'Mining Engineering Hub',
    situs:    'Site Sangatta Utara',
    periode:  'Agustus 2026',
    pengguna: { nama: 'Engineering', peran: 'Mine Engineering Dept.' },
  },

  /* ---------- Produksi harian ----------
     ton  = batu bara ROM terangkut
     bcm  = overburden dipindahkan
     Rasio pengupasan dihitung dari keduanya, tidak disimpan. */
  production: {
    target_harian_ton: 12000,
    harian: [
      // tanggal, ton, bcm, target ton
      { tgl: '2026-08-05', ton: 11840, bcm: 92500,  target: 12000 },
      { tgl: '2026-08-06', ton: 12310, bcm: 96800,  target: 12000 },
      { tgl: '2026-08-07', ton: 12680, bcm: 99400,  target: 12000 },
      { tgl: '2026-08-08', ton: 11520, bcm: 88300,  target: 12000 },
      { tgl: '2026-08-09', ton: 12940, bcm: 101200, target: 12000 },
      { tgl: '2026-08-10', ton: 13180, bcm: 103900, target: 12000 },
      { tgl: '2026-08-11', ton: 12450, bcm: 97600,  target: 12000 },
    ],
    // Rekap bulanan untuk saringan 3 bulan
    bulanan: [
      { label: 'Jun 2026', ton: 348200, bcm: 2712000, target: 360000 },
      { label: 'Jul 2026', ton: 366900, bcm: 2874000, target: 360000 },
      { label: 'Agu 2026', ton: 372100, bcm: 2921000, target: 360000 },
    ],
  },

  /* ---------- Energi ----------
     Liter dan kWh disimpan mentah; GJ, tCO₂e dan rupiah diturunkan. */
  energy: {
    baseline_gj_per_ton: 0.0472,   // realisasi tahun lalu
    target_gj_per_ton:   0.0425,   // sasaran tahun berjalan
    harian: [
      { tgl: '2026-08-05', liter: 14980, kwh: 21400 },
      { tgl: '2026-08-06', liter: 15320, kwh: 21900 },
      { tgl: '2026-08-07', liter: 15610, kwh: 22350 },
      { tgl: '2026-08-08', liter: 14730, kwh: 20800 },
      { tgl: '2026-08-09', liter: 15840, kwh: 22600 },
      { tgl: '2026-08-10', liter: 16020, kwh: 23100 },
      { tgl: '2026-08-11', liter: 15380, kwh: 22050 },
    ],
    // Porsi listrik dari sumber terbarukan (PLTS site), kWh per hari
    terbarukan_kwh_harian: 1860,
    penghematan: {
      persen: 12.8,
      status: 'GOOD',
      keterangan: 'Terhadap baseline tahun lalu, pada intensitas energi.',
      program: [
        { judul: 'Pembatasan idle dump truck di area loading', hemat_liter_bulan: 4200, status: 'Berjalan' },
        { judul: 'Penggantian lampu sorot workshop ke LED',    hemat_kwh_bulan: 3800,   status: 'Selesai'  },
        { judul: 'Penjadwalan ulang pompa dewatering',          hemat_kwh_bulan: 5200,   status: 'Disetujui'},
        { judul: 'Perataan ulang jalan angkut segmen 3',        hemat_liter_bulan: 3100, status: 'Kajian'   },
      ],
    },
  },

  /* ---------- Armada ----------
     jam_kerja  = operating
     jam_standby = tersedia tetapi tidak dioperasikan
     jam_rusak   = perbaikan / menunggu suku cadang
     Ketiganya dijumlahkan menjadi jam terjadwal, dan seluruh angka
     ketersediaan diturunkan darinya — bukan diketik terpisah. */
  fleet: {
    jam_terjadwal_periode: 720,      // 30 hari × 24 jam
    unit: [
      { kode: 'DT-001', tipe: 'HD785-7',    kelas: 'Hauling',   status: 'Operating',   jam_kerja: 604, jam_standby: 54, jam_rusak: 62,  liter: 23180, hm: 8421,  payload: 91 },
      { kode: 'DT-002', tipe: 'HD785-7',    kelas: 'Hauling',   status: 'Operating',   jam_kerja: 588, jam_standby: 62, jam_rusak: 70,  liter: 23520, hm: 7821,  payload: 91 },
      { kode: 'DT-003', tipe: 'HD465-7',    kelas: 'Hauling',   status: 'Standby',     jam_kerja: 512, jam_standby: 148, jam_rusak: 60, liter: 18430, hm: 9140,  payload: 55 },
      { kode: 'DT-004', tipe: 'HD785-7',    kelas: 'Hauling',   status: 'Operating',   jam_kerja: 596, jam_standby: 48, jam_rusak: 76,  liter: 24080, hm: 6980,  payload: 91 },
      { kode: 'DT-005', tipe: 'HD465-7',    kelas: 'Hauling',   status: 'Breakdown',   jam_kerja: 402, jam_standby: 88, jam_rusak: 230, liter: 14760, hm: 10240, payload: 55 },
      { kode: 'EX-001', tipe: 'PC2000-8',   kelas: 'Excavator', status: 'Maintenance', jam_kerja: 548, jam_standby: 62, jam_rusak: 110, liter: 34240, hm: 12231, payload: null },
      { kode: 'EX-002', tipe: 'PC1250-8',   kelas: 'Excavator', status: 'Operating',   jam_kerja: 612, jam_standby: 46, jam_rusak: 62,  liter: 32180, hm: 9860,  payload: null },
      { kode: 'DZ-001', tipe: 'D375A-6',    kelas: 'Dozer',     status: 'Operating',   jam_kerja: 574, jam_standby: 78, jam_rusak: 68,  liter: 26840, hm: 11420, payload: null },
      { kode: 'DZ-002', tipe: 'D155A-6',    kelas: 'Dozer',     status: 'Operating',   jam_kerja: 561, jam_standby: 84, jam_rusak: 75,  liter: 22310, hm: 8730,  payload: null },
      { kode: 'GD-001', tipe: 'GD825A-2',   kelas: 'Support',   status: 'Operating',   jam_kerja: 486, jam_standby: 168, jam_rusak: 66, liter: 9820,  hm: 7410,  payload: null },
      { kode: 'WT-001', tipe: 'Water Truck', kelas: 'Support',  status: 'Operating',   jam_kerja: 502, jam_standby: 152, jam_rusak: 66, liter: 10240, hm: 6320,  payload: null },
      { kode: 'FT-001', tipe: 'Fuel Truck',  kelas: 'Support',  status: 'Standby',     jam_kerja: 398, jam_standby: 256, jam_rusak: 66, liter: 7640,  hm: 5180,  payload: null },
    ],
    // Angka produktivitas hauling, dipakai halaman Fleet & Productivity
    produktivitas: {
      cycle_time_menit:   24.6,
      jarak_hauling_km:   3.4,
      truck_factor:       0.94,      // muatan nyata / kapasitas nominal
      kecepatan_rata_kmh: 18.2,
      match_factor:       0.87,      // keseimbangan jumlah truk terhadap excavator
    },
  },

  /* ---------- Pemeliharaan ---------- */
  maintenance: {
    pm_compliance_persen: 91.4,
    mtbf_jam: 168.5,                 // mean time between failures
    mttr_jam: 6.8,                   // mean time to repair
    breakdown_per_bulan: 14,
    biaya_bulan_rp: 3_480_000_000,
    // Jam kerja pemeliharaan menurut jenisnya, enam bulan terakhir
    jenis_bulanan: [
      { label: 'Mar', preventif: 820, korektif: 410, breakdown: 260 },
      { label: 'Apr', preventif: 860, korektif: 380, breakdown: 240 },
      { label: 'Mei', preventif: 910, korektif: 350, breakdown: 210 },
      { label: 'Jun', preventif: 940, korektif: 330, breakdown: 190 },
      { label: 'Jul', preventif: 980, korektif: 300, breakdown: 170 },
      { label: 'Agu', preventif: 1010, korektif: 280, breakdown: 150 },
    ],
    pekerjaan: [
      { unit: 'DT-005', masalah: 'Kebocoran final drive kiri',        prioritas: 'Critical', status: 'In Progress', pic: 'Tim Plant A',  tanggal: '2026-08-09' },
      { unit: 'EX-001', masalah: 'Penggantian bucket tooth & adapter', prioritas: 'High',     status: 'In Progress', pic: 'Tim Plant B',  tanggal: '2026-08-10' },
      { unit: 'DT-002', masalah: 'Getaran tidak wajar pada propeller', prioritas: 'High',     status: 'Open',        pic: 'Tim Plant A',  tanggal: '2026-08-11' },
      { unit: 'DZ-002', masalah: 'PM 500 jam',                         prioritas: 'Medium',   status: 'Scheduled',   pic: 'Tim PM',       tanggal: '2026-08-13' },
      { unit: 'GD-001', masalah: 'Kalibrasi blade control',            prioritas: 'Low',      status: 'Scheduled',   pic: 'Tim PM',       tanggal: '2026-08-15' },
      { unit: 'DT-003', masalah: 'Penggantian ban posisi 4',           prioritas: 'Medium',   status: 'Closed',      pic: 'Tim Tyre',     tanggal: '2026-08-07' },
      { unit: 'EX-002', masalah: 'Perbaikan seal boom cylinder',       prioritas: 'High',     status: 'Closed',      pic: 'Tim Plant B',  tanggal: '2026-08-06' },
    ],
  },

  /* ---------- Keselamatan dan kesehatan kerja ----------
     Frekuensi kecelakaan dihitung dari jumlah kejadian dan jam kerja,
     bukan diketik sebagai angka jadi — sebab definisinya berbeda-beda
     antar perusahaan (per sejuta atau per 200.000 jam kerja) dan yang
     dipakai di sini harus dapat ditelusuri. */
  hse: {
    jam_kerja_kumulatif: 4_820_000,
    pengali: 1_000_000,              // per satu juta jam kerja
    kejadian: {
      recordable: 6,                 // untuk TRIFR
      lost_time:  2,                 // untuk LTIFR
      hari_hilang: 84,               // untuk severity rate
      near_miss:  148,
      observasi:  1264,
    },
    hari_tanpa_lti: 96,
    // Penerapan tujuh elemen SMKP Minerba, Kepdirjen 185.K/37.04/DJB/2019
    smkp: [
      { no: 1, elemen: 'Kebijakan',                                  bobot: 10, capaian: 96 },
      { no: 2, elemen: 'Perencanaan',                                bobot: 15, capaian: 88 },
      { no: 3, elemen: 'Organisasi dan Personel',                    bobot: 17, capaian: 84 },
      { no: 4, elemen: 'Implementasi',                               bobot: 35, capaian: 79 },
      { no: 5, elemen: 'Pemantauan, Evaluasi dan Tindak Lanjut',     bobot: 15, capaian: 82 },
      { no: 6, elemen: 'Dokumentasi',                                bobot:  3, capaian: 91 },
      { no: 7, elemen: 'Tinjauan Manajemen & Peningkatan Kinerja',   bobot:  5, capaian: 74 },
    ],
  },

  /* ---------- Referensi regulasi dan standar ----------
     Hanya identitas dokumen yang disimpan: nomor, tahun, penerbit, dan
     satu kalimat ruang lingkup. Isi pasalnya sengaja tidak disalin —
     teks standar ISO berhak cipta, dan regulasi harus dibaca dari
     sumber resminya agar revisinya tidak tertinggal. */
  regulations: [
    { judul: 'Kepdirjen Minerba 185.K/37.04/DJB/2019', kategori: 'SMKP',    tahun: 2019,
      penerbit: 'Direktorat Jenderal Mineral dan Batubara',
      ket: 'Petunjuk teknis penerapan SMKP Minerba beserta tujuh elemen dan tata cara penilaiannya.',
      sumber: 'https://jdih.esdm.go.id' },
    { judul: 'Permen ESDM No. 26 Tahun 2018', kategori: 'Mining', tahun: 2018,
      penerbit: 'Kementerian ESDM',
      ket: 'Pelaksanaan kaidah pertambangan yang baik dan pengawasan pertambangan mineral dan batubara.',
      sumber: 'https://jdih.esdm.go.id' },
    { judul: 'Kepmen ESDM No. 1827 K/30/MEM/2018', kategori: 'Mining', tahun: 2018,
      penerbit: 'Kementerian ESDM',
      ket: 'Pedoman pelaksanaan kaidah teknik pertambangan yang baik, termasuk lampiran keselamatan pertambangan.',
      sumber: 'https://jdih.esdm.go.id' },
    { judul: 'Permenaker No. 5 Tahun 2018', kategori: 'HSE', tahun: 2018,
      penerbit: 'Kementerian Ketenagakerjaan',
      ket: 'Keselamatan dan kesehatan kerja lingkungan kerja; memuat nilai ambang batas kebisingan dan faktor fisika lainnya.',
      sumber: 'https://jdih.kemnaker.go.id' },
    { judul: 'SNI ISO 45001:2018', kategori: 'HSE', tahun: 2018,
      penerbit: 'Badan Standardisasi Nasional',
      ket: 'Sistem manajemen keselamatan dan kesehatan kerja — persyaratan dengan panduan penggunaan.',
      sumber: 'https://akses-sni.bsn.go.id' },
    { judul: 'SNI ISO 14001:2015', kategori: 'Environment', tahun: 2015,
      penerbit: 'Badan Standardisasi Nasional',
      ket: 'Sistem manajemen lingkungan — persyaratan dengan panduan penggunaan.',
      sumber: 'https://akses-sni.bsn.go.id' },
    { judul: 'SNI ISO 50001:2018', kategori: 'Energy', tahun: 2018,
      penerbit: 'Badan Standardisasi Nasional',
      ket: 'Sistem manajemen energi — persyaratan dengan panduan penggunaan.',
      sumber: 'https://akses-sni.bsn.go.id' },
    { judul: 'SNI ISO 9001:2015', kategori: 'Quality', tahun: 2015,
      penerbit: 'Badan Standardisasi Nasional',
      ket: 'Sistem manajemen mutu — persyaratan.',
      sumber: 'https://akses-sni.bsn.go.id' },
    { judul: 'PP No. 33 Tahun 2023', kategori: 'Mining', tahun: 2023,
      penerbit: 'Pemerintah Republik Indonesia',
      ket: 'Peningkatan nilai tambah mineral dan batubara.',
      sumber: 'https://peraturan.go.id' },
    { judul: 'ISO 1999:2013', kategori: 'HSE', tahun: 2013,
      penerbit: 'International Organization for Standardization',
      ket: 'Penaksiran pajanan bising di tempat kerja dan perkiraan gangguan pendengaran akibat bising.',
      sumber: 'https://www.iso.org' },
  ],
};

/* ══════════════════════════════════════════════════════════════
   Katalog indikator
   Tiap indikator membawa rumusnya sendiri. Menaruh rumus di sebelah
   angkanya membuat halaman KPI dapat dibaca tanpa bertanya "ini
   dihitung dari mana", dan mencegah satu indikator yang sama
   dihitung berbeda di dua halaman.
   ══════════════════════════════════════════════════════════════ */
const kpiKatalog = {
  Equipment: [
    { nama: 'Physical Availability',  kunci: 'pa',           satuan: '%',      rumus: '(Kerja + Standby) ÷ Jam terjadwal × 100' },
    { nama: 'Mechanical Availability', kunci: 'ma',          satuan: '%',      rumus: 'Jam kerja ÷ (Jam kerja + Jam rusak) × 100' },
    { nama: 'Use of Availability',    kunci: 'ua',           satuan: '%',      rumus: 'Jam kerja ÷ (Kerja + Standby) × 100' },
    { nama: 'Utilization',            kunci: 'utilisasi',    satuan: '%',      rumus: 'Jam kerja ÷ Jam terjadwal × 100' },
    { nama: 'Productivity',           kunci: 'produktivitas', satuan: 'ton/jam', rumus: 'Produksi ÷ Jam kerja armada' },
    { nama: 'Fuel Ratio',             kunci: 'fuelRatio',    satuan: 'L/ton',  rumus: 'Solar terpakai ÷ Produksi' },
  ],
  Mining: [
    { nama: 'Production',        kunci: 'produksi',   satuan: 'ton/hari', rumus: 'Rata-rata ROM terangkut per hari' },
    { nama: 'Stripping Ratio',   kunci: 'sr',         satuan: 'BCM/ton',  rumus: 'Overburden dipindahkan ÷ Batu bara terangkut' },
    { nama: 'Cycle Time',        kunci: 'cycle',      satuan: 'menit',    rumus: 'Rata-rata satu siklus muat–angkut–buang–kembali' },
    { nama: 'Hauling Distance',  kunci: 'jarak',      satuan: 'km',       rumus: 'Jarak rata-rata front ke disposal atau ROM' },
    { nama: 'Truck Factor',      kunci: 'truckFactor', satuan: '',        rumus: 'Muatan nyata ÷ Kapasitas nominal vessel' },
    { nama: 'Payload',           kunci: 'payload',    satuan: 'ton',      rumus: 'Rata-rata muatan per ritase armada angkut' },
  ],
  Energy: [
    { nama: 'Energy Consumption',    kunci: 'energiTotal',   satuan: 'GJ/hari',  rumus: 'Solar × 0,0358 + Listrik × 0,0036' },
    { nama: 'Energy Intensity',      kunci: 'intensitas',    satuan: 'GJ/ton',   rumus: 'Energi total ÷ Produksi' },
    { nama: 'Fuel Consumption',      kunci: 'fuelRate',      satuan: 'L/jam',    rumus: 'Solar terpakai ÷ Jam kerja armada' },
    { nama: 'Electricity Consumption', kunci: 'listrik',     satuan: 'kWh/hari', rumus: 'Rata-rata pemakaian listrik harian' },
    { nama: 'Energy Saving',         kunci: 'hemat',         satuan: '%',        rumus: '(Baseline − Sekarang) ÷ Baseline × 100' },
  ],
};

/* Diekspor lewat window agar berkas ini dapat dibuka langsung dari
   file:// tanpa modul ES — modul menuntut server, dan syaratnya adalah
   klik ganda index.html. */
window.FAKTOR = FAKTOR;
window.engineeringData = engineeringData;
window.kpiKatalog = kpiKatalog;

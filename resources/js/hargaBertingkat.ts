/**
 * Harga bertingkat: butir pertama penuh, berikutnya lebih murah.
 *
 * ── SATU BERKAS, DIPAKAI SETIAP LAYAR YANG MENJUMLAHKAN ──
 *
 * Aturan yang sama hidup di dua bahasa: di sini untuk angka yang dibaca
 * pembeli sebelum ia menekan pesan, dan di Produk::subtotal() untuk
 * angka yang benar-benar ditagihkan. Keduanya HARUS memberi hasil yang
 * sama.
 *
 * Kalau tidak, yang terjadi bukan galat. Pembeli melihat Rp 29 juta di
 * layar, menekan pesan, lalu menerima tagihan Rp 75 juta — dan tidak
 * ada satu pun baris log yang menyebut ada yang salah, sebab keduanya
 * bekerja persis seperti yang ditulis. Yang rusak hanya kepercayaan
 * orang yang membacanya.
 *
 * Karena itu perkaliannya TIDAK ditulis ulang di dalam komponen mana
 * pun. Ada uji yang menjaga hal itu.
 */

/** Harga tambahan null berarti tidak bertingkat — harga × banyaknya. */
export function subtotalBertingkat(
  harga: number,
  hargaTambahan: number | null | undefined,
  jumlah: number,
): number {
  const n = Math.max(0, Math.floor(jumlah || 0));

  if (n === 0) return 0;

  /* null DAN undefined sama-sama berarti "tidak ada aturan tambahan".
     Nol tidak: nol adalah harga yang sah untuk butir tambahan — gratis
     — dan memperlakukannya sebagai "tidak bertingkat" akan menagih
     harga penuh untuk setiap butir yang seharusnya cuma-cuma. */
  if (hargaTambahan === null || hargaTambahan === undefined) return harga * n;

  return harga + hargaTambahan * (n - 1);
}

/**
 * Kalimat yang menerangkan angkanya, untuk baris yang bertingkat.
 *
 * Total yang tidak sama dengan harga × banyaknya akan dibaca sebagai
 * salah hitung kalau tidak diterangkan di tempat angkanya muncul.
 */
export function rincianBertingkat(
  harga: number,
  hargaTambahan: number | null | undefined,
  jumlah: number,
  rupiah: (n: number) => string,
): string | null {
  const n = Math.max(0, Math.floor(jumlah || 0));

  if (hargaTambahan === null || hargaTambahan === undefined || n < 2) return null;

  return `${rupiah(harga)} untuk yang pertama, lalu ${n - 1} × ${rupiah(hargaTambahan)}`;
}

/**
 * Harga satu tahun server yang spesifikasinya dipilih pembeli.
 *
 * Padanan Produk::hargaSatuan() di peladen — dasar 1 vCPU dan RAM 1 GB
 * sudah termasuk dalam `harga`; yang ditambahkan hanya vCPU dan GB RAM
 * di atasnya. Null pada kedua tarif berarti butirnya tidak dapat diatur.
 * Uji HargaServerTest menjalankan berkas ini lewat node dan
 * membandingkan hasilnya dengan peladen.
 */
export function hargaServer(
  harga: number,
  hargaCpu: number | null | undefined,
  hargaRam: number | null | undefined,
  cpu: number,
  ram: number,
): number {
  if ((hargaCpu === null || hargaCpu === undefined) && (hargaRam === null || hargaRam === undefined)) return harga;

  const tambahCpu = Math.max(0, Math.floor(cpu || 1) - 1);
  const tambahRam = Math.max(0, Math.floor(ram || 1) - 1);

  return harga + (hargaCpu ?? 0) * tambahCpu + (hargaRam ?? 0) * tambahRam;
}

/** Subtotal server: harga menurut spesifikasinya × banyaknya (tahun). */
export function subtotalServer(
  harga: number,
  hargaCpu: number | null | undefined,
  hargaRam: number | null | undefined,
  cpu: number,
  ram: number,
  jumlah: number,
): number {
  const n = Math.max(0, Math.floor(jumlah || 0));

  return n === 0 ? 0 : hargaServer(harga, hargaCpu, hargaRam, cpu, ram) * n;
}

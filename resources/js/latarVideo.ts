/**
 * Boleh atau tidaknya sebuah halaman memuat VIDEO sebagai latar.
 *
 * ── LIMA MEGABYTE, KEPADA YANG PALING TIDAK MAMPU MENANGGUNGNYA ──
 *
 * Tiga halaman memasang rekaman selebar layar di belakang tulisannya:
 * halaman depan dan etalase memakai tambang.mp4 (4,5 MB), halaman masuk
 * memakai masuk.mp4 (5,6 MB). Posternya masing-masing 228 KB dan 268 KB
 * — dua puluh kali lebih ringan.
 *
 * Diukur di peramban ponsel, berkas itu terunduh PENUH meski atribut
 * `preload="metadata"` terpasang. Dan yang membukanya dari ponsel adalah
 * pengawas di site, pada jaringan yang persis menjadi alasan produk ini
 * ada. Halaman jualan yang menghabiskan kuota orang sebelum kalimat
 * pertamanya terbaca sudah kalah sebelum dibaca; halaman masuk yang
 * melakukannya menahan orang dari pekerjaannya.
 *
 * ── SATU ATURAN, BUKAN TIGA SALINAN ──
 *
 * Sebelum ini ketiganya menuliskan syaratnya sendiri-sendiri, dan
 * ketiganya hanya memeriksa prefers-reduced-motion. Disalin, aturan
 * seperti ini selalu berakhir berbeda di satu tempat — dan yang berbeda
 * adalah halaman yang diam-diam tetap mengunduh lima megabyte.
 */

/** Ambang lebar layar. Di bawah ini, yang tersisa dari video hanya biayanya. */
const LEBAR_MINIMUM = 768;

type JaringanPeramban = {
  saveData?: boolean;
  effectiveType?: string;
};

/**
 * Dibaca SEKALI saat pemasangan, bukan sebagai computed yang ikut
 * berubah ketika jendela diubah ukurannya.
 *
 * Nilai yang berbalik di tengah jalan justru MEMULAI unduhan yang
 * seharusnya dihindari: pengguna yang memperlebar jendelanya di tengah
 * membaca tiba-tiba menarik 4,5 MB yang sudah berhasil dihindari saat
 * halaman dibuka.
 */
export function bolehLatarVideo(): boolean {
  if (typeof window === 'undefined') return false;

  /* Gerakan yang tidak diminta memicu mual dan pusing pada sebagian
     orang, dan latar halaman adalah tempat mereka tidak punya pilihan
     untuk menghindarinya. */
  if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true) return false;

  if (window.innerWidth < LEBAR_MINIMUM) return false;

  /* Network Information API belum ada di Safari maupun Firefox. Yang
     tidak menyatakan apa-apa dianggap sanggup: menahan rekaman dari
     seluruh pengguna Safari demi jaringan yang mungkin baik-baik saja
     adalah harga yang lebih mahal daripada yang dihemat. */
  const jaringan = (navigator as Navigator & { connection?: JaringanPeramban }).connection;

  if (jaringan?.saveData === true) return false;

  /* 'slow-2g', '2g', '3g' — hanya '4g' yang dianggap sanggup. Ditulis
     sebagai daftar yang ditolak, bukan '!== 4g': nilai baru yang muncul
     kelak (5g, misalnya) seharusnya lolos, bukan tertahan. */
  if (jaringan?.effectiveType
      && ['slow-2g', '2g', '3g'].includes(jaringan.effectiveType)) return false;

  return true;
}

/**
 * Warna grafik — satu tempat, dipakai seluruh modul.
 *
 * Nilainya BUKAN pilihan selera. Seluruh daftar di bawah lulus
 * pemeriksaan yang dijalankan mesin, bukan dikira-kira dengan mata:
 * pita terang, lantai kroma, jarak antar warna di bawah buta warna
 * protan dan deutan, jarak yang sama di bawah penglihatan penuh, dan
 * kontras terhadap latar kartu (#FFFFFF).
 *
 * Kenapa dijalankan mesin: sekitar satu dari dua belas laki-laki
 * mengalami buta warna merah-hijau. Dua warna yang jelas berbeda di
 * layar perancangnya dapat menjadi satu warna yang sama bagi mereka —
 * dan grafik yang menyatu tidak terlihat rusak, ia hanya terbaca
 * salah. Tidak ada yang tahu itu terjadi. Pada aplikasi yang dipakai
 * memutuskan alat mana yang boleh beroperasi, itu bukan soal selera.
 *
 * ATURAN YANG TIDAK BOLEH DILANGGAR
 *
 * 1. URUTANNYA yang menjaga keamanannya. Ambil dari depan, jangan
 *    diacak, jangan diputar ulang. Deret kesembilan bukan warna baru —
 *    ia dilebur menjadi "Lainnya" atau dipecah menjadi beberapa grafik
 *    kecil.
 *
 * 2. Warna mengikuti ENTITASNYA, bukan peringkatnya. Penyaring yang
 *    mengubah jumlah deret tidak boleh mengecat ulang yang tersisa;
 *    pembaca membaca warna sebagai identitas, dan identitas yang
 *    berpindah membuatnya salah membandingkan.
 *
 * 3. Satu deret TIDAK memakai warna kategori, melainkan AKSEN. Tidak
 *    ada identitas yang perlu dibedakan — dan mewarnai batang menurut
 *    nilainya menghabiskan saluran identitas untuk mengulang apa yang
 *    sudah dikatakan panjangnya.
 *
 * 4. Warna keadaan TIDAK pernah dipakai sebagai "deret keempat". Merah
 *    berarti gawat; merah yang berarti "regu C" membuat pembacanya
 *    menyimpulkan hal yang salah sebelum sempat membaca keterangannya.
 *
 * 5. Tiga warna kategori — aqua, kuning, magenta — kontrasnya di bawah
 *    3:1 pada latar putih. Ketiganya tetap dipakai DENGAN SYARAT
 *    angkanya juga terbaca tanpa warna: label langsung atau tabel.
 *    Itu sebabnya setiap kartu grafik di sini menyediakan tabel.
 */

/**
 * Delapan warna identitas, urutannya tetap.
 *
 * Terverifikasi pada latar #FFFFFF: jarak buta warna terburuk antar
 * tetangga ΔE 9,1 (ambang 8) dan jarak penglihatan penuh terburuk
 * ΔE 19,6 (lantai 15).
 */
export const KATEGORI = [
  '#2a78d6', // 1 biru
  '#eb6834', // 2 jingga
  '#1baf7a', // 3 aqua
  '#eda100', // 4 kuning
  '#e87ba4', // 5 magenta
  '#008300', // 6 hijau
  '#4a3aa7', // 7 ungu
  '#e34948', // 8 merah
];

/**
 * Batas aman untuk bentuk grafik yang setiap markanya dapat
 * bersebelahan — sebar, gelembung, peta. Di sana ke-28 pasangnya
 * berlaku sekaligus, dan hanya tiga yang pertama yang lolos.
 */
export const KATEGORI_SEBAR = 3;

/** Aksen satu deret — jingga EQOHSEE, distep agar lolos 3:1 pada putih. */
export const AKSEN = '#C47000';

/** Yang diredupkan ketika satu deret ditonjolkan. */
export const REDUP = '#D6D3D1';

/**
 * Tangga besaran: satu warna, terang → gelap.
 *
 * Terverifikasi: terangnya menaik tunggal, jarak antar anak tangga
 * ≥ 0,06, ujung terangnya 2,14:1 terhadap putih, sebaran rona 5°.
 */
export const TANGGA = ['#F0A040', '#E28510', '#C47000', '#A05B00', '#7C4600', '#583200'];

/**
 * Warna keadaan — maknanya dipesan, tidak dipakai untuk hal lain.
 * Selalu disertai label; tidak pernah warna saja.
 */
export const KEADAAN: Record<string, string> = {
  baik:   '#16A34A',
  ingat:  '#D97706',
  serius: '#EA580C',
  gawat:  '#D92D20',
  netral: '#78716C',
};

/** Sumbu, garis bantu, dan tinta — tenang, tidak bersaing dengan datanya. */
export const BINGKAI = {
  bantu:      '#F1F0EE',
  sumbu:      '#E7E5E4',
  tinta:      '#292524',
  redupTinta: '#78716C',
  latar:      '#FFFFFF',
};

/**
 * Warna deret ke-i menurut urutan tetap.
 *
 * Melewati delapan sengaja memulangkan warna terakhir, bukan memutar
 * ke awal: dua deret berwarna sama terlihat sebagai kesalahan dan
 * memang begitulah keadaannya — pemanggilnya harus melebur ekornya
 * menjadi "Lainnya" lebih dulu.
 */
export function warnaDeret(i: number): string {
  return KATEGORI[Math.min(Math.max(0, i), KATEGORI.length - 1)];
}

/**
 * Satu anak tangga besaran untuk rasio 0..1.
 *
 * Yang tidak berangka memulangkan null, dan pemanggilnya menggambarnya
 * sebagai kotak kosong: "tidak ada data" dan "nilainya nol" adalah dua
 * keadaan yang berbeda, dan menyamakannya adalah cara termudah
 * membaca tambang yang berhenti sebagai tambang yang aman.
 */
export function anakTangga(rasio: number | null | undefined): string | null {
  if (rasio === null || rasio === undefined || Number.isNaN(rasio)) return null;

  return TANGGA[Math.round(Math.min(1, Math.max(0, rasio)) * (TANGGA.length - 1))];
}

/**
 * Ringkas untuk sumbu dan kartu: 12.400 → 12,4 rb. Angka penuh tetap
 * di tabel.
 *
 * Desimalnya mengikuti BESARNYA, tidak dipatok satu angka. Intensitas
 * energi berukuran 0,0105 GJ/ton; dibulatkan ke satu desimal ia
 * menjadi "0,0" — dan sumbu yang setiap tanda centangnya bertuliskan
 * "0,0" tidak mengatakan apa pun sambil terlihat seperti grafik yang
 * benar. Ini persis yang terjadi pada dasbor energi: meternya
 * menampilkan "0 GJ/ton" untuk nilai yang sebenarnya ada.
 */
export function ringkas(n: number | null | undefined): string {
  if (n === null || n === undefined || Number.isNaN(n)) return '—';

  const a = Math.abs(n);
  const f = (x: number, d: number) =>
    x.toLocaleString('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d });

  if (a >= 1e9) return f(n / 1e9, 1) + ' M';
  if (a >= 1e6) return f(n / 1e6, 1) + ' jt';
  if (a >= 1e4) return f(n / 1e3, 1) + ' rb';

  if (n === 0) return '0';
  if (Number.isInteger(n)) return f(n, 0);

  /* Tiga angka berarti, berapa pun besarnya: 0,0105 dan 1,05 dan 105
     semuanya terbaca. Nol di ekor dibuang — "0,00500" pada tanda
     centang sumbu terbaca sebagai ketelitian yang tidak ada. */
  return a.toLocaleString('id-ID', {
    maximumFractionDigits: Math.min(6, Math.max(0, 2 - Math.floor(Math.log10(a)))),
    minimumFractionDigits: 0,
  }).replace(/^/, n < 0 ? '-' : '');
}

/**
 * Sumbu tegak yang berhenti di angka bulat.
 *
 * Sumbu yang berhenti di 8.437 memaksa pembacanya menaksir setiap
 * batang; yang berhenti di 10.000 membuat setengahnya terbaca
 * langsung.
 */
export function batasBulat(maks: number): number {
  if (!isFinite(maks) || maks <= 0) return 1;

  const pangkat = Math.pow(10, Math.floor(Math.log10(maks)));
  const sisa    = maks / pangkat;
  const naik    = sisa <= 1 ? 1 : sisa <= 2 ? 2 : sisa <= 2.5 ? 2.5 : sisa <= 5 ? 5 : 10;

  return naik * pangkat;
}

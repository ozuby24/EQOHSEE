/**
 * v-belah — judul tersingkap dari balik topeng, bukan sekadar memudar.
 *
 * ── BEDANYA DENGAN v-singkap ──
 *
 * `v-singkap` menaikkan unsurnya delapan belas piksel sambil
 * menaikkan opacity. Itu halus, dan pada kartu berulang memang itu
 * yang dimaui. Pada JUDUL ia terlalu sopan: yang dibaca orang pertama
 * kali di sebuah halaman sebaiknya terasa dibuka, bukan sekadar
 * muncul.
 *
 * Di sini hurufnya berangkat dari BAWAH garis bacanya dan naik ke
 * tempatnya, sementara sebuah topeng memotong apa pun yang masih
 * berada di bawah garis itu. Yang terlihat: kalimat yang terangkat
 * dari balik sesuatu.
 *
 * ── KENAPA clip-path, BUKAN PEMBUNGKUS overflow:hidden ──
 *
 * Cara yang biasa dipakai adalah membungkus tiap baris dalam elemen
 * ber-overflow:hidden. Itu menuntut judulnya dipecah menjadi baris —
 * dan judul di halaman ini memuat elemen sebaris di tengahnya (tombol
 * pil berpanah pada hero). Dipecah, tombol itu ikut terpotong menjadi
 * dua bagian yang tidak lagi dapat ditekan.
 *
 * `clip-path` tidak menyentuh susunan anaknya sama sekali. Tepinya
 * dilebihkan sedikit ke kiri, kanan, dan atas supaya huruf berkait —
 * g, j, y — dan bayangan tombolnya tidak ikut terpangkas.
 *
 * ── GAGAL BERARTI TERLIHAT, BUKAN HILANG ──
 *
 * Keadaan awalnya dipasang dari sini, bukan dari CSS. Ditulis di CSS,
 * halaman yang skripnya gagal dijalankan menampilkan judul yang
 * terpotong habis selamanya — dan judul adalah hal terakhir yang boleh
 * hilang dari sebuah halaman jual.
 */
import type { Directive, DirectiveBinding } from 'vue';

const LENGKUNG = 'cubic-bezier(.22,1,.36,1)';
const LAMA = 900;

/** Terbuka penuh, dengan kelebihan tepi untuk huruf berkait dan bayangan. */
const BUKA = 'inset(-.22em -.35em -.28em -.35em)';

/** Tertutup: seluruh badan teks berada di bawah garis potong. */
const TUTUP = 'inset(-.22em -.35em 100% -.35em)';

let pengamat: IntersectionObserver | null = null;

/**
 * Yang diamati INDUKNYA, bukan judulnya sendiri.
 *
 * Ini bukan kerapian melainkan syarat. IntersectionObserver menghitung
 * perpotongan dari kotak yang TERSISA sesudah pemangkasan — dan judul
 * yang memangkas dirinya habis lewat `clip-path: inset(... 100% ...)`
 * menyisakan kotak kosong. Rasionya tetap nol berapa pun ia terlihat,
 * pengamatnya tidak pernah memanggil balik, dan judulnya tidak pernah
 * terbuka.
 *
 * Kegagalannya diam dan total: tidak ada galat, tidak ada bagian yang
 * setengah tergambar — hanya judul yang tidak ada. Terukur: sembilan
 * dari sembilan judul di halaman depan.
 *
 * Induknya tidak memangkas apa pun, jadi perpotongannya terhitung
 * sebagaimana mestinya.
 */
const sasaran = new WeakMap<Element, HTMLElement>();

function diamSaja(): boolean {
  return typeof window === 'undefined'
    || typeof IntersectionObserver === 'undefined'
    || window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;
}

function bersihkan(el: HTMLElement): void {
  el.style.removeProperty('clip-path');
  el.style.removeProperty('transform');
  el.style.removeProperty('transition');
  el.style.removeProperty('transition-delay');
}

function buka(el: HTMLElement): void {
  delete el.dataset.belahTutup;
  el.style.clipPath = BUKA;
  el.style.transform = 'none';

  /* Gaya sebarisnya dilepas sesudah geraknya selesai. Dibiarkan
     menempel, `transition` bawaannya ikut dipakai oleh tiap perubahan
     berikutnya pada unsur yang sama — termasuk yang dirancang jauh
     lebih cepat. */
  window.setTimeout(() => bersihkan(el), LAMA + 260);
}

function siapkan(): IntersectionObserver {
  pengamat ??= new IntersectionObserver((butir, obs) => {
    for (const b of butir) {
      if (!b.isIntersecting) continue;

      const el = sasaran.get(b.target);

      if (!el) continue;

      const jaring = el.dataset.belahJaring;

      if (jaring) {
        window.clearTimeout(Number(jaring));
        delete el.dataset.belahJaring;
      }

      buka(el);
      obs.unobserve(b.target);
      sasaran.delete(b.target);
    }
  }, { rootMargin: '0px 0px -10% 0px', threshold: 0.12 });

  return pengamat;
}

export const belah: Directive<HTMLElement, number | undefined> = {
  mounted(el: HTMLElement, ikatan: DirectiveBinding<number | undefined>) {
    if (diamSaja()) return;

    const tunda = ikatan.value ?? 0;

    el.style.clipPath = TUTUP;
    el.style.transform = 'translateY(.34em)';
    el.style.transition =
      `clip-path ${LAMA}ms ${LENGKUNG} ${tunda}ms, transform ${LAMA}ms ${LENGKUNG} ${tunda}ms`;

    /* Jaring pengaman yang sama seperti pada v-singkap: pengamatnya
       dapat tidak pernah memanggil balik — unsur di dalam induk
       display:none saat dipasang, halaman yang dibuka lewat jangkar,
       pemulihan posisi gulir. Kegagalannya diam dan total: judulnya
       sekadar tidak ada, tanpa satu pun galat. */
    /* Keadaannya dipegang penanda, BUKAN dengan membandingkan
       `el.style.clipPath` terhadap TUTUP. Peramban menulis ulang nilai
       yang dipasang: `inset(-.22em -.35em 100% -.35em)` dibaca kembali
       sebagai `inset(-0.22em -0.35em 100%)` — sisi kiri dihapus karena
       sama dengan kanan. Perbandingannya tidak pernah cocok, dan jaring
       yang seharusnya menyelamatkan justru ikut diam. */
    el.dataset.belahTutup = '1';

    const jaring = window.setTimeout(() => {
      if (el.dataset.belahTutup) buka(el);
    }, 2600);

    el.dataset.belahJaring = String(jaring);

    const diamati = el.parentElement ?? el;

    sasaran.set(diamati, el);
    siapkan().observe(diamati);
  },

  unmounted(el: HTMLElement) {
    const diamati = el.parentElement ?? el;

    pengamat?.unobserve(diamati);
    sasaran.delete(diamati);

    const jaring = el.dataset.belahJaring;

    if (jaring) window.clearTimeout(Number(jaring));
  },
};

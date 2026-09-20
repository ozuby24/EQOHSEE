/**
 * v-paralaks — unsur bergerak lebih lambat daripada gulirannya.
 *
 * ── SATU LOOP, BUKAN SATU PENDENGAR PER UNSUR ──
 *
 * Tiap unsur berparalaks yang memasang pendengar `scroll` sendiri
 * berarti sepuluh fungsi dipanggil pada tiap kejadian gulir — dan
 * kejadian gulir datang berpuluh kali per detik. Di sini semuanya
 * terdaftar pada satu daftar, dibaca satu pendengar, dan digambar satu
 * requestAnimationFrame.
 *
 * ── DIBACA SEKALI, DITULIS SEKALI ──
 *
 * Kedudukan tiap unsur dibaca lebih dulu untuk SELURUH daftar, baru
 * transformnya ditulis. Dibaca-tulis bergantian per unsur, peramban
 * dipaksa menghitung ulang tata letak di antara tiap pasangnya —
 * "layout thrashing", dan pada tablet ia terasa sebagai guliran yang
 * tersendat justru karena gerakan yang dimaksudkan menghaluskan.
 *
 * ── YANG MEMINTA GERAKAN BERHENTI, BERHENTI ──
 *
 * Paralaks termasuk gerakan yang paling memicu pusing: latar yang
 * bergerak berbeda dari isinya persis meniru keadaan yang membuat
 * orang mabuk perjalanan. Diminta berhenti, tidak ada satu pun
 * transform yang dipasang.
 */
import type { Directive } from 'vue';

type Butir = { el: HTMLElement; laju: number };

const daftar: Butir[] = [];
let pendengar = false;
let menunggu = false;

function diamSaja(): boolean {
  return typeof window === 'undefined'
    || window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;
}

function gambar(): void {
  menunggu = false;

  const tinggi = window.innerHeight;

  /* BACA dulu seluruhnya. */
  const ukur = daftar.map(({ el, laju }) => {
    const k = el.getBoundingClientRect();
    return { el, laju, tengah: k.top + k.height / 2 };
  });

  /* Baru TULIS. */
  for (const { el, laju, tengah } of ukur) {
    /* Diukur dari tengah layar: unsur yang tepat di tengah tidak
       tergeser sama sekali, dan pergeserannya tumbuh ke dua arah.
       Diukur dari puncak halaman, unsur di bagian bawah halaman
       panjang akan tergeser ratusan piksel sebelum sempat terlihat. */
    const selisih = tengah - tinggi / 2;

    el.style.transform = `translate3d(0, ${(selisih * laju).toFixed(2)}px, 0)`;
  }
}

function minta(): void {
  if (menunggu) return;
  menunggu = true;
  requestAnimationFrame(gambar);
}

export const paralaks: Directive<HTMLElement, number | undefined> = {
  mounted(el, ikatan) {
    if (diamSaja()) return;

    /* Laju bertanda: negatif berarti unsurnya tertinggal di belakang
       guliran — itu yang membuatnya terbaca sebagai lebih jauh. */
    daftar.push({ el, laju: ikatan.value ?? -0.08 });

    if (!pendengar) {
      pendengar = true;
      window.addEventListener('scroll', minta, { passive: true });
      window.addEventListener('resize', minta, { passive: true });
    }

    minta();
  },

  unmounted(el) {
    const i = daftar.findIndex((b) => b.el === el);

    if (i >= 0) daftar.splice(i, 1);

    /* Pendengarnya ikut dilepas begitu tidak ada lagi yang memakainya.
       Ditinggal terpasang, tiap guliran di halaman berikutnya tetap
       memanggil rAF yang tidak menggambar apa pun. */
    if (daftar.length === 0 && pendengar) {
      pendengar = false;
      window.removeEventListener('scroll', minta);
      window.removeEventListener('resize', minta);
    }
  },
};

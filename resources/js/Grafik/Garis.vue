<script setup lang="ts">
/**
 * Garis waktu, satu atau beberapa deret, dengan garis bidik dan
 * keterangan yang mengikuti kursor.
 *
 * Yang dijaga di sini, dan alasannya:
 *
 * - SATU SUMBU TEGAK, selalu. Dua skala pada satu grafik membuat dua
 *   deret tampak berpotongan di tempat yang sesungguhnya tidak berarti
 *   apa-apa — persilangan yang seluruhnya ditentukan pemilihan
 *   skalanya, bukan datanya. Dua besaran yang berbeda satuan digambar
 *   sebagai dua grafik.
 *
 * - GARIS BIDIK MENCARI TANGGALNYA, bukan sebaliknya. Pembaca
 *   mengarahkan kursor ke sebuah hari; menuntutnya mengenai garis
 *   setebal dua piksel membuat sebagian besar nilainya tidak pernah
 *   terbaca.
 *
 * - SATU KETERANGAN MEMUAT SELURUH DERET pada tanggal itu, sehingga
 *   membandingkan tidak menuntut dua kali arahkan.
 *
 * - Lubang data digambar sebagai PUTUS, bukan disambung. Garis yang
 *   menyambung dua titik berjauhan menggambarkan pengukuran yang tidak
 *   pernah dilakukan, dan pada data lingkungan itu berarti melaporkan
 *   angka yang tidak ada.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { BINGKAI, ringkas, ringkasSumbu, skalaRapi, warnaDeret, AKSEN } from './warna';

type Titik = number | null;

const props = withDefaults(defineProps<{
  /** Label sumbu datar, searah dengan tiap deret. */
  label: string[];
  /** Satu entri per deret. */
  deret: { nama: string; nilai: Titik[]; warna?: string }[];
  satuan?: string;
  /** Digambar sebagai bidang berwarna tipis; hanya untuk satu deret. */
  bidang?: boolean;
  tinggi?: number;
  /**
   * Bantalan kiri yang DITETAPKAN pemanggilnya. Untuk grafik kecil yang
   * bersusun: sumbu waktunya hanya sejajar bila semuanya mulai di
   * piksel yang sama, sedangkan bantalan yang dihitung dari label
   * sumbunya sendiri berbeda antara "30 rb" dan "250 rb".
   */
  kiri?: number | null;
}>(), { satuan: '', bidang: false, tinggi: 176, kiri: null });


/* Pengenal unik per contoh komponen: dua grafik pada satu halaman yang
   memakai id landaian sama akan saling menimpa, dan yang kedua mewarisi
   warna yang pertama tanpa satu pun galat. */
const uid = Math.random().toString(36).slice(2, 8);

/* LEBAR KANVAS = LEBAR WADAHNYA, diukur — bukan 640 yang tetap.

   Dengan viewBox selebar 640 dan tinggi CSS yang tetap, grafik pada
   wadah 320px di ponsel diperkecil setengahnya: label sumbu tinggal
   setinggi 5px dan tidak terbaca, lalu sisa tingginya menjadi pita
   kosong di atas dan di bawah grafik. Dengan satu satuan viewBox sama
   dengan satu piksel layar, huruf 10px tetap 10px di layar mana pun, dan
   yang berubah hanyalah jarak antartitik. */
const wadah = ref<HTMLElement | null>(null);
const lebar = ref(640);
let pengukur: ResizeObserver | null = null;

onMounted(() => {
  const ukur = () => {
    const w = Math.round(wadah.value?.getBoundingClientRect().width ?? 0);
    if (w > 0 && w !== lebar.value) lebar.value = w;
  };
  ukur();
  if (typeof ResizeObserver !== 'undefined' && wadah.value) {
    pengukur = new ResizeObserver(ukur);
    pengukur.observe(wadah.value as unknown as Element);
  }
});

onBeforeUnmount(() => pengukur?.disconnect());

const L = computed(() => Math.max(240, lebar.value));

/* Berapa label tanggal muat di sumbu datar: satu label ~48px. */
const langkahLabel = computed(() => {
  const muat = Math.max(2, Math.floor((L.value - P.value.kiri - P.value.kanan) / 48));
  return props.label.length <= muat ? 1 : Math.ceil(props.label.length / muat);
});

const T = computed(() => props.tinggi);

const warna = (i: number) =>
  props.deret[i]?.warna ?? (props.deret.length === 1 ? AKSEN : warnaDeret(i));

const terukur = computed(() =>
  props.deret.flatMap(d => d.nilai).filter((v): v is number => v !== null && v !== undefined));

/* Langkah sumbu bulat, dan bulat-bilangan untuk data cacahan: deret
   yang seluruh nilainya bilangan bulat tidak diberi tanda "2,5". */
const skala = computed(() => {
  const v = terukur.value;

  return skalaRapi(v.length ? Math.max(...v) : 1, v.length > 0 && v.every(Number.isInteger));
});

const maks = computed(() => skala.value.batas);

/** Tanda sumbu tegak, dari nol sampai ujungnya. */
const tanda = computed(() => {
  const { batas, langkah } = skala.value;
  const n = Math.round(batas / langkah);

  return Array.from({ length: n + 1 }, (_, i) => ({ nilai: i * langkah, teks: ringkasSumbu(i * langkah, batas) }));
});

/* Bantalan kiri selebar label sumbu terpanjang, bukan 44px yang tetap:
   "250 rb" dan "1,5 jt" lebih lebar daripada "25", dan label yang
   melewati tepi kiri kanvas terpotong tanpa tanda apa pun. Satu angka
   10px ≈ 5,9px. */
const P = computed(() => ({
  atas: 12, kanan: 12, bawah: 22,
  kiri: props.kiri ?? Math.max(30, Math.ceil(Math.max(...tanda.value.map(t => t.teks.length)) * 5.9) + 14),
}));

const x = (i: number) => {
  const n = Math.max(1, props.label.length - 1);

  return P.value.kiri + (i / n) * (L.value - P.value.kiri - P.value.kanan);
};

const y = (v: number) =>
  P.value.atas + (1 - v / maks.value) * (T.value - P.value.atas - P.value.bawah);

/**
 * Posisi label tanggal. Label di ujung digeser ke dalam kanvas, bukan
 * dibiarkan terpotong: "Nov 26" di titik terakhir berpusat 12px dari
 * tepi kanan, dan separuh lebarnya melewati tepi itu.
 */
const posLabel = (i: number) => {
  const w  = (props.label[i]?.length ?? 0) * 5.6;
  const px = x(i);

  if (px + w / 2 > L.value - 2) return { x: L.value - 2, anchor: 'end' };
  if (px - w / 2 < 2)           return { x: 2, anchor: 'start' };

  return { x: px, anchor: 'middle' };
};

/**
 * Jalur dipecah pada setiap lubang, bukan disambung melewatinya.
 * Itulah sebabnya ia memulangkan daftar penggal, bukan satu string.
 */
const penggal = (nilai: Titik[]): string[] => {
  const keluar: string[] = [];
  let kini: string[] = [];

  nilai.forEach((v, i) => {
    if (v === null) {
      if (kini.length > 1) keluar.push(kini.join(' '));
      kini = [];

      return;
    }

    kini.push(`${kini.length ? 'L' : 'M'}${x(i).toFixed(1)} ${y(v).toFixed(1)}`);
  });

  if (kini.length > 1) keluar.push(kini.join(' '));

  return keluar;
};

const jalurBidang = computed(() => {
  const d = props.deret[0];
  if (!props.bidang || !d) return '';

  const ada = d.nilai
    .map((v, i) => (v === null ? null : [x(i), y(v)] as const))
    .filter((t): t is readonly [number, number] => t !== null);

  if (ada.length < 2) return '';

  const dasar = T.value - P.value.bawah;

  return `M${ada[0][0]} ${dasar} `
    + ada.map(([px, py]) => `L${px.toFixed(1)} ${py.toFixed(1)}`).join(' ')
    + ` L${ada[ada.length - 1][0]} ${dasar} Z`;
});

/** Garis bantu di tiap tanda sumbu; langkahnya bulat (skalaRapi). */
const bantu = computed(() =>
  tanda.value.map((t, i) => ({ r: i, nilai: t.nilai, teks: t.teks, py: y(t.nilai) })));

/* ── bidikan ── */

const bidik = ref<number | null>(null);
const svgEl = ref<SVGSVGElement | null>(null);

function arahkan(e: PointerEvent | FocusEvent) {
  const el = svgEl.value;
  if (!el) return;

  if (!(e instanceof PointerEvent)) return;

  const kotak = el.getBoundingClientRect();
  const px    = ((e.clientX - kotak.left) / kotak.width) * L.value;
  const n     = Math.max(1, props.label.length - 1);
  const rasio = (px - P.value.kiri) / (L.value - P.value.kiri - P.value.kanan);

  bidik.value = Math.min(props.label.length - 1, Math.max(0, Math.round(rasio * n)));
}

/** Keterangan dipindah ke sisi lain begitu mendekati tepi kanan. */
const sisiKiri = computed(() =>
  bidik.value !== null && bidik.value > props.label.length * 0.6);

const nilaiBidik = computed(() => {
  const i = bidik.value;
  if (i === null) return [];

  return props.deret.map((d, di) => ({
    nama:  d.nama,
    warna: warna(di),
    teks:  d.nilai[i] === null || d.nilai[i] === undefined
      ? 'tidak diukur'
      : ringkas(d.nilai[i] as number) + (props.satuan ? ' ' + props.satuan : ''),
  }));
});

const kosong = computed(() =>
  !props.label.length
  || props.deret.every(d => d.nilai.every(v => v === null || v === undefined)));
</script>

<template>
  <div v-if="kosong"
       class="h-full min-h-[140px] grid place-items-center text-[12px] text-stone-400">
    Belum ada pengukuran pada rentang ini.
  </div>

  <div v-else ref="wadah" class="relative">
    <svg ref="svgEl" :viewBox="`0 0 ${L} ${T}`" class="w-full block"
         :style="{ height: T + 'px' }" role="img"
         :aria-label="`Grafik garis: ${deret.map(d => d.nama).join(', ')}`"
         @pointermove="arahkan" @pointerleave="bidik = null">

      <line v-for="g in bantu" :key="g.r"
            :x1="P.kiri" :x2="L - P.kanan" :y1="g.py" :y2="g.py"
            :stroke="g.r === 0 ? BINGKAI.sumbu : BINGKAI.bantu" stroke-width="1" />

      <text v-for="g in bantu" :key="'t' + g.r"
            :x="P.kiri - 8" :y="g.py + 3.5" text-anchor="end"
            font-size="10" :fill="BINGKAI.redupTinta">{{ g.teks }}</text>

      <text v-for="(l, i) in label" :key="'x' + i"
            v-show="i % langkahLabel === 0"
            :x="posLabel(i).x" :y="T - 6" :text-anchor="posLabel(i).anchor"
            font-size="10" :fill="BINGKAI.redupTinta">{{ l }}</text>

      <!-- Bidang di bawah garis dibuat memudar ke bawah, bukan
           berwarna rata. Bidang rata setebal 10% membentuk balok warna
           yang bersaing dengan garisnya sendiri; yang memudar menuntun
           mata ke garisnya dan tetap menandai luasannya. -->
      <defs v-if="jalurBidang">
        <linearGradient :id="`bidang-${uid}`" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" :stop-color="warna(0)" stop-opacity=".26" />
          <stop offset="100%" :stop-color="warna(0)" stop-opacity="0" />
        </linearGradient>
      </defs>

      <path v-if="jalurBidang" :d="jalurBidang" :fill="`url(#bidang-${uid})`" />

      <template v-for="(d, di) in deret" :key="d.nama">
        <path v-for="(p, pi) in penggal(d.nilai)" :key="pi"
              :d="p" fill="none" :stroke="warna(di)" stroke-width="2"
              stroke-linejoin="round" stroke-linecap="round" />
      </template>

      <!-- Titik ujung menandai deretnya berakhir di mana; cincin putih
           2px menjaganya tetap terbaca saat dua deret berpotongan. -->
      <template v-for="(d, di) in deret" :key="'u' + d.nama">
        <circle v-if="d.nilai.length && d.nilai[d.nilai.length - 1] !== null"
                :cx="x(d.nilai.length - 1)" :cy="y(d.nilai[d.nilai.length - 1] as number)"
                r="4" :fill="warna(di)" :stroke="BINGKAI.latar" stroke-width="2" />
      </template>

      <g v-if="bidik !== null">
        <line :x1="x(bidik)" :x2="x(bidik)" :y1="P.atas" :y2="T - P.bawah"
              :stroke="BINGKAI.sumbu" stroke-width="1" />

        <template v-for="(d, di) in deret" :key="'b' + d.nama">
          <circle v-if="d.nilai[bidik] !== null && d.nilai[bidik] !== undefined"
                  :cx="x(bidik)" :cy="y(d.nilai[bidik] as number)"
                  r="4.5" :fill="warna(di)" :stroke="BINGKAI.latar" stroke-width="2" />
        </template>
      </g>
    </svg>

    <div v-if="bidik !== null"
         class="absolute top-1 pointer-events-none bg-white border border-stone-200
                rounded-lg shadow-lg px-2.5 py-2 min-w-[132px] z-10"
         :class="sisiKiri ? 'left-1' : 'right-1'">
      <p class="text-[10.5px] font-bold text-stone-500 mb-1">{{ label[bidik] }}</p>

      <p v-for="n in nilaiBidik" :key="n.nama"
         class="flex items-baseline gap-1.5 text-[11px] leading-snug">
        <span class="inline-block w-2.5 h-0.5 rounded-full shrink-0"
              :style="{ background: n.warna }"></span>
        <span class="font-bold text-cam-ink num">{{ n.teks }}</span>
        <span class="text-stone-500 truncate">{{ n.nama }}</span>
      </p>
    </div>
  </div>
</template>

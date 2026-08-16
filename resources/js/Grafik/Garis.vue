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
import { computed, ref } from 'vue';
import { BINGKAI, ringkas, batasBulat, warnaDeret, AKSEN } from './warna';

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
}>(), { satuan: '', bidang: false, tinggi: 176 });

const P = { atas: 12, kanan: 12, bawah: 22, kiri: 44 };
const L = 640;

const T = computed(() => props.tinggi);

const warna = (i: number) =>
  props.deret[i]?.warna ?? (props.deret.length === 1 ? AKSEN : warnaDeret(i));

const maks = computed(() => {
  const semua = props.deret.flatMap(d => d.nilai).filter((v): v is number => v !== null);

  return batasBulat(semua.length ? Math.max(...semua) : 1);
});

const x = (i: number) => {
  const n = Math.max(1, props.label.length - 1);

  return P.kiri + (i / n) * (L - P.kiri - P.kanan);
};

const y = (v: number) =>
  P.atas + (1 - v / maks.value) * (T.value - P.atas - P.bawah);

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

  const dasar = T.value - P.bawah;

  return `M${ada[0][0]} ${dasar} `
    + ada.map(([px, py]) => `L${px.toFixed(1)} ${py.toFixed(1)}`).join(' ')
    + ` L${ada[ada.length - 1][0]} ${dasar} Z`;
});

/** Empat garis bantu; angkanya bulat karena sumbunya dibulatkan. */
const bantu = computed(() =>
  [0, 0.25, 0.5, 0.75, 1].map(r => ({ r, nilai: maks.value * r, py: y(maks.value * r) })));

/* ── bidikan ── */

const bidik = ref<number | null>(null);
const svgEl = ref<SVGSVGElement | null>(null);

function arahkan(e: PointerEvent | FocusEvent) {
  const el = svgEl.value;
  if (!el) return;

  if (!(e instanceof PointerEvent)) return;

  const kotak = el.getBoundingClientRect();
  const px    = ((e.clientX - kotak.left) / kotak.width) * L;
  const n     = Math.max(1, props.label.length - 1);
  const rasio = (px - P.kiri) / (L - P.kiri - P.kanan);

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

  <div v-else class="relative">
    <svg ref="svgEl" :viewBox="`0 0 ${L} ${T}`" class="w-full block"
         :style="{ height: T + 'px' }" role="img"
         :aria-label="`Grafik garis: ${deret.map(d => d.nama).join(', ')}`"
         @pointermove="arahkan" @pointerleave="bidik = null">

      <line v-for="g in bantu" :key="g.r"
            :x1="P.kiri" :x2="L - P.kanan" :y1="g.py" :y2="g.py"
            :stroke="g.r === 0 ? BINGKAI.sumbu : BINGKAI.bantu" stroke-width="1" />

      <text v-for="g in bantu" :key="'t' + g.r"
            :x="P.kiri - 8" :y="g.py + 3.5" text-anchor="end"
            font-size="10" :fill="BINGKAI.redupTinta">{{ ringkas(g.nilai) }}</text>

      <text v-for="(l, i) in label" :key="'x' + i"
            v-show="label.length <= 8 || i % Math.ceil(label.length / 7) === 0"
            :x="x(i)" :y="T - 6" text-anchor="middle"
            font-size="10" :fill="BINGKAI.redupTinta">{{ l }}</text>

      <path v-if="jalurBidang" :d="jalurBidang" :fill="warna(0)" fill-opacity="0.1" />

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

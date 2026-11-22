<script setup lang="ts">
/*
  Panggung pembuka halaman Energi & Karbon.

  Satu pita bergradasi di bawah sampul modul: angka terpenting halaman
  dibaca lebih dulu, di atas latar yang langsung memberi tahu aplikasi
  mana yang sedang dibuka — jingga untuk Energi, toska untuk Karbon.

  Dua lapis hiasannya sama-sama digambar di sini, tanpa berkas gambar:
  - motif garis kontur (peta topografi) — dibangkitkan dari rumus, jadi
    tiap tema mendapat bentuk yang sama tanpa satu byte unduhan pun;
  - siluet kegiatan tambang di kaki pita: ekskavator, dump truck,
    konveyor dan stockpile, menara listrik, panel surya, kincir angin,
    dan cerobong — rantai energi sampai emisinya.
*/
import { computed } from 'vue';
import Ikon from './Ikon.vue';

type Stat = { label: string; nilai: string; satuan?: string | null; ikon?: string; catatan?: string | null; arah?: { teks: string; baik: boolean } | null };

const props = withDefaults(defineProps<{
  tema?: 'energi' | 'karbon';
  judul: string;
  sub?: string | null;
  stat?: Stat[];
  lencana?: string[];
}>(), { tema: 'energi', sub: null, stat: () => [], lencana: () => [] });

/* Garis kontur: cincin-cincin tak beraturan di sekitar satu titik
   puncak, seperti peta topografi pit. Deterministik — sama di setiap
   kunjungan. */
const kontur = computed(() => {
  const cx = 900, cy = 40, jalur: string[] = [];
  for (let k = 1; k <= 11; k++) {
    const r = 26 + k * 30;
    const titik: string[] = [];
    for (let i = 0; i <= 72; i++) {
      const t = (i / 72) * Math.PI * 2;
      const rr = r * (1 + 0.13 * Math.sin(3 * t + k * 0.55) + 0.07 * Math.sin(5 * t - k * 0.3) + 0.04 * Math.cos(7 * t + k));
      titik.push(`${(cx + rr * Math.cos(t) * 1.45).toFixed(1)},${(cy + rr * Math.sin(t)).toFixed(1)}`);
    }
    jalur.push('M' + titik.join('L') + 'Z');
  }
  return jalur;
});
</script>

<template>
  <section class="pg" :class="`pg-${tema}`">
    <svg class="pg-kontur" viewBox="0 0 1200 260" preserveAspectRatio="xMaxYMid slice" aria-hidden="true">
      <path v-for="(d, i) in kontur" :key="i" :d="d" />
    </svg>

    <!-- Siluet rantai kegiatan: alat gali-muat-angkut, konveyor, listrik, EBT, cerobong. -->
    <svg class="pg-siluet" viewBox="0 0 1200 120" preserveAspectRatio="xMidYMax slice" aria-hidden="true">
      <g fill="currentColor">
        <!-- tanah bergelombang -->
        <path d="M0 108 C120 100 220 112 340 106 S560 98 700 106 S960 114 1200 104 V120 H0Z" />
        <!-- ekskavator -->
        <path d="M70 96h70l6 10H64Z" /><rect x="78" y="80" width="46" height="16" rx="3" /><rect x="112" y="70" width="14" height="12" rx="2" />
        <path d="M124 82 L170 52 L176 58 L132 88Z" /><path d="M170 52 L196 66 L192 72 L168 60Z" /><path d="M188 66 h18 l-4 14 h-16Z" />
        <!-- dump truck -->
        <path d="M232 70 h92 l10 18 h-102Z" /><rect x="334" y="74" width="22" height="18" rx="3" /><rect x="228" y="88" width="132" height="8" rx="2" />
        <circle cx="252" cy="100" r="9" /><circle cx="280" cy="100" r="9" /><circle cx="340" cy="100" r="9" />
        <!-- stockpile & konveyor -->
        <path d="M400 106 Q450 56 500 106Z" /><path d="M470 100 L600 40 L604 46 L476 106Z" />
        <rect x="520" y="76" width="4" height="30" /><rect x="560" y="58" width="4" height="48" /><rect x="596" y="40" width="10" height="66" />
        <!-- menara listrik -->
        <path d="M690 106 L706 30 L722 106 h-6 L706 44 L696 106Z" /><rect x="688" y="44" width="36" height="3" /><rect x="692" y="58" width="28" height="3" />
        <path d="M706 30 L700 40 h12Z" />
        <!-- panel surya -->
        <path d="M770 98 l20 -16 h44 l-20 16Z" /><rect x="790" y="98" width="3" height="8" /><rect x="812" y="98" width="3" height="8" />
        <path d="M842 98 l20 -16 h44 l-20 16Z" /><rect x="862" y="98" width="3" height="8" /><rect x="884" y="98" width="3" height="8" />
        <!-- kincir angin -->
        <rect x="954" y="44" width="4" height="62" /><circle cx="956" cy="44" r="4" />
        <path d="M956 44 L952 8 L959 10Z" /><path d="M956 44 L986 60 L982 66Z" /><path d="M956 44 L926 62 L924 55Z" />
        <!-- pabrik & cerobong -->
        <path d="M1030 106 V76 l22 -12 v12 l22 -12 v12 l22 -12 V106Z" /><rect x="1104" y="46" width="12" height="60" />
        <circle cx="1114" cy="36" r="9" opacity=".7" /><circle cx="1128" cy="24" r="12" opacity=".5" /><circle cx="1148" cy="14" r="14" opacity=".32" />
      </g>
    </svg>

    <div class="pg-isi">
      <div class="pg-kepala">
        <div class="min-w-0">
          <p class="pg-judul">{{ judul }}</p>
          <p v-if="sub" class="pg-sub">{{ sub }}</p>
        </div>
        <div v-if="lencana.length || $slots.aksi" class="pg-lencana">
          <span v-for="l in lencana" :key="l">{{ l }}</span>
          <slot name="aksi" />
        </div>
      </div>

      <div v-if="stat.length" class="pg-stat" :style="{ '--kolom': Math.min(stat.length, 4) }">
        <div v-for="s in stat" :key="s.label" class="pg-kaca">
          <span class="pg-ikon"><Ikon :nama="s.ikon ?? 'activity'" :ukuran="20" /></span>
          <div class="min-w-0">
            <p class="pg-label">{{ s.label }}</p>
            <p class="pg-nilai num">{{ s.nilai }}<small v-if="s.satuan">{{ s.satuan }}</small></p>
            <p v-if="s.arah || s.catatan" class="pg-catatan">
              <span v-if="s.arah" class="pg-arah" :class="s.arah.baik ? 'baik' : 'buruk'">
                <Ikon :nama="s.arah.teks.startsWith('−') ? 'trending-down' : 'trending-up'" :ukuran="13" :tebal="2.4" />{{ s.arah.teks }}
              </span>
              <template v-if="s.catatan">{{ s.catatan }}</template>
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.pg { position: relative; overflow: hidden; border-radius: 20px; color: #fff; isolation: isolate;
  box-shadow: 0 18px 40px -24px rgba(15, 23, 32, .55); }
.pg-energi { background: linear-gradient(118deg, #7A2E00 0%, #B85C00 28%, #F57C00 64%, #FFA23A 100%); }
.pg-karbon { background: linear-gradient(118deg, #062F2C 0%, #0B4F49 30%, #0F766E 62%, #22A699 100%); }

.pg-kontur { position: absolute; inset: 0; width: 100%; height: 100%; z-index: -1; }
.pg-kontur path { fill: none; stroke: rgba(255, 255, 255, .16); stroke-width: 1.1; }
.pg-siluet { position: absolute; left: 0; right: 0; bottom: 0; width: 100%; height: 92px; z-index: -1; color: rgba(0, 0, 0, .22); }
.pg-karbon .pg-siluet { color: rgba(0, 20, 18, .30); }

.pg-isi { padding: 22px 24px 30px; }
.pg-kepala { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 12px; }
.pg-judul { font-size: 19px; font-weight: 800; letter-spacing: -.01em; }
.pg-sub { font-size: 12.5px; color: rgba(255, 255, 255, .82); margin-top: 3px; }
.pg-lencana { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
.pg-lencana span { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 999px;
  background: rgba(255, 255, 255, .16); border: 1px solid rgba(255, 255, 255, .28); }

.pg-stat { display: grid; gap: 12px; margin-top: 18px; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); }
.pg-kaca { display: flex; gap: 12px; align-items: flex-start; padding: 14px 15px; border-radius: 16px;
  background: rgba(255, 255, 255, .13); border: 1px solid rgba(255, 255, 255, .22);
  backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); }
.pg-ikon { width: 40px; height: 40px; flex: none; border-radius: 12px; display: grid; place-items: center;
  background: rgba(255, 255, 255, .95); color: #B85C00; }
.pg-karbon .pg-ikon { color: #0F766E; }
.pg-label { font-size: 11.5px; font-weight: 600; color: rgba(255, 255, 255, .85); }
.pg-nilai { font-size: 24px; font-weight: 800; line-height: 1.15; letter-spacing: -.02em; margin-top: 2px; }
.pg-nilai small { font-size: 12px; font-weight: 700; margin-left: 5px; color: rgba(255, 255, 255, .8); letter-spacing: 0; }
.pg-catatan { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; font-size: 11px; color: rgba(255, 255, 255, .82); margin-top: 4px; }
.pg-arah { display: inline-flex; align-items: center; gap: 3px; font-weight: 800; padding: 1px 7px 1px 5px; border-radius: 999px; }
.pg-arah.baik { background: rgba(220, 252, 231, .95); color: #166534; }
.pg-arah.buruk { background: rgba(254, 226, 226, .95); color: #991B1B; }

@media (max-width: 640px) {
  .pg-isi { padding: 18px 16px 26px; }
  .pg-nilai { font-size: 21px; }
}
</style>

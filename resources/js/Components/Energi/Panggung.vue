<script setup lang="ts">
/*
  Panggung pembuka halaman Energi & Karbon.

  Satu pita bergradasi di bawah sampul modul: angka terpenting halaman
  dibaca lebih dulu, di atas latar yang langsung memberi tahu aplikasi
  mana yang sedang dibuka — jingga untuk Energi, toska untuk Karbon,
  kuning-arang K3 untuk Scaffolding, hijau hutan untuk LCA, biru baja
  untuk Lifting & Rigging, dan tanah-ke-hijau untuk Rehabilitasi DAS.

  Dua lapis hiasannya sama-sama digambar di sini, tanpa berkas gambar:
  - motif garis kontur (peta topografi) — dibangkitkan dari rumus, jadi
    tiap tema mendapat bentuk yang sama tanpa satu byte unduhan pun;
  - siluet di kaki pita, berbeda per aplikasi: rantai energi tambang
    (Energi/Karbon), perancah berdiri di depan kolom proses dan tangki
    (Scaffolding), daur hidup produk dari tambang ke kapal sampai
    kembali ke tanah (LCA), crane mengangkat beban di workshop,
    pit, dan dermaga (Lifting & Rigging), atau lahan di tepi sungai yang
    tumbuh dari ajir dan bibit P0 sampai hutan siap diserahterimakan
    (Rehabilitasi DAS).
*/
import { computed } from 'vue';
import Ikon from './Ikon.vue';

type Stat = { label: string; nilai: string; satuan?: string | null; ikon?: string; catatan?: string | null; arah?: { teks: string; baik: boolean } | null };

const props = withDefaults(defineProps<{
  tema?: 'energi' | 'karbon' | 'perancah' | 'lca' | 'angkat' | 'rdas';
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

    <!-- Garis peringatan K3 di tepi atas pita Scaffolding. -->
    <div v-if="tema === 'perancah'" class="pg-belang" aria-hidden="true" />

    <!-- Siluet perancah: tiang, ledger, bracing, plank, tangga, pekerja, kolom proses & tangki. -->
    <svg v-if="tema === 'perancah'" class="pg-siluet" viewBox="0 0 1200 120" preserveAspectRatio="xMidYMax slice" aria-hidden="true">
      <g fill="currentColor">
        <path d="M0 110 H1200 V120 H0Z" />
        <template v-for="x in [40, 92, 144, 196]" :key="'t' + x"><rect :x="x" y="14" width="5" height="96" /></template>
        <template v-for="y in [22, 50, 78]" :key="'l' + y"><rect x="40" :y="y" width="161" height="4" /></template>
        <path d="M45 76 L92 52 L94 56 L47 80Z" /><path d="M149 76 L196 52 L198 56 L151 80Z" /><path d="M97 48 L144 24 L146 28 L99 52Z" />
        <rect x="36" y="18" width="169" height="5" rx="1" /><rect x="36" y="46" width="169" height="5" rx="1" />
        <rect x="200" y="20" width="4" height="90" /><rect x="216" y="20" width="4" height="90" />
        <template v-for="y in [30, 42, 54, 66, 78, 90, 102]" :key="'a' + y"><rect x="200" :y="y" width="20" height="3" /></template>
        <circle cx="120" cy="34" r="4.5" /><path d="M114 40 h12 l2 10 h-16Z" /><rect x="115" y="50" width="4" height="10" /><rect x="121" y="50" width="4" height="10" />
        <template v-for="x in [300, 344, 388, 432, 476]" :key="'u' + x"><rect :x="x" y="36" width="4" height="74" /></template>
        <template v-for="y in [44, 70, 96]" :key="'v' + y"><rect x="300" :y="y" width="180" height="3" /></template>
        <path d="M304 94 L344 72 L346 75 L306 97Z" /><path d="M392 68 L432 46 L434 49 L394 71Z" />
        <rect x="540" y="10" width="34" height="100" rx="4" /><rect x="534" y="30" width="46" height="4" /><rect x="534" y="62" width="46" height="4" />
        <path d="M557 10 v-8 h4 v8Z" />
        <template v-for="x in [588, 616]" :key="'k' + x"><rect :x="x" y="24" width="4" height="86" /></template>
        <template v-for="y in [32, 58, 84]" :key="'w' + y"><rect x="588" :y="y" width="32" height="3" /></template>
        <path d="M660 110 V60 a50 16 0 0 1 100 0 V110Z" /><path d="M780 110 V74 a38 12 0 0 1 76 0 V110Z" />
        <rect x="880" y="40" width="160" height="6" /><template v-for="x in [884, 924, 964, 1004, 1036]" :key="'r' + x"><rect :x="x" y="46" width="5" height="64" /></template>
        <path d="M890 46 l34 30 v4 l-34 -30Z" /><path d="M970 46 l34 30 v4 l-34 -30Z" />
        <path d="M1070 110 V30 h12 V110Z" /><path d="M1100 110 V58 l30 -14 l30 14 V110Z" />
      </g>
    </svg>

    <!-- Siluet daur hidup: tambang → angkut → olah → kemas & kapal → kembali ke tanah (pohon, reklamasi). -->
    <svg v-else-if="tema === 'lca'" class="pg-siluet" viewBox="0 0 1200 120" preserveAspectRatio="xMidYMax slice" aria-hidden="true">
      <g fill="currentColor">
        <path d="M0 106 C140 98 260 112 400 104 S640 96 800 106 S1040 114 1200 102 V120 H0Z" />
        <path d="M20 106 L70 62 L110 82 L150 50 L210 106Z" />
        <path d="M232 74 h80 l9 16 h-89Z" /><rect x="320" y="78" width="20" height="14" rx="3" /><rect x="228" y="90" width="116" height="7" rx="2" />
        <circle cx="250" cy="101" r="8" /><circle cx="276" cy="101" r="8" /><circle cx="326" cy="101" r="8" />
        <path d="M392 106 V70 l24 -12 v12 l24 -12 v12 l24 -12 V106Z" /><rect x="476" y="40" width="11" height="66" />
        <circle cx="482" cy="30" r="7" opacity=".6" /><circle cx="494" cy="20" r="9" opacity=".4" />
        <rect x="530" y="84" width="26" height="22" /><rect x="560" y="76" width="26" height="30" /><rect x="545" y="60" width="26" height="22" />
        <path d="M620 92 h150 l-16 18 h-118Z" /><rect x="660" y="70" width="60" height="22" /><rect x="700" y="54" width="10" height="16" />
        <path d="M800 70 a26 26 0 1 1 0.1 0" fill="none" stroke="currentColor" stroke-width="7" />
        <path d="M822 58 l10 12 -14 4Z" /><path d="M778 82 l-10 -12 14 -4Z" />
        <template v-for="(x, i) in [880, 930, 985, 1040, 1100, 1150]" :key="'p' + x">
          <rect :x="x - 2" :y="70 - (i % 2) * 14" width="5" :height="40 + (i % 2) * 14" />
          <circle :cx="x" :cy="62 - (i % 2) * 14" :r="18 - (i % 3) * 2" />
        </template>
      </g>
    </svg>

    <!-- Siluet pengangkatan: mobile crane mengangkat engine, crawler lattice, overhead crane workshop, crane dermaga. -->
    <svg v-else-if="tema === 'angkat'" class="pg-siluet" viewBox="0 0 1200 120" preserveAspectRatio="xMidYMax slice" aria-hidden="true">
      <g fill="currentColor">
        <path d="M0 110 H1200 V120 H0Z" />
        <!-- mobile crane: carrier, outrigger, boom, kabel, beban -->
        <rect x="30" y="92" width="120" height="14" rx="3" /><circle cx="52" cy="108" r="6" /><circle cx="74" cy="108" r="6" /><circle cx="128" cy="108" r="6" />
        <rect x="22" y="104" width="14" height="6" /><rect x="146" y="104" width="14" height="6" />
        <path d="M72 92 h40 v-12 h-28Z" /><rect x="112" y="76" width="18" height="16" rx="2" />
        <path d="M90 84 L262 18 L265 23 L94 90Z" /><rect x="261" y="20" width="2" height="54" />
        <rect x="246" y="74" width="32" height="20" rx="2" /><path d="M262 74 l-12 -6 h24Z" />
        <!-- crawler crane lattice -->
        <rect x="360" y="96" width="110" height="12" rx="6" /><rect x="380" y="76" width="60" height="20" rx="2" />
        <path d="M398 78 L560 4 L563 9 L404 84Z" /><path d="M406 82 L566 8" stroke="currentColor" stroke-width="2" fill="none" />
        <template v-for="i in 7" :key="'lt' + i"><path :d="`M${404 + i * 21} ${82 - i * 9.6} l10 -9`" stroke="currentColor" stroke-width="2" fill="none" /></template>
        <rect x="561" y="8" width="2" height="62" /><path d="M548 70 h28 v14 h-28Z" />
        <!-- overhead crane workshop -->
        <rect x="660" y="40" width="8" height="70" /><rect x="840" y="40" width="8" height="70" /><rect x="650" y="32" width="208" height="10" rx="2" />
        <rect x="740" y="42" width="30" height="10" /><rect x="754" y="52" width="2" height="22" /><rect x="732" y="74" width="46" height="24" rx="3" />
        <path d="M640 110 V40 l14 -12 h200 l14 12 V110 h-6 V44 H646 V110Z" opacity=".55" />
        <!-- crane dermaga & kapal -->
        <rect x="960" y="30" width="10" height="80" /><rect x="1010" y="30" width="10" height="80" /><rect x="930" y="22" width="190" height="10" />
        <path d="M960 70 L1020 40 L1022 44 L962 74Z" /><rect x="1086" y="32" width="2" height="34" /><rect x="1074" y="66" width="26" height="14" />
        <path d="M1050 100 h150 v10 h-160Z" /><rect x="1100" y="86" width="40" height="14" /><rect x="1150" y="80" width="30" height="20" />
      </g>
    </svg>

    <!-- Siluet rehabilitasi DAS: dari ajir & bibit P0 di kiri, P1–P2, sampai hutan BAST di kanan; sungai di kaki. -->
    <svg v-else-if="tema === 'rdas'" class="pg-siluet" viewBox="0 0 1200 120" preserveAspectRatio="xMidYMax slice" aria-hidden="true">
      <g fill="currentColor">
        <path d="M0 98 C160 90 300 104 460 96 S760 88 920 98 S1100 104 1200 94 V120 H0Z" />
        <path d="M0 112 C200 104 420 118 640 110 S1000 104 1200 112 V120 H0Z" opacity=".55" />
        <!-- ajir & bibit P0 -->
        <template v-for="x in [40, 78, 116, 154, 192, 230]" :key="'a' + x">
          <rect :x="x" y="76" width="2.5" height="22" />
          <path :d="`M${x + 7} 97 v-9 M${x + 7} 90 q-5 -2 -6 -7 q5 1 6 6 q1 -6 6 -7 q-1 5 -6 8`" stroke="currentColor" stroke-width="2" fill="none" />
        </template>
        <!-- tanaman muda P1–P2 -->
        <template v-for="(x, i) in [300, 350, 400, 450, 500, 550]" :key="'m' + x">
          <rect :x="x - 1.5" :y="78 - i * 3" width="3" :height="20 + i * 3" />
          <circle :cx="x" :cy="72 - i * 3" :r="9 + i * 1.4" />
        </template>
        <!-- tegakan siap serah terima -->
        <template v-for="(x, i) in [640, 700, 760, 820, 880, 940, 1000, 1060, 1120, 1170]" :key="'h' + x">
          <rect :x="x - 2.5" :y="56 - (i % 3) * 8" width="5" :height="42 + (i % 3) * 8" />
          <circle :cx="x" :cy="46 - (i % 3) * 8" :r="20 + (i % 2) * 4" />
          <circle :cx="x - 12" :cy="56 - (i % 3) * 8" :r="13" />
          <circle :cx="x + 12" :cy="54 - (i % 3) * 8" :r="14" />
        </template>
      </g>
    </svg>

    <!-- Siluet rantai kegiatan: alat gali-muat-angkut, konveyor, listrik, EBT, cerobong. -->
    <svg v-else class="pg-siluet" viewBox="0 0 1200 120" preserveAspectRatio="xMidYMax slice" aria-hidden="true">
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
.pg-perancah { background: linear-gradient(118deg, #17181C 0%, #2E2A1E 26%, #8A5A00 60%, #E3A008 88%, #F7C948 100%); }
.pg-lca { background: linear-gradient(118deg, #052E1C 0%, #0E4D33 30%, #15803D 64%, #6BAF3A 100%); }
.pg-rdas { background: linear-gradient(118deg, #3B2A16 0%, #5C4326 24%, #2F6B3A 56%, #3F9A4E 80%, #A3D36B 100%); }
.pg-angkat { background: linear-gradient(118deg, #0A1628 0%, #12305A 30%, #1D4ED8 66%, #4F8EF7 100%); }
.pg-belang { position: absolute; left: 0; right: 0; top: 0; height: 7px; z-index: -1;
  background: repeating-linear-gradient(-45deg, #F7C948 0 14px, #17181C 14px 28px); opacity: .9; }

.pg-kontur { position: absolute; inset: 0; width: 100%; height: 100%; z-index: -1; }
.pg-kontur path { fill: none; stroke: rgba(255, 255, 255, .16); stroke-width: 1.1; }
.pg-siluet { position: absolute; left: 0; right: 0; bottom: 0; width: 100%; height: 92px; z-index: -1; color: rgba(0, 0, 0, .22); }
.pg-karbon .pg-siluet { color: rgba(0, 20, 18, .30); }
.pg-perancah .pg-siluet { color: rgba(10, 10, 10, .34); }
.pg-lca .pg-siluet { color: rgba(2, 30, 16, .30); }
.pg-angkat .pg-siluet { color: rgba(4, 12, 30, .34); }
.pg-rdas .pg-siluet { color: rgba(18, 32, 14, .34); }

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
.pg-perancah .pg-ikon { color: #A16207; }
.pg-lca .pg-ikon { color: #15803D; }
.pg-angkat .pg-ikon { color: #1D4ED8; }
.pg-rdas .pg-ikon { color: #2F6B3A; }
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

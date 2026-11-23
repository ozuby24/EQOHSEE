<script setup lang="ts">
/*
  Tampak depan rigging: kait, kaki sling pada sudut efektifnya, tegangan
  tiap sisi, titik berat beban, dan spreader bila dipakai. Kaki di sisi
  titik berat diberi tegangan maksimum, sisi lain minimum — persis
  seperti yang dihitung server — dan warnanya mengikuti utilisasi WLL.
*/
import { computed } from 'vue';
import { n } from '../../energi';

const props = defineProps<{ r: any; cog?: number; spreader?: boolean }>();
const W = 360, H = 230;

const g = computed(() => {
  const r = props.r;
  const sudut = Math.max(15, Math.min(90, Number(r.sudut_efektif ?? 60)));
  const a = (sudut * Math.PI) / 180;
  const hook = { x: W / 2, y: 30 };
  const tinggiKaki = 118;
  const spreader = props.spreader || r.sudut_efektif >= 90 && r.kaki > 1;
  const setengah = spreader ? 92 : Math.min(150, tinggiKaki / Math.tan(a));
  const yAtas = hook.y + tinggiKaki;
  const offset = Math.max(-0.35, Math.min(0.35, (props.cog ?? 0) / 100));
  return { sudut, hook, yAtas, setengah, spreader, offset, satuKaki: r.kaki === 1 };
});

const warnaUtil = (u: number) => (u > 100 ? '#DC2626' : u > 90 ? '#D97706' : '#15803D');
const util = computed(() => Number(props.r.util_sling ?? 0));
</script>

<template>
  <svg :viewBox="`0 0 ${W} ${H}`" class="ds" role="img" :aria-label="`Rigging ${r.kaki} kaki, sudut ${n(r.sudut_efektif, 0)} derajat, tegangan maksimum ${n(r.tegangan_maks_t, 2)} ton`">
    <!-- kait -->
    <line :x1="g.hook.x" y1="0" :x2="g.hook.x" :y2="g.hook.y - 8" class="ds-kabel" />
    <path :d="`M${g.hook.x - 9} ${g.hook.y - 10} h18 l-3 12 h-12z`" class="ds-kait" />

    <!-- satu kaki vertikal -->
    <template v-if="g.satuKaki">
      <line :x1="g.hook.x" :y1="g.hook.y + 2" :x2="g.hook.x" :y2="g.yAtas" :stroke="warnaUtil(util)" stroke-width="4" />
      <text :x="g.hook.x + 8" :y="(g.hook.y + g.yAtas) / 2" class="ds-teks">{{ n(r.tegangan_maks_t, 2) }} t</text>
    </template>

    <!-- spreader: kaki atas ke balok, kaki bawah tegak -->
    <template v-else-if="g.spreader">
      <line :x1="g.hook.x" :y1="g.hook.y + 2" :x2="g.hook.x - g.setengah" :y2="g.yAtas - 46" :stroke="warnaUtil(util)" stroke-width="3" />
      <line :x1="g.hook.x" :y1="g.hook.y + 2" :x2="g.hook.x + g.setengah" :y2="g.yAtas - 46" :stroke="warnaUtil(util)" stroke-width="3" />
      <rect :x="g.hook.x - g.setengah - 10" :y="g.yAtas - 52" :width="g.setengah * 2 + 20" height="10" rx="2" class="ds-spreader" />
      <line :x1="g.hook.x - g.setengah" :y1="g.yAtas - 42" :x2="g.hook.x - g.setengah" :y2="g.yAtas" :stroke="warnaUtil(util)" stroke-width="3" />
      <line :x1="g.hook.x + g.setengah" :y1="g.yAtas - 42" :x2="g.hook.x + g.setengah" :y2="g.yAtas" :stroke="warnaUtil(util)" stroke-width="3" />
      <text :x="g.hook.x + g.setengah + 8" :y="g.yAtas - 18" class="ds-teks">{{ n(r.tegangan_maks_t, 2) }} t</text>
      <text :x="g.hook.x - g.setengah - 8" :y="g.yAtas - 18" text-anchor="end" class="ds-teks ds-redup">{{ n(r.tegangan_min_t, 2) }} t</text>
    </template>

    <!-- bridle dua sisi -->
    <template v-else>
      <line :x1="g.hook.x" :y1="g.hook.y + 2" :x2="g.hook.x - g.setengah" :y2="g.yAtas" :stroke="warnaUtil(Number(r.util_sling) * (r.tegangan_min_t / Math.max(0.01, r.tegangan_maks_t)))" stroke-width="3.5" />
      <line :x1="g.hook.x" :y1="g.hook.y + 2" :x2="g.hook.x + g.setengah" :y2="g.yAtas" :stroke="warnaUtil(util)" stroke-width="3.5" />
      <path :d="`M${g.hook.x + g.setengah - 30} ${g.yAtas} A30 30 0 0 0 ${g.hook.x + g.setengah - 30 * Math.cos(g.sudut * Math.PI / 180)} ${g.yAtas - 30 * Math.sin(g.sudut * Math.PI / 180)}`" class="ds-busur" />
      <text :x="g.hook.x + g.setengah - 40" :y="g.yAtas - 8" text-anchor="end" class="ds-teks-kecil">{{ n(g.sudut, 0) }}°</text>
      <text :x="g.hook.x + g.setengah / 2 + 10" :y="(g.hook.y + g.yAtas) / 2" class="ds-teks">{{ n(r.tegangan_maks_t, 2) }} t</text>
      <text :x="g.hook.x - g.setengah / 2 - 10" :y="(g.hook.y + g.yAtas) / 2" text-anchor="end" class="ds-teks ds-redup">{{ n(r.tegangan_min_t, 2) }} t</text>
      <text v-if="r.tekan_horizontal_t > 0" :x="g.hook.x" :y="g.yAtas - 6" text-anchor="middle" class="ds-teks-kecil">← tekan {{ n(r.tekan_horizontal_t, 2) }} t →</text>
    </template>

    <!-- beban & titik berat -->
    <rect :x="W / 2 - Math.max(70, g.setengah + 14)" :y="g.yAtas" :width="Math.max(140, g.setengah * 2 + 28)" height="44" rx="4" class="ds-beban" />
    <g :transform="`translate(${W / 2 + g.offset * Math.max(140, g.setengah * 2 + 28)}, ${g.yAtas + 22})`">
      <circle r="8" class="ds-cog" /><path d="M-8 0 A8 8 0 0 1 0 -8 V0Z M8 0 A8 8 0 0 1 0 8 V0Z" class="ds-cog-isi" />
    </g>
    <text :x="W / 2" :y="H - 8" text-anchor="middle" class="ds-teks-kecil">{{ r.kaki }} kaki ({{ r.kaki_efektif }} efektif){{ g.spreader ? ' · spreader, sling tegak' : '' }} · LAM {{ n(r.lam, 3) }} · beban {{ n(r.beban_t, 2) }} t</text>
  </svg>
</template>

<style scoped>
.ds { width: 100%; height: auto; display: block; }
.ds-kabel { stroke: #44403C; stroke-width: 2; }
.ds-kait { fill: #44403C; }
.ds-spreader { fill: #57534E; }
.ds-busur { fill: none; stroke: #2563EB; stroke-width: 1.5; }
.ds-beban { fill: #FDE68A; stroke: #B45309; stroke-width: 1.5; }
.ds-cog { fill: #fff; stroke: #1C1917; stroke-width: 1.5; }
.ds-cog-isi { fill: #1C1917; }
.ds-teks { font-size: 12px; font-weight: 800; fill: #1C1917; }
.ds-teks-kecil { font-size: 10.5px; font-weight: 600; fill: #57534E; }
.ds-redup { fill: #78716C; }
:root[data-tema="gelap"] .ds-kabel { stroke: #B4BEC3; }
:root[data-tema="gelap"] .ds-kait, :root[data-tema="gelap"] .ds-spreader { fill: #B4BEC3; }
:root[data-tema="gelap"] .ds-teks { fill: #E8EDEF; }
:root[data-tema="gelap"] .ds-teks-kecil, :root[data-tema="gelap"] .ds-redup { fill: #93A1A8; }
</style>

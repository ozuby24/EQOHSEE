<script setup lang="ts">
/*
  Tampak samping pengangkatan, digambar dari hasil simulasi — bukan
  gambar hiasan. Panjang boom, sudut, radius, tinggi ujung boom, tinggi
  rintangan, dan jarak bebas di atasnya memakai skala yang sama, jadi
  ketika radius digeser di form, boom ikut rebah dan kait ikut turun
  menabrak rintangan bila ruang bebasnya habis.
*/
import { computed } from 'vue';
import { n } from '../../energi';
import { simulasi } from '../../angkat';

const props = defineProps<{ h: any }>();

const W = 560, H = 300, G = 262, P0 = 92;

const g = computed(() => {
  const h = props.h;
  const tetap = ['overhead', 'hoist'].includes(h.alat?.kategori);
  const R = Number(h.radius_m), B = Number(h.boom_m), ujung = Number(h.tinggi_ujung_m), perlu = Number(h.tinggi_perlu_m);
  const rint = Number(h.tinggi_rintangan_m ?? h.masukan?.tinggi_rintangan_m ?? 0);
  const tb = Number(h.masukan?.tinggi_beban_m ?? 2), tr = Number(h.masukan?.tinggi_rigging_m ?? 3);
  const maxX = R + 3.5, maxY = Math.max(ujung, perlu) + 2;
  const s = Math.min((W - P0 - 34) / maxX, (G - 26) / maxY);
  const a = (Number(h.sudut_boom) * Math.PI) / 180;
  const pivot = { x: P0, y: G - 2.4 * s };
  const tip = tetap ? { x: P0 + R * s, y: G - B * s } : { x: P0 + B * Math.cos(a) * s, y: G - ujung * s };
  const dasarBeban = rint + 1;
  let kait = dasarBeban + tb + tr;
  const tabrak = kait > (tetap ? B : ujung) - 0.6;
  if (tabrak) kait = (tetap ? B : ujung) - 0.6;
  const lebarBeban = Math.max(44, Math.min(96, 2.6 * s));
  const lebarRint = Math.max(34, Math.min(130, 4.2 * s));
  return {
    tetap, s, pivot, tip, R, B, ujung, perlu, rint, tabrak,
    kaitY: G - kait * s,
    beban: { x: tip.x - lebarBeban / 2, y: G - (Math.max(dasarBeban, kait - tr - tb) + tb) * s, w: lebarBeban, h: Math.max(10, tb * s) },
    rintangan: { x: tip.x - lebarRint / 2, y: G - rint * s, w: lebarRint, h: rint * s },
    perluY: G - perlu * s,
  };
});
const warna = computed(() => simulasi(props.h?.putusan).warna);
const label = (x: unknown, d = 1) => n(x, d);
</script>

<template>
  <svg :viewBox="`0 0 ${W} ${H}`" class="dc" role="img" :aria-label="`Tampak samping: radius ${label(h.radius_m)} m, boom ${label(h.boom_m)} m, ujung ${label(h.tinggi_ujung_m)} m`">
    <defs>
      <pattern id="dc-arsir" width="7" height="7" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
        <line x1="0" y1="0" x2="0" y2="7" class="dc-arsir" stroke-width="3" />
      </pattern>
    </defs>

    <!-- tanah & landasan -->
    <rect x="0" :y="G" :width="W" :height="H - G" class="dc-tanah" />
    <line x1="0" :y1="G" :x2="W" :y2="G" class="dc-garis-tanah" />

    <!-- rintangan yang harus dilewati beban -->
    <g v-if="g.rint > 0">
      <rect :x="g.rintangan.x" :y="g.rintangan.y" :width="g.rintangan.w" :height="g.rintangan.h" fill="url(#dc-arsir)" class="dc-rintangan" />
      <text :x="g.rintangan.x + g.rintangan.w / 2" :y="G - 6" text-anchor="middle" class="dc-teks-kecil">rintangan {{ label(g.rint) }} m</text>
    </g>

    <!-- tinggi yang diperlukan -->
    <line :x1="Math.max(P0 + 10, g.tip.x - 110)" :y1="g.perluY" :x2="Math.min(W - 6, g.tip.x + 80)" :y2="g.perluY" class="dc-perlu" />
    <text :x="Math.min(W - 6, g.tip.x + 80)" :y="g.perluY - 5" text-anchor="end" class="dc-teks-kecil">perlu {{ label(g.perlu) }} m</text>

    <!-- crane tetap: tiang & girder -->
    <g v-if="g.tetap">
      <rect :x="P0 - 30" :y="g.tip.y" width="10" :height="G - g.tip.y" class="dc-badan" />
      <rect :x="Math.min(W - 20, g.tip.x + 60)" :y="g.tip.y" width="10" :height="G - g.tip.y" class="dc-badan" />
      <rect :x="P0 - 30" :y="g.tip.y - 10" :width="Math.min(W - 20, g.tip.x + 60) - P0 + 40" height="12" rx="2" :fill="warna" />
      <rect :x="g.tip.x - 14" :y="g.tip.y + 2" width="28" height="12" rx="2" class="dc-badan" />
    </g>

    <!-- crane bergerak: carrier, outrigger, boom -->
    <g v-else>
      <rect :x="P0 - 66" :y="G - 22" width="104" height="16" rx="3" class="dc-badan" />
      <rect :x="P0 - 76" :y="G - 6" width="20" height="6" class="dc-badan" />
      <rect :x="P0 + 28" :y="G - 6" width="20" height="6" class="dc-badan" />
      <circle :cx="P0 - 44" :cy="G - 4" r="6" class="dc-roda" /><circle :cx="P0 - 22" :cy="G - 4" r="6" class="dc-roda" /><circle :cx="P0 + 16" :cy="G - 4" r="6" class="dc-roda" />
      <path :d="`M${P0 - 24} ${G - 22} h44 v-14 h-30 z`" class="dc-badan" />
      <rect :x="P0 + 6" :y="G - 40" width="20" height="16" rx="3" class="dc-kabin" />
      <line :x1="g.pivot.x" :y1="g.pivot.y" :x2="g.tip.x" :y2="g.tip.y" :stroke="warna" stroke-width="9" stroke-linecap="round" />
      <line :x1="g.pivot.x" :y1="g.pivot.y" :x2="g.tip.x" :y2="g.tip.y" class="dc-boom-dalam" stroke-width="2.5" stroke-dasharray="6 6" />
      <circle :cx="g.tip.x" :cy="g.tip.y" r="5" :fill="warna" />
      <text :x="(g.pivot.x + g.tip.x) / 2 - 8" :y="(g.pivot.y + g.tip.y) / 2 - 10" text-anchor="end" class="dc-teks">boom {{ label(g.B) }} m · {{ label(h.sudut_boom) }}°</text>
    </g>

    <!-- kabel, kait, rigging, beban -->
    <line :x1="g.tip.x" :y1="g.tip.y + (g.tetap ? 14 : 4)" :x2="g.tip.x" :y2="g.kaitY" class="dc-kabel" />
    <path :d="`M${g.tip.x - 6} ${g.kaitY - 4} h12 l-2 9 h-8z`" class="dc-kait" />
    <line :x1="g.tip.x" :y1="g.kaitY + 5" :x2="g.beban.x + 4" :y2="g.beban.y" class="dc-sling" />
    <line :x1="g.tip.x" :y1="g.kaitY + 5" :x2="g.beban.x + g.beban.w - 4" :y2="g.beban.y" class="dc-sling" />
    <rect :x="g.beban.x" :y="g.beban.y" :width="g.beban.w" :height="g.beban.h" rx="3" class="dc-beban" />
    <text :x="g.tip.x" :y="g.beban.y + g.beban.h / 2 + 4" text-anchor="middle" class="dc-teks-beban">{{ label(h.berat_kotor_t) }} t</text>

    <!-- tinggi ujung -->
    <text :x="g.tip.x + 10" :y="g.tip.y - 4" class="dc-teks">ujung {{ label(g.tetap ? g.B : g.ujung) }} m</text>
    <text v-if="g.tabrak" :x="g.tip.x + 10" :y="g.tip.y + 12" class="dc-teks dc-teks-merah">ruang bebas tidak cukup</text>

    <!-- dimensi radius -->
    <line :x1="P0" :y1="G + 18" :x2="g.tip.x" :y2="G + 18" class="dc-dimensi" />
    <line :x1="P0" :y1="G + 12" :x2="P0" :y2="G + 24" class="dc-dimensi" />
    <line :x1="g.tip.x" :y1="G + 12" :x2="g.tip.x" :y2="G + 24" class="dc-dimensi" />
    <text :x="(P0 + g.tip.x) / 2" :y="G + 33" text-anchor="middle" class="dc-teks">radius {{ label(g.R) }} m</text>
  </svg>
</template>

<style scoped>
.dc { width: 100%; height: auto; display: block; }
.dc-tanah { fill: #E7E5E4; }
.dc-garis-tanah { stroke: #A8A29E; stroke-width: 1.5; }
.dc-arsir { stroke: #D6D3D1; }
.dc-rintangan { stroke: #78716C; stroke-width: 1.2; }
.dc-perlu { stroke: #2563EB; stroke-width: 1.4; stroke-dasharray: 5 4; }
.dc-badan { fill: #44403C; }
.dc-roda { fill: #1C1917; }
.dc-kabin { fill: #93C5FD; stroke: #44403C; stroke-width: 2; }
.dc-boom-dalam { stroke: rgba(255, 255, 255, .55); }
.dc-kabel { stroke: #292524; stroke-width: 1.4; }
.dc-kait { fill: #292524; }
.dc-sling { stroke: #EA580C; stroke-width: 2; }
.dc-beban { fill: #FBBF24; stroke: #92400E; stroke-width: 1.5; }
.dc-dimensi { stroke: #78716C; stroke-width: 1.2; }
.dc-teks { font-size: 11px; font-weight: 700; fill: #44403C; }
.dc-teks-kecil { font-size: 10px; font-weight: 600; fill: #78716C; }
.dc-teks-beban { font-size: 10.5px; font-weight: 800; fill: #451A03; }
.dc-teks-merah { fill: #DC2626; }
:root[data-tema="gelap"] .dc-tanah { fill: #2A3034; }
:root[data-tema="gelap"] .dc-garis-tanah, :root[data-tema="gelap"] .dc-dimensi { stroke: #5B676D; }
:root[data-tema="gelap"] .dc-arsir { stroke: #3A4449; }
:root[data-tema="gelap"] .dc-badan, :root[data-tema="gelap"] .dc-kait { fill: #B4BEC3; }
:root[data-tema="gelap"] .dc-roda { fill: #E8EDEF; }
:root[data-tema="gelap"] .dc-kabel { stroke: #B4BEC3; }
:root[data-tema="gelap"] .dc-teks { fill: #D5DDE1; }
:root[data-tema="gelap"] .dc-teks-kecil { fill: #93A1A8; }
:root[data-tema="gelap"] .dc-teks-merah { fill: #F5A9A9; }
</style>

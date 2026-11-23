<script setup lang="ts">
/*
  Aliran lima tahap daur hidup — dari tambang sampai akhir hidup.

  Tiap tahap satu kartu berwarna tetap (ekstraksi toska, angkutan biru,
  pengolahan amber, distribusi jingga, akhir hidup abu), bersambung panah
  searah aliran material. Tinggi pita bawah sebanding porsinya terhadap
  GWP total; tahap berkontribusi negatif (kredit reklamasi, daur ulang)
  ditandai pita hijau. Klik satu tahap untuk menyaring hotspot di bawahnya.
*/
import Ikon from '../Energi/Ikon.vue';
import { n } from '../../energi';

const props = withDefaults(defineProps<{ tahap: any[]; satuan?: string; terpilih?: string | null }>(), { satuan: 'kg CO₂e', terpilih: null });
const emit = defineEmits<{ (e: 'pilih', t: string | null): void }>();
const IKON: Record<string, string> = { ekstraksi: 'pickaxe', angkut: 'truck', olah: 'factory', distribusi: 'ship', akhir: 'recycle' };
const maks = () => Math.max(1e-9, ...props.tahap.map(t => Math.abs(t.gwp)));
</script>

<template>
  <div class="al">
    <template v-for="(t, i) in tahap" :key="t.tahap">
      <button type="button" class="al-tahap" :class="terpilih === t.tahap && 'al-aktif'" :style="{ '--w': t.warna }"
              @click="emit('pilih', terpilih === t.tahap ? null : t.tahap)">
        <span class="al-ikon"><Ikon :nama="IKON[t.tahap] ?? 'activity'" :ukuran="18" /></span>
        <span class="al-label">{{ t.pendek }}</span>
        <span class="al-nilai num">{{ n(t.gwp, Math.abs(t.gwp) < 10 ? 2 : 0) }}</span>
        <span class="al-satuan">{{ satuan }}</span>
        <span class="al-porsi num" :class="t.gwp < 0 && 'al-kredit'">{{ t.gwp < 0 ? 'kredit ' : '' }}{{ n(t.porsi, 1) }}%</span>
        <span class="al-pita"><i :style="{ width: Math.max(3, Math.abs(t.gwp) / maks() * 100) + '%', background: t.gwp < 0 ? '#16A34A' : t.warna }" /></span>
      </button>
      <span v-if="i < tahap.length - 1" class="al-panah" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="22" height="22"><path d="M4 12h14m-5-5 5 5-5 5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" /></svg>
      </span>
    </template>
  </div>
</template>

<style scoped>
.al { display: flex; align-items: stretch; gap: 6px; overflow-x: auto; padding-bottom: 2px; }
.al-tahap { --w: #0F766E; flex: 1 1 0; min-width: 132px; text-align: left; display: grid; gap: 1px; padding: 12px 13px 11px; border-radius: 16px;
  background: color-mix(in srgb, var(--w) 8%, #fff); border: 1.5px solid color-mix(in srgb, var(--w) 25%, #fff); transition: transform .12s, box-shadow .12s; }
.al-tahap:hover { transform: translateY(-2px); box-shadow: 0 12px 22px -16px var(--w); }
.al-aktif { border-color: var(--w); box-shadow: 0 0 0 3px color-mix(in srgb, var(--w) 25%, transparent); }
.al-ikon { width: 34px; height: 34px; border-radius: 11px; display: grid; place-items: center; color: #fff; background: var(--w); margin-bottom: 6px; }
.al-label { font-size: 11.5px; font-weight: 800; color: var(--w); }
.al-nilai { font-size: 20px; font-weight: 900; color: #1C1917; letter-spacing: -.02em; line-height: 1.15; }
.al-satuan { font-size: 10.5px; color: #78716C; font-weight: 600; }
.al-porsi { font-size: 11px; font-weight: 800; color: #44403C; margin-top: 4px; }
.al-kredit { color: #15803D; }
.al-pita { height: 6px; border-radius: 999px; background: rgba(0,0,0,.06); overflow: hidden; margin-top: 6px; display: block; }
.al-pita i { display: block; height: 100%; border-radius: 999px; }
.al-panah { display: grid; place-items: center; color: #A8A29E; flex: none; }
@media (max-width: 720px) { .al { flex-direction: column; } .al-panah { transform: rotate(90deg); } }
:root[data-tema="gelap"] .al-tahap { background: color-mix(in srgb, var(--w) 16%, #1B2124); border-color: color-mix(in srgb, var(--w) 40%, #1B2124); }
:root[data-tema="gelap"] .al-nilai { color: #E8EDEF; }
:root[data-tema="gelap"] .al-porsi { color: #C9D2D6; }
</style>

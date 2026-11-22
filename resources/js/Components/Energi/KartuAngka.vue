<script setup lang="ts">
/*
  Angka utama modul Energi & Karbon.

  Warna kartu menandai KATEGORI angkanya (energi jingga, emisi toska,
  biaya ungu, EBT hijau, dan seterusnya), bukan keadaannya — keadaan
  terhadap target dibawa lencana statusnya sendiri. Dua makna warna pada
  satu permukaan tidak dapat dibedakan pembacanya; karena itu kartu yang
  melewati target hanya diberi bingkai merah, bukan diwarnai ulang.
*/
import { computed } from 'vue';
import Ikon from './Ikon.vue';
import { n, rp } from '../../energi';

type Warna = 'oranye' | 'amber' | 'toska' | 'biru' | 'ungu' | 'hijau' | 'merah' | 'langit' | 'mawar' | 'abu';

const props = withDefaults(defineProps<{
  label: string; nilai: unknown; satuan?: string; desimal?: number;
  rag?: string | null; catatan?: string | null;
  arah?: { persen: number; baik: boolean } | null;
  ikon?: string | null; warna?: Warna | null;
}>(), { satuan: '', desimal: 1, rag: null, catatan: null, arah: null, ikon: null, warna: null });

const angka = computed(() => props.satuan === 'Rp' ? rp(props.nilai) : n(props.nilai, props.desimal));

/* Tebakan kategori dari satuan dan labelnya; pemanggil boleh menimpanya. */
const tebak = computed<[string, Warna]>(() => {
  const s = props.satuan, l = props.label.toLowerCase();
  if (s === 'Rp' || /rp/i.test(s)) return ['banknote', 'ungu'];
  if (/ebt|surya|plts/.test(l)) return ['sun', 'hijau'];
  if (/rekonsiliasi|selisih/.test(l)) return ['droplets', 'mawar'];
  if (/CO₂e?\/ton|tco2e\/ton/i.test(s)) return ['cloud-cog', 'langit'];
  if (/CO/.test(s) || /emisi|scope/.test(l)) return ['cloud', 'toska'];
  if (/GJ\/ton|intensitas/i.test(s + l)) return ['gauge', 'amber'];
  if (/GJ|TOE/.test(s)) return ['zap', 'oranye'];
  if (/kWh|kW/.test(s)) return ['plug-zap', 'biru'];
  if (/L\/|^L$|solar|bbm/i.test(s + l)) return ['fuel', 'amber'];
  if (/ton|BCM/.test(s)) return ['mountain', 'langit'];
  if (/%/.test(s)) return ['percent', 'biru'];
  return ['activity', 'abu'];
});
const ikonPakai = computed(() => props.ikon ?? tebak.value[0]);
const warnaPakai = computed(() => props.warna ?? tebak.value[1]);

const STATUS: Record<string, string> = { hijau: 'Sesuai target', kuning: 'Mendekati batas', merah: 'Melewati target' };
</script>

<template>
  <div class="ka" :class="[`ka-${warnaPakai}`, { 'ka-lewat': rag === 'merah' }]">
    <span class="ka-ikon"><Ikon :nama="ikonPakai" :ukuran="21" /></span>
    <div class="ka-isi">
      <p class="ka-label">{{ label }}</p>
      <p class="ka-nilai num">{{ angka }}<small v-if="satuan && satuan !== 'Rp'">{{ satuan }}</small></p>
      <div v-if="(rag && STATUS[rag]) || arah || catatan" class="ka-bawah">
        <span v-if="rag && STATUS[rag]" class="ka-status" :class="`st-${rag}`">{{ STATUS[rag] }}</span>
        <span v-if="arah" class="ka-arah" :class="arah.baik ? 'baik' : 'buruk'" :title="'Dibanding periode yang sama tahun lalu'">
          <Ikon :nama="arah.persen >= 0 ? 'trending-up' : 'trending-down'" :ukuran="13" :tebal="2.4" />{{ arah.persen >= 0 ? '+' : '−' }}{{ n(Math.abs(arah.persen), 1) }}%
        </span>
        <span v-if="catatan" class="ka-catatan">{{ catatan }}</span>
      </div>
    </div>
    <div v-if="$slots.default" class="ka-slot"><slot /></div>
  </div>
</template>

<style scoped>
.ka { --w1: #57534E; --w2: #A8A29E; --wl: rgba(120, 113, 108, .10);
  position: relative; overflow: hidden; display: flex; gap: 13px; align-items: flex-start;
  padding: 16px 16px 15px; border-radius: 18px; background: #fff; border: 1px solid rgba(27, 32, 36, .07);
  box-shadow: 0 1px 2px rgba(27, 32, 36, .04), 0 14px 28px -24px rgba(27, 32, 36, .45); }
/* Semburat warna kategori di pojok kanan atas. */
.ka::after { content: ''; position: absolute; right: -40px; top: -48px; width: 150px; height: 150px; border-radius: 50%;
  background: radial-gradient(circle, var(--wl) 0%, transparent 70%); pointer-events: none; }
.ka-ikon { width: 44px; height: 44px; flex: none; border-radius: 13px; display: grid; place-items: center; color: #fff;
  background: linear-gradient(135deg, var(--w1), var(--w2)); box-shadow: 0 8px 16px -10px var(--w1); }
.ka-isi { min-width: 0; flex: 1; position: relative; z-index: 1; }
/* Isi tambahan (sparkline) di baris sendiri selebar kartu — di samping
   angka ia menyempitkan kolomnya sampai satuannya patah ke baris baru. */
.ka { flex-wrap: wrap; }
.ka-slot { flex-basis: 100%; display: flex; justify-content: flex-end; margin-top: -2px; position: relative; z-index: 1; }
.ka-nilai, .ka-nilai small { white-space: nowrap; }
.ka-label { font-size: 11.5px; font-weight: 600; color: #78716C; }
.ka-nilai { font-size: 24px; font-weight: 800; line-height: 1.15; letter-spacing: -.02em; color: #1C1917; margin-top: 3px; }
.ka-nilai small { font-size: 12px; font-weight: 700; color: #A8A29E; margin-left: 5px; letter-spacing: 0; }
.ka-bawah { display: flex; flex-wrap: wrap; gap: 5px 7px; align-items: center; margin-top: 7px; }
.ka-catatan { font-size: 11px; color: #78716C; }
.ka-status, .ka-arah { display: inline-flex; align-items: center; gap: 3px; font-size: 10.5px; font-weight: 800;
  padding: 2px 8px; border-radius: 999px; white-space: nowrap; }
.st-hijau { background: #DCFCE7; color: #166534; }
.st-kuning { background: #FEF3C7; color: #92400E; }
.st-merah { background: #FEE2E2; color: #991B1B; }
.ka-arah { padding-left: 6px; }
.ka-arah.baik { background: #ECFDF5; color: #047857; }
.ka-arah.buruk { background: #FFF1F2; color: #BE123C; }
.ka-lewat { border-color: rgba(220, 38, 38, .45); box-shadow: 0 0 0 1px rgba(220, 38, 38, .18), 0 14px 28px -24px rgba(220, 38, 38, .5); }

.ka-oranye { --w1: #E25C00; --w2: #FF9A1F; --wl: rgba(245, 124, 0, .16); }
.ka-amber  { --w1: #C2410C; --w2: #F59E0B; --wl: rgba(245, 158, 11, .16); }
.ka-toska  { --w1: #0B5F57; --w2: #14B8A6; --wl: rgba(20, 184, 166, .16); }
.ka-biru   { --w1: #1D4ED8; --w2: #60A5FA; --wl: rgba(59, 130, 246, .15); }
.ka-ungu   { --w1: #5B21B6; --w2: #A78BFA; --wl: rgba(139, 92, 246, .15); }
.ka-hijau  { --w1: #15803D; --w2: #4ADE80; --wl: rgba(34, 197, 94, .15); }
.ka-merah  { --w1: #B91C1C; --w2: #F87171; --wl: rgba(239, 68, 68, .14); }
.ka-langit { --w1: #0369A1; --w2: #38BDF8; --wl: rgba(14, 165, 233, .15); }
.ka-mawar  { --w1: #BE123C; --w2: #FB7185; --wl: rgba(244, 63, 94, .14); }

:root[data-tema="gelap"] .ka { background: #182125; border-color: #222E33; box-shadow: none; }
:root[data-tema="gelap"] .ka-label, :root[data-tema="gelap"] .ka-catatan { color: #93A1A8; }
:root[data-tema="gelap"] .ka-nilai { color: #E8EDEF; }
:root[data-tema="gelap"] .ka-nilai small { color: #7C8B92; }
:root[data-tema="gelap"] .st-hijau { background: #143A2C; color: #8FE3BE; }
:root[data-tema="gelap"] .st-kuning { background: #4A3810; color: #F6D488; }
:root[data-tema="gelap"] .st-merah { background: #4E1D1D; color: #F5A9A9; }
:root[data-tema="gelap"] .ka-arah.baik { background: #143A2C; color: #8FE3BE; }
:root[data-tema="gelap"] .ka-arah.buruk { background: #4E1D1D; color: #F5A9A9; }
</style>

<style>
/* Kisi kartu angka — tidak terlingkup supaya halaman pemakainya dapat
   memakainya langsung. Lebar kolomnya ditentukan ruang yang ada, bukan
   lebar layar (alasan yang sama dengan .ubin-kisi). */
.ka-kisi { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 230px), 1fr)); }
</style>

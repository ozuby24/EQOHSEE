<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{
  units: Array<Record<string, any>>;
  areas: Record<string, string>;
  sources: Record<string, string>;
  recent: Record<string, any[]>;
  tautan: Record<string, string>;
}>();

const hariIni = new Date().toISOString().slice(0, 10);
const produksi = useForm<any>({ tanggal: hariIni, ton: '', bcm: '' });
const fuel = useForm<any>({ equipment_id: '', tanggal: hariIni, hm: '', liter: '', idle_jam: 0, jarak_km: 0, ton: 0, bcm: 0, cycle_menit: '' });
const listrik = useForm<any>({ tanggal: hariIni, area: 'workshop', sumber: 'pln', kwh: '', puncak_kw: '', jam_operasi: 24, liter_genset: 0 });
const recon = useForm<any>({ tanggal: hariIni, stok_awal_liter: '', disalurkan_liter: '', stok_akhir_liter: '', catatan: '' });

const angka = (value: unknown) => Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });
const tanggal = (value: unknown) => value ? new Date(String(value)).toLocaleDateString('id-ID') : '-';

function kirim(form: any, url: string, keep: string[]) {
  form.post(url, { preserveScroll: true, onSuccess: () => form.reset(...keep) });
}
</script>

<template>
  <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
    <div class="flex flex-wrap items-end justify-between gap-3"><div><p class="text-[10px] uppercase tracking-[.18em] text-cam-orange font-bold">Data capture</p><h3 class="text-lg font-extrabold">Input Lapangan Terpadu</h3><p class="text-[12px] text-stone-500 mt-1">Satu titik input untuk produksi, alat, listrik, dan rekonsiliasi bahan bakar.</p></div><span class="rounded-full bg-emerald-50 px-3 py-1 text-[10px] font-bold text-emerald-700">Update langsung ke monitoring</span></div>
  </section>

  <section class="grid gap-5 xl:grid-cols-2">
    <form class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 space-y-3" @submit.prevent="kirim(produksi, props.tautan.production, ['ton', 'bcm'])">
      <div><h3 class="font-bold text-[14px]">Produksi Harian</h3><p class="text-[11px] text-stone-400">Menjadi pembagi intensitas energi dan dasar produktivitas.</p></div>
      <input v-model="produksi.tanggal" required type="date" class="w-full rounded-lg border-stone-200 text-[12px]"><div class="grid grid-cols-2 gap-2"><input v-model="produksi.ton" required type="number" step="any" min="0" placeholder="Produksi batubara (ton)" class="rounded-lg border-stone-200 text-[12px]"><input v-model="produksi.bcm" required type="number" step="any" min="0" placeholder="Overburden (BCM)" class="rounded-lg border-stone-200 text-[12px]"></div><button :disabled="produksi.processing" class="eq-btn-utama w-full">{{ produksi.processing ? 'Menyimpan...' : 'Simpan produksi' }}</button><p v-if="produksi.errors.ton" class="text-[11px] text-red-600">{{ produksi.errors.ton }}</p>
    </form>

    <form class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 space-y-3" @submit.prevent="kirim(fuel, props.tautan.fuel, ['hm', 'liter', 'idle_jam', 'jarak_km', 'ton', 'bcm', 'cycle_menit'])">
      <div><h3 class="font-bold text-[14px]">Fuel & Produktivitas Alat</h3><p class="text-[11px] text-stone-400">HM, solar, idle, jarak, tonase, dan cycle time per unit per hari.</p></div>
      <div class="grid grid-cols-2 gap-2"><select v-model="fuel.equipment_id" required class="rounded-lg border-stone-200 text-[12px] col-span-2"><option value="" disabled>Pilih unit alat</option><option v-for="unit in props.units" :key="unit.id" :value="unit.id">{{ unit.kode }} · {{ unit.nama }}</option></select><input v-model="fuel.tanggal" required type="date" class="rounded-lg border-stone-200 text-[12px]"><input v-model="fuel.hm" required type="number" step="any" min="0" placeholder="HM / jam operasi" class="rounded-lg border-stone-200 text-[12px]"><input v-model="fuel.liter" required type="number" step="any" min="0" placeholder="Solar (liter)" class="rounded-lg border-stone-200 text-[12px]"><input v-model="fuel.idle_jam" required type="number" step="any" min="0" placeholder="Idle (jam)" class="rounded-lg border-stone-200 text-[12px]"><input v-model="fuel.ton" required type="number" step="any" min="0" placeholder="Tonase" class="rounded-lg border-stone-200 text-[12px]"><input v-model="fuel.bcm" required type="number" step="any" min="0" placeholder="BCM" class="rounded-lg border-stone-200 text-[12px]"><input v-model="fuel.jarak_km" required type="number" step="any" min="0" placeholder="Jarak (km)" class="rounded-lg border-stone-200 text-[12px]"><input v-model="fuel.cycle_menit" type="number" step="any" min="0" placeholder="Cycle (menit)" class="rounded-lg border-stone-200 text-[12px]"></div><button :disabled="fuel.processing" class="eq-btn-utama w-full">{{ fuel.processing ? 'Menyimpan...' : 'Simpan fuel alat' }}</button><p v-if="fuel.errors.equipment_id" class="text-[11px] text-red-600">{{ fuel.errors.equipment_id }}</p>
    </form>

    <form class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 space-y-3" @submit.prevent="kirim(listrik, props.tautan.power, ['kwh', 'puncak_kw', 'liter_genset'])">
      <div><h3 class="font-bold text-[14px]">Listrik & Genset</h3><p class="text-[11px] text-stone-400">Catat per area dan sumber supaya faktor beban serta intensitas bisa dibandingkan.</p></div>
      <div class="grid grid-cols-2 gap-2"><input v-model="listrik.tanggal" required type="date" class="rounded-lg border-stone-200 text-[12px]"><select v-model="listrik.area" class="rounded-lg border-stone-200 text-[12px]"><option v-for="(item, key) in props.areas" :key="key" :value="key">{{ item }}</option></select><select v-model="listrik.sumber" class="rounded-lg border-stone-200 text-[12px]"><option v-for="(item, key) in props.sources" :key="key" :value="key">{{ item }}</option></select><input v-model="listrik.kwh" required type="number" step="any" min="0" placeholder="kWh" class="rounded-lg border-stone-200 text-[12px]"><input v-model="listrik.puncak_kw" required type="number" step="any" min="0" placeholder="Beban puncak (kW)" class="rounded-lg border-stone-200 text-[12px]"><input v-model="listrik.jam_operasi" required type="number" step="any" min="0" max="24" placeholder="Jam operasi" class="rounded-lg border-stone-200 text-[12px]"><input v-model="listrik.liter_genset" required type="number" step="any" min="0" placeholder="Solar genset (liter)" class="rounded-lg border-stone-200 text-[12px]"></div><button :disabled="listrik.processing" class="eq-btn-utama w-full">{{ listrik.processing ? 'Menyimpan...' : 'Simpan listrik' }}</button>
    </form>

    <form class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 space-y-3" @submit.prevent="kirim(recon, props.tautan.recon, ['stok_awal_liter', 'disalurkan_liter', 'stok_akhir_liter', 'catatan'])">
      <div><h3 class="font-bold text-[14px]">Rekonsiliasi Solar</h3><p class="text-[11px] text-stone-400">Kontrol selisih antara stok dan pemakaian unit untuk menemukan data anomali.</p></div>
      <div class="grid grid-cols-2 gap-2"><input v-model="recon.tanggal" required type="date" class="rounded-lg border-stone-200 text-[12px]"><input v-model="recon.stok_awal_liter" required type="number" step="any" min="0" placeholder="Stok awal (L)" class="rounded-lg border-stone-200 text-[12px]"><input v-model="recon.disalurkan_liter" required type="number" step="any" min="0" placeholder="Disalurkan (L)" class="rounded-lg border-stone-200 text-[12px]"><input v-model="recon.stok_akhir_liter" required type="number" step="any" min="0" placeholder="Stok akhir (L)" class="rounded-lg border-stone-200 text-[12px]"><textarea v-model="recon.catatan" placeholder="Catatan selisih / hasil pemeriksaan" class="rounded-lg border-stone-200 text-[12px] col-span-2"></textarea></div><button :disabled="recon.processing" class="eq-btn-utama w-full">{{ recon.processing ? 'Menyimpan...' : 'Simpan rekonsiliasi' }}</button><p v-if="recon.errors.stok_akhir_liter" class="text-[11px] text-red-600">{{ recon.errors.stok_akhir_liter }}</p>
    </form>
  </section>

  <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-x-auto"><div class="px-5 py-4 border-b border-stone-100"><h3 class="font-bold text-[14px]">Input Terakhir</h3><p class="text-[11px] text-stone-400">Gunakan untuk memastikan data baru masuk sebelum membaca KPI.</p></div><table class="min-w-full text-left text-[12px]"><thead><tr class="border-b border-stone-100 text-stone-400"><th class="px-5 py-3">Tanggal</th><th class="px-5 py-3">Produksi</th><th class="px-5 py-3">Fuel</th><th class="px-5 py-3">Listrik</th><th class="px-5 py-3">Rekonsiliasi</th></tr></thead><tbody><tr v-for="index in 8" :key="index" class="border-b border-stone-50"><td class="px-5 py-3">{{ tanggal(props.recent.production?.[index - 1]?.tanggal || props.recent.fuel?.[index - 1]?.tanggal || props.recent.power?.[index - 1]?.tanggal) }}</td><td class="px-5 py-3">{{ props.recent.production?.[index - 1] ? `${angka(props.recent.production[index - 1].ton)} ton` : '-' }}</td><td class="px-5 py-3">{{ props.recent.fuel?.[index - 1] ? `${angka(props.recent.fuel[index - 1].liter)} L` : '-' }}</td><td class="px-5 py-3">{{ props.recent.power?.[index - 1] ? `${angka(props.recent.power[index - 1].kwh)} kWh` : '-' }}</td><td class="px-5 py-3">{{ props.recent.recon?.[index - 1] ? `${angka(props.recent.recon[index - 1].disalurkan_liter)} L` : '-' }}</td></tr></tbody></table></section>
</template>

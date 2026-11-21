<script setup lang="ts">
/*
  Input lapangan terpadu (FSD §8.1). Setiap baris yang disimpan masuk
  berstatus "diajukan" dan menunggu verifikasi supervisor; baris yang
  ditolak dibetulkan lewat formulir yang sama dan kembali "diajukan".
*/
import { useForm } from '@inertiajs/vue3';
import Lencana from './Energi/Lencana.vue';

const props = defineProps<{
  units: Array<Record<string, any>>;
  areas: Record<string, string>;
  sources: Record<string, string>;
  sites?: Array<Record<string, any>>;
  lain?: Record<string, { label: string; satuan: string }>;
  sumberData?: Record<string, string>;
  fameBawaan?: number | null;
  recent: Record<string, any[]>;
  tautan: Record<string, string>;
  boleh?: Record<string, boolean>;
}>();

const hariIni = new Date().toISOString().slice(0, 10);
const produksi = useForm<any>({ tanggal: hariIni, ton: '', bcm: '', stripping_ratio: '', jarak_angkut_km: '', sumber_data: 'nota' });
const fuel = useForm<any>({ equipment_id: '', tanggal: hariIni, hm: '', liter: '', idle_jam: 0, jarak_km: 0, ton: 0, bcm: 0, cycle_menit: '', persen_fame: '', sumber_data: 'flowmeter' });
const listrik = useForm<any>({ tanggal: hariIni, site_id: '', area: 'workshop', sumber: 'pln', kwh: '', puncak_kw: '', jam_operasi: 24, liter_genset: 0, sumber_data: 'flowmeter' });
const recon = useForm<any>({ tanggal: hariIni, stok_awal_liter: '', disalurkan_liter: '', stok_akhir_liter: '', catatan: '' });
const lain = useForm<any>({ tanggal: hariIni, site_id: '', jenis: 'bensin', jumlah: '', sumber_data: 'nota', keterangan: '' });

const angka = (value: unknown) => Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });
const tanggal = (value: unknown) => value ? new Date(String(value)).toLocaleDateString('id-ID') : '-';
const kosongJadiNull = (d: any) => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v]));

function kirim(form: any, url: string, keep: string[]) {
  form.transform(kosongJadiNull).post(url, { preserveScroll: true, onSuccess: () => form.reset(...keep) });
}

const isian = 'w-full rounded-lg border-stone-200 text-[12px]';
const label = 'text-[11px] font-semibold text-stone-500';
</script>

<template>
  <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h3 class="text-lg font-extrabold">Input Lapangan Terpadu</h3>
        <p class="text-[12px] text-stone-500 mt-1">Baris baru menunggu verifikasi supervisor.</p>
      </div>
      <span v-if="props.boleh?.input === false" class="rounded-full bg-amber-50 px-3 py-1 text-[10px] font-bold text-amber-800">Peran Anda baca-saja</span>
      <span v-else class="rounded-full bg-emerald-50 px-3 py-1 text-[10px] font-bold text-emerald-700">Masuk ke antrean verifikasi</span>
    </div>
  </section>

  <section class="grid gap-5 xl:grid-cols-2">
    <form class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 space-y-3" @submit.prevent="kirim(produksi, props.tautan.production, ['ton', 'bcm', 'stripping_ratio', 'jarak_angkut_km'])">
      <div><h3 class="font-bold text-[14px]">Produksi Harian</h3></div>
      <div class="grid grid-cols-2 gap-2">
        <label :class="label">Tanggal<input v-model="produksi.tanggal" required type="date" :class="isian"></label>
        <label :class="label">Sumber data<select v-model="produksi.sumber_data" :class="isian"><option v-for="[k, l] in Object.entries((props.sumberData ?? {}) as Record<string, any>)" :key="k" :value="k">{{ l }}</option></select></label>
        <label :class="label">Batubara (ton)<input v-model="produksi.ton" required type="number" step="any" min="0" :class="isian"></label>
        <label :class="label">Overburden (BCM)<input v-model="produksi.bcm" required type="number" step="any" min="0" :class="isian"></label>
        <label :class="label">Stripping ratio<input v-model="produksi.stripping_ratio" type="number" step="any" min="0" :class="isian"></label>
        <label :class="label">Jarak angkut rata-rata (km)<input v-model="produksi.jarak_angkut_km" type="number" step="any" min="0" :class="isian"></label>
      </div>
      <button :disabled="produksi.processing" class="eq-btn-utama w-full">{{ produksi.processing ? 'Menyimpan...' : 'Simpan produksi' }}</button>
      <p v-for="(pesan, k) in produksi.errors" :key="k" class="text-[11px] text-red-600">{{ pesan }}</p>
    </form>

    <form class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 space-y-3" @submit.prevent="kirim(fuel, props.tautan.fuel, ['hm', 'liter', 'idle_jam', 'jarak_km', 'ton', 'bcm', 'cycle_menit'])">
      <div><h3 class="font-bold text-[14px]">Solar & Produktivitas Alat</h3><p class="text-[11px] text-stone-400">HM, solar, idle, jarak, tonase, dan cycle time per unit per hari.</p></div>
      <div class="grid grid-cols-2 gap-2">
        <label :class="label" class="col-span-2">Unit alat<select v-model="fuel.equipment_id" required :class="isian"><option value="" disabled>Pilih unit alat</option><option v-for="unit in props.units" :key="unit.id" :value="unit.id">{{ unit.kode }} · {{ unit.nama }}</option></select></label>
        <label :class="label">Tanggal<input v-model="fuel.tanggal" required type="date" :class="isian"></label>
        <label :class="label">Sumber data<select v-model="fuel.sumber_data" :class="isian"><option v-for="[k, l] in Object.entries((props.sumberData ?? {}) as Record<string, any>)" :key="k" :value="k">{{ l }}</option></select></label>
        <label :class="label">HM / jam operasi<input v-model="fuel.hm" required type="number" step="any" min="0" :class="isian"></label>
        <label :class="label">Solar (liter)<input v-model="fuel.liter" required type="number" step="any" min="0" :class="isian"></label>
        <label :class="label">Idle (jam)<input v-model="fuel.idle_jam" required type="number" step="any" min="0" :class="isian"></label>
        <label :class="label">Tonase<input v-model="fuel.ton" required type="number" step="any" min="0" :class="isian"></label>
        <label :class="label">BCM<input v-model="fuel.bcm" required type="number" step="any" min="0" :class="isian"></label>
        <label :class="label">Jarak (km)<input v-model="fuel.jarak_km" required type="number" step="any" min="0" :class="isian"></label>
        <label :class="label">Cycle (menit)<input v-model="fuel.cycle_menit" type="number" step="any" min="0" :class="isian"></label>
        <label :class="label">FAME (%) — kosong = B{{ props.fameBawaan ?? '?' }} nasional<input v-model="fuel.persen_fame" type="number" step="any" min="0" max="100" :class="isian"></label>
      </div>
      <button :disabled="fuel.processing" class="eq-btn-utama w-full">{{ fuel.processing ? 'Menyimpan...' : 'Simpan solar alat' }}</button>
      <p v-for="(pesan, k) in fuel.errors" :key="k" class="text-[11px] text-red-600">{{ pesan }}</p>
    </form>

    <form class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 space-y-3" @submit.prevent="kirim(listrik, props.tautan.power, ['kwh', 'puncak_kw', 'liter_genset'])">
      <div><h3 class="font-bold text-[14px]">Listrik, Genset & PLTS</h3></div>
      <div class="grid grid-cols-2 gap-2">
        <label :class="label">Tanggal<input v-model="listrik.tanggal" required type="date" :class="isian"></label>
        <label :class="label">Site<select v-model="listrik.site_id" :class="isian"><option value="">—</option><option v-for="s in props.sites ?? []" :key="s.id" :value="s.id">{{ s.nama }}</option></select></label>
        <label :class="label">Area<select v-model="listrik.area" :class="isian"><option v-for="[key, item] in Object.entries((props.areas ?? {}) as Record<string, any>)" :key="key" :value="key">{{ item }}</option></select></label>
        <label :class="label">Sumber<select v-model="listrik.sumber" :class="isian"><option v-for="[key, item] in Object.entries((props.sources ?? {}) as Record<string, any>)" :key="key" :value="key">{{ item }}</option></select></label>
        <label :class="label">kWh<input v-model="listrik.kwh" required type="number" step="any" min="0" :class="isian"></label>
        <label :class="label">Beban puncak (kW)<input v-model="listrik.puncak_kw" required type="number" step="any" min="0" :class="isian"></label>
        <label :class="label">Jam operasi<input v-model="listrik.jam_operasi" required type="number" step="any" min="0" max="24" :class="isian"></label>
        <label :class="label">Solar genset (liter)<input v-model="listrik.liter_genset" required type="number" step="any" min="0" :class="isian"></label>
        <label :class="label" class="col-span-2">Sumber data<select v-model="listrik.sumber_data" :class="isian"><option v-for="[k, l] in Object.entries((props.sumberData ?? {}) as Record<string, any>)" :key="k" :value="k">{{ l }}</option></select></label>
      </div>
      <button :disabled="listrik.processing" class="eq-btn-utama w-full">{{ listrik.processing ? 'Menyimpan...' : 'Simpan listrik' }}</button>
      <p v-for="(pesan, k) in listrik.errors" :key="k" class="text-[11px] text-red-600">{{ pesan }}</p>
    </form>

    <form class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 space-y-3" @submit.prevent="kirim(lain, props.tautan.other, ['jumlah', 'keterangan'])">
      <div><h3 class="font-bold text-[14px]">Gas & Energi Lain</h3><p class="text-[11px] text-stone-400">Bensin, LPG, gas, bahan peledak, refrigeran, SF₆.</p></div>
      <div class="grid grid-cols-2 gap-2">
        <label :class="label">Tanggal<input v-model="lain.tanggal" required type="date" :class="isian"></label>
        <label :class="label">Site<select v-model="lain.site_id" :class="isian"><option value="">—</option><option v-for="s in props.sites ?? []" :key="s.id" :value="s.id">{{ s.nama }}</option></select></label>
        <label :class="label">Jenis<select v-model="lain.jenis" required :class="isian"><option v-for="[kode, j] in Object.entries((props.lain ?? {}) as Record<string, any>)" :key="kode" :value="kode">{{ j.label }}</option></select></label>
        <label :class="label">Jumlah ({{ props.lain?.[lain.jenis]?.satuan ?? '' }})<input v-model="lain.jumlah" required type="number" step="any" min="0" :class="isian"></label>
        <label :class="label">Sumber data<select v-model="lain.sumber_data" required :class="isian"><option v-for="[k, l] in Object.entries((props.sumberData ?? {}) as Record<string, any>)" :key="k" :value="k">{{ l }}</option></select></label>
        <label :class="label">Keterangan<input v-model="lain.keterangan" maxlength="255" :class="isian"></label>
      </div>
      <button :disabled="lain.processing" class="eq-btn-utama w-full">{{ lain.processing ? 'Menyimpan...' : 'Simpan energi lain' }}</button>
      <p v-for="(pesan, k) in lain.errors" :key="k" class="text-[11px] text-red-600">{{ pesan }}</p>
    </form>

    <form class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 space-y-3 xl:col-span-2" @submit.prevent="kirim(recon, props.tautan.recon, ['stok_awal_liter', 'disalurkan_liter', 'stok_akhir_liter', 'catatan'])">
      <div><h3 class="font-bold text-[14px]">Rekonsiliasi Solar</h3></div>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
        <label :class="label">Tanggal<input v-model="recon.tanggal" required type="date" :class="isian"></label>
        <label :class="label">Stok awal (L)<input v-model="recon.stok_awal_liter" required type="number" step="any" min="0" :class="isian"></label>
        <label :class="label">Disalurkan (L)<input v-model="recon.disalurkan_liter" required type="number" step="any" min="0" :class="isian"></label>
        <label :class="label">Stok akhir (L)<input v-model="recon.stok_akhir_liter" required type="number" step="any" min="0" :class="isian"></label>
        <label :class="label" class="col-span-2 md:col-span-4">Catatan selisih / hasil pemeriksaan<textarea v-model="recon.catatan" :class="isian"></textarea></label>
      </div>
      <button :disabled="recon.processing" class="eq-btn-utama w-full">{{ recon.processing ? 'Menyimpan...' : 'Simpan rekonsiliasi' }}</button>
      <p v-for="(pesan, k) in recon.errors" :key="k" class="text-[11px] text-red-600">{{ pesan }}</p>
    </form>
  </section>

  <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-x-auto">
    <div class="px-5 py-4 border-b border-stone-100"><h3 class="font-bold text-[14px]">Input Terakhir</h3><p class="text-[11px] text-stone-400">Status alur tiap baris — yang ditolak menyebut alasannya.</p></div>
    <table class="min-w-full text-left text-[12px]">
      <thead><tr class="border-b border-stone-100 text-stone-400"><th class="px-5 py-3">Jenis</th><th class="px-5 py-3">Tanggal</th><th class="px-5 py-3">Isi</th><th class="px-5 py-3">Status</th></tr></thead>
      <tbody>
        <tr v-for="b in props.recent.fuel ?? []" :key="'f' + b.id" class="border-b border-stone-50"><td class="px-5 py-2">Solar alat</td><td class="px-5 py-2">{{ tanggal(b.tanggal) }}</td><td class="px-5 py-2">{{ b.equipment?.kode }} · {{ angka(b.liter) }} L</td><td class="px-5 py-2"><Lencana :status="b.status" /> <span v-if="b.alasan_tolak" class="text-[10.5px] text-red-600">{{ b.alasan_tolak }}</span></td></tr>
        <tr v-for="b in props.recent.power ?? []" :key="'p' + b.id" class="border-b border-stone-50"><td class="px-5 py-2">Listrik</td><td class="px-5 py-2">{{ tanggal(b.tanggal) }}</td><td class="px-5 py-2">{{ props.areas[b.area] ?? b.area }} · {{ angka(b.kwh) }} kWh</td><td class="px-5 py-2"><Lencana :status="b.status" /></td></tr>
        <tr v-for="b in props.recent.other ?? []" :key="'o' + b.id" class="border-b border-stone-50"><td class="px-5 py-2">Energi lain</td><td class="px-5 py-2">{{ tanggal(b.tanggal) }}</td><td class="px-5 py-2">{{ props.lain?.[b.jenis]?.label ?? b.jenis }} · {{ angka(b.jumlah) }} {{ b.satuan }}</td><td class="px-5 py-2"><Lencana :status="b.status" /></td></tr>
        <tr v-for="b in props.recent.production ?? []" :key="'d' + b.id" class="border-b border-stone-50"><td class="px-5 py-2">Produksi</td><td class="px-5 py-2">{{ tanggal(b.tanggal) }}</td><td class="px-5 py-2">{{ angka(b.ton) }} ton · {{ angka(b.bcm) }} BCM</td><td class="px-5 py-2"><Lencana :status="b.status" /></td></tr>
        <tr v-for="b in props.recent.recon ?? []" :key="'r' + b.id" class="border-b border-stone-50"><td class="px-5 py-2">Rekonsiliasi</td><td class="px-5 py-2">{{ tanggal(b.tanggal) }}</td><td class="px-5 py-2">Disalurkan {{ angka(b.disalurkan_liter) }} L</td><td class="px-5 py-2"><Lencana :status="b.status" /></td></tr>
      </tbody>
    </table>
  </section>
</template>

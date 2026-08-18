<script setup lang="ts">
import { computed, reactive } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import EngineeringMonitor from '../../Components/EngineeringMonitor.vue';
import MiningTools from '../../Components/MiningTools.vue';

/*
  Prop halaman diambil lewat usePage(), bukan defineProps.

  Bentuk `defineProps<{ mode: string; [key: string]: any }>()` yang
  dipakai sebelumnya terbaca seolah menerima apa saja. Yang sebenarnya
  terjadi: penyusun Vue tidak dapat menurunkan nama prop dari sebuah
  index signature, sehingga HANYA `mode` yang benar-benar terdaftar
  sebagai prop. Seluruh sisanya jatuh ke $attrs — dan karena template
  ini berakar jamak (<Head> beserta pembungkusnya), atribut itu bahkan
  tidak tersangkut di mana pun.

  Akibatnya halaman merender kosong seluruhnya: tidak ada galat, tidak
  ada peringatan pada build produksi, hanya data yang dikirim server dan
  tidak pernah sampai ke tampilan. Uji sisi server tetap hijau, sebab
  yang salah bukan propnya melainkan penerimaannya.

  usePage() mengambil prop halaman apa adanya — termasuk yang dibagikan
  middleware — sehingga tidak ada daftar nama yang harus dirawat sejajar
  dengan controller-nya, dan tidak ada nama yang dapat hilang diam-diam.
*/
const props = usePage<any>().props as any;
const titlesMonitor = 'Engineering Control Tower';
const titles: Record<string, string> = { index: 'Mining Engineering Hub', energy: 'Energy Dashboard', fleet: 'Fleet & Productivity', equipment: 'Mining Equipment', maintenance: 'Maintenance', hse: 'HSE & SMKP', kpi: 'Engineering KPI', tools: 'Engineering Tools', regulations: 'Regulations & Standards' };
const tabs = [['index', 'Dashboard', '/mining-engineering-hub'], ['energy', 'Energi', '/mining-engineering-hub/energy'], ['fleet', 'Armada', '/mining-engineering-hub/fleet'], ['equipment', 'Equipment', '/mining-engineering-hub/equipment'], ['maintenance', 'Maintenance', '/mining-engineering-hub/maintenance'], ['hse', 'HSE', '/mining-engineering-hub/hse'], ['kpi', 'KPI', '/mining-engineering-hub/kpi'], ['tools', 'Tools', '/mining-engineering-hub/tools'], ['regulations', 'Regulasi', '/mining-engineering-hub/regulations']];
const angka = (v: unknown) => typeof v === 'number' ? v.toLocaleString('id-ID', { maximumFractionDigits: 2 }) : (v ?? '—');
const tanggal = (v: unknown) => v ? new Date(String(v)).toLocaleDateString('id-ID') : '—';
titles.monitor = titlesMonitor;
const rows = computed(() => props.unit ?? props.kerja ?? props.daftar ?? []);
tabs.splice(1, 0, ['monitor', titlesMonitor, '/mining-engineering-hub/monitor']);
const filterForm = reactive({ status: props.status ?? 'semua', prioritas: props.prioritas ?? 'semua', kategori: props.kategori ?? 'Semua' });
function filter(key: string, value: string) { router.get(window.location.pathname, { [key]: value }, { preserveState: true, preserveScroll: true }); }
function value(row: any, key: string) { return row[key] ?? '—'; }

/**
 * Nama manusia untuk tiap kunci indikator.
 *
 * Sebelum ini kuncinya ditampilkan apa adanya lewat
 * `String(key).replaceAll('_',' ')`. Pada kunci ber-garis-bawah itu
 * menghasilkan sesuatu yang setengah terbaca — "pm compliance",
 * "biaya rp" — dan pada kunci camelCase ia tidak berpengaruh sama
 * sekali: halaman menampilkan `tonHari`, `fuelRate`, `porsiPreventif`,
 * dan `terbarukanPersen` mentah-mentah kepada general manager.
 *
 * Satuannya ikut ditulis di sini. Angka tanpa satuan pada dasbor
 * teknik adalah angka yang harus ditebak — 168,5 itu jam atau hari,
 * 38,73 itu liter per jam atau liter per ton.
 */
const LABEL: Record<string, string> = {
  /* Produksi */
  hari: 'Hari operasi',
  ton: 'Produksi (ton)',
  bcm: 'Overburden (BCM)',
  target: 'Target (ton)',
  tonHari: 'Rata-rata ton/hari',
  capaian: 'Capaian target (%)',
  sr: 'Stripping ratio',

  /* Armada */
  kerja: 'Jam kerja',
  standby: 'Jam standby',
  rusak: 'Jam rusak',
  liter: 'Bahan bakar (liter)',
  terjadwal: 'Jam terjadwal',
  jumlah: 'Jumlah alat',
  beroperasi: 'Alat beroperasi',
  pa: 'Physical availability (%)',
  ma: 'Mechanical availability (%)',
  ua: 'Use of availability (%)',
  utilisasi: 'Utilisasi (%)',
  fuelRate: 'Konsumsi bahan bakar (liter/jam)',

  /* Energi */
  gj: 'Energi (GJ)',
  kwh: 'Listrik (kWh)',
  tco2e: 'Emisi (ton CO₂e)',
  rupiah: 'Biaya energi (Rp)',
  gjHari: 'Energi per hari (GJ)',
  literHari: 'Bahan bakar per hari (liter)',
  kwhHari: 'Listrik per hari (kWh)',
  intensitas: 'Intensitas energi (GJ/ton)',
  fuelRatio: 'Rasio bahan bakar (liter/ton)',
  baseline: 'Baseline intensitas',
  penurunan: 'Penurunan terhadap baseline (%)',
  terbarukanPersen: 'Porsi energi terbarukan (%)',

  /* Maintenance */
  pm_compliance: 'Kepatuhan PM (%)',
  mtbf_jam: 'MTBF (jam)',
  mttr_jam: 'MTTR (jam)',
  breakdown: 'Jumlah breakdown',
  biaya_rp: 'Biaya pemeliharaan (Rp)',
  totalJam: 'Total jam henti',
  porsiPreventif: 'Porsi preventif (%)',
  terbuka: 'Perintah kerja terbuka',
  kritis: 'Perintah kerja kritis',
};

/**
 * Label untuk sebuah kunci.
 *
 * Kunci yang belum terdaftar TIDAK ditampilkan mentah: camelCase-nya
 * dipecah dan huruf pertamanya dibesarkan, sehingga indikator baru yang
 * lupa didaftarkan tetap terbaca sebagai "Ton Hari", bukan "tonHari".
 * Yang mentah selalu berakhir tayang di layar orang.
 */
const labelKunci = (k: unknown) => {
  const kunci = String(k);

  return LABEL[kunci] ?? kunci
    .replace(/_/g, ' ')
    .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
    .replace(/^./, (c) => c.toUpperCase());
};
</script>

<template>
  <Head :title="titles[props.mode] ?? 'Engineering'" />
  <div class="max-w-[1400px] mx-auto space-y-5"><section><h2 class="text-xl font-bold text-cam-ink">{{ titles[props.mode] ?? 'Engineering' }}</h2><p class="text-[12.5px] text-stone-500 mt-1">Indikator operasi, armada, pemeliharaan, energi, dan keselamatan pertambangan.</p></section><nav class="flex gap-1 overflow-x-auto rounded-xl bg-stone-100 p-1 text-[11px]"><Link v-for="tab in tabs" :key="tab[0]" :href="tab[2]" class="whitespace-nowrap rounded-lg px-3 py-2 text-stone-500 hover:bg-white" :class="props.mode === tab[0] ? 'bg-white font-bold text-cam-ink shadow-sm' : ''">{{ tab[1] }}</Link></nav>
    <section v-if="props.mode === 'index'" class="space-y-5"><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><article v-for="group in [{ key: 'produksi', data: props.p }, { key: 'armada', data: props.a }, { key: 'energi', data: props.e }, { key: 'maintenance', data: props.m }]" :key="group.key" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5"><p class="text-[10px] uppercase font-bold text-stone-400">{{ labelKunci(group.key) }}</p><div v-for="(v, key) in group.data" :key="String(key)" v-show="typeof v !== 'object'" class="flex justify-between gap-2 mt-2 text-[12px]"><span class="text-stone-500">{{ labelKunci(key) }}</span><b>{{ angka(v) }}</b></div></article></div><div class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-x-auto"><h3 class="px-5 py-4 font-bold text-[14px]">Produksi harian</h3><table class="min-w-full text-left text-[12px]"><thead><tr class="border-y border-stone-100 text-stone-400"><th class="px-5 py-3">Tanggal</th><th class="px-5 py-3">Produksi ton</th><th class="px-5 py-3">Target</th><th class="px-5 py-3">BCM</th></tr></thead><tbody><tr v-for="row in props.p?.harian ?? []" :key="row.tgl" class="border-b border-stone-50"><td class="px-5 py-3">{{ tanggal(row.tgl) }}</td><td class="px-5 py-3">{{ angka(row.ton) }}</td><td class="px-5 py-3">{{ angka(row.target) }}</td><td class="px-5 py-3">{{ angka(row.bcm) }}</td></tr></tbody></table></div></section>
    <section v-if="props.mode === 'energy'" class="space-y-5"><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><article v-for="(v, key) in props.e" :key="String(key)" v-show="typeof v !== 'object'" class="rounded-2xl bg-white border border-stone-100 shadow-card p-4"><p class="text-[10px] uppercase font-bold text-stone-400">{{ labelKunci(key) }}</p><b class="text-xl">{{ angka(v) }}</b></article></div><div class="grid gap-3 md:grid-cols-2"><article v-for="item in props.program ?? []" :key="item.judul" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5"><div class="flex justify-between gap-3"><h3 class="font-bold text-[13px]">{{ item.judul }}</h3><span class="text-[10px] rounded-full bg-stone-100 px-2 py-1">{{ item.status }}</span></div><div class="grid grid-cols-3 gap-3 mt-4 text-[12px]"><span>GJ<br><b>{{ angka(item.gj) }}</b></span><span>CO₂e<br><b>{{ angka(item.tco2e) }}</b></span><span>Nilai<br><b>Rp {{ angka(item.rupiah) }}</b></span></div></article></div></section>
    <section v-if="['fleet','equipment'].includes(props.mode)" class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-x-auto"><div class="px-5 py-4 border-b border-stone-100 flex justify-between"><h3 class="font-bold text-[14px]">{{ rows.length }} unit armada</h3><select v-if="props.mode === 'fleet'" v-model="filterForm.status" class="rounded-lg border-stone-200 text-[12px]" @change="filter('status', filterForm.status)" aria-label="Status"><option>semua</option><option>Operating</option><option>Standby</option><option>Breakdown</option><option>Maintenance</option></select></div><table class="min-w-full text-left text-[12px]"><thead><tr class="text-stone-400 border-b border-stone-100"><th class="px-5 py-3">Kode</th><th class="px-5 py-3">Tipe / kelas</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">PA</th><th class="px-5 py-3">MA</th><th class="px-5 py-3">UA</th><th class="px-5 py-3">Fuel rate</th><th class="px-5 py-3">Konsumsi</th></tr></thead><tbody><tr v-for="row in rows" :key="row.kode" class="border-b border-stone-50"><td class="px-5 py-3 font-semibold">{{ row.kode }}</td><td class="px-5 py-3">{{ row.tipe }}<small class="block text-stone-400">{{ row.kelas }}</small></td><td class="px-5 py-3">{{ row.status }}</td><td class="px-5 py-3">{{ angka(row.pa) }}%</td><td class="px-5 py-3">{{ angka(row.ma) }}%</td><td class="px-5 py-3">{{ angka(row.ua) }}%</td><td class="px-5 py-3">{{ angka(row.fuelRate) }} L/jam</td><td class="px-5 py-3"><span :style="{ color: row.statusBoros?.warna }">{{ row.statusBoros?.label ?? '—' }}</span></td></tr></tbody></table><div v-if="!rows.length" class="p-8 text-center text-stone-500">Belum ada data armada.</div></section>
    <section v-if="props.mode === 'maintenance'" class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-x-auto"><div class="px-5 py-4 border-b border-stone-100 flex justify-between"><h3 class="font-bold text-[14px]">Pekerjaan pemeliharaan</h3><select v-model="filterForm.prioritas" class="rounded-lg border-stone-200 text-[12px]" @change="filter('prioritas', filterForm.prioritas)" aria-label="Prioritas"><option>semua</option><option>Critical</option><option>High</option><option>Medium</option><option>Low</option></select></div><table class="min-w-full text-left text-[12px]"><thead><tr class="text-stone-400 border-b border-stone-100"><th class="px-5 py-3">Unit</th><th class="px-5 py-3">Masalah</th><th class="px-5 py-3">Prioritas</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">PIC</th><th class="px-5 py-3">Tanggal</th></tr></thead><tbody><tr v-for="row in rows" :key="row.unit + row.masalah" class="border-b border-stone-50"><td class="px-5 py-3 font-semibold">{{ row.unit }}</td><td class="px-5 py-3">{{ row.masalah }}</td><td class="px-5 py-3">{{ row.prioritas }}</td><td class="px-5 py-3">{{ row.status }}</td><td class="px-5 py-3">{{ row.pic }}</td><td class="px-5 py-3">{{ tanggal(row.tanggal) }}</td></tr></tbody></table></section>
    <section v-if="props.mode === 'hse'" class="space-y-5"><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><article v-for="(v, key) in props.h" :key="String(key)" v-show="typeof v !== 'object'" class="rounded-2xl bg-white border border-stone-100 shadow-card p-4"><p class="text-[10px] uppercase font-bold text-stone-400">{{ labelKunci(key) }}</p><b class="text-xl">{{ angka(v) }}</b></article></div><div class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-x-auto"><table class="min-w-full text-left text-[12px]"><thead><tr class="text-stone-400 border-b border-stone-100"><th class="px-5 py-3">Elemen</th><th class="px-5 py-3">Bobot</th><th class="px-5 py-3">Capaian</th></tr></thead><tbody><tr v-for="item in props.smkp ?? []" :key="item.no" class="border-b border-stone-50"><td class="px-5 py-3">{{ item.no }}. {{ item.elemen }}</td><td class="px-5 py-3">{{ item.bobot }}%</td><td class="px-5 py-3">{{ item.capaian }}%</td></tr></tbody></table></div></section>
    <section v-if="props.mode === 'kpi'" class="grid gap-3 md:grid-cols-3"><article v-for="group in [props.a, props.p, props.e]" :key="JSON.stringify(group)" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5"><div v-for="(v, key) in group" :key="String(key)" v-show="typeof v !== 'object'" class="flex justify-between text-[12px] py-1"><span class="text-stone-500">{{ labelKunci(key) }}</span><b>{{ angka(v) }}</b></div></article><article class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 md:col-span-3"><h3 class="font-bold text-[14px]">Rumus indikator</h3><ul class="mt-3 grid gap-2 sm:grid-cols-3 text-[12px] text-stone-600"><li v-for="item in props.rumus ?? []" :key="item" class="rounded-lg bg-stone-50 px-3 py-2">{{ item }}</li></ul></article></section>
    <section v-if="props.mode === 'tools'" class="grid gap-4 md:grid-cols-2"><article class="rounded-2xl bg-white border border-stone-100 shadow-card p-5"><h3 class="font-bold">Kalkulator fuel rate</h3><p class="text-[12px] text-stone-500 mt-2">Fuel rate = liter bahan bakar ÷ jam kerja.</p><div class="grid grid-cols-2 gap-2 mt-4"><input v-model="filterForm.status" type="number" placeholder="Liter" class="rounded-lg border-stone-200 text-[12px]"><input v-model="filterForm.prioritas" type="number" placeholder="Jam kerja" class="rounded-lg border-stone-200 text-[12px]"></div><b class="block mt-3">Hasil: {{ angka(Number(filterForm.status) / Math.max(1, Number(filterForm.prioritas))) }} L/jam</b></article><article class="rounded-2xl bg-white border border-stone-100 shadow-card p-5"><h3 class="font-bold">Acuan K3</h3><p v-for="item in props.catatan ?? []" :key="item" class="text-[12px] text-stone-600 mt-2">{{ item }}</p></article></section>
    <section v-if="props.mode === 'regulations'" class="space-y-3"><div class="flex justify-end"><select v-model="filterForm.kategori" class="rounded-lg border-stone-200 text-[12px]" @change="filter('kategori', filterForm.kategori)" aria-label="Kategori"><option v-for="item in props.semuaKategori ?? ['Semua']" :key="item">{{ item }}</option></select></div><article v-for="item in props.daftar ?? []" :key="item.judul" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5"><div class="flex flex-wrap justify-between gap-2"><h3 class="font-bold text-[14px]">{{ item.judul }}</h3><span class="text-[11px] text-stone-400">{{ item.kategori }} · {{ item.tahun }}</span></div><p class="text-[12px] text-stone-500 mt-2">{{ item.ket }}</p><a :href="item.sumber" target="_blank" rel="noopener" class="inline-block mt-3 text-[11px] text-cam-lime-deep">Buka sumber resmi ↗</a></article></section>
    <EngineeringMonitor v-if="props.mode === 'monitor'" :monitor="props.monitor ?? {}" :alerts="props.alerts ?? []" :unit="props.unit ?? []" :tren-monitor="props.trenMonitor ?? []" :baseline="props.baseline" :dari="props.dari" :sampai="props.sampai" :tautan="props.tautan ?? {}" />
    <MiningTools v-if="props.mode === 'tools'" />
  </div>
</template>

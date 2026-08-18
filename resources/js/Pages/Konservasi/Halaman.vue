<script setup lang="ts">
import { computed, reactive } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps<{
  judul: string;
  subjudul: string;
  mode: 'dashboard' | 'data' | 'laporan';
  tahun: number;
  tahunOpsi: number[];
  ringkas: Record<string, number>;
  perKomoditas: Array<Record<string, any>>;
  records: Array<Record<string, any>>;
  actions: Array<Record<string, any>>;
  alerts: Array<Record<string, any>>;
  companies: Array<{ id: number; name: string }>;
  opsi: Record<string, string[]>;
  tautan: Record<string, string>;
}>();

const halaman = usePage<any>();
const isAdmin = computed(() => Boolean(halaman.props.pengguna?.admin));

const record = useForm<any>({
  company_id: '',
  periode: `${props.tahun}-01-01`,
  lokasi: '',
  komoditas: 'Batubara',
  satuan: 'ton',
  target_produksi: 0,
  produksi_aktual: 0,
  material_digali: 0,
  recovery_percent: 0,
  kehilangan_material: 0,
  dilusi: 0,
  stok_akhir: 0,
  mineral_ikutan: '',
  // `status` tidak ada di sini: ia hanya berpindah lewat alur tinjauan.
  catatan: '',
});

const action = useForm<any>({
  company_id: '',
  record_id: '',
  judul: '',
  kategori: 'recovery',
  prioritas: 'sedang',
  status: 'rencana',
  penanggung_jawab: '',
  target_selesai: '',
  uraian: '',
});

const tahun = computed({
  get: () => props.tahun,
  set: (value: number | string) => router.get(props.mode === 'dashboard' ? props.tautan.dashboard : props.mode === 'data' ? props.tautan.data : props.tautan.laporan, { tahun: value }, { preserveState: true, replace: true }),
});

const angka = (value: unknown, digits = 0) => new Intl.NumberFormat('id-ID', {
  maximumFractionDigits: digits,
  minimumFractionDigits: digits,
}).format(Number(value || 0));

const persen = (value: unknown) => `${angka(value, 1)}%`;

const tanggal = (value: unknown) => value
  ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(String(value)))
  : '-';

const label = (value: string) => value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

function simpanRecord() {
  record.post(props.tautan.recordSimpan, {
    preserveScroll: true,
    onSuccess: () => record.reset('lokasi', 'target_produksi', 'produksi_aktual', 'material_digali', 'recovery_percent', 'kehilangan_material', 'dilusi', 'stok_akhir', 'mineral_ikutan', 'catatan'),
  });
}

function simpanAction() {
  action.post(props.tautan.actionSimpan, {
    preserveScroll: true,
    onSuccess: () => action.reset('record_id', 'judul', 'penanggung_jawab', 'target_selesai', 'uraian'),
  });
}

/** Menyisipkan id ke tautan bertanda __ID__ yang dikirim controller. */
const untuk = (pola: string | undefined, id: number | string) => String(pola || '').replace('__ID__', String(id));

function ubahStatus(item: Record<string, any>, status: string) {
  router.put(untuk(props.tautan.actionUbah, item.id), { status }, { preserveScroll: true });
}

/* ---------- alur tinjauan ---------- */

const sibuk = reactive<Record<number, boolean>>({});

function ajukan(item: Record<string, any>) {
  sibuk[item.id] = true;
  router.post(untuk(props.tautan.recordAjukan, item.id), {}, {
    preserveScroll: true, onFinish: () => { sibuk[item.id] = false; },
  });
}

function setujui(item: Record<string, any>) {
  if (!window.confirm(`Setujui data ${item.komoditas} periode ${item.periodeLabel}? Setelah disetujui, data tidak dapat diubah lagi.`)) return;
  sibuk[item.id] = true;
  router.post(untuk(props.tautan.recordSetujui, item.id), {}, {
    preserveScroll: true, onFinish: () => { sibuk[item.id] = false; },
  });
}

function tolak(item: Record<string, any>) {
  const alasan = window.prompt(`Alasan penolakan data ${item.komoditas} periode ${item.periodeLabel}:`);
  if (alasan === null) return;
  sibuk[item.id] = true;
  router.post(untuk(props.tautan.recordTolak, item.id), { alasan_tolak: alasan }, {
    preserveScroll: true, onFinish: () => { sibuk[item.id] = false; },
  });
}

const warnaStatus: Record<string, string> = {
  draf: 'bg-stone-100 text-stone-600',
  diajukan: 'bg-amber-100 text-amber-700',
  disetujui: 'bg-emerald-100 text-emerald-700',
  ditolak: 'bg-red-100 text-red-700',
};

function hapusRecord(item: Record<string, any>) {
  if (window.confirm(`Hapus data ${item.komoditas} periode ${item.periodeLabel}?`)) {
    router.delete(untuk(props.tautan.recordHapus, item.id), { preserveScroll: true });
  }
}

function hapusAction(item: Record<string, any>) {
  if (window.confirm(`Hapus tindak lanjut “${item.judul}”?`)) {
    router.delete(untuk(props.tautan.actionHapus, item.id), { preserveScroll: true });
  }
}

function cetak() {
  window.print();
}
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1400px] mx-auto space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-cam-orange">Minerba · Environment</p>
        <h2 class="text-2xl font-extrabold tracking-tight text-stone-800">{{ props.judul }}</h2>
        <p class="text-[12px] text-stone-500 mt-1">{{ props.subjudul }}</p>
      </div>
      <label class="text-[11px] font-semibold text-stone-500">Periode data
        <select v-model="tahun" class="ml-2 rounded-lg border-stone-200 text-[12px] font-semibold">
          <option v-for="item in props.tahunOpsi" :key="item" :value="item">{{ item }}</option>
        </select>
      </label>
    </div>

    <nav class="flex flex-wrap gap-2">
      <Link :href="props.tautan.dashboard" class="rounded-full px-4 py-2 text-[11px] font-bold" :class="props.mode === 'dashboard' ? 'bg-cam-ink text-white' : 'bg-white text-stone-500 border border-stone-200'">Dashboard</Link>
      <Link :href="props.tautan.data" class="rounded-full px-4 py-2 text-[11px] font-bold" :class="props.mode === 'data' ? 'bg-cam-ink text-white' : 'bg-white text-stone-500 border border-stone-200'">Data Konservasi</Link>
      <Link :href="props.tautan.laporan" class="rounded-full px-4 py-2 text-[11px] font-bold" :class="props.mode === 'laporan' ? 'bg-cam-ink text-white' : 'bg-white text-stone-500 border border-stone-200'">Laporan</Link>
      <a :href="props.tautan.cetak" class="ml-auto rounded-full px-4 py-2 text-[11px] font-bold bg-white text-cam-orange border border-cam-orange/40">Cetak laporan</a>
    </nav>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
      <article v-for="card in [
        { label: 'Produksi Aktual', value: angka(props.ringkas.produksi_aktual), suffix: 'ton', color: 'text-cam-ink' },
        { label: 'Capaian Target', value: persen(props.ringkas.capaian_target), suffix: '', color: 'text-cam-orange' },
        { label: 'Recovery', value: persen(props.ringkas.recovery), suffix: '', color: 'text-emerald-600' },
        { label: 'Kehilangan Material', value: angka(props.ringkas.kehilangan_material), suffix: 'ton', color: 'text-red-600' },
        { label: 'Tindak Lanjut Terbuka', value: angka(props.ringkas.action_terbuka), suffix: 'item', color: 'text-violet-600' },
      ]" :key="card.label" class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
        <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">{{ card.label }}</p>
        <p class="mt-2 text-xl font-extrabold" :class="card.color">{{ card.value }} <small class="text-[10px] font-semibold text-stone-400">{{ card.suffix }}</small></p>
      </article>
    </section>

    <!--
      Peringatan diletakkan tepat di bawah kartu KPI dan di atas seluruh
      rincian. Angka konservasi menurun perlahan dan jarang menimbulkan
      keluhan pada harinya; yang terlihat hanya cadangan yang habis lebih
      cepat daripada rencana, beberapa tahun kemudian.
    -->
    <section v-if="(props.alerts || []).length" class="grid gap-3 md:grid-cols-2">
      <div v-for="item in props.alerts" :key="item.kode" class="rounded-2xl border p-4"
           :class="item.level === 'tinggi' ? 'border-red-100 bg-red-50' : 'border-amber-100 bg-amber-50'">
        <b class="text-[12px]" :class="item.level === 'tinggi' ? 'text-red-700' : 'text-amber-700'">{{ item.judul }}</b>
        <p class="text-[11px] text-stone-600 mt-1">{{ item.ket }}</p>
        <p v-if="item.saran" class="text-[11px] text-stone-700 mt-2 pt-2 border-t border-black/5">
          <span class="font-bold">Tindakan: </span>{{ item.saran }}
        </p>
      </div>
    </section>

    <template v-if="props.mode !== 'laporan'">
      <section class="grid gap-5 xl:grid-cols-[1.25fr_.75fr]">
        <div class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <div class="px-5 py-4 border-b border-stone-100 flex items-center justify-between">
            <div><h3 class="font-bold text-[14px]">Kinerja per Komoditas</h3><p class="text-[11px] text-stone-400">Perbandingan target, aktual, dan recovery tahun {{ props.tahun }}</p></div>
            <span class="text-[11px] text-stone-400">{{ props.ringkas.jumlah_record }} record</span>
          </div>
          <div class="overflow-x-auto"><table class="min-w-full text-left text-[12px]"><thead><tr class="border-b border-stone-100 text-stone-400"><th class="px-5 py-3">Komoditas</th><th class="px-5 py-3">Target</th><th class="px-5 py-3">Aktual</th><th class="px-5 py-3">Capaian</th><th class="px-5 py-3">Recovery</th><th class="px-5 py-3">Kehilangan</th></tr></thead><tbody>
            <tr v-for="item in props.perKomoditas" :key="item.komoditas" class="border-b border-stone-50"><td class="px-5 py-3 font-semibold">{{ item.komoditas }}</td><td class="px-5 py-3">{{ angka(item.target) }}</td><td class="px-5 py-3">{{ angka(item.aktual) }}</td><td class="px-5 py-3"><span class="font-semibold">{{ persen(item.capaian) }}</span><div class="mt-1 h-1.5 w-24 rounded-full bg-stone-100"><div class="h-1.5 rounded-full bg-cam-orange" :style="{ width: `${Math.min(100, item.capaian || 0)}%` }"></div></div></td><td class="px-5 py-3 text-emerald-700 font-semibold">{{ persen(item.recovery) }}</td><td class="px-5 py-3 text-red-600">{{ angka(item.kehilangan) }}</td></tr>
            <tr v-if="!props.perKomoditas.length"><td colspan="6" class="px-5 py-10 text-center text-stone-400">Belum ada data pada tahun ini.</td></tr>
          </tbody></table></div>
        </div>

        <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
          <h3 class="font-bold text-[14px]">Indikator Konservasi</h3>
          <div class="mt-4 space-y-4 text-[12px]">
            <div><div class="flex justify-between mb-1"><span class="text-stone-500">Material termanfaatkan</span><b>{{ persen(props.ringkas.material_digali ? (props.ringkas.produksi_aktual / props.ringkas.material_digali) * 100 : 0) }}</b></div><div class="h-2 rounded-full bg-stone-100"><div class="h-2 rounded-full bg-emerald-500" :style="{ width: `${Math.min(100, props.ringkas.recovery || 0)}%` }"></div></div></div>
            <div><div class="flex justify-between mb-1"><span class="text-stone-500">Dilusi terhadap material</span><b>{{ persen(props.ringkas.material_digali ? (props.ringkas.dilusi / props.ringkas.material_digali) * 100 : 0) }}</b></div><div class="h-2 rounded-full bg-stone-100"><div class="h-2 rounded-full bg-amber-400" :style="{ width: `${Math.min(100, props.ringkas.material_digali ? (props.ringkas.dilusi / props.ringkas.material_digali) * 100 : 0)}%` }"></div></div></div>
            <div class="grid grid-cols-2 gap-3 pt-2"><div class="rounded-xl bg-stone-50 p-3"><small class="block text-[10px] text-stone-400">Material digali</small><b>{{ angka(props.ringkas.material_digali) }} ton</b></div><div class="rounded-xl bg-stone-50 p-3"><small class="block text-[10px] text-stone-400">Stockpile akhir</small><b>{{ angka(props.ringkas.stok_akhir) }} ton</b></div></div>
          </div>
        </div>
      </section>

      <section class="grid gap-5 xl:grid-cols-[1.1fr_.9fr]">
        <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
          <h3 class="font-bold text-[14px]">Catat Data Konservasi</h3><p class="text-[11px] text-stone-400 mt-1">Satu baris untuk satu lokasi, komoditas, dan periode pelaporan.</p>
          <form class="grid gap-3 md:grid-cols-3 mt-4" @submit.prevent="simpanRecord">
            <select v-model="record.company_id" class="rounded-lg border-stone-200 text-[12px]" aria-label="Perusahaan"><option value="">Semua perusahaan</option><option v-for="company in props.companies" :key="company.id" :value="company.id">{{ company.name }}</option></select>
            <input v-model="record.periode" required type="date" class="rounded-lg border-stone-200 text-[12px]" aria-label="Periode"><input v-model="record.lokasi" required placeholder="Lokasi / pit / fasilitas" class="rounded-lg border-stone-200 text-[12px]">
            <input v-model="record.komoditas" required placeholder="Komoditas" class="rounded-lg border-stone-200 text-[12px]"><input v-model="record.target_produksi" required type="number" step="any" placeholder="Target produksi" class="rounded-lg border-stone-200 text-[12px]"><input v-model="record.produksi_aktual" required type="number" step="any" placeholder="Produksi aktual" class="rounded-lg border-stone-200 text-[12px]">
            <input v-model="record.material_digali" required type="number" step="any" placeholder="Material digali" class="rounded-lg border-stone-200 text-[12px]"><input v-model="record.recovery_percent" required type="number" step="any" min="0" max="100" placeholder="Recovery %" class="rounded-lg border-stone-200 text-[12px]"><input v-model="record.kehilangan_material" required type="number" step="any" placeholder="Kehilangan material" class="rounded-lg border-stone-200 text-[12px]">
            <input v-model="record.dilusi" required type="number" step="any" placeholder="Dilusi" class="rounded-lg border-stone-200 text-[12px]"><input v-model="record.stok_akhir" required type="number" step="any" placeholder="Stockpile akhir" class="rounded-lg border-stone-200 text-[12px]"><input v-model="record.mineral_ikutan" placeholder="Mineral ikutan" class="rounded-lg border-stone-200 text-[12px]"><p class="self-center rounded-lg bg-stone-50 px-3 py-2 text-[11px] text-stone-500">Tersimpan sebagai <b>draf</b>. Ajukan dari tabel di bawah.</p><textarea v-model="record.catatan" placeholder="Catatan / metode pengukuran" class="rounded-lg border-stone-200 text-[12px] md:col-span-2"></textarea>
            <button :disabled="record.processing" class="eq-btn-utama md:col-span-3">{{ record.processing ? 'Menyimpan...' : 'Simpan data konservasi' }}</button>
          </form>
          <p v-if="record.errors.periode" class="text-[11px] text-red-600 mt-2">{{ record.errors.periode }}</p>
        </div>

        <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
          <h3 class="font-bold text-[14px]">Rencana Perbaikan</h3><p class="text-[11px] text-stone-400 mt-1">Tindak lanjuti gap recovery, kehilangan, dan pengelolaan mineral ikutan.</p>
          <form class="grid gap-3 mt-4" @submit.prevent="simpanAction">
            <input v-model="action.judul" required placeholder="Judul tindakan" class="rounded-lg border-stone-200 text-[12px]"><select v-model="action.record_id" class="rounded-lg border-stone-200 text-[12px]" aria-label="Record"><option value="">Tanpa record tertentu</option><option v-for="item in props.records" :key="item.id" :value="item.id">{{ item.komoditas }} · {{ item.periodeLabel }} · {{ item.lokasi }}</option></select>
            <div class="grid grid-cols-3 gap-2"><select v-model="action.kategori" class="rounded-lg border-stone-200 text-[12px]" aria-label="Kategori"><option v-for="item in props.opsi.kategoriAction" :key="item" :value="item">{{ label(item) }}</option></select><select v-model="action.prioritas" class="rounded-lg border-stone-200 text-[12px]" aria-label="Prioritas"><option v-for="item in props.opsi.prioritasAction" :key="item" :value="item">{{ label(item) }}</option></select><select v-model="action.status" class="rounded-lg border-stone-200 text-[12px]" aria-label="Status"><option v-for="item in props.opsi.statusAction" :key="item" :value="item">{{ label(item) }}</option></select></div>
            <div class="grid grid-cols-2 gap-2"><input v-model="action.penanggung_jawab" placeholder="PIC" class="rounded-lg border-stone-200 text-[12px]"><input v-model="action.target_selesai" type="date" class="rounded-lg border-stone-200 text-[12px]" aria-label="Tenggat"></div><textarea v-model="action.uraian" placeholder="Uraian tindakan dan indikator selesai" class="rounded-lg border-stone-200 text-[12px]"></textarea><button :disabled="action.processing" class="eq-btn-utama">{{ action.processing ? 'Menyimpan...' : 'Tambah tindak lanjut' }}</button>
          </form>
        </div>
      </section>

      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-x-auto">
        <div class="px-5 py-4 border-b border-stone-100 flex items-center justify-between"><h3 class="font-bold text-[14px]">Tindak Lanjut Aktif</h3><Link :href="props.tautan.data" class="text-[11px] font-bold text-cam-orange">Lihat seluruh data</Link></div>
        <table class="min-w-full text-left text-[12px]"><thead><tr class="border-b border-stone-100 text-stone-400"><th class="px-5 py-3">Tindakan</th><th class="px-5 py-3">Kategori</th><th class="px-5 py-3">PIC</th><th class="px-5 py-3">Target</th><th class="px-5 py-3">Status</th></tr></thead><tbody><tr v-for="item in props.actions.slice(0, 8)" :key="item.id" class="border-b border-stone-50"><td class="px-5 py-3 font-semibold">{{ item.judul }}<small v-if="item.record" class="block text-[10px] text-stone-400">{{ item.record }}</small></td><td class="px-5 py-3">{{ label(item.kategori) }}</td><td class="px-5 py-3">{{ item.penanggung_jawab || '-' }}</td><td class="px-5 py-3">{{ tanggal(item.target_selesai) }}</td><td class="px-5 py-3"><select :value="item.status" class="rounded border-stone-200 text-[11px]" @change="ubahStatus(item, ($event.target as HTMLSelectElement).value)" aria-label="Status"><option v-for="status in props.opsi.statusAction" :key="status" :value="status">{{ label(status) }}</option></select></td></tr><tr v-if="!props.actions.length"><td colspan="5" class="px-5 py-8 text-center text-stone-400">Belum ada tindak lanjut.</td></tr></tbody></table>
      </section>
    </template>

    <template v-if="props.mode === 'data'">
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-x-auto">
        <div class="px-5 py-4 border-b border-stone-100"><h3 class="font-bold text-[14px]">Register Data Konservasi</h3><p class="text-[11px] text-stone-400">Data operasional yang menjadi dasar evaluasi konservasi minerba.</p></div>
        <table class="min-w-full text-left text-[12px]"><thead><tr class="border-b border-stone-100 text-stone-400"><th class="px-5 py-3">Periode / Lokasi</th><th class="px-5 py-3">Komoditas</th><th class="px-5 py-3">Target</th><th class="px-5 py-3">Aktual</th><th class="px-5 py-3">Recovery</th><th class="px-5 py-3">Loss / Dilusi</th><th class="px-5 py-3">Status</th><th class="px-5 py-3"></th></tr></thead><tbody><tr v-for="item in props.records" :key="item.id" class="border-b border-stone-50"><td class="px-5 py-3 font-semibold">{{ item.periodeLabel }}<small class="block text-[10px] text-stone-400">{{ item.lokasi }}</small></td><td class="px-5 py-3">{{ item.komoditas }}</td><td class="px-5 py-3">{{ angka(item.target_produksi) }}</td><td class="px-5 py-3">{{ angka(item.produksi_aktual) }}</td><td class="px-5 py-3 text-emerald-700">{{ persen(item.recovery_terhitung) }}</td><td class="px-5 py-3 text-red-600">{{ angka(item.kehilangan_material) }} / {{ angka(item.dilusi) }}</td><td class="px-5 py-3">
    <span class="rounded-full px-2 py-1 text-[10px] font-bold" :class="warnaStatus[item.status] || 'bg-stone-100 text-stone-600'">{{ item.statusLabel || label(item.status) }}</span>
    <small v-if="item.alur?.pengaju" class="block text-[10px] text-stone-400 mt-1">Diajukan {{ item.alur.pengaju }}</small>
    <small v-if="item.alur?.peninjau" class="block text-[10px] text-stone-400">Ditinjau {{ item.alur.peninjau }}</small>
    <small v-if="item.alur?.alasanTolak" class="block text-[10px] text-red-600 mt-1">{{ item.alur.alasanTolak }}</small>
  </td>
  <td class="px-5 py-3 text-right whitespace-nowrap">
    <button v-if="item.alur?.dapatDiajukan" :disabled="sibuk[item.id]" type="button" class="text-[11px] font-bold text-cam-lime-deep disabled:opacity-40" @click="ajukan(item)">Ajukan</button>
    <template v-if="item.alur?.dapatDitinjau">
      <button :disabled="sibuk[item.id]" type="button" class="text-[11px] font-bold text-emerald-600 disabled:opacity-40" @click="setujui(item)">Setujui</button>
      <button :disabled="sibuk[item.id]" type="button" class="ml-3 text-[11px] font-bold text-amber-600 disabled:opacity-40" @click="tolak(item)">Tolak</button>
    </template>
    <button v-if="isAdmin && item.status !== 'disetujui'" type="button" class="ml-3 text-red-600 text-[11px]" @click="hapusRecord(item)">Hapus</button>
  </td></tr><tr v-if="!props.records.length"><td colspan="8" class="px-5 py-10 text-center text-stone-400">Belum ada data.</td></tr></tbody></table>
      </section>
    </template>

    <template v-if="props.mode === 'laporan'">
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-6"><div class="flex flex-wrap justify-between gap-3"><div><p class="text-[10px] uppercase tracking-[.18em] text-stone-400">Laporan internal</p><h3 class="text-xl font-extrabold">Ringkasan Konservasi Minerba {{ props.tahun }}</h3><p class="text-[12px] text-stone-500 mt-1">Dasar pemantauan pemanfaatan mineral dan rencana pengendalian.</p></div><button type="button" class="eq-btn-lain" @click="cetak">Cetak laporan</button></div><div class="grid gap-3 sm:grid-cols-4 mt-6"><div class="rounded-xl bg-stone-50 p-4"><small class="block text-[10px] text-stone-400">Target produksi</small><b class="text-lg">{{ angka(props.ringkas.target_produksi) }} ton</b></div><div class="rounded-xl bg-stone-50 p-4"><small class="block text-[10px] text-stone-400">Produksi aktual</small><b class="text-lg">{{ angka(props.ringkas.produksi_aktual) }} ton</b></div><div class="rounded-xl bg-stone-50 p-4"><small class="block text-[10px] text-stone-400">Recovery</small><b class="text-lg text-emerald-700">{{ persen(props.ringkas.recovery) }}</b></div><div class="rounded-xl bg-stone-50 p-4"><small class="block text-[10px] text-stone-400">Kehilangan</small><b class="text-lg text-red-600">{{ angka(props.ringkas.kehilangan_material) }} ton</b></div></div></section>
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-x-auto"><table class="min-w-full text-left text-[12px]"><thead><tr class="border-b border-stone-100 text-stone-400"><th class="px-5 py-3">Periode</th><th class="px-5 py-3">Lokasi</th><th class="px-5 py-3">Komoditas</th><th class="px-5 py-3">Produksi</th><th class="px-5 py-3">Mineral Ikutan</th><th class="px-5 py-3">Catatan</th></tr></thead><tbody><tr v-for="item in props.records" :key="item.id" class="border-b border-stone-50"><td class="px-5 py-3">{{ item.periodeLabel }}</td><td class="px-5 py-3">{{ item.lokasi }}</td><td class="px-5 py-3 font-semibold">{{ item.komoditas }}</td><td class="px-5 py-3">{{ angka(item.produksi_aktual) }} {{ item.satuan }}</td><td class="px-5 py-3">{{ item.mineral_ikutan || '-' }}</td><td class="px-5 py-3 text-stone-500">{{ item.catatan || '-' }}</td></tr></tbody></table></section>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import PetaTambang from '../../Components/PetaTambang.vue';

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
const isAdmin = computed(() => Boolean(props.pengguna?.admin));

const judul: Record<string, string> = {
  dashboard: 'Mine Operations Control Tower',
  data: 'Input Data Operasi Shift',
  target: 'Target Operasi Bulanan',
  gis: 'GIS & Layer Tambang',
};

// `status` tidak ada di sini: ia hanya berpindah lewat alur tinjauan,
// tidak pernah dipilih dari formulir.
const record = useForm<any>({
  company_id: '', tanggal: new Date().toISOString().slice(0, 10), shift: 'siang', pit: '', area: '', material: 'Batubara',
  produksi_ton: 0, overburden_bcm: 0, jarak_angkut_km: 0, jumlah_truk: 0, jumlah_excavator: 0,
  jam_operasi: 0, jam_delay: 0, catatan: '',
});
const target = useForm<any>({ company_id: '', tahun: new Date().getFullYear(), bulan: new Date().getMonth() + 1, target_produksi_ton: 0, target_overburden_bcm: 0, target_strip_ratio: '', target_jarak_km: '', catatan: '' });
const layer = useForm<any>({ company_id: '', nama: '', tipe: 'area_kerja', geojson: '{\n  "type": "FeatureCollection",\n  "features": []\n}', warna: '#84cc16', status: 'draft', catatan: '', tanggal_survey: '', sumber_survey: '' });

const angka = (value: unknown, digits = 0) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: digits, minimumFractionDigits: digits }).format(Number(value || 0));
const persen = (value: unknown) => `${angka(value, 1)}%`;
const tanggal = (value: unknown) => value ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(String(value))) : '-';
const label = (value: string) => value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

const tautan = computed(() => props.tautan || {});
const geoValid = computed(() => {
  try { JSON.parse(layer.geojson); return true; } catch { return false; }
});
const geoSummary = (value: string) => {
  try {
    const json = JSON.parse(value);
    const features = json.type === 'FeatureCollection' ? json.features?.length || 0 : json.type === 'Feature' ? 1 : 0;
    return `${json.type || 'JSON'} · ${features} feature`;
  } catch { return 'GeoJSON belum valid'; }
};

function rentang() {
  router.get(window.location.pathname, { dari: props.dari, sampai: props.sampai }, { preserveState: true, replace: true });
}
function simpanRecord() {
  record.post(tautan.value.recordSimpan, { preserveScroll: true, onSuccess: () => record.reset('produksi_ton', 'overburden_bcm', 'jarak_angkut_km', 'jumlah_truk', 'jumlah_excavator', 'jam_operasi', 'jam_delay', 'catatan') });
}
function simpanTarget() {
  target.post(tautan.value.targetSimpan, { preserveScroll: true, onSuccess: () => target.reset('target_produksi_ton', 'target_overburden_bcm', 'target_strip_ratio', 'target_jarak_km', 'catatan') });
}
function simpanLayer() {
  layer.post(tautan.value.layerSimpan, { preserveScroll: true, onSuccess: () => layer.reset('nama', 'catatan') });
}
/** Menyisipkan id ke tautan bertanda __ID__ yang dikirim controller. */
const untuk = (pola: string | undefined, id: number | string) => String(pola || '').replace('__ID__', String(id));

function hapusRecord(item: any) {
  if (window.confirm(`Hapus record ${item.tanggalLabel}?`)) router.delete(untuk(tautan.value.recordHapus, item.id), { preserveScroll: true });
}
function hapusLayer(item: any) {
  if (window.confirm(`Hapus layer ${item.nama}?`)) router.delete(untuk(tautan.value.layerHapus, item.id), { preserveScroll: true });
}

/* ---------- alur tinjauan ---------- */

const sibuk = reactive<Record<number, boolean>>({});

function ajukan(item: any) {
  sibuk[item.id] = true;
  router.post(untuk(tautan.value.recordAjukan, item.id), {}, {
    preserveScroll: true, onFinish: () => { sibuk[item.id] = false; },
  });
}

function setujui(item: any) {
  if (!window.confirm(`Setujui data ${item.tanggalLabel} shift ${item.shift}? Setelah disetujui, data tidak dapat diubah lagi.`)) return;
  sibuk[item.id] = true;
  router.post(untuk(tautan.value.recordSetujui, item.id), {}, {
    preserveScroll: true, onFinish: () => { sibuk[item.id] = false; },
  });
}

function tolak(item: any) {
  const alasan = window.prompt(`Alasan penolakan data ${item.tanggalLabel} shift ${item.shift}:`);
  if (alasan === null) return;
  sibuk[item.id] = true;
  router.post(untuk(tautan.value.recordTolak, item.id), { alasan_tolak: alasan }, {
    preserveScroll: true, onFinish: () => { sibuk[item.id] = false; },
  });
}

/* ---------- tindak lanjut ---------- */

const tindak = useForm<any>({
  company_id: '', record_id: '', kode_pemicu: '', judul: '', kategori: '',
  prioritas: 'sedang', penanggung_jawab: '', target_selesai: '', uraian: '',
});

/** Peringatan yang sudah punya tindak lanjut terbuka; dipakai menandainya. */
const ditangani = computed(() => new Set(props.kodeDitangani || []));

/**
 * Membuka formulir dengan judul dan saran dari peringatannya.
 *
 * Menyalin saran ke uraian bukan kemudahan semata: saran itu hilang dari
 * layar begitu peringatannya berhenti muncul, sementara orang yang
 * mengerjakannya beberapa hari kemudian perlu tahu apa yang semula
 * disarankan.
 */
function tindakDari(alert: any) {
  tindak.kode_pemicu = alert.kode;
  tindak.judul = alert.judul;
  tindak.kategori = alert.kode;
  tindak.prioritas = alert.level === 'tinggi' ? 'tinggi' : 'sedang';
  tindak.uraian = alert.saran || '';
  document.getElementById('form-tindak')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function simpanTindak() {
  tindak.post(tautan.value.tindakSimpan, {
    preserveScroll: true,
    onSuccess: () => tindak.reset('record_id', 'kode_pemicu', 'judul', 'kategori', 'penanggung_jawab', 'target_selesai', 'uraian'),
  });
}

function ubahTindak(item: any, status: string) {
  router.put(untuk(tautan.value.tindakUbah, item.id), { status }, { preserveScroll: true });
}

function hapusTindak(item: any) {
  if (window.confirm(`Hapus tindak lanjut “${item.judul}”?`)) {
    router.delete(untuk(tautan.value.tindakHapus, item.id), { preserveScroll: true });
  }
}

const warnaStatus: Record<string, string> = {
  draf: 'bg-stone-100 text-stone-600',
  diajukan: 'bg-amber-100 text-amber-700',
  disetujui: 'bg-emerald-100 text-emerald-700',
  ditolak: 'bg-red-100 text-red-700',
};
</script>

<template>
  <Head :title="judul[props.mode]" />
  <div class="max-w-[1400px] mx-auto space-y-5">
    <section class="flex flex-wrap items-end justify-between gap-4">
      <div><p class="text-[10px] font-bold uppercase tracking-[.16em] text-cam-lime-deep">Engineering · Operations</p><h2 class="text-2xl font-extrabold tracking-tight text-stone-800">{{ judul[props.mode] }}</h2><p class="text-[12px] text-stone-500 mt-1">Satu sumber data untuk target, realisasi, delay, produktivitas, dan layer spasial tambang.</p></div>
      <div v-if="props.mode === 'dashboard' || props.mode === 'data'" class="flex gap-2"><input v-model="props.dari" type="date" class="rounded-lg border-stone-200 text-[11px]"><input v-model="props.sampai" type="date" class="rounded-lg border-stone-200 text-[11px]"><button class="eq-btn-lain" type="button" @click="rentang">Terapkan</button></div>
    </section>

    <nav class="flex flex-wrap gap-2">
      <Link v-for="item in [['dashboard','Control Tower',tautan.dashboard],['data','Input Shift',tautan.data],['target','Target Bulanan',tautan.target],['gis','GIS Tambang',tautan.gis]]" :key="item[0]" :href="item[2] as string" class="rounded-full px-4 py-2 text-[11px] font-bold" :class="props.mode === item[0] ? 'bg-cam-ink text-white' : 'bg-white text-stone-500 border border-stone-200'">{{ item[1] }}</Link>
      <a :href="tautan.cetak" class="ml-auto rounded-full px-4 py-2 text-[11px] font-bold bg-white text-cam-lime-deep border border-cam-lime">Cetak laporan</a>
    </nav>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
      <article v-for="card in [{label:'Produksi',value:angka(props.ringkas?.produksi),suffix:'ton',color:'text-cam-ink'},{label:'Capaian Produksi',value:persen(props.ringkas?.capaian_produksi),suffix:'',color:'text-cam-orange'},{label:'Strip Ratio',value:angka(props.ringkas?.strip_ratio,2),suffix:'OB/ton',color:'text-violet-700'},{label:'Efisiensi Waktu',value:persen(props.ringkas?.efisiensi_waktu),suffix:'',color:'text-emerald-600'},{label:'Delay',value:angka(props.ringkas?.delay_jam,1),suffix:'jam',color:'text-red-600'}]" :key="card.label" class="rounded-2xl bg-white border border-stone-100 shadow-card p-4"><p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">{{ card.label }}</p><p class="mt-2 text-xl font-extrabold" :class="card.color">{{ card.value }} <small class="text-[10px] font-semibold text-stone-400">{{ card.suffix }}</small></p></article>
    </section>

    <template v-if="props.mode === 'dashboard'">
      <!--
        Ramalan dan kelengkapan diletakkan di atas seluruh angka lain.
        Capaian yang sama berarti dua hal berlawanan menurut tanggalnya,
        dan capaian rendah tidak membedakan produksi yang memang kurang
        dari laporan yang belum masuk — keduanya perlu terbaca lebih
        dulu, sebelum angka di bawahnya ditafsirkan.
      -->
      <section class="grid gap-5 lg:grid-cols-2">
        <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h3 class="font-bold text-[14px]">Proyeksi akhir periode</h3>
              <p class="text-[11px] text-stone-400 mt-1">Bila laju {{ angka(props.ramalan?.laju) }} ton/hari diteruskan.</p>
            </div>
            <span class="rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-wide" :class="{
              'bg-emerald-100 text-emerald-700': props.ramalan?.status === 'aman',
              'bg-amber-100 text-amber-700': props.ramalan?.status === 'berisiko',
              'bg-red-100 text-red-700': props.ramalan?.status === 'meleset',
              'bg-stone-100 text-stone-500': ['tanpa-target','belum-mulai'].includes(props.ramalan?.status),
            }">{{ label(String(props.ramalan?.status || '-')) }}</span>
          </div>

          <div class="mt-4 flex items-end gap-2">
            <b class="text-3xl font-extrabold tracking-tight text-stone-800">{{ angka(props.ramalan?.proyeksi) }}</b>
            <span class="text-[12px] text-stone-400 pb-1">ton · {{ persen(props.ramalan?.proyeksiPersen) }} dari target</span>
          </div>

          <div class="mt-3 h-2 rounded-full bg-stone-100 overflow-hidden">
            <div class="h-full rounded-full transition-all" :class="props.ramalan?.status === 'meleset' ? 'bg-red-400' : props.ramalan?.status === 'berisiko' ? 'bg-amber-400' : 'bg-cam-lime'"
                 :style="{ width: `${Math.max(2, Math.min(100, Number(props.ramalan?.proyeksiPersen || 0)))}%` }"></div>
          </div>

          <div class="grid grid-cols-3 gap-3 mt-5 text-center">
            <div class="rounded-xl bg-stone-50 p-3">
              <small class="block text-[10px] text-stone-400">Hari tersisa</small>
              <b class="text-[13px]">{{ props.ramalan?.hariTersisa ?? '-' }}</b>
            </div>
            <div class="rounded-xl bg-stone-50 p-3">
              <small class="block text-[10px] text-stone-400">Kekurangan</small>
              <b class="text-[13px]">{{ angka(props.ramalan?.kekurangan) }} ton</b>
            </div>
            <div class="rounded-xl bg-stone-50 p-3">
              <small class="block text-[10px] text-stone-400">Laju perlu</small>
              <b class="text-[13px]">{{ angka(props.ramalan?.lajuDibutuhkan) }}/hari</b>
            </div>
          </div>

          <p v-if="Number(props.ramalan?.pengaliDibutuhkan) > 1" class="mt-3 rounded-xl bg-amber-50 text-amber-800 p-3 text-[11px]">
            Sisa periode perlu berjalan <b>{{ angka(props.ramalan?.pengaliDibutuhkan, 2) }}×</b> lebih cepat daripada rata-rata sejauh ini.
          </p>
        </div>

        <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h3 class="font-bold text-[14px]">Kelengkapan laporan shift</h3>
              <p class="text-[11px] text-stone-400 mt-1">Selama belum lengkap, angka di halaman ini belum mewakili periode.</p>
            </div>
            <b class="text-2xl font-extrabold" :class="Number(props.kelengkapan?.persen) >= 90 ? 'text-emerald-600' : 'text-amber-600'">{{ persen(props.kelengkapan?.persen) }}</b>
          </div>

          <div class="mt-4 h-2 rounded-full bg-stone-100 overflow-hidden">
            <div class="h-full rounded-full bg-cam-lime transition-all" :style="{ width: `${Math.max(0, Math.min(100, Number(props.kelengkapan?.persen || 0)))}%` }"></div>
          </div>

          <!-- Dua celah dipisahkan: tagihannya beralamat berbeda. -->
          <div class="grid grid-cols-2 gap-3 mt-5">
            <div class="rounded-xl border border-stone-100 p-3">
              <small class="block text-[10px] text-stone-400">Belum dilaporkan</small>
              <b class="text-[15px]" :class="Number(props.kelengkapan?.belumDilaporkan) ? 'text-red-600' : 'text-stone-700'">{{ props.kelengkapan?.belumDilaporkan ?? 0 }}</b>
              <small class="block text-[10px] text-stone-400 mt-1">shift · tagih ke pengawas lapangan</small>
            </div>
            <div class="rounded-xl border border-stone-100 p-3">
              <small class="block text-[10px] text-stone-400">Menunggu tinjauan</small>
              <b class="text-[15px]" :class="Number(props.kelengkapan?.menungguTinjauan) ? 'text-amber-600' : 'text-stone-700'">{{ props.kelengkapan?.menungguTinjauan ?? 0 }}</b>
              <small class="block text-[10px] text-stone-400 mt-1">shift · tagih ke Kepala Teknik Tambang</small>
            </div>
          </div>

          <div v-if="(props.kelengkapan?.bolong || []).length" class="mt-4">
            <small class="block text-[10px] font-bold uppercase tracking-wide text-stone-400 mb-2">Shift yang bolong</small>
            <div class="flex flex-wrap gap-1.5 max-h-24 overflow-y-auto">
              <span v-for="b in props.kelengkapan.bolong" :key="`${b.tanggal}-${b.shift}`"
                    class="rounded-md bg-red-50 text-red-700 px-2 py-1 text-[10px] font-medium">
                {{ tanggal(b.tanggal) }} · {{ label(b.shift) }}
              </span>
            </div>
          </div>

          <p v-if="props.fuelPerTonBeda !== null && props.fuelPerTonBeda !== undefined" class="mt-4 rounded-xl p-3 text-[11px]"
             :class="Number(props.fuelPerTonBeda) > 10 ? 'bg-amber-50 text-amber-800' : 'bg-stone-50 text-stone-600'">
            Bahan bakar per ton {{ Number(props.fuelPerTonBeda) >= 0 ? 'naik' : 'turun' }}
            <b>{{ angka(Math.abs(Number(props.fuelPerTonBeda)), 1) }}%</b> terhadap periode sebelumnya.
          </p>
        </div>
      </section>

      <section class="grid gap-5 xl:grid-cols-[1.15fr_.85fr]">
        <div class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden"><div class="px-5 py-4 border-b border-stone-100 flex justify-between"><div><h3 class="font-bold text-[14px]">Kinerja per Pit / Area</h3><p class="text-[11px] text-stone-400">Periode {{ tanggal(props.dari) }} – {{ tanggal(props.sampai) }}</p></div><span class="text-[11px] text-stone-400">{{ props.ringkas?.jumlah_record || 0 }} record</span></div><div class="overflow-x-auto"><table class="min-w-full text-left text-[12px]"><thead><tr class="border-b border-stone-100 text-stone-400"><th class="px-5 py-3">Pit / Area</th><th class="px-5 py-3">Produksi</th><th class="px-5 py-3">OB</th><th class="px-5 py-3">Strip Ratio</th><th class="px-5 py-3">Delay</th></tr></thead><tbody><tr v-for="item in props.perPit || []" :key="item.nama" class="border-b border-stone-50"><td class="px-5 py-3 font-semibold">{{ item.nama }}</td><td class="px-5 py-3">{{ angka(item.produksi) }} ton</td><td class="px-5 py-3">{{ angka(item.ob) }} BCM</td><td class="px-5 py-3">{{ angka(item.strip_ratio, 2) }}</td><td class="px-5 py-3" :class="item.delay_persen > 15 ? 'text-red-600 font-bold' : ''">{{ persen(item.delay_persen) }}</td></tr><tr v-if="!(props.perPit || []).length"><td colspan="5" class="px-5 py-10 text-center text-stone-400">Belum ada input operasi pada periode ini.</td></tr></tbody></table></div></div>
        <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5"><h3 class="font-bold text-[14px]">Alert & keputusan</h3><div class="mt-4 space-y-3"><div v-for="item in props.alerts || []" :key="item.kode" class="rounded-xl border p-3" :class="item.level === 'tinggi' ? 'border-red-100 bg-red-50' : 'border-amber-100 bg-amber-50'"><b class="text-[12px]" :class="item.level === 'tinggi' ? 'text-red-700' : 'text-amber-700'">{{ item.judul }}</b><p class="text-[11px] text-stone-600 mt-1">{{ item.ket }}</p><p v-if="item.saran" class="text-[11px] text-stone-700 mt-2 pt-2 border-t border-black/5"><span class="font-bold">Tindakan: </span>{{ item.saran }}</p><div class="mt-2"><span v-if="ditangani.has(item.kode)" class="inline-block rounded-full bg-emerald-100 text-emerald-700 px-2 py-1 text-[10px] font-bold">Sedang ditangani</span><button v-else type="button" class="text-[11px] font-bold text-cam-lime-deep" @click="tindakDari(item)">+ Buat tindak lanjut</button></div></div><p v-if="!(props.alerts || []).length" class="rounded-xl bg-emerald-50 text-emerald-700 p-4 text-[12px]">Tidak ada alert kritis pada periode ini.</p></div><div class="grid grid-cols-2 gap-3 mt-5"><div class="rounded-xl bg-stone-50 p-3"><small class="block text-[10px] text-stone-400">Target produksi</small><b>{{ angka(props.target?.produksi) }} ton</b></div><div class="rounded-xl bg-stone-50 p-3"><small class="block text-[10px] text-stone-400">Layer GIS aktif</small><b>{{ angka(props.ringkas?.layer_aktif) }}</b></div></div></div>
      </section>
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5"><div class="flex justify-between items-center"><div><h3 class="font-bold text-[14px]">Trend Produksi Harian</h3><p class="text-[11px] text-stone-400">Visual sederhana dari input shift; detail dapat diekspor pada tahap berikutnya.</p></div><Link :href="tautan.data" class="text-[11px] font-bold text-cam-lime-deep">Tambah data shift</Link></div><div class="flex items-end gap-2 h-40 mt-5 overflow-x-auto"><div v-for="item in props.tren || []" :key="item.tanggal" class="min-w-[34px] flex flex-col items-center justify-end h-full"><span class="text-[9px] text-stone-400 mb-1">{{ angka(item.produksi / 1000, 1) }}k</span><div class="w-7 rounded-t-md bg-cam-lime" :style="{height:`${Math.max(5, Math.min(100, (item.produksi / Math.max(1, props.ringkas?.produksi)) * 100))}%`}"></div><small class="text-[9px] text-stone-400 mt-1">{{ new Date(item.tanggal).getDate() }}</small></div><p v-if="!(props.tren || []).length" class="m-auto text-[12px] text-stone-400">Belum ada trend.</p></div></section>
    </template>

    <!--
      Tindak lanjut ditempatkan pada dasbor, bukan halaman tersendiri.
      Peringatan dan penanganannya perlu terbaca berdampingan; dipisahkan
      ke halaman lain, yang terjadi adalah peringatan dibaca berulang kali
      tanpa ada yang tahu apakah sudah ada yang mengerjakannya.
    -->
    <section v-if="props.mode === 'dashboard'" class="grid gap-5 xl:grid-cols-[1.15fr_.85fr]">
      <div class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <div class="px-5 py-4 border-b border-stone-100 flex items-center justify-between">
          <div>
            <h3 class="font-bold text-[14px]">Tindak lanjut</h3>
            <p class="text-[11px] text-stone-400">
              {{ props.ringkas?.tindak_terbuka || 0 }} terbuka<span v-if="props.ringkas?.tindak_terlambat">, <b class="text-red-600">{{ props.ringkas.tindak_terlambat }} terlambat</b></span>
            </p>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-left text-[12px]">
            <thead><tr class="border-b border-stone-100 text-stone-400"><th class="px-5 py-3">Tindakan</th><th class="px-5 py-3">PIC</th><th class="px-5 py-3">Target</th><th class="px-5 py-3">Status</th><th></th></tr></thead>
            <tbody>
              <tr v-for="item in props.tindak || []" :key="item.id" class="border-b border-stone-50" :class="item.terlambat ? 'bg-red-50/40' : ''">
                <td class="px-5 py-3">
                  <b class="font-semibold">{{ item.judul }}</b>
                  <small v-if="item.uraian" class="block text-[10px] text-stone-400 mt-0.5">{{ item.uraian }}</small>
                  <span v-if="item.kodePemicu" class="inline-block mt-1 rounded bg-stone-100 text-stone-500 px-1.5 py-0.5 text-[9px] font-mono">{{ item.kodePemicu }}</span>
                </td>
                <td class="px-5 py-3">{{ item.penanggung_jawab || '-' }}</td>
                <td class="px-5 py-3" :class="item.terlambat ? 'text-red-600 font-bold' : ''">
                  {{ tanggal(item.target_selesai) }}
                  <small v-if="item.terlambat" class="block text-[10px]">terlambat {{ item.hariTerlambat }} hari</small>
                </td>
                <td class="px-5 py-3">
                  <select :value="item.status" class="rounded border-stone-200 text-[11px]" @change="ubahTindak(item, ($event.target as HTMLSelectElement).value)">
                    <option v-for="st in props.opsi?.statusTindak || []" :key="st" :value="st">{{ label(st) }}</option>
                  </select>
                </td>
                <td class="px-5 py-3 text-right"><button v-if="isAdmin" type="button" class="text-red-600 text-[11px]" @click="hapusTindak(item)">Hapus</button></td>
              </tr>
              <tr v-if="!(props.tindak || []).length"><td colspan="5" class="px-5 py-10 text-center text-stone-400">Belum ada tindak lanjut. Buat dari peringatan di atas.</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div id="form-tindak" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="font-bold text-[14px]">Tambah tindak lanjut</h3>
        <p class="text-[11px] text-stone-400 mt-1">Isi otomatis bila dibuat dari sebuah peringatan.</p>
        <form class="grid gap-3 mt-4" @submit.prevent="simpanTindak">
          <input v-model="tindak.judul" required placeholder="Tindakan yang akan dikerjakan" class="rounded-lg border-stone-200 text-[12px]">
          <div class="grid grid-cols-2 gap-2">
            <select v-model="tindak.prioritas" class="rounded-lg border-stone-200 text-[12px]">
              <option v-for="pr in props.opsi?.prioritasTindak || []" :key="pr" :value="pr">Prioritas {{ label(pr) }}</option>
            </select>
            <input v-model="tindak.target_selesai" type="date" class="rounded-lg border-stone-200 text-[12px]">
          </div>
          <input v-model="tindak.penanggung_jawab" placeholder="Penanggung jawab" class="rounded-lg border-stone-200 text-[12px]">
          <textarea v-model="tindak.uraian" rows="3" placeholder="Uraian" class="rounded-lg border-stone-200 text-[12px]"></textarea>
          <p v-if="tindak.kode_pemicu" class="rounded-lg bg-stone-50 px-3 py-2 text-[11px] text-stone-500">Dari peringatan <b class="font-mono">{{ tindak.kode_pemicu }}</b></p>
          <button :disabled="tindak.processing" class="eq-btn-utama">{{ tindak.processing ? 'Menyimpan...' : 'Simpan tindak lanjut' }}</button>
        </form>
      </div>
    </section>

    <template v-if="props.mode === 'data'">
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5"><h3 class="font-bold text-[14px]">Input laporan shift</h3><p class="text-[11px] text-stone-400 mt-1">Isi data aktual dari pit, fleet, dan laporan pengawas shift.</p><form class="grid gap-3 md:grid-cols-4 mt-4" @submit.prevent="simpanRecord"><select v-model="record.company_id" class="rounded-lg border-stone-200 text-[12px]"><option value="">Perusahaan umum</option><option v-for="item in props.companies || []" :key="item.id" :value="item.id">{{ item.name }}</option></select><input v-model="record.tanggal" required type="date" class="rounded-lg border-stone-200 text-[12px]"><select v-model="record.shift" class="rounded-lg border-stone-200 text-[12px]"><option v-for="item in props.opsi?.shift || []" :key="item" :value="item">Shift {{ label(item) }}</option></select><input v-model="record.material" required placeholder="Material" class="rounded-lg border-stone-200 text-[12px]"><input v-model="record.pit" placeholder="Pit" class="rounded-lg border-stone-200 text-[12px]"><input v-model="record.area" placeholder="Area" class="rounded-lg border-stone-200 text-[12px]"><input v-model.number="record.produksi_ton" required type="number" step="any" min="0" placeholder="Produksi ton" class="rounded-lg border-stone-200 text-[12px]"><input v-model.number="record.overburden_bcm" required type="number" step="any" min="0" placeholder="OB BCM" class="rounded-lg border-stone-200 text-[12px]"><input v-model.number="record.jarak_angkut_km" required type="number" step="any" min="0" placeholder="Jarak angkut km" class="rounded-lg border-stone-200 text-[12px]"><input v-model.number="record.jumlah_truk" required type="number" min="0" placeholder="Jumlah truk" class="rounded-lg border-stone-200 text-[12px]"><input v-model.number="record.jumlah_excavator" required type="number" min="0" placeholder="Jumlah excavator" class="rounded-lg border-stone-200 text-[12px]"><input v-model.number="record.jam_operasi" required type="number" step="any" min="0" max="24" placeholder="Jam operasi" class="rounded-lg border-stone-200 text-[12px]"><input v-model.number="record.jam_delay" required type="number" step="any" min="0" max="24" placeholder="Jam delay" class="rounded-lg border-stone-200 text-[12px]"><p class="md:col-span-2 self-center rounded-lg bg-stone-50 px-3 py-2 text-[11px] text-stone-500">Tersimpan sebagai <b>draf</b>. Ajukan dari tabel di bawah agar ditinjau Kepala Teknik Tambang.</p><textarea v-model="record.catatan" placeholder="Catatan penyebab delay / kendala lapangan" class="rounded-lg border-stone-200 text-[12px] md:col-span-2"></textarea><button :disabled="record.processing" class="eq-btn-utama md:col-span-4">{{ record.processing ? 'Menyimpan...' : 'Simpan laporan shift' }}</button></form><p v-if="record.errors.jam_delay" class="text-[11px] text-red-600 mt-2">{{ record.errors.jam_delay }}</p></section>
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-x-auto"><div class="px-5 py-4 border-b border-stone-100"><h3 class="font-bold text-[14px]">Record operasi terbaru</h3></div><table class="min-w-full text-left text-[12px]"><thead><tr class="border-b border-stone-100 text-stone-400"><th class="px-5 py-3">Tanggal / Shift</th><th class="px-5 py-3">Pit / Material</th><th class="px-5 py-3">Produksi</th><th class="px-5 py-3">OB</th><th class="px-5 py-3">Operasi / Delay</th><th class="px-5 py-3">Status</th><th></th></tr></thead><tbody><tr v-for="item in props.records || []" :key="item.id" class="border-b border-stone-50"><td class="px-5 py-3 font-semibold">{{ item.tanggalLabel }}<small class="block text-[10px] text-stone-400">{{ label(item.shift) }}</small></td><td class="px-5 py-3">{{ item.pit || item.area || '-' }}<small class="block text-[10px] text-stone-400">{{ item.material }}</small></td><td class="px-5 py-3">{{ angka(item.produksi_ton) }} ton</td><td class="px-5 py-3">{{ angka(item.overburden_bcm) }} BCM</td><td class="px-5 py-3">{{ angka(item.jam_operasi,1) }} / {{ angka(item.jam_delay,1) }} jam</td><td class="px-5 py-3">
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
  </td></tr><tr v-if="!(props.records || []).length"><td colspan="7" class="px-5 py-10 text-center text-stone-400">Belum ada record.</td></tr></tbody></table></section>
    </template>

    <template v-if="props.mode === 'target'">
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5"><h3 class="font-bold text-[14px]">Tetapkan target bulanan</h3><form class="grid gap-3 md:grid-cols-4 mt-4" @submit.prevent="simpanTarget"><select v-model="target.company_id" class="rounded-lg border-stone-200 text-[12px]"><option value="">Perusahaan umum</option><option v-for="item in props.companies || []" :key="item.id" :value="item.id">{{ item.name }}</option></select><input v-model.number="target.tahun" required type="number" min="2000" max="2100" placeholder="Tahun" class="rounded-lg border-stone-200 text-[12px]"><select v-model.number="target.bulan" class="rounded-lg border-stone-200 text-[12px]"><option v-for="item in 12" :key="item" :value="item">Bulan {{ item }}</option></select><input v-model.number="target.target_produksi_ton" required type="number" step="any" min="0" placeholder="Target produksi ton" class="rounded-lg border-stone-200 text-[12px]"><input v-model.number="target.target_overburden_bcm" required type="number" step="any" min="0" placeholder="Target OB BCM" class="rounded-lg border-stone-200 text-[12px]"><input v-model.number="target.target_strip_ratio" type="number" step="any" min="0" placeholder="Target strip ratio" class="rounded-lg border-stone-200 text-[12px]"><input v-model.number="target.target_jarak_km" type="number" step="any" min="0" placeholder="Target jarak km" class="rounded-lg border-stone-200 text-[12px]"><input v-model="target.catatan" placeholder="Catatan target" class="rounded-lg border-stone-200 text-[12px] md:col-span-2"><button :disabled="target.processing" class="eq-btn-utama md:col-span-4">{{ target.processing ? 'Menyimpan...' : 'Simpan target' }}</button></form></section>
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-x-auto"><div class="px-5 py-4 border-b border-stone-100"><h3 class="font-bold text-[14px]">Target terdaftar</h3></div><table class="min-w-full text-left text-[12px]"><thead><tr class="border-b border-stone-100 text-stone-400"><th class="px-5 py-3">Periode</th><th class="px-5 py-3">Perusahaan</th><th class="px-5 py-3">Produksi</th><th class="px-5 py-3">OB</th><th class="px-5 py-3">Strip Ratio</th><th class="px-5 py-3">Jarak</th></tr></thead><tbody><tr v-for="item in props.targets || []" :key="item.id" class="border-b border-stone-50"><td class="px-5 py-3 font-semibold">{{ item.bulan }}/{{ item.tahun }}</td><td class="px-5 py-3">{{ item.company?.name || 'Umum' }}</td><td class="px-5 py-3">{{ angka(item.target_produksi_ton) }} ton</td><td class="px-5 py-3">{{ angka(item.target_overburden_bcm) }} BCM</td><td class="px-5 py-3">{{ angka(item.target_strip_ratio,2) }}</td><td class="px-5 py-3">{{ angka(item.target_jarak_km,2) }} km</td></tr><tr v-if="!(props.targets || []).length"><td colspan="6" class="px-5 py-10 text-center text-stone-400">Belum ada target.</td></tr></tbody></table></section>
    </template>

    <template v-if="props.mode === 'gis'">
      <!--
        Peta dan angka kemajuan diletakkan di atas registri layer. Yang
        dicari orang saat membuka halaman ini adalah bentuk dan luasnya,
        bukan daftar berkas yang pernah diunggah.
      -->
      <section class="grid gap-5 xl:grid-cols-[1.4fr_.6fr]">
        <PetaTambang :layers="props.layers || []" />

        <div class="space-y-3">
          <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
            <h3 class="font-bold text-[14px]">Kemajuan area</h3>
            <p class="text-[11px] text-stone-400 mt-1">
              Hanya layer berstatus aktif yang dihitung.
              <span v-if="props.kemajuan?.surveiTerakhir">Survei terakhir {{ tanggal(props.kemajuan.surveiTerakhir) }}.</span>
            </p>

            <div class="mt-4 flex items-end gap-2">
              <b class="text-3xl font-extrabold tracking-tight text-stone-800">{{ angka(props.kemajuan?.terganggu, 2) }}</b>
              <span class="text-[12px] text-stone-400 pb-1">ha terganggu</span>
            </div>

            <div class="mt-3 h-2 rounded-full bg-stone-100 overflow-hidden">
              <div class="h-full rounded-full bg-emerald-500 transition-all"
                   :style="{ width: `${Math.max(0, Math.min(100, Number(props.kemajuan?.persen || 0)))}%` }"></div>
            </div>
            <p class="text-[11px] text-stone-500 mt-2">
              <b class="text-emerald-700">{{ angka(props.kemajuan?.reklamasi, 2) }} ha</b> direklamasi
              ({{ persen(props.kemajuan?.persen) }}), sisa {{ angka(props.kemajuan?.sisa, 2) }} ha.
            </p>

            <div class="grid grid-cols-2 gap-3 mt-4">
              <div class="rounded-xl bg-stone-50 p-3">
                <small class="block text-[10px] text-stone-400">Jalan &amp; drainase</small>
                <b class="text-[13px]">{{ angka(props.kemajuan?.panjangJalanKm, 2) }} km</b>
              </div>
              <div class="rounded-xl bg-stone-50 p-3">
                <small class="block text-[10px] text-stone-400">Layer aktif</small>
                <b class="text-[13px]">{{ props.ringkas?.layer_aktif || 0 }}</b>
              </div>
            </div>
          </div>

          <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
            <h3 class="font-bold text-[14px] mb-3">Luas per tipe</h3>
            <table class="w-full text-[11px]">
              <tbody>
                <tr v-for="t in props.kemajuan?.perTipe || []" :key="t.tipe" class="border-b border-stone-50">
                  <td class="py-2"><span class="inline-block w-2.5 h-2.5 rounded-sm mr-2 align-middle" :style="{ backgroundColor: t.warna }"></span>{{ label(t.tipe) }}</td>
                  <td class="py-2 text-right font-semibold">{{ angka(t.hektare, 2) }} ha</td>
                  <td class="py-2 text-right text-stone-400">{{ t.layer }} layer</td>
                </tr>
                <tr v-if="!(props.kemajuan?.perTipe || []).length"><td colspan="3" class="py-6 text-center text-stone-400">Belum ada layer aktif.</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>


      <section class="grid gap-5 xl:grid-cols-[.8fr_1.2fr]"><div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5"><h3 class="font-bold text-[14px]">Registri layer GeoJSON</h3><p class="text-[11px] text-stone-400 mt-1">Fondasi GIS ini menyimpan layer yang dapat dipakai oleh peta interaktif pada tahap berikutnya.</p><form class="grid gap-3 mt-4" @submit.prevent="simpanLayer"><select v-model="layer.company_id" class="rounded-lg border-stone-200 text-[12px]"><option value="">Perusahaan umum</option><option v-for="item in props.companies || []" :key="item.id" :value="item.id">{{ item.name }}</option></select><input v-model="layer.nama" required placeholder="Nama layer, contoh: Pit 1 2026" class="rounded-lg border-stone-200 text-[12px]"><div class="grid grid-cols-3 gap-2"><select v-model="layer.tipe" class="rounded-lg border-stone-200 text-[12px]"><option v-for="item in props.opsi?.tipeLayer || []" :key="item" :value="item">{{ label(item) }}</option></select><select v-model="layer.status" class="rounded-lg border-stone-200 text-[12px]"><option v-for="item in props.opsi?.statusLayer || []" :key="item" :value="item">{{ label(item) }}</option></select><input v-model="layer.warna" type="color" class="h-10 w-full rounded-lg border-stone-200"></div><textarea v-model="layer.geojson" required rows="10" class="font-mono text-[11px] rounded-lg border-stone-200"></textarea><p class="text-[11px]" :class="geoValid ? 'text-emerald-600' : 'text-red-600'">{{ geoValid ? geoSummary(layer.geojson) : 'GeoJSON belum valid' }}</p><div class="grid grid-cols-2 gap-2"><input v-model="layer.tanggal_survey" type="date" class="rounded-lg border-stone-200 text-[12px]" title="Tanggal survei"><input v-model="layer.sumber_survey" placeholder="Sumber survei (drone / total station)" class="rounded-lg border-stone-200 text-[12px]"></div><textarea v-model="layer.catatan" placeholder="Catatan" class="rounded-lg border-stone-200 text-[12px]"></textarea><button :disabled="layer.processing || !geoValid" class="eq-btn-utama">{{ layer.processing ? 'Menyimpan...' : 'Simpan layer GeoJSON' }}</button></form></div><div class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-x-auto"><div class="px-5 py-4 border-b border-stone-100"><h3 class="font-bold text-[14px]">Layer terdaftar</h3><p class="text-[11px] text-stone-400">{{ (props.layers || []).length }} layer siap diaktifkan pada peta.</p></div><div class="divide-y divide-stone-100"><details v-for="item in props.layers || []" :key="item.id" class="p-5"><summary class="cursor-pointer flex items-center justify-between gap-3"><span><b class="text-[13px]">{{ item.nama }}</b><small class="block text-[10px] text-stone-400">{{ label(item.tipe) }} · {{ label(item.status) }} · <b>{{ angka(item.hektare, 2) }} ha</b> · {{ angka(item.panjang_km, 2) }} km<span v-if="item.tanggal_survey"> · survei {{ tanggal(item.tanggal_survey) }}</span></small></span><span class="w-4 h-4 rounded-full border" :style="{backgroundColor:item.warna}"></span></summary><div class="mt-3"><pre class="max-h-44 overflow-auto rounded-lg bg-stone-900 text-lime-200 p-3 text-[10px]">{{ item.geojson }}</pre><div class="flex justify-between items-center mt-2"><span class="text-[11px] text-stone-500">{{ geoSummary(item.geojson) }}</span><button v-if="isAdmin" type="button" class="text-red-600 text-[11px]" @click="hapusLayer(item)">Hapus</button></div></div></details><p v-if="!(props.layers || []).length" class="p-10 text-center text-[12px] text-stone-400">Belum ada layer. Tambahkan GeoJSON dari survey/GIS.</p></div></div></section>
    </template>
  </div>
</template>

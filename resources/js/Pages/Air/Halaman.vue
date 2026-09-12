<script setup lang="ts">
import { computed, reactive } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
const { dialog, tanya, minta, batal, lanjut } = useDialog();


/*
  Prop halaman diambil lewat propHalaman(), bukan defineProps.

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

  propHalaman() mengambil prop halaman apa adanya — termasuk yang
  dibagikan middleware — sehingga tidak ada daftar nama yang harus
  dirawat sejajar dengan controller-nya, dan tidak ada nama yang dapat
  hilang diam-diam. Dibacanya hidup: lihat resources/js/halaman.ts.
*/
const props = propHalaman();
const isAdmin = computed(() => Boolean(props.pengguna?.admin));

const judul: Record<string, string> = {
  dashboard: 'Water & Dewatering Management',
  catatan: 'Catatan Harian Penirisan',
  kolam: 'Kolam & Pompa',
};

const angka = (v: unknown, d = 0) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: d, minimumFractionDigits: d }).format(Number(v || 0));
const persen = (v: unknown) => `${angka(v, 1)}%`;
const label = (v: string) => String(v || '').replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
const jam = (v: unknown) => v === null || v === undefined ? 'tidak akan' : `${angka(v, 1)} jam`;

const tautan = computed(() => props.tautan || {});
const untuk = (pola: string | undefined, id: number | string) => String(pola || '').replace('__ID__', String(id));
const sibuk = reactive<Record<number, boolean>>({});

const kolam = useForm<any>({
  company_id: '', kode: '', nama: '', jenis: 'sump', lokasi: '',
  kapasitas_m3: 0, luas_tangkapan_ha: 0, koefisien_limpasan: 0.8,
  elevasi_luapan_m: '', status: 'aktif', pembersihan_terakhir: '', interval_bersih_hari: '', catatan: '',
});

const catatan = useForm<any>({
  company_id: '', water_sump_id: '', tanggal: new Date().toISOString().slice(0, 10),
  curah_hujan_mm: 0, level_m: '', volume_m3: 0, debit_masuk_m3: 0, debit_keluar_m3: 0,
  jam_pompa: 0, energi_kwh: '', ph: '', tss_mgl: '', fe_mgl: '', mn_mgl: '', catatan: '',
});

const pompa = useForm<any>({ ko_object_id: '', nama: '', kapasitas_m3_jam: 0, status: 'siap' });
const tindak = useForm<any>({ company_id: '', kode_pemicu: '', judul: '', prioritas: 'sedang', penanggung_jawab: '', target_selesai: '', uraian: '' });

const ditangani = computed(() => new Set(props.kodeDitangani || []));

function rentang() {
  router.get(window.location.pathname, { dari: props.dari, sampai: props.sampai }, { preserveState: true, replace: true });
}
function simpanKolam() { kolam.post(tautan.value.kolamSimpan, { preserveScroll: true, onSuccess: () => kolam.reset('kode', 'nama', 'lokasi', 'catatan') }); }
function simpanCatatan() { catatan.post(tautan.value.catatanSimpan, { preserveScroll: true, onSuccess: () => catatan.reset('curah_hujan_mm', 'level_m', 'ph', 'tss_mgl', 'fe_mgl', 'mn_mgl', 'catatan') }); }
function simpanPompa(s: any) { pompa.post(untuk(tautan.value.pompaSimpan, s.id), { preserveScroll: true, onSuccess: () => pompa.reset() }); }
function ubahPompa(p: any, status: string) { router.put(untuk(tautan.value.pompaUbah, p.id), { status }, { preserveScroll: true }); }
async function hapusKolam(s: any) { if (await tanya(`Hapus kolam ${s.kode}?`)) router.delete(untuk(tautan.value.kolamHapus, s.id), { preserveScroll: true }); }
async function hapusCatatan(c: any) { if (await tanya(`Hapus catatan ${c.tanggalLabel}?`)) router.delete(untuk(tautan.value.catatanHapus, c.id), { preserveScroll: true }); }

function ajukan(c: any) { sibuk[c.id] = true; router.post(untuk(tautan.value.ajukan, c.id), {}, { preserveScroll: true, onFinish: () => { sibuk[c.id] = false; } }); }
async function setujui(c: any) {
  if (!await tanya(`Setujui catatan ${c.tanggalLabel}? Setelah disetujui tidak dapat diubah.`)) return;
  sibuk[c.id] = true; router.post(untuk(tautan.value.setujui, c.id), {}, { preserveScroll: true, onFinish: () => { sibuk[c.id] = false; } });
}
async function tolak(c: any) {
  const alasan = await minta({ judul: `Tolak catatan ${c.tanggalLabel}?`, label: 'Alasan penolakan',
    jenis: 'panjang', min: 5, labelAksi: 'Tolak', nada: 'bahaya' });
  if (alasan === null) return;
  sibuk[c.id] = true; router.post(untuk(tautan.value.tolak, c.id), { alasan_tolak: alasan }, { preserveScroll: true, onFinish: () => { sibuk[c.id] = false; } });
}

function tindakDari(a: any) {
  tindak.kode_pemicu = a.kode; tindak.judul = a.judul;
  tindak.prioritas = a.level === 'tinggi' ? 'tinggi' : 'sedang'; tindak.uraian = a.saran || '';
  document.getElementById('form-tindak')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
function simpanTindak() { tindak.post(tautan.value.tindakSimpan, { preserveScroll: true, onSuccess: () => tindak.reset('kode_pemicu', 'judul', 'penanggung_jawab', 'target_selesai', 'uraian') }); }
function ubahTindak(t: any, status: string) { router.put(untuk(tautan.value.tindakUbah, t.id), { status }, { preserveScroll: true }); }

const warnaStatus: Record<string, string> = {
  draf: 'bg-stone-100 text-stone-600', diajukan: 'bg-amber-100 text-amber-700',
  disetujui: 'bg-emerald-100 text-emerald-700', ditolak: 'bg-red-100 text-red-700',
};
const warnaPompa: Record<string, string> = {
  siap: 'text-emerald-600', jalan: 'text-cam-lime-deep', rusak: 'text-red-600', perawatan: 'text-amber-600',
};
</script>

<template>
  <Head :title="judul[props.mode]" />

  <div class="max-w-[1400px] mx-auto space-y-5">
    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-[10px] font-bold uppercase tracking-[.16em] text-sky-600">Environment · Dewatering</p>
        
        <p class="text-[12px] text-stone-500 mt-1">Hujan, level kolam, pompa, dan kualitas air. Pompa memakai registri Keselamatan Operasi.</p>
      </div>
      <div class="flex gap-2">
        <input v-model="props.dari" type="date" class="rounded-lg border-stone-200 text-[11px]" aria-label="Tanggal mulai">
        <input v-model="props.sampai" type="date" class="rounded-lg border-stone-200 text-[11px]" aria-label="Tanggal akhir">
        <button class="eq-btn-lain" type="button" @click="rentang">Terapkan</button>
      </div>
    </section>

    <nav class="flex flex-wrap gap-2">
      <Link v-for="i in [['dashboard','Ringkasan',tautan.dashboard],['catatan','Catatan Harian',tautan.catatan],['kolam','Kolam & Pompa',tautan.kolam]]"
            :key="i[0]" :href="i[2] as string" class="rounded-full px-4 py-2 text-[11px] font-bold"
            :class="props.mode === i[0] ? 'bg-cam-ink text-white' : 'bg-white text-stone-500 border border-stone-200'">{{ i[1] }}</Link>
      <a :href="tautan.cetak" class="ml-auto rounded-full px-4 py-2 text-[11px] font-bold bg-white text-sky-700 border border-sky-200">Cetak laporan</a>
    </nav>

    <section v-if="(props.alerts || []).length" class="grid gap-3 md:grid-cols-2">
      <div v-for="a in props.alerts" :key="a.kode" class="rounded-2xl border p-4"
           :class="a.level === 'tinggi' ? 'border-red-100 bg-red-50' : 'border-amber-100 bg-amber-50'">
        <b class="text-[12px]" :class="a.level === 'tinggi' ? 'text-red-700' : 'text-amber-700'">{{ a.judul }}</b>
        <p class="text-[11px] text-stone-600 mt-1">{{ a.ket }}</p>
        <p v-if="a.saran" class="text-[11px] text-stone-700 mt-2 pt-2 border-t border-black/5"><span class="font-bold">Tindakan: </span>{{ a.saran }}</p>
        <div class="mt-2">
          <span v-if="ditangani.has(a.kode)" class="inline-block rounded-full bg-emerald-100 text-emerald-700 px-2 py-1 text-[10px] font-bold">Sedang ditangani</span>
          <button v-else type="button" class="text-[11px] font-bold text-sky-700 py-1.5" @click="tindakDari(a)">+ Buat tindak lanjut</button>
        </div>
      </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
      <article v-for="c in [
        { l: 'Curah hujan', v: `${angka(props.ringkas?.hujanTotal, 1)} mm`, c: 'text-sky-700' },
        { l: 'Hujan terbesar', v: `${angka(props.ringkas?.hujanMaks, 1)} mm`, c: 'text-cam-ink' },
        { l: 'Air dipompa', v: `${angka(props.ringkas?.keluar)} m³`, c: 'text-cam-ink' },
        { l: 'Debit rata', v: `${angka(props.ringkas?.debitPerJam)} m³/j`, c: 'text-violet-700' },
        { l: 'Pompa rusak', v: String(props.ringkas?.pompaRusak || 0), c: props.ringkas?.pompaRusak ? 'text-red-600' : 'text-emerald-600' },
      ]" :key="c.l" class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
        <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">{{ c.l }}</p>
        <p class="mt-2 text-xl font-extrabold" :class="c.c">{{ c.v }}</p>
      </article>
    </section>

    <template v-if="props.mode === 'dashboard'">
      <!--
        Daya tampung dinyatakan dalam milimeter hujan, bukan meter kubik:
        inilah satu-satunya angka pada halaman ini yang dapat langsung
        dibandingkan dengan ramalan cuaca esok hari.
      -->
      <section class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        <div v-for="s in props.kolam || []" :key="s.id" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <b class="text-[14px]">{{ s.kode }}</b>
              <p class="text-[11px] text-stone-400">{{ s.nama }} · {{ label(s.jenis) }}</p>
            </div>
            <span class="rounded-full px-2 py-1 text-[10px] font-bold"
                  :class="s.simulasi?.akanLimpah ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'">
              {{ s.simulasi?.akanLimpah ? 'Berisiko limpah' : 'Aman' }}
            </span>
          </div>

          <div class="mt-3 h-2 rounded-full bg-stone-100 overflow-hidden">
            <div class="h-full rounded-full" :class="s.terisiPersen >= 80 ? 'bg-red-500' : 'bg-sky-500'"
                 :style="{ width: `${Math.min(100, Number(s.terisiPersen || 0))}%` }"></div>
          </div>
          <p class="text-[11px] text-stone-500 mt-2">
            Terisi {{ persen(s.terisiPersen) }} — {{ angka(s.volume) }} dari {{ angka(s.kapasitas_m3) }} m³
          </p>

          <div class="mt-4 rounded-xl bg-sky-50 p-3">
            <small class="block text-[10px] text-sky-700 font-bold uppercase tracking-wide">Masih sanggup menahan</small>
            <b class="text-2xl font-extrabold text-sky-800">{{ angka(s.hujanTertampung, 0) }} mm</b>
            <small class="block text-[10px] text-stone-500 mt-1">hujan, tanpa memompa</small>
          </div>

          <div class="grid grid-cols-2 gap-2 mt-3 text-center">
            <div class="rounded-xl bg-stone-50 p-2">
              <small class="block text-[10px] text-stone-400">Sampai limpah</small>
              <b class="text-[12px]">{{ jam(s.simulasi?.jamSampaiLimpah) }}</b>
              <small class="block text-[9px] text-stone-400">pada hujan {{ props.opsi?.hujanRencana }} mm</small>
            </div>
            <div class="rounded-xl bg-stone-50 p-2">
              <small class="block text-[10px] text-stone-400">Sampai kosong</small>
              <b class="text-[12px]">{{ jam(s.simulasi?.jamSampaiKosong) }}</b>
              <small class="block text-[9px] text-stone-400">pompa {{ angka(s.pompaSiap) }} m³/j</small>
            </div>
          </div>

          <p v-if="s.simulasi?.pompaDibutuhkan > s.pompaSiap" class="mt-3 rounded-xl bg-amber-50 text-amber-800 p-3 text-[11px]">
            Perlu <b>{{ angka(s.simulasi.pompaDibutuhkan) }} m³/jam</b> untuk hujan {{ props.opsi?.hujanRencana }} mm;
            tersedia {{ angka(s.pompaSiap) }} m³/jam.
          </p>
          <p v-if="s.pompaRusak" class="mt-2 text-[11px] text-red-600">{{ s.pompaRusak }} pompa rusak.</p>
        </div>
        <p v-if="!(props.kolam || []).length" class="rounded-2xl bg-white border border-stone-100 p-10 text-center text-[12px] text-stone-400 md:col-span-2 xl:col-span-3">
          Belum ada kolam terdaftar.
        </p>
      </section>

      <section class="grid gap-5 xl:grid-cols-[1.15fr_.85fr]">
        <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
          <h3 class="font-bold text-[14px]">Kualitas air</h3>
          <p class="text-[11px] text-stone-400 mt-1">Rata-rata {{ props.mutu?.sampel || 0 }} sampel pada periode ini.</p>
          <div class="grid grid-cols-4 gap-3 mt-4 text-center">
            <div v-for="q in [
              { l: 'pH', v: props.mutu?.ph, s: '' },
              { l: 'TSS', v: props.mutu?.tss, s: 'mg/L' },
              { l: 'Fe', v: props.mutu?.fe, s: 'mg/L' },
              { l: 'Mn', v: props.mutu?.mn, s: 'mg/L' },
            ]" :key="q.l" class="rounded-xl bg-stone-50 p-3">
              <small class="block text-[10px] text-stone-400">{{ q.l }}</small>
              <b class="text-[14px]">{{ q.v === null || q.v === undefined ? '—' : angka(q.v, 2) }}</b>
              <small class="block text-[9px] text-stone-400">{{ q.s }}</small>
            </div>
          </div>
          <div class="mt-4 pt-4 border-t border-stone-100 grid grid-cols-3 gap-3 text-center">
            <div><small class="block text-[10px] text-stone-400">Catatan lengkap</small><b>{{ persen(props.kelengkapan?.persen) }}</b></div>
            <div><small class="block text-[10px] text-stone-400">Belum masuk</small><b :class="props.kelengkapan?.belumDilaporkan ? 'text-red-600' : ''">{{ props.kelengkapan?.belumDilaporkan || 0 }}</b></div>
            <div><small class="block text-[10px] text-stone-400">Menunggu tinjauan</small><b :class="props.kelengkapan?.menungguTinjauan ? 'text-amber-600' : ''">{{ props.kelengkapan?.menungguTinjauan || 0 }}</b></div>
          </div>
        </div>

        <div id="form-tindak" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
          <h3 class="font-bold text-[14px]">Tambah tindak lanjut</h3>
          <form class="grid gap-3 mt-4" @submit.prevent="simpanTindak">
            <input v-model="tindak.judul" required placeholder="Tindakan yang akan dikerjakan" class="rounded-lg border-stone-200 text-[12px]">
            <div class="grid grid-cols-2 gap-2">
              <select v-model="tindak.prioritas" class="rounded-lg border-stone-200 text-[12px]" aria-label="Prioritas">
                <option v-for="pr in props.opsi?.prioritasTindak || []" :key="pr" :value="pr">Prioritas {{ label(pr) }}</option>
              </select>
              <input v-model="tindak.target_selesai" type="date" class="rounded-lg border-stone-200 text-[12px]" aria-label="Tenggat">
            </div>
            <input v-model="tindak.penanggung_jawab" placeholder="Penanggung jawab" class="rounded-lg border-stone-200 text-[12px]">
            <textarea v-model="tindak.uraian" rows="2" placeholder="Uraian" class="rounded-lg border-stone-200 text-[12px]"></textarea>
            <p v-if="tindak.kode_pemicu" class="rounded-lg bg-stone-50 px-3 py-2 text-[11px] text-stone-500">Dari peringatan <b class="font-mono">{{ tindak.kode_pemicu }}</b></p>
            <button :disabled="tindak.processing" class="eq-btn-utama">{{ tindak.processing ? 'Menyimpan...' : 'Simpan tindak lanjut' }}</button>
          </form>
        </div>
      </section>

      <section v-if="(props.tindak || []).length" class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-x-auto">
        <div class="px-5 py-4 border-b border-stone-100"><h3 class="font-bold text-[14px]">Tindak lanjut</h3></div>
        <table class="min-w-full text-left text-[12px]">
          <thead><tr class="border-b border-stone-100 text-stone-400"><th class="px-5 py-3">Tindakan</th><th class="px-5 py-3">PIC</th><th class="px-5 py-3">Target</th><th class="px-5 py-3">Status</th></tr></thead>
          <tbody>
            <tr v-for="t in props.tindak" :key="t.id" class="border-b border-stone-50" :class="t.terlambat ? 'bg-red-50/40' : ''">
              <td class="px-5 py-3"><b>{{ t.judul }}</b><small v-if="t.kodePemicu" class="block mt-1"><span class="rounded bg-stone-100 text-stone-500 px-1.5 py-0.5 text-[9px] font-mono">{{ t.kodePemicu }}</span></small></td>
              <td class="px-5 py-3">{{ t.penanggung_jawab || '—' }}</td>
              <td class="px-5 py-3" :class="t.terlambat ? 'text-red-600 font-bold' : ''">{{ t.target_selesai || '—' }}</td>
              <td class="px-5 py-3">
                <select :value="t.status" class="rounded border-stone-200 text-[11px]" @change="ubahTindak(t, ($event.target as HTMLSelectElement).value)" aria-label="Status tindak lanjut">
                  <option v-for="st in props.opsi?.statusTindak || []" :key="st" :value="st">{{ label(st) }}</option>
                </select>
              </td>
            </tr>
          </tbody>
        </table>
      </section>
    </template>

    <template v-if="props.mode === 'catatan'">
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="font-bold text-[14px]">Catatan harian</h3>
        <p class="text-[11px] text-stone-400 mt-1">Satu kolam satu catatan per hari. Kualitas air boleh dikosongkan pada hari tanpa sampel.</p>
        <form class="grid gap-3 md:grid-cols-4 mt-4" @submit.prevent="simpanCatatan">
          <select v-model="catatan.water_sump_id" required class="rounded-lg border-stone-200 text-[12px]" aria-label="Kolam">
            <option value="">Pilih kolam</option>
            <option v-for="s in props.kolam || []" :key="s.id" :value="s.id">{{ s.kode }} — {{ s.nama }}</option>
          </select>
          <input v-model="catatan.tanggal" type="date" required class="rounded-lg border-stone-200 text-[12px]" aria-label="Tanggal">
          <input v-model.number="catatan.curah_hujan_mm" type="number" step="any" min="0" placeholder="Curah hujan (mm)" required class="rounded-lg border-stone-200 text-[12px]">
          <input v-model.number="catatan.level_m" type="number" step="any" placeholder="Level (m)" class="rounded-lg border-stone-200 text-[12px]">
          <input v-model.number="catatan.volume_m3" type="number" step="any" min="0" placeholder="Volume (m³)" required class="rounded-lg border-stone-200 text-[12px]">
          <input v-model.number="catatan.debit_masuk_m3" type="number" step="any" min="0" placeholder="Debit masuk (m³)" required class="rounded-lg border-stone-200 text-[12px]">
          <input v-model.number="catatan.debit_keluar_m3" type="number" step="any" min="0" placeholder="Debit keluar (m³)" required class="rounded-lg border-stone-200 text-[12px]">
          <input v-model.number="catatan.jam_pompa" type="number" step="any" min="0" max="24" placeholder="Jam pompa" required class="rounded-lg border-stone-200 text-[12px]">
          <input v-model.number="catatan.energi_kwh" type="number" step="any" min="0" placeholder="Energi (kWh)" class="rounded-lg border-stone-200 text-[12px]">
          <input v-model.number="catatan.ph" type="number" step="any" min="0" max="14" placeholder="pH" class="rounded-lg border-stone-200 text-[12px]">
          <input v-model.number="catatan.tss_mgl" type="number" step="any" min="0" placeholder="TSS (mg/L)" class="rounded-lg border-stone-200 text-[12px]">
          <input v-model.number="catatan.fe_mgl" type="number" step="any" min="0" placeholder="Fe (mg/L)" class="rounded-lg border-stone-200 text-[12px]">
          <input v-model.number="catatan.mn_mgl" type="number" step="any" min="0" placeholder="Mn (mg/L)" class="rounded-lg border-stone-200 text-[12px]">
          <textarea v-model="catatan.catatan" placeholder="Catatan lapangan" class="rounded-lg border-stone-200 text-[12px] md:col-span-3"></textarea>
          <button :disabled="catatan.processing" class="eq-btn-utama md:col-span-4">{{ catatan.processing ? 'Menyimpan...' : 'Simpan catatan' }}</button>
        </form>
        <p v-if="catatan.errors.tanggal" class="text-[11px] text-red-600 mt-2">{{ catatan.errors.tanggal }}</p>
      </section>

      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-x-auto">
        <table class="min-w-full text-left text-[12px]">
          <thead><tr class="border-b border-stone-100 text-stone-400">
            <th class="px-5 py-3">Tanggal / Kolam</th><th class="px-5 py-3 text-right">Hujan</th>
            <th class="px-5 py-3 text-right">Volume</th><th class="px-5 py-3 text-right">Keluar</th>
            <th class="px-5 py-3 text-right">m³/jam</th><th class="px-5 py-3">Mutu</th>
            <th class="px-5 py-3">Status</th><th></th>
          </tr></thead>
          <tbody>
            <tr v-for="c in props.catatan || []" :key="c.id" class="border-b border-stone-50">
              <td class="px-5 py-3"><b>{{ c.tanggalLabel }}</b><small class="block text-[10px] text-stone-400">{{ c.sump }}</small></td>
              <td class="px-5 py-3 text-right">{{ angka(c.curah_hujan_mm, 1) }} mm</td>
              <td class="px-5 py-3 text-right">{{ angka(c.volume_m3) }}</td>
              <td class="px-5 py-3 text-right">{{ angka(c.debit_keluar_m3) }}</td>
              <td class="px-5 py-3 text-right">{{ c.debitPerJam === null ? '—' : angka(c.debitPerJam) }}</td>
              <td class="px-5 py-3 text-[10px]">
                <template v-if="c.adaSampel">pH {{ c.ph ?? '—' }} · TSS {{ c.tss_mgl ?? '—' }}</template>
                <span v-else class="text-stone-400">tanpa sampel</span>
              </td>
              <td class="px-5 py-3">
                <span class="rounded-full px-2 py-1 text-[10px] font-bold" :class="warnaStatus[c.status] || 'bg-stone-100'">{{ c.statusLabel }}</span>
                <small v-if="c.alur?.alasanTolak" class="block text-[10px] text-red-600 mt-1">{{ c.alur.alasanTolak }}</small>
              </td>
              <td class="px-5 py-3 text-right whitespace-nowrap">
                <button v-if="c.alur?.dapatDiajukan" :disabled="sibuk[c.id]" type="button" class="text-[11px] font-bold text-sky-700 disabled:opacity-40" @click="ajukan(c)">Ajukan</button>
                <template v-if="c.alur?.dapatDitinjau">
                  <button :disabled="sibuk[c.id]" type="button" class="text-[11px] font-bold text-emerald-600 disabled:opacity-40" @click="setujui(c)">Setujui</button>
                  <button :disabled="sibuk[c.id]" type="button" class="ml-3 text-[11px] font-bold text-amber-600 disabled:opacity-40" @click="tolak(c)">Tolak</button>
                </template>
                <button v-if="isAdmin && c.status !== 'disetujui'" type="button" class="ml-3 text-red-600 text-[11px]" @click="hapusCatatan(c)">Hapus</button>
              </td>
            </tr>
            <tr v-if="!(props.catatan || []).length"><td colspan="8" class="px-5 py-10 text-center text-stone-400">Belum ada catatan pada periode ini.</td></tr>
          </tbody>
        </table>
      </section>
    </template>

    <template v-if="props.mode === 'kolam'">
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="font-bold text-[14px]">Daftarkan kolam</h3>
        <p class="text-[11px] text-stone-400 mt-1">Luas tangkapan dan koefisien limpasan menentukan seluruh perkiraan luapan.</p>
        <form class="grid gap-3 md:grid-cols-4 mt-4" @submit.prevent="simpanKolam">
          <input v-model="kolam.kode" required placeholder="Kode, mis. SUMP-01" class="rounded-lg border-stone-200 text-[12px]">
          <input v-model="kolam.nama" required placeholder="Nama kolam" class="rounded-lg border-stone-200 text-[12px]">
          <select v-model="kolam.jenis" class="rounded-lg border-stone-200 text-[12px]" aria-label="Jenis">
            <option v-for="j in props.opsi?.jenis || []" :key="j" :value="j">{{ label(j) }}</option>
          </select>
          <input v-model="kolam.lokasi" placeholder="Lokasi" class="rounded-lg border-stone-200 text-[12px]">
          <input v-model.number="kolam.kapasitas_m3" type="number" step="any" min="0" required placeholder="Kapasitas (m³)" class="rounded-lg border-stone-200 text-[12px]">
          <input v-model.number="kolam.luas_tangkapan_ha" type="number" step="any" min="0" required placeholder="Luas tangkapan (ha)" class="rounded-lg border-stone-200 text-[12px]">
          <input v-model.number="kolam.koefisien_limpasan" type="number" step="0.01" min="0.01" max="1" required placeholder="Koefisien limpasan" class="rounded-lg border-stone-200 text-[12px]">
          <select v-model="kolam.status" class="rounded-lg border-stone-200 text-[12px]" aria-label="Status">
            <option v-for="st in props.opsi?.statusKolam || []" :key="st" :value="st">{{ label(st) }}</option>
          </select>
          <input v-model="kolam.pembersihan_terakhir" type="date" class="rounded-lg border-stone-200 text-[12px]" title="Pembersihan terakhir">
          <input v-model.number="kolam.interval_bersih_hari" type="number" min="1" placeholder="Interval bersih (hari)" class="rounded-lg border-stone-200 text-[12px]">
          <textarea v-model="kolam.catatan" placeholder="Catatan" class="rounded-lg border-stone-200 text-[12px] md:col-span-2"></textarea>
          <button :disabled="kolam.processing" class="eq-btn-utama md:col-span-4">{{ kolam.processing ? 'Menyimpan...' : 'Simpan kolam' }}</button>
        </form>
      </section>

      <section v-for="s in props.kolam || []" :key="s.id" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <div class="flex items-start justify-between gap-3">
          <div>
            <b class="text-[14px]">{{ s.kode }} — {{ s.nama }}</b>
            <p class="text-[11px] text-stone-400">
              {{ label(s.jenis) }} · {{ angka(s.kapasitas_m3) }} m³ · tangkapan {{ angka(s.luas_tangkapan_ha, 2) }} ha · C {{ s.koefisien }}
              <span v-if="s.sisaHariBersih !== null"> · bersih {{ s.sisaHariBersih < 0 ? `terlewat ${-s.sisaHariBersih} hari` : `${s.sisaHariBersih} hari lagi` }}</span>
            </p>
          </div>
          <button v-if="isAdmin" type="button" class="text-red-600 text-[11px]" @click="hapusKolam(s)">Hapus</button>
        </div>

        <div class="mt-4 grid gap-2">
          <div v-for="p in s.pumps || []" :key="p.id" class="flex items-center justify-between gap-3 rounded-xl bg-stone-50 px-3 py-2">
            <span class="text-[12px]"><b>{{ p.label }}</b> · {{ angka(p.kapasitas) }} m³/jam</span>
            <select :value="p.status" class="rounded border-stone-200 text-[11px]" :class="warnaPompa[p.status]" @change="ubahPompa(p, ($event.target as HTMLSelectElement).value)" aria-label="Status pompa">
              <option v-for="st in props.opsi?.statusPompa || []" :key="st" :value="st">{{ label(st) }}</option>
            </select>
          </div>
          <p v-if="!(s.pumps || []).length" class="text-[11px] text-stone-400">Belum ada pompa pada kolam ini.</p>
        </div>

        <form class="grid gap-2 md:grid-cols-4 mt-3" @submit.prevent="simpanPompa(s)">
          <select v-model="pompa.ko_object_id" class="rounded-lg border-stone-200 text-[12px]" aria-label="Objek KO">
            <option value="">Alat dari registri KO</option>
            <option v-for="o in props.objekOpsi || []" :key="o.id" :value="o.id">{{ o.kode }} — {{ o.nama }}</option>
          </select>
          <input v-model="pompa.nama" placeholder="atau nama pompa" class="rounded-lg border-stone-200 text-[12px]">
          <input v-model.number="pompa.kapasitas_m3_jam" type="number" step="any" min="0" required placeholder="m³/jam" class="rounded-lg border-stone-200 text-[12px]">
          <button :disabled="pompa.processing" class="eq-btn-lain">Tambah pompa</button>
        </form>
      </section>
    </template>
  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>

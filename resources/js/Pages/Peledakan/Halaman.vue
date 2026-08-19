<script setup lang="ts">
import { computed, reactive } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';

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
  dashboard: 'Pengeboran & Peledakan',
  rencana: 'Rencana Peledakan',
  titik: 'Titik Terlindung',
  getaran: 'Getaran Terukur',
};

const angka = (v: unknown, d = 0) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: d, minimumFractionDigits: d }).format(Number(v || 0));
const label = (v: string) => String(v || '').replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());

const tautan = computed(() => props.tautan || {});
const untuk = (pola: string | undefined, id: number | string) => String(pola || '').replace('__ID__', String(id));
const sibuk = reactive<Record<number, boolean>>({});

const rencana = useForm<any>({
  company_id: '', kode: '', lokasi: '', tanggal_rencana: new Date().toISOString().slice(0, 10),
  jenis_batuan: '', faktor_batuan: 7, diameter_lubang_mm: 150, burden_m: 4, spasi_m: 5,
  kedalaman_m: 11, subdrill_m: 1, stemming_m: 3.2, tinggi_jenjang_m: 10,
  jumlah_lubang: 40, pola: 'selang-seling', bahan_peledak: 'ANFO', kekuatan_relatif: 100,
  isi_per_lubang_kg: 60, isi_per_tunda_kg: 60, catatan: '',
});
const titik = useForm<any>({ company_id: '', kode: '', nama: '', jenis: 'permukiman', lokasi: '', ppv_ambang_mm_s: '', acuan_ambang: '', catatan: '' });
const hasil = useForm<any>({ company_id: '', waktu_ledak: '', volume_bcm: 0, ada_misfire: false, misfire_lubang: '', ada_flyrock: false, flyrock_jarak_m: '', backbreak_m: '', bongkah_persen: '', kejadian: '', catatan: '' });
const ukur = useForm<any>({ company_id: '', ledak_titik_id: '', jarak_m: '', ppv_mm_s: '', frekuensi_hz: '', airblast_db: '', alat_ukur: '' });
const tindak = useForm<any>({ company_id: '', kode_pemicu: '', judul: '', prioritas: 'sedang', penanggung_jawab: '', target_selesai: '', uraian: '' });

const ditangani = computed(() => new Set(props.kodeDitangani || []));
const dipilih = reactive<{ id: number | null }>({ id: null });

function rentang() {
  router.get(window.location.pathname, { dari: props.dari, sampai: props.sampai }, { preserveState: true, replace: true });
}
function simpanRencana() { rencana.post(tautan.value.rencanaSimpan, { preserveScroll: true, onSuccess: () => rencana.reset('kode', 'lokasi', 'catatan') }); }
function hapusRencana(r: any) { if (window.confirm(`Hapus rencana ${r.kode}?`)) router.delete(untuk(tautan.value.rencanaHapus, r.id), { preserveScroll: true }); }
function simpanTitik() { titik.post(tautan.value.titikSimpan, { preserveScroll: true, onSuccess: () => titik.reset('kode', 'nama', 'lokasi', 'ppv_ambang_mm_s', 'acuan_ambang') }); }
function hapusTitik(t: any) { if (window.confirm(`Hapus titik ${t.kode}?`)) router.delete(untuk(tautan.value.titikHapus, t.id), { preserveScroll: true }); }
function simpanHasil(r: any) { hasil.post(untuk(tautan.value.hasilSimpan, r.id), { preserveScroll: true }); }
function simpanUkur(r: any) { ukur.post(untuk(tautan.value.ukurSimpan, r.id), { preserveScroll: true, onSuccess: () => ukur.reset('ppv_mm_s', 'frekuensi_hz', 'airblast_db') }); }
function hapusUkur(u: any) { router.delete(untuk(tautan.value.ukurHapus, u.id), { preserveScroll: true }); }

function alur(pola: string, baris: any, muatan: Record<string, any> = {}) {
  sibuk[baris.id] = true;
  router.post(untuk(pola, baris.id), muatan, { preserveScroll: true, onFinish: () => { sibuk[baris.id] = false; } });
}
function setujui(pola: string, baris: any, apa: string) {
  if (!window.confirm(`Setujui ${apa}? Setelah disetujui tidak dapat diubah.`)) return;
  alur(pola, baris);
}
function tolak(pola: string, baris: any, apa: string) {
  const a = window.prompt(`Alasan penolakan ${apa}:`);
  if (a === null) return;
  alur(pola, baris, { alasan_tolak: a });
}

function tindakDari(a: any) {
  tindak.kode_pemicu = a.kode; tindak.judul = a.judul;
  tindak.prioritas = a.level === 'tinggi' ? 'tinggi' : 'sedang'; tindak.uraian = a.saran || '';
  document.getElementById('form-tindak')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
function simpanTindak() { tindak.post(tautan.value.tindakSimpan, { preserveScroll: true, onSuccess: () => tindak.reset('kode_pemicu', 'judul', 'penanggung_jawab', 'target_selesai', 'uraian') }); }
function ubahTindak(t: any, s: string) { router.put(untuk(tautan.value.tindakUbah, t.id), { status: s }, { preserveScroll: true }); }

const warnaStatus: Record<string, string> = {
  draf: 'bg-stone-100 text-stone-600', diajukan: 'bg-amber-100 text-amber-700',
  disetujui: 'bg-emerald-100 text-emerald-700', ditolak: 'bg-red-100 text-red-700',
};
</script>

<template>
  <Head :title="judul[props.mode]" />

  <div class="max-w-[1400px] mx-auto space-y-5">
    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-[10px] font-bold uppercase tracking-[.16em] text-cam-orange">Engineering · Drill &amp; Blast</p>
        <h2 class="text-2xl font-extrabold tracking-tight text-stone-800">{{ judul[props.mode] }}</h2>
        <p class="text-[12px] text-stone-500 mt-1">Rancangan, getaran, dan hasil peledakan dalam satu alur persetujuan.</p>
      </div>
      <div class="flex gap-2">
        <input v-model="props.dari" type="date" class="rounded-lg border-stone-200 text-[11px]" aria-label="Tanggal mulai">
        <input v-model="props.sampai" type="date" class="rounded-lg border-stone-200 text-[11px]" aria-label="Tanggal akhir">
        <button class="eq-btn-lain" type="button" @click="rentang">Terapkan</button>
      </div>
    </section>

    <!--
      Tetapan penjalaran getaran menentukan seluruh perkiraan di halaman
      ini. Selama belum dikalibrasi dari pengukuran situs sendiri, itu
      harus dinyatakan — tetapan umum meleset jauh antar jenis batuan,
      dan meleset ke arah yang tidak dapat ditebak.
    -->
    <section class="rounded-2xl border px-4 py-3"
             :class="props.tetapan?.dapatDipakai ? 'border-emerald-100 bg-emerald-50' : 'border-cam-orange/30 bg-cam-orange-soft'">
      <p class="text-[11.5px] text-stone-700 leading-relaxed">
        <template v-if="props.tetapan?.dapatDipakai">
          <b>Tetapan situs terkalibrasi.</b> K = {{ angka(props.tetapan.k, 0) }}, β = {{ angka(props.tetapan.beta, 2) }}
          dari {{ props.tetapan.n }} pengukuran (R² {{ angka(props.tetapan.r2, 2) }}).
        </template>
        <template v-else>
          <b>Memakai tetapan umum.</b> {{ props.tetapan?.alasan }}
          Pasang alat ukur pada beberapa peledakan berikutnya — {{ props.opsi?.minKalibrasi }} pengukuran
          sudah cukup untuk mulai mengalibrasi tetapan situs sendiri.
        </template>
      </p>
    </section>

    <section v-if="(props.alerts || []).length" class="grid gap-3 md:grid-cols-2">
      <div v-for="a in props.alerts" :key="a.kode" class="rounded-2xl border p-4"
           :class="a.level === 'tinggi' ? 'border-red-100 bg-red-50' : 'border-amber-100 bg-amber-50'">
        <b class="text-[12px]" :class="a.level === 'tinggi' ? 'text-red-700' : 'text-amber-700'">{{ a.judul }}</b>
        <p class="text-[11px] text-stone-600 mt-1">{{ a.ket }}</p>
        <p v-if="a.saran" class="text-[11px] text-stone-700 mt-2 pt-2 border-t border-black/5"><span class="font-bold">Tindakan: </span>{{ a.saran }}</p>
        <div class="mt-2">
          <span v-if="ditangani.has(a.kode)" class="inline-block rounded-full bg-emerald-100 text-emerald-700 px-2 py-1 text-[10px] font-bold">Sedang ditangani</span>
          <button v-else type="button" class="text-[11px] font-bold text-cam-orange-dark py-1.5" @click="tindakDari(a)">+ Buat tindak lanjut</button>
        </div>
      </div>
    </section>

    <nav class="flex flex-wrap gap-2">
      <Link v-for="i in [['dashboard','Ringkasan',tautan.dashboard],['rencana','Rencana',tautan.rencana],['titik','Titik Terlindung',tautan.titik],['getaran','Getaran',tautan.getaran]]"
            :key="i[0]" :href="i[2] as string" class="rounded-full px-4 py-2 text-[11px] font-bold"
            :class="props.mode === i[0] ? 'bg-cam-ink text-white' : 'bg-white text-stone-500 border border-stone-200'">{{ i[1] }}</Link>
      <a :href="tautan.cetak" class="ml-auto rounded-full px-4 py-2 text-[11px] font-bold bg-white text-cam-orange border border-cam-orange/40">Cetak laporan</a>
    </nav>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
      <article v-for="c in [
        { l: 'Rencana', v: String(props.ringkas?.rencana || 0), c: 'text-cam-ink' },
        { l: 'Terlaksana', v: String(props.ringkas?.terlaksana || 0), c: 'text-cam-ink' },
        { l: 'Volume', v: `${angka(props.ringkas?.volume)} bcm`, c: 'text-sky-700' },
        { l: 'Powder factor', v: props.ringkas?.pf === null ? '—' : `${angka(props.ringkas?.pf, 3)} kg/bcm`, c: 'text-violet-700' },
        { l: 'Misfire', v: String(props.ringkas?.misfire || 0), c: props.ringkas?.misfire ? 'text-red-600' : 'text-emerald-600' },
        { l: 'Getaran lewat', v: String(props.ringkas?.getaranLewat || 0), c: props.ringkas?.getaranLewat ? 'text-red-600' : 'text-emerald-600' },
      ]" :key="c.l" class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
        <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">{{ c.l }}</p>
        <p class="mt-2 text-lg font-extrabold" :class="c.c">{{ c.v }}</p>
      </article>
    </section>

    <!-- ═══════════ RINGKASAN ═══════════ -->
    <template v-if="props.mode === 'dashboard'">
      <section class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        <div v-for="r in props.rencana || []" :key="r.id" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <b class="text-[14px]">{{ r.kode }}</b>
              <p class="text-[11px] text-stone-400">{{ r.lokasi || '—' }} · {{ r.tanggalLabel }}</p>
            </div>
            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold" :class="warnaStatus[r.status]">{{ r.statusLabel }}</span>
          </div>

          <div class="mt-3 grid grid-cols-3 gap-2 text-[11.5px]">
            <div><p class="text-stone-400">PF rencana</p><p class="font-bold">{{ r.pfRencana === null ? '—' : angka(r.pfRencana, 3) }}</p></div>
            <div><p class="text-stone-400">PF nyata</p><p class="font-bold">{{ r.pfNyata === null ? '—' : angka(r.pfNyata, 3) }}</p></div>
            <div><p class="text-stone-400">Fragmen X50</p><p class="font-bold">{{ r.fragmentasi === null ? '—' : `${angka(r.fragmentasi, 1)} cm` }}</p></div>
          </div>

          <!--
            Radius lemparan adalah jarak terjauh yang MUNGKIN dicapai
            serpihan pada keadaan terburuk, bukan yang biasa terjadi —
            dan justru karena itu ia dipakai sebagai dasar pengamanan.
          -->
          <div class="mt-3 rounded-xl bg-stone-50 px-3 py-2.5">
            <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">Radius pengamanan</p>
            <p class="text-[13px] font-extrabold mt-0.5">{{ r.radiusLemparan === null ? '—' : `${angka(r.radiusLemparan)} m` }}</p>
            <p class="text-[10.5px] text-stone-500 mt-0.5">Lemparan maksimum secara teori pada lubang {{ angka(r.geometri?.diameter) }} mm.</p>
          </div>

          <div v-if="(r.getaran || []).length" class="mt-3 space-y-1.5">
            <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">Perkiraan getaran</p>
            <div v-for="g in r.getaran" :key="g.titik" class="flex items-center justify-between text-[11.5px]"
                 :class="g.lampaui ? 'text-red-600 font-bold' : 'text-stone-600'">
              <span>{{ g.titik }} · {{ angka(g.jarak_m) }} m</span>
              <span>{{ g.perkiraan === null ? '—' : `${angka(g.perkiraan, 1)} / ${angka(g.ambang, 1)} mm/s` }}</span>
            </div>
          </div>

          <div v-if="(r.penyimpangan || []).length" class="mt-3 rounded-xl bg-red-50 border border-red-100 px-3 py-2.5">
            <p class="text-[10px] uppercase tracking-wide font-bold text-red-600">Geometri</p>
            <p v-for="s in r.penyimpangan" :key="s.hal" class="text-[11px] text-stone-700 mt-1">{{ s.hal }}: <b>{{ s.nilai }}</b> — {{ s.anjuran }}</p>
          </div>

          <div v-if="r.hasil" class="mt-3 pt-3 border-t border-stone-100 text-[11.5px]">
            <p class="text-stone-400">Hasil · {{ r.hasil.waktuLabel }}</p>
            <p>{{ angka(r.hasil.volume_bcm) }} bcm
              <span v-if="r.hasil.ada_misfire" class="ml-2 rounded-full bg-red-100 text-red-700 px-2 py-0.5 text-[10px] font-bold">Misfire</span>
              <span v-if="r.hasil.ada_flyrock" class="ml-1 rounded-full bg-red-100 text-red-700 px-2 py-0.5 text-[10px] font-bold">Flyrock</span>
            </p>
          </div>
        </div>
      </section>
    </template>

    <!-- ═══════════ RENCANA ═══════════ -->
    <template v-if="props.mode === 'rencana'">
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="font-bold text-[14px]">Susun rencana peledakan</h3>
        <p class="text-[11.5px] text-stone-500 mt-1">
          <b>Isi per tundaan</b>, bukan isi seluruh peledakan — angka itulah yang menentukan getaran.
          Rencana yang disetujui berarti boleh diledakkan; hasil hanya dapat dicatat sesudahnya.
        </p>
        <form class="mt-3 grid gap-3 md:grid-cols-4" @submit.prevent="simpanRencana">
          <label class="text-[11px] font-bold text-stone-500">Kode
            <input v-model="rencana.kode" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Lokasi
            <input v-model="rencana.lokasi" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Tanggal
            <input v-model="rencana.tanggal_rencana" type="date" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Jenis batuan
            <input v-model="rencana.jenis_batuan" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>

          <p class="md:col-span-4 text-[10px] font-bold uppercase tracking-wide text-stone-400 pt-1">Geometri</p>
          <label class="text-[11px] font-bold text-stone-500">Diameter lubang (mm)
            <input v-model="rencana.diameter_lubang_mm" type="number" step="0.1" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Burden (m)
            <input v-model="rencana.burden_m" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Spasi (m)
            <input v-model="rencana.spasi_m" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Tinggi jenjang (m)
            <input v-model="rencana.tinggi_jenjang_m" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Kedalaman (m)
            <input v-model="rencana.kedalaman_m" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Subdrill (m)
            <input v-model="rencana.subdrill_m" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Stemming (m)
            <input v-model="rencana.stemming_m" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Jumlah lubang
            <input v-model="rencana.jumlah_lubang" type="number" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>

          <p class="md:col-span-4 text-[10px] font-bold uppercase tracking-wide text-stone-400 pt-1">Bahan peledak</p>
          <label class="text-[11px] font-bold text-stone-500">Jenis
            <input v-model="rencana.bahan_peledak" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Kekuatan relatif (ANFO=100)
            <input v-model="rencana.kekuatan_relatif" type="number" step="0.1" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Isi per lubang (kg)
            <input v-model="rencana.isi_per_lubang_kg" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Isi per tundaan (kg)
            <input v-model="rencana.isi_per_tunda_kg" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>

          <label class="text-[11px] font-bold text-stone-500">Faktor batuan
            <input v-model="rencana.faktor_batuan" type="number" step="0.1" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Pola
            <select v-model="rencana.pola" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
              <option v-for="p in props.opsi?.pola || []" :key="p" :value="p">{{ label(p) }}</option>
            </select></label>
          <label class="text-[11px] font-bold text-stone-500">Catatan
            <input v-model="rencana.catatan" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <button class="eq-btn-utama self-end" :disabled="rencana.processing">Simpan draf</button>
        </form>
        <p v-for="(e, k) in rencana.errors" :key="k" class="mt-2 text-[11px] text-red-600">{{ e }}</p>
      </section>

      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-[11.5px]">
            <thead class="bg-stone-50 text-left text-stone-400 uppercase text-[10px] tracking-wide">
              <tr>
                <th class="px-5 py-2.5">Kode</th><th class="px-5 py-2.5">Tanggal</th>
                <th class="px-5 py-2.5 text-right">Lubang</th><th class="px-5 py-2.5 text-right">Per tundaan</th>
                <th class="px-5 py-2.5 text-right">PF</th><th class="px-5 py-2.5">Status</th>
                <th class="px-5 py-2.5 text-right">Alur</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in props.rencana || []" :key="r.id" class="border-b border-stone-50">
                <td class="px-5 py-3 font-semibold">
                  <button type="button" class="underline decoration-dotted" @click="dipilih.id = dipilih.id === r.id ? null : r.id">{{ r.kode }}</button>
                </td>
                <td class="px-5 py-3">{{ r.tanggalLabel }}</td>
                <td class="px-5 py-3 text-right">{{ r.geometri?.lubang }}</td>
                <td class="px-5 py-3 text-right">{{ angka(r.bahan?.perTunda, 1) }} kg</td>
                <td class="px-5 py-3 text-right">{{ r.pfRencana === null ? '—' : angka(r.pfRencana, 3) }}</td>
                <td class="px-5 py-3">
                  <span class="rounded-full px-2 py-0.5 text-[10px] font-bold" :class="warnaStatus[r.status]">{{ r.statusLabel }}</span>
                  <p v-if="r.alur?.alasanTolak" class="text-[10.5px] text-red-600 mt-1">{{ r.alur.alasanTolak }}</p>
                </td>
                <td class="px-5 py-3 text-right whitespace-nowrap">
                  <button v-if="r.alur?.dapatDiajukan" type="button" class="text-[11px] font-bold text-cam-orange-dark disabled:opacity-40"
                          :disabled="sibuk[r.id]" @click="alur(tautan.rencanaAjukan, r)">Ajukan</button>
                  <template v-if="r.alur?.dapatDitinjau">
                    <button type="button" class="ml-3 text-[11px] font-bold text-emerald-700 disabled:opacity-40"
                            :disabled="sibuk[r.id]" @click="setujui(tautan.rencanaSetujui, r, `rencana ${r.kode}`)">Setujui</button>
                    <button type="button" class="ml-3 text-[11px] font-bold text-red-600 disabled:opacity-40"
                            :disabled="sibuk[r.id]" @click="tolak(tautan.rencanaTolak, r, `rencana ${r.kode}`)">Tolak</button>
                  </template>
                  <button v-if="isAdmin && r.status !== 'disetujui'" type="button" class="ml-3 text-[11px] font-bold text-stone-400" @click="hapusRencana(r)">Hapus</button>
                </td>
              </tr>
              <tr v-if="!(props.rencana || []).length"><td colspan="7" class="px-5 py-8 text-center text-stone-400">Belum ada rencana pada rentang ini.</td></tr>
            </tbody>
          </table>
        </div>

        <div v-for="r in (props.rencana || []).filter((x: any) => x.id === dipilih.id)" :key="`d-${r.id}`" class="border-t border-stone-100 p-5 bg-stone-50/60">
          <h4 class="font-bold text-[13px]">Catat hasil — {{ r.kode }}</h4>
          <p v-if="r.status !== 'disetujui'" class="text-[11.5px] text-amber-700 mt-1">
            Rencana ini belum disetujui. Hasil hanya dapat dicatat pada rencana yang izinnya sudah keluar.
          </p>
          <form v-else class="mt-3 grid gap-3 md:grid-cols-4" @submit.prevent="simpanHasil(r)">
            <label class="text-[11px] font-bold text-stone-500">Waktu ledak
              <input v-model="hasil.waktu_ledak" type="datetime-local" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
            <label class="text-[11px] font-bold text-stone-500">Volume (bcm)
              <input v-model="hasil.volume_bcm" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
            <label class="text-[11px] font-bold text-stone-500">Lubang misfire
              <input v-model="hasil.misfire_lubang" type="number" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
            <label class="text-[11px] font-bold text-stone-500">Jarak flyrock (m)
              <input v-model="hasil.flyrock_jarak_m" type="number" step="0.1" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
            <label class="md:col-span-3 text-[11px] font-bold text-stone-500">Kejadian
              <input v-model="hasil.kejadian" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
            <button class="eq-btn-utama self-end" :disabled="hasil.processing">Simpan hasil</button>
          </form>
          <p v-for="(e, k) in hasil.errors" :key="k" class="mt-2 text-[11px] text-red-600">{{ e }}</p>

          <h4 class="font-bold text-[13px] mt-5">Pengukuran getaran</h4>
          <form class="mt-2 grid gap-2 md:grid-cols-6" @submit.prevent="simpanUkur(r)">
            <select v-model="ukur.ledak_titik_id" class="rounded-lg border-stone-200 text-[11px]" required aria-label="Titik ukur">
              <option value="">— titik —</option>
              <option v-for="t in props.titik || []" :key="t.id" :value="t.id">{{ t.nama }}</option>
            </select>
            <input v-model="ukur.jarak_m" type="number" step="0.1" placeholder="Jarak m" class="rounded-lg border-stone-200 text-[11px]" required>
            <input v-model="ukur.ppv_mm_s" type="number" step="0.0001" placeholder="PPV mm/s" class="rounded-lg border-stone-200 text-[11px]" required>
            <input v-model="ukur.frekuensi_hz" type="number" step="0.1" placeholder="Hz" class="rounded-lg border-stone-200 text-[11px]">
            <input v-model="ukur.alat_ukur" type="text" placeholder="Alat" class="rounded-lg border-stone-200 text-[11px]">
            <button class="eq-btn-lain text-[11px]" :disabled="ukur.processing">+ Ukur</button>
          </form>
          <table v-if="(r.ukur || []).length" class="w-full mt-3 text-[11.5px]">
            <tbody>
              <tr v-for="u in r.ukur" :key="u.id" class="border-t border-stone-100">
                <td class="py-2">{{ u.titik }}</td>
                <td class="py-2">{{ angka(u.jarak_m) }} m</td>
                <td class="py-2 font-bold" :class="u.melampaui ? 'text-red-600' : ''">{{ angka(u.ppv, 2) }} mm/s</td>
                <td class="py-2 text-stone-400">ambang {{ angka(u.ambang, 1) }}</td>
                <td class="py-2 text-right"><button v-if="isAdmin" type="button" class="text-[11px] text-stone-400" @click="hapusUkur(u)">Hapus</button></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>

    <!-- ═══════════ TITIK TERLINDUNG ═══════════ -->
    <template v-if="props.mode === 'titik'">
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="font-bold text-[14px]">Daftarkan titik terlindung</h3>
        <p class="text-[11.5px] text-stone-500 mt-1">
          Ambang diisikan <b>dari dokumen izin</b> beserta dasar hukumnya. Ambang bawaan menurut jenis bangunan
          ({{ props.opsi?.ambangBawaan?.peka }} / {{ props.opsi?.ambangBawaan?.permukiman }} /
          {{ props.opsi?.ambangBawaan?.industri }} mm/s) hanya dipakai selama izinnya belum diisi — dan itu bukan ambang yang mengikat.
        </p>
        <form class="mt-3 grid gap-3 md:grid-cols-4" @submit.prevent="simpanTitik">
          <label class="text-[11px] font-bold text-stone-500">Kode
            <input v-model="titik.kode" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Nama
            <input v-model="titik.nama" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Jenis
            <select v-model="titik.jenis" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
              <option v-for="j in props.opsi?.jenisTitik || []" :key="j" :value="j">{{ label(j) }}</option>
            </select></label>
          <label class="text-[11px] font-bold text-stone-500">Lokasi
            <input v-model="titik.lokasi" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Ambang PPV (mm/s)
            <input v-model="titik.ppv_ambang_mm_s" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="md:col-span-2 text-[11px] font-bold text-stone-500">Acuan (dasar hukum)
            <input v-model="titik.acuan_ambang" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <button class="eq-btn-utama self-end" :disabled="titik.processing">Simpan</button>
        </form>
        <p v-for="(e, k) in titik.errors" :key="k" class="mt-2 text-[11px] text-red-600">{{ e }}</p>
      </section>

      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <table class="w-full text-[11.5px]">
          <thead class="bg-stone-50 text-left text-stone-400 uppercase text-[10px] tracking-wide">
            <tr><th class="px-5 py-2.5">Kode</th><th class="px-5 py-2.5">Nama</th><th class="px-5 py-2.5">Jenis</th>
              <th class="px-5 py-2.5 text-right">Ambang</th><th class="px-5 py-2.5">Acuan</th><th class="px-5 py-2.5"></th></tr>
          </thead>
          <tbody>
            <tr v-for="t in props.titik || []" :key="t.id" class="border-b border-stone-50">
              <td class="px-5 py-3 font-semibold">{{ t.kode }}</td>
              <td class="px-5 py-3">{{ t.nama }}</td>
              <td class="px-5 py-3 text-stone-500">{{ label(t.jenis) }}</td>
              <td class="px-5 py-3 text-right">
                {{ angka(t.ambang, 1) }} mm/s
                <span v-if="!t.ambangDitetapkan" class="ml-1 text-[10px] text-amber-700">bawaan</span>
              </td>
              <td class="px-5 py-3 text-stone-500">{{ t.acuan || '—' }}</td>
              <td class="px-5 py-3 text-right"><button v-if="isAdmin" type="button" class="text-[11px] text-stone-400" @click="hapusTitik(t)">Hapus</button></td>
            </tr>
            <tr v-if="!(props.titik || []).length"><td colspan="6" class="px-5 py-8 text-center text-stone-400">Belum ada titik terdaftar.</td></tr>
          </tbody>
        </table>
      </section>
    </template>

    <!-- ═══════════ GETARAN ═══════════ -->
    <template v-if="props.mode === 'getaran'">
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <div class="px-5 py-4 border-b border-stone-100">
          <h3 class="font-bold text-[14px]">Seluruh pengukuran getaran</h3>
          <p class="text-[11.5px] text-stone-500 mt-1">
            Kalibrasi memakai seluruh pengukuran yang ada, bukan hanya yang jatuh pada rentang tanggal —
            tetapan situs adalah sifat batuannya, bukan sifat periodenya.
          </p>
        </div>
        <table class="w-full text-[11.5px]">
          <thead class="bg-stone-50 text-left text-stone-400 uppercase text-[10px] tracking-wide">
            <tr><th class="px-5 py-2.5">Peledakan</th><th class="px-5 py-2.5">Titik</th>
              <th class="px-5 py-2.5 text-right">Jarak</th><th class="px-5 py-2.5 text-right">PPV</th>
              <th class="px-5 py-2.5 text-right">Ambang</th><th class="px-5 py-2.5">Alat</th></tr>
          </thead>
          <tbody>
            <tr v-for="u in props.ukur || []" :key="u.id" class="border-b border-stone-50" :class="u.melampaui ? 'bg-red-50/60' : ''">
              <td class="px-5 py-3 font-semibold">{{ u.rencana }}</td>
              <td class="px-5 py-3">{{ u.titik }}</td>
              <td class="px-5 py-3 text-right">{{ angka(u.jarak_m) }} m</td>
              <td class="px-5 py-3 text-right font-bold" :class="u.melampaui ? 'text-red-600' : ''">{{ angka(u.ppv, 3) }}</td>
              <td class="px-5 py-3 text-right text-stone-500">{{ angka(u.ambang, 1) }}</td>
              <td class="px-5 py-3 text-stone-500">{{ u.alat || '—' }}</td>
            </tr>
            <tr v-if="!(props.ukur || []).length"><td colspan="6" class="px-5 py-8 text-center text-stone-400">Belum ada pengukuran getaran.</td></tr>
          </tbody>
        </table>
      </section>
    </template>

    <!-- ═══════════ TINDAK LANJUT ═══════════ -->
    <section id="form-tindak" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="font-bold text-[14px]">Tindak lanjut</h3>
      <form class="mt-3 grid gap-3 md:grid-cols-5" @submit.prevent="simpanTindak">
        <label class="md:col-span-2 text-[11px] font-bold text-stone-500">Judul
          <input v-model="tindak.judul" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
        <label class="text-[11px] font-bold text-stone-500">Prioritas
          <select v-model="tindak.prioritas" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option v-for="p in props.opsi?.prioritasTindak || []" :key="p" :value="p">{{ label(p) }}</option>
          </select></label>
        <label class="text-[11px] font-bold text-stone-500">Penanggung jawab
          <input v-model="tindak.penanggung_jawab" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-bold text-stone-500">Target selesai
          <input v-model="tindak.target_selesai" type="date" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
        <label class="md:col-span-4 text-[11px] font-bold text-stone-500">Uraian
          <input v-model="tindak.uraian" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
        <button class="eq-btn-utama self-end" :disabled="tindak.processing">Tambah</button>
      </form>

      <table class="w-full mt-4 text-[11.5px]">
        <tbody>
          <tr v-for="t in props.tindak || []" :key="t.id" class="border-t border-stone-50">
            <td class="py-2"><b>{{ t.judul }}</b>
              <span v-if="t.terlambat" class="ml-2 rounded-full bg-red-100 text-red-700 px-2 py-0.5 text-[10px] font-bold">Terlambat</span></td>
            <td class="py-2 text-stone-500">{{ t.penanggung_jawab || '—' }}</td>
            <td class="py-2 text-stone-500">{{ t.target_selesai || '—' }}</td>
            <td class="py-2 text-right">
              <select class="rounded-lg border-stone-200 text-[11px]" :value="t.status"
                      @change="ubahTindak(t, ($event.target as HTMLSelectElement).value)" aria-label="Status">
                <option v-for="s in props.opsi?.statusTindak || []" :key="s" :value="s">{{ label(s) }}</option>
              </select>
            </td>
          </tr>
          <tr v-if="!(props.tindak || []).length"><td colspan="4" class="py-6 text-center text-stone-400">Belum ada tindak lanjut.</td></tr>
        </tbody>
      </table>
    </section>
  </div>
</template>

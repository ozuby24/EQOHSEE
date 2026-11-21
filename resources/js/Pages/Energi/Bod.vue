<script setup lang="ts">
/*
  Executive Energy & Carbon Control Tower (PRD §6, FSD §9).

  Lima pertanyaan direksi, berurutan dari atas: on-track? menyimpang di
  mana dan berapa rupiahnya? risiko kepatuhan terdekat? eksposur biaya
  karbon? tindak lanjut apa yang berjalan?

  Filter disimpan di alamat (?periode=ytd&company=3&site=7) supaya tautan
  dapat dibagikan; hak akses tetap diperiksa server. Angka tidak pernah
  tanpa sumber: setiap kartu menyebut mutu datanya, dan drill-down turun
  sampai catatan harian beserta faktor yang dipakai.
*/
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import KartuAngka from '../../Components/Energi/KartuAngka.vue';
import Lencana from '../../Components/Energi/Lencana.vue';
import KartuGrafik from '../../Grafik/KartuGrafik.vue';
import Garis from '../../Grafik/Garis.vue';
import Batang from '../../Grafik/Batang.vue';
import { KATEGORI } from '../../Grafik/warna';
import { json, n, persen, ringkas, rp, statusKelas, tgl } from '../../energi';

const props = propHalaman();

/* ── filter global (BOD-02) ── */
const f = reactive({
  periode: props.filter?.periode ?? 'ytd', company: props.filter?.company ?? '', site: props.filter?.site ?? '',
  dari: props.filter?.dari ?? '', sampai: props.filter?.sampai ?? '',
});
function terapkan() {
  const q: Record<string, any> = { periode: f.periode };
  if (f.company) q.company = f.company;
  if (f.site) q.site = f.site;
  if (f.periode === 'kustom') { q.dari = f.dari; q.sampai = f.sampai; }
  router.get('/energi/bod', q, { preserveScroll: true });
}
const kueri = computed(() => {
  const q = new URLSearchParams();
  Object.entries({ periode: f.periode, company: f.company, site: f.site, dari: f.periode === 'kustom' ? f.dari : '', sampai: f.periode === 'kustom' ? f.sampai : '' })
    .forEach(([k, v]) => { if (v) q.set(k, String(v)); });
  return q.toString();
});
const siteDipilih = computed(() => (props.pilihan?.site ?? []).filter((s: any) => !f.company || s.company_id === Number(f.company)));

/* ── tren & proyeksi (BOD-04) ── */
const metrik = ref<'tco2e' | 'gj' | 'gj_ton'>('tco2e');
const METRIK: Record<string, string> = { tco2e: 'Emisi (tCO₂e)', gj: 'Energi (GJ)', gj_ton: 'Intensitas (GJ/ton)' };
const labelTren = computed(() => (props.tren?.deret ?? []).map((d: any) => d.label));
const deretTren = computed(() => {
  const d = props.tren?.deret ?? [];
  const out: any[] = [{ nama: 'Aktual', nilai: d.map((x: any) => x[metrik.value]), warna: KATEGORI[1] }];
  if (metrik.value === 'gj_ton' && props.tren?.target_gj_ton) out.push({ nama: 'Target', nilai: d.map(() => Number(props.tren.target_gj_ton)), warna: '#16A34A' });
  if (metrik.value === 'gj_ton' && props.tren?.baseline_gj_ton) out.push({ nama: 'Baseline', nilai: d.map(() => Number(props.tren.baseline_gj_ton)), warna: '#78716C' });
  return out;
});

/* ── jalur dekarbonisasi (BOD-05) ── */
const labelJalur = computed(() => {
  const t = new Set<number>([...(props.jalur?.tahun ?? []).map((x: any) => x.tahun), ...(props.jalur?.lintasan ?? []).map((x: any) => x.tahun)]);
  return [...t].sort();
});
const deretJalur = computed(() => {
  const akt = Object.fromEntries((props.jalur?.tahun ?? []).map((x: any) => [x.tahun, x.tco2e]));
  const lin = Object.fromEntries((props.jalur?.lintasan ?? []).map((x: any) => [x.tahun, x.tco2e]));
  const out: any[] = [{ nama: 'Aktual', nilai: labelJalur.value.map(t => akt[t] ?? null), warna: KATEGORI[1] }];
  if (props.jalur?.lintasan?.length) out.push({ nama: 'Lintasan target', nilai: labelJalur.value.map(t => lin[t] ?? null), warna: '#16A34A' });
  return out;
});

/* ── drill-down (BOD-03) ── */
const jejak = ref<Array<{ tingkat: string; kunci: string | null; label: string; anak: any[] }>>([
  { tingkat: 'grup', kunci: null, label: 'Grup', anak: props.drill ?? [] },
]);
const sibukDrill = ref(false);
const simpulKini = computed(() => jejak.value[jejak.value.length - 1]);
async function turun(a: any) {
  if (!a.berikut) return;
  sibukDrill.value = true;
  try {
    const r = await json(`${props.tautan.drill}?${kueri.value}&tingkat=${a.berikut}&kunci=${encodeURIComponent(a.kunci)}`, undefined, 'GET');
    jejak.value.push({ tingkat: a.berikut, kunci: a.kunci, label: a.label, anak: r.anak });
  } finally { sibukDrill.value = false; }
}
const naik = (i: number) => { jejak.value = jejak.value.slice(0, i + 1); };

/* ── simulator (BOD-08) ── */
const sim = reactive({ produksi: 0, l_bcm: 0, harga_solar: 0, fame: '' as any, pln: '' as any, genset: '' as any, plts: '' as any });
const hasilSim = ref<any | null>(null);
const galatSim = ref('');
async function simulasi() {
  galatSim.value = '';
  try {
    hasilSim.value = await json(`${props.tautan.simulasi}?${kueri.value}`, Object.fromEntries(Object.entries(sim).map(([k, v]) => [k, v === '' ? null : Number(v)])));
  } catch (e: any) { galatSim.value = e.message; }
}

/* ── eksposur karbon (BOD-07) ── */
const hargaKustom = ref('');
const hitungKustom = () => router.get('/energi/bod', { ...Object.fromEntries(new URLSearchParams(kueri.value)), harga: hargaKustom.value }, { preserveScroll: true });

/* ── instruksi (BOD-11) & target (BOD-17) ── */
const instruksi = useForm<Record<string, any>>({ judul: '', isi: '', prioritas: 'tinggi', pic: '', tenggat: '', konteks: '', company_id: f.company || null });
function beriInstruksi(konteks = '') {
  instruksi.konteks = konteks;
  document.getElementById('form-instruksi')?.scrollIntoView({ behavior: 'smooth' });
}
const kirimInstruksi = () => instruksi.post('/energi/bod/instruksi', { preserveScroll: true, onSuccess: () => instruksi.reset('judul', 'isi', 'pic', 'tenggat', 'konteks') });
const ubahInstruksi = (t: any, status: string) => router.put(`/energi/bod/instruksi/${t.id}`, { status }, { preserveScroll: true });
const putusTarget = (b: any, putusan: string) => router.post(`/energi/bod/target/${b.id}`, { putusan }, { preserveScroll: true });

const pack = () => router.post(`/energi/bod/board-pack?${kueri.value}`);

const peringkatBatang = computed(() => (props.peringkat ?? []).map((u: any) => ({
  label: u.kode, nilai: Math.round(u.deviasi_rp), url: `/energi/alat/${u.id}`,
  keadaan: u.status?.kode === 'boros' ? ('gawat' as const) : u.status?.kode === 'pantau' ? ('ingat' as const) : ('baik' as const),
})));
</script>

<template>
  <Head title="Control Tower" />
  <div class="space-y-4">
    <!-- filter global & mutu data -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-4 space-y-3">
      <form class="flex flex-wrap items-end gap-2" @submit.prevent="terapkan">
        <label class="text-[11px] font-semibold text-stone-500">Periode
          <select v-model="f.periode" class="block mt-1 rounded-lg border-stone-200 text-[12px]"><option v-for="[k, l] in Object.entries((props.pilihan.periode ?? {}) as Record<string, any>)" :key="k" :value="k">{{ l }}</option></select>
        </label>
        <template v-if="f.periode === 'kustom'">
          <label class="text-[11px] font-semibold text-stone-500">Dari<input v-model="f.dari" type="date" class="block mt-1 rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-semibold text-stone-500">Sampai<input v-model="f.sampai" type="date" class="block mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        </template>
        <label v-if="props.pilihan.perusahaan?.length > 1" class="text-[11px] font-semibold text-stone-500">Perusahaan
          <select v-model="f.company" class="block mt-1 rounded-lg border-stone-200 text-[12px]"><option value="">Seluruh grup</option><option v-for="c in props.pilihan.perusahaan" :key="c.id" :value="c.id">{{ c.nama }}</option></select>
        </label>
        <label class="text-[11px] font-semibold text-stone-500">Site
          <select v-model="f.site" class="block mt-1 rounded-lg border-stone-200 text-[12px]"><option value="">Semua site</option><option v-for="s in siteDipilih" :key="s.id" :value="s.id">{{ s.nama }}</option></select>
        </label>
        <button class="eq-btn-utama" style="padding:9px 14px">Terapkan</button>
        <div class="ml-auto flex flex-wrap gap-2">
          <a :href="props.tautan.layar" class="eq-btn-lain">Mode layar</a>
          <button v-if="props.boleh.pack" type="button" class="eq-btn-lain" @click="pack">Board pack ⤓</button>
        </div>
      </form>
      <p class="text-[11.5px] text-stone-500">
        {{ tgl(props.filter.dari) }} – {{ tgl(props.filter.sampai) }} · Mutu data: <b>{{ persen(props.mutu.persen_terverifikasi, 0) }}</b> terverifikasi ·
        {{ persen(props.mutu.persen_estimasi, 0) }} estimasi · data s.d. {{ tgl(props.mutu.terakhir) }}
        <span v-if="!props.mutu.lengkap" class="text-red-700 font-semibold"> · sebagian baris kekurangan faktor — angka bertanda "—" tidak dihitung nol</span>
      </p>
    </section>

    <!-- 1 · kartu KPI dengan RAG (BOD-01, BOD-14) -->
    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <KartuAngka v-for="k in props.kartu" :key="k.kode" :label="k.label" :nilai="k.nilai" :satuan="k.satuan"
                  :desimal="k.satuan === 'Rp' ? 0 : (k.kode === 'gj_ton' || k.kode === 'tco2e_ton' ? 4 : (k.kode === 'l_bcm' ? 3 : 1))"
                  :rag="k.rag" :arah="k.arah" :catatan="k.catatan">
        <button v-if="props.boleh.instruksi" type="button" class="eq-tautan self-start mt-1" @click="beriInstruksi(`${k.label}: ${n(k.nilai, 2)} ${k.satuan}`)">Beri instruksi</button>
      </KartuAngka>
    </section>

    <!-- 2 · tren & kepatuhan -->
    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
      <KartuGrafik judul="Tren aktual vs baseline & target" :catatan="props.tren.proyeksi ? `Proyeksi akhir tahun ${n(props.tren.proyeksi.tco2e, 0)} ± ${n(props.tren.proyeksi.pita, 0)} tCO₂e (run-rate 3 bulan × ${props.tren.proyeksi.sisa_bulan} bulan sisa)` : 'Proyeksi butuh minimal satu bulan penuh.'" :angka="METRIK[metrik]">
        <div class="flex gap-1 mb-2" role="group" aria-label="Pilih metrik tren">
          <button v-for="(l, k) in METRIK" :key="k" type="button" class="eq-btn-mini" :class="metrik === k ? 'ring-2 ring-cam-orange' : ''" @click="metrik = k as any">{{ l }}</button>
        </div>
        <Garis :label="labelTren" :deret="deretTren" :satuan="metrik === 'gj_ton' ? 'GJ/ton' : metrik === 'gj' ? 'GJ' : 'tCO₂e'" />
        <template #tabel>
          <table><thead><tr><th>Bulan</th><th>GJ</th><th>tCO₂e</th><th>Ton</th><th>GJ/ton</th></tr></thead>
            <tbody><tr v-for="d in props.tren.deret" :key="d.bulan"><td>{{ d.label }}</td><td class="num">{{ n(d.gj, 0) }}</td><td class="num">{{ n(d.tco2e, 1) }}</td><td class="num">{{ n(d.ton, 0) }}</td><td class="num">{{ n(d.gj_ton, 4) }}</td></tr></tbody></table>
        </template>
      </KartuGrafik>

      <Kartu judul="Peta panas kepatuhan" ket="PP 33, Permen ESDM 8/2025, Perpres 110, PROPER, PSPK 2 — per perusahaan.">
        <div class="overflow-x-auto">
          <table class="w-full text-[11px]">
            <thead><tr class="text-left text-stone-400"><th class="py-1 pr-2">Perusahaan</th><th v-for="k in (props.kepatuhan?.[0]?.kewajiban ?? [])" :key="k.kode" class="py-1 px-1 font-semibold">{{ k.kode }}</th></tr></thead>
            <tbody>
              <tr v-for="c in props.kepatuhan" :key="c.id" class="border-t border-stone-50">
                <td class="py-1 pr-2 font-semibold whitespace-nowrap">{{ c.nama }}</td>
                <td v-for="k in c.kewajiban" :key="k.kode" class="py-1 px-1">
                  <span class="block rounded px-1 py-0.5 text-center text-[9.5px] font-bold" :class="statusKelas(k.status)" :title="`${k.judul} — ${k.status_label}${k.tenggat ? ' · ' + tgl(k.tenggat) : ''}`">{{ k.tenggat ? tgl(k.tenggat).slice(0, 6) : '—' }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p class="text-[10.5px] text-stone-400 mt-2">Arahkan penunjuk ke sel untuk rincian; <Link href="/energi/kalender" class="eq-tautan">kalender lengkap</Link>.</p>
      </Kartu>
    </section>

    <!-- 3 · menyimpang di mana & peringatan -->
    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
      <KartuGrafik judul="Unit menyimpang — deviasi rupiah" catatan="(L/HM aktual − acuan kategori) × HM × harga solar. Klik batang untuk membuka unit." angka="Rp periode ini">
        <Batang :baris="peringkatBatang" satuan="Rp" apa-adanya />
        <template #tabel>
          <table><thead><tr><th>Unit</th><th>Kategori</th><th>L/HM</th><th>Acuan</th><th>Deviasi</th></tr></thead>
            <tbody><tr v-for="u in props.peringkat" :key="u.id"><td>{{ u.kode }}</td><td>{{ u.kategori }}</td><td class="num">{{ n(u.l_hm, 2) }}</td><td class="num">{{ n(u.acuan, 2) }}</td><td class="num">{{ rp(u.deviasi_rp) }}</td></tr></tbody></table>
        </template>
      </KartuGrafik>

      <Kartu judul="Pusat peringatan" :ket="`${props.peringatan?.length ?? 0} peringatan aktif`">
        <ul class="divide-y divide-stone-100 max-h-[320px] overflow-y-auto">
          <li v-for="[i, p] in Object.entries((props.peringatan ?? {}) as Record<string, any>)" :key="i" class="py-2 flex items-start gap-2">
            <span class="mt-1 w-2 h-2 rounded-full shrink-0" :style="{ background: p.tingkat === 'merah' ? '#D92D20' : '#D97706' }"></span>
            <div class="min-w-0 flex-1">
              <p class="text-[11px] text-stone-400">{{ p.jenis }}<template v-if="p.tanggal"> · {{ tgl(p.tanggal) }}</template></p>
              <a :href="p.tautan" class="text-[12px] text-cam-ink hover:underline">{{ p.judul }}</a>
            </div>
            <button v-if="props.boleh.instruksi" type="button" class="eq-btn-mini shrink-0" @click="beriInstruksi(`${p.jenis}: ${p.judul}`)">Instruksi</button>
          </li>
        </ul>
        <p v-if="!props.peringatan?.length" class="text-[12px] text-stone-400 py-4 text-center">Tidak ada peringatan aktif.</p>
      </Kartu>
    </section>

    <!-- drill-down -->
    <Kartu judul="Telusur angka" ket="Grup → perusahaan → site → kelompok → sumber → catatan harian beserta faktor yang dipakai.">
      <nav class="flex flex-wrap items-center gap-1 text-[11.5px] mb-2" aria-label="Jejak telusur">
        <template v-for="(j, i) in jejak" :key="i">
          <button type="button" class="font-semibold" :class="i === jejak.length - 1 ? 'text-cam-ink' : 'text-cam-lime-deep hover:underline'" @click="naik(i)">{{ j.label }}</button>
          <span v-if="i < jejak.length - 1" class="text-stone-300">/</span>
        </template>
        <span v-if="sibukDrill" class="text-stone-400 ml-2">memuat…</span>
      </nav>
      <table class="w-full text-[12px]">
        <thead><tr class="text-left text-stone-400 border-b border-stone-100"><th class="py-2">Simpul</th><th class="py-2 text-right">GJ</th><th class="py-2 text-right">tCO₂e</th><th class="py-2 text-right">Rp</th><th class="py-2 text-right">Terverifikasi</th></tr></thead>
        <tbody>
          <tr v-for="a in simpulKini.anak" :key="a.kunci" class="border-b border-stone-50 align-top">
            <td class="py-2">
              <button v-if="a.berikut" type="button" class="font-semibold text-cam-lime-deep hover:underline text-left" @click="turun(a)">{{ a.label }}</button>
              <span v-else class="font-semibold">{{ a.label }}</span>
              <span v-if="a.liter !== undefined" class="block text-[10.5px] text-stone-500">{{ n(a.liter, 0) }} L · {{ n(a.hm, 1) }} HM · FAME {{ n(a.persen_fame, 0) }}% · {{ a.status }} · {{ a.sumber_data ?? '—' }}</span>
              <span v-if="a.faktor?.length" class="block text-[10px] text-stone-400">Faktor: {{ a.faktor.map((x: any) => `${x.kode_energi}/${x.parameter}=${x.nilai} (#${x.id})`).join(' · ') }}</span>
              <span v-if="a.lengkap === false" class="block text-[10.5px] text-red-600">kekurangan faktor</span>
            </td>
            <td class="py-2 text-right num">{{ n(a.gj, 1) }}</td><td class="py-2 text-right num">{{ n(a.tco2e, 2) }}</td>
            <td class="py-2 text-right num">{{ rp(a.rupiah) }}</td><td class="py-2 text-right num">{{ persen(a.persen_terverifikasi, 0) }}</td>
          </tr>
        </tbody>
      </table>
      <p v-if="!simpulKini.anak?.length" class="text-[12px] text-stone-400 py-4 text-center">Tidak ada data pada simpul ini.</p>
    </Kartu>

    <!-- 4 · portofolio, eksposur, simulator -->
    <section class="grid gap-4 xl:grid-cols-2">
      <Kartu judul="Portofolio inisiatif hemat" ket="Rencana per tahun vs penghematan terverifikasi M&V; CAPEX dan masa balik modal.">
        <table class="w-full text-[11.5px] mb-3">
          <thead><tr class="text-left text-stone-400 border-b border-stone-100"><th class="py-1.5">Status</th><th class="py-1.5 text-right">Jml</th><th class="py-1.5 text-right">CAPEX</th><th class="py-1.5 text-right">Rencana tCO₂e/thn</th><th class="py-1.5 text-right">Terverifikasi</th></tr></thead>
          <tbody><tr v-for="s in props.portofolio.per_status" :key="s.status" class="border-b border-stone-50">
            <td class="py-1.5"><Lencana :status="s.status" /></td><td class="py-1.5 text-right num">{{ s.jumlah }}</td><td class="py-1.5 text-right num">{{ rp(s.capex_rp) }}</td>
            <td class="py-1.5 text-right num">{{ n(s.rencana_tco2e, 1) }}</td><td class="py-1.5 text-right num">{{ n(s.terverifikasi_tco2e, 1) }}</td>
          </tr></tbody>
        </table>
        <ul class="divide-y divide-stone-100 text-[11.5px]">
          <li v-for="p in props.portofolio.rincian" :key="p.id" class="py-1.5 flex justify-between gap-2">
            <span class="min-w-0 truncate">{{ p.judul }} <span class="text-stone-400">· {{ p.pic ?? 'tanpa PIC' }}</span></span>
            <span class="shrink-0 text-stone-600">{{ n(p.rencana_tco2e, 1) }} t/thn<template v-if="p.payback !== null"> · balik modal {{ n(p.payback, 1) }} thn</template></span>
          </li>
        </ul>
      </Kartu>

      <Kartu judul="Eksposur biaya karbon" :ket="`(Scope 1 + Scope 2) ${n(props.ringkas.total, 1)} tCO₂e × harga karbon bayangan. Tarif minimum UU HPP Rp30/kgCO₂e — penerapannya masih ditunda.`">
        <table class="w-full text-[12px] mb-3">
          <tbody><tr v-for="e in props.eksposur" :key="e.skenario" class="border-b border-stone-50">
            <td class="py-2 font-semibold">{{ e.skenario }}</td><td class="py-2 text-right num">Rp {{ n(e.harga, 0) }}/tCO₂e</td><td class="py-2 text-right num font-bold">{{ rp(e.rupiah) }}</td>
            <td class="py-2 text-[10.5px] text-stone-400 pl-2">{{ e.sumber }}</td>
          </tr></tbody>
        </table>
        <form class="flex items-end gap-2" @submit.prevent="hitungKustom">
          <label class="text-[11px] font-semibold text-stone-500">Harga kustom (Rp/tCO₂e)<input v-model="hargaKustom" type="number" min="0" step="any" class="block mt-1 rounded-lg border-stone-200 text-[12px]"></label>
          <button class="eq-btn-mini">Hitung</button>
        </form>
      </Kartu>
    </section>

    <section class="grid gap-4 xl:grid-cols-2">
      <Kartu judul="Simulator skenario" ket="Ubah asumsi dan lihat dampaknya pada GJ, tCO₂e, dan rupiah — dihitung server dengan faktor aktif, tidak menyimpan apa pun.">
        <form class="grid gap-2 sm:grid-cols-3" @submit.prevent="simulasi">
          <label class="text-[11px] font-semibold text-stone-500">Δ produksi (%)<input v-model="sim.produksi" type="number" step="any" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-semibold text-stone-500">Δ L/BCM (%)<input v-model="sim.l_bcm" type="number" step="any" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-semibold text-stone-500">Δ harga solar (%)<input v-model="sim.harga_solar" type="number" step="any" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-semibold text-stone-500">FAME (%)<input v-model="sim.fame" type="number" step="any" min="0" max="100" placeholder="campuran berlaku" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-semibold text-stone-500">Bauran listrik — PLN (%)<input v-model="sim.pln" type="number" step="any" min="0" max="100" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-semibold text-stone-500">Genset (%)<input v-model="sim.genset" type="number" step="any" min="0" max="100" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-semibold text-stone-500">PLTS (%)<input v-model="sim.plts" type="number" step="any" min="0" max="100" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
          <div class="sm:col-span-2 flex items-end"><button class="eq-btn-utama">Jalankan simulasi</button></div>
        </form>
        <p v-if="galatSim" class="text-[11px] text-red-600 mt-2">{{ galatSim }}</p>
        <table v-if="hasilSim" class="w-full text-[12px] mt-3">
          <thead><tr class="text-left text-stone-400 border-b border-stone-100"><th class="py-1.5"></th><th class="py-1.5 text-right">GJ</th><th class="py-1.5 text-right">tCO₂e</th><th class="py-1.5 text-right">Rp</th></tr></thead>
          <tbody>
            <tr class="border-b border-stone-50"><td class="py-1.5">Dasar</td><td class="py-1.5 text-right num">{{ n(hasilSim.dasar.gj, 0) }}</td><td class="py-1.5 text-right num">{{ n(hasilSim.dasar.tco2e, 1) }}</td><td class="py-1.5 text-right num">{{ rp(hasilSim.dasar.rupiah) }}</td></tr>
            <tr class="border-b border-stone-50"><td class="py-1.5">Skenario</td><td class="py-1.5 text-right num">{{ n(hasilSim.skenario.gj, 0) }}</td><td class="py-1.5 text-right num">{{ n(hasilSim.skenario.tco2e, 1) }}</td><td class="py-1.5 text-right num">{{ rp(hasilSim.skenario.rupiah) }}</td></tr>
            <tr class="font-bold"><td class="py-1.5">Selisih</td>
              <td class="py-1.5 text-right num" :class="hasilSim.selisih.gj <= 0 ? 'text-emerald-700' : 'text-red-700'">{{ n(hasilSim.selisih.gj, 0) }}</td>
              <td class="py-1.5 text-right num" :class="hasilSim.selisih.tco2e <= 0 ? 'text-emerald-700' : 'text-red-700'">{{ n(hasilSim.selisih.tco2e, 1) }}</td>
              <td class="py-1.5 text-right num" :class="hasilSim.selisih.rupiah <= 0 ? 'text-emerald-700' : 'text-red-700'">{{ rp(hasilSim.selisih.rupiah) }}</td></tr>
          </tbody>
        </table>
      </Kartu>

      <KartuGrafik judul="Jalur dekarbonisasi" :catatan="props.jalur.target.tahun ? `Target −${n(props.jalur.target.persen, 0)}% pada ${props.jalur.target.tahun} terhadap tahun dasar ${props.jalur.target.dasar}.` : 'Atur tahun & persen target di Manajemen Energi → Pengaturan.'" angka="tCO₂e/tahun">
        <Garis :label="labelJalur.map(String)" :deret="deretJalur" satuan="tCO₂e" />
        <template #tabel>
          <table><thead><tr><th>Inisiatif mitigasi</th><th>Target turun/thn</th><th>Capaian tahun ini</th></tr></thead>
            <tbody><tr v-for="k in props.jalur.kontribusi" :key="k.judul"><td>{{ k.judul }}</td><td class="num">{{ n(k.tco2e, 1) }}</td><td class="num">{{ n(k.capaian, 1) }}</td></tr></tbody></table>
        </template>
      </KartuGrafik>
    </section>

    <section v-if="props.benchmark?.length" class="grid">
      <Kartu judul="Benchmark antar-perusahaan" ket="Diurutkan dari intensitas energi terendah.">
        <table class="w-full text-[12px]">
          <thead><tr class="text-left text-stone-400 border-b border-stone-100"><th class="py-2">#</th><th class="py-2">Perusahaan</th><th class="py-2 text-right">GJ</th><th class="py-2 text-right">GJ/ton</th><th class="py-2 text-right">tCO₂e</th><th class="py-2 text-right">tCO₂e/ton</th></tr></thead>
          <tbody><tr v-for="[i, b] in Object.entries((props.benchmark ?? {}) as Record<string, any>)" :key="b.id" class="border-b border-stone-50"><td class="py-2">{{ Number(i) + 1 }}</td><td class="py-2 font-semibold">{{ b.nama }}</td><td class="py-2 text-right num">{{ ringkas(b.gj) }}</td><td class="py-2 text-right num">{{ n(b.gj_ton, 4) }}</td><td class="py-2 text-right num">{{ ringkas(b.tco2e) }}</td><td class="py-2 text-right num">{{ n(b.tco2e_ton, 5) }}</td></tr></tbody>
        </table>
      </Kartu>
    </section>

    <!-- 5 · tindak lanjut -->
    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
      <Kartu judul="Instruksi BOD" ket="Tercatat sebagai tindak lanjut ber-PIC dan bertenggat; ikut pengingat yang dipakai seluruh modul.">
        <table class="w-full text-[12px]">
          <thead><tr class="text-left text-stone-400 border-b border-stone-100"><th class="py-2">Instruksi</th><th class="py-2">PIC</th><th class="py-2">Tenggat</th><th class="py-2">Status</th></tr></thead>
          <tbody>
            <tr v-for="t in props.instruksi" :key="t.id" class="border-b border-stone-50">
              <td class="py-2 font-semibold">{{ t.judul }}</td><td class="py-2">{{ t.penanggungJawab ?? t.penanggung_jawab ?? '—' }}</td>
              <td class="py-2" :class="t.terlambat ? 'text-red-600 font-bold' : ''">{{ tgl(t.targetSelesai ?? t.target_selesai) }}</td>
              <td class="py-2">
                <select v-if="props.boleh.instruksi" :value="t.status" class="rounded border-stone-200 text-[11px]" aria-label="Status instruksi" @change="ubahInstruksi(t, ($event.target as HTMLSelectElement).value)">
                  <option value="rencana">Rencana</option><option value="berjalan">Berjalan</option><option value="selesai">Selesai</option><option value="batal">Batal</option>
                </select>
                <Lencana v-else :status="t.status" />
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="!props.instruksi?.length" class="text-[12px] text-stone-400 py-4 text-center">Belum ada instruksi direksi.</p>

        <form v-if="props.boleh.instruksi" id="form-instruksi" class="grid gap-2 sm:grid-cols-4 mt-4 pt-4 border-t border-stone-100" @submit.prevent="kirimInstruksi">
          <label class="text-[11px] font-semibold text-stone-500 sm:col-span-2">Judul instruksi<input v-model="instruksi.judul" required maxlength="190" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-semibold text-stone-500">PIC
            <input v-model="instruksi.pic" required maxlength="150" list="daftar-pic" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]">
            <datalist id="daftar-pic"><option v-for="u in props.pic" :key="u.id" :value="u.name"></option></datalist>
          </label>
          <label class="text-[11px] font-semibold text-stone-500">Tenggat<input v-model="instruksi.tenggat" required type="date" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-semibold text-stone-500 sm:col-span-3">Isi instruksi<textarea v-model="instruksi.isi" required rows="2" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></textarea></label>
          <label class="text-[11px] font-semibold text-stone-500">Prioritas<select v-model="instruksi.prioritas" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option value="rendah">Rendah</option><option value="sedang">Sedang</option><option value="tinggi">Tinggi</option><option value="kritis">Kritis</option></select></label>
          <p v-if="instruksi.konteks" class="sm:col-span-4 text-[11px] text-stone-500">Konteks: {{ instruksi.konteks }}</p>
          <div class="sm:col-span-4"><button class="eq-btn-utama" :disabled="instruksi.processing">Kirim instruksi</button></div>
        </form>
        <p v-for="(pesan, k) in instruksi.errors" :key="k" class="text-[11px] text-red-600 mt-1">{{ pesan }}</p>
      </Kartu>

      <Kartu judul="Persetujuan target tahunan" ket="Baseline & target usulan Manajer Energi; berlaku di dasbor setelah disetujui direksi.">
        <article v-for="b in props.usulanTarget" :key="b.id" class="rounded-xl bg-stone-50 p-3 mb-2">
          <p class="text-[12.5px] font-semibold">Tahun {{ b.tahun }}: {{ n(b.baseline_gj_ton, 4) }} → {{ n(b.target_gj_ton, 4) }} GJ/ton<template v-if="b.target_tco2e_ton"> · {{ n(b.target_tco2e_ton, 5) }} tCO₂e/ton</template></p>
          <p v-if="b.model" class="text-[11px] text-stone-500">EnB: R² {{ n(b.model.r2, 3) }} · CV(RMSE) {{ n(b.model.cv_rmse, 1) }}% · {{ b.model.diterima ? 'model diterima' : 'model di luar ambang' }}</p>
          <p v-if="b.catatan" class="text-[11px] text-stone-500">{{ b.catatan }}</p>
          <div v-if="props.boleh.target" class="flex gap-2 mt-2">
            <button type="button" class="eq-btn-setuju" @click="putusTarget(b, 'setujui')">Setujui</button>
            <button type="button" class="eq-btn-tolak" @click="putusTarget(b, 'tolak')">Kembalikan</button>
          </div>
        </article>
        <p v-if="!props.usulanTarget?.length" class="text-[12px] text-stone-400 py-4 text-center">Tidak ada usulan target yang menunggu.</p>
      </Kartu>
    </section>
  </div>
</template>

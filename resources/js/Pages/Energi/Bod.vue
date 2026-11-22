<script setup lang="ts">
/*
  Control Tower energi & karbon untuk direksi.

  Bagian atas selalu tampak: saringan, mutu data, dan delapan angka
  utama. Sisanya dibagi ke tab supaya halaman tidak menjadi dinding
  kartu — direksi membuka satu pertanyaan sekali waktu.

  Filter disimpan di alamat (?periode=ytd&company=3&site=7) supaya tautan
  dapat dibagikan; hak akses tetap diperiksa server.
*/
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Panggung from '../../Components/Energi/Panggung.vue';
import KartuAngka from '../../Components/Energi/KartuAngka.vue';
import Lencana from '../../Components/Energi/Lencana.vue';
import KartuGrafik from '../../Grafik/KartuGrafik.vue';
import Garis from '../../Grafik/Garis.vue';
import Batang from '../../Grafik/Batang.vue';
import { KATEGORI } from '../../Grafik/warna';
import { json, n, persen, ringkas, rp, statusKelas, tgl } from '../../energi';

const props = propHalaman();

/* ── tab ── */
const TAB: Record<string, string> = {
  ringkasan: 'Ringkasan', kepatuhan: 'Kepatuhan & target', inisiatif: 'Inisiatif & karbon',
  telusur: 'Telusur data', instruksi: 'Instruksi',
};
const awalTab = typeof window === 'undefined' ? null : new URLSearchParams(window.location.search).get('tab');
const tab = ref(awalTab && awalTab in TAB ? awalTab : 'ringkasan');
const lencanaPanggung = computed(() => {
  const l: string[] = [];
  const merah = (props.kartu ?? []).filter((k: any) => k.rag === 'merah').length;
  l.push(merah ? `${merah} indikator melewati target` : 'Semua indikator dalam batas');
  if (props.peringatan?.length) l.push(`${props.peringatan.length} perlu perhatian`);
  if (!props.mutu?.lengkap) l.push('Sebagian data belum punya faktor emisi');
  return l;
});
const instruksiTerbuka = computed(() => (props.instruksi ?? []).filter((t: any) => t.status !== 'selesai' && t.status !== 'batal').length);

/* ── filter ── */
const f = reactive({
  periode: props.filter?.periode ?? 'ytd', company: props.filter?.company ?? '', site: props.filter?.site ?? '',
  dari: props.filter?.dari ?? '', sampai: props.filter?.sampai ?? '',
});
function terapkan() {
  const q: Record<string, any> = { periode: f.periode, tab: tab.value };
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

/* ── tren ── */
const metrik = ref<'tco2e' | 'gj' | 'gj_ton'>('tco2e');
const METRIK: Record<string, string> = { tco2e: 'Emisi', gj: 'Energi', gj_ton: 'Intensitas' };
const SATUAN: Record<string, string> = { tco2e: 'tCO₂e', gj: 'GJ', gj_ton: 'GJ/ton' };
const labelTren = computed(() => (props.tren?.deret ?? []).map((d: any) => d.label));
const deretTren = computed(() => {
  const d = props.tren?.deret ?? [];
  const out: any[] = [{ nama: 'Aktual', nilai: d.map((x: any) => x[metrik.value]), warna: KATEGORI[1] }];
  if (metrik.value === 'gj_ton' && props.tren?.target_gj_ton) out.push({ nama: 'Target', nilai: d.map(() => Number(props.tren.target_gj_ton)), warna: '#16A34A' });
  if (metrik.value === 'gj_ton' && props.tren?.baseline_gj_ton) out.push({ nama: 'Baseline', nilai: d.map(() => Number(props.tren.baseline_gj_ton)), warna: '#78716C' });
  return out;
});

/* ── jalur dekarbonisasi ── */
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

/* ── kepatuhan: judul kolom pendek ── */
const SINGKAT: Record<string, string> = {
  ESDM_ME: 'Lap. ESDM', ESDM_MONEV: 'Monev', GRK_PU: 'Inventaris GRK', AUDIT_ENERGI: 'Audit energi',
  REKOMENDASI: 'Rekomendasi', SERT_MANAJER: 'Manajer energi', PROPER: 'PROPER', OJK_LK: 'Lap. keberlanjutan', PSPK2: 'PSPK 2',
};
const kolomPatuh = computed(() => props.kepatuhan?.[0]?.kewajiban ?? []);

/* ── telusur ── */
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

/* ── simulator ── */
const sim = reactive({ produksi: 0, l_bcm: 0, harga_solar: 0, fame: '' as any, pln: '' as any, genset: '' as any, plts: '' as any });
const hasilSim = ref<any | null>(null);
const galatSim = ref('');
async function simulasi() {
  galatSim.value = '';
  try {
    hasilSim.value = await json(`${props.tautan.simulasi}?${kueri.value}`, Object.fromEntries(Object.entries(sim).map(([k, v]) => [k, v === '' ? null : Number(v)])));
  } catch (e: any) { galatSim.value = e.message; }
}

/* ── eksposur karbon ── */
const hargaKustom = ref('');
const hitungKustom = () => router.get('/energi/bod', { ...Object.fromEntries(new URLSearchParams(kueri.value)), harga: hargaKustom.value, tab: 'inisiatif' }, { preserveScroll: true });

/* ── instruksi & target ── */
const instruksi = useForm<Record<string, any>>({ judul: '', isi: '', prioritas: 'tinggi', pic: '', tenggat: '', konteks: '', company_id: f.company || null });
function beriInstruksi(konteks = '') {
  instruksi.konteks = konteks;
  if (!instruksi.judul) instruksi.judul = konteks.slice(0, 190);
  tab.value = 'instruksi';
}
const kirimInstruksi = () => instruksi.post('/energi/bod/instruksi', { preserveScroll: true, preserveState: true, onSuccess: () => instruksi.reset('judul', 'isi', 'pic', 'tenggat', 'konteks') });
const ubahInstruksi = (t: any, status: string) => router.put(`/energi/bod/instruksi/${t.id}`, { status }, { preserveScroll: true, preserveState: true });
const putusTarget = (b: any, putusan: string) => router.post(`/energi/bod/target/${b.id}`, { putusan }, { preserveScroll: true, preserveState: true });

const pack = () => router.post(`/energi/bod/board-pack?${kueri.value}`);

const peringkatBatang = computed(() => (props.peringkat ?? []).map((u: any) => ({
  label: u.kode, nilai: Math.round(u.deviasi_rp), url: `/energi/alat/${u.id}`,
  keadaan: u.status?.kode === 'boros' ? ('gawat' as const) : u.status?.kode === 'pantau' ? ('ingat' as const) : ('baik' as const),
})));

const desimal = (k: any) => k.satuan === 'Rp' ? 0 : (k.kode === 'gj_ton' || k.kode === 'tco2e_ton' ? 4 : (k.kode === 'l_bcm' ? 3 : 1));

const TH = 'px-5 py-2 font-semibold';
const TD = 'px-5 py-2';
</script>

<template>
  <Head title="Control Tower" />
  <div class="space-y-4">
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
      <button class="eq-btn-lain" style="padding:9px 14px">Terapkan</button>
      <div class="ml-auto flex flex-wrap gap-2">
        <a :href="props.tautan.layar" class="eq-btn-mini">Layar penuh</a>
        <button v-if="props.boleh.pack" type="button" class="eq-btn-mini" @click="pack">Unduh board pack</button>
      </div>
    </form>

    <Panggung tema="energi" judul="Control Tower energi & karbon"
              :sub="`${tgl(props.filter.dari)} – ${tgl(props.filter.sampai)} · ${persen(props.mutu.persen_terverifikasi, 0)} data terverifikasi · data terakhir ${tgl(props.mutu.terakhir)}`"
              :lencana="lencanaPanggung" />

    <div class="ka-kisi">
      <KartuAngka v-for="k in props.kartu" :key="k.kode" :label="k.label" :nilai="k.nilai" :satuan="k.satuan"
                  :desimal="desimal(k)" :rag="k.rag" :arah="k.arah" :catatan="k.catatan" />
    </div>

    <nav class="flex flex-wrap gap-1.5 pt-1" role="tablist" aria-label="Bagian Control Tower">
      <button v-for="(l, k) in TAB" :key="k" type="button" role="tab" class="eq-saring" :class="{ aktif: tab === k }" :aria-selected="tab === k" @click="tab = k">
        {{ l }}<b v-if="k === 'instruksi' && instruksiTerbuka">{{ instruksiTerbuka }}</b>
      </button>
    </nav>

    <!-- ═══ Ringkasan ═══ -->
    <template v-if="tab === 'ringkasan'">
      <section class="grid gap-4 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        <KartuGrafik judul="Tren bulanan" :catatan="props.tren.proyeksi ? `Bulan berjalan belum penuh · proyeksi akhir tahun ${n(props.tren.proyeksi.tco2e, 0)} ± ${n(props.tren.proyeksi.pita, 0)} tCO₂e` : 'Bulan berjalan belum penuh'" :angka="SATUAN[metrik]">
          <div class="flex gap-1.5 mb-2" role="group" aria-label="Pilih metrik tren">
            <button v-for="(l, k) in METRIK" :key="k" type="button" class="eq-saring" :class="{ aktif: metrik === k }" @click="metrik = k as any">{{ l }}</button>
          </div>
          <Garis :label="labelTren" :deret="deretTren" :satuan="SATUAN[metrik]" />
          <template #tabel>
            <table><thead><tr><th>Bulan</th><th>GJ</th><th>tCO₂e</th><th>Ton</th><th>GJ/ton</th></tr></thead>
              <tbody><tr v-for="d in props.tren.deret" :key="d.bulan"><td>{{ d.label }}</td><td class="num">{{ n(d.gj, 0) }}</td><td class="num">{{ n(d.tco2e, 1) }}</td><td class="num">{{ n(d.ton, 0) }}</td><td class="num">{{ n(d.gj_ton, 4) }}</td></tr></tbody></table>
          </template>
        </KartuGrafik>

        <Kartu judul="Perlu perhatian" ikon="bell-ring" warna="mawar" rapat>
          <template #aksi><span class="text-[11px] text-stone-400">{{ props.peringatan?.length ?? 0 }}</span></template>
          <ul class="divide-y divide-stone-100 max-h-[340px] overflow-y-auto border-t border-stone-100">
            <li v-for="[i, p] in Object.entries((props.peringatan ?? {}) as Record<string, any>)" :key="i" class="px-5 py-2.5 flex items-start gap-2.5">
              <span class="mt-1.5 w-1.5 h-1.5 rounded-full shrink-0" :class="p.tingkat === 'merah' ? 'bg-red-600' : 'bg-amber-500'"></span>
              <div class="min-w-0 flex-1">
                <a :href="p.tautan" class="text-[12px] text-cam-ink hover:underline">{{ p.judul }}</a>
                <p class="text-[10.5px] text-stone-400">{{ p.jenis }}<template v-if="p.tanggal"> · {{ tgl(p.tanggal) }}</template></p>
              </div>
              <button v-if="props.boleh.instruksi" type="button" class="text-[11px] font-semibold text-stone-500 hover:text-cam-ink shrink-0" @click="beriInstruksi(`${p.jenis}: ${p.judul}`)">Tindak lanjuti</button>
            </li>
          </ul>
          <p v-if="!props.peringatan?.length" class="eq-kosong eq-kosong-kecil">Tidak ada yang perlu ditindaklanjuti.</p>
        </Kartu>
      </section>

      <KartuGrafik judul="Unit paling boros" catatan="Selisih biaya solar terhadap rata-rata kategori unitnya." angka="Rp">
        <Batang :baris="peringkatBatang" satuan="Rp" apa-adanya />
        <template #tabel>
          <table><thead><tr><th>Unit</th><th>Kategori</th><th>L/HM</th><th>Acuan</th><th>Selisih</th></tr></thead>
            <tbody><tr v-for="u in props.peringkat" :key="u.id"><td>{{ u.kode }}</td><td>{{ u.kategori }}</td><td class="num">{{ n(u.l_hm, 2) }}</td><td class="num">{{ n(u.acuan, 2) }}</td><td class="num">{{ rp(u.deviasi_rp) }}</td></tr></tbody></table>
        </template>
      </KartuGrafik>
    </template>

    <!-- ═══ Kepatuhan & target ═══ -->
    <template v-else-if="tab === 'kepatuhan'">
      <Kartu judul="Kewajiban per perusahaan" ikon="calendar-clock" warna="biru" rapat>
        <template #aksi><Link href="/energi/kalender" class="eq-tautan">Kalender</Link></template>
        <div class="overflow-x-auto">
          <table class="w-full text-[11.5px]">
            <thead>
              <tr class="text-left text-stone-400 border-y border-stone-200 bg-stone-50/70">
                <th :class="TH">Perusahaan</th>
                <th v-for="k in kolomPatuh" :key="k.kode" class="px-2 py-2 font-semibold whitespace-nowrap">{{ SINGKAT[k.kode] ?? k.judul }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in props.kepatuhan" :key="c.id" class="border-b border-stone-100 last:border-0">
                <td :class="TD" class="font-semibold whitespace-nowrap">{{ c.nama }}</td>
                <td v-for="k in c.kewajiban" :key="k.kode" class="px-2 py-2">
                  <span v-if="k.status === 'tidak_berlaku'" class="text-stone-300" :title="k.judul">—</span>
                  <span v-else class="inline-block rounded-md px-1.5 py-0.5 text-[10.5px] font-semibold whitespace-nowrap" :class="statusKelas(k.status)"
                        :title="`${k.judul}${k.tenggat ? ' · tenggat ' + tgl(k.tenggat) : ''}`">{{ k.status_label }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </Kartu>

      <Kartu judul="Target tahunan menunggu persetujuan" ikon="target" warna="hijau">
        <article v-for="b in props.usulanTarget" :key="b.id" class="flex flex-wrap items-center justify-between gap-3 py-3 border-t border-stone-100 first:border-0 first:pt-0">
          <div class="min-w-0">
            <p class="text-[12.5px] font-semibold text-cam-ink">{{ b.tahun }} · {{ n(b.baseline_gj_ton, 4) }} → {{ n(b.target_gj_ton, 4) }} GJ/ton<template v-if="b.target_tco2e_ton"> · {{ n(b.target_tco2e_ton, 5) }} tCO₂e/ton</template></p>
            <p v-if="b.model" class="text-[11px] text-stone-500">Model baseline R² {{ n(b.model.r2, 2) }}, CV(RMSE) {{ n(b.model.cv_rmse, 1) }}%<template v-if="!b.model.diterima"> — di luar ambang</template></p>
          </div>
          <div v-if="props.boleh.target" class="flex gap-2">
            <button type="button" class="eq-btn-setuju" @click="putusTarget(b, 'setujui')">Setujui</button>
            <button type="button" class="eq-btn-tolak" @click="putusTarget(b, 'tolak')">Kembalikan</button>
          </div>
        </article>
        <p v-if="!props.usulanTarget?.length" class="text-[12px] text-stone-400">Tidak ada usulan.</p>
      </Kartu>
    </template>

    <!-- ═══ Inisiatif & karbon ═══ -->
    <template v-else-if="tab === 'inisiatif'">
      <section class="grid gap-4 xl:grid-cols-2">
        <Kartu judul="Inisiatif penghematan" ikon="trending-down" warna="hijau" rapat>
          <table class="w-full text-[12px]">
            <thead><tr class="text-left text-stone-400 border-y border-stone-200 bg-stone-50/70"><th :class="TH">Status</th><th class="px-2 py-2 font-semibold text-right">Jml</th><th class="px-2 py-2 font-semibold text-right">CAPEX</th><th class="px-2 py-2 font-semibold text-right">Rencana t/thn</th><th class="pl-2 pr-5 py-2 font-semibold text-right">Terukur t/thn</th></tr></thead>
            <tbody><tr v-for="s in props.portofolio.per_status.filter((x: any) => x.jumlah)" :key="s.status" class="border-b border-stone-100">
              <td :class="TD"><Lencana :status="s.status" /></td><td class="px-2 py-2 text-right num">{{ s.jumlah }}</td><td class="px-2 py-2 text-right num">{{ rp(s.capex_rp) }}</td>
              <td class="px-2 py-2 text-right num">{{ n(s.rencana_tco2e, 1) }}</td><td class="pl-2 pr-5 py-2 text-right num">{{ n(s.terverifikasi_tco2e, 1) }}</td>
            </tr></tbody>
          </table>
          <ul class="divide-y divide-stone-100 text-[11.5px]">
            <li v-for="p in props.portofolio.rincian" :key="p.id" class="px-5 py-2 flex justify-between gap-3">
              <span class="min-w-0 truncate">{{ p.judul }} <span class="text-stone-400">· {{ p.pic ?? 'tanpa PIC' }}</span></span>
              <span class="shrink-0 text-stone-600 num">{{ n(p.rencana_tco2e, 1) }} t/thn<template v-if="p.payback !== null"> · balik modal {{ n(p.payback, 1) }} thn</template></span>
            </li>
          </ul>
        </Kartu>

        <Kartu judul="Biaya karbon bila dikenakan" ikon="coins" warna="ungu" :ket="`${n(props.ringkas.total, 1)} tCO₂e (Scope 1 + 2)`" rapat>
          <table class="w-full text-[12px]">
            <thead><tr class="text-left text-stone-400 border-y border-stone-200 bg-stone-50/70"><th :class="TH">Skenario</th><th class="px-2 py-2 font-semibold text-right">Harga</th><th :class="TH" class="text-right">Biaya</th></tr></thead>
            <tbody><tr v-for="e in props.eksposur" :key="e.skenario" class="border-b border-stone-100">
              <td :class="TD"><span class="font-semibold">{{ e.skenario }}</span><span v-if="e.sumber" class="block text-[10.5px] text-stone-400">{{ e.sumber }}</span></td>
              <td class="px-2 py-2 text-right num whitespace-nowrap">Rp {{ n(e.harga, 0) }}/t</td><td :class="TD" class="text-right num font-bold">{{ rp(e.rupiah) }}</td>
            </tr></tbody>
          </table>
          <form class="flex items-end gap-2 px-5 py-3" @submit.prevent="hitungKustom">
            <label class="text-[11px] font-semibold text-stone-500">Harga lain (Rp/tCO₂e)<input v-model="hargaKustom" type="number" min="0" step="any" class="block mt-1 rounded-lg border-stone-200 text-[12px]"></label>
            <button class="eq-btn-mini">Hitung</button>
          </form>
        </Kartu>
      </section>

      <section class="grid gap-4 xl:grid-cols-2">
        <KartuGrafik judul="Jalur dekarbonisasi" :catatan="props.jalur.target.tahun ? `Target −${n(props.jalur.target.persen, 0)}% pada ${props.jalur.target.tahun} dari ${props.jalur.target.dasar}` : 'Target belum diatur.'" angka="tCO₂e/tahun">
          <Garis :label="labelJalur.map(String)" :deret="deretJalur" satuan="tCO₂e" />
          <template #tabel>
            <table><thead><tr><th>Inisiatif mitigasi</th><th>Target turun/thn</th><th>Capaian tahun ini</th></tr></thead>
              <tbody><tr v-for="k in props.jalur.kontribusi" :key="k.judul"><td>{{ k.judul }}</td><td class="num">{{ n(k.tco2e, 1) }}</td><td class="num">{{ n(k.capaian, 1) }}</td></tr></tbody></table>
          </template>
        </KartuGrafik>

        <Kartu judul="Simulasi" ikon="calculator" warna="langit" ket="Tidak menyimpan apa pun.">
          <form class="grid gap-2 grid-cols-2 sm:grid-cols-4" @submit.prevent="simulasi">
            <label class="text-[11px] font-semibold text-stone-500">Produksi ±%<input v-model="sim.produksi" type="number" step="any" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
            <label class="text-[11px] font-semibold text-stone-500">L/BCM ±%<input v-model="sim.l_bcm" type="number" step="any" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
            <label class="text-[11px] font-semibold text-stone-500">Harga solar ±%<input v-model="sim.harga_solar" type="number" step="any" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
            <label class="text-[11px] font-semibold text-stone-500">FAME %<input v-model="sim.fame" type="number" step="any" min="0" max="100" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
            <label class="text-[11px] font-semibold text-stone-500">PLN %<input v-model="sim.pln" type="number" step="any" min="0" max="100" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
            <label class="text-[11px] font-semibold text-stone-500">Genset %<input v-model="sim.genset" type="number" step="any" min="0" max="100" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
            <label class="text-[11px] font-semibold text-stone-500">PLTS %<input v-model="sim.plts" type="number" step="any" min="0" max="100" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
            <div class="flex items-end"><button class="eq-btn-lain w-full" style="padding:9px 14px">Hitung</button></div>
          </form>
          <p v-if="galatSim" class="text-[11px] text-red-600 mt-2">{{ galatSim }}</p>
          <table v-if="hasilSim" class="w-full text-[12px] mt-3">
            <thead><tr class="text-left text-stone-400 border-b border-stone-200"><th class="py-1.5"></th><th class="py-1.5 text-right">GJ</th><th class="py-1.5 text-right">tCO₂e</th><th class="py-1.5 text-right">Biaya</th></tr></thead>
            <tbody>
              <tr class="border-b border-stone-100"><td class="py-1.5">Sekarang</td><td class="py-1.5 text-right num">{{ n(hasilSim.dasar.gj, 0) }}</td><td class="py-1.5 text-right num">{{ n(hasilSim.dasar.tco2e, 1) }}</td><td class="py-1.5 text-right num">{{ rp(hasilSim.dasar.rupiah) }}</td></tr>
              <tr class="border-b border-stone-100"><td class="py-1.5">Simulasi</td><td class="py-1.5 text-right num">{{ n(hasilSim.skenario.gj, 0) }}</td><td class="py-1.5 text-right num">{{ n(hasilSim.skenario.tco2e, 1) }}</td><td class="py-1.5 text-right num">{{ rp(hasilSim.skenario.rupiah) }}</td></tr>
              <tr class="font-bold"><td class="py-1.5">Selisih</td>
                <td class="py-1.5 text-right num" :class="hasilSim.selisih.gj <= 0 ? 'text-emerald-700' : 'text-red-700'">{{ n(hasilSim.selisih.gj, 0) }}</td>
                <td class="py-1.5 text-right num" :class="hasilSim.selisih.tco2e <= 0 ? 'text-emerald-700' : 'text-red-700'">{{ n(hasilSim.selisih.tco2e, 1) }}</td>
                <td class="py-1.5 text-right num" :class="hasilSim.selisih.rupiah <= 0 ? 'text-emerald-700' : 'text-red-700'">{{ rp(hasilSim.selisih.rupiah) }}</td></tr>
            </tbody>
          </table>
        </Kartu>
      </section>
    </template>

    <!-- ═══ Telusur data ═══ -->
    <template v-else-if="tab === 'telusur'">
      <Kartu judul="Telusur angka" ikon="search-check" warna="biru" rapat>
        <nav class="flex flex-wrap items-center gap-1 text-[11.5px] px-5 pb-3" aria-label="Jejak telusur">
          <template v-for="(j, i) in jejak" :key="i">
            <button type="button" class="font-semibold" :class="i === jejak.length - 1 ? 'text-cam-ink' : 'text-cam-lime-deep hover:underline'" @click="naik(i)">{{ j.label }}</button>
            <span v-if="i < jejak.length - 1" class="text-stone-300">/</span>
          </template>
          <span v-if="sibukDrill" class="text-stone-400 ml-2">memuat…</span>
        </nav>
        <table class="w-full text-[12px]">
          <thead><tr class="text-left text-stone-400 border-y border-stone-200 bg-stone-50/70"><th :class="TH"></th><th :class="TH" class="text-right">GJ</th><th :class="TH" class="text-right">tCO₂e</th><th :class="TH" class="text-right">Biaya</th><th :class="TH" class="text-right">Terverifikasi</th></tr></thead>
          <tbody>
            <tr v-for="a in simpulKini.anak" :key="a.kunci" class="border-b border-stone-100 align-top">
              <td :class="TD">
                <button v-if="a.berikut" type="button" class="font-semibold text-cam-lime-deep hover:underline text-left" @click="turun(a)">{{ a.label }}</button>
                <span v-else class="font-semibold">{{ a.label }}</span>
                <span v-if="a.liter !== undefined" class="block text-[10.5px] text-stone-500">{{ n(a.liter, 0) }} L · {{ n(a.hm, 1) }} HM · FAME {{ n(a.persen_fame, 0) }}% · {{ a.status }}<template v-if="a.sumber_data"> · {{ a.sumber_data }}</template></span>
                <span v-if="a.faktor?.length" class="block text-[10px] text-stone-400">Faktor: {{ a.faktor.map((x: any) => `${x.kode_energi} ${x.parameter} ${x.nilai}`).join(' · ') }}</span>
                <span v-if="a.lengkap === false" class="block text-[10.5px] text-amber-700">belum punya faktor</span>
              </td>
              <td :class="TD" class="text-right num">{{ n(a.gj, 1) }}</td><td :class="TD" class="text-right num">{{ n(a.tco2e, 2) }}</td>
              <td :class="TD" class="text-right num">{{ rp(a.rupiah) }}</td><td :class="TD" class="text-right num">{{ persen(a.persen_terverifikasi, 0) }}</td>
            </tr>
          </tbody>
        </table>
        <p v-if="!simpulKini.anak?.length" class="eq-kosong eq-kosong-kecil">Tidak ada data.</p>
      </Kartu>

      <Kartu v-if="props.benchmark?.length" judul="Perbandingan antarperusahaan" ikon="chart-column" warna="amber" rapat>
        <table class="w-full text-[12px]">
          <thead><tr class="text-left text-stone-400 border-y border-stone-200 bg-stone-50/70"><th :class="TH">Perusahaan</th><th :class="TH" class="text-right">GJ</th><th :class="TH" class="text-right">GJ/ton</th><th :class="TH" class="text-right">tCO₂e</th><th :class="TH" class="text-right">tCO₂e/ton</th></tr></thead>
          <tbody><tr v-for="b in props.benchmark" :key="b.id" class="border-b border-stone-100 last:border-0"><td :class="TD" class="font-semibold">{{ b.nama }}</td><td :class="TD" class="text-right num">{{ ringkas(b.gj) }}</td><td :class="TD" class="text-right num">{{ n(b.gj_ton, 4) }}</td><td :class="TD" class="text-right num">{{ ringkas(b.tco2e) }}</td><td :class="TD" class="text-right num">{{ n(b.tco2e_ton, 5) }}</td></tr></tbody>
        </table>
      </Kartu>
    </template>

    <!-- ═══ Instruksi ═══ -->
    <template v-else>
      <Kartu judul="Instruksi direksi" ikon="clipboard-check" warna="oranye" rapat>
        <table class="w-full text-[12px]">
          <thead><tr class="text-left text-stone-400 border-y border-stone-200 bg-stone-50/70"><th :class="TH">Instruksi</th><th :class="TH">PIC</th><th :class="TH">Tenggat</th><th :class="TH">Status</th></tr></thead>
          <tbody>
            <tr v-for="t in props.instruksi" :key="t.id" class="border-b border-stone-100 last:border-0">
              <td :class="TD" class="font-semibold">{{ t.judul }}</td><td :class="TD">{{ t.penanggungJawab ?? t.penanggung_jawab ?? '—' }}</td>
              <td class="num whitespace-nowrap" :class="[TD, t.terlambat ? 'text-red-600 font-bold' : '']">{{ tgl(t.targetSelesai ?? t.target_selesai) }}</td>
              <td :class="TD">
                <select v-if="props.boleh.instruksi" :value="t.status" class="rounded-lg border-stone-200 text-[11.5px] py-1" aria-label="Status instruksi" @change="ubahInstruksi(t, ($event.target as HTMLSelectElement).value)">
                  <option value="rencana">Rencana</option><option value="berjalan">Berjalan</option><option value="selesai">Selesai</option><option value="batal">Batal</option>
                </select>
                <Lencana v-else :status="t.status" />
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="!props.instruksi?.length" class="eq-kosong eq-kosong-kecil">Belum ada instruksi.</p>
      </Kartu>

      <Kartu v-if="props.boleh.instruksi" judul="Instruksi baru" ikon="clipboard-check" warna="oranye">
        <form class="grid gap-2 sm:grid-cols-4" @submit.prevent="kirimInstruksi">
          <label class="text-[11px] font-semibold text-stone-500 sm:col-span-2">Judul<input v-model="instruksi.judul" required maxlength="190" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-semibold text-stone-500">PIC
            <input v-model="instruksi.pic" required maxlength="150" list="daftar-pic" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]">
            <datalist id="daftar-pic"><option v-for="u in props.pic" :key="u.id" :value="u.name"></option></datalist>
          </label>
          <label class="text-[11px] font-semibold text-stone-500">Tenggat<input v-model="instruksi.tenggat" required type="date" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-semibold text-stone-500 sm:col-span-3">Isi<textarea v-model="instruksi.isi" required rows="2" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></textarea></label>
          <label class="text-[11px] font-semibold text-stone-500">Prioritas<select v-model="instruksi.prioritas" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option value="rendah">Rendah</option><option value="sedang">Sedang</option><option value="tinggi">Tinggi</option><option value="kritis">Kritis</option></select></label>
          <p v-if="instruksi.konteks" class="sm:col-span-4 text-[11px] text-stone-500">Terkait: {{ instruksi.konteks }}</p>
          <div class="sm:col-span-4"><button class="eq-btn-utama" :disabled="instruksi.processing">Kirim</button></div>
        </form>
        <p v-for="(pesan, k) in instruksi.errors" :key="k" class="text-[11px] text-red-600 mt-1">{{ pesan }}</p>
      </Kartu>
    </template>
  </div>
</template>

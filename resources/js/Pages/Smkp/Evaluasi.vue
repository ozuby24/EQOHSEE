<script setup lang="ts">
/**
 * Evaluasi Audit SMKP — upgrade modul SMKP.
 *
 * Seluruh angka disusun di server (App\Support\SmkpEvaluasi) dari data
 * audit yang sudah diinput. Grafik interaktif memakai Chart.js yang dimuat
 * saat perlu (window.eqChartSiap): klik batang elemen/celah membuka
 * penilaiannya, klik irisan sebaran menyaring peta panas sub elemen.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import KepalaAudit from '../../Components/Smkp/KepalaAudit.vue';
import Ikon from '../../Components/Smkp/Ikon.vue';
import { angka, tanggal } from '../../smkp/lib/format';
import type { RingkasAudit } from '../../smkp/types';

interface Ruas { t?: string; b?: string }
interface El { k: string; n: string; bobot: number; c: number | null; lalu: number | null; nilai: number; maks: number; kontribusi: number }
interface SubPeta { k: string; n: string; c: number | null; kat: string; nilai: number; maks: number; lalu: number | null }
interface Evaluasi {
  ringkas: { skor: number | null; tingkat: { label: string; warna: string }; nilai: number; maks: number; dinilai: number; berlaku: number; lalu: { tahun: number; skor: number; id: number } | null };
  kategoriSub: Record<string, number>;
  elemen: El[];
  peta: Array<{ k: string; n: string; sub: SubPeta[] }>;
  celah: Array<{ k: string; n: string; el: string; kat: string; hilang: number; maks: number; c: number | null }>;
  temuan: {
    total: number; tutup: number; persenTutup: number | null; telat: number; hariTutup: number | null; tindakLanjut: number;
    jenis: Record<string, Record<string, number>>;
    terbuka: Array<{ id: number; nomor: string; kode: string; jenis: string; status: string; batas: string | null; hari: number | null }>;
  };
  kinerja: {
    nilai: Array<{ k: string; n: string; v: number | null; nasional: number; atas: boolean | null }>;
    kejadian: Array<{ k: string; n: string; v: number | null }>;
    sumber: string; tahunSe: number;
    saran: Record<string, boolean | null>;
  };
  keterkaitan: Array<{ a: string; b: string; label: string; kategoriA: string; kategoriB: string }>;
  formulir: { kesesuaian: number; otomatis: number; ofi: number };
  catatan: Array<{ ikon: string; nada: string; ruas: Ruas[] }>;
}

const props = defineProps<{ audit: RingkasAudit; evaluasi: Evaluasi; kategori: Record<string, string>; jumlahTemuan: number; bisaSunting: boolean }>();
const e = computed(() => props.evaluasi);
const id = props.audit.id;

const KAT: Record<string, { l: string; w: string; bg: string }> = {
  kesesuaian: { l: 'Kesesuaian', w: '#16A34A', bg: 'bg-hijau-500' },
  minor: { l: 'Minor', w: '#EAB308', bg: 'bg-amber-400' },
  mayor: { l: 'Mayor', w: '#EA580C', bg: 'bg-orange-600' },
  belum: { l: 'Belum tuntas dinilai', w: '#94A3B8', bg: 'bg-slate-400' },
  na: { l: 'N/A', w: '#CBD5E1', bg: 'bg-slate-200' },
};
const WARNA_TEMUAN: Record<string, string> = { kritikal: '#B91C1C', mayor: '#EA580C', minor: '#EAB308' };
const NADA: Record<string, string> = { baik: 'bg-hijau-50 text-hijau-700', sedang: 'bg-amber-50 text-amber-700', buruk: 'bg-rose-50 text-rose-700' };

const persen = (v: number | null | undefined, d = 1) => (v === null || v === undefined ? '—' : `${angka(v, d)}%`);
const selisih = computed(() => (e.value.ringkas.skor !== null && e.value.ringkas.lalu ? Math.round((e.value.ringkas.skor - e.value.ringkas.lalu.skor) * 100) / 100 : null));
const persenDinilai = computed(() => (e.value.ringkas.berlaku ? Math.round((e.value.ringkas.dinilai / e.value.ringkas.berlaku) * 100) : 0));
const urlNilai = (el: string, sub?: string) => `/smkp/${id}/elemen/${el}${sub ? `?sub=${encodeURIComponent(sub)}` : ''}`;

/* ───────── Peta panas: saring kategori ───────── */
const saring = ref<string | null>(null);
const bandingLalu = ref(true);
function warnaSel(s: SubPeta): string {
  if (s.kat === 'na') return '#E2E8F0';
  if (s.c === null) return '#F1F5F9';
  // Gradasi capaian: merah (0%) → kuning (75%) → hijau (100%).
  const c = Math.max(0, Math.min(100, s.c));
  const h = c < 75 ? (c / 75) * 45 : 45 + ((c - 75) / 25) * 95;
  return `hsl(${h.toFixed(0)} 72% ${c >= 100 ? 40 : 46}%)`;
}
const teksSel = (s: SubPeta) => (s.kat === 'na' ? 'N/A' : s.c === null ? '·' : `${Math.round(s.c)}`);
function judulSel(s: SubPeta): string {
  const lalu = s.lalu !== null ? ` · tahun lalu ${persen(s.lalu)}` : '';
  return `${s.k} ${s.n}\n${KAT[s.kat]?.l ?? ''} · capaian ${persen(s.c)} (${angka(s.nilai, 2)}/${s.maks} poin)${lalu}`;
}

/* ───────── Grafik ───────── */
const kElemen = ref<HTMLCanvasElement | null>(null);
const kSebar = ref<HTMLCanvasElement | null>(null);
const kCelah = ref<HTMLCanvasElement | null>(null);
const kTemuan = ref<HTMLCanvasElement | null>(null);
const kKinerja = ref<HTMLCanvasElement | null>(null);
let grafik: Record<string, any> = {};

const indeksKinerja = computed(() => e.value.kinerja.nilai.map((k) => (k.v === null || !k.nasional ? null : Math.round((k.v / k.nasional) * 1000) / 10)));
const adaKinerja = computed(() => indeksKinerja.value.some((v) => v !== null));

function gambar() {
  const siap = (window as any).eqChartSiap;
  if (typeof siap !== 'function') return;
  siap(() => {
    const C = (window as any).Chart;
    if (!C) return;
    Object.values(grafik).forEach((g) => g?.destroy());
    grafik = {};
    const d = e.value;
    const lbl = d.elemen.map((x) => x.k);

    if (kElemen.value) {
      const ds: any[] = [{ label: `Audit ${props.audit.tahun}`, data: d.elemen.map((x) => x.c), backgroundColor: d.elemen.map((x) => (x.c === null ? '#CBD5E1' : x.c >= 85 ? '#0E7490' : x.c >= 60 ? '#D97706' : '#DC2626')), borderRadius: 6, maxBarThickness: 34 }];
      if (bandingLalu.value && d.ringkas.lalu) ds.push({ label: `Audit ${d.ringkas.lalu.tahun}`, data: d.elemen.map((x) => x.lalu), backgroundColor: 'rgba(148,163,184,.55)', borderRadius: 6, maxBarThickness: 34 });
      ds.push({ type: 'line', label: 'Target 85%', data: lbl.map(() => 85), borderColor: '#16A34A', borderDash: [5, 4], borderWidth: 1.5, pointRadius: 0 });
      grafik.elemen = new C(kElemen.value, {
        type: 'bar', data: { labels: lbl, datasets: ds },
        options: {
          responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
          scales: { y: { min: 0, max: 100, ticks: { callback: (v: number) => `${v}%` } } },
          onHover: (ev: any, el: any[]) => { ev.native.target.style.cursor = el.length ? 'pointer' : 'default'; },
          onClick: (_: any, el: any[]) => { if (el.length) router.visit(urlNilai(d.elemen[el[0].index].k)); },
          plugins: {
            tooltip: {
              callbacks: {
                title: (x: any[]) => { const it = d.elemen[x[0]?.dataIndex ?? 0]; return it ? `Elemen ${it.k}. ${it.n}` : ''; },
                label: (x: any) => (x.dataset.type === 'line' ? 'Target 85%' : `${x.dataset.label}: ${persen(x.raw)}`),
                footer: (x: any[]) => { const it = d.elemen[x[0]?.dataIndex ?? 0]; return it ? `${angka(it.nilai, 2)} dari ${it.maks} poin · bobot ${angka(it.bobot, 0)}% · klik untuk membuka penilaian` : ''; },
              },
            },
          },
        },
      });
    }

    if (kSebar.value) {
      const kunci = Object.keys(KAT).filter((k) => (d.kategoriSub[k] ?? 0) > 0);
      grafik.sebar = new C(kSebar.value, {
        type: 'doughnut',
        data: { labels: kunci.map((k) => KAT[k].l), datasets: [{ data: kunci.map((k) => d.kategoriSub[k]), backgroundColor: kunci.map((k) => KAT[k].w), borderWidth: 2, borderColor: '#fff' }] },
        options: {
          responsive: true, maintainAspectRatio: false, cutout: '62%',
          onHover: (ev: any, el: any[]) => { ev.native.target.style.cursor = el.length ? 'pointer' : 'default'; },
          onClick: (_: any, el: any[]) => { if (el.length) { const k = kunci[el[0].index]; saring.value = saring.value === k ? null : k; } },
          plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } }, tooltip: { callbacks: { label: (x: any) => `${x.label}: ${x.raw} sub elemen` } } },
        },
      });
    }

    if (kCelah.value && d.celah.length) {
      grafik.celah = new C(kCelah.value, {
        type: 'bar',
        data: { labels: d.celah.map((x) => x.k), datasets: [{ label: 'Poin hilang', data: d.celah.map((x) => x.hilang), backgroundColor: d.celah.map((x) => KAT[x.kat]?.w ?? '#64748B'), borderRadius: 5 }] },
        options: {
          indexAxis: 'y', responsive: true, maintainAspectRatio: false,
          scales: { x: { beginAtZero: true, title: { display: true, text: 'poin hilang' } } },
          onHover: (ev: any, el: any[]) => { ev.native.target.style.cursor = el.length ? 'pointer' : 'default'; },
          onClick: (_: any, el: any[]) => { if (el.length) { const x = d.celah[el[0].index]; router.visit(urlNilai(x.el, x.k)); } },
          plugins: { legend: { display: false }, tooltip: { callbacks: { title: (x: any[]) => { const it = d.celah[x[0]?.dataIndex ?? 0]; return it ? `${it.k} ${it.n}` : ''; }, label: (x: any) => { const it = d.celah[x.dataIndex]; return `${angka(it.hilang, 2)} dari ${it.maks} poin hilang · capaian ${persen(it.c)}`; } } } },
        },
      });
    }

    if (kTemuan.value && d.temuan.total) {
      const jenis = Object.keys(props.kategori);
      const st = [['Open', 'Terbuka', '#DC2626'], ['In Progress', 'Dalam proses', '#F59E0B'], ['Closed', 'Ditutup', '#16A34A']];
      grafik.temuan = new C(kTemuan.value, {
        type: 'bar',
        data: { labels: jenis.map((j) => props.kategori[j]), datasets: st.map(([k, l, w]) => ({ label: l, data: jenis.map((j) => d.temuan.jenis[j]?.[k] ?? 0), backgroundColor: w, borderRadius: 4 })) },
        options: {
          responsive: true, maintainAspectRatio: false, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } },
          onHover: (ev: any, el: any[]) => { ev.native.target.style.cursor = el.length ? 'pointer' : 'default'; },
          onClick: (_: any, el: any[]) => { if (el.length) router.visit(`/smkp/${id}/temuan`); },
          plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
        },
      });
    }

    if (kKinerja.value && adaKinerja.value) {
      const n = d.kinerja.nilai;
      grafik.kinerja = new C(kKinerja.value, {
        type: 'bar',
        data: {
          labels: n.map((x) => x.k.toUpperCase()),
          datasets: [
            { label: 'Perusahaan (indeks thd nasional)', data: indeksKinerja.value, backgroundColor: n.map((x) => (x.atas ? '#E11D48' : '#0E7490')), borderRadius: 6, maxBarThickness: 46 },
            { type: 'line', label: 'Rata-rata nasional = 100', data: n.map(() => 100), borderColor: '#64748B', borderDash: [5, 4], borderWidth: 1.5, pointRadius: 0 },
          ],
        },
        options: {
          responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { callback: (v: number) => `${v}` } } },
          plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } },
            tooltip: { callbacks: { title: (x: any[]) => n[x[0]?.dataIndex ?? 0]?.n ?? '', label: (x: any) => { const it = n[x.dataIndex]; return x.dataset.type === 'line' ? `Nasional: ${angka(it.nasional, 2)}` : `Perusahaan: ${it.v === null ? '—' : angka(it.v, 2)} (indeks ${angka(x.raw, 1)})`; } } },
          },
        },
      });
    }
  });
}

watch(bandingLalu, () => nextTick(gambar));
watch(() => props.evaluasi, () => nextTick(gambar));
onMounted(gambar);
onBeforeUnmount(() => Object.values(grafik).forEach((g) => g?.destroy()));

const hariTeks = (h: number | null) => (h === null ? 'tanpa batas waktu' : h < 0 ? `lewat ${-h} hari` : h === 0 ? 'jatuh tempo hari ini' : `${h} hari lagi`);
const SARAN: Record<string, string> = { kecelakaan: 'Kondisi 4 — FR & SR di atas nasional', penyakit: 'Kondisi 5 — ASR & MFR di atas nasional', pak: 'Kondisi 7 — ada kejadian PAK/penyakit tenaga kerja' };
</script>

<template>
  <div class="smkp-ui">
    <Head title="Evaluasi Audit" />
    <KepalaAudit :audit="audit" aktif="evaluasi" :bisa-sunting="bisaSunting" :jumlah-temuan="jumlahTemuan" />

    <!-- KPI -->
    <section class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
      <div class="kartu p-4 col-span-2 lg:col-span-1">
        <p class="text-[12px] text-slate-500">Tingkat pencapaian</p>
        <p class="text-[30px] font-bold tabular-nums leading-tight" :style="{ color: e.ringkas.tingkat.warna }">{{ persen(e.ringkas.skor, 2) }}</p>
        <p class="text-[11.5px] font-semibold" :style="{ color: e.ringkas.tingkat.warna }">{{ e.ringkas.tingkat.label }}</p>
        <p v-if="selisih !== null" class="mt-0.5 text-[11.5px]" :class="selisih >= 0 ? 'text-hijau-700' : 'text-rose-600'">
          {{ selisih >= 0 ? '▲' : '▼' }} {{ angka(Math.abs(selisih), 2) }} poin vs {{ e.ringkas.lalu?.tahun }}
        </p>
      </div>
      <div class="kartu p-4">
        <p class="text-[12px] text-slate-500">Butir dinilai</p>
        <p class="text-[26px] font-bold text-navy-900 tabular-nums leading-tight">{{ e.ringkas.dinilai }}<span class="text-[14px] text-slate-400"> / {{ e.ringkas.berlaku }}</span></p>
        <span class="bilah mt-1.5 block w-full"><i class="bg-teal-500" :style="{ width: `${persenDinilai}%` }" /></span>
      </div>
      <div class="kartu p-4">
        <p class="text-[12px] text-slate-500">Sub elemen</p>
        <div class="mt-1 flex flex-wrap gap-1">
          <button v-for="k in ['kesesuaian', 'minor', 'mayor']" :key="k" type="button" class="lencana tabular-nums transition" :class="saring === k ? 'ring-2 ring-offset-1 ring-navy-300' : ''"
                  :style="{ background: `${KAT[k].w}1A`, color: KAT[k].w }" @click="saring = saring === k ? null : k">{{ e.kategoriSub[k] ?? 0 }} {{ KAT[k].l }}</button>
        </div>
        <p class="mt-1 text-[11px] text-slate-400">Klik untuk menyaring peta panas.</p>
      </div>
      <div class="kartu p-4">
        <p class="text-[12px] text-slate-500">Temuan ditutup</p>
        <p class="text-[26px] font-bold text-navy-900 tabular-nums leading-tight">{{ e.temuan.tutup }}<span class="text-[14px] text-slate-400"> / {{ e.temuan.total }}</span></p>
        <p class="text-[11.5px]" :class="e.temuan.telat ? 'text-rose-600 font-semibold' : 'text-slate-500'">{{ e.temuan.telat ? `${e.temuan.telat} lewat batas waktu` : e.temuan.hariTutup !== null ? `rata-rata ${angka(e.temuan.hariTutup, 1)} hari` : 'tidak ada yang terlambat' }}</p>
      </div>
      <div class="kartu p-4">
        <p class="text-[12px] text-slate-500">Formulir Kesesuaian & OFI</p>
        <p class="text-[26px] font-bold text-navy-900 tabular-nums leading-tight">{{ e.formulir.kesesuaian }}<span class="text-[14px] text-slate-400"> uraian</span></p>
        <p class="text-[11.5px] text-slate-500">{{ e.formulir.otomatis }} otomatis dari penilaian · {{ e.formulir.ofi }} OFI</p>
      </div>
    </section>

    <!-- Catatan evaluasi otomatis -->
    <section v-if="e.catatan.length" class="kartu p-5 sm:p-6 mb-5">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="kartu-judul flex items-center gap-2"><Ikon nama="kilau" class="w-4 h-4 text-teal-600" /> Catatan evaluasi otomatis</h2>
        <Link :href="`/smkp/${id}/laporan`" class="btn-garis btn-kecil"><Ikon nama="cetak" class="w-4 h-4" /> Laporan Audit</Link>
      </div>
      <ul class="mt-3 grid gap-2 md:grid-cols-2">
        <li v-for="(c, i) in e.catatan" :key="i" class="flex items-start gap-3 rounded-xl border border-slate-100 px-3.5 py-3">
          <span class="grid place-items-center w-8 h-8 shrink-0 rounded-lg" :class="NADA[c.nada] ?? NADA.sedang"><Ikon :nama="c.ikon" class="w-4 h-4" /></span>
          <p class="text-[13px] leading-relaxed text-slate-700"><template v-for="(r, j) in c.ruas" :key="j"><b v-if="r.b" class="text-navy-900">{{ r.b }}</b><template v-else>{{ r.t }}</template></template></p>
        </li>
      </ul>
    </section>

    <!-- Capaian elemen & sebaran -->
    <section class="grid xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] gap-5 mb-5">
      <div class="kartu p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-2">
          <div><h2 class="kartu-judul">Capaian per elemen</h2><p class="kartu-sub">Klik batang untuk membuka penilaian elemennya.</p></div>
          <label v-if="e.ringkas.lalu" class="inline-flex cursor-pointer items-center gap-2 text-[12.5px] font-medium text-slate-600">
            <input v-model="bandingLalu" type="checkbox" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Bandingkan dengan {{ e.ringkas.lalu.tahun }}
          </label>
        </div>
        <div class="mt-4 h-[300px]"><canvas ref="kElemen" aria-label="Grafik capaian per elemen" role="img" /></div>
        <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2">
          <Link v-for="x in e.elemen" :key="x.k" :href="urlNilai(x.k)" class="rounded-lg border border-slate-100 px-2.5 py-2 hover:border-brand-300 hover:bg-brand-50/40 transition">
            <p class="text-[11px] font-semibold text-slate-500">Elemen {{ x.k }}</p>
            <p class="text-[15px] font-bold tabular-nums text-navy-900">{{ persen(x.c) }}</p>
            <p v-if="x.c !== null && x.lalu !== null" class="text-[11px] tabular-nums" :class="x.c - x.lalu >= 0 ? 'text-hijau-700' : 'text-rose-600'">{{ x.c - x.lalu >= 0 ? '+' : '−' }}{{ angka(Math.abs(x.c - x.lalu), 1) }}</p>
          </Link>
        </div>
      </div>
      <div class="kartu p-5 sm:p-6">
        <h2 class="kartu-judul">Sebaran kategori sub elemen</h2>
        <p class="kartu-sub">Klik irisan untuk menyaring peta panas di bawah.</p>
        <div class="mt-3 h-[300px]"><canvas ref="kSebar" aria-label="Grafik sebaran kategori sub elemen" role="img" /></div>
      </div>
    </section>

    <!-- Peta panas sub elemen -->
    <section class="kartu p-5 sm:p-6 mb-5">
      <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
          <h2 class="kartu-judul">Peta panas capaian sub elemen</h2>
          <p class="kartu-sub">Angka = capaian (%). Arahkan kursor untuk rincian; klik sel untuk membuka penilaiannya.</p>
        </div>
        <div class="flex flex-wrap items-center gap-1.5">
          <button v-for="(v, k) in KAT" :key="k" type="button" class="lencana transition" :class="saring === k ? 'ring-2 ring-offset-1 ring-navy-300' : saring ? 'opacity-50' : ''"
                  :style="{ background: `${v.w}1F`, color: k === 'na' ? '#64748B' : v.w }" @click="saring = saring === k ? null : String(k)">{{ v.l }}</button>
          <button v-if="saring" type="button" class="text-[12px] font-semibold text-teal-700 hover:underline" @click="saring = null">Tampilkan semua</button>
        </div>
      </div>
      <div class="mt-4 space-y-2.5">
        <div v-for="el in e.peta" :key="el.k" class="grid grid-cols-[64px_1fr] sm:grid-cols-[150px_1fr] items-start gap-3">
          <Link :href="urlNilai(el.k)" class="pt-1 text-[12px] font-semibold leading-tight text-navy-900 hover:text-brand-700">
            <span class="tabular-nums">{{ el.k }}</span><span class="hidden sm:block text-[11px] font-normal text-slate-500">{{ el.n }}</span>
          </Link>
          <div class="flex flex-wrap gap-1.5">
            <Link v-for="s in el.sub" :key="s.k" :href="urlNilai(el.k, s.k)" :title="judulSel(s)"
                  class="group relative grid h-11 w-[52px] place-items-center rounded-lg text-[11.5px] font-bold tabular-nums transition hover:scale-[1.06] hover:shadow-md"
                  :class="[saring && s.kat !== saring ? 'opacity-20' : '', s.c === null || s.kat === 'na' ? 'text-slate-500' : 'text-white']"
                  :style="{ background: warnaSel(s) }">
              <span class="leading-none">{{ teksSel(s) }}</span>
              <span class="absolute bottom-0.5 text-[8.5px] font-semibold opacity-80">{{ s.k }}</span>
            </Link>
          </div>
        </div>
      </div>
      <div class="mt-4 flex items-center gap-2 text-[11px] text-slate-500">
        <span>0%</span><span class="h-2 w-40 rounded-full" style="background: linear-gradient(90deg, hsl(0 72% 46%), hsl(45 72% 46%), hsl(140 72% 40%))" /><span>100%</span>
      </div>
    </section>

    <!-- Celah & temuan -->
    <section class="grid xl:grid-cols-2 gap-5 mb-5">
      <div class="kartu p-5 sm:p-6">
        <h2 class="kartu-judul">10 sub elemen dengan poin hilang terbanyak</h2>
        <p class="kartu-sub">Prioritas perbaikan; klik batang untuk membuka penilaiannya.</p>
        <div v-if="e.celah.length" class="mt-3 h-[320px]"><canvas ref="kCelah" aria-label="Grafik celah poin sub elemen" role="img" /></div>
        <p v-else class="mt-6 text-center text-[13px] text-slate-500">Seluruh sub elemen yang dinilai mencapai poin penuh.</p>
      </div>
      <div class="kartu p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-2">
          <div><h2 class="kartu-judul">Temuan &amp; tindak lanjut</h2><p class="kartu-sub">{{ e.temuan.total }} ketidaksesuaian · {{ e.temuan.tindakLanjut }} kiriman tindak lanjut auditi.</p></div>
          <Link :href="`/smkp/${id}/temuan`" class="btn-garis btn-kecil"><Ikon nama="clipboard-check" class="w-4 h-4" /> Temuan &amp; CAR</Link>
        </div>
        <div v-if="e.temuan.total" class="mt-3 h-[200px]"><canvas ref="kTemuan" aria-label="Grafik temuan per kategori dan status" role="img" /></div>
        <p v-else class="mt-6 text-center text-[13px] text-slate-500">Belum ada temuan ketidaksesuaian.</p>
        <ul v-if="e.temuan.terbuka.length" class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-100">
          <li v-for="t in e.temuan.terbuka" :key="t.id" class="flex items-center gap-3 px-3 py-2">
            <i class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ background: WARNA_TEMUAN[t.jenis] ?? '#64748B' }" />
            <div class="min-w-0 flex-1">
              <p class="truncate text-[12.5px] font-semibold text-navy-900">{{ t.nomor }} <span class="font-normal text-slate-500">· {{ t.kode }}</span></p>
              <p class="text-[11.5px] text-slate-500">{{ t.status === 'In Progress' ? 'Dalam proses' : 'Terbuka' }} · batas {{ tanggal(t.batas) }}</p>
            </div>
            <span class="lencana shrink-0 tabular-nums" :class="t.hari !== null && t.hari < 0 ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-600'">{{ hariTeks(t.hari) }}</span>
          </li>
        </ul>
      </div>
    </section>

    <!-- Kinerja keselamatan & keterkaitan -->
    <section class="grid xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] gap-5">
      <div class="kartu p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-2">
          <div>
            <h2 class="kartu-judul">Kinerja keselamatan vs rata-rata nasional</h2>
            <p class="kartu-sub">Indeks = nilai perusahaan ÷ rata-rata nasional × 100 ({{ e.kinerja.sumber }}). Di atas 100 berarti lebih tinggi dari nasional.</p>
          </div>
          <Link :href="`/smkp/${id}/tahap-1`" class="btn-garis btn-kecil"><Ikon nama="pensil" class="w-4 h-4" /> Data kinerja</Link>
        </div>
        <div v-if="adaKinerja" class="mt-3 h-[230px]"><canvas ref="kKinerja" aria-label="Grafik kinerja keselamatan terhadap rata-rata nasional" role="img" /></div>
        <p v-else class="mt-6 text-center text-[13px] text-slate-500">FR, SR, ASR, dan MFR belum diisi di Tahap I bagian B.</p>
        <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-2">
          <div v-for="k in e.kinerja.nilai" :key="k.k" class="rounded-xl border px-3 py-2" :class="k.atas ? 'border-rose-200 bg-rose-50/50' : 'border-slate-100'">
            <p class="text-[11px] font-semibold text-slate-500">{{ k.k.toUpperCase() }}</p>
            <p class="text-[16px] font-bold tabular-nums text-navy-900">{{ k.v === null ? '—' : angka(k.v, 2) }}</p>
            <p class="text-[11px] tabular-nums text-slate-500">nasional {{ angka(k.nasional, 2) }}</p>
          </div>
        </div>
        <div class="mt-3 flex flex-wrap gap-1.5">
          <span v-for="(v, k) in e.kinerja.saran" :key="k" class="lencana" :class="v === null ? 'bg-slate-100 text-slate-500' : v ? 'bg-rose-50 text-rose-700' : 'bg-hijau-50 text-hijau-700'">
            {{ SARAN[k] }}: {{ v === null ? 'data belum cukup' : v ? 'terpenuhi' : 'tidak terpenuhi' }}
          </span>
        </div>
      </div>
      <div class="kartu p-5 sm:p-6">
        <h2 class="kartu-judul flex items-center gap-2"><Ikon nama="cabang" class="w-4 h-4 text-slate-500" /> Hasil elemen terkait yang tidak sejalan</h2>
        <p class="kartu-sub">Kesesuaian di satu sisi dan Ketidaksesuaian Mayor di sisi dasarnya — periksa kembali bukti penilaiannya.</p>
        <ul v-if="e.keterkaitan.length" class="mt-3 space-y-2">
          <li v-for="(t, i) in e.keterkaitan" :key="i" class="rounded-xl border border-amber-100 bg-amber-50/50 px-3 py-2.5">
            <p class="flex flex-wrap items-center gap-1.5 text-[12.5px] font-semibold">
              <Link :href="urlNilai(t.a.split('.')[0], t.a.split('.').slice(0, 2).join('.'))" class="rounded-md px-1.5 py-0.5" :style="{ background: `${KAT[t.kategoriA]?.w}22`, color: KAT[t.kategoriA]?.w }">{{ t.a }}</Link>
              <span class="text-slate-400">↔</span>
              <Link :href="urlNilai(t.b.split('.')[0], t.b.split('.').slice(0, 2).join('.'))" class="rounded-md px-1.5 py-0.5" :style="{ background: `${KAT[t.kategoriB]?.w}22`, color: KAT[t.kategoriB]?.w }">{{ t.b }}</Link>
            </p>
            <p class="mt-1 text-[12px] text-slate-600">{{ t.label }}</p>
          </li>
        </ul>
        <p v-else class="mt-6 flex items-center justify-center gap-2 text-[13px] text-hijau-700"><Ikon nama="centang-bulat" class="w-4 h-4" /> Tidak ada hasil yang bertentangan.</p>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
/**
 * Dasbor Audit SMKP — desain v2 mengikuti dasbor DBEST.
 *
 * Seluruh angka disusun di server (App\Support\SmkpDasbor) dan sudah
 * tersaring batas perusahaan. Halaman ini hanya menggambar: KPI per babak,
 * nilai akhir & perubahan terhadap tahun pembanding, sorotan otomatis,
 * radar tujuh elemen, temuan per elemen & kategori, analisis temuan &
 * tindak lanjut per lingkup, tindakan mendesak, dan agenda 30 hari.
 *
 * Chart.js dimuat saat dibutuhkan lewat `window.eqChartSiap` (resources/
 * js/bagan.ts) — sama dengan halaman PTPKKP — bukan ikut bundel masuk.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import KepalaHalaman from '../../Components/Smkp/KepalaHalaman.vue';
import Ikon from '../../Components/Smkp/Ikon.vue';
import Kosong from '../../Components/Smkp/Kosong.vue';
import { angka } from '../../smkp/lib/format';

interface Elemen { k: string; n: string; bobot: number; poin: number }
interface Ruas { t?: string; b?: string }
interface Perusahaan {
  id: number; nama: string; judul: string; kat: string; jenis: string; status: string; langkah: number; babak: string;
  skor: number | null; tingkat: string; poin: number; maks: number; dinilaiButir: number; berlaku: number;
  el: Array<number | null>; elPoin: Array<[number, number] | null>; lalu: number | null;
  temuan: number; buka: number; telat: number; bukti: number; buktiTerima: number; buktiTunggu: number; lead: string;
  lanjut: { t: string; nada: string }; url: { ringkasan: string; nilai: string; temuan: string; laporan: string };
}
interface Seri { label: string[]; dibuat: number[]; ditutup: number[]; rasio: Array<number | null> }
interface BarisTindakan { nomor: string; kode: string; kriteria: string; pt: string; batas: string | null; jenis: string; jenisK: string; hari: number; url: string }
interface Dasbor {
  tahun: number; banding: number | null; target: number; batas: number; elemen: Elemen[]; poinTotal: number;
  kategori: Record<string, string>;
  perusahaan: Perusahaan[];
  kpi: {
    audit: number; langkah: Record<string, number>; dinilai: number; rata: number | null; rataBanding: number | null;
    temuan: { total: number; buka: number; tutup: number; telat: number; bukaJenis: Record<string, number>; hariTutup: number | null; persenTutup: number };
    bukti: Record<string, number>;
  };
  sebar: Record<string, number>;
  elRata: Array<number | null>; elRataBanding: Array<number | null>;
  temuanElemen: Record<string, number[]>;
  analisis: {
    pilihan: Array<{ k: string; n: string }>;
    top: Record<string, Array<{ k: string; n: string; total: number; kritikal: number; mayor: number; minor: number; buka: number }>>;
    status: Record<string, { selesai: number; proses: number; telat: number }>;
    tren: Record<string, { bulan: Seri; kuartal: Seri; tahun: Seri }>;
  };
  tindakan: { telat: BarisTindakan[]; jumlahTelat: number; tenggat: BarisTindakan[]; jumlahTenggat: number };
  agenda: Array<{ tg: string; judul: string; pt: string; label: string; nada: string; url: string; hari: number; bulan: string }>;
  tren: { label: string[]; nilai: Array<number | null> };
  sorotan: Array<{ ikon: string; ruas: Ruas[] }>;
}

const props = defineProps<{
  dasbor: Dasbor;
  tahunAda: number[];
  pilihanBanding: number[];
  bisaSunting: boolean;
}>();

const d = computed(() => props.dasbor);
const satu = (v: number | null | undefined) => angka(v ?? null, 1);

/* ───────── Saringan periode & pembanding ───────── */
const tahun = ref<number>(props.dasbor.tahun);
const banding = ref<string>(props.dasbor.banding ? String(props.dasbor.banding) : 'tidak');
function terapkan() {
  router.get('/smkp/dasbor', { tahun: tahun.value, banding: banding.value }, { preserveScroll: true });
}
const cetak = () => window.print();
const eksporUrl = computed(() => `/smkp/dasbor/ekspor?tahun=${d.value.tahun}&banding=${d.value.banding ?? 'tidak'}`);

/* ───────── Nilai & tingkat ───────── */
function warnaSkor(v: number | null): string {
  if (v === null) return '#94A3B8';
  if (v >= d.value.target) return '#16A34A';
  if (v >= d.value.batas) return '#D97706';
  return '#DC2626';
}
const selisih = computed(() => (d.value.kpi.rata !== null && d.value.kpi.rataBanding !== null ? d.value.kpi.rata - d.value.kpi.rataBanding : null));
function teksSelisih(v: number | null): string {
  if (v === null) return '';
  return `${v >= 0 ? '+' : '−'}${satu(Math.abs(v))} p.p`;
}

const urut = ref<'skor' | 'nama' | 'temuan'>('skor');
const daftar = computed(() => {
  const x = [...d.value.perusahaan];
  if (urut.value === 'nama') x.sort((a, b) => a.nama.localeCompare(b.nama, 'id'));
  else if (urut.value === 'temuan') x.sort((a, b) => b.buka - a.buka || b.temuan - a.temuan);
  else x.sort((a, b) => (a.skor ?? 999) - (b.skor ?? 999)); // terendah dulu: yang perlu perhatian terbaca lebih dulu
  return x;
});
const buka = ref<number | null>(null);

const SEBAR: Array<[string, string]> = [
  ['Baik', 'bg-hijau-500'], ['Perlu Perbaikan', 'bg-amber-500'], ['Perlu Perhatian Serius', 'bg-rose-500'], ['Belum dinilai', 'bg-slate-300'],
];
const IKON_SOROT: Record<string, { nama: string; kelas: string }> = {
  ok: { nama: 'centang-bulat', kelas: 'text-hijau-600' },
  info: { nama: 'info', kelas: 'text-brand-600' },
  awas: { nama: 'peringatan', kelas: 'text-rose-600' },
};
const WARNA_KAT: Record<string, string> = { kritikal: '#B91C1C', mayor: '#EA580C', minor: '#EAB308' };

/* ───────── Analisis temuan ───────── */
const lingkup = ref('semua');
const skala = ref<'bulan' | 'kuartal' | 'tahun'>('bulan');
const top = computed(() => d.value.analisis.top[lingkup.value] ?? []);
const statusTl = computed(() => d.value.analisis.status[lingkup.value] ?? { selesai: 0, proses: 0, telat: 0 });
const totalTl = computed(() => statusTl.value.selesai + statusTl.value.proses + statusTl.value.telat);
const trenTl = computed(() => d.value.analisis.tren[lingkup.value]?.[skala.value]);

/* ───────── Grafik (Chart.js, dimuat saat perlu) ───────── */
const kRadar = ref<HTMLCanvasElement | null>(null);
const kTemuan = ref<HTMLCanvasElement | null>(null);
const kTren = ref<HTMLCanvasElement | null>(null);
const kTl = ref<HTMLCanvasElement | null>(null);
let grafik: Record<string, any> = {};

function gambar() {
  const siap = (window as any).eqChartSiap;
  if (typeof siap !== 'function') return;
  siap(() => {
    const C = (window as any).Chart;
    if (!C) return;
    Object.values(grafik).forEach((g) => g?.destroy());
    grafik = {};
    const label = d.value.elemen.map((e) => e.k);

    if (kRadar.value) {
      const ds: any[] = [
        { label: `Rata-rata ${d.value.tahun}`, data: d.value.elRata, borderColor: '#0E7490', backgroundColor: 'rgba(14,116,144,.18)', pointRadius: 3 },
      ];
      if (d.value.banding) ds.push({ label: `Rata-rata ${d.value.banding}`, data: d.value.elRataBanding, borderColor: '#94A3B8', backgroundColor: 'rgba(148,163,184,.10)', borderDash: [4, 4], pointRadius: 2 });
      ds.push({ label: `Target ${d.value.target}%`, data: label.map(() => d.value.target), borderColor: '#16A34A', backgroundColor: 'transparent', borderWidth: 1, pointRadius: 0 });
      grafik.radar = new C(kRadar.value, {
        type: 'radar', data: { labels: label, datasets: ds },
        options: {
          responsive: true, maintainAspectRatio: false,
          scales: { r: { min: 0, max: 100, ticks: { stepSize: 20, backdropColor: 'transparent' } } },
          // Label sumbu cukup kode elemen agar radarnya lega; nama lengkap di tooltip.
          plugins: { tooltip: { callbacks: { title: (x: any[]) => { const e = d.value.elemen[x[0]?.dataIndex ?? 0]; return e ? `${e.k}. ${e.n}` : ''; } } } },
        },
      });
    }

    if (kTemuan.value) {
      grafik.temuan = new C(kTemuan.value, {
        type: 'bar',
        data: { labels: label, datasets: Object.entries(d.value.kategori).map(([k, l]) => ({ label: l, data: d.value.temuanElemen[k] ?? [], backgroundColor: WARNA_KAT[k] ?? '#64748B', borderRadius: 4 })) },
        options: { responsive: true, maintainAspectRatio: false, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } } },
      });
    }

    if (kTren.value) {
      grafik.tren = new C(kTren.value, {
        type: 'line',
        data: { labels: d.value.tren.label, datasets: [{ label: 'Rata-rata nilai akhir (%)', data: d.value.tren.nilai, borderColor: '#0E7490', backgroundColor: 'rgba(14,116,144,.12)', fill: true, tension: .3, spanGaps: true }] },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { min: 0, max: 100 } } },
      });
    }

    gambarTl(C);
  });
}

function gambarTl(C?: any) {
  C ??= (window as any).Chart;
  if (!C || !kTl.value || !trenTl.value) return;
  grafik.tl?.destroy();
  const s = trenTl.value;
  grafik.tl = new C(kTl.value, {
    data: {
      labels: s.label,
      datasets: [
        { type: 'bar', label: 'Dibuat', data: s.dibuat, backgroundColor: '#F59E0B', borderRadius: 4, yAxisID: 'y' },
        { type: 'bar', label: 'Ditutup', data: s.ditutup, backgroundColor: '#16A34A', borderRadius: 4, yAxisID: 'y' },
        { type: 'line', label: 'Tingkat penyelesaian (%)', data: s.rasio, borderColor: '#0E7490', yAxisID: 'y1', tension: .3, spanGaps: true },
      ],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, y1: { position: 'right', min: 0, max: 100, grid: { drawOnChartArea: false } } },
    },
  });
}

watch([lingkup, skala], () => nextTick(() => gambarTl()));
watch(() => props.dasbor, () => nextTick(gambar));
onMounted(gambar);
onBeforeUnmount(() => Object.values(grafik).forEach((g) => g?.destroy()));
</script>

<template>
  <div class="smkp-ui">
    <Head title="Dasbor Audit SMKP" />
    <KepalaHalaman judul="Dasbor Audit SMKP" :sub="`Periode ${d.tahun}${d.banding ? ` · dibandingkan dengan ${d.banding}` : ''} · ${d.kpi.audit} audit dalam jangkauan Anda.`"
                   :jejak="[{ label: 'Audit SMKP', url: '/smkp' }, { label: 'Dasbor' }]">
      <a :href="eksporUrl" class="btn-garis"><Ikon nama="unduh" class="w-4 h-4" /> Ekspor Excel</a>
      <button type="button" class="btn-garis no-print" @click="cetak"><Ikon nama="cetak" class="w-4 h-4" /> Cetak</button>
      <Link v-if="bisaSunting" href="/smkp/buat" class="btn-utama"><Ikon nama="tambah" class="w-4 h-4" /> Audit Baru</Link>
    </KepalaHalaman>

    <form class="kartu flex flex-wrap items-end gap-3 p-4 mb-5 no-print" @submit.prevent="terapkan">
      <label class="min-w-[140px]">
        <span class="label block">Periode</span>
        <select v-model.number="tahun" class="input">
          <option v-for="t in tahunAda" :key="t" :value="t">{{ t }}</option>
        </select>
      </label>
      <label class="min-w-[180px]">
        <span class="label block">Bandingkan dengan</span>
        <select v-model="banding" class="input">
          <option value="tidak">Tanpa pembanding</option>
          <option v-for="t in pilihanBanding" :key="t" :value="String(t)">{{ t }}</option>
        </select>
      </label>
      <button class="btn-garis"><Ikon nama="saring" class="w-4 h-4" /> Terapkan</button>
    </form>

    <Kosong v-if="!d.kpi.audit" judul="Belum ada audit pada periode ini"
            :pesan="`Tidak ada audit SMKP tahun ${d.tahun} dalam jangkauan Anda. Pilih periode lain atau buat audit baru.`" ikon="grafik" />

    <template v-else>
      <!-- KPI -->
      <section class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
        <div class="kartu p-4">
          <p class="text-[12px] text-slate-500">Audit periode</p>
          <p class="text-[26px] font-bold text-navy-900 tabular-nums leading-tight">{{ d.kpi.audit }}</p>
          <p class="text-[11.5px] text-slate-500 leading-snug">
            Tahap I {{ d.kpi.langkah[1] }} · Rencana {{ d.kpi.langkah[2] }} · Lapangan {{ d.kpi.langkah[3] }} · Pelaporan {{ d.kpi.langkah[4] }}
          </p>
        </div>
        <div class="kartu p-4">
          <p class="text-[12px] text-slate-500">Rata-rata nilai akhir</p>
          <p class="text-[26px] font-bold tabular-nums leading-tight" :style="{ color: warnaSkor(d.kpi.rata) }">{{ d.kpi.rata !== null ? satu(d.kpi.rata) + '%' : '—' }}</p>
          <p class="text-[11.5px] leading-snug" :class="selisih === null ? 'text-slate-500' : selisih >= 0 ? 'text-hijau-700' : 'text-rose-600'">
            {{ selisih === null ? `${d.kpi.dinilai} audit dinilai` : `${teksSelisih(selisih)} vs ${d.banding}` }}
          </p>
        </div>
        <div class="kartu p-4">
          <p class="text-[12px] text-slate-500">Temuan terbuka</p>
          <p class="text-[26px] font-bold text-navy-900 tabular-nums leading-tight">{{ d.kpi.temuan.buka }}<span class="text-[14px] text-slate-400"> / {{ d.kpi.temuan.total }}</span></p>
          <p class="text-[11.5px] leading-snug" :class="d.kpi.temuan.telat ? 'text-rose-600 font-semibold' : 'text-slate-500'">
            {{ d.kpi.temuan.telat ? `${d.kpi.temuan.telat} lewat target` : 'Tidak ada yang lewat target' }}
          </p>
        </div>
        <div class="kartu p-4">
          <p class="text-[12px] text-slate-500">Tindak lanjut ditutup</p>
          <p class="text-[26px] font-bold text-navy-900 tabular-nums leading-tight">{{ d.kpi.temuan.persenTutup }}%</p>
          <p class="text-[11.5px] text-slate-500 leading-snug">{{ d.kpi.temuan.hariTutup !== null ? `rata-rata ${d.kpi.temuan.hariTutup} hari per temuan` : `${d.kpi.temuan.tutup} temuan ditutup` }}</p>
        </div>
        <div class="kartu p-4 col-span-2 lg:col-span-1">
          <p class="text-[12px] text-slate-500">Bukti terverifikasi</p>
          <p class="text-[26px] font-bold text-navy-900 tabular-nums leading-tight">{{ d.kpi.bukti.diterima ?? 0 }}<span class="text-[14px] text-slate-400"> / {{ d.kpi.bukti.total }}</span></p>
          <p class="text-[11.5px] text-slate-500 leading-snug">{{ d.kpi.bukti.menunggu ?? 0 }} menunggu verifikasi</p>
        </div>
      </section>

      <!-- Sorotan + sebaran -->
      <section class="grid lg:grid-cols-[1fr_340px] gap-5 mb-5">
        <div class="kartu">
          <div class="kartu-kepala"><div><h2 class="kartu-judul">Insight &amp; rekomendasi</h2><p class="kartu-sub">Disusun otomatis dari nilai, temuan, dan tindak lanjut periode ini.</p></div></div>
          <ul class="divide-y divide-slate-100">
            <li v-for="(s, i) in d.sorotan" :key="i" class="flex gap-3 px-5 sm:px-6 py-3.5 text-[13px] leading-relaxed text-slate-700">
              <Ikon :nama="IKON_SOROT[s.ikon]?.nama ?? 'info'" class="w-5 h-5 shrink-0 mt-0.5" :class="IKON_SOROT[s.ikon]?.kelas" />
              <p><template v-for="(r, j) in s.ruas" :key="j"><b v-if="r.b" class="text-navy-900">{{ r.b }}</b><template v-else>{{ r.t }}</template></template></p>
            </li>
            <li v-if="!d.sorotan.length" class="px-6 py-8 text-center text-[13px] text-slate-500">Belum ada nilai atau temuan untuk disorot.</li>
          </ul>
        </div>
        <div class="kartu p-5">
          <h2 class="kartu-judul mb-3">Sebaran tingkat penerapan</h2>
          <div class="flex h-3 rounded-full overflow-hidden bg-slate-100 mb-4">
            <i v-for="[k, kelas] in SEBAR" :key="k" :class="kelas" :style="{ width: `${(d.sebar[k] ?? 0) / Math.max(1, d.kpi.audit) * 100}%` }" />
          </div>
          <ul class="space-y-2 text-[13px]">
            <li v-for="[k, kelas] in SEBAR" :key="k" class="flex items-center gap-2">
              <i class="w-2.5 h-2.5 rounded-full" :class="kelas" /><span class="flex-1 text-slate-600">{{ k }}</span>
              <b class="tabular-nums text-navy-900">{{ d.sebar[k] ?? 0 }}</b>
            </li>
          </ul>
          <p class="mt-4 text-[11.5px] text-slate-500 leading-snug">Ambang Kepdirjen 185.K: Baik ≥ {{ d.target }}%, Perlu Perbaikan {{ d.batas }}–{{ d.target }}%, di bawah {{ d.batas }}% Perlu Perhatian Serius.</p>
        </div>
      </section>

      <!-- Benchmark per perusahaan -->
      <section class="kartu overflow-hidden mb-5">
        <div class="kartu-kepala flex-wrap gap-3">
          <div><h2 class="kartu-judul">Nilai akhir &amp; benchmark</h2><p class="kartu-sub">Klik baris untuk rincian tujuh elemen (bobot resmi 10/15/17/35/15/3/5). Poin maksimal {{ d.poinTotal }}.</p></div>
          <label class="flex items-center gap-2 text-[12.5px] text-slate-500 no-print">Urutkan
            <select v-model="urut" class="input input-kecil w-auto">
              <option value="skor">Nilai terendah dulu</option>
              <option value="temuan">Temuan terbuka terbanyak</option>
              <option value="nama">Nama perusahaan</option>
            </select>
          </label>
        </div>
        <div class="gulir-x">
          <table class="tabel tabel-hover min-w-[980px]">
            <thead>
              <tr><th>Perusahaan</th><th>Babak</th><th>Nilai akhir</th><th>Poin</th><th v-if="d.banding">{{ d.banding }}</th><th v-if="d.banding">Perubahan</th><th>Temuan</th><th>Langkah berikutnya</th></tr>
            </thead>
            <tbody>
              <template v-for="p in daftar" :key="p.id">
                <tr class="cursor-pointer" @click="buka = buka === p.id ? null : p.id">
                  <td>
                    <span class="block font-semibold text-navy-900">{{ p.nama }}</span>
                    <span class="block text-[11.5px] text-slate-500">{{ p.jenis }}{{ p.lead ? ` · Ketua tim ${p.lead}` : '' }}</span>
                  </td>
                  <td class="text-[12.5px] text-slate-600 whitespace-nowrap">{{ p.babak }}</td>
                  <td class="whitespace-nowrap">
                    <span class="font-bold tabular-nums" :style="{ color: warnaSkor(p.skor) }">{{ p.skor !== null ? satu(p.skor) + '%' : '—' }}</span>
                    <span class="block text-[11px] text-slate-400">{{ p.tingkat }}</span>
                  </td>
                  <td class="text-[12.5px] tabular-nums whitespace-nowrap">{{ satu(p.poin) }} / {{ p.maks }}</td>
                  <td v-if="d.banding" class="text-[12.5px] tabular-nums">{{ p.lalu !== null ? satu(p.lalu) + '%' : '—' }}</td>
                  <td v-if="d.banding" class="text-[12.5px] tabular-nums whitespace-nowrap"
                      :class="p.skor !== null && p.lalu !== null ? (p.skor >= p.lalu ? 'text-hijau-700' : 'text-rose-600') : 'text-slate-400'">
                    {{ p.skor !== null && p.lalu !== null ? teksSelisih(p.skor - p.lalu) : '—' }}
                  </td>
                  <td class="text-[12.5px] whitespace-nowrap"><b class="tabular-nums text-navy-900">{{ p.buka }}</b> <span class="text-slate-400">terbuka / {{ p.temuan }}</span>
                    <span v-if="p.telat" class="block text-[11px] font-semibold text-rose-600">{{ p.telat }} lewat target</span></td>
                  <td class="text-[12.5px]" :class="p.lanjut.nada === 'merah' ? 'text-rose-600 font-semibold' : 'text-slate-600'">{{ p.lanjut.t }}</td>
                </tr>
                <tr v-if="buka === p.id" class="bg-slate-50/70">
                  <td :colspan="d.banding ? 8 : 6" class="!py-4">
                    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                      <div v-for="(e, i) in d.elemen" :key="e.k" class="rounded-xl bg-white border border-slate-200 p-3">
                        <p class="text-[11.5px] text-slate-500 truncate">{{ e.k }}. {{ e.n }} · bobot {{ e.bobot }}%</p>
                        <p class="font-bold tabular-nums" :style="{ color: warnaSkor(p.el[i]) }">{{ p.el[i] !== null ? satu(p.el[i]) + '%' : '—' }}</p>
                        <p v-if="p.elPoin[i]" class="text-[11px] text-slate-400 tabular-nums">{{ satu(p.elPoin[i]![0]) }} / {{ p.elPoin[i]![1] }} poin</p>
                      </div>
                    </div>
                    <div class="flex flex-wrap gap-2 mt-3">
                      <Link :href="p.url.ringkasan" class="btn-garis btn-kecil"><Ikon nama="mata" class="w-4 h-4" /> Ringkasan</Link>
                      <Link :href="p.url.nilai" class="btn-garis btn-kecil"><Ikon nama="clipboard-check" class="w-4 h-4" /> Penilaian</Link>
                      <Link :href="p.url.temuan" class="btn-garis btn-kecil"><Ikon nama="peringatan" class="w-4 h-4" /> Temuan</Link>
                      <a :href="p.url.laporan" target="_blank" rel="noopener" class="btn-garis btn-kecil"><Ikon nama="cetak" class="w-4 h-4" /> Laporan</a>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Grafik elemen & temuan -->
      <section class="grid lg:grid-cols-3 gap-5 mb-5">
        <div class="kartu p-5">
          <h2 class="kartu-judul">Capaian 7 elemen</h2>
          <p class="kartu-sub mb-3">Rata-rata {{ d.tahun }}{{ d.banding ? ` vs ${d.banding}` : '' }} terhadap target {{ d.target }}%.</p>
          <div class="h-72"><canvas ref="kRadar" aria-label="Radar capaian elemen" role="img" /></div>
        </div>
        <div class="kartu p-5">
          <h2 class="kartu-judul">Temuan per elemen &amp; kategori</h2>
          <p class="kartu-sub mb-3">Seluruh temuan audit periode ini.</p>
          <div class="h-72"><canvas ref="kTemuan" aria-label="Temuan per elemen" role="img" /></div>
        </div>
        <div class="kartu p-5">
          <h2 class="kartu-judul">Tren nilai akhir</h2>
          <p class="kartu-sub mb-3">Rata-rata empat tahun terakhir.</p>
          <div class="h-72"><canvas ref="kTren" aria-label="Tren nilai akhir" role="img" /></div>
        </div>
      </section>

      <!-- Analisis temuan & tindak lanjut -->
      <section class="kartu mb-5">
        <div class="kartu-kepala flex-wrap gap-3">
          <div><h2 class="kartu-judul">Analisis temuan &amp; tindak lanjut</h2><p class="kartu-sub">Top 10 sub-elemen, status penyelesaian, dan tren temuan dibuat vs ditutup.</p></div>
          <div class="flex flex-wrap gap-2 no-print">
            <select v-model="lingkup" class="input input-kecil w-auto" aria-label="Lingkup analisis">
              <option v-for="o in d.analisis.pilihan" :key="o.k" :value="o.k">{{ o.n }}</option>
            </select>
            <select v-model="skala" class="input input-kecil w-auto" aria-label="Skala waktu">
              <option value="bulan">Per bulan</option><option value="kuartal">Per kuartal</option><option value="tahun">Per tahun</option>
            </select>
          </div>
        </div>
        <div class="grid lg:grid-cols-2 gap-5 p-5 sm:p-6">
          <div>
            <h3 class="text-[13px] font-semibold text-navy-900 mb-2">Top 10 sub-elemen bertemuan</h3>
            <table v-if="top.length" class="tabel">
              <thead><tr><th>Sub-elemen</th><th class="text-right">Temuan</th><th class="text-right">Terbuka</th></tr></thead>
              <tbody>
                <tr v-for="t in top" :key="t.k">
                  <td><b class="tabular-nums text-navy-900">{{ t.k }}</b> <span class="text-[12.5px] text-slate-600">{{ t.n }}</span>
                    <span class="block text-[11px] text-slate-400">{{ t.kritikal }} kritikal · {{ t.mayor }} mayor · {{ t.minor }} minor</span></td>
                  <td class="text-right tabular-nums font-semibold">{{ t.total }}</td>
                  <td class="text-right tabular-nums" :class="t.buka ? 'text-rose-600' : 'text-slate-400'">{{ t.buka }}</td>
                </tr>
              </tbody>
            </table>
            <p v-else class="text-[13px] text-slate-500 py-6 text-center">Belum ada temuan pada lingkup ini.</p>
          </div>
          <div>
            <h3 class="text-[13px] font-semibold text-navy-900 mb-2">Status tindak lanjut</h3>
            <div class="flex h-3 rounded-full overflow-hidden bg-slate-100 mb-2">
              <i class="bg-hijau-500" :style="{ width: `${statusTl.selesai / Math.max(1, totalTl) * 100}%` }" />
              <i class="bg-amber-400" :style="{ width: `${statusTl.proses / Math.max(1, totalTl) * 100}%` }" />
              <i class="bg-rose-500" :style="{ width: `${statusTl.telat / Math.max(1, totalTl) * 100}%` }" />
            </div>
            <p class="text-[12.5px] text-slate-600 mb-4">Selesai <b>{{ statusTl.selesai }}</b> · Proses <b>{{ statusTl.proses }}</b> · Terlambat <b class="text-rose-600">{{ statusTl.telat }}</b></p>
            <div class="h-64"><canvas ref="kTl" aria-label="Tren tindak lanjut" role="img" /></div>
          </div>
        </div>
      </section>

      <!-- Tindakan mendesak & agenda -->
      <section class="grid lg:grid-cols-2 gap-5">
        <div class="kartu">
          <div class="kartu-kepala"><div><h2 class="kartu-judul">Perlu tindakan</h2><p class="kartu-sub">{{ d.tindakan.jumlahTelat }} lewat target · {{ d.tindakan.jumlahTenggat }} bertenggat ≤ 7 hari.</p></div>
            <Link href="/smkp/tindak-lanjut" class="btn-garis btn-kecil no-print">Tindak Lanjut Temuan</Link></div>
          <ul class="divide-y divide-slate-100">
            <li v-for="t in [...d.tindakan.telat, ...d.tindakan.tenggat]" :key="t.nomor + t.pt" class="px-5 sm:px-6 py-3">
              <Link :href="t.url" class="flex items-start justify-between gap-3 group">
                <span class="min-w-0">
                  <span class="block text-[13px] font-semibold text-navy-900 group-hover:text-brand-600">{{ t.nomor }} · {{ t.kode }} {{ t.kriteria }}</span>
                  <span class="block text-[11.5px] text-slate-500">{{ t.pt }} · {{ t.jenis }} · batas {{ t.batas }}</span>
                </span>
                <span class="lencana shrink-0" :class="d.tindakan.telat.includes(t) ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700'">
                  {{ d.tindakan.telat.includes(t) ? `lewat ${t.hari} hari` : (t.hari ? `${t.hari} hari lagi` : 'hari ini') }}
                </span>
              </Link>
            </li>
            <li v-if="!d.tindakan.telat.length && !d.tindakan.tenggat.length" class="px-6 py-8 text-center text-[13px] text-slate-500">Tidak ada tindak lanjut yang mendesak.</li>
          </ul>
        </div>
        <div class="kartu">
          <div class="kartu-kepala"><div><h2 class="kartu-judul">Agenda 30 hari</h2><p class="kartu-sub">Audit lapangan, rapat penutupan, dan batas tindak lanjut.</p></div></div>
          <ul class="divide-y divide-slate-100">
            <li v-for="(a, i) in d.agenda" :key="i" class="px-5 sm:px-6 py-3">
              <Link :href="a.url" class="flex items-center gap-3 group">
                <span class="grid place-items-center w-11 h-11 rounded-xl shrink-0" :class="a.nada === 'perb' ? 'bg-amber-50 text-amber-700' : 'bg-brand-50 text-brand-700'">
                  <span class="text-[15px] font-bold leading-none tabular-nums">{{ a.hari }}</span><span class="text-[10px] uppercase">{{ a.bulan }}</span>
                </span>
                <span class="min-w-0">
                  <span class="block text-[13px] font-semibold text-navy-900 group-hover:text-brand-600">{{ a.judul }}</span>
                  <span class="block text-[11.5px] text-slate-500">{{ a.pt }} · {{ a.label }}</span>
                </span>
              </Link>
            </li>
            <li v-if="!d.agenda.length" class="px-6 py-8 text-center text-[13px] text-slate-500">Tidak ada agenda dalam 30 hari ke depan.</li>
          </ul>
        </div>
      </section>
    </template>
  </div>
</template>

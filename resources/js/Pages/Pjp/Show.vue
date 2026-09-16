<script setup lang="ts">
import { computed, reactive } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const STATUS_OPTIONS: Record<string, string> = {
  aktif: 'Aktif Dipantau',
  perlu_tindak_lanjut: 'Perlu Tindak Lanjut',
  tidak_aktif: 'Tidak Aktif',
};
const STATUS_BADGE: Record<string, string> = {
  aktif: 'bg-cam-lime-soft text-cam-lime-deep',
  perlu_tindak_lanjut: 'bg-amber-100 text-amber-700',
  tidak_aktif: 'bg-stone-100 text-stone-500',
};
const JENIS_LAPORAN_OPTIONS: Record<string, string> = {
  spip: 'Data SPIP (Sarana, Prasarana, Instalasi & Peralatan)',
  tsp: 'Target Sasaran Program (TSP)',
  laporan_bulanan: 'Laporan Bulanan',
  laporan_triwulan: 'Laporan Triwulan',
};
const KESESUAIAN_OPTIONS: Record<string, string> = { sesuai: 'Sesuai', tidak_sesuai: 'Tidak Sesuai' };
const SEMESTER_OPTIONS: Record<number, string> = { 1: 'Semester 1 (Jan-Jun)', 2: 'Semester 2 (Jul-Des)' };

interface Laporan {
  id: number; jenis: string; periode: string | null; file_path: string; file_name: string;
  file_size: number; kesesuaian_isi: string | null; tepat_waktu: boolean;
}
interface Catatan { id: number; isi: string; created_at: string }
interface Evaluasi {
  id: number; tahun: number; semester: number; skor_teknis: number;
  skor_keselamatan_kesehatan: number; skor_lingkungan: number; skor_rata_rata: number; catatan: string | null;
}

const props = defineProps<{
  pjp: { id: number; nama_perusahaan: string; nib: string | null; penanggung_jawab: string | null; status: string; catatan: string | null };
  laporans: Laporan[];
  evaluasis: Evaluasi[];
  catatans: Catatan[];
  smkpScore: { persentase: number };
  legalitasStatus: { total: number; lengkap: number };
  pelaporanScore: number | null;
  triwulanTerbuka: boolean;
  bulanTriwulanDibuka: string;
}>();

function scoreColor(v: number | null): string {
  if (v === null) return 'text-stone-300';
  if (v >= 80) return 'text-emerald-600';
  if (v >= 60) return 'text-sky-600';
  if (v >= 40) return 'text-amber-600';
  return 'text-red-600';
}

function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function formatTanggal(iso: string): string {
  return new Date(iso).toLocaleString('id-ID', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

const latestEvaluasi = computed(() => props.evaluasis[0] ?? null);

function hapusPjp() {
  if (!confirm(`Hapus data PJP "${props.pjp.nama_perusahaan}"? Semua dokumen, checklist, dan evaluasi terkait akan ikut terhapus.`)) return;
  router.delete(`/pjp/${props.pjp.id}`);
}

/* ---- Catatan ---- */
const catatanForm = useForm({ isi: '' });
function tambahCatatan() {
  catatanForm.post(`/pjp/${props.pjp.id}/catatan`, { preserveScroll: true, onSuccess: () => catatanForm.reset() });
}
function hapusCatatan(c: Catatan) {
  if (!confirm('Hapus catatan ini? Tindakan ini tidak bisa dibatalkan.')) return;
  router.delete(`/pjp/${props.pjp.id}/catatan/${c.id}`, { preserveScroll: true });
}

/* ---- Laporan per jenis ---- */
const laporanForms = reactive(
  Object.fromEntries(Object.keys(JENIS_LAPORAN_OPTIONS).map((jenis) => [
    jenis, useForm({ jenis, periode: '', file: null as File | null }),
  ])),
);

function laporanUntuk(jenis: string) {
  return props.laporans.filter((l) => l.jenis === jenis);
}

function pilihFile(jenis: string, e: Event) {
  laporanForms[jenis].file = (e.target as HTMLInputElement).files?.[0] ?? null;
}

function unggahLaporan(jenis: string) {
  laporanForms[jenis].post(`/pjp/${props.pjp.id}/laporan`, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => laporanForms[jenis].reset('periode', 'file'),
  });
}

function hapusLaporan(l: Laporan) {
  if (!confirm(`Hapus dokumen "${l.file_name}"? Tindakan ini tidak bisa dibatalkan.`)) return;
  router.delete(`/pjp/${props.pjp.id}/laporan/${l.id}`, { preserveScroll: true });
}

function ubahKesesuaian(l: Laporan, value: string) {
  router.patch(`/pjp/${props.pjp.id}/laporan/${l.id}`, { kesesuaian_isi: value }, { preserveScroll: true });
}

/* ---- Evaluasi ---- */
const evaluasiForm = useForm({
  tahun: new Date().getFullYear(),
  semester: 1,
  skor_teknis: '',
  skor_keselamatan_kesehatan: '',
  skor_lingkungan: '',
  catatan: '',
});
function simpanEvaluasi() {
  evaluasiForm.post(`/pjp/${props.pjp.id}/evaluasi`, {
    preserveScroll: true,
    onSuccess: () => evaluasiForm.reset('skor_teknis', 'skor_keselamatan_kesehatan', 'skor_lingkungan', 'catatan'),
  });
}
function hapusEvaluasi(e: Evaluasi) {
  if (!confirm(`Hapus evaluasi ${SEMESTER_OPTIONS[e.semester]} ${e.tahun}?`)) return;
  router.delete(`/pjp/${props.pjp.id}/evaluasi/${e.id}`, { preserveScroll: true });
}

/* Trend garis skor rata-rata, kronologis lama -> baru */
const tren = computed(() => {
  const kronologis = [...props.evaluasis].reverse();
  if (kronologis.length < 2) return null;

  const width = 280, height = 64, padX = 10, padY = 10;
  const step = (width - padX * 2) / (kronologis.length - 1);
  const points = kronologis.map((e, i) => ({
    x: padX + i * step,
    y: padY + (1 - e.skor_rata_rata / 100) * (height - padY * 2),
    e,
  }));
  const path = points.map((p) => `${p.x},${p.y}`).join(' ');
  const area = `${padX},${height} ${path} ${width - padX},${height}`;

  return { width, height, points, path, area, awal: kronologis[0], akhir: kronologis[kronologis.length - 1] };
});
</script>

<template>
  <Head :title="pjp.nama_perusahaan" />

  <div class="max-w-3xl mx-auto">
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
      <div>
        <h2 class="font-serif text-xl font-bold text-cam-ink">{{ pjp.nama_perusahaan }}</h2>
        <p class="text-[12.5px] text-stone-500 mt-1">PJP yang dipantau melalui checklist persyaratan, pelaporan, dan evaluasi kinerja.</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <Link :href="`/pjp/${pjp.id}/checklist-smkp`"
              class="rounded-xl border border-stone-200 px-3.5 py-2 text-[12px] font-semibold text-stone-600 hover:bg-stone-50">Persyaratan PJP</Link>
        <a :href="`/pjp/${pjp.id}/export-pdf`" target="_blank" rel="noopener noreferrer"
           class="rounded-xl border border-stone-200 px-3.5 py-2 text-[12px] font-semibold text-stone-600 hover:bg-stone-50">Export PDF</a>
        <Link :href="`/pjp/${pjp.id}/edit`"
              class="rounded-xl border border-stone-200 px-3.5 py-2 text-[12px] font-semibold text-stone-600 hover:bg-stone-50">Ubah Data</Link>
        <button type="button" class="rounded-xl border border-stone-200 px-3.5 py-2 text-[12px] font-semibold text-red-500 hover:bg-red-50" @click="hapusPjp">Hapus</button>
      </div>
    </div>

    <!-- Ringkasan -->
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 mb-6">
      <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-[13px]">
        <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold" :class="STATUS_BADGE[pjp.status]">
          {{ STATUS_OPTIONS[pjp.status] ?? pjp.status }}
        </span>
        <span v-if="pjp.nib" class="text-stone-600">NIB: {{ pjp.nib }}</span>
        <span v-if="pjp.penanggung_jawab" class="text-stone-600">Penanggung Jawab: {{ pjp.penanggung_jawab }}</span>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-5 pt-5 border-t border-stone-100">
        <div class="p-2">
          <p class="text-[11.5px] text-stone-500">Persyaratan PJP</p>
          <p class="text-xl font-extrabold mt-0.5" :class="scoreColor(smkpScore.persentase)">{{ smkpScore.persentase }}%</p>
        </div>
        <div class="p-2">
          <p class="text-[11.5px] text-stone-500">Dokumen Legalitas</p>
          <p class="text-xl font-extrabold mt-0.5" :class="legalitasStatus.lengkap === legalitasStatus.total ? 'text-emerald-600' : 'text-amber-600'">
            {{ legalitasStatus.lengkap }}/{{ legalitasStatus.total }}
          </p>
        </div>
        <div class="p-2">
          <p class="text-[11.5px] text-stone-500">Kepatuhan Pelaporan</p>
          <p class="text-xl font-extrabold mt-0.5" :class="scoreColor(pelaporanScore)">{{ pelaporanScore !== null ? `${pelaporanScore}%` : '—' }}</p>
        </div>
        <div class="p-2">
          <p class="text-[11.5px] text-stone-500">Evaluasi Terakhir</p>
          <p class="text-xl font-extrabold mt-0.5" :class="latestEvaluasi ? scoreColor(latestEvaluasi.skor_rata_rata) : 'text-stone-300'">
            {{ latestEvaluasi ? latestEvaluasi.skor_rata_rata : '—' }}
          </p>
        </div>
      </div>
    </div>

    <div v-if="pjp.catatan" class="rounded-2xl border border-amber-200 bg-amber-50 p-5 mb-6">
      <h3 class="text-[13px] font-bold text-amber-900">Catatan</h3>
      <p class="text-[12.5px] text-amber-800 mt-1 whitespace-pre-line">{{ pjp.catatan }}</p>
    </div>

    <!-- Riwayat catatan -->
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 mb-8">
      <h3 class="text-[13px] font-bold text-cam-ink">Riwayat Catatan</h3>
      <p class="text-[11.5px] text-stone-500 mt-1">Setiap catatan baru menambah entri — tidak menimpa yang lama.</p>

      <p v-if="!catatans.length" class="text-[12.5px] text-stone-500 mt-3">Belum ada catatan.</p>
      <ul v-else class="divide-y divide-stone-100 mt-3">
        <li v-for="c in catatans" :key="c.id" class="py-3 flex items-start justify-between gap-3 text-[12.5px]">
          <div class="min-w-0">
            <p class="text-stone-700 whitespace-pre-line">{{ c.isi }}</p>
            <p class="text-[11px] text-stone-400 mt-1">{{ formatTanggal(c.created_at) }}</p>
          </div>
          <button type="button" class="shrink-0 text-[11.5px] font-semibold text-red-500 hover:underline" @click="hapusCatatan(c)">Hapus</button>
        </li>
      </ul>

      <form class="mt-4 pt-4 border-t border-stone-100 space-y-2" @submit.prevent="tambahCatatan">
        <textarea v-model="catatanForm.isi" rows="2" placeholder="Tambah catatan baru…"
                  class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2 text-[12.5px]"></textarea>
        <p v-if="catatanForm.errors.isi" class="text-[12px] text-red-600">{{ catatanForm.errors.isi }}</p>
        <button type="submit" :disabled="catatanForm.processing || !catatanForm.isi.trim()"
                class="lime-gradient shadow-glow rounded-xl text-white px-3.5 py-1.5 text-[12px] font-bold disabled:opacity-50">
          Tambah Catatan
        </button>
      </form>
    </div>

    <!-- Dokumen & Laporan -->
    <h3 class="text-[14.5px] font-bold text-cam-ink mb-3 flex items-center gap-2">
      Dokumen &amp; Laporan
      <span class="rounded-full bg-stone-100 px-2 py-0.5 text-[11px] font-semibold text-stone-500">{{ laporans.length }} dokumen</span>
    </h3>
    <div class="space-y-3">
      <div v-for="(label, jenis) in JENIS_LAPORAN_OPTIONS" :key="jenis" class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
        <h4 class="font-bold text-[13px] text-cam-ink">{{ label }}</h4>

        <p v-if="!laporanUntuk(jenis).length" class="text-[12.5px] text-stone-500 mt-2">Belum ada dokumen diunggah.</p>
        <ul v-else class="divide-y divide-stone-100 mt-2">
          <li v-for="l in laporanUntuk(jenis)" :key="l.id" class="py-3 text-[12.5px] space-y-1.5">
            <div class="flex items-center justify-between gap-3">
              <a :href="`/storage/${l.file_path}`" target="_blank" class="truncate font-semibold text-cam-lime-deep hover:underline">{{ l.file_name }}</a>
              <button type="button" class="shrink-0 text-[11.5px] font-semibold text-red-500 hover:underline" @click="hapusLaporan(l)">Hapus</button>
            </div>
            <p class="text-[11px] text-stone-500">{{ l.periode ? `${l.periode} · ` : '' }}{{ formatFileSize(l.file_size) }}</p>
            <div class="flex flex-wrap items-center gap-2">
              <span class="rounded-full px-2 py-0.5 text-[10.5px] font-semibold" :class="l.tepat_waktu ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
                {{ l.tepat_waktu ? 'Tepat Waktu' : 'Terlambat' }}
              </span>
              <select :value="l.kesesuaian_isi ?? ''" class="rounded-lg border border-stone-200 px-2 py-1 text-[11px] text-stone-600"
                      @change="ubahKesesuaian(l, ($event.target as HTMLSelectElement).value)">
                <option value="">Belum dievaluasi</option>
                <option v-for="(kl, kv) in KESESUAIAN_OPTIONS" :key="kv" :value="kv">{{ kl }}</option>
              </select>
            </div>
          </li>
        </ul>

        <p v-if="jenis === 'laporan_triwulan' && !triwulanTerbuka" class="mt-4 rounded-xl bg-amber-50 px-3 py-2 text-[11.5px] text-amber-800">
          Laporan Triwulan hanya bisa diunggah pada bulan {{ bulanTriwulanDibuka }}.
        </p>
        <form v-else class="mt-4 pt-4 border-t border-stone-100 space-y-2" @submit.prevent="unggahLaporan(jenis)">
          <div class="grid sm:grid-cols-2 gap-2">
            <input v-model="laporanForms[jenis].periode" placeholder="Periode (mis. September 2026)"
                   class="ring-focus rounded-xl border border-stone-200 px-3 py-1.5 text-[12.5px]">
            <input type="file" class="text-[12px] text-stone-600" @change="pilihFile(jenis, $event)">
          </div>
          <p v-if="laporanForms[jenis].errors.file" class="text-[12px] text-red-600">{{ laporanForms[jenis].errors.file }}</p>
          <button type="submit" :disabled="laporanForms[jenis].processing || !laporanForms[jenis].file"
                  class="lime-gradient shadow-glow rounded-xl text-white px-3.5 py-1.5 text-[12px] font-bold disabled:opacity-50">
            Unggah
          </button>
        </form>
      </div>
    </div>

    <!-- Evaluasi Kinerja -->
    <h3 class="text-[14.5px] font-bold text-cam-ink mt-8 mb-3">Evaluasi Kinerja</h3>
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h4 class="font-bold text-[13px] text-cam-ink">Evaluasi Kinerja per Semester</h4>
      <p class="text-[11.5px] text-stone-500 mt-1">Skor 3 aspek (0-100): Teknis, Keselamatan &amp; Kesehatan, Lingkungan.</p>

      <p v-if="!evaluasis.length" class="text-[12.5px] text-stone-500 mt-3">Belum ada data evaluasi.</p>
      <template v-else>
        <div v-if="tren" class="my-4 rounded-xl bg-gradient-to-b from-orange-50 to-transparent p-3">
          <p class="text-[11px] font-bold text-stone-500 mb-2">Tren Skor Rata-rata Antar Semester</p>
          <svg :viewBox="`0 0 ${tren.width} ${tren.height}`" class="w-full max-w-[320px]">
            <defs>
              <linearGradient id="trenGradientPjp" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#F57C00" stop-opacity="0.35"/>
                <stop offset="100%" stop-color="#F57C00" stop-opacity="0"/>
              </linearGradient>
            </defs>
            <polygon :points="tren.area" fill="url(#trenGradientPjp)"/>
            <polyline :points="tren.path" fill="none" stroke="#F57C00" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <circle v-for="(p, i) in tren.points" :key="i" :cx="p.x" :cy="p.y" :r="i === tren.points.length - 1 ? 4 : 3" fill="#F57C00" stroke="#fff" stroke-width="2"/>
          </svg>
          <div class="flex justify-between text-[10px] text-stone-400 mt-1">
            <span>{{ SEMESTER_OPTIONS[tren.awal.semester] }} {{ tren.awal.tahun }}</span>
            <span>{{ SEMESTER_OPTIONS[tren.akhir.semester] }} {{ tren.akhir.tahun }}</span>
          </div>
        </div>

        <ul class="divide-y divide-stone-100">
          <li v-for="e in evaluasis" :key="e.id" class="py-3 text-[12.5px] space-y-1.5">
            <div class="flex items-center justify-between gap-3">
              <span class="font-semibold text-cam-ink">{{ SEMESTER_OPTIONS[e.semester] }} {{ e.tahun }}</span>
              <button type="button" class="text-[11.5px] font-semibold text-red-500 hover:underline" @click="hapusEvaluasi(e)">Hapus</button>
            </div>
            <div class="flex flex-wrap gap-x-4 gap-y-1 text-[11.5px] text-stone-600">
              <span>Teknis: {{ e.skor_teknis }}</span>
              <span>K3: {{ e.skor_keselamatan_kesehatan }}</span>
              <span>Lingkungan: {{ e.skor_lingkungan }}</span>
              <span class="font-bold" :class="scoreColor(e.skor_rata_rata)">Rata-rata: {{ e.skor_rata_rata }}</span>
            </div>
            <p v-if="e.catatan" class="text-[11.5px] italic text-stone-500">{{ e.catatan }}</p>
          </li>
        </ul>
      </template>

      <form class="mt-4 pt-4 border-t border-stone-100 space-y-2" @submit.prevent="simpanEvaluasi">
        <div class="grid sm:grid-cols-2 gap-2">
          <select v-model.number="evaluasiForm.semester" class="rounded-xl border border-stone-200 px-3 py-1.5 text-[12.5px]">
            <option v-for="(l, v) in SEMESTER_OPTIONS" :key="v" :value="Number(v)">{{ l }}</option>
          </select>
          <input v-model.number="evaluasiForm.tahun" type="number" class="rounded-xl border border-stone-200 px-3 py-1.5 text-[12.5px]">
        </div>
        <div class="grid sm:grid-cols-3 gap-2">
          <input v-model="evaluasiForm.skor_teknis" type="number" min="0" max="100" placeholder="Skor Teknis" class="rounded-xl border border-stone-200 px-3 py-1.5 text-[12.5px]">
          <input v-model="evaluasiForm.skor_keselamatan_kesehatan" type="number" min="0" max="100" placeholder="Skor K3" class="rounded-xl border border-stone-200 px-3 py-1.5 text-[12.5px]">
          <input v-model="evaluasiForm.skor_lingkungan" type="number" min="0" max="100" placeholder="Skor Lingkungan" class="rounded-xl border border-stone-200 px-3 py-1.5 text-[12.5px]">
        </div>
        <p v-if="evaluasiForm.errors.skor_teknis || evaluasiForm.errors.skor_keselamatan_kesehatan || evaluasiForm.errors.skor_lingkungan"
           class="text-[12px] text-red-600">Skor harus diisi, angka 0-100.</p>
        <textarea v-model="evaluasiForm.catatan" rows="2" placeholder="Catatan (opsional)" class="w-full rounded-xl border border-stone-200 px-3 py-1.5 text-[12.5px]"></textarea>
        <button type="submit" :disabled="evaluasiForm.processing"
                class="lime-gradient shadow-glow rounded-xl text-white px-3.5 py-1.5 text-[12px] font-bold disabled:opacity-50">
          Simpan Evaluasi
        </button>
      </form>
    </div>
  </div>
</template>

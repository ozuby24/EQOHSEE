<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import Ikon from '../Ikon.vue';
import BingkaiDialog from './BingkaiDialog.vue';
import { gabungNilai, GalatRencana, kirimBerkas, pesanGalat } from './api';
import type { BarisMetode, HasilImpor, MetodeSampel } from './api';

/*
 * Impor metode & sampel per kriteria dari berkas: CSV/XLSX dibaca langsung
 * di server, PDF/DOCX dibaca Asisten AI. Hasilnya dipratinjau per kode
 * (nilai sekarang → nilai baru) dan hanya baris yang dicentang diterapkan.
 */
const props = defineProps<{
  auditId: number;
  daftar: Array<{ kode: string; nama: string; sub: Array<{ kode: string; nama: string }> }>;
  /** isian formulir saat ini (boleh belum tersimpan) */
  nilai: Record<string, MetodeSampel>;
  aiAktif: boolean;
}>();
const buka = defineModel<boolean>('buka', { required: true });
const emit = defineEmits<{ terapkan: [hasil: { baris: BarisMetode[]; timpa: boolean; label: string }] }>();

const TERIMA = '.csv,.txt,.xlsx,.pdf,.docx';
const TABEL = ['csv', 'txt', 'xlsx'];
const DOKUMEN = ['pdf', 'docx'];
const MAKS = 10 * 1024 * 1024;

const tahap = ref<'pilih' | 'memuat' | 'pratinjau'>('pilih');
const berkas = ref<File | null>(null);
const asal = ref<'tabel' | 'ai'>('tabel');
const hasil = ref<HasilImpor | null>(null);
const galat = ref<string | null>(null);
const bisaCobaAi = ref(false);
const dipilih = ref<string[]>([]);
const timpa = ref(false);
const seret = ref(false);
const detik = ref(0);
let kendali: AbortController | null = null;
let jam: ReturnType<typeof setInterval> | null = null;

const namaSub = computed(() => Object.fromEntries(props.daftar.flatMap((e) => e.sub.map((s) => [s.kode, s.nama]))));
const ekstensi = (f: File) => (f.name.split('.').pop() ?? '').toLowerCase();

function hentikanJam() { if (jam) { clearInterval(jam); jam = null; } }

function setelUlang() {
  kendali?.abort();
  hentikanJam();
  tahap.value = 'pilih';
  berkas.value = null;
  hasil.value = null;
  galat.value = null;
  bisaCobaAi.value = false;
  dipilih.value = [];
  timpa.value = false;
}

watch(buka, (b) => { if (b) setelUlang(); });
onBeforeUnmount(() => { kendali?.abort(); hentikanJam(); });

async function proses(f: File, pakaiAi = false) {
  const ext = ekstensi(f);
  galat.value = null;
  bisaCobaAi.value = false;
  if (![...TABEL, ...DOKUMEN].includes(ext)) { galat.value = 'Pilih berkas CSV, XLSX, PDF, atau DOCX.'; return; }
  if (f.size > MAKS) { galat.value = 'Berkas lebih dari 10 MB.'; return; }

  const ai = pakaiAi || DOKUMEN.includes(ext);
  if (ai && !props.aiAktif) {
    galat.value = 'Dokumen PDF/DOCX dibaca dengan Asisten AI, yang belum diaktifkan administrator. Simpan tabel metode & sampelnya sebagai CSV/XLSX, atau isi templat CSV.';
    return;
  }

  berkas.value = f;
  asal.value = ai ? 'ai' : 'tabel';
  tahap.value = 'memuat';
  detik.value = 0;
  hentikanJam();
  jam = setInterval(() => { detik.value++; }, 1000);

  const c = new AbortController();
  kendali = c;
  try {
    const r = await kirimBerkas<HasilImpor>(ai ? `/asisten/smkp/${props.auditId}/rencana` : `/smkp/${props.auditId}/rencana/impor`, f, c.signal);
    hasil.value = r;
    dipilih.value = r.baris.map((b) => b.kode);
    tahap.value = 'pratinjau';
  } catch (e) {
    if ((e as Error).name === 'AbortError') return;
    galat.value = pesanGalat(e);
    // Tabel tanpa kode yang dikenali masih bisa dibaca asisten bila tata letaknya tidak baku.
    bisaCobaAi.value = !ai && props.aiAktif && e instanceof GalatRencana && e.jenis === 'kosong';
    tahap.value = 'pilih';
  } finally {
    if (kendali === c) { kendali = null; hentikanJam(); }
  }
}

function pilihBerkas(ev: Event) {
  const input = ev.target as HTMLInputElement;
  const f = input.files?.[0];
  input.value = '';
  if (f) proses(f);
}

function jatuh(ev: DragEvent) {
  seret.value = false;
  const f = ev.dataTransfer?.files?.[0];
  if (f) proses(f);
}

function batalMemuat() {
  kendali?.abort();
  hentikanJam();
  tahap.value = 'pilih';
}

/* ---------- pratinjau ---------- */
const baris = computed(() => (hasil.value?.baris ?? []).map((b) => {
  const lama = props.nilai[b.kode] ?? { metode: '', sampel: '' };
  const jadi = { metode: gabungNilai(lama.metode, b.metode, timpa.value), sampel: gabungNilai(lama.sampel, b.sampel, timpa.value) };
  const berubah = jadi.metode !== lama.metode || jadi.sampel !== lama.sampel;
  const status = !berubah ? 'tetap' : !lama.metode.trim() && !lama.sampel.trim() ? 'baru' : 'ganti';
  return { ...b, nama: namaSub.value[b.kode] ?? '', lama, jadi, berubah, status };
}));

const akanDiterapkan = computed(() => baris.value.filter((b) => b.berubah && dipilih.value.includes(b.kode)));
const semuaDipilih = computed(() => baris.value.filter((b) => b.berubah).every((b) => dipilih.value.includes(b.kode)));
function alihSemua() {
  dipilih.value = semuaDipilih.value ? [] : baris.value.map((b) => b.kode);
}

const STATUS: Record<string, { label: string; kelas: string }> = {
  baru: { label: 'Baru', kelas: 'bg-hijau-50 text-hijau-700' },
  ganti: { label: 'Diperbarui', kelas: 'bg-amber-50 text-amber-700' },
  tetap: { label: 'Tidak berubah', kelas: 'bg-slate-100 text-slate-500' },
};

function terapkan() {
  if (!hasil.value || !akanDiterapkan.value.length) return;
  const label = hasil.value.sumber === 'ai' ? `${hasil.value.berkas} (Asisten AI)` : hasil.value.berkas;
  emit('terapkan', { baris: akanDiterapkan.value.map(({ kode, metode, sampel }) => ({ kode, metode, sampel })), timpa: timpa.value, label });
  buka.value = false;
}
</script>

<template>
  <BingkaiDialog v-model:buka="buka" judul="Impor metode & sampel" ikon="unggah" :sibuk="tahap === 'memuat'"
                 :lebar="tahap === 'pratinjau' ? 'max-w-5xl' : 'max-w-2xl'"
                 sub="Pakai metode & sampel dari Rencana Audit yang pernah dibuat, lalu sesuaikan untuk audit ini.">
    <!-- Pilih berkas -->
    <div v-if="tahap === 'pilih'">
      <label class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-6 py-8 text-center cursor-pointer transition-colors"
             :class="seret ? 'border-brand-500 bg-brand-50/60' : 'border-slate-300 hover:border-brand-400 hover:bg-slate-50'"
             @dragover.prevent="seret = true" @dragleave.prevent="seret = false" @drop.prevent="jatuh">
        <span class="grid place-items-center w-11 h-11 rounded-xl bg-brand-50 text-brand-600"><Ikon nama="unggah" class="w-5 h-5" /></span>
        <span class="text-[13.5px] font-semibold text-navy-900">Tarik berkas ke sini atau <span class="text-brand-700 underline underline-offset-2">pilih berkas</span></span>
        <span class="text-[12px] text-slate-500">CSV, XLSX, PDF, atau DOCX · maks. 10 MB</span>
        <input type="file" class="sr-only" :accept="TERIMA" @change="pilihBerkas">
      </label>

      <div v-if="galat" class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-3 text-[12.5px] text-rose-700" role="alert">
        <p class="flex gap-2"><Ikon nama="peringatan" class="w-4 h-4 shrink-0 mt-px" /> {{ galat }}</p>
        <button v-if="bisaCobaAi && berkas" type="button" class="btn-garis btn-kecil mt-2.5" @click="proses(berkas, true)">
          <Ikon nama="kilau" class="w-3.5 h-3.5" /> Baca dengan Asisten AI
        </button>
      </div>

      <div class="mt-4 grid sm:grid-cols-2 gap-3">
        <div class="rounded-xl border border-slate-200 p-3.5">
          <p class="flex items-center gap-2 text-[12.5px] font-semibold text-navy-900"><Ikon nama="daftar" class="w-4 h-4 text-teal-700" /> Tabel CSV / XLSX</p>
          <p class="mt-1 text-[12px] leading-relaxed text-slate-500">Dibaca langsung. Kolom Kode, Metode Audit, dan Sampel dalam urutan bebas; kode boleh ditulis bersama namanya, mis. "I.1 Penyusunan Kebijakan".</p>
          <a :href="`/smkp/${auditId}/rencana/templat`" class="tautan mt-2 inline-flex items-center gap-1 text-[12px]"><Ikon nama="unduh" class="w-3.5 h-3.5" /> Unduh templat CSV</a>
        </div>
        <div class="rounded-xl border border-slate-200 p-3.5">
          <p class="flex items-center gap-2 text-[12.5px] font-semibold text-navy-900"><Ikon nama="kilau" class="w-4 h-4 text-brand-600" /> Dokumen PDF / DOCX</p>
          <p class="mt-1 text-[12px] leading-relaxed text-slate-500">Rencana Audit lama dibaca Asisten AI — biasanya 15–60 detik. Hasilnya dipratinjau dulu sebelum diterapkan.</p>
          <span v-if="!aiAktif" class="lencana mt-2 bg-amber-50 text-amber-700"><Ikon nama="kunci" class="w-3 h-3" /> Asisten AI belum diaktifkan</span>
        </div>
      </div>
    </div>

    <!-- Memuat -->
    <div v-else-if="tahap === 'memuat'" class="py-10 flex flex-col items-center text-center" aria-busy="true" aria-live="polite">
      <span class="grid place-items-center w-12 h-12 rounded-2xl bg-brand-50 text-brand-600"><Ikon nama="muat" class="w-6 h-6 animate-spin" /></span>
      <p class="mt-3 text-[14px] font-semibold text-navy-900">{{ asal === 'ai' ? 'Asisten AI sedang membaca dokumen…' : 'Membaca tabel…' }}</p>
      <p class="mt-1 text-[12.5px] text-slate-500 max-w-sm">
        {{ berkas?.name }}<template v-if="asal === 'ai'"> — biasanya 15–60 detik. Biarkan dialog ini terbuka.</template>
      </p>
      <p v-if="asal === 'ai'" class="mt-3 text-[12px] font-semibold text-slate-400 tabular-nums">{{ detik }} detik</p>
    </div>

    <!-- Pratinjau -->
    <div v-else-if="hasil" class="space-y-4">
      <div class="flex flex-wrap items-center gap-2">
        <span class="inline-flex items-center gap-2 min-w-0 text-[13px] font-semibold text-navy-900">
          <Ikon :nama="hasil.sumber === 'ai' ? 'kilau' : 'file-text'" class="w-4 h-4 shrink-0 text-brand-600" />
          <span class="truncate max-w-[280px]">{{ hasil.berkas }}</span>
        </span>
        <span class="lencana bg-navy-50 text-navy-800 tabular-nums">{{ hasil.baris.length }} kriteria terbaca</span>
        <span v-if="hasil.dilewati" class="lencana bg-amber-50 text-amber-700 tabular-nums" title="Baris berisi metode/sampel tetapi kodenya tidak dikenal atau ganda">{{ hasil.dilewati }} baris dilewati</span>
        <span v-if="hasil.sumber === 'ai'" class="lencana bg-brand-50 text-brand-700">Asisten AI<template v-if="hasil.model"> · {{ hasil.model }}</template></span>
      </div>

      <div v-if="hasil.sumber === 'ai'" class="rounded-xl border border-slate-200 bg-slate-50/80 px-3.5 py-3 text-[12.5px] text-slate-600">
        <p class="flex gap-2"><Ikon nama="info" class="w-4 h-4 shrink-0 mt-px text-slate-400" />
          <span><b class="text-navy-900">Saran AI — periksa sebelum menyimpan.</b><template v-if="hasil.catatan"> {{ hasil.catatan }}</template></span></p>
      </div>

      <div v-if="!hasil.baris.length" class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-[12.5px] text-slate-500">
        Tidak ada metode atau sampel per sub-elemen yang dapat diambil dari berkas ini.
      </div>
      <template v-else>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-0.5" role="radiogroup" aria-label="Cara menerapkan">
            <button v-for="m in [{ v: false, l: 'Isi yang masih kosong saja' }, { v: true, l: 'Timpa semua' }]" :key="String(m.v)" type="button" role="radio"
                    :aria-checked="timpa === m.v" class="h-8 px-3 rounded-md text-[12.5px] font-semibold transition-colors"
                    :class="timpa === m.v ? 'bg-white text-navy-900 shadow-kartu' : 'text-slate-500 hover:text-navy-900'" @click="timpa = m.v">{{ m.l }}</button>
          </div>
          <p class="text-[12px] text-slate-500"><b class="text-navy-900 tabular-nums">{{ akanDiterapkan.length }}</b> dari {{ baris.length }} kriteria akan diperbarui</p>
        </div>

        <!-- Kisi, bukan tabel: di layar sempit tiap kriteria menjadi kartu bertumpuk tanpa gulir mendatar. -->
        <div class="rounded-xl border border-slate-200 overflow-hidden">
          <div class="max-h-[52vh] overflow-y-auto scrollbar-tipis text-[12.5px]">
            <div class="sticky top-0 z-[1] grid grid-cols-[1.75rem_minmax(0,1fr)] md:grid-cols-[1.75rem_minmax(0,1.1fr)_minmax(0,1fr)_minmax(0,1fr)_6.5rem] gap-x-3 items-center px-3.5 py-2 bg-slate-50 shadow-[0_1px_0_theme(colors.slate.200)] text-[10.5px] font-semibold uppercase tracking-[0.08em] text-slate-400">
              <input type="checkbox" :checked="semuaDipilih" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" aria-label="Pilih semua" @change="alihSemua">
              <span class="md:hidden">Pilih semua kriteria yang berubah</span>
              <span class="hidden md:block">Kriteria</span><span class="hidden md:block">Metode audit</span><span class="hidden md:block">Sampel</span><span class="hidden md:block">Status</span>
            </div>
            <div v-for="b in baris" :key="b.kode" role="group" :aria-label="`${b.kode} ${b.nama}`"
                 class="grid grid-cols-[1.75rem_minmax(0,1fr)_auto] md:grid-cols-[1.75rem_minmax(0,1.1fr)_minmax(0,1fr)_minmax(0,1fr)_6.5rem] gap-x-3 gap-y-2 px-3.5 py-2.5 border-t border-slate-100"
                 :class="b.berubah ? '' : 'bg-slate-50/60'">
              <div class="pt-0.5">
                <input v-if="b.berubah" v-model="dipilih" type="checkbox" :value="b.kode" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" :aria-label="`Terapkan ${b.kode}`">
                <span v-else class="block w-4 text-center text-slate-300" aria-hidden="true">–</span>
              </div>
              <div class="min-w-0">
                <span class="lencana bg-slate-100 text-navy-900 tabular-nums">{{ b.kode }}</span>
                <span class="block mt-1 text-[12px] leading-snug text-slate-600">{{ b.nama }}</span>
              </div>
              <div class="md:order-last"><span class="lencana" :class="STATUS[b.status].kelas">{{ STATUS[b.status].label }}</span></div>
              <div v-for="k in (['metode', 'sampel'] as const)" :key="k" class="col-start-2 col-span-2 md:col-start-auto md:col-span-1 min-w-0 leading-snug">
                <span class="md:hidden block text-[10.5px] font-semibold uppercase tracking-[0.08em] text-slate-400 mb-0.5">{{ k === 'metode' ? 'Metode audit' : 'Sampel' }}</span>
                <template v-if="b.jadi[k] !== b.lama[k]">
                  <s v-if="b.lama[k]" class="block text-slate-400 decoration-slate-300">{{ b.lama[k] }}</s>
                  <span class="block font-medium text-navy-900">{{ b.jadi[k] }}</span>
                </template>
                <template v-else>
                  <span class="block text-slate-600">{{ b.lama[k] || '—' }}</span>
                  <span v-if="b[k] && b[k] !== b.lama[k]" class="block mt-0.5 text-[11px] text-slate-400 line-clamp-2" :title="b[k]">Di berkas: {{ b[k] }} · dipertahankan</span>
                </template>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>

    <template #kaki>
      <template v-if="tahap === 'memuat'">
        <button type="button" class="btn-garis" @click="batalMemuat">Batalkan</button>
      </template>
      <template v-else-if="tahap === 'pratinjau'">
        <button type="button" class="btn-hantu sm:mr-auto" @click="setelUlang"><Ikon nama="panah-kiri" class="w-4 h-4" /> Pilih berkas lain</button>
        <button type="button" class="btn-garis" @click="buka = false">Batal</button>
        <button type="button" class="btn-utama w-full sm:w-auto" :disabled="!akanDiterapkan.length" @click="terapkan">
          <Ikon nama="centang" class="w-4 h-4" /> Terapkan {{ akanDiterapkan.length }} kriteria
        </button>
      </template>
      <button v-else type="button" class="btn-garis" @click="buka = false">Tutup</button>
    </template>
  </BingkaiDialog>
</template>

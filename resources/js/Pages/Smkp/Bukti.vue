<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import KepalaAudit from '../../Components/Smkp/KepalaAudit.vue';
import Ikon from '../../Components/Smkp/Ikon.vue';
import { ukuranBerkas } from '../../smkp/lib/format';
import type { BerkasBukti, DokumenDiminta, RingkasAudit } from '../../smkp/types';

/*
 * Ringkasan bukti kelengkapan audit: dokumen & sampel yang diminta Rencana
 * Audit per sub-elemen, kemajuan unggahan auditi, dan antrean verifikasi
 * bagi auditor. Unggah dan verifikasi sendiri dilakukan per butir di
 * halaman Penilaian (tautan "Unggah bukti" / "Periksa").
 */
interface ButirRingkas { kode: string; nama: string; menunggu: number; diterima: number; ditolak: number }
interface SubRingkas { kode: string; nama: string; diminta: DokumenDiminta | null; butir: ButirRingkas[]; url: string }
interface ElemenRingkas { kode: string; nama: string; sub: SubRingkas[] }

const props = defineProps<{
  audit: RingkasAudit;
  elemen: ElemenRingkas[];
  /** Berkas menunggu verifikasi (hanya untuk auditor/administrator), terlama di atas. */
  antrean: BerkasBukti[];
  rencanaUrl: string;
  bisaSunting: boolean;
  bisaUnggah: boolean;
}>();

const BATAS_ANTREAN = 30;

const ALUR = [
  { judul: 'Rencana Audit', teks: 'Auditor menetapkan metode, dokumen & sampel per sub-elemen.' },
  { judul: 'Unggah bukti', teks: 'Auditi mengunggah berkas pada butir kriteria yang bersangkutan.' },
  { judul: 'Verifikasi & nilai', teks: 'Auditor menerima atau meminta perbaikan, lalu menilai butir.' },
];

// Alamat unduh dibentuk dari id berkas: tidak bergantung pada arti kolom `url` antrean.
const unduhan = (b: BerkasBukti) => `/smkp/${props.audit.id}/bukti/${b.id}/berkas`;

/* ---------- Hitungan ---------- */
function statSub(s: SubRingkas) {
  const h = { menunggu: 0, diterima: 0, ditolak: 0, berkas: 0, berbukti: 0, total: s.butir.length };
  for (const b of s.butir) {
    h.menunggu += b.menunggu; h.diterima += b.diterima; h.ditolak += b.ditolak;
    const n = b.menunggu + b.diterima + b.ditolak;
    h.berkas += n;
    if (n) h.berbukti++;
  }
  return h;
}
const stat = computed(() => Object.fromEntries(props.elemen.flatMap((e) => e.sub).map((s) => [s.kode, statSub(s)])));

function jumlahkan(daftar: SubRingkas[]) {
  const t = { sub: daftar.length, diminta: 0, butir: 0, berbukti: 0, berkas: 0, menunggu: 0, diterima: 0, ditolak: 0 };
  for (const s of daftar) {
    const h = stat.value[s.kode];
    if (s.diminta) t.diminta++;
    t.butir += h.total; t.berbukti += h.berbukti; t.berkas += h.berkas;
    t.menunggu += h.menunggu; t.diterima += h.diterima; t.ditolak += h.ditolak;
  }
  return t;
}
const semuaSub = computed(() => props.elemen.flatMap((e) => e.sub));
const total = computed(() => jumlahkan(semuaSub.value));
const totalElemen = computed(() => Object.fromEntries(props.elemen.map((e) => [e.kode, jumlahkan(e.sub)])));
const statistik = computed(() => [
  { label: 'Diminta Rencana', nilai: total.value.diminta, ket: `dari ${total.value.sub} sub-elemen`, ikon: 'clipboard-list', warna: 'text-teal-600' },
  { label: 'Menunggu verifikasi', nilai: total.value.menunggu, ket: 'berkas', ikon: 'jam', warna: 'text-amber-500' },
  { label: 'Perlu perbaikan', nilai: total.value.ditolak, ket: 'berkas', ikon: 'peringatan', warna: 'text-rose-500' },
  { label: 'Diterima', nilai: total.value.diterima, ket: 'berkas', ikon: 'centang-bulat', warna: 'text-emerald-600' },
]);

/* ---------- Saring & cari ---------- */
type Saring = 'semua' | 'diminta' | 'belum' | 'menunggu' | 'ditolak';
// Label ringkas agar kelima saringan muat satu baris di samping antrean; `judul` untuk tooltip.
const SARING: Array<{ kunci: Saring; label: string; judul?: string; cocok: (s: SubRingkas) => boolean }> = [
  { kunci: 'semua', label: 'Semua', cocok: () => true },
  { kunci: 'diminta', label: 'Diminta Rencana', cocok: (s) => !!s.diminta },
  { kunci: 'belum', label: 'Belum ada bukti', cocok: (s) => stat.value[s.kode].berkas === 0 },
  { kunci: 'menunggu', label: 'Menunggu', judul: 'Ada berkas yang menunggu verifikasi auditor', cocok: (s) => stat.value[s.kode].menunggu > 0 },
  { kunci: 'ditolak', label: 'Perlu perbaikan', judul: 'Ada berkas yang dikembalikan auditor untuk diperbaiki', cocok: (s) => stat.value[s.kode].ditolak > 0 },
];
// Bila Rencana Audit sudah menetapkan dokumen, mulai dari yang diminta.
const saring = ref<Saring>(total.value.diminta ? 'diminta' : 'semua');
const cari = ref('');
const hitungSaring = computed(() => Object.fromEntries(SARING.map((x) => [x.kunci, semuaSub.value.filter(x.cocok).length])) as Record<Saring, number>);

function cocokCari(s: SubRingkas, q: string) {
  if (!q) return true;
  const teks = [s.kode, s.nama, s.diminta?.metode ?? '', s.diminta?.sampel ?? '', ...s.butir.flatMap((b) => [b.kode, b.nama])].join(' ').toLowerCase();
  return q.split(/\s+/).every((k) => teks.includes(k));
}

const tampil = computed(() => {
  const q = cari.value.trim().toLowerCase();
  const cocok = SARING.find((x) => x.kunci === saring.value)!.cocok;
  return props.elemen
    .map((e) => ({ ...e, sub: e.sub.filter((s) => cocok(s) && cocokCari(s, q)) }))
    .filter((e) => e.sub.length);
});

const tutup = reactive<Record<string, boolean>>({});

/* ---------- Tautan ke butir di halaman Penilaian ---------- */
const lokasiButir = computed(() => {
  const m: Record<string, { nama: string; url: string }> = {};
  for (const s of semuaSub.value) for (const b of s.butir) m[b.kode] = { nama: b.nama, url: `${s.url}#butir-${b.kode}` };
  return m;
});
function tautanButir(kode: string) {
  if (lokasiButir.value[kode]) return lokasiButir.value[kode].url;
  const bagian = kode.split('.');
  return `/smkp/${props.audit.id}/elemen/${bagian[0]}?sub=${encodeURIComponent(bagian.slice(0, 2).join('.'))}#butir-${kode}`;
}

/* ---------- Tampilan status ---------- */
function kelasButir(b: ButirRingkas) {
  if (b.ditolak) return 'bg-rose-50 text-rose-700 ring-rose-200 hover:bg-rose-100';
  if (b.menunggu) return 'bg-amber-50 text-amber-700 ring-amber-200 hover:bg-amber-100';
  if (b.diterima) return 'bg-emerald-50 text-emerald-700 ring-emerald-200 hover:bg-emerald-100';
  return 'bg-white text-slate-400 ring-slate-200 hover:text-navy-900 hover:ring-brand-300';
}
function judulButir(b: ButirRingkas) {
  const n = b.menunggu + b.diterima + b.ditolak;
  if (!n) return `${b.kode} · ${b.nama} — belum ada bukti`;
  const bagian = [b.menunggu && `${b.menunggu} menunggu`, b.ditolak && `${b.ditolak} perlu perbaikan`, b.diterima && `${b.diterima} diterima`].filter(Boolean);
  return `${b.kode} · ${b.nama} — ${n} berkas (${bagian.join(', ')})`;
}

const labelAksi = computed(() => (props.bisaSunting ? 'Periksa & nilai' : props.bisaUnggah ? 'Unggah bukti' : 'Lihat bukti'));

const pesanKosong = computed(() => {
  if (cari.value.trim()) return { judul: 'Tidak ada yang cocok', pesan: `Tidak ada sub-elemen atau butir yang cocok dengan "${cari.value.trim()}" pada saringan ini.` };
  switch (saring.value) {
    case 'menunggu': return { judul: 'Tidak ada yang menunggu verifikasi', pesan: 'Seluruh berkas yang diunggah sudah diperiksa auditor.' };
    case 'ditolak': return { judul: 'Tidak ada yang perlu perbaikan', pesan: 'Belum ada berkas yang dikembalikan auditor untuk diperbaiki.' };
    case 'belum': return { judul: 'Semua sub-elemen sudah berbukti', pesan: 'Setiap sub-elemen sudah memiliki setidaknya satu berkas bukti.' };
    default: return null;
  }
});

const jenis = (nama: string) => (nama.includes('.') ? nama.split('.').pop()!.slice(0, 4).toUpperCase() : 'FILE');
</script>

<template>
  <div class="smkp-ui">
    <Head title="Bukti Audit" />
    <KepalaAudit :audit="audit" aktif="bukti" :bisa-sunting="bisaSunting" />

    <!-- Pengantar & alur -->
    <section class="kartu mb-5 p-4 sm:p-5">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 max-w-2xl">
          <p class="eyebrow">Bukti kelengkapan audit</p>
          <h2 class="mt-1 text-[17px] font-bold text-navy-900 leading-snug">Dokumen diminta &amp; status verifikasi</h2>
          <p class="mt-1 text-[13px] text-slate-500 leading-relaxed">
            {{ bisaSunting
              ? 'Pantau dokumen yang diminta Rencana Audit, berkas yang diunggah auditi, dan berkas yang menunggu verifikasi Anda.'
              : 'Unggah berkas bukti sesuai dokumen dan sampel yang diminta Rencana Audit. Auditor memverifikasi setiap berkas sebelum menilai.' }}
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link v-if="bisaSunting" :href="rencanaUrl" class="btn-garis btn-kecil"><Ikon nama="clipboard-list" class="w-4 h-4" /> Rencana Audit</Link>
          <Link :href="`/smkp/${audit.id}/elemen/I`" class="btn-utama btn-kecil"><Ikon nama="clipboard-check" class="w-4 h-4" /> Buka Penilaian</Link>
        </div>
      </div>
      <ol class="mt-4 grid gap-2 sm:grid-cols-3">
        <li v-for="(l, i) in ALUR" :key="l.judul" class="flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50/70 px-3.5 py-3">
          <span class="shrink-0 grid place-items-center w-6 h-6 rounded-full bg-navy-900 text-white text-[11.5px] font-bold tabular-nums">{{ i + 1 }}</span>
          <span class="min-w-0">
            <span class="block text-[12.5px] font-semibold text-navy-900">{{ l.judul }}</span>
            <span class="block text-[12px] text-slate-500 leading-snug">{{ l.teks }}</span>
          </span>
        </li>
      </ol>
    </section>

    <div v-if="!bisaSunting && !bisaUnggah" class="mb-5 flex items-start gap-3 rounded-kartu border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] text-amber-900">
      <Ikon nama="kunci" class="w-4 h-4 shrink-0 mt-0.5 text-amber-600" />
      <p>Audit sudah selesai — bukti tidak dapat ditambahkan lagi. Berkas dan hasil verifikasinya tetap dapat dilihat.</p>
    </div>

    <!-- Statistik: satu kartu ringkas; kemajuan butir berbukti paling menonjol -->
    <section class="kartu mb-5 overflow-hidden grid grid-cols-2 md:grid-cols-5 gap-px bg-slate-100" aria-label="Ringkasan bukti">
      <div class="col-span-2 md:col-span-1 bg-white px-4 py-3.5">
        <p class="flex items-center gap-1.5 text-[12px] font-semibold text-slate-500"><Ikon nama="folder" class="w-3.5 h-3.5 text-brand-600" /> Butir berbukti</p>
        <p class="mt-1 text-[22px] font-bold text-navy-900 leading-tight tabular-nums">{{ total.berbukti }}<span class="text-[13px] font-semibold text-slate-400">/{{ total.butir }}</span></p>
        <div class="bilah mt-2"><i class="bg-teal-500" :style="{ width: `${total.butir ? (total.berbukti / total.butir) * 100 : 0}%` }" /></div>
        <p class="mt-1.5 text-[11.5px] text-slate-400 tabular-nums">{{ total.berkas }} berkas terunggah</p>
      </div>
      <div v-for="x in statistik" :key="x.label" class="bg-white px-4 py-3.5">
        <p class="flex items-start gap-1.5 text-[12px] font-semibold leading-snug text-slate-500"><Ikon :nama="x.ikon" class="w-3.5 h-3.5 shrink-0 mt-px" :class="x.warna" /> {{ x.label }}</p>
        <p class="mt-1 text-[22px] font-bold text-navy-900 leading-tight tabular-nums">{{ x.nilai }}</p>
        <p class="mt-0.5 text-[11.5px] text-slate-400">{{ x.ket }}</p>
      </div>
    </section>

    <div class="grid gap-5 items-start" :class="bisaSunting && antrean.length ? 'xl:grid-cols-[minmax(0,1fr)_360px]' : ''">
      <!-- Antrean verifikasi (auditor / administrator); kolom sendiri hanya bila ada isinya -->
      <aside v-if="bisaSunting && antrean.length" class="order-first xl:order-last kartu overflow-hidden flex flex-col xl:sticky xl:top-20 xl:max-h-[calc(100vh-6.5rem)]">
        <div class="kartu-kepala shrink-0">
          <div class="min-w-0">
            <h3 class="kartu-judul">Antrean verifikasi</h3>
            <p class="kartu-sub">Berkas auditi yang menunggu diperiksa, terlama di atas.</p>
          </div>
          <span class="lencana tabular-nums bg-amber-50 text-amber-700 ring-1 ring-amber-200">{{ antrean.length }}{{ antrean.length >= BATAS_ANTREAN ? '+' : '' }}</span>
        </div>
        <ul class="flex-1 min-h-0 max-h-[420px] xl:max-h-none overflow-y-auto scrollbar-tipis divide-y divide-slate-100">
          <li v-for="b in antrean" :key="b.id" class="flex items-start gap-3 px-4 py-3">
            <img v-if="b.gambar" :src="`${unduhan(b)}?lihat=1`" alt="" loading="lazy" class="shrink-0 w-9 h-9 rounded-lg object-cover bg-slate-100 ring-1 ring-slate-200">
            <span v-else class="shrink-0 grid place-items-center w-9 h-9 rounded-lg bg-slate-100 text-slate-500 text-[9.5px] font-bold tracking-wide">{{ jenis(b.nama) }}</span>
            <div class="flex-1 min-w-0">
              <a :href="unduhan(b)" class="block truncate text-[12.5px] font-semibold text-navy-900 hover:text-brand-700" :title="`Unduh ${b.nama}`">{{ b.nama }}</a>
              <p class="mt-0.5 text-[11.5px] text-slate-500 line-clamp-1" :title="lokasiButir[b.kode]?.nama">
                <span class="tabular-nums font-semibold text-slate-600">{{ b.kode }}</span><template v-if="lokasiButir[b.kode]"> · {{ lokasiButir[b.kode].nama }}</template>
              </p>
              <p class="text-[11px] text-slate-400"><template v-if="b.oleh">{{ b.oleh }} · </template><span class="tabular-nums whitespace-nowrap">{{ b.waktu ?? '' }}</span> · <span class="tabular-nums whitespace-nowrap">{{ ukuranBerkas(b.ukuran) }}</span></p>
              <p v-if="b.keterangan" class="mt-1 text-[12px] text-slate-600 leading-snug line-clamp-2">{{ b.keterangan }}</p>
            </div>
            <Link :href="tautanButir(b.kode)" class="btn-garis btn-kecil shrink-0">Periksa</Link>
          </li>
        </ul>
        <p v-if="antrean.length >= BATAS_ANTREAN" class="shrink-0 px-4 py-2.5 border-t border-slate-100 text-[11.5px] text-slate-400">Menampilkan {{ BATAS_ANTREAN }} berkas terlama — selebihnya muncul setelah antrean ini diperiksa.</p>
      </aside>

      <!-- Daftar per elemen -->
      <div class="min-w-0 space-y-4">
        <p v-if="bisaSunting && !antrean.length" class="flex items-center gap-2.5 rounded-kartu border border-emerald-100 bg-emerald-50/60 px-4 py-2.5 text-[12.5px] text-emerald-800">
          <Ikon nama="centang-bulat" class="w-4 h-4 shrink-0 text-emerald-600" /> Antrean verifikasi kosong — tidak ada berkas auditi yang menunggu diperiksa.
        </p>

        <!-- Saringan selalu terlihat utuh (membungkus, bukan digulir), pencarian di bawahnya bila sempit -->
        <div class="kartu p-2 flex flex-col 2xl:flex-row 2xl:items-center gap-2">
          <div class="flex-1 min-w-0">
            <div class="flex flex-wrap gap-1" role="tablist" aria-label="Saring sub-elemen">
              <button v-for="x in SARING" :key="x.kunci" type="button" role="tab" :aria-selected="saring === x.kunci" :title="x.judul"
                      class="inline-flex items-center gap-1.5 h-8 rounded-lg px-2.5 text-[12.5px] font-semibold transition-colors whitespace-nowrap"
                      :class="saring === x.kunci ? 'bg-navy-900 text-white shadow-cahaya' : 'text-slate-600 hover:bg-slate-100 hover:text-navy-900'"
                      @click="saring = x.kunci">
                {{ x.label }}
                <span class="tabular-nums text-[11px] font-semibold rounded px-1.5 py-px"
                      :class="saring === x.kunci ? 'bg-white/15 text-white' : x.kunci === 'menunggu' && hitungSaring[x.kunci] ? 'bg-amber-100 text-amber-800' : x.kunci === 'ditolak' && hitungSaring[x.kunci] ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-500'">{{ hitungSaring[x.kunci] }}</span>
              </button>
            </div>
          </div>
          <label class="relative block 2xl:w-64 shrink-0">
            <span class="sr-only">Cari sub-elemen atau butir</span>
            <Ikon nama="cari" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input v-model="cari" type="search" class="input-kecil h-8 pl-9" placeholder="Cari kode, butir, dokumen…">
          </label>
        </div>

        <!-- Rencana belum menetapkan dokumen: jelaskan alurnya -->
        <div v-if="!total.diminta && (saring === 'semua' || saring === 'diminta')" class="kartu px-5 py-6 sm:px-8 text-center">
          <span class="mx-auto grid place-items-center w-12 h-12 rounded-kartu bg-teal-50 text-teal-700"><Ikon nama="clipboard-list" class="w-6 h-6" /></span>
          <h3 class="mt-3 text-[15px] font-bold text-navy-900">Rencana Audit belum menetapkan dokumen yang diminta</h3>
          <p class="mt-1.5 text-[13px] text-slate-500 max-w-xl mx-auto leading-relaxed">
            Alurnya: <b class="text-slate-700">Rencana Audit</b> menetapkan metode, dokumen &amp; sampel tiap sub-elemen →
            <b class="text-slate-700">auditi</b> mengunggah berkas pada butir terkait →
            <b class="text-slate-700">auditor</b> memverifikasi berkas lalu menilai.
            {{ bisaSunting ? 'Isi kolom Metode & Sampel di Rencana Audit agar auditi tahu bukti yang perlu disiapkan.' : 'Sambil menunggu, Anda tetap dapat mengunggah bukti per butir dari daftar di bawah.' }}
          </p>
          <Link v-if="bisaSunting" :href="rencanaUrl" class="mt-4 btn-utama btn-kecil"><Ikon nama="clipboard-list" class="w-4 h-4" /> Tetapkan di Rencana Audit</Link>
        </div>

        <article v-for="e in tampil" :key="e.kode" class="kartu overflow-hidden">
          <button type="button" class="w-full flex items-center gap-3 px-4 sm:px-5 py-3.5 text-left hover:bg-slate-50/70 transition-colors" :aria-expanded="!tutup[e.kode]" @click="tutup[e.kode] = !tutup[e.kode]">
            <span class="shrink-0 grid place-items-center min-w-[36px] h-9 px-1.5 rounded-lg bg-navy-900 text-white text-[12.5px] font-bold tabular-nums">{{ e.kode }}</span>
            <span class="flex-1 min-w-0">
              <span class="block text-[14px] font-semibold text-navy-900 leading-snug">{{ e.nama }}</span>
              <span class="block text-[12px] text-slate-500 tabular-nums">
                {{ totalElemen[e.kode].diminta }} sub-elemen diminta · {{ totalElemen[e.kode].berbukti }}/{{ totalElemen[e.kode].butir }} butir berbukti
              </span>
            </span>
            <span class="hidden sm:flex items-center gap-1.5">
              <span v-if="totalElemen[e.kode].menunggu" class="lencana bg-amber-50 text-amber-700 ring-1 ring-amber-200 tabular-nums">{{ totalElemen[e.kode].menunggu }} menunggu</span>
              <span v-if="totalElemen[e.kode].ditolak" class="lencana bg-rose-50 text-rose-700 ring-1 ring-rose-200 tabular-nums">{{ totalElemen[e.kode].ditolak }} perlu perbaikan</span>
            </span>
            <Ikon nama="chevron-down" class="w-4 h-4 text-slate-400 transition-transform shrink-0" :class="tutup[e.kode] ? '' : 'rotate-180'" />
          </button>

          <ul v-if="!tutup[e.kode]" class="border-t border-slate-100 divide-y divide-slate-100">
            <li v-for="s in e.sub" :key="s.kode" class="px-4 sm:px-5 py-4">
              <!-- Baris judul: sub-elemen, status, aksi -->
              <div class="flex flex-wrap items-start gap-x-4 gap-y-2">
                <p class="flex-1 min-w-[14rem] text-[13px] font-semibold text-navy-900 leading-snug"><span class="tabular-nums text-brand-600">{{ s.kode }}</span> {{ s.nama }}</p>
                <div class="flex flex-wrap items-center gap-1.5">
                  <span v-if="stat[s.kode].menunggu" class="lencana bg-amber-50 text-amber-700 ring-1 ring-amber-200 tabular-nums"><Ikon nama="jam" class="w-3 h-3" /> {{ stat[s.kode].menunggu }} menunggu</span>
                  <span v-if="stat[s.kode].ditolak" class="lencana bg-rose-50 text-rose-700 ring-1 ring-rose-200 tabular-nums"><Ikon nama="peringatan" class="w-3 h-3" /> {{ stat[s.kode].ditolak }} perlu perbaikan</span>
                  <span v-if="stat[s.kode].diterima" class="lencana bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 tabular-nums"><Ikon nama="centang-bulat" class="w-3 h-3" /> {{ stat[s.kode].diterima }} diterima</span>
                  <span v-if="!stat[s.kode].berkas" class="lencana bg-slate-100 text-slate-500">Belum ada bukti</span>
                  <Link :href="s.url" class="btn-garis btn-kecil whitespace-nowrap">
                    <Ikon :nama="bisaSunting ? 'clipboard-check' : bisaUnggah ? 'unggah' : 'mata'" class="w-3.5 h-3.5" /> {{ labelAksi }}
                  </Link>
                </div>
              </div>

              <div class="mt-3 grid gap-3 md:gap-5 md:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)] md:items-start">
                <!-- Kemajuan bukti per butir -->
                <div class="min-w-0">
                  <div class="flex items-center gap-2.5">
                    <div class="bilah flex-1 max-w-[200px]"><i class="bg-teal-500" :style="{ width: `${stat[s.kode].total ? (stat[s.kode].berbukti / stat[s.kode].total) * 100 : 0}%` }" /></div>
                    <span class="text-[11.5px] text-slate-500 tabular-nums">{{ stat[s.kode].berbukti }}/{{ stat[s.kode].total }} butir berbukti</span>
                  </div>
                  <div class="mt-2 flex flex-wrap gap-1">
                    <Link v-for="b in s.butir" :key="b.kode" :href="`${s.url}#butir-${b.kode}`" :title="judulButir(b)"
                          class="inline-flex items-center h-6 rounded-md px-1.5 text-[11px] font-semibold tabular-nums ring-1 transition-colors" :class="kelasButir(b)">{{ b.kode }}</Link>
                  </div>
                </div>

                <!-- Dokumen & sampel diminta -->
                <div class="min-w-0">
                  <div v-if="s.diminta" class="rounded-lg border border-teal-100 bg-teal-50/40 px-3 py-2.5 text-[12.5px] leading-relaxed">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.06em] text-teal-800">Diminta Rencana Audit</p>
                    <p v-if="s.diminta.metode" class="mt-1 text-slate-600"><span class="text-slate-400">Metode:</span> {{ s.diminta.metode }}</p>
                    <p v-if="s.diminta.sampel" class="mt-0.5 text-navy-900 whitespace-pre-line"><span class="text-slate-400">Sampel / dokumen:</span> {{ s.diminta.sampel }}</p>
                  </div>
                  <p v-else class="flex items-center gap-1.5 rounded-lg border border-dashed border-slate-200 px-3 py-2.5 text-[12px] text-slate-400"><Ikon nama="clipboard-list" class="w-3.5 h-3.5" /> Belum ditetapkan di Rencana Audit</p>
                </div>
              </div>
            </li>
          </ul>
        </article>

        <div v-if="!tampil.length && (pesanKosong || total.diminta)" class="kartu text-center px-6 py-12">
          <span class="mx-auto grid place-items-center w-14 h-14 rounded-kartu bg-slate-100 text-slate-400"><Ikon :nama="cari.trim() ? 'cari' : 'centang-bulat'" class="w-6 h-6" /></span>
          <h3 class="mt-4 text-[15px] font-bold text-navy-900">{{ pesanKosong?.judul ?? 'Tidak ada sub-elemen pada saringan ini' }}</h3>
          <p v-if="pesanKosong" class="mt-1.5 text-[13px] text-slate-500 max-w-md mx-auto leading-relaxed">{{ pesanKosong.pesan }}</p>
          <button v-if="saring !== 'semua' || cari.trim()" type="button" class="mt-5 btn-garis btn-kecil" @click="saring = 'semua'; cari = ''">Tampilkan semua sub-elemen</button>
        </div>
      </div>
    </div>
  </div>
</template>

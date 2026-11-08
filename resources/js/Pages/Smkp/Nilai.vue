<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import KepalaAudit from '../../Components/Smkp/KepalaAudit.vue';
import Ikon from '../../Components/Smkp/Ikon.vue';
import AsistenButir from '../../Components/Smkp/Asisten/AsistenButir.vue';
import AsistenBukti from '../../Components/Smkp/Asisten/AsistenBukti.vue';
import KriteriaNilai from '../../Components/Smkp/KriteriaNilai.vue';
import BerkasBukti from '../../Components/Smkp/Bukti/BerkasBukti.vue';
import { angka } from '../../smkp/lib/format';
import { KELAS_NADA, nadaNilai } from '../../smkp/lib/nilai';
import type { BatasBerkas, BerkasBukti as Berkas, DokumenDiminta, RingkasAudit, Rubrik } from '../../smkp/types';

/**
 * Nilai tersimpan yang perlu dinilai ulang: skala lama (maks_lama), di atas maksimum, atau diberikan
 * sebelum deskripsi tingkatnya dikoreksi Daftar Koreksi (koreksi = nomornya); `setara` = nilainya pada skala sekarang.
 */
interface Ulang { v: number; maks: number; maks_lama: number | null; koreksi: number | null; setara: number }
interface Butir { kode: string; nama: string; maks: number; v: string; ket: string; bukti: string; ulang: Ulang | null; rubrik: Rubrik | null; berkas: Berkas[] }
interface Sub {
  kode: string; nama: string; ref: string | null; rinci: boolean; nilai: number; maks: number; dinilai: number; berlaku: number;
  kategori: { kode: string; label: string; warna: string }; butir: Butir[];
  /** Dokumen & sampel yang diminta Rencana Audit untuk sub-elemen ini. */
  diminta: DokumenDiminta | null;
}
interface Kategori { kode: string; label: string; min: number; warna: string; ket?: string }

const props = defineProps<{
  audit: RingkasAudit;
  elemen: { kode: string; nama: string; bobot: number };
  sub: Sub[];
  rekap: { maks: number; nilai: number; capaian: number; bobot: number; skor: number; berlaku: number; dinilai: number; total: number };
  semua: Array<{ kode: string; nama: string; dinilai: number; berlaku: number; ulang: number; url: string }>;
  kategori: Kategori[];
  na: string;
  bisaSunting: boolean;
  /** Sub-elemen yang dibuka lebih dulu (tautan dari halaman Bukti Audit). */
  pilihSub: string | null;
  bukti: { bisaUnggah: boolean; batas: BatasBerkas; adaRencana: boolean };
}>();

// `ulang` = auditor telah memilih nilai baru untuk butir yang perlu dinilai ulang.
function isianAwal() {
  const k: Record<string, { v: string; ket: string; bukti: string; ulang: boolean }> = {};
  for (const s of props.sub) for (const b of s.butir) k[b.kode] = { v: b.v, ket: b.ket, bukti: b.bukti, ulang: false };
  return k;
}

const salin = <T,>(x: T): T => JSON.parse(JSON.stringify(x));
let dasar = isianAwal();
const form = useForm({ k: salin(dasar) });

const subAwal = () => props.pilihSub ?? props.sub[0]?.kode ?? '';
const pilih = ref(subAwal());
watch(() => props.elemen.kode, () => { pilih.value = subAwal(); });

const subAktif = computed(() => props.sub.find((s) => s.kode === pilih.value) ?? props.sub[0]);

/* Rekap hidup — cermin Smkp::rekapSub di server, hanya untuk dibaca. */
function kategoriDari(capaian: number) {
  const persen = capaian * 100;
  return props.kategori.find((k) => persen >= k.min) ?? props.kategori[props.kategori.length - 1];
}

function rekapSub(s: Sub) {
  let maks = 0, nilai = 0, berlaku = 0, dinilai = 0;
  for (const b of s.butir) {
    const v = form.k[b.kode]?.v ?? '';
    if (v === props.na) continue;
    berlaku++;
    maks += b.maks;
    if (v !== '') { dinilai++; nilai += perluUlang(b) ? b.ulang!.setara : Math.max(0, Math.min(Number(v), b.maks)); }
  }
  const capaian = maks ? nilai / maks : 0;
  return {
    maks, nilai, berlaku, dinilai, total: s.butir.length, capaian,
    // Kategori baru bermakna setelah seluruh butir berlaku dinilai (sama dengan Smkp::temuan).
    kategori: berlaku === 0 ? { kode: 'na', label: 'Tidak Berlaku', warna: '#94A3B8' } : (dinilai < berlaku ? null : kategoriDari(capaian)),
  };
}
const rekapPer = computed(() => Object.fromEntries(props.sub.map((s) => [s.kode, rekapSub(s)])));

const ringkas = computed(() => {
  let berlaku = 0, dinilai = 0, nilai = 0, maks = 0;
  const hitung = { kesesuaian: 0, minor: 0, mayor: 0, belum: 0, na: 0 } as Record<string, number>;
  for (const s of props.sub) {
    const r = rekapPer.value[s.kode];
    berlaku += r.berlaku; dinilai += r.dinilai; nilai += r.nilai; maks += r.maks;
    if (r.berlaku === 0) hitung.na++;
    else if (r.dinilai < r.berlaku) hitung.belum++;
    else if (r.kategori) hitung[r.kategori.kode] = (hitung[r.kategori.kode] ?? 0) + 1;
  }
  return { berlaku, dinilai, nilai, maks, capaian: maks ? nilai / maks : 0, persen: berlaku ? Math.round((dinilai / berlaku) * 100) : 0, hitung };
});

/* Bukti berkas per sub-elemen dan per elemen: penanda di daftar sub-elemen. */
function hitungBukti(daftar: Butir[]) {
  const h = { berkas: 0, menunggu: 0, ditolak: 0, diterima: 0 };
  for (const b of daftar) for (const f of b.berkas) { h.berkas++; h[f.status]++; }
  return h;
}
const buktiPer = computed(() => Object.fromEntries(props.sub.map((s) => [s.kode, hitungBukti(s.butir)])));
const buktiElemen = computed(() => hitungBukti(props.sub.flatMap((s) => s.butir)));

function setel(b: Butir, v: string) {
  if (!props.bisaSunting) return;
  const isi = form.k[b.kode];
  // Butir yang perlu dinilai ulang: pilihan pertama selalu menetapkan nilai
  // (juga bila angkanya sama dengan nilai lama), bukan mengosongkannya.
  if (perluUlang(b)) { isi.v = v; isi.ulang = true; return; }
  isi.v = isi.v === v ? '' : v;
}

/* Berpindah sub-elemen: bila kepala sub-elemen tidak terlihat (mis. dari
   bilah bawah atau dari daftar di ponsel), layar digulir ke sana. */
const kartuSub = ref<HTMLElement | null>(null);
function bukaSub(kode: string) {
  if (kode === pilih.value) return;
  pilih.value = kode;
  nextTick(() => {
    const el = kartuSub.value;
    if (!el) return;
    const atas = el.getBoundingClientRect().top;
    if (atas < 72 || atas > window.innerHeight * 0.6) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
}

const indeks = computed(() => props.sub.findIndex((s) => s.kode === subAktif.value?.kode));
function geser(arah: number) {
  const s = props.sub[indeks.value + arah];
  if (s) bukaSub(s.kode);
}

/* Asisten AI: saran diterapkan ke isian butir di layar; tersimpan lewat Simpan. */
function terapkanAsisten(b: Butir, kolom: 'v' | 'ket', nilai: string) {
  if (!props.bisaSunting) return;
  if (kolom === 'v' && perluUlang(b)) form.k[b.kode].ulang = true;
  form.k[b.kode][kolom] = nilai;
}
// Nilai lama yang belum dinilai ulang tidak dikirim ke asisten sebagai nilai sekarang.
const isianAsisten = (b: Butir) => (perluUlang(b) ? { ...form.k[b.kode], v: '' } : form.k[b.kode]);

type Isian = { v: string; ket: string; bukti: string; ulang: boolean };
const beda = (a: Isian, b: Isian | undefined) => !b || a.v !== b.v || a.ket !== b.ket || a.bukti !== b.bukti || a.ulang !== b.ulang;
const berubah = computed(() => Object.keys(form.k).filter((kode) => beda(form.k[kode], dasar[kode])).length);

// Hanya butir yang diubah yang dikirim: isian rekan yang menyimpan lebih
// dulu pada elemen yang sama tidak tertimpa oleh halaman yang sudah lama.
function simpan() {
  if (!props.bisaSunting || form.processing) return;
  form.transform((d) => {
    const k: typeof d.k = {};
    for (const kode in d.k) if (beda(d.k[kode], dasar[kode])) k[kode] = d.k[kode];
    return { k };
  }).post(`/smkp/${props.audit.id}/elemen/${props.elemen.kode}`, {
    preserveScroll: true,
    // Props kini memuat nilai terbaru (termasuk milik rekan): formulir disegarkan.
    onSuccess: () => { dasar = isianAwal(); form.k = salin(dasar); form.defaults(); },
  });
}

/* Ctrl/⌘ + S menyimpan penilaian elemen ini. */
function pintasan(e: KeyboardEvent) {
  if (!props.bisaSunting || !(e.ctrlKey || e.metaKey) || e.key.toLowerCase() !== 's') return;
  e.preventDefault();
  if (form.isDirty) simpan();
}
const mac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform);

/* Tautan #butir-KODE (dari antrean verifikasi) membuka sub-elemennya lalu
   menggulir ke butir tersebut dan menyorotnya sejenak. */
const sorot = ref('');
let jedaSorot: ReturnType<typeof setTimeout> | null = null;
function kePenanda() {
  const hash = decodeURIComponent(window.location.hash.slice(1));
  if (!hash.startsWith('butir-')) return;
  const kode = hash.slice(6);
  const s = props.sub.find((x) => x.butir.some((b) => b.kode === kode));
  if (!s) return;
  pilih.value = s.kode;
  sorot.value = kode;
  if (jedaSorot) clearTimeout(jedaSorot);
  jedaSorot = setTimeout(() => { sorot.value = ''; }, 2600);
  nextTick(() => document.getElementById(hash)?.scrollIntoView({ block: 'start' }));
}

/* Berkas yang terlepas di luar bagian bukti tidak boleh membuat peramban
   membuka berkas itu dan membuang isian nilai yang belum disimpan. */
function tahanLepas(e: DragEvent) {
  if (Array.from(e.dataTransfer?.types ?? []).includes('Files')) e.preventDefault();
}

onMounted(() => {
  window.addEventListener('keydown', pintasan);
  window.addEventListener('dragover', tahanLepas);
  window.addEventListener('drop', tahanLepas);
  kePenanda();
});
onBeforeUnmount(() => {
  window.removeEventListener('keydown', pintasan);
  window.removeEventListener('dragover', tahanLepas);
  window.removeEventListener('drop', tahanLepas);
  if (jedaSorot) clearTimeout(jedaSorot);
});

const warnaKode: Record<string, string> = { kesesuaian: '#16A34A', minor: '#D97706', mayor: '#DC2626' };
const warnaSub = (s: Sub) => {
  const k = rekapPer.value[s.kode]?.kategori;
  return k ? (warnaKode[k.kode] ?? k.warna) : '#CBD5E1';
};

/* Kriteria penilaian resmi: tingkat yang terpilih selalu tampil, tangga lengkapnya dibuka per butir. */
const kriteriaBuka = ref<Record<string, boolean>>({});
// Butir II.5 & III.3 berbeda untuk pemegang IUP dan IUJP; auditi PJP memakai versi IUJP.
const varian = computed<'iup' | 'iujp'>(() => (props.audit.perusahaan.kodeKategori === 'pjp' ? 'iujp' : 'iup'));

function tingkatTerpilih(b: Butir) {
  const v = form.k[b.kode]?.v ?? '';
  const r = b.rubrik;
  if (!r || v === '' || perluUlang(b)) return null;
  const isi = r.varian ? (r.varian.find((x) => x.untuk === varian.value) ?? r.varian[0]) : { nilai: r.nilai ?? [], na: r.na ?? null };
  if (v === props.na) return isi.na ? { label: 'N/A', teks: isi.na } : null;
  const t = isi.nilai.find((x) => String(x.n) === v);
  return t ? { label: `Nilai ${t.n}`, teks: t.teks } : null;
}

/* Dinilai pada skala lama / di atas maksimum dan belum dipilih ulang auditor. */
function perluUlang(b: Butir) {
  const isi = form.k[b.kode];
  return !!b.ulang && !!isi && !isi.ulang && isi.v === dasar[b.kode]?.v;
}
const angkaRingkas = (n: number) => angka(n, n % 1 ? 1 : 0);
const ulangSub = (s: Sub) => s.butir.filter(perluUlang).length;
</script>

<template>
  <div class="smkp-ui">
    <Head :title="`Elemen ${elemen.kode} — Penilaian`" />
    <KepalaAudit :audit="audit" aktif="nilai" :bisa-sunting="bisaSunting" />

    <!-- Petunjuk untuk auditi: alur bukti → verifikasi → nilai -->
    <div v-if="!bisaSunting" class="mb-5 flex flex-col sm:flex-row sm:items-center gap-3 rounded-kartu border px-4 py-3.5"
         :class="bukti.bisaUnggah ? 'border-brand-100 bg-brand-50/60' : 'border-amber-200 bg-amber-50'">
      <span class="hidden sm:grid shrink-0 place-items-center w-9 h-9 rounded-lg" :class="bukti.bisaUnggah ? 'bg-white text-brand-600 ring-1 ring-brand-100' : 'bg-white text-amber-600 ring-1 ring-amber-200'">
        <Ikon :nama="bukti.bisaUnggah ? 'unggah' : 'kunci'" class="w-[18px] h-[18px]" />
      </span>
      <div class="flex-1 min-w-0 text-[13px] leading-relaxed">
        <template v-if="bukti.bisaUnggah">
          <p class="font-semibold text-navy-900">Unggah bukti sesuai dokumen yang diminta Rencana Audit.</p>
          <p class="text-slate-600">Pilih sub-elemen, periksa <b class="font-semibold">Dokumen &amp; sampel diminta</b>, lalu unggah berkas pada butir yang bersangkutan. Auditor/administrator memverifikasi setiap berkas, kemudian menetapkan nilai — nilai di halaman ini hanya dapat dibaca.</p>
        </template>
        <template v-else>
          <p class="font-semibold text-amber-900">Audit sudah selesai — bukti tidak dapat ditambahkan lagi.</p>
          <p class="text-amber-800">Berkas yang sudah diunggah dan hasil verifikasinya tetap dapat dilihat.</p>
        </template>
      </div>
      <Link :href="`/smkp/${audit.id}/bukti`" class="btn-garis btn-kecil shrink-0 self-start sm:self-center"><Ikon nama="folder" class="w-4 h-4" /> Ringkasan bukti</Link>
    </div>

    <!-- Pemilih elemen -->
    <nav class="mb-4 gulir-x scrollbar-tipis -mx-1 px-1 pb-1" aria-label="Elemen SMKP">
      <div class="flex gap-2 min-w-max">
        <Link v-for="e in semua" :key="e.kode" :href="e.url" preserve-scroll :title="`Elemen ${e.kode} · ${e.nama}`"
              :aria-current="e.kode === elemen.kode ? 'page' : undefined"
              class="flex items-center gap-2 h-9 rounded-lg border px-3 text-[12.5px] font-semibold transition-colors"
              :class="e.kode === elemen.kode ? 'bg-navy-900 border-navy-900 text-white shadow-cahaya' : 'bg-white border-slate-200 text-slate-600 hover:border-brand-300 hover:text-navy-900'">
          <span class="tabular-nums">{{ e.kode }}</span>
          <span class="text-[11px] font-medium tabular-nums" :class="e.kode === elemen.kode ? 'text-white/60' : 'text-slate-400'">{{ e.dinilai }}/{{ e.berlaku }}</span>
          <span v-if="e.ulang" class="grid place-items-center min-w-[18px] h-[18px] px-1 rounded-full bg-amber-500 text-white text-[10px] font-bold tabular-nums" :title="`${e.ulang} butir perlu dinilai ulang`">{{ e.ulang }}</span>
        </Link>
      </div>
    </nav>

    <!-- Ringkasan elemen -->
    <section class="kartu mb-5 p-4 sm:p-5 grid gap-4 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-center">
      <div class="min-w-0">
        <p class="eyebrow">Elemen {{ elemen.kode }} · bobot {{ elemen.bobot }}</p>
        <h2 class="mt-1 text-[17px] font-bold text-navy-900 leading-snug">{{ elemen.nama }}</h2>
        <div class="mt-3 flex items-center gap-3">
          <div class="bilah h-2.5 flex-1"><i class="bg-gradient-to-r from-brand-500 to-hijau-500" :style="{ width: `${ringkas.persen}%` }" /></div>
          <span class="text-[13px] font-bold text-navy-900 tabular-nums w-11 text-right">{{ ringkas.persen }}%</span>
        </div>
        <div class="mt-2.5 flex flex-wrap gap-x-4 gap-y-1 text-[12px] text-slate-500">
          <span class="flex items-center gap-1.5"><i class="w-2.5 h-2.5 rounded-full bg-hijau-600" /> Kesesuaian <b class="tabular-nums font-semibold text-slate-700">{{ ringkas.hitung.kesesuaian ?? 0 }}</b></span>
          <span class="flex items-center gap-1.5"><i class="w-2.5 h-2.5 rounded-full bg-amber-500" /> Minor <b class="tabular-nums font-semibold text-slate-700">{{ ringkas.hitung.minor ?? 0 }}</b></span>
          <span class="flex items-center gap-1.5"><i class="w-2.5 h-2.5 rounded-full bg-rose-600" /> Mayor <b class="tabular-nums font-semibold text-slate-700">{{ ringkas.hitung.mayor ?? 0 }}</b></span>
          <span class="flex items-center gap-1.5"><i class="w-2.5 h-2.5 rounded-full bg-slate-300" /> Belum tuntas <b class="tabular-nums font-semibold text-slate-700">{{ ringkas.hitung.belum }}</b></span>
        </div>
      </div>
      <dl class="grid grid-cols-3 gap-2 sm:gap-3 xl:w-[440px]">
        <div class="rounded-xl bg-slate-50 border border-slate-100 px-3 py-2.5 sm:px-4 sm:py-3">
          <dt class="text-[11px] sm:text-[11.5px] text-slate-500">Butir dinilai</dt>
          <dd class="mt-0.5 text-[18px] sm:text-[20px] font-bold text-navy-900 leading-tight tabular-nums">{{ ringkas.dinilai }}<span class="text-[13px] text-slate-400">/{{ ringkas.berlaku }}</span></dd>
        </div>
        <div class="rounded-xl bg-slate-50 border border-slate-100 px-3 py-2.5 sm:px-4 sm:py-3">
          <dt class="text-[11px] sm:text-[11.5px] text-slate-500">Capaian</dt>
          <dd class="mt-0.5 text-[18px] sm:text-[20px] font-bold text-navy-900 leading-tight tabular-nums">{{ (ringkas.capaian * 100).toFixed(1) }}<span class="text-[13px] text-slate-400">%</span></dd>
        </div>
        <div class="rounded-xl bg-slate-50 border border-slate-100 px-3 py-2.5 sm:px-4 sm:py-3" :title="buktiElemen.menunggu ? `${buktiElemen.menunggu} berkas menunggu verifikasi` : undefined">
          <dt class="text-[11px] sm:text-[11.5px] text-slate-500">Bukti berkas</dt>
          <dd class="mt-0.5 flex items-baseline gap-1.5 text-[18px] sm:text-[20px] font-bold text-navy-900 leading-tight tabular-nums">
            {{ buktiElemen.berkas }}
            <span v-if="buktiElemen.menunggu" class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700"><i class="w-1.5 h-1.5 rounded-full bg-amber-500" />{{ buktiElemen.menunggu }}<span class="hidden sm:inline"> menunggu</span></span>
          </dd>
        </div>
      </dl>
    </section>

    <form class="grid gap-5 lg:grid-cols-[300px_minmax(0,1fr)] 2xl:grid-cols-[340px_minmax(0,1fr)] items-start" @submit.prevent="simpan">
      <!-- Daftar sub-elemen: lengket di bawah kepala aplikasi dan tidak pernah tertutup bilah simpan.
           Tingginya menyisakan ruang kaki halaman agar di dasar gulir kepalanya tidak terdorong ke balik kepala aplikasi. -->
      <aside class="kartu overflow-hidden flex flex-col lg:sticky lg:top-20 lg:max-h-[calc(100vh-10.5rem)]">
        <div class="shrink-0 flex items-center justify-between gap-2 px-4 py-3 border-b border-slate-100">
          <p class="text-[11.5px] font-bold uppercase tracking-[0.08em] text-slate-400">Sub-elemen</p>
          <span class="text-[11.5px] text-slate-400 tabular-nums">{{ sub.length }}</span>
        </div>
        <ul class="flex-1 min-h-0 max-h-[300px] lg:max-h-none overflow-y-auto scrollbar-tipis divide-y divide-slate-100">
          <li v-for="s in sub" :key="s.kode">
            <button type="button" class="w-full text-left flex items-start gap-3 pl-3.5 pr-3 py-3 border-l-[3px] transition-colors"
                    :class="s.kode === subAktif?.kode ? 'bg-brand-50/70 border-brand-500' : 'border-transparent hover:bg-slate-50'"
                    :aria-current="s.kode === subAktif?.kode ? 'true' : undefined" @click="bukaSub(s.kode)">
              <span class="mt-1 shrink-0 w-2.5 h-2.5 rounded-full" :style="{ background: warnaSub(s) }" />
              <span class="flex-1 min-w-0">
                <span class="block text-[12.5px] font-semibold leading-snug" :class="s.kode === subAktif?.kode ? 'text-brand-700' : 'text-navy-900'">
                  <span class="tabular-nums">{{ s.kode }}</span> {{ s.nama }}
                </span>
                <span class="mt-1 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-[11px] text-slate-400">
                  <span class="tabular-nums">{{ rekapPer[s.kode].dinilai }}/{{ rekapPer[s.kode].berlaku }} butir</span>
                  <span class="tabular-nums">{{ angkaRingkas(rekapPer[s.kode].nilai) }}/{{ rekapPer[s.kode].maks }}</span>
                  <span v-if="buktiPer[s.kode].berkas" class="inline-flex items-center gap-1 text-slate-500"
                        :title="`${buktiPer[s.kode].berkas} berkas bukti${buktiPer[s.kode].menunggu ? ` · ${buktiPer[s.kode].menunggu} menunggu verifikasi` : ''}${buktiPer[s.kode].ditolak ? ` · ${buktiPer[s.kode].ditolak} perlu perbaikan` : ''}`">
                    <Ikon nama="folder" class="w-3 h-3" /><span class="tabular-nums">{{ buktiPer[s.kode].berkas }}</span>
                    <i v-if="buktiPer[s.kode].menunggu" class="w-1.5 h-1.5 rounded-full bg-amber-500" />
                    <i v-if="buktiPer[s.kode].ditolak" class="w-1.5 h-1.5 rounded-full bg-rose-500" />
                  </span>
                  <span v-if="s.diminta" class="inline-flex text-teal-600" title="Dokumen & sampel diminta Rencana Audit"><Ikon nama="clipboard-list" class="w-3 h-3" /></span>
                </span>
                <span v-if="ulangSub(s)" class="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700"><Ikon nama="peringatan" class="w-3 h-3" /> {{ ulangSub(s) }} perlu dinilai ulang</span>
              </span>
            </button>
          </li>
        </ul>
        <div class="shrink-0 hidden lg:flex flex-wrap gap-x-3 gap-y-1 px-4 py-2.5 border-t border-slate-100 text-[11px] text-slate-400">
          <span class="inline-flex items-center gap-1"><Ikon nama="clipboard-list" class="w-3 h-3 text-teal-600" /> diminta Rencana</span>
          <span class="inline-flex items-center gap-1"><i class="w-1.5 h-1.5 rounded-full bg-amber-500" /> menunggu verifikasi</span>
          <span class="inline-flex items-center gap-1"><i class="w-1.5 h-1.5 rounded-full bg-rose-500" /> perlu perbaikan</span>
        </div>
      </aside>

      <!-- Kolom kanan: butir sub-elemen terpilih + bilah simpan -->
      <div v-if="subAktif" class="min-w-0">
        <section ref="kartuSub" class="kartu overflow-hidden scroll-mt-20">
          <header class="px-4 sm:px-6 pt-4 sm:pt-5 pb-4 border-b border-slate-100">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="eyebrow">Sub-elemen {{ indeks + 1 }} dari {{ sub.length }}</p>
                <h3 class="mt-1 text-[16px] font-bold text-navy-900 leading-snug"><span class="tabular-nums text-brand-600">{{ subAktif.kode }}</span> {{ subAktif.nama }}</h3>
                <p class="mt-1 text-[12px] text-slate-500">
                  Uraian penerapan {{ subAktif.ref ?? '—' }}<template v-if="subAktif.butir[0]?.rubrik"> · kriteria nilai hal. {{ subAktif.butir[0].rubrik!.hal }}</template> · {{ subAktif.butir.length }} butir{{ subAktif.rinci ? ' (dinilai lewat rinciannya)' : '' }}
                </p>
              </div>
              <span v-if="rekapPer[subAktif.kode].kategori" class="lencana text-white" :style="{ background: warnaSub(subAktif) }">{{ rekapPer[subAktif.kode].kategori!.label }}</span>
              <span v-else class="lencana bg-slate-100 text-slate-500">Belum tuntas dinilai</span>
            </div>
            <div class="mt-3 flex items-center gap-3">
              <div class="bilah h-2 flex-1"><i :style="{ width: `${(rekapPer[subAktif.kode].capaian * 100).toFixed(1)}%`, background: rekapPer[subAktif.kode].kategori ? warnaSub(subAktif) : '#1592D6' }" /></div>
              <span class="text-[12.5px] font-semibold text-navy-900 tabular-nums">{{ angkaRingkas(rekapPer[subAktif.kode].nilai) }}/{{ rekapPer[subAktif.kode].maks }}</span>
            </div>

            <!-- Acuan dokumen dari Rencana Audit -->
            <div v-if="subAktif.diminta" class="mt-4 rounded-xl border border-teal-100 bg-teal-50/50 px-4 py-3">
              <p class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-teal-800">
                <Ikon nama="clipboard-list" class="w-3.5 h-3.5" /> Dokumen &amp; sampel diminta · Rencana Audit
              </p>
              <dl class="mt-2 grid gap-x-6 gap-y-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] text-[13px] leading-relaxed">
                <div>
                  <dt class="text-[11.5px] text-slate-500">Metode audit</dt>
                  <dd class="font-medium text-navy-900 whitespace-pre-line">{{ subAktif.diminta.metode || '—' }}</dd>
                </div>
                <div>
                  <dt class="text-[11.5px] text-slate-500">Sampel / dokumen</dt>
                  <dd class="font-medium text-navy-900 whitespace-pre-line">{{ subAktif.diminta.sampel || '—' }}</dd>
                </div>
              </dl>
            </div>
            <p v-else class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 rounded-xl border border-dashed border-slate-200 px-4 py-2.5 text-[12.5px] text-slate-500">
              <Ikon nama="clipboard-list" class="w-4 h-4 text-slate-400" /> Belum ditetapkan di Rencana Audit.
              <Link v-if="bisaSunting" :href="`/smkp/${audit.id}/rencana`" class="tautan">Tetapkan dokumen &amp; sampel</Link>
            </p>
          </header>

          <div class="divide-y divide-slate-100">
            <article v-for="b in subAktif.butir" :id="`butir-${b.kode}`" :key="b.kode"
                     class="scroll-mt-20 px-4 sm:px-6 py-5 transition-colors duration-700" :class="sorot === b.kode ? 'bg-brand-50/70' : ''">
              <!-- Kepala butir: kode, nama, nilai maksimal -->
              <div class="flex items-start gap-3">
                <span class="shrink-0 mt-px grid place-items-center w-6 h-6 rounded-md text-[11px] font-bold"
                      :class="form.k[b.kode].v === '' ? 'bg-slate-100 text-slate-400' : perluUlang(b) ? 'bg-amber-500 text-white' : KELAS_NADA[nadaNilai(form.k[b.kode].v, b.maks)].lencana"
                      :title="form.k[b.kode].v === '' ? 'Belum dinilai' : 'Sudah dinilai'">
                  <Ikon v-if="form.k[b.kode].v !== ''" nama="centang" class="w-3.5 h-3.5" :tebal="3" /><template v-else>·</template>
                </span>
                <div class="flex-1 min-w-0">
                  <p class="text-[11.5px] font-bold text-slate-400 tabular-nums leading-none mt-1">{{ b.kode }}</p>
                  <h4 class="mt-1 text-[13.5px] font-semibold text-navy-900 leading-relaxed">{{ b.nama }}</h4>
                </div>
                <span class="shrink-0 lencana bg-navy-50 text-navy-700 tabular-nums" title="Nilai maksimal menurut kriteria penilaian Kepdirjen">maks {{ b.maks }}</span>
              </div>

              <div class="mt-3 space-y-3 sm:pl-9">
                <p v-if="perluUlang(b)" class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[12.5px] leading-snug text-amber-800">
                  <Ikon nama="peringatan" class="w-4 h-4 shrink-0 mt-px" />
                  <span v-if="b.ulang!.maks_lama">Dinilai <b class="tabular-nums">{{ angkaRingkas(b.ulang!.v) }}</b> pada skala lama 0–{{ b.ulang!.maks_lama }}, sebelum nilai maksimal butir ini dikoreksi ke Kepdirjen (<b class="tabular-nums">{{ b.maks }}</b>). Sementara dihitung setara <b class="tabular-nums">{{ angkaRingkas(b.ulang!.setara) }}/{{ b.maks }}</b> agar kategori temuannya tidak berubah; pilih nilai sesuai kriteria lalu simpan.</span>
                  <span v-else-if="b.ulang!.koreksi">Dinilai <b class="tabular-nums">{{ angkaRingkas(b.ulang!.v) }}</b> sebelum deskripsi tingkat butir ini dikoreksi oleh Daftar Koreksi dan Klarifikasi Lampiran II (no. {{ b.ulang!.koreksi }}). Nilainya tetap dihitung; periksa kembali kriteria, pilih nilai yang sesuai, lalu simpan.</span>
                  <span v-else>Nilai tersimpan <b class="tabular-nums">{{ angkaRingkas(b.ulang!.v) }}</b> melebihi nilai maksimal butir ini (<b class="tabular-nums">{{ b.maks }}</b>); sementara dihitung {{ b.maks }}. Pilih nilai sesuai kriteria lalu simpan.</span>
                </p>

                <!-- Nilai -->
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                  <div class="inline-flex flex-wrap gap-1.5" role="group" :aria-label="`Nilai butir ${b.kode}`">
                    <button v-for="v in b.maks + 1" :key="v" type="button" :disabled="!bisaSunting" @click="setel(b, String(v - 1))"
                            class="w-10 h-10 rounded-lg border text-[14px] font-bold tabular-nums transition disabled:cursor-default"
                            :aria-pressed="form.k[b.kode].v === String(v - 1) && !perluUlang(b)"
                            :class="form.k[b.kode].v === String(v - 1) && !perluUlang(b) ? `${KELAS_NADA[nadaNilai(v - 1, b.maks)].pilih} shadow-cahaya` : bisaSunting ? 'bg-white border-slate-200 text-slate-500 hover:border-brand-300 hover:text-navy-900' : 'bg-slate-50 border-slate-200 text-slate-400'">{{ v - 1 }}</button>
                    <button type="button" :disabled="!bisaSunting" @click="setel(b, na)"
                            class="h-10 px-3.5 rounded-lg border text-[12px] font-bold transition disabled:cursor-default"
                            :aria-pressed="form.k[b.kode].v === na"
                            :class="form.k[b.kode].v === na ? KELAS_NADA.slate.pilih : bisaSunting ? 'bg-white border-slate-200 text-slate-500 hover:border-slate-400' : 'bg-slate-50 border-slate-200 text-slate-400'">N/A</button>
                  </div>
                  <div class="flex flex-wrap items-center gap-1.5 sm:ml-auto">
                    <button v-if="b.rubrik" type="button" class="btn-hantu btn-kecil" :aria-expanded="!!kriteriaBuka[b.kode]" @click="kriteriaBuka[b.kode] = !kriteriaBuka[b.kode]">
                      <Ikon nama="buku" class="w-4 h-4" /> Kriteria 0–{{ b.maks }}
                      <Ikon nama="chevron-down" class="w-3.5 h-3.5 transition-transform" :class="kriteriaBuka[b.kode] ? 'rotate-180' : ''" />
                    </button>
                    <AsistenButir v-if="bisaSunting" :audit-id="audit.id" :butir="b" :isian="isianAsisten(b)" @terapkan="(kolom, nilai) => terapkanAsisten(b, kolom, nilai)" />
                  </div>
                </div>

                <!-- Kriteria Kepdirjen -->
                <div v-if="kriteriaBuka[b.kode]" class="rounded-xl bg-slate-50/80 border border-slate-100 p-3">
                  <KriteriaNilai :rubrik="b.rubrik" :maks="b.maks" :v="perluUlang(b) ? '' : form.k[b.kode].v" :bisa-pilih="bisaSunting" :varian="varian"
                                 @pilih="(v) => { if (perluUlang(b) || form.k[b.kode].v !== v) setel(b, v); }" />
                </div>
                <div v-else-if="tingkatTerpilih(b)" class="rounded-lg border border-l-4 bg-white px-3.5 py-2.5" :class="KELAS_NADA[nadaNilai(form.k[b.kode].v, b.maks)].garis">
                  <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ tingkatTerpilih(b)!.label }} — kriteria Kepdirjen</p>
                  <p class="mt-1 text-[12.5px] leading-relaxed text-slate-600 whitespace-pre-line">{{ tingkatTerpilih(b)!.teks }}</p>
                </div>

                <!-- Bukti objektif & catatan auditor -->
                <div v-if="bisaSunting" class="grid sm:grid-cols-2 gap-3">
                  <div>
                    <label :for="`bo-${b.kode}`" class="block text-[11.5px] font-semibold text-slate-500 mb-1">Bukti objektif / dokumen rujukan</label>
                    <input :id="`bo-${b.kode}`" v-model="form.k[b.kode].bukti" class="input" maxlength="500" placeholder="mis. SOP-K3-012 rev. 2; 3 sampel izin kerja">
                  </div>
                  <div>
                    <label :for="`ca-${b.kode}`" class="block text-[11.5px] font-semibold text-slate-500 mb-1">Catatan auditor</label>
                    <input :id="`ca-${b.kode}`" v-model="form.k[b.kode].ket" class="input" maxlength="2000" placeholder="Hasil pemeriksaan, wawancara, observasi">
                  </div>
                </div>
                <dl v-else-if="form.k[b.kode].bukti || form.k[b.kode].ket" class="grid sm:grid-cols-2 gap-3 rounded-lg bg-slate-50 border border-slate-100 px-3.5 py-2.5 text-[12.5px]">
                  <div v-if="form.k[b.kode].bukti"><dt class="text-[11.5px] font-semibold text-slate-500">Bukti objektif</dt><dd class="mt-0.5 text-slate-700 whitespace-pre-line">{{ form.k[b.kode].bukti }}</dd></div>
                  <div v-if="form.k[b.kode].ket"><dt class="text-[11.5px] font-semibold text-slate-500">Catatan auditor</dt><dd class="mt-0.5 text-slate-700 whitespace-pre-line">{{ form.k[b.kode].ket }}</dd></div>
                </dl>

                <!-- Bukti berkas (unggah auditi, verifikasi auditor) -->
                <BerkasBukti :audit-id="audit.id" :kode="b.kode" :berkas="b.berkas" :bisa-unggah="bukti.bisaUnggah"
                             :bisa-verifikasi="bisaSunting" :batas="bukti.batas">
                  <!-- Telaah berkas oleh asisten AI: saran nilai & catatan diterapkan ke isian butir -->
                  <template v-if="bisaSunting && b.berkas.length" #aksi>
                    <AsistenBukti :audit-id="audit.id" :butir="{ kode: b.kode, nama: b.nama, maks: b.maks }" :isian="isianAsisten(b)"
                                  :jumlah-berkas="b.berkas.filter((x) => x.status !== 'ditolak').length" @terapkan="(kolom: 'v' | 'ket', nilai: string) => terapkanAsisten(b, kolom, nilai)" />
                  </template>
                </BerkasBukti>
              </div>
            </article>
          </div>

          <!-- Navigasi sub-elemen bagi auditi (auditor memakai bilah simpan) -->
          <div v-if="!bisaSunting" class="flex items-center justify-between gap-3 px-4 sm:px-6 py-3 border-t border-slate-100 bg-slate-50/60">
            <button type="button" class="btn-hantu btn-kecil" :disabled="indeks <= 0" @click="geser(-1)"><Ikon nama="chevron-left" class="w-4 h-4" /> Sebelumnya</button>
            <span class="text-[12px] text-slate-400 tabular-nums">{{ indeks + 1 }} / {{ sub.length }}</span>
            <button type="button" class="btn-hantu btn-kecil" :disabled="indeks >= sub.length - 1" @click="geser(1)">Berikutnya <Ikon nama="chevron-right" class="w-4 h-4" /></button>
          </div>
        </section>

        <!-- Bilah simpan: hanya selebar kolom kanan, mengalir setelah butir terakhir; satu baris juga di ponsel -->
        <div v-if="bisaSunting" class="sticky bottom-3 z-20 mt-4 flex items-center gap-2 sm:gap-3 rounded-kartu bg-navy-900 text-white pl-1.5 pr-2 sm:px-4 py-2 sm:py-2.5 shadow-angkat ring-1 ring-white/10">
          <div class="flex items-center shrink-0">
            <button type="button" class="grid place-items-center w-8 h-8 rounded-lg text-white/70 hover:text-white hover:bg-white/10 disabled:opacity-30 disabled:hover:bg-transparent"
                    :disabled="indeks <= 0" aria-label="Sub-elemen sebelumnya" title="Sub-elemen sebelumnya" @click="geser(-1)"><Ikon nama="chevron-left" class="w-4 h-4" /></button>
            <span class="min-w-[2.75rem] sm:min-w-[3.25rem] text-center text-[12px] text-white/60 tabular-nums">{{ indeks + 1 }} / {{ sub.length }}</span>
            <button type="button" class="grid place-items-center w-8 h-8 rounded-lg text-white/70 hover:text-white hover:bg-white/10 disabled:opacity-30 disabled:hover:bg-transparent"
                    :disabled="indeks >= sub.length - 1" aria-label="Sub-elemen berikutnya" title="Sub-elemen berikutnya" @click="geser(1)"><Ikon nama="chevron-right" class="w-4 h-4" /></button>
          </div>
          <p class="flex-1 min-w-0 text-[12px] sm:text-[12.5px] leading-tight sm:leading-snug" aria-live="polite">
            <span v-if="form.isDirty" class="font-semibold text-amber-300">{{ berubah ? `${berubah} butir belum disimpan` : 'Ada perubahan belum disimpan' }}</span>
            <span v-else class="text-white/75"><span class="sm:hidden">Tersimpan</span><span class="hidden sm:inline">Penilaian elemen {{ elemen.kode }} tersimpan</span></span>
            <span class="hidden min-[1400px]:inline text-white/45"> · nilai sementara <span class="tabular-nums">{{ angka(ringkas.capaian * elemen.bobot) }}</span> dari bobot {{ elemen.bobot }}</span>
          </p>
          <button type="submit" class="btn-hijau h-9 shrink-0 px-3 sm:px-4" :disabled="form.processing || !form.isDirty" :title="`Simpan penilaian elemen ${elemen.kode} (${mac ? '⌘' : 'Ctrl'}+S)`">
            <Ikon nama="simpan" class="w-4 h-4" />
            <span v-if="form.processing">Menyimpan…</span>
            <span v-else>Simpan<span class="hidden sm:inline"> elemen {{ elemen.kode }}</span></span>
          </button>
        </div>
      </div>
    </form>
  </div>
</template>

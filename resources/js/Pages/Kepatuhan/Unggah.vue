<script setup lang="ts">
/**
 * Unggah & Rangkum.
 *
 * Kiri: naskah peraturannya — berkas .pdf/.docx/.txt, atau ditempel
 * langsung. Kanan: identitas peraturan dan butir-butir yang ditemukan,
 * untuk DIPERIKSA, disunting, dan dicentang sebelum disimpan.
 *
 * Alurnya dijalankan halaman ini sendiri, selangkah demi selangkah:
 *
 *   1. Baca berkas  — PDF dibaca DI PERAMBAN (pdfPeraturan.ts); yang
 *                      dikirim hanya teksnya. Halaman hasil pindaian
 *                      dikirim sebagai gambar JPEG satu-dua halaman sekali
 *                      minta. docx/txt diunggah biasa (kecil).
 *   2. Pecah        — server memecah pasal/ayat dan membaca identitasnya.
 *   3. Analisis     — identitas peraturan, lalu butirnya sepuluh sekali
 *                      minta: rangkuman, kewajiban atau bukan, dan usulan
 *                      penerapan.
 *
 * Setiap permintaan diulang sendiri bila jaringannya putus atau server
 * sedang sibuk. Sebelumnya satu putusan jaringan saja — lazim pada
 * jaringan lapangan — langsung berakhir sebagai "Tidak tersambung ke
 * server", dan seluruh pekerjaan harus diulang dari awal.
 *
 * Halaman ini tidak menyebut mesin analisisnya. Penyedia dan model yang
 * dipakai urusan pemasang di Pusat Kendali.
 */
import { computed, reactive, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import type { HalamanKepatuhanUnggah, HasilRangkum, IdentitasPeraturan } from '../../types';
import PindahKepatuhan from './Pindah.vue';
import type { PDFDocumentProxy } from 'pdfjs-dist';

const props = defineProps<HalamanKepatuhanUnggah>();

type Keadaan = 'belum' | 'proses' | 'ok' | 'gagal';

interface Butir {
  no: number; penunjuk: string; isi: string;
  rangkuman: string; penerapan: string;
  ikut: boolean; ikutDisentuh: boolean;
  kewajiban: boolean | null; analisis: Keadaan;
}

const hasil = ref<Butir[]>([]);
const catatan = ref<string[]>([]);
const naskah = ref('');
const galatBaca = ref<Record<string, string>>({});
const pesanAnalisis = ref<string | null>(null);

const tahap = ref<'diam' | 'berkas' | 'membaca' | 'halaman' | 'identitas' | 'analisis'>('diam');
const kemajuan = reactive({ selesai: 0, total: 0 });
let batal = false;

const baca = reactive({ teks: '', kegiatan: '', berkas: null as File | null });
/* Teks di kotak kiri yang diisi dari berkas, bukan diketik orang. Bila
   berkas lain dipilih, teks itu dibuang — kotak yang terisi selalu
   menang atas berkasnya, dan tanpa ini berkas baru diam-diam diabaikan. */
let teksDariBerkas = false;

/* ═══════════ permintaan JSON, dengan pengulangan ═══════════ */

function csrf(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

type Jawaban = { status: number; data: any };

async function kirimSekali(url: string, badan: FormData | object): Promise<Jawaban> {
  try {
    const r = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
        ...(badan instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
      },
      body: badan instanceof FormData ? badan : JSON.stringify(badan),
    });

    let data: any = null;
    try { data = await r.json(); } catch { /* bukan JSON: halaman galat server */ }

    return { status: r.status, data };
  } catch {
    return { status: 0, data: null };
  }
}

const SEMENTARA = [0, 408, 429, 500, 502, 503, 504, 520, 521, 522, 523, 524];
const tidur = (ms: number) => new Promise((ok) => setTimeout(ok, ms));

/**
 * Kirim, dan ulangi bila jaringannya putus atau servernya sibuk.
 *
 * Jawaban 200 ber-`ok: false` dari langkah analisis juga diulang sekali:
 * artinya mesinnya sedang sibuk walau server sudah mencoba dua kali.
 */
async function kirim(url: string, badan: FormData | object, opsi: { coba?: number; ulangBilaTidakOk?: boolean } = {}): Promise<Jawaban> {
  const coba = opsi.coba ?? 3;
  let r: Jawaban = { status: 0, data: null };

  for (let ke = 0; ke < coba; ke++) {
    if (ke > 0) await tidur(r.status === 429 ? 15000 : [0, 2500, 7000, 12000][ke] ?? 12000);
    if (batal && ke > 0) break;

    r = await kirimSekali(url, badan);

    const gagalSementara = SEMENTARA.includes(r.status)
      || (opsi.ulangBilaTidakOk && r.status === 200 && r.data && r.data.ok === false);
    if (!gagalSementara) break;
  }

  return r;
}

function pesanGagal(status: number, data: any): string {
  if (status === 419) return 'Sesi Anda berakhir. Muat ulang halaman ini, lalu coba lagi.';
  if (status === 413) return 'Berkasnya terlalu besar untuk diterima server. Tempelkan teksnya di kotak teks.';
  if (status === 429) return 'Terlalu banyak permintaan dalam satu menit. Tunggu sebentar, lalu ulangi.';
  if (status === 0) return 'Sambungan ke server terputus berkali-kali. Periksa jaringan, lalu ulangi.';
  if (status >= 500) return `Server sedang bermasalah (${status}). Ulangi sebentar lagi.`;
  return data?.pesan ?? data?.message ?? `Server memulangkan galat ${status}.`;
}

/* ═══════════ 1. baca berkas ═══════════ */

function pilihBerkas(e: Event) {
  baca.berkas = (e.target as HTMLInputElement).files?.[0] ?? null;
  if (teksDariBerkas) { baca.teks = ''; teksDariBerkas = false; }
}

const adalahPdf = (f: File | null) => !!f && (f.type === 'application/pdf' || /\.pdf$/i.test(f.name));

async function mulai() {
  batal = false;
  galatBaca.value = {};
  pesanAnalisis.value = null;

  if (!baca.teks.trim() && adalahPdf(baca.berkas)) {
    await bacaPdfDiPeramban(baca.berkas!);
    return;
  }

  await pecah(baca.teks.trim() ? { teks: baca.teks } : null);
}

/**
 * PDF dibaca di sini. Bila pdf.js gagal (berkas terkunci sandi, rusak,
 * atau peramban terlalu tua), jatuh ke jalan lama: unggah ke server.
 */
async function bacaPdfDiPeramban(berkas: File) {
  tahap.value = 'berkas';
  kemajuan.selesai = 0;
  kemajuan.total = 0;

  let dok: PDFDocumentProxy | null = null;
  let halaman: string[] = [];
  let gambar: number[] = [];

  try {
    const { bacaPdf } = await import('../../pdfPeraturan');
    const r = await bacaPdf(berkas, (a, b) => { kemajuan.selesai = a; kemajuan.total = b; });
    dok = r.dokumen; halaman = r.halaman; gambar = r.gambar;
  } catch {
    await pecah(null);          // jalan cadangan: dibaca server
    return;
  }

  const tambahan: string[] = [];

  if (gambar.length && props.otomatis) {
    const gagal = await bacaGambar(dok, gambar, halaman);
    tambahan.push(gagal.length
      ? `Halaman ${daftar(gagal)} berupa gambar dan belum terbaca. Tempelkan teksnya di kotak kiri bila perlu, lalu baca ulang.`
      : `Halaman ${daftar(gambar)} berupa gambar dan sudah dibaca otomatis — periksa teksnya di kotak kiri.`);
  } else if (gambar.length) {
    tambahan.push(`Halaman ${daftar(gambar)} berupa gambar, bukan teks — isinya tidak ikut terbaca. `
      + 'Aktifkan analisis otomatis di Pusat Kendali, atau tempelkan teks halaman itu di kotak kiri.');
  }

  const { tutupPdf } = await import('../../pdfPeraturan');
  await tutupPdf(dok);

  if (batal) { tahap.value = 'diam'; return; }

  const teks = halaman.join('\n\n').trim();
  if (!teks) {
    galatBaca.value = { umum: 'Tidak ada teks yang terbaca dari PDF ini. Tempelkan teksnya di kotak kiri.' };
    catatan.value = tambahan;
    tahap.value = 'diam';
    return;
  }

  baca.teks = teks;
  teksDariBerkas = true;
  await pecah({ teks }, tambahan);
}

/** "1, 3–5, 46". */
function daftar(h: number[]): string {
  const u = [...h].sort((a, b) => a - b);
  const out: string[] = [];
  for (let i = 0; i < u.length; i++) {
    const a = u[i];
    while (i + 1 < u.length && u[i + 1] === u[i] + 1) i++;
    out.push(a === u[i] ? String(a) : `${a}–${u[i]}`);
  }
  return out.join(', ');
}

/**
 * Halaman gambar dibaca satu halaman sekali minta, dua berjalan bersamaan
 * — kecuali server pernah menjawab "terlalu sering": sejak itu satu jalur.
 * Memulangkan nomor halaman yang gagal.
 */
async function bacaGambar(dok: PDFDocumentProxy, gambar: number[], halaman: string[]): Promise<number[]> {
  const { gambarHalaman } = await import('../../pdfPeraturan');
  tahap.value = 'halaman';
  kemajuan.selesai = 0;
  kemajuan.total = gambar.length;

  const antre = [...gambar];
  const gagal: number[] = [];
  let berhenti = false;
  let satuJalur = false;

  async function pekerja(ke: number) {
    while (antre.length && !batal && !berhenti) {
      if (ke > 0 && satuJalur) return;
      const no = antre.shift()!;

      let data: string;
      try {
        data = await gambarHalaman(dok, no);
      } catch {
        gagal.push(no); kemajuan.selesai++; continue;
      }

      const r = await kirim(props.tautan.gambar, { halaman: [{ no, data }] }, { ulangBilaTidakOk: true });

      if (r.status === 200 && r.data?.ok && r.data.halaman?.[no] !== undefined) {
        halaman[no - 1] = r.data.halaman[no];
      } else {
        gagal.push(no);
        pesanAnalisis.value = pesanGagal(r.status, r.data);
        if (r.status === 429) satuJalur = true;
        if (r.status === 409 || r.status === 419) berhenti = true;
      }
      kemajuan.selesai++;
    }
  }

  await Promise.all([pekerja(0), pekerja(1)]);

  gagal.push(...antre);          // yang belum sempat dibaca karena dihentikan
  return gagal;
}

/* ═══════════ 2. pecah ═══════════ */

/** Kirim teks (atau berkasnya, bila null) untuk dipecah server. */
async function pecah(teks: { teks: string } | null, catatanTambahan: string[] = []) {
  tahap.value = 'membaca';

  let badan: FormData | object;
  if (teks) {
    badan = teks;
  } else if (baca.berkas) {
    const fd = new FormData();
    fd.append('berkas', baca.berkas);
    badan = fd;
  } else {
    galatBaca.value = { teks: 'Unggah berkasnya atau tempelkan teks peraturannya.' };
    tahap.value = 'diam';
    return;
  }

  const r = await kirim(props.tautan.rangkum, badan);

  if (r.status === 422) {
    const e = r.data?.errors ?? {};
    galatBaca.value = Object.fromEntries(Object.entries(e).map(([k, v]) => [k, (v as string[])[0]]));
    tahap.value = 'diam';
    return;
  }
  if (r.status !== 200 || !r.data || !Array.isArray(r.data.butir)) {
    galatBaca.value = { umum: r.status === 200
      ? 'Server tidak memulangkan hasil bacaan. Muat ulang halaman ini, lalu coba lagi.'
      : pesanGagal(r.status, r.data) };
    tahap.value = 'diam';
    return;
  }

  const d = r.data as HasilRangkum;
  terapkan(d);
  catatan.value = [...catatan.value, ...catatanTambahan];

  /* Jalan cadangan (PDF dibaca server): halaman gambarnya dibaca lewat
     berkas sementara di server. */
  if (d.token && d.perHalaman && d.halamanGambar.length && props.otomatis) {
    await bacaHalamanServer(d);
    return;
  }

  if (hasil.value.length && props.otomatis) await analisis();
  tahap.value = 'diam';
}

function terapkan(d: HasilRangkum) {
  naskah.value = d.naskah;
  catatan.value = d.catatan;
  hasil.value = d.butir.map((b) => ({
    ...b, rangkuman: b.isi, penerapan: '', ikut: true, ikutDisentuh: false, kewajiban: null, analisis: 'belum',
  }));
  if (d.identitas) isiIdentitas(d.identitas);
}

/** Jalan cadangan: halaman gambar dibaca server dari PDF yang diunggah. */
async function bacaHalamanServer(d: HasilRangkum) {
  tahap.value = 'halaman';
  const halaman = [...(d.perHalaman ?? [])];
  kemajuan.selesai = 0;
  kemajuan.total = d.halamanGambar.length;
  const gagal: number[] = [];

  for (const no of d.halamanGambar) {
    if (batal) { gagal.push(no); continue; }
    const r = await kirim(props.tautan.baca, { token: d.token, dari: no, sampai: no }, { ulangBilaTidakOk: true });

    if (r.status === 200 && r.data?.ok && r.data.halaman?.[no] !== undefined) halaman[no - 1] = r.data.halaman[no];
    else {
      gagal.push(no);
      pesanAnalisis.value = pesanGagal(r.status, r.data);
      if (r.status === 409 || r.status === 404) break;
    }
    kemajuan.selesai++;
  }

  baca.teks = halaman.join('\n\n').trim();
  teksDariBerkas = true;

  const tambahan = gagal.length
    ? [`Halaman ${daftar(gagal)} berupa gambar dan belum terbaca. Tempelkan teksnya di kotak kiri bila perlu.`]
    : [`Halaman ${daftar(d.halamanGambar)} berupa gambar dan sudah dibaca otomatis — periksa teksnya di kotak kiri.`];

  if (batal || !baca.teks) { catatan.value = [...catatan.value, ...tambahan]; tahap.value = 'diam'; return; }

  await pecah({ teks: baca.teks }, tambahan);
}

/* ═══════════ 3. analisis ═══════════ */

const simpan = useForm({
  sumber: 'Peraturan', jenis: '', nomor: '', judul: '',
  tanggal_terbit: '', instansi: '', aspek: '',
  ruang_lingkup: '', rangkuman: '',
  company_id: '', tahun: String(props.opsi.tahun[1] ?? new Date().getFullYear()),
  dari_ai: false, butir: [] as Array<{ penunjuk: string; rangkuman: string; penerapan: string }>,
});

type KolomIdentitas = 'jenis' | 'nomor' | 'judul' | 'tanggal_terbit' | 'instansi' | 'aspek' | 'ruang_lingkup' | 'rangkuman';
const KOLOM: KolomIdentitas[] = ['jenis', 'nomor', 'judul', 'tanggal_terbit', 'instansi', 'aspek', 'ruang_lingkup', 'rangkuman'];

/* Nilai yang diisi mesin, per kolom. Kolom hanya ditimpa bila isinya
   masih sama dengan yang diisi mesin — sunting orang tidak pernah
   ditimpa analisis yang datang belakangan. */
const diisiMesin = reactive<Partial<Record<KolomIdentitas, string>>>({});

function isiIdentitas(i: Partial<IdentitasPeraturan>) {
  for (const k of KOLOM) {
    const baru = (i as Record<string, string | undefined>)[k];
    if (!baru) continue;
    const kini = simpan[k] as string;
    if (kini === '' || kini === diisiMesin[k]) {
      (simpan as unknown as Record<string, unknown>)[k] = baru;
      diisiMesin[k] = baru;
    }
  }
}

async function analisis(hanyaGagal = false) {
  batal = false;
  pesanAnalisis.value = null;

  if (!hanyaGagal && naskah.value) {
    tahap.value = 'identitas';
    const r = await kirim(props.tautan.identitas, { naskah: naskah.value }, { ulangBilaTidakOk: true, coba: 2 });
    if (r.status === 200 && r.data?.ok) isiIdentitas(r.data.identitas);
    else if (r.status === 409) { pesanAnalisis.value = pesanGagal(r.status, r.data); tahap.value = 'diam'; return; }
    /* Identitas yang gagal tidak menghentikan analisis butir: kolomnya
       tetap terisi dari bacaan naskah, dan dapat dilengkapi sendiri. */
  }

  const antre = hasil.value.filter((b) => (hanyaGagal ? b.analisis === 'gagal' : b.analisis !== 'ok'));
  if (!antre.length) { tahap.value = 'diam'; return; }

  tahap.value = 'analisis';
  kemajuan.selesai = 0;
  kemajuan.total = antre.length;

  const giliran: Butir[][] = [];
  for (let i = 0; i < antre.length; i += props.batas.perGiliran) giliran.push(antre.slice(i, i + props.batas.perGiliran));

  let berhenti = false;
  let satuJalur = false;

  /* Dua giliran berjalan bersamaan — kecuali server pernah menjawab
     "terlalu sering": sejak itu tinggal satu jalur, supaya batas laju
     mesinnya tidak terus-menerus terlampaui. */
  async function pekerja(ke: number) {
    while (giliran.length && !batal && !berhenti) {
      if (ke > 0 && satuJalur) return;
      const g = giliran.shift()!;
      g.forEach((b) => (b.analisis = 'proses'));

      const r = await kirim(props.tautan.analisis, {
        kegiatan: baca.kegiatan,
        butir: g.map((b) => ({ no: b.no, penunjuk: b.penunjuk, isi: b.isi })),
      }, { ulangBilaTidakOk: true });

      if (r.status === 429) satuJalur = true;

      const dijawab = new Map<number, any>();
      if (r.status === 200) for (const x of r.data?.butir ?? []) dijawab.set(x.no, x);

      for (const b of g) {
        const x = dijawab.get(b.no);
        if (!x) { b.analisis = 'gagal'; continue; }
        if (x.rangkuman && b.rangkuman === b.isi) b.rangkuman = x.rangkuman;
        if (x.penerapan && !b.penerapan) b.penerapan = x.penerapan;
        b.kewajiban = x.kewajiban;
        if (!b.ikutDisentuh && x.kewajiban === false) b.ikut = false;
        b.analisis = 'ok';
      }

      if (r.status !== 200 || !r.data?.ok || r.data?.hilang?.length) pesanAnalisis.value = pesanGagal(r.status, r.data);
      if (r.status === 409 || r.status === 419) berhenti = true;

      kemajuan.selesai += g.length;
    }
  }

  await Promise.all([pekerja(0), pekerja(1)]);

  hasil.value.filter((b) => b.analisis === 'proses').forEach((b) => (b.analisis = 'belum'));
  if (!hasil.value.some((b) => b.analisis === 'gagal')) pesanAnalisis.value = null;
  tahap.value = 'diam';
}

function hentikan() {
  batal = true;
}

/* ═══════════ tampilan & simpan ═══════════ */

const sibuk = computed(() => tahap.value !== 'diam');
const persen = computed(() => (kemajuan.total ? Math.round((kemajuan.selesai / kemajuan.total) * 100) : 0));
const labelTahap = computed(() => ({
  diam: '',
  berkas: kemajuan.total ? `Membaca PDF di perangkat ini — halaman ${kemajuan.selesai}/${kemajuan.total}` : 'Membuka PDF…',
  membaca: 'Memecah naskah menjadi pasal dan ayat…',
  halaman: `Membaca halaman gambar — ${kemajuan.selesai}/${kemajuan.total} halaman`,
  identitas: 'Menganalisis identitas peraturan…',
  analisis: `Menganalisis butir — ${kemajuan.selesai}/${kemajuan.total} butir`,
}[tahap.value]));

const hitung = computed(() => ({
  kewajiban: hasil.value.filter((b) => b.kewajiban === true).length,
  bukan: hasil.value.filter((b) => b.kewajiban === false).length,
  gagal: hasil.value.filter((b) => b.analisis === 'gagal').length,
  belum: hasil.value.filter((b) => b.analisis === 'belum').length,
  selesai: hasil.value.filter((b) => b.analisis === 'ok').length,
}));

const saring = ref<'semua' | 'kewajiban' | 'bukan' | 'gagal'>('semua');
const tampil = computed(() => hasil.value.filter((b) =>
  saring.value === 'semua' ? true
    : saring.value === 'kewajiban' ? b.kewajiban === true
      : saring.value === 'bukan' ? b.kewajiban === false
        : b.analisis === 'gagal'));

const terpilih = computed(() => hasil.value.filter((b) => b.ikut));

function centangSemua(nilai: boolean) {
  hasil.value.forEach((b) => { b.ikut = nilai; b.ikutDisentuh = true; });
}
function hanyaKewajiban() {
  hasil.value.forEach((b) => { b.ikut = b.kewajiban !== false; b.ikutDisentuh = true; });
}

function kirimSimpan() {
  simpan.butir = terpilih.value.map(({ penunjuk, rangkuman, penerapan }) => ({ penunjuk, rangkuman, penerapan }));
  simpan.dari_ai = hitung.value.selesai > 0;
  simpan.post(props.tautan.simpan, { preserveScroll: true });
}

function buang() {
  hentikan();
  hasil.value = [];
  catatan.value = [];
  naskah.value = '';
  pesanAnalisis.value = null;
  for (const k of KOLOM) {
    if ((simpan[k] as string) === diisiMesin[k]) (simpan as unknown as Record<string, unknown>)[k] = '';
    delete diisiMesin[k];
  }
}

/** Kolom yang isinya masih persis seperti diisi otomatis. */
const otomatisDi = (k: KolomIdentitas) => !!diisiMesin[k] && simpan[k] === diisiMesin[k];

const isian = 'ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition';
const label = 'block text-[10.5px] font-bold uppercase tracking-wide text-stone-500 mb-1';
</script>

<template>
  <Head title="Unggah &amp; Rangkum Peraturan" />

  <div class="space-y-5">
    <PindahKepatuhan :tautan="tautan" kini="unggah" />

    <div class="grid gap-5 xl:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] items-start">
      <!-- 1. Naskahnya -->
      <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 xl:sticky xl:top-4">
        <h3 class="text-[14px] font-extrabold text-cam-ink">1. Naskah Peraturan</h3>

        <p class="kpt-status mt-2" :class="{ mati: !otomatis }">
          <template v-if="otomatis">
            <b>Analisis otomatis aktif.</b> Rangkuman, penilaian kewajiban, usulan penerapan, identitas
            peraturan, dan halaman PDF hasil pindaian dikerjakan otomatis.
          </template>
          <template v-else>
            <b>Analisis otomatis belum diaktifkan.</b> Pemecahan pasalnya tetap berjalan penuh — yang tidak ada
            hanya rangkuman, penilaian kewajiban, dan usulan penerapan otomatis, yang tetap dapat diisi sendiri.
          </template>
        </p>

        <div class="mt-4 space-y-4">
          <div>
            <label :class="label" for="berkas">Unggah berkas</label>
            <input id="berkas" type="file" accept=".pdf,.docx,.txt,.md" :class="isian"
                   :disabled="sibuk" @change="pilihBerkas">
            <p class="text-[11px] text-stone-400 mt-1 leading-relaxed">
              Format .pdf, .docx, atau .txt. PDF dibaca langsung di perangkat ini. Halaman PDF hasil pindaian
              <template v-if="otomatis">dibaca otomatis.</template>
              <template v-else>tidak dapat dibaca; tempelkan teksnya di kotak di bawah.</template>
            </p>
            <p v-if="galatBaca.berkas" class="text-[11px] text-red-600 mt-1">{{ galatBaca.berkas }}</p>
          </div>

          <div>
            <label :class="label" for="teks">Atau tempel teks peraturan</label>
            <textarea id="teks" v-model="baca.teks" rows="8" :class="isian" :disabled="sibuk"
                      placeholder="Tempel isi peraturan di sini bila berkasnya tidak dapat dibaca otomatis"></textarea>
            <p class="text-[11px] text-stone-400 mt-1">
              Bila kotak ini diisi, isinya yang dipakai — berkas diabaikan. Teks yang terbaca dari berkas
              juga ditaruh di sini untuk diperiksa.
            </p>
            <p v-if="galatBaca.teks" class="text-[11px] text-red-600 mt-1">{{ galatBaca.teks }}</p>
          </div>

          <div>
            <label :class="label" for="kegiatan">Keterangan kegiatan perusahaan</label>
            <input id="kegiatan" v-model="baca.kegiatan" :class="isian" :disabled="sibuk"
                   placeholder="mis. tambang batubara terbuka, ada workshop, hauling road, dan kantin">
            <p class="text-[11px] text-stone-400 mt-1">
              Membantu menyusun usulan penerapan yang masuk akal. Boleh dikosongkan.
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="eq-btn-utama" style="flex:none;padding:9px 18px"
                    :disabled="sibuk" @click="mulai()">
              {{ sibuk ? 'Sedang bekerja…' : otomatis ? '✦ Baca & Analisis' : '✦ Baca & Pecah' }}
            </button>
            <button v-if="sibuk && (tahap === 'halaman' || tahap === 'identitas' || tahap === 'analisis')"
                    type="button" class="eq-btn-mini" @click="hentikan">Hentikan</button>
          </div>

          <div v-if="sibuk" class="kpt-maju" role="status" aria-live="polite">
            <p class="text-[11.5px] font-semibold text-stone-600">{{ labelTahap }}</p>
            <div v-if="kemajuan.total && (tahap === 'berkas' || tahap === 'halaman' || tahap === 'analisis')"
                 class="kpt-pita mt-1.5">
              <div :style="{ width: persen + '%' }"></div>
            </div>
            <p class="text-[10.5px] text-stone-400 mt-1">
              Hasil yang sudah selesai langsung muncul di kanan dan boleh mulai diperiksa.
            </p>
          </div>

          <p v-if="galatBaca.umum" class="kpt-galat">{{ galatBaca.umum }}</p>
        </div>
      </section>

      <!-- 2. Periksa & simpan -->
      <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
        <h3 class="text-[14px] font-extrabold text-cam-ink">2. Periksa &amp; Simpan</h3>

        <ul v-if="catatan.length" class="kpt-catatan mt-2">
          <li v-for="(c, i) in catatan" :key="i">{{ c }}</li>
        </ul>

        <p v-if="pesanAnalisis" class="kpt-galat mt-2">
          {{ pesanAnalisis }}
          <button v-if="hitung.gagal && !sibuk" type="button" class="eq-btn-mini ml-2" @click="analisis(true)">
            Ulangi {{ hitung.gagal }} butir yang gagal
          </button>
        </p>

        <p v-if="!hasil.length && !sibuk" class="eq-kosong eq-kosong-kecil">
          <strong>Belum ada hasil.</strong>
          <span class="block halus">Unggah berkasnya atau tempel naskahnya di kolom kiri, lalu tekan
            {{ otomatis ? 'Baca & Analisis' : 'Baca & Pecah' }}.</span>
        </p>

        <template v-if="hasil.length">
          <div class="grid gap-3 sm:grid-cols-[180px_1fr] mt-4">
            <div>
              <label :class="label" for="s-jenis">Jenis <small v-if="otomatisDi('jenis')" class="kpt-asal">otomatis</small></label>
              <select id="s-jenis" v-model="simpan.jenis" :class="isian">
                <option value="">— pilih —</option>
                <option v-for="j in opsi.jenis" :key="j" :value="j">{{ j }}</option>
              </select>
            </div>
            <div>
              <label :class="label" for="s-nomor">Nomor <span class="text-red-500">*</span>
                <small v-if="otomatisDi('nomor')" class="kpt-asal">otomatis</small></label>
              <input id="s-nomor" v-model="simpan.nomor" :class="isian"
                     placeholder="mis. Permen ESDM Nomor 26 Tahun 2018">
              <p v-if="simpan.errors.nomor" class="text-[11px] text-red-600 mt-1">{{ simpan.errors.nomor }}</p>
            </div>
          </div>

          <div class="mt-3">
            <label :class="label" for="s-judul">Judul <span class="text-red-500">*</span>
              <small v-if="otomatisDi('judul')" class="kpt-asal">otomatis</small></label>
            <textarea id="s-judul" v-model="simpan.judul" rows="2" :class="isian"></textarea>
            <p v-if="simpan.errors.judul" class="text-[11px] text-red-600 mt-1">{{ simpan.errors.judul }}</p>
          </div>

          <div class="grid gap-3 sm:grid-cols-3 mt-3">
            <div>
              <label :class="label" for="s-terbit">Tanggal terbit
                <small v-if="otomatisDi('tanggal_terbit')" class="kpt-asal">otomatis</small></label>
              <input id="s-terbit" v-model="simpan.tanggal_terbit" type="date" :class="isian">
            </div>
            <div>
              <label :class="label" for="s-instansi">Instansi
                <small v-if="otomatisDi('instansi')" class="kpt-asal">otomatis</small></label>
              <input id="s-instansi" v-model="simpan.instansi" :class="isian">
            </div>
            <div>
              <label :class="label" for="s-aspek">Aspek
                <small v-if="otomatisDi('aspek')" class="kpt-asal">otomatis</small></label>
              <select id="s-aspek" v-model="simpan.aspek" :class="isian">
                <option v-for="a in opsi.aspek" :key="a.nilai" :value="a.nilai">{{ a.nama }}</option>
              </select>
            </div>
          </div>

          <div class="grid gap-3 sm:grid-cols-2 mt-3">
            <div>
              <label :class="label" for="s-perusahaan">Perusahaan</label>
              <select id="s-perusahaan" v-model="simpan.company_id" :class="isian">
                <option value="">Berlaku umum</option>
                <option v-for="c in opsi.perusahaan" :key="c.id" :value="String(c.id)">{{ c.nama }}</option>
              </select>
            </div>
            <div>
              <label :class="label" for="s-tahun">Tahun evaluasi</label>
              <select id="s-tahun" v-model="simpan.tahun" :class="isian">
                <option v-for="t in opsi.tahun" :key="t" :value="String(t)">{{ t }}</option>
              </select>
            </div>
          </div>

          <div class="mt-3">
            <label :class="label" for="s-lingkup">Ruang lingkup
              <small v-if="otomatisDi('ruang_lingkup')" class="kpt-asal">otomatis</small></label>
            <textarea id="s-lingkup" v-model="simpan.ruang_lingkup" rows="2" :class="isian"></textarea>
          </div>

          <div class="mt-3">
            <label :class="label" for="s-rangkum">Rangkuman peraturan
              <small v-if="otomatisDi('rangkuman')" class="kpt-asal">otomatis</small></label>
            <textarea id="s-rangkum" v-model="simpan.rangkuman" rows="3" :class="isian"></textarea>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-2 mt-5 mb-2">
            <p class="text-[12px] font-bold text-cam-ink">
              Pasal / Ayat yang Ditemukan
              <span class="font-normal text-stone-400">— {{ hasil.length }} butir, {{ terpilih.length }} dicentang</span>
            </p>
            <div class="flex flex-wrap gap-1.5">
              <button v-if="hitung.selesai" type="button" class="eq-btn-mini" @click="hanyaKewajiban">Centang kewajiban saja</button>
              <button type="button" class="eq-btn-mini" @click="centangSemua(terpilih.length !== hasil.length)">
                {{ terpilih.length === hasil.length ? 'Lepas semua' : 'Centang semua' }}
              </button>
            </div>
          </div>

          <div v-if="hitung.selesai || hitung.gagal" class="kpt-saring" role="group" aria-label="Saring butir">
            <button v-for="s in ([['semua', `Semua ${hasil.length}`], ['kewajiban', `Kewajiban ${hitung.kewajiban}`],
                                   ['bukan', `Bukan kewajiban ${hitung.bukan}`], ['gagal', `Gagal dianalisis ${hitung.gagal}`]] as const)"
                    :key="s[0]" type="button" :class="{ kini: saring === s[0] }" @click="saring = s[0]">{{ s[1] }}</button>
          </div>

          <div class="kpt-usul">
            <table>
              <thead>
                <tr>
                  <th class="kpt-k-ikut">Ikut</th>
                  <th class="kpt-k-tunjuk">Pasal; Ayat</th>
                  <th>Rangkuman Isi</th>
                  <th>Saran Penerapan</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="b in tampil" :key="b.no" :class="{ 'is-lepas': !b.ikut }">
                  <td class="kpt-k-ikut">
                    <input v-model="b.ikut" type="checkbox" class="ring-focus" @change="b.ikutDisentuh = true"
                           :aria-label="`Ikut simpan ${b.penunjuk}`">
                  </td>
                  <td class="kpt-k-tunjuk">
                    <input v-model="b.penunjuk" class="kpt-sel" :aria-label="`Penunjuk butir ${b.no}`">
                    <span v-if="b.analisis === 'proses'" class="kpt-lencana proses">menganalisis…</span>
                    <span v-else-if="b.analisis === 'gagal'" class="kpt-lencana gagal">gagal</span>
                    <span v-else-if="b.kewajiban === true" class="kpt-lencana wajib">kewajiban</span>
                    <span v-else-if="b.kewajiban === false" class="kpt-lencana bukan">bukan kewajiban</span>
                  </td>
                  <td>
                    <textarea v-model="b.rangkuman" rows="3" class="kpt-sel"
                              :aria-label="`Rangkuman butir ${b.no}`"></textarea>
                    <details v-if="b.rangkuman !== b.isi" class="kpt-asli">
                      <summary>Naskah asli</summary>
                      <p>{{ b.isi }}</p>
                    </details>
                  </td>
                  <td>
                    <textarea v-model="b.penerapan" rows="3" class="kpt-sel"
                              :aria-label="`Saran penerapan butir ${b.no}`"></textarea>
                  </td>
                </tr>
                <tr v-if="!tampil.length">
                  <td colspan="4" class="text-center text-stone-400 py-4">Tidak ada butir pada saringan ini.</td>
                </tr>
              </tbody>
            </table>
          </div>

          <p class="text-[11px] text-stone-500 mt-2 leading-relaxed">
            Kolom <b>Saran Penerapan</b> berisi bentuk pemenuhan yang lazim, <b>bukan</b> pernyataan bahwa hal
            itu sudah dikerjakan. Butir yang dinilai <b>bukan kewajiban</b> perusahaan (definisi, tugas
            pemerintah, ketentuan penutup) dilepas centangnya — centang kembali bila memang perlu dinilai.
          </p>

          <div class="flex flex-wrap items-center gap-2 mt-4">
            <button type="button" class="eq-btn-utama" style="flex:none;padding:9px 18px"
                    :disabled="simpan.processing || !terpilih.length || sibuk" @click="kirimSimpan">
              {{ simpan.processing ? 'Menyimpan…' : `Simpan ${terpilih.length} Butir ke Register` }}
            </button>
            <button v-if="otomatis && hitung.belum && !sibuk" type="button" class="eq-btn-mini" @click="analisis()">
              Analisis {{ hitung.belum }} butir
            </button>
            <button type="button" class="eq-btn-mini" @click="buang">Buang hasil ini</button>
          </div>
          <p v-if="simpan.errors.butir" class="text-[11px] text-red-600 mt-1">{{ simpan.errors.butir }}</p>

          <p class="text-[11px] text-stone-500 mt-2 leading-relaxed">
            Tersimpan berstatus <b>Draf</b> dan seluruh butirnya <b>belum dinilai</b> — bukan N/A.
            Selama masih draf, isinya tidak ikut dihitung di dasbor. Periksa di halaman penilaiannya,
            lalu jadikan Tetap.
          </p>
        </template>
      </section>
    </div>
  </div>
</template>

<style scoped>
.kpt-status {
  border-radius: .75rem; padding: .55rem .75rem; font-size: 11.5px; line-height: 1.5;
  border: 1px solid #BBF7D0; background: #F0FDF4; color: #14532D;
}
.kpt-status.mati { border-color: #E7E5E4; background: #FAFAF9; color: #57534E; }

.kpt-maju { border: 1px solid #E7E5E4; border-radius: .75rem; padding: .6rem .75rem; }
.kpt-pita { height: 6px; border-radius: 999px; background: #F0EFEE; overflow: hidden; }
.kpt-pita > div { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #DC6E00, #FF9800); transition: width .3s; }

.kpt-galat {
  border-radius: .75rem; padding: .55rem .75rem; font-size: 11.5px; line-height: 1.5;
  border: 1px solid #FECACA; background: #FEF2F2; color: #991B1B;
}
.kpt-catatan {
  border-radius: .75rem; padding: .5rem .75rem .5rem 1.6rem; font-size: 11.5px; line-height: 1.55;
  border: 1px solid #FDE68A; background: #FFFBEB; color: #78350F; list-style: disc;
}

.kpt-asal {
  margin-left: .3rem; font-size: 8.5px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase;
  color: #1D4ED8; background: #DBEAFE; padding: .05rem .3rem; border-radius: .3rem;
}

.kpt-saring { display: flex; flex-wrap: wrap; gap: .35rem; margin-bottom: .5rem; }
.kpt-saring button {
  font-size: 10.5px; font-weight: 700; padding: .25rem .6rem; border-radius: 999px;
  border: 1px solid #E7E5E4; color: #57534E;
}
.kpt-saring button.kini { background: #0F1720; border-color: #0F1720; color: #fff; }

.kpt-usul { border: 1px solid #E7E5E4; border-radius: .9rem; overflow: auto; max-height: 34rem; position: relative; }
.kpt-usul table { width: 100%; border-collapse: collapse; font-size: 11.5px; }

.kpt-usul thead th {
  position: sticky; top: 0; z-index: 1;
  background: #FAFAF9; border-bottom: 1px solid #E7E5E4;
  padding: .45rem .5rem; text-align: left;
  font-size: 9.5px; font-weight: 800; letter-spacing: .04em;
  text-transform: uppercase; color: #78716C; white-space: normal;
}

.kpt-usul tbody td { border-bottom: 1px solid #F5F5F4; padding: .35rem .5rem; vertical-align: top; }
.kpt-usul tbody tr.is-lepas { opacity: .45; }

.kpt-k-ikut   { width: 7%; text-align: center; }
.kpt-k-tunjuk { width: 21%; }

.kpt-sel {
  width: 100%; border: 1px solid #E7E5E4; border-radius: .45rem; background: transparent;
  padding: .25rem .4rem; font-size: 11.5px; line-height: 1.35; resize: vertical;
}
.kpt-sel:focus { outline: 2px solid #F57C00; outline-offset: 1px; border-color: transparent; }

.kpt-lencana {
  display: inline-block; margin-top: .3rem; font-size: 9px; font-weight: 800; letter-spacing: .03em;
  padding: .1rem .4rem; border-radius: 999px; text-transform: uppercase;
}
.kpt-lencana.wajib  { background: #DCFCE7; color: #166534; }
.kpt-lencana.bukan  { background: #F5F5F4; color: #57534E; }
.kpt-lencana.gagal  { background: #FEE2E2; color: #991B1B; }
.kpt-lencana.proses { background: #FEF3C7; color: #92400E; }

.kpt-asli { margin-top: .25rem; font-size: 10.5px; color: #78716C; }
.kpt-asli summary { cursor: pointer; font-weight: 700; }
.kpt-asli p { margin-top: .2rem; line-height: 1.45; }

:global(:root[data-tema="gelap"] .kpt-status) { background: rgba(34,197,94,.12); border-color: rgba(34,197,94,.3); color: #BBF7D0; }
:global(:root[data-tema="gelap"] .kpt-status.mati) { background: #101A1E; border-color: #223238; color: #AEBCC2; }
:global(:root[data-tema="gelap"] .kpt-maju),
:global(:root[data-tema="gelap"] .kpt-usul),
:global(:root[data-tema="gelap"] .kpt-sel) { border-color: #223238; }
:global(:root[data-tema="gelap"] .kpt-pita) { background: #223238; }
:global(:root[data-tema="gelap"] .kpt-galat) { background: rgba(239,68,68,.12); border-color: rgba(239,68,68,.3); color: #FCA5A5; }
:global(:root[data-tema="gelap"] .kpt-catatan) { background: rgba(245,158,11,.12); border-color: rgba(245,158,11,.3); color: #FDE68A; }
:global(:root[data-tema="gelap"] .kpt-asal) { background: rgba(59,130,246,.2); color: #BFDBFE; }
:global(:root[data-tema="gelap"] .kpt-usul thead th) { background: #101A1E; border-color: #223238; color: #96A8AF; }
:global(:root[data-tema="gelap"] .kpt-usul tbody td) { border-color: #1B292E; }
:global(:root[data-tema="gelap"] .kpt-saring button) { border-color: #223238; color: #AEBCC2; }
:global(:root[data-tema="gelap"] .kpt-lencana.wajib) { background: rgba(34,197,94,.16); color: #86EFAC; }
:global(:root[data-tema="gelap"] .kpt-lencana.bukan) { background: #223238; color: #96A8AF; }
:global(:root[data-tema="gelap"] .kpt-lencana.gagal) { background: rgba(239,68,68,.16); color: #FCA5A5; }
:global(:root[data-tema="gelap"] .kpt-lencana.proses) { background: rgba(245,158,11,.16); color: #FCD34D; }
</style>

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
 *   1. Baca & pecah   — server membaca berkasnya dan memecah pasal/ayat
 *                        (tanpa AI; selalu berjalan).
 *   2. Halaman gambar — halaman PDF hasil pindaian dibaca AI, dua
 *                        halaman sekali minta, lalu naskahnya dipecah ulang.
 *   3. Analisis AI    — identitas peraturan, lalu butirnya selusin sekali
 *                        minta: rangkuman, kewajiban atau bukan, dan usulan
 *                        penerapan.
 *
 * Sebelumnya seluruhnya satu kiriman Inertia yang hasilnya dititipkan
 * lewat flash — dan flash itu tidak pernah sampai ke halaman. Tombolnya
 * berputar sebentar, lalu kolom hasil tetap kosong. Kini tiap langkah
 * memulangkan JSON langsung ke sini, dan kemajuannya terlihat.
 *
 * Tidak ada jalan pintas dari kiri langsung ke register. Hasilnya
 * tersimpan berstatus Draf, dan draf tidak dihitung di mana pun sampai
 * seseorang memeriksa dan menaikkannya.
 */
import { computed, reactive, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import type { HalamanKepatuhanUnggah, HasilRangkum, IdentitasPeraturan } from '../../types';
import PindahKepatuhan from './Pindah.vue';

const props = defineProps<HalamanKepatuhanUnggah>();

type KeadaanAi = 'belum' | 'proses' | 'ok' | 'gagal';

interface Butir {
  no: number; penunjuk: string; isi: string;
  rangkuman: string; penerapan: string;
  ikut: boolean; ikutDisentuh: boolean;
  kewajiban: boolean | null; ai: KeadaanAi;
}

const hasil = ref<Butir[]>([]);
const catatan = ref<string[]>([]);
const naskah = ref('');
const galatBaca = ref<Record<string, string>>({});
const pesanAi = ref<string | null>(null);

const tahap = ref<'diam' | 'membaca' | 'halaman' | 'identitas' | 'analisis'>('diam');
const kemajuan = reactive({ selesai: 0, total: 0 });
let batal = false;

const baca = reactive({ teks: '', kegiatan: '', berkas: null as File | null });

/* ═══════════ permintaan JSON ═══════════ */

function csrf(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

async function kirim(url: string, badan: FormData | object): Promise<{ status: number; data: any }> {
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
}

function pesanGagal(status: number, data: any): string {
  if (status === 419) return 'Sesi Anda berakhir. Muat ulang halaman ini, lalu coba lagi.';
  if (status === 413) return 'Berkasnya terlalu besar untuk diterima server (maksimal 20 MB).';
  if (status === 429) return 'Terlalu banyak permintaan dalam satu menit. Tunggu sebentar, lalu ulangi.';
  if (status === 0) return 'Tidak tersambung ke server. Periksa jaringan, lalu ulangi.';
  const p = data?.pesan ?? data?.message;
  const g = data?.galat ? ` (${data.galat})` : '';
  return (p || `Server memulangkan galat ${status}.`) + g;
}

/* ═══════════ 1. baca & pecah ═══════════ */

async function kirimBaca(dariTeks?: string, catatanTambahan: string[] = []) {
  batal = false;
  galatBaca.value = {};
  pesanAi.value = null;
  tahap.value = 'membaca';

  const fd = new FormData();
  const teks = dariTeks ?? baca.teks;
  if (teks.trim()) fd.append('teks', teks);
  else if (baca.berkas) fd.append('berkas', baca.berkas);
  else {
    galatBaca.value = { teks: 'Unggah berkasnya atau tempelkan teks peraturannya.' };
    tahap.value = 'diam';
    return;
  }

  let r: { status: number; data: any };
  try {
    r = await kirim(props.tautan.rangkum, fd);
  } catch {
    r = { status: 0, data: null };
  }

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

  if (d.token && d.perHalaman && d.halamanGambar.length && props.ai) {
    await bacaHalamanGambar(d);
    return;
  }

  if (hasil.value.length && props.ai) await analisis();
  tahap.value = 'diam';
}

function terapkan(d: HasilRangkum) {
  naskah.value = d.naskah;
  catatan.value = d.catatan;
  hasil.value = d.butir.map((b) => ({
    ...b, rangkuman: b.isi, penerapan: '', ikut: true, ikutDisentuh: false, kewajiban: null, ai: 'belum',
  }));
  if (d.identitas) isiIdentitas(d.identitas, 'otomatis');
}

/* ═══════════ 2. halaman gambar ═══════════ */

/** [[1],[45,46]] dari [1,45,46] — halaman berurutan dibaca bersama. */
function kelompokHalaman(h: number[]): Array<[number, number]> {
  const out: Array<[number, number]> = [];
  for (const n of [...h].sort((a, b) => a - b)) {
    const akhir = out[out.length - 1];
    if (akhir && n === akhir[1] + 1 && n - akhir[0] < props.batas.halamanPerBaca) akhir[1] = n;
    else out.push([n, n]);
  }
  return out;
}

async function bacaHalamanGambar(d: HasilRangkum) {
  tahap.value = 'halaman';
  const halaman = [...(d.perHalaman ?? [])];
  const kelompok = kelompokHalaman(d.halamanGambar);
  kemajuan.selesai = 0;
  kemajuan.total = d.halamanGambar.length;
  const gagal: number[] = [];

  for (const [dari, sampai] of kelompok) {
    if (batal) break;
    let r: { status: number; data: any };
    try {
      r = await kirim(props.tautan.baca, { token: d.token, dari, sampai });
    } catch {
      r = { status: 0, data: null };
    }

    if (r.status === 200 && r.data?.ok) {
      for (const [no, teks] of Object.entries(r.data.halaman as Record<string, string>)) {
        halaman[Number(no) - 1] = teks;
      }
    } else {
      for (let n = dari; n <= sampai; n++) gagal.push(n);
      pesanAi.value = pesanGagal(r.status, r.data);
      if (r.status === 409 || r.status === 404) break;
    }
    kemajuan.selesai += sampai - dari + 1;
  }

  /* Naskah lengkap ditaruh di kotak teks supaya dapat diperiksa dan
     disunting, lalu dipecah ulang dari sana. */
  baca.teks = halaman.join('\n\n').trim();

  const tambahan = gagal.length
    ? [`Halaman ${gagal.join(', ')} gagal dibaca AI${pesanAi.value ? ' — ' + pesanAi.value : ''}. Tempelkan teksnya di kotak kiri bila perlu.`]
    : [`Halaman ${d.halamanGambar.join(', ')} dibaca AI dan disisipkan pada tempatnya — periksa teksnya di kotak kiri.`];

  if (batal || !baca.teks) { catatan.value = [...catatan.value, ...tambahan]; tahap.value = 'diam'; return; }

  await kirimBaca(baca.teks, tambahan);
}

/* ═══════════ 3. analisis AI ═══════════ */

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
   ditimpa AI yang datang belakangan. */
const diisiMesin = reactive<Partial<Record<KolomIdentitas, string>>>({});
const sumberKolom = reactive<Partial<Record<KolomIdentitas, 'otomatis' | 'AI'>>>({});

function isiIdentitas(i: Partial<IdentitasPeraturan>, sumber: 'otomatis' | 'AI') {
  for (const k of KOLOM) {
    const baru = (i as Record<string, string | undefined>)[k];
    if (!baru) continue;
    const kini = simpan[k] as string;
    if (kini === '' || kini === diisiMesin[k]) {
      (simpan as unknown as Record<string, unknown>)[k] = baru;
      diisiMesin[k] = baru;
      sumberKolom[k] = sumber;
    }
  }
}

async function analisis(hanyaGagal = false) {
  batal = false;
  pesanAi.value = null;

  if (!hanyaGagal && naskah.value) {
    tahap.value = 'identitas';
    try {
      const r = await kirim(props.tautan.identitas, { naskah: naskah.value });
      if (r.status === 200 && r.data?.ok) isiIdentitas(r.data.identitas, 'AI');
      else pesanAi.value = 'Identitas peraturan: ' + pesanGagal(r.status, r.data);
      if (r.status === 409) { tahap.value = 'diam'; return; }
    } catch {
      pesanAi.value = 'Identitas peraturan: ' + pesanGagal(0, null);
    }
  }

  const antre = hasil.value.filter((b) => (hanyaGagal ? b.ai === 'gagal' : b.ai !== 'ok'));
  if (!antre.length) { tahap.value = 'diam'; return; }

  tahap.value = 'analisis';
  kemajuan.selesai = 0;
  kemajuan.total = antre.length;

  const giliran: Butir[][] = [];
  for (let i = 0; i < antre.length; i += props.batas.perGiliran) giliran.push(antre.slice(i, i + props.batas.perGiliran));

  let berhenti = false;

  /* Dua giliran berjalan bersamaan: separuh waktunya, tanpa membanjiri
     batas permintaan per menit. */
  async function pekerja() {
    while (giliran.length && !batal && !berhenti) {
      const g = giliran.shift()!;
      g.forEach((b) => (b.ai = 'proses'));

      let r: { status: number; data: any };
      try {
        r = await kirim(props.tautan.aiButir, {
          kegiatan: baca.kegiatan,
          butir: g.map((b) => ({ no: b.no, penunjuk: b.penunjuk, isi: b.isi })),
        });
        if (r.status === 429) {
          await new Promise((ok) => setTimeout(ok, 20000));
          r = await kirim(props.tautan.aiButir, {
            kegiatan: baca.kegiatan,
            butir: g.map((b) => ({ no: b.no, penunjuk: b.penunjuk, isi: b.isi })),
          });
        }
      } catch {
        r = { status: 0, data: null };
      }

      const dijawab = new Map<number, any>();
      if (r.status === 200) for (const x of r.data?.butir ?? []) dijawab.set(x.no, x);

      for (const b of g) {
        const x = dijawab.get(b.no);
        if (!x) { b.ai = 'gagal'; continue; }
        if (x.rangkuman && b.rangkuman === b.isi) b.rangkuman = x.rangkuman;
        if (x.penerapan && !b.penerapan) b.penerapan = x.penerapan;
        b.kewajiban = x.kewajiban;
        if (!b.ikutDisentuh && x.kewajiban === false) b.ikut = false;
        b.ai = 'ok';
      }

      if (r.status !== 200 || !r.data?.ok || r.data?.hilang?.length) {
        pesanAi.value = pesanGagal(r.status, r.data);
      }
      if (r.status === 409 || r.status === 419) berhenti = true;

      kemajuan.selesai += g.length;
    }
  }

  await Promise.all([pekerja(), pekerja()]);

  hasil.value.filter((b) => b.ai === 'proses').forEach((b) => (b.ai = 'belum'));
  tahap.value = 'diam';
}

function hentikan() {
  batal = true;
}

/* ═══════════ tampilan & simpan ═══════════ */

const sibuk = computed(() => tahap.value !== 'diam');
const persen = computed(() => (kemajuan.total ? Math.round((kemajuan.selesai / kemajuan.total) * 100) : 0));
const labelTahap = computed(() => ({
  diam: '', membaca: 'Membaca dan memecah naskah…',
  halaman: `Membaca halaman gambar dengan AI — ${kemajuan.selesai}/${kemajuan.total} halaman`,
  identitas: 'Menganalisis identitas peraturan dengan AI…',
  analisis: `Menganalisis butir dengan AI — ${kemajuan.selesai}/${kemajuan.total} butir`,
}[tahap.value]));

const hitung = computed(() => ({
  kewajiban: hasil.value.filter((b) => b.kewajiban === true).length,
  bukan: hasil.value.filter((b) => b.kewajiban === false).length,
  gagal: hasil.value.filter((b) => b.ai === 'gagal').length,
  belum: hasil.value.filter((b) => b.ai === 'belum').length,
  ai: hasil.value.filter((b) => b.ai === 'ok').length,
}));

const saring = ref<'semua' | 'kewajiban' | 'bukan' | 'gagal'>('semua');
const tampil = computed(() => hasil.value.filter((b) =>
  saring.value === 'semua' ? true
    : saring.value === 'kewajiban' ? b.kewajiban === true
      : saring.value === 'bukan' ? b.kewajiban === false
        : b.ai === 'gagal'));

const terpilih = computed(() => hasil.value.filter((b) => b.ikut));

function centangSemua(nilai: boolean) {
  hasil.value.forEach((b) => { b.ikut = nilai; b.ikutDisentuh = true; });
}
function hanyaKewajiban() {
  hasil.value.forEach((b) => { b.ikut = b.kewajiban !== false; b.ikutDisentuh = true; });
}

function kirimSimpan() {
  simpan.butir = terpilih.value.map(({ penunjuk, rangkuman, penerapan }) => ({ penunjuk, rangkuman, penerapan }));
  simpan.dari_ai = hitung.value.ai > 0;
  simpan.post(props.tautan.simpan, { preserveScroll: true });
}

function buang() {
  hentikan();
  hasil.value = [];
  catatan.value = [];
  naskah.value = '';
  pesanAi.value = null;
  for (const k of KOLOM) {
    if ((simpan[k] as string) === diisiMesin[k]) (simpan as unknown as Record<string, unknown>)[k] = '';
    delete diisiMesin[k];
    delete sumberKolom[k];
  }
}

function pilihBerkas(e: Event) {
  baca.berkas = (e.target as HTMLInputElement).files?.[0] ?? null;
}

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

        <p class="kpt-ai mt-2" :class="{ mati: !ai }">
          <template v-if="ai">
            <b>Analisis AI aktif</b> · {{ aiLabel }}. Rangkuman, penilaian kewajiban, usulan penerapan, identitas
            peraturan, dan halaman PDF hasil pindaian dikerjakan AI yang terpasang di Pusat Kendali.
          </template>
          <template v-else>
            <b>Kunci AI belum dipasang.</b> Pemecahan pasalnya tetap berjalan penuh — yang tidak ada hanya
            rangkuman, penilaian kewajiban, dan usulan penerapan otomatis, yang tetap dapat diisi sendiri.
          </template>
        </p>

        <div class="mt-4 space-y-4">
          <div>
            <label :class="label" for="berkas">Unggah berkas</label>
            <input id="berkas" type="file" accept=".pdf,.docx,.txt,.md" :class="isian"
                   :disabled="sibuk" @change="pilihBerkas">
            <p class="text-[11px] text-stone-400 mt-1 leading-relaxed">
              Format .pdf, .docx, atau .txt — maksimal 20 MB. Halaman PDF hasil pindaian
              <template v-if="ai">dibaca AI.</template>
              <template v-else>tidak dapat dibaca tanpa AI; tempelkan teksnya di kotak di bawah.</template>
            </p>
            <p v-if="galatBaca.berkas" class="text-[11px] text-red-600 mt-1">{{ galatBaca.berkas }}</p>
          </div>

          <div>
            <label :class="label" for="teks">Atau tempel teks peraturan</label>
            <textarea id="teks" v-model="baca.teks" rows="8" :class="isian" :disabled="sibuk"
                      placeholder="Tempel isi peraturan di sini bila berkasnya tidak dapat dibaca otomatis"></textarea>
            <p class="text-[11px] text-stone-400 mt-1">
              Bila kotak ini diisi, isinya yang dipakai — berkas diabaikan. Teks halaman gambar yang dibaca AI
              juga ditaruh di sini untuk diperiksa.
            </p>
            <p v-if="galatBaca.teks" class="text-[11px] text-red-600 mt-1">{{ galatBaca.teks }}</p>
          </div>

          <div>
            <label :class="label" for="kegiatan">Keterangan kegiatan perusahaan</label>
            <input id="kegiatan" v-model="baca.kegiatan" :class="isian" :disabled="sibuk"
                   placeholder="mis. tambang batubara terbuka, ada workshop, hauling road, dan kantin">
            <p class="text-[11px] text-stone-400 mt-1">
              Membantu AI menyusun usulan penerapan yang masuk akal. Boleh dikosongkan.
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="eq-btn-utama" style="flex:none;padding:9px 18px"
                    :disabled="sibuk" @click="kirimBaca()">
              {{ sibuk ? 'Sedang bekerja…' : ai ? '✦ Baca & Analisis' : '✦ Baca & Pecah' }}
            </button>
            <button v-if="sibuk && tahap !== 'membaca'" type="button" class="eq-btn-mini" @click="hentikan">Hentikan</button>
          </div>

          <div v-if="sibuk" class="kpt-maju" role="status" aria-live="polite">
            <p class="text-[11.5px] font-semibold text-stone-600">{{ labelTahap }}</p>
            <div v-if="kemajuan.total && (tahap === 'analisis' || tahap === 'halaman')" class="kpt-pita mt-1.5">
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

        <p v-if="pesanAi" class="kpt-galat mt-2">
          <b>AI:</b> {{ pesanAi }}
          <button v-if="hitung.gagal && !sibuk" type="button" class="eq-btn-mini ml-2" @click="analisis(true)">
            Ulangi {{ hitung.gagal }} butir yang gagal
          </button>
        </p>

        <p v-if="!hasil.length && !sibuk" class="eq-kosong eq-kosong-kecil">
          <strong>Belum ada hasil.</strong>
          <span class="block halus">Unggah berkasnya atau tempel naskahnya di kolom kiri, lalu tekan
            {{ ai ? 'Baca & Analisis' : 'Baca & Pecah' }}.</span>
        </p>

        <template v-if="hasil.length">
          <div class="grid gap-3 sm:grid-cols-[180px_1fr] mt-4">
            <div>
              <label :class="label" for="s-jenis">Jenis <small v-if="sumberKolom.jenis" class="kpt-asal">{{ sumberKolom.jenis }}</small></label>
              <select id="s-jenis" v-model="simpan.jenis" :class="isian">
                <option value="">— pilih —</option>
                <option v-for="j in opsi.jenis" :key="j" :value="j">{{ j }}</option>
              </select>
            </div>
            <div>
              <label :class="label" for="s-nomor">Nomor <span class="text-red-500">*</span>
                <small v-if="sumberKolom.nomor" class="kpt-asal">{{ sumberKolom.nomor }}</small></label>
              <input id="s-nomor" v-model="simpan.nomor" :class="isian"
                     placeholder="mis. Permen ESDM Nomor 26 Tahun 2018">
              <p v-if="simpan.errors.nomor" class="text-[11px] text-red-600 mt-1">{{ simpan.errors.nomor }}</p>
            </div>
          </div>

          <div class="mt-3">
            <label :class="label" for="s-judul">Judul <span class="text-red-500">*</span>
              <small v-if="sumberKolom.judul" class="kpt-asal">{{ sumberKolom.judul }}</small></label>
            <textarea id="s-judul" v-model="simpan.judul" rows="2" :class="isian"></textarea>
            <p v-if="simpan.errors.judul" class="text-[11px] text-red-600 mt-1">{{ simpan.errors.judul }}</p>
          </div>

          <div class="grid gap-3 sm:grid-cols-3 mt-3">
            <div>
              <label :class="label" for="s-terbit">Tanggal terbit
                <small v-if="sumberKolom.tanggal_terbit" class="kpt-asal">{{ sumberKolom.tanggal_terbit }}</small></label>
              <input id="s-terbit" v-model="simpan.tanggal_terbit" type="date" :class="isian">
            </div>
            <div>
              <label :class="label" for="s-instansi">Instansi
                <small v-if="sumberKolom.instansi" class="kpt-asal">{{ sumberKolom.instansi }}</small></label>
              <input id="s-instansi" v-model="simpan.instansi" :class="isian">
            </div>
            <div>
              <label :class="label" for="s-aspek">Aspek
                <small v-if="sumberKolom.aspek" class="kpt-asal">{{ sumberKolom.aspek }}</small></label>
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
              <small v-if="sumberKolom.ruang_lingkup" class="kpt-asal">{{ sumberKolom.ruang_lingkup }}</small></label>
            <textarea id="s-lingkup" v-model="simpan.ruang_lingkup" rows="2" :class="isian"></textarea>
          </div>

          <div class="mt-3">
            <label :class="label" for="s-rangkum">Rangkuman peraturan
              <small v-if="sumberKolom.rangkuman" class="kpt-asal">{{ sumberKolom.rangkuman }}</small></label>
            <textarea id="s-rangkum" v-model="simpan.rangkuman" rows="3" :class="isian"></textarea>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-2 mt-5 mb-2">
            <p class="text-[12px] font-bold text-cam-ink">
              Pasal / Ayat yang Ditemukan
              <span class="font-normal text-stone-400">— {{ hasil.length }} butir, {{ terpilih.length }} dicentang</span>
            </p>
            <div class="flex flex-wrap gap-1.5">
              <button v-if="hitung.ai" type="button" class="eq-btn-mini" @click="hanyaKewajiban">Centang kewajiban saja</button>
              <button type="button" class="eq-btn-mini" @click="centangSemua(terpilih.length !== hasil.length)">
                {{ terpilih.length === hasil.length ? 'Lepas semua' : 'Centang semua' }}
              </button>
            </div>
          </div>

          <div v-if="hitung.ai || hitung.gagal" class="kpt-saring" role="group" aria-label="Saring butir">
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
                    <span v-if="b.ai === 'proses'" class="kpt-lencana proses">menganalisis…</span>
                    <span v-else-if="b.ai === 'gagal'" class="kpt-lencana gagal">gagal</span>
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
            itu sudah dikerjakan. Butir yang dinilai AI <b>bukan kewajiban</b> perusahaan (definisi, tugas
            pemerintah, ketentuan penutup) dilepas centangnya — centang kembali bila memang perlu dinilai.
          </p>

          <div class="flex flex-wrap items-center gap-2 mt-4">
            <button type="button" class="eq-btn-utama" style="flex:none;padding:9px 18px"
                    :disabled="simpan.processing || !terpilih.length || sibuk" @click="kirimSimpan">
              {{ simpan.processing ? 'Menyimpan…' : `Simpan ${terpilih.length} Butir ke Register` }}
            </button>
            <button v-if="ai && hitung.belum && !sibuk" type="button" class="eq-btn-mini" @click="analisis()">
              Analisis {{ hitung.belum }} butir dengan AI
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
.kpt-ai {
  border-radius: .75rem; padding: .55rem .75rem; font-size: 11.5px; line-height: 1.5;
  border: 1px solid #BBF7D0; background: #F0FDF4; color: #14532D;
}
.kpt-ai.mati { border-color: #E7E5E4; background: #FAFAF9; color: #57534E; }

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

:global(:root[data-tema="gelap"] .kpt-ai) { background: rgba(34,197,94,.12); border-color: rgba(34,197,94,.3); color: #BBF7D0; }
:global(:root[data-tema="gelap"] .kpt-ai.mati) { background: #101A1E; border-color: #223238; color: #AEBCC2; }
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

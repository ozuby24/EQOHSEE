<script setup lang="ts">
/**
 * Form Penilaian Audit SMKP — 100 butir tujuh elemen dalam satu lembar.
 *
 * Yang dijawab halaman ini bukan "berapa nilai butir ini" melainkan
 * "butir mana yang belum sesuai". Pertanyaan kedua itu yang benar-benar
 * ditanyakan orang: sebelum rapat penutupan, sebelum menyusun temuan,
 * dan setiap kali manajemen bertanya sudah sampai mana.
 *
 * Sebelumnya jawabannya menuntut tujuh halaman dibuka satu per satu
 * lalu dibandingkan sendiri di kepala. Karena itu penyaringnya bekerja
 * di sisi peramban dan tanda kesesuaiannya dihitung ulang seketika saat
 * nilai diubah — memeriksa tidak boleh menunggu simpan.
 *
 * TIGA HAL YANG SENGAJA DIBEDAKAN, sebab ketiganya sering disamakan
 * dan akibatnya fatal bagi pembacaan audit:
 *
 *   belum dinilai — belum ada yang memeriksa. Abu-abu, bukan merah.
 *                   Audit yang baru dibuka harus tampak kosong, bukan
 *                   tampak gagal.
 *   nilai 0       — sudah diperiksa dan tidak memenuhi sama sekali.
 *                   Inilah yang merah.
 *   N/A           — di luar lingkup perusahaan. Keluar dari pembagi,
 *                   sehingga tidak menghukum capaian.
 *
 * Satuan temuan yang sah tetap SUB-ELEMEN, sebagaimana Formulir
 * Rekapitulasi Ketidaksesuaian. Tanda per butir di sini alat periksa —
 * ia menunjukkan butir mana yang menarik capaian sub-elemennya turun,
 * bukan mengangkat dirinya sendiri menjadi temuan.
 */
import { computed, reactive, ref, watch } from 'vue';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

type Butir = {
  kode: string; nama: string; maks: number;
  v: string; ket: string; bukti: string;
  keadaan: string; capaian: number | null;
};
type Sub = {
  kode: string; nama: string; ref: string | null; rinci: boolean;
  maks: number; nilai: number; berlaku: number; dinilai: number; capaian: number;
  kategori: { kode: string; label: string; warna: string } | null;
  butir: Butir[];
};
type Elemen = {
  kode: string; nama: string; bobot: number;
  maks: number; nilai: number; dinilai: number; berlaku: number; capaian: number;
  sub: Sub[];
};
type Keadaan = { kode: string; label: string; warna: string; min: number | null };
/** Satu anak tangga nilai. `ket`/`ada` hanya terisi setelah bunyinya diambil. */
type Anak = { nilai: number; persen: number; label: string; keadaan: string };
type Bunyi = { nilai: number; ket: string; ada: boolean };
type Rubrik = {
  skala: string[];
  sumber: string;
  lengkap: number;
  total: number;
  alamat: string;
};

/** Satu berkas bukti yang sudah terlampir pada sebuah butir. */
type Bukti = { id: number; nama: string; ukuran: number; catatan: string | null; url: string | null; unduh: string };

/** Nilai butir yang sama pada audit tahun sebelumnya. */
type Sanding = { lalu: number | string | null; lalu_maks: number | null; arah: string | null; selisih: number | null };

const props = defineProps<{
  audit: any;
  elemen: Elemen[];
  rekap: any;
  ringkas: Record<string, number>;
  keadaan: Keadaan[];
  rubrik: Rubrik;
  prasyarat: Array<{ kunci: string; judul: string; ket: string; selesai: boolean; tautan: string }>;

  /** Sandingan dengan audit tahun sebelumnya; `ada` false bila ini yang pertama. */
  banding: { ada: boolean; tahun: number | null; tahunKini: number; butir: Record<string, Sanding>; sub: Record<string, any>; elemen: Record<string, any>; akhir: any };
  konsistensi: { naik: any[]; turun: any[]; tetap_rendah: any[]; tetap_baik: any[] };

  bukti: Record<string, Bukti[]>;
  maksBuktiKb: number;

  peluang: Array<{ kode: string; lingkup: string; nama: string }>;
  ofiAda: string[];

  tautan: Record<string, string>;
}>();

const NA = 'N/A';

/* ---------- keadaan tiap butir, dihitung ulang saat nilai berubah ---------- */

/* Ambangnya datang dari server, bukan ditulis ulang di sini: satu angka,
   satu sumber. Daftarnya sudah urut menurun, jadi yang pertama memenuhi
   adalah kategori yang benar. */
const ambang = computed(() => props.keadaan.filter((k) => k.min !== null));

function keadaanDariPersen(capaian: number): string {
  for (const k of ambang.value) if (capaian >= (k.min as number)) return k.kode;

  return 'mayor';
}

function keadaanDari(nilai: string, maks: number): string {
  const v = String(nilai ?? '').trim();
  if (v === '') return 'belum';
  if (v.toUpperCase() === NA) return 'na';

  const angka = Number(v);
  if (Number.isNaN(angka)) return 'belum';

  return keadaanDariPersen(maks > 0 ? (angka / maks) * 100 : 0);
}

const rupa = computed(() => Object.fromEntries(props.keadaan.map((k) => [k.kode, k])));
const label = (kode?: string) => (kode ? rupa.value[kode]?.label ?? kode : '—');
const warna = (kode?: string) => (kode ? rupa.value[kode]?.warna : null) ?? '#94A3B8';

/* ---------- formulir ---------- */

const { dialog, tanya, batal, lanjut } = useDialog();

const form = useForm<{ k: Record<string, { v: string; ket: string; bukti: string }> }>({ k: {} });

for (const e of props.elemen) {
  for (const s of e.sub) {
    for (const b of s.butir) form.k[b.kode] = { v: b.v, ket: b.ket, bukti: b.bukti };
  }
}

const semuaButir = computed(() => props.elemen.flatMap((e) => e.sub.flatMap((s) => s.butir)));

const keadaanButir = computed<Record<string, string>>(() => {
  const out: Record<string, string> = {};
  for (const b of semuaButir.value) out[b.kode] = keadaanDari(form.k[b.kode]?.v ?? '', b.maks);
  return out;
});

/** Berapa butir pada tiap keadaan MENURUT ISI FORMULIR SEKARANG, termasuk yang belum disimpan. */
const jumlah = computed<Record<string, number>>(() => {
  const n: Record<string, number> = {};
  for (const k of props.keadaan) n[k.kode] = 0;
  for (const kode of Object.values(keadaanButir.value)) n[kode] = (n[kode] ?? 0) + 1;
  return n;
});

/**
 * Keadaan tiap SUB-ELEMEN, juga dihitung ulang dari isi formulir.
 *
 * Sub-elemen adalah satuan temuan yang sah — yang masuk Formulir
 * Rekapitulasi Ketidaksesuaian adalah sub-elemennya, bukan butirnya.
 * Karena itu ia harus ikut hidup saat nilai diubah: auditor yang
 * menaikkan satu butir ingin tahu apakah sub-elemennya sudah lepas dari
 * kategori mayor, dan menunggu simpan untuk mengetahuinya berarti
 * menyimpan berkali-kali hanya untuk melihat akibatnya.
 *
 * Butir N/A keluar dari pembagi, persis seperti di sisi server.
 */
const keadaanSub = computed(() => {
  const out: Record<string, { kode: string; capaian: number | null; nilai: number; maks: number }> = {};

  for (const e of props.elemen) {
    for (const s of e.sub) {
      let maks = 0, nilai = 0, berlaku = 0, dinilai = 0;

      for (const b of s.butir) {
        const v = String(form.k[b.kode]?.v ?? '').trim();
        if (v.toUpperCase() === NA) continue;

        berlaku++;
        maks += b.maks;
        if (v !== '') {
          dinilai++;
          nilai += Math.max(0, Math.min(Number(v) || 0, b.maks));
        }
      }

      const persen = maks > 0 ? (nilai / maks) * 100 : 0;

      out[s.kode] = {
        // Sebelum ada yang dinilai, capaiannya nol bukan karena gagal
        // melainkan karena pembilangnya masih kosong. Melabelinya
        // "Mayor" di titik itu mengarang temuan.
        kode: berlaku === 0 ? 'na' : dinilai === 0 ? 'belum' : keadaanDariPersen(persen),
        capaian: dinilai === 0 ? null : Math.round(persen * 10) / 10,
        nilai,
        maks,
      };
    }
  }

  return out;
});

/* Dirangkai di sini, bukan di template: dua penggal teks bersebelahan
   di dalam <span> menyisakan spasi tambahan yang tidak dapat dibuang
   dari CSS, dan lencananya tercetak "Kesesuaian  · 100%". */
function teksSub(kode: string): string {
  const k = keadaanSub.value[kode];
  if (!k) return '—';

  return k.capaian === null ? label(k.kode) : `${label(k.kode)} · ${angka(k.capaian)}%`;
}

/** Keadaan tersimpan sebuah sub-elemen, untuk dibandingkan dengan isi formulir. */
function keadaanTersimpan(s: Sub): string {
  return s.kategori?.kode ?? (s.berlaku === 0 ? 'na' : 'belum');
}

const belumDisimpan = (s: Sub) => keadaanSub.value[s.kode]?.kode !== keadaanTersimpan(s);

/* ---------- penyaring ---------- */

const saring = ref<string>('semua');
const elemenAktif = ref<string>('semua');
const cari = ref('');

/* Butir yang lolos penyaring. Sub-elemen dan elemen yang seluruh
   butirnya tersaring habis ikut hilang — daftar yang menyisakan judul
   kosong menyesatkan, seolah bagian itu memang tidak punya butir. */
const tampil = computed<Elemen[]>(() => {
  const kata = cari.value.trim().toLowerCase();

  const lolos = (b: Butir) => {
    if (saring.value === 'belum-sesuai') {
      if (!['mayor', 'minor'].includes(keadaanButir.value[b.kode])) return false;

    /* Dua penyaring yang membaca tahun lalu, bukan keadaan sekarang.
       "Turun" adalah pertanyaan pertama pada audit ulangan: butir mana
       yang tahun lalu lebih baik, dan mengapa. Ia tidak dapat dijawab
       penyaring keadaan — butir yang turun dari 4 ke 3 tetap berlencana
       Kesesuaian, dan karena itu tidak pernah muncul. */
    } else if (saring.value === 'turun') {
      if (sanding(b.kode)?.arah !== 'turun') return false;
    } else if (saring.value === 'berubah') {
      const a = sanding(b.kode)?.arah;
      if (a !== 'naik' && a !== 'turun') return false;
    } else if (saring.value !== 'semua' && keadaanButir.value[b.kode] !== saring.value) {
      return false;
    }
    if (kata && !(`${b.kode} ${b.nama}`.toLowerCase().includes(kata))) return false;
    return true;
  };

  return props.elemen
    .filter((e) => elemenAktif.value === 'semua' || e.kode === elemenAktif.value)
    .map((e) => ({
      ...e,
      sub: e.sub
        .map((s) => ({ ...s, butir: s.butir.filter(lolos) }))
        .filter((s) => s.butir.length > 0),
    }))
    .filter((e) => e.sub.length > 0);
});

const adaHasil = computed(() => tampil.value.length > 0);

/* ---------- rubrik: arti tiap angka pada tangga nilai ---------- */

/* Tangga tombolnya disusun di sini, bukan diminta ke server: ia hanya
   butuh nilai maksimum butir dan nama tingkat — keduanya sudah ada —
   sehingga tombol nilai selalu siap tanpa menunggu satu pun permintaan.
   Yang menunggu hanya BUNYI rubriknya. */
function tangga(b: Butir): Anak[] {
  const out: Anak[] = [];

  for (let n = b.maks; n >= 0; n--) {
    const persen = b.maks > 0 ? (n / b.maks) * 100 : 0;
    out.push({
      nilai: n,
      persen: Math.round(persen * 10) / 10,
      label: props.rubrik.skala[n] ?? '',
      keadaan: keadaanDariPersen(persen),
    });
  }

  return out;
}

/** Rubrik dibuka per butir, atau sekaligus lewat sakelar di bilah penyaring. */
const rubrikSemua = ref(false);
const rubrikButir = reactive<Record<string, boolean>>({});
const rubrikTampil = (b: Butir) => rubrikSemua.value || !!rubrikButir[b.kode];

/* Bunyi rubrik yang sudah terambil, disimpan per butir. Sekali diambil
   tidak diminta ulang: teksnya teks peraturan, ia tidak berubah selama
   halaman terbuka. */
const bunyi = reactive<Record<string, Bunyi[]>>({});
const sedangAmbil = ref(0);

async function ambilRubrik(kode: string[]) {
  const perlu = [...new Set(kode)].filter((k) => !bunyi[k]);
  if (!perlu.length) return;

  sedangAmbil.value++;
  try {
    // Dipotong per 60 kode: sakelar "semua butir" dapat meminta seratus
    // sekaligus, dan satu alamat sepanjang itu tidak dijamin dilayani.
    for (let i = 0; i < perlu.length; i += 60) {
      const potong = perlu.slice(i, i + 60);
      const r = await fetch(`${props.rubrik.alamat}?butir=${encodeURIComponent(potong.join(','))}`, {
        headers: { Accept: 'application/json' },
      });
      if (!r.ok) throw new Error(String(r.status));

      const isi = await r.json();
      for (const [k, v] of Object.entries(isi.tangga ?? {})) bunyi[k] = v as Bunyi[];

      // Butir yang tidak dijawab ditandai kosong supaya tidak diminta
      // berulang kali setiap kali panelnya digambar ulang.
      for (const k of potong) bunyi[k] ??= [];
    }
  } catch {
    for (const k of perlu) bunyi[k] ??= [];
  } finally {
    sedangAmbil.value--;
  }
}

const bunyiButir = (b: Butir, n: number) => bunyi[b.kode]?.find((x) => x.nilai === n);
const rubrikSiap = (b: Butir) => Array.isArray(bunyi[b.kode]);
const rubrikTakLengkap = (b: Butir) => (bunyi[b.kode] ?? []).some((x) => !x.ada);

function bukaRubrik(b: Butir) {
  rubrikButir[b.kode] = !rubrikTampil(b);
  if (rubrikButir[b.kode]) ambilRubrik([b.kode]);
}

/* Sakelar "semua butir" hanya mengambil yang sedang tampak. Menariknya
   untuk seratus butir sekaligus berarti 260 ribu aksara demi elemen yang
   bahkan tidak sedang dibuka. */
watch([rubrikSemua, tampil], () => {
  if (!rubrikSemua.value) return;
  ambilRubrik(tampil.value.flatMap((e) => e.sub.flatMap((s) => s.butir.map((b) => b.kode))));
});

function pilih(b: Butir, nilai: string) {
  // Menekan ulang nilai yang sudah terpilih mengembalikannya ke "belum
  // dinilai" — tanpa itu, angka yang terlanjur diketuk tidak dapat
  // dibatalkan tanpa mencari tombol tersendiri.
  form.k[b.kode].v = form.k[b.kode].v === nilai ? '' : nilai;
}

/* Sub-elemen apa adanya, sebelum disaring. Tombol "Tandai N/A" adalah
   pernyataan tentang SELURUH sub-elemen — bahwa ia di luar lingkup
   perusahaan — jadi ia harus mengenai seluruh butirnya, bukan hanya
   yang kebetulan sedang lolos penyaring di layar. */
const subAsli = computed<Record<string, Sub>>(() =>
  Object.fromEntries(props.elemen.flatMap((e) => e.sub.map((s) => [s.kode, s]))),
);

/** Seluruh butir satu sub-elemen ditandai di luar lingkup sekaligus. */
function tandaiNa(s: Sub) {
  for (const b of subAsli.value[s.kode]?.butir ?? []) form.k[b.kode].v = NA;
}

function kosongkan(s: Sub) {
  for (const b of subAsli.value[s.kode]?.butir ?? []) form.k[b.kode].v = '';
}

/* Keterangan dan bukti disembunyikan sampai diperlukan: ditampilkan
   sekaligus untuk 100 butir, lembar ini menjadi dinding ruas isian dan
   pertanyaan pokoknya — mana yang belum sesuai — tenggelam. */
const terbuka = reactive<Record<string, boolean>>({});
const adaCatatan = (b: Butir) => !!(form.k[b.kode]?.ket || form.k[b.kode]?.bukti);
const tampilCatatan = (b: Butir) => terbuka[b.kode] || adaCatatan(b);

function simpan() {
  form.post(`/smkp/${props.audit.id}/penilaian`, { preserveScroll: true });
}

/* ---------- sandingan dengan tahun sebelumnya ---------- */

/**
 * Nilai butir yang sama pada audit tahun lalu.
 *
 * Yang diperiksa auditor bukan hanya "berapa nilainya" melainkan
 * "apakah jawabannya konsisten": butir yang melompat dari 1 ke 4 tanpa
 * perubahan bukti adalah butir yang perlu ditanyakan ulang. Tanpa angka
 * pembandingnya di layar, tidak ada yang pernah menanyakannya — dan
 * membukanya di tab lain menuntut mencocokkan 349 baris dengan mata.
 */
const sanding = (kode: string): Sanding | null => props.banding?.butir?.[kode] ?? null;

const PANAH: Record<string, string> = { naik: '▲', turun: '▼', tetap: '=', baru: '•' };

/* Warna arah memakai palet keadaan yang sama dengan sisa halaman, bukan
   hijau/merah tersendiri: dua sistem warna pada satu baris membuat
   pembacanya menebak mana yang sedang berbicara. */
function warnaArah(arah: string | null | undefined): string {
  if (arah === 'naik')  return warna('kesesuaian');
  if (arah === 'turun') return warna('mayor');
  return '#94A3B8';
}

function teksSanding(b: Butir): string {
  const d = sanding(b.kode);
  if (!d || d.lalu === null || d.lalu === undefined) return '';

  const lalu = String(d.lalu).toUpperCase() === NA ? 'N/A' : `${d.lalu}/${d.lalu_maks ?? b.maks}`;

  return d.selisih === null || d.selisih === 0
    ? `${props.banding.tahun}: ${lalu}`
    : `${props.banding.tahun}: ${lalu} (${d.selisih > 0 ? '+' : ''}${d.selisih}%)`;
}

/* ---------- bukti berkas per butir ---------- */

const berkasButir = (kode: string): Bukti[] => props.bukti?.[kode] ?? [];

/** Butir yang sedang dibuka panel unggahnya. */
const unggahTerbuka = reactive<Record<string, boolean>>({});
const catatanBukti  = reactive<Record<string, string>>({});
const galatBukti    = reactive<Record<string, string>>({});
const sedangUnggah  = ref<string | null>(null);

const maksMb = computed(() => Math.round((props.maksBuktiKb / 1024) * 10) / 10);

/**
 * Unggah satu berkas bukti.
 *
 * Dikirim lewat router, BUKAN lewat <form> bersarang. Seluruh lembar
 * penilaian sudah berada di dalam satu <form>, dan form di dalam form
 * bukan HTML yang sah: peramban menutup yang luar pada tag pembuka yang
 * dalam, sehingga separuh isian penilaian berhenti terkirim sama sekali
 * — tanpa satu pun galat, dan hanya pada butir yang kebetulan berada di
 * bawah panel ini.
 */
function unggahBukti(kode: string, ev: Event) {
  const input = ev.target as HTMLInputElement;
  const berkas = input.files?.[0];
  if (!berkas) return;

  /* Diperiksa di sini SEKADAR supaya pesannya cepat; yang menegakkan
     batasnya tetap server. Berkas 40 MB yang ditolak sesudah terkirim
     seluruhnya adalah dua menit menunggu untuk sebuah penolakan. */
  if (berkas.size > props.maksBuktiKb * 1024) {
    galatBukti[kode] = `Berkas paling besar ${maksMb.value} MB. Yang dipilih ${(berkas.size / 1048576).toFixed(1)} MB.`;
    input.value = '';
    return;
  }

  delete galatBukti[kode];
  sedangUnggah.value = kode;

  router.post(`/smkp/${props.audit.id}/bukti`, {
    kode,
    catatan: catatanBukti[kode] ?? '',
    berkas,
  }, {
    forceFormData: true,
    preserveScroll: true,
    preserveState: false,
    onError: (e: any) => { galatBukti[kode] = e.berkas ?? e.kode ?? 'Berkas gagal diunggah.'; },
    onFinish: () => { sedangUnggah.value = null; input.value = ''; },
  });
}

/**
 * Membuang satu berkas bukti — bertanya lebih dulu.
 *
 * Penghapusan yang terjadi pada ketukan pertama tidak menimbulkan galat:
 * berkasnya hilang, halamannya menggambar ulang, dan tidak ada yang
 * tahu sampai seseorang mencari bukti yang sudah tidak ada — biasanya
 * auditor eksternal. Tombol × di sini berdempetan dengan tautan berkas
 * dalam satu lencana selebar dua sentimeter.
 */
async function hapusBukti(kode: string, b: Bukti) {
  if (!await tanya({
    judul: 'Hapus berkas bukti ini?',
    pesan: `${b.nama} akan dibuang dari butir ${kode} beserta berkasnya.`,
    labelAksi: 'Hapus', nada: 'bahaya',
  })) return;

  router.delete(`/smkp/${props.audit.id}/bukti/${b.id}`, { preserveScroll: true, preserveState: false });
}

const ukuran = (b: number) => (b >= 1048576 ? `${(b / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`);

/* ---------- peluang perbaikan ---------- */

/** Berapa butir yang naik dan turun dibanding tahun lalu. */
const arahJumlah = computed(() => {
  const n = { naik: 0, turun: 0 };

  for (const b of semuaButir.value) {
    const a = sanding(b.kode)?.arah;
    if (a === 'naik' || a === 'turun') n[a]++;
  }

  return n;
});

const peluangKode = computed(() => new Set(props.peluang.map((p) => p.kode)));
const ofiKode     = computed(() => new Set(props.ofiAda));

const angka = (v: unknown) =>
  typeof v === 'number' ? v.toLocaleString('id-ID', { maximumFractionDigits: 2 }) : (v ?? '—');

const belumSiap = computed(() => props.prasyarat.filter((p) => !p.selesai));

const kartu = computed(() => [
  {
    label: 'Nilai akhir tersimpan',
    nilai: `${angka(props.rekap?.skor ?? 0)}%`,

    /* Selisih terhadap tahun lalu dituliskan pada kartu pertama, bukan
       disembunyikan di panel yang harus dibuka. Angka 78% menjawab
       "berapa"; yang ditanyakan rapat tinjauan manajemen adalah
       "naik atau turun", dan itu pertanyaan yang berbeda. */
    ket: props.banding?.ada && props.banding.akhir?.selisih !== null
      ? `${props.rekap?.tingkat?.label ?? 'belum dinilai'} · ${props.banding.akhir.selisih > 0 ? '+' : ''}${angka(props.banding.akhir.selisih)} dari ${props.banding.tahun}`
      : (props.rekap?.tingkat?.label ?? 'belum dinilai'),
  },
  {
    label: 'Sudah dinilai',
    nilai: `${props.ringkas.total - props.ringkas.belum}`,
    ket: `dari ${props.ringkas.total} butir kriteria`,
  },
  {
    label: 'Sudah sesuai',
    nilai: `${props.ringkas.kesesuaian}`,
    ket: `${props.ringkas.na} butir di luar lingkup`,
  },
  {
    label: 'Belum sesuai',
    nilai: `${props.ringkas.mayor + props.ringkas.minor}`,
    ket: `${props.ringkas.mayor} mayor · ${props.ringkas.minor} minor`,
  },
]);
</script>

<template>
  <Head title="Form Penilaian Audit SMKP" />

  <div class="max-w-[1400px] mx-auto space-y-5">

    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-cam-ink">Form Penilaian Audit</h2>
        <p class="text-[12.5px] text-stone-500 mt-1">
          {{ props.audit?.judul || `Audit ${props.audit?.tahun}` }}
          <span v-if="props.audit?.company?.name"> · {{ props.audit.company.name }}</span>
          · {{ props.ringkas.total }} butir kriteria pada tujuh elemen SMKP.
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <Link :href="props.tautan.audit" class="eq-btn-lain">Ringkasan audit</Link>
        <a :href="props.tautan.kriteria" class="eq-btn-lain">Cetak Formulir Kriteria</a>
        <a :href="props.tautan.ekspor" class="eq-btn-lain">Unduh CSV</a>
      </div>
    </section>

    <!-- Urutan yang mendahului penilaian. Ditampilkan, bukan mengunci:
         auditor lazim membaca kriteria lebih dulu untuk menyiapkan
         sampel, dan menutup halaman ini justru menghalangi pekerjaan
         yang sah. Yang perlu terlihat urutannya. -->
    <section v-if="belumSiap.length"
             class="rounded-2xl bg-amber-50 border border-amber-200 p-4">
      <p class="text-[12.5px] font-bold text-amber-800">
        Penilaian lapangan seharusnya dimulai setelah dua berkas ini selesai
      </p>
      <p class="text-[12px] text-amber-700 mt-1 leading-relaxed">
        Keduanya yang menetapkan lingkup, kriteria, dan sampel audit. Menilai sebelum itu
        disepakati berarti menilai butir yang belum tentu berlaku. Formulir tetap dapat diisi —
        urutan ini pengingat, bukan penghalang.
      </p>
      <div class="flex flex-wrap gap-2 mt-3">
        <Link v-for="p in belumSiap" :key="p.kunci" :href="p.tautan" class="eq-btn-mini">
          {{ p.judul }} — {{ p.ket || 'belum selesai' }}
        </Link>
      </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <article v-for="item in kartu" :key="item.label"
               class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
        <p class="text-[10px] uppercase tracking-wider font-bold text-stone-400">{{ item.label }}</p>
        <strong class="block text-xl mt-1">{{ item.nilai }}</strong>
        <p class="text-[11px] text-stone-500 mt-1 leading-snug">{{ item.ket }}</p>
      </article>
    </section>

    <!-- Penyaring. Inilah alasan halaman ini ada: satu klik untuk melihat
         hanya yang belum sesuai, tanpa memuat ulang dan tanpa kehilangan
         isian yang belum disimpan. -->
    <!-- ══════════ KONSISTENSI DENGAN AUDIT SEBELUMNYA ══════════
         Tiga golongan, dan yang ketiga yang paling mudah hilang:
         sub-elemen yang bertahan di bawah ambang mayor dua tahun
         berturut-turut. Selisihnya nol, jadi setiap tampilan yang
         mengurutkan menurut perubahan menaruhnya di tengah dan tidak
         seorang pun melihatnya — padahal ia yang paling lama rusak. -->
    <section v-if="props.banding?.ada" class="grid gap-3 lg:grid-cols-3">
      <article v-for="g in [
                 { kunci: 'turun',        judul: 'Turun dari ' + props.banding.tahun, warna: warna('mayor'),
                   ket: 'Sub-elemen yang capaiannya lebih rendah daripada audit sebelumnya.' },
                 { kunci: 'tetap_rendah', judul: 'Rendah dua tahun berturut', warna: warna('minor'),
                   ket: 'Di bawah ambang mayor pada kedua audit — tidak memburuk, tetapi tidak pernah diperbaiki.' },
                 { kunci: 'naik',         judul: 'Naik dari ' + props.banding.tahun, warna: warna('kesesuaian'),
                   ket: 'Sub-elemen yang capaiannya membaik.' },
               ]" :key="g.kunci"
               class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <header class="px-4 py-3 border-b border-stone-100">
          <h3 class="text-[12.5px] font-bold flex items-center gap-2">
            <i class="titik" :style="{ background: g.warna }"></i>
            {{ g.judul }}
            <span class="font-normal text-stone-400">
              {{ (props.konsistensi as any)[g.kunci]?.length ?? 0 }}
            </span>
          </h3>
          <p class="text-[10.5px] text-stone-500 mt-0.5 leading-snug">{{ g.ket }}</p>
        </header>

        <ul v-if="(props.konsistensi as any)[g.kunci]?.length" class="divide-y divide-stone-100 max-h-52 overflow-y-auto">
          <li v-for="b in (props.konsistensi as any)[g.kunci].slice(0, 12)" :key="b.kode"
              class="px-4 py-2 flex items-baseline gap-2 text-[11.5px]">
            <b class="shrink-0">{{ b.kode }}</b>
            <span class="min-w-0 flex-1 truncate text-stone-600" :title="b.nama">{{ b.nama }}</span>
            <span class="shrink-0 num text-stone-400">{{ angka(b.lalu) }}%</span>
            <span class="shrink-0 text-stone-300">→</span>
            <span class="shrink-0 num font-bold" :style="{ color: g.warna }">{{ angka(b.kini) }}%</span>
          </li>
        </ul>

        <p v-else class="px-4 py-6 text-center text-[11.5px] text-stone-400">Tidak ada.</p>
      </article>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 space-y-3">
      <div class="flex flex-wrap items-center gap-2">
        <span class="text-[11px] uppercase tracking-wider font-bold text-stone-400 mr-1">Tampilkan</span>

        <button type="button" class="eq-saring" :class="{ aktif: saring === 'semua' }"
                @click="saring = 'semua'">
          Semua <b>{{ props.ringkas.total }}</b>
        </button>

        <button type="button" class="eq-saring" :class="{ aktif: saring === 'belum-sesuai' }"
                @click="saring = 'belum-sesuai'">
          <i class="titik" :style="{ background: warna('mayor') }"></i>
          Belum sesuai <b>{{ (jumlah.mayor ?? 0) + (jumlah.minor ?? 0) }}</b>
        </button>

        <button v-for="k in props.keadaan" :key="k.kode" type="button"
                class="eq-saring" :class="{ aktif: saring === k.kode }"
                @click="saring = k.kode">
          <i class="titik" :style="{ background: k.warna }"></i>
          {{ k.label }} <b>{{ jumlah[k.kode] ?? 0 }}</b>
        </button>

        <!-- Dua penyaring yang membaca tahun lalu. "Turun" adalah
             pertanyaan pertama pada audit ulangan, dan ia tidak dapat
             dijawab penyaring keadaan: butir yang turun dari 4 ke 3
             tetap berlencana Kesesuaian, jadi ia tidak pernah muncul di
             daftar mana pun. -->
        <template v-if="props.banding?.ada">
          <button type="button" class="eq-saring" :class="{ aktif: saring === 'turun' }"
                  @click="saring = 'turun'"
                  :title="`Butir yang nilainya lebih rendah daripada audit ${props.banding.tahun}`">
            <i class="titik" :style="{ background: warna('mayor') }"></i>
            Turun dari {{ props.banding.tahun }} <b>{{ arahJumlah.turun }}</b>
          </button>

          <button type="button" class="eq-saring" :class="{ aktif: saring === 'berubah' }"
                  @click="saring = 'berubah'">
            Berubah <b>{{ arahJumlah.naik + arahJumlah.turun }}</b>
          </button>
        </template>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <span class="text-[11px] uppercase tracking-wider font-bold text-stone-400 mr-1">Elemen</span>
        <button type="button" class="eq-saring" :class="{ aktif: elemenAktif === 'semua' }"
                @click="elemenAktif = 'semua'">Semua</button>
        <button v-for="e in props.elemen" :key="e.kode" type="button"
                class="eq-saring" :class="{ aktif: elemenAktif === e.kode }"
                @click="elemenAktif = e.kode">
          {{ e.kode }} · {{ e.nama }}
        </button>

        <input v-model="cari" type="search" placeholder="Cari kode atau uraian butir…"
               class="ml-auto w-full sm:w-72 rounded-xl border-stone-200 text-[12.5px]">
      </div>

      <div class="flex flex-wrap items-center gap-3 pt-1 border-t border-stone-100">
        <label class="flex items-center gap-2 text-[12px] font-semibold cursor-pointer mt-2">
          <input v-model="rubrikSemua" type="checkbox" class="accent-[#F57C00]">
          Tampilkan acuan pengisian pada semua butir
        </label>
        <p class="text-[11px] text-stone-500 mt-2">
          Bunyi rubrik lengkap untuk {{ props.rubrik.lengkap }} dari {{ props.rubrik.total }} butir
          <span v-if="sedangAmbil"> · mengambil…</span>
        </p>
      </div>
    </section>

    <form @submit.prevent="simpan" class="space-y-4">

      <article v-for="e in tampil" :key="e.kode"
               class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">

        <header class="px-5 py-4 border-b border-stone-100 flex flex-wrap items-baseline gap-x-4 gap-y-1">
          <h3 class="font-bold text-[15px]">{{ e.kode }} · {{ e.nama }}</h3>
          <p class="text-[11.5px] text-stone-500">
            Bobot {{ e.bobot }}% · {{ angka(e.nilai) }} dari {{ e.maks }} poin ·
            {{ e.dinilai }} dari {{ e.berlaku }} butir berlaku sudah dinilai
          </p>
          <span class="ml-auto text-[13px] font-bold">{{ angka(e.capaian) }}%</span>
        </header>

        <section v-for="s in e.sub" :key="s.kode" class="border-b border-stone-100 last:border-b-0">

          <!-- Kepala sub-elemen HANYA bagi yang punya rincian. Sub-elemen
               tanpa rincian dinilai langsung sebagai butirnya sendiri:
               menggambarnya sebagai judul lalu sekali lagi sebagai baris
               berarti mencetak kode dan nama yang sama dua kali
               berturut-turut, dan lembar 100 butir ini menjadi dua kali
               lebih panjang tanpa satu pun keterangan tambahan. -->
          <div v-if="s.rinci" class="px-5 py-3 bg-stone-100 flex flex-wrap items-center gap-x-3 gap-y-2">
            <div class="min-w-0 flex-1">
              <h4 class="font-bold text-[12.5px]">{{ s.kode }} · {{ s.nama }}</h4>
              <p class="text-[11px] text-stone-500 mt-0.5">
                {{ s.butir.length }} butir ·
                {{ angka(keadaanSub[s.kode]?.nilai) }} dari {{ keadaanSub[s.kode]?.maks }} poin berlaku
                <span v-if="s.ref"> · acuan {{ s.ref }}</span>
              </p>
            </div>

            <!-- Keadaan sub-elemen — satuan temuan yang sah pada Formulir
                 Rekapitulasi Ketidaksesuaian. Dihitung dari isi formulir,
                 bukan dari yang tersimpan, supaya akibat sebuah nilai
                 terlihat sebelum disimpan. -->
            <span class="eq-keadaan"
                  :style="{ color: warna(keadaanSub[s.kode]?.kode), borderColor: warna(keadaanSub[s.kode]?.kode) }">
              {{ teksSub(s.kode) }}
            </span>
            <span v-if="belumDisimpan(s)" class="text-[10px] font-bold uppercase tracking-wide text-amber-700">
              belum disimpan
            </span>

            <div class="ml-auto flex gap-2">
              <button type="button" class="eq-btn-mini" @click="tandaiNa(s)">Tandai N/A</button>
              <button type="button" class="eq-btn-mini" @click="kosongkan(s)">Kosongkan</button>
            </div>
          </div>

          <!-- Baris butir. Rincian sebuah sub-elemen menjorok supaya
               terbaca sebagai miliknya: pada elemen IV satu sub-elemen
               memuat sepuluh rincian, dan tanpa jorokan batas antar
               sub-elemen hilang sama sekali. -->
          <div v-for="b in s.butir" :key="b.kode"
               class="py-3 border-t border-stone-100 first:border-t-0"
               :class="s.rinci ? 'pl-12 pr-5' : 'px-5'">

            <div class="flex flex-wrap items-start gap-x-3 gap-y-2">
              <div class="min-w-0 flex-1">
                <p class="text-[12.5px] leading-snug"><b>{{ b.kode }}</b> · {{ b.nama }}</p>
                <p class="text-[11px] text-stone-500 mt-0.5">
                  Nilai maksimum {{ b.maks }}
                  <span v-if="!s.rinci && s.ref"> · acuan {{ s.ref }}</span>
                </p>
              </div>

              <span class="eq-keadaan"
                    :style="{ color: warna(keadaanButir[b.kode]), borderColor: warna(keadaanButir[b.kode]) }">
                {{ label(keadaanButir[b.kode]) }}
              </span>

              <!-- Nilai butir yang sama tahun lalu. Ditaruh di sebelah
                   lencana keadaan, bukan di panel yang harus dibuka:
                   yang diperiksa adalah KONSISTENSINYA, dan pemeriksaan
                   yang menuntut satu klik per butir tidak pernah
                   dikerjakan untuk 349 butir. -->
              <span v-if="teksSanding(b)" class="eq-keadaan"
                    :style="{ color: warnaArah(sanding(b.kode)?.arah), borderColor: warnaArah(sanding(b.kode)?.arah) }"
                    :title="`Nilai pada audit ${props.banding.tahun}`">
                {{ PANAH[sanding(b.kode)?.arah ?? ''] ?? '' }} {{ teksSanding(b) }}
              </span>

              <span v-if="!s.rinci && belumDisimpan(s)"
                    class="text-[10px] font-bold uppercase tracking-wide text-amber-700">
                belum disimpan
              </span>
            </div>

            <!-- TANGGA NILAI, bukan daftar tarik-turun.
                 Angka telanjang menuntut auditor mengingat sendiri apa arti
                 3 pada butir bermaksimum 4 — dan dua auditor yang mengingat
                 berbeda menghasilkan tingkat penerapan berbeda untuk
                 perusahaan yang sama. Di sini tiap angka membawa namanya,
                 dan yang terpilih membawa warna kategori yang ia hasilkan:
                 memilih nilai berarti sekaligus melihat apa yang sedang
                 dinyatakan. -->
            <div class="flex flex-wrap items-center gap-1.5 mt-2">
              <button v-for="a in tangga(b)" :key="a.nilai" type="button"
                      class="eq-nilai" :class="{ terpilih: form.k[b.kode].v === String(a.nilai) }"
                      :style="form.k[b.kode].v === String(a.nilai)
                        ? { borderColor: warna(a.keadaan), color: warna(a.keadaan) } : undefined"
                      :title="`${a.label} · ${a.persen}% · ${label(a.keadaan)}`"
                      @click="pilih(b, String(a.nilai))">
                <b>{{ a.nilai }}</b>
                <span>{{ a.label }}</span>
              </button>

              <button type="button" class="eq-nilai" :class="{ terpilih: form.k[b.kode].v === NA }"
                      :style="form.k[b.kode].v === NA
                        ? { borderColor: warna('na'), color: warna('na') } : undefined"
                      title="Butir tidak berlaku bagi perusahaan ini — keluar dari pembagi"
                      @click="pilih(b, NA)">
                <b>N/A</b>
                <span>Tidak berlaku</span>
              </button>

              <button type="button" class="eq-btn-mini ml-auto" @click="bukaRubrik(b)">
                {{ rubrikTampil(b) ? 'Tutup acuan' : 'Acuan pengisian' }}
              </button>
            </div>

            <!-- Rubrik: bunyi tiap anak tangga beserta kategori yang
                 dihasilkannya. Inilah yang membuat penilaian dapat diulang
                 oleh auditor lain dan dipertanggungjawabkan ke inspektur. -->
            <div v-if="rubrikTampil(b)" class="mt-2 rounded-xl border border-stone-200 overflow-hidden">
              <p class="px-3 py-2 bg-stone-50 text-[10.5px] text-stone-600">
                Acuan pengisian nilai <b>0–{{ b.maks }}</b>
                <span v-if="s.ref"> · kriteria {{ s.ref }}</span>
                <span v-if="props.rubrik.sumber"> · {{ props.rubrik.sumber }}</span>
              </p>

              <p v-if="!rubrikSiap(b)" class="px-3 py-3 text-[11px] text-stone-500">
                Mengambil bunyi rubrik…
              </p>

              <template v-else>
                <!-- Enam butir bernilai maksimum 4 hanya berbunyi sampai 2
                     atau 3 pada lampiran. Selisihnya tidak ditambal
                     karangan; yang diberi nilai di sana perlu tahu bahwa ia
                     melakukannya tanpa acuan tertulis. -->
                <p v-if="rubrikTakLengkap(b)"
                   class="px-3 py-2 bg-amber-50 text-[10.5px] text-amber-800 leading-relaxed">
                  Lampiran tidak mencantumkan bunyi rubrik untuk seluruh tingkat butir ini.
                  Nilai maksimumnya tetap {{ b.maks }} sesuai tabel kriteria — tingkat yang
                  kosong di bawah memang tidak berbunyi pada acuan, bukan terlewat disalin.
                </p>

                <div v-for="a in tangga(b)" :key="a.nilai"
                     class="flex gap-3 px-3 py-2 border-t border-stone-100 text-[11px]">
                  <b class="w-6 shrink-0 text-center text-[13px]" :style="{ color: warna(a.keadaan) }">
                    {{ a.nilai }}
                  </b>
                  <div class="min-w-0">
                    <p class="font-semibold">
                      {{ a.label }}
                      <span class="text-stone-500">· {{ angka(a.persen) }}% · {{ label(a.keadaan) }}</span>
                    </p>
                    <p v-if="bunyiButir(b, a.nilai)?.ada"
                       class="text-stone-600 mt-0.5 leading-relaxed whitespace-pre-line">{{ bunyiButir(b, a.nilai)?.ket }}</p>
                    <p v-else class="text-amber-700 mt-0.5">
                      Tidak berbunyi pada lampiran.
                    </p>
                  </div>
                </div>
              </template>
            </div>

            <div v-if="tampilCatatan(b)" class="grid gap-2 sm:grid-cols-2 mt-2">
              <input v-model="form.k[b.kode].ket" placeholder="Keterangan auditor"
                     class="rounded-lg border-stone-200 text-[12px]">
              <input v-model="form.k[b.kode].bukti" placeholder="Bukti objektif yang diperiksa"
                     class="rounded-lg border-stone-200 text-[12px]">
            </div>
            <button v-else type="button" class="eq-btn-mini mt-2" @click="terbuka[b.kode] = true">
              Tambah keterangan &amp; bukti
            </button>

            <!-- ══════════ BERKAS BUKTI ══════════
                 Berdampingan dengan kolom teks di atas, bukan
                 menggantikannya: yang satu menjawab "bukti apa" — nomor
                 dokumen, siapa yang diwawancarai — yang ini menjawab
                 "mana buktinya". Berkas audit yang diminta Inspektur
                 Tambang menuntut keduanya. -->
            <div v-if="berkasButir(b.kode).length" class="mt-2 flex flex-wrap gap-1.5">
              <span v-for="f in berkasButir(b.kode)" :key="f.id"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-stone-200 bg-stone-50 pl-2 pr-1 py-1 text-[11px]">
                <a :href="f.url ?? f.unduh" target="_blank" rel="noopener"
                   class="font-semibold text-cam-lime-deep hover:underline max-w-[220px] truncate"
                   :title="f.catatan || f.nama">{{ f.nama }}</a>
                <span class="text-stone-400">{{ ukuran(f.ukuran) }}</span>
                <button type="button" class="text-stone-400 hover:text-red-600 px-1"
                        :title="`Hapus ${f.nama}`" @click="hapusBukti(b.kode, f)">×</button>
              </span>
            </div>

            <div v-if="unggahTerbuka[b.kode]" class="mt-2 rounded-xl border border-stone-200 p-3 grid gap-2">
              <p class="text-[10.5px] text-stone-500">
                Satu berkas paling besar <b>{{ maksMb }} MB</b>. PDF, dokumen kantor, atau gambar.
                Tersimpan tertutup — hanya terbuka bagi yang dapat membuka audit ini.
              </p>

              <input v-model="catatanBukti[b.kode]" maxlength="300"
                     placeholder="Keterangan berkas — nomor dokumen, tanggal terbit"
                     class="rounded-lg border-stone-200 text-[12px]">

              <input type="file" class="text-[11.5px]"
                     :disabled="sedangUnggah === b.kode"
                     @change="unggahBukti(b.kode, $event)">

              <p v-if="sedangUnggah === b.kode" class="text-[11px] text-stone-500">Mengunggah…</p>
              <p v-if="galatBukti[b.kode]" class="text-[11px] text-red-600">{{ galatBukti[b.kode] }}</p>
            </div>

            <div class="mt-2 flex flex-wrap items-center gap-2">
              <button type="button" class="eq-btn-mini"
                      @click="unggahTerbuka[b.kode] = !unggahTerbuka[b.kode]">
                {{ unggahTerbuka[b.kode] ? 'Tutup unggahan' : 'Lampirkan berkas bukti' }}
              </button>

              <!-- Butir yang capaiannya penuh berhak memperoleh peluang
                   perbaikan. Ditawarkan DI SINI, tempat auditor baru saja
                   memberi nilainya — bukan hanya di halaman OFI, yang
                   perlu dibuka sendiri dan karena itu jarang dibuka. -->
              <Link v-if="peluangKode.has(b.kode)" :href="props.tautan.ofi"
                    class="eq-btn-mini"
                    :title="ofiKode.has(b.kode) ? 'Peluang perbaikan sudah dicatat' : 'Butir ini sudah sempurna — catat peluang perbaikannya'">
                {{ ofiKode.has(b.kode) ? '✓ OFI tercatat' : '+ Peluang perbaikan' }}
              </Link>
            </div>
          </div>
        </section>
      </article>

      <p v-if="!adaHasil" class="rounded-2xl bg-white border border-stone-100 shadow-card p-10 text-center text-[13px] text-stone-500">
        Tidak ada butir yang cocok dengan penyaring ini.
      </p>

      <!-- Bilah simpan menempel di bawah: dengan 100 butir dalam satu
           lembar, tombol yang hanya ada di ujung halaman berarti isian
           tengah tersimpan hanya bila penggulirannya sampai bawah. -->
      <div class="sticky bottom-0 rounded-2xl bg-white border border-stone-100 shadow-card p-4
                  flex flex-wrap items-center gap-3">
        <p class="text-[12px] text-stone-500">
          Menampilkan {{ tampil.reduce((n, e) => n + e.sub.reduce((m, s) => m + s.butir.length, 0), 0) }}
          dari {{ props.ringkas.total }} butir.
          Menyimpan tetap mengirim seluruh butir, termasuk yang sedang tersaring.
        </p>
        <button type="submit" class="eq-btn-setuju ml-auto" :disabled="form.processing">
          {{ form.processing ? 'Menyimpan…' : 'Simpan penilaian' }}
        </button>
      </div>
    </form>
  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>

<script setup lang="ts">
/**
 * Dasbor menyeluruh — keadaan seluruh situs dalam satu layar.
 *
 * TERPISAH DARI DASBOR PEMBELAJARAN. Halaman /lms menjawab pertanyaan
 * seorang peserta tentang kursusnya sendiri; halaman ini menjawab
 * pertanyaan seorang pengawas tentang situsnya.
 *
 * SUSUNANNYA MENGIKUTI URUTAN MENDESAKNYA: apa yang harus dikerjakan
 * hari ini, lalu bagaimana keadaan keseluruhannya, lalu rinciannya per
 * modul. Dasbor yang disusun menurut nama modul memaksa pembacanya
 * menyaring sendiri tiap pagi — pekerjaan yang diulang tiap orang,
 * dengan hasil yang berbeda tiap kali.
 *
 * ── SETIAP ANGKA MEMBAWA PENYEBUTNYA ──
 *
 * "12 berkas habis" berarti lain pada 20 berkas dan pada 2.000. Angka
 * utama ditulis bersama porsinya ("14,3% dari 84 berkas"), dan porsi
 * tanpa penyebut ditulis "—", bukan 0% atau 100%. Jumlah di panel
 * situasi selalu sama dengan jumlah tunggakan di tabel modul: gawat,
 * serius, dan perlu diurus — tidak ada nada yang jatuh di antaranya.
 *
 * ── WARNA ──
 *
 * Warna KEADAAN (merah, jingga, kuning, hijau) hanya untuk keadaan, dan
 * nadanya sama dengan lencana di modul asalnya. Pembeda antarbutir
 * memakai palet kategori tervalidasi buta warna (Grafik/warna.ts) —
 * tanpa hijau dan merahnya, supaya butir ketiga tidak terbaca "aman"
 * dan butir kedelapan tidak terbaca "gawat".
 *
 * ── SATU SATUAN SATU SUMBU ──
 *
 * Dua besaran berbeda satuan (ton dan bcm, mm dan m³, liter dan jam)
 * digambar sebagai dua grafik kecil bersusun dengan sumbu waktu yang
 * sejajar, bukan ditumpuk di satu sumbu — di sana deret yang kecil
 * menjadi garis datar di dasar grafik dan tidak terbaca sama sekali.
 */
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import KartuGrafik from '../../Grafik/KartuGrafik.vue';
import Batang from '../../Grafik/Batang.vue';
import Donat from '../../Grafik/Donat.vue';
import Garis from '../../Grafik/Garis.vue';
import Cincin from '../../Grafik/Cincin.vue';
import TabelAngka from '../../Grafik/TabelAngka.vue';
import Legenda from './Legenda.vue';
import IkonPadat from '../../Components/IkonPadat.vue';
import { AKSEN, KATEGORI, KEADAAN, warnaDeret } from '../../Grafik/warna';

const props = propHalaman();

/* ═════════ pembantu angka ═════════ */

const fmt = new Intl.NumberFormat('id-ID');
const angka = (n: number | null | undefined) => (n === null || n === undefined ? '—' : fmt.format(n));

/** Porsi berpenyebut; tanpa penyebut bukan 0% — tidak ada porsinya. */
function porsi(atas: number | null | undefined, bawah: number | null | undefined): number | null {
  if (!bawah || atas === null || atas === undefined) return null;
  return (atas / bawah) * 100;
}
const fmtPersen = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 });
const persen = (p: number | null) => (p === null ? '—' : `${fmtPersen.format(p)}%`);

/** Jumlah seluruh rentang; hari tak terukur tidak menambah apa pun. */
const jumlah = (deret: (number | null)[] | undefined) => (deret ?? []).reduce((n: number, v) => n + (v ?? 0), 0);

/* ═════════ warna keadaan ═════════ */

/*
 * `warna` untuk tanda (titik, batang, isian); `teks` untuk tulisan
 * kecil. Warna keadaan yang cerah tidak mencapai 4,5:1 pada putih —
 * kuning 3,2:1, hijau 3,3:1 — jadi tulisannya memakai turunan gelapnya,
 * dan turunan terangnya pada mode gelap (variabel --das-* di bawah).
 */
type Nada = 'gawat' | 'serius' | 'ingat' | 'bersih';
const STATUS: Record<Nada, { label: string; warna: string; teks: string }> = {
  gawat:  { label: 'Gawat',        warna: KEADAAN.gawat,  teks: 'var(--das-gawat)' },
  serius: { label: 'Serius',       warna: KEADAAN.serius, teks: 'var(--das-serius)' },
  ingat:  { label: 'Perlu diurus', warna: KEADAAN.ingat,  teks: 'var(--das-ingat)' },
  bersih: { label: 'Bersih',       warna: KEADAAN.baik,   teks: 'var(--das-baik)' },
};

/* Pita masa berlaku — sama persis dengan halaman pemantauan Miners.
   Dua skala warna untuk satu makna membuat orang membaca kuning di sini
   sebagai sesuatu yang lain di sana. Tanpa tanggal: abu-abu netral —
   bukan baik, bukan buruk, tidak dapat dinilai sama sekali. */
const WARNA_PITA: Record<string, string> = {
  habis: '#DC2626', mendesak: '#EA580C', dekat: '#CA8A04', panjang: '#16A34A',
  'tak-bertanggal': KEADAAN.netral,
};

/* Label pita beserta batas harinya. "Panjang" saja tidak memberi tahu
   pembacanya seberapa panjang; batasnya sama dengan Authority. */
const LABEL_PITA: Record<string, string> = {
  habis: 'Habis',
  mendesak: 'Mendesak · ≤ 30 hari',
  dekat: 'Dekat · 31–60 hari',
  panjang: 'Aman · > 60 hari',
  'tak-bertanggal': 'Tanpa tanggal',
};
const URUT_PITA = ['habis', 'mendesak', 'dekat', 'panjang', 'tak-bertanggal'];

/* Palet kategori untuk butir yang berdampingan, TANPA hijau (#008300)
   dan merah (#e34948)-nya: di panel yang seluruhnya gawat, butir hijau
   terbaca "sudah aman". Ekornya dilebur menjadi "Lainnya" berwarna
   netral, tidak diberi warna ketujuh. */
const PALET_BUTIR = [KATEGORI[0], KATEGORI[1], KATEGORI[2], KATEGORI[3], KATEGORI[4], KATEGORI[6]];

/* ═════════ 1 · situasi hari ini ═════════ */

const butirBernada = (nada: string) => computed<any[]>(() => (props.modul ?? [])
  .filter((m: any) => m.nada === nada && m.nilai > 0)
  .sort((a: any, b: any) => b.nilai - a.nilai));

/* Tiga kelompok, terpisah karena menuntut tindakan yang berbeda:
   gawat — sudah lewat batas, hari ini; serius — menunggu diperiksa
   atau dinilai seseorang; perlu diurus — dijadwalkan minggu ini. */
const mendesak    = butirBernada('gawat');
const serius      = butirBernada('serius');
const perluDiurus = butirBernada('ingat');

const total = (d: any[]) => d.reduce((n, m) => n + m.nilai, 0);
const jumlahMendesak = computed(() => total(mendesak.value));
const jumlahSerius   = computed(() => total(serius.value));
const jumlahDiurus   = computed(() => total(perluDiurus.value));

const modulBersih = computed(() => (props.ringkasanModul ?? []).filter((m: any) => !m.perlu).length);
const jumlahModul = computed(() => (props.ringkasanModul ?? []).length);

/* Pita komposisi: porsi tiap butir terhadap seluruh yang gawat. */
const komposisi = computed(() => {
  const isi = mendesak.value;
  const depan = isi.slice(0, PALET_BUTIR.length)
    .map((m, i) => ({ nama: m.nama, nilai: m.nilai, warna: PALET_BUTIR[i], url: m.url as string | null, ket: m.ket as string }));
  const sisa = isi.slice(PALET_BUTIR.length);
  if (sisa.length) {
    depan.push({ nama: `Lainnya (${sisa.length} butir)`, nilai: total(sisa), warna: KEADAAN.netral, url: null, ket: '' });
  }
  return depan;
});

/* ═════════ 2 · angka pembuka ═════════ */

/* Penyebut porsi: SELURUH berkas, termasuk yang tanpa tanggal. */
const totalBerkas = computed<number>(() => Number(props.ringkas?.berkas
  ?? (props.kepatuhanBerkas ?? []).reduce((n: number, b: any) => n + b.nilai, 0)));

const kpi = computed(() => {
  const r = props.ringkas ?? {};
  return [
    {
      label: 'Tenaga kerja', nilai: r.manpower, warna: null as string | null,
      porsi: porsi(r.manpowerAktif, r.manpower), pita: AKSEN as string | null,
      ket: `${angka(r.manpowerAktif)} aktif · ${persen(porsi(r.manpowerAktif, r.manpower))}`,
    },
    {
      label: 'Berkas sudah habis', nilai: r.berkasHabis, warna: KEADAAN.gawat,
      porsi: porsi(r.berkasHabis, totalBerkas.value), pita: KEADAAN.gawat,
      ket: `${persen(porsi(r.berkasHabis, totalBerkas.value))} dari ${angka(totalBerkas.value)} berkas · tidak boleh masuk`,
    },
    {
      label: 'Mendekati habis', nilai: r.berkasDekat, warna: KEADAAN.ingat,
      porsi: porsi(r.berkasDekat, totalBerkas.value), pita: KEADAAN.ingat,
      ket: `${persen(porsi(r.berkasDekat, totalBerkas.value))} dari ${angka(totalBerkas.value)} berkas · ≤ 60 hari`,
    },
    {
      label: 'Perusahaan', nilai: r.perusahaan, warna: null,
      porsi: null, pita: null,
      ket: 'memegang berkas kelayakan',
    },
  ];
});

/* ═════════ 3 · tab dan rentang ═════════ */

/* Dua puluh grafik pada satu gulungan menuntut orang menggulir mencari
   yang dicarinya, tiap kali. Dikelompokkan menurut pertanyaan yang
   dijawabnya, bukan menurut modul asalnya. */
const TAB = [
  { kode: 'ringkas',     nama: 'Ringkasan' },
  { kode: 'keselamatan', nama: 'Keselamatan' },
  { kode: 'produksi',    nama: 'Produksi & Energi' },
  { kode: 'lingkungan',  nama: 'Air & Lingkungan' },
  { kode: 'aset',        nama: 'Aset & Biaya' },
  { kode: 'orang',       nama: 'Kelayakan Orang' },
];

const tab = ref('ringkas');

/* Rentang berpindah lewat URL, bukan di peramban: hasilnya dapat
   ditautkan, dan yang menempelkannya ke laporan menempelkan rentang
   yang sama pula. Langsung ke alamat aslinya — /dasbor hanya
   pengalihan, dan satu lompatan tambahan terasa di jaringan lapangan. */
function pilihHari(h: number) {
  router.get('/dashboard', { hari: h }, { preserveScroll: true, preserveState: true });
}

const g = computed<any>(() => props.grafik ?? {});

/* ── tabel di balik grafik ── */

/** Baris tabel deret waktu: tanggal, lalu nilai tiap deret. */
const barisDeret = (label: string[] | undefined, deret: ((number | null)[] | undefined)[]) =>
  (label ?? []).map((l, i) => [l, ...deret.map((d) => d?.[i] ?? null)]);

/** Baris tabel sebaran: nama, jumlah, dan porsinya. */
const barisBagian = (bagian: any[] | undefined) => {
  const b = bagian ?? [];
  const semua = b.reduce((n: number, x: any) => n + (x.nilai ?? 0), 0);
  return b.map((x: any) => [x.label, x.nilai, persen(porsi(x.nilai, semua))]);
};

/** Warna kunci tiap baris sebaran — sama dengan irisannya. */
const kunciBagian = (bagian: any[] | undefined, satuWarna = false) =>
  (bagian ?? []).map((x: any, i: number) =>
    x.warna ?? (x.keadaan ? KEADAAN[x.keadaan] : (satuWarna ? AKSEN : warnaDeret(i))));

/** Ada isinya? Grafik kosong lebih baik diganti kalimat. */
const berisi = (baris: any[] | undefined) =>
  Array.isArray(baris) && baris.some((b) => (b?.nilai ?? 0) > 0);

/* ── ringkasan: kepatuhan berkas ── */

const pita = computed(() => (props.kepatuhanBerkas ?? [])
  .filter((b: any) => b.nilai > 0)
  .map((b: any) => ({ label: LABEL_PITA[b.kode] ?? b.label, nilai: b.nilai, warna: WARNA_PITA[b.kode] })));

/*
 * Berkas per perusahaan, BERTUMPUK menurut keadaan yang sama dengan
 * donat di sebelahnya, dengan warna yang sama. Satuannya berkas —
 * jumlah orang ditulis di sebelahnya, tidak dicampur ke batangnya.
 * Panjang batang sebanding dengan jumlah berkas perusahaan itu.
 */
type Potong = { kode: string; nilai: number; warna: string; label: string };
type BarisPerusahaan = {
  label: string; orang: number; berkas: number; potong: Potong[]; per: Record<string, number>;
  habis: number; mendekati: number; lebar: number;
};

const perusahaanBerkas = computed<BarisPerusahaan[]>(() => {
  const baris: Omit<BarisPerusahaan, 'lebar'>[] = (props.perPerusahaan ?? []).map((c: any) => {
    const per: Record<string, number> = {};
    for (const k of URUT_PITA) per[k] = Number(c.perKeadaan?.[k] ?? 0);
    const potong: Potong[] = URUT_PITA
      .map((k) => ({ kode: k, nilai: per[k], warna: WARNA_PITA[k], label: LABEL_PITA[k] }))
      .filter((p) => p.nilai > 0);
    return {
      label: c.label, orang: Number(c.nilai ?? 0), berkas: Number(c.berkas ?? 0), potong, per,
      habis: Number(c.habis ?? 0), mendekati: Number(c.mendekati ?? 0),
    };
  });
  const puncak = Math.max(1, ...baris.map((b) => b.berkas));
  return baris.map((b) => ({ ...b, lebar: (b.berkas / puncak) * 100 }));
});

/* Legenda grafik bertumpuk: hanya keadaan yang benar-benar muncul. */
const legendaPita = computed(() => URUT_PITA
  .filter((k) => perusahaanBerkas.value.some((c) => c.per[k] > 0))
  .map((k) => ({ kode: k, label: LABEL_PITA[k], warna: WARNA_PITA[k] })));

/* ── ringkasan: tunggakan per modul, bertumpuk menurut nada ── */

/*
 * Satu batang per modul, dipotong menurut nada butirnya: "Hazard &
 * Inspeksi 48" yang berwarna merah seluruhnya menyembunyikan bahwa
 * dua belas di antaranya baru perlu dijadwalkan. Jumlahnya selalu
 * sama dengan kolom "Tertunggak" di tabel modul.
 */
type NadaTunggak = 'gawat' | 'serius' | 'ingat';
const NADA_TUMPUK: NadaTunggak[] = ['gawat', 'serius', 'ingat'];
type BarisTunggak = {
  modul: string; label: string; url: string | null; perlu: number;
  per: Record<NadaTunggak, number>; lebar: number;
};

const tunggakanModul = computed<BarisTunggak[]>(() => {
  const baris = (props.ringkasanModul ?? [])
    .filter((m: any) => m.perlu > 0)
    .map((m: any) => {
      const per: Record<NadaTunggak, number> = { gawat: 0, serius: 0, ingat: 0 };
      for (const b of (m.semuaButir ?? m.butir ?? [])) {
        if (b.nada in per) per[b.nada as NadaTunggak] += Number(b.nilai ?? 0);
      }
      return { modul: String(m.modul), label: String(m.label), url: (m.url ?? null) as string | null, perlu: Number(m.perlu), per };
    })
    .sort((a: { perlu: number }, b: { perlu: number }) => b.perlu - a.perlu);
  const puncak = Math.max(1, ...baris.map((b: { perlu: number }) => b.perlu));
  return baris.map((b: Omit<BarisTunggak, 'lebar'>) => ({ ...b, lebar: (b.perlu / puncak) * 100 }));
});

const nadaTunggakan = computed(() => NADA_TUMPUK
  .filter((n) => tunggakanModul.value.some((m) => m.per[n] > 0)));

/* ── keselamatan ── */

const hazardLabel = computed<string[]>(() => g.value.keselamatan?.hazardBulanan?.label ?? []);
const hazardDeret = computed(() => [
  { nama: 'Masuk',         nilai: g.value.keselamatan?.hazardBulanan?.masuk ?? [], warna: KATEGORI[0] },
  { nama: 'Sudah ditutup', nilai: g.value.keselamatan?.hazardBulanan?.tutup ?? [], warna: KEADAAN.baik },
]);
const totalHazard = computed(() => jumlah(g.value.keselamatan?.hazardBulanan?.masuk));
const totalTutup  = computed(() => jumlah(g.value.keselamatan?.hazardBulanan?.tutup));

/* ── produksi dan energi: grafik kecil bersusun, satu satuan satu sumbu ── */

const prod   = computed<any>(() => g.value.produksi?.harian ?? {});
const angkut = computed<any>(() => g.value.produksi?.angkutan ?? {});
const energi = computed<any>(() => g.value.produksi?.energi ?? {});

const seriProduksi = computed(() => [
  { kunci: 'ton', nama: 'Batubara',   satuan: 'ton', nilai: prod.value.ton ?? [], warna: KATEGORI[0] },
  { kunci: 'ob',  nama: 'Overburden', satuan: 'bcm', nilai: prod.value.ob ?? [],  warna: KATEGORI[1] },
]);
const seriAngkut = computed(() => [
  { kunci: 'ton', nama: 'Tonase tertimbang', satuan: 'ton', nilai: angkut.value.ton ?? [], warna: KATEGORI[0] },
  { kunci: 'rit', nama: 'Ritase',            satuan: 'rit', nilai: angkut.value.rit ?? [], warna: KATEGORI[2] },
]);
const seriEnergi = computed(() => [
  { kunci: 'liter', nama: 'Solar terpakai', satuan: 'liter', nilai: energi.value.liter ?? [], warna: KATEGORI[1] },
  { kunci: 'idle',  nama: 'Jam idle',       satuan: 'jam',   nilai: energi.value.idle ?? [],  warna: KATEGORI[6] },
]);
const deretNisbah = computed(() => [
  { nama: 'Nisbah kupas', nilai: prod.value.nisbah ?? [], warna: KATEGORI[6] },
]);
const deretJam = computed(() => [
  { nama: 'Jam operasi', nilai: prod.value.jam ?? [],   warna: KATEGORI[2] },
  { nama: 'Jam delay',   nilai: prod.value.delay ?? [], warna: KATEGORI[4] },
]);

/* Nisbah keseluruhan rentang = jumlah OB / jumlah batubara — bukan
   rata-rata nisbah harian, yang memberi hari bertonase kecil bobot
   yang sama dengan hari penuh. */
const nisbahRentang = computed(() => {
  const ton = jumlah(prod.value.ton);
  return ton > 0 ? jumlah(prod.value.ob) / ton : null;
});
const fmtNisbah = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/* ── air ── */

const air = computed<any>(() => g.value.lingkungan?.air ?? {});
const seriAir = computed(() => [
  { kunci: 'hujan', nama: 'Curah hujan', satuan: 'mm', nilai: air.value.hujan ?? [], warna: KATEGORI[0] },
  { kunci: 'pompa', nama: 'Air dipompa', satuan: 'm³', nilai: air.value.pompa ?? [], warna: KATEGORI[6] },
]);

/* ── biaya ── */

const biaya = computed<any>(() => g.value.aset?.biayaBulanan ?? {});
const deretBiaya = computed(() => [{ nama: 'Realisasi', nilai: biaya.value.rp ?? [] }]);

/* ── sertifikat jatuh tempo ── */

const sertifikat = computed<{ label: string; nilai: number }[]>(() => {
  const x = g.value.orang?.sertifikat;
  return (x?.label ?? []).map((l: string, i: number) => ({ label: l, nilai: Number(x.nilai[i] ?? 0) }));
});
const totalSertifikat = computed(() => sertifikat.value.reduce((n, s) => n + s.nilai, 0));

/* ═════════ 4 · keadaan tiap modul ═════════ */

/*
 * Satu BARIS per modul — termasuk yang bersih. Modul yang hilang saat
 * semuanya beres tidak dapat dibedakan dari modul yang memang tidak ada
 * di dasbor, dan itulah cara dua modul luput selama ini.
 *
 * Tabel, bukan kisi kartu: tiga puluh modul dibandingkan dengan mata
 * menuruni satu kolom angka, bukan meloncat antarkartu.
 */
type Saring = 'semua' | 'masalah' | 'bersih';
const saring = ref<Saring>('semua');

const barisModul = computed(() => (props.ringkasanModul ?? [])
  .map((m: any) => {
    const kode: Nada = !m.perlu ? 'bersih' : (m.nada in STATUS ? m.nada : 'ingat');
    /* Seluruh butir, bukan dua teratas — dua teratas hanya untuk kartu
       ringkas; baris rincian di sini harus memuat semuanya. */
    const semua = m.semuaButir ?? m.butir ?? [];
    const butirPerlu = semua.filter((b: any) => b.nilai > 0 && b.nada !== 'kabar')
      .sort((a: any, b: any) => b.nilai - a.nilai);
    return { ...m, status: STATUS[kode], kode, semua, butirPerlu };
  })
  .filter((m: any) => saring.value === 'semua' || (saring.value === 'bersih' ? m.kode === 'bersih' : m.kode !== 'bersih'))
  .sort((a: any, b: any) => b.perlu - a.perlu || String(a.label).localeCompare(String(b.label), 'id')));

/* Baris yang sedang dibuka, sebagai peta kunci → terbuka. Bukan Set:
   membuka dan menutup baris tidak menghapus apa pun, dan `.delete()`
   di sini akan terbaca sebagai penghapusan tanpa penegasan oleh
   penjaga di LapanganTest. */
const terbuka = ref<Record<string, boolean>>({});
function alih(kunci: string) {
  terbuka.value = { ...terbuka.value, [kunci]: !terbuka.value[kunci] };
}

/** Warna tulisan sebuah butir menurut nadanya sendiri, bukan nada modulnya. */
const teksNada = (nada: string) => (nada in STATUS ? STATUS[nada as Nada].teks : undefined);
</script>

<template>
  <Head :title="props.judul" />

  <div class="das space-y-5">

    <!-- ═══ 1 · situasi hari ini ═══ -->
    <section class="eq-panel das-situasi" aria-labelledby="das-situasi-judul">
      <div class="das-situasi-kepala">
        <div class="min-w-0">
          <p class="das-mata">Situasi hari ini</p>
          <h2 id="das-situasi-judul" class="das-judul">
            <template v-if="jumlahMendesak">
              <b class="num" :style="{ color: STATUS.gawat.teks }">{{ angka(jumlahMendesak) }}</b> hal menuntut tindakan hari ini
            </template>
            <template v-else>Tidak ada yang lewat batas hari ini</template>
          </h2>
          <p class="das-ket">
            <template v-if="mendesak.length">Tersebar di {{ mendesak.length }} butir pekerjaan · </template>
            {{ angka(jumlahSerius) }} menunggu diperiksa ·
            {{ angka(jumlahDiurus) }} perlu dijadwalkan minggu ini ·
            {{ modulBersih }} dari {{ jumlahModul }} modul bersih
          </p>
        </div>
        <dl class="das-ringkas">
          <div>
            <dt><i :style="{ background: STATUS.gawat.warna }"></i>Gawat</dt>
            <dd class="num" :style="{ color: jumlahMendesak ? STATUS.gawat.teks : undefined }">{{ angka(jumlahMendesak) }}</dd>
          </div>
          <div>
            <dt><i :style="{ background: STATUS.serius.warna }"></i>Serius</dt>
            <dd class="num" :style="{ color: jumlahSerius ? STATUS.serius.teks : undefined }">{{ angka(jumlahSerius) }}</dd>
          </div>
          <div>
            <dt><i :style="{ background: STATUS.ingat.warna }"></i>Perlu diurus</dt>
            <dd class="num" :style="{ color: jumlahDiurus ? STATUS.ingat.teks : undefined }">{{ angka(jumlahDiurus) }}</dd>
          </div>
          <div>
            <dt><i :style="{ background: STATUS.bersih.warna }"></i>Modul bersih</dt>
            <dd class="num">{{ modulBersih }}<small>/{{ jumlahModul }}</small></dd>
          </div>
        </dl>
      </div>

      <template v-if="komposisi.length">
        <!-- Pita komposisi: porsi tiap butir terhadap seluruh yang gawat. -->
        <div class="das-pita" role="img"
             :aria-label="`Komposisi ${jumlahMendesak} hal gawat: ` + komposisi.map((k) => `${k.nama} ${k.nilai}`).join(', ')">
          <span v-for="k in komposisi" :key="k.nama" :title="`${k.nama} · ${k.nilai}`"
                :style="{ flexGrow: k.nilai, background: k.warna }"></span>
        </div>

        <ol class="das-tindakan">
          <li v-for="k in komposisi" :key="k.nama">
            <component :is="k.url ? Link : 'div'" :href="k.url ?? undefined" class="das-tindakan-baris">
              <i class="das-titik" :style="{ background: k.warna }" aria-hidden="true"></i>
              <b class="num das-tindakan-angka">{{ angka(k.nilai) }}</b>
              <span class="min-w-0">
                <strong>{{ k.nama }}</strong>
                <small v-if="k.ket">{{ k.ket }}</small>
              </span>
              <span class="das-tindakan-porsi num">{{ persen(porsi(k.nilai, jumlahMendesak)) }}</span>
            </component>
          </li>
        </ol>
      </template>

      <!-- Serius: tidak lewat batas, tetapi menunggu keputusan orang. -->
      <div v-if="serius.length" class="das-minggu">
        <p class="das-minggu-judul">Menunggu diperiksa</p>
        <div class="das-minggu-isi">
          <Link v-for="m in serius" :key="m.nama" :href="m.url" class="das-chip" :title="m.ket">
            <b class="num" :style="{ color: STATUS.serius.teks }">{{ angka(m.nilai) }}</b>
            {{ m.nama }}<template v-if="m.total > 0"><span class="das-chip-dari num"> / {{ angka(m.total) }}</span></template>
          </Link>
        </div>
      </div>

      <!-- Perlu diurus: belum lewat batas, tetapi tidak akan selesai sendiri. -->
      <div v-if="perluDiurus.length" class="das-minggu">
        <p class="das-minggu-judul">Dijadwalkan minggu ini</p>
        <div class="das-minggu-isi">
          <Link v-for="m in perluDiurus" :key="m.nama" :href="m.url" class="das-chip" :title="m.ket">
            <b class="num" :style="{ color: STATUS.ingat.teks }">{{ angka(m.nilai) }}</b>
            {{ m.nama }}<template v-if="m.total > 0"><span class="das-chip-dari num"> / {{ angka(m.total) }}</span></template>
          </Link>
        </div>
      </div>
    </section>

    <!-- ═══ 2 · angka pembuka ═══ -->
    <section class="das-kpi" aria-label="Angka pembuka">
      <div v-for="k in kpi" :key="k.label" class="eq-panel das-kpi-kartu">
        <p class="das-kpi-label">
          <i v-if="k.warna" class="das-titik" :style="{ background: k.warna }" aria-hidden="true"></i>{{ k.label }}
        </p>
        <strong class="num das-kpi-nilai" :style="{ color: k.warna ?? undefined }">{{ angka(k.nilai) }}</strong>
        <p class="das-kpi-ket">{{ k.ket }}</p>
        <span v-if="k.porsi !== null" class="das-kpi-pita" role="img" :aria-label="persen(k.porsi)">
          <i :style="{ width: `${Math.min(100, k.porsi)}%`, background: k.pita ?? undefined }"></i>
        </span>
      </div>
    </section>

    <!-- ═══ 3 · pemilih tab dan rentang ═══ -->
    <section class="das-alat">
      <div class="das-segmen das-segmen-tab" role="tablist" aria-label="Kelompok grafik">
        <button v-for="t in TAB" :key="t.kode" type="button" role="tab"
                :aria-selected="tab === t.kode" :class="{ kini: tab === t.kode }"
                @click="tab = t.kode">{{ t.nama }}</button>
      </div>

      <!-- Rentang hanya mengubah grafik bertren. Angka sebaran — status
           objek, kategori barang — tidak punya waktu. -->
      <div class="das-rentang">
        <span>Rentang tren</span>
        <button v-for="h in props.opsiHari ?? []" :key="h" type="button" class="num"
                :class="{ kini: props.hari === h }" :aria-pressed="props.hari === h"
                @click="pilihHari(h)">{{ h }} hari</button>
      </div>
    </section>

    <!-- ═══ 4 · grafik, per tab ═══ -->

    <!-- ── ringkasan ── -->
    <template v-if="tab === 'ringkas'">
      <div class="grid gap-4 lg:grid-cols-5">
        <KartuGrafik class="lg:col-span-2" judul="Kepatuhan berkas kelayakan" :angka="`${angka(totalBerkas)} berkas`"
                     catatan="MCU, Mine Permit, dan SIMPER — dihitung atas tanggal yang benar-benar berlaku.">
          <Donat v-if="pita.length" :bagian="pita"
                 :tengah="persen(porsi(props.ringkas?.berkasHabis, totalBerkas))" tengah-label="sudah habis" />
          <p v-else class="das-kosong">Belum ada berkas yang tercatat.</p>
          <template #tabel>
            <TabelAngka :kolom="['Keadaan', 'Berkas', 'Porsi']" :baris="barisBagian(pita)" :kunci="kunciBagian(pita)" />
          </template>
        </KartuGrafik>

        <KartuGrafik class="lg:col-span-3" judul="Berkas per perusahaan"
                     catatan="Bertumpuk menurut keadaan, dalam satuan berkas; panjang batang sebanding dengan jumlah berkasnya. Jumlah orang ditulis terpisah — satu orang memegang beberapa berkas.">
          <div v-if="perusahaanBerkas.length" class="das-tumpuk">
            <div v-for="c in perusahaanBerkas" :key="c.label" class="das-tumpuk-baris">
              <span class="das-tumpuk-nama">{{ c.label }}</span>
              <div class="das-tumpuk-jalur" role="img"
                   :aria-label="`${c.label}, ${c.berkas} berkas: ` + c.potong.map((p) => `${p.label} ${p.nilai}`).join(', ')">
                <div class="das-tumpuk-isi" :style="{ width: `${c.lebar}%` }">
                  <i v-for="p in c.potong" :key="p.kode" :title="`${p.label}: ${p.nilai} berkas`"
                     :style="{ flexGrow: p.nilai, background: p.warna }"></i>
                </div>
              </div>
              <span class="das-tumpuk-angka num">{{ angka(c.berkas) }} berkas · {{ angka(c.orang) }} orang</span>
            </div>
            <div class="das-legenda">
              <span v-for="l in legendaPita" :key="l.kode"><i :style="{ background: l.warna }"></i>{{ l.label }}</span>
            </div>
          </div>
          <p v-else class="das-kosong">Belum ada data perusahaan.</p>
          <template #tabel>
            <TabelAngka :kolom="['Perusahaan', 'Orang', 'Berkas', 'Habis', 'Mendesak', 'Dekat', 'Aman', 'Tanpa tanggal']"
                        :baris="perusahaanBerkas.map((c) => [c.label, c.orang, c.berkas, c.per.habis, c.per.mendesak, c.per.dekat, c.per.panjang, c.per['tak-bertanggal']])" />
          </template>
        </KartuGrafik>
      </div>

      <div class="grid gap-4 lg:grid-cols-5">
        <KartuGrafik class="lg:col-span-3" judul="Tunggakan per modul" :angka="`${tunggakanModul.length} modul`"
                     catatan="Dipotong menurut nada butirnya; jumlahnya sama dengan kolom Tertunggak di tabel modul. Klik baris untuk membuka modulnya.">
          <div v-if="tunggakanModul.length" class="das-tumpuk">
            <component :is="m.url ? Link : 'div'" v-for="m in tunggakanModul" :key="m.modul" :href="m.url ?? undefined"
                       class="das-tumpuk-baris das-tumpuk-baris-sempit das-tumpuk-tautan">
              <span class="das-tumpuk-nama">{{ m.label }}</span>
              <div class="das-tumpuk-jalur" role="img"
                   :aria-label="`${m.label}, ${m.perlu} tertunggak: ` + NADA_TUMPUK.filter((n) => m.per[n]).map((n) => `${STATUS[n].label} ${m.per[n]}`).join(', ')">
                <div class="das-tumpuk-isi" :style="{ width: `${m.lebar}%` }">
                  <template v-for="n in NADA_TUMPUK" :key="n">
                    <i v-if="m.per[n]" :title="`${STATUS[n].label}: ${m.per[n]}`"
                       :style="{ flexGrow: m.per[n], background: STATUS[n].warna }"></i>
                  </template>
                </div>
              </div>
              <span class="das-tumpuk-angka num"><b>{{ angka(m.perlu) }}</b></span>
            </component>
            <div class="das-legenda">
              <span v-for="n in nadaTunggakan" :key="n"><i :style="{ background: STATUS[n].warna }"></i>{{ STATUS[n].label }}</span>
            </div>
          </div>
          <p v-else class="das-kosong">Tidak ada modul yang menunggak.</p>
          <template #tabel>
            <TabelAngka :kolom="['Modul', 'Gawat', 'Serius', 'Perlu diurus', 'Jumlah']"
                        :baris="tunggakanModul.map((m) => [m.label, m.per.gawat, m.per.serius, m.per.ingat, m.perlu])" />
          </template>
        </KartuGrafik>
        <KartuGrafik class="lg:col-span-2" judul="Kepatuhan"
                     catatan="Persen, bukan jumlah — dua belas objek lewat jadwal berarti lain pada 15 objek dan pada 400. Takik pada cincin: target 90%; warnanya mengikuti jarak ke target.">
          <ul class="das-cincin">
            <li v-for="m in props.meter ?? []" :key="m.nama" class="das-cincin-baris">
              <Cincin :nilai="m.nilai" :maks="100" satuan="%" :target="90" :ukuran="84" :label="m.nama" class="das-cincin-gambar" />
              <div class="min-w-0">
                <p class="das-cincin-nama">{{ m.nama }}</p>
                <p class="das-cincin-ket">{{ m.ket }}</p>
                <p v-if="m.dari > 0" class="das-cincin-pecah num">{{ angka(m.baik) }} dari {{ angka(m.dari) }}</p>
                <p v-else class="das-cincin-pecah">Belum ada data</p>
              </div>
            </li>
          </ul>
          <template #tabel>
            <TabelAngka :kolom="['Ukuran', 'Memenuhi', 'Dari', 'Capaian', 'Dasar hitungan']"
                        :baris="(props.meter ?? []).map((m: any) => [m.nama, m.baik, m.dari, persen(m.nilai), m.ket])" />
          </template>
        </KartuGrafik>
      </div>
    </template>

    <!-- ── keselamatan ── -->
    <template v-if="tab === 'keselamatan'">
      <KartuGrafik judul="Laporan bahaya per bulan"
                   :angka="`${angka(totalHazard)} masuk · ${persen(porsi(totalTutup, totalHazard))} sudah ditutup`"
                   catatan="Masuk dan sudah ditutup berdampingan: laporan yang naik dapat berarti budaya lapor membaik ATAU keadaan memburuk — yang membedakannya berapa yang ditutup. Ditutup dihitung atas laporan yang masuk pada bulan itu.">
        <Garis :label="hazardLabel" :deret="hazardDeret" satuan="laporan" :tinggi="210" />
        <Legenda :deret="hazardDeret" />
        <template #tabel>
          <TabelAngka :kolom="['Bulan', 'Masuk', 'Sudah ditutup']"
                      :baris="barisDeret(hazardLabel, [g.keselamatan?.hazardBulanan?.masuk, g.keselamatan?.hazardBulanan?.tutup])" />
        </template>
      </KartuGrafik>

      <div class="grid gap-4 lg:grid-cols-2">
        <KartuGrafik judul="Bahaya menurut tingkat risiko"
                     catatan="Urut menurut skalanya, dari yang tertinggi — yang menentukan urutan penanganan, bukan jumlahnya.">
          <Batang v-if="berisi(g.keselamatan?.hazardRisiko)" :baris="g.keselamatan.hazardRisiko" apa-adanya satuan="laporan" />
          <p v-else class="das-kosong">Belum ada laporan bahaya.</p>
          <template #tabel>
            <TabelAngka :kolom="['Tingkat risiko', 'Laporan', 'Porsi']" :baris="barisBagian(g.keselamatan?.hazardRisiko)"
                        :kunci="kunciBagian(g.keselamatan?.hazardRisiko, true)" />
          </template>
        </KartuGrafik>

        <KartuGrafik judul="Bahaya menurut kategori"
                     catatan="Enam kategori terbanyak — sisanya berekor panjang dan tidak mengubah keputusan.">
          <Batang v-if="berisi(g.keselamatan?.hazardKategori)" :baris="g.keselamatan.hazardKategori" satuan="laporan" />
          <p v-else class="das-kosong">Belum ada laporan bahaya.</p>
          <template #tabel>
            <TabelAngka :kolom="['Kategori', 'Laporan', 'Porsi']" :baris="barisBagian(g.keselamatan?.hazardKategori)" />
          </template>
        </KartuGrafik>
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <KartuGrafik judul="Inspeksi menurut status">
          <Donat v-if="berisi(g.keselamatan?.inspeksiStatus)" :bagian="g.keselamatan.inspeksiStatus" />
          <p v-else class="das-kosong">Belum ada inspeksi.</p>
          <template #tabel>
            <TabelAngka :kolom="['Status', 'Inspeksi', 'Porsi']" :baris="barisBagian(g.keselamatan?.inspeksiStatus)"
                        :kunci="kunciBagian(g.keselamatan?.inspeksiStatus)" />
          </template>
        </KartuGrafik>

        <KartuGrafik judul="Temuan audit SMKP menurut status"
                     catatan="Nama status sama dengan modul SMKP: Open, In Progress, Closed.">
          <Donat v-if="berisi(g.keselamatan?.temuanStatus)" :bagian="g.keselamatan.temuanStatus" />
          <p v-else class="das-kosong">Belum ada temuan audit.</p>
          <template #tabel>
            <TabelAngka :kolom="['Status', 'Temuan', 'Porsi']" :baris="barisBagian(g.keselamatan?.temuanStatus)"
                        :kunci="kunciBagian(g.keselamatan?.temuanStatus)" />
          </template>
        </KartuGrafik>
      </div>
    </template>

    <!-- ── produksi dan energi ── -->
    <template v-if="tab === 'produksi'">
      <KartuGrafik :judul="`Produksi ${props.hari} hari terakhir`"
                   :angka="`Nisbah rentang ${nisbahRentang === null ? '—' : fmtNisbah.format(nisbahRentang)} bcm/ton`"
                   catatan="Batubara dan overburden digambar terpisah karena satuannya berbeda; sumbu waktunya sejajar. Hari tanpa catatan digambar nol, bukan dilewati — garis yang melompati hari kosong membuat tambang berhenti terbaca sebagai penurunan bertahap.">
        <div class="das-multipel">
          <div v-for="s in seriProduksi" :key="s.kunci">
            <p class="das-multipel-kepala">
              <i :style="{ background: s.warna }" aria-hidden="true"></i><b>{{ s.nama }}</b>
              <span class="das-multipel-jumlah num">{{ angka(Math.round(jumlah(s.nilai))) }} {{ s.satuan }}</span>
            </p>
            <Garis :label="prod.label ?? []" :deret="[{ nama: s.nama, nilai: s.nilai, warna: s.warna }]"
                   :satuan="s.satuan" :tinggi="140" :kiri="56" bidang />
          </div>
        </div>
        <template #tabel>
          <TabelAngka :kolom="['Tanggal', 'Batubara (ton)', 'Overburden (bcm)', 'Nisbah (bcm/ton)']"
                      :baris="barisDeret(prod.label, [prod.ton, prod.ob, prod.nisbah])" />
        </template>
      </KartuGrafik>

      <div class="grid gap-4 lg:grid-cols-2">
        <KartuGrafik judul="Nisbah kupas harian"
                     catatan="Dihitung per hari dari kedua angkanya, bukan dari totalnya: nisbah rata-rata sebulan menyembunyikan hari pengupasan berat yang justru menentukan biayanya. Hari tanpa produksi tidak punya nisbah — digambar putus, bukan nol.">
          <Garis :label="prod.label ?? []" :deret="deretNisbah" satuan="bcm/ton" :tinggi="190" bidang />
          <template #tabel>
            <TabelAngka :kolom="['Tanggal', 'Nisbah (bcm/ton)']" :baris="barisDeret(prod.label, [prod.nisbah])" />
          </template>
        </KartuGrafik>

        <KartuGrafik judul="Jam operasi dan jam delay"
                     :angka="`${persen(porsi(jumlah(prod.delay), jumlah(prod.jam) + jumlah(prod.delay)))} delay`"
                     catatan="Porsi delay = jam delay dibagi jumlah jam operasi dan jam delay pada rentang ini.">
          <Garis :label="prod.label ?? []" :deret="deretJam" satuan="jam" :tinggi="190" />
          <Legenda :deret="deretJam" />
          <template #tabel>
            <TabelAngka :kolom="['Tanggal', 'Jam operasi', 'Jam delay']" :baris="barisDeret(prod.label, [prod.jam, prod.delay])" />
          </template>
        </KartuGrafik>
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <KartuGrafik judul="Angkutan tertimbang"
                     catatan="Dari jembatan timbang, bukan dari catatan shift. Ritase = banyaknya penimbangan.">
          <div class="das-multipel">
            <div v-for="s in seriAngkut" :key="s.kunci">
              <p class="das-multipel-kepala">
                <i :style="{ background: s.warna }" aria-hidden="true"></i><b>{{ s.nama }}</b>
                <span class="das-multipel-jumlah num">{{ angka(Math.round(jumlah(s.nilai))) }} {{ s.satuan }}</span>
              </p>
              <Garis :label="angkut.label ?? []" :deret="[{ nama: s.nama, nilai: s.nilai, warna: s.warna }]"
                     :satuan="s.satuan" :tinggi="120" :kiri="56" bidang />
            </div>
          </div>
          <template #tabel>
            <TabelAngka :kolom="['Tanggal', 'Tonase (ton)', 'Ritase']" :baris="barisDeret(angkut.label, [angkut.ton, angkut.rit])" />
          </template>
        </KartuGrafik>

        <KartuGrafik judul="Solar terpakai dan jam idle"
                     catatan="Idle yang tinggi bersama solar yang tinggi berarti bahan bakar terbakar tanpa memindahkan apa pun.">
          <div class="das-multipel">
            <div v-for="s in seriEnergi" :key="s.kunci">
              <p class="das-multipel-kepala">
                <i :style="{ background: s.warna }" aria-hidden="true"></i><b>{{ s.nama }}</b>
                <span class="das-multipel-jumlah num">{{ angka(Math.round(jumlah(s.nilai))) }} {{ s.satuan }}</span>
              </p>
              <Garis :label="energi.label ?? []" :deret="[{ nama: s.nama, nilai: s.nilai, warna: s.warna }]"
                     :satuan="s.satuan" :tinggi="120" :kiri="56" bidang />
            </div>
          </div>
          <template #tabel>
            <TabelAngka :kolom="['Tanggal', 'Solar (liter)', 'Jam idle']" :baris="barisDeret(energi.label, [energi.liter, energi.idle])" />
          </template>
        </KartuGrafik>
      </div>
    </template>

    <!-- ── air dan lingkungan ── -->
    <template v-if="tab === 'lingkungan'">
      <KartuGrafik judul="Curah hujan dan air yang dipompa"
                   catatan="Bersusun karena yang satu menjelaskan yang lain: pemompaan yang naik tanpa hujan berarti ada rembesan. Satuannya berbeda, jadi sumbunya terpisah.">
        <div class="das-multipel">
          <div v-for="s in seriAir" :key="s.kunci">
            <p class="das-multipel-kepala">
              <i :style="{ background: s.warna }" aria-hidden="true"></i><b>{{ s.nama }}</b>
              <span class="das-multipel-jumlah num">{{ angka(Math.round(jumlah(s.nilai))) }} {{ s.satuan }}</span>
            </p>
            <Garis :label="air.label ?? []" :deret="[{ nama: s.nama, nilai: s.nilai, warna: s.warna }]"
                   :satuan="s.satuan" :tinggi="140" :kiri="56" bidang />
          </div>
        </div>
        <template #tabel>
          <TabelAngka :kolom="['Tanggal', 'Curah hujan (mm)', 'Air dipompa (m³)']" :baris="barisDeret(air.label, [air.hujan, air.pompa])" />
        </template>
      </KartuGrafik>

      <div class="grid gap-4 lg:grid-cols-2">
        <KartuGrafik judul="Hasil pantau terhadap baku mutu"
                     catatan="Dihitung terhadap ambang yang berlaku SEKARANG — baku mutu berubah, dan pelanggaran yang dihitung sekali akan menyebut angka lama selamanya.">
          <Donat v-if="berisi(g.lingkungan?.bakuMutu)" :bagian="g.lingkungan.bakuMutu" />
          <p v-else class="das-kosong">Belum ada hasil pantau.</p>
          <template #tabel>
            <TabelAngka :kolom="['Hasil', 'Pengukuran', 'Porsi']" :baris="barisBagian(g.lingkungan?.bakuMutu)"
                        :kunci="kunciBagian(g.lingkungan?.bakuMutu)" />
          </template>
        </KartuGrafik>

        <KartuGrafik judul="Dokumen menurut status"
                     catatan="Dokumen yang tidak berstatus Berlaku tidak boleh dipakai di lapangan.">
          <Donat v-if="berisi(g.orang?.dokumenStatus)" :bagian="g.orang.dokumenStatus" />
          <p v-else class="das-kosong">Belum ada dokumen.</p>
          <template #tabel>
            <TabelAngka :kolom="['Status', 'Dokumen', 'Porsi']" :baris="barisBagian(g.orang?.dokumenStatus)"
                        :kunci="kunciBagian(g.orang?.dokumenStatus)" />
          </template>
        </KartuGrafik>
      </div>
    </template>

    <!-- ── aset dan biaya ── -->
    <template v-if="tab === 'aset'">
      <div class="grid gap-4 lg:grid-cols-2">
        <KartuGrafik judul="Objek keselamatan operasi menurut status">
          <Donat v-if="berisi(g.aset?.koStatus)" :bagian="g.aset.koStatus" />
          <p v-else class="das-kosong">Belum ada objek terdaftar.</p>
          <template #tabel>
            <TabelAngka :kolom="['Status', 'Objek', 'Porsi']" :baris="barisBagian(g.aset?.koStatus)"
                        :kunci="kunciBagian(g.aset?.koStatus)" />
          </template>
        </KartuGrafik>

        <KartuGrafik judul="Work order menurut status"
                     catatan="Warna sama dengan lencana di halaman Maintenance.">
          <Donat v-if="berisi(g.aset?.workOrderStatus)" :bagian="g.aset.workOrderStatus" />
          <p v-else class="das-kosong">Belum ada work order.</p>
          <template #tabel>
            <TabelAngka :kolom="['Status', 'Work order', 'Porsi']" :baris="barisBagian(g.aset?.workOrderStatus)"
                        :kunci="kunciBagian(g.aset?.workOrderStatus)" />
          </template>
        </KartuGrafik>
      </div>

      <div class="grid gap-4 lg:grid-cols-5">
        <KartuGrafik class="lg:col-span-2" judul="Barang gudang menurut kategori">
          <Batang v-if="berisi(g.aset?.gudangKategori)" :baris="g.aset.gudangKategori" satuan="jenis" />
          <p v-else class="das-kosong">Belum ada barang terdaftar.</p>
          <template #tabel>
            <TabelAngka :kolom="['Kategori', 'Jenis barang', 'Porsi']" :baris="barisBagian(g.aset?.gudangKategori)" />
          </template>
        </KartuGrafik>

        <KartuGrafik class="lg:col-span-3" judul="Realisasi biaya per bulan" :angka="`Rp ${angka(Math.round(jumlah(biaya.rp)))}`"
                     catatan="Tahun berjalan. Bulan yang sudah lewat tanpa catatan digambar nol supaya bentuk serapannya terlihat utuh; bulan yang belum tiba tidak digambar.">
          <Garis :label="biaya.label ?? []" :deret="deretBiaya" satuan="Rp" :tinggi="190" bidang />
          <template #tabel>
            <TabelAngka :kolom="['Bulan', 'Realisasi (Rp)']" :baris="barisDeret(biaya.label, [biaya.rp])" :desimal="0" />
          </template>
        </KartuGrafik>
      </div>
    </template>

    <!-- ── kelayakan orang ── -->
    <template v-if="tab === 'orang'">
      <div class="grid gap-4 lg:grid-cols-2">
        <KartuGrafik judul="Hasil MCU terakhir tiap orang"
                     catatan="Yang TERAKHIR saja — memasukkan seluruh riwayat membuat orang yang pernah unfit lalu pulih tetap terhitung unfit selamanya. Urut dari yang layak.">
          <Donat v-if="berisi(g.orang?.mcuHasil)" :bagian="g.orang.mcuHasil" />
          <p v-else class="das-kosong">Belum ada hasil MCU.</p>
          <template #tabel>
            <TabelAngka :kolom="['Hasil', 'Orang', 'Porsi']" :baris="barisBagian(g.orang?.mcuHasil)"
                        :kunci="kunciBagian(g.orang?.mcuHasil)" />
          </template>
        </KartuGrafik>

        <KartuGrafik judul="Sertifikat kompetensi jatuh tempo" :angka="`${angka(totalSertifikat)} sertifikat`"
                     catatan="Enam bulan ke depan, per bulan: yang menumpuk menentukan kapan pelatihan ulang harus dijadwalkan, dan jadwal pelatihan disusun berbulan-bulan sebelumnya.">
          <Batang v-if="berisi(sertifikat)" :baris="sertifikat" apa-adanya satuan="sertifikat" />
          <p v-else class="das-kosong">Tidak ada sertifikat yang jatuh tempo enam bulan ke depan.</p>
          <template #tabel>
            <TabelAngka :kolom="['Bulan', 'Sertifikat']" :baris="sertifikat.map((s) => [s.label, s.nilai])" />
          </template>
        </KartuGrafik>
      </div>

      <section class="eq-panel" aria-labelledby="das-perusahaan-judul">
        <div class="eq-panel-kepala">
          <div>
            <h3 id="das-perusahaan-judul">Kepatuhan berkas per perusahaan</h3>
            <span class="eq-panel-ket">
              Diurutkan dari yang paling banyak masalahnya — itu yang perlu ditelepon lebih dulu.
              Porsi dihitung terhadap jumlah berkas perusahaan itu.
            </span>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="das-tabel">
            <thead>
              <tr>
                <th>Perusahaan</th>
                <th class="kanan">Orang</th>
                <th class="kanan">Berkas</th>
                <th class="kanan">Habis</th>
                <th class="kanan">Mendesak</th>
                <th class="kanan">Dekat</th>
                <th class="kanan">Tanpa tanggal</th>
                <th class="kanan">Porsi habis</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in perusahaanBerkas" :key="c.label">
                <td class="tebal">{{ c.label }}</td>
                <td class="kanan num">{{ angka(c.orang) }}</td>
                <td class="kanan num">{{ angka(c.berkas) }}</td>
                <td class="kanan num tebal" :style="{ color: c.per.habis ? STATUS.gawat.teks : undefined }">{{ c.per.habis || '—' }}</td>
                <td class="kanan num" :style="{ color: c.per.mendesak ? STATUS.serius.teks : undefined }">{{ c.per.mendesak || '—' }}</td>
                <td class="kanan num" :style="{ color: c.per.dekat ? STATUS.ingat.teks : undefined }">{{ c.per.dekat || '—' }}</td>
                <td class="kanan num">{{ c.per['tak-bertanggal'] || '—' }}</td>
                <td class="kanan num">{{ persen(porsi(c.per.habis, c.berkas)) }}</td>
              </tr>
              <tr v-if="!perusahaanBerkas.length">
                <td colspan="8" class="das-kosong">Belum ada data perusahaan.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>

    <!-- ═══ 5 · keadaan tiap modul ═══ -->
    <section v-if="(props.ringkasanModul ?? []).length" class="eq-panel" aria-labelledby="das-modul-judul">
      <div class="eq-panel-kepala das-modul-kepala">
        <div>
          <h3 id="das-modul-judul">Keadaan tiap modul</h3>
          <span class="eq-panel-ket">
            {{ modulBersih }} dari {{ jumlahModul }} modul tidak menyisakan apa pun hari ini.
            Nol berarti tidak ada yang tertunggak — bukan tidak ada datanya.
          </span>
        </div>
        <div class="das-segmen das-segmen-kecil" role="group" aria-label="Saring modul">
          <button type="button" :class="{ kini: saring === 'semua' }" :aria-pressed="saring === 'semua'" @click="saring = 'semua'">Semua</button>
          <button type="button" :class="{ kini: saring === 'masalah' }" :aria-pressed="saring === 'masalah'" @click="saring = 'masalah'">Bermasalah</button>
          <button type="button" :class="{ kini: saring === 'bersih' }" :aria-pressed="saring === 'bersih'" @click="saring = 'bersih'">Bersih</button>
        </div>
      </div>

      <!-- data-tabel-tetap: tata letak ponselnya ditulis sendiri di bawah
           (dua baris ringkas per modul), bukan kartu berlabel otomatis
           yang membuat tiga puluh modul setinggi tujuh ribu piksel. -->
      <div data-tabel-tetap>
        <table class="das-tabel das-tabel-modul">
          <thead>
            <tr>
              <th colspan="3">Modul</th>
              <th>Keadaan</th>
              <th class="kanan">Tertunggak</th>
              <th>Rincian</th>
              <th class="kanan">Catatan tersimpan</th>
              <th><span class="sr-only">Buka</span></th>
            </tr>
          </thead>
          <tbody>
            <template v-for="m in barisModul" :key="m.modul">
              <tr class="das-modul-baris">
                <td class="das-sel-buka">
                  <button v-if="m.semua.length > 1" type="button" class="das-buka"
                          :aria-expanded="!!terbuka[m.modul]" :aria-label="`Rincian ${m.label}`" @click="alih(m.modul)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"
                         stroke-linejoin="round" aria-hidden="true" :class="{ putar: terbuka[m.modul] }"><path d="m9 6 6 6-6 6" /></svg>
                  </button>
                </td>
                <td class="das-sel-ikon">
                  <span class="das-modul-ikon" aria-hidden="true"><IkonPadat :jalur="m.ikonPadat" :ukuran="15" /></span>
                </td>
                <td class="das-sel-nama tebal">{{ m.label }}</td>
                <td class="das-sel-status">
                  <span class="das-status" :style="{ '--s': m.status.warna, '--st': m.status.teks }">
                    <i aria-hidden="true"></i>{{ m.status.label }}
                  </span>
                </td>
                <td class="das-sel-angka kanan num tebal" :style="{ color: m.perlu ? m.status.teks : undefined }">{{ angka(m.perlu) }}</td>
                <td class="das-sel-rincian">
                  <div class="das-rincian">
                    <template v-if="m.butirPerlu.length">
                      <span v-for="b in m.butirPerlu.slice(0, 2)" :key="b.nama">
                        <b class="num" :style="{ color: teksNada(b.nada) }">{{ angka(b.nilai) }}</b> {{ b.nama }}
                      </span>
                      <span v-if="m.butirPerlu.length > 2" class="das-redup">+{{ m.butirPerlu.length - 2 }} lainnya</span>
                    </template>
                    <span v-else class="das-redup">
                      Tidak ada yang tertunggak<span v-if="m.kabar" class="das-hanya-hp num"> · {{ angka(m.kabar) }} catatan</span>
                    </span>
                  </div>
                </td>
                <td class="das-sel-kabar kanan num das-redup">{{ m.kabar ? angka(m.kabar) : '—' }}</td>
                <td class="das-sel-tautan kanan">
                  <Link v-if="m.url" :href="m.url" class="das-tautan">Buka<span class="sr-only"> {{ m.label }}</span></Link>
                </td>
              </tr>
              <tr v-if="terbuka[m.modul]" class="das-sub">
                <td colspan="8">
                  <ul class="das-butir">
                    <li v-for="b in m.semua" :key="b.nama">
                      <span>{{ b.nama }}<small v-if="b.nada === 'kabar'"> · catatan</small></span>
                      <b class="num" :style="{ color: b.nilai && b.nada !== 'kabar' ? teksNada(b.nada) : undefined }">{{ angka(b.nilai) }}</b>
                      <small v-if="b.total > 0" class="num">dari {{ angka(b.total) }}</small>
                    </li>
                  </ul>
                </td>
              </tr>
            </template>
            <tr v-if="!barisModul.length" class="das-kosong-baris">
              <td colspan="8" class="das-kosong">Tidak ada modul pada saringan ini.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<style scoped>
.das {
  --das-garis: rgba(15, 33, 30, .08);
  --das-garis-2: rgba(15, 33, 30, .14);
  /* Turunan gelap warna keadaan untuk TULISAN — ≥ 4,6:1 pada putih
     dan pada latar lencananya (warna keadaan 10%). */
  --das-gawat: #B42318;
  --das-serius: #B93815;
  --das-ingat: #A15C07;
  --das-baik: #067647;
}
:root[data-tema="gelap"] .das {
  --das-garis: rgba(255, 255, 255, .08);
  --das-garis-2: rgba(255, 255, 255, .14);
  /* Turunan terangnya pada permukaan gelap — ≥ 5:1 pada #141F23. */
  --das-gawat: #F97066;
  --das-serius: #F7905A;
  --das-ingat: #FDB022;
  --das-baik: #47CD89;
}

.das-mata {
  font-size: 11px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase;
  color: var(--eq-aksen-gelap, #0F6B59);
}
:root[data-tema="gelap"] .das-mata { color: #A9CF5E; }
.das-judul { margin-top: 4px; font-size: 22px; font-weight: 800; letter-spacing: -.02em; color: var(--eq-judul, #0F211E); }
.das-ket { margin-top: 4px; font-size: 12.5px; color: var(--eq-redup, #5F6E6A); }
.das-titik { display: inline-block; width: 9px; height: 9px; border-radius: 3px; flex: none; }

/* ── situasi ── */
.das-situasi-kepala { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 16px 32px; }
.das-ringkas { display: flex; border: 1px solid var(--das-garis-2); border-radius: 12px; overflow: hidden; }
.das-ringkas > div { padding: 8px 16px; min-width: 92px; }
.das-ringkas > div + div { border-left: 1px solid var(--das-garis-2); }
.das-ringkas dt {
  display: flex; align-items: center; gap: 5px; white-space: nowrap;
  font-size: 10.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--eq-redup, #5F6E6A);
}
.das-ringkas dt i { width: 7px; height: 7px; border-radius: 2px; flex: none; }
.das-ringkas dd { margin-top: 2px; font-size: 20px; font-weight: 800; color: var(--eq-judul, #0F211E); }
.das-ringkas dd small { font-size: 12px; font-weight: 600; color: var(--eq-redup, #5F6E6A); }

.das-pita { display: flex; gap: 2px; height: 10px; margin-top: 18px; border-radius: 6px; overflow: hidden; }
.das-pita span { display: block; min-width: 4px; }

.das-tindakan { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 320px), 1fr)); gap: 0 24px; margin-top: 10px; }
.das-tindakan li { border-bottom: 1px solid var(--das-garis); }
.das-tindakan-baris {
  display: grid; grid-template-columns: auto 3.2rem minmax(0, 1fr) auto; align-items: center; gap: 10px;
  padding: 10px 4px; border-radius: 8px; transition: background-color .15s;
}
a.das-tindakan-baris:hover { background: var(--eq-aksen-tipis, rgba(19, 128, 107, .08)); }
.das-tindakan-angka { font-size: 17px; font-weight: 800; color: var(--eq-judul, #0F211E); text-align: right; }
.das-tindakan-baris strong { display: block; font-size: 13px; font-weight: 650; color: var(--eq-judul, #0F211E); overflow-wrap: anywhere; }
.das-tindakan-baris small { display: block; font-size: 11.5px; line-height: 1.4; color: var(--eq-redup, #5F6E6A); overflow-wrap: anywhere; }
.das-tindakan-porsi { font-size: 12px; font-weight: 600; color: var(--eq-redup, #5F6E6A); }

.das-minggu { margin-top: 16px; padding-top: 14px; border-top: 1px dashed var(--das-garis-2); }
.das-minggu-judul { font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--eq-redup, #5F6E6A); }
.das-minggu-isi { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.das-chip {
  display: inline-flex; align-items: baseline; gap: 6px; padding: 6px 10px; border-radius: 999px;
  border: 1px solid var(--das-garis-2); font-size: 12px; color: var(--eq-judul, #0F211E);
  transition: border-color .15s, background-color .15s;
}
.das-chip:hover { border-color: var(--eq-aksen, #13806B); background: var(--eq-aksen-tipis, rgba(19, 128, 107, .08)); }
.das-chip b { font-weight: 800; }
.das-chip-dari { color: var(--eq-redup, #5F6E6A); }

/* ── KPI ── */
.das-kpi { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(min(100%, 160px), 1fr)); }
.das-kpi-kartu { position: relative; padding: 16px 18px 18px !important; }
.das-kpi-label { display: flex; align-items: center; gap: 7px; font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--eq-redup, #5F6E6A); }
.das-kpi-nilai { display: block; margin-top: 8px; font-size: 30px; font-weight: 800; line-height: 1; letter-spacing: -.03em; color: var(--eq-judul, #0F211E); }
.das-kpi-ket { margin-top: 6px; font-size: 11.5px; color: var(--eq-redup, #5F6E6A); }
.das-kpi-pita { display: block; height: 4px; margin-top: 12px; border-radius: 2px; background: var(--das-garis); overflow: hidden; }
.das-kpi-pita i { display: block; height: 100%; border-radius: 2px; }

/* ── alat: segmen & rentang ── */
.das-alat { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
.das-segmen { display: inline-flex; flex-wrap: wrap; gap: 2px; padding: 3px; border-radius: 12px; background: var(--das-garis); }
.das-segmen button {
  padding: 6px 12px; border-radius: 9px; font-size: 12.5px; font-weight: 600; white-space: nowrap;
  color: var(--eq-redup, #5F6E6A); transition: background-color .15s, color .15s;
}
.das-segmen button:hover { color: var(--eq-judul, #0F211E); }
.das-segmen button.kini { background: #FFFFFF; color: var(--eq-judul, #0F211E); box-shadow: 0 1px 3px rgba(7, 36, 32, .12); }
:root[data-tema="gelap"] .das-segmen button.kini { background: rgba(255, 255, 255, .12); }
.das-segmen-kecil button { padding: 4px 10px; font-size: 12px; }
.das-rentang { margin-left: auto; display: flex; flex-wrap: wrap; align-items: center; gap: 4px; }
.das-rentang span { margin-right: 4px; font-size: 11.5px; color: var(--eq-redup, #5F6E6A); }
.das-rentang button {
  padding: 4px 10px; border-radius: 8px; border: 1px solid var(--das-garis-2); font-size: 11.5px; font-weight: 600;
  color: var(--eq-redup, #5F6E6A); transition: border-color .15s, color .15s;
}
.das-rentang button:hover { color: var(--eq-judul, #0F211E); border-color: var(--eq-redup, #5F6E6A); }
.das-rentang button.kini { background: var(--eq-aksen, #13806B); border-color: transparent; color: #FFFFFF; }

/* ── grafik bertumpuk: perusahaan & modul ──
   Satu baris per butir: nama | batang | angka, angkanya rata kanan
   supaya dapat dibandingkan menuruni satu kolom. Di layar sempit nama
   dan angka naik ke atas batangnya. */
.das-tumpuk { display: grid; gap: 10px; }
.das-tumpuk-baris {
  display: grid; align-items: center; gap: 4px 12px;
  grid-template-columns: minmax(0, 1fr) auto;
  grid-template-areas: "nama angka" "jalur jalur";
}
.das-tumpuk-nama { grid-area: nama; font-size: 12px; font-weight: 650; color: var(--eq-judul, #0F211E); min-width: 0; overflow-wrap: anywhere; }
.das-tumpuk-jalur { grid-area: jalur; height: 10px; border-radius: 5px; background: var(--das-garis); overflow: hidden; }
.das-tumpuk-angka { grid-area: angka; font-size: 12px; color: var(--eq-redup, #5F6E6A); text-align: right; white-space: nowrap; }
.das-tumpuk-angka b { font-weight: 800; color: var(--eq-judul, #0F211E); }
.das-tumpuk-isi { display: flex; gap: 2px; height: 100%; }
.das-tumpuk-isi i { display: block; min-width: 3px; height: 100%; }
.das-tumpuk-tautan { margin: -3px -6px; padding: 3px 6px; border-radius: 8px; transition: background-color .15s; }
a.das-tumpuk-tautan:hover { background: var(--eq-aksen-tipis, rgba(19, 128, 107, .08)); }
@media (min-width: 640px) {
  .das-tumpuk-baris { grid-template-columns: minmax(0, 11rem) minmax(0, 1fr) 8.5rem; grid-template-areas: "nama jalur angka"; }
  .das-tumpuk-baris-sempit { grid-template-columns: minmax(0, 11rem) minmax(0, 1fr) 2.5rem; }
}
.das-legenda { display: flex; flex-wrap: wrap; gap: 6px 16px; margin-top: 4px; font-size: 11.5px; color: var(--eq-redup, #5F6E6A); }
.das-legenda span { display: inline-flex; align-items: center; gap: 6px; }
.das-legenda i { width: 10px; height: 10px; border-radius: 3px; }

/* ── grafik kecil bersusun ── */
.das-multipel { display: grid; gap: 14px; }
.das-multipel-kepala { display: flex; align-items: center; gap: 7px; margin-bottom: 2px; font-size: 12px; color: var(--eq-judul, #0F211E); }
.das-multipel-kepala i { width: 10px; height: 3px; border-radius: 2px; flex: none; }
.das-multipel-kepala b { font-weight: 700; }
.das-multipel-jumlah { margin-left: auto; font-weight: 700; color: var(--eq-redup, #5F6E6A); }

/* Cincin kepatuhan: daftar tegak — cincin di kiri, nama, dasar
   hitungan, dan pecahannya di kanan. Label bawaan cincin disembunyikan
   (namanya sudah ditulis di sebelahnya), tetapi tetap mengisi
   aria-label gambarnya. */
.das-cincin { display: grid; gap: 14px; }
.das-cincin-baris { display: grid; grid-template-columns: auto minmax(0, 1fr); align-items: center; gap: 14px; }
.das-cincin-baris + .das-cincin-baris { padding-top: 14px; border-top: 1px solid var(--das-garis); }
.das-cincin-gambar :deep(p) { display: none; }
.das-cincin-nama { font-size: 13px; font-weight: 700; color: var(--eq-judul, #0F211E); }
.das-cincin-ket { margin-top: 2px; font-size: 11.5px; line-height: 1.4; color: var(--eq-redup, #5F6E6A); }
.das-cincin-pecah { margin-top: 4px; font-size: 12px; font-weight: 650; color: var(--eq-judul, #0F211E); }
.das-kosong { padding: 24px 0; text-align: center; font-size: 12.5px; color: var(--eq-redup, #5F6E6A); }

/* ── tabel ── */
.das-tabel { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.das-tabel th {
  padding: 8px 12px 8px 0; text-align: left; font-size: 10.5px; font-weight: 700; letter-spacing: .06em;
  text-transform: uppercase; color: var(--eq-redup, #5F6E6A); border-bottom: 1px solid var(--das-garis-2); white-space: nowrap;
}
.das-tabel td { padding: 10px 12px 10px 0; vertical-align: middle; border-bottom: 1px solid var(--das-garis); color: var(--eq-judul, #0F211E); }
.das-tabel .kanan { text-align: right; }
.das-tabel .tebal { font-weight: 650; }
.das-redup { color: var(--eq-redup, #5F6E6A); }
.das-hanya-hp { display: none; }

.das-tabel-modul .das-sel-buka { width: 28px; padding-right: 4px; }
.das-tabel-modul .das-sel-ikon { width: 32px; padding-right: 8px; }
.das-tabel-modul .das-sel-nama { white-space: nowrap; }
.das-buka { display: inline-grid; place-items: center; width: 22px; height: 22px; border-radius: 6px; vertical-align: middle; color: var(--eq-redup, #5F6E6A); }
.das-buka:hover { background: var(--das-garis); color: var(--eq-judul, #0F211E); }
.das-buka svg { width: 14px; height: 14px; transition: transform .15s; }
.das-buka svg.putar { transform: rotate(90deg); }
.das-modul-ikon {
  display: inline-grid; place-items: center; width: 24px; height: 24px; border-radius: 7px;
  vertical-align: middle; color: var(--eq-aksen-gelap, #0F6B59); background: var(--eq-aksen-lembut, #E3F1EC);
}
:root[data-tema="gelap"] .das-modul-ikon { color: #A9CF5E; background: rgba(169, 207, 94, .14); }
.das-status {
  display: inline-flex; align-items: center; gap: 6px; padding: 3px 9px 3px 7px; border-radius: 999px; white-space: nowrap;
  font-size: 11.5px; font-weight: 650; color: var(--st);
  background: color-mix(in srgb, var(--s) 10%, transparent);
}
:root[data-tema="gelap"] .das-status { background: color-mix(in srgb, var(--s) 16%, transparent); }
.das-status i { width: 7px; height: 7px; border-radius: 50%; background: var(--s); }
.das-rincian { display: flex; flex-wrap: wrap; gap: 4px 14px; }
.das-rincian b { font-weight: 800; }
.das-tautan { font-size: 12px; font-weight: 700; color: var(--eq-aksen-gelap, #0F6B59); }
.das-tautan:hover { text-decoration: underline; }
:root[data-tema="gelap"] .das-tautan { color: #A9CF5E; }
.das-sub td { background: color-mix(in srgb, var(--eq-aksen, #13806B) 4%, transparent); }
.das-butir { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 260px), 1fr)); gap: 6px 24px; padding: 4px 0 4px 64px; }
.das-butir li { display: flex; align-items: baseline; gap: 8px; font-size: 12px; }
.das-butir li span { flex: 1; min-width: 0; color: var(--eq-judul, #0F211E); }
.das-butir small { color: var(--eq-redup, #5F6E6A); }

.das-modul-kepala { flex-wrap: wrap; gap: 10px; }

:focus-visible { outline: 2px solid var(--eq-aksen, #13806B); outline-offset: 2px; }

@media (max-width: 640px) {
  .das-ringkas { width: 100%; display: grid; grid-template-columns: 1fr 1fr; }
  .das-ringkas > div { min-width: 0; padding: 8px 10px; }
  .das-ringkas > div + div { border-left: 0; }
  .das-ringkas > div:nth-child(even) { border-left: 1px solid var(--das-garis-2); }
  .das-ringkas > div:nth-child(n + 3) { border-top: 1px solid var(--das-garis-2); }
  .das-judul { font-size: 19px; }

  /* Tab bergulir mendatar dalam satu baris, bukan menumpuk tiga baris. */
  .das-segmen-tab { flex-wrap: nowrap; max-width: 100%; overflow-x: auto; scrollbar-width: none; }
  .das-segmen-tab::-webkit-scrollbar { display: none; }
  .das-rentang { margin-left: 0; }
}

/* Tabel modul di layar sempit: dua baris ringkas per modul —
   ikon, nama, keadaan, angka; lalu rincian dan tautannya. */
@media (max-width: 767.98px) {
  .das-tabel-modul thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
  .das-tabel-modul, .das-tabel-modul tbody { display: block; width: 100%; }
  .das-tabel-modul tr.das-modul-baris {
    display: grid; align-items: center; column-gap: 0; row-gap: 6px;
    grid-template-columns: 26px 30px minmax(0, 1fr) auto auto;
    grid-template-areas: "buka ikon nama status angka" ". . rincian rincian tautan";
    padding: 10px 0; border-bottom: 1px solid var(--das-garis);
  }
  /* Satu pola pemilih untuk semua sel (tr.das-modul-baris > td.…):
     aturan sel yang lebih umum di atasnya tidak boleh menang hanya
     karena lebih panjang — begitulah sel catatan sempat muncul sebagai
     "—" di awal baris kedua. */
  .das-tabel-modul tr.das-modul-baris > td { display: block; padding: 0; border: 0; width: auto; }
  .das-tabel-modul tr.das-modul-baris > td.das-sel-buka { grid-area: buka; }
  .das-tabel-modul tr.das-modul-baris > td.das-sel-ikon { grid-area: ikon; }
  .das-tabel-modul tr.das-modul-baris > td.das-sel-nama { grid-area: nama; white-space: normal; padding-right: 8px; }
  .das-tabel-modul tr.das-modul-baris > td.das-sel-status { grid-area: status; padding-right: 8px; }
  .das-tabel-modul tr.das-modul-baris > td.das-sel-angka { grid-area: angka; min-width: 1.8rem; }
  .das-tabel-modul tr.das-modul-baris > td.das-sel-rincian { grid-area: rincian; font-size: 12px; }
  .das-tabel-modul tr.das-modul-baris > td.das-sel-tautan { grid-area: tautan; padding-left: 8px; }
  .das-tabel-modul tr.das-modul-baris > td.das-sel-kabar { display: none; }
  .das-tabel-modul .das-hanya-hp { display: inline; }
  .das-tabel-modul tr.das-sub, .das-tabel-modul tr.das-kosong-baris { display: block; }
  .das-tabel-modul tr.das-sub > td, .das-tabel-modul tr.das-kosong-baris > td { display: block; padding: 8px 0; }
  .das-butir { padding-left: 56px; }
}
</style>

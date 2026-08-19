<script setup lang="ts">
/**
 * Dasbor menyeluruh — keadaan seluruh situs dalam satu layar.
 *
 * TERPISAH DARI DASBOR PEMBELAJARAN. Halaman /dashboard menjawab
 * pertanyaan seorang peserta tentang kursusnya sendiri; halaman ini
 * menjawab pertanyaan seorang pengawas tentang situsnya.
 *
 * SUSUNANNYA MENGIKUTI URUTAN MENDESAKNYA: apa yang harus dikerjakan
 * hari ini, lalu bagaimana keadaan keseluruhannya, lalu rinciannya per
 * modul. Dasbor yang disusun menurut nama modul memaksa pembacanya
 * menyaring sendiri tiap pagi — pekerjaan yang diulang tiap orang,
 * dengan hasil yang berbeda tiap kali.
 */
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import KartuGrafik from '../../Grafik/KartuGrafik.vue';
import Batang from '../../Grafik/Batang.vue';
import Donat from '../../Grafik/Donat.vue';
import Garis from '../../Grafik/Garis.vue';
import Cincin from '../../Grafik/Cincin.vue';
import Legenda from './Legenda.vue';

const props = propHalaman();

/* Warna pita masa berlaku — sama persis dengan halaman pemantauan.
   Dua skala warna untuk satu makna membuat orang membaca kuning di sini
   sebagai sesuatu yang lain di sana. */
const WARNA_PITA: Record<string, string> = {
  habis: '#DC2626', mendesak: '#EA580C', dekat: '#CA8A04', panjang: '#16A34A',
};

/*
 * Yang menuntut tindakan hari ini, dipisahkan dari sisanya.
 *
 * Dua puluh satu angka berderet menuntut pembacanya membandingkan
 * sendiri mana yang penting. Yang gawat dan berisi diangkat ke atas;
 * sisanya tetap ada, hanya tidak berebut perhatian.
 */
const mendesak = computed<any[]>(() =>
  (props.modul ?? []).filter((m: any) => m.nada === 'gawat' && m.nilai > 0));

const perluDiurus = computed<any[]>(() =>
  (props.modul ?? []).filter((m: any) => m.nada === 'ingat' && m.nilai > 0));

/* Nol TIDAK disembunyikan: modul yang menghilang saat semuanya beres
   membuat orang tidak dapat membedakan "tidak ada masalah" dari "tidak
   ada datanya", dan kedua keadaan itu menuntut tindakan berbeda. */
const tenang = computed<any[]>(() =>
  (props.modul ?? []).filter((m: any) =>
    !(m.nada === 'gawat' && m.nilai > 0) && !(m.nada === 'ingat' && m.nilai > 0)));

const jumlahMendesak = computed(() =>
  mendesak.value.reduce((n, m) => n + m.nilai, 0));

const pita = computed(() => (props.kepatuhanBerkas ?? [])
  .filter((b: any) => b.nilai > 0)
  .map((b: any) => ({ label: b.label, nilai: b.nilai, warna: WARNA_PITA[b.kode] })));

const totalBerkas = computed<number>(() =>
  (props.kepatuhanBerkas ?? []).reduce((n: number, b: any) => n + b.nilai, 0));

const perusahaan = computed(() => (props.perPerusahaan ?? [])
  .map((c: any) => ({ label: c.label, nilai: c.nilai })));

const angka = (n: number | null | undefined) =>
  n === null || n === undefined ? '—' : new Intl.NumberFormat('id-ID').format(n);

/* ── tab ──
   Dua puluh grafik pada satu gulungan menuntut orang menggulir mencari
   yang dicarinya, tiap kali. Dikelompokkan menurut pertanyaan yang
   dijawabnya, bukan menurut modul asalnya: "berapa produksi kemarin"
   dan "berapa solar yang terbakar" adalah satu pertanyaan yang sama. */
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
   yang sama pula. */
function pilihHari(h: number) {
  router.get('/dasbor', { hari: h }, { preserveScroll: true, preserveState: true });
}

const g = computed<any>(() => props.grafik ?? {});

/* ── deret keselamatan ──
   Garis, bukan batang: batang bernilai nol tidak menggambar apa-apa,
   sehingga bulan sepi terbaca sebagai daftar angka berderet, bukan
   grafik. Garis tetap menggambar dasarnya, dan nol terlihat sebagai
   nol. */
const hazardLabel = computed<string[]>(() => g.value.keselamatan?.hazardBulanan?.label ?? []);
const hazardDeret = computed(() => [
  { nama: 'Masuk',   nilai: g.value.keselamatan?.hazardBulanan?.masuk ?? [], warna: '#C47000' },
  { nama: 'Ditutup', nilai: g.value.keselamatan?.hazardBulanan?.tutup ?? [], warna: '#16A34A' },
]);

/* ── deret produksi ── */
const prod = computed<any>(() => g.value.produksi?.harian ?? {});

const deretProduksi = computed(() => [
  { nama: 'Batubara (ton)', nilai: prod.value.ton ?? [], warna: '#C47000' },
  { nama: 'Overburden (bcm)', nilai: prod.value.ob ?? [], warna: '#0EA5E9' },
]);

const deretNisbah = computed(() => [
  { nama: 'Nisbah kupas (bcm/ton)', nilai: prod.value.nisbah ?? [], warna: '#7C3AED' },
]);

const deretJam = computed(() => [
  { nama: 'Jam operasi', nilai: prod.value.jam ?? [], warna: '#16A34A' },
  { nama: 'Jam delay',   nilai: prod.value.delay ?? [], warna: '#DC2626' },
]);

const angkut = computed<any>(() => g.value.produksi?.angkutan ?? {});
const deretAngkut = computed(() => [
  { nama: 'Tonase tertimbang', nilai: angkut.value.ton ?? [], warna: '#C47000' },
]);

const energi = computed<any>(() => g.value.produksi?.energi ?? {});
const deretEnergi = computed(() => [
  { nama: 'Solar (liter)', nilai: energi.value.liter ?? [], warna: '#EA580C' },
  { nama: 'Jam idle',      nilai: energi.value.idle ?? [], warna: '#94A3B8' },
]);

/* ── deret air ── */
const air = computed<any>(() => g.value.lingkungan?.air ?? {});
const deretAir = computed(() => [
  { nama: 'Curah hujan (mm)', nilai: air.value.hujan ?? [], warna: '#0EA5E9' },
  { nama: 'Dipompa (m³)',     nilai: air.value.pompa ?? [], warna: '#7C3AED' },
]);

/* ── deret biaya ── */
const biaya = computed<any>(() => g.value.aset?.biayaBulanan ?? {});
const deretBiaya = computed(() => [
  { nama: 'Realisasi (Rp)', nilai: biaya.value.rp ?? [], warna: '#C47000' },
]);

/* ── sertifikat jatuh tempo ── */
const sertifikat = computed(() => {
  const x = g.value.orang?.sertifikat;
  return (x?.label ?? []).map((l: string, i: number) => ({ label: l, nilai: x.nilai[i] }));
});

/* Empat angka pembuka. Ikonnya dipilih supaya bentuknya berbeda satu
   sama lain bahkan pada layar kecil — orang, tanda seru, jam, gedung —
   sebab empat kartu seukuran dengan ikon serupa dibedakan hanya oleh
   teksnya, dan teks kecil itu yang justru tidak terbaca sekilas. */
const kpi = computed(() => [
  { label: 'Tenaga kerja', nilai: props.ringkas?.manpower, warna: '#0F1720',
    ket: `${props.ringkas?.manpowerAktif ?? 0} aktif`,
    ikon: 'M16 20v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V20M9.5 10.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM21 20v-1.5a4 4 0 0 0-3-3.87' },

  { label: 'Berkas sudah habis', nilai: props.ringkas?.berkasHabis, warna: '#DC2626',
    ket: 'tidak boleh masuk hari ini',
    ikon: 'M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z' },

  { label: 'Mendekati habis', nilai: props.ringkas?.berkasDekat, warna: '#CA8A04',
    ket: '≤ 60 hari lagi',
    ikon: 'M12 7v5l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z' },

  { label: 'Perusahaan', nilai: props.ringkas?.perusahaan, warna: '#0EA5E9',
    ket: 'punya pemegang berkas',
    ikon: 'M3 21h18M5 21V7l7-4 7 4v14M9 21v-4h6v4M9 11h.01M15 11h.01' },
]);

/** Ada isinya? Grafik kosong lebih baik diganti kalimat. */
const berisi = (baris: any[] | undefined) =>
  Array.isArray(baris) && baris.some((b) => (b?.nilai ?? 0) > 0);
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1400px] mx-auto space-y-5">

    <!-- ═══ 1 · apa yang harus dikerjakan hari ini ═══ -->
    <section v-if="mendesak.length" class="rounded-2xl border p-4 sm:p-5"
             style="background:#FEF2F2;border-color:#FCA5A5">
      <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
        <h3 class="text-[15px] font-bold" style="color:#B91C1C">
          {{ jumlahMendesak }} hal menuntut tindakan hari ini
        </h3>
        <span class="text-[12px]" style="color:#B91C1C;opacity:.85">
          tersebar di {{ mendesak.length }} modul
        </span>
      </div>

      <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
        <Link v-for="m in mendesak" :key="m.nama" :href="m.url"
              class="flex items-center gap-3 rounded-xl bg-white/70 px-3 py-2.5 transition hover:bg-white">
          <strong class="num text-xl" style="color:#B91C1C;min-width:2.2rem">{{ m.nilai }}</strong>
          <span class="min-w-0">
            <span class="block text-[12.5px] font-semibold text-cam-ink truncate">{{ m.nama }}</span>
            <small class="block text-[11px] text-stone-500 truncate">{{ m.ket }}</small>
          </span>
        </Link>
      </div>
    </section>

    <section v-else class="rounded-2xl border px-5 py-4"
             style="background:#F0FDF4;border-color:#86EFAC">
      <h3 class="text-[14px] font-bold" style="color:#15803D">Tidak ada yang lewat batas hari ini.</h3>
      <p class="text-[12px] mt-0.5" style="color:#15803D;opacity:.85">
        Yang perlu dijadwalkan minggu ini ada di bawah.
      </p>
    </section>

    <!-- ═══ 2 · pemilih tab dan rentang ═══ -->
    <section class="flex flex-wrap items-center gap-2">
      <div class="flex flex-wrap gap-1.5">
        <button v-for="t in TAB" :key="t.kode" type="button"
                class="rounded-xl px-3 py-1.5 text-[12px] font-semibold border transition"
                :class="tab === t.kode
                  ? 'bg-cam-lime-deep text-white border-transparent'
                  : 'bg-white text-stone-600 border-stone-200 hover:border-stone-300'"
                @click="tab = t.kode">{{ t.nama }}</button>
      </div>

      <!--
        Rentang hanya mengubah grafik bertren. Angka sebaran — status
        objek, kategori barang — tidak punya waktu, dan menyaringnya
        menurut tanggal akan membuang barang yang dicatat tahun lalu
        tetapi masih ada di rak hari ini.
      -->
      <div class="ml-auto flex items-center gap-1.5">
        <span class="text-[11.5px] text-stone-400">Rentang tren</span>
        <button v-for="h in props.opsiHari ?? []" :key="h" type="button"
                class="rounded-lg px-2.5 py-1 text-[11.5px] font-semibold border transition num"
                :class="props.hari === h
                  ? 'bg-cam-ink text-white border-transparent'
                  : 'bg-white text-stone-600 border-stone-200 hover:border-stone-300'"
                @click="pilihHari(h)">{{ h }} hari</button>
      </div>
    </section>

    <!-- ═══ 3 · angka pembuka ═══ -->
    <!--
      Pita warna di tepi kiri tiap kartu, bukan angka berwarna saja.
      Empat angka besar berderet dengan warna berbeda membuat warnanya
      terbaca sebagai hiasan; pita tegak di tepi memberi tiap kartu
      penanda yang sama tebalnya, sehingga warnanya kembali berarti
      tingkat kemendesakan.
    -->
    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <div v-for="k in kpi" :key="k.label"
           class="relative overflow-hidden rounded-2xl bg-white border border-stone-100 shadow-card p-4 pl-5">
        <i class="absolute left-0 top-0 bottom-0 w-[5px]" aria-hidden="true"
           :style="{ background: `linear-gradient(180deg, ${k.warna}, ${k.warna}80)` }"></i>

        <div class="flex items-start justify-between gap-2">
          <p class="text-[11px] font-bold uppercase tracking-wide text-stone-400">{{ k.label }}</p>
          <span class="grid place-items-center rounded-xl w-8 h-8 shrink-0"
                :style="{ background: `${k.warna}14`, color: k.warna }" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path :d="k.ikon" />
            </svg>
          </span>
        </div>

        <strong class="num block text-[28px] leading-none mt-1.5" :style="{ color: k.warna }">
          {{ angka(k.nilai) }}
        </strong>
        <p class="text-[11px] text-stone-400 mt-1">{{ k.ket }}</p>
      </div>
    </section>

    <!-- ═══ 4 · grafik, per tab ═══ -->
    <div v-if="tab === 'ringkas'" class="grid gap-4 lg:grid-cols-2">
      <KartuGrafik judul="Kepatuhan berkas kelayakan"
                   :angka="String(totalBerkas)"
                   catatan="MCU, Mine Permit, dan SIMPER — dihitung atas tanggal yang benar-benar berlaku.">
        <Donat v-if="pita.length" :bagian="pita"
               :tengah="String(props.ringkas?.berkasHabis ?? 0)" tengah-label="sudah habis" />
        <p v-else class="text-[12.5px] text-stone-500 py-6 text-center">Belum ada berkas yang tercatat.</p>
      </KartuGrafik>

      <KartuGrafik judul="Tenaga kerja per perusahaan"
                   catatan="Jumlah ORANG, bukan jumlah berkas — satu orang memegang beberapa.">
        <Batang v-if="perusahaan.length" :baris="perusahaan" satuan="orang" />
        <p v-else class="text-[12.5px] text-stone-500 py-6 text-center">Belum ada data perusahaan.</p>
      </KartuGrafik>
    </div>

    <KartuGrafik v-if="tab === 'ringkas'" judul="Kepatuhan"
                 catatan="Persen, bukan jumlah — dua belas objek lewat jadwal berarti berbeda pada situs dengan 15 objek dan pada situs dengan 400.">
      <div class="grid gap-5 md:grid-cols-3">
        <!--
          Cincin, bukan jalur mendatar. Angkanya berada di tengah
          bentuknya sendiri — satu benda untuk dibaca, bukan angka di
          satu sisi dan bentuk di sisi lain — dan takik pada jalurnya
          menunjukkan targetnya, sehingga "72%" dapat dinilai tanpa
          membaca keterangan di bawahnya.
        -->
        <div v-for="m in props.meter ?? []" :key="m.nama" class="text-center">
          <Cincin :nilai="m.nilai" :maks="100" satuan="%" :target="90" :label="m.nama" />
          <p class="text-[11px] text-stone-400 mt-1 num">{{ m.baik }} dari {{ m.dari }}</p>
          <p class="text-[11px] text-stone-400">{{ m.ket }}</p>
        </div>
      </div>
    </KartuGrafik>

    <!-- ── tab: keselamatan ── -->
    <template v-if="tab === 'keselamatan'">
      <KartuGrafik judul="Laporan bahaya per bulan"
                   catatan="Masuk dan ditutup berdampingan: laporan yang naik dapat berarti budaya lapor membaik ATAU keadaan memburuk — yang membedakannya berapa yang ditutup.">
        <Garis :label="hazardLabel" :deret="hazardDeret" satuan="laporan" :tinggi="200" />
        <Legenda :deret="hazardDeret" />
      </KartuGrafik>

      <div class="grid gap-4 lg:grid-cols-2">
        <KartuGrafik judul="Bahaya menurut tingkat risiko"
                     catatan="Yang menentukan urutan penanganan, bukan jumlahnya.">
          <Batang v-if="berisi(g.keselamatan?.hazardRisiko)" :baris="g.keselamatan.hazardRisiko" satuan="laporan" />
          <p v-else class="text-[12.5px] text-stone-500 py-6 text-center">Belum ada laporan bahaya.</p>
        </KartuGrafik>

        <KartuGrafik judul="Bahaya menurut kategori"
                     catatan="Enam kategori terbanyak — sisanya berekor panjang dan tidak mengubah keputusan.">
          <Batang v-if="berisi(g.keselamatan?.hazardKategori)" :baris="g.keselamatan.hazardKategori" satuan="laporan" />
          <p v-else class="text-[12.5px] text-stone-500 py-6 text-center">Belum ada laporan bahaya.</p>
        </KartuGrafik>
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <KartuGrafik judul="Inspeksi menurut status">
          <Donat v-if="berisi(g.keselamatan?.inspeksiStatus)" :bagian="g.keselamatan.inspeksiStatus" />
          <p v-else class="text-[12.5px] text-stone-500 py-6 text-center">Belum ada inspeksi.</p>
        </KartuGrafik>

        <KartuGrafik judul="Temuan audit SMKP menurut status">
          <Donat v-if="berisi(g.keselamatan?.temuanStatus)" :bagian="g.keselamatan.temuanStatus" />
          <p v-else class="text-[12.5px] text-stone-500 py-6 text-center">Belum ada temuan audit.</p>
        </KartuGrafik>
      </div>
    </template>

    <!-- ── tab: produksi dan energi ── -->
    <template v-if="tab === 'produksi'">
      <KartuGrafik :judul="`Produksi ${props.hari} hari terakhir`"
                   catatan="Hari tanpa catatan digambar sebagai nol, bukan dilewati — garis yang melompati hari kosong membuat tambang berhenti terbaca sebagai penurunan bertahap.">
        <Garis :label="prod.label ?? []" :deret="deretProduksi" :tinggi="200" />
        <Legenda :deret="deretProduksi" />
      </KartuGrafik>

      <div class="grid gap-4 lg:grid-cols-2">
        <KartuGrafik judul="Nisbah kupas harian"
                     catatan="Dihitung per hari dari kedua angkanya, bukan dari totalnya: nisbah rata-rata sebulan menyembunyikan hari pengupasan berat yang justru menentukan biayanya. Hari tanpa produksi tidak punya nisbah — dan digambar sebagai putus, bukan nol.">
          <Garis :label="prod.label ?? []" :deret="deretNisbah" satuan="bcm/ton" :tinggi="180" bidang />
        </KartuGrafik>

        <KartuGrafik judul="Jam operasi dan jam delay"
                     catatan="Berdampingan karena yang penting perbandingannya, bukan besarnya masing-masing.">
          <Garis :label="prod.label ?? []" :deret="deretJam" satuan="jam" :tinggi="180" />
          <Legenda :deret="deretJam" />
        </KartuGrafik>
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <KartuGrafik judul="Tonase tertimbang" catatan="Dari jembatan timbang, bukan dari catatan shift.">
          <Garis :label="angkut.label ?? []" :deret="deretAngkut" satuan="ton" :tinggi="180" bidang />
        </KartuGrafik>

        <KartuGrafik judul="Solar terpakai dan jam idle"
                     catatan="Idle yang tinggi bersama solar yang tinggi berarti bahan bakar terbakar tanpa memindahkan apa pun.">
          <Garis :label="energi.label ?? []" :deret="deretEnergi" :tinggi="180" />
          <Legenda :deret="deretEnergi" />
        </KartuGrafik>
      </div>
    </template>

    <!-- ── tab: air dan lingkungan ── -->
    <template v-if="tab === 'lingkungan'">
      <KartuGrafik judul="Curah hujan dan air yang dipompa"
                   catatan="Berdampingan karena yang satu menjelaskan yang lain: pemompaan yang naik tanpa hujan berarti ada rembesan.">
        <Garis :label="air.label ?? []" :deret="deretAir" :tinggi="200" />
        <Legenda :deret="deretAir" />
      </KartuGrafik>

      <div class="grid gap-4 lg:grid-cols-2">
        <KartuGrafik judul="Hasil pantau terhadap baku mutu"
                     catatan="Dihitung terhadap ambang yang berlaku SEKARANG — baku mutu berubah, dan pelanggaran yang dihitung sekali akan menyebut angka lama selamanya.">
          <Donat v-if="berisi(g.lingkungan?.bakuMutu)" :bagian="g.lingkungan.bakuMutu" />
          <p v-else class="text-[12.5px] text-stone-500 py-6 text-center">Belum ada hasil pantau.</p>
        </KartuGrafik>

        <KartuGrafik judul="Dokumen menurut status"
                     catatan="Dokumen yang tidak berstatus berlaku tidak boleh dipakai di lapangan.">
          <Donat v-if="berisi(g.orang?.dokumenStatus)" :bagian="g.orang.dokumenStatus" />
          <p v-else class="text-[12.5px] text-stone-500 py-6 text-center">Belum ada dokumen.</p>
        </KartuGrafik>
      </div>
    </template>

    <!-- ── tab: aset dan biaya ── -->
    <template v-if="tab === 'aset'">
      <div class="grid gap-4 lg:grid-cols-2">
        <KartuGrafik judul="Objek keselamatan operasi menurut status">
          <Donat v-if="berisi(g.aset?.koStatus)" :bagian="g.aset.koStatus" />
          <p v-else class="text-[12.5px] text-stone-500 py-6 text-center">Belum ada objek terdaftar.</p>
        </KartuGrafik>

        <KartuGrafik judul="Work order menurut status">
          <Donat v-if="berisi(g.aset?.workOrderStatus)" :bagian="g.aset.workOrderStatus" />
          <p v-else class="text-[12.5px] text-stone-500 py-6 text-center">Belum ada work order.</p>
        </KartuGrafik>
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <KartuGrafik judul="Barang gudang menurut kategori">
          <Batang v-if="berisi(g.aset?.gudangKategori)" :baris="g.aset.gudangKategori" satuan="jenis" />
          <p v-else class="text-[12.5px] text-stone-500 py-6 text-center">Belum ada barang terdaftar.</p>
        </KartuGrafik>

        <KartuGrafik judul="Realisasi biaya per bulan"
                     catatan="Tahun berjalan. Bulan yang belum tercatat digambar nol supaya bentuk serapannya terlihat utuh.">
          <Garis :label="biaya.label ?? []" :deret="deretBiaya" satuan="Rp" :tinggi="180" bidang />
        </KartuGrafik>
      </div>
    </template>

    <!-- ── tab: kelayakan orang ── -->
    <template v-if="tab === 'orang'">
      <div class="grid gap-4 lg:grid-cols-2">
        <KartuGrafik judul="Hasil MCU terakhir tiap orang"
                     catatan="Yang TERAKHIR saja — memasukkan seluruh riwayat membuat orang yang pernah unfit lalu pulih tetap terhitung unfit selamanya.">
          <Donat v-if="berisi(g.orang?.mcuHasil)" :bagian="g.orang.mcuHasil" />
          <p v-else class="text-[12.5px] text-stone-500 py-6 text-center">Belum ada hasil MCU.</p>
        </KartuGrafik>

        <KartuGrafik judul="Sertifikat kompetensi jatuh tempo"
                     catatan="Enam bulan ke depan, dikelompokkan per bulan: yang menumpuk menentukan kapan pelatihan ulang harus dijadwalkan, dan jadwal pelatihan disusun berbulan-bulan sebelumnya.">
          <Batang v-if="berisi(sertifikat)" :baris="sertifikat" apa-adanya satuan="sertifikat" />
          <p v-else class="text-[12.5px] text-stone-500 py-6 text-center">
            Tidak ada sertifikat yang jatuh tempo enam bulan ke depan.
          </p>
        </KartuGrafik>
      </div>

      <KartuGrafik judul="Kepatuhan berkas per perusahaan"
                   catatan="Diurutkan dari yang paling banyak masalahnya — itu yang perlu ditelepon lebih dulu.">
        <div class="overflow-x-auto">
          <table class="min-w-full text-left text-[12px]">
            <thead>
              <tr class="text-stone-400 border-b border-stone-100">
                <th class="py-2 pr-3 font-semibold">Perusahaan</th>
                <th class="py-2 pr-3 font-semibold text-right">Tenaga kerja</th>
                <th class="py-2 pr-3 font-semibold text-right">Mendekati</th>
                <th class="py-2 font-semibold text-right">Habis</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in props.perPerusahaan ?? []" :key="c.label" class="border-b border-stone-50">
                <td class="py-2 pr-3 font-semibold text-cam-ink">{{ c.label }}</td>
                <td class="py-2 pr-3 num text-right">{{ c.nilai }}</td>
                <td class="py-2 pr-3 num text-right" style="color:#A16207">{{ c.mendekati || '—' }}</td>
                <td class="py-2 num text-right font-bold"
                    :style="{ color: c.habis ? '#B91C1C' : '#A8A29E' }">{{ c.habis || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </KartuGrafik>
    </template>

    <!-- ═══ 4 · rincian per modul ═══ -->
    <section v-if="perluDiurus.length" class="eq-panel">
      <div class="eq-panel-kepala">
        <h3>Perlu diurus minggu ini</h3>
        <span class="eq-panel-ket">Belum melewati batas, tetapi tidak akan selesai sendiri.</span>
      </div>
      <div class="eq-modul">
        <Link v-for="item in perluDiurus" :key="item.nama" :href="item.url">
          <span class="eq-modul-atas">
            <span class="eq-modul-nilai" :style="{ color: item.warna }">{{ item.nilai }}</span>
            <span class="eq-modul-ikon" :style="{ background: `${item.warna}18`, color: item.warna }">
              <svg v-if="item.ikon" viewBox="0 0 24 24" width="18" height="18" fill="none"
                   stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" :d="item.ikon" />
              </svg>
            </span>
          </span>
          <strong>{{ item.nama }}</strong>
          <small>{{ item.ket }}<template v-if="item.total > 0"> · dari {{ item.total }}</template></small>
        </Link>
      </div>
    </section>

    <section class="eq-panel">
      <div class="eq-panel-kepala">
        <h3>Seluruh modul</h3>
        <span class="eq-panel-ket">Nol berarti tidak ada yang tertunggak — bukan tidak ada datanya.</span>
      </div>
      <div class="eq-modul">
        <Link v-for="item in tenang" :key="item.nama" :href="item.url">
          <span class="eq-modul-atas">
            <span class="eq-modul-nilai"
                  :style="{ color: item.nilai ? item.warna : '#16A34A' }">{{ item.nilai }}</span>
            <span class="eq-modul-ikon"
                  :style="{ background: `${item.nilai ? item.warna : '#16A34A'}18`,
                            color: item.nilai ? item.warna : '#16A34A' }">
              <svg v-if="item.ikon" viewBox="0 0 24 24" width="18" height="18" fill="none"
                   stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" :d="item.ikon" />
              </svg>
            </span>
          </span>
          <strong>{{ item.nama }}</strong>
          <small>{{ item.ket }}<template v-if="item.total > 0"> · dari {{ item.total }}</template></small>
        </Link>
      </div>
    </section>
  </div>
</template>

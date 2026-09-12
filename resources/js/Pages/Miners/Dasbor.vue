<script setup lang="ts">
/**
 * Ringkasan Miners.
 *
 * Menjawab satu pertanyaan lebih dahulu: berapa orang yang hari ini
 * TIDAK boleh masuk, dan karena apa. Ubin yang menyebut total pekerja
 * tanpa itu hanya memberi tahu ukuran perusahaan — yang sudah diketahui
 * setiap orang yang membukanya.
 */
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { propHalaman } from '../../halaman';
import KartuGrafik from '../../Grafik/KartuGrafik.vue';
import Batang from '../../Grafik/Batang.vue';
import Lencana from './Lencana.vue';

const props = propHalaman();

const JENIS: [string, string][] = [
  ['mcu', 'MCU'],
  ['induksi', 'Induksi'],
  ['permit', 'Mine Permit'],
  ['simper', 'SIMPER'],
];

const mendesak = computed<any[]>(() => (props.mendesak ?? []) as any[]);
const kompetensi = computed<any[]>(() => (props.kompetensiHabis ?? []) as any[]);

/**
 * Satu batang per jenis berkas: berapa orang yang TIDAK lengkap.
 *
 * Yang digambar bukan seluruh sebarannya melainkan sisi yang menuntut
 * pekerjaan — habis ditambah belum ada. Menggambar keempat keadaan
 * berdampingan membuat batang "berlaku" menguasai skalanya, dan yang
 * harus diurus menyusut menjadi garis setipis rambut di ujungnya.
 * Sebaran lengkapnya tetap ada, di tabel yang menyertai grafik ini.
 */
const perluDiurus = computed(() =>
  JENIS.map(([kode, nama]) => {
    const r = props.ringkas?.[kode] ?? {};
    const n = Number(r.habis ?? 0) + Number(r.belum ?? 0);

    return {
      label: nama,
      nilai: n,
      keadaan: (n === 0 ? 'baik' : Number(r.habis ?? 0) > 0 ? 'gawat' : 'ingat') as
        'baik' | 'gawat' | 'ingat',
    };
  }));

/**
 * Berapa orang yang hari ini tertahan.
 *
 * Yang TERBANYAK di antara keempat jenis, bukan jumlahnya. Satu orang
 * yang MCU dan permitnya sama-sama habis akan terhitung dua kali oleh
 * penjumlahan — dan angka yang lebih besar daripada jumlah pekerja
 * adalah angka yang membuat seluruh ubin berhenti dipercaya.
 */
const tertahan = computed(() =>
  Math.max(0, ...perluDiurus.value.map((b) => b.nilai)));

const antrean = computed(() => props.antrean ?? {});
const jumlah  = computed(() => props.jumlah ?? {});
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1400px] mx-auto space-y-5">

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <Link href="/miners/kedaluwarsa"
            class="rounded-2xl bg-white border border-stone-100 shadow-card p-4 hover:border-stone-200">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-stone-400">Perlu diurus</div>
        <div class="num text-2xl font-bold text-cam-ink mt-1">{{ tertahan }}</div>
        <div class="text-[11px] text-stone-500">berkas habis atau belum ada</div>
      </Link>

      <Link href="/miners"
            class="rounded-2xl bg-white border border-stone-100 shadow-card p-4 hover:border-stone-200">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-stone-400">Pekerja aktif</div>
        <div class="num text-2xl font-bold text-cam-ink mt-1">{{ jumlah.pekerjaAktif ?? 0 }}</div>
        <div class="text-[11px] text-stone-500">dari {{ jumlah.pekerja ?? 0 }} terdaftar</div>
      </Link>

      <Link href="/miners/permit"
            class="rounded-2xl bg-white border border-stone-100 shadow-card p-4 hover:border-stone-200">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-stone-400">Mine Permit terbit</div>
        <div class="num text-2xl font-bold text-cam-ink mt-1">{{ jumlah.permitTerbit ?? 0 }}</div>
        <div class="text-[11px] text-stone-500">kartu masuk tambang</div>
      </Link>

      <Link href="/miners/simper"
            class="rounded-2xl bg-white border border-stone-100 shadow-card p-4 hover:border-stone-200">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-stone-400">SIMPER terbit</div>
        <div class="num text-2xl font-bold text-cam-ink mt-1">{{ jumlah.simperTerbit ?? 0 }}</div>
        <div class="text-[11px] text-stone-500">izin mengemudikan unit</div>
      </Link>
    </section>

    <KartuGrafik judul="Berkas yang perlu diurus"
                 catatan="Habis atau belum ada. Dihitung atas seluruh pekerja, bukan atas yang tersaring — ringkasan yang mengikuti penyaring akan menyebut 100% habis begitu seseorang memilih &quot;habis&quot;."
                 :angka="`${tertahan} orang terbanyak`">
      <Batang :baris="perluDiurus" satuan=" orang" apa-adanya />

      <template #tabel>
        <table>
          <thead>
            <tr>
              <th>Berkas</th>
              <th v-for="(label, kode) in (props.KEADAAN ?? {})" :key="kode">{{ label }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="[kode, nama] in JENIS" :key="kode">
              <td>{{ nama }}</td>
              <td v-for="(label, k) in (props.KEADAAN ?? {})" :key="k" class="num">
                {{ props.ringkas?.[kode]?.[k] ?? 0 }}
              </td>
            </tr>
          </tbody>
        </table>
      </template>
    </KartuGrafik>

    <section class="grid gap-4 lg:grid-cols-2">
      <div class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <header class="px-5 py-3.5 border-b border-stone-100">
          <h3 class="text-[13.5px] font-bold text-cam-ink">Menunggu tindakan</h3>
          <p class="text-[11px] text-stone-500 mt-0.5">
            Dokumen yang berhenti pada salah satu langkah persetujuan.
          </p>
        </header>

        <ul class="divide-y divide-stone-100">
          <li v-for="[kode, nama] in JENIS" :key="kode"
              class="px-5 py-3 flex items-center justify-between gap-3">
            <span class="text-[12px] text-stone-600">{{ nama }}</span>
            <span class="num text-[13px] font-bold"
                  :class="Number(antrean[kode] ?? 0) > 0 ? 'text-cam-ink' : 'text-stone-300'">
              {{ antrean[kode] ?? 0 }}
            </span>
          </li>
        </ul>
      </div>

      <div class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <header class="px-5 py-3.5 border-b border-stone-100">
          <h3 class="text-[13.5px] font-bold text-cam-ink">Paling mendesak</h3>
          <p class="text-[11px] text-stone-500 mt-0.5">
            Orang yang salah satu berkasnya habis atau belum ada.
          </p>
        </header>

        <ul class="divide-y divide-stone-100">
          <li v-for="m in mendesak" :key="m.id" class="px-5 py-3 flex items-center justify-between gap-3">
            <Link :href="`/miners/${m.id}`" class="text-[12px] font-semibold text-cam-lime-deep hover:underline">
              {{ m.nama }}
            </Link>
            <Lencana :keadaan="m.terburuk" :label="props.KEADAAN?.[m.terburuk]" :nada="props.NADA" />
          </li>

          <li v-if="!mendesak.length" class="px-5 py-8 text-center text-[12px] text-stone-400">
            Tidak ada berkas yang habis. Seluruh pekerja lengkap hari ini.
          </li>
        </ul>
      </div>
    </section>

    <section v-if="kompetensi.length"
             class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100">
        <h3 class="text-[13.5px] font-bold text-cam-ink">Sertifikat kompetensi akan habis</h3>
      </header>

      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[11.5px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
              <th class="px-4 py-2 font-semibold">Pemegang</th>
              <th class="px-4 py-2 font-semibold">Sertifikat</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Berlaku sampai</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap text-right">Sisa</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="k in kompetensi" :key="k.id" class="border-b border-stone-100">
              <td class="px-4 py-2.5 font-semibold text-cam-ink">{{ k.pekerja || '—' }}</td>
              <td class="px-4 py-2.5 text-stone-600">{{ k.nama }}</td>
              <td class="px-4 py-2.5 num text-stone-500">{{ k.sampai || '—' }}</td>
              <td class="px-4 py-2.5 num text-right">{{ k.sisa }} hari</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

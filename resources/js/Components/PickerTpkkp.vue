<script setup lang="ts">
/**
 * Navigasi dalam-halaman PTPKKP untuk halaman Inertia.
 *
 * Sepadan dengan resources/views/tpkkp/_picker.blade.php dan membaca
 * daftar tab yang sama (App\Support\TpkkpNav), dikirim server sebagai
 * prop. Tanpa komponen ini, halaman Vue PTPKKP terkirim tanpa navigasi
 * apa pun — satu-satunya jalan keluar adalah tombol back peramban.
 *
 * Tiap tab memakai bentuk tautan sesuai tandanya dari server: <Link>
 * untuk sesama halaman Inertia (Penilaian ↔ Rekapitulasi) sehingga tidak
 * memuat ulang, dan <a> biasa untuk tab yang masih Blade.
 */
import { Link, router } from '@inertiajs/vue3';

interface Tab { label: string; url: string; ikon: string; aktif: boolean; inertia: boolean }

/* <Link> hanya untuk tujuan yang benar-benar Inertia. Ke halaman Blade,
   <Link> mengirim permintaan ber-header X-Inertia, menerima HTML utuh,
   lalu menampilkan modal galat dan TETAP DIAM di halaman yang sama —
   ia tidak jatuh ke navigasi peramban seperti yang wajar diduga. */
const tautan = (inertia: boolean) => (inertia ? Link : 'a');

const props = defineProps<{
  tabs: Tab[];
  tahun: number;
  daftarTahun: number[];
}>();

function gantiTahun(e: Event) {
  const tahun = (e.target as HTMLSelectElement).value;
  router.get(window.location.pathname, { tahun }, { preserveScroll: true });
}

/* Periode berjalan ditawarkan walau belum pernah dibuat, sama seperti
   versi Blade — kalau tidak, tahun baru tidak pernah bisa dimulai. */
const tahunSekarang = new Date().getFullYear();
</script>

<template>
  <div class="flex flex-wrap items-center gap-1.5 mb-4">
    <select :value="tahun" @change="gantiTahun"
            class="ring-focus rounded-xl border border-stone-200 bg-white px-3.5 py-2
                   text-[12.5px] font-semibold shadow-sm mr-1">
      <option v-for="t in daftarTahun" :key="t" :value="t">Periode {{ t }}</option>
      <option v-if="!daftarTahun.includes(tahunSekarang)" :value="tahunSekarang">
        Periode {{ tahunSekarang }} (baru)
      </option>
    </select>

    <component :is="tautan(tab.inertia)"
          v-for="tab in tabs" :key="tab.url" :href="tab.url" :title="tab.label"
          class="group inline-flex items-center gap-1.5 text-[12px] font-semibold pl-2 pr-3 py-1.5
                 rounded-full border transition-all duration-200"
          :class="tab.aktif
            ? 'bg-cam-ink text-white border-cam-ink shadow-sm scale-[1.03]'
            : 'bg-white text-stone-600 border-stone-200 hover:border-cam-ink/40 hover:text-cam-ink hover:-translate-y-0.5 hover:shadow-sm'">
      <svg class="w-3.5 h-3.5 flex-none transition-transform duration-200 group-hover:scale-110"
           :class="tab.aktif ? 'text-cam-lime-light' : 'text-stone-400 group-hover:text-cam-lime-deep'"
           viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path :d="tab.ikon"/>
      </svg>
      <span>{{ tab.label }}</span>
    </component>
  </div>
</template>

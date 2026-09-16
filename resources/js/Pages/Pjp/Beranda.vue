<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PjpStatusStackedBar from '../../Components/PjpStatusStackedBar.vue';

interface MiniPjp { id: number; nama_perusahaan: string }
interface PerluPerhatian { id: number; nama_perusahaan: string; achievement: number }

defineProps<{
  stats: { total: number; aktifDipantau: number; perluTindakLanjut: number; tidakAktif: number };
  statusCounts: Record<string, number>;
  pjpBelumLaporanBulanan: MiniPjp[];
  perluPerhatian: PerluPerhatian[];
}>();

function achievementColor(v: number): string {
  if (v >= 60) return 'text-amber-800 bg-amber-100';
  if (v >= 40) return 'text-orange-800 bg-orange-100';
  return 'text-red-800 bg-red-100';
}

const kartu = [
  { key: 'total', label: 'Total PJP Terdaftar', href: '/pjp', gradient: 'from-orange-500 to-cam-ink' },
  { key: 'aktifDipantau', label: 'Aktif Dipantau', href: '/pjp?status=aktif', gradient: 'from-emerald-600 to-emerald-800' },
  { key: 'perluTindakLanjut', label: 'Perlu Tindak Lanjut', href: '/pjp?status=perlu_tindak_lanjut', gradient: 'from-amber-500 to-orange-600' },
  { key: 'tidakAktif', label: 'Tidak Aktif', href: '/pjp?status=tidak_aktif', gradient: 'from-stone-500 to-stone-700' },
] as const;

const tahapan = [
  { href: '/pjp-tahapan/persyaratan', title: 'Persyaratan, Seleksi, dan Penetapan',
    description: 'Proses awal penilaian persyaratan, seleksi, hingga penetapan PJP.' },
  { href: '/pjp-tahapan/pelaporan', title: 'Tanggung Jawab, Pemantauan, dan Pelaporan',
    description: 'Pengelolaan tanggung jawab, pemantauan berkala, dan pelaporan kinerja PJP.' },
  { href: '/pjp-tahapan/evaluasi', title: 'Evaluasi',
    description: 'Evaluasi menyeluruh terhadap kinerja dan kepatuhan PJP sebagai dasar tindak lanjut.' },
];
</script>

<template>
  <Head title="Beranda PJP" />

  <div class="max-w-5xl mx-auto">
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
      <div>
        <h2 class="font-serif text-xl font-bold text-cam-ink">Beranda Pemantauan PJP</h2>
        <p class="text-[12.5px] text-stone-500 mt-1">
          Memantau dan mengelola Perusahaan Jasa Pertambangan (PJP) di seluruh tahapan pengelolaannya.
        </p>
      </div>
      <Link href="/pjp" class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5 text-[12.5px] font-bold">Lihat Data PJP →</Link>
    </div>

    <div v-if="pjpBelumLaporanBulanan.length" class="rounded-2xl border border-amber-200 bg-amber-50 p-5 mb-6">
      <p class="text-[13px] font-bold text-amber-900">
        {{ pjpBelumLaporanBulanan.length }} PJP belum/terlambat mengirim Laporan Bulanan bulan ini
      </p>
      <ul class="flex flex-wrap gap-2 mt-2">
        <li v-for="pjp in pjpBelumLaporanBulanan" :key="pjp.id">
          <Link :href="`/pjp/${pjp.id}`" class="rounded-full bg-white px-3 py-1 text-[12px] font-semibold text-amber-800 hover:bg-amber-100">
            {{ pjp.nama_perusahaan }}
          </Link>
        </li>
      </ul>
    </div>

    <div v-if="perluPerhatian.length" class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 mb-6">
      <p class="text-[13px] font-bold text-cam-ink mb-2">PJP Paling Perlu Perhatian</p>
      <ul class="divide-y divide-stone-100">
        <li v-for="pjp in perluPerhatian" :key="pjp.id">
          <Link :href="`/pjp/${pjp.id}`" class="flex items-center justify-between gap-3 py-2.5 text-[12.5px] hover:bg-stone-50 transition -mx-1 px-1 rounded-lg">
            <span class="font-semibold text-cam-ink">{{ pjp.nama_perusahaan }}</span>
            <span class="rounded-full px-2 py-0.5 text-[11px] font-bold" :class="achievementColor(pjp.achievement)">{{ pjp.achievement }}%</span>
          </Link>
        </li>
      </ul>
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <Link v-for="k in kartu" :key="k.key" :href="k.href"
            class="relative overflow-hidden rounded-2xl p-5 text-white shadow-lg bg-gradient-to-br transition-transform hover:-translate-y-1"
            :class="k.gradient">
        <p class="text-2xl font-extrabold">{{ (stats as any)[k.key] }}</p>
        <p class="text-[12px] text-white/90 mt-0.5">{{ k.label }}</p>
        <p class="text-[11px] text-white/80 mt-4">Lihat data →</p>
      </Link>
    </div>

    <div v-if="stats.total === 0" class="bg-white rounded-2xl border border-dashed border-stone-200 p-8 text-center text-[13px] text-stone-400 mb-8">
      Belum ada data PJP. Tambahkan data untuk mulai memantau dan mengelola PJP.
    </div>
    <div v-else class="mb-8"><PjpStatusStackedBar title="Capaian Status Seluruh PJP" :counts="statusCounts" /></div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <Link v-for="t in tahapan" :key="t.href" :href="t.href"
            class="bg-white rounded-2xl shadow-card border border-stone-100 p-6 flex flex-col hover:-translate-y-1 hover:shadow-lg transition">
        <h3 class="font-bold text-[14px] text-cam-ink">{{ t.title }}</h3>
        <p class="text-[12px] text-stone-500 mt-2 flex-1">{{ t.description }}</p>
        <span class="text-[12px] font-bold text-cam-lime-deep mt-4">Lihat detail →</span>
      </Link>
    </div>
  </div>
</template>

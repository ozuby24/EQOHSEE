<script setup lang="ts">
/*
 * Kepala halaman audit SMKP (porting Aurorasys). Kop kerangka EQOHSEE
 * ditolak untuk rute smkp.* dengan `kop: false` (HandleInertiaRequests),
 * karena kepala inilah yang membawa judul, jejak, dan tombol aksinya.
 */
import { Link } from '@inertiajs/vue3';
import Ikon from './Ikon.vue';
import Kilat from './Kilat.vue';
import LogoPerusahaan from './LogoPerusahaan.vue';
import type { RingkasAudit } from '../../smkp/types';

defineProps<{ audit: RingkasAudit; aktif: string; bisaSunting?: boolean; jumlahTemuan?: number | null }>();

const tab = [
  { kunci: 'ringkasan', label: 'Ringkasan', url: (id: number) => `/smkp/${id}` },
  { kunci: 'tahap1', label: 'Tahap I', url: (id: number) => `/smkp/${id}/tahap-1` },
  { kunci: 'rencana', label: 'Rencana Audit', url: (id: number) => `/smkp/${id}/rencana` },
  { kunci: 'rapat', label: 'Rapat & Hadir', url: (id: number) => `/smkp/${id}/rapat` },
  { kunci: 'nilai', label: 'Penilaian', url: (id: number) => `/smkp/${id}/elemen/I` },
  { kunci: 'bukti', label: 'Bukti Audit', url: (id: number) => `/smkp/${id}/bukti` },
  { kunci: 'temuan', label: 'Temuan & CAR', url: (id: number) => `/smkp/${id}/temuan` },
  { kunci: 'formulir', label: 'Formulir', url: (id: number) => `/smkp/${id}/formulir` },
  { kunci: 'laporan', label: 'Laporan', url: (id: number) => `/smkp/${id}/laporan/isian` },
];

const STATUS: Record<string, string> = {
  draft: 'bg-slate-100 text-slate-600',
  berjalan: 'bg-brand-50 text-brand-700 ring-1 ring-brand-100',
  selesai: 'bg-hijau-50 text-hijau-700 ring-1 ring-hijau-100',
};
</script>

<template>
  <div class="mb-5">
    <nav class="flex flex-wrap items-center gap-1 text-[12px] text-slate-400 mb-3" aria-label="Jejak">
      <Link href="/smkp" class="hover:text-brand-600">Audit SMKP</Link>
      <Ikon nama="chevron-right" class="w-3.5 h-3.5" />
      <span class="text-slate-500">{{ audit.judul }}</span>
    </nav>

    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="flex items-start gap-4 min-w-0">
        <LogoPerusahaan :logo="audit.perusahaan.logo" :nama="audit.perusahaan.nama" ukuran="w-12 h-12 hidden sm:grid" />
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2.5">
            <h1 class="text-[20px] sm:text-[22px] font-bold tracking-tight text-navy-900 leading-tight">{{ audit.judul }}</h1>
            <span class="lencana" :class="STATUS[audit.status] ?? STATUS.draft">{{ audit.labelStatus }}</span>
          </div>
          <p class="mt-1 text-[13px] text-slate-500">
            {{ audit.perusahaan.nama }} · {{ audit.periode }}<template v-if="audit.ketua"> · Ketua tim {{ audit.ketua }}</template>
          </p>
          <p class="mt-1 text-[12px] font-semibold text-brand-600">{{ audit.labelTahap }}</p>
        </div>
      </div>
      <div class="flex flex-wrap gap-2">
        <slot name="aksi" />
        <Link v-if="bisaSunting" :href="`/smkp/${audit.id}/ubah`" class="btn-garis btn-kecil py-2"><Ikon nama="pensil" class="w-4 h-4" /> Ubah</Link>
        <Link :href="`/smkp/${audit.id}/laporan`" class="btn-garis btn-kecil py-2"><Ikon nama="cetak" class="w-4 h-4" /> Laporan</Link>
      </div>
    </div>

    <div class="mt-4 border-b border-slate-200 gulir-x scrollbar-tipis">
      <div class="flex gap-1 min-w-max">
        <Link v-for="t in tab" :key="t.kunci" :href="t.url(audit.id)" preserve-scroll
              class="relative px-3.5 py-2.5 text-[13px] font-semibold transition whitespace-nowrap"
              :class="t.kunci === aktif ? 'text-brand-600' : 'text-slate-500 hover:text-navy-900'">
          {{ t.label }}<span v-if="t.kunci === 'temuan' && jumlahTemuan" class="ml-1 text-slate-400">({{ jumlahTemuan }})</span>
          <span v-if="t.kunci === aktif" class="absolute inset-x-2 -bottom-px h-0.5 rounded-full bg-brand-500" />
        </Link>
      </div>
    </div>
    <Kilat />
  </div>
</template>

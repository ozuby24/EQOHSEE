<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import KepalaHalaman from '../../Components/Smkp/KepalaHalaman.vue';
import Ikon from '../../Components/Smkp/Ikon.vue';
import Paginasi from '../../Components/Smkp/Paginasi.vue';
import Kosong from '../../Components/Smkp/Kosong.vue';
import type { Halaman } from '../../smkp/types';

interface Baris {
  id: number; nomor: string; kode: string; jenis: string; uraian: string; status: string; target: string | null;
  terlambat: boolean; kiriman: number; perusahaan: string | null; audit: string | null; url: string | null;
}

const props = defineProps<{
  daftar: Halaman<Baris>;
  q: string;
  status: string;
  jumlah: Record<string, number>;
  bisaKirim: boolean;
}>();

const cari = ref(props.q);
const muat = (status: string, q = cari.value.trim()) =>
  router.get('/smkp/tindak-lanjut', { ...(status !== 'terbuka' ? { status } : {}), ...(q ? { q } : {}) }, { preserveState: true, preserveScroll: true, replace: true });

const SARING = [
  { kunci: 'terbuka', label: 'Belum ditutup' },
  { kunci: 'Open', label: 'Open' },
  { kunci: 'In Progress', label: 'In Progress' },
  { kunci: 'Closed', label: 'Closed' },
  { kunci: 'semua', label: 'Semua' },
];
const JENIS: Record<string, string> = { kritikal: 'bg-rose-900 text-white', mayor: 'bg-rose-600 text-white', minor: 'bg-amber-500 text-white' };
const STATUS: Record<string, string> = { Open: 'bg-rose-50 text-rose-700', 'In Progress': 'bg-amber-50 text-amber-700', Closed: 'bg-hijau-50 text-hijau-700' };
</script>

<template>
  <div class="smkp-ui">
    <Head title="Tindak Lanjut Temuan" />
    <KepalaHalaman judul="Tindak Lanjut Temuan"
                   :sub="bisaKirim ? 'Sampaikan perbaikan dan unggah buktinya untuk setiap temuan audit perusahaan Anda.' : 'Pantau perbaikan yang dikirim auditi, lalu verifikasi dan tutup di halaman Temuan audit.'"
                   :jejak="[{ label: 'Dasbor', url: '/dashboard' }, { label: 'Audit SMKP', url: '/smkp' }, { label: 'Tindak Lanjut' }]" />

    <div v-if="bisaKirim && jumlah.Open" class="kartu p-4 mb-5 flex items-center gap-3 border-amber-200 bg-amber-50/70">
      <Ikon nama="peringatan" class="w-5 h-5 text-amber-600 shrink-0" />
      <p class="text-[13px] text-amber-800"><b>{{ jumlah.Open }} temuan</b> belum mendapat tindak lanjut. Buka temuan, isi perbaikan, lampirkan bukti, lalu kirim.</p>
    </div>

    <div class="flex flex-wrap items-center gap-2 mb-4">
      <button v-for="s in SARING" :key="s.kunci" type="button" @click="muat(s.kunci)"
              class="rounded-xl px-4 py-2 text-[13px] font-semibold transition"
              :class="status === s.kunci ? 'bg-navy-900 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:border-slate-300'">
        {{ s.label }} <span class="opacity-60 tabular-nums">({{ jumlah[s.kunci] ?? 0 }})</span>
      </button>
      <form class="relative w-full sm:w-auto sm:ml-auto sm:min-w-[260px]" @submit.prevent="muat(status)">
        <Ikon nama="cari" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
        <input v-model="cari" type="search" class="input pl-10" placeholder="Cari kode atau uraian…" aria-label="Cari temuan">
      </form>
    </div>

    <div class="kartu overflow-hidden">
      <ul v-if="daftar.data.length" class="divide-y divide-slate-100">
        <li v-for="t in daftar.data" :key="t.id">
          <component :is="t.url ? Link : 'div'" :href="t.url ?? undefined" class="flex flex-wrap items-center gap-x-3 gap-y-2 px-4 sm:px-5 py-3.5 hover:bg-slate-50/70 transition-colors">
            <span class="lencana uppercase tracking-wide" :class="JENIS[t.jenis] ?? 'bg-slate-500 text-white'">{{ t.jenis }}</span>
            <div class="min-w-0 flex-1 basis-[240px]">
              <p class="text-[13.5px] text-navy-900 leading-snug line-clamp-2"><b class="tabular-nums">{{ t.nomor || t.kode }}</b><span v-if="t.nomor" class="tabular-nums text-slate-400"> · {{ t.kode }}</span> — {{ t.uraian }}</p>
              <p class="mt-0.5 text-[12px] text-slate-500 truncate">{{ t.perusahaan }}<span v-if="t.audit"> · {{ t.audit }}</span></p>
            </div>
            <div class="flex items-center gap-2 ml-auto">
              <span v-if="t.kiriman" class="lencana bg-brand-50 text-brand-700"><Ikon nama="unggah" class="w-3 h-3" /> {{ t.kiriman }} kiriman</span>
              <span v-if="t.target" class="text-[12px] tabular-nums whitespace-nowrap" :class="t.terlambat ? 'font-semibold text-rose-600' : 'text-slate-400'">{{ t.terlambat ? 'Lewat ' : 'Target ' }}{{ t.target }}</span>
              <span class="lencana" :class="STATUS[t.status]">{{ t.status }}</span>
              <Ikon nama="chevron-right" class="w-4 h-4 text-slate-300" />
            </div>
          </component>
        </li>
      </ul>
      <Kosong v-else :judul="q ? 'Tidak ada temuan yang cocok' : 'Tidak ada temuan'"
              :pesan="status === 'terbuka' ? 'Seluruh temuan sudah ditutup, atau belum ada temuan yang diangkat auditor.' : null" ikon="centang-bulat" />
      <Paginasi :tautan="daftar.links" :dari="daftar.from" :sampai="daftar.to" :total="daftar.total" />
    </div>
  </div>
</template>

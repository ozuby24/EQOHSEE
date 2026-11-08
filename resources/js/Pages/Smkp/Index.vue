<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import KepalaHalaman from '../../Components/Smkp/KepalaHalaman.vue';
import Ikon from '../../Components/Smkp/Ikon.vue';
import LogoPerusahaan from '../../Components/Smkp/LogoPerusahaan.vue';
import Paginasi from '../../Components/Smkp/Paginasi.vue';
import Kosong from '../../Components/Smkp/Kosong.vue';
import { angka } from '../../smkp/lib/format';
import type { Halaman } from '../../smkp/types';

interface Baris {
  id: number; judul: string; tahun: number; status: string; tahap: number; labelTahap: string;
  perusahaan: string | null; logo: string | null; ketua: string | null; mulai: string | null; selesai: string | null;
  skor: number; tingkat: { label: string; warna: string }; kemajuan: number; temuan: number; terbuka: number;
}

const props = defineProps<{
  audits: Halaman<Baris>;
  saring: { q: string; status: string; perusahaan: number | string };
  jumlah: Record<string, number>;
  perusahaan: Array<{ id: number; nama: string }>;
  statusPilihan: Record<string, string>;
  bisaSunting: boolean;
}>();

const q = ref(props.saring.q);
const pilihPerusahaan = ref<string | number>(props.saring.perusahaan || '');
const menu = ref<number | null>(null);

function terapkan(status = props.saring.status) {
  router.get('/smkp', {
    ...(q.value.trim() ? { q: q.value.trim() } : {}),
    ...(status ? { status } : {}),
    ...(pilihPerusahaan.value ? { perusahaan: pilihPerusahaan.value } : {}),
  }, { preserveState: true, preserveScroll: true, replace: true });
}

function reset() {
  q.value = '';
  pilihPerusahaan.value = '';
  router.get('/smkp');
}

const tab = [
  { kunci: '', label: 'Semua', jumlah: 'semua' },
  { kunci: 'berjalan', label: 'Berjalan', jumlah: 'berjalan' },
  { kunci: 'draft', label: 'Draft', jumlah: 'draft' },
  { kunci: 'selesai', label: 'Selesai', jumlah: 'selesai' },
];

const STATUS: Record<string, string> = {
  draft: 'bg-slate-100 text-slate-600',
  berjalan: 'bg-brand-50 text-brand-700',
  selesai: 'bg-hijau-50 text-hijau-700',
};
</script>

<template>
  <div class="smkp-ui">
    <Head title="Audit SMKP" />
    <KepalaHalaman judul="Audit SMKP Minerba" sub="Kelola seluruh periode audit Sistem Manajemen Keselamatan Pertambangan perusahaan Anda."
                   :jejak="[{ label: 'Dasbor', url: '/dashboard' }, { label: 'Audit SMKP' }]">
      <Link href="/smkp/acuan" class="btn-garis"><Ikon nama="buku" class="w-4 h-4" /> Kriteria</Link>
      <Link v-if="bisaSunting" href="/smkp/buat" class="btn-utama"><Ikon nama="tambah" class="w-4 h-4" /> Audit Baru</Link>
    </KepalaHalaman>

    <div class="flex flex-wrap gap-2 mb-4">
      <button v-for="t in tab" :key="t.kunci" type="button" @click="terapkan(t.kunci)"
              class="rounded-xl px-4 py-2 text-[13px] font-semibold transition"
              :class="saring.status === t.kunci ? 'bg-navy-900 text-white shadow-kartu' : 'bg-white text-slate-600 border border-slate-200 hover:border-slate-300'">
        {{ t.label }} <span class="ml-0.5 opacity-60 tabular-nums">({{ jumlah[t.jumlah] ?? 0 }})</span>
      </button>
    </div>

    <div class="kartu overflow-hidden">
      <form class="flex flex-wrap items-center gap-3 p-4 border-b border-slate-100" @submit.prevent="terapkan()">
        <label class="relative flex-1 min-w-[220px]">
          <Ikon nama="cari" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
          <input v-model="q" type="search" placeholder="Cari judul audit, perusahaan, ketua tim…" class="input pl-10">
        </label>
        <select v-model="pilihPerusahaan" class="input w-auto min-w-[220px]" aria-label="Saring perusahaan" @change="terapkan()">
          <option value="">Semua perusahaan</option>
          <option v-for="p in props.perusahaan" :key="p.id" :value="p.id">{{ p.nama }}</option>
        </select>
        <button class="btn-garis"><Ikon nama="saring" class="w-4 h-4" /> Terapkan</button>
        <button v-if="saring.q || saring.perusahaan || saring.status" type="button" class="btn-hantu" @click="reset">Reset</button>
      </form>

      <div v-if="audits.data.length" class="gulir-x">
        <table class="tabel tabel-hover min-w-[980px]">
          <thead>
            <tr>
              <th>Judul audit</th><th>Periode</th><th>Tahap</th><th>Status</th><th class="w-[170px]">Kemajuan penilaian</th><th>Nilai</th><th>Temuan</th><th class="text-right">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="a in audits.data" :key="a.id">
              <td>
                <Link :href="`/smkp/${a.id}`" class="flex items-center gap-3 group">
                  <LogoPerusahaan :logo="a.logo" :nama="a.perusahaan" ukuran="w-10 h-10" />
                  <span class="min-w-0">
                    <span class="block font-semibold text-navy-900 group-hover:text-brand-600">{{ a.judul }}</span>
                    <span class="block text-[12px] text-slate-500 truncate max-w-[260px]">{{ a.perusahaan }}</span>
                  </span>
                </Link>
              </td>
              <td class="text-[12.5px] text-slate-600 whitespace-nowrap">{{ a.mulai ? `${a.mulai}${a.selesai ? ' – ' + a.selesai : ''}` : `Tahun ${a.tahun}` }}</td>
              <td class="text-[12.5px] text-slate-600 whitespace-nowrap">{{ a.labelTahap.split('—')[0].trim() }}</td>
              <td><span class="lencana capitalize" :class="STATUS[a.status] ?? STATUS.draft">{{ statusPilihan[a.status] ?? a.status }}</span></td>
              <td>
                <div class="flex items-center gap-2.5">
                  <div class="bilah h-1.5 flex-1"><i class="bg-brand-500" :style="{ width: `${Math.round(a.kemajuan * 100)}%` }" /></div>
                  <span class="text-[12px] font-semibold text-navy-900 w-9 text-right tabular-nums">{{ Math.round(a.kemajuan * 100) }}%</span>
                </div>
              </td>
              <td>
                <span class="font-bold tabular-nums" :style="{ color: a.tingkat.warna }">{{ angka(a.skor) }}</span>
                <span class="block text-[11px] text-slate-400 whitespace-nowrap">{{ a.tingkat.label }}</span>
              </td>
              <td class="whitespace-nowrap text-[12.5px]"><b class="text-navy-900 tabular-nums">{{ a.temuan }}</b> <span class="text-slate-400">({{ a.terbuka }} terbuka)</span></td>
              <td class="text-right relative">
                <button type="button" class="p-2 rounded-lg text-slate-400 hover:text-navy-900 hover:bg-slate-100" @click="menu = menu === a.id ? null : a.id" aria-label="Aksi">
                  <Ikon nama="lainnya" class="w-5 h-5" />
                </button>
                <div v-if="menu === a.id" class="fixed inset-0 z-10" @click="menu = null" />
                <div v-if="menu === a.id" class="absolute right-4 top-12 z-20 w-48 kartu p-1.5 shadow-angkat text-left">
                  <Link :href="`/smkp/${a.id}`" class="flex items-center gap-2 rounded-lg px-3 py-2 text-[13px] hover:bg-slate-50"><Ikon nama="mata" class="w-4 h-4 text-slate-400" /> Lihat ringkasan</Link>
                  <Link :href="`/smkp/${a.id}/elemen/I`" class="flex items-center gap-2 rounded-lg px-3 py-2 text-[13px] hover:bg-slate-50"><Ikon nama="clipboard-check" class="w-4 h-4 text-slate-400" /> Penilaian</Link>
                  <Link :href="`/smkp/${a.id}/temuan`" class="flex items-center gap-2 rounded-lg px-3 py-2 text-[13px] hover:bg-slate-50"><Ikon nama="peringatan" class="w-4 h-4 text-slate-400" /> Temuan</Link>
                  <Link v-if="bisaSunting" :href="`/smkp/${a.id}/ubah`" class="flex items-center gap-2 rounded-lg px-3 py-2 text-[13px] hover:bg-slate-50"><Ikon nama="pensil" class="w-4 h-4 text-slate-400" /> Ubah</Link>
                  <Link :href="`/smkp/${a.id}/laporan`" class="flex items-center gap-2 rounded-lg px-3 py-2 text-[13px] hover:bg-slate-50"><Ikon nama="cetak" class="w-4 h-4 text-slate-400" /> Laporan cetak</Link>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <Kosong v-else judul="Belum ada audit" :pesan="saring.q || saring.status || saring.perusahaan ? 'Tidak ada audit yang cocok dengan saringan.' : 'Buat periode audit SMKP pertama untuk mulai menilai tujuh elemen.'" ikon="clipboard-check">
        <Link v-if="bisaSunting && !saring.q" href="/smkp/buat" class="btn-utama btn-kecil"><Ikon nama="tambah" class="w-4 h-4" /> Audit Baru</Link>
      </Kosong>

      <Paginasi :tautan="audits.links" :dari="audits.from" :sampai="audits.to" :total="audits.total" />
    </div>
  </div>
</template>

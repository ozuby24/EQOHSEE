<script setup lang="ts">
/**
 * Rekap absensi satu periode.
 *
 * JAM DI LUAR ROSTER DIPISAHKAN, tidak dilebur ke dalam jumlah jam.
 * Dilebur, tunjangan site terhitung atas hari libur yang dikerjakan —
 * dan lembur yang belum diperintahkan justru terbayar diam-diam
 * sebagai hari biasa, yang berarti tidak pernah ada yang menanyakan
 * mengapa ia diperintahkan.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { propHalaman } from '../../../halaman';

const props = propHalaman();

const baris = computed<any[]>(() => (props.baris ?? []) as any[]);

const dari   = ref(String((props.rentang as any)?.dari ?? ''));
const sampai = ref(String((props.rentang as any)?.sampai ?? ''));

function muat() {
  router.get('/hris/absensi/rekap', { dari: dari.value || undefined, sampai: sampai.value || undefined },
    { preserveState: true, preserveScroll: true, replace: true });
}

const jumlah = computed(() => {
  const t = (k: string) => baris.value.reduce((n, b) => n + (Number(b[k]) || 0), 0);

  return {
    hariKerja: t('hariKerja'),
    jam:       Math.round(t('jam') * 100) / 100,
    jamLuar:   Math.round(t('jamLuar') * 100) / 100,
    telat:     t('telat'),
    absen:     t('absen'),
    luar:      t('luar'),
  };
});
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1280px] mx-auto space-y-5">
    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-cam-ink">{{ props.judul }}</h2>
        <p class="text-[12.5px] text-stone-500 mt-0.5">{{ props.subjudul }}</p>
      </div>

      <Link href="/hris/absensi" class="eq-btn-lain">Pemantauan harian</Link>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
      <div class="flex flex-wrap items-end gap-3">
        <label class="block">
          <span class="text-[11px] text-stone-500">Dari</span>
          <input v-model="dari" type="date" class="mt-1 rounded-lg border-stone-200 text-[12px]" @change="muat">
        </label>

        <label class="block">
          <span class="text-[11px] text-stone-500">Sampai</span>
          <input v-model="sampai" type="date" class="mt-1 rounded-lg border-stone-200 text-[12px]" @change="muat">
        </label>

        <p class="text-[11px] text-stone-400 pb-1">Rentang dibatasi 92 hari.</p>
      </div>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[11.5px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
              <th class="px-4 py-2 font-semibold">Pekerja</th>
              <th class="px-4 py-2 font-semibold">Area</th>
              <th class="px-4 py-2 font-semibold text-right">Hari kerja</th>
              <th class="px-4 py-2 font-semibold text-right">Jam</th>
              <th class="px-4 py-2 font-semibold text-right">Jam luar roster</th>
              <th class="px-4 py-2 font-semibold text-right">Hadir</th>
              <th class="px-4 py-2 font-semibold text-right">Terlambat</th>
              <th class="px-4 py-2 font-semibold text-right">Menit telat</th>
              <th class="px-4 py-2 font-semibold text-right">Belum pulang</th>
              <th class="px-4 py-2 font-semibold text-right">Absen</th>
              <th class="px-4 py-2 font-semibold text-right">Luar roster</th>
              <th class="px-4 py-2 font-semibold text-right">Luar area</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="(b, i) in baris" :key="i" class="border-b border-stone-100">
              <td class="px-4 py-2.5">
                <div class="font-semibold text-cam-ink">{{ b.pekerja || '—' }}</div>
                <div class="text-[10.5px] text-stone-400">
                  {{ b.nomor }}<span v-if="b.jabatan"> · {{ b.jabatan }}</span>
                  <span v-if="b.dikoreksi"> · {{ b.dikoreksi }} koreksi</span>
                </div>
              </td>
              <td class="px-4 py-2.5 text-stone-600">{{ b.blok || '—' }}</td>
              <td class="px-4 py-2.5 num text-right font-semibold text-cam-ink">{{ b.hariKerja }}</td>
              <td class="px-4 py-2.5 num text-right">{{ b.jam.toFixed(2) }}</td>
              <td class="px-4 py-2.5 num text-right" :class="b.jamLuar > 0 ? 'text-violet-700 font-semibold' : 'text-stone-300'">
                {{ b.jamLuar > 0 ? b.jamLuar.toFixed(2) : '—' }}
              </td>
              <td class="px-4 py-2.5 num text-right text-emerald-700">{{ b.hadir }}</td>
              <td class="px-4 py-2.5 num text-right" :class="b.terlambat ? 'text-amber-700' : 'text-stone-300'">{{ b.terlambat }}</td>
              <td class="px-4 py-2.5 num text-right" :class="b.telat ? 'text-amber-700' : 'text-stone-300'">{{ b.telat }}</td>
              <td class="px-4 py-2.5 num text-right" :class="b.belum ? 'text-sky-700' : 'text-stone-300'">{{ b.belum }}</td>
              <td class="px-4 py-2.5 num text-right" :class="b.absen ? 'text-red-600 font-semibold' : 'text-stone-300'">{{ b.absen }}</td>
              <td class="px-4 py-2.5 num text-right" :class="b.luar ? 'text-violet-700 font-semibold' : 'text-stone-300'">{{ b.luar }}</td>
              <td class="px-4 py-2.5 num text-right" :class="b.luarArea ? 'text-red-600' : 'text-stone-300'">{{ b.luarArea }}</td>
            </tr>

            <tr v-if="!baris.length">
              <td colspan="12" class="px-4 py-10 text-center text-stone-400">
                Belum ada catatan absensi pada rentang ini.
              </td>
            </tr>
          </tbody>

          <tfoot v-if="baris.length">
            <tr class="border-t-2 border-stone-200 bg-stone-50/70 font-bold text-cam-ink">
              <td class="px-4 py-2.5" colspan="2">Jumlah {{ baris.length }} orang</td>
              <td class="px-4 py-2.5 num text-right">{{ jumlah.hariKerja }}</td>
              <td class="px-4 py-2.5 num text-right">{{ jumlah.jam.toFixed(2) }}</td>
              <td class="px-4 py-2.5 num text-right">{{ jumlah.jamLuar.toFixed(2) }}</td>
              <td class="px-4 py-2.5" colspan="2"></td>
              <td class="px-4 py-2.5 num text-right">{{ jumlah.telat }}</td>
              <td class="px-4 py-2.5"></td>
              <td class="px-4 py-2.5 num text-right">{{ jumlah.absen }}</td>
              <td class="px-4 py-2.5 num text-right">{{ jumlah.luar }}</td>
              <td class="px-4 py-2.5"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </section>
  </div>
</template>

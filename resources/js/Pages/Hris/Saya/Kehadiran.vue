<script setup lang="ts">
/**
 * Kehadiran saya.
 *
 * YANG DITAMPILKAN ADALAH YANG TERCATAT MESIN, bukan yang dirasa
 * orangnya. Perselisihan absensi selalu berbentuk "saya masuk kok" —
 * dan yang menyelesaikannya adalah jam tap yang terbaca, bukan
 * kesimpulan "hadir" atau "absen". Karena itu jam masuk dan jam
 * keluarnya ditampilkan apa adanya di samping kesimpulannya.
 */
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { propHalaman } from '../../../halaman';

const props = propHalaman();

const baris   = computed<any[]>(() => (props.baris ?? []) as any[]);
const KEADAAN = computed<Record<string, string>>(() => (props.KEADAAN ?? {}) as any);
</script>

<template>
  <Head :title="props.judul as string" />

  <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
    <header class="px-4 pt-4 pb-3">
      <h2 class="text-[13px] font-bold">
        Catatan harian <span class="text-stone-400 font-normal">| {{ baris.length }} hari</span>
      </h2>
      <p class="text-[11.5px] text-stone-500 mt-1">
        Jam tap ditampilkan apa adanya di samping kesimpulannya — yang menyelesaikan perselisihan
        absensi adalah jamnya, bukan kata "hadir" atau "absen".
      </p>
    </header>

    <div class="overflow-x-auto">
      <table class="w-full text-[12px]">
        <thead class="bg-stone-50/70 text-[11px] text-stone-500">
          <tr>
            <th class="text-left font-medium px-4 py-2.5">Tanggal</th>
            <th class="text-left font-medium px-3 py-2.5">Keadaan</th>
            <th class="text-right font-medium px-3 py-2.5">Masuk</th>
            <th class="text-right font-medium px-3 py-2.5">Keluar</th>
            <th class="text-right font-medium px-3 py-2.5">Jam</th>
            <th class="text-right font-medium px-3 py-2.5">Telat</th>
            <th class="text-left font-medium px-4 py-2.5">Area</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="b in baris" :key="b.tanggal" class="border-t border-stone-100">
            <td class="px-4 py-2.5 num">{{ b.tanggal }}</td>

            <td class="px-3 py-2.5">
              <span class="sk-lencana" :class="'sk-' + b.keadaan">{{ KEADAAN[b.keadaan] ?? b.keadaan }}</span>
            </td>

            <td class="px-3 py-2.5 text-right num">{{ b.masuk ?? '—' }}</td>
            <td class="px-3 py-2.5 text-right num">{{ b.keluar ?? '—' }}</td>
            <td class="px-3 py-2.5 text-right num">{{ b.jam ? b.jam.toFixed(2) : '—' }}</td>

            <td class="px-3 py-2.5 text-right num"
                :class="b.telat > 0 ? 'text-amber-700' : 'text-stone-400'">
              {{ b.telat > 0 ? b.telat + "'" : 'tepat' }}
            </td>

            <td class="px-4 py-2.5 text-[11px]"
                :class="b.dalamArea === false ? 'text-red-700' : 'text-stone-400'">
              {{ b.dalamArea === false ? 'di luar geofence' : 'dalam area' }}
            </td>
          </tr>

          <tr v-if="!baris.length">
            <td colspan="7" class="px-4 py-10 text-center text-[12px] text-stone-400">
              Belum ada catatan kehadiran atas nama Anda pada rentang ini.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<style>
.sk-lencana{display:inline-block;border-radius:9999px;padding:0 .45rem;
  font-size:10px;font-weight:600;white-space:nowrap}
.sk-hadir{background:#D1FAE5;color:#065F46}
.sk-terlambat{background:#FEF3C7;color:#78350F}
.sk-belum_pulang{background:#DBEAFE;color:#1E3A5F}
.sk-absen{background:#FEE2E2;color:#991B1B}
.sk-luar_roster{background:#EDE9FE;color:#5B21B6}

:root[data-tema="gelap"] .sk-hadir{background:#143A2C;color:#8FE3BE}
:root[data-tema="gelap"] .sk-terlambat{background:#4A3810;color:#F6D488}
:root[data-tema="gelap"] .sk-belum_pulang{background:#1E3F5E;color:#A8CDF0}
:root[data-tema="gelap"] .sk-absen{background:#4E1D1D;color:#F5A9A9}
:root[data-tema="gelap"] .sk-luar_roster{background:#34255E;color:#C8B6F5}
</style>

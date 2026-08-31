<script setup lang="ts">
/**
 * Daftar berkas investigasi.
 *
 * Kolom "tertahan oleh" tidak ada di sini dengan sengaja: menghitungnya
 * untuk tiap baris berarti memeriksa syarat tiap berkas pada tiap
 * penggambaran daftar. Yang menunggu tindakan sudah disebut di
 * Ringkasan, tempat daftarnya memang pendek.
 */
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { propHalaman } from '../../halaman';
import { KEADAAN } from '../../Grafik/warna';

const props = propHalaman();

const cari = ref('');

const tersaring = computed<any[]>(() => {
  const q = cari.value.trim().toLowerCase();
  const semua = (props.baris ?? []) as any[];
  if (!q) return semua;

  return semua.filter((b: any) => [b.nomor, b.judul, b.ketua, b.insiden?.nomor]
    .some((x: any) => String(x ?? '').toLowerCase().includes(q)));
});

function warnaStatus(s: string): string {
  return s === 'ditutup' ? KEADAAN.baik : '#0F766E';
}
</script>

<template>
  <Head title="Berkas Investigasi" />

  <div class="max-w-[1400px] mx-auto space-y-5">
    <section>
      <h2 class="text-xl font-bold text-cam-ink">Berkas Investigasi</h2>
      <p class="text-[12.5px] text-stone-500 mt-0.5">
        Tiap berkas menempuh jalur tahap yang panjangnya mengikuti level triase insidennya.
      </p>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100 flex flex-wrap items-center gap-3">
        <h3 class="text-[13.5px] font-bold text-cam-ink flex-1 min-w-0">
          Daftar investigasi <span class="font-normal text-stone-400">| {{ tersaring.length }} data</span>
        </h3>
        <input v-model="cari" placeholder="Cari nomor, judul, ketua…"
               class="rounded-lg border-stone-200 text-[12px] w-56" aria-label="Cari investigasi">
      </header>

      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[11.5px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
              <th class="px-4 py-2 font-semibold w-10">No</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">No. Investigasi</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Insiden</th>
              <th class="px-4 py-2 font-semibold">Kejadian</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Level</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Tahap</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Ketua</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Target</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(b, i) in tersaring" :key="b.id" class="border-b border-stone-100 hover:bg-stone-50/60">
              <td class="px-4 py-2.5 num text-stone-400">{{ i + 1 }}</td>
              <td class="px-4 py-2.5 whitespace-nowrap">
                <Link :href="`/investigasi/berkas/${b.id}`"
                      class="num font-bold text-cam-lime-deep hover:underline">{{ b.nomor }}</Link>
              </td>
              <td class="px-4 py-2.5 whitespace-nowrap">
                <Link :href="`/investigasi/insiden/${b.insiden?.id}`"
                      class="num text-stone-500 hover:underline">{{ b.insiden?.nomor }}</Link>
              </td>
              <td class="px-4 py-2.5 text-cam-ink">{{ b.judul }}</td>
              <td class="px-4 py-2.5 whitespace-nowrap">
                <span class="rounded px-1.5 py-0.5 text-[10px] font-bold"
                      :style="{ background: b.warnaPita + '1F', color: b.warnaPita }">{{ b.levelNama }}</span>
              </td>
              <td class="px-4 py-2.5 text-stone-500 whitespace-nowrap">{{ b.tahapNama }}</td>
              <td class="px-4 py-2.5 text-stone-500 whitespace-nowrap">{{ b.ketua || '—' }}</td>
              <td class="px-4 py-2.5 num text-stone-500 whitespace-nowrap">{{ b.target || '—' }}</td>
              <td class="px-4 py-2.5 whitespace-nowrap">
                <span class="rounded px-1.5 py-0.5 text-[10px] font-bold capitalize"
                      :style="{ background: warnaStatus(b.status) + '1F', color: warnaStatus(b.status) }">
                  {{ b.status }}
                </span>
              </td>
            </tr>

            <tr v-if="!tersaring.length">
              <td colspan="9" class="px-4 py-10 text-center text-[12px] text-stone-400">
                {{ (props.baris ?? []).length ? 'Tidak ada yang cocok.' : 'Belum ada berkas investigasi.' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
/**
 * Riwayat satu jenis dokumen.
 *
 * Menjawab "apa yang pernah terjadi pada dokumen ini" — berbeda dari
 * pemantauan, yang menjawab "apa yang harus dikerjakan hari ini".
 * Keduanya dibaca orang yang berbeda pada waktu yang berbeda, dan
 * digabung menjadi satu layar keduanya menjadi lebih sulit dibaca.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { propHalaman } from '../../halaman';
import Lencana from './Lencana.vue';

const props = propHalaman();

const baris = computed<any[]>(() => (props.baris ?? []) as any[]);
const cari  = ref(String(props.saring?.cari ?? ''));

let tunda: ReturnType<typeof setTimeout> | undefined;

watch(cari, () => {
  clearTimeout(tunda);

  tunda = setTimeout(() => {
    router.get(window.location.pathname, { cari: cari.value || undefined },
      { preserveState: true, preserveScroll: true, replace: true });
  }, 300);
});
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1400px] mx-auto space-y-5">

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100 flex flex-wrap items-center gap-3">
        <h3 class="text-[13.5px] font-bold text-cam-ink flex-1 min-w-0">
          Riwayat <span class="font-normal text-stone-400">| {{ baris.length }} baris</span>
        </h3>

        <input v-model="cari" placeholder="Cari nama…"
               class="rounded-lg border-stone-200 text-[12px] w-56" aria-label="Cari riwayat">
      </header>

      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[11.5px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
              <th class="px-4 py-2 font-semibold w-10">No</th>
              <th class="px-4 py-2 font-semibold">Nama</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Nomor</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Tanggal</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Berlaku sampai</th>
              <th class="px-4 py-2 font-semibold">Keterangan</th>
              <th class="px-4 py-2 font-semibold">Keadaan</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="(b, i) in baris" :key="b.id" class="border-b border-stone-100 hover:bg-stone-50/60">
              <td class="px-4 py-2.5 num text-stone-400">{{ i + 1 }}</td>
              <td class="px-4 py-2.5">
                <Link v-if="b.pekerja_id" :href="`/miners/${b.pekerja_id}`"
                      class="font-semibold text-cam-lime-deep hover:underline">{{ b.nama || '—' }}</Link>
                <span v-else class="font-semibold text-cam-ink">{{ b.nama || '—' }}</span>
              </td>
              <td class="px-4 py-2.5 num text-stone-500">{{ b.nomor || '—' }}</td>
              <td class="px-4 py-2.5 num">{{ b.tanggal || '—' }}</td>
              <td class="px-4 py-2.5 num">{{ b.sampai || '—' }}</td>
              <td class="px-4 py-2.5 text-stone-600">{{ b.ket || '—' }}</td>
              <td class="px-4 py-2.5">
                <Lencana :keadaan="b.keadaan" :label="props.KEADAAN?.[b.keadaan]" :nada="props.NADA" />
              </td>
            </tr>

            <tr v-if="!baris.length">
              <td colspan="7" class="px-4 py-10 text-center text-stone-400">Belum ada riwayat.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

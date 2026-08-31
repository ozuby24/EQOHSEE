<script setup lang="ts">
/**
 * Register insiden — daftar seluruh kejadian yang dilaporkan.
 *
 * Berbentuk tabel, bukan kartu: yang dicari orang di layar ini hampir
 * selalu satu baris — insiden mana yang belum ditriase, atau kejadian
 * bulan lalu yang nomornya disebut di telepon.
 *
 * Kolom level sengaja menyebut "belum ditriase" alih-alih dibiarkan
 * kosong. Sel kosong terbaca sebagai insiden ringan; yang benar adalah
 * insiden yang belum dinilai siapa pun, dan itu keadaan yang lebih
 * perlu terlihat.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { propHalaman } from '../../halaman';
import { KEADAAN } from '../../Grafik/warna';

const props = propHalaman();

const cari = ref<string>(props.saring?.q ?? '');

const tersaring = computed<any[]>(() => {
  const q = cari.value.trim().toLowerCase();
  const semua = (props.baris ?? []) as any[];
  if (!q) return semua;

  return semua.filter((b: any) => [b.nomor, b.judul, b.lokasi, b.jenis, b.pelapor]
    .some((x: any) => String(x ?? '').toLowerCase().includes(q)));
});

const LABEL_STATUS: Record<string, string> = {
  dilaporkan: 'Dilaporkan',
  ditriase:   'Ditriase',
  diselidiki: 'Diselidiki',
  ditutup:    'Ditutup',
};

function saring(kunci: string, nilai: string) {
  router.get('/investigasi/insiden', { ...(props.saring ?? {}), [kunci]: nilai || undefined },
    { preserveState: true, preserveScroll: true, replace: true });
}
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1400px] mx-auto space-y-5">
    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-cam-ink">Register Insiden</h2>
        <p class="text-[12.5px] text-stone-500 mt-0.5">
          Seluruh kejadian yang dilaporkan, beserta hasil triasenya.
        </p>
      </div>

      <span class="inline-flex">
        <Link href="/investigasi/insiden/baru" class="eq-btn-utama">+ Lapor insiden</Link>
      </span>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100 flex flex-wrap items-center gap-3">
        <h3 class="text-[13.5px] font-bold text-cam-ink flex-1 min-w-0">
          Daftar insiden <span class="font-normal text-stone-400">| {{ tersaring.length }} data</span>
        </h3>

        <select :value="props.saring?.level ?? ''" class="rounded-lg border-stone-200 text-[12px]"
                aria-label="Saring level" @change="saring('level', ($event.target as HTMLSelectElement).value)">
          <option value="">Semua level</option>
          <option v-for="(nama, k) in (props.opsi?.level ?? {})" :key="k" :value="k">{{ nama }}</option>
        </select>

        <input v-model="cari" placeholder="Cari nomor, judul, lokasi…"
               class="rounded-lg border-stone-200 text-[12px] w-56" aria-label="Cari insiden">
      </header>

      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[11.5px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
              <th class="px-4 py-2 font-semibold w-10">No</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Tanggal</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">No. Insiden</th>
              <th class="px-4 py-2 font-semibold">Kejadian</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Lokasi</th>
              <th class="px-4 py-2 font-semibold text-right whitespace-nowrap">Skor</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Level</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Status</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Berkas</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="(b, i) in tersaring" :key="b.id" class="border-b border-stone-100 hover:bg-stone-50/60">
              <td class="px-4 py-2.5 num text-stone-400">{{ i + 1 }}</td>
              <td class="px-4 py-2.5 num text-stone-500 whitespace-nowrap">{{ b.tanggal }}</td>

              <td class="px-4 py-2.5 whitespace-nowrap">
                <Link :href="`/investigasi/insiden/${b.id}`"
                      class="num font-bold text-cam-lime-deep hover:underline">{{ b.nomor }}</Link>
              </td>

              <td class="px-4 py-2.5">
                <span class="text-cam-ink font-semibold">{{ b.judul }}</span>
                <span v-if="b.terlambatLapor" class="ml-2 rounded px-1.5 py-0.5 text-[10px] font-bold"
                      :style="{ background: KEADAAN.gawat + '1F', color: KEADAAN.gawat }">
                  lewat tenggat lapor
                </span>
              </td>

              <td class="px-4 py-2.5 text-stone-500 whitespace-nowrap">{{ b.lokasi || '—' }}</td>
              <td class="px-4 py-2.5 num text-right font-semibold">{{ b.skor ?? '—' }}</td>

              <td class="px-4 py-2.5 whitespace-nowrap">
                <span v-if="b.ditriase" class="rounded px-1.5 py-0.5 text-[10px] font-bold"
                      :style="{ background: b.warnaPita + '1F', color: b.warnaPita }">
                  {{ b.levelNama }}
                </span>
                <!-- Kekosongan disebut, bukan dibiarkan kosong: sel
                     kosong terbaca sebagai insiden ringan. -->
                <Link v-else :href="`/investigasi/insiden/${b.id}/triase`"
                      class="rounded px-1.5 py-0.5 text-[10px] font-bold hover:underline"
                      :style="{ background: KEADAAN.serius + '1F', color: KEADAAN.serius }">
                  belum ditriase →
                </Link>
              </td>

              <td class="px-4 py-2.5 text-stone-500 whitespace-nowrap">
                {{ LABEL_STATUS[b.status] ?? b.status }}
              </td>

              <td class="px-4 py-2.5 whitespace-nowrap">
                <Link v-if="b.invId" :href="`/investigasi/berkas/${b.invId}`"
                      class="num font-bold text-cam-lime-deep hover:underline">{{ b.invNomor }}</Link>
                <span v-else class="text-stone-300">—</span>
              </td>
            </tr>

            <tr v-if="!tersaring.length">
              <td colspan="9" class="px-4 py-10 text-center text-[12px] text-stone-400">
                {{ (props.baris ?? []).length ? 'Tidak ada yang cocok dengan pencarian.' : 'Belum ada insiden tercatat.' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

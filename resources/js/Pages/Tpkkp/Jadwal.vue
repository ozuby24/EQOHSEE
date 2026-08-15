<script setup lang="ts">
/**
 * PTPKKP — Jadwal.
 *
 * Rencana penilaian 30 hari. Yang didapat dari pemindahan ini: bilah
 * kemajuan di atas ikut bergerak begitu satu kegiatan dicentang, dan
 * perubahan yang belum tersimpan ditandai. Pada versi Blade keduanya
 * baru terlihat setelah tombol simpan ditekan dan halaman dimuat ulang —
 * padahal justru sebelum menyimpan itulah orang ingin tahu posisinya.
 */
import { computed, reactive, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PickerTpkkp from '../../Components/PickerTpkkp.vue';
import type { HalamanJadwal } from '../../types';

const props = defineProps<HalamanJadwal>();

/** idx → selesai. Kunci memakai idx dari server, bukan urutan tampil. */
const selesai = reactive<Record<number, boolean>>({});
const awal = ref('');

function muat() {
  for (const k of Object.keys(selesai)) delete selesai[Number(k)];
  for (const t of props.tahap) {
    for (const b of t.baris) selesai[b.idx] = b.selesai;
  }
  awal.value = JSON.stringify(selesai);
}
muat();
watch(() => props.tahap, muat);

const kotor = computed(() => JSON.stringify(selesai) !== awal.value);

const kemajuan = computed(() => {
  const nilai = Object.values(selesai);
  const tuntas = nilai.filter(Boolean).length;

  return {
    tuntas,
    jumlah: nilai.length,
    persen: nilai.length ? Math.round((tuntas / nilai.length) * 100) : 0,
  };
});

const menyimpan = ref(false);

function simpan() {
  menyimpan.value = true;

  // Bentuk kiriman sama dengan versi Blade — done[] berisi idx yang
  // tercentang — supaya saveJadwal() di server tidak perlu diubah.
  const done = Object.entries(selesai)
    .filter(([, v]) => v)
    .map(([k]) => Number(k));

  router.post(`/tpkkp/jadwal?tahun=${props.tahun}`, { done }, {
    preserveScroll: true,
    onSuccess: () => { awal.value = JSON.stringify(selesai); },
    onFinish:  () => { menyimpan.value = false; },
  });
}
</script>

<template>
  <Head title="PTPKKP — Jadwal" />

  <div class="max-w-6xl mx-auto space-y-5">

    <PickerTpkkp v-bind="picker" />

    <div class="bg-white rounded-2xl border border-stone-200 p-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h3 class="text-[13px] font-bold text-cam-ink">Jadwal Penilaian — 30 hari, 4 tahap</h3>
          <p class="text-[11.5px] text-stone-500 mt-0.5 num">
            {{ kemajuan.tuntas }} dari {{ kemajuan.jumlah }} kegiatan selesai
          </p>
        </div>
        <div class="w-48">
          <div class="h-2 rounded-full bg-stone-100 overflow-hidden">
            <div class="h-full rounded-full bg-cam-lime transition-all duration-300"
                 :style="{ width: kemajuan.persen + '%' }"></div>
          </div>
        </div>
      </div>
    </div>

    <div v-for="t in tahap" :key="t.nama"
         class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
      <div class="px-5 py-3 bg-cam-ink text-white">
        <h3 class="text-[12.5px] font-bold">{{ t.nama }}</h3>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-[12px] min-w-[720px]">
          <thead class="text-[10px] uppercase tracking-wider text-stone-400 bg-stone-50">
            <tr>
              <th class="text-left px-4 py-2 font-bold" style="width:34px"></th>
              <th class="text-left px-3 py-2 font-bold">Kegiatan</th>
              <th class="text-left px-3 py-2 font-bold">Keluaran</th>
              <th class="text-left px-4 py-2 font-bold" style="width:230px">Hari 1 – 30</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in t.baris" :key="b.idx" class="border-b border-stone-50"
                :class="selesai[b.idx] ? 'bg-cam-lime-soft/40' : ''">
              <td class="px-4 py-2">
                <input type="checkbox" v-model="selesai[b.idx]" :disabled="!bisaSunting"
                       class="w-4 h-4 accent-cam-lime-deep">
              </td>
              <td class="px-3 py-2" :class="selesai[b.idx] ? 'text-stone-400 line-through' : 'text-stone-700'">
                {{ b.kegiatan }}
              </td>
              <td class="px-3 py-2 text-stone-500">{{ b.keluaran }}</td>
              <td class="px-4 py-2">
                <div class="relative h-2.5 rounded-full bg-stone-100">
                  <div class="absolute h-2.5 rounded-full transition-colors"
                       :class="selesai[b.idx] ? 'bg-cam-lime' : 'bg-cam-ink/45'"
                       :style="{ left: b.kiri + '%', width: b.lebar + '%' }"></div>
                </div>
                <div class="text-[10px] text-stone-400 mt-1 num">hari {{ b.mulai }}–{{ b.akhir }}</div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="bisaSunting" class="flex items-center gap-3 flex-wrap">
      <button type="button" :disabled="menyimpan || !kotor" @click="simpan"
              class="lime-gradient shadow-glow rounded-xl text-white px-6 py-2.5 text-[13px] font-bold
                     hover:brightness-105 transition disabled:opacity-40 disabled:cursor-not-allowed">
        {{ menyimpan ? 'Menyimpan…' : 'Simpan tanda selesai' }}
      </button>
      <span v-if="kotor" class="text-[11.5px] text-amber-700 font-semibold">
        Ada perubahan yang belum tersimpan.
      </span>
    </div>
  </div>
</template>

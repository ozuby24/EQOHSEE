<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

/**
 * Penyaring nama dan status, dipakai daftar dan ketiga halaman aspek.
 *
 * Nilai kosong dibuang dari kueri, bukan dikirim sebagai string kosong:
 * `?cari=&status=` ikut tersalin ke tautan ekspor dan ke tombol
 * halaman berikutnya, dan alamat yang terlihat "sudah tersaring" padahal
 * tidak menyaring apa pun sulit dibedakan dari yang benar-benar menyaring.
 */
const props = defineProps<{
  aksi: string;
  awal: { cari: string; status: string };
  statusOpsi: Record<string, string>;
}>();

const cari = ref(props.awal.cari);
const status = ref(props.awal.status);

const adaSaringan = Boolean(props.awal.cari || props.awal.status);

function terapkan() {
  const kueri: Record<string, string> = {};
  if (cari.value) kueri.cari = cari.value;
  if (status.value) kueri.status = status.value;

  router.get(props.aksi, kueri, { preserveState: true, preserveScroll: true });
}

function bersihkan() {
  cari.value = '';
  status.value = '';
  router.get(props.aksi, {}, { preserveState: true, preserveScroll: true });
}
</script>

<template>
  <form class="rounded-2xl bg-white border border-stone-100 shadow-card p-4 flex flex-wrap items-end gap-3"
        @submit.prevent="terapkan">
    <!-- Kendalinya berada DI DALAM <label>, bukan bersebelahan dengannya.
         Label yang hanya berdampingan terbaca oleh mata tetapi tidak
         terikat pada kendalinya, sehingga pembaca layar menyebut
         "combo box" tanpa menyebut apa yang dipilih. -->
    <label class="min-w-[180px] flex-1 block text-[11px] font-semibold text-stone-500">Cari nama
      <input v-model="cari" type="text" placeholder="Nama perusahaan…"
             class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
    </label>

    <label class="min-w-[180px] block text-[11px] font-semibold text-stone-500">Status
      <select v-model="status" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        <option value="">Semua status</option>
        <option v-for="(label, nilai) in props.statusOpsi" :key="nilai" :value="nilai">{{ label }}</option>
      </select>
    </label>

    <div class="flex gap-2">
      <button type="submit" class="eq-btn-utama px-5">Terapkan</button>
      <button v-if="adaSaringan" type="button" class="eq-btn-lain px-5" @click="bersihkan">Bersihkan</button>
    </div>
  </form>
</template>

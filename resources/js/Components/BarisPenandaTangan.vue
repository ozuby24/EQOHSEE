<script setup lang="ts">
/**
 * Satu baris penanda tangan yang dapat disunting di tempat.
 *
 * Dipisah menjadi komponen sendiri karena tiap baris butuh keadaan
 * formulirnya sendiri — termasuk berkas tanda tangan yang sedang
 * dipilih. Satu useForm bersama akan membuat unggahan pada satu baris
 * ikut terkirim saat baris lain disimpan.
 */
import { router, useForm } from '@inertiajs/vue3';
import type { PenandaTangan } from '../types';

const props = defineProps<{ s: PenandaTangan; perusahaan?: Array<{ id: number; nama: string }> }>();

const form = useForm({
  name: props.s.nama,
  title: props.s.jabatan,
  company_id: props.s.perusahaanId ?? ('' as string | number),
  is_active: props.s.aktif,
  signature: null as File | null,
});

function pilihBerkas(e: Event) {
  const f = (e.target as HTMLInputElement).files;
  form.signature = f && f.length ? f[0] : null;
}

function simpan() {
  form.put(props.s.urlSimpan, { preserveScroll: true });
}

function hapus() {
  if (!confirm(`Hapus penanda tangan "${props.s.nama}"?`)) return;
  router.delete(props.s.urlHapus, { preserveScroll: true });
}

const kecil = 'ring-focus w-full rounded-lg border border-stone-200 px-3 py-2 text-[12.5px] transition';
const label = 'block text-[10.5px] font-bold uppercase tracking-wide text-stone-500 mb-1';
</script>

<template>
  <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
    <form class="grid sm:grid-cols-[1fr_1fr_auto] gap-3 items-end" @submit.prevent="simpan">
      <div>
        <label :class="label">Nama</label>
        <input v-model="form.name" :class="kecil">
      </div>
      <div>
        <label :class="label">Jabatan</label>
        <input v-model="form.title" :class="kecil">
      </div>

      <div class="sm:col-span-2">
        <label :class="label">Perusahaan pemilik</label>
        <select v-model="form.company_id" :class="kecil">
          <option value="">— penanda tangan pusat (semua perusahaan) —</option>
          <option v-for="c in perusahaan || []" :key="c.id" :value="c.id">{{ c.nama }}</option>
        </select>
      </div>
      <div class="flex items-center gap-2">
        <label class="flex items-center gap-1.5 text-[12px] text-stone-600 cursor-pointer">
          <input v-model="form.is_active" type="checkbox"
                 class="rounded border-stone-300 accent-[color:var(--eq-aksen,#F57C00)]">
          Aktif
        </label>
        <button type="submit" :disabled="form.processing"
                class="lime-gradient rounded-lg text-white px-3 py-2 text-[11.5px] font-bold
                       hover:brightness-105 disabled:opacity-40">
          {{ form.processing ? '…' : 'Simpan' }}
        </button>
      </div>

      <div class="sm:col-span-3 flex items-center gap-3 pt-2 border-t border-stone-100">
        <img v-if="s.tandaTangan" :src="s.tandaTangan" class="h-9" alt="Contoh tanda tangan">
        <input type="file" accept="image/*" class="text-[11.5px] text-stone-500" @change="pilihBerkas">
      </div>
    </form>

    <p v-if="Object.keys(form.errors).length" class="text-[11.5px] text-red-600 mt-2">
      {{ Object.values(form.errors).join(' · ') }}
    </p>

    <button type="button" @click="hapus"
            class="mt-2 text-[11.5px] font-semibold text-red-500 hover:bg-red-50 px-2.5 py-1 rounded-lg">
      Hapus
    </button>
  </div>
</template>

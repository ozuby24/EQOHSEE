<script setup lang="ts">
/**
 * Penanda tangan sertifikat.
 *
 * Yang berstatus aktif dipakai otomatis saat sertifikat diterbitkan,
 * jadi daftar ini menentukan nama yang tercetak pada lembar yang dipegang
 * peserta — bukan sekadar data induk.
 */
import { Head, useForm } from '@inertiajs/vue3';
import BarisPenandaTangan from '../../Components/BarisPenandaTangan.vue';
import type { HalamanPenandaTangan } from '../../types';

const props = defineProps<HalamanPenandaTangan>();

const baru = useForm({ name: '', title: '', is_active: true, signature: null as File | null });

function pilihBerkas(e: Event) {
  const f = (e.target as HTMLInputElement).files;
  baru.signature = f && f.length ? f[0] : null;
}

function tambah() {
  baru.post(props.tautan.tambah, {
    preserveScroll: true,
    onSuccess: () => baru.reset(),
  });
}

const kecil = 'ring-focus rounded-lg border border-stone-200 px-3 py-2 text-[12.5px] transition';
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-3xl mx-auto space-y-5">

    <p class="text-[12.5px] text-stone-400">
      Nama yang tercetak pada sertifikat. Yang berstatus
      <span class="font-semibold text-cam-lime-deep">aktif</span>
      dipakai otomatis saat sertifikat diterbitkan.
    </p>

    <div class="space-y-2.5">
      <BarisPenandaTangan v-for="s in penandaTangan" :key="s.id" :s="s" />

      <div v-if="!penandaTangan.length"
           class="bg-white rounded-2xl border border-dashed border-stone-200 p-12
                  text-center text-[13px] text-stone-400">
        Belum ada penanda tangan.
      </div>
    </div>

    <form class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 grid sm:grid-cols-2 gap-3"
          @submit.prevent="tambah">
      <div class="sm:col-span-2 text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">
        Tambah Baru
      </div>

      <input v-model="baru.name" placeholder="Nama lengkap" :class="kecil">
      <input v-model="baru.title" placeholder="Jabatan" :class="kecil">
      <input type="file" accept="image/*" class="text-[11.5px] text-stone-500 sm:col-span-2"
             @change="pilihBerkas">

      <label class="flex items-center gap-1.5 text-[12px] text-stone-600 cursor-pointer">
        <input v-model="baru.is_active" type="checkbox"
               class="rounded border-stone-300 accent-[color:var(--eq-aksen,#F57C00)]">
        Aktif
      </label>

      <button type="submit" :disabled="baru.processing || !baru.name.trim()"
              class="lime-gradient shadow-glow rounded-xl text-white py-2.5 text-[12.5px]
                     font-bold hover:brightness-105 disabled:opacity-40">
        {{ baru.processing ? 'Menyimpan…' : '+ Tambah' }}
      </button>

      <p v-if="Object.keys(baru.errors).length" class="sm:col-span-2 text-[11.5px] text-red-600">
        {{ Object.values(baru.errors).join(' · ') }}
      </p>
    </form>
  </div>
</template>

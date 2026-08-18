<script setup lang="ts">
/**
 * Formulir kursus.
 */
import { Head, useForm } from '@inertiajs/vue3';
import type { HalamanFormKursus } from '../../types';

const props = defineProps<HalamanFormKursus>();

const form = useForm<Record<string, any>>({ ...props.awal, image: null as File | null });

function pilihGambar(e: Event) {
  const f = (e.target as HTMLInputElement).files;
  form.image = f && f.length ? f[0] : null;
}

/** Huruf yang mudah tertukar (O/0, I/1) sengaja tidak dipakai. */
function acakKode() {
  const c = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  form.access_code = Array.from({ length: 6 }, () => c[Math.floor(Math.random() * c.length)]).join('');
}

function simpan() {
  form.transform((d) => ({ ...d, access_code: String(d.access_code).toUpperCase() }));

  if (props.tersimpan) form.put(props.tautan.simpan);
  else form.post(props.tautan.simpan);
}

const isian = 'ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition';
const label = 'block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5';
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-2xl mx-auto">
    <form class="bg-white rounded-2xl shadow-card border border-stone-100 p-6 space-y-5"
          @submit.prevent="simpan">

      <div v-if="Object.keys(form.errors).length"
           class="rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]">
        <ul class="space-y-0.5">
          <li v-for="(pesan, k) in form.errors" :key="k">• {{ pesan }}</li>
        </ul>
      </div>

      <div>
        <label :class="label">Judul</label>
        <input v-model="form.title" :class="isian">
      </div>
      <div>
        <label :class="label">Kategori</label>
        <input v-model="form.category" :class="isian">
      </div>
      <div>
        <label :class="label">Deskripsi</label>
        <textarea v-model="form.description" rows="4" :class="[isian, 'leading-relaxed']"></textarea>
      </div>
      <div>
        <label :class="label">Gambar sampul</label>
        <img v-if="gambar" :src="gambar" class="w-28 h-16 object-cover rounded-lg mb-2" alt="">
        <input type="file" accept="image/*" class="block text-[12.5px] text-stone-500" @change="pilihGambar">
        <p class="text-[11px] text-stone-400 mt-1">Maks 2 MB. Kosongkan jika tak ingin mengubah.</p>
      </div>

      <div class="rounded-xl border border-stone-200 p-4 space-y-3">
        <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">Kode Akses Kursus</p>
        <div class="grid sm:grid-cols-[1fr_auto] gap-3 items-end">
          <div>
            <label :class="label">Kode dari trainer</label>
            <input v-model="form.access_code" maxlength="20"
                   class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5
                          text-[15px] font-bold tracking-[0.25em] uppercase num transition">
          </div>
          <button type="button" @click="acakKode"
                  class="rounded-xl border border-stone-200 px-4 py-2.5 text-[12px] font-bold
                         text-stone-600 hover:border-cam-lime hover:bg-cam-lime-soft transition">Acak</button>
        </div>
        <label class="flex items-center gap-2.5 text-[13px] text-stone-600 cursor-pointer">
          <input v-model="form.require_code" type="checkbox"
                 class="rounded border-stone-300 accent-[color:var(--eq-aksen,#F57C00)]">
          <span class="font-semibold">Wajib memasukkan kode untuk mengambil kursus</span>
        </label>
        <p class="text-[11px] text-stone-400 leading-relaxed">
          Bila aktif, peserta harus memasukkan kode ini sebelum bisa membuka kursus.
          Admin dan trainer tetap bisa masuk tanpa kode.
        </p>
      </div>

      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label :class="label">Ragam sertifikat</label>
          <select v-model="form.cert_template" :class="isian" aria-label="Templat sertifikat">
            <option v-for="o in opsi.sertifikat" :key="o.nilai" :value="o.nilai">{{ o.label }}</option>
          </select>
        </div>
        <div class="flex flex-col justify-end gap-2 pb-1">
          <label class="flex items-center gap-2.5 text-[13px] text-stone-600 cursor-pointer">
            <input v-model="form.auto_certificate" type="checkbox"
                   class="rounded border-stone-300 accent-[color:var(--eq-aksen,#F57C00)]">
            <span class="font-semibold">Terbitkan sertifikat otomatis</span>
          </label>
          <label class="flex items-center gap-2.5 text-[13px] text-stone-600 cursor-pointer">
            <input v-model="form.require_evaluation" type="checkbox"
                   class="rounded border-stone-300 accent-[color:var(--eq-aksen,#F57C00)]">
            <span class="font-semibold">Tunggu evaluasi trainer</span>
          </label>
        </div>
      </div>
      <p class="text-[11px] text-stone-400 -mt-2 leading-relaxed">
        Alur: peserta menyelesaikan kursus &amp; kuis → muncul di daftar tunggu trainer →
        trainer menilai → sertifikat terbit dengan nilai dari evaluasi.
      </p>

      <div class="flex items-center gap-3 pt-1">
        <button type="submit" :disabled="form.processing"
                class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2.5 text-[12.5px]
                       font-bold hover:brightness-105 transition disabled:opacity-40">
          {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
        </button>
        <a :href="tautan.batal" class="text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink">Batal</a>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
/**
 * Inspeksi — buat dan ubah.
 *
 * Satu berkas untuk keduanya: dua salinan formulir yang sama pasti
 * berbeda isinya cepat atau lambat, dan medan yang hanya ada di satu
 * sisi hilang diam-diam saat disimpan lewat sisi yang lain.
 */
import { Head, useForm } from '@inertiajs/vue3';
import type { HalamanFormInspeksi } from '../../types';

const props = defineProps<HalamanFormInspeksi>();

const form = useForm({ ...props.awal });

function simpan() {
  if (props.sunting) form.put(props.tautan.simpan);
  else form.post(props.tautan.simpan);
}

const isian = 'ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px] transition';
const label = 'block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5';
</script>

<template>
  <Head :title="judul" />

  <form class="max-w-3xl mx-auto space-y-5" @submit.prevent="simpan">
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <div class="grid gap-3 sm:grid-cols-2">
        <div class="sm:col-span-2">
          <label :class="label">Judul inspeksi <span class="text-red-500">*</span></label>
          <input v-model="form.judul" :class="isian" placeholder="mis. Inspeksi APAR Area Workshop">
          <p v-if="form.errors.judul" class="text-[11.5px] text-red-600 mt-1">{{ form.errors.judul }}</p>
        </div>

        <div>
          <label :class="label">Jenis inspeksi</label>
          <select v-model="form.template_id" :class="isian" aria-label="Templat">
            <option value="">— tanpa jenis —</option>
            <option v-for="t in opsi.template" :key="t.id" :value="String(t.id)">
              {{ t.nama }} ({{ t.jumlahItem }} parameter)
            </option>
          </select>
          <p v-if="!sunting" class="text-[11px] text-stone-400 mt-1">
            Parameternya disalin otomatis saat inspeksi dibuat.
          </p>
        </div>

        <div>
          <label :class="label">Perusahaan</label>
          <select v-model="form.company_id" :class="isian" aria-label="Perusahaan">
            <option value="">— pilih perusahaan —</option>
            <option v-for="c in opsi.perusahaan" :key="c.id" :value="String(c.id)">{{ c.nama }}</option>
          </select>
        </div>

        <div>
          <label :class="label">Tanggal <span class="text-red-500">*</span></label>
          <input v-model="form.tanggal" type="date" :class="isian" aria-label="Tanggal">
          <p v-if="form.errors.tanggal" class="text-[11.5px] text-red-600 mt-1">{{ form.errors.tanggal }}</p>
        </div>

        <div>
          <label :class="label">Lokasi</label>
          <input v-model="form.lokasi" :class="isian">
        </div>

        <div class="sm:col-span-2">
          <label :class="label">Catatan</label>
          <textarea v-model="form.catatan" rows="3" :class="isian" style="resize:none"></textarea>
        </div>
      </div>
    </div>

    <div class="flex items-center gap-2">
      <button type="submit" :disabled="form.processing"
              class="eq-btn-utama disabled:opacity-40" style="flex:none;padding:11px 22px">
        {{ form.processing ? 'Menyimpan…' : (sunting ? 'Simpan Perubahan' : 'Buat Inspeksi') }}
      </button>
      <a :href="tautan.batal" class="px-4 py-2.5 text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink">
        Batal
      </a>
    </div>
  </form>
</template>

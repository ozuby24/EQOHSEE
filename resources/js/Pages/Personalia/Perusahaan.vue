<script setup lang="ts">
/**
 * Personalia — Data Perusahaan.
 *
 * Logo yang diunggah menentukan warna aksen seluruh aplikasi, jadi
 * pratinjaunya ditampilkan sebelum dikirim: memilih berkas yang keliru
 * di sini bukan hanya salah gambar, melainkan salah warna di setiap
 * halaman sampai ada yang mengganti lagi.
 */
import { computed, onBeforeUnmount, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import type { HalamanPerusahaan } from '../../types';

const props = defineProps<HalamanPerusahaan>();

const form = useForm<Record<string, any>>({ ...(props.isian ?? {}), logo: null as File | null });

const pratinjau = ref<string | null>(null);

function pilihLogo(e: Event) {
  const berkas = (e.target as HTMLInputElement).files?.[0] ?? null;

  if (pratinjau.value) URL.revokeObjectURL(pratinjau.value);
  pratinjau.value = berkas ? URL.createObjectURL(berkas) : null;
  form.logo = berkas;
}

onBeforeUnmount(() => { if (pratinjau.value) URL.revokeObjectURL(pratinjau.value); });

const gambar = computed(() => pratinjau.value ?? props.logo);

function simpan() {
  form.post('/personalia/perusahaan', {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      if (pratinjau.value) URL.revokeObjectURL(pratinjau.value);
      pratinjau.value = null;
      form.logo = null;
    },
  });
}

function hapusLogo() {
  if (!confirm('Hapus logo? Warna tampilan kembali ke bawaan EQOHSEE.')) return;

  router.delete('/personalia/perusahaan/logo', { preserveScroll: true });
}
</script>

<template>
  <Head title="Data Perusahaan" />

  <div class="max-w-[900px] mx-auto space-y-5">

    <div v-if="!ada" class="bg-white rounded-2xl shadow-card border border-stone-100 px-6 py-10 text-center">
      <p class="text-[14px] font-bold text-[#14385A]">Akun Anda belum terhubung ke perusahaan</p>
      <p class="text-[12.5px] text-stone-500 mt-1.5">
        Administrator dapat menautkannya lewat Administrasi &rarr; Kelola Pengguna.
      </p>
    </div>

    <div v-else class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      <div class="px-6 py-5 border-b border-stone-100">
        <h3 class="text-[15px] font-bold text-[#14385A]">{{ nama }}</h3>
        <p class="text-[12.5px] text-stone-500 mt-1">
          <template v-if="bisaSunting">
            Data ini tercetak pada kop dokumen, laporan audit, dan sertifikat.
          </template>
          <template v-else>
            Hanya administrator atau PIC perusahaan yang dapat mengubah data ini.
          </template>
        </p>
      </div>

      <div class="px-6 py-5 border-b border-stone-100">
        <div class="flex flex-wrap items-center gap-5">
          <div class="w-20 h-20 rounded-xl border border-stone-200 grid place-items-center bg-stone-50 shrink-0">
            <img v-if="gambar" :src="gambar" alt="" class="max-w-[68px] max-h-[68px] object-contain">
            <span v-else class="text-[11px] text-stone-400">Belum ada</span>
          </div>

          <div class="flex-1 min-w-[220px]">
            <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Logo Perusahaan</label>
            <input type="file" accept="image/jpeg,image/png,image/webp,image/svg+xml"
                   :disabled="!bisaSunting" @change="pilihLogo"
                   class="block w-full text-[12.5px] text-stone-600
                          file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0
                          file:text-[12px] file:font-semibold file:bg-stone-100 file:text-[#14385A]">
            <p v-if="pratinjau" class="text-[11.5px] text-amber-700 font-semibold mt-1.5">
              Logo baru dipilih — warna aksen baru dihitung setelah Anda menyimpan.
            </p>
            <p v-else class="text-[11.5px] text-stone-400 mt-1.5">
              Warna khas logo diambil otomatis dan dipakai sebagai aksen tampilan.
              SVG dan logo hitam-putih tetap tersimpan, hanya tidak mengubah warna.
            </p>
            <p v-if="form.errors.logo" class="text-[11.5px] text-red-600 mt-1">{{ form.errors.logo }}</p>
          </div>

          <button v-if="logoSendiri && bisaSunting" type="button" @click="hapusLogo"
                  class="text-[12px] font-semibold text-red-600 hover:underline">
            Hapus logo
          </button>
        </div>

        <div v-if="warna.length" class="mt-4 flex items-center gap-3 flex-wrap">
          <span class="text-[11.5px] text-stone-500">Warna yang terbaca dari logo:</span>
          <span v-for="w in warna" :key="w.nama" v-show="w.hex"
                class="inline-flex items-center gap-2 text-[11.5px] text-stone-600">
            <i class="w-5 h-5 rounded-md border border-stone-200 inline-block"
               :style="{ background: w.hex }"></i>{{ w.nama }} {{ w.hex }}
          </span>
        </div>
      </div>

      <div class="px-6 py-5 grid gap-4 sm:grid-cols-2">
        <div v-for="m in medan" :key="m.nama" :class="m.lebar ? 'sm:col-span-2' : ''">
          <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">
            {{ m.label }} <span v-if="m.wajib" class="text-red-500">*</span>
          </label>
          <input v-model="form[m.nama]" type="text" :disabled="!bisaSunting"
                 class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]
                        disabled:bg-stone-50 disabled:text-stone-500
                        focus:border-[color:var(--eq-aksen,#0E747E)] focus:ring-0">
          <p v-if="form.errors[m.nama]" class="text-[11.5px] text-red-600 mt-1">
            {{ form.errors[m.nama] }}
          </p>
        </div>

        <div class="sm:col-span-2">
          <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Alamat</label>
          <textarea v-model="form.address" rows="3" :disabled="!bisaSunting"
                    class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]
                           disabled:bg-stone-50 disabled:text-stone-500
                           focus:border-[color:var(--eq-aksen,#0E747E)] focus:ring-0"></textarea>
        </div>
      </div>

      <div v-if="bisaSunting"
           class="px-6 py-4 bg-stone-50 border-t border-stone-100 flex items-center justify-end gap-3">
        <span v-if="form.progress" class="text-[11.5px] text-stone-500 num">
          Mengunggah {{ form.progress.percentage }}%
        </span>
        <button type="button" :disabled="form.processing" @click="simpan"
                class="eq-btn-utama disabled:opacity-40 disabled:cursor-not-allowed"
                style="flex:none;padding:10px 22px">
          {{ form.processing ? 'Menyimpan…' : 'Simpan Perubahan' }}
        </button>
      </div>
    </div>
  </div>
</template>

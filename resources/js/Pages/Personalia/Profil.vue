<script setup lang="ts">
/**
 * Personalia — Data Diri.
 *
 * Halaman pertama dengan unggahan berkas di sisi Inertia. Berkas dikirim
 * lewat useForm; Inertia beralih ke multipart sendiri begitu ada File di
 * dalamnya, jadi tidak ada FormData yang perlu disusun tangan.
 *
 * Foto yang dipilih ditampilkan lebih dulu dari berkas lokal, sebelum
 * apa pun dikirim. Versi Blade baru memperlihatkan hasilnya setelah
 * unggahan selesai, sehingga memilih berkas yang keliru baru ketahuan
 * setelah foto lama telanjur terhapus dari penyimpanan.
 */
import { computed, onBeforeUnmount, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import type { HalamanProfilDiri } from '../../types';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
const { dialog, tanya, batal, lanjut } = useDialog();


const props = defineProps<HalamanProfilDiri>();

const form = useForm<Record<string, any>>({ ...props.isian, avatar: null as File | null });

/** Pratinjau berkas lokal; dicabut supaya objek URL-nya tidak menumpuk. */
const pratinjau = ref<string | null>(null);

function pilihFoto(e: Event) {
  const berkas = (e.target as HTMLInputElement).files?.[0] ?? null;

  if (pratinjau.value) URL.revokeObjectURL(pratinjau.value);
  pratinjau.value = berkas ? URL.createObjectURL(berkas) : null;
  form.avatar = berkas;
}

onBeforeUnmount(() => { if (pratinjau.value) URL.revokeObjectURL(pratinjau.value); });

const foto = computed(() => pratinjau.value ?? props.avatar);

function simpan() {
  form.post('/personalia', {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      if (pratinjau.value) URL.revokeObjectURL(pratinjau.value);
      pratinjau.value = null;
      form.avatar = null;
    },
  });
}

async function hapusFoto() {
  if (!await tanya('Hapus foto profil?')) return;

  router.delete('/personalia/avatar', { preserveScroll: true });
}
</script>

<template>
  <Head title="Data Diri" />

  <div class="max-w-[900px] mx-auto space-y-5">

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      <div class="px-6 py-5 border-b border-stone-100">
        <h3 class="text-[15px] font-bold text-cam-ink">Data Diri</h3>
        <p class="text-[12.5px] text-stone-500 mt-1">
          Nama dan jabatan di sini yang tercetak pada sertifikat serta laporan yang Anda terbitkan.
        </p>
      </div>

      <div class="px-6 py-5 border-b border-stone-100 flex flex-wrap items-center gap-5">
        <img v-if="foto" :src="foto" alt=""
             class="w-20 h-20 rounded-full object-cover border border-stone-200">
        <span v-else class="w-20 h-20 rounded-full grid place-items-center text-white text-2xl font-black"
              style="background:linear-gradient(135deg,var(--eq-aksen,#F57C00),#FF9800)">
          {{ inisial }}
        </span>

        <div class="flex-1 min-w-[220px]">
          <label class="block text-[12px] font-semibold text-cam-ink mb-1.5">Foto Profil</label>
          <input type="file" accept="image/jpeg,image/png,image/webp" @change="pilihFoto"
                 class="block w-full text-[12.5px] text-stone-600
                        file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0
                        file:text-[12px] file:font-semibold file:bg-stone-100 file:text-cam-ink">
          <p v-if="pratinjau" class="text-[11.5px] text-amber-700 font-semibold mt-1.5">
            Foto baru dipilih — belum tersimpan sampai Anda menekan Simpan Perubahan.
          </p>
          <p v-else class="text-[11.5px] text-stone-400 mt-1.5">JPG, PNG, atau WebP — paling besar 2 MB.</p>
          <p v-if="form.errors.avatar" class="text-[11.5px] text-red-600 mt-1">{{ form.errors.avatar }}</p>
        </div>

        <button v-if="avatar" type="button" @click="hapusFoto"
                class="text-[12px] font-semibold text-red-600 hover:underline">
          Hapus foto
        </button>
      </div>

      <div class="px-6 py-5 grid gap-4 sm:grid-cols-2">
        <div v-for="m in medan" :key="m.nama">
          <label class="block text-[12px] font-semibold text-cam-ink mb-1.5">
            {{ m.label }} <span v-if="m.wajib" class="text-red-500">*</span>
          </label>
          <input v-model="form[m.nama]" :type="m.tipe"
                 class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]
                        focus:border-[color:var(--eq-aksen,#F57C00)] focus:ring-0">
          <p v-if="form.errors[m.nama]" class="text-[11.5px] text-red-600 mt-1">
            {{ form.errors[m.nama] }}
          </p>
        </div>

        <div class="sm:col-span-2">
          <label class="block text-[12px] font-semibold text-cam-ink mb-1.5">Keterangan Singkat</label>
          <textarea v-model="form.bio" rows="3" maxlength="300"
                    placeholder="Ringkasan peran atau kompetensi Anda."
                    class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]
                           focus:border-[color:var(--eq-aksen,#F57C00)] focus:ring-0"></textarea>
          <p v-if="form.errors.bio" class="text-[11.5px] text-red-600 mt-1">{{ form.errors.bio }}</p>
        </div>
      </div>

      <div class="px-6 pb-5">
        <div class="rounded-xl bg-stone-50 border border-stone-100 px-4 py-3.5 flex items-center gap-3">
          <img v-if="perusahaan?.logo" :src="perusahaan.logo" alt="" class="w-10 h-10 object-contain">
          <div class="min-w-0">
            <p class="text-[12.5px] font-bold text-cam-ink">
              {{ perusahaan?.nama ?? 'Belum terhubung ke perusahaan' }}
            </p>
            <p class="text-[11.5px] text-stone-500">
              <template v-if="perusahaan">
                {{ perusahaan.jenis }}<template v-if="perusahaan.lokasi"> · {{ perusahaan.lokasi }}</template>
              </template>
              <template v-else>Hubungi administrator untuk menautkan akun Anda.</template>
            </p>
          </div>
          <Link :href="urlPerusahaan" class="ml-auto text-[12px] font-semibold shrink-0"
                style="color:var(--eq-aksen,#F57C00)">Lihat &rarr;</Link>
        </div>
      </div>

      <div class="px-6 py-4 bg-stone-50 border-t border-stone-100 flex items-center justify-end gap-3">
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

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>

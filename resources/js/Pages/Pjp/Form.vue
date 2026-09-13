<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import PjpNav from '../../Components/PjpNav.vue';

const props = defineProps<{
  judul: string;
  pjp: Record<string, any>;
  statusOpsi: Record<string, string>;
  perusahaans: Array<{ id: number; name: string }>;
  tautan: Record<string, string>;
}>();

/*
 * Tidak ada isian "tahapan" di sini, dan itu disengaja.
 *
 * Aplikasi asal punya kolom itu beserta tombol "Lanjutkan ke Tahap
 * Berikutnya", sehingga satu PJP hanya muncul di satu halaman aspek.
 * Ketiga aspek berjalan bersamaan — memilih salah satunya di formulir
 * pendaftaran berarti menyembunyikan dua aspek lain untuk perusahaan itu.
 */
const form = useForm({
  company_id:       props.pjp.company_id ?? '',
  nama_perusahaan:  props.pjp.nama_perusahaan ?? '',
  nib:              props.pjp.nib ?? '',
  penanggung_jawab: props.pjp.penanggung_jawab ?? '',
  alamat:           props.pjp.alamat ?? '',
  status:           props.pjp.status ?? 'aktif',
  catatan:          props.pjp.catatan ?? '',
});

function kirim() {
  if (props.tautan.metode === 'put') {
    form.put(props.tautan.kirim);
    return;
  }

  form.post(props.tautan.kirim);
}
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[900px] mx-auto space-y-5">
    <div>
      <!-- Judul dan nama modulnya digambar kop kerangka. Kalimat di
           bawah BUKAN subjudul yang sama: ia menerangkan urutan
           pengisian, yang tidak muat di kop. -->
      <p class="text-[12px] text-stone-500">
        Data induk perusahaan jasa. Checklist persyaratan, dokumen pelaporan, dan evaluasi kinerja diisi
        setelahnya dari halaman detail — ketiganya dapat diisi kapan saja, tanpa urutan.
      </p>
    </div>

    <PjpNav :tautan="props.tautan" aktif="daftar" />

    <form class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 grid gap-4 md:grid-cols-2"
          @submit.prevent="kirim">
      <label class="md:col-span-2">
        <span class="block text-[11px] font-semibold text-stone-500">Nama perusahaan</span>
        <input v-model="form.nama_perusahaan" required type="text" maxlength="255"
               class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        <small v-if="form.errors.nama_perusahaan" class="text-[11px] text-red-600">{{ form.errors.nama_perusahaan }}</small>
      </label>

      <label>
        <span class="block text-[11px] font-semibold text-stone-500">NIB</span>
        <input v-model="form.nib" type="text" maxlength="255"
               class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        <small v-if="form.errors.nib" class="text-[11px] text-red-600">{{ form.errors.nib }}</small>
      </label>

      <label>
        <span class="block text-[11px] font-semibold text-stone-500">Penanggung jawab</span>
        <input v-model="form.penanggung_jawab" type="text" maxlength="255"
               class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        <small v-if="form.errors.penanggung_jawab" class="text-[11px] text-red-600">{{ form.errors.penanggung_jawab }}</small>
      </label>

      <label class="md:col-span-2">
        <span class="block text-[11px] font-semibold text-stone-500">Alamat</span>
        <textarea v-model="form.alamat" rows="2" maxlength="1000"
                  class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></textarea>
        <small v-if="form.errors.alamat" class="text-[11px] text-red-600">{{ form.errors.alamat }}</small>
      </label>

      <label>
        <span class="block text-[11px] font-semibold text-stone-500">Status pemantauan</span>
        <select v-model="form.status" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
          <option v-for="(label, nilai) in props.statusOpsi" :key="nilai" :value="nilai">{{ label }}</option>
        </select>
        <small v-if="form.errors.status" class="text-[11px] text-red-600">{{ form.errors.status }}</small>
      </label>

      <!--
        Pemilih perusahaan hanya muncul bagi administrator; server tetap
        memaksakan perusahaan pengguna bagi yang lain, apa pun isi kiriman
        formulirnya.
      -->
      <label v-if="props.perusahaans.length">
        <span class="block text-[11px] font-semibold text-stone-500">Dipantau oleh</span>
        <select v-model="form.company_id" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
          <option value="">Belum ditentukan</option>
          <option v-for="p in props.perusahaans" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
      </label>

      <label class="md:col-span-2">
        <span class="block text-[11px] font-semibold text-stone-500">Catatan</span>
        <textarea v-model="form.catatan" rows="3" maxlength="3000"
                  class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></textarea>
        <small v-if="form.errors.catatan" class="text-[11px] text-red-600">{{ form.errors.catatan }}</small>
      </label>

      <div class="md:col-span-2 flex flex-wrap gap-2">
        <button :disabled="form.processing" class="eq-btn-utama px-6 !flex-none">
          {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
        </button>
        <Link :href="props.tautan.batal" class="eq-btn-lain px-6 !flex-none">Batal</Link>
      </div>
    </form>
  </div>
</template>

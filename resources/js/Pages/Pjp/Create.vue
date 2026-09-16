<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

const STATUS_OPTIONS: Record<string, string> = {
  aktif: 'Aktif Dipantau',
  perlu_tindak_lanjut: 'Perlu Tindak Lanjut',
  tidak_aktif: 'Tidak Aktif',
};

const form = useForm({
  nama_perusahaan: '',
  nib: '',
  penanggung_jawab: '',
  alamat: '',
  status: 'aktif',
  catatan: '',
});

function simpan() {
  form.post('/pjp');
}
</script>

<template>
  <Head title="Tambah PJP" />

  <div class="max-w-2xl mx-auto">
    <h2 class="font-serif text-xl font-bold text-cam-ink">Tambah PJP</h2>
    <p class="text-[12.5px] text-stone-500 mt-1 mb-6">Tambahkan data Perusahaan Jasa Pertambangan baru ke dalam sistem.</p>

    <form class="space-y-5" @submit.prevent="simpan">
      <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-6 space-y-5">
        <div>
          <label class="label">Nama Perusahaan</label>
          <input v-model="form.nama_perusahaan" class="input">
          <p v-if="form.errors.nama_perusahaan" class="text-[12px] text-red-600 mt-1">{{ form.errors.nama_perusahaan }}</p>
        </div>
        <div>
          <label class="label">NIB</label>
          <input v-model="form.nib" inputmode="numeric" maxlength="13" placeholder="13 digit angka" class="input">
          <p v-if="form.errors.nib" class="text-[12px] text-red-600 mt-1">{{ form.errors.nib }}</p>
          <p v-else class="text-[11.5px] text-stone-400 mt-1">Opsional — kalau diisi, harus 13 digit angka.</p>
        </div>
        <div>
          <label class="label">Penanggung Jawab</label>
          <input v-model="form.penanggung_jawab" class="input">
          <p v-if="form.errors.penanggung_jawab" class="text-[12px] text-red-600 mt-1">{{ form.errors.penanggung_jawab }}</p>
        </div>
        <div>
          <label class="label">Alamat</label>
          <textarea v-model="form.alamat" rows="3" class="input"></textarea>
          <p v-if="form.errors.alamat" class="text-[12px] text-red-600 mt-1">{{ form.errors.alamat }}</p>
        </div>
        <div>
          <label class="label">Status</label>
          <select v-model="form.status" class="input">
            <option v-for="(label, key) in STATUS_OPTIONS" :key="key" :value="key">{{ label }}</option>
          </select>
          <p v-if="form.errors.status" class="text-[12px] text-red-600 mt-1">{{ form.errors.status }}</p>
        </div>
        <div>
          <label class="label">Catatan</label>
          <textarea v-model="form.catatan" rows="3" class="input"></textarea>
          <p v-if="form.errors.catatan" class="text-[12px] text-red-600 mt-1">{{ form.errors.catatan }}</p>
        </div>
      </div>

      <div class="flex gap-3">
        <button type="submit" :disabled="form.processing"
                class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5 text-[12.5px] font-bold
                       disabled:opacity-50 transition">
          {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
        </button>
        <Link href="/pjp" class="rounded-xl border border-stone-200 px-4 py-2.5 text-[12.5px] font-semibold text-stone-600 hover:bg-stone-50">Batal</Link>
      </div>
    </form>
  </div>
</template>

<style scoped>
.label { display:block; margin-bottom:.375rem; font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#78716c }
.input { width:100%; border:1px solid #e7e5e4; border-radius:.75rem; background:#fff; padding:.6rem 1rem; font-size:.8125rem; transition:box-shadow .15s,border-color .15s }
.input:focus { outline:none; border-color:#f57c00; box-shadow:0 0 0 3px rgb(245 124 0 / .15) }
</style>

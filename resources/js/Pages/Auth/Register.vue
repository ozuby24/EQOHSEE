<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';
import InputSandi from '../../Components/InputSandi.vue';
import VerifikasiTurnstile from '../../Components/VerifikasiTurnstile.vue';

defineOptions({ layout: GuestLayout });

const props = defineProps<{
  companies: Array<{ id: number; name: string }>;
  departments: string[];
  positions: string[];

  /** Kunci situs Turnstile. null berarti verifikasinya tidak dipasang. */
  turnstile?: string | null;

  /** Penanda pintu dari Turnstile::TINDAKAN, agar tokennya terikat ke sini. */
  tindakan?: string | null;

  /**
   * Panjang sandi terpendek yang diterima, dari AturanSandi::MINIMAL.
   *
   * Dikirim server, tidak ditulis di sini. Angka yang ditulis di dua
   * tempat akan berbeda pada suatu hari, dan yang membacanya diberi
   * tahu batas yang salah oleh formulir yang menolaknya dengan batas
   * yang lain.
   */
  sandiMinimal: number;
}>();

const form = useForm({
  name: '', email: '', employee_id: '', department: '', position: '',
  company_id: '', password: '', password_confirmation: '',

  /* Nama kolomnya ditentukan Cloudflare — widget-nya mengisi kolom
     dengan nama persis ini, dan validasi di server mencarinya dengan
     nama yang sama. */
  'cf-turnstile-response': '',
});

/* Penghitung, bukan boolean. Tokennya sekali pakai: sandi yang tidak
   cocok atau surel yang sudah terdaftar sudah menghabiskannya, dan
   percobaan berikutnya akan ditolak karena verifikasinya — bukan karena
   hal yang barusan diperbaiki orangnya. Dua galat berturut-turut
   menghasilkan boolean yang sama, sehingga pengawasnya tidak menyala. */
const gagalKe = ref(0);

function daftar() {
  form.post('/register', {
    onError: () => { gagalKe.value += 1; },
    onFinish: () => form.reset('password', 'password_confirmation'),
  });
}
</script>

<template>
  <Head title="Daftar" />
  <h2 class="font-serif text-3xl font-semibold mb-1">Buat akun</h2>
  <p class="text-sm text-stone-500 mb-6">Daftar untuk mulai mengikuti pelatihan.</p>

  <div v-if="Object.keys(form.errors).length" class="mb-4 rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]"><ul class="space-y-0.5"><li v-for="(pesan, k) in form.errors" :key="k">• {{ pesan }}</li></ul></div>

  <form class="space-y-4" @submit.prevent="daftar">
    <div><label class="label">Nama lengkap</label><input v-model="form.name" autofocus required class="input"></div>
    <div><label class="label">Email</label><input v-model="form.email" type="email" required autocomplete="username" class="input"></div>
    <div class="grid grid-cols-2 gap-3"><div><label class="label">NRP / Employee ID</label><input v-model="form.employee_id" class="input"></div><div><label class="label">Departemen</label><input v-model="form.department" list="departments" class="input"><datalist id="departments"><option v-for="d in departments" :key="d" :value="d" /></datalist></div></div>
    <div><label class="label">Jabatan</label><select v-model="form.position" required class="input" aria-label="Jabatan"><option value="">— pilih jabatan —</option><option v-for="p in positions" :key="p" :value="p">{{ p }}</option></select></div>
    <div><label class="label">Perusahaan</label><select v-model="form.company_id" class="input" aria-label="Perusahaan"><option value="">— pilih perusahaan —</option><option v-for="c in companies" :key="c.id" :value="String(c.id)">{{ c.name }}</option></select></div>
    <div>
      <label class="label">Kata sandi</label>
      <InputSandi v-model="form.password" required autocomplete="new-password" kelas="input" label="baru" />
      <p class="petunjuk">Minimal {{ sandiMinimal }} huruf, tanpa syarat huruf besar atau angka. Kalimat pendek seperti &laquo;kopi pagi di tambang&raquo; lebih mudah diingat sekaligus lebih sulit ditebak daripada satu kata bercampur angka.</p>
    </div>
    <div><label class="label">Ulangi kata sandi</label><InputSandi v-model="form.password_confirmation" required autocomplete="new-password" kelas="input" label="ulangan" /></div>
    <VerifikasiTurnstile v-model="form['cf-turnstile-response']"
                         :kunci="props.turnstile ?? null" :tindakan="props.tindakan ?? null"
                         :galat="gagalKe" />
    <button type="submit" :disabled="form.processing" class="w-full rounded-xl bg-[#F57C00] hover:bg-[#DC6E00] text-white py-3 font-bold transition disabled:opacity-50">{{ form.processing ? 'Mendaftarkan…' : 'Daftar' }}</button>
  </form>
  <p class="text-center text-sm text-stone-500 mt-6">Sudah punya akun? <Link href="/login" class="font-bold text-[#D96500] hover:underline">Masuk</Link></p>
</template>

<style scoped>
.label { display:block; margin-bottom:.375rem; font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#78716c }
.input { width:100%; border:1px solid #e7e5e4; border-radius:.75rem; background:#fff; padding:.7rem 1rem; font-size:.875rem; transition:box-shadow .15s,border-color .15s }
.input:focus { outline:none; border-color:#f57c00; box-shadow:0 0 0 3px rgb(245 124 0 / .15) }
.petunjuk { margin:.4rem 0 0; font-size:11.5px; line-height:1.5; color:#78716c }
</style>

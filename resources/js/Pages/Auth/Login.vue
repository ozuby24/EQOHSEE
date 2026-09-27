<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';
import InputSandi from '../../Components/InputSandi.vue';
import Putaran from '../../Components/Putaran.vue';
import VerifikasiTurnstile from '../../Components/VerifikasiTurnstile.vue';
import { bersihkanSimpanan } from '../../lapangan/pekerja';

defineOptions({ layout: GuestLayout });

/* Kunci situs dari server. null berarti verifikasinya tidak dipasang,
   dan halaman ini menggambar dirinya seperti sebelum fitur itu ada. */
const kunciTurnstile = computed(
  () => (usePage().props as Record<string, unknown>).turnstile as string | null ?? null,
);

/* Penanda pintu, juga dari server. Lihat Turnstile::TINDAKAN. */
const tindakan = computed(
  () => (usePage().props as Record<string, unknown>).tindakan as string | null ?? null,
);

const form = useForm({
  email: '',
  password: '',
  remember: false,

  /* Namanya ditentukan Cloudflare, bukan kami — widget-nya mengisi kolom
     dengan nama persis ini, dan aturan validasi di server mencarinya
     dengan nama yang sama. */
  'cf-turnstile-response': '',
});

/* Penghitung, bukan boolean.
   Dua kali salah sandi berturut-turut menghasilkan boolean yang sama,
   sehingga widget-nya tidak disetel ulang pada percobaan kedua — dan
   tokennya sudah habis sejak percobaan pertama. */
const gagalKe = ref(0);

/* Salinan layar mode lapangan milik orang sebelumnya dibuang di sini:
   ponsel site sering dipakai bergantian, dan halaman masuk adalah
   satu-satunya tempat yang pasti dilewati di antara dua orang. */
onMounted(() => { void bersihkanSimpanan(); });

function masuk() {
  form.post('/login', {
    onError: () => { gagalKe.value += 1; },
    onFinish: () => form.reset('password'),
  });
}
</script>

<template>
  <Head title="Masuk" />

  <h2 class="auth-judul">Masuk ke platform</h2>
  <p class="auth-ket mb-7">Gunakan akun yang diberikan admin perusahaan Anda.</p>

  <div v-if="Object.keys(form.errors).length" class="mb-4 rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]">
    <ul class="space-y-0.5"><li v-for="(pesan, k) in form.errors" :key="k">• {{ pesan }}</li></ul>
  </div>

  <form class="space-y-4" @submit.prevent="masuk">
    <div>
      <label for="email" class="auth-label">Email</label>
      <input id="email" v-model="form.email" type="email" autocomplete="username" autofocus required class="auth-isian">
      <p v-if="form.errors.email" class="text-xs text-red-600 mt-1">{{ form.errors.email }}</p>
    </div>
    <div>
      <label for="password" class="auth-label">Kata sandi</label>
      <InputSandi id="password" v-model="form.password" autocomplete="current-password" required
                  kelas="auth-isian" />
      <p v-if="form.errors.password" class="text-xs text-red-600 mt-1">{{ form.errors.password }}</p>
    </div>
    <VerifikasiTurnstile v-model="form['cf-turnstile-response']"
                        :kunci="kunciTurnstile" :tindakan="tindakan" :galat="gagalKe" />

    <div class="flex items-center justify-between text-sm">
      <label class="flex items-center gap-2 text-[#3A4450] cursor-pointer min-h-[44px]"><input v-model="form.remember" type="checkbox" class="accent-[#F57C00] w-4 h-4 rounded"> Ingat perangkat ini</label>
      <Link href="/forgot-password" class="auth-tautan min-h-[44px] inline-flex items-center">Lupa sandi?</Link>
    </div>
    <!-- Tombolnya menyebut PEKERJAAN yang sedang berjalan, bukan
         "Memproses…". Yang menunggu di sambungan site yang lambat perlu
         tahu apa yang ditunggu: kredensialnya sedang diperiksa, bukan
         halamannya sedang digambar. Cincinnya bergerak supaya tombol
         yang terkunci tidak terbaca sebagai tombol yang rusak. -->
    <button type="submit" :disabled="form.processing" class="auth-tombol">
      <Putaran v-if="form.processing" />
      {{ form.processing ? 'Memeriksa kredensial…' : 'Masuk' }}
    </button>
  </form>

  <p class="text-center text-sm text-[#5E6875] mt-6">Belum punya akun? <Link href="/register" class="auth-tautan">Daftar di sini</Link></p>
</template>

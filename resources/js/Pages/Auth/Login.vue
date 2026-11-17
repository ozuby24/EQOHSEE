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

  <h2 class="ms-judul">Masuk</h2>
  <p class="ms-sub">Gunakan akun dari admin perusahaan Anda.</p>

  <div v-if="Object.keys(form.errors).length" class="mb-5 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-[13px]">
    <ul class="space-y-0.5"><li v-for="(pesan, k) in form.errors" :key="k">{{ pesan }}</li></ul>
  </div>

  <form class="space-y-5" @submit.prevent="masuk">
    <div>
      <label for="email" class="ms-label">Email</label>
      <input id="email" v-model="form.email" type="email" autocomplete="username" autofocus required class="ms-isian"
             :aria-invalid="form.errors.email ? 'true' : undefined">
    </div>
    <div>
      <label for="password" class="ms-label">Kata sandi</label>
      <InputSandi id="password" v-model="form.password" autocomplete="current-password" required kelas="ms-isian" />
    </div>
    <VerifikasiTurnstile v-model="form['cf-turnstile-response']"
                        :kunci="kunciTurnstile" :tindakan="tindakan" :galat="gagalKe" />

    <div class="flex items-center justify-between gap-4 text-[14.5px]">
      <label class="flex items-center gap-2.5 text-[#3A4450] cursor-pointer min-h-[44px]">
        <input v-model="form.remember" type="checkbox" class="accent-[#0B1117] w-[18px] h-[18px]"> Ingat perangkat ini
      </label>
      <Link href="/forgot-password" class="ms-tautan min-h-[44px] inline-flex items-center">Lupa sandi?</Link>
    </div>
    <!-- Tombolnya menyebut PEKERJAAN yang sedang berjalan, bukan
         "Memproses…". Yang menunggu di sambungan site yang lambat perlu
         tahu apa yang ditunggu: kredensialnya sedang diperiksa, bukan
         halamannya sedang digambar. Cincinnya bergerak supaya tombol
         yang terkunci tidak terbaca sebagai tombol yang rusak. -->
    <button type="submit" :disabled="form.processing" class="ms-tombol">
      <Putaran v-if="form.processing" />
      {{ form.processing ? 'Memeriksa kredensial…' : 'Masuk' }}
      <svg v-if="!form.processing" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-5-5 5 5-5 5" /></svg>
    </button>
  </form>

  <p class="mt-6 text-[14.5px] text-[#4A5563]">Belum punya akun? <Link href="/register" class="ms-tautan">Daftar sebagai pekerja</Link></p>
</template>

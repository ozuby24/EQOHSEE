<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';
import InputSandi from '../../Components/InputSandi.vue';
import Putaran from '../../Components/Putaran.vue';
import VerifikasiTurnstile from '../../Components/VerifikasiTurnstile.vue';
import { bersihkanSimpanan } from '../../lapangan/pekerja';
import { useBahasa } from '../../tamu/bahasa';

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

/* Masuk dengan Google/Microsoft: server hanya mengirim penyedia yang
   kredensial OAuth-nya terpasang. Tanpa satu pun, bagian ini tidak
   tergambar — tidak ada tombol yang tidak bekerja. */
const sso = computed(
  () => ((usePage().props as Record<string, unknown>).sso ?? { google: false, microsoft: false }) as { google: boolean; microsoft: boolean },
);
const adaSso = computed(() => sso.value.google || sso.value.microsoft);

const { t } = useBahasa();

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
  <Head :title="t('masuk.judul_tab')" />

  <h2 class="ms-judul">{{ t('masuk.judul') }} <span class="ms-judul-merek">EQOHSEE</span></h2>
  <p class="ms-sub">{{ t('masuk.sub') }}</p>

  <div v-if="Object.keys(form.errors).length" role="alert"
       class="mb-5 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-[13px]">
    <ul class="space-y-0.5"><li v-for="(pesan, k) in form.errors" :key="k">{{ pesan }}</li></ul>
  </div>

  <form class="space-y-5" @submit.prevent="masuk">
    <div>
      <label for="email" class="ms-label">{{ t('masuk.email') }}</label>
      <div class="ms-ikon-isian">
        <span class="ms-ikon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round">
            <rect x="3.2" y="5.2" width="17.6" height="13.6" rx="2.6" /><path d="m4.4 7.4 7.6 5.6 7.6-5.6" />
          </svg>
        </span>
        <input id="email" v-model="form.email" type="email" autocomplete="username" autofocus required
               :placeholder="t('masuk.email_contoh')" class="ms-isian ms-isian-ikon"
               :aria-invalid="form.errors.email ? 'true' : undefined">
      </div>
    </div>
    <div>
      <label for="password" class="ms-label">{{ t('masuk.sandi') }}</label>
      <div class="ms-ikon-isian">
        <span class="ms-ikon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round">
            <rect x="4.6" y="10.4" width="14.8" height="10.2" rx="2.6" /><path d="M8 10.4V7.8a4 4 0 0 1 8 0v2.6" /><circle cx="12" cy="15.5" r="1.3" fill="currentColor" stroke="none" />
          </svg>
        </span>
        <InputSandi id="password" v-model="form.password" autocomplete="current-password" required
                    :placeholder="t('masuk.sandi_isi')" kelas="ms-isian ms-isian-ikon" />
      </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-[14.5px]">
      <label class="ms-cek">
        <input v-model="form.remember" type="checkbox"> {{ t('masuk.ingat') }}
      </label>
      <Link href="/forgot-password" class="ms-tautan min-h-[44px] inline-flex items-center">{{ t('masuk.lupa') }}</Link>
    </div>
    <!-- Verifikasi Cloudflare Turnstile tepat sebelum tombol kirim — langkah
         terakhir sebelum masuk. Bertema terang: halaman tamu selalu
         terang, dan kotak bertema gelap di kartu putih terbaca sebagai
         sisipan asing. Tanpa kunci situs dari server, tidak tergambar. -->
    <VerifikasiTurnstile v-model="form['cf-turnstile-response']"
                        :kunci="kunciTurnstile" :tindakan="tindakan" :galat="gagalKe" tema="light" />
    <!-- Tombolnya menyebut PEKERJAAN yang sedang berjalan, bukan
         "Memproses…". Yang menunggu di sambungan site yang lambat perlu
         tahu apa yang ditunggu: kredensialnya sedang diperiksa, bukan
         halamannya sedang digambar. Cincinnya bergerak supaya tombol
         yang terkunci tidak terbaca sebagai tombol yang rusak. -->
    <button type="submit" :disabled="form.processing" class="ms-tombol">
      <Putaran v-if="form.processing" />
      <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
           stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M14 4h3.5A2.5 2.5 0 0 1 20 6.5v11a2.5 2.5 0 0 1-2.5 2.5H14" /><path d="M10 16.5 14.5 12 10 7.5" /><path d="M14.5 12H4" />
      </svg>
      {{ form.processing ? t('masuk.memeriksa') : t('masuk.tombol') }}
    </button>
  </form>

  <!-- Penyedia akun pihak ketiga. Tautan biasa, bukan kunjungan Inertia:
       alurnya berpindah ke Google/Microsoft lalu kembali. Berdampingan,
       bertuliskan nama penyedianya saja di bawah "atau masuk dengan";
       kalimat lengkapnya untuk pembaca layar (aria-label). -->
  <template v-if="adaSso">
    <p class="ms-atau">{{ t('masuk.atau') }}</p>
    <div class="ms-sso">
      <a v-if="sso.google" href="/masuk/google" class="ms-sso-tombol" :aria-label="t('masuk.google')">
        <svg viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z" /><path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z" /><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.5 39.6 16.2 44 24 44z" /><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z" /></svg>
        Google
      </a>
      <a v-if="sso.microsoft" href="/masuk/microsoft" class="ms-sso-tombol" :aria-label="t('masuk.microsoft')">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#F25022" d="M2 2h9.5v9.5H2z" /><path fill="#7FBA00" d="M12.5 2H22v9.5h-9.5z" /><path fill="#00A4EF" d="M2 12.5h9.5V22H2z" /><path fill="#FFB900" d="M12.5 12.5H22V22h-9.5z" /></svg>
        Microsoft
      </a>
    </div>
  </template>

  <p class="ms-daftar">{{ t('masuk.belum_punya') }} <Link href="/register" class="ms-tautan">{{ t('masuk.daftar') }}</Link></p>
</template>

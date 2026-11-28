<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';
import InputSandi from '../../Components/InputSandi.vue';
import VerifikasiTurnstile from '../../Components/VerifikasiTurnstile.vue';
import { useBahasa } from '../../tamu/bahasa';

defineOptions({ layout: GuestLayout });

const { t } = useBahasa();

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
  /* Satu-satunya perusahaan demo langsung terpilih: memilih dari
     daftar berisi satu hanya menambah satu ketukan yang tidak mengubah
     apa pun. */
  company_id: props.companies.length === 1 ? String(props.companies[0].id) : '',
  password: '', password_confirmation: '',

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
  <Head :title="t('daftar.judul_tab')" />
  <h2 class="auth-judul">{{ t('daftar.judul') }}</h2>
  <p class="auth-ket mb-7">{{ t('daftar.sub') }}</p>

  <div v-if="Object.keys(form.errors).length" class="mb-4 rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]"><ul class="space-y-0.5"><li v-for="(pesan, k) in form.errors" :key="k">• {{ pesan }}</li></ul></div>

  <form class="space-y-4" @submit.prevent="daftar">
    <div><label class="label">{{ t('daftar.nama') }}</label><input v-model="form.name" autofocus required class="input"></div>
    <div><label class="label">{{ t('masuk.email') }}</label><input v-model="form.email" type="email" required autocomplete="username" class="input"></div>
    <div class="grid grid-cols-2 gap-3"><div><label class="label">{{ t('daftar.nrp') }}</label><input v-model="form.employee_id" class="input"></div><div><label class="label">{{ t('daftar.departemen') }}</label><input v-model="form.department" list="departments" class="input"><datalist id="departments"><option v-for="d in departments" :key="d" :value="d" /></datalist></div></div>
    <div><label class="label">{{ t('daftar.jabatan') }}</label><select v-model="form.position" required class="input" :aria-label="t('daftar.jabatan')"><option value="">{{ t('daftar.pilih_jabatan') }}</option><option v-for="p in positions" :key="p" :value="p">{{ p }}</option></select></div>
    <!-- Akun baru HANYA memakai perusahaan demo (server menolak yang
         lain). Perusahaan sungguhan ditetapkan administrator sesudahnya,
         di Kelola Pengguna — dikatakan di sini supaya pendaftar tidak
         mencari nama perusahaannya sendiri di daftar ini. -->
    <div v-if="companies.length">
      <label class="label">{{ t('daftar.perusahaan_demo') }}</label>
      <select v-model="form.company_id" required class="input" :aria-label="t('daftar.perusahaan_demo')"><option value="">{{ t('daftar.pilih_perusahaan') }}</option><option v-for="c in companies" :key="c.id" :value="String(c.id)">{{ c.name }}</option></select>
      <p class="petunjuk">{{ t('daftar.petunjuk_demo') }}</p>
    </div>
    <p v-else class="petunjuk">{{ t('daftar.petunjuk_perusahaan') }}</p>
    <div>
      <label class="label">{{ t('daftar.sandi') }}</label>
      <InputSandi v-model="form.password" required autocomplete="new-password" kelas="input" label="baru" />
      <p class="petunjuk">{{ t('daftar.petunjuk_sandi', { n: sandiMinimal }) }}</p>
    </div>
    <div><label class="label">{{ t('daftar.ulangi_sandi') }}</label><InputSandi v-model="form.password_confirmation" required autocomplete="new-password" kelas="input" label="ulangan" /></div>
    <VerifikasiTurnstile v-model="form['cf-turnstile-response']"
                         :kunci="props.turnstile ?? null" :tindakan="props.tindakan ?? null"
                         :galat="gagalKe" tema="light" />
    <button type="submit" :disabled="form.processing" class="auth-tombol">{{ form.processing ? t('daftar.memproses') : t('daftar.tombol') }}</button>
  </form>
  <p class="text-center text-sm text-[#5E6875] mt-6">{{ t('daftar.sudah_punya') }} <Link href="/login" class="auth-tautan">{{ t('daftar.masuk') }}</Link></p>
</template>

<style scoped>
/* Label, isian, dan petunjuk memakai bentuk bersama dari app.css
   (.auth-label/.auth-isian) supaya seluruh pintu masuk seragam. */
.label { display:block; margin-bottom:.4rem; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:#5E6875 }
.input { width:100%; min-height:46px; border:1px solid #D9DEE4; border-radius:.75rem; background:#fff; padding:.65rem 1rem; font-size:14.5px; color:#0F1720; transition:box-shadow .15s,border-color .15s }
.input:focus { outline:none; border-color:var(--eq-aksen); box-shadow:0 0 0 3px color-mix(in srgb, var(--eq-aksen) 18%, transparent) }
.petunjuk { margin:.45rem 0 0; font-size:11.5px; line-height:1.5; color:#5E6875 }
</style>

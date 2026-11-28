<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';
import InputSandi from '../../Components/InputSandi.vue';
import VerifikasiTurnstile from '../../Components/VerifikasiTurnstile.vue';
import LogoPenyedia from '../../Components/Tamu/LogoPenyedia.vue';
import { useBahasa } from '../../tamu/bahasa';

defineOptions({ layout: GuestLayout });

const { t } = useBahasa();

type Penyedia = 'google' | 'microsoft';

const props = defineProps<{
  /**
   * Ada perusahaan demo tempat akun baru otomatis dimulai. Tidak ada
   * pilihan perusahaan di formulir ini: server yang menetapkannya
   * (RegisteredUserController::perusahaanDemoBawaan), dan pindah ke
   * perusahaan sungguhan adalah keputusan administrator.
   */
  adaDemo: boolean;
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

  /** Penyedia daftar pihak ketiga yang kredensialnya terpasang. */
  sso?: { google: boolean; microsoft: boolean };

  /**
   * Identitas yang baru dibuktikan Google/Microsoft dan menunggu formulir
   * ini diselesaikan (MasukPihakKetiga::identitas). Surelnya milik
   * penyedia: ditampilkan terkunci, dan server mengabaikan kolom surel
   * yang dikirim. Akun dari penyedia tidak memerlukan sandi.
   */
  identitas?: {
    penyedia: Penyedia; namaPenyedia: string; email: string; nama: string; dariMasuk: boolean;
  } | null;
}>();

const sso = computed(() => props.sso ?? { google: false, microsoft: false });
const adaSso = computed(() => sso.value.google || sso.value.microsoft);
const identitas = computed(() => props.identitas ?? null);

const form = useForm({
  name: props.identitas?.nama ?? '', email: props.identitas?.email ?? '',
  employee_id: '', department: '', position: '',
  password: '', password_confirmation: '',

  /* Penanda bahwa formulir ini diisi untuk identitas dari penyedia. Bila
     identitasnya sudah lewat waktu ketika formulirnya dikirim, server
     menjawab dengan pesan yang jelas — bukan "kata sandi wajib diisi"
     untuk kolom yang tidak pernah digambar. */
  lewat_sso: Boolean(props.identitas),

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
  <p class="auth-ket mb-7">{{ identitas ? t('daftar.sso_sub') : t('daftar.sub') }}</p>

  <div v-if="Object.keys(form.errors).length" class="mb-4 rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]"><ul class="space-y-0.5"><li v-for="(pesan, k) in form.errors" :key="k">• {{ pesan }}</li></ul></div>

  <!-- Identitas dari penyedia: surel yang sudah dibuktikan Google atau
       Microsoft, terkunci. "Pakai akun lain" membuang identitasnya dan
       mengembalikan formulir biasa. -->
  <template v-if="identitas">
    <div class="ms-identitas" role="group" :aria-label="t('daftar.sso_akun', { nama: identitas.namaPenyedia })">
      <span class="ms-identitas-logo"><LogoPenyedia :penyedia="identitas.penyedia" /></span>
      <span class="ms-identitas-teks">
        <b :title="identitas.email">{{ identitas.email }}</b>
        <small>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
          {{ t('daftar.sso_terverifikasi', { nama: identitas.namaPenyedia }) }}
        </small>
      </span>
      <Link href="/daftar/sso/batal" method="post" as="button" class="ms-tautan ms-identitas-ganti">{{ t('daftar.sso_ganti') }}</Link>
    </div>
    <p v-if="identitas.dariMasuk" class="ms-identitas-belum">
      {{ t('daftar.sso_belum_ada') }}
      <Link href="/daftar/sso/batal" method="post" as="button" :data="{ ke: 'masuk' }" class="ms-tautan">{{ t('daftar.sso_ke_masuk') }}</Link>
    </p>
  </template>

  <!-- Daftar dengan Google/Microsoft — di ATAS formulir: formulirnya
       panjang, dan jalan pintasnya tidak boleh tersembunyi di bawahnya.
       Tautan biasa, bukan kunjungan Inertia: alurnya berpindah ke
       penyedia lalu kembali. Hanya penyedia yang dipasang. -->
  <template v-else-if="adaSso">
    <div class="ms-sso">
      <a v-if="sso.google" href="/daftar/google" class="ms-sso-tombol" :aria-label="t('daftar.google')">
        <LogoPenyedia penyedia="google" />
        Google
      </a>
      <a v-if="sso.microsoft" href="/daftar/microsoft" class="ms-sso-tombol" :aria-label="t('daftar.microsoft')">
        <LogoPenyedia penyedia="microsoft" />
        Microsoft
      </a>
    </div>
    <p class="ms-atau ms-atau-formulir">{{ t('daftar.atau_email') }}</p>
  </template>

  <form class="space-y-4" @submit.prevent="daftar">
    <div class="grid gap-4" :class="identitas ? '' : 'xl:grid-cols-2 xl:gap-3'">
      <div><label class="label" for="daftar-nama">{{ t('daftar.nama') }}</label><input id="daftar-nama" v-model="form.name" :autofocus="!identitas" required autocomplete="name" class="input"></div>
      <div v-if="!identitas"><label class="label" for="daftar-email">{{ t('masuk.email') }}</label><input id="daftar-email" v-model="form.email" type="email" required autocomplete="username" class="input"></div>
    </div>
    <div class="grid grid-cols-2 gap-3"><div><label class="label" for="daftar-nrp">{{ t('daftar.nrp') }}</label><input id="daftar-nrp" v-model="form.employee_id" class="input"></div><div><label class="label" for="daftar-departemen">{{ t('daftar.departemen') }}</label><input id="daftar-departemen" v-model="form.department" list="departments" class="input"><datalist id="departments"><option v-for="d in departments" :key="d" :value="d" /></datalist></div></div>
    <!-- Tanpa pilihan perusahaan: akun baru otomatis masuk perusahaan
         demo, dan perusahaan sungguhan ditetapkan administrator
         sesudahnya di Kelola Pengguna — dikatakan di sini supaya
         pendaftar tidak mencari nama perusahaannya sendiri. -->
    <div>
      <label class="label" for="daftar-jabatan">{{ t('daftar.jabatan') }}</label><select id="daftar-jabatan" v-model="form.position" required class="input"><option value="">{{ t('daftar.pilih_jabatan') }}</option><option v-for="p in positions" :key="p" :value="p">{{ p }}</option></select>
      <p class="petunjuk">{{ adaDemo ? t('daftar.petunjuk_demo') : t('daftar.petunjuk_perusahaan') }}</p>
    </div>

    <!-- Akun dari penyedia masuk lewat tombol penyedianya: tidak ada
         kolom sandi. Sandi dapat dibuat kemudian lewat Lupa kata sandi. -->
    <p v-if="identitas" class="petunjuk ms-tanpa-sandi">{{ t('daftar.sso_tanpa_sandi', { nama: identitas.namaPenyedia }) }}</p>
    <div v-else>
      <div class="grid gap-4 xl:grid-cols-2 xl:gap-3">
        <div><label class="label" for="daftar-sandi">{{ t('daftar.sandi') }}</label><InputSandi id="daftar-sandi" v-model="form.password" required autocomplete="new-password" kelas="input" label="baru" /></div>
        <div><label class="label" for="daftar-ulangi">{{ t('daftar.ulangi_sandi') }}</label><InputSandi id="daftar-ulangi" v-model="form.password_confirmation" required autocomplete="new-password" kelas="input" label="ulangan" /></div>
      </div>
      <p class="petunjuk">{{ t('daftar.petunjuk_sandi', { n: sandiMinimal }) }}</p>
    </div>

    <VerifikasiTurnstile v-model="form['cf-turnstile-response']"
                         :kunci="props.turnstile ?? null" :tindakan="props.tindakan ?? null"
                         :galat="gagalKe" tema="light" />
    <button type="submit" :disabled="form.processing" class="auth-tombol">{{ form.processing ? t('daftar.memproses') : t('daftar.tombol') }}</button>
  </form>
  <p class="ms-daftar">{{ t('daftar.sudah_punya') }} <Link href="/login" class="auth-tautan">{{ t('daftar.masuk') }}</Link></p>
</template>

<style scoped>
/* Label, isian, dan petunjuk memakai bentuk bersama dari app.css
   (.auth-label/.auth-isian) supaya seluruh pintu masuk seragam. */
.label { display:block; margin-bottom:.4rem; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:#5E6875 }
.input { width:100%; min-height:46px; border:1px solid #D9DEE4; border-radius:.75rem; background:#fff; padding:.65rem 1rem; font-size:14.5px; color:#0F1720; transition:box-shadow .15s,border-color .15s }
.input:focus { outline:none; border-color:var(--eq-aksen); box-shadow:0 0 0 3px color-mix(in srgb, var(--eq-aksen) 18%, transparent) }
.petunjuk { margin:.45rem 0 0; font-size:11.5px; line-height:1.5; color:#5E6875 }
</style>

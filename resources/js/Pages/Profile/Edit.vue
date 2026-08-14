<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';

interface ProfileUser {
  name: string;
  email: string;
  verified: boolean;
}

const props = defineProps<{
  judul: string;
  subjudul: string;
  user: ProfileUser;
}>();

const profile = useForm<Record<string, string>>({
  name: props.user.name,
  email: props.user.email,
});

const password = useForm<Record<string, string>>({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const hapus = useForm<Record<string, string>>({ password: '' });
const modalHapus = ref(false);

function simpanProfil() {
  profile.patch('/profile', { preserveScroll: true });
}

function gantiSandi() {
  password.put('/password', {
    preserveScroll: true,
    onSuccess: () => password.reset(),
  });
}

function kirimVerifikasi() {
  router.post('/email/verification-notification', {}, { preserveScroll: true });
}

function hapusAkun() {
  hapus.delete('/profile', {
    preserveScroll: true,
    onSuccess: () => { modalHapus.value = false; },
  });
}
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[900px] mx-auto space-y-5">
    <section class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      <div class="px-6 py-5 border-b border-stone-100">
        <h2 class="text-[15px] font-bold text-[#0F1720]">Informasi Profil</h2>
        <p class="text-[12.5px] text-stone-500 mt-1">Perbarui nama dan alamat email akun Anda.</p>
      </div>
      <form class="px-6 py-5 space-y-4" @submit.prevent="simpanProfil">
        <div>
          <label for="profile-name" class="block text-[12px] font-semibold text-[#0F1720] mb-1.5">Nama</label>
          <input id="profile-name" v-model="profile.name" autocomplete="name" required
                 class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
          <p v-if="profile.errors.name" class="text-[11.5px] text-red-600 mt-1">{{ profile.errors.name }}</p>
        </div>
        <div>
          <label for="profile-email" class="block text-[12px] font-semibold text-[#0F1720] mb-1.5">Email</label>
          <input id="profile-email" v-model="profile.email" type="email" autocomplete="username" required
                 class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
          <p v-if="profile.errors.email" class="text-[11.5px] text-red-600 mt-1">{{ profile.errors.email }}</p>
          <p v-if="!props.user.verified" class="text-[12px] text-amber-700 mt-2">
            Email Anda belum diverifikasi.
            <button type="button" class="underline font-semibold" @click="kirimVerifikasi">Kirim ulang tautan verifikasi</button>
          </p>
        </div>
        <button type="submit" :disabled="profile.processing" class="eq-btn-utama disabled:opacity-40">
          {{ profile.processing ? 'Menyimpan…' : 'Simpan Perubahan' }}
        </button>
      </form>
    </section>

    <section class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      <div class="px-6 py-5 border-b border-stone-100">
        <h2 class="text-[15px] font-bold text-[#0F1720]">Ganti Kata Sandi</h2>
        <p class="text-[12.5px] text-stone-500 mt-1">Gunakan kata sandi panjang dan unik untuk menjaga keamanan akun.</p>
      </div>
      <form class="px-6 py-5 space-y-4" @submit.prevent="gantiSandi">
        <div v-for="field in [
          ['current_password', 'Kata Sandi Saat Ini'],
          ['password', 'Kata Sandi Baru'],
          ['password_confirmation', 'Konfirmasi Kata Sandi'],
        ]" :key="field[0]">
          <label :for="`password-${field[0]}`" class="block text-[12px] font-semibold text-[#0F1720] mb-1.5">{{ field[1] }}</label>
          <input :id="`password-${field[0]}`" v-model="password[field[0]]" type="password" required
                 class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
          <p v-if="password.errors[field[0]]" class="text-[11.5px] text-red-600 mt-1">{{ password.errors[field[0]] }}</p>
        </div>
        <button type="submit" :disabled="password.processing" class="eq-btn-utama disabled:opacity-40">
          {{ password.processing ? 'Menyimpan…' : 'Perbarui Kata Sandi' }}
        </button>
      </form>
    </section>

    <section class="bg-white rounded-2xl shadow-card border border-red-100 overflow-hidden">
      <div class="px-6 py-5">
        <h2 class="text-[15px] font-bold text-red-700">Hapus Akun</h2>
        <p class="text-[12.5px] text-stone-500 mt-1">Penghapusan akun bersifat permanen. Pastikan data yang diperlukan sudah disimpan.</p>
        <button type="button" class="mt-4 rounded-xl border border-red-200 px-4 py-2.5 text-[12px] font-semibold text-red-700 hover:bg-red-50" @click="modalHapus = true">
          Hapus Akun
        </button>
      </div>
    </section>
  </div>

  <div v-if="modalHapus" class="fixed inset-0 z-50 grid place-items-center bg-black/40 px-4" @click.self="modalHapus = false">
    <form class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" @submit.prevent="hapusAkun">
      <h2 class="text-lg font-bold text-[#0F1720]">Konfirmasi penghapusan akun</h2>
      <p class="mt-2 text-sm text-stone-600">Masukkan kata sandi untuk mengonfirmasi tindakan permanen ini.</p>
      <input v-model="hapus.password" type="password" autocomplete="current-password" required placeholder="Kata sandi"
             class="mt-5 w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
      <p v-if="hapus.errors.password" class="text-[11.5px] text-red-600 mt-1">{{ hapus.errors.password }}</p>
      <div class="mt-5 flex justify-end gap-3">
        <button type="button" class="rounded-xl border border-stone-200 px-4 py-2.5 text-[12px] font-semibold" @click="modalHapus = false">Batal</button>
        <button type="submit" :disabled="hapus.processing" class="rounded-xl bg-red-600 px-4 py-2.5 text-[12px] font-semibold text-white disabled:opacity-40">Hapus Permanen</button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
/**
 * Verifikasi email — enam kotak angka.
 *
 * Enam kotak terpisah, bukan satu isian panjang: angka yang sedang
 * diketik terlihat posisinya, dan salah ketik satu angka tidak perlu
 * menghapus seluruhnya. Tempel (paste) tetap dilayani sebagai satu
 * kesatuan, sebab orang menyalin kodenya utuh dari surel.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import type { HalamanVerifikasi } from '../../types';

const props = defineProps<HalamanVerifikasi>();

const angka = ref<string[]>(['', '', '', '', '', '']);
const kotak = ref<HTMLInputElement[]>([]);
const form  = useForm({ kode: '' });

const lengkap = computed(() => angka.value.every((a) => a !== ''));

function taruh(i: number, nilai: string) {
  const bersih = nilai.replace(/\D/g, '');

  if (!bersih) { angka.value[i] = ''; return; }

  // Tempel enam angka sekaligus: sebarkan ke kotak berikutnya.
  bersih.split('').forEach((d, n) => {
    if (i + n < 6) angka.value[i + n] = d;
  });

  const tujuan = Math.min(i + bersih.length, 5);
  kotak.value[tujuan]?.focus();

  if (lengkap.value) kirim();
}

function mundur(i: number, e: KeyboardEvent) {
  if (e.key !== 'Backspace' || angka.value[i]) return;

  // Kotak yang sudah kosong memindahkan hapusan ke kotak sebelumnya,
  // supaya menghapus terasa seperti pada satu isian utuh.
  e.preventDefault();
  angka.value[i - 1] = '';
  kotak.value[i - 1]?.focus();
}

function kirim() {
  form.kode = angka.value.join('');
  if (form.kode.length !== 6) return;

  form.post('/verify-email', {
    onError: () => {
      angka.value = ['', '', '', '', '', ''];
      kotak.value[0]?.focus();
    },
  });
}

/* Hitung mundur kirim ulang. Dijaga juga di server — tombol yang mati
   hanya menghalangi orang yang memakai halaman ini apa adanya. */
const sisa = ref(props.jeda);
let jam: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
  kotak.value[0]?.focus();
  jam = setInterval(() => { if (sisa.value > 0) sisa.value--; }, 1000);
});

onBeforeUnmount(() => { if (jam) clearInterval(jam); });

function kirimUlang() {
  if (sisa.value > 0) return;

  router.post('/email/verification-notification', {}, {
    preserveScroll: true,
    onSuccess: () => { sisa.value = 60; },
  });
}
</script>

<template>
  <Head title="Verifikasi Email" />

  <div class="max-w-md mx-auto">
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-7 text-center">

      <h2 class="text-[17px] font-bold text-cam-ink">Periksa surel Anda</h2>
      <p v-if="suratAktif" class="text-[12.5px] text-stone-500 mt-2 leading-relaxed">
        Kami mengirim kode 6 angka ke<br>
        <span class="font-bold text-cam-ink">{{ email }}</span>
      </p>
      <p v-else class="text-[12.5px] text-stone-500 mt-2 leading-relaxed">
        Kode 6 angka untuk<br>
        <span class="font-bold text-cam-ink">{{ email }}</span>
      </p>

      <!-- Dikatakan apa adanya. Menjanjikan surel yang tidak pernah
           dikirim membuat orang menunggu tanpa akhir, lalu menyalahkan
           kotak masuknya sendiri. -->
      <div v-if="!suratAktif"
           class="mt-4 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-left">
        <p class="text-[12px] font-bold text-amber-800">Pengiriman surel belum aktif di server ini</p>
        <p class="text-[11.5px] text-amber-700 mt-1 leading-relaxed">
          Kodenya sudah dibuat tetapi tidak dikirim ke mana pun. Hubungi administrator
          untuk memperoleh kodenya, atau agar akun Anda diverifikasi langsung.
        </p>
      </div>

      <div class="flex justify-center gap-2 mt-6" dir="ltr">
        <input v-for="(a, i) in angka" :key="i"
               :ref="(el) => { if (el) kotak[i] = el as HTMLInputElement }"
               :value="a"
               @input="taruh(i, ($event.target as HTMLInputElement).value)"
               @keydown="mundur(i, $event)"
               inputmode="numeric" autocomplete="one-time-code" maxlength="6"
               class="w-11 h-14 text-center text-[22px] font-bold num rounded-xl border-2
                      border-stone-200 text-cam-ink transition
                      focus:border-[color:var(--eq-aksen,#F57C00)] focus:ring-0"
               :class="form.errors.kode ? 'border-red-300' : ''">
      </div>

      <p v-if="form.errors.kode" class="text-[12px] text-red-600 mt-3">{{ form.errors.kode }}</p>
      <p v-else class="text-[11.5px] text-stone-400 mt-3">Kode berlaku {{ berlaku }} menit.</p>

      <button type="button" :disabled="!lengkap || form.processing" @click="kirim"
              class="eq-btn-utama w-full justify-center mt-5 disabled:opacity-40 disabled:cursor-not-allowed"
              style="padding:11px 18px">
        {{ form.processing ? 'Memeriksa…' : 'Verifikasi' }}
      </button>

      <div class="mt-5 pt-4 border-t border-stone-100">
        <button type="button" :disabled="sisa > 0" @click="kirimUlang"
                class="text-[12.5px] font-bold transition"
                :class="sisa > 0 ? 'text-stone-300 cursor-not-allowed'
                                 : 'text-[color:var(--eq-aksen,#F57C00)] hover:underline'">
          {{ sisa > 0 ? `Kirim ulang dalam ${sisa} detik` : 'Kirim ulang kode' }}
        </button>
      </div>

      <p v-if="hangus" class="text-[11.5px] text-amber-700 bg-amber-50 border border-amber-200
                              rounded-xl px-3 py-2.5 mt-4 leading-relaxed">
        Kode terkunci karena terlalu banyak percobaan. Minta kode baru untuk mencoba lagi.
      </p>
    </div>
  </div>
</template>

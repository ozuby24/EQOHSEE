<script setup lang="ts">
/**
 * Halaman kode, di antara sandi yang benar dan sesi yang hidup.
 *
 * Satu kolom untuk dua jenis kode — dari aplikasi autentikator DAN dari
 * daftar pemulihan. Dua kolom terpisah memaksa orang yang ponselnya
 * baru saja hilang memilih kolom yang benar lebih dulu, pada saat ia
 * paling tidak tenang. Servernya mencoba keduanya.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';
import { useBahasa } from '../../tamu/bahasa';

defineOptions({ layout: GuestLayout });

const { t } = useBahasa();

const props = defineProps<{
  surel?: string | null;
  sisaDetik: number;
}>();

const form = useForm({ kode: '' });
const batal = useForm({});

/* Hitung mundur yang terlihat. Setengah-masuk ini memang berumur lima
   menit, dan orang yang tidak diberi tahu batasnya akan menyalahkan
   kodenya ketika yang habis sebenarnya waktunya. */
const sisa = ref(props.sisaDetik);
let jam: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
  jam = setInterval(() => { if (sisa.value > 0) sisa.value -= 1; }, 1000);
});
onBeforeUnmount(() => { if (jam) clearInterval(jam); });

const waktu = computed(() => {
  const m = Math.floor(sisa.value / 60);
  const d = sisa.value % 60;
  return `${m}:${String(d).padStart(2, '0')}`;
});

function kirim() {
  form.post('/dua-faktor', { onFinish: () => form.reset('kode') });
}
</script>

<template>
  <Head :title="t('dua.judul_tab')" />

  <h2 class="auth-judul">{{ t('dua.judul') }}</h2>
  <p class="auth-ket mb-7">
    {{ t('dua.sub') }}<span v-if="props.surel">
      {{ t('dua.untuk') }} <span class="font-semibold text-stone-700">{{ props.surel }}</span></span>.
  </p>

  <div v-if="Object.keys(form.errors).length" class="mb-4 rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]">
    <ul class="space-y-0.5"><li v-for="(pesan, k) in form.errors" :key="k">• {{ pesan }}</li></ul>
  </div>

  <form class="space-y-4" @submit.prevent="kirim">
    <div>
      <label for="kode" class="label">{{ t('dua.kode') }}</label>
      <input id="kode" v-model="form.kode" required autofocus autocomplete="one-time-code"
             inputmode="text" placeholder="123456"
             class="input kode">
      <p class="petunjuk">
        {{ t('dua.petunjuk_1') }} <strong>{{ t('dua.kode_pemulihan') }}</strong>
        {{ t('dua.petunjuk_2') }}
        <code>a1b2c-d3e4f</code>. {{ t('dua.petunjuk_3') }}
      </p>
    </div>

    <button type="submit" :disabled="form.processing"
            class="auth-tombol">
      {{ form.processing ? t('dua.memproses') : t('dua.tombol') }}
    </button>
  </form>

  <p class="mt-5 text-center text-[12px] text-stone-500">
    <span v-if="sisa > 0">{{ t('dua.sisa_waktu') }} <span class="tabular-nums font-semibold">{{ waktu }}</span>.</span>
    <span v-else>{{ t('dua.habis') }}</span>
  </p>

  <p class="text-center text-sm text-stone-500 mt-3">
    <button type="button" class="auth-tautan"
            @click="batal.post('/dua-faktor/batal')">
      {{ t('dua.akun_lain') }}
    </button>
  </p>
</template>

<style scoped>
.label { display:block; margin-bottom:.4rem; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:#5E6875 }
.input { width:100%; min-height:46px; border:1px solid #D9DEE4; border-radius:.75rem; background:#fff; padding:.65rem 1rem; font-size:14.5px; color:#0F1720; transition:box-shadow .15s,border-color .15s }
.input:focus { outline:none; border-color:var(--eq-aksen); box-shadow:0 0 0 3px color-mix(in srgb, var(--eq-aksen) 15%, transparent) }

/* Angka berjarak dan monospasi: enam angka rapat sulit diperiksa ulang
   sebelum ditekan, dan yang paling sering salah adalah angka kembar. */
.kode { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:1.05rem; letter-spacing:.22em; text-align:center }

.petunjuk { margin:.5rem 0 0; font-size:11.5px; line-height:1.55; color:#78716c }
.petunjuk code { background:rgb(0 0 0 / .05); border-radius:.25rem; padding:0 .25rem; font-size:11px }
</style>

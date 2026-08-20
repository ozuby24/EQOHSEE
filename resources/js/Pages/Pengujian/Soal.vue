<script setup lang="ts">
/**
 * Pengujian (metode PJ) — satu soal per layar.
 *
 * ── Pencacah waktu di sini MEMBERI TAHU, tidak menegakkan ──
 *
 * Batas waktunya ditegakkan server: sisa detiknya dihitung dari batas
 * yang disimpan server, dan jawaban yang tiba lewat batas ditolak di
 * sana. Yang di layar ini hanya menampilkannya. Bedanya penting —
 * pencacah yang menegakkan dapat dihentikan dari konsol peramban dengan
 * satu baris, dan memuat ulang halaman akan mengembalikannya ke 90.
 * Di sini memuat ulang tidak menambah waktu sedetik pun, sebab yang
 * digambar adalah sisa waktu menurut server.
 *
 * ── Kunci jawaban tidak ada di halaman ini ──
 *
 * Yang diterima halaman ini hanyalah teks soal dan pilihannya yang
 * sudah diacak. Tidak ada penanda mana yang benar, karena tidak ada
 * yang dikirim — memeriksanya terjadi di server setelah pilihan
 * dikirim. Membuka View Source tidak memberi apa pun.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
  token: string;
  nomor: number;
  jumlahSoal: number;
  pertanyaan: string;
  pilihan: string[];
  detikPerSoal: number;
  sisaDetik: number;
}>();

const HURUF = 'ABCDEFGHIJ';

const pilih = ref<number | null>(null);
const sisa = ref(props.sisaDetik);
const mengirim = ref(false);

/* Berapa kali peserta meninggalkan layar ini. Bukan penilaian —
   membuka notifikasi bukan kecurangan — melainkan angka yang ditaruh
   di layar admin apa adanya, supaya sebaran nilai yang aneh punya
   sesuatu untuk dibandingkan. Dibawa serta tiap kali menjawab agar
   tidak hilang bersama halaman. */
const pindah = ref(0);

let jam: number | undefined;

const menit = computed(() => {
  const s = Math.max(0, sisa.value);
  return `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`;
});

const persen = computed(() =>
  Math.max(0, Math.min(100, (sisa.value / Math.max(1, props.detikPerSoal)) * 100)));

const mendesak = computed(() => sisa.value <= 20);

function kirim() {
  if (mengirim.value) return;
  mengirim.value = true;
  if (jam) window.clearInterval(jam);

  router.post(`/uji/${props.token}/jawab`, {
    nomor: props.nomor,
    pilih: pilih.value,
    pindah: pindah.value,
  }, {
    /* preserveState mati: soal berikutnya harus mengganti komponen ini
       seluruhnya, termasuk pilihan yang tersorot dan pencacahnya. */
    preserveState: false,
    preserveScroll: false,
  });
}

function saatTersembunyi() {
  if (document.hidden) pindah.value++;
}

onMounted(() => {
  document.addEventListener('visibilitychange', saatTersembunyi);

  jam = window.setInterval(() => {
    sisa.value--;
    if (sisa.value <= 0) kirim();    // habis waktu → dikirim tanpa jawaban bila kosong
  }, 1000);

  if (sisa.value <= 0) kirim();
});

onBeforeUnmount(() => {
  document.removeEventListener('visibilitychange', saatTersembunyi);
  if (jam) window.clearInterval(jam);
});
</script>

<template>
  <Head :title="`Soal ${nomor} dari ${jumlahSoal}`" />

  <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
    <!-- Bilah waktu, melekat di atas kartu soal -->
    <div class="px-5 pt-4">
      <div class="flex items-center justify-between text-[11.5px] mb-1.5">
        <span class="font-bold text-stone-500">Soal {{ nomor }} dari {{ jumlahSoal }}</span>
        <span class="num font-bold tabular-nums"
              :class="mendesak ? 'text-red-600' : 'text-stone-500'">{{ menit }}</span>
      </div>
      <div class="h-2 rounded-full bg-stone-100 overflow-hidden">
        <div class="h-full rounded-full transition-[width] duration-1000 ease-linear"
             :class="mendesak ? 'bg-red-500' : 'lime-gradient'"
             :style="{ width: `${persen}%` }"></div>
      </div>
    </div>

    <div class="p-5 sm:p-6">
      <h1 class="text-[15.5px] font-bold text-cam-ink leading-relaxed">{{ pertanyaan }}</h1>

      <div class="grid gap-2.5 mt-5" role="radiogroup" :aria-label="pertanyaan">
        <label v-for="(o, i) in pilihan" :key="i"
               class="flex gap-3 items-start rounded-xl border px-4 py-3 cursor-pointer transition"
               :class="pilih === i
                 ? 'border-cam-lime-deep bg-lime-50/60 ring-1 ring-cam-lime-deep'
                 : 'border-stone-200 hover:border-stone-300 hover:bg-stone-50'">
          <input type="radio" name="pilihan" class="sr-only" :value="i" v-model="pilih">
          <span class="shrink-0 w-6 h-6 rounded-lg grid place-items-center text-[11.5px] font-bold"
                :class="pilih === i ? 'bg-cam-lime-deep text-white' : 'bg-stone-100 text-stone-500'">
            {{ HURUF[i] }}
          </span>
          <span class="text-[13px] text-stone-700 leading-relaxed">{{ o }}</span>
        </label>
      </div>

      <div class="mt-6 flex flex-wrap items-center gap-3">
        <!--
          Tombol yang mati harus TERLIHAT mati. Kelas bersama
          `eq-btn-utama` tidak punya gaya :disabled, dan menambahkannya
          di sana akan mengubah tampilan tombol di seluruh aplikasi —
          jadi dipasang di sini saja. Tanpa ini, peserta yang belum
          memilih jawaban menekan tombol yang tampak hidup dan tidak
          terjadi apa-apa; yang disimpulkannya adalah halaman ini rusak.
        -->
        <button type="button" class="eq-btn-utama disabled:opacity-40 disabled:cursor-not-allowed"
                :disabled="pilih === null || mengirim" @click="kirim">
          {{ nomor === jumlahSoal ? 'Selesai & kirim' : 'Soal berikutnya →' }}
        </button>
        <span class="text-[11.5px] text-stone-400">
          Jawaban tidak dapat diubah setelah lanjut. Bila waktunya habis, soal berpindah sendiri.
        </span>
      </div>
    </div>
  </div>
</template>

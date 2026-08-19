<script setup lang="ts">
/**
 * Dialog penegasan di dalam halaman.
 *
 * Menggantikan `confirm()` dan `prompt()` bawaan peramban, yang tidak
 * dapat diandalkan di tempat aplikasi ini justru dipakai: pada webview
 * ponsel dan tablet, keduanya kerap dibungkam oleh aplikasi induknya.
 * Yang dikembalikan bukan galat melainkan `false` dan `null` — sama
 * persis dengan "pengguna menekan Batal".
 *
 * Akibatnya tidak terlihat sebagai kerusakan. Tombolnya ditekan, tidak
 * ada dialog yang muncul, tidak ada pesan, dan tidak ada yang terjadi.
 * Dari sisi pemakainya, fiturnya sekadar mati.
 *
 * Karena itu penegasan yang benar-benar menjaga sesuatu tidak boleh
 * bergantung pada dialog bawaan. Yang di sini digambar oleh aplikasinya
 * sendiri, jadi ia muncul di mana pun aplikasinya muncul.
 *
 * `tegasNama` untuk tindakan yang perlu lebih dari satu ketukan: teks
 * yang diketik harus sama dengan nilainya sebelum tombolnya hidup.
 * Perbandingannya memaafkan huruf besar-kecil dan spasi di ujung —
 * yang diminta kesengajaan, bukan ketepatan mengetik.
 */
import { computed, nextTick, ref, watch } from 'vue';

const props = withDefaults(defineProps<{
  terbuka: boolean;
  judul: string;
  pesan?: string;
  /** Bila diisi, pengguna harus mengetik ulang teks ini. */
  tegasNama?: string | null;
  labelAksi?: string;
  /** 'bahaya' untuk tindakan yang membuang data. */
  nada?: 'utama' | 'bahaya';
  sibuk?: boolean;
}>(), {
  pesan: '',
  tegasNama: null,
  labelAksi: 'Lanjutkan',
  nada: 'utama',
  sibuk: false,
});

const emit = defineEmits<{ batal: []; lanjut: [] }>();

const ketikan = ref('');
const kolom = ref<HTMLInputElement | null>(null);

const cocok = computed(() => {
  if (!props.tegasNama) return true;
  return ketikan.value.trim().toLowerCase() === props.tegasNama.trim().toLowerCase();
});

watch(() => props.terbuka, async (buka) => {
  if (!buka) return;
  ketikan.value = '';
  await nextTick();
  kolom.value?.focus();
});

function lanjut() {
  if (!cocok.value || props.sibuk) return;
  emit('lanjut');
}
</script>

<template>
  <!--
    Latarnya menutup seluruh layar dan menangkap klik di luar kotak.
    Esc juga membatalkan — dialog yang hanya dapat ditutup lewat satu
    tombol kecil adalah jebakan di layar sempit.
  -->
  <div v-if="terbuka"
       class="fixed inset-0 z-[60] grid place-items-center bg-black/50 p-4"
       role="dialog" aria-modal="true" :aria-label="judul"
       @click.self="emit('batal')" @keydown.esc="emit('batal')">

    <div class="w-full max-w-md rounded-2xl bg-white shadow-xl border border-stone-100 p-5">
      <h3 class="text-[15px] font-bold text-cam-ink">{{ judul }}</h3>

      <p v-if="pesan" class="mt-2 text-[12.5px] leading-relaxed text-stone-600 whitespace-pre-line">{{ pesan }}</p>

      <div v-if="tegasNama" class="mt-4">
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">
          Ketik <span class="text-cam-ink">{{ tegasNama }}</span> untuk menegaskan
        </label>
        <input ref="kolom" v-model="ketikan" type="text" autocomplete="off"
               class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-3 text-[13px]"
               :aria-label="`Ketik ${tegasNama} untuk menegaskan`"
               @keydown.enter.prevent="lanjut">
      </div>

      <div class="mt-5 flex flex-wrap gap-2 justify-end">
        <button type="button" class="eq-btn-lain" style="flex:none" :disabled="sibuk"
                @click="emit('batal')">Batal</button>

        <button type="button" style="flex:none"
                :class="nada === 'bahaya' ? 'eq-btn-tolak' : 'eq-btn-utama'"
                :disabled="!cocok || sibuk"
                :style="{ flex: 'none', opacity: (!cocok || sibuk) ? 0.4 : 1 }"
                @click="lanjut">
          {{ sibuk ? 'Memproses…' : labelAksi }}
        </button>
      </div>
    </div>
  </div>
</template>

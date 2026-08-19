<script setup lang="ts">
/**
 * Dialog penegasan dan isian, digambar di dalam halaman.
 *
 * Menggantikan `confirm()` dan `prompt()` bawaan peramban, yang tidak
 * dapat diandalkan di tempat aplikasi ini justru dipakai: pada webview
 * ponsel dan tablet, keduanya kerap dibungkam oleh aplikasi induknya.
 * Yang dikembalikan bukan galat melainkan `false` dan `null` — sama
 * persis dengan "pengguna menekan Batal".
 *
 * Akibatnya tidak terlihat sebagai kerusakan. Tombolnya ditekan, tidak
 * ada dialog yang muncul, tidak ada pesan, dan tidak ada yang terjadi.
 * Dari sisi pemakainya, fiturnya sekadar mati. Terjadi sungguhan pada
 * panel data contoh, dan diukur: seluruh panelnya mati sementara sisi
 * servernya sehat sepenuhnya.
 *
 * Tiga bentuk yang dilayani:
 *
 *   penegasan  — hanya Batal/Lanjutkan (pengganti `confirm`)
 *   isian      — satu kolom yang nilainya dikembalikan (pengganti `prompt`)
 *   tegasNama  — pengguna harus mengetik ulang sebuah nama sebelum
 *                tombolnya hidup, untuk tindakan yang membuang banyak data
 *
 * Yang dipakai halaman biasanya bukan komponen ini langsung melainkan
 * `useDialog()` di resources/js/dialog.ts — bentuk pemanggilannya
 * sengaja dibuat semirip mungkin dengan `confirm`/`prompt` yang
 * digantikannya.
 */
import { computed, nextTick, ref, watch } from 'vue';

const props = withDefaults(defineProps<{
  terbuka: boolean;
  judul: string;
  pesan?: string;

  /** Bila diisi, pengguna harus mengetik ulang teks ini. */
  tegasNama?: string | null;

  /** Bila diisi, dialog menampilkan satu kolom isian dengan label ini. */
  isianLabel?: string | null;
  isianJenis?: 'teks' | 'panjang' | 'tanggal' | 'angka';
  isianNilai?: string;
  isianWajib?: boolean;
  /** Panjang minimum isian, disamakan dengan aturan di server. */
  isianMin?: number;
  /** Kolom hanya untuk disalin, bukan diisi. */
  isianBaca?: boolean;

  labelAksi?: string;
  nada?: 'utama' | 'bahaya';
  sibuk?: boolean;
}>(), {
  pesan: '',
  tegasNama: null,
  isianLabel: null,
  isianJenis: 'teks',
  isianNilai: '',
  isianWajib: false,
  isianMin: 0,
  isianBaca: false,
  labelAksi: 'Lanjutkan',
  nada: 'utama',
  sibuk: false,
});

const emit = defineEmits<{ batal: []; lanjut: [nilai: string] }>();

const nilai = ref('');
const kolom = ref<HTMLInputElement | HTMLTextAreaElement | null>(null);

const bolehLanjut = computed(() => {
  if (props.tegasNama) {
    return nilai.value.trim().toLowerCase() === props.tegasNama.trim().toLowerCase();
  }
  if (props.isianLabel) {
    const t = nilai.value.trim();
    if (props.isianWajib && t === '') return false;
    /* Batas yang sama dengan aturan server. Tanpa ini penolakannya baru
       ketahuan sesudah satu perjalanan bolak-balik, dan yang diketik
       pengguna sudah hilang bersama dialognya. */
    if (props.isianMin && t !== '' && t.length < props.isianMin) return false;
  }
  return true;
});

watch(() => props.terbuka, async (buka) => {
  if (!buka) return;
  nilai.value = props.tegasNama ? '' : (props.isianNilai ?? '');
  await nextTick();
  kolom.value?.focus();
  if (props.isianBaca) (kolom.value as HTMLInputElement | null)?.select();
});

function lanjut() {
  if (!bolehLanjut.value || props.sibuk) return;
  emit('lanjut', nilai.value.trim());
}

/* Enter mengirim, kecuali pada isian panjang — di sana Enter memang
   dipakai untuk berganti baris, dan alasan penolakan sering lebih dari
   satu kalimat. */
function tekanEnter(e: KeyboardEvent) {
  if (props.isianJenis === 'panjang') return;
  e.preventDefault();
  lanjut();
}

const kelasKolom =
  'ring-focus w-full rounded-xl border border-stone-200 px-4 py-3 text-[13px] transition';
</script>

<template>
  <!--
    Latarnya menutup seluruh layar dan menangkap klik di luar kotak. Esc
    juga membatalkan — dialog yang hanya dapat ditutup lewat satu tombol
    kecil adalah jebakan di layar sempit.
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
        <input ref="kolom" v-model="nilai" type="text" autocomplete="off" :class="kelasKolom"
               :aria-label="`Ketik ${tegasNama} untuk menegaskan`"
               @keydown.enter="tekanEnter">
      </div>

      <div v-else-if="isianLabel" class="mt-4">
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">
          {{ isianLabel }}<span v-if="isianWajib" class="text-red-500"> *</span>
        </label>

        <p v-if="isianMin" class="text-[11px] text-stone-400 mb-1.5">
          Sekurang-kurangnya {{ isianMin }} huruf.
        </p>

        <textarea v-if="isianJenis === 'panjang'" ref="kolom" v-model="nilai" rows="3"
                  :class="kelasKolom" :aria-label="isianLabel"></textarea>

        <input v-else ref="kolom" v-model="nilai" autocomplete="off"
               :type="isianJenis === 'tanggal' ? 'date' : isianJenis === 'angka' ? 'number' : 'text'"
               :readonly="isianBaca" :class="kelasKolom" :aria-label="isianLabel"
               @keydown.enter="tekanEnter">
      </div>

      <div class="mt-5 flex flex-wrap gap-2 justify-end">
        <button type="button" class="eq-btn-lain" style="flex:none" :disabled="sibuk"
                @click="emit('batal')">Batal</button>

        <button type="button"
                :class="nada === 'bahaya' ? 'eq-btn-tolak' : 'eq-btn-utama'"
                :disabled="!bolehLanjut || sibuk"
                :style="{ flex: 'none', opacity: (!bolehLanjut || sibuk) ? 0.4 : 1 }"
                @click="lanjut">
          {{ sibuk ? 'Memproses…' : labelAksi }}
        </button>
      </div>
    </div>
  </div>
</template>

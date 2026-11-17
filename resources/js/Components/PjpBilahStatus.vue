<script setup lang="ts">
import { computed } from 'vue';

/**
 * Sebaran status seluruh PJP sebagai satu bilah bertumpuk.
 *
 * Ketiga status selalu tergambar pada daftar keterangan di bawah bilah,
 * termasuk yang berjumlah nol. Yang hilang saat kosong membuat pembaca
 * menjumlahkan dua angka dan mendapati totalnya tidak cocok — pernah
 * terjadi persis begitu: total 7 dengan dua kartu berjumlah 6, dan
 * selisihnya tidak dapat dijelaskan dari layar mana pun.
 */
const props = defineProps<{
  judul: string;
  jumlah: Record<string, number>;
  opsi: Record<string, string>;
}>();

const URUT = ['aktif', 'perlu_tindak_lanjut', 'tidak_aktif'];

const WARNA: Record<string, { isi: string; dari: string; teks: string }> = {
  aktif:               { isi: '#0ca30c', dari: '#4ade80', teks: '#ffffff' },
  perlu_tindak_lanjut: { isi: '#fab219', dari: '#fde047', teks: '#1e293b' },
  tidak_aktif:         { isi: '#94a3b8', dari: '#cbd5e1', teks: '#1e293b' },
};

const total = computed(() => URUT.reduce((n, k) => n + (props.jumlah[k] ?? 0), 0));

const segmen = computed(() => URUT
  .map((kunci) => ({
    kunci,
    jumlah: props.jumlah[kunci] ?? 0,
    persen: total.value === 0 ? 0 : ((props.jumlah[kunci] ?? 0) / total.value) * 100,
  }))
  .filter((s) => s.jumlah > 0));

const persenUntuk = (kunci: string) => total.value === 0
  ? 0
  : Math.round(((props.jumlah[kunci] ?? 0) / total.value) * 100);
</script>

<template>
  <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
    <div class="mb-3 flex items-baseline justify-between gap-3">
      <h3 class="font-bold text-[14px] text-stone-800">{{ props.judul }}</h3>
      <span class="text-[11px] text-stone-400">{{ total }} PJP</span>
    </div>

    <div v-if="total === 0" class="h-6 rounded-full bg-stone-100"></div>

    <div v-else class="flex h-6 w-full gap-[3px] overflow-hidden rounded-full bg-stone-200/60 shadow-inner">
      <div
        v-for="s in segmen"
        :key="s.kunci"
        class="flex h-full items-center justify-center first:rounded-l-full last:rounded-r-full"
        :style="{
          flexGrow: s.jumlah,
          flexBasis: 0,
          backgroundImage: `linear-gradient(90deg, ${WARNA[s.kunci].dari}, ${WARNA[s.kunci].isi})`,
        }"
      >
        <span v-if="s.persen >= 15" class="text-[11px] font-bold leading-none"
              :style="{ color: WARNA[s.kunci].teks }">{{ Math.round(s.persen) }}%</span>
      </div>
    </div>

    <dl class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-3">
      <div v-for="kunci in URUT" :key="kunci" class="flex items-center gap-2 text-[12px]">
        <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: WARNA[kunci].isi }"></span>
        <span class="text-stone-600">{{ props.opsi[kunci] ?? kunci }}</span>
        <span class="ml-auto font-bold text-stone-800">{{ persenUntuk(kunci) }}%</span>
      </div>
    </dl>
  </div>
</template>

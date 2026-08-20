<script setup lang="ts">
/**
 * Sel masa berlaku — tanggal, sisa hari, dan dari mana tanggalnya.
 *
 * TIGA BARIS, dan ketiganya menjawab pertanyaan berbeda. Tanggalnya
 * menjawab "sampai kapan"; sisa harinya menjawab "seberapa mendesak" —
 * sebab "2026-11-22" tidak memberitahu siapa pun bahwa itu tiga hari
 * lagi; dan sumbernya menjawab "kenapa segitu".
 *
 * BARIS KETIGA YANG PALING SERING HILANG. Mine Permit gugur bersama
 * MCU-nya dan SIMPER gugur bersama SIM-nya, sehingga tanggal yang
 * tampil kerap BUKAN yang tercetak pada kartunya. Tanpa keterangan
 * sumbernya, orang memperpanjang kartunya — padahal yang perlu
 * diperpanjang MCU-nya, dan kartunya akan tetap gugur pada tanggal yang
 * sama.
 */
import { computed } from 'vue';

const props = defineProps<{
  /** Tanggal efektif — yang benar-benar berlaku, bukan yang tercetak. */
  tanggal: string | null;
  /** Sisa hari; negatif berarti sudah lewat. Null = belum bertanggal. */
  sisaHari?: number | null;
  /** 'MCU' / 'SIM kepolisian' bila dibatasi berkas lain. */
  sumber?: string | null;
  /** Tanggal yang tercetak pada kartunya, bila berbeda dari efektif. */
  tglKartu?: string | null;
}>();

/* Pita warna sama dengan seluruh modul: merah sudah lewat, oranye ≤30
   hari, kuning 31–60, hijau di atasnya, abu belum bertanggal. Ambangnya
   sama persis dengan yang dipakai server — dua ambang untuk satu makna
   adalah cara tercepat membuat layar dan laporan berselisih. */
const zona = computed(() => {
  const s = props.sisaHari;

  if (props.tanggal === null || s === null || s === undefined) return 'kosong';
  if (s < 0)  return 'expired';
  if (s <= 30) return 'kritis';
  if (s <= 60) return 'waspada';

  return 'aman';
});

const WARNA: Record<string, { latar: string; teks: string }> = {
  expired: { latar: '#FEE2E2', teks: '#B91C1C' },
  kritis:  { latar: '#FFEDD5', teks: '#C2410C' },
  waspada: { latar: '#FEF9C3', teks: '#A16207' },
  aman:    { latar: '#DCFCE7', teks: '#15803D' },
  kosong:  { latar: '#F5F5F4', teks: '#78716C' },
};

const sisa = computed(() => {
  const s = props.sisaHari;
  if (s === null || s === undefined) return null;

  return s < 0 ? `lewat ${Math.abs(s)} hari` : `${s} hari lagi`;
});
</script>

<template>
  <span class="inline-block">
    <span class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-[11px] font-bold num"
          :style="{ background: WARNA[zona].latar, color: WARNA[zona].teks }">
      {{ props.tanggal ?? 'Belum diisi' }}
    </span>

    <small v-if="sisa" class="block text-[10.5px] mt-0.5" :style="{ color: WARNA[zona].teks }">
      {{ sisa }}
    </small>

    <!--
      Sumbernya, bukan hanya tanggalnya. Tanpa baris ini orang
      memperpanjang kartunya padahal yang habis MCU-nya — dan kartunya
      akan tetap gugur pada tanggal yang sama.
    -->
    <small v-if="props.sumber" class="block text-[10.5px]" style="color:#92400E">
      ikut {{ props.sumber }}
      <span v-if="props.tglKartu" class="text-stone-400">· kartu {{ props.tglKartu }}</span>
    </small>
    <small v-else-if="props.tanggal" class="block text-[10.5px] text-stone-400">
      sesuai kartu
    </small>
  </span>
</template>

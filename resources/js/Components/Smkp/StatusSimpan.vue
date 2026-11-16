<script setup lang="ts">
/**
 * Keadaan simpan otomatis (smkp/lib/autosimpan) dan tawaran memulihkan
 * ketikan yang belum tersimpan — port DBEST. Dipasang di bilah simpan
 * formulir panjang Audit SMKP.
 */
import type { KeadaanSimpan } from '../../smkp/lib/autosimpan';

defineProps<{
  keadaan: KeadaanSimpan;
  teks: string;
  pulihan: { waktu: string } | null;
  gelap?: boolean;
}>();
defineEmits<{ pulihkan: []; buang: [] }>();

const waktu = (iso: string) => new Date(iso).toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
</script>

<template>
  <div v-if="pulihan" class="fixed left-1/2 top-20 z-40 w-[min(560px,calc(100%-32px))] -translate-x-1/2 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 shadow-angkat text-[13px] text-amber-900" role="alert">
    <p class="font-semibold">Ada ketikan yang belum tersimpan ({{ waktu(pulihan.waktu) }}).</p>
    <p class="mt-0.5 text-[12.5px]">Pulihkan untuk memasangnya kembali ke formulir, lalu simpan — atau buang bila tidak diperlukan.</p>
    <div class="mt-2 flex gap-2">
      <button type="button" class="btn-utama btn-kecil" @click="$emit('pulihkan')">Pulihkan ketikan</button>
      <button type="button" class="btn-garis btn-kecil" @click="$emit('buang')">Buang</button>
    </div>
  </div>
  <p v-if="teks" class="text-[11.5px] leading-snug" role="status"
     :class="keadaan === 'bentrok' || keadaan === 'galat' ? (gelap ? 'text-amber-300 font-semibold' : 'text-rose-600 font-semibold') : (gelap ? 'text-white/70' : 'text-slate-500')">
    {{ teks }}
  </p>
</template>

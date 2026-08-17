<script setup lang="ts">
/**
 * Baris berjalur: judul, keterangan, status, dan tombol alurnya.
 *
 * Dipakai field break, cuti, dan campaign. Ketiganya berbeda isinya
 * tetapi identik alurnya — draf → diajukan → disetujui/ditolak, dengan
 * OHSE sebagai satu-satunya penentu — dan menyalin tombolnya tiga kali
 * berarti tiga tempat yang harus diubah bersama setiap kali aturannya
 * bergeser. Yang tertinggal tidak menimbulkan galat, hanya satu halaman
 * yang diam-diam mengizinkan apa yang dua halaman lain tolak.
 *
 * Isinya diserahkan ke slot; yang dipegang komponen ini hanya statusnya.
 */
import { KEADAAN } from '../../Grafik/warna';

const props = defineProps<{
  status: string;
  statusLabel: string;
  dapatDiubah: boolean;
  dapatDitinjau: boolean;
  alasanTolak?: string | null;

  /** Ditampilkan menonjol ketika benar — "sedang berlangsung". */
  aktif?: boolean;
  aktifLabel?: string;
}>();

const emit = defineEmits<{
  (e: 'ajukan'): void;
  (e: 'tinjau', aksi: 'setujui' | 'tolak' | 'tarik'): void;
  (e: 'hapus'): void;
}>();

const WARNA: Record<string, string> = {
  draf:      KEADAAN.netral,
  diajukan:  KEADAAN.ingat,
  disetujui: KEADAAN.baik,
  ditolak:   KEADAAN.gawat,
};
</script>

<template>
  <div class="py-3">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="min-w-0 flex-1"><slot /></div>

      <div class="text-right shrink-0">
        <p class="text-[11.5px] font-bold" :style="{ color: WARNA[props.status] ?? KEADAAN.netral }">
          {{ props.statusLabel }}
        </p>
        <!-- "Sedang berlangsung" adalah keadaan waktu, bukan keadaan
             alur. Dipisah supaya tidak terbaca sebagai status kelima. -->
        <p v-if="props.aktif" class="text-[10.5px] font-semibold mt-0.5"
           :style="{ color: KEADAAN.serius }">{{ props.aktifLabel ?? 'Sedang berlangsung' }}</p>
      </div>
    </div>

    <p v-if="props.alasanTolak" class="text-[11px] text-red-600 mt-1">
      Ditolak: {{ props.alasanTolak }}
    </p>

    <div class="flex flex-wrap items-center gap-3 mt-2">
      <slot name="aksi" />

      <button v-if="props.dapatDiubah" type="button"
              class="text-[11px] font-semibold text-cam-lime-deep" @click="emit('ajukan')">
        Ajukan
      </button>
      <button v-if="props.dapatDitinjau" type="button" class="text-[11px] font-semibold"
              :style="{ color: KEADAAN.baik }" @click="emit('tinjau', 'setujui')">Setujui</button>
      <button v-if="props.dapatDitinjau" type="button"
              class="text-[11px] font-semibold text-red-600" @click="emit('tinjau', 'tolak')">Tolak</button>
      <button v-if="props.status === 'diajukan'" type="button"
              class="text-[11px] text-stone-500" @click="emit('tinjau', 'tarik')">Tarik</button>
      <button v-if="props.dapatDiubah" type="button"
              class="text-[11px] text-red-600 ml-auto" @click="emit('hapus')">Hapus</button>
    </div>
  </div>
</template>

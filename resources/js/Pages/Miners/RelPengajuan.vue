<script setup lang="ts">
/**
 * Rel satu pengajuan — draf, paraf berjenjang, terbit.
 *
 * MENYATUKAN TIGA HAL YANG DULU TERSERAK di tiga sudut kartu yang sama:
 * lencana status di pojok kanan, kotak kuning "Belum dapat diajukan" di
 * tengah, dan tombol Ajukan jauh di bawah rantai paraf. Ketiganya
 * menjawab satu pertanyaan — "sekarang giliran siapa, dan apa yang
 * kurang" — dan yang membacanya harus menyusunnya sendiri.
 *
 * Yang paling merugikan: kotak kuningnya hanya muncul selama kartunya
 * masih dapat diubah. Pengajuan yang sudah dikirim karena itu tidak
 * menyebut apa pun tentang giliran siapa sekarang, dan yang menunggunya
 * menyimpulkan berkasnya hilang.
 *
 * Keadaan tiap simpul datang dari server, bukan dihitung di sini:
 * menghitungnya di layar berarti menyalin aturan "paraf mana yang sudah
 * lewat" ke tempat kedua, dan tempat kedua akan berselisih pada hari
 * rantainya berubah — tanpa satu pun galat.
 */
import { KEADAAN } from '../../Grafik/warna';

defineProps<{
  alur: {
    rel: Array<{ kunci: string; label: string; tugas: string; lewat: boolean; kini: boolean; nanti: boolean }>;
    tugas: string;
    kurang: string[];
    bolehAjukan: boolean;
    ditolak: boolean;
    selesai: boolean;
  };
  sibuk?: boolean;
}>();

const emit = defineEmits<{ (e: 'ajukan'): void }>();
</script>

<template>
  <div class="rounded-xl overflow-hidden border"
       :style="alur.ditolak
         ? { borderColor: '#FECACA', background: '#FEF2F2' }
         : { borderColor: 'var(--st-garis, #E7E5E4)', background: 'var(--st-pasir-lembut, #FAFAF9)' }">

    <!-- ══════════ rel ══════════ -->
    <ol class="flex items-start gap-0 px-4 pt-4 pb-1 overflow-x-auto">
      <li v-for="(t, i) in alur.rel" :key="t.kunci"
          class="flex-1 min-w-[92px] flex flex-col items-center text-center relative">

        <!-- Garis penghubung digambar di belakang bulatannya, dan tidak
             sebelum yang pertama: garis menggantung di tepi kiri terbaca
             sebagai tahap yang terpotong. -->
        <span v-if="i > 0" class="absolute top-[9px] right-1/2 w-full h-[2px]"
              :style="{ background: t.lewat || t.kini ? KEADAAN.baik : 'var(--st-garis-tegas, #E7E5E4)' }"></span>

        <span class="relative z-10 w-[18px] h-[18px] rounded-full grid place-items-center text-[9px] font-bold"
              :style="t.lewat
                ? { background: KEADAAN.baik, color: '#fff' }
                : (t.kini
                    ? { background: '#fff', color: '#0F766E', boxShadow: 'inset 0 0 0 2px #0F766E' }
                    : { background: '#fff', color: '#A8A29E', boxShadow: 'inset 0 0 0 2px var(--st-garis-tegas, #E7E5E4)' })">
          {{ t.lewat ? '✓' : i + 1 }}
        </span>

        <span class="mt-1 text-[10.5px] leading-tight px-0.5"
              :class="t.kini ? 'font-bold' : ''"
              :style="{ color: t.kini ? '#08302D' : 'var(--st-redup, #A8A29E)' }">
          {{ t.label }}
        </span>
      </li>
    </ol>

    <!-- ══════════ tugas sekarang, apa yang kurang, tombolnya ══════════ -->
    <div class="px-4 pb-4 pt-2">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <p class="text-[12px] font-semibold min-w-0"
           :style="{ color: alur.ditolak ? '#B91C1C' : (alur.selesai ? KEADAAN.baik : '#08302D') }">
          {{ alur.tugas }}
        </p>

        <!-- Tombolnya HANYA muncul bila memang boleh. Sebelumnya ia
             muncul lalu ditolak sesudah ditekan, dan penolakan sesudah
             ditekan terbaca sebagai sistemnya rusak. -->
        <span v-if="alur.bolehAjukan" class="inline-flex shrink-0">
          <button type="button" class="eq-btn-utama !flex-none" :disabled="sibuk" @click="emit('ajukan')">
            Ajukan
          </button>
        </span>
      </div>

      <ul v-if="alur.kurang.length" class="mt-2 grid gap-1">
        <li v-for="k in alur.kurang" :key="k"
            class="flex items-start gap-2 text-[11.5px]"
            :style="{ color: alur.ditolak ? '#B91C1C' : '#92400E' }">
          <span class="mt-[5px] w-1.5 h-1.5 rounded-full shrink-0"
                :style="{ background: alur.ditolak ? '#DC2626' : KEADAAN.ingat }"></span>
          <span>{{ k }}</span>
        </li>
      </ul>
    </div>
  </div>
</template>

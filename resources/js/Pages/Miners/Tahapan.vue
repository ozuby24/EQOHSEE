<script setup lang="ts">
/**
 * Urutan yang harus dilalui pekerja baru, digambar berurutan.
 *
 * Inilah yang hilang dan membuat alurnya membingungkan: setiap bagian
 * dapat diisi kapan saja, dan tidak ada satu pun layar yang mengatakan
 * apa yang harus dikerjakan berikutnya. Orang menebak, tebakannya
 * salah, formulirnya ditolak — dan penolakan itu terbaca sebagai
 * sistemnya rusak.
 *
 * TAHAP YANG TERKUNCI MENYEBUTKAN SEBABNYA, bukan sekadar diredupkan.
 * "Tidak bisa diklik" tanpa alasan adalah bentuk kegagalan yang paling
 * sering membuat orang mencari jalan lain — biasanya di luar sistem.
 */
import { KEADAAN } from '../../Grafik/warna';

const props = defineProps<{
  tahapan: Array<{
    kode: string; urut: number; label: string; terang: string;
    keadaan: string; ringkas: string; sebab?: string | null;
    opsional?: boolean; jalur?: string | null; cetak?: string | null;
  }>;
}>();

const WARNA: Record<string, string> = {
  selesai:  KEADAAN.baik,
  berjalan: KEADAAN.ingat,
  siap:     '#2a78d6',
  terkunci: KEADAAN.netral,
};

const KATA: Record<string, string> = {
  selesai:  'Selesai',
  berjalan: 'Berjalan',
  siap:     'Siap dikerjakan',
  terkunci: 'Menunggu tahap sebelumnya',
};
</script>

<template>
  <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
    <h3 class="text-[14px] font-bold text-cam-ink">Tahapan kelayakan</h3>
    <p class="text-[11.5px] text-stone-500 mt-0.5 mb-4">
      MCU → induksi → Mine Permit, dan SIMPER bagi yang mengemudi.
      Urutannya tidak dapat didahului: tiap tahap menuntut yang sebelumnya selesai.
    </p>

    <ol class="grid gap-3 md:grid-cols-4">
      <li v-for="t in props.tahapan" :key="t.kode"
          class="rounded-xl border p-3 min-w-0"
          :style="{ borderColor: t.keadaan === 'terkunci' ? '#E7E5E4' : WARNA[t.keadaan] + '55',
                    background: t.keadaan === 'selesai' ? '#F2FBF5' : '#fff' }">

        <div class="flex items-center gap-2">
          <span class="shrink-0 w-5 h-5 rounded-full grid place-items-center
                       text-[10px] font-bold text-white"
                :style="{ background: WARNA[t.keadaan] }">{{ t.urut }}</span>
          <p class="text-[12px] font-bold text-cam-ink truncate">
            {{ t.label }}
            <span v-if="t.opsional" class="text-[9.5px] font-normal text-stone-400">opsional</span>
          </p>
        </div>

        <p class="text-[10.5px] font-semibold mt-1.5" :style="{ color: WARNA[t.keadaan] }">
          {{ KATA[t.keadaan] ?? t.keadaan }}
        </p>
        <p class="text-[10.5px] text-stone-600 mt-0.5">{{ t.ringkas }}</p>

        <!-- Sebabnya disebut, bukan hanya tahapnya diredupkan. -->
        <p v-if="t.sebab" class="text-[10px] text-stone-500 mt-1.5 pt-1.5 border-t border-stone-100">
          {{ t.sebab }}
        </p>

        <a v-if="t.cetak" :href="t.cetak" target="_blank"
           class="inline-block text-[10.5px] font-semibold text-cam-lime-deep mt-2">
          Cetak Mine Permit →
        </a>
      </li>
    </ol>
  </section>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Capaian tiap PJP sebagai bilah mendatar, terendah lebih dulu.
 *
 * Diurutkan naik dengan sengaja: grafik ini bukan ringkasan melainkan
 * daftar kerja. Yang paling perlu ditindaklanjuti harus terbaca lebih
 * dulu, bukan tenggelam di bawah setelah belasan baris yang sudah baik.
 *
 * PJP yang capaiannya `null` dikumpulkan di bawah, bukan digambar
 * sebagai bilah 0%. Belum ada datanya dan berskor nol adalah dua keadaan
 * berbeda — yang satu belum diperiksa, yang lain sudah diperiksa dan
 * gagal — dan menggambar keduanya merah sama gelapnya membuat perbedaan
 * itu hilang tepat di tempat orang mengambil keputusan.
 */
const props = defineProps<{
  judul: string;
  kosong: string;
  labelTanpaData: string;
  items: Array<{ id: number; label: string; nilai: number | null }>;
  /** Tujuan tiap baris; __ID__ diganti id PJP. */
  pola: string;
}>();

/** Empat pita yang sama dipakai di seluruh modul — lihat juga PjpDonat. */
const PITA = [
  { min: 80, isi: '#0ca30c', dari: '#4ade80', label: 'Baik (≥ 80)' },
  { min: 60, isi: '#fab219', dari: '#fde047', label: 'Perlu Perhatian (60–79)' },
  { min: 40, isi: '#ec835a', dari: '#fdba74', label: 'Perlu Tindak Lanjut (40–59)' },
  { min: 0,  isi: '#d03b3b', dari: '#f87171', label: 'Kritis (< 40)' },
];

const pitaUntuk = (nilai: number) => PITA.find((p) => nilai >= p.min) ?? PITA[PITA.length - 1];

const baris = computed(() => {
  const bernilai = props.items.filter((i) => i.nilai !== null)
    .sort((a, b) => (a.nilai as number) - (b.nilai as number));
  const kosong = props.items.filter((i) => i.nilai === null)
    .sort((a, b) => a.label.localeCompare(b.label));

  return [...bernilai, ...kosong];
});

const angka = (n: number) => Number.isInteger(n) ? String(n) : n.toFixed(1);

const untuk = (id: number) => props.pola.replace('__ID__', String(id));
</script>

<template>
  <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
    <div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between">
      <h3 class="font-bold text-[14px] text-stone-800">{{ props.judul }}</h3>
      <span class="text-[11px] text-stone-400">Urut dari yang paling perlu ditindaklanjuti</span>
    </div>

    <p v-if="!baris.length" class="py-6 text-center text-[12px] text-stone-400">{{ props.kosong }}</p>

    <div v-else class="space-y-1.5">
      <!--
        Baris menumpuk di layar sempit (flex-col) dan kembali sebaris
        pada sm ke atas. Lebar tetap pada kolom label dan nilai disusun
        untuk layar lebar; di lebar ~390px keduanya sudah menghabiskan
        satu baris, sehingga bilah ber-flex-1 menyusut menjadi nol dan
        HILANG — tanpa galat, tanpa tata letak yang tampak rusak.

        Label sengaja tidak dipotong dengan `truncate`: nama perusahaan
        yang panjang lebih baik turun ke baris kedua daripada berakhir
        sebagai "PT Borneo Tambang Sej…" yang tidak dapat dibedakan dari
        perusahaan lain berawalan sama.
      -->
      <component
        :is="props.pola ? Link : 'div'"
        v-for="item in baris"
        :key="item.id"
        :href="props.pola ? untuk(item.id) : undefined"
        class="flex flex-col gap-1.5 rounded-lg px-2 py-2 transition-colors hover:bg-stone-50 sm:flex-row sm:items-center sm:gap-3 sm:py-1.5"
        :title="item.nilai !== null ? `${item.label}: ${angka(item.nilai)}%` : `${item.label}: ${props.labelTanpaData}`"
      >
        <div class="flex items-center justify-between gap-2 sm:w-64 sm:shrink-0 sm:justify-start">
          <span class="text-[12px] text-stone-700">{{ item.label }}</span>
          <span class="shrink-0 text-[11px] font-semibold text-stone-500 sm:hidden">
            {{ item.nilai !== null ? `${angka(item.nilai)}%` : props.labelTanpaData }}
          </span>
        </div>

        <span class="h-3 w-full overflow-hidden rounded-full bg-stone-200/60 shadow-inner sm:flex-1">
          <span
            v-if="item.nilai !== null"
            class="block h-full rounded-full"
            :style="{
              width: `${Math.max(item.nilai, 2)}%`,
              backgroundImage: `linear-gradient(90deg, ${pitaUntuk(item.nilai).dari}, ${pitaUntuk(item.nilai).isi})`,
            }"
          ></span>
        </span>

        <span class="hidden shrink-0 text-right text-[11px] font-semibold text-stone-500 sm:block sm:w-28">
          {{ item.nilai !== null ? `${angka(item.nilai)}%` : props.labelTanpaData }}
        </span>
      </component>
    </div>

    <div class="mt-4 flex flex-wrap gap-x-4 gap-y-1 border-t border-stone-100 pt-3">
      <span v-for="pita in PITA" :key="pita.label" class="flex items-center gap-1.5 text-[11px] text-stone-500">
        <span class="h-2 w-2 shrink-0 rounded-full" :style="{ backgroundColor: pita.isi }"></span>
        {{ pita.label }}
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

defineProps<{
  token: string;
  company: { name: string };
}>();
</script>

<template>
  <Head title="Terima kasih" />

  <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-10 text-center animate-pop">
    <div class="w-20 h-20 mx-auto rounded-full lime-gradient shadow-glow grid place-items-center text-white text-[34px]">✓</div>
    <h1 class="stat text-cam-ink mt-5">Terima kasih!</h1>
    <p class="text-[13px] text-stone-500 mt-2 leading-relaxed">
      Jawaban Anda sudah tersimpan dan akan digunakan untuk penilaian PTPKKP di {{ company.name }}.
    </p>
    <div class="mt-6 flex flex-wrap items-center justify-center gap-x-5 gap-y-2">
      <Link :href="`/q/${token}`"
            class="text-[12.5px] font-bold text-cam-lime-deep hover:underline">Isi kuesioner lain</Link>
      <!--
        Tautan ke pengujian dipasang di sini, bukan hanya disebar
        terpisah. Keduanya ditujukan kepada orang yang sama dan
        dibagikan lewat cara yang sama; yang baru selesai mengisi
        kuesioner adalah orang yang paling mungkin mengerjakan
        pengujiannya sekarang juga, dan menyuruhnya mencari tautan
        kedua di grup WhatsApp adalah cara paling pasti kehilangan dia.
      -->
      <Link :href="`/uji/${token}`"
            class="text-[12.5px] font-bold text-cam-lime-deep hover:underline">
        Ikuti pengujian kesadaran risiko →
      </Link>
    </div>
  </div>
</template>

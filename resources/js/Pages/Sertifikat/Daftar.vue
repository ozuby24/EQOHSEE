<script setup lang="ts">
/**
 * Sertifikat pelatihan.
 *
 * Tiga keadaan dibedakan dengan tegas: sudah terbit, siap diterbitkan,
 * dan masih menunggu penilaian trainer. Menggabungkannya membuat peserta
 * bertanya-tanya mengapa sertifikatnya belum ada padahal kursusnya sudah
 * selesai.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import type { HalamanDaftarSertifikat } from '../../types';

defineProps<HalamanDaftarSertifikat>();

function terbitkan(url: string) {
  router.post(url, {}, { preserveScroll: true });
}
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-4xl mx-auto">

    <section v-if="menungguEvaluasi.length"
             class="bg-white rounded-2xl shadow-card border border-amber-200 p-5 mb-5">
      <div class="flex items-start gap-3">
        <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 grid place-items-center shrink-0">
          <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        </div>
        <div class="min-w-0">
          <h3 class="text-[13px] font-bold text-cam-ink">Menunggu evaluasi trainer</h3>
          <p class="text-[11.5px] text-stone-500 mt-0.5 leading-relaxed">
            Kursus berikut sudah kamu selesaikan. Sertifikat terbit otomatis setelah trainer
            menyelesaikan penilaian.
          </p>
          <ul class="mt-2.5 space-y-1.5">
            <li v-for="(k, i) in menungguEvaluasi" :key="i"
                class="text-[12.5px] font-semibold text-stone-600 bg-stone-50 rounded-lg px-3 py-2">
              {{ k }}
            </li>
          </ul>
        </div>
      </div>
    </section>

    <section v-if="siapTerbit.length" class="bg-cam-lime-soft border border-cam-lime/25 rounded-2xl p-5 mb-5">
      <h3 class="text-[13px] font-bold text-cam-lime-deep mb-3">🎓 Siap diterbitkan</h3>
      <div v-for="(s, i) in siapTerbit" :key="i"
           class="flex items-center justify-between gap-3 bg-white rounded-xl px-4 py-3 mb-2 last:mb-0">
        <span class="text-[13px] font-semibold text-cam-ink">{{ s.kursus }}</span>
        <button type="button" @click="terbitkan(s.url)"
                class="lime-gradient rounded-lg text-white px-3.5 py-1.5 text-[11.5px]
                       font-bold hover:brightness-105 transition">Terbitkan</button>
      </div>
    </section>

    <div class="space-y-2.5">
      <Link v-for="c in sertifikat" :key="c.id" :href="c.url"
            class="block bg-white rounded-2xl shadow-card border border-stone-100 p-5
                   hover:border-cam-lime/40 card-hover transition">
        <div class="flex items-center justify-between gap-4">
          <div class="min-w-0">
            <div class="text-[14px] font-bold text-cam-ink clamp-1">{{ c.kursus }}</div>
            <div class="text-[11px] text-stone-400 mt-0.5">{{ c.nomor }} · {{ c.tanggal }}</div>
            <div v-if="admin" class="text-[11px] text-stone-400 mt-0.5">{{ c.penerima }}</div>
          </div>
          <span class="text-[22px]">🎓</span>
        </div>
      </Link>

      <div v-if="!sertifikat.length"
           class="bg-white rounded-2xl border border-dashed border-stone-200 p-14 text-center">
        <p class="text-[13px] text-stone-400">Belum ada sertifikat.</p>
        <p class="text-[12px] text-stone-300 mt-1">Selesaikan kursus untuk mendapatkannya.</p>
      </div>
    </div>
  </div>
</template>

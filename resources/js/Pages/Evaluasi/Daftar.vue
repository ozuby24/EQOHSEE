<script setup lang="ts">
/**
 * Evaluasi pasca-pelatihan.
 *
 * Peserta hanya melihat evaluasinya sendiri; penyaringnya di server,
 * bukan di sini — daftar yang disaring peramban tetap terkirim utuh.
 */
import { Head, Link } from '@inertiajs/vue3';
import type { HalamanDaftarEvaluasi } from '../../types';

defineProps<HalamanDaftarEvaluasi>();
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-5xl mx-auto">

    <div v-if="bolehMenilai" class="flex flex-wrap items-center justify-between gap-3 mb-5">
      <p class="text-[12.5px] text-stone-400">Penilaian peserta setelah pelatihan selesai.</p>
      <a :href="tautan.buat" class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5
                                    text-[12.5px] font-bold hover:brightness-105 transition">
        + Nilai Peserta
      </a>
    </div>

    <section v-if="menunggu.length" class="bg-cam-lime-soft border border-cam-lime/25 rounded-2xl p-5 mb-5">
      <h3 class="text-[13px] font-bold text-cam-lime-deep mb-1">Menunggu dievaluasi</h3>
      <p class="text-[11.5px] text-stone-500 mb-3">
        Peserta berikut sudah menyelesaikan kursusnya tetapi belum dinilai trainer.
      </p>
      <div class="space-y-2">
        <div v-for="(m, i) in menunggu" :key="i"
             class="flex flex-wrap items-center justify-between gap-3 bg-white rounded-xl px-4 py-3">
          <div class="min-w-0">
            <div class="text-[13px] font-bold text-cam-ink">{{ m.peserta ?? '—' }}</div>
            <div class="text-[11.5px] text-stone-400">{{ m.kursus ?? '—' }}</div>
          </div>
          <a :href="m.url" class="lime-gradient rounded-lg text-white px-3.5 py-1.5 text-[11.5px]
                                  font-bold hover:brightness-105 transition shrink-0">Nilai sekarang</a>
        </div>
      </div>
    </section>

    <div class="space-y-2.5">
      <Link v-for="ev in evaluasi" :key="ev.id" :href="ev.url"
            class="block bg-white rounded-2xl shadow-card border border-stone-100 p-5
                   hover:border-cam-lime/40 card-hover transition">
        <div class="flex items-center justify-between gap-4">
          <div class="min-w-0">
            <div class="text-[14px] font-bold text-cam-ink">{{ ev.peserta ?? '—' }}</div>
            <div class="text-[12px] text-stone-400 mt-0.5 clamp-1">{{ ev.kursus ?? 'Tanpa kursus' }}</div>
            <div class="text-[11px] text-stone-300 mt-1.5">
              Dinilai oleh {{ ev.trainer ?? '—' }} · {{ ev.tanggal }}
            </div>
          </div>
          <div class="text-right shrink-0">
            <div class="stat leading-none" :class="ev.nilai >= 70 ? 'text-cam-lime-dark' : 'text-stone-300'">
              {{ ev.nilai }}
            </div>
            <div v-if="ev.rekomendasi"
                 class="text-[9.5px] font-bold uppercase tracking-wide text-stone-400 mt-1.5 max-w-[120px]">
              {{ ev.rekomendasi }}
            </div>
          </div>
        </div>
      </Link>

      <div v-if="!evaluasi.length"
           class="bg-white rounded-2xl border border-dashed border-stone-200 p-14 text-center">
        <p class="text-[13px] text-stone-400">Belum ada evaluasi.</p>
        <p v-if="bolehMenilai" class="text-[12px] text-stone-300 mt-1">
          Klik "Nilai Peserta" untuk membuat evaluasi pertama.
        </p>
      </div>
    </div>

    <nav v-if="halaman.akhir > 1" class="mt-5 flex flex-wrap gap-1.5">
      <component v-for="(t, i) in halaman.tautan" :key="i"
                 :is="t.url ? Link : 'span'" :href="t.url ?? undefined"
                 class="px-3 py-1.5 rounded-lg text-[12px] font-semibold border transition"
                 :class="t.aktif ? 'border-transparent bg-cam-ink text-white'
                       : t.url ? 'border-stone-200 text-stone-600 hover:bg-stone-50'
                               : 'border-stone-100 text-stone-300'"
                 v-html="t.label" />
    </nav>
  </div>
</template>

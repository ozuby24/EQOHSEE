<script setup lang="ts">
/**
 * Lembar sertifikat, siap dicetak.
 *
 * Empat gaya tersedia dan hanya bagian kopnya yang berbeda; isi lembar
 * sengaja sama persis di keempatnya supaya sertifikat yang dicetak pada
 * gaya mana pun tetap memuat keterangan yang identik.
 */
import { Head, Link } from '@inertiajs/vue3';
import type { HalamanLembarSertifikat } from '../../types';

const props = defineProps<HalamanLembarSertifikat>();

const cetak = () => window.print();

const bingkai =
  props.c.template === 'modern' ? 'border-0'
  : props.c.template === 'minimal' ? 'border border-stone-200'
  : 'border-[3px] border-cam-lime/30';
</script>

<template>
  <Head title="Sertifikat" />

  <div class="max-w-3xl mx-auto">
    <div class="bg-white rounded-2xl shadow-card overflow-hidden relative" :class="bingkai">

      <div v-if="c.template === 'korporat'"
           class="flex items-center gap-4 px-9 py-5 border-b-2 border-cam-lime/40 bg-stone-50">
        <img v-if="c.logo" :src="c.logo" class="h-12 w-auto object-contain" alt="">
        <div class="min-w-0">
          <div class="text-[15px] font-bold text-cam-ink leading-tight">{{ c.pemilik }}</div>
          <div v-if="c.lokasi" class="text-[11px] text-stone-400 mt-0.5">{{ c.lokasi }}</div>
        </div>
      </div>
      <div v-else-if="c.template === 'modern'" class="lime-gradient h-2"></div>

      <div class="p-9 md:p-14 text-center relative">
        <template v-if="c.template === 'klasik'">
          <div class="absolute -right-24 -top-24 w-64 h-64 rounded-full bg-cam-lime/5"></div>
          <div class="absolute -left-24 -bottom-24 w-64 h-64 rounded-full bg-cam-lime/5"></div>
        </template>

        <div class="relative">
          <template v-if="c.template !== 'korporat'">
            <img v-if="c.logo" :src="c.logo" class="h-14 mx-auto object-contain" alt="">
            <img v-else :src="markUrl" class="h-8 mx-auto rounded-xl" alt="EQOHSEE">
          </template>

          <p class="text-[10px] uppercase tracking-[0.3em] text-stone-400 mt-5 font-bold">
            Sertifikat Pelatihan
          </p>
          <p v-if="c.template !== 'korporat' && c.pemilik" class="text-[12px] text-stone-400 mt-1">
            {{ c.pemilik }}
          </p>

          <p class="text-[12px] text-stone-400 mt-7">Diberikan kepada</p>
          <h2 class="font-display text-[30px] md:text-[38px] font-black text-cam-ink mt-2 leading-tight">
            {{ c.penerima }}
          </h2>

          <p class="text-[12.5px] text-stone-400 mt-4">telah menyelesaikan pelatihan</p>
          <h3 class="font-display text-[20px] md:text-[24px] font-extrabold text-cam-lime-deep mt-2">
            {{ c.kursus }}
          </h3>

          <p v-if="c.nilai" class="mt-4 inline-block bg-cam-lime-soft border border-cam-lime/25
                                   text-cam-lime-deep px-4 py-1.5 rounded-full text-[12px] font-bold">
            Nilai akhir: <span class="num">{{ c.nilai }}</span>
          </p>

          <div class="grid sm:grid-cols-[1fr_auto] gap-8 items-end mt-12 text-left">
            <div>
              <div class="text-[9.5px] uppercase tracking-[0.15em] text-stone-400 font-bold">
                Nomor Sertifikat
              </div>
              <div class="font-mono font-bold text-cam-ink text-[12.5px] mt-1 num">{{ c.nomor }}</div>

              <div class="mt-3.5 max-w-[230px]" v-html="c.barcodeSvg"></div>
              <div class="font-mono text-[9.5px] tracking-[0.12em] text-stone-500 mt-1 text-center num">
                {{ c.barcodeTeks }}
              </div>
              <div class="text-[9px] text-stone-300 mt-1.5 break-all">{{ tautan.verifikasi }}</div>
            </div>

            <div class="text-right">
              <img v-if="c.ttdGambar" :src="c.ttdGambar" class="h-11 ml-auto mb-1" alt="">
              <div class="text-[12.5px] font-bold text-cam-ink border-t border-stone-200 pt-1.5 min-w-[170px]">
                {{ c.ttdNama }}
              </div>
              <div class="text-[10.5px] text-stone-400 mt-0.5">{{ c.ttdJabatan }}</div>
            </div>
          </div>

          <p class="text-[10.5px] text-stone-300 mt-8">Diterbitkan {{ c.terbit }} · EQOHSEE</p>
        </div>
      </div>

      <div v-if="c.template === 'modern'" class="lime-gradient h-2"></div>
    </div>

    <div class="flex flex-wrap gap-2.5 justify-center mt-5 print:hidden">
      <button type="button" @click="cetak"
              class="rounded-xl bg-cam-ink text-white px-5 py-2.5 text-[12.5px]
                     font-bold hover:bg-cam-panel transition">Cetak / PDF</button>
      <a :href="tautan.verifikasi" target="_blank" rel="noopener"
         class="rounded-xl border border-stone-200 px-5 py-2.5 text-[12.5px] font-bold
                text-stone-600 hover:bg-stone-50 transition">Halaman Verifikasi</a>
      <Link :href="tautan.daftar"
            class="rounded-xl border border-stone-200 px-5 py-2.5 text-[12.5px] font-bold
                   text-stone-600 hover:bg-stone-50 transition">Kembali</Link>
    </div>
  </div>
</template>

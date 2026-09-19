<script setup lang="ts">
/**
 * Satu pengumuman, halaman penuh.
 *
 * TIDAK diganti pop-out, melainkan dilengkapi olehnya. Alamat
 * pengumuman kerap disalin ke grup pesan dan ditempelkan ke notulen;
 * alamat yang mati karena isinya pindah ke pop-out adalah kerugian yang
 * baru terlihat ketika orang mengeluh tautannya rusak. Halaman ini pula
 * yang dibuka ketika pengumuman dicetak.
 *
 * Isinya ditulis sebagai teks biasa, bukan HTML: apa pun yang diketik
 * penulis tergambar apa adanya lewat whitespace-pre-line, sehingga tidak
 * ada jalan menyelipkan markah ke halaman orang lain.
 */
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import type { HalamanDetailBerita } from '../../types';
import Putaran from '../../Components/Putaran.vue';

const props = defineProps<HalamanDetailBerita>();

const menandai   = ref(false);
const baruDibaca = ref(false);

const sudah = computed(() => baruDibaca.value || props.berita.sudahDibaca);

const jumlah = computed(
  () => props.berita.jumlahDibaca + (baruDibaca.value ? 1 : 0),
);

function tandaiBaca() {
  if (sudah.value || menandai.value) return;

  menandai.value = true;

  router.post(props.berita.urlBaca, {}, {
    preserveScroll: true,
    preserveState: true,
    only: [],
    onSuccess: () => { baruDibaca.value = true; },
    onFinish:  () => { menandai.value = false; },
  });
}
</script>

<template>
  <Head :title="berita.judul" />

  <article class="max-w-3xl mx-auto bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">

    <img v-if="berita.sampul" :src="berita.sampul" :alt="`Sampul: ${berita.judul}`"
         class="w-full h-[260px] object-cover bg-stone-100">

    <div class="p-7 md:p-9">
      <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-cam-lime-dark">{{ berita.tanggal }}</div>

      <h1 class="font-display text-[28px] md:text-[32px] font-black text-cam-ink mt-2.5 leading-tight">
        {{ berita.judul }}
      </h1>

      <!-- Ringkasan tidak digambar di sini, sama seperti di pop-out:
           tugasnya menggantikan isi di daftar, bukan mendahuluinya. -->
      <div class="text-[13.5px] text-stone-600 leading-[1.75] mt-5 whitespace-pre-line">{{ berita.isi }}</div>

      <p v-if="berita.terpotong"
         class="mt-4 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3
                text-[12px] text-amber-800 leading-relaxed">
        Pengumuman ini melebihi batas panjang yang dapat ditampilkan. Bagian akhirnya
        tidak tergambar di sini — mintalah berkas lengkapnya kepada penerbitnya.
      </p>

      <a v-if="berita.lampiran" :href="berita.lampiran.url"
         class="mt-7 flex items-center gap-3 rounded-2xl border border-stone-200 px-4 py-3
                hover:border-[color:var(--eq-aksen,#F57C00)]/50 transition">
        <span class="w-9 h-9 shrink-0 grid place-items-center rounded-xl
                     text-[color:var(--eq-aksen,#D96500)] bg-[color:var(--eq-aksen,#F57C00)]/12"
              aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round" class="w-[18px] h-[18px]">
            <path d="M14 3v5h5"/><path d="M15 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/>
          </svg>
        </span>
        <span class="min-w-0">
          <strong class="block text-[12.5px] font-bold text-cam-ink truncate">{{ berita.lampiran.nama }}</strong>
          <small class="block text-[11px] text-stone-400">Unduh lampiran</small>
        </span>
      </a>

      <div class="mt-8 pt-5 border-t border-stone-100 flex flex-wrap items-center gap-3">
        <button v-if="!sudah" type="button" class="eq-btn-utama" style="padding:9px 16px;font-size:12px"
                :disabled="menandai" @click="tandaiBaca">
          <Putaran v-if="menandai" :ukuran="13" />
          {{ menandai ? 'Mencatat…' : 'Saya sudah membaca' }}
        </button>

        <span v-else class="inline-flex items-center gap-1.5 text-[12px] font-bold text-emerald-600">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"
               stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"
               aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
          Sudah Anda baca
        </span>

        <span v-if="jumlah" class="text-[11.5px] text-stone-400">Dibaca {{ jumlah }} orang</span>

        <Link :href="tautan.daftar"
              class="ml-auto text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink">
          ← Semua pengumuman
        </Link>
      </div>
    </div>
  </article>
</template>

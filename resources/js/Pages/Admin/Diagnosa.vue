<script setup lang="ts">
/**
 * Diagnosa sistem.
 *
 * Yang ditampilkan di sini sengaja bukan lampu hijau-merah saja. Tiap
 * baris menyebut apa keadaannya, apa akibatnya bila dibiarkan, dan apa
 * langkahnya — sebab daftar merah tanpa akibat dan tanpa langkah hanya
 * melahirkan kebiasaan mengabaikan warna merah.
 *
 * Yang aman ditutup secara bawaan. Dua puluh baris hijau di atas satu
 * baris merah membuat yang merah itu ikut terbaca sebagai latar.
 */
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { HalamanDiagnosa } from '../../types';

const props = defineProps<HalamanDiagnosa>();

const galat = computed<Record<string, string>>(() => (usePage().props as any).errors ?? {});

const tampilkanAman = ref(false);

const NADA: Record<string, { label: string; teks: string; latar: string; garis: string; titik: string }> = {
  gawat:     { label: 'Gawat',          teks: 'text-red-700',   latar: 'bg-red-50',    garis: 'border-red-100',   titik: 'bg-red-500' },
  perhatian: { label: 'Perlu perhatian', teks: 'text-amber-800', latar: 'bg-amber-50',  garis: 'border-amber-100', titik: 'bg-amber-500' },
  'tak-tahu':{ label: 'Tidak diketahui', teks: 'text-stone-600', latar: 'bg-stone-50',  garis: 'border-stone-200', titik: 'bg-stone-400' },
  aman:      { label: 'Aman',            teks: 'text-emerald-700', latar: 'bg-white',   garis: 'border-stone-100', titik: 'bg-emerald-500' },
};

const URUT = ['gawat', 'perhatian', 'tak-tahu', 'aman'];

const terlihat = computed(() =>
  props.hasil.filter((h) => tampilkanAman.value || h.keadaan !== 'aman'),
);

const perluTindakan = computed(() =>
  props.hasil.filter((h) => h.keadaan === 'gawat' || h.keadaan === 'perhatian').length,
);

/* ── aksi ── */

const perbaikanForm = useForm({});

function perbaiki(p: HalamanDiagnosa['perbaikan'][number]) {
  if (p.berat && !confirm(`${p.label}\n\n${p.ket}\n\nJalankan sekarang?`)) return;

  perbaikanForm.post(p.url, { preserveScroll: true });
}

function periksaUlang() {
  router.reload({ only: ['hasil', 'ringkas', 'dijalankan'] });
}
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-5xl mx-auto space-y-5">

    <section class="brand-gradient rounded-2xl p-6 text-white shadow-card relative overflow-hidden">
      <div class="absolute -right-20 -top-20 w-56 h-56 rounded-full bg-cam-lime/20 blur-3xl"></div>
      <div class="relative">
        <div class="flex items-start justify-between gap-4 flex-wrap">
          <div>
            <span class="text-[10px] font-bold uppercase tracking-[0.22em] text-cam-lime-light">
              Pemeriksaan Mandiri
            </span>
            <h2 class="stat mt-1.5">{{ judul }}</h2>
            <p class="text-[12px] text-white/70 mt-1.5 max-w-xl leading-relaxed">
              {{ subjudul }} — mode debug yang tertinggal menyala, migrasi yang belum jalan,
              tautan berkas yang putus. Semuanya membuat sistem tetap terlihat sehat.
            </p>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <button type="button" @click="periksaUlang"
                    class="glass rounded-xl px-3.5 py-2 text-[11.5px] font-bold text-white
                           hover:bg-white/20 transition">
              Periksa ulang
            </button>
            <Link :href="tautan.sistem"
                  class="glass rounded-xl px-3.5 py-2 text-[11.5px] font-bold text-white
                         hover:bg-white/20 transition">
              Pusat Kendali
            </Link>
          </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-5">
          <div v-for="k in URUT" :key="k" class="glass rounded-xl px-3.5 py-2.5">
            <div class="flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full" :class="NADA[k].titik"></span>
              <span class="text-[9px] uppercase tracking-[0.12em] text-white/62 font-bold">
                {{ NADA[k].label }}
              </span>
            </div>
            <div class="stat stat-sm text-white mt-1 num">{{ ringkas[k] ?? 0 }}</div>
          </div>
        </div>

        <p class="text-[10.5px] text-white/55 mt-3">Diperiksa {{ dijalankan }}</p>
      </div>
    </section>

    <div v-if="galat.perbaikan"
         class="rounded-2xl border border-red-100 bg-red-50 px-5 py-4 text-[12.5px]
                text-red-700 leading-relaxed">
      {{ galat.perbaikan }}
    </div>

    <!-- Hasil pemeriksaan -->
    <section class="space-y-2.5">
      <div class="flex items-center justify-between">
        <h3 class="text-[14px] font-bold text-cam-ink">
          {{ perluTindakan }} dari {{ hasil.length }} pemeriksaan perlu ditindak
        </h3>
        <label class="flex items-center gap-2 text-[11.5px] font-semibold text-stone-500 cursor-pointer">
          <input type="checkbox" v-model="tampilkanAman"
                 class="rounded border-stone-300 text-cam-lime-deep focus:ring-cam-lime" />
          Tampilkan yang aman
        </label>
      </div>

      <div v-for="h in terlihat" :key="h.kode"
           class="rounded-2xl border shadow-card p-4"
           :class="[NADA[h.keadaan].garis, NADA[h.keadaan].latar]">
        <div class="flex items-start gap-3">
          <span class="w-2.5 h-2.5 rounded-full mt-1.5 shrink-0" :class="NADA[h.keadaan].titik"></span>

          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-baseline gap-x-2.5 gap-y-1">
              <span class="text-[13.5px] font-bold text-cam-ink">{{ h.judul }}</span>
              <!-- Nada bakunya, bukan bg-white/70: pada tema gelap
                   putih tetap putih sementara text-stone-500 ikut
                   diterangkan, sehingga abu terang duduk di atas putih
                   pada 2,6:1. Pasangan stone-100/stone-500 inilah yang
                   dipakai lencana lain di aplikasi ini dan yang
                   terbukti lolos di kedua tema. -->
              <span class="text-[9.5px] font-bold uppercase tracking-wide bg-stone-100
                           text-stone-600 px-1.5 py-0.5 rounded">
                {{ h.kelompok }}
              </span>
              <span class="text-[11px] font-bold" :class="NADA[h.keadaan].teks">
                {{ NADA[h.keadaan].label }}
              </span>
            </div>

            <div class="text-[12.5px] font-semibold text-stone-700 mt-1.5 break-words">
              {{ h.nilai }}
            </div>

            <!-- stone-600, bukan stone-500: kartu ini berlatar merah
                 atau kuning muda, dan di atas latar itu stone-500
                 turun ke 4,4:1 — tepat di bawah ambang. Justru baris
                 yang paling perlu dibaca yang latarnya paling berwarna. -->
            <p class="text-[12px] text-stone-600 mt-1.5 leading-relaxed">{{ h.uraian }}</p>

            <p v-if="h.tindakan"
               class="text-[12px] mt-2 leading-relaxed font-semibold" :class="NADA[h.keadaan].teks">
              Langkah: {{ h.tindakan }}
            </p>
          </div>
        </div>
      </div>

      <div v-if="!terlihat.length"
           class="rounded-2xl border border-emerald-100 bg-emerald-50 px-5 py-10 text-center">
        <div class="text-[14px] font-bold text-emerald-800">Tidak ada yang perlu ditindak</div>
        <p class="text-[12px] text-emerald-700 mt-1.5">
          Seluruh {{ hasil.length }} pemeriksaan lolos. Centang "Tampilkan yang aman" untuk melihat rinciannya.
        </p>
      </div>
    </section>

    <!-- Perbaikan -->
    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[14px] font-bold text-cam-ink mb-1">Perbaikan</h3>
      <p class="text-[11.5px] text-stone-400 leading-relaxed mb-3.5">
        Dijalankan langsung di server ini. Keluarannya ditampilkan apa adanya, termasuk bila gagal —
        tombol yang hanya berkata "berhasil" memindahkan pekerjaan menebak kepada Anda.
      </p>

      <div class="grid gap-2 sm:grid-cols-2">
        <button v-for="p in perbaikan" :key="p.aksi" type="button"
                :disabled="perbaikanForm.processing" @click="perbaiki(p)"
                class="text-left rounded-xl border px-4 py-3 transition disabled:opacity-40"
                :class="p.berat
                  ? 'border-amber-100 bg-amber-50/60 hover:border-amber-400'
                  : 'border-stone-200 hover:border-cam-lime hover:bg-cam-lime-soft'">
          <div class="flex items-center gap-2">
            <span class="text-[12.5px] font-bold text-cam-ink">{{ p.label }}</span>
            <!-- Tangga -100/-800, bukan -200/-900: tema gelap
                 menjangkau tangga yang itu, dan yang di luarnya tetap
                 krem terang di tengah halaman gelap. -->
            <span v-if="p.berat"
                  class="text-[9px] font-bold uppercase tracking-wide bg-amber-100
                         text-amber-800 px-1.5 py-0.5 rounded">
              mengubah data
            </span>
          </div>
          <div class="text-[11px] text-stone-500 mt-1 leading-relaxed">{{ p.ket }}</div>
        </button>
      </div>
    </section>

  </div>
</template>

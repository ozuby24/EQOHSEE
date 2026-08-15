<script setup lang="ts">
/**
 * Halaman belajar: modul, materi, catatan pribadi, dan kuisnya.
 *
 * Catatan tiap modul punya formulirnya sendiri. Satu formulir bersama
 * akan mengirim seluruh catatan setiap kali satu di antaranya disimpan,
 * dan catatan yang sedang diketik di modul lain ikut tertimpa.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import type { HalamanBelajarKursus } from '../../types';

const props = defineProps<HalamanBelajarKursus>();

/** Isi catatan per modul; disalin agar prop tidak diubah di tempat. */
const catatan = reactive<Record<number, string>>(
  Object.fromEntries(props.modul.map((m) => [m.id, m.catatan])),
);

const menyimpan = ref<number | null>(null);
const terbuka = reactive<Record<number, boolean>>({});

function tandaiSelesai(url: string) {
  router.post(url, {}, { preserveScroll: true });
}

function simpanCatatan(m: { id: number; urlCatatan: string }) {
  menyimpan.value = m.id;

  router.post(m.urlCatatan, { content: catatan[m.id] }, {
    preserveScroll: true,
    onFinish: () => { menyimpan.value = null; },
  });
}

function terbitkanSertifikat() {
  router.post(props.tautan.sertifikat);
}
</script>

<template>
  <Head :title="kursus.judul" />

  <div class="max-w-4xl mx-auto space-y-5">

    <section class="brand-gradient rounded-2xl p-6 text-white shadow-card relative overflow-hidden">
      <div class="absolute -right-20 -top-20 w-56 h-56 rounded-full bg-cam-lime/20 blur-3xl"></div>
      <div class="relative">
        <h2 class="stat leading-tight">{{ kursus.judul }}</h2>
        <p class="text-[12.5px] text-white/50 mt-1.5 leading-relaxed">{{ kursus.keterangan }}</p>

        <div class="flex items-center gap-3 mt-5">
          <div class="flex-1 h-2 rounded-full bg-white/10 overflow-hidden">
            <div class="h-full rounded-full lime-gradient transition-all"
                 :style="{ width: kursus.progres + '%' }"></div>
          </div>
          <span class="text-[13px] font-bold text-cam-lime-light">{{ kursus.progres }}%</span>
        </div>

        <button v-if="kursus.selesai" type="button" @click="terbitkanSertifikat"
                class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2.5 text-[12.5px]
                       font-bold hover:brightness-105 transition mt-4">
          🎓 Terbitkan Sertifikat
        </button>
      </div>
    </section>

    <div class="space-y-2.5">
      <div v-for="m in modul" :key="m.id"
           class="bg-white rounded-2xl shadow-card border p-5"
           :class="m.selesai ? 'border-cam-lime/35' : 'border-stone-100'">
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2.5">
              <span v-if="m.selesai"
                    class="w-5 h-5 rounded-full lime-gradient text-white grid place-items-center
                           text-[10px] font-bold shrink-0">✓</span>
              <span v-else class="w-5 h-5 rounded-full border-2 border-stone-200 shrink-0"></span>
              <h3 class="text-[14px] font-bold text-cam-ink">{{ m.urutan }}. {{ m.judul }}</h3>
            </div>
            <p v-if="m.keterangan" class="text-[12.5px] text-stone-400 mt-1.5 ml-[30px] leading-relaxed">
              {{ m.keterangan }}
            </p>
          </div>
          <button v-if="!m.selesai" type="button" @click="tandaiSelesai(m.urlSelesai)"
                  class="shrink-0 lime-gradient rounded-lg text-white px-3 py-1.5 text-[11px]
                         font-bold hover:brightness-105 transition">Tandai selesai</button>
        </div>

        <ul v-if="m.materi.length" class="mt-3.5 ml-[30px] space-y-1.5">
          <li v-for="x in m.materi" :key="x.id" class="flex items-center gap-2 text-[12.5px]">
            <span class="text-[9.5px] uppercase font-bold bg-cam-lime-soft text-cam-lime-deep
                         px-1.5 py-0.5 rounded tracking-wide">{{ x.jenis }}</span>
            <a v-if="x.url" :href="x.url" target="_blank" rel="noopener"
               class="text-stone-600 hover:text-cam-lime-deep hover:underline">{{ x.judul }}</a>
            <span v-else class="text-stone-600">{{ x.judul }}</span>
          </li>
        </ul>

        <div class="mt-3.5 ml-[30px]">
          <button type="button" @click="terbuka[m.id] = !terbuka[m.id]"
                  class="text-[12px] font-bold text-cam-lime-deep hover:underline">
            {{ terbuka[m.id] ? '− ' : '+ ' }}Catatan saya
          </button>

          <div v-show="terbuka[m.id]" class="mt-2">
            <textarea v-model="catatan[m.id]" rows="3" placeholder="Tulis catatan…"
                      class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5
                             text-[12.5px] leading-relaxed transition"></textarea>
            <button type="button" :disabled="menyimpan === m.id" @click="simpanCatatan(m)"
                    class="mt-2 rounded-lg bg-cam-ink text-white px-3 py-1.5 text-[11px]
                           font-bold hover:bg-cam-panel transition disabled:opacity-40">
              {{ menyimpan === m.id ? 'Menyimpan…' : 'Simpan catatan' }}
            </button>
          </div>
        </div>
      </div>

      <div v-if="!modul.length"
           class="bg-white rounded-2xl border border-dashed border-stone-200 p-12
                  text-center text-[13px] text-stone-400">
        Kursus ini belum memiliki modul.
      </div>
    </div>

    <template v-if="kuis.length">
      <h3 class="text-[15px] font-bold text-cam-ink pt-1">Kuis</h3>
      <div class="space-y-2">
        <Link v-for="q in kuis" :key="q.id" :href="q.url"
              class="flex items-center justify-between bg-white rounded-xl shadow-soft
                     border border-stone-100 px-5 py-4 hover:border-cam-lime/40 transition">
          <span class="text-[13px] font-bold text-cam-ink">{{ q.judul }}</span>
          <span class="text-[11.5px] text-stone-400">Lulus ≥ {{ q.nilaiLulus }} →</span>
        </Link>
      </div>
    </template>
  </div>
</template>

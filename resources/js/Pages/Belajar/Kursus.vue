<script setup lang="ts">
/**
 * Halaman belajar: modul, materi, catatan pribadi, dan kuisnya.
 *
 * Catatan tiap modul punya formulirnya sendiri. Satu formulir bersama
 * akan mengirim seluruh catatan setiap kali satu di antaranya disimpan,
 * dan catatan yang sedang diketik di modul lain ikut tertimpa.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import type { HalamanBelajarKursus } from '../../types';
import IkonStat from '../../Components/IkonStat.vue';
import Putaran from '../../Components/Putaran.vue';

const props = defineProps<HalamanBelajarKursus>();

/** Isi catatan per modul; disalin agar prop tidak diubah di tempat. */
const catatan = reactive<Record<number, string>>(
  Object.fromEntries(props.modul.map((m) => [m.id, m.catatan])),
);

const menyimpan = ref<number | null>(null);
const tersimpan = ref<number | null>(null);
const terbuka = reactive<Record<number, boolean>>({});
const menandai = ref<number | null>(null);
const menerbitkan = ref(false);

let jamTersimpan: ReturnType<typeof setTimeout> | null = null;

/* Berapa materi modul ini yang sudah tuntas. Dihitung di sini, bukan
   dikirim server: angkanya turunan langsung dari daftar yang sudah ada
   di layar, dan medan turunan yang ikut dikirim akan berselisih dengan
   daftarnya sendiri pada muat ulang sebagian. */
const tuntas = computed(() => Object.fromEntries(
  props.modul.map((m) => [m.id, m.materi.filter((x) => x.selesai).length]),
));

function tandaiSelesai(m: { id: number; urlSelesai: string }) {
  if (menandai.value !== null) return;

  menandai.value = m.id;

  router.post(m.urlSelesai, {}, {
    preserveScroll: true,
    onFinish: () => { menandai.value = null; },
  });
}

function simpanCatatan(m: { id: number; urlCatatan: string }) {
  menyimpan.value = m.id;

  router.post(m.urlCatatan, { content: catatan[m.id] }, {
    preserveScroll: true,

    /* Penanda tersimpan yang HILANG SENDIRI. Tanda permanen masih
       terpampang ketika orangnya sudah mengetik tiga kalimat baru, dan
       sejak saat itu ia berbohong. */
    onSuccess: () => {
      tersimpan.value = m.id;
      if (jamTersimpan) clearTimeout(jamTersimpan);
      jamTersimpan = setTimeout(() => { tersimpan.value = null; }, 4000);
    },

    onFinish: () => { menyimpan.value = null; },
  });
}

function ketikCatatan(id: number) {
  /* Mengetik lagi memadamkan tandanya seketika: "tersimpan" di samping
     teks yang belum tersimpan adalah kabar yang salah. */
  if (tersimpan.value === id) tersimpan.value = null;
}

function terbitkanSertifikat() {
  if (menerbitkan.value) return;

  menerbitkan.value = true;

  router.post(props.tautan.sertifikat, {}, {
    onFinish: () => { menerbitkan.value = false; },
  });
}
</script>

<template>
  <Head :title="kursus.judul" />

  <div class="space-y-5">

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

        <button v-if="kursus.selesai" type="button" :disabled="menerbitkan" @click="terbitkanSertifikat"
                class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2.5 text-[12.5px]
                       font-bold hover:brightness-105 transition mt-4 disabled:opacity-60
                       inline-flex items-center gap-2">
          <Putaran v-if="menerbitkan" :ukuran="13" />
          {{ menerbitkan ? 'Menerbitkan sertifikat…' : '🎓 Terbitkan Sertifikat' }}
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
          <!-- Tombol "tandai selesai" hanya untuk modul TANPA materi.
               Modul bermateri menandai dirinya sendiri begitu seluruh
               materinya tuntas; tombol yang tetap ada di sampingnya
               menawarkan jalan pintas yang membuat seluruh centang
               materi di bawahnya menjadi hiasan. -->
          <button v-if="!m.selesai && !m.materi.length" type="button"
                  :disabled="menandai === m.id" @click="tandaiSelesai(m)"
                  class="shrink-0 lime-gradient rounded-lg text-white px-3 py-1.5 text-[11px]
                         font-bold hover:brightness-105 transition disabled:opacity-50
                         inline-flex items-center gap-1.5">
            <Putaran v-if="menandai === m.id" :ukuran="11" />
            {{ menandai === m.id ? 'Menandai…' : 'Tandai selesai' }}
          </button>

          <span v-else-if="!m.selesai" class="shrink-0 text-[11px] font-bold text-stone-400">
            {{ tuntas[m.id] }}/{{ m.materi.length }} materi
          </span>
        </div>

        <!--
          Baris materi yang SERAGAM: centang, ikon jenis, judul, lalu
          jenis dan durasinya. Sebelumnya tiap materi tergambar sebagai
          lencana huruf kecil berisi nama kolom basis data — "pptx",
          "document" — di samping tautan yang membuka tab baru, dan tidak
          satu pun dari keduanya memberi tahu berapa lama isinya.
        -->
        <ul v-if="m.materi.length" class="mt-3.5 ml-[30px] space-y-1">
          <li v-for="x in m.materi" :key="x.id">
            <Link :href="x.url"
                  class="flex items-center gap-2.5 rounded-xl px-2.5 py-2 -mx-2.5
                         hover:bg-stone-50 transition">
              <span class="w-4 h-4 shrink-0 rounded-[5px] grid place-items-center border-[1.5px]"
                    :class="x.selesai ? 'bg-cam-lime border-transparent text-white'
                                      : 'border-stone-300 text-transparent'"
                    aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.4"
                     stroke-linecap="round" stroke-linejoin="round" class="w-2.5 h-2.5"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
              </span>

              <span class="w-7 h-7 shrink-0 grid place-items-center rounded-lg
                           text-[color:var(--eq-aksen,#D96500)] bg-[color:var(--eq-aksen,#F57C00)]/12"
                    aria-hidden="true"><IkonStat :nama="x.ikon" :ukuran="14" /></span>

              <span class="min-w-0 flex-1">
                <span class="block text-[12.5px] font-semibold text-cam-ink truncate">{{ x.judul }}</span>
                <span class="block text-[10.5px] text-stone-400">
                  {{ x.label }}<template v-if="x.durasi"> · {{ x.durasi }}</template>
                </span>
              </span>

              <span class="shrink-0 text-[11px] font-bold text-stone-300" aria-hidden="true">→</span>
            </Link>
          </li>
        </ul>

        <div class="mt-3.5 ml-[30px]">
          <button type="button" @click="terbuka[m.id] = !terbuka[m.id]"
                  class="text-[12px] font-bold text-cam-lime-deep hover:underline">
            {{ terbuka[m.id] ? '− ' : '+ ' }}Catatan saya
          </button>

          <div v-show="terbuka[m.id]" class="mt-2">
            <textarea v-model="catatan[m.id]" rows="3" placeholder="Tulis catatan…"
                      @input="ketikCatatan(m.id)"
                      class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5
                             text-[12.5px] leading-relaxed transition"></textarea>

            <div class="mt-2 flex items-center gap-2.5">
              <button type="button" :disabled="menyimpan === m.id" @click="simpanCatatan(m)"
                      class="rounded-lg bg-cam-ink text-white px-3 py-1.5 text-[11px]
                             font-bold hover:bg-cam-panel transition disabled:opacity-40
                             inline-flex items-center gap-1.5">
                <Putaran v-if="menyimpan === m.id" :ukuran="11" />
                {{ menyimpan === m.id ? 'Menyimpan…' : 'Simpan catatan' }}
              </button>

              <span v-if="tersimpan === m.id" role="status"
                    class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-600">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8"
                     stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3"
                     aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
                Tersimpan
              </span>
            </div>
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

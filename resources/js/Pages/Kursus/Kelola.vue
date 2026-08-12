<script setup lang="ts">
/**
 * Kelola konten kursus: modul, materi, kuis, dan soalnya.
 *
 * Tiap formulir tambah punya keadaannya sendiri. Formulir bersama akan
 * membuat isian pada satu modul ikut terkirim saat modul lain disimpan —
 * dan materi mendarat di modul yang salah.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive, watchEffect } from 'vue';
import type { HalamanKelolaKursus } from '../../types';

const props = defineProps<HalamanKelolaKursus>();

const terbuka = reactive<Record<string, boolean>>({});

/* ── modul ── */

const formModul = useForm({ title: '', description: '' });

function tambahModul() {
  formModul.post(props.tautan.tambahModul, {
    preserveScroll: true,
    onSuccess: () => formModul.reset(),
  });
}

/* ── materi: satu keadaan per modul ── */

/*
  Keadaannya diikutkan pada prop, bukan disalin sekali di awal. Modul
  yang baru ditambahkan datang lewat pemuatan ulang prop, dan tanpa
  penyelarasan ini isiannya tidak punya tempat sama sekali — templat
  membaca undefined dan seluruh halaman berhenti tergambar tepat setelah
  penambahan berhasil.
*/
const materi = reactive<Record<number, { title: string; type: string; url: string }>>({});

watchEffect(() => {
  for (const m of props.modul) {
    if (!materi[m.id]) materi[m.id] = { title: '', type: 'document', url: '' };
  }
});

function tambahMateri(m: { id: number; urlTambahMateri: string }) {
  router.post(m.urlTambahMateri, { ...materi[m.id] }, {
    preserveScroll: true,
    onSuccess: () => { materi[m.id] = { title: '', type: 'document', url: '' }; },
  });
}

/* ── kuis ── */

const formKuis = useForm({ title: '', pass_score: '70' });

function tambahKuis() {
  formKuis.post(props.tautan.tambahKuis, {
    preserveScroll: true,
    onSuccess: () => formKuis.reset(),
  });
}

/* ── soal: satu keadaan per kuis ── */

const soalBaru = reactive<Record<number, { question: string; options: string[]; correct_index: number }>>({});

watchEffect(() => {
  for (const q of props.kuis) {
    if (!soalBaru[q.id]) soalBaru[q.id] = { question: '', options: ['', '', '', ''], correct_index: 0 };
  }
});

function tambahSoal(q: { id: number; urlTambahSoal: string }) {
  router.post(q.urlTambahSoal, { ...soalBaru[q.id] }, {
    preserveScroll: true,
    onSuccess: () => { soalBaru[q.id] = { question: '', options: ['', '', '', ''], correct_index: 0 }; },
  });
}

function hapus(url: string, pesan: string) {
  if (!confirm(pesan)) return;
  router.delete(url, { preserveScroll: true });
}

const kecil = 'ring-focus rounded-lg border border-stone-200 px-3 py-2 text-[12.5px] transition';
const besar = 'ring-focus rounded-xl border border-stone-200 px-4 py-2.5 text-[13px] transition';
const HURUF = ['A', 'B', 'C', 'D'];
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-4xl mx-auto space-y-5">

    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">Kelola Konten</div>
        <h2 class="text-[18px] font-bold text-cam-ink mt-0.5">{{ kursus.judul }}</h2>
      </div>
      <div class="flex gap-2">
        <Link :href="tautan.pratinjau"
              class="rounded-xl border border-stone-200 px-4 py-2 text-[12px] font-bold
                     text-stone-600 hover:bg-stone-50">Pratinjau</Link>
        <a :href="tautan.info"
           class="rounded-xl border border-stone-200 px-4 py-2 text-[12px] font-bold
                  text-cam-lime-deep hover:bg-cam-lime-soft">Info Kursus</a>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5
                flex flex-wrap items-center justify-between gap-4">
      <div>
        <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">Kode Akses Kursus</div>
        <div class="flex items-center gap-3 mt-1.5">
          <span class="text-[22px] font-bold tracking-[0.3em] text-cam-ink num">{{ kursus.kode ?? '—' }}</span>
          <span v-if="kursus.perluKode" class="text-[10px] font-bold bg-cam-lime-soft
                                               text-cam-lime-deep px-2 py-0.5 rounded">WAJIB</span>
          <span v-else class="text-[10px] font-bold bg-stone-100 text-stone-400 px-2 py-0.5 rounded">
            TIDAK AKTIF
          </span>
        </div>
        <p class="text-[11.5px] text-stone-400 mt-1">
          Berikan kode ini kepada peserta yang berhak mengambil kursus.
        </p>
      </div>
      <a :href="tautan.info" class="rounded-xl border border-stone-200 px-4 py-2 text-[12px]
                                    font-bold text-cam-lime-deep hover:bg-cam-lime-soft transition">Ubah kode</a>
    </div>

    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-6">
      <h3 class="text-[14px] font-bold text-cam-ink mb-4">Modul &amp; Materi</h3>

      <div class="space-y-3">
        <div v-for="m in modul" :key="m.id" class="rounded-xl border border-stone-200 p-4">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <div class="text-[13.5px] font-bold text-cam-ink">{{ m.urutan }}. {{ m.judul }}</div>
              <p v-if="m.keterangan" class="text-[12px] text-stone-400 mt-0.5">{{ m.keterangan }}</p>
            </div>
            <button type="button" @click="hapus(m.urlHapus, `Hapus modul “${m.judul}” beserta materinya?`)"
                    class="text-[11.5px] font-semibold text-red-500 hover:bg-red-50 px-2.5 py-1 rounded-lg">
              Hapus
            </button>
          </div>

          <ul v-if="m.materi.length" class="mt-3 space-y-1.5">
            <li v-for="x in m.materi" :key="x.id"
                class="flex items-center justify-between gap-2 text-[12.5px] bg-stone-50 rounded-lg px-3 py-2">
              <span class="flex items-center gap-2 min-w-0">
                <span class="text-[9.5px] uppercase font-bold bg-cam-lime-soft text-cam-lime-deep
                             px-1.5 py-0.5 rounded tracking-wide">{{ x.jenis }}</span>
                <span class="text-stone-600 clamp-1">{{ x.judul }}</span>
              </span>
              <button type="button" @click="hapus(x.urlHapus, `Hapus materi “${x.judul}”?`)"
                      class="text-[11px] text-red-400 hover:text-red-600">✕</button>
            </li>
          </ul>

          <div class="mt-3">
            <button type="button" @click="terbuka[`m${m.id}`] = !terbuka[`m${m.id}`]"
                    class="text-[11.5px] font-bold text-cam-lime-deep hover:underline">
              {{ terbuka[`m${m.id}`] ? '−' : '+' }} Tambah materi
            </button>

            <form v-show="terbuka[`m${m.id}`]" class="mt-2.5 grid sm:grid-cols-[1fr_130px] gap-2"
                  @submit.prevent="tambahMateri(m)">
              <input v-model="materi[m.id].title" placeholder="Judul materi" :class="kecil">
              <select v-model="materi[m.id].type" :class="kecil">
                <option value="document">Dokumen</option>
                <option value="pdf">PDF</option>
                <option value="pptx">PPTX</option>
                <option value="video">Video</option>
              </select>
              <input v-model="materi[m.id].url" placeholder="Tautan (opsional) https://…"
                     :class="[kecil, 'sm:col-span-2']">
              <button type="submit" :disabled="!materi[m.id].title.trim()"
                      class="sm:col-span-2 lime-gradient rounded-lg text-white py-2 text-[12px]
                             font-bold hover:brightness-105 disabled:opacity-40">Simpan Materi</button>
            </form>
          </div>
        </div>

        <p v-if="!modul.length" class="text-[12.5px] text-stone-400 text-center py-6">Belum ada modul.</p>
      </div>

      <form class="mt-4 pt-4 border-t border-stone-100 grid gap-2" @submit.prevent="tambahModul">
        <input v-model="formModul.title" placeholder="Judul modul baru" :class="besar">
        <input v-model="formModul.description" placeholder="Deskripsi singkat (opsional)" :class="besar">
        <button type="submit" :disabled="formModul.processing || !formModul.title.trim()"
                class="lime-gradient shadow-glow rounded-xl text-white py-2.5 text-[12.5px]
                       font-bold hover:brightness-105 disabled:opacity-40">+ Tambah Modul</button>
      </form>
    </section>

    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-6">
      <h3 class="text-[14px] font-bold text-cam-ink mb-4">Kuis &amp; Soal</h3>

      <div class="space-y-3">
        <div v-for="q in kuis" :key="q.id" class="rounded-xl border border-stone-200 p-4">
          <div class="flex items-center justify-between gap-3">
            <div>
              <div class="text-[13.5px] font-bold text-cam-ink">{{ q.judul }}</div>
              <div class="text-[11px] text-stone-400 mt-0.5">
                {{ q.soal.length }} soal · lulus ≥ {{ q.nilaiLulus }}
              </div>
            </div>
            <button type="button" @click="hapus(q.urlHapus, `Hapus kuis “${q.judul}” beserta soalnya?`)"
                    class="text-[11.5px] font-semibold text-red-500 hover:bg-red-50 px-2.5 py-1 rounded-lg">
              Hapus
            </button>
          </div>

          <ul v-if="q.soal.length" class="mt-3 space-y-1.5">
            <li v-for="(x, i) in q.soal" :key="x.id"
                class="flex items-start justify-between gap-2 text-[12.5px] bg-stone-50 rounded-lg px-3 py-2">
              <div class="min-w-0">
                <div class="text-stone-600">{{ i + 1 }}. {{ x.soal }}</div>
                <div class="text-[11px] text-cam-lime-deep mt-0.5">✓ {{ x.jawaban }}</div>
              </div>
              <button type="button" @click="hapus(x.urlHapus, 'Hapus soal ini?')"
                      class="text-[11px] text-red-400 hover:text-red-600">✕</button>
            </li>
          </ul>

          <div class="mt-3">
            <button type="button" @click="terbuka[`q${q.id}`] = !terbuka[`q${q.id}`]"
                    class="text-[11.5px] font-bold text-cam-lime-deep hover:underline">
              {{ terbuka[`q${q.id}`] ? '−' : '+' }} Tambah soal
            </button>

            <form v-show="terbuka[`q${q.id}`]" class="mt-2.5 space-y-2" @submit.prevent="tambahSoal(q)">
              <input v-model="soalBaru[q.id].question" placeholder="Tulis pertanyaan"
                     :class="[kecil, 'w-full']">
              <div v-for="(_, i) in soalBaru[q.id].options" :key="i" class="flex items-center gap-2">
                <input v-model.number="soalBaru[q.id].correct_index" type="radio" :value="i"
                       title="Tandai jawaban benar" class="accent-[color:var(--eq-aksen,#F57C00)]">
                <input v-model="soalBaru[q.id].options[i]" :placeholder="`Pilihan ${HURUF[i]}`"
                       :class="[kecil, 'flex-1']">
              </div>
              <p class="text-[11px] text-stone-400">Bulatan di kiri = kunci jawaban.</p>
              <button type="submit"
                      :disabled="!soalBaru[q.id].question.trim()
                                 || soalBaru[q.id].options.some((o) => !o.trim())"
                      class="w-full lime-gradient rounded-lg text-white py-2 text-[12px]
                             font-bold hover:brightness-105 disabled:opacity-40">Simpan Soal</button>
            </form>
          </div>
        </div>

        <p v-if="!kuis.length" class="text-[12.5px] text-stone-400 text-center py-6">Belum ada kuis.</p>
      </div>

      <form class="mt-4 pt-4 border-t border-stone-100 grid sm:grid-cols-[1fr_130px] gap-2"
            @submit.prevent="tambahKuis">
        <input v-model="formKuis.title" placeholder="Judul kuis baru" :class="besar">
        <input v-model="formKuis.pass_score" type="number" min="0" max="100" title="Nilai lulus" :class="besar">
        <button type="submit" :disabled="formKuis.processing || !formKuis.title.trim()"
                class="sm:col-span-2 lime-gradient shadow-glow rounded-xl text-white py-2.5
                       text-[12.5px] font-bold hover:brightness-105 disabled:opacity-40">+ Tambah Kuis</button>
      </form>
    </section>
  </div>
</template>

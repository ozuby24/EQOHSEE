<script setup lang="ts">
/**
 * Ikhtisar satu materi — halaman pengelola.
 *
 * Terpisah dari halaman kelola kursus dengan sengaja. Formulir sebaris
 * di sana dipakai menambah materi cepat-cepat sambil menyusun kerangka
 * kursus; menempelkan tujuh medan lagi ke sana akan membuat pekerjaan
 * yang paling sering dilakukan menjadi yang paling berat.
 */
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import type { HalamanKelolaMateri } from '../../types';
import Dialog from '../../Components/Dialog.vue';
import Putaran from '../../Components/Putaran.vue';
import { useDialog } from '../../dialog';

const { dialog, tanya, batal, lanjut } = useDialog();

const props = defineProps<HalamanKelolaMateri>();

const form = useForm({
  title: String(props.awal.title ?? ''),
  type: String(props.awal.type ?? 'document'),
  url: String(props.awal.url ?? ''),
  description: String(props.awal.description ?? ''),
  outcomes: String(props.awal.outcomes ?? ''),
  prerequisite: String(props.awal.prerequisite ?? ''),
  duration_minutes: String(props.awal.duration_minutes ?? ''),
  content: String(props.awal.content ?? ''),
  sop_url: String(props.awal.sop_url ?? ''),
});

const lampiranBaru = useForm({ title: '', url: '' });

/* Berapa butir hasil belajar yang benar-benar terisi. Dihitung dengan
   aturan yang SAMA dengan sisi server — baris kosong dibuang — supaya
   angka di layar tidak menjanjikan enam butir sementara yang tersimpan
   empat. */
const jumlahHasil = computed(
  () => form.outcomes.split(/\r\n|\r|\n/).map((b) => b.trim()).filter((b) => b !== '').length,
);

function simpan() {
  form.put(props.tautan.simpan);
}

function tambahLampiran() {
  lampiranBaru.post(props.tautan.tambahLampiran, {
    preserveScroll: true,
    onSuccess: () => lampiranBaru.reset(),
  });
}

/* Bertanya lebih dulu. Lampiran yang hilang pada ketukan pertama tidak
   menimbulkan galat — yang hilang adalah alamatnya, dan alamat itu
   biasanya tidak tersimpan di tempat lain mana pun. */
async function hapusLampiran(url: string, judul: string) {
  if (!await tanya(`Hapus lampiran “${judul}”?`)) return;

  router.delete(url, { preserveScroll: true });
}

const isian = 'ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13px] transition';
const label = 'block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5';
const bantu = 'text-[11px] text-stone-400 mt-1.5 leading-relaxed';
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-2xl mx-auto space-y-4">

    <form class="bg-white rounded-2xl shadow-card border border-stone-100 p-6 space-y-5"
          @submit.prevent="simpan">

      <div v-if="Object.keys(form.errors).length"
           class="rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]">
        <ul class="space-y-0.5">
          <li v-for="(pesan, k) in form.errors" :key="k">• {{ pesan }}</li>
        </ul>
      </div>

      <div class="grid sm:grid-cols-[1fr_150px] gap-3">
        <div>
          <label :class="label" for="judul-materi">Judul materi</label>
          <input id="judul-materi" v-model="form.title" :class="isian">
        </div>
        <div>
          <label :class="label" for="jenis-materi">Jenis</label>
          <select id="jenis-materi" v-model="form.type" :class="isian" aria-label="Jenis materi">
            <option value="document">Dokumen</option>
            <option value="pdf">PDF</option>
            <option value="pptx">Presentasi</option>
            <option value="video">Video</option>
          </select>
        </div>
      </div>

      <div>
        <label :class="label" for="url-materi">Tautan utama</label>
        <input id="url-materi" v-model="form.url" placeholder="https://…" :class="isian">

        <!-- Keputusan semat dikatakan SEKARANG, bukan sesudah dua puluh
             peserta bertanya kenapa videonya tidak muncul. -->
        <p v-if="semat" class="text-[11px] text-emerald-700 mt-1.5 font-semibold">
          Akan diputar langsung di halaman materi.
        </p>
        <p v-else-if="awal.url" class="text-[11px] text-amber-700 mt-1.5 font-semibold">
          Tautan ini tidak dapat diputar di halaman — akan tergambar sebagai kartu
          yang membuka tab baru. Hanya YouTube dan Vimeo yang dapat disematkan.
        </p>
        <p v-else :class="bantu">Kosongkan bila materinya berupa bacaan saja.</p>
      </div>

      <div>
        <label :class="label" for="ket-materi">Keterangan singkat</label>
        <textarea id="ket-materi" v-model="form.description" rows="3" :class="[isian, 'leading-relaxed']"></textarea>
        <p :class="bantu">Satu paragraf yang dibaca peserta sebelum memutuskan membukanya.</p>
      </div>

      <div>
        <label :class="label" for="hasil-materi">
          Yang akan dipelajari
          <span v-if="jumlahHasil" class="text-stone-400 font-semibold normal-case tracking-normal">
            — {{ jumlahHasil }} butir
          </span>
        </label>
        <textarea id="hasil-materi" v-model="form.outcomes" rows="5" :class="[isian, 'leading-relaxed']"
                  placeholder="Satu butir per baris"></textarea>
        <p :class="bantu">Satu butir per baris. Baris kosong diabaikan.</p>
      </div>

      <div class="grid sm:grid-cols-[1fr_160px] gap-3">
        <div>
          <label :class="label" for="syarat-materi">Prasyarat</label>
          <input id="syarat-materi" v-model="form.prerequisite" :class="isian"
                 placeholder="mis. Baca SOP-07 lebih dulu">
        </div>
        <div>
          <label :class="label" for="durasi-materi">Durasi (menit)</label>
          <input id="durasi-materi" v-model="form.duration_minutes" type="number" min="1" max="1440" :class="isian">
        </div>
      </div>

      <div>
        <label :class="label" for="sop-materi">Tautan SOP terkait</label>
        <input id="sop-materi" v-model="form.sop_url" placeholder="https://…" :class="isian">
      </div>

      <div>
        <label :class="label" for="bacaan-materi">Bacaan</label>
        <textarea id="bacaan-materi" v-model="form.content" rows="8" :class="[isian, 'leading-relaxed']"></textarea>
        <p :class="bantu">Teks biasa, tergambar di bawah tautan utamanya. Pergantian baris dipertahankan.</p>
      </div>

      <div class="flex flex-wrap items-center gap-3 pt-1">
        <button type="submit" :disabled="form.processing"
                class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2.5 text-[12.5px]
                       font-bold hover:brightness-105 transition disabled:opacity-40
                       inline-flex items-center gap-2">
          <Putaran v-if="form.processing" :ukuran="13" />
          {{ form.processing ? 'Menyimpan…' : 'Simpan ikhtisar' }}
        </button>

        <Link :href="tautan.pratinjau" class="text-[12.5px] font-semibold text-cam-lime-deep hover:underline">
          Lihat sebagai peserta →
        </Link>

        <a :href="tautan.batal" class="text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink">Kembali</a>
      </div>
    </form>

    <!-- Lampiran disimpan TERPISAH dari formulir di atas: menambahkannya
         tidak boleh menuntut seluruh ikhtisar disimpan ulang, dan
         sebaliknya menyimpan ikhtisar tidak boleh menghapus lampiran
         yang baru saja ditambahkan orang lain. -->
    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-6">
      <h3 class="text-[14px] font-bold text-cam-ink mb-3.5">Lampiran</h3>

      <ul v-if="lampiran.length" class="space-y-1.5 mb-4">
        <li v-for="l in lampiran" :key="l.id"
            class="flex items-center justify-between gap-2 bg-stone-50 rounded-lg px-3 py-2">
          <span class="min-w-0">
            <strong class="block text-[12.5px] font-bold text-cam-ink truncate">{{ l.judul }}</strong>
            <small class="block text-[11px] text-stone-400 truncate">{{ l.url }}</small>
          </span>
          <button type="button" class="text-[11px] text-red-400 hover:text-red-600 shrink-0"
                  @click="hapusLampiran(l.urlHapus, l.judul)">✕</button>
        </li>
      </ul>

      <p v-else class="text-[12.5px] text-stone-400 mb-4">Belum ada lampiran.</p>

      <form class="grid sm:grid-cols-[1fr_1fr_auto] gap-2" @submit.prevent="tambahLampiran">
        <input v-model="lampiranBaru.title" placeholder="Judul lampiran"
               class="ring-focus rounded-lg border border-stone-200 px-3 py-2 text-[12px]">
        <input v-model="lampiranBaru.url" placeholder="https://…"
               class="ring-focus rounded-lg border border-stone-200 px-3 py-2 text-[12px]">
        <button type="submit"
                :disabled="lampiranBaru.processing || !lampiranBaru.title.trim() || !lampiranBaru.url.trim()"
                class="lime-gradient rounded-lg text-white px-4 py-2 text-[12px] font-bold
                       hover:brightness-105 disabled:opacity-40 inline-flex items-center gap-1.5">
          <Putaran v-if="lampiranBaru.processing" :ukuran="11" />
          Tambah
        </button>
      </form>

      <p v-if="lampiranBaru.errors.url" class="text-[11.5px] text-red-600 mt-2">{{ lampiranBaru.errors.url }}</p>
      <p v-if="lampiranBaru.errors.title" class="text-[11.5px] text-red-600 mt-2">{{ lampiranBaru.errors.title }}</p>
    </section>
  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>

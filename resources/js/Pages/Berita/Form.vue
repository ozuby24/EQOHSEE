<script setup lang="ts">
/**
 * Formulir pengumuman.
 *
 * Mengirim sebagai multipart karena membawa sampul dan lampiran. Itu
 * sebabnya pembaruan dikirim lewat POST dengan `_method: 'put'` alih-alih
 * form.put(): PHP tidak mengurai badan multipart pada permintaan PUT —
 * $_FILES kosong, dan berkas yang diunggah hilang tanpa satu pun galat.
 */
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import type { HalamanFormBerita } from '../../types';
import Putaran from '../../Components/Putaran.vue';

const props = defineProps<HalamanFormBerita>();

/* Bentuknya ditulis, bukan Record<string, unknown>. Tipe seluas itu
   membuat v-model pada tiap kolom kehilangan tipenya, dan salah ketik
   nama medan — `form.exerpt` — lolos tanpa satu pun peringatan sampai
   isian itu diam-diam tidak pernah terkirim. */
const form = useForm({
  title: String(props.awal.title ?? ''),
  excerpt: String(props.awal.excerpt ?? ''),
  content: String(props.awal.content ?? ''),
  published_at: String(props.awal.published_at ?? ''),
  cover: null as File | null,
  lampiran: null as File | null,
});

/* Pratinjau sampul yang BARU dipilih, di atas sampul yang sudah ada.
   Tanpa ini, memilih berkas tidak mengubah apa pun di layar sampai
   halamannya disimpan — dan yang memilih gambar salah baru tahu
   sesudah pengumumannya terbit. */
const pratinjau = ref<string | null>(null);

const sampulTampil = computed(() => pratinjau.value ?? props.berkas.sampul);

const namaLampiranBaru = ref<string | null>(null);

function pilihSampul(e: Event) {
  const f = (e.target as HTMLInputElement).files?.[0] ?? null;
  form.cover = f;

  /* URL lama dilepas sebelum yang baru dibuat. Tanpa revokeObjectURL,
     setiap gambar yang sempat dipilih tetap tertahan di memori peramban
     sampai halamannya ditinggalkan. */
  if (pratinjau.value) URL.revokeObjectURL(pratinjau.value);
  pratinjau.value = f ? URL.createObjectURL(f) : null;
}

function pilihLampiran(e: Event) {
  const f = (e.target as HTMLInputElement).files?.[0] ?? null;
  form.lampiran = f;
  namaLampiranBaru.value = f?.name ?? null;
}

function simpan() {
  /* forceFormData: satu-satunya cara menjamin multipart ketika tidak
     ada berkas yang dipilih sekali pun. Tanpa itu Inertia mengirim JSON
     pada penyimpanan tanpa unggahan dan multipart pada yang dengan
     unggahan — dua bentuk permintaan untuk satu formulir, dan yang
     kedua baru diuji orang ketika sudah terlanjur dipakai. */
  if (props.tersimpan) {
    form.transform((d) => ({ ...d, _method: 'put' }))
        .post(props.tautan.simpan, { forceFormData: true });
  } else {
    form.post(props.tautan.simpan, { forceFormData: true });
  }
}

const isian = 'ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition';
const label = 'block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5';
const bantu = 'text-[11px] text-stone-400 mt-1.5 leading-relaxed';
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-2xl mx-auto">
    <form class="bg-white rounded-2xl shadow-card border border-stone-100 p-6 space-y-5"
          @submit.prevent="simpan">

      <div v-if="Object.keys(form.errors).length"
           class="rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]">
        <ul class="space-y-0.5">
          <li v-for="(pesan, k) in form.errors" :key="k">• {{ pesan }}</li>
        </ul>
      </div>

      <div>
        <label :class="label">Judul</label>
        <input v-model="form.title" :class="isian">
      </div>

      <div>
        <label :class="label">Tanggal terbit</label>
        <input v-model="form.published_at" type="date" :class="isian" aria-label="Tanggal terbit">
      </div>

      <div>
        <label :class="label">Ringkasan</label>
        <textarea v-model="form.excerpt" rows="2" :class="[isian, 'leading-relaxed']"
                  placeholder="Satu-dua kalimat yang muncul di daftar pengumuman"></textarea>
        <p :class="bantu">
          Muncul di daftar pengumuman, bukan di dalam pengumumannya — jadi tidak
          apa-apa mengulang kalimat pembuka isi. Dikosongkan, isinya dipotong
          otomatis, dan potongan otomatis kerap terputus di tengah angka atau nama lokasi.
        </p>
      </div>

      <div>
        <label :class="label">Isi</label>
        <textarea v-model="form.content" rows="10" :class="[isian, 'leading-relaxed']"></textarea>
        <p :class="bantu">Teks biasa. Pergantian baris dipertahankan apa adanya.</p>
      </div>

      <div>
        <label :class="label" for="cover">Sampul</label>

        <img v-if="sampulTampil" :src="sampulTampil" alt="Pratinjau sampul"
             class="w-full h-40 object-cover rounded-xl border border-stone-200 mb-2.5 bg-stone-50">

        <input id="cover" type="file" accept="image/jpeg,image/png,image/webp"
               class="block w-full text-[12px] text-stone-500
                      file:mr-3 file:py-2 file:px-3.5 file:rounded-lg file:border-0
                      file:text-[11.5px] file:font-bold file:bg-stone-100 file:text-cam-ink
                      hover:file:bg-stone-200"
               @change="pilihSampul">
        <p :class="bantu">JPG, PNG, atau WEBP sampai 4 MB. Dibiarkan kosong, sampul yang lama tetap terpakai.</p>
      </div>

      <div>
        <label :class="label" for="lampiran">Lampiran</label>

        <p v-if="namaLampiranBaru" class="text-[12px] font-semibold text-cam-ink mb-2">
          Berkas baru: {{ namaLampiranBaru }}
        </p>
        <a v-else-if="berkas.lampiran" :href="berkas.lampiran.url"
           class="block text-[12px] font-semibold text-cam-lime-deep hover:underline mb-2">
          Terpasang: {{ berkas.lampiran.nama }}
        </a>

        <input id="lampiran" type="file"
               class="block w-full text-[12px] text-stone-500
                      file:mr-3 file:py-2 file:px-3.5 file:rounded-lg file:border-0
                      file:text-[11.5px] file:font-bold file:bg-stone-100 file:text-cam-ink
                      hover:file:bg-stone-200"
               @change="pilihLampiran">
        <p :class="bantu">
          Satu berkas saja — PDF, dokumen, lembar kerja, atau gambar sampai 20 MB.
          Berkas yang lebih dari satu adalah dokumen, dan dokumen punya modulnya sendiri.
        </p>
      </div>

      <div class="flex items-center gap-3 pt-1">
        <button type="submit" :disabled="form.processing"
                class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2.5 text-[12.5px]
                       font-bold hover:brightness-105 transition disabled:opacity-40
                       inline-flex items-center gap-2">
          <Putaran v-if="form.processing" :ukuran="13" />
          <template v-if="form.processing">
            {{ form.progress ? `Mengunggah ${form.progress.percentage}%` : 'Menyimpan…' }}
          </template>
          <template v-else>Simpan</template>
        </button>
        <a :href="tautan.batal" class="text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink">Batal</a>
      </div>
    </form>
  </div>
</template>

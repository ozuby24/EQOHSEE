<script setup lang="ts">
/**
 * Gudang — lokasi penyimpanan dan syaratnya.
 *
 * Pantangan ditampilkan pada kartu lokasinya sendiri, bukan dikumpulkan
 * di satu daftar: yang perlu diketahui petugas bukan berapa banyak
 * pelanggaran yang ada, melainkan rak mana yang harus dibereskan.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { HalamanLokasiGudang } from '../../types';

const props = defineProps<HalamanLokasiGudang>();

const buka = ref(false);

const form = useForm({
  kode: '', nama: '', jenis: 'umum', lokasi: '', penanggung_jawab: '',
  berventilasi: false, tahan_api: false, ada_tanggul: false, ada_apar: false, ada_eyewash: false,
} as Record<string, string | boolean>);

function simpan() {
  form.post(props.tautan.simpan, {
    onSuccess: () => { form.reset(); buka.value = false; },
  });
}

const nadaJenis = (j: string) => (j === 'b3' ? 'merah' : j === 'apd' ? 'hijau' : 'biru');
const labelJenis = (j: string) => (j === 'b3' ? 'B3' : j[0].toUpperCase() + j.slice(1));

const isian = 'w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px] ring-focus transition';
const label = 'block text-[12px] font-semibold text-cam-ink mb-1.5';
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-[1200px] mx-auto space-y-5">

    <div v-if="bolehUbah" class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      <button type="button" class="w-full px-6 py-4 text-left text-[13px] font-bold text-cam-ink"
              @click="buka = !buka">
        {{ buka ? '−' : '+' }} Tambah Lokasi Penyimpanan
      </button>

      <form v-show="buka" class="px-6 pb-6 pt-2 border-t border-stone-100" @submit.prevent="simpan">
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label :class="label">Kode <span class="text-red-500">*</span></label>
            <input v-model="form.kode" :class="isian">
            <p v-if="form.errors.kode" class="text-[11.5px] text-red-600 mt-1">{{ form.errors.kode }}</p>
          </div>
          <div>
            <label :class="label">Jenis <span class="text-red-500">*</span></label>
            <select v-model="form.jenis" :class="isian" aria-label="Jenis">
              <option v-for="o in opsi.jenis" :key="o.nilai" :value="o.nilai">{{ o.label }}</option>
            </select>
          </div>
          <div class="sm:col-span-2">
            <label :class="label">Nama <span class="text-red-500">*</span></label>
            <input v-model="form.nama" :class="isian">
            <p v-if="form.errors.nama" class="text-[11.5px] text-red-600 mt-1">{{ form.errors.nama }}</p>
          </div>
          <div>
            <label :class="label">Letak</label>
            <input v-model="form.lokasi" placeholder="Blok, area, atau koordinat" :class="isian">
          </div>
          <div>
            <label :class="label">Penanggung Jawab</label>
            <input v-model="form.penanggung_jawab" :class="isian">
          </div>
        </div>

        <p class="text-[12px] font-semibold text-cam-ink mt-5 mb-2">Syarat Penyimpanan</p>
        <div class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
          <label v-for="s in opsi.syarat" :key="s.kunci" class="inline-flex items-center gap-2.5 cursor-pointer">
            <input v-model="form[s.kunci]" type="checkbox"
                   class="rounded border-stone-300 accent-[color:var(--eq-aksen,#F57C00)]">
            <span class="text-[12.5px] text-stone-600">{{ s.label }}</span>
          </label>
        </div>

        <div class="mt-5 flex justify-end">
          <button type="submit" :disabled="form.processing" class="eq-btn-utama disabled:opacity-40"
                  style="flex:none;padding:10px 22px">
            {{ form.processing ? 'Menyimpan…' : 'Simpan Lokasi' }}
          </button>
        </div>
      </form>
    </div>

    <div v-if="!lokasi.length"
         class="bg-white rounded-2xl shadow-card border border-stone-100 px-6 py-12 text-center">
      <p class="text-[13.5px] font-bold text-cam-ink">Belum ada lokasi penyimpanan</p>
      <p class="text-[12.5px] text-stone-500 mt-1">Tambahkan gudang atau rak untuk mulai menata barang.</p>
    </div>

    <div v-else class="grid gap-4 lg:grid-cols-2">
      <section v-for="l in lokasi" :key="l.id" class="eq-panel">
        <div class="eq-panel-kepala">
          <div class="min-w-0">
            <h3 class="truncate">{{ l.nama }}</h3>
            <p class="text-[11.5px] text-stone-400 mt-0.5">
              {{ l.kode }}<template v-if="l.letak"> · {{ l.letak }}</template>
            </p>
          </div>
          <span class="eq-lencana-kat" :class="`k-${nadaJenis(l.jenis)}`">{{ labelJenis(l.jenis) }}</span>
        </div>

        <div class="flex flex-wrap gap-1.5">
          <span v-for="s in l.syarat" :key="s.kunci"
                class="text-[10.5px] font-bold px-2 py-1 rounded-md"
                :class="s.ada ? 'bg-emerald-50 text-emerald-700' : 'bg-stone-100 text-stone-400'">
            {{ s.ada ? '✓' : '×' }} {{ s.label }}
          </span>
        </div>

        <p v-if="l.pj" class="text-[11.5px] text-stone-500 mt-3">Penanggung jawab: {{ l.pj }}</p>

        <div v-if="l.langgar.length" class="mt-3.5 rounded-xl bg-red-50 border border-red-100 px-3.5 py-3">
          <p class="text-[12px] font-bold text-red-800">
            {{ l.langgar.length }} pasang bahan berpantangan di lokasi ini
          </p>
          <p v-for="(x, i) in l.langgar" :key="i" class="text-[11.5px] text-red-700 mt-1.5">
            <b>{{ x.a }}</b> × <b>{{ x.b }}</b> — {{ x.alasan }}
          </p>
        </div>

        <p class="text-[11.5px] text-stone-400 mt-3.5">{{ l.jumlah }} jenis barang tersimpan di sini</p>
      </section>
    </div>

  </div>
</template>

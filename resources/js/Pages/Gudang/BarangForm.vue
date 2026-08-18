<script setup lang="ts">
/**
 * Gudang — formulir barang.
 *
 * Blok tambahan mengikuti kategori yang dipilih. Kolom yang tersembunyi
 * tetap ikut terkirim, dan server-lah yang membuangnya — kelas bahaya
 * yang menempel pada material membuat matriks pantangan memperingatkan
 * hal yang sebenarnya bukan bahan kimia.
 */
import { Head, useForm } from '@inertiajs/vue3';
import type { HalamanFormBarangGudang } from '../../types';

const props = defineProps<HalamanFormBarangGudang>();

const form = useForm<Record<string, any>>({ ...props.awal, msds: null as File | null });

function pilihBerkas(e: Event) {
  const f = (e.target as HTMLInputElement).files;
  form.msds = f && f.length ? f[0] : null;
}

function simpan() {
  // Unggahan berkas dengan PUT tidak dikenali PHP; Inertia menanganinya
  // sendiri dengan mengubahnya menjadi POST ber-_method saat ada berkas.
  if (props.tersimpan) form.put(props.tautan.simpan);
  else form.post(props.tautan.simpan);
}

const isian = 'w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px] ring-focus transition';
const label = 'block text-[12px] font-semibold text-cam-ink mb-1.5';
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-[900px] mx-auto space-y-5">

    <div v-if="Object.keys(form.errors).length"
         class="rounded-xl bg-red-50 border border-red-100 px-4 py-3">
      <p class="text-[12.5px] font-bold text-red-700">
        Ada {{ Object.keys(form.errors).length }} isian yang perlu diperbaiki:
      </p>
      <ul class="mt-1.5 space-y-0.5">
        <li v-for="(pesan, k) in form.errors" :key="k" class="text-[12px] text-red-600">· {{ pesan }}</li>
      </ul>
    </div>

    <form class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden"
          @submit.prevent="simpan">

      <div class="px-6 py-5 border-b border-stone-100">
        <h3 class="text-[15px] font-bold text-cam-ink">{{ nama }}</h3>
        <p class="text-[12.5px] text-stone-500 mt-1">
          Stok tidak diisi di sini — saldo berjalan dihitung dari mutasi penerimaan dan pengeluaran.
        </p>
      </div>

      <div class="px-6 py-5 grid gap-4 sm:grid-cols-2">
        <div>
          <label :class="label">Kode <span class="text-red-500">*</span></label>
          <input v-model="form.kode" :class="isian">
        </div>

        <div>
          <label :class="label">Kategori <span class="text-red-500">*</span></label>
          <select v-model="form.kategori" :class="isian" aria-label="Kategori">
            <option v-for="o in opsi.kategori" :key="o.nilai" :value="o.nilai">{{ o.label }}</option>
          </select>
        </div>

        <div class="sm:col-span-2">
          <label :class="label">Nama Barang <span class="text-red-500">*</span></label>
          <input v-model="form.nama" :class="isian">
        </div>

        <div>
          <label :class="label">Satuan <span class="text-red-500">*</span></label>
          <input v-model="form.satuan" :class="isian">
        </div>

        <div>
          <label :class="label">Stok Minimum</label>
          <input v-model="form.stok_min" type="number" step="0.01" min="0" :class="isian">
          <p class="text-[11px] text-stone-400 mt-1">
            Nol berarti belum ditetapkan — barangnya tidak akan ditandai menipis.
          </p>
        </div>

        <div class="sm:col-span-2">
          <label :class="label">Lokasi Penyimpanan</label>
          <select v-model="form.lokasi_id" :class="isian" aria-label="Lokasi Penyimpanan">
            <option value="">— belum ditentukan —</option>
            <option v-for="o in opsi.lokasi" :key="o.nilai" :value="o.nilai">{{ o.label }}</option>
          </select>
        </div>
      </div>

      <div v-show="form.kategori === 'b3'" class="px-6 py-5 border-t border-stone-100 bg-red-50/40">
        <h4 class="text-[13px] font-bold text-red-800 mb-3">Penggolongan B3</h4>

        <div class="grid gap-4 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <label :class="label">Kelas Bahaya</label>
            <select v-model="form.kelas_b3" :class="isian" aria-label="Kelas Bahaya">
              <option value="">— belum digolongkan —</option>
              <option v-for="o in opsi.kelasB3" :key="o.nilai" :value="o.nilai">{{ o.label }}</option>
            </select>
            <p class="text-[11px] text-stone-500 mt-1">
              Dipakai memeriksa pantangan penyimpanan antar bahan di lokasi yang sama.
            </p>
          </div>

          <div>
            <label :class="label">Wujud</label>
            <select v-model="form.wujud" :class="isian" aria-label="Wujud">
              <option value="">—</option>
              <option v-for="w in opsi.wujud" :key="w" :value="w">{{ w[0].toUpperCase() + w.slice(1) }}</option>
            </select>
          </div>

          <div>
            <label :class="label">Nomor UN</label>
            <input v-model="form.un_number" :class="isian">
          </div>

          <div class="sm:col-span-2">
            <label :class="label">Lembar Data Keselamatan (LDK / MSDS)</label>
            <p v-if="msds" class="text-[12px] mb-1.5">
              <a :href="msds" target="_blank" rel="noopener" class="font-semibold"
                 style="color:var(--eq-aksen,#F57C00)">Lihat berkas tersimpan →</a>
            </p>
            <input type="file" accept="application/pdf" @change="pilihBerkas"
                   class="block w-full text-[12.5px] text-stone-600
                          file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0
                          file:text-[12px] file:font-semibold file:bg-stone-100 file:text-cam-ink">
            <p class="text-[11px] text-stone-500 mt-1">PDF, paling besar 5 MB.</p>
            <p v-if="form.progress" class="text-[11px] text-stone-500 mt-1">
              Mengunggah {{ form.progress.percentage }}%
            </p>
          </div>
        </div>
      </div>

      <div v-show="form.kategori === 'apd'" class="px-6 py-5 border-t border-stone-100 bg-emerald-50/40">
        <h4 class="text-[13px] font-bold text-emerald-800 mb-3">Alat Pelindung Diri</h4>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label :class="label">Masa Pakai (bulan)</label>
            <input v-model="form.masa_pakai_bulan" type="number" min="1" max="600" :class="isian">
          </div>
          <div>
            <label :class="label">Ukuran</label>
            <input v-model="form.ukuran" placeholder="S / M / L / 42" :class="isian">
          </div>
        </div>
      </div>

      <div v-show="form.kategori === 'material'" class="px-6 py-5 border-t border-stone-100 bg-sky-50/40">
        <h4 class="text-[13px] font-bold text-sky-800 mb-3">Material &amp; Suku Cadang</h4>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label :class="label">Part Number</label>
            <input v-model="form.part_number" :class="isian">
          </div>
          <div>
            <label :class="label">Merk</label>
            <input v-model="form.merk" :class="isian">
          </div>
        </div>
      </div>

      <div class="px-6 py-5 border-t border-stone-100">
        <label :class="label">Keterangan</label>
        <textarea v-model="form.keterangan" rows="3" :class="isian"></textarea>

        <label class="inline-flex items-center gap-2.5 mt-4 cursor-pointer">
          <input v-model="form.aktif" type="checkbox" class="rounded border-stone-300
                 accent-[color:var(--eq-aksen,#F57C00)]">
          <span class="text-[12.5px] text-stone-600">Barang aktif</span>
        </label>
      </div>

      <div class="px-6 py-4 bg-stone-50 border-t border-stone-100 flex justify-end gap-2.5">
        <a :href="tautan.batal"
           class="px-4 py-2.5 text-[12.5px] font-semibold text-stone-500 self-center hover:underline">Batal</a>
        <button type="submit" :disabled="form.processing" class="eq-btn-utama disabled:opacity-40"
                style="flex:none;padding:10px 22px">
          {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
        </button>
      </div>
    </form>

  </div>
</template>

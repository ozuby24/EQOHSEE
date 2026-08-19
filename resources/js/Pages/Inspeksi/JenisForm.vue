<script setup lang="ts">
/**
 * Inspeksi — jenis dan parameternya.
 *
 * Parameter hanya dapat ditambahkan setelah jenisnya tersimpan, sebab
 * tiap parameter menempel pada id jenisnya. Menampilkan isian parameter
 * sebelum itu hanya menyiapkan isian yang pasti gagal disimpan.
 */
import { Head, router, useForm } from '@inertiajs/vue3';
import type { HalamanJenisFormInspeksi } from '../../types';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
const { dialog, tanya, batal, lanjut } = useDialog();


const props = defineProps<HalamanJenisFormInspeksi>();

const form = useForm({ ...props.awal });

function simpan() {
  if (props.tersimpan) form.put(props.tautan.simpan);
  else form.post(props.tautan.simpan);
}

const formItem = useForm({ uraian: '', kelompok: '', acuan: '', risiko_default: '' });

function tambahItem() {
  if (!props.tautan.tambahItem) return;

  formItem.post(props.tautan.tambahItem, {
    preserveScroll: true,
    onSuccess: () => formItem.reset(),
  });
}

async function hapusItem(url: string) {
  if (!await tanya('Hapus parameter ini?')) return;
  router.delete(url, { preserveScroll: true });
}

const isian = 'ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px] transition';
const kecil = 'ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition';
const label = 'block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5';
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-3xl mx-auto space-y-5">

    <form class="bg-white rounded-2xl shadow-card border border-stone-100 p-5" @submit.prevent="simpan">
      <div class="grid gap-3">
        <div>
          <label :class="label">Nama jenis <span class="text-red-500">*</span></label>
          <input v-model="form.nama" :class="isian" placeholder="mis. Inspeksi APAR">
          <p v-if="form.errors.nama" class="text-[11.5px] text-red-600 mt-1">{{ form.errors.nama }}</p>
        </div>

        <div>
          <label :class="label">Keterangan</label>
          <textarea v-model="form.keterangan" rows="2" :class="isian" style="resize:none"></textarea>
        </div>

        <label class="flex items-center gap-2 text-[12.5px] text-stone-600 cursor-pointer">
          <input v-model="form.is_active" type="checkbox" class="accent-[color:var(--eq-aksen,#F57C00)]">
          Aktif — dapat dipilih saat membuat inspeksi baru
        </label>
      </div>

      <div class="flex items-center gap-2 mt-4">
        <button type="submit" :disabled="form.processing"
                class="eq-btn-utama disabled:opacity-40" style="flex:none;padding:10px 20px">
          {{ form.processing ? 'Menyimpan…' : (tersimpan ? 'Simpan Perubahan' : 'Simpan & Lanjut') }}
        </button>
        <a :href="tautan.batal" class="px-4 py-2.5 text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink">
          Batal
        </a>
      </div>
    </form>

    <div v-if="tersimpan" class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      <div class="px-5 py-4 border-b border-stone-100">
        <h3 class="text-[13px] font-bold text-cam-ink">Parameter Pemeriksaan ({{ item.length }})</h3>
        <p class="text-[11.5px] text-stone-400 mt-0.5">Disalin ke tiap inspeksi yang memakai jenis ini.</p>
      </div>

      <div class="divide-y divide-stone-100">
        <div v-for="x in item" :key="x.id" class="px-5 py-3 flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="text-[12.5px] font-semibold text-cam-ink">{{ x.uraian }}</p>
            <p v-if="x.kelompok || x.acuan || x.risiko" class="text-[11px] text-stone-400 mt-0.5">
              <template v-if="x.kelompok">{{ x.kelompok }}</template>
              <template v-if="x.acuan"> · Acuan: {{ x.acuan }}</template>
              <template v-if="x.risiko"> · Risiko awal: {{ x.risiko }}</template>
            </p>
          </div>
          <button type="button" class="text-[12px] text-stone-300 hover:text-red-500 shrink-0"
                  @click="hapusItem(x.urlHapus)">✕</button>
        </div>

        <p v-if="!item.length" class="px-5 py-8 text-center text-[12.5px] text-stone-400">
          Belum ada parameter.
        </p>
      </div>

      <form class="p-4 bg-stone-50/60 border-t border-stone-100 grid gap-2 sm:grid-cols-[1fr_150px_150px_110px_auto] items-end"
            @submit.prevent="tambahItem">
        <div>
          <label :class="label">Uraian</label>
          <input v-model="formItem.uraian" :class="kecil" placeholder="Yang diperiksa">
        </div>
        <div>
          <label :class="label">Kelompok</label>
          <input v-model="formItem.kelompok" :class="kecil">
        </div>
        <div>
          <label :class="label">Acuan</label>
          <input v-model="formItem.acuan" :class="kecil">
        </div>
        <div>
          <label :class="label">Risiko awal</label>
          <select v-model="formItem.risiko_default" :class="kecil" aria-label="Risiko awal">
            <option value="">—</option>
            <option v-for="r in opsi.risiko" :key="r" :value="r">{{ r }}</option>
          </select>
        </div>
        <button type="submit" :disabled="formItem.processing || !formItem.uraian.trim()"
                class="rounded-xl border border-stone-200 bg-white px-3.5 py-2 text-[12px] font-bold
                       text-stone-600 hover:bg-stone-50 transition disabled:opacity-40">Tambah</button>
      </form>
    </div>

    <p v-else class="text-[12px] text-stone-400 text-center">
      Simpan jenisnya dulu, lalu parameternya dapat ditambahkan.
    </p>
  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>

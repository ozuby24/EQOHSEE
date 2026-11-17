<script setup lang="ts">
/**
 * Identitas audit dan profil perusahaan yang diaudit.
 *
 * Profilnya disimpan sebagai POTRET pada saat audit, bukan ditarik dari
 * data master perusahaan. Jumlah karyawan berubah sepanjang tahun, dan
 * lembar audit yang menampilkan angka hari ini pada audit tahun lalu
 * tidak lagi sama dengan lembar yang ditandatangani waktu itu.
 */
import { Head, useForm } from '@inertiajs/vue3';
import type { HalamanAuditLingkunganForm } from '../../types';

const props = defineProps<HalamanAuditLingkunganForm>();

const f = useForm({ ...props.awal });

const kirim = () =>
  props.sunting
    ? f.transform((d) => ({ ...d, _method: 'put' })).post(props.tautan.simpan)
    : f.post(props.tautan.simpan);

const isian = 'ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition';
const label = 'block text-[10.5px] font-bold uppercase tracking-wide text-stone-500 mb-1';
</script>

<template>
  <Head :title="judul" />

  <form class="max-w-4xl space-y-5" @submit.prevent="kirim">
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 space-y-4">
      <h3 class="text-[13px] font-bold text-cam-ink">Identitas Audit</h3>

      <div>
        <label :class="label" for="judul">Judul audit <span class="text-red-500">*</span></label>
        <input id="judul" v-model="f.judul" :class="isian"
               placeholder="mis. Audit Kinerja Pengelolaan dan Pemantauan Lingkungan 2026">
        <p v-if="f.errors.judul" class="text-[11px] text-red-600 mt-1">{{ f.errors.judul }}</p>
      </div>

      <div class="grid gap-3 sm:grid-cols-3">
        <div>
          <label :class="label" for="company_id">Perusahaan yang diaudit</label>
          <select id="company_id" v-model="f.company_id" :class="isian">
            <option value="">— pilih —</option>
            <option v-for="c in opsi.perusahaan" :key="c.id" :value="String(c.id)">{{ c.nama }}</option>
          </select>
        </div>
        <div>
          <label :class="label" for="tahun">Periode tahun <span class="text-red-500">*</span></label>
          <select id="tahun" v-model="f.tahun" :class="isian">
            <option v-for="t in opsi.tahun" :key="t" :value="String(t)">{{ t }}</option>
          </select>
        </div>
        <div>
          <label :class="label" for="tanggal">Tanggal audit</label>
          <input id="tanggal" v-model="f.tanggal" type="date" :class="isian">
        </div>
      </div>

      <div class="grid gap-3 sm:grid-cols-2">
        <div>
          <label :class="label" for="lokasi">Lokasi / site</label>
          <input id="lokasi" v-model="f.lokasi" :class="isian">
        </div>
        <div v-if="sunting">
          <label :class="label" for="status">Status</label>
          <select id="status" v-model="f.status" :class="isian">
            <option v-for="s in opsi.status" :key="s" :value="s">{{ s }}</option>
          </select>
        </div>
      </div>

      <div>
        <label :class="label" for="catatan">Catatan auditor</label>
        <textarea id="catatan" v-model="f.catatan" rows="3" :class="isian"></textarea>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink">Profil Perusahaan</h3>
      <p class="text-[11.5px] text-stone-500 mt-1 mb-4 leading-relaxed">
        Disimpan sebagai potret pada saat audit, bukan ditarik dari data master. Jumlah karyawan
        berubah sepanjang tahun, dan lembar audit tahun lalu harus tetap menampilkan angka yang
        ditandatangani waktu itu.
      </p>

      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <div v-for="p in opsi.profil" :key="p.kunci">
          <label :class="label" :for="`p-${p.kunci}`">{{ p.label }}</label>
          <input :id="`p-${p.kunci}`" v-model="f.profil[p.kunci]" :class="isian">
        </div>
      </div>
    </div>

    <div class="flex items-center gap-2">
      <button type="submit" class="eq-btn-utama" style="flex:none;padding:9px 20px" :disabled="f.processing">
        {{ f.processing ? 'Menyimpan…' : 'Simpan' }}
      </button>
      <a :href="tautan.batal" class="eq-btn-mini">Batal</a>
    </div>
  </form>
</template>

<script setup lang="ts">
/**
 * Formulir perusahaan.
 *
 * Kendali dokumen di sini bukan hiasan: divisi, departemen, prefiks, dan
 * tanggal-tanggalnya tercetak pada kop tiap berkas audit, jadi isian yang
 * kosong akan terlihat di lembar yang keluar dari printer.
 */
import { Head, useForm } from '@inertiajs/vue3';
import type { HalamanFormPerusahaan } from '../../../types';

const props = defineProps<HalamanFormPerusahaan>();

const form = useForm({ ...props.awal, logo: null as File | null });

function pilihLogo(e: Event) {
  const f = (e.target as HTMLInputElement).files;
  form.logo = f && f.length ? f[0] : null;
}

function simpan() {
  if (props.tersimpan) form.put(props.tautan.simpan);
  else form.post(props.tautan.simpan);
}

const isian = 'ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition';
const label = 'block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5';
const kepala = 'text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400';
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-2xl mx-auto">
    <form class="bg-white rounded-2xl shadow-card border border-stone-100 p-6 space-y-6"
          @submit.prevent="simpan">

      <div v-if="Object.keys(form.errors).length"
           class="rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]">
        <ul class="space-y-0.5">
          <li v-for="(pesan, k) in form.errors" :key="k">• {{ pesan }}</li>
        </ul>
      </div>

      <div class="space-y-4">
        <p :class="kepala">Identitas</p>
        <div class="grid sm:grid-cols-3 gap-4">
          <div class="sm:col-span-2">
            <label :class="label">Nama perusahaan</label>
            <input v-model="form.name" :class="isian">
          </div>
          <div>
            <label :class="label">Kode</label>
            <input v-model="form.code" placeholder="CDI" :class="isian">
          </div>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label :class="label">Pemilik izin / Induk</label>
            <select v-model="form.parent_id" :class="isian">
              <option value="">— berdiri sendiri (IUP/Owner) —</option>
              <option v-for="o in opsi.induk" :key="o.nilai" :value="o.nilai">{{ o.label }}</option>
            </select>
            <p class="text-[10.5px] text-stone-400 mt-1">
              Perusahaan jasa (IUJP) memakai logo pemiliknya bila logonya kosong.
            </p>
          </div>
          <div>
            <label :class="label">Prefiks nomor dokumen</label>
            <input v-model="form.doc_no_prefix" :placeholder="contoh.prefiks" :class="isian">
            <p class="text-[10.5px] text-stone-400 mt-1">
              Dipakai pada kop berkas audit, mis. CAM-OHSE-IV.067h. Kosong berarti diturunkan dari nama.
            </p>
          </div>
        </div>
      </div>

      <div class="space-y-4">
        <p :class="kepala">Kendali Dokumen</p>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label :class="label">Divisi</label>
            <input v-model="form.divisi" :placeholder="contoh.divisi" :class="isian">
          </div>
          <div>
            <label :class="label">Departemen</label>
            <input v-model="form.departemen" :placeholder="contoh.departemen" :class="isian">
          </div>
          <div>
            <label :class="label">Tanggal penerbitan</label>
            <input v-model="form.doc_terbit" type="date" :class="isian">
          </div>
          <div>
            <label :class="label">Tanggal persetujuan</label>
            <input v-model="form.doc_setuju" type="date" :class="isian">
          </div>
          <div>
            <label :class="label">Nomor revisi</label>
            <input v-model="form.doc_revisi" inputmode="numeric" :class="[isian, 'num']">
          </div>
        </div>
      </div>

      <div class="space-y-4">
        <p :class="kepala">Spesifikasi Pertambangan</p>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label :class="label">Jenis izin</label>
            <input v-model="form.izin_type" placeholder="IUP OP Batubara" :class="isian">
          </div>
          <div>
            <label :class="label">Komoditas</label>
            <input v-model="form.commodity" :class="isian">
          </div>
          <div>
            <label :class="label">Site / lokasi</label>
            <input v-model="form.location" :class="isian">
          </div>
          <div>
            <label :class="label">Kelas risiko</label>
            <select v-model="form.risk_class" :class="isian">
              <option v-for="r in opsi.risiko" :key="r" :value="r">{{ r }}</option>
            </select>
          </div>
          <div class="sm:col-span-2">
            <label :class="label">Alamat</label>
            <input v-model="form.address" :class="isian">
          </div>
        </div>
      </div>

      <div class="space-y-4">
        <p :class="kepala">Penanggung Jawab &amp; Tenaga Kerja</p>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label :class="label">KTT (Kepala Teknik Tambang)</label>
            <input v-model="form.ktt" :class="isian">
          </div>
          <div>
            <label :class="label">PJO</label>
            <input v-model="form.pjo" :class="isian">
          </div>
          <div>
            <label :class="label">Pekerja perusahaan</label>
            <input v-model="form.workers_employee" type="number" min="0" :class="isian">
          </div>
          <div>
            <label :class="label">Pekerja jasa pertambangan</label>
            <input v-model="form.workers_sub" type="number" min="0" :class="isian">
          </div>
        </div>

        <div class="grid sm:grid-cols-3 gap-4 pt-2 border-t border-stone-100">
          <div class="sm:col-span-3">
            <p :class="kepala">PIC Tindak Lanjut Temuan</p>
            <p class="text-[11px] text-stone-400 mt-1">
              Tujuan pengingat email &amp; WhatsApp untuk temuan yang belum ditutup.
            </p>
          </div>
          <div>
            <label :class="label">Nama PIC</label>
            <input v-model="form.pic_name" :class="isian">
          </div>
          <div>
            <label :class="label">Email PIC</label>
            <input v-model="form.pic_email" type="email" placeholder="pic@perusahaan.co.id" :class="isian">
          </div>
          <div>
            <label :class="label">WhatsApp PIC</label>
            <input v-model="form.pic_phone" placeholder="08123456789" :class="isian">
          </div>
        </div>

        <div>
          <label :class="label">Logo</label>
          <img v-if="logo" :src="logo" class="h-12 mb-2 rounded-lg" alt="Logo perusahaan">
          <input type="file" accept="image/*" class="text-[12px] text-stone-500" @change="pilihLogo">
          <p v-if="form.progress" class="text-[11px] text-stone-500 mt-1">
            Mengunggah {{ form.progress.percentage }}%
          </p>
        </div>
      </div>

      <div class="flex items-center gap-3 pt-1">
        <button type="submit" :disabled="form.processing"
                class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2.5 text-[12.5px]
                       font-bold hover:brightness-105 transition disabled:opacity-40">
          {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
        </button>
        <a :href="tautan.batal" class="text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink">Batal</a>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
/**
 * Formulir pengguna.
 *
 * Kata sandi dikirim kosong saat menyunting dan server membuangnya —
 * mengirimnya kosong sebagai nilai baru akan mengunci akun orang setiap
 * kali ada yang membetulkan nomor teleponnya.
 */
import { Head, useForm } from '@inertiajs/vue3';
import type { HalamanFormPengguna } from '../../../types';

const props = defineProps<HalamanFormPengguna>();

const form = useForm({ ...props.awal });

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
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label :class="label">Nama lengkap</label>
            <input v-model="form.name" :class="isian">
          </div>
          <div>
            <label :class="label">Email</label>
            <input v-model="form.email" type="email" :class="isian">
          </div>
        </div>
        <div>
          <label :class="label">
            Kata sandi <template v-if="tersimpan">(kosongkan bila tidak diubah)</template>
          </label>
          <input v-model="form.password" type="password" autocomplete="new-password" :class="isian">
          <p class="text-[11px] text-stone-400 mt-1">
            Minimal 8 karakter. Default bila kosong: <code class="text-cam-lime-deep">password</code>
          </p>
        </div>
      </div>

      <div class="space-y-4 pt-1">
        <p :class="kepala">Peran &amp; Akses</p>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label :class="label">Peran LMS</label>
            <select v-model="form.lms_role" :class="isian">
              <option value="">— tanpa peran —</option>
              <option v-for="o in opsi.lms" :key="o.nilai" :value="o.nilai">{{ o.label }}</option>
            </select>
          </div>
          <div>
            <label :class="label">Peran Audit</label>
            <select v-model="form.audit_role" :class="isian">
              <option value="">— tanpa peran —</option>
              <option v-for="o in opsi.audit" :key="o.nilai" :value="o.nilai">{{ o.label }}</option>
            </select>
          </div>
        </div>
        <div>
          <label :class="label">Perusahaan</label>
          <select v-model="form.company_id" :class="isian">
            <option value="">— tidak ada —</option>
            <option v-for="c in opsi.perusahaan" :key="c.nilai" :value="c.nilai">{{ c.label }}</option>
          </select>
        </div>

        <div class="flex flex-wrap gap-5 pt-1">
          <label class="flex items-center gap-2.5 text-[13px] text-stone-600 cursor-pointer">
            <input v-model="form.is_admin" type="checkbox"
                   class="rounded border-stone-300 accent-[color:var(--eq-aksen,#F57C00)]">
            <span class="font-semibold">Administrator</span>
            <span class="text-[11px] text-stone-400">(akses penuh semua modul)</span>
          </label>
          <label class="flex items-center gap-2.5 text-[13px] text-stone-600 cursor-pointer">
            <input v-model="form.active" type="checkbox"
                   class="rounded border-stone-300 accent-[color:var(--eq-aksen,#F57C00)]">
            <span class="font-semibold">Akun aktif</span>
          </label>
        </div>
      </div>

      <div class="space-y-4 pt-1">
        <p :class="kepala">Data Pegawai</p>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label :class="label">NRP / NIK</label>
            <input v-model="form.employee_id" :class="isian">
          </div>
          <div>
            <label :class="label">Jabatan</label>
            <select v-model="form.position" :class="isian">
              <option value="">— pilih jabatan —</option>
              <option v-for="j in opsi.jabatan" :key="j" :value="j">{{ j }}</option>
            </select>
            <p class="text-[10.5px] text-stone-400 mt-1">Menentukan golongan &amp; target KPI.</p>
          </div>
          <div>
            <label :class="label">Departemen</label>
            <input v-model="form.department" list="dlDeptAdmin" :class="isian">
            <datalist id="dlDeptAdmin">
              <option v-for="d in opsi.departemen" :key="d" :value="d" />
            </datalist>
          </div>
          <div>
            <label :class="label">Telepon</label>
            <input v-model="form.phone" :class="isian">
          </div>
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

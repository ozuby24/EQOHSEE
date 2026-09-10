<script setup lang="ts">
/**
 * Formulir data perusahaan jasa — dipakai menambah maupun mengubah.
 *
 * Satu berkas untuk keduanya, sebab medannya memang sama persis.
 * Dua berkas yang disalin akan berselisih pada perubahan pertama, dan
 * yang tertinggal tidak menimbulkan galat — hanya satu kolom yang
 * tidak dapat diisi lagi pada salah satu jalan masuknya.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';

const props = propHalaman();

const lama = props.pjp as Record<string, any> | null;

const f = useForm({
  nama_perusahaan:  lama?.nama_perusahaan  ?? '',
  nib:              lama?.nib              ?? '',
  penanggung_jawab: lama?.penanggung_jawab ?? '',
  alamat:           lama?.alamat           ?? '',
  status:           lama?.status           ?? 'aktif',
  catatan:          lama?.catatan          ?? '',
});

function simpan() {
  if (lama) f.put(`/pjp/${lama.id}`, { preserveScroll: true });
  else f.post('/pjp/baru', { preserveScroll: true });
}
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[860px] mx-auto space-y-5">
    <section>
      <h2 class="text-xl font-bold text-cam-ink">{{ props.judul }}</h2>
      <p class="text-[12.5px] text-stone-500 mt-0.5">
        Data dasar mitra. Prakualifikasi SMKP, dokumen berkala, dan evaluasi kinerjanya
        diisi dari halaman rincian sesudah tersimpan.
      </p>
    </section>

    <form class="grid gap-4" @submit.prevent="simpan">
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <header class="px-5 py-3.5 border-b border-stone-100">
          <h3 class="text-[13.5px] font-bold text-cam-ink">
            Identitas <span class="font-normal text-stone-400">| siapa mitranya</span>
          </h3>
        </header>

        <div class="px-5 py-4 grid gap-3 sm:grid-cols-2">
          <label class="grid gap-1 sm:col-span-2">
            <span class="text-[11.5px] font-semibold text-stone-600">Nama perusahaan</span>
            <input v-model="f.nama_perusahaan" required maxlength="200"
                   class="rounded-lg border-stone-200 text-[12.5px]">
            <small v-if="f.errors.nama_perusahaan" class="text-[11px] text-red-600">{{ f.errors.nama_perusahaan }}</small>
          </label>

          <label class="grid gap-1">
            <span class="text-[11.5px] font-semibold text-stone-600">NIB</span>
            <input v-model="f.nib" maxlength="60" class="rounded-lg border-stone-200 text-[12.5px] num">
            <small v-if="f.errors.nib" class="text-[11px] text-red-600">{{ f.errors.nib }}</small>
          </label>

          <label class="grid gap-1">
            <span class="text-[11.5px] font-semibold text-stone-600">Penanggung jawab</span>
            <input v-model="f.penanggung_jawab" maxlength="150" class="rounded-lg border-stone-200 text-[12.5px]">
            <small v-if="f.errors.penanggung_jawab" class="text-[11px] text-red-600">{{ f.errors.penanggung_jawab }}</small>
          </label>

          <label class="grid gap-1 sm:col-span-2">
            <span class="text-[11.5px] font-semibold text-stone-600">Alamat</span>
            <textarea v-model="f.alamat" rows="2" class="rounded-lg border-stone-200 text-[12.5px]" />
            <small v-if="f.errors.alamat" class="text-[11px] text-red-600">{{ f.errors.alamat }}</small>
          </label>
        </div>
      </section>

      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <header class="px-5 py-3.5 border-b border-stone-100">
          <h3 class="text-[13.5px] font-bold text-cam-ink">
            Pemantauan <span class="font-normal text-stone-400">| bagaimana mitra ini diperlakukan</span>
          </h3>
        </header>

        <div class="px-5 py-4 grid gap-3">
          <label class="grid gap-1 sm:max-w-xs">
            <span class="text-[11.5px] font-semibold text-stone-600">Status</span>
            <select v-model="f.status" class="rounded-lg border-stone-200 text-[12.5px]">
              <option v-for="(label, kode) in (props.STATUS ?? {})" :key="kode" :value="kode">{{ label }}</option>
            </select>
            <small class="text-[11px] text-stone-500">
              Mitra "Tidak Aktif" tidak lagi dihitung menunggak laporan bulanan.
            </small>
            <small v-if="f.errors.status" class="text-[11px] text-red-600">{{ f.errors.status }}</small>
          </label>

          <label class="grid gap-1">
            <span class="text-[11.5px] font-semibold text-stone-600">Catatan</span>
            <textarea v-model="f.catatan" rows="3" class="rounded-lg border-stone-200 text-[12.5px]" />
            <small v-if="f.errors.catatan" class="text-[11px] text-red-600">{{ f.errors.catatan }}</small>
          </label>
        </div>
      </section>

      <div class="flex flex-wrap items-center gap-2">
        <button type="submit" class="eq-btn-utama" :disabled="f.processing">
          {{ lama ? 'Simpan perubahan' : 'Simpan' }}
        </button>

        <Link :href="lama ? `/pjp/${lama.id}` : '/pjp/daftar'" class="eq-btn-lain">Batal</Link>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
/**
 * Satu dokumen terkendali: keterangan, penerbitan revisi, dan riwayatnya.
 *
 * Berkas revisi lama tidak pernah dibuang, jadi riwayat di bawah adalah
 * jejak yang dibaca auditor — bukan sekadar catatan perubahan.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import type { HalamanDetailDokumen } from '../../types';

const props = defineProps<HalamanDetailDokumen>();

const form = useForm({
  ringkasan_perubahan: '',
  tanggal: '',
  tanggal_tinjau: '',
  berkas: null as File | null,
});

function pilihBerkas(e: Event) {
  const f = (e.target as HTMLInputElement).files;
  form.berkas = f && f.length ? f[0] : null;
}

function terbitkan() {
  form.post(props.tautan.revisi, { onSuccess: () => form.reset() });
}

function hapus() {
  if (!confirm(`Hapus dokumen ${props.d.kode} beserta riwayatnya?`)) return;
  router.delete(props.tautan.hapus);
}

const keterangan = [
  ['Departemen', props.d.departemen],
  ['Perusahaan', props.d.perusahaan ?? 'Semua'],
  ['Klasifikasi', props.d.klasifikasi],
  ['Disetujui', props.d.disetujui],
  ['Terbit', props.d.tanggalTerbit],
  ['Berlaku', props.d.tanggalBerlaku],
  ['Tinjau', props.d.tanggalTinjau],
  ['Acuan', props.d.acuan],
] as Array<[string, string | null]>;

const isian = 'ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] transition';
const label = 'block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5';
const chip = 'text-[9.5px] font-bold uppercase tracking-wide px-2 py-0.5 rounded';
</script>

<template>
  <Head :title="d.kode" />

  <div class="max-w-4xl mx-auto space-y-5">

    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-6">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <span class="num text-[12px] font-bold text-stone-500">{{ d.kode }}</span>
            <span :class="chip" class="text-white" :style="{ background: d.warnaStatus }">{{ d.status }}</span>
            <span :class="chip" class="bg-stone-100 text-stone-500">{{ d.jenis }}</span>
            <span :class="chip" class="bg-cam-lime-soft text-cam-lime-deep">{{ d.labelRevisi }}</span>
          </div>
          <h2 class="text-[17px] font-bold text-cam-ink mt-2 leading-snug">{{ d.judul }}</h2>
          <p v-if="d.ringkasan" class="text-[12.5px] text-stone-500 mt-2 leading-relaxed">{{ d.ringkasan }}</p>
        </div>
        <div class="flex flex-wrap gap-2 shrink-0">
          <a v-if="tautan.unduh" :href="tautan.unduh"
             class="rounded-xl border border-stone-200 px-4 py-2.5 text-[12.5px] font-bold
                    text-stone-600 hover:bg-stone-50 transition">Unduh</a>
          <a :href="tautan.ubah"
             class="rounded-xl border border-stone-200 px-4 py-2.5 text-[12.5px] font-bold
                    text-stone-600 hover:bg-stone-50 transition">Ubah</a>
        </div>
      </div>

      <div v-if="d.perluTinjau" class="mt-4 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3
                                       text-[12.5px] text-amber-800">
        Dokumen ini sudah melewati jatuh tempo peninjauan ({{ d.tanggalTinjau }}).
      </div>
      <div v-else-if="d.segeraTinjau" class="mt-4 rounded-xl bg-amber-50/70 border border-amber-100
                                             px-4 py-3 text-[12.5px] text-amber-700">
        Jatuh tempo peninjauan {{ d.tanggalTinjau }}.
      </div>

      <div class="grid gap-4 grid-cols-2 sm:grid-cols-4 mt-5 pt-5 border-t border-stone-100 text-[12px]">
        <div v-for="[l, v] in keterangan" :key="l">
          <div class="text-[10px] font-bold uppercase tracking-wide text-stone-400">{{ l }}</div>
          <div class="text-cam-ink mt-1 break-words">{{ v || '—' }}</div>
        </div>
      </div>
    </section>

    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-6">
      <h3 class="text-[14px] font-bold text-cam-ink mb-1">Terbitkan Revisi Baru</h3>
      <p class="text-[11.5px] text-stone-400 mb-4">
        Nomor revisi naik menjadi {{ d.revisiBerikut }} dan status menjadi berlaku.
        Berkas revisi lama tetap tersimpan di riwayat.
      </p>

      <form class="space-y-3" @submit.prevent="terbitkan">
        <div>
          <label :class="label">Ringkasan perubahan <span class="text-red-500">*</span></label>
          <textarea v-model="form.ringkasan_perubahan" rows="2" :class="isian"></textarea>
          <p v-if="form.errors.ringkasan_perubahan" class="text-[11.5px] text-red-600 mt-1">
            {{ form.errors.ringkasan_perubahan }}
          </p>
        </div>
        <div class="grid gap-3 sm:grid-cols-3">
          <div>
            <label :class="label">Tanggal terbit</label>
            <input v-model="form.tanggal" type="date" :class="isian" aria-label="Tanggal">
          </div>
          <div>
            <label :class="label">Jatuh tempo tinjau</label>
            <input v-model="form.tanggal_tinjau" type="date" :class="isian" aria-label="Jatuh tempo tinjau">
          </div>
          <div>
            <label :class="label">Berkas baru</label>
            <input type="file" :class="isian" @change="pilihBerkas"
                   class="file:mr-2 file:rounded file:border-0 file:bg-cam-lime-soft file:px-2
                          file:py-1 file:text-[11px] file:font-bold file:text-cam-lime-deep">
          </div>
        </div>
        <button type="submit" :disabled="form.processing || !form.ringkasan_perubahan.trim()"
                class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5 text-[12.5px]
                       font-bold hover:brightness-105 transition disabled:opacity-40">
          {{ form.processing ? 'Menerbitkan…' : 'Terbitkan Revisi' }}
        </button>
      </form>
    </section>

    <section class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      <div class="px-5 py-4 border-b border-stone-100">
        <h3 class="text-[14px] font-bold text-cam-ink">Riwayat Revisi</h3>
      </div>

      <div v-if="riwayat.length" class="divide-y divide-stone-100">
        <div v-for="r in riwayat" :key="r.id" class="px-5 py-4">
          <div class="flex flex-wrap items-center gap-2">
            <span class="text-[10px] font-bold uppercase tracking-wide bg-stone-100
                         text-stone-600 px-2 py-0.5 rounded">{{ r.label }}</span>
            <span v-if="r.tanggal" class="text-[11px] text-stone-400">{{ r.tanggal }}</span>
            <span v-if="r.oleh" class="text-[11px] text-stone-400">· {{ r.oleh }}</span>
          </div>
          <p v-if="r.ringkasan" class="text-[12.5px] text-cam-ink mt-1.5 leading-relaxed">{{ r.ringkasan }}</p>
        </div>
      </div>
      <p v-else class="px-5 py-6 text-[12.5px] text-stone-400">Belum ada riwayat revisi.</p>
    </section>

    <div class="flex flex-wrap gap-2">
      <Link :href="tautan.daftar" class="text-[12px] font-bold text-cam-lime-deep hover:underline">
        ← Register dokumen
      </Link>
      <button v-if="bolehHapus" type="button" @click="hapus"
              class="ml-auto text-[12px] font-semibold text-stone-300 hover:text-red-500 transition">
        Hapus dokumen
      </button>
    </div>

    <section v-if="klausul.length" class="kartu-lux rounded-2xl p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-3">Pemenuhan Klausul ISO</h3>
      <div class="space-y-3">
        <div v-for="s in klausul" :key="s.kode">
          <div class="flex items-center gap-2 mb-1.5">
            <span class="shrink-0 w-2.5 h-2.5 rounded-full" :style="{ background: s.warna }"></span>
            <a :href="s.url" class="text-[12px] font-bold text-cam-ink hover:underline">{{ s.nama }}</a>
          </div>
          <div class="flex flex-wrap gap-1.5">
            <span v-for="no in s.butir" :key="no"
                  class="num text-[10.5px] font-semibold rounded-lg px-2 py-1
                         bg-cam-lime-soft text-cam-lime-deep">{{ no }}</span>
          </div>
        </div>
      </div>
    </section>

  </div>
</template>

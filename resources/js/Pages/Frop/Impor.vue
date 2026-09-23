<script setup lang="ts">
/**
 * Impor berkas kerja observasi (.xlsx).
 *
 * Yang dibaca hanya kolom isian Akumulatif Observasi dan Coaching Log;
 * kolom berumus dihitung ulang. Baris yang sudah ada dilewati, jadi
 * berkas yang sama boleh diunggah lagi sesudah ditambah baris baru.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import type { TautanFrop } from './tipe';
import './frop.css';

const props = defineProps<{
  judul: string;
  hasil: { observasi: number; coaching: number; dilewati: number; ditolak: string[]; sheet: string[] } | null;
  tautan: TautanFrop & { kirim: string };
}>();

const f = useForm<{ berkas: File | null }>({ berkas: null });
const pilih = (e: Event) => { f.berkas = (e.target as HTMLInputElement).files?.[0] ?? null; };
const kirim = () => f.post(props.tautan.kirim, { forceFormData: true, preserveScroll: true, onSuccess: () => f.reset() });
</script>

<template>
  <Head :title="judul" />

  <div class="fr-kisi fr-kisi-3-1">
    <div class="space-y-4">
      <section class="fr-kartu">
        <div class="fr-kartu-kepala"><h3>Unggah Berkas</h3></div>
        <form class="fr-isi space-y-3" @submit.prevent="kirim">
          <label class="block rounded-xl border-2 border-dashed border-stone-200 p-6 text-center cursor-pointer hover:border-orange-300 relative">
            <input type="file" accept=".xlsx" class="sr-only" @change="pilih">
            <b class="block text-[13px]">{{ f.berkas ? f.berkas.name : 'Pilih berkas .xlsx' }}</b>
            <span class="fr-ket">Berkas kerja "Observasi PTY-CT Loader" — maks. 20 MB</span>
          </label>
          <p v-if="f.errors.berkas" class="fr-galat">{{ f.errors.berkas }}</p>
          <button type="submit" class="eq-btn-utama" style="flex:none" :disabled="!f.berkas || f.processing">
            {{ f.processing ? 'Membaca berkas…' : 'Impor' }}
          </button>
        </form>
      </section>

      <section v-if="hasil" class="fr-kartu">
        <div class="fr-kartu-kepala"><h3>Hasil Impor</h3><span class="fr-ket">{{ hasil.sheet.join(' · ') }}</span></div>
        <div class="fr-isi space-y-3">
          <div class="grid grid-cols-3 gap-2 text-center">
            <div class="rounded-xl bg-green-50 p-3"><div class="num text-[22px] font-extrabold fr-teks-baik">{{ hasil.observasi }}</div><div class="fr-ket">sesi observasi baru</div></div>
            <div class="rounded-xl bg-green-50 p-3"><div class="num text-[22px] font-extrabold fr-teks-baik">{{ hasil.coaching }}</div><div class="fr-ket">coaching baru</div></div>
            <div class="rounded-xl bg-stone-50 p-3"><div class="num text-[22px] font-extrabold">{{ hasil.dilewati }}</div><div class="fr-ket">sudah ada, dilewati</div></div>
          </div>
          <div v-if="hasil.ditolak.length" class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-[12px] text-amber-900">
            <b>{{ hasil.ditolak.length }} baris tidak diimpor:</b>
            <ul class="list-disc pl-5 mt-1"><li v-for="(d, i) in hasil.ditolak" :key="i">{{ d }}</li></ul>
          </div>
          <Link :href="tautan.index" class="eq-btn-mini">Lihat sesi observasi →</Link>
        </div>
      </section>
    </div>

    <aside class="fr-kartu">
      <div class="fr-kartu-kepala"><h3>Yang Dibaca</h3></div>
      <div class="fr-isi text-[12px] text-stone-600 space-y-2 leading-relaxed">
        <p><b>Akumulatif Observasi</b> — kolom isian: tanggal, shift, unit, operator, GL, observer, kondisi, jenis
          material, komponen CT, loading time, N passing, bucket heap, jam, target &amp; aktual PTY, temuan, CA, status CA, cuaca.</p>
        <p><b>Coaching Log</b> — seluruh barisnya, tertaut ke sesi operator yang sama pada tanggal yang sama.</p>
        <p>Kolom berumus (Aktual CT, Status, Ringkasan, Rekomendasi) <b>tidak</b> diambil — dihitung ulang dengan aturan yang
          sudah dikoreksi, lihat Panduan &amp; Acuan.</p>
        <p>Loading time "1:56" yang tersimpan Excel sebagai 1 jam 56 menit dibaca kembali sebagai 1 menit 56 detik.</p>
        <p>Kolom dicari menurut judulnya, jadi kolom yang disisipkan atau digeser tetap terbaca benar.</p>
      </div>
    </aside>
  </div>
</template>

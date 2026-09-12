<script setup lang="ts">
/**
 * Upah dasar perhitungan lembur.
 *
 * DASARNYA 100% ATAU 75% MENURUT SUSUNAN UPAHNYA, bukan menurut ada
 * tidaknya tunjangan tidak tetap. Pasal 32 ayat (3) berbunyi: bila
 * upah terdiri dari pokok, tunjangan tetap, DAN tunjangan tidak tetap,
 * sedangkan pokok ditambah tunjangan tetap kurang dari 75% keseluruhan
 * upah, barulah dasarnya 75% dari keseluruhan. Disederhanakan menjadi
 * "ada tunjangan tidak tetap → 75%", seorang yang pokoknya sudah 90%
 * dari upahnya kehilangan seperenam upah lemburnya tiap bulan.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { propHalaman } from '../../../halaman';

const props = propHalaman();

const baris = computed<any[]>(() => (props.baris ?? []) as any[]);
const acuan = computed<any>(() => props.ACUAN ?? {});

const form = useForm({
  pekerja_id: '', berlaku_mulai: '', pokok: '',
  tunjangan_tetap: '', tunjangan_tidak_tetap: '', catatan: '',
});

function simpan() {
  form.post('/hris/lembur/upah', {
    preserveScroll: true,
    onSuccess: () => form.reset('pokok', 'tunjangan_tetap', 'tunjangan_tidak_tetap', 'catatan'),
  });
}

function rupiah(n: number | null) {
  if (n === null || n === undefined) return '—';
  return 'Rp ' + Math.round(n).toLocaleString('id-ID');
}

const belumAda = computed(() => baris.value.filter((b) => !b.berlaku && !b.kecuali).length);
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1200px] mx-auto space-y-5">
    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-cam-ink">{{ props.judul }}</h2>
        <p class="text-[12.5px] text-stone-500 mt-0.5">{{ props.subjudul }}</p>
      </div>

      <Link href="/hris/lembur" class="eq-btn-lain">Perintah lembur</Link>
    </section>

    <p v-if="belumAda" class="rounded-2xl bg-amber-50 border border-amber-200 px-4 py-3 text-[12.5px] text-amber-800">
      <span class="num font-bold">{{ belumAda }}</span> pekerja belum punya upah dasar.
      Lembur mereka tetap tercatat jamnya tetapi bernilai nol sampai upahnya diisi —
      dilewati diam-diam, lembur yang benar-benar dikerjakan hilang tanpa jejak.
    </p>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[11.5px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
              <th class="px-5 py-2 font-semibold">Pekerja</th>
              <th class="px-5 py-2 font-semibold">Berlaku</th>
              <th class="px-5 py-2 font-semibold text-right">Pokok</th>
              <th class="px-5 py-2 font-semibold text-right">Tunj. tetap</th>
              <th class="px-5 py-2 font-semibold text-right">Tunj. tidak tetap</th>
              <th class="px-5 py-2 font-semibold text-right">Dasar</th>
              <th class="px-5 py-2 font-semibold text-right">Upah sejam</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="b in baris" :key="b.id" class="border-b border-stone-100">
              <td class="px-5 py-2.5">
                <div class="font-semibold text-cam-ink">{{ b.nama }}</div>
                <div class="text-[10.5px] text-stone-400">
                  {{ b.nik }}<span v-if="b.jabatan"> · {{ b.jabatan }}</span>
                </div>
                <div v-if="b.kecuali" class="text-[10.5px] text-violet-700">
                  Dikecualikan dari upah lembur — pasal 27 ayat (4)
                </div>
              </td>
              <td class="px-5 py-2.5 num" :class="b.berlaku ? 'text-stone-600' : 'text-amber-700'">
                {{ b.berlaku || 'belum diisi' }}
              </td>
              <td class="px-5 py-2.5 num text-right">{{ rupiah(b.pokok) }}</td>
              <td class="px-5 py-2.5 num text-right" :class="b.tetap ? '' : 'text-stone-300'">{{ rupiah(b.tetap) }}</td>
              <td class="px-5 py-2.5 num text-right" :class="b.tidakTetap ? '' : 'text-stone-300'">{{ rupiah(b.tidakTetap) }}</td>
              <td class="px-5 py-2.5 num text-right font-semibold text-cam-ink">
                {{ rupiah(b.dasar) }}
                <span v-if="b.persen" class="text-[10.5px]"
                      :class="b.persen === 75 ? 'text-amber-700' : 'text-stone-400'">{{ b.persen }}%</span>
              </td>
              <td class="px-5 py-2.5 num text-right">{{ rupiah(b.sejam) }}</td>
            </tr>

            <tr v-if="!baris.length">
              <td colspan="7" class="px-5 py-10 text-center text-stone-400">Belum ada pekerja aktif.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="text-[13.5px] font-bold text-cam-ink mb-1">Catat upah dasar</h3>
      <p class="text-[11.5px] text-stone-500 mb-3">
        Bertanggal berlaku: baris lama tidak ditimpa. Upah naik, dan lembur bulan lalu harus
        tetap terhitung dengan upah yang berlaku saat itu.
      </p>

      <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" @submit.prevent="simpan">
        <label class="block">
          <span class="text-[11px] text-stone-500">Pekerja</span>
          <select v-model="form.pekerja_id" required class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option value="">—</option>
            <option v-for="b in baris" :key="b.id" :value="b.id">{{ b.nama }} · {{ b.nik }}</option>
          </select>
        </label>

        <label class="block">
          <span class="text-[11px] text-stone-500">Berlaku mulai</span>
          <input v-model="form.berlaku_mulai" type="date" required
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="text-[11px] text-stone-500">Gaji pokok</span>
          <input v-model="form.pokok" type="number" min="0" step="1000" required
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
          <span v-if="form.errors.pokok" class="text-[11px] text-red-600">{{ form.errors.pokok }}</span>
        </label>

        <label class="block">
          <span class="text-[11px] text-stone-500">Tunjangan tetap</span>
          <input v-model="form.tunjangan_tetap" type="number" min="0" step="1000"
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="text-[11px] text-stone-500">Tunjangan tidak tetap</span>
          <input v-model="form.tunjangan_tidak_tetap" type="number" min="0" step="1000"
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="text-[11px] text-stone-500">Catatan</span>
          <input v-model="form.catatan" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <div class="sm:col-span-2 lg:col-span-3">
          <button type="submit" class="eq-btn-utama" :disabled="form.processing">Simpan</button>
        </div>
      </form>

      <p class="mt-3 text-[11.5px] text-stone-500">
        Dasarnya 100% dari pokok + tunjangan tetap. Bila ada tunjangan tidak tetap DAN
        pokok + tunjangan tetap kurang dari {{ acuan.ambang }}% keseluruhan upah, dasarnya menjadi
        {{ acuan.ambang }}% dari keseluruhan — PP 35/2021 pasal 32 ayat (2) dan (3).
      </p>
    </section>
  </div>
</template>

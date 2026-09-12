<script setup lang="ts">
/**
 * Saldo cuti per orang.
 *
 * MASA KERJA IKUT DITULIS, dan itu bukan hiasan: hak cuti tahunan baru
 * timbul sesudah dua belas bulan bekerja terus-menerus (UU 13/2003
 * pasal 79). Tanpa angka itu, "hak 0" pada orang yang baru masuk
 * terbaca seperti kesalahan data — dan yang membacanya memperbaiki
 * yang tidak rusak.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { propHalaman } from '../../../halaman';
import KopHalaman from '../../../Components/KopHalaman.vue';

const props = propHalaman();

const baris = computed<any[]>(() => (props.baris ?? []) as any[]);
const jenis = computed<any[]>(() => (props.jenis ?? []) as any[]);

const tahun = ref(String(props.tahun ?? ''));

function muat() {
  router.get('/hris/cuti/saldo', { tahun: tahun.value || undefined },
    { preserveState: true, preserveScroll: true, replace: true });
}

const jumlah = computed(() => {
  const n = { hak: 0, carry: 0, terpakai: 0, tertunda: 0, sisa: 0 };

  for (const b of baris.value) {
    for (const s of b.saldo ?? []) {
      n.hak += s.hak; n.carry += s.carry_over;
      n.terpakai += s.terpakai; n.tertunda += s.tertunda; n.sisa += s.sisa;
    }
  }

  return n;
});

function masaKerja(bulan: number | null) {
  if (bulan === null || bulan === undefined) return 'belum diisi';
  if (bulan < 12) return `${bulan} bln`;
  return `${Math.floor(bulan / 12)} th ${bulan % 12} bln`;
}
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1200px] mx-auto space-y-5">
    <KopHalaman :judul="props.judul as string" :subjudul="props.subjudul as string"
                tagline="Every Day Accounted"
                :remah="[['HRIS', '/hris'], ['Cuti & Izin', null], ['Saldo Cuti', null]]" ringkas />

    <section class="-mt-2 flex flex-wrap items-end justify-end gap-3">

      <div class="flex flex-wrap items-end gap-2">
        <label class="block">
          <span class="block text-[11px] text-stone-500">Tahun</span>
          <input v-model="tahun" type="number" min="2020" max="2100"
                 class="mt-1 w-24 rounded-lg border-stone-200 text-[12px]" @change="muat">
        </label>

        <Link href="/hris/cuti" class="eq-btn-lain">Pengajuan</Link>
      </div>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[11.5px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
              <th class="px-5 py-2 font-semibold">Pekerja</th>
              <th class="px-5 py-2 font-semibold">Masa kerja</th>
              <th class="px-5 py-2 font-semibold text-right">Hak</th>
              <th class="px-5 py-2 font-semibold text-right">Bawaan</th>
              <th class="px-5 py-2 font-semibold text-right">Terpakai</th>
              <th class="px-5 py-2 font-semibold text-right">Antre</th>
              <th class="px-5 py-2 font-semibold text-right">Sisa</th>
              <th class="px-5 py-2 font-semibold text-right">Dapat diajukan</th>
            </tr>
          </thead>

          <tbody>
            <template v-for="b in baris" :key="b.id">
              <tr v-for="(s, i) in (b.saldo ?? [])" :key="b.id + '-' + i" class="border-b border-stone-100">
                <td class="px-5 py-2.5">
                  <div class="font-semibold text-cam-ink">{{ b.nama }}</div>
                  <div class="text-[10.5px] text-stone-400">
                    {{ b.nik }}<span v-if="b.jabatan"> · {{ b.jabatan }}</span>
                    <span v-if="(b.saldo ?? []).length > 1"> · {{ s.jenis }}</span>
                  </div>
                </td>
                <td class="px-5 py-2.5 num" :class="b.masuk ? 'text-stone-600' : 'text-amber-700'">
                  {{ masaKerja(b.bulan) }}
                </td>
                <td class="px-5 py-2.5 num text-right font-semibold text-cam-ink">{{ s.hak }}</td>
                <td class="px-5 py-2.5 num text-right" :class="s.carry_over ? 'text-stone-600' : 'text-stone-300'">
                  {{ s.carry_over }}
                </td>
                <td class="px-5 py-2.5 num text-right" :class="s.terpakai ? 'text-stone-600' : 'text-stone-300'">
                  {{ s.terpakai }}
                </td>
                <td class="px-5 py-2.5 num text-right" :class="s.tertunda ? 'text-amber-700' : 'text-stone-300'">
                  {{ s.tertunda }}
                </td>
                <td class="px-5 py-2.5 num text-right font-semibold"
                    :class="s.sisa > 0 ? 'text-emerald-700' : 'text-stone-400'">{{ s.sisa }}</td>
                <td class="px-5 py-2.5 num text-right font-bold"
                    :class="s.tersedia > 0 ? 'text-cam-ink' : 'text-red-600'">{{ s.tersedia }}</td>
              </tr>
            </template>

            <tr v-if="!baris.length">
              <td colspan="8" class="px-5 py-10 text-center text-stone-400">
                Belum ada pekerja aktif.
              </td>
            </tr>
          </tbody>

          <tfoot v-if="baris.length">
            <tr class="border-t-2 border-stone-200 bg-stone-50/70 font-bold text-cam-ink">
              <td class="px-5 py-2.5" colspan="2">Jumlah {{ baris.length }} orang</td>
              <td class="px-5 py-2.5 num text-right">{{ jumlah.hak }}</td>
              <td class="px-5 py-2.5 num text-right">{{ jumlah.carry }}</td>
              <td class="px-5 py-2.5 num text-right">{{ jumlah.terpakai }}</td>
              <td class="px-5 py-2.5 num text-right">{{ jumlah.tertunda }}</td>
              <td class="px-5 py-2.5 num text-right">{{ jumlah.sisa }}</td>
              <td class="px-5 py-2.5"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="text-[13.5px] font-bold text-cam-ink mb-1">Jenis yang berakru</h3>
      <p class="text-[11.5px] text-stone-500 mb-3">
        Hanya jenis yang berakru punya saldo. Izin khusus pasal 93 ayat (4) timbul dari
        kejadiannya dan tidak memotong cuti tahunan — dipotong, seorang yang ayahnya meninggal
        kehilangan dua hari cuti tahunannya.
      </p>

      <table class="min-w-full text-left text-[11.5px]">
        <thead>
          <tr class="text-stone-400 border-b border-stone-200">
            <th class="py-2 pr-4 font-semibold">Kode</th>
            <th class="py-2 pr-4 font-semibold">Jenis</th>
            <th class="py-2 pr-4 font-semibold text-right">Hari setahun</th>
            <th class="py-2 pr-4 font-semibold text-right">Bawaan maks</th>
            <th class="py-2 font-semibold">Dasar</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="j in jenis" :key="j.kode" class="border-b border-stone-100">
            <td class="py-2 pr-4 num font-bold text-cam-ink">{{ j.kode }}</td>
            <td class="py-2 pr-4 text-stone-600">{{ j.nama }}</td>
            <td class="py-2 pr-4 num text-right">{{ j.hari ?? '—' }}</td>
            <td class="py-2 pr-4 num text-right">{{ j.carry }}</td>
            <td class="py-2 text-stone-500">{{ j.dasar }}</td>
          </tr>
        </tbody>
      </table>

      <p class="mt-3 text-[11.5px] text-stone-500">
        Hak timbul sesudah {{ props.BULAN_HAK }} bulan bekerja terus-menerus — sebelum itu
        haknya nol, bukan sebagian.
      </p>
    </section>
  </div>
</template>

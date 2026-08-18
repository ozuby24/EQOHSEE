<script setup lang="ts">
/**
 * PTPKKP — Rekapitulasi.
 *
 * Halaman kedua yang dipindah ke Vue, sengaja dipilih karena terhubung
 * langsung dengan Formulir Nilai (Penilaian.vue): alur wajarnya isi nilai
 * lalu cek rekapnya. Baru dengan dua halaman Inertia yang saling terkait
 * begini, perpindahan ANTARA keduanya lewat bilah samping bisa instan —
 * satu halaman saja tidak cukup untuk membuktikan itu.
 *
 * Memilih perusahaan memakai reload sebagian (`only`), bukan navigasi
 * penuh seperti versi Blade (`onchange="this.form.submit()"`). Tabel
 * utama di atas tidak bergantung pada perusahaan yang dipilih, jadi tidak
 * ada alasan ia ikut memuat ulang setiap kali orang sekadar
 * membandingkan satu perusahaan ke perusahaan lain.
 */
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PickerTpkkp from '../../Components/PickerTpkkp.vue';
import type { HalamanRekap } from '../../types';

const props = defineProps<HalamanRekap>();

const memuat = ref(false);

function pilihPerusahaan(nama: string) {
  memuat.value = true;

  router.get('/tpkkp/rekap', { tahun: props.tahun, entitas: nama || undefined }, {
    only: ['rincian', 'lemah', 'entitasAktif'],
    preserveScroll: true,
    preserveState: true,
    onFinish: () => { memuat.value = false; },
  });
}

function warnaKategori(label: string | null): string {
  if (!label) return '#a8a29e';
  return props.ambang.find((a) => a.label === label)?.warna ?? '#a8a29e';
}

const fmt = (n: number | null | undefined, d = 2) =>
  n === null || n === undefined ? '—' : n.toLocaleString('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d });
</script>

<template>
  <Head title="PTPKKP — Rekapitulasi" />

  <div class="max-w-6xl mx-auto space-y-5">

    <PickerTpkkp v-bind="picker" />

    <!-- Tautan balik ke Formulir Nilai lewat <Link>: perpindahan antara
         kedua halaman Inertia ini yang seharusnya terasa instan. -->
    <div class="flex items-center gap-2 text-[12px] text-stone-500">
      <Link href="/tpkkp/penilaian" class="font-semibold hover:underline"
            style="color:var(--eq-aksen,#F57C00)">&larr; Kembali ke Formulir Nilai</Link>
    </div>

    <!-- ══════════ TABEL UTAMA ══════════ -->
    <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-stone-100 flex flex-wrap items-center justify-between gap-3">
        <h3 class="text-[13px] font-bold text-cam-ink">Rekapitulasi Nilai per Parameter</h3>
        <span class="num text-[12px] font-bold text-cam-ink">
          Total {{ fmt(hasil.skor, 3) }} / target {{ hasil.target.toFixed(2) }}
        </span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-[12px] min-w-[720px]">
          <thead class="text-[10px] uppercase tracking-wider text-stone-400 bg-stone-50">
            <tr>
              <th class="text-left px-5 py-2.5 font-bold">Parameter</th>
              <th class="text-right px-3 py-2.5 font-bold">Nilai</th>
              <th class="text-right px-3 py-2.5 font-bold">Maks</th>
              <th class="text-right px-3 py-2.5 font-bold">Rasio</th>
              <th class="text-right px-3 py-2.5 font-bold">Bobot</th>
              <th class="text-right px-3 py-2.5 font-bold">Capaian</th>
              <th class="text-right px-3 py-2.5 font-bold">Target</th>
              <th class="text-right px-5 py-2.5 font-bold">Kategori</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="ind in hasil.indikator" :key="ind.kode">
              <tr class="bg-stone-50/70 border-y border-stone-100">
                <td class="px-5 py-2 font-bold text-cam-ink">{{ ind.kode }}. {{ ind.nama }}</td>
                <td colspan="3"></td>
                <td class="px-3 py-2 text-right num font-bold">{{ ind.bobot.toFixed(2) }}</td>
                <td class="px-3 py-2 text-right num font-bold">{{ fmt(ind.nilai, 3) }}</td>
                <td class="px-3 py-2 text-right num">{{ ind.target.toFixed(2) }}</td>
                <td class="px-5 py-2 text-right">
                  <span class="inline-block text-[10px] font-bold px-2 py-0.5 rounded-full text-white whitespace-nowrap"
                        :style="{ background: warnaKategori(ind.kategori) }">
                    {{ ind.kategori ?? 'Belum dinilai' }}
                  </span>
                </td>
              </tr>
              <tr v-for="p in ind.parameter" :key="p.kode" class="border-b border-stone-50">
                <td class="px-5 py-2 pl-9"><b class="num">{{ p.kode }}</b> · {{ p.nama }}</td>
                <td class="px-3 py-2 text-right num">{{ fmt(p.nilai, 1) }}</td>
                <td class="px-3 py-2 text-right num text-stone-400">{{ p.maks }}</td>
                <td class="px-3 py-2 text-right num">{{ fmt(p.rasio, 3) }}</td>
                <td class="px-3 py-2 text-right num text-stone-400">{{ p.bobot.toFixed(2) }}</td>
                <td class="px-3 py-2 text-right num font-semibold">{{ fmt(p.skor, 3) }}</td>
                <td class="px-3 py-2 text-right num text-stone-400">{{ p.target === null ? '—' : p.target.toFixed(2) }}</td>
                <td class="px-5 py-2 text-right">
                  <span class="inline-block text-[10px] font-bold px-2 py-0.5 rounded-full text-white whitespace-nowrap"
                        :style="{ background: warnaKategori(p.kategori) }">
                    {{ p.kategori ?? 'Belum dinilai' }}
                  </span>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ══════════ REKAP PER PERUSAHAAN ══════════ -->
    <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-stone-100 flex flex-wrap items-center justify-between gap-3">
        <h3 class="text-[13px] font-bold text-cam-ink">Rekap per Perusahaan</h3>
        <select :value="entitasAktif ?? ''" :disabled="memuat"
                @change="pilihPerusahaan(($event.target as HTMLSelectElement).value)"
                class="ring-focus rounded-xl border border-stone-200 bg-white px-3.5 py-2 text-[12.5px] font-semibold" aria-label="Perusahaan">
          <option value="">— pilih perusahaan —</option>
          <option v-for="c in perusahaan" :key="c" :value="c">{{ c }}</option>
        </select>
      </div>

      <div :class="memuat ? 'opacity-50 transition-opacity' : 'transition-opacity'">
        <p v-if="!entitasAktif" class="px-5 py-8 text-[12.5px] text-stone-400">
          Pilih perusahaan untuk melihat rincian. Nilai dihitung dengan mengisolasi kolom
          entitas perusahaan itu pada metode {{ metodePerusahaan.join(', ') }} — skala 1–5,
          bukan capaian berbobot.
        </p>

        <template v-else-if="rincian">
          <table class="w-full text-[12px]">
            <tbody>
              <template v-for="ind in rincian" :key="ind.kode">
                <tr class="bg-stone-50/70 border-y border-stone-100">
                  <td class="px-5 py-2 font-bold text-cam-ink">{{ ind.kode }}. {{ ind.nama }}</td>
                  <td class="px-5 py-2 text-right num font-bold">{{ fmt(ind.rerata) }}</td>
                </tr>
                <tr v-for="p in ind.parameter" :key="p.kode" class="border-b border-stone-50">
                  <td class="px-5 py-2 pl-9"><b class="num">{{ p.kode }}</b> · {{ p.nama }}</td>
                  <td class="px-5 py-2 text-right num">
                    {{ fmt(p.rerata) }} <span class="text-stone-400">({{ p.jumlah }})</span>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>

          <div v-if="lemah" class="px-5 py-4 border-t border-stone-100">
            <h4 class="text-[12px] font-bold text-cam-ink mb-2">10 item terlemah — {{ entitasAktif }}</h4>
            <div class="space-y-1">
              <div v-for="g in lemah" :key="g.kode" class="flex justify-between text-[11.5px] gap-3">
                <span class="text-stone-600"><b class="num">{{ g.kode }}</b> {{ g.nama.slice(0, 60) }}</span>
                <span class="num font-semibold whitespace-nowrap">{{ fmt(g.rerata) }}</span>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

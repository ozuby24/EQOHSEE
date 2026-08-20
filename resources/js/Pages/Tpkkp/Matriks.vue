<script setup lang="ts">
/**
 * PTPKKP — Matriks.
 *
 * Seluruh baris dikirim sekali lalu disaring di peramban. Versi Blade
 * menyaring di server dan memuat ulang halaman tiap kali kata kuncinya
 * berubah; untuk tabel yang seluruh isinya sudah ada di memori, itu
 * perjalanan bolak-balik yang tidak menghasilkan apa pun selain jeda.
 *
 * Penyaringnya menjatuhkan baris dari bawah ke atas: item dulu, lalu
 * parameter yang kehabisan item, lalu indikator yang kehabisan parameter.
 * Tanpa urutan itu, judul indikator tetap tampil di atas tabel kosong dan
 * hasil pencariannya terbaca seolah ada isinya.
 */
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import PickerTpkkp from '../../Components/PickerTpkkp.vue';
import LencanaKategori from '../../Components/LencanaKategori.vue';
import type { HalamanMatriks } from '../../types';

const props = defineProps<HalamanMatriks>();

const cari = ref('');
const indikatorPilihan = ref('');

const angka = (n: number | null | undefined, d = 1) =>
  n === null || n === undefined
    ? '—'
    : n.toLocaleString('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d });

const persen = (r: number | null | undefined) =>
  r === null || r === undefined ? '—' : (r * 100).toFixed(1) + '%';

const tersaring = computed(() => {
  const q = cari.value.trim().toLowerCase();
  const ind = indikatorPilihan.value;

  return props.indikator
    .filter((I) => ind === '' || I.kode === ind)
    .map((I) => ({
      ...I,
      parameter: I.parameter
        .map((P) => ({
          ...P,
          items: q === ''
            ? P.items
            : P.items.filter(
                (c) => c.kode.toLowerCase().includes(q) || c.nama.toLowerCase().includes(q),
              ),
        }))
        // Parameter dipertahankan bila namanya sendiri cocok, walau tak
        // ada itemnya yang cocok — orang mencari nama parameter juga.
        .filter((P) => {
          if (q === '') return true;
          if (P.items.length) return true;
          return P.kode.toLowerCase().includes(q) || P.nama.toLowerCase().includes(q);
        }),
    }))
    .filter((I) => q === '' || I.parameter.length > 0);
});

const jumlahItem = computed(() =>
  tersaring.value.reduce(
    (n, I) => n + I.parameter.reduce((m, P) => m + P.items.length, 0), 0));

function reset() {
  cari.value = '';
  indikatorPilihan.value = '';
}
</script>

<template>
  <Head title="PTPKKP — Matriks" />

  <div class="max-w-full mx-auto space-y-5">

    <PickerTpkkp v-bind="picker" />

    <div class="bg-white rounded-2xl border border-stone-200 p-4 flex flex-wrap items-center gap-2">
      <input v-model="cari" placeholder="Cari kode atau item…"
             class="ring-focus rounded-xl border border-stone-200 px-3.5 py-2 text-[12.5px] w-64">

      <select v-model="indikatorPilihan"
              class="ring-focus rounded-xl border border-stone-200 px-3.5 py-2 text-[12.5px] font-semibold" aria-label="Indikator">
        <option value="">Semua indikator</option>
        <option v-for="I in indikator" :key="I.kode" :value="I.kode">
          {{ I.kode }} — {{ I.nama.slice(0, 38) }}
        </option>
      </select>

      <span class="text-[11.5px] text-stone-500 num">{{ jumlahItem }} item</span>

      <button v-if="cari || indikatorPilihan" type="button" @click="reset"
              class="text-[12px] text-stone-500 underline">Reset</button>

      <span class="ml-auto text-[11px] text-stone-400">
        titik · = metode berlaku tapi belum dinilai · — = metode tidak berlaku
      </span>
    </div>

    <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-[11.5px] min-w-[980px]">
          <thead class="text-[10px] uppercase tracking-wider text-stone-400 bg-stone-50 sticky top-0">
            <tr>
              <th class="text-left px-4 py-2.5 font-bold">No</th>
              <th class="text-left px-3 py-2.5 font-bold" style="min-width:300px">Item Pengukuran</th>
              <th v-for="m in metode" :key="m" class="text-right px-2 py-2.5 font-bold">{{ m }}</th>
              <th class="text-right px-3 py-2.5 font-bold">Nilai</th>
              <th class="text-right px-2 py-2.5 font-bold">Maks</th>
              <th class="text-right px-3 py-2.5 font-bold">Achv</th>
              <!--
                DUA KOLOM, dan keduanya perlu. "Kategori" adalah rasio
                capaian terhadap nilai maksimum — rumus resmi workbook.
                "Tingkat" adalah rerata skor 1–5 yang benar-benar diisi
                penilai.

                Keduanya kerap berselisih, dan selisihnya bukan
                kesalahan: skor 3 dari maksimum 5 berarti rasio 0,6 yang
                menurut ambang Kepdirjen jatuh ke "Reaktif", sedangkan
                tingkat rubrik yang diisi penilai memang "Terencana".
                Menampilkan satu saja membuat penilai melihat angka yang
                tidak pernah ia isi.
              -->
              <th class="text-left px-4 py-2.5 font-bold">Kategori</th>
              <th class="text-left px-4 py-2.5 font-bold">Tingkat</th>
            </tr>
          </thead>

          <tbody>
            <template v-for="I in tersaring" :key="I.kode">
              <tr class="bg-cam-ink/5 border-y border-stone-200 font-bold">
                <td class="px-4 py-2 num">{{ I.kode }}</td>
                <td class="px-3 py-2 text-cam-ink" :colspan="metode.length + 1">{{ I.nama }}</td>
                <td class="px-2 py-2 text-right num">{{ I.bobot.toFixed(2) }}</td>
                <td class="px-3 py-2 text-right num">{{ persen(I.rasio) }}</td>
                <td class="px-4 py-2"><LencanaKategori :kategori="I.kategori" :warna="I.warna" /></td>
                <td class="px-4 py-2">
                  <LencanaKategori v-if="I.tingkat" :kategori="I.tingkat" :warna="I.warnaTingkat" />
                  <span v-else class="text-stone-300">—</span>
                </td>
              </tr>

              <template v-for="P in I.parameter" :key="P.kode">
                <tr class="bg-stone-50 border-b border-stone-100">
                  <td class="px-4 py-1.5 num text-stone-500">{{ P.kode }}</td>
                  <td class="px-3 py-1.5 font-semibold text-stone-700" :colspan="metode.length">
                    {{ P.nama }}
                    <span class="font-normal text-stone-400 num">
                      · bobot {{ P.bobot.toFixed(2) }} ·
                      target {{ P.target === null ? '—' : P.target.toFixed(2) }}
                    </span>
                  </td>
                  <td class="px-3 py-1.5 text-right num">{{ angka(P.nilai) }}</td>
                  <td class="px-2 py-1.5 text-right num text-stone-400">{{ P.maks }}</td>
                  <td class="px-3 py-1.5 text-right num">{{ persen(P.rasio) }}</td>
                  <td class="px-4 py-1.5"><LencanaKategori :kategori="P.kategori" :warna="P.warna" /></td>
                  <td class="px-4 py-1.5">
                    <LencanaKategori v-if="P.tingkat" :kategori="P.tingkat" :warna="P.warnaTingkat" />
                    <span v-else class="text-stone-300">—</span>
                  </td>
                </tr>

                <tr v-for="c in P.items" :key="c.kode" class="border-b border-stone-50 hover:bg-stone-50/60">
                  <td class="px-4 py-1.5 num text-stone-400">{{ c.kode }}</td>
                  <td class="px-3 py-1.5 text-stone-700">{{ c.nama }}</td>

                  <td v-for="m in metode" :key="m" class="px-2 py-1.5 text-right"
                      :class="!c.metode.includes(m)
                        ? 'text-stone-200'
                        : (c.perMetode[m] === null || c.perMetode[m] === undefined
                            ? 'num text-stone-300'
                            : 'num text-cam-lime-deep font-semibold')">
                    {{ !c.metode.includes(m)
                        ? '—'
                        : (c.perMetode[m] === null || c.perMetode[m] === undefined
                            ? '·' : angka(c.perMetode[m])) }}
                  </td>

                  <td class="px-3 py-1.5 text-right num">{{ angka(c.nilai) }}</td>
                  <td class="px-2 py-1.5 text-right num text-stone-400">{{ c.maks }}</td>
                  <td class="px-3 py-1.5 text-right num">{{ persen(c.capaian) }}</td>
                  <td class="px-4 py-1.5"><LencanaKategori :kategori="c.kategori" :warna="c.warna" /></td>
                  <td class="px-4 py-1.5">
                    <!-- Rerata mentahnya ikut disebut: tingkat 4 dari
                         rerata 3,60 dan dari rerata 4,00 adalah dua
                         keadaan berbeda, dan yang membedakannya hanya
                         angka ini. -->
                    <LencanaKategori v-if="c.tingkat" :kategori="c.tingkat" :warna="c.warnaTingkat" />
                    <span v-else class="text-stone-300">—</span>
                    <small v-if="c.rerata !== null && c.rerata !== undefined"
                           class="block text-[10px] text-stone-400 num">
                      rerata {{ Number(c.rerata).toFixed(2) }}
                    </small>
                  </td>
                </tr>
              </template>
            </template>

            <tr v-if="!tersaring.length">
              <td :colspan="metode.length + 6" class="px-5 py-10 text-center text-[12.5px] text-stone-400">
                Tidak ada baris yang cocok.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

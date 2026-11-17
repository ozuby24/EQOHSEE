<script setup lang="ts">
/**
 * Rekap Bulanan — lembar PICA.
 *
 * Dua belas baris bulan beserta jumlah comply, not comply, N/A, belum
 * dinilai, persentase, evaluasi, dan rencana tindak lanjutnya.
 *
 * Angka berjalan DITAWARKAN, tidak disimpan sendiri. Rekap adalah
 * potret yang dibawa ke rapat dan ditandatangani; potret yang berubah
 * setiap kali halamannya dibuka bukan potret, dan rekap Januari yang
 * dihitung ulang pada bulan Desember memulangkan angka Desember.
 */
import { reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import type { HalamanKepatuhanRekap } from '../../types';
import PindahKepatuhan from './Pindah.vue';

const props = defineProps<HalamanKepatuhanRekap>();

/* Namanya BUKAN `saring`: itu nama propnya, dan fungsi bernama sama
   menutupi prop di dalam template. */
function ubahSaring(ubah: Record<string, string | number | null>) {
  router.get(props.tautan.rekap, { ...props.saring, ...ubah },
             { preserveState: true, replace: true });
}

const membuka = ref<number | null>(null);
const menyimpan = ref(false);

const draf = reactive<Record<string, string | number>>({
  comply: 0, not_comply: 0, na: 0, belum: 0, evaluasi: '', rencana: '',
});

function buka(b: HalamanKepatuhanRekap['bulan'][number]) {
  membuka.value = b.bulan;

  /* Bulan yang belum pernah direkap diisi angka BERJALAN sebagai usul;
     yang sudah direkap dibuka dengan angkanya sendiri, bukan ditimpa
     angka hari ini. */
  Object.assign(draf, b.terekap
    ? { comply: b.comply ?? 0, not_comply: b.notComply ?? 0, na: b.na ?? 0, belum: b.belum ?? 0,
        evaluasi: b.evaluasi ?? '', rencana: b.rencana ?? '' }
    : { comply: props.kini.comply, not_comply: props.kini.notComply, na: props.kini.na,
        belum: props.kini.belum, evaluasi: '', rencana: '' });
}

function simpan(bulan: number) {
  menyimpan.value = true;

  router.post(props.tautan.simpan, {
    ...draf,
    bulan,
    tahun: props.saring.tahun,
    sumber: props.saring.sumber ?? 'Peraturan',
    company_id: props.saring.perusahaan ?? '',
  }, {
    preserveScroll: true,
    onSuccess: () => { membuka.value = null; },
    onFinish: () => { menyimpan.value = false; },
  });
}

const teksPersen = (p: number | null | undefined) => (p === null || p === undefined ? '—' : `${p}%`);

const nada = (p: number | null | undefined) =>
  p === null || p === undefined ? '#A8A29E' : p >= 90 ? '#16A34A' : p >= 70 ? '#CA9A04' : '#DC2626';

const isian = 'ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]';
const pilih = 'ring-focus rounded-xl border border-stone-200 bg-white pl-3 pr-9 py-2 text-[12px] font-semibold text-stone-600';
const label = 'block text-[10.5px] font-bold uppercase tracking-wide text-stone-500 mb-1';
</script>

<template>
  <Head title="Rekap Bulanan Pemenuhan" />

  <div class="space-y-5">
    <PindahKepatuhan :tautan="tautan" kini="rekap" />

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-4">
      <div class="flex flex-wrap items-end gap-3">
        <label class="grid gap-1">
          <span class="block text-[10.5px] font-bold uppercase tracking-wide text-stone-400">Tahun</span>
          <select :class="pilih" :value="saring.tahun" @change="ubahSaring({ tahun: ($event.target as HTMLSelectElement).value })">
            <option v-for="t in opsi.tahun" :key="t" :value="t">{{ t }}</option>
          </select>
        </label>

        <label class="grid gap-1">
          <span class="block text-[10.5px] font-bold uppercase tracking-wide text-stone-400">Sumber</span>
          <select :class="pilih" :value="saring.sumber ?? ''" @change="ubahSaring({ sumber: ($event.target as HTMLSelectElement).value })">
            <option value="">Peraturan (bawaan)</option>
            <option v-for="s in opsi.sumber" :key="s" :value="s">{{ opsi.sumberNama[s] }}</option>
          </select>
        </label>

        <p class="text-[11.5px] text-stone-500 ml-auto max-w-md leading-relaxed">
          Angka berjalan saat ini:
          <b class="num">{{ kini.comply }}</b> comply ·
          <b class="num">{{ kini.notComply }}</b> not comply ·
          <b class="num">{{ kini.na }}</b> N/A ·
          <b class="num">{{ kini.belum }}</b> belum dinilai
          (<b class="num" :style="{ color: nada(kini.persen) }">{{ teksPersen(kini.persen) }}</b>).
          Angka ini diusulkan saat merekap bulan yang belum terisi.
        </p>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      <div class="px-5 py-3.5 border-b border-stone-100">
        <h3 class="text-[13px] font-bold text-cam-ink">Rekapitulasi {{ saring.tahun }}</h3>
        <p class="text-[11.5px] text-stone-500 mt-0.5">
          Bulan yang belum direkap ditandai jelas — ia tidak ikut menjadi titik pada tren dasbor.
        </p>
      </div>

      <div class="overflow-x-auto">
        <table class="kpt-pica w-full">
          <thead>
            <tr>
              <th class="kpt-k-bulan">Bulan</th>
              <th class="kpt-k-n num">Comply</th>
              <th class="kpt-k-n num">Not Comply</th>
              <th class="kpt-k-n num">N/A</th>
              <th class="kpt-k-n num">Belum</th>
              <th class="kpt-k-persen">Pemenuhan</th>
              <th class="kpt-k-teks">Evaluasi</th>
              <th class="kpt-k-teks">Rencana Tindak Lanjut</th>
              <th class="kpt-k-aksi">#</th>
            </tr>
          </thead>

          <tbody>
            <template v-for="b in bulan" :key="b.bulan">
              <tr :class="{ 'is-kosong': !b.terekap }">
                <td class="kpt-k-bulan">{{ b.nama }}</td>

                <template v-if="b.terekap">
                  <td class="kpt-k-n num">{{ b.comply }}</td>
                  <td class="kpt-k-n num">{{ b.notComply }}</td>
                  <td class="kpt-k-n num">{{ b.na }}</td>
                  <td class="kpt-k-n num">{{ b.belum }}</td>
                  <td class="kpt-k-persen">
                    <div class="flex items-center gap-2">
                      <div class="flex-1 h-1.5 rounded-full bg-stone-100 overflow-hidden min-w-[48px]">
                        <div class="h-full rounded-full"
                             :style="{ width: (b.persen ?? 0) + '%', background: nada(b.persen) }"></div>
                      </div>
                      <span class="num font-extrabold text-[11.5px]" :style="{ color: nada(b.persen) }">
                        {{ teksPersen(b.persen) }}
                      </span>
                    </div>
                  </td>
                  <td class="kpt-k-teks">{{ b.evaluasi || '—' }}</td>
                  <td class="kpt-k-teks">{{ b.rencana || '—' }}</td>
                </template>

                <td v-else colspan="6" class="kpt-belum">belum direkap</td>

                <td class="kpt-k-aksi">
                  <button type="button" class="kpt-ikon" :aria-label="`Rekap ${b.nama}`" @click="buka(b)">
                    {{ b.terekap ? '✎' : '+' }}
                  </button>
                </td>
              </tr>

              <tr v-if="membuka === b.bulan" class="kpt-sunting">
                <td colspan="9">
                  <p class="text-[12px] font-bold text-cam-ink mb-3">Rekap {{ b.nama }} {{ saring.tahun }}</p>

                  <div class="grid gap-3 sm:grid-cols-4">
                    <div><label :class="label" :for="`c-${b.bulan}`">Comply</label>
                         <input :id="`c-${b.bulan}`" v-model.number="draf.comply" type="number" min="0" :class="isian"></div>
                    <div><label :class="label" :for="`nc-${b.bulan}`">Not Comply</label>
                         <input :id="`nc-${b.bulan}`" v-model.number="draf.not_comply" type="number" min="0" :class="isian"></div>
                    <div><label :class="label" :for="`na-${b.bulan}`">N/A</label>
                         <input :id="`na-${b.bulan}`" v-model.number="draf.na" type="number" min="0" :class="isian"></div>
                    <div><label :class="label" :for="`bl-${b.bulan}`">Belum dinilai</label>
                         <input :id="`bl-${b.bulan}`" v-model.number="draf.belum" type="number" min="0" :class="isian"></div>
                  </div>

                  <div class="grid gap-3 sm:grid-cols-2 mt-3">
                    <div>
                      <label :class="label" :for="`ev-${b.bulan}`">Evaluasi</label>
                      <textarea :id="`ev-${b.bulan}`" v-model="draf.evaluasi" rows="2" :class="isian"
                                placeholder="Apa yang berubah bulan ini, dan kenapa"></textarea>
                    </div>
                    <div>
                      <label :class="label" :for="`rc-${b.bulan}`">Rencana tindak lanjut</label>
                      <textarea :id="`rc-${b.bulan}`" v-model="draf.rencana" rows="2" :class="isian"
                                placeholder="Yang akan dikerjakan bulan depan"></textarea>
                    </div>
                  </div>

                  <div class="flex items-center gap-2 mt-3">
                    <button type="button" class="eq-btn-utama" style="flex:none;padding:7px 16px"
                            :disabled="menyimpan" @click="simpan(b.bulan)">
                      {{ menyimpan ? 'Menyimpan…' : 'Simpan Rekap' }}
                    </button>
                    <button type="button" class="eq-btn-mini" @click="membuka = null">Batal</button>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
.kpt-pica { border-collapse: collapse; font-size: 12px; }

.kpt-pica thead th {
  background: #FAFAF9; border-bottom: 1px solid #E7E5E4;
  padding: .55rem .6rem; text-align: left;
  font-size: 10px; font-weight: 800; letter-spacing: .05em;
  text-transform: uppercase; color: #78716C;
  white-space: normal; vertical-align: bottom;
}

.kpt-pica tbody td { border-bottom: 1px solid #F5F5F4; padding: .55rem .6rem; vertical-align: top; }
.kpt-pica tbody tr.is-kosong td { background: #FCFCFB; }

.kpt-k-bulan  { width: 10%; font-weight: 700; }
.kpt-k-n      { width: 7%; }
.kpt-k-persen { width: 16%; }
.kpt-k-teks   { width: 19%; color: #57534E; }
.kpt-k-aksi   { width: 5%; text-align: right; }

.kpt-belum { color: #A8A29E; font-style: italic; }

.kpt-ikon {
  border: 1px solid #E7E5E4; background: #fff; border-radius: .5rem;
  width: 1.65rem; height: 1.65rem; font-size: 11px; color: #57534E;
  transition: border-color .16s, color .16s;
}
.kpt-ikon:hover { border-color: #DC6E00; color: #DC6E00; }

.kpt-sunting > td { background: #FAFAF9; border-bottom: 2px solid #E7E5E4; padding: 1rem; }

@media (max-width: 64rem) {
  .kpt-k-teks { display: none; }
  .kpt-k-persen { width: 34%; }
}
</style>

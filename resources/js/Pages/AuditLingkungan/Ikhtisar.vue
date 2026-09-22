<script setup lang="ts">
/**
 * Ikhtisar satu audit: skor per bagian, pengurang, predikat, peringkat.
 *
 * Bagian yang WAJIB PENUH ditandai, dan yang belum penuh diberi tahu
 * berapa poin lagi yang kurang. Tanpa itu, perusahaan berskor 99
 * melihat predikatnya tidak terbit dan tidak punya satu pun petunjuk
 * di mana poin yang hilang.
 */
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { HalamanAuditLingkunganIkhtisar } from '../../types';
import PindahBagian from './Pindah.vue';
import KartuSkor from './Skor.vue';

const props = defineProps<HalamanAuditLingkunganIkhtisar>();

const kurang = ref<string[]>([...props.a.pengurang]);
const menyimpanKurang = ref(false);

function simpanKurang() {
  menyimpanKurang.value = true;

  router.post(props.tautan.pengurang, { pengurang: kurang.value }, {
    preserveScroll: true,
    onFinish: () => { menyimpanKurang.value = false; },
  });
}

const maju = Object.fromEntries(
  Object.entries(props.skor.bagian).map(([k, b]) => [k, { belum: b.belum, kriteria: b.kriteria, penuh: b.penuh }]),
);
</script>

<template>
  <Head :title="a.kode" />

  <div class="space-y-5">
    <PindahBagian :bagian="Object.values(skor.bagian).map((b) => ({
                    kunci: b.kunci, huruf: b.kunci.toUpperCase(), judul: b.judul,
                    bobot: b.bobot, kriteria: b.kriteria, wajib: b.kunci === 'a' || b.kunci === 'b' }))"
                  :tautan-bagian="tautan.bagian" :ikhtisar="tautan.ikhtisar"
                  :kini="null" :maju="maju" />

    <!-- Identitas dan skor. -->
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <span class="text-[10px] font-bold bg-cam-ink text-white px-2 py-0.5 rounded num">{{ a.kode }}</span>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                  :class="a.status === 'Selesai' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
              {{ a.status }}
            </span>
          </div>
          <h2 class="text-[16px] font-extrabold text-cam-ink mt-2">{{ a.perusahaan ?? a.judul }}</h2>
          <p class="text-[12.5px] text-stone-500">
            {{ a.judul }}<template v-if="a.lokasi"> · {{ a.lokasi }}</template>
            <template v-if="a.tanggal"> · {{ a.tanggal }}</template>
          </p>
        </div>

        <div class="flex flex-wrap gap-2 shrink-0">
          <a :href="tautan.ubah" class="eq-btn-mini">✎ Ubah Identitas</a>
          <a :href="tautan.lembar" target="_blank" rel="noopener" class="eq-btn-lain" style="flex:none">
            ⎙ Cetak Lembar Audit
          </a>
        </div>
      </div>

      <KartuSkor :skor="skor" />
    </div>

    <!-- Skor per bagian. -->
    <section class="eq-panel">
      <div class="eq-panel-kepala">
        <h3>Skor per Bagian</h3>
        <span class="eq-panel-ket">Hasil tertimbang menjumlah menjadi persentase pemenuhan</span>
      </div>

      <div class="overflow-x-auto">
        <table class="akl-ringkas w-full">
          <thead>
            <tr>
              <th class="akl-k-b">Bagian</th>
              <th class="akl-k-bobot num">Bobot</th>
              <th class="akl-k-nilai num">Nilai / Maks</th>
              <th class="akl-k-capai">Capaian</th>
              <th class="akl-k-hasil num">Hasil</th>
              <th class="akl-k-sisa">Sisa</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in skor.bagian" :key="b.kunci"
                :class="{ 'is-wajib': b.kunci === 'a' || b.kunci === 'b' }">
              <td class="akl-k-b">
                <a :href="tautan.bagian[b.kunci]" class="akl-b-nama">
                  <b>{{ b.kunci.toUpperCase() }}.</b> {{ b.judul }}
                </a>
                <span v-if="(b.kunci === 'a' || b.kunci === 'b') && !b.penuh" class="akl-wajib-tanda">
                  wajib penuh — kurang {{ b.maks - b.nilai }} poin
                </span>
                <span v-else-if="b.kunci === 'a' || b.kunci === 'b'" class="akl-wajib-ok">
                  wajib penuh — terpenuhi
                </span>
              </td>

              <td class="akl-k-bobot num">{{ b.bobot.toFixed(2) }}</td>
              <td class="akl-k-nilai num">{{ b.nilai }} / {{ b.maks }}</td>

              <td class="akl-k-capai">
                <div class="akl-pita">
                  <div class="akl-pita-isi" :style="{ width: b.persen + '%' }"></div>
                </div>
                <span class="num akl-persen">{{ b.persen }}%</span>
              </td>

              <td class="akl-k-hasil num">{{ b.hasil.toFixed(2) }}</td>

              <td class="akl-k-sisa">
                <span v-if="b.belum" class="akl-sisa">{{ b.belum }} kriteria</span>
                <span v-else class="akl-tuntas">lengkap</span>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="4">Persentase pemenuhan</td>
              <td class="num">{{ skor.pemenuhan.toFixed(2) }}</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </section>

    <!-- Nilai pengurang. -->
    <section class="eq-panel">
      <div class="eq-panel-kepala">
        <h3>Nilai Pengurang</h3>
        <span class="eq-panel-ket">Dipotong dari skor akhir, bukan dari bagiannya</span>
      </div>

      <div class="grid gap-2 sm:grid-cols-2">
        <label v-for="p in opsi.pengurang" :key="p.kunci" class="akl-kurang-baris">
          <input v-model="kurang" type="checkbox" :value="p.kunci" class="ring-focus">
          <span class="akl-kurang-label">{{ p.label }}</span>
          <span class="akl-kurang-poin num">−{{ p.poin }}</span>
        </label>
      </div>

      <button type="button" class="eq-btn-lain mt-3" style="flex:none"
              :disabled="menyimpanKurang" @click="simpanKurang">
        {{ menyimpanKurang ? 'Menyimpan…' : 'Simpan Nilai Pengurang' }}
      </button>
    </section>

    <!-- Profil perusahaan yang diaudit. -->
    <section v-if="a.profil.length" class="eq-panel">
      <div class="eq-panel-kepala"><h3>Profil Perusahaan</h3></div>

      <dl class="akl-profil">
        <template v-for="p in a.profil" :key="p.kunci">
          <dt>{{ p.label }}</dt><dd>{{ p.nilai }}</dd>
        </template>
      </dl>
    </section>

    <section v-if="a.catatan" class="eq-panel">
      <div class="eq-panel-kepala"><h3>Catatan Auditor</h3></div>
      <p class="text-[12.5px] text-stone-600 leading-relaxed whitespace-pre-line">{{ a.catatan }}</p>
    </section>
  </div>
</template>

<style scoped>
.akl-ringkas { border-collapse: collapse; font-size: 12px; }
.akl-ringkas thead th {
  background: #FAFAF9; border-bottom: 1px solid #E7E5E4; padding: .5rem .6rem;
  text-align: left; font-size: 10px; font-weight: 800; letter-spacing: .05em;
  text-transform: uppercase; color: #78716C; white-space: normal; vertical-align: bottom;
}
.akl-ringkas tbody td { border-bottom: 1px solid #F5F5F4; padding: .6rem; vertical-align: top; }
.akl-ringkas tbody tr.is-wajib td { background: #FFFBF5; }
.akl-ringkas tfoot td {
  padding: .6rem; font-weight: 800; color: #0F1720; border-top: 2px solid #E7E5E4;
}

.akl-k-b     { width: 38%; }
.akl-k-bobot { width: 9%; }
.akl-k-nilai { width: 13%; }
.akl-k-capai { width: 20%; }
.akl-k-hasil { width: 10%; font-weight: 800; }
.akl-k-sisa  { width: 10%; }

.akl-b-nama { color: #44403C; }
.akl-b-nama b { color: #DC6E00; }
.akl-b-nama:hover { text-decoration: underline; }

.akl-wajib-tanda {
  display: block; margin-top: .2rem; font-size: 10px; font-weight: 700; color: #B45309;
}
.akl-wajib-ok { display: block; margin-top: .2rem; font-size: 10px; font-weight: 700; color: #15803D; }

.akl-pita { height: 6px; border-radius: 999px; background: #F0EFEE; overflow: hidden; }
.akl-pita-isi { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #DC6E00, #FF9800); }
.akl-persen { display: block; font-size: 10.5px; color: #78716C; margin-top: .2rem; font-weight: 700; }

.akl-sisa   { font-size: 11px; color: #B45309; font-weight: 700; }
.akl-tuntas { font-size: 11px; color: #15803D; font-weight: 700; }

.akl-kurang-baris {
  display: flex; align-items: center; gap: .6rem;
  border: 1px solid #E7E5E4; border-radius: .7rem; padding: .5rem .7rem;
  cursor: pointer; transition: border-color .16s;
}
.akl-kurang-baris:hover { border-color: #FCA5A5; }
.akl-kurang-baris:has(input:checked) { border-color: #DC2626; background: #FFFAFA; }
.akl-kurang-label { flex: 1; font-size: 11.5px; color: #44403C; }
.akl-kurang-poin  { font-size: 13px; font-weight: 800; color: #DC2626; }

.akl-profil { display: grid; grid-template-columns: auto 1fr; gap: .25rem .9rem; font-size: 12px; }
.akl-profil dt { font-weight: 700; color: #78716C; }
.akl-profil dd { color: #44403C; margin: 0; }

@media (max-width: 64rem) {
  .akl-k-bobot, .akl-k-sisa { display: none; }
  .akl-k-b { width: 46%; }
}
</style>

<script setup lang="ts">
/**
 * Daftar pelaksanaan Audit Kinerja Lingkungan.
 *
 * Tiap baris membawa skor akhir, predikat, dan peringkatnya — bukan
 * hanya judul dan tanggal. Yang membuka daftar ini ingin tahu mitra
 * mana yang tertinggal, dan pertanyaan itu tidak terjawab oleh daftar
 * judul.
 */
import { Head, router } from '@inertiajs/vue3';
import type { HalamanAuditLingkunganDaftar } from '../../types';

const props = defineProps<HalamanAuditLingkunganDaftar>();

const ubahTahun = (t: string) =>
  router.get(props.tautan.index, { tahun: t }, { preserveState: true, replace: true });

const pilih = 'ring-focus rounded-xl border border-stone-200 bg-white pl-3 pr-9 py-2 text-[12px] font-semibold text-stone-600';
</script>

<template>
  <Head title="Audit Kinerja Lingkungan" />

  <div class="space-y-5">
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-4">
      <div class="flex flex-wrap items-end gap-3">
        <label class="grid gap-1">
          <span class="block text-[10.5px] font-bold uppercase tracking-wide text-stone-400">Periode tahun</span>
          <select :class="pilih" :value="saring.tahun"
                  @change="ubahTahun(($event.target as HTMLSelectElement).value)">
            <option v-for="t in opsi.tahun" :key="t" :value="t">{{ t }}</option>
          </select>
        </label>

        <a :href="tautan.buat" class="eq-btn-utama ml-auto" style="flex:none">+ Audit Baru</a>
      </div>
    </div>

    <!-- Struktur instrumennya disebut di muka: enam bagian, bobotnya,
         dan mana yang harus bernilai penuh. Tanpa itu, yang membuka
         bagian F lebih dulu tidak tahu bahwa bobotnya 0,05. -->
    <section class="eq-panel">
      <div class="eq-panel-kepala">
        <h3>Struktur Penilaian</h3>
        <span class="eq-panel-ket">Bobot total 1,00 — bagian bertanda wajib harus bernilai penuh untuk mendapat predikat</span>
      </div>

      <div class="akl-bagian">
        <div v-for="b in bagian" :key="b.kunci" class="akl-ubin" :class="{ wajib: b.wajib }">
          <span class="akl-huruf">{{ b.huruf }}</span>
          <span class="akl-judul">{{ b.judul }}</span>
          <span class="akl-angka num">
            bobot {{ b.bobot.toFixed(2) }} · {{ b.kriteria }} kriteria · maks {{ b.maks }}
          </span>
          <span v-if="b.wajib" class="akl-wajib">wajib penuh</span>
        </div>
      </div>
    </section>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      <div class="px-5 py-3.5 border-b border-stone-100 flex items-center justify-between gap-2">
        <h3 class="text-[13px] font-bold text-cam-ink">Pelaksanaan Audit {{ saring.tahun }}</h3>
        <span class="text-[11.5px] text-stone-400 num">{{ daftar.length }} audit</span>
      </div>

      <div class="overflow-x-auto">
        <table class="akl-tabel w-full">
          <thead>
            <tr>
              <th class="akl-k-kode">Kode</th>
              <th class="akl-k-judul">Perusahaan &amp; Judul</th>
              <th class="akl-k-tgl">Tanggal</th>
              <th class="akl-k-maju">Kemajuan</th>
              <th class="akl-k-skor">Skor Akhir</th>
              <th class="akl-k-hasil">Predikat &amp; Peringkat</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="a in daftar" :key="a.id">
              <td class="akl-k-kode num"><a :href="a.url">{{ a.kode }}</a></td>

              <td class="akl-k-judul">
                <a :href="a.url" class="akl-nama">{{ a.perusahaan ?? a.judul }}</a>
                <span class="akl-sub">{{ a.judul }}<template v-if="a.lokasi"> · {{ a.lokasi }}</template></span>
              </td>

              <td class="akl-k-tgl num">{{ a.tanggal ?? '—' }}</td>

              <td class="akl-k-maju">
                <div class="akl-pita">
                  <div class="akl-pita-isi"
                       :style="{ width: ((a.kriteria - a.belum) / a.kriteria * 100) + '%' }"></div>
                </div>
                <span class="akl-pecah num">{{ a.kriteria - a.belum }} / {{ a.kriteria }} diverifikasi</span>
              </td>

              <td class="akl-k-skor num">
                <b :style="{ color: a.peringkat.warna }">{{ a.akhir.toFixed(2) }}</b>
                <span v-if="a.pengurang.length" class="akl-kurang">−{{ (a.pemenuhan - a.akhir).toFixed(0) }}</span>
              </td>

              <td class="akl-k-hasil">
                <span v-if="a.predikat.nama" class="akl-predikat">{{ a.predikat.nama }}</span>
                <span v-else class="akl-tanpa">predikat tidak terbit</span>
                <span class="akl-peringkat" :style="{ background: a.peringkat.warna }">
                  {{ a.peringkat.nama }}
                </span>
              </td>
            </tr>

            <tr v-if="!daftar.length">
              <td colspan="6">
                <p class="eq-kosong">
                  <strong>Belum ada audit pada tahun {{ saring.tahun }}.</strong>
                  <span class="block halus">
                    Instrumen ini menilai mitra kerja pada enam bagian, dua ratus satu kriteria.
                  </span>
                  <a :href="tautan.buat" class="eq-btn-utama">+ Mulai Audit</a>
                </p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
.akl-bagian { display: grid; gap: .6rem; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); }

.akl-ubin {
  border: 1px solid #E7E5E4; border-radius: .9rem; padding: .7rem .8rem;
  display: grid; gap: .1rem; position: relative;
}
.akl-ubin.wajib { border-color: #FDBA74; background: #FFFBF5; }

.akl-huruf { font-size: 18px; font-weight: 800; color: #DC6E00; line-height: 1; }
.akl-judul { font-size: 11.5px; font-weight: 700; color: #0F1720; line-height: 1.35; }
.akl-angka { font-size: 10px; color: #A8A29E; }
.akl-wajib {
  position: absolute; top: .55rem; right: .6rem;
  font-size: 8.5px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase;
  color: #B45309; background: #FEF3C7; padding: .1rem .35rem; border-radius: .3rem;
}

.akl-tabel { border-collapse: collapse; font-size: 12px; }
.akl-tabel thead th {
  background: #FAFAF9; border-bottom: 1px solid #E7E5E4; padding: .55rem .6rem;
  text-align: left; font-size: 10px; font-weight: 800; letter-spacing: .05em;
  text-transform: uppercase; color: #78716C; white-space: normal; vertical-align: bottom;
}
.akl-tabel tbody td { border-bottom: 1px solid #F5F5F4; padding: .6rem; vertical-align: top; }
.akl-tabel tbody tr:hover td { background: #FAFAF9; }

.akl-k-kode  { width: 10%; font-weight: 700; }
.akl-k-judul { width: 30%; }
.akl-k-tgl   { width: 11%; color: #78716C; }
.akl-k-maju  { width: 19%; }
.akl-k-skor  { width: 11%; }
.akl-k-hasil { width: 19%; }

.akl-k-kode a:hover, .akl-nama:hover { color: #DC6E00; text-decoration: underline; }
.akl-nama { font-weight: 700; color: #0F1720; }
.akl-sub  { display: block; font-size: 10.5px; color: #A8A29E; margin-top: .1rem; }

.akl-pita { height: 6px; border-radius: 999px; background: #F0EFEE; overflow: hidden; }
.akl-pita-isi { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #DC6E00, #FF9800); }
.akl-pecah { display: block; font-size: 10px; color: #A8A29E; margin-top: .25rem; }

.akl-k-skor b { font-size: 16px; font-weight: 800; }
.akl-kurang { display: block; font-size: 10px; color: #DC2626; font-weight: 700; }

.akl-predikat { display: block; font-size: 12px; font-weight: 800; color: #0F1720; }
.akl-tanpa { display: block; font-size: 11px; font-style: italic; color: #A8A29E; }
.akl-peringkat {
  display: inline-block; margin-top: .25rem; color: #fff;
  font-size: 9.5px; font-weight: 800; letter-spacing: .05em;
  padding: .1rem .45rem; border-radius: 999px;
}

@media (max-width: 66rem) {
  .akl-k-tgl { display: none; }
  .akl-k-judul { width: 38%; }
}
</style>

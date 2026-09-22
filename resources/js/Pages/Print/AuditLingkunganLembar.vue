<script setup lang="ts">
/**
 * Lembar Audit Kinerja Pengelolaan dan Pemantauan Lingkungan.
 *
 * Dokumen yang ditandatangani dan diserahkan ke pemegang IUP. Berisi
 * profil perusahaan, seluruh kriteria beserta nilainya, rekap
 * tertimbang per bagian, nilai pengurang, dan predikat beserta
 * peringkatnya.
 *
 * Tidak dipaksakan satu halaman: dua ratus satu kriteria memang tidak
 * muat, dan memaksanya akan menghasilkan huruf yang tidak terbaca.
 * Yang dijaga adalah judul kolom yang terulang di tiap halaman dan
 * baris yang tidak terbelah.
 */
import { Head } from '@inertiajs/vue3';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';
import KopCetak from '../../Components/KopCetak.vue';

defineOptions({ layout: BlankLayout });

defineProps<{
  dok: Record<string, unknown> | null;
  a: {
    kode: string; judul: string; tahun: number; lokasi: string | null;
    tanggal: string | null; status: string; perusahaan: string | null;
    catatan: string | null;
    profil: Array<{ kunci: string; label: string; nilai: string }>;
  };
  skor: {
    akhir: number; pemenuhan: number; pengurang: number; belum: number; kriteria: number;
    bagian: Record<string, { kunci: string; judul: string; bobot: number; maks: number;
                             nilai: number; persen: number; hasil: number; penuh: boolean; belum: number }>;
    predikat: { nama: string | null; alasan: string | null };
    peringkat: { nama: string; kriteria: string; warna: string };
    rincianKurang: Array<{ kunci: string; label: string; poin: number }>;
  };
  bagian: Array<{
    kunci: string; huruf: string; judul: string;
    butir: Array<{ kode: string; sub: string | null; kelompok: string; huruf: string | null;
                   uraian: string; nilai: number | null; verifikasi: number | null;
                   keterangan: string | null }>;
  }>;
  tangga: Record<string, Record<string, string>>;
  kembali: string;
}>();

</script>

<template>
  <Head :title="`Audit Lingkungan ${a.kode}`" />

  <PrintShell :title="`Audit Lingkungan ${a.kode}`" :kembali="kembali">
    <article class="akl-lembar lembar">
      <KopCetak :dok="dok" />

      <table class="akl-identitas">
        <tbody>
          <tr>
            <th>Nomor Audit</th><td class="num">{{ a.kode }}</td>
            <th>Periode</th><td class="num">Tahun {{ a.tahun }}</td>
            <th>Tanggal</th><td class="num">{{ a.tanggal ?? '—' }}</td>
          </tr>
          <tr>
            <th>Perusahaan</th><td colspan="3">{{ a.perusahaan ?? '—' }}</td>
            <th>Lokasi</th><td>{{ a.lokasi ?? '—' }}</td>
          </tr>
          <tr><th>Judul</th><td colspan="5">{{ a.judul }}</td></tr>

          <tr v-for="p in a.profil" :key="p.kunci">
            <th>{{ p.label }}</th><td colspan="5">{{ p.nilai }}</td>
          </tr>
        </tbody>
      </table>

      <!-- Rekap tertimbang, predikat, dan peringkat: yang dicari
           pembacanya pertama kali. -->
      <section class="akl-rekap">
        <div class="akl-rekap-judul">TOTAL SKOR PENILAIAN</div>

        <table class="akl-total">
          <thead>
            <tr>
              <th class="akl-t-no">No</th>
              <th class="akl-t-par">Parameter Penilaian</th>
              <th class="akl-t-n">Nilai Maksimal</th>
              <th class="akl-t-n">Hasil Verifikasi</th>
              <th class="akl-t-n">Bobot</th>
              <th class="akl-t-n">Hasil</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(b, i) in skor.bagian" :key="b.kunci">
              <td class="akl-t-no num">{{ i }}</td>
              <td>{{ b.judul }}<span v-if="!b.penuh && (b.kunci === 'a' || b.kunci === 'b')"
                                     class="akl-belum-penuh"> — belum bernilai penuh</span></td>
              <td class="akl-t-n num">{{ b.maks }}</td>
              <td class="akl-t-n num">{{ b.nilai }}</td>
              <td class="akl-t-n num">{{ b.bobot.toFixed(2) }}</td>
              <td class="akl-t-n num">{{ b.hasil.toFixed(2) }}</td>
            </tr>
            <tr class="akl-jumlah">
              <td colspan="5">Persentase Pemenuhan</td>
              <td class="akl-t-n num">{{ skor.pemenuhan.toFixed(2) }}</td>
            </tr>
          </tbody>
        </table>

        <table v-if="skor.rincianKurang.length" class="akl-total akl-kurang">
          <thead><tr><th colspan="2">Nilai Pengurang</th></tr></thead>
          <tbody>
            <tr v-for="k in skor.rincianKurang" :key="k.kunci">
              <td>{{ k.label }}</td>
              <td class="akl-t-n num">− {{ k.poin }}</td>
            </tr>
            <tr class="akl-jumlah"><td>Total pengurang</td><td class="akl-t-n num">− {{ skor.pengurang }}</td></tr>
          </tbody>
        </table>

        <div class="akl-hasil">
          <div class="akl-hasil-kotak">
            <span class="akl-hasil-label">SKOR AKHIR</span>
            <span class="akl-hasil-angka num">{{ skor.akhir.toFixed(2) }}</span>
          </div>
          <div class="akl-hasil-kotak">
            <span class="akl-hasil-label">PREDIKAT PENGHARGAAN</span>
            <span class="akl-hasil-angka">{{ skor.predikat.nama ?? '—' }}</span>
          </div>
          <div class="akl-hasil-kotak" :style="{ borderColor: skor.peringkat.warna }">
            <span class="akl-hasil-label">PERINGKAT</span>
            <span class="akl-hasil-angka" :style="{ color: skor.peringkat.warna }">
              {{ skor.peringkat.nama }}
            </span>
            <span class="akl-hasil-ket">{{ skor.peringkat.kriteria }}</span>
          </div>
        </div>

        <p v-if="skor.predikat.alasan" class="akl-alasan">{{ skor.predikat.alasan }}</p>
        <p v-if="skor.belum" class="akl-alasan">
          {{ skor.belum }} dari {{ skor.kriteria }} kriteria belum diverifikasi; kriteria yang belum
          diisi dihitung nol, sehingga skor di atas masih sementara.
        </p>
      </section>

      <!-- Rincian kriteria per bagian. -->
      <section v-for="b in bagian" :key="b.kunci" class="akl-bagian">
        <h3 class="akl-bagian-judul">{{ b.huruf }}. {{ b.judul.toUpperCase() }}</h3>

        <table class="akl-butir">
          <thead>
            <tr>
              <th class="akl-b-no">No</th>
              <th class="akl-b-uraian">Kriteria Penilaian</th>
              <th class="akl-b-n">Nilai</th>
              <th class="akl-b-n">Verifikasi</th>
              <th class="akl-b-ket">Keterangan</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="(k, i) in b.butir" :key="k.kode">
              <tr v-if="k.sub && (i === 0 || b.butir[i - 1].sub !== k.sub)" class="akl-sub">
                <td colspan="5">{{ k.sub }}</td>
              </tr>
              <tr v-if="i === 0 || b.butir[i - 1].kelompok !== k.kelompok" class="akl-grup">
                <td colspan="5">{{ k.kelompok }}</td>
              </tr>

              <tr>
                <td class="akl-b-no">{{ k.huruf ?? '—' }}</td>
                <td>{{ k.uraian }}</td>
                <td class="akl-b-n num">{{ k.nilai ?? '—' }}</td>
                <td class="akl-b-n num" :class="{ 'is-kosong': k.verifikasi === null }">
                  {{ k.verifikasi ?? '☐' }}
                </td>
                <td class="akl-b-ket">{{ k.keterangan || '—' }}</td>
              </tr>
            </template>
          </tbody>
        </table>
      </section>

      <p class="akl-tangga">
        <b>Kriteria nilai:</b>
        <span v-for="(t, n) in tangga.umum" :key="n"><b class="num">{{ n }}</b> {{ t }}.</span>
        <br><b>Bagian D (Kompetensi Personil):</b>
        <span v-for="(t, n) in tangga.d" :key="'d' + n"><b class="num">{{ n }}</b> {{ t }}.</span>
      </p>

      <section v-if="a.catatan" class="akl-catatan">
        <b>Catatan auditor:</b> {{ a.catatan }}
      </section>

      <section class="akl-ttd">
        <div class="akl-ttd-kotak">
          <div class="akl-ttd-peran">Diaudit oleh</div>
          <div class="akl-ttd-ruang"></div>
          <div class="akl-ttd-nama">&nbsp;</div>
          <div class="akl-ttd-jabatan">Auditor Lingkungan</div>
        </div>
        <div class="akl-ttd-kotak">
          <div class="akl-ttd-peran">Diketahui oleh</div>
          <div class="akl-ttd-ruang"></div>
          <div class="akl-ttd-nama">&nbsp;</div>
          <div class="akl-ttd-jabatan">Penanggung Jawab Operasional Mitra</div>
        </div>
        <div class="akl-ttd-kotak">
          <div class="akl-ttd-peran">Disahkan oleh</div>
          <div class="akl-ttd-ruang"></div>
          <div class="akl-ttd-nama">&nbsp;</div>
          <div class="akl-ttd-jabatan">Kepala Teknik Tambang</div>
        </div>
      </section>
    </article>
  </PrintShell>
</template>

<style scoped>
.akl-lembar { background: #fff; padding: 1.4rem 1.6rem 1.6rem; font-size: 9px; color: #1B1817; line-height: 1.32; }

.akl-identitas { width: 100%; border-collapse: collapse; margin-bottom: .5rem; table-layout: fixed; }
.akl-identitas th, .akl-identitas td { border: 1px solid #D6D3D1; padding: .2rem .4rem; text-align: left; vertical-align: top; }
.akl-identitas th { width: 12%; background: #F5F5F4; font-weight: 700; font-size: 8px; text-transform: uppercase; letter-spacing: .03em; }
.akl-identitas td { width: 21.33%; }

.akl-rekap { border: 1px solid #1C1917; margin-bottom: .7rem; break-inside: avoid; }
.akl-rekap-judul { background: #1C1917; color: #E7E5E4; font-size: 8.5px; font-weight: 800; letter-spacing: .07em; padding: .25rem .5rem; }

.akl-total { width: 100%; border-collapse: collapse; table-layout: fixed; }
.akl-total th, .akl-total td { border: 1px solid #D6D3D1; padding: .22rem .4rem; text-align: left; vertical-align: top; }
.akl-total thead th { background: #F5F5F4; font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
.akl-total .akl-jumlah td { background: #FAFAF9; font-weight: 800; }

.akl-t-no { width: 5%; text-align: center; }
.akl-t-par { width: 47%; }
.akl-t-n  { width: 12%; text-align: right; }
.akl-kurang { margin-top: -1px; }
.akl-kurang td:first-child { width: 88%; }

.akl-belum-penuh { color: #B45309; font-weight: 700; }

.akl-hasil { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0; border-top: 1px solid #D6D3D1; }
.akl-hasil-kotak { border-right: 1px solid #D6D3D1; border-bottom: 2px solid transparent; padding: .45rem .5rem; text-align: center; }
.akl-hasil-kotak:last-child { border-right: 0; }
.akl-hasil-label { display: block; font-size: 7.5px; font-weight: 800; letter-spacing: .07em; color: #78716C; }
.akl-hasil-angka { display: block; font-size: 17px; font-weight: 800; color: #0F1720; line-height: 1.2; }
.akl-hasil-ket   { display: block; font-size: 7.5px; color: #78716C; font-weight: 700; letter-spacing: .04em; }

.akl-alasan { padding: .35rem .5rem; font-size: 8px; color: #B45309; border-top: 1px solid #F0EFEE; }

.akl-bagian { margin-bottom: .6rem; }
.akl-bagian-judul { background: #E7E5E4; font-size: 8.5px; font-weight: 800; letter-spacing: .05em; padding: .22rem .45rem; margin: 0 0 .15rem; }

.akl-butir { width: 100%; border-collapse: collapse; table-layout: fixed; }
.akl-butir th, .akl-butir td { border: 1px solid #E7E5E4; padding: .18rem .35rem; vertical-align: top; text-align: left; }
.akl-butir thead th { background: #FAFAF9; font-size: 7.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; color: #57534E; }

.akl-b-no  { width: 4%; text-align: center; color: #A8A29E; }
.akl-b-uraian { width: 54%; }
.akl-b-n   { width: 8%; text-align: center; font-weight: 700; }
.akl-b-n.is-kosong { color: #A8A29E; font-weight: 400; }
.akl-b-ket { width: 26%; color: #57534E; }

.akl-sub  td { background: #1C1917; color: #E7E5E4; font-size: 7.5px; font-weight: 800; letter-spacing: .06em; }
.akl-grup td { background: #F5F5F4; font-size: 8px; font-weight: 800; }

.akl-tangga { font-size: 7.5px; color: #57534E; margin: .4rem 0 .5rem; line-height: 1.6; }
.akl-tangga span { margin-right: .5rem; }
.akl-tangga span b { color: #0F1720; }

.akl-catatan { border: 1px solid #D6D3D1; background: #FAFAF9; padding: .3rem .45rem; margin-bottom: .5rem; white-space: pre-line; }

.akl-ttd { display: grid; grid-template-columns: repeat(3, 1fr); gap: .8rem; margin-top: 1rem; break-inside: avoid; page-break-inside: avoid; }
.akl-ttd-kotak { text-align: center; }
.akl-ttd-peran { font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #57534E; }
.akl-ttd-ruang { height: 2.4rem; }
.akl-ttd-nama { border-top: 1px solid #1B1817; padding-top: .15rem; font-weight: 700; }
.akl-ttd-jabatan { font-size: 8px; color: #57534E; }

@media print {
  .akl-lembar { padding: 0; }
  /* Judul kolom terulang tiap halaman: dua ratus satu kriteria pasti
     melewati banyak halaman, dan halaman tanpa judul kolom adalah lima
     kolom angka yang harus ditebak. */
  .akl-butir thead, .akl-total thead { display: table-header-group; }
  .akl-butir tr, .akl-total tr { break-inside: avoid; page-break-inside: avoid; }
  .akl-bagian-judul { break-after: avoid; page-break-after: avoid; }
  .akl-sub td, .akl-grup td { break-after: avoid; page-break-after: avoid; }
}
</style>

<script setup lang="ts">
/**
 * Lembar Identifikasi & Evaluasi Pemenuhan — satu kewajiban.
 *
 * Bentuknya mengikuti tabel yang selama ini dipakai di berkas Word:
 * satu baris per pasal atau ayat, dengan kolom Rangkuman Isi,
 * Penerapan, Evaluasi Pemenuhan, dan Keterangan & Tindak Lanjut.
 * Kolom Evaluasi terbagi dua — "A" untuk yang comply dan "N/A" untuk
 * yang tidak berlaku — persis seperti berkas acuannya, karena lembar
 * inilah yang dilampirkan ke berkas audit dan dibandingkan auditor
 * dengan arsip tahun sebelumnya.
 *
 * Butir yang BELUM dinilai tidak dibiarkan tampil sebagai kolom kosong
 * yang terbaca seperti N/A. Ia ditandai tegas, karena lembar yang
 * memulangkan pekerjaan setengah jadi sebagai evaluasi lengkap adalah
 * lembar yang ditandatangani tanpa ada yang tahu apa yang belum
 * dikerjakan.
 */
import { Head } from '@inertiajs/vue3';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';
import KopCetak from '../../Components/KopCetak.vue';

defineOptions({ layout: BlankLayout });

const props = defineProps<{
  dok: Record<string, unknown> | null;
  s: {
    kode: string | null; sumber: string; jenis: string | null; nomor: string;
    judul: string; instansi: string | null; tanggalTerbit: string | null;
    aspek: string; tahun: number; ruangLingkup: string | null;
    rangkuman: string | null; perusahaan: string | null;
  };
  rekap: { total: number; comply: number; notComply: number; na: number; belum: number;
           dinilai: number; persen: number | null };
  butir: Array<{
    no: number; penunjuk: string; rangkuman: string | null; penerapan: string | null;
    status: string | null; keterangan: string | null; tindak: string | null;
    pic: string | null; target: string | null;
  }>;
  kembali: string;
}>();

const persen = props.rekap.persen;
</script>

<template>
  <Head :title="`Evaluasi Pemenuhan ${s.nomor}`" />

  <PrintShell :title="`Evaluasi Pemenuhan ${s.nomor}`" :kembali="kembali">
    <article class="kpt-lembar lembar">
      <KopCetak :dok="dok" />

      <table class="kpt-identitas">
        <tbody>
          <tr>
            <th>Kode Register</th><td class="num">{{ s.kode ?? '—' }}</td>
            <th>Aspek</th><td>{{ s.aspek }}</td>
            <th>Tahun Evaluasi</th><td class="num">{{ s.tahun }}</td>
          </tr>
          <tr>
            <th>Jenis</th><td>{{ s.jenis ?? '—' }}</td>
            <th>Instansi Penerbit</th><td>{{ s.instansi ?? '—' }}</td>
            <th>Tanggal Terbit</th><td class="num">{{ s.tanggalTerbit ?? '—' }}</td>
          </tr>
          <tr>
            <th>Nomor</th><td colspan="3">{{ s.nomor }}</td>
            <th>Perusahaan</th><td>{{ s.perusahaan ?? 'Berlaku umum' }}</td>
          </tr>
          <tr>
            <th>Judul</th><td colspan="5">{{ s.judul }}</td>
          </tr>
          <tr v-if="s.ruangLingkup">
            <th>Ruang Lingkup</th><td colspan="5">{{ s.ruangLingkup }}</td>
          </tr>
          <tr v-if="s.rangkuman">
            <th>Rangkuman</th><td colspan="5">{{ s.rangkuman }}</td>
          </tr>
        </tbody>
      </table>

      <div class="kpt-rekap">
        <span class="kpt-rekap-label">REKAPITULASI</span>
        <span><b>{{ rekap.total }}</b> butir</span>
        <span class="is-ok"><b>{{ rekap.comply }}</b> comply</span>
        <span class="is-nok"><b>{{ rekap.notComply }}</b> not comply</span>
        <span v-if="rekap.na"><b>{{ rekap.na }}</b> N/A</span>
        <span v-if="rekap.belum" class="is-nok"><b>{{ rekap.belum }}</b> belum dinilai</span>
        <span class="kpt-persen">
          Pemenuhan {{ persen === null ? '—' : persen + '%' }}
        </span>
      </div>

      <table class="kpt-nilai">
        <thead>
          <tr>
            <th rowspan="2" class="kpt-k-no">No</th>
            <th rowspan="2" class="kpt-k-tunjuk">Pasal; Ayat</th>
            <th rowspan="2" class="kpt-k-isi">Rangkuman Isi</th>
            <th rowspan="2" class="kpt-k-terap">Penerapan</th>
            <th colspan="2" class="kpt-k-eval">Evaluasi Pemenuhan</th>
            <th rowspan="2" class="kpt-k-ket">Keterangan &amp; Tindak Lanjut</th>
          </tr>
          <tr>
            <th class="kpt-k-a">A</th>
            <th class="kpt-k-na">N/A</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="b in butir" :key="b.no">
            <td class="kpt-k-no num">{{ b.no }}</td>
            <td class="kpt-k-tunjuk">{{ b.penunjuk }}</td>
            <td>{{ b.rangkuman ?? '—' }}</td>
            <td>{{ b.penerapan ?? '—' }}</td>

            <!-- Kolom A memuat tanda comply, atau tanda silang bila not
                 comply. Sel yang dikosongkan untuk keduanya membuat
                 "belum diperiksa" dan "diperiksa, tidak comply" tampak
                 sama di atas kertas. -->
            <td class="kpt-tengah">
              <span v-if="b.status === 'Comply'" class="is-ok">✓</span>
              <span v-else-if="b.status === 'Not Comply'" class="is-nok">✗</span>
              <span v-else-if="!b.status" class="is-kosong">☐</span>
            </td>
            <td class="kpt-tengah">
              <span v-if="b.status === 'N/A'">✓</span>
            </td>

            <td>
              <span v-if="b.keterangan">{{ b.keterangan }}</span>

              <template v-if="b.status === 'Not Comply'">
                <span v-if="b.tindak" class="kpt-blok"><b>Tindak lanjut:</b> {{ b.tindak }}</span>
                <span v-if="b.pic || b.target" class="kpt-blok">
                  <template v-if="b.pic"><b>PIC:</b> {{ b.pic }}</template>
                  <template v-if="b.target"> · <b>Target:</b> <span class="num">{{ b.target }}</span></template>
                </span>
              </template>

              <span v-if="!b.keterangan && !b.tindak && !b.pic" class="kpt-sunyi">—</span>
            </td>
          </tr>

          <tr v-if="!butir.length">
            <td colspan="7" class="kpt-tengah kpt-sunyi">Belum ada butir yang dirinci.</td>
          </tr>
        </tbody>
      </table>

      <p class="kpt-kunci">
        <b>A</b> = terpenuhi (comply) &nbsp;·&nbsp; <b>✗</b> = belum terpenuhi (not comply)
        &nbsp;·&nbsp; <b>N/A</b> = tidak berlaku bagi kegiatan perusahaan, tidak ikut jadi pembagi
        &nbsp;·&nbsp; <b>☐</b> = belum dinilai
      </p>

      <section class="kpt-ttd">
        <div class="kpt-ttd-kotak">
          <div class="kpt-ttd-peran">Disusun oleh</div>
          <div class="kpt-ttd-ruang"></div>
          <div class="kpt-ttd-nama">&nbsp;</div>
          <div class="kpt-ttd-jabatan">Petugas Lingkungan / K3</div>
        </div>
        <div class="kpt-ttd-kotak">
          <div class="kpt-ttd-peran">Diperiksa oleh</div>
          <div class="kpt-ttd-ruang"></div>
          <div class="kpt-ttd-nama">&nbsp;</div>
          <div class="kpt-ttd-jabatan">Manager OHSE</div>
        </div>
        <div class="kpt-ttd-kotak">
          <div class="kpt-ttd-peran">Disahkan oleh</div>
          <div class="kpt-ttd-ruang"></div>
          <div class="kpt-ttd-nama">&nbsp;</div>
          <div class="kpt-ttd-jabatan">Penanggung Jawab Operasional</div>
        </div>
      </section>
    </article>
  </PrintShell>
</template>

<style scoped>
.kpt-lembar {
  background: #fff;
  padding: 1.4rem 1.6rem 1.6rem;
  font-size: 9px;
  color: #1B1817;
  line-height: 1.32;
}

.kpt-identitas { width: 100%; border-collapse: collapse; margin-bottom: .45rem; table-layout: fixed; }
.kpt-identitas th,
.kpt-identitas td { border: 1px solid #D6D3D1; padding: .2rem .4rem; text-align: left; vertical-align: top; }
.kpt-identitas th {
  width: 11%; background: #F5F5F4; font-weight: 700;
  font-size: 8px; text-transform: uppercase; letter-spacing: .03em;
}
.kpt-identitas td { width: 22.33%; }

.kpt-rekap {
  display: flex; flex-wrap: wrap; align-items: baseline; gap: .1rem .9rem;
  border: 1px solid #D6D3D1; background: #FAFAF9;
  padding: .26rem .45rem; margin-bottom: .45rem;
}
.kpt-rekap-label { font-size: 8px; font-weight: 800; letter-spacing: .07em; color: #57534E; }
.kpt-rekap b { font-size: 11px; }
.kpt-persen { margin-left: auto; font-weight: 800; }

.kpt-nilai { width: 100%; border-collapse: collapse; table-layout: fixed; }
.kpt-nilai th,
.kpt-nilai td { border: 1px solid #D6D3D1; padding: .22rem .35rem; vertical-align: top; text-align: left; }
.kpt-nilai thead th {
  background: #1C1917; color: #E7E5E4;
  font-size: 8px; font-weight: 700; letter-spacing: .04em;
  text-transform: uppercase; text-align: center; vertical-align: middle;
}

.kpt-k-no     { width: 4%; text-align: center; }
.kpt-k-tunjuk { width: 12%; }
.kpt-k-isi    { width: 24%; }
.kpt-k-terap  { width: 22%; }
.kpt-k-eval   { width: 10%; }
.kpt-k-a      { width: 5%; }
.kpt-k-na     { width: 5%; }
.kpt-k-ket    { width: 23%; }

.kpt-tengah { text-align: center; font-weight: 800; font-size: 11px; }
.kpt-sunyi  { color: #A8A29E; font-weight: 400; font-size: 9px; }
.kpt-blok   { display: block; margin-top: .15rem; }

.is-ok     { color: #15803D; }
.is-nok    { color: #B91C1C; }
.is-kosong { color: #A8A29E; }

.kpt-kunci { margin: .35rem 0 .6rem; font-size: 8px; color: #57534E; }

.kpt-ttd {
  display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .8rem;
  margin-top: 1rem; break-inside: avoid; page-break-inside: avoid;
}
.kpt-ttd-kotak { text-align: center; }
.kpt-ttd-peran { font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #57534E; }
.kpt-ttd-ruang { height: 2.4rem; }
.kpt-ttd-nama { border-top: 1px solid #1B1817; padding-top: .15rem; font-weight: 700; }
.kpt-ttd-jabatan { font-size: 8px; color: #57534E; }

@media print {
  .kpt-lembar { padding: 0; }
  /* Judul kolom diulang tiap halaman: register lima puluh pasal pasti
     melewati satu halaman, dan halaman kedua tanpa judul kolom adalah
     tujuh kolom yang harus ditebak. */
  .kpt-nilai thead { display: table-header-group; }
  .kpt-nilai tr { break-inside: avoid; page-break-inside: avoid; }
}
</style>

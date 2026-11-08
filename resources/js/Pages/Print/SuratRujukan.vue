<script setup lang="ts">
/**
 * Surat rujukan — mcus/surat-rujukan.blade.php Safe Track: kop formulir
 * "Pengantar Rujukan Dokter Perusahaan (Inhouse Clinic)", isi rujukan,
 * tanda tangan dokter, lalu lembar "Surat Rujukan Balik" yang diisi
 * dokter spesialis.
 *
 * Kop dan nomor formulir mengikuti IUP penerbit MCU-nya; penanda
 * tangannya dokter pemeriksa — bukan nama dokter perusahaan asal formulir.
 * Tanda tangan berupa QR berisi nomor, nama pasien, dan tanggal surat.
 */
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import QRCode from 'qrcode';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';

defineOptions({ layout: BlankLayout });

const props = defineProps<{ s: any; kembali?: string }>();

const qr = ref<string | null>(null);

onMounted(async () => {
  try {
    qr.value = await QRCode.toDataURL(String(props.s.qr), { margin: 1, width: 300, errorCorrectionLevel: 'M' });
  } catch { /* tanpa QR, suratnya tetap dapat dicetak */ }
});
</script>

<template>
  <Head title="Surat Rujukan" />

  <PrintShell title="Surat Rujukan" :kembali="props.kembali">
    <div class="lembar-rujukan">
      <table class="kop">
        <tbody>
          <tr>
            <td rowspan="6" class="logo">
              <img v-if="s.kop.logo" :src="s.kop.logo" alt="">
              <b v-else>{{ s.kop.perusahaan }}</b>
            </td>
            <td colspan="2" class="tengah"><b>FORMS &amp; CHECKLIST</b></td>
            <td colspan="2"><b>No. {{ s.kop.nomor }}</b></td>
          </tr>
          <tr>
            <td rowspan="3" colspan="2" class="tengah judul"><b>{{ s.kop.judul }}</b></td>
            <td>Tgl Penerbitan :</td><td>{{ s.kop.terbit }}</td>
          </tr>
          <tr><td>Tgl Persetujuan :</td><td>{{ s.kop.setuju }}</td></tr>
          <tr><td>No. Revisi :</td><td>{{ s.kop.revisi }}</td></tr>
          <tr><td><b>DIVISI :</b></td><td><b>HSE</b></td><td>Halaman :</td><td>1 dari 1</td></tr>
          <tr><td><b>DEPARTEMEN :</b></td><td colspan="3"><b>OCCUPATIONAL HEALTH AND SAFETY (OHS)</b></td></tr>
        </tbody>
      </table>

      <p class="mt">Tanggal {{ s.tanggal }}</p>
      <table class="garis"><tbody><tr><td>PERUSAHAAN : {{ s.perusahaan }}</td></tr></tbody></table>

      <div class="kotak mt">
        <p>Yang terhormat Rekan Sejawat,</p>
        <table class="isian">
          <tbody>
            <tr><td class="lbl">Dokter Spesialis</td><td>: {{ s.dokter }}</td></tr>
            <tr><td class="lbl">Poliklinik</td><td>: {{ s.poliklinik }}</td></tr>
            <tr><td class="lbl">Rumah Sakit</td><td>: {{ s.rumahSakit }}</td></tr>
          </tbody>
        </table>
        <p>Mohon pemeriksaan dan pengobatan lebih lanjut, terhadap pasien :</p>
        <table class="isian">
          <tbody>
            <tr><td class="lbl">Nama</td><td>: {{ s.nama }}</td></tr>
            <tr><td class="lbl">Usia</td><td>: {{ s.usia }}</td></tr>
            <tr><td class="lbl">Jabatan</td><td>: {{ s.jabatan }}</td></tr>
            <tr><td class="lbl">Diagnosa Awal</td><td>: {{ s.diagnosis }}</td></tr>
          </tbody>
        </table>
        <p>Pemeriksaan penunjang ( laboratorium, Radiologi, dll ) :</p>
        <table class="isian">
          <tbody><tr><td class="lbl">Keterangan</td><td>: {{ s.keterangan }}</td></tr></tbody>
        </table>
        <p>Atas perhatian dan kerjasamanya kami ucapkan terimakasih.</p>

        <div class="ttd">
          <div>{{ s.kota ? s.kota + ', ' : '' }}{{ s.tanggalPanjang }}</div>
          <img v-if="qr" :src="qr" alt="QR tanda tangan dokter">
          <div v-else style="height:70px"></div>
          <div class="nama">{{ s.penandatangan || '(dokter belum diatur)' }}</div>
        </div>
      </div>

      <p class="potong">..............................................................................................................................................................</p>

      <table class="garis"><tbody><tr><td class="tengah"><b>SURAT RUJUKAN BALIK</b></td></tr></tbody></table>
      <div class="kotak">
        <table class="isian">
          <tbody>
            <tr><td class="lbl">Nama</td><td style="width:45%">:</td><td style="width:50px">Usia</td><td>:</td></tr>
            <tr><td class="lbl">Diagnosa</td><td colspan="3">:</td></tr>
            <tr><td class="lbl">Terapi</td><td colspan="3">:</td></tr>
            <tr><td class="lbl">Saran</td><td colspan="3">:</td></tr>
            <tr><td class="lbl">Keterangan</td><td colspan="3">:</td></tr>
          </tbody>
        </table>
        <div class="ttd">
          <div>......................, ..../..../........</div>
          <div style="height:60px"></div>
          <div>(Dokter Spesialis)</div>
        </div>
      </div>
    </div>
  </PrintShell>
</template>

<style>
@media print { @page { size: A4 portrait; margin: 12mm; } }
</style>

<style scoped>
.lembar-rujukan{background:#fff;color:#000;font-family:Arial,sans-serif;font-size:12px;padding:24px;border:1px solid #e7e5e4}
@media print{.lembar-rujukan{border:0;padding:0}}
table{border-collapse:collapse;width:100%}
.kop td,.garis td{border:1px solid #000;padding:4px 6px}
.kop .logo{width:150px;text-align:center}
.kop .logo img{max-width:120px;max-height:70px}
.tengah{text-align:center}
.judul{font-size:13px}
.mt{margin-top:12px}
.kotak{border:1px solid #000;padding:10px 12px}
.kotak p{margin:6px 0}
.isian td{border:none;padding:2px 0}
.isian .lbl{width:120px}
.ttd{width:260px;margin-left:auto;text-align:center;margin-top:12px}
.ttd img{width:80px;height:80px;margin:6px auto}
.ttd .nama{font-weight:bold}
.potong{margin:14px 0;color:#666;overflow:hidden;white-space:nowrap}
</style>

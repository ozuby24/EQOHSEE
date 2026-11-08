<script setup lang="ts">
/**
 * Sertifikat induksi — induction_registrations/sertifikat.blade.php Safe
 * Track: A4 mendatar berbingkai ganda dalam warna IUP penyelenggara,
 * logo, "SERTIFIKAT INDUKSI / SAFETY INDUCTION CERTIFICATE", nomor
 * IND/{kode}/{id}/{mm}/{yyyy}, nama peserta, rincian, pernyataan lulus,
 * QR verifikasi, dan tanda tangan KTT.
 *
 * Diterbitkan aplikasi sendiri — bukan pindaian yang diunggah — dan
 * hanya bagi peserta pendaftaran yang disetujui dan tidak dinyatakan
 * Tidak Lulus; selain itu lembarnya MENGATAKAN alasannya.
 */
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import QRCode from 'qrcode';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';

defineOptions({ layout: BlankLayout });

const props = defineProps<{ boleh: boolean; alasan?: string; s: any | null; kembali?: string }>();

const qr = ref<string | null>(null);

onMounted(async () => {
  if (!props.s?.qr) return;
  try {
    qr.value = await QRCode.toDataURL(String(props.s.qr), { margin: 0, width: 220, errorCorrectionLevel: 'L' });
  } catch { /* tanpa QR, sertifikatnya tetap dapat dicetak */ }
});

const rincian = computed(() => props.s ? [
  ['Nomor Induk', props.s.noid],
  ['Jabatan', props.s.jabatan],
  ['Departemen', props.s.departemen],
  ['Perusahaan', props.s.perusahaan],
].filter(([, v]) => String(v ?? '').trim() !== '') : []);

const iup = computed(() => props.s?.namaIup || 'perusahaan');
</script>

<template>
  <Head :title="`Sertifikat Induksi ${s?.nama ?? ''}`" />

  <PrintShell title="Sertifikat Induksi" :kembali="props.kembali">
    <div v-if="boleh && s" class="lembar" :style="{ '--warna': s.warna }">
      <div class="bingkai"></div>
      <div class="bingkai-dalam"></div>

      <div class="isi">
        <img v-if="s.logo" class="logo" :src="s.logo" alt="">
        <div class="nama-iup">{{ s.namaIup }}</div>

        <div class="judul">SERTIFIKAT INDUKSI</div>
        <div class="anak-judul">SAFETY INDUCTION CERTIFICATE</div>
        <div class="nomor">Nomor: {{ s.nomor }}</div>

        <div class="kepada">Diberikan kepada</div>
        <div class="nama-peserta">{{ s.nama || '—' }}</div>

        <table class="rincian">
          <tbody>
            <tr v-for="[label, nilai] in rincian" :key="label">
              <td class="label">{{ label }}</td><td class="titik">:</td><td class="nilai">{{ nilai }}</td>
            </tr>
          </tbody>
        </table>

        <div class="pernyataan">
          telah mengikuti dan dinyatakan <strong>LULUS</strong> Induksi Keselamatan Pertambangan yang diselenggarakan {{ iup }}<template v-if="s.tanggal"> pada tanggal <strong>{{ s.tanggal }}</strong></template><template v-if="s.lokasi"> di <strong>{{ s.lokasi }}</strong></template>.
          <br>
          Sertifikat ini berlaku selama yang bersangkutan bekerja di area operasional {{ iup }}.
        </div>
      </div>

      <div class="kaki">
        <div class="qr">
          <img v-if="qr" :src="qr" alt="QR verifikasi">
          <div v-if="qr" class="keterangan">{{ s.qrTautan ? 'Pindai untuk verifikasi' : 'Kode verifikasi sertifikat' }}</div>
        </div>
        <div class="ttd">
          <img v-if="s.ttdKtt" :src="s.ttdKtt" alt="">
          <div v-else style="height:18mm"></div>
          <div class="garis">{{ s.namaKtt || '—' }}</div>
          <div class="jabatan">{{ s.jabatanKtt }}</div>
        </div>
      </div>
    </div>

    <div v-else class="lembar-kosong">
      <b>Sertifikat induksi belum dapat diterbitkan.</b>
      <p>{{ alasan }}</p>
    </div>
  </PrintShell>
</template>

<style>
@media print { @page { size: A4 landscape; margin: 0; } }
</style>

<style scoped>
.lembar{position:relative;overflow:hidden;width:297mm;height:210mm;background:#fff;color:#16233a;
  font-family:'DejaVu Sans',Arial,sans-serif;margin:0 auto;box-shadow:0 0 0 1px #e7e5e4}
@media print{.lembar{box-shadow:none}}
.bingkai{position:absolute;top:8mm;left:8mm;right:8mm;bottom:8mm;border:1.2mm solid var(--warna)}
.bingkai-dalam{position:absolute;top:10.5mm;left:10.5mm;right:10.5mm;bottom:10.5mm;border:.3mm solid var(--warna)}
.isi{position:relative;text-align:center;margin:14mm 18mm 0}
.logo{height:16mm;margin:0 auto 2mm;display:block}
.nama-iup{font-size:12pt;font-weight:bold;letter-spacing:.4pt;color:var(--warna)}
.judul{margin-top:7mm;font-size:24pt;font-weight:bold;letter-spacing:2pt;color:var(--warna)}
.anak-judul{font-size:10pt;letter-spacing:3pt;color:#55627a;margin-top:1mm}
.nomor{font-size:9pt;color:#55627a;margin-top:2mm}
.kepada{font-size:10pt;color:#44506a;margin-top:8mm}
.nama-peserta{font-size:22pt;font-weight:bold;margin-top:2mm;border-bottom:.4mm solid #c7cedb;display:inline-block;padding:0 10mm 2mm}
.rincian{margin:6mm auto 0;font-size:9.5pt;border-collapse:collapse}
.rincian td{padding:1mm 3mm;vertical-align:top}
.rincian .label,.rincian .titik{color:#55627a;text-align:right}
.rincian .nilai{font-weight:bold;text-align:left}
.pernyataan{width:210mm;margin:7mm auto 0;font-size:9.5pt;color:#44506a;line-height:1.6}
.kaki{position:absolute;left:18mm;right:18mm;top:162mm;display:flex;justify-content:space-between;align-items:flex-end}
.qr{text-align:center;width:40mm}
.qr img{width:24mm;height:24mm;margin:0 auto}
.qr .keterangan{font-size:6.5pt;color:#55627a;margin-top:1mm}
.ttd{text-align:center;width:70mm;font-size:9pt}
.ttd img{height:18mm;margin:0 auto}
.ttd .garis{border-top:.3mm solid #16233a;margin-top:1mm;padding-top:1mm;font-weight:bold}
.ttd .jabatan{color:#55627a;font-size:8pt}
.lembar-kosong{background:#fff;border:1px solid #e7e5e4;border-radius:12px;padding:48px;text-align:center;font-size:13px}
</style>

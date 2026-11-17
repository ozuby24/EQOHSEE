<script setup lang="ts">
/**
 * Sertifikat MCU — mcus/sertifikat-mcu.blade.php Safe Track, formulir
 * "Medical Check Up Certificate" Rev 02, A4 mendatar, satu halaman.
 *
 * Kopnya KopCetak — bentuk yang sama dengan seluruh laporan — atas nama
 * IUP penerbit (KopDokumen 'sertifikat-mcu'). SATU tanda tangan:
 * dokter — yang dinyatakan lembar ini penilaian medis. Tanda tangannya
 * QR, bukan gambar: memuat nomor, nama, penanda tangan, dan tautan
 * pemeriksaan. Stempel validasinya DIHITUNG dari datanya (TERVALIDASI,
 * KEDALUWARSA, TIDAK LAYAK KERJA, BELUM TERVALIDASI), bukan gambar yang
 * ikut tercetak pada MCU yang sudah habis.
 */
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import QRCode from 'qrcode';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';
import KopCetak from '../../Components/KopCetak.vue';

defineOptions({ layout: BlankLayout });

const props = defineProps<{ s: any; kembali?: string }>();

const qr = ref<string | null>(null);

onMounted(async () => {
  try {
    qr.value = await QRCode.toDataURL(String(props.s.qr), { margin: 1, width: 600, errorCorrectionLevel: 'M' });
  } catch { /* tanpa QR, lembarnya tetap dapat dicetak */ }
});

const REKOM = [
  ['fit', 'Fit'],
  ['fitnote', 'Fit With Note'],
  ['unfit', 'Unfit'],
] as const;
</script>

<template>
  <Head title="Medical Check Up Certificate" />

  <PrintShell mendatar title="Sertifikat MCU" :kembali="props.kembali">
    <div class="lembar-mcu">
      <template v-if="s.boleh">
        <KopCetak :dok="s.kop" />

        <table class="identitas">
          <tbody>
            <tr>
              <td class="lbl">Name</td><td class="sep">:</td><td class="isi">{{ s.nama }}</td><td class="sela"></td>
              <td class="lbl">Age</td><td class="sep">:</td><td class="isi-kanan">{{ s.usia }}</td><td></td>
            </tr>
            <tr>
              <td class="lbl">Occupation</td><td class="sep">:</td><td class="isi">{{ s.jabatan }}</td><td class="sela"></td>
              <td class="lbl">Date of MCU</td><td class="sep">:</td><td class="isi-kanan">{{ s.tglMcu }}</td><td></td>
            </tr>
            <tr>
              <td class="lbl">Department</td><td class="sep">:</td><td class="isi">{{ s.departemen }}</td><td class="sela"></td>
              <td class="lbl">Date of next MCU</td><td class="sep">:</td><td class="isi-kanan">{{ s.tglBerikut }}</td><td></td>
            </tr>
            <tr>
              <td class="lbl">Company</td><td class="sep">:</td><td class="isi">{{ s.perusahaan }}</td><td class="sela"></td>
              <td class="lbl">Status*</td><td class="sep">:</td><td class="isi-kanan">{{ s.status }}</td><td></td>
            </tr>
          </tbody>
        </table>
        <p class="catatan">* First / Periodic</p>

        <table class="rekom">
          <tbody>
            <tr><td class="kepala" colspan="2">Recomendation</td><td class="kepala">Remarks</td></tr>
            <tr v-for="([kunci, label], i) in REKOM" :key="kunci">
              <td class="kotak" :class="s.centang === kunci ? kunci : ''">{{ s.centang === kunci ? '✔' : '' }}</td>
              <td class="nilai" :class="s.centang === kunci ? kunci : ''">{{ label }}</td>
              <td v-if="i === 0" class="remarks" rowspan="3">
                <i>{{ s.catatan }}</i>
                <div v-if="s.hasil && !s.centang" style="color:#b91c1c;margin-top:3px;font-size:9px">
                  Hasil MCU <b>{{ s.hasil }}</b> — di luar tiga pilihan di kolom ini.
                </div>
              </td>
            </tr>
            <tr>
              <td colspan="2" rowspan="2">Level Risiko : {{ s.risiko }}</td>
              <td>Mine Permit&nbsp;&nbsp;&nbsp;: {{ s.permit }}</td>
            </tr>
            <tr><td>Mine License&nbsp;&nbsp;: {{ s.simper }}</td></tr>
          </tbody>
        </table>

        <table class="ttd">
          <tbody>
            <tr>
              <td style="width:34%" rowspan="4">
                <table class="stempel" :style="{ border: `3px double ${s.stempel.hex}` }">
                  <tbody>
                    <tr><td class="st-judul" :style="{ color: s.stempel.hex }">{{ s.stempel.judul }}</td></tr>
                    <tr><td class="st-garis" :style="{ borderTop: `1px solid ${s.stempel.hex}` }"></td></tr>
                    <tr>
                      <td class="st-rinci">
                        {{ s.stempel.nomor }} · {{ s.stempel.hasil }}<br>
                        Berlaku {{ s.stempel.tglMcu || '(belum diisi)' }} s/d {{ s.stempel.berlaku || '(belum diisi)' }}
                      </td>
                    </tr>
                    <tr v-if="!s.stempel.sah"><td class="st-alasan">{{ s.stempel.alasan }}</td></tr>
                    <tr><td class="st-cetak">Dicetak {{ s.stempel.dicetak }}</td></tr>
                  </tbody>
                </table>
              </td>
              <td style="width:32%">Examined &amp; Approved By,</td>
              <td style="width:34%"></td>
            </tr>
            <tr><td class="qr"><img v-if="qr" :src="qr" alt="QR tanda tangan dokter"></td><td></td></tr>
            <tr><td class="nama">{{ s.dokter || '(dokter belum diatur)' }}</td><td></td></tr>
            <tr><td>{{ s.sebutanDokter }}</td><td></td></tr>
          </tbody>
        </table>

        <p v-if="!s.dokter" class="catatan" style="text-align:center;color:#b91c1c">
          Dokter pemeriksa belum ditentukan pada skema IUP ini. Lengkapi di Miners → Alur Persetujuan sebelum sertifikat diterbitkan.
        </p>
      </template>

      <p v-else class="kosong">
        Sertifikat belum dapat dicetak.<br>
        Hasil MCU atas nama <b>{{ s.nama }}</b> belum disetujui.
      </p>
    </div>
  </PrintShell>
</template>

<style>
@media print { @page { size: A4 landscape; margin: 6mm 8mm; } }
</style>

<style scoped>
.lembar-mcu{background:#fff;color:#000;font-family:'DejaVu Sans',Arial,sans-serif;font-size:10px;padding:18px 22px;width:1050px;max-width:none;margin:0 auto;border:1px solid #e7e5e4}
@media print{.lembar-mcu{border:0;padding:0;width:auto}}
table{border-collapse:collapse;width:100%}
.identitas{margin-top:14px}
.identitas td{border:none;padding:3px 2px;font-size:10.5px}
.identitas .lbl{width:105px}
.identitas .sep{width:10px}
.identitas .isi{border-bottom:1px solid #000}
.identitas .sela{width:45px}
.identitas .isi-kanan{border-bottom:1px solid #000;width:190px}
.rekom{margin-top:14px}
.rekom,.rekom td{border:1px solid #000}
.rekom td{padding:4px 6px;font-size:10.5px}
.rekom .kepala{text-align:center}
.rekom .kotak{width:34px;text-align:center}
.rekom .nilai{width:190px}
.rekom .remarks{vertical-align:top}
.fit{background:#47ee8e}.fitnote{background:#f5f67c}.unfit{background:#fd3f3f}
.ttd{margin-top:14px}
.ttd td{border:none;padding:0;text-align:center;font-size:10.5px}
.ttd .qr{padding:6px 0 4px}
.ttd .qr img{width:96px;height:96px}
.ttd .nama{padding-top:2px;font-weight:bold}
.stempel{width:248px;margin:0 auto}
.stempel td{border:none;padding:0 9px;text-align:center}
.stempel .st-judul{font-size:11.5px;font-weight:bold;letter-spacing:1px;padding:4px 9px 2px}
.stempel .st-garis{padding:0;font-size:0;line-height:0}
.stempel .st-rinci{text-align:left;font-size:8px;line-height:1.35;padding-top:4px}
.stempel .st-alasan{text-align:left;font-size:7px;line-height:1.3;padding-top:3px;color:#333}
.stempel .st-cetak{font-size:6.5px;color:#555;padding:1px 9px 4px}
.catatan{font-size:9px;margin:4px 0 0}
.kosong{text-align:center;padding:60px 0;font-size:12px}
</style>

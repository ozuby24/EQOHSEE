<script setup lang="ts">
/**
 * Satu sisi kartu CR80 (54 × 86 mm tegak) — kartu/depan dan kartu/belakang
 * Safe Track. Isinya disiapkan App\Support\Miners\Kartu\DataKartu.
 *
 * Kartu adalah DOKUMEN, bukan layar: tata letaknya milimeter mutlak dan
 * mengikuti kartu contoh SOP, tidak memakai token tampilan Miners. Yang
 * diganti dari Safe Track hanya mereknya — identitas IUP penerbit dari
 * Master Data → Perusahaan — dan dua gambar aset (coretan non-SIMPER,
 * kotak pelanggaran) yang di sini digambar dengan CSS.
 */
import { onMounted, ref, watch } from 'vue';
import QRCode from 'qrcode';

const props = defineProps<{ k: Record<string, any>; sisi: 'depan' | 'belakang' }>();

const qr = ref<string | null>(null);

async function buatQr() {
  qr.value = null;
  if (props.sisi !== 'belakang' || !props.k?.verifikasi) return;
  try {
    qr.value = await QRCode.toDataURL(String(props.k.verifikasi), { margin: 0, width: 400, errorCorrectionLevel: 'L' });
  } catch { /* tanpa QR kartu tetap dapat dicetak */ }
}
onMounted(buatQr);
watch(() => props.k?.verifikasi, buatQr);

/* Isi data dikecilkan bertahap bila panjang (Safe Track: >20 dan >25 huruf),
   dipotong dengan elipsis hanya di atas 30 huruf. */
function kelasIsi(v: unknown) {
  const n = String(v ?? '').length;
  return n > 25 ? 'isi sangat-panjang' : n > 20 ? 'isi panjang' : 'isi';
}
function potong(v: unknown) {
  const s = String(v ?? '');
  return s.length > 30 ? `${s.slice(0, 29)}…` : s;
}

const pasangan = (unit: any[]) => {
  const hasil: any[][] = [];
  for (let i = 0; i < unit.length; i += 2) hasil.push([unit[i], unit[i + 1] ?? null]);
  return hasil;
};
const gayaUnit = (b: any) => (b && Number(b.ukuran) < 4.2 ? { fontSize: `${Number(b.ukuran).toFixed(2)}pt` } : undefined);
</script>

<template>
  <div v-if="props.sisi === 'depan'" class="kartu kartu-depan">
    <div class="kop">
      <img v-if="props.k.logo_grup" class="logo" :src="props.k.logo_grup" alt="">
      <div class="nama-iup">{{ props.k.nama_iup }}</div>
      <div class="akses">{{ props.k.akses }}</div>
    </div>

    <div class="pita-jenis" :class="`pita-${props.k.warna_akses}`" :style="{ background: props.k.hex_akses }">
      ID CARD – {{ props.k.jenis_kartu }}
    </div>

    <div class="nama-pemegang" :style="props.k.ukuran_nama < 8.4 ? { fontSize: `${props.k.ukuran_nama}pt` } : undefined">{{ props.k.nama }}</div>

    <div class="panel-foto" :style="{ background: props.k.warna }">
      <div v-if="props.k.foto" class="bingkai-foto"><img class="foto" :src="props.k.foto" alt=""></div>
      <div v-else class="tanpa-foto">FOTO BELUM DIUNGGAH</div>
    </div>

    <div class="data">
      <div v-for="[label, nilai] in [['NOMOR INDUK', props.k.nomor_induk], ['JABATAN', props.k.jabatan], ['PERUSAHAAN', props.k.perusahaan], ['BERLAKU ID CARD', props.k.berlaku]]"
           :key="label" class="baris-data">
        <span class="label">{{ label }}</span><span class="titik">:</span><span :class="kelasIsi(nilai)">{{ potong(nilai) }}</span>
      </div>
    </div>

    <div class="darurat">
      <div class="judul" :class="{ 'judul-saja': !props.k.channel }">EMERGENCY CALL</div>
      <div v-if="props.k.channel" class="channel">{{ props.k.channel }}</div>
    </div>
  </div>

  <div v-else class="kartu kartu-belakang">
    <div class="kop-simper">SIMPER</div>

    <div class="identitas">
      <div v-for="[label, nilai] in [['No. Register', props.k.no_register], ['No. SIM Pol / KTP', props.k.no_simpol], ['Exp. SIM Pol / KTP', props.k.exp_simpol]]"
           :key="label" class="baris-data">
        <span class="label">{{ label }}</span><span class="titik">:</span><span class="isi">{{ nilai || '-' }}</span>
      </div>
    </div>

    <div class="tabel-unit">
      <div class="judul">Recommended &amp; Authorized to Operate</div>
      <table>
        <thead><tr><th class="unit">Equipments</th><th class="kelas">Class</th><th class="unit">Equipments</th><th class="kelas">Class</th></tr></thead>
        <tbody>
          <tr v-for="(p, i) in pasangan(props.k.unit ?? [])" :key="i">
            <td class="unit" :style="gayaUnit(p[0])">{{ p[0]?.nama }}</td><td class="kelas">{{ p[0]?.kelas }}</td>
            <td class="unit" :style="gayaUnit(p[1])">{{ p[1]?.nama }}</td><td class="kelas">{{ p[1]?.kelas }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Mine Permit tanpa SIMPER: tabelnya tetap dicetak lalu dicoret (SOP). -->
    <div v-if="!props.k.punya_simper" class="coret" aria-hidden="true" />

    <div class="catatan">ID Card – Mine Permit dan SIMPER berlaku selama<br>yang bersangkutan bekerja di {{ props.k.nama_iup_pendek }}</div>

    <div class="legenda">
      <div class="judul">Class :</div>
      <div v-for="(arti, huruf) in props.k.arti_kelas" :key="huruf" class="baris">
        <span class="huruf">{{ huruf }}</span><span class="arti">: {{ props.k.nama_kelas?.[huruf] }}</span><span>= {{ arti }}</span>
      </div>
    </div>

    <div v-if="qr" class="verifikasi">
      <img :src="qr" alt="QR verifikasi">
      <div class="teks">Pindai untuk verifikasi</div>
    </div>

    <div class="pelanggaran">
      <div class="judul">PELANGGARAN</div>
      <div class="kotak-langgar"><span>1</span><span>2</span><span>3</span></div>
    </div>

    <div class="ktt">
      <div class="judul">Approved by,</div>
      <img v-if="props.k.ttd_ktt" :src="props.k.ttd_ktt" alt="">
      <div v-else class="kosong" />
      <div class="nama">{{ props.k.nama_ktt }}</div>
      <div class="jabatan">{{ props.k.jabatan_ktt }}</div>
    </div>
  </div>
</template>

<style scoped>
/* Ukuran dan letak mengikuti kartu/gaya Safe Track, milimeter mutlak. */
.kartu { position: relative; width: 54mm; height: 86mm; overflow: hidden; background: #fff; border-radius: 3mm;
  font-family: Montserrat, 'DejaVu Sans', Arial, sans-serif; color: #10243f; box-sizing: border-box;
  -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.kartu * { box-sizing: content-box; }
.baris-data { font-size: 5pt; line-height: 1.1; white-space: nowrap; }
.baris-data > span { display: inline-block; vertical-align: top; }
.baris-data .titik { width: 2mm; text-align: center; }
.baris-data .isi { font-weight: 700; letter-spacing: .1pt; }
.baris-data .isi.panjang { font-size: 4.6pt; letter-spacing: 0; }
.baris-data .isi.sangat-panjang { font-size: 4.2pt; letter-spacing: -.05pt; }

.kop { position: absolute; top: 0; left: 0; width: 54mm; height: 18mm; text-align: center; }
.kop .logo { height: 8.2mm; margin-top: 2.6mm; max-width: 40mm; object-fit: contain; }
.kop .nama-iup { font-size: 5.4pt; font-weight: 700; letter-spacing: .35pt; margin-top: .6mm; }
.kop .akses { font-size: 4.6pt; font-style: italic; color: #35507a; margin-top: .3mm; letter-spacing: .2pt; }
.pita-jenis { position: absolute; top: 18mm; left: 0; width: 54mm; height: 3.7mm; color: #fff; font-size: 6.4pt; font-weight: 700;
  letter-spacing: .55pt; text-align: center; padding-top: 1.15mm; }
.pita-putih { color: #0f2d5c; height: 3.2mm; border-top: .25mm solid #6b7785; border-bottom: .25mm solid #6b7785; }
.nama-pemegang { position: absolute; top: 24mm; left: 0; width: 54mm; height: 3.5mm; text-align: center; padding-top: 1.35mm;
  font-size: 8.4pt; font-weight: 700; color: #0f2d5c; letter-spacing: .2pt; white-space: nowrap; overflow: hidden; line-height: 1; }
.panel-foto { position: absolute; top: 30.2mm; left: 0; width: 54mm; height: 34.2mm; overflow: hidden; }
.bingkai-foto { position: absolute; bottom: 0; left: 14.3mm; width: 25.4mm; height: 34.2mm; overflow: hidden; }
.bingkai-foto .foto { width: 100%; height: 100%; object-fit: cover; object-position: center top; }
.tanpa-foto { position: absolute; top: 15.5mm; left: 0; width: 54mm; text-align: center; color: #fff; font-size: 5pt; letter-spacing: .3pt; }
.data { position: absolute; top: 64.4mm; left: 0; width: 48mm; height: 11.2mm; background: #123a76; color: #fff; padding: 1.4mm 2.5mm 0 3.5mm; }
.data .baris-data { padding: .25mm 0; }
.data .label { width: 19mm; color: #cddcf2; letter-spacing: .1pt; }
.darurat { position: absolute; bottom: 0; left: 0; width: 54mm; height: 7.6mm; background: #c8161d; color: #fff; text-align: center; }
.darurat .judul { font-size: 5.4pt; font-weight: 700; letter-spacing: .8pt; margin-top: 1.3mm; color: #ffdada; }
.darurat .judul-saja { margin-top: 2.4mm; }
.darurat .channel { font-size: 6.4pt; font-weight: 700; margin-top: .2mm; letter-spacing: .2pt; }

.kop-simper { position: absolute; top: 0; left: 0; width: 54mm; height: 4.8mm; background: #123a76; color: #fff; font-size: 9.4pt;
  font-weight: 700; letter-spacing: 2.2pt; text-align: center; padding-top: 2.1mm; line-height: 1; }
.identitas { position: absolute; top: 10.4mm; left: 3.5mm; width: 47mm; }
.identitas .baris-data { padding: .35mm 0; }
.identitas .label { width: 18.5mm; color: #4a5b73; }
.tabel-unit { position: absolute; top: 22.4mm; left: 3.5mm; width: 47mm; }
.tabel-unit .judul { background: #123a76; color: #fff; font-size: 4.6pt; font-weight: 700; text-align: center; padding: .35mm 0; }
.tabel-unit table { width: 47mm; border-collapse: collapse; table-layout: fixed; }
.tabel-unit th { background: #dce7f7; font-size: 4.2pt; font-weight: 700; text-align: center; border: .25mm solid #7d9bc6; padding: .3mm 0; }
.tabel-unit td { font-size: 4.2pt; height: 2.6mm; border: .25mm solid #7d9bc6; padding: 0 .8mm; white-space: nowrap; overflow: hidden; line-height: 2.6mm; }
.tabel-unit .unit { width: 35%; }
.tabel-unit .kelas { width: 15%; text-align: center; }
.coret { position: absolute; top: 29.6mm; left: 3.5mm; width: 47mm; height: 14.1mm;
  background: linear-gradient(to top right, transparent calc(50% - .3mm), #c8161d calc(50% - .3mm), #c8161d calc(50% + .3mm), transparent calc(50% + .3mm)); }
.catatan { position: absolute; top: 45.4mm; left: 3.5mm; width: 47mm; font-size: 4.2pt; line-height: 1.3; color: #33445e; text-align: center; }
.legenda { position: absolute; top: 51.4mm; left: 3.5mm; width: 33mm; }
.legenda .judul { font-size: 4.6pt; font-weight: 700; margin-bottom: .5mm; }
.legenda .baris { font-size: 3.8pt; line-height: 1.35; white-space: nowrap; padding: .1mm 0; }
.legenda .baris span { display: inline-block; vertical-align: top; }
.legenda .huruf { width: 2.6mm; font-weight: 700; }
.legenda .arti { width: 12mm; }
.verifikasi { position: absolute; top: 50.2mm; right: 3.5mm; width: 13mm; text-align: center; }
.verifikasi img { width: 13mm; height: 13mm; }
.verifikasi .teks { font-size: 3.2pt; line-height: 1.2; color: #5b6880; margin-top: .4mm; white-space: nowrap; }
.pelanggaran { position: absolute; top: 66.5mm; left: 3.5mm; width: 29mm; }
.pelanggaran .judul { font-size: 5.4pt; font-weight: 700; letter-spacing: .3pt; margin-bottom: 1.2mm; }
.kotak-langgar { display: flex; gap: 1mm; }
.kotak-langgar span { width: 6mm; height: 6mm; border: .3mm solid #10243f; font-size: 4pt; text-align: right; padding: .3mm; box-sizing: border-box; }
.ktt { position: absolute; top: 66.5mm; right: 3.5mm; width: 17mm; border: .25mm solid #9aa9c0; text-align: center; }
.ktt .judul { font-size: 4.2pt; font-weight: 700; border-bottom: .25mm solid #9aa9c0; padding: .5mm 0; }
.ktt img { height: 6.6mm; max-width: 16mm; margin: .7mm 0 .2mm; object-fit: contain; }
.ktt .kosong { height: 6.6mm; }
.ktt .nama { font-size: 4.2pt; font-weight: 700; line-height: 1.15; }
.ktt .jabatan { font-size: 3.8pt; line-height: 1.15; padding-bottom: .6mm; }
</style>

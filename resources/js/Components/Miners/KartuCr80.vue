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

/* Urutan legenda kelas tetap (kartu contoh SOP), bukan diulang dari kunci data. */
const KELAS = ['F', 'R', 'P', 'I'] as const;

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
  return n > 25 ? 'k-isi k-sangat-panjang' : n > 20 ? 'k-isi k-panjang' : 'k-isi';
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
  <div v-if="props.sisi === 'depan'" class="kcr kcr-depan">
    <div class="k-kop">
      <img v-if="props.k.logo_grup" class="k-logo" :src="props.k.logo_grup" alt="">
      <div class="k-nama-iup">{{ props.k.nama_iup }}</div>
      <div class="k-akses">{{ props.k.akses }}</div>
    </div>

    <div class="k-pita-jenis" :class="`k-pita-${props.k.warna_akses}`" :style="{ background: props.k.hex_akses }">
      ID CARD – {{ props.k.jenis_kartu }}
    </div>

    <div class="k-nama-pemegang" :style="props.k.ukuran_nama < 8.4 ? { fontSize: `${props.k.ukuran_nama}pt` } : undefined">{{ props.k.nama }}</div>

    <div class="k-panel-foto" :style="{ background: props.k.warna }">
      <div v-if="props.k.foto" class="k-bingkai-foto"><img class="k-foto" :src="props.k.foto" alt=""></div>
      <div v-else class="k-tanpa-foto">FOTO BELUM DIUNGGAH</div>
    </div>

    <div class="k-data">
      <div v-for="[label, nilai] in [['NOMOR INDUK', props.k.nomor_induk], ['JABATAN', props.k.jabatan], ['PERUSAHAAN', props.k.perusahaan], ['BERLAKU ID CARD', props.k.berlaku]]"
           :key="label" class="k-baris-data">
        <span class="k-label">{{ label }}</span><span class="k-titik">:</span><span :class="kelasIsi(nilai)">{{ potong(nilai) }}</span>
      </div>
    </div>

    <div class="k-darurat">
      <div class="k-judul" :class="{ 'k-judul-saja': !props.k.channel }">EMERGENCY CALL</div>
      <div v-if="props.k.channel" class="k-channel">{{ props.k.channel }}</div>
    </div>
  </div>

  <div v-else class="kcr kcr-belakang">
    <div class="k-kop-simper">SIMPER</div>

    <div class="k-identitas">
      <div v-for="[label, nilai] in [['No. Register', props.k.no_register], ['No. SIM Pol / KTP', props.k.no_simpol], ['Exp. SIM Pol / KTP', props.k.exp_simpol]]"
           :key="label" class="k-baris-data">
        <span class="k-label">{{ label }}</span><span class="k-titik">:</span><span class="k-isi">{{ nilai || '-' }}</span>
      </div>
    </div>

    <div class="k-tabel-unit">
      <div class="k-judul">Recommended &amp; Authorized to Operate</div>
      <!-- `lembar` + data-tabel-tetap: aturan tabel layar sempit (app.css)
           dan tabelPas.ts tidak boleh menyentuh tabel di muka kartu. -->
      <table class="lembar" data-tabel-tetap>
        <thead><tr><th class="k-unit">Equipments</th><th class="k-kelas">Class</th><th class="k-unit">Equipments</th><th class="k-kelas">Class</th></tr></thead>
        <tbody>
          <tr v-for="(p, i) in pasangan(props.k.unit ?? [])" :key="i">
            <td class="k-unit" :style="gayaUnit(p[0])">{{ p[0]?.nama }}</td><td class="k-kelas">{{ p[0]?.kelas }}</td>
            <td class="k-unit" :style="gayaUnit(p[1])">{{ p[1]?.nama }}</td><td class="k-kelas">{{ p[1]?.kelas }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Mine Permit tanpa SIMPER: tabelnya tetap dicetak lalu dicoret (SOP). -->
    <div v-if="!props.k.punya_simper" class="k-coret" aria-hidden="true" />

    <div class="k-catatan">ID Card – Mine Permit dan SIMPER berlaku selama<br>yang bersangkutan bekerja di {{ props.k.nama_iup_pendek }}</div>

    <div class="k-legenda">
      <div class="k-judul">Class :</div>
      <div v-for="huruf in KELAS" :key="huruf" class="k-baris">
        <span class="k-huruf">{{ huruf }}</span><span class="k-arti">: {{ props.k.nama_kelas?.[huruf] }}</span><span>= {{ props.k.arti_kelas?.[huruf] }}</span>
      </div>
    </div>

    <div v-if="qr" class="k-verifikasi">
      <img :src="qr" alt="QR verifikasi">
      <div class="k-teks">Pindai untuk verifikasi</div>
    </div>

    <div class="k-pelanggaran">
      <div class="k-judul">PELANGGARAN</div>
      <div class="k-kotak-langgar"><span>1</span><span>2</span><span>3</span></div>
    </div>

    <div class="k-ktt">
      <div class="k-judul">Approved by,</div>
      <img v-if="props.k.ttd_ktt" :src="props.k.ttd_ktt" alt="">
      <div v-else class="k-kosong" />
      <div class="nama">{{ props.k.nama_ktt }}</div>
      <div class="k-jabatan">{{ props.k.jabatan_ktt }}</div>
    </div>
  </div>
</template>

<style scoped>
/* Ukuran dan letak mengikuti kartu/gaya Safe Track, milimeter mutlak. */
.kcr { color-scheme: light; position: relative; width: 54mm; height: 86mm; overflow: hidden; background: #fff; border-radius: 3mm;
  font-family: Montserrat, 'DejaVu Sans', Arial, sans-serif; color: #10243f; box-sizing: border-box;
  -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.kcr * { box-sizing: content-box; }
.k-baris-data { font-size: 5pt; line-height: 1.1; white-space: nowrap; }
.k-baris-data > span { display: inline-block; vertical-align: top; }
.k-baris-data .k-titik { width: 2mm; text-align: center; }
.k-baris-data .k-isi { font-weight: 700; letter-spacing: .1pt; }
.k-baris-data .k-isi.k-panjang { font-size: 4.6pt; letter-spacing: 0; }
.k-baris-data .k-isi.k-sangat-panjang { font-size: 4.2pt; letter-spacing: -.05pt; }

.k-kop { position: absolute; top: 0; left: 0; width: 54mm; height: 18mm; text-align: center; }
.k-kop .k-logo { height: 8.2mm; margin-top: 2.6mm; max-width: 40mm; object-fit: contain; }
.k-kop .k-nama-iup { font-size: 5.4pt; font-weight: 700; letter-spacing: .35pt; margin-top: .6mm; }
.k-kop .k-akses { font-size: 4.6pt; font-style: italic; color: #35507a; margin-top: .3mm; letter-spacing: .2pt; }
.k-pita-jenis { position: absolute; top: 18mm; left: 0; width: 54mm; height: 3.7mm; color: #fff; font-size: 6.4pt; font-weight: 700;
  letter-spacing: .55pt; text-align: center; padding-top: 1.15mm; }
.k-pita-putih { color: #0f2d5c; height: 3.2mm; border-top: .25mm solid #6b7785; border-bottom: .25mm solid #6b7785; }
.k-nama-pemegang { position: absolute; top: 24mm; left: 0; width: 54mm; height: 3.5mm; text-align: center; padding-top: 1.35mm;
  font-size: 8.4pt; font-weight: 700; color: #0f2d5c; letter-spacing: .2pt; white-space: nowrap; overflow: hidden; line-height: 1; }
.k-panel-foto { position: absolute; top: 30.2mm; left: 0; width: 54mm; height: 34.2mm; overflow: hidden; }
.k-bingkai-foto { position: absolute; bottom: 0; left: 14.3mm; width: 25.4mm; height: 34.2mm; overflow: hidden; }
.k-bingkai-foto .k-foto { width: 100%; height: 100%; object-fit: cover; object-position: center top; }
.k-tanpa-foto { position: absolute; top: 15.5mm; left: 0; width: 54mm; text-align: center; color: #fff; font-size: 5pt; letter-spacing: .3pt; }
.k-data { position: absolute; top: 64.4mm; left: 0; width: 48mm; height: 11.2mm; background: #123a76; color: #fff; padding: 1.4mm 2.5mm 0 3.5mm; }
.k-data .k-baris-data { padding: .25mm 0; }
.k-data .k-label { width: 19mm; color: #cddcf2; letter-spacing: .1pt; }
.k-darurat { position: absolute; bottom: 0; left: 0; width: 54mm; height: 7.6mm; background: #c8161d; color: #fff; text-align: center; }
.k-darurat .k-judul { font-size: 5.4pt; font-weight: 700; letter-spacing: .8pt; margin-top: 1.3mm; color: #ffdada; }
.k-darurat .k-judul-saja { margin-top: 2.4mm; }
.k-darurat .k-channel { font-size: 6.4pt; font-weight: 700; margin-top: .2mm; letter-spacing: .2pt; }

.k-kop-simper { position: absolute; top: 0; left: 0; width: 54mm; height: 4.8mm; background: #123a76; color: #fff; font-size: 9.4pt;
  font-weight: 700; letter-spacing: 2.2pt; text-align: center; padding-top: 2.1mm; line-height: 1; }
.k-identitas { position: absolute; top: 10.4mm; left: 3.5mm; width: 47mm; }
.k-identitas .k-baris-data { padding: .35mm 0; }
.k-identitas .k-label { width: 18.5mm; color: #4a5b73; }
.k-tabel-unit { position: absolute; top: 22.4mm; left: 3.5mm; width: 47mm; }
.k-tabel-unit .k-judul { background: #123a76; color: #fff; font-size: 4.6pt; font-weight: 700; text-align: center; padding: .35mm 0;
  height: 2.5mm; box-sizing: border-box; line-height: 1.8mm; }
.k-tabel-unit table { width: 47mm; border-collapse: collapse; table-layout: fixed; }
.k-tabel-unit th { background: #dce7f7; font-size: 4.2pt; font-weight: 700; text-align: center; border: .25mm solid #7d9bc6; padding: 0;
  height: 2.6mm; line-height: 2.1mm; box-sizing: border-box; }
.k-tabel-unit td { white-space: nowrap !important; font-size: 4.2pt; height: 2.6mm; border: .25mm solid #7d9bc6; padding: 0 .8mm; white-space: nowrap; overflow: hidden; line-height: 2.1mm;
  box-sizing: border-box; }
/* Tinggi tabel unit dikunci (judul 2,5 + kepala 2,6 + 5 × 2,6 mm ≈ 18,3 mm,
   berakhir ± 40,7 mm), tidak bergantung tinggi baris bawaan peramban —
   sebelumnya tabel layar memanjang sampai 45,8 mm dan menimpa catatan kaki. */
.k-tabel-unit .k-unit { width: 35%; }
.k-tabel-unit .k-kelas { width: 15%; text-align: center; }
.k-coret { position: absolute; top: 27.5mm; left: 3.5mm; width: 47mm; height: 13.2mm;
  background: linear-gradient(to top right, transparent calc(50% - .3mm), #c8161d calc(50% - .3mm), #c8161d calc(50% + .3mm), transparent calc(50% + .3mm)); }
.k-catatan { position: absolute; top: 43.6mm; left: 3.5mm; width: 47mm; font-size: 4.2pt; line-height: 1.3; color: #33445e; text-align: center; }
.k-legenda { position: absolute; top: 51.4mm; left: 3.5mm; width: 33mm; }
.k-legenda .k-judul { font-size: 4.6pt; font-weight: 700; margin-bottom: .5mm; }
.k-legenda .k-baris { font-size: 3.8pt; line-height: 1.35; white-space: nowrap; padding: .1mm 0; }
.k-legenda .k-baris span { display: inline-block; vertical-align: top; }
.k-legenda .k-huruf { width: 2.6mm; font-weight: 700; }
.k-legenda .k-arti { width: 12mm; }
.k-verifikasi { position: absolute; top: 50.2mm; right: 3.5mm; width: 13mm; text-align: center; }
.k-verifikasi img { width: 13mm; height: 13mm; }
.k-verifikasi .k-teks { font-size: 3.2pt; line-height: 1.2; color: #5b6880; margin-top: .4mm; white-space: nowrap; }
.k-pelanggaran { position: absolute; top: 66.5mm; left: 3.5mm; width: 29mm; }
.k-pelanggaran .k-judul { font-size: 5.4pt; font-weight: 700; letter-spacing: .3pt; margin-bottom: 1.2mm; }
.k-kotak-langgar { display: flex; gap: 1mm; }
.k-kotak-langgar span { width: 6mm; height: 6mm; border: .3mm solid #10243f; font-size: 4pt; text-align: right; padding: .3mm; box-sizing: border-box; }
.k-ktt { position: absolute; top: 66.5mm; right: 3.5mm; width: 17mm; border: .25mm solid #9aa9c0; text-align: center; }
.k-ktt .k-judul { font-size: 4.2pt; font-weight: 700; border-bottom: .25mm solid #9aa9c0; padding: .5mm 0; }
.k-ktt img { height: 6.6mm; max-width: 16mm; margin: .7mm 0 .2mm; object-fit: contain; }
.k-ktt .k-kosong { height: 6.6mm; }
.k-ktt .nama { font-size: 4.2pt; font-weight: 700; line-height: 1.15; }
.k-ktt .k-jabatan { font-size: 3.8pt; line-height: 1.15; padding-bottom: .6mm; }
</style>

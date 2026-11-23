<script setup lang="ts">
/*
  Replika scafftag fisik — kartu yang dimasukkan ke holder di kaki
  tangga perancah.

  Tata letaknya mengikuti kartu industri yang dikenal pekerja: kepala
  berwarna tebal dengan perintah singkat (LAYAK / LAYAK BERSYARAT / TIDAK LAYAK),
  SWL besar, nomor seri, masa berlaku, inspektur, syarat atau alasan
  penolakan, dan QR yang membuka halaman verifikasi tanpa masuk. Kartu
  merah tidak mencetak masa berlaku — ia tidak berlaku sampai diganti.
*/
import { onMounted, ref, watch } from 'vue';
import QRCode from 'qrcode';
import { waktu } from '../../perancah';

const props = defineProps<{ tag: any; inspektur?: string | null; sertifikat?: string | null }>();

const qr = ref('');
async function buatQr() {
  qr.value = props.tag?.verifikasi
    ? await QRCode.toDataURL(props.tag.verifikasi, { margin: 1, width: 220, errorCorrectionLevel: 'M', color: { dark: '#111111', light: '#FFFFFF' } })
    : '';
}
onMounted(buatQr);
watch(() => props.tag?.verifikasi, buatQr);

const KEPALA: Record<string, { judul: string; perintah: string }> = {
  hijau:  { judul: 'SCAFFOLD SAFE FOR USE', perintah: 'LAYAK DIGUNAKAN' },
  kuning: { judul: 'CAUTION — RESTRICTED', perintah: 'LAYAK DIGUNAKAN DENGAN SYARAT' },
  merah:  { judul: 'DANGER — DO NOT USE', perintah: 'TIDAK LAYAK DIGUNAKAN' },
};
</script>

<template>
  <article class="kt" :class="`kt-${tag.warna}`">
    <div class="kt-lubang" aria-hidden="true" />
    <header class="kt-kepala">
      <svg viewBox="0 0 24 24" class="kt-tanda" aria-hidden="true">
        <path v-if="tag.warna === 'hijau'" d="M12 2 4 5v6c0 5 3.4 9.4 8 11 4.6-1.6 8-6 8-11V5Zm-1.2 14.2-4-4 1.6-1.6 2.4 2.4 5-5 1.6 1.6Z" fill="currentColor" />
        <path v-else d="M12 2 1 21h22Zm-1 7h2v6h-2Zm0 8h2v2h-2Z" fill="currentColor" />
      </svg>
      <div>
        <p class="kt-judul">{{ KEPALA[tag.warna]?.judul ?? KEPALA.merah.judul }}</p>
        <p class="kt-perintah">{{ tag.kedaluwarsa ? 'TIDAK LAYAK DIGUNAKAN · KEDALUWARSA' : (KEPALA[tag.warna]?.perintah ?? KEPALA.merah.perintah) }}</p>
      </div>
    </header>

    <div class="kt-badan">
      <div class="kt-baris2">
        <div><p class="kt-lbl">No. tag</p><p class="kt-isi num">{{ tag.kode }}</p></div>
        <div><p class="kt-lbl">Unit</p><p class="kt-isi num">{{ tag.unit?.kode }}</p></div>
      </div>

      <div v-if="tag.warna !== 'merah'" class="kt-swl">
        <p class="kt-lbl">Beban kerja aman (SWL)</p>
        <p class="kt-swl-angka num">{{ tag.swl }}<small> kg/m²</small></p>
        <p class="kt-lbl">Maks. {{ tag.unit?.dek ?? 1 }} dek kerja bersamaan</p>
      </div>
      <div v-else class="kt-tolak">
        <p class="kt-lbl">Alasan</p>
        <p class="kt-tolak-isi">{{ tag.alasan || 'Tidak layak digunakan sampai diinspeksi dan diberi tag baru.' }}</p>
      </div>

      <dl class="kt-data">
        <div><dt>Lokasi</dt><dd>{{ tag.unit?.zona }}<template v-if="tag.unit?.peralatan"> · {{ tag.unit.peralatan }}</template></dd></div>
        <div><dt>Pekerjaan</dt><dd>{{ tag.unit?.judul }}</dd></div>
        <div><dt>Dimensi</dt><dd>{{ tag.unit?.dimensi }} · {{ tag.unit?.jenis }}</dd></div>
        <div v-if="tag.unit?.izin"><dt>Izin kerja</dt><dd class="num">{{ tag.unit.izin }}</dd></div>
        <div><dt>Diterbitkan</dt><dd>{{ waktu(tag.terbit_at) }}</dd></div>
        <div v-if="tag.warna !== 'merah'"><dt>Berlaku s.d.</dt><dd class="font-extrabold">{{ waktu(tag.kedaluwarsa_at) }}</dd></div>
        <div><dt>Inspektur</dt><dd>{{ inspektur || tag.oleh }}<template v-if="sertifikat"> · {{ sertifikat }}</template></dd></div>
        <div v-if="tag.unit?.lat"><dt>Koordinat</dt><dd class="num">{{ Number(tag.unit.lat).toFixed(6) }}, {{ Number(tag.unit.lng).toFixed(6) }}</dd></div>
      </dl>

      <div v-if="tag.syarat" class="kt-syarat"><p class="kt-lbl">Syarat penggunaan</p><p>{{ tag.syarat }}</p></div>

      <div class="kt-kaki">
        <img v-if="qr" :src="qr" alt="QR verifikasi scafftag" class="kt-qr">
        <p>Pindai untuk memeriksa tag yang berlaku saat ini. Kartu fisik dapat sudah diganti.</p>
      </div>
    </div>
  </article>
</template>

<style scoped>
.kt { --k: #15803D; --km: #DCFCE7; position: relative; width: 100%; max-width: 360px; margin: 0 auto; border-radius: 18px;
  background: #fff; border: 3px solid var(--k); overflow: hidden; color: #1C1917; box-shadow: 0 22px 44px -26px rgba(0,0,0,.55); }
.kt-kuning { --k: #E0A100; --km: #FEF3C7; }
.kt-merah { --k: #DC2626; --km: #FEE2E2; }
.kt-lubang { position: absolute; top: 10px; left: 50%; width: 46px; height: 12px; margin-left: -23px; border-radius: 999px;
  background: #fff; border: 2px solid rgba(0,0,0,.15); z-index: 2; }
.kt-kepala { display: flex; gap: 12px; align-items: center; padding: 34px 18px 16px; background: var(--k); color: #fff; }
.kt-kuning .kt-kepala { color: #1C1917; background: repeating-linear-gradient(-45deg, #F5B800 0 16px, #E0A100 16px 32px); }
.kt-merah .kt-kepala { background: repeating-linear-gradient(-45deg, #DC2626 0 16px, #B91C1C 16px 32px); }
.kt-tanda { width: 44px; height: 44px; flex: none; }
.kt-judul { font-size: 19px; font-weight: 900; letter-spacing: .01em; line-height: 1.1; }
.kt-perintah { font-size: 11.5px; font-weight: 800; margin-top: 3px; opacity: .92; }
.kt-badan { padding: 14px 18px 16px; }
.kt-baris2 { display: grid; grid-template-columns: 1.4fr 1fr; gap: 10px; }
.kt-lbl { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: #78716C; }
.kt-isi { font-size: 13.5px; font-weight: 800; margin-top: 1px; }
.kt-swl { margin-top: 12px; padding: 10px 12px; border-radius: 12px; background: var(--km); text-align: center; }
.kt-swl-angka { font-size: 38px; font-weight: 900; line-height: 1.05; color: var(--k); }
.kt-kuning .kt-swl-angka { color: #92400E; }
.kt-swl-angka small { font-size: 14px; font-weight: 800; }
.kt-tolak { margin-top: 12px; padding: 10px 12px; border-radius: 12px; background: var(--km); }
.kt-tolak-isi { font-size: 12.5px; font-weight: 700; color: #7F1D1D; margin-top: 2px; }
.kt-data { margin-top: 12px; display: grid; gap: 5px; }
.kt-data div { display: grid; grid-template-columns: 82px 1fr; gap: 8px; font-size: 12px; }
.kt-data dt { color: #78716C; font-weight: 600; }
.kt-data dd { font-weight: 700; }
.kt-syarat { margin-top: 10px; padding: 9px 11px; border-radius: 10px; border: 1.5px dashed var(--k); font-size: 12px; font-weight: 600; }
.kt-kaki { display: flex; gap: 12px; align-items: center; margin-top: 12px; padding-top: 12px; border-top: 1px solid #E7E5E4; }
.kt-qr { width: 92px; height: 92px; flex: none; border-radius: 8px; border: 1px solid #E7E5E4; }
.kt-kaki p { font-size: 11px; color: #57534E; }

@media print {
  .kt { box-shadow: none; max-width: 90mm; }
}
</style>

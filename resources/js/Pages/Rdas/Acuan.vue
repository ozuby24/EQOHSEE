<script setup lang="ts">
/*
  Ringkasan regulasi & pedoman Rehabilitasi DAS — dirangkum dari bahan
  Bimtek BPDAS Mahakam Berau (November 2026) supaya petugas lapangan tidak
  perlu membuka paparan untuk mengingat angka-angka yang menentukan.
*/
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Ikon from '../../Components/Energi/Ikon.vue';

const props = propHalaman();
const a = computed(() => props.pilihan?.ambang ?? {});
const dasar = [
  ['UU 41/1999', 'Kehutanan'], ['PP 26/2020', 'Rehabilitasi dan Reklamasi Hutan'],
  ['Permen LHK 7/2021', 'Perencanaan kehutanan, perubahan peruntukan & fungsi, penggunaan kawasan hutan'],
  ['Permen LHK P.59/2019', 'Penanaman dalam rangka Rehabilitasi DAS'], ['Permenhut P.60/2009', 'Pedoman penilaian reklamasi hutan'],
  ['SE MenLHK 1/2024', 'Penyusunan rencana umum reklamasi hutan'], ['Permen LHK 10/2022', 'RURHL DAS & rencana tahunan RHL (arahan lokasi)'],
];
const langkah = [
  ['map-pin', 'Pengajuan & penetapan lokasi', 'PPKH dapat mengajukan/memilih lokasi sendiri; pembahasan para pihak; SK Penlok ditetapkan Menteri. Matriks prioritas: layak biofisik & sosial (RU-RHL DAS), bebas tumpang tindih perizinan, potensi keberlanjutan.'],
  ['clipboard-check', 'Rencana Kegiatan Penanaman (RKP)', 'Disusun, dinilai, dan disahkan PPKH; disupervisi BPDAS/pemangku. Pola & jenis mengacu kondisi tapak dan rencana kelola pemangku.'],
  ['sprout', 'Penanaman P0', 'Pembersihan lahan, PLTB, ajir, penanaman — melibatkan masyarakat sekitar.'],
  ['leaf', 'Pemeliharaan P1 & P2', 'P1: penyulaman, pendangiran, pemupukan. P2: memastikan persen tumbuh memenuhi standar. Pengamanan dari karhutla di seluruh fase sampai serah terima.'],
  ['clipboard-check', 'Penilaian keberhasilan', 'Usia tanaman minimal 36 bulan, keberhasilan ≥ 75%. Diawali self-declare geotagging 100% tanaman tanpa duplikasi. Tim dibentuk Direktur TKTRH, diketuai Kepala BPDAS.'],
  ['file-check-2', 'Serah terima', 'Hasil tanaman diserahkan kepada pemangku/pengelola kawasan; dapat bertahap (parsial).'],
];
</script>

<template>
  <Head title="Regulasi & Pedoman Rehab DAS" />
  <div class="space-y-4">
    <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
      <Kartu judul="Kewajiban pokok" ikon="file-text" warna="hijau">
        <ul class="space-y-2.5 text-[12.5px]">
          <li class="ac-poin"><b>Luas kewajiban minimal 1 : 1</b> luas PPKH, didasarkan pada luas Penetapan Batas Areal Kerja (PAK).</li>
          <li class="ac-poin"><b>Waktu penyelesaian</b> ½ N untuk PPKH di atas 5 tahun, ½ N + 1 tahun untuk PPKH 5 tahun atau kurang (N = jangka waktu PPKH).</li>
          <li class="ac-poin"><b>Seluruh biaya</b> pelaksanaan tahapan pemenuhan kewajiban dibebankan kepada pemegang PPKH.</li>
          <li class="ac-poin"><b>Berakhirnya PPKH</b> tidak membebaskan pemegang izin dari utang kewajiban penanaman Rehab DAS.</li>
          <li class="ac-poin"><b>Pelaporan</b> tertib dan sesuai kondisi lapangan; layanan daring SICERDAS & SINERGI.</li>
        </ul>
      </Kartu>
      <Kartu judul="Angka yang menentukan" ket="Dipakai modul ini untuk menghitung status secara otomatis" ikon="ruler" warna="biru">
        <div class="grid gap-2 grid-cols-2 md:grid-cols-4">
          <div class="ac-angka"><b class="num">{{ a.tumbuh_min }}%</b><span>persen tumbuh minimal saat dinilai</span></div>
          <div class="ac-angka"><b class="num">{{ a.usia_min_bulan }} bln</b><span>usia tanaman minimal</span></div>
          <div class="ac-angka"><b class="num">{{ a.kerapatan }}</b><span>batang/ha (jarak 4 × 4 m)</span></div>
          <div class="ac-angka"><b class="num">{{ a.sehat_ha_min }}</b><span>batang sehat/ha minimal</span></div>
          <div class="ac-angka"><b class="num">{{ a.is_pct }}%</b><span>intensitas sampling Petak Ukur</span></div>
          <div class="ac-angka"><b class="num">100%</b><span>tanaman tergeotag, tanpa duplikasi</span></div>
          <div class="ac-angka"><b class="num">≥ {{ a.lokal_min }}%</b><span>jenis lokal berdaur panjang</span></div>
          <div class="ac-angka"><b class="num">≤ {{ a.cepat_maks }}%</b><span>jenis cepat tumbuh</span></div>
        </div>
      </Kartu>
    </section>

    <Kartu judul="Tahapan penyelesaian kewajiban" ikon="git-branch" warna="hijau">
      <ol class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
        <li v-for="(l, i) in langkah" :key="i" class="ac-langkah">
          <span class="ac-no"><Ikon :nama="l[0]" :ukuran="16" /></span>
          <p class="text-[12.5px] font-extrabold text-cam-ink mt-2">{{ i + 1 }}. {{ l[1] }}</p>
          <p class="text-[11.5px] text-stone-600 mt-1">{{ l[2] }}</p>
        </li>
      </ol>
    </Kartu>

    <section class="grid gap-4 xl:grid-cols-2">
      <Kartu judul="Sanksi atas ketidakpatuhan" ikon="gavel" warna="merah">
        <ol class="space-y-2 text-[12.5px]">
          <li class="ac-sanksi" style="--w:#FDE68A"><b>Tahap 1</b> Teguran tertulis oleh Kepala BPDAS — maksimal 3× berturut-turut, jarak maksimal 30 hari kerja per teguran.</li>
          <li class="ac-sanksi" style="--w:#FDBA74"><b>Tahap 2</b> Rekomendasi pembatalan lokasi rehabilitasi DAS kepada Dirjen PDASRH.</li>
          <li class="ac-sanksi" style="--w:#F87171"><b>Tahap 3</b> Rekomendasi pencabutan atau pembatalan PPKH sepenuhnya kepada Dirjen Planologi Kehutanan.</li>
          <li class="ac-sanksi" style="--w:#B91C1C"><b>Tahap 4</b> Rekomendasi penindakan hukum tegas kepada Dirjen Penegakan Hukum Kehutanan.</li>
        </ol>
      </Kartu>
      <Kartu judul="Jika tanaman terdampak kebakaran" ket="Kewajiban PPKH tidak gugur oleh musibah kebakaran" ikon="flame" warna="oranye">
        <div class="grid gap-2 md:grid-cols-3">
          <div class="ac-exit"><b>Exit strategy 1</b><span>Pemeliharaan lanjutan (penanaman ulang)</span></div>
          <div class="ac-exit"><b>Exit strategy 2</b><span>Pengusulan relokasi</span></div>
          <div class="ac-exit"><b>Exit strategy 3</b><span>Serah terima bertahap (parsial)</span></div>
        </div>
        <ul class="mt-3 space-y-1.5 text-[12px]">
          <li class="ac-poin">Siapkan regu pengendalian karhutla yang kompeten dan sarpras yang berfungsi.</li>
          <li class="ac-poin">Dokumentasikan kejadian dan buat Berita Acara kejadian.</li>
          <li class="ac-poin">Laporkan ke BPDAS setempat dan koordinasikan dengan Dinas & pemangku di tingkat tapak.</li>
        </ul>
      </Kartu>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
      <Kartu judul="Penilaian reklamasi hutan (kewajiban saudara)" ket="Permenhut P.60/2009 · syarat mutlak perpanjangan & pengembalian PPKH" ikon="mountain" warna="toska">
        <div class="grid gap-2 md:grid-cols-3 text-[12px]">
          <div class="ac-bobot"><b>Penataan lahan · bobot 20</b><span>Penutupan lubang bekas tambang, penataan permukaan, stabilisasi lereng, penaburan tanah pucuk.</span></div>
          <div class="ac-bobot"><b>Erosi, sedimentasi & pencemaran air · bobot 30</b><span>Bangunan konservasi, cover crop, muatan sedimen, baku mutu air limbah.</span></div>
          <div class="ac-bobot"><b>Revegetasi · bobot 50</b><span>Luas tanam, persen tumbuh, 625 btg/ha, lokal ≥ 40% & cepat tumbuh ≤ 60%, kesehatan tanaman.</span></div>
        </div>
        <p class="text-[12px] mt-3"><span class="pr-pil pr-hijau">80–100 Baik — berhasil</span> <span class="pr-pil pr-kuning">60–79 Sedang</span> <span class="pr-pil pr-merah">&lt; 60 Buruk</span></p>
      </Kartu>
      <Kartu judul="Dasar hukum" ikon="book-open" warna="ungu">
        <ul class="space-y-1.5 text-[12px]">
          <li v-for="d in dasar" :key="d[0]" class="flex gap-2"><b class="text-cam-ink shrink-0 w-36">{{ d[0] }}</b><span class="text-stone-600">{{ d[1] }}</span></li>
        </ul>
        <Link href="/rehab-das" class="eq-btn-lain mt-4 inline-flex">Kembali ke dashboard</Link>
      </Kartu>
    </section>
  </div>
</template>

<style scoped>
.ac-poin { padding-left: 14px; position: relative; color: #44403C; }
.ac-poin::before { content: ""; position: absolute; left: 0; top: 7px; width: 6px; height: 6px; border-radius: 999px; background: #3F9A4E; }
.ac-angka { border-radius: 14px; background: #F0FDF4; border: 1px solid #BBF7D0; padding: 10px 12px; display: grid; gap: 2px; }
.ac-angka b { font-size: 22px; font-weight: 900; color: #14532D; } .ac-angka span { font-size: 11px; color: #3F6212; }
.ac-langkah { border-radius: 16px; background: #FAFAF9; border: 1px solid #F0EFED; padding: 12px; }
.ac-no { width: 32px; height: 32px; border-radius: 10px; display: grid; place-items: center; background: linear-gradient(135deg, #5C4326, #3F9A4E); color: #fff; }
.ac-sanksi { border-left: 6px solid var(--w); padding: 6px 10px; border-radius: 8px; background: #FAFAF9; color: #44403C; }
.ac-sanksi b { margin-right: 6px; }
.ac-exit { border-radius: 14px; background: #FFF7ED; border: 1px solid #FED7AA; padding: 10px 12px; display: grid; gap: 2px; }
.ac-exit b { font-size: 11px; color: #C2410C; } .ac-exit span { font-size: 12.5px; font-weight: 700; color: #431407; }
.ac-bobot { border-radius: 14px; background: #F0FDFA; border: 1px solid #99F6E4; padding: 10px 12px; display: grid; gap: 4px; }
.ac-bobot b { color: #115E59; } .ac-bobot span { color: #134E4A; }
:root[data-tema="gelap"] .ac-poin { color: #D5DDE1; }
:root[data-tema="gelap"] .ac-angka { background: #12271D; border-color: #1F4D33; } :root[data-tema="gelap"] .ac-angka b, :root[data-tema="gelap"] .ac-angka span { color: #8FE3BE; }
:root[data-tema="gelap"] .ac-langkah, :root[data-tema="gelap"] .ac-sanksi { background: #20272B; border-color: #2E373C; color: #D5DDE1; }
:root[data-tema="gelap"] .ac-exit { background: #3D2410; border-color: #7C2D12; } :root[data-tema="gelap"] .ac-exit b, :root[data-tema="gelap"] .ac-exit span { color: #FDBA74; }
:root[data-tema="gelap"] .ac-bobot { background: #0F2A28; border-color: #134E4A; } :root[data-tema="gelap"] .ac-bobot b, :root[data-tema="gelap"] .ac-bobot span { color: #99F6E4; }
</style>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import LapanganLayout from '../../Layouts/LapanganLayout.vue';
import IkonLapangan from '../../Components/IkonLapangan.vue';
import BilahLuring from '../../Components/BilahLuring.vue';
import { kabar } from '../../lapangan/kabar';

defineOptions({ layout: LapanganLayout });

type Sertifikat = { id: number; nomor: string; nama: string; kursus: string; nilai: number | null; terbit: string | null; penanda: string | null; verifikasi: string | null; unduh: string };

const p = defineProps<{
  saya: { nama: string; jabatan: string | null; perusahaan: string | null };
  sertifikat: Sertifikat[];
  berjalan: { judul: string; progress: number; url: string | null }[];
}>();

/* QR digambar di perangkat, bukan diambil dari server: sertifikatnya
   tetap dapat ditunjukkan di gerbang site walau sinyal tidak ada. */
const qr = ref<Record<number, string>>({});
onMounted(async () => {
  for (const s of p.sertifikat) {
    if (!s.verifikasi) continue;
    try {
      qr.value[s.id] = await QRCode.toDataURL(s.verifikasi, { margin: 0, width: 240, errorCorrectionLevel: 'M', color: { dark: '#0B1117', light: '#FFFFFF' } });
    } catch { /* tanpa QR, tautan teksnya tetap ada */ }
  }
});

const ke = ref(0);
const geser = ref<HTMLElement | null>(null);
function gulir() {
  const g = geser.value;
  if (g) ke.value = Math.round(g.scrollLeft / Math.max(1, g.clientWidth));
}

const pendek = (u: string | null) => (u ? u.replace(/^https?:\/\//, '') : '');

async function bagikan(s: Sertifikat) {
  const url = s.verifikasi ?? s.unduh;
  const data = { title: `Sertifikat ${s.kursus}`, text: `Sertifikat ${s.nomor} — ${s.nama}`, url };
  try {
    if (navigator.share) { await navigator.share(data); return; }
    await navigator.clipboard.writeText(url);
    kabar('Tautan verifikasi disalin.');
  } catch { /* dibatalkan */ }
}
</script>

<template>
  <Head title="Sertifikat" />

  <div class="lp-bilah">
    <Link href="/lapangan/profil" class="lp-ikon-tombol" aria-label="Kembali"><IkonLapangan nama="kiri" :tebal="2.2" /></Link>
    <h1 class="lp-bilah-judul" style="margin:0">Sertifikat</h1>
    <span v-if="sertifikat.length > 1" class="lp-mono hitung">{{ ke + 1 }} dari {{ sertifikat.length }}</span>
  </div>
  <BilahLuring />

  <div v-if="!sertifikat.length" class="lp-kosong">
    <strong>Belum ada sertifikat</strong>Sertifikat terbit otomatis saat kursus di LMS selesai dan lulus.
  </div>

  <div v-else ref="geser" class="geser" @scroll.passive="gulir">
    <div v-for="s in sertifikat" :key="s.id" class="slot">
      <article class="kartu">
        <header>
          <span class="merek"><img src="/brand/eqohsee-mark-128.png" alt="" width="24" height="24"><span>E<b>Q</b>OHSEE</span></span>
          <span class="lp-mono nomor">{{ s.nomor }}</span>
        </header>
        <div class="garis-jingga"></div>
        <div class="isi">
          <div class="lp-mono label-k">SERTIFIKAT KOMPETENSI · DIBERIKAN KEPADA</div>
          <div class="nama">{{ s.nama }}</div>
          <div class="sub">{{ [saya.jabatan, saya.perusahaan].filter(Boolean).join(' · ') }}</div>
          <hr>
          <div class="lp-mono label-k">TELAH MENYELESAIKAN</div>
          <div class="kursus">{{ s.kursus }}</div>
          <div class="angka">
            <div><div class="lp-mono label-k">NILAI AKHIR</div><div class="angka-n">{{ s.nilai ?? '—' }}</div></div>
            <div><div class="lp-mono label-k">TERBIT</div><div class="angka-n">{{ s.terbit ?? '—' }}</div></div>
          </div>
          <hr>
          <div class="verif">
            <div class="qr"><img v-if="qr[s.id]" :src="qr[s.id]" :alt="`Kode QR verifikasi ${s.nomor}`"><span v-else class="qr-kosong"></span></div>
            <div class="verif-isi">
              <div class="verif-judul">Pindai untuk memeriksa keaslian</div>
              <a v-if="s.verifikasi" :href="s.verifikasi" class="lp-mono verif-url" target="_blank" rel="noopener">{{ pendek(s.verifikasi) }}</a>
              <div class="verif-ket">Siapa pun dapat memeriksa tanpa masuk ke EQOHSEE.</div>
            </div>
          </div>
        </div>
        <footer v-if="s.penanda"><IkonLapangan nama="perisai" :tebal="2" />Terverifikasi · ditandatangani {{ s.penanda }}</footer>
      </article>
      <div class="aksi">
        <a :href="s.unduh" class="lp-tombol garis kecil"><IkonLapangan nama="unduh" :tebal="2" />Buka / cetak</a>
        <button type="button" class="lp-tombol kecil" @click="bagikan(s)"><IkonLapangan nama="bagikan" :tebal="2" />Bagikan</button>
      </div>
    </div>
  </div>

  <section v-if="berjalan.length">
    <div class="lp-bagian"><h2>Kursus berjalan</h2></div>
    <component :is="k.url ? 'a' : 'div'" v-for="k in berjalan" :key="k.judul" :href="k.url ?? undefined" class="lp-kartu kursus-baris">
      <span class="lp-petak nada-biru"><IkonLapangan nama="sertifikat" /></span>
      <div class="kursus-isi">
        <div class="lp-mono lp-baris-kode">KURSUS BERJALAN</div>
        <div class="kursus-judul">{{ k.judul }}</div>
        <div class="lp-rel" style="margin-top:8px;height:5px"><span :style="{ width: k.progress + '%', background: 'var(--ink)' }"></span></div>
      </div>
      <span class="lp-mono kursus-persen">{{ k.progress }}%</span>
    </component>
  </section>
</template>

<style scoped>
.hitung { font-size: 12px; color: var(--abu2); padding-right: 8px; }
.geser { display: flex; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; padding-bottom: 4px; }
.geser::-webkit-scrollbar { display: none; }
.slot { flex: none; width: 100%; padding: 4px 20px 0; scroll-snap-align: start; }
.kartu { background: #FFFFFF; border: 1px solid var(--garis); border-radius: 18px; overflow: hidden; box-shadow: 0 18px 40px -28px rgba(11, 17, 23, .4); }
header { background: var(--ink); padding: 15px 18px; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.merek { display: flex; align-items: center; gap: 8px; font-weight: 800; font-size: 14px; color: #FFFFFF; }
.merek img { height: 24px; width: auto; display: block; }
.merek b { color: #FF9800; font-weight: 800; }
.nomor { font-size: 11px; color: rgba(255, 255, 255, .74); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.garis-jingga { height: 3px; background: var(--sinyal); }
.isi { padding: 18px 18px 0; }
.label-k { font-size: 10.5px; letter-spacing: .06em; color: var(--abu3); }
.nama { font-size: 27px; line-height: 1.05; font-weight: 700; font-stretch: 88%; letter-spacing: -.02em; color: var(--ink); margin-top: 7px; overflow-wrap: anywhere; }
.sub { font-size: 13px; color: var(--abu2); margin-top: 4px; }
hr { border: 0; height: 1px; background: var(--garis); margin: 16px 0; }
.kursus { font-size: 17px; font-weight: 700; line-height: 1.3; color: var(--ink); margin-top: 6px; text-wrap: pretty; }
.angka { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin-top: 14px; }
.angka-n { font-size: 16px; font-weight: 700; color: var(--ink); margin-top: 2px; }
.verif { display: flex; gap: 16px; align-items: center; padding-bottom: 18px; }
.qr { padding: 6px; border: 1px solid var(--garis); border-radius: 10px; flex: none; background: #FFFFFF; }
.qr img, .qr-kosong { width: 96px; height: 96px; display: block; }
.qr-kosong { background: var(--garis3); border-radius: 4px; }
.verif-isi { min-width: 0; }
.verif-judul { font-size: 14px; font-weight: 600; line-height: 1.35; color: var(--ink); }
.verif-url { font-size: 12px; color: var(--jingga); margin-top: 4px; display: block; overflow-wrap: anywhere; min-height: 24px; }
.verif-ket { font-size: 12.5px; line-height: 1.45; color: var(--abu2); margin-top: 4px; }
footer { display: flex; align-items: center; gap: 8px; background: var(--aman-lunak); padding: 10px 18px; font-size: 13px; font-weight: 600; color: var(--aman-teks); }
footer svg { width: 16px; height: 16px; flex: none; }
.aksi { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin-top: 14px; }

.kursus-baris { margin: 12px 20px 0; padding: 12px; display: flex; gap: 12px; align-items: center; }
.kursus-isi { flex: 1; min-width: 0; }
.kursus-judul { font-size: 14.5px; font-weight: 600; color: var(--ink); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.kursus-persen { font-size: 13px; font-weight: 600; color: var(--ink); }
</style>

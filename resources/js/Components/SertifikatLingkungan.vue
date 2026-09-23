<script setup lang="ts">
/**
 * Lembar Sertifikat Penghargaan Kinerja Lingkungan — A4 lanskap.
 *
 * Susunannya mengikuti lembar acuan: kertas terang, pita hijau di tepi
 * kiri, baji hijau tua di kiri bawah, dan foto lanskap hutan-sungai di
 * kanan bawah yang dipotong melengkung. Capaian dibaca dari tiga kartu —
 * skor akhir, peringkat, predikat — bukan dari kalimat.
 *
 * Hanya fotonya yang berupa gambar (resources/images/sertifikat-lanskap.jpg,
 * dibuat di Canva). Selebihnya — tekstur kertas, pita, baji, busur, garis
 * emas — digambar sebagai SVG supaya tetap tajam pada cetak A4, dan isinya
 * teks sungguhan karena nama, nilai, nomor, dan QR berbeda di setiap lembar.
 *
 * Ukurannya tetap 1122 × 793 px — pada 96 dpi tepat di bawah
 * 297 × 210 mm, satu lembar cetak tanpa sisa. Di layar lembar yang sama
 * DISKALAKAN mengikuti lebar wadahnya, bukan disusun ulang.
 *
 * Unsur pengaman: teks mikro berisi nomornya sendiri di tepi atas dan di
 * pita kiri, roset halus di belakang judul, dan QR menuju halaman
 * verifikasi publik yang membaca keadaan HARI INI — sah, dicabut, atau
 * kedaluwarsa.
 */
import { computed, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import QRCode from 'qrcode';
import lanskap from '../../images/sertifikat-lanskap.jpg';
import type { DataSertifikatLingkungan } from '../types';

const props = defineProps<{
  s: DataSertifikatLingkungan;
  /** Belum terbit: diberi tanda air PRATINJAU dan QR-nya tidak digambar. */
  pratinjau?: boolean;
}>();

const W = 1122;
const H = 793;

/* ═══════════ penskalaan ═══════════ */

const luar = shallowRef<HTMLElement | null>(null);
const k = ref(1);
let ro: ResizeObserver | null = null;

onMounted(() => {
  if (!luar.value) return;
  k.value = luar.value.clientWidth / W || 1;
  ro = new ResizeObserver(([e]) => { if (e.contentRect.width) k.value = e.contentRect.width / W; });
  ro.observe(luar.value);
});
onBeforeUnmount(() => ro?.disconnect());

/* ═══════════ QR ═══════════ */

const qr = ref('');
watch(() => [props.s.urlVerifikasi, props.pratinjau], async () => {
  if (props.pratinjau) { qr.value = ''; return; }
  try {
    qr.value = await QRCode.toString(props.s.urlVerifikasi, {
      type: 'svg', margin: 0, errorCorrectionLevel: 'M',
      color: { dark: '#10261C', light: '#FFFFFF' },
    });
  } catch {
    qr.value = '';
  }
}, { immediate: true });

/* ═══════════ hiasan ═══════════ */

const uid = 'sl' + Math.random().toString(36).slice(2, 8);
const id = (n: string) => `${uid}-${n}`;
const url = (n: string) => `url(#${id(n)})`;

/** Roset latar: hiposikloid yang diputar berulang — nyaris tak terlihat, sulit ditiru. */
function roset(R: number, r: number, d: number, putar: number): string {
  const titik: string[] = [];
  const n = 480;
  const cp = Math.cos(putar), sp = Math.sin(putar);
  for (let i = 0; i <= n; i++) {
    const t = (i / n) * 2 * Math.PI;
    const x = (R - r) * Math.cos(t) + d * Math.cos(((R - r) / r) * t);
    const y = (R - r) * Math.sin(t) - d * Math.sin(((R - r) / r) * t);
    titik.push(`${(x * cp - y * sp).toFixed(1)},${(x * sp + y * cp).toFixed(1)}`);
  }
  return `M${titik.join('L')}Z`;
}
const rosetLatar = Array.from({ length: 8 }, (_, i) => roset(190, 38, 88, (i * Math.PI) / 24));

/** Tepi atas foto: melandai dari bawah tengah, menanjak di tepi kanan. */
const TEPI_FOTO = 'M 300 793 C 640 788, 930 760, 1122 470';
/** Tepi atas baji kiri bawah. */
const TEPI_BAJI = 'M 0 600 C 120 668, 240 735, 340 793';

function bintang(r: number): string {
  const t: string[] = [];
  for (let i = 0; i < 10; i++) {
    const a = -Math.PI / 2 + (i * Math.PI) / 5;
    const rr = i % 2 === 0 ? r : r * 0.42;
    t.push(`${(rr * Math.cos(a)).toFixed(2)},${(rr * Math.sin(a)).toFixed(2)}`);
  }
  return t.join(' ');
}

/* ═══════════ isi ═══════════ */

const angka = (n: number) => n.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const ukuranNama = computed(() => {
  const n = props.s.perusahaan.length;
  return n <= 26 ? 44 : n <= 36 ? 37 : n <= 50 ? 30 : 25;
});

/** Kaki kartu skor: dari mana skor akhir itu datang. */
const asalSkor = computed(() => props.s.pengurang > 0
  ? `Pemenuhan ${angka(props.s.pemenuhan)} · pengurang ${angka(props.s.pengurang)}`
  : `Pemenuhan ${angka(props.s.pemenuhan)} · tanpa pengurang`);

const mikro = computed(() =>
  Array(12).fill(`EQOHSEE · SERTIFIKAT PENGHARGAAN KINERJA LINGKUNGAN · ${props.s.nomor} · `).join(''));

const cap = computed(() => (props.s.status === 'dicabut' ? 'dicabut' : props.s.status === 'kedaluwarsa' ? 'kedaluwarsa' : null));

/** Kalimat dasar pemberian, dirakit utuh supaya spasinya tidak dimakan templat. */
const alasan = computed(() => {
  const s = props.s;
  return 'atas hasil Audit Internal Kinerja Pengelolaan dan Pemantauan Lingkungan'
    + (s.lokasi ? ` di ${s.lokasi}` : '')
    + ` Periode Tahun ${s.tahun}`
    + (s.tanggalAudit ? ` yang dilaksanakan pada ${s.tanggalAudit}` : '')
    + ', dengan capaian sebagai berikut:';
});

/** "Tempat, tanggal" — dipecah dua baris bila nama tempatnya panjang. */
const tempatTanggal = computed(() => {
  const { tempat, terbit } = props.s;
  if (!tempat) return [terbit || '…'];
  return tempat.length > 30 ? [`${tempat},`, terbit || '…'] : [`${tempat}, ${terbit || '…'}`];
});
</script>

<template>
  <div ref="luar" class="sl-luar" :style="{ height: `${H * k}px` }">
    <div class="sl-lembar" :style="{ transform: `scale(${k})` }">

      <!-- ═══ latar ═══ -->
      <svg class="sl-latar" :viewBox="`0 0 ${W} ${H}`" aria-hidden="true">
        <defs>
          <linearGradient :id="id('kertas')" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#FBFAF6" />
            <stop offset=".55" stop-color="#F6F4EE" />
            <stop offset="1" stop-color="#EFECE4" />
          </linearGradient>
          <filter :id="id('serat')" x="0" y="0" width="100%" height="100%">
            <feTurbulence type="fractalNoise" baseFrequency=".85" numOctaves="2" seed="11" />
            <feColorMatrix type="matrix" values="0 0 0 0 .32  0 0 0 0 .30  0 0 0 0 .24  .11 0 0 0 0" />
          </filter>
          <linearGradient :id="id('tua')" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#1B4A37" />
            <stop offset="1" stop-color="#0C2D21" />
          </linearGradient>
          <linearGradient :id="id('baji')" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#1E4F3A" />
            <stop offset="1" stop-color="#0A271C" />
          </linearGradient>
          <clipPath :id="id('foto')">
            <path :d="`${TEPI_FOTO} L 1122 793 Z`" />
          </clipPath>
        </defs>

        <!-- kertas -->
        <rect :width="W" :height="H" :fill="url('kertas')" />
        <rect :width="W" :height="H" :filter="url('serat')" />

        <!-- busur samar kanan atas -->
        <g fill="none">
          <circle cx="1096" cy="92" r="150" stroke="#E5E8E1" stroke-width="30" />
          <circle cx="1096" cy="92" r="206" stroke="#ECEEE8" stroke-width="9" />
        </g>

        <!-- roset di belakang judul -->
        <g :transform="`translate(${W / 2} 250)`" class="sl-roset">
          <path v-for="(d, i) in rosetLatar" :key="i" :d="d" />
        </g>

        <!-- pita kiri -->
        <polygon points="0,205 46,258 46,588 0,656" fill="#A9BEA5" opacity=".5" />
        <rect x="0" y="0" width="17" :height="H" :fill="url('tua')" />
        <line x1="17.6" y1="0" x2="17.6" :y2="H" stroke="#C9A24A" stroke-width=".9" />

        <!-- baji kiri bawah -->
        <path d="M 0 548 L 408 793 L 0 793 Z" fill="#7E9C80" opacity=".42" />
        <path :d="`${TEPI_BAJI} L 0 793 Z`" :fill="url('baji')" />
        <path :d="TEPI_BAJI" fill="none" stroke="#C9A24A" stroke-width="1.6" />

        <!-- foto lanskap kanan bawah -->
        <g :clip-path="url('foto')">
          <image :href="lanskap" x="300" y="440" width="822" height="353" preserveAspectRatio="xMidYMax slice" />
        </g>
        <path :d="TEPI_FOTO" fill="none" stroke="#123A2B" stroke-width="6" />
        <path :d="TEPI_FOTO" fill="none" stroke="#C9A24A" stroke-width="1.3" transform="translate(0 -5.5)" />

        <!-- teks mikro -->
        <text class="sl-mikro" x="250" y="17" :textLength="W - 500" lengthAdjust="spacing">{{ mikro }}</text>
        <text class="sl-mikro terang" :transform="`translate(11 ${H - 12}) rotate(-90)`" :textLength="H - 24"
              lengthAdjust="spacing">{{ mikro }}</text>
      </svg>

      <!-- ═══ isi ═══ -->
      <div class="sl-isi">
        <div class="sl-logo">
          <img v-if="s.logoPenerbit" :src="s.logoPenerbit" alt="">
          <span v-if="s.logoPenerbit && s.logoPenerima" class="sl-logo-sekat"></span>
          <img v-if="s.logoPenerima" :src="s.logoPenerima" alt="">
          <!-- Belum ada logo terunggah: nama perusahaan sebagai kop huruf,
               bukan lambang karangan yang bisa disangka logonya. -->
          <span v-if="!s.logoPenerbit && !s.logoPenerima" class="sl-kop-nama">{{ s.penerbit || s.perusahaan }}</span>
        </div>

        <h1 class="sl-judul">Sertifikat</h1>
        <p class="sl-subjudul">Hasil Audit Internal Kinerja Pengelolaan &amp; Pemantauan Lingkungan</p>

        <div class="sl-sekat" aria-hidden="true"><span></span><i></i><span></span></div>
        <p class="sl-nomor num">Nomor: {{ s.nomor }}</p>

        <p class="sl-kepada">Diberikan kepada</p>
        <h2 class="sl-nama" :style="{ fontSize: `${ukuranNama}px` }">{{ s.perusahaan }}</h2>

        <p class="sl-alasan">{{ alasan }}</p>

        <!-- hasil: tiga kolom dalam satu panel berbingkai -->
        <div class="sl-hasil">
          <div class="sl-h">
            <span class="sl-h-label">Skor Akhir</span>
            <b class="sl-h-skor num">{{ angka(s.skor) }}<small>/100</small></b>
            <span class="sl-h-ket num">{{ asalSkor }}</span>
          </div>
          <div class="sl-h peringkat" :class="`t-${s.tema}`">
            <span class="sl-h-label">Peringkat</span>
            <b class="sl-h-mewah">{{ s.peringkat }}</b>
            <span class="sl-h-ket kriteria">{{ s.peringkatKriteria }}</span>
          </div>
          <div class="sl-h">
            <span class="sl-h-label">Predikat</span>
            <b class="sl-h-mewah">{{ s.predikat }}</b>
            <svg class="sl-h-bintang" :viewBox="`-8 -8 ${s.bintang * 18} 16`" :width="s.bintang * 13" height="11" aria-hidden="true">
              <polygon v-for="n in s.bintang" :key="n" :points="bintang(7)" :transform="`translate(${(n - 1) * 18} 0)`" />
            </svg>
          </div>
        </div>
      </div>

      <!-- ═══ kaki: verifikasi kiri, pengesahan kanan ═══ -->
      <div class="sl-qr-blok">
        <div class="sl-qr">
          <div v-if="qr" class="sl-qr-gambar" v-html="qr"></div>
          <div v-else class="sl-qr-kosong">QR terbit<br>bersama<br>nomor</div>
        </div>
        <div class="sl-qr-teks">
          <b>Verifikasi keaslian</b>
          <span>Pindai kode QR untuk mencocokkan sertifikat ini dengan catatan di EQOHSEE.</span>
          <span class="sl-kode num">{{ pratinjau ? '····-····-····' : s.kode }}</span>
          <span class="sl-masa num">Terbit {{ s.terbit || '…' }}</span>
          <span v-if="s.berlaku" class="sl-masa num">Berlaku s.d. {{ s.berlaku }}</span>
        </div>
      </div>

      <div class="sl-ttd">
        <span v-for="(b, i) in tempatTanggal" :key="i">{{ b }}</span>
        <b class="sl-ttd-jabatan">{{ s.ttdJabatan || 'Kepala Teknik Tambang' }}</b>
        <div class="sl-ttd-gambar">
          <img v-if="s.ttdGambar && !pratinjau" :src="s.ttdGambar" alt="">
        </div>
        <b class="sl-ttd-nama">{{ s.ttdNama || 'Penanggung Jawab' }}</b>
        <span class="sl-ttd-pt">{{ s.penerbit || s.perusahaan }}</span>
      </div>

      <!-- ═══ keadaan ═══ -->
      <div v-if="pratinjau" class="sl-air" aria-hidden="true">PRATINJAU</div>

      <div v-if="cap" class="sl-cap" :class="cap" role="note">
        <b>{{ cap === 'dicabut' ? 'DICABUT' : 'KEDALUWARSA' }}</b>
        <span v-if="cap === 'dicabut'">sejak {{ s.dicabutPada }}</span>
        <span v-else>berlaku s.d. {{ s.berlaku }}</span>
        <small v-if="cap === 'dicabut' && s.alasanCabut">{{ s.alasanCabut }}</small>
      </div>
    </div>
  </div>
</template>

<style scoped>
.sl-luar { position: relative; width: 100%; overflow: hidden; }

.sl-lembar {
  position: absolute; top: 0; left: 0; width: 1122px; height: 793px;
  transform-origin: 0 0; overflow: hidden; background: #F6F4EE;
  font-family: 'Inter', system-ui, sans-serif; color: #1F2A24;
  -webkit-print-color-adjust: exact; print-color-adjust: exact;
}
.sl-latar { position: absolute; inset: 0; width: 100%; height: 100%; }
.sl-roset path { fill: none; stroke: #B8912B; stroke-width: .5; opacity: .07; }
.sl-mikro { font-size: 4.3px; letter-spacing: .5px; fill: #A38A48; font-weight: 700; }
.sl-mikro.terang { fill: rgba(233, 205, 122, .6); }

/* ─── isi tengah ─── */
.sl-isi {
  position: absolute; left: 190px; right: 190px; top: 36px;
  display: flex; flex-direction: column; align-items: center; text-align: center;
}

.sl-logo { height: 58px; display: flex; align-items: center; justify-content: center; gap: 16px; }
.sl-logo img { max-height: 58px; max-width: 230px; object-fit: contain; }
.sl-logo-sekat { width: 1px; height: 34px; background: #C9BE9C; }
.sl-kop-nama {
  font-size: 13px; font-weight: 800; letter-spacing: .28em; padding: 8px 0 8px .28em; text-transform: uppercase;
  color: #1B4A37; border-top: 1px solid #C9BE9C; border-bottom: 1px solid #C9BE9C;
}

.sl-judul {
  margin-top: 8px; font-weight: 800; font-size: 70px; line-height: 1;
  letter-spacing: .17em; padding-left: .17em; text-transform: uppercase;
  color: #163F2F; text-shadow: 0 1px 0 rgba(255, 255, 255, .6);
}
.sl-subjudul {
  margin-top: 12px; font-size: 12.5px; font-weight: 600; letter-spacing: .2em; padding-left: .2em;
  text-transform: uppercase; color: #23302A; white-space: nowrap;
}
.sl-sekat { display: flex; align-items: center; gap: 9px; margin-top: 12px; }
.sl-sekat span { width: 150px; height: 1px; background: linear-gradient(90deg, transparent, #9DA79F); }
.sl-sekat span:last-child { background: linear-gradient(90deg, #9DA79F, transparent); }
.sl-sekat i { width: 6px; height: 6px; transform: rotate(45deg); background: #B8912B; }
.sl-nomor { margin-top: 5px; font-size: 11.5px; color: #56605A; letter-spacing: .02em; }

.sl-kepada { margin-top: 10px; font-size: 13.5px; color: #3A4640; }
.sl-nama {
  margin-top: 4px; max-width: 700px; padding: 0 26px 9px; font-weight: 800; line-height: 1.1; letter-spacing: -.005em;
  color: #133B2C;
  background: linear-gradient(90deg, transparent, #B8912B 12%, #E9CD7A 50%, #B8912B 88%, transparent) bottom / 100% 2.5px no-repeat;
}
.sl-alasan { margin-top: 10px; max-width: 690px; font-size: 13.2px; line-height: 1.6; color: #36423B; }

/* ─── hasil: satu panel, tiga kolom ───
   Datar dan berbingkai garis rambut seperti tabel pada dokumen resmi —
   tanpa ikon hiasan dan kilap gradasi. Hanya kolom peringkat yang
   berwarna, karena warna itulah isi peringkatnya. */
.sl-hasil {
  margin-top: 16px; width: 700px; display: grid; grid-template-columns: repeat(3, 1fr);
  background: rgba(255, 255, 255, .72); border: 1px solid #B9C3B6; border-top: 3px solid #1B4A37;
  border-radius: 3px;
}
.sl-h { display: grid; justify-items: center; align-content: center; gap: 3px; padding: 11px 10px 12px; min-height: 104px; }
.sl-h + .sl-h { border-left: 1px solid #D3DAD0; }
.sl-h-label { font-size: 9.5px; font-weight: 700; letter-spacing: .22em; padding-left: .22em; text-transform: uppercase; color: #5A655E; }
.sl-h-skor { font-size: 34px; font-weight: 800; line-height: 1.05; color: #13241B; letter-spacing: -.01em; }
.sl-h-skor small { font-size: 13px; font-weight: 600; color: #6B756F; margin-left: 2px; letter-spacing: 0; }
.sl-h-mewah {
  font-family: 'Playfair Display', Georgia, serif; font-weight: 900; font-size: 29px; line-height: 1.12;
  letter-spacing: .06em; padding-left: .06em; color: #13241B;
}
.sl-h-ket { font-size: 10px; color: #56615A; }
.sl-h-ket.kriteria { font-weight: 700; letter-spacing: .12em; text-transform: uppercase; font-size: 9.5px; }
.sl-h-bintang { margin-top: 2px; }
.sl-h-bintang polygon { fill: #B8912B; }

.sl-h.peringkat.t-emas  { background: linear-gradient(180deg, #FBF3DC, #F2E2B4); }
.sl-h.peringkat.t-emas  .sl-h-mewah, .sl-h.peringkat.t-emas  .sl-h-ket { color: #6E4F0B; }
.sl-h.peringkat.t-hijau { background: linear-gradient(180deg, #EDF7EF, #D4EDD9); }
.sl-h.peringkat.t-hijau .sl-h-mewah, .sl-h.peringkat.t-hijau .sl-h-ket { color: #14532D; }
.sl-h.peringkat.t-biru  { background: linear-gradient(180deg, #EEF4FD, #D6E4F8); }
.sl-h.peringkat.t-biru  .sl-h-mewah, .sl-h.peringkat.t-biru  .sl-h-ket { color: #173E7A; }

/* ─── verifikasi ─── */
.sl-qr-blok { position: absolute; left: 190px; top: 566px; display: flex; align-items: center; gap: 14px; text-align: left; }
.sl-qr {
  width: 86px; height: 86px; flex: none; background: #fff; padding: 6px; border-radius: 4px;
  box-shadow: 0 2px 8px -3px rgba(16, 38, 28, .35), 0 0 0 1px #D6DDD3;
}
.sl-qr-gambar, .sl-qr-gambar :deep(svg) { width: 100%; height: 100%; display: block; }
.sl-qr-kosong {
  width: 100%; height: 100%; display: grid; place-items: center; text-align: center; font-size: 8.5px; line-height: 1.3;
  color: #A8A29E; background: repeating-linear-gradient(45deg, #FAFAF9 0 6px, #F2F2EF 6px 12px);
}
.sl-qr-teks { display: grid; gap: 2px; width: 250px; font-size: 10.2px; color: #4E5952; line-height: 1.4; }
.sl-qr-teks b { font-size: 12px; color: #16241C; }
.sl-kode {
  font-family: ui-monospace, 'SFMono-Regular', Menlo, monospace; font-size: 12.5px; font-weight: 800;
  letter-spacing: .08em; color: #10261C; margin-top: 2px;
}
.sl-masa { font-size: 9.8px; color: #5F6A63; }

/* ─── pengesahan ─── */
.sl-ttd {
  position: absolute; left: 800px; top: 546px; width: 380px; transform: translateX(-50%);
  display: flex; flex-direction: column; align-items: center; text-align: center;
  font-size: 11.5px; color: #3E4A43; line-height: 1.4;
}
.sl-ttd-jabatan { font-size: 12px; color: #16241C; margin-top: 1px; }
.sl-ttd-gambar { height: 50px; display: flex; align-items: flex-end; justify-content: center; }
.sl-ttd-gambar img { max-height: 50px; max-width: 190px; object-fit: contain; }
.sl-ttd-nama { font-size: 14px; color: #10261C; padding: 0 10px 1px; border-bottom: 1.5px solid #10261C; }
.sl-ttd-pt { margin-top: 2px; font-size: 11px; color: #4E5952; }

/* ─── keadaan ─── */
.sl-air {
  position: absolute; inset: 0; display: grid; place-items: center; pointer-events: none;
  font-weight: 900; font-size: 124px; letter-spacing: .18em;
  color: rgba(19, 59, 44, .07); transform: rotate(-20deg);
}

.sl-cap {
  position: absolute; top: 250px; right: 64px; transform: rotate(-14deg);
  display: grid; justify-items: center; gap: 2px; padding: 10px 22px 12px;
  border: 5px double currentColor; border-radius: 10px; background: rgba(255, 255, 255, .8);
  max-width: 330px; text-align: center;
}
.sl-cap b { font-size: 44px; font-weight: 900; letter-spacing: .12em; line-height: 1; }
.sl-cap span { font-size: 12px; font-weight: 800; letter-spacing: .06em; }
.sl-cap small { font-size: 10.5px; line-height: 1.35; opacity: .9; }
.sl-cap.dicabut { color: #B91C1C; }
.sl-cap.kedaluwarsa { color: #B45309; }
</style>

<style>
/* Cetak: skala dibuang, lembar berukuran aslinya. Tidak dilingkup
   (scoped) karena harus berlaku di luar komponen, pada saat cetak. */
@media print {
  .sl-luar { width: 1122px !important; height: 793px !important; overflow: visible !important; }
  .sl-lembar { transform: none !important; }
}
</style>

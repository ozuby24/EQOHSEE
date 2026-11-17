<script setup lang="ts">
/**
 * Verifikasi publik Sertifikat Penghargaan Kinerja Lingkungan.
 *
 * Dibuka siapa pun dari QR pada lembar yang sudah tercetak — pemberi
 * kerja, regulator, tamu. Keadaannya dibaca HARI INI: lembar yang
 * dicetak tahun lalu dan sudah dicabut harus terbaca dicabut, bukan sah.
 * Tiga keadaan dibedakan tegas warnanya, karena yang memindainya sering
 * hanya melirik.
 */
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import type { HalamanVerifikasiLingkungan } from '../../types';

defineOptions({ layout: PublicLayout });

const props = defineProps<HalamanVerifikasiLingkungan>();

const angka = (n: number) => n.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const keadaan = computed(() => {
  switch (props.s?.status) {
    case 'sah':         return { kelas: 'sah', ikon: '✓', judul: 'Sertifikat Sah', ket: 'Tercatat dan masih berlaku pada sistem EQOHSEE.' };
    case 'kedaluwarsa': return { kelas: 'lewat', ikon: '!', judul: 'Masa Berlaku Habis', ket: 'Sertifikat ini pernah sah, tetapi masa berlakunya sudah lewat.' };
    case 'dicabut':     return { kelas: 'cabut', ikon: '×', judul: 'Sertifikat Dicabut', ket: 'Sertifikat ini tidak berlaku lagi.' };
    default:            return null;
  }
});

const baris = computed(() => props.s ? [
  ['Diberikan kepada', props.s.perusahaan],
  ['Lokasi', props.s.lokasi ?? '—'],
  ...(props.s.penerbit ? [['Diberikan oleh', props.s.penerbit]] : []),
  ['Periode audit', String(props.s.tahun)],
  ['Skor akhir', `${angka(props.s.skor)} / 100`],
  ['Nomor sertifikat', props.s.nomor],
  ['Kode verifikasi', props.s.kode],
  ['Tanggal terbit', props.s.terbit ?? '—'],
  ['Berlaku s.d.', props.s.berlaku ?? '—'],
  ['Ditandatangani', [props.s.ttdNama, props.s.ttdJabatan].filter(Boolean).join(' — ') || '—'],
] : []);
</script>

<template>
  <Head title="Verifikasi Sertifikat Lingkungan" />

  <div v-if="s && keadaan" class="vl-kartu" :class="keadaan.kelas">
    <div class="vl-kepala">
      <div class="vl-ikon" aria-hidden="true">{{ keadaan.ikon }}</div>
      <div>
        <h1 class="vl-judul">{{ keadaan.judul }}</h1>
        <p class="vl-ket">{{ keadaan.ket }}</p>
      </div>
    </div>

    <p v-if="s.status === 'dicabut'" class="vl-cabut">
      Dicabut sejak <b>{{ s.dicabutPada }}</b><template v-if="s.alasanCabut"> — {{ s.alasanCabut }}</template>
    </p>

    <!-- Ringkasan penghargaan, berwarna peringkatnya. -->
    <div class="vl-penghargaan" :class="`tema-${s.tema}`">
      <div class="vl-logo" v-if="s.logoPenerbit || s.logoPenerima">
        <img v-if="s.logoPenerbit" :src="s.logoPenerbit" alt="">
        <img v-if="s.logoPenerima" :src="s.logoPenerima" alt="">
      </div>
      <p class="vl-label">Sertifikat Penghargaan Kinerja Lingkungan</p>
      <p class="vl-nama">{{ s.perusahaan }}</p>
      <div class="vl-medali">
        <span class="vl-bintang" aria-hidden="true">{{ '★'.repeat(s.bintang) }}</span>
        <span class="vl-predikat">{{ s.predikat }}</span>
      </div>
      <span class="vl-peringkat" :style="{ background: s.warna }">
        Peringkat {{ s.peringkat }} · {{ s.peringkatKriteria }}
      </span>
    </div>

    <dl class="vl-rincian">
      <div v-for="r in baris" :key="r[0]">
        <dt>{{ r[0] }}</dt>
        <dd class="num">{{ r[1] }}</dd>
      </div>
    </dl>
  </div>

  <div v-else class="vl-kartu tidak">
    <div class="vl-kepala">
      <div class="vl-ikon" aria-hidden="true">?</div>
      <div>
        <h1 class="vl-judul">Tidak Ditemukan</h1>
        <p class="vl-ket">
          Kode <b class="font-mono">{{ kode }}</b> tidak terdaftar sebagai Sertifikat Penghargaan Kinerja Lingkungan.
          Periksa kembali kode pada lembar sertifikat — dua belas huruf dan angka di bawah kode QR.
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.vl-kartu {
  background: #fff; border-radius: 1.25rem; border: 1px solid #E7E5E4; overflow: hidden;
  box-shadow: 0 10px 30px -16px rgba(15, 23, 32, .25);
}
.vl-kepala { display: flex; align-items: center; gap: 1rem; padding: 1.4rem 1.5rem; }
.vl-ikon {
  width: 56px; height: 56px; flex: none; border-radius: 50%; display: grid; place-items: center;
  font-size: 28px; font-weight: 900; color: #fff;
}
.vl-judul { font-family: 'Playfair Display', Georgia, serif; font-size: 22px; font-weight: 900; color: #0F1720; }
.vl-ket { font-size: 12.5px; color: #57534E; margin-top: .15rem; line-height: 1.5; }

.sah .vl-kepala   { background: linear-gradient(135deg, #ECFDF5, #F0FDF4); border-bottom: 1px solid #BBF7D0; }
.sah .vl-ikon     { background: linear-gradient(135deg, #059669, #10B981); }
.lewat .vl-kepala { background: #FFFBEB; border-bottom: 1px solid #FDE68A; }
.lewat .vl-ikon   { background: #D97706; }
.cabut .vl-kepala { background: #FEF2F2; border-bottom: 1px solid #FECACA; }
.cabut .vl-ikon   { background: #DC2626; }
.tidak .vl-ikon   { background: #A8A29E; }

.vl-cabut { margin: 1rem 1.5rem 0; font-size: 12.5px; color: #991B1B; background: #FEF2F2; border-radius: .7rem; padding: .6rem .8rem; }

.vl-penghargaan {
  margin: 1.2rem 1.5rem 0; border-radius: 1rem; padding: 1.2rem 1rem 1.3rem; text-align: center; color: #fff;
  /* Zamrud-emas, sewarna lembar sertifikatnya; peringkat dibawa keping di bawah. */
  background: radial-gradient(120% 120% at 20% 0%, #0E6B4C, #062A1D);
  position: relative; overflow: hidden;
}
.vl-penghargaan::after {
  content: ''; position: absolute; inset: 6px; border: 1px solid rgba(233, 199, 102, .55); border-radius: .75rem; pointer-events: none;
}

.vl-logo { display: flex; justify-content: center; gap: .8rem; margin-bottom: .6rem; }
.vl-logo img { height: 34px; max-width: 110px; object-fit: contain; background: #fff; border-radius: .4rem; padding: 3px 6px; }
.vl-label { font-size: 10px; font-weight: 800; letter-spacing: .22em; text-transform: uppercase; color: #F4D98B; }
.vl-nama { font-family: 'Playfair Display', Georgia, serif; font-size: 22px; font-weight: 800; margin-top: .35rem; line-height: 1.2; }
.vl-medali { display: grid; justify-items: center; margin-top: .7rem; }
.vl-bintang { color: #F6E3A1; letter-spacing: .2em; font-size: 14px; }
.vl-predikat { font-family: 'Playfair Display', Georgia, serif; font-weight: 900; font-size: 26px; letter-spacing: .12em; color: #FFF8E1; }
.vl-peringkat {
  display: inline-block; margin-top: .6rem; padding: .2rem .8rem; border-radius: 999px;
  font-size: 11px; font-weight: 800; letter-spacing: .06em; box-shadow: 0 0 0 2px rgba(255, 255, 255, .35);
}

.vl-rincian { padding: 1rem 1.5rem 1.4rem; display: grid; }
.vl-rincian > div {
  display: flex; justify-content: space-between; gap: 1rem; padding: .55rem 0; border-bottom: 1px solid #F5F5F4;
}
.vl-rincian > div:last-child { border-bottom: 0; }
.vl-rincian dt { font-size: 11.5px; color: #A8A29E; flex: none; }
.vl-rincian dd { font-size: 12.5px; font-weight: 700; color: #1C1917; text-align: right; margin: 0; word-break: break-word; }

:global(:root[data-tema="gelap"] .vl-kartu) { background: #101A1E; border-color: #223238; }
:global(:root[data-tema="gelap"] .vl-judul),
:global(:root[data-tema="gelap"] .vl-rincian dd) { color: #EEF3F4; }
:global(:root[data-tema="gelap"] .vl-ket) { color: #AEBCC2; }
:global(:root[data-tema="gelap"] .sah .vl-kepala) { background: rgba(16, 185, 129, .12); border-color: rgba(16, 185, 129, .3); }
:global(:root[data-tema="gelap"] .lewat .vl-kepala) { background: rgba(245, 158, 11, .12); border-color: rgba(245, 158, 11, .3); }
:global(:root[data-tema="gelap"] .cabut .vl-kepala),
:global(:root[data-tema="gelap"] .vl-cabut) { background: rgba(239, 68, 68, .12); border-color: rgba(239, 68, 68, .3); color: #FCA5A5; }
:global(:root[data-tema="gelap"] .vl-rincian > div) { border-color: #1B292E; }
</style>

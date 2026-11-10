<script setup lang="ts">
/**
 * Persetujuan — include/persetujuan.blade.php Safe Track, digambar
 * sebagai stepper (pengaju → dokter → HSE → KTT) alih-alih tabel kotak.
 *
 * Isi tiap langkah tetap sama dengan tabel Safe Track: JABATAN beserta
 * organisasinya sebagai judul, nama orang di dalamnya, waktu, keadaan,
 * dan catatan. Kalimat posisi ("Sedang menunggu tindakan dari HSE PT X …")
 * tetap di atas. Keadaan selalu disertai ikon DAN tulisan: lembar
 * persetujuan sering dicetak hitam-putih dan tidak semua mata membedakan
 * hijau dari jingga. Di bawah 720 px stepper berubah menjadi linimasa tegak.
 */
import '../../../css/miners.css';
import Ikon from './Ikon.vue';
import { IKON } from './ikon';
import type { LangkahPersetujuan } from './zona';

defineProps<{
  alur: LangkahPersetujuan[];
  posisi?: { pesan: string; nada: string } | null;
}>();

function kelas(l: LangkahPersetujuan): string {
  if (l.keadaan === 'setuju') return 'mn-s-setuju';
  if (l.keadaan === 'tolak') return 'mn-s-tolak';
  if (l.keadaan === 'dikembalikan') return 'mn-s-kembali';
  return l.giliran ? 'mn-s-proses' : 'mn-s-tunggu';
}

function ikon(l: LangkahPersetujuan): string {
  if (l.keadaan === 'setuju') return IKON.cek;
  if (l.keadaan === 'tolak') return IKON.x;
  if (l.keadaan === 'dikembalikan') return IKON.balik;
  return l.giliran ? IKON.jam : IKON.titik;
}

const lencana: Record<string, string> = {
  'mn-s-setuju': 'mn-l-sukses', 'mn-s-tolak': 'mn-l-bahaya', 'mn-s-kembali': 'mn-l-oranye', 'mn-s-proses': 'mn-l-utama', 'mn-s-tunggu': 'mn-l-terang',
};

const IKON_POSISI: Record<string, string> = {
  setuju: IKON.centang, tolak: IKON.silang, kembali: IKON.balik, proses: IKON.jam, tunggu: IKON.info,
};

/* Nama yang dicetak: yang menindak bila sudah ditindak, yang ditugasi
   bila belum. */
const nama = (l: LangkahPersetujuan) => (l.keadaan !== 'menunggu' ? l.oleh : l.ditugaskan) ?? null;
</script>

<template>
  <div>
    <div v-if="posisi" class="mn-posisi" :class="`mn-posisi-${posisi.nada}`" role="status">
      <Ikon :d="IKON_POSISI[posisi.nada] ?? IKON.info" />
      {{ posisi.pesan }}
    </div>

    <ol v-if="alur.length" class="mn-stepper" aria-label="Langkah persetujuan">
      <li v-for="l in alur" :key="l.urutan" class="mn-step" :class="kelas(l)" :aria-current="l.giliran ? 'step' : undefined">
        <div class="mn-step-kepala">
          <span class="mn-step-bulat"><Ikon :d="ikon(l)" /></span>
          <span class="mn-step-garis" aria-hidden="true" />
        </div>
        <span v-if="l.sebutan" class="mn-step-peran">{{ l.sebutan }}</span>
        <span class="mn-step-label">{{ l.label }}</span>
        <span class="mn-step-nama">{{ nama(l) ?? (l.peran === 'pengaju' ? '—' : 'Belum ditentukan') }}</span>
        <span v-if="l.atasNama && l.ditugaskan" class="mn-step-waktu">atas nama {{ l.ditugaskan }}</span>
        <span v-if="l.pada" class="mn-step-waktu">{{ l.pada }}</span>
        <span><span class="mn-lencana" :class="lencana[kelas(l)]">{{ l.keadaanLabel }}</span></span>
        <span v-if="l.catatan" class="mn-step-catatan">“{{ l.catatan }}”</span>
      </li>
    </ol>
    <div v-else class="mn-alert mn-alert-peringatan">
      <span><b>Alur persetujuan belum tersusun.</b> Atur di Miners → Alur Persetujuan.</span>
    </div>
  </div>
</template>

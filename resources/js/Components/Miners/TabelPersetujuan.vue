<script setup lang="ts">
/**
 * Tabel persetujuan — include/persetujuan.blade.php Safe Track.
 *
 * Kalimat posisi di atas ("Sedang menunggu tindakan dari HSE PT X …")
 * dan satu kolom per langkah. Judul kolom adalah JABATAN beserta
 * organisasinya, bukan nama orang; nama dicetak di dalam kotaknya.
 * Warna selalu disertai tulisan keadaannya: lembar persetujuan sering
 * dicetak hitam-putih.
 */
import '../../../css/miners.css';
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

/* Nama yang dicetak: yang menindak bila sudah ditindak, yang ditugasi
   bila belum. */
const nama = (l: LangkahPersetujuan) => (l.keadaan !== 'menunggu' ? l.oleh : l.ditugaskan) ?? null;
</script>

<template>
  <div>
    <div v-if="posisi" class="mn-posisi" :class="`mn-posisi-${posisi.nada}`">{{ posisi.pesan }}</div>

    <div class="mn-gulir">
      <table class="mn-setuju">
        <thead>
          <tr>
            <th v-for="l in alur" :key="l.urutan">
              <span v-if="l.sebutan" class="mn-peran">{{ l.sebutan }}</span>
              {{ l.label }}
            </th>
            <th v-if="!alur.length">Persetujuan</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td v-for="l in alur" :key="l.urutan" :class="kelas(l)">
              <span class="mn-nama">{{ nama(l) ?? (l.peran === 'pengaju' ? '—' : 'Belum ditentukan') }}</span>
              <span v-if="l.atasNama && l.ditugaskan" class="mn-waktu">atas nama {{ l.ditugaskan }}</span>
              <span v-if="l.pada" class="mn-waktu">{{ l.pada }}</span>
              <span class="mn-tanda">{{ l.keadaanLabel }}</span>
              <span v-if="l.catatan" class="mn-catatan">“{{ l.catatan }}”</span>
            </td>
            <td v-if="!alur.length" class="mn-s-tunggu">
              <span class="mn-nama">Alur persetujuan belum tersusun</span>
              <span class="mn-waktu">Atur di Miners → Alur Persetujuan</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

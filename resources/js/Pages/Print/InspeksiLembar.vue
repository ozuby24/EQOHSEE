<script setup lang="ts">
/**
 * Lembar satu inspeksi — dokumen yang ditandatangani dan diarsipkan.
 *
 * ── Kenapa terpisah dari register ──
 *
 * Register menjawab "apa saja yang sudah diperiksa bulan ini" dan
 * berguna di rapat. Lembar ini menjawab pertanyaan yang berbeda dan
 * jauh lebih sering ditanyakan auditor: "tunjukkan bukti pemeriksaan
 * tanggal sekian". Bukti itu harus memuat siapa yang memeriksa, apa
 * yang diperiksa satu per satu, apa hasilnya, dan tanda tangan yang
 * mempertanggungjawabkannya. Register tidak memuat satu pun dari
 * keempatnya.
 *
 * ── Kenapa butir yang N/A tetap dicetak ──
 *
 * Butir yang tidak berlaku adalah KEPUTUSAN pemeriksa, bukan baris
 * kosong. Membuangnya dari lembar membuat daftar periksa yang tercetak
 * lebih pendek daripada daftar periksa bakunya, dan yang membandingkan
 * keduanya tidak dapat membedakan butir yang sengaja dilewati dari
 * butir yang lupa diperiksa.
 *
 * ── Kenapa foto ditampilkan kecil dan berjajar ──
 *
 * Lembar ini dicetak. Foto selebar halaman memaksa satu temuan memakan
 * satu halaman sendiri, dan lembar inspeksi dua puluh delapan butir
 * menjadi dokumen dua puluh halaman yang tidak akan dibaca siapa pun.
 */
import { Head } from '@inertiajs/vue3';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';
import KopCetak from '../../Components/KopCetak.vue';

/* Tanpa kerangka aplikasi.
   Halaman cetak yang mewarisi AppLayout ikut membawa bilah atas,
   sampul modul, dan bilah sampingnya ke atas kertas — dan itu tidak
   terlihat di layar sama sekali, karena di layar semuanya memang wajar
   berada di sana. Yang menemukannya adalah orang yang sudah menekan
   cetak. */
defineOptions({ layout: BlankLayout });

interface Butir {
  uraian: string | null;
  acuan: string | null;
  risiko: string | null;
  kondisi: string | null;
  temuan: string | null;
  tindakan: string | null;
  foto: string[];
}

const props = defineProps<{
  dok: Record<string, unknown> | null;
  i: {
    kode: string; judul: string | null; jenis: string | null; lokasi: string | null;
    tanggal: string | null; status: string | null; catatan: string | null;
    template: string | null; perusahaan: string | null;
  };
  rekap: { total: number; sesuai: number; tidakSesuai: number; na: number; belum: number };
  kelompok: Array<{ nama: string; butir: Butir[] }>;
  pemeriksa: Array<{ nama: string | null; jabatan: string | null; peran: string | null }>;
  kembali: string;
}>();

/* Persentase kesesuaian dihitung TERHADAP yang benar-benar dinilai —
   Sesuai ditambah Tidak Sesuai — bukan terhadap seluruh butir. Butir
   N/A yang ikut menjadi penyebut menurunkan angkanya tanpa ada satu pun
   ketidaksesuaian, sehingga area yang separuh butirnya memang tidak
   berlaku selalu terbaca lebih buruk daripada keadaannya. */
const dinilai = props.rekap.sesuai + props.rekap.tidakSesuai;
const persen = dinilai ? Math.round((props.rekap.sesuai / dinilai) * 100) : null;

const nadaKondisi = (k: string | null) =>
  k === 'Sesuai' ? 'eq-ok' : k === 'Tidak Sesuai' ? 'eq-nok' : k === 'N/A' ? 'eq-na' : 'eq-kosong';
</script>

<template>
  <Head :title="`Lembar Inspeksi ${i.kode}`" />

  <PrintShell :title="`Lembar Inspeksi ${i.kode}`" :kembali="kembali">
    <article class="eq-lembar lembar">
      <KopCetak :dok="dok" />

      <!-- Identitas: apa, di mana, kapan, oleh siapa. Empat pertanyaan
           yang harus terjawab sebelum satu baris hasil pun dibaca. -->
      <table class="eq-identitas">
        <tbody>
          <tr>
            <th>Nomor Inspeksi</th><td class="num">{{ i.kode }}</td>
            <th>Tanggal</th><td>{{ i.tanggal ?? '—' }}</td>
          </tr>
          <tr>
            <th>Judul</th><td>{{ i.judul ?? '—' }}</td>
            <th>Jenis</th><td>{{ i.jenis ?? '—' }}</td>
          </tr>
          <tr>
            <th>Lokasi / Area</th><td>{{ i.lokasi ?? '—' }}</td>
            <th>Status</th><td>{{ i.status ?? '—' }}</td>
          </tr>
          <tr>
            <th>Daftar Periksa</th><td>{{ i.template ?? 'Tanpa template baku' }}</td>
            <th>Perusahaan</th><td>{{ i.perusahaan ?? '—' }}</td>
          </tr>
        </tbody>
      </table>

      <!-- Rekapitulasi di ATAS daftar, bukan di bawahnya. Yang membuka
           lembar ini biasanya mencari satu angka: berapa yang tidak
           sesuai. Menaruhnya di kaki dokumen dua puluh delapan baris
           berarti angka itu baru terlihat sesudah seluruhnya dibaca. -->
      <section class="eq-rekap">
        <div class="eq-rekap-judul">REKAPITULASI PEMERIKSAAN</div>
        <div class="eq-rekap-angka">
          <span><b>{{ rekap.total }}</b> butir diperiksa</span>
          <span class="eq-ok-teks"><b>{{ rekap.sesuai }}</b> sesuai</span>
          <span class="eq-nok-teks"><b>{{ rekap.tidakSesuai }}</b> tidak sesuai</span>
          <span v-if="rekap.na"><b>{{ rekap.na }}</b> tidak berlaku</span>
          <span v-if="rekap.belum" class="eq-nok-teks"><b>{{ rekap.belum }}</b> belum dinilai</span>
          <span v-if="persen !== null" class="eq-persen">Kesesuaian {{ persen }}%</span>
        </div>
      </section>

      <table class="eq-periksa">
        <thead>
          <tr>
            <th class="eq-k-no">No</th>
            <th class="eq-k-uraian">Uraian Pemeriksaan</th>
            <th class="eq-k-acuan">Acuan</th>
            <th class="eq-k-risiko">Risiko</th>
            <th class="eq-k-kondisi">Kondisi</th>
            <th class="eq-k-temuan">Temuan dan Tindakan</th>
          </tr>
        </thead>

        <tbody v-for="(g, gi) in kelompok" :key="g.nama">
          <tr class="eq-baris-kelompok">
            <td colspan="6">{{ String.fromCharCode(65 + gi) }}. {{ g.nama.toUpperCase() }}</td>
          </tr>

          <tr v-for="(b, bi) in g.butir" :key="bi">
            <td class="eq-k-no num">{{ bi + 1 }}</td>
            <td>{{ b.uraian }}</td>
            <td class="eq-acuan">{{ b.acuan ?? '—' }}</td>
            <td class="eq-tengah">{{ b.risiko ?? '—' }}</td>
            <td class="eq-tengah">
              <span :class="nadaKondisi(b.kondisi)">{{ b.kondisi ?? 'belum' }}</span>
            </td>
            <td>
              <template v-if="b.temuan || b.tindakan">
                <div v-if="b.temuan"><b>Temuan:</b> {{ b.temuan }}</div>
                <div v-if="b.tindakan"><b>Tindakan:</b> {{ b.tindakan }}</div>
              </template>
              <span v-else class="eq-sunyi">—</span>

              <div v-if="b.foto.length" class="eq-foto">
                <img v-for="(f, fi) in b.foto" :key="fi" :src="f"
                     :alt="`Foto butir ${bi + 1}`" loading="lazy">
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <section v-if="i.catatan" class="eq-catatan">
        <div class="eq-catatan-judul">CATATAN PEMERIKSA</div>
        <p>{{ i.catatan }}</p>
      </section>

      <!-- Tanda tangan sebanyak pemeriksanya, bukan dua kolom tetap.
           Lembar berkolom tetap memaksa inspeksi bertim empat orang
           menandatangani di luar kotaknya, dan inspeksi seorang diri
           meninggalkan satu kotak kosong yang terbaca seperti tanda
           tangan yang belum didapat. -->
      <section class="eq-ttd" :style="{ '--kolom': Math.max(pemeriksa.length, 1) }">
        <div v-for="(p, pi) in pemeriksa" :key="pi" class="eq-ttd-kotak">
          <div class="eq-ttd-peran">{{ p.peran ?? 'Pemeriksa' }}</div>
          <div class="eq-ttd-ruang"></div>
          <div class="eq-ttd-nama">{{ p.nama ?? '' }}</div>
          <div class="eq-ttd-jabatan">{{ p.jabatan ?? '' }}</div>
        </div>

        <div v-if="!pemeriksa.length" class="eq-ttd-kotak">
          <div class="eq-ttd-peran">Pemeriksa</div>
          <div class="eq-ttd-ruang"></div>
          <div class="eq-ttd-nama">&nbsp;</div>
          <div class="eq-ttd-jabatan">&nbsp;</div>
        </div>
      </section>
    </article>
  </PrintShell>
</template>

<style scoped>
.eq-lembar {
  background: #fff;
  padding: 1.6rem 1.8rem 2rem;
  font-size: 10.5px;
  color: #1B1817;
  line-height: 1.45;
}

/* ── identitas ── */
.eq-identitas { width: 100%; border-collapse: collapse; margin-bottom: .8rem; }
.eq-identitas th, .eq-identitas td { border: 1px solid #D6D3D1; padding: .3rem .5rem; text-align: left; vertical-align: top; }
.eq-identitas th { width: 13%; background: #F5F5F4; font-weight: 700; font-size: 9.5px; text-transform: uppercase; letter-spacing: .03em; }
.eq-identitas td { width: 37%; }

/* ── rekap ── */
.eq-rekap { border: 1px solid #D6D3D1; margin-bottom: .8rem; }
.eq-rekap-judul { background: #1C1917; color: #E7E5E4; font-size: 9.5px; font-weight: 700; letter-spacing: .06em; padding: .3rem .5rem; }
.eq-rekap-angka { display: flex; flex-wrap: wrap; gap: .25rem 1.4rem; padding: .45rem .5rem; }
.eq-rekap-angka b { font-size: 12px; }
.eq-ok-teks { color: #15803D; }
.eq-nok-teks { color: #B91C1C; }
.eq-persen { margin-left: auto; font-weight: 700; }

/* ── daftar periksa ── */
.eq-periksa { width: 100%; border-collapse: collapse; }
.eq-periksa th, .eq-periksa td { border: 1px solid #D6D3D1; padding: .3rem .45rem; vertical-align: top; }
.eq-periksa thead th { background: #1C1917; color: #E7E5E4; font-size: 9px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; text-align: left; }

.eq-k-no { width: 4%; text-align: center; }
.eq-k-uraian { width: 31%; }
.eq-k-acuan { width: 15%; }
.eq-k-risiko { width: 8%; }
.eq-k-kondisi { width: 10%; }
.eq-k-temuan { width: 32%; }

.eq-acuan { font-size: 9px; color: #57534E; }
.eq-tengah { text-align: center; }
.eq-sunyi { color: #A8A29E; }

.eq-baris-kelompok td {
  background: #E7E5E4;
  font-weight: 800;
  font-size: 9.5px;
  letter-spacing: .05em;
}

.eq-ok  { color: #15803D; font-weight: 700; }
.eq-nok { color: #B91C1C; font-weight: 800; }
.eq-na  { color: #78716C; }
.eq-kosong { color: #A8A29E; font-style: italic; }

.eq-foto { display: flex; flex-wrap: wrap; gap: .25rem; margin-top: .3rem; }
.eq-foto img { width: 74px; height: 56px; object-fit: cover; border: 1px solid #D6D3D1; border-radius: 3px; }

/* ── catatan ── */
.eq-catatan { border: 1px solid #D6D3D1; margin-top: .8rem; }
.eq-catatan-judul { background: #F5F5F4; font-size: 9.5px; font-weight: 700; letter-spacing: .05em; padding: .3rem .5rem; border-bottom: 1px solid #D6D3D1; }
.eq-catatan p { padding: .45rem .5rem; margin: 0; white-space: pre-line; }

/* ── tanda tangan ── */
.eq-ttd {
  display: grid;
  grid-template-columns: repeat(var(--kolom, 2), minmax(0, 1fr));
  gap: 1rem;
  margin-top: 1.6rem;

  /* Tidak boleh terbelah antar halaman. Blok tanda tangan yang
     kepalanya di halaman tiga dan garisnya di halaman empat membuat
     lembar itu ditolak saat diarsipkan. */
  break-inside: avoid;
  page-break-inside: avoid;
}

.eq-ttd-kotak { text-align: center; }
.eq-ttd-peran { font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #57534E; }
.eq-ttd-ruang { height: 3.2rem; }
.eq-ttd-nama { border-top: 1px solid #1B1817; padding-top: .2rem; font-weight: 700; }
.eq-ttd-jabatan { font-size: 9px; color: #57534E; }

/* ── saat dicetak ── */
@media print {
  .eq-lembar { padding: 0; }

  /* Judul kolom diulang pada tiap halaman. Daftar dua puluh delapan
     butir pasti melewati satu halaman, dan halaman kedua tanpa judul
     kolom adalah enam kolom yang harus ditebak. */
  .eq-periksa thead { display: table-header-group; }

  .eq-periksa tr { break-inside: avoid; page-break-inside: avoid; }
  .eq-baris-kelompok td { break-after: avoid; page-break-after: avoid; }
}
</style>

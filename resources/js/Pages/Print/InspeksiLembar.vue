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
 * ── Kenapa satu halaman, dan bagaimana caranya ──
 *
 * Lembar ini dibawa ke lapangan, difoto, dilampirkan ke surel, dan
 * diarsipkan dalam bindex. Keempatnya berurusan dengan HALAMAN, bukan
 * dengan dokumen: lembar tiga halaman difoto tiga kali, dilampirkan
 * sebagai tiga berkas, dan halaman yang tercecer dari bindex tidak
 * dapat dikenali sebagai milik inspeksi yang mana.
 *
 * Yang membuatnya muat bukan mengecilkan huruf, melainkan menyusunnya
 * menurut kenyataan: dari dua puluh delapan butir, dua puluh empat
 * hanya perlu satu tanda. Butir-butir itu dicetak dua lajur sebagai
 * daftar tanda, dan hanya yang TIDAK SESUAI yang mendapat barisnya
 * sendiri di bawah — lengkap dengan risiko, temuan, tindakan, dan
 * fotonya. Ruangnya jadi berada pada yang memang perlu dibaca.
 *
 * ── Kenapa butir yang N/A tetap dicetak ──
 *
 * Butir yang tidak berlaku adalah KEPUTUSAN pemeriksa, bukan baris
 * kosong. Membuangnya membuat daftar periksa yang tercetak lebih
 * pendek daripada daftar periksa bakunya, dan yang membandingkan
 * keduanya tidak dapat membedakan butir yang sengaja dilewati dari
 * butir yang lupa diperiksa.
 */
import { computed } from 'vue';
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

/* Penomoran BERJALAN terus melintasi kelompok, bukan mulai dari satu
   pada tiap kelompok. Blok temuan di bawah menunjuk butirnya dengan
   nomor itu, dan nomor yang berulang di tujuh kelompok menunjuk ke
   tujuh baris sekaligus. */
const berlajur = computed(() => {
  let n = 0;

  return props.kelompok.map((g, gi) => ({
    nama: g.nama,
    huruf: String.fromCharCode(65 + gi),
    butir: g.butir.map((b) => ({ ...b, no: ++n })),
  }));
});

/* Hanya yang tidak sesuai yang mendapat barisnya sendiri.

   Kelompoknya tidak ikut disebut di sini: nomor butirnya sudah
   menunjuk satu baris tertentu di daftar atas, dan kelompok yang
   diulang menambah satu baris teks pada tiap temuan — enam temuan
   berarti enam baris yang tidak memberi tahu apa pun yang belum
   terbaca sebaris di atasnya. */
const temuan = computed(() =>
  berlajur.value.flatMap((g) => g.butir.filter((b) => b.kondisi === 'Tidak Sesuai')),
);

/** Satu aksara, karena kolomnya selebar satu aksara. */
const tanda = (k: string | null) =>
  k === 'Sesuai' ? '✓' : k === 'Tidak Sesuai' ? '✗' : k === 'N/A' ? '–' : '☐';

const nada = (k: string | null) =>
  k === 'Sesuai' ? 'is-ok' : k === 'Tidak Sesuai' ? 'is-nok' : k === 'N/A' ? 'is-na' : 'is-kosong';
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
            <th>Nomor</th><td class="num">{{ i.kode }}</td>
            <th>Tanggal</th><td>{{ i.tanggal ?? '—' }}</td>
            <th>Jenis</th><td>{{ i.jenis ?? '—' }}</td>
          </tr>
          <tr>
            <th>Judul</th><td colspan="3">{{ i.judul ?? '—' }}</td>
            <th>Lokasi</th><td>{{ i.lokasi ?? '—' }}</td>
          </tr>
          <tr>
            <th>Daftar Periksa</th><td colspan="3">{{ i.template ?? 'Tanpa template baku' }}</td>
            <th>Perusahaan</th><td>{{ i.perusahaan ?? '—' }}</td>
          </tr>
        </tbody>
      </table>

      <!-- Rekapitulasi di ATAS daftar, bukan di bawahnya. Yang membuka
           lembar ini biasanya mencari satu angka: berapa yang tidak
           sesuai. Menaruhnya di kaki dokumen berarti angka itu baru
           terlihat sesudah seluruhnya dibaca. -->
      <div class="eq-rekap">
        <span class="eq-rekap-label">REKAPITULASI</span>
        <span><b>{{ rekap.total }}</b> butir</span>
        <span class="is-ok"><b>{{ rekap.sesuai }}</b> sesuai</span>
        <span class="is-nok"><b>{{ rekap.tidakSesuai }}</b> tidak sesuai</span>
        <span v-if="rekap.na"><b>{{ rekap.na }}</b> N/A</span>
        <span v-if="rekap.belum" class="is-nok"><b>{{ rekap.belum }}</b> belum dinilai</span>
        <span v-if="persen !== null" class="eq-persen">Kesesuaian {{ persen }}%</span>
      </div>

      <p class="eq-kunci">
        <b>✓</b> sesuai &nbsp;·&nbsp; <b>✗</b> tidak sesuai &nbsp;·&nbsp;
        <b>–</b> tidak berlaku &nbsp;·&nbsp; <b>☐</b> belum dinilai
      </p>

      <!-- Daftar tanda, dua lajur.

           Bukan tabel, karena lajur CSS tidak berlaku pada tabel dan
           membelah tabelnya menjadi dua secara manual berarti menebak
           di baris mana halamannya seimbang — tebakan yang salah
           setiap kali jumlah butirnya berubah. -->
      <div class="eq-periksa">
        <section v-for="g in berlajur" :key="g.nama" class="eq-blok">
          <h3 class="eq-kel">{{ g.huruf }}. {{ g.nama.toUpperCase() }}</h3>

          <div v-for="b in g.butir" :key="b.no" class="eq-baris">
            <span class="eq-no num">{{ b.no }}</span>
            <span class="eq-uraian">
              {{ b.uraian }}<i v-if="b.acuan"> · {{ b.acuan }}</i>
            </span>
            <span class="eq-tanda" :class="nada(b.kondisi)">{{ tanda(b.kondisi) }}</span>
          </div>
        </section>
      </div>

      <!-- Temuan mendapat ruang yang ditinggalkan butir yang sesuai. -->
      <section v-if="temuan.length" class="eq-temuan">
        <div class="eq-temuan-judul">TEMUAN DAN TINDAKAN PERBAIKAN</div>

        <table>
          <thead>
            <tr>
              <th class="eq-t-no">No</th>
              <th class="eq-t-butir">Butir yang Tidak Sesuai</th>
              <th class="eq-t-risiko">Risiko</th>
              <th class="eq-t-temuan">Temuan</th>
              <th class="eq-t-tindakan">Tindakan Perbaikan</th>
              <th class="eq-t-bukti">Bukti</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in temuan" :key="t.no">
              <td class="eq-t-no num">{{ t.no }}</td>
              <td>{{ t.uraian }}</td>
              <td class="eq-tengah">{{ t.risiko ?? '—' }}</td>
              <td>{{ t.temuan ?? '—' }}</td>
              <td>{{ t.tindakan ?? '—' }}</td>
              <td>
                <div v-if="t.foto.length" class="eq-foto">
                  <img v-for="(f, fi) in t.foto.slice(0, 2)" :key="fi" :src="f"
                       :alt="`Bukti butir ${t.no}`" loading="lazy">
                </div>
                <span v-else class="eq-sunyi">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </section>

      <section v-if="i.catatan" class="eq-catatan">
        <b>Catatan pemeriksa:</b> {{ i.catatan }}
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
  padding: 1.4rem 1.6rem 1.6rem;
  font-size: 9px;
  color: #1B1817;
  line-height: 1.3;
}

/* ── identitas ── */
.eq-identitas { width: 100%; border-collapse: collapse; margin-bottom: .45rem; table-layout: fixed; }
.eq-identitas th,
.eq-identitas td { border: 1px solid #D6D3D1; padding: .15rem .4rem; text-align: left; vertical-align: top; }
.eq-identitas th {
  width: 11%; background: #F5F5F4; font-weight: 700;
  font-size: 8px; text-transform: uppercase; letter-spacing: .03em;
}
.eq-identitas td { width: 22.33%; }

/* ── rekap ── */
.eq-rekap {
  display: flex; flex-wrap: wrap; align-items: baseline; gap: .1rem .9rem;
  border: 1px solid #D6D3D1; background: #FAFAF9;
  padding: .24rem .45rem; margin-bottom: .15rem;
}
.eq-rekap-label { font-size: 8px; font-weight: 800; letter-spacing: .07em; color: #57534E; }
.eq-rekap b { font-size: 11px; }
.eq-persen { margin-left: auto; font-weight: 800; }

/* ── daftar tanda, dua lajur ── */
.eq-periksa {
  column-count: 2;
  column-gap: 1.1rem;
  /* Garis pemisah lajur: tanpanya, mata membaca dua lajur sebagai satu
     kalimat panjang yang terpotong di tengah halaman. */
  column-rule: 1px solid #E7E5E4;
}

.eq-blok { break-inside: avoid-column; margin-bottom: .35rem; }

.eq-kel {
  background: #1C1917; color: #E7E5E4;
  font-size: 8px; font-weight: 800; letter-spacing: .06em;
  padding: .16rem .4rem; margin: 0 0 .1rem;
}

.eq-baris {
  display: flex; align-items: baseline; gap: .3rem;
  padding: .1rem .4rem .1rem .2rem;
  border-bottom: 1px solid #F0EFEE;
  break-inside: avoid;
}

.eq-no { flex: none; width: 1.1rem; text-align: right; color: #78716C; font-size: 8px; }
.eq-uraian { flex: 1 1 auto; min-width: 0; }
.eq-uraian i { color: #A8A29E; font-size: 7.5px; font-style: normal; }

.eq-tanda { flex: none; width: .9rem; text-align: center; font-weight: 800; font-size: 10px; }

.is-ok     { color: #15803D; }
.is-nok    { color: #B91C1C; font-weight: 800; }
.is-na     { color: #78716C; }
.is-kosong { color: #A8A29E; }

.eq-kunci { margin: 0 0 .3rem; text-align: right; font-size: 8px; color: #57534E; }
.eq-kunci b { font-size: 9.5px; }

/* ── temuan ── */

/* TANPA `break-inside: avoid`.

   Blok yang menolak dibelah dan tidak muat di sisa halaman pindah
   SELURUHNYA ke halaman berikutnya — menyisakan setengah halaman
   pertama kosong dan tetap menghasilkan dua halaman. Yang dijaga
   adalah barisnya, supaya tidak ada temuan yang terbelah di tengah. */
.eq-temuan { border: 1px solid #B91C1C; margin-bottom: .4rem; }
.eq-temuan-judul {
  background: #B91C1C; color: #fff;
  font-size: 8px; font-weight: 800; letter-spacing: .06em; padding: .22rem .45rem;
}
.eq-temuan table { width: 100%; border-collapse: collapse; table-layout: fixed; line-height: 1.25; }
.eq-temuan th,
.eq-temuan td { border: 1px solid #D6D3D1; padding: .16rem .3rem; vertical-align: top; text-align: left; }
.eq-temuan thead th {
  background: #F5F5F4; font-size: 7.5px; font-weight: 700;
  letter-spacing: .04em; text-transform: uppercase;
}

.eq-t-no       { width: 4%; text-align: center; }
.eq-t-butir    { width: 20%; }
.eq-t-risiko   { width: 7%; }
.eq-t-temuan   { width: 26%; }
.eq-t-tindakan { width: 28%; }
.eq-t-bukti    { width: 13%; }

.eq-tengah { text-align: center; }
.eq-sunyi { color: #A8A29E; }

.eq-foto { display: flex; flex-wrap: wrap; gap: .15rem; }
.eq-foto img { width: 46px; height: 34px; object-fit: cover; border: 1px solid #D6D3D1; border-radius: 2px; }

/* ── catatan ── */
.eq-catatan {
  border: 1px solid #D6D3D1; background: #FAFAF9;
  padding: .22rem .4rem; margin-bottom: .4rem; white-space: pre-line;
}

/* ── tanda tangan ── */
.eq-ttd {
  display: grid;
  grid-template-columns: repeat(var(--kolom, 2), minmax(0, 1fr));
  gap: .8rem;
  margin-top: .45rem;

  /* Tidak boleh terbelah antar halaman. Blok tanda tangan yang
     kepalanya di halaman satu dan garisnya di halaman dua membuat
     lembar itu ditolak saat diarsipkan. */
  break-inside: avoid;
  page-break-inside: avoid;
}

.eq-ttd-kotak { text-align: center; }
.eq-ttd-peran { font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #57534E; }
.eq-ttd-ruang { height: 1.05rem; }
.eq-ttd-nama { border-top: 1px solid #1B1817; padding-top: .15rem; font-weight: 700; }
.eq-ttd-jabatan { font-size: 8px; color: #57534E; }

/* ── saat dicetak ── */
@media print {
  .eq-lembar { padding: 0; }
  .eq-temuan thead { display: table-header-group; }
  .eq-temuan tr { break-inside: avoid; page-break-inside: avoid; }
}
</style>

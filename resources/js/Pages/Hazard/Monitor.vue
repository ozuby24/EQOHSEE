<script setup lang="ts">
/**
 * Hazard Report — monitor laporan.
 *
 * Saringan dikirim ke server, bukan disaring di peramban: yang dimuat
 * hanya satu halaman dari lima belas laporan, jadi menyaring di sini
 * hanya menyaring sisa yang kebetulan ikut terbawa.
 *
 * Kotak pencarian ditunda sebentar sebelum dikirim dan hanya memuat
 * ulang prop daftarnya, sehingga fokusnya tidak hilang di tengah orang
 * mengetik. Saringan pilihan dikirim seketika — orang sudah selesai
 * memutuskan begitu ia melepas pilihan.
 *
 * ── Kenapa tabel, bukan kartu ──
 *
 * Halaman ini bernama MONITOR, dan yang dilakukan orang di sini bukan
 * membaca satu laporan melainkan membandingkan lima belas sekaligus:
 * mana yang risikonya tinggi tetapi masih Open, mana yang sudah
 * ditutup tetapi tidak punya bukti foto perbaikan. Kartu menjawab
 * "apa isi laporan ini"; yang ditanyakan di sini "laporan mana yang
 * perlu saya kejar", dan itu pertanyaan perbandingan — kolom yang
 * sejajar menjawabnya, tumpukan kartu tidak.
 *
 * Dua kolom fotonya yang paling berbobot. Foto temuan sendirian tidak
 * memberi tahu apakah bahayanya sudah ditangani; yang memberi tahu
 * adalah ADA atau TIDAK ADA foto tindak lanjut di sebelahnya. Sebelum
 * ini keduanya baru terlihat sesudah laporannya dibuka satu per satu.
 *
 * ── Di layar sempit tabelnya menumpuk, tidak menggeser ──
 *
 * Satu markup, dua rupa: di bawah 64rem tiap baris menjadi kartu
 * berlabel lewat CSS. Tabel sebelas kolom yang digeser mendatar di
 * ponsel berarti kode laporan hilang dari layar tepat ketika orangnya
 * menggulir untuk melihat statusnya — dan laporan bahaya memang
 * dibaca dari ponsel di lapangan.
 */
import { onBeforeUnmount, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import type { HalamanMonitorBahaya } from '../../types';
import GaleriFoto from '../../Components/GaleriFoto.vue';
import DialogRegister from '../../Components/DialogRegister.vue';

const props = defineProps<HalamanMonitorBahaya>();

const isi = reactive({ ...props.saring });
const memuat = ref(false);

const HANYA = ['laporan', 'halaman', 'stat', 'saring', 'adaSaringan', 'tautan'];

function kirim(ganti = true) {
  memuat.value = true;

  /* Saringan kosong tidak ikut dikirim. Membawanya serta menghasilkan
     alamat seperti ?q=&bulan=&status= — yang lalu ikut tersalin saat
     orang membagikan tautan hasil saringannya. */
  const data: Record<string, string> = {};
  for (const [k, v] of Object.entries(isi)) {
    if (v !== null && v !== '') data[k] = String(v);
  }

  router.get('/hazard', data, {
    only: HANYA,
    preserveState: true,
    preserveScroll: true,
    replace: ganti,
    onFinish: () => { memuat.value = false; },
  });
}

let jeda: ReturnType<typeof setTimeout> | null = null;

watch(() => isi.q, () => {
  if (jeda) clearTimeout(jeda);
  jeda = setTimeout(kirim, 350);
});

onBeforeUnmount(() => { if (jeda) clearTimeout(jeda); });

function reset() {
  Object.assign(isi, {
    q: '', bulan: null, kategori: null, risiko: null, status: null, perusahaan: null,
  });
  kirim();
}

const kartu = [
  { kunci: 'total'  as const, label: 'Total laporan',              kelas: 'text-cam-ink' },
  { kunci: 'open'   as const, label: 'Open',                        kelas: 'text-red-500' },
  { kunci: 'proses' as const, label: 'In Progress',                 kelas: 'text-amber-500' },
  { kunci: 'closed' as const, label: 'Closed',                      kelas: 'text-emerald-600' },
  { kunci: 'tinggi' as const, label: 'Risiko tinggi belum tutup',   kelas: 'text-red-600' },
];

/* Register punya dialognya sendiri, terpisah dari deretan ekspor di
   sebelahnya. Ketiga tombol di bilah itu — CSV, PDF, WA — mengambil
   hasil saringan layar apa adanya; register tidak, dan menyandingkannya
   sebagai tombol keempat yang sama bentuknya akan membuat perbedaan itu
   tidak terlihat oleh siapa pun. */
const registerTerbuka = ref(false);

const pilihan =
  'ring-focus rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] font-semibold text-stone-600';
</script>

<template>
  <Head title="Monitor Hazard Report" />

  <!-- Lebar penuh, tanpa max-w. Sebelas kolom di dalam max-w-6xl
       mendorong Foto Tindak dan Aksi keluar layar — dua kolom yang
       justru paling dicari. -->
  <div class="space-y-5">

    <div class="grid gap-3 grid-cols-2 lg:grid-cols-5">
      <div v-for="k in kartu" :key="k.kunci"
           class="bg-white rounded-2xl shadow-card border border-stone-100 p-4">
        <div class="stat stat-sm" :class="k.kelas">{{ stat[k.kunci] }}</div>
        <div class="text-[11px] text-stone-400 mt-1.5 leading-tight">{{ k.label }}</div>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-3">
      <div class="flex flex-wrap items-center gap-2">
        <input v-model="isi.q" placeholder="Cari kode, lokasi, deskripsi, pelapor…"
               class="ring-focus flex-1 min-w-0 basis-[180px] rounded-xl border border-stone-200
                      px-4 py-2.5 text-[13px] transition">

        <select v-model="isi.bulan" :class="pilihan" @change="kirim()" aria-label="Bulan">
          <option :value="null">Semua bulan</option>
          <option v-for="b in opsi.bulan" :key="b.nilai" :value="b.nilai">{{ b.label }}</option>
        </select>

        <select v-model="isi.risiko" :class="pilihan" @change="kirim()" aria-label="Tingkat risiko">
          <option :value="null">Semua risiko</option>
          <option v-for="r in opsi.risiko" :key="r" :value="r">{{ r }}</option>
        </select>

        <select v-model="isi.status" :class="pilihan" @change="kirim()" aria-label="Status">
          <option :value="null">Semua status</option>
          <option v-for="s in opsi.status" :key="s" :value="s">{{ s }}</option>
        </select>

        <select v-model="isi.kategori" :class="pilihan" @change="kirim()" aria-label="Kategori">
          <option :value="null">Semua kategori</option>
          <option v-for="k in opsi.kategori" :key="k" :value="k">{{ k }}</option>
        </select>

        <select v-model="isi.perusahaan" :class="pilihan" @change="kirim()" aria-label="Perusahaan">
          <option :value="null">Semua perusahaan</option>
          <option v-for="c in opsi.perusahaan" :key="c.id" :value="String(c.id)">{{ c.nama }}</option>
        </select>

        <span class="text-[11.5px] text-stone-400 num px-1">
          {{ memuat ? 'memuat…' : `${halaman.total} laporan` }}
        </span>

        <button type="button" @click="reset"
                class="px-3 py-2.5 text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink">
          Reset
        </button>

        <a :href="tautan.buat"
           class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5 text-[12.5px]
                  font-bold hover:brightness-105 transition">+ Buat Laporan</a>
      </div>

      <div class="flex flex-wrap items-center gap-2 mt-2.5 pt-2.5 border-t border-stone-100">
        <span class="text-[11px] font-bold uppercase tracking-wide text-stone-400 px-1">
          Ekspor hasil saringan
        </span>
        <a :href="tautan.csv"
           class="rounded-lg border border-stone-200 px-3 py-1.5 text-[11.5px] font-bold text-stone-600
                  hover:border-cam-lime hover:bg-cam-lime-soft transition">⤓ Excel (CSV)</a>
        <a :href="tautan.cetak" target="_blank" rel="noopener"
           class="rounded-lg border border-stone-200 px-3 py-1.5 text-[11.5px] font-bold text-stone-600
                  hover:border-cam-lime hover:bg-cam-lime-soft transition">⎙ PDF</a>
        <a :href="tautan.wa" target="_blank" rel="noopener"
           class="rounded-lg bg-[#25D366] text-white px-3 py-1.5 text-[11.5px] font-bold
                  hover:brightness-105 transition">Bagikan ke Grup WA</a>
        <a :href="tautan.pengingat"
           class="ml-auto rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-[11.5px]
                  font-bold text-amber-700 hover:bg-amber-100 transition">Pengingat PIC →</a>
      </div>

      <div class="flex flex-wrap items-center gap-2 mt-2.5 pt-2.5 border-t border-stone-100">
        <span class="text-[11px] font-bold uppercase tracking-wide text-stone-400 px-1">
          Lembar terkendali
        </span>

        <button type="button" @click="registerTerbuka = true"
                class="rounded-lg border border-cam-lime bg-cam-lime-soft px-3 py-1.5 text-[11.5px]
                       font-bold text-cam-lime-deep hover:brightness-95 transition">
          ⤓ Register Tindakan Perbaikan (.xlsx)
        </button>

        <span class="text-[11px] text-stone-400">
          Berfoto tertanam, siap dicetak dan dibawa ke rapat bulanan.
        </span>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden
                transition-opacity" :class="memuat ? 'opacity-50' : ''">
      <div class="eq-gulung">
        <table v-if="laporan.length" class="eq-tabel">
          <thead>
            <tr>
              <th>ID Laporan</th>
              <th>Tanggal</th>
              <th>Pelapor</th>
              <th>Perusahaan Terlapor</th>
              <th>Lokasi</th>
              <th>Risiko</th>
              <th>Kategori</th>
              <th>Status</th>
              <th class="eq-th-foto">Foto Temuan</th>
              <th class="eq-th-foto">Foto Tindak</th>
              <th>Aksi</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="r in laporan" :key="r.id">
              <td data-kolom="ID Laporan" class="eq-sel-kode">
                <span class="eq-kode">{{ r.kode }}</span>

                <!-- Uraiannya tidak ada pada acuan, dan sengaja tetap di sini:
                     itulah satu-satunya kolom yang memberi tahu bahaya APA
                     yang sedang dibicarakan barisnya. Tanpa itu, tiap baris
                     harus dibuka dulu untuk diketahui isinya. -->
                <span class="eq-uraian">{{ r.deskripsi }}</span>
              </td>

              <td data-kolom="Tanggal" class="eq-nowrap num">{{ r.tanggal ?? '—' }}</td>
              <td data-kolom="Pelapor">{{ r.pelapor ?? '—' }}</td>

              <td data-kolom="Perusahaan Terlapor">
                <span :class="r.tujuan ? '' : 'text-stone-300'">{{ r.tujuan ?? '— belum diisi —' }}</span>
                <span v-if="r.terlapor" class="eq-terlapor">{{ r.terlapor }}</span>
              </td>

              <td data-kolom="Lokasi">{{ r.lokasi ?? '—' }}</td>

              <td data-kolom="Risiko">
                <span class="eq-pil" :style="{ background: r.warnaRisiko }">{{ r.risiko }}</span>
              </td>

              <td data-kolom="Kategori">
                <span v-if="r.kategori" class="eq-pil-abu">{{ r.kategori }}</span>
                <span v-else class="text-stone-300">—</span>
              </td>

              <td data-kolom="Status">
                <span class="eq-pil" :style="{ background: r.warnaStatus }">{{ r.status }}</span>
              </td>

              <td data-kolom="Foto Temuan">
                <GaleriFoto :foto="r.fotoTemuan" :label="`Foto temuan ${r.kode}`"
                            kosong="Tidak ada foto temuan" />
              </td>

              <td data-kolom="Foto Tindak">
                <GaleriFoto :foto="r.fotoTindak" :label="`Foto tindak lanjut ${r.kode}`"
                            kosong="Belum ada bukti tindak lanjut" />
              </td>

              <td data-kolom="Aksi">
                <Link :href="r.url" class="eq-detail">Detail</Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="!laporan.length" class="p-14 text-center">
        <template v-if="adaSaringan">
          <p class="text-[13px] text-stone-400">Tidak ada laporan yang cocok dengan saringan.</p>
          <button type="button" @click="reset"
                  class="inline-block mt-3 text-[12.5px] font-bold text-cam-lime-deep hover:underline">
            Hapus saringan →
          </button>
        </template>
        <template v-else>
          <p class="text-[13px] text-stone-400">Belum ada laporan bahaya.</p>
          <a :href="tautan.buat" class="inline-block mt-3 text-[12.5px] font-bold text-cam-lime-deep hover:underline">
            Buat laporan pertama →
          </a>
        </template>
      </div>
    </div>

    <div v-if="halaman.akhir > 1" class="flex flex-wrap gap-1.5">
      <component v-for="(t, i) in halaman.tautan" :key="i"
                 :is="t.url ? Link : 'span'" :href="t.url ?? undefined"
                 :only="HANYA" preserve-state preserve-scroll
                 class="min-w-[36px] text-center rounded-lg border px-3 py-1.5 text-[12px] font-semibold transition"
                 :class="t.aktif
                   ? 'bg-[color:var(--eq-aksen,#F57C00)] text-white border-transparent'
                   : t.url ? 'bg-white text-stone-600 border-stone-200 hover:border-stone-400'
                           : 'bg-white text-stone-300 border-stone-100 cursor-default'"
                 v-html="t.label" />
    </div>
  </div>

  <DialogRegister :terbuka="registerTerbuka" :opsi="opsi"
                  :url-unduh="tautan.register" :url-jumlah="tautan.registerJumlah"
                  @tutup="registerTerbuka = false" />
</template>

<style scoped>
/* ══════════════════════════════════════════════════════════════
   Tabel monitor. Lebar di layar besar, menumpuk di layar sempit.
   ══════════════════════════════════════════════════════════════ */

.eq-gulung { overflow-x: auto; }

.eq-tabel {
  width: 100%;
  border-collapse: collapse;
  font-size: 12.5px;
}

.eq-tabel thead th {
  position: sticky;
  top: 0;
  z-index: 1;
  padding: .7rem .6rem;
  background: #1C1917;
  color: #E7E5E4;
  font-size: 10.5px;
  font-weight: 700;
  letter-spacing: .04em;
  text-transform: uppercase;
  text-align: left;
  white-space: nowrap;
}

.eq-th-foto { text-align: center; }

.eq-tabel tbody td {
  padding: .6rem .6rem;
  border-top: 1px solid #F5F5F4;
  color: #57534E;
  vertical-align: middle;
}

.eq-tabel tbody tr:hover td { background: #FEFCE8; }

.eq-nowrap { white-space: nowrap; }

/* ── kolom pertama: kode + uraian ── */
.eq-sel-kode { min-width: 13rem; max-width: 20rem; }

.eq-kode {
  display: inline-block;
  padding: .1rem .4rem;
  border-radius: .3rem;
  background: #1C1917;
  color: #FFFFFF;
  font-size: 10.5px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  letter-spacing: .02em;
}

.eq-uraian {
  margin-top: .25rem;
  font-size: 12.5px;
  font-weight: 600;
  color: #1C1917;
  line-height: 1.4;

  /* Dua baris, lalu dipotong. Uraian bahaya kadang satu paragraf penuh,
     dan satu baris setinggi paragraf menghancurkan kesejajaran yang
     menjadi satu-satunya alasan tabel ini ada. */
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.eq-terlapor {
  display: block;
  font-size: 11px;
  color: #A8A29E;
  margin-top: .1rem;
}

/* ── pil ── */
.eq-pil {
  display: inline-block;
  padding: .12rem .5rem;
  border-radius: 99px;
  color: #FFFFFF;
  font-size: 10px;
  font-weight: 700;
  white-space: nowrap;
}

.eq-pil-abu {
  display: inline-block;
  padding: .12rem .5rem;
  border-radius: 99px;
  background: #F5F5F4;
  color: #78716C;
  font-size: 10px;
  font-weight: 600;
  white-space: nowrap;
}

/* ── aksi ── */
.eq-detail {
  display: inline-block;
  padding: .3rem .7rem;
  border: 1px solid #E7E5E4;
  border-radius: .5rem;
  font-size: 11.5px;
  font-weight: 700;
  color: #57534E;
  text-decoration: none;
  white-space: nowrap;
  transition: all .15s;
}

.eq-detail:hover { border-color: #F57C00; background: #FFF7ED; color: #C2410C; }

/* ══ layar sempit: tiap baris menjadi kartu berlabel ══

   Bukan gulung mendatar. Sebelas kolom yang digeser di ponsel membuat
   kode laporan hilang dari layar tepat saat orangnya menggulir untuk
   melihat status — dan laporan bahaya memang dibaca dari ponsel di
   lapangan. */
@media (max-width: 64rem) {
  .eq-gulung { overflow-x: visible; }

  .eq-tabel, .eq-tabel tbody, .eq-tabel tr, .eq-tabel td { display: block; width: 100%; }
  .eq-tabel thead { display: none; }

  .eq-tabel tbody tr {
    border-top: 1px solid #F5F5F4;
    padding: .85rem 1rem;
  }

  .eq-tabel tbody tr:hover td { background: transparent; }

  .eq-tabel tbody td {
    display: flex;
    align-items: center;
    gap: .6rem;
    padding: .2rem 0;
    border-top: 0;
  }

  .eq-tabel tbody td::before {
    content: attr(data-kolom);
    flex: none;
    width: 8.5rem;
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: .03em;
    text-transform: uppercase;
    color: #A8A29E;
  }

  /* Kode dan uraian jadi kepala kartu — tanpa label, karena label
     "ID Laporan" di atas kode laporan tidak memberi tahu apa pun.

     Pemilihnya disebut LENGKAP sampai `td`, bukan `.eq-sel-kode` saja:
     aturan `.eq-tabel tbody td` di atas lebih spesifik, jadi yang
     pendek kalah dan selnya tetap flex — kode dan uraian berdampingan
     pada satu baris, dan uraian bahayanya terhimpit di sisa lebar
     yang tinggal separuh. */
  .eq-tabel tbody td.eq-sel-kode {
    display: block;
    max-width: none;
    margin-bottom: .45rem;
  }

  .eq-tabel tbody td.eq-sel-kode::before { display: none; }

  .eq-uraian { -webkit-line-clamp: 3; }
}

/* ══ mode gelap ══ */
:global([data-tema='gelap']) .eq-tabel tbody td { border-color: #1E2E42; color: #A8A29E; }
:global([data-tema='gelap']) .eq-tabel tbody tr:hover td { background: #101A26; }
:global([data-tema='gelap']) .eq-uraian { color: #F5F5F4; }
:global([data-tema='gelap']) .eq-pil-abu { background: #1E2E42; color: #A8A29E; }
:global([data-tema='gelap']) .eq-detail { border-color: #1E2E42; color: #A8A29E; }
:global([data-tema='gelap']) .eq-detail:hover { background: #1E2E42; color: #FDBA74; }
:global([data-tema='gelap']) .eq-tabel tbody tr { border-color: #1E2E42; }
</style>

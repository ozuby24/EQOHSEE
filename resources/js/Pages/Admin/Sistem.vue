<script setup lang="ts">
/**
 * Pusat kendali sistem.
 *
 * Grafiknya digambar sebagai SVG di halaman ini, bukan lewat pustaka
 * bagan dari CDN. Versi sebelumnya memuat Chart.js dari jaringan luar:
 * di jaringan tambang yang tertutup — tempat aplikasi ini justru dipakai
 * — skripnya gagal dimuat dan ketiga panelnya kosong tanpa penjelasan.
 */
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { HalamanSistem } from '../../types';

const props = defineProps<HalamanSistem>();

/** Galat validasi tidak ditampilkan oleh tata letak; halaman ini sendiri
    yang menampilkannya — tanpa itu, penolakan penandaan data contoh
    berlalu tanpa satu kata pun. */
const galat = computed<Record<string, string>>(() => (usePage().props as any).errors ?? {});

/* ── donat sebaran peran ── */

const WARNA = ['#F57C00', '#0B1117', '#FACC15', '#FF9800', '#B85C00', '#B8BEC5', '#29ABE2'];
const KELILING = 2 * Math.PI * 42;

const totalPeran = computed(() => props.peran.reduce((a, p) => a + p.jumlah, 0));

/** Potongan donat beserta pergeserannya; peran bernilai nol dilewati. */
const potongan = computed(() => {
  const total = totalPeran.value;
  if (!total) return [];

  let lalu = 0;

  return props.peran
    .map((p, i) => {
      const panjang = (p.jumlah / total) * KELILING;
      const potong = { ...p, warna: WARNA[i % WARNA.length], panjang, geser: -lalu };
      lalu += panjang;
      return potong;
    })
    .filter((p) => p.jumlah > 0);
});

/* ── ringkasan diagnosa ── */

const perluDitindak = computed(
  () => (props.diagnosa.ringkas.gawat ?? 0)
      + (props.diagnosa.ringkas.perhatian ?? 0)
      + (props.diagnosa.ringkas['tak-tahu'] ?? 0),
);

/* ── tren log tujuh hari ── */

const maksTren = computed(() => Math.max(1, ...props.tren.map((t) => t.jumlah)));
const totalTren = computed(() => props.tren.reduce((a, t) => a + t.jumlah, 0));

/* ── aksi ── */

const pemeliharaanForm = useForm({});

function jalankan(url: string) {
  pemeliharaanForm.post(url, { preserveScroll: true });
}

function bersihkanLog() {
  if (!confirm('Kosongkan seluruh log aktivitas?')) return;
  router.delete(props.tautan.bersihkanLog, { preserveScroll: true });
}

/* ── data contoh ── */

type Perusahaan = HalamanSistem['perusahaan'][number];

const demoForm = useForm({ demo: true, sadar: '' });

const perusahaanContoh = computed(() => props.perusahaan.filter((c) => c.demo));

/** Jumlah baris data contoh satu perusahaan. */
function jumlahIsi(c: Perusahaan): number {
  return Object.values(c.isi ?? {}).reduce((a, n) => a + n, 0);
}

/** Tiga tabel terbesar, sekadar untuk mengenali sasarannya sekilas. */
function ringkasIsi(c: Perusahaan): string {
  const isi = Object.entries(c.isi ?? {}).sort((a, b) => b[1] - a[1]).slice(0, 3);

  return isi.length ? isi.map(([t, n]) => `${t} ${n}`).join(' · ') : 'belum ada data';
}

function tandai(c: Perusahaan) {
  /* Perusahaan yang sudah berisi menuntut namanya diketik. Penjagaan
     yang sama ada di server — yang di sini hanya menghemat satu
     perjalanan, bukan menggantikannya. */
  const isi = jumlahIsi(c);
  let sadar = '';

  if (isi > 0) {
    sadar =
      prompt(
        `${c.nama} sudah berisi ${isi} baris data.\n\n` +
          'Menandainya sebagai perusahaan contoh membuat seluruh data itu dapat dibuang ' +
          'oleh tombol muat ulang.\n\nKetik nama perusahaannya persis untuk menegaskan:',
      ) ?? '';

    if (!sadar) return;
  }

  demoForm.transform(() => ({ demo: true, sadar })).post(c.urlTandai, { preserveScroll: true });
}

function lepasTanda(c: Perusahaan) {
  demoForm.transform(() => ({ demo: false, sadar: '' })).post(c.urlTandai, { preserveScroll: true });
}

function muat(c: Perusahaan) {
  const isi = jumlahIsi(c);

  const pesan = isi
    ? `Muat ulang data contoh ${c.nama}?\n\n${isi} baris yang ada sekarang akan DIBUANG ` +
      'dan diganti dengan data contoh yang baru. Tindakan ini tidak dapat dibatalkan.'
    : `Muat data contoh untuk ${c.nama}?`;

  if (!confirm(pesan)) return;

  demoForm.transform(() => ({})).post(c.urlMuat, { preserveScroll: true });
}

/**
 * Buang data contoh tanpa mengisinya lagi.
 *
 * Terpisah dari "muat ulang" dan sengaja begitu: data contoh bertahan
 * sampai tombol INI ditekan. Itulah yang membuatnya dapat dipakai
 * memeriksa alur dari input sampai laporan tanpa khawatir isinya
 * hilang di tengah pemeriksaan.
 */
function buang(c: Perusahaan) {
  const isi = jumlahIsi(c);

  if (!isi) return;

  if (!confirm(
    `Buang ${isi} baris data contoh ${c.nama}?\n\n` +
    'Seluruh modulnya akan kosong kembali. Tindakan ini tidak dapat dibatalkan, ' +
    'tetapi data contoh memang dibuat untuk dibuang — jalankan ini setelah ' +
    'pemeriksaan selesai, sebelum pemasangan dipakai sungguhan.',
  )) return;

  demoForm.transform(() => ({})).delete(c.urlHapus, { preserveScroll: true });
}
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-6xl mx-auto space-y-5">

    <section class="brand-gradient rounded-2xl p-6 text-white shadow-card relative overflow-hidden">
      <div class="absolute -right-20 -top-20 w-56 h-56 rounded-full bg-cam-lime/20 blur-3xl"></div>
      <div class="relative">
        <span class="text-[10px] font-bold uppercase tracking-[0.22em] text-cam-lime-light">Status Server</span>
        <h2 class="stat mt-1.5 flex items-center gap-3">
          <span class="eq-denyut"></span>Sistem Berjalan Normal
        </h2>

        <div class="grid sm:grid-cols-3 lg:grid-cols-5 gap-2 mt-5">
          <div v-for="s in server" :key="s.label" class="glass rounded-xl px-3.5 py-2.5">
            <div class="text-[9px] uppercase tracking-[0.12em] text-white/62 font-bold clamp-1">{{ s.label }}</div>
            <div class="text-[12px] font-bold text-white mt-1 clamp-1">{{ s.nilai }}</div>
          </div>
        </div>
      </div>
    </section>

    <!-- Ringkasan diagnosa.

         Diletakkan di sini, bukan hanya di halamannya sendiri: halaman
         diagnosa hanya dibuka orang yang sudah curiga ada yang salah,
         sementara justru hal-hal yang diperiksanya adalah hal yang
         tidak menimbulkan kecurigaan apa pun. -->
    <Link :href="diagnosa.url"
          class="block rounded-2xl border shadow-card px-5 py-4 card-hover transition"
          :class="perluDitindak
            ? 'border-amber-100 bg-amber-50 hover:border-amber-400'
            : 'border-emerald-100 bg-emerald-50 hover:border-emerald-400'">
      <div class="flex items-center gap-4 flex-wrap">
        <div class="min-w-0 flex-1">
          <div class="text-[13.5px] font-bold text-cam-ink">
            {{ perluDitindak
                ? perluDitindak + ' pemeriksaan sistem perlu ditindak'
                : 'Seluruh pemeriksaan sistem lolos' }}
          </div>
          <div class="text-[11.5px] text-stone-500 mt-0.5 leading-relaxed">
            Mode debug, migrasi tertunda, izin berkas, tautan storage, antrean, nomor sertifikat
            kembar, dan baris tanpa perusahaan.
          </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <span v-for="k in ['gawat', 'perhatian', 'tak-tahu']" :key="k"
                v-show="diagnosa.ringkas[k]"
                class="text-[11px] font-bold px-2.5 py-1 rounded-lg"
                :class="{
                  'bg-red-100 text-red-700': k === 'gawat',
                  'bg-amber-100 text-amber-800': k === 'perhatian',
                  'bg-stone-100 text-stone-600': k === 'tak-tahu',
                }">
            {{ diagnosa.ringkas[k] }} {{ k === 'tak-tahu' ? 'tak diketahui' : k }}
          </span>
          <svg class="w-4 h-4 text-stone-400" fill="none" stroke="currentColor"
               stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" /></svg>
        </div>
      </div>
    </Link>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <Link v-for="p in pintasan" :key="p.label" :href="p.url"
            class="bg-white rounded-2xl shadow-card border border-stone-100 p-4 flex items-start gap-3.5
                   hover:border-cam-lime/40 card-hover transition">
        <div class="w-11 h-11 shrink-0 rounded-xl grid place-items-center" :style="{ background: p.warna + '22' }">
          <svg class="w-[22px] h-[22px]" fill="none" :stroke="p.warna" stroke-width="1.9" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" :d="p.ikon" /></svg>
        </div>
        <div class="min-w-0">
          <div class="text-[13px] font-bold text-cam-ink">{{ p.label }}</div>
          <div class="text-[11px] text-stone-400 mt-0.5">{{ p.sub }}</div>
        </div>
        <svg class="w-4 h-4 ml-auto mt-1 text-stone-300 shrink-0" fill="none" stroke="currentColor"
             stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" /></svg>
      </Link>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">

      <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-[14px] font-bold text-cam-ink">Sebaran Peran</h3>
          <span class="text-[11px] font-bold text-cam-lime-deep">{{ totalPeran }} pengguna</span>
        </div>

        <div class="flex items-center gap-5 flex-wrap">
          <svg viewBox="0 0 100 100" class="w-[132px] h-[132px] shrink-0 -rotate-90" role="img"
               aria-label="Sebaran peran pengguna">
            <circle cx="50" cy="50" r="42" fill="none" stroke="#F1F0EE" stroke-width="14" />
            <circle v-for="p in potongan" :key="p.label"
                    cx="50" cy="50" r="42" fill="none" stroke-width="14"
                    :stroke="p.warna"
                    :stroke-dasharray="`${p.panjang} ${KELILING - p.panjang}`"
                    :stroke-dashoffset="p.geser">
              <title>{{ p.label }}: {{ p.jumlah }}</title>
            </circle>
          </svg>

          <ul class="min-w-0 flex-1 space-y-1.5">
            <li v-for="(p, i) in peran" :key="p.label" class="flex items-center gap-2 text-[12px]">
              <span class="w-2.5 h-2.5 rounded-sm shrink-0"
                    :style="{ background: p.jumlah ? WARNA[i % WARNA.length] : '#E7E5E4' }"></span>
              <span class="text-stone-600 truncate">{{ p.label }}</span>
              <span class="ml-auto font-bold text-cam-ink num">{{ p.jumlah }}</span>
            </li>
          </ul>
        </div>
      </section>

      <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-[14px] font-bold text-cam-ink">Aktivitas 7 Hari</h3>
          <span class="text-[11px] font-bold text-cam-lime-deep">{{ totalTren }} log</span>
        </div>

        <div class="flex items-end gap-2 h-[132px]">
          <div v-for="t in tren" :key="t.label" class="flex-1 flex flex-col items-center gap-1.5 h-full justify-end">
            <span class="text-[10.5px] font-bold text-cam-ink num">{{ t.jumlah }}</span>
            <!-- Batang bernilai nol tetap diberi tinggi minimum supaya
                 harinya terlihat ada, bukan hilang dari sumbunya. -->
            <div class="w-full rounded-t-md lime-gradient transition-all"
                 :style="{ height: `calc(${(t.jumlah / maksTren) * 100}% + 3px)` }"></div>
            <span class="text-[10px] text-stone-400">{{ t.label }}</span>
          </div>
        </div>
      </section>
    </div>

    <section v-for="m in modul" :key="m.nama" class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <div class="flex items-center justify-between mb-3.5">
        <h3 class="text-[14px] font-bold text-cam-ink">{{ m.nama }}</h3>
        <Link v-if="m.url" :href="m.url" class="text-[11.5px] font-bold text-cam-lime-deep hover:underline">
          Buka modul →
        </Link>
      </div>

      <!-- Maksimal 5 kolom: pada 7 kolom label seperti "Percobaan kuis"
           terpotong sehingga kartu kehilangan maknanya. -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2.5">
        <div v-for="x in m.items" :key="x.label"
             class="group relative bg-white rounded-xl border border-stone-100 p-3.5 overflow-hidden
                    hover:border-cam-lime/50 hover:shadow-card transition">
          <span class="absolute left-0 top-0 bottom-0 w-[3px] bg-cam-lime/70 rounded-r"></span>
          <span class="absolute -right-5 -top-5 w-16 h-16 rounded-full bg-cam-lime/5
                       group-hover:bg-cam-lime/10 transition"></span>

          <div class="relative flex items-start justify-between gap-2">
            <div class="min-w-0">
              <div class="stat stat-sm text-cam-lime-deep leading-none">{{ x.nilai }}</div>
              <div class="text-[10.5px] text-stone-400 mt-1.5 leading-snug">{{ x.label }}</div>
            </div>
            <span class="shrink-0 w-8 h-8 rounded-lg grid place-items-center bg-cam-lime-soft
                         text-cam-lime-deep group-hover:scale-110 transition-transform">
              <svg class="w-[17px] h-[17px]" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="x.ikon" /></svg>
            </span>
          </div>
        </div>
      </div>
    </section>

    <section class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      <div class="flex items-center justify-between px-5 py-4 border-b border-stone-100">
        <h3 class="text-[14px] font-bold text-cam-ink">Perusahaan Terdaftar</h3>
        <a :href="tautan.perusahaanBaru" class="text-[11.5px] font-bold text-cam-lime-deep hover:underline">
          + Tambah
        </a>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-[12.5px]">
          <thead>
            <tr class="bg-stone-50 border-b border-stone-100">
              <th v-for="h in ['Perusahaan', 'Komoditas', 'Lokasi', 'Pekerja', 'Pengguna', 'Data contoh', '']" :key="h"
                  class="text-left font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-2.5">
                {{ h }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in perusahaan" :key="c.id" class="border-b border-stone-50 last:border-0 hover:bg-stone-50/60">
              <td class="px-4 py-2.5 font-bold text-cam-ink">{{ c.nama }}</td>
              <td class="px-4 py-2.5 text-stone-500">{{ c.komoditas ?? '—' }}</td>
              <td class="px-4 py-2.5 text-stone-500">{{ c.lokasi ?? '—' }}</td>
              <td class="px-4 py-2.5 text-stone-500 num">{{ c.pekerja }}</td>
              <td class="px-4 py-2.5 text-stone-500 num">{{ c.pengguna }}</td>
              <td class="px-4 py-2.5">
                <button v-if="c.demo" type="button" @click="lepasTanda(c)"
                        :disabled="demoForm.processing"
                        class="text-[10px] font-bold uppercase tracking-wide px-2 py-1 rounded
                               bg-amber-100 text-amber-800 hover:brightness-95 transition disabled:opacity-40"
                        title="Lepas tanda perusahaan contoh">
                  Perusahaan contoh
                </button>
                <button v-else type="button" @click="tandai(c)" :disabled="demoForm.processing"
                        class="text-[11px] font-semibold text-stone-400 hover:text-cam-lime-deep
                               hover:underline transition disabled:opacity-40">
                  Jadikan contoh
                </button>
              </td>
              <td class="px-4 py-2.5 text-right">
                <a :href="c.urlUbah" class="text-[11.5px] font-semibold text-cam-lime-deep hover:underline">Kelola</a>
              </td>
            </tr>
            <tr v-if="!perusahaan.length">
              <td colspan="7" class="px-4 py-10 text-center text-stone-400">Belum ada perusahaan.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Data contoh.

         Diletakkan sebagai bagian tersendiri, bukan sebagai satu tombol
         lagi di dalam tabel perusahaan: tombol ini membuang seluruh data
         satu perusahaan, dan tombol semacam itu tidak sepatutnya duduk
         sebaris dengan tombol "Kelola". -->
    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <div class="flex items-center justify-between mb-1">
        <h3 class="text-[14px] font-bold text-cam-ink">Data Contoh</h3>
        <span class="text-[11px] font-bold text-cam-lime-deep">
          {{ perusahaanContoh.length }} perusahaan contoh
        </span>
      </div>
      <p class="text-[11.5px] text-stone-400 leading-relaxed mb-3.5">
        Mengisi seluruh modul sekaligus — operasi, gudang, penirisan, geoteknik, lingkungan,
        peledakan, angkutan, biaya, dan izin kerja — supaya angka turunannya dapat diperiksa
        apakah masuk akal. Datanya sengaja tidak sempurna: ada bulan yang melampaui anggaran,
        lereng yang lajunya naik, pompa rusak, dan izin lewat waktu, supaya peringatannya
        benar-benar menyala.
      </p>

      <div v-if="galat.demo"
           class="rounded-xl border border-red-100 bg-red-50 px-4 py-3 mb-3.5 text-[12px]
                  text-red-700 leading-relaxed">
        {{ galat.demo }}
      </div>

      <div v-if="perusahaanContoh.length" class="space-y-2.5">
        <div v-for="c in perusahaanContoh" :key="c.id"
             class="rounded-xl border border-amber-100 bg-amber-50/60 px-4 py-3
                    flex flex-wrap items-center gap-3">
          <div class="min-w-0 flex-1">
            <div class="text-[13px] font-bold text-cam-ink">{{ c.nama }}</div>
            <div class="text-[11px] text-stone-500 mt-0.5">
              <span class="num font-semibold">{{ jumlahIsi(c) }}</span> baris ·
              {{ ringkasIsi(c) }}
            </div>
          </div>

          <div class="flex items-center gap-2 shrink-0">
            <button type="button" @click="muat(c)" :disabled="demoForm.processing"
                    class="rounded-xl bg-cam-lime-deep px-4 py-2 text-[11.5px] font-bold text-white
                           hover:brightness-95 transition disabled:opacity-40">
              {{ jumlahIsi(c) ? 'Muat ulang' : 'Muat data contoh' }}
            </button>

            <button v-if="jumlahIsi(c)" type="button" @click="buang(c)" :disabled="demoForm.processing"
                    class="rounded-xl border border-red-200 px-4 py-2 text-[11.5px] font-bold text-red-700
                           hover:bg-red-50 transition disabled:opacity-40">
              Hapus data contoh
            </button>
          </div>
        </div>
      </div>

      <p v-else class="text-[12px] text-stone-400 py-3">
        Belum ada perusahaan contoh. Tandai satu perusahaan pada tabel di atas — sebaiknya
        perusahaan yang memang dibuat untuk pengujian, sebab pemuatan membuang seluruh
        datanya lebih dulu.
      </p>
    </section>

    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[14px] font-bold text-cam-ink mb-3.5">Pemeliharaan Sistem</h3>
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
        <button v-for="p in pemeliharaan" :key="p.aksi" type="button"
                :disabled="pemeliharaanForm.processing" @click="jalankan(p.url)"
                class="rounded-xl border border-stone-200 px-3 py-2.5 text-[11.5px] font-bold text-stone-600
                       hover:border-cam-lime hover:bg-cam-lime-soft transition disabled:opacity-40">
          {{ p.label }}
        </button>
      </div>
      <p class="text-[11px] text-stone-400 mt-3 leading-relaxed">
        Jalankan setelah mengubah kode atau tampilan bila perubahan belum terlihat.
      </p>
    </section>

    <div>
      <div class="flex items-center justify-between mb-3">
        <h3 class="text-[14px] font-bold text-cam-ink">Log Aktivitas Terbaru</h3>
        <button v-if="log.length" type="button" @click="bersihkanLog"
                class="text-[11.5px] font-semibold text-red-500 hover:bg-red-50 px-3 py-1.5 rounded-lg">
          Bersihkan log
        </button>
      </div>
      <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
        <div v-for="l in log" :key="l.id"
             class="flex items-start gap-3 px-5 py-3 border-b border-stone-50 last:border-0">
          <div class="w-1.5 h-1.5 rounded-full bg-cam-lime mt-2 shrink-0"></div>
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-baseline gap-2">
              <span class="text-[12.5px] font-bold text-cam-ink">{{ l.aksi }}</span>
              <span class="text-[9.5px] font-bold uppercase tracking-wide bg-stone-100
                           text-stone-500 px-1.5 py-0.5 rounded">{{ l.modul }}</span>
            </div>
            <div v-if="l.detail" class="text-[12px] text-stone-400 mt-0.5">{{ l.detail }}</div>
          </div>
          <div class="text-right shrink-0">
            <div class="text-[11px] font-semibold text-stone-500">{{ l.oleh ?? '—' }}</div>
            <div class="text-[10.5px] text-stone-300">{{ l.waktu }}</div>
          </div>
        </div>

        <div v-if="!log.length" class="px-5 py-12 text-center text-[13px] text-stone-400">
          Belum ada aktivitas.
        </div>
      </div>
    </div>

  </div>
</template>

<style scoped>
@keyframes eqdenyut {
  0%, 100% { box-shadow: 0 0 0 0 rgba(245, 124, 0, .5); }
  70%      { box-shadow: 0 0 0 9px rgba(245, 124, 0, 0); }
}
.eq-denyut {
  width: 10px; height: 10px; border-radius: 9999px;
  background: #FF9800; flex: none; animation: eqdenyut 2s infinite;
}
</style>

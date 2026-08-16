<script setup lang="ts">
/**
 * Dasbor energi — dibaca untuk memutuskan, bukan untuk dilaporkan.
 *
 * Yang menggantikannya: satu kisi kartu yang menggambar setiap kunci
 * larik ringkasan apa adanya — "liter hari", "kwh ton", "tco2e" —
 * dengan angkanya di bawahnya. Dua puluh angka tanpa urutan
 * kepentingan, tanpa pembanding, dan tanpa satu pun yang mengatakan
 * apakah angka itu baik atau buruk. Pembacanya harus sudah tahu
 * jawabannya sebelum membuka halamannya.
 *
 * Susunannya sekarang mengikuti pertanyaan yang sesungguhnya dibawa
 * orang ke halaman ini, berurutan:
 *
 *   1. Apakah kita boros?          → intensitas terhadap target
 *   2. Sedang membaik atau memburuk? → tren harian
 *   3. Boros DI MANA?              → bauran energi dan peringkat alat
 *   4. Apa yang sedang dikerjakan? → peluang penghematan
 *
 * Angka yang tidak menjawab salah satunya tidak ada di layar utama; ia
 * tetap terjangkau lewat tombol "Lihat angkanya" pada tiap kartu, dan
 * lewat tab rinciannya.
 */
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import KartuGrafik from '../../Grafik/KartuGrafik.vue';
import Garis from '../../Grafik/Garis.vue';
import Batang from '../../Grafik/Batang.vue';
import Donat from '../../Grafik/Donat.vue';
import Meter from '../../Grafik/Meter.vue';
import Sparkline from '../../Grafik/Sparkline.vue';
import { AKSEN, KATEGORI, KEADAAN, ringkas } from '../../Grafik/warna';

const props = defineProps<{
  r: Record<string, any>;
  tren: any[];
  teratas: any[];
  peluang: any[];
  baseline: Record<string, any> | null;
}>();

const n = (v: unknown, d = 1) =>
  typeof v === 'number'
    ? v.toLocaleString('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d })
    : '—';

const hari = computed(() =>
  props.tren.map(t => new Date(t.tanggal).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })));

/* ── 1. apakah kita boros ── */

const target = computed(() => {
  const t = props.baseline?.target_gj_ton;

  return t === null || t === undefined ? null : Number(t);
});

const intensitas = computed(() =>
  props.r?.intensitas ? Number(props.r.intensitas) : null);

/**
 * Skala meter diambil dari nilai DAN targetnya, bukan dipatok.
 * Intensitas energi berukuran 0,02-an GJ/ton; jalur bertepi 100 akan
 * menggambar keduanya sebagai satu garis rapat di tepi kiri.
 */
const skalaIntensitas = computed(() => {
  const puncak = Math.max(intensitas.value ?? 0, target.value ?? 0);

  return puncak > 0 ? puncak * 1.6 : 1;
});

/* ── 2. tren ── */

const trenIntensitas = computed(() => [{
  nama: 'Intensitas energi',
  /* Hari tanpa produksi memulangkan null, bukan nol: alat tetap
     membakar solar saat tidak ada tonase, dan menggambarnya sebagai
     nol menarik garis ke bawah persis pada hari yang paling boros. */
  nilai: props.tren.map(t => (t.ton > 0 ? Number(t.intensitas) : null)),
}]);

/**
 * Solar dan listrik disandingkan SETELAH keduanya diubah ke GJ.
 * Liter dan kWh tidak dapat berbagi satu sumbu, dan dua sumbu pada
 * satu grafik membuat perpotongannya ditentukan pemilihan skala, bukan
 * datanya.
 */
const GJ_PER_LITER = 0.0358;
const GJ_PER_KWH   = 0.0036;

/**
 * Satu warna untuk satu sumber energi, DI SELURUH halaman.
 *
 * Sebelumnya grafik garis memakai jingga untuk solar sementara donat
 * di bawahnya memakai biru — warna dipilih menurut urutan di dalam
 * masing-masing grafik, bukan menurut apa yang diwakilinya. Pembaca
 * membaca warna sebagai identitas: dua grafik yang menukar warnanya
 * pada satu layar membuat perbandingan antar keduanya salah, dan
 * salahnya tidak terlihat sebagai kesalahan.
 */
const WARNA_SUMBER: Record<string, string> = {
  Solar:   KATEGORI[1],
  Listrik: KATEGORI[0],
  Gas:     KATEGORI[2],
};

const trenSumber = computed(() => [
  { nama: 'Solar',   nilai: props.tren.map(t => Number(t.liter) * GJ_PER_LITER), warna: WARNA_SUMBER.Solar },
  { nama: 'Listrik', nilai: props.tren.map(t => Number(t.kwh) * GJ_PER_KWH),     warna: WARNA_SUMBER.Listrik },
]);

/* ── 3. boros di mana ── */

const bauran = computed(() =>
  Object.entries(props.r?.rincian ?? {})
    .map(([nama, d]: [string, any]) => ({
      label: nama,
      nilai: Math.round(Number(d.gj)),
      warna: WARNA_SUMBER[nama],
    }))
    .filter(b => b.nilai > 0));

/**
 * `status` adalah OBJEK {kode, label, warna}, bukan teks.
 *
 * Dibandingkan sebagai teks — `u.status === 'Boros'` — perbandingannya
 * tidak pernah benar, jadi setiap alat tampil hijau termasuk yang
 * paling boros; dan menulisnya langsung ke tabel menampilkan JSON
 * mentah kepada yang membacanya. Keduanya sudah terjadi.
 */
const keadaanUnit = (u: any) =>
  u?.status?.kode === 'boros'  ? ('gawat' as const)
  : u?.status?.kode === 'pantau' ? ('ingat' as const)
  : u?.status?.kode === 'belum'  ? ('netral' as const)
  : ('baik' as const);

const peringkat = computed(() =>
  props.teratas.map(u => ({
    label: u.unit?.kode ?? u.unit?.nama ?? '—',
    nilai: Number(u.l_hm ?? 0),
    keadaan: keadaanUnit(u),
  })));

/**
 * Yang paling boros DIBANDING acuan kategorinya sendiri, bukan yang
 * angkanya terbesar: excavator memang lebih haus daripada grader, dan
 * daftar yang tidak memperhitungkannya akan selalu menuduh alat
 * bertenaga besar.
 */
const terboros = computed(() => {
  const boros  = props.teratas.filter(u => u.status?.kode === 'boros');
  const pantau = props.teratas.filter(u => u.status?.kode === 'pantau');

  if (boros.length)  return { kode: boros[0].unit?.kode, kata: 'membakar solar di atas rata-rata kelasnya' };
  if (pantau.length) return { kode: pantau[0].unit?.kode, kata: 'mulai menjauh dari rata-rata kelasnya' };

  return null;
});

const idle = computed(() => {
  const jam = props.teratas.reduce((j, u) => j + Number(u.idle ?? 0), 0);
  const hm  = props.teratas.reduce((j, u) => j + Number(u.hm ?? 0), 0);

  return hm > 0 ? (jam / hm) * 100 : null;
});

/* ── kartu angka ── */

const kartu = computed(() => [
  {
    label: 'Solar terpakai',
    nilai: ringkas(Number(props.r?.liter ?? 0)),
    satuan: 'L',
    catatan: `${ringkas(Number(props.r?.liter_hari ?? 0))} L/hari`,
    garis: props.tren.map(t => Number(t.liter)),
  },
  {
    label: 'Listrik terpakai',
    nilai: ringkas(Number(props.r?.kwh ?? 0)),
    satuan: 'kWh',
    catatan: null,
    garis: props.tren.map(t => Number(t.kwh)),
  },
  {
    label: 'Produksi',
    nilai: ringkas(Number(props.r?.ton ?? 0)),
    satuan: 'ton',
    catatan: `${ringkas(Number(props.r?.bcm ?? 0))} bcm`,
    garis: props.tren.map(t => Number(t.ton)),
  },
  {
    label: 'Emisi',
    nilai: n(Number(props.r?.tco2e ?? 0), 1),
    satuan: 'tCO₂e',
    catatan: `Rp ${ringkas(Number(props.r?.rupiah ?? 0))} biaya energi`,
    garis: [],
  },
]);
</script>

<template>
  <div class="space-y-4">

    <!-- 1 · apakah kita boros -->
    <section class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
      <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
        <h3 class="text-[14px] font-bold text-cam-ink">Intensitas energi</h3>
        <p class="text-[11.5px] text-stone-500 mt-0.5 mb-4">
          Energi yang terpakai untuk tiap ton yang keluar — angka yang dibandingkan
          antar periode dan antar site.
        </p>

        <Meter :nilai="intensitas" :target="target" :maks="skalaIntensitas"
               satuan=" GJ/ton" kecil-lebih-baik />

        <p v-if="!baseline" class="text-[11px] text-stone-400 mt-3">
          Belum ada baseline tahun ini, jadi tidak ada yang dapat dibandingkan.
          <Link href="/energi/baseline" class="text-cam-lime-deep font-semibold">Tetapkan baseline</Link>
        </p>
      </div>

      <div class="grid gap-3 sm:grid-cols-2">
        <article v-for="k in kartu" :key="k.label"
                 class="bg-white rounded-2xl shadow-card border border-stone-100 p-4">
          <p class="text-[11px] font-semibold text-stone-500">{{ k.label }}</p>

          <div class="flex items-end justify-between gap-2 mt-1">
            <p class="text-[20px] font-bold text-cam-ink leading-none num">
              {{ k.nilai }}<span class="text-[12px] font-semibold text-stone-400 ml-1">{{ k.satuan }}</span>
            </p>

            <Sparkline v-if="k.garis.length > 1" :nilai="k.garis" :warna="AKSEN" />
          </div>

          <p v-if="k.catatan" class="text-[10.5px] text-stone-400 mt-1.5">{{ k.catatan }}</p>
        </article>
      </div>
    </section>

    <!-- 2 · membaik atau memburuk -->
    <section class="grid gap-4 lg:grid-cols-2">
      <KartuGrafik judul="Intensitas harian"
                   catatan="Hari tanpa produksi sengaja diputus, bukan digambar nol."
                   :angka="`${tren.length} hari`">
        <Garis :label="hari" :deret="trenIntensitas" satuan="GJ/ton" bidang />

        <template #tabel>
          <table>
            <thead><tr><th>Tanggal</th><th>Intensitas (GJ/ton)</th><th>Ton</th></tr></thead>
            <tbody>
              <tr v-for="(t, i) in tren" :key="i">
                <td>{{ hari[i] }}</td>
                <td class="num">{{ t.ton > 0 ? n(Number(t.intensitas), 4) : 'tidak diukur' }}</td>
                <td class="num">{{ n(Number(t.ton), 0) }}</td>
              </tr>
            </tbody>
          </table>
        </template>
      </KartuGrafik>

      <KartuGrafik judul="Energi harian menurut sumber"
                   catatan="Solar dan listrik disamakan ke GJ lebih dulu supaya sebanding pada satu sumbu."
                   :angka="`${n(Number(r?.gj ?? 0), 1)} GJ`">
        <Garis :label="hari" :deret="trenSumber" satuan="GJ" />

        <template #tabel>
          <table>
            <thead><tr><th>Tanggal</th><th>Solar (GJ)</th><th>Listrik (GJ)</th></tr></thead>
            <tbody>
              <tr v-for="(t, i) in tren" :key="i">
                <td>{{ hari[i] }}</td>
                <td class="num">{{ n(Number(t.liter) * GJ_PER_LITER, 2) }}</td>
                <td class="num">{{ n(Number(t.kwh) * GJ_PER_KWH, 2) }}</td>
              </tr>
            </tbody>
          </table>
        </template>
      </KartuGrafik>
    </section>

    <!-- 3 · boros di mana -->
    <section class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
      <KartuGrafik judul="Bauran energi" catatan="Seluruhnya disetarakan ke GJ."
                   :angka="`${n(Number(r?.gj ?? 0), 0)} GJ`" :tinggi="150">
        <Donat :bagian="bauran" :tengah="ringkas(Number(r?.gj ?? 0))" tengah-label="GJ" />

        <template #tabel>
          <table>
            <thead><tr><th>Sumber</th><th>Jumlah</th><th>GJ</th><th>tCO₂e</th></tr></thead>
            <tbody>
              <tr v-for="(d, nama) in (r?.rincian ?? {})" :key="nama">
                <td>{{ nama }}</td>
                <td class="num">{{ n(Number(d.jumlah), 0) }} {{ d.satuan }}</td>
                <td class="num">{{ n(Number(d.gj), 1) }}</td>
                <td class="num">{{ n(Number(d.tco2e), 2) }}</td>
              </tr>
            </tbody>
          </table>
        </template>
      </KartuGrafik>

      <KartuGrafik judul="Alat paling haus"
                   :catatan="terboros
                     ? `${terboros.kode} ${terboros.kata}.`
                     : 'Semua alat masih di sekitar rata-rata kelasnya.'"
                   angka="liter per jam operasi" :tinggi="150">
        <Batang :baris="peringkat" satuan="L/HM" apa-adanya />

        <template #tabel>
          <table>
            <thead>
              <tr><th>Unit</th><th>L/HM</th><th>Acuan kelas</th><th>Liter</th><th>Keadaan</th></tr>
            </thead>
            <tbody>
              <tr v-for="u in teratas" :key="u.unit?.id">
                <td>{{ u.unit?.kode }} — {{ u.unit?.nama }}</td>
                <td class="num">{{ n(Number(u.l_hm), 2) }}</td>
                <td class="num">{{ n(Number(u.acuan), 2) }}</td>
                <td class="num">{{ n(Number(u.liter), 0) }}</td>
                <td>{{ u.status?.label ?? '—' }}</td>
              </tr>
            </tbody>
          </table>
        </template>
      </KartuGrafik>
    </section>

    <!-- 4 · apa yang sedang dikerjakan -->
    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <div class="flex items-center justify-between gap-3 mb-3">
        <div>
          <h3 class="text-[14px] font-bold text-cam-ink">Penghematan yang sedang berjalan</h3>
          <p v-if="idle !== null" class="text-[11.5px] text-stone-500 mt-0.5">
            {{ n(idle, 1) }}% jam operasi terbuang sebagai idle pada lima alat teratas.
          </p>
        </div>

        <Link href="/energi/penghematan" class="text-[11px] font-semibold text-cam-lime-deep shrink-0">
          Semua peluang
        </Link>
      </div>

      <ul v-if="peluang.length" class="divide-y divide-stone-100">
        <li v-for="p in peluang" :key="p.id" class="py-2.5 flex items-center gap-3">
          <span class="w-1.5 h-1.5 rounded-full shrink-0"
                :style="{ background: p.status === 'berjalan' ? KEADAAN.baik : KEADAAN.ingat }"></span>

          <span class="text-[12.5px] text-cam-ink min-w-0 flex-1 truncate">{{ p.judul }}</span>

          <span class="text-[11px] text-stone-400 shrink-0 capitalize">{{ p.status }}</span>

          <span class="text-[11.5px] font-bold text-cam-ink num shrink-0 w-24 text-right">
            {{ Number(p.hemat_liter) > 0 ? ringkas(Number(p.hemat_liter)) + ' L' : '' }}
            {{ Number(p.hemat_kwh) > 0 ? ringkas(Number(p.hemat_kwh)) + ' kWh' : '' }}
          </span>
        </li>
      </ul>

      <p v-else class="text-[12px] text-stone-400 py-4 text-center">
        Belum ada peluang penghematan yang disetujui atau berjalan.
      </p>
    </section>
  </div>
</template>

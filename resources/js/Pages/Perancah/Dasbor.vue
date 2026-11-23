<script setup lang="ts">
/*
  Dasbor Scaffolding Management.

  Urutan bacanya mengikuti pertanyaan pengawas di pagi hari: berapa
  perancah berdiri dan berapa yang LAYAK digunakan (panggung), mana yang
  harus didatangi hari ini (perlu tindakan, jatuh tempo), lalu polanya —
  tren inspeksi, zona, butir yang paling sering gagal — dan terakhir
  material yang sedang terpasang di lapangan.
*/
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Panggung from '../../Components/Energi/Panggung.vue';
import Kartu from '../../Components/Energi/Kartu.vue';
import KartuAngka from '../../Components/Energi/KartuAngka.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import PilihPerusahaan from '../../Components/Energi/PilihPerusahaan.vue';
import PetaTitik from '../../Components/Perancah/PetaTitik.vue';
import KartuGrafik from '../../Grafik/KartuGrafik.vue';
import Donat from '../../Grafik/Donat.vue';
import Kolom from '../../Grafik/Kolom.vue';
import BatangTumpuk from '../../Grafik/BatangTumpuk.vue';
import { KATEGORI } from '../../Grafik/warna';
import { n } from '../../energi';
import { tag, sisa } from '../../perancah';

const props = propHalaman();
const r = computed(() => props.ringkas ?? {});

const stat = computed(() => [
  { label: 'Perancah berdiri', nilai: n(r.value.aktif, 0), satuan: 'unit', ikon: 'construction', catatan: `${r.value.hijau} hijau · ${r.value.kuning} kuning` },
  { label: 'Layak digunakan', nilai: n(r.value.kepatuhan, 1), satuan: '%', ikon: 'shield-check', catatan: 'Tag hijau/kuning yang masih berlaku' },
  { label: 'Tidak layak digunakan', nilai: n((r.value.merah ?? 0) + (r.value.kedaluwarsa ?? 0), 0), satuan: 'unit', ikon: 'shield-x',
    catatan: `${r.value.merah} merah · ${r.value.kedaluwarsa} kedaluwarsa` },
  { label: 'Faktor keamanan rata-rata', nilai: n(r.value.sf_rata, 1), satuan: '×', ikon: 'scale', catatan: `Terendah ${n(r.value.sf_min, 1)}× · batas 4×` },
]);
const lencana = computed(() => [
  ...(r.value.jatuh_tempo ? [`${r.value.jatuh_tempo} jatuh tempo ≤ 48 jam`] : ['Tidak ada yang jatuh tempo 48 jam']),
  ...(r.value.perlu_pe ? [`${r.value.perlu_pe} perlu pengesahan P.E.`] : []),
  'Inspeksi ulang tiap 7 hari',
]);

const bagianStatus = computed(() => ['hijau', 'kuning', 'merah', 'kedaluwarsa']
  .map(k => ({ label: tag(k).pendek, nilai: Number(r.value[k] ?? 0), warna: tag(k).warna })));

const tren = computed(() => props.tren ?? []);
const deretTren = computed(() => [
  { nama: 'Layak', nilai: tren.value.map((t: any) => t.aman), warna: tag('hijau').warna },
  { nama: 'Layak bersyarat', nilai: tren.value.map((t: any) => t.jumlah - t.aman - t.bahaya), warna: tag('kuning').warna },
  { nama: 'Tidak layak', nilai: tren.value.map((t: any) => t.bahaya), warna: tag('merah').warna },
]);

const pareto = computed(() => props.pareto ?? []);
const kumulatif = computed(() => {
  const total = pareto.value.reduce((a: number, p: any) => a + p.jumlah, 0) || 1;
  let jalan = 0;
  return pareto.value.map((p: any) => { jalan += p.jumlah; return Math.round(jalan / total * 1000) / 10; });
});

const barisZona = computed(() => (props.zona ?? []).map((z: any) => ({
  label: z.zona, sub: `${z.total} unit`, url: `/perancah/peta?zona=${encodeURIComponent(z.zona)}`,
  bagian: [
    { nama: 'Hijau', nilai: z.hijau, warna: tag('hijau').warna },
    { nama: 'Kuning', nilai: z.kuning, warna: tag('kuning').warna },
    { nama: 'Merah / kedaluwarsa', nilai: z.merah, warna: tag('merah').warna },
  ],
})));

const bagianJenis = computed(() => (props.perJenis ?? []).map((j: any, i: number) => ({ label: j.label, nilai: j.nilai, warna: KATEGORI[(i + 3) % 8] })));
const perlu = computed(() => (props.perlu ?? []).map((u: any, urut: number) => ({ ...u, urut, w: tag(u.status) })));
const m = computed(() => props.material ?? {});
</script>

<template>
  <Head title="Scaffolding Management" />
  <div class="space-y-4">
    <section class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex flex-wrap gap-2">
        <Link href="/perancah/baru" class="eq-btn-utama inline-flex items-center gap-2"><Ikon nama="calculator" :ukuran="16" />Daftarkan &amp; hitung</Link>
        <Link href="/perancah/peta" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="map" :ukuran="16" />Daftar &amp; peta</Link>
        <a href="/perancah/ekspor/geojson" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="globe" :ukuran="16" />GeoJSON</a>
      </div>
      <PilihPerusahaan :daftar="props.daftarPerusahaan" :terpilih="props.perusahaan" />
    </section>

    <Panggung tema="perancah" judul="Status perancah di lapangan"
              :sub="`${n(r.aktif, 0)} unit berdiri · ${n(r.inspeksi_30, 0)} inspeksi dalam 30 hari · tingkat penolakan ${r.tolak_30 === null ? '—' : n(r.tolak_30, 1) + '%'}`"
              :stat="stat" :lencana="lencana" />

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
      <KartuGrafik judul="Komposisi tag aktif" :catatan="`Dibaca dari tag yang berlaku sekarang; ${r.kedaluwarsa} unit lewat 7 hari tanpa inspeksi ulang`" :angka="`${n(r.aktif, 0)} unit`">
        <Donat :bagian="bagianStatus" :tengah="n(r.kepatuhan, 0) + '%'" tengah-label="layak digunakan" />
      </KartuGrafik>
      <div class="ka-kisi content-start">
        <KartuAngka label="Jatuh tempo ≤ 48 jam" :nilai="r.jatuh_tempo" :desimal="0" satuan="unit" ikon="calendar-clock" warna="amber"
                    catatan="Jadwalkan inspeksi ulang sebelum tag lewat" />
        <KartuAngka label="Tingkat penolakan 30 hari" :nilai="r.tolak_30" :desimal="1" satuan="%" ikon="shield-alert" warna="merah"
                    :catatan="`${n(r.inspeksi_30, 0)} inspeksi · gagal butir kritis`" />
        <KartuAngka label="Perancah tertinggi" :nilai="r.tinggi_maks" :desimal="1" satuan="m" ikon="move-vertical" warna="ungu"
                    :catatan="r.perlu_pe ? `${r.perlu_pe} unit wajib disahkan P.E.` : 'Tidak ada yang > 15 m'" />
        <KartuAngka label="Dibongkar bulan ini" :nilai="r.dibongkar_bulan" :desimal="0" satuan="unit" ikon="package" warna="abu"
                    catatan="Keluar dari peta aktif" />
      </div>
    </section>

    <section class="grid gap-4 xl:grid-cols-2">
      <KartuGrafik judul="Inspeksi per minggu" catatan="12 minggu terakhir menurut putusan; garis = persen lulus penuh" :angka="`${tren.reduce((a: number, t: any) => a + t.jumlah, 0)} inspeksi`">
        <Kolom :label="tren.map((t: any) => t.label)" :deret="deretTren" :garis="{ nama: 'Lulus penuh', nilai: tren.map((t: any) => t.lulus), warna: '#2A78D6' }" satuan="inspeksi" :tinggi="220" />
      </KartuGrafik>
      <Kartu judul="Perancah per zona" ket="Klik zona untuk membuka petanya" ikon="map-pin" warna="amber">
        <BatangTumpuk :baris="barisZona" satuan="unit" />
      </Kartu>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
      <KartuGrafik judul="Butir inspeksi yang paling sering gagal" catatan="Pareto 90 hari — perbaiki dari kiri" :angka="`${pareto.reduce((a: number, p: any) => a + p.jumlah, 0)} temuan`">
        <Kolom :label="pareto.map((p: any) => p.kode)" :deret="[{ nama: 'Temuan gagal', nilai: pareto.map((p: any) => p.jumlah), warna: '#EB6834' }]"
               :garis="{ nama: 'Kumulatif', nilai: kumulatif, warna: '#4A3AA7' }" satuan="kali" :tinggi="210" />
        <template #tabel>
          <table><thead><tr><th>Kode</th><th>Butir</th><th>Kritis</th><th>Gagal</th></tr></thead>
            <tbody><tr v-for="p in pareto" :key="p.kode"><td>{{ p.kode }}</td><td>{{ p.label }}</td><td>{{ p.kritis ? 'Ya' : 'Tidak' }}</td><td class="num">{{ p.jumlah }}</td></tr></tbody></table>
        </template>
      </KartuGrafik>
      <KartuGrafik judul="Sistem perancah terpasang" catatan="Menurut jenis sistem" :angka="`${n(r.aktif, 0)} unit`">
        <Donat :bagian="bagianJenis" :tengah="n(r.aktif, 0)" tengah-label="unit" />
      </KartuGrafik>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]">
      <Kartu judul="Peta titik pemasangan" ket="Gulir untuk memperbesar · klik penanda untuk membuka unit" ikon="map" warna="amber" rapat>
        <div class="px-4 pb-4">
          <PetaTitik :titik="props.titik ?? []" :tinggi="360" @pilih="(id: number) => router.visit(`/perancah/${id}`)" />
        </div>
      </Kartu>
      <Kartu judul="Perlu tindakan hari ini" :ket="perlu.length ? 'Merah, kedaluwarsa, atau jatuh tempo ≤ 48 jam' : 'Tidak ada'" ikon="triangle-alert" warna="merah" rapat>
        <ul class="divide-y divide-stone-100">
          <li v-for="u in perlu" :key="u.id" class="px-5 py-3 flex items-center gap-3">
            <span class="w-2 self-stretch rounded-full shrink-0" :style="{ background: u.w.warna }" />
            <div class="min-w-0 flex-1">
              <Link :href="`/perancah/${u.id}`" class="text-[13px] font-bold text-cam-ink hover:underline">{{ u.kode }} · {{ u.judul }}</Link>
              <p class="text-[11.5px] text-stone-500 truncate">{{ u.zona }} · {{ u.dimensi }}
                <template v-if="u.inspeksi_berikut"> · tag {{ sisa(u.inspeksi_berikut) }}</template></p>
            </div>
            <span class="pr-pil" :class="u.w.kelas">{{ u.w.pendek }}</span>
            <Link :href="`/perancah/${u.id}/inspeksi`" class="eq-btn-mini">Inspeksi</Link>
          </li>
          <li v-if="!perlu.length" class="px-5 py-8 text-center text-[12.5px] text-stone-400">Semua perancah bertag berlaku dan tidak ada yang jatuh tempo.</li>
        </ul>
      </Kartu>
    </section>

    <Kartu judul="Material terpasang di lapangan" ket="Dijumlah dari BoM seluruh perancah yang masih berdiri" ikon="boxes" warna="biru">
      <div class="ka-kisi">
        <KartuAngka label="Pipa / tube" :nilai="m.pipa_m" :desimal="0" satuan="m" ikon="ruler" warna="biru" />
        <KartuAngka label="Steel plank" :nilai="m.plank" :desimal="0" satuan="lembar" ikon="layers" warna="amber" />
        <KartuAngka label="Coupler" :nilai="m.coupler" :desimal="0" satuan="buah" ikon="cog" warna="ungu" />
        <KartuAngka label="Berat baja terpasang" :nilai="m.berat_t" :desimal="1" satuan="ton" ikon="scale" warna="langit" />
        <KartuAngka label="Luas dek kerja" :nilai="m.luas_dek" :desimal="0" satuan="m²" ikon="construction" warna="hijau" />
      </div>
    </Kartu>
  </div>
</template>

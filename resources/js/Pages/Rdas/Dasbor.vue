<script setup lang="ts">
/*
  Dasbor Rehabilitasi DAS.

  Pertanyaan pertama pemegang PPKH sama dengan pertanyaan BPDAS: dari
  kewajiban sekian hektar, berapa yang sudah ditetapkan lokasinya, sudah
  ditanam, dan sudah diserahterimakan — dan berapa lama lagi waktunya.
  Corong itu dibaca lebih dulu, lalu apa yang harus ditangani (petak di
  bawah 75%, laporan, kebakaran, batas waktu), lalu peta dan polanya.
*/
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Panggung from '../../Components/Energi/Panggung.vue';
import Kartu from '../../Components/Energi/Kartu.vue';
import KartuAngka from '../../Components/Energi/KartuAngka.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import PilihPerusahaan from '../../Components/Energi/PilihPerusahaan.vue';
import PetaPoligon from '../../Components/Rdas/PetaPoligon.vue';
import KartuGrafik from '../../Grafik/KartuGrafik.vue';
import Donat from '../../Grafik/Donat.vue';
import Kolom from '../../Grafik/Kolom.vue';
import { n, rp } from '../../energi';
import { fase, warnaTumbuh, WAKTU, KATEGORI_JENIS, sisa } from '../../rdas';

const props = propHalaman();
const r = computed(() => props.ringkas ?? {});

const stat = computed(() => [
  { label: 'Kewajiban Rehab DAS', nilai: n(r.value.kewajiban, 0), satuan: 'ha', ikon: 'map', catatan: `${n(r.value.ditetapkan_pct, 0)}% lokasi sudah ditetapkan` },
  { label: 'Sudah ditanam', nilai: n(r.value.ditanam_pct, 0), satuan: '%', ikon: 'sprout', catatan: `${n(r.value.ditanam, 0)} ha · ${n(r.value.batang, 0)} batang` },
  { label: 'Diserahterimakan', nilai: n(r.value.bast_pct, 0), satuan: '%', ikon: 'file-check-2', catatan: `${n(r.value.bast, 0)} ha ber-BAST` },
  { label: 'Persen tumbuh rata-rata', nilai: n(r.value.tumbuh, 1), satuan: '%', ikon: 'trees', catatan: `${n(r.value.sehat_ha, 0)} batang sehat/ha · ambang 75%` },
]);
const lencana = computed(() => [
  ...(r.value.petak_sulam ? [`${r.value.petak_sulam} petak wajib sulam`] : ['Tidak ada petak di bawah 75%']),
  ...(r.value.batas_terdekat ? [`Batas ${r.value.batas_terdekat.kode}: ${sisa(r.value.batas_terdekat.sisa_hari)}`] : []),
  'PP 26/2020 · P.59/2019',
]);

const corong = computed(() => [
  { label: 'Kewajiban (1 : 1 luas PAK)', ha: r.value.kewajiban, pct: 100, warna: '#5C4326', ikon: 'map' },
  { label: 'Lokasi ditetapkan', ha: r.value.ditetapkan, pct: r.value.ditetapkan_pct, warna: '#7C6A3A', ikon: 'map-pin' },
  { label: 'Ditanam', ha: r.value.ditanam, pct: r.value.ditanam_pct, warna: '#3F9A4E', ikon: 'sprout' },
  { label: 'Diserahterimakan (BAST)', ha: r.value.bast, pct: r.value.bast_pct, warna: '#15803D', ikon: 'file-check-2' },
]);

const modePeta = ref<'tumbuh' | 'fase'>('tumbuh');
const bagianFase = computed(() => (props.fase ?? []).filter((f: any) => f.luas > 0).map((f: any) => ({ label: f.label, nilai: f.luas, warna: f.warna })));
const tren = computed(() => props.tren ?? []);
const bulan = computed(() => props.bulan ?? []);
const hist = computed(() => props.histogram ?? []);
const warnaHist = ['#991B1B', '#EF4444', '#F59E0B', '#65A30D', '#15803D'];
const komposisi = computed(() => (props.komposisi ?? []).map((k: any, i: number) => ({ label: k.label, nilai: k.batang,
  warna: k.kategori === 'lokal' ? ['#14532D', '#166534', '#15803D', '#1E6F3F'][i % 4] : k.kategori === 'cepat' ? ['#84CC16', '#A3E635', '#65A30D'][i % 3] : ['#D97706', '#F59E0B', '#B45309'][i % 3] })));
const tingkat: Record<string, string> = { kritis: 'rd-kritis', waspada: 'rd-waspada', info: 'rd-info' };
</script>

<template>
  <Head title="Rehabilitasi DAS" />
  <div class="space-y-4">
    <section class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex flex-wrap gap-2">
        <Link href="/rehab-das/blok/baru" class="eq-btn-utama inline-flex items-center gap-2"><Ikon nama="map-pin" :ukuran="16" />Daftarkan lokasi</Link>
        <Link href="/rehab-das/kewajiban" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="file-text" :ukuran="16" />Kewajiban PPKH</Link>
        <Link href="/rehab-das/peta" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="map" :ukuran="16" />Peta petak</Link>
        <Link href="/rehab-das/penilaian" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="clipboard-check" :ukuran="16" />Penilaian &amp; BAST</Link>
        <a href="/rehab-das/ekspor/geojson" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="globe" :ukuran="16" />GeoJSON</a>
      </div>
      <PilihPerusahaan :daftar="props.daftarPerusahaan" :terpilih="props.perusahaan" />
    </section>

    <Panggung tema="rdas" judul="Pemenuhan kewajiban Rehabilitasi DAS"
              :sub="`${n(r.ppkh, 0)} PPKH · ${n(r.blok, 0)} lokasi · ${n(r.petak, 0)} petak tanam · ${n(r.batang, 0)} batang tertanam`"
              :stat="stat" :lencana="lencana" />

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
      <Kartu judul="Progres pemenuhan kewajiban" ket="Dari kewajiban sampai serah terima — seperti dibaca BPDAS" ikon="trees" warna="hijau">
        <ol class="space-y-3">
          <li v-for="(c, i) in corong" :key="c.label" class="rd-corong">
            <span class="rd-corong-no" :style="{ background: c.warna }"><Ikon :nama="c.ikon" :ukuran="16" /></span>
            <div class="min-w-0 flex-1">
              <div class="flex items-baseline justify-between gap-2">
                <p class="text-[12.5px] font-bold text-cam-ink">{{ c.label }}</p>
                <p class="num text-[13px] font-extrabold text-cam-ink">{{ n(c.ha, 1) }} ha <span class="text-stone-500 font-bold">{{ i ? n(c.pct, 0) + '%' : '' }}</span></p>
              </div>
              <div class="rd-batang"><i :style="{ width: Math.max(1.5, Math.min(100, c.pct ?? 0)) + '%', background: c.warna }" /></div>
            </div>
          </li>
        </ol>
        <p v-if="(r.kewajiban ?? 0) - (r.ditetapkan ?? 0) > 0.5" class="mt-3 text-[12px] rd-teks-waspada">
          {{ n(r.kewajiban - r.ditetapkan, 1) }} ha belum ditetapkan lokasinya — ajukan sebelum tertinggal di garis start.
        </p>
      </Kartu>
      <Kartu judul="Perlu ditangani" :ket="(props.peringatan ?? []).length ? 'Diurutkan dari yang paling mendesak' : 'Tidak ada peringatan'" ikon="triangle-alert" warna="merah" rapat>
        <ul class="divide-y divide-stone-100 max-h-[380px] overflow-y-auto">
          <li v-for="a in props.peringatan" :key="a.judul + a.rinci">
            <Link :href="a.url" class="px-5 py-3 flex gap-3 rd-baris">
              <span class="rd-tanda" :class="tingkat[a.tingkat]"><Ikon :nama="a.ikon" :ukuran="15" /></span>
              <div class="min-w-0"><p class="text-[12.5px] font-bold text-cam-ink">{{ a.judul }}</p><p class="text-[11.5px] text-stone-500">{{ a.rinci }}</p></div>
            </Link>
          </li>
          <li v-if="!(props.peringatan ?? []).length" class="px-5 py-8 text-center text-[12.5px] text-stone-400">Semua kewajiban berjalan sesuai jadwal.</li>
        </ul>
      </Kartu>
    </section>

    <section class="ka-kisi">
      <KartuAngka label="Petak wajib sulam" :nilai="r.petak_sulam" :desimal="0" satuan="petak" ikon="sprout" warna="merah" :catatan="`${n(r.luas_sulam, 1)} ha di bawah 75% persen tumbuh`" />
      <KartuAngka label="Siap diajukan penilaian" :nilai="r.siap_nilai" :desimal="0" satuan="lokasi" ikon="clipboard-check" warna="biru" :catatan="`${r.penilaian} lokasi sedang dinilai tim`" />
      <KartuAngka label="Laporan semesteran" :nilai="r.laporan_terlambat" :desimal="0" satuan="terlambat" ikon="file-text" warna="amber" :catatan="`${r.laporan_tempo} jatuh tempo ≤ 30 hari`" />
      <KartuAngka label="Gangguan terbuka" :nilai="r.kejadian" :desimal="0" satuan="kejadian" ikon="flame" warna="oranye" catatan="Kebakaran, banjir, perambahan" />
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.55fr)_minmax(0,1fr)]">
      <Kartu judul="Peta petak tanam" ket="Gulir untuk memperbesar · seret untuk menggeser · klik petak untuk membuka lokasinya" ikon="map" warna="hijau">
        <template #aksi>
          <div class="rd-tab">
            <button :class="{ aktif: modePeta === 'tumbuh' }" @click="modePeta = 'tumbuh'">Persen tumbuh</button>
            <button :class="{ aktif: modePeta === 'fase' }" @click="modePeta = 'fase'">Fase</button>
          </div>
        </template>
        <PetaPoligon :fitur="props.peta ?? []" :mode="modePeta" :tinggi="440" @pilih="(id: number) => router.visit(`/rehab-das/blok/${id}`)" />
      </Kartu>
      <KartuGrafik judul="Luas menurut fase" catatan="Fase dihitung dari tanggal tanam P0 dan status penilaian" :angka="`${n(r.ditetapkan, 0)} ha`">
        <Donat :bagian="bagianFase" :tengah="n(r.blok, 0)" tengah-label="lokasi" />
      </KartuGrafik>
    </section>

    <section class="grid gap-4 xl:grid-cols-2">
      <KartuGrafik judul="Persen tumbuh per kuartal" catatan="Kolom = jumlah Petak Ukur (merah = wajib sulam); garis = persen tumbuh rata-rata" :angka="`${n(r.tumbuh, 1)}%`">
        <Kolom :label="tren.map((t: any) => t.label)" :deret="[{ nama: 'PU lulus', nilai: tren.map((t: any) => t.pu - t.sulam), warna: '#65A30D' }, { nama: 'PU wajib sulam', nilai: tren.map((t: any) => t.sulam), warna: '#DC2626' }]"
               :garis="{ nama: 'Persen tumbuh (%)', nilai: tren.map((t: any) => t.tumbuh), warna: '#14532D' }" satuan="PU" :tinggi="220" />
      </KartuGrafik>
      <KartuGrafik judul="Sebaran persen tumbuh petak" catatan="Petak di kiri garis 75% wajib disulam sebelum dinilai" :angka="`${n(r.petak, 0)} petak`">
        <Kolom :label="hist.map((h: any) => h.label)" :deret="hist.map((h: any, i: number) => ({ nama: h.label, nilai: hist.map((_: any, j: number) => (j === i ? h.petak : null)), warna: warnaHist[i] }))" satuan="petak" :tinggi="220" />
        <template #tabel>
          <table><thead><tr><th>Kelas</th><th>Petak</th><th>Luas (ha)</th></tr></thead>
            <tbody><tr v-for="h in hist" :key="h.label"><td>{{ h.label }}</td><td class="num">{{ h.petak }}</td><td class="num">{{ n(h.luas, 1) }}</td></tr></tbody></table>
        </template>
      </KartuGrafik>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
      <KartuGrafik judul="Tenaga kerja lapangan per bulan" catatan="Hari orang kerja (HOK) menurut fase · bibit dan biaya di tabel" :angka="`${n(bulan.reduce((a: number, b: any) => a + b.p0 + b.p1 + b.p2, 0), 0)} HOK`">
        <Kolom :label="bulan.map((b: any) => b.label)" :deret="[{ nama: 'P0', nilai: bulan.map((b: any) => b.p0), warna: '#F59E0B' }, { nama: 'P1', nilai: bulan.map((b: any) => b.p1), warna: '#84CC16' }, { nama: 'P2', nilai: bulan.map((b: any) => b.p2), warna: '#15803D' }]" satuan="HOK" :tinggi="220" />
        <template #tabel>
          <table><thead><tr><th>Bulan</th><th>HOK</th><th>Bibit</th><th>Biaya (Rp juta)</th></tr></thead>
            <tbody><tr v-for="b in bulan" :key="b.label"><td>{{ b.label }}</td><td class="num">{{ b.p0 + b.p1 + b.p2 }}</td><td class="num">{{ n(b.bibit, 0) }}</td><td class="num">{{ n(b.biaya, 1) }}</td></tr></tbody></table>
        </template>
      </KartuGrafik>
      <KartuGrafik judul="Komposisi jenis tertanam" catatan="Batang menurut rancangan · hijau tua lokal berdaur panjang, hijau muda cepat tumbuh, jingga MPTS" :angka="`${(props.komposisi ?? []).length} jenis teratas`">
        <Donat :bagian="komposisi" tengah-label="batang" />
      </KartuGrafik>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
      <Kartu judul="Serapan biaya terhadap progres fisik" ket="Biaya kegiatan terverifikasi ÷ anggaran RKP, dibanding tahap fisik" ikon="hand-coins" warna="amber">
        <ul class="space-y-2.5">
          <li v-for="s in props.serapan" :key="s.kode">
            <Link :href="`/rehab-das/blok/${s.id}`" class="block">
              <div class="flex justify-between text-[12px]"><span class="font-bold text-cam-ink">{{ s.kode }} · {{ s.nama }}</span><span class="num text-stone-500">biaya {{ n(s.serapan, 0) }}% · fisik {{ n(s.fisik, 0) }}%</span></div>
              <div class="rd-ganda">
                <i class="rd-ganda-biaya" :style="{ width: Math.min(100, s.serapan) + '%' }" />
                <i class="rd-ganda-fisik" :style="{ width: Math.min(100, s.fisik) + '%' }" />
              </div>
            </Link>
          </li>
        </ul>
        <div class="flex gap-4 mt-3 text-[11px] font-bold text-stone-500"><span><i class="rd-leg rd-ganda-biaya" />Serapan biaya</span><span><i class="rd-leg rd-ganda-fisik" />Progres fisik</span></div>
      </Kartu>
      <Kartu judul="Kewajiban per PPKH" ket="Klik untuk membuka rinciannya" ikon="file-text" warna="hijau" rapat>
        <div class="overflow-x-auto">
          <table class="w-full text-[12px]">
            <thead><tr class="rd-kepala"><th>PPKH</th><th class="text-right">Kewajiban</th><th class="text-right">Ditetapkan</th><th class="text-right">Ditanam</th><th class="text-right">BAST</th><th>Batas penyelesaian</th></tr></thead>
            <tbody class="divide-y divide-stone-100">
              <tr v-for="p in props.perPpkh" :key="p.id" class="rd-baris" @click="router.visit(`/rehab-das/kewajiban/${p.id}`)">
                <td><p class="font-bold text-cam-ink">{{ p.kode }}</p><p class="text-[10.5px] text-stone-500 truncate max-w-[220px]">{{ p.nomor_sk }}</p></td>
                <td class="text-right num font-bold">{{ n(p.kewajiban, 1) }} ha</td>
                <td class="text-right num">{{ n(p.ditetapkan_pct, 0) }}%</td>
                <td class="text-right num">{{ n(p.ditanam_pct, 0) }}%</td>
                <td class="text-right num font-bold">{{ n(p.bast_pct, 0) }}%</td>
                <td><span class="pr-pil" :class="WAKTU[p.waktu]?.kelas">{{ sisa(p.sisa_hari) }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </Kartu>
    </section>
  </div>
</template>

<style scoped>
.rd-corong { display: flex; gap: 12px; align-items: center; }
.rd-corong-no { width: 34px; height: 34px; border-radius: 11px; display: grid; place-items: center; color: #fff; flex: none; }
.rd-batang { height: 12px; border-radius: 999px; background: #F0EFED; overflow: hidden; margin-top: 5px; }
.rd-batang i { display: block; height: 100%; border-radius: 999px; }
.rd-teks-waspada { color: #B45309; font-weight: 700; }
.rd-baris { cursor: pointer; }
.rd-baris:hover { background: #F7FBF4; }
.rd-tanda { width: 30px; height: 30px; border-radius: 10px; display: grid; place-items: center; flex: none; }
.rd-kritis { background: #FEE2E2; color: #B91C1C; } .rd-waspada { background: #FEF3C7; color: #B45309; } .rd-info { background: #DBEAFE; color: #1D4ED8; }
.rd-tab { display: inline-flex; gap: 2px; padding: 3px; border-radius: 10px; background: #F5F5F4; }
.rd-tab button { padding: 4px 10px; border-radius: 8px; font-size: 11.5px; font-weight: 700; color: #57534E; }
.rd-tab button.aktif { background: #fff; color: #166534; box-shadow: 0 1px 3px rgba(0, 0, 0, .08); }
.rd-ganda { position: relative; height: 14px; border-radius: 7px; background: #F0EFED; margin-top: 4px; overflow: hidden; }
.rd-ganda i { position: absolute; left: 0; display: block; }
.rd-ganda-biaya { top: 0; height: 7px; background: #D97706; }
.rd-ganda-fisik { bottom: 0; height: 7px; background: #15803D; }
.rd-leg { display: inline-block; width: 12px; height: 6px; border-radius: 3px; margin-right: 6px; position: static; vertical-align: middle; }
.rd-kepala th { text-align: left; font-size: 11px; font-weight: 700; color: #78716C; padding: 8px 12px; background: #FAFAF9; }
.rd-kepala th:first-child, .rd-baris td:first-child { padding-left: 20px; }
.rd-baris td { padding: 9px 12px; }
:root[data-tema="gelap"] .rd-batang, :root[data-tema="gelap"] .rd-ganda { background: #2E373C; }
:root[data-tema="gelap"] .rd-teks-waspada { color: #F6CF7A; }
:root[data-tema="gelap"] .rd-baris:hover { background: #20272B; }
:root[data-tema="gelap"] .rd-kritis { background: #4E1D1D; color: #F5A9A9; }
:root[data-tema="gelap"] .rd-waspada { background: #3D2E10; color: #F6CF7A; }
:root[data-tema="gelap"] .rd-info { background: #0F2C3D; color: #93D2F5; }
:root[data-tema="gelap"] .rd-tab { background: #20272B; }
:root[data-tema="gelap"] .rd-tab button { color: #B4BEC3; }
:root[data-tema="gelap"] .rd-tab button.aktif { background: #2E373C; color: #8FE3BE; }
:root[data-tema="gelap"] .rd-kepala th { background: #20272B; color: #93A1A8; }
</style>

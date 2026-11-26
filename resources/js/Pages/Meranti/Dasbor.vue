<script setup lang="ts">
/*
  Dashboard Analitik MERANTI.

  Empat angka yang dibaca pertama sama dengan MERANTI: stok bibit,
  realisasi rehabilitasi DAS terhadap target, luas reklamasi terhadap
  bukaan (PKAR), dan skor keberhasilan Matrik 17. Lalu tren realisasi
  per bulan, komposisi P0/P1/P2, realisasi per blok, rencana vs realisasi
  reklamasi, peta spasial, dan yang perlu ditangani.
*/
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Panggung from '../../Components/Energi/Panggung.vue';
import Kartu from '../../Components/Energi/Kartu.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import PilihPerusahaan from '../../Components/Energi/PilihPerusahaan.vue';
import PetaGeo from '../../Components/Meranti/PetaGeo.vue';
import PetaSatelit from '../../Components/Meranti/PetaSatelit.vue';
import KartuGrafik from '../../Grafik/KartuGrafik.vue';
import Donat from '../../Grafik/Donat.vue';
import Kolom from '../../Grafik/Kolom.vue';
import Garis from '../../Grafik/Garis.vue';
import Batang from '../../Grafik/Batang.vue';
import { n, rp } from '../../energi';
import { BULAN_PENDEK, WARNA_GOLONGAN, WARNA_PEKERJAAN, LABEL_PEKERJAAN } from '../../meranti';

const props = propHalaman();
const d = computed(() => props.dasbor ?? {});
const k = computed(() => d.value.kartu ?? {});

const stat = computed(() => [
  { label: 'Total stok bibit', nilai: n(k.value.totalStok, 0), satuan: 'batang', ikon: 'leaf', catatan: `${k.value.jenisBibit} jenis bibit · ${k.value.jenisMaterial} jenis material` },
  { label: 'Realisasi rehabilitasi DAS', nilai: n(k.value.totalRealisasi, 2), satuan: 'ha', ikon: 'sprout',
    catatan: k.value.persenTarget !== null ? `${n(k.value.persenTarget, 1)}% dari target ${n(k.value.totalTarget, 2)} ha · ${k.value.recordProgres} catatan` : `${k.value.recordProgres} catatan progres · target belum diisi` },
  { label: 'Luas reklamasi', nilai: n(k.value.luasReklamasi, 2), satuan: 'ha', ikon: 'layers',
    catatan: k.value.pkar !== null ? `PKAR ${n(k.value.pkar, 1)}% dari ${n(k.value.luasBukaan, 2)} ha bukaan` : 'Rencana reklamasi belum diisi' },
  { label: 'Skor keberhasilan', nilai: k.value.skor === null ? '—' : n(k.value.skor, 1), satuan: k.value.skor === null ? null : 'skor', ikon: 'badge-check',
    catatan: k.value.skor === null ? 'Belum ada penilaian' : `${k.value.kategoriSkor} · rata-rata ${k.value.penilaian} penilaian Matrik 17` },
]);
const lencana = computed(() => [
  ...(props.bibitHabis?.length ? [`${props.bibitHabis.length} jenis bibit stok habis`] : ['Stok bibit tersedia']),
  ...(props.pengajuan?.menunggu ? [`${props.pengajuan.menunggu} pengajuan dana menunggu`] : []),
  d.value.tahun === 'semua' ? 'Semua tahun' : `Tahun ${d.value.tahun}`,
]);

function pilihTahun(t: string) {
  router.get('/meranti', { ...(props.perusahaan ? { perusahaan: props.perusahaan } : {}), tahun: t }, { preserveScroll: true });
}

const pekerjaan = computed(() => Object.entries(d.value.pekerjaan ?? {}).map(([kode, v]) => ({ label: `${kode} ${LABEL_PEKERJAAN[kode] ?? ''}`, nilai: Number(v), warna: WARNA_PEKERJAAN[kode] })));
const totalPekerjaan = computed(() => pekerjaan.value.reduce((a, b) => a + b.nilai, 0));
const blok = computed(() => (d.value.blok ?? []).map((b: any) => ({ label: b.label, nilai: b.nilai, url: '/meranti/progres-mingguan' })));
const luas = computed(() => d.value.luas ?? { label: [], bukaan: [], reklamasi: [] });
const pkarPerTahun = computed(() => luas.value.label.map((_: string, i: number) => luas.value.bukaan[i] > 0 ? Math.round(luas.value.reklamasi[i] / luas.value.bukaan[i] * 1000) / 10 : null));
const golongan = computed(() => (d.value.golongan ?? []).map((g: any) => ({ label: g.label, nilai: g.nilai, warna: WARNA_GOLONGAN[g.label] ?? WARNA_GOLONGAN.Lainnya })));

const akses = [
  { slug: 'progres-mingguan', label: 'Progres Mingguan', ket: 'Rehabilitasi DAS per bulan', ikon: 'sprout' },
  { slug: 'aktual-pencapaian', label: 'Aktual Pencapaian', ket: '% P0 · P1 · P2 per blok', ikon: 'chart-column' },
  { slug: 'target-blok', label: 'Target Blok', ket: 'Rencana luas per blok', ikon: 'target' },
  { slug: 'stok-bibit', label: 'Stok Bibit', ket: 'Persediaan bibit', ikon: 'leaf' },
  { slug: 'penilaian-keberhasilan', label: 'Penilaian Keberhasilan', ket: 'Matrik 17 (skor)', ikon: 'badge-check' },
  { slug: 'jaminan-reklamasi', label: 'Jaminan Reklamasi', ket: 'Status jaminan', ikon: 'landmark' },
];
const perGrup = computed(() => {
  const g: Record<string, any[]> = {};
  for (const m of props.jumlahModul ?? []) (g[m.grup] ??= []).push(m);
  return g;
});
// Peta satelit bila sumber ubinnya ada; kembali ke skema bila WebGL atau citranya gagal.
const satelit = ref(!!props.ubin);
const tglPendek = (v: string | null) => (v ? new Date(v + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '—');
</script>

<template>
  <Head title="MERANTI — Dashboard Analitik" />
  <div class="space-y-5">
    <section class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Pilih tahun">
        <button type="button" class="md-chip" :class="{ aktif: d.tahun === 'semua' }" @click="pilihTahun('semua')">Semua tahun</button>
        <button v-for="t in d.tahunList ?? []" :key="t" type="button" class="md-chip" :class="{ aktif: String(d.tahun) === String(t) }" @click="pilihTahun(String(t))">{{ t }}</button>
      </div>
      <PilihPerusahaan :daftar="props.daftarPerusahaan" :terpilih="props.perusahaan" />
    </section>

    <Panggung tema="meranti" judul="Monitoring reklamasi & pembibitan" :sub="`MERANTI · ${props.namaPerusahaan}`" :stat="stat" :lencana="lencana" />

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
      <KartuGrafik judul="Tren realisasi per bulan" catatan="Luas realisasi (ha) dari Progres Mingguan rehabilitasi DAS" :angka="`${n(k.totalRealisasi, 2)} ha`">
        <Garis :label="BULAN_PENDEK" :deret="[{ nama: 'Luas realisasi', nilai: d.perBulan ?? [], warna: '#2E7D55' }]" satuan="ha" bidang :tinggi="210" />
      </KartuGrafik>
      <KartuGrafik judul="Komposisi pekerjaan" catatan="P0 Penanaman · P1 Pemeliharaan I · P2 Pemeliharaan II (ha)" :angka="`${n(totalPekerjaan, 2)} ha`">
        <Donat :bagian="pekerjaan" :tengah="n(totalPekerjaan, 1)" tengah-label="ha" />
      </KartuGrafik>
    </section>

    <section class="grid gap-4 xl:grid-cols-2">
      <KartuGrafik judul="Realisasi per blok" catatan="Enam blok dengan realisasi terluas — klik untuk membuka Progres Mingguan" :angka="`${(d.blok ?? []).length} blok`">
        <Batang v-if="blok.length" :baris="blok" satuan="ha" satuan-tampak />
        <p v-else class="text-[12px] text-stone-400 py-6 text-center">Belum ada progres mingguan.</p>
      </KartuGrafik>
      <KartuGrafik judul="Rencana vs realisasi luas reklamasi" catatan="Kolom = luas bukaan (hijau = sudah direklamasi); garis = PKAR %" :angka="k.pkar !== null ? `PKAR ${n(k.pkar, 1)}%` : null">
        <Kolom v-if="luas.label.length" :label="luas.label"
               :deret="[{ nama: 'Luas reklamasi (ha)', nilai: luas.reklamasi, warna: '#2E7D55' }, { nama: 'Bukaan belum direklamasi (ha)', nilai: luas.bukaan.map((b: number, i: number) => Math.max(0, b - luas.reklamasi[i])), warna: '#C9A227' }]"
               :garis="{ nama: 'PKAR (%)', nilai: pkarPerTahun, warna: '#0A3A2A' }" satuan="ha" :tinggi="210" />
        <p v-else class="text-[12px] text-stone-400 py-6 text-center">Belum ada rencana reklamasi.</p>
      </KartuGrafik>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)] items-start">
      <Kartu judul="Peta spasial" :ket="`${(props.peta ?? []).length} fitur · ${satelit && props.ubin ? 'citra satelit & medan 3D' : 'gulir untuk memperbesar'} · klik fitur untuk membuka peta lengkap`" ikon="map" warna="hijau">
        <template #aksi><Link href="/meranti/peta" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="map" :ukuran="15" />Peta lengkap</Link></template>
        <PetaSatelit v-if="satelit && props.ubin" :fitur="props.peta ?? []" :ubin="props.ubin" :tinggi="440" @pilih="() => router.visit('/meranti/peta')" @gagal="satelit = false" />
        <PetaGeo v-else :fitur="props.peta ?? []" :tinggi="420" :label="false" @pilih="() => router.visit('/meranti/peta')" />
      </Kartu>

      <div class="space-y-4">
        <Kartu judul="Perlu perhatian" ikon="triangle-alert" warna="amber" rapat>
          <ul class="divide-y divide-stone-100 text-[12.5px]">
            <li v-if="props.bibitHabis?.length" class="px-5 py-3">
              <Link href="/meranti/stok-bibit" class="font-bold text-cam-ink hover:underline">{{ props.bibitHabis.length }} jenis bibit stok habis</Link>
              <p class="text-[11.5px] text-stone-500">{{ props.bibitHabis.join(', ') }}</p>
            </li>
            <li v-if="props.pengajuan?.menunggu" class="px-5 py-3">
              <Link href="/meranti/pengajuan-dana" class="font-bold text-cam-ink hover:underline">{{ props.pengajuan.menunggu }} pengajuan dana berstatus Diajukan</Link>
              <p class="text-[11.5px] text-stone-500">Total seluruh pengajuan {{ rp(props.pengajuan.total) }}</p>
            </li>
            <li v-if="k.persenTarget !== null && k.persenTarget < 100" class="px-5 py-3">
              <Link href="/meranti/aktual-pencapaian" class="font-bold text-cam-ink hover:underline">Realisasi DAS {{ n(k.persenTarget, 1) }}% dari target</Link>
              <p class="text-[11.5px] text-stone-500">Kurang {{ n(Math.max(0, k.totalTarget - k.totalRealisasi), 2) }} ha — lihat capaian per blok</p>
            </li>
            <li v-if="props.periodeAktif" class="px-5 py-3">
              <Link href="/meranti/periode-pengajuan" class="font-bold text-cam-ink hover:underline">Periode aktif: {{ props.periodeAktif.nama_periode }}</Link>
              <p class="text-[11.5px] text-stone-500">{{ tglPendek(props.periodeAktif.tanggal_mulai) }} – {{ tglPendek(props.periodeAktif.tanggal_selesai) }}</p>
            </li>
            <li v-if="!props.bibitHabis?.length && !props.pengajuan?.menunggu && !props.periodeAktif && !(k.persenTarget !== null && k.persenTarget < 100)" class="px-5 py-5 text-stone-400 text-center">Tidak ada yang perlu ditangani.</li>
          </ul>
        </Kartu>

        <Kartu judul="Nursery 30 hari terakhir" ikon="leaf" warna="hijau" rapat>
          <ul class="grid grid-cols-5 text-center">
            <li v-for="mu in props.mutasi ?? []" :key="mu.kunci">
              <Link :href="`/meranti/${mu.slug}`" class="block px-1 py-3 md-mutasi">
                <span class="block text-[18px] font-black num text-cam-ink">{{ n(mu.jumlah, 0) }}</span>
                <span class="block text-[10.5px] font-bold text-stone-500">{{ mu.label }}</span>
              </Link>
            </li>
          </ul>
          <div v-if="golongan.length" class="px-5 pb-4">
            <p class="text-[11px] font-bold text-stone-500 mb-1">Stok per golongan</p>
            <div class="md-bilah"><i v-for="g in golongan" :key="g.label" :style="{ background: g.warna, flexGrow: Math.max(g.nilai, 0.0001) }" :title="`${g.label}: ${n(g.nilai, 0)} batang`" /></div>
            <p class="flex flex-wrap gap-x-3 gap-y-1 mt-2 text-[11px] text-stone-600"><span v-for="g in golongan" :key="g.label"><b :style="{ color: g.warna }">●</b> {{ g.label }} {{ n(g.nilai, 0) }}</span></p>
          </div>
        </Kartu>

        <Kartu judul="Jaminan reklamasi" :ket="rp(props.jaminan?.total ?? 0)" ikon="landmark" warna="amber" rapat>
          <ul class="divide-y divide-stone-100 text-[12.5px]">
            <li v-for="j in props.jaminan?.perStatus ?? []" :key="j.label" class="px-5 py-2.5 flex items-center justify-between gap-3">
              <span>{{ j.label }} <span class="text-stone-400">· {{ j.n }}</span></span><b class="num">{{ rp(j.nilai) }}</b>
            </li>
            <li v-if="!(props.jaminan?.perStatus ?? []).length" class="px-5 py-4 text-stone-400 text-center">Belum ada jaminan tercatat.</li>
          </ul>
        </Kartu>
      </div>
    </section>

    <section>
      <h2 class="text-[15px] font-black text-cam-ink mb-2">Akses cepat</h2>
      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <Link v-for="a in akses" :key="a.slug" :href="`/meranti/${a.slug}`" class="md-akses">
          <span class="md-akses-ikon"><Ikon :nama="a.ikon" :ukuran="18" /></span>
          <span><b class="block text-[13px] text-cam-ink">{{ a.label }}</b><span class="text-[11.5px] text-stone-500">{{ a.ket }}</span></span>
        </Link>
      </div>
    </section>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <Kartu v-for="(daftar, grup) in perGrup" :key="grup" :judul="String(grup)" :ket="`${daftar.reduce((a: number, m: any) => a + m.n, 0)} baris`" ikon="database" rapat>
        <ul class="divide-y divide-stone-100 text-[12.5px]">
          <li v-for="m in daftar" :key="m.kunci">
            <Link :href="`/meranti/${m.slug}`" class="px-5 py-2 flex items-center justify-between gap-3 md-baris">
              <span class="inline-flex items-center gap-2"><Ikon :nama="m.ikon" :ukuran="14" />{{ m.judul }}</span><b class="num">{{ n(m.n, 0) }}</b>
            </Link>
          </li>
        </ul>
      </Kartu>
    </section>
  </div>
</template>

<style scoped>
.md-chip { padding: 6px 13px; border-radius: 999px; border: 1px solid #E7E5E4; background: #fff; font-size: 12px; font-weight: 700; color: #44403C; }
.md-chip.aktif { border-color: #2E7D55; background: #ECF7F0; color: #0A3A2A; }
.md-mutasi:hover { background: #F4FAF6; }
.md-bilah { display: flex; height: 10px; border-radius: 999px; overflow: hidden; background: #F1F5F9; }
.md-akses { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: 16px; background: #fff; border: 1px solid #E7E5E4; transition: border-color .15s, transform .15s; }
.md-akses:hover { border-color: #4CA771; transform: translateY(-1px); }
.md-akses-ikon { width: 38px; height: 38px; border-radius: 12px; display: grid; place-items: center; background: #ECF7F0; color: #1F6B42; flex: none; }
.md-baris:hover { background: #F7FBF8; }
:root[data-tema="gelap"] .md-chip { background: #20272B; border-color: #2E373C; color: #D5DDE1; }
:root[data-tema="gelap"] .md-chip.aktif { background: #12271D; border-color: #22C55E; color: #8FE3BE; }
:root[data-tema="gelap"] .md-mutasi:hover, :root[data-tema="gelap"] .md-baris:hover { background: #20272B; }
:root[data-tema="gelap"] .md-akses { background: #1B2124; border-color: #2E373C; }
:root[data-tema="gelap"] .md-akses-ikon { background: #12271D; color: #8FE3BE; }
</style>

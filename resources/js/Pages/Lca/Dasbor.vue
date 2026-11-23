<script setup lang="ts">
/*
  Dasbor Life Cycle Assessment.

  Membaca satu studi utama (yang terbit/terverifikasi terbaru, atau yang
  dipilih): berapa jejaknya per unit fungsional dan ke mana arahnya
  terhadap baseline (panggung), dari tahap mana (aliran), seberapa berat
  di keenam kategori dampak (radar), baris mana yang paling menentukan
  (hotspot), lalu portofolio seluruh produk, tren gate-to-gate dari data
  Energy Performance, alur verifikasi, dan nilai ekonomi penurunannya.
*/
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Panggung from '../../Components/Energi/Panggung.vue';
import Kartu from '../../Components/Energi/Kartu.vue';
import KartuAngka from '../../Components/Energi/KartuAngka.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import PilihPerusahaan from '../../Components/Energi/PilihPerusahaan.vue';
import AliranTahap from '../../Components/Lca/AliranTahap.vue';
import KartuGrafik from '../../Grafik/KartuGrafik.vue';
import Radar from '../../Grafik/Radar.vue';
import Garis from '../../Grafik/Garis.vue';
import BatangTumpuk from '../../Grafik/BatangTumpuk.vue';
import { n, rp } from '../../energi';

const props = propHalaman();
const u = computed(() => props.utama ?? null);
const tahapPilih = ref<string | null>(null);
const WARNA: Record<string, string> = Object.fromEntries((props.perTahap ?? []).map((t: any) => [t.tahap, t.warna]));
const PENDEK: Record<string, string> = Object.fromEntries((props.perTahap ?? []).map((t: any) => [t.tahap, t.pendek]));

const desimal = (v: number) => (Math.abs(v) >= 100 ? 0 : Math.abs(v) >= 10 ? 1 : 2);
const arah = computed(() => {
  if (!u.value?.baseline) return null;
  const p = ((u.value.gwp - u.value.baseline) / u.value.baseline) * 100;
  return { teks: `${p < 0 ? '−' : '+'}${n(Math.abs(p), 1)}% vs baseline`, baik: p <= 0 };
});
const stat = computed(() => u.value ? [
  { label: 'Jejak karbon (GWP100)', nilai: n(u.value.gwp, desimal(u.value.gwp)), satuan: 'kg CO₂e', ikon: 'cloud', arah: arah.value,
    catatan: `per ${u.value.fu}${u.value.target ? ' · target ' + n(u.value.target, desimal(u.value.target)) : ''}` },
  { label: 'Energi kumulatif (CED)', nilai: n(u.value.ced, desimal(u.value.ced)), satuan: 'MJ', ikon: 'zap', catatan: `per ${u.value.fu}` },
  { label: 'Kelangkaan air (AWARE)', nilai: n(u.value.wsf, 2), satuan: 'm³ eq', ikon: 'droplets', catatan: `per ${u.value.fu}` },
  { label: 'Kualitas data (DQR)', nilai: n(u.value.dqr, 2), ikon: 'badge-check', catatan: `${u.value.mutu} · 1 terbaik, 5 terlemah` },
] : []);
const lencana = computed(() => u.value ? [u.value.status_label, 'ISO 14040/14044', `${n(u.value.ton_tahun, 0)} t CO₂e/tahun`] : []);

const dampak = computed(() => props.dampak ?? []);
/* Skala logaritmik: keenam kategori berbeda beberapa orde besaran setelah
   dinormalisasi, dan skala linear membuat lima di antaranya menempel di
   pusat. Log menjaga urutan dan tetap memperlihatkan bentuk profilnya. */
const logSkala = computed(() => {
  const v = dampak.value.map((d: any) => Math.abs(d.normal)).filter((x: number) => x > 0);
  const lo = Math.log10(Math.min(...v, 1)), hi = Math.log10(Math.max(...v, 1e-12));
  return (x: number) => (x <= 0 ? 6 : hi - lo < 1e-9 ? 100 : 18 + 82 * (Math.log10(x) - lo) / (hi - lo));
});
const radar = computed(() => ({
  sumbu: dampak.value.map((d: any) => ({ label: d.kode, sub: d.nama })),
  deret: [{ nama: u.value?.produk ?? 'Studi', warna: '#15803D',
    nilai: dampak.value.map((d: any) => logSkala.value(Math.abs(d.normal))),
    teks: dampak.value.map((d: any) => `${n(d.nilai, Math.abs(d.nilai) < 0.01 ? 6 : Math.abs(d.nilai) < 10 ? 3 : 1)} ${d.satuan}`) }],
}));

const hotspot = computed(() => (props.hotspot ?? []).filter((h: any) => !tahapPilih.value || h.tahap === tahapPilih.value).map((h: any, urut: number) => ({ ...h, urut })));
const maksHot = computed(() => Math.max(1e-9, ...hotspot.value.map((h: any) => h.gwp)));

const portofolio = computed(() => (props.portofolio ?? []).map((s: any) => ({
  label: `${s.produk}`, sub: `${n(s.gwp, desimal(s.gwp))} kg CO₂e/${s.fu_label} · ${s.status_label}`, url: `/lca/studi/${s.id}`,
  bagian: Object.keys(WARNA).map(k => ({ nama: PENDEK[k], nilai: Math.max(0, Number(s.tahap?.[k] ?? 0)), warna: WARNA[k] })),
})));

const tren = computed(() => props.tren ?? []);
const harga = computed(() => props.harga ?? {});
const sk = computed(() => props.skenarioTerbaik ?? null);
const statusUrut = computed(() => (props.status ?? []).filter((s: any) => s.status !== 'arsip'));
function gantiStudi(e: Event) { router.get('/lca', { studi: (e.target as HTMLSelectElement).value || undefined }, { preserveScroll: true }); }
</script>

<template>
  <Head title="Life Cycle Assessment" />
  <div class="space-y-4">
    <section class="flex flex-wrap items-end justify-between gap-3">
      <div class="flex flex-wrap items-end gap-2">
        <label class="text-[11px] font-semibold text-stone-500">Studi ditampilkan
          <select class="block mt-1 rounded-lg border-stone-200 text-[12px] min-w-[280px]" :value="u?.id ?? ''" @change="gantiStudi">
            <option v-for="s in props.pilihanStudi" :key="s.id" :value="s.id">{{ s.label }}</option>
          </select>
        </label>
        <Link href="/lca/studi" class="eq-btn-utama inline-flex items-center gap-2"><Ikon nama="flask-conical" :ukuran="16" />Studi LCA</Link>
        <Link v-if="u" :href="`/lca/studi/${u.id}`" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="search-check" :ukuran="16" />Buka studi</Link>
      </div>
      <PilihPerusahaan :daftar="props.daftarPerusahaan" :terpilih="props.perusahaan" />
    </section>

    <Panggung v-if="u" tema="lca" :judul="`Jejak daur hidup · ${u.produk}`"
              :sub="`${u.kode} v${u.versi} · per ${u.fu} · ${u.batas_label} · ${u.periode}`" :stat="stat" :lencana="lencana" />
    <section v-else class="rounded-2xl p-10 text-center bg-white border border-stone-100">
      <p class="text-[15px] font-bold text-cam-ink">Belum ada studi LCA</p>
      <p class="text-[12.5px] text-stone-500 mt-1">Buat studi pertama — inventorinya dapat ditarik langsung dari data Energy Performance.</p>
      <Link href="/lca/studi" class="eq-btn-utama inline-flex mt-4">Buat studi</Link>
    </section>

    <template v-if="u">
      <Kartu judul="Aliran emisi lima tahap daur hidup" :ket="`kg CO₂e per ${u.fu} — klik tahap untuk menyaring hotspot`" ikon="route" warna="hijau">
        <AliranTahap :tahap="props.perTahap" :terpilih="tahapPilih" @pilih="(t: string | null) => (tahapPilih = t)" />
      </Kartu>

      <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
        <KartuGrafik judul="Profil enam kategori dampak" catatan="Dinormalisasi per kapita dunia/tahun, skala logaritmik — arahkan ke sumbu untuk angka aslinya">
          <Radar :sumbu="radar.sumbu" :deret="radar.deret" :ukuran="330" />
          <template #tabel>
            <table><thead><tr><th>Kategori</th><th>Nilai</th><th>Satuan</th><th>Ternormalisasi</th></tr></thead>
              <tbody><tr v-for="d in dampak" :key="d.kategori"><td>{{ d.kode }} · {{ d.nama }}</td><td class="num">{{ n(d.nilai, 6) }}</td><td>{{ d.satuan }}</td><td class="num">{{ n(d.normal, 8) }}</td></tr></tbody></table>
          </template>
        </KartuGrafik>
        <Kartu judul="Hotspot — kontributor terbesar" :ket="tahapPilih ? `Tahap ${PENDEK[tahapPilih]} · klik tahap lagi untuk semua` : 'Pareto GWP: perbaiki dari atas'" ikon="target" warna="merah" rapat>
          <ol class="divide-y divide-stone-100">
            <li v-for="h in hotspot" :key="h.id" class="px-5 py-3">
              <div class="flex items-baseline justify-between gap-3">
                <p class="text-[12.5px] font-bold text-cam-ink min-w-0"><span class="text-stone-400 num mr-1.5">{{ h.urut + 1 }}</span>{{ h.nama }}</p>
                <p class="text-[12.5px] font-extrabold num shrink-0">{{ n(h.gwp, desimal(h.gwp)) }} <small class="text-stone-400">kg</small></p>
              </div>
              <div class="mt-1.5 flex items-center gap-2">
                <span class="h-2 rounded-full" :style="{ width: Math.max(4, h.gwp / maksHot * 70) + '%', background: WARNA[h.tahap] }" />
                <span class="text-[11px] text-stone-500 num whitespace-nowrap">{{ n(h.porsi, 1) }}% · kumulatif {{ n(h.kumulatif, 0) }}%</span>
              </div>
              <p class="text-[10.5px] text-stone-400 mt-0.5">{{ PENDEK[h.tahap] }} · DQR {{ n(h.dqr, 1) }}</p>
            </li>
            <li v-if="!hotspot.length" class="px-5 py-8 text-center text-[12.5px] text-stone-400">Tidak ada kontributor positif pada tahap ini.</li>
          </ol>
        </Kartu>
      </section>
    </template>

    <section class="grid gap-4 xl:grid-cols-2">
      <Kartu judul="Portofolio produk" ket="Komposisi tahap per studi — klik untuk membuka" ikon="layers" warna="hijau">
        <BatangTumpuk :baris="portofolio" relatif />
      </Kartu>
      <KartuGrafik judul="Intensitas gate-to-gate bulanan" catatan="Dihitung langsung dari solar, genset & listrik di Energy Performance ÷ produksi" angka="kg CO₂e/t">
        <Garis :label="tren.map((t: any) => t.label)" :deret="[{ nama: 'Gate-to-gate', nilai: tren.map((t: any) => t.nilai), warna: '#15803D' }]" satuan="kg CO₂e/t" bidang :tinggi="220" />
      </KartuGrafik>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
      <Kartu judul="Alur verifikasi ISO 14044" ket="Studi terverifikasi dikunci; perubahan dibuat sebagai versi baru" ikon="workflow" warna="biru">
        <div class="flex flex-wrap items-center gap-2">
          <template v-for="(s, i) in statusUrut" :key="s.status">
            <div class="ld-langkah"><p class="text-[22px] font-black num text-cam-ink">{{ s.jumlah }}</p><p class="text-[11px] font-bold text-stone-500">{{ s.label }}</p></div>
            <Ikon v-if="Number(i) < statusUrut.length - 1" nama="arrow-up-right" :ukuran="16" class="text-stone-300 rotate-45" />
          </template>
        </div>
        <div class="ka-kisi mt-4">
          <KartuAngka label="Emisi produk terverifikasi" :nilai="props.emisiTahunan" :desimal="0" satuan="t CO₂e/thn" ikon="cloud" warna="toska" catatan="GWP per FU × volume tahunan" />
          <KartuAngka label="Eksposur pajak karbon" :nilai="props.emisiTahunan * harga.idr_per_t" satuan="Rp" ikon="coins" warna="amber" :catatan="`@ ${rp(harga.idr_per_t)}/t CO₂e`" />
          <KartuAngka label="Eksposur EU CBAM" :nilai="props.emisiTahunan * harga.eur_per_t" :desimal="0" satuan="€" ikon="globe" warna="biru" :catatan="`@ €${harga.eur_per_t}/t — bila diekspor ke UE`" />
        </div>
      </Kartu>
      <Kartu judul="Skenario dekarbonisasi terbaik" :ket="sk ? sk.nama : 'Belum ada skenario tersimpan'" ikon="sliders-horizontal" warna="hijau">
        <div v-if="sk" class="space-y-3">
          <div class="flex items-end gap-3"><p class="text-[38px] font-black num ld-hijau leading-none">−{{ n(sk.reduksi_pct, 1) }}%</p>
            <p class="text-[12px] text-stone-500 pb-1">{{ n(sk.baseline, 2) }} → {{ n(sk.proyeksi, 2) }} kg CO₂e per FU</p></div>
          <div class="ka-kisi">
            <KartuAngka label="CO₂e dihindari" :nilai="sk.hemat_t_tahun" :desimal="0" satuan="t/thn" ikon="leaf" warna="hijau" />
            <KartuAngka label="Nilai karbon" :nilai="sk.hemat_idr" satuan="Rp" ikon="banknote" warna="amber" catatan="per tahun" />
          </div>
          <Link v-if="u" :href="`/lca/studi/${u.id}#skenario`" class="eq-btn-lain inline-flex">Atur tuas skenario</Link>
        </div>
        <p v-else class="text-[12.5px] text-stone-500">Buka studi, geser tuas PLTS, efisiensi BBM, logistik, daur ulang, dan limbah, lalu simpan sebagai skenario.</p>
      </Kartu>
    </section>
  </div>
</template>

<style scoped>
.ld-langkah { padding: 10px 14px; border-radius: 14px; background: #F0FDF4; border: 1px solid #BBF7D0; min-width: 96px; }
.ld-hijau { color: #15803D; }
:root[data-tema="gelap"] .ld-langkah { background: #143A2C; border-color: #1F5A43; }
:root[data-tema="gelap"] .ld-hijau { color: #8FE3BE; }
</style>

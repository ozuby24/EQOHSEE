<script setup lang="ts">
/*
  Dasbor Carbon Management.

  Urutan bacanya: berapa emisinya dan ke mana arahnya (panggung),
  dari scope mana, kapan, dari sumber apa, lalu jalur turunnya —
  dekarbonisasi, biaya bila karbon dikenakan harga, aksi mitigasi, dan
  kewajiban pelaporannya. Semua angka berasal dari mesin yang sama
  dengan laporan GRK resmi.
*/
import { computed, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Panggung from '../../Components/Energi/Panggung.vue';
import Kartu from '../../Components/Energi/Kartu.vue';
import KartuAngka from '../../Components/Energi/KartuAngka.vue';
import BilahScope from '../../Components/Energi/BilahScope.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import PilihPerusahaan from '../../Components/Energi/PilihPerusahaan.vue';
import KartuGrafik from '../../Grafik/KartuGrafik.vue';
import Garis from '../../Grafik/Garis.vue';
import Donat from '../../Grafik/Donat.vue';
import { KATEGORI } from '../../Grafik/warna';
import { n, rp, statusKelas, tgl } from '../../energi';

const props = propHalaman();
const r = computed(() => props.r ?? {});
const lalu = computed(() => props.lalu ?? {});

const rentang = reactive({ dari: props.dari, sampai: props.sampai });
const terapkan = () => router.get('/karbon', { ...rentang, perusahaan: props.perusahaan ?? undefined }, { preserveState: true });

/** Perubahan terhadap periode yang sama tahun lalu; emisi turun = baik. */
function ubah(kini: unknown, dulu: unknown) {
  const a = Number(kini), b = Number(dulu);
  if (!isFinite(a) || !isFinite(b) || b <= 0) return null;
  const p = (a - b) / b * 100;
  return { persen: p, baik: p <= 0, teks: `${p >= 0 ? '+' : '−'}${n(Math.abs(p), 1)}% thn lalu` };
}

const total = computed(() => Number(r.value.total ?? 0));
const kgPerTon = (v: unknown) => (v === null || v === undefined ? null : Number(v) * 1000);
const hargaDasar = computed(() => (props.eksposur ?? []).find((e: any) => e.skenario === 'Dasar') ?? (props.eksposur ?? [])[0]);

const stat = computed(() => [
  { label: 'Emisi Scope 1 + 2', nilai: n(total.value, 0), satuan: 'tCO₂e', ikon: 'cloud', arah: ubah(total.value, lalu.value.total) },
  { label: 'Intensitas emisi', nilai: n(kgPerTon(props.intensitas), 1), satuan: 'kgCO₂e/ton', ikon: 'gauge', arah: ubah(props.intensitas, props.intensitasLalu) },
  { label: 'Scope 3 (rantai nilai)', nilai: n(r.value.s3, 0), satuan: 'tCO₂e', ikon: 'ship',
    catatan: r.value.s3_terpisah ? `+${n(r.value.s3_terpisah / 1e6, 2)} jt tCO₂e kategori 11, terpisah` : null },
  { label: 'Biaya karbon (harga dasar)', nilai: rp(hargaDasar.value?.rupiah), ikon: 'coins',
    catatan: hargaDasar.value ? `Rp ${n(hargaDasar.value.harga, 0)}/tCO₂e` : null },
]);

const lencana = computed(() => [
  `GWP ${props.gwp ?? 'AR5'}`,
  props.batasDisetujui ? 'Batas organisasi disetujui' : 'Batas organisasi belum disetujui',
  props.dasar ? `Tahun dasar ${props.dasar.tahun}` : 'Tahun dasar belum ditetapkan',
]);

const vsDasar = computed(() => props.dasar && props.dasar.total > 0 ? (total.value - props.dasar.total) / props.dasar.total * 100 : null);

/* ── grafik ── */
const labelBulan = computed(() => (props.bulanan ?? []).map((b: any) => b.label));
/* Scope 3 dicatat per tahun, bukan per bulan — menggambarnya di sini
   menumpuk setahun emisi rantai nilai pada satu titik. */
const deretBulan = computed(() => [
  { nama: 'Scope 1', nilai: (props.bulanan ?? []).map((b: any) => b.s1), warna: KATEGORI[1] },
  { nama: 'Scope 2', nilai: (props.bulanan ?? []).map((b: any) => b.s2), warna: KATEGORI[0] },
]);
const totalBulanan = computed(() => (props.bulanan ?? []).reduce((a: number, b: any) => a + b.s1 + b.s2, 0));
const skenario = computed(() => [...(props.eksposur ?? [])].sort((a: any, b: any) => a.harga - b.harga));
const bagianIpcc = computed(() => (props.perKategori ?? []).map((k: any, i: number) => ({ label: k.kategori, nilai: Math.round(k.co2e_t * 10) / 10, warna: KATEGORI[Number(i) % 8] })).filter((b: any) => b.nilai > 0));

const labelJalur = computed(() => {
  const t = new Set<number>([...(props.jalur?.tahun ?? []).map((x: any) => x.tahun), ...(props.jalur?.lintasan ?? []).map((x: any) => x.tahun)]);
  return [...t].sort();
});
const deretJalur = computed(() => {
  const akt = Object.fromEntries((props.jalur?.tahun ?? []).map((x: any) => [x.tahun, x.tco2e]));
  const lin = Object.fromEntries((props.jalur?.lintasan ?? []).map((x: any) => [x.tahun, x.tco2e]));
  const out: any[] = [{ nama: 'Aktual', nilai: labelJalur.value.map(t => akt[t] ?? null), warna: KATEGORI[2] }];
  if (props.jalur?.lintasan?.length) out.push({ nama: 'Lintasan target', nilai: labelJalur.value.map(t => lin[t] ?? null), warna: KATEGORI[6] });
  return out;
});

/* ── sumber terbesar ── */
const sumberDaftar = computed(() => (props.sumber ?? []).map((s: any, i: number) => ({ ...s, urut: i + 1 })));
const maksSumber = computed(() => Math.max(1, ...sumberDaftar.value.map((s: any) => s.co2e_t)));
const warnaScope = (s: number) => (s === 2 ? KATEGORI[0] : s === 3 ? KATEGORI[6] : KATEGORI[1]);

/* ── gas ── */
const gas = computed(() => {
  const g = (props.perGas ?? []).filter((x: any) => (x.co2e_t ?? 0) > 0);
  const jml = g.reduce((a: number, x: any) => a + (x.co2e_t ?? 0), 0);
  return g.map((x: any) => ({ ...x, persen: jml > 0 ? x.co2e_t / jml * 100 : 0 }));
});

/* ── biaya karbon ── */
const NADA_SKENARIO: Record<string, string> = { Rendah: 'sk-hijau', Dasar: 'sk-amber', Tinggi: 'sk-merah', Kustom: 'sk-ungu' };

/* ── mitigasi ── */
const maksMitigasi = computed(() => Math.max(1, ...(props.mitigasi ?? []).map((m: any) => m.target)));
const totalTarget = computed(() => (props.mitigasi ?? []).reduce((a: number, m: any) => a + m.target, 0));
const totalCapaian = computed(() => (props.mitigasi ?? []).reduce((a: number, m: any) => a + (m.capaian ?? 0), 0));
</script>

<template>
  <Head title="Carbon Management" />
  <div class="space-y-4">
    <section class="flex flex-wrap items-end justify-between gap-3">
      <form class="flex flex-wrap items-end gap-2" @submit.prevent="terapkan">
        <label class="text-[11px] font-semibold text-stone-500">Dari<input v-model="rentang.dari" type="date" class="block mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Sampai<input v-model="rentang.sampai" type="date" class="block mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <button class="eq-btn-lain" style="padding:9px 14px">Terapkan</button>
      </form>
      <PilihPerusahaan :daftar="props.daftarPerusahaan" :terpilih="props.perusahaan" />
    </section>

    <Panggung tema="karbon" judul="Jejak karbon operasi"
              :sub="`${tgl(props.dari)} – ${tgl(props.sampai)} · ${n(r.persen_terverifikasi, 0)}% data terverifikasi`"
              :stat="stat" :lencana="lencana" />

    <p v-if="props.faktorCocok?.length" class="kb-catatan">
      <Ikon nama="triangle-alert" :ukuran="16" />
      <span>{{ props.faktorCocok.length }} faktor emisi bawaan belum dicocokkan dengan dokumen sumbernya. <Link href="/karbon/faktor" class="font-semibold underline">Periksa registri</Link></span>
    </p>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.25fr)_minmax(0,1fr)]">
      <Kartu judul="Komposisi emisi" ikon="layers" warna="toska">
        <BilahScope :s1="r.s1" :s2="r.s2_lb" :s3="r.s3" :s3-terpisah="r.s3_terpisah" />
      </Kartu>
      <div class="ka-kisi content-start">
        <KartuAngka label="CO₂ biogenik (FAME)" :nilai="r.co2_bio" satuan="tCO₂" ikon="leaf" warna="hijau" catatan="Di luar Scope 1" />
        <KartuAngka label="Dibanding tahun dasar" :nilai="vsDasar" satuan="%" ikon="target" warna="biru"
                    :catatan="props.dasar ? `Tahun dasar ${props.dasar.tahun}: ${n(props.dasar.total, 0)} t` : 'Belum ditetapkan'" />
        <KartuAngka label="Data terverifikasi" :nilai="r.persen_terverifikasi" satuan="%" ikon="shield-check" warna="hijau" :catatan="`${n(r.persen_estimasi, 1)}% estimasi`" />
        <KartuAngka label="Ketidakpastian gabungan" :nilai="r.ketidakpastian_persen" satuan="%" ikon="scale" warna="langit" catatan="IPCC pendekatan 1" />
      </div>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
      <KartuGrafik judul="Emisi bulanan Scope 1 & 2" :catatan="`Dua belas bulan terakhir, bulan berjalan sampai ${tgl(props.sampai)}; Scope 3 dicatat tahunan`" :angka="`${n(totalBulanan, 0)} tCO₂e`">
        <Garis :label="labelBulan" :deret="deretBulan" satuan="tCO₂e" :tinggi="210" />
        <template #tabel>
          <table><thead><tr><th>Bulan</th><th>Scope 1</th><th>Scope 2</th></tr></thead>
            <tbody><tr v-for="b in props.bulanan" :key="b.bulan"><td>{{ b.label }}</td><td class="num">{{ n(b.s1, 1) }}</td><td class="num">{{ n(b.s2, 1) }}</td></tr></tbody></table>
        </template>
      </KartuGrafik>
      <KartuGrafik judul="Scope 1 per kategori IPCC" :angka="`${n(r.s1, 0)} tCO₂e`" :tinggi="170">
        <Donat :bagian="bagianIpcc" :tengah="n(r.s1, 0)" tengah-label="tCO₂e" />
        <template #tabel>
          <table><thead><tr><th>Kategori</th><th>tCO₂e</th></tr></thead><tbody><tr v-for="k in props.perKategori" :key="k.kategori"><td>{{ k.kategori }}</td><td class="num">{{ n(k.co2e_t, 2) }}</td></tr></tbody></table>
        </template>
      </KartuGrafik>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
      <Kartu judul="Sumber emisi terbesar" ket="Scope 1 dan 2 pada periode ini" ikon="factory" warna="oranye">
        <ol class="kb-sumber">
          <li v-for="s in sumberDaftar" :key="s.urut">
            <span class="kb-urut num">{{ s.urut }}</span>
            <div class="min-w-0 flex-1">
              <div class="flex items-baseline justify-between gap-3">
                <p class="kb-nama">{{ s.label }}<span v-if="s.kelompok" class="kb-kelompok"> · {{ s.kelompok }}</span></p>
                <p class="kb-angka num">{{ n(s.co2e_t, 1) }} <small>t</small></p>
              </div>
              <div class="kb-jalur"><span :style="{ width: (s.co2e_t / maksSumber * 100) + '%', background: warnaScope(s.scope) }" /></div>
            </div>
          </li>
        </ol>
        <p v-if="!props.sumber?.length" class="eq-kosong eq-kosong-kecil">Belum ada data aktivitas.</p>
      </Kartu>

      <Kartu judul="Emisi per gas" ket="Setara CO₂ dengan GWP perusahaan" ikon="wind" warna="langit">
        <div class="kb-gas">
          <div v-for="(g, i) in gas" :key="g.gas" class="kb-gas-ubin" :style="{ '--g': KATEGORI[Number(i) % 8] }">
            <p class="kb-gas-nama">{{ g.gas }}</p>
            <p class="kb-gas-nilai num">{{ n(g.co2e_t, 1) }}<small>tCO₂e</small></p>
            <div class="kb-jalur"><span :style="{ width: g.persen + '%', background: KATEGORI[Number(i) % 8] }" /></div>
            <p class="kb-gas-persen num">{{ n(g.persen, 1) }}%</p>
          </div>
        </div>
      </Kartu>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
      <KartuGrafik judul="Jalur dekarbonisasi"
                   :catatan="props.jalur?.target?.tahun ? `Target −${n(props.jalur.target.persen, 0)}% pada ${props.jalur.target.tahun} dari tahun dasar ${props.jalur.target.dasar}; tahun berjalan diproyeksikan setahun penuh` : 'Target belum diatur'"
                   angka="tCO₂e/tahun">
        <Garis :label="labelJalur.map(String)" :deret="deretJalur" satuan="tCO₂e" :tinggi="200" />
        <template #tabel>
          <table><thead><tr><th>Tahun</th><th>tCO₂e</th><th>Keterangan</th></tr></thead>
            <tbody><tr v-for="t in props.jalur?.tahun" :key="t.tahun"><td>{{ t.tahun }}</td><td class="num">{{ n(t.tco2e, 0) }}</td><td>{{ t.proyeksi ? `proyeksi; berjalan ${n(t.ytd, 0)}` : 'aktual' }}</td></tr></tbody></table>
        </template>
      </KartuGrafik>

      <Kartu judul="Biaya karbon bila dikenakan" :ket="`${n(total, 0)} tCO₂e Scope 1 + 2 × harga karbon`" ikon="coins" warna="ungu">
        <div class="kb-skenario">
          <div v-for="e in skenario" :key="e.skenario" class="kb-sk" :class="NADA_SKENARIO[e.skenario] ?? 'sk-ungu'">
            <p class="kb-sk-nama">{{ e.skenario }}</p>
            <p class="kb-sk-nilai num">{{ rp(e.rupiah) }}</p>
            <p class="kb-sk-harga num">Rp {{ n(e.harga, 0) }}/tCO₂e</p>
          </div>
        </div>
        <p class="text-[11px] text-stone-500 mt-3">Harga dasar mengikuti tarif minimum UU HPP; skenario rendah dan tinggi dapat diatur di registri faktor.</p>
      </Kartu>
    </section>

    <section class="grid gap-4 xl:grid-cols-2">
      <Kartu judul="Aksi mitigasi" :ket="`Target ${n(totalTarget, 0)} tCO₂e/tahun · tercapai ${n(totalCapaian, 0)} tCO₂e tahun ini`" ikon="sprout" warna="hijau">
        <template #aksi><Link href="/karbon/mitigasi" class="eq-tautan">Kelola</Link></template>
        <ul class="kb-mitigasi">
          <li v-for="m in props.mitigasi" :key="m.id">
            <div class="flex items-baseline justify-between gap-3">
              <p class="kb-nama">{{ m.judul }}</p>
              <p class="kb-angka num">{{ n(m.capaian, 0) }} <small>/ {{ n(m.target, 0) }} t</small></p>
            </div>
            <div class="kb-jalur kb-jalur-tebal">
              <span class="kb-target" :style="{ width: (m.target / maksMitigasi * 100) + '%' }" />
              <span class="kb-capai" :style="{ width: (Math.min(m.capaian ?? 0, m.target) / maksMitigasi * 100) + '%' }" />
            </div>
            <p class="kb-kelompok">{{ m.jenis }}<template v-if="m.status_srn"> · SRN {{ m.status_srn }}</template></p>
          </li>
        </ul>
        <p v-if="!props.mitigasi?.length" class="eq-kosong eq-kosong-kecil">Belum ada aksi mitigasi.</p>
      </Kartu>

      <Kartu judul="Kewajiban pelaporan emisi" ikon="calendar-clock" warna="biru">
        <template #aksi><Link href="/energi/pelaporan" class="eq-tautan">Pusat Pelaporan</Link></template>
        <ul class="divide-y divide-stone-100">
          <li v-for="k in props.kewajiban" :key="k.kode" class="py-2.5 flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="text-[12.5px] font-semibold text-cam-ink">{{ k.judul }}</p>
              <p class="text-[11px] text-stone-500">{{ k.dasar }}<template v-if="k.tenggat"> · tenggat {{ tgl(k.tenggat) }}</template></p>
            </div>
            <span class="shrink-0 rounded-md px-1.5 py-0.5 text-[10.5px] font-semibold" :class="statusKelas(k.status)">{{ k.status_label }}</span>
          </li>
        </ul>
      </Kartu>
    </section>
  </div>
</template>

<style scoped>
.kb-catatan { display: flex; gap: 8px; align-items: flex-start; padding: 11px 14px; border-radius: 14px; font-size: 12px;
  background: #FFFBEB; color: #92400E; border: 1px solid #FDE68A; }
.kb-sumber { display: grid; gap: 12px; }
.kb-sumber li { display: flex; gap: 12px; align-items: center; }
.kb-urut { width: 26px; height: 26px; flex: none; border-radius: 8px; display: grid; place-items: center; font-size: 11.5px; font-weight: 800;
  background: #F5F5F4; color: #57534E; }
.kb-nama { font-size: 12.5px; font-weight: 600; color: #1C1917; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.kb-kelompok { font-size: 11px; font-weight: 500; color: #A8A29E; }
.kb-angka { font-size: 13px; font-weight: 800; color: #1C1917; white-space: nowrap; }
.kb-angka small { font-size: 10.5px; font-weight: 600; color: #A8A29E; }
.kb-jalur { position: relative; height: 7px; border-radius: 999px; background: #F0EFED; margin-top: 6px; overflow: hidden; }
.kb-jalur > span { position: absolute; left: 0; top: 0; bottom: 0; border-radius: 999px; }
.kb-jalur-tebal { height: 10px; }
.kb-target { background: #D1FAE5; }
.kb-capai { background: linear-gradient(90deg, #15803D, #4ADE80); }

.kb-gas { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; }
.kb-gas-ubin { padding: 12px; border-radius: 14px; background: #FAFAF9; border: 1px solid #F0EFED; border-top: 3px solid var(--g); }
.kb-gas-nama { font-size: 13px; font-weight: 800; color: #1C1917; }
.kb-gas-nilai { font-size: 17px; font-weight: 800; color: #1C1917; margin-top: 4px; }
.kb-gas-nilai small { font-size: 10.5px; color: #A8A29E; font-weight: 700; margin-left: 3px; }
.kb-gas-persen { font-size: 10.5px; color: #78716C; margin-top: 4px; font-weight: 700; }

.kb-skenario { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; }
.kb-sk { padding: 14px; border-radius: 16px; color: #fff; background: linear-gradient(135deg, var(--s1), var(--s2)); }
.sk-hijau { --s1: #15803D; --s2: #34D399; }
.sk-amber { --s1: #C2410C; --s2: #F59E0B; }
.sk-merah { --s1: #9F1239; --s2: #F43F5E; }
.sk-ungu  { --s1: #5B21B6; --s2: #A78BFA; }
.kb-sk-nama { font-size: 11px; font-weight: 700; opacity: .9; text-transform: uppercase; letter-spacing: .06em; }
.kb-sk-nilai { font-size: 18px; font-weight: 800; margin-top: 4px; white-space: nowrap; }
.kb-sk-harga { font-size: 11px; opacity: .88; margin-top: 2px; }

.kb-mitigasi { display: grid; gap: 14px; }

:root[data-tema="gelap"] .kb-catatan { background: #2A2210; color: #F6D488; border-color: #4A3810; }
:root[data-tema="gelap"] .kb-urut { background: #232F35; color: #C7D0D5; }
:root[data-tema="gelap"] .kb-nama, :root[data-tema="gelap"] .kb-angka, :root[data-tema="gelap"] .kb-gas-nama, :root[data-tema="gelap"] .kb-gas-nilai { color: #E8EDEF; }
:root[data-tema="gelap"] .kb-jalur { background: #26343A; }
:root[data-tema="gelap"] .kb-target { background: #143A2C; }
:root[data-tema="gelap"] .kb-gas-ubin { background: #182125; border-color: #222E33; }
</style>

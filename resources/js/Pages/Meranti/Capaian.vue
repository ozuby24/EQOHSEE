<script setup lang="ts">
/*
  Aktual Pencapaian — persen capaian P0, P1, P2 per blok.

  Σ luas realisasi Progres Mingguan dibagi Σ luas Target Blok, per blok
  dan per pekerjaan, dengan garis target 100% — sama dengan MERANTI.
  Kolom di atas 150% dipotong pada grafik saja; tabel rinciannya tetap
  menyebut angka sebenarnya.
*/
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import PilihPerusahaan from '../../Components/Energi/PilihPerusahaan.vue';
import { n } from '../../energi';
import { WARNA_PEKERJAAN, warnaCapaian } from '../../meranti';

const props = propHalaman();
const c = computed(() => props.capaian ?? { label: [], deret: {}, rinci: [], perPekerjaan: {}, total: {}, tahunList: [], tahun: 'Semua' });
const P = ['P0', 'P1', 'P2'];

function pilihTahun(t: string) {
  router.get('/meranti/aktual-pencapaian', { ...(props.perusahaan ? { perusahaan: props.perusahaan } : {}), tahun: t }, { preserveScroll: true });
}

/* Grafik kolom berkelompok: tiga kolom per blok, garis 100%. */
const T = 260, KIRI = 40, BAWAH = 34, ATAS = 14;
const lebarBlok = computed(() => Math.max(78, Math.min(150, 900 / Math.max(1, c.value.label.length))));
const W = computed(() => KIRI + 12 + c.value.label.length * lebarBlok.value);
const y = (p: number) => ATAS + (T - ATAS - BAWAH) * (1 - Math.min(150, Math.max(0, p)) / 150);
const kolom = computed(() => c.value.label.map((b: string, i: number) => ({
  b, x0: KIRI + 12 + i * lebarBlok.value,
  batang: P.map((p, j) => {
    const v = c.value.deret[p]?.[i];
    const lebar = (lebarBlok.value - 24) / 3;
    return { p, v, x: KIRI + 12 + i * lebarBlok.value + 8 + j * (lebar + 4), lebar, y: v === null || v === undefined ? y(0) : y(v) };
  }),
})));
const garisBantu = [0, 50, 100, 150];
</script>

<template>
  <Head title="Aktual Pencapaian" />
  <div class="space-y-4">
    <section class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Pilih tahun">
        <button type="button" class="ac-chip" :class="{ aktif: c.tahun === 'Semua' }" @click="pilihTahun('Semua')">Semua tahun</button>
        <button v-for="t in c.tahunList" :key="t" type="button" class="ac-chip" :class="{ aktif: String(c.tahun) === String(t) }" @click="pilihTahun(String(t))">{{ t }}</button>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <Link href="/meranti/progres-mingguan" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="sprout" :ukuran="15" />Progres Mingguan</Link>
        <Link href="/meranti/target-blok" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="target" :ukuran="15" />Target Blok</Link>
        <PilihPerusahaan :daftar="props.daftarPerusahaan" :terpilih="props.perusahaan" />
      </div>
    </section>

    <section class="grid gap-3 grid-cols-2 lg:grid-cols-4">
      <div class="ac-angka">
        <p class="ac-label">Capaian keseluruhan</p>
        <p class="ac-nilai num" :style="{ color: warnaCapaian(c.total.target ? c.total.pct : null) }">{{ c.total.target ? n(c.total.pct, 1) + '%' : '—' }}</p>
        <p class="ac-ket num">{{ n(c.total.real, 2) }} dari {{ n(c.total.target, 2) }} ha · {{ c.total.blok }} blok</p>
      </div>
      <div v-for="p in P" :key="p" class="ac-angka" :style="{ borderTopColor: WARNA_PEKERJAAN[p] }">
        <p class="ac-label">{{ p }} · {{ props.pekerjaan?.[p] }}</p>
        <p class="ac-nilai num" :style="{ color: warnaCapaian(c.perPekerjaan[p]?.pct) }">{{ c.perPekerjaan[p]?.pct === null ? '—' : n(c.perPekerjaan[p]?.pct, 1) + '%' }}</p>
        <p class="ac-ket num">{{ n(c.perPekerjaan[p]?.real, 2) }} / {{ n(c.perPekerjaan[p]?.target, 2) }} ha</p>
      </div>
    </section>

    <Kartu judul="Capaian per blok" :ket="`Tahun ${c.tahun} · garis putus-putus = target 100%`" ikon="chart-column" warna="hijau">
      <div v-if="c.label.length" class="overflow-x-auto">
        <svg :viewBox="`0 0 ${W} ${T}`" :width="Math.max(W, 320)" :height="T" class="ac-svg" role="img" :aria-label="`Capaian P0, P1, P2 untuk ${c.label.length} blok`">
          <g v-for="g in garisBantu" :key="g">
            <line :x1="KIRI" :x2="W" :y1="y(g)" :y2="y(g)" :class="g === 100 ? 'ac-target' : 'ac-kisi'" />
            <text :x="KIRI - 6" :y="y(g) + 4" text-anchor="end" class="ac-sumbu">{{ g }}%</text>
          </g>
          <g v-for="kol in kolom" :key="kol.b">
            <template v-for="bt in kol.batang" :key="bt.p">
              <rect v-if="bt.v !== null && bt.v !== undefined" :x="bt.x" :y="bt.y" :width="bt.lebar" :height="Math.max(1, y(0) - bt.y)" :fill="WARNA_PEKERJAAN[bt.p]" rx="3">
                <title>{{ kol.b }} {{ bt.p }}: {{ n(bt.v, 1) }}%</title>
              </rect>
              <text v-if="bt.v !== null && bt.v !== undefined" :x="bt.x + bt.lebar / 2" :y="bt.y - 4" text-anchor="middle" class="ac-teks">{{ n(bt.v, 0) }}</text>
              <text v-else :x="bt.x + bt.lebar / 2" :y="y(0) - 4" text-anchor="middle" class="ac-kosong">–</text>
            </template>
            <text :x="kol.x0 + lebarBlok / 2" :y="T - 12" text-anchor="middle" class="ac-blok">{{ kol.b }}</text>
          </g>
        </svg>
        <p class="flex flex-wrap gap-4 mt-2 text-[11.5px] font-bold text-stone-600">
          <span v-for="p in P" :key="p"><i class="ac-titik" :style="{ background: WARNA_PEKERJAAN[p] }" />{{ p }} {{ props.pekerjaan?.[p] }}</span>
          <span><i class="ac-titik ac-titik-garis" />Target 100%</span>
        </p>
      </div>
      <p v-else class="py-8 text-center text-[12.5px] text-stone-400">Belum ada Progres Mingguan atau Target Blok untuk tahun ini.</p>
    </Kartu>

    <Kartu judul="Rincian realisasi terhadap target" :ket="`${c.rinci.length} blok`" ikon="list-checks" rapat>
      <div class="overflow-x-auto">
        <table class="w-full text-[12.5px] ac-tabel">
          <thead>
            <tr><th>Blok</th><th v-for="p in P" :key="p" class="text-right">{{ p }} · {{ props.pekerjaan?.[p] }}</th><th class="text-right">Total blok</th></tr>
          </thead>
          <tbody class="divide-y divide-stone-100">
            <tr v-for="r in c.rinci" :key="r.blok">
              <td class="font-bold text-cam-ink">{{ r.blok }}</td>
              <td v-for="p in P" :key="p" class="text-right num">
                <template v-if="r[p].target > 0 || r[p].real > 0">
                  <b :style="{ color: warnaCapaian(r[p].pct) }">{{ r[p].pct === null ? 'tanpa target' : n(r[p].pct, 1) + '%' }}</b>
                  <span class="block text-[11px] text-stone-500">{{ n(r[p].real, 2) }} / {{ n(r[p].target, 2) }} ha</span>
                </template>
                <span v-else class="text-stone-300">—</span>
              </td>
              <td class="text-right num">
                <b>{{ n(P.reduce((a, p) => a + r[p].real, 0), 2) }}</b>
                <span class="block text-[11px] text-stone-500">dari {{ n(P.reduce((a, p) => a + r[p].target, 0), 2) }} ha</span>
              </td>
            </tr>
            <tr v-if="!c.rinci.length"><td colspan="5" class="px-5 py-6 text-center text-stone-400">Belum ada data.</td></tr>
          </tbody>
        </table>
      </div>
    </Kartu>
  </div>
</template>

<style scoped>
.ac-chip { padding: 6px 13px; border-radius: 999px; border: 1px solid #E7E5E4; background: #fff; font-size: 12px; font-weight: 700; color: #44403C; }
.ac-chip.aktif { border-color: #2E7D55; background: #ECF7F0; color: #0A3A2A; }
.ac-angka { border-radius: 16px; background: #fff; border: 1px solid #E7E5E4; border-top: 4px solid #0A3A2A; padding: 12px 14px; }
.ac-label { font-size: 11px; font-weight: 700; color: #78716C; }
.ac-nilai { font-size: 26px; font-weight: 900; line-height: 1.15; }
.ac-ket { font-size: 11px; color: #78716C; }
.ac-svg { display: block; }
.ac-kisi { stroke: #E7E5E4; stroke-width: 1; }
.ac-target { stroke: #0A3A2A; stroke-width: 1.4; stroke-dasharray: 5 4; }
.ac-sumbu { font-size: 10px; fill: #78716C; font-weight: 700; }
.ac-teks { font-size: 10px; fill: #1C1917; font-weight: 800; }
.ac-kosong { font-size: 11px; fill: #A8A29E; }
.ac-blok { font-size: 11px; fill: #44403C; font-weight: 800; }
.ac-titik { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 6px; vertical-align: -1px; }
.ac-titik-garis { background: transparent; border-top: 2px dashed #0A3A2A; height: 0; border-radius: 0; width: 16px; vertical-align: 3px; }
.ac-tabel thead th { text-align: left; font-size: 11px; font-weight: 700; color: #78716C; padding: 8px 12px; background: #FAFAF9; white-space: nowrap; }
.ac-tabel thead th.text-right { text-align: right; }
.ac-tabel td { padding: 9px 12px; vertical-align: top; }
.ac-tabel th:first-child, .ac-tabel td:first-child { padding-left: 20px; }
:root[data-tema="gelap"] .ac-chip { background: #20272B; border-color: #2E373C; color: #D5DDE1; }
:root[data-tema="gelap"] .ac-chip.aktif { background: #12271D; border-color: #22C55E; color: #8FE3BE; }
:root[data-tema="gelap"] .ac-angka { background: #1B2124; border-color: #2E373C; }
:root[data-tema="gelap"] .ac-kisi { stroke: #2E373C; }
:root[data-tema="gelap"] .ac-target { stroke: #8FE3BE; }
:root[data-tema="gelap"] .ac-teks, :root[data-tema="gelap"] .ac-blok { fill: #E8EDEF; }
:root[data-tema="gelap"] .ac-tabel thead th { background: #20272B; color: #93A1A8; }
</style>

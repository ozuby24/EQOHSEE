<script setup lang="ts">
/* Daftar laporan riksa uji PAA & alat bantu angkat dengan tren 12 bulan. */
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import PilihPerusahaan from '../../Components/Energi/PilihPerusahaan.vue';
import KartuGrafik from '../../Grafik/KartuGrafik.vue';
import Kolom from '../../Grafik/Kolom.vue';
import BatangTumpuk from '../../Grafik/BatangTumpuk.vue';
import { n } from '../../energi';
import { riksa, tanggal, WARNA_KODE } from '../../angkat';

const props = propHalaman();
const bulan = computed(() => props.bulan ?? []);
const daftar = computed(() => props.riksa ?? []);
const total = computed(() => bulan.value.reduce((a: number, b: any) => a + b.layak + b.tidak, 0));
const tolak = computed(() => bulan.value.reduce((a: number, b: any) => a + b.tidak, 0));
const barisKat = computed(() => (props.perKategori ?? []).filter((k: any) => k.layak + k.tidak).map((k: any) => ({
  label: k.label, sub: `${k.layak + k.tidak} riksa`,
  bagian: [{ nama: 'Layak', nilai: k.layak, warna: '#15803D' }, { nama: 'Tidak layak', nilai: k.tidak, warna: '#DC2626' }],
})));
function saring(p: string) { router.get('/pengangkatan/riksa-uji', p ? { putusan: p } : {}, { preserveScroll: true }); }
</script>

<template>
  <Head title="Riksa uji PAA" />
  <div class="space-y-4">
    <section class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex flex-wrap gap-2">
        <Link href="/pengangkatan/riksa-uji/baru" class="eq-btn-utama inline-flex items-center gap-2"><Ikon nama="scan-eye" :ukuran="16" />Catat riksa uji</Link>
        <button class="eq-btn-lain" :class="{ 'ring-2 ring-blue-500': !props.saring?.putusan }" @click="saring('')">Semua</button>
        <button class="eq-btn-lain" :class="{ 'ring-2 ring-blue-500': props.saring?.putusan === 'tidak_layak' }" @click="saring('tidak_layak')">Tidak layak saja</button>
      </div>
      <PilihPerusahaan :daftar="props.daftarPerusahaan" :terpilih="props.perusahaan" />
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
      <KartuGrafik judul="Riksa uji per bulan" :catatan="`12 bulan · ${tolak} tidak layak dari ${total} pemeriksaan`" :angka="`${total ? n(tolak / total * 100, 1) : '—'}% ditolak`">
        <Kolom :label="bulan.map((b: any) => b.label)" :deret="[{ nama: 'Layak digunakan', nilai: bulan.map((b: any) => b.layak), warna: '#15803D' }, { nama: 'Tidak layak digunakan', nilai: bulan.map((b: any) => b.tidak), warna: '#DC2626' }]" satuan="riksa" :tinggi="220" />
      </KartuGrafik>
      <Kartu judul="Menurut kategori uji" ket="Beban uji & masa berlaku berbeda per kategori" ikon="list-checks" warna="biru">
        <BatangTumpuk :baris="barisKat" satuan="riksa" />
      </Kartu>
    </section>

    <Kartu judul="Laporan riksa uji" :ket="`${daftar.length} laporan`" ikon="file-check-2" warna="hijau" rapat>
      <ul class="divide-y divide-stone-100">
        <li v-for="x in daftar" :key="x.id">
          <Link :href="`/pengangkatan/riksa-uji/${x.id}`" class="px-5 py-3 flex items-center gap-3 rk-baris">
            <span class="rk-cat" :style="{ background: WARNA_KODE[x.kode_warna] ?? '#E7E5E4' }" />
            <div class="min-w-0 flex-1">
              <p class="text-[12.5px] font-bold text-cam-ink truncate">{{ x.nomor }} · {{ x.target_kode }} {{ x.target_nama }}</p>
              <p class="text-[11.5px] text-stone-500 truncate">{{ x.jenis_label }} · {{ x.kategori_label }} · SWL {{ n(x.swl_t, 2) }} t<template v-if="x.beban_statis_t"> · uji {{ n(x.beban_statis_t, 2) }} t</template> · {{ x.pemeriksa }}</p>
              <p v-if="x.putusan === 'tidak_layak' && x.alasan" class="text-[11.5px] rk-merah truncate">{{ x.alasan }}</p>
            </div>
            <div class="text-right shrink-0">
              <span class="pr-pil" :class="riksa(x.putusan).kelas">{{ riksa(x.putusan).pendek }}</span>
              <p class="text-[11px] text-stone-500 mt-1">{{ tanggal(x.diperiksa_at) }}</p>
            </div>
          </Link>
        </li>
        <li v-if="!daftar.length" class="px-5 py-10 text-center text-[12.5px] text-stone-400">Belum ada laporan riksa uji.</li>
      </ul>
    </Kartu>
  </div>
</template>

<style scoped>
.rk-baris:hover { background: #F8FAFC; }
.rk-cat { width: 12px; align-self: stretch; border-radius: 6px; border: 1px solid rgba(0, 0, 0, .1); flex: none; }
.rk-merah { color: #B91C1C; }
:root[data-tema="gelap"] .rk-baris:hover { background: #20272B; }
:root[data-tema="gelap"] .rk-merah { color: #F5A9A9; }
</style>

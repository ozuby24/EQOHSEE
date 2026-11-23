<script setup lang="ts">
/*
  Laporan riksa uji — satu halaman yang bisa dicetak dan ditempel di
  map SUKET: identitas alat, beban uji, ukuran, NDT, butir pemeriksaan,
  putusan, dan riwayat uji alat yang sama.
*/
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import { n } from '../../energi';
import { riksa, kelayakan, tanggal, WARNA_KODE } from '../../angkat';

const props = propHalaman();
const x = computed(() => props.riksa ?? {});
const t = computed(() => props.target);
const ukur = computed(() => Object.entries(x.value.ukur ?? {}).map(([k, v]) => ({ k, v, l: props.ukurLabel?.[k] })));
function cetak() { window.print(); }
</script>

<template>
  <Head :title="`Laporan ${x.nomor}`" />
  <div class="space-y-4 max-w-5xl">
    <section class="lr-kepala" :class="x.putusan === 'layak' ? 'lr-hijau' : 'lr-merah'">
      <div class="min-w-0">
        <p class="text-[12px] font-bold opacity-85">{{ x.nomor }} · {{ x.jenis_label }}</p>
        <p class="text-[24px] font-black leading-tight">{{ riksa(x.putusan).label }}</p>
        <p class="text-[12.5px] opacity-90 mt-1">{{ x.target_kode }} · {{ x.target_nama }} — diperiksa {{ tanggal(x.diperiksa_at) }}<template v-if="x.berlaku_sampai">, berlaku s.d. {{ tanggal(x.berlaku_sampai) }}</template></p>
      </div>
      <div class="lr-label" :style="{ background: WARNA_KODE[x.kode_warna] ?? '#E7E5E4' }"><span>{{ x.kode_warna }}</span></div>
    </section>

    <div class="flex flex-wrap gap-2 lr-aksi">
      <button type="button" class="eq-btn-lain inline-flex items-center gap-2" @click="cetak"><Ikon nama="printer" :ukuran="15" />Cetak</button>
      <Link :href="`/pengangkatan/riksa-uji/baru?objek=${x.objek}&id=${x.target_id}`" class="eq-btn-lain">Riksa uji ulang</Link>
      <Link href="/pengangkatan/riksa-uji" class="eq-btn-lain">Daftar riksa uji</Link>
    </div>

    <section class="grid gap-4 md:grid-cols-2">
      <Kartu judul="Uji beban & NDT" ikon="weight" warna="biru">
        <dl class="grid grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)] gap-x-4 gap-y-1.5 text-[12.5px]">
          <dt class="text-stone-500">SWL / WLL</dt><dd class="font-bold num">{{ n(x.swl_t, 2) }} t</dd>
          <template v-if="x.uji_statis_pct"><dt class="text-stone-500">Uji statis</dt><dd class="font-bold num">{{ x.uji_statis_pct }}% · {{ n(x.beban_statis_t, 2) }} t · tahan {{ x.tahan_menit }} menit</dd></template>
          <template v-if="x.uji_dinamis_pct"><dt class="text-stone-500">Uji dinamis</dt><dd class="font-bold num">{{ x.uji_dinamis_pct }}% · {{ n(x.beban_dinamis_t, 2) }} t</dd></template>
          <dt class="text-stone-500">NDT</dt><dd class="font-bold">{{ x.ndt_metode }} · {{ x.ndt_hasil === 'ada_cacat' ? 'ditemukan retak/cacat' : 'tanpa indikasi' }}</dd>
          <template v-for="u in ukur" :key="u.k"><dt class="text-stone-500">{{ u.l?.label ?? u.k }}</dt><dd class="font-bold num">{{ n(u.v, 1) }} {{ u.l?.satuan }}</dd></template>
          <dt class="text-stone-500">Pemeriksa</dt><dd class="font-bold">{{ x.pemeriksa }} · {{ x.skp_nomor }}</dd>
          <dt class="text-stone-500">PJK3</dt><dd class="font-bold">{{ x.pjk3 || '—' }}</dd>
        </dl>
        <p v-if="x.alasan" class="mt-3 text-[12.5px]" :class="x.putusan === 'layak' ? 'text-stone-600' : 'lr-teks-merah'">{{ x.alasan }}</p>
      </Kartu>
      <Kartu judul="Butir pemeriksaan" ikon="list-checks" warna="amber" rapat>
        <ul class="divide-y divide-stone-100">
          <li v-for="b in x.butir" :key="b.kode" class="px-5 py-2 flex items-center gap-3 text-[12px]">
            <Ikon :nama="b.status === 'gagal' ? 'circle-x' : 'circle-check'" :ukuran="16" :class="b.status === 'gagal' ? 'lr-teks-merah' : b.status === 'na' ? 'text-stone-400' : 'lr-teks-hijau'" class="shrink-0" />
            <span class="flex-1">{{ b.label }}<b v-if="b.kritis" class="lr-teks-merah"> · kritis</b></span>
            <span class="font-bold text-stone-500">{{ b.status === 'na' ? 'N/A' : b.status === 'gagal' ? 'Gagal' : 'Lulus' }}</span>
          </li>
        </ul>
      </Kartu>
    </section>

    <Kartu v-if="t" :judul="`Riwayat ${t.kode}`" :ket="`Status sekarang: ${kelayakan(t.status).label}`" ikon="history" warna="ungu" rapat>
      <ul class="divide-y divide-stone-100">
        <li v-for="h in props.riwayat" :key="h.id" class="px-5 py-2.5 flex items-center gap-3 text-[12px]">
          <span class="lr-cat" :style="{ background: WARNA_KODE[h.kode_warna] ?? '#E7E5E4' }" />
          <Link :href="`/pengangkatan/riksa-uji/${h.id}`" class="flex-1 font-semibold text-cam-ink hover:underline">{{ h.nomor }} · {{ h.jenis_label }}</Link>
          <span class="text-stone-500">{{ tanggal(h.diperiksa_at) }}</span>
          <span class="pr-pil" :class="riksa(h.putusan).kelas">{{ riksa(h.putusan).pendek }}</span>
        </li>
      </ul>
    </Kartu>
  </div>
</template>

<style scoped>
@media (max-width: 640px) { .lr-label { width: 72px; height: 72px; } }
.lr-kepala { display: flex; justify-content: space-between; gap: 16px; align-items: center; padding: 20px 22px; border-radius: 20px; color: #fff; }
.lr-hijau { background: linear-gradient(120deg, #14532D, #15803D 60%, #22C55E); }
.lr-merah { background: linear-gradient(120deg, #7F1D1D, #B91C1C 60%, #EF4444); }
.lr-label { width: 96px; height: 96px; border-radius: 18px; display: grid; place-items: center; border: 3px solid rgba(255, 255, 255, .85); flex: none; }
.lr-label span { font-size: 12px; font-weight: 900; color: #1C1917; background: rgba(255, 255, 255, .85); padding: 3px 8px; border-radius: 8px; }
.lr-cat { width: 14px; height: 14px; border-radius: 4px; border: 1px solid rgba(0, 0, 0, .15); flex: none; }
.lr-teks-merah { color: #B91C1C; } .lr-teks-hijau { color: #15803D; }
@media print { .lr-aksi { display: none !important; } }
:root[data-tema="gelap"] .lr-teks-merah { color: #F5A9A9; }
:root[data-tema="gelap"] .lr-teks-hijau { color: #8FE3BE; }
</style>

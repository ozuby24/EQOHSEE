<script setup lang="ts">
/*
  Peta seluruh petak tanam dengan saringan fase dan PPKH, beserta daftar
  lokasi di sampingnya. Ekspor GeoJSON untuk QGIS/ArcGIS atau untuk
  dilampirkan pada permohonan penilaian.
*/
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import PilihPerusahaan from '../../Components/Energi/PilihPerusahaan.vue';
import PetaPoligon from '../../Components/Rdas/PetaPoligon.vue';
import { n } from '../../energi';
import { fase, warnaTumbuh, FASE } from '../../rdas';

const props = propHalaman();
const mode = ref<'tumbuh' | 'fase'>('tumbuh');
const saringFase = ref('');
const cari = ref('');
const fitur = computed(() => (props.fitur ?? []).filter((f: any) => (!saringFase.value || f.fase === saringFase.value)
  && (!cari.value || `${f.blok} ${f.nama} ${f.kode} ${f.das}`.toLowerCase().includes(cari.value.toLowerCase()))));
const blok = computed(() => (props.blok ?? []).filter((b: any) => (!saringFase.value || b.fase === saringFase.value)
  && (!cari.value || `${b.kode} ${b.nama} ${b.das}`.toLowerCase().includes(cari.value.toLowerCase()))));
const jumlahFase = computed(() => Object.keys(FASE).map(k => ({ k, n: (props.blok ?? []).filter((b: any) => b.fase === k).length })).filter(x => x.n));
</script>

<template>
  <Head title="Peta petak tanam" />
  <div class="space-y-4">
    <section class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex flex-wrap gap-2 items-center">
        <input v-model="cari" placeholder="Cari blok, petak, DAS" aria-label="Cari lokasi" class="pt-i">
        <button class="pt-chip" :class="{ aktif: !saringFase }" @click="saringFase = ''">Semua fase</button>
        <button v-for="f in jumlahFase" :key="f.k" class="pt-chip" :class="{ aktif: saringFase === f.k }" @click="saringFase = f.k">
          <i :style="{ background: fase(f.k).warna }" />{{ fase(f.k).pendek }} <b class="num">{{ f.n }}</b>
        </button>
      </div>
      <div class="flex flex-wrap gap-2 items-center">
        <a href="/rehab-das/ekspor/geojson" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="globe" :ukuran="15" />GeoJSON</a>
        <a href="/rehab-das/ekspor/csv" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="download" :ukuran="15" />CSV</a>
        <PilihPerusahaan :daftar="props.daftarPerusahaan" :terpilih="props.perusahaan" />
      </div>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)] items-start">
      <Kartu judul="Petak tanam" :ket="`${fitur.length} petak · ${n(fitur.reduce((a: number, f: any) => a + f.luas, 0), 1)} ha`" ikon="map" warna="hijau">
        <template #aksi>
          <div class="pt-tab">
            <button :class="{ aktif: mode === 'tumbuh' }" @click="mode = 'tumbuh'">Persen tumbuh</button>
            <button :class="{ aktif: mode === 'fase' }" @click="mode = 'fase'">Fase</button>
          </div>
        </template>
        <PetaPoligon :fitur="fitur" :mode="mode" :tinggi="560" @pilih="(id: number) => router.visit(`/rehab-das/blok/${id}`)" />
      </Kartu>
      <Kartu judul="Lokasi" :ket="`${blok.length} lokasi`" ikon="map-pin" warna="amber" rapat>
        <ul class="divide-y divide-stone-100 max-h-[640px] overflow-y-auto">
          <li v-for="b in blok" :key="b.id">
            <Link :href="`/rehab-das/blok/${b.id}`" class="px-5 py-3 flex items-center gap-3 pt-baris">
              <span class="pt-cincin" :style="{ borderColor: warnaTumbuh(b.persen_tumbuh) }">{{ b.persen_tumbuh === null ? '—' : n(b.persen_tumbuh, 0) }}</span>
              <div class="min-w-0 flex-1">
                <p class="text-[12.5px] font-bold text-cam-ink truncate">{{ b.kode }} · {{ b.nama }}</p>
                <p class="text-[11px] text-stone-500 truncate">{{ b.das }}{{ b.sub_das ? ' / ' + b.sub_das : '' }} · {{ b.fungsi_kawasan }} · {{ n(b.luas_ha, 1) }} ha · {{ b.petak }} petak</p>
              </div>
              <span class="pr-pil" :class="fase(b.fase).kelas">{{ fase(b.fase).pendek }}</span>
            </Link>
          </li>
        </ul>
      </Kartu>
    </section>
  </div>
</template>

<style scoped>
.pt-i { border-radius: 10px; border-color: #E7E5E4; font-size: 12.5px; min-width: 220px; }
.pt-chip { display: inline-flex; align-items: center; gap: 7px; padding: 6px 12px; border-radius: 999px; border: 1px solid #E7E5E4; background: #fff; font-size: 12px; font-weight: 700; color: #44403C; }
.pt-chip i { width: 8px; height: 8px; border-radius: 999px; }
.pt-chip.aktif { border-color: #15803D; background: #F0FDF4; color: #14532D; }
.pt-tab { display: inline-flex; gap: 2px; padding: 3px; border-radius: 10px; background: #F5F5F4; }
.pt-tab button { padding: 4px 10px; border-radius: 8px; font-size: 11.5px; font-weight: 700; color: #57534E; }
.pt-tab button.aktif { background: #fff; color: #166534; box-shadow: 0 1px 3px rgba(0, 0, 0, .08); }
.pt-baris:hover { background: #F7FBF4; }
.pt-cincin { width: 38px; height: 38px; border-radius: 999px; border: 4px solid; display: grid; place-items: center; font-size: 11px; font-weight: 900; color: #1C1917; flex: none; }
:root[data-tema="gelap"] .pt-chip { background: #20272B; border-color: #2E373C; color: #D5DDE1; }
:root[data-tema="gelap"] .pt-chip.aktif { background: #12271D; border-color: #22C55E; color: #8FE3BE; }
:root[data-tema="gelap"] .pt-tab { background: #20272B; }
:root[data-tema="gelap"] .pt-tab button { color: #B4BEC3; }
:root[data-tema="gelap"] .pt-tab button.aktif { background: #2E373C; color: #8FE3BE; }
:root[data-tema="gelap"] .pt-baris:hover { background: #20272B; }
:root[data-tema="gelap"] .pt-cincin { color: #E8EDEF; }
</style>

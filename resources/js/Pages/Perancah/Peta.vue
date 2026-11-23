<script setup lang="ts">
/*
  Daftar & peta perancah.

  Satu saringan menggerakkan dua tampilan sekaligus: peta dan tabel
  selalu memperlihatkan himpunan yang sama, sehingga "yang merah di Zona
  CHPP" yang terlihat di peta adalah persis baris yang ada di tabel.
  Saringannya bekerja di peramban — daftar satu situs jarang lebih dari
  beberapa ratus unit — dan ikut tertulis di alamat supaya dapat dibagi.
*/
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import PilihPerusahaan from '../../Components/Energi/PilihPerusahaan.vue';
import PetaTitik from '../../Components/Perancah/PetaTitik.vue';
import { n } from '../../energi';
import { tag, sisa, PUTUSAN } from '../../perancah';

const props = propHalaman();
const saring = reactive({ status: props.saring?.status ?? '', zona: props.saring?.zona ?? '', q: props.saring?.q ?? '' });
const terpilih = ref<number | null>(null);

const semua = computed(() => (props.unit ?? []) as any[]);
const STATUS = ['hijau', 'kuning', 'merah', 'kedaluwarsa', 'dibongkar'];
const hitungStatus = computed(() => STATUS.map(s => ({ s, n: semua.value.filter(u => u.status === s).length, w: tag(s) })));

const tampil = computed(() => {
  const q = saring.q.trim().toLowerCase();
  return semua.value.filter(u =>
    (!saring.status ? u.status !== 'dibongkar' : u.status === saring.status)
    && (!saring.zona || u.zona === saring.zona)
    && (!q || [u.kode, u.judul, u.peralatan, u.izin, u.kontraktor, u.kode_tag].some(x => String(x ?? '').toLowerCase().includes(q))));
});
const daftar = computed(() => tampil.value.map((u, urut) => ({ ...u, urut, w: tag(u.status) })));
const pilih = computed(() => semua.value.find(u => u.id === terpilih.value) ?? null);

watch(saring, () => {
  const p = new URLSearchParams();
  Object.entries(saring).forEach(([k, v]) => v && p.set(k, String(v)));
  history.replaceState(history.state, '', `${location.pathname}${p.toString() ? '?' + p : ''}`);
});
</script>

<template>
  <Head title="Daftar & Peta Perancah" />
  <div class="space-y-4">
    <section class="flex flex-wrap items-end justify-between gap-3">
      <div class="flex flex-wrap items-end gap-2">
        <label class="text-[11px] font-semibold text-stone-500">Cari
          <input v-model="saring.q" type="search" placeholder="Kode, pekerjaan, peralatan, izin…" class="block mt-1 w-64 rounded-lg border-stone-200 text-[12px]">
        </label>
        <label class="text-[11px] font-semibold text-stone-500">Zona
          <select v-model="saring.zona" class="block mt-1 rounded-lg border-stone-200 text-[12px]">
            <option value="">Semua zona</option>
            <option v-for="z in props.zona" :key="z" :value="z">{{ z }}</option>
          </select>
        </label>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <a href="/perancah/ekspor/csv" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="file-spreadsheet" :ukuran="16" />CSV</a>
        <a href="/perancah/ekspor/geojson" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="globe" :ukuran="16" />GeoJSON</a>
        <Link href="/perancah/baru" class="eq-btn-utama inline-flex items-center gap-2"><Ikon nama="calculator" :ukuran="16" />Daftarkan</Link>
        <PilihPerusahaan :daftar="props.daftarPerusahaan" :terpilih="props.perusahaan" />
      </div>
    </section>

    <div class="flex flex-wrap gap-2">
      <button type="button" class="pm-chip" :class="!saring.status && 'pm-aktif'" @click="saring.status = ''">Masih berdiri <b>{{ semua.filter(u => u.status !== 'dibongkar').length }}</b></button>
      <button v-for="h in hitungStatus" :key="h.s" type="button" class="pm-chip" :class="saring.status === h.s && 'pm-aktif'" @click="saring.status = saring.status === h.s ? '' : h.s">
        <i :style="{ background: h.w.warna }" />{{ h.w.pendek }} <b>{{ h.n }}</b>
      </button>
    </div>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
      <PetaTitik :titik="tampil" :terpilih="terpilih" :tinggi="520" @pilih="(id: number) => (terpilih = id)" />
      <Kartu :judul="pilih ? pilih.kode : 'Pilih satu titik'" :ket="pilih ? pilih.zona : 'Klik penanda di peta atau baris di tabel'" ikon="map-pin" warna="amber">
        <div v-if="pilih" class="space-y-3 text-[12.5px]">
          <p class="font-bold text-cam-ink text-[14px] leading-snug">{{ pilih.judul }}</p>
          <div class="flex flex-wrap gap-1.5">
            <span class="pr-pil" :class="tag(pilih.status).kelas">{{ tag(pilih.status).pendek }}</span>
            <span v-if="pilih.putusan" class="pr-pil" :class="PUTUSAN[pilih.putusan]?.kelas">Kajian: {{ PUTUSAN[pilih.putusan]?.label }}</span>
            <span v-if="pilih.perlu_pe" class="pr-pil pr-ungu">Perlu P.E.</span>
          </div>
          <dl class="grid grid-cols-[110px_1fr] gap-y-1.5">
            <dt class="text-stone-500">Dimensi</dt><dd class="font-semibold">{{ pilih.dimensi }}</dd>
            <dt class="text-stone-500">Sistem</dt><dd class="font-semibold">{{ pilih.jenis_label }}</dd>
            <dt class="text-stone-500">SWL</dt><dd class="font-semibold num">{{ pilih.swl }} kg/m²</dd>
            <dt class="text-stone-500">Faktor keamanan</dt><dd class="font-semibold num">{{ n(pilih.sf, 2) }}×</dd>
            <dt class="text-stone-500">Rasio H/B</dt><dd class="font-semibold num">{{ n(pilih.hb, 2) }} : 1</dd>
            <dt class="text-stone-500">Peralatan</dt><dd class="font-semibold">{{ pilih.peralatan || '—' }}</dd>
            <dt class="text-stone-500">Izin kerja</dt><dd class="font-semibold num">{{ pilih.izin || '—' }}</dd>
            <dt class="text-stone-500">Tag</dt><dd class="font-semibold num">{{ pilih.kode_tag || '—' }}</dd>
            <dt class="text-stone-500">Berlaku</dt><dd class="font-semibold">{{ sisa(pilih.inspeksi_berikut) }}</dd>
            <dt class="text-stone-500">GPS</dt><dd class="font-semibold num">{{ Number(pilih.lat).toFixed(6) }}, {{ Number(pilih.lng).toFixed(6) }}</dd>
          </dl>
          <div class="flex flex-wrap gap-2 pt-1">
            <Link :href="`/perancah/${pilih.id}`" class="eq-btn-utama">Rincian</Link>
            <Link v-if="pilih.status !== 'dibongkar'" :href="`/perancah/${pilih.id}/inspeksi`" class="eq-btn-lain">Inspeksi</Link>
            <Link v-if="pilih.kode_tag" :href="`/perancah/tag/${pilih.kode_tag}`" class="eq-btn-lain">Kartu tag</Link>
          </div>
        </div>
        <p v-else class="text-[12.5px] text-stone-500">Penanda merah berdenyut adalah perancah yang tidak layak digunakan: tag merah atau inspeksinya lewat 7 hari.</p>
      </Kartu>
    </section>

    <Kartu :judul="`Daftar perancah (${daftar.length})`" ket="Klik baris untuk menyorotnya di peta" ikon="list-checks" warna="amber" rapat>
      <div class="overflow-x-auto">
        <table class="w-full text-[12.5px]">
          <thead class="text-left text-[11px] uppercase tracking-wide text-stone-500 bg-stone-50">
            <tr><th class="px-4 py-2.5">Kode</th><th class="px-3">Pekerjaan</th><th class="px-3">Zona</th><th class="px-3">Dimensi</th>
              <th class="px-3 text-right">SF</th><th class="px-3">Tag</th><th class="px-3">Berlaku</th><th class="px-3"></th></tr>
          </thead>
          <tbody class="divide-y divide-stone-100">
            <tr v-for="u in daftar" :key="u.id" class="hover:bg-amber-50/40 cursor-pointer" :class="terpilih === u.id && 'bg-amber-50/70'" @click="terpilih = u.id">
              <td class="px-4 py-2.5 font-bold num whitespace-nowrap">{{ u.kode }}</td>
              <td class="px-3 py-2.5"><span class="font-semibold text-cam-ink">{{ u.judul }}</span><br><span class="text-[11px] text-stone-400">{{ u.peralatan || '—' }} · {{ u.izin || 'tanpa izin kerja' }}</span></td>
              <td class="px-3 py-2.5 whitespace-nowrap">{{ u.zona }}</td>
              <td class="px-3 py-2.5 whitespace-nowrap num">{{ u.dimensi }}</td>
              <td class="px-3 py-2.5 text-right num font-bold" :class="(u.sf ?? 9) < 4 ? 'pm-sf-rendah' : ''">{{ n(u.sf, 1) }}</td>
              <td class="px-3 py-2.5"><span class="pr-pil" :class="u.w.kelas">{{ u.w.pendek }}</span></td>
              <td class="px-3 py-2.5 whitespace-nowrap text-stone-500">{{ u.status === 'dibongkar' ? '—' : sisa(u.inspeksi_berikut) }}</td>
              <td class="px-3 py-2.5 text-right whitespace-nowrap"><Link :href="`/perancah/${u.id}`" class="eq-btn-mini" @click.stop>Buka</Link></td>
            </tr>
            <tr v-if="!daftar.length"><td colspan="8" class="px-4 py-10 text-center text-stone-400">Tidak ada perancah yang cocok dengan saringan.</td></tr>
          </tbody>
        </table>
      </div>
    </Kartu>
  </div>
</template>

<style scoped>
.pm-chip { display: inline-flex; align-items: center; gap: 7px; padding: 7px 12px; border-radius: 999px; font-size: 12px; font-weight: 700;
  background: #fff; border: 1px solid #E7E5E4; color: #57534E; }
.pm-chip i { width: 9px; height: 9px; border-radius: 999px; display: inline-block; }
.pm-chip b { font-weight: 800; color: #1C1917; }
.pm-aktif { border-color: #D97706; box-shadow: 0 0 0 3px rgba(217, 119, 6, .15); }
.pm-sf-rendah { color: #B91C1C; }
:root[data-tema="gelap"] .pm-chip { background: #20272B; border-color: #2E373C; color: #C9D2D6; }
:root[data-tema="gelap"] .pm-chip b { color: #E8EDEF; }
:root[data-tema="gelap"] .pm-sf-rendah { color: #F5A9A9; }
</style>

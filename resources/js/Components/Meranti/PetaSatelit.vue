<script setup lang="ts">
/*
  Peta Spasial MERANTI bergaya Google Earth.

  Citra satelit di atas bola dunia, medan 3D yang dapat dimiringkan dan
  diputar, dan poligon blok/petak di atasnya. Saat dibuka, kamera
  "terbang" dari bola dunia ke lokasi reklamasi — kecuali bagi yang
  memilih gerak dikurangi di perangkatnya.

  Pustakanya (MapLibre GL) dan pekerjanya dimuat saat komponen dipasang,
  bukan bersama halaman: 1 MB lebih yang hanya berguna di halaman ini.
  Pekerjanya disajikan dari asal sendiri (tanpa blob), dan ubin hanya dari
  host di config/meranti.php — keduanya supaya CSP tetap ketat.

  Bila WebGL tidak tersedia, komponen ini mengirim `gagal` dan halaman
  kembali ke peta skema. Bila citra satelit tidak termuat (jaringan site
  tertutup), petanya tetap jalan dengan catatan dan tombol ke peta skema.
*/
import { onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import type { LngLatLike, Map as PetaMaplibre, MapGeoJSONFeature, Popup } from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import { n } from '../../energi';
import { garisSatelit, warnaKategori } from '../../meranti';
import type { FiturPeta } from './PetaGeo.vue';

export type UbinPeta = { satelit: string; label: string | null; medan: string | null; atribusi: string; atribusiMedan: string | null; zoomMaks: number };

const props = withDefaults(defineProps<{ fitur: FiturPeta[]; ubin: UbinPeta; tinggi?: number; terpilih?: number | null }>(), { tinggi: 560, terpilih: null });
const emit = defineEmits<{ (e: 'pilih', id: number): void; (e: 'gagal', alasan: string): void }>();

const KALIMANTAN: [number, number] = [116.6, -0.6];
const MIRING = 58;
const LEBIH_TINGGI = 1.6;

const wadah = ref<HTMLDivElement | null>(null);
const peta = shallowRef<PetaMaplibre | null>(null);
let ml: typeof import('maplibre-gl') | null = null;
const siap = ref(false);
const tigaD = ref(!!props.ubin.medan);
const label = ref(!!props.ubin.label);
const citraGagal = ref(false);
let ubinMasuk = 0;
let galatUbin = 0;
let popup: Popup | null = null;
let sorotId: number | null = null;
let pengawas: ReturnType<typeof setTimeout> | null = null;
let pengamat: IntersectionObserver | null = null;
const geraknya = () => !window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

function koleksi() {
  return {
    type: 'FeatureCollection' as const,
    features: props.fitur.map(f => ({
      type: 'Feature' as const, id: f.id, geometry: f.geometri,
      properties: { id: f.id, nama: f.nama, kategori: f.kategori || 'Lainnya', warna: warnaKategori(f.kategori), garis: garisSatelit(f.kategori),
        luas: f.luas, petak: f.petak ?? '', sumber: f.sumber ?? '' },
    })),
  };
}

type Batas = [[number, number], [number, number]];
function batas(daftar: FiturPeta[]): Batas | null {
  let x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
  const titik = (p: number[]) => { x0 = Math.min(x0, p[0]); x1 = Math.max(x1, p[0]); y0 = Math.min(y0, p[1]); y1 = Math.max(y1, p[1]); };
  for (const f of daftar) {
    const g = f.geometri;
    if (g.type === 'Point') titik(g.coordinates);
    else if (g.type === 'LineString') g.coordinates.forEach(titik);
    else g.coordinates.forEach(r => r.forEach(titik));
  }
  return Number.isFinite(x0) ? [[x0, y0], [x1, y1]] : null;
}

/* Gaya peta disusun di sini, bukan diunduh: tidak ada server gaya, huruf,
   atau sprite luar yang perlu diizinkan CSP. Tipe gaya MapLibre sangat
   ketat terhadap literal ekspresi, jadi objeknya diketik longgar. */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
function gaya(): any {
  const u = props.ubin;
  const sorot = ['boolean', ['feature-state', 'sorot'], false];
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const sumber: Record<string, any> = {
    satelit: { type: 'raster', tiles: [u.satelit], tileSize: 256, maxzoom: u.zoomMaks, attribution: u.atribusi },
    fitur: { type: 'geojson', data: koleksi(), promoteId: 'id' },
  };
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const lapisan: any[] = [{ id: 'satelit', type: 'raster', source: 'satelit', paint: { 'raster-fade-duration': 250 } }];
  if (u.medan) sumber.medan = { type: 'raster-dem', tiles: [u.medan], tileSize: 256, maxzoom: 15, encoding: 'terrarium', attribution: u.atribusiMedan ?? undefined };
  lapisan.push(
    { id: 'fitur-isi', type: 'fill', source: 'fitur', filter: ['==', ['geometry-type'], 'Polygon'],
      paint: { 'fill-color': ['case', sorot, ['get', 'garis'], ['get', 'warna']], 'fill-opacity': ['case', sorot, 0.42, 0.24] } },
    { id: 'fitur-pinggir', type: 'line', source: 'fitur', filter: ['!=', ['geometry-type'], 'Point'], layout: { 'line-join': 'round' },
      paint: { 'line-color': '#06140E', 'line-opacity': 0.55, 'line-width': ['case', sorot, 7, 4.6] } },
    { id: 'fitur-garis', type: 'line', source: 'fitur', filter: ['!=', ['geometry-type'], 'Point'], layout: { 'line-join': 'round' },
      paint: { 'line-color': ['case', sorot, '#FFFFFF', ['get', 'garis']], 'line-width': ['case', sorot, 3.4, 2] } },
    { id: 'fitur-titik', type: 'circle', source: 'fitur', filter: ['==', ['geometry-type'], 'Point'],
      paint: { 'circle-radius': ['case', sorot, 9, 7], 'circle-color': ['get', 'garis'], 'circle-stroke-color': '#06140E', 'circle-stroke-width': 2 } },
  );
  if (u.label) {
    sumber.label = { type: 'raster', tiles: [u.label], tileSize: 256, maxzoom: u.zoomMaks };
    lapisan.push({ id: 'label', type: 'raster', source: 'label', layout: { visibility: label.value ? 'visible' : 'none' } });
  }

  return {
    version: 8,
    projection: { type: 'globe' },
    // Langit dan selubung atmosfer di tepi bola dunia, memudar saat mendekat.
    sky: { 'sky-color': '#0B2545', 'horizon-color': '#A7C7E7', 'fog-color': '#FFFFFF', 'sky-horizon-blend': 0.55, 'horizon-fog-blend': 0.6,
      'fog-ground-blend': 0.35, 'atmosphere-blend': ['interpolate', ['linear'], ['zoom'], 0, 1, 6, 1, 10, 0] },
    sources: sumber,
    layers: lapisan,
    ...(u.medan && tigaD.value ? { terrain: { source: 'medan', exaggeration: LEBIH_TINGGI } } : {}),
  };
}

/** Terbang ke seluruh fitur; pembukaan memakai lintasan panjang dari bola dunia. */
function terbang(awal = false) {
  const m = peta.value;
  if (!m) return;
  const b = batas(props.fitur);
  const lama = geraknya() ? (awal ? 5200 : 1800) : 0;
  if (!b) { m.flyTo({ center: KALIMANTAN, zoom: 5, duration: lama, essential: true }); return; }
  m.fitBounds(b, { padding: 64, pitch: tigaD.value ? MIRING : 0, bearing: tigaD.value ? -18 : 0, maxZoom: 16, duration: lama, essential: true });
}

function aturSorot(id: number | null) {
  const m = peta.value;
  if (!m || !siap.value) return;
  if (sorotId !== null) m.setFeatureState({ source: 'fitur', id: sorotId }, { sorot: false });
  sorotId = id;
  if (id !== null) m.setFeatureState({ source: 'fitur', id }, { sorot: true });
}

function bukaPopup(f: { nama: string; kategori: string | null; petak?: string | null; luas: number | null; sumber?: string | null }, lokasi: LngLatLike) {
  const m = peta.value;
  if (!m || !ml) return;
  // Isi popup dibangun lewat textContent, bukan HTML: nama fitur datang dari berkas unggahan.
  const isi = document.createElement('div');
  isi.className = 'ms-popup';
  const judul = document.createElement('p');
  judul.className = 'ms-popup-judul';
  judul.textContent = f.nama;
  const ket = document.createElement('p');
  ket.textContent = [f.kategori || 'Lainnya', f.petak || null, f.luas ? `${n(f.luas, 2)} ha` : null].filter(Boolean).join(' · ');
  isi.append(judul, ket);
  if (f.sumber) {
    const s = document.createElement('p');
    s.className = 'ms-popup-sumber';
    s.textContent = f.sumber;
    isi.append(s);
  }
  popup?.remove();
  popup = new ml.Popup({ closeButton: true, maxWidth: '280px', offset: 12 }).setLngLat(lokasi).setDOMContent(isi).addTo(m);
}

function pasangInteraksi(m: PetaMaplibre) {
  for (const lapis of ['fitur-isi', 'fitur-garis', 'fitur-titik']) {
    m.on('click', lapis, (e) => {
      const f = e.features?.[0] as MapGeoJSONFeature | undefined;
      if (!f) return;
      const id = Number(f.properties.id);
      aturSorot(id);
      bukaPopup({ nama: String(f.properties.nama ?? ''), kategori: f.properties.kategori ?? null, petak: f.properties.petak || null,
        luas: f.properties.luas === null || f.properties.luas === undefined ? null : Number(f.properties.luas), sumber: f.properties.sumber || null }, e.lngLat);
      emit('pilih', id);
    });
    m.on('mouseenter', lapis, () => { m.getCanvas().style.cursor = 'pointer'; });
    m.on('mouseleave', lapis, () => { m.getCanvas().style.cursor = ''; });
  }
}

/** Pusatkan dan miringkan kamera ke satu fitur — dari daftar di samping peta. */
function fokus(id: number) {
  const m = peta.value;
  const f = props.fitur.find(x => x.id === id);
  if (!m || !f) return;
  const b = batas([f]);
  if (!b) return;
  aturSorot(id);
  const lama = geraknya() ? 2200 : 0;
  if (f.geometri.type === 'Point') m.flyTo({ center: f.geometri.coordinates as [number, number], zoom: 17, pitch: tigaD.value ? MIRING : 0, duration: lama, essential: true });
  else m.fitBounds(b, { padding: 90, pitch: tigaD.value ? MIRING : 0, bearing: m.getBearing(), maxZoom: 17.5, duration: lama, essential: true });
  bukaPopup(f, [(b[0][0] + b[1][0]) / 2, (b[0][1] + b[1][1]) / 2]);
}

function aturTigaD(nyala: boolean) {
  tigaD.value = nyala;
  const m = peta.value;
  if (!m || !props.ubin.medan) return;
  m.setTerrain(nyala ? { source: 'medan', exaggeration: LEBIH_TINGGI } : null);
  m.easeTo({ pitch: nyala ? MIRING : 0, bearing: nyala ? (m.getBearing() || -18) : 0, duration: geraknya() ? 900 : 0 });
}

function aturLabel(nyala: boolean) {
  label.value = nyala;
  if (peta.value?.getLayer('label')) peta.value.setLayoutProperty('label', 'visibility', nyala ? 'visible' : 'none');
}

/* Pustakanya baru dimuat saat petanya tampak di layar: di dasbor, kartu
   peta ada di bawah lipatan, dan pengunjung yang tidak menggulir ke sana
   tidak perlu mengunduh satu megabita lebih. */
onMounted(() => {
  if (!wadah.value) return;
  if (typeof IntersectionObserver === 'undefined') { void pasang(); return; }
  pengamat = new IntersectionObserver((entri) => {
    if (entri.some(x => x.isIntersecting)) { pengamat?.disconnect(); pengamat = null; void pasang(); }
  }, { rootMargin: '240px' });
  pengamat.observe(wadah.value);
});

async function pasang() {
  try {
    const [modul, pekerja] = await Promise.all([import('maplibre-gl'), import('maplibre-gl/dist/maplibre-gl-worker.mjs?url')]);
    ml = modul;
    ml.setWorkerUrl(new URL(pekerja.default, window.location.href).href);
    if (!wadah.value) return;

    const b = batas(props.fitur);
    const pusat: [number, number] = b ? [(b[0][0] + b[1][0]) / 2, (b[0][1] + b[1][1]) / 2] : KALIMANTAN;
    const m = new ml.Map({
      container: wadah.value, style: gaya(), center: pusat, zoom: geraknya() ? 1.3 : 13, pitch: 0, maxPitch: 78,
      maxZoom: Math.max(props.ubin.zoomMaks + 2, 18), attributionControl: { compact: true },
    });
    peta.value = m;

    m.addControl(new ml.NavigationControl({ visualizePitch: true }), 'top-right');
    m.addControl(new ml.GlobeControl(), 'top-right');
    m.addControl(new ml.FullscreenControl(), 'top-right');
    m.addControl(new ml.ScaleControl({ unit: 'metric', maxWidth: 120 }), 'bottom-left');

    m.on('data', (e) => {
      if (e.dataType === 'source' && 'sourceId' in e && e.sourceId === 'satelit' && 'tile' in e && e.tile) { ubinMasuk++; citraGagal.value = false; }
    });
    m.on('error', (e) => {
      if ('sourceId' in e && e.sourceId === 'satelit' && ++galatUbin >= 4 && ubinMasuk === 0) citraGagal.value = true;
    });
    m.on('load', () => {
      siap.value = true;
      pasangInteraksi(m);
      if (props.terpilih !== null) aturSorot(props.terpilih);
      terbang(true);
    });
    // Jaringan site yang tertutup tidak selalu menjawab dengan galat — kadang hanya diam.
    pengawas = setTimeout(() => { if (ubinMasuk === 0) citraGagal.value = true; }, 15000);
  } catch {
    emit('gagal', 'Peramban ini tidak dapat menggambar peta 3D (WebGL tidak tersedia) — memakai peta skema.');
  }
}

onBeforeUnmount(() => {
  pengamat?.disconnect();
  if (pengawas) clearTimeout(pengawas);
  popup?.remove();
  peta.value?.remove();
  peta.value = null;
});

watch(() => props.fitur, () => {
  const s = peta.value?.getSource('fitur') as { setData?: (d: unknown) => void } | undefined;
  s?.setData?.(koleksi());
});
watch(() => props.terpilih, (id) => { if (id !== null && id !== sorotId) fokus(id); });

defineExpose({ fokus, terbang });
</script>

<template>
  <div class="ms" :style="{ height: `${tinggi}px` }">
    <div ref="wadah" class="ms-peta" role="region" :aria-label="`Peta satelit ${fitur.length} fitur spasial`" />

    <div class="ms-kendali">
      <button v-if="ubin.medan" type="button" :class="{ aktif: tigaD }" :aria-pressed="tigaD" @click="aturTigaD(!tigaD)">3D</button>
      <button v-if="ubin.label" type="button" :class="{ aktif: label }" :aria-pressed="label" @click="aturLabel(!label)">Label</button>
      <button type="button" title="Terbang ke seluruh fitur" @click="terbang()">Lokasi</button>
    </div>

    <div v-if="!siap" class="ms-muat"><span class="ms-putar" />Memuat peta satelit…</div>

    <div v-if="citraGagal" class="ms-catatan" role="status">
      <span>Citra satelit tidak termuat — jaringan mungkin tertutup.</span>
      <button type="button" @click="emit('gagal', 'Citra satelit tidak termuat — memakai peta skema.')">Pakai peta skema</button>
    </div>
  </div>
</template>

<style scoped>
.ms { position: relative; border-radius: 14px; overflow: hidden; background: #0B2545; }
.ms-peta { position: absolute; inset: 0; }
.ms-kendali { position: absolute; top: 10px; left: 10px; z-index: 2; display: flex; gap: 4px; padding: 3px; border-radius: 11px; background: rgba(10, 30, 22, .62); backdrop-filter: blur(6px); }
.ms-kendali button { padding: 5px 11px; border-radius: 8px; font-size: 11.5px; font-weight: 800; color: #E7F1EC; }
.ms-kendali button.aktif { background: #fff; color: #0A3A2A; }
.ms-muat { position: absolute; inset: 0; z-index: 3; display: flex; align-items: center; justify-content: center; gap: 10px; color: #DDE7F0; font-size: 12.5px; font-weight: 700;
  background: radial-gradient(circle at 50% 45%, #163a63 0%, #0B2545 60%, #06162a 100%); }
.ms-putar { width: 18px; height: 18px; border-radius: 999px; border: 2px solid rgba(255, 255, 255, .25); border-top-color: #8FE3BE; animation: ms-putar 1s linear infinite; }
@keyframes ms-putar { to { transform: rotate(360deg); } }
.ms-catatan { position: absolute; left: 10px; right: 10px; bottom: 34px; z-index: 2; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px;
  padding: 8px 12px; border-radius: 12px; background: rgba(255, 255, 255, .95); color: #7A5D0B; font-size: 12px; font-weight: 700; box-shadow: 0 8px 20px -12px rgba(0, 0, 0, .5); }
.ms-catatan button { padding: 4px 10px; border-radius: 8px; background: #0A3A2A; color: #fff; font-size: 11.5px; font-weight: 800; }
:deep(.ms-popup) { font-size: 12px; color: #334155; }
:deep(.ms-popup-judul) { font-weight: 900; color: #0A3A2A; margin-bottom: 2px; }
:deep(.ms-popup-sumber) { color: #78716C; font-size: 11px; margin-top: 2px; }
:deep(.maplibregl-ctrl-attrib) { font-size: 10px; }
@media (prefers-reduced-motion: reduce) { .ms-putar { animation: none; } }
</style>

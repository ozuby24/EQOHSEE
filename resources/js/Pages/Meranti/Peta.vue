<script setup lang="ts">
/*
  Peta Spasial MERANTI.

  Unggah KML, KMZ, atau GeoJSON — setiap placemark/geometri menjadi satu
  fitur dengan luas yang dihitung bila berkasnya tidak menyebutnya.

  Dua tampilan: Satelit 3D (citra satelit, medan, bola dunia — seperti
  Google Earth) dan Skema (digambar dari koordinat sendiri, tanpa
  internet, untuk jaringan site yang tertutup). Pilihan terakhir diingat
  per peramban; bila peta 3D gagal, halaman kembali ke skema dengan
  sendirinya. Daftar di samping memusatkan peta ke fitur yang dipilih.
*/
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import PilihPerusahaan from '../../Components/Energi/PilihPerusahaan.vue';
import Dialog from '../../Components/Dialog.vue';
import PetaGeo from '../../Components/Meranti/PetaGeo.vue';
import PetaSatelit from '../../Components/Meranti/PetaSatelit.vue';
import Lencana from '../../Components/Meranti/Lencana.vue';
import { useDialog } from '../../dialog';
import { n } from '../../energi';
import { warnaKategori } from '../../meranti';

const props = propHalaman();
const izin = computed(() => props.izin ?? {});
const { dialog, tanya, batal, lanjut } = useDialog();

const cari = ref('');
const kategori = ref('');
const terpilih = ref<number | null>(null);
const peta = ref<InstanceType<typeof PetaGeo> | null>(null);
const petaSatelit = ref<InstanceType<typeof PetaSatelit> | null>(null);

/* Tampilan: satelit bila sumber ubinnya dikonfigurasi. Pilihan diingat
   per peramban — hanya kenyamanan, jadi gagal membaca/menulisnya tidak
   mengubah apa pun selain kembali ke bawaan. */
const KUNCI_TAMPILAN = 'meranti.peta.tampilan';
function tampilanTersimpan(): 'satelit' | 'skema' | null {
  try { const v = window.localStorage.getItem(KUNCI_TAMPILAN); return v === 'satelit' || v === 'skema' ? v : null; } catch { return null; }
}
const tampilan = ref<'satelit' | 'skema'>(props.ubin ? (tampilanTersimpan() ?? 'satelit') : 'skema');
const pesanTampilan = ref('');
// Ponsel: peta lebih pendek supaya daftar fitur di bawahnya tetap terjangkau.
const tinggiPeta = typeof window !== 'undefined' && window.innerWidth < 640 ? 440 : 600;
function aturTampilan(t: 'satelit' | 'skema') {
  tampilan.value = t;
  pesanTampilan.value = '';
  try { window.localStorage.setItem(KUNCI_TAMPILAN, t); } catch { /* penyimpanan peramban tidak tersedia */ }
}
function satelitGagal(alasan: string) {
  tampilan.value = 'skema';
  pesanTampilan.value = alasan;
}

const fitur = computed(() => (props.fitur ?? []).filter((f: any) => (!kategori.value || (f.kategori || 'Lainnya') === kategori.value)
  && (!cari.value || `${f.nama} ${f.petak ?? ''} ${f.sumber ?? ''}`.toLowerCase().includes(cari.value.toLowerCase()))));
const luasTampil = computed(() => fitur.value.reduce((a: number, f: any) => a + (Number(f.luas) || 0), 0));
const dipilih = computed(() => (props.fitur ?? []).find((f: any) => f.id === terpilih.value) ?? null);

function pilih(id: number) {
  terpilih.value = id;
  if (tampilan.value === 'satelit') petaSatelit.value?.fokus(id);
  else peta.value?.fokus(id);
}

const unggah = useForm<{ berkas: File | null; kategori: string }>({ berkas: null, kategori: '' });
function kirim() {
  unggah.post('/meranti/peta/unggah', { forceFormData: true, preserveScroll: true, onSuccess: () => unggah.reset() });
}

async function hapus(f: any) {
  if (await tanya({ judul: `Hapus fitur ${f.nama}?`, pesan: 'Fitur ini dihapus dari Peta Spasial dan tercatat di Log Aktivitas.', labelAksi: 'Hapus', nada: 'bahaya' })) {
    router.delete(`/meranti/data/peta_spasial/${f.id}`, { preserveScroll: true, onSuccess: () => { if (terpilih.value === f.id) terpilih.value = null; } });
  }
}
const kueri = computed(() => (props.perusahaan ? `?perusahaan=${props.perusahaan}` : ''));
</script>

<template>
  <Head title="Peta Spasial" />
  <div class="space-y-4">
    <section class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex flex-wrap items-center gap-2">
        <input v-model="cari" type="search" placeholder="Cari nama, petak, berkas" aria-label="Cari fitur" class="ps-i">
        <button type="button" class="ps-chip" :class="{ aktif: !kategori }" @click="kategori = ''">Semua <b class="num">{{ props.ringkas?.fitur }}</b></button>
        <button v-for="k in props.ringkas?.kategori ?? []" :key="k.label" type="button" class="ps-chip" :class="{ aktif: kategori === k.label }" @click="kategori = k.label">
          <i :style="{ background: warnaKategori(k.label) }" />{{ k.label }} <b class="num">{{ k.n }}</b>
        </button>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <a :href="`/meranti/peta/geojson${kueri}`" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="globe" :ukuran="15" />GeoJSON</a>
        <Link href="/meranti/peta-spasial" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="list-filter" :ukuran="15" />Tabel fitur</Link>
        <PilihPerusahaan :daftar="props.daftarPerusahaan" :terpilih="props.perusahaan" />
      </div>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.75fr)_minmax(0,1fr)] items-start">
      <Kartu judul="Peta" :ket="tampilan === 'satelit'
          ? `${fitur.length} fitur · ${n(luasTampil, 2)} ha · seret untuk menggeser, klik kanan/dua jari untuk memiringkan dan memutar`
          : `${fitur.length} fitur · ${n(luasTampil, 2)} ha · gulir untuk memperbesar, seret untuk menggeser`" ikon="map" warna="hijau">
        <template v-if="props.ubin" #aksi>
          <div class="ps-tab" role="group" aria-label="Tampilan peta">
            <button type="button" :class="{ aktif: tampilan === 'satelit' }" :aria-pressed="tampilan === 'satelit'" @click="aturTampilan('satelit')"><Ikon nama="globe" :ukuran="14" />Satelit 3D</button>
            <button type="button" :class="{ aktif: tampilan === 'skema' }" :aria-pressed="tampilan === 'skema'" @click="aturTampilan('skema')"><Ikon nama="map" :ukuran="14" />Skema</button>
          </div>
        </template>
        <p v-if="pesanTampilan" class="ps-pesan">{{ pesanTampilan }}</p>
        <PetaSatelit v-if="tampilan === 'satelit' && props.ubin" ref="petaSatelit" :fitur="fitur" :ubin="props.ubin" :tinggi="tinggiPeta" :terpilih="terpilih" @pilih="pilih" @gagal="satelitGagal" />
        <PetaGeo v-else ref="peta" :fitur="fitur" :tinggi="560" :terpilih="terpilih" @pilih="pilih" />
        <div v-if="dipilih" class="ps-rinci mt-3">
          <div class="min-w-0">
            <p class="font-black text-cam-ink truncate">{{ dipilih.nama }}</p>
            <p class="text-[11.5px] text-stone-500">{{ [dipilih.kategori, dipilih.petak, dipilih.week, dipilih.month, dipilih.year].filter(Boolean).join(' · ') }}<template v-if="dipilih.sumber"> · {{ dipilih.sumber }}</template></p>
          </div>
          <b v-if="dipilih.luas" class="num whitespace-nowrap">{{ n(dipilih.luas, 4) }} ha</b>
          <button v-if="izin.hapus" type="button" class="eq-btn-mini ps-hapus" @click="hapus(dipilih)">Hapus</button>
        </div>
      </Kartu>

      <div class="space-y-4">
        <Kartu v-if="izin.tambah" judul="Unggah KML / KMZ / GeoJSON" ket="Setiap placemark menjadi satu fitur; luas poligon dihitung otomatis" ikon="map-pin" warna="amber">
          <form class="space-y-3" @submit.prevent="kirim">
            <label class="ps-label">Berkas peta
              <input type="file" accept=".kml,.kmz,.geojson,.json" class="ps-berkas" required @input="unggah.berkas = ($event.target as HTMLInputElement).files?.[0] ?? null">
            </label>
            <label class="ps-label">Kategori (opsional)
              <select v-model="unggah.kategori" class="ps-i w-full">
                <option value="">Ikuti berkas — poligon jadi Blok, titik jadi Titik Tanam</option>
                <option v-for="k in props.kategori ?? []" :key="k" :value="k">{{ k }}</option>
              </select>
            </label>
            <p v-if="unggah.errors.berkas" class="text-[12px] font-bold text-red-700">{{ unggah.errors.berkas }}</p>
            <div v-if="unggah.progress" class="ps-progres"><i :style="{ width: `${unggah.progress.percentage}%` }" /></div>
            <button type="submit" class="eq-btn-utama w-full" :disabled="unggah.processing || !unggah.berkas">{{ unggah.processing ? 'Mengunggah…' : 'Unggah & simpan fitur' }}</button>
          </form>
        </Kartu>

        <Kartu judul="Fitur" :ket="`${fitur.length} fitur`" ikon="layers" rapat>
          <ul class="divide-y divide-stone-100 max-h-[520px] overflow-y-auto">
            <li v-for="f in fitur" :key="f.id">
              <button type="button" class="w-full text-left px-5 py-2.5 flex items-center gap-3 ps-baris" :class="{ aktif: terpilih === f.id }" @click="pilih(f.id)">
                <i class="ps-warna" :style="{ background: warnaKategori(f.kategori) }" />
                <span class="min-w-0 grow">
                  <span class="block text-[12.5px] font-bold text-cam-ink truncate">{{ f.nama }}</span>
                  <span class="block text-[11px] text-stone-500 truncate">{{ f.geometri.type === 'Point' ? 'Titik' : f.geometri.type === 'LineString' ? 'Garis' : 'Poligon' }}<template v-if="f.petak"> · {{ f.petak }}</template><template v-if="f.sumber"> · {{ f.sumber }}</template></span>
                </span>
                <span v-if="f.luas" class="text-[11.5px] font-bold num whitespace-nowrap">{{ n(f.luas, 2) }} ha</span>
              </button>
            </li>
            <li v-if="!fitur.length" class="px-5 py-6 text-center text-[12px] text-stone-400">Tidak ada fitur.</li>
          </ul>
        </Kartu>

        <Kartu v-if="(props.ringkas?.sumber ?? []).length" judul="Berkas sumber" ikon="file-text" rapat>
          <ul class="divide-y divide-stone-100 text-[12px]">
            <li v-for="s in props.ringkas.sumber" :key="s.label" class="px-5 py-2 flex justify-between gap-3"><span class="truncate">{{ s.label }}</span><Lencana :nilai="`${s.n} fitur`" /></li>
          </ul>
        </Kartu>
      </div>
    </section>

    <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
  </div>
</template>

<style scoped>
.ps-i { border-radius: 10px; border: 1px solid #E7E5E4; font-size: 12.5px; padding: 7px 10px; min-width: 200px; background: #fff; }
.ps-chip { display: inline-flex; align-items: center; gap: 7px; padding: 6px 12px; border-radius: 999px; border: 1px solid #E7E5E4; background: #fff; font-size: 12px; font-weight: 700; color: #44403C; }
.ps-chip i { width: 8px; height: 8px; border-radius: 999px; }
.ps-chip.aktif { border-color: #2E7D55; background: #ECF7F0; color: #0A3A2A; }
.ps-label { display: flex; flex-direction: column; gap: 4px; font-size: 11.5px; font-weight: 700; color: #57534E; }
.ps-berkas { font-size: 12px; }
.ps-progres { height: 6px; border-radius: 999px; background: #F1F5F9; overflow: hidden; }
.ps-progres i { display: block; height: 100%; background: #2E7D55; }
.ps-baris:hover, .ps-baris.aktif { background: #F4FAF6; }
.ps-warna { width: 12px; height: 12px; border-radius: 4px; flex: none; }
.ps-rinci { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 12px; background: #F4FAF6; border: 1px solid #C9E7D3; font-size: 12.5px; }
.ps-rinci > div { flex: 1 1 220px; }
.ps-hapus { color: #B91C1C; }
.ps-tab { display: inline-flex; gap: 2px; padding: 3px; border-radius: 10px; background: #F5F5F4; }
.ps-tab button { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; border-radius: 8px; font-size: 11.5px; font-weight: 800; color: #57534E; }
.ps-tab button.aktif { background: #fff; color: #0A3A2A; box-shadow: 0 1px 3px rgba(0, 0, 0, .08); }
.ps-pesan { margin-bottom: 8px; padding: 7px 11px; border-radius: 10px; background: #FEF7E6; color: #7A5D0B; font-size: 12px; font-weight: 700; }
:root[data-tema="gelap"] .ps-i, :root[data-tema="gelap"] .ps-chip { background: #20272B; border-color: #2E373C; color: #D5DDE1; }
:root[data-tema="gelap"] .ps-chip.aktif { background: #12271D; border-color: #22C55E; color: #8FE3BE; }
:root[data-tema="gelap"] .ps-label { color: #B4BEC3; }
:root[data-tema="gelap"] .ps-baris:hover, :root[data-tema="gelap"] .ps-baris.aktif { background: #20272B; }
:root[data-tema="gelap"] .ps-rinci { background: #12271D; border-color: #1F5A3A; }
:root[data-tema="gelap"] .ps-tab { background: #20272B; }
:root[data-tema="gelap"] .ps-tab button { color: #B4BEC3; }
:root[data-tema="gelap"] .ps-tab button.aktif { background: #2E373C; color: #8FE3BE; }
:root[data-tema="gelap"] .ps-pesan { background: #3A2F14; color: #F3D9A0; }
</style>

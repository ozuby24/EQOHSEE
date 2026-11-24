<script setup lang="ts">
/*
  Rincian satu lokasi Rehab DAS.

  Bagian teratas menjawab satu pertanyaan: apakah lokasi ini sudah boleh
  diajukan untuk dinilai? Sepuluh prasyarat — SK Penlok, RKP, usia ≥ 36
  bulan, tumbuh ≥ 75%, ≥ 500 batang sehat/ha, geotag 100% tanpa
  duplikasi, petak berpeta, tidak ada gangguan terbuka — dibaca dari data
  di bawahnya, bukan dicentang tangan.
*/
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import { useDialog } from '../../dialog';
import Dialog from '../../Components/Dialog.vue';
import Kartu from '../../Components/Energi/Kartu.vue';
import KartuAngka from '../../Components/Energi/KartuAngka.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import PetaPoligon from '../../Components/Rdas/PetaPoligon.vue';
import KartuGrafik from '../../Grafik/KartuGrafik.vue';
import Kolom from '../../Grafik/Kolom.vue';
import Cincin from '../../Grafik/Cincin.vue';
import { n, rp } from '../../energi';
import { fase, tumbuh, penilaian, warnaTumbuh, KATEGORI_JENIS, tanggal } from '../../rdas';

const props = propHalaman();
const { dialog, minta, batal, lanjut } = useDialog();
const b = computed(() => props.blok ?? {});
const k = computed(() => props.kelayakan ?? { cek: [] });
const pil = computed(() => props.pilihan ?? {});
const galat = computed(() => (props.errors ?? {}) as Record<string, string>);
const jenisMap = computed(() => Object.fromEntries((pil.value.jenis ?? []).map((j: any) => [j.nilai, j])));

async function ajukan() {
  const sisa = Math.max(0, b.value.luas_ha - (props.luasBast ?? 0));
  const luas = await minta({ judul: 'Ajukan penilaian keberhasilan', pesan: 'Permohonan disampaikan kepada Dirjen PDASRH; tim penilai diketuai Kepala BPDAS. Boleh bertahap (sebagian luas).',
    label: `Luas yang diajukan (ha, maks ${n(sisa, 2)})`, jenis: 'angka', nilai: String(Math.round(sisa * 100) / 100), wajib: true, labelAksi: 'Ajukan' });
  if (luas === null) return;
  router.post(`/rehab-das/blok/${b.value.id}/penilaian`, { luas_diajukan_ha: Number(luas) }, { preserveScroll: true });
}

const formPetak = ref(false);
const fPetak = useForm({ kode: '', luas_ha: null as number | null, kelerengan: 'landai', lat: null as number | null, lng: null as number | null, poligon: '', bibit_tanam: 0 });
function simpanPetak() { fPetak.post(`/rehab-das/blok/${b.value.id}/petak`, { preserveScroll: true, onSuccess: () => { formPetak.value = false; fPetak.reset(); } }); }

const formKeg = ref(false);
const fKeg = useForm({ tanggal: new Date().toISOString().slice(0, 10), jenis: 'penyiangan', petak_id: null as number | null, luas_ha: null as number | null, bibit: 0, hok: 0, biaya_rp: 0, pelaksana: '', catatan: '' });
function simpanKeg() { fKeg.post(`/rehab-das/blok/${b.value.id}/kegiatan`, { preserveScroll: true, onSuccess: () => { formKeg.value = false; fKeg.reset(); } }); }
async function verifikasi(kg: any, aksi: 'terima' | 'tolak') {
  let catatan: string | null = null;
  if (aksi === 'tolak') {
    catatan = await minta({ judul: 'Tolak laporan kegiatan?', label: 'Alasan penolakan', jenis: 'panjang', wajib: true, labelAksi: 'Tolak', nada: 'bahaya' });
    if (catatan === null) return;
  }
  router.post(`/rehab-das/kegiatan/${kg.id}/verifikasi`, { aksi, catatan }, { preserveScroll: true });
}

const fGeo = useForm({ geotag_total: b.value.geotag_total ?? 0, geotag_duplikat: b.value.geotag_duplikat ?? 0 });
function simpanGeo() { fGeo.post(`/rehab-das/blok/${b.value.id}/geotag`, { preserveScroll: true }); }

const tren = computed(() => props.tren ?? []);
const statusKeg: Record<string, string> = { diajukan: 'pr-kuning', diverifikasi: 'pr-hijau', ditolak: 'pr-merah' };
const tahapan = ['diajukan', 'tim_dibentuk', 'dinilai', 'berhasil', 'serah_terima'];
</script>

<template>
  <Head :title="`${b.kode} · ${b.nama}`" />
  <div class="space-y-4">
    <section class="bk-kepala">
      <div class="min-w-0">
        <p class="text-[11.5px] font-bold text-stone-500"><Link :href="`/rehab-das/kewajiban/${b.ppkh_id}`" class="hover:underline">{{ b.ppkh_kode }}</Link> · {{ b.bpdas }} · {{ b.das }}{{ b.sub_das ? ' / ' + b.sub_das : '' }}</p>
        <h1 class="text-[20px] font-extrabold text-cam-ink leading-tight">{{ b.kode }} · {{ b.nama }}</h1>
        <p class="text-[12px] text-stone-500 mt-1">{{ b.kabupaten }}{{ b.desa ? ', ' + b.desa : '' }} · {{ b.fungsi_kawasan }}{{ b.pemangku ? ' · ' + b.pemangku : '' }} · {{ n(b.luas_ha, 2) }} ha · {{ b.jarak_tanam }} ({{ b.kerapatan }} btg/ha) · {{ b.kontraktor }}</p>
        <div class="flex flex-wrap gap-1.5 mt-2">
          <span class="pr-pil" :class="fase(b.fase).kelas">{{ fase(b.fase).label }}</span>
          <span v-if="b.usia_bulan !== null" class="pr-pil pr-abu">Usia {{ b.usia_bulan }} bulan sejak P0 {{ tanggal(b.tanggal_tanam) }}</span>
          <span v-if="b.persen_tumbuh !== null" class="pr-pil" :class="b.persen_tumbuh >= 80 ? 'pr-hijau' : b.persen_tumbuh >= 75 ? 'pr-kuning' : 'pr-merah'">Tumbuh {{ n(b.persen_tumbuh, 1) }}%</span>
          <span v-if="props.luasBast" class="pr-pil pr-hijau">BAST {{ n(props.luasBast, 2) }} ha</span>
        </div>
      </div>
      <div class="flex flex-wrap gap-2">
        <Link v-if="b.fase !== 'serah_terima'" :href="`/rehab-das/blok/${b.id}/ubah`" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="sliders-horizontal" :ukuran="15" />Ubah</Link>
        <Link href="/rehab-das/penilaian" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="clipboard-check" :ukuran="15" />Penilaian & BAST</Link>
      </div>
    </section>

    <!-- sudah diserahterimakan penuh -->
    <section v-if="b.fase === 'serah_terima'" class="bk-layak bk-layak-ok">
      <Ikon nama="file-check-2" :ukuran="36" class="bk-ok shrink-0" />
      <div class="min-w-0">
        <p class="text-[14px] font-extrabold text-cam-ink">Seluruh {{ n(props.luasBast, 2) }} ha telah diserahterimakan kepada pemangku kawasan</p>
        <p class="text-[12px] text-stone-600">{{ (props.penilaian ?? []).filter((p: any) => p.bast_nomor).map((p: any) => `BAST ${p.bast_nomor} (${tanggal(p.bast_at)})`).join(' · ') }} — kewajiban di lokasi ini selesai; pemantauan berikutnya menjadi tanggung jawab pengelola kawasan.</p>
      </div>
    </section>

    <!-- prasyarat penilaian -->
    <section v-else class="bk-layak" :class="k.layak ? 'bk-layak-ok' : 'bk-layak-belum'">
      <div class="shrink-0">
        <Cincin :nilai="Math.round(k.terpenuhi / Math.max(1, k.jumlah) * 100)" satuan="%" :ukuran="96" :warna="k.layak ? '#15803D' : '#D97706'" :label="`${k.terpenuhi} dari ${k.jumlah} prasyarat`" />
      </div>
      <div class="min-w-0 flex-1">
        <p class="text-[14px] font-extrabold text-cam-ink">{{ k.layak ? 'Seluruh prasyarat wajib terpenuhi — boleh diajukan penilaian' : 'Belum boleh diajukan penilaian' }}</p>
        <p v-if="!k.layak" class="text-[12px] bk-teks-kuning">Penghenti: {{ k.penghenti.join('; ') }}.</p>
        <ul class="grid gap-x-5 gap-y-1.5 md:grid-cols-2 mt-2">
          <li v-for="c in k.cek" :key="c.kode" class="flex gap-2 text-[12px]">
            <Ikon :nama="c.ok ? 'circle-check' : c.wajib ? 'circle-x' : 'info'" :ukuran="16" class="shrink-0 mt-px" :class="c.ok ? 'bk-ok' : c.wajib ? 'bk-gagal' : 'bk-info'" />
            <span class="min-w-0"><b class="text-cam-ink">{{ c.label }}</b><span class="block text-[11px] text-stone-500">{{ c.bukti }}</span></span>
          </li>
        </ul>
        <p v-if="galat.luas_diajukan_ha" class="mt-2 text-[12px] bk-gagal">{{ galat.luas_diajukan_ha }}</p>
      </div>
      <button v-if="['p2', 'siap_nilai'].includes(b.fase) || (b.fase === 'serah_terima' ? false : (props.penilaian ?? []).some((p: any) => p.status === 'tidak_berhasil'))"
              class="eq-btn-utama self-start" :disabled="!k.layak" @click="ajukan">Ajukan penilaian</button>
    </section>

    <section class="ka-kisi">
      <KartuAngka label="Batang rancangan" :nilai="b.target_batang" :desimal="0" satuan="batang" ikon="trees" warna="hijau" :catatan="`${n(b.luas_ha, 1)} ha × ${b.kerapatan} btg/ha`" />
      <KartuAngka label="Batang sehat per hektar" :nilai="k.sehat_ha" :desimal="0" satuan="btg/ha" ikon="sprout" :warna="(k.sehat_ha ?? 0) >= 500 ? 'hijau' : 'merah'" catatan="Ambang penilaian 500 btg/ha" />
      <KartuAngka label="Geotagging unik" :nilai="k.geotag_pct" :desimal="1" satuan="%" ikon="map-pin" :warna="k.geotag_pct >= 100 && !b.geotag_duplikat ? 'hijau' : 'amber'" :catatan="`${n(b.geotag_duplikat, 0)} duplikat`" />
      <KartuAngka label="Anggaran RKP" :nilai="b.anggaran_rp" satuan="Rp" ikon="hand-coins" warna="amber" :catatan="`Progres fisik ${n(b.fisik, 0)}%`" />
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
      <Kartu judul="Peta petak" :ket="`${(props.petak ?? []).length} petak · warna = persen tumbuh Petak Ukur terakhir`" ikon="map" warna="hijau">
        <PetaPoligon :fitur="props.fitur ?? []" mode="tumbuh" :tinggi="340" :label="false" />
      </Kartu>
      <div class="space-y-4">
        <KartuGrafik judul="Persen tumbuh & tinggi" catatan="Garis = persen tumbuh (%) · kolom = tinggi rata-rata (cm)" :angka="`${tren.length} pemantauan`">
          <Kolom :label="tren.map((t: any) => t.label)" :deret="[{ nama: 'Tinggi (cm)', nilai: tren.map((t: any) => t.tinggi), warna: '#86EFAC' }]"
                 :garis="{ nama: 'Persen tumbuh (%)', nilai: tren.map((t: any) => t.tumbuh), warna: '#14532D' }" satuan="cm" :tinggi="190" />
        </KartuGrafik>
        <Kartu judul="Komposisi jenis" :ket="`Lokal ${n(b.komposisi_cek?.lokal, 0)}% · cepat tumbuh ${n(b.komposisi_cek?.cepat, 0)}% · MPTS ${n(b.komposisi_cek?.mpts, 0)}%`" ikon="trees" warna="hijau">
          <ul class="space-y-1.5">
            <li v-for="j in b.komposisi" :key="j.jenis" class="flex items-center gap-2 text-[12px]">
              <i class="bk-titik" :style="{ background: KATEGORI_JENIS[jenisMap[j.jenis]?.kategori ?? 'lokal']?.warna }" />
              <span class="flex-1"><b class="text-cam-ink">{{ jenisMap[j.jenis]?.nama ?? j.jenis }}</b> <i class="text-stone-500">{{ jenisMap[j.jenis]?.latin }}</i></span>
              <span class="num font-bold">{{ n(j.persen, 0) }}%</span>
              <span class="num text-stone-500 w-24 text-right">{{ n(b.target_batang * j.persen / 100, 0) }} btg</span>
            </li>
          </ul>
        </Kartu>
      </div>
    </section>

    <Kartu judul="Petak tanam" ket="Petak Ukur IS 5% per petak · klik untuk mencatat pemantauan" ikon="layers" warna="amber" rapat>
      <template #aksi><button class="eq-btn-mini" @click="formPetak = !formPetak">{{ formPetak ? 'Tutup' : '+ Petak' }}</button></template>
      <form v-if="formPetak" class="px-5 pb-4 grid gap-2 md:grid-cols-6" @submit.prevent="simpanPetak">
        <label class="bk-l">Kode petak<input v-model="fPetak.kode" required maxlength="40" class="bk-i"></label>
        <label class="bk-l">Luas (ha)<input v-model.number="fPetak.luas_ha" type="number" step="0.01" min="0.01" required class="bk-i num"></label>
        <label class="bk-l">Kelerengan<select v-model="fPetak.kelerengan" class="bk-i"><option v-for="x in pil.kelerengan" :key="x.nilai" :value="x.nilai">{{ x.label }}</option></select></label>
        <label class="bk-l">Lat<input v-model.number="fPetak.lat" type="number" step="0.0000001" class="bk-i num"></label>
        <label class="bk-l">Lng<input v-model.number="fPetak.lng" type="number" step="0.0000001" class="bk-i num"></label>
        <label class="bk-l">Bibit tertanam<input v-model.number="fPetak.bibit_tanam" type="number" min="0" class="bk-i num"></label>
        <label class="bk-l md:col-span-6">Poligon (GeoJSON Polygon atau "lat, lng" per baris)<textarea v-model="fPetak.poligon" rows="3" class="bk-i font-mono text-[11px]" placeholder="-0.4521, 116.8123&#10;-0.4528, 116.8180&#10;-0.4575, 116.8172" /></label>
        <p v-if="Object.keys(fPetak.errors).length" class="md:col-span-6 text-[12px] bk-gagal">{{ Object.values(fPetak.errors)[0] }}</p>
        <div class="md:col-span-6"><button class="eq-btn-utama" :disabled="fPetak.processing">Simpan petak</button></div>
      </form>
      <div class="overflow-x-auto">
        <table class="w-full text-[12px]">
          <thead><tr class="bk-th"><th>Petak</th><th class="text-right">Luas</th><th>Lereng</th><th class="text-right">Tertanam</th><th class="text-right">Disulam</th><th class="text-right">Tumbuh</th><th class="text-right">Sehat/ha</th><th>Status</th><th>Dipantau</th><th /></tr></thead>
          <tbody class="divide-y divide-stone-100">
            <tr v-for="p in props.petak" :key="p.id" class="bk-tr">
              <td class="font-bold text-cam-ink">{{ p.kode }} <Ikon v-if="!p.berpeta" nama="triangle-alert" :ukuran="13" class="bk-gagal inline" /></td>
              <td class="text-right num">{{ n(p.luas_ha, 2) }} ha</td>
              <td>{{ pil.kelerengan?.find((x: any) => x.nilai === p.kelerengan)?.label ?? '—' }}</td>
              <td class="text-right num">{{ n(p.bibit_tanam, 0) }}</td>
              <td class="text-right num">{{ n(p.bibit_sulam, 0) }}</td>
              <td class="text-right num font-extrabold" :style="{ color: warnaTumbuh(p.persen_tumbuh) }">{{ p.persen_tumbuh === null ? '—' : n(p.persen_tumbuh, 1) + '%' }}</td>
              <td class="text-right num">{{ p.sehat_ha === null ? '—' : n(p.sehat_ha, 0) }}</td>
              <td><span class="pr-pil" :class="tumbuh(p.status_tumbuh).kelas">{{ tumbuh(p.status_tumbuh).pendek }}</span></td>
              <td class="whitespace-nowrap">{{ tanggal(p.dipantau_at) }}</td>
              <td class="text-right"><Link :href="`/rehab-das/petak/${p.id}/pantau`" class="eq-btn-mini">Petak Ukur</Link></td>
            </tr>
            <tr v-if="!(props.petak ?? []).length"><td colspan="10" class="px-5 py-6 text-center text-stone-400">Belum ada petak.</td></tr>
          </tbody>
        </table>
      </div>
    </Kartu>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]">
      <Kartu judul="Log kegiatan lapangan" ket="Dicatat kontraktor, diverifikasi petugas lingkungan — yang mencatat tidak bisa memverifikasi sendiri" ikon="shovel" warna="amber" rapat>
        <template #aksi><button class="eq-btn-mini" @click="formKeg = !formKeg">{{ formKeg ? 'Tutup' : '+ Kegiatan' }}</button></template>
        <form v-if="formKeg" class="px-5 pb-4 grid gap-2 md:grid-cols-4" @submit.prevent="simpanKeg">
          <label class="bk-l">Tanggal<input v-model="fKeg.tanggal" type="date" required class="bk-i"></label>
          <label class="bk-l md:col-span-2">Kegiatan<select v-model="fKeg.jenis" class="bk-i"><option v-for="x in pil.kegiatan" :key="x.nilai" :value="x.nilai">{{ x.fase }} · {{ x.label }}</option></select></label>
          <label class="bk-l">Petak<select v-model="fKeg.petak_id" class="bk-i"><option :value="null">Seluruh lokasi</option><option v-for="p in props.petak" :key="p.id" :value="p.id">{{ p.kode }}</option></select></label>
          <label class="bk-l">Luas (ha)<input v-model.number="fKeg.luas_ha" type="number" step="0.01" min="0" class="bk-i num"></label>
          <label class="bk-l">Bibit (batang)<input v-model.number="fKeg.bibit" type="number" min="0" class="bk-i num"></label>
          <label class="bk-l">Tenaga kerja (HOK)<input v-model.number="fKeg.hok" type="number" min="0" class="bk-i num"></label>
          <label class="bk-l">Biaya (Rp)<input v-model.number="fKeg.biaya_rp" type="number" min="0" step="10000" class="bk-i num"></label>
          <label class="bk-l md:col-span-4">Catatan<input v-model="fKeg.catatan" maxlength="2000" class="bk-i"></label>
          <p v-if="Object.keys(fKeg.errors).length" class="md:col-span-4 text-[12px] bk-gagal">{{ Object.values(fKeg.errors)[0] }}</p>
          <div class="md:col-span-4"><button class="eq-btn-utama" :disabled="fKeg.processing">Simpan kegiatan</button></div>
        </form>
        <p v-if="galat.aksi || galat.catatan" class="px-5 pb-2 text-[12px] bk-gagal">{{ galat.aksi || galat.catatan }}</p>
        <ul class="divide-y divide-stone-100 max-h-[460px] overflow-y-auto">
          <li v-for="kg in props.kegiatan" :key="kg.id" class="px-5 py-2.5 flex items-center gap-3 text-[12px]">
            <span class="bk-fase">{{ kg.fase }}</span>
            <div class="min-w-0 flex-1">
              <p class="font-bold text-cam-ink truncate">{{ kg.label }}<template v-if="kg.petak"> · {{ kg.petak }}</template></p>
              <p class="text-[11px] text-stone-500">{{ tanggal(kg.tanggal) }}<template v-if="kg.luas_ha"> · {{ n(kg.luas_ha, 1) }} ha</template><template v-if="kg.bibit"> · {{ n(kg.bibit, 0) }} bibit</template> · {{ kg.hok }} HOK · {{ rp(kg.biaya_rp) }}</p>
            </div>
            <span class="pr-pil" :class="statusKeg[kg.status]">{{ kg.status === 'diajukan' ? 'Menunggu' : kg.status === 'diverifikasi' ? 'Terverifikasi' : 'Ditolak' }}</span>
            <template v-if="kg.status === 'diajukan' && !kg.milik_saya">
              <button class="eq-btn-mini" @click="verifikasi(kg, 'terima')">Terima</button>
              <button class="eq-btn-mini" @click="verifikasi(kg, 'tolak')">Tolak</button>
            </template>
          </li>
          <li v-if="!(props.kegiatan ?? []).length" class="px-5 py-6 text-center text-stone-400">Belum ada kegiatan.</li>
        </ul>
      </Kartu>
      <div class="space-y-4">
        <Kartu judul="Rekap kegiatan terverifikasi" ikon="list-checks" warna="biru" rapat>
          <table class="w-full text-[12px]">
            <thead><tr class="bk-th"><th>Kegiatan</th><th class="text-right">Luas</th><th class="text-right">HOK</th><th class="text-right">Biaya</th></tr></thead>
            <tbody class="divide-y divide-stone-100">
              <tr v-for="r in props.rekapKegiatan" :key="r.jenis" class="bk-tr"><td>{{ r.label }}</td><td class="text-right num">{{ n(r.luas, 1) }}</td><td class="text-right num">{{ n(r.hok, 0) }}</td><td class="text-right num">{{ rp(r.biaya) }}</td></tr>
            </tbody>
          </table>
        </Kartu>
        <Kartu judul="Geotagging tanaman" ket="Self-declare penilaian: 100% tanaman tergeotag tanpa duplikasi" ikon="map-pin" warna="ungu">
          <form class="grid grid-cols-3 gap-2 items-end" @submit.prevent="simpanGeo">
            <label class="bk-l">Tergeotag<input v-model.number="fGeo.geotag_total" type="number" min="0" class="bk-i num"></label>
            <label class="bk-l">Duplikat<input v-model.number="fGeo.geotag_duplikat" type="number" min="0" class="bk-i num"></label>
            <button class="eq-btn-lain" :disabled="fGeo.processing">Simpan</button>
          </form>
          <p class="text-[11.5px] text-stone-500 mt-2">Target {{ n(b.target_batang, 0) }} batang. Duplikasi geotag menahan proses penilaian di BPDAS.</p>
        </Kartu>
        <Kartu judul="Riwayat penilaian" :ket="(props.penilaian ?? []).length ? 'Serah terima boleh bertahap' : 'Belum pernah diajukan'" ikon="clipboard-check" warna="hijau">
          <ol class="space-y-3">
            <li v-for="p in props.penilaian" :key="p.id">
              <div class="flex justify-between gap-2"><p class="text-[12.5px] font-bold text-cam-ink">{{ p.kode }} · tahap {{ p.tahap }} · {{ n(p.luas_diajukan_ha, 2) }} ha</p><span class="pr-pil" :class="penilaian(p.status).kelas">{{ penilaian(p.status).pendek }}</span></div>
              <div class="bk-tahap"><i v-for="t in tahapan" :key="t" :class="{ aktif: tahapan.indexOf(t) <= tahapan.indexOf(p.status === 'tidak_berhasil' || p.status === 'ditunda' ? 'dinilai' : p.status) }" /></div>
              <p class="text-[11px] text-stone-500">Diajukan {{ tanggal(p.diajukan_at) }}<template v-if="p.sk_tim_nomor"> · SK tim {{ p.sk_tim_nomor }}</template><template v-if="p.persen_tumbuh"> · tumbuh {{ n(p.persen_tumbuh, 1) }}%</template><template v-if="p.bast_nomor"> · BAST {{ p.bast_nomor }}</template></p>
            </li>
          </ol>
        </Kartu>
      </div>
    </section>

    <Kartu judul="Riwayat Petak Ukur" :ket="`${(props.pantau ?? []).length} pengukuran terakhir`" ikon="scan-line" warna="hijau" rapat>
      <div class="overflow-x-auto">
        <table class="w-full text-[12px]">
          <thead><tr class="bk-th"><th>Kode</th><th>Tanggal</th><th>Petak</th><th>Periode</th><th class="text-right">PU</th><th class="text-right">Diamati</th><th class="text-right">Sehat / merana / mati</th><th class="text-right">Tumbuh</th><th class="text-right">Sehat/ha</th><th class="text-right">Tinggi</th><th>Status</th><th class="text-right">Sulam</th></tr></thead>
          <tbody class="divide-y divide-stone-100">
            <tr v-for="p in props.pantau" :key="p.id" class="bk-tr">
              <td class="font-bold">{{ p.kode }}</td><td class="whitespace-nowrap">{{ tanggal(p.tanggal) }}</td><td>{{ p.petak }}</td>
              <td>{{ pil.periode?.find((x: any) => x.nilai === p.periode)?.label }}</td><td class="text-right num">{{ p.jumlah_pu }}</td><td class="text-right num">{{ n(p.diamati, 0) }}</td>
              <td class="text-right num">{{ p.sehat }} / {{ p.merana }} / {{ p.mati }}</td>
              <td class="text-right num font-extrabold" :style="{ color: warnaTumbuh(p.persen_tumbuh) }">{{ n(p.persen_tumbuh, 1) }}%</td>
              <td class="text-right num">{{ n(p.sehat_ha, 0) }}</td><td class="text-right num">{{ p.tinggi_cm ? n(p.tinggi_cm, 0) + ' cm' : '—' }}</td>
              <td><span class="pr-pil" :class="tumbuh(p.status).kelas">{{ tumbuh(p.status).pendek }}</span></td>
              <td class="text-right num">{{ p.kebutuhan_sulam ? n(p.kebutuhan_sulam, 0) : '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </Kartu>

    <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
  </div>
</template>

<style scoped>
.bk-kepala { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12px; align-items: flex-start; }
.bk-layak { display: flex; flex-wrap: wrap; gap: 18px; padding: 16px 18px; border-radius: 18px; border: 1px solid #E7E5E4; border-left-width: 6px; background: #fff; }
.bk-layak-ok { border-left-color: #15803D; background: #F0FDF4; }
.bk-layak-belum { border-left-color: #D97706; background: #FFFBEB; }
.bk-ok { color: #15803D; } .bk-gagal { color: #DC2626; } .bk-info { color: #A8A29E; }
.bk-teks-kuning { color: #B45309; font-weight: 700; }
.bk-l { display: block; font-size: 11px; font-weight: 600; color: #78716C; }
.bk-i { display: block; width: 100%; margin-top: 4px; border-radius: 10px; border-color: #E7E5E4; font-size: 12.5px; }
.bk-th th { text-align: left; font-size: 11px; font-weight: 700; color: #78716C; padding: 8px 12px; background: #FAFAF9; white-space: nowrap; }
.bk-th th:first-child, .bk-tr td:first-child { padding-left: 20px; }
.bk-tr td { padding: 8px 12px; }
.bk-titik { width: 10px; height: 10px; border-radius: 3px; flex: none; }
.bk-fase { width: 30px; height: 30px; border-radius: 9px; display: grid; place-items: center; background: #F0FDF4; color: #166534; font-size: 10.5px; font-weight: 900; flex: none; }
.bk-tahap { display: grid; grid-template-columns: repeat(5, 1fr); gap: 3px; margin: 5px 0 3px; }
.bk-tahap i { height: 5px; border-radius: 3px; background: #E7E5E4; }
.bk-tahap i.aktif { background: #15803D; }
:root[data-tema="gelap"] .bk-layak { background: #1B2124; border-color: #2E373C; }
:root[data-tema="gelap"] .bk-layak-ok { background: #12271D; border-left-color: #22C55E; }
:root[data-tema="gelap"] .bk-layak-belum { background: #2A2414; border-left-color: #D97706; }
:root[data-tema="gelap"] .bk-ok { color: #8FE3BE; } :root[data-tema="gelap"] .bk-gagal { color: #F5A9A9; }
:root[data-tema="gelap"] .bk-teks-kuning { color: #F6CF7A; }
:root[data-tema="gelap"] .bk-l { color: #93A1A8; }
:root[data-tema="gelap"] .bk-th th { background: #20272B; color: #93A1A8; }
:root[data-tema="gelap"] .bk-fase { background: #12271D; color: #8FE3BE; }
:root[data-tema="gelap"] .bk-tahap i { background: #2E373C; }
:root[data-tema="gelap"] .bk-tahap i.aktif { background: #22C55E; }
</style>

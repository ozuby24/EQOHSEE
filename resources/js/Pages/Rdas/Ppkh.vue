<script setup lang="ts">
/*
  Rincian satu PPKH: corong kewajiban, lokasi-lokasinya, laporan
  semesteran, kejadian & tangga sanksi, dan status reklamasi hutan
  (kewajiban saudara yang dinilai direktorat yang sama).
*/
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import { useDialog } from '../../dialog';
import Dialog from '../../Components/Dialog.vue';
import Kartu from '../../Components/Energi/Kartu.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import PetaPoligon from '../../Components/Rdas/PetaPoligon.vue';
import { n } from '../../energi';
import { fase, warnaTumbuh, LAPORAN, KEJADIAN_STATUS, WAKTU, sisa, tanggal } from '../../rdas';

const props = propHalaman();
const { dialog, minta, batal, lanjut } = useDialog();
const p = computed(() => props.ppkh ?? {});
const pil = computed(() => props.pilihan ?? {});
const galat = computed(() => (props.errors ?? {}) as Record<string, string>);

async function kirim(l: any) {
  const t = await minta({ judul: `Catat laporan semester ${l.semester} ${l.tahun} terkirim`, label: 'Tanggal dikirim ke BPDAS', jenis: 'tanggal', wajib: true, labelAksi: 'Lanjut' });
  if (t === null) return;
  const tt = await minta({ judul: 'Nomor tanda terima', label: 'Nomor tanda terima BPDAS (boleh kosong)', labelAksi: 'Simpan' });
  if (tt === null) return;
  router.post(`/rehab-das/laporan/${l.id}`, { dikirim_at: t, tanda_terima: tt || null }, { preserveScroll: true });
}

const fKejadian = useForm({ jenis: 'kebakaran', tanggal: new Date().toISOString().slice(0, 10), blok_id: null as number | null, luas_terdampak_ha: 0, uraian: '', strategi: '', ba_nomor: '', dilaporkan_at: '' });
const formKejadian = ref(false);
function simpanKejadian() { fKejadian.post(`/rehab-das/kewajiban/${p.value.id}/kejadian`, { preserveScroll: true, onSuccess: () => { formKejadian.value = false; fKejadian.reset(); } }); }
async function tutupKejadian(k: any) {
  const strategi = k.jenis === 'kebakaran' && !k.strategi ? await minta({ judul: 'Exit strategy kebakaran', label: 'Ketik: replanting, relokasi, atau parsial', wajib: true }) : k.strategi;
  if (strategi === null) return;
  const lapor = k.jenis === 'kebakaran' && !k.dilaporkan_at ? await minta({ judul: 'Tanggal dilaporkan ke BPDAS', label: 'Tanggal laporan', jenis: 'tanggal', wajib: true }) : k.dilaporkan_at;
  if (lapor === null) return;
  router.post(`/rehab-das/kejadian/${k.id}`, { status: 'selesai', strategi: strategi || null, dilaporkan_at: lapor || null }, { preserveScroll: true });
}

const fRek = useForm({ rurh_status: p.value.rurh_status, luas_terganggu_ha: p.value.luas_terganggu_ha, luas_direklamasi_ha: p.value.luas_direklamasi_ha, nilai_reklamasi: p.value.nilai_reklamasi });
function simpanRek() { fRek.post(`/rehab-das/kewajiban/${p.value.id}/reklamasi`, { preserveScroll: true }); }

const tangga = [
  { t: 1, label: 'Teguran tertulis Kepala BPDAS', ket: 'Maksimal 3× berturut-turut, jarak 30 hari kerja' },
  { t: 2, label: 'Rekomendasi pembatalan lokasi', ket: 'Kepada Dirjen PDASRH' },
  { t: 3, label: 'Rekomendasi pencabutan PPKH', ket: 'Kepada Dirjen Planologi Kehutanan' },
  { t: 4, label: 'Rekomendasi penindakan hukum', ket: 'Kepada Dirjen Penegakan Hukum Kehutanan' },
];
const corong = computed(() => [
  ['Kewajiban', p.value.kewajiban, 100, '#5C4326'], ['Ditetapkan', p.value.ditetapkan, p.value.ditetapkan_pct, '#7C6A3A'],
  ['Ditanam', p.value.ditanam, p.value.ditanam_pct, '#3F9A4E'], ['BAST', p.value.bast, p.value.bast_pct, '#15803D'],
]);
</script>

<template>
  <Head :title="`${p.kode} · Kewajiban Rehab DAS`" />
  <div class="space-y-4">
    <section class="pk-kepala">
      <div class="min-w-0">
        <p class="text-[11.5px] font-bold text-stone-500">{{ p.kode }} · {{ p.bpdas }} · {{ p.kabupaten }}, {{ p.provinsi }}</p>
        <h1 class="text-[19px] font-extrabold text-cam-ink leading-tight">{{ p.nomor_sk }}</h1>
        <p class="text-[12px] text-stone-500 mt-1">{{ p.kegiatan }} · SK {{ tanggal(p.tanggal_sk) }} · {{ p.jangka_tahun }} tahun (s.d. {{ tanggal(p.berlaku_sampai) }}) · luas PPKH {{ n(p.luas_ppkh_ha, 2) }} ha · PAK {{ n(p.luas_pak_ha, 2) }} ha × {{ n(p.rasio, 1) }}</p>
        <div class="flex flex-wrap gap-1.5 mt-2">
          <span class="pr-pil" :class="WAKTU[p.waktu]?.kelas">Batas penyelesaian {{ tanggal(p.batas_selesai) }} · {{ sisa(p.sisa_hari) }}</span>
          <span v-if="p.sanksi_tahap" class="pr-pil pr-merah">Sanksi tahap {{ p.sanksi_tahap }}</span>
        </div>
      </div>
      <Link :href="`/rehab-das/blok/baru?ppkh=${p.id}`" class="eq-btn-utama inline-flex items-center gap-2"><Ikon nama="map-pin" :ukuran="15" />Tambah lokasi</Link>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.35fr)]">
      <Kartu judul="Corong kewajiban" :ket="`${n(p.belum_ditetapkan, 1)} ha belum ditetapkan lokasinya`" ikon="trees" warna="hijau">
        <ul class="space-y-3">
          <li v-for="c in corong" :key="c[0]">
            <div class="flex justify-between text-[12.5px]"><span class="font-bold text-cam-ink">{{ c[0] }}</span><span class="num font-extrabold">{{ n(c[1], 1) }} ha <span class="text-stone-500">{{ c[0] !== 'Kewajiban' ? n(c[2], 0) + '%' : '' }}</span></span></div>
            <div class="pk-batang"><i :style="{ width: Math.max(1.5, Math.min(100, Number(c[2]) || 0)) + '%', background: String(c[3]) }" /></div>
          </li>
        </ul>
        <p class="text-[11.5px] text-stone-500 mt-3">Berakhirnya PPKH tidak membebaskan pemegang dari utang kewajiban Rehab DAS.</p>
      </Kartu>
      <Kartu judul="Peta lokasi" :ket="`${(props.blok ?? []).length} lokasi`" ikon="map" warna="hijau">
        <PetaPoligon :fitur="props.fitur ?? []" mode="fase" :tinggi="300" @pilih="(id: number) => router.visit(`/rehab-das/blok/${id}`)" />
      </Kartu>
    </section>

    <Kartu judul="Lokasi Rehab DAS" :ket="`${(props.blok ?? []).length} lokasi ditetapkan`" ikon="map-pin" warna="amber" rapat>
      <div class="overflow-x-auto">
        <table class="w-full text-[12px]">
          <thead><tr class="pk-th"><th>Lokasi</th><th>DAS · fungsi</th><th class="text-right">Luas</th><th>Tanam P0</th><th>Fase</th><th class="text-right">Tumbuh</th><th class="text-right">BAST</th><th>Prasyarat</th></tr></thead>
          <tbody class="divide-y divide-stone-100">
            <tr v-for="b in props.blok" :key="b.id" class="pk-tr" @click="router.visit(`/rehab-das/blok/${b.id}`)">
              <td><p class="font-bold text-cam-ink">{{ b.kode }} · {{ b.nama }}</p><p class="text-[10.5px] text-stone-500">{{ b.kontraktor }}</p></td>
              <td>{{ b.das }}{{ b.sub_das ? ' / ' + b.sub_das : '' }} · {{ b.fungsi_kawasan }}</td>
              <td class="text-right num font-bold">{{ n(b.luas_ha, 1) }} ha</td>
              <td class="whitespace-nowrap">{{ tanggal(b.tanggal_tanam) }}<p v-if="b.usia_bulan !== null" class="text-[10.5px] text-stone-500">{{ b.usia_bulan }} bulan</p></td>
              <td><span class="pr-pil" :class="fase(b.fase).kelas">{{ fase(b.fase).pendek }}</span></td>
              <td class="text-right num font-bold" :style="{ color: warnaTumbuh(b.persen_tumbuh) }">{{ b.persen_tumbuh === null ? '—' : n(b.persen_tumbuh, 1) + '%' }}</td>
              <td class="text-right num">{{ n(b.luas_bast, 1) }} ha</td>
              <td><span v-if="['p2', 'siap_nilai'].includes(b.fase)" class="pr-pil" :class="b.layak ? 'pr-hijau' : 'pr-kuning'">{{ b.layak ? 'Lengkap' : 'Belum lengkap' }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </Kartu>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
      <Kartu judul="Laporan semesteran" ket="Semester I paling lambat 31 Juli · semester II 31 Januari tahun berikutnya" ikon="file-text" warna="biru" rapat>
        <ul class="divide-y divide-stone-100">
          <li v-for="l in props.laporan" :key="l.id" class="px-5 py-2.5 flex items-center gap-3 text-[12px]">
            <div class="min-w-0 flex-1"><p class="font-bold text-cam-ink">Semester {{ l.semester }} · {{ l.tahun }}</p>
              <p class="text-[11px] text-stone-500">Batas {{ tanggal(l.batas) }}<template v-if="l.dikirim_at"> · dikirim {{ tanggal(l.dikirim_at) }}</template><template v-if="l.tanda_terima"> · {{ l.tanda_terima }}</template></p></div>
            <span class="pr-pil" :class="LAPORAN[l.keadaan]?.kelas">{{ LAPORAN[l.keadaan]?.pendek }}</span>
            <button v-if="!l.dikirim_at" class="eq-btn-mini" @click="kirim(l)">Catat terkirim</button>
          </li>
        </ul>
      </Kartu>
      <Kartu judul="Kejadian & sanksi" ket="Kebakaran tidak menggugurkan kewajiban — pilih exit strategy" ikon="flame" warna="merah" rapat>
        <template #aksi><button class="eq-btn-mini" @click="formKejadian = !formKejadian">{{ formKejadian ? 'Tutup' : 'Catat kejadian' }}</button></template>
        <form v-if="formKejadian" class="px-5 pb-4 grid gap-2 md:grid-cols-3" @submit.prevent="simpanKejadian">
          <label class="pk-l">Jenis<select v-model="fKejadian.jenis" class="pk-i"><option v-for="j in pil.kejadian" :key="j.nilai" :value="j.nilai">{{ j.label }}</option></select></label>
          <label class="pk-l">Tanggal<input v-model="fKejadian.tanggal" type="date" required class="pk-i"></label>
          <label class="pk-l">Lokasi<select v-model="fKejadian.blok_id" class="pk-i"><option :value="null">Seluruh PPKH</option><option v-for="b in props.blok" :key="b.id" :value="b.id">{{ b.kode }}</option></select></label>
          <label class="pk-l">Luas terdampak (ha)<input v-model.number="fKejadian.luas_terdampak_ha" type="number" step="0.1" min="0" class="pk-i num"></label>
          <label class="pk-l">Exit strategy<select v-model="fKejadian.strategi" class="pk-i"><option value="">— belum —</option><option v-for="s in pil.strategi" :key="s.nilai" :value="s.nilai">{{ s.label }}</option></select></label>
          <label class="pk-l">Dilaporkan ke BPDAS<input v-model="fKejadian.dilaporkan_at" type="date" class="pk-i"></label>
          <label class="pk-l md:col-span-3">Uraian<textarea v-model="fKejadian.uraian" rows="2" required maxlength="2000" class="pk-i" /></label>
          <p v-if="Object.keys(fKejadian.errors).length" class="md:col-span-3 text-[12px] pk-galat">{{ Object.values(fKejadian.errors)[0] }}</p>
          <div class="md:col-span-3"><button class="eq-btn-utama" :disabled="fKejadian.processing">Simpan kejadian</button></div>
        </form>
        <p v-if="galat.status" class="px-5 pb-2 text-[12px] pk-galat">{{ galat.status }}</p>
        <ul class="divide-y divide-stone-100">
          <li v-for="k in props.kejadian" :key="k.id" class="px-5 py-3 flex items-start gap-3">
            <span class="pk-tanda" :class="k.tahap ? 'pk-sanksi' : 'pk-gangguan'"><Ikon :nama="pil.kejadian?.find((x: any) => x.nilai === k.jenis)?.ikon ?? 'triangle-alert'" :ukuran="15" /></span>
            <div class="min-w-0 flex-1">
              <p class="text-[12.5px] font-bold text-cam-ink">{{ k.label }}<template v-if="k.blok"> · {{ k.blok }}</template><template v-if="k.luas_terdampak_ha"> · {{ n(k.luas_terdampak_ha, 1) }} ha</template></p>
              <p class="text-[11.5px] text-stone-500">{{ tanggal(k.tanggal) }} · {{ k.uraian }}</p>
              <p v-if="!k.tahap" class="text-[11px] text-stone-500">{{ k.dilaporkan_at ? 'Dilaporkan ' + tanggal(k.dilaporkan_at) : 'Belum dilaporkan ke BPDAS' }}<template v-if="k.strategi"> · {{ pil.strategi?.find((s: any) => s.nilai === k.strategi)?.label }}</template><template v-if="k.ba_nomor"> · BA {{ k.ba_nomor }}</template></p>
            </div>
            <span class="pr-pil" :class="KEJADIAN_STATUS[k.status]?.kelas">{{ KEJADIAN_STATUS[k.status]?.pendek }}</span>
            <button v-if="k.status !== 'selesai' && !k.tahap" class="eq-btn-mini" @click="tutupKejadian(k)">Tutup</button>
          </li>
          <li v-if="!(props.kejadian ?? []).length" class="px-5 py-6 text-center text-[12px] text-stone-400">Tidak ada kejadian tercatat.</li>
        </ul>
        <div class="px-5 py-4 pk-tangga">
          <p class="text-[11px] font-extrabold text-stone-500 mb-2">TANGGA SANKSI ATAS KETIDAKPATUHAN</p>
          <ol class="grid gap-1.5 md:grid-cols-4">
            <li v-for="s in tangga" :key="s.t" class="pk-anak" :class="{ aktif: p.sanksi_tahap >= s.t }"><b>Tahap {{ s.t }}</b><span>{{ s.label }}</span><small>{{ s.ket }}</small></li>
          </ol>
        </div>
      </Kartu>
    </section>

    <Kartu judul="Reklamasi hutan" ket="RURH paling lambat 30 hari sejak PAK · nilai ≥ 80 baik (berhasil), 60–79 sedang, < 60 buruk" ikon="mountain" warna="toska">
      <form class="grid gap-3 md:grid-cols-5 items-end" @submit.prevent="simpanRek">
        <label class="pk-l">Status RURH<select v-model="fRek.rurh_status" class="pk-i"><option v-for="x in pil.rurh" :key="x.nilai" :value="x.nilai">{{ x.label }}</option></select></label>
        <label class="pk-l">Areal terganggu (ha)<input v-model.number="fRek.luas_terganggu_ha" type="number" step="0.01" min="0" class="pk-i num"></label>
        <label class="pk-l">Areal direklamasi (ha)<input v-model.number="fRek.luas_direklamasi_ha" type="number" step="0.01" min="0" class="pk-i num"></label>
        <label class="pk-l">Nilai penilaian (0–100)<input v-model.number="fRek.nilai_reklamasi" type="number" step="0.1" min="0" max="100" class="pk-i num"></label>
        <button class="eq-btn-lain" :disabled="fRek.processing">Simpan</button>
      </form>
      <p class="text-[11.5px] text-stone-500 mt-2">Bobot penilaian: penataan lahan 20 · pengendalian erosi, sedimentasi & pencemaran air 30 · revegetasi 50 (625 btg/ha, lokal ≥ 40%).
        <template v-if="p.luas_terganggu_ha"> Direklamasi {{ n(p.luas_direklamasi_ha / Math.max(1, p.luas_terganggu_ha) * 100, 0) }}% dari areal terganggu.</template></p>
    </Kartu>

    <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
  </div>
</template>

<style scoped>
.pk-kepala { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12px; align-items: flex-start; }
.pk-batang { height: 11px; border-radius: 999px; background: #F0EFED; overflow: hidden; margin-top: 5px; }
.pk-batang i { display: block; height: 100%; border-radius: 999px; }
.pk-th th { text-align: left; font-size: 11px; font-weight: 700; color: #78716C; padding: 8px 12px; background: #FAFAF9; }
.pk-th th:first-child, .pk-tr td:first-child { padding-left: 20px; }
.pk-tr { cursor: pointer; } .pk-tr:hover { background: #F7FBF4; } .pk-tr td { padding: 9px 12px; }
.pk-l { display: block; font-size: 11px; font-weight: 600; color: #78716C; }
.pk-i { display: block; width: 100%; margin-top: 4px; border-radius: 10px; border-color: #E7E5E4; font-size: 12.5px; }
.pk-galat { color: #B91C1C; }
.pk-tanda { width: 30px; height: 30px; border-radius: 10px; display: grid; place-items: center; flex: none; }
.pk-gangguan { background: #FFEDD5; color: #C2410C; } .pk-sanksi { background: #FEE2E2; color: #B91C1C; }
.pk-tangga { border-top: 1px solid #F0EFED; }
.pk-anak { border-radius: 12px; padding: 8px 10px; background: #FAFAF9; border: 1px solid #F0EFED; display: grid; gap: 1px; font-size: 11px; color: #57534E; }
.pk-anak b { font-size: 11px; font-weight: 900; } .pk-anak small { font-size: 10px; color: #A8A29E; }
.pk-anak.aktif { background: #FEE2E2; border-color: #FCA5A5; color: #991B1B; }
:root[data-tema="gelap"] .pk-batang { background: #2E373C; }
:root[data-tema="gelap"] .pk-th th { background: #20272B; color: #93A1A8; }
:root[data-tema="gelap"] .pk-tr:hover { background: #20272B; }
:root[data-tema="gelap"] .pk-l { color: #93A1A8; }
:root[data-tema="gelap"] .pk-galat { color: #F5A9A9; }
:root[data-tema="gelap"] .pk-gangguan { background: #3D2410; color: #FDBA74; }
:root[data-tema="gelap"] .pk-sanksi { background: #4E1D1D; color: #F5A9A9; }
:root[data-tema="gelap"] .pk-tangga { border-color: #2E373C; }
:root[data-tema="gelap"] .pk-anak { background: #20272B; border-color: #2E373C; color: #B4BEC3; }
:root[data-tema="gelap"] .pk-anak.aktif { background: #4E1D1D; border-color: #7F1D1D; color: #F5A9A9; }
</style>

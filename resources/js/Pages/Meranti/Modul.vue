<script setup lang="ts">
/*
  Daftar modul MERANTI — satu halaman untuk dua puluh lima modul.

  Kolom, tipe, pilihan, dan kolom wajibnya dibaca dari katalog server
  (App\Support\Meranti\Katalog), seperti tabel dan formulir generik
  MERANTI. Cari, saring tahun/bulan, urutkan kolom, halaman 25 baris,
  total kolom angka, tambah/ubah lewat panel, hapus dengan konfirmasi,
  ekspor CSV, dan cetak.
*/
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import PilihPerusahaan from '../../Components/Energi/PilihPerusahaan.vue';
import Dialog from '../../Components/Dialog.vue';
import Lencana from '../../Components/Meranti/Lencana.vue';
import { useDialog } from '../../dialog';
import { n } from '../../energi';
import { BULAN, kategoriSkor, matrik17 } from '../../meranti';

type Kolom = {
  k: string; l: string; t: 'teks' | 'panjang' | 'angka' | 'tanggal' | 'pilih' | 'tautan' | 'hitung'; wajib: boolean; opsi: string[] | null;
  des: number | null; satuan: string | null; lencana: boolean; petunjuk: string | null; saran: string | null; daftar: boolean; panjang: number | null;
};
type Baris = Record<string, any> & { id: number };

const props = propHalaman();
const m = computed(() => props.modul);
const izin = computed(() => props.izin ?? {});
const kolom = computed<Kolom[]>(() => m.value.kolom);
const kolomDaftar = computed(() => kolom.value.filter(k => k.daftar));
const kolomIsian = computed(() => kolom.value.filter(k => k.t !== 'hitung'));
const semua = computed<Baris[]>(() => props.baris ?? []);
const { dialog, tanya, minta, batal, lanjut } = useDialog();

/* ── navigasi kelompok ── */
const tab = computed(() => {
  const t = [...(props.nav?.[m.value.grup] ?? [])];
  if (m.value.grup === 'Rehabilitasi DAS') t.splice(1, 0, { kunci: '_capaian', judul: 'Aktual Pencapaian', slug: 'aktual-pencapaian', ikon: 'chart-column' });
  if (m.value.kunci === 'peta_spasial') t.push({ kunci: '_peta', judul: 'Peta', slug: 'peta', ikon: 'map' });
  return t;
});

/* ── cari, saring, urut, halaman ── */
const cari = ref('');
// Kuncinya diisi sejak awal: kotak pilihan yang v-model-nya undefined tidak memilih opsi "Semua" (nilai '') dan tampil kosong.
const saring = reactive<Record<string, string>>(Object.fromEntries((props.modul?.filter ?? []).map((k: string) => [k, ''])));
const urut = ref<{ k: string; arah: 1 | -1 } | null>(null);
const hlm = ref(1);
const PER = 25;

const opsiSaring = computed(() => (m.value.filter ?? []).map((k: string) => {
  const nilai = [...new Set(semua.value.map(b => b[k]).filter(v => v !== null && v !== '').map(String))];
  nilai.sort(k === 'bulan' ? (a, b) => BULAN.indexOf(a) - BULAN.indexOf(b) : (a, b) => b.localeCompare(a, 'id', { numeric: true }));
  return { k, label: kolom.value.find(c => c.k === k)?.l ?? k, nilai };
}));

const tersaring = computed(() => {
  const q = cari.value.trim().toLowerCase();
  let r = semua.value.filter(b => Object.entries(saring).every(([k, v]) => !v || String(b[k] ?? '') === v));
  if (q) r = r.filter(b => kolomDaftar.value.some(k => String(b[k.k] ?? '').toLowerCase().includes(q)));
  if (urut.value) {
    const { k, arah } = urut.value;
    const kol = kolom.value.find(c => c.k === k);
    r = [...r].sort((a, b) => {
      const x = a[k], y = b[k];
      if (x === null || x === '' || x === undefined) return 1;
      if (y === null || y === '' || y === undefined) return -1;
      if (k === 'bulan') return (BULAN.indexOf(x) - BULAN.indexOf(y)) * arah;
      return (kol?.t === 'angka' || kol?.t === 'hitung' && typeof x === 'number' ? (Number(x) - Number(y)) : String(x).localeCompare(String(y), 'id', { numeric: true })) * arah;
    });
  }
  return r;
});
const jumlahHlm = computed(() => Math.max(1, Math.ceil(tersaring.value.length / PER)));
const barisHlm = computed(() => tersaring.value.slice((hlm.value - 1) * PER, hlm.value * PER));
watch([cari, () => ({ ...saring })], () => { hlm.value = 1; });

function aturUrut(k: string) {
  urut.value = urut.value?.k === k ? (urut.value.arah === 1 ? { k, arah: -1 } : null) : { k, arah: 1 };
}
function bersihkan() { cari.value = ''; Object.keys(saring).forEach(k => { saring[k] = ''; }); }

const total = computed(() => Object.fromEntries((m.value.jumlah ?? []).map((k: string) => [k, tersaring.value.reduce((a, b) => a + (Number(b[k]) || 0), 0)])));

/* ── tampilan sel ── */
function angka(k: Kolom, v: unknown): string {
  if (v === null || v === undefined || v === '') return '—';
  if (k.satuan === 'Rp') return 'Rp ' + n(v, 0);
  return n(v, k.des ?? 2) + (k.satuan && k.satuan !== 'Rp' ? ' ' + k.satuan : '');
}
const polos = (k: Kolom) => ['tahun', 'year'].includes(k.k);
function tanggal(v: unknown): string {
  if (!v) return '—';
  const d = new Date(String(v) + 'T00:00:00');
  return Number.isNaN(d.getTime()) ? String(v) : d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

/* ── formulir ── */
const panel = ref(false);
const edit = ref<number | null>(null);
const isian = reactive<Record<string, any>>({});
const galat = ref<Record<string, string>>({});
const sibuk = ref(false);

function kosong(): Record<string, any> {
  const d: Record<string, any> = {};
  const kini = new Date();
  for (const k of kolomIsian.value) d[k.k] = '';
  if ('tanggal' in d) d.tanggal = kini.toISOString().slice(0, 10);
  if ('tahun' in d) d.tahun = kini.getFullYear();
  if ('bulan' in d && m.value.kunci === 'progres_mingguan') d.bulan = BULAN[kini.getMonth()];
  if ('minggu' in d) d.minggu = 'Minggu ' + Math.min(5, Math.ceil(kini.getDate() / 7));
  return d;
}
function buka(b?: Baris) {
  edit.value = b ? b.id : null;
  Object.keys(isian).forEach(k => delete isian[k]);
  Object.assign(isian, b ? Object.fromEntries(kolomIsian.value.map(k => [k.k, b[k.k] ?? ''])) : kosong());
  galat.value = {};
  panel.value = true;
}
function tutup() { panel.value = false; edit.value = null; }

const pratinjauSkor = computed(() => m.value.hitung === 'matrik17'
  ? matrik17(isian.nilai_penatagunaan, isian.nilai_revegetasi, isian.nilai_penyelesaian) : null);

function simpan() {
  sibuk.value = true;
  const url = edit.value ? `/meranti/data/${m.value.kunci}/${edit.value}` : `/meranti/data/${m.value.kunci}`;
  const data = Object.fromEntries(Object.entries(isian).map(([k, v]) => [k, v === '' ? null : v]));
  const opsi = {
    preserveScroll: true,
    onSuccess: () => tutup(),
    onError: (e: Record<string, string>) => { galat.value = e; },
    onFinish: () => { sibuk.value = false; },
  };
  if (edit.value) router.put(url, data, opsi); else router.post(url, data, opsi);
}

async function hapus(b: Baris) {
  const nama = String(b[kolom.value[0].k] ?? '#' + b.id);
  const ya = await tanya({ judul: `Hapus ${nama}?`, pesan: `Baris ini dihapus dari ${m.value.judul} dan tercatat di Log Aktivitas.`, labelAksi: 'Hapus', nada: 'bahaya' });
  if (ya) router.delete(`/meranti/data/${m.value.kunci}/${b.id}`, { preserveScroll: true });
}

/* ── ekspor, cetak, folder Drive ── */
const kueriPerusahaan = computed(() => (props.perusahaan ? `?perusahaan=${props.perusahaan}` : ''));
function cetak() { window.print(); }
async function aturFolder() {
  const url = await minta({ judul: 'Folder Google Drive arsip capaian', label: 'Tautan folder (https://drive.google.com/…)', nilai: props.setelan?.folder_drive ?? '', wajib: false });
  if (url !== null) router.put('/meranti/setelan', { folder_drive: url || null }, { preserveScroll: true });
}
</script>

<template>
  <Head :title="m.judul" />
  <div class="space-y-4">
    <section class="flex flex-wrap items-center justify-between gap-3 mr-tanpa-cetak">
      <nav v-if="tab.length > 1" class="mr-tab" :aria-label="`Modul ${m.grup}`">
        <Link v-for="t in tab" :key="t.kunci" :href="`/meranti/${t.slug}`" :class="{ aktif: t.kunci === m.kunci }"><Ikon :nama="t.ikon" :ukuran="14" />{{ t.judul }}</Link>
      </nav>
      <span v-else />
      <PilihPerusahaan :daftar="props.daftarPerusahaan" :terpilih="props.perusahaan" />
    </section>

    <Kartu :judul="`${m.grup} · ${m.judul}`" :ket="`${tersaring.length} dari ${semua.length} baris${m.master ? ' · data master dikelola peran Data Master' : ''}`" :ikon="m.ikon" warna="hijau" rapat>
      <template #aksi>
        <div class="flex flex-wrap items-center gap-2 mr-tanpa-cetak">
          <template v-if="m.kunci === 'actual_capaian'">
            <a v-if="props.setelan?.folder_drive" :href="props.setelan.folder_drive" target="_blank" rel="noopener" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="globe" :ukuran="15" />Buka Folder Drive</a>
            <button v-if="izin.master" type="button" class="eq-btn-lain" @click="aturFolder">{{ props.setelan?.folder_drive ? 'Ubah folder' : 'Atur folder Drive' }}</button>
          </template>
          <Link v-if="m.kunci === 'peta_spasial'" href="/meranti/peta" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="map" :ukuran="15" />Lihat peta</Link>
          <a :href="`/meranti/data/${m.kunci}/csv${kueriPerusahaan}`" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="file-spreadsheet" :ukuran="15" />CSV</a>
          <button type="button" class="eq-btn-lain inline-flex items-center gap-2" @click="cetak"><Ikon nama="printer" :ukuran="15" />Cetak</button>
          <button v-if="izin.tambah" type="button" class="eq-btn-utama" @click="buka()">+ {{ m.tambah }}</button>
        </div>
      </template>

      <div class="px-5 pb-3 flex flex-wrap items-end gap-2 mr-tanpa-cetak">
        <label class="mr-label grow basis-[220px]">Cari
          <input v-model="cari" type="search" class="mr-isian" :placeholder="`Cari di ${m.judul.toLowerCase()}…`">
        </label>
        <label v-for="s in opsiSaring" :key="s.k" class="mr-label min-w-[140px]">{{ s.label }}
          <select v-model="saring[s.k]" class="mr-isian">
            <option value="">Semua</option>
            <option v-for="v in s.nilai" :key="v" :value="v">{{ v }}</option>
          </select>
        </label>
        <button v-if="cari || Object.values(saring).some(Boolean)" type="button" class="eq-btn-mini" @click="bersihkan">Hapus saringan</button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-[12.5px] mr-tabel">
          <thead>
            <tr>
              <th class="w-10">#</th>
              <th v-for="k in kolomDaftar" :key="k.k" :class="{ 'text-right': k.t === 'angka' || (k.t === 'hitung' && k.des !== null) }">
                <button type="button" class="mr-urut" @click="aturUrut(k.k)">{{ k.l }}<span v-if="k.satuan && k.satuan !== 'Rp'" class="font-normal"> ({{ k.satuan }})</span>
                  <span class="mr-panah">{{ urut?.k === k.k ? (urut.arah === 1 ? '▲' : '▼') : '' }}</span></button>
              </th>
              <th v-if="izin.ubah || izin.hapus" class="mr-tanpa-cetak" />
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100">
            <tr v-for="(b, i) in barisHlm" :key="b.id">
              <td class="text-stone-400 num" :title="b._impor ? 'Dimuat dari lembar Google Sheets' : (b._oleh ? 'Dicatat ' + b._oleh : '')">{{ (hlm - 1) * PER + i + 1 }}<span v-if="b._impor" class="mr-impor" aria-label="dari lembar Google Sheets">⇣</span></td>
              <td v-for="k in kolomDaftar" :key="k.k" :class="{ 'text-right num': k.t === 'angka' || (k.t === 'hitung' && k.des !== null) }">
                <Lencana v-if="k.lencana" :nilai="b[k.k]" />
                <a v-else-if="k.t === 'tautan' && b[k.k]" :href="b[k.k]" target="_blank" rel="noopener" class="mr-tautan">Buka berkas ↗</a>
                <template v-else-if="k.t === 'tanggal'">{{ tanggal(b[k.k]) }}</template>
                <template v-else-if="(k.t === 'angka' || k.t === 'hitung') && k.des !== null">{{ polos(k) ? (b[k.k] ?? '—') : angka(k, b[k.k]) }}</template>
                <template v-else>{{ b[k.k] === null || b[k.k] === '' ? '—' : b[k.k] }}</template>
              </td>
              <td v-if="izin.ubah || izin.hapus" class="text-right whitespace-nowrap mr-tanpa-cetak">
                <button v-if="izin.ubah" type="button" class="eq-btn-mini" @click="buka(b)">Ubah</button>
                <button v-if="izin.hapus" type="button" class="eq-btn-mini mr-hapus" @click="hapus(b)">Hapus</button>
              </td>
            </tr>
            <tr v-if="!barisHlm.length">
              <td :colspan="kolomDaftar.length + 2" class="px-5 py-8 text-center text-stone-400">
                {{ semua.length ? 'Tidak ada baris yang cocok dengan saringan.' : `Belum ada data ${m.judul}.` }}
                <button v-if="!semua.length && izin.tambah" type="button" class="eq-btn-mini ml-2" @click="buka()">{{ m.tambah }}</button>
              </td>
            </tr>
          </tbody>
          <tfoot v-if="tersaring.length && (m.jumlah ?? []).length">
            <tr>
              <td class="font-bold">Σ</td>
              <td v-for="k in kolomDaftar" :key="k.k" :class="{ 'text-right num font-bold': k.k in total }">{{ k.k in total ? angka(k, total[k.k]) : '' }}</td>
              <td v-if="izin.ubah || izin.hapus" class="mr-tanpa-cetak" />
            </tr>
          </tfoot>
        </table>
      </div>

      <div v-if="jumlahHlm > 1" class="px-5 py-3 flex flex-wrap items-center justify-between gap-2 text-[12px] text-stone-500 mr-tanpa-cetak">
        <span>Halaman {{ hlm }} dari {{ jumlahHlm }} · {{ tersaring.length }} baris</span>
        <div class="flex gap-1">
          <button type="button" class="eq-btn-mini" :disabled="hlm <= 1" @click="hlm--">‹ Sebelumnya</button>
          <button type="button" class="eq-btn-mini" :disabled="hlm >= jumlahHlm" @click="hlm++">Berikutnya ›</button>
        </div>
      </div>
    </Kartu>

    <!-- Panel tambah / ubah — dipindah ke <body>: kerangka halaman memakai transform,
         dan lapisan `fixed` di dalamnya hanya menutupi kolom konten, bukan layar. -->
    <Teleport to="body">
    <div v-if="panel" class="mr-latar" @click.self="tutup">
      <form class="mr-panel" role="dialog" aria-modal="true" :aria-label="edit ? `Ubah ${m.judul}` : m.tambah" @submit.prevent="simpan">
        <header class="flex items-start justify-between gap-3">
          <div>
            <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">{{ m.grup }}</p>
            <p class="text-[17px] font-black text-cam-ink">{{ edit ? `Ubah ${m.judul}` : m.tambah }}</p>
          </div>
          <button type="button" class="mr-x" aria-label="Tutup" @click="tutup">×</button>
        </header>

        <div class="grid gap-3 sm:grid-cols-2 mt-4">
          <label v-for="k in kolomIsian" :key="k.k" class="mr-label" :class="{ 'sm:col-span-2': k.t === 'panjang' || k.t === 'tautan' || k.k.startsWith('nama_') || k.k === 'keterangan' || k.k === 'perihal' || k.k === 'judul' }">
            <span>{{ k.l }}<span v-if="k.satuan && k.satuan !== 'Rp'" class="font-normal"> ({{ k.satuan }})</span><b v-if="k.wajib" class="text-red-600"> *</b></span>
            <select v-if="k.t === 'pilih'" v-model="isian[k.k]" class="mr-isian" :required="k.wajib">
              <option value="">— pilih —</option>
              <option v-for="o in k.opsi ?? []" :key="o" :value="o">{{ o }}</option>
              <option v-if="isian[k.k] && !(k.opsi ?? []).includes(isian[k.k])" :value="isian[k.k]">{{ isian[k.k] }}</option>
            </select>
            <textarea v-else-if="k.t === 'panjang'" v-model="isian[k.k]" class="mr-isian font-mono text-[11px]" rows="5" :required="k.wajib" />
            <input v-else-if="k.t === 'angka'" v-model="isian[k.k]" type="number" :step="k.des ? 1 / 10 ** k.des : 1" min="0" class="mr-isian num" :required="k.wajib">
            <input v-else-if="k.t === 'tanggal'" v-model="isian[k.k]" type="date" class="mr-isian" :required="k.wajib">
            <input v-else-if="k.t === 'tautan'" v-model="isian[k.k]" type="url" class="mr-isian" placeholder="https://…">
            <input v-else v-model="isian[k.k]" type="text" class="mr-isian" :maxlength="k.panjang ?? 190" :required="k.wajib" :list="k.saran ? `saran-${k.saran}` : undefined">
            <span v-if="k.petunjuk" class="mr-petunjuk">{{ k.petunjuk }}</span>
            <span v-if="galat[k.k]" class="mr-galat">{{ galat[k.k] }}</span>
          </label>
        </div>

        <div v-if="pratinjauSkor !== null" class="mr-skor mt-4">
          <span>Skor Matrik 17</span>
          <b class="num">{{ n(pratinjauSkor, 2) }}</b>
          <Lencana :nilai="kategoriSkor(pratinjauSkor)" />
          <span class="text-[11px] text-stone-500">60% penatagunaan + 20% revegetasi + 20% penyelesaian akhir</span>
        </div>

        <datalist v-for="(daftar, s) in props.saran ?? {}" :id="`saran-${s}`" :key="s">
          <option v-for="v in daftar" :key="v" :value="v" />
        </datalist>

        <footer class="mt-5 flex flex-wrap justify-end gap-2">
          <button type="button" class="eq-btn-lain" @click="tutup">Batal</button>
          <button type="submit" class="eq-btn-utama" :disabled="sibuk">{{ sibuk ? 'Menyimpan…' : 'Simpan' }}</button>
        </footer>
      </form>
    </div>
    </Teleport>

    <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
  </div>
</template>

<style scoped>
.mr-tab { display: flex; flex-wrap: wrap; gap: 6px; }
.mr-tab a { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; border: 1px solid #E7E5E4; background: #fff; font-size: 12px; font-weight: 700; color: #44403C; }
.mr-tab a.aktif { border-color: #2E7D55; background: #ECF7F0; color: #0A3A2A; }
.mr-label { display: flex; flex-direction: column; gap: 4px; font-size: 11.5px; font-weight: 700; color: #57534E; }
.mr-isian { border-radius: 10px; border: 1px solid #E7E5E4; font-size: 12.5px; padding: 7px 10px; background: #fff; color: #1C1917; width: 100%; }
.mr-isian:focus { outline: 2px solid #4CA771; outline-offset: 0; border-color: #4CA771; }
.mr-petunjuk { font-size: 10.5px; font-weight: 500; color: #78716C; }
.mr-galat { font-size: 11px; font-weight: 700; color: #B91C1C; }
.mr-tabel thead th { text-align: left; font-size: 11px; font-weight: 700; color: #78716C; padding: 8px 10px; background: #FAFAF9; white-space: nowrap; }
.mr-tabel thead th.text-right { text-align: right; }
.mr-tabel td { padding: 8px 10px; vertical-align: top; }
.mr-tabel th:first-child, .mr-tabel td:first-child { padding-left: 20px; }
.mr-tabel tbody tr:hover { background: #F7FBF8; }
.mr-tabel tfoot td { padding: 9px 10px; background: #F4FAF6; border-top: 2px solid #C9E7D3; }
.mr-urut { font: inherit; color: inherit; display: inline-flex; align-items: center; gap: 4px; }
.mr-panah { font-size: 9px; color: #2E7D55; min-width: 8px; }
.mr-impor { margin-left: 3px; font-size: 10px; color: #2D8C8C; }
.mr-tautan { color: #1E6F4A; font-weight: 700; text-decoration: underline; }
.mr-hapus { color: #B91C1C; }
.mr-latar { position: fixed; inset: 0; z-index: 80; background: rgba(10, 30, 22, .45); display: flex; justify-content: flex-end; }
.mr-panel { width: min(560px, 100%); height: 100%; overflow-y: auto; background: #fff; padding: 22px 22px 28px; box-shadow: -12px 0 32px -12px rgba(0, 0, 0, .35); }
.mr-x { width: 34px; height: 34px; border-radius: 10px; font-size: 22px; line-height: 1; color: #57534E; background: #F5F5F4; }
.mr-skor { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 12px; background: #F4FAF6; border: 1px solid #C9E7D3; font-size: 12.5px; font-weight: 700; color: #0A3A2A; }
.mr-skor b { font-size: 20px; }
@media print {
  .mr-tanpa-cetak { display: none !important; }
}
:root[data-tema="gelap"] .mr-tab a { background: #20272B; border-color: #2E373C; color: #D5DDE1; }
:root[data-tema="gelap"] .mr-tab a.aktif { background: #12271D; border-color: #22C55E; color: #8FE3BE; }
:root[data-tema="gelap"] .mr-label { color: #B4BEC3; }
:root[data-tema="gelap"] .mr-isian { background: #1B2124; border-color: #2E373C; color: #E8EDEF; }
:root[data-tema="gelap"] .mr-tabel thead th { background: #20272B; color: #93A1A8; }
:root[data-tema="gelap"] .mr-tabel tbody tr:hover { background: #20272B; }
:root[data-tema="gelap"] .mr-tabel tfoot td { background: #12271D; border-top-color: #1F5A3A; }
:root[data-tema="gelap"] .mr-panel { background: #1B2124; }
:root[data-tema="gelap"] .mr-x { background: #2A3034; color: #D5DDE1; }
:root[data-tema="gelap"] .mr-skor { background: #12271D; border-color: #1F5A3A; color: #D5E3DA; }
:root[data-tema="gelap"] .mr-galat { color: #F5A9A9; }
:root[data-tema="gelap"] .mr-tautan { color: #8FE3BE; }
</style>

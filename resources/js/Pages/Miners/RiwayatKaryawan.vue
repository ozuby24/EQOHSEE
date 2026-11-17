<script setup lang="ts">
/**
 * Miners → Monitoring → Riwayat Karyawan — port DBEST #22 (16 Nov 2026).
 *
 * Bahan pertimbangan rekrutmen bagi pemegang IUP dan mitranya: mengapa
 * seseorang berhenti dari perusahaan sebelumnya di site, rekomendasi rekrut
 * kembali, dan catatan pelanggarannya. "Lintas perusahaan" = lintas satu
 * grup IUP (induk + mitranya), tidak pernah lintas pemegang IUP.
 *
 * Tiga tab: Cek Karyawan · Karyawan Nonaktif · Rekap Pelanggaran.
 * NIK perusahaan lain tampil tersamar; data kesehatan tidak ada di sini.
 */
import { computed, reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import KepalaHalaman from '../../Components/Miners/KepalaHalaman.vue';
import Kartu from '../../Components/Miners/Kartu.vue';
import Modal from '../../Components/Miners/Modal.vue';
import Medan from '../../Components/Miners/Medan.vue';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';

type Peta = Record<string, string>;
interface Catatan {
  id: number; tanggal: string | null; kategori: string; labelKategori: string; tingkat: string; labelTingkat: string;
  sanksi: string | null; labelSanksi: string | null; uraian: string; lokasi: string; noDokumen: string;
  status: string; labelStatus: string; catatanVerifikasi: string; perusahaan: string | null; pencatat: string | null;
  verifikator: string | null; bolehUbah: boolean; bolehVerifikasi: boolean; pekerja?: string | null;
}
interface Baris {
  id: number; nama: string; perusahaan: string | null; jabatan: string | null; noInduk: string; aktif: boolean;
  masuk: string | null; berhenti: string | null; sebab: string | null; labelSebab: string | null; keterangan: string;
  rekomendasi: string | null; labelRekomendasi: string | null; bolehKelola: boolean;
  nik?: string; pelanggaran?: number; berat?: number;
}
interface Orang {
  kunci: string; nama: string; namaLain: string[]; nik: string; nikValid: boolean; aktifDi: string[]; nonaktif: number;
  phkPelanggaran: boolean; tidakDisarankan: boolean; jumlahPelanggaran: number; berat: number; sanksiTerberat: string | null;
  baris: Baris[]; terpotong: number; pelanggaran: Catatan[];
}

const props = defineProps<{
  tab: 'cek' | 'nonaktif' | 'pelanggaran';
  q: string;
  orang?: Orang[];
  nonaktif?: { halaman: { data: Baris[]; links: Array<{ url: string | null; label: string; active: boolean }>; total: number }; jumlah: Record<string, number> };
  rekap?: {
    kartu: Record<string, number>;
    kategori: Array<{ k: string; n: string; jumlah: number }>;
    perusahaan: Array<{ n: string; jumlah: number; berat: number }>;
    orang: Array<{ nama: string; nik: string; perusahaan: string | null; jumlah: number; berat: number; sanksi: string | null; terakhir: string | null }>;
    daftar: Catatan[];
  };
  saring: Record<string, string>;
  pengelola: boolean;
  menunggu: number;
  opsi: {
    alasan: Peta; wajibKet: string[]; rekomendasi: Peta; kategori: Peta; tingkat: Peta; sanksi: Peta; status: Peta;
    perusahaan: Array<{ id: number; nama: string }>; tahun: number[];
  };
}>();

const URL = '/miners/riwayat-karyawan';

/* Dialog EQOHSEE, bukan confirm()/prompt() peramban — di webview ponsel
   keduanya dibungkam dan tindakannya diam-diam tidak jadi. */
const { dialog, tanya, minta, batal, lanjut } = useDialog();
const TAB: Array<[typeof props.tab, string]> = [['cek', 'Cek Karyawan'], ['nonaktif', 'Karyawan Nonaktif'], ['pelanggaran', 'Rekap Pelanggaran']];

const cari = ref(props.q);
const saring = reactive({ ...props.saring });
function ke(tab: string, extra: Record<string, string> = {}) {
  const isi: Record<string, string> = { tab, ...extra };
  Object.keys(isi).forEach((k) => { if (isi[k] === '' || isi[k] == null) delete isi[k]; });
  router.get(URL, isi, { preserveScroll: true, preserveState: false });
}
const terapkanNonaktif = () => ke('nonaktif', { q: cari.value, sebab: saring.sebab, perusahaan: saring.perusahaan, tahun: saring.tahun, rekomendasi: saring.rekomendasi });
const terapkanRekap = () => ke('pelanggaran', { kategori: saring.kategori, tingkat: saring.tingkat, status: saring.status });

const WARNA_SEBAB: Record<string, string> = { phk_pelanggaran: 'mn-l-bahaya', phk_lain: 'mn-l-oranye', mangkir: 'mn-l-oranye', resign: 'mn-l-info' };
const WARNA_REK: Record<string, string> = { layak: 'mn-l-sukses', pertimbangkan: 'mn-l-oranye', tidak: 'mn-l-bahaya' };
const WARNA_TINGKAT: Record<string, string> = { berat: 'mn-l-bahaya', sedang: 'mn-l-oranye', ringan: 'mn-l-kuning' };
const WARNA_STATUS: Record<string, string> = { diajukan: 'mn-l-kuning', diverifikasi: 'mn-l-sukses', ditolak: 'mn-l-bahaya' };
const hariIni = new Date().toISOString().slice(0, 10);

/* ═══════════ dialog status ═══════════ */
const dlgStatus = ref<Baris | null>(null);
const fStatus = useForm({ aktif: false as boolean, sebab: '', tanggal: hariIni, keterangan: '', rekomendasi: '' });
function bukaStatus(b: Baris) {
  dlgStatus.value = b;
  fStatus.clearErrors();
  Object.assign(fStatus, { aktif: !b.aktif, sebab: b.sebab ?? '', tanggal: b.berhenti ?? hariIni, keterangan: b.keterangan ?? '', rekomendasi: b.rekomendasi ?? '' });
}
const ketWajib = computed(() => props.opsi.wajibKet.includes(fStatus.sebab));
function simpanStatus() {
  if (!dlgStatus.value) return;
  fStatus.post(`${URL}/${dlgStatus.value.id}/status`, { preserveScroll: true, onSuccess: () => { dlgStatus.value = null; } });
}

/* ═══════════ dialog pelanggaran ═══════════ */
const dlgCatat = ref<{ pekerja?: Baris; catatan?: Catatan } | null>(null);
const fCatat = useForm({ tanggal: hariIni, kategori: '', tingkat: '', sanksi: '', uraian: '', lokasi: '', no_dokumen: '' });
function bukaCatat(pekerja?: Baris, catatan?: Catatan) {
  dlgCatat.value = { pekerja, catatan };
  fCatat.clearErrors();
  Object.assign(fCatat, catatan
    ? { tanggal: catatan.tanggal ?? hariIni, kategori: catatan.kategori, tingkat: catatan.tingkat, sanksi: catatan.sanksi ?? '', uraian: catatan.uraian, lokasi: catatan.lokasi, no_dokumen: catatan.noDokumen }
    : { tanggal: hariIni, kategori: '', tingkat: '', sanksi: '', uraian: '', lokasi: '', no_dokumen: '' });
}
function simpanCatat() {
  const d = dlgCatat.value;
  if (!d) return;
  const opsi = { preserveScroll: true, onSuccess: () => { dlgCatat.value = null; } };
  fCatat.transform((x) => ({ ...x, sanksi: x.sanksi || null }));
  if (d.catatan) fCatat.put(`${URL}/pelanggaran/${d.catatan.id}`, opsi);
  else if (d.pekerja) fCatat.post(`${URL}/${d.pekerja.id}/pelanggaran`, opsi);
}
async function hapusCatat(c: Catatan) {
  const ok = await tanya({
    judul: 'Hapus catatan pelanggaran?',
    pesan: `${c.labelKategori} tanggal ${c.tanggal ?? '—'} dihapus dari riwayat karyawan. Penghapusan tercatat di log aktivitas.`,
    labelAksi: 'Hapus', nada: 'bahaya',
  });
  if (!ok) return;
  router.delete(`${URL}/pelanggaran/${c.id}`, { preserveScroll: true });
}
async function verifikasi(c: Catatan, keputusan: 'diverifikasi' | 'ditolak') {
  let catatan: string | null = null;
  if (keputusan === 'ditolak') {
    catatan = await minta({
      judul: 'Tolak catatan pelanggaran?',
      pesan: 'Alasannya terbaca oleh pencatat agar dapat diperbaiki dan diajukan kembali.',
      label: 'Alasan penolakan', jenis: 'panjang', wajib: true, min: 3, nada: 'bahaya', labelAksi: 'Tolak',
    });
    if (catatan === null) return;
  }
  router.post(`${URL}/pelanggaran/${c.id}/verifikasi`, { keputusan, catatan }, { preserveScroll: true });
}
</script>

<template>
  <div>
    <KepalaHalaman judul="Riwayat Karyawan" :remah="[['Home', '/miners/dasbor'], ['Monitoring', null], ['Riwayat Karyawan', null]]" />

    <div class="mn-alert mn-alert-info">
      Bahan pertimbangan rekrutmen di site: alasan berhenti dari perusahaan sebelumnya, rekomendasi rekrut kembali, dan catatan pelanggaran.
      Data pribadi (UU No. 27 Tahun 2022) — hanya untuk pertimbangan rekrutmen dan keselamatan kerja, tidak untuk disebarluaskan.
    </div>

    <div class="mn-chip-baris" role="tablist">
      <button v-for="[k, l] in TAB" :key="k" type="button" role="tab" class="mn-chip" :class="{ 'mn-aktif': tab === k }" :aria-selected="tab === k" @click="ke(k)">
        {{ l }}<template v-if="k === 'pelanggaran' && pengelola && menunggu"> · {{ menunggu }} menunggu</template>
      </button>
    </div>

    <!-- ═══════════ CEK KARYAWAN ═══════════ -->
    <template v-if="tab === 'cek'">
      <Kartu judul="Cek Karyawan" keterangan="Cari dengan NIK KTP 16 digit (disarankan), nama (min. 3 huruf), atau nomor induk">
        <form class="mn-saring" @submit.prevent="ke('cek', { q: cari })">
          <input v-model="cari" class="mn-isian mn-saring-lebar" placeholder="NIK, nama, atau nomor induk" aria-label="Cari karyawan" minlength="3">
          <button class="mn-btn mn-btn-utama">Cari</button>
        </form>
      </Kartu>

      <p v-if="q && !orang?.length" class="mn-hampa">Tidak ditemukan karyawan dengan kata kunci "{{ q }}" dalam grup perusahaan Anda.</p>

      <Kartu v-for="o in orang" :key="o.kunci" :judul="o.nama" :keterangan="`NIK ${o.nik || '—'}${o.nikValid ? '' : ' (tidak sah — dikenali per baris)'}${o.namaLain.length ? ' · juga tercatat sebagai ' + o.namaLain.join(', ') : ''}`">
        <template #kanan>
          <span v-if="o.aktifDi.length" class="mn-lencana mn-l-sukses">Aktif di {{ o.aktifDi.join(', ') }}</span>
          <span v-if="o.phkPelanggaran" class="mn-lencana mn-l-bahaya">Pernah PHK karena pelanggaran</span>
          <span v-if="o.tidakDisarankan" class="mn-lencana mn-l-bahaya">Tidak disarankan direkrut</span>
          <span v-if="o.jumlahPelanggaran" class="mn-lencana mn-l-oranye">{{ o.jumlahPelanggaran }} pelanggaran{{ o.berat ? ` (${o.berat} berat)` : '' }}</span>
        </template>
        <div class="mn-gulir">
          <table class="mn-tabel">
            <thead><tr><th>Perusahaan</th><th>Jabatan</th><th>Status</th><th>Alasan / keterangan</th><th>Rekomendasi</th><th /></tr></thead>
            <tbody>
              <tr v-for="b in o.baris" :key="b.id">
                <td>{{ b.perusahaan }}<span v-if="b.noInduk" class="mn-teks-redup"> · {{ b.noInduk }}</span></td>
                <td>{{ b.jabatan ?? '—' }}</td>
                <td>
                  <span class="mn-lencana" :class="b.aktif ? 'mn-l-sukses' : 'mn-l-abu'">{{ b.aktif ? 'Aktif' : 'Tidak aktif' }}</span>
                  <span v-if="b.berhenti" class="mn-teks-redup"> sejak {{ b.berhenti }}</span>
                </td>
                <td>
                  <span v-if="b.labelSebab" class="mn-lencana" :class="WARNA_SEBAB[b.sebab ?? ''] ?? 'mn-l-abu'">{{ b.labelSebab }}</span>
                  <span v-if="b.keterangan" class="mn-teks-redup"> {{ b.keterangan }}</span>
                </td>
                <td><span v-if="b.labelRekomendasi" class="mn-lencana" :class="WARNA_REK[b.rekomendasi ?? '']">{{ b.labelRekomendasi }}</span></td>
                <td class="whitespace-nowrap">
                  <template v-if="b.bolehKelola">
                    <button type="button" class="mn-btn mn-btn-kecil mn-btn-garis" @click="bukaStatus(b)">{{ b.aktif ? 'Nonaktifkan' : 'Ubah status' }}</button>
                    <button type="button" class="mn-btn mn-btn-kecil mn-btn-garis-bahaya" @click="bukaCatat(b)">Catat pelanggaran</button>
                  </template>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-if="o.terpotong" class="mn-teks-redup">… dan {{ o.terpotong }} baris lain.</p>

        <div v-if="o.pelanggaran.length" class="mt-4">
          <p class="mn-sub-bagian">Catatan pelanggaran<template v-if="o.sanksiTerberat"> · sanksi terberat: {{ o.sanksiTerberat }}</template></p>
          <ul class="mn-daftar">
            <li v-for="c in o.pelanggaran" :key="c.id" class="mn-daftar-baris">
              <div class="min-w-0 leading-relaxed">
                <b>{{ c.tanggal }}</b> · {{ c.labelKategori }}
                <span class="mn-lencana" :class="WARNA_TINGKAT[c.tingkat]">{{ c.labelTingkat }}</span>
                <span v-if="c.labelSanksi" class="mn-lencana mn-l-abu">{{ c.labelSanksi }}</span>
                <span class="mn-lencana" :class="WARNA_STATUS[c.status]">{{ c.labelStatus }}</span>
                <p>{{ c.uraian }}<span v-if="c.lokasi" class="mn-teks-redup"> · {{ c.lokasi }}</span><span v-if="c.noDokumen" class="mn-teks-redup"> · {{ c.noDokumen }}</span></p>
                <p class="mn-teks-redup">{{ c.perusahaan }}<template v-if="c.pencatat"> · dicatat {{ c.pencatat }}</template></p>
                <p v-if="c.status === 'ditolak' && c.catatanVerifikasi" class="mn-galat">Ditolak: {{ c.catatanVerifikasi }}</p>
              </div>
              <div class="flex flex-wrap gap-1.5 justify-end shrink-0">
                <button v-if="c.bolehVerifikasi" type="button" class="mn-btn mn-btn-kecil mn-btn-sukses" @click="verifikasi(c, 'diverifikasi')">Verifikasi</button>
                <button v-if="c.bolehVerifikasi" type="button" class="mn-btn mn-btn-kecil mn-btn-garis-bahaya" @click="verifikasi(c, 'ditolak')">Tolak</button>
                <button v-if="c.bolehUbah" type="button" class="mn-btn mn-btn-kecil mn-btn-garis" @click="bukaCatat(undefined, c)">Ubah</button>
                <button v-if="c.bolehUbah" type="button" class="mn-btn mn-btn-kecil mn-btn-hantu" @click="hapusCatat(c)">Hapus</button>
              </div>
            </li>
          </ul>
        </div>
      </Kartu>
    </template>

    <!-- ═══════════ KARYAWAN NONAKTIF ═══════════ -->
    <template v-else-if="tab === 'nonaktif' && nonaktif">
      <Kartu judul="Karyawan Nonaktif" keterangan="Seluruh perusahaan dalam grup — saring per alasan, perusahaan, tahun berhenti, dan rekomendasi">
        <div class="mn-chip-baris">
          <button type="button" class="mn-chip" :class="{ 'mn-aktif': !saring.sebab }" @click="saring.sebab = ''; terapkanNonaktif()">Semua</button>
          <button v-for="(l, k) in { ...opsi.alasan, kosong: 'Alasan belum diisi' }" :key="k" type="button" class="mn-chip"
                  :class="{ 'mn-aktif': saring.sebab === k }" @click="saring.sebab = String(k); terapkanNonaktif()">
            {{ l }} ({{ nonaktif.jumlah[k] ?? 0 }})
          </button>
        </div>
        <form class="mn-saring" @submit.prevent="terapkanNonaktif">
          <input v-model="cari" class="mn-isian mn-saring-lebar" placeholder="Nama atau NIK" aria-label="Nama atau NIK">
          <select v-model="saring.perusahaan" class="mn-isian" aria-label="Perusahaan">
            <option value="">Semua perusahaan</option>
            <option v-for="p in opsi.perusahaan" :key="p.id" :value="String(p.id)">{{ p.nama }}</option>
          </select>
          <select v-model="saring.tahun" class="mn-isian" aria-label="Tahun berhenti">
            <option value="">Semua tahun</option>
            <option v-for="t in opsi.tahun" :key="t" :value="String(t)">{{ t }}</option>
          </select>
          <select v-model="saring.rekomendasi" class="mn-isian" aria-label="Rekomendasi">
            <option value="">Semua rekomendasi</option>
            <option v-for="(l, k) in opsi.rekomendasi" :key="k" :value="k">{{ l }}</option>
          </select>
          <button class="mn-btn mn-btn-utama">Terapkan</button>
        </form>
        <div class="mn-gulir">
          <table class="mn-tabel">
            <thead><tr><th>Nama</th><th>NIK</th><th>Perusahaan</th><th>Berhenti</th><th>Alasan</th><th>Rekomendasi</th><th>Pelanggaran</th><th /></tr></thead>
            <tbody>
              <tr v-for="b in nonaktif.halaman.data" :key="b.id">
                <td><b>{{ b.nama }}</b><span v-if="b.jabatan" class="mn-teks-redup"> · {{ b.jabatan }}</span></td>
                <td class="mn-num">{{ b.nik }}</td>
                <td>{{ b.perusahaan }}</td>
                <td>{{ b.berhenti ?? '—' }}</td>
                <td><span class="mn-lencana" :class="WARNA_SEBAB[b.sebab ?? ''] ?? 'mn-l-abu'">{{ b.labelSebab }}</span>
                  <span v-if="b.keterangan" class="mn-teks-redup"> {{ b.keterangan }}</span></td>
                <td><span v-if="b.labelRekomendasi" class="mn-lencana" :class="WARNA_REK[b.rekomendasi ?? '']">{{ b.labelRekomendasi }}</span></td>
                <td>{{ b.pelanggaran || '—' }}<span v-if="b.berat" class="mn-teks-kritis"> ({{ b.berat }} berat)</span></td>
                <td class="whitespace-nowrap">
                  <button v-if="b.bolehKelola" type="button" class="mn-btn mn-btn-kecil mn-btn-garis" @click="bukaStatus(b)">Ubah status</button>
                </td>
              </tr>
              <tr v-if="!nonaktif.halaman.data.length"><td colspan="8" class="mn-hampa">Tidak ada karyawan nonaktif pada saringan ini.</td></tr>
            </tbody>
          </table>
        </div>
        <nav v-if="nonaktif.halaman.links.length > 3" class="mn-paginasi">
          <template v-for="(l, i) in nonaktif.halaman.links" :key="i">
            <a v-if="l.url" :href="l.url" :class="{ 'mn-kini': l.active }" v-html="l.label" />
          </template>
        </nav>
      </Kartu>
    </template>

    <!-- ═══════════ REKAP PELANGGARAN ═══════════ -->
    <template v-else-if="tab === 'pelanggaran' && rekap">
      <div class="mn-kpi-grid">
        <div class="mn-kpi mn-kpi-info"><p class="mn-kpi-label">Catatan</p><p class="mn-kpi-angka">{{ rekap.kartu.total }}</p></div>
        <div class="mn-kpi mn-kpi-bahaya"><p class="mn-kpi-label">Berat</p><p class="mn-kpi-angka">{{ rekap.kartu.berat }}</p></div>
        <div class="mn-kpi mn-kpi-kritis"><p class="mn-kpi-label">Sedang</p><p class="mn-kpi-angka">{{ rekap.kartu.sedang }}</p></div>
        <div class="mn-kpi mn-kpi-aman"><p class="mn-kpi-label">Ringan</p><p class="mn-kpi-angka">{{ rekap.kartu.ringan }}</p></div>
        <div class="mn-kpi mn-kpi-aksen"><p class="mn-kpi-label">Karyawan berulang</p><p class="mn-kpi-angka">{{ rekap.kartu.berulang }}</p></div>
        <div class="mn-kpi mn-kpi-info"><p class="mn-kpi-label">Menunggu verifikasi</p><p class="mn-kpi-angka">{{ rekap.kartu.menunggu }}</p></div>
      </div>

      <div class="mn-dua-kolom">
        <Kartu judul="Sebaran per kategori">
          <ul class="mn-daftar">
            <li v-for="k in rekap.kategori" :key="k.k" class="mn-daftar-baris"><span>{{ k.n }}</span><b class="mn-num">{{ k.jumlah }}</b></li>
            <li v-if="!rekap.kategori.length" class="mn-hampa">Belum ada catatan.</li>
          </ul>
        </Kartu>
        <Kartu v-if="pengelola" judul="Per perusahaan">
          <ul class="mn-daftar">
            <li v-for="p in rekap.perusahaan" :key="p.n" class="mn-daftar-baris"><span>{{ p.n }}</span><b class="mn-num">{{ p.jumlah }}<span v-if="p.berat" class="mn-teks-kritis"> ({{ p.berat }} berat)</span></b></li>
            <li v-if="!rekap.perusahaan.length" class="mn-hampa">Belum ada catatan.</li>
          </ul>
        </Kartu>
      </div>

      <Kartu judul="Rekap per karyawan" keterangan="Sanksi terberat dan catatan terakhir">
        <div class="mn-gulir">
          <table class="mn-tabel">
            <thead><tr><th>Nama</th><th>NIK</th><th>Perusahaan</th><th>Catatan</th><th>Sanksi terberat</th><th>Terakhir</th></tr></thead>
            <tbody>
              <tr v-for="(o, i) in rekap.orang" :key="i">
                <td><b>{{ o.nama }}</b></td><td class="mn-num">{{ o.nik }}</td><td>{{ o.perusahaan }}</td>
                <td>{{ o.jumlah }}<span v-if="o.berat" class="mn-teks-kritis"> ({{ o.berat }} berat)</span></td>
                <td>{{ o.sanksi ?? '—' }}</td><td>{{ o.terakhir }}</td>
              </tr>
              <tr v-if="!rekap.orang.length"><td colspan="6" class="mn-hampa">Belum ada catatan pelanggaran.</td></tr>
            </tbody>
          </table>
        </div>
      </Kartu>

      <Kartu judul="Daftar catatan">
        <form class="mn-saring" @submit.prevent="terapkanRekap">
          <select v-model="saring.kategori" class="mn-isian" aria-label="Kategori">
            <option value="">Semua kategori</option>
            <option v-for="(l, k) in opsi.kategori" :key="k" :value="k">{{ l }}</option>
          </select>
          <select v-model="saring.tingkat" class="mn-isian" aria-label="Tingkat">
            <option value="">Semua tingkat</option>
            <option v-for="(l, k) in opsi.tingkat" :key="k" :value="k">{{ l }}</option>
          </select>
          <select v-model="saring.status" class="mn-isian" aria-label="Status verifikasi">
            <option value="">Semua status</option>
            <option v-for="(l, k) in opsi.status" :key="k" :value="k">{{ l }}</option>
          </select>
          <button class="mn-btn mn-btn-utama">Terapkan</button>
        </form>
        <ul class="mn-daftar">
          <li v-for="c in rekap.daftar" :key="c.id" class="mn-daftar-baris">
            <div class="min-w-0 leading-relaxed">
              <b>{{ c.pekerja }}</b> · {{ c.tanggal }} · {{ c.labelKategori }}
              <span class="mn-lencana" :class="WARNA_TINGKAT[c.tingkat]">{{ c.labelTingkat }}</span>
              <span v-if="c.labelSanksi" class="mn-lencana mn-l-abu">{{ c.labelSanksi }}</span>
              <span class="mn-lencana" :class="WARNA_STATUS[c.status]">{{ c.labelStatus }}</span>
              <p>{{ c.uraian }}</p>
              <p class="mn-teks-redup">{{ c.perusahaan }}<template v-if="c.pencatat"> · dicatat {{ c.pencatat }}</template><template v-if="c.verifikator"> · diputuskan {{ c.verifikator }}</template></p>
              <p v-if="c.status === 'ditolak' && c.catatanVerifikasi" class="mn-galat">Ditolak: {{ c.catatanVerifikasi }}</p>
            </div>
            <div class="flex flex-wrap gap-1.5 justify-end shrink-0">
              <button v-if="c.bolehVerifikasi" type="button" class="mn-btn mn-btn-kecil mn-btn-sukses" @click="verifikasi(c, 'diverifikasi')">Verifikasi</button>
              <button v-if="c.bolehVerifikasi" type="button" class="mn-btn mn-btn-kecil mn-btn-garis-bahaya" @click="verifikasi(c, 'ditolak')">Tolak</button>
              <button v-if="c.bolehUbah" type="button" class="mn-btn mn-btn-kecil mn-btn-garis" @click="bukaCatat(undefined, c)">Ubah</button>
              <button v-if="c.bolehUbah" type="button" class="mn-btn mn-btn-kecil mn-btn-hantu" @click="hapusCatat(c)">Hapus</button>
            </div>
          </li>
          <li v-if="!rekap.daftar.length" class="mn-hampa">Tidak ada catatan pada saringan ini.</li>
        </ul>
      </Kartu>
    </template>

    <!-- ═══════════ dialog status ═══════════ -->
    <Modal :terbuka="!!dlgStatus" :judul="`Status — ${dlgStatus?.nama ?? ''}`" @tutup="dlgStatus = null">
      <form class="mn-form" @submit.prevent="simpanStatus">
        <Medan label="Status" untuk="s-aktif" penuh>
          <select id="s-aktif" v-model="fStatus.aktif" class="mn-isian">
            <option :value="false">Tidak aktif</option>
            <option :value="true">Aktif kembali</option>
          </select>
        </Medan>
        <template v-if="!fStatus.aktif">
          <Medan label="Alasan tidak aktif" untuk="s-sebab" wajib :galat="fStatus.errors.sebab" penuh>
            <select id="s-sebab" v-model="fStatus.sebab" class="mn-isian">
              <option value="">— pilih —</option>
              <option v-for="(l, k) in opsi.alasan" :key="k" :value="k">{{ l }}</option>
            </select>
          </Medan>
          <Medan label="Tanggal berhenti" untuk="s-tgl" wajib :galat="fStatus.errors.tanggal">
            <input id="s-tgl" v-model="fStatus.tanggal" type="date" class="mn-isian">
          </Medan>
          <Medan label="Rekomendasi rekrut kembali" untuk="s-rek" :galat="fStatus.errors.rekomendasi">
            <select id="s-rek" v-model="fStatus.rekomendasi" class="mn-isian">
              <option value="">—</option>
              <option v-for="(l, k) in opsi.rekomendasi" :key="k" :value="k">{{ l }}</option>
            </select>
          </Medan>
          <Medan label="Keterangan" untuk="s-ket" :wajib="ketWajib" :galat="fStatus.errors.keterangan" penuh
                 :petunjuk="ketWajib ? 'Alasan ini wajib disertai keterangan agar tidak menyesatkan pembaca.' : null">
            <textarea id="s-ket" v-model="fStatus.keterangan" class="mn-isian" rows="2" maxlength="200" />
          </Medan>
        </template>
        <p v-else class="mn-teks-redup">Mengaktifkan kembali mengosongkan alasan; isian lamanya tetap tercatat di log aktivitas.</p>
      </form>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-hantu" @click="dlgStatus = null">Batal</button>
        <button type="button" class="mn-btn mn-btn-utama" :disabled="fStatus.processing" @click="simpanStatus">Simpan</button>
      </template>
    </Modal>

    <!-- ═══════════ dialog pelanggaran ═══════════ -->
    <Modal :terbuka="!!dlgCatat" :judul="dlgCatat?.catatan ? 'Ubah catatan pelanggaran' : `Catat pelanggaran — ${dlgCatat?.pekerja?.nama ?? ''}`" lebar @tutup="dlgCatat = null">
      <form class="mn-form" @submit.prevent="simpanCatat">
        <Medan label="Tanggal" untuk="c-tgl" wajib :galat="fCatat.errors.tanggal">
          <input id="c-tgl" v-model="fCatat.tanggal" type="date" class="mn-isian" :max="hariIni">
        </Medan>
        <Medan label="Kategori" untuk="c-kat" wajib :galat="fCatat.errors.kategori">
          <select id="c-kat" v-model="fCatat.kategori" class="mn-isian">
            <option value="">— pilih —</option>
            <option v-for="(l, k) in opsi.kategori" :key="k" :value="k">{{ l }}</option>
          </select>
        </Medan>
        <Medan label="Tingkat" untuk="c-tkt" wajib :galat="fCatat.errors.tingkat">
          <select id="c-tkt" v-model="fCatat.tingkat" class="mn-isian">
            <option value="">— pilih —</option>
            <option v-for="(l, k) in opsi.tingkat" :key="k" :value="k">{{ l }}</option>
          </select>
        </Medan>
        <Medan label="Sanksi" untuk="c-snk" :galat="fCatat.errors.sanksi">
          <select id="c-snk" v-model="fCatat.sanksi" class="mn-isian">
            <option value="">—</option>
            <option v-for="(l, k) in opsi.sanksi" :key="k" :value="k">{{ l }}</option>
          </select>
        </Medan>
        <Medan label="Uraian" untuk="c-ur" wajib :galat="fCatat.errors.uraian" penuh>
          <textarea id="c-ur" v-model="fCatat.uraian" class="mn-isian" rows="3" maxlength="2000" />
        </Medan>
        <Medan label="Lokasi" untuk="c-lok" :galat="fCatat.errors.lokasi">
          <input id="c-lok" v-model="fCatat.lokasi" class="mn-isian" maxlength="150">
        </Medan>
        <Medan label="No. dokumen sanksi" untuk="c-dok" :galat="fCatat.errors.no_dokumen">
          <input id="c-dok" v-model="fCatat.no_dokumen" class="mn-isian" maxlength="100">
        </Medan>
        <p v-if="!pengelola" class="mn-teks-redup mn-form-penuh">Catatan dari mitra menunggu verifikasi OHSE sebelum terlihat perusahaan lain.</p>
      </form>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-hantu" @click="dlgCatat = null">Batal</button>
        <button type="button" class="mn-btn mn-btn-utama" :disabled="fCatat.processing" @click="simpanCatat">Simpan</button>
      </template>
    </Modal>

    <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
  </div>
</template>

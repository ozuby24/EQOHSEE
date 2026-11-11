<script setup lang="ts">
/**
 * Detail karyawan — `detail_manpower` Safe Track dalam bahasa rupa Miners.
 *
 * Kepala profil, lalu kartu menurut RANTAINYA — MCU, Induksi, Mine
 * Permit, SIMPER — bukan menurut jenis dokumen yang kebetulan paling
 * banyak: yang menggugurkan kartu seseorang hampir selalu dokumen yang
 * lebih awal pada rantainya. Sesudahnya perpanjangan, berkas, dan
 * kompetensi. Cetak kartu dan verifikasi publik ditautkan dari baris
 * Mine Permit / SIMPER yang sudah terbit.
 */
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { propHalaman } from '../../halaman';
import KepalaHalaman from '../../Components/Miners/KepalaHalaman.vue';
import KepalaProfil from '../../Components/Miners/KepalaProfil.vue';
import Kartu from '../../Components/Miners/Kartu.vue';
import DaftarDefinisi from '../../Components/Miners/DaftarDefinisi.vue';
import Butir from '../../Components/Miners/Butir.vue';
import TabelData from '../../Components/Miners/TabelData.vue';
import LencanaMasaBerlaku from '../../Components/Miners/LencanaMasaBerlaku.vue';
import LencanaStatus from '../../Components/Miners/LencanaStatus.vue';
import ChipBerkas from '../../Components/Miners/ChipBerkas.vue';
import TombolIkon from '../../Components/Miners/TombolIkon.vue';
import KeadaanKosong from '../../Components/Miners/KeadaanKosong.vue';
import { tglId } from '../../Components/Miners/zona';

const props = propHalaman();

const p = computed<any>(() => props.pekerja ?? {});
const keadaan = computed<any>(() => props.keadaan ?? {});
const mcu = computed<any[]>(() => (props.mcu ?? []) as any[]);
const induksi = computed<any[]>(() => (props.induksi ?? []) as any[]);
const permit = computed<any[]>(() => (props.permit ?? []) as any[]);
const simper = computed<any[]>(() => (props.simper ?? []) as any[]);
const riwayat = computed<any[]>(() => (props.riwayat ?? []) as any[]);
const berkas = computed<any[]>(() => (props.berkas ?? []) as any[]);
const kompetensi = computed<any[]>(() => (props.kompetensi ?? []) as any[]);

const RINGKAS: [string, string][] = [
  ['mcu', 'MCU'],
  ['induksi', 'Induksi'],
  ['permit', 'Mine Permit'],
  ['simper', 'SIMPER'],
];

const SEBAB: Record<string, string> = {
  mcu: 'Kartu mengikuti MCU yang habis lebih dahulu.',
  simpol: 'Kartu mengikuti SIM kepolisian yang habis lebih dahulu.',
  permit: 'Kartu mengikuti Mine Permit yang habis lebih dahulu.',
};

const kolomMcu = [
  { kunci: 'surat', label: 'No Registrasi' },
  { kunci: 'tanggal', label: 'Tanggal MCU' },
  { kunci: 'hasil', label: 'Hasil MCU' },
  { kunci: 'sampai', label: 'Masa Berlaku' },
  { kunci: 'status', label: 'Status' },
  { kunci: 'aksi', label: '#' },
];
const kolomInduksi = [
  { kunci: 'surat', label: 'No Registrasi' },
  { kunci: 'tanggal', label: 'Tanggal Induksi' },
  { kunci: 'nilai', label: 'Nilai' },
  { kunci: 'hasil', label: 'Hasil' },
  { kunci: 'sampai', label: 'Masa Berlaku' },
  { kunci: 'aksi', label: '#' },
];
const kolomPermit = [
  { kunci: 'nomor', label: 'No Registrasi' },
  { kunci: 'tanggal', label: 'Tanggal' },
  { kunci: 'cakupan', label: 'Cakupan Area' },
  { kunci: 'efektif', label: 'Masa Berlaku' },
  { kunci: 'status', label: 'Status' },
  { kunci: 'aksi', label: '#' },
];
const kolomUnit = [
  { kunci: 'unit', label: 'Unit' },
  { kunci: 'type', label: 'Type' },
  { kunci: 'authority', label: 'Authority' },
  { kunci: 'asal', label: 'Asal' },
];
const kolomRiwayat = [
  { kunci: 'pada', label: 'Tanggal' },
  { kunci: 'jenis', label: 'Dokumen' },
  { kunci: 'lama', label: 'Berlaku lama' },
  { kunci: 'baru', label: 'Berlaku baru' },
];
const kolomKompetensi = [
  { kunci: 'nama', label: 'Kompetensi' },
  { kunci: 'lembaga', label: 'Lembaga' },
  { kunci: 'nomor', label: 'Nomor' },
  { kunci: 'sampai', label: 'Masa Berlaku' },
];
</script>

<template>
  <div>
    <KepalaHalaman :judul="props.judul" :remah="[['Home', '/miners/dasbor'], ['Manpower', '/miners'], ['Detail', null]]" />

    <KepalaProfil :judul="p.nama || '—'" :eyebrow="`Manpower · ${p.perusahaan || 'tanpa perusahaan'}`" :foto="p.foto"
                  :keterangan="[['NIK', p.nik], ['No ID', p.no_induk], ['Jabatan', p.jabatan], ['Departemen', p.departemen]]">
      <template #lencana>
        <span class="mn-lencana mn-titik" :class="p.aktif ? 'mn-l-sukses' : 'mn-l-abu'">{{ p.status }}</span>
        <span v-if="p.dataLama" class="mn-lencana mn-l-terang">Data lama</span>
      </template>
    </KepalaProfil>

    <div v-if="!p.aktif && p.nonaktifSejak" class="mn-langkah mn-langkah-tolak">
      <div>
        <div class="mn-langkah-label">Tidak aktif sejak {{ tglId(p.nonaktifSejak) }}</div>
        <div class="mn-langkah-kurang">{{ p.alasanNonaktif || 'Tanpa keterangan.' }}</div>
      </div>
    </div>

    <Kartu judul="Kelayakan saat ini" keterangan="dokumen terbaru tiap tahap">
      <div class="mn-ringkas-rantai">
        <div v-for="[k, label] in RINGKAS" :key="k" class="mn-ringkas-tahap">
          <span class="mn-ringkas-label">{{ label }}</span>
          <LencanaMasaBerlaku v-if="keadaan[k]?.sampai" :tanggal="keadaan[k].sampai" :sisa="keadaan[k].sisa" />
          <span v-else class="mn-redup">Belum ada dokumen</span>
        </div>
      </div>
      <p v-if="keadaan.sebab && SEBAB[keadaan.sebab] && (keadaan.simper?.sisa ?? 999) <= 30" class="mn-status-catatan">{{ SEBAB[keadaan.sebab] }}</p>
    </Kartu>

    <Kartu judul="Data Karyawan">
      <DaftarDefinisi>
        <Butir label="Nama" :nilai="p.nama" />
        <Butir label="Perusahaan" :nilai="p.perusahaan" />
        <Butir label="NIK" :nilai="p.nik" />
        <Butir label="No ID" :nilai="p.no_induk" />
        <Butir label="No Registrasi" :nilai="p.no_registrasi" />
        <Butir label="Tanggal Lahir" :nilai="p.tanggal_lahir ? `${tglId(p.tanggal_lahir)}${p.usia ? ` (${p.usia} tahun)` : ''}` : null" />
        <Butir label="Golongan Darah" :nilai="p.gol_darah" />
        <Butir label="Telepon" :nilai="p.telepon" />
        <Butir label="Telepon Darurat" :nilai="p.telepon_darurat" />
        <Butir label="Jabatan" :nilai="p.jabatan" />
        <Butir label="Departemen" :nilai="p.departemen" />
        <Butir label="Subkontraktor" :nilai="p.subkontraktor" />
        <Butir label="Lokasi Kerja" :nilai="p.lokasi" />
        <Butir label="Status Kerja" :nilai="p.status_kerja" />
        <Butir label="Catatan" :nilai="p.catatan" penuh />
      </DaftarDefinisi>
    </Kartu>

    <TabelData judul="MCU" :jumlah="mcu.length" :kolom="kolomMcu" :baris="mcu" kosong="Belum ada MCU untuk karyawan ini.">
      <template #sel-surat="{ baris: b }">
        <Link :href="`/miners/mcu/${b.mcu_id}`" class="mn-tautan">{{ b.surat || 'tanpa nomor' }}</Link>
        <span v-if="!b.aktif" class="mn-lencana mn-l-abu ml-1">tidak dipakai</span>
      </template>
      <template #sel-tanggal="{ nilai }">{{ tglId(nilai) }}</template>
      <template #sel-hasil="{ baris: b }">
        <span v-if="b.hasil" class="mn-lencana" :class="b.layak ? 'mn-l-sukses' : 'mn-l-bahaya'">{{ b.hasil }}</span>
        <span v-else class="mn-redup">belum dinilai</span>
        <span v-if="b.rujukan" class="mn-lencana mn-l-kuning ml-1">{{ b.rujukan }} rujukan</span>
      </template>
      <template #sel-sampai="{ baris: b }"><LencanaMasaBerlaku :tanggal="b.sampai" :sisa="b.sisa" /></template>
      <template #sel-status="{ nilai }"><LencanaStatus :status="nilai" /></template>
      <template #sel-aksi="{ baris: b }">
        <TombolIkon ikon="mata" varian="garis" :href="`/miners/mcu/${b.mcu_id}`" :judul="`Buka MCU ${b.surat || ''}`" />
      </template>
    </TabelData>

    <TabelData judul="Induksi" :jumlah="induksi.length" :kolom="kolomInduksi" :baris="induksi" kosong="Belum ada induksi untuk karyawan ini.">
      <template #sel-surat="{ baris: b }"><Link :href="`/miners/induksi/${b.induksi_id}`" class="mn-tautan">{{ b.surat || 'tanpa nomor' }}</Link></template>
      <template #sel-tanggal="{ nilai }">{{ tglId(nilai) }}</template>
      <template #sel-nilai="{ nilai }"><span class="tabular-nums">{{ nilai ?? '—' }}</span></template>
      <template #sel-hasil="{ baris: b }"><span class="mn-lencana" :class="b.lulus ? 'mn-l-sukses' : 'mn-l-abu'">{{ b.hasil }}</span></template>
      <template #sel-sampai="{ baris: b }"><LencanaMasaBerlaku :tanggal="b.sampai" :sisa="b.sisa" /></template>
      <template #sel-aksi="{ baris: b }">
        <div class="mn-aksi mn-aksi-kiri">
          <TombolIkon ikon="mata" varian="garis" :href="`/miners/induksi/${b.induksi_id}`" :judul="`Buka induksi ${b.surat || ''}`" />
          <TombolIkon v-if="b.verifikasi" ikon="cek" varian="garis" :href="b.verifikasi" luar judul="Halaman verifikasi sertifikat induksi" />
        </div>
      </template>
    </TabelData>

    <TabelData judul="Mine Permit" :jumlah="permit.length" :kolom="kolomPermit" :baris="permit" kosong="Belum ada Mine Permit untuk karyawan ini.">
      <template #sel-nomor="{ baris: b }">
        <Link :href="`/miners/permit/${b.id}`" class="mn-tautan">{{ b.nomor || 'tanpa nomor' }}</Link>
        <span v-if="b.dataLama" class="mn-lencana mn-l-terang ml-1">data lama</span>
        <span v-if="!b.aktif" class="mn-lencana mn-l-abu ml-1">non-aktif</span>
      </template>
      <template #sel-tanggal="{ nilai }">{{ tglId(nilai) }}</template>
      <template #sel-efektif="{ baris: b }"><LencanaMasaBerlaku :tanggal="b.efektif" :sisa="b.sisa" :sumber="b.sumber" /></template>
      <template #sel-status="{ nilai }"><LencanaStatus :status="nilai" /></template>
      <template #sel-aksi="{ baris: b }">
        <div class="mn-aksi mn-aksi-kiri">
          <TombolIkon ikon="mata" varian="garis" :href="`/miners/permit/${b.id}`" :judul="`Buka Mine Permit ${b.nomor || ''}`" />
          <TombolIkon v-if="b.cetak" ikon="cetak" varian="garis" :href="b.cetak" luar :judul="`Cetak kartu ${b.nomor || ''}`" />
          <TombolIkon v-if="b.verifikasi" ikon="cek" varian="garis" :href="b.verifikasi" luar judul="Halaman verifikasi kartu" />
        </div>
      </template>
    </TabelData>

    <Kartu judul="SIMPER" :keterangan="`${simper.length} dokumen`">
      <KeadaanKosong v-if="!simper.length" teks="Belum ada SIMPER. SIMPER dibuat dari Mine Permit yang sudah terbit." />
      <section v-for="s in simper" :key="s.id" class="mn-simper-blok">
        <header class="mn-simper-kepala">
          <div>
            <Link :href="`/miners/simper/${s.id}`" class="mn-tautan font-semibold">{{ s.nomor || 'tanpa nomor' }}</Link>
            <span class="mn-redup"> · {{ s.jenisSim || 'jenis SIM belum diisi' }}{{ s.noSim ? ` · ${s.noSim}` : '' }}</span>
          </div>
          <div class="mn-aksi mn-aksi-kiri">
            <LencanaStatus :status="s.status" />
            <span v-if="!s.aktif" class="mn-lencana mn-l-abu">non-aktif</span>
            <LencanaMasaBerlaku :tanggal="s.efektif" :sisa="s.sisa" :sumber="s.sumber" />
            <TombolIkon ikon="mata" varian="garis" :href="`/miners/simper/${s.id}`" :judul="`Buka SIMPER ${s.nomor || ''}`" />
            <TombolIkon v-if="s.verifikasi" ikon="cek" varian="garis" :href="s.verifikasi" luar judul="Halaman verifikasi SIMPER" />
          </div>
        </header>
        <div class="mn-gulir">
          <table class="mn-tabel">
            <thead><tr><th v-for="k in kolomUnit" :key="k.kunci">{{ k.label }}</th></tr></thead>
            <tbody>
              <tr v-for="u in s.unit" :key="u.id">
                <td>{{ u.unit || '—' }}</td><td>{{ u.type || '—' }}</td>
                <td><span class="mn-lencana mn-l-terang">{{ u.authority || '—' }}</span></td><td>{{ u.asal || '—' }}</td>
              </tr>
              <tr v-if="!s.unit.length"><td :colspan="kolomUnit.length" class="mn-kosong">Belum ada unit pada SIMPER ini.</td></tr>
            </tbody>
          </table>
        </div>
        <ul v-if="s.ajuan.length" class="mn-ajuan-daftar" aria-label="Pengajuan lanjutan">
          <li v-for="a in s.ajuan" :key="a.id">
            <Link :href="`/miners/simper-lanjutan/${a.id}`" class="mn-tautan">{{ a.jenis }} {{ a.nomor || '' }}</Link>
            <span class="mn-redup">{{ tglId(a.tanggal) }}</span>
            <LencanaStatus :status="a.status" />
          </li>
        </ul>
      </section>
    </Kartu>

    <Kartu judul="Perpanjangan" keterangan="Mine Permit dan SIMPER">
      <ul v-if="permit.some((x) => x.perpanjangan.length)" class="mn-ajuan-daftar mb-4" aria-label="Pengajuan perpanjangan Mine Permit">
        <template v-for="x in permit" :key="x.id">
          <li v-for="pp in x.perpanjangan" :key="pp.id">
            <Link :href="`/miners/perpanjangan-permit/${pp.id}`" class="mn-tautan">Perpanjangan {{ pp.nomor || x.nomor }}</Link>
            <span class="mn-redup">{{ tglId(pp.tanggal) }}{{ pp.berlakuBaru ? ` → berlaku s/d ${tglId(pp.berlakuBaru)}` : '' }}</span>
            <LencanaStatus :status="pp.status" />
          </li>
        </template>
      </ul>
      <TabelData v-if="riwayat.length" judul="Riwayat masa berlaku" :jumlah="riwayat.length" :kolom="kolomRiwayat" :baris="riwayat">
        <template #sel-pada="{ nilai }">{{ tglId(nilai) }}</template>
        <template #sel-lama="{ nilai }">{{ tglId(nilai) }}</template>
        <template #sel-baru="{ nilai }">{{ tglId(nilai) }}</template>
      </TabelData>
      <KeadaanKosong v-if="!riwayat.length && !permit.some((x) => x.perpanjangan.length)"
                     teks="Belum ada perpanjangan. Ajukan dari menu Perpanjangan Permit atau Perpanjangan SIMPER bila masa berlakunya mendekati habis." />
    </Kartu>

    <Kartu judul="Berkas" :keterangan="`${berkas.length} berkas`">
      <KeadaanKosong v-if="!berkas.length" teks="Belum ada berkas terunggah untuk karyawan ini." />
      <ul v-else class="mn-berkas-daftar">
        <li v-for="(b, i) in berkas" :key="i">
          <span class="mn-redup">{{ b.kelompok }}</span>
          <ChipBerkas :href="b.url" :nama="b.label" :aksi="b.url ? 'Lihat' : 'Terkunci'" />
        </li>
      </ul>
    </Kartu>

    <TabelData v-if="kompetensi.length" judul="Kompetensi" :jumlah="kompetensi.length" :kolom="kolomKompetensi" :baris="kompetensi">
      <template #sel-sampai="{ baris: b }"><LencanaMasaBerlaku :tanggal="b.sampai" :sisa="b.sisa" /></template>
    </TabelData>
  </div>
</template>

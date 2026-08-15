<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';

defineOptions({ layout: BlankLayout });

const props = defineProps<{
  dok: any; dari: string; sampai: string;
  dasar: Record<string, number>;
  ringkas: Record<string, any>;
  tetapan: Record<string, any>;
  rencana: Array<Record<string, any>>;
  ukur: Array<Record<string, any>>;
  titik: Array<Record<string, any>>;
  tindak: Array<Record<string, any>>;
  kembali?: string;
}>();

const angka = (v: unknown, d = 0) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: d, minimumFractionDigits: d }).format(Number(v || 0));
const tanggal = (v: unknown) => v ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(String(v))) : '—';
const label = (v: string) => String(v || '').replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
const nilai = (v: unknown, d = 2) => v === null || v === undefined ? '—' : angka(v, d);

</script>

<template>
  <Head title="Laporan Pengeboran dan Peledakan" />

  <PrintShell title="Laporan Pengeboran dan Peledakan" :kembali="props.kembali">
    <div class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0">

      <table class="w-full border-collapse text-[10px] mb-5">
        <tbody><tr>
          <td class="border border-stone-300 p-2 w-[18%] text-center font-bold">{{ props.dok?.perusahaan || 'EQOHSEE' }}</td>
          <td class="border border-stone-300 p-2 text-center">
            <div class="text-[9px] tracking-wide text-stone-500">{{ props.dok?.jenis }}</div>
            <div class="font-bold">{{ props.dok?.judul }}</div>
          </td>
          <td class="border border-stone-300 p-0 w-[30%]">
            <table class="w-full border-collapse"><tbody>
              <tr><td class="border-b border-r border-stone-300 p-1">No. Dokumen</td><td class="border-b border-stone-300 p-1 font-semibold">{{ props.dok?.nomor }}</td></tr>
              <tr><td class="border-b border-r border-stone-300 p-1">Tgl Penerbitan</td><td class="border-b border-stone-300 p-1">{{ tanggal(props.dok?.terbit) }}</td></tr>
              <tr><td class="border-b border-r border-stone-300 p-1">Tgl Persetujuan</td><td class="border-b border-stone-300 p-1">{{ tanggal(props.dok?.setuju) }}</td></tr>
              <tr><td class="border-r border-stone-300 p-1">No. Revisi</td><td class="p-1">{{ props.dok?.revisi }}</td></tr>
            </tbody></table>
          </td>
        </tr></tbody>
      </table>

      <header class="text-center border-b border-stone-200 pb-4 mb-5">
        <h1 class="font-bold text-[16px] uppercase">Laporan Pengeboran dan Peledakan</h1>
        <p class="text-[11px] text-stone-500 mt-1">Periode {{ tanggal(props.dari) }} — {{ tanggal(props.sampai) }}</p>
        <p class="text-[10px] text-stone-400 mt-1">{{ props.dok?.divisi }} · {{ props.dok?.departemen }}</p>
      </header>

      <section class="mb-5 rounded border border-stone-300 p-3 text-[10px] print:rounded-none"
               :class="Number(props.dasar?.belumDitinjau) ? 'bg-amber-50' : ''">
        <b class="block mb-1">Dasar laporan</b>
        Disusun dari <b>{{ props.dasar?.disetujui ?? 0 }}</b> rencana peledakan yang telah disetujui,
        atas <b>{{ props.dasar?.titik ?? 0 }}</b> titik terlindung terdaftar.
        <template v-if="Number(props.dasar?.belumDitinjau)">
          <b>{{ props.dasar.belumDitinjau }}</b> rencana lain masih draf atau menunggu tinjauan dan tidak ikut dihitung.
        </template>
      </section>

            <h3 class="font-bold text-[13px] mb-2">A. Ringkasan Peledakan</h3>
      <table class="w-full border-collapse text-[11px] mb-6"><tbody>
        <tr v-for="b in [
          { k: 'Rencana disusun', v: String(props.ringkas.rencana ?? 0), s: 'Termasuk yang belum ditinjau' },
          { k: 'Terlaksana', v: String(props.ringkas.terlaksana ?? 0), s: 'Sudah punya catatan hasil' },
          { k: 'Volume terbongkar', v: `${angka(props.ringkas.volume)} bcm`, s: '' },
          { k: 'Bahan peledak', v: `${angka(props.ringkas.bahan)} kg`, s: '' },
          { k: 'Powder factor', v: props.ringkas.pf === null ? '—' : `${angka(props.ringkas.pf, 3)} kg/bcm`, s: 'Dari volume yang benar-benar terbongkar' },
          { k: 'Misfire', v: String(props.ringkas.misfire ?? 0), s: 'Peledakan dengan bahan peledak gagal meledak' },
          { k: 'Lemparan batu', v: String(props.ringkas.flyrock ?? 0), s: '' },
          { k: 'Getaran melampaui ambang', v: String(props.ringkas.getaranLewat ?? 0), s: 'Dari seluruh pengukuran' },
        ]" :key="b.k" class="border-b border-stone-200">
          <td class="p-2 w-[30%]">{{ b.k }}</td>
          <td class="p-2 font-semibold w-[20%]">{{ b.v }}</td>
          <td class="p-2 text-stone-500">{{ b.s }}</td>
        </tr>
      </tbody></table>

      <h3 class="font-bold text-[13px] mb-2">B. Tetapan Penjalaran Getaran</h3>
      <!--
        Dasar perkiraan disebutkan lebih dulu. Angka getaran tanpa
        tetapannya tidak dapat diperiksa siapa pun, dan tetapan umum
        tidak boleh dibaca seolah hasil pengukuran situs ini sendiri.
      -->
      <section class="mb-6 rounded border border-stone-300 p-3 text-[10px] print:rounded-none">
        <template v-if="props.tetapan?.dapatDipakai">
          Perkiraan memakai tetapan situs hasil kalibrasi <b>{{ props.tetapan.n }}</b> pengukuran:
          K = <b>{{ angka(props.tetapan.k, 0) }}</b>, β = <b>{{ angka(props.tetapan.beta, 3) }}</b>,
          R² = {{ angka(props.tetapan.r2, 3) }}. Rumus: PPV = K · (D/√W)^(−β), D dalam meter dan
          W isi bahan peledak per tundaan dalam kilogram.
        </template>
        <template v-else>
          Perkiraan memakai tetapan umum (K = 1140, β = 1,6). {{ props.tetapan?.alasan }}
          Tetapan umum meleset jauh antar jenis batuan dan tidak dapat disebut aman maupun konservatif.
        </template>
      </section>

      <h3 class="font-bold text-[13px] mb-2">C. Rencana yang Disetujui</h3>
      <table class="w-full border-collapse text-[10px] mb-6">
        <thead class="bg-stone-100">
          <tr>
            <th class="border border-stone-300 p-1.5 text-left">Kode</th>
            <th class="border border-stone-300 p-1.5 text-left">Tanggal</th>
            <th class="border border-stone-300 p-1.5 text-right">Lubang</th>
            <th class="border border-stone-300 p-1.5 text-right">Per tundaan (kg)</th>
            <th class="border border-stone-300 p-1.5 text-right">PF rencana</th>
            <th class="border border-stone-300 p-1.5 text-right">PF nyata</th>
            <th class="border border-stone-300 p-1.5 text-right">Radius aman (m)</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in props.rencana" :key="r.id">
            <td class="border border-stone-300 p-1.5 font-semibold">{{ r.kode }}</td>
            <td class="border border-stone-300 p-1.5">{{ tanggal(r.tanggal) }}</td>
            <td class="border border-stone-300 p-1.5 text-right">{{ r.geometri?.lubang }}</td>
            <td class="border border-stone-300 p-1.5 text-right">{{ nilai(r.bahan?.perTunda, 1) }}</td>
            <td class="border border-stone-300 p-1.5 text-right">{{ nilai(r.pfRencana, 3) }}</td>
            <td class="border border-stone-300 p-1.5 text-right">{{ nilai(r.pfNyata, 3) }}</td>
            <td class="border border-stone-300 p-1.5 text-right">{{ nilai(r.radiusLemparan, 0) }}</td>
          </tr>
          <tr v-if="!props.rencana.length"><td colspan="7" class="border border-stone-300 p-3 text-center text-stone-400">Belum ada rencana disetujui.</td></tr>
        </tbody>
      </table>

      <h3 class="font-bold text-[13px] mb-2">D. Getaran Terukur</h3>
      <table class="w-full border-collapse text-[10px] mb-6">
        <thead class="bg-stone-100">
          <tr>
            <th class="border border-stone-300 p-1.5 text-left">Peledakan</th>
            <th class="border border-stone-300 p-1.5 text-left">Titik</th>
            <th class="border border-stone-300 p-1.5 text-right">Jarak (m)</th>
            <th class="border border-stone-300 p-1.5 text-right">PPV (mm/s)</th>
            <th class="border border-stone-300 p-1.5 text-right">Ambang</th>
            <th class="border border-stone-300 p-1.5 text-left">Ket</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="u in props.ukur" :key="u.id">
            <td class="border border-stone-300 p-1.5">{{ u.rencana }}</td>
            <td class="border border-stone-300 p-1.5">{{ u.titik }}</td>
            <td class="border border-stone-300 p-1.5 text-right">{{ angka(u.jarak_m) }}</td>
            <td class="border border-stone-300 p-1.5 text-right font-semibold">{{ angka(u.ppv, 3) }}</td>
            <td class="border border-stone-300 p-1.5 text-right">{{ angka(u.ambang, 1) }}</td>
            <td class="border border-stone-300 p-1.5 font-semibold">{{ u.melampaui ? 'Melampaui' : 'Taat' }}</td>
          </tr>
          <tr v-if="!props.ukur.length"><td colspan="6" class="border border-stone-300 p-3 text-center text-stone-400">Belum ada pengukuran getaran.</td></tr>
        </tbody>
      </table>

      <h3 class="font-bold text-[13px] mb-2">E. Titik Terlindung</h3>
      <table class="w-full border-collapse text-[10px] mb-6">
        <thead class="bg-stone-100">
          <tr>
            <th class="border border-stone-300 p-1.5 text-left">Kode</th>
            <th class="border border-stone-300 p-1.5 text-left">Nama</th>
            <th class="border border-stone-300 p-1.5 text-left">Jenis</th>
            <th class="border border-stone-300 p-1.5 text-right">Ambang (mm/s)</th>
            <th class="border border-stone-300 p-1.5 text-left">Acuan</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in props.titik" :key="t.id">
            <td class="border border-stone-300 p-1.5 font-semibold">{{ t.kode }}</td>
            <td class="border border-stone-300 p-1.5">{{ t.nama }}</td>
            <td class="border border-stone-300 p-1.5">{{ label(t.jenis) }}</td>
            <td class="border border-stone-300 p-1.5 text-right">
              {{ angka(t.ambang, 1) }}<template v-if="!t.ambangDitetapkan"> (bawaan)</template>
            </td>
            <td class="border border-stone-300 p-1.5">{{ t.acuan || '— belum diisi —' }}</td>
          </tr>
        </tbody>
      </table>

      <h3 class="font-bold text-[13px] mb-2">F. Tindak Lanjut Terbuka</h3>
      <table class="w-full border-collapse text-[10px] mb-6">
        <thead class="bg-stone-100">
          <tr>
            <th class="border border-stone-300 p-1.5 text-left">Uraian</th>
            <th class="border border-stone-300 p-1.5 text-left">Penanggung jawab</th>
            <th class="border border-stone-300 p-1.5 text-left">Target</th>
            <th class="border border-stone-300 p-1.5 text-left">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in props.tindak" :key="t.id">
            <td class="border border-stone-300 p-1.5">{{ t.judul }}</td>
            <td class="border border-stone-300 p-1.5">{{ t.penanggung_jawab || '—' }}</td>
            <td class="border border-stone-300 p-1.5">{{ tanggal(t.target_selesai) }}</td>
            <td class="border border-stone-300 p-1.5">{{ label(t.status) }}{{ t.terlambat ? ' · terlambat' : '' }}</td>
          </tr>
          <tr v-if="!props.tindak.length"><td colspan="4" class="border border-stone-300 p-3 text-center text-stone-400">Tidak ada tindak lanjut terbuka.</td></tr>
        </tbody>
      </table>

      <section class="mt-8 grid grid-cols-3 gap-6 text-[10px] break-inside-avoid">
        <div v-for="p in ['Disusun oleh', 'Diperiksa oleh', 'Disetujui oleh']" :key="p" class="text-center">
          <p class="mb-14">{{ p }}</p>
          <div class="border-t border-stone-400 pt-1">
            <p class="text-stone-500">{{ p === 'Disusun oleh' ? 'Juru Ledak' : p === 'Diperiksa oleh' ? 'Pengawas Peledakan' : 'Kepala Teknik Tambang' }}</p>
          </div>
        </div>
      </section>
    </div>
  </PrintShell>
</template>

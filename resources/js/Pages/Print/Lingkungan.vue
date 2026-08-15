<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';

defineOptions({ layout: BlankLayout });

const props = defineProps<{
  dok: any; dari: string; sampai: string;
  dasar: Record<string, number>;
  neraca: Record<string, any>;
  nisbah: number | null;
  jaminan: Record<string, any>;
  area: Array<Record<string, any>>;
  pantau: Array<Record<string, any>>;
  tindak: Array<Record<string, any>>;
  kembali?: string;
}>();

const angka = (v: unknown, d = 0) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: d, minimumFractionDigits: d }).format(Number(v || 0));
const tanggal = (v: unknown) => v ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(String(v))) : '—';
const label = (v: string) => String(v || '').replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
const rupiah = (v: unknown) => `Rp ${angka(v)}`;

const langgar = props.pantau.filter((x) => x.melanggar);
</script>

<template>
  <Head title="Laporan Pengelolaan Lingkungan dan Reklamasi" />

  <PrintShell title="Laporan Pengelolaan Lingkungan dan Reklamasi" :kembali="props.kembali">
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
        <h1 class="font-bold text-[16px] uppercase">Laporan Pengelolaan Lingkungan dan Reklamasi</h1>
        <p class="text-[11px] text-stone-500 mt-1">Periode {{ tanggal(props.dari) }} — {{ tanggal(props.sampai) }}</p>
        <p class="text-[10px] text-stone-400 mt-1">{{ props.dok?.divisi }} · {{ props.dok?.departemen }}</p>
      </header>

      <section class="mb-5 rounded border border-stone-300 p-3 text-[10px] print:rounded-none"
               :class="Number(props.dasar?.belumDitinjau) ? 'bg-amber-50' : ''">
        <b class="block mb-1">Dasar laporan</b>
        Disusun dari <b>{{ props.dasar?.disetujui ?? 0 }}</b> laporan kemajuan yang telah ditinjau dan disetujui,
        atas <b>{{ props.dasar?.area ?? 0 }}</b> petak lahan terdaftar.
        <template v-if="Number(props.dasar?.belumDitinjau)">
          <b>{{ props.dasar.belumDitinjau }}</b> kemajuan lain masih draf atau menunggu tinjauan dan tidak ikut dihitung.
        </template>
      </section>

      <h3 class="font-bold text-[13px] mb-2">A. Neraca Lahan</h3>
      <!--
        Lahan yang masih ditambang disebut terpisah. Tanpa pemisahan itu,
        pembaca laporan menghitung seluruh lahan terganggu sebagai
        tunggakan reklamasi, padahal sebagiannya belum jatuh tempo.
      -->
      <table class="w-full border-collapse text-[11px] mb-6"><tbody>
        <tr v-for="b in [
          { k: 'Lahan terganggu', v: `${angka(props.neraca.terganggu, 2)} ha`, s: 'Seluruh petak terdaftar' },
          { k: 'Masih ditambang', v: `${angka(props.neraca.aktif, 2)} ha`, s: 'Belum jatuh tempo reklamasi' },
          { k: 'Wajib direklamasi', v: `${angka(props.neraca.wajibReklamasi, 2)} ha`, s: 'Terganggu dikurangi yang masih ditambang' },
          { k: 'Selesai direklamasi', v: `${angka(props.neraca.selesai, 2)} ha`, s: `Capaian ${props.neraca.persenSelesai === null ? '—' : angka(props.neraca.persenSelesai, 1) + '%'} terhadap yang wajib` },
          { k: 'Reklamasi berjalan', v: `${angka(props.neraca.berjalan, 2)} ha`, s: 'Sudah dimulai, belum tuntas' },
          { k: 'Menunggu dimulai', v: `${angka(props.neraca.menunggu, 2)} ha`, s: 'Berhenti ditambang, belum disentuh' },
          { k: 'Menganggur melewati batas', v: `${angka(props.neraca.telat, 2)} ha`, s: `Terlama ${props.neraca.umurTerlama ?? 0} hari` },
          { k: 'Nisbah reklamasi', v: props.nisbah === null ? '—' : angka(props.nisbah, 2), s: 'Luas diselesaikan berbanding luas dibuka; di bawah 1 berarti tunggakan bertambah' },
        ]" :key="b.k" class="border-b border-stone-200">
          <td class="p-2 w-[30%]">{{ b.k }}</td>
          <td class="p-2 font-semibold w-[20%]">{{ b.v }}</td>
          <td class="p-2 text-stone-500">{{ b.s }}</td>
        </tr>
      </tbody></table>

      <h3 class="font-bold text-[13px] mb-2">B. Keadaan Tiap Petak</h3>
      <table class="w-full border-collapse text-[10px] mb-6">
        <thead class="bg-stone-100">
          <tr>
            <th class="border border-stone-300 p-1.5 text-left">Kode</th>
            <th class="border border-stone-300 p-1.5 text-left">Jenis</th>
            <th class="border border-stone-300 p-1.5 text-right">Luas (ha)</th>
            <th class="border border-stone-300 p-1.5 text-left">Tahapan</th>
            <th class="border border-stone-300 p-1.5 text-right">Menganggur</th>
            <th class="border border-stone-300 p-1.5 text-right">Pohon</th>
            <th class="border border-stone-300 p-1.5 text-right">Tumbuh</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="a in props.area" :key="a.id">
            <td class="border border-stone-300 p-1.5 font-semibold">{{ a.kode }}</td>
            <td class="border border-stone-300 p-1.5">{{ label(a.jenis) }}</td>
            <td class="border border-stone-300 p-1.5 text-right">{{ angka(a.luas_ha, 2) }}</td>
            <td class="border border-stone-300 p-1.5">{{ a.tahapLabel }}</td>
            <td class="border border-stone-300 p-1.5 text-right">
              {{ a.masihDitambang ? 'masih ditambang' : (a.mengangurHari === null ? '—' : `${a.mengangurHari} hari`) }}
            </td>
            <td class="border border-stone-300 p-1.5 text-right">{{ angka(a.pohonDitanam) }}</td>
            <td class="border border-stone-300 p-1.5 text-right">
              {{ a.tingkatTumbuh === null || a.tingkatTumbuh === undefined ? '—' : `${angka(a.tingkatTumbuh, 1)}%` }}
            </td>
          </tr>
          <tr v-if="!props.area.length"><td colspan="7" class="border border-stone-300 p-3 text-center text-stone-400">Belum ada petak terdaftar.</td></tr>
        </tbody>
      </table>

      <h3 class="font-bold text-[13px] mb-2">C. Jaminan Reklamasi</h3>
      <table class="w-full border-collapse text-[11px] mb-6"><tbody>
        <tr class="border-b border-stone-200"><td class="p-2 w-[30%]">Kebutuhan</td><td class="p-2 font-semibold">{{ rupiah(props.jaminan?.butuh) }}</td></tr>
        <tr class="border-b border-stone-200"><td class="p-2">Telah ditempatkan</td><td class="p-2 font-semibold">{{ rupiah(props.jaminan?.ditempatkan) }}</td></tr>
        <tr class="border-b border-stone-200">
          <td class="p-2">Selisih</td>
          <td class="p-2 font-semibold">{{ rupiah(props.jaminan?.selisih) }} — {{ props.jaminan?.cukup ? 'mencukupi' : 'belum mencukupi' }}</td>
        </tr>
      </tbody></table>

      <h3 class="font-bold text-[13px] mb-2">D. Pemantauan Mutu Lingkungan</h3>
      <!--
        Ambang dan dasar hukumnya ikut dicetak. Angka hasil uji tanpa
        ambangnya tidak dapat dinilai pembaca, dan ambang tanpa dasar
        hukumnya tidak dapat diperiksa inspektur.
      -->
      <table class="w-full border-collapse text-[10px] mb-3">
        <thead class="bg-stone-100">
          <tr>
            <th class="border border-stone-300 p-1.5 text-left">Tanggal</th>
            <th class="border border-stone-300 p-1.5 text-left">Titik</th>
            <th class="border border-stone-300 p-1.5 text-left">Parameter</th>
            <th class="border border-stone-300 p-1.5 text-right">Hasil</th>
            <th class="border border-stone-300 p-1.5 text-left">Baku mutu</th>
            <th class="border border-stone-300 p-1.5 text-left">Acuan</th>
            <th class="border border-stone-300 p-1.5 text-left">Ket</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="x in props.pantau" :key="x.id">
            <td class="border border-stone-300 p-1.5">{{ x.tanggal }}</td>
            <td class="border border-stone-300 p-1.5">{{ x.titik }}</td>
            <td class="border border-stone-300 p-1.5">{{ x.parameter }}</td>
            <td class="border border-stone-300 p-1.5 text-right font-semibold">{{ angka(x.nilai, 3) }} {{ x.satuan }}</td>
            <td class="border border-stone-300 p-1.5">{{ x.rentang }}</td>
            <td class="border border-stone-300 p-1.5">{{ x.acuan || '—' }}</td>
            <td class="border border-stone-300 p-1.5 font-semibold">{{ x.melanggar ? 'Melampaui' : 'Taat' }}</td>
          </tr>
          <tr v-if="!props.pantau.length"><td colspan="7" class="border border-stone-300 p-3 text-center text-stone-400">Tidak ada hasil uji disetujui pada periode ini.</td></tr>
        </tbody>
      </table>
      <p class="text-[10px] text-stone-600 mb-6">
        <b>{{ langgar.length }}</b> dari <b>{{ props.pantau.length }}</b> hasil uji melampaui baku mutu.
      </p>

      <h3 class="font-bold text-[13px] mb-2">E. Tindak Lanjut Terbuka</h3>
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
            <p class="text-stone-500">{{ p === 'Disusun oleh' ? 'Pengawas Lingkungan' : p === 'Diperiksa oleh' ? 'Manajer Lingkungan' : 'Kepala Teknik Tambang' }}</p>
          </div>
        </div>
      </section>
    </div>
  </PrintShell>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';

defineOptions({ layout: BlankLayout });

const props = defineProps<{
  dok: any; dari: string; sampai: string;
  dasar: Record<string, number>;
  ringkas: Record<string, any>;
  lereng: Array<Record<string, any>>;
  bacaan: Array<Record<string, any>>;
  tindak: Array<Record<string, any>>;
  kembali?: string;
}>();

const angka = (v: unknown, d = 0) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: d, minimumFractionDigits: d }).format(Number(v || 0));
const tanggal = (v: unknown) => v ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(String(v))) : '—';
const label = (v: string) => String(v || '').replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
const nilai = (v: unknown, d = 2) => v === null || v === undefined ? '—' : angka(v, d);

const arah: Record<string, string> = {
  menderas: 'Menderas', melambat: 'Melambat', tetap: 'Tetap', 'belum-cukup': 'Belum cukup titik',
};
</script>

<template>
  <Head title="Laporan Pemantauan Kestabilan Lereng" />

  <PrintShell title="Laporan Pemantauan Kestabilan Lereng" :kembali="props.kembali">
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
        <h1 class="font-bold text-[16px] uppercase">Laporan Pemantauan Kestabilan Lereng</h1>
        <p class="text-[11px] text-stone-500 mt-1">Periode {{ tanggal(props.dari) }} — {{ tanggal(props.sampai) }}</p>
        <p class="text-[10px] text-stone-400 mt-1">{{ props.dok?.divisi }} · {{ props.dok?.departemen }}</p>
      </header>

      <!--
        Batasan kewenangan dicetak di halaman pertama, bukan di catatan
        kaki. Laporan ini dapat dibaca pengawas, kontraktor, dan inspektur
        tambang, dan angka pemantauan mudah salah dibaca sebagai
        pernyataan kestabilan bila batasnya tidak disebut lebih dulu.
      -->
      <section class="mb-4 rounded border-2 border-stone-400 p-3 text-[10px] print:rounded-none">
        <b class="block mb-1">Batasan laporan</b>
        Laporan ini memuat hasil pemantauan dan turunannya, bukan hasil kajian kestabilan lereng.
        Perkiraan waktu dari kebalikan laju bersifat indikatif dan berlaku hanya selama pola gerakan
        tidak berubah. Faktor keamanan yang tercantum diambil dari dokumen kajian geoteknik yang berlaku,
        beserta penyusun dan tanggalnya. <b>Penetapan aman atau tidaknya sebuah lereng tetap menjadi
        kewenangan tenaga kompeten geoteknik.</b>
      </section>

      <section class="mb-5 rounded border border-stone-300 p-3 text-[10px] print:rounded-none"
               :class="Number(props.dasar?.belumDitinjau) ? 'bg-amber-50' : ''">
        <b class="block mb-1">Dasar laporan</b>
        Disusun dari <b>{{ props.dasar?.disetujui ?? 0 }}</b> pembacaan yang telah ditinjau dan disetujui,
        atas <b>{{ props.dasar?.lereng ?? 0 }}</b> lereng aktif.
        <template v-if="Number(props.dasar?.belumDitinjau)">
          <b>{{ props.dasar.belumDitinjau }}</b> pembacaan lain masih draf atau menunggu tinjauan dan tidak ikut dihitung.
        </template>
      </section>

      <h3 class="font-bold text-[13px] mb-2">A. Ringkasan Pemantauan</h3>
      <table class="w-full border-collapse text-[11px] mb-6"><tbody>
        <tr v-for="b in [
          { k: 'Lereng dipantau', v: String(props.ringkas.lereng ?? 0), s: 'Berstatus aktif pada periode ini' },
          { k: 'Laju tertinggi', v: props.ringkas.lajuTertinggi === null ? '—' : `${angka(props.ringkas.lajuTertinggi, 2)} mm/hari`, s: 'Pembacaan terakhir tiap lereng' },
          { k: 'Perkiraan terdekat', v: props.ringkas.ttfTerdekat === null ? 'Tidak ada' : `${angka(props.ringkas.ttfTerdekat, 1)} hari`, s: 'Dari kebalikan laju; indikatif' },
          { k: 'Geometri menyimpang', v: String(props.ringkas.penyimpangan ?? 0), s: 'Terbangun keluar dari toleransi rancangan' },
          { k: 'Pembacaan bergejala', v: String(props.ringkas.gejala ?? 0), s: 'Retakan, gugur batu, atau rembesan tercatat' },
          { k: 'Alat pantau rusak', v: String(props.ringkas.instrumenRusak ?? 0), s: 'Lereng yang berhenti terpantau tampak tenang' },
        ]" :key="b.k" class="border-b border-stone-200">
          <td class="p-2 w-[30%]">{{ b.k }}</td>
          <td class="p-2 font-semibold w-[20%]">{{ b.v }}</td>
          <td class="p-2 text-stone-500">{{ b.s }}</td>
        </tr>
      </tbody></table>

      <h3 class="font-bold text-[13px] mb-2">B. Keadaan Tiap Lereng</h3>
      <table class="w-full border-collapse text-[10px] mb-6">
        <thead class="bg-stone-100">
          <tr>
            <th class="border border-stone-300 p-1.5 text-left">Kode</th>
            <th class="border border-stone-300 p-1.5 text-left">Jenis</th>
            <th class="border border-stone-300 p-1.5 text-right">Laju (mm/h)</th>
            <th class="border border-stone-300 p-1.5 text-left">Kecenderungan</th>
            <th class="border border-stone-300 p-1.5 text-left">Tingkat</th>
            <th class="border border-stone-300 p-1.5 text-right">FK acuan</th>
            <th class="border border-stone-300 p-1.5 text-left">Perkiraan</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="l in props.lereng" :key="l.id">
            <td class="border border-stone-300 p-1.5 font-semibold">{{ l.kode }}</td>
            <td class="border border-stone-300 p-1.5">{{ label(l.jenis) }}</td>
            <td class="border border-stone-300 p-1.5 text-right">{{ nilai(l.gerakan?.laju) }}</td>
            <td class="border border-stone-300 p-1.5">{{ arah[l.gerakan?.tren?.arah] || '—' }}</td>
            <td class="border border-stone-300 p-1.5">{{ label(l.gerakan?.tingkat) }}</td>
            <td class="border border-stone-300 p-1.5 text-right">{{ l.rencana?.fk ?? '—' }}</td>
            <td class="border border-stone-300 p-1.5">
              <template v-if="l.gerakan?.ttf?.dapatDipakai">
                ± {{ angka(l.gerakan.ttf.hari, 1) }} hari (R² {{ angka(l.gerakan.ttf.r2, 2) }})
              </template>
              <template v-else>—</template>
            </td>
          </tr>
          <tr v-if="!props.lereng.length"><td colspan="7" class="border border-stone-300 p-3 text-center text-stone-400">Belum ada lereng aktif.</td></tr>
        </tbody>
      </table>

      <h3 class="font-bold text-[13px] mb-2">C. Penyimpangan Geometri Terbangun</h3>
      <table class="w-full border-collapse text-[10px] mb-6">
        <thead class="bg-stone-100">
          <tr>
            <th class="border border-stone-300 p-1.5 text-left">Lereng</th>
            <th class="border border-stone-300 p-1.5 text-left">Hal</th>
            <th class="border border-stone-300 p-1.5 text-right">Rancangan</th>
            <th class="border border-stone-300 p-1.5 text-right">Terbangun</th>
            <th class="border border-stone-300 p-1.5 text-right">Selisih</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="l in props.lereng" :key="`g-${l.id}`">
            <tr v-for="s in l.penyimpangan || []" :key="`${l.id}-${s.hal}`">
              <td class="border border-stone-300 p-1.5 font-semibold">{{ l.kode }}</td>
              <td class="border border-stone-300 p-1.5">{{ s.hal }}</td>
              <td class="border border-stone-300 p-1.5 text-right">{{ s.rencana }}{{ s.satuan }}</td>
              <td class="border border-stone-300 p-1.5 text-right font-semibold">{{ s.aktual }}{{ s.satuan }}</td>
              <td class="border border-stone-300 p-1.5 text-right">{{ s.selisih > 0 ? '+' : '' }}{{ s.selisih }}{{ s.satuan }}</td>
            </tr>
          </template>
          <tr v-if="!Number(props.ringkas.penyimpangan)">
            <td colspan="5" class="border border-stone-300 p-3 text-center text-stone-400">
              Seluruh lereng terbangun dalam toleransi rancangannya.
            </td>
          </tr>
        </tbody>
      </table>

      <h3 class="font-bold text-[13px] mb-2">D. Acuan Kajian Geoteknik</h3>
      <table class="w-full border-collapse text-[10px] mb-6">
        <thead class="bg-stone-100">
          <tr>
            <th class="border border-stone-300 p-1.5 text-left">Lereng</th>
            <th class="border border-stone-300 p-1.5 text-left">Disusun oleh</th>
            <th class="border border-stone-300 p-1.5 text-left">Tanggal kajian</th>
            <th class="border border-stone-300 p-1.5 text-right">FK</th>
            <th class="border border-stone-300 p-1.5 text-right">PPA</th>
            <th class="border border-stone-300 p-1.5 text-right">Sisa masa</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="l in props.lereng" :key="`k-${l.id}`">
            <td class="border border-stone-300 p-1.5 font-semibold">{{ l.kode }}</td>
            <td class="border border-stone-300 p-1.5">{{ l.kajian?.oleh || '— belum dicatat —' }}</td>
            <td class="border border-stone-300 p-1.5">{{ tanggal(l.kajian?.tanggal) }}</td>
            <td class="border border-stone-300 p-1.5 text-right">{{ l.rencana?.fk ?? '—' }}</td>
            <td class="border border-stone-300 p-1.5 text-right">{{ l.rencana?.ppa === null || l.rencana?.ppa === undefined ? '—' : `${l.rencana.ppa}%` }}</td>
            <td class="border border-stone-300 p-1.5 text-right">
              {{ l.kajian?.sisaHari === null || l.kajian?.sisaHari === undefined ? '—' : `${l.kajian.sisaHari} hari` }}
            </td>
          </tr>
        </tbody>
      </table>

      <h3 class="font-bold text-[13px] mb-2">E. Tindak Lanjut Terbuka</h3>
      <table class="w-full border-collapse text-[10px] mb-6">
        <thead class="bg-stone-100">
          <tr>
            <th class="border border-stone-300 p-1.5 text-left">Uraian</th>
            <th class="border border-stone-300 p-1.5 text-left">Penanggung jawab</th>
            <th class="border border-stone-300 p-1.5 text-left">Target</th>
            <th class="border border-stone-300 p-1.5 text-left">Prioritas</th>
            <th class="border border-stone-300 p-1.5 text-left">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in props.tindak" :key="t.id">
            <td class="border border-stone-300 p-1.5">{{ t.judul }}</td>
            <td class="border border-stone-300 p-1.5">{{ t.penanggung_jawab || '—' }}</td>
            <td class="border border-stone-300 p-1.5">{{ tanggal(t.target_selesai) }}</td>
            <td class="border border-stone-300 p-1.5">{{ label(t.prioritas) }}</td>
            <td class="border border-stone-300 p-1.5">{{ label(t.status) }}{{ t.terlambat ? ' · terlambat' : '' }}</td>
          </tr>
          <tr v-if="!props.tindak.length"><td colspan="5" class="border border-stone-300 p-3 text-center text-stone-400">Tidak ada tindak lanjut terbuka.</td></tr>
        </tbody>
      </table>

      <section class="mt-8 grid grid-cols-3 gap-6 text-[10px] break-inside-avoid">
        <div v-for="p in ['Disusun oleh', 'Diperiksa oleh', 'Disetujui oleh']" :key="p" class="text-center">
          <p class="mb-14">{{ p }}</p>
          <div class="border-t border-stone-400 pt-1">
            <p class="text-stone-500">{{ p === 'Disusun oleh' ? 'Pengawas Geoteknik' : p === 'Diperiksa oleh' ? 'Tenaga Kompeten Geoteknik' : 'Kepala Teknik Tambang' }}</p>
          </div>
        </div>
      </section>
    </div>
  </PrintShell>
</template>

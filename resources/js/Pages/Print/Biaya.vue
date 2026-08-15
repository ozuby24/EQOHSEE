<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';

defineOptions({ layout: BlankLayout });

const props = defineProps<{
  dok: any; tahun: number;
  produksi: Record<string, any>;
  total: Record<string, any>;
  baca: Record<string, any>;
  akun: Array<Record<string, any>>;
  kelompok: Array<Record<string, any>>;
  bulan: Array<Record<string, any>>;
  bulanTerisi: number;
  menunggu: number;
  tindak: Array<Record<string, any>>;
  kembali?: string;
}>();

const angka = (v: unknown, d = 0) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: d, minimumFractionDigits: d }).format(Number(v || 0));
const rp = (v: unknown) => v === null || v === undefined ? '—' : `Rp ${angka(v)}`;
const tanggal = (v: unknown) => v ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(String(v))) : '—';
const label = (v: string) => String(v || '').replaceAll('-', ' ').replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
const namaBulan = (b: number) => ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'][b] || String(b);
</script>

<template>
  <Head title="Laporan Pengendalian Biaya Operasi" />

  <PrintShell title="Laporan Pengendalian Biaya Operasi" :kembali="props.kembali">
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
        <h1 class="font-bold text-[16px] uppercase">Laporan Pengendalian Biaya Operasi</h1>
        <p class="text-[11px] text-stone-500 mt-1">Tahun anggaran {{ props.tahun }}</p>
        <p class="text-[10px] text-stone-400 mt-1">{{ props.dok?.divisi }} · {{ props.dok?.departemen }}</p>
      </header>

      <!--
        Batas modul disebut sebelum angka mana pun dibaca. Laporan yang
        dikira laporan keuangan akan dibandingkan dengan buku besar, dan
        selisihnya akan dianggap kesalahan salah satu pihak.
      -->
      <section class="mb-5 rounded border border-stone-300 p-3 text-[10px] print:rounded-none"
               :class="Number(props.menunggu) ? 'bg-amber-50' : ''">
        <b class="block mb-1">Dasar laporan</b>
        Disusun untuk pengendalian operasi, bukan sebagai laporan keuangan; yang mengikat tetap catatan
        keuangan perusahaan. Hanya realisasi yang sudah disetujui yang dihitung, dari
        <b>{{ props.bulanTerisi ?? 0 }}</b> bulan yang datanya sudah lengkap.
        <template v-if="Number(props.menunggu)">
          <b>{{ props.menunggu }}</b> baris masih menunggu tinjauan dan tidak ikut dihitung — seluruh angka
          di bawah karenanya menunjukkan biaya yang lebih rendah daripada yang sebenarnya.
        </template>
        Tonase pembagi diambil dari catatan Mine Operations yang telah disetujui.
      </section>

      <h3 class="font-bold text-[13px] mb-2">A. Ringkasan Anggaran dan Realisasi</h3>
      <table class="w-full border-collapse text-[11px] mb-6"><tbody>
        <tr v-for="b in [
          { k: 'Pagu anggaran', v: rp(props.total.anggaran), s: 'Dari RKAB yang disahkan' },
          { k: 'Realisasi disetujui', v: rp(props.total.realisasi), s: '' },
          { k: 'Sisa pagu', v: rp(props.total.sisa), s: '' },
          { k: 'Serapan', v: props.total.serapan === null ? '—' : `${angka(props.total.serapan, 1)}%`, s: props.baca?.label },
          { k: 'Kemajuan produksi', v: props.produksi.kemajuan === null ? '—' : `${angka(props.produksi.kemajuan, 1)}%`, s: 'Tonase nyata terhadap target tahunan' },
          { k: 'Biaya per ton', v: props.total.perTon === null ? '—' : rp(props.total.perTon), s: `Pagu ${props.total.perTonAnggaran === null ? '—' : rp(props.total.perTonAnggaran)} per ton` },
          { k: 'Biaya per BCM', v: props.total.perBcm === null ? '—' : rp(props.total.perBcm), s: 'Denominator yang tidak terpengaruh pergeseran nisbah' },
          { k: 'Proyeksi akhir tahun', v: rp(props.total.proyeksi), s: `Laju rata-rata ${props.bulanTerisi ?? 0} bulan yang lengkap` },
        ]" :key="b.k" class="border-b border-stone-200">
          <td class="p-2 w-[32%]">{{ b.k }}</td>
          <td class="p-2 font-semibold w-[24%]">{{ b.v }}</td>
          <td class="p-2 text-stone-500">{{ b.s }}</td>
        </tr>
      </tbody></table>

      <h3 class="font-bold text-[13px] mb-2">B. Pemecahan Selisih</h3>
      <section class="mb-3 rounded border border-stone-300 p-3 text-[10px] print:rounded-none">
        Anggaran diluweskan lebih dulu ke tonase yang benar-benar terjadi, lalu selisihnya dipecah dua:
        bagian <b>volume</b> yang terjelaskan oleh banyaknya material yang dipindahkan, dan bagian
        <b>tarif</b> yang tidak. Hanya bagian kedua yang boleh dibaca sebagai kinerja — biaya yang naik
        karena material yang dipindahkan lebih banyak bukan pemborosan.
      </section>
      <table class="w-full border-collapse text-[11px] mb-6"><tbody>
        <tr v-for="b in [
          { k: 'Anggaran diluweskan', v: props.total.varians?.anggaranLuwes === null ? '—' : rp(props.total.varians?.anggaranLuwes) },
          { k: 'Selisih bagian volume', v: props.total.varians?.volume === null ? '—' : rp(props.total.varians?.volume) },
          { k: 'Selisih bagian tarif', v: props.total.varians?.tarif === null ? '—' : rp(props.total.varians?.tarif) },
          { k: 'Selisih total', v: rp(props.total.varians?.total) },
        ]" :key="b.k" class="border-b border-stone-200">
          <td class="p-2 w-[32%]">{{ b.k }}</td>
          <td class="p-2 font-semibold">{{ b.v }}</td>
        </tr>
      </tbody></table>

      <h3 class="font-bold text-[13px] mb-2">C. Produksi Pembagi</h3>
      <table class="w-full border-collapse text-[11px] mb-6"><tbody>
        <tr v-for="b in [
          { k: 'Tonase nyata', v: `${angka(props.produksi.tonNyata)} ton`, s: `Rencana ${angka(props.produksi.tonRencana)} ton` },
          { k: 'Overburden nyata', v: `${angka(props.produksi.bcmNyata)} bcm`, s: `Rencana ${angka(props.produksi.bcmRencana)} bcm` },
          { k: 'Nisbah kupas', v: props.produksi.srNyata === null ? '—' : angka(props.produksi.srNyata, 2), s: props.produksi.srRencana === null ? 'Rencana —' : `Rencana ${angka(props.produksi.srRencana, 2)}` },
          { k: 'Pergeseran nisbah', v: props.produksi.selisihSr === null ? '—' : `${angka(props.produksi.selisihSr, 1)}%`, s: 'Di atas 10%, biaya per ton tidak sebanding antar periode' },
        ]" :key="b.k" class="border-b border-stone-200">
          <td class="p-2 w-[32%]">{{ b.k }}</td>
          <td class="p-2 font-semibold w-[24%]">{{ b.v }}</td>
          <td class="p-2 text-stone-500">{{ b.s }}</td>
        </tr>
      </tbody></table>

      <h3 class="font-bold text-[13px] mb-2">D. Rincian per Akun</h3>
      <table class="w-full border-collapse text-[10px] mb-6">
        <thead class="bg-stone-100">
          <tr>
            <th class="border border-stone-300 p-1.5 text-left">Akun</th>
            <th class="border border-stone-300 p-1.5 text-left">Kelompok</th>
            <th class="border border-stone-300 p-1.5 text-right">Pagu</th>
            <th class="border border-stone-300 p-1.5 text-right">Realisasi</th>
            <th class="border border-stone-300 p-1.5 text-right">Serapan</th>
            <th class="border border-stone-300 p-1.5 text-right">Volume</th>
            <th class="border border-stone-300 p-1.5 text-right">Tarif</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="a in props.akun" :key="a.akunId">
            <td class="border border-stone-300 p-1.5"><b>{{ a.akun }}</b> — {{ a.nama }}</td>
            <td class="border border-stone-300 p-1.5">{{ label(a.kelompok) }}</td>
            <td class="border border-stone-300 p-1.5 text-right">{{ rp(a.anggaran) }}</td>
            <td class="border border-stone-300 p-1.5 text-right font-semibold">{{ rp(a.realisasi) }}</td>
            <td class="border border-stone-300 p-1.5 text-right">{{ a.serapan === null ? '—' : `${angka(a.serapan, 1)}%` }}</td>
            <td class="border border-stone-300 p-1.5 text-right">{{ a.varians?.volume === null ? '—' : rp(a.varians?.volume) }}</td>
            <td class="border border-stone-300 p-1.5 text-right">{{ a.varians?.tarif === null ? '—' : rp(a.varians?.tarif) }}</td>
          </tr>
          <tr v-if="!props.akun.length"><td colspan="7" class="border border-stone-300 p-3 text-center text-stone-400">Belum ada anggaran maupun realisasi.</td></tr>
        </tbody>
      </table>

      <h3 class="font-bold text-[13px] mb-2">E. Realisasi per Bulan</h3>
      <table class="w-full border-collapse text-[10px] mb-6">
        <thead class="bg-stone-100">
          <tr>
            <th class="border border-stone-300 p-1.5 text-left">Bulan</th>
            <th class="border border-stone-300 p-1.5 text-right">Realisasi disetujui</th>
            <th class="border border-stone-300 p-1.5 text-right">Menunggu tinjauan</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="b in props.bulan" :key="b.bulan">
            <td class="border border-stone-300 p-1.5">{{ namaBulan(b.bulan) }}</td>
            <td class="border border-stone-300 p-1.5 text-right">{{ rp(b.realisasi) }}</td>
            <td class="border border-stone-300 p-1.5 text-right">{{ b.menunggu || '—' }}</td>
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
            <p class="text-stone-500">{{ p === 'Disusun oleh' ? 'Pengendali Biaya' : p === 'Diperiksa oleh' ? 'Manajer Operasi' : 'Kepala Teknik Tambang' }}</p>
          </div>
        </div>
      </section>
    </div>
  </PrintShell>
</template>

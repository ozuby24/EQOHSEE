<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';

defineOptions({ layout: BlankLayout });

const props = defineProps<{
  dok: any; dari: string; sampai: string;
  ringkas: Record<string, any>;
  ambang: Record<string, any>;
  izin: Array<Record<string, any>>;
  tindak: Array<Record<string, any>>;
  kembali?: string;
}>();

const angka = (v: unknown, d = 0) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: d, minimumFractionDigits: d }).format(Number(v || 0));
const tanggal = (v: unknown) => v ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(String(v))) : '—';
const label = (v: string) => String(v || '').replaceAll('-', ' ').replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
</script>

<template>
  <Head title="Laporan Izin Kerja Aman" />

  <PrintShell title="Laporan Izin Kerja Aman" :kembali="props.kembali">
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
        <h1 class="font-bold text-[16px] uppercase">Laporan Izin Kerja Aman</h1>
        <p class="text-[11px] text-stone-500 mt-1">Periode {{ tanggal(props.dari) }} — {{ tanggal(props.sampai) }}</p>
        <p class="text-[10px] text-stone-400 mt-1">{{ props.dok?.divisi }} · {{ props.dok?.departemen }}</p>
      </header>

      <section class="mb-5 rounded border border-stone-300 p-3 text-[10px] print:rounded-none"
               :class="Number(props.ringkas?.belumTutup) ? 'bg-amber-50' : ''">
        <b class="block mb-1">Dasar laporan</b>
        Disusun dari <b>{{ props.ringkas?.total ?? 0 }}</b> izin yang masa berlakunya jatuh pada periode ini.
        Izin disaring menurut masa berlakunya, bukan tanggal pembuatannya.
        <template v-if="Number(props.ringkas?.belumTutup)">
          <b>{{ props.ringkas.belumTutup }}</b> izin sudah lewat waktunya tetapi belum ditutup — area itu masih
          tercatat di bawah izin, dan izin berikutnya diterbitkan di atas keadaan yang dikira aman.
        </template>
      </section>

      <h3 class="font-bold text-[13px] mb-2">A. Ringkasan</h3>
      <table class="w-full border-collapse text-[11px] mb-6"><tbody>
        <tr v-for="b in [
          { k: 'Izin pada periode', v: String(props.ringkas.total ?? 0), s: 'Menurut masa berlakunya' },
          { k: 'Diterbitkan', v: String(props.ringkas.terbit ?? 0), s: '' },
          { k: 'Sedang berlaku saat laporan dicetak', v: String(props.ringkas.berlaku ?? 0), s: '' },
          { k: 'Menunggu penerbitan', v: String(props.ringkas.menunggu ?? 0), s: 'Pekerjaannya belum boleh dimulai' },
          { k: 'Sudah ditutup', v: String(props.ringkas.ditutup ?? 0), s: '' },
          { k: 'Lewat waktu belum ditutup', v: String(props.ringkas.belumTutup ?? 0), s: 'Keadaan area tidak diketahui' },
          { k: 'Uji gas basi pada izin berlaku', v: String(props.ringkas.gasBasi ?? 0), s: 'Melewati batas umur pengukuran' },
        ]" :key="b.k" class="border-b border-stone-200">
          <td class="p-2 w-[42%]">{{ b.k }}</td>
          <td class="p-2 font-semibold w-[12%]">{{ b.v }}</td>
          <td class="p-2 text-stone-500">{{ b.s }}</td>
        </tr>
      </tbody></table>

      <h3 class="font-bold text-[13px] mb-2">B. Ambang Gas yang Dipakai</h3>
      <table class="w-full border-collapse text-[10px] mb-6">
        <thead class="bg-stone-100">
          <tr>
            <th class="border border-stone-300 p-1.5 text-left">Parameter</th>
            <th class="border border-stone-300 p-1.5 text-right">Batas bawah</th>
            <th class="border border-stone-300 p-1.5 text-right">Batas atas</th>
            <th class="border border-stone-300 p-1.5 text-left">Sumber</th>
            <th class="border border-stone-300 p-1.5 text-left">Acuan</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(a, kode) in props.ambang" :key="kode">
            <td class="border border-stone-300 p-1.5 font-semibold">{{ String(kode).toUpperCase() }} — {{ a.nama }}</td>
            <td class="border border-stone-300 p-1.5 text-right">{{ a.min === null ? '—' : `${a.min} ${a.satuan}` }}</td>
            <td class="border border-stone-300 p-1.5 text-right">{{ a.maks === null ? '—' : `${a.maks} ${a.satuan}` }}</td>
            <td class="border border-stone-300 p-1.5">{{ a.ditetapkan ? 'Ditetapkan situs' : 'Bawaan aplikasi' }}</td>
            <td class="border border-stone-300 p-1.5">{{ a.acuan || '— belum diisi —' }}</td>
          </tr>
        </tbody>
      </table>

      <h3 class="font-bold text-[13px] mb-2">C. Daftar Izin</h3>
      <table class="w-full border-collapse text-[10px] mb-6">
        <thead class="bg-stone-100">
          <tr>
            <th class="border border-stone-300 p-1.5 text-left">Nomor</th>
            <th class="border border-stone-300 p-1.5 text-left">Jenis</th>
            <th class="border border-stone-300 p-1.5 text-left">Lokasi</th>
            <th class="border border-stone-300 p-1.5 text-left">Masa berlaku</th>
            <th class="border border-stone-300 p-1.5 text-left">Status</th>
            <th class="border border-stone-300 p-1.5 text-left">Penutupan</th>
            <th class="border border-stone-300 p-1.5 text-right">Uji gas</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="i in props.izin" :key="i.id">
            <td class="border border-stone-300 p-1.5 font-semibold">{{ i.nomor }}</td>
            <td class="border border-stone-300 p-1.5">{{ label(i.jenis) }}</td>
            <td class="border border-stone-300 p-1.5">{{ i.lokasi }}</td>
            <td class="border border-stone-300 p-1.5">{{ i.mulai }} — {{ i.selesai }}</td>
            <td class="border border-stone-300 p-1.5">{{ i.statusLabel }}</td>
            <td class="border border-stone-300 p-1.5">
              <template v-if="i.ditutup">{{ i.ditutupPada }}</template>
              <template v-else-if="i.lewatBelumDitutup"><b>belum ditutup</b></template>
              <template v-else>—</template>
            </td>
            <td class="border border-stone-300 p-1.5 text-right">
              <template v-if="!i.perluUjiGas">tidak diperlukan</template>
              <template v-else-if="!(i.gas || []).length"><b>tidak ada</b></template>
              <template v-else>{{ (i.gas || []).length }}× · terakhir {{ angka(i.gas[0].usia) }} mnt</template>
            </td>
          </tr>
          <tr v-if="!props.izin.length"><td colspan="7" class="border border-stone-300 p-3 text-center text-stone-400">Belum ada izin pada periode ini.</td></tr>
        </tbody>
      </table>

      <h3 class="font-bold text-[13px] mb-2">D. Tindak Lanjut Terbuka</h3>
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
            <p class="text-stone-500">{{ p === 'Disusun oleh' ? 'Petugas K3' : p === 'Diperiksa oleh' ? 'Penerbit Izin' : 'Kepala Teknik Tambang' }}</p>
          </div>
        </div>
      </section>
    </div>
  </PrintShell>
</template>

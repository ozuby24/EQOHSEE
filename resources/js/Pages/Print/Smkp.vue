<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';
import KopCetak from '../../Components/KopCetak.vue';

defineOptions({ layout: BlankLayout });

const props = defineProps<{
  mode: string;
  audit: any;
  totalLembar?: number;
  dok?: any;
  elemen?: any[];
  kelayakan?: Record<string, string>;
  faktor?: Record<string, any>;
  kinerja?: Record<string, any>;
  komponen?: Record<string, any>;
  pengesah?: Record<string, any>;
  judul?: string;
  hadir?: any[];
  temuan?: any[];
  rekap?: any;
  mandays?: any;
  meta?: any;
  kembali?: string;
}>();

const rencana = computed(() => props.audit?.rencana || {});
const permulaan = computed(() => props.audit?.permulaan || {});
const title = computed(() => ({
  berita: 'Berita Acara Tahap I',
  rencana: 'Rencana Audit SMKP',
  hadir: `Daftar Hadir ${props.judul || ''}`,
  laporan: 'Laporan Audit SMKP',
}[props.mode] || 'Dokumen SMKP'));
const pages = computed(() => {
  const rows = props.mode === 'hadir' ? props.hadir || [] : props.temuan || [];
  const size = props.mode === 'hadir' ? 16 : 6;
  const result: any[][] = [];
  for (let i = 0; i < rows.length; i += size) result.push(rows.slice(i, i + size));
  return result.length ? result : [[]];
});
const tanggal = (value: unknown) => value
  ? new Date(String(value)).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })
  : '-';
const nomor = (value: unknown) => Number(value) + 1;
const total = computed(() => props.totalLembar || (
  props.mode === 'hadir' || props.mode === 'laporan' ? pages.value.length : 1
));
</script>

<template>
  <Head :title="title" />
  <PrintShell :title="title" :kembali="props.kembali">
    <template v-if="props.mode === 'berita'">
      <section class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0 lembar-putus">
        <DocHeader :dok="props.dok" :title="title" :halaman="1" :dari="4" />
        <h1 class="text-center font-bold text-[16px] uppercase border-b border-stone-200 pb-4 mb-5">Berita Acara Hasil Pelaksanaan</h1>
        <h2 class="text-center font-bold text-[13px] uppercase mb-5">Tahapan Awal Audit Internal SMKP</h2>
        <InfoAudit :audit="props.audit" />
        <h3 class="font-bold text-[13px] mb-2">Data Kinerja Keselamatan Pertambangan</h3>
        <table class="w-full text-[12px]"><tbody>
          <tr v-for="(item, key) in props.kinerja || {}" :key="key" class="border-b border-stone-100">
            <td class="p-2">{{ item.label || key }}</td>
            <td class="p-2 text-right font-semibold">{{ props.audit?.kinerja?.[key] || '-' }} {{ item.satuan || '' }}</td>
          </tr>
        </tbody></table>
      </section>
      <section class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0 lembar-putus">
        <DocHeader :dok="props.dok" :title="title" :halaman="2" :dari="4" />
        <h3 class="font-bold text-[13px] mb-2">B. Pelaksanaan Kontak Awal dan Kelayakan Audit</h3>
        <p class="text-[12px] text-stone-600 mb-3">Kontak awal: {{ tanggal(permulaan.tanggal_kontak) }} - {{ permulaan.media_kontak || 'Media belum dicatat' }} - {{ permulaan.wakil_auditi || 'Wakil auditi belum dicatat' }}</p>
        <table class="w-full text-[11.5px]"><thead><tr class="bg-stone-100 text-left"><th class="p-2">No</th><th class="p-2">Indikator Kelayakan Audit</th><th class="p-2">Hasil Evaluasi</th></tr></thead><tbody>
          <tr v-for="(label, key) in props.kelayakan || {}" :key="key" class="border-b border-stone-100"><td class="p-2">{{ nomor(key) }}</td><td class="p-2">{{ label }}</td><td class="p-2">{{ permulaan.kelayakan?.[key] || '-' }}</td></tr>
        </tbody></table>
        <p v-if="permulaan.kesimpulan" class="text-[12px] text-stone-600 mt-4 whitespace-pre-line">{{ permulaan.kesimpulan }}</p>
      </section>
      <section class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0 lembar-putus">
        <DocHeader :dok="props.dok" :title="title" :halaman="3" :dari="4" />
        <h3 class="font-bold text-[13px] mb-2">C. Faktor Penyesuaian dan Kecukupan Dokumentasi</h3>
        <table class="w-full text-[11.5px] mb-5"><thead><tr class="bg-stone-100 text-left"><th class="p-2">Faktor</th><th class="p-2">Hasil</th></tr></thead><tbody>
          <tr v-for="(item, key) in props.faktor || {}" :key="key" class="border-b border-stone-100"><td class="p-2">{{ item.label || key }}</td><td class="p-2">{{ permulaan.faktor?.[key] ? 'Ya' : 'Tidak' }}</td></tr>
        </tbody></table>
        <table class="w-full text-[11.5px]"><thead><tr class="bg-stone-100 text-left"><th class="p-2">Elemen</th><th class="p-2">Status</th><th class="p-2">Catatan</th></tr></thead><tbody>
          <tr v-for="element in props.elemen || []" :key="element.kode" class="border-b border-stone-100"><td class="p-2 font-semibold">{{ element.kode }} - {{ element.nama }}</td><td class="p-2">{{ props.audit?.kecukupan?.[element.kode]?.status || 'Belum ditinjau' }}</td><td class="p-2">{{ props.audit?.kecukupan?.[element.kode]?.ket || '-' }}</td></tr>
        </tbody></table>
      </section>
      <section class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0">
        <DocHeader :dok="props.dok" :title="title" :halaman="4" :dari="4" />
        <h3 class="font-bold text-[13px] mb-2">D. Kesimpulan Tahap I</h3>
        <div class="rounded-xl border border-stone-200 p-4 text-[12px] text-stone-600 whitespace-pre-line">{{ permulaan.kesimpulan || 'Hasil dan kesimpulan Tahap I belum diisi.' }}</div>
        <div class="grid grid-cols-2 gap-8 text-center text-[11px] mt-12"><div>Ketua Tim Audit<div class="h-14"></div><b class="block border-t border-stone-400 pt-1">{{ props.audit?.ketua_auditor || ' ' }}</b></div><div>Kepala Teknik Tambang<div class="h-14"></div><b class="block border-t border-stone-400 pt-1">&nbsp;</b></div></div>
      </section>
    </template>

    <template v-if="props.mode === 'rencana'">
      <section class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0 lembar-putus">
        <DocHeader :dok="props.dok" :title="title" :halaman="1" :dari="3" /><h1 class="text-center font-bold text-[16px] uppercase border-b border-stone-200 pb-4 mb-5">Rencana Audit SMKP</h1><InfoAudit :audit="props.audit" />
        <div v-for="(key, index) in ['tujuan', 'kriteria', 'ruang_lingkup']" :key="key" class="mb-4"><h3 class="font-bold text-[13px]">{{ nomor(index) }}. {{ props.komponen?.[key]?.judul || key }}</h3><p class="text-[12px] text-stone-600 whitespace-pre-line">{{ rencana[key] || 'Belum diisi.' }}</p></div>
        <h3 class="font-bold text-[13px]">4. {{ props.komponen?.tanggal?.judul || 'Jadwal Audit' }}</h3><p class="text-[12px] text-stone-600">{{ tanggal(rencana.tanggal_mulai) }} - {{ tanggal(rencana.tanggal_selesai) }}</p><p class="text-[11px] text-stone-500 mt-2">Alokasi Tahap II: {{ props.mandays?.tahap2 ?? '-' }} hari dari {{ props.mandays?.total ?? '-' }} hari.</p>
      </section>
      <section class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0 lembar-putus">
        <DocHeader :dok="props.dok" :title="title" :halaman="2" :dari="3" /><h3 class="font-bold text-[13px] mb-2">5. {{ props.komponen?.susunan?.judul || 'Susunan Kegiatan' }}</h3><table class="w-full text-[11px]"><thead><tr class="bg-stone-100 text-left"><th class="p-2">No</th><th class="p-2">Tanggal</th><th class="p-2">Waktu</th><th class="p-2">Kegiatan</th><th class="p-2">Auditi</th><th class="p-2">Auditor</th></tr></thead><tbody><tr v-for="(row, index) in rencana.susunan || []" :key="index" class="border-b border-stone-100"><td class="p-2">{{ nomor(index) }}</td><td class="p-2">{{ tanggal(row.tanggal) }}</td><td class="p-2">{{ row.waktu || '-' }}</td><td class="p-2">{{ row.kegiatan || '-' }}</td><td class="p-2">{{ row.auditi || '-' }}</td><td class="p-2">{{ row.auditor || '-' }}</td></tr></tbody></table><h3 class="font-bold text-[13px] mt-6 mb-2">6. {{ props.komponen?.tugas?.judul || 'Tim Auditor' }}</h3><table class="w-full text-[11px]"><thead><tr class="bg-stone-100 text-left"><th class="p-2">No</th><th class="p-2">Nama</th><th class="p-2">Peran</th><th class="p-2">Registrasi</th><th class="p-2">Lingkup</th></tr></thead><tbody><tr v-for="(row, index) in rencana.tugas || []" :key="index" class="border-b border-stone-100"><td class="p-2">{{ nomor(index) }}</td><td class="p-2">{{ row.nama }}</td><td class="p-2">{{ row.peran }}</td><td class="p-2">{{ row.registrasi || '-' }}</td><td class="p-2">{{ row.lingkup || '-' }}</td></tr></tbody></table>
      </section>
      <section class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0">
        <DocHeader :dok="props.dok" :title="title" :halaman="3" :dari="3" /><div v-for="(key, index) in ['sumberdaya', 'metode', 'sampel']" :key="key" class="mb-5"><h3 class="font-bold text-[13px]">{{ nomor(index + 6) }}. {{ props.komponen?.[key]?.judul || key }}</h3><p class="text-[12px] text-stone-600 whitespace-pre-line">{{ rencana[key] || 'Belum diisi.' }}</p></div><template v-for="slot in ['present', 'future']" :key="slot"><div v-if="rencana.risiko?.[slot]?.length" class="mb-4"><h3 class="font-bold text-[12px]">Top Risks - {{ slot }}</h3><table class="w-full text-[11px]"><tbody><tr v-for="(risk, index) in rencana.risiko[slot]" :key="index" class="border-b border-stone-100"><td class="p-2">{{ risk.kegiatan }}</td><td class="p-2">{{ risk.risiko }}</td><td class="p-2">{{ risk.nilai }}</td></tr></tbody></table></div></template><h3 class="font-bold text-[13px]">9. {{ props.komponen?.pengesahan?.judul || 'Pengesahan' }}</h3><div class="grid grid-cols-2 gap-8 text-center text-[11px] mt-6"><div v-for="(sign, key) in rencana.pengesahan || {}" :key="key">{{ sign.jabatan || props.pengesah?.[key]?.peran || key }}<div class="h-14"></div><b class="block border-t border-stone-400 pt-1">{{ sign.nama || ' ' }}</b></div></div>
      </section>
    </template>

    <template v-if="props.mode === 'hadir'"><section v-for="(bagian, page) in pages" :key="page" class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0" :class="Number(page) < pages.length - 1 ? 'lembar-putus' : ''"><DocHeader :dok="props.dok" :title="title" :halaman="nomor(page)" :dari="pages.length" /><h1 v-if="Number(page) === 0" class="text-center font-bold text-[16px] uppercase border-b border-stone-200 pb-4 mb-5">Daftar Hadir {{ props.judul }}</h1><table class="w-full text-[11.5px]"><thead><tr class="bg-stone-100 text-left"><th class="p-2">No</th><th class="p-2">Nama</th><th class="p-2">Jabatan</th><th class="p-2">Perusahaan</th><th class="p-2">Tanda Tangan</th></tr></thead><tbody><tr v-for="(person, index) in bagian" :key="person.id" class="border-b border-stone-100"><td class="p-2">{{ Number(page) * 16 + Number(index) + 1 }}</td><td class="p-2 font-semibold">{{ person.nama }}</td><td class="p-2">{{ person.jabatan || '-' }}</td><td class="p-2">{{ person.perusahaan || '-' }}</td><td class="p-2"><div class="h-7 border-b border-dashed border-stone-300"></div></td></tr><tr v-if="!bagian.length"><td colspan="5" class="p-8 text-center text-stone-400">Belum ada peserta tercatat.</td></tr></tbody></table></section></template>

    <template v-if="props.mode === 'laporan'"><section class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0 lembar-putus"><DocHeader :dok="props.dok" :title="title" :halaman="1" :dari="total" /><h1 class="text-center font-bold text-[17px] uppercase border-b border-stone-200 pb-4 mb-5">Laporan Audit Internal SMKP</h1><p class="text-center text-[11px] text-stone-500">{{ props.meta?.basis || 'Sistem Manajemen Keselamatan Pertambangan' }}</p><InfoAudit :audit="props.audit" /><div class="rounded-xl border border-stone-200 p-4 flex justify-between my-5"><span>Nilai akhir<br><b class="text-2xl">{{ props.rekap?.skor ?? 0 }}</b></span><span class="text-right">Tingkat penerapan<br><b>{{ props.rekap?.tingkat?.label || '-' }}</b></span></div><h3 class="font-bold text-[13px] mb-2">Rekapitulasi per Elemen</h3><table class="w-full text-[11px]"><thead><tr class="bg-stone-100 text-left"><th class="p-2">Elemen</th><th class="p-2">Bobot</th><th class="p-2">Dinilai</th><th class="p-2">Capaian</th><th class="p-2">Nilai</th></tr></thead><tbody><tr v-for="element in props.elemen || []" :key="element.kode" class="border-b border-stone-100"><td class="p-2">{{ element.kode }}. {{ element.nama }}</td><td class="p-2">{{ props.rekap?.elemen?.[element.kode]?.bobot }}</td><td class="p-2">{{ props.rekap?.elemen?.[element.kode]?.dinilai }}/{{ props.rekap?.elemen?.[element.kode]?.berlaku }}</td><td class="p-2">{{ props.rekap?.elemen?.[element.kode]?.capaian }}</td><td class="p-2 font-bold">{{ props.rekap?.elemen?.[element.kode]?.skor }}</td></tr></tbody></table></section><section v-for="(bagian, page) in pages" :key="`temuan-${page}`" class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0" :class="Number(page) < pages.length - 1 ? 'lembar-putus' : ''"><DocHeader :dok="props.dok" title="Daftar Temuan" :halaman="Number(page) + 2" :dari="total" /><h3 class="font-bold text-[13px] mb-3">Daftar Temuan ({{ props.temuan?.length || 0 }})</h3><article v-for="finding in bagian" :key="finding.id" class="border border-stone-200 rounded-xl p-3 mb-2"><div class="flex gap-2 text-[10px] font-bold"><span>{{ finding.kode_kriteria }}</span><span>{{ finding.jenis }}</span><span>{{ finding.status }}</span></div><p class="text-[12px] mt-1">{{ finding.uraian }}</p><p v-if="finding.akar_masalah" class="text-[11px] text-stone-500">Akar masalah: {{ finding.akar_masalah }}</p><p v-if="finding.tindakan" class="text-[11px] text-stone-500">Tindakan: {{ finding.tindakan }}</p></article></section></template>
  </PrintShell>
</template>

<script lang="ts">
import { h } from 'vue';

/* Kop ringkas diganti kop penuh: yang lama hanya menyebut nama
   perusahaan, judul, dan nomor halaman — tanpa nomor dokumen,
   tanggal penerbitan, tanggal persetujuan, maupun revisi.
   Keempatnya justru yang membuat sebuah lembar disebut
   dokumen terkendali. */
const DocHeader = (props: any) => h(KopCetak, {
  dok: { ...(props.dok ?? {}), judul: props.dok?.judul || props.title },
  halaman: props.halaman, dari: props.dari,
});
const InfoAudit = (props: any) => h('table', { class: 'w-full text-[12px] mb-5' }, [
  h('tbody', {}, [
    ['Nama perusahaan auditi', props.audit?.company?.name || 'Seluruh Perusahaan'],
    ['Jenis perizinan', props.audit?.company?.izin_type || '-'],
    ['Jenis komoditas', props.audit?.company?.commodity || '-'],
    ['Alamat perusahaan', props.audit?.company?.address || props.audit?.company?.location || '-'],
    ['Periode audit', props.audit?.tahun],
  ].map(([key, value]) => h('tr', { class: 'border-b border-stone-100' }, [
    h('td', { class: 'p-2 text-stone-500 w-56' }, key),
    h('td', { class: 'p-2' }, value),
  ]))),
]);
export default { components: { DocHeader, InfoAudit } };
</script>

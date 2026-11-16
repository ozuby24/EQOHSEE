<script setup lang="ts">
/**
 * Satu seksi Formulir Ketidaksesuaian dan Tindak Lanjut Ketidaksesuaian Audit.
 * Dipakai di wadah ukur dan di lembar cetak (lib/susunLembar useSeksiLembar),
 * sehingga seksi yang panjang — akar masalah, tindakan perbaikan — dapat
 * berlanjut ke lembar berikutnya tanpa meluber.
 */
import FormIdentitas from './FormIdentitas.vue';
import FormTtd from './FormTtd.vue';
import type { RingkasAudit, TtdForm } from '../../smkp/types';

export interface TemuanKts {
  id: number; nomor: string; kode: string; elemen: string; sub: string; subsub: string; kategori: string; labelKategori: string;
  uraian: string; lokasi: string; divisi: string; departemen: string; bukti: string; deskripsi: string; deskripsiOtomatis: string; akar: string; tindakan: string; buktiPerbaikan: string;
  pj: string; batas: string | null; tanggalPerbaikan: string | null; status: string; verifikasi: string; nomorTindakLanjut: string;
  syaratTtd: { perbaikan: string | null; verifikasi: string | null }; lampiran: Array<{ nama: string; url: string }>;
}

const props = defineProps<{ seksi: string; t: TemuanKts | null; audit: RingkasAudit; ttd: TtdForm; lanjut?: boolean }>();

const KATEGORI: Array<[string, string]> = [['kritikal', 'Kritikal'], ['mayor', 'Mayor'], ['minor', 'Minor']];
const urlTemuan = (t: TemuanKts) => `/smkp/${props.audit.id}/temuan?buka=${t.id}`;
</script>

<template>
  <div class="flow-root">
    <!-- Lembar temuan (diisi auditor) -->
    <template v-if="seksi === 'judul'">
      <p class="form-judul">Formulir Ketidaksesuaian dan Tindak Lanjut Ketidaksesuaian Audit<br>Penerapan Sistem Manajemen Keselamatan Pertambangan</p>
      <FormIdentitas :baris="[['Nama Perusahaan', audit.perusahaan.nama], ['Tanggal Audit', audit.periode], ['Nomor Ketidaksesuaian', t?.nomor]]" />
    </template>

    <template v-else-if="seksi === 'uraian'">
      <p class="font-bold text-slate-900 mb-2">Uraian Ketidaksesuaian</p>
      <table class="tabel-form">
        <tbody>
          <tr>
            <td class="w-[22%]">Uraian Masalah<br><i class="text-slate-500">(Problem)</i></td>
            <td class="whitespace-pre-line text-justify">{{ t?.uraian }}</td>
            <td rowspan="4" class="w-[17%]">
              <p class="mb-2">Kategori</p>
              <p v-for="k in KATEGORI" :key="k[0]" class="flex items-center gap-2 mb-1.5"><span class="centang">{{ t?.kategori === k[0] ? '✓' : '' }}</span>{{ k[1] }}</p>
            </td>
          </tr>
          <tr><td>Area / Lokasi &amp; Departemen<br><i class="text-slate-500">(Location)</i></td><td class="whitespace-pre-line">{{ t?.lokasi }}</td></tr>
          <tr><td>Bukti<br><i class="text-slate-500">(Objective Evidence)</i></td><td class="h-14 whitespace-pre-line">{{ t?.bukti }}</td></tr>
          <tr>
            <td>Referensi <i class="text-slate-500">(Reference)</i><br>Elemen<br>Sub Elemen<template v-if="t?.subsub"><br>Sub-sub Elemen</template></td>
            <td><br>{{ t?.elemen }}<br>{{ t?.sub }}<template v-if="t?.subsub"><br>{{ t.subsub }}</template></td>
          </tr>
          <tr><td colspan="3"><p>Deskripsi</p><p class="min-h-[56px] whitespace-pre-line text-justify">{{ t ? (t.deskripsi.trim() || t.deskripsiOtomatis) : '' }}</p></td></tr>
        </tbody>
      </table>
    </template>

    <template v-else-if="seksi === 'batas'">
      <p class="font-bold text-slate-900 mt-5 mb-2">Batas Waktu Perbaikan</p>
      <div class="kotak-isi">{{ t?.batas || ' ' }}</div>
    </template>

    <FormTtd v-else-if="seksi === 'ttdTemuan'" :pihak="[{ peran: 'Nama Auditor', kunci: 'ketua', nama: ttd.ketua }, { peran: 'Nama Auditi', kunci: 'ktt', nama: ttd.ktt }]" :ttd="ttd"
             :bagian="t ? `ketidaksesuaian.${t.id}.temuan` : undefined" />

    <!-- Lembar tindak lanjut (auditi) & ulasan tim auditor -->
    <p v-else-if="seksi === 'tlJudul'" class="font-bold text-slate-900 mb-2">Tindak Lanjut Audit<template v-if="t"> — {{ t.nomor }}</template></p>

    <table v-else-if="seksi === 'akar'" class="tabel-form mb-3"><tbody>
      <tr><th class="text-left">Investigasi Akar Permasalahan</th></tr>
      <tr><td class="h-20 whitespace-pre-line text-justify">{{ t?.akar }}</td></tr>
    </tbody></table>

    <table v-else-if="seksi === 'tindakan'" class="tabel-form mb-3"><tbody>
      <tr><th class="text-left">Tindakan Perbaikan</th></tr>
      <tr><td class="h-24 whitespace-pre-line text-justify">{{ t?.tindakan }}<template v-if="t?.pj"><br><span class="text-slate-500">Penanggung jawab: {{ t.pj }}</span></template></td></tr>
    </tbody></table>

    <template v-else-if="seksi === 'bukti'">
      <table class="tabel-form mb-3"><tbody>
        <tr><th class="text-left">Bukti</th></tr>
        <tr><td class="h-16 whitespace-pre-line">{{ t?.buktiPerbaikan }}</td></tr>
      </tbody></table>
      <p v-if="t?.lampiran.length" class="no-print -mt-2 mb-3 text-[11px] text-slate-500">
        Lampiran bukti tindak lanjut (tidak tercetak):
        <a v-for="(l, j) in t.lampiran" :key="j" :href="l.url" target="_blank" rel="noopener" class="ml-2 font-semibold text-navy-700 hover:underline">{{ l.nama }}</a>
      </p>
    </template>

    <table v-else-if="seksi === 'tanggal'" class="tabel-form"><tbody>
      <tr><td class="w-[22%]">Tanggal Perbaikan</td><td>{{ t?.tanggalPerbaikan }}</td></tr>
    </tbody></table>

    <FormTtd v-else-if="seksi === 'ttdAuditi'" :pihak="[{ peran: 'Nama Auditi', kunci: 'ktt', nama: ttd.ktt }]" :ttd="ttd" manual :syarat="t?.syaratTtd.perbaikan"
             :tautan="t ? urlTemuan(t) : undefined" :bagian="t ? `ketidaksesuaian.${t.id}.perbaikan` : undefined" />

    <template v-else-if="seksi === 'ulasan'">
      <p class="font-bold text-slate-900 mt-7 mb-2">Ulasan Tim Auditor</p>
      <FormIdentitas :baris="[['Nomor Tindak Lanjut', t?.nomorTindakLanjut]]" />
    </template>

    <table v-else-if="seksi === 'verifikasi'" class="tabel-form"><tbody>
      <tr><th class="text-left">Verifikasi</th></tr>
      <tr><td class="h-20 whitespace-pre-line">{{ t?.verifikasi }}<template v-if="t?.status === 'Closed'"><br><b>Status: ditutup.</b></template></td></tr>
    </tbody></table>

    <FormTtd v-else-if="seksi === 'ttdAuditor'" :pihak="[{ peran: 'Nama Auditor', kunci: 'ketua', nama: ttd.ketua }]" :ttd="ttd" manual :syarat="t?.syaratTtd.verifikasi"
             :tautan="t ? urlTemuan(t) : undefined" :bagian="t ? `ketidaksesuaian.${t.id}.verifikasi` : undefined" />
  </div>
</template>

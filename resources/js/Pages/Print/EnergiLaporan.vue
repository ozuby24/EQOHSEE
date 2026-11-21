<script setup lang="ts">
/*
  Laporan energi & GRK terbit — digambar dari SNAPSHOT-nya (FSD §7.4, §10).
  Tidak ada angka yang dihitung ulang di sini: yang tercetak adalah yang
  dibekukan pada laporan, dan hash SHA-256-nya membuktikan itu.
*/
import { Head, router, useForm } from '@inertiajs/vue3';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';
import KopCetak from '../../Components/KopCetak.vue';
import { n, rp, statusLabel, tgl } from '../../energi';

defineOptions({ layout: BlankLayout });

const props = defineProps<{
  dok: any; laporan: Record<string, any>; snap: Record<string, any>;
  validasi: { blokir: string[]; peringatan: string[] }; boleh: Record<string, boolean>;
  unduh: Record<string, string | null>; kembali?: string;
}>();

const s = props.snap ?? {};
const r = s.ringkas ?? {};
const aksi = (jenis: string) => router.post(`/energi/pelaporan/terbit/${props.laporan.id}/${jenis}`, {}, { preserveScroll: true });
const sampai = useForm({ disampaikan_pada: new Date().toISOString().slice(0, 10), bukti_penyampaian: '' });
const catat = () => sampai.post(`/energi/pelaporan/terbit/${props.laporan.id}/sampaikan`, { preserveScroll: true });
</script>

<template>
  <Head :title="props.laporan.judul" />

  <PrintShell :title="props.laporan.judul" :kembali="props.kembali" mendatar>
    <div class="print:hidden mb-4 rounded-2xl border border-stone-200 bg-white p-4 space-y-2 text-[12px]">
      <div class="flex flex-wrap items-center gap-2">
        <span class="font-bold">Status: {{ statusLabel(props.laporan.status) }} · v{{ props.laporan.versi }}</span>
        <span v-if="props.laporan.hash" class="text-stone-500 font-mono text-[10.5px]">SHA-256 {{ props.laporan.hash.slice(0, 16) }}… <b :class="props.laporan.utuh ? 'text-emerald-700' : 'text-red-700'">{{ props.laporan.utuh ? 'utuh' : 'TIDAK COCOK' }}</b></span>
        <div class="ml-auto flex flex-wrap gap-2">
          <button v-if="props.boleh.susunUlang" type="button" class="eq-btn-lain" @click="aksi('susun-ulang')">Susun ulang</button>
          <button v-if="props.boleh.ajukan" type="button" class="eq-btn-utama" @click="aksi('ajukan')">Ajukan</button>
          <button v-if="props.boleh.setujui" type="button" class="eq-btn-setuju" @click="aksi('setujui')">Setujui</button>
          <button v-if="props.boleh.terbitkan" type="button" class="eq-btn-setuju" @click="aksi('terbitkan')">Terbitkan & bekukan</button>
          <button v-if="props.boleh.revisi" type="button" class="eq-btn-lain" @click="aksi('revisi')">Buat versi baru</button>
          <a v-for="[f, url] in Object.entries((props.unduh ?? {}) as Record<string, any>)" v-show="url" :key="f" :href="url ?? '#'" class="eq-btn-mini uppercase">{{ f }}</a>
        </div>
      </div>
      <ul v-if="props.validasi.blokir.length" class="rounded-lg bg-red-50 text-red-700 p-2 space-y-0.5"><li v-for="b in props.validasi.blokir" :key="b">✖ {{ b }}</li></ul>
      <ul v-if="props.validasi.peringatan.length" class="rounded-lg bg-amber-50 text-amber-800 p-2 space-y-0.5"><li v-for="p in props.validasi.peringatan" :key="p">⚠ {{ p }}</li></ul>
      <form v-if="props.boleh.sampaikan" class="flex flex-wrap items-end gap-2" @submit.prevent="catat">
        <label class="text-[11px] font-semibold text-stone-500">Disampaikan pada<input v-model="sampai.disampaikan_pada" type="date" class="block mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">No. tanda terima (POME/SRN/dll.)<input v-model="sampai.bukti_penyampaian" required maxlength="255" class="block mt-1 rounded-lg border-stone-200 text-[12px] w-72"></label>
        <button class="eq-btn-mini">Catat penyampaian</button>
      </form>
      <p v-if="props.laporan.disampaikan_pada" class="text-emerald-700">Disampaikan {{ tgl(props.laporan.disampaikan_pada) }} — {{ props.laporan.bukti_penyampaian }}</p>
    </div>

    <div class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0 text-[11px]">
      <KopCetak :dok="props.dok" />

      <header class="text-center border-b border-stone-200 pb-3 mb-4">
        <h1 class="font-bold text-[15px] uppercase">{{ s.judul }}</h1>
        <p class="text-stone-600 mt-1">{{ s.perusahaan?.nama }} · periode {{ tgl(s.periode?.[0]) }} — {{ tgl(s.periode?.[1]) }}</p>
        <p class="text-[10px] text-stone-500 mt-1">Dasar: {{ s.dasar }} · GWP {{ s.gwp_set }} · disusun {{ tgl(s.dibuat_pada) }}<template v-if="s.versi_aplikasi"> · versi aplikasi {{ s.versi_aplikasi }}</template></p>
      </header>

      <h3 class="font-bold text-[12.5px] mb-2">A. Ringkasan energi & emisi</h3>
      <table class="w-full border-collapse mb-4"><tbody>
        <tr class="border-b border-stone-200"><td class="p-1.5 w-[38%]">Konsumsi energi total</td><td class="p-1.5 font-semibold">{{ n(r.gj, 1) }} GJ · {{ n(r.toe, 1) }} TOE · {{ n(s.sbm, 0) }} SBM</td></tr>
        <tr class="border-b border-stone-200"><td class="p-1.5">Produksi</td><td class="p-1.5">{{ n(s.produksi?.ton, 0) }} ton · {{ n(s.produksi?.bcm, 0) }} BCM</td></tr>
        <tr class="border-b border-stone-200"><td class="p-1.5">Intensitas energi</td><td class="p-1.5">{{ n(s.intensitas?.gj_ton, 4) }} GJ/ton · {{ n(s.intensitas?.l_bcm, 3) }} L/BCM</td></tr>
        <tr class="border-b border-stone-200"><td class="p-1.5">Emisi Scope 1</td><td class="p-1.5 font-semibold">{{ n(r.s1, 2) }} tCO₂e</td></tr>
        <tr class="border-b border-stone-200"><td class="p-1.5">Emisi Scope 2 (location-based / market-based)</td><td class="p-1.5 font-semibold">{{ n(r.s2_lb, 2) }} / {{ n(r.s2_mb, 2) }} tCO₂e</td></tr>
        <tr class="border-b border-stone-200"><td class="p-1.5">Emisi Scope 3 (kategori terpilih)</td><td class="p-1.5">{{ n(r.s3, 2) }} tCO₂e<template v-if="r.s3_terpisah"> · kategori 11 disajikan terpisah: {{ n(r.s3_terpisah, 0) }} tCO₂e</template></td></tr>
        <tr class="border-b border-stone-200"><td class="p-1.5">CO₂ biogenik (FAME) — terpisah</td><td class="p-1.5">{{ n(r.co2_bio, 2) }} tCO₂</td></tr>
        <tr class="border-b border-stone-200"><td class="p-1.5">Intensitas emisi (S1+S2)</td><td class="p-1.5">{{ n(s.intensitas?.tco2e_ton, 5) }} tCO₂e/ton</td></tr>
        <tr class="border-b border-stone-200"><td class="p-1.5">Biaya energi</td><td class="p-1.5">{{ rp(r.rupiah) }}</td></tr>
        <tr class="border-b border-stone-200"><td class="p-1.5">Mutu data</td><td class="p-1.5">{{ n(r.persen_terverifikasi, 1) }}% terverifikasi · {{ n(r.persen_estimasi, 1) }}% estimasi · ketidakpastian gabungan ±{{ n(r.ketidakpastian_persen, 1) }}%</td></tr>
      </tbody></table>

      <template v-if="s.personel">
        <h3 class="font-bold text-[12.5px] mb-2">B. Manajer Energi & Auditor Energi</h3>
        <table class="w-full border-collapse mb-4">
          <thead><tr class="border-b border-stone-300 text-left text-stone-500"><th class="p-1.5">Nama</th><th class="p-1.5">Peran</th><th class="p-1.5">No. sertifikat</th><th class="p-1.5">Berlaku s.d.</th><th class="p-1.5">Keadaan</th></tr></thead>
          <tbody><tr v-for="p in s.personel" :key="p.nama + p.peran" class="border-b border-stone-100"><td class="p-1.5">{{ p.nama }}</td><td class="p-1.5">{{ p.peran }}</td><td class="p-1.5">{{ p.nomor_sertifikat ?? '—' }}</td><td class="p-1.5">{{ tgl(p.berlaku_sampai) }}</td><td class="p-1.5">{{ p.sah ? 'Berlaku' : 'Tidak berlaku' }}</td></tr></tbody>
        </table>
      </template>

      <h3 class="font-bold text-[12.5px] mb-2">{{ s.personel ? 'C' : 'B' }}. Konsumsi & emisi per sumber</h3>
      <table class="w-full border-collapse mb-4 text-[10px]">
        <thead><tr class="border-b border-stone-300 text-left text-stone-500"><th class="p-1">Scope</th><th class="p-1">Sumber</th><th class="p-1">Energi</th><th class="p-1 text-right">Jumlah</th><th class="p-1 text-right">GJ</th><th class="p-1 text-right">CO₂ (t)</th><th class="p-1 text-right">CH₄ (t)</th><th class="p-1 text-right">N₂O (t)</th><th class="p-1 text-right">tCO₂e</th></tr></thead>
        <tbody><tr v-for="(b, i) in s.per_sumber" :key="i" class="border-b border-stone-100"><td class="p-1">{{ b.scope || 'EBT' }}</td><td class="p-1">{{ b.label }}</td><td class="p-1">{{ b.energi }}</td><td class="p-1 text-right">{{ n(b.jumlah, b.jumlah < 10 ? 2 : 0) }} {{ b.satuan }}</td><td class="p-1 text-right">{{ n(b.gj, 1) }}</td><td class="p-1 text-right">{{ n(b.co2_fosil_t, 2) }}</td><td class="p-1 text-right">{{ n(b.ch4_t, 4) }}</td><td class="p-1 text-right">{{ n(b.n2o_t, 4) }}</td><td class="p-1 text-right font-semibold">{{ n(b.co2e_t, 2) }}</td></tr></tbody>
      </table>

      <template v-if="s.per_gas">
        <h3 class="font-bold text-[12.5px] mb-2">Emisi per gas (Perpres 110/2025 Ps. 11)</h3>
        <table class="w-full border-collapse mb-4">
          <tbody><tr v-for="g in s.per_gas" :key="g.gas" class="border-b border-stone-100"><td class="p-1.5 w-1/3">{{ g.gas }}</td><td class="p-1.5">{{ g.massa_t !== null ? n(g.massa_t, 4) + ' t' : '' }}</td><td class="p-1.5">{{ g.co2e_t !== null ? n(g.co2e_t, 2) + ' tCO₂e' : '' }}</td></tr></tbody>
        </table>
      </template>

      <template v-if="s.batas">
        <h3 class="font-bold text-[12.5px] mb-2">Batas organisasi</h3>
        <p class="mb-1">Pendekatan: <b>{{ s.batas.pendekatan ?? '—' }}</b><template v-if="s.batas.persen_ekuitas"> ({{ n(s.batas.persen_ekuitas, 1) }}%)</template> · {{ s.batas.disetujui ? 'disetujui ' + tgl(s.batas.disetujui_pada) : 'BELUM DISETUJUI' }}</p>
        <p class="mb-1 whitespace-pre-line">{{ s.batas.uraian }}</p>
        <ul v-if="s.batas.pengecualian?.length" class="list-disc pl-5 mb-4"><li v-for="(p, i) in s.batas.pengecualian" :key="i">{{ p.sumber }} — {{ p.alasan }}<template v-if="p.estimasi_persen"> (±{{ p.estimasi_persen }}%)</template></li></ul>
      </template>

      <template v-if="s.iso_kategori">
        <h3 class="font-bold text-[12.5px] mb-2">Kategori ISO 14064-1:2018</h3>
        <table class="w-full border-collapse mb-4"><tbody><tr v-for="k in s.iso_kategori" :key="k.no" class="border-b border-stone-100"><td class="p-1.5 w-10">{{ k.no }}</td><td class="p-1.5">{{ k.label }}</td><td class="p-1.5 text-right">{{ n(k.co2e_t, 2) }} tCO₂e</td></tr></tbody></table>
      </template>

      <template v-if="s.tahun_dasar">
        <p class="mb-4">Tahun dasar {{ s.tahun_dasar.tahun }}: {{ n(s.tahun_dasar.total, 2) }} tCO₂e (S1+S2) — perubahan {{ n(s.tahun_dasar.perubahan_persen, 1) }}%.</p>
      </template>

      <template v-if="s.seu?.length">
        <h3 class="font-bold text-[12.5px] mb-2">Pemakai energi signifikan (SEU)</h3>
        <table class="w-full border-collapse mb-4"><tbody><tr v-for="x in s.seu" :key="x.kunci" class="border-b border-stone-100"><td class="p-1.5">{{ x.label }}</td><td class="p-1.5 text-right">{{ n(x.gj, 0) }} GJ</td><td class="p-1.5 text-right">{{ n(x.persen, 1) }}%</td><td class="p-1.5">{{ x.usul_seu ? 'SEU' : '' }}</td></tr></tbody></table>
      </template>

      <template v-if="s.audit">
        <h3 class="font-bold text-[12.5px] mb-2">Audit energi</h3>
        <p class="mb-1">{{ s.audit.judul }} ({{ s.audit.jenis }}) · {{ tgl(s.audit.mulai) }} – {{ tgl(s.audit.selesai) }} · {{ s.audit.standar }} · SEU: {{ (s.audit.seu ?? []).join(', ') || '—' }}</p>
        <table class="w-full border-collapse mb-4"><tbody><tr v-for="(x, i) in s.audit.rekomendasi" :key="i" class="border-b border-stone-100"><td class="p-1.5">{{ x.uraian }}</td><td class="p-1.5">{{ tgl(x.tenggat) }}</td><td class="p-1.5">{{ x.status }}{{ x.lewat ? ' (lewat tenggat)' : '' }}</td></tr></tbody></table>
      </template>

      <template v-if="s.program?.length || s.program_berikut?.length">
        <h3 class="font-bold text-[12.5px] mb-2">Program konservasi energi</h3>
        <table class="w-full border-collapse mb-4"><tbody>
          <tr v-for="(p, i) in s.program" :key="'p' + i" class="border-b border-stone-100"><td class="p-1.5">{{ p.judul }}</td><td class="p-1.5 text-right">{{ n(p.target_hemat_gj, 0) }} GJ</td><td class="p-1.5">{{ p.status }}</td></tr>
          <tr v-for="(p, i) in s.program_berikut" :key="'b' + i" class="border-b border-stone-100"><td class="p-1.5">Rencana tahun berikut: {{ p.judul }}</td><td class="p-1.5 text-right">{{ n(p.target_hemat_gj, 0) }} GJ</td><td class="p-1.5">{{ p.status }}</td></tr>
        </tbody></table>
      </template>

      <template v-if="s.penghematan">
        <p class="mb-4">Penghematan terverifikasi M&V: {{ s.penghematan.jumlah_terverifikasi }} inisiatif · {{ n(s.penghematan.terverifikasi_gj_bulan * 12, 0) }} GJ/tahun · {{ n(s.penghematan.terverifikasi_tco2e_bulan * 12, 1) }} tCO₂e/tahun · {{ rp(s.penghematan.terverifikasi_rp_bulan * 12) }}/tahun.</p>
      </template>

      <template v-if="s.deret">
        <h3 class="font-bold text-[12.5px] mb-2">Efisiensi energi & penurunan emisi 4 tahun</h3>
        <table class="w-full border-collapse mb-4"><thead><tr class="border-b border-stone-300 text-left text-stone-500"><th class="p-1.5">Tahun</th><th class="p-1.5 text-right">GJ</th><th class="p-1.5 text-right">GJ/ton</th><th class="p-1.5 text-right">tCO₂e</th><th class="p-1.5 text-right">tCO₂e/ton</th></tr></thead>
          <tbody><tr v-for="d in s.deret" :key="d.tahun" class="border-b border-stone-100"><td class="p-1.5">{{ d.tahun }}</td><td class="p-1.5 text-right">{{ n(d.gj, 0) }}</td><td class="p-1.5 text-right">{{ n(d.gj_ton, 4) }}</td><td class="p-1.5 text-right">{{ n(d.tco2e, 1) }}</td><td class="p-1.5 text-right">{{ n(d.tco2e_ton, 5) }}</td></tr></tbody></table>
      </template>

      <template v-if="s.mitigasi?.length">
        <h3 class="font-bold text-[12.5px] mb-2">Aksi mitigasi</h3>
        <table class="w-full border-collapse mb-4"><tbody><tr v-for="(m, i) in s.mitigasi" :key="i" class="border-b border-stone-100"><td class="p-1.5">{{ m.judul }} ({{ m.jenis }})</td><td class="p-1.5 text-right">baseline {{ n(m.baseline_tco2e_tahun, 1) }} · target −{{ n(m.target_penurunan_tco2e_tahun, 1) }} · capaian {{ n(m.capaian_tahun, 1) }} tCO₂e</td><td class="p-1.5">{{ m.status_srn }}{{ m.nomor_srn ? ' ' + m.nomor_srn : '' }}</td></tr></tbody></table>
      </template>

      <template v-if="s.target">
        <p class="mb-4">Target iklim: −{{ n(s.target.penurunan_persen, 0) }}% pada {{ s.target.tahun ?? '—' }} terhadap tahun dasar {{ s.target.tahun_dasar ?? '—' }} · harga karbon internal {{ rp(s.harga_karbon) }}/tCO₂e.</p>
      </template>

      <template v-if="s.kartu">
        <h3 class="font-bold text-[12.5px] mb-2">Kartu kinerja direksi</h3>
        <table class="w-full border-collapse mb-4"><tbody><tr v-for="k in s.kartu" :key="k.kode" class="border-b border-stone-100"><td class="p-1.5">{{ k.label }}</td><td class="p-1.5 text-right">{{ n(k.nilai, 3) }} {{ k.satuan }}</td><td class="p-1.5">{{ k.rag }}</td></tr></tbody></table>
      </template>

      <h3 class="font-bold text-[12.5px] mb-2">Faktor yang dipakai</h3>
      <table class="w-full border-collapse mb-4 text-[9.5px]">
        <thead><tr class="border-b border-stone-300 text-left text-stone-500"><th class="p-1">#</th><th class="p-1">Kode/parameter</th><th class="p-1">Nilai</th><th class="p-1">Berlaku</th><th class="p-1">Tier</th><th class="p-1">Sumber</th></tr></thead>
        <tbody><tr v-for="f in s.faktor" :key="f.id" class="border-b border-stone-100"><td class="p-1">{{ f.id }}</td><td class="p-1">{{ f.kode_energi }}/{{ f.parameter }}{{ f.kategori_ipcc ? ' (' + f.kategori_ipcc + ')' : '' }}</td><td class="p-1">{{ f.nilai }} {{ f.satuan }}</td><td class="p-1">{{ tgl(f.berlaku_mulai) }}</td><td class="p-1">{{ f.tier }}</td><td class="p-1">{{ f.sumber_dokumen }}{{ f.perlu_cocok ? ' — perlu dicocokkan' : '' }}</td></tr></tbody>
      </table>

      <p class="text-[9.5px] text-stone-500">Struktur laporan mengikuti rancangan FSD Energi-Karbon; format resmi (mis. Lampiran I Permen ESDM 8/2025) wajib dicocokkan dengan naskah resminya sebelum disampaikan. Hash SHA-256 snapshot: {{ props.laporan.hash ?? '— (belum terbit)' }}.</p>
    </div>
  </PrintShell>
</template>

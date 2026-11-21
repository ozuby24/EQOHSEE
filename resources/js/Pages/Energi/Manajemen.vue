<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import KartuAngka from '../../Components/Energi/KartuAngka.vue';
import Lencana from '../../Components/Energi/Lencana.vue';
import PilihPerusahaan from '../../Components/Energi/PilihPerusahaan.vue';
import Meter from '../../Grafik/Meter.vue';
import { n, tgl } from '../../energi';

const props = propHalaman();
const a = props.atur ?? {};
const form = useForm<Record<string, any>>({
  kategori_pengguna: a.kategori_pengguna ?? 'industri', ambang_toe: a.ambang_toe ?? '', sistem_grid: a.sistem_grid ?? '', emiten: !!a.emiten,
  ambang_kelengkapan_persen: a.ambang_kelengkapan_persen ?? 95, ambang_rekonsiliasi_persen: a.ambang_rekonsiliasi_persen ?? 2,
  ambang_rag_persen: a.ambang_rag_persen ?? 5, target_tahun: a.target_tahun ?? '', target_penurunan_persen: a.target_penurunan_persen ?? '',
  tanggal_proper: a.tanggal_proper ? String(a.tanggal_proper).slice(0, 10) : '', tanggal_laporan_tahunan: a.tanggal_laporan_tahunan ? String(a.tanggal_laporan_tahunan).slice(0, 10) : '',
});
const simpan = () => form.transform(d => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v]))).post('/energi/manajemen/pengaturan', { preserveScroll: true });
</script>

<template>
  <Head title="Kewajiban & Ambang" />
  <div class="space-y-4">
    <div class="flex justify-end"><PilihPerusahaan :daftar="props.daftarPerusahaan" :terpilih="props.perusahaan" :nama="props.namaPerusahaan" /></div>

    <section class="grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
      <Kartu judul="Ambang konsumsi energi" :ket="props.ambang.kategori_label">
        <template #aksi><Lencana :status="props.ambang.status" /></template>
        <Meter :nilai="props.ambang.toe_lalu" :target="props.ambang.ambang" :maks="Math.max(props.ambang.ambang, props.ambang.toe_lalu, props.ambang.toe_proyeksi) * 1.25 || 1" satuan=" TOE" sebutan="ambang" kecil-lebih-baik />
        <div class="ubin-kisi mt-4">
          <KartuAngka :label="`Konsumsi ${props.ambang.tahun - 1}`" :nilai="props.ambang.toe_lalu" satuan="TOE" />
          <KartuAngka :label="`YTD ${props.ambang.tahun}`" :nilai="props.ambang.toe_ytd" satuan="TOE" />
          <KartuAngka label="Proyeksi akhir tahun" :nilai="props.ambang.toe_proyeksi" satuan="TOE" :catatan="`Ambang ${n(props.ambang.ambang, 0)} TOE ≈ ${n(props.ambang.gj_setara_ambang, 0)} GJ`" />
        </div>
      </Kartu>

      <Kartu judul="Kewajiban manajemen energi">
        <ul class="divide-y divide-stone-100">
          <li v-for="c in props.checklist" :key="c.label" class="py-2.5 flex items-start justify-between gap-3">
            <div class="min-w-0"><p class="text-[12.5px] font-semibold text-cam-ink" :title="c.dasar">{{ c.label }}</p><p v-if="c.rincian" class="text-[11px] text-stone-500">{{ c.rincian }}</p></div>
            <span class="shrink-0 rounded-md px-1.5 py-0.5 text-[10.5px] font-semibold" :class="c.ok === null ? 'bg-stone-100 text-stone-600' : c.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">{{ c.ok === null ? 'Tidak berlaku' : c.ok ? 'Terpenuhi' : 'Belum' }}</span>
          </li>
        </ul>
        <div class="flex flex-wrap gap-2 mt-3">
          <Link href="/energi/manajemen/personel" class="eq-btn-mini">Manajer & auditor</Link>
          <Link href="/energi/manajemen/program" class="eq-btn-mini">Program konservasi</Link>
          <Link href="/energi/manajemen/audit" class="eq-btn-mini">Audit energi</Link>
          <Link href="/energi/manajemen/tinjauan" class="eq-btn-mini">Monev</Link>
        </div>
      </Kartu>
    </section>

    <Kartu judul="Kalender kewajiban">
      <template #aksi><Link href="/energi/kalender" class="eq-tautan">Kalender lengkap</Link></template>
      <table class="w-full text-[12px]">
        <thead><tr class="text-left text-stone-400 border-b border-stone-100"><th class="py-2">Kewajiban</th><th class="py-2">Dasar</th><th class="py-2">Tenggat</th><th class="py-2">Status</th></tr></thead>
        <tbody>
          <tr v-for="k in props.kewajiban" :key="k.kode" class="border-b border-stone-50">
            <td class="py-2 font-semibold">{{ k.judul }}<span v-if="k.keterangan" class="block text-[10.5px] text-stone-400 font-normal">{{ k.keterangan }}</span></td>
            <td class="py-2 text-stone-500">{{ k.dasar }}</td>
            <td class="py-2">{{ tgl(k.tenggat) }}<span v-if="k.sisa_hari !== null && k.status !== 'selesai' && k.status !== 'tidak_berlaku'" class="block text-[10.5px]" :class="k.sisa_hari < 0 ? 'text-red-600' : 'text-stone-400'">{{ k.sisa_hari < 0 ? `lewat ${-k.sisa_hari} hari` : `${k.sisa_hari} hari lagi` }}</span></td>
            <td class="py-2"><Lencana :status="k.status" /></td>
          </tr>
        </tbody>
      </table>
    </Kartu>

    <Kartu judul="Pengaturan">
      <form class="grid gap-3 md:grid-cols-4" @submit.prevent="simpan">
        <label class="text-[11px] font-semibold text-stone-500 md:col-span-2">Kategori pengguna energi<select v-model="form.kategori_pengguna" :disabled="!props.boleh.kelola" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option v-for="[k, l] in Object.entries((props.pilihan.kategori ?? {}) as Record<string, any>)" :key="k" :value="k">{{ l }}</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500">Ambang khusus (TOE)<input v-model="form.ambang_toe" type="number" step="any" min="0" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Sistem grid bawaan<select v-model="form.sistem_grid" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option value="">Nasional</option><option v-for="[k, l] in Object.entries((props.pilihan.grid ?? {}) as Record<string, any>)" :key="k" :value="k">{{ l }}</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500">Ambang kelengkapan kunci (%)<input v-model="form.ambang_kelengkapan_persen" type="number" step="any" min="0" max="100" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Ambang selisih rekonsiliasi (%)<input v-model="form.ambang_rekonsiliasi_persen" type="number" step="any" min="0" max="100" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Toleransi RAG kuning (%)<input v-model="form.ambang_rag_persen" type="number" step="any" min="0" max="100" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500 flex items-center gap-2 mt-5"><input v-model="form.emiten" type="checkbox"> Perusahaan emiten (POJK 51, PSPK 2)</label>
        <label class="text-[11px] font-semibold text-stone-500">Tahun target dekarbonisasi<input v-model="form.target_tahun" type="number" min="2020" max="2100" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Target penurunan vs tahun dasar (%)<input v-model="form.target_penurunan_persen" type="number" step="any" min="0" max="100" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Tenggat data PROPER<input v-model="form.tanggal_proper" type="date" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Tenggat laporan tahunan<input v-model="form.tanggal_laporan_tahunan" type="date" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <div v-if="props.boleh.kelola" class="md:col-span-4"><button class="eq-btn-utama" :disabled="form.processing">Simpan pengaturan</button></div>
      </form>
      <p v-for="(pesan, k) in form.errors" :key="k" class="text-[11px] text-red-600 mt-1">{{ pesan }}</p>
    </Kartu>
  </div>
</template>

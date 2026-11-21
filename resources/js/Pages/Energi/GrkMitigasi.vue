<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import { n, tgl } from '../../energi';

const props = propHalaman();
const tahunIni = new Date().getFullYear();
const kosong = () => ({ judul: '', jenis: 'efisiensi', site_id: '', peluang_id: '', mulai: new Date().toISOString().slice(0, 10), selesai: '',
  metodologi: '', baseline_tco2e_tahun: '', target_penurunan_tco2e_tahun: '', capaian: {} as Record<string, any>, status_srn: 'belum', nomor_srn: '', catatan: '' });
const form = useForm<Record<string, any>>(kosong());
const sunting = ref<number | null>(null);

function ubah(m: any) {
  sunting.value = m.id;
  Object.assign(form, { ...kosong(), ...m, site_id: '', peluang_id: '', capaian: { ...(m.capaian ?? {}) } });
}
const kirim = () => {
  const t = form.transform(d => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v])));
  if (sunting.value) t.put(`/energi/grk/mitigasi/${sunting.value}`, { preserveScroll: true, onSuccess: () => { sunting.value = null; form.defaults(kosong()); form.reset(); } });
  else t.post('/energi/grk/mitigasi', { preserveScroll: true, onSuccess: () => form.reset() });
};
</script>

<template>
  <Head title="Aksi Mitigasi" />
  <div class="space-y-4">
    <Kartu judul="Aksi mitigasi">
      <div class="overflow-x-auto">
        <table class="w-full text-[12px] min-w-[820px]">
          <thead><tr class="text-left text-stone-400 border-b border-stone-100"><th class="py-2">Aksi</th><th class="py-2">Jenis</th><th class="py-2">Periode</th><th class="py-2 text-right">Baseline</th><th class="py-2 text-right">Target turun/thn</th><th class="py-2 text-right">Capaian {{ tahunIni }}</th><th class="py-2">SRN PPI</th><th class="py-2"></th></tr></thead>
          <tbody>
            <tr v-for="m in props.daftar" :key="m.id" class="border-b border-stone-50">
              <td class="py-2 font-semibold">{{ m.judul }}<span class="block text-[10.5px] text-stone-400">{{ m.site ?? '' }} {{ m.metodologi ?? '' }}</span></td>
              <td class="py-2">{{ props.jenis[m.jenis] }}</td><td class="py-2">{{ tgl(m.mulai) }} – {{ m.selesai ? tgl(m.selesai) : 'berjalan' }}</td>
              <td class="py-2 text-right num">{{ n(m.baseline_tco2e_tahun, 1) }}</td><td class="py-2 text-right num">{{ n(m.target_penurunan_tco2e_tahun, 1) }}</td>
              <td class="py-2 text-right num">{{ n(m.capaian?.[tahunIni] ?? null, 1) }}</td>
              <td class="py-2">{{ props.statusSrn[m.status_srn] }}<span v-if="m.nomor_srn" class="block text-[10.5px] text-stone-400">{{ m.nomor_srn }}</span></td>
              <td class="py-2 text-right"><button v-if="props.boleh.kelola" type="button" class="eq-btn-mini" @click="ubah(m)">Ubah</button></td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="!props.daftar?.length" class="text-[12px] text-stone-400 py-4 text-center">Belum ada aksi mitigasi tercatat.</p>
    </Kartu>

    <Kartu v-if="props.boleh.kelola" :judul="sunting ? 'Ubah aksi mitigasi' : 'Catat aksi mitigasi'">
      <form class="grid gap-3 md:grid-cols-4" @submit.prevent="kirim">
        <label class="text-[11px] font-semibold text-stone-500 md:col-span-2">Judul<input v-model="form.judul" required maxlength="200" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Jenis<select v-model="form.jenis" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option v-for="[k, l] in Object.entries((props.jenis ?? {}) as Record<string, any>)" :key="k" :value="k">{{ l }}</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500">Site<select v-model="form.site_id" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option value="">—</option><option v-for="s in props.sites" :key="s.id" :value="s.id">{{ s.nama }}</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500">Mulai<input v-model="form.mulai" required type="date" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Selesai<input v-model="form.selesai" type="date" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Baseline (tCO₂e/thn)<input v-model="form.baseline_tco2e_tahun" type="number" step="any" min="0" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Target penurunan (tCO₂e/thn)<input v-model="form.target_penurunan_tco2e_tahun" type="number" step="any" min="0" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Capaian {{ tahunIni }} (tCO₂e)<input v-model="form.capaian[tahunIni]" type="number" step="any" min="0" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Peluang terkait<select v-model="form.peluang_id" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option value="">—</option><option v-for="p in props.peluang" :key="p.id" :value="p.id">{{ p.judul }}</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500">Status SRN<select v-model="form.status_srn" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option v-for="[k, l] in Object.entries((props.statusSrn ?? {}) as Record<string, any>)" :key="k" :value="k">{{ l }}</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500">Nomor SRN<input v-model="form.nomor_srn" maxlength="60" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500 md:col-span-2">Metodologi<input v-model="form.metodologi" maxlength="255" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500 md:col-span-2">Catatan<input v-model="form.catatan" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <div class="md:col-span-4 flex gap-2"><button class="eq-btn-utama" :disabled="form.processing">Simpan</button><button v-if="sunting" type="button" class="eq-btn-lain" @click="sunting = null; form.defaults(kosong()); form.reset()">Batal</button></div>
      </form>
      <p v-for="(pesan, k) in form.errors" :key="k" class="text-[11px] text-red-600 mt-1">{{ pesan }}</p>
    </Kartu>
  </div>
</template>

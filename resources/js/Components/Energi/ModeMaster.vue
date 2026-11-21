<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import Kartu from './Kartu.vue';
import Dialog from '../Dialog.vue';
import { useDialog } from '../../dialog';

const props = defineProps<{ units: any[]; companies: any[]; sites: any[]; pilihan: Record<string, any>; boleh: Record<string, any> }>();
const { dialog, tanya, batal, lanjut } = useDialog();

const unit = useForm<Record<string, any>>({ company_id: '', kode: '', nama: '', kategori: 'hauling', merek: '', daya_hp: '', payload_ton: '', site_id: '', kategori_ipcc: 'bergerak_offroad' });
const site = useForm<Record<string, any>>({ kode: '', nama: '', jenis: 'pit', provinsi: '', sistem_grid: '', zona_waktu: 'Asia/Makassar' });

const simpanUnit = () => unit.post('/energi/data-induk', { preserveScroll: true, onSuccess: () => unit.reset('kode', 'nama', 'merek', 'daya_hp', 'payload_ton') });
const simpanSite = () => site.post('/energi/data-induk/site', { preserveScroll: true, onSuccess: () => site.reset() });
function ubahUnit(u: any, isi: Record<string, any>) {
  router.put(`/energi/data-induk/${u.id}`, { nama: u.nama, kategori: u.kategori, site_id: u.site_id, kategori_ipcc: u.kategori_ipcc, aktif: u.aktif, ...isi }, { preserveScroll: true });
}
async function hapusUnit(u: any) {
  const pesan = u.fuel_logs_count > 0 ? `${u.kode} sudah punya ${u.fuel_logs_count} catatan BBM dan akan DINONAKTIFKAN, bukan dihapus. Lanjutkan?` : `Hapus unit ${u.kode}?`;
  if (await tanya(pesan)) router.delete(`/energi/data-induk/${u.id}`, { preserveScroll: true });
}
</script>

<template>
  <div class="space-y-4">
    <Kartu judul="Site" ket="Sistem ketenagalistrikan site menentukan faktor grid Scope 2 (Kepmen ESDM 163.K/2021).">
      <table class="w-full text-[12px] mb-4">
        <thead><tr class="text-left text-stone-400 border-b border-stone-100"><th class="py-2">Kode</th><th class="py-2">Nama</th><th class="py-2">Jenis</th><th class="py-2">Provinsi</th><th class="py-2">Sistem grid</th><th class="py-2">Zona</th></tr></thead>
        <tbody>
          <tr v-for="s in sites" :key="s.id" class="border-b border-stone-50">
            <td class="py-2 font-semibold">{{ s.kode }}</td><td class="py-2">{{ s.nama }}</td><td class="py-2">{{ pilihan.jenisSite?.[s.jenis] ?? s.jenis }}</td>
            <td class="py-2">{{ s.provinsi ?? '—' }}</td><td class="py-2">{{ pilihan.grid?.[s.sistem_grid] ?? 'Nasional (bawaan)' }}</td><td class="py-2">{{ pilihan.zona?.[s.zona_waktu] ?? s.zona_waktu }}</td>
          </tr>
        </tbody>
      </table>
      <form v-if="boleh.kelola" class="grid gap-3 md:grid-cols-6 items-end" @submit.prevent="simpanSite">
        <label class="text-[11px] font-semibold text-stone-500">Kode<input v-model="site.kode" required class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Nama<input v-model="site.nama" required class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Jenis<select v-model="site.jenis" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option v-for="(l, k) in pilihan.jenisSite" :key="k" :value="k">{{ l }}</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500">Provinsi<input v-model="site.provinsi" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Sistem grid<select v-model="site.sistem_grid" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option value="">Nasional (bawaan)</option><option v-for="(l, k) in pilihan.grid" :key="k" :value="k">{{ l }}</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500">Zona waktu<select v-model="site.zona_waktu" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option v-for="(l, k) in pilihan.zona" :key="k" :value="k">{{ l }}</option></select></label>
        <button class="eq-btn-utama md:col-span-6 justify-self-start" :disabled="site.processing">Tambah site</button>
      </form>
      <p v-for="(pesan, k) in site.errors" :key="k" class="text-[11px] text-red-600 mt-1">{{ pesan }}</p>
    </Kartu>

    <Kartu judul="Unit alat" ket="Kategori IPCC menentukan faktor CH₄/N₂O: alat tambang off-road, kendaraan jalan, atau stasioner.">
      <table class="w-full text-[12px] mb-4">
        <thead><tr class="text-left text-stone-400 border-b border-stone-100"><th class="py-2">Kode</th><th class="py-2">Nama</th><th class="py-2">Kategori</th><th class="py-2">Site</th><th class="py-2">Kategori IPCC</th><th class="py-2">Aktif</th><th class="py-2"></th></tr></thead>
        <tbody>
          <tr v-for="u in units" :key="u.id" class="border-b border-stone-50">
            <td class="py-2 font-semibold">{{ u.kode }}</td><td class="py-2">{{ u.nama }}</td><td class="py-2">{{ pilihan.kategori?.[u.kategori] ?? u.kategori }}</td>
            <td class="py-2">
              <select :value="u.site_id ?? ''" class="rounded border-stone-200 text-[11px]" aria-label="Site unit" @change="ubahUnit(u, { site_id: ($event.target as HTMLSelectElement).value || null })">
                <option value="">—</option><option v-for="s in sites" :key="s.id" :value="s.id">{{ s.nama }}</option>
              </select>
            </td>
            <td class="py-2">
              <select :value="u.kategori_ipcc" class="rounded border-stone-200 text-[11px]" aria-label="Kategori IPCC unit" @change="ubahUnit(u, { kategori_ipcc: ($event.target as HTMLSelectElement).value })">
                <option value="bergerak_offroad">Off-road</option><option value="bergerak_jalan">Jalan</option><option value="stasioner">Stasioner</option>
              </select>
            </td>
            <td class="py-2">{{ u.aktif ? 'Aktif' : 'Nonaktif' }}</td>
            <td class="py-2 text-right"><button v-if="boleh.admin" type="button" class="text-red-600 text-[11px]" @click="hapusUnit(u)">Hapus</button></td>
          </tr>
        </tbody>
      </table>
      <form class="grid gap-3 md:grid-cols-4 items-end" @submit.prevent="simpanUnit">
        <label v-if="companies.length" class="text-[11px] font-semibold text-stone-500">Perusahaan<select v-model="unit.company_id" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option value="">Semua perusahaan</option><option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500">Kode<input v-model="unit.kode" required class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Nama<input v-model="unit.nama" required class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Kategori<select v-model="unit.kategori" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option v-for="(l, k) in pilihan.kategori" :key="k" :value="k">{{ l }}</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500">Site<select v-model="unit.site_id" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option value="">—</option><option v-for="s in sites" :key="s.id" :value="s.id">{{ s.nama }}</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500">Kategori IPCC<select v-model="unit.kategori_ipcc" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option value="bergerak_offroad">Off-road</option><option value="bergerak_jalan">Jalan</option><option value="stasioner">Stasioner</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500">Merek<input v-model="unit.merek" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Daya (HP)<input v-model="unit.daya_hp" type="number" min="0" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Payload (ton)<input v-model="unit.payload_ton" type="number" step="any" min="0" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <button class="eq-btn-utama" :disabled="unit.processing">Tambah unit</button>
      </form>
      <p v-for="(pesan, k) in unit.errors" :key="k" class="text-[11px] text-red-600 mt-1">{{ pesan }}</p>
    </Kartu>
  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>

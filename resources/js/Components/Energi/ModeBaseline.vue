<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import Kartu from './Kartu.vue';
import Lencana from './Lencana.vue';
import { n } from '../../energi';

const props = defineProps<{ daftar: any[]; baseline: Record<string, any> | null; r: Record<string, any>; companies: any[] }>();
const form = useForm<Record<string, any>>({ company_id: '', tahun: new Date().getFullYear(), baseline_gj_ton: '', target_gj_ton: '', catatan: '' });
const simpan = () => form.post('/energi/baseline', { preserveScroll: true, onSuccess: () => form.reset('baseline_gj_ton', 'target_gj_ton', 'catatan') });
</script>

<template>
  <div class="space-y-4">
    <Kartu judul="Baseline & target intensitas" ket="Dasbor direksi hanya memakai target yang sudah disetujui.">
      <template #aksi><Link href="/energi/enpi" class="eq-btn-lain">Susun EnB regresi</Link></template>
      <form class="grid gap-3 md:grid-cols-3" @submit.prevent="simpan">
        <label v-if="props.companies.length" class="text-[11px] font-semibold text-stone-500">Perusahaan
          <select v-model="form.company_id" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]">
            <option value="">Semua perusahaan</option>
            <option v-for="c in props.companies" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </label>
        <label class="text-[11px] font-semibold text-stone-500">Tahun<input v-model="form.tahun" type="number" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Baseline (GJ/ton)<input v-model="form.baseline_gj_ton" type="number" step="any" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Target (GJ/ton)<input v-model="form.target_gj_ton" type="number" step="any" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500 md:col-span-2">Catatan<input v-model="form.catatan" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <div class="flex items-end"><button class="eq-btn-utama w-full" :disabled="form.processing">Simpan baseline</button></div>
      </form>
      <p v-if="form.errors.target_gj_ton" class="text-[11px] text-red-600 mt-2">{{ form.errors.target_gj_ton }}</p>
      <p class="text-[11.5px] text-stone-500 mt-3">Intensitas periode terpilih: <b>{{ n(r.intensitas, 4) }} GJ/ton</b>.</p>
    </Kartu>

    <Kartu judul="Riwayat baseline">
      <table class="w-full text-[12px]">
        <thead><tr class="text-left text-stone-400 border-b border-stone-100"><th class="py-2">Tahun</th><th class="py-2 text-right">Baseline</th><th class="py-2 text-right">Target</th><th class="py-2 text-right">Penurunan</th><th class="py-2">Status</th><th class="py-2">Catatan</th></tr></thead>
        <tbody>
          <tr v-for="b in daftar" :key="b.id" class="border-b border-stone-50">
            <td class="py-2 font-semibold">{{ b.tahun }}</td><td class="py-2 text-right num">{{ n(b.baseline_gj_ton, 4) }}</td>
            <td class="py-2 text-right num">{{ n(b.target_gj_ton, 4) }}</td>
            <td class="py-2 text-right num">{{ b.baseline_gj_ton > 0 ? n((b.baseline_gj_ton - b.target_gj_ton) / b.baseline_gj_ton * 100, 1) + '%' : '—' }}</td>
            <td class="py-2"><Lencana :status="b.status ?? 'disetujui_bod'" /></td><td class="py-2 text-stone-500">{{ b.catatan ?? '—' }}</td>
          </tr>
        </tbody>
      </table>
      <p v-if="!daftar.length" class="text-[12px] text-stone-400 py-4 text-center">Belum ada baseline.</p>
    </Kartu>
  </div>
</template>

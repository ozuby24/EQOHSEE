<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Lencana from '../../Components/Energi/Lencana.vue';
import { n, tgl } from '../../energi';

const props = propHalaman();
const form = useForm<Record<string, any>>({ jenis: 'internal', judul: '', ruang_lingkup: '', seu: [] as string[], auditor_ids: [] as number[],
  mulai: new Date().toISOString().slice(0, 10), selesai: '', standar: 'SNI ISO 50002', status: 'direncanakan', ringkasan: '' });
const kirim = () => form.transform(d => ({ ...d, selesai: d.selesai || null })).post('/energi/manajemen/audit');
</script>

<template>
  <Head title="Audit Energi" />
  <div class="space-y-4">
    <Kartu judul="Audit energi" ket="Minimal sekali dalam tiga tahun dan mencakup minimal satu SEU.">
      <table class="w-full text-[12px]">
        <thead><tr class="text-left text-stone-400 border-b border-stone-100"><th class="py-2">Audit</th><th class="py-2">Jenis</th><th class="py-2">Periode</th><th class="py-2">SEU yang dicakup</th><th class="py-2 text-right">Rekomendasi</th><th class="py-2">Status</th></tr></thead>
        <tbody>
          <tr v-for="a in props.daftar" :key="a.id" class="border-b border-stone-50">
            <td class="py-2"><Link :href="a.tautan" class="font-semibold text-cam-lime-deep">{{ a.judul }}</Link><span class="block text-[10.5px] text-stone-400">{{ a.standar ?? '' }}</span></td>
            <td class="py-2">{{ a.jenis }}</td><td class="py-2">{{ tgl(a.mulai) }} – {{ tgl(a.selesai) }}</td>
            <td class="py-2">{{ a.seu.length ? a.seu.join(', ') : '— belum ada SEU' }}</td>
            <td class="py-2 text-right num">{{ a.rekomendasi }}</td><td class="py-2"><Lencana :status="a.status" /></td>
          </tr>
        </tbody>
      </table>
      <p v-if="!props.daftar?.length" class="text-[12px] text-stone-400 py-4 text-center">Belum ada audit energi tercatat.</p>
    </Kartu>

    <Kartu v-if="props.boleh.kelola" judul="Catat audit energi">
      <form class="grid gap-3 md:grid-cols-4" @submit.prevent="kirim">
        <label class="text-[11px] font-semibold text-stone-500 md:col-span-2">Judul<input v-model="form.judul" required maxlength="200" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Jenis<select v-model="form.jenis" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option value="internal">Internal</option><option value="eksternal">Eksternal</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500">Status<select v-model="form.status" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option v-for="s in props.status" :key="s" :value="s">{{ s }}</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500">Mulai<input v-model="form.mulai" required type="date" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Selesai<input v-model="form.selesai" type="date" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Standar<input v-model="form.standar" maxlength="100" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Auditor<select v-model="form.auditor_ids" multiple class="block w-full mt-1 rounded-lg border-stone-200 text-[12px] h-20"><option v-for="a in props.auditor" :key="a.id" :value="a.id">{{ a.nama }}</option></select></label>
        <fieldset class="md:col-span-4">
          <legend class="text-[11px] font-semibold text-stone-500 mb-1">SEU yang dicakup</legend>
          <div class="flex flex-wrap gap-2">
            <label v-for="s in props.seu" :key="s.kunci" class="text-[11px] rounded-lg bg-stone-50 px-2 py-1 flex items-center gap-1.5"><input v-model="form.seu" type="checkbox" :value="s.kunci"> {{ s.label }} <span class="text-stone-400">{{ n(s.persen, 1) }}%</span><span v-if="s.usul_seu" class="text-amber-700 font-semibold">SEU</span></label>
          </div>
        </fieldset>
        <label class="text-[11px] font-semibold text-stone-500 md:col-span-4">Ruang lingkup<textarea v-model="form.ruang_lingkup" rows="2" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></textarea></label>
        <div><button class="eq-btn-utama" :disabled="form.processing">Simpan audit</button></div>
      </form>
      <p v-for="(pesan, k) in form.errors" :key="k" class="text-[11px] text-red-600 mt-1">{{ pesan }}</p>
    </Kartu>
  </div>
</template>

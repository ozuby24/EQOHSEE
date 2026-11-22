<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Lencana from '../../Components/Energi/Lencana.vue';
import { n, rp } from '../../energi';

const props = propHalaman();
const kosong = () => ({ tahun: props.tahun, judul: '', sasaran: '', target_hemat_gj: '', target_hemat_tco2e: '', anggaran_rp: '', pic: '', status: 'rencana', peluang_ids: [] as number[] });
const form = useForm<Record<string, any>>(kosong());
const sunting = ref<number | null>(null);
const ubah = (p: any) => { sunting.value = p.id; Object.assign(form, { ...kosong(), ...p, peluang_ids: p.peluang_ids ?? [] }); };
const kirim = () => {
  const t = form.transform(d => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v])));
  if (sunting.value) t.put(`/energi/manajemen/program/${sunting.value}`, { preserveScroll: true, onSuccess: () => { sunting.value = null; form.defaults(kosong()); form.reset(); } });
  else t.post('/energi/manajemen/program', { preserveScroll: true, onSuccess: () => form.reset() });
};
const gantiTahun = (e: Event) => router.get('/energi/manajemen/program', { tahun: (e.target as HTMLInputElement).value });
const namaPeluang = (id: number) => (props.peluang ?? []).find((p: any) => p.id === id)?.judul ?? '#' + id;
</script>

<template>
  <Head title="Program Konservasi" />
  <div class="space-y-4">
    <label class="text-[11px] font-semibold text-stone-500 inline-block">Tahun program<input :value="props.tahun" type="number" min="2000" max="2100" class="block mt-1 rounded-lg border-stone-200 text-[12px] w-28" @change="gantiTahun"></label>

    <Kartu ikon="sprout" warna="hijau" :judul="`Program konservasi energi ${props.tahun}`">
      <article v-for="p in props.daftar" :key="p.id" class="border-b border-stone-100 py-3 last:border-0">
        <div class="flex flex-wrap items-start justify-between gap-2">
          <div class="min-w-0"><p class="text-[13px] font-semibold text-cam-ink">{{ p.judul }}</p><p class="text-[11.5px] text-stone-500 whitespace-pre-line">{{ p.sasaran }}</p></div>
          <div class="flex items-center gap-2"><Lencana :status="p.status" /><button v-if="props.boleh.kelola" type="button" class="eq-btn-mini" @click="ubah(p)">Ubah</button></div>
        </div>
        <p class="text-[11.5px] text-stone-600 mt-1">Target {{ n(p.target_hemat_gj, 0) }} GJ · {{ n(p.target_hemat_tco2e, 1) }} tCO₂e · anggaran {{ rp(p.anggaran_rp) }} · PIC {{ p.pic ?? '—' }}</p>
        <p v-if="p.peluang_ids?.length" class="text-[10.5px] text-stone-400">Peluang: {{ p.peluang_ids.map(namaPeluang).join(', ') }}</p>
      </article>
      <p v-if="!props.daftar?.length" class="text-[12px] text-stone-400 py-4 text-center">Belum ada program konservasi tahun ini.</p>
    </Kartu>

    <Kartu v-if="props.boleh.kelola" :judul="sunting ? 'Ubah program' : 'Tambah program'">
      <form class="grid gap-3 md:grid-cols-4" @submit.prevent="kirim">
        <label class="text-[11px] font-semibold text-stone-500 md:col-span-2">Judul<input v-model="form.judul" required maxlength="200" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Tahun<input v-model="form.tahun" type="number" min="2000" max="2100" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Status<select v-model="form.status" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option v-for="s in props.status" :key="s" :value="s">{{ s }}</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500 md:col-span-4">Sasaran & kegiatan<textarea v-model="form.sasaran" rows="2" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></textarea></label>
        <label class="text-[11px] font-semibold text-stone-500">Target hemat (GJ)<input v-model="form.target_hemat_gj" type="number" step="any" min="0" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Target hemat (tCO₂e)<input v-model="form.target_hemat_tco2e" type="number" step="any" min="0" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Anggaran (Rp)<input v-model="form.anggaran_rp" type="number" step="any" min="0" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">PIC<input v-model="form.pic" maxlength="150" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500 md:col-span-4">Peluang penghematan terkait
          <select v-model="form.peluang_ids" multiple class="block w-full mt-1 rounded-lg border-stone-200 text-[12px] h-24"><option v-for="p in props.peluang" :key="p.id" :value="p.id">{{ p.judul }} ({{ p.status }})</option></select>
        </label>
        <div class="md:col-span-4 flex gap-2"><button class="eq-btn-utama" :disabled="form.processing">Simpan</button><button v-if="sunting" type="button" class="eq-btn-lain" @click="sunting = null; form.defaults(kosong()); form.reset()">Batal</button></div>
      </form>
      <p v-for="(pesan, k) in form.errors" :key="k" class="text-[11px] text-red-600 mt-1">{{ pesan }}</p>
    </Kartu>
  </div>
</template>

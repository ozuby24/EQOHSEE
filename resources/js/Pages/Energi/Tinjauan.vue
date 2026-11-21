<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import { tgl } from '../../energi';

const props = propHalaman();
const form = useForm<Record<string, any>>({ jenis: 'monev', tanggal: new Date().toISOString().slice(0, 10), peserta: '', masukan: '', keluaran: '' });
const kirim = () => form.post('/energi/manajemen/tinjauan', { preserveScroll: true, onSuccess: () => form.reset('peserta', 'masukan', 'keluaran') });
</script>

<template>
  <Head title="Monev & Tinjauan" />
  <div class="space-y-4">
    <Kartu judul="Monev & tinjauan manajemen" ket="Monev tahunan Manajer Energi ke manajemen puncak (Permen ESDM 8/2025 Ps. 10) dan tinjauan manajemen ISO 50001 klausul 9.3.">
      <article v-for="t in props.daftar" :key="t.id" class="border-b border-stone-100 py-3 last:border-0">
        <p class="text-[13px] font-semibold text-cam-ink">{{ props.jenis[t.jenis] }} — {{ tgl(t.tanggal) }}</p>
        <p class="text-[11px] text-stone-400">Peserta: {{ t.peserta ?? '—' }}</p>
        <div class="grid gap-3 md:grid-cols-2 mt-2 text-[11.5px]">
          <div><p class="font-bold text-stone-600">Masukan</p><p class="whitespace-pre-line text-stone-600">{{ t.masukan ?? '—' }}</p></div>
          <div><p class="font-bold text-stone-600">Keluaran & keputusan</p><p class="whitespace-pre-line text-stone-600">{{ t.keluaran ?? '—' }}</p></div>
        </div>
      </article>
      <p v-if="!props.daftar?.length" class="text-[12px] text-stone-400 py-4 text-center">Belum ada monev atau tinjauan tercatat.</p>
    </Kartu>

    <Kartu v-if="props.boleh.kelola" judul="Catat monev / tinjauan">
      <form class="grid gap-3 md:grid-cols-3" @submit.prevent="kirim">
        <label class="text-[11px] font-semibold text-stone-500">Jenis<select v-model="form.jenis" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option v-for="[k, l] in Object.entries((props.jenis ?? {}) as Record<string, any>)" :key="k" :value="k">{{ l }}</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500">Tanggal<input v-model="form.tanggal" required type="date" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Peserta<input v-model="form.peserta" maxlength="500" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500 md:col-span-3">Masukan (kinerja EnPI vs EnB, status sasaran, hasil audit, kendala)<textarea v-model="form.masukan" rows="3" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></textarea></label>
        <label class="text-[11px] font-semibold text-stone-500 md:col-span-3">Keluaran (keputusan, sumber daya, perubahan sasaran)<textarea v-model="form.keluaran" rows="3" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></textarea></label>
        <div><button class="eq-btn-utama" :disabled="form.processing">Simpan</button></div>
      </form>
    </Kartu>
  </div>
</template>

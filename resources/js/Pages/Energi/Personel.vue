<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import { tgl } from '../../energi';

const props = propHalaman();
const kosong = () => ({ user_id: '', nama: '', peran: 'manajer_energi', internal: true, lembaga: '', nomor_sertifikat: '', lembaga_sertifikasi: '', berlaku_sampai: '', nomor_sk: '', aktif: true });
const form = useForm<Record<string, any>>(kosong());
const sunting = ref<number | null>(null);
const ubah = (p: any) => { sunting.value = p.id; Object.assign(form, { ...kosong(), ...p, user_id: p.user_id ?? '', berlaku_sampai: p.berlaku_sampai ?? '' }); };
const kirim = () => {
  const t = form.transform(d => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v])));
  if (sunting.value) t.put(`/energi/manajemen/personel/${sunting.value}`, { preserveScroll: true, onSuccess: () => { sunting.value = null; form.defaults(kosong()); form.reset(); } });
  else t.post('/energi/manajemen/personel', { preserveScroll: true, onSuccess: () => form.reset() });
};
</script>

<template>
  <Head title="Manajer & Auditor Energi" />
  <div class="space-y-4">
    <Kartu judul="Manajer Energi & Auditor Energi" ket="Permen ESDM 8/2025 Ps. 4: perusahaan wajib menunjuk Manajer Energi bersertifikat kompetensi. Pengingat kedaluwarsa dikirim H-90 dan H-30.">
      <table class="w-full text-[12px]">
        <thead><tr class="text-left text-stone-400 border-b border-stone-100"><th class="py-2">Nama</th><th class="py-2">Peran</th><th class="py-2">Sertifikat</th><th class="py-2">Berlaku sampai</th><th class="py-2">Keadaan</th><th class="py-2"></th></tr></thead>
        <tbody>
          <tr v-for="p in props.daftar" :key="p.id" class="border-b border-stone-50">
            <td class="py-2 font-semibold">{{ p.nama }}<span class="block text-[10.5px] text-stone-400 font-normal">{{ p.internal ? 'Internal' : 'Eksternal — ' + (p.lembaga ?? '') }}<template v-if="p.nomor_sk"> · SK {{ p.nomor_sk }}</template></span></td>
            <td class="py-2">{{ props.peran[p.peran] }}</td>
            <td class="py-2">{{ p.nomor_sertifikat ?? '—' }}<span class="block text-[10.5px] text-stone-400">{{ p.lembaga_sertifikasi ?? '' }}</span></td>
            <td class="py-2">{{ tgl(p.berlaku_sampai) }}<span v-if="p.sisa_hari !== null" class="block text-[10.5px]" :class="p.sisa_hari < 0 ? 'text-red-600' : p.sisa_hari <= 90 ? 'text-amber-700' : 'text-stone-400'">{{ p.sisa_hari < 0 ? 'kedaluwarsa' : `${p.sisa_hari} hari lagi` }}</span></td>
            <td class="py-2"><span class="rounded-full px-2 py-0.5 text-[10.5px] font-bold" :class="p.sah ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">{{ p.sah ? 'Sah' : (p.aktif ? 'Sertifikat tidak berlaku' : 'Nonaktif') }}</span></td>
            <td class="py-2 text-right"><button v-if="props.boleh.kelola" type="button" class="eq-btn-mini" @click="ubah(p)">Ubah</button></td>
          </tr>
        </tbody>
      </table>
      <p v-if="!props.daftar?.length" class="text-[12px] text-stone-400 py-4 text-center">Belum ada Manajer Energi tercatat.</p>
    </Kartu>

    <Kartu v-if="props.boleh.kelola" :judul="sunting ? 'Ubah personel' : 'Catat personel'">
      <form class="grid gap-3 md:grid-cols-4" @submit.prevent="kirim">
        <label class="text-[11px] font-semibold text-stone-500">Nama<input v-model="form.nama" required maxlength="150" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Peran<select v-model="form.peran" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option v-for="[k, l] in Object.entries((props.peran ?? {}) as Record<string, any>)" :key="k" :value="k">{{ l }}</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500">Akun EQOHSEE<select v-model="form.user_id" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option value="">—</option><option v-for="u in props.akun" :key="u.id" :value="u.id">{{ u.name }}</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500 flex items-center gap-2 mt-5"><input v-model="form.internal" type="checkbox"> Internal perusahaan</label>
        <label class="text-[11px] font-semibold text-stone-500">Lembaga (eksternal)<input v-model="form.lembaga" maxlength="150" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">No. sertifikat kompetensi<input v-model="form.nomor_sertifikat" maxlength="100" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Lembaga sertifikasi<input v-model="form.lembaga_sertifikasi" maxlength="150" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Berlaku sampai<input v-model="form.berlaku_sampai" type="date" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">No. SK penunjukan<input v-model="form.nomor_sk" maxlength="100" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500 flex items-center gap-2 mt-5"><input v-model="form.aktif" type="checkbox"> Aktif</label>
        <div class="md:col-span-4 flex gap-2"><button class="eq-btn-utama" :disabled="form.processing">Simpan</button><button v-if="sunting" type="button" class="eq-btn-lain" @click="sunting = null; form.defaults(kosong()); form.reset()">Batal</button></div>
      </form>
      <p v-for="(pesan, k) in form.errors" :key="k" class="text-[11px] text-red-600 mt-1">{{ pesan }}</p>
    </Kartu>
  </div>
</template>

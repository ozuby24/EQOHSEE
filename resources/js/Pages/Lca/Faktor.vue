<script setup lang="ts">
/*
  Pustaka faktor dampak LCA.

  Baris BERSAMA berasal dari pustaka bawaan (lca:pasang) dan dipakai
  seluruh perusahaan; baris PERUSAHAAN ditambahkan sendiri. Faktor
  bawaan bertanda "belum dicocokkan" sampai petugas memeriksanya
  terhadap dokumen sumbernya — studi yang bersandar padanya diberi tahu
  pada daftar periksa ISO 14044.
*/
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import { n } from '../../energi';

const props = propHalaman();
const pil = computed(() => props.pilihan ?? {});
const KAT: Record<string, string> = Object.fromEntries((props.pilihan?.kategori ?? []).map((k: any) => [k.nilai, k.label]));
const TAHAP: Record<string, any> = Object.fromEntries((props.pilihan?.tahap ?? []).map((k: any) => [k.nilai, k]));
const q = ref(''); const kat = ref('');
const daftar = computed(() => ((props.faktor ?? []) as any[]).filter(f =>
  (!kat.value || f.kategori === kat.value) && (!q.value || (f.nama + f.kode + f.sumber).toLowerCase().includes(q.value.toLowerCase())))
  .map((f, urut) => ({ ...f, urut })));
const hitungKat = computed(() => (pil.value.kategori ?? []).map((k: any) => ({ ...k, n: (props.faktor ?? []).filter((f: any) => f.kategori === k.nilai).length })));
const buka = ref(false);
const form = useForm<Record<string, any>>({ kode: '', nama: '', kategori: 'energi', tahap_bawaan: 'olah', scope: 1, satuan: 'L',
  gwp: null, ap: 0, ep: 0, wsf: 0, ced: 0, odp: 0, sumber: '', tahun: new Date().getFullYear(), wilayah: 'Indonesia', catatan: '' });
function cocok(f: any) { router.post(`/lca/faktor/${f.id}/cocok`, {}, { preserveScroll: true }); }
</script>

<template>
  <Head title="Faktor Dampak LCA" />
  <div class="space-y-4">
    <section class="flex flex-wrap items-end justify-between gap-3">
      <div class="flex flex-wrap items-end gap-2">
        <label class="text-[11px] font-semibold text-stone-500">Cari<input v-model="q" type="search" placeholder="nama, kode, sumber…" class="block mt-1 w-64 rounded-lg border-stone-200 text-[12px]"></label>
      </div>
      <button type="button" class="eq-btn-utama inline-flex items-center gap-2" @click="buka = !buka"><Ikon nama="database" :ukuran="16" />{{ buka ? 'Tutup' : 'Faktor khusus perusahaan' }}</button>
    </section>
    <div class="flex flex-wrap gap-2">
      <button type="button" class="fk-chip" :class="!kat && 'fk-aktif'" @click="kat = ''">Semua <b>{{ (props.faktor ?? []).length }}</b></button>
      <button v-for="k in hitungKat" :key="k.nilai" type="button" class="fk-chip" :class="kat === k.nilai && 'fk-aktif'" @click="kat = kat === k.nilai ? '' : k.nilai">{{ k.label }} <b>{{ k.n }}</b></button>
    </div>

    <Kartu v-if="buka" judul="Tambah faktor perusahaan" ket="Faktor sendiri langsung dianggap sudah dicocokkan — sebutkan sumbernya" ikon="flask-conical" warna="hijau">
      <form class="grid gap-3 md:grid-cols-6" @submit.prevent="form.post('/lca/faktor', { preserveScroll: true, onSuccess: () => { form.reset(); buka = false; } })">
        <label class="fk-l">Kode<input v-model="form.kode" required maxlength="60" placeholder="EF-XXX" class="fk-i num"></label>
        <label class="fk-l md:col-span-3">Nama<input v-model="form.nama" required maxlength="180" class="fk-i"></label>
        <label class="fk-l">Kategori<select v-model="form.kategori" class="fk-i"><option v-for="k in pil.kategori" :key="k.nilai" :value="k.nilai">{{ k.label }}</option></select></label>
        <label class="fk-l">Tahap bawaan<select v-model="form.tahap_bawaan" class="fk-i"><option v-for="t in pil.tahap" :key="t.nilai" :value="t.nilai">{{ t.pendek }}</option></select></label>
        <label class="fk-l">Satuan<input v-model="form.satuan" required maxlength="20" class="fk-i"></label>
        <label class="fk-l">Scope<select v-model.number="form.scope" class="fk-i"><option :value="1">1</option><option :value="2">2</option><option :value="3">3</option></select></label>
        <label class="fk-l">GWP kg CO₂e<input v-model.number="form.gwp" required type="number" step="any" class="fk-i num"></label>
        <label class="fk-l">AP kg SO₂e<input v-model.number="form.ap" type="number" step="any" class="fk-i num"></label>
        <label class="fk-l">EP kg PO₄e<input v-model.number="form.ep" type="number" step="any" class="fk-i num"></label>
        <label class="fk-l">CED MJ<input v-model.number="form.ced" type="number" step="any" class="fk-i num"></label>
        <label class="fk-l md:col-span-3">Sumber<input v-model="form.sumber" required maxlength="160" class="fk-i"></label>
        <label class="fk-l">Tahun<input v-model.number="form.tahun" type="number" class="fk-i num"></label>
        <label class="fk-l md:col-span-2">Wilayah<input v-model="form.wilayah" required maxlength="60" class="fk-i"></label>
        <p v-if="Object.keys(form.errors).length" class="md:col-span-6 text-[12px] fk-galat">{{ Object.values(form.errors)[0] }}</p>
        <div class="md:col-span-6"><button class="eq-btn-utama" :disabled="form.processing">Simpan faktor</button></div>
      </form>
    </Kartu>

    <Kartu :judul="`Pustaka faktor (${daftar.length})`" ket="Nilai per satuan aktivitas; baris inventori menyimpan salinannya saat dihitung" ikon="database" warna="toska" rapat>
      <div class="overflow-x-auto">
        <table class="w-full text-[12px]">
          <thead class="text-left text-[11px] text-stone-500 bg-stone-50"><tr><th class="px-4 py-2.5">Faktor</th><th class="px-3">Tahap</th><th class="px-3 text-right">GWP</th><th class="px-3 text-right">AP</th><th class="px-3 text-right">CED</th><th class="px-3 text-right">Air</th><th class="px-3">Sumber</th><th class="px-3">Status</th></tr></thead>
          <tbody class="divide-y divide-stone-100">
            <tr v-for="f in daftar" :key="f.id">
              <td class="px-4 py-2.5"><b class="text-cam-ink">{{ f.nama }}</b><br><span class="text-[10.5px] text-stone-400 num">{{ f.kode }} · {{ KAT[f.kategori] }} · scope {{ f.scope }} · per {{ f.satuan }}</span>
                <p v-if="f.catatan" class="text-[10.5px] text-stone-400">{{ f.catatan }}</p></td>
              <td class="px-3 py-2.5 whitespace-nowrap"><span class="inline-block w-2 h-2 rounded-full mr-1" :style="{ background: TAHAP[f.tahap_bawaan]?.warna }" />{{ TAHAP[f.tahap_bawaan]?.pendek }}</td>
              <td class="px-3 py-2.5 text-right num font-extrabold" :class="f.gwp < 0 && 'fk-kredit'">{{ n(f.gwp, Math.abs(f.gwp) >= 100 ? 0 : 3) }}</td>
              <td class="px-3 py-2.5 text-right num">{{ n(f.ap, 4) }}</td>
              <td class="px-3 py-2.5 text-right num">{{ n(f.ced, 1) }}</td>
              <td class="px-3 py-2.5 text-right num">{{ n(f.wsf, 3) }}</td>
              <td class="px-3 py-2.5 text-[11px] text-stone-500 max-w-[240px]">{{ f.sumber }} · {{ f.tahun }} · {{ f.wilayah }}</td>
              <td class="px-3 py-2.5 whitespace-nowrap">
                <span class="pr-pil" :class="f.bersama ? 'pr-biru' : 'pr-ungu'">{{ f.bersama ? 'Bersama' : 'Perusahaan' }}</span>
                <span v-if="f.perlu_cocok" class="pr-pil pr-kuning ml-1">Belum dicocokkan</span>
                <span v-else class="pr-pil pr-hijau ml-1">Dicocokkan</span>
                <button v-if="f.perlu_cocok" type="button" class="eq-btn-mini ml-1" @click="cocok(f)">Cocokkan</button>
                <p class="text-[10.5px] text-stone-400 mt-0.5">Dipakai {{ f.dipakai }} baris</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </Kartu>
  </div>
</template>

<style scoped>
.fk-chip { display: inline-flex; align-items: center; gap: 7px; padding: 7px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; background: #fff; border: 1px solid #E7E5E4; color: #57534E; }
.fk-chip b { color: #1C1917; }
.fk-aktif { border-color: #15803D; box-shadow: 0 0 0 3px rgba(21, 128, 61, .15); }
.fk-l { display: block; font-size: 11px; font-weight: 600; color: #78716C; }
.fk-i { display: block; width: 100%; margin-top: 4px; border-radius: 10px; border-color: #E7E5E4; font-size: 12.5px; }
.fk-galat { color: #B91C1C; }
.fk-kredit { color: #15803D; }
:root[data-tema="gelap"] .fk-chip { background: #20272B; border-color: #2E373C; color: #C9D2D6; }
:root[data-tema="gelap"] .fk-chip b { color: #E8EDEF; }
:root[data-tema="gelap"] .fk-galat { color: #F5A9A9; }
:root[data-tema="gelap"] .fk-kredit { color: #8FE3BE; }
</style>

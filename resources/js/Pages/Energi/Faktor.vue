<script setup lang="ts">
/*
  Registri faktor emisi & konversi berversi.
  Faktor aktif tidak disunting di tempat: perubahan = versi baru (draf)
  dengan tanggal berlaku baru, disetujui petugas lain, lalu versi lama
  ditutup otomatis sehari sebelumnya.
*/
import { computed, reactive, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Lencana from '../../Components/Energi/Lencana.vue';
import { json, n, namaParameter, statusLabel, tgl } from '../../energi';

const props = propHalaman();
const saring = reactive({ kode: props.saring?.kode ?? '', parameter: props.saring?.parameter ?? '', status: props.saring?.status ?? '' });
const terapkan = () => router.get('/energi/faktor', Object.fromEntries(Object.entries(saring).filter(([, v]) => v)), { preserveState: true });

const kelompok = computed(() => {
  const g: Record<string, any[]> = {};
  for (const f of props.faktor ?? []) (g[`${f.kode_energi}|${f.parameter}|${f.kategori_ipcc ?? ''}|${f.wilayah ?? ''}`] ??= []).push(f);
  return Object.values(g);
});

const form = useForm<Record<string, any>>({
  kode_energi: 'solar', parameter: 'ncv', kategori_ipcc: '', wilayah: '', pemasok: '', nilai: '', satuan: 'MJ/kg',
  berlaku_mulai: new Date().toISOString().slice(0, 10), berlaku_sampai: '', tier: 1, sumber_dokumen: '', sumber_rujukan: '',
  sumber_url: '', ketidakpastian_persen: '', catatan: '', bersama: false,
});
const kirim = () => form.transform(d => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v])))
  .post(props.tautan.simpan, { preserveScroll: true, onSuccess: () => form.reset('nilai', 'sumber_dokumen', 'sumber_rujukan', 'sumber_url', 'catatan') });

function versiBaru(f: any) {
  Object.assign(form, { kode_energi: f.kode_energi, parameter: f.parameter, kategori_ipcc: f.kategori_ipcc ?? '', wilayah: f.wilayah ?? '',
    pemasok: f.pemasok ?? '', satuan: f.satuan, nilai: f.nilai, tier: f.tier, sumber_dokumen: '', berlaku_mulai: new Date().toISOString().slice(0, 10) });
  document.getElementById('form-faktor')?.scrollIntoView({ behavior: 'smooth' });
}

const aksi = (f: any, jenis: string) => router.post(`/energi/faktor/${f.id}/${jenis}`, {}, { preserveScroll: true });

const dampak = ref<Record<number, any>>({});
async function uji(f: any) {
  dampak.value[f.id] = { sibuk: true };
  try { dampak.value[f.id] = await json(`${props.tautan.uji}?faktor=${f.id}`, undefined, 'GET'); }
  catch (e: any) { dampak.value[f.id] = { galat: e.message }; }
}
</script>

<template>
  <Head title="Registri Faktor" />
  <div class="space-y-4">
    <p v-if="!props.terpasang" class="rounded-xl bg-amber-50 text-amber-800 text-[12px] p-3">
      Paket faktor bawaan belum dipasang — jalankan <code>php artisan energi:pasang</code> di server.
    </p>

    <form class="flex flex-wrap items-end gap-2" @submit.prevent="terapkan">
      <label class="text-[11px] font-semibold text-stone-500">Energi
        <select v-model="saring.kode" class="block mt-1 rounded-lg border-stone-200 text-[12px]"><option value="">Semua</option><option v-for="[k, l] in Object.entries((props.pilihan.kode ?? {}) as Record<string, any>)" :key="k" :value="k">{{ l }}</option></select>
      </label>
      <label class="text-[11px] font-semibold text-stone-500">Parameter
        <select v-model="saring.parameter" class="block mt-1 rounded-lg border-stone-200 text-[12px]"><option value="">Semua</option><option v-for="k in Object.keys(props.pilihan.parameter ?? {})" :key="k" :value="k">{{ namaParameter(k) }}</option></select>
      </label>
      <label class="text-[11px] font-semibold text-stone-500">Status
        <select v-model="saring.status" class="block mt-1 rounded-lg border-stone-200 text-[12px]"><option value="">Semua</option><option v-for="s in props.pilihan.status" :key="s" :value="s">{{ statusLabel(s) }}</option></select>
      </label>
      <button class="eq-btn-lain" style="padding:9px 14px">Terapkan</button>
      <a v-if="props.boleh.kelola" href="#form-faktor" class="eq-btn-mini ml-auto">Tambah versi</a>
    </form>

    <Kartu judul="Faktor emisi & konversi" rapat>
      <div class="overflow-x-auto">
        <table class="w-full text-[12px] min-w-[860px]">
          <thead><tr class="text-left text-stone-400 border-y border-stone-200 bg-stone-50/70"><th class="px-5 py-2 font-semibold">Nilai</th><th class="px-3 py-2 font-semibold">Berlaku</th><th class="px-3 py-2 font-semibold">Tier</th><th class="px-3 py-2 font-semibold">Sumber</th><th class="px-3 py-2 font-semibold">Milik</th><th class="px-3 py-2 font-semibold">Status</th><th class="px-5 py-2"></th></tr></thead>
          <tbody v-for="g in kelompok" :key="g[0].id">
            <tr class="bg-stone-50/40 border-b border-stone-100">
              <td colspan="7" class="px-5 pt-3 pb-1.5">
                <span class="font-bold text-cam-ink">{{ props.pilihan.kode[g[0].kode_energi] ?? g[0].kode_energi }}</span>
                <span class="text-stone-500"> · {{ namaParameter(g[0].parameter) }}</span>
                <span v-if="g[0].kategori_ipcc" class="text-stone-400"> · {{ props.pilihan.kategori[g[0].kategori_ipcc] }}</span>
                <span v-if="g[0].wilayah" class="text-stone-400"> · {{ props.pilihan.wilayah?.[g[0].wilayah] ?? g[0].wilayah }}</span>
              </td>
            </tr>
            <tr v-for="f in g" :key="f.id" class="border-b border-stone-100 align-top">
              <td class="px-5 py-2 font-semibold num whitespace-nowrap">{{ f.nilai }} <span class="text-stone-400 font-normal">{{ f.satuan }}</span><span v-if="f.ketidakpastian_persen !== null" class="block text-[10px] text-stone-400 font-normal">± {{ n(f.ketidakpastian_persen, 1) }}%</span></td>
              <td class="px-3 py-2 whitespace-nowrap">{{ tgl(f.berlaku_mulai) }} – {{ f.berlaku_sampai ? tgl(f.berlaku_sampai) : 'sekarang' }}</td>
              <td class="px-3 py-2">{{ f.tier }}</td>
              <td class="px-3 py-2 max-w-[320px]">{{ f.sumber_dokumen }}<span v-if="f.sumber_rujukan" class="text-stone-400"> · {{ f.sumber_rujukan }}</span>
                <span v-if="f.perlu_cocok" class="block text-[10.5px] text-amber-700">Belum dicocokkan dengan dokumen sumber</span>
                <span v-if="f.catatan" class="block text-[10.5px] text-stone-500">{{ f.catatan }}</span>
                <span v-if="dampak[f.id]" class="block text-[10.5px] text-sky-700 mt-1">
                  <template v-if="dampak[f.id].sibuk">Menghitung…</template>
                  <template v-else-if="dampak[f.id].galat">{{ dampak[f.id].galat }}</template>
                  <template v-else>12 bulan terakhir: {{ n(dampak[f.id].aktif?.total, 1) }} → {{ n(dampak[f.id].draf?.total, 1) }} tCO₂e ({{ n(dampak[f.id].selisih_persen, 2) }}%)</template>
                </span>
              </td>
              <td class="px-3 py-2 text-stone-500">{{ f.milik }}<span v-if="f.penyetuju" class="block text-[10px]">Disetujui {{ f.penyetuju }} {{ f.disetujui_pada }}</span></td>
              <td class="px-3 py-2"><Lencana :status="f.status" /></td>
              <td class="px-5 py-2 text-right whitespace-nowrap space-x-1">
                <template v-if="f.status === 'draf'">
                  <button type="button" class="eq-btn-mini" @click="uji(f)">Uji dampak</button>
                  <button v-if="f.boleh_setujui" type="button" class="eq-btn-setuju" @click="aksi(f, 'setujui')">Setujui</button>
                </template>
                <template v-else-if="f.status === 'aktif' && props.boleh.kelola">
                  <button type="button" class="eq-btn-mini" @click="versiBaru(f)">Versi baru</button>
                  <button v-if="f.perlu_cocok && props.boleh.faktor" type="button" class="eq-btn-mini" @click="aksi(f, 'cocokkan')">Sudah dicocokkan</button>
                  <button type="button" class="eq-btn-mini bahaya" @click="aksi(f, 'tarik')">Tarik</button>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="!kelompok.length" class="eq-kosong eq-kosong-kecil">Tidak ada faktor yang cocok.</p>
    </Kartu>

    <Kartu v-if="props.boleh.kelola" id="form-faktor" judul="Tambah versi faktor" ket="Masuk sebagai draf dan berlaku setelah disetujui petugas lain.">
      <form class="grid gap-3 md:grid-cols-4" @submit.prevent="kirim">
        <label class="text-[11px] font-semibold text-stone-500">Kode energi<select v-model="form.kode_energi" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option v-for="[k, l] in Object.entries((props.pilihan.kode ?? {}) as Record<string, any>)" :key="k" :value="k">{{ l }}</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500">Parameter<select v-model="form.parameter" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option v-for="[k, l] in Object.entries((props.pilihan.parameter ?? {}) as Record<string, any>)" :key="k" :value="k">{{ namaParameter(k) }} ({{ l }})</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500">Kategori IPCC<select v-model="form.kategori_ipcc" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option value="">Semua kategori</option><option v-for="[k, l] in Object.entries((props.pilihan.kategori ?? {}) as Record<string, any>)" :key="k" :value="k">{{ l }}</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500">Wilayah / sistem grid<select v-model="form.wilayah" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option value="">Nasional / semua</option><option v-for="[k, l] in Object.entries((props.pilihan.wilayah ?? {}) as Record<string, any>)" :key="k" :value="k">{{ l }}</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500">Nilai<input v-model="form.nilai" required type="number" step="any" min="0" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Satuan<input v-model="form.satuan" required maxlength="30" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Berlaku mulai<input v-model="form.berlaku_mulai" required type="date" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Berlaku sampai<input v-model="form.berlaku_sampai" type="date" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Tier IPCC<select v-model="form.tier" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option :value="1">1 — bawaan</option><option :value="2">2 — nasional/pemasok</option><option :value="3">3 — pengukuran</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500">Pemasok (Tier 2)<input v-model="form.pemasok" maxlength="100" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Ketidakpastian (±%)<input v-model="form.ketidakpastian_persen" type="number" step="any" min="0" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500 flex items-center gap-2 mt-5"><input v-model="form.bersama" type="checkbox"> Faktor bersama (admin)</label>
        <label class="text-[11px] font-semibold text-stone-500 md:col-span-2">Sumber dokumen<input v-model="form.sumber_dokumen" required maxlength="255" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Tabel / halaman<input v-model="form.sumber_rujukan" maxlength="255" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">URL sumber<input v-model="form.sumber_url" type="url" maxlength="500" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500 md:col-span-3">Catatan<input v-model="form.catatan" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <div class="flex items-end"><button class="eq-btn-utama w-full" :disabled="form.processing">Ajukan draf</button></div>
      </form>
      <p v-for="(pesan, k) in form.errors" :key="k" class="text-[11px] text-red-600 mt-1">{{ pesan }}</p>
    </Kartu>
  </div>
</template>

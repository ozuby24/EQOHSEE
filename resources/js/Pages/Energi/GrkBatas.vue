<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Lencana from '../../Components/Energi/Lencana.vue';
import PilihPerusahaan from '../../Components/Energi/PilihPerusahaan.vue';

const props = propHalaman();
const b = props.batas ?? {};

const relevansi: Record<string, { relevan: boolean; alasan: string }> = {};
for (const k of Object.keys(props.kategoriScope3 ?? {})) {
  relevansi[k] = { relevan: !!b.relevansi_scope3?.[k]?.relevan, alasan: b.relevansi_scope3?.[k]?.alasan ?? '' };
}

const form = useForm<Record<string, any>>({
  tahun: props.tahun, pendekatan: b.pendekatan ?? 'kendali_operasional', persen_ekuitas: b.persen_ekuitas ?? '',
  uraian_batas: b.uraian_batas ?? '', pengecualian: (b.pengecualian ?? []).map((x: any) => ({ ...x })), relevansi_scope3: relevansi,
});
const dasar = useForm<Record<string, any>>({ tahun_dasar: props.atur?.tahun_dasar ?? '', ambang_rekalkulasi_persen: props.atur?.ambang_rekalkulasi_persen ?? 5, gwp_set: props.atur?.gwp_set ?? 'AR5' });

const tambahPengecualian = () => form.pengecualian.push({ sumber: '', alasan: '', estimasi_persen: '' });
const simpan = () => form.transform(d => ({ ...d, persen_ekuitas: d.persen_ekuitas === '' ? null : d.persen_ekuitas })).post('/energi/grk/batas', { preserveScroll: true });
const simpanDasar = () => dasar.transform(d => ({ ...d, tahun_dasar: d.tahun_dasar === '' ? null : d.tahun_dasar })).post('/energi/grk/tahun-dasar', { preserveScroll: true });
const setujui = () => router.post(`/energi/grk/batas/${b.id}/setujui`, {}, { preserveScroll: true });
const gantiTahun = (e: Event) => router.get('/energi/grk/batas', { tahun: (e.target as HTMLInputElement).value, perusahaan: props.perusahaan ?? undefined });
</script>

<template>
  <Head title="Batas & Tahun Dasar" />
  <div class="space-y-4">
    <section class="flex flex-wrap items-end justify-between gap-3">
      <label class="text-[11px] font-semibold text-stone-500">Tahun inventarisasi<input :value="props.tahun" type="number" min="2000" max="2100" class="block mt-1 rounded-lg border-stone-200 text-[12px] w-28" @change="gantiTahun"></label>
      <PilihPerusahaan :daftar="props.daftarPerusahaan" :terpilih="props.perusahaan" :nama="props.namaPerusahaan" />
    </section>

    <Kartu :judul="`Batas organisasi & operasional ${props.tahun}`" ket="Laporan GRK baru dapat terbit setelah batas tahun ini disetujui.">
      <template #aksi>
        <Lencana v-if="props.batas" :status="props.batas.disetujui ? 'disetujui' : 'diajukan'" :label="props.batas.disetujui ? `Disetujui ${props.batas.penyetuju ?? ''} ${props.batas.disetujui_pada ?? ''}` : 'Menunggu persetujuan'" />
        <button v-if="props.batas && !props.batas.disetujui && props.boleh.setujui" type="button" class="eq-btn-setuju" @click="setujui">Setujui batas</button>
      </template>
      <form class="space-y-4" @submit.prevent="simpan">
        <div class="grid gap-3 md:grid-cols-3">
          <label class="text-[11px] font-semibold text-stone-500">Pendekatan konsolidasi
            <select v-model="form.pendekatan" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]" :disabled="!props.boleh.kelola"><option v-for="[k, l] in Object.entries((props.pendekatan ?? {}) as Record<string, any>)" :key="k" :value="k">{{ l }}</option></select>
          </label>
          <label v-if="form.pendekatan === 'ekuitas'" class="text-[11px] font-semibold text-stone-500">Bagian ekuitas (%)<input v-model="form.persen_ekuitas" type="number" step="any" min="0" max="100" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        </div>
        <label class="block text-[11px] font-semibold text-stone-500">Uraian batas operasional (site, sumber, kegiatan yang dicakup)
          <textarea v-model="form.uraian_batas" rows="3" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]" :disabled="!props.boleh.kelola"></textarea>
        </label>

        <div>
          <p class="text-[12px] font-bold mb-2">Pengecualian</p>
          <div v-for="(p, i) in form.pengecualian" :key="i" class="grid gap-2 md:grid-cols-[2fr_3fr_1fr_auto] mb-2">
            <label class="text-[11px] text-stone-500">Sumber<input v-model="p.sumber" required class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
            <label class="text-[11px] text-stone-500">Alasan<input v-model="p.alasan" required class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
            <label class="text-[11px] text-stone-500">Estimasi % emisi<input v-model="p.estimasi_persen" type="number" step="any" min="0" max="100" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
            <button type="button" class="eq-btn-lain self-end" @click="form.pengecualian.splice(i, 1)">Hapus</button>
          </div>
          <button v-if="props.boleh.kelola" type="button" class="eq-btn-mini" @click="tambahPengecualian">+ Pengecualian</button>
        </div>

        <div>
          <p class="text-[12px] font-bold mb-2">Penyaringan relevansi Scope 3 (15 kategori GHG Protocol)</p>
          <div class="grid gap-2 md:grid-cols-2">
            <div v-for="[k, label] in Object.entries((props.kategoriScope3 ?? {}) as Record<string, any>)" :key="k" class="rounded-lg bg-stone-50 p-2 text-[11.5px]">
              <label class="flex items-center gap-2 font-semibold"><input v-model="form.relevansi_scope3[k].relevan" type="checkbox"> {{ k }}. {{ label }}</label>
              <label class="block text-[10.5px] text-stone-500 mt-1">Alasan<input v-model="form.relevansi_scope3[k].alasan" maxlength="500" class="block w-full mt-0.5 rounded border-stone-200 text-[11px]"></label>
            </div>
          </div>
        </div>
        <button v-if="props.boleh.kelola" class="eq-btn-utama" :disabled="form.processing">Simpan batas</button>
        <p v-for="(pesan, k) in form.errors" :key="k" class="text-[11px] text-red-600">{{ pesan }}</p>
      </form>
    </Kartu>

    <Kartu judul="Tahun dasar & set GWP">
      <form class="grid gap-3 md:grid-cols-4 items-end" @submit.prevent="simpanDasar">
        <label class="text-[11px] font-semibold text-stone-500">Tahun dasar<input v-model="dasar.tahun_dasar" type="number" min="2000" max="2100" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Ambang rekalkulasi (%)<input v-model="dasar.ambang_rekalkulasi_persen" type="number" step="any" min="0" max="100" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Set GWP<select v-model="dasar.gwp_set" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option v-for="[k, l] in Object.entries((props.gwp ?? {}) as Record<string, any>)" :key="k" :value="k">{{ l }}</option></select></label>
        <button v-if="props.boleh.kelola" class="eq-btn-utama" :disabled="dasar.processing">Simpan</button>
      </form>
    </Kartu>
  </div>
</template>

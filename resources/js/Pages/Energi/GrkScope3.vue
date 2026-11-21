<script setup lang="ts">
import { computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Lencana from '../../Components/Energi/Lencana.vue';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
import { n } from '../../energi';

const props = propHalaman();
const { dialog, tanya, batal, lanjut } = useDialog();

const total = computed(() => (props.daftar ?? []).filter((s: any) => s.kategori !== 11).reduce((a: number, s: any) => a + s.tco2e, 0));
const kat11 = computed(() => (props.daftar ?? []).filter((s: any) => s.kategori === 11).reduce((a: number, s: any) => a + s.tco2e, 0));

const form = useForm<Record<string, any>>({ tahun: props.tahun, bulan: '', kategori: 4, subkategori: '', metode: 'distance', kuantitas: '', satuan: 'ton-km',
  faktor_kgco2e: '', sumber_faktor: '', mitra: '', kualitas_data: 3, catatan: '' });
const simpan = () => form.transform(d => ({ ...d, bulan: d.bulan === '' ? null : d.bulan })).post('/energi/grk/scope3', { preserveScroll: true, onSuccess: () => form.reset('subkategori', 'kuantitas', 'faktor_kgco2e', 'mitra', 'catatan') });
const verifikasi = (s: any) => router.post(`/energi/grk/scope3/${s.id}/verifikasi`, {}, { preserveScroll: true });
async function hapus(s: any) { if (await tanya(`Hapus aktivitas "${s.subkategori}"?`)) router.delete(`/energi/grk/scope3/${s.id}`, { preserveScroll: true }); }
const gantiTahun = (e: Event) => router.get('/energi/grk/scope3', { tahun: (e.target as HTMLInputElement).value });
</script>

<template>
  <Head title="Scope 3" />
  <div class="space-y-4">
    <section class="flex flex-wrap items-end justify-between gap-3">
      <label class="text-[11px] font-semibold text-stone-500">Tahun<input :value="props.tahun" type="number" min="2000" max="2100" class="block mt-1 rounded-lg border-stone-200 text-[12px] w-28" @change="gantiTahun"></label>
      <p class="text-[12px] text-stone-600">Total Scope 3: <b>{{ n(total, 2) }} tCO₂e</b><template v-if="kat11"> · kategori 11 (disajikan terpisah) <b>{{ n(kat11, 0) }} tCO₂e</b></template></p>
    </section>

    <Kartu judul="Aktivitas Scope 3" ket="Kategori 11 (batubara terjual) dilaporkan terpisah.">
      <div class="overflow-x-auto">
        <table class="w-full text-[12px] min-w-[860px]">
          <thead><tr class="text-left text-stone-400 border-b border-stone-100"><th class="py-2">Kategori</th><th class="py-2">Aktivitas</th><th class="py-2">Metode</th><th class="py-2 text-right">Kuantitas</th><th class="py-2 text-right">Faktor</th><th class="py-2 text-right">tCO₂e</th><th class="py-2">Mutu</th><th class="py-2">Status</th><th class="py-2"></th></tr></thead>
          <tbody>
            <tr v-for="s in props.daftar" :key="s.id" class="border-b border-stone-50 align-top">
              <td class="py-2">{{ s.kategori }}. {{ props.kategori[s.kategori] }}</td>
              <td class="py-2">{{ s.subkategori }}<span class="block text-[10.5px] text-stone-400">{{ s.mitra ?? '' }} {{ s.bulan ? '· bulan ' + s.bulan : '· setahun' }}</span></td>
              <td class="py-2">{{ props.metode[s.metode] ?? s.metode }}</td>
              <td class="py-2 text-right num">{{ n(s.kuantitas, 2) }} {{ s.satuan }}</td>
              <td class="py-2 text-right num">{{ n(s.faktor_kgco2e, 4) }} kg/{{ s.satuan }}<span class="block text-[10px] text-stone-400 max-w-[220px] ml-auto">{{ s.sumber_faktor }}</span></td>
              <td class="py-2 text-right num font-bold">{{ n(s.tco2e, 2) }}</td>
              <td class="py-2">{{ s.kualitas_data }}/5</td>
              <td class="py-2"><Lencana :status="s.status" /></td>
              <td class="py-2 text-right whitespace-nowrap">
                <button v-if="s.status === 'diajukan' && props.boleh.kelola" type="button" class="eq-btn-mini" @click="verifikasi(s)">Verifikasi</button>
                <button v-if="props.boleh.kelola" type="button" class="text-red-600 text-[11px] ml-1" @click="hapus(s)">Hapus</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="!props.daftar?.length" class="text-[12px] text-stone-400 py-4 text-center">Belum ada aktivitas Scope 3 tahun ini.</p>
    </Kartu>

    <Kartu v-if="props.boleh.kelola" judul="Catat aktivitas Scope 3">
      <form class="grid gap-3 md:grid-cols-4" @submit.prevent="simpan">
        <label class="text-[11px] font-semibold text-stone-500">Kategori<select v-model="form.kategori" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option v-for="[k, l] in Object.entries((props.kategori ?? {}) as Record<string, any>)" :key="k" :value="Number(k)">{{ k }}. {{ l }}</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500">Bulan (kosong = setahun)<input v-model="form.bulan" type="number" min="1" max="12" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500 md:col-span-2">Aktivitas<input v-model="form.subkategori" required maxlength="150" placeholder="mis. angkutan tongkang pihak ketiga" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Metode<select v-model="form.metode" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option v-for="[k, l] in Object.entries((props.metode ?? {}) as Record<string, any>)" :key="k" :value="k">{{ l }}</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500">Kuantitas<input v-model="form.kuantitas" required type="number" step="any" min="0" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Satuan<input v-model="form.satuan" required maxlength="20" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Faktor (kgCO₂e/satuan)<input v-model="form.faktor_kgco2e" required type="number" step="any" min="0" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500 md:col-span-2">Sumber faktor<input v-model="form.sumber_faktor" required maxlength="255" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Mitra / pemasok<input v-model="form.mitra" maxlength="150" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Mutu data (1 terbaik – 5)<input v-model="form.kualitas_data" type="number" min="1" max="5" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <div class="md:col-span-4"><button class="eq-btn-utama" :disabled="form.processing">Catat aktivitas</button></div>
      </form>
      <p v-for="(pesan, k) in form.errors" :key="k" class="text-[11px] text-red-600 mt-1">{{ pesan }}</p>
    </Kartu>
  </div>
  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>

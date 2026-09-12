<script setup lang="ts">
/**
 * Surat pengajuan MCU beserta hasil tiap orang di dalamnya.
 *
 * SATU SURAT MEMUAT BANYAK ORANG, dan hasilnya diisi per orang — bukan
 * per surat. Klinik memulangkan hasil yang berbeda untuk tiap nama pada
 * surat yang sama, dan satu kolom hasil untuk seluruh surat memaksa
 * pengisinya memilih salah satunya.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { propHalaman } from '../../halaman';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
import Lencana from './Lencana.vue';
import Langkah from './Langkah.vue';

const props = propHalaman();
const { dialog, tanya, batal, lanjut } = useDialog();

const baris = computed<any[]>(() => (props.baris ?? []) as any[]);
const buka  = ref<number | null>(null);

const surat = useForm({ no_registrasi: '', tanggal: '', kepada: '', perihal: '' });

function simpanSurat() {
  surat.post('/miners/mcu', { preserveScroll: true, onSuccess: () => surat.reset() });
}

const tambah = useForm({ pekerja_id: '' });

function tambahOrang(id: number) {
  tambah.post(`/miners/mcu/${id}/orang`, { preserveScroll: true, onSuccess: () => tambah.reset() });
}

const hasil = useForm({ hasil_id: '', tanggal_periksa: '', hasil_napza: '', catatan: '' });

function simpanHasil(mcuId: number, orangId: number) {
  hasil.post(`/miners/mcu/${mcuId}/orang/${orangId}/hasil`, {
    preserveScroll: true, onSuccess: () => hasil.reset(),
  });
}

const tindakan = useForm({ keadaan: 'setuju', catatan: '' });

function tindak(id: number, keadaan: string) {
  tindakan.keadaan = keadaan;
  tindakan.post(`/miners/mcu/${id}/tindak`, { preserveScroll: true });
}

async function hapusSurat(id: number, nomor: string) {
  if (!await tanya(`Hapus surat ${nomor || 'ini'} beserta seluruh hasil di dalamnya?`)) return;

  useForm({}).delete(`/miners/mcu/${id}`, { preserveScroll: true });
}

const bolehTindak = computed(() =>
  ((props.peranSaya ?? []) as string[]).some((p) => p !== 'pjo'));
</script>

<template>
  <Head :title="props.judul" />
  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />

  <div class="max-w-[1400px] mx-auto space-y-5">

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="text-[13.5px] font-bold text-cam-ink mb-3">Surat pengajuan baru</h3>

      <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="simpanSurat">
        <label class="block">
          <span class="block text-[11px] text-stone-500">Nomor</span>
          <input v-model="surat.no_registrasi" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Tanggal</span>
          <input v-model="surat.tanggal" type="date" required
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Kepada (klinik)</span>
          <input v-model="surat.kepada" required class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Perihal</span>
          <input v-model="surat.perihal" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <div class="sm:col-span-2 lg:col-span-4">
          <button type="submit" class="eq-btn-utama" :disabled="surat.processing">Buat surat</button>
          <span v-if="surat.errors.kepada" class="ml-3 text-[11.5px] text-red-600">{{ surat.errors.kepada }}</span>
        </div>
      </form>
    </section>

    <section v-for="m in baris" :key="m.id"
             class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100 flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-0">
          <h3 class="text-[13.5px] font-bold text-cam-ink">
            {{ m.nomor || 'Tanpa nomor' }}
            <span class="font-normal text-stone-400">| {{ m.kepada }}</span>
          </h3>
          <p class="text-[11px] text-stone-500 num">{{ m.tanggal || '—' }} · {{ props.STATUS?.[m.status] ?? m.status }}</p>
        </div>

        <button class="eq-btn-lain" @click="buka = buka === m.id ? null : m.id">
          {{ buka === m.id ? 'Tutup' : 'Kelola' }}
        </button>
      </header>

      <div class="px-5 py-3 border-b border-stone-100">
        <Langkah :alur="m.alur" />
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[11.5px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
              <th class="px-4 py-2 font-semibold">Nama</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Diperiksa</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Berlaku sampai</th>
              <th class="px-4 py-2 font-semibold">Hasil</th>
              <th class="px-4 py-2 font-semibold">Napza</th>
              <th class="px-4 py-2 font-semibold">Keadaan</th>
              <th v-if="buka === m.id" class="px-4 py-2 font-semibold"></th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="o in m.orang" :key="o.id" class="border-b border-stone-100">
              <td class="px-4 py-2.5">
                <Link v-if="o.pekerja_id" :href="`/miners/${o.pekerja_id}`"
                      class="font-semibold text-cam-lime-deep hover:underline">{{ o.nama }}</Link>
                <span v-else class="font-semibold text-cam-ink">{{ o.nama }}</span>
                <div class="text-[10.5px] text-stone-400 num">{{ o.nik || '—' }}</div>
              </td>
              <td class="px-4 py-2.5 num">{{ o.periksa || '—' }}</td>
              <td class="px-4 py-2.5 num">{{ o.sampai || '—' }}</td>
              <td class="px-4 py-2.5">
                {{ o.hasil || '—' }}
                <span v-if="o.hasil && !o.layak" class="text-red-600">· tidak layak</span>
              </td>
              <td class="px-4 py-2.5">{{ o.napza || '—' }}</td>
              <td class="px-4 py-2.5">
                <Lencana :keadaan="o.keadaan" :label="props.KEADAAN?.[o.keadaan]" :nada="props.NADA" />
              </td>

              <td v-if="buka === m.id" class="px-4 py-2.5">
                <form class="flex flex-wrap items-end gap-2" @submit.prevent="simpanHasil(m.id, o.id)">
                  <select v-model="hasil.hasil_id" class="rounded-lg border-stone-200 text-[11px]"
                          aria-label="Hasil MCU">
                    <option value="">Hasil…</option>
                    <option v-for="h in (props.hasilMcu ?? [])" :key="h.id" :value="h.id">{{ h.nama }}</option>
                  </select>

                  <input v-model="hasil.tanggal_periksa" type="date"
                         class="rounded-lg border-stone-200 text-[11px]" aria-label="Tanggal periksa">

                  <select v-model="hasil.hasil_napza" class="rounded-lg border-stone-200 text-[11px]"
                          aria-label="Hasil napza">
                    <option value="">Napza…</option>
                    <option value="negatif">Negatif</option>
                    <option value="positif">Positif</option>
                  </select>

                  <button type="submit" class="eq-btn-lain">Simpan</button>
                </form>
              </td>
            </tr>

            <tr v-if="!m.orang?.length">
              <td :colspan="buka === m.id ? 7 : 6" class="px-4 py-6 text-center text-stone-400">
                Belum ada nama pada surat ini.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer v-if="buka === m.id" class="px-5 py-4 border-t border-stone-100 space-y-3">
        <form class="flex flex-wrap items-end gap-2" @submit.prevent="tambahOrang(m.id)">
          <label class="block">
            <span class="block text-[11px] text-stone-500">Tambah nama</span>
            <select v-model="tambah.pekerja_id" required
                    class="mt-1 rounded-lg border-stone-200 text-[12px] w-64">
              <option value="">Pilih pekerja…</option>
              <option v-for="pk in (props.pekerja ?? [])" :key="pk.id" :value="pk.id">
                {{ pk.nama }} — {{ pk.nik || 'tanpa NIK' }}
              </option>
            </select>
          </label>

          <button type="submit" class="eq-btn-lain">Tambahkan</button>
        </form>

        <div v-if="bolehTindak" class="flex flex-wrap items-end gap-2">
          <input v-model="tindakan.catatan" placeholder="Catatan peninjau (opsional)"
                 class="rounded-lg border-stone-200 text-[12px] w-72" aria-label="Catatan peninjau">
          <button class="eq-btn-utama" @click="tindak(m.id, 'setuju')">Setujui</button>
          <button class="eq-btn-lain" @click="tindak(m.id, 'dikembalikan')">Kembalikan</button>
          <button class="eq-btn-lain" @click="tindak(m.id, 'tolak')">Tolak</button>
        </div>

        <p v-if="tindakan.errors.keadaan" class="text-[11.5px] text-red-600">{{ tindakan.errors.keadaan }}</p>

        <button class="text-[11.5px] text-red-600 hover:underline" @click="hapusSurat(m.id, m.nomor)">
          Hapus surat ini
        </button>
      </footer>
    </section>

    <p v-if="!baris.length" class="rounded-2xl bg-white border border-stone-100 shadow-card px-5 py-10 text-center text-stone-400">
      Belum ada surat pengajuan MCU.
    </p>
  </div>
</template>

<script setup lang="ts">
/**
 * Induksi keselamatan dan nilai post test-nya.
 *
 * NILAI DICATAT PER ORANG, dan tiap pencatatan ULANG menambah hitungan
 * percobaan. Pembedaan itu penting: memperbaiki salah ketik pada nilai
 * tidak boleh menghabiskan jatah remidi orangnya, sedangkan ujian
 * kedua memang harus terhitung.
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

const jadwal = useForm({ no_registrasi: '', tanggal: '', perihal: '' });

function simpanJadwal() {
  jadwal.post('/miners/induksi', { preserveScroll: true, onSuccess: () => jadwal.reset() });
}

const tambah = useForm({ pekerja_id: '' });

function tambahOrang(id: number) {
  tambah.post(`/miners/induksi/${id}/orang`, { preserveScroll: true, onSuccess: () => tambah.reset() });
}

const nilai = useForm({ nilai: '', lokasi: '', catatan: '' });

function simpanNilai(induksiId: number, orangId: number) {
  nilai.post(`/miners/induksi/${induksiId}/orang/${orangId}/nilai`, {
    preserveScroll: true, onSuccess: () => nilai.reset(),
  });
}

const tindakan = useForm({ keadaan: 'setuju', catatan: '' });

function tindak(id: number, keadaan: string) {
  tindakan.keadaan = keadaan;
  tindakan.post(`/miners/induksi/${id}/tindak`, { preserveScroll: true });
}

async function hapusJadwal(id: number, nomor: string) {
  if (!await tanya(`Hapus jadwal induksi ${nomor || 'ini'} beserta seluruh nilainya?`)) return;

  useForm({}).delete(`/miners/induksi/${id}`, { preserveScroll: true });
}

const bolehTindak = computed(() =>
  ((props.peranSaya ?? []) as string[]).some((p) => p !== 'pjo'));
</script>

<template>
  <Head :title="props.judul" />
  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />

  <div class="max-w-[1400px] mx-auto space-y-5">
    <section>
      <p class="text-[12.5px] text-stone-500 mt-0.5">
        {{ props.subjudul }} Ambang kelulusan {{ props.NILAI_LULUS }}, paling banyak
        {{ props.MAKS_PERCOBAAN }} kali ujian.
      </p>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="text-[13.5px] font-bold text-cam-ink mb-3">Jadwal induksi baru</h3>

      <form class="grid gap-3 sm:grid-cols-3" @submit.prevent="simpanJadwal">
        <label class="block">
          <span class="block text-[11px] text-stone-500">Nomor</span>
          <input v-model="jadwal.no_registrasi" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Tanggal</span>
          <input v-model="jadwal.tanggal" type="date" required
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Perihal</span>
          <input v-model="jadwal.perihal" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <div class="sm:col-span-3">
          <button type="submit" class="eq-btn-utama" :disabled="jadwal.processing">Buat jadwal</button>
        </div>
      </form>
    </section>

    <section v-for="i in baris" :key="i.id"
             class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100 flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-0">
          <h3 class="text-[13.5px] font-bold text-cam-ink">{{ i.nomor || 'Tanpa nomor' }}</h3>
          <p class="text-[11px] text-stone-500 num">
            {{ i.tanggal || '—' }} · {{ props.STATUS?.[i.status] ?? i.status }}
          </p>
        </div>

        <button class="eq-btn-lain" @click="buka = buka === i.id ? null : i.id">
          {{ buka === i.id ? 'Tutup' : 'Kelola' }}
        </button>
      </header>

      <div class="px-5 py-3 border-b border-stone-100">
        <Langkah :alur="i.alur" />
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[11.5px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
              <th class="px-4 py-2 font-semibold">Peserta</th>
              <th class="px-4 py-2 font-semibold text-right">Nilai</th>
              <th class="px-4 py-2 font-semibold text-right">Percobaan</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Berlaku sampai</th>
              <th class="px-4 py-2 font-semibold">Keadaan</th>
              <th v-if="buka === i.id" class="px-4 py-2 font-semibold"></th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="o in i.orang" :key="o.id" class="border-b border-stone-100">
              <td class="px-4 py-2.5">
                <Link v-if="o.pekerja_id" :href="`/miners/${o.pekerja_id}`"
                      class="font-semibold text-cam-lime-deep hover:underline">{{ o.nama || '—' }}</Link>
                <span v-else class="font-semibold text-cam-ink">{{ o.nama || '—' }}</span>
              </td>
              <td class="px-4 py-2.5 num text-right" :class="o.lulus ? '' : 'text-red-600 font-semibold'">
                {{ o.nilai ?? '—' }}
              </td>
              <td class="px-4 py-2.5 num text-right">
                {{ o.percobaan }}
                <span v-if="!o.lulus && !o.bolehUlang" class="text-red-600"> · habis</span>
              </td>
              <td class="px-4 py-2.5 num">{{ o.sampai || '—' }}</td>
              <td class="px-4 py-2.5">
                <Lencana :keadaan="o.keadaan" :label="props.KEADAAN?.[o.keadaan]" :nada="props.NADA" />
              </td>

              <td v-if="buka === i.id" class="px-4 py-2.5">
                <form class="flex flex-wrap items-end gap-2" @submit.prevent="simpanNilai(i.id, o.id)">
                  <input v-model="nilai.nilai" type="number" min="0" max="100" required
                         placeholder="Nilai" class="w-20 rounded-lg border-stone-200 text-[11px]"
                         aria-label="Nilai post test">
                  <input v-model="nilai.lokasi" placeholder="Lokasi"
                         class="w-32 rounded-lg border-stone-200 text-[11px]" aria-label="Lokasi induksi">
                  <button type="submit" class="eq-btn-lain">Simpan</button>
                </form>
              </td>
            </tr>

            <tr v-if="!i.orang?.length">
              <td :colspan="buka === i.id ? 6 : 5" class="px-4 py-6 text-center text-stone-400">
                Belum ada peserta.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer v-if="buka === i.id" class="px-5 py-4 border-t border-stone-100 space-y-3">
        <form class="flex flex-wrap items-end gap-2" @submit.prevent="tambahOrang(i.id)">
          <label class="block">
            <span class="block text-[11px] text-stone-500">Tambah peserta</span>
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
          <button class="eq-btn-utama" @click="tindak(i.id, 'setuju')">Setujui</button>
          <button class="eq-btn-lain" @click="tindak(i.id, 'dikembalikan')">Kembalikan</button>
          <button class="eq-btn-lain" @click="tindak(i.id, 'tolak')">Tolak</button>
        </div>

        <p v-if="tindakan.errors.keadaan" class="text-[11.5px] text-red-600">{{ tindakan.errors.keadaan }}</p>

        <button class="text-[11.5px] text-red-600 hover:underline" @click="hapusJadwal(i.id, i.nomor)">
          Hapus jadwal ini
        </button>
      </footer>
    </section>

    <p v-if="!baris.length" class="rounded-2xl bg-white border border-stone-100 shadow-card px-5 py-10 text-center text-stone-400">
      Belum ada jadwal induksi.
    </p>
  </div>
</template>

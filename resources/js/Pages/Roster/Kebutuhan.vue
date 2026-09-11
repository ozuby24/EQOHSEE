<script setup lang="ts">
/**
 * Manpower plan lawan actual.
 *
 * DIBANDINGKAN PER JABATAN, bukan sebagai satu angka per site.
 * Kekurangan dua operator excavator tidak tertutup kelebihan tiga
 * admin — dan satu angka gabungan menyatakan "cukup" pada hari front
 * berhenti menggali.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { propHalaman } from '../../halaman';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
import { KEADAAN } from '../../Grafik/warna';

const props = propHalaman();
const { dialog, tanya, batal, lanjut } = useDialog();

const baris   = computed<any[]>(() => (props.baris ?? []) as any[]);
const tanggal = ref(String(props.tanggal ?? ''));

function muat() {
  router.get('/roster/kebutuhan', { tanggal: tanggal.value || undefined },
    { preserveState: true, preserveScroll: true, replace: true });
}

const form = useForm({
  blok_id: '', jabatan_id: '', mulai: '', selesai: '', jumlah: 1, shift: '', catatan: '',
});

function simpan() {
  form.post('/roster/kebutuhan', { preserveScroll: true, onSuccess: () => form.reset() });
}

async function hapus(id: number) {
  if (!await tanya('Hapus baris kebutuhan ini?')) return;

  useForm({}).delete(`/roster/kebutuhan/${id}`, { preserveScroll: true });
}

const kurang = computed(() => baris.value.filter((b) => b.selisih < 0));

function warna(selisih: number) {
  if (selisih < 0) return KEADAAN.gawat;
  if (selisih === 0) return KEADAAN.baik;
  return KEADAAN.netral;
}
</script>

<template>
  <Head :title="props.judul" />
  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />

  <div class="max-w-[1200px] mx-auto space-y-5">
    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-cam-ink">{{ props.judul }}</h2>
        <p class="text-[12.5px] text-stone-500 mt-0.5">{{ props.subjudul }}</p>
      </div>

      <Link href="/roster" class="eq-btn-lain">Kalender regu</Link>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
      <label class="block">
        <span class="text-[11px] text-stone-500">Tanggal yang dibandingkan</span>
        <input v-model="tanggal" type="date" class="mt-1 rounded-lg border-stone-200 text-[12px]" @change="muat">
      </label>

      <p v-if="kurang.length" class="mt-3 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-[12px] text-red-700">
        {{ kurang.length }} baris kekurangan orang pada tanggal ini.
      </p>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[11.5px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
              <th class="px-4 py-2 font-semibold">Area</th>
              <th class="px-4 py-2 font-semibold">Jabatan</th>
              <th class="px-4 py-2 font-semibold">Shift</th>
              <th class="px-4 py-2 font-semibold text-right">Butuh</th>
              <th class="px-4 py-2 font-semibold text-right">Terjadwal</th>
              <th class="px-4 py-2 font-semibold text-right">Selisih</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Berlaku</th>
              <th class="px-4 py-2 font-semibold"></th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="b in baris" :key="b.id" class="border-b border-stone-100"
                :class="b.selisih < 0 ? 'bg-red-50/40' : ''">
              <td class="px-4 py-2.5 font-semibold text-cam-ink">{{ b.blok || '—' }}</td>
              <td class="px-4 py-2.5 text-stone-600">{{ b.jabatan || 'semua jabatan' }}</td>
              <td class="px-4 py-2.5 text-stone-600">{{ b.shift || '—' }}</td>
              <td class="px-4 py-2.5 num text-right">{{ b.butuh }}</td>
              <td class="px-4 py-2.5 num text-right">{{ b.ada }}</td>
              <td class="px-4 py-2.5 num text-right font-bold" :style="{ color: warna(b.selisih) }">
                {{ b.selisih > 0 ? '+' : '' }}{{ b.selisih }}
              </td>
              <td class="px-4 py-2.5 num text-stone-500">
                {{ b.mulai }}<span v-if="b.selesai"> – {{ b.selesai }}</span>
              </td>
              <td class="px-4 py-2.5 text-right">
                <button class="text-[11px] text-red-600 hover:underline" @click="hapus(b.id)">Hapus</button>
              </td>
            </tr>

            <tr v-if="!baris.length">
              <td colspan="8" class="px-4 py-10 text-center text-stone-400">
                Belum ada kebutuhan yang berlaku pada tanggal ini.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="text-[13.5px] font-bold text-cam-ink mb-3">Tambah kebutuhan</h3>

      <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6" @submit.prevent="simpan">
        <label class="block">
          <span class="text-[11px] text-stone-500">Area</span>
          <select v-model="form.blok_id" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option value="">—</option>
            <option v-for="(nama, id) in (props.blok ?? {})" :key="id" :value="id">{{ nama }}</option>
          </select>
        </label>

        <label class="block">
          <span class="text-[11px] text-stone-500">Jabatan</span>
          <select v-model="form.jabatan_id" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option value="">Semua</option>
            <option v-for="(nama, id) in (props.jabatan ?? {})" :key="id" :value="id">{{ nama }}</option>
          </select>
        </label>

        <label class="block">
          <span class="text-[11px] text-stone-500">Shift</span>
          <select v-model="form.shift" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option value="">—</option>
            <option v-for="(label, kode) in (props.SHIFT ?? {})" :key="kode" :value="kode">{{ label }}</option>
          </select>
        </label>

        <label class="block">
          <span class="text-[11px] text-stone-500">Jumlah</span>
          <input v-model="form.jumlah" type="number" min="0" required
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="text-[11px] text-stone-500">Mulai</span>
          <input v-model="form.mulai" type="date" required
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="text-[11px] text-stone-500">Selesai</span>
          <input v-model="form.selesai" type="date"
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <div class="sm:col-span-2 lg:col-span-6">
          <button type="submit" class="eq-btn-utama" :disabled="form.processing">Simpan</button>
        </div>
      </form>
    </section>
  </div>
</template>

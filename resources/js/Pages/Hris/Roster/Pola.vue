<script setup lang="ts">
/**
 * Pola bergilir dan regu yang memakainya.
 *
 * POLA YANG MELANGGAR BATASNYA SENDIRI DITANDAI DI SINI, bukan menunggu
 * penerbitan. Pola 15:6 berjam 11 melanggar batas empat belas hari pada
 * TIAP siklusnya; menemukannya baru saat roster diterbitkan berarti
 * seluruh regu sudah terlanjur disusun, dan yang menyusunnya harus
 * mengulang sebulan penuh.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { propHalaman } from '../../../halaman';
import KopHalaman from '../../../Components/KopHalaman.vue';
import Dialog from '../../../Components/Dialog.vue';
import { useDialog } from '../../../dialog';

const props = propHalaman();
const { dialog, tanya, batal, lanjut } = useDialog();

const pola = computed<any[]>(() => (props.pola ?? []) as any[]);
const regu = computed<any[]>(() => (props.regu ?? []) as any[]);

const bukaRegu = ref<number | null>(null);

const formPola = useForm({
  kode: '', nama: '', kerja: 14, libur: 7,
  satuan: 'hari', jam: 11, shift: 'putar', keterangan: '', aktif: true,
  mulai_siang: '06:00', mulai_malam: '18:00', toleransi_menit: 15,
});

function simpanPola() {
  formPola.post('/hris/roster/pola', { preserveScroll: true, onSuccess: () => formPola.reset() });
}

async function hapusPola(id: number, kode: string) {
  if (!await tanya(`Hapus pola ${kode}? Regu yang memakainya akan kehilangan polanya.`)) return;

  useForm({}).delete(`/hris/roster/pola/${id}`, { preserveScroll: true });
}

const formRegu = useForm({
  nama: '', pola_roster_id: '', blok_id: '', mulai: '', shift: 'siang', catatan: '', aktif: true,
});

function simpanRegu() {
  formRegu.post('/hris/roster/regu', { preserveScroll: true, onSuccess: () => formRegu.reset() });
}

async function hapusRegu(id: number, nama: string) {
  if (!await tanya(`Hapus regu ${nama} beserta keanggotaannya?`)) return;

  useForm({}).delete(`/hris/roster/regu/${id}`, { preserveScroll: true });
}

const formAnggota = useForm({ pekerja_id: '', mulai: '', selesai: '' });

function tambahAnggota(id: number) {
  formAnggota.post(`/hris/roster/regu/${id}/anggota`, {
    preserveScroll: true, onSuccess: () => formAnggota.reset(),
  });
}

/** Panjang siklus dan jam per siklus, dihitung langsung di formulir. */
const pratinjau = computed(() => {
  const kali = formPola.satuan === 'minggu' ? 7 : 1;
  const kerja = Number(formPola.kerja || 0) * kali;
  const libur = Number(formPola.libur || 0) * kali;

  return {
    kerja,
    libur,
    siklus: Math.max(1, kerja + libur),
    jam: kerja * Number(formPola.jam || 0),
  };
});

const langgarBaru = computed(() => {
  const l: string[] = [];
  const b = props.BATAS ?? {};

  if (Number(formPola.jam) > Number(b.jam_hari)) l.push(`Melebihi ${b.jam_hari} jam sehari`);
  if (pratinjau.value.kerja > Number(b.hari_beruntun)) l.push(`Lebih dari ${b.hari_beruntun} hari berturut-turut`);
  if (pratinjau.value.libur > 0 && pratinjau.value.libur < Number(b.istirahat))
    l.push(`Istirahat kurang dari ${b.istirahat} hari`);
  if (Math.min(pratinjau.value.kerja, 14) * Number(formPola.jam) > Number(b.jam_14_hari))
    l.push(`Melebihi ${b.jam_14_hari} jam dalam 14 hari`);

  return l;
});
</script>

<template>
  <Head :title="props.judul" />
  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />

  <div class="max-w-[1400px] mx-auto space-y-5">
    <KopHalaman :judul="props.judul as string" :subjudul="props.subjudul as string"
                tagline="Built To Rotate"
                :remah="[['HRIS', '/hris'], ['Roster & Shift', null], ['Pola & Regu', null]]" ringkas />

    <section class="-mt-2 flex flex-wrap items-end justify-end gap-3">

      <Link href="/hris/roster" class="eq-btn-lain">Kalender regu</Link>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="text-[13.5px] font-bold text-cam-ink mb-3">Pola baru</h3>

      <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="simpanPola">
        <label class="block">
          <span class="block text-[11px] text-stone-500">Kode</span>
          <input v-model="formPola.kode" required placeholder="14:7"
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block lg:col-span-2">
          <span class="block text-[11px] text-stone-500">Nama</span>
          <input v-model="formPola.nama" required placeholder="Empat belas hari kerja, tujuh hari libur"
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Satuan</span>
          <select v-model="formPola.satuan" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option v-for="(label, kode) in (props.SATUAN ?? {})" :key="kode" :value="kode">{{ label }}</option>
          </select>
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Kerja</span>
          <input v-model="formPola.kerja" type="number" min="1" required
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Libur</span>
          <input v-model="formPola.libur" type="number" min="0" required
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Jam per hari</span>
          <input v-model="formPola.jam" type="number" min="1" max="24" required
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Shift</span>
          <select v-model="formPola.shift" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option v-for="(label, kode) in (props.SHIFT ?? {})" :key="kode" :value="kode">{{ label }}</option>
          </select>
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Mulai shift siang</span>
          <input v-model="formPola.mulai_siang" type="time"
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Mulai shift malam</span>
          <input v-model="formPola.mulai_malam" type="time"
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Toleransi telat (menit)</span>
          <input v-model="formPola.toleransi_menit" type="number" min="0" max="180"
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <div class="sm:col-span-2 lg:col-span-4 space-y-2">
          <p class="text-[11.5px] text-stone-500">
            Absensi dihitung terhadap jam mulai di atas: yang datang lewat dari toleransinya
            tercatat terlambat.
          </p>

          <p class="text-[11.5px] text-stone-500">
            Siklus <span class="num font-semibold">{{ pratinjau.siklus }}</span> hari
            — <span class="num">{{ pratinjau.kerja }}</span> hari kerja,
            <span class="num">{{ pratinjau.libur }}</span> hari libur,
            <span class="num">{{ pratinjau.jam }}</span> jam per siklus.
          </p>

          <p v-for="l in langgarBaru" :key="l"
             class="rounded-lg bg-red-50 border border-red-200 px-3 py-1.5 text-[11.5px] text-red-700">
            {{ l }} — Kepmenakertrans 234/2003.
          </p>

          <button type="submit" class="eq-btn-utama" :disabled="formPola.processing">Tambah pola</button>
        </div>
      </form>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100">
        <h3 class="text-[13.5px] font-bold text-cam-ink">
          Pola <span class="font-normal text-stone-400">| {{ pola.length }} pola</span>
        </h3>
      </header>

      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[11.5px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
              <th class="px-4 py-2 font-semibold">Kode</th>
              <th class="px-4 py-2 font-semibold">Nama</th>
              <th class="px-4 py-2 font-semibold text-right">Siklus</th>
              <th class="px-4 py-2 font-semibold text-right">Jam/hari</th>
              <th class="px-4 py-2 font-semibold text-right">Jam/siklus</th>
              <th class="px-4 py-2 font-semibold">Shift</th>
              <th class="px-4 py-2 font-semibold">Mulai</th>
              <th class="px-4 py-2 font-semibold text-right">Regu</th>
              <th class="px-4 py-2 font-semibold">Kepatuhan</th>
              <th class="px-4 py-2 font-semibold"></th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="p in pola" :key="p.id" class="border-b border-stone-100"
                :class="p.langgar?.length ? 'bg-red-50/40' : ''">
              <td class="px-4 py-2.5 num font-bold text-cam-ink">{{ p.kode }}</td>
              <td class="px-4 py-2.5 text-stone-600">{{ p.nama }}</td>
              <td class="px-4 py-2.5 num text-right">
                {{ p.siklus }} hari
                <span class="text-stone-400">({{ p.hariKerja }}+{{ p.siklus - p.hariKerja }})</span>
              </td>
              <td class="px-4 py-2.5 num text-right">{{ p.jam }}</td>
              <td class="px-4 py-2.5 num text-right">{{ p.jamSiklus }}</td>
              <td class="px-4 py-2.5 text-stone-600">{{ props.SHIFT?.[p.shift] ?? p.shift }}</td>
              <td class="px-4 py-2.5 num text-stone-600">
                {{ p.mulaiSiang }} / {{ p.mulaiMalam }}
                <span class="text-stone-400">±{{ p.toleransi }}′</span>
              </td>
              <td class="px-4 py-2.5 num text-right">{{ p.regu }}</td>
              <td class="px-4 py-2.5">
                <span v-if="!p.langgar?.length"
                      class="rounded-full bg-green-100 text-green-700 px-2 py-0.5 text-[10.5px] font-semibold">
                  Sesuai
                </span>
                <span v-for="l in (p.langgar ?? [])" :key="l"
                      class="block text-[10.5px] text-red-700">{{ l }}</span>
              </td>
              <td class="px-4 py-2.5 text-right">
                <button class="text-[11px] text-red-600 hover:underline" @click="hapusPola(p.id, p.kode)">
                  Hapus
                </button>
              </td>
            </tr>

            <tr v-if="!pola.length">
              <td colspan="10" class="px-4 py-8 text-center text-stone-400">Belum ada pola roster.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="text-[13.5px] font-bold text-cam-ink mb-3">Regu baru</h3>

      <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="simpanRegu">
        <label class="block">
          <span class="block text-[11px] text-stone-500">Nama</span>
          <input v-model="formRegu.nama" required placeholder="Regu A"
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Pola</span>
          <select v-model="formRegu.pola_roster_id" required
                  class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option value="">Pilih pola…</option>
            <option v-for="p in pola" :key="p.id" :value="p.id">{{ p.kode }} — {{ p.nama }}</option>
          </select>
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Area kerja</span>
          <select v-model="formRegu.blok_id" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option value="">—</option>
            <option v-for="(nama, id) in (props.blok ?? {})" :key="id" :value="id">{{ nama }}</option>
          </select>
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Mulai siklus</span>
          <input v-model="formRegu.mulai" type="date" required
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Shift awal</span>
          <select v-model="formRegu.shift" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option value="siang">Siang</option>
            <option value="malam">Malam</option>
          </select>
        </label>

        <div class="sm:col-span-2 lg:col-span-5">
          <button type="submit" class="eq-btn-utama" :disabled="formRegu.processing">Tambah regu</button>
          <span class="ml-3 text-[11px] text-stone-500">
            Tanggal mulai adalah jangkar siklusnya. Dua regu berjangkar berselisih setengah siklus
            akan saling mengisi — yang satu pulang ketika yang lain datang.
          </span>
        </div>
      </form>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100">
        <h3 class="text-[13.5px] font-bold text-cam-ink">
          Regu <span class="font-normal text-stone-400">| {{ regu.length }} regu</span>
        </h3>
      </header>

      <ul class="divide-y divide-stone-100">
        <li v-for="g in regu" :key="g.id" class="px-5 py-3.5 space-y-2">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
              <span class="font-bold text-cam-ink">{{ g.nama }}</span>
              <span class="text-[11.5px] text-stone-500">
                · {{ g.pola || 'tanpa pola' }}
                · {{ g.blok || 'tanpa area' }}
                · jangkar <span class="num">{{ g.mulai || '—' }}</span>
                · {{ g.anggota }} anggota
              </span>
            </div>

            <div class="flex items-center gap-2">
              <Link :href="`/hris/roster?regu=${g.id}`" class="eq-btn-lain">Kalender</Link>
              <button class="eq-btn-lain" @click="bukaRegu = bukaRegu === g.id ? null : g.id">
                {{ bukaRegu === g.id ? 'Tutup' : 'Anggota' }}
              </button>
            </div>
          </div>

          <div v-if="bukaRegu === g.id" class="pt-2 border-t border-stone-100 space-y-2">
            <form class="flex flex-wrap items-end gap-2" @submit.prevent="tambahAnggota(g.id)">
              <label class="block">
                <span class="block text-[11px] text-stone-500">Pekerja</span>
                <select v-model="formAnggota.pekerja_id" required
                        class="mt-1 rounded-lg border-stone-200 text-[12px] w-56">
                  <option value="">Pilih pekerja…</option>
                  <option v-for="pk in (props.pekerja ?? [])" :key="pk.id" :value="pk.id">
                    {{ pk.nama }}
                  </option>
                </select>
              </label>

              <label class="block">
                <span class="block text-[11px] text-stone-500">Mulai</span>
                <input v-model="formAnggota.mulai" type="date" required
                       class="mt-1 rounded-lg border-stone-200 text-[12px]">
              </label>

              <label class="block">
                <span class="block text-[11px] text-stone-500">Selesai (opsional)</span>
                <input v-model="formAnggota.selesai" type="date"
                       class="mt-1 rounded-lg border-stone-200 text-[12px]">
              </label>

              <button type="submit" class="eq-btn-lain">Tambahkan</button>
            </form>

            <p class="text-[11px] text-stone-500">
              Keanggotaan bertanggal: roster yang sudah berlalu tidak ikut berpindah ketika
              seseorang dipindah regu.
            </p>

            <button class="text-[11.5px] text-red-600 hover:underline" @click="hapusRegu(g.id, g.nama)">
              Hapus regu ini
            </button>
          </div>
        </li>

        <li v-if="!regu.length" class="px-5 py-8 text-center text-stone-400">Belum ada regu.</li>
      </ul>
    </section>
  </div>
</template>

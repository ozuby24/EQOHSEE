<script setup lang="ts">
/**
 * Mine Permit — kartu yang dibawa ke gerbang.
 *
 * MASA BERLAKU EFEKTIF DITAMPILKAN BERDAMPINGAN dengan tanggal kartunya.
 * Keduanya berbeda persis pada kartu yang paling perlu diperhatikan:
 * yang tanggalnya masih 31 Desember tetapi MCU-nya sudah lewat. Hanya
 * menampilkan tanggal kartunya membuat kartu itu terbaca sah — dan
 * yang membacanya adalah petugas pos.
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

const cari   = ref(String(props.saring?.cari ?? ''));
const status = ref(String(props.saring?.status ?? ''));

const buat = useForm({
  pekerja_id: '', no_registrasi: '', tanggal: '',
  tipe_permit_id: '', kategori_permit_id: '', cakupan_area: '', kode_warna: '',
});

/** Kategori yang ditawarkan mengikuti tipe yang dipilih, bukan seluruhnya. */
const kategoriTipe = computed(() =>
  ((props.kategori ?? []) as any[]).filter((k) => String(k.tipe_permit_id) === String(buat.tipe_permit_id)));

function simpan() {
  buat.post('/miners/permit', { preserveScroll: true, onSuccess: () => buat.reset() });
}

const tindakan = useForm({ keadaan: 'setuju', catatan: '' });

function tindak(id: number, keadaan: string) {
  tindakan.keadaan = keadaan;
  tindakan.post(`/miners/permit/${id}/tindak`, { preserveScroll: true });
}

const cabut = useForm({ alasan_cabut: '' });

async function cabutKartu(id: number, nomor: string) {
  if (!await tanya(`Cabut Mine Permit ${nomor || 'ini'}? Pemegangnya tidak lagi boleh masuk.`)) return;

  cabut.alasan_cabut = cabut.alasan_cabut || 'Dicabut oleh OHSE.';
  cabut.post(`/miners/permit/${id}/cabut`, { preserveScroll: true });
}

async function hapus(id: number, nomor: string) {
  if (!await tanya(`Hapus Mine Permit ${nomor || 'ini'}?`)) return;

  useForm({}).delete(`/miners/permit/${id}`, { preserveScroll: true });
}

const bolehTindak = computed(() =>
  ((props.peranSaya ?? []) as string[]).some((p) => p !== 'pjo'));

const WARNA_LABEL = computed<Record<string, any>>(() => (props.WARNA ?? {}) as Record<string, any>);
</script>

<template>
  <Head :title="props.judul" />
  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />

  <div class="max-w-[1400px] mx-auto space-y-5">

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="text-[13.5px] font-bold text-cam-ink mb-3">Mine Permit baru</h3>

      <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="simpan">
        <label class="block">
          <span class="block text-[11px] text-stone-500">Pekerja</span>
          <select v-model="buat.pekerja_id" required class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option value="">Pilih pekerja…</option>
            <option v-for="pk in (props.pekerja ?? [])" :key="pk.id" :value="pk.id">{{ pk.nama }}</option>
          </select>
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Nomor kartu</span>
          <input v-model="buat.no_registrasi" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Tanggal terbit</span>
          <input v-model="buat.tanggal" type="date" required
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Jenis permit</span>
          <select v-model="buat.tipe_permit_id" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option value="">Pilih jenis…</option>
            <option v-for="t in (props.tipe ?? [])" :key="t.id" :value="t.id">
              {{ t.nama }}<span v-if="t.hari_berlaku"> ({{ t.hari_berlaku }} hari)</span>
            </option>
          </select>
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Kategori</span>
          <select v-model="buat.kategori_permit_id" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option value="">Pilih kategori…</option>
            <option v-for="k in kategoriTipe" :key="k.id" :value="k.id">{{ k.nama }}</option>
          </select>
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Zona akses</span>
          <select v-model="buat.cakupan_area" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option value="">Pilih zona…</option>
            <option v-for="(z, kode) in (props.CAKUPAN ?? {})" :key="kode" :value="kode">{{ z.label }}</option>
          </select>
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Warna kartu</span>
          <select v-model="buat.kode_warna" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option value="">Pilih warna…</option>
            <option v-for="(w, kode) in WARNA_LABEL" :key="kode" :value="kode">{{ w.label }}</option>
          </select>
        </label>

        <div class="sm:col-span-2 lg:col-span-4">
          <button type="submit" class="eq-btn-utama" :disabled="buat.processing">Buat kartu (draf)</button>
          <span v-if="buat.errors.pekerja_id" class="ml-3 text-[11.5px] text-red-600">{{ buat.errors.pekerja_id }}</span>
        </div>
      </form>

      <p class="mt-3 text-[11px] text-stone-500">
        Masa berlakunya dihitung sendiri menurut jenisnya — Visitor 7 hari, Temporary 30 hari,
        selebihnya sampai 31 Desember tahun terbit. MCU dan induksi terakhir ikut dilekatkan
        pada kartu, supaya pertanyaan "hasil MCU mana yang menjadi dasar kartu ini" tetap
        terjawab setelah MCU berikutnya terbit.
      </p>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100 flex flex-wrap items-center gap-3">
        <h3 class="text-[13.5px] font-bold text-cam-ink flex-1 min-w-0">
          Kartu <span class="font-normal text-stone-400">| {{ baris.length }} data</span>
        </h3>
      </header>

      <ul class="divide-y divide-stone-100">
        <li v-for="k in baris" :key="k.id" class="px-5 py-4 space-y-2">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
              <Link v-if="k.pekerja_id" :href="`/miners/${k.pekerja_id}`"
                    class="font-bold text-cam-lime-deep hover:underline">{{ k.pekerja || '—' }}</Link>
              <span class="text-[11.5px] text-stone-500">
                · <span class="num">{{ k.nomor || 'tanpa nomor' }}</span>
                · {{ k.tipe || '—' }} · {{ k.cakupan || '—' }}
                · {{ props.STATUS?.[k.status] ?? k.status }}
              </span>
            </div>

            <div class="flex items-center gap-2">
              <Lencana :keadaan="k.keadaan" :label="props.KEADAAN?.[k.keadaan]" :nada="props.NADA" :sisa="k.sisa" />
              <button class="eq-btn-lain" @click="buka = buka === k.id ? null : k.id">
                {{ buka === k.id ? 'Tutup' : 'Kelola' }}
              </button>
            </div>
          </div>

          <p class="text-[11.5px] text-stone-500">
            Terbit {{ k.tanggal || '—' }} · tertulis berlaku sampai
            <span class="num">{{ k.sampai || '—' }}</span>
            <span v-if="k.efektif && k.efektif !== k.sampai" class="text-amber-700">
              — tetapi efektif habis <span class="num">{{ k.efektif }}</span>, sebab MCU-nya
              habis <span class="num">{{ k.mcu }}</span>
            </span>
            <span v-if="k.gugurMcu" class="text-red-600 font-semibold"> · sudah gugur karena MCU</span>
          </p>

          <Langkah :alur="k.alur" />

          <div v-if="buka === k.id" class="pt-2 space-y-3 border-t border-stone-100">
            <div v-if="bolehTindak" class="flex flex-wrap items-end gap-2">
              <input v-model="tindakan.catatan" placeholder="Catatan peninjau (opsional)"
                     class="rounded-lg border-stone-200 text-[12px] w-72" aria-label="Catatan peninjau">
              <button class="eq-btn-utama" @click="tindak(k.id, 'setuju')">Setujui</button>
              <button class="eq-btn-lain" @click="tindak(k.id, 'dikembalikan')">Kembalikan</button>
              <button class="eq-btn-lain" @click="tindak(k.id, 'tolak')">Tolak</button>
            </div>

            <p v-if="tindakan.errors.keadaan" class="text-[11.5px] text-red-600">{{ tindakan.errors.keadaan }}</p>

            <div class="flex flex-wrap items-end gap-3">
              <input v-model="cabut.alasan_cabut" placeholder="Alasan pencabutan"
                     class="rounded-lg border-stone-200 text-[12px] w-72" aria-label="Alasan pencabutan">
              <button class="text-[11.5px] text-amber-700 hover:underline" @click="cabutKartu(k.id, k.nomor)">
                Cabut kartu
              </button>
              <button class="text-[11.5px] text-red-600 hover:underline" @click="hapus(k.id, k.nomor)">
                Hapus kartu
              </button>
            </div>
          </div>
        </li>

        <li v-if="!baris.length" class="px-5 py-10 text-center text-stone-400">
          Belum ada Mine Permit.
        </li>
      </ul>
    </section>
  </div>
</template>

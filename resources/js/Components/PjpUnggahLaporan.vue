<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Dialog from '../Components/Dialog.vue';
import { useDialog } from '../dialog';

const { dialog, tanya, batal, lanjut } = useDialog();

/**
 * Satu kartu per jenis dokumen: daftar unggahan dan formulirnya.
 *
 * `terkunci` berisi alasan mengapa unggahan sedang ditutup — dipakai
 * Laporan Triwulan di luar bulan yang dibuka. Menyembunyikan formulirnya
 * saja tidak menutup apa pun: server memeriksa ulang jendela itu sendiri.
 * Yang dilakukan pesan ini hanyalah menjelaskan, supaya penutupannya
 * tidak terbaca sebagai kerusakan.
 */
const props = defineProps<{
  jenis: string;
  label: string;
  laporans: Array<Record<string, any>>;
  kesesuaianOpsi: Record<string, string>;
  simpan: string;
  nilaiPola: string;
  hapusPola: string;
  terkunci: string | null;
}>();

const untuk = (pola: string, id: number) => String(pola).replace('__ID__', String(id));

const kunciBerkas = ref(0);

const form = useForm<{ jenis: string; periode: string; catatan: string; file: File | null }>({
  jenis: props.jenis,
  periode: '',
  catatan: '',
  file: null,
});

function pilihBerkas(ev: Event) {
  form.file = (ev.target as HTMLInputElement).files?.[0] ?? null;
}

function unggah() {
  form.post(props.simpan, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      form.reset('periode', 'catatan', 'file');
      // Isian berkas tidak dapat dikosongkan lewat v-model; menggantinya
      // dengan kunci baru memaksa peramban menggambar ulang isian itu.
      kunciBerkas.value += 1;
    },
  });
}

function nilai(laporan: Record<string, any>, nilaiBaru: string) {
  router.patch(untuk(props.nilaiPola, laporan.id), { kesesuaian_isi: nilaiBaru }, { preserveScroll: true });
}

async function hapus(laporan: Record<string, any>) {
  if (!await tanya({
    judul: `Hapus dokumen “${laporan.file_name}”?`,
    pesan: 'Berkasnya ikut terhapus dan tidak dapat dikembalikan. '
      + 'Unggah ulang akan tercatat dengan tanggal hari ini, '
      + 'sehingga dokumen yang dahulu tepat waktu dapat berubah menjadi terlambat.',
    nada: 'bahaya',
    labelAksi: 'Hapus',
  })) return;

  router.delete(untuk(props.hapusPola, laporan.id), { preserveScroll: true });
}

const ukuran = (byte: number) => {
  if (byte < 1024) return `${byte} B`;
  if (byte < 1024 * 1024) return `${(byte / 1024).toFixed(1)} KB`;
  return `${(byte / (1024 * 1024)).toFixed(1)} MB`;
};
</script>

<template>
  <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
    <h4 class="font-bold text-[13px] text-stone-800">{{ props.label }}</h4>

    <p v-if="!props.laporans.length" class="mt-2 text-[12px] text-stone-400">Belum ada dokumen diunggah.</p>

    <ul v-else class="mt-3 divide-y divide-stone-100">
      <li v-for="l in props.laporans" :key="l.id" class="py-3 text-[12px] space-y-1.5">
        <div class="flex items-center justify-between gap-3">
          <a :href="l.file_url" target="_blank" rel="noopener noreferrer"
             class="truncate font-semibold text-cam-orange hover:underline">{{ l.file_name }}</a>
          <button type="button" class="shrink-0 text-[11px] font-bold text-red-600" @click="hapus(l)">Hapus</button>
        </div>

        <p class="text-[11px] text-stone-400">
          <span v-if="l.periode">{{ l.periode }} · </span>{{ ukuran(l.file_size) }} · diunggah {{ l.diunggah }}
        </p>

        <div class="flex flex-wrap items-center gap-2">
          <!--
            Ketepatan waktu dihitung dari tanggal unggahnya dan tidak
            dapat diubah orang; kesesuaian isi adalah penilaian manusia.
            Keduanya sengaja berdiri sendiri — dokumen dapat datang tepat
            waktu dengan isi yang tidak sesuai, dan sebaliknya.
          -->
          <span class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                :class="l.tepat_waktu ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'">
            {{ l.tepat_waktu ? 'Tepat waktu' : 'Terlambat' }}
          </span>

          <select :value="l.kesesuaian_isi ?? ''" class="rounded-lg border-stone-200 text-[11px] py-1"
                  :aria-label="`Kesesuaian isi dokumen ${l.file_name}`"
                  @change="nilai(l, ($event.target as HTMLSelectElement).value)">
            <option value="">Belum dinilai</option>
            <option v-for="(label, kunci) in props.kesesuaianOpsi" :key="kunci" :value="kunci">{{ label }}</option>
          </select>
        </div>

        <p v-if="l.catatan" class="text-[11px] italic text-stone-400">{{ l.catatan }}</p>
      </li>
    </ul>

    <p v-if="props.terkunci" class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-[11px] text-amber-800">
      {{ props.terkunci }}
    </p>

    <form v-else class="mt-4 space-y-2 border-t border-stone-100 pt-4" @submit.prevent="unggah">
      <div class="grid gap-2 sm:grid-cols-2">
        <input v-model="form.periode" type="text" placeholder="Periode (mis. September 2026)"
               class="rounded-lg border-stone-200 text-[12px]">
        <input :key="kunciBerkas" type="file" class="text-[11px] text-stone-500" @change="pilihBerkas">
      </div>

      <input v-model="form.catatan" type="text" placeholder="Catatan (opsional)"
             class="w-full rounded-lg border-stone-200 text-[12px]">

      <p v-if="form.errors.file" class="text-[11px] text-red-600">{{ form.errors.file }}</p>
      <p v-if="form.errors.jenis" class="text-[11px] text-red-600">{{ form.errors.jenis }}</p>

      <p class="text-[10px] text-stone-400">
        PDF, Word, Excel, atau gambar. Maksimal 10 MB.
      </p>

      <button :disabled="form.processing || !form.file" class="eq-btn-utama disabled:opacity-40">
        {{ form.processing ? 'Mengunggah…' : 'Unggah dokumen' }}
      </button>
    </form>

    <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
  </div>
</template>

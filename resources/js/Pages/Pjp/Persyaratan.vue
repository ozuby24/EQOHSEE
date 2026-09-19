<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PjpBarCapaian from '../../Components/PjpBarCapaian.vue';
import PjpBilahStatus from '../../Components/PjpBilahStatus.vue';
import PjpLencanaStatus from '../../Components/PjpLencanaStatus.vue';
import PjpNav from '../../Components/PjpNav.vue';
import PjpSaring from '../../Components/PjpSaring.vue';

const props = defineProps<{
  judul: string;
  subjudul: string;
  pjps: Array<{
    id: number; nama_perusahaan: string; status: string; statusLabel: string;
    capaian: number | null; smkpPersentase?: number; kategoriRisiko?: string;
  }>;
  saring: { cari: string; status: string };
  statusJumlah: Record<string, number>;
  statusOpsi: Record<string, string>;
  checklistRingkas: { kategori: number; pertanyaan: number; totalBobot: number } | null;
  tautan: Record<string, string>;
}>();

const untuk = (pola: string, id: number) => String(pola).replace('__ID__', String(id));

const angka = (n: number) => Number.isInteger(n) ? String(n) : n.toFixed(1);

const TAHAP = [
  { judul: 'Persyaratan', ket: 'Kelengkapan dokumen legalitas dan syarat administratif calon PJP.' },
  { judul: 'Seleksi',     ket: 'Penilaian checklist prakualifikasi SMKP berbobot untuk tiap calon.' },
  { judul: 'Penetapan',   ket: 'Penetapan resmi PJP yang lolos, beserta kategori risiko pekerjaan yang layak.' },
];
</script>

<template>
  <Head :title="props.judul" />

  <div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <!-- Judul dan subjudulnya digambar kop kerangka; "Aspek 1 dari 3"
           tidak, dan itulah yang tersisa di sini. -->
      <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-cam-orange">Aspek 1 dari 3</p>
      <Link :href="props.tautan.baru" class="eq-btn-utama px-5 !flex-none">+ Tambah PJP</Link>
    </div>

    <PjpNav :tautan="props.tautan" aktif="persyaratan" />

    <PjpBilahStatus judul="Sebaran Status Seluruh PJP" :jumlah="props.statusJumlah" :opsi="props.statusOpsi" />

    <!--
      Tiap baris menuju langsung ke checklist-nya, bukan ke halaman
      detail umum. Grafik ini memang tentang checklist persyaratan, dan
      memaksa pembacanya singgah dulu di halaman detail hanya menambah
      satu klik di antara "ada yang kurang" dan formulir yang mengisinya.
    -->
    <PjpBarCapaian
      judul="Capaian Persyaratan PJP per Perusahaan"
      kosong="Belum ada data PJP."
      label-tanpa-data="Belum diisi"
      :pola="props.tautan.checklistUntuk"
      :items="props.pjps.map((p) => ({ id: p.id, label: p.nama_perusahaan, nilai: p.capaian }))"
    />

    <section class="grid gap-3 sm:grid-cols-3">
      <article v-for="t in TAHAP" :key="t.judul" class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
        <h3 class="font-bold text-[13px] text-stone-800">{{ t.judul }}</h3>
        <p class="mt-1 text-[11px] text-stone-500">{{ t.ket }}</p>
      </article>
    </section>

    <section v-if="props.checklistRingkas" class="rounded-2xl border border-stone-200 bg-stone-50 p-5">
      <h3 class="font-bold text-[13px] text-stone-800">Checklist prakualifikasi SMKP</h3>
      <p class="mt-1 text-[12px] text-stone-600">
        {{ props.checklistRingkas.kategori }} kategori dan {{ props.checklistRingkas.pertanyaan }} pertanyaan berbobot.
        Kategori A–P berjumlah {{ props.checklistRingkas.totalBobot }} poin dan menentukan skornya; Dokumen Legalitas
        berdiri di luar jumlah itu sebagai syarat wajib lengkap. Klik salah satu baris pada grafik di atas
        untuk membuka formulir checklist perusahaan tersebut.
      </p>
      <p class="mt-2 text-[11px] text-stone-500">
        Label kategori risiko menyatakan tingkat risiko pekerjaan yang <b>layak dipercayakan</b> kepada PJP
        tersebut — bukan tingkat bahayanya. Skor tinggi karena itu berlabel “Kritis”.
      </p>
    </section>

    <section class="space-y-3">
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <h3 class="text-[15px] font-extrabold text-stone-800">Daftar Seluruh PJP</h3>
        <Link :href="props.tautan.daftar"
              class="inline-flex min-h-[24px] items-center rounded text-[11px] font-bold text-cam-orange">Kelola data PJP →</Link>
      </div>

      <PjpSaring :aksi="props.tautan.terapkan" :awal="props.saring" :status-opsi="props.statusOpsi" />

      <div v-if="props.pjps.length" class="rounded-2xl bg-white border border-stone-100 shadow-card divide-y divide-stone-100 overflow-hidden">
        <Link
          v-for="pjp in props.pjps"
          :key="pjp.id"
          :href="untuk(props.tautan.checklistUntuk, pjp.id)"
          class="flex flex-col gap-2 px-4 py-3 text-[12px] hover:bg-stone-50 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
        >
          <span class="font-semibold text-stone-700">{{ pjp.nama_perusahaan }}</span>
          <div class="flex flex-wrap items-center gap-2">
            <span v-if="pjp.smkpPersentase !== undefined"
                  class="rounded-full bg-stone-100 px-2 py-0.5 text-[10px] font-bold text-stone-600">
              Persyaratan: {{ angka(pjp.smkpPersentase) }}%
            </span>
            <span v-if="pjp.kategoriRisiko"
                  class="rounded-full bg-stone-100 px-2 py-0.5 text-[10px] font-bold text-stone-600">
              Layak risiko: {{ pjp.kategoriRisiko }}
            </span>
            <PjpLencanaStatus :status="pjp.status" :label="pjp.statusLabel" />
          </div>
        </Link>
      </div>

      <p v-else class="rounded-2xl bg-white border border-stone-100 shadow-card p-8 text-center text-[12px] text-stone-400">
        {{ props.saring.cari || props.saring.status
          ? 'Tidak ada PJP yang cocok dengan penyaring.'
          : 'Belum ada data PJP. Tambahkan data untuk mulai memantau.' }}
      </p>
    </section>
  </div>
</template>

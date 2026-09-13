<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PjpBarCapaian from '../../Components/PjpBarCapaian.vue';
import PjpBilahStatus from '../../Components/PjpBilahStatus.vue';
import PjpLencanaStatus from '../../Components/PjpLencanaStatus.vue';
import PjpNav from '../../Components/PjpNav.vue';
import PjpSaring from '../../Components/PjpSaring.vue';
import PjpTrenEvaluasi from '../../Components/PjpTrenEvaluasi.vue';

const props = defineProps<{
  judul: string;
  subjudul: string;
  pjps: Array<{
    id: number; nama_perusahaan: string; status: string; statusLabel: string;
    capaian: number | null;
    evaluasiTerakhir: { periode: string; skor_rata_rata: number } | null;
    riwayatEvaluasi: Array<{ tahun: number; semester: number; periode: string; skor_rata_rata: number }>;
  }>;
  saring: { cari: string; status: string };
  statusJumlah: Record<string, number>;
  statusOpsi: Record<string, string>;
  tautan: Record<string, string>;
}>();

const untuk = (pola: string, id: number) => String(pola).replace('__ID__', String(id));

const angka = (n: number) => Number.isInteger(n) ? String(n) : n.toFixed(1);
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1400px] mx-auto space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-cam-orange">Aspek 3 dari 3</p>
        <h2 class="text-2xl font-extrabold tracking-tight text-stone-800">{{ props.judul }}</h2>
        <p class="text-[12px] text-stone-500 mt-1">{{ props.subjudul }}</p>
      </div>
      <Link :href="props.tautan.baru" class="eq-btn-utama px-5 !flex-none">+ Tambah PJP</Link>
    </div>

    <PjpNav :tautan="props.tautan" aktif="evaluasi" />

    <PjpBilahStatus judul="Sebaran Status Seluruh PJP" :jumlah="props.statusJumlah" :opsi="props.statusOpsi" />

    <PjpBarCapaian
      judul="Skor Evaluasi Kinerja per Perusahaan"
      kosong="Belum ada data PJP."
      label-tanpa-data="Belum dievaluasi"
      :pola="props.tautan.detail"
      :items="props.pjps.map((p) => ({ id: p.id, label: p.nama_perusahaan, nilai: p.capaian }))"
    />

    <PjpTrenEvaluasi
      judul="Perbandingan Tren Evaluasi Antar PJP"
      :seri="props.pjps.map((p) => ({ id: p.id, label: p.nama_perusahaan, titik: p.riwayatEvaluasi }))"
    />

    <section class="rounded-2xl border border-stone-200 bg-stone-50 p-5">
      <h3 class="font-bold text-[13px] text-stone-800">Evaluasi kinerja semester</h3>
      <p class="mt-1 text-[12px] text-stone-600">
        Diisi dari kartu Evaluasi Kinerja pada halaman detail tiap PJP, per semester, dengan tiga skor 0–100:
        Teknis, Keselamatan &amp; Kesehatan, dan Lingkungan. Rata-ratanya dihitung otomatis, dan mengisi ulang
        semester yang sama akan menimpa nilainya alih-alih menambah baris baru.
      </p>
    </section>

    <section class="space-y-3">
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <h3 class="text-[15px] font-extrabold text-stone-800">Daftar Seluruh PJP</h3>
        <Link :href="props.tautan.daftar" class="text-[11px] font-bold text-cam-orange">Kelola data PJP →</Link>
      </div>

      <PjpSaring :aksi="props.tautan.terapkan" :awal="props.saring" :status-opsi="props.statusOpsi" />

      <div v-if="props.pjps.length" class="rounded-2xl bg-white border border-stone-100 shadow-card divide-y divide-stone-100 overflow-hidden">
        <Link
          v-for="pjp in props.pjps"
          :key="pjp.id"
          :href="untuk(props.tautan.detail, pjp.id)"
          class="flex flex-col gap-2 px-4 py-3 text-[12px] hover:bg-stone-50 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
        >
          <span class="font-semibold text-stone-700">{{ pjp.nama_perusahaan }}</span>
          <div class="flex flex-wrap items-center gap-2">
            <!--
              Yang belum pernah dinilai diberi lencana tersendiri, bukan
              angka 0. Evaluasi bernilai 0 adalah penilaian; belum ada
              evaluasi adalah ketiadaan penilaian.
            -->
            <span v-if="pjp.evaluasiTerakhir"
                  class="rounded-full bg-stone-100 px-2 py-0.5 text-[10px] font-bold text-stone-600">
              {{ pjp.evaluasiTerakhir.periode }}: {{ angka(pjp.evaluasiTerakhir.skor_rata_rata) }}
            </span>
            <span v-else class="rounded-full bg-stone-100 px-2 py-0.5 text-[10px] font-bold text-stone-400">
              Belum dievaluasi
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

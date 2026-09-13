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
    capaian: number | null;
  }>;
  saring: { cari: string; status: string };
  statusJumlah: Record<string, number>;
  statusOpsi: Record<string, string>;
  jenisLaporan: Record<string, string> | null;
  batasTanggal: number;
  bulanTriwulanDibuka: string;
  tautan: Record<string, string>;
}>();

const untuk = (pola: string, id: number) => String(pola).replace('__ID__', String(id));

const angka = (n: number) => Number.isInteger(n) ? String(n) : n.toFixed(1);

const TAHAP = [
  { judul: 'Tanggung Jawab', ket: 'Kewajiban PJP atas keselamatan pekerjaannya sendiri di wilayah izin.' },
  { judul: 'Pemantauan',     ket: 'Pemeriksaan berkala atas dokumen yang masuk, ketepatan waktu, dan isinya.' },
  { judul: 'Pelaporan',      ket: 'Unggahan dokumen wajib beserta penilaian kesesuaian isinya.' },
];
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1400px] mx-auto space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-cam-orange">Aspek 2 dari 3</p>
        <h2 class="text-2xl font-extrabold tracking-tight text-stone-800">{{ props.judul }}</h2>
        <p class="text-[12px] text-stone-500 mt-1">{{ props.subjudul }}</p>
      </div>
      <Link :href="props.tautan.baru" class="eq-btn-utama px-5 !flex-none">+ Tambah PJP</Link>
    </div>

    <PjpNav :tautan="props.tautan" aktif="pelaporan" />

    <PjpBilahStatus judul="Sebaran Status Seluruh PJP" :jumlah="props.statusJumlah" :opsi="props.statusOpsi" />

    <!--
      "Belum ada laporan" bukan 0%. PJP yang baru terdaftar dan PJP yang
      setahun tidak melapor sama-sama tanpa angka bila keduanya digambar
      nol, dan yang kedua itulah yang perlu dikejar lebih dulu.
    -->
    <PjpBarCapaian
      judul="Kepatuhan Pelaporan per Perusahaan"
      kosong="Belum ada data PJP."
      label-tanpa-data="Belum ada laporan"
      :pola="props.tautan.detail"
      :items="props.pjps.map((p) => ({ id: p.id, label: p.nama_perusahaan, nilai: p.capaian }))"
    />

    <section class="grid gap-3 sm:grid-cols-3">
      <article v-for="t in TAHAP" :key="t.judul" class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
        <h3 class="font-bold text-[13px] text-stone-800">{{ t.judul }}</h3>
        <p class="mt-1 text-[11px] text-stone-500">{{ t.ket }}</p>
      </article>
    </section>

    <section v-if="props.jenisLaporan" class="rounded-2xl border border-stone-200 bg-stone-50 p-5">
      <h3 class="font-bold text-[13px] text-stone-800">Dokumen wajib dan aturan waktunya</h3>
      <ul class="mt-2 space-y-1 text-[12px] text-stone-600">
        <li v-for="(label, kunci) in props.jenisLaporan" :key="kunci" class="flex gap-2">
          <span class="text-stone-400">•</span><span>{{ label }}</span>
        </li>
      </ul>
      <p class="mt-3 text-[11px] text-stone-500">
        Semua jenis dokumen dihitung <b>tepat waktu</b> bila diunggah pada atau sebelum tanggal
        {{ props.batasTanggal }} bulan berjalan. Laporan Triwulan hanya terbuka pada bulan
        {{ props.bulanTriwulanDibuka }}. Skor kepatuhan adalah rata-rata persentase tepat waktu dan
        persentase dokumen yang dinilai sesuai — yang kedua hanya dihitung dari dokumen yang sudah dinilai.
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
            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                  :class="pjp.capaian === null ? 'bg-stone-100 text-stone-400' : 'bg-stone-100 text-stone-600'">
              {{ pjp.capaian === null ? 'Belum ada laporan' : `Kepatuhan: ${angka(pjp.capaian)}%` }}
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

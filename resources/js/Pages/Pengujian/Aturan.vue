<script setup lang="ts">
/**
 * Pengujian (metode PJ) — langkah 2: aturan, lalu mulai.
 *
 * Halaman ini ada supaya tidak ada kejutan. Aturan yang paling mudah
 * dianggap tidak adil adalah aturan yang baru diketahui setelah
 * melanggarnya: peserta yang tidak tahu bahwa soal tidak dapat diulang
 * akan menekan "berikutnya" untuk melihat-lihat, dan kehilangan satu
 * soal karenanya. Karena itu semuanya disebut di muka, dan pencacah
 * waktu baru berjalan pada tombol di bawah.
 */
import { Head, useForm, router } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
  token: string;
  company: { name: string };
  identitas: Record<string, string | null>;
  jumlahSoal: number;
  detikPerSoal: number;
}>();

const mulai = useForm({});

function jalan() {
  mulai.post(`/uji/${props.token}/jalan`);
}
</script>

<template>
  <Head title="Aturan pengujian" />

  <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-6 grid gap-5">
    <div>
      <span class="text-[10px] font-bold uppercase tracking-[0.22em] text-stone-400">
        Langkah 2 dari 2 · Aturan pengujian
      </span>
      <h1 class="text-[18px] font-bold text-cam-ink mt-1.5">Baca dulu, lalu mulai saat Anda siap</h1>
      <p class="text-[12.5px] text-stone-500 mt-1.5 leading-relaxed">
        Pencacah waktu <b>baru berjalan setelah Anda menekan tombol Mulai</b>.
        Pastikan Anda di tempat yang tenang dan sinyalnya cukup.
      </p>
    </div>

    <ul class="grid gap-2.5 text-[12.5px] text-stone-600">
      <li class="flex gap-2.5">
        <span class="text-cam-lime-deep font-bold">1.</span>
        <span><b>{{ jumlahSoal }} soal</b> pilihan ganda, diambil acak dari bank soal —
          setiap peserta mendapat susunan yang berbeda.</span>
      </li>
      <li class="flex gap-2.5">
        <span class="text-cam-lime-deep font-bold">2.</span>
        <span><b>{{ detikPerSoal }} detik</b> untuk tiap soal. Bila waktunya habis, sistem
          otomatis pindah ke soal berikutnya dan soal itu dihitung tidak dijawab.</span>
      </li>
      <li class="flex gap-2.5">
        <span class="text-cam-lime-deep font-bold">3.</span>
        <span><b>Satu soal per layar</b> dan <b>tidak dapat kembali</b> ke soal sebelumnya.
          Soal berikutnya baru disusun setelah soal ini dijawab.</span>
      </li>
      <li class="flex gap-2.5">
        <span class="text-cam-lime-deep font-bold">4.</span>
        <span>Urutan pilihan jawaban ikut diacak, jadi menyalin jawaban rekan tidak membantu.</span>
      </li>
      <li class="flex gap-2.5">
        <span class="text-cam-lime-deep font-bold">5.</span>
        <span><b>Nilai Anda tidak ditampilkan.</b> Hasil seluruh peserta dirata-ratakan menjadi
          satu nilai Pengujian (PJ) untuk {{ company.name }}.</span>
      </li>
    </ul>

    <div class="rounded-xl bg-stone-50 border border-stone-100 px-4 py-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[12px]">
      <b class="text-cam-ink">{{ identitas.nama }}</b>
      <span class="text-stone-500">{{ identitas.jabatan }}</span>
      <span class="text-stone-500">{{ identitas.dept }}</span>
      <span class="text-stone-500">{{ identitas.perusahaan }}</span>
      <button type="button" class="ml-auto text-[11.5px] font-bold text-cam-lime-deep hover:underline"
              @click="router.get(`/uji/${token}`)">
        Ubah identitas
      </button>
    </div>

    <button type="button" class="eq-btn-utama justify-self-start disabled:opacity-40" :disabled="mulai.processing"
            @click="jalan">
      Mulai pengujian — pencacah waktu berjalan ▶
    </button>
  </div>
</template>

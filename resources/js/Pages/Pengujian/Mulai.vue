<script setup lang="ts">
/**
 * Pengujian (metode PJ) — langkah 1: data diri.
 *
 * Identitasnya diminta SEBELUM pencacah waktu berjalan, dan itu bukan
 * urutan yang kebetulan. Pengujian ini dibatasi 90 detik per soal; bila
 * identitas diisi sesudah kuis dimulai, waktu mengetik nama dan memilih
 * departemen dipotong dari waktu menjawab — dan yang paling lama
 * mengetik biasanya bukan yang paling tidak tahu jawabannya.
 */
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
  token: string;
  company: { name: string };
  identitas: Record<string, any>;
  jumlahSoal: number;
  detikPerSoal: number;
  judul: string;
}>();

const isi = useForm({ nama: '', nrp: '', jabatan: '', dept: '', perusahaan: '' });

function lanjut() {
  isi.post(`/uji/${props.token}/siap`);
}
</script>

<template>
  <Head :title="judul" />

  <div class="text-center mb-6">
    <span class="text-[10px] font-bold uppercase tracking-[0.22em] text-cam-lime-deep">
      PTPKKP · Metode PJ — Pengujian
    </span>
    <h1 class="stat text-cam-ink mt-1.5">Kesadaran Risiko Keselamatan Pertambangan</h1>
    <p class="text-[13px] text-stone-500 mt-2">{{ company.name }}</p>
  </div>

  <form class="bg-white rounded-2xl shadow-card border border-stone-100 p-6 grid gap-4"
        @submit.prevent="lanjut">
    <p class="text-[12.5px] text-stone-500 leading-relaxed">
      Isi data diri Anda lebih dahulu. <b>Pencacah waktu belum berjalan</b> —
      ia baru mulai setelah Anda membaca aturan dan menekan tombol Mulai.
    </p>

    <div class="rounded-xl px-4 py-3 text-[12.5px]" style="background:#F0FDF4;color:#15803D">
      {{ jumlahSoal }} soal acak · {{ detikPerSoal }} detik per soal ·
      <b>nilai Anda tidak ditampilkan dan tidak dinilai perorangan</b>
    </div>

    <label class="text-[11.5px] font-semibold text-stone-600">
      Nama lengkap <span class="block text-cam-lime-deep">*</span>
      <input v-model="isi.nama" required autocomplete="name" placeholder="mis. Budi Santoso"
             class="mt-1 w-full rounded-xl border-stone-200 text-[13px]">
      <span v-if="isi.errors.nama" class="text-[11px] text-red-600">{{ isi.errors.nama }}</span>
    </label>

    <div class="grid gap-4 sm:grid-cols-2">
      <label class="text-[11.5px] font-semibold text-stone-600">
        Jabatan <span class="block text-cam-lime-deep">*</span>
        <select v-model="isi.jabatan" required class="mt-1 w-full rounded-xl border-stone-200 text-[13px]">
          <option value="">— pilih jabatan —</option>
          <optgroup v-for="(kel, kode) in (props.identitas?.positionGroups ?? {})" :key="kode"
                    :label="(kel as any).label">
            <option v-for="j in (kel as any).positions" :key="j" :value="j">{{ j }}</option>
          </optgroup>
        </select>
        <span v-if="isi.errors.jabatan" class="text-[11px] text-red-600">{{ isi.errors.jabatan }}</span>
      </label>

      <label class="text-[11.5px] font-semibold text-stone-600">
        Departemen <span class="block text-cam-lime-deep">*</span>
        <select v-model="isi.dept" required class="mt-1 w-full rounded-xl border-stone-200 text-[13px]">
          <option value="">— pilih —</option>
          <option v-for="d in (props.identitas?.departments ?? [])" :key="d">{{ d }}</option>
        </select>
        <span v-if="isi.errors.dept" class="text-[11px] text-red-600">{{ isi.errors.dept }}</span>
      </label>

      <label class="text-[11.5px] font-semibold text-stone-600">
        Perusahaan <span class="block text-cam-lime-deep">*</span>
        <select v-model="isi.perusahaan" required class="mt-1 w-full rounded-xl border-stone-200 text-[13px]">
          <option value="">— pilih —</option>
          <option v-for="c in (props.identitas?.companies ?? [])" :key="c">{{ c }}</option>
        </select>
        <span v-if="isi.errors.perusahaan" class="text-[11px] text-red-600">{{ isi.errors.perusahaan }}</span>
      </label>

      <label class="text-[11.5px] font-semibold text-stone-600">
        NRP / NIK <span class="block font-normal text-stone-400">— boleh dikosongkan</span>
        <input v-model="isi.nrp" class="mt-1 w-full rounded-xl border-stone-200 text-[13px]">
      </label>
    </div>

    <button class="eq-btn-utama justify-self-start disabled:opacity-40" :disabled="isi.processing">
      Lanjut ke aturan pengujian →
    </button>
  </form>
</template>

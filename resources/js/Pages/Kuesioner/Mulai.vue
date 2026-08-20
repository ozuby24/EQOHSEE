<script setup lang="ts">
/**
 * Halaman pembuka kuesioner — identitas dahulu, kuesionernya menyusul.
 *
 * ALUR DIBALIK dari yang sebelumnya. Halaman ini dulu menyodorkan dua
 * kartu dan meminta responden memilih sendiri kuesionernya. Pekerja
 * tambang berulang kali memilih "Pimpinan Unit Kerja" — bukan karena
 * lalai melainkan karena kartu pertama yang terlihat memang itu, dan
 * tidak ada apa pun di layar yang mengatakan pilihan itu keliru.
 * Jawabannya masuk sebagai persepsi pimpinan atas dirinya sendiri, dan
 * penilaian kepemimpinan tercemar tanpa satu pun tanda.
 *
 * Kini JABATAN yang menentukan. Responden mengisi identitas, dan
 * begitu jabatannya dipilih, layar langsung menyebutkan kuesioner mana
 * yang akan ia isi beserta jumlah pertanyaannya — sehingga yang keliru
 * memilih jabatan pun masih dapat melihatnya sebelum mulai.
 */
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
  token: string;
  company: { name: string };
  kategori: Record<string, { label: string; indicator: number }>;
  kelompok: Record<string, { label: string; hint: string; positions: string[] }>;
  identitas: Record<string, any>;
  jumlahButir: Record<string, number>;
}>();

const isi = ref({ nrp: '', jabatan: '', dept: '', perusahaan: '' });

/**
 * Kuesioner yang akan diisi, dihitung dari jabatannya.
 *
 * Aturannya SAMA dengan yang dipakai server — daftar resmi dulu, lalu
 * kata kunci, dan bawaannya "pekerja". Yang di layar hanya menuntun;
 * yang menentukan isi basis data tetap sisi server, sebab alamat dapat
 * disusun tangan.
 */
const kategoriTerpilih = computed<string | null>(() => {
  const j = isi.value.jabatan.trim();
  if (!j) return null;

  for (const [kode, kel] of Object.entries(props.kelompok)) {
    if ((kel.positions ?? []).some((p) => p.toLowerCase() === j.toLowerCase())) return kode;
  }

  return /(direktur|manager|manajer|superintendent|supervisor|dokter|foreman|leading hand|group leader|officer|paramedic|kepala|ktt|pjo|pengawas|head|chief|koordinator|coordinator)/i
    .test(j) ? 'pimpinan' : 'pekerja';
});

const labelTerpilih = computed(() =>
  kategoriTerpilih.value ? props.kategori[kategoriTerpilih.value]?.label : null);

const butirTerpilih = computed(() =>
  kategoriTerpilih.value ? props.jumlahButir?.[kategoriTerpilih.value] ?? null : null);

function mulai() {
  if (!kategoriTerpilih.value) return;

  router.get(`/q/${props.token}/${kategoriTerpilih.value}`, { ...isi.value });
}
</script>

<template>
  <Head title="Kuesioner Persepsi Keselamatan" />

  <div class="text-center mb-6">
    <h1 class="stat text-cam-ink">Kuesioner Persepsi Keselamatan</h1>
    <p class="text-[13px] text-stone-500 mt-2">{{ company.name }}</p>
  </div>

  <form class="bg-white rounded-2xl shadow-card border border-stone-100 p-6 grid gap-4"
        @submit.prevent="mulai">
    <p class="text-[12.5px] text-stone-500">
      Isi identitas Anda lebih dahulu. Kuesioner yang sesuai akan ditentukan
      dari jabatan yang Anda pilih.
    </p>

    <label class="text-[11.5px] font-semibold text-stone-600">
      Jabatan <span class="text-cam-lime-deep">*</span>
      <!--
        Dikelompokkan, bukan satu daftar datar: batas antara pimpinan
        unit kerja dan pekerja tambang adalah hal yang justru perlu
        terlihat oleh yang memilih.
      -->
      <select v-model="isi.jabatan" required
              class="mt-1 w-full rounded-xl border-stone-200 text-[13px]">
        <option value="">— pilih jabatan —</option>
        <optgroup v-for="(kel, kode) in props.kelompok" :key="kode" :label="kel.label">
          <option v-for="j in kel.positions" :key="j" :value="j">{{ j }}</option>
        </optgroup>
      </select>
    </label>

    <!--
      Petunjuk langsung, sebelum mulai. Yang tahu sedang mengisi tiga
      pertanyaan tidak berhenti di tengah karena mengira daftarnya
      panjang — dan yang keliru memilih jabatan masih sempat melihatnya.
    -->
    <p v-if="labelTerpilih" class="rounded-xl px-4 py-3 text-[12.5px]"
       style="background:#F0FDF4;color:#15803D">
      Kuesioner Anda: <b>{{ labelTerpilih }}</b>
      <span v-if="butirTerpilih"> · {{ butirTerpilih }} pertanyaan</span>
    </p>

    <div class="grid gap-4 sm:grid-cols-2">
      <label class="text-[11.5px] font-semibold text-stone-600">
        Departemen
        <select v-model="isi.dept" class="mt-1 w-full rounded-xl border-stone-200 text-[13px]">
          <option value="">— pilih —</option>
          <option v-for="d in (props.identitas?.departments ?? [])" :key="d">{{ d }}</option>
        </select>
      </label>

      <label class="text-[11.5px] font-semibold text-stone-600">
        Perusahaan
        <select v-model="isi.perusahaan" class="mt-1 w-full rounded-xl border-stone-200 text-[13px]">
          <option value="">— pilih —</option>
          <option v-for="c in (props.identitas?.companies ?? [])" :key="c">{{ c }}</option>
        </select>
      </label>
    </div>

    <label class="text-[11.5px] font-semibold text-stone-600">
      NRP / NIK <span class="font-normal text-stone-400">— boleh dikosongkan</span>
      <input v-model="isi.nrp" class="mt-1 w-full rounded-xl border-stone-200 text-[13px]">
    </label>

    <button class="eq-btn-utama justify-self-start" :disabled="!kategoriTerpilih">
      Mulai mengisi →
    </button>
  </form>
</template>

<script setup lang="ts">
/**
 * PTPKKP — Mitra & Akses.
 *
 * Daftar entitas tiap metode, satu per baris. Entitas menentukan kolom
 * nilai di halaman Penilaian, jadi perubahan di sini terasa di sana.
 *
 * Yang ditambahkan dibanding versi Blade: hitungan entitas berjalan
 * sambil mengetik, dan kotak yang dikosongkan menyebutkan berapa entitas
 * bawaan yang akan dipakai. Aturan "kosong berarti kembali ke bawaan"
 * sudah ada di server sejak awal, tapi sebelumnya hanya tertulis sebagai
 * keterangan — orang baru tahu hasilnya setelah menyimpan.
 */
import { computed, reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PickerTpkkp from '../../Components/PickerTpkkp.vue';
import type { HalamanRoster, MetodeRoster } from '../../types';

const props = defineProps<HalamanRoster>();

const teks = reactive<Record<string, string>>({});

function pulihkan() {
  for (const m of props.metode) {
    if (m.punyaEntitas) teks[m.kode] = m.entitas.join('\n');
  }
}

pulihkan();

const awal = ref(JSON.stringify({ ...teks }));
const kotor = computed(() => JSON.stringify({ ...teks }) !== awal.value);
const menyimpan = ref(false);

/**
 * Baris yang benar-benar menjadi entitas: dipangkas, kosong dibuang,
 * tanpa kembar.
 *
 * Ini hanya untuk hitungan di layar; yang menentukan tetap server, dan
 * setelah menyimpan prop-nya datang kembali dari sana. Kalau aturannya
 * berbeda sedikit, yang keliru cuma angka sementara sebelum disimpan.
 */
function bersih(isi: string): string[] {
  const keluar: string[] = [];

  for (const b of isi.split(/\r\n|\r|\n/)) {
    const t = b.trim();
    if (t !== '' && !keluar.includes(t)) keluar.push(t);
  }

  return keluar;
}

const jumlah = (m: MetodeRoster) => bersih(teks[m.kode] ?? '').length;

const baris = (m: MetodeRoster) =>
  Math.max(3, Math.min(10, (teks[m.kode] ?? '').split('\n').length));

function simpan() {
  menyimpan.value = true;

  router.post(`/tpkkp/roster?tahun=${props.tahun}`, { roster: { ...teks } }, {
    preserveScroll: true,
    onSuccess: () => { awal.value = JSON.stringify({ ...teks }); },
    onFinish:  () => { menyimpan.value = false; },
  });
}
</script>

<template>
  <Head title="PTPKKP — Mitra & Akses" />

  <div class="max-w-4xl mx-auto space-y-5">

    <PickerTpkkp v-bind="picker" />

    <div class="bg-white rounded-2xl border border-stone-200 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink">Mitra Kerja &amp; Entitas Penilaian</h3>
      <p class="text-[11.5px] text-stone-500 mt-1">
        Entitas menentukan kolom nilai di halaman Penilaian. Menghapus entitas tidak menghapus
        nilai yang sudah ada — nilainya hanya berhenti ikut dirata-rata.
      </p>
    </div>

    <div v-for="m in metode" :key="m.kode"
         class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
      <div class="px-5 py-3 bg-stone-50 border-b border-stone-200 flex flex-wrap items-center justify-between gap-2">
        <h4 class="text-[12.5px] font-bold text-cam-ink">{{ m.kode }} · {{ m.nama }}</h4>
        <span class="text-[11px] text-stone-500">
          {{ m.labelEntitas || 'Tanpa entitas' }}
          <template v-if="m.punyaEntitas"> · {{ jumlah(m) }} entitas</template>
        </span>
      </div>

      <p v-if="!m.punyaEntitas" class="px-5 py-4 text-[12px] text-stone-400">
        Metode ini dinilai satu nilai untuk seluruh organisasi — tidak punya entitas.
      </p>

      <div v-else class="p-5">
        <textarea v-model="teks[m.kode]" :rows="baris(m)" :disabled="!bisaSunting"
                  placeholder="Satu entitas per baris"
                  class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5
                         text-[12.5px] font-mono leading-relaxed"></textarea>

        <p v-if="jumlah(m) === 0" class="text-[10.5px] text-amber-700 font-semibold mt-1.5">
          Kosong — saat disimpan akan kembali ke {{ m.bawaan.length }} entitas bawaan instrumen.
        </p>
        <p v-else class="text-[10.5px] text-stone-400 mt-1.5">
          Satu entitas per baris. Kosongkan untuk memakai daftar bawaan instrumen.
        </p>
      </div>
    </div>

    <div v-if="bisaSunting" class="flex items-center gap-3 flex-wrap">
      <button type="button" :disabled="menyimpan || !kotor" @click="simpan"
              class="lime-gradient shadow-glow rounded-xl text-white px-6 py-2.5 text-[13px] font-bold
                     hover:brightness-105 transition disabled:opacity-40 disabled:cursor-not-allowed">
        {{ menyimpan ? 'Menyimpan…' : 'Simpan roster' }}
      </button>
      <span v-if="kotor" class="text-[11.5px] text-amber-700 font-semibold">
        Ada perubahan yang belum tersimpan.
      </span>
    </div>
  </div>
</template>

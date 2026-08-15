<script setup lang="ts">
/**
 * Hazard Report — pengingat tindak lanjut.
 *
 * Isi pesan disusun di server; halaman ini hanya menampilkannya dan
 * menyediakan jalannya ke WhatsApp atau email. Menyusun ulang teksnya di
 * sini berarti dua tempat yang harus sepakat tentang bunyi pesan resmi
 * kepada PIC.
 */
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import type { HalamanPengingat } from '../../types';

defineProps<HalamanPengingat>();

/** Perusahaan yang isi pesannya sedang dibentangkan. */
const terbentang = ref<number | null>(null);

function alih(id: number) {
  terbentang.value = terbentang.value === id ? null : id;
}
</script>

<template>
  <Head title="Pengingat Tindak Lanjut" />

  <div class="max-w-5xl mx-auto space-y-5">

    <div class="glass-light rounded-xl border border-stone-200/60 px-4 py-3">
      <p class="text-[12px] text-stone-500 leading-relaxed">
        Kirim pengingat temuan yang <b>belum ditutup</b> kepada PIC tiap perusahaan lewat
        <b>WhatsApp</b> atau <b>email</b>. Isi pesan disusun otomatis berisi daftar temuan,
        tingkat risiko, dan lokasinya. Atur kontak PIC di
        <a :href="urlPerusahaan" class="font-bold text-cam-lime-deep hover:underline">Kelola Perusahaan</a>.
      </p>
    </div>

    <div v-for="p in perusahaan" :key="p.id"
         class="bg-white rounded-2xl shadow-card border p-5"
         :class="p.tinggi ? 'border-red-200' : 'border-stone-100'">

      <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
          <h3 class="text-[15px] font-bold text-cam-ink">{{ p.nama }}</h3>

          <div class="text-[11.5px] text-stone-400 mt-1">
            PIC: {{ p.pic.nama ?? '— belum diisi —' }}
            <template v-if="p.pic.email"> · ✉ {{ p.pic.email }}</template>
            <template v-if="p.pic.telepon"> · ☎ {{ p.pic.telepon }}</template>
          </div>

          <div class="flex flex-wrap gap-2 mt-3">
            <span class="text-[11px] font-bold bg-stone-100 text-stone-600 px-2.5 py-1 rounded-full num">
              {{ p.jumlah }} belum tutup
            </span>
            <span v-if="p.tinggi" class="text-[11px] font-bold bg-red-100 text-red-700 px-2.5 py-1 rounded-full num">
              {{ p.tinggi }} risiko tinggi
            </span>
            <span v-if="p.lama" class="text-[11px] font-bold bg-amber-100 text-amber-700 px-2.5 py-1 rounded-full num">
              {{ p.lama }} lewat 14 hari
            </span>
          </div>
        </div>

        <div class="flex flex-wrap gap-2 shrink-0">
          <a :href="p.wa" target="_blank" rel="noopener"
             class="rounded-xl bg-[#25D366] text-white px-4 py-2.5 text-[12px] font-bold
                    hover:brightness-105 transition">
            WhatsApp<template v-if="!p.punyaNomor"> (pilih grup)</template>
          </a>

          <a v-if="p.mail" :href="p.mail"
             class="rounded-xl border border-stone-200 px-4 py-2.5 text-[12px] font-bold text-stone-600
                    hover:bg-stone-50 transition">Email PIC</a>
          <a v-else :href="p.urlEdit"
             class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-[12px] font-bold
                    text-amber-700 hover:bg-amber-100 transition">Isi email PIC</a>

          <a :href="p.urlLihat"
             class="rounded-xl border border-stone-200 px-4 py-2.5 text-[12px] font-bold text-stone-600
                    hover:bg-stone-50 transition">Lihat</a>
        </div>
      </div>

      <div class="mt-4 pt-3.5 border-t border-stone-100">
        <button type="button" @click="alih(p.id)"
                class="text-[12px] font-bold text-cam-lime-deep hover:underline">
          {{ terbentang === p.id ? 'Sembunyikan isi pesan' : 'Lihat isi pesan pengingat' }}
        </button>

        <pre v-if="terbentang === p.id"
             class="mt-2.5 bg-stone-50 rounded-xl p-3.5 text-[11.5px] text-stone-600
                    whitespace-pre-wrap leading-relaxed">{{ p.pesan }}</pre>
      </div>
    </div>

    <div v-if="!perusahaan.length"
         class="bg-white rounded-2xl border border-dashed border-stone-200 p-14 text-center">
      <div class="text-[36px]">✓</div>
      <p class="text-[14px] font-bold text-cam-ink mt-2">Semua temuan sudah ditutup</p>
      <p class="text-[12.5px] text-stone-400 mt-1">Tidak ada pengingat yang perlu dikirim.</p>
    </div>
  </div>
</template>

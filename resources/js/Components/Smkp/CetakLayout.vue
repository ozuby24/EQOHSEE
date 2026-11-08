<script setup lang="ts">
/**
 * Bingkai lembar cetak audit SMKP (porting `Layouts/CetakLayout.vue` Aurorasys).
 *
 * Bukan tata letak Inertia: halaman cetak memakai BlankLayout (konvensi
 * EQOHSEE untuk Pages/Print) lalu membungkus dirinya dengan komponen ini.
 *
 * Seluruh gaya lembarnya bercakupan `.smkp-cetak` (resources/css/app.css),
 * terpisah dari `.lembar` dan `@page { margin: 10mm }` milik dokumen
 * terkendali EQOHSEE lain. Lembar SMKP mengukur sendiri bantalan 14 mm-nya
 * di dalam kertas A4 tanpa margin, jadi aturan @page di bawah dipasang
 * selama halaman ini terbuka dan dicabut lagi saat ditinggalkan.
 */
import { onBeforeUnmount, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import Ikon from './Ikon.vue';

const props = defineProps<{ judul: string; kembali?: string; peringatan?: string | null; lanskap?: boolean }>();
const cetak = () => window.print();

/*
 * Kertas seluruh dokumen ditetapkan di sini — A4 potret, atau A4 lanskap
 * untuk Formulir Rencana Tindak Lanjut. Aturan bernama (@page lanskap) saja
 * tidak diikuti semua peramban saat "Simpan sebagai PDF": kertas jatuh ke
 * A4 potret atau Letter, lembar selebar 297 mm dikecilkan dan terpotong.
 * Disisipkan paling akhir di <head> supaya menang atas @page bawaan EQOHSEE.
 */
let gaya: HTMLStyleElement | null = null;
onMounted(() => {
  gaya = document.createElement('style');
  gaya.textContent = `@page { size: A4${props.lanskap ? ' landscape' : ''}; margin: 0; }`;
  document.head.appendChild(gaya);
});
onBeforeUnmount(() => gaya?.remove());
</script>

<template>
  <!-- data-tabel-tetap: tabel lembar tidak diubah menjadi kartu bertumpuk
       oleh tabelPas.ts — bentuknya harus sama dengan kertasnya. -->
  <div class="smkp-cetak min-h-screen bg-slate-200/70 print:bg-white" data-tabel-tetap>
    <div class="no-print sticky top-0 z-20 bg-navy-950 text-white">
      <div class="max-w-[900px] mx-auto flex items-center justify-between gap-3 px-4 py-3">
        <Link v-if="kembali" :href="kembali" class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-white/80 hover:text-white">
          <Ikon nama="panah-kiri" class="w-4 h-4" /> Kembali
        </Link>
        <div class="hidden sm:block min-w-0 text-center">
          <p class="text-[12.5px] text-white/60 truncate">{{ judul }}</p>
          <p class="text-[11px] text-white/40 truncate">Kertas A4 {{ lanskap ? 'lanskap' : 'potret' }} · margin: tidak ada · skala 100%</p>
        </div>
        <div class="flex items-center gap-2">
          <slot name="aksi" />
          <button type="button" class="btn-hijau btn-kecil" @click="cetak"><Ikon nama="cetak" class="w-4 h-4" /> <span>Cetak<span class="hidden sm:inline"> / Simpan</span> PDF</span></button>
        </div>
      </div>
    </div>
    <div v-if="peringatan" class="no-print max-w-[900px] mx-auto px-4 pt-5">
      <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-[12.5px] text-amber-800">{{ peringatan }}</div>
    </div>
    <div class="py-6 print:py-0 px-3 print:px-0"><slot /></div>
  </div>
</template>

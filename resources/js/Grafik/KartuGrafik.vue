<script setup lang="ts">
/**
 * Bingkai satu grafik: judul, angka pendamping, grafiknya, dan tabel.
 *
 * TABELNYA BUKAN HIASAN. Tiga warna kategori yang dipakai grafik di
 * sini kontrasnya di bawah 3:1 terhadap kartu putih — sah dipakai
 * hanya bila angkanya juga terbaca tanpa bergantung pada warna. Tabel
 * inilah yang memenuhi syarat itu, sekaligus menjadi jalan bagi
 * pembaca layar, penyalinan ke laporan, dan pencetakan hitam-putih.
 *
 * Karena itu tombolnya selalu ada dan tidak dapat dimatikan
 * pemanggilnya. Grafik yang boleh menyembunyikan tabelnya akan
 * kehilangan tabelnya pada halaman pertama yang terburu-buru.
 *
 * Saat dicetak, tabelnya selalu ikut tercetak dan grafiknya
 * disembunyikan: lembar yang keluar dari printer tidak dapat dihover.
 */
import { ref, useId } from 'vue';

withDefaults(defineProps<{
  judul: string;
  catatan?: string | null;
  /** Angka pendamping di kanan judul — total, rerata, atau periodenya. */
  angka?: string | null;
  /** Tinggi bidang gambar; grafiknya sendiri mengisi lebar. */
  tinggi?: number;
}>(), { catatan: null, angka: null, tinggi: 176 });

const tabelTampak = ref(false);
const idTabel     = useId();
</script>

<template>
  <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
    <div class="flex items-start justify-between gap-3 mb-1">
      <div class="min-w-0">
        <h3 class="text-[14px] font-bold text-cam-ink leading-snug">{{ judul }}</h3>
        <p v-if="catatan" class="text-[11.5px] text-stone-500 mt-0.5 leading-snug">{{ catatan }}</p>
      </div>

      <span v-if="angka" class="text-[11px] font-bold text-cam-lime-deep num shrink-0 mt-0.5">
        {{ angka }}
      </span>
    </div>

    <div class="mt-3 grafik-bidang" :style="{ minHeight: tinggi + 'px' }">
      <slot />
    </div>

    <div class="mt-3 pt-3 border-t border-stone-100 grafik-tanpa-cetak">
      <button type="button"
              class="text-[11px] font-semibold text-stone-500 hover:text-cam-ink
                     inline-flex items-center gap-1.5 rounded focus:outline-none
                     focus-visible:ring-2 focus-visible:ring-cam-lime focus-visible:ring-offset-2"
              :aria-expanded="tabelTampak" :aria-controls="idTabel"
              @click="tabelTampak = !tabelTampak">
        <svg class="w-3.5 h-3.5 transition-transform" :class="tabelTampak && 'rotate-90'"
             fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6" />
        </svg>
        {{ tabelTampak ? 'Sembunyikan angkanya' : 'Lihat angkanya' }}
      </button>
    </div>

    <div :id="idTabel" class="grafik-tabel" :class="!tabelTampak && 'grafik-tabel-tutup'">
      <div class="mt-3 overflow-x-auto">
        <slot name="tabel" />
      </div>
    </div>
  </section>
</template>

<style scoped>
/* Lembar cetak tidak dapat dihover dan tidak dapat diklik: grafiknya
   diganti tabelnya, bukan dicetak berdampingan dengan tombol yang
   tidak berguna di atas kertas. */
@media print {
  .grafik-bidang,
  .grafik-tanpa-cetak { display: none; }

  .grafik-tabel,
  .grafik-tabel-tutup { display: block !important; }
}
</style>

<style>
.grafik-tabel-tutup { display: none; }

.grafik-tabel table { width: 100%; border-collapse: collapse; font-size: 11.5px; }

.grafik-tabel th,
.grafik-tabel td {
  padding: 4px 8px;
  border-bottom: 1px solid #F5F5F4;
  text-align: right;
  white-space: nowrap;
}

.grafik-tabel th:first-child,
.grafik-tabel td:first-child { text-align: left; white-space: normal; }

.grafik-tabel thead th {
  color: #78716C;
  font-weight: 700;
  border-bottom-color: #E7E5E4;
  position: sticky;
  top: 0;
  background: #fff;
}

.grafik-tabel td { color: #292524; }

/* Kunci identitas di kolom pertama tabel — bentuknya mengikuti
   markanya: kotak untuk batang dan bidang, garis untuk garis. */
.grafik-kunci {
  display: inline-block;
  width: 10px; height: 10px;
  border-radius: 2px;
  margin-right: 6px;
  vertical-align: -1px;
}

.grafik-kunci-garis { height: 2px; border-radius: 1px; vertical-align: 4px; }
</style>

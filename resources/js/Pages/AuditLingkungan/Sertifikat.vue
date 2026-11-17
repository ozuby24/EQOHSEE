<script setup lang="ts">
/**
 * Lembar Sertifikat Penghargaan Kinerja Lingkungan, siap dicetak.
 *
 * Satu lembar A4 lanskap tanpa tepi. Pengaturan halamannya memakai
 * HALAMAN BERNAMA (`@page sertifikat`), bukan `@page` biasa: gaya
 * halaman yang dimuat Inertia tetap tinggal di dokumen sesudah
 * berpindah halaman, dan `@page` biasa akan ikut membuat lembar audit
 * yang dicetak sesudahnya menjadi lanskap tanpa tepi.
 */
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import SertifikatLingkungan from '../../Components/SertifikatLingkungan.vue';
import type { HalamanSertifikatLingkungan } from '../../types';

defineOptions({ layout: BlankLayout });

const props = defineProps<HalamanSertifikatLingkungan>();

const cetak = () => window.print();

const tersalin = ref(false);
async function salin() {
  try {
    await navigator.clipboard.writeText(props.s.urlVerifikasi);
    tersalin.value = true;
    setTimeout(() => { tersalin.value = false; }, 2200);
  } catch {
    window.open(props.s.urlVerifikasi, '_blank', 'noopener');
  }
}
</script>

<template>
  <Head :title="judul" />

  <div class="slc-halaman">
    <div class="slc-alat">
      <a :href="kembali" class="slc-kembali">← Kembali ke audit</a>
      <div class="slc-aksi">
        <button type="button" class="slc-tombol" @click="salin">
          {{ tersalin ? '✓ Tautan tersalin' : 'Salin tautan verifikasi' }}
        </button>
        <a :href="s.urlVerifikasi" target="_blank" rel="noopener" class="slc-tombol">Halaman verifikasi ↗</a>
        <button type="button" class="slc-tombol utama" @click="cetak">⎙ Cetak / Simpan PDF</button>
      </div>
    </div>

    <p v-if="s.status !== 'sah'" class="slc-peringatan" :class="s.status" role="alert">
      <template v-if="s.status === 'dicabut'">
        Sertifikat ini <b>DICABUT</b> sejak {{ s.dicabutPada }}<template v-if="s.alasanCabut"> — {{ s.alasanCabut }}</template>.
        Halaman verifikasinya menyatakan hal yang sama.
      </template>
      <template v-else>
        Masa berlaku sertifikat ini <b>sudah lewat</b> ({{ s.berlaku }}).
      </template>
    </p>

    <div class="slc-kertas">
      <SertifikatLingkungan :s="s" />
    </div>

    <p class="slc-catatan">
      Cetak pada kertas A4 lanskap, skala 100%, tanpa tepi (margin "None"), dengan "Background graphics" menyala.
    </p>
  </div>
</template>

<style scoped>
.slc-halaman { min-height: 100vh; background: #E7E5E4; padding: 0 16px 32px; }

.slc-alat {
  position: sticky; top: 0; z-index: 5; margin: 0 -16px 18px; padding: 10px 16px;
  display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px;
  background: rgba(255, 255, 255, .92); border-bottom: 1px solid #D6D3D1; backdrop-filter: blur(8px);
}
.slc-kembali { font-size: 12.5px; font-weight: 700; color: #57534E; }
.slc-kembali:hover { color: #DC6E00; }
.slc-aksi { display: flex; flex-wrap: wrap; gap: 8px; }
.slc-tombol {
  border: 1px solid #D6D3D1; background: #fff; color: #44403C; border-radius: .7rem;
  padding: .45rem .85rem; font-size: 12px; font-weight: 700; transition: border-color .15s, color .15s;
}
.slc-tombol:hover { border-color: #DC6E00; color: #DC6E00; }
.slc-tombol.utama { background: #0F1720; border-color: #0F1720; color: #fff; }
.slc-tombol.utama:hover { background: #1F2937; color: #fff; }

.slc-peringatan {
  max-width: 1122px; margin: 0 auto 14px; border-radius: .8rem; padding: .7rem 1rem; font-size: 12.5px;
  border: 1px solid #FECACA; background: #FEF2F2; color: #991B1B;
}
.slc-peringatan.kedaluwarsa { border-color: #FDE68A; background: #FFFBEB; color: #92400E; }

.slc-kertas { max-width: 1122px; margin: 0 auto; box-shadow: 0 18px 50px -18px rgba(15, 23, 32, .45); border-radius: 4px; overflow: hidden; }
.slc-catatan { max-width: 1122px; margin: 12px auto 0; font-size: 11px; color: #78716C; text-align: center; }

:global(:root[data-tema="gelap"] .slc-halaman) { background: #0B1215; }
:global(:root[data-tema="gelap"] .slc-alat) { background: rgba(16, 26, 30, .92); border-color: #223238; }
:global(:root[data-tema="gelap"] .slc-kembali) { color: #AEBCC2; }
:global(:root[data-tema="gelap"] .slc-tombol:not(.utama)) { background: #101A1E; border-color: #223238; color: #D5DEE1; }
:global(:root[data-tema="gelap"] .slc-peringatan) { background: rgba(239, 68, 68, .12); border-color: rgba(239, 68, 68, .3); color: #FCA5A5; }
:global(:root[data-tema="gelap"] .slc-peringatan.kedaluwarsa) { background: rgba(245, 158, 11, .12); border-color: rgba(245, 158, 11, .3); color: #FDE68A; }
:global(:root[data-tema="gelap"] .slc-catatan) { color: #96A8AF; }
</style>

<style>
@page sertifikat { size: A4 landscape; margin: 0; }

@media print {
  .slc-halaman { page: sertifikat; background: #fff !important; padding: 0 !important; min-height: 0 !important; }
  .slc-alat, .slc-catatan, .slc-peringatan { display: none !important; }
  .slc-kertas { box-shadow: none !important; border-radius: 0 !important; max-width: none !important; }
}
</style>

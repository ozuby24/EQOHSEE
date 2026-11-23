<script setup lang="ts">
/*
  Verifikasi scafftag dari QR — halaman publik untuk pekerja di kaki
  tangga. Satu pertanyaan, dijawab sebesar mungkin: LAYAK DIGUNAKAN atau
  TIDAK LAYAK DIGUNAKAN, dari tag yang berlaku saat ini (bukan kartu yang dipindai).
*/
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import { waktu } from '../../perancah';

defineOptions({ layout: PublicLayout });
const props = defineProps<{ ada: boolean; tag: any | null }>();

const boleh = computed(() => props.tag && (props.tag.warna === 'hijau' || props.tag.warna === 'kuning'));
const JUDUL: Record<string, string> = { hijau: 'LAYAK DIGUNAKAN', kuning: 'LAYAK DIGUNAKAN DENGAN SYARAT', merah: 'TIDAK LAYAK DIGUNAKAN', dibongkar: 'SUDAH DIBONGKAR' };
</script>

<template>
  <Head title="Verifikasi Scafftag" />
  <div v-if="ada && tag" class="vf" :class="`vf-${tag.warna}`">
    <div class="vf-kepala">
      <p class="vf-judul">{{ JUDUL[tag.warna] ?? 'TIDAK LAYAK DIGUNAKAN' }}</p>
      <p class="vf-label">{{ tag.label }}</p>
    </div>
    <div class="vf-badan">
      <p v-if="!tag.berlaku" class="vf-peringatan">Kartu yang Anda pindai ({{ tag.kode }}) sudah diganti. Yang berlaku: <b>{{ tag.kode_aktif ?? 'tidak ada' }}</b>.</p>
      <dl class="vf-data">
        <div><dt>Perancah</dt><dd>{{ tag.unit?.kode }} · {{ tag.unit?.judul }}</dd></div>
        <div><dt>Lokasi</dt><dd>{{ tag.unit?.zona }}</dd></div>
        <div><dt>Dimensi</dt><dd>{{ tag.unit?.dimensi }} · {{ tag.unit?.jenis }}</dd></div>
        <div v-if="boleh"><dt>SWL</dt><dd>{{ tag.swl }} kg/m²</dd></div>
        <div v-if="boleh"><dt>Berlaku s.d.</dt><dd>{{ waktu(tag.kedaluwarsa_at) }}</dd></div>
        <div><dt>Diterbitkan</dt><dd>{{ waktu(tag.terbit_at) }}</dd></div>
      </dl>
      <p v-if="tag.syarat" class="vf-kotak"><b>Syarat:</b> {{ tag.syarat }}</p>
      <p v-if="tag.alasan && !boleh" class="vf-kotak"><b>Alasan:</b> {{ tag.alasan }}</p>
      <p class="vf-kaki">Diverifikasi sistem EQOHSEE · Scaffolding Management</p>
    </div>
  </div>
  <div v-else class="bg-white rounded-2xl shadow-card p-8 text-center">
    <p class="text-[20px] font-black text-cam-ink">Tag tidak dikenal</p>
    <p class="text-[13px] text-stone-500 mt-2">QR ini tidak cocok dengan scafftag mana pun. Anggap perancah tidak layak digunakan dan hubungi pengawas area.</p>
  </div>
</template>

<style scoped>
.vf { --k: #15803D; border-radius: 22px; overflow: hidden; background: #fff; border: 3px solid var(--k); box-shadow: 0 24px 48px -28px rgba(0,0,0,.5); }
.vf-kuning { --k: #D97706; } .vf-merah { --k: #DC2626; } .vf-dibongkar { --k: #57534E; }
.vf-kepala { background: var(--k); color: #fff; padding: 26px 22px; text-align: center; }
.vf-judul { font-size: 30px; font-weight: 900; letter-spacing: .01em; line-height: 1.05; }
.vf-label { font-size: 12.5px; font-weight: 700; margin-top: 6px; opacity: .92; }
.vf-badan { padding: 18px 20px 20px; color: #1C1917; }
.vf-peringatan { padding: 10px 12px; border-radius: 12px; background: #FEF3C7; color: #78350F; font-size: 12.5px; margin-bottom: 12px; }
.vf-data { display: grid; gap: 7px; }
.vf-data div { display: grid; grid-template-columns: 96px 1fr; gap: 10px; font-size: 13px; }
.vf-data dt { color: #78716C; font-weight: 600; }
.vf-data dd { font-weight: 800; }
.vf-kotak { margin-top: 12px; padding: 10px 12px; border-radius: 12px; border: 1.5px dashed var(--k); font-size: 12.5px; }
.vf-kaki { margin-top: 14px; font-size: 11px; color: #A8A29E; text-align: center; }
</style>

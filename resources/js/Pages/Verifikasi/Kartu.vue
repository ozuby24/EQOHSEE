<script setup lang="ts">
/**
 * Verifikasi publik kartu Mine Permit / SIMPER dan sertifikat Miners —
 * halaman yang dibuka QR kartu (`/v/{kode}`), tanpa login.
 *
 * Menjawab satu pertanyaan petugas pos: "kartu ini SAH dan BERLAKU hari
 * ini?" — keadaan besar di atas (ikon + kata + warna), lalu data minimum
 * yang tersamarkan. Data diri lain sengaja tidak ditampilkan.
 */
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import PublicLayout from '../../Layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps<{ kode: string; sah: boolean; r: Record<string, any> | null; jenis: string | null; diperiksa: string }>();

const KEADAAN: Record<string, { kelas: string; ikon: string; judul: string; ket: string }> = {
  'berlaku':      { kelas: 'sah', ikon: '✓', judul: 'BERLAKU', ket: 'Tercatat dan masih berlaku pada sistem EQOHSEE.' },
  'segera-habis': { kelas: 'waspada', ikon: '!', judul: 'BERLAKU — SEGERA HABIS', ket: 'Masih berlaku, tetapi masa berlakunya kurang dari 30 hari.' },
  'habis':        { kelas: 'lewat', ikon: '×', judul: 'MASA BERLAKU HABIS', ket: 'Kartu ini pernah sah, tetapi masa berlakunya sudah lewat.' },
  'nonaktif':     { kelas: 'lewat', ikon: '×', judul: 'TIDAK AKTIF', ket: 'Kartu ini sudah dinonaktifkan dan tidak berlaku lagi.' },
  'belum-terbit': { kelas: 'lewat', ikon: '×', judul: 'BELUM DISAHKAN', ket: 'Pengajuannya belum disetujui penuh.' },
  'tidak-sah':    { kelas: 'lewat', ikon: '×', judul: 'TIDAK SAH', ket: 'Sertifikat ini tidak berlaku.' },
};

const k = computed(() => (props.r ? KEADAAN[props.r.keadaan] ?? KEADAAN['tidak-sah'] : null));
const tgl = (v?: string | null) => (v ? v.split('-').reverse().join('-') : '—');
const sisa = (n?: number | null) => (n === null || n === undefined ? '' : n >= 0 ? ` (${n} hari lagi)` : ` (lewat ${-n} hari)`);

const baris = computed(() => {
  const r = props.r;
  if (!r) return [];
  return [
    ['Nama', r.nama],
    ['Jabatan', r.jabatan],
    ['Perusahaan', r.perusahaan],
    ['NIK', r.nik],
    ['Jenis', r.jenis],
    ['Nomor', r.nomor],
    ...(r.berlaku !== undefined ? [['Berlaku sampai', tgl(r.berlaku) + sisa(r.sisa)]] : []),
    ...(r.tanggal ? [['Tanggal', tgl(r.tanggal)]] : []),
    ...(r.akses ? [['Cakupan area', r.akses]] : []),
    ...(r.mcuSampai !== undefined ? [['MCU berlaku sampai', tgl(r.mcuSampai) + sisa(r.mcuSisa)]] : []),
    ...(r.penerbit ? [['Diterbitkan', r.penerbit]] : []),
    ...(r.ktt ? [['Disahkan KTT', r.ktt]] : []),
  ].filter(([, v]) => v !== null && v !== undefined && v !== '');
});
</script>

<template>
  <Head title="Verifikasi Kartu" />
  <main class="vk">
    <h1 class="vk-judul">Verifikasi Kartu</h1>

    <section v-if="!props.sah" class="vk-keadaan lewat" role="status">
      <span class="vk-ikon" aria-hidden="true">×</span>
      <div><b>KODE TIDAK DIKENALI</b><p>Kode ini tidak sah atau kartunya tidak tercatat. Jangan izinkan masuk; hubungi HSE.</p></div>
    </section>

    <template v-else-if="props.r && k">
      <section class="vk-keadaan" :class="k.kelas" role="status">
        <span class="vk-ikon" aria-hidden="true">{{ k.ikon }}</span>
        <div><b>{{ k.judul }}</b><p>{{ k.ket }}</p></div>
      </section>

      <dl class="vk-data">
        <template v-for="[l, v] in baris" :key="l"><dt>{{ l }}</dt><dd>{{ v }}</dd></template>
      </dl>

      <section v-if="props.r.simper" class="vk-simper">
        <h2>SIMPER {{ props.r.simper.nomor }}</h2>
        <p>{{ props.r.simper.aktif ? 'Aktif' : 'Tidak aktif' }} · SIM {{ props.r.simper.jenisSim || '' }} {{ props.r.simper.sim || '' }} · berlaku sampai {{ tgl(props.r.simper.berlaku) }}</p>
        <table v-if="props.r.simper.unit.length">
          <thead><tr><th>Unit</th><th>Kelas</th></tr></thead>
          <tbody><tr v-for="(u, i) in props.r.simper.unit" :key="i"><td>{{ u.nama }}</td><td>{{ u.kelas }}</td></tr></tbody>
        </table>
        <p class="vk-kecil">Kelas: F Full · R Restricted (keadaan darurat) · P Probation · I Instructor.</p>
      </section>
    </template>

    <p class="vk-kecil">Diperiksa {{ props.diperiksa }} WITA · kode {{ props.kode }}</p>
  </main>
</template>

<style scoped>
.vk { max-width: 560px; margin: 0 auto; padding: 24px 16px 40px; font-size: 15px; color: var(--teks, #10243f); }
.vk-judul { font-size: 20px; font-weight: 700; margin-bottom: 14px; }
.vk-keadaan { display: flex; gap: 14px; align-items: center; padding: 16px; border-radius: 14px; border: 2px solid; margin-bottom: 16px; }
.vk-keadaan b { font-size: 18px; letter-spacing: .3px; }
.vk-keadaan p { margin: 4px 0 0; font-size: 14px; }
.vk-ikon { flex: none; width: 44px; height: 44px; border-radius: 50%; display: grid; place-items: center; font-size: 24px; font-weight: 700; color: #fff; }
.sah { border-color: #16a34a; background: #effcf3; color: #14532d; }
.sah .vk-ikon { background: #16a34a; }
.waspada { border-color: #d97706; background: #fffbeb; color: #78350f; }
.waspada .vk-ikon { background: #d97706; }
.lewat { border-color: #dc2626; background: #fef2f2; color: #7f1d1d; }
.lewat .vk-ikon { background: #dc2626; }
.vk-data { display: grid; grid-template-columns: max-content 1fr; gap: 8px 14px; margin: 0 0 16px; }
.vk-data dt { color: #5b6b80; }
.vk-data dd { margin: 0; font-weight: 600; overflow-wrap: anywhere; }
.vk-simper h2 { font-size: 16px; font-weight: 700; margin: 0 0 4px; }
.vk-simper table { width: 100%; border-collapse: collapse; margin-top: 8px; }
.vk-simper th, .vk-simper td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
.vk-kecil { font-size: 12px; color: #5b6b80; margin-top: 10px; }
</style>

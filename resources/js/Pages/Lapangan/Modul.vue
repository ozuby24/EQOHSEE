<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import LapanganLayout from '../../Layouts/LapanganLayout.vue';
import IkonLapangan from '../../Components/IkonLapangan.vue';
import BilahLuring from '../../Components/BilahLuring.vue';

defineOptions({ layout: LapanganLayout });

type Modul = { kunci: string; nama: string; ket: string; ikon: string | null; pilar: string; warna: string; perlu: number; url: string | null };

const p = defineProps<{
  saya: { inisial: string };
  modul: Modul[];
  pilar: Record<string, string>;
}>();

/* Warna ikon mengikuti pilar — tiga keluarga, bukan delapan warna. */
const KELUARGA: Record<string, [string, string]> = {
  safety: ['#E3F2FA', '#16729A'], quality: ['#E3F2FA', '#16729A'],
  engineering: ['#FFF0DE', '#A85400'], energy: ['#FFF0DE', '#A85400'], konservasi: ['#FFF0DE', '#A85400'],
  environment: ['#E4F6EA', '#15803D'], occhealth: ['#E4F6EA', '#15803D'], hygiene: ['#E4F6EA', '#15803D'],
};
const warna = (k: string) => KELUARGA[k] ?? ['#EEF1F4', '#3A4450'];

const saring = ref<string>('semua');
const cari = ref('');

const chip = computed(() => [
  { kunci: 'semua', nama: 'Semua', n: p.modul.length },
  ...Object.entries(p.pilar).map(([kunci, nama]) => ({ kunci, nama, n: p.modul.filter((m) => m.pilar === kunci).length })),
]);

const tampil = computed(() => {
  const q = cari.value.trim().toLowerCase();
  return p.modul.filter((m) => (saring.value === 'semua' || m.pilar === saring.value)
    && (!q || m.nama.toLowerCase().includes(q) || m.ket.toLowerCase().includes(q)));
});
</script>

<template>
  <Head title="Modul" />

  <div class="lp-atas">
    <div>
      <h1 class="lp-judul">Modul</h1>
      <div class="lp-subjudul">{{ modul.length }} modul aktif · satu akun</div>
    </div>
    <Link href="/lapangan/profil" class="lp-avatar" aria-label="Profil">{{ saya.inisial || '·' }}</Link>
  </div>
  <div style="height:12px"></div>
  <BilahLuring />

  <div class="cari">
    <IkonLapangan nama="cari" :tebal="2" />
    <input v-model="cari" type="search" placeholder="Cari modul" aria-label="Cari modul" autocomplete="off" enterkeyhint="search">
  </div>

  <div class="lp-geser saring" role="group" aria-label="Saring per pilar">
    <button v-for="c in chip" :key="c.kunci" type="button" class="lp-chip" :aria-pressed="saring === c.kunci" @click="saring = c.kunci">
      {{ c.nama }}<small>{{ c.n }}</small>
    </button>
  </div>

  <div v-if="tampil.length" class="lp-kartu lp-daftar">
    <component :is="m.url ? 'a' : 'div'" v-for="m in tampil" :key="m.kunci" :href="m.url ?? undefined" class="lp-baris modul">
      <span class="lp-petak besar" :style="{ background: warna(m.pilar)[0], color: warna(m.pilar)[1] }">
        <IkonLapangan v-if="m.ikon" :jalur="m.ikon" :tebal="1.8" /><IkonLapangan v-else nama="modul" />
      </span>
      <span class="lp-baris-isi">
        <span class="lp-baris-judul" style="display:block;font-size:14.5px">{{ m.nama }}</span>
        <span class="ket">{{ m.ket }}</span>
      </span>
      <span v-if="m.perlu > 0" class="perlu" :aria-label="`${m.perlu} perlu perhatian`">{{ m.perlu > 99 ? '99+' : m.perlu }}</span>
      <IkonLapangan v-else nama="kanan" :tebal="2.2" class="lp-baris-panah" />
    </component>
  </div>
  <div v-else class="lp-kosong"><strong>Tidak ada modul yang cocok</strong>Coba kata lain atau pilih “Semua”.</div>
</template>

<style scoped>
.cari { margin: 2px 20px 0; min-height: 46px; border-radius: 12px; background: #FFFFFF; border: 1px solid var(--garis); display: flex; align-items: center; gap: 10px; padding: 0 14px; color: var(--abu3); }
.cari:focus-within { border-color: var(--ink); box-shadow: 0 0 0 3px rgba(245, 124, 0, .28); }
.cari svg { width: 18px; height: 18px; flex: none; }
.cari input { flex: 1; min-width: 0; border: 0; background: none; font: inherit; font-size: 15px; color: var(--teks); min-height: 44px; }
.cari input:focus { outline: none; }
.saring { padding: 14px 20px 0; }
.lp-chip { min-height: 36px; padding: 0 13px; }
.modul { min-height: 62px; padding: 11px 14px; }
.lp-petak.besar { width: 40px; height: 40px; }
.lp-petak.besar svg { width: 20px; height: 20px; }
.ket { display: block; font-size: 12.5px; color: var(--abu3); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.perlu { min-width: 24px; height: 24px; padding: 0 6px; border-radius: 12px; background: var(--sinyal); color: var(--ink); font-size: 12px; font-weight: 700; display: grid; place-items: center; flex: none; }
</style>

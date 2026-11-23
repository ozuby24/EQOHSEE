<script setup lang="ts">
/*
  Daftar rencana pengangkatan dengan saringan status, klasifikasi, dan
  kata kunci. Di layar sempit tabel berubah menjadi kartu — pengawas
  lapangan membukanya dari ponsel di samping crane.
*/
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import PilihPerusahaan from '../../Components/Energi/PilihPerusahaan.vue';
import { n } from '../../energi';
import { simulasi, klas, status, kajian, waktu, STATUS } from '../../angkat';

const props = propHalaman();
const saring = ref<Record<string, string>>({ status: '', klasifikasi: '', q: '', ...(props.saring ?? {}) });
function terapkan() {
  const q = Object.fromEntries(Object.entries(saring.value).filter(([, v]) => v));
  router.get('/pengangkatan/rencana', q, { preserveState: true, preserveScroll: true });
}
function pilihStatus(s: string) { saring.value.status = saring.value.status === s ? '' : s; terapkan(); }
const hitung = computed(() => props.hitungStatus ?? {});
const rencana = computed(() => props.rencana ?? []);
</script>

<template>
  <Head title="Rencana pengangkatan" />
  <div class="space-y-4">
    <section class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex flex-wrap gap-2">
        <Link href="/pengangkatan/rencana/baru" class="eq-btn-utama inline-flex items-center gap-2"><Ikon nama="calculator" :ukuran="16" />Rencana baru</Link>
        <a href="/pengangkatan/rencana/ekspor/csv" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="download" :ukuran="16" />CSV</a>
      </div>
      <PilihPerusahaan :daftar="props.daftarPerusahaan" :terpilih="props.perusahaan" />
    </section>

    <section class="flex flex-wrap gap-2">
      <button v-for="(label, k) in STATUS" :key="k" class="df-chip" :class="{ aktif: saring.status === k }" @click="pilihStatus(String(k))">
        <i :style="{ background: label.warna }" />{{ label.pendek }} <b class="num">{{ hitung[k] ?? 0 }}</b>
      </button>
    </section>

    <Kartu judul="Rencana pengangkatan" :ket="`${rencana.length} ditampilkan`" ikon="clipboard-list" warna="biru" rapat>
      <form class="px-5 pb-3 flex flex-wrap gap-2" @submit.prevent="terapkan">
        <input v-model="saring.q" placeholder="Cari kode, pekerjaan, lokasi" aria-label="Cari rencana" class="df-i flex-1 min-w-[200px]">
        <select v-model="saring.klasifikasi" aria-label="Saring klasifikasi" class="df-i" @change="terapkan">
          <option value="">Semua klasifikasi</option><option value="rutin">Rutin</option><option value="rekayasa">Rekayasa</option><option value="kritis">Kritis</option>
        </select>
        <button class="eq-btn-lain">Cari</button>
      </form>
      <div class="overflow-x-auto hidden md:block">
        <table class="w-full text-[12.5px]">
          <thead><tr class="df-kepala"><th>Rencana</th><th>Jadwal</th><th>Crane</th><th class="text-right">Beban · radius</th><th class="text-right">Utilisasi</th><th>Simulasi</th><th>Kajian</th><th>Status</th></tr></thead>
          <tbody class="divide-y divide-stone-100">
            <tr v-for="p in rencana" :key="p.id" class="df-baris" @click="router.visit(`/pengangkatan/rencana/${p.id}`)">
              <td><p class="font-bold text-cam-ink">{{ p.kode }} · {{ p.judul }}</p><p class="text-[11px] text-stone-500">{{ p.lokasi }} · <span :style="{ color: klas(p.klasifikasi).warna }" class="font-bold">{{ klas(p.klasifikasi).pendek }}</span></p></td>
              <td class="whitespace-nowrap">{{ waktu(p.jadwal_at) }}</td>
              <td>{{ p.alat_label }}</td>
              <td class="text-right num whitespace-nowrap">{{ n(p.berat_kotor_t, 1) }} t · {{ n(p.radius_m, 0) }} m</td>
              <td class="text-right">
                <div class="df-util"><i :style="{ width: Math.min(100, p.utilisasi_pct) + '%', background: p.utilisasi_pct > 85 ? '#DC2626' : p.utilisasi_pct > 75 ? '#D97706' : '#15803D' }" /></div>
                <span class="num font-bold">{{ n(p.utilisasi_pct, 1) }}%</span>
              </td>
              <td><span class="pr-pil" :class="simulasi(p.putusan).kelas">{{ simulasi(p.putusan).pendek }}</span></td>
              <td><span v-if="p.kajian_putusan" class="pr-pil" :class="kajian(p.kajian_putusan).kelas">{{ n(p.kajian_skor, 0) }}</span></td>
              <td><span class="pr-pil" :class="status(p.status).kelas">{{ status(p.status).pendek }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
      <ul class="md:hidden divide-y divide-stone-100">
        <li v-for="p in rencana" :key="p.id">
          <Link :href="`/pengangkatan/rencana/${p.id}`" class="block px-4 py-3">
            <div class="flex items-start justify-between gap-2">
              <p class="text-[13px] font-bold text-cam-ink">{{ p.kode }} · {{ p.judul }}</p>
              <span class="pr-pil shrink-0" :class="status(p.status).kelas">{{ status(p.status).pendek }}</span>
            </div>
            <p class="text-[11.5px] text-stone-500 mt-0.5">{{ p.lokasi }} · {{ waktu(p.jadwal_at) }}</p>
            <div class="flex flex-wrap items-center gap-1.5 mt-2">
              <span class="pr-pil" :class="klas(p.klasifikasi).kelas">{{ klas(p.klasifikasi).pendek }}</span>
              <span class="pr-pil" :class="simulasi(p.putusan).kelas">{{ simulasi(p.putusan).pendek }}</span>
              <span class="text-[11.5px] font-bold num text-cam-ink">{{ n(p.berat_kotor_t, 1) }} t · {{ n(p.radius_m, 0) }} m · {{ n(p.utilisasi_pct, 1) }}%</span>
            </div>
          </Link>
        </li>
      </ul>
      <p v-if="!rencana.length" class="px-5 py-10 text-center text-[12.5px] text-stone-400">Tidak ada rencana yang cocok dengan saringan.</p>
    </Kartu>
  </div>
</template>

<style scoped>
.df-chip { display: inline-flex; align-items: center; gap: 7px; padding: 7px 12px; border-radius: 999px; border: 1px solid #E7E5E4; background: #fff; font-size: 12px; font-weight: 700; color: #44403C; }
.df-chip i { width: 8px; height: 8px; border-radius: 999px; }
.df-chip b { font-weight: 900; color: #1C1917; }
.df-chip.aktif { border-color: #1D4ED8; background: #EFF6FF; color: #1E3A8A; }
.df-i { border-radius: 10px; border-color: #E7E5E4; font-size: 12.5px; }
.df-kepala th { text-align: left; font-size: 11px; font-weight: 700; color: #78716C; padding: 8px 12px; background: #FAFAF9; }
.df-kepala th:first-child { padding-left: 20px; }
.df-baris { cursor: pointer; }
.df-baris td { padding: 10px 12px; vertical-align: middle; }
.df-baris td:first-child { padding-left: 20px; }
.df-baris:hover { background: #F8FAFC; }
.df-util { width: 70px; height: 6px; border-radius: 999px; background: #F0EFED; overflow: hidden; display: inline-block; margin-right: 8px; vertical-align: middle; }
.df-util i { display: block; height: 100%; }
:root[data-tema="gelap"] .df-chip { background: #20272B; border-color: #2E373C; color: #D5DDE1; }
:root[data-tema="gelap"] .df-chip b { color: #E8EDEF; }
:root[data-tema="gelap"] .df-chip.aktif { background: #14233A; border-color: #3B82F6; color: #BFDBFE; }
:root[data-tema="gelap"] .df-kepala th { background: #20272B; color: #93A1A8; }
:root[data-tema="gelap"] .df-baris:hover { background: #20272B; }
:root[data-tema="gelap"] .df-util { background: #2E373C; }
</style>

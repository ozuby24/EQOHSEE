<script setup lang="ts">
/**
 * Ringkasan masa berlaku satu jenis berkas, dipasang di daftarnya.
 *
 * Daftar Mine Permit menjawab "berkas apa saja yang ada". Yang
 * ditanyakan di sebelahnya selalu "lalu berapa yang bermasalah, dan
 * milik siapa" — dan menjawabnya menuntut pindah halaman. Yang berpindah
 * halaman hanya orang yang sudah tahu ada yang salah; sisanya menutup
 * daftar dengan perasaan semuanya beres.
 *
 * DIHITUNG ATAS TANGGAL EFEKTIF, bukan yang tercetak pada berkasnya.
 * Mine Permit tidak dapat hidup lebih lama daripada MCU yang
 * mendasarinya, dan SIMPER tidak lebih lama daripada SIM kepolisiannya.
 */
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps<{ pemantauan: any }>();

/* Warna pita, dipesan maknanya — sama persis dengan halaman
   pemantauan, sebab dua skala warna untuk satu makna membuat orang
   membaca kuning di sini sebagai sesuatu yang lain di sana. */
const WARNA: Record<string, { latar: string; teks: string }> = {
  habis:    { latar: '#FEE2E2', teks: '#B91C1C' },
  mendesak: { latar: '#FFEDD5', teks: '#C2410C' },
  dekat:    { latar: '#FEF9C3', teks: '#A16207' },
  panjang:  { latar: '#DCFCE7', teks: '#15803D' },
};

const r = computed<any>(() => props.pemantauan?.ringkas ?? {});
const perusahaan = computed<any[]>(() => props.pemantauan?.perPerusahaan ?? []);

/** Tautan ke halaman pemantauan, sudah tersaring pada jenis ini. */
function tautan(keadaan?: string): string {
  const q = new URLSearchParams({ jenis: props.pemantauan?.jenis ?? '' });
  if (keadaan) q.set('keadaan', keadaan);

  return `/miners/kedaluwarsa?${q}`;
}

const pita = computed(() => [
  { kunci: 'habis',    label: 'Sudah habis', nilai: r.value.perKeadaan?.habis ?? 0 },
  { kunci: 'mendesak', label: '≤ 30 hari',   nilai: r.value.perKeadaan?.mendesak ?? 0 },
  { kunci: 'dekat',    label: '31–60 hari',  nilai: r.value.perKeadaan?.dekat ?? 0 },
  { kunci: 'panjang',  label: '> 60 hari',   nilai: r.value.perKeadaan?.panjang ?? 0 },
]);
</script>

<template>
  <section v-if="props.pemantauan" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
      <div>
        <h3 class="font-bold text-[14px]">Masa berlaku &amp; tenaga kerja</h3>
        <p class="text-[11.5px] text-stone-400 mt-0.5">
          Dihitung atas tanggal yang benar-benar berlaku, bukan yang tercetak.
        </p>
      </div>

      <Link :href="tautan()" class="text-[12px] font-semibold text-cam-lime-deep py-1.5 -my-1.5">
        Buka pemantauan →
      </Link>
    </div>

    <!-- tenaga kerja: orangnya, bukan berkasnya -->
    <div class="mt-4 flex flex-wrap items-center gap-x-7 gap-y-3">
      <div>
        <p class="text-[10.5px] font-bold uppercase tracking-wide text-stone-400">Tenaga kerja</p>
        <strong class="num text-xl text-cam-ink">{{ r.manpower ?? 0 }}</strong>
      </div>
      <div>
        <p class="text-[10.5px] font-bold uppercase tracking-wide text-stone-400">Aktif</p>
        <strong class="num text-xl" style="color:#15803D">{{ r.manpowerAktif ?? 0 }}</strong>
      </div>
      <div>
        <p class="text-[10.5px] font-bold uppercase tracking-wide text-stone-400">Tidak aktif</p>
        <strong class="num text-xl" :style="{ color: r.manpowerNonaktif ? '#A16207' : '#A8A29E' }">
          {{ r.manpowerNonaktif ?? 0 }}
        </strong>
      </div>
    </div>

    <!-- pita masa berlaku; tiap pita membuka daftar yang sudah tersaring -->
    <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
      <Link v-for="p in pita" :key="p.kunci" :href="tautan(p.kunci)"
            class="rounded-xl px-3 py-2 transition hover:brightness-[.98]"
            :style="{ background: WARNA[p.kunci].latar }">
        <span class="block text-[10.5px] font-bold uppercase tracking-wide"
              :style="{ color: WARNA[p.kunci].teks }">{{ p.label }}</span>
        <strong class="num text-lg" :style="{ color: WARNA[p.kunci].teks }">{{ p.nilai }}</strong>
      </Link>
    </div>

    <!-- per perusahaan -->
    <div v-if="perusahaan.length" class="mt-4 overflow-x-auto">
      <table class="min-w-full text-left text-[12px]">
        <thead>
          <tr class="text-stone-400 border-b border-stone-100">
            <th class="py-2 pr-3 font-semibold">Perusahaan</th>
            <th class="py-2 pr-3 font-semibold text-right">Tenaga kerja</th>
            <th class="py-2 pr-3 font-semibold text-right">Tidak aktif</th>
            <th class="py-2 pr-3 font-semibold text-right">Berlaku</th>
            <th class="py-2 pr-3 font-semibold text-right">Mendekati</th>
            <th class="py-2 font-semibold text-right">Habis</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in perusahaan" :key="c.perusahaan" class="border-b border-stone-50">
            <td class="py-2 pr-3 font-semibold text-cam-ink">{{ c.perusahaan }}</td>
            <td class="py-2 pr-3 num text-right">{{ c.manpower }}</td>
            <td class="py-2 pr-3 num text-right"
                :style="{ color: c.manpowerNonaktif ? '#A16207' : '#A8A29E' }">
              {{ c.manpowerNonaktif || '—' }}
            </td>
            <td class="py-2 pr-3 num text-right" style="color:#15803D">{{ c.aktif }}</td>
            <td class="py-2 pr-3 num text-right" style="color:#A16207">{{ c.mendekati || '—' }}</td>
            <td class="py-2 num text-right font-bold"
                :style="{ color: c.habis ? '#B91C1C' : '#A8A29E' }">{{ c.habis || '—' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

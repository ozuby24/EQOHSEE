<script setup lang="ts">
/**
 * Panel zona masa berlaku — ringkasan yang sekaligus penyaring.
 *
 * Mengikuti bentuk D'Best: satu deret kartu zona di atas daftarnya, dan
 * MENGKLIKNYA MENYARING daftar di bawah. Angka yang tidak dapat diklik
 * memaksa orang membaca "3 sudah habis" lalu mencari sendiri yang mana
 * di antara dua ratus baris — dua langkah untuk satu pertanyaan, tiap
 * pagi, oleh tiap orang.
 *
 * "SEMUA DATA" DAN "BELUM DIISI" IKUT DISEBUT. Tanpa "semua data" tidak
 * ada jalan kembali selain menghapus alamatnya sendiri; tanpa "belum
 * diisi" baris yang tanggalnya kosong hilang dari kelima zona lain dan
 * tidak pernah muncul di mana pun — justru baris yang paling perlu
 * diurus, sebab tidak ada yang tahu kapan ia habis.
 */
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps<{
  /** { total, expired, kritis, waspada, aman, kosong } */
  ringkas: Record<string, number>;
  /** { total, aktif, nonaktif } — jumlah ORANG, bukan berkas. */
  manpower?: Record<string, number> | null;
  /** Zona yang sedang dipakai menyaring. */
  zona?: string | null;
  judul?: string;
  /** Alamat halaman ini, untuk menyusun tautan saring. */
  rute: string;
}>();

const ZONA = [
  { kode: 'expired', pendek: 'Expired',      label: 'Lewat masa berlaku',   latar: '#FEE2E2', teks: '#B91C1C' },
  { kode: 'kritis',  pendek: '≤ 30 hari',    label: 'Kurang dari 30 hari',  latar: '#FFEDD5', teks: '#C2410C' },
  { kode: 'waspada', pendek: '31–60 hari',   label: '31 sampai 60 hari',    latar: '#FEF9C3', teks: '#A16207' },
  { kode: 'aman',    pendek: '> 60 hari',    label: 'Lebih dari 60 hari',   latar: '#DCFCE7', teks: '#15803D' },
  { kode: 'kosong',  pendek: 'Belum diisi',  label: 'Tanggal belum diisi',  latar: '#F5F5F4', teks: '#57534E' },
];

const aktif = computed(() => props.zona ?? '');

/** Mengklik zona yang sedang aktif melepasnya — tidak perlu tombol khusus. */
function saring(kode: string) {
  const q: Record<string, string> = {};
  const url = new URL(window.location.href);

  url.searchParams.forEach((v, k) => { if (k !== 'zona' && k !== 'page' && v) q[k] = v; });
  if (kode && aktif.value !== kode) q.zona = kode;

  router.get(props.rute, q, { preserveScroll: true, preserveState: true });
}
</script>

<template>
  <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
      <h3 class="font-bold text-[14px]">
        {{ props.judul ?? 'Masa berlaku' }}
        <span class="font-normal text-[11.5px] text-stone-400">| klik kartu untuk menyaring</span>
      </h3>

      <!-- Jumlah ORANG, bukan berkas: satu orang memegang beberapa. -->
      <p v-if="props.manpower" class="text-[12px] text-stone-500 flex flex-wrap gap-x-3">
        <span><b class="num text-cam-ink">{{ props.manpower.total }}</b> manpower</span>
        <span style="color:#15803D"><b class="num">{{ props.manpower.aktif }}</b> aktif</span>
        <span class="text-stone-400"><b class="num">{{ props.manpower.nonaktif }}</b> tidak aktif</span>
      </p>
    </div>

    <div class="mt-3 grid gap-2 grid-cols-2 sm:grid-cols-3 lg:grid-cols-6">
      <button type="button"
              class="rounded-xl border px-3 py-2.5 text-left transition hover:brightness-[.98]"
              :style="{ background: '#FFFFFF',
                        borderColor: aktif === '' ? '#0F1720' : '#E7E5E4' }"
              @click="saring('')">
        <span class="num block text-xl font-bold text-cam-ink">{{ props.ringkas?.total ?? 0 }}</span>
        <span class="block text-[11px] font-semibold text-stone-500">Semua data</span>
      </button>

      <button v-for="z in ZONA" :key="z.kode" type="button"
              class="rounded-xl border px-3 py-2.5 text-left transition hover:brightness-[.98]"
              :style="{ background: z.latar,
                        borderColor: aktif === z.kode ? z.teks : 'transparent' }"
              @click="saring(z.kode)">
        <span class="num block text-xl font-bold" :style="{ color: z.teks }">
          {{ props.ringkas?.[z.kode] ?? 0 }}
        </span>
        <span class="block text-[11px] font-semibold" :style="{ color: z.teks }">{{ z.pendek }}</span>
        <span class="block text-[10px]" :style="{ color: z.teks, opacity: .75 }">{{ z.label }}</span>
      </button>
    </div>
  </section>
</template>

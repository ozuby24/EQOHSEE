<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PjpStatusStackedBar from '../../Components/PjpStatusStackedBar.vue';

/*
  Lokasi acuan cuaca (Kalimantan Tengah, area pertambangan) — belum ada
  data lokasi per-perusahaan yang bisa dipakai di sini, jadi dipatok satu
  titik yang representatif daripada tidak menampilkan apa pun.
*/
const LOKASI_LABEL = 'Kalimantan Tengah';
const LOKASI = { lat: -2.21, lon: 113.92 };
const ZONA_WAKTU = 'Asia/Makassar';

const WMO_CUACA: Record<number, { label: string; ikon: 'cerah' | 'berawan' | 'hujan' | 'petir' | 'kabut' }> = {
  0: { label: 'Cerah', ikon: 'cerah' },
  1: { label: 'Cerah Berawan', ikon: 'cerah' },
  2: { label: 'Berawan', ikon: 'berawan' },
  3: { label: 'Mendung', ikon: 'berawan' },
  45: { label: 'Berkabut', ikon: 'kabut' },
  48: { label: 'Berkabut', ikon: 'kabut' },
  51: { label: 'Gerimis', ikon: 'hujan' },
  53: { label: 'Gerimis', ikon: 'hujan' },
  55: { label: 'Gerimis Lebat', ikon: 'hujan' },
  61: { label: 'Hujan Ringan', ikon: 'hujan' },
  63: { label: 'Hujan', ikon: 'hujan' },
  65: { label: 'Hujan Lebat', ikon: 'hujan' },
  80: { label: 'Hujan Ringan', ikon: 'hujan' },
  81: { label: 'Hujan', ikon: 'hujan' },
  82: { label: 'Hujan Lebat', ikon: 'hujan' },
  95: { label: 'Badai Petir', ikon: 'petir' },
  96: { label: 'Badai Petir', ikon: 'petir' },
  99: { label: 'Badai Petir', ikon: 'petir' },
};

const jamSekarang = ref('--:--');
const tanggalSekarang = ref('');
const cuaca = ref<{ suhu: number; hujan: number; label: string; ikon: string } | null>(null);
const cuacaGagal = ref(false);

let timer: ReturnType<typeof setInterval> | undefined;

function perbaruiJam() {
  const now = new Date();
  jamSekarang.value = now.toLocaleTimeString('id-ID', { timeZone: ZONA_WAKTU, hour: '2-digit', minute: '2-digit' });
  tanggalSekarang.value = now.toLocaleDateString('id-ID', { timeZone: ZONA_WAKTU, weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
}

onMounted(() => {
  perbaruiJam();
  timer = setInterval(perbaruiJam, 15000);

  fetch(`https://api.open-meteo.com/v1/forecast?latitude=${LOKASI.lat}&longitude=${LOKASI.lon}&current=temperature_2m,precipitation,weather_code&timezone=${encodeURIComponent(ZONA_WAKTU)}`)
    .then((r) => { if (!r.ok) throw new Error('gagal'); return r.json(); })
    .then((data) => {
      const kode = data?.current?.weather_code as number | undefined;
      const info = WMO_CUACA[kode ?? -1] ?? { label: 'Tidak diketahui', ikon: 'berawan' };
      cuaca.value = {
        suhu: Math.round(data?.current?.temperature_2m ?? 0),
        hujan: data?.current?.precipitation ?? 0,
        label: info.label,
        ikon: info.ikon,
      };
    })
    .catch(() => { cuacaGagal.value = true; });
});

onUnmounted(() => { if (timer) clearInterval(timer); });

interface MiniPjp { id: number; nama_perusahaan: string }
interface PerluPerhatian { id: number; nama_perusahaan: string; achievement: number }

defineProps<{
  stats: { total: number; aktifDipantau: number; perluTindakLanjut: number; tidakAktif: number };
  statusCounts: Record<string, number>;
  pjpBelumLaporanBulanan: MiniPjp[];
  perluPerhatian: PerluPerhatian[];
}>();

function achievementColor(v: number): string {
  if (v >= 60) return 'text-amber-800 bg-amber-100';
  if (v >= 40) return 'text-orange-800 bg-orange-100';
  return 'text-red-800 bg-red-100';
}

const kartu = [
  { key: 'total', label: 'Total PJP Terdaftar', href: '/pjp', gradient: 'from-orange-500 to-cam-ink' },
  { key: 'aktifDipantau', label: 'Aktif Dipantau', href: '/pjp?status=aktif', gradient: 'from-emerald-600 to-emerald-800' },
  { key: 'perluTindakLanjut', label: 'Perlu Tindak Lanjut', href: '/pjp?status=perlu_tindak_lanjut', gradient: 'from-amber-500 to-orange-600' },
  { key: 'tidakAktif', label: 'Tidak Aktif', href: '/pjp?status=tidak_aktif', gradient: 'from-stone-500 to-stone-700' },
] as const;

const tahapan = [
  { href: '/pjp-tahapan/persyaratan', title: 'Persyaratan, Seleksi, dan Penetapan',
    description: 'Proses awal penilaian persyaratan, seleksi, hingga penetapan PJP.' },
  { href: '/pjp-tahapan/pelaporan', title: 'Tanggung Jawab, Pemantauan, dan Pelaporan',
    description: 'Pengelolaan tanggung jawab, pemantauan berkala, dan pelaporan kinerja PJP.' },
  { href: '/pjp-tahapan/evaluasi', title: 'Evaluasi',
    description: 'Evaluasi menyeluruh terhadap kinerja dan kepatuhan PJP sebagai dasar tindak lanjut.' },
];
</script>

<template>
  <Head title="Beranda PJP" />

  <div class="max-w-5xl mx-auto">
    <div class="relative overflow-hidden rounded-2xl mb-6 min-h-[190px] flex flex-col justify-end p-5"
         style="background:linear-gradient(0deg,rgba(11,17,23,.92) 20%,rgba(11,17,23,.45) 65%,rgba(11,17,23,.15) 100%),
                url('/media/hero/tambang.jpg') center/cover no-repeat">
      <p class="text-[10.5px] font-bold uppercase tracking-[0.14em] text-cam-lime-light flex items-center gap-2">
        <span class="w-4 h-[2px] bg-cam-lime-light rounded-full"></span>
        Perusahaan Jasa Pertambangan
      </p>
      <h2 class="font-serif text-2xl font-extrabold text-white mt-1">Beranda Pemantauan PJP</h2>
      <p class="text-[12.5px] text-white/75 mt-1 max-w-[46ch]">
        Memantau dan mengelola PJP di seluruh tahapan pengelolaannya.
      </p>

      <div class="flex flex-wrap items-center gap-2.5 mt-4">
        <div class="rounded-xl bg-black/35 border border-white/10 px-3.5 py-2 backdrop-blur-sm">
          <p class="text-[15px] font-extrabold text-white leading-none">{{ jamSekarang }} <span class="text-[10px] font-bold text-white/60">WITA</span></p>
          <p class="text-[10.5px] text-white/65 mt-1">{{ tanggalSekarang }}</p>
        </div>
        <div v-if="cuaca" class="rounded-xl bg-black/35 border border-white/10 px-3.5 py-2 backdrop-blur-sm flex items-center gap-2.5">
          <svg class="w-6 h-6 text-amber-300 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <circle v-if="cuaca.ikon === 'cerah'" cx="12" cy="12" r="4.2"/>
            <path v-if="cuaca.ikon === 'cerah'" d="M12 2.5v2.2M12 19.3v2.2M4.2 4.2l1.6 1.6M18.2 18.2l1.6 1.6M2.5 12h2.2M19.3 12h2.2M4.2 19.8l1.6-1.6M18.2 5.8l1.6-1.6"/>
            <path v-else d="M7 17.5a4 4 0 0 1-.5-7.97 5.5 5.5 0 0 1 10.6-2.03A4.5 4.5 0 0 1 17 17.5Z"/>
          </svg>
          <div>
            <p class="text-[13px] font-extrabold text-white leading-none">{{ cuaca.suhu }}°C <span class="text-[10.5px] font-semibold text-amber-300">{{ cuaca.label }}</span></p>
            <p class="text-[10.5px] text-white/65 mt-1">{{ cuaca.hujan }} mm · {{ LOKASI_LABEL }}</p>
          </div>
        </div>
        <div v-else-if="!cuacaGagal" class="rounded-xl bg-black/35 border border-white/10 px-3.5 py-2 text-[11px] text-white/60">Memuat cuaca…</div>
        <Link href="/pjp" class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5 text-[12.5px] font-bold ml-auto">Lihat Data PJP →</Link>
      </div>
    </div>

    <div v-if="pjpBelumLaporanBulanan.length" class="rounded-2xl border border-amber-200 bg-amber-50 p-5 mb-6">
      <p class="text-[13px] font-bold text-amber-900">
        {{ pjpBelumLaporanBulanan.length }} PJP belum/terlambat mengirim Laporan Bulanan bulan ini
      </p>
      <ul class="flex flex-wrap gap-2 mt-2">
        <li v-for="pjp in pjpBelumLaporanBulanan" :key="pjp.id">
          <Link :href="`/pjp/${pjp.id}`" class="rounded-full bg-white px-3 py-1 text-[12px] font-semibold text-amber-800 hover:bg-amber-100">
            {{ pjp.nama_perusahaan }}
          </Link>
        </li>
      </ul>
    </div>

    <div v-if="perluPerhatian.length" class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 mb-6">
      <p class="text-[13px] font-bold text-cam-ink mb-2">PJP Paling Perlu Perhatian</p>
      <ul class="divide-y divide-stone-100">
        <li v-for="pjp in perluPerhatian" :key="pjp.id">
          <Link :href="`/pjp/${pjp.id}`" class="flex items-center justify-between gap-3 py-2.5 text-[12.5px] hover:bg-stone-50 transition -mx-1 px-1 rounded-lg">
            <span class="font-semibold text-cam-ink">{{ pjp.nama_perusahaan }}</span>
            <span class="rounded-full px-2 py-0.5 text-[11px] font-bold" :class="achievementColor(pjp.achievement)">{{ pjp.achievement }}%</span>
          </Link>
        </li>
      </ul>
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <Link v-for="k in kartu" :key="k.key" :href="k.href"
            class="relative overflow-hidden rounded-2xl p-5 text-white shadow-lg bg-gradient-to-br transition-transform hover:-translate-y-1"
            :class="k.gradient">
        <p class="text-2xl font-extrabold">{{ (stats as any)[k.key] }}</p>
        <p class="text-[12px] text-white/90 mt-0.5">{{ k.label }}</p>
        <p class="text-[11px] text-white/80 mt-4">Lihat data →</p>
      </Link>
    </div>

    <div v-if="stats.total === 0" class="bg-white rounded-2xl border border-dashed border-stone-200 p-8 text-center text-[13px] text-stone-400 mb-8">
      Belum ada data PJP. Tambahkan data untuk mulai memantau dan mengelola PJP.
    </div>
    <div v-else class="mb-8"><PjpStatusStackedBar title="Capaian Status Seluruh PJP" :counts="statusCounts" /></div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <Link v-for="t in tahapan" :key="t.href" :href="t.href"
            class="bg-white rounded-2xl shadow-card border border-stone-100 p-6 flex flex-col hover:-translate-y-1 hover:shadow-lg transition">
        <h3 class="font-bold text-[14px] text-cam-ink">{{ t.title }}</h3>
        <p class="text-[12px] text-stone-500 mt-2 flex-1">{{ t.description }}</p>
        <span class="text-[12px] font-bold text-cam-lime-deep mt-4">Lihat detail →</span>
      </Link>
    </div>
  </div>
</template>

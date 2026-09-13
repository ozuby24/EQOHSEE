<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PjpLencanaStatus from '../../Components/PjpLencanaStatus.vue';
import PjpNav from '../../Components/PjpNav.vue';
import PjpUnggahLaporan from '../../Components/PjpUnggahLaporan.vue';

const props = defineProps<{
  pjp: Record<string, any>;
  kelompokLaporan: Array<{
    jenis: string; label: string; terkunci: string | null;
    berkas: Array<Record<string, any>>;
  }>;
  evaluasis: Array<Record<string, any>>;
  smkpScore: { total_bobot: number; total_skor: number; persentase: number; kategori_risiko: string };
  legalitasStatus: { total: number; lengkap: number };
  pelaporanScore: number | null;
  triwulanTerbuka: boolean;
  bulanTriwulanDibuka: string;
  kesesuaianOpsi: Record<string, string>;
  semesterOpsi: Record<string, string>;
  tahunSekarang: number;
  tautan: Record<string, string>;
}>();

const untuk = (pola: string, id: number) => String(pola).replace('__ID__', String(id));

const angka = (n: number | null) => n === null ? '—' : (Number.isInteger(n) ? String(n) : n.toFixed(1));

/** Pita warna yang sama dipakai grafik capaian di halaman aspek. */
function warnaSkor(n: number | null): string {
  if (n === null) return 'text-stone-300';
  if (n >= 80) return 'text-emerald-600';
  if (n >= 60) return 'text-amber-600';
  if (n >= 40) return 'text-orange-600';
  return 'text-red-600';
}

const evaluasiTerakhir = computed(() => props.evaluasis[0] ?? null);

/* ---------- evaluasi kinerja ---------- */

const evaluasi = useForm({
  tahun: props.tahunSekarang,
  semester: 1,
  skor_teknis: '',
  skor_keselamatan_kesehatan: '',
  skor_lingkungan: '',
  catatan: '',
});

function simpanEvaluasi() {
  evaluasi.post(props.tautan.evaluasiSimpan, {
    preserveScroll: true,
    onSuccess: () => evaluasi.reset('skor_teknis', 'skor_keselamatan_kesehatan', 'skor_lingkungan', 'catatan'),
  });
}

function hapusEvaluasi(e: Record<string, any>) {
  if (!window.confirm(`Hapus evaluasi ${e.semesterLabel} ${e.tahun}? Tindakan ini tidak dapat dibatalkan.`)) return;

  router.delete(untuk(props.tautan.evaluasiHapus, e.id), { preserveScroll: true });
}

/* ---------- hapus PJP ---------- */

const sedangHapus = ref(false);

function hapusPjp() {
  if (!window.confirm(
    `Hapus data PJP “${props.pjp.nama_perusahaan}”? Seluruh dokumen, checklist, dan evaluasinya ikut terhapus. `
    + 'Tindakan ini tidak dapat dibatalkan.'
  )) return;

  sedangHapus.value = true;
  router.delete(props.tautan.hapus, { onFinish: () => { sedangHapus.value = false; } });
}

/** Tren rata-rata antar semester — hanya berarti bila ada dua titik atau lebih. */
const tren = computed(() => {
  const urut = [...props.evaluasis].reverse();
  if (urut.length < 2) return null;

  const L = 280, T = 64, PX = 10, PY = 10;
  const langkah = (L - PX * 2) / (urut.length - 1);

  const titik = urut.map((e, i) => ({
    x: PX + i * langkah,
    y: PY + (1 - e.skor_rata_rata / 100) * (T - PY * 2),
    e,
  }));

  return {
    L, T,
    jalur: titik.map((p) => `${p.x},${p.y}`).join(' '),
    titik,
    awal: urut[0],
    akhir: urut[urut.length - 1],
  };
});
</script>

<template>
  <Head :title="props.pjp.nama_perusahaan" />

  <div class="max-w-[1100px] mx-auto space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-cam-orange">Perusahaan Jasa Pertambangan</p>
        <h2 class="text-2xl font-extrabold tracking-tight text-stone-800">{{ props.pjp.nama_perusahaan }}</h2>
        <p class="text-[12px] text-stone-500 mt-1">
          Dipantau lewat checklist persyaratan, kepatuhan pelaporan, dan evaluasi kinerja.
        </p>
      </div>
      <!--
        Tombol menumpuk penuh pada layar sempit. Dipaksa sebaris, keempat
        tombol ini meluber keluar viewport ~390px tanpa menimbulkan galat
        apa pun — hanya teks yang terpotong di tepi kanan.
      -->
      <div class="flex w-full flex-wrap gap-2 sm:w-auto">
        <Link :href="props.tautan.checklist" class="eq-btn-lain px-4 !flex-none">Checklist Persyaratan</Link>
        <a :href="props.tautan.cetak" class="eq-btn-lain px-4 !flex-none">Cetak</a>
        <Link :href="props.tautan.ubah" class="eq-btn-lain px-4 !flex-none">Ubah</Link>
        <button type="button" :disabled="sedangHapus"
                class="eq-btn-lain px-4 !flex-none !text-red-600 disabled:opacity-40" @click="hapusPjp">Hapus</button>
      </div>
    </div>

    <PjpNav :tautan="props.tautan" aktif="daftar" />

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-[12px]">
        <PjpLencanaStatus :status="props.pjp.status" :label="props.pjp.statusLabel" />
        <span v-if="props.pjp.nib" class="text-stone-600">NIB: <b>{{ props.pjp.nib }}</b></span>
        <span v-if="props.pjp.penanggung_jawab" class="text-stone-600">
          Penanggung jawab: <b>{{ props.pjp.penanggung_jawab }}</b>
        </span>
        <span v-if="props.pjp.perusahaan" class="text-stone-500">Dipantau: {{ props.pjp.perusahaan }}</span>
      </div>

      <p v-if="props.pjp.alamat" class="mt-2 text-[11px] text-stone-500 whitespace-pre-line">{{ props.pjp.alamat }}</p>

      <div class="mt-5 grid grid-cols-2 gap-3 border-t border-stone-100 pt-5 sm:grid-cols-4">
        <div>
          <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">Persyaratan PJP</p>
          <p class="mt-1 text-xl font-extrabold" :class="warnaSkor(props.smkpScore.persentase)">
            {{ angka(props.smkpScore.persentase) }}%
          </p>
          <p class="text-[10px] text-stone-400">
            {{ angka(props.smkpScore.total_skor) }} / {{ props.smkpScore.total_bobot }} · layak risiko
            {{ props.smkpScore.kategori_risiko }}
          </p>
        </div>

        <div>
          <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">Dokumen Legalitas</p>
          <p class="mt-1 text-xl font-extrabold"
             :class="props.legalitasStatus.lengkap === props.legalitasStatus.total ? 'text-emerald-600' : 'text-amber-600'">
            {{ props.legalitasStatus.lengkap }}/{{ props.legalitasStatus.total }}
          </p>
          <p class="text-[10px] text-stone-400">Syarat wajib, di luar skor</p>
        </div>

        <div>
          <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">Kepatuhan Pelaporan</p>
          <p class="mt-1 text-xl font-extrabold" :class="warnaSkor(props.pelaporanScore)">
            {{ props.pelaporanScore === null ? '—' : `${angka(props.pelaporanScore)}%` }}
          </p>
          <p class="text-[10px] text-stone-400">
            {{ props.pelaporanScore === null ? 'Belum ada laporan' : 'Tepat waktu & kesesuaian isi' }}
          </p>
        </div>

        <div>
          <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">Evaluasi Terakhir</p>
          <p class="mt-1 text-xl font-extrabold"
             :class="warnaSkor(evaluasiTerakhir ? evaluasiTerakhir.skor_rata_rata : null)">
            {{ evaluasiTerakhir ? angka(evaluasiTerakhir.skor_rata_rata) : '—' }}
          </p>
          <p class="text-[10px] text-stone-400">
            {{ evaluasiTerakhir ? `${evaluasiTerakhir.semesterLabel} ${evaluasiTerakhir.tahun}` : 'Belum dievaluasi' }}
          </p>
        </div>
      </div>
    </section>

    <section v-if="props.pjp.catatan" class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
      <h3 class="text-[12px] font-bold text-amber-800">Catatan</h3>
      <p class="mt-1 text-[12px] text-amber-700 whitespace-pre-line">{{ props.pjp.catatan }}</p>
    </section>

    <section class="space-y-3">
      <h3 class="text-[15px] font-extrabold text-stone-800">Dokumen &amp; Laporan</h3>

      <div class="grid gap-4 lg:grid-cols-2">
        <PjpUnggahLaporan
          v-for="kelompok in props.kelompokLaporan"
          :key="kelompok.jenis"
          :jenis="kelompok.jenis"
          :label="kelompok.label"
          :laporans="kelompok.berkas"
          :kesesuaian-opsi="props.kesesuaianOpsi"
          :simpan="props.tautan.laporanSimpan"
          :nilai-pola="props.tautan.laporanNilai"
          :hapus-pola="props.tautan.laporanHapus"
          :terkunci="kelompok.terkunci"
        />
      </div>
    </section>

    <section class="space-y-3">
      <h3 class="text-[15px] font-extrabold text-stone-800">Evaluasi Kinerja per Semester</h3>

      <div class="grid gap-4 xl:grid-cols-[1fr_1fr]">
        <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
          <h4 class="font-bold text-[13px] text-stone-800">Riwayat penilaian</h4>
          <p class="text-[11px] text-stone-400 mt-0.5">
            Tiga aspek 0–100; rata-ratanya dihitung otomatis.
          </p>

          <div v-if="tren" class="mt-4 rounded-xl bg-stone-50 p-3">
            <p class="text-[11px] font-semibold text-stone-500">Tren skor rata-rata antar semester</p>
            <svg :viewBox="`0 0 ${tren.L} ${tren.T}`" class="w-full mt-1" style="max-width:320px">
              <polyline :points="tren.jalur" fill="none" stroke="#2a78d6" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round" />
              <circle v-for="(p, i) in tren.titik" :key="i" :cx="p.x" :cy="p.y"
                      :r="i === tren.titik.length - 1 ? 4 : 3" fill="#2a78d6" stroke="#ffffff" stroke-width="2">
                <title>{{ p.e.semesterLabel }} {{ p.e.tahun }}: {{ p.e.skor_rata_rata }}</title>
              </circle>
            </svg>
            <div class="mt-1 flex justify-between text-[10px] text-stone-400">
              <span>S{{ tren.awal.semester }} {{ tren.awal.tahun }}</span>
              <span>S{{ tren.akhir.semester }} {{ tren.akhir.tahun }}</span>
            </div>
          </div>

          <ul v-if="props.evaluasis.length" class="mt-4 divide-y divide-stone-100">
            <li v-for="e in props.evaluasis" :key="e.id" class="py-3 text-[12px]">
              <div class="flex items-center justify-between gap-3">
                <span class="font-semibold text-stone-700">{{ e.semesterLabel }} {{ e.tahun }}</span>
                <button type="button" class="text-[11px] font-bold text-red-600" @click="hapusEvaluasi(e)">Hapus</button>
              </div>
              <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-stone-500">
                <span>Teknis: {{ e.skor_teknis }}</span>
                <span>K3: {{ e.skor_keselamatan_kesehatan }}</span>
                <span>Lingkungan: {{ e.skor_lingkungan }}</span>
                <span class="font-bold" :class="warnaSkor(e.skor_rata_rata)">Rata-rata: {{ angka(e.skor_rata_rata) }}</span>
              </div>
              <p v-if="e.catatan" class="mt-1 text-[11px] italic text-stone-400">{{ e.catatan }}</p>
            </li>
          </ul>

          <p v-else class="mt-6 text-center text-[12px] text-stone-400">Belum ada data evaluasi.</p>
        </div>

        <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
          <h4 class="font-bold text-[13px] text-stone-800">Catat evaluasi</h4>
          <!--
            Satu baris per (tahun, semester). Mengisi ulang semester yang
            sama menimpa nilainya — karena itu tidak ada tombol "ubah"
            terpisah, dan formulir ini memang satu-satunya jalan masuk.
          -->
          <p class="text-[11px] text-stone-400 mt-0.5">
            Mengisi semester yang sudah ada akan menimpa nilainya, bukan menambah baris baru.
          </p>

          <form class="mt-4 grid gap-3" @submit.prevent="simpanEvaluasi">
            <div class="grid grid-cols-2 gap-2">
              <select v-model.number="evaluasi.semester" class="rounded-lg border-stone-200 text-[12px]">
                <option v-for="(label, nilai) in props.semesterOpsi" :key="nilai" :value="Number(nilai)">{{ label }}</option>
              </select>
              <input v-model.number="evaluasi.tahun" type="number" min="2000" max="2100"
                     class="rounded-lg border-stone-200 text-[12px]">
            </div>

            <div class="grid grid-cols-3 gap-2">
              <input v-model="evaluasi.skor_teknis" type="number" min="0" max="100" required
                     placeholder="Teknis" class="rounded-lg border-stone-200 text-[12px]">
              <input v-model="evaluasi.skor_keselamatan_kesehatan" type="number" min="0" max="100" required
                     placeholder="K3" class="rounded-lg border-stone-200 text-[12px]">
              <input v-model="evaluasi.skor_lingkungan" type="number" min="0" max="100" required
                     placeholder="Lingkungan" class="rounded-lg border-stone-200 text-[12px]">
            </div>

            <textarea v-model="evaluasi.catatan" rows="2" placeholder="Catatan (opsional)"
                      class="rounded-lg border-stone-200 text-[12px]"></textarea>

            <p v-if="evaluasi.errors.skor_teknis || evaluasi.errors.skor_keselamatan_kesehatan || evaluasi.errors.skor_lingkungan"
               class="text-[11px] text-red-600">Ketiga skor wajib diisi dengan angka 0–100.</p>
            <p v-if="evaluasi.errors.tahun" class="text-[11px] text-red-600">{{ evaluasi.errors.tahun }}</p>

            <button :disabled="evaluasi.processing" class="eq-btn-utama">
              {{ evaluasi.processing ? 'Menyimpan…' : 'Simpan evaluasi' }}
            </button>
          </form>
        </div>
      </div>
    </section>
  </div>
</template>

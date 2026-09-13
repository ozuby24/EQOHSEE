<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PjpBilahStatus from '../../Components/PjpBilahStatus.vue';

const props = defineProps<{
  judul: string;
  subjudul: string;
  ringkas: Record<string, number>;
  statusJumlah: Record<string, number>;
  statusOpsi: Record<string, string>;
  belumLaporan: Array<{ id: number; nama_perusahaan: string }>;
  perluPerhatian: Array<{ id: number; nama_perusahaan: string; achievement: number }>;
  ambangPerhatian: number;
  batasTanggal: number;
  tautan: Record<string, string>;
}>();

const untuk = (pola: string, id: number) => String(pola).replace('__ID__', String(id));

const angka = (n: number) => Number.isInteger(n) ? String(n) : n.toFixed(1);

/** Pita yang sama dengan PjpBarCapaian — satu arti, satu warna. */
function warnaCapaian(nilai: number): string {
  if (nilai >= 60) return 'bg-amber-100 text-amber-800';
  if (nilai >= 40) return 'bg-orange-100 text-orange-800';
  return 'bg-red-100 text-red-800';
}

/*
 * Keempat kartu digambar, termasuk "Tidak Aktif". Menyembunyikan status
 * yang tidak menuntut tindakan membuat Total tidak sama dengan jumlah
 * kartu yang terlihat, dan selisih itu terbaca sebagai kesalahan
 * hitung — bukan sebagai status yang sengaja tidak ditampilkan.
 */
const kartu = [
  { kunci: 'total', label: 'Total PJP Terdaftar', warna: 'text-cam-ink',      sisip: '' },
  { kunci: 'aktif', label: 'Aktif Dipantau',      warna: 'text-emerald-600',  sisip: 'aktif' },
  { kunci: 'perluTindakLanjut', label: 'Perlu Tindak Lanjut', warna: 'text-amber-600', sisip: 'perlu_tindak_lanjut' },
  { kunci: 'tidakAktif', label: 'Tidak Aktif',    warna: 'text-stone-500',    sisip: 'tidak_aktif' },
];

const aspek = [
  {
    tautan: props.tautan.persyaratan,
    judul: 'Persyaratan, Seleksi, dan Penetapan',
    ket: 'Prakualifikasi lewat checklist SMKP berbobot: 17 kategori, 126 pertanyaan, dan skor kepatuhan per perusahaan.',
  },
  {
    tautan: props.tautan.pelaporan,
    judul: 'Tanggung Jawab, Pemantauan, dan Pelaporan',
    ket: 'Dokumen SPIP, TSP, Laporan Bulanan, dan Laporan Triwulan beserta ketepatan waktu dan kesesuaian isinya.',
  },
  {
    tautan: props.tautan.evaluasi,
    judul: 'Evaluasi Kinerja',
    ket: 'Penilaian per semester pada aspek teknis, keselamatan dan kesehatan, serta lingkungan.',
  },
];
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1400px] mx-auto space-y-5">
    <!-- Judul dan subjudulnya digambar kop kerangka.

         PjpNav juga tidak dipasang DI SINI. Kerangka sudah menggambar
         bilah pindahnya sendiri pada halaman awal tiap modul — dari
         grup menu yang sama — sehingga memasang PjpNav di halaman ini
         menaruh dua baris pil berisi tautan yang sama persis, bertumpuk
         satu di atas yang lain. Di halaman modul lainnya kerangka tidak
         menggambarnya, dan di sanalah PjpNav tetap terpasang. -->
    <div class="flex flex-wrap items-end justify-end gap-4">
      <Link :href="props.tautan.baru" class="eq-btn-utama px-5 !flex-none">+ Tambah PJP</Link>
    </div>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <Link
        v-for="k in kartu"
        :key="k.kunci"
        :href="k.sisip ? `${props.tautan.daftar}?status=${k.sisip}` : props.tautan.daftar"
        class="rounded-2xl bg-white border border-stone-100 shadow-card p-4 transition hover:border-stone-200"
      >
        <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">{{ k.label }}</p>
        <p class="mt-2 text-2xl font-extrabold" :class="k.warna">{{ props.ringkas[k.kunci] ?? 0 }}</p>
      </Link>
    </section>

    <!--
      Tunggakan laporan bulan berjalan. Kosong sebelum tanggal batas
      terlewati — bukan karena datanya belum ada, melainkan karena
      sebelum tanggal itu memang belum ada yang terlambat.
    -->
    <section v-if="props.belumLaporan.length"
             class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
      <p class="text-[12px] font-bold text-amber-800">
        {{ props.belumLaporan.length }} PJP belum atau terlambat mengirim Laporan Bulanan bulan ini
      </p>
      <p class="text-[11px] text-amber-700 mt-0.5">
        Batas pengiriman tanggal {{ props.batasTanggal }} setiap bulan.
      </p>
      <ul class="mt-3 flex flex-wrap gap-2">
        <li v-for="pjp in props.belumLaporan" :key="pjp.id">
          <Link :href="untuk(props.tautan.detail, pjp.id)"
                class="rounded-full bg-white px-3 py-1 text-[11px] font-semibold text-amber-800 hover:bg-amber-100">
            {{ pjp.nama_perusahaan }}
          </Link>
        </li>
      </ul>
    </section>

    <section class="grid gap-5 xl:grid-cols-[1.1fr_.9fr]">
      <PjpBilahStatus judul="Sebaran Status Seluruh PJP" :jumlah="props.statusJumlah" :opsi="props.statusOpsi" />

      <!--
        Angka pada kartu ini adalah skor TERENDAH di antara ketiga aspek
        yang datanya ada, bukan rata-ratanya. Rata-rata akan menutupi
        satu aspek yang buruk dengan dua aspek yang baik — persis
        keadaan yang seharusnya memanggil orang ke halaman ini.
      -->
      <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="font-bold text-[14px] text-stone-800">PJP Paling Perlu Perhatian</h3>
        <p class="text-[11px] text-stone-400 mt-0.5">
          Skor terendah di antara persyaratan, pelaporan, dan evaluasi — di bawah {{ props.ambangPerhatian }}.
        </p>

        <ul v-if="props.perluPerhatian.length" class="mt-3 divide-y divide-stone-100">
          <li v-for="pjp in props.perluPerhatian" :key="pjp.id">
            <Link :href="untuk(props.tautan.detail, pjp.id)"
                  class="flex items-center justify-between gap-3 py-2.5 text-[12px] hover:bg-stone-50">
              <span class="font-semibold text-stone-700">{{ pjp.nama_perusahaan }}</span>
              <span class="rounded-full px-2 py-0.5 text-[11px] font-bold" :class="warnaCapaian(pjp.achievement)">
                {{ angka(pjp.achievement) }}%
              </span>
            </Link>
          </li>
        </ul>

        <p v-else class="mt-6 text-center text-[12px] text-stone-400">
          Tidak ada PJP di bawah ambang perhatian.
        </p>
      </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <Link v-for="a in aspek" :key="a.judul" :href="a.tautan"
            class="group rounded-2xl bg-white border border-stone-100 shadow-card p-5 flex flex-col transition hover:border-cam-orange/40">
        <h3 class="font-bold text-[14px] text-stone-800 group-hover:text-cam-orange">{{ a.judul }}</h3>
        <p class="mt-2 flex-1 text-[12px] text-stone-500">{{ a.ket }}</p>
        <span class="mt-4 text-[11px] font-bold text-cam-orange">Lihat capaian →</span>
      </Link>
    </section>
  </div>
</template>

<script setup lang="ts">
/**
 * Akar modul HRIS.
 *
 * SATU LAYAR, SATU PERTANYAAN: siapa yang seharusnya di site hari
 * ini, apakah mereka benar-benar ada, dan apa yang menghalangi.
 * Jawabannya tinggal di tiga tabel yang berbeda, dan sebelum layar
 * ini yang menanyakannya harus membuka tiga layar lalu mencocokkan
 * angkanya sendiri di kepala.
 *
 * TIAP ANGKA DIPASANGKAN DENGAN APA YANG SEHARUSNYA. "Dua ratus orang
 * hadir" tidak menuntut tindakan apa pun; "empat hari kerja terjadwal
 * yang orangnya tidak datang" menuntutnya pagi itu juga.
 */
import { Head, Link } from '@inertiajs/vue3';
import { computed, h } from 'vue';
import { propHalaman } from '../../halaman';
import KartuGrafik from '../../Grafik/KartuGrafik.vue';
import Batang from '../../Grafik/Batang.vue';
import UbinAngka from '../../Components/UbinAngka.vue';
import KutipanKaki from '../../Components/KutipanKaki.vue';

/**
 * Ikon ubin, digambar sebagai path inline.
 *
 * Bukan dari pustaka ikon: repo ini sudah sekali melepas pustaka dari
 * CDN, dan ikon yang gagal dimuat pada jaringan site tambang
 * meninggalkan kotak kosong di tempat angka yang seharusnya terbaca.
 */
const IKON: Record<string, string[]> = {
  orang:  ['M16 20v-1.5a4 4 0 0 0-8 0V20', 'M12 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z'],
  siang:  ['M12 17.5a5.5 5.5 0 1 0 0-11 5.5 5.5 0 0 0 0 11Z', 'M12 2v2M12 20v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M2 12h2M20 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4'],
  malam:  ['M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5Z'],
  libur:  ['M4 9.5h16v9a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 18.5Z', 'M9 9.5V7a3 3 0 0 1 6 0v2.5'],
  berkas: ['M7 3.5h7L18 8v12.5H7a1 1 0 0 1-1-1v-15a1 1 0 0 1 1-1Z', 'M14 3.5V8h4', 'M9 13h6M9 16.5h4'],
  hadir:  ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'm8.5 12.2 2.4 2.4 4.6-4.9'],
  telat:  ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'M12 7.5v5l3.2 1.9'],
  belum:  ['M3.5 12h6l2-3 2.5 6 2-3h4.5'],
  absen:  ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'm9 9 6 6M15 9l-6 6'],
  luar:   ['M16 20v-1.5a4 4 0 0 0-8 0V20', 'M12 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z', 'M19 7.5 21 5M21 9.5 19 12'],
  area:   ['M12 21s7-5.4 7-11a7 7 0 1 0-14 0c0 5.6 7 11 7 11Z', 'M12 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z'],
  mesin:  ['M5.5 4.5h13a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-13a1 1 0 0 1-1-1v-13a1 1 0 0 1 1-1Z', 'M8.5 9h7M8.5 12.5h7M8.5 16h4'],
  token:  ['M15 8.5a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0Z', 'M11.5 12v7.5l2 1.5 2-2-1.5-1.5 1.5-1.5-2-2'],
  diam:   ['M12 9.4v4.2', 'M12 17h.01', 'M10.4 4.1 2.6 17.8a1.8 1.8 0 0 0 1.6 2.7h15.6a1.8 1.8 0 0 0 1.6-2.7L13.6 4.1a1.8 1.8 0 0 0-3.2 0Z'],
  pindai: ['M4 8V5.5A1.5 1.5 0 0 1 5.5 4H8M16 4h2.5A1.5 1.5 0 0 1 20 5.5V8M20 16v2.5a1.5 1.5 0 0 1-1.5 1.5H16M8 20H5.5A1.5 1.5 0 0 1 4 18.5V16', 'M4 12h16'],
};

const props = propHalaman();

/** Pembungkus kecil supaya tiap ubin cukup menyebut nama ikonnya. */
const Ikon = (p: { nama: string }) => h('svg', {
  viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 1.9,
  'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'aria-hidden': 'true',
}, (IKON[p.nama] ?? []).map((d) => h('path', { d })));

const jadwal  = computed<any>(() => props.jadwal ?? {});
const hadir   = computed<any>(() => props.hadir ?? {});
const kemarin = computed<any>(() => props.kemarin ?? {});
const fatigue = computed<any>(() => props.fatigue ?? {});
const mesin   = computed<any>(() => props.mesin ?? {});

/**
 * Kehadiran kemarin sebagai batang — kemarin, bukan hari ini.
 *
 * Shift hari ini belum selesai, sehingga angkanya belum dapat
 * dibandingkan dengan apa pun: separuh site masih tercatat "belum tap
 * pulang" sampai sore. Digambar sebagai grafik, keadaan setengah jadi
 * itu terbaca seperti kesimpulan.
 */
const batang = computed(() => ([
  { label: 'Hadir',            nilai: kemarin.value.hadir ?? 0,        keadaan: 'baik' as const },
  { label: 'Terlambat',        nilai: kemarin.value.terlambat ?? 0,    keadaan: 'ingat' as const },
  { label: 'Belum tap pulang', nilai: kemarin.value.belum_pulang ?? 0, keadaan: 'serius' as const },
  { label: 'Absen',            nilai: kemarin.value.absen ?? 0,        keadaan: 'gawat' as const },
  { label: 'Di luar roster',   nilai: kemarin.value.luar_roster ?? 0,  keadaan: 'netral' as const },
]).filter((b) => b.nilai > 0));

/** Yang menuntut tindakan hari ini, dihitung sebagai satu angka. */
const perluDitindak = computed(() =>
  (jadwal.value.terhalang ?? 0) + (fatigue.value.jumlah ?? 0)
  + (hadir.value.absen ?? 0) + (mesin.value.diam ?? 0));
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1200px] mx-auto space-y-5">

    <p v-if="perluDitindak === 0"
       class="rounded-2xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-[12.5px] text-emerald-800">
      Tidak ada yang menuntut tindakan hari ini: tidak ada hari kerja yang terhalang berkas,
      tidak ada pelanggaran batas jam kerja pada {{ props.JENDELA }} hari ke depan, tidak ada
      yang tercatat absen, dan seluruh alat pindai masih menyapa.
    </p>

    <p v-else class="rounded-2xl bg-amber-50 border border-amber-200 px-4 py-3 text-[12.5px] text-amber-800">
      <span class="num font-bold">{{ perluDitindak }}</span> hal menuntut tindakan hari ini —
      dirinci di bawah.
    </p>

    <!-- ══════════ hari ini menurut roster ══════════ -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <header class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
        <h3 class="text-[13.5px] font-bold text-cam-ink">Dijadwalkan hari ini</h3>
        <span class="num text-[11px] text-stone-400">{{ props.tanggal }}</span>
      </header>

      <div class="grid gap-2.5 grid-cols-2 sm:grid-cols-3 lg:grid-cols-5">
        <UbinAngka :angka="jadwal.kerja ?? 0" label="Total pekerja" nada="serius">
          <template #ikon><Ikon nama="orang" /></template>
        </UbinAngka>
        <UbinAngka :angka="jadwal.siang ?? 0" label="Shift siang" nada="ingat" :dari="jadwal.kerja ?? 0">
          <template #ikon><Ikon nama="siang" /></template>
        </UbinAngka>
        <UbinAngka :angka="jadwal.malam ?? 0" label="Shift malam" nada="luar" :dari="jadwal.kerja ?? 0">
          <template #ikon><Ikon nama="malam" /></template>
        </UbinAngka>
        <UbinAngka :angka="jadwal.libur ?? 0" label="Libur / off-site" nada="netral">
          <template #ikon><Ikon nama="libur" /></template>
        </UbinAngka>
        <UbinAngka :angka="jadwal.terhalang ?? 0" label="Terhalang berkas"
                   :nada="(jadwal.terhalang ?? 0) > 0 ? 'gawat' : 'baik'" :dari="jadwal.kerja ?? 0">
          <template #ikon><Ikon nama="berkas" /></template>
        </UbinAngka>
      </div>

      <p v-if="(jadwal.terhalang ?? 0) > 0" class="mt-3 text-[11.5px] text-red-700">
        {{ jadwal.terhalang }} hari kerja terjadwal hari ini dipegang orang yang MCU, induksi,
        atau Mine Permit-nya tidak berlaku.
        <Link href="/hris/roster" class="underline">Lihat kalender regu</Link>
      </p>
    </section>

    <!-- ══════════ kehadiran ══════════ -->
    <section class="grid gap-4 lg:grid-cols-2">
      <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <header class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
          <h3 class="text-[13.5px] font-bold text-cam-ink">Kehadiran hari ini</h3>
          <span class="text-[11px] text-stone-400">shift berjalan</span>
        </header>

        <div class="grid gap-2.5 grid-cols-2 sm:grid-cols-3">
          <UbinAngka :angka="hadir.hadir ?? 0" label="Hadir" nada="baik" :dari="jadwal.kerja ?? 0">
            <template #ikon><Ikon nama="hadir" /></template>
          </UbinAngka>
          <UbinAngka :angka="hadir.terlambat ?? 0" label="Terlambat" nada="ingat" :dari="jadwal.kerja ?? 0">
            <template #ikon><Ikon nama="telat" /></template>
          </UbinAngka>
          <UbinAngka :angka="hadir.belum_pulang ?? 0" label="Belum tap pulang" nada="serius" :dari="jadwal.kerja ?? 0">
            <template #ikon><Ikon nama="belum" /></template>
          </UbinAngka>
          <UbinAngka :angka="hadir.absen ?? 0" label="Absen"
                     :nada="(hadir.absen ?? 0) > 0 ? 'gawat' : 'netral'" :dari="jadwal.kerja ?? 0">
            <template #ikon><Ikon nama="absen" /></template>
          </UbinAngka>
          <UbinAngka :angka="hadir.luar_roster ?? 0" label="Di luar roster" nada="luar">
            <template #ikon><Ikon nama="luar" /></template>
          </UbinAngka>
          <UbinAngka :angka="hadir.luar_area ?? 0" label="Di luar geofence"
                     :nada="(hadir.luar_area ?? 0) > 0 ? 'gawat' : 'netral'">
            <template #ikon><Ikon nama="area" /></template>
          </UbinAngka>
        </div>

        <p class="mt-3 text-[11.5px] text-stone-500">
          <span class="num font-semibold text-cam-ink">{{ hadir.jam ?? 0 }}</span> jam tercatat
          hari ini. Shift yang belum selesai belum dinyatakan absen — ketidakhadiran adalah
          kesimpulan, dan kesimpulan itu tidak dapat diambil sebelum shiftnya berakhir.
        </p>
      </div>

      <KartuGrafik judul="Kehadiran kemarin"
                   catatan="Kemarin, bukan hari ini: shift hari ini belum selesai, sehingga separuh site masih tercatat &quot;belum tap pulang&quot; sampai sore — keadaan setengah jadi yang terbaca seperti kesimpulan bila digambar."
                   :angka="`${kemarin.jam ?? 0} jam · ${kemarin.telat ?? 0} menit telat`">
        <Batang v-if="batang.length" :baris="batang" satuan=" orang" apa-adanya />

        <p v-else class="py-8 text-center text-[12px] text-stone-400">
          Belum ada catatan absensi pada {{ kemarin.tanggal }}.
        </p>

        <template #tabel>
          <table>
            <thead>
              <tr><th>Keadaan</th><th>Orang</th></tr>
            </thead>
            <tbody>
              <tr v-for="b in batang" :key="b.label">
                <td>{{ b.label }}</td>
                <td class="num">{{ b.nilai }}</td>
              </tr>
            </tbody>
          </table>
        </template>
      </KartuGrafik>
    </section>

    <!-- ══════════ batas jam kerja ══════════ -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100 flex flex-wrap items-baseline justify-between gap-2">
        <h3 class="text-[13.5px] font-bold text-cam-ink">
          Batas jam kerja
          <span class="font-normal text-stone-400">| {{ props.JENDELA }} hari ke depan</span>
        </h3>
        <span class="num text-[11px]" :class="(fatigue.jumlah ?? 0) > 0 ? 'text-red-600' : 'text-emerald-700'">
          {{ fatigue.jumlah ?? 0 }} pelanggaran
        </span>
      </header>

      <div v-if="!(fatigue.daftar ?? []).length" class="px-5 py-8 text-center text-[12px] text-stone-400">
        Tidak ada roster tersusun yang melampaui batas Kepmenakertrans 234/2003.
      </div>

      <table v-else class="min-w-full text-left text-[11.5px]">
        <thead>
          <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
            <th class="px-5 py-2 font-semibold">Pekerja</th>
            <th class="px-5 py-2 font-semibold">Pelanggaran</th>
            <th class="px-5 py-2 font-semibold">Tanggal</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(t, i) in (fatigue.daftar ?? [])" :key="i" class="border-b border-stone-100">
            <td class="px-5 py-2.5 font-semibold text-cam-ink">{{ t.pekerja || '—' }}</td>
            <td class="px-5 py-2.5 text-red-700">{{ t.label }}</td>
            <td class="px-5 py-2.5 num text-stone-500">{{ t.tanggal }}</td>
          </tr>
        </tbody>
      </table>

      <p v-if="(fatigue.jumlah ?? 0) > (fatigue.daftar ?? []).length"
         class="px-5 py-3 text-[11.5px] text-stone-500 border-t border-stone-100">
        {{ fatigue.jumlah - (fatigue.daftar ?? []).length }} pelanggaran lagi tidak ditampilkan.
        <Link href="/hris/roster" class="underline">Buka kalender regu</Link>
      </p>
    </section>

    <!-- ══════════ alat lapangan ══════════ -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <header class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
        <h3 class="text-[13.5px] font-bold text-cam-ink">Alat pindai lapangan</h3>
        <Link href="/hris/absensi/mesin" class="text-[11px] text-sky-700 hover:underline">Kelola mesin</Link>
      </header>

      <div class="grid gap-2.5 grid-cols-2 sm:grid-cols-4">
        <UbinAngka :angka="mesin.aktif ?? 0" label="Mesin aktif" nada="netral">
          <template #ikon><Ikon nama="mesin" /></template>
        </UbinAngka>
        <UbinAngka :angka="mesin.bertoken ?? 0" label="Sudah bertoken"
                   :nada="(mesin.aktif ?? 0) > (mesin.bertoken ?? 0) ? 'ingat' : 'baik'"
                   :dari="mesin.aktif ?? 0">
          <template #ikon><Ikon nama="token" /></template>
        </UbinAngka>
        <UbinAngka :angka="mesin.diam ?? 0" :label="`Diam > ${props.DIAM_JAM} jam`"
                   :nada="(mesin.diam ?? 0) > 0 ? 'gawat' : 'baik'" :dari="mesin.aktif ?? 0">
          <template #ikon><Ikon nama="diam" /></template>
        </UbinAngka>
        <UbinAngka :angka="mesin.jejak24 ?? 0" label="Pindaian 24 jam" nada="netral">
          <template #ikon><Ikon nama="pindai" /></template>
        </UbinAngka>
      </div>

      <p v-if="(mesin.nama ?? []).length" class="mt-3 text-[11.5px] text-red-700">
        Berhenti menyapa:
        <span v-for="(m, i) in (mesin.nama ?? [])" :key="i">
          <span v-if="i"> · </span>{{ m.nama }}
          <span class="text-stone-400">({{ m.terakhir || 'belum pernah' }})</span>
        </span>
      </p>

      <p class="mt-2 text-[11.5px] text-stone-500">
        Alat pos jaga yang mati menghasilkan layar absensi yang tampak baik-baik saja: seluruh
        regunya tercatat absen, dan angka itu terbaca sebagai mangkir massal, bukan sebagai alat
        rusak. Satu-satunya tanda bahwa yang rusak adalah alatnya ada di sini.
      </p>
    </section>

    <KutipanKaki teks="Orang yang tepat, di tempat yang tepat, pada waktu yang tepat."
                 kanan="People Drive&#10;Progress" />
  </div>
</template>

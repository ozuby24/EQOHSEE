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
import { computed } from 'vue';
import { propHalaman } from '../../halaman';
import KartuGrafik from '../../Grafik/KartuGrafik.vue';
import Batang from '../../Grafik/Batang.vue';

const props = propHalaman();

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
    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-cam-ink">{{ props.judul }}</h2>
        <p class="text-[12.5px] text-stone-500 mt-0.5">{{ props.subjudul }}</p>
      </div>

      <div class="flex flex-wrap gap-2">
        <Link href="/hris/roster" class="eq-btn-lain">Kalender regu</Link>
        <Link href="/hris/absensi" class="eq-btn-lain">Pemantauan harian</Link>
      </div>
    </section>

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

      <div class="grid gap-2 grid-cols-2 sm:grid-cols-3 lg:grid-cols-5">
        <div class="hr-kartu hr-netral">
          <div class="num text-[19px] font-bold leading-none">{{ jadwal.kerja ?? 0 }}</div>
          <div class="text-[10.5px] mt-1">Kerja</div>
        </div>
        <div class="hr-kartu hr-netral">
          <div class="num text-[19px] font-bold leading-none">{{ jadwal.siang ?? 0 }}</div>
          <div class="text-[10.5px] mt-1">Shift siang</div>
        </div>
        <div class="hr-kartu hr-netral">
          <div class="num text-[19px] font-bold leading-none">{{ jadwal.malam ?? 0 }}</div>
          <div class="text-[10.5px] mt-1">Shift malam</div>
        </div>
        <div class="hr-kartu hr-netral">
          <div class="num text-[19px] font-bold leading-none">{{ jadwal.libur ?? 0 }}</div>
          <div class="text-[10.5px] mt-1">Libur / off-site</div>
        </div>
        <div class="hr-kartu" :class="(jadwal.terhalang ?? 0) > 0 ? 'hr-gawat' : 'hr-baik'">
          <div class="num text-[19px] font-bold leading-none">{{ jadwal.terhalang ?? 0 }}</div>
          <div class="text-[10.5px] mt-1">Terhalang berkas</div>
        </div>
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

        <div class="grid gap-2 grid-cols-2 sm:grid-cols-3">
          <div class="hr-kartu hr-baik">
            <div class="num text-[19px] font-bold leading-none">{{ hadir.hadir ?? 0 }}</div>
            <div class="text-[10.5px] mt-1">Hadir</div>
          </div>
          <div class="hr-kartu hr-ingat">
            <div class="num text-[19px] font-bold leading-none">{{ hadir.terlambat ?? 0 }}</div>
            <div class="text-[10.5px] mt-1">Terlambat</div>
          </div>
          <div class="hr-kartu hr-serius">
            <div class="num text-[19px] font-bold leading-none">{{ hadir.belum_pulang ?? 0 }}</div>
            <div class="text-[10.5px] mt-1">Belum tap pulang</div>
          </div>
          <div class="hr-kartu" :class="(hadir.absen ?? 0) > 0 ? 'hr-gawat' : 'hr-netral'">
            <div class="num text-[19px] font-bold leading-none">{{ hadir.absen ?? 0 }}</div>
            <div class="text-[10.5px] mt-1">Absen</div>
          </div>
          <div class="hr-kartu hr-luar">
            <div class="num text-[19px] font-bold leading-none">{{ hadir.luar_roster ?? 0 }}</div>
            <div class="text-[10.5px] mt-1">Di luar roster</div>
          </div>
          <div class="hr-kartu" :class="(hadir.luar_area ?? 0) > 0 ? 'hr-gawat' : 'hr-netral'">
            <div class="num text-[19px] font-bold leading-none">{{ hadir.luar_area ?? 0 }}</div>
            <div class="text-[10.5px] mt-1">Di luar geofence</div>
          </div>
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

      <div class="grid gap-2 grid-cols-2 sm:grid-cols-4">
        <div class="hr-kartu hr-netral">
          <div class="num text-[19px] font-bold leading-none">{{ mesin.aktif ?? 0 }}</div>
          <div class="text-[10.5px] mt-1">Mesin aktif</div>
        </div>
        <div class="hr-kartu" :class="(mesin.aktif ?? 0) > (mesin.bertoken ?? 0) ? 'hr-ingat' : 'hr-baik'">
          <div class="num text-[19px] font-bold leading-none">{{ mesin.bertoken ?? 0 }}</div>
          <div class="text-[10.5px] mt-1">Sudah bertoken</div>
        </div>
        <div class="hr-kartu" :class="(mesin.diam ?? 0) > 0 ? 'hr-gawat' : 'hr-baik'">
          <div class="num text-[19px] font-bold leading-none">{{ mesin.diam ?? 0 }}</div>
          <div class="text-[10.5px] mt-1">Diam &gt; {{ props.DIAM_JAM }} jam</div>
        </div>
        <div class="hr-kartu hr-netral">
          <div class="num text-[19px] font-bold leading-none">{{ mesin.jejak24 ?? 0 }}</div>
          <div class="text-[10.5px] mt-1">Pindaian 24 jam</div>
        </div>
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
  </div>
</template>

<style>
/**
 * Kartu angka, sadar tema.
 *
 * Ditulis sebagai kelas dan bukan gaya sebaris supaya aturan mode
 * gelap dapat menimpanya. Nilai gelapnya dipilih agar tetap terbaca
 * pada latar #0D1417 tanpa menyilaukan.
 */
.hr-kartu { border-radius: 0.75rem; padding: 0.7rem 0.8rem; }

.hr-netral { background: #F5F5F4; color: #44403C; }
.hr-baik   { background: #D1FAE5; color: #065F46; }
.hr-ingat  { background: #FEF3C7; color: #78350F; }
.hr-serius { background: #DBEAFE; color: #1E3A5F; }
.hr-gawat  { background: #FEE2E2; color: #7F1D1D; }
.hr-luar   { background: #EDE9FE; color: #4C1D95; }

:root[data-tema="gelap"] .hr-netral { background: #1C262B; color: #C7D0D5; }
:root[data-tema="gelap"] .hr-baik   { background: #143A2C; color: #8FE3BE; }
:root[data-tema="gelap"] .hr-ingat  { background: #4A3810; color: #F6D488; }
:root[data-tema="gelap"] .hr-serius { background: #1E3F5E; color: #A8CDF0; }
:root[data-tema="gelap"] .hr-gawat  { background: #4E1D1D; color: #F5A9A9; }
:root[data-tema="gelap"] .hr-luar   { background: #34255E; color: #C8B6F5; }
</style>

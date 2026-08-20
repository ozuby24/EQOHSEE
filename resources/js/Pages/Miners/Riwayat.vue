<script setup lang="ts">
/**
 * Riwayat satu tahap, menyilang seluruh pekerja.
 *
 * Sebelumnya induksi, Mine Permit, Mine License, dan kompetensi hanya
 * dapat dilihat dengan membuka orangnya satu per satu — sehingga
 * pertanyaan yang paling sering diajukan tidak terjawab sama sekali:
 * "mana saja yang menunggu keputusan saya", "berapa yang jatuh tempo
 * bulan ini". Pertanyaan itu menyilang orang, bukan menyusuri satu
 * orang.
 *
 * SATU KOMPONEN UNTUK EMPAT TAHAP, dan kolomnya datang dari server.
 * Empat halaman yang bentuknya sama tetapi ditulis empat kali adalah
 * empat tempat yang harus diubah bersamaan tiap kali satu kolom
 * bertambah — dan yang tertinggal tidak menimbulkan galat, hanya satu
 * halaman yang diam-diam berbeda dari tiga lainnya.
 */
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import ZonaMasaBerlaku from './ZonaMasaBerlaku.vue';
import PitaMasaBerlaku from './PitaMasaBerlaku.vue';
import { propHalaman } from '../../halaman';
import { KEADAAN } from '../../Grafik/warna';

const props = propHalaman();

const baris = computed(() => props.baris ?? []);

const NADA: Record<string, string> = {
  netral: KEADAAN.netral, baik: KEADAAN.baik,
  ingat: KEADAAN.ingat, serius: KEADAAN.serius, gawat: KEADAAN.gawat,
};

/** Warna masa berlaku — dipesan maknanya, tidak dipakai untuk lain. */
const WARNA: Record<string, string> = {
  aman: KEADAAN.baik, perhatian: KEADAAN.ingat,
  segera: KEADAAN.serius, kritis: KEADAAN.gawat,
  'tak-bertanggal': KEADAAN.netral,
};

/** Nol tidak diberi warna peringatan — merah yang selalu menyala diabaikan. */
function warnaAngka(n: number, nada: string): string {
  return n > 0 ? (NADA[nada] ?? KEADAAN.netral) : KEADAAN.netral;
}

/* Urutan tahapnya digambar sebagai tautan, bukan hanya sebagai menu
   samping: dari halaman mana pun, urutan seluruh alurnya tetap
   terlihat. */
const URUT = [
  ['MCU',          '/miners/riwayat/mcu'],
  ['Induksi',      '/miners/riwayat/induksi'],
  ['Mine Permit',  '/miners/riwayat/mine-permit'],
  ['Mine License', '/miners/riwayat/mine-license'],
  ['Authority',    '/miners/riwayat/authority'],
];

/*
 * Kolom mana yang berisi nama orang dan mana yang berisi tanggal
 * berlaku — dicari dari JUDULNYA, bukan dari nomor urutnya.
 *
 * Susunan kolom berbeda antar tahap dan pernah berubah sekali. Nomor
 * urut yang ditulis tetap membuat tautan nama menempel pada kolom yang
 * salah begitu urutannya digeser — tanpa galat, hanya nama perusahaan
 * yang tiba-tiba dapat diklik.
 */
const kolomNama = computed(() =>
  (props.kolom ?? []).findIndex((k: string) => /^nama/i.test(k)));

const kolomTanggal = computed(() =>
  (props.kolom ?? []).findIndex((k: string) => /berlaku sampai|sim berlaku/i.test(k)));

const kini = computed(() => {
  const t = String(props.tahap ?? '');

  return { mcu: 'MCU', induksi: 'Induksi', 'mine-permit': 'Mine Permit',
           'mine-license': 'Mine License', authority: 'Authority' }[t] ?? '';
});
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1400px] mx-auto space-y-5">

    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-cam-ink">{{ props.judul }}</h2>
        <p class="text-[12.5px] text-stone-500 mt-1">{{ props.subjudul }}</p>
      </div>
      <Link href="/miners/dasbor" class="eq-btn-lain">Ringkasan</Link>
    </section>

    <!-- urutan alurnya, terlihat dari halaman mana pun -->
    <nav class="flex flex-wrap items-center gap-1.5 text-[11.5px]">
      <template v-for="(u, i) in URUT" :key="u[0]">
        <span v-if="i" class="text-stone-300">→</span>
        <Link :href="u[1]"
              class="rounded-lg px-2.5 py-1 font-semibold"
              :class="u[0] === kini
                ? 'bg-cam-lime-deep text-white'
                : 'bg-white border border-stone-200 text-stone-600 hover:border-stone-300'">
          {{ u[0] }}
        </Link>
      </template>
    </nav>

    <section class="grid gap-3 sm:grid-cols-4">
      <div v-for="r in (props.ringkas ?? [])" :key="r[0]"
           class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <p class="text-[26px] font-bold leading-none num"
           :style="{ color: warnaAngka(Number(r[1]), String(r[2])) }">{{ r[1] }}</p>
        <p class="text-[11.5px] text-stone-500 mt-1.5">{{ r[0] }}</p>
      </div>
    </section>

    <!--
      Panel zona hanya untuk daftar berkas berjangka. Riwayat induksi
      dan riwayat kompetensi tidak mengirimkannya, dan komponennya
      sendiri diam bila propnya kosong.
    -->
    <ZonaMasaBerlaku v-if="props.pemantauan?.zona"
                     :ringkas="props.pemantauan.zona"
                     :manpower="props.pemantauan.manpower"
                     :zona="props.zona"
                     :rute="props.rute ?? ''"
                     :judul="`Masa berlaku ${props.judul?.replace('Miners — Riwayat ', '') ?? ''}`" />

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <!--
        Judul kartu membawa jumlah barisnya, mengikuti D'Best. Daftar
        tanpa jumlah membuat orang menggulir sampai bawah hanya untuk
        tahu ada berapa — dan setelah disaring, jumlah itulah jawaban
        pertanyaannya.
      -->
      <div class="flex flex-wrap items-baseline justify-between gap-2 px-5 pt-4 pb-3">
        <h3 class="font-bold text-[14px]">
          Daftar {{ kini || 'berkas' }}
          <span class="font-normal text-[11.5px] text-stone-400">| {{ baris.length }} data</span>
        </h3>

        <p v-if="props.zona" class="text-[11.5px] text-stone-500">
          disaring pada satu zona masa berlaku
        </p>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[12px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-100">
              <th class="pl-5 pr-2 py-3 w-10">No</th>
              <th v-for="k in (props.kolom ?? [])" :key="k" class="px-3 py-3">{{ k }}</th>
              <th class="px-3 py-3">Keadaan</th>
              <th class="px-3 py-3"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(b, n) in baris" :key="b.id" class="border-b border-stone-50">
              <td class="pl-5 pr-2 py-3 num text-stone-400">{{ Number(n) + 1 }}</td>

              <td v-for="(s, i) in b.sel" :key="i" class="px-3 py-3"
                  :class="kolomNama === i ? 'font-semibold' : ''">
                <Link v-if="kolomNama === i" :href="`/miners/${b.pasporId}`" class="text-cam-lime-deep">
                  {{ s || '—' }}
                </Link>

                <!-- Kolom tanggal digambar sebagai pita: tanggal, sisa
                     hari, dan dari mana tanggalnya berasal. -->
                <PitaMasaBerlaku v-else-if="kolomTanggal === i"
                                 :tanggal="s || null" :sisa-hari="b.sisaHari ?? null"
                                 :sumber="b.namaDasar ?? null" :tgl-kartu="b.tglTercetak ?? null" />

                <template v-else>{{ s || '—' }}</template>

                <small v-if="kolomNama === i && b.jabatan"
                       class="block text-[10px] text-stone-400 font-normal">
                  {{ b.jabatan }}
                </small>
              </td>

              <td class="px-3 py-3">
                <!-- Status alur mendahului masa berlaku: yang belum
                     disetujui belum berlaku sama sekali, jadi menyebut
                     sisa harinya lebih dulu akan menyesatkan. -->
                <span v-if="b.status && b.status !== 'disetujui'"
                      class="font-semibold"
                      :style="{ color: b.status === 'ditolak' ? KEADAAN.gawat
                                     : b.status === 'diajukan' ? KEADAAN.ingat : KEADAAN.netral }">
                  {{ b.statusLabel }}
                </span>
                <!--
                  Sisa harinya sudah tertulis pada pita di kolom tanggal;
                  mengulanginya di sini membuat satu keterangan tampak
                  seperti dua hal berbeda. Yang tersisa untuk kolom ini
                  hanyalah keadaan alurnya — dan bila alurnya selesai,
                  cukup ditandai berlaku.
                -->
                <span v-else-if="kolomTanggal < 0" class="font-semibold"
                      :style="{ color: WARNA[b.keadaan] }">
                  {{ b.keterangan }}
                </span>
                <span v-else class="font-semibold" style="color:#15803D">Berlaku</span>

                <small v-if="b.tertinggal?.length" class="block text-[10px]"
                       :style="{ color: KEADAAN.ingat }">
                  belum diparaf: {{ b.tertinggal.join(', ') }}
                </small>
              </td>

              <td class="px-5 py-3 text-right whitespace-nowrap">
                <a v-if="b.cetak" :href="b.cetak" target="_blank"
                   class="text-[11px] font-semibold text-cam-lime-deep">Cetak</a>
                <span v-else-if="b.dapatDitinjau" class="text-[11px]"
                      :style="{ color: KEADAAN.ingat }">menunggu Anda</span>
              </td>
            </tr>

            <tr v-if="!baris.length">
              <td :colspan="(props.kolom?.length ?? 4) + 3"
                  class="px-5 py-12 text-center text-stone-400">
                Belum ada data pada tahap ini.
                <span v-if="props.tahap === 'induksi'">
                  Induksi dicatat dari halaman rincian pekerja, sesudah hasil MCU menyatakan layak.
                </span>
                <span v-else-if="props.tahap === 'authority'">
                  Sertifikat kompetensi dicatat dari halaman rincian pekerja.
                </span>
                <span v-else>
                  Pengajuan kartu dibuat dari halaman rincian pekerja.
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

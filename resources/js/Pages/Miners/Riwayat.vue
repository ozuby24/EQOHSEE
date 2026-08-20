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
import RingkasPemantauan from './RingkasPemantauan.vue';
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
      Hanya untuk daftar berkas berjangka. Riwayat induksi dan riwayat
      kompetensi tidak mengirimkannya, dan komponennya sendiri diam bila
      propnya kosong.
    -->
    <RingkasPemantauan v-if="props.pemantauan" :pemantauan="props.pemantauan" />

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[12px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-100">
              <th v-for="k in (props.kolom ?? [])" :key="k" class="px-5 py-3">{{ k }}</th>
              <th class="px-5 py-3">Keadaan</th>
              <th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in baris" :key="b.id" class="border-b border-stone-50">
              <td v-for="(s, i) in b.sel" :key="i" class="px-5 py-3"
                  :class="i === 0 ? 'font-semibold' : ''">
                <Link v-if="i === 0" :href="`/miners/${b.pasporId}`" class="text-cam-lime-deep">
                  {{ s || '—' }}
                </Link>
                <template v-else>{{ s || '—' }}</template>
                <small v-if="i === 0 && b.jabatan" class="block text-[10px] text-stone-400 font-normal">
                  {{ b.jabatan }}
                </small>
              </td>

              <td class="px-5 py-3">
                <!-- Status alur mendahului masa berlaku: yang belum
                     disetujui belum berlaku sama sekali, jadi menyebut
                     sisa harinya lebih dulu akan menyesatkan. -->
                <span v-if="b.status && b.status !== 'disetujui'"
                      class="font-semibold"
                      :style="{ color: b.status === 'ditolak' ? KEADAAN.gawat
                                     : b.status === 'diajukan' ? KEADAAN.ingat : KEADAAN.netral }">
                  {{ b.statusLabel }}
                </span>
                <span v-else class="font-semibold" :style="{ color: WARNA[b.keadaan] }">
                  {{ b.keterangan }}
                </span>

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
              <td :colspan="(props.kolom?.length ?? 4) + 2"
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

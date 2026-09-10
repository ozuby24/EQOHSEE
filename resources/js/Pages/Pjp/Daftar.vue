<script setup lang="ts">
/**
 * Daftar perusahaan jasa beserta ketiga skornya.
 *
 * Ketiga skor ditampilkan berdampingan, bukan diringkas menjadi satu
 * kolom. Achievement memang satu angka, tetapi angka itu sengaja
 * merupakan yang TERENDAH di antara ketiganya — dan tanpa ketiganya
 * terlihat, pembacanya tidak dapat tahu sisi mana yang menariknya turun.
 *
 * Penyaringan dikerjakan server, bukan di peramban. Daftar mitra dapat
 * tumbuh melewati satu layar, dan penyaring yang hanya bekerja atas
 * baris yang sudah terkirim akan diam-diam menyembunyikan sisanya.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { propHalaman } from '../../halaman';
import { KEADAAN } from '../../Grafik/warna';

const props = propHalaman();

/* Ditegaskan sebagai daftar. Tanpa itu `v-for="(b, i) in props.baris"`
   membuat indeksnya bertipe string|number — Vue memperbolehkan
   perulangan atas objek — dan `i + 1` gagal diperiksa. */
const baris = computed<any[]>(() => (props.baris ?? []) as any[]);

const cari   = ref(String(props.saring?.cari ?? ''));
const status = ref(String(props.saring?.status ?? ''));

let tunda: ReturnType<typeof setTimeout> | undefined;

/**
 * Menunda pengiriman, dan menahan gulirannya.
 *
 * Tanpa preserveState, tiap ketukan huruf memasang ulang komponennya
 * dan mengosongkan kotak isian di tengah pengetikan.
 */
watch([cari, status], () => {
  clearTimeout(tunda);

  tunda = setTimeout(() => {
    router.get('/pjp/daftar',
      { cari: cari.value || undefined, status: status.value || undefined },
      { preserveState: true, preserveScroll: true, replace: true });
  }, 300);
});

function nada(skor: number | null | undefined): string {
  if (skor === null || skor === undefined) return KEADAAN.netral;
  if (skor >= 80) return KEADAAN.baik;
  if (skor >= 55) return KEADAAN.ingat;
  return KEADAAN.gawat;
}

const angka = (v: number | null | undefined) => (v === null || v === undefined ? '—' : v);

/**
 * Warna lencana status.
 *
 * Dipetakan langsung dari statusnya, bukan dititipkan pada nada() dengan
 * angka karangan. "Tidak Aktif" bukan skor rendah — ia mitra yang memang
 * tidak lagi dipantau, dan mewarnainya merah menyuruh orang mengurus
 * sesuatu yang tidak perlu diurus.
 */
const WARNA_STATUS: Record<string, string> = {
  aktif:               KEADAAN.baik,
  perlu_tindak_lanjut: KEADAAN.serius,
  tidak_aktif:         KEADAAN.netral,
};

const warnaStatus = (s: string) => WARNA_STATUS[s] ?? KEADAAN.netral;

function tautanUnduh(dasar: string): string {
  const q = new URLSearchParams();
  if (cari.value) q.set('cari', cari.value);
  if (status.value) q.set('status', status.value);
  const s = q.toString();

  return s ? `${dasar}?${s}` : dasar;
}
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1400px] mx-auto space-y-5">
    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-cam-ink">{{ props.judul }}</h2>
        <p class="text-[12.5px] text-stone-500 mt-0.5">
          Skor terendah dari ketiga sisi yang menentukan angka achievement-nya.
        </p>
      </div>

      <span class="inline-flex flex-wrap gap-2">
        <a :href="tautanUnduh('/pjp/csv')" class="eq-btn-lain">Unduh CSV</a>
        <Link :href="tautanUnduh('/pjp/cetak')" class="eq-btn-lain">Cetak</Link>
        <Link href="/pjp/baru" class="eq-btn-utama">+ Tambah mitra</Link>
      </span>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100 flex flex-wrap items-center gap-3">
        <h3 class="text-[13.5px] font-bold text-cam-ink flex-1 min-w-0">
          Perusahaan jasa <span class="font-normal text-stone-400">| {{ baris.length }} data</span>
        </h3>

        <select v-model="status" class="rounded-lg border-stone-200 text-[12px]" aria-label="Saring status">
          <option value="">Semua status</option>
          <option v-for="(label, kode) in (props.STATUS ?? {})" :key="kode" :value="kode">{{ label }}</option>
        </select>

        <input v-model="cari" placeholder="Cari nama, NIB, penanggung jawab…"
               class="rounded-lg border-stone-200 text-[12px] w-64" aria-label="Cari perusahaan jasa">
      </header>

      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[11.5px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
              <th class="px-4 py-2 font-semibold w-10">No</th>
              <th class="px-4 py-2 font-semibold">Perusahaan Jasa</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">NIB</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Penanggung Jawab</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap text-right">SMKP</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap text-right">Pelaporan</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap text-right">Evaluasi</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap text-right">Achievement</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Status</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="(b, i) in baris" :key="b.id"
                class="border-b border-stone-100 hover:bg-stone-50/60">
              <td class="px-4 py-2.5 num text-stone-400">{{ i + 1 }}</td>

              <td class="px-4 py-2.5">
                <Link :href="`/pjp/${b.id}`" class="font-bold text-cam-lime-deep hover:underline">
                  {{ b.nama_perusahaan }}
                </Link>
              </td>

              <td class="px-4 py-2.5 num text-stone-500 whitespace-nowrap">{{ b.nib || '—' }}</td>
              <td class="px-4 py-2.5 text-stone-500 whitespace-nowrap">{{ b.penanggung_jawab || '—' }}</td>

              <td class="px-4 py-2.5 num text-right whitespace-nowrap" :style="{ color: nada(b.smkp) }">{{ angka(b.smkp) }}</td>
              <td class="px-4 py-2.5 num text-right whitespace-nowrap" :style="{ color: nada(b.pelaporan) }">{{ angka(b.pelaporan) }}</td>
              <td class="px-4 py-2.5 num text-right whitespace-nowrap" :style="{ color: nada(b.evaluasi) }">{{ angka(b.evaluasi) }}</td>

              <td class="px-4 py-2.5 text-right whitespace-nowrap">
                <span class="num font-bold" :style="{ color: nada(b.achievement) }">{{ angka(b.achievement) }}</span>
              </td>

              <td class="px-4 py-2.5 whitespace-nowrap">
                <span class="rounded px-1.5 py-0.5 text-[10px] font-bold"
                      :style="{ background: warnaStatus(b.status) + '1F', color: warnaStatus(b.status) }">
                  {{ (props.STATUS ?? {})[b.status] ?? b.status }}
                </span>
              </td>
            </tr>

            <tr v-if="!baris.length">
              <td colspan="9" class="px-4 py-10 text-center text-[12px] text-stone-400">
                {{ cari || status ? 'Tidak ada yang cocok dengan penyaring ini.' : 'Belum ada perusahaan jasa terdaftar.' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
/**
 * Perintah lembur (SPL).
 *
 * TIAP BARIS MENUNJUKKAN RINCIAN FAKTORNYA, bukan hanya jumlahnya.
 * Sengketa upah lembur selalu berbentuk "kenapa angkanya segini" —
 * dan jawabannya adalah berapa jam dikalikan faktor berapa. Ditampilkan
 * sebagai satu angka akhir, yang menjawabnya harus menghitung ulang
 * dengan aturan yang mungkin sudah berubah.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { propHalaman } from '../../../halaman';
import Dialog from '../../../Components/Dialog.vue';
import { useDialog } from '../../../dialog';

const props = propHalaman();
const { dialog, tanya, minta, batal, lanjut } = useDialog();

const antrean = computed<any[]>(() => (props.antrean ?? []) as any[]);
const riwayat = computed<any[]>(() => (props.riwayat ?? []) as any[]);
const ringkas = computed<any>(() => props.ringkas ?? {});
const acuan   = computed<any>(() => props.ACUAN ?? {});

const dari   = ref(String((props.rentang as any)?.dari ?? ''));
const sampai = ref(String((props.rentang as any)?.sampai ?? ''));

function muat() {
  router.get('/hris/lembur', { dari: dari.value || undefined, sampai: sampai.value || undefined },
    { preserveState: true, preserveScroll: true, replace: true });
}

const usul = useForm({});

async function usulkan() {
  if (!await tanya({
    judul: 'Usulkan lembur dari catatan absensi?',
    pesan: `Selisih jam tercatat terhadap jadwal shift, minimal ${acuan.value.min_usul} jam. `
      + 'Hari yang sudah punya perintah lembur dilewati.',
    labelAksi: 'Usulkan',
  })) return;

  usul.post(`/hris/lembur/usulkan?dari=${dari.value}&sampai=${sampai.value}`, { preserveScroll: true });
}

function kirim(id: number, aksi: string, catatan?: string) {
  useForm({ catatan: catatan ?? '' }).post(`/hris/lembur/${id}/${aksi}`, { preserveScroll: true });
}

async function setujui(l: any) {
  if (!await tanya({
    judul: `Setujui lembur ${l.pekerja} ${l.tanggal}?`,
    pesan: [
      `${l.jam} jam · ${rupiah(l.nilai)}.`,
      (l.peringatan ?? []).join(' '),
    ].join(' ').trim(),
    labelAksi: 'Setujui',
    nada: (l.peringatan ?? []).length ? 'bahaya' : 'utama',
  })) return;

  kirim(l.id, 'setujui');
}

async function tolak(l: any) {
  const alasan = await minta({
    judul: `Tolak lembur ${l.pekerja} ${l.tanggal}?`,
    label: 'Alasan penolakan',
    jenis: 'panjang',
    min: 5,
    labelAksi: 'Tolak',
    nada: 'bahaya',
  });

  if (alasan === null) return;

  kirim(l.id, 'tolak', alasan);
}

async function batalkan(l: any) {
  if (!await tanya({
    judul: `Batalkan lembur ${l.pekerja} ${l.tanggal}?`,
    pesan: 'Nilainya menjadi nol dan tidak ikut terbayar.',
    labelAksi: 'Batalkan',
    nada: 'bahaya',
  })) return;

  kirim(l.id, 'batalkan');
}

function rupiah(n: number | null) {
  if (n === null || n === undefined) return '—';
  return 'Rp ' + Math.round(n).toLocaleString('id-ID');
}

function faktorTeks(rincian: any[]) {
  return (rincian ?? []).map((r) => `${r.jam} jam × ${r.faktor}`).join('  +  ');
}
</script>

<template>
  <Head :title="props.judul" />
  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />

  <div class="max-w-[1240px] mx-auto space-y-5">
    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-cam-ink">{{ props.judul }}</h2>
        <p class="text-[12.5px] text-stone-500 mt-0.5">{{ props.subjudul }}</p>
      </div>

      <Link href="/hris/lembur/upah" class="eq-btn-lain">Upah dasar</Link>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
      <div class="flex flex-wrap items-end gap-3">
        <label class="block">
          <span class="text-[11px] text-stone-500">Dari</span>
          <input v-model="dari" type="date" class="mt-1 rounded-lg border-stone-200 text-[12px]" @change="muat">
        </label>

        <label class="block">
          <span class="text-[11px] text-stone-500">Sampai</span>
          <input v-model="sampai" type="date" class="mt-1 rounded-lg border-stone-200 text-[12px]" @change="muat">
        </label>

        <button type="button" class="eq-btn-lain" :disabled="usul.processing" @click="usulkan">
          Usulkan dari absensi
        </button>

        <p class="text-[11.5px] text-stone-500 pb-1">
          Disetujui: <span class="num font-semibold text-cam-ink">{{ ringkas.jam ?? 0 }}</span> jam ·
          <span class="num font-semibold text-cam-ink">{{ rupiah(ringkas.nilai) }}</span>
        </p>
      </div>
    </section>

    <!-- ══════════ antrean ══════════ -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100 flex flex-wrap items-baseline justify-between gap-2">
        <h3 class="text-[13.5px] font-bold text-cam-ink">
          Menunggu persetujuan
          <span class="font-normal text-stone-400">| {{ antrean.length }} perintah</span>
        </h3>
        <span class="text-[11px] text-stone-400">
          Upah sejam = 1/{{ acuan.pembagi }} × upah sebulan — PP 35/2021 pasal 32.
        </span>
      </header>

      <div v-if="!antrean.length" class="px-5 py-10 text-center text-[12px] text-stone-400">
        Tidak ada perintah lembur yang menunggu.
      </div>

      <div v-for="l in antrean" :key="l.id" class="px-5 py-4 border-b border-stone-100 last:border-0">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="min-w-0">
            <div class="text-[13px] font-bold text-cam-ink">
              {{ l.pekerja }}
              <span class="font-normal text-stone-400">· {{ l.nik }}<span v-if="l.jabatan"> · {{ l.jabatan }}</span></span>
            </div>

            <div class="text-[12px] text-stone-600 mt-0.5">
              <span class="num">{{ l.tanggal }}</span> ·
              {{ props.JENIS_HARI?.[l.jenisHari] ?? l.jenisHari }}
              <span v-if="l.jenisHari === 'libur'" class="text-stone-400">(pola {{ l.hariMinggu }} hari seminggu)</span>
              · <span class="num font-semibold">{{ l.jam }}</span> jam
            </div>

            <div class="text-[11.5px] text-stone-500 mt-1">
              {{ faktorTeks(l.rincian) }}
              <span class="text-stone-400">
                × {{ rupiah(l.sejam) }}/jam (dasar {{ l.persen }}% dari {{ rupiah(l.sebulan) }})
              </span>
            </div>

            <p v-if="l.alasan" class="text-[11.5px] text-stone-500 mt-1">{{ l.alasan }}</p>

            <p v-for="(w, i) in (l.peringatan ?? [])" :key="i"
               class="mt-1.5 rounded-lg bg-amber-50 border border-amber-200 px-3 py-1.5 text-[11.5px] text-amber-800">
              {{ w }}
            </p>
          </div>

          <div class="flex flex-col items-end gap-2 shrink-0">
            <div class="num text-[17px] font-bold text-cam-ink">{{ rupiah(l.nilai) }}</div>

            <div class="flex flex-wrap gap-2">
              <button type="button" class="eq-btn-utama" @click="setujui(l)">Setujui</button>
              <button type="button" class="eq-btn-lain" @click="tolak(l)">Tolak</button>
              <button type="button" class="text-[11px] text-red-600 hover:underline" @click="batalkan(l)">Batalkan</button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ══════════ riwayat ══════════ -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100">
        <h3 class="text-[13.5px] font-bold text-cam-ink">
          Riwayat <span class="font-normal text-stone-400">| {{ riwayat.length }} baris</span>
        </h3>
      </header>

      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[11.5px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
              <th class="px-5 py-2 font-semibold">Pekerja</th>
              <th class="px-5 py-2 font-semibold">Tanggal</th>
              <th class="px-5 py-2 font-semibold">Hari</th>
              <th class="px-5 py-2 font-semibold text-right">Jam</th>
              <th class="px-5 py-2 font-semibold">Faktor</th>
              <th class="px-5 py-2 font-semibold text-right">Nilai</th>
              <th class="px-5 py-2 font-semibold">Status</th>
              <th class="px-5 py-2 font-semibold"></th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="l in riwayat" :key="l.id" class="border-b border-stone-100">
              <td class="px-5 py-2.5">
                <div class="font-semibold text-cam-ink">{{ l.pekerja }}</div>
                <div class="text-[10.5px] text-stone-400">{{ l.nik }}</div>
              </td>
              <td class="px-5 py-2.5 num text-stone-600">{{ l.tanggal }}</td>
              <td class="px-5 py-2.5 text-stone-600">{{ props.JENIS_HARI?.[l.jenisHari] ?? l.jenisHari }}</td>
              <td class="px-5 py-2.5 num text-right">{{ l.jam }}</td>
              <td class="px-5 py-2.5 num text-[10.5px] text-stone-500">{{ faktorTeks(l.rincian) }}</td>
              <td class="px-5 py-2.5 num text-right font-semibold"
                  :class="l.nilai > 0 ? 'text-cam-ink' : 'text-stone-300'">{{ rupiah(l.nilai) }}</td>
              <td class="px-5 py-2.5">
                <span class="lb-lencana" :class="'lb-' + l.status">{{ props.STATUS?.[l.status] ?? l.status }}</span>
                <div v-if="l.catatan" class="text-[10.5px] text-stone-400 mt-0.5">{{ l.catatan }}</div>
              </td>
              <td class="px-5 py-2.5 text-right">
                <button v-if="l.status === 'disetujui'" type="button"
                        class="text-[11px] text-red-600 hover:underline" @click="batalkan(l)">Batalkan</button>
              </td>
            </tr>

            <tr v-if="!riwayat.length">
              <td colspan="8" class="px-5 py-10 text-center text-stone-400">
                Belum ada perintah lembur yang selesai ditindak pada rentang ini.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="text-[13.5px] font-bold text-cam-ink mb-2">Dasar perhitungan</h3>

      <ul class="text-[11.5px] text-stone-600 space-y-1 list-disc pl-4">
        <li>Upah sejam = 1/{{ acuan.pembagi }} × upah sebulan — pasal 32 ayat (1).</li>
        <li>Hari kerja: jam pertama 1,5× upah sejam, jam kedua dan seterusnya 2× — pasal 31 ayat (1).</li>
        <li>Hari libur pola 6 hari: jam ke-1 s.d. ke-7 2×, jam ke-8 3×, jam ke-9 dan seterusnya 4× — pasal 31 ayat (2) huruf a.</li>
        <li>Hari libur pola 5 hari: jam ke-1 s.d. ke-8 2×, jam ke-9 3×, jam ke-10 dan seterusnya 4× — pasal 31 ayat (2) huruf b.</li>
        <li>
          Batas {{ acuan.maks_hari }} jam sehari dan {{ acuan.maks_minggu }} jam seminggu — pasal 29 ayat (2).
          Tidak berlaku pada hari libur, dan melampauinya tidak membatalkan upahnya:
          jamnya sudah dikerjakan.
        </li>
      </ul>
    </section>
  </div>
</template>

<style>
.lb-lencana {
  display: inline-block; border-radius: 9999px; padding: 0.125rem 0.5rem;
  font-size: 11px; font-weight: 500; white-space: nowrap;
}

.lb-menunggu   { background: #FEF3C7; color: #78350F; }
.lb-disetujui  { background: #D1FAE5; color: #065F46; }
.lb-ditolak    { background: #FEE2E2; color: #7F1D1D; }
.lb-dibatalkan { background: #F5F5F4; color: #57534E; }

:root[data-tema="gelap"] .lb-menunggu   { background: #4A3810; color: #F6D488; }
:root[data-tema="gelap"] .lb-disetujui  { background: #143A2C; color: #8FE3BE; }
:root[data-tema="gelap"] .lb-ditolak    { background: #4E1D1D; color: #F5A9A9; }
:root[data-tema="gelap"] .lb-dibatalkan { background: #1C262B; color: #A8B2B8; }
</style>

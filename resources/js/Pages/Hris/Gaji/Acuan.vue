<script setup lang="ts">
/**
 * Acuan pajak dan BPJS, beserta siapa yang memeriksanya.
 *
 * TABEL TARIFNYA DITAMPILKAN SELURUHNYA, bukan diringkas menjadi
 * "125 baris terpasang". Yang memeriksa harus punya sesuatu untuk
 * dicocokkan dengan naskah peraturannya — tanpa itu, penandanya
 * menjadi centang tanpa isi, dan tanda tanpa isi lebih berbahaya
 * daripada tidak ada tanda sama sekali: ia menghentikan pertanyaan.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { propHalaman } from '../../../halaman';
import Dialog from '../../../Components/Dialog.vue';
import { useDialog } from '../../../dialog';

const props = propHalaman();
const { dialog, tanya, batal, lanjut } = useDialog();

const acuan = computed<any[]>(() => (props.acuan ?? []) as any[]);
const ter   = computed<any>(() => props.ter ?? {});

const kategori = ref<'A' | 'B' | 'C'>('A');

const belum = computed(() => acuan.value.filter((a) => !a.terverifikasi).length);

async function tandai(a: any) {
  const nyala = !a.terverifikasi;

  if (nyala && !await tanya({
    judul: `Tandai "${a.nama}" sudah diperiksa?`,
    pesan: 'Tandai hanya bila Anda benar-benar sudah mencocokkan angkanya dengan naskah peraturannya, '
      + 'baris demi baris. Tanda ini yang membuka penguncian periode gaji.',
    labelAksi: 'Sudah saya periksa',
  })) return;

  useForm({ terverifikasi: nyala }).post(`/hris/gaji/acuan/${a.id}`, { preserveScroll: true });
}

function rupiah(n: number | null) {
  if (n === null || n === undefined) return '—';
  return Math.round(n).toLocaleString('id-ID');
}
</script>

<template>
  <Head :title="props.judul" />
  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />

  <div class="max-w-[1100px] mx-auto space-y-5">

    <section class="-mt-2 flex flex-wrap items-end justify-end gap-3">

      <Link href="/hris/gaji" class="eq-btn-lain">Periode &amp; slip</Link>
    </section>

    <section v-if="belum" class="rounded-2xl bg-amber-50 border border-amber-200 px-4 py-3 text-[12.5px] text-amber-800">
      <span class="num font-bold">{{ belum }}</span> acuan belum diperiksa. Angka di bawah disusun dari
      acuan yang beredar umum dan <strong>belum dicocokkan baris demi baris</strong> dengan naskah
      peraturannya. Periode gaji tidak dapat dikunci sampai seseorang yang memegang naskah itu
      menandainya.
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <div v-for="a in acuan" :key="a.id" class="px-5 py-4 border-b border-stone-100 last:border-0">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="min-w-0">
            <div class="text-[13px] font-bold text-cam-ink">{{ a.nama }}</div>
            <div class="text-[11.5px] text-stone-600 mt-0.5">{{ a.sumber }}</div>
            <p v-if="a.catatan" class="text-[11.5px] text-stone-500 mt-1">{{ a.catatan }}</p>
            <p v-if="a.terverifikasi" class="text-[11px] text-emerald-700 mt-1">
              Diperiksa {{ a.pemeriksa || 'seseorang' }}<span v-if="a.pada"> · {{ a.pada }}</span>
            </p>
          </div>

          <div class="shrink-0 flex flex-col items-end gap-2">
            <span class="ac-lencana" :class="a.terverifikasi ? 'ac-ya' : 'ac-belum'">
              {{ a.terverifikasi ? 'Sudah diperiksa' : 'Belum diperiksa' }}
            </span>
            <button type="button" class="text-[11px] hover:underline"
                    :class="a.terverifikasi ? 'text-stone-500' : 'text-sky-700'"
                    @click="tandai(a)">
              {{ a.terverifikasi ? 'Cabut tanda' : 'Tandai sudah diperiksa' }}
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- ══════════ tarif efektif ══════════ -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100 flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-[13.5px] font-bold text-cam-ink">Tarif efektif rata-rata bulanan</h3>

        <div class="flex gap-1">
          <button v-for="k in (['A', 'B', 'C'] as const)" :key="k" type="button"
                  class="rounded-lg px-2.5 py-1 text-[11.5px] font-semibold"
                  :class="kategori === k ? 'bg-cam-ink text-white' : 'bg-stone-100 text-stone-600'"
                  @click="kategori = k">Kategori {{ k }}</button>
        </div>
      </header>

      <p class="px-5 py-2 text-[11.5px] text-stone-500 border-b border-stone-100">
        Kategori ditentukan status PTKP, bukan besar penghasilan:
        A untuk TK/0, TK/1, dan K/0 · B untuk TK/2, TK/3, K/1, dan K/2 · C untuk K/3.
      </p>

      <div class="overflow-x-auto max-h-[420px]">
        <table class="min-w-full text-left text-[11.5px]">
          <thead class="sticky top-0">
            <tr class="text-stone-400 border-b border-stone-200 bg-stone-50">
              <th class="px-5 py-2 font-semibold text-right">Di atas</th>
              <th class="px-5 py-2 font-semibold text-right">Sampai</th>
              <th class="px-5 py-2 font-semibold text-right">Tarif</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(b, i) in (ter[kategori] ?? [])" :key="i" class="border-b border-stone-100">
              <td class="px-5 py-1.5 num text-right text-stone-500">{{ rupiah(b.bawah) }}</td>
              <td class="px-5 py-1.5 num text-right text-stone-600">
                {{ b.atas === null ? 'tak berbatas' : rupiah(b.atas) }}
              </td>
              <td class="px-5 py-1.5 num text-right font-semibold text-cam-ink">{{ b.tarif }}%</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- ══════════ PTKP dan Pasal 17 ══════════ -->
    <section class="grid gap-4 lg:grid-cols-2">
      <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="text-[13.5px] font-bold text-cam-ink mb-2">PTKP setahun</h3>
        <table class="min-w-full text-left text-[11.5px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-200">
              <th class="py-1.5 pr-3 font-semibold">Status</th>
              <th class="py-1.5 pr-3 font-semibold">TER</th>
              <th class="py-1.5 font-semibold text-right">PTKP</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in (props.ptkp ?? [])" :key="p.kode" class="border-b border-stone-100">
              <td class="py-1.5 pr-3 num font-semibold text-cam-ink">{{ p.kode }}</td>
              <td class="py-1.5 pr-3 num text-stone-600">{{ p.kategori }}</td>
              <td class="py-1.5 num text-right">{{ rupiah(p.ptkp) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="text-[13.5px] font-bold text-cam-ink mb-2">Tarif progresif Pasal 17</h3>
        <p class="text-[11.5px] text-stone-500 mb-2">
          Dipakai pada rekonsiliasi Desember saja, bukan pada bulan Januari sampai November.
        </p>
        <table class="min-w-full text-left text-[11.5px]">
          <tbody>
            <tr v-for="(l, i) in (props.pasal17 ?? [])" :key="i" class="border-b border-stone-100">
              <td class="py-1.5 num text-stone-600">
                sampai {{ l.atas === null ? 'tak berbatas' : rupiah(l.atas) }}
              </td>
              <td class="py-1.5 num text-right font-semibold text-cam-ink">{{ l.tarif }}%</td>
            </tr>
          </tbody>
        </table>

        <h3 class="text-[13.5px] font-bold text-cam-ink mt-4 mb-2">Iuran BPJS</h3>
        <table class="min-w-full text-left text-[11.5px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-200">
              <th class="py-1.5 pr-3 font-semibold">Program</th>
              <th class="py-1.5 pr-3 font-semibold text-right">Perusahaan</th>
              <th class="py-1.5 pr-3 font-semibold text-right">Karyawan</th>
              <th class="py-1.5 font-semibold text-right">Plafon upah</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(b, i) in (props.bpjs ?? [])" :key="i" class="border-b border-stone-100">
              <td class="py-1.5 pr-3 text-stone-600">{{ b.nama }}</td>
              <td class="py-1.5 pr-3 num text-right">{{ b.perusahaan }}%</td>
              <td class="py-1.5 pr-3 num text-right">{{ b.karyawan }}%</td>
              <td class="py-1.5 num text-right text-stone-500">
                {{ b.plafon === null ? 'tanpa plafon' : rupiah(b.plafon) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<style>
.ac-lencana {
  display: inline-block; border-radius: 9999px; padding: 0.125rem 0.5rem;
  font-size: 11px; font-weight: 600; white-space: nowrap;
}

.ac-ya    { background: #D1FAE5; color: #065F46; }
.ac-belum { background: #FEF3C7; color: #78350F; }

:root[data-tema="gelap"] .ac-ya    { background: #143A2C; color: #8FE3BE; }
:root[data-tema="gelap"] .ac-belum { background: #4A3810; color: #F6D488; }
</style>

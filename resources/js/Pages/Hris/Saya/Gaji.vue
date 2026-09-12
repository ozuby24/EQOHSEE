<script setup lang="ts">
/**
 * Slip gaji saya.
 *
 * HANYA PERIODE YANG SUDAH DIKUNCI. Periode yang belum dikunci masih
 * pratinjau: angkanya dapat berubah saat dihitung ulang, dan angka gaji
 * yang berubah sesudah dilihat orangnya adalah cara tercepat kehilangan
 * kepercayaan atas seluruh sistem. Halaman ini menyebutkan pembatasan
 * itu, bukan menyembunyikannya — orang yang tahu periodenya sudah
 * dihitung dan tidak menemukannya di sini akan mengira slipnya hilang.
 *
 * RINCIANNYA DIBUKA DI TEMPAT, bukan di halaman lain. Yang ditanyakan
 * orang tentang slipnya selalu "kenapa segini", dan jawabannya adalah
 * baris-baris yang menyusunnya.
 */
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { propHalaman } from '../../../halaman';

const props = propHalaman();
const slip  = computed<any[]>(() => (props.slip ?? []) as any[]);

const dibuka = ref<number | null>(null);

function rupiah(n: number | null | undefined): string {
  if (n === null || n === undefined) return '—';
  return 'Rp ' + Math.round(Number(n)).toLocaleString('id-ID');
}

/** Baris rincian sebuah slip: [label, nilai, tanda]. */
function rincian(s: any): [string, string, 'tambah' | 'kurang' | 'jumlah'][] {
  return [
    ['Upah pokok', rupiah(s.pokok), 'tambah'],
    [`Tunjangan site (${s.hariSite} hari)`, rupiah(s.site), 'tambah'],
    ['Lembur', rupiah(s.lembur), 'tambah'],
    ['Bruto pajak', rupiah(s.bruto), 'jumlah'],
    ['Iuran BPJS karyawan', rupiah(s.bpjs), 'kurang'],
    [`PPh 21${s.statusPtkp ? ' · ' + s.statusPtkp : ''}`, rupiah(s.pph21), 'kurang'],
    ['Dibawa pulang', rupiah(s.neto), 'jumlah'],
  ];
}
</script>

<template>
  <Head :title="props.judul as string" />

  <div class="space-y-4">
    <section v-if="!slip.length"
             class="rounded-2xl bg-white border border-stone-100 shadow-card p-8 text-center">
      <h2 class="text-[14px] font-bold">Belum ada slip yang terbit</h2>
      <p class="text-[12.5px] text-stone-500 mt-2 max-w-md mx-auto leading-relaxed">
        Slip muncul di sini sesudah periode gajinya <b>dikunci</b>. Sebelum dikunci, angkanya
        masih pratinjau dan dapat berubah saat dihitung ulang — dan angka gaji yang berubah
        sesudah dilihat lebih buruk daripada angka yang belum ada.
      </p>
    </section>

    <section v-for="s in slip" :key="s.id"
             class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <button type="button" class="w-full flex flex-wrap items-center gap-4 px-4 py-3.5 text-left"
              :aria-expanded="dibuka === s.id"
              @click="dibuka = dibuka === s.id ? null : s.id">
        <span class="min-w-0">
          <span class="block text-[13px] font-bold">{{ s.periode }}</span>
          <span class="block text-[11px] text-stone-500">Slip gaji · sudah dikunci</span>
        </span>

        <span class="ml-auto text-right">
          <span class="block text-[10.5px] text-stone-400">Dibawa pulang</span>
          <span class="num block text-[17px] font-extrabold">{{ rupiah(s.neto) }}</span>
        </span>

        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
             class="w-4 h-4 text-stone-400 transition-transform"
             :class="dibuka === s.id ? 'rotate-180' : null">
          <path d="m6 9.5 6 6 6-6"/>
        </svg>
      </button>

      <div v-if="dibuka === s.id" class="border-t border-stone-100 px-4 py-3">
        <dl class="text-[12.5px]">
          <div v-for="([label, nilai, tanda], i) in rincian(s)" :key="i"
               class="sg-baris" :class="'sg-' + tanda">
            <dt>{{ label }}</dt>
            <dd class="num">{{ nilai }}</dd>
          </div>
        </dl>
      </div>
    </section>
  </div>
</template>

<style>
.sg-baris{display:flex;align-items:baseline;justify-content:space-between;gap:1rem;
  padding:.45rem 0}
.sg-baris + .sg-baris{border-top:1px solid #F5F5F4}
.sg-baris dt{color:#57534E}
.sg-baris dd{margin:0;font-weight:600;color:#1C1917}

.sg-kurang dd{color:#B91C1C}
.sg-kurang dd::before{content:"− "}

/* Baris jumlah dibedakan garisnya, bukan hanya tebalnya: pada daftar
   tujuh baris yang seluruhnya berangka, tebal saja tidak cukup
   memisahkan yang dijumlahkan dari yang menjumlah. */
.sg-jumlah{border-top:1.5px solid #E7E5E4 !important;margin-top:.2rem;padding-top:.6rem}
.sg-jumlah dt{font-weight:700;color:#1C1917}
.sg-jumlah dd{font-size:14px;font-weight:800}

:root[data-tema="gelap"] .sg-baris + .sg-baris{border-top-color:#222E33}
:root[data-tema="gelap"] .sg-baris dt{color:#A8B2B8}
:root[data-tema="gelap"] .sg-baris dd{color:#E8EDEF}
:root[data-tema="gelap"] .sg-kurang dd{color:#F5A9A9}
:root[data-tema="gelap"] .sg-jumlah{border-top-color:#2C3A41 !important}
:root[data-tema="gelap"] .sg-jumlah dt{color:#E8EDEF}
</style>

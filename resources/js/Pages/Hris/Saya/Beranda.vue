<script setup lang="ts">
/**
 * Beranda layanan mandiri.
 *
 * MENJAWAB EMPAT PERTANYAAN YANG PALING SERING DITANYAKAN LEWAT PESAN
 * KEPADA STAF HR: besok saya masuk atau libur, sisa cuti saya berapa,
 * kontrak saya sampai kapan, dan gaji terakhir saya berapa. Keempatnya
 * ada di satu layar dan tidak menuntut siapa pun membuka layar admin
 * untuk menjawabnya.
 */
import { Head, Link } from '@inertiajs/vue3';
import { computed, h } from 'vue';
import { propHalaman } from '../../../halaman';
import UbinAngka from '../../../Components/UbinAngka.vue';

const IKON: Record<string, string[]> = {
  hak:      ['M8 3v3m8-3v3M3.5 9.5h17M5 5.5h14a1.5 1.5 0 0 1 1.5 1.5v12a1.5 1.5 0 0 1-1.5 1.5H5A1.5 1.5 0 0 1 3.5 19V7A1.5 1.5 0 0 1 5 5.5Z'],
  pakai:    ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'm8.5 12.2 2.4 2.4 4.6-4.9'],
  antre:    ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'M12 7.5v5l3.2 1.9'],
  sisa:     ['M3.5 8.5h17a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1h-17a1 1 0 0 1-1-1v-8a1 1 0 0 1 1-1Z', 'M17.5 13.5h.01'],
};

const props = propHalaman();

const Ikon = (p: { nama: string }) => h('svg', {
  viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 1.9,
  'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'aria-hidden': 'true',
}, (IKON[p.nama] ?? []).map((d) => h('path', { d })));

const saya    = computed<any>(() => props.saya ?? {});
const jadwal  = computed<any[]>(() => (props.jadwal ?? []) as any[]);
const saldo   = computed<any>(() => props.saldo ?? {});
const kontrak = computed<any>(() => props.kontrak ?? null);
const slip    = computed<any>(() => props.slip ?? null);

const HARI = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

function hari(t: string) { return HARI[new Date(t + 'T00:00:00').getDay()] ?? ''; }
function tgl(t: string)  { return t.slice(8, 10); }

function rupiah(n: number | null | undefined): string {
  if (n === null || n === undefined) return '—';
  return 'Rp ' + Math.round(Number(n)).toLocaleString('id-ID');
}
</script>

<template>
  <Head :title="props.judul as string" />

  <div class="space-y-4">
    <!-- ── Diri ── -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-4
                    flex flex-wrap items-center gap-4">
      <img v-if="saya.foto" :src="saya.foto" alt="" width="52" height="52"
           class="w-13 h-13 rounded-xl object-cover" style="width:52px;height:52px">
      <span v-else class="grid place-items-center w-13 h-13 rounded-xl bg-stone-50
                          text-[17px] font-extrabold text-stone-400" style="width:52px;height:52px">
        {{ (saya.nama ?? '?').charAt(0) }}
      </span>

      <div class="min-w-0">
        <div class="text-[15px] font-bold">{{ saya.nama }}</div>
        <div class="text-[11.5px] text-stone-500">
          <span class="num">{{ saya.registrasi }}</span>
          <span v-if="saya.jabatan"> · {{ saya.jabatan }}</span>
          <span v-if="saya.departemen"> · {{ saya.departemen }}</span>
        </div>
      </div>

      <div v-if="saya.masuk" class="ml-auto text-right">
        <div class="text-[10.5px] text-stone-400">Bergabung</div>
        <div class="num text-[12.5px] font-semibold">{{ saya.masuk }}</div>
      </div>
    </section>

    <!-- ── Jadwal 14 hari ── -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
      <h2 class="text-[13px] font-bold">
        Jadwal saya <span class="text-stone-400 font-normal">| 14 hari ke depan</span>
      </h2>

      <p class="text-[11.5px] text-stone-500 mt-1">
        Diambil dari kalender regu Anda. Hari yang lahir dari cuti yang disetujui ditandai tersendiri.
      </p>

      <div v-if="jadwal.length" class="mt-3 flex flex-wrap gap-1.5">
        <span v-for="j in jadwal" :key="j.tanggal" class="sy-hari"
              :class="['sy-' + j.keadaan, j.cuti ? 'sy-cuti' : null]"
              :title="`${j.tanggal} — ${j.keadaan}${j.shift ? ' · ' + j.shift : ''}`">
          <small>{{ hari(j.tanggal) }}</small>
          <strong class="num">{{ tgl(j.tanggal) }}</strong>
        </span>
      </div>

      <p v-else class="mt-4 text-[12px] text-stone-400">
        Belum ada jadwal tersusun untuk dua minggu ke depan.
      </p>
    </section>

    <!-- ── Cuti ── -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
      <header class="flex items-center justify-between gap-3">
        <h2 class="text-[13px] font-bold">Cuti tahunan saya</h2>
        <Link href="/hris/saya/cuti" class="text-[11.5px] text-sky-700 hover:underline">
          Ajukan cuti
        </Link>
      </header>

      <div class="mt-3 ubin-kisi">
        <UbinAngka :angka="saldo.hak ?? 0" label="Hak setahun">
          <template #ikon><Ikon nama="hak" /></template>
        </UbinAngka>

        <UbinAngka :angka="saldo.terpakai ?? 0" label="Sudah terpakai" nada="serius"
                   :dari="saldo.hak ?? 0">
          <template #ikon><Ikon nama="pakai" /></template>
        </UbinAngka>

        <UbinAngka :angka="saldo.tertunda ?? 0" label="Menunggu persetujuan"
                   :nada="(saldo.tertunda ?? 0) > 0 ? 'ingat' : 'netral'">
          <template #ikon><Ikon nama="antre" /></template>
        </UbinAngka>

        <UbinAngka :angka="saldo.tersedia ?? 0" label="Dapat diajukan" nada="baik">
          <template #ikon><Ikon nama="sisa" /></template>
        </UbinAngka>
      </div>
    </section>

    <!-- ── Kontrak & slip ── -->
    <div class="grid gap-4 lg:grid-cols-2">
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
        <header class="flex items-center justify-between gap-3">
          <h2 class="text-[13px] font-bold">Kontrak saya</h2>
          <Link href="/hris/saya/kontrak" class="text-[11.5px] text-sky-700 hover:underline">
            Riwayat
          </Link>
        </header>

        <div v-if="kontrak" class="mt-3">
          <div class="text-[13px] font-semibold">{{ kontrak.jenis }}</div>
          <div class="num text-[11.5px] text-stone-500 mt-0.5">{{ kontrak.nomor }}</div>

          <div class="num text-[12px] mt-2">
            {{ kontrak.mulai }} <span class="text-stone-400">–</span>
            {{ kontrak.selesai ?? 'tanpa batas' }}
          </div>

          <p v-if="kontrak.sisa !== null" class="text-[11.5px] mt-2"
             :class="kontrak.sisa <= 60 ? 'text-amber-700' : 'text-stone-500'">
            Berakhir {{ kontrak.sisa }} hari lagi.
          </p>
        </div>

        <p v-else class="mt-4 text-[12px] text-stone-400">
          Belum ada kontrak yang tercatat berjalan atas nama Anda.
        </p>
      </section>

      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
        <header class="flex items-center justify-between gap-3">
          <h2 class="text-[13px] font-bold">Slip gaji terakhir</h2>
          <Link href="/hris/saya/gaji" class="text-[11.5px] text-sky-700 hover:underline">
            Semua slip
          </Link>
        </header>

        <div v-if="slip" class="mt-3">
          <div class="text-[11.5px] text-stone-500">{{ slip.periode }}</div>
          <div class="num text-[22px] font-extrabold mt-0.5">{{ rupiah(slip.neto) }}</div>

          <p v-if="!slip.terkunci" class="text-[11.5px] text-amber-700 mt-2">
            Periodenya belum dikunci — angkanya masih dapat berubah, dan karena itu belum
            ditampilkan sebagai slip.
          </p>
        </div>

        <p v-else class="mt-4 text-[12px] text-stone-400">
          Belum ada slip gaji yang terbit atas nama Anda.
        </p>
      </section>
    </div>
  </div>
</template>

<style>
.sy-hari{display:inline-flex;flex-direction:column;align-items:center;justify-content:center;
  width:44px;padding:6px 0 7px;border-radius:.65rem;border:1px solid #F0EFED;background:#FAFAF9}
.sy-hari small{font-size:9.5px;color:#A8A29E;line-height:1}
.sy-hari strong{font-size:14px;font-weight:800;line-height:1.15;margin-top:2px;color:#1C1917}

.sy-kerja{background:#F4FCF8;border-color:#DDF3E8}
.sy-kerja strong{color:#047857}
.sy-libur{background:#FAFAF9;border-color:#F0EFED}
.sy-libur strong{color:#78716C}
.sy-cuti{background:#F5F9FE;border-color:#DCE9F7}
.sy-cuti strong{color:#1D4ED8}

:root[data-tema="gelap"] .sy-hari{background:#182125;border-color:#222E33}
:root[data-tema="gelap"] .sy-hari small{color:#93A1A8}
:root[data-tema="gelap"] .sy-hari strong{color:#E8EDEF}
:root[data-tema="gelap"] .sy-kerja{background:#142521;border-color:#1D3630}
:root[data-tema="gelap"] .sy-kerja strong{color:#8FE3BE}
:root[data-tema="gelap"] .sy-libur{background:#182125;border-color:#222E33}
:root[data-tema="gelap"] .sy-libur strong{color:#A8B2B8}
:root[data-tema="gelap"] .sy-cuti{background:#131F2B;border-color:#1D2E3F}
:root[data-tema="gelap"] .sy-cuti strong{color:#A8CDF0}
</style>

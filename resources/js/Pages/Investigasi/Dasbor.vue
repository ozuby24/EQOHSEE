<script setup lang="ts">
/**
 * Ringkasan Investigasi — "apa yang menunggu saya", bukan "berapa banyak".
 *
 * Bagian pertama halaman ini adalah daftar yang menuntut tindakan, dan
 * urutannya disengaja: yang melanggar tenggat regulasi lebih dahulu,
 * sesudahnya yang belum dinilai sama sekali, baru yang tertahan di
 * tengah tahap. Daftar yang diurutkan menurut tanggal menempatkan
 * pelanggaran hukum di bawah pekerjaan rutin.
 *
 * Angka besar tanpa tautan tidak dibuat. Kartu bertuliskan "24 insiden"
 * yang tidak dapat ditekan hanya memberi tahu bahwa datanya banyak;
 * yang dicari pembacanya selalu barisnya.
 */
import { Head, Link } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import { KEADAAN } from '../../Grafik/warna';

const props = propHalaman();

const WARNA_JENIS: Record<string, string> = {
  'terlambat-lapor': KEADAAN.gawat,
  'belum-triase':    KEADAAN.serius,
  'tahap-tertahan':  KEADAAN.ingat,
};

function tautan(b: any): string {
  if (b.tautan === 'triase')      return `/investigasi/insiden/${b.id}/triase`;
  if (b.tautan === 'investigasi') return `/investigasi/berkas/${b.id}`;
  return `/investigasi/insiden/${b.id}`;
}
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1400px] mx-auto space-y-5">
    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
      </div>

      <span class="inline-flex">
        <Link href="/investigasi/insiden/baru" class="eq-btn-utama">+ Lapor insiden</Link>
      </span>
    </section>

    <!-- ══════════ angka ringkas, tiap kartu dapat ditekan ══════════ -->
    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
      <Link v-for="k in [
              { label: 'Insiden tercatat',   nilai: props.ringkas?.insiden,        href: '/investigasi/insiden', warna: '#0F766E' },
              { label: 'Belum ditriase',     nilai: props.ringkas?.belumTriase,    href: '/investigasi/insiden?status=dilaporkan', warna: KEADAAN.serius },
              { label: 'Belum ada berkas',   nilai: props.ringkas?.tanpaBerkas,    href: '/investigasi/insiden?status=ditriase', warna: KEADAAN.ingat },
              { label: 'Investigasi berjalan', nilai: props.ringkas?.berjalan,     href: '/investigasi/berkas', warna: '#0F766E' },
              { label: 'Lewat tenggat lapor', nilai: props.ringkas?.terlambatLapor, href: '/investigasi/insiden', warna: KEADAAN.gawat },
            ]" :key="k.label" :href="k.href"
            class="rounded-2xl bg-white border border-stone-100 shadow-card px-4 py-3.5 hover:border-stone-200 transition">
        <p class="text-[10.5px] uppercase tracking-wide text-stone-400">{{ k.label }}</p>
        <p class="text-[26px] font-bold leading-none mt-1 num" :style="{ color: k.warna }">{{ k.nilai ?? 0 }}</p>
      </Link>
    </section>

    <div class="grid gap-4 lg:grid-cols-[1.25fr_.75fr] items-start">

      <!-- ══════════ perlu tindakan ══════════ -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <header class="px-5 py-3.5 border-b border-stone-100">
          <h3 class="text-[13.5px] font-bold text-cam-ink">
            Perlu tindakan <span class="font-normal text-stone-400">| {{ (props.perluTindakan ?? []).length }} butir</span>
          </h3>
          <p class="text-[11px] text-stone-500 mt-0.5">
            Diurut menurut mendesaknya, bukan menurut tanggal.
          </p>
        </header>

        <ul v-if="(props.perluTindakan ?? []).length" class="divide-y divide-stone-100">
          <li v-for="(b, i) in props.perluTindakan" :key="i">
            <Link :href="tautan(b)" class="flex items-start gap-3 px-5 py-3 hover:bg-stone-50/70 transition">
              <span class="mt-[6px] w-1.5 h-1.5 rounded-full shrink-0"
                    :style="{ background: WARNA_JENIS[b.jenis] ?? '#A8A29E' }"></span>

              <span class="min-w-0 flex-1">
                <span class="text-[12.5px] font-semibold text-cam-ink block truncate">{{ b.judul }}</span>
                <span class="text-[11px] text-stone-400 num">{{ b.nomor }}</span>
              </span>

              <span class="shrink-0 text-[10.5px] font-bold rounded-md px-2 py-1"
                    :style="{ background: (WARNA_JENIS[b.jenis] ?? '#A8A29E') + '1F', color: WARNA_JENIS[b.jenis] ?? '#78716C' }">
                {{ b.label }}
              </span>
            </Link>
          </li>
        </ul>

        <p v-else class="px-5 py-8 text-center text-[12px] text-stone-400">
          Tidak ada yang menunggu tindakan.
        </p>
      </section>

      <div class="grid gap-4">
        <!-- ══════════ sebaran per level ══════════ -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">
              Sebaran level <span class="font-normal text-stone-400">| hasil triase</span>
            </h3>
          </header>

          <ul class="px-5 py-4 grid gap-2.5">
            <li v-for="l in props.perLevel" :key="l.level" class="flex items-center gap-3">
              <span class="text-[11.5px] text-stone-500 w-32 shrink-0">{{ l.nama }}</span>
              <span class="flex-1 h-2 rounded-full bg-stone-100 overflow-hidden">
                <span class="block h-full rounded-full"
                      :style="{ width: ((l.jumlah / Math.max(1, props.ringkas?.insiden ?? 1)) * 100) + '%', background: '#0F766E' }"></span>
              </span>
              <span class="text-[12px] font-bold num w-6 text-right">{{ l.jumlah }}</span>
            </li>
          </ul>
        </section>

        <!-- ══════════ tahap berkas berjalan ══════════ -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">
              Berkas berjalan <span class="font-normal text-stone-400">| per tahap</span>
            </h3>
          </header>

          <ul class="px-5 py-4 grid gap-2">
            <li v-for="t in props.perTahap" :key="t.tahap" class="flex items-center justify-between gap-3">
              <span class="text-[11.5px] text-stone-500">{{ t.nama }}</span>
              <span class="text-[12px] font-bold num" :class="t.jumlah ? 'text-cam-ink' : 'text-stone-300'">
                {{ t.jumlah }}
              </span>
            </li>
          </ul>
        </section>
      </div>
    </div>

    <!-- ══════════ tindakan perbaikan yang lewat tenggat ══════════ -->
    <section v-if="(props.tindakanTelat ?? []).length"
             class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100">
        <h3 class="text-[13.5px] font-bold text-cam-ink">
          Tindakan perbaikan lewat tenggat
          <span class="font-normal text-stone-400">| {{ props.tindakanTelat.length }} tindakan</span>
        </h3>
        <p class="text-[11px] text-stone-500 mt-0.5">
          Tindakan yang menggantung membuat investigasinya tidak dapat ditutup.
        </p>
      </header>

      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[11.5px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
              <th class="px-4 py-2 font-semibold">No.</th>
              <th class="px-4 py-2 font-semibold">Tindakan</th>
              <th class="px-4 py-2 font-semibold">PIC</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Tenggat</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Telat</th>
              <th class="px-4 py-2 font-semibold">Berkas</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in props.tindakanTelat" :key="t.id" class="border-b border-stone-100">
              <td class="px-4 py-2.5 num text-stone-400 whitespace-nowrap">{{ t.nomor }}</td>
              <td class="px-4 py-2.5 text-cam-ink">{{ t.uraian }}</td>
              <td class="px-4 py-2.5 text-stone-500 whitespace-nowrap">{{ t.pic || '—' }}</td>
              <td class="px-4 py-2.5 num text-stone-500 whitespace-nowrap">{{ t.tenggat }}</td>
              <td class="px-4 py-2.5 num font-bold whitespace-nowrap" :style="{ color: KEADAAN.gawat }">
                {{ t.telat }} hari
              </td>
              <td class="px-4 py-2.5 whitespace-nowrap">
                <Link :href="`/investigasi/berkas/${t.invId}`"
                      class="text-cam-lime-deep font-bold hover:underline num">{{ t.invNo }}</Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

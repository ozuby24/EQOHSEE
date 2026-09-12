<script setup lang="ts">
/** Seluruh tagihan beserta keadaannya — layar penjual. */
import { Head, Link, router } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';

const props = propHalaman();

function rupiah(n: number) {
  return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
}

function saring(status: string) {
  router.get('/pembelian/tagihan', status ? { status } : {},
    { preserveState: true, preserveScroll: true });
}

/* Warna mengikuti ARTINYA, bukan urutan status. Lunas hijau, yang
   menunggu tindakan jingga, yang mati abu — supaya daftar panjang
   dapat dipindai dengan mata tanpa membaca tiap katanya. */
const WARNA: Record<string, string> = {
  lunas: '#16A34A',
  menunggu_verifikasi: '#EA580C',
  menunggu_bayar: '#B45309',
  ditolak: '#DC2626',
  batal: '#78716C',
  kedaluwarsa: '#78716C',
  draf: '#78716C',
};
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1400px] mx-auto space-y-5">
    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
      </div>
      <Link href="/pembelian" class="eq-btn-utama">Buat tagihan</Link>
    </section>

    <section class="grid gap-3 sm:grid-cols-3">
      <div v-for="r in (props.ringkas ?? [])" :key="r[0]"
           class="rounded-2xl bg-white border border-stone-100 shadow-card px-5 py-4">
        <p class="text-[24px] font-bold leading-none num"
           :style="{ color: r[2] === 'ingat' ? '#EA580C' : r[2] === 'baik' ? '#16A34A' : '#44403C' }">
          {{ r[1] }}
        </p>
        <p class="text-[11.5px] text-stone-500 mt-1.5">{{ r[0] }}</p>
      </div>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3 border-b border-stone-100 flex flex-wrap items-center gap-2">
        <h3 class="text-[13.5px] font-bold text-cam-ink mr-auto">
          Tagihan <span class="font-normal text-stone-400">| {{ (props.baris ?? []).length }} data</span>
        </h3>
        <select :value="props.saring?.status ?? ''" aria-label="Saring status"
                class="beli-isian" @change="saring(($event.target as HTMLSelectElement).value)">
          <option value="">Semua status</option>
          <option v-for="(label, k) in (props.opsiStatus ?? {})" :key="k" :value="k">{{ label }}</option>
        </select>
      </header>

      <div class="overflow-x-auto">
        <table v-if="(props.baris ?? []).length" class="w-full text-[12.5px]">
          <thead class="text-left text-[11px] uppercase tracking-wide text-stone-500"
                 style="background:#F6EEDF">
            <tr>
              <th class="px-4 py-3">Nomor</th>
              <th class="px-4 py-3">Pembeli</th>
              <th class="px-4 py-3">Butir</th>
              <th class="px-4 py-3">Total</th>
              <th class="px-4 py-3">Tanggal</th>
              <th class="px-4 py-3">Keadaan</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100">
            <tr v-for="b in props.baris" :key="b.id" class="hover:bg-stone-50">
              <td class="px-4 py-3">
                <Link :href="`/pembelian/tagihan/${b.id}`"
                      class="num font-semibold hover:underline" style="color:#0F766E">{{ b.nomor }}</Link>
              </td>
              <td class="px-4 py-3">
                {{ b.pembeli }}
                <span v-if="b.perusahaan" class="block text-[11px] text-stone-400">{{ b.perusahaan }}</span>
              </td>
              <td class="px-4 py-3 num text-stone-500">{{ b.butir }}</td>
              <td class="px-4 py-3 num font-semibold">{{ rupiah(b.total) }}</td>
              <td class="px-4 py-3 num text-stone-500">{{ b.tanggal }}</td>
              <td class="px-4 py-3">
                <span class="rounded-md px-2 py-1 text-[11px] font-bold"
                      :style="{ background: (WARNA[b.status] ?? '#78716C') + '1F',
                                color: WARNA[b.status] ?? '#78716C' }">
                  {{ b.statusLabel }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>

        <p v-else class="px-5 py-6 text-[12.5px] text-stone-400">Belum ada tagihan.</p>
      </div>
    </section>
  </div>
</template>

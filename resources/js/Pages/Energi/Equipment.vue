<script setup lang="ts">
/**
 * Equipment Energy Performance — peringkat keborosan tiap unit.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import KartuKpi from '../../Components/KartuKpi.vue';
import KepalaEnergi from '../../Components/KepalaEnergi.vue';
import { angka } from '../../energi';
import type { HalamanEnergiEquipment } from '../../types';

const props = defineProps<HalamanEnergiEquipment>();

function saring(kategori: string | null) {
  useForm({ dari: props.dari, sampai: props.sampai, kategori: kategori ?? '' })
    .transform((d) => (d.kategori ? d : { dari: d.dari, sampai: d.sampai }))
    .get(props.tautan.equipment, { preserveState: true, preserveScroll: true, replace: true });
}

function bilah(b: (typeof props.peringkat)[number]): number {
  if (b.acuan <= 0) return 0;
  return Math.max(4, Math.min(100, (b.lHm / Math.max(b.acuan * 1.5, 1e-9)) * 100));
}
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-5xl mx-auto space-y-5">

    <KepalaEnergi judul="Equipment Energy Performance"
        ket="Peringkat keborosan tiap unit. Yang dinilai adalah selisihnya terhadap rata-rata
             kelompoknya sendiri — mengurutkan liter mentah hanya akan selalu menempatkan alat
             bertenaga besar di puncak daftar, dan itu tidak memberi tahu apa-apa."
        :dari="dari" :sampai="sampai" :rute="tautan.equipment">

      <div class="flex flex-wrap gap-2 mt-6 pt-5 hairline border-b-0">
        <button type="button" @click="saring(null)"
                class="text-[11.5px] font-bold px-3.5 py-1.5 rounded-xl transition"
                :class="kategori ? 'bg-stone-100 text-stone-500 hover:bg-stone-200' : 'bg-cam-ink text-white'">
          Semua
        </button>
        <button v-for="(nama, kode) in opsi.kategori" :key="kode" type="button" @click="saring(kode)"
                class="text-[11.5px] font-bold px-3.5 py-1.5 rounded-xl transition"
                :class="kategori === kode ? 'bg-cam-ink text-white' : 'bg-stone-100 text-stone-500 hover:bg-stone-200'">
          {{ nama }}
        </button>
      </div>
    </KepalaEnergi>

    <div v-if="perKategori.length" class="grid gap-4 grid-cols-2 lg:grid-cols-4">
      <KartuKpi v-for="k in perKategori" :key="k.kode" :label="k.nama" :nilai="angka(k.l_hm, 2)" satuan="L/HM"
                :ket="`${angka(k.liter)} L · ${angka(k.hm, 1)} jam operasi`"
                :warna="kategori === k.kode ? '#E2663A' : '#F57C00'" />
    </div>

    <section class="kartu-lux rounded-2xl p-6">
      <div class="flex items-baseline justify-between gap-3">
        <h3 class="font-display text-[16px] font-black text-cam-ink">
          Daftar Unit{{ kategori ? ' — ' + (opsi.kategori[kategori] ?? kategori) : '' }}
        </h3>
        <span class="text-[11.5px] text-stone-400 shrink-0">{{ peringkat.length }} unit beroperasi</span>
      </div>

      <div v-if="peringkat.length" class="grid gap-3 sm:grid-cols-2 mt-5">
        <Link v-for="b in peringkat" :key="b.id" :href="b.url" class="kartu-lux rounded-2xl p-4 block card-hover">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <div class="text-[13px] font-bold text-cam-ink truncate">{{ b.kode }}</div>
              <div class="text-[11px] text-stone-500 truncate">{{ b.nama }}</div>
            </div>
            <span class="shrink-0 text-[9.5px] font-bold uppercase tracking-wide text-white px-2 py-1 rounded-lg whitespace-nowrap"
                  :style="{ background: b.status.warna }">{{ b.status.label }}</span>
          </div>

          <div class="grid grid-cols-3 gap-2 mt-4">
            <div>
              <div class="num text-[14px] font-bold" :style="{ color: b.status.warna }">{{ angka(b.lHm, 2) }}</div>
              <div class="text-[10px] text-stone-400 mt-0.5">L/HM</div>
            </div>
            <div>
              <div class="num text-[14px] font-bold text-cam-ink">{{ angka(b.liter) }}</div>
              <div class="text-[10px] text-stone-400 mt-0.5">Liter</div>
            </div>
            <div>
              <div class="num text-[14px] font-bold text-cam-ink">{{ angka(b.hm, 1) }}</div>
              <div class="text-[10px] text-stone-400 mt-0.5">HM</div>
            </div>
          </div>

          <template v-if="b.acuan > 0">
            <div class="mt-3 h-1.5 rounded-full bg-stone-100 overflow-hidden">
              <div class="h-full rounded-full transition-all duration-700"
                   :style="{ width: bilah(b) + '%', background: b.status.warna }"></div>
            </div>
            <div class="text-[10px] text-stone-400 mt-1.5">
              Acuan {{ b.kategori }}: <span class="num">{{ angka(b.acuan, 2) }}</span> L/HM
            </div>
          </template>
        </Link>
      </div>
      <p v-else class="text-[12px] text-stone-400 mt-6">
        Belum ada catatan bahan bakar pada rentang ini{{ kategori ? ' untuk kategori tersebut' : '' }}.
      </p>
    </section>

  </div>
</template>

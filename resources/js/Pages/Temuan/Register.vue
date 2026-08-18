<script setup lang="ts">
/**
 * Register temuan lintas modul.
 *
 * Urutannya adalah isi laporannya: yang lewat tenggat lebih dulu, lalu
 * yang tidak ditangani siapa pun, lalu prioritas tertinggi. Yang di atas
 * adalah yang paling menuntut hari ini.
 *
 * "Tanpa penanggung jawab" sengaja diberi tanda sendiri dan bukan sekadar
 * kolom kosong. Dalam daftar biasa temuan tak bertuan terlihat sama persis
 * dengan temuan yang sedang ditangani perlahan — keduanya berstatus
 * terbuka — dan kemiripan itulah yang membuatnya bertahan berbulan-bulan.
 */
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { HalamanRegisterTemuan } from '../../types';

const props = defineProps<HalamanRegisterTemuan>();

const NADA: Record<string, { label: string; teks: string; titik: string }> = {
  kritis: { label: 'Kritis', teks: 'text-red-700',     titik: 'bg-red-500' },
  tinggi: { label: 'Tinggi', teks: 'text-amber-800',   titik: 'bg-amber-500' },
  sedang: { label: 'Sedang', teks: 'text-stone-600',   titik: 'bg-stone-400' },
  rendah: { label: 'Rendah', teks: 'text-stone-500',   titik: 'bg-stone-300' },
};

const nada = (p: string) => NADA[p] ?? NADA.sedang;

const namaModul = (m: string) =>
  m.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

const kartu = computed(() => [
  { kunci: 'terbuka',    nilai: props.ringkas.terbuka,    label: 'Masih terbuka',  warna: '#F57C00' },
  { kunci: 'terlambat',  nilai: props.ringkas.terlambat,  label: 'Lewat tenggat',  warna: '#DC2626' },
  { kunci: 'takBertuan', nilai: props.ringkas.takBertuan, label: 'Tanpa penanggung jawab', warna: '#B45309' },
  { kunci: 'selesai',    nilai: props.ringkas.selesai,    label: 'Sudah selesai',  warna: '#15803D' },
]);
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-5xl mx-auto space-y-5">

    <section class="brand-gradient rounded-2xl p-6 text-white shadow-card relative overflow-hidden">
      <div class="absolute -right-12 -top-16 w-56 h-56 rounded-full"
           style="background:radial-gradient(circle,rgba(42,157,143,.4),transparent 70%)"></div>

      <div class="relative">
        <p class="text-[10.5px] font-bold uppercase tracking-[.18em] text-white/55">Lintas Modul</p>
        <h2 class="font-display text-[22px] font-black mt-1.5">Register Temuan</h2>
        <p class="text-[12.5px] text-white/70 mt-2 max-w-2xl leading-relaxed">
          Temuan dari laporan bahaya, butir inspeksi, audit SMKP, keselamatan operasi, dan
          tindak lanjut sepuluh modul lainnya — dibaca dalam satu bentuk. Angkanya tidak
          disalin ke mana pun; tiap baris tetap dimiliki modul asalnya.
        </p>

        <div class="grid gap-3 grid-cols-2 lg:grid-cols-4 mt-5">
          <div v-for="k in kartu" :key="k.kunci" class="glass rounded-xl px-3.5 py-2.5">
            <div class="num stat stat-sm text-white">{{ k.nilai }}</div>
            <div class="text-[10.5px] text-white/60 mt-1">{{ k.label }}</div>
          </div>
        </div>
      </div>
    </section>

    <!-- Saringan -->
    <div class="flex flex-wrap items-center gap-2">
      <Link v-for="o in opsi" :key="o.nilai"
            :href="`${tautan.register}?saring=${o.nilai}`"
            preserve-scroll
            class="text-[11.5px] font-bold px-3.5 py-1.5 rounded-xl transition"
            :class="saring === o.nilai
              ? 'bg-cam-ink text-white'
              : 'bg-stone-100 text-stone-500 hover:bg-stone-200'">
        {{ o.label }}
      </Link>

      <span class="ml-auto text-[11.5px] text-stone-400">
        {{ temuan.length }} baris ditampilkan
      </span>
    </div>

    <!-- Sebaran per modul -->
    <section v-if="perModul.length" class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[13.5px] font-bold text-cam-ink">Temuan terbuka per modul</h3>
      <div class="flex flex-wrap gap-2 mt-3">
        <span v-for="m in perModul" :key="m.modul"
              class="inline-flex items-center gap-2 rounded-lg bg-stone-100 px-2.5 py-1.5
                     text-[11.5px] font-semibold text-stone-600">
          {{ namaModul(m.modul) }}
          <span class="num rounded bg-white px-1.5 text-[11px] font-bold text-cam-ink">{{ m.jumlah }}</span>
        </span>
      </div>
    </section>

    <!-- Daftar -->
    <section class="space-y-2.5">
      <article v-for="(t, i) in temuan" :key="t.sumber + t.kode + i"
               class="rounded-2xl border shadow-card p-4"
               :class="t.terlambat ? 'border-red-100 bg-red-50'
                     : !t.bertuan && t.terbuka ? 'border-amber-100 bg-amber-50'
                     : 'border-stone-100 bg-white'">
        <div class="flex items-start gap-3">
          <span class="w-2.5 h-2.5 rounded-full mt-1.5 shrink-0" :class="nada(t.prioritas).titik"></span>

          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-baseline gap-x-2.5 gap-y-1">
              <span class="text-[13.5px] font-bold text-cam-ink">{{ t.judul }}</span>

              <span class="text-[9.5px] font-bold uppercase tracking-wide bg-stone-100
                           text-stone-600 px-1.5 py-0.5 rounded">
                {{ namaModul(t.modul) }}
              </span>

              <span class="num text-[10.5px] text-stone-400">{{ t.kode }}</span>

              <span class="text-[11px] font-bold" :class="nada(t.prioritas).teks">
                {{ nada(t.prioritas).label }}
              </span>
            </div>

            <p v-if="t.uraian" class="text-[12px] text-stone-600 mt-1.5 leading-relaxed">{{ t.uraian }}</p>

            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-2 text-[11.5px]">
              <span v-if="t.penanggungJawab" class="text-stone-600">
                <span class="text-stone-400">PJ:</span> {{ t.penanggungJawab }}
              </span>

              <span v-if="t.targetSelesai" class="num"
                    :class="t.terlambat ? 'font-bold text-red-700' : 'text-stone-500'">
                Target {{ t.targetSelesai }}
                <template v-if="t.terlambat"> · terlambat {{ t.hariTerlambat }} hari</template>
              </span>

              <span v-if="!t.bertuan && t.terbuka" class="font-bold text-amber-800">
                Belum ada penanggung jawab &amp; tenggat
              </span>

              <span v-if="!t.terbuka" class="font-semibold text-emerald-700">
                {{ t.status === 'selesai' ? 'Selesai' : 'Dibatalkan' }}
              </span>
            </div>
          </div>
        </div>
      </article>

      <div v-if="!temuan.length"
           class="rounded-2xl border border-emerald-100 bg-emerald-50 px-5 py-12 text-center">
        <div class="text-[14px] font-bold text-emerald-800">Tidak ada yang cocok dengan saringan ini</div>
        <p class="text-[12px] text-emerald-700 mt-1.5">
          Coba saringan lain, atau muat data contoh dari Pusat Kendali untuk melihat bentuknya.
        </p>
      </div>
    </section>

    <p class="text-[11.5px] text-stone-400 leading-relaxed max-w-3xl">
      Laporan bahaya dan butir inspeksi belum punya kolom penanggung jawab maupun tenggat,
      sehingga keduanya selalu tampil sebagai belum bertuan. Itu bukan cara membacanya di
      halaman ini, melainkan keadaan sebenarnya — keduanya memang belum dapat ditugaskan
      kepada siapa pun di aplikasi ini.
    </p>

  </div>
</template>

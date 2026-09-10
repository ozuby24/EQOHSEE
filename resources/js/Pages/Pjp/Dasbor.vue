<script setup lang="ts">
/**
 * Ringkasan pemantauan perusahaan jasa.
 *
 * Dua daftar mendahului angka apa pun, dan urutannya disengaja: yang
 * menunggak laporan bulanannya lebih dahulu — itu kewajiban dengan
 * tanggal, dan keterlambatannya dapat ditanyakan Inspektur Tambang —
 * baru mitra yang skornya rendah, yang menuntut pembinaan alih-alih
 * teguran.
 *
 * Angka besar tanpa tautan tidak dibuat. Kartu bertuliskan "14 mitra"
 * yang tidak dapat ditekan hanya memberi tahu bahwa datanya banyak;
 * yang dicari pembacanya selalu barisnya.
 */
import { Head, Link } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import { KEADAAN } from '../../Grafik/warna';

const props = propHalaman();

/** Warna satu skor achievement — hijau aman, jingga waspada, merah jatuh. */
function nada(skor: number | null): string {
  if (skor === null || skor === undefined) return KEADAAN.netral;
  if (skor >= 80) return KEADAAN.baik;
  if (skor >= 55) return KEADAAN.ingat;
  return KEADAAN.gawat;
}
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1400px] mx-auto space-y-5">
    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-cam-ink">{{ props.judul }}</h2>
        <p class="text-[12.5px] text-stone-500 mt-0.5">{{ props.subjudul }}</p>
      </div>

      <span class="inline-flex gap-2">
        <Link href="/pjp/daftar" class="eq-btn-lain">Daftar mitra</Link>
        <Link href="/pjp/baru" class="eq-btn-utama">+ Tambah mitra</Link>
      </span>
    </section>

    <!-- ══════════ angka ringkas, tiap kartu dapat ditekan ══════════ -->
    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
      <Link v-for="k in [
              { label: 'Mitra terdaftar',      nilai: props.ringkas?.total,             href: '/pjp/daftar', warna: '#0F766E' },
              { label: 'Aktif dipantau',       nilai: props.ringkas?.aktif,             href: '/pjp/daftar?status=aktif', warna: KEADAAN.baik },
              { label: 'Perlu tindak lanjut',  nilai: props.ringkas?.perluTindakLanjut, href: '/pjp/daftar?status=perlu_tindak_lanjut', warna: KEADAAN.serius },
              { label: 'Tidak aktif',          nilai: props.ringkas?.tidakAktif,        href: '/pjp/daftar?status=tidak_aktif', warna: KEADAAN.netral },
              { label: 'Skor di bawah ambang', nilai: props.ringkas?.perluPerhatian,    href: '/pjp/daftar', warna: KEADAAN.gawat },
            ]" :key="k.label" :href="k.href"
            class="rounded-2xl bg-white border border-stone-100 shadow-card px-4 py-3.5 hover:border-stone-200 transition">
        <p class="text-[10.5px] uppercase tracking-wide text-stone-400">{{ k.label }}</p>
        <p class="text-[26px] font-bold leading-none mt-1 num" :style="{ color: k.warna }">{{ k.nilai ?? 0 }}</p>
      </Link>
    </section>

    <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3 items-start">

      <!-- ══════════ menunggak laporan bulanan ══════════ -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <header class="px-5 py-3.5 border-b border-stone-100">
          <h3 class="text-[13.5px] font-bold text-cam-ink">
            Laporan bulanan menunggak
            <span class="font-normal text-stone-400">| {{ (props.menunggak ?? []).length }} mitra</span>
          </h3>
          <p class="text-[11px] text-stone-500 mt-0.5">
            Batas kirim tanggal {{ props.batasTanggal }} tiap bulan. Daftar ini kosong
            selama tanggal batas belum terlewati.
          </p>
        </header>

        <ul v-if="(props.menunggak ?? []).length" class="divide-y divide-stone-100">
          <li v-for="m in props.menunggak" :key="m.id">
            <Link :href="`/pjp/${m.id}`" class="flex items-center gap-3 px-5 py-3 hover:bg-stone-50/70 transition">
              <span class="w-1.5 h-1.5 rounded-full shrink-0" :style="{ background: KEADAAN.gawat }" />
              <span class="text-[12.5px] text-cam-ink flex-1 min-w-0 truncate">{{ m.nama_perusahaan }}</span>
              <span class="text-[11px] text-stone-400 shrink-0">belum diterima</span>
            </Link>
          </li>
        </ul>

        <p v-else class="px-5 py-10 text-center text-[12px] text-stone-400">
          Tidak ada yang menunggak bulan ini.
        </p>
      </section>

      <!-- ══════════ skor terendah ══════════ -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <header class="px-5 py-3.5 border-b border-stone-100">
          <h3 class="text-[13.5px] font-bold text-cam-ink">
            Paling perlu perhatian
            <span class="font-normal text-stone-400">| {{ (props.perluPerhatian ?? []).length }} mitra</span>
          </h3>
          <p class="text-[11px] text-stone-500 mt-0.5">
            Angkanya diambil dari skor TERENDAH di antara prakualifikasi, pelaporan, dan
            evaluasi — bukan rata-ratanya.
          </p>
        </header>

        <ul v-if="(props.perluPerhatian ?? []).length" class="divide-y divide-stone-100">
          <li v-for="m in props.perluPerhatian" :key="m.id">
            <Link :href="`/pjp/${m.id}`" class="flex items-center gap-3 px-5 py-3 hover:bg-stone-50/70 transition">
              <span class="text-[12.5px] text-cam-ink flex-1 min-w-0 truncate">{{ m.nama }}</span>

              <span class="w-24 h-1.5 rounded-full bg-stone-100 overflow-hidden shrink-0">
                <span class="block h-full rounded-full"
                      :style="{ width: Math.max(2, Math.min(100, m.achievement)) + '%', background: nada(m.achievement) }" />
              </span>

              <span class="text-[12px] font-bold num w-12 text-right shrink-0"
                    :style="{ color: nada(m.achievement) }">{{ m.achievement }}</span>
            </Link>
          </li>
        </ul>

        <p v-else class="px-5 py-10 text-center text-[12px] text-stone-400">
          Tidak ada mitra di bawah ambang.
        </p>
      </section>

      <!-- ══════════ dokumen menunggu dinilai ══════════ -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <header class="px-5 py-3.5 border-b border-stone-100">
          <h3 class="text-[13.5px] font-bold text-cam-ink">
            Menunggu dinilai
            <span class="font-normal text-stone-400">| {{ (props.belumDinilai ?? []).length }} dokumen</span>
          </h3>
          <p class="text-[11px] text-stone-500 mt-0.5">
            Sudah diunggah, kesesuaian isinya belum diperiksa siapa pun.
          </p>
        </header>

        <ul v-if="(props.belumDinilai ?? []).length" class="divide-y divide-stone-100">
          <li v-for="(d, i) in props.belumDinilai" :key="i">
            <Link :href="`/pjp/${d.id}`" class="flex items-start gap-3 px-5 py-3 hover:bg-stone-50/70 transition">
              <span class="flex-1 min-w-0">
                <span class="block text-[12.5px] text-cam-ink truncate">{{ d.mitra }}</span>
                <span class="block text-[11px] text-stone-500 truncate">
                  {{ d.jenis }}<template v-if="d.periode"> · {{ d.periode }}</template>
                </span>
              </span>

              <span class="rounded px-1.5 py-0.5 text-[10px] font-bold shrink-0"
                    :style="{ background: (d.tepat_waktu ? KEADAAN.baik : KEADAAN.gawat) + '1F',
                              color: d.tepat_waktu ? KEADAAN.baik : KEADAAN.gawat }">
                {{ d.tepat_waktu ? 'Tepat waktu' : 'Terlambat' }}
              </span>
            </Link>
          </li>
        </ul>

        <p v-else class="px-5 py-10 text-center text-[12px] text-stone-400">
          Semua dokumen sudah dinilai.
        </p>
      </section>
    </div>
  </div>
</template>

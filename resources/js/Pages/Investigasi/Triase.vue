<script setup lang="ts">
/**
 * Triase — matriks 5×5 yang diklik langsung di layar.
 *
 * MENGAPA MATRIKS, BUKAN DUA MENU PILIH. Angka yang dipilih dari menu
 * tidak memperlihatkan akibatnya sampai formulirnya disimpan. Matriks
 * memperlihatkan seluruh petanya sekaligus: yang memilih melihat sel
 * mana yang sedang ia tunjuk, sel mana yang di sebelahnya, dan di mana
 * batas antara L2 dan L3 berada. Keputusan "seberapa serius ini"
 * menjadi keputusan yang dapat dibantah orang lain — dan itulah seluruh
 * gunanya matriks.
 *
 * KEPARAHAN POTENSIAL berdiri sendiri di samping keparahan nyata, dan
 * itu medan yang paling mudah dikira mubazir. Unit yang lepas kendali
 * lalu berhenti satu meter dari pekerja tidak melukai siapa pun —
 * keparahan nyatanya 1, potensinya fatal. Yang dipakai matriks adalah
 * yang tertinggi di antara keduanya.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { propHalaman } from '../../halaman';
import { KEADAAN } from '../../Grafik/warna';

const props = propHalaman();

const f = useForm<Record<string, any>>({
  kemungkinan: props.insiden?.kemungkinan ?? null,
  keparahan: props.insiden?.keparahan ?? null,
  keparahan_potensial: props.insiden?.keparahanPotensial ?? null,
  klasifikasi_regulasi_id: '',
  k1_benar_terjadi: false, k2_mencederai_pekerja: false, k3_akibat_kegiatan: false,
  k4_jam_kerja: false, k5_wilayah_usaha: false,
});

const WARNA_PITA: Record<string, string> = {
  rendah: '#15803D', sedang: '#B45309', tinggi: '#C2410C', kritis: '#991B1B',
};

/** Keparahan yang benar-benar dipakai matriks: yang tertinggi. */
const keparahanDipakai = computed<number | null>(() => {
  const a = Number(f.keparahan) || 0;
  const b = Number(f.keparahan_potensial) || 0;
  const m = Math.max(a, b);
  return m >= 1 ? m : null;
});

function sel(k: number, p: number) {
  return (props.matriks ?? []).find((m: any) => m.kemungkinan === k && m.keparahan === p);
}

const hasil = computed(() => {
  if (!f.kemungkinan || !keparahanDipakai.value) return null;
  return sel(Number(f.kemungkinan), keparahanDipakai.value);
});

function pilih(k: number, p: number) {
  f.kemungkinan = k;

  /* Yang diklik adalah keparahan YANG DIPAKAI. Bila potensinya lebih
     tinggi daripada yang nyata, sel yang menyala tetap sel potensinya —
     dan menuliskannya balik ke `keparahan` akan diam-diam menaikkan
     keparahan nyata yang sudah diisi orang. */
  if (Number(f.keparahan_potensial) > p) return;
  f.keparahan = p;
}

function simpan() {
  f.post(`/investigasi/insiden/${props.insiden.id}/triase`, { preserveScroll: true });
}
</script>

<template>
  <Head title="Triase Insiden" />

  <div class="max-w-[1200px] mx-auto space-y-5">
    <section>
      <p class="text-[11.5px] text-stone-400 num">{{ props.insiden?.nomor }}</p>
      <h2 class="text-xl font-bold text-cam-ink">{{ props.insiden?.judul }}</h2>
      <p class="text-[12.5px] text-stone-500 mt-0.5">
        Triase menentukan level investigasi — dan level menentukan berapa tahap yang harus dilalui,
        metode analisis mana yang wajib, serta siapa yang menyetujui penutupannya.
      </p>
    </section>

    <form class="grid gap-4 lg:grid-cols-[1.15fr_.85fr] items-start" @submit.prevent="simpan">

      <!-- ══════════ matriks ══════════ -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <header class="px-5 py-3.5 border-b border-stone-100">
          <h3 class="text-[13.5px] font-bold text-cam-ink">
            Matriks risiko <span class="font-normal text-stone-400">| kemungkinan × keparahan</span>
          </h3>
          <p class="text-[11px] text-stone-500 mt-0.5">
            Tekan selnya. Baris = kemungkinan, kolom = keparahan yang dipakai.
          </p>
        </header>

        <div class="px-5 py-4 overflow-x-auto">
          <table class="text-[11px] border-separate" style="border-spacing:3px">
            <thead>
              <tr>
                <th class="w-24"></th>
                <th v-for="p in 5" :key="p" class="px-1 pb-1 font-semibold text-stone-400 align-bottom">
                  <span class="block text-[10px] leading-tight">{{ props.label?.keparahan?.[p] }}</span>
                  <span class="block num text-[10px]">{{ p }}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="k in 5" :key="k">
                <th class="pr-2 text-right font-semibold text-stone-400 whitespace-nowrap">
                  <span class="block text-[10px] leading-tight">{{ props.label?.kemungkinan?.[k] }}</span>
                  <span class="block num text-[10px]">{{ k }}</span>
                </th>

                <td v-for="p in 5" :key="p">
                  <button type="button"
                          class="w-full h-11 rounded-lg text-[11px] font-bold transition"
                          :style="{
                            background: WARNA_PITA[sel(k, p)?.pita] + (Number(f.kemungkinan) === k && keparahanDipakai === p ? 'FF' : '22'),
                            color: Number(f.kemungkinan) === k && keparahanDipakai === p ? '#fff' : WARNA_PITA[sel(k, p)?.pita],
                            outline: Number(f.kemungkinan) === k && keparahanDipakai === p ? '2px solid #0F766E' : 'none',
                          }"
                          @click="pilih(k, p)">
                    {{ sel(k, p)?.skor }}
                    <span class="block text-[9px] font-semibold opacity-80">{{ sel(k, p)?.level }}</span>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Keparahan potensial: medan tersendiri, dengan alasannya. -->
        <div class="px-5 pb-5">
          <label class="grid gap-1">
            <span class="block text-[10px] uppercase tracking-wide text-stone-400">Keparahan potensial</span>
            <select v-model="f.keparahan_potensial" class="rounded-lg border-stone-200 text-[12.5px]">
              <option :value="null">— sama dengan keparahan nyata</option>
              <option v-for="p in 5" :key="p" :value="p">{{ p }} · {{ props.label?.keparahan?.[p] }}</option>
            </select>
            <span class="text-[10.5px] text-stone-400">
              Isi bila akibatnya bisa jauh lebih berat dari yang benar-benar terjadi. Unit yang berhenti
              satu meter dari pekerja tidak melukai siapa pun — tetapi potensinya fatal, dan yang dipakai
              matriks adalah yang tertinggi di antara keduanya.
            </span>
          </label>
        </div>
      </section>

      <div class="grid gap-4">
        <!-- ══════════ hasilnya ══════════ -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">
              Hasil triase <span class="font-normal text-stone-400">| akibatnya pada alur</span>
            </h3>
          </header>

          <div v-if="hasil" class="px-5 py-4 grid gap-3">
            <div class="flex items-center gap-3">
              <span class="rounded-lg px-3 py-2 text-[18px] font-bold num"
                    :style="{ background: WARNA_PITA[hasil.pita] + '1F', color: WARNA_PITA[hasil.pita] }">
                {{ hasil.skor }}
              </span>
              <div class="min-w-0">
                <p class="text-[14px] font-bold" :style="{ color: WARNA_PITA[hasil.pita] }">
                  {{ props.opsi?.level?.[hasil.level] ?? hasil.level }}
                </p>
                <p class="text-[11px] text-stone-500 capitalize">Pita risiko {{ hasil.pita }}</p>
              </div>
            </div>

            <div>
              <p class="text-[10px] uppercase tracking-wide text-stone-400 mb-1">Metode analisis yang wajib</p>
              <div class="flex flex-wrap gap-1.5">
                <span v-for="m in (props.metode?.[hasil.level] ?? [])" :key="m"
                      class="rounded-md px-2 py-1 text-[10.5px] font-bold uppercase"
                      style="background:#F6EEDF;color:#0F766E">{{ m }}</span>
              </div>
            </div>

            <div>
              <p class="text-[10px] uppercase tracking-wide text-stone-400 mb-1">Penyetuju penutupan</p>
              <p class="text-[12px] font-semibold text-cam-ink">{{ props.penyetuju?.[hasil.level] }}</p>
            </div>
          </div>

          <p v-else class="px-5 py-8 text-center text-[12px] text-stone-400">
            Pilih satu sel matriks untuk melihat levelnya.
          </p>
        </section>

        <!-- ══════════ lima kriteria ══════════ -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">
              Kriteria kecelakaan tambang <span class="font-normal text-stone-400">| Kepdirjen 185/2019</span>
            </h3>
            <p class="text-[11px] text-stone-500 mt-0.5">
              Kelimanya harus terpenuhi bersamaan. Yang tidak terpenuhi disebut satu per satu —
              itu yang ditanyakan Inspektur Tambang.
            </p>
          </header>

          <div class="px-5 py-4 grid gap-2">
            <label v-for="(bunyi, kunci) in (props.kriteria ?? {})" :key="kunci"
                   class="flex items-start gap-2.5 text-[12px] cursor-pointer">
              <input v-model="f[kunci]" type="checkbox" class="mt-0.5 rounded border-stone-300">
              <span class="text-cam-ink">{{ bunyi }}</span>
            </label>
          </div>

          <div class="px-5 pb-5">
            <label class="grid gap-1">
              <span class="block text-[10px] uppercase tracking-wide text-stone-400">Klasifikasi menurut regulasi</span>
              <select v-model="f.klasifikasi_regulasi_id" class="rounded-lg border-stone-200 text-[12.5px]">
                <option value="">—</option>
                <option v-for="r in (props.opsi?.regulasi ?? [])" :key="r.id" :value="r.id">
                  {{ r.nama }}{{ r.wajib_lapor_kait ? ' · wajib lapor KaIT' : '' }}
                </option>
              </select>
              <!-- Kewajiban lapor mengikuti klasifikasi ini, BUKAN skor:
                   kejadian berbahaya tanpa korban pun wajib dilaporkan. -->
              <span class="text-[10.5px] text-stone-400">
                Yang menentukan wajib lapor adalah pilihan ini, bukan skor risikonya.
              </span>
            </label>
          </div>
        </section>

        <div class="flex items-center gap-3">
          <span class="inline-flex">
            <button class="eq-btn-utama" :disabled="f.processing || !hasil">Simpan triase</button>
          </span>
          <p v-if="!hasil" class="text-[11px] text-stone-400">Pilih sel matriksnya lebih dahulu.</p>
        </div>
      </div>
    </form>
  </div>
</template>

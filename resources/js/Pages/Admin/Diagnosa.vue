<script setup lang="ts">
/**
 * Diagnosa sistem.
 *
 * Yang ditampilkan di sini sengaja bukan lampu hijau-merah saja. Tiap
 * baris menyebut apa keadaannya, apa akibatnya bila dibiarkan, dan apa
 * langkahnya — sebab daftar merah tanpa akibat dan tanpa langkah hanya
 * melahirkan kebiasaan mengabaikan warna merah.
 *
 * Yang aman ditutup secara bawaan. Dua puluh baris hijau di atas satu
 * baris merah membuat yang merah itu ikut terbaca sebagai latar.
 */
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import BarisPeriksa from '../../Components/BarisPeriksa.vue';
import type { HalamanDiagnosa } from '../../types';

const props = defineProps<HalamanDiagnosa>();

const galat = computed<Record<string, string>>(() => (usePage().props as any).errors ?? {});

const tampilkanAman = ref(false);

const NADA: Record<string, { label: string; teks: string; latar: string; garis: string; titik: string }> = {
  gawat:     { label: 'Gawat',          teks: 'text-red-700',   latar: 'bg-red-50',    garis: 'border-red-100',   titik: 'bg-red-500' },
  perhatian: { label: 'Perlu perhatian', teks: 'text-amber-800', latar: 'bg-amber-50',  garis: 'border-amber-100', titik: 'bg-amber-500' },
  'tak-tahu':{ label: 'Tidak diketahui', teks: 'text-stone-600', latar: 'bg-stone-50',  garis: 'border-stone-200', titik: 'bg-stone-400' },
  aman:      { label: 'Aman',            teks: 'text-emerald-700', latar: 'bg-white',   garis: 'border-stone-100', titik: 'bg-emerald-500' },
};

const URUT = ['gawat', 'perhatian', 'tak-tahu', 'aman'];

const terlihat = computed(() =>
  props.hasil.filter((h) => tampilkanAman.value || h.keadaan !== 'aman'),
);

const perluTindakan = computed(() =>
  props.hasil.filter((h) => h.keadaan === 'gawat' || h.keadaan === 'perhatian').length,
);

/* Kesesuaian memakai saringan yang sama. "Belum dinilai" sengaja ikut
   terlihat walau bukan merah: modul yang kosong bukan modul yang sehat,
   ia hanya modul yang belum membuktikan apa pun. */
const sesuaiTerlihat = computed(() =>
  props.sesuai.filter((h) => tampilkanAman.value || h.keadaan !== 'aman'),
);

const sesuaiPerluTindakan = computed(() =>
  props.sesuai.filter((h) => h.keadaan !== 'aman').length,
);

/* ── aksi ── */

const perbaikanForm = useForm({});

function perbaiki(p: HalamanDiagnosa['perbaikan'][number]) {
  if (p.berat && !confirm(`${p.label}\n\n${p.ket}\n\nJalankan sekarang?`)) return;

  perbaikanForm.post(p.url, { preserveScroll: true });
}

const jawabanAi = computed<string | null>(() => (usePage().props.kilat as any)?.aiJawaban ?? null);
const tanyaForm = useForm({ pertanyaan: '' });

function tanyaAi() {
  tanyaForm.post(props.ai.url, { preserveScroll: true });
}

function periksaUlang() {
  router.reload({ only: ['hasil', 'ringkas', 'sesuai', 'ringkasSesuai', 'dijalankan'] });
}
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-5xl mx-auto space-y-5">

    <section class="brand-gradient rounded-2xl p-6 text-white shadow-card relative overflow-hidden">
      <div class="absolute -right-20 -top-20 w-56 h-56 rounded-full bg-cam-lime/20 blur-3xl"></div>
      <div class="relative">
        <div class="flex items-start justify-between gap-4 flex-wrap">
          <div>
            <span class="text-[10px] font-bold uppercase tracking-[0.22em] text-cam-lime-light">
              Pemeriksaan Mandiri
            </span>
            <h2 class="stat mt-1.5">{{ judul }}</h2>
            <p class="text-[12px] text-white/70 mt-1.5 max-w-xl leading-relaxed">
              {{ subjudul }} — mode debug yang tertinggal menyala, migrasi yang belum jalan,
              tautan berkas yang putus. Semuanya membuat sistem tetap terlihat sehat.
            </p>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <button type="button" @click="periksaUlang"
                    class="glass rounded-xl px-3.5 py-2 text-[11.5px] font-bold text-white
                           hover:bg-white/20 transition">
              Periksa ulang
            </button>
            <Link :href="tautan.sistem"
                  class="glass rounded-xl px-3.5 py-2 text-[11.5px] font-bold text-white
                         hover:bg-white/20 transition">
              Pusat Kendali
            </Link>
          </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-5">
          <div v-for="k in URUT" :key="k" class="glass rounded-xl px-3.5 py-2.5">
            <div class="flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full" :class="NADA[k].titik"></span>
              <span class="text-[9px] uppercase tracking-[0.12em] text-white/62 font-bold">
                {{ NADA[k].label }}
              </span>
            </div>
            <div class="stat stat-sm text-white mt-1 num">{{ ringkas[k] ?? 0 }}</div>
          </div>
        </div>

        <p class="text-[10.5px] text-white/55 mt-3">Diperiksa {{ dijalankan }}</p>
      </div>
    </section>

    <div v-if="galat.perbaikan"
         class="rounded-2xl border border-red-100 bg-red-50 px-5 py-4 text-[12.5px]
                text-red-700 leading-relaxed">
      {{ galat.perbaikan }}
    </div>

    <!-- Hasil pemeriksaan -->
    <section class="space-y-2.5">
      <div class="flex items-center justify-between">
        <h3 class="text-[14px] font-bold text-cam-ink">
          {{ perluTindakan }} dari {{ hasil.length }} pemeriksaan perlu ditindak
        </h3>
        <label class="flex items-center gap-2 text-[11.5px] font-semibold text-stone-500 cursor-pointer">
          <input type="checkbox" v-model="tampilkanAman"
                 class="rounded border-stone-300 text-cam-lime-deep focus:ring-cam-lime" />
          Tampilkan yang aman
        </label>
      </div>

      <BarisPeriksa v-for="h in terlihat" :key="h.kode" :baris="h" />

      <div v-if="!terlihat.length"
           class="rounded-2xl border border-emerald-100 bg-emerald-50 px-5 py-10 text-center">
        <div class="text-[14px] font-bold text-emerald-800">Tidak ada yang perlu ditindak</div>
        <p class="text-[12px] text-emerald-700 mt-1.5">
          Seluruh {{ hasil.length }} pemeriksaan lolos. Centang "Tampilkan yang aman" untuk melihat rinciannya.
        </p>
      </div>
    </section>

    <!--
      Kesesuaian angka.

      Dipisahkan dari diagnosa karena pertanyaannya berbeda: diagnosa
      menanyakan apakah sistemnya sehat, bagian ini menanyakan apakah
      angka yang dipajangnya benar. Sistem dapat sehat sempurna sementara
      skor yang ditampilkannya keliru — dan kekeliruan macam itu tidak
      pernah memunculkan galat, hanya memunculkan angka.
    -->
    <section class="space-y-2.5 pt-2">
      <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h3 class="text-[14px] font-bold text-cam-ink">
          Kesesuaian isi &amp; penilaian tiap modul
        </h3>
        <span class="text-[11.5px] font-semibold text-stone-500">
          {{ sesuaiPerluTindakan }} dari {{ sesuai.length }} perlu diperiksa
        </span>
      </div>

      <p class="text-[11.5px] text-stone-500 leading-relaxed max-w-3xl">
        Yang diperiksa di sini bukan kesehatan sistemnya melainkan kebenaran angkanya:
        apakah totalnya masih sama dengan jumlah rinciannya, apakah persentasenya masih
        di dalam 0–100, dan apakah modulnya cukup terisi sehingga skornya layak dibaca.
        Modul yang kosong ditandai <strong>belum dinilai</strong>, bukan aman — halaman
        kosong tidak pernah keliru, dan justru karena itu ia tidak membuktikan apa pun.
      </p>

      <BarisPeriksa v-for="h in sesuaiTerlihat" :key="'s-' + h.kode + h.judul" :baris="h" />

      <div v-if="!sesuaiTerlihat.length"
           class="rounded-2xl border border-emerald-100 bg-emerald-50 px-5 py-10 text-center">
        <div class="text-[14px] font-bold text-emerald-800">Angka tiap modul konsisten</div>
        <p class="text-[12px] text-emerald-700 mt-1.5">
          Seluruh {{ sesuai.length }} pemeriksaan kesesuaian lolos.
        </p>
      </div>
    </section>

    <!-- Pendamping AI -->
    <section v-if="ai.aktif" class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[14px] font-bold text-cam-ink mb-1">Tanya AI tentang temuan ini</h3>
      <p class="text-[11.5px] text-stone-500 leading-relaxed mb-3.5">
        Daftar temuan di atas dikirim ke asisten untuk diurutkan menurut yang paling mendesak.
        Yang dikirim hanya temuannya — bukan isi basis data, bukan <code>.env</code>, bukan kode.
        Ia menasihati dan tidak menjalankan apa pun.
      </p>

      <form class="flex flex-wrap gap-2" @submit.prevent="tanyaAi">
        <input v-model="tanyaForm.pertanyaan" maxlength="500"
               placeholder="Mana yang harus saya tangani lebih dulu, dan mengapa?"
               class="flex-1 min-w-[240px] rounded-xl border-stone-200 text-[12.5px]">
        <button type="submit" :disabled="tanyaForm.processing"
                class="rounded-xl bg-cam-lime-deep px-4 py-2 text-[11.5px] font-bold text-white
                       hover:brightness-95 transition disabled:opacity-40">
          {{ tanyaForm.processing ? 'Menanyakan…' : 'Tanya' }}
        </button>
      </form>

      <!-- Kegagalan AI DITAMPILKAN, tidak ditelan.

           Sebelumnya pesan galatnya dibuat di server lalu tidak pernah
           digambar di sini — hanya `galat.perbaikan` yang punya
           tempat. Kunci yang salah, kuota yang habis, dan nama model
           yang tidak dikenal karena itu terlihat persis sama: tombolnya
           kembali normal dan tidak ada apa-apa yang muncul. Tidak ada
           jalan bagi yang memakainya untuk mengetahui bahwa ada yang
           perlu diperbaiki, apalagi apa yang perlu diperbaiki. -->
      <div v-if="galat.ai"
           class="mt-4 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-[12.5px]
                  text-red-700 leading-relaxed">
        {{ galat.ai }}
        <Link :href="ai.atur" class="block mt-1.5 font-semibold underline">
          Periksa pengaturan Integrasi AI
        </Link>
      </div>

      <div v-if="jawabanAi"
           class="mt-4 rounded-xl border border-stone-200 bg-stone-50 p-4 text-[12.5px]
                  text-stone-700 leading-relaxed whitespace-pre-wrap">{{ jawabanAi }}</div>
    </section>

    <section v-else class="rounded-2xl border border-stone-200 bg-stone-50 px-5 py-4">
      <p class="text-[12px] text-stone-600 leading-relaxed">
        Asisten AI belum diaktifkan. Dengan kunci API sendiri, temuan di atas dapat diurutkan
        menurut yang paling mendesak —
        <Link :href="ai.atur" class="text-cam-lime-deep font-semibold hover:underline">atur di Integrasi AI</Link>.
      </p>
    </section>

    <!-- Perbaikan -->
    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[14px] font-bold text-cam-ink mb-1">Perbaikan</h3>
      <p class="text-[11.5px] text-stone-400 leading-relaxed mb-3.5">
        Dijalankan langsung di server ini. Keluarannya ditampilkan apa adanya, termasuk bila gagal —
        tombol yang hanya berkata "berhasil" memindahkan pekerjaan menebak kepada Anda.
      </p>

      <div class="grid gap-2 sm:grid-cols-2">
        <button v-for="p in perbaikan" :key="p.aksi" type="button"
                :disabled="perbaikanForm.processing" @click="perbaiki(p)"
                class="text-left rounded-xl border px-4 py-3 transition disabled:opacity-40"
                :class="p.berat
                  ? 'border-amber-100 bg-amber-50/60 hover:border-amber-400'
                  : 'border-stone-200 hover:border-cam-lime hover:bg-cam-lime-soft'">
          <div class="flex items-center gap-2">
            <span class="text-[12.5px] font-bold text-cam-ink">{{ p.label }}</span>
            <!-- Tangga -100/-800, bukan -200/-900: tema gelap
                 menjangkau tangga yang itu, dan yang di luarnya tetap
                 krem terang di tengah halaman gelap. -->
            <span v-if="p.berat"
                  class="text-[9px] font-bold uppercase tracking-wide bg-amber-100
                         text-amber-800 px-1.5 py-0.5 rounded">
              mengubah data
            </span>
          </div>
          <div class="text-[11px] text-stone-500 mt-1 leading-relaxed">{{ p.ket }}</div>
        </button>
      </div>
    </section>

  </div>
</template>

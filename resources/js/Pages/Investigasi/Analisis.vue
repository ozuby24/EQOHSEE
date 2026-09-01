<script setup lang="ts">
/**
 * Analisis penyebab SCAT — tiga lapis, dari yang terlihat ke yang sistemik.
 *
 * ── MENGAPA LAYARNYA DISUSUN BERLAPIS, BUKAN SATU DAFTAR ──
 *
 * Kamusnya 252 butir. Disajikan sebagai satu daftar bercentang, yang
 * terjadi selalu sama: dipilih dua tiga butir dari bagian atas — yang
 * memang paling mudah dilihat — lalu ditutup. Berkasnya berhenti pada
 * "operator tidak mematuhi prosedur", dan bagian 9 yang menyebut
 * kegagalan sistem tidak pernah tersentuh.
 *
 * Karena itu lapisnya dipisah menjadi tiga blok berurutan, masing-masing
 * dengan usulannya sendiri, dan ringkasan rantai di atas yang menyebut
 * dengan kalimat penuh apa yang HILANG dari berkas kalau sebuah lapis
 * dibiarkan kosong. Yang ditulis di sana akibatnya, bukan namanya:
 * "lapis 3 kosong" terbaca sebagai kolom opsional.
 *
 * ── USULAN SELALU MENYEBUT ASALNYA ──
 *
 * Tiap usulan membawa daftar butir lapis sebelumnya yang
 * memunculkannya. Usulan tanpa alasan tidak dapat dinilai, dan yang
 * tidak dapat dinilai akan dicentang semua — yang persis membatalkan
 * gunanya.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { propHalaman } from '../../halaman';
import { useDialog } from '../../dialog';
import Dialog from '../../Components/Dialog.vue';

const props = propHalaman();
const { dialog, tanya, batal, lanjut } = useDialog();

const basis = () => `/investigasi/berkas/${props.inv.id}`;
const bisaUbah = () => props.inv?.berjalan === true;

/** Pencarian per lapis — katalog panjang tidak dapat ditelusuri dengan mata. */
const cari = reactive<Record<number, string>>({ 1: '', 2: '', 3: '' });

/** Katalog penuh per lapis dibuka atas permintaan, kecuali lapis 1. */
const bukaKatalog = reactive<Record<number, boolean>>({ 1: true, 2: false, 3: false });

const fCatatan = useForm<Record<string, any>>({ catatan: props.catatan ?? '' });

const terpilih = computed<number[]>(() =>
  (props.pilihan ?? []).map((p: any) => p.taksonomiId));

function pilihanLapis(l: number) {
  return (props.pilihan ?? []).filter((p: any) => p.lapis === l);
}

function saranLapis(l: number) {
  return (props.saran ?? {})[l] ?? [];
}

/**
 * Katalog satu lapis, tersaring kata pencarian.
 *
 * Yang sudah dipilih TIDAK dibuang dari katalog, hanya ditandai. Butir
 * yang hilang begitu dicentang membuat daftarnya melompat di bawah
 * kursor, dan centang berikutnya jatuh ke butir yang salah.
 */
function katalogLapis(l: number) {
  const q = (cari[l] ?? '').trim().toLowerCase();
  const isi = (props.katalog ?? {})[l] ?? [];

  if (!q) return isi;

  return isi.filter((t: any) =>
    `${t.kode} ${t.label} ${t.grup ?? ''}`.toLowerCase().includes(q));
}

/**
 * Usulan dikelompokkan menurut judul grupnya supaya terbaca sebagai tema.
 *
 * ALASANNYA DITARUH DI JUDUL GRUP, bukan di tiap butir. Petanya memang
 * bekerja per grup, jadi seluruh butir dalam satu grup selalu membawa
 * alasan yang sama persis — ditulis di tiap baris, ia menjadi empat
 * puluh kalimat kembar yang menenggelamkan bunyi usulannya sendiri.
 * Diukur di layar: 42 usulan menjadi 84 baris, separuhnya pengulangan.
 */
function saranPerGrup(l: number) {
  const peta = new Map<string, { butir: any[]; dari: string[] }>();

  for (const s of saranLapis(l)) {
    const kunci = s.grup || s.kategori || '—';
    if (!peta.has(kunci)) peta.set(kunci, { butir: [], dari: [] });

    const g = peta.get(kunci)!;
    g.butir.push(s);

    for (const d of s.dari ?? []) if (!g.dari.includes(d)) g.dari.push(d);
  }

  return [...peta.entries()].map(([grup, g]) => ({ grup, ...g }));
}

function pilih(id: number, dariSaran = false) {
  router.post(`${basis()}/scat`, { taksonomi_id: id, dari_saran: dariSaran },
    { preserveScroll: true });
}

async function lepas(pilihanId: number, kode: string) {
  if (await tanya(`Lepaskan penyebab ${kode} dari analisis ini?`)) {
    router.post(`${basis()}/scat/${pilihanId}/hapus`, {}, { preserveScroll: true });
  }
}

function idPilihan(taksonomiId: number) {
  return (props.pilihan ?? []).find((p: any) => p.taksonomiId === taksonomiId)?.id;
}

const WARNA_LAPIS: Record<number, string> = { 1: '#B45309', 2: '#0F766E', 3: '#7C3AED' };
</script>

<template>
  <Head :title="`Analisis SCAT · ${props.inv?.nomor ?? ''}`" />

  <div class="max-w-[1500px] mx-auto space-y-5">

    <!-- ══════════ kepala ══════════ -->
    <section class="flex flex-wrap items-start justify-between gap-4">
      <div class="min-w-0">
        <p class="text-[11.5px] text-stone-400">
          <Link :href="`/investigasi/berkas/${props.inv?.id}`" class="hover:underline">← Ruang kerja</Link>
          · <span class="num">{{ props.inv?.nomor }}</span>
        </p>
        <h2 class="text-xl font-bold text-cam-ink">Analisis penyebab — SCAT</h2>
        <p class="text-[12.5px] text-stone-500 mt-0.5">{{ props.inv?.judul }}</p>
      </div>

      <Link :href="`/investigasi/berkas/${props.inv?.id}/wawancara`"
            class="shrink-0 rounded-xl px-3.5 py-2 text-[12px] font-semibold border border-stone-200 text-cam-ink hover:bg-stone-50">
        Panduan wawancara
      </Link>
    </section>

    <!-- ══════════ ringkasan rantai ══════════ -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <div class="grid gap-px bg-stone-100 sm:grid-cols-3">
        <div v-for="l in (props.rantai?.lapis ?? [])" :key="l.lapis" class="bg-white px-5 py-4">
          <div class="flex items-center gap-2">
            <span class="rounded-md px-1.5 py-0.5 text-[10px] font-bold"
                  :style="{ background: WARNA_LAPIS[l.lapis] + '1F', color: WARNA_LAPIS[l.lapis] }">
              LAPIS {{ l.lapis }}
            </span>
            <span class="text-[12.5px] font-bold text-cam-ink">{{ l.nama }}</span>
          </div>
          <p class="text-[11.5px] text-stone-500 mt-1.5 leading-snug">{{ l.uraian }}</p>
          <p class="text-[13px] font-bold mt-2 num"
             :style="{ color: l.jumlah ? WARNA_LAPIS[l.lapis] : '#A8A29E' }">
            {{ l.jumlah }} butir dipilih
          </p>
        </div>
      </div>

      <!-- Akibatnya yang ditulis, bukan namanya. -->
      <p v-if="props.rantai?.pesan"
         class="px-5 py-3 text-[12px] border-t border-stone-100"
         style="background:#FEF3C7;color:#92400E">
        {{ props.rantai.pesan }}
      </p>
      <p v-else class="px-5 py-3 text-[12px] border-t border-stone-100"
         style="background:#ECFDF5;color:#065F46">
        Rantai penyebabnya lengkap: dari yang terlihat di lapangan sampai kegagalan sistem yang membiarkannya.
      </p>
    </section>

    <!-- ══════════ tiga lapis ══════════ -->
    <section v-for="l in [1, 2, 3]" :key="l"
             class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">

      <header class="px-5 py-3.5 border-b border-stone-100 flex flex-wrap items-center gap-3">
        <span class="rounded-md px-1.5 py-0.5 text-[10px] font-bold"
              :style="{ background: WARNA_LAPIS[l] + '1F', color: WARNA_LAPIS[l] }">LAPIS {{ l }}</span>
        <h3 class="text-[13.5px] font-bold text-cam-ink">
          {{ (props.rantai?.lapis ?? [])[l - 1]?.nama }}
          <span class="font-normal text-stone-400">| {{ pilihanLapis(l).length }} dipilih</span>
        </h3>
      </header>

      <!-- yang sudah dipilih -->
      <ul v-if="pilihanLapis(l).length" class="divide-y divide-stone-100">
        <li v-for="p in pilihanLapis(l)" :key="p.id" class="px-5 py-2.5 flex items-start gap-3">
          <span class="num text-[11.5px] font-bold w-14 shrink-0" :style="{ color: WARNA_LAPIS[l] }">{{ p.kode }}</span>
          <span class="min-w-0 flex-1">
            <span class="text-[12.5px] text-cam-ink">{{ p.label }}</span>
            <span v-if="p.grup" class="block text-[11px] text-stone-400">{{ p.grup }}</span>
            <span v-if="p.dariSaran"
                  class="inline-block mt-1 rounded px-1.5 py-0.5 text-[10px] font-semibold"
                  style="background:#F1F5F9;color:#64748B">dipilih dari usulan</span>
          </span>
          <button v-if="bisaUbah()" type="button" class="shrink-0 text-red-600 text-[11px]"
                  @click="lepas(p.id, p.kode)">lepas</button>
        </li>
      </ul>
      <p v-else class="px-5 py-3 text-[12px] text-stone-400">Belum ada butir yang dipilih pada lapis ini.</p>

      <!-- usulan mesin -->
      <div v-if="saranLapis(l).length" class="border-t border-stone-100" style="background:#FBF5EA">
        <p class="px-5 pt-3.5 pb-1 text-[11px] font-bold uppercase tracking-wide text-stone-500">
          Usulan dari pilihan lapis {{ l - 1 }} — {{ saranLapis(l).length }} butir dari
          {{ ((props.katalog ?? {})[l] ?? []).length }}
        </p>

        <!-- Daftarnya dibatasi tingginya dan digulir sendiri. Usulan yang
             mendorong catatan analisis sejauh dua layar ke bawah membuat
             kolom yang justru paling perlu diisi tidak pernah terlihat. -->
        <div class="max-h-[26rem] overflow-y-auto">
          <div v-for="g in saranPerGrup(l)" :key="g.grup" class="px-5 py-2.5">
            <!-- alasannya di judul grup, supaya usulannya dapat DITOLAK
                 sekelompok sekaligus alih-alih dinilai satu per satu -->
            <p class="text-[11.5px] font-semibold text-cam-ink">
              {{ g.grup }}
              <span class="font-normal text-stone-400">| {{ g.butir.length }} butir</span>
            </p>
            <p class="text-[10.5px] text-stone-400 mb-1">muncul karena: {{ g.dari.join(' · ') }}</p>

            <ul class="space-y-1">
              <li v-for="s in g.butir" :key="s.id" class="flex items-start gap-2.5">
                <button v-if="bisaUbah() && !terpilih.includes(s.id)" type="button"
                        class="shrink-0 mt-0.5 rounded-md px-2 py-0.5 text-[10.5px] font-bold text-white"
                        :style="{ background: WARNA_LAPIS[l] }"
                        @click="pilih(s.id, true)">pilih</button>
                <span v-else class="shrink-0 mt-0.5 text-[10.5px] text-stone-400 w-9">dipilih</span>

                <span class="min-w-0">
                  <span class="num text-[11.5px] text-stone-500">{{ s.kode }}</span>
                  <span class="text-[12px] text-cam-ink ml-1.5">{{ s.label }}</span>
                </span>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <p v-else-if="l > 1" class="px-5 py-2.5 text-[11.5px] text-stone-400 border-t border-stone-100">
        Belum ada usulan — pilih dahulu butir pada lapis {{ l - 1 }}.
      </p>

      <!-- katalog penuh -->
      <div class="border-t border-stone-100">
        <button type="button" class="w-full px-5 py-2.5 text-left text-[11.5px] font-semibold text-stone-500 hover:bg-stone-50"
                @click="bukaKatalog[l] = !bukaKatalog[l]">
          {{ bukaKatalog[l] ? '▾' : '▸' }}
          Seluruh kamus lapis {{ l }} ({{ ((props.katalog ?? {})[l] ?? []).length }} butir)
        </button>

        <div v-if="bukaKatalog[l]" class="px-5 pb-4">
          <input v-model="cari[l]" type="search" placeholder="Cari kode, kalimat, atau nama grup…"
                 class="w-full rounded-lg border border-stone-200 px-3 py-1.5 text-[12px]" />

          <ul class="mt-2 max-h-80 overflow-y-auto divide-y divide-stone-100">
            <li v-for="t in katalogLapis(l)" :key="t.id" class="py-1.5 flex items-start gap-2.5">
              <button v-if="bisaUbah() && !terpilih.includes(t.id)" type="button"
                      class="shrink-0 mt-0.5 rounded-md px-2 py-0.5 text-[10.5px] font-bold border"
                      :style="{ borderColor: WARNA_LAPIS[l], color: WARNA_LAPIS[l] }"
                      @click="pilih(t.id, false)">pilih</button>

              <button v-else-if="bisaUbah()" type="button"
                      class="shrink-0 mt-0.5 rounded-md px-2 py-0.5 text-[10.5px] font-bold text-white"
                      :style="{ background: WARNA_LAPIS[l] }"
                      @click="lepas(idPilihan(t.id), t.kode)">lepas</button>

              <span v-else class="shrink-0 w-9"></span>

              <span class="min-w-0">
                <span class="num text-[11.5px] text-stone-500">{{ t.kode }}</span>
                <span class="text-[12px] text-cam-ink ml-1.5">{{ t.label }}</span>
                <span v-if="t.grup" class="block text-[10.5px] text-stone-400">{{ t.grup }}</span>
              </span>
            </li>
          </ul>

          <p v-if="!katalogLapis(l).length" class="text-[12px] text-stone-400 py-2">
            Tidak ada butir yang cocok dengan “{{ cari[l] }}”.
          </p>
        </div>
      </div>
    </section>

    <!-- ══════════ catatan analisis ══════════ -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100">
        <h3 class="text-[13.5px] font-bold text-cam-ink">
          Catatan analisis
          <span class="font-normal text-stone-400">| alasan di balik pilihan di atas</span>
        </h3>
      </header>

      <form v-if="bisaUbah()" class="px-5 py-4"
            @submit.prevent="fCatatan.post(`${basis()}/scat/catatan`, { preserveScroll: true })">
        <textarea v-model="fCatatan.catatan" rows="4"
                  placeholder="Mengapa butir-butir itu yang dipilih, dan apa yang sengaja tidak dipilih beserta alasannya."
                  class="w-full rounded-lg border border-stone-200 px-3 py-2 text-[12.5px]"></textarea>
        <button type="submit" :disabled="fCatatan.processing"
                class="mt-2 rounded-xl px-3.5 py-2 text-[12px] font-semibold text-white"
                style="background:#0F766E">Simpan catatan</button>
      </form>

      <p v-else class="px-5 py-3 text-[12.5px] text-stone-600 whitespace-pre-line">
        {{ props.catatan || '—' }}
      </p>
    </section>
  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>

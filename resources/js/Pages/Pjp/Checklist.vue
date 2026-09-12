<script setup lang="ts">
/**
 * Daftar periksa prakualifikasi SMKP — 17 kategori, 126 butir.
 *
 * SATU KATEGORI TERBUKA PADA SATU WAKTU, bukan 126 pertanyaan
 * bergulir. Kategori J sendirian berisi 88 butir; digambar sekaligus
 * dengan yang lain, halaman ini menjadi gulungan sepanjang belasan
 * layar tempat orang kehilangan tempatnya dan mengisi baris yang salah.
 *
 * SELURUH JAWABAN DIKIRIM SEKALIGUS, bukan per butir saat diubah.
 * Pengisian daftar periksa berlangsung berjam-jam dan kerap ditinggal
 * setengah jalan; menyimpan tiap ketukan berarti ratusan permintaan,
 * dan menyimpan hanya yang sedang dibuka berarti perubahan pada
 * kategori sebelumnya hilang tanpa peringatan saat kategori berpindah.
 * Karena itu keadaannya disimpan di sini untuk SEMUA kategori, dan
 * tombol simpan mengirim semuanya.
 *
 * "Layak s.d. risiko" ditulis lengkap. Nilai "Kritis" pada skor 100%
 * berarti mitra ini layak menangani pekerjaan berisiko kritis — bukan
 * bahwa keadaannya kritis.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { propHalaman } from '../../halaman';
import { KEADAAN } from '../../Grafik/warna';

const props = propHalaman();

const kategori = computed<any[]>(() => (props.kategori ?? []) as any[]);

const terbuka = ref<string>(kategori.value[0]?.kode ?? '');

/* Keadaan seluruh butir, dikunci menurut id butirnya. Disusun sekali
   pada pemuatan pertama: menyusunnya ulang pada tiap penggambaran akan
   membuang apa yang sedang diketik orang. */
const jawaban = ref<Record<number, { jawaban: string; nilai: string; penjelasan: string }>>(
  Object.fromEntries(
    kategori.value.flatMap((k: any) => (k.butir ?? []).map((b: any) => [
      b.id,
      { jawaban: b.jawaban ?? '', nilai: b.nilai ?? '', penjelasan: b.penjelasan ?? '' },
    ])),
  ),
);

const f = useForm<{ jawaban: any[] }>({ jawaban: [] });

function simpan() {
  f.jawaban = Object.entries(jawaban.value).map(([itemId, j]) => ({
    item_id: Number(itemId),
    jawaban: j.jawaban || null,
    nilai: j.nilai || null,
    penjelasan: j.penjelasan || null,
  }));

  f.post(`/pjp/${props.pjp?.id}/checklist`, { preserveScroll: true, preserveState: true });
}

/** Berapa butir kategori ini yang sudah punya jawaban apa pun. */
function terisi(k: any): number {
  return (k.butir ?? []).filter((b: any) => {
    const j = jawaban.value[b.id];

    return !!(j && (k.legalitas ? j.jawaban : j.nilai));
  }).length;
}

function rincianKategori(kode: string): any {
  return (props.rincian ?? []).find((r: any) => r.kode === kode);
}

function nada(persen: number | null | undefined): string {
  if (persen === null || persen === undefined) return KEADAAN.netral;
  if (persen >= 80) return KEADAAN.baik;
  if (persen >= 55) return KEADAAN.ingat;
  return KEADAAN.gawat;
}
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1200px] mx-auto space-y-5">

    <section class="flex flex-wrap items-start justify-between gap-4">
      <div class="min-w-0">
        <p class="text-[11px] text-stone-400">
          <Link href="/pjp/daftar" class="hover:underline">Perusahaan Jasa</Link> ›
          <Link :href="`/pjp/${props.pjp?.id}`" class="hover:underline">{{ props.pjp?.nama_perusahaan }}</Link> ›
        </p>
        <h2 class="text-xl font-bold text-cam-ink">Prakualifikasi SMKP</h2>
        <p class="text-[12.5px] text-stone-500 mt-0.5">
          Kategori A–P dinilai 0–3 dan berbobot; Dokumen Legalitas dijawab ada/tidak ada
          dan tidak ikut dihitung ke skor.
        </p>
      </div>

      <span class="inline-flex gap-2">
        <button type="button" class="eq-btn-utama" :disabled="f.processing" @click="simpan">
          Simpan daftar periksa
        </button>
      </span>
    </section>

    <!-- ══════════ skor ══════════ -->
    <section class="grid gap-3 sm:grid-cols-3">
      <div class="rounded-2xl bg-white border border-stone-100 shadow-card px-4 py-3.5">
        <p class="text-[10.5px] uppercase tracking-wide text-stone-400">Skor kepatuhan</p>
        <p class="text-[26px] font-bold leading-none mt-1 num" :style="{ color: nada(props.skor?.persentase) }">
          {{ props.skor?.persentase }}<span class="text-[13px] font-semibold">%</span>
        </p>
        <p class="text-[11px] text-stone-500 mt-1">
          <span class="num">{{ props.skor?.total_skor }}</span> dari
          <span class="num">{{ props.skor?.total_bobot }}</span> bobot yang dinilai
        </p>
      </div>

      <div class="rounded-2xl bg-white border border-stone-100 shadow-card px-4 py-3.5">
        <p class="text-[10.5px] uppercase tracking-wide text-stone-400">Layak s.d. risiko</p>
        <p class="text-[20px] font-bold leading-none mt-2" :style="{ color: nada(props.skor?.persentase) }">
          {{ props.skor?.kelayakan }}
        </p>
        <p class="text-[11px] text-stone-500 mt-1.5">
          Sampai setinggi apa pekerjaan yang boleh diserahkan — bukan tingkat bahaya mitranya.
        </p>
      </div>

      <div class="rounded-2xl bg-white border border-stone-100 shadow-card px-4 py-3.5">
        <p class="text-[10.5px] uppercase tracking-wide text-stone-400">Dokumen legalitas</p>
        <p class="text-[26px] font-bold leading-none mt-1 num"
           :style="{ color: props.legalitas?.lengkap === props.legalitas?.total ? KEADAAN.baik : KEADAAN.serius }">
          {{ props.legalitas?.lengkap }} / {{ props.legalitas?.total }}
        </p>
        <p class="text-[11px] text-stone-500 mt-1">Syarat wajib, tidak punya nilai tengah.</p>
      </div>
    </section>

    <div class="grid gap-4 lg:grid-cols-[280px_1fr] items-start">

      <!-- ══════════ daftar kategori ══════════ -->
      <nav class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden lg:sticky lg:top-4">
        <ul class="divide-y divide-stone-100 max-h-[70vh] overflow-y-auto">
          <li v-for="k in kategori" :key="k.kode">
            <button type="button"
                    class="w-full text-left px-4 py-2.5 hover:bg-stone-50/70 transition"
                    :class="terbuka === k.kode ? 'bg-stone-50' : ''"
                    @click="terbuka = k.kode">
              <div class="flex items-baseline gap-2">
                <!-- Lebarnya mengikuti kode terpanjang, "LEGALITAS", dan
                     tidak boleh membungkus: dipatok selebar kode satu
                     huruf, kata itu terpenggal menjadi "LEGALITA/S". -->
                <span class="text-[10.5px] font-bold num text-stone-400 w-[62px] shrink-0 whitespace-nowrap">{{ k.kode }}</span>
                <span class="text-[11.5px] text-cam-ink flex-1 min-w-0">{{ k.nama }}</span>
              </div>

              <div class="flex items-center gap-2 mt-1 pl-[70px]">
                <span class="text-[10.5px] text-stone-400 num">{{ terisi(k) }}/{{ (k.butir ?? []).length }}</span>
                <span v-if="!k.legalitas" class="text-[10.5px] num font-bold"
                      :style="{ color: nada(rincianKategori(k.kode)?.persentase) }">
                  {{ rincianKategori(k.kode)?.persentase ?? 0 }}%
                </span>
              </div>
            </button>
          </li>
        </ul>
      </nav>

      <!-- ══════════ butir kategori terbuka ══════════ -->
      <section v-for="k in kategori" v-show="terbuka === k.kode" :key="k.kode"
               class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <header class="px-5 py-3.5 border-b border-stone-100">
          <h3 class="text-[13.5px] font-bold text-cam-ink">
            {{ k.kode }} — {{ k.nama }}
            <span class="font-normal text-stone-400">| bobot {{ k.bobot }}</span>
          </h3>
          <p class="text-[11px] text-stone-500 mt-0.5">
            {{ k.legalitas
               ? 'Syarat wajib. Dijawab ada / tidak ada, tidak ikut dihitung ke skor berbobot.'
               : 'Nilai N/A mengeluarkan butirnya dari perhitungan; butir yang dibiarkan kosong tetap dihitung nol.' }}
          </p>
        </header>

        <ul class="divide-y divide-stone-100">
          <li v-for="b in (k.butir ?? [])" :key="b.id" class="px-5 py-4 grid gap-2">
            <p v-if="b.grup_nama" class="text-[10.5px] uppercase tracking-wide text-stone-400">
              <span class="num">{{ b.grup_kode }}</span> {{ b.grup_nama }}
            </p>

            <p class="text-[12.5px] text-cam-ink whitespace-pre-line">
              <span class="num text-stone-400">{{ b.nomor }}.</span> {{ b.pertanyaan }}
            </p>

            <p v-if="b.petunjuk" class="text-[11px] text-stone-500">{{ b.petunjuk }}</p>

            <div class="grid gap-2 sm:grid-cols-[minmax(0,240px)_1fr] items-start">
              <label class="grid gap-1">
                <span class="block text-[10.5px] uppercase tracking-wide text-stone-400">
                  {{ k.legalitas ? 'Ketersediaan' : `Nilai · bobot ${b.bobot}` }}
                </span>

                <select v-if="k.legalitas" v-model="jawaban[b.id].jawaban"
                        class="rounded-lg border-stone-200 text-[12px]">
                  <option value="">Belum dijawab</option>
                  <option v-for="(label, kode) in (props.JAWABAN ?? {})" :key="kode" :value="kode">{{ label }}</option>
                </select>

                <select v-else v-model="jawaban[b.id].nilai" class="rounded-lg border-stone-200 text-[12px]">
                  <option value="">Belum dinilai</option>
                  <option v-for="(label, kode) in (props.NILAI ?? {})" :key="kode" :value="kode">{{ label }}</option>
                </select>
              </label>

              <label class="grid gap-1">
                <span class="block text-[10.5px] uppercase tracking-wide text-stone-400">Penjelasan / bukti</span>
                <textarea v-model="jawaban[b.id].penjelasan" rows="2"
                          class="rounded-lg border-stone-200 text-[12px]" />
              </label>
            </div>
          </li>
        </ul>

        <footer class="px-5 py-3.5 border-t border-stone-100 bg-stone-50/50">
          <button type="button" class="eq-btn-utama" :disabled="f.processing" @click="simpan">
            Simpan daftar periksa
          </button>
        </footer>
      </section>
    </div>
  </div>
</template>

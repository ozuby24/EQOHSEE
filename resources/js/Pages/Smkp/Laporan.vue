<script setup lang="ts">
/**
 * Penyusunan Laporan Audit Internal SMKP — bagian naratifnya.
 *
 * Laporan yang dibaca inspektur tambang bukan hanya angka. Berkas acuan
 * menempuh urutan: latar belakang beserta dasar hukumnya, gambaran umum
 * auditi (domisili, legalitas, kegiatan, peralatan, tenaga kerja),
 * ringkasan penerapan tiap elemen, lingkup audit, pelaksanaan dan tim,
 * lalu baru penilaiannya — dan ditutup daftar lampiran serta distribusi.
 *
 * Tanpa bagian itu, "39,14%" adalah angka tanpa perusahaan di
 * belakangnya: pembacanya tidak dapat mengetahui berapa pekerja yang
 * diaudit, kegiatan apa yang dijalankan, atau mengapa sebuah elemen
 * memperoleh nilai serendah itu.
 *
 * YANG SUDAH DIKETAHUI SISTEM TIDAK DIMINTA LAGI. Dasar hukum, nilai
 * tiap elemen, jumlah pekerja, dan susunan tim sudah ada; halaman ini
 * menampilkannya sebagai acuan di samping ruas isian, bukan sebagai
 * kotak kosong yang harus diketik ulang.
 */
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';

const props = propHalaman();

const elemen = computed<any[]>(() => (props.elemen ?? []) as any[]);
const ruas   = computed<Record<string, any>>(() => (props.ruas ?? {}) as any);
const rekap  = computed<any>(() => props.rekap ?? {});

const isi = (props.isi ?? {}) as Record<string, any>;

const form = useForm<Record<string, any>>({
  domisili:   isi.domisili   ?? '',
  kegiatan:   isi.kegiatan   ?? '',
  penerapan:  isi.penerapan  ?? '',
  lingkup:    isi.lingkup    ?? '',
  kesimpulan: isi.kesimpulan ?? '',
  elemen:     { ...(isi.elemen ?? {}) },
  peralatan:  [...(isi.peralatan ?? [])],
  lampiran:   [...(isi.lampiran ?? [])],
  distribusi: [...(isi.distribusi ?? [])],
});

function tambahAlat()            { form.peralatan.push({ jenis: '', jumlah: '' }); }
function hapusAlat(i: number)    { form.peralatan.splice(i, 1); }
function tambah(k: 'lampiran' | 'distribusi')             { form[k].push(''); }
function hapus(k: 'lampiran' | 'distribusi', i: number)   { form[k].splice(i, 1); }
function pulihkan(k: 'lampiran' | 'distribusi')           { form[k] = [...((props.bawaan ?? {})[k] ?? [])]; }

function simpan() { form.post(props.tautan.simpan, { preserveScroll: true }); }

/**
 * Capaian elemen sebagai angka acuan di samping ruas naratifnya.
 *
 * `rekap()` menyimpan capaian sebagai pecahan penuh — 0.8421052631578947 —
 * supaya penjumlahan tujuh elemen tidak kehilangan ketelitiannya. Tercetak
 * apa adanya, ia terbaca sebagai kesalahan cetak.
 */
const persen = (v: unknown) =>
  Number.isFinite(Number(v))
    ? `${(Number(v) * 100).toLocaleString('id-ID', { maximumFractionDigits: 1 })}%`
    : '—';

const angka2 = (v: unknown) =>
  Number.isFinite(Number(v))
    ? Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    : '—';

function capaian(kode: string) {
  const e = rekap.value?.elemen?.[kode];
  if (!e) return '—';
  return `${persen(e.capaian)} · nilai ${angka2(e.skor)} dari bobot ${e.bobot ?? '—'}`;
}

const isian = 'ring-focus w-full rounded-lg border border-stone-200 px-3 py-2 text-[12.5px] transition';
const label = 'block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5';
</script>

<template>
  <Head title="Penyusunan Laporan Audit" />

  <div class="space-y-5">
    <section class="rounded-2xl border border-stone-200 bg-stone-50 p-4 text-[12.5px]">
      <p class="font-bold text-cam-ink">
        {{ props.rekapNarasi?.terisi ?? 0 }} dari {{ props.rekapNarasi?.total ?? 0 }} bagian naratif terisi
      </p>
      <p v-if="(props.rekapNarasi?.kurang ?? []).length" class="text-stone-600 mt-1 leading-relaxed">
        Belum diisi: {{ (props.rekapNarasi.kurang as string[]).join(', ') }}.
      </p>
      <p v-else class="text-stone-600 mt-1">Laporan siap dicetak lengkap dengan bagian naratifnya.</p>
    </section>

    <form class="space-y-5" @submit.prevent="simpan">

      <!-- ── Latar belakang ──
           TIDAK ADA RUAS ISIAN DI SINI. Dasar hukumnya sama bagi setiap
           audit SMKP Minerba dan sudah tertulis di satu tempat; meminta
           auditor mengetiknya ulang tiap periode hanya melahirkan empat
           nomor peraturan yang dapat salah ketik. -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="font-bold text-[14px]">I · Latar Belakang</h3>
        <p class="text-[12px] text-stone-500 mt-1">
          Dasar hukum tercetak apa adanya pada laporan; tidak perlu diketik ulang.
        </p>
        <ol class="mt-3 space-y-1.5 text-[12px] text-stone-600 list-decimal pl-5">
          <li v-for="(d, i) in props.dasarHukum ?? []" :key="i" class="leading-relaxed">{{ d }}</li>
        </ol>
      </section>

      <!-- ── Gambaran umum ── -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 space-y-4">
        <h3 class="font-bold text-[14px]">II · Gambaran Umum Auditi</h3>

        <div v-for="k in ['domisili', 'kegiatan', 'penerapan']" :key="k">
          <label :class="label">{{ ruas[k]?.judul }}</label>
          <p class="text-[11.5px] text-stone-400 mb-1.5 leading-relaxed">{{ ruas[k]?.ket }}</p>
          <textarea v-model="form[k]" rows="4" :class="isian"></textarea>
        </div>

        <div>
          <div class="flex flex-wrap items-center justify-between gap-2">
            <label :class="label">Daftar unit &amp; peralatan</label>
            <button type="button" class="eq-btn-lain" @click="tambahAlat">Tambah baris</button>
          </div>
          <div v-for="(b, i) in form.peralatan" :key="i" class="grid grid-cols-[1fr_120px_auto] gap-2 mt-2">
            <input v-model="b.jenis" placeholder="Jenis peralatan" :class="isian">
            <input v-model="b.jumlah" placeholder="Jumlah" :class="isian">
            <button type="button" class="text-red-600 text-[11.5px] px-2" @click="hapusAlat(Number(i))">Hapus</button>
          </div>
          <p v-if="!form.peralatan.length" class="text-[11.5px] text-stone-400 mt-2">
            Belum ada baris. Laporan acuan mencantumkannya sebagai tabel jenis dan jumlah.
          </p>
        </div>

        <!-- Jumlah tenaga kerja TIDAK diminta di sini: ia tercatat pada
             profil perusahaan, tempat mandays audit juga dihitung darinya. -->
        <p class="text-[11.5px] text-stone-500 leading-relaxed rounded-lg bg-stone-50 px-3 py-2">
          Jumlah tenaga kerja diambil dari profil perusahaan — tidak diketik ulang di sini.
        </p>
      </section>

      <!-- ── Ringkasan penerapan tiap elemen ── -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="font-bold text-[14px]">II.b · Ringkasan Penerapan Tiap Elemen</h3>
        <p class="text-[12px] text-stone-500 mt-1">
          Satu paragraf per elemen: apa yang sudah dijalankan auditi dan apa yang belum,
          sebagaimana yang akan dibaca inspektur sebelum melihat angkanya.
        </p>

        <div v-for="e in elemen" :key="e.kode" class="mt-4 pt-4 border-t border-stone-100 first:border-t-0 first:pt-0">
          <div class="flex flex-wrap items-baseline justify-between gap-2">
            <label :class="label">{{ e.kode }} · {{ e.nama }}</label>
            <!-- Angka capaiannya ditampilkan di sini, bukan disembunyikan
                 di halaman lain: paragraf yang menyebut "sudah berjalan
                 baik" di atas capaian 12% adalah laporan yang menyangkal
                 tabelnya sendiri. -->
            <span class="text-[11px] text-stone-400">Capaian {{ capaian(e.kode) }}</span>
          </div>
          <textarea v-model="form.elemen[e.kode]" rows="3" :class="isian"></textarea>
        </div>
      </section>

      <!-- ── Lingkup & kesimpulan ── -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 space-y-4">
        <h3 class="font-bold text-[14px]">III · Lingkup Audit dan Kesimpulan</h3>

        <div v-for="k in ['lingkup', 'kesimpulan']" :key="k">
          <label :class="label">{{ ruas[k]?.judul }}</label>
          <p class="text-[11.5px] text-stone-400 mb-1.5 leading-relaxed">{{ ruas[k]?.ket }}</p>
          <textarea v-model="form[k]" rows="4" :class="isian"></textarea>
        </div>
      </section>

      <!-- ── Lampiran & distribusi ── -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 grid gap-5 md:grid-cols-2">
        <div v-for="k in (['lampiran', 'distribusi'] as const)" :key="k">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="font-bold text-[14px]">
              {{ k === 'lampiran' ? 'Lampiran Laporan' : 'Distribusi Laporan' }}
            </h3>
            <div class="flex gap-2">
              <button type="button" class="eq-btn-lain" @click="pulihkan(k)">Daftar bawaan</button>
              <button type="button" class="eq-btn-lain" @click="tambah(k)">Tambah</button>
            </div>
          </div>
          <div v-for="(_, i) in form[k]" :key="i" class="flex gap-2 mt-2">
            <input v-model="form[k][i]" :class="isian">
            <button type="button" class="text-red-600 text-[11.5px] px-2" @click="hapus(k, Number(i))">Hapus</button>
          </div>
          <p v-if="!form[k].length" class="text-[11.5px] text-stone-400 mt-2">
            Kosong — bagian ini tidak akan tercetak pada laporan.
          </p>
        </div>
      </section>

      <!-- Wadah block, bukan flex: .eq-btn-utama di dalam wadah flex akan
           berbagi lebar dengan tautan di sampingnya. -->
      <div>
        <button class="eq-btn-utama" :disabled="form.processing">
          {{ form.processing ? 'Menyimpan…' : 'Simpan Bagian Naratif' }}
        </button>
        <a :href="props.tautan.cetak" target="_blank" class="eq-btn-lain ml-2">Lihat laporan cetak</a>
      </div>
    </form>
  </div>
</template>

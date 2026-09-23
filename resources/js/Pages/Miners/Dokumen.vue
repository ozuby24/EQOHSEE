<script setup lang="ts">
/**
 * Halaman rincian satu dokumen Miners.
 *
 * SATU HALAMAN UNTUK EMPAT JENIS — MCU, induksi, Mine Permit, SIMPER.
 * Bingkainya sama bagi keempatnya; yang berbeda hanya isinya, dan
 * isinya sudah dinormalkan di App\Support\Miners\RincianDokumen menjadi
 * daftar baris label/nilai, daftar lampiran, dan daftar orang. Karena
 * itu halaman ini TIDAK PERLU TAHU sedang menggambar dokumen yang mana,
 * dan tidak punya satu pun cabang `v-if="jenis === ..."` untuk tata
 * letaknya.
 *
 * ── Susunannya ──
 *
 * Tiga bagian tetap, urutannya mengikuti pertanyaan yang benar-benar
 * diajukan orang yang membukanya:
 *
 *   1. Rincian     — dokumen apa ini, milik siapa, berlaku sampai kapan
 *   2. Lampiran    — berkas apa yang menyertainya, dan mana yang kurang
 *   3. Persetujuan — sudah sampai mana, dan apa yang harus saya lakukan
 *
 * Sebelumnya ketiganya berebut lebar satu sel tabel di halaman daftar.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { propHalaman } from '../../halaman';
import Lencana from './Lencana.vue';
import Langkah from './Langkah.vue';

const props = propHalaman();

const d        = computed<any>(() => props.dokumen ?? {});
const rincian  = computed<any[]>(() => (props.rincian ?? []) as any[]);
const lampiran = computed<any[]>(() => (props.lampiran ?? []) as any[]);
const orang    = computed<any[]>(() => (props.orang ?? []) as any[]);

/**
 * Judul daftar yang menempel pada dokumen.
 *
 * SIMPER membawa UNIT, bukan orang, pada kunci yang sama — keduanya
 * daftar baris yang menempel pada dokumennya, jadi tidak ada gunanya
 * dua kunci. Yang berbeda hanya sebutannya di layar.
 */
const judulDaftar = computed(() =>
  d.value.jenis === 'simper' ? 'Unit yang diizinkan'
  : d.value.jenis === 'induksi' ? 'Peserta'
  : 'Nama pada surat');

const wajib  = computed<any[]>(() => (props.wajib ?? []) as any[]);
const kurang = computed(() => wajib.value.filter(w => !w.ada));

const tindakan = useForm({ keadaan: 'setuju', catatan: '' });
/* Bentuknya disebut supaya `errors.ajukanUlang` dikenali TypeScript;
   useForm({}) kosong tidak punya kunci galat apa pun. */
const ulang = useForm<{ ajukanUlang?: string }>({});

function ajukanUlang() {
  ulang.post(`/miners/${d.value.jenis}/${d.value.id}/ajukan-ulang`, { preserveScroll: true });
}

function tindak(keadaan: string) {
  tindakan.keadaan = keadaan;
  tindakan.post(`/miners/${d.value.jenis}/${d.value.id}/tindak`, { preserveScroll: true });
}
</script>

<template>
  <Head :title="props.judul" />

  <div class="space-y-5">
    <section class="flex flex-wrap items-end justify-between gap-4">
      <div class="min-w-0">
        <Link :href="d.daftar" class="text-[11.5px] text-stone-400 hover:underline">
          ← Daftar {{ d.label }}
        </Link>
        <h2 class="text-xl font-bold text-cam-ink mt-1">
          {{ d.nomor || `${d.label} tanpa nomor` }}
        </h2>
        <p class="text-[12.5px] text-stone-500 mt-0.5">{{ props.subjudul }}</p>
      </div>

      <span v-if="d.status" class="rounded-full bg-stone-100 px-3 py-1 text-[11.5px] text-stone-600">
        {{ d.status }}
      </span>
    </section>

    <!-- ══════════ 1. Rincian ══════════ -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="font-bold text-[14px] text-cam-ink">Rincian</h3>

      <dl class="mt-3 grid gap-x-6 gap-y-2.5 sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="b in rincian" :key="b.label" class="min-w-0">
          <dt class="text-[11px] text-stone-400">{{ b.label }}</dt>
          <dd class="text-[12.5px] break-words"
              :class="b.nada === 'peringatan' ? 'text-amber-700 font-semibold' : 'text-cam-ink'">
            {{ b.nilai || '—' }}
          </dd>

          <!-- Sebabnya ikut, bukan hanya tanggalnya. Tanggal efektif
               yang lebih awal daripada yang tercetak di kartu adalah
               pertanyaan pertama yang diajukan pemegangnya, dan
               menjawabnya di sini menghemat satu telepon ke OHSE. -->
          <dd v-if="b.sebab" class="text-[10.5px] text-amber-700/80 mt-0.5">{{ b.sebab }}</dd>
        </div>
      </dl>
    </section>

    <!-- ══════════ 2. Lampiran ══════════ -->
    <section v-if="lampiran.length" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="font-bold text-[14px] text-cam-ink">Lampiran</h3>

      <ul class="mt-3 divide-y divide-stone-100">
        <li v-for="b in lampiran" :key="b.label" class="flex flex-wrap items-baseline gap-x-3 py-2">
          <span class="text-[12.5px] text-cam-ink font-medium">{{ b.label }}</span>
          <span v-if="b.nomor" class="text-[11px] text-stone-400 num">{{ b.nomor }}</span>
          <span v-if="b.kunci && b.kunci !== b.label" class="text-[10.5px] text-stone-300">{{ b.kunci }}</span>

          <a v-if="b.url" :href="b.url" target="_blank" rel="noopener"
             class="text-[11.5px] text-cam-lime-deep hover:underline">Buka</a>

          <!-- "Terjaga" BUKAN sama dengan "belum ada". Yang pertama
               sudah beres dan hanya tidak boleh dibaca oleh yang
               membuka; yang kedua menuntut seseorang mengunggahnya.
               Disamakan, petugas akan terus mencari berkas yang
               sebenarnya sudah ada. -->
          <span v-else-if="b.ada" class="text-[11.5px] text-stone-400"
                title="Sudah diunggah, tetapi hanya dapat dibuka paramedis, tim OHSE, atau administrator.">
            terjaga
          </span>
          <span v-else class="text-[11.5px] text-stone-300">belum diunggah</span>

        </li>
      </ul>
    </section>

    <!-- ══════════ daftar periksa SOP ══════════ -->
    <section v-if="wajib.length" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h3 class="font-bold text-[14px] text-cam-ink">Lampiran wajib menurut SOP</h3>
        <span class="text-[11.5px]"
              :class="kurang.length ? 'text-amber-700 font-semibold' : 'text-emerald-700 font-semibold'">
          {{ wajib.length - kurang.length }} / {{ wajib.length }} terpenuhi
        </span>
      </div>

      <!-- Yang KURANG disebut apa adanya, bukan disembunyikan di balik
           angka. Persetujuan OHSE ditahan sampai daftar ini penuh, jadi
           yang mengurusnya berhak tahu persis apa yang menahannya —
           tanpa itu berkas bolak-balik antara mitra dan OHSE
           berhari-hari, yang persis keluhan pemakai Safe Track. -->
      <ul class="mt-3 grid gap-x-6 gap-y-1 sm:grid-cols-2">
        <li v-for="w in wajib" :key="w.kunci" class="flex items-baseline gap-2 text-[12px]">
          <span aria-hidden="true" :class="w.ada ? 'text-emerald-600' : 'text-amber-600'">
            {{ w.ada ? '✓' : '○' }}
          </span>
          <span :class="w.ada ? 'text-stone-500' : 'text-cam-ink'">
            {{ w.label }}
            <span class="sr-only">{{ w.ada ? '— sudah dilampirkan' : '— belum dilampirkan' }}</span>
          </span>
        </li>
      </ul>

      <p v-if="kurang.length" class="mt-3 text-[11.5px] text-amber-700">
        Persetujuan OHSE ditahan sampai seluruhnya terpenuhi. Menolak atau
        mengembalikan tetap dapat dilakukan.
      </p>
    </section>

    <!-- ══════════ orang / unit ══════════ -->
    <section v-if="orang.length" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="font-bold text-[14px] text-cam-ink">{{ judulDaftar }}</h3>

      <ul class="mt-3 divide-y divide-stone-100">
        <li v-for="o in orang" :key="o.id" class="py-3">
          <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
            <div class="min-w-0">
              <Link v-if="o.pekerja_id" :href="`/miners/${o.pekerja_id}`"
                    class="text-[13px] font-semibold text-cam-lime-deep hover:underline">{{ o.nama }}</Link>
              <span v-else class="text-[13px] font-semibold text-cam-ink">{{ o.nama || '—' }}</span>

              <span v-if="o.nik" class="ml-2 text-[10.5px] text-stone-400 num">{{ o.nik }}</span>
              <span v-if="o.golongan" class="ml-2 text-[11px] text-stone-500">{{ o.golongan }}</span>
            </div>

            <Lencana v-if="o.keadaan" :keadaan="o.keadaan"
                     :label="props.KEADAAN?.[o.keadaan]" :nada="props.NADA" />
          </div>

          <p class="mt-1 text-[11.5px] text-stone-500">
            <template v-if="o.bentuk === 'mcu'">
              {{ o.hasil || 'hasil belum masuk' }}
              <span v-if="o.hasil && !o.layak" class="text-red-600">· tidak layak</span>
              <span v-if="o.napza"> · napza {{ o.napza }}</span>
              <span v-if="o.periksa"> · diperiksa <span class="num">{{ o.periksa }}</span></span>
              <span v-if="o.sampai"> · sampai <span class="num">{{ o.sampai }}</span></span>
            </template>

            <template v-else-if="o.bentuk === 'induksi'">
              nilai <span class="num">{{ o.nilai }}</span>
              · percobaan {{ o.percobaan }}
              <span v-if="!o.lulus" class="text-red-600"> · belum lulus</span>
              <span v-if="o.sampai"> · sampai <span class="num">{{ o.sampai }}</span></span>
            </template>

            <template v-else-if="o.bentuk === 'unit'">
              {{ o.kewenangan }}
              <span v-if="o.nilai"> · P2H {{ o.nilai.P2H ?? '—' }}
                · praktik {{ o.nilai.Praktik ?? '—' }} · teori {{ o.nilai.Teori ?? '—' }}</span>
              <span v-if="!o.lulus" class="text-red-600"> · belum lulus</span>
            </template>
          </p>

          <div v-if="o.berkas?.length" class="mt-1.5 flex flex-wrap gap-x-3 gap-y-0.5">
            <template v-for="b in o.berkas" :key="b.jenis">
              <a v-if="b.url" :href="b.url" target="_blank" rel="noopener"
                 class="text-[11px] text-cam-lime-deep hover:underline">{{ b.label }}</a>
              <span v-else-if="b.ada" class="text-[11px] text-stone-400">{{ b.label }} · terjaga</span>
            </template>
          </div>

          <div v-for="j in (o.rujukan ?? [])" :key="j.id" class="mt-1 text-[11px] text-stone-500">
            Rujukan <span class="num">{{ j.tanggal }}</span> · {{ j.dokter }}
            <span v-if="j.poliklinik"> · {{ j.poliklinik }}</span>
            <a v-if="j.url" :href="j.url" target="_blank" rel="noopener"
               class="ml-1 text-cam-lime-deep hover:underline">buka surat</a>
            <span v-else-if="j.ada" class="ml-1 text-stone-400">· terjaga</span>
          </div>
        </li>
      </ul>
    </section>

    <!-- ══════════ 3. Persetujuan ══════════ -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 space-y-3">
      <h3 class="font-bold text-[14px] text-cam-ink">Persetujuan</h3>

      <Langkah :alur="props.alur" />

      <!-- Catatan tiap langkah ditampilkan, bukan hanya keadaannya.
           "Dikembalikan" tanpa alasan memaksa yang mengajukan menebak
           apa yang kurang, lalu mengajukan ulang dengan kekurangan yang
           sama. -->
      <ul v-if="(props.alur as any[])?.some(l => l.catatan || l.pada)"
          class="divide-y divide-stone-100 text-[11.5px]">
        <li v-for="l in (props.alur as any[])" :key="l.urutan">
          <div v-if="l.catatan || l.pada" class="py-2">
            <span class="font-medium text-cam-ink">{{ l.label }}</span>
            <span v-if="l.oleh" class="text-stone-500"> · {{ l.oleh }}</span>
            <span v-if="l.pada" class="text-stone-400 num"> · {{ l.pada }}</span>
            <p v-if="l.catatan" class="text-stone-600 mt-0.5">{{ l.catatan }}</p>
          </div>
        </li>
      </ul>

      <div v-if="props.bolehTindak" class="flex flex-wrap items-end gap-2 border-t border-stone-100 pt-3">
        <input v-model="tindakan.catatan" placeholder="Catatan peninjau (opsional)"
               class="w-72 rounded-lg border-stone-200 text-[12px]" aria-label="Catatan peninjau">
        <button class="eq-btn-utama" :disabled="tindakan.processing" @click="tindak('setuju')">Setujui</button>
        <button class="eq-btn-lain" :disabled="tindakan.processing" @click="tindak('dikembalikan')">Kembalikan</button>
        <button class="eq-btn-lain" :disabled="tindakan.processing" @click="tindak('tolak')">Tolak</button>
      </div>

      <p v-else-if="!props.bolehAjukanUlang" class="text-[11.5px] text-stone-400 border-t border-stone-100 pt-3">
        Tidak ada langkah yang menunggu tindakan Anda.
      </p>

      <!-- Pengajuan yang DIKEMBALIKAN dapat dikirim ulang. Sebelum ini
           ia hanya turun menjadi draf dan berhenti di sana: alurnya
           menyimpan langkah "dikembalikan", tidak ada langkah yang
           menunggu siapa pun, dan tidak ada tindakan yang dapat
           menjalankannya lagi. -->
      <div v-if="props.bolehAjukanUlang" class="border-t border-stone-100 pt-3 space-y-2">
        <p class="text-[11.5px] text-stone-600">
          Pengajuan ini dikembalikan untuk diperbaiki. Sesudah lampirannya
          dilengkapi, kirim ulang — alurnya diulang dari langkah pertama.
        </p>
        <button class="eq-btn-utama" :disabled="ulang.processing" @click="ajukanUlang">
          Ajukan ulang
        </button>
        <p v-if="ulang.errors.ajukanUlang" class="text-[11.5px] text-red-600">
          {{ ulang.errors.ajukanUlang }}
        </p>
      </div>

      <p v-if="tindakan.errors.keadaan" class="text-[11.5px] text-red-600">{{ tindakan.errors.keadaan }}</p>
    </section>
  </div>
</template>

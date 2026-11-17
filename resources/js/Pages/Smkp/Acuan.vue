<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import KepalaHalaman from '../../Components/Smkp/KepalaHalaman.vue';
import KriteriaNilai from '../../Components/Smkp/KriteriaNilai.vue';
import Ikon from '../../Components/Smkp/Ikon.vue';
import type { Rubrik } from '../../smkp/types';

interface Sub {
  kode: string; nama: string; ref: string | null; maks: number;
  subsub: Array<{ kode: string; nama: string; maks: number | null }>;
  butir: Array<{ kode: string; nama: string; maks: number; rubrik: Rubrik | null }>;
}

const props = defineProps<{
  elemen: Array<{ kode: string; nama: string; bobot: number; maks: number; sub: Sub[] }>;
  meta: { title?: string; basis?: string; catatan?: string; total_nilai?: number; selisih?: Array<{ kode: string; ket: string }> };
  kategori: Array<{ kode: string; label: string; min: number; warna: string; ket?: string }>;
  tingkat: Array<{ min: number; label: string; warna: string }>;
  ringkas: { elemen: number; subElemen: number; butir: number; maks: number };
  alur: Array<{ nomor: string; judul: string; ket: string; warna: string; langkah: Array<{ judul: string; jenis: string }> }>;
}>();

const buka = ref<string | null>(props.elemen[0]?.kode ?? null);
const rubrikBuka = ref<Record<string, boolean>>({});
const urut = [...props.tingkat].sort((a, b) => b.min - a.min);
const rentang = (i: number) => (i === 0 ? `≥ ${urut[0].min}` : `${urut[i].min} – ${urut[i - 1].min - 1}`);
</script>

<template>
  <div class="smkp-ui">
    <Head title="Kriteria Audit SMKP" />
    <KepalaHalaman judul="Kriteria Audit SMKP Minerba" :sub="`${meta.basis ?? 'Kepdirjen Minerba No. 185.K/37.04/DJB/2019 — Lampiran II'}. Struktur elemen, bobot, nilai maksimum, dan kriteria tiap nilai yang dipakai seluruh formulir penilaian.`"
                   :jejak="[{ label: 'Audit SMKP', url: '/smkp' }, { label: 'Kriteria & Acuan' }]" />

    <section class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 mb-5">
      <div v-for="r in [['Elemen', ringkas.elemen, 'lapisan'], ['Sub-elemen', ringkas.subElemen, 'daftar'], ['Butir dinilai', ringkas.butir, 'clipboard-check'], ['Nilai maksimum', ringkas.maks, 'target']]" :key="String(r[0])" class="kartu p-5 flex items-center gap-4">
        <span class="grid place-items-center w-11 h-11 rounded-xl bg-brand-50 text-brand-600"><Ikon :nama="String(r[2])" class="w-5 h-5" /></span>
        <div><p class="text-[24px] font-bold text-navy-900 leading-none tabular-nums">{{ r[1] }}</p><p class="text-[12px] text-slate-500 mt-1">{{ r[0] }}</p></div>
      </div>
    </section>

    <section class="grid lg:grid-cols-2 gap-5 mb-5">
      <div class="kartu p-5 sm:p-6">
        <h2 class="kartu-judul">Kategori temuan</h2>
        <p class="kartu-sub mb-4">Diturunkan dari capaian nilai tiap sub-elemen — bukan dipilih auditor.</p>
        <div class="space-y-2.5">
          <div v-for="k in kategori" :key="k.kode" class="flex items-start gap-3 rounded-xl border border-slate-100 p-3.5">
            <i class="mt-1 w-3 h-3 rounded-full shrink-0" :style="{ background: k.warna }" />
            <div><p class="text-[13.5px] font-semibold text-navy-900">{{ k.label }}</p><p class="text-[12px] text-slate-500">{{ k.ket }}</p></div>
          </div>
        </div>
      </div>
      <div class="kartu p-5 sm:p-6">
        <h2 class="kartu-judul">Tingkat penerapan</h2>
        <p class="kartu-sub mb-4">Ditentukan dari nilai akhir audit berskala 0–100.</p>
        <div class="space-y-2.5">
          <div v-for="(t, i) in urut" :key="t.label" class="flex items-center gap-3 rounded-xl border border-slate-100 p-3.5">
            <i class="w-3 h-3 rounded-full shrink-0" :style="{ background: t.warna }" />
            <p class="flex-1 text-[13.5px] font-semibold text-navy-900">{{ t.label }}</p>
            <p class="text-[12.5px] text-slate-500 tabular-nums">{{ rentang(i) }}</p>
          </div>
        </div>
        <p class="mt-4 text-[12px] text-slate-500 leading-relaxed">Nilai elemen = capaian × bobot. Nilai akhir = Σ nilai elemen ÷ Σ bobot elemen yang berlaku × 100. Butir N/A dikeluarkan dari pembagi; butir belum dinilai dihitung nol.</p>
      </div>
    </section>

    <section class="kartu p-5 sm:p-6 mb-5">
      <h2 class="kartu-judul mb-4">Alur audit</h2>
      <div class="grid md:grid-cols-2 xl:grid-cols-4 gap-4">
        <div v-for="b in alur" :key="b.nomor" class="rounded-xl border border-slate-100 p-4">
          <span class="lencana text-white uppercase tracking-wide" :style="{ background: b.warna }">{{ b.nomor }}</span>
          <p class="mt-2.5 text-[14px] font-bold text-navy-900">{{ b.judul }}</p>
          <p class="mt-1 text-[12px] text-slate-500 leading-relaxed">{{ b.ket }}</p>
          <ul class="mt-3 space-y-1.5">
            <li v-for="l in b.langkah" :key="l.judul" class="flex items-center gap-2 text-[12px] text-slate-600">
              <Ikon :nama="l.jenis === 'cetak' ? 'cetak' : 'centang'" class="w-3.5 h-3.5 text-slate-400" /> {{ l.judul }}
            </li>
          </ul>
        </div>
      </div>
    </section>

    <section class="kartu p-5 sm:p-6 mb-5">
      <div class="flex items-start gap-3">
        <span class="grid place-items-center w-10 h-10 rounded-xl bg-teal-50 text-teal-700 shrink-0"><Ikon nama="buku" class="w-5 h-5" /></span>
        <div class="min-w-0">
          <h2 class="kartu-judul">Sumber nilai maksimum</h2>
          <p class="mt-1 text-[12.5px] text-slate-600 leading-relaxed">{{ meta.catatan }}</p>
        </div>
      </div>
      <div v-if="meta.selisih?.length" class="mt-4 rounded-xl border border-amber-200 bg-amber-50/60 p-4">
        <p class="text-[12.5px] font-bold text-amber-800 flex items-center gap-1.5"><Ikon nama="info" class="w-4 h-4" /> Koreksi menurut Daftar Koreksi dan Klarifikasi Lampiran II, serta catatan pembacaan Formulir Kriteria Audit (hal. 605–608)</p>
        <ul class="mt-2 space-y-1.5">
          <li v-for="x in meta.selisih" :key="x.kode" class="flex gap-3 text-[12px] text-slate-700 leading-relaxed">
            <b class="w-28 shrink-0 tabular-nums text-navy-900">{{ x.kode }}</b><span>{{ x.ket }}</span>
          </li>
        </ul>
      </div>
    </section>

    <section class="space-y-3">
      <article v-for="e in elemen" :key="e.kode" class="kartu overflow-hidden">
        <button type="button" class="w-full text-left flex flex-wrap items-center gap-3 px-5 py-4 hover:bg-slate-50/60" @click="buka = buka === e.kode ? null : e.kode">
          <span class="grid place-items-center min-w-[36px] h-9 px-2 rounded-lg bg-navy-900 text-white text-[12px] font-bold tabular-nums">{{ e.kode }}</span>
          <span class="flex-1 text-[14px] font-bold text-navy-900">{{ e.nama }}</span>
          <span class="text-[12px] text-slate-500">bobot <b class="text-navy-900">{{ e.bobot }}</b> · {{ e.sub.length }} sub-elemen · maks <b class="text-navy-900 tabular-nums">{{ e.maks }}</b></span>
          <Ikon :nama="buka === e.kode ? 'chevron-up' : 'chevron-down'" class="w-4 h-4 text-slate-400" />
        </button>
        <div v-if="buka === e.kode" class="border-t border-slate-100 divide-y divide-slate-100">
          <div v-for="s in e.sub" :key="s.kode" class="px-4 sm:px-5 py-4">
            <div class="flex flex-wrap items-start gap-x-3 gap-y-1">
              <span class="w-14 shrink-0 font-bold text-brand-600 tabular-nums text-[13px]">{{ s.kode }}</span>
              <div class="flex-1 min-w-0">
                <p class="text-[13.5px] font-semibold text-navy-900 leading-snug">{{ s.nama }}</p>
                <p class="text-[11.5px] text-slate-400 mt-0.5">Uraian penerapan {{ s.ref ?? '—' }}<template v-if="s.subsub.length"> · {{ s.subsub.length }} sub-sub elemen</template></p>
              </div>
              <span class="lencana bg-navy-900 text-white tabular-nums" title="Nilai maksimal sub-elemen">maks {{ s.maks }}</span>
            </div>
            <ul class="mt-2.5 sm:ml-[68px] space-y-1.5">
              <li v-for="b in s.butir" :key="b.kode" class="rounded-xl border border-slate-100 bg-slate-50/50">
                <button type="button" class="w-full flex items-center gap-2.5 px-3 py-2 text-left rounded-xl hover:bg-white transition"
                        :aria-expanded="!!rubrikBuka[b.kode]" @click="rubrikBuka[b.kode] = !rubrikBuka[b.kode]">
                  <span v-if="s.subsub.length" class="w-16 shrink-0 tabular-nums text-[12px] font-semibold text-slate-500">{{ b.kode }}</span>
                  <span class="flex-1 min-w-0 text-[12.5px]" :class="s.subsub.length ? 'text-slate-700' : 'text-slate-500'">{{ s.subsub.length ? b.nama : 'Kriteria nilai butir' }}</span>
                  <span class="hidden sm:flex items-center gap-0.5" aria-hidden="true">
                    <i v-for="n in b.maks" :key="n" class="w-1.5 h-3 rounded-sm bg-brand-500/70" />
                  </span>
                  <span class="lencana bg-white border border-slate-200 text-navy-900 tabular-nums">maks {{ b.maks }}</span>
                  <Ikon nama="chevron-down" class="w-4 h-4 text-slate-400 transition-transform" :class="rubrikBuka[b.kode] ? 'rotate-180' : ''" />
                </button>
                <div v-if="rubrikBuka[b.kode]" class="px-3 pb-3">
                  <KriteriaNilai :rubrik="b.rubrik" :maks="b.maks" />
                </div>
              </li>
            </ul>
          </div>
        </div>
      </article>
    </section>
  </div>
</template>

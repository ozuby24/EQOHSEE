<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import KepalaHalaman from '../../Components/Smkp/KepalaHalaman.vue';
import KriteriaNilai from '../../Components/Smkp/KriteriaNilai.vue';
import Ikon from '../../Components/Smkp/Ikon.vue';
import type { Rubrik } from '../../smkp/types';
import { angka, tanggalPanjang } from '../../smkp/lib/format';
import { useDialog } from '../../dialog';
import Dialog from '../../Components/Dialog.vue';

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
  /** Peta keterkaitan antar elemen (port DBEST #23) — resources/data/smkp/keterkaitan.json. */
  keterkaitan?: {
    meta: { judul?: string; sumber?: string; catatan?: string };
    kelompok: Array<{ judul: string; tautan: Array<{ a: string[]; b: string[]; label: string; uraian: string; umum?: boolean }> }>;
  };
  /** Rata-rata nasional FR/SR/ASR/MFR per tahun Surat Edaran (port DBEST #23). */
  rataNasional?: Array<{ tahun: number; nomor: string; tanggal: string | null; fr: number; sr: number; asr: number; mfr: number; sumber: string; catatan: string | null; bawaan: boolean; diubah: boolean }>;
  tahunIni?: number;
  bisaKelola?: boolean;
}>();

/* ───────── Rata-rata nasional ───────── */
const seDipakai = computed(() => {
  let pilih: number | null = null;
  for (const r of props.rataNasional ?? []) if (pilih === null || r.tahun <= (props.tahunIni ?? 0)) pilih = r.tahun;
  return pilih;
});
const seUrut = computed(() => [...(props.rataNasional ?? [])].sort((a, b) => b.tahun - a.tahun));
const formSe = useForm({ tahun: String(props.tahunIni ?? ''), nomor: '', tanggal: '', fr: '', sr: '', asr: '', mfr: '', catatan: '' });
function ubahSe(r: NonNullable<typeof props.rataNasional>[number]) {
  const t = (v: number) => String(v).replace('.', ',');
  Object.assign(formSe, { tahun: String(r.tahun), nomor: r.nomor, tanggal: r.tanggal ?? '', fr: t(r.fr), sr: t(r.sr), asr: t(r.asr), mfr: t(r.mfr), catatan: r.catatan ?? '' });
  document.getElementById('form-se')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
function simpanSe() {
  formSe.post('/smkp/acuan/rata-nasional', { preserveScroll: true, onSuccess: () => formSe.reset() });
}
/* Penegasan memakai dialog EQOHSEE — confirm() bawaan dibungkam di webview ponsel. */
const { dialog, tanya, batal, lanjut } = useDialog();
async function hapusSe(tahun: number, bawaan: boolean) {
  const ya = await tanya({
    judul: bawaan ? `Kembalikan nilai bawaan SE ${tahun}?` : `Hapus Surat Edaran ${tahun}?`,
    pesan: bawaan ? 'Isian administrator untuk tahun ini dihapus; audit kembali memakai nilai bawaan Surat Edaran.' : 'Audit tahun ini dan sesudahnya akan memakai Surat Edaran terbaru sebelumnya.',
    labelAksi: bawaan ? 'Kembalikan' : 'Hapus', nada: 'bahaya',
  });
  if (!ya) return;
  router.delete(`/smkp/acuan/rata-nasional/${tahun}`, { preserveScroll: true });
}

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

    <section v-if="rataNasional?.length" id="rata-nasional" class="kartu p-5 sm:p-6 mb-5 scroll-mt-20">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="kartu-judul">Rata-rata nasional FR, SR, ASR, MFR</h2>
          <p class="kartu-sub">Dari Surat Edaran Dirjen Minerba. Audit memakai SE terbaru yang terbit sampai tahun auditnya — untuk saran faktor penyesuaian hari kerja Tahap I dan evaluasi kinerja keselamatan.</p>
        </div>
        <span v-if="seDipakai" class="lencana bg-teal-50 text-teal-800">Audit {{ tahunIni }} memakai SE {{ seDipakai }}</span>
      </div>
      <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200">
        <table class="w-full min-w-[640px] text-[12.5px]">
          <thead class="bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-[0.06em] text-slate-500">
            <tr><th class="px-3 py-2">Tahun</th><th class="px-3 py-2">Surat Edaran</th><th class="px-3 py-2 text-right">FR</th><th class="px-3 py-2 text-right">SR</th><th class="px-3 py-2 text-right">ASR</th><th class="px-3 py-2 text-right">MFR</th><th v-if="bisaKelola" class="px-3 py-2" /></tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="r in seUrut" :key="r.tahun" :class="r.tahun === seDipakai ? 'bg-teal-50/40' : ''">
              <td class="px-3 py-2 font-semibold text-navy-900 tabular-nums">{{ r.tahun }}</td>
              <td class="px-3 py-2">
                <span class="text-slate-700">{{ r.nomor }}</span>
                <span v-if="r.tanggal" class="block text-[11.5px] text-slate-400">{{ tanggalPanjang(r.tanggal) }}</span>
                <span class="mt-0.5 inline-block rounded-full px-2 py-0.5 text-[10.5px] font-semibold" :class="r.diubah ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-500'">{{ r.diubah ? 'isian administrator' : 'bawaan sistem' }}</span>
              </td>
              <td v-for="k in (['fr', 'sr', 'asr', 'mfr'] as const)" :key="k" class="px-3 py-2 text-right tabular-nums text-navy-900">{{ angka(r[k], 2) }}</td>
              <td v-if="bisaKelola" class="px-3 py-2 text-right whitespace-nowrap">
                <button type="button" class="text-[12px] font-semibold text-teal-700 hover:underline" @click="ubahSe(r)">Ubah</button>
                <button v-if="r.diubah" type="button" class="ml-3 text-[12px] font-semibold text-rose-600 hover:underline" @click="hapusSe(r.tahun, r.bawaan)">{{ r.bawaan ? 'Kembalikan bawaan' : 'Hapus' }}</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <form v-if="bisaKelola" id="form-se" class="mt-4 rounded-xl border border-slate-200 bg-slate-50/60 p-4" @submit.prevent="simpanSe">
        <p class="text-[12.5px] font-semibold text-navy-900">Tambah / perbarui Surat Edaran</p>
        <p class="text-[11.5px] text-slate-500">Satu baris per tahun SE; isian bertahun sama menimpa bawaan. Koma desimal boleh (mis. 0,09).</p>
        <div class="mt-3 grid gap-3 sm:grid-cols-4">
          <label class="block"><span class="label">Tahun</span><input v-model="formSe.tahun" inputmode="numeric" class="input" placeholder="2025"></label>
          <label class="block sm:col-span-2"><span class="label">Nomor Surat Edaran</span><input v-model="formSe.nomor" class="input" maxlength="120" placeholder="B-9669/MB.07/DBT.KP/2025"></label>
          <label class="block"><span class="label">Tanggal</span><input v-model="formSe.tanggal" type="date" class="input"></label>
          <label v-for="[k, l] in ([['fr', 'FR'], ['sr', 'SR'], ['asr', 'ASR'], ['mfr', 'MFR']] as const)" :key="k" class="block">
            <span class="label">{{ l }}</span><input v-model="formSe[k]" inputmode="decimal" class="input tabular-nums">
          </label>
          <label class="block sm:col-span-4"><span class="label">Catatan <span class="font-normal text-slate-400">(opsional)</span></span><input v-model="formSe.catatan" class="input" maxlength="500"></label>
        </div>
        <p v-for="(g, k) in formSe.errors" :key="k" class="galat">{{ g }}</p>
        <div class="mt-3 flex justify-end"><button type="submit" class="btn-utama btn-kecil" :disabled="formSe.processing"><Ikon nama="simpan" class="w-4 h-4" /> Simpan Surat Edaran</button></div>
      </form>
    </section>

    <section v-if="keterkaitan?.kelompok?.length" id="keterkaitan" class="kartu p-5 sm:p-6 mb-5 scroll-mt-20">
      <div class="flex items-start gap-3">
        <span class="grid place-items-center w-10 h-10 rounded-xl bg-sky-50 text-sky-700 shrink-0"><Ikon nama="cabang" class="w-5 h-5" /></span>
        <div class="min-w-0">
          <h2 class="kartu-judul">{{ keterkaitan.meta.judul || 'Keterkaitan antar elemen' }}</h2>
          <p class="mt-1 text-[12.5px] text-slate-600 leading-relaxed">
            Acuan penilaian: di halaman Penilaian setiap sub elemen memajang keterkaitannya, status sisi terkait, dan peringatan
            bila hasilnya tidak sejalan. <span v-if="keterkaitan.meta.sumber">{{ keterkaitan.meta.sumber }}</span>
          </p>
        </div>
      </div>
      <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <div v-for="g in keterkaitan.kelompok" :key="g.judul" class="rounded-xl border border-slate-100 p-4">
          <p class="text-[13px] font-bold text-navy-900">{{ g.judul }}</p>
          <ul class="mt-2.5 space-y-2.5">
            <li v-for="t in g.tautan" :key="t.label" class="text-[12.5px] leading-relaxed">
              <p class="flex flex-wrap items-center gap-1.5">
                <b v-for="k in t.a" :key="`a${k}`" class="rounded bg-slate-100 px-1.5 tabular-nums text-navy-900">{{ k }}</b>
                <span class="text-slate-400">↔</span>
                <b v-for="k in t.b" :key="`b${k}`" class="rounded bg-slate-100 px-1.5 tabular-nums text-navy-900">{{ k }}</b>
                <span class="font-semibold text-navy-900">{{ t.label }}</span>
              </p>
              <p class="mt-0.5 text-slate-600">{{ t.uraian }}</p>
            </li>
          </ul>
        </div>
      </div>
      <p v-if="keterkaitan.meta.catatan" class="mt-3 text-[12px] text-slate-500">{{ keterkaitan.meta.catatan }}</p>
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
    <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
  </div>
</template>

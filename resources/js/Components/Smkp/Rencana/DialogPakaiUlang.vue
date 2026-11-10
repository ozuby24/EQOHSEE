<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Ikon from '../Ikon.vue';
import BingkaiDialog from './BingkaiDialog.vue';
import { ambilJson, BAGIAN_SALIN, pesanGalat } from './api';
import type { IsiSalin, KunciBagian, SumberRencana } from './api';

/*
 * Pakai ulang Rencana Audit dari audit lain (tahun lalu atau perusahaan
 * lain dalam jangkauan): pilih sumber, bagian yang disalin, dan cara
 * menerapkannya. Hasilnya diterapkan ke formulir — belum tersimpan.
 */
const props = defineProps<{ auditId: number }>();
const buka = defineModel<boolean>('buka', { required: true });
const emit = defineEmits<{ terapkan: [hasil: { sumber: SumberRencana; isi: IsiSalin; bagian: KunciBagian[]; timpa: boolean }] }>();

const daftar = ref<SumberRencana[] | null>(null);
const total = ref(51);
const memuatDaftar = ref(false);
const galatDaftar = ref<string | null>(null);

const pilih = ref<number | null>(null);
const cache = new Map<number, IsiSalin>();
const isi = ref<IsiSalin | null>(null);
const memuatIsi = ref(false);
const galatIsi = ref<string | null>(null);

const bagian = ref<KunciBagian[]>(['kriteria']);
const timpa = ref(false);
const cari = ref('');

async function muatDaftar() {
  memuatDaftar.value = true;
  galatDaftar.value = null;
  try {
    const r = await ambilJson<{ sumber: SumberRencana[]; total: number }>(`/smkp/${props.auditId}/rencana/sumber`);
    daftar.value = r.sumber;
    total.value = r.total;
    if (pilih.value === null && r.sumber[0]) pilih.value = r.sumber[0].id;
  } catch (e) {
    galatDaftar.value = pesanGalat(e);
  } finally {
    memuatDaftar.value = false;
  }
}

watch(buka, (b) => { if (b && !daftar.value && !memuatDaftar.value) muatDaftar(); }, { immediate: true });

watch(pilih, async (id) => {
  isi.value = null;
  galatIsi.value = null;
  memuatIsi.value = false;
  if (id === null) return;
  if (cache.has(id)) { isi.value = cache.get(id)!; return; }
  memuatIsi.value = true;
  try {
    const r = await ambilJson<{ isi: IsiSalin }>(`/smkp/${props.auditId}/rencana/sumber/${id}`);
    cache.set(id, r.isi);
    if (pilih.value === id) isi.value = r.isi;
  } catch (e) {
    if (pilih.value === id) galatIsi.value = pesanGalat(e);
  } finally {
    if (pilih.value === id) memuatIsi.value = false;
  }
});

const sumber = computed(() => daftar.value?.find((s) => s.id === pilih.value) ?? null);
const tersaring = computed(() => {
  const q = cari.value.trim().toLowerCase();
  return (daftar.value ?? []).filter((s) => !q || `${s.judul} ${s.perusahaan} ${s.tahun}`.toLowerCase().includes(q));
});

const ada = (v: string | undefined) => !!(v ?? '').trim();
function jumlah(k: KunciBagian): number {
  const x = isi.value;
  if (!x) return 0;
  switch (k) {
    case 'kriteria': return Object.keys(x.metode_sampel ?? {}).length;
    case 'metode': return [x.metode, x.sampel].filter(ada).length;
    case 'lingkup': return [x.jenis_audit, x.tujuan, x.kriteria, x.ruang_lingkup, x.pengecualian].filter(ada).length;
    case 'risiko': return x.risiko.present.length + x.risiko.future.length;
    case 'sumberdaya': return [x.dokumentasi, x.sumberdaya, x.distribusi].filter(ada).length;
    case 'tim': return x.tugas.length;
  }
}
function satuan(k: KunciBagian, n: number): string {
  if (!n) return 'kosong';
  return k === 'kriteria' ? `${n}/${total.value} kriteria` : k === 'risiko' ? `${n} risiko` : k === 'tim' ? `${n} auditor` : `${n} isian`;
}

const bagianBerisi = computed(() => bagian.value.filter((k) => jumlah(k) > 0));

function terapkan() {
  if (!sumber.value || !isi.value || !bagianBerisi.value.length) return;
  emit('terapkan', { sumber: sumber.value, isi: isi.value, bagian: bagianBerisi.value, timpa: timpa.value });
  buka.value = false;
}

const WARNA_STATUS: Record<string, string> = {
  draft: 'bg-slate-100 text-slate-600',
  berjalan: 'bg-brand-50 text-brand-700',
  selesai: 'bg-hijau-50 text-hijau-700',
};
</script>

<template>
  <BingkaiDialog v-model:buka="buka" judul="Pakai ulang Rencana Audit" ikon="salin" lebar="max-w-4xl"
                 sub="Salin isian Rencana Audit lain sebagai acuan, lalu sesuaikan dengan audit ini dan simpan.">
    <div class="grid md:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)] gap-5 md:gap-6">
      <!-- 1. Sumber -->
      <section class="min-w-0">
        <div class="flex items-center justify-between gap-2 mb-2">
          <p class="eyebrow">1 · Audit sumber</p>
          <span v-if="daftar?.length" class="text-[11.5px] text-slate-400 tabular-nums">{{ daftar.length }} rencana</span>
        </div>
        <div v-if="(daftar?.length ?? 0) > 5" class="relative mb-2">
          <Ikon nama="cari" class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" />
          <input v-model="cari" type="search" class="input-kecil pl-8" placeholder="Cari perusahaan atau tahun" aria-label="Cari audit sumber">
        </div>

        <div v-if="memuatDaftar" class="space-y-2" aria-busy="true">
          <div v-for="n in 3" :key="n" class="rounded-xl border border-slate-200 p-3 animate-pulse space-y-2">
            <div class="h-3.5 w-1/2 rounded bg-slate-200" /><div class="h-3 w-3/4 rounded bg-slate-100" />
          </div>
        </div>
        <div v-else-if="galatDaftar" class="rounded-xl border border-rose-200 bg-rose-50 p-3.5 text-[12.5px] text-rose-700">
          <p class="flex gap-2"><Ikon nama="peringatan" class="w-4 h-4 shrink-0 mt-px" /> {{ galatDaftar }}</p>
          <button type="button" class="btn-garis btn-kecil mt-2.5" @click="muatDaftar"><Ikon nama="riwayat" class="w-3.5 h-3.5" /> Coba lagi</button>
        </div>
        <div v-else-if="daftar && !daftar.length" class="rounded-xl border border-dashed border-slate-300 p-5 text-center">
          <Ikon nama="folder" class="w-6 h-6 mx-auto text-slate-400" />
          <p class="mt-2 text-[13px] font-semibold text-navy-900">Belum ada Rencana Audit lain</p>
          <p class="mt-1 text-[12px] text-slate-500 leading-relaxed">Tidak ada audit lain dalam jangkauan akun Anda yang Rencana Audit-nya sudah diisi. Pakai <b>Impor berkas</b> untuk memakai dokumen lama.</p>
        </div>
        <ul v-else class="space-y-2 max-h-[48vh] overflow-y-auto pr-1 scrollbar-tipis" role="radiogroup" aria-label="Audit sumber">
          <li v-for="s in tersaring" :key="s.id">
            <label class="flex items-start gap-3 rounded-xl border p-3 cursor-pointer transition"
                   :class="pilih === s.id ? 'border-brand-500 bg-brand-50/50 ring-2 ring-brand-500/15' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50'">
              <input v-model="pilih" type="radio" name="sumber-rencana" :value="s.id" class="mt-0.5 border-slate-300 text-brand-600 focus:ring-brand-500">
              <span class="min-w-0 flex-1">
                <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
                  <span class="text-[13px] font-semibold text-navy-900">{{ s.judul }}</span>
                  <span class="lencana bg-white border border-slate-200 text-slate-600 tabular-nums">{{ s.tahun }}</span>
                </span>
                <span class="block mt-0.5 text-[12px] text-slate-500 truncate">{{ s.perusahaan }}</span>
                <span class="mt-2 flex flex-wrap gap-1.5">
                  <span v-if="s.samaPerusahaan" class="lencana bg-teal-50 text-teal-800"><Ikon nama="gedung" class="w-3 h-3" /> Perusahaan sama</span>
                  <span class="lencana tabular-nums" :class="s.terisi ? 'bg-navy-50 text-navy-800' : 'bg-slate-100 text-slate-500'">{{ s.terisi }}/{{ total }} kriteria</span>
                  <span v-if="s.risiko" class="lencana bg-slate-100 text-slate-600 tabular-nums">{{ s.risiko }} risiko</span>
                  <span v-if="s.tim" class="lencana bg-slate-100 text-slate-600 tabular-nums">{{ s.tim }} auditor</span>
                  <span class="lencana" :class="WARNA_STATUS[s.status] ?? WARNA_STATUS.draft">{{ s.labelStatus }}</span>
                </span>
              </span>
            </label>
          </li>
          <li v-if="!tersaring.length" class="px-3 py-6 text-center text-[12.5px] text-slate-500">Tidak ada audit yang cocok.</li>
        </ul>
      </section>

      <!-- 2–3. Bagian & cara menerapkan -->
      <section class="min-w-0">
        <p class="eyebrow mb-2">2 · Bagian yang disalin</p>
        <div v-if="!pilih" class="rounded-xl border border-dashed border-slate-300 p-4 text-[12.5px] text-slate-500">Pilih audit sumber terlebih dahulu.</div>
        <div v-else-if="galatIsi" class="rounded-xl border border-rose-200 bg-rose-50 p-3.5 text-[12.5px] text-rose-700 flex gap-2">
          <Ikon nama="peringatan" class="w-4 h-4 shrink-0 mt-px" /> {{ galatIsi }}
        </div>
        <div v-else class="rounded-xl border border-slate-200 divide-y divide-slate-100" :aria-busy="memuatIsi">
          <label v-for="b in BAGIAN_SALIN" :key="b.kunci" class="flex items-start gap-3 px-3.5 py-2.5 cursor-pointer transition-colors hover:bg-slate-50"
                 :class="!memuatIsi && !jumlah(b.kunci) ? 'opacity-55 cursor-not-allowed' : ''">
            <input v-model="bagian" type="checkbox" :value="b.kunci" :disabled="memuatIsi || !jumlah(b.kunci)"
                   class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            <span class="min-w-0 flex-1">
              <span class="block text-[12.5px] font-semibold text-navy-900 leading-snug">{{ b.judul }}</span>
              <span class="block text-[11.5px] text-slate-500 leading-snug">{{ b.ket }}</span>
            </span>
            <span v-if="memuatIsi" class="h-4 w-14 rounded bg-slate-100 animate-pulse" />
            <span v-else class="lencana tabular-nums" :class="jumlah(b.kunci) ? 'bg-slate-100 text-slate-700' : 'bg-white text-slate-400'">{{ satuan(b.kunci, jumlah(b.kunci)) }}</span>
          </label>
        </div>

        <p class="eyebrow mt-5 mb-2">3 · Cara menerapkan</p>
        <div class="grid sm:grid-cols-2 gap-2" role="radiogroup" aria-label="Cara menerapkan">
          <label v-for="m in [{ v: false, judul: 'Isi yang masih kosong saja', ket: 'Isian audit ini dipertahankan.' }, { v: true, judul: 'Timpa semua', ket: 'Isian sumber menggantikan isian sekarang.' }]"
                 :key="String(m.v)" class="flex items-start gap-2.5 rounded-xl border p-3 cursor-pointer transition"
                 :class="timpa === m.v ? 'border-brand-500 bg-brand-50/50 ring-2 ring-brand-500/15' : 'border-slate-200 hover:bg-slate-50'">
            <input v-model="timpa" type="radio" name="mode-salin" :value="m.v" class="mt-0.5 border-slate-300 text-brand-600 focus:ring-brand-500">
            <span><span class="block text-[12.5px] font-semibold text-navy-900">{{ m.judul }}</span><span class="block text-[11.5px] text-slate-500">{{ m.ket }}</span></span>
          </label>
        </div>

        <p class="bantu mt-3 flex gap-1.5"><Ikon nama="info" class="w-3.5 h-3.5 shrink-0 mt-px" /> Nomor formulir, tanggal, susunan kegiatan, dan pengesahan tidak ikut disalin. Hasilnya tersimpan setelah Anda menekan Simpan Rencana Audit.</p>
      </section>
    </div>

    <template #kaki>
      <button type="button" class="btn-garis" @click="buka = false">Batal</button>
      <button type="button" class="btn-utama" :disabled="!isi || memuatIsi || !bagianBerisi.length" @click="terapkan">
        <Ikon nama="centang" class="w-4 h-4" /> Terapkan ke formulir
      </button>
    </template>
  </BingkaiDialog>
</template>

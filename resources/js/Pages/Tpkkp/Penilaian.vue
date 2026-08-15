<script setup lang="ts">
/**
 * PTPKKP — formulir penilaian.
 *
 * Halaman pertama yang dipindah ke Vue. Yang didapat dari pemindahan ini
 * bukan tampilannya — itu sengaja dibuat sama — melainkan tiga hal yang
 * pada versi Blade mustahil tanpa memuat ulang halaman:
 *
 *   1. Capaian tiap item terhitung ulang begitu angkanya diubah.
 *   2. Kemajuan parameter (berapa item sudah terisi) hidup di layar.
 *   3. Perubahan yang belum tersimpan ditandai, sehingga orang tahu
 *      persis apa yang akan hilang bila menutup halaman.
 *
 * Aturan kategorinya disalin dari App\Support\Tpkkp::category(). Ini satu
 * -satunya perhitungan yang kini ada di dua tempat — server tetap yang
 * menentukan saat menyimpan, dan angka di layar hanyalah bantuan baca.
 * Uji memastikan kedua ambangnya tidak berpisah diam-diam.
 */
import { computed, reactive, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PickerTpkkp from '../../Components/PickerTpkkp.vue';
import type { HalamanPenilaian } from '../../types';

const props = defineProps<HalamanPenilaian>();

/* ══════════ state nilai ══════════ */

type Sel = Record<string, Record<string, number | null>>;

const nilai = reactive<Sel>({});
const ket   = reactive<Record<string, string>>({});
const awal  = ref('');

function muat() {
  for (const k of Object.keys(nilai)) delete nilai[k];
  for (const k of Object.keys(ket)) delete ket[k];

  for (const it of props.items) {
    nilai[it.kode] = { ...it.nilai };
    ket[it.kode]   = it.ket;
  }
  awal.value = JSON.stringify({ nilai, ket });
}
muat();

// Data halaman berganti saat metode atau parameter dipilih; state harus
// ikut dimuat ulang, kalau tidak nilai parameter sebelumnya tertinggal
// di layar dan tampak seolah milik parameter yang baru.
watch(() => props.items, muat);

const kotor = computed(() => JSON.stringify({ nilai, ket }) !== awal.value);

/* ══════════ hitungan hidup ══════════ */

/**
 * Kategori dari rasio capaian.
 *
 * Ambangnya datang dari server (props.ambang), bukan ditulis di sini.
 * Salinan yang ditulis tangan sempat ada di berkas ini dan isinya keliru
 * seluruhnya — batas maupun nama tingkatnya tidak ada di acuan. Cacat
 * seperti itu tidak menimbulkan galat apa pun; lencananya hanya menyebut
 * tingkat kematangan yang salah, dan itu justru angka yang dibaca orang.
 */
function kategori(rasio: number | null) {
  if (rasio === null) return null;

  for (const a of props.ambang) if (rasio < a.batas) return a;

  return props.ambang[props.ambang.length - 1] ?? null;
}

/** Capaian satu item: rerata sel terisi dibagi nilai penuh. */
function capaian(kode: string): number | null {
  const sel = nilai[kode];
  if (!sel) return null;

  const isi = Object.values(sel).filter((v): v is number => v !== null && v !== undefined);
  if (!isi.length) return null;

  const jumlahSel = Object.keys(sel).length;
  return isi.reduce((a, b) => a + b, 0) / (jumlahSel * 5);
}

function terisi(kode: string): number {
  return Object.values(nilai[kode] ?? {}).filter((v) => v !== null && v !== undefined).length;
}

const kemajuan = computed(() => {
  let isi = 0;
  let total = 0;

  for (const it of props.items) {
    const sel = Object.keys(nilai[it.kode] ?? {});
    total += sel.length;
    isi   += terisi(it.kode);
  }

  return { isi, total, persen: total ? Math.round((isi / total) * 100) : 0 };
});

/* ══════════ perpindahan ══════════ */

const kunciSel = computed(() => (props.entitas.length ? props.entitas : ['_']));

function buka(m: string, p?: string | null) {
  if (kotor.value && !confirm('Ada perubahan yang belum tersimpan. Tinggalkan halaman ini?')) return;

  router.get('/tpkkp/penilaian', { m, ...(p ? { p } : {}) }, {
    preserveScroll: false,
    preserveState: false,
  });
}

const menyimpan = ref(false);

function simpan() {
  menyimpan.value = true;

  // Bentuk kiriman sengaja sama persis dengan versi Blade — n[kode][sel]
  // dan ket[kode] — supaya saveAssess() di server tidak perlu diubah
  // sama sekali dan tetap melayani kedua tampilan.
  router.post(`/tpkkp/penilaian?tahun=${props.tahun}`,
    { metode: props.metodeAktif, n: nilai, ket },
    {
      preserveScroll: true,
      onSuccess: () => { awal.value = JSON.stringify({ nilai, ket }); },
      onFinish:  () => { menyimpan.value = false; },
    });
}

const bukaRubrik = reactive<Record<string, boolean>>({});
const bukaTarget = reactive<Record<string, boolean>>({});
</script>

<template>
  <Head title="PTPKKP — Penilaian" />

  <div class="max-w-6xl mx-auto space-y-5">

    <PickerTpkkp v-bind="picker" />

    <!-- ══════════ METODE ══════════ -->
    <div class="bg-white rounded-2xl border border-stone-200 p-4">
      <div class="text-[10px] font-bold uppercase tracking-[0.18em] text-stone-400 mb-2.5">
        Metode Pengukuran
      </div>
      <div class="flex flex-wrap gap-2">
        <button v-for="m in metode" :key="m.kode" type="button" @click="buka(m.kode)"
                class="text-[12px] font-semibold px-3 py-1.5 rounded-lg border transition"
                :class="m.kode === metodeAktif
                  ? 'bg-cam-ink text-white border-cam-ink'
                  : 'bg-white text-stone-600 border-stone-200 hover:border-stone-400'">
          {{ m.kode }} <span class="font-normal opacity-70">· {{ m.nama }}</span>
        </button>
      </div>

      <p class="text-[11.5px] text-stone-500 mt-3">
        <template v-if="entitas.length">
          Dinilai per <b>{{ (metode.find(x => x.kode === metodeAktif)?.labelEntitas || 'entitas').toLowerCase() }}</b>
          ({{ entitas.length }} entitas). Skor metode adalah <b>rerata</b> entitas yang terisi.
        </template>
        <template v-else>Dinilai satu nilai untuk seluruh organisasi.</template>
      </p>
    </div>

    <!-- ══════════ PARAMETER ══════════ -->
    <div class="bg-white rounded-2xl border border-stone-200 p-4">
      <div class="text-[10px] font-bold uppercase tracking-[0.18em] text-stone-400 mb-2.5">
        Parameter yang memakai metode {{ metodeAktif }}
      </div>
      <div class="flex flex-wrap gap-1.5">
        <button v-for="p in parameter" :key="p.kode" type="button" @click="buka(metodeAktif, p.kode)"
                class="text-[11.5px] px-2.5 py-1 rounded-lg border transition"
                :class="p.kode === paramAktif
                  ? 'bg-cam-lime-soft border-cam-lime/40 text-cam-lime-deep font-bold'
                  : 'bg-white text-stone-600 border-stone-200 hover:border-stone-400'">
          <span class="num font-semibold">{{ p.kode }}</span>
          <span class="opacity-60">({{ p.jumlah }})</span>
        </button>
      </div>
    </div>

    <div v-if="!paramAktif || !items.length"
         class="bg-white rounded-2xl border border-stone-200 px-5 py-8 text-center text-[12.5px] text-stone-400">
      Tidak ada item pada kombinasi ini.
    </div>

    <template v-else>
      <!-- ══════════ KEMAJUAN HIDUP ══════════ -->
      <div class="bg-white rounded-2xl border border-stone-200 px-5 py-4">
        <div class="flex items-baseline justify-between gap-3 flex-wrap">
          <div>
            <h3 class="text-[13px] font-bold text-cam-ink">
              {{ paramAktif }} · {{ parameter.find(x => x.kode === paramAktif)?.nama }}
            </h3>
            <p class="text-[11px] text-stone-500 mt-0.5 num">
              bobot {{ paramBobot.toFixed(2) }} · target {{ paramTarget.toFixed(2) }} ·
              {{ items.length }} item memakai metode {{ metodeAktif }}
            </p>
          </div>
          <div class="text-right">
            <span class="text-[18px] font-black num" style="color:var(--eq-aksen,#F57C00)">
              {{ kemajuan.persen }}%
            </span>
            <p class="text-[11px] text-stone-400 num">{{ kemajuan.isi }} / {{ kemajuan.total }} sel terisi</p>
          </div>
        </div>
        <div class="eq-bilah mt-2.5"><i :style="{ width: kemajuan.persen + '%' }"></i></div>
      </div>

      <!-- ══════════ ITEM ══════════ -->
      <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
        <div class="divide-y divide-stone-100">
          <div v-for="it in items" :key="it.kode" class="px-5 py-4">

            <div class="flex flex-wrap items-start justify-between gap-2 mb-3">
              <div class="pr-3 min-w-0">
                <span class="num text-[11px] font-bold text-stone-400">{{ it.kode }}</span>
                <div class="text-[12.5px] font-semibold text-cam-ink leading-snug">{{ it.nama }}</div>
                <div class="text-[10.5px] text-stone-400 mt-1">
                  metode: {{ it.metode.join(' · ') }} · maks {{ it.maks }}
                </div>
              </div>

              <div class="text-right whitespace-nowrap">
                <span v-if="kategori(capaian(it.kode))"
                      class="inline-block text-[10.5px] font-bold text-white px-2.5 py-1 rounded-md"
                      :style="{ background: kategori(capaian(it.kode))!.warna }">
                  {{ kategori(capaian(it.kode))!.label }}
                </span>
                <span v-else class="inline-block text-[10.5px] font-bold px-2.5 py-1 rounded-md
                                    bg-stone-100 text-stone-400">Belum dinilai</span>
                <div class="num text-[11px] text-stone-400 mt-1">
                  {{ terisi(it.kode) }}/{{ kunciSel.length }} terisi
                </div>
              </div>
            </div>

            <!-- kolom nilai -->
            <div class="flex flex-wrap gap-2">
              <label v-for="sel in kunciSel" :key="sel"
                     class="flex items-center gap-2 rounded-lg border border-stone-200 bg-stone-50 px-2.5 py-1.5">
                <span class="text-[11px] text-stone-500 whitespace-nowrap">
                  {{ sel === '_' ? 'Nilai' : sel }}
                </span>
                <select v-model="nilai[it.kode][sel]" :disabled="!bisaSunting"
                        class="ring-focus rounded-md border border-stone-200 bg-white px-2 py-1
                               text-[12px] font-semibold num">
                  <option :value="null">—</option>
                  <option v-for="i in 5" :key="i" :value="i">{{ i }}</option>
                </select>
              </label>
            </div>

            <input v-model="ket[it.kode]" type="text" :disabled="!bisaSunting"
                   placeholder="Keterangan / bukti (opsional)"
                   class="ring-focus w-full mt-2.5 rounded-lg border border-stone-200 bg-white
                          px-3 py-2 text-[12px]">

            <div v-if="it.target" class="mt-2">
              <button type="button" class="text-[11px] font-semibold text-stone-500"
                      @click="bukaTarget[it.kode] = !bukaTarget[it.kode]">
                Target sampel / dokumen {{ bukaTarget[it.kode] ? '▾' : '▸' }}
              </button>
              <pre v-if="bukaTarget[it.kode]"
                   class="text-[11px] text-stone-600 whitespace-pre-wrap mt-1.5 bg-stone-50
                          rounded-lg p-3 border border-stone-100">{{ it.target }}</pre>
            </div>

            <div v-if="it.rubrik.length" class="mt-2">
              <button type="button" class="text-[11px] font-semibold text-cam-lime-deep"
                      @click="bukaRubrik[it.kode] = !bukaRubrik[it.kode]">
                Rubrik 5 tingkat {{ bukaRubrik[it.kode] ? '▾' : '▸' }}
              </button>
              <div v-if="bukaRubrik[it.kode]" class="mt-1.5 space-y-1">
                <div v-for="r in it.rubrik" :key="r.tingkat" class="flex gap-2.5 text-[11.5px] leading-snug">
                  <span class="flex-none w-5 h-5 rounded-md text-white text-[10px] font-bold
                               grid place-items-center" :style="{ background: r.warna }">{{ r.tingkat }}</span>
                  <span class="text-stone-600">{{ r.teks }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="bisaSunting"
             class="px-5 py-4 border-t border-stone-100 bg-stone-50 flex items-center
                    justify-between gap-3 flex-wrap sticky bottom-0">
          <p class="text-[11px]" :class="kotor ? 'text-amber-700 font-semibold' : 'text-stone-500'">
            <template v-if="kotor">Ada perubahan yang belum tersimpan.</template>
            <template v-else>Metode yang dibiarkan kosong dihitung nol pada capaian item.</template>
          </p>
          <button type="button" :disabled="menyimpan || !kotor" @click="simpan"
                  class="lime-gradient shadow-glow rounded-xl text-white px-6 py-2.5 text-[13px]
                         font-bold hover:brightness-105 transition disabled:opacity-40
                         disabled:cursor-not-allowed">
            {{ menyimpan ? 'Menyimpan…' : `Simpan nilai ${metodeAktif}` }}
          </button>
        </div>
      </div>
    </template>
  </div>
</template>

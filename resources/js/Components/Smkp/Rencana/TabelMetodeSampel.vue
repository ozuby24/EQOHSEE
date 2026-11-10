<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Ikon from '../Ikon.vue';
import type { MetodeSampel } from './api';

/*
 * Metode & sampel per sub-elemen: akordeon per elemen dengan progres
 * pengisian, pencarian, dan saring "hanya yang kosong". Isian ditulis
 * langsung ke objek formulir induk (form.metode_sampel) sehingga alur
 * simpan Rencana Audit tidak berubah.
 */
interface Elemen { kode: string; nama: string; sub: Array<{ kode: string; nama: string }> }

const props = defineProps<{
  daftar: Elemen[];
  nilai: Record<string, MetodeSampel>;
  /** kode sub-elemen → asal isian yang baru diterapkan (belum tersimpan) */
  penanda: Record<string, string>;
  saran: string[];
}>();

const idSaran = 'saran-metode-kriteria';
const terbuka = ref<string[]>(props.daftar[0] ? [props.daftar[0].kode] : []);
const cari = ref('');
const hanyaKosong = ref(false);
// Saring "kosong" memakai potret saat dicentang, agar baris yang sedang diisi tidak langsung hilang.
const potretKosong = ref<Set<string> | null>(null);

const isi = (kode: string) => !!(props.nilai[kode]?.metode?.trim() || props.nilai[kode]?.sampel?.trim());

watch(hanyaKosong, (v) => {
  potretKosong.value = v ? new Set(props.daftar.flatMap((e) => e.sub.map((s) => s.kode)).filter((k) => !isi(k))) : null;
});

// Elemen yang baru menerima isian dari pakai ulang/impor dibuka agar langsung terlihat.
watch(() => Object.keys(props.penanda).join('|'), () => {
  const kode = new Set(Object.keys(props.penanda));
  for (const e of props.daftar) {
    if (e.sub.some((s) => kode.has(s.kode)) && !terbuka.value.includes(e.kode)) terbuka.value.push(e.kode);
  }
});

const menyaring = computed(() => !!potretKosong.value || !!cari.value.trim());

const tampil = computed(() => {
  const q = cari.value.trim().toLowerCase();
  return props.daftar
    .map((e) => {
      const cocokElemen = !!q && e.nama.toLowerCase().includes(q);
      const sub = e.sub.filter((s) => (!potretKosong.value || potretKosong.value.has(s.kode))
        && (!q || cocokElemen || s.kode.toLowerCase().includes(q) || s.nama.toLowerCase().includes(q)));
      const terisi = e.sub.filter((s) => isi(s.kode)).length;
      return { ...e, subTampil: sub, terisi, diperbarui: e.sub.filter((s) => props.penanda[s.kode]).length };
    })
    .filter((e) => e.subTampil.length);
});

const dibuka = (kode: string) => menyaring.value || terbuka.value.includes(kode);
function alih(kode: string) {
  terbuka.value = terbuka.value.includes(kode) ? terbuka.value.filter((k) => k !== kode) : [...terbuka.value, kode];
}
function bukaSemua() { terbuka.value = props.daftar.map((e) => e.kode); }

const persen = (a: number, b: number) => `${b ? Math.round((a / b) * 100) : 0}%`;
</script>

<template>
  <div class="rounded-xl border border-slate-200 overflow-hidden bg-white">
    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 px-3 sm:px-4 py-2.5 border-b border-slate-200 bg-slate-50/70">
      <div class="relative flex-1 min-w-[180px] sm:max-w-[280px]">
        <Ikon nama="cari" class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" />
        <input v-model="cari" type="search" class="input-kecil pl-8" placeholder="Cari kode atau kriteria" aria-label="Cari kriteria">
      </div>
      <label class="inline-flex items-center gap-2 text-[12.5px] font-medium text-slate-600 cursor-pointer select-none">
        <input v-model="hanyaKosong" type="checkbox" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Hanya yang kosong
      </label>
      <div class="flex items-center gap-1 sm:ml-auto">
        <button type="button" class="btn-hantu btn-kecil" :disabled="menyaring" @click="bukaSemua"><Ikon nama="chevron-down" class="w-3.5 h-3.5" /> Buka semua</button>
        <button type="button" class="btn-hantu btn-kecil" :disabled="menyaring" @click="terbuka = []"><Ikon nama="chevron-up" class="w-3.5 h-3.5" /> Tutup semua</button>
      </div>
    </div>

    <div class="divide-y divide-slate-200">
      <div v-for="e in tampil" :key="e.kode">
        <button type="button" class="w-full flex items-center gap-3 px-3 sm:px-4 py-2.5 text-left transition-colors hover:bg-slate-50"
                :aria-expanded="dibuka(e.kode)" @click="alih(e.kode)">
          <span class="grid place-items-center w-8 h-8 shrink-0 rounded-lg text-[11.5px] font-bold text-white"
                :class="e.terisi === e.sub.length ? 'bg-hijau-600' : 'bg-navy-900'">{{ e.kode }}</span>
          <span class="min-w-0 flex-1">
            <span class="block text-[13px] font-semibold text-navy-900 truncate">{{ e.nama }}</span>
            <span class="mt-1 flex items-center gap-2">
              <span class="bilah w-20 sm:w-28"><i :class="e.terisi === e.sub.length ? 'bg-hijau-500' : 'bg-teal-500'" :style="{ width: persen(e.terisi, e.sub.length) }" /></span>
              <span class="text-[11.5px] text-slate-500 tabular-nums">{{ e.terisi }}/{{ e.sub.length }} terisi</span>
            </span>
          </span>
          <span v-if="e.diperbarui" class="lencana bg-brand-50 text-brand-700 hidden sm:inline-flex tabular-nums">{{ e.diperbarui }} diperbarui</span>
          <Ikon nama="chevron-down" class="w-4 h-4 shrink-0 text-slate-400 transition-transform" :class="dibuka(e.kode) ? 'rotate-180' : ''" />
        </button>

        <div v-if="dibuka(e.kode)" class="border-t border-slate-100">
          <div class="hidden lg:grid grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,1fr)] gap-3 px-4 py-1.5 bg-slate-50/70 text-[10.5px] font-semibold uppercase tracking-[0.08em] text-slate-400">
            <span>Kriteria</span><span>Metode audit</span><span>Sampel</span>
          </div>
          <div v-for="sb in e.subTampil" :id="`ms-${sb.kode}`" :key="sb.kode"
               class="grid lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,1fr)] gap-x-3 gap-y-2 px-3 sm:px-4 py-2.5 border-t border-slate-100 first:border-t-0 transition-colors"
               :class="penanda[sb.kode] ? 'bg-brand-50/50' : ''">
            <div class="flex items-start gap-2.5 min-w-0 lg:pt-1">
              <span class="lencana justify-center min-w-[46px] shrink-0 bg-slate-100 text-navy-900 tabular-nums">{{ sb.kode }}</span>
              <div class="min-w-0">
                <p class="text-[12.5px] leading-snug text-slate-700">{{ sb.nama }}</p>
                <p v-if="penanda[sb.kode]" class="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold text-brand-700">
                  <Ikon nama="salin" class="w-3 h-3" /> dari {{ penanda[sb.kode] }}
                </p>
              </div>
            </div>
            <div class="grid sm:grid-cols-2 gap-2 lg:contents">
              <label class="block min-w-0">
                <span class="lg:sr-only block text-[11px] font-semibold text-slate-500 mb-1">Metode audit</span>
                <input v-model="nilai[sb.kode].metode" :list="idSaran" maxlength="300" class="input-kecil" placeholder="Mis. tinjauan dokumen, wawancara, observasi"
                       :aria-label="`Metode audit ${sb.kode}`">
              </label>
              <label class="block min-w-0">
                <span class="lg:sr-only block text-[11px] font-semibold text-slate-500 mb-1">Sampel</span>
                <input v-model="nilai[sb.kode].sampel" maxlength="300" class="input-kecil" placeholder="Per metode, pisahkan dengan ; — mis. dokumen IBPR; KTT, HSE Manager; area tambang"
                       :aria-label="`Sampel ${sb.kode}`">
              </label>
            </div>
          </div>
        </div>
      </div>

      <p v-if="!tampil.length" class="px-4 py-8 text-center text-[12.5px] text-slate-500">
        {{ potretKosong && !potretKosong.size ? 'Semua kriteria sudah terisi.' : 'Tidak ada kriteria yang cocok dengan pencarian.' }}
      </p>
    </div>

    <datalist :id="idSaran"><option v-for="m in saran" :key="m" :value="m" /></datalist>
  </div>
</template>

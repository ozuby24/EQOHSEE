<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Ikon from '../Ikon.vue';
import { angka } from '../../../smkp/lib/format';
import { bilangan, hitungRate, tulisAngka, type RataNasional } from '../../../smkp/lib/kinerja';

/*
 * Kalkulator FR · SR · ASR · MFR (port DBEST #23). Cukup isi jam kerja
 * orang dan jumlah kejadian; hasilnya dimasukkan ke kotak Data Kinerja.
 * Jumlah kecelakaan terisi dari cidera ringan + berat + mati.
 */
const props = defineProps<{ kinerja: Record<string, string>; nasional: RataNasional; boleh: boolean }>();
const emit = defineEmits<{ pakai: [nilai: Record<'fr' | 'sr' | 'asr' | 'mfr', string>] }>();

const jam = ref('');
const kecelakaan = ref('');
const hariHilang = ref('');
const hariAbsen = ref('');
const kasusSakit = ref('');

// Jumlah kecelakaan mengikuti isian cidera sampai auditor mengetik sendiri.
const kecelakaanDiketik = ref(false);
const jumlahCidera = computed(() => {
  const v = ['cidera_ringan', 'cidera_berat', 'mati'].map((k) => bilangan(props.kinerja[k]));
  return v.every((x) => x === null) ? '' : String(v.reduce<number>((a, x) => a + (x ?? 0), 0));
});
watch(jumlahCidera, (v) => { if (!kecelakaanDiketik.value) kecelakaan.value = v; }, { immediate: true });

const hasil = computed(() => hitungRate({ jam: jam.value, kecelakaan: kecelakaan.value, hariHilang: hariHilang.value, hariAbsen: hariAbsen.value, kasusSakit: kasusSakit.value }));
const ada = computed(() => Object.values(hasil.value).some((v) => v !== null));
const KUNCI = [['fr', 'FR'], ['sr', 'SR'], ['asr', 'ASR'], ['mfr', 'MFR']] as const;

function pakai() {
  const h = hasil.value;
  const out = {} as Record<'fr' | 'sr' | 'asr' | 'mfr', string>;
  for (const [k] of KUNCI) out[k] = h[k] === null ? props.kinerja[k] ?? '' : tulisAngka(h[k]);
  emit('pakai', out);
}
</script>

<template>
  <div class="mt-3 rounded-xl border border-slate-200 bg-white p-3.5">
    <p class="flex items-center gap-1.5 text-[12.5px] font-semibold text-navy-900"><Ikon nama="kalkulator" class="w-4 h-4 text-teal-600" /> Hitung FR · SR · ASR · MFR</p>
    <div class="mt-2.5 grid gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
      <label class="block"><span class="text-[11.5px] font-semibold text-slate-500">Jam kerja orang (periode audit)</span>
        <input v-model="jam" inputmode="decimal" class="input-kecil tabular-nums" placeholder="mis. 2.450.000"></label>
      <label class="block"><span class="text-[11.5px] font-semibold text-slate-500">Jumlah kecelakaan</span>
        <input v-model="kecelakaan" inputmode="decimal" class="input-kecil tabular-nums" placeholder="ringan + berat + mati" @input="kecelakaanDiketik = true"></label>
      <label class="block"><span class="text-[11.5px] font-semibold text-slate-500">Hari hilang karena kecelakaan</span>
        <input v-model="hariHilang" inputmode="decimal" class="input-kecil tabular-nums" placeholder="mis. 120"></label>
      <label class="block"><span class="text-[11.5px] font-semibold text-slate-500">Hari absen karena sakit</span>
        <input v-model="hariAbsen" inputmode="decimal" class="input-kecil tabular-nums" placeholder="mis. 340"></label>
      <label class="block"><span class="text-[11.5px] font-semibold text-slate-500">Jumlah kasus sakit</span>
        <input v-model="kasusSakit" inputmode="decimal" class="input-kecil tabular-nums" placeholder="mis. 45"></label>
    </div>
    <div class="mt-3 flex flex-wrap items-center gap-2">
      <span v-for="[k, l] in KUNCI" :key="k" class="inline-flex items-baseline gap-1 rounded-lg px-2.5 py-1 text-[12px]"
            :class="hasil[k] === null ? 'bg-slate-100 text-slate-500' : hasil[k]! > nasional[k] ? 'bg-rose-50 text-rose-700' : 'bg-hijau-50 text-hijau-700'"
            :title="`Rata-rata nasional ${l}: ${angka(nasional[k], 2)}`">
        {{ l }} <b class="tabular-nums">{{ hasil[k] === null ? '—' : angka(hasil[k], 2) }}</b>
      </span>
      <button v-if="boleh" type="button" class="btn-utama btn-kecil ml-auto" :disabled="!ada" @click="pakai">
        <Ikon nama="unduh" class="w-4 h-4" /> Masukkan ke isian
      </button>
    </div>
    <p class="mt-2 text-[11.5px] leading-relaxed text-slate-500">
      Rumus per 1.000.000 jam kerja orang: FR = kecelakaan × 1.000.000 ÷ jam kerja; SR = hari hilang × 1.000.000 ÷ jam kerja (kecelakaan mati lazimnya dihitung 6.000 hari);
      ASR = hari absen sakit × 1.000.000 ÷ jam kerja; MFR = kasus sakit × 1.000.000 ÷ jam kerja. Ikuti definisi statistik K3 perusahaan bila berbeda.
    </p>
  </div>
</template>

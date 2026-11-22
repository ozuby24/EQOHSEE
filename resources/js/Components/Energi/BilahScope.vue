<script setup lang="ts">
/*
  Komposisi emisi per scope dalam satu bilah bertumpuk.

  Warnanya sama dengan grafik garis per scope di halaman yang sama
  (Grafik/warna KATEGORI: Scope 1 jingga, Scope 2 biru, Scope 3 ungu)
  supaya satu scope tidak berganti warna dari satu panel ke panel lain.
  Persentasenya juga dicetak sebagai angka, tidak hanya sebagai panjang.
*/
import { computed } from 'vue';
import Ikon from './Ikon.vue';
import { KATEGORI } from '../../Grafik/warna';
import { n } from '../../energi';

const props = withDefaults(defineProps<{ s1: number; s2: number; s3: number; s3Terpisah?: number }>(), { s3Terpisah: 0 });

const bagian = computed(() => {
  const total = (props.s1 || 0) + (props.s2 || 0) + (props.s3 || 0);
  const baris = [
    { kode: 'Scope 1', ket: 'Pembakaran langsung: solar, gas, peledak, refrigeran', nilai: props.s1 || 0, warna: KATEGORI[1], ikon: 'flame' },
    { kode: 'Scope 2', ket: 'Listrik yang dibeli dari PLN / IUPTLS', nilai: props.s2 || 0, warna: KATEGORI[0], ikon: 'plug-zap' },
    { kode: 'Scope 3', ket: 'Rantai nilai: angkutan pihak ketiga, perjalanan dinas', nilai: props.s3 || 0, warna: KATEGORI[6], ikon: 'ship' },
  ];
  return baris.map(b => ({ ...b, persen: total > 0 ? (b.nilai / total) * 100 : 0 }));
});
const total = computed(() => bagian.value.reduce((a, b) => a + b.nilai, 0));
</script>

<template>
  <div>
    <div class="bs-total"><span class="num">{{ n(total, 0) }}</span><small>tCO₂e total Scope 1–3</small></div>
    <div class="bs-bilah" role="img" :aria-label="bagian.map(b => `${b.kode} ${n(b.persen, 1)}%`).join(', ')">
      <span v-for="b in bagian" v-show="b.persen > 0" :key="b.kode" :style="{ width: b.persen + '%', background: b.warna }" />
    </div>
    <ul class="bs-legenda">
      <li v-for="b in bagian" :key="b.kode">
        <span class="bs-ikon" :style="{ background: b.warna }"><Ikon :nama="b.ikon" :ukuran="16" :tebal="2.2" /></span>
        <div class="min-w-0">
          <p class="bs-kode">{{ b.kode }} <b class="num">{{ n(b.persen, 1) }}%</b></p>
          <p class="bs-nilai num">{{ n(b.nilai, 1) }} <small>tCO₂e</small></p>
          <p class="bs-ket">{{ b.ket }}</p>
        </div>
      </li>
    </ul>
    <p v-if="s3Terpisah > 0" class="bs-catatan">
      <Ikon nama="info" :ukuran="13" /> Belum termasuk {{ n(s3Terpisah, 0) }} tCO₂e Scope 3 kategori 11 (batubara terjual), yang dilaporkan terpisah.
    </p>
  </div>
</template>

<style scoped>
.bs-total { display: flex; align-items: baseline; gap: 8px; }
.bs-total span { font-size: 30px; font-weight: 800; letter-spacing: -.02em; color: #1C1917; }
.bs-total small { font-size: 12px; color: #78716C; font-weight: 600; }
.bs-bilah { display: flex; height: 16px; border-radius: 999px; overflow: hidden; margin-top: 10px; background: #F0EFED; gap: 2px; }
.bs-bilah span { display: block; height: 100%; }
.bs-legenda { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-top: 16px; }
.bs-legenda li { display: flex; gap: 10px; align-items: flex-start; }
.bs-ikon { width: 32px; height: 32px; flex: none; border-radius: 10px; display: grid; place-items: center; color: #fff; }
.bs-kode { font-size: 12px; font-weight: 700; color: #44403C; }
.bs-kode b { font-weight: 800; color: #1C1917; margin-left: 4px; }
.bs-nilai { font-size: 17px; font-weight: 800; color: #1C1917; margin-top: 1px; }
.bs-nilai small { font-size: 11px; color: #A8A29E; font-weight: 700; }
.bs-ket { font-size: 11px; color: #78716C; margin-top: 2px; line-height: 1.35; }
.bs-catatan { display: flex; gap: 6px; align-items: center; font-size: 11px; color: #78716C; margin-top: 12px; }

:root[data-tema="gelap"] .bs-total span, :root[data-tema="gelap"] .bs-nilai, :root[data-tema="gelap"] .bs-kode b { color: #E8EDEF; }
:root[data-tema="gelap"] .bs-kode { color: #C7D0D5; }
:root[data-tema="gelap"] .bs-total small, :root[data-tema="gelap"] .bs-ket, :root[data-tema="gelap"] .bs-catatan { color: #93A1A8; }
:root[data-tema="gelap"] .bs-bilah { background: #26343A; }
</style>

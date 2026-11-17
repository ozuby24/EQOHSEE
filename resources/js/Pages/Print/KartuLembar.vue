<script setup lang="ts">
/**
 * Lembar cetak banyak kartu — `kartu/lembar` Safe Track: kartu dipetakan ke
 * kertas A4/Letter/F4 menurut tata cetak (SkemaCetak), dengan garis potong.
 *
 *   · lipat — depan dan belakang berdampingan dalam satu petak;
 *   · bolak-balik — halaman depan lalu halaman belakang yang KOLOMNYA
 *     DICERMINKAN per baris, supaya tiap belakang jatuh di balik kartunya
 *     saat dicetak duplex (balik pada sisi panjang);
 *   · depan — sisi depan saja.
 *
 * Kartu tanpa foto dilewati dan disebutkan di atas lembar (tidak tercetak).
 */
import { computed, onBeforeUnmount, onMounted } from 'vue';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';
import KartuCr80 from '../../Components/Miners/KartuCr80.vue';

defineOptions({ layout: BlankLayout });

const props = defineProps<{
  kartu: Record<string, any>[];
  dilewati: string[];
  skema: Record<string, any>;
  petak: Record<string, number>;
  ringkas: string;
  kembali?: string;
}>();

const mm = (v: number) => `${v}mm`;

/* Kartu dibagi per lembar. */
const lembar = computed(() => {
  const n = Math.max(1, props.petak.per_lembar);
  const hasil: Record<string, any>[][] = [];
  for (let i = 0; i < props.kartu.length; i += n) hasil.push(props.kartu.slice(i, i + n));
  return hasil;
});

/* Letak petak ke-i: baris-kolom; `cermin` membalik kolom untuk sisi belakang duplex. */
function letak(i: number, cermin = false) {
  const kol = props.petak.kolom;
  const b = Math.floor(i / kol);
  let c = i % kol;
  if (cermin) c = kol - 1 - c;
  return {
    left: mm(props.petak.kiri + c * (props.petak.petak_l + props.skema.sela)),
    top: mm(props.petak.atas + b * (props.petak.petak_t + props.skema.sela)),
    width: mm(props.petak.petak_l), height: mm(props.petak.petak_t),
  };
}

const halaman = computed(() => {
  const h: { sisi: 'depan' | 'belakang' | 'lipat'; kartu: Record<string, any>[] }[] = [];
  for (const l of lembar.value) {
    if (props.skema.skema === 'lipat') h.push({ sisi: 'lipat', kartu: l });
    else {
      h.push({ sisi: 'depan', kartu: l });
      if (props.skema.skema === 'bolak-balik') h.push({ sisi: 'belakang', kartu: l });
    }
  }
  return h;
});

/* @page tidak dapat membaca variabel CSS, jadi ukurannya disisipkan
   sebagai <style> sendiri selama halaman ini terbuka. */
let gayaHalaman: HTMLStyleElement | null = null;
onMounted(() => {
  gayaHalaman = document.createElement('style');
  gayaHalaman.textContent = `@media print { @page { size: ${props.petak.kertas_l}mm ${props.petak.kertas_t}mm; margin: 0; } }`;
  document.head.appendChild(gayaHalaman);
});
onBeforeUnmount(() => gayaHalaman?.remove());
</script>

<template>
  <PrintShell title="Lembar Cetak Kartu" :kembali="props.kembali" mendatar>
    <div class="lembar-info">
      <div v-html="props.ringkas" />
      <div v-if="props.dilewati.length" class="lembar-lewat">Dilewati (foto belum diunggah): {{ props.dilewati.join(', ') }}.</div>
      <div v-if="!props.kartu.length" class="lembar-lewat">Tidak ada kartu yang dapat dicetak.</div>
    </div>
    <div v-for="(h, hi) in halaman" :key="hi" class="lembar" :style="{ width: mm(props.petak.kertas_l), height: mm(props.petak.kertas_t) }">
      <div v-for="(k, i) in h.kartu" :key="`${hi}-${k.id}`" class="petak" :class="{ potong: props.skema.garis_potong }"
           :style="letak(i, h.sisi === 'belakang')">
        <template v-if="h.sisi === 'lipat'">
          <KartuCr80 :k="k" sisi="depan" />
          <div :style="{ width: mm(props.skema.sela_lipat) }" class="sela-lipat" />
          <KartuCr80 :k="k" sisi="belakang" />
        </template>
        <KartuCr80 v-else :k="k" :sisi="h.sisi" />
      </div>
    </div>
  </PrintShell>
</template>

<style scoped>
.lembar-info { max-width: 1120px; margin: 12px auto; font-size: 13px; }
.lembar-lewat { color: #9b1c1c; margin-top: 4px; }
.lembar { position: relative; margin: 0 auto 12mm; background: #fff; box-shadow: 0 1px 8px rgba(0,0,0,.15); overflow: hidden; }
.petak { position: absolute; display: flex; }
.petak.potong { outline: .2mm dashed #9aa3ad; outline-offset: 0; }
.sela-lipat { flex: none; border-left: .2mm dotted #c3c9d0; }
@media print {
  .lembar-info { display: none; }
  .lembar { margin: 0; box-shadow: none; break-after: page; page-break-after: always; }
}
</style>

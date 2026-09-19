<script setup lang="ts">
/**
 * Sel foto untuk tabel: gambar kecil yang dapat dibuka besar.
 *
 * ── Kenapa gambar kecilnya harus dapat diklik ──
 *
 * Foto temuan bahaya diambil dari ponsel di lapangan: tanggul yang
 * tergerus, ceceran oli, kabel terkelupas. Pada kotak 44 piksel tidak
 * satu pun dari itu terbaca — yang terbaca hanya "ada fotonya". Itu
 * sudah berguna untuk memindai daftar, tetapi tidak cukup untuk
 * memutuskan apa pun, dan orang yang harus memutuskan akan membuka
 * laporannya satu per satu seperti sebelumnya.
 *
 * ── Kosong itu KABAR, bukan ketiadaan ──
 *
 * Kolom foto tindak lanjut yang kosong berarti "belum ada bukti
 * perbaikan" — justru baris yang paling perlu dilihat pengawas.
 * Digambar sebagai sel kosong begitu saja, ia terbaca sebagai kolom
 * yang belum selesai dibuat. Karena itu kotak putus-putus dengan
 * tanda pisah: bentuk yang menyatakan "memang belum ada".
 */
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = withDefaults(defineProps<{
  foto: string[];

  /** Disebut pembaca layar dan dipakai sebagai judul saat dibuka besar. */
  label: string;

  /** Keterangan saat kosong — sengaja berbeda untuk temuan dan tindak lanjut. */
  kosong?: string;
}>(), { kosong: 'Belum ada foto' });

const buka = ref(false);
const ke   = ref(0);

const ada  = computed(() => props.foto.length > 0);
const kini = computed(() => props.foto[ke.value] ?? null);

function bukaDi(i: number): void {
  ke.value = i;
  buka.value = true;
}

function maju(n: number): void {
  if (!props.foto.length) return;

  /* Berputar, tidak mentok. Dua foto dengan tombol yang mati di ujung
     memaksa orang menebak arah mana yang masih hidup. */
  ke.value = (ke.value + n + props.foto.length) % props.foto.length;
}

function tekan(e: KeyboardEvent): void {
  if (e.key === 'Escape')     { e.preventDefault(); buka.value = false; }
  if (e.key === 'ArrowRight') maju(1);
  if (e.key === 'ArrowLeft')  maju(-1);
}

/* Didengarkan di tingkat dokumen: begitu orangnya mengklik latar gelap
   di luar gambar, fokus tidak lagi berada di dalam kotaknya, dan
   pendengar yang menempel pada elemen berhenti bekerja tanpa alasan
   yang terlihat. */
watch(buka, (b) => {
  if (b) document.addEventListener('keydown', tekan);
  else   document.removeEventListener('keydown', tekan);
});

onBeforeUnmount(() => document.removeEventListener('keydown', tekan));
</script>

<template>
  <!-- ── gambar kecil di dalam sel ── -->
  <button v-if="ada" type="button" class="eq-foto-kecil"
          :aria-label="`${label} — buka besar`" @click.stop.prevent="bukaDi(0)">
    <img :src="foto[0]" alt="" loading="lazy" decoding="async">
    <span v-if="foto.length > 1" class="eq-foto-jumlah">+{{ foto.length - 1 }}</span>
  </button>

  <span v-else class="eq-foto-kosong" :title="kosong" :aria-label="kosong">–</span>

  <!-- ── dibuka besar ──
       Digambar hanya saat terbuka: lima belas baris × dua kolom foto
       berarti tiga puluh lapisan layar penuh yang selamanya tersembunyi
       di dalam halaman bila digambar terus-menerus. -->
  <Teleport to="body">
    <div v-if="buka && kini" class="eq-foto-tirai" role="dialog" aria-modal="true"
         :aria-label="label" @click.self="buka = false">

      <div class="eq-foto-kepala">
        <p class="eq-foto-judul">
          {{ label }}
          <span v-if="foto.length > 1" class="eq-foto-urut">{{ ke + 1 }} / {{ foto.length }}</span>
        </p>

        <a :href="kini" target="_blank" rel="noopener" class="eq-foto-tombol" title="Buka di tab baru">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14 21 3"/>
          </svg>
        </a>

        <button type="button" class="eq-foto-tombol" aria-label="Tutup" @click="buka = false">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
               stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
        </button>
      </div>

      <img :src="kini" :alt="label" class="eq-foto-besar">

      <template v-if="foto.length > 1">
        <button type="button" class="eq-foto-arah is-kiri" aria-label="Foto sebelumnya"
                @click.stop="maju(-1)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m15 18-6-6 6-6"/>
          </svg>
        </button>

        <button type="button" class="eq-foto-arah is-kanan" aria-label="Foto berikutnya"
                @click.stop="maju(1)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m9 18 6-6-6-6"/>
          </svg>
        </button>
      </template>
    </div>
  </Teleport>
</template>

<style scoped>
.eq-foto-kecil {
  position: relative;
  display: block;
  width: 2.75rem;
  height: 2.75rem;
  padding: 0;
  border: 1px solid #E7E5E4;
  border-radius: .55rem;
  background: #FAFAF9;
  overflow: hidden;
  cursor: zoom-in;
  transition: border-color .15s, box-shadow .15s, transform .15s;
}

.eq-foto-kecil img { width: 100%; height: 100%; object-fit: cover; display: block; }

.eq-foto-kecil:hover {
  border-color: #FDBA74;
  box-shadow: 0 3px 10px -2px rgb(245 124 0 / .4);
  transform: translateY(-1px);
}

.eq-foto-kecil:focus-visible { outline: 2px solid #F57C00; outline-offset: 2px; }

.eq-foto-jumlah {
  position: absolute;
  right: 0;
  bottom: 0;
  padding: 0 .2rem;
  border-radius: .3rem 0 0 0;
  background: rgb(28 25 23 / .78);
  color: #FFFFFF;
  font-size: 9px;
  font-weight: 700;
  line-height: 1.35;
}

.eq-foto-kosong {
  display: grid;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  border: 1px dashed #E7E5E4;
  border-radius: .55rem;
  color: #D6D3D1;
  font-size: 13px;
  user-select: none;
}

/* ── dibuka besar ── */
.eq-foto-tirai {
  position: fixed;
  inset: 0;
  z-index: 80;
  display: grid;
  place-items: center;
  padding: 3.5rem 1rem 1.5rem;
  background: rgb(9 13 20 / .88);
  backdrop-filter: blur(3px);
}

.eq-foto-besar {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  border-radius: .5rem;
  box-shadow: 0 20px 60px rgb(0 0 0 / .6);
}

.eq-foto-kepala {
  position: absolute;
  inset: 0 0 auto;
  display: flex;
  align-items: center;
  gap: .5rem;
  padding: .7rem .9rem;
}

.eq-foto-judul {
  flex: 1;
  min-width: 0;
  margin: 0;
  font-size: 12px;
  font-weight: 600;
  color: rgb(231 229 228 / .85);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.eq-foto-urut {
  margin-left: .45rem;
  font-variant-numeric: tabular-nums;
  color: rgb(231 229 228 / .5);
}

.eq-foto-tombol {
  flex: none;
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border: 0;
  border-radius: .5rem;
  background: rgb(255 255 255 / .1);
  color: #E7E5E4;
  cursor: pointer;
  transition: background-color .15s;
}

.eq-foto-tombol svg { width: .95rem; height: .95rem; }
.eq-foto-tombol:hover { background: rgb(255 255 255 / .22); }

.eq-foto-arah {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  display: grid;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border: 0;
  border-radius: 99px;
  background: rgb(255 255 255 / .12);
  color: #E7E5E4;
  cursor: pointer;
  transition: background-color .15s;
}

.eq-foto-arah svg { width: 1.2rem; height: 1.2rem; }
.eq-foto-arah:hover { background: rgb(255 255 255 / .26); }
.eq-foto-arah.is-kiri  { left: .75rem; }
.eq-foto-arah.is-kanan { right: .75rem; }

:global([data-tema='gelap']) .eq-foto-kecil { background: #101A26; border-color: #1E2E42; }
:global([data-tema='gelap']) .eq-foto-kosong { border-color: #1E2E42; color: #3A4E66; }

@media (prefers-reduced-motion: reduce) {
  .eq-foto-kecil:hover { transform: none; }
}
</style>

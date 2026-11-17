<script setup lang="ts">
/**
 * Kartu skor audit: skor akhir, predikat, dan peringkat warna.
 *
 * Predikat dan peringkat sengaja DIPISAH, karena syaratnya berbeda:
 * peringkat warna hanya melihat angkanya, sedangkan predikat
 * penghargaan menuntut nilai PENUH pada bagian Administrasi dan
 * Implementasi. Perusahaan berskor 92 yang kehilangan satu poin di
 * bagian A mendapat peringkat EMAS tetapi TIDAK mendapat ADITAMA —
 * dan tanpa alasannya disebut, yang membacanya menyimpulkan
 * perhitungannya rusak.
 */
import { computed } from 'vue';
import type { SkorAudit } from '../../types';
import SkalaPeringkat from './SkalaPeringkat.vue';
import { angka } from './bantu';

const props = defineProps<{
  skor: SkorAudit;
  peringkat: Array<{ nama: string; kriteria: string; min: number; warna: string }>;
}>();

const BINTANG: Record<string, number> = { ADITAMA: 3, UTAMA: 2, PRATAMA: 1 };
const bintang = computed(() => BINTANG[props.skor.predikat.nama ?? ''] ?? 0);
const terverifikasi = computed(() => props.skor.kriteria - props.skor.belum);
const warnaPeringkat = computed(() => (props.skor.peringkat.nama === 'HITAM' ? 'var(--akl-hitam)' : props.skor.peringkat.warna));

function titikBintang(r: number): string {
  const t: string[] = [];
  for (let i = 0; i < 10; i++) {
    const a = -Math.PI / 2 + (i * Math.PI) / 5;
    const rr = i % 2 === 0 ? r : r * 0.42;
    t.push(`${(rr * Math.cos(a)).toFixed(2)},${(rr * Math.sin(a)).toFixed(2)}`);
  }
  return t.join(' ');
}
</script>

<template>
  <section class="aks">
    <div class="aks-angka">
      <span class="aks-label">Skor akhir</span>
      <b class="num">{{ angka(skor.akhir) }}</b>
      <span class="aks-dari">dari 100</span>
      <span class="akl-keping mt-2" :style="{ gap: '.4rem' }">
        <span class="akl-titik" :style="{ background: warnaPeringkat }"></span>
        {{ skor.peringkat.nama }} · {{ skor.peringkat.kriteria }}
      </span>
    </div>

    <div class="aks-tengah">
      <SkalaPeringkat :skor="skor.akhir" :peringkat="peringkat" />

      <dl class="aks-hitung">
        <div><dt>Persentase pemenuhan</dt><dd class="num">{{ angka(skor.pemenuhan) }}</dd></div>
        <div>
          <dt>Nilai pengurang</dt>
          <dd class="num" :class="{ kurang: skor.pengurang > 0 }">{{ skor.pengurang > 0 ? '− ' + skor.pengurang : '—' }}</dd>
        </div>
        <div class="akhir"><dt>Skor akhir</dt><dd class="num">{{ angka(skor.akhir) }}</dd></div>
      </dl>

      <ul v-if="skor.rincianKurang.length" class="aks-kurang">
        <li v-for="k in skor.rincianKurang" :key="k.kunci"><b class="num">−{{ k.poin }}</b> {{ k.label }}</li>
      </ul>

      <div class="aks-verif">
        <div class="aks-verif-pita"><span :style="{ width: `${(terverifikasi / Math.max(1, skor.kriteria)) * 100}%` }"></span></div>
        <p v-if="skor.belum" class="belum">
          <b class="num">{{ terverifikasi }}</b> dari <b class="num">{{ skor.kriteria }}</b> kriteria diverifikasi —
          sisanya dihitung nol, jadi skor ini masih <b>sementara</b>.
        </p>
        <p v-else class="lengkap">Seluruh <b class="num">{{ skor.kriteria }}</b> kriteria sudah diverifikasi.</p>
      </div>
    </div>

    <div class="aks-medali" :class="{ kosong: !skor.predikat.nama }">
      <svg viewBox="-50 -50 100 118" aria-hidden="true">
        <defs>
          <linearGradient id="aks-emas" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#8A6A14" /><stop offset=".3" stop-color="#E8C766" />
            <stop offset=".55" stop-color="#A9831F" /><stop offset=".8" stop-color="#F6E3A1" />
            <stop offset="1" stop-color="#937018" />
          </linearGradient>
        </defs>
        <g class="pita" :style="{ fill: skor.predikat.nama ? warnaPeringkat : undefined }">
          <path d="M-22 22 L-36 64 L-26 57 L-18 67 L-6 28 Z" />
          <path d="M22 22 L36 64 L26 57 L18 67 L6 28 Z" />
        </g>
        <circle r="40" class="cincin" />
        <circle r="33" class="inti" />
        <circle r="29" fill="none" class="garis" stroke-width="1" />
        <g v-if="bintang" :transform="`translate(${-((bintang - 1) * 13) / 2} -6)`">
          <polygon v-for="n in bintang" :key="n" :points="titikBintang(5.5)"
                   :transform="`translate(${(n - 1) * 13} 0)`" class="bintang" />
        </g>
        <text v-else y="4" class="tanya">—</text>
      </svg>
      <span class="aks-label">Predikat penghargaan</span>
      <b class="aks-predikat">{{ skor.predikat.nama ?? 'Tidak terbit' }}</b>
    </div>

    <p v-if="skor.predikat.alasan" class="aks-alasan">{{ skor.predikat.alasan }}</p>
  </section>
</template>

<style scoped>
.aks {
  display: grid; gap: 1.2rem 1.6rem; align-items: center;
  grid-template-columns: minmax(150px, auto) minmax(0, 1fr) minmax(140px, auto);
}

.aks-angka { display: grid; justify-items: start; }
.aks-label { font-size: 10px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: var(--akl-tinta-3); }
.aks-angka > b { font-size: 46px; font-weight: 800; letter-spacing: -.03em; line-height: 1; color: var(--akl-tinta); margin-top: .2rem; }
.aks-dari { font-size: 11px; color: var(--akl-tinta-3); margin-top: .1rem; }

.aks-hitung {
  display: flex; flex-wrap: wrap; gap: .3rem 1.6rem; margin-top: .9rem; padding-top: .7rem;
  border-top: 1px solid var(--akl-garis-halus);
}
.aks-hitung dt { font-size: 10.5px; color: var(--akl-tinta-3); }
.aks-hitung dd { font-size: 16px; font-weight: 800; color: var(--akl-tinta-2); margin: 0; }
.aks-hitung .kurang { color: #DC2626; }
.aks-hitung .akhir dd { color: var(--akl-tinta); font-size: 18px; }

.aks-kurang { margin-top: .4rem; display: grid; gap: .1rem; }
.aks-kurang li { font-size: 11px; color: var(--akl-tinta-2); }
.aks-kurang b { color: #DC2626; margin-right: .3rem; }

.aks-verif { margin-top: .7rem; }
.aks-verif-pita { height: 6px; border-radius: 99px; background: var(--akl-jalur); overflow: hidden; }
.aks-verif-pita > span { display: block; height: 100%; border-radius: 99px; background: linear-gradient(90deg, #DC6E00, #FF9800); }
.aks-verif p { margin-top: .35rem; font-size: 11.5px; line-height: 1.5; }
.aks-verif .belum { color: #B45309; }
.aks-verif .lengkap { color: #15803D; }

.aks-medali { display: grid; justify-items: center; text-align: center; }
.aks-medali svg { width: 96px; height: 113px; }
.aks-medali .cincin { fill: url(#aks-emas); }
.aks-medali .inti { fill: #0F3D2E; }
.aks-medali .garis { stroke: #E8C766; }
.aks-medali .bintang { fill: #F6E3A1; }
.aks-medali .tanya { fill: #A8A29E; font-size: 18px; font-weight: 800; text-anchor: middle; }
.aks-medali.kosong .cincin { fill: #D6D3D1; }
.aks-medali.kosong .inti { fill: #F5F5F4; }
.aks-medali.kosong .garis { stroke: #E7E5E4; }
.aks-medali.kosong .pita { fill: #D6D3D1; }
.aks-predikat { font-size: 20px; font-weight: 900; letter-spacing: .06em; color: var(--akl-tinta); margin-top: .1rem; }
.aks-medali.kosong .aks-predikat { font-size: 14px; font-weight: 700; font-style: italic; letter-spacing: 0; color: var(--akl-tinta-3); }

.aks-alasan {
  grid-column: 1 / -1; font-size: 11.5px; line-height: 1.55; color: #B45309;
  background: #FFF7E6; border: 1px solid #FDE68A; border-radius: .7rem; padding: .55rem .7rem;
}

@media (max-width: 52rem) {
  .aks { grid-template-columns: 1fr auto; }
  .aks-tengah { grid-column: 1 / -1; grid-row: 2; }
  .aks-medali { grid-row: 1; grid-column: 2; }
}

:global(:root[data-tema="gelap"] .aks-verif .belum) { color: #FCD34D; }
:global(:root[data-tema="gelap"] .aks-verif .lengkap) { color: #86EFAC; }
:global(:root[data-tema="gelap"] .aks-medali.kosong .inti) { fill: #1B292E; }
:global(:root[data-tema="gelap"] .aks-medali.kosong .cincin),
:global(:root[data-tema="gelap"] .aks-medali.kosong .pita) { fill: #3A4A50; }
:global(:root[data-tema="gelap"] .aks-alasan) { background: rgba(245, 158, 11, .12); border-color: rgba(245, 158, 11, .3); color: #FDE68A; }
</style>

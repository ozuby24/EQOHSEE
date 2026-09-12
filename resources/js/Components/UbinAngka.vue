<script setup lang="ts">
/**
 * Ubin angka berikon — satu angka, satu label, satu bilah proporsi.
 *
 * BILAHNYA MENYATAKAN BAGIAN DARI KESELURUHAN, bukan hiasan. Diberi
 * lebar acak supaya kartunya terlihat penuh, ia berbohong tentang hal
 * yang paling mudah dipercaya orang: panjang. Karena itu bilahnya
 * hanya muncul bila `dari` diberikan — tanpa pembanding, tidak ada
 * proporsi yang dapat digambar.
 *
 * ANGKA NOL TIDAK DIREDUPKAN. Nol pada "Terhalang berkas" adalah
 * kabar baik dan nol pada "Hadir" adalah kabar buruk; keduanya sama
 * pentingnya untuk dibaca, dan meredupkan salah satunya membuat
 * pembacanya melewatkannya justru ketika ia paling berarti.
 */
import { computed } from 'vue';

const props = withDefaults(defineProps<{
  angka: string | number;
  label: string;

  /** Pembanding untuk bilah proporsi. Kosong berarti tanpa bilah. */
  dari?: number | null;

  /**
   * Nilai mentah untuk bilahnya, bila `angka` sudah diformat.
   *
   * `Number("Rp 48.052.520")` bernilai NaN, dan NaN pada lebar bilah
   * tidak menggagalkan apa pun — ia hanya menggambar bilah kosong yang
   * terbaca sebagai "nol persen". Sebuah nilai yang salah yang tampak
   * seperti sebuah nilai yang benar; karena itu angka terformat wajib
   * membawa nilai mentahnya sendiri.
   */
  nilai?: number | null;

  /** Nada warna ikon dan bilahnya. */
  nada?: 'netral' | 'baik' | 'ingat' | 'serius' | 'gawat' | 'luar';

  /** Keterangan kecil di bawah label. */
  catatan?: string | null;

}>(), { dari: null, nilai: null, nada: 'netral', catatan: null });

/**
 * Lebar bilahnya dalam persen, atau null bila tidak dapat digambar.
 *
 * Sebuah bilah hanya boleh muncul bila kedua sisinya berupa bilangan
 * yang sah. Bila salah satunya bukan — pembanding nol, atau angka yang
 * sudah diformat tanpa `nilai` mentahnya — tidak ada bilah sama sekali,
 * karena bilah kosong terbaca sebagai nol dan bukan sebagai "tidak tahu".
 */
const lebar = computed(() => {
  if (props.dari === null || !(props.dari > 0)) return null;

  const n = props.nilai ?? Number(props.angka);
  if (!Number.isFinite(n)) return null;

  return Math.min(100, Math.max(0, (n / props.dari) * 100));
});
</script>

<template>
  <div class="ubin" :class="'ubin-' + nada">
    <div class="ubin-atas">
      <span class="ubin-ikon" aria-hidden="true"><slot name="ikon" /></span>

      <span class="min-w-0">
        <span class="ubin-angka num">{{ angka }}</span>
        <span class="ubin-label">{{ label }}</span>
      </span>
    </div>

    <p v-if="catatan" class="ubin-catatan">{{ catatan }}</p>

    <div v-if="lebar !== null" class="ubin-bilah" aria-hidden="true">
      <span :style="{ width: lebar + '%' }" />
    </div>
  </div>
</template>

<style>
.ubin {
  border-radius: .85rem;
  padding: .8rem .85rem .7rem;
  background: var(--ubin-latar, #FAFAF9);
  border: 1px solid var(--ubin-garis, #F0EFED);
  display: flex;
  flex-direction: column;
  gap: .45rem;
}

.ubin-atas { display: flex; align-items: center; gap: .6rem; }

.ubin-ikon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px; height: 34px;
  border-radius: .65rem;
  flex: none;
  background: var(--ubin-ikon-latar, #F0EFED);
  color: var(--ubin-ikon-warna, #57534E);
}

.ubin-ikon svg { width: 18px; height: 18px; }

.ubin-angka {
  display: block;
  font-size: 19px;
  font-weight: 800;
  line-height: 1.05;
  color: var(--ubin-angka, #1C1917);
}

.ubin-label {
  display: block;
  font-size: 10.5px;
  margin-top: .15rem;
  color: var(--ubin-label, #78716C);
}

.ubin-catatan { margin: 0; font-size: 10.5px; color: var(--ubin-label, #78716C); }

.ubin-bilah {
  height: 3px;
  border-radius: 999px;
  background: var(--ubin-bilah-latar, #EAE8E5);
  overflow: hidden;
}

.ubin-bilah > span {
  display: block;
  height: 100%;
  border-radius: 999px;
  background: var(--ubin-ikon-warna, #57534E);
}

/* ── nada ── */
.ubin-netral { --ubin-ikon-latar: #F0F0EE; --ubin-ikon-warna: #57534E; }
.ubin-baik   { --ubin-latar: #F4FCF8; --ubin-garis: #DDF3E8; --ubin-ikon-latar: #D1FAE5; --ubin-ikon-warna: #047857; }
.ubin-ingat  { --ubin-latar: #FFFCF3; --ubin-garis: #F7EACB; --ubin-ikon-latar: #FEF3C7; --ubin-ikon-warna: #B45309; }
.ubin-serius { --ubin-latar: #F5F9FE; --ubin-garis: #DCE9F7; --ubin-ikon-latar: #DBEAFE; --ubin-ikon-warna: #1D4ED8; }
.ubin-gawat  { --ubin-latar: #FFF7F6; --ubin-garis: #F7DDDA; --ubin-ikon-latar: #FEE2E2; --ubin-ikon-warna: #B91C1C; }
.ubin-luar   { --ubin-latar: #FAF8FF; --ubin-garis: #E8E1F7; --ubin-ikon-latar: #EDE9FE; --ubin-ikon-warna: #6D28D9; }

/* ── mode gelap ──
   Latarnya dinaikkan sedikit dari latar kartu, bukan diturunkan:
   ubin yang lebih gelap daripada kartunya terbaca seperti lubang. */
:root[data-tema="gelap"] .ubin {
  --ubin-latar: #182125;
  --ubin-garis: #222E33;
  --ubin-angka: #E8EDEF;
  --ubin-label: #93A1A8;
  --ubin-bilah-latar: #26343A;
}

:root[data-tema="gelap"] .ubin-netral { --ubin-ikon-latar: #232F35; --ubin-ikon-warna: #C7D0D5; }
:root[data-tema="gelap"] .ubin-baik   { --ubin-latar: #142521; --ubin-garis: #1D3630; --ubin-ikon-latar: #143A2C; --ubin-ikon-warna: #8FE3BE; }
:root[data-tema="gelap"] .ubin-ingat  { --ubin-latar: #241E12; --ubin-garis: #38301B; --ubin-ikon-latar: #4A3810; --ubin-ikon-warna: #F6D488; }
:root[data-tema="gelap"] .ubin-serius { --ubin-latar: #131F2B; --ubin-garis: #1D2E3F; --ubin-ikon-latar: #1E3F5E; --ubin-ikon-warna: #A8CDF0; }
:root[data-tema="gelap"] .ubin-gawat  { --ubin-latar: #251515; --ubin-garis: #3A1F1F; --ubin-ikon-latar: #4E1D1D; --ubin-ikon-warna: #F5A9A9; }
:root[data-tema="gelap"] .ubin-luar   { --ubin-latar: #1C1730; --ubin-garis: #2A2246; --ubin-ikon-latar: #34255E; --ubin-ikon-warna: #C8B6F5; }
</style>

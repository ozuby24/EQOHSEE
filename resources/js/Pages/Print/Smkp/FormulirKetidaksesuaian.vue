<script setup lang="ts">
/**
 * Formulir Ketidaksesuaian dan Tindak Lanjut Ketidaksesuaian Audit — per
 * temuan: lembar uraian (auditor) lalu lembar tindak lanjut (auditi) &
 * ulasan tim auditor. Seksinya diukur dan dialirkan ke lembar (lib/susunLembar
 * useSeksiLembar): akar masalah atau tindakan yang panjang berlanjut ke lembar
 * berikutnya, tidak meluber melewati batas bawah.
 */
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import CetakLayout from '../../../Components/Smkp/CetakLayout.vue';
import Lembar from '../../../Components/Smkp/Lembar.vue';
import SeksiKetidaksesuaian, { type TemuanKts } from '../../../Components/Smkp/SeksiKetidaksesuaian.vue';
import { useSeksiLembar, type GrupSeksi } from '../../../smkp/lib/susunLembar';
import type { KopDok, RingkasAudit, TtdForm } from '../../../smkp/types';
import BlankLayout from '../../../Layouts/BlankLayout.vue';

/* Lembar cetak di luar kerangka aplikasi (konvensi EQOHSEE: Pages/Print + BlankLayout). */
defineOptions({ layout: BlankLayout });

const props = defineProps<{ audit: RingkasAudit; dok: KopDok; ttd: TtdForm; kembali: string; temuan: TemuanKts[] }>();

// Formulir kosong tetap dapat dicetak bila belum ada temuan.
const daftar = computed<Array<TemuanKts | null>>(() => (props.temuan.length ? props.temuan : [null]));

// Dua grup per temuan — masing-masing mulai di lembar baru seperti formulir resmi.
const LEMBAR_TEMUAN: GrupSeksi = { seksi: ['judul', 'uraian', 'batas', 'ttdTemuan'], lekat: ['judul'] };
const LEMBAR_TINDAK_LANJUT: GrupSeksi = { seksi: ['tlJudul', 'akar', 'tindakan', 'bukti', 'tanggal', 'ttdAuditi', 'ulasan', 'verifikasi', 'ttdAuditor'], lekat: ['tlJudul', 'tanggal', 'ulasan'] };
const grup = computed<GrupSeksi[]>(() => daftar.value.flatMap(() => [LEMBAR_TEMUAN, LEMBAR_TINDAK_LANJUT]));
const { ukurEl, halaman } = useSeksiLembar(grup);

const temuanDari = (gi: number) => daftar.value[Math.floor(gi / 2)];
// Lembar pertama tiap grup tidak diberi keterangan lanjutan.
const lanjutan = (n: number) => !!halaman.value && n > 0 && halaman.value[n - 1].grup === halaman.value[n].grup;

// Setiap temuan boleh menimpa Divisi/Departemen kop bila berasal dari
// divisi/departemen lain dari yang tercatat di data perusahaan; kosong
// berarti tetap memakai nilai perusahaan.
const dokUntuk = (t: TemuanKts | null): KopDok => ({
  ...props.dok,
  divisi: t?.divisi || props.dok.divisi,
  departemen: t?.departemen || props.dok.departemen,
});
</script>

<template>
  <Head title="Formulir Ketidaksesuaian & Tindak Lanjut" />
  <CetakLayout judul="Formulir Ketidaksesuaian dan Tindak Lanjut" :kembali="kembali">
    <!-- Wadah ukur: seluruh seksi seluruh temuan dalam satu lembar selebar A4, tidak terlihat. -->
    <div ref="ukurEl" class="smkp-ukur no-print" aria-hidden="true" inert>
      <Lembar :dok="dok" :halaman="1" :dari="1">
        <div data-awal />
        <p data-lanjutan class="mb-2 text-[10.5px] italic text-slate-500">Lanjutan</p>
        <template v-for="(g, gi) in grup" :key="gi">
          <SeksiKetidaksesuaian v-for="s in g.seksi" :key="s" :data-seksi="`${gi}:${s}`" :seksi="s" :t="temuanDari(gi)" :audit="audit" :ttd="ttd" />
        </template>
      </Lembar>
    </div>

    <p v-if="!halaman" class="text-center text-[12.5px] text-slate-500 py-10">Menyusun halaman…</p>
    <template v-else>
      <Lembar v-for="(h, n) in halaman" :key="n" :dok="dokUntuk(temuanDari(h.grup))" :halaman="n + 1" :dari="halaman.length">
        <p v-if="lanjutan(n)" class="mb-2 text-[10.5px] italic text-slate-500">
          {{ h.grup % 2 ? 'Tindak Lanjut Audit' : 'Uraian Ketidaksesuaian' }}<template v-if="temuanDari(h.grup)"> — {{ temuanDari(h.grup)?.nomor }}</template> (lanjutan)
        </p>
        <SeksiKetidaksesuaian v-for="s in h.seksi" :key="s" :seksi="s" :t="temuanDari(h.grup)" :audit="audit" :ttd="ttd" />
      </Lembar>
    </template>
  </CetakLayout>
</template>

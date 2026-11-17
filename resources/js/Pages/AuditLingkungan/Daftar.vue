<script setup lang="ts">
/**
 * Daftar pelaksanaan Audit Kinerja Lingkungan.
 *
 * Tiap kartu membawa skor akhir, peringkat, predikat, capaian per
 * bagian, dan sertifikatnya — bukan hanya judul dan tanggal. Yang
 * membuka daftar ini ingin tahu mitra mana yang tertinggal dan mana
 * yang sudah bersertifikat, dan pertanyaan itu tidak terjawab oleh
 * daftar judul.
 */
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { HalamanAuditLingkunganDaftar } from '../../types';
import UbinAngka from '../../Components/UbinAngka.vue';
import IkonStat from '../../Components/IkonStat.vue';
import { WARNA_BAGIAN, angka } from './bantu';
import './akl.css';

const props = defineProps<HalamanAuditLingkunganDaftar>();

const ubahTahun = (t: string) =>
  router.get(props.tautan.index, { tahun: t }, { preserveState: true, replace: true });

const cari = ref('');
const saringan = ref<'semua' | 'sertifikat' | 'berjalan' | 'selesai'>('semua');

const tampil = computed(() => {
  const q = cari.value.trim().toLowerCase();
  return props.daftar.filter((a) => {
    if (saringan.value === 'sertifikat' && !a.sertifikat) return false;
    if (saringan.value === 'berjalan' && a.status !== 'Berjalan') return false;
    if (saringan.value === 'selesai' && a.status !== 'Selesai') return false;
    if (!q) return true;
    return [a.kode, a.perusahaan, a.judul, a.lokasi].some((x) => (x ?? '').toLowerCase().includes(q));
  });
});

/* ═══════════ ringkasan ═══════════ */

const ringkas = computed(() => {
  const d = props.daftar;
  const lengkap = d.filter((a) => a.belum === 0);
  return {
    jumlah: d.length,
    selesai: d.filter((a) => a.status === 'Selesai').length,
    sertifikat: d.filter((a) => a.sertifikat).length,
    predikat: d.filter((a) => a.predikat.nama).length,
    rata: lengkap.length ? lengkap.reduce((t, a) => t + a.akhir, 0) / lengkap.length : null,
    lengkap: lengkap.length,
  };
});

/** Jumlah audit per peringkat, urut dari tertinggi. */
const sebaran = computed(() => {
  const urut = [...props.opsi.peringkat].sort((a, b) => b.min - a.min);
  const maks = Math.max(1, ...urut.map((p) => props.daftar.filter((a) => a.peringkat.nama === p.nama).length));
  return urut.map((p) => {
    const n = props.daftar.filter((a) => a.peringkat.nama === p.nama).length;
    return { ...p, n, lebar: (n / maks) * 100 };
  });
});

const warnaPeringkat = (p: { nama: string; warna: string }) => (p.nama === 'HITAM' ? 'var(--akl-hitam)' : p.warna);
</script>

<template>
  <Head title="Audit Kinerja Lingkungan" />

  <div class="akl-akar space-y-5">
    <!-- ═══ ringkasan ═══ -->
    <div class="akl-ubin">
      <UbinAngka :angka="ringkas.jumlah" :label="`Audit tahun ${saring.tahun}`">
        <template #ikon><IkonStat nama="audit" /></template>
      </UbinAngka>
      <UbinAngka :angka="ringkas.rata === null ? '—' : angka(ringkas.rata)" label="Rata-rata skor akhir"
                 :nada="ringkas.rata === null ? 'netral' : ringkas.rata >= 80 ? 'baik' : ringkas.rata >= 70 ? 'ingat' : 'gawat'"
                 :catatan="ringkas.lengkap ? `dari ${ringkas.lengkap} audit yang lengkap` : 'belum ada audit yang lengkap'">
        <template #ikon><IkonStat nama="penilaian" /></template>
      </UbinAngka>
      <UbinAngka :angka="`${ringkas.sertifikat} / ${ringkas.predikat}`" label="Bersertifikat / berpredikat"
                 :dari="ringkas.predikat || null" :nilai="ringkas.sertifikat" nada="baik">
        <template #ikon><IkonStat nama="medali" /></template>
      </UbinAngka>
      <UbinAngka :angka="`${ringkas.selesai} / ${ringkas.jumlah}`" label="Audit selesai"
                 :dari="ringkas.jumlah || null" :nilai="ringkas.selesai">
        <template #ikon><IkonStat nama="tuntas" /></template>
      </UbinAngka>
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)] items-start">
      <!-- ═══ sebaran peringkat ═══ -->
      <section class="eq-panel">
        <div class="eq-panel-kepala">
          <h3>Sebaran Peringkat</h3>
          <span class="eq-panel-ket num">{{ ringkas.jumlah }} audit · {{ saring.tahun }}</span>
        </div>
        <ul class="akl-sebaran">
          <li v-for="p in sebaran" :key="p.nama">
            <span class="akl-sebaran-nama">
              <span class="akl-titik" :style="{ background: warnaPeringkat(p) }"></span>
              <b>{{ p.nama }}</b>
              <small>{{ p.min }}+ · {{ p.kriteria.toLowerCase() }}</small>
            </span>
            <span class="akl-sebaran-pita" :title="`${p.nama}: ${p.n} audit`">
              <span :style="{ width: `${p.lebar}%`, background: warnaPeringkat(p) }"></span>
            </span>
            <b class="num akl-sebaran-n">{{ p.n }}</b>
          </li>
        </ul>
      </section>

      <!-- ═══ struktur penilaian ═══ -->
      <section class="eq-panel">
        <div class="eq-panel-kepala">
          <h3>Struktur Penilaian</h3>
          <span class="eq-panel-ket">Bobot total 1,00 — bagian bertanda wajib harus bernilai penuh</span>
        </div>
        <div class="akl-struktur">
          <div v-for="b in bagian" :key="b.kunci" class="akl-struktur-baris">
            <span class="akl-kb-huruf" :style="{ background: WARNA_BAGIAN[b.kunci] }">{{ b.huruf }}</span>
            <span class="min-w-0">
              <b>{{ b.judul }}</b>
              <small class="num">{{ b.kriteria }} kriteria · maks {{ b.maks }}</small>
            </span>
            <span class="akl-struktur-bobot">
              <span class="akl-kb-pita"><span :style="{ width: `${b.bobot * 100}%`, background: WARNA_BAGIAN[b.kunci] }"></span></span>
              <b class="num">{{ angka(b.bobot) }}</b>
            </span>
            <span class="akl-struktur-tanda"><span v-if="b.wajib" class="akl-keping jalan">wajib penuh</span></span>
          </div>
        </div>
      </section>
    </div>

    <!-- ═══ daftar ═══ -->
    <section class="eq-panel">
      <div class="akl-alat">
        <h3 class="text-[16px] font-extrabold" style="color: var(--akl-tinta)">Pelaksanaan Audit</h3>
        <div class="akl-saring" role="group" aria-label="Saring audit">
          <button v-for="s in ([['semua', 'Semua'], ['sertifikat', 'Bersertifikat'], ['berjalan', 'Berjalan'], ['selesai', 'Selesai']] as const)"
                  :key="s[0]" type="button" :class="{ kini: saringan === s[0] }" @click="saringan = s[0]">{{ s[1] }}</button>
        </div>
        <input v-model="cari" type="search" class="akl-isian akl-cari" placeholder="Cari perusahaan, kode, lokasi…"
               aria-label="Cari audit">
        <select class="akl-isian akl-tahun" :value="saring.tahun" aria-label="Periode tahun"
                @change="ubahTahun(($event.target as HTMLSelectElement).value)">
          <option v-for="t in opsi.tahun" :key="t" :value="t">{{ t }}</option>
        </select>
        <a :href="tautan.buat" class="eq-btn-utama" style="flex:none">+ Audit Baru</a>
      </div>

      <div v-if="tampil.length" class="akl-kartu-daftar mt-4">
        <a v-for="a in tampil" :key="a.id" :href="a.url" class="akl-kd"
           :style="{ '--akl-warna': a.peringkat.nama === 'HITAM' ? 'var(--akl-hitam)' : a.peringkat.warna }">
          <div class="flex flex-wrap items-center gap-1.5">
            <span class="akl-keping gelap num">{{ a.kode }}</span>
            <span class="akl-keping" :class="a.status === 'Selesai' ? 'ok' : 'jalan'">{{ a.status }}</span>
            <span v-if="a.sertifikat" class="akl-keping sert">★ Bersertifikat</span>
          </div>

          <div>
            <p class="akl-kd-nama">{{ a.perusahaan ?? a.judul }}</p>
            <p class="akl-kd-sub">
              {{ a.judul }}<template v-if="a.lokasi"> · {{ a.lokasi }}</template><template v-if="a.tanggal"> · {{ a.tanggal }}</template>
            </p>
          </div>

          <div class="akl-kd-skor">
            <div>
              <p class="akl-kd-angka num">{{ angka(a.akhir) }}<small>/ 100</small></p>
              <p class="flex flex-wrap items-center gap-1.5 mt-1.5">
                <span class="akl-keping">
                  <span class="akl-titik" :style="{ background: a.peringkat.nama === 'HITAM' ? 'var(--akl-hitam)' : a.peringkat.warna }"></span>
                  {{ a.peringkat.nama }}
                </span>
                <span v-if="a.predikat.nama" class="akl-keping sert">{{ a.predikat.nama }}</span>
                <span v-else class="text-[10.5px] italic" style="color: var(--akl-tinta-3)">predikat tidak terbit</span>
              </p>
            </div>
            <div class="akl-mini" :aria-label="`Capaian per bagian: ${Object.entries(a.bagianPersen).map(([k, v]) => `${k.toUpperCase()} ${v}%`).join(', ')}`">
              <span v-for="(v, k) in a.bagianPersen" :key="k" :title="`Bagian ${String(k).toUpperCase()}: ${v}%`">
                <i :style="{ height: `${Math.max(2, v * 0.26)}px` }"></i>
                <em>{{ String(k).toUpperCase() }}</em>
              </span>
            </div>
          </div>

          <div>
            <div class="akl-kd-pita"><span :style="{ width: `${((a.kriteria - a.belum) / Math.max(1, a.kriteria)) * 100}%` }"></span></div>
            <div class="akl-kd-kaki mt-1.5">
              <span class="num">{{ a.kriteria - a.belum }} / {{ a.kriteria }} diverifikasi</span>
              <span v-if="a.pengurang.length" class="num" style="color: #DC2626">· −{{ angka(a.pemenuhan - a.akhir, 0) }} pengurang</span>
              <span v-if="a.sertifikat" class="kanan num">{{ a.sertifikat.nomor }}</span>
              <span v-else class="kanan">Buka →</span>
            </div>
          </div>
        </a>
      </div>

      <p v-else-if="daftar.length" class="eq-kosong eq-kosong-kecil">
        <strong>Tidak ada audit yang cocok.</strong>
        <span class="block halus">Ubah kata pencarian atau saringannya.</span>
      </p>

      <p v-else class="eq-kosong">
        <strong>Belum ada audit pada tahun {{ saring.tahun }}.</strong>
        <span class="block halus">Instrumen ini menilai mitra kerja pada enam bagian, dua ratus satu kriteria.</span>
        <a :href="tautan.buat" class="eq-btn-utama">+ Mulai Audit</a>
      </p>
    </section>
  </div>
</template>

<style scoped>
.akl-ubin { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); }

.akl-sebaran { display: grid; gap: .55rem; }
.akl-sebaran > li { display: grid; grid-template-columns: minmax(150px, 1.1fr) minmax(0, 1fr) 2rem; align-items: center; gap: .7rem; }
/* Keterangan ambang di baris kedua, sejajar dengan namanya — utuh,
   tidak dipotong titik ("melebihi ketaat…"). */
.akl-sebaran-nama { display: flex; flex-wrap: wrap; align-items: center; column-gap: .45rem; row-gap: 0; min-width: 0; }
.akl-sebaran-nama b { font-size: 11.5px; letter-spacing: .04em; color: var(--akl-tinta); }
.akl-sebaran-nama small { flex-basis: 100%; padding-left: 1rem; font-size: 10px; line-height: 1.35; color: var(--akl-tinta-3); }
.akl-sebaran-pita { height: 12px; border-radius: 0 4px 4px 0; background: var(--akl-jalur); overflow: hidden; }
.akl-sebaran-pita > span { display: block; height: 100%; border-radius: 0 4px 4px 0; min-width: 0; }
.akl-sebaran-n { font-size: 13px; text-align: right; color: var(--akl-tinta); }

.akl-struktur { display: grid; gap: .45rem; }
.akl-struktur-baris {
  display: grid; grid-template-columns: 30px minmax(0, 1fr) 120px 84px; align-items: center; gap: .7rem;
  padding: .35rem 0; border-bottom: 1px solid var(--akl-garis-halus);
}
.akl-struktur-baris:last-child { border-bottom: 0; }
.akl-struktur-baris b { display: block; font-size: 12px; color: var(--akl-tinta); line-height: 1.3; }
.akl-struktur-baris small { font-size: 10.5px; color: var(--akl-tinta-3); }
.akl-struktur-bobot { display: flex; align-items: center; gap: .45rem; }
.akl-struktur-bobot .akl-kb-pita { flex: 1; }
.akl-struktur-bobot b { font-size: 11.5px; width: 2.2rem; text-align: right; }

.akl-alat { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; }
.akl-alat h3 { margin-right: auto; }
.akl-cari { width: 15rem; max-width: 100%; }
.akl-tahun { width: auto; }
.akl-saring { display: flex; flex-wrap: wrap; gap: .3rem; }
.akl-saring button {
  font-size: 11px; font-weight: 700; padding: .3rem .7rem; border-radius: 999px;
  border: 1px solid var(--akl-garis); color: var(--akl-tinta-2);
}
.akl-saring button.kini { background: #0F1720; border-color: #0F1720; color: #fff; }
:global(:root[data-tema="gelap"] .akl-saring button.kini) { background: #EEF3F4; border-color: #EEF3F4; color: #0F1720; }

@media (max-width: 40rem) {
  .akl-struktur-baris { grid-template-columns: 30px minmax(0, 1fr) 84px; }
  .akl-struktur-bobot { grid-column: 2 / -1; grid-row: 2; }
  .akl-cari { width: 100%; }
  .akl-sebaran > li { grid-template-columns: minmax(120px, auto) minmax(0, 1fr) 1.6rem; }
}
</style>

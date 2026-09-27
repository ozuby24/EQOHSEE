<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import LapanganLayout from '../../Layouts/LapanganLayout.vue';
import IkonLapangan from '../../Components/IkonLapangan.vue';
import BilahLuring from '../../Components/BilahLuring.vue';
import type { NamaIkon } from '../../lapangan/ikon';

defineOptions({ layout: LapanganLayout });

type Peringatan = { jenis: 'air' | 'lereng'; kode: string; level: 'waspada' | 'siaga'; judul: string; ket: string; persen: number | null; kaki: string | null; url: string | null };
type Tindakan = { jenis: 'hazard' | 'izin' | 'p2h'; kode: string; judul: string; ket: string; nada: 'bahaya' | 'waspada' | 'netral'; url: string };

const p = defineProps<{
  saya: { nama: string; depan: string; inisial: string; jabatan: string | null; perusahaan: string | null };
  waktu: { tanggal: string; shift: string; salam: string };
  site: string | null;
  cuaca: { hujanMm: number; tempat: string } | null;
  angka: { tanpaLti: number | null; perlu: number; peringatan: number };
  peringatan: Peringatan[];
  tindakan: Tindakan[];
}>();

const IKON_TINDAKAN: Record<Tindakan['jenis'], NamaIkon> = { hazard: 'bahaya', izin: 'api', p2h: 'tugas' };

const hujan = computed(() => {
  if (!p.cuaca) return null;
  const mm = Number(p.cuaca.hujanMm);
  return mm > 0 ? `Hujan ${mm.toLocaleString('id-ID', { maximumFractionDigits: 1 })} mm/jam` : 'Tidak hujan';
});

const angkaLti = computed(() => (p.angka.tanpaLti === null ? '—' : p.angka.tanpaLti.toLocaleString('id-ID')));
</script>

<template>
  <Head title="Beranda lapangan" />

  <header class="kepala">
    <div class="sapa">
      <span class="avatar">{{ saya.inisial || '·' }}</span>
      <div class="sapa-isi">
        <div class="lp-mono waktu">{{ waktu.tanggal }} · {{ waktu.shift.toUpperCase() }}</div>
        <h1>{{ waktu.salam }}, {{ saya.depan }}</h1>
      </div>
      <Link href="/lapangan/tugas" class="lonceng" :aria-label="`${angka.perlu} perlu tindakan`">
        <IkonLapangan nama="lonceng" :tebal="1.8" />
        <span v-if="angka.perlu" class="lonceng-n">{{ angka.perlu > 99 ? '99+' : angka.perlu }}</span>
      </Link>
    </div>

    <div class="situs">
      <span v-if="site" class="situs-chip"><IkonLapangan nama="pin" :tebal="2" class="jingga" />{{ site }}</span>
      <span v-if="hujan" class="cuaca"><IkonLapangan :nama="Number(cuaca?.hujanMm) > 0 ? 'hujan' : 'awan'" :tebal="1.8" />{{ hujan }}</span>
    </div>

    <BilahLuring gelap />

    <div class="angka">
      <div>
        <div class="angka-n">{{ angkaLti }}</div>
        <div class="angka-k">{{ angka.tanpaLti === null ? 'Belum ada catatan LTI' : 'Hari tanpa LTI' }}</div>
      </div>
      <div>
        <div class="angka-n" :class="{ jingga: angka.perlu > 0 }">{{ angka.perlu }}</div>
        <div class="angka-k">Perlu tindakan</div>
      </div>
      <div>
        <div class="angka-n" :class="{ jingga: angka.peringatan > 0 }">{{ angka.peringatan }}</div>
        <div class="angka-k">Peringatan site</div>
      </div>
    </div>
  </header>

  <section aria-labelledby="judul-peringatan">
    <div class="lp-bagian" style="padding-top:18px"><h2 id="judul-peringatan">Peringatan site</h2></div>
    <div v-if="peringatan.length" class="geser-kartu lp-geser">
      <component :is="w.url ? 'a' : 'div'" v-for="w in peringatan" :key="w.kode + w.judul" :href="w.url ?? undefined"
                 class="lp-kartu peringatan" :class="w.level === 'siaga' ? 'nada-bahaya' : 'nada-waspada'">
        <div class="peringatan-kepala">
          <span class="lp-petak kecil" :class="w.level === 'siaga' ? 'nada-bahaya' : 'nada-waspada'">
            <IkonLapangan :nama="w.jenis === 'air' ? 'air' : 'lereng'" :tebal="2" />
          </span>
          <span class="lp-mono peringatan-kode">{{ w.kode }}</span>
          <span class="lp-pil" :class="w.level === 'siaga' ? 'nada-bahaya' : 'nada-waspada'">{{ w.level === 'siaga' ? 'Siaga' : 'Waspada' }}</span>
        </div>
        <div class="peringatan-judul">{{ w.judul }}</div>
        <div class="peringatan-ket">{{ w.ket }}</div>
        <template v-if="w.persen !== null">
          <div class="isi-bar" role="img" :aria-label="`Terisi ${Math.round(w.persen)} persen, batas siaga 90 persen`">
            <span :style="{ width: w.persen + '%', background: w.level === 'siaga' ? 'var(--bahaya)' : 'var(--waspada)' }"></span>
            <i></i>
          </div>
          <div class="lp-mono peringatan-kaki"><span>{{ w.kaki || '' }}</span><span>siaga 90%</span></div>
        </template>
        <div v-else-if="w.kaki" class="lp-mono peringatan-kaki"><span>{{ w.kaki }}</span></div>
      </component>
    </div>
    <div v-else class="lp-kartu tenang">
      <span class="lp-petak nada-aman"><IkonLapangan nama="perisai" /></span>
      <div>
        <div class="tenang-judul">Tidak ada peringatan</div>
        <div class="tenang-ket">Kolam di bawah 70% kapasitas dan lereng di bawah ambang waspada.</div>
      </div>
    </div>
  </section>

  <section aria-labelledby="judul-tindakan">
    <div class="lp-bagian">
      <h2 id="judul-tindakan">Perlu tindakan Anda</h2>
      <Link v-if="angka.perlu > tindakan.length" href="/lapangan/tugas">Lihat semua {{ angka.perlu }}</Link>
      <span v-else class="lp-mono lp-hitung">{{ angka.perlu }}</span>
    </div>
    <div v-if="tindakan.length" class="lp-kartu lp-daftar">
      <Link v-for="t in tindakan" :key="t.kode + t.judul" :href="t.url" class="lp-baris">
        <span class="lp-petak" :class="'nada-' + t.nada"><IkonLapangan :nama="IKON_TINDAKAN[t.jenis]" /></span>
        <span class="lp-baris-isi">
          <span class="lp-mono lp-baris-kode" style="display:block">{{ t.kode }}</span>
          <span class="lp-baris-judul" style="display:block">{{ t.judul }}</span>
          <span class="lp-baris-ket" :class="'nada-' + t.nada" style="display:block">{{ t.ket }}</span>
        </span>
        <IkonLapangan nama="kanan" :tebal="2.2" class="lp-baris-panah" />
      </Link>
    </div>
    <div v-else class="lp-kosong"><strong>Tidak ada yang menunggu Anda</strong>Laporan, uji gas, dan P2H shift ini sudah beres.</div>
  </section>

  <section aria-labelledby="judul-pintasan">
    <div class="lp-bagian"><h2 id="judul-pintasan">Pintasan</h2></div>
    <div class="pintasan">
      <Link href="/lapangan/p2h" class="lp-kartu"><span class="lp-petak nada-jingga"><IkonLapangan nama="truk" /></span>P2H unit</Link>
      <Link href="/lapangan/izin" class="lp-kartu"><span class="lp-petak nada-biru"><IkonLapangan nama="izin" /></span>Izin kerja</Link>
      <Link href="/lapangan/sertifikat" class="lp-kartu"><span class="lp-petak nada-aman"><IkonLapangan nama="sertifikat" /></span>Sertifikat</Link>
    </div>
  </section>
</template>

<style scoped>
.kepala { background: var(--ink); padding: calc(env(safe-area-inset-top, 0px) + 14px) 0 18px; color: #FFFFFF; }
.sapa { display: flex; align-items: center; gap: 12px; padding: 0 20px; }
.avatar { width: 44px; height: 44px; border-radius: 22px; background: #1E2835; border: 1px solid rgba(255, 255, 255, .14); display: grid; place-items: center; font-size: 15px; font-weight: 700; flex: none; }
.sapa-isi { flex: 1; min-width: 0; }
.waktu { font-size: 11px; letter-spacing: .06em; color: rgba(255, 255, 255, .66); }
h1 { margin: 3px 0 0; font-size: 20px; font-weight: 700; letter-spacing: -.01em; line-height: 1.15; overflow-wrap: anywhere; }
.lonceng { position: relative; width: 44px; height: 44px; border-radius: 22px; border: 1px solid rgba(255, 255, 255, .18); display: grid; place-items: center; flex: none; color: #FFFFFF; }
.lonceng svg { width: 21px; height: 21px; }
.lonceng-n { position: absolute; top: -3px; right: -3px; min-width: 20px; height: 20px; padding: 0 4px; border-radius: 10px; background: var(--sinyal); color: var(--ink); font-size: 11px; font-weight: 700; display: grid; place-items: center; border: 2px solid var(--ink); }

.situs { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 16px 20px 0; flex-wrap: wrap; }
.situs-chip { display: inline-flex; align-items: center; gap: 7px; min-height: 36px; padding: 0 12px 0 10px; border-radius: 18px; background: #151D26; border: 1px solid rgba(255, 255, 255, .12); font-size: 13.5px; font-weight: 600; max-width: 100%; }
.situs-chip svg { width: 16px; height: 16px; flex: none; }
.jingga { color: #FF9800; }
.cuaca { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: rgba(255, 255, 255, .76); white-space: nowrap; }
.cuaca svg { width: 17px; height: 17px; }

.angka { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); margin: 18px 20px 0; border-top: 1px solid rgba(255, 255, 255, .12); padding-top: 14px; }
.angka > div { padding: 0 12px; border-left: 1px solid rgba(255, 255, 255, .12); }
.angka > div:first-child { padding-left: 0; border-left: 0; }
.angka-n { font-size: 30px; font-weight: 700; font-stretch: 88%; line-height: 1; font-variant-numeric: tabular-nums; }
.angka-n.jingga { color: #FF9800; }
.angka-k { font-size: 12.5px; color: rgba(255, 255, 255, .68); margin-top: 6px; line-height: 1.25; }

.geser-kartu { padding: 12px 20px 2px; scroll-snap-type: x mandatory; }
.peringatan { flex: none; width: min(282px, 80vw); padding: 14px 16px; scroll-snap-align: start; display: block; }
.peringatan-kepala { display: flex; align-items: center; gap: 10px; }
.lp-petak.kecil { width: 32px; height: 32px; border-radius: 9px; }
.lp-petak.kecil svg { width: 18px; height: 18px; }
.peringatan-kode { font-size: 11.5px; font-weight: 500; color: var(--abu2); letter-spacing: .04em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; }
.peringatan-kepala .lp-pil { margin-left: auto; }
.peringatan-judul { font-size: 19px; font-weight: 700; color: var(--ink); margin-top: 12px; letter-spacing: -.01em; }
.peringatan-ket { font-size: 13.5px; line-height: 1.45; color: var(--abu2); margin-top: 3px; }
.isi-bar { position: relative; height: 6px; border-radius: 3px; background: var(--garis3); margin-top: 12px; }
.isi-bar span { position: absolute; left: 0; top: 0; bottom: 0; border-radius: 3px; }
.isi-bar i { position: absolute; left: 90%; top: -3px; width: 2px; height: 12px; background: var(--ink); }
.peringatan-kaki { display: flex; justify-content: space-between; gap: 8px; font-size: 10.5px; color: var(--abu3); margin-top: 8px; white-space: nowrap; }
.peringatan-kaki span:first-child { overflow: hidden; text-overflow: ellipsis; }

.tenang { margin: 12px 20px 0; padding: 14px 16px; display: flex; gap: 12px; align-items: center; }
.tenang-judul { font-size: 15px; font-weight: 700; color: var(--ink); }
.tenang-ket { font-size: 13px; line-height: 1.45; color: var(--abu2); margin-top: 2px; }

.pintasan { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; padding: 12px 20px 0; }
.pintasan a { display: flex; flex-direction: column; align-items: flex-start; gap: 10px; padding: 12px; min-height: 88px; font-size: 14px; font-weight: 600; color: var(--ink); }
</style>

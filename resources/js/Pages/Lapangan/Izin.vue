<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import LapanganLayout from '../../Layouts/LapanganLayout.vue';
import IkonLapangan from '../../Components/IkonLapangan.vue';
import BilahLuring from '../../Components/BilahLuring.vue';
import { jaringan } from '../../lapangan/kotakKeluar';
import { kabar } from '../../lapangan/kabar';
import type { NamaIkon } from '../../lapangan/ikon';

defineOptions({ layout: LapanganLayout });

type Rinci = { kode: string; nama: string; nilai: number | null; satuan: string; min: number | null; maks: number | null; keadaan: 'aman' | 'di-luar-ambang' | 'tidak-diukur' };
type Izin = {
  id: number; nomor: string; jenis: string; uraian: string; lokasi: string | null; jam: string; pemohon: string | null;
  status: 'aktif' | 'menunggu' | 'ditutup' | 'draf'; statusLabel: string;
  gas: null | { usia: number | null; batas: number; sisa: number | null; segar: boolean; bacaan: null | { waktu: string; rinci: Rinci[]; lulus: boolean | null } };
  bisaTutup: boolean;
  tautan: { gas: string; tutup: string };
};

const p = defineProps<{
  izin: Izin[];
  bentrok: { kode: string; judul: string; ket: string; saran: string }[];
  tautan: { lengkap: string; baru: string };
  petugas: string;
}>();

const tab = ref<'aktif' | 'menunggu' | 'ditutup'>('aktif');
const hitung = computed(() => ({
  aktif: p.izin.filter((i) => i.status === 'aktif').length,
  menunggu: p.izin.filter((i) => i.status === 'menunggu' || i.status === 'draf').length,
  ditutup: p.izin.filter((i) => i.status === 'ditutup').length,
}));
const tampil = computed(() => p.izin.filter((i) => (tab.value === 'menunggu' ? ['menunggu', 'draf'].includes(i.status) : i.status === tab.value)));

const IKON: Record<string, NamaIkon> = { panas: 'api', 'ruang-terbatas': 'gas' };
const NADA_IKON: Record<string, string> = { panas: 'nada-jingga', 'ruang-terbatas': 'nada-biru' };
const STATUS: Record<Izin['status'], string> = { aktif: 'nada-aman', menunggu: 'nada-waspada', draf: 'nada-netral', ditutup: 'nada-netral' };
const LABEL_GAS: Record<string, string> = { o2: 'O₂', lel: 'LEL', h2s: 'H₂S', co: 'CO' };

const angka = (x: number | null) => (x === null ? '—' : x.toLocaleString('id-ID', { maximumFractionDigits: 1 }));
const rentang = (r: Rinci) => (r.min !== null && r.maks !== null ? `${angka(r.min)}–${angka(r.maks)}` : r.maks !== null ? `< ${angka(r.maks)}` : r.min !== null ? `> ${angka(r.min)}` : '');
const urutGas = (rinci: Rinci[]) => ['o2', 'lel', 'h2s', 'co'].map((k) => rinci.find((r) => r.kode === k)).filter(Boolean) as Rinci[];

function gasKeadaan(g: NonNullable<Izin['gas']>) {
  if (g.usia === null) return { judul: 'Belum ada uji gas', sisa: 'wajib diuji', nada: 'bahaya', persen: 100 };
  const persen = Math.min(100, Math.round((g.usia / Math.max(1, g.batas)) * 100));
  if (!g.segar || (g.sisa ?? 0) <= 0) return { judul: `Uji gas ${g.usia} menit lalu`, sisa: `lewat ${Math.abs(g.sisa ?? 0)} mnt`, nada: 'bahaya', persen: 100 };
  return { judul: `Uji gas ${g.usia} menit lalu`, sisa: `sisa ${g.sisa} mnt`, nada: (g.sisa ?? 0) <= 15 ? 'waspada' : 'aman', persen };
}

/* ═══════════ uji gas ulang & tutup izin ═══════════ */
const aktifIzin = ref<Izin | null>(null);
const mode = ref<'gas' | 'tutup'>('gas');

function waktuSetempat(): string {
  const d = new Date();
  d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
  return d.toISOString().slice(0, 16);
}

const gas = useForm({ waktu_uji: '', o2: '', lel: '', co: '', h2s: '', alat: '', petugas: p.petugas, catatan: '' });
const tutup = useForm({ catatan_penutupan: '' });

function buka(i: Izin, m: 'gas' | 'tutup') {
  if (!jaringan.daring) { kabar('Perlu sinyal untuk menyimpan ke izin kerja.', true); return; }
  aktifIzin.value = i;
  mode.value = m;
  gas.reset(); gas.clearErrors(); tutup.reset(); tutup.clearErrors();
  gas.waktu_uji = waktuSetempat();
  gas.petugas = p.petugas;
}

function simpanGas() {
  if (!aktifIzin.value) return;
  gas.transform((d) => ({ ...d, o2: d.o2.replace(',', '.'), lel: d.lel.replace(',', '.'), co: d.co.replace(',', '.'), h2s: d.h2s.replace(',', '.') }))
    .post(aktifIzin.value.tautan.gas, { preserveScroll: true, onSuccess: () => { aktifIzin.value = null; } });
}

function simpanTutup() {
  if (!aktifIzin.value) return;
  tutup.post(aktifIzin.value.tautan.tutup, { preserveScroll: true, onSuccess: () => { aktifIzin.value = null; } });
}
</script>

<template>
  <Head title="Izin kerja" />

  <div class="lp-atas">
    <div>
      <h1 class="lp-judul">Izin kerja</h1>
      <div class="lp-subjudul">Izin yang berlaku hari ini</div>
    </div>
    <a :href="tautan.baru" class="tambah" aria-label="Ajukan izin baru di web"><IkonLapangan nama="tambah" :tebal="2.2" /></a>
  </div>
  <div style="height:12px"></div>
  <BilahLuring />

  <div class="lp-segmen tiga" role="group" aria-label="Saring status">
    <button type="button" :aria-pressed="tab === 'aktif'" @click="tab = 'aktif'">Aktif<small>{{ hitung.aktif }}</small></button>
    <button type="button" :aria-pressed="tab === 'menunggu'" @click="tab = 'menunggu'">Menunggu<small>{{ hitung.menunggu }}</small></button>
    <button type="button" :aria-pressed="tab === 'ditutup'" @click="tab = 'ditutup'">Ditutup<small>{{ hitung.ditutup }}</small></button>
  </div>

  <div v-for="b in bentrok" :key="b.kode" class="bentrok" role="alert">
    <span class="bentrok-ikon"><IkonLapangan nama="bahaya" :tebal="2.2" /></span>
    <div>
      <div class="bentrok-judul">Bentrok lokasi · {{ b.judul }}</div>
      <div class="bentrok-ket">{{ b.ket }}</div>
      <div class="bentrok-saran">{{ b.saran }}</div>
    </div>
  </div>

  <div v-if="!tampil.length" class="lp-kosong">
    <strong>{{ tab === 'aktif' ? 'Tidak ada izin aktif hari ini' : tab === 'menunggu' ? 'Tidak ada izin menunggu' : 'Belum ada izin ditutup hari ini' }}</strong>
    Izin diajukan dan diterbitkan dari versi web.
  </div>

  <article v-for="i in tampil" :key="i.id" class="lp-kartu izin">
    <div class="izin-kepala">
      <span class="lp-petak" :class="NADA_IKON[i.jenis] ?? 'nada-netral'"><IkonLapangan :nama="IKON[i.jenis] ?? 'izin'" /></span>
      <div class="izin-isi">
        <div class="lp-mono lp-baris-kode">{{ i.nomor }} · {{ i.jenis.replace('-', ' ').toUpperCase() }}</div>
        <div class="izin-judul">{{ i.uraian }}</div>
        <div class="izin-ket">{{ i.jam }}<template v-if="i.lokasi"> · {{ i.lokasi }}</template><template v-if="i.pemohon"> · pemohon {{ i.pemohon }}</template></div>
      </div>
      <span class="lp-pil" :class="STATUS[i.status]">{{ i.statusLabel }}</span>
    </div>

    <template v-if="i.gas && i.status !== 'ditutup'">
      <div class="gas" :class="'gas-' + gasKeadaan(i.gas).nada">
        <div class="gas-atas">
          <IkonLapangan nama="jam" :tebal="2" />
          <span class="gas-judul">{{ gasKeadaan(i.gas).judul }}</span>
          <span class="lp-mono gas-sisa">{{ gasKeadaan(i.gas).sisa }}</span>
        </div>
        <div class="gas-bar"><span :style="{ width: gasKeadaan(i.gas).persen + '%' }"></span></div>
        <div class="gas-ket">Uji ulang wajib tiap {{ i.gas.batas }} menit selama pekerjaan berjalan.</div>
      </div>
      <div v-if="i.gas.bacaan" class="bacaan">
        <div v-for="r in urutGas(i.gas.bacaan.rinci)" :key="r.kode" class="bacaan-sel">
          <div class="bacaan-atas">
            <span class="lp-mono">{{ LABEL_GAS[r.kode] ?? r.kode }}</span>
            <i :class="r.keadaan" :title="r.keadaan === 'aman' ? 'Dalam ambang' : r.keadaan === 'tidak-diukur' ? 'Tidak diukur' : 'Di luar ambang'"></i>
          </div>
          <div class="bacaan-n">{{ angka(r.nilai) }}<small>{{ r.satuan.replace('%LEL', '%') }}</small></div>
          <div class="lp-mono bacaan-r">{{ rentang(r) }}</div>
        </div>
      </div>
    </template>

    <div v-if="i.bisaTutup" class="izin-aksi">
      <button v-if="i.gas" type="button" class="lp-tombol kecil" style="flex:1.4" @click="buka(i, 'gas')"><IkonLapangan nama="gas" :tebal="2" />Uji gas ulang</button>
      <button type="button" class="lp-tombol garis kecil" style="flex:1" @click="buka(i, 'tutup')">Tutup izin</button>
    </div>
  </article>

  <div class="kaki"><a :href="tautan.lengkap" class="lp-tombol garis kecil"><IkonLapangan nama="layar" :tebal="1.9" />Seluruh izin di versi web</a></div>

  <div v-if="aktifIzin" class="tirai" @click.self="aktifIzin = null">
    <form v-if="mode === 'gas'" class="lembar lp-muncul" role="dialog" aria-modal="true" aria-labelledby="judul-gas" @submit.prevent="simpanGas">
      <div class="lembar-kepala">
        <h2 id="judul-gas">Uji gas · {{ aktifIzin.nomor }}</h2>
        <button type="button" class="lp-ikon-tombol" aria-label="Tutup" @click="aktifIzin = null"><IkonLapangan nama="tutup" :tebal="2" /></button>
      </div>
      <div class="kisi-gas">
        <label v-for="k in ['o2', 'lel', 'h2s', 'co']" :key="k">
          <span class="lp-label">{{ LABEL_GAS[k] }} <small>{{ k === 'o2' ? '%' : k === 'lel' ? '%LEL' : 'ppm' }}</small></span>
          <input v-model="(gas as any)[k]" class="lp-masukan lp-mono" inputmode="decimal" autocomplete="off">
          <span v-if="(gas.errors as any)[k]" class="lp-salah">{{ (gas.errors as any)[k] }}</span>
        </label>
      </div>
      <label class="blok"><span class="lp-label">Waktu uji</span><input v-model="gas.waktu_uji" type="datetime-local" class="lp-masukan"></label>
      <p v-if="gas.errors.waktu_uji" class="lp-salah">{{ gas.errors.waktu_uji }}</p>
      <div class="kisi-dua">
        <label><span class="lp-label">Alat</span><input v-model="gas.alat" class="lp-masukan" maxlength="100" placeholder="mis. Altair 4X"></label>
        <label><span class="lp-label">Petugas</span><input v-model="gas.petugas" class="lp-masukan" maxlength="150"></label>
      </div>
      <button type="submit" class="lp-tombol" style="margin-top:18px" :disabled="gas.processing">{{ gas.processing ? 'Menyimpan…' : 'Simpan uji gas' }}</button>
    </form>

    <form v-else class="lembar lp-muncul" role="dialog" aria-modal="true" aria-labelledby="judul-tutup" @submit.prevent="simpanTutup">
      <div class="lembar-kepala">
        <h2 id="judul-tutup">Tutup {{ aktifIzin.nomor }}</h2>
        <button type="button" class="lp-ikon-tombol" aria-label="Tutup" @click="aktifIzin = null"><IkonLapangan nama="tutup" :tebal="2" /></button>
      </div>
      <p class="tutup-ket">Pastikan area sudah bersih, peralatan diamankan, dan seluruh orang sudah keluar.</p>
      <label class="blok"><span class="lp-label block">Catatan penutupan</span>
        <textarea v-model="tutup.catatan_penutupan" class="lp-masukan" rows="3" maxlength="1000" placeholder="mis. Pekerjaan selesai 14:30, area dibersihkan, APAR dikembalikan"></textarea>
      </label>
      <p v-if="tutup.errors.catatan_penutupan || (tutup.errors as any).alur" class="lp-salah">{{ tutup.errors.catatan_penutupan || (tutup.errors as any).alur }}</p>
      <button type="submit" class="lp-tombol gelap" style="margin-top:18px" :disabled="tutup.processing">{{ tutup.processing ? 'Menyimpan…' : 'Tutup izin' }}</button>
    </form>
  </div>
</template>

<style scoped>
.tambah { width: 44px; height: 44px; border-radius: 12px; background: var(--ink); color: #FFFFFF; display: grid; place-items: center; flex: none; }
.tambah svg { width: 20px; height: 20px; }
.lp-segmen.tiga { grid-template-columns: repeat(3, minmax(0, 1fr)); margin: 4px 20px 0; }

.bentrok { margin: 14px 20px 0; background: #FDECEC; border: 1px solid #F4C4C4; border-radius: 14px; padding: 13px 14px; display: flex; gap: 12px; }
.bentrok-ikon { width: 32px; height: 32px; border-radius: 9px; background: var(--bahaya); color: #FFFFFF; display: grid; place-items: center; flex: none; }
.bentrok-ikon svg { width: 18px; height: 18px; }
.bentrok-judul { font-size: 14.5px; font-weight: 700; color: #991B1B; }
.bentrok-ket { font-size: 13px; line-height: 1.45; color: var(--abu1); margin-top: 3px; }
.bentrok-saran { font-size: 12.5px; line-height: 1.45; color: #7F1D1D; margin-top: 6px; font-weight: 600; }

.izin { margin: 12px 20px 0; padding: 16px; }
.izin-kepala { display: flex; gap: 12px; align-items: flex-start; }
.izin-kepala .lp-petak { width: 40px; height: 40px; }
.izin-isi { flex: 1; min-width: 0; }
.izin-judul { font-size: 16px; font-weight: 700; color: var(--ink); margin-top: 2px; overflow-wrap: anywhere; }
.izin-ket { font-size: 13px; color: var(--abu2); margin-top: 2px; }

.gas { margin-top: 14px; border-radius: 12px; padding: 12px 14px; }
.gas-aman { background: var(--aman-lunak); border: 1px solid #BFE6CB; --gas: #16A34A; --gas-lunak: #CDEFD8; --gas-teks: var(--aman-teks); }
.gas-waspada { background: #FEF8E1; border: 1px solid #F1E0A0; --gas: #CA8A04; --gas-lunak: #F1E4B5; --gas-teks: var(--waspada-teks); }
.gas-bahaya { background: #FDECEC; border: 1px solid #F4C4C4; --gas: var(--bahaya); --gas-lunak: #F8CDCD; --gas-teks: var(--bahaya-teks); }
.gas-atas { display: flex; align-items: center; gap: 8px; }
.gas-atas svg { width: 16px; height: 16px; color: var(--gas-teks); flex: none; }
.gas-judul { font-size: 14px; font-weight: 600; color: var(--ink); }
.gas-sisa { margin-left: auto; font-size: 12.5px; font-weight: 600; color: var(--gas-teks); white-space: nowrap; }
.gas-bar { height: 6px; border-radius: 3px; background: var(--gas-lunak); margin-top: 10px; overflow: hidden; }
.gas-bar span { display: block; height: 100%; background: var(--gas); }
.gas-ket { font-size: 12px; color: #5E6875; margin-top: 8px; }

.bacaan { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 6px; margin-top: 12px; }
.bacaan-sel { border: 1px solid var(--garis); border-radius: 10px; padding: 9px 8px; min-width: 0; }
.bacaan-atas { display: flex; align-items: center; justify-content: space-between; font-size: 11px; font-weight: 600; color: var(--abu1); }
.bacaan-atas i { width: 7px; height: 7px; border-radius: 4px; background: var(--aman); }
.bacaan-atas i.di-luar-ambang { background: var(--bahaya); }
.bacaan-atas i.tidak-diukur { background: var(--abu4); }
.bacaan-n { font-size: 17px; font-weight: 700; color: var(--ink); margin-top: 4px; font-variant-numeric: tabular-nums; }
.bacaan-n small { font-size: 11px; font-weight: 600; color: var(--abu3); margin-left: 2px; }
.bacaan-r { font-size: 10px; color: var(--abu3); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.izin-aksi { display: flex; gap: 8px; margin-top: 14px; }
.kaki { padding: 18px 20px 0; }

.tirai { position: fixed; inset: 0; background: rgba(11, 17, 23, .5); z-index: 70; display: flex; align-items: flex-end; justify-content: center; }
.lembar { width: 100%; max-width: 480px; max-height: 92dvh; overflow-y: auto; background: #FFFFFF; border-radius: 22px 22px 0 0; padding: 8px 20px calc(20px + env(safe-area-inset-bottom, 0px)); }
.lembar-kepala { display: flex; align-items: center; justify-content: space-between; }
.lembar-kepala h2 { margin: 0; font-size: 16px; font-weight: 700; color: var(--ink); }
.kisi-gas { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 12px; margin-top: 6px; }
.kisi-dua { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 12px; margin-top: 12px; }
.blok { display: block; margin-top: 12px; }
.lp-masukan { margin-top: 6px; }
.tutup-ket { font-size: 14px; line-height: 1.5; color: var(--abu1); margin: 4px 0 0; }
</style>

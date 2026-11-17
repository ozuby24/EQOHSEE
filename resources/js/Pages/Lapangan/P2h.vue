<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import LapanganLayout from '../../Layouts/LapanganLayout.vue';
import IkonLapangan from '../../Components/IkonLapangan.vue';
import BilahLuring from '../../Components/BilahLuring.vue';
import { ambilDraf, hapusDraf, hapusKiriman, jaringan, kirim, klienId, perkecilFoto, simpanDraf, simpanKiriman, type Kiriman } from '../../lapangan/kotakKeluar';
import { kabar } from '../../lapangan/kabar';

defineOptions({ layout: LapanganLayout });

type Butir = { kode: string; kelompok: string; uraian: string; kritis: boolean };
type Nilai = 'ok' | 'tidak' | 'na';

const p = defineProps<{
  unit: {
    id: number; kode: string; nama: string; jenis: string; ket: string | null; hm: number | string | null; status: string;
    ditahanSejak: string | null; ditahanKarena: string | null; sudahShiftIni: boolean;
    terakhir: { tanggal: string; shift: string; hasil: string; operator: string } | null;
  };
  butir: Butir[];
  shift: { nilai: string; pilihan: Record<string, string> };
  operator: string;
  tautan: { simpan: string; daftar: string };
}>();

const halaman = usePage<any>();
const uid = computed(() => Number(halaman.props?.pengguna?.id ?? 0));
const kunciDraf = computed(() => `${uid.value}:p2h:${p.unit.id}`);

type Foto = { id: string; nama: string; tipe: string; data: Blob; url: string };

const f = reactive({
  klien_id: klienId(),
  jawab: {} as Record<string, Nilai>,
  shift: p.shift.nilai,
  operator: p.operator,
  hm: '' as string,
  catatan: '',
  dibuat: new Date().toISOString(),
});
const foto = ref<Foto[]>([]);
const galat = ref<Record<string, string>>({});
const mengirim = ref(false);
const selesai = ref<null | { luring: boolean; ditahan: boolean }>(null);

const PILIHAN: { nilai: Nilai; label: string; bg: string; fg: string }[] = [
  { nilai: 'ok', label: 'OK', bg: '#22C55E', fg: '#0B1117' },
  { nilai: 'tidak', label: 'Tidak', bg: '#DC2626', fg: '#FFFFFF' },
  { nilai: 'na', label: 'N/A', bg: '#3A4450', fg: '#FFFFFF' },
];

const kelompok = computed(() => {
  const peta = new Map<string, Butir[]>();
  for (const b of p.butir) peta.set(b.kelompok, [...(peta.get(b.kelompok) ?? []), b]);
  return [...peta.entries()].map(([judul, isi]) => ({ judul, isi, terisi: isi.filter((b) => f.jawab[b.kode]).length }));
});

const hitung = computed(() => {
  const v = p.butir.map((b) => f.jawab[b.kode]);
  const n = (x: Nilai) => v.filter((y) => y === x).length;
  const total = p.butir.length || 1;
  return { selesai: v.filter(Boolean).length, total: p.butir.length, ok: n('ok'), tidak: n('tidak'), na: n('na'), persen: (x: number) => `${(x / total) * 100}%` };
});

const kritisGagal = computed(() => p.butir.filter((b) => b.kritis && f.jawab[b.kode] === 'tidak'));
const sisa = computed(() => hitung.value.total - hitung.value.selesai);
const perluFoto = computed(() => kritisGagal.value.length > 0 && foto.value.length === 0);

const tombol = computed(() => {
  if (sisa.value) return { label: `Lengkapi ${sisa.value} item lagi`, kelas: '' };
  if (kritisGagal.value.length) return { label: perluFoto.value ? 'Tambah foto bukti dulu' : 'Kirim P2H · unit ditahan', kelas: 'bahaya' };
  return { label: 'Kirim P2H · unit laik operasi', kelas: '' };
});

function jawab(kode: string, v: Nilai) {
  f.jawab[kode] = f.jawab[kode] === v ? (undefined as unknown as Nilai) : v;
  if (!f.jawab[kode]) delete f.jawab[kode];
  delete galat.value.jawab;
}

/* ═══════════ foto ═══════════ */
const inputKamera = ref<HTMLInputElement | null>(null);
const inputGaleri = ref<HTMLInputElement | null>(null);

async function tambahFoto(e: Event) {
  const input = e.target as HTMLInputElement;
  const berkas = Array.from(input.files ?? []).slice(0, Math.max(0, 6 - foto.value.length));
  input.value = '';
  for (const b of berkas) {
    if (!b.type.startsWith('image/')) continue;
    const kecil = await perkecilFoto(b);
    foto.value.push({ id: klienId(), ...kecil, url: URL.createObjectURL(kecil.data) });
  }
  if (foto.value.length) delete galat.value.foto;
}

function buangFoto(id: string) {
  const x = foto.value.find((y) => y.id === id);
  if (x) URL.revokeObjectURL(x.url);
  foto.value = foto.value.filter((y) => y.id !== id);
}

/* ═══════════ draf ═══════════ */
let pewaktu: number | undefined;
let siap = false;
const adaIsi = () => Object.keys(f.jawab).length > 0 || foto.value.length > 0 || !!f.catatan.trim();

async function simpanSekarang() {
  if (!uid.value || !adaIsi()) return;
  await simpanDraf(kunciDraf.value, { ...f, jawab: { ...f.jawab }, foto: foto.value.map(({ url: _u, ...x }) => x) });
}

watch([f, foto], () => {
  if (!siap) return;
  window.clearTimeout(pewaktu);
  pewaktu = window.setTimeout(() => void simpanSekarang(), 600);
}, { deep: true });

onMounted(async () => {
  const d = await ambilDraf<any>(kunciDraf.value);
  if (d?.nilai) {
    const { foto: fd, ...isi } = d.nilai;
    Object.assign(f, isi);
    foto.value = (fd ?? []).map((x: any) => ({ ...x, url: URL.createObjectURL(x.data) }));
    kabar(`P2H ${p.unit.kode} dilanjutkan dari draf.`);
  }
  siap = true;
});

onBeforeUnmount(() => {
  window.clearTimeout(pewaktu);
  if (!selesai.value && adaIsi()) void simpanSekarang();
  foto.value.forEach((x) => URL.revokeObjectURL(x.url));
});

/* ═══════════ kirim ═══════════ */

async function kirimP2h() {
  if (mengirim.value) return;

  if (sisa.value) {
    const pertama = p.butir.find((b) => !f.jawab[b.kode]);
    if (pertama) document.getElementById(`butir-${pertama.kode}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    kabar(`Masih ${sisa.value} item belum dijawab.`, true);
    return;
  }
  if (perluFoto.value) {
    galat.value = { foto: 'Butir kritis yang gagal wajib disertai foto bukti.' };
    document.getElementById('foto-p2h')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    kabar(galat.value.foto, true);
    return;
  }
  if (!f.operator.trim()) { galat.value = { operator: 'Nama operator wajib diisi.' }; return; }

  mengirim.value = true;
  const ditahan = kritisGagal.value.length > 0;

  const isian: [string, string][] = [['operator', f.operator.trim()], ['shift', f.shift]];
  if (String(f.hm).trim() !== '') isian.push(['hm', String(f.hm).replace(',', '.')]);
  if (f.catatan.trim()) isian.push(['catatan', f.catatan.trim()]);
  for (const b of p.butir) isian.push([`jawab[${b.kode}]`, f.jawab[b.kode]]);

  const k: Kiriman = {
    klien_id: f.klien_id, jenis: 'p2h', judul: `P2H ${p.unit.kode} · ${ditahan ? 'unit ditahan' : 'laik operasi'}`,
    url: p.tautan.simpan, isian, foto: foto.value.map(({ nama, tipe, data }) => ({ nama, tipe, data })),
    pengguna: uid.value, dibuat: f.dibuat, percobaan: 0, galat: null,
  };

  let diAntre = true;
  try { await simpanKiriman(k); } catch { diAntre = false; }

  try {
    if (!navigator.onLine && diAntre) {
      await hapusDraf(kunciDraf.value);
      selesai.value = { luring: true, ditahan };
      return;
    }

    const h = await kirim(k);
    if (h.status === 'terkirim') {
      if (diAntre) await hapusKiriman(k.klien_id, uid.value);
      await hapusDraf(kunciDraf.value);
      selesai.value = { luring: false, ditahan };
      kabar(ditahan ? `P2H terkirim. ${p.unit.kode} DITAHAN — perintah kerja terbit untuk mekanik.` : `P2H terkirim. ${p.unit.kode} laik operasi.`, ditahan, 6000);
      router.visit(p.tautan.daftar);
      return;
    }
    if (h.status === 'ditolak') {
      if (diAntre) await hapusKiriman(k.klien_id, uid.value);
      galat.value = h.errors;
      kabar(h.sebab, true);
      return;
    }
    if (diAntre) {
      await hapusDraf(kunciDraf.value);
      selesai.value = { luring: true, ditahan };
      return;
    }
    kabar(`${h.sebab} P2H belum terkirim — coba lagi.`, true);
  } finally {
    mengirim.value = false;
  }
}

const hm = computed(() => (p.unit.hm === null || p.unit.hm === '' ? null : Number(p.unit.hm).toLocaleString('id-ID', { maximumFractionDigits: 1 })));
</script>

<template>
  <Head :title="`P2H ${unit.kode}`" />

  <div class="lp-bilah lekat">
    <Link :href="tautan.daftar" class="lp-ikon-tombol" aria-label="Kembali ke daftar unit"><IkonLapangan nama="kiri" :tebal="2.2" /></Link>
    <div class="lp-bilah-judul"><h1 style="margin:0;font-size:17px">P2H pra-operasi</h1><div class="lp-bilah-sub">Pemeriksaan harian sebelum unit bekerja</div></div>
  </div>

  <BilahLuring formulir />

  <div v-if="selesai?.luring" class="selesai lp-muncul">
    <span class="lp-petak besar" :class="selesai.ditahan ? 'nada-bahaya' : 'nada-jingga'"><IkonLapangan :nama="selesai.ditahan ? 'bahaya' : 'luring'" /></span>
    <h2>{{ selesai.ditahan ? `${unit.kode} DITAHAN — jangan dioperasikan` : 'P2H tersimpan di perangkat' }}</h2>
    <p v-if="selesai.ditahan">Butir kritis gagal. P2H beserta fotonya tersimpan di ponsel ini dan terkirim otomatis begitu ada sinyal — saat itu tahanan unit tercatat dan perintah kerja terbit untuk mekanik. Sampai terkirim, beri tahu pengawas lewat radio.</p>
    <p v-else>Belum terkirim karena sinyal tidak ada atau terputus. P2H aman di ponsel ini dan terkirim otomatis begitu tersambung.</p>
    <div class="selesai-aksi">
      <Link :href="tautan.daftar" class="lp-tombol">Kembali ke daftar unit</Link>
    </div>
  </div>

  <template v-else>
    <section class="unit">
      <div class="unit-atas">
        <span class="lp-mono unit-kode">{{ unit.kode }}<template v-if="hm"> · HM {{ hm }}</template></span>
        <span class="shift">
          <select v-model="f.shift" aria-label="Shift"><option v-for="(label, k) in shift.pilihan" :key="k" :value="k">{{ label }}</option></select>
        </span>
      </div>
      <div class="unit-nama">{{ unit.nama }}</div>
      <div class="unit-ket">{{ unit.jenis }}<template v-if="unit.ket"> · {{ unit.ket }}</template> · operator {{ f.operator || '—' }}</div>
      <div class="unit-hitung">
        <span class="unit-n">{{ hitung.selesai }} dari {{ hitung.total }} item</span>
        <span class="legenda">
          <span><i style="background:#22C55E"></i>{{ hitung.ok }} OK</span>
          <span><i style="background:#F87171"></i>{{ hitung.tidak }} tidak</span>
          <span><i style="background:#9AA3AE"></i>{{ hitung.na }} N/A</span>
        </span>
      </div>
      <div class="unit-bar" role="img" :aria-label="`${hitung.selesai} dari ${hitung.total} item terjawab`">
        <span :style="{ width: hitung.persen(hitung.ok), background: '#22C55E' }"></span>
        <span :style="{ width: hitung.persen(hitung.tidak), background: '#F87171' }"></span>
        <span :style="{ width: hitung.persen(hitung.na), background: '#9AA3AE' }"></span>
      </div>
    </section>

    <div v-if="unit.status === 'ditahan'" class="tahan">
      <IkonLapangan nama="bahaya" :tebal="2" />
      <div><strong>Unit sedang ditahan<template v-if="unit.ditahanSejak"> sejak {{ unit.ditahanSejak }}</template></strong>{{ unit.ditahanKarena }}. Unit baru boleh beroperasi setelah dilepas pengawas.</div>
    </div>
    <div v-else-if="unit.sudahShiftIni && unit.terakhir" class="sudah">
      <IkonLapangan nama="centang" :tebal="2.4" />
      <span>Sudah diperiksa {{ unit.terakhir.shift.toLowerCase() }} ini oleh {{ unit.terakhir.operator }}. P2H baru tetap boleh dikirim bila ada pergantian operator.</span>
    </div>

    <section v-for="g in kelompok" :key="g.judul" class="kelompok">
      <div class="lp-mono kelompok-judul"><span>{{ g.judul }}</span><span>{{ g.terisi }}/{{ g.isi.length }}</span></div>
      <div class="lp-kartu kelompok-isi">
        <div v-for="b in g.isi" :id="`butir-${b.kode}`" :key="b.kode" class="p2h-butir" :class="{ belum: galat.jawab && !f.jawab[b.kode] }">
          <div class="p2h-baris">
            <div class="p2h-teks">{{ b.uraian }}<span v-if="b.kritis" class="lp-mono kritis">KRITIS</span></div>
            <div class="p2h-pilih" role="group" :aria-label="b.uraian">
              <button v-for="o in PILIHAN" :key="o.nilai" type="button" :aria-pressed="f.jawab[b.kode] === o.nilai"
                      :style="f.jawab[b.kode] === o.nilai ? { background: o.bg, color: o.fg, borderColor: o.bg } : undefined"
                      @click="jawab(b.kode, o.nilai)">{{ o.label }}</button>
            </div>
          </div>
          <div v-if="b.kritis && f.jawab[b.kode] === 'tidak'" class="p2h-catatan">
            <IkonLapangan nama="bahaya" :tebal="2" />
            <div><strong>Unit akan ditahan · mekanik diberi tahu</strong>Perintah kerja korektif berprioritas kritis terbit saat P2H terkirim. Foto bukti wajib.</div>
          </div>
        </div>
      </div>
    </section>

    <section id="foto-p2h" class="rinci">
      <div class="lp-label"><span>Foto bukti</span><small>{{ kritisGagal.length ? 'wajib' : 'opsional' }} · {{ foto.length }} / 6</small></div>
      <div class="foto">
        <div v-for="x in foto" :key="x.id" class="foto-sel">
          <img :src="x.url" alt="Foto bukti P2H">
          <button type="button" class="foto-buang" aria-label="Buang foto" @click="buangFoto(x.id)"><IkonLapangan nama="tutup" :tebal="3" /></button>
        </div>
        <template v-if="foto.length < 6">
          <button type="button" class="foto-tambah" :class="{ wajib: perluFoto }" @click="inputKamera?.click()"><IkonLapangan nama="kamera" :tebal="1.8" />Kamera</button>
          <button type="button" class="foto-tambah" @click="inputGaleri?.click()"><IkonLapangan nama="galeri" :tebal="1.8" />Galeri</button>
        </template>
      </div>
      <input ref="inputKamera" type="file" accept="image/*" capture="environment" hidden @change="tambahFoto">
      <input ref="inputGaleri" type="file" accept="image/*" multiple hidden @change="tambahFoto">
      <p v-if="galat.foto || galat['foto.0']" class="lp-salah">{{ galat.foto || galat['foto.0'] }}</p>

      <div class="p2h-dua">
        <div>
          <label for="hm" class="lp-label">Hour meter</label>
          <input id="hm" v-model="f.hm" class="lp-masukan lp-mono" inputmode="decimal" :placeholder="hm ?? 'mis. 18442'" autocomplete="off">
          <p v-if="galat.hm" class="lp-salah">{{ galat.hm }}</p>
        </div>
        <div>
          <label for="operator" class="lp-label">Operator</label>
          <input id="operator" v-model="f.operator" class="lp-masukan" maxlength="150" autocomplete="name">
          <p v-if="galat.operator" class="lp-salah">{{ galat.operator }}</p>
        </div>
      </div>

      <label for="catatan" class="lp-label" style="margin-top:14px"><span>Catatan operator</span><small>opsional</small></label>
      <textarea id="catatan" v-model="f.catatan" class="lp-masukan" rows="2" maxlength="2000" placeholder="mis. Berdecit saat retarder aktif di turunan R-04"></textarea>
      <p v-if="galat.jawab" class="lp-salah">{{ galat.jawab }}</p>
    </section>

    <div class="lp-aksi">
      <button type="button" class="lp-tombol" :class="[tombol.kelas, sisa ? 'belum' : '']" :disabled="mengirim" :aria-disabled="sisa > 0" @click="kirimP2h">
        {{ mengirim ? 'Mengirim P2H…' : (!jaringan.daring && !sisa ? tombol.label.replace('Kirim P2H', 'Simpan P2H') : tombol.label) }}
      </button>
    </div>
  </template>
</template>

<style scoped>

.unit { margin: 0 20px; background: var(--ink); border-radius: 16px; padding: 15px 16px; color: #FFFFFF; }
.unit-atas { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.unit-kode { font-size: 12px; color: rgba(255, 255, 255, .72); letter-spacing: .04em; }
.shift select { appearance: none; font: inherit; font-size: 12px; font-weight: 600; color: #FFFFFF; background: rgba(255, 255, 255, .1); border: 0; border-radius: 6px; padding: 6px 10px; min-height: 30px; }
.shift select option { color: var(--ink); }
.unit-nama { font-size: 19px; font-weight: 700; margin-top: 8px; overflow-wrap: anywhere; }
.unit-ket { font-size: 13px; color: rgba(255, 255, 255, .72); margin-top: 2px; }
.unit-hitung { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: 14px; flex-wrap: wrap; }
.unit-n { font-size: 13.5px; font-weight: 600; }
.legenda { display: flex; gap: 12px; font-size: 12px; color: rgba(255, 255, 255, .76); }
.legenda span { display: flex; align-items: center; gap: 5px; }
.legenda i { width: 7px; height: 7px; border-radius: 4px; }
.unit-bar { display: flex; height: 6px; border-radius: 3px; overflow: hidden; background: rgba(255, 255, 255, .12); margin-top: 10px; }
.unit-bar span { transition: width .2s ease; }

.tahan, .sudah { margin: 12px 20px 0; border-radius: 12px; padding: 11px 13px; display: flex; gap: 10px; font-size: 13px; line-height: 1.45; }
.tahan { background: var(--bahaya-lunak); border: 1px solid #F4C4C4; color: #3A4450; }
.tahan svg { width: 18px; height: 18px; color: var(--bahaya-teks); flex: none; margin-top: 1px; }
.tahan strong { display: block; color: #991B1B; font-size: 13.5px; }
.sudah { background: var(--aman-lunak); border: 1px solid #BFE6CB; color: #14532D; }
.sudah svg { width: 16px; height: 16px; flex: none; margin-top: 2px; }

.kelompok { padding: 0 20px; }
.kelompok-judul { font-size: 11.5px; letter-spacing: .06em; color: var(--abu2); margin: 16px 0 8px; display: flex; justify-content: space-between; }
.kelompok-isi { border-radius: 14px; overflow: hidden; }
.p2h-butir { border-top: 1px solid var(--garis3); }
.p2h-butir:first-child { border-top: 0; }
.p2h-butir.belum { background: #FFFBEB; }
.p2h-baris { display: flex; align-items: center; gap: 10px; padding: 10px 10px 10px 14px; }
.p2h-teks { flex: 1; min-width: 0; font-size: 14px; line-height: 1.35; font-weight: 500; color: var(--teks); }
.kritis { display: inline-block; margin-left: 6px; font-size: 10px; font-weight: 600; letter-spacing: .05em; color: var(--bahaya-teks); border: 1px solid #F4C4C4; border-radius: 4px; padding: 1px 4px; vertical-align: 2px; }
.p2h-pilih { display: flex; gap: 4px; flex: none; }
.p2h-pilih button { width: 48px; height: 44px; border-radius: 10px; border: 1px solid var(--garis2); background: #FFFFFF; color: var(--abu1); font-size: 12.5px; font-weight: 700; padding: 0; }
.p2h-catatan { margin: 0 10px 10px 14px; border-radius: 10px; background: #FDECEC; border: 1px solid #F4C4C4; padding: 10px 12px; display: flex; gap: 10px; font-size: 13px; line-height: 1.4; color: var(--abu1); }
.p2h-catatan svg { width: 18px; height: 18px; color: var(--bahaya-teks); flex: none; margin-top: 1px; }
.p2h-catatan strong { display: block; font-size: 12.5px; color: var(--bahaya-teks); }

.rinci { padding: 20px 20px 0; }
.foto { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; margin-top: 8px; }
.foto-sel { position: relative; aspect-ratio: 1; border-radius: 12px; overflow: hidden; background: var(--garis); }
.foto-sel img { width: 100%; height: 100%; object-fit: cover; display: block; }
.foto-buang { position: absolute; right: 2px; top: 2px; width: 32px; height: 32px; border: 0; background: none; display: grid; place-items: center; padding: 0; }
.foto-buang svg { width: 20px; height: 20px; padding: 5px; border-radius: 10px; background: rgba(11, 17, 23, .66); color: #FFFFFF; }
.foto-tambah { aspect-ratio: 1; border-radius: 12px; border: 1.5px dashed #B8C0C9; background: #FFFFFF; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; color: var(--abu1); font-size: 12px; font-weight: 600; }
.foto-tambah svg { width: 22px; height: 22px; }
.foto-tambah.wajib { border-color: var(--bahaya); color: var(--bahaya-teks); background: #FFF7F7; }
.p2h-dua { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr); gap: 10px; margin-top: 14px; }
.lp-masukan { margin-top: 8px; }

.selesai { padding: 28px 24px 0; }
.lp-petak.besar { width: 52px; height: 52px; border-radius: 14px; }
.lp-petak.besar svg { width: 26px; height: 26px; }
.selesai h2 { margin: 16px 0 0; font-size: 24px; font-weight: 700; font-stretch: 88%; letter-spacing: -.01em; color: var(--ink); }
.selesai p { margin: 8px 0 0; font-size: 15px; line-height: 1.55; color: var(--abu1); }
.selesai-aksi { display: flex; flex-direction: column; gap: 10px; margin-top: 22px; }
</style>

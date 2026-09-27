<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import LapanganLayout from '../../Layouts/LapanganLayout.vue';
import IkonLapangan from '../../Components/IkonLapangan.vue';
import BilahLuring from '../../Components/BilahLuring.vue';
import { ambilDraf, hapusDraf, hapusKiriman, jaringan, kirim, klienId, perkecilFoto, simpanDraf, simpanKiriman, type Kiriman } from '../../lapangan/kotakKeluar';
import { kabar } from '../../lapangan/kabar';

defineOptions({ layout: LapanganLayout });

type Pita = { min: number; nama: string; risiko: string; hari: number; tindakan: string };

const p = defineProps<{
  jenis: Record<string, string>;
  matriks: { kemungkinan: Record<string, string>; keparahan: Record<string, string>; pita: Record<string, Pita> };
  perusahaan: { id: number; nama: string }[];
  bawaan: { company_id: number | null };
  maksFoto: number;
  tautan: { simpan: string };
}>();

const halaman = usePage<any>();
const uid = computed(() => Number(halaman.props?.pengguna?.id ?? 0));
const kunciDraf = computed(() => `${uid.value}:lapor`);

type Foto = { id: string; nama: string; tipe: string; data: Blob; url: string };

const f = reactive({
  klien_id: klienId(),
  kategori: Object.keys(p.jenis)[0] ?? '',
  deskripsi: '',
  lokasi: '',
  rekomendasi: '',
  company_id: p.bawaan.company_id ?? p.perusahaan[0]?.id ?? null as number | null,
  kemungkinan: 0,
  keparahan: 0,
  lat: null as number | null,
  lng: null as number | null,
  akurasi: null as number | null,
  dibuat: new Date().toISOString(),
});
const foto = ref<Foto[]>([]);
const galat = ref<Record<string, string>>({});
const mengirim = ref(false);
const tersimpan = ref<null | { luring: boolean }>(null);

/* ═══════════ matriks 5×5 ═══════════ */

const WARNA: Record<string, { lunak: string; teks: string; kuat: string; di: string }> = {
  Rendah: { lunak: '#DDF3E4', teks: '#166534', kuat: '#22C55E', di: '#0B1117' },
  Sedang: { lunak: '#FBF1C7', teks: '#854D0E', kuat: '#FACC15', di: '#0B1117' },
  Tinggi: { lunak: '#FFE4C7', teks: '#9A4A00', kuat: '#F57C00', di: '#0B1117' },
  Ekstrem: { lunak: '#FDDEDE', teks: '#B91C1C', kuat: '#DC2626', di: '#FFFFFF' },
};

const urutPita = computed(() => Object.values(p.matriks.pita).sort((a, b) => b.min - a.min));
const pitaUntuk = (skor: number) => urutPita.value.find((x) => skor >= x.min) ?? urutPita.value[urutPita.value.length - 1];

const sel = computed(() => {
  const out: { k: number; s: number; n: number; w: typeof WARNA.Rendah; pilih: boolean; nama: string }[] = [];
  for (let k = 5; k >= 1; k--) {
    for (let s = 1; s <= 5; s++) {
      const n = k * s;
      const pita = pitaUntuk(n);
      out.push({ k, s, n, w: WARNA[pita.nama] ?? WARNA.Rendah, pilih: f.kemungkinan === k && f.keparahan === s, nama: pita.nama });
    }
  }
  return out;
});

const risiko = computed(() => {
  if (!f.kemungkinan || !f.keparahan) return null;
  const skor = f.kemungkinan * f.keparahan;
  const pita = pitaUntuk(skor);
  const tenggat = new Date();
  tenggat.setDate(tenggat.getDate() + pita.hari);
  return {
    skor, pita, w: WARNA[pita.nama] ?? WARNA.Rendah,
    kombinasi: `${p.matriks.kemungkinan[f.kemungkinan]} (${f.kemungkinan}) × ${p.matriks.keparahan[f.keparahan]} (${f.keparahan})`,
    tenggat: pita.hari === 0 ? 'hari ini' : new Intl.DateTimeFormat('id-ID', { weekday: 'short', day: 'numeric', month: 'short' }).format(tenggat),
  };
});

function pilihSel(k: number, s: number) {
  f.kemungkinan = k;
  f.keparahan = s;
  delete galat.value.kemungkinan;
}

/* ═══════════ foto ═══════════ */

const inputKamera = ref<HTMLInputElement | null>(null);
const inputGaleri = ref<HTMLInputElement | null>(null);
const sisaFoto = computed(() => Math.max(0, p.maksFoto - foto.value.length));

async function tambahFoto(e: Event) {
  const input = e.target as HTMLInputElement;
  const berkas = Array.from(input.files ?? []).slice(0, sisaFoto.value);
  input.value = '';
  for (const b of berkas) {
    if (!b.type.startsWith('image/')) continue;
    const kecil = await perkecilFoto(b);
    foto.value.push({ id: klienId(), ...kecil, url: URL.createObjectURL(kecil.data) });
  }
  if (berkas.length) delete galat.value.foto;
}

function buangFoto(id: string) {
  const x = foto.value.find((y) => y.id === id);
  if (x) URL.revokeObjectURL(x.url);
  foto.value = foto.value.filter((y) => y.id !== id);
}

/* ═══════════ GPS ═══════════ */

const gps = ref<'mencari' | 'ada' | 'tidak' | 'ditolak'>('tidak');

function ambilGps() {
  if (!('geolocation' in navigator)) { gps.value = 'tidak'; return; }
  gps.value = 'mencari';
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      f.lat = Number(pos.coords.latitude.toFixed(7));
      f.lng = Number(pos.coords.longitude.toFixed(7));
      f.akurasi = Math.round(pos.coords.accuracy);
      gps.value = 'ada';
    },
    (e) => { gps.value = e.code === 1 ? 'ditolak' : 'tidak'; },
    { enableHighAccuracy: true, timeout: 20000, maximumAge: 60000 },
  );
}

const koordinat = computed(() => {
  if (f.lat === null || f.lng === null) return null;
  const angka = (x: number) => x.toFixed(5).replace('-', '−');
  return `${angka(f.lat)}, ${angka(f.lng)}${f.akurasi !== null ? ` · ±${f.akurasi} m` : ''}`;
});

/* ═══════════ draf ═══════════ */

type IsiDraf = Omit<typeof f, never> & { foto: Omit<Foto, 'url'>[] };
let pewaktuDraf: number | undefined;
let siap = false;

function isiDraf(): IsiDraf {
  return { ...f, foto: foto.value.map(({ url: _u, ...x }) => x) };
}

function adaIsi(): boolean {
  return Boolean(f.deskripsi.trim() || f.lokasi.trim() || f.rekomendasi.trim() || foto.value.length || f.kemungkinan);
}

async function simpanSekarang(tampil = false) {
  if (!uid.value) return;
  if (!adaIsi()) { if (tampil) kabar('Belum ada yang perlu disimpan.'); return; }
  await simpanDraf(kunciDraf.value, isiDraf());
  if (tampil) kabar('Draf tersimpan di perangkat.');
}

watch([f, foto], () => {
  if (!siap) return;
  window.clearTimeout(pewaktuDraf);
  pewaktuDraf = window.setTimeout(() => void simpanSekarang(), 700);
}, { deep: true });

onMounted(async () => {
  const d = await ambilDraf<IsiDraf>(kunciDraf.value);
  if (d?.nilai) {
    const { foto: fd, ...isi } = d.nilai;
    Object.assign(f, isi);
    foto.value = (fd ?? []).map((x) => ({ ...x, url: URL.createObjectURL(x.data) }));
    kabar('Draf sebelumnya dipulihkan.');
  }
  siap = true;
  if (f.lat === null) ambilGps();
});

onBeforeUnmount(() => {
  window.clearTimeout(pewaktuDraf);
  if (!tersimpan.value && adaIsi()) void simpanSekarang();
  foto.value.forEach((x) => URL.revokeObjectURL(x.url));
});

/* ═══════════ kirim ═══════════ */

function periksa(): boolean {
  const g: Record<string, string> = {};
  if (!f.kategori) g.kategori = 'Pilih jenis temuan.';
  if (!f.deskripsi.trim()) g.deskripsi = 'Tulis apa yang Anda lihat.';
  if (!f.kemungkinan || !f.keparahan) g.kemungkinan = 'Ketuk satu sel matriks risiko.';
  if (p.perusahaan.length > 1 && !f.company_id) g.company_id = 'Pilih perusahaan tujuan.';
  galat.value = g;
  if (Object.keys(g).length) {
    kabar(Object.values(g)[0], true);
    document.querySelector(`[data-medan="${Object.keys(g)[0]}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return false;
  }
  return true;
}

function susunKiriman(): Kiriman {
  const isian: [string, string][] = [
    ['kategori', f.kategori],
    ['deskripsi', f.deskripsi.trim()],
    ['kemungkinan', String(f.kemungkinan)],
    ['keparahan', String(f.keparahan)],
  ];
  if (f.lokasi.trim()) isian.push(['lokasi', f.lokasi.trim()]);
  if (f.rekomendasi.trim()) isian.push(['rekomendasi', f.rekomendasi.trim()]);
  if (f.company_id) isian.push(['company_id', String(f.company_id)]);
  if (f.lat !== null && f.lng !== null) {
    isian.push(['lat', String(f.lat)], ['lng', String(f.lng)]);
    if (f.akurasi !== null) isian.push(['akurasi_m', String(Math.min(65000, f.akurasi))]);
  }

  return {
    klien_id: f.klien_id,
    jenis: 'lapor',
    judul: `${p.jenis[f.kategori] ?? 'Laporan bahaya'} · ${f.lokasi.trim() || f.deskripsi.trim().slice(0, 40)}`,
    url: p.tautan.simpan,
    isian,
    foto: foto.value.map(({ nama, tipe, data }) => ({ nama, tipe, data })),
    pengguna: uid.value,
    dibuat: f.dibuat,
    percobaan: 0,
    galat: null,
  };
}

async function kirimLaporan() {
  if (mengirim.value || !periksa()) return;
  mengirim.value = true;

  const k = susunKiriman();
  let diAntre = true;
  try { await simpanKiriman(k); } catch { diAntre = false; }

  try {
    if (!navigator.onLine && diAntre) return selesaiLuring();

    const h = await kirim(k);

    if (h.status === 'terkirim') {
      if (diAntre) await hapusKiriman(k.klien_id, uid.value);
      await hapusDraf(kunciDraf.value);
      tersimpan.value = { luring: false };
      kabar(`Laporan ${h.data?.kode ?? ''} terkirim.`.replace('  ', ' '));
      router.visit(h.url ?? '/lapangan/tugas');
      return;
    }

    if (h.status === 'ditolak') {
      if (diAntre) await hapusKiriman(k.klien_id, uid.value);
      galat.value = h.errors;
      kabar(h.sebab, true);
      return;
    }

    if (diAntre) return selesaiLuring(h.sebab);
    kabar(`${h.sebab} Laporan belum terkirim — coba lagi.`, true);
  } finally {
    mengirim.value = false;
  }
}

async function selesaiLuring(sebab?: string) {
  await hapusDraf(kunciDraf.value);
  tersimpan.value = { luring: true };
  kabar(sebab && jaringan.daring ? `${sebab} Laporan disimpan dan dikirim ulang otomatis.` : 'Laporan disimpan di perangkat — terkirim otomatis saat sinyal kembali.');
}

function laporLagi() {
  foto.value.forEach((x) => URL.revokeObjectURL(x.url));
  foto.value = [];
  Object.assign(f, {
    klien_id: klienId(), deskripsi: '', lokasi: '', rekomendasi: '', kemungkinan: 0, keparahan: 0,
    dibuat: new Date().toISOString(),
  });
  galat.value = {};
  tersimpan.value = null;
  ambilGps();
}

function tutup() {
  if (adaIsi()) void simpanSekarang(true);
  router.visit('/lapangan');
}
</script>

<template>
  <Head title="Lapor bahaya" />

  <div class="lp-bilah lekat">
    <button type="button" class="lp-ikon-tombol" aria-label="Tutup" @click="tutup"><IkonLapangan nama="tutup" :tebal="2" /></button>
    <h1 class="lp-bilah-judul" style="text-align:center;margin:0">Lapor bahaya</h1>
    <button type="button" class="lp-teks-tombol" :disabled="!!tersimpan" @click="simpanSekarang(true)">Simpan draf</button>
  </div>

  <BilahLuring formulir />

  <!-- Sesudah tersimpan tanpa sinyal: formulirnya diganti ringkasan,
       supaya tidak ada yang mengirim laporan yang sama dua kali. -->
  <div v-if="tersimpan?.luring" class="selesai lp-muncul">
    <span class="lp-petak nada-jingga besar"><IkonLapangan nama="luring" /></span>
    <h2>Laporan tersimpan di perangkat</h2>
    <p>Belum terkirim karena sinyal tidak ada atau terputus. Laporan beserta fotonya aman di ponsel ini dan terkirim otomatis begitu tersambung — Anda tidak perlu mengirim ulang.</p>
    <div class="selesai-aksi">
      <button type="button" class="lp-tombol" @click="laporLagi"><IkonLapangan nama="tambah" :tebal="2.2" />Lapor temuan lain</button>
      <Link href="/lapangan/tugas" class="lp-tombol garis">Lihat antrean kiriman</Link>
    </div>
  </div>

  <form v-else class="isi" novalidate @submit.prevent="kirimLaporan">
    <!-- Foto -->
    <section data-medan="foto">
      <div class="lp-label"><span>Foto</span><small>{{ foto.length }} / {{ maksFoto }}</small></div>
      <div class="foto">
        <div v-for="x in foto" :key="x.id" class="foto-sel">
          <img :src="x.url" alt="Foto temuan">
          <button type="button" class="foto-buang" aria-label="Buang foto" @click="buangFoto(x.id)"><IkonLapangan nama="tutup" :tebal="3" /></button>
        </div>
        <template v-if="sisaFoto > 0">
          <button type="button" class="foto-tambah" @click="inputKamera?.click()"><IkonLapangan nama="kamera" :tebal="1.8" />Kamera</button>
          <button type="button" class="foto-tambah" @click="inputGaleri?.click()"><IkonLapangan nama="galeri" :tebal="1.8" />Galeri</button>
        </template>
      </div>
      <input ref="inputKamera" type="file" accept="image/*" capture="environment" hidden @change="tambahFoto">
      <input ref="inputGaleri" type="file" accept="image/*" multiple hidden @change="tambahFoto">
      <p v-if="galat.foto || galat['foto.0']" class="lp-salah">{{ galat.foto || galat['foto.0'] }}</p>
    </section>

    <!-- Lokasi -->
    <section class="lokasi lp-kartu" data-medan="lokasi">
      <span class="lp-petak nada-jingga"><IkonLapangan nama="pin" :tebal="2" /></span>
      <div class="lokasi-isi">
        <label for="lokasi" class="sr-only">Nama lokasi</label>
        <input id="lokasi" v-model="f.lokasi" class="lokasi-nama" maxlength="200" placeholder="Nama lokasi (mis. R-04 KM 2,3)" autocomplete="off">
        <div class="lp-mono lokasi-gps">
          <template v-if="gps === 'mencari'">Mencari lokasi GPS…</template>
          <template v-else-if="koordinat">{{ koordinat }}</template>
          <template v-else-if="gps === 'ditolak'">Izin lokasi ditolak — tulis lokasinya</template>
          <template v-else>GPS tidak tersedia — tulis lokasinya</template>
        </div>
      </div>
      <button type="button" class="lp-teks-tombol" :disabled="gps === 'mencari'" @click="ambilGps">{{ koordinat ? 'Perbarui' : 'Ambil GPS' }}</button>
    </section>
    <p v-if="galat.lokasi || galat.lat" class="lp-salah">{{ galat.lokasi || galat.lat }}</p>

    <!-- Jenis temuan -->
    <section data-medan="kategori">
      <div class="lp-label" id="label-jenis">Jenis temuan</div>
      <div class="lp-geser chip" role="group" aria-labelledby="label-jenis">
        <button v-for="(label, kunci) in jenis" :key="kunci" type="button" class="lp-chip"
                :aria-pressed="f.kategori === kunci" @click="f.kategori = String(kunci)">
          <IkonLapangan v-if="f.kategori === kunci" nama="centang" :tebal="3" />{{ label }}
        </button>
      </div>
      <p v-if="galat.kategori" class="lp-salah">{{ galat.kategori }}</p>
    </section>

    <!-- Uraian -->
    <section data-medan="deskripsi">
      <label for="deskripsi" class="lp-label">Apa yang Anda lihat?</label>
      <textarea id="deskripsi" v-model="f.deskripsi" class="lp-masukan" :class="{ salah: galat.deskripsi }" maxlength="3000" rows="3"
                placeholder="mis. Tanggul pengaman tergerus di tikungan, tinggi tersisa ± 40 cm"></textarea>
      <p v-if="galat.deskripsi" class="lp-salah">{{ galat.deskripsi }}</p>
    </section>

    <!-- Matriks risiko -->
    <section data-medan="kemungkinan">
      <div class="lp-label">
        <span>Penilaian risiko</span>
        <span v-if="risiko" class="pita" :style="{ background: risiko.w.kuat, color: risiko.w.di }">{{ risiko.pita.nama }} · {{ risiko.skor }}</span>
        <span v-else class="pita kosong">Belum dinilai</span>
      </div>
      <div class="matriks">
        <div class="sumbu-k"><span class="lp-mono">KEMUNGKINAN →</span></div>
        <div class="matriks-isi">
          <div class="kisi" role="grid" aria-label="Matriks risiko 5 kali 5">
            <button v-for="c in sel" :key="`${c.k}-${c.s}`" type="button" class="lp-mono"
                    :aria-pressed="c.pilih"
                    :aria-label="`${matriks.kemungkinan[c.k]} × ${matriks.keparahan[c.s]}: skor ${c.n}, ${c.nama}`"
                    :style="{ background: c.pilih ? c.w.kuat : c.w.lunak, color: c.pilih ? c.w.di : c.w.teks, fontWeight: c.pilih ? 700 : 500,
                              boxShadow: c.pilih ? '0 0 0 2px #0B1117, 0 6px 14px -6px rgba(11,17,23,.55)' : 'none' }"
                    @click="pilihSel(c.k, c.s)">{{ c.n }}</button>
          </div>
          <div class="sumbu-s lp-mono"><span v-for="(nama, i) in matriks.keparahan" :key="i">{{ nama }}</span></div>
        </div>
      </div>
      <div class="risiko-ket">
        <template v-if="risiko">
          <div class="risiko-judul">{{ risiko.kombinasi }}</div>
          <div class="risiko-teks">{{ risiko.pita.tindakan }}</div>
          <div class="lp-mono risiko-tenggat">Tenggat tindak lanjut: {{ risiko.tenggat }}</div>
        </template>
        <template v-else>
          <div class="risiko-judul">Ketuk satu sel</div>
          <div class="risiko-teks">Baris = seberapa mungkin terjadi, kolom = seberapa parah akibatnya. Tingkat risiko dan tenggat tindak lanjut terisi sendiri.</div>
        </template>
      </div>
      <p v-if="galat.kemungkinan || galat.keparahan" class="lp-salah">{{ galat.kemungkinan || galat.keparahan }}</p>
    </section>

    <!-- Saran -->
    <section>
      <label for="rekomendasi" class="lp-label"><span>Saran pengendalian</span><small>opsional</small></label>
      <textarea id="rekomendasi" v-model="f.rekomendasi" class="lp-masukan" maxlength="2000" rows="2"
                placeholder="mis. Pasang cone dan rambu, alihkan lalu lintas ke lajur kiri"></textarea>
    </section>

    <section v-if="perusahaan.length > 1" data-medan="company_id">
      <label for="perusahaan" class="lp-label">Perusahaan tujuan</label>
      <select id="perusahaan" v-model.number="f.company_id" class="lp-masukan">
        <option v-for="c in perusahaan" :key="c.id" :value="c.id">{{ c.nama }}</option>
      </select>
      <p v-if="galat.company_id" class="lp-salah">{{ galat.company_id }}</p>
    </section>

    <div class="lp-aksi">
      <button type="submit" class="lp-tombol" :disabled="mengirim">
        <template v-if="mengirim">Mengirim laporan…</template>
        <template v-else>{{ jaringan.daring ? 'Kirim laporan' : 'Simpan & kirim nanti' }}<IkonLapangan nama="panahKanan" :tebal="2.2" /></template>
      </button>
    </div>
  </form>
</template>

<style scoped>
.isi { padding: 8px 20px 0; display: flex; flex-direction: column; gap: 18px; }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

.foto { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; margin-top: 8px; }
.foto-sel { position: relative; aspect-ratio: 1; border-radius: 12px; overflow: hidden; background: var(--garis); }
.foto-sel img { width: 100%; height: 100%; object-fit: cover; display: block; }
.foto-buang { position: absolute; right: 2px; top: 2px; width: 32px; height: 32px; border: 0; background: none; display: grid; place-items: center; padding: 0; }
.foto-buang svg { width: 20px; height: 20px; padding: 5px; border-radius: 10px; background: rgba(11, 17, 23, .66); color: #FFFFFF; }
.foto-tambah { aspect-ratio: 1; border-radius: 12px; border: 1.5px dashed #B8C0C9; background: #FFFFFF; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; color: var(--abu1); font-size: 12px; font-weight: 600; }
.foto-tambah svg { width: 22px; height: 22px; }

.lokasi { display: flex; align-items: center; gap: 12px; padding: 8px 4px 8px 14px; border-radius: 12px; }
.lokasi .lp-petak { width: 36px; height: 36px; }
.lokasi .lp-petak svg { width: 18px; height: 18px; }
.lokasi-isi { flex: 1; min-width: 0; }
.lokasi-nama { width: 100%; border: 0; padding: 6px 0 2px; font: inherit; font-size: 14.5px; font-weight: 600; color: var(--ink); background: none; min-height: 34px; }
.lokasi-nama::placeholder { font-weight: 500; color: var(--abu3); }
.lokasi-nama:focus { outline: none; box-shadow: inset 0 -2px 0 var(--sinyal); }
.lokasi-gps { font-size: 11.5px; color: var(--abu3); margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.chip { margin-top: 8px; margin-right: -20px; }
.lp-masukan { margin-top: 8px; }

.pita { white-space: nowrap; font-family: 'Archivo', sans-serif; font-size: 12.5px; font-weight: 700; padding: 5px 10px; border-radius: 8px; }
.pita.kosong { background: var(--garis3); color: var(--abu2); }

.matriks { display: flex; gap: 6px; margin-top: 10px; }
.sumbu-k { width: 16px; display: flex; align-items: center; justify-content: center; padding-bottom: 20px; }
.sumbu-k span { writing-mode: vertical-rl; transform: rotate(180deg); font-size: 10.5px; letter-spacing: .06em; color: var(--abu3); white-space: nowrap; }
.matriks-isi { flex: 1; min-width: 0; }
.kisi { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 4px; }
.kisi button { height: 44px; border: 0; border-radius: 8px; font-size: 12.5px; padding: 0; transition: box-shadow .12s ease; }
.sumbu-s { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 4px; margin-top: 6px; font-size: 10px; color: var(--abu3); text-align: center; }
.sumbu-s span { overflow: hidden; text-overflow: ellipsis; }

.risiko-ket { margin-top: 12px; background: var(--kertas); border-radius: 12px; padding: 11px 14px; }
.risiko-judul { font-size: 13.5px; font-weight: 600; color: var(--ink); }
.risiko-teks { font-size: 13px; line-height: 1.4; color: var(--abu2); margin-top: 2px; }
.risiko-tenggat { font-size: 11.5px; color: var(--jingga); font-weight: 600; margin-top: 6px; }

.selesai { padding: 28px 24px 0; }
.lp-petak.besar { width: 52px; height: 52px; border-radius: 14px; }
.lp-petak.besar svg { width: 26px; height: 26px; }
.selesai h2 { margin: 16px 0 0; font-size: 24px; font-weight: 700; font-stretch: 88%; letter-spacing: -.01em; color: var(--ink); }
.selesai p { margin: 8px 0 0; font-size: 15px; line-height: 1.55; color: var(--abu1); }
.selesai-aksi { display: flex; flex-direction: column; gap: 10px; margin-top: 22px; }
</style>

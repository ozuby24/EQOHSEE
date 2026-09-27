<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import LapanganLayout from '../../Layouts/LapanganLayout.vue';
import IkonLapangan from '../../Components/IkonLapangan.vue';
import BilahLuring from '../../Components/BilahLuring.vue';
import { jaringan, perkecilFoto } from '../../lapangan/kotakKeluar';
import { kabar } from '../../lapangan/kabar';

defineOptions({ layout: LapanganLayout });

const p = defineProps<{
  r: {
    id: number; kode: string; judul: string; lokasi: string | null; kategori: string; risiko: string; pita: string; skor: number | null;
    status: string; tanggal: string; batas: string | null; sisaHari: number | null;
    deskripsi: string; rekomendasi: string | null; perusahaan: string | null; pelapor: string | null; jabatan: string | null;
    koordinat: { lat: number; lng: number; akurasi: number | null } | null;
    kemungkinan: number | null; keparahan: number | null;
    foto: string[]; fotoTindak: string[]; catatan: string | null;
  };
  linimasa: { judul: string; waktu: string | null; ket: string; tahap: 'lewat' | 'kini' | 'nanti' }[];
  tautan: { tindak: string; lengkap: string };
}>();

const STATUS: Record<string, { label: string; nada: string }> = {
  Open: { label: 'Menunggu tindak lanjut', nada: 'nada-waspada' },
  'In Progress': { label: 'Dalam perbaikan', nada: 'nada-biru' },
  Closed: { label: 'Ditutup', nada: 'nada-aman' },
};
const PITA: Record<string, string> = { Ekstrem: 'kuat-bahaya', Tinggi: 'kuat-jingga', Sedang: 'kuat-waspada', Rendah: 'kuat-aman' };

const semuaFoto = computed(() => [...p.r.foto, ...p.r.fotoTindak]);
const ke = ref(0);
const galeri = ref<HTMLElement | null>(null);
function gulirFoto() {
  const g = galeri.value;
  if (g) ke.value = Math.round(g.scrollLeft / Math.max(1, g.clientWidth));
}

const tenggat = computed(() => {
  const s = p.r.sisaHari;
  if (p.r.status === 'Closed') return { teks: 'Sudah ditutup', nada: '' };
  if (s === null) return { teks: 'Tanpa tenggat', nada: '' };
  if (s < 0) return { teks: `lewat ${Math.abs(s)} hari`, nada: 'bahaya' };
  if (s === 0) return { teks: 'hari ini', nada: 'bahaya' };
  return { teks: `sisa ${s} hari`, nada: s <= 1 ? 'bahaya' : '' };
});

const peta = computed(() => (p.r.koordinat ? `https://www.google.com/maps?q=${p.r.koordinat.lat},${p.r.koordinat.lng}` : null));

/* ═══════════ tindak lanjut ═══════════ */
const lembar = ref(false);
const pratinjau = ref<string[]>([]);
const inputFoto = ref<HTMLInputElement | null>(null);
const form = useForm({
  status: p.r.status === 'Open' ? 'In Progress' : p.r.status,
  catatan_penutupan: p.r.catatan ?? '',
  foto_tindaklanjut: [] as File[],
});

function bukaLembar(ambilFoto = false) {
  form.clearErrors();
  lembar.value = true;
  if (ambilFoto) setTimeout(() => inputFoto.value?.click(), 60);
}

async function tambahFoto(e: Event) {
  const input = e.target as HTMLInputElement;
  const berkas = Array.from(input.files ?? []).slice(0, 6 - form.foto_tindaklanjut.length);
  input.value = '';
  for (const b of berkas) {
    const k = await perkecilFoto(b);
    const file = new File([k.data], k.nama, { type: k.tipe });
    form.foto_tindaklanjut.push(file);
    pratinjau.value.push(URL.createObjectURL(file));
  }
}

function buangFoto(i: number) {
  URL.revokeObjectURL(pratinjau.value[i]);
  pratinjau.value.splice(i, 1);
  form.foto_tindaklanjut.splice(i, 1);
}

function simpan() {
  if (!jaringan.daring) { kabar('Tindak lanjut perlu sinyal — coba lagi saat tersambung.', true); return; }
  if (form.status === 'Closed' && !form.catatan_penutupan.trim()) {
    form.setError('catatan_penutupan', 'Tulis hasil perbaikannya sebelum menutup laporan.');
    return;
  }
  form.post(p.tautan.tindak, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      lembar.value = false;
      pratinjau.value.forEach((u) => URL.revokeObjectURL(u));
      pratinjau.value = [];
      form.foto_tindaklanjut = [];
    },
  });
}
</script>

<template>
  <Head :title="r.kode" />

  <div class="sampul" :class="{ tanpa: !semuaFoto.length }">
    <div v-if="semuaFoto.length" ref="galeri" class="galeri" @scroll.passive="gulirFoto">
      <a v-for="(u, i) in semuaFoto" :key="u" :href="u" target="_blank" rel="noopener" class="galeri-sel">
        <img :src="u" :alt="i < r.foto.length ? `Foto temuan ${i + 1}` : 'Foto bukti perbaikan'" loading="lazy">
      </a>
    </div>
    <div class="sampul-atas">
      <Link href="/lapangan/tugas" class="bulat" aria-label="Kembali"><IkonLapangan nama="kiri" :tebal="2.2" /></Link>
      <a :href="tautan.lengkap" class="bulat" aria-label="Buka versi lengkap di web"><IkonLapangan nama="layar" :tebal="2" /></a>
    </div>
    <span v-if="semuaFoto.length > 1" class="lp-mono hitung-foto">{{ ke + 1 }} / {{ semuaFoto.length }}</span>
  </div>

  <article class="lembar-isi">
    <BilahLuring />
    <div class="label-atas">
      <span class="lp-mono kode">{{ r.kode }}</span>
      <span class="lp-pil" :class="PITA[r.pita] ?? ''">{{ r.pita }}<template v-if="r.skor"> · {{ r.skor }}</template></span>
      <span class="lp-pil" :class="STATUS[r.status]?.nada">{{ STATUS[r.status]?.label ?? r.status }}</span>
    </div>
    <h1>{{ r.judul }}</h1>

    <div class="rinci">
      <div>
        <div class="lp-mono rinci-k">PELAPOR</div>
        <div class="rinci-n">{{ r.pelapor || '—' }}</div>
        <div class="rinci-s">{{ r.jabatan || r.perusahaan || '' }}</div>
      </div>
      <div>
        <div class="lp-mono rinci-k">TENGGAT</div>
        <div class="rinci-n">{{ r.batas || '—' }}</div>
        <div class="rinci-s" :class="{ bahaya: tenggat.nada === 'bahaya' }">{{ tenggat.teks }}</div>
      </div>
      <div>
        <div class="lp-mono rinci-k">JENIS</div>
        <div class="rinci-n">{{ r.kategori }}</div>
        <div class="rinci-s">{{ r.tanggal }}</div>
      </div>
      <div>
        <div class="lp-mono rinci-k">LOKASI</div>
        <div class="rinci-n">{{ r.lokasi || '—' }}</div>
        <a v-if="peta" :href="peta" target="_blank" rel="noopener" class="rinci-s tautan lp-mono">{{ r.koordinat!.lat.toFixed(5) }}, {{ r.koordinat!.lng.toFixed(5) }}</a>
      </div>
    </div>

    <p class="uraian">{{ r.deskripsi }}</p>
    <p v-if="r.rekomendasi" class="saran"><strong>Saran pengendalian</strong>{{ r.rekomendasi }}</p>

    <h2>Tindak lanjut</h2>
    <ol class="linimasa">
      <li v-for="(t, i) in linimasa" :key="i" :class="t.tahap">
        <div class="ln-garis">
          <span class="titik">
            <IkonLapangan v-if="t.tahap === 'lewat'" nama="centang" :tebal="3.2" />
            <i v-else-if="t.tahap === 'kini'"></i>
          </span>
          <span v-if="i < linimasa.length - 1" class="ln-sambung"></span>
        </div>
        <div class="linimasa-isi">
          <div class="linimasa-atas"><span class="linimasa-judul">{{ t.judul }}</span><span class="lp-mono linimasa-waktu">{{ t.waktu ?? '—' }}</span></div>
          <div class="linimasa-ket">{{ t.ket }}</div>
        </div>
      </li>
    </ol>
  </article>

  <div class="lp-aksi">
    <a :href="tautan.lengkap" class="lp-tombol garis" style="flex:1"><IkonLapangan nama="layar" :tebal="1.9" />Versi web</a>
    <button v-if="r.status !== 'Closed'" type="button" class="lp-tombol" style="flex:1.8" @click="bukaLembar(true)">
      <IkonLapangan nama="kamera" :tebal="2" />Unggah bukti
    </button>
    <button v-else type="button" class="lp-tombol garis" style="flex:1.4" @click="bukaLembar(false)">Buka kembali</button>
  </div>

  <!-- Lembar tindak lanjut: status, catatan, foto bukti. -->
  <div v-if="lembar" class="tirai" @click.self="lembar = false">
    <form class="lembar lp-muncul" role="dialog" aria-modal="true" aria-labelledby="judul-tindak" @submit.prevent="simpan">
      <div class="lembar-kepala">
        <h2 id="judul-tindak">Tindak lanjut {{ r.kode }}</h2>
        <button type="button" class="lp-ikon-tombol" aria-label="Tutup" @click="lembar = false"><IkonLapangan nama="tutup" :tebal="2" /></button>
      </div>

      <div class="lp-segmen tiga" role="group" aria-label="Status">
        <button type="button" :aria-pressed="form.status === 'Open'" @click="form.status = 'Open'">Menunggu</button>
        <button type="button" :aria-pressed="form.status === 'In Progress'" @click="form.status = 'In Progress'">Diperbaiki</button>
        <button type="button" :aria-pressed="form.status === 'Closed'" @click="form.status = 'Closed'">Ditutup</button>
      </div>

      <label for="catatan" class="lp-label" style="margin-top:14px">{{ form.status === 'Closed' ? 'Hasil perbaikan' : 'Catatan' }}</label>
      <textarea id="catatan" v-model="form.catatan_penutupan" class="lp-masukan" rows="3" maxlength="2000"
                placeholder="mis. Tanggul ditimbun ulang ≥ ½ diameter roda HD785"></textarea>
      <p v-if="form.errors.catatan_penutupan" class="lp-salah">{{ form.errors.catatan_penutupan }}</p>

      <div class="lp-label" style="margin-top:14px"><span>Foto bukti</span><small>{{ form.foto_tindaklanjut.length }} / 6</small></div>
      <div class="foto">
        <div v-for="(u, i) in pratinjau" :key="u" class="foto-sel">
          <img :src="u" alt="Foto bukti">
          <button type="button" class="foto-buang" aria-label="Buang foto" @click="buangFoto(i)"><IkonLapangan nama="tutup" :tebal="3" /></button>
        </div>
        <button v-if="form.foto_tindaklanjut.length < 6" type="button" class="foto-tambah" @click="inputFoto?.click()"><IkonLapangan nama="kamera" :tebal="1.8" />Tambah</button>
      </div>
      <input ref="inputFoto" type="file" accept="image/*" capture="environment" hidden @change="tambahFoto">
      <p v-if="form.errors['foto_tindaklanjut.0'] || form.errors.status" class="lp-salah">{{ form.errors['foto_tindaklanjut.0'] || form.errors.status }}</p>

      <button type="submit" class="lp-tombol" style="margin-top:18px" :disabled="form.processing">
        {{ form.processing ? 'Menyimpan…' : (jaringan.daring ? 'Simpan tindak lanjut' : 'Perlu sinyal untuk menyimpan') }}
      </button>
    </form>
  </div>
</template>

<style scoped>
.sampul { position: relative; height: 258px; flex: none; background: #1E2835; overflow: hidden; }
.sampul.tanpa { height: calc(env(safe-area-inset-top, 0px) + 84px); background: var(--ink); }
.galeri { display: flex; height: 100%; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; }
.galeri::-webkit-scrollbar { display: none; }
.galeri-sel { flex: none; width: 100%; height: 100%; scroll-snap-align: start; }
.galeri-sel img { width: 100%; height: 100%; object-fit: cover; display: block; }
.sampul-atas { position: absolute; top: calc(env(safe-area-inset-top, 0px) + 14px); left: 16px; right: 16px; display: flex; justify-content: space-between; z-index: 5; }
.bulat { width: 44px; height: 44px; border-radius: 22px; background: rgba(11, 17, 23, .62); border: 1px solid rgba(255, 255, 255, .22); display: grid; place-items: center; color: #FFFFFF; }
.bulat svg { width: 20px; height: 20px; }
.hitung-foto { position: absolute; right: 16px; bottom: 32px; font-size: 11.5px; color: #FFFFFF; background: rgba(11, 17, 23, .64); padding: 4px 8px; border-radius: 6px; }

.lembar-isi { position: relative; margin-top: -20px; background: #FFFFFF; border-radius: 22px 22px 0 0; padding: 20px 20px 8px; flex: 1; }
.lembar-isi :deep(.lp-luring) { margin: 0 0 12px; }
.label-atas { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.kode { font-size: 12px; color: var(--abu2); }
h1 { font-size: 21px; line-height: 1.22; font-weight: 700; letter-spacing: -.01em; color: var(--ink); margin: 10px 0 0; text-wrap: pretty; overflow-wrap: anywhere; }
.rinci { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 16px; margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--garis); }
.rinci-k { font-size: 10.5px; letter-spacing: .05em; color: var(--abu3); }
.rinci-n { font-size: 14px; font-weight: 600; color: var(--ink); margin-top: 3px; overflow-wrap: anywhere; }
.rinci-s { font-size: 12.5px; color: var(--abu2); display: block; }
.rinci-s.bahaya { color: var(--bahaya-teks); font-weight: 600; }
.rinci-s.tautan { color: var(--jingga); font-size: 11.5px; min-height: 24px; display: inline-flex; align-items: center; }
.uraian { font-size: 15px; line-height: 1.55; color: var(--teks); margin: 16px 0 0; white-space: pre-line; }
.saran { font-size: 14px; line-height: 1.5; color: var(--abu1); margin: 10px 0 0; background: var(--kertas); border-radius: 12px; padding: 10px 14px; }
.saran strong { display: block; font-size: 12.5px; color: var(--ink); }
h2 { font-size: 15px; font-weight: 700; color: var(--ink); margin: 22px 0 0; }

.linimasa { list-style: none; margin: 12px 0 0; padding: 0; }
.linimasa li { display: flex; gap: 12px; }
.ln-garis { display: flex; flex-direction: column; align-items: center; flex: none; width: 22px; }
.titik { width: 22px; height: 22px; border-radius: 11px; display: grid; place-items: center; flex: none; background: var(--ink); color: #FFFFFF; }
.titik svg { width: 12px; height: 12px; }
.kini .titik { background: var(--jingga-lunak); border: 2px solid var(--sinyal); }
.kini .titik i { width: 8px; height: 8px; border-radius: 4px; background: var(--sinyal); }
.nanti .titik { background: #FFFFFF; border: 2px solid var(--garis2); }
.ln-sambung { flex: 1; width: 2px; background: var(--ink); margin: 2px 0; min-height: 12px; }
.kini .ln-sambung, .nanti .ln-sambung { width: 0; background: none; border-left: 2px dashed var(--garis2); }
.linimasa-isi { flex: 1; min-width: 0; padding-bottom: 14px; }
.linimasa-atas { display: flex; justify-content: space-between; gap: 8px; }
.linimasa-judul { font-size: 14.5px; font-weight: 600; color: var(--ink); }
.kini .linimasa-judul { font-weight: 700; }
.nanti .linimasa-judul { color: var(--abu2); }
.linimasa-waktu { font-size: 12px; color: var(--abu3); white-space: nowrap; }
.kini .linimasa-waktu { color: var(--jingga); font-weight: 600; }
.linimasa-ket { font-size: 13px; line-height: 1.45; color: var(--abu2); margin-top: 2px; }
.nanti .linimasa-ket { color: var(--abu3); }

.tirai { position: fixed; inset: 0; background: rgba(11, 17, 23, .5); z-index: 70; display: flex; align-items: flex-end; justify-content: center; }
.lembar { width: 100%; max-width: 480px; max-height: 92dvh; overflow-y: auto; background: #FFFFFF; border-radius: 22px 22px 0 0; padding: 8px 20px calc(20px + env(safe-area-inset-bottom, 0px)); }
.lembar-kepala { display: flex; align-items: center; justify-content: space-between; }
.lembar-kepala h2 { margin: 0; }
.lp-segmen.tiga { grid-template-columns: repeat(3, minmax(0, 1fr)); margin-top: 8px; }
.lp-masukan { margin-top: 8px; }
.foto { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; margin-top: 8px; }
.foto-sel { position: relative; aspect-ratio: 1; border-radius: 12px; overflow: hidden; background: var(--garis); }
.foto-sel img { width: 100%; height: 100%; object-fit: cover; display: block; }
.foto-buang { position: absolute; right: 2px; top: 2px; width: 32px; height: 32px; border: 0; background: none; display: grid; place-items: center; padding: 0; }
.foto-buang svg { width: 20px; height: 20px; padding: 5px; border-radius: 10px; background: rgba(11, 17, 23, .66); color: #FFFFFF; }
.foto-tambah { aspect-ratio: 1; border-radius: 12px; border: 1.5px dashed #B8C0C9; background: #FFFFFF; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; color: var(--abu1); font-size: 12px; font-weight: 600; }
.foto-tambah svg { width: 22px; height: 22px; }
</style>

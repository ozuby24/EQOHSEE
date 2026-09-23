<script setup lang="ts">
/**
 * Pengisian satu bagian audit.
 *
 * Dua kolom nilai, bukan satu: "Mitra" diisi mitra sebagai penilaian
 * mandiri, "Verifikasi" diisi auditor sesudah memeriksa lapangan. Yang
 * menentukan skor akhir hanya kolom verifikasi. Disatukan, audit
 * berubah menjadi formulir isian mandiri yang ditandatangani auditor.
 *
 * Nilainya dipilih dengan tombol 0–3, bukan daftar pilihan: di tablet
 * lapangan satu ketukan lebih cepat dan lebih jarang meleset daripada
 * membuka daftar lalu memilih — dikalikan seratus lima puluh baris
 * bagian B. Mengetuk nilai yang sudah terpilih mengosongkannya.
 *
 * Seluruh bagian disimpan dalam SATU kiriman, dan perubahan yang belum
 * disimpan dihitung serta dijaga: berpindah bagian lewat bilah atas
 * menanyakan dulu, bukan membuang isian diam-diam.
 */
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import type { HalamanAuditLingkunganBagian } from '../../types';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
import PindahBagian from './Pindah.vue';
import { WARNA_BAGIAN } from './bantu';
import './akl.css';

const props = defineProps<HalamanAuditLingkunganBagian>();

const { dialog, tanya, batal, lanjut } = useDialog();

type Baris = { nilai: number | null; verifikasi: number | null; keterangan: string };

const isi = reactive<Record<string, Baris>>({});

for (const s of props.susun) {
  for (const g of s.kelompok) {
    for (const b of g.butir) {
      isi[b.kode] = { nilai: b.nilai, verifikasi: b.verifikasi, keterangan: b.keterangan ?? '' };
    }
  }
}

const semua = computed(() => Object.keys(isi));
const sidik = (b: Baris) => `${b.nilai ?? ''}|${b.verifikasi ?? ''}|${b.keterangan}`;
const potret = () => Object.fromEntries(Object.entries(isi).map(([k, v]) => [k, sidik(v)]));

/* ═══════════ perubahan yang belum disimpan ═══════════ */

const dasar = ref<Record<string, string>>(potret());
const kotor = computed(() => semua.value.filter((k) => sidik(isi[k]) !== dasar.value[k]).length);

let izinkan = false;
const lepasJaga = router.on('before', (e) => {
  const v = e.detail.visit;
  if (izinkan || !kotor.value || v.method !== 'get') return;

  const tujuan = v.url.href;
  tanya({
    judul: 'Tinggalkan bagian ini?',
    pesan: `${kotor.value} baris belum disimpan dan akan hilang bila halaman ini ditinggalkan.`,
    labelAksi: 'Tinggalkan', nada: 'bahaya',
  }).then((ok) => { if (ok) { izinkan = true; router.visit(tujuan); } });

  return false;
});

const jagaMuatUlang = (e: BeforeUnloadEvent) => {
  if (!kotor.value) return;
  e.preventDefault();
  e.returnValue = '';
};
onMounted(() => window.addEventListener('beforeunload', jagaMuatUlang));
onBeforeUnmount(() => { lepasJaga(); window.removeEventListener('beforeunload', jagaMuatUlang); });

/* ═══════════ skor hidup ═══════════ */

const hidup = computed(() => {
  let jumlah = 0;
  let belum = 0;
  for (const k of semua.value) {
    const v = isi[k].verifikasi;
    if (v === null || v === undefined) { belum++; continue; }
    jumlah += Math.max(0, Math.min(3, v));
  }
  const maks = semua.value.length * 3;
  return { jumlah, belum, maks, persen: maks ? Math.round((jumlah / maks) * 1000) / 10 : 0 };
});

const selisih = (k: string) => isi[k].nilai !== null && isi[k].verifikasi !== null && isi[k].nilai !== isi[k].verifikasi;

function statKelompok(butir: Array<{ kode: string }>) {
  const n = butir.length;
  const sudah = butir.filter((b) => isi[b.kode].verifikasi !== null).length;
  const jumlah = butir.reduce((t, b) => t + (isi[b.kode].verifikasi ?? 0), 0);
  return { n, sudah, persen: n ? Math.round((jumlah / (n * 3)) * 100) : 0 };
}

/* ═══════════ saringan ═══════════ */

const tampilan = ref<'semua' | 'belum' | 'selisih' | 'kurang'>('semua');
const cari = ref('');

/* Himpunan baris yang tampil dihitung SAAT saringan diganti, bukan
   terus-menerus: baris yang baru diisi pada saringan "belum" tidak
   langsung lenyap dari bawah jari yang mengisinya. */
const terlihat = ref<Set<string> | null>(null);

function terapkanSaringan() {
  const q = cari.value.trim().toLowerCase();
  if (tampilan.value === 'semua' && !q) { terlihat.value = null; return; }

  const out = new Set<string>();
  for (const s of props.susun) {
    for (const g of s.kelompok) {
      for (const b of g.butir) {
        const r = isi[b.kode];
        const cocok = tampilan.value === 'semua' ? true
          : tampilan.value === 'belum' ? r.verifikasi === null
            : tampilan.value === 'selisih' ? selisih(b.kode)
              : r.verifikasi !== null && r.verifikasi < 3;
        if (cocok && (!q || b.uraian.toLowerCase().includes(q) || g.nama.toLowerCase().includes(q))) out.add(b.kode);
      }
    }
  }
  terlihat.value = out;
}
watch([tampilan, cari], terapkanSaringan);

const tampak = (kode: string) => terlihat.value === null || terlihat.value.has(kode);
const jumlahTampak = computed(() => terlihat.value?.size ?? semua.value.length);

const hitungSaring = computed(() => ({
  belum: semua.value.filter((k) => isi[k].verifikasi === null).length,
  selisih: semua.value.filter((k) => selisih(k)).length,
  kurang: semua.value.filter((k) => isi[k].verifikasi !== null && (isi[k].verifikasi as number) < 3).length,
}));

/* ═══════════ simpan ═══════════ */

const menyimpan = ref(false);
const galat = ref<string[]>([]);

function simpan() {
  menyimpan.value = true;
  galat.value = [];

  router.post(props.tautan.simpanNilai, { bagian: props.kini, nilai: isi }, {
    /* preserveState: isian DIPERTAHANKAN ketika kiriman ditolak —
       tanpa ini halaman digambar ulang dari props dan seluruh isian
       yang belum tersimpan hilang. */
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => { dasar.value = potret(); },
    onError: (e) => { galat.value = Object.values(e as Record<string, string>); },
    onFinish: () => { menyimpan.value = false; },
  });
}

/* ═══════════ aksi kelompok ═══════════ */

function terima(butir: Array<{ kode: string }>) {
  for (const b of butir) {
    const n = isi[b.kode].nilai;
    if (n !== null && n !== undefined) isi[b.kode].verifikasi = n;
  }
}
function setPenuh(butir: Array<{ kode: string }>) {
  for (const b of butir) isi[b.kode].verifikasi = 3;
}

function pilih(kode: string, kolom: 'nilai' | 'verifikasi', n: number) {
  isi[kode][kolom] = isi[kode][kolom] === n ? null : n;
}

const warnaBagian = computed(() => WARNA_BAGIAN[props.kini] ?? '#DC6E00');
</script>

<template>
  <Head :title="`${a.kode} · Bagian ${kini.toUpperCase()}`" />

  <div class="akl-akar space-y-4">
    <PindahBagian :bagian="bagian" :tautan-bagian="tautan.bagian"
                  :ikhtisar="tautan.ikhtisar" :kini="kini" />

    <!-- Kepala yang MELEKAT: tombol simpan yang tertinggal di puncak
         berarti yang selesai mengisi baris terakhir bagian B harus
         menggulir kembali seratus lima puluh baris. -->
    <div class="akb-kepala" :style="{ '--akb-warna': warnaBagian }">
      <span class="akl-kb-huruf" :style="{ background: warnaBagian }">{{ kini.toUpperCase() }}</span>
      <div class="min-w-0 flex-1">
        <h3 class="akb-judul">{{ skorBagian.judul }}</h3>
        <p class="akb-ket num">
          bobot {{ skorBagian.bobot.toFixed(2).replace('.', ',') }} ·
          <b>{{ hidup.jumlah }}</b> / {{ hidup.maks }} poin · <b>{{ String(hidup.persen).replace('.', ',') }}%</b>
          <template v-if="hidup.belum"> · <span class="is-belum">{{ hidup.belum }} belum diverifikasi</span></template>
          <template v-else> · <span class="is-lengkap">lengkap</span></template>
        </p>
        <div class="akb-pita"><span :style="{ width: `${((semua.length - hidup.belum) / Math.max(1, semua.length)) * 100}%` }"></span></div>
      </div>

      <span v-if="kotor" class="akb-kotor" role="status">{{ kotor }} belum disimpan</span>
      <button type="button" class="eq-btn-utama" style="flex:none;padding:8px 18px"
              :disabled="menyimpan || !kotor" @click="simpan">
        {{ menyimpan ? 'Menyimpan…' : kotor ? 'Simpan Bagian' : '✓ Tersimpan' }}
      </button>
    </div>

    <!-- tangga nilai & saringan -->
    <div class="akb-alat">
      <div class="akb-tangga" aria-label="Kriteria nilai">
        <span v-for="(t, n) in tangga" :key="n" :class="`n${n}`"><b class="num">{{ n }}</b>{{ t }}</span>
      </div>

      <div class="akb-saring">
        <div class="akl-saring-pil" role="group" aria-label="Tampilkan baris">
          <button v-for="s in ([['semua', `Semua ${semua.length}`], ['belum', `Belum ${hitungSaring.belum}`],
                                 ['selisih', `Berselisih ${hitungSaring.selisih}`], ['kurang', `Di bawah 3: ${hitungSaring.kurang}`]] as const)"
                  :key="s[0]" type="button" :class="{ kini: tampilan === s[0] }" @click="tampilan = s[0]">{{ s[1] }}</button>
        </div>
        <input v-model="cari" type="search" class="akl-isian akb-cari" placeholder="Cari kriteria…" aria-label="Cari kriteria">
        <button v-if="terlihat" type="button" class="eq-btn-mini" @click="terapkanSaringan">↻ Segarkan saringan</button>
      </div>
    </div>

    <p v-if="terlihat && !jumlahTampak" class="eq-kosong eq-kosong-kecil">
      <strong>Tidak ada kriteria pada saringan ini.</strong>
    </p>

    <section v-for="s in susun" :key="s.nama || 'tunggal'"
             v-show="s.kelompok.some((g) => g.butir.some((b) => tampak(b.kode)))">
      <h4 v-if="s.nama" class="akb-sub">{{ s.nama }}</h4>

      <div v-for="g in s.kelompok" :key="g.nama" v-show="g.butir.some((b) => tampak(b.kode))" class="akb-grup">
        <header class="akb-grup-kepala">
          <div class="min-w-0">
            <span class="akb-grup-nama">{{ g.nama }}</span>
            <span class="akb-grup-stat num">
              {{ statKelompok(g.butir).sudah }}/{{ statKelompok(g.butir).n }} diverifikasi · {{ statKelompok(g.butir).persen }}%
            </span>
          </div>
          <span class="akb-grup-aksi">
            <button type="button" class="akb-mini" @click="terima(g.butir)">Terima nilai mitra</button>
            <button type="button" class="akb-mini" @click="setPenuh(g.butir)">Semua 3</button>
          </span>
        </header>

        <div class="akb-kolom" aria-hidden="true">
          <span>#</span><span>Kriteria penilaian</span><span>Mitra</span><span>Verifikasi auditor</span><span>Keterangan</span>
        </div>

        <div v-for="b in g.butir" v-show="tampak(b.kode)" :key="b.kode" class="akb-baris"
             :class="{ selisih: selisih(b.kode), berubah: sidik(isi[b.kode]) !== dasar[b.kode] }">
          <span class="akb-huruf">{{ b.huruf ?? '—' }}</span>

          <p class="akb-uraian">
            {{ b.uraian }}
            <span v-if="b.kosong" class="akb-tanda">uraian kosong pada berkas acuan</span>
            <span v-if="selisih(b.kode)" class="akb-tanda selisih">mitra {{ isi[b.kode].nilai }} ≠ verifikasi {{ isi[b.kode].verifikasi }}</span>
          </p>

          <div class="akb-nilai">
            <span class="akb-nilai-lbl">Mitra</span>
            <div class="akb-seg mitra" role="radiogroup" :aria-label="`Nilai mitra ${b.kode}`">
              <button v-for="n in [0, 1, 2, 3]" :key="n" type="button" role="radio"
                      :aria-checked="isi[b.kode].nilai === n" :class="[`n${n}`, { pilih: isi[b.kode].nilai === n }]"
                      :title="tangga[n]" @click="pilih(b.kode, 'nilai', n)">{{ n }}</button>
            </div>
          </div>

          <div class="akb-nilai">
            <span class="akb-nilai-lbl">Verifikasi</span>
            <div class="akb-seg" role="radiogroup" :aria-label="`Verifikasi ${b.kode}`">
              <button v-for="n in [0, 1, 2, 3]" :key="n" type="button" role="radio"
                      :aria-checked="isi[b.kode].verifikasi === n" :class="[`n${n}`, { pilih: isi[b.kode].verifikasi === n }]"
                      :title="tangga[n]" @click="pilih(b.kode, 'verifikasi', n)">{{ n }}</button>
            </div>
          </div>

          <div class="akb-ket-kolom">
            <!-- maxlength DARI SERVER: keterangan yang melampaui batas
                 server membuat SELURUH kiriman bagian ditolak. -->
            <input v-model="isi[b.kode].keterangan" class="akl-isian akb-ket-isian"
                   :maxlength="props.maksKeterangan" placeholder="Dokumen pendukung / catatan"
                   :aria-label="`Keterangan ${b.kode}`">
            <a v-if="b.berkas.length" :href="b.berkas[0]" target="_blank" rel="noopener" class="akb-lampiran">
              📎 {{ b.berkas.length }} lampiran
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- Galat kiriman. TIDAK ADA satu pun nilai yang tersimpan ketika
         ini muncul — kiriman bagian ditolak seluruhnya. -->
    <div v-if="galat.length" role="alert" class="akl-kotak-info bahaya space-y-1">
      <p class="font-semibold">
        Bagian ini TIDAK tersimpan — tidak satu pun nilainya masuk. Perbaiki
        {{ galat.length === 1 ? 'satu hal' : `${galat.length} hal` }} berikut lalu simpan lagi.
      </p>
      <ul class="list-disc pl-5">
        <li v-for="(x, i) in galat" :key="i">{{ x }}</li>
      </ul>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <button type="button" class="eq-btn-utama" style="flex:none;padding:9px 20px"
              :disabled="menyimpan || !kotor" @click="simpan">
        {{ menyimpan ? 'Menyimpan…' : kotor ? `Simpan ${kotor} Perubahan` : '✓ Tersimpan' }}
      </button>
      <a :href="tautan.ikhtisar" class="eq-btn-mini">← Ikhtisar</a>
    </div>
  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>

<style scoped>
.akb-kepala {
  position: sticky; top: var(--eq-topbar-h, 66px); z-index: 20;
  display: flex; align-items: center; gap: .9rem; flex-wrap: wrap;
  background: rgba(255, 255, 255, .96); border: 1px solid var(--akl-garis); border-radius: 1rem;
  padding: .75rem 1rem; box-shadow: 0 8px 20px -18px rgba(15, 23, 32, .5);
}
@supports (backdrop-filter: blur(2px)) {
  .akb-kepala { background: rgba(255, 255, 255, .86); backdrop-filter: saturate(180%) blur(12px); }
}
.akb-judul { font-size: 13.5px; font-weight: 800; color: var(--akl-tinta); }
.akb-ket { font-size: 11.5px; color: var(--akl-tinta-2); margin-top: .1rem; }
.akb-ket b { color: var(--akl-tinta); }
.is-belum { color: #B45309; font-weight: 700; }
.is-lengkap { color: #15803D; font-weight: 700; }
.akb-pita { height: 4px; border-radius: 99px; background: var(--akl-jalur); overflow: hidden; margin-top: .4rem; }
.akb-pita > span { display: block; height: 100%; background: var(--akb-warna); border-radius: 99px; transition: width .25s; }
.akb-kotor {
  font-size: 11px; font-weight: 800; color: #92400E; background: #FEF3C7; border-radius: 999px; padding: .25rem .6rem;
}

.akb-alat { display: grid; gap: .6rem; }
.akb-tangga { display: flex; flex-wrap: wrap; gap: .4rem; }
.akb-tangga > span {
  display: inline-flex; align-items: flex-start; gap: .35rem; font-size: 10.5px; line-height: 1.35; color: var(--akl-tinta-2);
  border: 1px solid var(--akl-garis); border-radius: .6rem; padding: .3rem .5rem; flex: 1 1 200px;
}
.akb-tangga b { flex: none; width: 18px; height: 18px; border-radius: 5px; display: grid; place-items: center; color: #fff; font-size: 11px; }
.akb-tangga .n0 b { background: #DC2626; } .akb-tangga .n1 b { background: #EA580C; }
.akb-tangga .n2 b { background: #CA8A04; } .akb-tangga .n3 b { background: #16A34A; }

.akb-saring { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
.akl-saring-pil { display: flex; flex-wrap: wrap; gap: .3rem; }
.akl-saring-pil button {
  font-size: 11px; font-weight: 700; padding: .3rem .7rem; border-radius: 999px;
  border: 1px solid var(--akl-garis); color: var(--akl-tinta-2);
}
.akl-saring-pil button.kini { background: #0F1720; border-color: #0F1720; color: #fff; }
.akb-cari { width: 14rem; max-width: 100%; padding-block: .35rem; }

.akb-sub {
  font-size: 11px; font-weight: 800; letter-spacing: .07em; text-transform: uppercase;
  color: #fff; background: #1C1917; padding: .4rem .75rem; border-radius: .6rem; margin-bottom: .5rem;
}

.akb-grup { background: var(--akl-permukaan); border: 1px solid var(--akl-garis); border-radius: 1rem; overflow: hidden; margin-bottom: .75rem; }
.akb-grup-kepala {
  display: flex; align-items: center; justify-content: space-between; gap: .75rem; flex-wrap: wrap;
  background: #FAFAF9; border-bottom: 1px solid var(--akl-garis-halus); padding: .55rem .8rem;
}
.akb-grup-nama { display: block; font-size: 12px; font-weight: 800; color: var(--akl-tinta); }
.akb-grup-stat { display: block; font-size: 10.5px; color: var(--akl-tinta-3); margin-top: .05rem; }
.akb-grup-aksi { display: flex; gap: .35rem; flex: none; }
.akb-mini {
  border: 1px solid var(--akl-garis); background: var(--akl-permukaan); border-radius: .5rem;
  padding: .25rem .55rem; font-size: 10.5px; font-weight: 700; color: var(--akl-tinta-2); transition: border-color .16s, color .16s;
}
.akb-mini:hover { border-color: #DC6E00; color: #DC6E00; }

.akb-kolom, .akb-baris {
  display: grid; grid-template-columns: 26px minmax(0, 1fr) 148px 148px minmax(170px, .5fr);
  gap: .75rem; align-items: start; padding: .5rem .8rem;
}
.akb-kolom {
  font-size: 9.5px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase;
  color: var(--akl-tinta-3); border-bottom: 1px solid var(--akl-garis-halus); padding-block: .35rem;
}
.akb-baris { border-bottom: 1px solid var(--akl-garis-halus); transition: background-color .15s; }
.akb-baris:last-child { border-bottom: 0; }
.akb-baris.berubah { background: #FFFDF5; box-shadow: inset 3px 0 0 #F59E0B; }
.akb-baris.selisih { background: #FFFBEB; }

.akb-huruf { font-size: 11.5px; font-weight: 800; color: var(--akl-tinta-3); padding-top: .2rem; }
.akb-uraian { font-size: 12px; line-height: 1.5; color: var(--akl-tinta); }
.akb-tanda {
  display: inline-block; margin-left: .3rem; font-size: 9.5px; font-weight: 700; color: #B45309;
  background: #FEF3C7; padding: .05rem .35rem; border-radius: .3rem;
}
.akb-tanda.selisih { color: #9A3412; background: #FFEDD5; }

.akb-nilai-lbl { display: none; font-size: 9.5px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; color: var(--akl-tinta-3); margin-bottom: .2rem; }
.akb-seg { display: grid; grid-template-columns: repeat(4, 1fr); gap: 3px; }
.akb-seg button {
  height: 32px; border-radius: .5rem; border: 1px solid var(--akl-garis); background: var(--akl-permukaan);
  font-size: 13px; font-weight: 800; color: var(--akl-tinta-3); font-variant-numeric: tabular-nums;
  transition: background-color .12s, color .12s, border-color .12s, transform .12s;
}
.akb-seg button:hover { border-color: #A8A29E; color: var(--akl-tinta); }
.akb-seg button:active { transform: scale(.94); }
.akb-seg button:focus-visible { outline: 2px solid #F57C00; outline-offset: 1px; }
.akb-seg button.pilih { color: #fff; border-color: transparent; }
.akb-seg button.n0.pilih { background: #DC2626; }
.akb-seg button.n1.pilih { background: #EA580C; }
.akb-seg button.n2.pilih { background: #CA8A04; }
.akb-seg button.n3.pilih { background: #16A34A; }
.akb-seg.mitra button { height: 28px; font-size: 12px; }
.akb-seg.mitra button.pilih { opacity: .78; }

.akb-ket-isian { padding: .4rem .55rem; font-size: 11.5px; }
.akb-lampiran { display: block; margin-top: .2rem; font-size: 10.5px; color: #DC6E00; }
.akb-lampiran:hover { text-decoration: underline; }

@media (max-width: 64rem) {
  .akb-kolom { display: none; }
  .akb-baris { grid-template-columns: 22px minmax(0, 1fr) minmax(0, 1fr); gap: .5rem .7rem; padding: .7rem .8rem; }
  .akb-uraian { grid-column: 2 / -1; }
  .akb-nilai:nth-of-type(1) { grid-column: 2; }
  .akb-nilai { grid-column: auto; }
  .akb-nilai-lbl { display: block; }
  .akb-ket-kolom { grid-column: 2 / -1; }
}
@media (max-width: 30rem) {
  .akb-baris { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
  .akb-huruf { display: none; }
  .akb-uraian, .akb-ket-kolom { grid-column: 1 / -1; }
  .akb-nilai:nth-of-type(1) { grid-column: 1; }
  .akb-nilai:nth-of-type(2) { grid-column: 2; }
}

:global(:root[data-tema="gelap"] .akb-kepala) { background: rgba(16, 26, 30, .92); }
:global(:root[data-tema="gelap"] .akb-kotor) { background: rgba(245, 158, 11, .16); color: #FCD34D; }
:global(:root[data-tema="gelap"] .akb-grup-kepala) { background: #0D161A; }
:global(:root[data-tema="gelap"] .akb-baris.berubah) { background: rgba(245, 158, 11, .07); }
:global(:root[data-tema="gelap"] .akb-baris.selisih) { background: rgba(245, 158, 11, .1); }
:global(:root[data-tema="gelap"] .akb-tanda) { background: rgba(245, 158, 11, .16); color: #FCD34D; }
:global(:root[data-tema="gelap"] .akb-tanda.selisih) { background: rgba(234, 88, 12, .18); color: #FDBA74; }
:global(:root[data-tema="gelap"] .akl-saring-pil button.kini) { background: #EEF3F4; border-color: #EEF3F4; color: #0F1720; }
:global(:root[data-tema="gelap"] .is-belum) { color: #FCD34D; }
:global(:root[data-tema="gelap"] .is-lengkap) { color: #86EFAC; }
</style>

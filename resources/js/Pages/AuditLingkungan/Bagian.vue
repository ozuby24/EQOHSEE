<script setup lang="ts">
/**
 * Pengisian satu bagian audit.
 *
 * Dua kolom nilai, bukan satu: "Nilai" diisi mitra sebagai penilaian
 * mandiri, "Verifikasi" diisi auditor sesudah memeriksa lapangan. Yang
 * menentukan skor akhir hanya kolom verifikasi. Disatukan, audit
 * berubah menjadi formulir isian mandiri yang ditandatangani auditor.
 *
 * Baris yang kedua kolomnya BERSELISIH ditandai — itulah baris yang
 * perlu ditengok, dan tanpa penanda ia tenggelam di antara seratus
 * lima puluh baris lain.
 *
 * Seluruh bagian disimpan dalam SATU kiriman. Menyimpan per baris
 * berarti yang mengisi bagian B menunggu seratus lima puluh kali, dan
 * kehilangan sebagian isian tiap kali sambungan lapangan terputus.
 */
import { computed, reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import type { HalamanAuditLingkunganBagian } from '../../types';
import PindahBagian from './Pindah.vue';

const props = defineProps<HalamanAuditLingkunganBagian>();

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

/** Skor bagian yang dihitung DI LAYAR, langsung saat nilai diubah. */
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

const menyimpan = ref(false);

/** Pesan galat per kriteria, dari kiriman yang ditolak server. */
const galat = ref<string[]>([]);

function simpan() {
  menyimpan.value = true;
  galat.value = [];

  router.post(props.tautan.simpanNilai, { bagian: props.kini, nilai: isi }, {
    /* preserveState: isian DIPERTAHANKAN ketika kiriman ditolak.
       Tanpa ini halaman digambar ulang dari props, dan seluruh isian
       yang belum tersimpan hilang — tepat pada saat yang paling
       menyakitkan, yaitu sesudah seseorang mengisi seratus lima puluh
       baris. */
    preserveState: true,
    preserveScroll: true,

    /* Galatnya DITAMPILKAN. Sebelumnya halaman ini tidak punya satu
       pun tempat menggambar galat: kiriman yang ditolak memulangkan
       302, layar tidak berubah sedikit pun, dan yang mengisinya
       menyimpulkan nilainya sudah tersimpan — padahal tidak satu pun
       tersimpan. */
    onError: (e) => { galat.value = Object.values(e as Record<string, string>); },
    onFinish: () => { menyimpan.value = false; },
  });
}

/** Salin seluruh kolom Nilai ke kolom Verifikasi pada satu kelompok. */
function terima(butir: Array<{ kode: string }>) {
  for (const b of butir) {
    const n = isi[b.kode].nilai;
    if (n !== null && n !== undefined) isi[b.kode].verifikasi = n;
  }
}

function setPenuh(butir: Array<{ kode: string }>) {
  for (const b of butir) isi[b.kode].verifikasi = 3;
}

const nadaNilai = (v: number | null) =>
  v === null || v === undefined ? 'is-kosong'
    : v === 3 ? 'is-3' : v === 2 ? 'is-2' : v === 1 ? 'is-1' : 'is-0';

const isian = 'ring-focus w-full rounded-lg border border-stone-200 px-2 py-1.5 text-[11.5px]';
</script>

<template>
  <Head :title="`${a.kode} · Bagian ${kini.toUpperCase()}`" />

  <div class="space-y-4">
    <PindahBagian :bagian="bagian" :tautan-bagian="tautan.bagian"
                  :ikhtisar="tautan.ikhtisar" :kini="kini" />

    <!-- Kepala yang MELEKAT: bagian B berisi seratus lima puluh
         kriteria, dan tombol simpan yang tertinggal di puncaknya berarti
         yang selesai mengisi baris terakhir harus menggulir kembali. -->
    <div class="akl-kepala">
      <div class="min-w-0">
        <h3 class="akl-judul">Bagian {{ kini.toUpperCase() }} · {{ skorBagian.judul }}</h3>
        <p class="akl-ket num">
          Bobot {{ skorBagian.bobot.toFixed(2) }} ·
          <b>{{ hidup.jumlah }}</b> / {{ hidup.maks }} poin ·
          <b>{{ hidup.persen }}%</b>
          <template v-if="hidup.belum"> · <span class="is-belum">{{ hidup.belum }} belum diverifikasi</span></template>
          <template v-else> · <span class="is-lengkap">lengkap</span></template>
        </p>
      </div>

      <button type="button" class="eq-btn-utama" style="flex:none;padding:8px 18px"
              :disabled="menyimpan" @click="simpan">
        {{ menyimpan ? 'Menyimpan…' : 'Simpan Bagian' }}
      </button>
    </div>

    <p class="akl-tangga">
      <b>Kriteria nilai —</b>
      <span v-for="(t, n) in tangga" :key="n"><b class="num">{{ n }}</b> {{ t }}</span>
    </p>

    <section v-for="s in susun" :key="s.nama || 'tunggal'" class="akl-sub">
      <h4 v-if="s.nama" class="akl-sub-judul">{{ s.nama }}</h4>

      <div v-for="g in s.kelompok" :key="g.nama" class="akl-grup">
        <header class="akl-grup-kepala">
          <span class="akl-grup-nama">{{ g.nama }}</span>
          <span class="akl-grup-aksi">
            <button type="button" class="akl-mini" @click="terima(g.butir)">Terima nilai mitra</button>
            <button type="button" class="akl-mini" @click="setPenuh(g.butir)">Semua 3</button>
          </span>
        </header>

        <table class="akl-nilai">
          <thead>
            <tr>
              <th class="akl-k-huruf">#</th>
              <th class="akl-k-uraian">Kriteria Penilaian</th>
              <th class="akl-k-n">Nilai<small>mitra</small></th>
              <th class="akl-k-n">Verifikasi<small>auditor</small></th>
              <th class="akl-k-ket">Keterangan</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in g.butir" :key="b.kode"
                :class="{ 'is-selisih': isi[b.kode].nilai !== null && isi[b.kode].verifikasi !== null
                                        && isi[b.kode].nilai !== isi[b.kode].verifikasi }">
              <td class="akl-k-huruf">{{ b.huruf ?? '—' }}</td>

              <td class="akl-k-uraian">
                {{ b.uraian }}
                <span v-if="b.kosong" class="akl-kosong-tanda">
                  uraian kosong pada berkas acuan
                </span>
              </td>

              <td class="akl-k-n">
                <select v-model.number="isi[b.kode].nilai" class="akl-pilih"
                        :class="nadaNilai(isi[b.kode].nilai)" :aria-label="`Nilai mitra ${b.kode}`">
                  <option :value="null">—</option>
                  <option v-for="n in [0, 1, 2, 3]" :key="n" :value="n">{{ n }}</option>
                </select>
              </td>

              <td class="akl-k-n">
                <select v-model.number="isi[b.kode].verifikasi" class="akl-pilih"
                        :class="nadaNilai(isi[b.kode].verifikasi)" :aria-label="`Verifikasi ${b.kode}`">
                  <option :value="null">—</option>
                  <option v-for="n in [0, 1, 2, 3]" :key="n" :value="n">{{ n }}</option>
                </select>
              </td>

              <td class="akl-k-ket">
                <!-- maxlength DARI SERVER, bukan angka yang ditulis
                     ulang di sini. Tanpa batas sama sekali, keterangan
                     yang melampaui batas server membuat SELURUH kiriman
                     bagian ditolak — seratus lima puluh baris isian
                     hilang karena satu kolom. -->
                <input v-model="isi[b.kode].keterangan" :class="isian"
                       :maxlength="props.maksKeterangan"
                       placeholder="Dokumen pendukung atau catatan"
                       :aria-label="`Keterangan ${b.kode}`">

                <a v-if="b.berkas.length" :href="b.berkas[0]" target="_blank" rel="noopener"
                   class="akl-lampiran">📎 {{ b.berkas.length }} lampiran</a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Galat kiriman. Menyebut KRITERIANYA, bukan nomor larik: lembar
         bagian B berisi seratus lima puluh baris, dan pesan yang tidak
         menyebut kriteria mana memaksa yang mengisinya mencari sendiri.

         TIDAK ADA satu pun nilai yang tersimpan ketika ini muncul —
         kiriman bagian ditolak seluruhnya — jadi kalimatnya menyebutkan
         itu secara tegas. Yang mengisinya perlu tahu bahwa menutup
         halaman sekarang berarti kehilangan seluruhnya. -->
    <div v-if="galat.length" role="alert"
         class="rounded-2xl border border-red-200 bg-red-50 p-4 space-y-1">
      <p class="text-[12.5px] font-semibold text-red-700">
        Bagian ini TIDAK tersimpan — tidak satu pun nilainya masuk. Perbaiki
        {{ galat.length === 1 ? 'satu hal' : `${galat.length} hal` }} berikut lalu simpan lagi.
      </p>
      <ul class="list-disc pl-5 text-[11.5px] text-red-700">
        <li v-for="(g, i) in galat" :key="i">{{ g }}</li>
      </ul>
    </div>

    <div class="flex items-center gap-2">
      <button type="button" class="eq-btn-utama" style="flex:none;padding:9px 20px"
              :disabled="menyimpan" @click="simpan">
        {{ menyimpan ? 'Menyimpan…' : 'Simpan Bagian' }}
      </button>
      <a :href="tautan.ikhtisar" class="eq-btn-mini">← Ikhtisar</a>
    </div>
  </div>
</template>

<style scoped>
.akl-kepala {
  position: sticky; top: var(--eq-topbar-h, 66px); z-index: 20;
  display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;
  background: rgba(255, 255, 255, .96);
  border: 1px solid #E7E5E4; border-radius: 1rem;
  padding: .75rem 1rem;
}
@supports (backdrop-filter: blur(2px)) {
  .akl-kepala { background: rgba(255, 255, 255, .86); backdrop-filter: saturate(180%) blur(12px); }
}

.akl-judul { font-size: 13.5px; font-weight: 800; color: #0F1720; }
.akl-ket   { font-size: 11.5px; color: #78716C; margin-top: .15rem; }
.akl-ket b  { color: #0F1720; }
.is-belum   { color: #B45309; font-weight: 700; }
.is-lengkap { color: #15803D; font-weight: 700; }

.akl-tangga {
  font-size: 10.5px; color: #78716C; line-height: 1.6;
  background: #FAFAF9; border: 1px solid #F0EFEE; border-radius: .8rem; padding: .5rem .7rem;
}
.akl-tangga span { display: inline-block; margin-right: 1rem; }
.akl-tangga span b { color: #0F1720; margin-right: .2rem; }

.akl-sub { }
.akl-sub-judul {
  font-size: 11px; font-weight: 800; letter-spacing: .07em; text-transform: uppercase;
  color: #fff; background: #1C1917; padding: .35rem .7rem; border-radius: .6rem;
  margin-bottom: .5rem;
}

.akl-grup {
  background: #fff; border: 1px solid #E7E5E4; border-radius: 1rem;
  overflow: hidden; margin-bottom: .7rem;
}

.akl-grup-kepala {
  display: flex; align-items: center; justify-content: space-between; gap: .75rem;
  background: #FAFAF9; border-bottom: 1px solid #F0EFEE; padding: .5rem .75rem;
}
.akl-grup-nama { font-size: 12px; font-weight: 800; color: #0F1720; }
.akl-grup-aksi { display: flex; gap: .35rem; flex: none; }

.akl-mini {
  border: 1px solid #E7E5E4; background: #fff; border-radius: .5rem;
  padding: .2rem .5rem; font-size: 10px; font-weight: 700; color: #57534E;
  transition: border-color .16s, color .16s;
}
.akl-mini:hover { border-color: #DC6E00; color: #DC6E00; }

.akl-nilai { width: 100%; border-collapse: collapse; font-size: 11.5px; }
.akl-nilai thead th {
  background: #fff; border-bottom: 1px solid #F0EFEE; padding: .4rem .6rem;
  text-align: left; font-size: 9.5px; font-weight: 800; letter-spacing: .04em;
  text-transform: uppercase; color: #A8A29E; white-space: normal; vertical-align: bottom;
}
.akl-nilai thead small { display: block; font-weight: 600; letter-spacing: 0; text-transform: none; }
.akl-nilai tbody td { border-bottom: 1px solid #F7F6F5; padding: .4rem .6rem; vertical-align: top; }
.akl-nilai tbody tr.is-selisih td { background: #FFFBEB; }

.akl-k-huruf  { width: 4%; color: #A8A29E; font-weight: 700; }
.akl-k-uraian { width: 50%; line-height: 1.45; }
.akl-k-n      { width: 9%; }
.akl-k-ket    { width: 28%; }

.akl-kosong-tanda {
  display: inline-block; margin-left: .3rem;
  font-size: 9px; font-weight: 700; color: #B45309;
  background: #FEF3C7; padding: .05rem .3rem; border-radius: .25rem;
}

.akl-pilih {
  width: 100%; border: 1px solid #E7E5E4; border-radius: .45rem;
  padding: .25rem .3rem; font-size: 12px; font-weight: 800; text-align: center;
  font-variant-numeric: tabular-nums;
}
.akl-pilih.is-3 { background: #E7F8ED; color: #15803D; border-color: #A7E8BE; }
.akl-pilih.is-2 { background: #FEF9E7; color: #B45309; border-color: #FBDF9A; }
.akl-pilih.is-1 { background: #FFF1E6; color: #C2410C; border-color: #FDBA74; }
.akl-pilih.is-0 { background: #FEE9E9; color: #B91C1C; border-color: #FCA5A5; }
.akl-pilih.is-kosong { color: #A8A29E; }

.akl-lampiran { display: block; margin-top: .2rem; font-size: 10px; color: #DC6E00; }
.akl-lampiran:hover { text-decoration: underline; }

@media (max-width: 60rem) {
  .akl-k-ket { display: none; }
  .akl-k-uraian { width: 70%; }
}
</style>

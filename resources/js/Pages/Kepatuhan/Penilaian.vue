<script setup lang="ts">
/**
 * Penilaian Pemenuhan per butir.
 *
 * Tempat pekerjaan sebenarnya dikerjakan: satu baris per pasal, ayat,
 * atau klausul, dengan kolom Rangkuman Isi, Penerapan, Evaluasi
 * Pemenuhan, dan Keterangan & Tindak Lanjut — mengikuti bentuk tabel
 * yang selama ini dipakai di berkas Word, supaya yang pindah ke sini
 * tidak perlu belajar bentuk baru.
 *
 * Blok Rencana Tindak Lanjut, PIC, dan Target hanya muncul saat
 * statusnya Not Comply. Pada butir yang comply, ketiganya adalah tiga
 * medan kosong yang tidak akan pernah diisi — dikali lima puluh butir,
 * itulah yang membuat register terasa seperti formulir pajak.
 */
import { computed, reactive, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import type { HalamanKepatuhanPenilaian } from '../../types';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';

const { dialog, tanya, batal, lanjut } = useDialog();

const props = defineProps<HalamanKepatuhanPenilaian>();

/** Baris yang sedang dibuka penyuntingnya; null berarti tidak ada. */
const menyunting = ref<number | null>(null);

const draf = reactive<Record<string, string>>({});

function buka(b: HalamanKepatuhanPenilaian['butir'][number]) {
  menyunting.value = b.id;

  Object.assign(draf, {
    penunjuk: b.penunjuk ?? '',
    rangkuman: b.rangkuman ?? '',
    penerapan: b.penerapan ?? '',
    status: b.status ?? '',
    keterangan: b.keterangan ?? '',
    tindak_lanjut: b.tindak ?? '',
    pic: b.pic ?? '',
    target: b.target ?? '',
  });
}

const menyimpan = ref(false);

function simpan(url: string) {
  menyimpan.value = true;

  router.put(url, { ...draf }, {
    preserveScroll: true,
    onSuccess: () => { menyunting.value = null; },
    onFinish: () => { menyimpan.value = false; },
  });
}

async function hapus(url: string) {
  if (!await tanya('Hapus butir ini beserta penilaiannya?')) return;
  router.delete(url, { preserveScroll: true });
}

const tambah = useForm({ penunjuk: '', rangkuman: '' });

/* Menaikkan draf menjadi tetap: sejak saat itu isinya ikut menentukan
   angka pemenuhan, jadi ia tombol tersendiri dan bukan efek samping
   dari menyunting sesuatu yang lain. */
async function jadikanTetap() {
  if (!await tanya('Jadikan TETAP? Sejak saat itu isinya ikut dihitung di dasbor pemenuhan.')) return;

  router.put(props.tautan.ubah.replace('/ubah', ''), {
    sumber: props.s.sumber, nomor: props.s.nomor, judul: props.s.judul,
    tahun: props.s.tahun, aspek: props.s.aspek ?? '', status: 'Tetap',
  }, { preserveScroll: true });
}

const tahunSalin = ref(String(props.s.tahun + 1));

async function salin() {
  if (!await tanya(`Salin ke tahun ${tahunSalin.value}? Penilaiannya dikosongkan.`)) return;
  router.post(props.tautan.salin, { tahun: tahunSalin.value });
}

const rekap = computed(() => props.s.rekap);

const teksPersen = (p: number | null) => (p === null ? '—' : `${p}%`);

const nada = (p: number | null) =>
  p === null ? '#A8A29E' : p >= 90 ? '#16A34A' : p >= 70 ? '#CA9A04' : '#DC2626';

const warnaStatus = (s: string | null) =>
  s === 'Comply' ? 'is-ok' : s === 'Not Comply' ? 'is-nok' : s === 'N/A' ? 'is-na' : 'is-kosong';

const isian = 'ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition';
const label = 'block text-[10.5px] font-bold uppercase tracking-wide text-stone-500 mb-1';
</script>

<template>
  <Head :title="s.nomor" />

  <div class="space-y-5">
    <!-- Kepala: identitas dan capaiannya, sesuai kepala tabel dokumen aslinya. -->
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <span v-if="s.kode" class="text-[10px] font-bold bg-cam-ink text-white px-2 py-0.5 rounded num">{{ s.kode }}</span>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full text-white" :style="{ background: s.aspekWarna }">
              {{ s.aspekNama }}
            </span>
            <span class="text-[10px] font-semibold bg-stone-100 text-stone-500 px-2 py-0.5 rounded-full">
              {{ opsi.sumberNama[s.sumber] }}
            </span>
            <span v-if="s.status === 'Draf'" class="text-[10px] font-bold bg-amber-300 text-amber-950 px-2 py-0.5 rounded-full">
              Draf — belum ikut dihitung
            </span>
            <span v-if="s.dariAi" class="text-[10px] font-bold bg-stone-200 text-stone-600 px-2 py-0.5 rounded-full">
              Hasil rangkuman otomatis
            </span>
          </div>

          <h2 class="text-[16px] font-extrabold text-cam-ink mt-2.5">{{ s.nomor }}</h2>
          <p class="text-[13px] text-stone-600">{{ s.judul }}</p>
        </div>

        <div class="text-right shrink-0">
          <div class="text-[28px] font-extrabold leading-none num" :style="{ color: nada(rekap.persen) }">
            {{ teksPersen(rekap.persen) }}
          </div>
          <p class="text-[11px] text-stone-400 num mt-1">
            {{ rekap.comply }} comply · {{ rekap.notComply }} NC · {{ rekap.na }} N/A
            <template v-if="rekap.belum"> · {{ rekap.belum }} belum</template>
          </p>
        </div>
      </div>

      <dl class="grid gap-x-5 gap-y-2 mt-4 sm:grid-cols-2 xl:grid-cols-4 text-[12px]">
        <div><dt class="text-[10px] font-bold uppercase tracking-wide text-stone-400">Jenis</dt>
             <dd>{{ s.jenis ?? '—' }}</dd></div>
        <div><dt class="text-[10px] font-bold uppercase tracking-wide text-stone-400">Instansi Penerbit</dt>
             <dd>{{ s.instansi ?? '—' }}</dd></div>
        <div><dt class="text-[10px] font-bold uppercase tracking-wide text-stone-400">Tanggal Terbit</dt>
             <dd class="num">{{ s.tanggalTerbit ?? '—' }}</dd></div>
        <div><dt class="text-[10px] font-bold uppercase tracking-wide text-stone-400">Tahun Evaluasi</dt>
             <dd class="num">{{ s.tahun }}</dd></div>
      </dl>

      <div v-if="s.ruangLingkup" class="mt-3">
        <p class="text-[10px] font-bold uppercase tracking-wide text-stone-400">Ruang Lingkup</p>
        <p class="text-[12px] text-stone-600 leading-relaxed">{{ s.ruangLingkup }}</p>
      </div>

      <div v-if="s.rangkuman" class="mt-2">
        <p class="text-[10px] font-bold uppercase tracking-wide text-stone-400">Rangkuman</p>
        <p class="text-[12px] text-stone-600 leading-relaxed">{{ s.rangkuman }}</p>
      </div>

      <div class="flex flex-wrap items-center gap-2 mt-4">
        <a :href="tautan.register" class="eq-btn-mini">← Register</a>
        <a :href="tautan.ubah" class="eq-btn-mini">✎ Ubah Identitas</a>
        <a :href="tautan.lembar" target="_blank" rel="noopener" class="eq-btn-mini">⎙ Cetak Lembar</a>
        <a v-if="s.urlDokumen" :href="s.urlDokumen" class="eq-btn-mini">▤ {{ s.dokumen }}</a>
        <a v-if="s.urlIso" :href="s.urlIso" class="eq-btn-mini">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
               style="width:13px;height:13px">
            <path d="M12 3.5 20 8v8l-8 4.5L4 16V8z" /><path d="M12 12v8.5" /><path d="m4 8 8 4 8-4" />
          </svg>
          ISO {{ s.isoKode }}
        </a>

        <button v-if="s.status === 'Draf'" type="button" class="eq-btn-utama"
                style="flex:none;padding:7px 14px" @click="jadikanTetap">
          ✓ Jadikan Tetap
        </button>

        <span class="flex items-center gap-1.5 ml-auto">
          <input v-model="tahunSalin" class="ring-focus w-[76px] rounded-lg border border-stone-200 px-2 py-1.5 text-[11.5px] num"
                 aria-label="Tahun tujuan salinan">
          <button type="button" class="eq-btn-mini" @click="salin">⧉ Salin ke Tahun</button>
        </span>
      </div>

      <div v-if="s.berkas.length" class="flex flex-wrap gap-2 mt-3">
        <a v-for="(f, n) in s.berkas" :key="n" :href="f" target="_blank" rel="noopener"
           class="eq-btn-mini">📎 Salinan {{ n + 1 }}</a>
      </div>
    </div>

    <!-- Tabel penilaian. -->
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      <div class="px-5 py-3.5 border-b border-stone-100 flex items-center justify-between gap-2">
        <h3 class="text-[13px] font-bold text-cam-ink">Identifikasi &amp; Evaluasi Pemenuhan</h3>
        <span class="text-[11.5px] text-stone-400 num">{{ butir.length }} butir</span>
      </div>

      <div class="overflow-x-auto">
        <table class="kpt-nilai w-full">
          <thead>
            <tr>
              <th class="kpt-k-no">No</th>
              <th class="kpt-k-tunjuk">Pasal / Ayat</th>
              <th class="kpt-k-isi">Rangkuman Isi</th>
              <th class="kpt-k-terap">Penerapan</th>
              <th class="kpt-k-status">Evaluasi</th>
              <th class="kpt-k-ket">Keterangan &amp; Tindak Lanjut</th>
              <th class="kpt-k-aksi">#</th>
            </tr>
          </thead>

          <tbody>
            <template v-for="(b, i) in butir" :key="b.id">
              <tr :class="{ 'is-lewat': b.lewat }">
                <td class="kpt-k-no num">{{ i + 1 }}</td>
                <td class="kpt-k-tunjuk">{{ b.penunjuk }}</td>
                <td class="kpt-k-isi">{{ b.rangkuman ?? '—' }}</td>
                <td class="kpt-k-terap">{{ b.penerapan ?? '—' }}</td>

                <td class="kpt-k-status">
                  <span class="kpt-status" :class="warnaStatus(b.status)">
                    {{ b.status ?? 'belum dinilai' }}
                  </span>
                </td>

                <td class="kpt-k-ket">
                  <span v-if="b.keterangan">{{ b.keterangan }}</span>

                  <template v-if="b.status === 'Not Comply'">
                    <span v-if="b.tindak" class="block mt-1"><b>Tindak lanjut:</b> {{ b.tindak }}</span>
                    <span v-if="b.pic || b.target" class="block mt-0.5">
                      <b v-if="b.pic">PIC:</b> {{ b.pic }}
                      <template v-if="b.target">
                        · <b>Target:</b>
                        <span :class="b.lewat ? 'text-red-600 font-bold' : ''" class="num">
                          {{ b.target }}<template v-if="b.lewat"> (lewat)</template>
                        </span>
                      </template>
                    </span>
                  </template>

                  <span v-if="!b.keterangan && !b.tindak && !b.pic" class="text-stone-300">—</span>
                </td>

                <td class="kpt-k-aksi">
                  <button type="button" class="kpt-ikon" aria-label="Nilai butir" @click="buka(b)">✎</button>
                  <button type="button" class="kpt-ikon bahaya" aria-label="Hapus butir" @click="hapus(b.urlHapus)">🗑</button>
                </td>
              </tr>

              <!-- Penyunting dibuka di BAWAH barisnya, bukan di jendela
                   melayang: yang menilai butir 37 ingin tetap melihat
                   butir 36 dan 38 sebagai pembanding, dan jendela
                   melayang menutupi keduanya. -->
              <tr v-if="menyunting === b.id" class="kpt-sunting">
                <td colspan="7">
                  <div class="grid gap-3 lg:grid-cols-[180px_1fr_1fr]">
                    <div>
                      <label :class="label" :for="`p-${b.id}`">Pasal / Ayat</label>
                      <input :id="`p-${b.id}`" v-model="draf.penunjuk" :class="isian">

                      <label :class="label" class="mt-3" :for="`s-${b.id}`">Evaluasi Pemenuhan</label>
                      <select :id="`s-${b.id}`" v-model="draf.status" :class="isian">
                        <option value="">— belum dinilai —</option>
                        <option v-for="o in opsi.status" :key="o" :value="o">{{ o }}</option>
                      </select>
                    </div>

                    <div>
                      <label :class="label" :for="`r-${b.id}`">Rangkuman Isi</label>
                      <textarea :id="`r-${b.id}`" v-model="draf.rangkuman" rows="4" :class="isian"
                                placeholder="Kewajiban yang diatur butir ini"></textarea>
                    </div>

                    <div>
                      <label :class="label" :for="`t-${b.id}`">Penerapan</label>
                      <textarea :id="`t-${b.id}`" v-model="draf.penerapan" rows="4" :class="isian"
                                placeholder="Apa yang sudah dikerjakan perusahaan untuk memenuhinya"></textarea>
                    </div>
                  </div>

                  <div class="mt-3">
                    <label :class="label" :for="`k-${b.id}`">Keterangan</label>
                    <textarea :id="`k-${b.id}`" v-model="draf.keterangan" rows="2" :class="isian"
                              placeholder="Bukti, nomor dokumen, atau catatan lain"></textarea>
                  </div>

                  <!-- Hanya saat Not Comply. -->
                  <div v-if="draf.status === 'Not Comply'" class="kpt-tindak">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-red-700 mb-2">
                      Rencana Tindak Lanjut
                    </p>

                    <div class="grid gap-3 sm:grid-cols-[1fr_200px_160px]">
                      <div>
                        <label :class="label" :for="`tl-${b.id}`">Tindak lanjut</label>
                        <textarea :id="`tl-${b.id}`" v-model="draf.tindak_lanjut" rows="2" :class="isian"
                                  placeholder="Apa yang akan dikerjakan untuk menutup celahnya"></textarea>
                      </div>
                      <div>
                        <label :class="label" :for="`pic-${b.id}`">PIC</label>
                        <input :id="`pic-${b.id}`" v-model="draf.pic" :class="isian" placeholder="Departemen — nama">
                      </div>
                      <div>
                        <label :class="label" :for="`tg-${b.id}`">Target selesai</label>
                        <input :id="`tg-${b.id}`" v-model="draf.target" type="date" :class="isian">
                      </div>
                    </div>

                    <p class="text-[11px] text-red-800/80 mt-2">
                      Isian ini yang muncul sebagai daftar kerja di Dasbor Pemenuhan.
                    </p>
                  </div>

                  <div class="flex items-center gap-2 mt-3">
                    <button type="button" class="eq-btn-utama" style="flex:none;padding:7px 16px"
                            :disabled="menyimpan" @click="simpan(b.urlUbah)">
                      {{ menyimpan ? 'Menyimpan…' : 'Simpan Butir' }}
                    </button>
                    <button type="button" class="eq-btn-mini" @click="menyunting = null">Batal</button>
                  </div>
                </td>
              </tr>
            </template>

            <tr v-if="!butir.length">
              <td colspan="7">
                <p class="eq-kosong">
                  <strong>Belum ada satu butir pun.</strong>
                  <span class="block halus">
                    Selama belum dirinci, kewajiban ini tidak ikut menentukan persentase pemenuhan di dasbor.
                  </span>
                </p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <form class="p-4 bg-stone-50/60 border-t border-stone-100 grid gap-2 sm:grid-cols-[200px_1fr_auto] items-end
                   rounded-b-2xl"
            @submit.prevent="tambah.post(tautan.tambahButir, { preserveScroll: true, onSuccess: () => tambah.reset() })">
        <div>
          <label :class="label">Tambah pasal / ayat</label>
          <input v-model="tambah.penunjuk" :class="isian" placeholder="mis. Pasal 3 Ayat (1)">
        </div>
        <div>
          <label :class="label">Rangkuman isi</label>
          <input v-model="tambah.rangkuman" :class="isian" placeholder="Kewajiban yang diatur">
        </div>
        <button type="submit" class="eq-btn-lain" style="flex:none"
                :disabled="tambah.processing || !tambah.penunjuk.trim()">Tambah</button>
      </form>
    </div>

    <p class="text-[11.5px] text-stone-500 leading-relaxed px-1">
      <b>Comply</b> berarti kewajibannya sudah dipenuhi dan buktinya ada.
      <b>Not Comply</b> berarti belum — isi tindak lanjut, PIC, dan targetnya supaya muncul di daftar kerja dasbor.
      <b>N/A</b> berarti butir itu tidak berlaku bagi kegiatan perusahaan, dan karenanya tidak ikut jadi pembagi.
      Butir yang <b>belum dinilai</b> bukan N/A: ia pekerjaan yang belum dikerjakan, dan dihitung terpisah.
    </p>
  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>

<style scoped>
.kpt-nilai { border-collapse: collapse; font-size: 12px; }

.kpt-nilai thead th {
  background: #FAFAF9;
  border-bottom: 1px solid #E7E5E4;
  padding: .55rem .6rem;
  text-align: left;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: .05em;
  text-transform: uppercase;
  color: #78716C;
  white-space: normal;
  vertical-align: bottom;
}

.kpt-nilai tbody td { border-bottom: 1px solid #F5F5F4; padding: .6rem; vertical-align: top; line-height: 1.4; }
.kpt-nilai tbody tr.is-lewat td { background: #FFFAFA; }

.kpt-k-no     { width: 3.5%; text-align: center; color: #A8A29E; }
.kpt-k-tunjuk { width: 11%; font-weight: 700; }
.kpt-k-isi    { width: 24%; }
.kpt-k-terap  { width: 22%; color: #57534E; }
.kpt-k-status { width: 10%; }
.kpt-k-ket    { width: 22%; color: #57534E; }
.kpt-k-aksi   { width: 7.5%; white-space: nowrap; text-align: right; }

.kpt-status {
  display: inline-block; font-size: 9.5px; font-weight: 800;
  padding: .12rem .45rem; border-radius: 999px; white-space: nowrap;
}
.is-ok     { background: #16A34A; color: #fff; }
.is-nok    { background: #DC2626; color: #fff; }
.is-na     { background: #E7E5E4; color: #57534E; }
.is-kosong { background: #FFF2E2; color: #B45309; }

.kpt-ikon {
  border: 1px solid #E7E5E4; background: #fff; border-radius: .5rem;
  width: 1.65rem; height: 1.65rem; font-size: 11px; color: #57534E;
  transition: border-color .16s, color .16s, background-color .16s;
}
.kpt-ikon:hover { border-color: #DC6E00; color: #DC6E00; }
.kpt-ikon.bahaya:hover { border-color: #DC2626; color: #DC2626; background: #FEF2F2; }

.kpt-sunting > td { background: #FAFAF9; border-bottom: 2px solid #E7E5E4; padding: 1rem; }

.kpt-tindak {
  margin-top: .8rem; padding: .8rem;
  border: 1px solid #FECACA; border-radius: .9rem; background: #FFFBFB;
}

/* Di layar sempit kolom penerapan dilepas lebih dulu: ia yang paling
   panjang, dan isinya tetap terbaca utuh begitu penyuntingnya dibuka. */
@media (max-width: 78rem) {
  .kpt-k-terap { display: none; }
  .kpt-k-isi   { width: 34%; }
}

@media (max-width: 56rem) {
  .kpt-k-ket { display: none; }
  .kpt-k-isi { width: 48%; }
}
</style>

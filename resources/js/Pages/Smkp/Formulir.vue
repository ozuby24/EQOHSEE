<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import KepalaAudit from '../../Components/Smkp/KepalaAudit.vue';
import Ikon from '../../Components/Smkp/Ikon.vue';
import type { RingkasAudit } from '../../smkp/types';

interface Baris { elemen: string; sub: string; subsub?: string; keterangan?: string; uraian?: string }
interface Kode { kode: string; nama: string }
interface Elemen extends Kode { sub: Array<Kode & { subsub: Kode[] }> }

const props = defineProps<{
  audit: RingkasAudit;
  kesesuaian: Baris[];
  rekomendasi: Baris[];
  kriteria: Elemen[];
  ringkas: {
    rencana: { terisi: number; total: number };
    penilaian: { terisi: number; total: number };
    kesesuaian: number;
    rekomendasi: number;
    ketidaksesuaian: number;
    kategori: Record<string, number>;
    rtl: number;
  };
  bisaSunting: boolean;
}>();

const id = props.audit.id;
const cetak = (jenis: string) => `/smkp/${id}/formulir/${jenis}/cetak`;

const kategoriTeks = computed(() => {
  const k = props.ringkas.kategori;
  return [['kritikal', 'kritikal'], ['mayor', 'mayor'], ['minor', 'minor']]
    .filter(([kunci]) => k[kunci]).map(([kunci, l]) => `${k[kunci]} ${l}`).join(' · ') || 'belum ada';
});

const FORMULIR = computed(() => [
  { no: 1, judul: 'Rencana Audit', ket: 'Jenis & kriteria audit, ruang lingkup, tim, susunan kegiatan, metode & sampel per kriteria, pengesahan.',
    status: `${props.ringkas.rencana.terisi}/${props.ringkas.rencana.total} komponen`, lengkap: props.ringkas.rencana.terisi === props.ringkas.rencana.total,
    isi: `/smkp/${id}/rencana`, cetak: `/smkp/${id}/rencana/cetak` },
  { no: 2, judul: 'Formulir Kriteria Audit (Penilaian SMKP)', ket: 'Nilai tiap sub-elemen dan sub-sub-elemen, total & persentase nilai elemen berbobot.',
    status: `${props.ringkas.penilaian.terisi}/${props.ringkas.penilaian.total} butir dinilai`, lengkap: props.ringkas.penilaian.total > 0 && props.ringkas.penilaian.terisi === props.ringkas.penilaian.total,
    isi: `/smkp/${id}/elemen/I`, cetak: cetak('penilaian') },
  { no: 3, judul: 'Formulir Kesesuaian', ket: 'Uraian kesesuaian per elemen, sub-elemen & sub-sub-elemen, ditandatangani auditor dan auditi.',
    status: `${props.ringkas.kesesuaian} uraian`, lengkap: props.ringkas.kesesuaian > 0, isi: '#kesesuaian', cetak: cetak('kesesuaian') },
  { no: 4, judul: 'Formulir Ketidaksesuaian & Tindak Lanjut', ket: 'Satu formulir per temuan: uraian masalah, lokasi, bukti, kategori, tindak lanjut auditi, ulasan auditor.',
    status: `${props.ringkas.ketidaksesuaian} temuan`, lengkap: props.ringkas.ketidaksesuaian > 0, isi: `/smkp/${id}/temuan`, cetak: cetak('ketidaksesuaian') },
  { no: 5, judul: 'Rekapitulasi Ketidaksesuaian', ket: 'Daftar seluruh ketidaksesuaian beserta jumlah temuan kritikal, mayor, dan minor.',
    status: kategoriTeks.value, lengkap: props.ringkas.ketidaksesuaian > 0, isi: null, cetak: cetak('rekapitulasi') },
  { no: 6, judul: 'Rekomendasi & Peluang untuk Perbaikan', ket: 'Opsional — rekomendasi dan OFI per elemen & sub-elemen.',
    status: `${props.ringkas.rekomendasi} uraian`, lengkap: props.ringkas.rekomendasi > 0, isi: '#rekomendasi', cetak: cetak('rekomendasi') },
  { no: 7, judul: 'Rencana Tindak Lanjut Ketidaksesuaian', ket: 'Akar permasalahan, tindakan koreksi, penanggung jawab, dan batas waktu tiap ketidaksesuaian.',
    status: `${props.ringkas.rtl}/${props.ringkas.ketidaksesuaian} terisi`, lengkap: props.ringkas.ketidaksesuaian > 0 && props.ringkas.rtl === props.ringkas.ketidaksesuaian,
    isi: `/smkp/${id}/temuan`, cetak: cetak('tindak-lanjut') },
]);

/* ───────── Editor kesesuaian & rekomendasi ───────── */
const form = useForm({
  kesesuaian: props.kesesuaian.map((b) => ({ elemen: b.elemen, sub: b.sub, subsub: b.subsub ?? '', keterangan: b.keterangan ?? '' })),
  rekomendasi: props.rekomendasi.map((b) => ({ elemen: b.elemen, sub: b.sub, subsub: b.subsub ?? '', uraian: b.uraian ?? '' })),
});

const subDari = (elemen: string) => props.kriteria.find((e) => e.kode === elemen)?.sub ?? [];
const subsubDari = (elemen: string, sub: string) => subDari(elemen).find((s) => s.kode === sub)?.subsub ?? [];
const tambahK = () => form.kesesuaian.push({ elemen: '', sub: '', subsub: '', keterangan: '' });
const tambahR = () => form.rekomendasi.push({ elemen: '', sub: '', subsub: '', uraian: '' });

function simpan() {
  form.post(`/smkp/${id}/formulir`, { preserveScroll: true });
}
</script>

<template>
  <div class="smkp-ui">
    <Head title="Formulir Audit" />
    <KepalaAudit :audit="audit" aktif="formulir" :bisa-sunting="bisaSunting" />

    <section class="kartu">
      <div class="kartu-kepala">
        <div>
          <h2 class="kartu-judul">Paket Formulir Audit SMKP</h2>
          <p class="kartu-sub">Mengikuti lampiran Kepdirjen Minerba No. 185.K/37.04/DJB/2019. Data diambil dari isian audit — tidak perlu mengetik ulang.</p>
        </div>
      </div>
      <ol class="divide-y divide-slate-100">
        <li v-for="f in FORMULIR" :key="f.no" class="grid grid-cols-[36px_1fr] md:grid-cols-[36px_1fr_190px_176px] items-center gap-x-4 gap-y-2 px-5 sm:px-6 py-4">
          <span class="grid place-items-center w-9 h-9 rounded-lg bg-navy-50 text-navy-800 text-[13px] font-bold tabular-nums">{{ f.no }}</span>
          <div class="min-w-0">
            <p class="text-[14px] font-semibold text-navy-900">{{ f.judul }}</p>
            <p class="text-[12.5px] text-slate-500 leading-snug">{{ f.ket }}</p>
          </div>
          <span class="col-start-2 md:col-start-auto inline-flex items-center gap-1.5 text-[12.5px] font-medium" :class="f.lengkap ? 'text-hijau-700' : 'text-slate-500'">
            <Ikon :nama="f.lengkap ? 'centang-bulat' : 'jam'" class="w-4 h-4 shrink-0" />{{ f.status }}
          </span>
          <div class="col-start-2 md:col-start-auto flex gap-2 md:justify-end">
            <component :is="f.isi.startsWith('#') ? 'a' : Link" v-if="f.isi" :href="f.isi" class="btn-garis btn-kecil"><Ikon nama="pensil" class="w-4 h-4" /> Isi</component>
            <a :href="f.cetak" target="_blank" rel="noopener" class="btn-garis btn-kecil"><Ikon nama="cetak" class="w-4 h-4" /> Cetak</a>
          </div>
        </li>
      </ol>
    </section>

    <form class="mt-5 grid xl:grid-cols-2 gap-5" @submit.prevent="simpan">
      <section id="kesesuaian" class="kartu scroll-mt-24">
        <div class="kartu-kepala">
          <div><h2 class="kartu-judul">Uraian Kesesuaian</h2><p class="kartu-sub">Formulir 3 — praktik terbaik yang sesuai kriteria, per elemen, sub-elemen & sub-sub-elemen.</p></div>
          <button v-if="bisaSunting" type="button" class="btn-garis btn-kecil" @click="tambahK"><Ikon nama="tambah" class="w-4 h-4" /> Tambah</button>
        </div>
        <fieldset :disabled="!bisaSunting">
          <ol v-if="form.kesesuaian.length" class="divide-y divide-slate-100">
            <li v-for="(b, i) in form.kesesuaian" :key="i" class="px-5 sm:px-6 py-4 grid sm:grid-cols-2 gap-3">
              <div>
                <label class="label" :for="`k-el-${i}`">Elemen</label>
                <select :id="`k-el-${i}`" v-model="b.elemen" class="input" @change="b.sub = ''; b.subsub = ''">
                  <option value="" disabled>Pilih elemen</option>
                  <option v-for="e in kriteria" :key="e.kode" :value="e.kode">{{ e.kode }} — {{ e.nama }}</option>
                </select>
              </div>
              <div>
                <label class="label" :for="`k-sub-${i}`">Sub elemen</label>
                <select :id="`k-sub-${i}`" v-model="b.sub" class="input" :disabled="!b.elemen" @change="b.subsub = ''">
                  <option value="">— Seluruh elemen —</option>
                  <option v-for="s in subDari(b.elemen)" :key="s.kode" :value="s.kode">{{ s.kode }} — {{ s.nama }}</option>
                </select>
              </div>
              <div v-if="subsubDari(b.elemen, b.sub).length" class="sm:col-span-2">
                <label class="label" :for="`k-ss-${i}`">Sub-sub elemen</label>
                <select :id="`k-ss-${i}`" v-model="b.subsub" class="input">
                  <option value="">— Seluruh sub elemen —</option>
                  <option v-for="s in subsubDari(b.elemen, b.sub)" :key="s.kode" :value="s.kode">{{ s.kode }} — {{ s.nama }}</option>
                </select>
              </div>
              <div class="sm:col-span-2">
                <div class="flex items-center justify-between"><label class="label" :for="`k-ket-${i}`">Keterangan</label>
                  <button v-if="bisaSunting" type="button" class="text-[12px] font-semibold text-rose-600 hover:text-rose-700" @click="form.kesesuaian.splice(i, 1)">Hapus</button></div>
                <textarea :id="`k-ket-${i}`" v-model="b.keterangan" rows="2" class="input" placeholder="Bukti kesesuaian: dokumen, rekaman, atau pengamatan" />
                <p v-if="(form.errors as Record<string, string>)[`kesesuaian.${i}.keterangan`]" class="galat">{{ (form.errors as Record<string, string>)[`kesesuaian.${i}.keterangan`] }}</p>
              </div>
            </li>
          </ol>
          <p v-else class="px-6 py-10 text-center text-[13px] text-slate-500">Belum ada uraian kesesuaian.</p>
        </fieldset>
      </section>

      <section id="rekomendasi" class="kartu scroll-mt-24">
        <div class="kartu-kepala">
          <div><h2 class="kartu-judul">Rekomendasi &amp; Peluang untuk Perbaikan</h2><p class="kartu-sub">Formulir 6 — opsional, tidak wajib diisi setiap auditor.</p></div>
          <button v-if="bisaSunting" type="button" class="btn-garis btn-kecil" @click="tambahR"><Ikon nama="tambah" class="w-4 h-4" /> Tambah</button>
        </div>
        <fieldset :disabled="!bisaSunting">
          <ol v-if="form.rekomendasi.length" class="divide-y divide-slate-100">
            <li v-for="(b, i) in form.rekomendasi" :key="i" class="px-5 sm:px-6 py-4 grid sm:grid-cols-2 gap-3">
              <div>
                <label class="label" :for="`r-el-${i}`">Elemen</label>
                <select :id="`r-el-${i}`" v-model="b.elemen" class="input" @change="b.sub = ''; b.subsub = ''">
                  <option value="" disabled>Pilih elemen</option>
                  <option v-for="e in kriteria" :key="e.kode" :value="e.kode">{{ e.kode }} — {{ e.nama }}</option>
                </select>
              </div>
              <div>
                <label class="label" :for="`r-sub-${i}`">Sub elemen</label>
                <select :id="`r-sub-${i}`" v-model="b.sub" class="input" :disabled="!b.elemen" @change="b.subsub = ''">
                  <option value="">— Seluruh elemen —</option>
                  <option v-for="s in subDari(b.elemen)" :key="s.kode" :value="s.kode">{{ s.kode }} — {{ s.nama }}</option>
                </select>
              </div>
              <div v-if="subsubDari(b.elemen, b.sub).length" class="sm:col-span-2">
                <label class="label" :for="`r-ss-${i}`">Sub-sub elemen</label>
                <select :id="`r-ss-${i}`" v-model="b.subsub" class="input">
                  <option value="">— Seluruh sub elemen —</option>
                  <option v-for="s in subsubDari(b.elemen, b.sub)" :key="s.kode" :value="s.kode">{{ s.kode }} — {{ s.nama }}</option>
                </select>
              </div>
              <div class="sm:col-span-2">
                <div class="flex items-center justify-between"><label class="label" :for="`r-ur-${i}`">Rekomendasi dan peluang untuk perbaikan</label>
                  <button v-if="bisaSunting" type="button" class="text-[12px] font-semibold text-rose-600 hover:text-rose-700" @click="form.rekomendasi.splice(i, 1)">Hapus</button></div>
                <textarea :id="`r-ur-${i}`" v-model="b.uraian" rows="2" class="input" />
              </div>
            </li>
          </ol>
          <p v-else class="px-6 py-10 text-center text-[13px] text-slate-500">Belum ada rekomendasi atau OFI.</p>
        </fieldset>
      </section>

      <div v-if="bisaSunting" class="xl:col-span-2 sticky bottom-4 z-10 flex flex-wrap items-center justify-between gap-3 rounded-kartu bg-navy-900 text-white px-5 py-3 shadow-angkat">
        <p class="text-[13px] text-white/80">{{ form.isDirty ? 'Ada perubahan yang belum disimpan.' : 'Kesesuaian dan rekomendasi tersimpan.' }}</p>
        <button type="submit" class="btn-aksen btn-kecil" :disabled="form.processing || !form.isDirty"><Ikon nama="simpan" class="w-4 h-4" /> Simpan formulir</button>
      </div>
    </form>
  </div>
</template>

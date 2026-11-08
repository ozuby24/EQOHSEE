<script setup lang="ts">
/**
 * Isian Laporan Audit (format Kepdirjen 185.K/37.04/DJB/2019) untuk bagian
 * yang tidak dapat diturunkan dari data audit. Bagian lain — tim auditor,
 * gambaran penerapan per elemen, praktik terbaik, temuan, dan tingkat
 * pencapaian — tersusun otomatis di halaman cetak.
 */
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import KepalaAudit from '../../Components/Smkp/KepalaAudit.vue';
import Ikon from '../../Components/Smkp/Ikon.vue';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
import type { RingkasAudit } from '../../smkp/types';

/* Penegasan memakai dialog EQOHSEE (useDialog), pengganti window.confirm Aurorasys:
   confirm() bawaan peramban dibungkam pada webview ponsel dan memulangkan false. */
const { dialog, tanya, batal, lanjut } = useDialog();

interface Isian {
  status_perusahaan: string; sk_izin: string; wiup: string; legalitas: string; kegiatan: string; penerapan: string;
  satuan_produksi: string; produksi: Array<{ periode: string; jumlah: string }>; pekerja: Array<{ jabatan: string; jumlah: string }>;
  peralatan: Array<{ jenis: string; tipe: string; produktivitas: string; jumlah: string }>;
  area: string[]; distribusi: string; tempat: string; tanggal: string;
}

const props = defineProps<{
  audit: RingkasAudit;
  isian: Isian;
  bawaan: { penerapan: string; distribusi: string };
  otomatis: { periode: string; alamatSite: string; jumlahPekerja: string; tim: number; praktik: number; temuan: number; dinilai: number; berlaku: number };
  bisaSunting: boolean;
}>();

/* Daftar kosong mendapat satu baris isian agar langsung dapat diketik; baris kosong dibuang server. */
const salin = JSON.parse(JSON.stringify(props.isian)) as Isian;
if (!salin.produksi.length) salin.produksi.push({ periode: '', jumlah: '' });
if (!salin.pekerja.length) salin.pekerja.push({ jabatan: '', jumlah: '' });
if (!salin.peralatan.length) salin.peralatan.push({ jenis: '', tipe: '', produktivitas: '', jumlah: '' });
if (!salin.area.length) salin.area.push('');
const form = useForm(salin);

const BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
function isiBulan() {
  const ada = form.produksi.filter((r) => r.periode.trim() || r.jumlah.trim());
  form.produksi = BULAN.map((b) => ada.find((r) => r.periode.trim().toLowerCase() === b.toLowerCase()) ?? { periode: b, jumlah: '' });
}
const totalPekerja = computed(() => form.pekerja.reduce((n, r) => n + (Number(r.jumlah.replace(/\D/g, '')) || 0), 0));

function simpan() {
  form.post(`/smkp/${props.audit.id}/laporan`, { preserveScroll: true });
}

// Cetak & Word memakai isian tersimpan; perubahan yang belum disimpan hilang saat halaman ditinggalkan.
async function bukaHasil(e: MouseEvent) {
  if (!form.isDirty) return;
  e.preventDefault();
  const tujuan = (e.currentTarget as HTMLAnchorElement).href;
  if (await tanya({
    judul: 'Lanjutkan tanpa menyimpan?',
    pesan: 'Ada perubahan isian laporan yang belum disimpan dan tidak akan ikut ke dokumen. Tetap lanjutkan tanpa menyimpan?',
    labelAksi: 'Tetap lanjutkan',
  })) window.location.href = tujuan;
}

const bagian = 'kartu p-5 sm:p-6';
const hapus = 'p-1.5 rounded-md text-slate-300 hover:text-rose-600 hover:bg-rose-50';
const kepala = 'text-left text-[10.5px] font-semibold uppercase tracking-[0.08em] text-slate-400';
</script>

<template>
  <div class="smkp-ui">
    <Head title="Laporan Audit" />
    <KepalaAudit :audit="audit" aktif="laporan" :bisa-sunting="bisaSunting" />

    <form class="grid xl:grid-cols-[1fr_300px] gap-5 items-start" @submit.prevent="simpan">
      <fieldset :disabled="!bisaSunting" class="space-y-5 min-w-0">
        <!-- 2.1 -->
        <section :class="bagian">
          <h2 class="kartu-judul">Domisili dan Legalitas</h2>
          <p class="kartu-sub">Laporan bagian 2.1. Alamat kantor pusat & site serta jenis perizinan diambil dari Tahap I.</p>
          <div class="mt-5 grid sm:grid-cols-3 gap-4">
            <div><label class="label" for="l-status">Status perusahaan</label><input id="l-status" v-model="form.status_perusahaan" class="input" maxlength="150" placeholder="mis. Penanaman Modal Dalam Negeri (PMDN)"></div>
            <div><label class="label" for="l-sk">Nomor SK perizinan</label><input id="l-sk" v-model="form.sk_izin" class="input" maxlength="200"></div>
            <div><label class="label" for="l-wiup">WIUP</label><input id="l-wiup" v-model="form.wiup" class="input tabular-nums" maxlength="100"></div>
            <div class="sm:col-span-3">
              <label class="label" for="l-legal">Uraian domisili & legalitas</label>
              <textarea id="l-legal" v-model="form.legalitas" rows="6" class="input" maxlength="10000"
                        placeholder="Badan usaha, lokasi, riwayat perizinan, luas wilayah, dokumen lingkungan (AMDAL), dan lainnya." />
              <p class="bantu">Pisahkan paragraf dengan satu baris kosong.</p>
            </div>
          </div>
        </section>

        <!-- 2.2 -->
        <section :class="bagian">
          <h2 class="kartu-judul">Kegiatan Pertambangan</h2>
          <p class="kartu-sub">Laporan bagian 2.2: kegiatan, jumlah produksi, pekerja, dan peralatan.</p>
          <div class="mt-5 space-y-5">
            <div>
              <label class="label" for="l-kegiatan">Uraian kegiatan pertambangan</label>
              <textarea id="l-kegiatan" v-model="form.kegiatan" rows="6" class="input" maxlength="10000"
                        placeholder="Kegiatan operasional: land clearing, pengupasan tanah penutup, pemboran & peledakan, pemuatan & pengangkutan, reklamasi, dan lainnya." />
            </div>

            <div>
              <div class="flex flex-wrap items-end justify-between gap-2">
                <p class="text-[13px] font-semibold text-navy-900">Jumlah produksi</p>
                <div class="flex items-end gap-2">
                  <div><label class="label" for="l-satuan">Satuan</label><input id="l-satuan" v-model="form.satuan_produksi" class="input-kecil w-44" maxlength="60" placeholder="Metrik Ton (MT)"></div>
                  <button type="button" class="btn-garis btn-kecil" @click="isiBulan"><Ikon nama="kalender" class="w-4 h-4" /> 12 bulan</button>
                </div>
              </div>
              <table class="mt-2 w-full text-[12.5px]">
                <thead><tr :class="kepala"><th class="py-2 pr-2">Periode</th><th class="py-2 px-2 w-48">Jumlah</th><th class="w-10" /></tr></thead>
                <tbody>
                  <tr v-for="(r, i) in form.produksi" :key="i" class="border-t border-slate-100">
                    <td class="py-1.5 pr-2"><input v-model="r.periode" class="input-kecil" maxlength="60" :aria-label="`Periode produksi ${i + 1}`"></td>
                    <td class="py-1.5 px-2"><input v-model="r.jumlah" class="input-kecil tabular-nums" maxlength="60" :aria-label="`Jumlah produksi ${i + 1}`"></td>
                    <td class="py-1.5 text-center"><button type="button" :class="hapus" aria-label="Hapus baris produksi" @click="form.produksi.splice(i, 1)"><Ikon nama="tutup" class="w-4 h-4" /></button></td>
                  </tr>
                </tbody>
              </table>
              <button v-if="form.produksi.length < 24" type="button" class="mt-2 btn-garis btn-kecil" @click="form.produksi.push({ periode: '', jumlah: '' })"><Ikon nama="tambah" class="w-4 h-4" /> Baris</button>
            </div>

            <div>
              <p class="text-[13px] font-semibold text-navy-900">Jumlah pekerja tambang <span class="font-normal text-slate-500">— total {{ totalPekerja }} orang</span></p>
              <table class="mt-2 w-full text-[12.5px]">
                <thead><tr :class="kepala"><th class="py-2 pr-2">Jabatan</th><th class="py-2 px-2 w-40">Jumlah (orang)</th><th class="w-10" /></tr></thead>
                <tbody>
                  <tr v-for="(r, i) in form.pekerja" :key="i" class="border-t border-slate-100">
                    <td class="py-1.5 pr-2"><input v-model="r.jabatan" class="input-kecil" maxlength="150" placeholder="mis. Pengawas" :aria-label="`Jabatan ${i + 1}`"></td>
                    <td class="py-1.5 px-2"><input v-model="r.jumlah" inputmode="numeric" class="input-kecil tabular-nums" maxlength="30" :aria-label="`Jumlah pekerja ${i + 1}`"></td>
                    <td class="py-1.5 text-center"><button type="button" :class="hapus" aria-label="Hapus baris pekerja" @click="form.pekerja.splice(i, 1)"><Ikon nama="tutup" class="w-4 h-4" /></button></td>
                  </tr>
                </tbody>
              </table>
              <button v-if="form.pekerja.length < 40" type="button" class="mt-2 btn-garis btn-kecil" @click="form.pekerja.push({ jabatan: '', jumlah: '' })"><Ikon nama="tambah" class="w-4 h-4" /> Jabatan</button>
              <p v-if="otomatis.jumlahPekerja" class="bantu">Tahap I mencatat {{ otomatis.jumlahPekerja }} pekerja auditi.</p>
            </div>

            <div>
              <p class="text-[13px] font-semibold text-navy-900">Peralatan tambang</p>
              <div class="mt-2 gulir-x scrollbar-tipis">
                <table class="w-full min-w-[620px] text-[12.5px]">
                  <thead><tr :class="kepala"><th class="py-2 pr-2">Jenis</th><th class="py-2 px-2">Tipe</th><th class="py-2 px-2 w-40">Produktivitas</th><th class="py-2 px-2 w-24">Jumlah</th><th class="w-10" /></tr></thead>
                  <tbody>
                    <tr v-for="(r, i) in form.peralatan" :key="i" class="border-t border-slate-100">
                      <td class="py-1.5 pr-2"><input v-model="r.jenis" class="input-kecil" maxlength="150" placeholder="Excavator" :aria-label="`Jenis peralatan ${i + 1}`"></td>
                      <td class="py-1.5 px-2"><input v-model="r.tipe" class="input-kecil" maxlength="150" placeholder="PC 500" :aria-label="`Tipe peralatan ${i + 1}`"></td>
                      <td class="py-1.5 px-2"><input v-model="r.produktivitas" class="input-kecil" maxlength="100" placeholder="395 Ton/Jam" :aria-label="`Produktivitas ${i + 1}`"></td>
                      <td class="py-1.5 px-2"><input v-model="r.jumlah" class="input-kecil tabular-nums" maxlength="30" :aria-label="`Jumlah alat ${i + 1}`"></td>
                      <td class="py-1.5 text-center"><button type="button" :class="hapus" aria-label="Hapus peralatan" @click="form.peralatan.splice(i, 1)"><Ikon nama="tutup" class="w-4 h-4" /></button></td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <button v-if="form.peralatan.length < 80" type="button" class="mt-2 btn-garis btn-kecil" @click="form.peralatan.push({ jenis: '', tipe: '', produktivitas: '', jumlah: '' })"><Ikon nama="tambah" class="w-4 h-4" /> Peralatan</button>
            </div>
          </div>
        </section>

        <!-- 2.3 -->
        <section :class="bagian">
          <h2 class="kartu-judul">Gambaran Umum Penerapan SMKP Minerba</h2>
          <p class="kartu-sub">Laporan bagian 2.3. Ringkasan per elemen disusun otomatis dari uraian tingkat nilai Kepdirjen 185 sesuai hasil Penilaian.</p>
          <div class="mt-5">
            <label class="label" for="l-penerapan">Pengantar <span class="font-normal text-slate-400">(opsional)</span></label>
            <textarea id="l-penerapan" v-model="form.penerapan" rows="4" class="input" maxlength="5000" :placeholder="bawaan.penerapan" />
            <p class="bantu">Kosong: memakai teks bawaan di atas.</p>
          </div>
        </section>

        <!-- 3 -->
        <section :class="bagian">
          <h2 class="kartu-judul">Lingkup Audit</h2>
          <p class="kartu-sub">Laporan bagian 3: area kegiatan yang menjadi objek audit.
            Periode penilaian: <b>{{ otomatis.periode || audit.periode }}</b> (<Link :href="`/smkp/${audit.id}/tahap-1`" class="font-semibold text-navy-700 hover:underline">ubah di Tahap I</Link>).</p>
          <div class="mt-4 space-y-2">
            <div v-for="(_, i) in form.area" :key="i" class="flex items-center gap-1.5">
              <span class="block w-9 shrink-0 text-[11.5px] text-slate-400 tabular-nums">3.{{ i + 1 }}.</span>
              <input v-model="form.area[i]" class="input-kecil" maxlength="300" placeholder="mis. Area Front Penambangan" :aria-label="`Area ${i + 1}`">
              <button type="button" :class="hapus" :aria-label="`Hapus area ${i + 1}`" @click="form.area.splice(i, 1)"><Ikon nama="tutup" class="w-4 h-4" /></button>
            </div>
          </div>
          <button v-if="form.area.length < 40" type="button" class="mt-2 btn-garis btn-kecil" @click="form.area.push('')"><Ikon nama="tambah" class="w-4 h-4" /> Area</button>
        </section>

        <!-- Penutup -->
        <section :class="bagian">
          <h2 class="kartu-judul">Penutup & Distribusi</h2>
          <p class="kartu-sub">Tempat dan tanggal laporan dibuat. Tanda tangan tim auditor dibubuhkan di halaman cetak.</p>
          <div class="mt-5 grid sm:grid-cols-2 gap-4">
            <div><label class="label" for="l-tempat">Tempat</label><input id="l-tempat" v-model="form.tempat" class="input" maxlength="100" placeholder="mis. Padang"></div>
            <div><label class="label" for="l-tanggal">Tanggal laporan</label><input id="l-tanggal" v-model="form.tanggal" type="date" class="input"></div>
            <div class="sm:col-span-2">
              <label class="label" for="l-distribusi">Distribusi laporan</label>
              <textarea id="l-distribusi" v-model="form.distribusi" rows="4" class="input" maxlength="3000" :placeholder="bawaan.distribusi" />
              <p class="bantu">Satu penerima per baris. Kosong: memakai daftar distribusi Kepdirjen 185 di atas.</p>
            </div>
          </div>
        </section>
      </fieldset>

      <aside class="xl:sticky xl:top-24 space-y-4">
        <div class="kartu p-5">
          <p class="text-[13px] font-bold text-navy-900">Tersusun otomatis</p>
          <ul class="mt-3 space-y-2 text-[12.5px] text-slate-600">
            <li class="flex items-start gap-2"><Ikon nama="users" class="mt-0.5 w-4 h-4 text-slate-400" /><span><b class="tabular-nums">{{ otomatis.tim }}</b> anggota tim auditor — <Link :href="`/smkp/${audit.id}/rencana#k-tugas`" class="text-navy-700 hover:underline">Rencana Audit</Link></span></li>
            <li class="flex items-start gap-2"><Ikon nama="clipboard-check" class="mt-0.5 w-4 h-4 text-slate-400" /><span><b class="tabular-nums">{{ otomatis.dinilai }}/{{ otomatis.berlaku }}</b> butir dinilai — gambaran penerapan & tingkat pencapaian</span></li>
            <li class="flex items-start gap-2"><Ikon nama="penghargaan" class="mt-0.5 w-4 h-4 text-slate-400" /><span><b class="tabular-nums">{{ otomatis.praktik }}</b> praktik terbaik — <Link :href="`/smkp/${audit.id}/formulir`" class="text-navy-700 hover:underline">Formulir Kesesuaian</Link></span></li>
            <li class="flex items-start gap-2"><Ikon nama="peringatan" class="mt-0.5 w-4 h-4 text-slate-400" /><span><b class="tabular-nums">{{ otomatis.temuan }}</b> ketidaksesuaian — <Link :href="`/smkp/${audit.id}/temuan`" class="text-navy-700 hover:underline">Temuan & CAR</Link></span></li>
          </ul>
          <a :href="`/smkp/${audit.id}/laporan`" class="mt-4 btn-garis btn-kecil w-full" @click="bukaHasil"><Ikon nama="cetak" class="w-4 h-4" /> Cetak Laporan Audit</a>
          <a :href="`/smkp/${audit.id}/laporan?unduh=word`" class="mt-2 btn-garis btn-kecil w-full" @click="bukaHasil"><Ikon nama="unduh" class="w-4 h-4" /> Unduh Word (.docx)</a>
        </div>
        <div v-if="bisaSunting" class="kartu p-5">
          <p class="text-[12.5px] text-slate-500" :class="form.isDirty ? 'text-amber-700 font-semibold' : ''">{{ form.isDirty ? 'Ada perubahan yang belum disimpan.' : 'Seluruh isian laporan tersimpan.' }}</p>
          <p v-if="Object.keys(form.errors).length" class="galat mt-2">{{ Object.values(form.errors)[0] }}</p>
          <button class="mt-3 btn-utama w-full" :disabled="form.processing || !form.isDirty"><Ikon nama="simpan" class="w-4 h-4" /> {{ form.processing ? 'Menyimpan…' : 'Simpan Isian Laporan' }}</button>
        </div>
      </aside>
    </form>
    <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
  </div>
</template>

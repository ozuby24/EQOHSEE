<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import KepalaAudit from '../../Components/Smkp/KepalaAudit.vue';
import Ikon from '../../Components/Smkp/Ikon.vue';
import { angka } from '../../smkp/lib/format';
import type { RingkasAudit } from '../../smkp/types';

interface Permulaan {
  tanggal_kontak: string; media_kontak: string; wakil_auditi: string; jabatan_wakil: string; surat_nomor: string; surat_tanggal: string;
  izin: string; alamat: string; periode: string; sifat_kontak: string; tanggal_kelayakan: string; layak: string; bukti_integritas: string; tanggal_ba: string;
  alamat_site: string; sk_auditor: string; form_rapat: string;
  jumlah_pekerja: string; kelas_risiko: string; mandays_dasar: string; jumlah_auditor: string; penyesuaian: string; kesimpulan: string;
  kelayakan: Record<string, string>; faktor: Record<string, boolean>;
}
interface Persiapan {
  tren: Record<string, string[]>;
  peralatan: Array<{ jenis: string; pa: string; ma: string }>;
  kli: Array<{ indikator: string; status: string }>;
  audit_lalu: Record<string, string>;
  masukan: Array<{ sumber: string; isi: string }>;
}

const props = defineProps<{
  audit: RingkasAudit;
  isian: { permulaan: Permulaan; kinerja: Record<string, string>; kecukupan: Record<string, { status: string; ket: string }>; persiapan: Persiapan };
  elemen: Array<{ kode: string; nama: string }>;
  kelayakan: Record<string, string>;
  faktor: Record<string, string>;
  kinerja: Record<string, { label: string; satuan: string }>;
  kelasRisiko: string[];
  mandays: Record<string, number>;
  hariTetap: { total: number; penyesuaian: number; aturan?: string } | null;
  rekap: { lengkap: number; tidak: number; belum: number; total: number; siap: boolean };
  nilaiStatus: { lengkap: string; tidak: string };
  bisaSunting: boolean;
  dataPerusahaan: { izin: string; alamat: string; alamatSite: string };
  tren: Record<string, { judul: string; kolom: string }>;
  auditLalu: Record<string, string>;
  buktiIntegritas: string;
  kliBawaan: string[];
  nomorRapat: string;
  jumlahRisiko: { present: number; future: number };
  saranAuditLalu: { tahun: number; isi: Record<string, string> } | null;
}>();

/* Daftar kosong mendapat satu baris isian agar langsung dapat diketik; baris kosong dibuang server. */
const salin = JSON.parse(JSON.stringify(props.isian)) as typeof props.isian;
for (const k of Object.keys(props.tren)) if (!salin.persiapan.tren[k]?.length) salin.persiapan.tren[k] = [''];
if (!salin.persiapan.peralatan.length) salin.persiapan.peralatan.push({ jenis: '', pa: '', ma: '' });
if (!salin.persiapan.kli.length) salin.persiapan.kli.push({ indikator: '', status: '' });
if (!salin.persiapan.masukan.length) salin.persiapan.masukan.push({ sumber: '', isi: '' });
const form = useForm(salin);
const MAKS_BARIS = 20;
// Baris baru mewarisi sumber baris sebelumnya — masukan sekelompok diketik berurutan.
function tambahMasukan() {
  const akhir = form.persiapan.masukan[form.persiapan.masukan.length - 1];
  form.persiapan.masukan.push({ sumber: akhir?.sumber ?? '', isi: '' });
}
const SUMBER_MASUKAN = computed(() => [
  `Masukan Audit Internal SMKP Periode ${props.audit.tahun - 1}`,
  `Masukan dari Manajemen ${props.audit.perusahaan.nama}`,
  'Perintah, Larangan, Petunjuk Inspektur Tambang (Buku Tambang)',
  'Masukan Pembinaan Direktorat Teknik dan Lingkungan Minerba',
  'Masukan Komite Keselamatan Pertambangan',
  'Masukan Pemegang IUP selaku Pemberi Kerja',
  'Masukan Serikat Pekerja',
  'Pengaduan Masyarakat',
]);
function pakaiKliBawaan() {
  const ada = new Set(form.persiapan.kli.map((k) => k.indikator.trim().toLowerCase()));
  form.persiapan.kli = form.persiapan.kli.filter((k) => k.indikator.trim() || k.status.trim());
  for (const i of props.kliBawaan) if (!ada.has(i.toLowerCase())) form.persiapan.kli.push({ indikator: i, status: '' });
}
function isiAuditLalu() {
  for (const [k, v] of Object.entries(props.saranAuditLalu?.isi ?? {})) {
    if (v && !form.persiapan.audit_lalu[k]?.trim()) form.persiapan.audit_lalu[k] = v;
  }
}
const IZIN = ['IUP Eksplorasi', 'IUP Operasi Produksi', 'IUPK Operasi Produksi', 'IUP Operasi Produksi khusus Pengolahan dan/atau Pemurnian', 'IPR', 'IUJP'];

/* Hitungan hari kerja ditampilkan seketika; server menghitung ulang saat menyimpan. */
const hari = computed(() => {
  const p = form.permulaan;
  const dasar = Math.max(0, Number(String(p.mandays_dasar).replace(',', '.')) || 0);
  const auditor = Math.max(1, parseInt(p.jumlah_auditor) || 1);
  const per = dasar / auditor;
  // Satu kondisi atau lebih: tambahan paling banyak 10% (bukan satu hari per kondisi) — cermin SmkpTahap::mandays.
  const kondisi = Object.values(p.faktor).filter(Boolean).length;
  const maks = kondisi > 0 ? Math.floor(per * 0.1 * 100 + 1e-9) / 100 : 0;
  const isian = p.penyesuaian !== '' && p.penyesuaian !== null ? Math.max(0, Number(String(p.penyesuaian).replace(',', '.')) || 0) : null;
  const tambah = isian === null ? maks : Math.min(isian, maks);
  const total = per + tambah;
  return { kondisi, maks, lewat: kondisi > 0 && isian !== null && isian > maks + 1e-9, per, tambah, total, t1: total * 0.1, t2: total * 0.9 };
});

const ringkasKecukupan = computed(() => {
  const v = Object.values(form.kecukupan);
  return {
    lengkap: v.filter((x) => x.status === props.nilaiStatus.lengkap).length,
    tidak: v.filter((x) => x.status === props.nilaiStatus.tidak).length,
    belum: v.filter((x) => !x.status).length,
  };
});

function simpan() {
  // Tanpa kondisi, isian penyesuaian lama tidak berlaku (kolomnya nonaktif).
  form.transform((d) => (hari.value.kondisi ? d : { ...d, permulaan: { ...d.permulaan, penyesuaian: '' } }))
    .post(`/smkp/${props.audit.id}/tahap-1`, { preserveScroll: true });
}

const bagian = 'kartu p-5 sm:p-6';
</script>

<template>
  <div class="smkp-ui">
    <Head title="Tahap I — Permulaan Audit" />
    <KepalaAudit :audit="audit" aktif="tahap1" :bisa-sunting="bisaSunting">
      <template #aksi>
        <Link :href="`/smkp/${audit.id}/berita-acara`" class="btn-garis btn-kecil py-2"><Ikon nama="file-check" class="w-4 h-4" /> Berita Acara</Link>
      </template>
    </KepalaAudit>

    <form class="grid xl:grid-cols-[1fr_300px] gap-5 items-start" @submit.prevent="simpan">
      <fieldset :disabled="!bisaSunting" class="space-y-5 min-w-0">
        <!-- A -->
        <section :class="bagian">
          <h2 class="kartu-judul">A. Kontak Awal &amp; Surat Penugasan</h2>
          <p class="kartu-sub">Pertemuan pertama dengan auditi dan surat pengangkatan tim audit.</p>
          <div class="mt-5 grid sm:grid-cols-2 gap-4">
            <div><label class="label" for="t1-tgl-kontak">Tanggal kontak awal</label><input id="t1-tgl-kontak" v-model="form.permulaan.tanggal_kontak" type="date" class="input"></div>
            <div>
              <label class="label" for="t1-sifat">Sifat kontak awal</label>
              <select id="t1-sifat" v-model="form.permulaan.sifat_kontak" class="input"><option value="">—</option><option value="formal">Formal</option><option value="informal">Informal</option></select>
            </div>
            <div><label class="label">Media</label><input v-model="form.permulaan.media_kontak" class="input" placeholder="Pertemuan tatap muka / daring"></div>
            <div><label class="label">Perwakilan auditi</label><input v-model="form.permulaan.wakil_auditi" class="input"></div>
            <div><label class="label">Jabatan perwakilan</label><input v-model="form.permulaan.jabatan_wakil" class="input"></div>
            <div><label class="label">Nomor surat pengangkatan tim</label><input v-model="form.permulaan.surat_nomor" class="input"></div>
            <div><label class="label" for="t1-tgl-surat">Tanggal surat</label><input id="t1-tgl-surat" v-model="form.permulaan.surat_tanggal" type="date" class="input"></div>
            <div>
              <label class="label" for="t1-izin">Jenis perizinan</label>
              <input id="t1-izin" v-model="form.permulaan.izin" class="input" list="t1-izin-daftar" maxlength="150"
                     :placeholder="dataPerusahaan.izin || 'mis. IUP Operasi Produksi'">
              <datalist id="t1-izin-daftar">
                <option v-for="j in IZIN" :key="j" :value="j" />
              </datalist>
            </div>
            <div class="sm:col-span-2">
              <label class="label" for="t1-sk">Surat pengangkatan auditor (SK auditor)</label>
              <input id="t1-sk" v-model="form.permulaan.sk_auditor" class="input" maxlength="500" placeholder="mis. 001/SK/2026 (Lead Auditor) pada tanggal 1 Oktober 2026, 002/SK/2026 (Auditor) pada tanggal 1 Oktober 2026">
              <p class="mt-1 text-[12px] text-slate-500">Untuk penetapan tanggung jawab auditor di Berita Acara. Kosong: memakai nomor & tanggal surat pengangkatan tim.</p>
            </div>
            <div class="sm:col-span-2">
              <label class="label" for="t1-alamat">Alamat perusahaan (Head Office)</label>
              <textarea id="t1-alamat" v-model="form.permulaan.alamat" rows="2" class="input" maxlength="500"
                        :placeholder="dataPerusahaan.alamat || 'Alamat kantor / lokasi tambang'" />
              <p class="mt-1 text-[12px] text-slate-500">Tampil di Berita Acara Tahap I dan lampiran lain. Kosongkan untuk memakai data perusahaan.</p>
            </div>
            <div class="sm:col-span-2">
              <label class="label" for="t1-alamat-site">Alamat perusahaan (Site Office)</label>
              <textarea id="t1-alamat-site" v-model="form.permulaan.alamat_site" rows="2" class="input" maxlength="500"
                        :placeholder="dataPerusahaan.alamatSite || 'Alamat kantor site / lokasi tambang'" />
            </div>
            <div class="sm:col-span-2">
              <label class="label" for="t1-periode">Periode audit</label>
              <input id="t1-periode" v-model="form.permulaan.periode" class="input" maxlength="150" :placeholder="`mis. 1 Januari – 31 Desember ${audit.tahun}`">
              <p class="mt-1 text-[12px] text-slate-500">Periode data yang diaudit, untuk tabel Data Perusahaan Auditi di Berita Acara. Kosong: {{ audit.periode }}.</p>
            </div>
          </div>
        </section>

        <!-- B -->
        <section :class="bagian">
          <h2 class="kartu-judul">B. Data Kinerja Keselamatan Pertambangan</h2>
          <p class="kartu-sub">Angka kinerja pada periode audit.</p>
          <div class="mt-5 grid sm:grid-cols-2 gap-4">
            <div v-for="(b, k) in kinerja" :key="k">
              <label class="label">{{ b.label }} <span v-if="b.satuan" class="font-normal text-slate-400">({{ b.satuan }})</span></label>
              <input v-model="form.kinerja[k]" inputmode="decimal" class="input tabular-nums">
            </div>
          </div>
        </section>

        <!-- C -->
        <section :class="bagian">
          <h2 class="kartu-judul">C. Penentuan Kelayakan Audit</h2>
          <p class="kartu-sub">Tujuh indikator yang memutuskan audit dapat dijalankan.</p>
          <div class="mt-5 space-y-3">
            <div v-for="(label, k, i) in kelayakan" :key="k" class="grid md:grid-cols-[1fr_260px] gap-2 md:items-center">
              <label class="text-[13px] text-slate-700"><span class="text-slate-400 tabular-nums mr-1">{{ Number(i) + 1 }}.</span>{{ label }}</label>
              <input v-model="form.permulaan.kelayakan[k]" class="input" placeholder="Hasil evaluasi">
            </div>
          </div>
          <div class="mt-5 grid sm:grid-cols-2 gap-4">
            <div><label class="label" for="t1-tgl-layak">Tanggal penentuan kelayakan</label><input id="t1-tgl-layak" v-model="form.permulaan.tanggal_kelayakan" type="date" class="input"></div>
            <div>
              <p class="label">Hasil penentuan</p>
              <div class="flex gap-1.5" role="group" aria-label="Hasil penentuan kelayakan">
                <button v-for="o in [['layak', 'Layak', 'bg-hijau-500 border-hijau-500 text-white'], ['tidak', 'Tidak layak', 'bg-amber-500 border-amber-500 text-white']]" :key="o[0]"
                        type="button" class="rounded-lg border px-3 py-2 text-[12.5px] font-semibold transition" :aria-pressed="form.permulaan.layak === o[0]"
                        :class="form.permulaan.layak === o[0] ? o[2] : 'border-slate-200 text-slate-500 hover:border-slate-300'"
                        @click="form.permulaan.layak = form.permulaan.layak === o[0] ? '' : o[0]">{{ o[1] }}</button>
              </div>
            </div>
          </div>
          <div class="mt-4"><label class="label">Catatan kelayakan <span class="font-normal text-slate-400">(opsional)</span></label><textarea v-model="form.permulaan.kesimpulan" rows="2" class="input" /></div>
        </section>

        <!-- D -->
        <section :class="bagian">
          <h2 class="kartu-judul">D. Perhitungan Hari Kerja Audit</h2>
          <p class="kartu-sub">Centang kondisi yang terpenuhi. Satu kondisi atau lebih menambah hari kerja audit paling banyak 10% — bukan satu hari per kondisi.</p>
          <div class="mt-5 space-y-2">
            <label v-for="(label, k, i) in faktor" :key="k" class="flex items-start gap-3 rounded-xl border px-4 py-3 cursor-pointer transition"
                   :class="form.permulaan.faktor[k] ? 'border-brand-300 bg-brand-50/50' : 'border-slate-200 hover:bg-slate-50'">
              <input v-model="form.permulaan.faktor[k]" type="checkbox" class="mt-0.5 rounded border-slate-300 text-brand-500 focus:ring-brand-500/30">
              <span class="text-[13px] text-slate-700 leading-relaxed"><span class="text-slate-400 tabular-nums mr-1">{{ Number(i) + 1 }}.</span>{{ label }}</span>
            </label>
          </div>
          <div class="mt-5 grid sm:grid-cols-2 gap-4">
            <div><label class="label">Jumlah pekerja auditi</label><input v-model="form.permulaan.jumlah_pekerja" inputmode="numeric" class="input tabular-nums"></div>
            <div>
              <label class="label" for="t1-kelas-risiko">Kelas risiko</label>
              <select id="t1-kelas-risiko" v-model="form.permulaan.kelas_risiko" class="input"><option value="">—</option><option v-for="k in kelasRisiko" :key="k" :value="k">{{ k }}</option></select>
              <p v-if="form.errors['permulaan.kelas_risiko']" class="galat">{{ form.errors['permulaan.kelas_risiko'] }}</p>
            </div>
            <div><label class="label">Mandays dasar (hari)</label><input v-model="form.permulaan.mandays_dasar" inputmode="decimal" class="input tabular-nums"></div>
            <div><label class="label">Jumlah auditor</label><input v-model="form.permulaan.jumlah_auditor" inputmode="numeric" class="input tabular-nums"></div>
            <div class="sm:col-span-2">
              <label class="label">Faktor penyesuaian (hari)</label>
              <input v-model="form.permulaan.penyesuaian" inputmode="decimal" class="input tabular-nums" :disabled="!hari.kondisi"
                     :placeholder="hari.kondisi ? `Kosongkan untuk memakai tambahan maksimum: ${angka(hari.maks)} hari (10%)` : 'Centang minimal satu kondisi di atas'">
              <p v-if="form.errors['permulaan.penyesuaian']" class="galat">{{ form.errors['permulaan.penyesuaian'] }}</p>
              <p v-else-if="hari.lewat" class="galat">Paling banyak {{ angka(hari.maks) }} hari (10% dari {{ angka(hari.per) }} hari per auditor).</p>
              <p v-else class="bantu">
                <template v-if="hari.kondisi">{{ hari.kondisi }} kondisi terpenuhi · tambahan {{ angka(hari.tambah) }} hari dari maksimum {{ angka(hari.maks) }} hari (10% × {{ angka(hari.per) }} hari).</template>
                <template v-else>Tanpa kondisi yang terpenuhi, hari kerja audit tidak ditambah.</template>
              </p>
            </div>
          </div>
          <div class="mt-5 grid grid-cols-2 sm:grid-cols-4 gap-3 rounded-kartu bg-gradient-to-br from-navy-900 to-navy-800 text-white p-5">
            <div v-for="h in [['Per auditor', hari.per], ['Total mandays', hari.total], ['Tahap I (maks 10%)', hari.t1], ['Tahap II (min 90%)', hari.t2]]" :key="String(h[0])">
              <p class="text-[22px] font-bold tabular-nums">{{ angka(Number(h[1])) }}</p>
              <p class="text-[11.5px] text-white/60">{{ h[0] }}</p>
            </div>
          </div>
          <p v-if="hariTetap" class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-[12.5px] leading-relaxed text-amber-800">
            Audit sudah selesai: Berita Acara dan Rencana Audit memakai hari kerja yang dibekukan saat audit diselesaikan —
            total <b class="tabular-nums">{{ angka(hariTetap.total) }}</b> hari<template v-if="hariTetap.aturan === 'lama'"> (aturan lama, satu hari per kondisi)</template>.
            Hitungan di atas berlaku bila audit dibuka kembali (dipindah keluar dari tahap Pelaporan).
          </p>
        </section>

        <!-- E -->
        <section :class="bagian">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div><h2 class="kartu-judul">E. Kecukupan Dokumentasi</h2><p class="kartu-sub">Peninjauan dokumen dan rekaman tiap elemen sebelum audit lapangan.</p></div>
            <span class="lencana" :class="ringkasKecukupan.belum ? 'bg-amber-50 text-amber-700' : 'bg-hijau-50 text-hijau-700'">
              {{ ringkasKecukupan.lengkap }} lengkap · {{ ringkasKecukupan.tidak }} tidak lengkap · {{ ringkasKecukupan.belum }} belum
            </span>
          </div>
          <div class="mt-5 space-y-3">
            <div v-for="e in elemen" :key="e.kode" class="rounded-xl border border-slate-200 p-4">
              <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-[13px] font-semibold text-navy-900"><span class="text-brand-600 tabular-nums mr-1">{{ e.kode }}.</span>{{ e.nama }}</p>
                <div class="flex gap-1.5">
                  <button v-for="opsi in [[nilaiStatus.lengkap, 'Lengkap', 'bg-hijau-500 border-hijau-500 text-white'], [nilaiStatus.tidak, 'Tidak lengkap', 'bg-amber-500 border-amber-500 text-white']]" :key="opsi[0]"
                          type="button" class="rounded-lg border px-3 py-1.5 text-[12px] font-semibold transition"
                          :class="form.kecukupan[e.kode].status === opsi[0] ? opsi[2] : 'border-slate-200 text-slate-500 hover:border-slate-300'"
                          @click="form.kecukupan[e.kode].status = form.kecukupan[e.kode].status === opsi[0] ? '' : opsi[0]">{{ opsi[1] }}</button>
                </div>
              </div>
              <input v-model="form.kecukupan[e.kode].ket" class="input mt-3" placeholder="Catatan peninjauan">
            </div>
          </div>
        </section>

        <!-- F -->
        <section :class="bagian">
          <h2 class="kartu-judul">F. Persiapan Audit Lapangan</h2>
          <p class="kartu-sub">Data pertimbangan penetapan metode dan sampel — Berita Acara bagian III.</p>
          <div class="mt-5 space-y-5">
            <div>
              <label class="label" for="t1-integritas">Bukti komitmen integritas auditor</label>
              <input id="t1-integritas" v-model="form.permulaan.bukti_integritas" class="input" maxlength="300" :placeholder="buktiIntegritas">
            </div>
            <p class="rounded-xl bg-slate-50 px-4 py-3 text-[12.5px] text-slate-600">
              Top Risks diambil dari Rencana Audit:
              <b class="tabular-nums">{{ jumlahRisiko.present }}</b> Risk of Present, <b class="tabular-nums">{{ jumlahRisiko.future }}</b> Risk of Future.
              <Link :href="`/smkp/${audit.id}/rencana#k-metode`" class="font-semibold text-navy-700 hover:underline">Ubah di Rencana Audit</Link>
            </p>

            <div class="grid md:grid-cols-2 gap-4">
              <div v-for="(d, k) in tren" :key="k" class="rounded-xl border border-slate-200 p-4 min-w-0">
                <p class="text-[12.5px] font-semibold text-navy-900 leading-snug">{{ d.judul }}</p>
                <p class="text-[11.5px] text-slate-500">{{ d.kolom }}</p>
                <div class="mt-2.5 space-y-2">
                  <div v-for="(_, i) in form.persiapan.tren[k]" :key="i" class="flex items-center gap-1.5">
                    <span class="block w-5 shrink-0 text-[11.5px] text-slate-400 tabular-nums">{{ i + 1 }}.</span>
                    <input v-model="form.persiapan.tren[k][i]" class="input-kecil" maxlength="300" :aria-label="`${d.kolom} ${i + 1}`">
                    <button type="button" class="p-1.5 rounded-md text-slate-300 hover:text-rose-600 hover:bg-rose-50" :aria-label="`Hapus baris ${i + 1}`" @click="form.persiapan.tren[k].splice(i, 1)"><Ikon nama="tutup" class="w-4 h-4" /></button>
                  </div>
                </div>
                <button v-if="form.persiapan.tren[k].length < MAKS_BARIS" type="button" class="mt-2 btn-garis btn-kecil" @click="form.persiapan.tren[k].push('')"><Ikon nama="tambah" class="w-4 h-4" /> Baris</button>
              </div>
            </div>

            <div>
              <p class="text-[13px] font-semibold text-navy-900">Data Unjuk Kerja Peralatan Pertambangan</p>
              <div class="mt-2 gulir-x scrollbar-tipis">
                <table class="w-full min-w-[520px] text-[12.5px]">
                  <thead><tr class="text-left text-[10.5px] font-semibold uppercase tracking-[0.08em] text-slate-400"><th class="py-2 pr-2">Jenis peralatan</th><th class="py-2 px-2 w-36">Physical availability</th><th class="py-2 px-2 w-36">Mechanical availability</th><th class="w-10" /></tr></thead>
                  <tbody>
                    <tr v-for="(b, i) in form.persiapan.peralatan" :key="i" class="border-t border-slate-100">
                      <td class="py-1.5 pr-2"><input v-model="b.jenis" class="input-kecil" maxlength="150" :aria-label="`Jenis peralatan ${i + 1}`"></td>
                      <td class="py-1.5 px-2"><input v-model="b.pa" class="input-kecil tabular-nums" maxlength="30" placeholder="85 %" :aria-label="`Physical availability ${i + 1}`"></td>
                      <td class="py-1.5 px-2"><input v-model="b.ma" class="input-kecil tabular-nums" maxlength="30" placeholder="90 %" :aria-label="`Mechanical availability ${i + 1}`"></td>
                      <td class="py-1.5 text-center"><button type="button" class="p-1.5 rounded-md text-slate-300 hover:text-rose-600 hover:bg-rose-50" aria-label="Hapus peralatan" @click="form.persiapan.peralatan.splice(i, 1)"><Ikon nama="tutup" class="w-4 h-4" /></button></td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <button v-if="form.persiapan.peralatan.length < 30" type="button" class="mt-2 btn-garis btn-kecil" @click="form.persiapan.peralatan.push({ jenis: '', pa: '', ma: '' })"><Ikon nama="tambah" class="w-4 h-4" /> Peralatan</button>
            </div>

            <div>
              <p class="text-[13px] font-semibold text-navy-900">Data Capaian Key Leading Indicator</p>
              <div class="mt-2 gulir-x scrollbar-tipis">
                <table class="w-full min-w-[520px] text-[12.5px]">
                  <thead><tr class="text-left text-[10.5px] font-semibold uppercase tracking-[0.08em] text-slate-400"><th class="py-2 pr-2">Key leading indicator</th><th class="py-2 px-2 w-56">Status pencapaian</th><th class="w-10" /></tr></thead>
                  <tbody>
                    <tr v-for="(b, i) in form.persiapan.kli" :key="i" class="border-t border-slate-100">
                      <td class="py-1.5 pr-2"><input v-model="b.indikator" class="input-kecil" maxlength="300" :aria-label="`Key leading indicator ${i + 1}`"></td>
                      <td class="py-1.5 px-2"><input v-model="b.status" class="input-kecil" maxlength="150" placeholder="100 %" :aria-label="`Status pencapaian ${i + 1}`"></td>
                      <td class="py-1.5 text-center"><button type="button" class="p-1.5 rounded-md text-slate-300 hover:text-rose-600 hover:bg-rose-50" aria-label="Hapus indikator" @click="form.persiapan.kli.splice(i, 1)"><Ikon nama="tutup" class="w-4 h-4" /></button></td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div class="mt-2 flex flex-wrap gap-2">
                <button v-if="form.persiapan.kli.length < 30" type="button" class="btn-garis btn-kecil" @click="form.persiapan.kli.push({ indikator: '', status: '' })"><Ikon nama="tambah" class="w-4 h-4" /> Indikator</button>
                <button type="button" class="btn-garis btn-kecil" :title="kliBawaan.join(', ')" @click="pakaiKliBawaan"><Ikon nama="daftar" class="w-4 h-4" /> Pakai indikator bawaan</button>
              </div>
            </div>

            <div>
              <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-[13px] font-semibold text-navy-900">Data Audit Internal SMKP Tahun {{ audit.tahun - 1 }}</p>
                <button v-if="saranAuditLalu" type="button" class="btn-garis btn-kecil" @click="isiAuditLalu"><Ikon nama="salin" class="w-4 h-4" /> Isi dari audit {{ saranAuditLalu.tahun }}</button>
              </div>
              <div class="mt-2 space-y-2.5">
                <div v-for="(label, k) in auditLalu" :key="k" class="grid md:grid-cols-[1fr_minmax(0,22rem)] gap-1.5 md:gap-3 md:items-center">
                  <label class="text-[12.5px] text-slate-700" :for="`t1-lalu-${k}`">{{ label }}</label>
                  <input :id="`t1-lalu-${k}`" v-model="form.persiapan.audit_lalu[k]" class="input" maxlength="1000">
                </div>
              </div>
            </div>

            <div>
              <p class="text-[13px] font-semibold text-navy-900">Masukan Pemangku Kepentingan Tahun {{ audit.tahun - 1 }}</p>
              <p class="text-[11.5px] text-slate-500">Buku Tambang, pembinaan Ditjen Minerba, Komite Keselamatan, pemberi kerja (IUJP), serikat pekerja, pengaduan masyarakat.</p>
              <datalist id="t1-sumber-masukan"><option v-for="o in SUMBER_MASUKAN" :key="o" :value="o" /></datalist>
              <div class="mt-2 space-y-2.5">
                <div v-for="(m, i) in form.persiapan.masukan" :key="i" class="flex items-start gap-1.5">
                  <span class="w-5 shrink-0 pt-2 text-[11.5px] text-slate-400 tabular-nums">{{ i + 1 }}.</span>
                  <div class="min-w-0 flex-1 space-y-1.5">
                    <input v-model="m.sumber" class="input-kecil" list="t1-sumber-masukan" maxlength="200" placeholder="Sumber / kelompok (opsional), mis. Masukan dari Manajemen" :aria-label="`Sumber masukan ${i + 1}`">
                    <textarea v-model="m.isi" rows="2" class="input" maxlength="2000" :aria-label="`Masukan ${i + 1}`" />
                  </div>
                  <button type="button" class="mt-1.5 p-1.5 rounded-md text-slate-300 hover:text-rose-600 hover:bg-rose-50" :aria-label="`Hapus masukan ${i + 1}`" @click="form.persiapan.masukan.splice(i, 1)"><Ikon nama="tutup" class="w-4 h-4" /></button>
                </div>
              </div>
              <p class="bantu">Masukan bersumber sama dicetak berkelompok di bawah judul sumbernya.</p>
              <button v-if="form.persiapan.masukan.length < 30" type="button" class="mt-2 btn-garis btn-kecil" @click="tambahMasukan"><Ikon nama="tambah" class="w-4 h-4" /> Masukan</button>
            </div>

            <div>
              <label class="label" for="t1-rapat">Nomor Formulir Rekaman Rapat</label>
              <input id="t1-rapat" v-model="form.permulaan.form_rapat" class="input" maxlength="200" :placeholder="nomorRapat">
              <p class="mt-1 text-[12px] text-slate-500">Dokumen kerja butir 5 di Berita Acara, mis. formulir rekaman rapat Safety Committee/KKP. Kosong: nomor Daftar Hadir Rapat.</p>
            </div>
            <p v-if="Object.keys(form.errors).some((k) => k.startsWith('persiapan'))" class="galat">{{ Object.entries(form.errors).find(([k]) => k.startsWith('persiapan'))?.[1] }}</p>
          </div>
        </section>

        <!-- G -->
        <section :class="bagian">
          <h2 class="kartu-judul">G. Penutup Berita Acara</h2>
          <p class="kartu-sub">Tanggal Berita Acara dibuat. Tanda tangan dibubuhkan di halaman cetak Berita Acara.</p>
          <div class="mt-5 sm:w-1/2"><label class="label" for="t1-tgl-ba">Tanggal Berita Acara</label><input id="t1-tgl-ba" v-model="form.permulaan.tanggal_ba" type="date" class="input"></div>
        </section>
      </fieldset>

      <aside class="xl:sticky xl:top-24 space-y-4">
        <div class="kartu p-5">
          <p class="text-[13px] font-bold text-navy-900">Kesiapan Tahap II</p>
          <ul class="mt-3 space-y-2 text-[12.5px]">
            <li class="flex items-center gap-2"><Ikon :nama="rekap.siap ? 'centang-bulat' : 'silang-bulat'" class="w-4 h-4" :class="rekap.siap ? 'text-hijau-600' : 'text-slate-300'" /> Kecukupan dokumentasi tersimpan</li>
            <li class="flex items-center gap-2"><Ikon nama="info" class="w-4 h-4 text-slate-300" /> Rencana Audit 9 komponen</li>
          </ul>
          <Link :href="`/smkp/${audit.id}/rencana`" class="mt-4 btn-garis btn-kecil w-full">Buka Rencana Audit <Ikon nama="panah-kanan" class="w-4 h-4" /></Link>
        </div>
        <div v-if="bisaSunting" class="kartu p-5">
          <p class="text-[12.5px] text-slate-500" :class="form.isDirty ? 'text-amber-700 font-semibold' : ''">{{ form.isDirty ? 'Ada perubahan yang belum disimpan.' : 'Seluruh isian Tahap I tersimpan.' }}</p>
          <button class="mt-3 btn-utama w-full" :disabled="form.processing || !form.isDirty"><Ikon nama="simpan" class="w-4 h-4" /> {{ form.processing ? 'Menyimpan…' : 'Simpan Tahap I' }}</button>
        </div>
      </aside>
    </form>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import KepalaAudit from '../../Components/Smkp/KepalaAudit.vue';
import Ikon from '../../Components/Smkp/Ikon.vue';
import KepalaBagian from '../../Components/Smkp/Rencana/KepalaBagian.vue';
import TabelMetodeSampel from '../../Components/Smkp/Rencana/TabelMetodeSampel.vue';
import DialogPakaiUlang from '../../Components/Smkp/Rencana/DialogPakaiUlang.vue';
import DialogImpor from '../../Components/Smkp/Rencana/DialogImpor.vue';
import { gabungNilai, tabelKosong } from '../../Components/Smkp/Rencana/api';
import type { BarisMetode, IsiSalin, KunciBagian, SumberRencana } from '../../Components/Smkp/Rencana/api';
import { angka } from '../../smkp/lib/format';
import type { RingkasAudit } from '../../smkp/types';

type Baris = Record<string, string>;

const props = defineProps<{
  audit: RingkasAudit;
  isian: {
    nomor: string; tujuan: string; kriteria: string; ruang_lingkup: string; tanggal_mulai: string; tanggal_selesai: string;
    sumberdaya: string; metode: string; sampel: string;
    jenis_audit: string; alamat: string; pengecualian: string; dokumentasi: string; distribusi: string;
    metode_sampel: Record<string, { metode: string; sampel: string }>;
    susunan: Baris[]; tugas: Baris[];
    pengesahan: Record<string, { nama: string; jabatan: string; tanggal: string }>;
    risiko: { present: Baris[]; future: Baris[] };
  };
  komponen: Record<string, { judul: string; ket: string }>;
  jenisAudit: string[];
  kriteriaDaftar: Array<{ kode: string; nama: string; sub: Array<{ kode: string; nama: string }> }>;
  pengesah: Record<string, { peran: string; wajib: boolean }>;
  kegiatan: string[];
  rekap: { terisi: Record<string, boolean>; jumlah: number; total: number; lengkap: boolean; kurang: string[] };
  mandays: Record<string, number>;
  bisaSunting: boolean;
}>();

const page = usePage<any>();
const aiAktif = computed(() => !!page.props.asisten?.aktif);

const KOSONG = {
  susunan: { tanggal: '', waktu: '', kegiatan: '', auditi: '', auditor: '' },
  tugas: { nama: '', peran: '', registrasi: '', lingkup: '' },
  risiko: { kegiatan: '', risiko: '', nilai: '' },
};

const salin = JSON.parse(JSON.stringify(props.isian)) as typeof props.isian;
if (!salin.susunan.length) salin.susunan.push({ ...KOSONG.susunan });
if (!salin.tugas.length) salin.tugas.push({ ...KOSONG.tugas });
if (!salin.risiko.present.length) salin.risiko.present.push({ ...KOSONG.risiko });
if (!salin.risiko.future.length) salin.risiko.future.push({ ...KOSONG.risiko });

// Setiap sub-elemen punya baris metode & sampel sendiri (Formulir Metode dan Sampel Audit).
salin.metode_sampel = { ...(salin.metode_sampel ?? {}) };
for (const e of props.kriteriaDaftar) for (const sb of e.sub) salin.metode_sampel[sb.kode] ??= { metode: '', sampel: '' };

const form = useForm(salin);

/* ---------- kelengkapan langsung dari isian (cermin SmkpTahap::rekapRencana) ---------- */
const ada = (v: unknown) => String(v ?? '').trim() !== '';
const adaBaris = (rows: Baris[], kolom: string[]) => rows.some((b) => kolom.some((k) => ada(b[k])));
const terisi = computed<Record<string, boolean>>(() => ({
  tujuan: ada(form.tujuan),
  kriteria: ada(form.kriteria),
  ruang_lingkup: ada(form.ruang_lingkup),
  tanggal: ada(form.tanggal_mulai) && ada(form.tanggal_selesai),
  susunan: adaBaris(form.susunan, ['kegiatan']),
  tugas: adaBaris(form.tugas, ['nama']),
  sumberdaya: ada(form.sumberdaya),
  metode: ada(form.metode),
  pengesahan: Object.entries(props.pengesah).every(([k, d]) => !d.wajib || ada(form.pengesahan[k]?.nama)),
}));
const daftarKomponen = computed(() => Object.entries(props.komponen).map(([kunci, k], i) => ({ kunci, ...k, nomor: i + 1, terisi: !!terisi.value[kunci] })));
const jumlahTerisi = computed(() => daftarKomponen.value.filter((k) => k.terisi).length);
const lengkap = computed(() => jumlahTerisi.value === daftarKomponen.value.length);
const nomor = (kunci: string) => daftarKomponen.value.find((k) => k.kunci === kunci)?.nomor ?? '';

const LABEL_PENDEK: Record<string, string> = {
  tujuan: 'Tujuan', kriteria: 'Kriteria', ruang_lingkup: 'Ruang lingkup', tanggal: 'Tanggal', susunan: 'Susunan kegiatan',
  tugas: 'Tim & tugas', sumberdaya: 'Sumber daya', metode: 'Metode & sampel', pengesahan: 'Pengesahan',
};

const totalKriteria = computed(() => props.kriteriaDaftar.reduce((n, e) => n + e.sub.length, 0));
const kriteriaTerisi = computed(() => Object.values(form.metode_sampel).filter((v) => ada(v.metode) || ada(v.sampel)).length);

const TOP_RISKS = [
  { kunci: 'present', huruf: 'A', judul: 'Top Risks — Risk of Present', ket: 'Risiko tertinggi pada periode audit', warna: 'bg-brand-600' },
  { kunci: 'future', huruf: 'B', judul: 'Top Risks — Risk of Future', ket: 'Risiko dari rencana kegiatan berikutnya', warna: 'bg-teal-700' },
] as const;
const METODE_SARAN = ['Tinjauan dokumen', 'Wawancara', 'Observasi lapangan', 'Tinjauan dokumen & wawancara', 'Tinjauan dokumen, wawancara & observasi'];

const tambah = (daftar: Baris[], kosong: Baris) => daftar.push({ ...kosong });
const buang = (daftar: Baris[], i: number) => daftar.splice(i, 1);

function lompat(id: string) {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/* ---------- pakai ulang & impor: diterapkan ke formulir, belum tersimpan ---------- */
const bukaSalin = ref(false);
const bukaImpor = ref(false);
/** kode sub-elemen → asal isian yang baru diterapkan */
const penanda = ref<Record<string, string>>({});
const ringkasan = ref<{ judul: string; rinci: string; berubah: boolean } | null>(null);
// Potret isian sebelum penerapan pertama, untuk tombol Urungkan.
let cadangan: string | null = null;

function cadangkan() {
  if (cadangan === null) cadangan = JSON.stringify(form.data());
}

function urungkan() {
  if (cadangan === null) return;
  const lama = JSON.parse(cadangan) as typeof salin;
  for (const k of Object.keys(lama) as Array<keyof typeof salin>) (form as any)[k] = lama[k];
  cadangan = null;
  penanda.value = {};
  ringkasan.value = null;
}

/** Terapkan metode & sampel per kode; kembalikan jumlah kriteria yang berubah. */
function terapkanKriteria(baris: BarisMetode[], timpa: boolean, label: string): number {
  const tanda: Record<string, string> = {};
  for (const b of baris) {
    const t = form.metode_sampel[b.kode];
    if (!t) continue;
    const metode = gabungNilai(t.metode, b.metode, timpa);
    const sampel = gabungNilai(t.sampel, b.sampel, timpa);
    if (metode === t.metode && sampel === t.sampel) continue;
    t.metode = metode;
    t.sampel = sampel;
    tanda[b.kode] = label;
  }
  penanda.value = { ...penanda.value, ...tanda };

  return Object.keys(tanda).length;
}

type KunciTeks = 'metode' | 'sampel' | 'tujuan' | 'kriteria' | 'ruang_lingkup' | 'pengecualian' | 'dokumentasi' | 'sumberdaya' | 'distribusi' | 'jenis_audit';
const TEKS_BAGIAN: Partial<Record<KunciBagian, KunciTeks[]>> = {
  metode: ['metode', 'sampel'],
  lingkup: ['jenis_audit', 'tujuan', 'kriteria', 'ruang_lingkup', 'pengecualian'],
  sumberdaya: ['sumberdaya', 'dokumentasi', 'distribusi'],
};

function terapkanSalin({ sumber, isi, bagian, timpa }: { sumber: SumberRencana; isi: IsiSalin; bagian: KunciBagian[]; timpa: boolean }) {
  cadangkan();
  const label = sumber.judul;
  const rinci: string[] = [];
  const tetap: string[] = [];

  const nKriteria = bagian.includes('kriteria')
    ? terapkanKriteria(Object.entries(isi.metode_sampel ?? {}).map(([kode, v]) => ({ kode, ...v })), timpa, label)
    : 0;
  if (nKriteria) rinci.push(`${nKriteria} kriteria`);

  let nTeks = 0;
  for (const b of bagian) {
    for (const k of TEKS_BAGIAN[b] ?? []) {
      // Jenis audit hanya dari pilihan yang sah agar daftar pilihan tidak kosong.
      if (k === 'jenis_audit' && !props.jenisAudit.includes(isi.jenis_audit)) continue;
      const baru = gabungNilai(form[k], isi[k], timpa);
      if (baru !== form[k]) { form[k] = baru; nTeks++; }
    }
  }
  if (nTeks) rinci.push(`${nTeks} isian teks`);

  // Tabel disalin utuh: menggantikan tabel yang masih kosong, atau semuanya bila "timpa".
  if (bagian.includes('risiko')) {
    let n = 0;
    for (const j of ['present', 'future'] as const) {
      if (!isi.risiko[j].length) continue;
      if (timpa || tabelKosong(form.risiko[j])) {
        form.risiko[j] = isi.risiko[j].map((r) => ({ ...KOSONG.risiko, ...r }));
        n += isi.risiko[j].length;
      } else if (!tetap.includes('Top Risks')) tetap.push('Top Risks');
    }
    if (n) rinci.push(`${n} top risks`);
  }
  if (bagian.includes('tim') && isi.tugas.length) {
    if (timpa || tabelKosong(form.tugas)) {
      form.tugas = isi.tugas.map((r) => ({ ...KOSONG.tugas, ...r }));
      rinci.push(`${isi.tugas.length} auditor`);
    } else tetap.push('tim audit');
  }

  umumkan(label, nKriteria, rinci, timpa, tetap);
}

function terapkanImpor({ baris, timpa, label }: { baris: BarisMetode[]; timpa: boolean; label: string }) {
  cadangkan();
  const n = terapkanKriteria(baris, timpa, label);
  umumkan(label, n, n ? [`${n} kriteria`] : [], timpa, []);
}

async function umumkan(label: string, nKriteria: number, rinci: string[], timpa: boolean, tetap: string[]) {
  const berubah = rinci.length > 0;
  // Tabel yang sudah berisi tidak digabung baris per baris — beri tahu bahwa isinya dipertahankan.
  const catatTetap = tetap.length ? ` ${tetap.join(' dan ')} dipertahankan karena sudah terisi.` : '';
  ringkasan.value = berubah
    ? {
        judul: nKriteria ? `${nKriteria} kriteria terisi dari ${label}` : `Isian disalin dari ${label}`,
        rinci: `Diperbarui: ${rinci.join(', ')}.${catatTetap} Periksa dan sesuaikan, lalu tekan Simpan Rencana Audit.`,
        berubah,
      }
    : {
        judul: 'Tidak ada isian yang berubah',
        rinci: timpa ? `Isian ${label} sama dengan isian sekarang.` : 'Bagian yang dipilih sudah terisi. Pilih "Timpa semua" bila ingin menggantinya.',
        berubah,
      };
  if (!berubah && cadangan === JSON.stringify(form.data())) cadangan = null;
  await nextTick();
  lompat('ringkasan-terapan');
}

function simpan() {
  form.post(`/smkp/${props.audit.id}/rencana`, {
    preserveScroll: true,
    onSuccess: () => { cadangan = null; penanda.value = {}; ringkasan.value = null; },
  });
}
</script>

<template>
  <div class="smkp-ui">
    <Head title="Rencana Audit" />
    <KepalaAudit :audit="audit" aktif="rencana" :bisa-sunting="bisaSunting">
      <template #aksi>
        <Link :href="`/smkp/${audit.id}/rencana/cetak`" class="btn-garis btn-kecil py-2"><Ikon nama="cetak" class="w-4 h-4" /> Cetak Rencana</Link>
      </template>
    </KepalaAudit>

    <div class="space-y-5">
      <!-- Kelengkapan sembilan komponen -->
      <section class="kartu p-4 sm:p-5">
        <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
          <div class="min-w-0 flex-1">
            <p class="text-[14px] font-semibold text-navy-900">Kelengkapan sembilan komponen wajib</p>
            <p class="kartu-sub">Kepdirjen 185.K/37.04/DJB/2019 — dasar kesepakatan antara klien audit, tim audit, dan auditi.</p>
          </div>
          <div class="flex items-center gap-3 w-full sm:w-auto">
            <span class="bilah sm:w-40"><i :class="lengkap ? 'bg-hijau-500' : 'bg-teal-500'" :style="{ width: `${Math.round((jumlahTerisi / daftarKomponen.length) * 100)}%` }" /></span>
            <span class="text-[20px] font-bold tabular-nums leading-none" :class="lengkap ? 'text-hijau-600' : 'text-navy-900'">{{ jumlahTerisi }}<span class="text-[14px] text-slate-400">/{{ daftarKomponen.length }}</span></span>
          </div>
        </div>
        <nav class="mt-3.5 flex flex-wrap gap-1.5" aria-label="Komponen Rencana Audit">
          <button v-for="k in daftarKomponen" :key="k.kunci" type="button" :title="`${k.judul} — ${k.ket}`" @click="lompat(`k-${k.kunci}`)"
                  class="lencana transition-colors" :class="k.terisi ? 'bg-hijau-50 text-hijau-700 hover:bg-hijau-100' : 'bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-navy-900'">
            <Ikon :nama="k.terisi ? 'centang' : 'kurang'" class="w-3 h-3" :tebal="3" /> {{ k.nomor }}. {{ LABEL_PENDEK[k.kunci] ?? k.judul }}
          </button>
        </nav>
      </section>

      <!-- Pakai ulang Rencana Audit sebelumnya -->
      <section v-if="bisaSunting" class="kartu overflow-hidden" aria-labelledby="judul-pakai-ulang">
        <div class="flex flex-col lg:flex-row lg:items-center gap-4 p-4 sm:p-5">
          <div class="flex items-start gap-3 min-w-0 flex-1">
            <span class="grid place-items-center w-10 h-10 shrink-0 rounded-xl bg-teal-50 text-teal-700"><Ikon nama="arsip" class="w-5 h-5" /></span>
            <div class="min-w-0">
              <h2 id="judul-pakai-ulang" class="kartu-judul">Mulai dari Rencana Audit sebelumnya</h2>
              <p class="kartu-sub">Pakai rencana tahun lalu atau dokumen yang pernah dibuat sebagai acuan, lalu sesuaikan bila ada penambahan.</p>
            </div>
          </div>
          <div class="grid grid-cols-2 sm:flex sm:flex-wrap gap-2 lg:justify-end shrink-0">
            <button type="button" class="btn-utama btn-kecil col-span-2" @click="bukaSalin = true"><Ikon nama="salin" class="w-4 h-4" /> Pakai ulang dari audit lain</button>
            <a :href="`/smkp/${audit.id}/rencana/templat`" class="btn-garis btn-kecil" download><Ikon nama="unduh" class="w-4 h-4" /> Unduh templat</a>
            <button type="button" class="btn-garis btn-kecil" @click="bukaImpor = true"><Ikon nama="unggah" class="w-4 h-4" /> Impor berkas</button>
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-x-5 gap-y-1.5 px-4 sm:px-5 py-2.5 border-t border-slate-100 bg-slate-50/70 text-[12px] text-slate-500">
          <span class="inline-flex items-center gap-1.5"><Ikon nama="daftar" class="w-3.5 h-3.5 text-teal-700" /> CSV/XLSX dibaca langsung</span>
          <span class="inline-flex items-center gap-1.5">
            <Ikon nama="kilau" class="w-3.5 h-3.5 text-brand-600" /> PDF/DOCX dibaca Asisten AI
            <span v-if="!aiAktif" class="lencana bg-amber-50 text-amber-700">belum aktif</span>
          </span>
          <span class="inline-flex items-center gap-1.5"><Ikon nama="info" class="w-3.5 h-3.5 text-slate-400" /> Hasil masuk ke formulir dan tersimpan setelah Anda menekan Simpan.</span>
        </div>
      </section>

      <!-- Ringkasan penerapan -->
      <div v-if="ringkasan" id="ringkasan-terapan" role="status"
           class="scroll-mt-24 flex flex-col sm:flex-row sm:items-center gap-3 rounded-kartu border px-4 py-3 animate-muncul"
           :class="ringkasan.berubah ? 'border-brand-200 bg-brand-50/70' : 'border-slate-200 bg-slate-50'">
        <div class="flex items-start gap-3 min-w-0 flex-1">
          <Ikon :nama="ringkasan.berubah ? 'centang-bulat' : 'info'" class="w-5 h-5 shrink-0 mt-px" :class="ringkasan.berubah ? 'text-brand-600' : 'text-slate-400'" />
          <div class="min-w-0">
            <p class="text-[13px] font-semibold text-navy-900">{{ ringkasan.judul }}</p>
            <p class="text-[12.5px] text-slate-600 leading-snug">{{ ringkasan.rinci }}</p>
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-2 pl-8 sm:pl-0">
          <button v-if="Object.keys(penanda).length" type="button" class="btn-garis btn-kecil" @click="lompat('k-metode-kriteria')"><Ikon nama="mata" class="w-3.5 h-3.5" /> Lihat kriteria</button>
          <button v-if="ringkasan.berubah" type="button" class="btn-hantu btn-kecil" @click="urungkan"><Ikon nama="riwayat" class="w-3.5 h-3.5" /> Urungkan</button>
          <button type="button" class="btn-hantu btn-kecil px-2" aria-label="Tutup ringkasan" @click="ringkasan = null"><Ikon nama="tutup" class="w-4 h-4" /></button>
        </div>
      </div>

      <form @submit.prevent="simpan">
        <fieldset :disabled="!bisaSunting" class="space-y-5 min-w-0">
          <!-- Identitas & komponen 1–4 -->
          <section class="kartu divide-y divide-slate-100">
            <div class="grid lg:grid-cols-[minmax(0,15rem)_minmax(0,1fr)] gap-x-8 gap-y-4 p-5 sm:p-6">
              <KepalaBagian ikon="file-text" judul="Identitas Rencana Audit" ket="Baris pembuka Formulir Rencana Audit sesuai lampiran Kepdirjen." />
              <div class="grid md:grid-cols-[minmax(0,14rem)_minmax(0,1fr)] gap-4">
                <div><label class="label" for="r-nomor">Nomor formulir</label><input id="r-nomor" v-model="form.nomor" class="input" placeholder="Mis. SP-HSE-IV.059"></div>
                <div>
                  <label class="label" for="r-jenis">Jenis audit</label>
                  <select id="r-jenis" v-model="form.jenis_audit" class="input"><option v-for="j in jenisAudit" :key="j" :value="j">{{ j }}</option></select>
                </div>
                <div class="md:col-span-2"><label class="label" for="r-alamat">Alamat perusahaan</label><textarea id="r-alamat" v-model="form.alamat" rows="2" class="input" /></div>
                <div class="md:col-span-2"><label class="label" for="r-kecuali">Pengecualian</label><textarea id="r-kecuali" v-model="form.pengecualian" rows="2" class="input" placeholder="Area, kegiatan, atau elemen yang tidak termasuk audit (kosongkan bila tidak ada)" /></div>
              </div>
            </div>

            <div v-for="kunci in (['tujuan', 'kriteria', 'ruang_lingkup'] as const)" :id="`k-${kunci}`" :key="kunci"
                 class="scroll-mt-24 grid lg:grid-cols-[minmax(0,15rem)_minmax(0,1fr)] gap-x-8 gap-y-3 p-5 sm:p-6">
              <KepalaBagian :nomor="nomor(kunci)" :judul="komponen[kunci].judul" :ket="komponen[kunci].ket" :terisi="terisi[kunci]" />
              <textarea v-model="form[kunci]" rows="3" class="input" :aria-label="komponen[kunci].judul" />
            </div>

            <div id="k-tanggal" class="scroll-mt-24 grid lg:grid-cols-[minmax(0,15rem)_minmax(0,1fr)] gap-x-8 gap-y-3 p-5 sm:p-6">
              <KepalaBagian :nomor="nomor('tanggal')" :judul="komponen.tanggal.judul" :ket="komponen.tanggal.ket" :terisi="terisi.tanggal" />
              <div>
                <div class="grid grid-cols-2 gap-3 sm:gap-4 max-w-md">
                  <div><label class="label" for="r-mulai">Mulai</label><input id="r-mulai" v-model="form.tanggal_mulai" type="date" class="input"></div>
                  <div><label class="label" for="r-selesai">Selesai</label><input id="r-selesai" v-model="form.tanggal_selesai" type="date" class="input"></div>
                </div>
                <p v-if="form.errors.tanggal_selesai" class="galat">{{ form.errors.tanggal_selesai }}</p>
                <p class="bantu">Alokasi hari kerja Tahap II dari Tahap I: <b class="text-navy-900 tabular-nums">{{ angka(mandays.tahap2) }}</b> hari dari total {{ angka(mandays.total) }} hari.</p>
              </div>
            </div>
          </section>

          <!-- 5. Susunan kegiatan -->
          <section id="k-susunan" class="kartu scroll-mt-24 p-5 sm:p-6">
            <KepalaBagian :nomor="nomor('susunan')" :judul="komponen.susunan.judul" :ket="komponen.susunan.ket" :terisi="terisi.susunan" />
            <p class="bantu mt-3 mb-3">Kegiatan Tahap II: {{ kegiatan.join(' · ') }}.</p>
            <div class="rounded-xl border border-slate-200 overflow-hidden">
              <div class="gulir-x scrollbar-tipis">
                <table class="w-full min-w-[760px] text-[12.5px]">
                  <thead class="bg-slate-50/80">
                    <tr class="text-left text-[10.5px] font-semibold uppercase tracking-[0.08em] text-slate-400">
                      <th class="py-2 pl-3 pr-2 w-40">Tanggal</th><th class="py-2 px-2 w-32">Waktu</th><th class="py-2 px-2">Kegiatan</th><th class="py-2 px-2 w-[18%]">Auditi</th><th class="py-2 px-2 w-[18%]">Auditor</th><th class="w-10" />
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(b, i) in form.susunan" :key="i" class="border-t border-slate-100">
                      <td class="py-1.5 pl-3 pr-2"><input v-model="b.tanggal" type="date" class="input-kecil" :aria-label="`Tanggal kegiatan ${i + 1}`"></td>
                      <td class="py-1.5 px-2"><input v-model="b.waktu" class="input-kecil" placeholder="08.00–10.00" :aria-label="`Waktu kegiatan ${i + 1}`"></td>
                      <td class="py-1.5 px-2"><input v-model="b.kegiatan" class="input-kecil" :aria-label="`Kegiatan ${i + 1}`"></td>
                      <td class="py-1.5 px-2"><input v-model="b.auditi" class="input-kecil" :aria-label="`Auditi kegiatan ${i + 1}`"></td>
                      <td class="py-1.5 px-2"><input v-model="b.auditor" class="input-kecil" :aria-label="`Auditor kegiatan ${i + 1}`"></td>
                      <td class="py-1.5 pr-2 text-center"><button type="button" class="p-1.5 rounded-md text-slate-300 hover:text-rose-600 hover:bg-rose-50" aria-label="Hapus baris" @click="buang(form.susunan, i)"><Ikon nama="tutup" class="w-4 h-4" /></button></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <button type="button" class="mt-3 btn-garis btn-kecil" @click="tambah(form.susunan, KOSONG.susunan)"><Ikon nama="tambah" class="w-4 h-4" /> Baris kegiatan</button>
          </section>

          <!-- 6. Tim audit -->
          <section id="k-tugas" class="kartu scroll-mt-24 p-5 sm:p-6">
            <KepalaBagian :nomor="nomor('tugas')" :judul="komponen.tugas.judul" :ket="komponen.tugas.ket" :terisi="terisi.tugas" />
            <div class="mt-4 rounded-xl border border-slate-200 overflow-hidden">
              <div class="gulir-x scrollbar-tipis">
                <table class="w-full min-w-[720px] text-[12.5px]">
                  <thead class="bg-slate-50/80">
                    <tr class="text-left text-[10.5px] font-semibold uppercase tracking-[0.08em] text-slate-400">
                      <th class="py-2 pl-3 pr-2">Nama</th><th class="py-2 px-2 w-44">Peran</th><th class="py-2 px-2 w-60">No. registrasi auditor</th><th class="py-2 px-2">Elemen / lingkup</th><th class="w-10" />
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(b, i) in form.tugas" :key="i" class="border-t border-slate-100">
                      <td class="py-1.5 pl-3 pr-2"><input v-model="b.nama" class="input-kecil" :aria-label="`Nama auditor ${i + 1}`"></td>
                      <td class="py-1.5 px-2"><input v-model="b.peran" class="input-kecil" placeholder="Ketua Tim Audit" :aria-label="`Peran auditor ${i + 1}`"></td>
                      <td class="py-1.5 px-2"><input v-model="b.registrasi" class="input-kecil tabular-nums" placeholder="000/AUD-SMKP/37.04/DBT/2025" :aria-label="`Nomor registrasi auditor ${i + 1}`"></td>
                      <td class="py-1.5 px-2"><input v-model="b.lingkup" class="input-kecil" placeholder="Elemen I–IV" :aria-label="`Lingkup auditor ${i + 1}`"></td>
                      <td class="py-1.5 pr-2 text-center"><button type="button" class="p-1.5 rounded-md text-slate-300 hover:text-rose-600 hover:bg-rose-50" aria-label="Hapus auditor" @click="buang(form.tugas, i)"><Ikon nama="tutup" class="w-4 h-4" /></button></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <button type="button" class="mt-3 btn-garis btn-kecil" @click="tambah(form.tugas, KOSONG.tugas)"><Ikon nama="tambah" class="w-4 h-4" /> Auditor</button>
          </section>

          <!-- 7. Sumber daya -->
          <section id="k-sumberdaya" class="kartu scroll-mt-24 grid lg:grid-cols-[minmax(0,15rem)_minmax(0,1fr)] gap-x-8 gap-y-4 p-5 sm:p-6">
            <KepalaBagian :nomor="nomor('sumberdaya')" :judul="komponen.sumberdaya.judul" :ket="komponen.sumberdaya.ket" :terisi="terisi.sumberdaya" />
            <div class="space-y-4 min-w-0">
              <div><label class="label" for="r-fasilitas">Fasilitas</label><textarea id="r-fasilitas" v-model="form.sumberdaya" rows="4" class="input" /><p class="bantu">Satu fasilitas per baris.</p></div>
              <div class="grid md:grid-cols-2 gap-4">
                <div><label class="label" for="r-dok">Dokumentasi relevan</label><textarea id="r-dok" v-model="form.dokumentasi" rows="6" class="input" /><p class="bantu">Satu dokumen per baris.</p></div>
                <div><label class="label" for="r-dist">Distribusi laporan</label><textarea id="r-dist" v-model="form.distribusi" rows="6" class="input" /><p class="bantu">Satu penerima per baris.</p></div>
              </div>
            </div>
          </section>

          <!-- 8. Metode, sampel & top risks -->
          <section id="k-metode" class="kartu scroll-mt-24 p-5 sm:p-6 space-y-5">
            <KepalaBagian :nomor="nomor('metode')" :judul="komponen.metode.judul" :ket="komponen.metode.ket" :terisi="terisi.metode" />
            <div class="grid md:grid-cols-2 gap-4">
              <div><label class="label" for="r-metode">Metode audit</label><textarea id="r-metode" v-model="form.metode" rows="3" class="input" placeholder="Mis. tinjauan dokumen, wawancara, dan observasi lapangan" /></div>
              <div><label class="label" for="r-sampel">Dasar pengambilan sampel</label><textarea id="r-sampel" v-model="form.sampel" rows="3" class="input" placeholder="Mis. berbasis top risks dan kegiatan berisiko tinggi" /></div>
            </div>

            <div id="k-metode-kriteria" class="scroll-mt-24">
              <div class="flex flex-wrap items-end justify-between gap-x-4 gap-y-2 mb-2.5">
                <div>
                  <p class="text-[13.5px] font-semibold text-navy-900">Metode dan sampel per kriteria</p>
                  <p class="text-[12px] text-slate-500">Isi untuk setiap sub-elemen yang diaudit; kosongkan bila tidak diambil sampel.</p>
                </div>
                <div class="flex items-center gap-2.5">
                  <span class="bilah w-24"><i :class="kriteriaTerisi === totalKriteria ? 'bg-hijau-500' : 'bg-teal-500'" :style="{ width: `${totalKriteria ? Math.round((kriteriaTerisi / totalKriteria) * 100) : 0}%` }" /></span>
                  <span class="text-[12.5px] font-semibold text-navy-900 tabular-nums">{{ kriteriaTerisi }}/{{ totalKriteria }}</span>
                  <span class="text-[12px] text-slate-500">terisi</span>
                </div>
              </div>
              <TabelMetodeSampel :daftar="kriteriaDaftar" :nilai="form.metode_sampel" :penanda="penanda" :saran="METODE_SARAN" />
            </div>

            <div class="grid gap-4">
              <div v-for="slot in TOP_RISKS" :key="slot.kunci" class="rounded-xl border border-slate-200 overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 bg-slate-50/80 border-b border-slate-200">
                  <div class="flex items-center gap-2.5">
                    <span class="grid place-items-center w-7 h-7 rounded-lg text-[12px] font-bold text-white" :class="slot.warna">{{ slot.huruf }}</span>
                    <div>
                      <p class="text-[13px] font-semibold text-navy-900">{{ slot.judul }}</p>
                      <p class="text-[11.5px] text-slate-500">{{ slot.ket }}</p>
                    </div>
                  </div>
                  <span class="lencana bg-white border border-slate-200 text-slate-600 tabular-nums">{{ form.risiko[slot.kunci].filter((r) => r.kegiatan || r.risiko || r.nilai).length }} risiko</span>
                </div>
                <div class="gulir-x scrollbar-tipis">
                  <table class="w-full min-w-[600px] text-[12.5px]">
                    <thead>
                      <tr class="text-left text-[10.5px] font-semibold uppercase tracking-[0.08em] text-slate-400">
                        <th class="py-2 pl-4 pr-2 w-12">No</th><th class="py-2 px-2 w-[34%]">Kegiatan</th><th class="py-2 px-2">Risiko</th><th class="py-2 px-2 w-28">Nilai risiko</th><th class="w-10" />
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(b, i) in form.risiko[slot.kunci]" :key="i" class="border-t border-slate-100">
                        <td class="py-1.5 pl-4 pr-2 text-slate-400 font-semibold tabular-nums">{{ i + 1 }}</td>
                        <td class="py-1.5 px-2"><input v-model="b.kegiatan" class="input-kecil" :aria-label="`Kegiatan ${i + 1}`"></td>
                        <td class="py-1.5 px-2"><input v-model="b.risiko" class="input-kecil" :aria-label="`Risiko ${i + 1}`"></td>
                        <td class="py-1.5 px-2"><input v-model="b.nilai" inputmode="numeric" class="input-kecil tabular-nums text-center" :aria-label="`Nilai risiko ${i + 1}`"></td>
                        <td class="py-1.5 pr-2 text-center"><button type="button" class="p-1.5 rounded-md text-slate-300 hover:text-rose-600 hover:bg-rose-50" aria-label="Hapus risiko" @click="buang(form.risiko[slot.kunci], i)"><Ikon nama="tutup" class="w-4 h-4" /></button></td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div class="px-4 py-2.5 border-t border-slate-100">
                  <button type="button" class="btn-garis btn-kecil" @click="tambah(form.risiko[slot.kunci], KOSONG.risiko)"><Ikon nama="tambah" class="w-4 h-4" /> Tambah risiko</button>
                </div>
              </div>
            </div>
            <p class="bantu">Pada cetak Rencana Audit, top risks tampil sebagai tabel No · Kegiatan · Risiko · Nilai Risiko dengan baris sebanyak isian di atas.</p>
            <p v-if="Object.keys(form.errors).some((k) => k.startsWith('risiko'))" class="galat">Nilai risiko harus angka 0–100.</p>
          </section>

          <!-- 9. Pengesahan -->
          <section id="k-pengesahan" class="kartu scroll-mt-24 p-5 sm:p-6">
            <KepalaBagian :nomor="nomor('pengesahan')" :judul="komponen.pengesahan.judul" :ket="komponen.pengesahan.ket" :terisi="terisi.pengesahan" />
            <div class="mt-4 grid md:grid-cols-2 xl:grid-cols-3 gap-3">
              <div v-for="(def, k) in pengesah" :key="k" role="group" :aria-label="def.peran" class="rounded-xl border border-slate-200 p-4 space-y-2.5 min-w-0">
                <p class="text-[13px] font-semibold text-navy-900 leading-snug">
                  {{ def.peran }}<span v-if="def.wajib" class="text-rose-500"> *</span>
                  <span v-else class="block text-[11.5px] font-normal text-slate-400">Bila auditi perusahaan jasa pertambangan</span>
                </p>
                <input v-model="form.pengesahan[k].nama" class="input" placeholder="Nama" :aria-label="`Nama ${def.peran}`">
                <input v-model="form.pengesahan[k].jabatan" class="input" placeholder="Jabatan" :aria-label="`Jabatan ${def.peran}`">
                <input v-model="form.pengesahan[k].tanggal" type="date" class="input" :aria-label="`Tanggal pengesahan ${def.peran}`">
              </div>
            </div>
          </section>
        </fieldset>

        <div v-if="bisaSunting" class="sticky bottom-4 z-10 mt-5 flex flex-wrap items-center justify-between gap-3 rounded-kartu bg-navy-900 text-white px-5 py-3.5 shadow-angkat">
          <p class="text-[13px]" :class="form.isDirty ? 'text-amber-300 font-semibold' : 'text-white/70'">
            {{ form.isDirty ? (Object.keys(penanda).length ? 'Isian hasil pakai ulang belum disimpan.' : 'Ada perubahan yang belum disimpan.') : 'Rencana Audit tersimpan.' }}
          </p>
          <button class="btn-hijau" :disabled="form.processing || !form.isDirty"><Ikon nama="simpan" class="w-4 h-4" /> {{ form.processing ? 'Menyimpan…' : 'Simpan Rencana Audit' }}</button>
        </div>
      </form>
    </div>

    <template v-if="bisaSunting">
      <DialogPakaiUlang v-model:buka="bukaSalin" :audit-id="audit.id" @terapkan="terapkanSalin" />
      <DialogImpor v-model:buka="bukaImpor" :audit-id="audit.id" :daftar="kriteriaDaftar" :nilai="form.metode_sampel" :ai-aktif="aiAktif" @terapkan="terapkanImpor" />
    </template>
  </div>
</template>

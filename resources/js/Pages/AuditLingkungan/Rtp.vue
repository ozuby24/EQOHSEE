<script setup lang="ts">
/**
 * Rencana Tindak Perbaikan (RTP) Audit Kinerja Lingkungan — mengikuti
 * Audit Lingkungan DBEST.
 *
 * Satu kartu per temuan (kriteria bernilai verifikasi ≤ 2). Auditee
 * mengisi rencana, PIC, dan target lalu mengunggah bukti — unggahan itu
 * yang mengajukan verifikasi. Auditor memutuskan dari panel "Verifikasi &
 * nilai" di kartu yang sama: nilai sekarang, arti tiap nilai 0–3, catatan,
 * lalu Setujui (temuan ditutup, nilai lembar audit ikut berubah) atau Tolak.
 *
 * Nilai hanya dapat NAIK bila diverifikasi paling lambat pada target
 * selesai; lewat target, RTP tetap dapat diputuskan tetapi nilainya
 * bertahan. Panel menyebutkannya sebelum auditor menekan tombol.
 */
import { reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import './akl.css';

interface Kartu {
  id: number; kode: string; temuan: string; nilaiAwal: number; nilaiKini: number | null;
  rencana: string; pic: string; target: string | null; status: string; labelStatus: string; terlambat: boolean;
  selesai: string | null; penutup: string; verifikasi: string | null; catatanVerifikasi: string; verifikator: string;
  diverifikasi: string | null; diajukan: string | null; bukti: Array<{ nama: string; url: string | null }>;
  targetTerkunci: boolean; bolehNaik: boolean; urlKriteria: string;
}

const props = defineProps<{
  judul: string; subjudul: string;
  audit: { id: number; kode: string; judul: string; perusahaan: string | null };
  saring: { status: string };
  hitung: Record<string, number>;
  arti: Record<number, string>;
  bisaVerifikasi: boolean;
  hariIni: string;
  rtp: Kartu[];
  tautan: { ikhtisar: string; rtp: string };
}>();

const TAB: Array<[string, string]> = [['aktif', 'Aktif'], ['menunggu', 'Menunggu verifikasi'], ['telat', 'Lewat target'], ['selesai', 'Selesai'], ['semua', 'Semua']];

function saringKe(status: string) {
  router.get(props.tautan.rtp, { status }, { preserveScroll: true, preserveState: true, replace: true });
}

/* Isian per kartu — disimpan per kartu, bukan sekaligus. */
const isian = reactive<Record<number, { rencana: string; pic: string; target: string }>>({});
const putusan = reactive<Record<number, { nilai: number | null; catatan: string }>>({});
for (const r of props.rtp) {
  isian[r.id] = { rencana: r.rencana, pic: r.pic, target: r.target ?? '' };
  putusan[r.id] = { nilai: r.nilaiKini ?? r.nilaiAwal, catatan: '' };
}
const sibuk = ref<number | null>(null);
const galat = ref<Record<string, string>>({});

const url = (r: Kartu, akhir = '') => `${props.tautan.rtp}/${r.id}${akhir}`;
const selesai = () => { sibuk.value = null; };

function simpan(r: Kartu) {
  sibuk.value = r.id;
  galat.value = {};
  router.put(url(r), { ...isian[r.id], target: isian[r.id].target || null }, {
    preserveScroll: true, onError: (e) => { galat.value = e; }, onFinish: selesai,
  });
}

function unggah(r: Kartu, e: Event) {
  const f = (e.target as HTMLInputElement).files;
  if (!f?.length) return;
  sibuk.value = r.id;
  router.post(url(r, '/bukti'), { berkas: Array.from(f) }, {
    forceFormData: true, preserveScroll: true, onError: (x) => { galat.value = x; }, onFinish: selesai,
  });
}

function hapusBukti(r: Kartu, indeks: number) {
  router.post(url(r, '/bukti/hapus'), { indeks }, { preserveScroll: true });
}

function putuskan(r: Kartu, keputusan: 'disetujui' | 'ditolak') {
  sibuk.value = r.id;
  galat.value = {};
  router.post(url(r, '/verifikasi'), { keputusan, nilai: putusan[r.id].nilai, catatan: putusan[r.id].catatan || null }, {
    preserveScroll: true, onError: (e) => { galat.value = e; }, onFinish: selesai,
  });
}

const LENCANA_VERIFIKASI: Record<string, [string, string]> = {
  menunggu: ['Menunggu verifikasi', 'background:#FEF3C7;color:#92400E'],
  disetujui: ['Disetujui', 'background:#DCFCE7;color:#166534'],
  ditolak: ['Ditolak — perlu perbaikan', 'background:#FEE2E2;color:#991B1B'],
};
</script>

<template>
  <div>
    <Head :title="`RTP · ${audit.kode}`" />

    <section class="eq-panel mb-4">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
          <p class="text-[12px] uppercase tracking-wide opacity-60">Rencana Tindak Perbaikan</p>
          <h1 class="text-[20px] font-bold leading-tight">{{ audit.perusahaan ?? audit.judul }}</h1>
          <p class="text-[13px] opacity-70">{{ audit.kode }} · {{ audit.judul }}</p>
        </div>
        <a :href="tautan.ikhtisar" class="eq-btn-mini">← Ikhtisar</a>
      </div>
      <p class="mt-3 text-[12.5px] opacity-75 leading-relaxed">
        Temuan terbit otomatis untuk kriteria dengan nilai verifikasi 0–2. Isi rencana, PIC, dan target, lalu unggah bukti
        penutupan untuk diajukan ke auditor. Nilai hanya dapat naik bila diverifikasi paling lambat pada target selesai.
      </p>
      <div class="flex flex-wrap gap-2 mt-4">
        <button v-for="[k, l] in TAB" :key="k" type="button" class="eq-btn-mini" :style="saring.status === k ? 'background:#0F172A;color:#fff' : ''" @click="saringKe(k)">
          {{ l }} <span class="opacity-60 num">({{ hitung[k] ?? 0 }})</span>
        </button>
      </div>
    </section>

    <p v-if="!rtp.length" class="eq-panel text-center text-[13px] opacity-70 py-10">
      {{ hitung.semua ? 'Tidak ada RTP pada saringan ini.' : 'Belum ada temuan: seluruh kriteria terverifikasi bernilai 3, atau belum diverifikasi.' }}
    </p>

    <article v-for="r in rtp" :id="`rtp-${r.id}`" :key="r.id" class="eq-panel mb-4">
      <header class="flex flex-wrap items-start justify-between gap-2">
        <div class="min-w-0">
          <p class="font-bold num">{{ r.kode }} <span class="text-[12px] font-normal opacity-60">· nilai awal {{ r.nilaiAwal }}<template v-if="r.nilaiKini !== null"> · nilai sekarang {{ r.nilaiKini }}</template></span></p>
          <p class="text-[13px] leading-snug opacity-80">{{ r.temuan }}</p>
        </div>
        <div class="flex flex-wrap gap-1.5">
          <span class="akl-keping">{{ r.labelStatus }}</span>
          <span v-if="r.terlambat" class="akl-keping" style="background:#FEE2E2;color:#991B1B">Lewat target</span>
          <span v-if="r.verifikasi && LENCANA_VERIFIKASI[r.verifikasi]" class="akl-keping" :style="LENCANA_VERIFIKASI[r.verifikasi][1]">{{ LENCANA_VERIFIKASI[r.verifikasi][0] }}</span>
          <a :href="r.urlKriteria" class="eq-btn-mini">Buka kriteria</a>
        </div>
      </header>

      <p v-if="r.status === 'selesai'" class="mt-3 text-[12.5px] opacity-80">
        Ditutup {{ r.selesai }}<template v-if="r.verifikator"> · diverifikasi {{ r.verifikator }} {{ r.diverifikasi }}</template>.
        <template v-if="r.penutup"> {{ r.penutup }}</template>
      </p>
      <p v-if="r.verifikasi === 'ditolak' && r.catatanVerifikasi" class="mt-3 text-[12.5px] rounded-lg px-3 py-2" style="background:#FEF2F2;color:#991B1B">
        Alasan penolakan ({{ r.verifikator }}, {{ r.diverifikasi }}): {{ r.catatanVerifikasi }}
      </p>

      <div class="grid md:grid-cols-[1fr_200px_170px] gap-3 mt-4">
        <label class="block">
          <span class="text-[12px] font-semibold opacity-70">Rencana tindak perbaikan</span>
          <textarea v-model="isian[r.id].rencana" rows="2" class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition" :disabled="r.status === 'selesai'" maxlength="3000" />
        </label>
        <label class="block">
          <span class="text-[12px] font-semibold opacity-70">PIC</span>
          <input v-model="isian[r.id].pic" class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition" :disabled="r.status === 'selesai'" maxlength="150">
        </label>
        <label class="block">
          <span class="text-[12px] font-semibold opacity-70">Target selesai</span>
          <input v-model="isian[r.id].target" type="date" class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition" :disabled="r.status === 'selesai' || r.targetTerkunci">
          <span v-if="r.targetTerkunci" class="block text-[11px] opacity-60">Sudah lewat — hanya auditor yang dapat mengubah.</span>
        </label>
      </div>
      <p v-if="sibuk === null && galat.target" class="text-[12px] text-red-700 mt-1">{{ galat.target }}</p>

      <div class="flex flex-wrap items-center gap-2 mt-3">
        <button v-if="r.status !== 'selesai'" type="button" class="eq-btn-utama" style="flex:none" :disabled="sibuk === r.id" @click="simpan(r)">Simpan rencana</button>
        <label v-if="r.status !== 'selesai'" class="eq-btn-lain cursor-pointer" style="flex:none">
          ⬆ Unggah bukti &amp; ajukan
          <input type="file" multiple class="hidden" @change="unggah(r, $event)">
        </label>
        <span v-if="r.diajukan" class="text-[11.5px] opacity-60">Diajukan {{ r.diajukan }}</span>
      </div>

      <ul v-if="r.bukti.length" class="mt-3 flex flex-wrap gap-2">
        <li v-for="(b, i) in r.bukti" :key="i" class="akl-keping">
          <a :href="b.url ?? '#'" target="_blank" rel="noopener" class="underline">{{ b.nama }}</a>
          <button v-if="r.status !== 'selesai'" type="button" class="ml-1 opacity-60 hover:opacity-100" :aria-label="`Hapus ${b.nama}`" @click="hapusBukti(r, i)">×</button>
        </li>
      </ul>

      <!-- Panel auditor -->
      <section v-if="bisaVerifikasi && r.status !== 'selesai'" class="mt-4 rounded-xl border px-4 py-3" style="border-color:rgba(15,23,42,.12)">
        <p class="text-[13px] font-bold">Verifikasi &amp; nilai</p>
        <p class="text-[12px] opacity-70 mb-2">Nilai sekarang: <b>{{ r.nilaiKini ?? '—' }}</b>. Pilih nilai verifikasi setelah memeriksa bukti.</p>
        <div class="flex flex-wrap gap-2">
          <button v-for="(teks, n) in arti" :key="n" type="button" class="eq-btn-mini"
                  :style="putusan[r.id].nilai === Number(n) ? 'background:#0F172A;color:#fff' : ''" @click="putusan[r.id].nilai = Number(n)">
            <b class="num">{{ n }}</b> · {{ teks }}
          </button>
        </div>
        <p v-if="!r.bolehNaik" class="mt-2 text-[12px]" style="color:#92400E">
          Target selesai sudah lewat: RTP tetap dapat diputuskan, tetapi nilai tidak akan dinaikkan.
        </p>
        <textarea v-model="putusan[r.id].catatan" rows="2" class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition mt-2" placeholder="Catatan (wajib bila menolak)" maxlength="2000" />
        <p v-if="galat.catatan" class="text-[12px] text-red-700">{{ galat.catatan }}</p>
        <div class="flex flex-wrap gap-2 mt-2">
          <button type="button" class="eq-btn-utama" style="flex:none" :disabled="sibuk === r.id || putusan[r.id].nilai === null" @click="putuskan(r, 'disetujui')">✓ Setujui &amp; tutup</button>
          <button type="button" class="eq-btn-lain" style="flex:none" :disabled="sibuk === r.id" @click="putuskan(r, 'ditolak')">✕ Tolak</button>
        </div>
      </section>
    </article>
  </div>
</template>

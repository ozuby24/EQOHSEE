<script setup lang="ts">
import { computed, reactive } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
const { dialog, tanya, minta, batal, lanjut } = useDialog();


/*
  Prop halaman diambil lewat usePage(), bukan defineProps — lihat
  tests/Feature/PropHalamanTest.php untuk sebabnya. Ringkasnya: bentuk
  `defineProps<{ mode: string; [key: string]: any }>()` hanya
  mendaftarkan `mode`, dan sisanya hilang tanpa galat apa pun.
*/
const props = usePage<any>().props as any;
const isAdmin = computed(() => Boolean(props.pengguna?.admin));

const judul: Record<string, string> = {
  dashboard: 'Pengendalian Biaya',
  realisasi: 'Realisasi Bulanan',
  anggaran: 'Anggaran Tahunan',
  akun: 'Bagan Akun Biaya',
};

const angka = (v: unknown, d = 0) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: d, minimumFractionDigits: d }).format(Number(v || 0));
const rp = (v: unknown) => v === null || v === undefined ? '—' : `Rp ${angka(v)}`;
const label = (v: string) => String(v || '').replaceAll('-', ' ').replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
const namaBulan = (b: number) => ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][b] || String(b);

const tautan = computed(() => props.tautan || {});
const untuk = (pola: string | undefined, id: number | string) => String(pola || '').replace('__ID__', String(id));
const sibuk = reactive<Record<number, boolean>>({});

const akun = useForm<any>({ company_id: '', kode: '', nama: '', kelompok: 'bahan-bakar', jenis: 'variabel', satuan: '', catatan: '' });
const anggaran = useForm<any>({ company_id: '', biaya_akun_id: '', tahun: props.tahun, pusat_biaya: 'penambangan', nilai_rp: '', kuantitas_rencana: '', catatan: '' });
const realisasi = useForm<any>({ company_id: '', biaya_akun_id: '', tahun: props.tahun, bulan: new Date().getMonth() + 1, pusat_biaya: 'penambangan', nilai_rp: '', kuantitas: '', catatan: '' });
const tindak = useForm<any>({ company_id: '', kode_pemicu: '', judul: '', prioritas: 'sedang', penanggung_jawab: '', target_selesai: '', uraian: '' });

const ditangani = computed(() => new Set(props.kodeDitangani || []));
const akunTerpilih = computed(() => (props.daftarAkun || []).find((a: any) => String(a.id) === String(realisasi.biaya_akun_id)));
const puncakBulan = computed(() => Math.max(1, ...(props.bulan || []).map((b: any) => Number(b.realisasi || 0))));
const tinggiBatang = (v: unknown) => {
  const n = Number(v || 0);
  return n > 0 ? Math.max(4, Math.round((n / puncakBulan.value) * 120)) : 2;
};

function gantiTahun(t: number | string) {
  router.get(window.location.pathname, { tahun: t }, { preserveState: false });
}
function simpanAkun() { akun.post(tautan.value.akunSimpan, { preserveScroll: true, onSuccess: () => akun.reset('kode', 'nama', 'satuan', 'catatan') }); }
async function hapusAkun(a: any) { if (await tanya(`Hapus akun ${a.kode}? Anggaran dan realisasinya ikut terhapus.`)) router.delete(untuk(tautan.value.akunHapus, a.id), { preserveScroll: true }); }
function simpanAnggaran() { anggaran.post(tautan.value.anggaranSimpan, { preserveScroll: true, onSuccess: () => anggaran.reset('nilai_rp', 'kuantitas_rencana', 'catatan') }); }
function hapusAnggaran(a: any) { router.delete(untuk(tautan.value.anggaranHapus, a.id), { preserveScroll: true }); }
function simpanRealisasi() { realisasi.post(tautan.value.realisasiSimpan, { preserveScroll: true, onSuccess: () => realisasi.reset('nilai_rp', 'kuantitas', 'catatan') }); }
async function hapusRealisasi(r: any) { if (await tanya('Hapus realisasi ini?')) router.delete(untuk(tautan.value.realisasiHapus, r.id), { preserveScroll: true }); }

function alur(pola: string, baris: any, isi: Record<string, any> = {}) {
  sibuk[baris.id] = true;
  router.post(untuk(pola, baris.id), isi, { preserveScroll: true, onFinish: () => { sibuk[baris.id] = false; } });
}
async function setujui(baris: any) {
  if (!await tanya('Setujui realisasi ini? Setelah disetujui tidak dapat diubah.')) return;
  alur(tautan.value.realisasiSetujui, baris);
}
async function tolak(baris: any) {
  const a = await minta({ judul: 'Tolak realisasi biaya?', label: 'Alasan penolakan',
    jenis: 'panjang', min: 5, labelAksi: 'Tolak', nada: 'bahaya' });
  if (a === null) return;
  alur(tautan.value.realisasiTolak, baris, { alasan_tolak: a });
}

function tindakDari(a: any) {
  tindak.kode_pemicu = a.kode; tindak.judul = a.judul;
  tindak.prioritas = a.level === 'tinggi' ? 'tinggi' : 'sedang'; tindak.uraian = a.saran || '';
  document.getElementById('form-tindak')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
function simpanTindak() { tindak.post(tautan.value.tindakSimpan, { preserveScroll: true, onSuccess: () => tindak.reset('kode_pemicu', 'judul', 'penanggung_jawab', 'target_selesai', 'uraian') }); }
function ubahTindak(t: any, s: string) { router.put(untuk(tautan.value.tindakUbah, t.id), { status: s }, { preserveScroll: true }); }

const warnaStatus: Record<string, string> = {
  draf: 'bg-stone-100 text-stone-600', diajukan: 'bg-amber-100 text-amber-700',
  disetujui: 'bg-emerald-100 text-emerald-700', ditolak: 'bg-red-100 text-red-700',
};
const warnaSerapan: Record<string, string> = {
  sepadan: 'text-emerald-700', mendahului: 'text-red-600',
  tertinggal: 'text-amber-700', 'tak-diketahui': 'text-stone-400',
};
</script>

<template>
  <Head :title="judul[props.mode]" />

  <div class="max-w-[1400px] mx-auto space-y-5">
    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-[10px] font-bold uppercase tracking-[.16em] text-cam-orange">Engineering · Cost Control</p>
        <h2 class="text-2xl font-extrabold tracking-tight text-stone-800">{{ judul[props.mode] }}</h2>
        <p class="text-[12px] text-stone-500 mt-1">Anggaran terhadap realisasi, biaya per ton, dan selisih yang dipecah menjadi bagian volume dan bagian tarif.</p>
      </div>
      <div class="flex items-center gap-2">
        <label class="text-[11px] font-bold text-stone-500">Tahun anggaran</label>
        <select class="rounded-lg border-stone-200 text-[12px]" :value="props.tahun"
                @change="gantiTahun(($event.target as HTMLSelectElement).value)" aria-label="Tahun">
          <option v-for="t in props.opsi?.tahunPilihan || []" :key="t" :value="t">{{ t }}</option>
        </select>
      </div>
    </section>

    <!--
      Batas modul dinyatakan di muka. Tanpa itu angka di halaman ini
      mudah dibawa ke rapat sebagai angka keuangan, dan yang mengikat
      bukan ini melainkan catatan keuangan.
    -->
    <section class="rounded-2xl border border-sky-100 bg-sky-50 px-4 py-3">
      <p class="text-[11.5px] text-stone-700 leading-relaxed">
        <b>Untuk pengendalian operasi, bukan pembukuan.</b>
        Angka di sini menjawab mengapa biaya bergerak dan bagian mana yang masih dapat dikendalikan dari pit.
        Tonase pembaginya diambil dari catatan Mine Operations yang sudah disetujui — tidak dicatat ulang di modul ini.
      </p>
    </section>

    <section v-if="(props.alerts || []).length" class="grid gap-3 md:grid-cols-2">
      <div v-for="a in props.alerts" :key="a.kode" class="rounded-2xl border p-4"
           :class="a.level === 'tinggi' ? 'border-red-100 bg-red-50' : 'border-amber-100 bg-amber-50'">
        <b class="text-[12px]" :class="a.level === 'tinggi' ? 'text-red-700' : 'text-amber-700'">{{ a.judul }}</b>
        <p class="text-[11px] text-stone-600 mt-1">{{ a.ket }}</p>
        <p v-if="a.saran" class="text-[11px] text-stone-700 mt-2 pt-2 border-t border-black/5"><span class="font-bold">Tindakan: </span>{{ a.saran }}</p>
        <div class="mt-2">
          <span v-if="ditangani.has(a.kode)" class="inline-block rounded-full bg-emerald-100 text-emerald-700 px-2 py-1 text-[10px] font-bold">Sedang ditangani</span>
          <button v-else type="button" class="text-[11px] font-bold text-cam-orange-dark py-1.5" @click="tindakDari(a)">+ Buat tindak lanjut</button>
        </div>
      </div>
    </section>

    <nav class="flex flex-wrap gap-2">
      <Link v-for="i in [['dashboard','Ringkasan',tautan.dashboard],['realisasi','Realisasi',tautan.realisasi],['anggaran','Anggaran',tautan.anggaran],['akun','Bagan Akun',tautan.akun]]"
            :key="i[0]" :href="i[2] as string" class="rounded-full px-4 py-2 text-[11px] font-bold"
            :class="props.mode === i[0] ? 'bg-cam-ink text-white' : 'bg-white text-stone-500 border border-stone-200'">{{ i[1] }}</Link>
      <a :href="tautan.cetak" class="ml-auto rounded-full px-4 py-2 text-[11px] font-bold bg-white text-cam-orange border border-cam-orange/40">Cetak laporan</a>
    </nav>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
      <article v-for="c in [
        { l: 'Pagu anggaran', v: rp(props.total?.anggaran), c: 'text-cam-ink' },
        { l: 'Realisasi disetujui', v: rp(props.total?.realisasi), c: 'text-cam-ink' },
        { l: 'Serapan', v: props.total?.serapan === null ? '—' : `${angka(props.total?.serapan, 1)}%`, c: warnaSerapan[props.bacaSerapan?.kelas] },
        { l: 'Kemajuan produksi', v: props.produksi?.kemajuan === null ? '—' : `${angka(props.produksi?.kemajuan, 1)}%`, c: 'text-sky-700' },
        { l: 'Biaya per ton', v: props.total?.perTon === null ? '—' : rp(props.total?.perTon), c: 'text-violet-700' },
        { l: 'Proyeksi akhir tahun', v: rp(props.total?.proyeksi), c: (props.total?.proyeksi || 0) > (props.total?.anggaran || 0) ? 'text-red-600' : 'text-emerald-600' },
      ]" :key="c.l" class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
        <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">{{ c.l }}</p>
        <p class="mt-2 text-[15px] font-extrabold" :class="c.c">{{ c.v }}</p>
      </article>
    </section>

    <!-- ═══════════ RINGKASAN ═══════════ -->
    <template v-if="props.mode === 'dashboard'">
      <!--
        Pemecahan selisih berdiri paling atas. Biaya yang melampaui
        anggaran karena material yang dipindahkan lebih banyak adalah
        kabar yang berbeda sama sekali dari biaya yang melampaui anggaran
        pada volume yang sama.
      -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 class="font-bold text-[14px]">Selisih terhadap anggaran</h3>
            <p class="text-[11.5px] text-stone-500 mt-1">
              Anggaran diluweskan lebih dulu ke tonase yang benar-benar terjadi, lalu selisihnya dipecah dua.
              Hanya bagian <b>tarif</b> yang boleh dibaca sebagai kinerja.
            </p>
          </div>
          <span class="rounded-full px-3 py-1 text-[11px] font-bold"
                :class="props.bacaSerapan?.kelas === 'sepadan' ? 'bg-emerald-100 text-emerald-700'
                       : props.bacaSerapan?.kelas === 'mendahului' ? 'bg-red-100 text-red-700'
                       : props.bacaSerapan?.kelas === 'tertinggal' ? 'bg-amber-100 text-amber-700'
                       : 'bg-stone-100 text-stone-600'">{{ props.bacaSerapan?.label }}</span>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <div v-for="k in [
            { l: 'Selisih total', v: rp(props.total?.varians?.total), s: 'Realisasi dikurangi pagu' },
            { l: 'Bagian volume', v: props.total?.varians?.volume === null ? '—' : rp(props.total?.varians?.volume), s: 'Terjelaskan oleh banyaknya material' },
            { l: 'Bagian tarif', v: props.total?.varians?.tarif === null ? '—' : rp(props.total?.varians?.tarif), s: 'Tidak terjelaskan oleh volume' },
            { l: 'Anggaran diluweskan', v: props.total?.varians?.anggaranLuwes === null ? '—' : rp(props.total?.varians?.anggaranLuwes), s: 'Pagu pada tonase nyata' },
          ]" :key="k.l" class="rounded-xl bg-stone-50 px-3 py-2.5">
            <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">{{ k.l }}</p>
            <p class="text-[14px] font-extrabold mt-0.5">{{ k.v }}</p>
            <p class="text-[10.5px] text-stone-500 mt-0.5">{{ k.s }}</p>
          </div>
        </div>
        <p class="text-[11.5px] text-stone-600 mt-3">{{ props.total?.varians?.alasan }} {{ props.bacaSerapan?.ket }}</p>
      </section>

      <section class="grid gap-5 lg:grid-cols-3">
        <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
          <h3 class="font-bold text-[14px]">Produksi pembagi</h3>
          <p class="text-[11.5px] text-stone-500 mt-1">Dari Mine Operations yang sudah disetujui ({{ props.produksi?.catatan || 0 }} catatan shift).</p>
          <div class="mt-3 space-y-2 text-[11.5px]">
            <div v-for="b in [
              { l: 'Tonase nyata', v: `${angka(props.produksi?.tonNyata)} t`, r: `rencana ${angka(props.produksi?.tonRencana)} t` },
              { l: 'Overburden nyata', v: `${angka(props.produksi?.bcmNyata)} bcm`, r: `rencana ${angka(props.produksi?.bcmRencana)} bcm` },
              { l: 'Nisbah kupas', v: props.produksi?.srNyata === null ? '—' : angka(props.produksi?.srNyata, 2), r: props.produksi?.srRencana === null ? 'rencana —' : `rencana ${angka(props.produksi?.srRencana, 2)}` },
            ]" :key="b.l" class="flex items-baseline justify-between border-b border-stone-50 pb-1.5">
              <span class="text-stone-500">{{ b.l }}</span>
              <span class="text-right"><b>{{ b.v }}</b><span class="block text-[10.5px] text-stone-400">{{ b.r }}</span></span>
            </div>
          </div>
          <p v-if="props.produksi?.selisihSr !== null && Math.abs(props.produksi?.selisihSr) > (props.opsi?.ambangNisbah || 10)"
             class="mt-3 rounded-xl bg-amber-50 border border-amber-100 px-3 py-2 text-[11px] text-stone-700">
            Nisbah bergeser {{ angka(props.produksi?.selisihSr, 1) }}% dari rencana — biaya per ton tahun ini tidak
            sebanding dengan tahun lain. Pakai biaya per BCM ({{ rp(props.total?.perBcm) }}) untuk menilai kinerja.
          </p>
        </div>

        <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 lg:col-span-2">
          <h3 class="font-bold text-[14px]">Realisasi per bulan</h3>
          <p class="text-[11.5px] text-stone-500 mt-1">
            {{ props.bulanTerisi || 0 }} bulan sudah lengkap; proyeksi akhir tahun memakai laju rata-rata bulan itu saja.
          </p>
          <!--
            Tinggi batang dalam piksel, bukan persen. Persen menuntut
            tinggi induk yang pasti; di dalam kolom flex yang tingginya
            ditentukan isinya, seluruh batang runtuh menjadi garis tipis
            tanpa satu pun galat.
          -->
          <div class="mt-4 flex items-end gap-1.5">
            <div v-for="b in props.bulan || []" :key="b.bulan" class="flex-1 flex flex-col items-center gap-1">
              <div class="w-full eq-batang"
                   :style="{ height: `${tinggiBatang(b.realisasi)}px` }"
                   :title="`${namaBulan(b.bulan)}: ${rp(b.realisasi)}`"></div>
              <span class="text-[9.5px] text-stone-400">{{ namaBulan(b.bulan) }}</span>
              <span v-if="b.menunggu" class="text-[9px] font-bold text-amber-700">{{ b.menunggu }}</span>
            </div>
          </div>
        </div>
      </section>

      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <div class="px-5 py-4 border-b border-stone-100">
          <h3 class="font-bold text-[14px]">Akun biaya</h3>
          <p class="text-[11.5px] text-stone-500 mt-1">
            Diurutkan menurut realisasi terbesar. Selisih tarif di atas {{ angka(props.opsi?.ambangTarif) }}% pagu ditandai.
          </p>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-[11.5px]">
            <thead class="bg-stone-50 text-left text-stone-400 uppercase text-[10px] tracking-wide">
              <tr>
                <th class="px-5 py-2.5">Akun</th><th class="px-5 py-2.5">Kelompok</th>
                <th class="px-5 py-2.5 text-right">Pagu</th><th class="px-5 py-2.5 text-right">Realisasi</th>
                <th class="px-5 py-2.5 text-right">Serapan</th><th class="px-5 py-2.5 text-right">Volume</th>
                <th class="px-5 py-2.5 text-right">Tarif</th><th class="px-5 py-2.5 text-right">Per ton</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="a in props.akun || []" :key="a.akunId" class="border-b border-stone-50" :class="a.tarifMenonjol ? 'bg-amber-50/60' : ''">
                <td class="px-5 py-3"><b>{{ a.akun }}</b><span class="block text-[10.5px] text-stone-400">{{ a.nama }}</span></td>
                <td class="px-5 py-3 text-stone-500">{{ label(a.kelompok) }}</td>
                <td class="px-5 py-3 text-right">{{ rp(a.anggaran) }}</td>
                <td class="px-5 py-3 text-right font-semibold">{{ rp(a.realisasi) }}</td>
                <td class="px-5 py-3 text-right">{{ a.serapan === null ? '—' : `${angka(a.serapan, 1)}%` }}</td>
                <td class="px-5 py-3 text-right text-stone-500">{{ a.varians?.volume === null ? '—' : rp(a.varians?.volume) }}</td>
                <td class="px-5 py-3 text-right font-bold" :class="a.tarifMenonjol ? ((a.varians?.tarif || 0) > 0 ? 'text-red-600' : 'text-emerald-700') : ''">
                  {{ a.varians?.tarif === null ? '—' : rp(a.varians?.tarif) }}
                </td>
                <td class="px-5 py-3 text-right">{{ a.perTon === null ? '—' : rp(a.perTon) }}</td>
              </tr>
              <tr v-if="!(props.akun || []).length"><td colspan="8" class="px-5 py-8 text-center text-stone-400">Belum ada anggaran maupun realisasi pada tahun ini.</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>

    <!-- ═══════════ REALISASI ═══════════ -->
    <template v-if="props.mode === 'realisasi'">
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="font-bold text-[14px]">Catat realisasi bulanan</h3>
        <p class="text-[11.5px] text-stone-500 mt-1">
          Satu baris per akun, per bulan, per pusat biaya. Kuantitas hanya diisi pada akun bersatuan —
          dari situlah selisihnya dapat dipisahkan menjadi <b>harga</b> dan <b>pemakaian</b>.
        </p>
        <form class="mt-3 grid gap-3 md:grid-cols-4" @submit.prevent="simpanRealisasi">
          <label class="text-[11px] font-bold text-stone-500">Akun
            <select v-model="realisasi.biaya_akun_id" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required>
              <option value="">— pilih —</option>
              <option v-for="a in props.daftarAkun || []" :key="a.id" :value="a.id">{{ a.kode }} — {{ a.nama }}</option>
            </select></label>
          <label class="text-[11px] font-bold text-stone-500">Bulan
            <select v-model="realisasi.bulan" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
              <option v-for="b in 12" :key="b" :value="b">{{ namaBulan(b) }}</option>
            </select></label>
          <label class="text-[11px] font-bold text-stone-500">Pusat biaya
            <select v-model="realisasi.pusat_biaya" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
              <option v-for="p in props.opsi?.pusat || []" :key="p" :value="p">{{ label(p) }}</option>
            </select></label>
          <label class="text-[11px] font-bold text-stone-500">Nilai (Rp)
            <input v-model="realisasi.nilai_rp" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">
            Kuantitas <span v-if="akunTerpilih?.satuan" class="text-stone-400">({{ akunTerpilih.satuan }})</span>
            <input v-model="realisasi.kuantitas" type="number" step="0.001" :disabled="!akunTerpilih?.bersatuan"
                   class="mt-1 w-full rounded-lg border-stone-200 text-[12px] disabled:bg-stone-50"
                   :placeholder="akunTerpilih?.bersatuan ? '' : 'akun tanpa satuan'"></label>
          <label class="md:col-span-2 text-[11px] font-bold text-stone-500">Catatan
            <input v-model="realisasi.catatan" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <button class="eq-btn-utama self-end" :disabled="realisasi.processing">Simpan draf</button>
        </form>
        <p v-for="(e, k) in realisasi.errors" :key="k" class="mt-2 text-[11px] text-red-600">{{ e }}</p>
      </section>

      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-[11.5px]">
            <thead class="bg-stone-50 text-left text-stone-400 uppercase text-[10px] tracking-wide">
              <tr>
                <th class="px-5 py-2.5">Bulan</th><th class="px-5 py-2.5">Akun</th>
                <th class="px-5 py-2.5">Pusat biaya</th><th class="px-5 py-2.5 text-right">Nilai</th>
                <th class="px-5 py-2.5 text-right">Kuantitas</th><th class="px-5 py-2.5 text-right">Harga satuan</th>
                <th class="px-5 py-2.5">Status</th><th class="px-5 py-2.5 text-right">Alur</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in props.daftarRealisasi || []" :key="r.id" class="border-b border-stone-50">
                <td class="px-5 py-3 font-semibold">{{ namaBulan(r.bulan) }}</td>
                <td class="px-5 py-3">{{ r.akun }}<span class="block text-[10.5px] text-stone-400">{{ r.akunNama }}</span></td>
                <td class="px-5 py-3 text-stone-500">{{ label(r.pusat) }}</td>
                <td class="px-5 py-3 text-right font-semibold">{{ rp(r.nilai) }}</td>
                <td class="px-5 py-3 text-right">{{ r.kuantitas === null ? '—' : `${angka(r.kuantitas, 2)} ${r.satuan || ''}` }}</td>
                <td class="px-5 py-3 text-right text-stone-500">{{ r.harga === null ? '—' : rp(r.harga) }}</td>
                <td class="px-5 py-3">
                  <span class="rounded-full px-2 py-0.5 text-[10px] font-bold" :class="warnaStatus[r.status]">{{ r.statusLabel }}</span>
                  <p v-if="r.alur?.alasanTolak" class="text-[10.5px] text-red-600 mt-1">{{ r.alur.alasanTolak }}</p>
                </td>
                <td class="px-5 py-3 text-right whitespace-nowrap">
                  <button v-if="r.alur?.dapatDiajukan" type="button" class="text-[11px] font-bold text-cam-orange-dark disabled:opacity-40"
                          :disabled="sibuk[r.id]" @click="alur(tautan.realisasiAjukan, r)">Ajukan</button>
                  <template v-if="r.alur?.dapatDitinjau">
                    <button type="button" class="ml-3 text-[11px] font-bold text-emerald-700 disabled:opacity-40"
                            :disabled="sibuk[r.id]" @click="setujui(r)">Setujui</button>
                    <button type="button" class="ml-3 text-[11px] font-bold text-red-600 disabled:opacity-40"
                            :disabled="sibuk[r.id]" @click="tolak(r)">Tolak</button>
                  </template>
                  <button v-if="isAdmin && r.status !== 'disetujui'" type="button" class="ml-3 text-[11px] font-bold text-stone-400" @click="hapusRealisasi(r)">Hapus</button>
                </td>
              </tr>
              <tr v-if="!(props.daftarRealisasi || []).length"><td colspan="8" class="px-5 py-8 text-center text-stone-400">Belum ada realisasi pada tahun ini.</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>

    <!-- ═══════════ ANGGARAN ═══════════ -->
    <template v-if="props.mode === 'anggaran'">
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="font-bold text-[14px]">Pagu anggaran {{ props.tahun }}</h3>
        <p class="text-[11.5px] text-stone-500 mt-1">
          Disalin dari RKAB yang disahkan. Disimpan <b>tahunan</b>, bukan bulanan: membagi dua belas menghasilkan
          pagu bulanan yang tidak pernah disusun siapa pun, lalu bulan yang pekerjaannya memang lebih berat
          terbaca sebagai pemborosan. Laju penyerapan diukur terhadap kemajuan produksi, bukan terhadap kalender.
        </p>
        <form class="mt-3 grid gap-3 md:grid-cols-4" @submit.prevent="simpanAnggaran">
          <label class="text-[11px] font-bold text-stone-500">Akun
            <select v-model="anggaran.biaya_akun_id" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required>
              <option value="">— pilih —</option>
              <option v-for="a in props.daftarAkun || []" :key="a.id" :value="a.id">{{ a.kode }} — {{ a.nama }}</option>
            </select></label>
          <label class="text-[11px] font-bold text-stone-500">Pusat biaya
            <select v-model="anggaran.pusat_biaya" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
              <option v-for="p in props.opsi?.pusat || []" :key="p" :value="p">{{ label(p) }}</option>
            </select></label>
          <label class="text-[11px] font-bold text-stone-500">Pagu (Rp)
            <input v-model="anggaran.nilai_rp" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Kuantitas rencana
            <input v-model="anggaran.kuantitas_rencana" type="number" step="0.001" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="md:col-span-3 text-[11px] font-bold text-stone-500">Catatan
            <input v-model="anggaran.catatan" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <button class="eq-btn-utama self-end" :disabled="anggaran.processing">Simpan</button>
        </form>
        <p v-for="(e, k) in anggaran.errors" :key="k" class="mt-2 text-[11px] text-red-600">{{ e }}</p>
      </section>

      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <table class="w-full text-[11.5px]">
          <thead class="bg-stone-50 text-left text-stone-400 uppercase text-[10px] tracking-wide">
            <tr><th class="px-5 py-2.5">Akun</th><th class="px-5 py-2.5">Pusat biaya</th>
              <th class="px-5 py-2.5 text-right">Pagu</th><th class="px-5 py-2.5 text-right">Kuantitas</th>
              <th class="px-5 py-2.5 text-right">Harga rencana</th><th class="px-5 py-2.5"></th></tr>
          </thead>
          <tbody>
            <tr v-for="a in props.daftarAnggaran || []" :key="a.id" class="border-b border-stone-50">
              <td class="px-5 py-3"><b>{{ a.akun }}</b><span class="block text-[10.5px] text-stone-400">{{ a.akunNama }}</span></td>
              <td class="px-5 py-3 text-stone-500">{{ label(a.pusat) }}</td>
              <td class="px-5 py-3 text-right font-semibold">{{ rp(a.nilai) }}</td>
              <td class="px-5 py-3 text-right">{{ a.kuantitas === null ? '—' : `${angka(a.kuantitas, 2)} ${a.satuan || ''}` }}</td>
              <td class="px-5 py-3 text-right text-stone-500">{{ a.harga === null ? '—' : rp(a.harga) }}</td>
              <td class="px-5 py-3 text-right"><button v-if="isAdmin" type="button" class="text-[11px] text-stone-400" @click="hapusAnggaran(a)">Hapus</button></td>
            </tr>
            <tr v-if="!(props.daftarAnggaran || []).length"><td colspan="6" class="px-5 py-8 text-center text-stone-400">Belum ada pagu untuk {{ props.tahun }}.</td></tr>
          </tbody>
        </table>
      </section>
    </template>

    <!-- ═══════════ BAGAN AKUN ═══════════ -->
    <template v-if="props.mode === 'akun'">
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="font-bold text-[14px]">Bagan akun biaya</h3>
        <p class="text-[11.5px] text-stone-500 mt-1">
          Susunannya mengikuti RKAB dan kebiasaan perusahaan, karena itu disimpan sebagai data.
          <b>Satuan</b> menentukan apakah selisih akun ini nanti dapat dipisahkan menjadi harga dan pemakaian —
          isikan liter untuk solar, ban untuk ban, dan seterusnya.
        </p>
        <form class="mt-3 grid gap-3 md:grid-cols-4" @submit.prevent="simpanAkun">
          <label class="text-[11px] font-bold text-stone-500">Kode
            <input v-model="akun.kode" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Nama
            <input v-model="akun.nama" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Kelompok
            <input v-model="akun.kelompok" list="kelompok-biaya" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required>
            <datalist id="kelompok-biaya">
              <option v-for="k in props.opsi?.kelompok || []" :key="k" :value="k"></option>
            </datalist></label>
          <label class="text-[11px] font-bold text-stone-500">Jenis
            <select v-model="akun.jenis" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
              <option v-for="j in props.opsi?.jenis || []" :key="j" :value="j">{{ label(j) }}</option>
            </select></label>
          <label class="text-[11px] font-bold text-stone-500">Satuan
            <input v-model="akun.satuan" type="text" placeholder="liter, ban, kg — kosongkan bila tidak ada"
                   class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="md:col-span-2 text-[11px] font-bold text-stone-500">Catatan
            <input v-model="akun.catatan" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <button class="eq-btn-utama self-end" :disabled="akun.processing">Simpan</button>
        </form>
        <p v-for="(e, k) in akun.errors" :key="k" class="mt-2 text-[11px] text-red-600">{{ e }}</p>
      </section>

      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <table class="w-full text-[11.5px]">
          <thead class="bg-stone-50 text-left text-stone-400 uppercase text-[10px] tracking-wide">
            <tr><th class="px-5 py-2.5">Kode</th><th class="px-5 py-2.5">Nama</th><th class="px-5 py-2.5">Kelompok</th>
              <th class="px-5 py-2.5">Jenis</th><th class="px-5 py-2.5">Satuan</th><th class="px-5 py-2.5"></th></tr>
          </thead>
          <tbody>
            <tr v-for="a in props.daftarAkun || []" :key="a.id" class="border-b border-stone-50">
              <td class="px-5 py-3 font-semibold">{{ a.kode }}</td>
              <td class="px-5 py-3">{{ a.nama }}</td>
              <td class="px-5 py-3 text-stone-500">{{ label(a.kelompok) }}</td>
              <td class="px-5 py-3 text-stone-500">{{ label(a.jenis) }}</td>
              <td class="px-5 py-3">
                <span v-if="a.bersatuan">{{ a.satuan }}</span>
                <span v-else class="text-stone-400">— selisih tidak dapat dipecah</span>
              </td>
              <td class="px-5 py-3 text-right"><button v-if="isAdmin" type="button" class="text-[11px] text-stone-400" @click="hapusAkun(a)">Hapus</button></td>
            </tr>
            <tr v-if="!(props.daftarAkun || []).length"><td colspan="6" class="px-5 py-8 text-center text-stone-400">Belum ada akun terdaftar.</td></tr>
          </tbody>
        </table>
      </section>
    </template>

    <!-- ═══════════ TINDAK LANJUT ═══════════ -->
    <section id="form-tindak" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="font-bold text-[14px]">Tindak lanjut</h3>
      <form class="mt-3 grid gap-3 md:grid-cols-5" @submit.prevent="simpanTindak">
        <label class="md:col-span-2 text-[11px] font-bold text-stone-500">Judul
          <input v-model="tindak.judul" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
        <label class="text-[11px] font-bold text-stone-500">Prioritas
          <select v-model="tindak.prioritas" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option v-for="p in props.opsi?.prioritasTindak || []" :key="p" :value="p">{{ label(p) }}</option>
          </select></label>
        <label class="text-[11px] font-bold text-stone-500">Penanggung jawab
          <input v-model="tindak.penanggung_jawab" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-bold text-stone-500">Target selesai
          <input v-model="tindak.target_selesai" type="date" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
        <label class="md:col-span-4 text-[11px] font-bold text-stone-500">Uraian
          <input v-model="tindak.uraian" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
        <button class="eq-btn-utama self-end" :disabled="tindak.processing">Tambah</button>
      </form>

      <table class="w-full mt-4 text-[11.5px]">
        <tbody>
          <tr v-for="t in props.tindak || []" :key="t.id" class="border-t border-stone-50">
            <td class="py-2"><b>{{ t.judul }}</b>
              <span v-if="t.terlambat" class="ml-2 rounded-full bg-red-100 text-red-700 px-2 py-0.5 text-[10px] font-bold">Terlambat</span></td>
            <td class="py-2 text-stone-500">{{ t.penanggung_jawab || '—' }}</td>
            <td class="py-2 text-stone-500">{{ t.target_selesai || '—' }}</td>
            <td class="py-2 text-right">
              <select class="rounded-lg border-stone-200 text-[11px]" :value="t.status"
                      @change="ubahTindak(t, ($event.target as HTMLSelectElement).value)" aria-label="Status">
                <option v-for="s in props.opsi?.statusTindak || []" :key="s" :value="s">{{ label(s) }}</option>
              </select>
            </td>
          </tr>
          <tr v-if="!(props.tindak || []).length"><td colspan="4" class="py-6 text-center text-stone-400">Belum ada tindak lanjut.</td></tr>
        </tbody>
      </table>
    </section>
  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>

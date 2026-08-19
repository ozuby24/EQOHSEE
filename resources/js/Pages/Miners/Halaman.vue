<script setup lang="ts">
/**
 * Authority — berkas kelayakan kerja.
 *
 * Susunannya mengikuti pertanyaan gerbang, bukan bentuk tabelnya:
 * berapa orang yang HARI INI tidak boleh bekerja, dan kenapa. Itu
 * pertanyaan yang dibawa orang ke halaman ini; sisanya — daftar
 * sertifikat, riwayat MCU — adalah rinciannya.
 *
 * Sebabnya selalu ditulis. "Tidak layak" tanpa sebab memaksa pengawas
 * membuka tiga halaman untuk mencarinya sendiri, di gerbang, sambil
 * antrean memanjang.
 */
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import KartuGrafik from '../../Grafik/KartuGrafik.vue';
import Batang from '../../Grafik/Batang.vue';
import Donat from '../../Grafik/Donat.vue';
import Rantai from './Rantai.vue';
import Tahapan from './Tahapan.vue';
import { KEADAAN } from '../../Grafik/warna';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
const { dialog, tanya, minta, batal, lanjut } = useDialog();


const props = usePage<any>().props as any;

/** Warna keadaan masa berlaku — dipesan maknanya, tidak dipakai lain. */
const WARNA: Record<string, string> = {
  aman:            KEADAAN.baik,
  perhatian:       KEADAAN.ingat,
  segera:          KEADAAN.serius,
  kritis:          KEADAAN.gawat,
  'tak-bertanggal': KEADAAN.netral,
};

const LABEL: Record<string, string> = {
  aman: 'Aman', perhatian: 'Perhatian', segera: 'Segera',
  kritis: 'Kritis', 'tak-bertanggal': 'Tanpa tanggal',
};

/* Ambangnya ditulis di layar, bukan dihafal pembacanya. */
const AMBANG = 'Aman >180 hari · Perhatian 91–180 · Segera 31–90 · Kritis ≤30 atau lewat';

const orang   = computed(() => props.orang ?? []);
const ringkas = computed(() => props.ringkas ?? {});

const saring = reactive({
  q: props.saring?.q ?? '',
  klas: props.saring?.klas ?? '',
  keadaan: props.saring?.keadaan ?? '',
});

function cari() {
  router.get('/miners', saring, { preserveState: true, preserveScroll: true });
}

/* ── ringkasan sebagai grafik ── */

const sebaranKeadaan = computed(() => {
  const per = ringkas.value?.perKeadaan ?? {};
  const gabung: Record<string, number> = {};

  for (const jenis of ['sertifikat', 'mcu', 'kartu', 'induksi']) {
    for (const [k, n] of Object.entries((per[jenis] ?? {}) as Record<string, number>)) {
      gabung[k] = (gabung[k] ?? 0) + Number(n);
    }
  }

  return ['kritis', 'segera', 'perhatian', 'aman', 'tak-bertanggal']
    .filter(k => (gabung[k] ?? 0) > 0)
    .map(k => ({ label: LABEL[k], nilai: gabung[k], warna: WARNA[k] }));
});

/**
 * Tiga jenis berkas berdampingan pada satu sumbu — berapa yang KRITIS
 * pada masing-masing. Itu yang menentukan ke mana tenaga diarahkan
 * lebih dulu; jumlah totalnya tidak.
 */
const kritisPerJenis = computed(() => {
  const per = ringkas.value?.perKeadaan ?? {};

  return [
    ['Induksi', per.induksi],
    ['Sertifikat kompetensi', per.sertifikat],
    ['MCU', per.mcu],
    ['Kartu masuk', per.kartu],
  ].map(([label, d]: any) => ({
    label,
    nilai: Number(d?.kritis ?? 0) + Number(d?.segera ?? 0),
    keadaan: (Number(d?.kritis ?? 0) > 0 ? 'gawat' : 'ingat') as 'gawat' | 'ingat',
  }));
});

const takLayak = computed(() => orang.value.filter((o: any) => !o.layak));

/* ── formulir ── */

const buka = ref<string | null>(null);

const fOrang = useForm<Record<string, any>>({
  nama: '', nik: '', jabatan: '', departemen: '', klasifikasi: '',
  nomor_register: '', tgl_bergabung: '', status: 'aktif', catatan: '',
});

const fSertifikat = useForm<Record<string, any>>({
  kompetensi_jenis_id: '', nama: '', lembaga: '', nomor: '',
  tgl_terbit: '', tgl_expired: '', catatan: '',
});

const fMcu = useForm<Record<string, any>>({
  tgl_periksa: '', tgl_expired: '', penyelenggara: '',
  jenis: 'Berkala', hasil: 'Fit', pembatasan: '',
});

const fKartu = useForm<Record<string, any>>({
  jenis: 'Mine Permit', sebab_terbit: 'Terbit', nomor: '', tgl_terbit: '', tgl_expired: '',
  golongan: '', area: '', sim_polisi: '', sim_polisi_expired: '',
  pengalaman_kerja: '', berkas_induksi: '', berkas_ddt: '', email_atasan: '',
  catatan: '',
});

const fInduksi = useForm<Record<string, any>>({
  nomor_registrasi: '', jenis: 'Awal', tanggal: '', tgl_expired: '',
  pemberi: '', lokasi: '', nilai: '', hasil: 'Lulus', catatan: '',
});

/* ── pengajuan MCU ── */

const fPengajuan = useForm<Record<string, any>>({
  nomor_register: '', tanggal: '', kepada: '', judul: '',
  jenis: 'Berkala', catatan: '',
});

const fNama  = useForm<Record<string, any>>({ paspor_id: '', tgl_periksa: '' });
const fAlur  = useForm<Record<string, any>>({ aksi: '', alasan: '' });

/** Pengajuan yang sedang dibuka rinciannya — hanya satu, supaya
    layarnya tidak berubah menjadi dinding tabel bersarang. */
const bukaPengajuan = ref<number | null>(null);

/**
 * Hasil MCU diisi per baris, jadi formulirnya juga per baris.
 *
 * Satu useForm bersama akan membuat isian yang belum disimpan pada satu
 * nama muncul pada nama berikutnya yang dibuka — dan pada berkas medis,
 * angka yang nyasar ke orang lain bukan gangguan kecil.
 */
const isiHasil = reactive<Record<number, any>>({});

function mulaiIsi(h: any) {
  isiHasil[h.id] = {
    tgl_periksa: h.tglPeriksa ?? '', tgl_expired: h.tglExpired ?? '',
    nomor: h.nomor ?? '', hasil: h.hasil ?? 'Fit',
    pembatasan: h.pembatasan ?? '', rujukan: h.rujukan ?? '',
    outstanding: h.outstanding ?? '',
  };
}

function batalIsi(hasilId: number) { delete isiHasil[hasilId]; }

function simpanHasil(pengajuanId: number, hasilId: number) {
  router.put(`/miners/mcu/${pengajuanId}/hasil/${hasilId}`, isiHasil[hasilId], {
    preserveScroll: true,
    onSuccess: () => batalIsi(hasilId),
  });
}

function simpanPengajuan() {
  fPengajuan.post('/miners/mcu', {
    preserveScroll: true,
    onSuccess: () => { fPengajuan.reset(); buka.value = null; },
  });
}

function tambahNama(pengajuanId: number) {
  fNama.post(`/miners/mcu/${pengajuanId}/nama`, {
    preserveScroll: true, onSuccess: () => fNama.reset(),
  });
}

function ajukanPengajuan(pengajuanId: number) {
  router.post(`/miners/mcu/${pengajuanId}/ajukan`, {}, { preserveScroll: true });
}

/**
 * Menolak selalu menuntut alasan, dan alasannya diminta di muka.
 *
 * Penolakan tanpa alasan memaksa pengaju menebak apa yang salah, dan
 * yang paling sering ditebak adalah "tidak ada yang salah, coba kirim
 * ulang" — yang membuat surat yang sama bolak-balik tanpa ada yang
 * berubah.
 */
/**
 * Membubuhkan paraf. Sengaja TIDAK meminta konfirmasi.
 *
 * Paraf tidak menerbitkan apa pun dan dapat dilihat siapa saja setelah
 * dibubuhkan; meminta konfirmasi untuk tindakan yang tidak berakibat
 * hanya melatih orang menekan "ya" tanpa membaca — kebiasaan yang lalu
 * terbawa ke dialog yang benar-benar penting.
 */
function paraf(jalur: string, tahap: string) {
  router.post(jalur, { tahap }, { preserveScroll: true });
}

async function tinjau(jalur: string, aksi: 'setujui' | 'tolak' | 'tarik') {
  let alasan = '';

  if (aksi === 'tolak') {
    alasan = (await minta({ judul: 'Tolak pengajuan?', label: 'Alasan penolakan',
      jenis: 'panjang', min: 5, labelAksi: 'Tolak', nada: 'bahaya' }) ?? '').trim();
    if (!alasan) return;
  }

  fAlur.transform(() => ({ aksi, alasan })).post(jalur, { preserveScroll: true });
}

/**
 * Memilih jenis kompetensi ikut mengisi nama dan lembaganya.
 *
 * Nama disalin, bukan hanya dirujuk: jenis yang kemudian diganti
 * namanya tidak boleh mengubah bunyi sertifikat yang sudah tercetak
 * dan sudah diperiksa inspektur.
 */
function pilihJenis() {
  const j = (props.opsi?.kompetensi ?? []).find(
    (x: any) => String(x.id) === String(fSertifikat.kompetensi_jenis_id));

  if (j) { fSertifikat.nama = j.nama; fSertifikat.lembaga = j.lembaga ?? ''; }
}

const id = computed(() => props.p?.id);

function simpanOrang() {
  fOrang.post('/miners', { preserveScroll: true, onSuccess: () => { fOrang.reset(); buka.value = null; } });
}
function simpanSertifikat() {
  fSertifikat.post(`/miners/${id.value}/sertifikat`, { preserveScroll: true, onSuccess: () => fSertifikat.reset() });
}
function simpanMcu() {
  fMcu.post(`/miners/${id.value}/mcu`, { preserveScroll: true, onSuccess: () => fMcu.reset() });
}
function simpanKartu() {
  fKartu.post(`/miners/${id.value}/kartu`, { preserveScroll: true, onSuccess: () => fKartu.reset() });
}
function simpanInduksi() {
  fInduksi.post(`/miners/${id.value}/induksi`, { preserveScroll: true, onSuccess: () => fInduksi.reset() });
}
function ajukanKartu(kartuId: number) {
  router.post(`/miners/${id.value}/kartu/${kartuId}/ajukan`, {}, { preserveScroll: true });
}
async function hapus(jalur: string, apa: string) {
  if (await tanya(`Hapus ${apa}?`)) router.delete(jalur, { preserveScroll: true });
}
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1400px] mx-auto space-y-5">

    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-cam-ink">{{ props.judul }}</h2>
        <p class="text-[12.5px] text-stone-500 mt-1">{{ props.subjudul }}</p>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <Link v-if="props.mode !== 'daftar'" href="/miners" class="eq-btn-lain">
          Kembali ke daftar
        </Link>

        <Link v-if="props.mode === 'daftar'" href="/miners/mcu" class="eq-btn-lain">
          Pengajuan MCU
        </Link>

        <button v-if="props.mode === 'daftar'" type="button" class="eq-btn-utama"
                @click="buka = buka === 'orang' ? null : 'orang'">
          {{ buka === 'orang' ? 'Batal' : 'Tambah orang' }}
        </button>

        <button v-if="props.mode === 'mcu'" type="button" class="eq-btn-utama"
                @click="buka = buka === 'pengajuan' ? null : 'pengajuan'">
          {{ buka === 'pengajuan' ? 'Batal' : 'Surat pengajuan baru' }}
        </button>
      </div>
    </section>

    <!-- ══════════ DAFTAR ══════════ -->
    <template v-if="props.mode === 'daftar'">

      <form v-if="buka === 'orang'"
            class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 grid gap-3 md:grid-cols-4"
            @submit.prevent="simpanOrang">
        <input v-model="fOrang.nama" required placeholder="Nama lengkap"
               class="rounded-lg border-stone-200 text-[12px] md:col-span-2">
        <input v-model="fOrang.nik" placeholder="NIK" class="rounded-lg border-stone-200 text-[12px]">
        <input v-model="fOrang.jabatan" placeholder="Jabatan" class="rounded-lg border-stone-200 text-[12px]">
        <input v-model="fOrang.departemen" placeholder="Departemen" class="rounded-lg border-stone-200 text-[12px]">
        <select v-model="fOrang.klasifikasi" class="rounded-lg border-stone-200 text-[12px]" aria-label="Klasifikasi">
          <option value="">Tanpa klasifikasi</option>
          <option v-for="(l, k) in (props.opsi?.klasifikasi ?? {})" :key="k" :value="k">{{ k }} — {{ l }}</option>
        </select>
        <input v-model="fOrang.tgl_bergabung" type="date" class="rounded-lg border-stone-200 text-[12px]" aria-label="Tanggal bergabung">
        <button class="eq-btn-utama" :disabled="fOrang.processing">Simpan</button>
        <p v-if="fOrang.errors.nama" class="text-[11px] text-red-600 md:col-span-4">{{ fOrang.errors.nama }}</p>
      </form>

      <!-- yang tidak boleh bekerja hari ini, paling atas -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <div class="flex items-start justify-between gap-3 mb-3">
          <div>
            <h3 class="text-[14px] font-bold text-cam-ink">Tidak boleh bekerja hari ini</h3>
            <p class="text-[11.5px] text-stone-500 mt-0.5">
              Induksi, MCU, atau kartu masuk kadaluarsa, belum ada, belum disetujui,
              atau hasil MCU menyatakan tidak layak.
            </p>
          </div>
          <span class="text-[11px] font-bold shrink-0 num"
                :style="{ color: takLayak.length ? KEADAAN.gawat : KEADAAN.baik }">
            {{ takLayak.length }} dari {{ ringkas.orang ?? 0 }} orang
          </span>
        </div>

        <ul v-if="takLayak.length" class="divide-y divide-stone-100">
          <li v-for="o in takLayak" :key="o.id" class="py-2.5 flex items-center gap-3">
            <span class="w-1.5 h-1.5 rounded-full shrink-0" :style="{ background: KEADAAN.gawat }"></span>
            <Link :href="`/miners/${o.id}`"
                  class="text-[12.5px] font-semibold text-cam-ink hover:text-cam-lime-deep min-w-0 truncate w-44 py-1.5 -my-1.5">
              {{ o.nama }}
            </Link>
            <span class="text-[11.5px] text-stone-500 min-w-0 flex-1 truncate">{{ o.jabatan || '—' }}</span>
            <!-- Sebabnya, bukan cuma vonisnya. -->
            <span class="text-[11.5px] font-semibold shrink-0 text-right"
                  :style="{ color: KEADAAN.gawat }">{{ o.sebab.join(' · ') }}</span>
          </li>
        </ul>

        <p v-else class="text-[12px] py-4 text-center" :style="{ color: KEADAAN.baik }">
          Seluruh {{ ringkas.orang ?? 0 }} orang memenuhi syarat masuk hari ini.
        </p>
      </section>

      <section class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
        <KartuGrafik judul="Masa berlaku seluruh berkas" :catatan="AMBANG"
                     :angka="`${ringkas.sertifikat ?? 0} sertifikat`">
          <Donat :bagian="sebaranKeadaan" :tengah="String(ringkas.orang ?? 0)" tengah-label="orang" />

          <template #tabel>
            <table>
              <thead><tr><th>Keadaan</th><th>Induksi</th><th>Sertifikat</th><th>MCU</th><th>Kartu</th></tr></thead>
              <tbody>
                <tr v-for="k in ['kritis','segera','perhatian','aman','tak-bertanggal']" :key="k">
                  <td>{{ LABEL[k] }}</td>
                  <td class="num">{{ ringkas.perKeadaan?.induksi?.[k] ?? 0 }}</td>
                  <td class="num">{{ ringkas.perKeadaan?.sertifikat?.[k] ?? 0 }}</td>
                  <td class="num">{{ ringkas.perKeadaan?.mcu?.[k] ?? 0 }}</td>
                  <td class="num">{{ ringkas.perKeadaan?.kartu?.[k] ?? 0 }}</td>
                </tr>
              </tbody>
            </table>
          </template>
        </KartuGrafik>

        <KartuGrafik judul="Yang perlu diperpanjang"
                     catatan="Kritis dan segera digabung — keduanya sudah harus punya tanggal, bukan niat."
                     angka="berkas">
          <Batang :baris="kritisPerJenis" apa-adanya />

          <template #tabel>
            <table>
              <thead><tr><th>Klasifikasi</th><th>Orang</th></tr></thead>
              <tbody>
                <tr v-for="k in (ringkas.perKlasifikasi ?? [])" :key="k.kode">
                  <td>{{ k.kode }} — {{ k.label }}</td>
                  <td class="num">{{ k.orang }}</td>
                </tr>
              </tbody>
            </table>
          </template>
        </KartuGrafik>
      </section>

      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <form class="p-4 grid gap-2 md:grid-cols-5 border-b border-stone-100" @submit.prevent="cari">
          <input v-model="saring.q" placeholder="Cari nama, NIK, jabatan…"
                 class="rounded-lg border-stone-200 text-[12px] md:col-span-2">
          <select v-model="saring.klas" class="rounded-lg border-stone-200 text-[12px]" aria-label="Klasifikasi">
            <option value="">Semua klasifikasi</option>
            <option v-for="(l, k) in (props.opsi?.klasifikasi ?? {})" :key="k" :value="k">{{ k }} — {{ l }}</option>
          </select>
          <select v-model="saring.keadaan" class="rounded-lg border-stone-200 text-[12px]" aria-label="Keadaan">
            <option value="">Semua keadaan</option>
            <option v-for="(l, k) in LABEL" :key="k" :value="k">{{ l }}</option>
          </select>
          <button class="eq-btn-utama">Saring</button>
        </form>

        <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[12px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-100">
              <th class="px-5 py-3">Nama</th><th class="px-5 py-3">Jabatan</th>
              <th class="px-5 py-3">Klas</th><th class="px-5 py-3">Sertifikat</th>
              <th class="px-5 py-3">MCU</th><th class="px-5 py-3">Kartu masuk</th>
              <th class="px-5 py-3">Boleh kerja</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="o in orang" :key="o.id" class="border-b border-stone-50">
              <td class="px-5 py-3 font-semibold">
                <Link :href="`/miners/${o.id}`" class="text-cam-lime-deep">{{ o.nama }}</Link>
                <small class="block text-[10px] text-stone-400">{{ o.nik || '—' }}</small>
              </td>
              <td class="px-5 py-3">{{ o.jabatan || '—' }}</td>
              <td class="px-5 py-3">{{ o.klasifikasi || '—' }}</td>
              <td class="px-5 py-3 num">{{ o.jumlahSertifikat }}</td>
              <td class="px-5 py-3">
                <span v-if="o.mcu" :style="{ color: WARNA[o.mcu.keadaan] }" class="font-semibold">
                  {{ o.mcu.keterangan }}
                </span>
                <span v-else class="text-stone-400">belum ada</span>
              </td>
              <td class="px-5 py-3">
                <span v-if="o.kartu" :style="{ color: WARNA[o.kartu.keadaan] }" class="font-semibold">
                  {{ o.kartu.keterangan }}
                </span>
                <span v-else class="text-stone-400">belum ada</span>
              </td>
              <td class="px-5 py-3">
                <span class="font-bold" :style="{ color: o.layak ? KEADAAN.baik : KEADAAN.gawat }">
                  {{ o.layak ? 'Boleh' : 'Tidak' }}
                </span>
              </td>
            </tr>
            <tr v-if="!orang.length">
              <td colspan="7" class="px-5 py-10 text-center text-stone-400">
                Belum ada orang yang cocok dengan saringan ini.
              </td>
            </tr>
          </tbody>
        </table>
        </div>
      </section>
    </template>

    <!-- ══════════ RINCIAN ══════════ -->
    <template v-if="props.mode === 'rincian'">

      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h3 class="text-lg font-bold text-cam-ink">{{ props.p?.nama }}</h3>
            <p class="text-[12.5px] text-stone-500 mt-0.5">
              {{ props.p?.jabatan || 'Jabatan belum diisi' }}
              <span v-if="props.p?.klasLabel"> · {{ props.p.klasifikasi }} — {{ props.p.klasLabel }}</span>
              <span v-if="props.p?.nik"> · NIK {{ props.p.nik }}</span>
            </p>
          </div>

          <div class="text-right">
            <p class="text-[19px] font-bold leading-none"
               :style="{ color: props.p?.layak ? KEADAAN.baik : KEADAAN.gawat }">
              {{ props.p?.layak ? 'Boleh bekerja' : 'Tidak boleh bekerja' }}
            </p>
            <p v-if="!props.p?.layak" class="text-[11.5px] mt-1" :style="{ color: KEADAAN.gawat }">
              {{ props.p?.sebab?.join(' · ') }}
            </p>
          </div>
        </div>
      </section>

      <Tahapan v-if="props.tahapan" :tahapan="props.tahapan" />

      <!-- sertifikat -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="text-[14px] font-bold text-cam-ink mb-3">Sertifikat kompetensi</h3>

        <ul v-if="props.sertifikat?.length" class="divide-y divide-stone-100 mb-4">
          <li v-for="s in props.sertifikat" :key="s.id" class="py-2.5 flex items-center gap-3">
            <span class="w-1.5 h-1.5 rounded-full shrink-0" :style="{ background: WARNA[s.keadaan] }"></span>
            <span class="text-[12.5px] font-semibold text-cam-ink min-w-0 flex-1 truncate">
              {{ s.nama }}
              <span v-if="s.dariLms" class="text-[10px] font-normal text-stone-400">· dari LMS</span>
            </span>
            <span class="text-[11px] text-stone-400 shrink-0 hidden sm:block">{{ s.lembaga || '—' }}</span>
            <span class="text-[11.5px] font-semibold shrink-0 w-32 text-right"
                  :style="{ color: WARNA[s.keadaan] }">{{ s.keterangan }}</span>
            <button type="button" class="text-red-600 text-[11px] shrink-0"
                    @click="hapus(`/miners/${id}/sertifikat/${s.id}`, s.nama)">Hapus</button>
          </li>
        </ul>
        <p v-else class="text-[12px] text-stone-400 py-3">Belum ada sertifikat tercatat.</p>

        <form class="grid gap-2 md:grid-cols-6 pt-3 border-t border-stone-100" @submit.prevent="simpanSertifikat">
          <select v-model="fSertifikat.kompetensi_jenis_id" class="rounded-lg border-stone-200 text-[12px] md:col-span-2"
                  @change="pilihJenis" aria-label="Jenis kompetensi">
            <option value="">Pilih jenis kompetensi…</option>
            <option v-for="j in (props.opsi?.kompetensi ?? [])" :key="j.id" :value="j.id">{{ j.nama }}</option>
          </select>
          <input v-model="fSertifikat.nomor" placeholder="No. sertifikat" class="rounded-lg border-stone-200 text-[12px]">
          <input v-model="fSertifikat.tgl_terbit" type="date" title="Tanggal terbit" class="rounded-lg border-stone-200 text-[12px]">
          <input v-model="fSertifikat.tgl_expired" type="date" title="Tanggal kadaluarsa" class="rounded-lg border-stone-200 text-[12px]">
          <button class="eq-btn-utama" :disabled="fSertifikat.processing">Tambah</button>
        </form>
      </section>

      <div class="grid gap-4 lg:grid-cols-2">
        <!-- MCU -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
          <h3 class="text-[14px] font-bold text-cam-ink mb-1">Pemeriksaan kesehatan</h3>
          <p class="text-[11px] text-stone-500 mb-3">
            Hanya kesimpulan kelayakan kerjanya yang disimpan — rincian medis adalah rekam medis.
          </p>

          <ul v-if="props.mcu?.length" class="divide-y divide-stone-100 mb-4">
            <li v-for="m in props.mcu" :key="m.id" class="py-2.5">
              <div class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full shrink-0" :style="{ background: WARNA[m.keadaan] }"></span>
                <span class="text-[12.5px] font-semibold" :style="{ color: m.hasilLayak ? '#292524' : KEADAAN.gawat }">
                  {{ m.hasil }}
                </span>
                <span class="text-[11px] text-stone-400">{{ m.jenis }} · {{ m.tglPeriksa }}</span>
                <span class="ml-auto text-[11.5px] font-semibold" :style="{ color: WARNA[m.keadaan] }">
                  {{ m.keterangan }}
                </span>
                <button type="button" class="text-red-600 text-[11px]"
                        @click="hapus(`/miners/${id}/mcu/${m.id}`, 'catatan MCU')">Hapus</button>
              </div>
              <p v-if="m.pembatasan" class="text-[11px] text-amber-700 mt-1 ml-3.5">{{ m.pembatasan }}</p>
            </li>
          </ul>
          <p v-else class="text-[12px] text-stone-400 py-3">Belum ada MCU tercatat.</p>

          <form class="grid gap-2 md:grid-cols-3 pt-3 border-t border-stone-100" @submit.prevent="simpanMcu">
            <input v-model="fMcu.tgl_periksa" type="date" required title="Tanggal periksa" class="rounded-lg border-stone-200 text-[12px]">
            <input v-model="fMcu.tgl_expired" type="date" title="Berlaku sampai" class="rounded-lg border-stone-200 text-[12px]">
            <select v-model="fMcu.jenis" class="rounded-lg border-stone-200 text-[12px]" aria-label="Jenis">
              <option v-for="j in (props.opsi?.jenisMcu ?? [])" :key="j">{{ j }}</option>
            </select>
            <select v-model="fMcu.hasil" class="rounded-lg border-stone-200 text-[12px]" aria-label="Hasil">
              <option v-for="h in (props.opsi?.hasilMcu ?? [])" :key="h">{{ h }}</option>
            </select>
            <input v-model="fMcu.pembatasan" placeholder="Pembatasan kerja" class="rounded-lg border-stone-200 text-[12px]">
            <button class="eq-btn-utama" :disabled="fMcu.processing">Catat</button>
          </form>
        </section>

      </div>

      <!-- ══════════ KARTU MASUK TAMBANG ══════════
           Dipindah ke lebar penuh, keluar dari kisi dua kolom. Isinya
           tumbuh menjadi tiga hal sekaligus — daftar kartu, rantai
           paraf, dan formulir bermedan sepuluh — dan memampatkan
           ketiganya ke setengah lebar layar membuat setiap medan hanya
           cukup menampilkan separuh keterangannya. Itulah sebab
           "Golongan kendar", "No. SIM kepolisia", dan "Sertifikat
           defensi" terpotong di tengah kata. -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="text-[14px] font-bold text-cam-ink mb-1">Kartu masuk tambang</h3>
        <p class="text-[11.5px] text-stone-500 mb-4">
          <b>Mine Permit</b> adalah izin masuk area — terbit sesudah MCU dan induksi.
          <b>Mine License</b> izin mengemudi di atasnya, hanya bagi yang membawa unit.
          <b>Visitor</b> untuk tamu, tidak menuntut MCU.
        </p>

        <ul v-if="props.kartu?.length" class="space-y-3 mb-5">
          <li v-for="k in props.kartu" :key="k.id"
              class="rounded-xl border p-4"
              :style="{ borderColor: k.berlaku ? '#D6E9DC' : '#E7E5E4',
                        background: k.berlaku ? '#F7FCF9' : '#FAFAF9' }">

            <!-- kepala: jenis, nomor, dan vonis masa berlakunya -->
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-[13px] font-bold text-cam-ink">
                  {{ k.jenis }}
                  <span v-if="k.nomor" class="text-[11.5px] font-normal text-stone-400">
                    · {{ k.nomor }}
                  </span>
                </p>
                <p class="text-[11.5px] text-stone-500 mt-0.5">
                  {{ k.sebabTerbit }}
                  <span v-if="k.golongan"> · golongan {{ k.golongan }}</span>
                  <span v-if="k.area"> · {{ k.area }}</span>
                  <span v-if="k.tglTerbit"> · terbit {{ k.tglTerbit }}</span>
                </p>
              </div>

              <div class="text-right shrink-0">
                <!-- Yang belum disetujui BELUM BERLAKU, jadi statusnya
                     yang disebut — bukan sisa harinya. Menyebut "365 hari
                     lagi" pada kartu yang belum terbit membuatnya terbaca
                     sebagai kartu yang sah. -->
                <p class="text-[12px] font-bold"
                   :style="{ color: k.berlaku ? WARNA[k.keadaan] : KEADAAN.ingat }">
                  {{ k.berlaku ? k.keterangan : k.statusLabel }}
                </p>
                <p v-if="k.berlaku" class="text-[10.5px] mt-0.5" :style="{ color: KEADAAN.baik }">
                  berlaku di gerbang
                </p>
                <p v-else class="text-[10.5px] text-stone-500 mt-0.5">
                  belum berlaku di gerbang
                </p>
              </div>
            </div>

            <p v-if="k.alasanTolak" class="text-[11.5px] text-red-600 mt-2">
              Ditolak: {{ k.alasanTolak }}
            </p>
            <p v-if="k.syaratKurang?.length && k.dapatDiubah"
               class="text-[11.5px] mt-2 rounded-lg px-2.5 py-2"
               :style="{ color: '#92400E', background: '#FEF6E7' }">
              <b>Belum dapat diajukan.</b> {{ k.syaratKurang.join(' ') }}
            </p>

            <div v-if="k.status !== 'draf'" class="mt-3">
              <Rantai :rantai="k.rantai" :tertinggal="k.tertinggal"
                      :dapat-paraf="k.dapatParaf" :saya-penentu="props.opsi?.sayaPenentu"
                      @paraf="t => paraf(`/miners/${id}/kartu/${k.id}/paraf`, t)" />
            </div>

            <!-- Keputusan dipisahkan dari tindakan biasa oleh garis dan
                 oleh bentuk tombolnya, bukan hanya oleh urutan. -->
            <div class="flex flex-wrap items-center gap-2 mt-3 pt-3 border-t border-stone-200/70">
              <button v-if="k.dapatDiubah && !k.syaratKurang?.length" type="button"
                      class="eq-btn-utama !flex-none" @click="ajukanKartu(k.id)">
                Ajukan ke OHSE
              </button>

              <button v-if="k.dapatDitinjau" type="button" class="eq-btn-setuju"
                      @click="tinjau(`/miners/${id}/kartu/${k.id}/tinjau`, 'setujui')">
                Setujui &amp; terbitkan
              </button>
              <button v-if="k.dapatDitinjau" type="button" class="eq-btn-tolak"
                      @click="tinjau(`/miners/${id}/kartu/${k.id}/tinjau`, 'tolak')">
                Tolak
              </button>

              <a v-if="k.berlaku && k.jenis === 'Mine Permit'"
                 :href="`/miners/${id}/kartu/${k.id}/cetak`" target="_blank"
                 class="eq-btn-lain !py-2 !text-[12px]">Cetak</a>

              <button v-if="k.status === 'diajukan'" type="button" class="eq-btn-mini"
                      @click="tinjau(`/miners/${id}/kartu/${k.id}/tinjau`, 'tarik')">Tarik</button>
              <button type="button" class="eq-btn-mini bahaya ml-auto"
                      @click="hapus(`/miners/${id}/kartu/${k.id}`, k.jenis)">Hapus</button>

              <!-- Sebab tombol Setujui tidak ada, DIKATAKAN. Menyembunyikan
                   tombol tanpa keterangan membuat pembacanya tidak dapat
                   membedakan antara tidak berhak, sudah diputus, dan
                   sistemnya rusak — dan dugaan yang paling sering diambil
                   adalah yang ketiga. -->
              <p v-if="!k.dapatDitinjau && k.sebabTakTinjau && k.status === 'diajukan'"
                 class="text-[11px] text-stone-500 basis-full mt-1">
                {{ k.sebabTakTinjau }}
              </p>
            </div>
          </li>
        </ul>
        <p v-else class="text-[12px] text-stone-400 py-4">Belum ada kartu tercatat.</p>

        <!-- ── formulir pengajuan ──
             BERLABEL, bukan berplaceholder. Placeholder hilang begitu
             diketik dan terpotong begitu kolomnya sempit — dua sifat
             yang membuat sepuluh kotak kosong berjajar tidak dapat
             dibedakan satu sama lain.

             Medan syarat pengemudi hanya muncul untuk Mine License.
             Medan yang selalu tampil tetapi jarang berlaku dilewati
             mata; yang muncul justru saat dibutuhkan tidak. -->
        <form class="pt-4 border-t border-stone-100" @submit.prevent="simpanKartu">
          <p class="text-[12px] font-bold text-cam-ink mb-3">Buat pengajuan kartu baru</p>

          <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="text-[11px] font-semibold text-stone-600">
              Jenis kartu
              <select v-model="fKartu.jenis" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
                <option v-for="j in (props.opsi?.jenisKartu ?? [])" :key="j">{{ j }}</option>
              </select>
            </label>

            <label class="text-[11px] font-semibold text-stone-600">
              Sebab penerbitan
              <select v-model="fKartu.sebab_terbit" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
                <option v-for="s in (props.opsi?.sebabKartu ?? [])" :key="s">{{ s }}</option>
              </select>
            </label>

            <label class="text-[11px] font-semibold text-stone-600">
              Nomor kartu
              <input v-model="fKartu.nomor" placeholder="mis. MP/0007"
                     class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            </label>

            <label class="text-[11px] font-semibold text-stone-600">
              Area berlaku
              <input v-model="fKartu.area" placeholder="mis. Seluruh area tambang"
                     class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            </label>

            <label class="text-[11px] font-semibold text-stone-600">
              Tanggal terbit
              <input v-model="fKartu.tgl_terbit" type="date"
                     class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            </label>

            <label class="text-[11px] font-semibold text-stone-600">
              Berlaku sampai
              <input v-model="fKartu.tgl_expired" type="date"
                     class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            </label>

            <label class="text-[11px] font-semibold text-stone-600 sm:col-span-2">
              Lampiran bukti induksi <span class="font-normal text-stone-400">— opsional</span>
              <input v-model="fKartu.berkas_induksi" placeholder="jalur berkas"
                     class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            </label>
          </div>

          <!-- syarat khusus pengemudi -->
          <div v-if="fKartu.jenis === 'Mine License'"
               class="mt-4 rounded-xl border border-stone-200 bg-stone-50 p-4">
            <p class="text-[11.5px] font-bold text-cam-ink mb-1">Syarat pengemudi</p>
            <p class="text-[11px] text-stone-500 mb-3">
              Wajib untuk Mine License. Mine Permit yang masih berlaku juga harus sudah terbit.
            </p>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <label class="text-[11px] font-semibold text-stone-600">
                Golongan kendaraan
                <input v-model="fKartu.golongan" placeholder="mis. LV, Alat Berat, A2B"
                       class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
              </label>
              <label class="text-[11px] font-semibold text-stone-600">
                No. SIM kepolisian
                <input v-model="fKartu.sim_polisi" placeholder="mis. SIM B2 Umum"
                       class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
              </label>
              <label class="text-[11px] font-semibold text-stone-600">
                SIM berlaku sampai
                <input v-model="fKartu.sim_polisi_expired" type="date"
                       class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
              </label>
              <label class="text-[11px] font-semibold text-stone-600">
                Sertifikat defensive driving
                <input v-model="fKartu.berkas_ddt" placeholder="jalur berkas"
                       class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
              </label>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-3 mt-4">
            <label class="text-[11px] font-semibold text-stone-600 flex-1 min-w-[220px]">
              E-mail atasan <span class="font-normal text-stone-400">— untuk pemberitahuan</span>
              <input v-model="fKartu.email_atasan" type="email" placeholder="nama@perusahaan.co.id"
                     class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            </label>
            <button class="eq-btn-utama !flex-none self-end" :disabled="fKartu.processing">
              Buat pengajuan
            </button>
          </div>

          <p v-if="fKartu.errors.kartu" class="text-[11.5px] text-red-600 mt-2">{{ fKartu.errors.kartu }}</p>
        </form>
      </section>

      <!-- induksi -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="text-[14px] font-bold text-cam-ink mb-1">Induksi keselamatan</h3>
        <p class="text-[11px] text-stone-500 mb-3">
          Syarat pertama sebelum masuk area — mendahului MCU maupun kartu.
          Yang dipakai menilai kelayakan adalah induksi LULUS terakhir yang masih berlaku.
        </p>

        <ul v-if="props.induksi?.length" class="divide-y divide-stone-100 mb-4">
          <li v-for="i in props.induksi" :key="i.id" class="py-2.5 flex flex-wrap items-center gap-2">
            <span class="w-1.5 h-1.5 rounded-full shrink-0"
                  :style="{ background: i.lulus ? WARNA[i.keadaan] : KEADAAN.gawat }"></span>
            <span class="text-[12.5px] font-semibold text-cam-ink">{{ i.jenis }}</span>
            <span class="text-[11px] text-stone-400 min-w-0 truncate">
              {{ i.tanggal }}<span v-if="i.pemberi"> · {{ i.pemberi }}</span>
              <span v-if="i.nilai !== null"> · nilai {{ i.nilai }}</span>
            </span>
            <span class="text-[11.5px] font-semibold"
                  :style="{ color: i.lulus ? KEADAAN.baik : KEADAAN.gawat }">{{ i.hasil }}</span>
            <span class="ml-auto text-[11.5px] font-semibold shrink-0" :style="{ color: WARNA[i.keadaan] }">
              {{ i.keterangan }}
            </span>
            <button type="button" class="text-red-600 text-[11px] shrink-0"
                    @click="hapus(`/miners/${id}/induksi/${i.id}`, 'catatan induksi')">Hapus</button>
          </li>
        </ul>
        <p v-else class="text-[12px] text-stone-400 py-3">Belum ada induksi tercatat.</p>

        <form class="grid gap-2 md:grid-cols-4 pt-3 border-t border-stone-100" @submit.prevent="simpanInduksi">
          <select v-model="fInduksi.jenis" class="rounded-lg border-stone-200 text-[12px]" aria-label="Jenis">
            <option v-for="j in (props.opsi?.jenisInduksi ?? [])" :key="j">{{ j }}</option>
          </select>
          <input v-model="fInduksi.tanggal" type="date" required title="Tanggal induksi"
                 class="rounded-lg border-stone-200 text-[12px]">
          <input v-model="fInduksi.tgl_expired" type="date" title="Berlaku sampai"
                 class="rounded-lg border-stone-200 text-[12px]">
          <input v-model="fInduksi.pemberi" placeholder="Pemberi induksi"
                 class="rounded-lg border-stone-200 text-[12px]">
          <input v-model="fInduksi.nomor_registrasi" placeholder="No. registrasi"
                 class="rounded-lg border-stone-200 text-[12px]">
          <input v-model="fInduksi.lokasi" placeholder="Lokasi" class="rounded-lg border-stone-200 text-[12px]">
          <input v-model="fInduksi.nilai" type="number" min="0" max="100" placeholder="Nilai"
                 class="rounded-lg border-stone-200 text-[12px]">
          <select v-model="fInduksi.hasil" class="rounded-lg border-stone-200 text-[12px]" aria-label="Hasil">
            <option v-for="h in (props.opsi?.hasilInduksi ?? [])" :key="h">{{ h }}</option>
          </select>
          <button class="eq-btn-utama md:col-start-4" :disabled="fInduksi.processing">Catat induksi</button>
          <p v-if="fInduksi.errors.induksi" class="text-[11px] text-red-600 md:col-span-4">
            {{ fInduksi.errors.induksi }}
          </p>
        </form>
      </section>
    </template>

    <!-- ══════════ PENGAJUAN MCU ══════════ -->
    <template v-if="props.mode === 'mcu'">

      <form v-if="buka === 'pengajuan'"
            class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 grid gap-3 md:grid-cols-4"
            @submit.prevent="simpanPengajuan">
        <input v-model="fPengajuan.nomor_register" placeholder="Nomor register surat"
               class="rounded-lg border-stone-200 text-[12px]">
        <input v-model="fPengajuan.tanggal" type="date" required title="Tanggal surat"
               class="rounded-lg border-stone-200 text-[12px]">
        <input v-model="fPengajuan.kepada" placeholder="Kepada (klinik / rumah sakit)"
               class="rounded-lg border-stone-200 text-[12px]">
        <select v-model="fPengajuan.jenis" class="rounded-lg border-stone-200 text-[12px]" aria-label="Jenis">
          <option v-for="j in (props.opsi?.jenisMcu ?? [])" :key="j">{{ j }}</option>
        </select>
        <input v-model="fPengajuan.judul" placeholder="Perihal"
               class="rounded-lg border-stone-200 text-[12px] md:col-span-3">
        <button class="eq-btn-utama" :disabled="fPengajuan.processing">Simpan draf</button>
      </form>

      <section class="grid gap-4 sm:grid-cols-3">
        <div v-for="k in [
               ['Surat pengajuan', props.ringkasMcu?.total ?? 0, KEADAAN.netral],
               ['Menunggu tinjauan', props.ringkasMcu?.menunggu ?? 0, KEADAAN.ingat],
               ['Hasil belum kembali', props.ringkasMcu?.belumKembali ?? 0, KEADAAN.serius],
             ]" :key="k[0] as string"
             class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
          <p class="text-[11.5px] text-stone-500">{{ k[0] }}</p>
          <p class="text-[26px] font-bold leading-none mt-1 num"
             :style="{ color: Number(k[1]) ? (k[2] as string) : KEADAAN.netral }">{{ k[1] }}</p>
        </div>
      </section>

      <section v-for="m in (props.pengajuan ?? [])" :key="m.id"
               class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <h3 class="text-[14px] font-bold text-cam-ink">
              {{ m.nomor || 'Tanpa nomor register' }}
              <span class="text-[11px] font-normal text-stone-400">· {{ m.tanggal }} · {{ m.jenis }}</span>
            </h3>
            <p class="text-[11.5px] text-stone-500 mt-0.5">
              {{ m.judul || 'Perihal belum diisi' }}
              <span v-if="m.kepada"> · kepada {{ m.kepada }}</span>
            </p>
            <p v-if="m.alasanTolak" class="text-[11.5px] text-red-600 mt-1">Ditolak: {{ m.alasanTolak }}</p>
          </div>

          <div class="text-right shrink-0">
            <p class="text-[12px] font-bold"
               :style="{ color: m.status === 'disetujui' ? KEADAAN.baik
                              : m.status === 'ditolak' ? KEADAAN.gawat
                              : m.status === 'diajukan' ? KEADAAN.ingat : KEADAAN.netral }">
              {{ m.statusLabel }}
            </p>
            <p class="text-[11px] text-stone-400 mt-0.5">
              {{ m.jumlah }} nama<span v-if="m.belumKembali"> · {{ m.belumKembali }} belum kembali</span>
            </p>
          </div>
        </div>

        <div v-if="m.status !== 'draf'" class="mt-3">
          <Rantai :rantai="m.rantai" :tertinggal="m.tertinggal"
                  :dapat-paraf="m.dapatParaf" :saya-penentu="props.opsi?.sayaPenentu"
                  @paraf="t => paraf(`/miners/mcu/${m.id}/paraf`, t)" />
        </div>

        <div class="flex flex-wrap items-center gap-3 mt-3">
          <button type="button" class="text-[11.5px] font-semibold text-cam-lime-deep"
                  @click="bukaPengajuan = bukaPengajuan === m.id ? null : m.id">
            {{ bukaPengajuan === m.id ? 'Tutup daftar nama' : 'Lihat daftar nama' }}
          </button>
          <button v-if="m.dapatDiubah && m.jumlah" type="button"
                  class="text-[11.5px] font-semibold text-cam-lime-deep"
                  @click="ajukanPengajuan(m.id)">Ajukan</button>
          <button v-if="m.dapatDitinjau" type="button" class="text-[11.5px] font-semibold"
                  :style="{ color: KEADAAN.baik }"
                  @click="tinjau(`/miners/mcu/${m.id}/tinjau`, 'setujui')">Setujui</button>
          <button v-if="m.dapatDitinjau" type="button" class="text-[11.5px] font-semibold text-red-600"
                  @click="tinjau(`/miners/mcu/${m.id}/tinjau`, 'tolak')">Tolak</button>
          <button v-if="m.status === 'diajukan'" type="button" class="text-[11.5px] text-stone-500"
                  @click="tinjau(`/miners/mcu/${m.id}/tinjau`, 'tarik')">Tarik</button>
          <span v-if="!m.dapatDitinjau && m.sebabTakTinjau && m.status === 'diajukan'"
                class="text-[11px] text-stone-500 basis-full">
            {{ m.sebabTakTinjau }}
          </span>
          <button v-if="m.dapatDiubah" type="button" class="text-[11.5px] text-red-600 ml-auto"
                  @click="hapus(`/miners/mcu/${m.id}`, 'pengajuan ini')">Hapus</button>
        </div>

        <div v-if="bukaPengajuan === m.id" class="mt-4 pt-4 border-t border-stone-100">
          <div class="overflow-x-auto">
            <table class="min-w-full text-left text-[12px]">
              <thead>
                <tr class="text-stone-400 border-b border-stone-100">
                  <th class="py-2 pr-3">Nama</th><th class="py-2 pr-3">Periksa</th>
                  <th class="py-2 pr-3">Berlaku s/d</th><th class="py-2 pr-3">Hasil</th>
                  <th class="py-2 pr-3">Rujukan</th><th class="py-2"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="h in m.nama" :key="h.id" class="border-b border-stone-50 align-top">
                  <template v-if="!isiHasil[h.id]">
                    <td class="py-2 pr-3 font-semibold">
                      <Link :href="`/miners/${h.pasporId}`" class="text-cam-lime-deep">{{ h.nama }}</Link>
                    </td>
                    <td class="py-2 pr-3">{{ h.tglPeriksa || '—' }}</td>
                    <td class="py-2 pr-3">{{ h.tglExpired || '—' }}</td>
                    <td class="py-2 pr-3">
                      <span v-if="h.hasil" class="font-semibold">{{ h.hasil }}</span>
                      <span v-else class="text-stone-400">belum kembali</span>
                    </td>
                    <td class="py-2 pr-3">
                      <span v-if="h.rujukan" :style="{ color: h.tertunggak ? KEADAAN.gawat : KEADAAN.ingat }">
                        {{ h.rujukan }}
                      </span>
                      <span v-else class="text-stone-400">—</span>
                    </td>
                    <td class="py-2 text-right whitespace-nowrap">
                      <button type="button" class="text-[11px] font-semibold text-cam-lime-deep"
                              @click="mulaiIsi(h)">Isi hasil</button>
                      <button v-if="m.dapatDiubah" type="button" class="text-[11px] text-red-600 ml-2"
                              @click="hapus(`/miners/mcu/${m.id}/nama/${h.id}`, h.nama)">Keluarkan</button>
                    </td>
                  </template>

                  <td v-else colspan="6" class="py-2">
                    <p class="text-[11.5px] font-semibold text-cam-ink mb-2">{{ h.nama }}</p>
                    <div class="grid gap-2 md:grid-cols-4">
                      <input v-model="isiHasil[h.id].tgl_periksa" type="date" required title="Tanggal periksa"
                             class="rounded-lg border-stone-200 text-[12px]">
                      <input v-model="isiHasil[h.id].tgl_expired" type="date" title="Berlaku sampai"
                             class="rounded-lg border-stone-200 text-[12px]">
                      <select v-model="isiHasil[h.id].hasil" class="rounded-lg border-stone-200 text-[12px]" aria-label="Hasil">
                        <option v-for="x in (props.opsi?.hasilMcu ?? [])" :key="x">{{ x }}</option>
                      </select>
                      <input v-model="isiHasil[h.id].nomor" placeholder="No. hasil"
                             class="rounded-lg border-stone-200 text-[12px]">
                      <input v-model="isiHasil[h.id].pembatasan" placeholder="Pembatasan kerja"
                             class="rounded-lg border-stone-200 text-[12px] md:col-span-2">
                      <input v-model="isiHasil[h.id].rujukan" placeholder="Rujukan medis"
                             class="rounded-lg border-stone-200 text-[12px]">
                      <input v-model="isiHasil[h.id].outstanding" type="date" title="Tindak lanjut sampai"
                             class="rounded-lg border-stone-200 text-[12px]">
                    </div>
                    <div class="flex gap-3 mt-2">
                      <button type="button" class="eq-btn-utama text-[11px] py-1"
                              @click="simpanHasil(m.id, h.id)">Simpan hasil</button>
                      <button type="button" class="text-[11px] text-stone-500"
                              @click="batalIsi(h.id)">Batal</button>
                    </div>
                  </td>
                </tr>
                <tr v-if="!m.nama?.length">
                  <td colspan="6" class="py-6 text-center text-stone-400">
                    Belum ada nama dalam pengajuan ini.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <form v-if="m.dapatDiubah" class="grid gap-2 md:grid-cols-4 mt-3 pt-3 border-t border-stone-100"
                @submit.prevent="tambahNama(m.id)">
            <select v-model="fNama.paspor_id" required class="rounded-lg border-stone-200 text-[12px] md:col-span-2" aria-label="Paspor">
              <option value="">Pilih pekerja…</option>
              <option v-for="o in (props.opsi?.orang ?? [])" :key="o.id" :value="o.id">
                {{ o.nama }}<span v-if="o.jabatan"> — {{ o.jabatan }}</span>
              </option>
            </select>
            <input v-model="fNama.tgl_periksa" type="date" title="Rencana tanggal periksa"
                   class="rounded-lg border-stone-200 text-[12px]">
            <button class="eq-btn-utama" :disabled="fNama.processing">Tambah nama</button>
            <p v-if="fNama.errors.paspor_id" class="text-[11px] text-red-600 md:col-span-4">
              {{ fNama.errors.paspor_id }}
            </p>
          </form>
        </div>
      </section>

      <p v-if="!(props.pengajuan ?? []).length"
         class="rounded-2xl bg-white border border-stone-100 shadow-card p-10 text-center text-[12px] text-stone-400">
        Belum ada surat pengajuan MCU.
      </p>
    </template>
  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>

<script setup lang="ts">
import { computed, reactive } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
const { dialog, tanya, minta, batal, lanjut } = useDialog();


/*
  Prop halaman diambil lewat usePage(), bukan defineProps.

  Bentuk `defineProps<{ mode: string; [key: string]: any }>()` yang
  dipakai sebelumnya terbaca seolah menerima apa saja. Yang sebenarnya
  terjadi: penyusun Vue tidak dapat menurunkan nama prop dari sebuah
  index signature, sehingga HANYA `mode` yang benar-benar terdaftar
  sebagai prop. Seluruh sisanya jatuh ke $attrs — dan karena template
  ini berakar jamak (<Head> beserta pembungkusnya), atribut itu bahkan
  tidak tersangkut di mana pun.

  Akibatnya halaman merender kosong seluruhnya: tidak ada galat, tidak
  ada peringatan pada build produksi, hanya data yang dikirim server dan
  tidak pernah sampai ke tampilan. Uji sisi server tetap hijau, sebab
  yang salah bukan propnya melainkan penerimaannya.

  usePage() mengambil prop halaman apa adanya — termasuk yang dibagikan
  middleware — sehingga tidak ada daftar nama yang harus dirawat sejajar
  dengan controller-nya, dan tidak ada nama yang dapat hilang diam-diam.
*/
const props = usePage<any>().props as any;
const isAdmin = computed(() => Boolean(props.pengguna?.admin));

const judul: Record<string, string> = {
  dashboard: 'Dispatch & Pengangkutan',
  regu: 'Regu Angkut',
  armada: 'Armada Angkut',
  muatan: 'Penimbangan Muatan',
};

const angka = (v: unknown, d = 0) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: d, minimumFractionDigits: d }).format(Number(v || 0));
const label = (v: string) => String(v || '').replaceAll('-', ' ').replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());

const tautan = computed(() => props.tautan || {});
const untuk = (pola: string | undefined, id: number | string) => String(pola || '').replace('__ID__', String(id));
const sibuk = reactive<Record<number, boolean>>({});

const regu = useForm<any>({
  company_id: '', kode: '', tanggal: new Date().toISOString().slice(0, 10), shift: '1',
  pit: '', tujuan: '', material: 'overburden', alat_muat_id: '', jumlah_alat_muat: 1,
  jumlah_truk: 5, jarak_km: 3.4,
  waktu_muat_menit: 4, waktu_angkut_menit: 9, waktu_tumpah_menit: 2,
  waktu_kembali_menit: 7, waktu_antre_menit: 2,
  ritase: 0, tonase: 0, jam_kerja: 9, jam_delay: 1, batas_kecepatan_kmh: '', catatan: '',
});
const alat = useForm<any>({
  company_id: '', kode: '', nama: '', kelas: 'truk', tipe: '',
  kapasitas_ton: '', kapasitas_bucket_m3: '', faktor_isi: '', catatan: '',
});
const muatan = useForm<any>({ company_id: '', angkut_alat_id: '', rit_ke: '', muatan_ton: '', waktu_timbang: '', sumber: 'Jembatan timbang' });
const tindak = useForm<any>({ company_id: '', kode_pemicu: '', judul: '', prioritas: 'sedang', penanggung_jawab: '', target_selesai: '', uraian: '' });

const ditangani = computed(() => new Set(props.kodeDitangani || []));
const dipilih = reactive<{ id: number | null }>({ id: null });

const truk = computed(() => (props.alat || []).filter((a: any) => a.kelas === 'truk'));
const alatMuat = computed(() => (props.alat || []).filter((a: any) => a.kelas === 'alat-muat'));

function rentang() {
  router.get(window.location.pathname, { dari: props.dari, sampai: props.sampai }, { preserveState: true, replace: true });
}
function simpanRegu() { regu.post(tautan.value.reguSimpan, { preserveScroll: true, onSuccess: () => regu.reset('kode', 'catatan') }); }
async function hapusRegu(r: any) { if (await tanya(`Hapus catatan ${r.kode}?`)) router.delete(untuk(tautan.value.reguHapus, r.id), { preserveScroll: true }); }
function simpanAlat() { alat.post(tautan.value.alatSimpan, { preserveScroll: true, onSuccess: () => alat.reset('kode', 'nama', 'tipe', 'catatan') }); }
async function hapusAlat(a: any) { if (await tanya(`Hapus unit ${a.kode}?`)) router.delete(untuk(tautan.value.alatHapus, a.id), { preserveScroll: true }); }
function simpanMuatan(r: any) { muatan.post(untuk(tautan.value.muatanSimpan, r.id), { preserveScroll: true, onSuccess: () => muatan.reset('rit_ke', 'muatan_ton') }); }
function hapusMuatan(m: any) { router.delete(untuk(tautan.value.muatanHapus, m.id), { preserveScroll: true }); }

function alur(pola: string, baris: any, isi: Record<string, any> = {}) {
  sibuk[baris.id] = true;
  router.post(untuk(pola, baris.id), isi, { preserveScroll: true, onFinish: () => { sibuk[baris.id] = false; } });
}
async function setujui(baris: any) {
  if (!await tanya(`Setujui catatan ${baris.kode}? Setelah disetujui tidak dapat diubah.`)) return;
  alur(tautan.value.reguSetujui, baris);
}
async function tolak(baris: any) {
  const a = await minta({ judul: `Tolak catatan ${baris.kode}?`, label: 'Alasan penolakan',
    jenis: 'panjang', min: 5, labelAksi: 'Tolak', nada: 'bahaya' });
  if (a === null) return;
  alur(tautan.value.reguTolak, baris, { alasan_tolak: a });
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
const warnaMf: Record<string, string> = {
  seimbang: 'text-emerald-700', 'lebih-truk': 'text-amber-700',
  'kurang-truk': 'text-amber-700', 'tak-diketahui': 'text-stone-400',
};
const lebarBagian: Record<string, string> = {
  Muat: 'bg-sky-400', Angkut: 'bg-cam-ink', Tumpah: 'bg-violet-400',
  Kembali: 'bg-stone-400', Antre: 'bg-red-400',
};
</script>

<template>
  <Head :title="judul[props.mode]" />

  <div class="max-w-[1400px] mx-auto space-y-5">
    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-[10px] font-bold uppercase tracking-[.16em] text-cam-orange">Engineering · Dispatch &amp; Hauling</p>
        <h2 class="text-2xl font-extrabold tracking-tight text-stone-800">{{ judul[props.mode] }}</h2>
        <p class="text-[12px] text-stone-500 mt-1">Bukan berapa yang terangkut, melainkan bagaimana — keseimbangan armada, waktu edar, dan kepatuhan muatan.</p>
      </div>
      <div class="flex gap-2">
        <input v-model="props.dari" type="date" class="rounded-lg border-stone-200 text-[11px]" aria-label="Tanggal mulai">
        <input v-model="props.sampai" type="date" class="rounded-lg border-stone-200 text-[11px]" aria-label="Tanggal akhir">
        <button class="eq-btn-lain" type="button" @click="rentang">Terapkan</button>
      </div>
    </section>

    <!--
      Tonase di modul ini adalah bagian dari tonase pit pada Mine
      Operations, bukan tambahannya. Dinyatakan di muka supaya tidak ada
      yang menjumlahkan keduanya dan mendapat angka yang selalu terlalu
      besar tanpa tahu dari mana kelebihannya datang.
    -->
    <section class="rounded-2xl border border-sky-100 bg-sky-50 px-4 py-3">
      <p class="text-[11.5px] text-stone-700 leading-relaxed">
        <b>Tonase di sini bukan tambahan bagi Mine Operations.</b>
        Yang dicatat adalah bagian dari tonase pit yang sama, dirinci per regu angkut supaya
        dapat dijelaskan asal-usulnya. Untuk angka produksi yang dilaporkan keluar, pakai Mine Operations.
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
      <Link v-for="i in [['dashboard','Ringkasan',tautan.dashboard],['regu','Regu Angkut',tautan.regu],['muatan','Penimbangan',tautan.muatan],['armada','Armada',tautan.armada]]"
            :key="i[0]" :href="i[2] as string" class="rounded-full px-4 py-2 text-[11px] font-bold"
            :class="props.mode === i[0] ? 'bg-cam-ink text-white' : 'bg-white text-stone-500 border border-stone-200'">{{ i[1] }}</Link>
      <a :href="tautan.cetak" class="ml-auto rounded-full px-4 py-2 text-[11px] font-bold bg-white text-cam-orange border border-cam-orange/40">Cetak laporan</a>
    </nav>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
      <article v-for="c in [
        { l: 'Regu disetujui', v: `${props.ringkas?.disetujui || 0} / ${props.ringkas?.regu || 0}`, c: 'text-cam-ink' },
        { l: 'Ritase', v: angka(props.ringkas?.ritase), c: 'text-cam-ink' },
        { l: 'Tonase terinci', v: `${angka(props.ringkas?.tonase)} t`, c: 'text-sky-700' },
        { l: 'Match factor rata', v: props.ringkas?.mfRata === null ? '—' : angka(props.ringkas?.mfRata, 2), c: 'text-violet-700' },
        { l: 'Antre rata', v: props.ringkas?.antreRata === null ? '—' : `${angka(props.ringkas?.antreRata, 1)}%`, c: (props.ringkas?.antreRata || 0) > (props.opsi?.antreWajar || 15) ? 'text-red-600' : 'text-emerald-600' },
        { l: 'Hilang karena antre', v: `${angka(props.ringkas?.hilangAntre)} t`, c: (props.ringkas?.hilangAntre || 0) > 0 ? 'text-amber-700' : 'text-emerald-600' },
      ]" :key="c.l" class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
        <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">{{ c.l }}</p>
        <p class="mt-2 text-lg font-extrabold" :class="c.c">{{ c.v }}</p>
      </article>
    </section>

    <!-- ═══════════ RINGKASAN ═══════════ -->
    <template v-if="props.mode === 'dashboard'">
      <!--
        Kepatuhan muatan berdiri paling atas karena ia satu-satunya
        indikator di halaman ini yang akibatnya jatuh pada rem truk,
        bukan pada biaya.
      -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 class="font-bold text-[14px]">Kepatuhan muatan 10/10/20</h3>
            <p class="text-[11.5px] text-stone-500 mt-1">
              Rata-rata tidak melebihi {{ angka(props.opsi?.muatan?.rata) }}%, tidak lebih dari
              {{ angka(props.opsi?.muatan?.porsi) }}% muatan di atas {{ angka(props.opsi?.muatan?.lebih) }}%,
              dan tidak satu pun di atas {{ angka(props.opsi?.muatan?.puncak) }}%.
            </p>
          </div>
          <span class="rounded-full px-3 py-1 text-[11px] font-bold"
                :class="props.kepatuhan?.patuh === true ? 'bg-emerald-100 text-emerald-700'
                       : props.kepatuhan?.patuh === false ? 'bg-red-100 text-red-700' : 'bg-stone-100 text-stone-600'">
            {{ props.kepatuhan?.patuh === true ? 'Patuh' : props.kepatuhan?.patuh === false ? 'Melanggar' : 'Belum dapat dinilai' }}
          </span>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <div v-for="k in [
            { l: 'Penimbangan', v: angka(props.kepatuhan?.n), s: props.kepatuhan?.cukupData ? 'cukup untuk menilai sebaran' : `minimum ${props.opsi?.muatan?.minimum}` },
            { l: 'Rata-rata muatan', v: props.kepatuhan?.rata === null ? '—' : `${angka(props.kepatuhan?.rata, 1)}%`, s: 'terhadap kapasitas nominal' },
            { l: `Di atas ${angka(props.opsi?.muatan?.lebih)}%`, v: `${angka(props.kepatuhan?.lebih110)} rit`, s: props.kepatuhan?.porsi110 === null ? '—' : `${angka(props.kepatuhan?.porsi110, 1)}% dari seluruh rit` },
            { l: `Di atas ${angka(props.opsi?.muatan?.puncak)}%`, v: `${angka(props.kepatuhan?.lebih120)} rit`, s: 'batas mutlak, berlaku per muatan' },
          ]" :key="k.l" class="rounded-xl bg-stone-50 px-3 py-2.5">
            <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">{{ k.l }}</p>
            <p class="text-[15px] font-extrabold mt-0.5">{{ k.v }}</p>
            <p class="text-[10.5px] text-stone-500 mt-0.5">{{ k.s }}</p>
          </div>
        </div>
        <p class="text-[11.5px] text-stone-600 mt-3">{{ props.kepatuhan?.alasan }}</p>
      </section>

      <section class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        <div v-for="r in props.regu || []" :key="r.id" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <b class="text-[14px]">{{ r.kode }}</b>
              <p class="text-[11px] text-stone-400">{{ r.pit || '—' }} · Shift {{ r.shift }} · {{ r.tanggalLabel }}</p>
            </div>
            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold" :class="warnaStatus[r.status]">{{ r.statusLabel }}</span>
          </div>

          <div class="mt-3 grid grid-cols-3 gap-2 text-[11.5px]">
            <div><p class="text-stone-400">Armada</p><p class="font-bold">{{ r.jumlahTruk }} truk / {{ r.jumlahAlatMuat }} muat</p></div>
            <div><p class="text-stone-400">Ritase</p><p class="font-bold">{{ angka(r.ritase) }}<span v-if="r.ritaseTeoritis" class="text-stone-400 font-normal"> / {{ angka(r.ritaseTeoritis) }}</span></p></div>
            <div><p class="text-stone-400">Tonase</p><p class="font-bold">{{ angka(r.tonase) }} t</p></div>
          </div>

          <div class="mt-3 rounded-xl bg-stone-50 px-3 py-2.5">
            <div class="flex items-baseline justify-between">
              <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">Match factor</p>
              <b class="text-[13px]" :class="warnaMf[r.bacaMf?.kelas]">{{ r.matchFactor === null ? '—' : angka(r.matchFactor, 2) }}</b>
            </div>
            <p class="text-[10.5px] text-stone-500 mt-0.5">{{ r.bacaMf?.ket }}</p>
          </div>

          <!-- Rincian waktu edar; antre digambar merah karena hanya
               bagian inilah yang tidak menghasilkan apa pun. -->
          <div v-if="(r.edar?.rincian || []).length" class="mt-3">
            <div class="flex items-baseline justify-between">
              <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">Waktu edar</p>
              <span class="text-[11px] text-stone-500">{{ angka(r.edar?.nyata, 1) }} menit</span>
            </div>
            <div class="mt-1.5 flex h-2.5 overflow-hidden rounded-full bg-stone-100">
              <div v-for="b in r.edar.rincian" :key="b.nama" :style="{ width: `${b.persen}%` }"
                   :class="lebarBagian[b.nama] || 'bg-stone-300'" :title="`${b.nama} ${b.menit} menit`"></div>
            </div>
            <div class="mt-1.5 flex flex-wrap gap-x-3 gap-y-0.5 text-[10.5px] text-stone-500">
              <span v-for="b in r.edar.rincian" :key="`l-${b.nama}`">{{ b.nama }} {{ angka(b.persen, 0) }}%</span>
            </div>
          </div>

          <div class="mt-3 grid grid-cols-3 gap-2 text-[11.5px]">
            <div><p class="text-stone-400">Utilisasi</p><p class="font-bold">{{ r.utilisasi === null ? '—' : `${angka(r.utilisasi, 1)}%` }}</p></div>
            <div><p class="text-stone-400">Kecepatan</p>
              <p class="font-bold" :class="r.lampauiKecepatan ? 'text-red-600' : ''">{{ r.kecepatan === null ? '—' : `${angka(r.kecepatan, 1)} km/j` }}</p></div>
            <div><p class="text-stone-400">Hilang antre</p><p class="font-bold">{{ r.hilangAntre === null ? '—' : `${angka(r.hilangAntre)} t` }}</p></div>
          </div>

          <div v-if="r.kepatuhan?.patuh === false" class="mt-3 rounded-xl bg-red-50 border border-red-100 px-3 py-2.5">
            <p class="text-[10px] uppercase tracking-wide font-bold text-red-600">Muatan</p>
            <p class="text-[11px] text-stone-700 mt-1">{{ r.kepatuhan.alasan }}</p>
          </div>
        </div>
        <p v-if="!(props.regu || []).length" class="text-[12px] text-stone-400">Belum ada catatan regu pada rentang ini.</p>
      </section>
    </template>

    <!-- ═══════════ REGU ANGKUT ═══════════ -->
    <template v-if="props.mode === 'regu'">
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="font-bold text-[14px]">Catat regu angkut</h3>
        <p class="text-[11.5px] text-stone-500 mt-1">
          Satu baris = satu alat muat beserta truk yang melayaninya, pada satu shift dan satu rute.
          <b>Antre dicatat terpisah</b> dan tidak ikut dijumlahkan ke waktu edar yang menghitung match factor —
          antre adalah akibat ketidakseimbangan, dan memasukkannya membuat armada yang kelebihan truk terbaca seimbang.
        </p>
        <form class="mt-3 grid gap-3 md:grid-cols-4" @submit.prevent="simpanRegu">
          <label class="text-[11px] font-bold text-stone-500">Kode
            <input v-model="regu.kode" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Tanggal
            <input v-model="regu.tanggal" type="date" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Shift
            <select v-model="regu.shift" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
              <option v-for="s in props.opsi?.shift || []" :key="s" :value="s">Shift {{ s }}</option>
            </select></label>
          <label class="text-[11px] font-bold text-stone-500">Material
            <select v-model="regu.material" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
              <option v-for="m in props.opsi?.material || []" :key="m" :value="m">{{ label(m) }}</option>
            </select></label>

          <label class="text-[11px] font-bold text-stone-500">Pit / muka gali
            <input v-model="regu.pit" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Tujuan
            <input v-model="regu.tujuan" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Alat muat
            <select v-model="regu.alat_muat_id" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
              <option value="">— pilih —</option>
              <option v-for="a in alatMuat" :key="a.id" :value="a.id">{{ a.kode }}</option>
            </select></label>
          <label class="text-[11px] font-bold text-stone-500">Jarak sekali jalan (km)
            <input v-model="regu.jarak_km" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>

          <p class="md:col-span-4 text-[10px] font-bold uppercase tracking-wide text-stone-400 pt-1">Armada</p>
          <label class="text-[11px] font-bold text-stone-500">Jumlah truk
            <input v-model="regu.jumlah_truk" type="number" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Jumlah alat muat
            <input v-model="regu.jumlah_alat_muat" type="number" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Jam kerja
            <input v-model="regu.jam_kerja" type="number" step="0.1" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Jam delay
            <input v-model="regu.jam_delay" type="number" step="0.1" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>

          <p class="md:col-span-4 text-[10px] font-bold uppercase tracking-wide text-stone-400 pt-1">Waktu edar per rit (menit)</p>
          <label class="text-[11px] font-bold text-stone-500">Muat
            <input v-model="regu.waktu_muat_menit" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Angkut isi
            <input v-model="regu.waktu_angkut_menit" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Tumpah
            <input v-model="regu.waktu_tumpah_menit" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Kembali kosong
            <input v-model="regu.waktu_kembali_menit" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Antre
            <input v-model="regu.waktu_antre_menit" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>

          <p class="md:col-span-4 text-[10px] font-bold uppercase tracking-wide text-stone-400 pt-1">Hasil shift</p>
          <label class="text-[11px] font-bold text-stone-500">Ritase
            <input v-model="regu.ritase" type="number" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Tonase (ton)
            <input v-model="regu.tonase" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Batas kecepatan rute (km/jam)
            <input v-model="regu.batas_kecepatan_kmh" type="number" step="1" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Catatan
            <input v-model="regu.catatan" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <button class="eq-btn-utama self-end" :disabled="regu.processing">Simpan draf</button>
        </form>
        <p v-for="(e, k) in regu.errors" :key="k" class="mt-2 text-[11px] text-red-600">{{ e }}</p>
      </section>

      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-[11.5px]">
            <thead class="bg-stone-50 text-left text-stone-400 uppercase text-[10px] tracking-wide">
              <tr>
                <th class="px-5 py-2.5">Kode</th><th class="px-5 py-2.5">Tanggal</th>
                <th class="px-5 py-2.5 text-right">Truk</th><th class="px-5 py-2.5 text-right">MF</th>
                <th class="px-5 py-2.5 text-right">Antre</th><th class="px-5 py-2.5 text-right">Ritase</th>
                <th class="px-5 py-2.5">Status</th><th class="px-5 py-2.5 text-right">Alur</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in props.regu || []" :key="r.id" class="border-b border-stone-50">
                <td class="px-5 py-3 font-semibold">
                  <button type="button" class="underline decoration-dotted" @click="dipilih.id = dipilih.id === r.id ? null : r.id">{{ r.kode }}</button>
                </td>
                <td class="px-5 py-3">{{ r.tanggalLabel }} · S{{ r.shift }}</td>
                <td class="px-5 py-3 text-right">{{ r.jumlahTruk }}</td>
                <td class="px-5 py-3 text-right font-bold" :class="warnaMf[r.bacaMf?.kelas]">{{ r.matchFactor === null ? '—' : angka(r.matchFactor, 2) }}</td>
                <td class="px-5 py-3 text-right">{{ r.porsiAntre === null ? '—' : `${angka(r.porsiAntre, 1)}%` }}</td>
                <td class="px-5 py-3 text-right">{{ angka(r.ritase) }}</td>
                <td class="px-5 py-3">
                  <span class="rounded-full px-2 py-0.5 text-[10px] font-bold" :class="warnaStatus[r.status]">{{ r.statusLabel }}</span>
                  <p v-if="r.alur?.alasanTolak" class="text-[10.5px] text-red-600 mt-1">{{ r.alur.alasanTolak }}</p>
                </td>
                <td class="px-5 py-3 text-right whitespace-nowrap">
                  <button v-if="r.alur?.dapatDiajukan" type="button" class="text-[11px] font-bold text-cam-orange-dark disabled:opacity-40"
                          :disabled="sibuk[r.id]" @click="alur(tautan.reguAjukan, r)">Ajukan</button>
                  <template v-if="r.alur?.dapatDitinjau">
                    <button type="button" class="ml-3 text-[11px] font-bold text-emerald-700 disabled:opacity-40"
                            :disabled="sibuk[r.id]" @click="setujui(r)">Setujui</button>
                    <button type="button" class="ml-3 text-[11px] font-bold text-red-600 disabled:opacity-40"
                            :disabled="sibuk[r.id]" @click="tolak(r)">Tolak</button>
                  </template>
                  <button v-if="isAdmin && r.status !== 'disetujui'" type="button" class="ml-3 py-1.5 -my-1.5 text-[11px] font-bold text-stone-400" @click="hapusRegu(r)">Hapus</button>
                </td>
              </tr>
              <tr v-if="!(props.regu || []).length"><td colspan="8" class="px-5 py-8 text-center text-stone-400">Belum ada catatan regu pada rentang ini.</td></tr>
            </tbody>
          </table>
        </div>

        <div v-for="r in (props.regu || []).filter((x: any) => x.id === dipilih.id)" :key="`d-${r.id}`" class="border-t border-stone-100 p-5 bg-stone-50/60">
          <h4 class="font-bold text-[13px]">Penimbangan muatan — {{ r.kode }}</h4>
          <p class="text-[11.5px] text-stone-500 mt-1">
            Penimbangan tetap dapat ditambahkan setelah catatan disetujui: ia bukan bagian dari angka yang ditinjau,
            melainkan pembacaan alat yang menilai angka itu.
          </p>
          <form class="mt-2 grid gap-2 md:grid-cols-6" @submit.prevent="simpanMuatan(r)">
            <select v-model="muatan.angkut_alat_id" class="rounded-lg border-stone-200 text-[11px]" required aria-label="Alat angkut">
              <option value="">— truk —</option>
              <option v-for="t in truk" :key="t.id" :value="t.id">{{ t.kode }}</option>
            </select>
            <input v-model="muatan.rit_ke" type="number" placeholder="Rit ke" class="rounded-lg border-stone-200 text-[11px]">
            <input v-model="muatan.muatan_ton" type="number" step="0.01" placeholder="Muatan ton" class="rounded-lg border-stone-200 text-[11px]" required>
            <input v-model="muatan.waktu_timbang" type="datetime-local" class="rounded-lg border-stone-200 text-[11px]">
            <input v-model="muatan.sumber" type="text" placeholder="Sumber" class="rounded-lg border-stone-200 text-[11px]">
            <button class="eq-btn-lain text-[11px]" :disabled="muatan.processing">+ Timbang</button>
          </form>
          <p v-for="(e, k) in muatan.errors" :key="k" class="mt-2 text-[11px] text-red-600">{{ e }}</p>

          <div class="overflow-x-auto"><table v-if="(r.muatan || []).length" class="w-full mt-3 text-[11.5px]">
            <tbody>
              <tr v-for="m in r.muatan" :key="m.id" class="border-t border-stone-100">
                <td class="py-2">{{ m.alat }}</td>
                <td class="py-2 text-stone-400">rit {{ m.ritKe || '—' }}</td>
                <td class="py-2 font-bold" :class="m.lampauiPuncak ? 'text-red-600' : ''">{{ angka(m.muatan, 2) }} t</td>
                <td class="py-2" :class="m.lampauiPuncak ? 'text-red-600 font-bold' : 'text-stone-500'">
                  {{ m.persen === null ? 'kapasitas belum diisi' : `${angka(m.persen, 1)}%` }}
                </td>
                <td class="py-2 text-right"><button v-if="isAdmin" type="button" class="py-1.5 -my-1.5 text-[11px] text-stone-400" @click="hapusMuatan(m)">Hapus</button></td>
              </tr>
            </tbody>
          </table></div>
        </div>
      </section>
    </template>

    <!-- ═══════════ PENIMBANGAN ═══════════ -->
    <template v-if="props.mode === 'muatan'">
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <div class="px-5 py-4 border-b border-stone-100">
          <h3 class="font-bold text-[14px]">Seluruh penimbangan</h3>
          <p class="text-[11.5px] text-stone-500 mt-1">
            Muatan di atas {{ angka(props.opsi?.muatan?.puncak) }}% kapasitas nominal diperingatkan sejak penimbangannya masuk,
            tanpa menunggu catatan shiftnya ditinjau — kelebihannya diserap retarder saat menuruni jalan angkut hari ini juga.
          </p>
        </div>
        <div class="overflow-x-auto"><table class="w-full text-[11.5px]">
          <thead class="bg-stone-50 text-left text-stone-400 uppercase text-[10px] tracking-wide">
            <tr><th class="px-5 py-2.5">Regu</th><th class="px-5 py-2.5">Truk</th>
              <th class="px-5 py-2.5 text-right">Rit</th><th class="px-5 py-2.5 text-right">Muatan</th>
              <th class="px-5 py-2.5 text-right">Nominal</th><th class="px-5 py-2.5 text-right">Persen</th>
              <th class="px-5 py-2.5">Sumber</th><th class="px-5 py-2.5"></th></tr>
          </thead>
          <tbody>
            <tr v-for="m in props.muatan || []" :key="m.id" class="border-b border-stone-50" :class="m.lampauiPuncak ? 'bg-red-50/60' : ''">
              <td class="px-5 py-3 font-semibold">{{ m.reguKode }}</td>
              <td class="px-5 py-3">{{ m.alat }}</td>
              <td class="px-5 py-3 text-right text-stone-500">{{ m.ritKe || '—' }}</td>
              <td class="px-5 py-3 text-right">{{ angka(m.muatan, 2) }} t</td>
              <td class="px-5 py-3 text-right text-stone-500">{{ m.nominal === null ? '—' : `${angka(m.nominal, 1)} t` }}</td>
              <td class="px-5 py-3 text-right font-bold" :class="m.lampauiPuncak ? 'text-red-600' : ''">
                {{ m.persen === null ? '—' : `${angka(m.persen, 1)}%` }}
              </td>
              <td class="px-5 py-3 text-stone-500">{{ m.sumber || '—' }}</td>
              <td class="px-5 py-3 text-right"><button v-if="isAdmin" type="button" class="py-1.5 -my-1.5 text-[11px] text-stone-400" @click="hapusMuatan(m)">Hapus</button></td>
            </tr>
            <tr v-if="!(props.muatan || []).length"><td colspan="8" class="px-5 py-8 text-center text-stone-400">Belum ada penimbangan pada rentang ini.</td></tr>
          </tbody>
        </table></div>
      </section>
    </template>

    <!-- ═══════════ ARMADA ═══════════ -->
    <template v-if="props.mode === 'armada'">
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="font-bold text-[14px]">Daftarkan unit armada</h3>
        <p class="text-[11.5px] text-stone-500 mt-1">
          <b>Kapasitas nominal wajib diisi untuk truk.</b> Tanpa angka itu muatannya tetap tercatat, tetapi tidak ada
          pembagi bagi kaidah 10/10/20 — muatan berlebih pada unit itu tidak akan pernah ketahuan.
        </p>
        <form class="mt-3 grid gap-3 md:grid-cols-4" @submit.prevent="simpanAlat">
          <label class="text-[11px] font-bold text-stone-500">Kode
            <input v-model="alat.kode" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Nama
            <input v-model="alat.nama" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Kelas
            <select v-model="alat.kelas" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
              <option v-for="k in props.opsi?.kelas || []" :key="k" :value="k">{{ label(k) }}</option>
            </select></label>
          <label class="text-[11px] font-bold text-stone-500">Tipe
            <input v-model="alat.tipe" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Kapasitas nominal (ton)
            <input v-model="alat.kapasitas_ton" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Kapasitas mangkuk (m³)
            <input v-model="alat.kapasitas_bucket_m3" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Faktor isi
            <input v-model="alat.faktor_isi" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <button class="eq-btn-utama self-end" :disabled="alat.processing">Simpan</button>
        </form>
        <p v-for="(e, k) in alat.errors" :key="k" class="mt-2 text-[11px] text-red-600">{{ e }}</p>
      </section>

      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <div class="overflow-x-auto"><table class="w-full text-[11.5px]">
          <thead class="bg-stone-50 text-left text-stone-400 uppercase text-[10px] tracking-wide">
            <tr><th class="px-5 py-2.5">Kode</th><th class="px-5 py-2.5">Nama</th><th class="px-5 py-2.5">Kelas</th>
              <th class="px-5 py-2.5">Tipe</th><th class="px-5 py-2.5 text-right">Kapasitas</th><th class="px-5 py-2.5"></th></tr>
          </thead>
          <tbody>
            <tr v-for="a in props.alat || []" :key="a.id" class="border-b border-stone-50">
              <td class="px-5 py-3 font-semibold">{{ a.kode }}</td>
              <td class="px-5 py-3">{{ a.nama || '—' }}</td>
              <td class="px-5 py-3 text-stone-500">{{ label(a.kelas) }}</td>
              <td class="px-5 py-3 text-stone-500">{{ a.tipe || '—' }}</td>
              <td class="px-5 py-3 text-right">
                <template v-if="a.kelas === 'truk'">
                  <span v-if="a.kapasitasDitetapkan">{{ angka(a.kapasitas, 1) }} t</span>
                  <span v-else class="text-red-600 font-bold">belum diisi</span>
                </template>
                <template v-else>{{ a.bucket === null ? '—' : `${angka(a.bucket, 1)} m³` }}</template>
              </td>
              <td class="px-5 py-3 text-right"><button v-if="isAdmin" type="button" class="py-1.5 -my-1.5 text-[11px] text-stone-400" @click="hapusAlat(a)">Hapus</button></td>
            </tr>
            <tr v-if="!(props.alat || []).length"><td colspan="6" class="px-5 py-8 text-center text-stone-400">Belum ada unit terdaftar.</td></tr>
          </tbody>
        </table></div>
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

      <div class="overflow-x-auto"><table class="w-full mt-4 text-[11.5px]">
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
      </table></div>
    </section>
  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>

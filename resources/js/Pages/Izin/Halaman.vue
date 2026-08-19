<script setup lang="ts">
import { computed, reactive } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
const { dialog, tanya, minta, batal, lanjut } = useDialog();


/*
  Prop halaman diambil lewat usePage(), bukan defineProps — lihat
  tests/Feature/PropHalamanTest.php untuk sebabnya.
*/
const props = usePage<any>().props as any;
const isAdmin = computed(() => Boolean(props.pengguna?.admin));

const judul: Record<string, string> = {
  dashboard: 'Izin Kerja Aman',
  daftar: 'Daftar Izin Kerja',
  syarat: 'Daftar Periksa Izin',
  ambang: 'Ambang Gas',
};

const angka = (v: unknown, d = 0) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: d, minimumFractionDigits: d }).format(Number(v || 0));
const label = (v: string) => String(v || '').replaceAll('-', ' ').replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());

const tautan = computed(() => props.tautan || {});
const untuk = (pola: string | undefined, id: number | string) => String(pola || '').replace('__ID__', String(id));
const sibuk = reactive<Record<number, boolean>>({});
const dipilih = reactive<{ id: number | null }>({ id: null });

const izin = useForm<any>({
  company_id: '', nomor: '', jenis: 'panas', lokasi: '', uraian: '', pelaksana: '',
  jumlah_pekerja: '', pengawas_lapangan: '', mulai: '', selesai: '', batas_uji_menit: '', catatan: '',
});
const gas = useForm<any>({ company_id: '', waktu_uji: '', o2: '', lel: '', co: '', h2s: '', alat: '', petugas: '', catatan: '' });
const syarat = useForm<any>({ company_id: '', jenis: 'panas', teks: '', urutan: 0, wajib: true });
const ambang = useForm<any>({ company_id: '', parameter: 'o2', batas_min: '', batas_maks: '', satuan: '', acuan: '' });
const tindak = useForm<any>({ company_id: '', kode_pemicu: '', judul: '', prioritas: 'sedang', penanggung_jawab: '', target_selesai: '', uraian: '' });

const ditangani = computed(() => new Set(props.kodeDitangani || []));
const perluGas = computed(() => (props.opsi?.wajibUjiGas || []).includes(izin.jenis));
const berlaku = computed(() => (props.izin || []).filter((i: any) => i.sedangBerlaku));

function rentang() {
  router.get(window.location.pathname, { dari: props.dari, sampai: props.sampai }, { preserveState: true, replace: true });
}
function simpanIzin() { izin.post(tautan.value.izinSimpan, { preserveScroll: true, onSuccess: () => izin.reset('nomor', 'uraian', 'catatan') }); }
async function hapusIzin(i: any) { if (await tanya(`Hapus izin ${i.nomor}?`)) router.delete(untuk(tautan.value.izinHapus, i.id), { preserveScroll: true }); }
function simpanGas(i: any) { gas.post(untuk(tautan.value.gasSimpan, i.id), { preserveScroll: true, onSuccess: () => gas.reset('o2', 'lel', 'co', 'h2s') }); }
async function hapusGas(g: any) {
  if (!await tanya(`Hapus pengukuran gas ${g.waktu}?`)) return;
  router.delete(untuk(tautan.value.gasHapus, g.id), { preserveScroll: true });
}
function simpanSyarat() { syarat.post(tautan.value.syaratSimpan, { preserveScroll: true, onSuccess: () => syarat.reset('teks') }); }
async function hapusSyarat(s: any) {
  if (!await tanya(`Hapus syarat "${s.teks}"?`)) return;
  router.delete(untuk(tautan.value.syaratHapus, s.id), { preserveScroll: true });
}
function simpanAmbang() { ambang.post(tautan.value.ambangSimpan, { preserveScroll: true }); }
async function hapusAmbang(a: any) {
  /* Baris ini dicari dari daftar lain oleh templatnya; bila tidak ketemu
     tidak ada yang boleh dihapus. Tanpa penjagaan ini `a.id` menjadi
     undefined dan permintaannya tetap terkirim ke alamat yang salah. */
  if (!a) return;
  if (!await tanya(`Hapus ambang ${String(a.parameter ?? '').toUpperCase()} yang ditetapkan situs?`)) return;
  router.delete(untuk(tautan.value.ambangHapus, a.id), { preserveScroll: true });
}

function ubahPeriksa(p: any, nilai: boolean) {
  router.put(untuk(tautan.value.periksaUbah, p.id), { terpenuhi: nilai, keterangan: p.keterangan || '' }, { preserveScroll: true });
}

function alur(pola: string, baris: any, isi: Record<string, any> = {}) {
  sibuk[baris.id] = true;
  router.post(untuk(pola, baris.id), isi, { preserveScroll: true, onFinish: () => { sibuk[baris.id] = false; } });
}
async function terbitkan(i: any) {
  if (!await tanya(`Terbitkan izin ${i.nomor}? Pekerjaan boleh dimulai setelah ini.`)) return;
  alur(tautan.value.izinTerbitkan, i);
}
async function tolak(i: any) {
  const a = await minta({ judul: `Tolak izin ${i.nomor}?`, label: 'Alasan penolakan',
    jenis: 'panjang', min: 5, labelAksi: 'Tolak', nada: 'bahaya' });
  if (a === null) return;
  alur(tautan.value.izinTolak, i, { alasan_tolak: a });
}
async function tutup(i: any) {
  const c = await minta({ judul: `Tutup izin ${i.nomor}?`, label: 'Catatan penutupan',
    pesan: 'Keadaan area, orang sudah keluar, peralatan sudah diamankan, dan sebagainya.',
    jenis: 'panjang', wajib: false, labelAksi: 'Tutup izin' });
  if (c === null) return;
  alur(tautan.value.izinTutup, i, { catatan_penutupan: c });
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
const warnaWaktu: Record<string, string> = {
  berlaku: 'text-emerald-700', 'belum-mulai': 'text-sky-700',
  lewat: 'text-red-600', 'tak-diketahui': 'text-stone-400',
};
</script>

<template>
  <Head :title="judul[props.mode]" />

  <div class="max-w-[1400px] mx-auto space-y-5">
    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-[10px] font-bold uppercase tracking-[.16em] text-cam-orange">Safety · Permit to Work</p>
        <h2 class="text-2xl font-extrabold tracking-tight text-stone-800">{{ judul[props.mode] }}</h2>
        <p class="text-[12px] text-stone-500 mt-1">Izin yang diterbitkan sebelum pekerjaan dimulai — dan yang harus ditutup setelah selesai.</p>
      </div>
      <div class="flex gap-2">
        <input v-model="props.dari" type="date" class="rounded-lg border-stone-200 text-[11px]" aria-label="Tanggal mulai">
        <input v-model="props.sampai" type="date" class="rounded-lg border-stone-200 text-[11px]" aria-label="Tanggal akhir">
        <button class="eq-btn-lain" type="button" @click="rentang">Terapkan</button>
      </div>
    </section>

    <!--
      Batas modul dinyatakan di muka: aplikasi memeriksa kelengkapan,
      kesegaran, dan tumpang tindih. Penilaian atas bahayanya tetap milik
      penerbit izin dan pengawas yang berdiri di lokasi.
    -->
    <section class="rounded-2xl border border-sky-100 bg-sky-50 px-4 py-3">
      <p class="text-[11.5px] text-stone-700 leading-relaxed">
        <b>Yang diperiksa aplikasi: kelengkapan, kesegaran, dan tumpang tindih.</b>
        Penilaian atas bahaya pekerjaannya tetap milik penerbit izin dan pengawas yang berdiri di lokasi.
        Izin tidak dapat diterbitkan selama syarat wajib belum terpenuhi atau uji gasnya sudah basi.
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
      <Link v-for="i in [['dashboard','Ringkasan',tautan.dashboard],['daftar','Daftar Izin',tautan.daftar],['syarat','Daftar Periksa',tautan.syarat],['ambang','Ambang Gas',tautan.ambang]]"
            :key="i[0]" :href="i[2] as string" class="rounded-full px-4 py-2 text-[11px] font-bold"
            :class="props.mode === i[0] ? 'bg-cam-ink text-white' : 'bg-white text-stone-500 border border-stone-200'">{{ i[1] }}</Link>
      <a :href="tautan.cetak" class="ml-auto rounded-full px-4 py-2 text-[11px] font-bold bg-white text-cam-orange border border-cam-orange/40">Cetak laporan</a>
    </nav>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
      <article v-for="c in [
        { l: 'Izin pada rentang', v: String(props.ringkas?.total || 0), c: 'text-cam-ink' },
        { l: 'Sedang berlaku', v: String(props.ringkas?.berlaku || 0), c: 'text-emerald-700' },
        { l: 'Menunggu penerbitan', v: String(props.ringkas?.menunggu || 0), c: (props.ringkas?.menunggu || 0) ? 'text-amber-700' : 'text-stone-400' },
        { l: 'Sudah ditutup', v: String(props.ringkas?.ditutup || 0), c: 'text-cam-ink' },
        { l: 'Lewat belum ditutup', v: String(props.ringkas?.belumTutup || 0), c: (props.ringkas?.belumTutup || 0) ? 'text-red-600' : 'text-emerald-600' },
        { l: 'Uji gas basi', v: String(props.ringkas?.gasBasi || 0), c: (props.ringkas?.gasBasi || 0) ? 'text-red-600' : 'text-emerald-600' },
      ]" :key="c.l" class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
        <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">{{ c.l }}</p>
        <p class="mt-2 text-lg font-extrabold" :class="c.c">{{ c.v }}</p>
      </article>
    </section>

    <!-- ═══════════ RINGKASAN ═══════════ -->
    <template v-if="props.mode === 'dashboard'">
      <section class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        <div v-for="i in berlaku" :key="i.id" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <b class="text-[14px]">{{ i.nomor }}</b>
              <p class="text-[11px] text-stone-400">{{ label(i.jenis) }} · {{ i.lokasi }}</p>
            </div>
            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold bg-emerald-100 text-emerald-700">Berlaku</span>
          </div>

          <p class="mt-2 text-[11.5px] text-stone-600">{{ i.uraian }}</p>

          <div class="mt-3 grid grid-cols-2 gap-2 text-[11.5px]">
            <div><p class="text-stone-400">Mulai</p><p class="font-bold">{{ i.mulai }}</p></div>
            <div><p class="text-stone-400">Selesai</p><p class="font-bold">{{ i.selesai }}</p>
              <p class="text-[10.5px]" :class="warnaWaktu[i.waktu?.kelas]">sisa {{ angka(i.waktu?.sisaMenit) }} menit</p></div>
          </div>

          <div v-if="i.perluUjiGas" class="mt-3 rounded-xl px-3 py-2.5"
               :class="i.ujiSegar ? 'bg-emerald-50 border border-emerald-100' : 'bg-red-50 border border-red-100'">
            <p class="text-[10px] uppercase tracking-wide font-bold"
               :class="i.ujiSegar ? 'text-emerald-700' : 'text-red-600'">Uji gas</p>
            <template v-if="i.gas?.length">
              <p class="text-[11.5px] mt-0.5">
                {{ i.gas[0].waktu }} · {{ angka(i.gas[0].usia) }} menit lalu
                <span class="font-bold">{{ i.ujiSegar ? '· masih segar' : `· lewat batas ${i.batasUji} menit` }}</span>
              </p>
              <p class="text-[11px] text-stone-600 mt-1">
                <span v-for="r in i.gas[0].rinci" :key="r.kode" class="mr-2"
                      :class="r.keadaan === 'di-luar-ambang' ? 'text-red-600 font-bold' : r.keadaan === 'tidak-diukur' ? 'text-stone-400' : ''">
                  {{ r.kode.toUpperCase() }} {{ r.nilai === null ? '—' : `${r.nilai}${r.satuan}` }}
                </span>
              </p>
            </template>
            <p v-else class="text-[11.5px] mt-0.5 font-bold text-red-600">Belum ada pengukuran sama sekali.</p>
          </div>

          <div class="mt-3 flex items-center justify-between text-[11.5px]">
            <span class="text-stone-500">{{ (i.periksa || []).length }} butir periksa</span>
            <button type="button" class="font-bold text-cam-orange-dark" @click="tutup(i)">Tutup izin</button>
          </div>
        </div>
        <p v-if="!berlaku.length" class="text-[12px] text-stone-400">Tidak ada izin yang sedang berlaku pada rentang ini.</p>
      </section>
    </template>

    <!-- ═══════════ DAFTAR IZIN ═══════════ -->
    <template v-if="props.mode === 'daftar'">
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="font-bold text-[14px]">Ajukan izin kerja</h3>
        <p class="text-[11.5px] text-stone-500 mt-1">
          Daftar periksa jenis ini disalin ke berkas izin saat disimpan, sehingga izin yang sudah terbit tetap
          terbaca dengan syarat yang berlaku saat itu — bukan dengan syarat yang berlaku setahun kemudian.
        </p>
        <form class="mt-3 grid gap-3 md:grid-cols-4" @submit.prevent="simpanIzin">
          <label class="text-[11px] font-bold text-stone-500">Nomor
            <input v-model="izin.nomor" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Jenis
            <select v-model="izin.jenis" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
              <option v-for="j in props.opsi?.jenis || []" :key="j" :value="j">{{ label(j) }}</option>
            </select></label>
          <label class="md:col-span-2 text-[11px] font-bold text-stone-500">Lokasi
            <input v-model="izin.lokasi" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>

          <label class="md:col-span-4 text-[11px] font-bold text-stone-500">Uraian pekerjaan
            <input v-model="izin.uraian" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>

          <label class="text-[11px] font-bold text-stone-500">Mulai
            <input v-model="izin.mulai" type="datetime-local" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Selesai
            <input v-model="izin.selesai" type="datetime-local" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Pelaksana / kontraktor
            <input v-model="izin.pelaksana" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Jumlah pekerja
            <input v-model="izin.jumlah_pekerja" type="number" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>

          <label class="md:col-span-2 text-[11px] font-bold text-stone-500">Pengawas lapangan
            <input v-model="izin.pengawas_lapangan" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">
            Batas umur uji gas (menit)
            <input v-model="izin.batas_uji_menit" type="number" :disabled="!perluGas"
                   :placeholder="perluGas ? String(props.opsi?.batasUjiBawaan) : 'jenis ini tidak menuntut uji gas'"
                   class="mt-1 w-full rounded-lg border-stone-200 text-[12px] disabled:bg-stone-50"></label>
          <button class="eq-btn-utama self-end" :disabled="izin.processing">Simpan draf</button>
        </form>
        <p v-for="(e, k) in izin.errors" :key="k" class="mt-2 text-[11px] text-red-600">{{ e }}</p>
      </section>

      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-[11.5px]">
            <thead class="bg-stone-50 text-left text-stone-400 uppercase text-[10px] tracking-wide">
              <tr>
                <th class="px-5 py-2.5">Nomor</th><th class="px-5 py-2.5">Jenis</th>
                <th class="px-5 py-2.5">Lokasi</th><th class="px-5 py-2.5">Berlaku</th>
                <th class="px-5 py-2.5">Keadaan</th><th class="px-5 py-2.5">Status</th>
                <th class="px-5 py-2.5 text-right">Alur</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="i in props.izin || []" :key="i.id" class="border-b border-stone-50"
                  :class="i.lewatBelumDitutup ? 'bg-red-50/60' : ''">
                <td class="px-5 py-3 font-semibold">
                  <button type="button" class="underline decoration-dotted" @click="dipilih.id = dipilih.id === i.id ? null : i.id">{{ i.nomor }}</button>
                </td>
                <td class="px-5 py-3 text-stone-500">{{ label(i.jenis) }}</td>
                <td class="px-5 py-3">{{ i.lokasi }}</td>
                <td class="px-5 py-3 text-stone-500">{{ i.mulai }} — {{ i.selesai }}</td>
                <td class="px-5 py-3 font-bold" :class="warnaWaktu[i.waktu?.kelas]">
                  {{ i.ditutup ? 'Ditutup' : i.waktu?.label }}
                </td>
                <td class="px-5 py-3">
                  <span class="rounded-full px-2 py-0.5 text-[10px] font-bold" :class="warnaStatus[i.status]">{{ i.statusLabel }}</span>
                  <p v-if="i.alur?.alasanTolak" class="text-[10.5px] text-red-600 mt-1">{{ i.alur.alasanTolak }}</p>
                </td>
                <td class="px-5 py-3 text-right whitespace-nowrap">
                  <button v-if="i.alur?.dapatDiajukan" type="button" class="text-[11px] font-bold text-cam-orange-dark disabled:opacity-40"
                          :disabled="sibuk[i.id]" @click="alur(tautan.izinAjukan, i)">Ajukan</button>
                  <template v-if="i.alur?.dapatDitinjau">
                    <button type="button" class="ml-3 text-[11px] font-bold text-emerald-700 disabled:opacity-40"
                            :disabled="sibuk[i.id] || !i.siap?.boleh" :title="i.siap?.alasan?.join(' ')"
                            @click="terbitkan(i)">Terbitkan</button>
                    <button type="button" class="ml-3 text-[11px] font-bold text-red-600 disabled:opacity-40"
                            :disabled="sibuk[i.id]" @click="tolak(i)">Tolak</button>
                  </template>
                  <button v-if="i.alur?.dapatDitutup" type="button" class="ml-3 text-[11px] font-bold text-cam-orange-dark"
                          @click="tutup(i)">Tutup</button>
                  <button v-if="isAdmin && i.status !== 'disetujui'" type="button" class="ml-3 text-[11px] font-bold text-stone-400" @click="hapusIzin(i)">Hapus</button>
                </td>
              </tr>
              <tr v-if="!(props.izin || []).length"><td colspan="7" class="px-5 py-8 text-center text-stone-400">Belum ada izin pada rentang ini.</td></tr>
            </tbody>
          </table>
        </div>

        <div v-for="i in (props.izin || []).filter((x: any) => x.id === dipilih.id)" :key="`d-${i.id}`" class="border-t border-stone-100 p-5 bg-stone-50/60 space-y-5">
          <!-- Alasan belum dapat diterbitkan disebut sebelum tombolnya
               ditekan; penolakan yang baru muncul sesudahnya membuat
               orang mencoba jalan lain, bukan melengkapi syaratnya. -->
          <div v-if="i.status !== 'disetujui' && (i.siap?.alasan || []).length"
               class="rounded-xl bg-amber-50 border border-amber-100 px-3 py-2.5">
            <p class="text-[10px] uppercase tracking-wide font-bold text-amber-700">Belum dapat diterbitkan</p>
            <p v-for="(a, n) in i.siap.alasan" :key="n" class="text-[11.5px] text-stone-700 mt-1">{{ a }}</p>
          </div>

          <div>
            <h4 class="font-bold text-[13px]">Daftar periksa — {{ i.nomor }}</h4>
            <p v-if="!(i.periksa || []).length" class="text-[11.5px] text-amber-700 mt-1">
              Jenis ini belum punya daftar periksa; penerbitannya menjadi tanda tangan, bukan pemeriksaan.
            </p>
            <table v-else class="w-full mt-2 text-[11.5px]">
              <tbody>
                <tr v-for="p in i.periksa" :key="p.id" class="border-t border-stone-100">
                  <td class="py-2 w-8">
                    <input type="checkbox" :checked="p.terpenuhi" :disabled="i.status === 'disetujui'"
                           class="rounded border-stone-300" @change="ubahPeriksa(p, ($event.target as HTMLInputElement).checked)">
                  </td>
                  <td class="py-2">{{ p.teks }}
                    <span v-if="p.wajib" class="ml-1 text-[10px] font-bold text-red-600">wajib</span></td>
                  <td class="py-2 text-right text-stone-400">{{ p.terpenuhi ? 'terpenuhi' : '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="i.perluUjiGas">
            <h4 class="font-bold text-[13px]">Uji gas</h4>
            <p class="text-[11.5px] text-stone-500 mt-1">
              Batas umur {{ i.batasUji }} menit. Pengukuran ulang tetap dapat ditambahkan setelah izin terbit —
              justru itu yang diharapkan setiap kali regu keluar-masuk.
            </p>
            <form class="mt-2 grid gap-2 md:grid-cols-7" @submit.prevent="simpanGas(i)">
              <input v-model="gas.waktu_uji" type="datetime-local" class="rounded-lg border-stone-200 text-[11px] md:col-span-2" required>
              <input v-model="gas.o2" type="number" step="0.01" placeholder="O₂ %" class="rounded-lg border-stone-200 text-[11px]">
              <input v-model="gas.lel" type="number" step="0.01" placeholder="LEL %" class="rounded-lg border-stone-200 text-[11px]">
              <input v-model="gas.co" type="number" step="0.01" placeholder="CO ppm" class="rounded-lg border-stone-200 text-[11px]">
              <input v-model="gas.h2s" type="number" step="0.01" placeholder="H₂S ppm" class="rounded-lg border-stone-200 text-[11px]">
              <button class="eq-btn-lain text-[11px]" :disabled="gas.processing">+ Uji</button>
            </form>
            <p v-for="(e, k) in gas.errors" :key="k" class="mt-2 text-[11px] text-red-600">{{ e }}</p>

            <table v-if="(i.gas || []).length" class="w-full mt-3 text-[11.5px]">
              <tbody>
                <tr v-for="g in i.gas" :key="g.id" class="border-t border-stone-100">
                  <td class="py-2">{{ g.waktu }}</td>
                  <td class="py-2 text-stone-400">{{ angka(g.usia) }} mnt</td>
                  <td class="py-2">
                    <span v-for="r in g.rinci" :key="r.kode" class="mr-2"
                          :class="r.keadaan === 'di-luar-ambang' ? 'text-red-600 font-bold' : r.keadaan === 'tidak-diukur' ? 'text-stone-400' : ''">
                      {{ r.kode.toUpperCase() }} {{ r.nilai === null ? '—' : r.nilai }}
                    </span>
                  </td>
                  <td class="py-2 font-bold" :class="g.lulus ? 'text-emerald-700' : 'text-red-600'">{{ g.lulus ? 'Dalam ambang' : 'Di luar ambang' }}</td>
                  <td class="py-2 text-right"><button v-if="isAdmin" type="button" class="text-[11px] text-stone-400" @click="hapusGas(g)">Hapus</button></td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="i.ditutup" class="rounded-xl bg-white border border-stone-100 px-3 py-2.5">
            <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">Penutupan</p>
            <p class="text-[11.5px] mt-0.5">{{ i.ditutupPada }} oleh {{ i.ditutupOleh || '—' }}</p>
            <p class="text-[11.5px] text-stone-600 mt-1">{{ i.catatanPenutupan }}</p>
          </div>
        </div>
      </section>
    </template>

    <!-- ═══════════ DAFTAR PERIKSA ═══════════ -->
    <template v-if="props.mode === 'syarat'">
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="font-bold text-[14px]">Susun daftar periksa</h3>
        <p class="text-[11.5px] text-stone-500 mt-1">
          Disimpan sebagai data karena tiap perusahaan memakai daftarnya sendiri, dan daftar itu bertambah setiap
          kali ada kejadian yang menambah satu baris ke dalamnya. Syarat <b>wajib</b> menghalangi penerbitan
          selama belum terpenuhi; yang tidak wajib hanya dicatat.
        </p>
        <form class="mt-3 grid gap-3 md:grid-cols-5" @submit.prevent="simpanSyarat">
          <label class="text-[11px] font-bold text-stone-500">Jenis
            <select v-model="syarat.jenis" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
              <option v-for="j in props.opsi?.jenis || []" :key="j" :value="j">{{ label(j) }}</option>
            </select></label>
          <label class="md:col-span-2 text-[11px] font-bold text-stone-500">Teks syarat
            <input v-model="syarat.teks" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Urutan
            <input v-model="syarat.urutan" type="number" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="flex items-center gap-2 text-[11px] font-bold text-stone-500 self-end pb-2">
            <input v-model="syarat.wajib" type="checkbox" class="rounded border-stone-300"> Wajib</label>
          <button class="eq-btn-utama md:col-start-5" :disabled="syarat.processing">Tambah</button>
        </form>
        <p v-for="(e, k) in syarat.errors" :key="k" class="mt-2 text-[11px] text-red-600">{{ e }}</p>
      </section>

      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <table class="w-full text-[11.5px]">
          <thead class="bg-stone-50 text-left text-stone-400 uppercase text-[10px] tracking-wide">
            <tr><th class="px-5 py-2.5">Jenis</th><th class="px-5 py-2.5 text-right">Urutan</th>
              <th class="px-5 py-2.5">Syarat</th><th class="px-5 py-2.5">Sifat</th><th class="px-5 py-2.5"></th></tr>
          </thead>
          <tbody>
            <tr v-for="s in props.syarat || []" :key="s.id" class="border-b border-stone-50">
              <td class="px-5 py-3 font-semibold">{{ label(s.jenis) }}</td>
              <td class="px-5 py-3 text-right text-stone-400">{{ s.urutan }}</td>
              <td class="px-5 py-3">{{ s.teks }}</td>
              <td class="px-5 py-3">
                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                      :class="s.wajib ? 'bg-red-100 text-red-700' : 'bg-stone-100 text-stone-600'">{{ s.wajib ? 'Wajib' : 'Catatan' }}</span>
              </td>
              <td class="px-5 py-3 text-right"><button v-if="isAdmin" type="button" class="text-[11px] text-stone-400" @click="hapusSyarat(s)">Hapus</button></td>
            </tr>
            <tr v-if="!(props.syarat || []).length"><td colspan="5" class="px-5 py-8 text-center text-stone-400">Belum ada syarat tersusun.</td></tr>
          </tbody>
        </table>
      </section>
    </template>

    <!-- ═══════════ AMBANG GAS ═══════════ -->
    <template v-if="props.mode === 'ambang'">
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="font-bold text-[14px]">Ambang gas situs</h3>
        <p class="text-[11.5px] text-stone-500 mt-1">
          Menimpa nilai bawaan. Yang mengikat adalah prosedur perusahaan dan ketentuan yang berlaku di
          wilayahnya — nilai bawaan hanya dipakai selama ini belum diisi, dan pemakaiannya dinyatakan
          di peringatan, bukan disembunyikan.
        </p>
        <form class="mt-3 grid gap-3 md:grid-cols-5" @submit.prevent="simpanAmbang">
          <label class="text-[11px] font-bold text-stone-500">Parameter
            <select v-model="ambang.parameter" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
              <option v-for="p in props.opsi?.parameterGas || []" :key="p" :value="p">{{ p.toUpperCase() }}</option>
            </select></label>
          <label class="text-[11px] font-bold text-stone-500">Batas bawah
            <input v-model="ambang.batas_min" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Batas atas
            <input v-model="ambang.batas_maks" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Satuan
            <input v-model="ambang.satuan" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Acuan
            <input v-model="ambang.acuan" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <button class="eq-btn-utama md:col-start-5" :disabled="ambang.processing">Simpan</button>
        </form>
        <p v-for="(e, k) in ambang.errors" :key="k" class="mt-2 text-[11px] text-red-600">{{ e }}</p>
      </section>

      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <table class="w-full text-[11.5px]">
          <thead class="bg-stone-50 text-left text-stone-400 uppercase text-[10px] tracking-wide">
            <tr><th class="px-5 py-2.5">Parameter</th><th class="px-5 py-2.5 text-right">Batas bawah</th>
              <th class="px-5 py-2.5 text-right">Batas atas</th><th class="px-5 py-2.5">Sumber</th>
              <th class="px-5 py-2.5">Acuan</th><th class="px-5 py-2.5"></th></tr>
          </thead>
          <tbody>
            <tr v-for="(a, kode) in props.ambang || {}" :key="kode" class="border-b border-stone-50">
              <td class="px-5 py-3 font-semibold">{{ String(kode).toUpperCase() }}<span class="block text-[10.5px] text-stone-400">{{ a.nama }}</span></td>
              <td class="px-5 py-3 text-right">{{ a.min === null ? '—' : `${a.min} ${a.satuan}` }}</td>
              <td class="px-5 py-3 text-right">{{ a.maks === null ? '—' : `${a.maks} ${a.satuan}` }}</td>
              <td class="px-5 py-3">
                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                      :class="a.ditetapkan ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                  {{ a.ditetapkan ? 'Ditetapkan situs' : 'Bawaan' }}</span>
              </td>
              <td class="px-5 py-3 text-stone-500">{{ a.acuan || '—' }}</td>
              <td class="px-5 py-3 text-right">
                <button v-if="isAdmin && a.ditetapkan" type="button" class="text-[11px] text-stone-400"
                        @click="hapusAmbang((props.daftarAmbang || []).find((x: any) => x.parameter === kode))">Hapus</button>
              </td>
            </tr>
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

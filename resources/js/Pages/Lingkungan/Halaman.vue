<script setup lang="ts">
import { computed, reactive } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';

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
  dashboard: 'Lingkungan & Reklamasi',
  lahan: 'Petak Lahan & Kemajuan Reklamasi',
  pemantauan: 'Pemantauan Mutu Lingkungan',
  baku: 'Baku Mutu',
};

const angka = (v: unknown, d = 0) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: d, minimumFractionDigits: d }).format(Number(v || 0));
const label = (v: string) => String(v || '').replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
const rupiah = (v: unknown) => `Rp ${angka(v)}`;

const tautan = computed(() => props.tautan || {});
const untuk = (pola: string | undefined, id: number | string) => String(pola || '').replace('__ID__', String(id));
const sibuk = reactive<Record<number, boolean>>({});

const area = useForm<any>({
  company_id: '', kode: '', nama: '', jenis: 'bukaan', luas_ha: 0,
  tanggal_buka: '', tanggal_selesai_tambang: '', rencana_selesai_reklamasi: '',
  pohon_rencana: '', catatan: '',
});
const kemajuan = useForm<any>({
  company_id: '', lingkungan_area_id: '', tanggal: new Date().toISOString().slice(0, 10),
  tahap: 'penataan', luas_ha: 0, pohon_ditanam: '', tingkat_tumbuh_persen: '', catatan: '',
});
const parameter = useForm<any>({
  company_id: '', kode: '', nama: '', media: 'air', satuan: '',
  batas_min: '', batas_maks: '', acuan: '', aktif: true,
});
const pantau = useForm<any>({
  company_id: '', lingkungan_parameter_id: '', titik: '',
  tanggal: new Date().toISOString().slice(0, 10), nilai: 0, laboratorium: '', catatan: '',
});
const tindak = useForm<any>({ company_id: '', kode_pemicu: '', judul: '', prioritas: 'sedang', penanggung_jawab: '', target_selesai: '', uraian: '' });

const ditangani = computed(() => new Set(props.kodeDitangani || []));
const tahapOpsi = computed<Record<string, string>>(() => props.opsi?.tahap || {});

/** Petak yang dipilih di formulir kemajuan — menentukan tahapan yang wajar. */
const petakPilihan = computed(() =>
  (props.area || []).find((a: any) => String(a.id) === String(kemajuan.lingkungan_area_id)));

function rentang() {
  router.get(window.location.pathname, { dari: props.dari, sampai: props.sampai }, { preserveState: true, replace: true });
}
function simpanArea() { area.post(tautan.value.areaSimpan, { preserveScroll: true, onSuccess: () => area.reset('kode', 'nama', 'catatan') }); }
function hapusArea(a: any) { if (window.confirm(`Hapus petak ${a.kode}?`)) router.delete(untuk(tautan.value.areaHapus, a.id), { preserveScroll: true }); }
function simpanKemajuan() { kemajuan.post(tautan.value.kemajuanSimpan, { preserveScroll: true, onSuccess: () => kemajuan.reset('pohon_ditanam', 'tingkat_tumbuh_persen', 'catatan') }); }
function hapusKemajuan(k: any) { if (window.confirm(`Hapus kemajuan ${k.tanggalLabel}?`)) router.delete(untuk(tautan.value.kemajuanHapus, k.id), { preserveScroll: true }); }
function simpanParameter() { parameter.post(tautan.value.parameterSimpan, { preserveScroll: true, onSuccess: () => parameter.reset('kode', 'nama', 'satuan', 'batas_min', 'batas_maks', 'acuan') }); }
function hapusParameter(p: any) { if (window.confirm(`Hapus parameter ${p.kode}?`)) router.delete(untuk(tautan.value.parameterHapus, p.id), { preserveScroll: true }); }
function simpanPantau() { pantau.post(tautan.value.pantauSimpan, { preserveScroll: true, onSuccess: () => pantau.reset('nilai', 'catatan') }); }
function hapusPantau(x: any) { if (window.confirm(`Hapus hasil uji ${x.tanggalLabel}?`)) router.delete(untuk(tautan.value.pantauHapus, x.id), { preserveScroll: true }); }

function alur(pola: string, baris: any, muatan: Record<string, any> = {}) {
  sibuk[baris.id] = true;
  router.post(untuk(pola, baris.id), muatan, { preserveScroll: true, onFinish: () => { sibuk[baris.id] = false; } });
}
function tolakDengan(pola: string, baris: any, apa: string) {
  const alasan = window.prompt(`Alasan penolakan ${apa}:`);
  if (alasan === null) return;
  alur(pola, baris, { alasan_tolak: alasan });
}
function setujuiDengan(pola: string, baris: any, apa: string) {
  if (!window.confirm(`Setujui ${apa}? Setelah disetujui tidak dapat diubah.`)) return;
  alur(pola, baris);
}

function tindakDari(a: any) {
  tindak.kode_pemicu = a.kode; tindak.judul = a.judul;
  tindak.prioritas = a.level === 'tinggi' ? 'tinggi' : 'sedang'; tindak.uraian = a.saran || '';
  document.getElementById('form-tindak')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
function simpanTindak() { tindak.post(tautan.value.tindakSimpan, { preserveScroll: true, onSuccess: () => tindak.reset('kode_pemicu', 'judul', 'penanggung_jawab', 'target_selesai', 'uraian') }); }
function ubahTindak(t: any, status: string) { router.put(untuk(tautan.value.tindakUbah, t.id), { status }, { preserveScroll: true }); }

const warnaStatus: Record<string, string> = {
  draf: 'bg-stone-100 text-stone-600', diajukan: 'bg-amber-100 text-amber-700',
  disetujui: 'bg-emerald-100 text-emerald-700', ditolak: 'bg-red-100 text-red-700',
};
</script>

<template>
  <Head :title="judul[props.mode]" />

  <div class="max-w-[1400px] mx-auto space-y-5">
    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-[10px] font-bold uppercase tracking-[.16em] text-emerald-600">Environment · Reclamation</p>
        <h2 class="text-2xl font-extrabold tracking-tight text-stone-800">{{ judul[props.mode] }}</h2>
        <p class="text-[12px] text-stone-500 mt-1">Neraca lahan, tahapan reklamasi berjenjang, dan pemantauan mutu terhadap baku mutu yang berlaku.</p>
      </div>
      <div class="flex gap-2">
        <input v-model="props.dari" type="date" class="rounded-lg border-stone-200 text-[11px]" aria-label="Tanggal mulai">
        <input v-model="props.sampai" type="date" class="rounded-lg border-stone-200 text-[11px]" aria-label="Tanggal akhir">
        <button class="eq-btn-lain" type="button" @click="rentang">Terapkan</button>
      </div>
    </section>

    <section v-if="(props.alerts || []).length" class="grid gap-3 md:grid-cols-2">
      <div v-for="a in props.alerts" :key="a.kode" class="rounded-2xl border p-4"
           :class="a.level === 'tinggi' ? 'border-red-100 bg-red-50' : 'border-amber-100 bg-amber-50'">
        <b class="text-[12px]" :class="a.level === 'tinggi' ? 'text-red-700' : 'text-amber-700'">{{ a.judul }}</b>
        <p class="text-[11px] text-stone-600 mt-1">{{ a.ket }}</p>
        <p v-if="a.saran" class="text-[11px] text-stone-700 mt-2 pt-2 border-t border-black/5"><span class="font-bold">Tindakan: </span>{{ a.saran }}</p>
        <div class="mt-2">
          <span v-if="ditangani.has(a.kode)" class="inline-block rounded-full bg-emerald-100 text-emerald-700 px-2 py-1 text-[10px] font-bold">Sedang ditangani</span>
          <button v-else type="button" class="text-[11px] font-bold text-emerald-700 py-1.5" @click="tindakDari(a)">+ Buat tindak lanjut</button>
        </div>
      </div>
    </section>

    <nav class="flex flex-wrap gap-2">
      <Link v-for="i in [['dashboard','Ringkasan',tautan.dashboard],['lahan','Petak & Reklamasi',tautan.lahan],['pemantauan','Pemantauan',tautan.pemantauan],['baku','Baku Mutu',tautan.baku]]"
            :key="i[0]" :href="i[2] as string" class="rounded-full px-4 py-2 text-[11px] font-bold"
            :class="props.mode === i[0] ? 'bg-cam-ink text-white' : 'bg-white text-stone-500 border border-stone-200'">{{ i[1] }}</Link>
      <a :href="tautan.cetak" class="ml-auto rounded-full px-4 py-2 text-[11px] font-bold bg-white text-emerald-700 border border-emerald-200">Cetak laporan</a>
    </nav>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
      <article v-for="c in [
        { l: 'Lahan terganggu', v: `${angka(props.neraca?.terganggu, 2)} ha`, c: 'text-cam-ink' },
        { l: 'Selesai direklamasi', v: `${angka(props.neraca?.selesai, 2)} ha`, c: 'text-emerald-700' },
        { l: 'Tunggakan', v: `${angka(props.neraca?.tunggakan, 2)} ha`, c: props.neraca?.tunggakan ? 'text-amber-700' : 'text-emerald-700' },
        { l: 'Menganggur telat', v: `${angka(props.neraca?.telat, 2)} ha`, c: props.neraca?.telat ? 'text-red-600' : 'text-emerald-600' },
        { l: 'Nisbah reklamasi', v: props.nisbah === null ? '—' : angka(props.nisbah, 2), c: (props.nisbah ?? 1) < 1 ? 'text-red-600' : 'text-emerald-700' },
      ]" :key="c.l" class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
        <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">{{ c.l }}</p>
        <p class="mt-2 text-xl font-extrabold" :class="c.c">{{ c.v }}</p>
      </article>
    </section>

    <!-- ═══════════ RINGKASAN ═══════════ -->
    <template v-if="props.mode === 'dashboard'">
      <section class="grid gap-5 md:grid-cols-2">
        <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
          <h3 class="font-bold text-[14px]">Neraca lahan</h3>
          <!--
            Lahan yang masih ditambang dipisahkan dari tunggakan. Tanpa
            pemisahan ini, tambang yang sedang berproduksi selalu tampak
            lalai, dan angka yang selalu merah berhenti dibaca.
          -->
          <p class="text-[11.5px] text-stone-500 mt-1">
            Lahan yang masih ditambang belum jatuh tempo reklamasi, jadi tidak dihitung sebagai tunggakan.
          </p>
          <div class="mt-4 space-y-2 text-[12px]">
            <div v-for="b in [
              { l: 'Masih ditambang', v: props.neraca?.aktif, c: 'bg-stone-300' },
              { l: 'Menunggu dimulai', v: props.neraca?.menunggu, c: 'bg-amber-400' },
              { l: 'Reklamasi berjalan', v: props.neraca?.berjalan, c: 'bg-sky-400' },
              { l: 'Selesai', v: props.neraca?.selesai, c: 'bg-emerald-500' },
            ]" :key="b.l" class="flex items-center gap-3">
              <span class="w-3 h-3 rounded-full shrink-0" :class="b.c"></span>
              <span class="flex-1">{{ b.l }}</span>
              <b>{{ angka(b.v, 2) }} ha</b>
            </div>
          </div>
          <div class="mt-4 pt-3 border-t border-stone-100 text-[12px]">
            <div class="flex justify-between"><span class="text-stone-500">Wajib direklamasi</span><b>{{ angka(props.neraca?.wajibReklamasi, 2) }} ha</b></div>
            <div class="flex justify-between mt-1"><span class="text-stone-500">Capaian</span>
              <b>{{ props.neraca?.persenSelesai === null ? '—' : `${angka(props.neraca?.persenSelesai, 1)}%` }}</b></div>
            <div class="flex justify-between mt-1"><span class="text-stone-500">Perkiraan menutup tunggakan</span>
              <b :class="props.proyeksi === null ? 'text-red-600' : ''">
                {{ props.proyeksi === null ? 'tidak akan tertutup' : `${angka(props.proyeksi, 1)} tahun` }}
              </b></div>
          </div>
        </div>

        <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
          <h3 class="font-bold text-[14px]">Jaminan reklamasi</h3>
          <p class="text-[11.5px] text-stone-500 mt-1">
            Dihitung dari luas yang belum direklamasi dikali biaya satuan
            ({{ rupiah(props.opsi?.biayaPerHa) }}/ha). Isikan yang sudah ditempatkan lewat parameter alamat
            <code class="text-[10.5px]">?jaminan=</code> untuk membandingkannya.
          </p>
          <div class="mt-4 space-y-2 text-[12px]">
            <div class="flex justify-between"><span class="text-stone-500">Kebutuhan</span><b>{{ rupiah(props.jaminan?.butuh) }}</b></div>
            <div class="flex justify-between"><span class="text-stone-500">Ditempatkan</span><b>{{ rupiah(props.jaminan?.ditempatkan) }}</b></div>
            <div class="flex justify-between pt-2 border-t border-stone-100">
              <span class="text-stone-500">Selisih</span>
              <b :class="props.jaminan?.cukup ? 'text-emerald-700' : 'text-red-600'">{{ rupiah(props.jaminan?.selisih) }}</b>
            </div>
          </div>
        </div>
      </section>

      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <div class="px-5 py-4 border-b border-stone-100"><h3 class="font-bold text-[14px]">Petak lahan</h3></div>
        <div class="overflow-x-auto">
          <table class="w-full text-[11.5px]">
            <thead class="bg-stone-50 text-left text-stone-400 uppercase text-[10px] tracking-wide">
              <tr>
                <th class="px-5 py-2.5">Kode</th><th class="px-5 py-2.5">Jenis</th>
                <th class="px-5 py-2.5 text-right">Luas</th><th class="px-5 py-2.5">Tahapan</th>
                <th class="px-5 py-2.5 text-right">Menganggur</th><th class="px-5 py-2.5 text-right">Tumbuh</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="a in props.area || []" :key="a.id" class="border-b border-stone-50">
                <td class="px-5 py-3 font-semibold">{{ a.kode }}</td>
                <td class="px-5 py-3 text-stone-500">{{ label(a.jenis) }}</td>
                <td class="px-5 py-3 text-right">{{ angka(a.luas_ha, 2) }} ha</td>
                <td class="px-5 py-3">
                  <span class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                        :class="a.selesai ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-stone-600'">{{ a.tahapLabel }}</span>
                </td>
                <td class="px-5 py-3 text-right">
                  <span v-if="a.masihDitambang" class="text-stone-400">masih ditambang</span>
                  <span v-else-if="a.mengangurHari === null" class="text-stone-300">—</span>
                  <span v-else :class="a.mengangurHari > Number(props.opsi?.tunggakanWajar) ? 'text-red-600 font-bold' : ''">{{ a.mengangurHari }} hari</span>
                </td>
                <td class="px-5 py-3 text-right">
                  <span v-if="a.tingkatTumbuh === null || a.tingkatTumbuh === undefined" class="text-stone-300">—</span>
                  <span v-else :class="a.tingkatTumbuh < Number(props.opsi?.tumbuhMinimum) ? 'text-red-600 font-bold' : 'text-emerald-700'">
                    {{ angka(a.tingkatTumbuh, 1) }}%
                  </span>
                </td>
              </tr>
              <tr v-if="!(props.area || []).length"><td colspan="6" class="px-5 py-8 text-center text-stone-400">Belum ada petak terdaftar.</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>

    <!-- ═══════════ PETAK & KEMAJUAN ═══════════ -->
    <template v-if="props.mode === 'lahan'">
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="font-bold text-[14px]">Daftarkan petak</h3>
        <p class="text-[11.5px] text-stone-500 mt-1">
          <b>Tanggal selesai tambang</b> dikosongkan selama petak masih ditambang — jam tunggakan reklamasi
          baru berjalan setelah tanggal itu diisi.
        </p>
        <form class="mt-3 grid gap-3 md:grid-cols-4" @submit.prevent="simpanArea">
          <label class="text-[11px] font-bold text-stone-500">Kode
            <input v-model="area.kode" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Nama
            <input v-model="area.nama" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Jenis
            <select v-model="area.jenis" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
              <option v-for="j in props.opsi?.jenis || []" :key="j" :value="j">{{ label(j) }}</option>
            </select></label>
          <label class="text-[11px] font-bold text-stone-500">Luas (ha)
            <input v-model="area.luas_ha" type="number" step="0.0001" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>

          <label class="text-[11px] font-bold text-stone-500">Tanggal buka
            <input v-model="area.tanggal_buka" type="date" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Selesai ditambang
            <input v-model="area.tanggal_selesai_tambang" type="date" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Rencana selesai reklamasi
            <input v-model="area.rencana_selesai_reklamasi" type="date" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Pohon rencana
            <input v-model="area.pohon_rencana" type="number" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>

          <label class="md:col-span-3 text-[11px] font-bold text-stone-500">Catatan
            <input v-model="area.catatan" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <button class="eq-btn-utama self-end" :disabled="area.processing">Simpan petak</button>
        </form>
        <p v-for="(e, k) in area.errors" :key="k" class="mt-2 text-[11px] text-red-600">{{ e }}</p>
      </section>

      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="font-bold text-[14px]">Catat kemajuan reklamasi</h3>
        <p class="text-[11.5px] text-stone-500 mt-1">
          Tahapan bertingkat: penataan lahan → tanah pucuk → revegetasi → pemeliharaan.
          Melompatinya ditolak — tanah pucuk di atas lahan yang belum ditata tergerus pada hujan pertama.
          <b>Tahapan petak berpindah setelah kemajuan ini disetujui</b>, bukan saat disimpan.
        </p>
        <form class="mt-3 grid gap-3 md:grid-cols-4" @submit.prevent="simpanKemajuan">
          <label class="text-[11px] font-bold text-stone-500">Petak
            <select v-model="kemajuan.lingkungan_area_id" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required>
              <option value="">— pilih —</option>
              <option v-for="a in props.area || []" :key="a.id" :value="a.id">{{ a.kode }} — {{ a.tahapLabel }}</option>
            </select></label>
          <label class="text-[11px] font-bold text-stone-500">Tahapan dicapai
            <select v-model="kemajuan.tahap" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
              <option v-for="(t, k) in tahapOpsi" :key="k" :value="k">{{ t }}</option>
            </select>
            <span v-if="petakPilihan?.berikutnya" class="block mt-1 font-normal text-[10.5px] text-stone-400">
              Berikutnya yang wajar: {{ tahapOpsi[petakPilihan.berikutnya] }}
            </span>
          </label>
          <label class="text-[11px] font-bold text-stone-500">Tanggal
            <input v-model="kemajuan.tanggal" type="date" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Luas (ha)
            <input v-model="kemajuan.luas_ha" type="number" step="0.0001" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>

          <label class="text-[11px] font-bold text-stone-500">Pohon ditanam
            <input v-model="kemajuan.pohon_ditanam" type="number" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Tingkat tumbuh (%)
            <input v-model="kemajuan.tingkat_tumbuh_persen" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="md:col-span-1 text-[11px] font-bold text-stone-500">Catatan
            <input v-model="kemajuan.catatan" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <button class="eq-btn-utama self-end" :disabled="kemajuan.processing">Simpan draf</button>
        </form>
        <p v-for="(e, k) in kemajuan.errors" :key="k" class="mt-2 text-[11px] text-red-600">{{ e }}</p>
      </section>

      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <div class="px-5 py-4 border-b border-stone-100 flex items-center justify-between">
          <h3 class="font-bold text-[14px]">Kemajuan pada periode ini</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-[11.5px]">
            <thead class="bg-stone-50 text-left text-stone-400 uppercase text-[10px] tracking-wide">
              <tr>
                <th class="px-5 py-2.5">Tanggal</th><th class="px-5 py-2.5">Petak</th>
                <th class="px-5 py-2.5">Tahapan</th><th class="px-5 py-2.5 text-right">Luas</th>
                <th class="px-5 py-2.5 text-right">Tumbuh</th><th class="px-5 py-2.5">Status</th>
                <th class="px-5 py-2.5 text-right">Alur</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="k in props.kemajuan || []" :key="k.id" class="border-b border-stone-50">
                <td class="px-5 py-3 font-semibold">{{ k.tanggalLabel }}</td>
                <td class="px-5 py-3">{{ k.area }}</td>
                <td class="px-5 py-3">{{ k.tahapLabel }}</td>
                <td class="px-5 py-3 text-right">{{ angka(k.luas_ha, 2) }} ha</td>
                <td class="px-5 py-3 text-right">{{ k.tingkat_tumbuh_persen === null ? '—' : `${angka(k.tingkat_tumbuh_persen, 1)}%` }}</td>
                <td class="px-5 py-3">
                  <span class="rounded-full px-2 py-0.5 text-[10px] font-bold" :class="warnaStatus[k.status]">{{ k.statusLabel }}</span>
                  <p v-if="k.alur?.alasanTolak" class="text-[10.5px] text-red-600 mt-1">{{ k.alur.alasanTolak }}</p>
                </td>
                <td class="px-5 py-3 text-right whitespace-nowrap">
                  <button v-if="k.alur?.dapatDiajukan" type="button" class="text-[11px] font-bold text-emerald-700 disabled:opacity-40"
                          :disabled="sibuk[k.id]" @click="alur(tautan.kemajuanAjukan, k)">Ajukan</button>
                  <template v-if="k.alur?.dapatDitinjau">
                    <button type="button" class="ml-3 text-[11px] font-bold text-emerald-700 disabled:opacity-40"
                            :disabled="sibuk[k.id]" @click="setujuiDengan(tautan.kemajuanSetujui, k, `kemajuan ${k.tanggalLabel}`)">Setujui</button>
                    <button type="button" class="ml-3 text-[11px] font-bold text-red-600 disabled:opacity-40"
                            :disabled="sibuk[k.id]" @click="tolakDengan(tautan.kemajuanTolak, k, `kemajuan ${k.tanggalLabel}`)">Tolak</button>
                  </template>
                  <button v-if="isAdmin && k.status !== 'disetujui'" type="button" class="ml-3 text-[11px] font-bold text-stone-400" @click="hapusKemajuan(k)">Hapus</button>
                </td>
              </tr>
              <tr v-if="!(props.kemajuan || []).length"><td colspan="7" class="px-5 py-8 text-center text-stone-400">Belum ada kemajuan pada rentang ini.</td></tr>
            </tbody>
          </table>
        </div>
      </section>

      <section v-if="isAdmin" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="font-bold text-[14px]">Petak terdaftar</h3>
        <table class="w-full mt-3 text-[11.5px]">
          <tbody>
            <tr v-for="a in props.area || []" :key="a.id" class="border-t border-stone-50">
              <td class="py-2 font-semibold">{{ a.kode }}</td>
              <td class="py-2 text-stone-500">{{ a.nama }}</td>
              <td class="py-2 text-right">{{ angka(a.luas_ha, 2) }} ha</td>
              <td class="py-2 text-right"><button type="button" class="text-[11px] font-bold text-stone-400 py-1.5" @click="hapusArea(a)">Hapus</button></td>
            </tr>
          </tbody>
        </table>
      </section>
    </template>

    <!-- ═══════════ PEMANTAUAN ═══════════ -->
    <template v-if="props.mode === 'pemantauan'">
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="font-bold text-[14px]">Catat hasil uji</h3>
        <form class="mt-3 grid gap-3 md:grid-cols-5" @submit.prevent="simpanPantau">
          <label class="text-[11px] font-bold text-stone-500">Parameter
            <select v-model="pantau.lingkungan_parameter_id" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required>
              <option value="">— pilih —</option>
              <option v-for="p in props.parameter || []" :key="p.id" :value="p.id">{{ p.kode }} — {{ p.nama }} ({{ p.rentang }})</option>
            </select></label>
          <label class="text-[11px] font-bold text-stone-500">Titik penaatan
            <input v-model="pantau.titik" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Tanggal
            <input v-model="pantau.tanggal" type="date" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Nilai
            <input v-model="pantau.nilai" type="number" step="0.0001" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Laboratorium
            <input v-model="pantau.laboratorium" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <p class="md:col-span-4 text-[11px] text-stone-500 self-center">
            Pelanggaran dihitung saat dibaca, terhadap ambang yang berlaku — bukan disimpan sebagai status yang membeku ketika baku mutunya berubah.
          </p>
          <button class="eq-btn-utama self-end" :disabled="pantau.processing">Simpan draf</button>
        </form>
        <p v-for="(e, k) in pantau.errors" :key="k" class="mt-2 text-[11px] text-red-600">{{ e }}</p>
      </section>

      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-[11.5px]">
            <thead class="bg-stone-50 text-left text-stone-400 uppercase text-[10px] tracking-wide">
              <tr>
                <th class="px-5 py-2.5">Tanggal</th><th class="px-5 py-2.5">Titik</th>
                <th class="px-5 py-2.5">Parameter</th><th class="px-5 py-2.5 text-right">Nilai</th>
                <th class="px-5 py-2.5">Ambang</th><th class="px-5 py-2.5">Status</th>
                <th class="px-5 py-2.5 text-right">Alur</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="x in props.pantauSemua || []" :key="x.id" class="border-b border-stone-50"
                  :class="x.melanggar ? 'bg-red-50/60' : ''">
                <td class="px-5 py-3 font-semibold">{{ x.tanggalLabel }}</td>
                <td class="px-5 py-3">{{ x.titik }}</td>
                <td class="px-5 py-3 text-stone-500">{{ x.parameter }}</td>
                <td class="px-5 py-3 text-right font-bold" :class="x.melanggar ? 'text-red-600' : ''">
                  {{ angka(x.nilai, 3) }} {{ x.satuan }}
                </td>
                <td class="px-5 py-3 text-stone-500">
                  {{ x.rentang }}
                  <span v-if="x.acuan" class="block text-[10px] text-stone-400">{{ x.acuan }}</span>
                </td>
                <td class="px-5 py-3">
                  <span class="rounded-full px-2 py-0.5 text-[10px] font-bold" :class="warnaStatus[x.status]">{{ x.statusLabel }}</span>
                </td>
                <td class="px-5 py-3 text-right whitespace-nowrap">
                  <button v-if="x.alur?.dapatDiajukan" type="button" class="text-[11px] font-bold text-emerald-700 disabled:opacity-40"
                          :disabled="sibuk[x.id]" @click="alur(tautan.pantauAjukan, x)">Ajukan</button>
                  <template v-if="x.alur?.dapatDitinjau">
                    <button type="button" class="ml-3 text-[11px] font-bold text-emerald-700 disabled:opacity-40"
                            :disabled="sibuk[x.id]" @click="setujuiDengan(tautan.pantauSetujui, x, `hasil uji ${x.tanggalLabel}`)">Setujui</button>
                    <button type="button" class="ml-3 text-[11px] font-bold text-red-600 disabled:opacity-40"
                            :disabled="sibuk[x.id]" @click="tolakDengan(tautan.pantauTolak, x, `hasil uji ${x.tanggalLabel}`)">Tolak</button>
                  </template>
                  <button v-if="isAdmin && x.status !== 'disetujui'" type="button" class="ml-3 text-[11px] font-bold text-stone-400" @click="hapusPantau(x)">Hapus</button>
                </td>
              </tr>
              <tr v-if="!(props.pantauSemua || []).length"><td colspan="7" class="px-5 py-8 text-center text-stone-400">Belum ada hasil uji pada rentang ini.</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>

    <!-- ═══════════ BAKU MUTU ═══════════ -->
    <template v-if="props.mode === 'baku'">
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="font-bold text-[14px]">Tetapkan baku mutu</h3>
        <!--
          Ambangnya data, bukan tetapan di dalam kode: peraturan berganti,
          dan tiap izin dapat menetapkan angka yang lebih ketat daripada
          ketentuan umumnya.
        -->
        <p class="text-[11.5px] text-stone-500 mt-1">
          Angkanya dapat disesuaikan tanpa mengubah aplikasi. Isi <b>Acuan</b> dengan dasar hukumnya —
          nilai itu ikut tercetak pada laporan, sehingga pembacanya tahu ambang mana yang dipakai.
          Cukup satu ambang: pH punya batas atas dan bawah, debu hanya batas atas.
        </p>
        <form class="mt-3 grid gap-3 md:grid-cols-4" @submit.prevent="simpanParameter">
          <label class="text-[11px] font-bold text-stone-500">Kode
            <input v-model="parameter.kode" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Nama
            <input v-model="parameter.nama" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
          <label class="text-[11px] font-bold text-stone-500">Media
            <select v-model="parameter.media" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
              <option v-for="m in props.opsi?.media || []" :key="m" :value="m">{{ label(m) }}</option>
            </select></label>
          <label class="text-[11px] font-bold text-stone-500">Satuan
            <input v-model="parameter.satuan" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>

          <label class="text-[11px] font-bold text-stone-500">Batas minimum
            <input v-model="parameter.batas_min" type="number" step="0.0001" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Batas maksimum
            <input v-model="parameter.batas_maks" type="number" step="0.0001" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Acuan (dasar hukum)
            <input v-model="parameter.acuan" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <button class="eq-btn-utama self-end" :disabled="parameter.processing">Simpan</button>
        </form>
        <p v-for="(e, k) in parameter.errors" :key="k" class="mt-2 text-[11px] text-red-600">{{ e }}</p>
      </section>

      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-[11.5px]">
            <thead class="bg-stone-50 text-left text-stone-400 uppercase text-[10px] tracking-wide">
              <tr>
                <th class="px-5 py-2.5">Kode</th><th class="px-5 py-2.5">Parameter</th>
                <th class="px-5 py-2.5">Media</th><th class="px-5 py-2.5">Ambang</th>
                <th class="px-5 py-2.5">Acuan</th><th class="px-5 py-2.5 text-right"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in props.parameter || []" :key="p.id" class="border-b border-stone-50">
                <td class="px-5 py-3 font-semibold">{{ p.kode }}</td>
                <td class="px-5 py-3">{{ p.nama }}</td>
                <td class="px-5 py-3 text-stone-500">{{ label(p.media) }}</td>
                <td class="px-5 py-3">{{ p.rentang }}</td>
                <td class="px-5 py-3 text-stone-500">{{ p.acuan || '—' }}</td>
                <td class="px-5 py-3 text-right">
                  <button v-if="isAdmin" type="button" class="text-[11px] font-bold text-stone-400 py-1.5" @click="hapusParameter(p)">Hapus</button>
                </td>
              </tr>
              <tr v-if="!(props.parameter || []).length"><td colspan="6" class="px-5 py-8 text-center text-stone-400">Belum ada baku mutu ditetapkan.</td></tr>
            </tbody>
          </table>
        </div>
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
        <thead class="text-left text-stone-400 uppercase text-[10px]">
          <tr><th class="py-1">Judul</th><th class="py-1">PJ</th><th class="py-1">Target</th><th class="py-1">Status</th><th class="py-1 text-right">Ubah</th></tr>
        </thead>
        <tbody>
          <tr v-for="t in props.tindak || []" :key="t.id" class="border-t border-stone-50">
            <td class="py-2">
              <b>{{ t.judul }}</b>
              <span v-if="t.terlambat" class="ml-2 rounded-full bg-red-100 text-red-700 px-2 py-0.5 text-[10px] font-bold">Terlambat</span>
            </td>
            <td class="py-2 text-stone-500">{{ t.penanggung_jawab || '—' }}</td>
            <td class="py-2 text-stone-500">{{ t.target_selesai || '—' }}</td>
            <td class="py-2">{{ label(t.status) }}</td>
            <td class="py-2 text-right">
              <select class="rounded-lg border-stone-200 text-[11px]" :value="t.status"
                      @change="ubahTindak(t, ($event.target as HTMLSelectElement).value)" aria-label="Status tindak lanjut">
                <option v-for="s in props.opsi?.statusTindak || []" :key="s" :value="s">{{ label(s) }}</option>
              </select>
            </td>
          </tr>
          <tr v-if="!(props.tindak || []).length"><td colspan="5" class="py-6 text-center text-stone-400">Belum ada tindak lanjut.</td></tr>
        </tbody>
      </table>
    </section>
  </div>
</template>

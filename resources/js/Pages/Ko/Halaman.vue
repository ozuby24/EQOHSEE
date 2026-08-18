<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import Dasbor from './Dasbor.vue';

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
const titles: Record<string, string> = { dashboard: 'Keselamatan Operasi', register: 'Register Objek KO', form: 'Objek Keselamatan Operasi', rincian: 'Rincian Objek', kelayakan: 'Kelayakan Operasi', perawatan: 'Perawatan dan Pemeliharaan', pengaman: 'Perangkat Pengaman', kajian: 'Kajian Teknis', tenaga: 'Tenaga Teknis', tindak: 'Tindak Lanjut', pengaturan: 'Pengaturan KO' };
const kategori = ['Sarana', 'Prasarana', 'Instalasi', 'Peralatan'];
const kritis = ['Tinggi', 'Sedang', 'Rendah'];
const operasi = ['Aktif', 'Standby', 'Breakdown'];
const interval = [1, 2, 3, 5, 10];
const safeguardStatus = ['Berfungsi', 'Perlu Perbaikan', 'Tidak Berfungsi'];
const kajianStatus = ['Berjalan', 'Dilaporkan', 'Selesai'];
const actionStatus = ['Terbuka', 'Berjalan', 'Selesai', 'Dibatalkan'];
const objek = computed(() => props.objek ?? []);
const list = computed(() => props.aksi ?? props.kajian ?? props.tenaga ?? objek.value);
const angka = (v: unknown) => typeof v === 'number' ? v.toLocaleString('id-ID', { maximumFractionDigits: 2 }) : (v ?? '—');

/*
  Kartu ringkasan disebut satu per satu, bukan diulang dari kunci
  props.c.

  Versi sebelumnya `v-for="(value, key) in props.c"` dengan label
  `String(key).replaceAll('_',' ')`. Itu memperlakukan bentuk internal
  array hitungan sebagai bahasa yang dibaca orang: kunci `pgTot` tampil
  sebagai "PGTOT", dan `byStat` — yang isinya objek, bukan angka —
  tercetak utuh sebagai JSON di tengah kartu.

  Cacatnya lama, tetapi baru terlihat setelah halaman ini berhenti
  merender kosong. Selama halamannya kosong tidak ada yang dapat
  melihatnya, dan tidak ada uji yang menangkapnya: propnya memang
  terkirim benar, yang salah cara menggambarnya.

  Disebut satu per satu supaya kunci hitungan yang ditambahkan kemudian
  tidak otomatis muncul sebagai kartu tanpa nama — yang muncul hanyalah
  yang memang diputuskan untuk ditampilkan.
*/
const ringkas = computed(() => {
  const c = props.c ?? {};
  const layak = c.byStat?.['Layak'] ?? 0;

  return [
    { label: 'Objek terdaftar', nilai: angka(c.total ?? 0), ket: 'sarana, prasarana, instalasi, peralatan' },
    { label: 'Bersertifikat layak', nilai: angka(layak), ket: `dari ${angka(c.total ?? 0)} objek` },
    { label: 'PM terlewat', nilai: angka(c.overdue ?? 0), ket: 'perawatan melewati jadwalnya' },
    /* `?? 0` diganti pemeriksaan null: register yang masih kosong
       dahulu menampilkan "0%", terbaca sebagai kepatuhan buruk padahal
       tidak ada satu pun objek untuk dinilai. */
    { label: 'Kepatuhan PM',
      nilai: c.pmc === null || c.pmc === undefined ? '—' : `${angka(c.pmc)}%`,
      ket: c.pmc === null || c.pmc === undefined
        ? 'belum ada objek terdaftar untuk dinilai'
        : 'objek yang perawatannya sesuai jadwal' },
  ];
});

/** Sebaran status kelayakan — nama statusnya memang sudah bahasa manusia. */
const sebaranStatus = computed(() =>
  Object.entries((props.c?.byStat ?? {}) as Record<string, number>)
    .map(([label, jumlah]) => ({ label, jumlah })),
);
const tanggal = (v: unknown) => v ? String(v).slice(0, 10) : '';
const filter = reactive({ q: props.q ?? '', kat: props.kat ?? '', ops: props.ops ?? '', st: props.st ?? '' });
const selectedObject = ref(props.objek?.[0]?.id ?? props.o?.id ?? '');
const objectForm = useForm<Record<string, any>>({ kode: props.o?.kode ?? '', nama: props.o?.nama ?? '', kategori: props.o?.kategori ?? '', jenis: props.o?.jenis ?? '', merk: props.o?.merk ?? '', serial_number: props.o?.serial_number ?? '', lokasi: props.o?.lokasi ?? '', company_id: props.o?.company_id ?? '', kritikalitas: props.o?.kritikalitas ?? 'Sedang', status_operasi: props.o?.status_operasi ?? 'Aktif', tgl_sertifikasi: tanggal(props.o?.tgl_sertifikasi), interval_tahun: props.o?.interval_tahun ?? 3, no_sertifikat: props.o?.no_sertifikat ?? '', lembaga_uji: props.o?.lembaga_uji ?? '', lapor_kait: props.o?.lapor_kait ?? false, pm_jenis: props.o?.pm_jenis ?? '', pm_terakhir: tanggal(props.o?.pm_terakhir), pm_berikutnya: tanggal(props.o?.pm_berikutnya), keterangan: props.o?.keterangan ?? '' });
const pmForm = useForm<Record<string, any>>({ tanggal: new Date().toISOString().slice(0, 10), berikutnya: '', hasil: 'Baik', catatan: '', ko_personnel_id: '' });
const safeguardForm = useForm<Record<string, any>>({ id: '', nama: '', spesifikasi: '', status: 'Berfungsi', tgl_periksa: '', catatan: '' });
const reviewForm = useForm<Record<string, any>>({ id: '', ko_object_id: '', judul: '', pemicu: '', tanggal: '', ko_personnel_id: '', status: 'Berjalan', tgl_lapor: '', ringkasan: '' });
const personnelForm = useForm<Record<string, any>>({ id: '', nama: '', jabatan: '', company_id: '', sertifikasi: '', no_sertifikat: '', tgl_kadaluarsa: '', user_id: '' });
const actionForm = useForm<Record<string, any>>({ id: '', ko_object_id: '', sumber: 'Manual', uraian: '', prioritas: 'Sedang', pic_user_id: '', pic_nama: '', target_tgl: '', status: 'Terbuka', tgl_selesai: '', tindakan: '' });
const settingsForm = useForm<Record<string, any>>({ ...(props.set ?? {}) });

function cari() { router.get(window.location.pathname, filter, { preserveState: true, preserveScroll: true }); }
function simpanObjek() { props.o?.id ? objectForm.put(`/ko/objek/${props.o.id}`, { preserveScroll: true }) : objectForm.post('/ko/objek', { preserveScroll: true }); }
function hapusObjek() { if (props.o?.id && confirm(`Hapus ${props.o.kode}?`)) router.delete(`/ko/objek/${props.o.id}`); }
function simpanPm() { pmForm.post(`/ko/perawatan/${selectedObject.value}`, { preserveScroll: true }); }
function simpanPengaman() { safeguardForm.post(`/ko/pengaman/${props.o?.id ?? selectedObject.value}`, { preserveScroll: true, onSuccess: () => safeguardForm.reset('id', 'nama', 'spesifikasi', 'tgl_periksa', 'catatan') }); }
function simpanKajian() { reviewForm.post('/ko/kajian', { preserveScroll: true }); }
function simpanTenaga() { personnelForm.post('/ko/tenaga', { preserveScroll: true }); }
function simpanTindak() { actionForm.post('/ko/tindak', { preserveScroll: true }); }
function simpanPengaturan() { settingsForm.post('/ko/pengaturan', { preserveScroll: true }); }
function tarikPeringatan() { router.post('/ko/tindak/tarik', {}, { preserveScroll: true }); }
function hapus(path: string) { if (confirm('Hapus data ini?')) router.delete(path, { preserveScroll: true }); }
</script>

<template>
  <Head :title="titles[props.mode] ?? 'KO'" />
  <div class="max-w-[1400px] mx-auto space-y-5">
    <section class="flex flex-wrap items-end justify-between gap-4"><div><h2 class="text-xl font-bold text-cam-ink">{{ titles[props.mode] ?? 'Keselamatan Operasi' }}</h2><p class="text-[12.5px] text-stone-500 mt-1">Register objek, kelayakan, perawatan, pengaman, dan tindakan keselamatan operasi.</p></div><div class="flex gap-2"><Link v-if="props.mode === 'register' && props.bolehUbah" href="/ko/objek/baru" class="eq-btn-utama">Tambah Objek</Link><button v-if="props.mode === 'tindak' && props.bolehUbah" type="button" class="eq-btn-lain" @click="tarikPeringatan">Tarik peringatan</button></div></section>
    <nav class="flex gap-1 overflow-x-auto rounded-xl bg-stone-100 p-1 text-[11px]"><Link v-for="tab in [['dashboard','Dashboard','/ko'],['register','Register','/ko/register'],['kelayakan','Kelayakan','/ko/kelayakan'],['perawatan','Perawatan','/ko/perawatan'],['pengaman','Pengaman','/ko/pengaman'],['kajian','Kajian','/ko/kajian'],['tenaga','Tenaga','/ko/tenaga'],['tindak','Tindak lanjut','/ko/tindak'],['pengaturan','Pengaturan','/ko/pengaturan']]" :key="tab[0]" :href="tab[2]" class="whitespace-nowrap rounded-lg px-3 py-2 text-stone-500 hover:bg-white" :class="props.mode === tab[0] ? 'bg-white font-bold text-cam-ink shadow-sm' : ''">{{ tab[1] }}</Link></nav>

    <Dasbor v-if="props.mode === 'dashboard'"
            :c="props.c ?? {}" :sub="props.sub ?? {}" :peringatan="props.peringatan ?? []"
            :objek="objek" :aksi-terbuka="props.aksiTerbuka ?? 0" :set="props.set ?? {}" />

    <!-- Selisih antara tanggal sertifikasi unit dan uji terakhirnya.
         Ditampilkan sebagai temuan, bukan dibetulkan diam-diam:
         pembetulan otomatis menutupi sebabnya, dan sebabnya yang penting
         — kolomnya disunting tangan tanpa uji, atau ada uji disetujui
         yang gagal memperbarui unitnya. -->
    <section v-if="props.mode === 'kelayakan' && (props.berselisih ?? []).length"
             class="rounded-2xl bg-white border border-amber-200 shadow-card p-5">
      <h3 class="text-[14px] font-bold text-cam-ink">Sertifikasi tidak cocok dengan uji terakhir</h3>
      <p class="text-[11.5px] text-stone-500 mt-0.5 mb-3">
        Tanggal pada unit berbeda dari uji kelayakan terakhir yang disetujui.
        Salah satunya disunting tanpa dasar uji.
      </p>
      <ul class="divide-y divide-stone-100">
        <li v-for="b in props.berselisih" :key="b.id" class="py-2 flex flex-wrap items-center gap-3 text-[12px]">
          <Link :href="`/ko/objek/${b.id}`" class="font-semibold text-cam-lime-deep w-44 truncate">
            {{ b.kode }} · {{ b.nama }}
          </Link>
          <span class="text-stone-500">unit: {{ b.unit || 'tanpa tanggal' }}</span>
          <span class="text-stone-500">uji terakhir: {{ b.uji || 'tanpa tanggal' }}</span>
        </li>
      </ul>
    </section>

    <section v-if="['register','kelayakan','perawatan','pengaman'].includes(props.mode)" class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-x-auto"><form v-if="props.mode === 'register'" class="p-4 grid gap-2 md:grid-cols-5 border-b border-stone-100" @submit.prevent="cari"><input v-model="filter.q" placeholder="Cari kode, nama, lokasi…" class="rounded-lg border-stone-200 text-[12px] md:col-span-2"><select v-model="filter.kat" class="rounded-lg border-stone-200 text-[12px]" aria-label="Kategori"><option value="">Semua kategori</option><option v-for="item in kategori" :key="item">{{ item }}</option></select><select v-model="filter.ops" class="rounded-lg border-stone-200 text-[12px]" aria-label="Operasi"><option value="">Semua operasi</option><option v-for="item in operasi" :key="item">{{ item }}</option></select><button class="eq-btn-utama">Filter</button></form><table class="min-w-full text-left text-[12px]"><thead><tr class="text-stone-400 border-b border-stone-100"><th class="px-5 py-3">Kode</th><th class="px-5 py-3">Nama</th><th class="px-5 py-3">Kategori</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">PM berikutnya</th><th class="px-5 py-3"></th></tr></thead><tbody><tr v-for="item in objek" :key="item.id" class="border-b border-stone-50"><td class="px-5 py-3 font-semibold"><Link :href="`/ko/objek/${item.id}`" class="text-cam-lime-deep">{{ item.kode }}</Link></td><td class="px-5 py-3">{{ item.nama }}</td><td class="px-5 py-3">{{ item.kategori }}</td><td class="px-5 py-3">{{ item.status_ko ?? item.status_operasi }}</td><td class="px-5 py-3">{{ tanggal(item.pm_berikutnya) || '—' }}</td><td class="px-5 py-3 text-right"><Link v-if="props.bolehUbah" :href="`/ko/objek/${item.id}/edit`" class="text-cam-lime-deep mr-2">Ubah</Link></td></tr></tbody></table><div v-if="!objek.length" class="p-8 text-center text-[13px] text-stone-500">Belum ada objek dalam lingkup Anda.</div></section>

    <section v-if="props.mode === 'form'" class="rounded-2xl bg-white border border-stone-100 shadow-card p-6"><form class="grid gap-4 md:grid-cols-3" @submit.prevent="simpanObjek"><label v-for="field in [['kode','Kode'],['nama','Nama objek'],['jenis','Jenis'],['merk','Merek'],['serial_number','Nomor seri'],['lokasi','Lokasi'],['no_sertifikat','No. sertifikat'],['lembaga_uji','Lembaga uji'],['pm_jenis','Jenis PM']]" :key="field[0]" class="text-[12px] font-semibold">{{ field[1] }}<input v-model="objectForm[field[0]]" class="mt-1 w-full rounded-xl border-stone-200 text-[12px]"></label><label class="text-[12px] font-semibold">Kategori<select v-model="objectForm.kategori" required class="mt-1 w-full rounded-xl border-stone-200 text-[12px]"><option value="" disabled>Pilih kategori</option><option v-for="item in kategori" :key="item">{{ item }}</option></select></label><label class="text-[12px] font-semibold">Kritikalitas<select v-model="objectForm.kritikalitas" class="mt-1 w-full rounded-xl border-stone-200 text-[12px]"><option v-for="item in kritis" :key="item">{{ item }}</option></select></label><label class="text-[12px] font-semibold">Status operasi<select v-model="objectForm.status_operasi" class="mt-1 w-full rounded-xl border-stone-200 text-[12px]"><option v-for="item in operasi" :key="item">{{ item }}</option></select></label><label class="text-[12px] font-semibold">Tanggal sertifikasi<input v-model="objectForm.tgl_sertifikasi" type="date" class="mt-1 w-full rounded-xl border-stone-200 text-[12px]"></label><label class="text-[12px] font-semibold">Interval tahun<select v-model="objectForm.interval_tahun" class="mt-1 w-full rounded-xl border-stone-200 text-[12px]"><option v-for="item in interval" :key="item" :value="item">{{ item }} tahun</option></select></label><label class="text-[12px] font-semibold">PM terakhir<input v-model="objectForm.pm_terakhir" type="date" class="mt-1 w-full rounded-xl border-stone-200 text-[12px]"></label><label class="text-[12px] font-semibold">PM berikutnya<input v-model="objectForm.pm_berikutnya" type="date" class="mt-1 w-full rounded-xl border-stone-200 text-[12px]"></label><label class="flex items-center gap-2 text-[12px] mt-6"><input v-model="objectForm.lapor_kait" type="checkbox"> Lapor ke KaIT</label><label class="text-[12px] font-semibold md:col-span-3">Keterangan<textarea v-model="objectForm.keterangan" rows="3" class="mt-1 w-full rounded-xl border-stone-200 text-[12px]"></textarea></label><div class="md:col-span-3 flex gap-2"><button class="eq-btn-utama" :disabled="objectForm.processing">Simpan objek</button><button v-if="props.o?.id" type="button" class="rounded-xl border border-red-200 px-4 text-red-600 text-[12px]" @click="hapusObjek">Hapus objek</button></div></form></section>

    <section v-if="props.mode === 'rincian'" class="space-y-4"><div class="rounded-2xl bg-white border border-stone-100 shadow-card p-6"><div class="flex flex-wrap justify-between gap-3"><div><h3 class="text-lg font-bold">{{ props.o?.kode }} — {{ props.o?.nama }}</h3><p class="text-[12.5px] text-stone-500 mt-1">{{ props.o?.kategori }} · {{ props.o?.lokasi ?? 'Lokasi belum diisi' }}</p></div><Link v-if="props.bolehUbah" :href="`/ko/objek/${props.o?.id}/edit`" class="eq-btn-lain">Ubah objek</Link></div><div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-5 mt-5"><div v-for="key in ['status_ko','kritikalitas','tgl_sertifikasi','pm_berikutnya','no_sertifikat']" :key="key" class="rounded-xl bg-stone-50 p-3"><small class="block text-[10px] uppercase font-bold text-stone-400">{{ key.replaceAll('_',' ') }}</small><b>{{ key.includes('tgl') || key.includes('pm_') ? (tanggal(props.o?.[key]) || '—') : (props.o?.[key] ?? '—') }}</b></div></div></div><div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5"><div class="flex justify-between"><h3 class="font-bold text-[14px]">Perangkat pengaman</h3></div><div v-for="item in props.o?.safeguards ?? []" :key="item.id" class="flex justify-between border-b border-stone-100 py-3 text-[12px]"><span><b>{{ item.nama }}</b> · {{ item.spesifikasi ?? '—' }} · {{ item.status }}</span><button v-if="props.bolehUbah" type="button" class="text-red-600" @click="hapus(`/ko/pengaman/${props.o.id}/${item.id}`)">Hapus</button></div><form v-if="props.bolehUbah" class="grid gap-2 md:grid-cols-5 mt-4" @submit.prevent="simpanPengaman"><input v-model="safeguardForm.nama" required placeholder="Nama pengaman" class="rounded-lg border-stone-200 text-[12px]"><input v-model="safeguardForm.spesifikasi" placeholder="Spesifikasi" class="rounded-lg border-stone-200 text-[12px]"><select v-model="safeguardForm.status" class="rounded-lg border-stone-200 text-[12px]" aria-label="Status"><option v-for="item in safeguardStatus" :key="item">{{ item }}</option></select><input v-model="safeguardForm.tgl_periksa" type="date" class="rounded-lg border-stone-200 text-[12px]" aria-label="Tanggal periksa"><button class="eq-btn-utama">Simpan pengaman</button></form></div></section>

    <section v-if="props.mode === 'perawatan'" class="space-y-4"><div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5"><h3 class="font-bold text-[14px]">Catat perawatan preventif</h3><form class="grid gap-3 md:grid-cols-5 mt-4" @submit.prevent="simpanPm"><select v-model="selectedObject" class="rounded-lg border-stone-200 text-[12px]" aria-label="Objek"><option v-for="item in objek" :key="item.id" :value="item.id">{{ item.kode }} · {{ item.nama }}</option></select><input v-model="pmForm.tanggal" type="date" class="rounded-lg border-stone-200 text-[12px]" aria-label="Tanggal"><input v-model="pmForm.berikutnya" type="date" class="rounded-lg border-stone-200 text-[12px]" aria-label="Tanggal berikutnya"><input v-model="pmForm.catatan" placeholder="Catatan" class="rounded-lg border-stone-200 text-[12px]"><button class="eq-btn-utama">Catat PM</button></form></div><p class="text-[12px] text-stone-500">Objek di bawah diurutkan dari jadwal perawatan yang paling mendesak.</p></section>

    <section v-if="props.mode === 'pengaman'" class="space-y-4"><div v-if="props.bolehUbah" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5"><h3 class="font-bold text-[14px]">Tambah perangkat pengaman</h3><form class="grid gap-2 md:grid-cols-5 mt-4" @submit.prevent="simpanPengaman"><select v-model="selectedObject" required class="rounded-lg border-stone-200 text-[12px]" aria-label="Objek"><option value="">Pilih objek</option><option v-for="item in objek" :key="item.id" :value="item.id">{{ item.kode }} · {{ item.nama }}</option></select><input v-model="safeguardForm.nama" required placeholder="Nama pengaman" class="rounded-lg border-stone-200 text-[12px]"><input v-model="safeguardForm.spesifikasi" placeholder="Spesifikasi" class="rounded-lg border-stone-200 text-[12px]"><select v-model="safeguardForm.status" class="rounded-lg border-stone-200 text-[12px]" aria-label="Status"><option v-for="item in safeguardStatus" :key="item">{{ item }}</option></select><button class="eq-btn-utama">Simpan</button></form></div><div v-for="object in objek" :key="object.id" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5"><div class="flex justify-between"><h3 class="font-bold text-[14px]">{{ object.kode }} · {{ object.nama }}</h3><span class="text-[11px] text-stone-400">{{ object.safeguards?.length ?? 0 }} pengaman</span></div><div v-for="item in object.safeguards ?? []" :key="item.id" class="flex justify-between border-b border-stone-100 py-2 text-[12px]"><span>{{ item.nama }} · {{ item.status }}</span><button v-if="props.bolehUbah" type="button" class="text-red-600" @click="hapus(`/ko/pengaman/${object.id}/${item.id}`)">Hapus</button></div></div></section>

    <section v-if="props.mode === 'kajian'" class="space-y-4"><form v-if="props.bolehUbah" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 grid gap-3 md:grid-cols-4" @submit.prevent="simpanKajian"><select v-model="reviewForm.ko_object_id" required class="rounded-lg border-stone-200 text-[12px]" aria-label="Objek KO"><option value="">Pilih objek</option><option v-for="item in objek" :key="item.id" :value="item.id">{{ item.kode }} · {{ item.nama }}</option></select><input v-model="reviewForm.judul" required placeholder="Judul kajian" class="rounded-lg border-stone-200 text-[12px]"><select v-model="reviewForm.status" class="rounded-lg border-stone-200 text-[12px]" aria-label="Status"><option v-for="item in kajianStatus" :key="item">{{ item }}</option></select><button class="eq-btn-utama">Simpan kajian</button><textarea v-model="reviewForm.ringkasan" placeholder="Ringkasan" class="rounded-lg border-stone-200 text-[12px] md:col-span-3"></textarea></form><div class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-x-auto"><table class="min-w-full text-left text-[12px]"><thead><tr class="text-stone-400 border-b border-stone-100"><th class="px-5 py-3">Judul</th><th class="px-5 py-3">Objek</th><th class="px-5 py-3">Status</th><th class="px-5 py-3"></th></tr></thead><tbody><tr v-for="item in props.kajian ?? []" :key="item.id" class="border-b border-stone-50"><td class="px-5 py-3 font-semibold">{{ item.judul }}</td><td class="px-5 py-3">{{ item.object?.kode ?? '—' }}</td><td class="px-5 py-3">{{ item.status }}</td><td class="px-5 py-3 text-right"><button v-if="props.bolehUbah" type="button" class="text-red-600" @click="hapus(`/ko/kajian/${item.id}`)">Hapus</button></td></tr></tbody></table></div></section>

    <section v-if="props.mode === 'tenaga'" class="space-y-4"><form v-if="props.bolehUbah" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 grid gap-3 md:grid-cols-4" @submit.prevent="simpanTenaga"><input v-model="personnelForm.nama" required placeholder="Nama tenaga teknis" class="rounded-lg border-stone-200 text-[12px]"><input v-model="personnelForm.jabatan" placeholder="Jabatan" class="rounded-lg border-stone-200 text-[12px]"><input v-model="personnelForm.sertifikasi" placeholder="Sertifikasi" class="rounded-lg border-stone-200 text-[12px]"><input v-model="personnelForm.no_sertifikat" placeholder="No. sertifikat" class="rounded-lg border-stone-200 text-[12px]"><input v-model="personnelForm.tgl_kadaluarsa" type="date" class="rounded-lg border-stone-200 text-[12px]" aria-label="Tanggal kedaluwarsa"><select v-model="personnelForm.company_id" class="rounded-lg border-stone-200 text-[12px]" aria-label="Perusahaan"><option value="">Perusahaan aktif</option><option v-for="company in props.perusahaan ?? []" :key="company.id" :value="company.id">{{ company.name }}</option></select><button class="eq-btn-utama">Simpan tenaga</button></form><div class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-x-auto"><table class="min-w-full text-left text-[12px]"><thead><tr class="text-stone-400 border-b border-stone-100"><th class="px-5 py-3">Nama</th><th class="px-5 py-3">Jabatan</th><th class="px-5 py-3">Sertifikasi</th><th class="px-5 py-3">Kadaluarsa</th><th></th></tr></thead><tbody><tr v-for="item in props.tenaga ?? []" :key="item.id" class="border-b border-stone-50"><td class="px-5 py-3 font-semibold">{{ item.nama }}</td><td class="px-5 py-3">{{ item.jabatan ?? '—' }}</td><td class="px-5 py-3">{{ item.sertifikasi ?? '—' }}</td><td class="px-5 py-3">{{ tanggal(item.tgl_kadaluarsa) || '—' }}</td><td class="px-5 py-3 text-right"><button v-if="props.bolehUbah && item.id" type="button" class="text-red-600" @click="hapus(`/ko/tenaga/${item.id}`)">Hapus</button></td></tr></tbody></table></div></section>

    <section v-if="props.mode === 'tindak'" class="space-y-4"><form v-if="props.bolehUbah" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 grid gap-3 md:grid-cols-4" @submit.prevent="simpanTindak"><select v-model="actionForm.ko_object_id" required class="rounded-lg border-stone-200 text-[12px]" aria-label="Objek KO"><option value="">Pilih objek</option><option v-for="item in objek" :key="item.id" :value="item.id">{{ item.kode }} · {{ item.nama }}</option></select><input v-model="actionForm.uraian" required placeholder="Uraian tindak lanjut" class="rounded-lg border-stone-200 text-[12px] md:col-span-2"><select v-model="actionForm.prioritas" class="rounded-lg border-stone-200 text-[12px]" aria-label="Prioritas"><option>Tinggi</option><option>Sedang</option><option>Rendah</option></select><select v-model="actionForm.status" class="rounded-lg border-stone-200 text-[12px]" aria-label="Status"><option v-for="item in actionStatus" :key="item">{{ item }}</option></select><input v-model="actionForm.target_tgl" type="date" class="rounded-lg border-stone-200 text-[12px]" aria-label="Tenggat"><textarea v-model="actionForm.tindakan" placeholder="Tindakan" class="rounded-lg border-stone-200 text-[12px] md:col-span-2"></textarea><button class="eq-btn-utama">Simpan tindak lanjut</button></form><div class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-x-auto"><table class="min-w-full text-left text-[12px]"><thead><tr class="text-stone-400 border-b border-stone-100"><th class="px-5 py-3">Uraian</th><th class="px-5 py-3">Prioritas</th><th class="px-5 py-3">Target</th><th class="px-5 py-3">Status</th><th></th></tr></thead><tbody><tr v-for="item in props.aksi ?? []" :key="item.id" class="border-b border-stone-50"><td class="px-5 py-3">{{ item.uraian }}</td><td class="px-5 py-3">{{ item.prioritas }}</td><td class="px-5 py-3">{{ tanggal(item.target_tgl) || '—' }}</td><td class="px-5 py-3">{{ item.status }}</td><td class="px-5 py-3 text-right"><button v-if="props.bolehUbah && item.id" type="button" class="text-red-600" @click="hapus(`/ko/tindak/${item.id}`)">Hapus</button></td></tr></tbody></table></div></section>

    <section v-if="props.mode === 'pengaturan'" class="rounded-2xl bg-white border border-stone-100 shadow-card p-6 max-w-3xl"><form class="grid gap-4 sm:grid-cols-2" @submit.prevent="simpanPengaturan"><label v-for="key in ['ko_warn_days','ko_target_layak','ko_target_pmc','ko_iv_peralatan','ko_iv_instalasi']" :key="key" class="text-[12px] font-semibold">{{ key.replaceAll('_',' ') }}<input v-model="settingsForm[key]" type="number" class="mt-1 w-full rounded-xl border-stone-200 text-[12px]"></label><button class="eq-btn-utama">Simpan pengaturan</button></form></section>
  </div>
</template>

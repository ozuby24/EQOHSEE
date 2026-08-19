<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
const { dialog, tanya, batal, lanjut } = useDialog();


/*
  Prop halaman diambil lewat propHalaman(), bukan defineProps.

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

  propHalaman() mengambil prop halaman apa adanya — termasuk yang
  dibagikan middleware — sehingga tidak ada daftar nama yang harus
  dirawat sejajar dengan controller-nya, dan tidak ada nama yang dapat
  hilang diam-diam. Dibacanya hidup: lihat resources/js/halaman.ts.
*/
const props = propHalaman();
const audit = computed(() => props.audit ?? {});
const audits = computed(() => props.audits?.data ?? []);
const title: Record<string, string> = { index: 'Audit SMKP', form: 'Periode Audit SMKP', show: 'Ringkasan Audit SMKP', acuan: 'Acuan Kriteria SMKP', tahap1: 'Tahap I — Permulaan Audit', rencana: 'Rencana Audit', rapat: 'Rapat Audit', nilai: 'Penilaian Elemen SMKP', temuan: 'Temuan dan Tindakan Perbaikan' };
const angka = (v: unknown) => typeof v === 'number' ? v.toLocaleString('id-ID', { maximumFractionDigits: 2 }) : (v ?? '—');

/*
  Kartu ringkasan disebut satu per satu, bukan diulang dari kunci
  props.rekap.

  Rekap memuat `elemen` — array bersarang per kode elemen — dan
  `bobotTerpakai`, keduanya bentuk internal hitungan. Diulang apa
  adanya, yang pertama tercetak sebagai JSON sepanjang halaman dan yang
  kedua muncul sebagai label "BOBOTTERPAKAI".

  Sama persis dengan cacat di halaman Keselamatan Operasi, dan
  ditemukan karena mencari pola yang sama sesudahnya.
*/
const ringkas = computed(() => {
  const r = props.rekap ?? {};

  return [
    { label: 'Skor akhir', nilai: `${angka(r.skor ?? 0)}%`, ket: r.tingkat?.label ?? 'belum dinilai' },
    { label: 'Butir dinilai', nilai: angka(r.dinilai ?? 0), ket: `dari ${angka(r.berlaku ?? 0)} butir berlaku` },
    { label: 'Nilai terkumpul', nilai: angka(r.nilai ?? 0), ket: `dari maksimum ${angka(r.maks ?? 0)}` },
    { label: 'Butir keseluruhan', nilai: angka(r.total ?? 0), ket: 'termasuk yang tidak berlaku' },
  ];
});
const tanggal = (v: unknown) => v ? String(v).slice(0, 10) : '';
const form = useForm<Record<string, any>>({ tahun: audit.value.tahun ?? new Date().getFullYear(), judul: audit.value.judul ?? '', company_id: audit.value.company_id ?? '', status: audit.value.status ?? 'draft', tanggal_mulai: tanggal(audit.value.tanggal_mulai), tanggal_selesai: tanggal(audit.value.tanggal_selesai), ketua_auditor: audit.value.ketua_auditor ?? '' });
/* `faktor` dan `pengurang` disiapkan sebagai objek walau audit lama belum
   punya keduanya. Tanpa itu v-model menulis ke properti dari undefined dan
   seluruh formulir Tahap I berhenti merender pada audit yang dibuat sebelum
   kedua daftar ini ada. */
const tahapForm = useForm<Record<string, any>>({ permulaan: { faktor: {}, pengurang: {}, ...(audit.value.permulaan ?? {}) }, kinerja: { ...(audit.value.kinerja ?? {}) }, kecukupan: { ...(audit.value.kecukupan ?? {}) } });
tahapForm.permulaan.faktor    ??= {};
tahapForm.permulaan.pengurang ??= {};
/* Selalu ada sekurang-kurangnya satu baris: baris pertama adalah Lead
   Auditor, dan daftar kosong tidak memberi tempat untuk mengetiknya. */
if (!Array.isArray(tahapForm.permulaan.tim) || !tahapForm.permulaan.tim.length) tahapForm.permulaan.tim = [''];
/**
 * Kartu hari kerja audit mengikuti isian yang sedang diketik.
 *
 * Sebelumnya keempatnya membaca `props.mandays` — hasil simpanan
 * terakhir. Mengetik jumlah pekerja, mencentang faktor, atau menambah
 * auditor tidak mengubah apa pun sampai tombol simpan ditekan, sehingga
 * perhitungannya tampak tidak jalan. Yang terbaca memang bukan angka
 * dari isian yang sedang dilihat.
 *
 * Rumusnya tidak disalin ke sini: ia dihitung server lewat endpoint yang
 * tidak menyimpan apa pun. Dua salinan rumus hari kerja audit akan
 * berselisih cepat atau lambat, dan yang berselisih adalah tagihannya.
 *
 * Ditunda sesaat supaya mengetik angka empat digit tidak melahirkan empat
 * permintaan, dan jawaban yang datang terlambat dibuang — tanpa itu,
 * jawaban permintaan lama dapat menimpa jawaban yang lebih baru.
 */
const mandaysHidup = ref<Record<string, any> | null>(null);
const mandaysTampil = computed(() => mandaysHidup.value ?? props.mandays);

let jedaMandays: ReturnType<typeof setTimeout> | undefined;
let permintaanKe = 0;

async function hitungMandays() {
  if (!props.tautan?.mandays) return;

  const ini = ++permintaanKe;
  const p = tahapForm.permulaan;

  try {
    const r = await fetch(props.tautan.mandays, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
      },
      body: JSON.stringify({
        permulaan: {
          tim: p.tim, jumlah_pekerja: p.jumlah_pekerja, jumlah_auditor: p.jumlah_auditor,
          kelas_risiko: p.kelas_risiko, faktor: p.faktor, pengurang: p.pengurang,
        },
      }),
    });
    if (!r.ok) return;

    const hasil = await r.json();
    if (ini === permintaanKe) mandaysHidup.value = hasil;
  } catch {
    /* Jaringan tambang memang putus-putus. Kartunya tetap menampilkan
       angka terakhir yang sah, bukan nol yang menyesatkan. */
  }
}

watch(
  () => [
    tahapForm.permulaan.jumlah_pekerja,
    tahapForm.permulaan.kelas_risiko,
    tahapForm.permulaan.jumlah_auditor,
    JSON.stringify(tahapForm.permulaan.tim ?? []),
    JSON.stringify(tahapForm.permulaan.faktor ?? {}),
    JSON.stringify(tahapForm.permulaan.pengurang ?? {}),
  ],
  () => {
    clearTimeout(jedaMandays);
    jedaMandays = setTimeout(hitungMandays, 300);
  },
);

const rencanaForm = useForm<Record<string, any>>({ ...(audit.value.rencana ?? {}), susunan: audit.value.rencana?.susunan ?? [], tugas: audit.value.rencana?.tugas ?? [], pengesahan: audit.value.rencana?.pengesahan ?? {}, risiko: audit.value.risiko ?? { present: [], future: [] } });
const hadirForm = useForm<Record<string, any>>({ rapat: 'pembukaan', nama: '', jabatan: '', perusahaan: '' });
const nilaiForm = useForm<Record<string, any>>({ k: {} });
const temuanForm = reactive<Record<string, any>>({});
const temuan = computed(() => props.temuan ?? []);
const elements = Array.isArray(props.elemen) ? props.elemen : (props.elemen ? [props.elemen] : []);
for (const element of elements) {
  tahapForm.kecukupan[element.kode] ??= { status: '', ket: '' };
  for (const sub of element.sub ?? []) {
    for (const item of (sub.subsub?.length ? sub.subsub : [sub])) nilaiForm.k[item.kode] = nilaiAwal(item.kode);
  }
}
for (const item of temuan.value) temuanForm[item.id] = { akar_masalah: item.akar_masalah ?? '', tindakan: item.tindakan ?? '', penanggung_jawab: item.penanggung_jawab ?? '', target_selesai: tanggal(item.target_selesai), status: item.status ?? 'Open', verifikasi: item.verifikasi ?? '' };

function simpanAudit() { audit.value.id ? form.put(`/smkp/${audit.value.id}`, { preserveScroll: true }) : form.post('/smkp', { preserveScroll: true }); }
function simpanTahap1() { tahapForm.post(`/smkp/${audit.value.id}/tahap-1`, { preserveScroll: true }); }
function simpanRencana() { rencanaForm.post(`/smkp/${audit.value.id}/rencana`, { preserveScroll: true }); }
function simpanHadir() { hadirForm.post(`/smkp/${audit.value.id}/rapat`, { preserveScroll: true, onSuccess: () => hadirForm.reset('nama', 'jabatan', 'perusahaan') }); }
function simpanNilai() { nilaiForm.post(`/smkp/${audit.value.id}/elemen/${props.elemen?.kode}`, { preserveScroll: true }); }
function angkatTemuan() { router.post(`/smkp/${audit.value.id}/temuan/angkat`, {}, { preserveScroll: true }); }
function simpanTemuan(item: any) { router.put(`/smkp/${audit.value.id}/temuan/${item.id}`, temuanForm[item.id], { preserveScroll: true }); }
async function hapusTemuan(item: any) { if (await tanya('Hapus temuan ini?')) router.delete(`/smkp/${audit.value.id}/temuan/${item.id}`, { preserveScroll: true }); }
function tambahBaris(kunci: 'susunan' | 'tugas') { rencanaForm[kunci].push(kunci === 'susunan' ? { tanggal: '', waktu: '', kegiatan: '', auditi: '', auditor: '' } : { nama: '', peran: '', registrasi: '', lingkup: '' }); }
/* Pembagian tugas diisi dari susunan tim Tahap I, bukan diketik ulang.
   Mengetik ulang nama melahirkan dua daftar yang dapat berselisih, dan
   lingkup elemen berakhir tertulis atas nama orang yang tidak berangkat.
   Yang sudah punya baris dibiarkan — isinya lingkup yang sudah dibagi. */
function isiTugasDariTim() {
  const ada = new Set((rencanaForm.tugas ?? []).map((b: any) => String(b?.nama ?? '').trim()).filter(Boolean));
  for (const orang of props.timTahap1 ?? []) {
    if (ada.has(orang.nama)) continue;
    rencanaForm.tugas.push({ nama: orang.nama, peran: orang.peran, registrasi: orang.registrasi ?? '', lingkup: '' });
  }
}
function hapusBaris(kunci: 'susunan' | 'tugas', index: number | string) { rencanaForm[kunci].splice(Number(index), 1); }
function ubahTahap(tahap: number) { router.post(`/smkp/${audit.value.id}/tahap`, { tahap }, { preserveScroll: true }); }
/* Menyalin, bukan mengikat. Jumlah pekerja audit adalah angka PADA SAAT
   audit berjalan; mengikatnya ke profil perusahaan membuat audit tahun lalu
   ikut berubah begitu jumlah pekerjanya diperbarui hari ini. */
function tambahAuditor() { tahapForm.permulaan.tim.push(''); }
function hapusAuditor(i: number) { tahapForm.permulaan.tim.splice(i, 1); }
function pakaiProfilPerusahaan() {
  const p = props.pekerjaPerusahaan ?? {};
  if (p.total) tahapForm.permulaan.jumlah_pekerja = p.total;
  if (p.risiko && (props.risiko ?? []).includes(p.risiko)) tahapForm.permulaan.kelas_risiko = p.risiko;
}
function nilaiAwal(kode: string) { return { ...(audit.value.hasil?.[kode] ?? {}) }; }
</script>

<template>
  <Head :title="title[props.mode] ?? 'SMKP'" />
  <div class="max-w-[1400px] mx-auto space-y-5">
    <section class="flex flex-wrap items-end justify-between gap-4"><div><h2 class="text-xl font-bold text-cam-ink">{{ title[props.mode] ?? 'SMKP' }}</h2><p class="text-[12.5px] text-stone-500 mt-1">Audit keselamatan pertambangan berbasis tujuh elemen SMKP.</p></div><div class="flex gap-2"><Link v-if="props.mode === 'index'" href="/smkp/buat" class="eq-btn-utama">Buat Periode Audit</Link><Link v-if="props.mode === 'show'" :href="`/smkp/${audit.id}/laporan`" class="eq-btn-lain" target="_blank">Laporan cetak</Link></div></section>

    <section v-if="props.mode === 'index'" class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-x-auto"><table class="min-w-full text-left text-[12px]"><thead><tr class="border-b border-stone-100 text-stone-400"><th class="px-5 py-3">Periode</th><th class="px-5 py-3">Perusahaan</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Temuan</th><th class="px-5 py-3"></th></tr></thead><tbody><tr v-for="item in audits" :key="item.id" class="border-b border-stone-50"><td class="px-5 py-3"><Link :href="`/smkp/${item.id}`" class="font-semibold text-cam-lime-deep py-1.5 -my-1.5">{{ item.judul || `Audit ${item.tahun}` }}</Link><small class="block text-stone-400">{{ item.tahun }}</small></td><td class="px-5 py-3">{{ item.company?.name ?? '—' }}</td><td class="px-5 py-3">{{ item.status }}</td><td class="px-5 py-3">{{ item.findings_count ?? 0 }} <span class="text-stone-400">({{ item.findings_open_count ?? 0 }} terbuka)</span></td><td class="px-5 py-3 text-right"><Link :href="`/smkp/${item.id}/ubah`" class="text-cam-lime-deep mr-2 py-1.5 -my-1.5">Ubah</Link></td></tr></tbody></table><div v-if="!audits.length" class="p-8 text-center text-[13px] text-stone-500">Belum ada periode audit.</div><div v-if="props.audits?.links" class="flex gap-1 p-4"><Link v-for="link in props.audits.links" :key="link.label" :href="link.url ?? '# '" class="rounded px-2 py-1 text-[11px]" :class="link.active ? 'bg-cam-ink text-white' : 'bg-stone-100 text-stone-500'" v-html="link.label" /></div></section>

    <section v-if="props.mode === 'form'" class="rounded-2xl bg-white border border-stone-100 shadow-card p-6 max-w-3xl"><form class="space-y-4" @submit.prevent="simpanAudit"><div><label class="block text-[12px] font-semibold mb-1">Perusahaan</label><select v-model="form.company_id" class="w-full rounded-xl border-stone-200 text-[13px]" aria-label="Perusahaan"><option value="">Perusahaan aktif saya</option><option v-for="company in props.companies ?? []" :key="company.id" :value="company.id">{{ company.name }}</option></select></div><div><label class="block text-[12px] font-semibold mb-1">Judul audit</label><input v-model="form.judul" class="w-full rounded-xl border-stone-200 text-[13px]" placeholder="Audit Internal SMKP 2026"></div><div class="grid gap-3 sm:grid-cols-2"><label class="text-[12px] font-semibold">Tahun<input v-model="form.tahun" type="number" class="mt-1 w-full rounded-xl border-stone-200 text-[13px]"></label><label class="text-[12px] font-semibold">Ketua auditor<input v-model="form.ketua_auditor" class="mt-1 w-full rounded-xl border-stone-200 text-[13px]"></label><label class="text-[12px] font-semibold">Tanggal mulai<input v-model="form.tanggal_mulai" type="date" class="mt-1 w-full rounded-xl border-stone-200 text-[13px]"></label><label class="text-[12px] font-semibold">Tanggal selesai<input v-model="form.tanggal_selesai" type="date" class="mt-1 w-full rounded-xl border-stone-200 text-[13px]"></label></div><label class="block text-[12px] font-semibold">Status<select v-model="form.status" class="mt-1 w-full rounded-xl border-stone-200 text-[13px]"><option value="draft">Draft</option><option value="berjalan">Berjalan</option><option value="selesai">Selesai</option></select></label><p v-if="form.errors.tahun" class="text-xs text-red-600">{{ form.errors.tahun }}</p><button class="eq-btn-utama" :disabled="form.processing">{{ form.processing ? 'Menyimpan…' : 'Simpan Periode' }}</button></form></section>

    <template v-if="props.mode === 'show'"><section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><article v-for="item in ringkas" :key="item.label" class="rounded-2xl bg-white border border-stone-100 shadow-card p-4"><p class="text-[10px] uppercase tracking-wider font-bold text-stone-400">{{ item.label }}</p><strong class="block text-xl mt-1">{{ item.nilai }}</strong><p class="text-[11px] text-stone-500 mt-1 leading-snug">{{ item.ket }}</p></article></section><section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5"><div class="flex flex-wrap gap-2 mb-5"><Link :href="`/smkp/${audit.id}/tahap-1`" class="eq-btn-lain">Tahap I</Link><Link :href="`/smkp/${audit.id}/rencana`" class="eq-btn-lain">Rencana Audit</Link><Link :href="`/smkp/${audit.id}/penilaian`" class="eq-btn-utama">Form Penilaian Audit</Link><Link :href="`/smkp/${audit.id}/rapat`" class="eq-btn-lain">Rapat</Link><Link :href="`/smkp/${audit.id}/temuan`" class="eq-btn-lain">Temuan</Link><button type="button" class="eq-btn-lain" @click="ubahTahap((audit.tahap ?? 0) + 1)">Naikkan tahap</button></div><h3 class="font-bold text-[14px]">{{ audit.judul || `Audit ${audit.tahun}` }}</h3><p class="text-[12px] text-stone-500 mt-1">Status: {{ audit.status }} · Tahap {{ audit.tahap ?? 0 }}</p><div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><Link v-for="element in props.elemen" :key="element.kode ?? element.nama" :href="`/smkp/${audit.id}/elemen/${element.kode}`" class="rounded-xl bg-stone-50 p-3 hover:bg-cam-lime-soft"><b class="text-[12px]">{{ element.nama ?? element }}</b><small class="block text-[11px] text-stone-500 mt-1">Bobot {{ element.bobot ?? '—' }}</small></Link></div></section></template>

    <section v-if="props.mode === 'acuan'" class="grid gap-3 md:grid-cols-2"><article v-for="element in props.elemen" :key="element.kode ?? element.nama" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5"><h3 class="font-bold text-[14px]">{{ element.nama ?? element }}</h3><p class="text-[12px] text-stone-500 mt-2">Bobot: {{ element.bobot ?? '—' }}</p><p v-if="element.rujukan" class="text-[12px] mt-2">{{ element.rujukan }}</p><p class="text-[11px] text-stone-400 mt-3">{{ element.sub?.length ?? 0 }} sub-elemen</p></article></section>

    <section v-if="props.mode === 'tahap1'" class="space-y-4"><form class="space-y-4" @submit.prevent="simpanTahap1"><div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5"><h3 class="font-bold text-[14px]">Data permulaan audit</h3><div class="grid gap-3 sm:grid-cols-2 mt-4"><label class="text-[12px] font-semibold">Tanggal kontak<input v-model="tahapForm.permulaan.tanggal_kontak" type="date" class="mt-1 w-full rounded-xl border-stone-200 text-[12px]"></label><label class="text-[12px] font-semibold">Media kontak<input v-model="tahapForm.permulaan.media_kontak" class="mt-1 w-full rounded-xl border-stone-200 text-[12px]"></label><label class="text-[12px] font-semibold">Auditi<input v-model="tahapForm.permulaan.wakil_auditi" class="mt-1 w-full rounded-xl border-stone-200 text-[12px]"></label><label class="text-[12px] font-semibold">Jabatan<input v-model="tahapForm.permulaan.jabatan_wakil" class="mt-1 w-full rounded-xl border-stone-200 text-[12px]"></label><label class="text-[12px] font-semibold">Jumlah pekerja<input v-model="tahapForm.permulaan.jumlah_pekerja" type="number" class="mt-1 w-full rounded-xl border-stone-200 text-[12px]"></label><label class="text-[12px] font-semibold">Kelas risiko<select v-model="tahapForm.permulaan.kelas_risiko" class="mt-1 w-full rounded-xl border-stone-200 text-[12px]"><option v-for="item in props.risiko ?? []" :key="item">{{ item }}</option></select></label><label class="text-[12px] font-semibold">Kesimpulan<textarea v-model="tahapForm.permulaan.kesimpulan" rows="2" class="mt-1 w-full rounded-xl border-stone-200 text-[12px]"></textarea></label></div></div><div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5"><h3 class="font-bold text-[14px]">Kecukupan dokumentasi</h3><div class="divide-y divide-stone-100 mt-3"><div v-for="element in props.elemen" :key="element.kode" class="py-3 grid gap-2 md:grid-cols-[1fr_180px_1fr] items-center"><span class="text-[12px] font-semibold">{{ element.kode }} · {{ element.nama }}</span><select v-model="tahapForm.kecukupan[element.kode].status" class="rounded-lg border-stone-200 text-[12px]" aria-label="Status"><option value="">Belum ditinjau</option><option value="Lengkap">Lengkap</option><option value="Tidak lengkap">Tidak lengkap</option></select><input v-model="tahapForm.kecukupan[element.kode].ket" placeholder="Catatan" class="rounded-lg border-stone-200 text-[12px]"></div></div></div><div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5"><h3 class="font-bold text-[14px]">Kinerja Keselamatan Pertambangan pada periode audit</h3>
      <!-- Label diambil dari acuan, bukan dari kunci ruasnya. Diulang apa
           adanya, `cidera_ringan` tercetak di layar sebagai nama butir. -->
      <div class="grid gap-2 sm:grid-cols-2 mt-3"><label v-for="(butir, kunci) in props.kinerja ?? {}" :key="kunci" class="text-[12px]">{{ butir.label }}<span v-if="butir.satuan" class="text-stone-400"> ({{ butir.satuan }})</span><input v-model="tahapForm.kinerja[kunci]" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label></div></div>

      <!-- Hari kerja audit. Mandays dasar DIBACA DARI TABEL menurut jumlah
           pekerja dan kelas risiko di atas, bukan diketik: angka ketikan
           tidak dapat ditelusuri kembali ke dasarnya, dan dua auditor yang
           mengetik berbeda menagih hari berbeda tanpa satu pun yang salah
           menurut sistem. -->
      <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5"><h3 class="font-bold text-[14px]">Perhitungan hari kerja audit</h3>
        <p class="text-[12px] text-stone-500 mt-1">Mandays dasar terbaca dari tabel menurut jumlah pekerja dan kelas risiko auditi. Tersimpan setelah tombol di bawah ditekan.</p>
        <!-- Dibiarkan nol, tabel jatuh ke baris terkecil dan menagih tiga
             hari untuk tambang berapa pun besarnya — tanpa satu pun galat. -->
        <p v-if="props.pekerjaPerusahaan?.total" class="text-[12px] mt-2">
          Profil perusahaan mencatat <b>{{ props.pekerjaPerusahaan.total }}</b> pekerja
          ({{ props.pekerjaPerusahaan.karyawan }} karyawan · {{ props.pekerjaPerusahaan.subkontrak }} subkontraktor)<span v-if="props.pekerjaPerusahaan.risiko">, risiko {{ props.pekerjaPerusahaan.risiko }}</span>.
          <button type="button" class="eq-btn-mini ml-1" @click="pakaiProfilPerusahaan">Pakai angka ini</button>
        </p>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mt-4">
          <div v-for="kartu in [
                 ['Mandays dasar', `${mandaysTampil?.dasar ?? 0} hari`, `${mandaysTampil?.pekerja ?? 0} pekerja · risiko ${mandaysTampil?.kelas ?? '—'} · rentang ${mandaysTampil?.rentang ?? '—'}`],
                 ['Total mandays', `${mandaysTampil?.total ?? 0}`, `penambah +${mandaysTampil?.penambah ?? 0} · pengurang −${mandaysTampil?.pengurang ?? 0}`],
                 ['Durasi di lapangan', `${mandaysTampil?.durasi ?? 0} hari`, `dibagi ${mandaysTampil?.auditor ?? 1} auditor`],
                 ['Alokasi tahap', `${mandaysTampil?.tahap1 ?? 0} / ${mandaysTampil?.tahap2 ?? 0}`, 'Tahap I maks 10% · Tahap II sisanya'],
               ]" :key="kartu[0]" class="rounded-xl bg-stone-50 p-3">
            <p class="text-[10px] uppercase tracking-wider font-bold text-stone-400">{{ kartu[0] }}</p>
            <strong class="block text-lg mt-1">{{ kartu[1] }}</strong>
            <p class="text-[11px] text-stone-500 mt-1 leading-snug">{{ kartu[2] }}</p>
          </div>
        </div>
        <!-- Satu nama satu auditor; jumlahnya yang membagi durasi. Angka
             lepas dapat menyimpang dari tim yang sebenarnya berangkat —
             diketik 4 sementara yang hadir 2 — dan durasi yang tercetak
             menjadi separuh dari yang akan terjadi. Peran mengikuti urutan,
             sehingga selalu ada tepat satu Lead Auditor. -->
        <div class="mt-5">
          <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h4 class="text-[12px] font-bold">Susunan tim auditor — {{ tahapForm.permulaan.tim.length }} orang</h4>
            <button type="button" class="eq-btn-mini" @click="tambahAuditor">Tambah auditor</button>
          </div>
          <div v-for="(nama, i) in tahapForm.permulaan.tim" :key="i" class="flex items-center gap-2 mt-2">
            <span class="eq-keadaan shrink-0 w-24 justify-center"
                  :style="{ color: Number(i) === 0 ? '#0F766E' : '#7C8894', borderColor: Number(i) === 0 ? '#0F766E' : '#C9CFD4' }">
              {{ Number(i) === 0 ? 'Lead Auditor' : `Auditor ${Number(i) + 1}` }}
            </span>
            <input v-model="tahapForm.permulaan.tim[i]" placeholder="Nama auditor"
                   class="flex-1 rounded-lg border-stone-200 text-[12px]">
            <!-- Baris pertama tidak dapat dibuang: audit tanpa ketua tim
                 tidak dapat ditandatangani, dan pembagi durasi tidak boleh
                 jatuh ke nol. -->
            <button v-if="Number(i) > 0" type="button" class="eq-btn-mini bahaya" @click="hapusAuditor(Number(i))">Hapus</button>
          </div>
          <p class="text-[11px] text-stone-500 mt-2">
            Nomor registrasi dan lingkup elemen tiap auditor diisi pada Pembagian Tugas di Rencana Audit.
          </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 mt-5">
          <div><h4 class="text-[12px] font-bold">Faktor penambah hari</h4>
            <label v-for="(teks, kunci) in props.faktor ?? {}" :key="kunci" class="flex gap-2 items-start text-[12px] mt-2"><input v-model="tahapForm.permulaan.faktor[kunci]" type="checkbox" class="mt-0.5 accent-[#F57C00]"><span>{{ teks }}</span></label></div>
          <!-- Tanpa daftar pengurang, mandays hanya dapat bertambah:
               perusahaan yang sistemnya matang dan auditnya bersih tetap
               ditagih hari sebanyak yang paling bermasalah. -->
          <div><h4 class="text-[12px] font-bold">Faktor pengurang hari</h4>
            <label v-for="(teks, kunci) in props.pengurang ?? {}" :key="kunci" class="flex gap-2 items-start text-[12px] mt-2"><input v-model="tahapForm.permulaan.pengurang[kunci]" type="checkbox" class="mt-0.5 accent-[#F57C00]"><span>{{ teks }}</span></label></div>
        </div>

        <!-- Tabelnya ditampilkan, bukan hanya hasilnya. Auditor yang
             menyerahkan angka mandays kepada auditi akan ditanya dari mana
             angka itu; baris yang berlaku ditandai supaya jawabannya ada di
             layar yang sama, bukan di berkas acuan yang terpisah. -->
        <details class="mt-5">
          <summary class="text-[12px] font-bold cursor-pointer">Tabel mandays dasar — {{ (props.tabelMandays ?? []).length }} rentang pekerja</summary>
          <div class="overflow-x-auto mt-3">
            <table class="min-w-full text-[11.5px]">
              <thead><tr class="text-left text-stone-400 border-b border-stone-100"><th class="py-1.5 pr-4">Jumlah pekerja</th><th class="py-1.5 pr-4">Tinggi</th><th class="py-1.5 pr-4">Menengah</th><th class="py-1.5">Rendah</th></tr></thead>
              <tbody>
                <tr v-for="baris in props.tabelMandays ?? []" :key="baris[0]"
                    class="border-b border-stone-50"
                    :class="mandaysTampil?.rentang === `${baris[0]}–${baris[1] >= 999999 ? '∞' : baris[1]}` ? 'font-bold bg-stone-50' : ''">
                  <td class="py-1.5 pr-4">{{ baris[0] }}–{{ baris[1] >= 999999 ? '∞' : baris[1] }}</td>
                  <td class="py-1.5 pr-4">{{ baris[2] }}</td>
                  <td class="py-1.5 pr-4">{{ baris[3] }}</td>
                  <td class="py-1.5">{{ baris[4] }}</td>
                </tr>
              </tbody>
            </table>
            <!-- Peringatan sumber ikut tercetak. Angka ini menagih hari kerja
                 auditor dan dibawa ke auditi; berkas acuannya sendiri
                 menyebutnya default ilustratif, bukan angka Kepdirjen. -->
            <p class="text-[11px] text-amber-700 mt-3 leading-relaxed">
              Tabel ini pola ISO/IEC 17021 sebagai bawaan, bukan salinan angka Kepdirjen
              185.K/37.04/DJB/2019. Sesuaikan bila ketentuan yang berlaku bagi perusahaan berbeda.
            </p>
          </div>
        </details>
      </div><button class="eq-btn-utama">Simpan Tahap I</button></form></section>

    <section v-if="props.mode === 'rencana'" class="space-y-4">
      <!-- Hitungan Tahap I dibawa ke sini. Keduanya disimpan terpisah dan
           sampai sekarang tidak pernah dibandingkan, sehingga rencana yang
           menjadwalkan tiga hari untuk audit yang menuntut empat belas tetap
           lolos sebagai "lengkap" — dan selisihnya baru ketahuan di lapangan
           pada hari terakhir. -->
      <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
          <h3 class="font-bold text-[14px]">Dasar hari kerja audit</h3>
          <Link :href="`/smkp/${audit.id}/tahap-1`" class="eq-btn-mini">Ubah di Tahap I</Link>
        </div>
        <p class="text-[12px] text-stone-500 mt-1">
          {{ mandaysTampil?.pekerja ?? 0 }} pekerja · risiko {{ mandaysTampil?.kelas ?? '—' }} ·
          {{ mandaysTampil?.total ?? 0 }} mandays ÷ {{ mandaysTampil?.auditor ?? 1 }} auditor =
          <b>{{ mandaysTampil?.durasi ?? 0 }} hari</b> di lapangan, Tahap II
          <b>{{ mandaysTampil?.tahap2 ?? 0 }} hari</b>.
        </p>
        <ul class="mt-3 space-y-1.5">
          <li v-for="c in props.selaras ?? []" :key="c.kunci" class="flex gap-2 text-[12px] items-start">
            <!-- Bentuk lebih dulu, warna menyusul: lembar ini juga dibaca
                 orang yang tidak dapat membedakan hijau dari merah. -->
            <span class="font-bold w-4 shrink-0" :class="c.selaras ? 'text-emerald-700' : 'text-amber-700'">{{ c.selaras ? '✓' : '!' }}</span>
            <span><b>{{ c.judul }}</b> — <span :class="c.selaras ? 'text-stone-500' : 'text-amber-700'">{{ c.ket }}</span></span>
          </li>
        </ul>
      </div>

      <form @submit.prevent="simpanRencana"><div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5"><h3 class="font-bold text-[14px]">Sembilan komponen rencana audit</h3><div class="grid gap-3 md:grid-cols-2 mt-4"><label v-for="field in ['nomor','tanggal_mulai','tanggal_selesai']" :key="field" class="text-[12px] font-semibold">{{ field.replaceAll('_',' ') }}<input v-model="rencanaForm[field]" :type="field.includes('tanggal') ? 'date' : 'text'" class="mt-1 w-full rounded-xl border-stone-200 text-[12px]"></label><label v-for="field in ['tujuan','kriteria','ruang_lingkup','sumberdaya','metode','sampel']" :key="field" class="text-[12px] font-semibold md:col-span-2">{{ field.replaceAll('_',' ') }}<textarea v-model="rencanaForm[field]" rows="2" class="mt-1 w-full rounded-xl border-stone-200 text-[12px]"></textarea></label></div></div><div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5"><div class="flex justify-between items-center"><h3 class="font-bold text-[14px]">Susunan kegiatan</h3><button type="button" class="eq-btn-lain" @click="tambahBaris('susunan')">Tambah baris</button></div><div v-for="(row, index) in rencanaForm.susunan" :key="index" class="grid gap-2 md:grid-cols-5 mt-3"><input v-model="row.tanggal" type="date" class="rounded-lg border-stone-200 text-[11px]" aria-label="Tanggal"><input v-model="row.waktu" placeholder="Waktu" class="rounded-lg border-stone-200 text-[11px]"><input v-model="row.kegiatan" placeholder="Kegiatan" class="rounded-lg border-stone-200 text-[11px]"><input v-model="row.auditi" placeholder="Auditi" class="rounded-lg border-stone-200 text-[11px]"><button type="button" class="text-red-600 text-[11px]" @click="hapusBaris('susunan', index)">Hapus</button></div></div><div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5"><div class="flex flex-wrap justify-between items-center gap-2"><h3 class="font-bold text-[14px]">Pembagian tugas tim audit</h3><div class="flex gap-2"><button type="button" class="eq-btn-lain" @click="isiTugasDariTim">Isi dari susunan Tahap I</button><button type="button" class="eq-btn-lain" @click="tambahBaris('tugas')">Tambah anggota</button></div></div>
      <p class="text-[12px] text-stone-500 mt-1">Nama dan peran berasal dari susunan tim Tahap I; di sini ditambahkan nomor registrasi dan elemen yang menjadi lingkup tiap auditor.</p>
      <!-- Kolom lingkup sebelumnya tidak punya ruas isian sama sekali,
           padahal ia tercetak pada Rencana Audit maupun Laporan Audit —
           kolom yang selalu kosong tanpa ada cara mengisinya. -->
      <div v-for="(row, index) in rencanaForm.tugas" :key="index" class="grid gap-2 md:grid-cols-[1.2fr_1fr_1fr_1.4fr_auto] mt-3"><input v-model="row.nama" placeholder="Nama" class="rounded-lg border-stone-200 text-[11px]"><input v-model="row.peran" placeholder="Peran" class="rounded-lg border-stone-200 text-[11px]"><input v-model="row.registrasi" placeholder="No. registrasi" class="rounded-lg border-stone-200 text-[11px]"><input v-model="row.lingkup" placeholder="Lingkup elemen, mis. I, II" class="rounded-lg border-stone-200 text-[11px]"><button type="button" class="text-red-600 text-[11px]" @click="hapusBaris('tugas', index)">Hapus</button></div></div><button class="eq-btn-utama">Simpan Rencana Audit</button></form><div class="text-[12px] text-stone-500">Kelengkapan: {{ props.rekap?.lengkap ? 'lengkap' : `belum lengkap (${(props.rekap?.kurang ?? []).join(', ')})` }}</div></section>

    <section v-if="props.mode === 'rapat'" class="space-y-4"><div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5"><h3 class="font-bold text-[14px]">Tambah peserta rapat</h3><form class="grid gap-3 md:grid-cols-5 mt-4" @submit.prevent="simpanHadir"><select v-model="hadirForm.rapat" class="rounded-lg border-stone-200 text-[12px]" aria-label="Rapat"><option v-for="(label, key) in props.rapat ?? {}" :key="key" :value="key">{{ label }}</option></select><input v-model="hadirForm.nama" required placeholder="Nama" class="rounded-lg border-stone-200 text-[12px]"><input v-model="hadirForm.jabatan" placeholder="Jabatan" class="rounded-lg border-stone-200 text-[12px]"><input v-model="hadirForm.perusahaan" placeholder="Perusahaan" class="rounded-lg border-stone-200 text-[12px]"><button class="eq-btn-utama">Tambah</button></form></div><div v-for="(rows, key) in props.hadir ?? {}" :key="key" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5"><h3 class="font-bold text-[14px]">{{ props.rapat?.[key] ?? key }}</h3><div v-for="person in rows" :key="person.id" class="flex justify-between border-b border-stone-100 py-2 text-[12px]"><span>{{ person.nama }} · {{ person.jabatan ?? '—' }} · {{ person.perusahaan ?? '—' }}</span><button type="button" class="text-red-600" @click="router.delete(`/smkp/${audit.id}/rapat/${person.id}`)">Hapus</button></div><a :href="`/smkp/${audit.id}/daftar-hadir/${key}`" target="_blank" class="inline-block mt-3 text-[11px] text-cam-lime-deep">Cetak daftar hadir</a></div></section>

    <section v-if="props.mode === 'nilai'" class="space-y-4"><div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5"><div class="flex justify-between"><div><h3 class="font-bold text-[15px]">{{ props.elemen?.kode }} · {{ props.elemen?.nama }}</h3><p class="text-[12px] text-stone-500 mt-1">Isi nilai sesuai maksimum setiap butir. Gunakan N/A bila tidak berlaku.</p></div><div class="flex gap-2"><Link :href="`/smkp/${audit.id}/penilaian`" class="eq-btn-lain">Seluruh elemen</Link><Link :href="`/smkp/${audit.id}`" class="eq-btn-lain">Kembali</Link></div></div></div><form @submit.prevent="simpanNilai"><div v-for="sub in props.elemen?.sub ?? []" :key="sub.kode" class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden mb-4"><h3 class="px-5 py-3 bg-stone-50 text-[13px] font-bold">{{ sub.kode }} · {{ sub.nama }}</h3><div v-for="item in (sub.subsub?.length ? sub.subsub : [sub])" :key="item.kode" class="grid gap-2 md:grid-cols-[1fr_100px_1fr_1fr] items-start px-5 py-3 border-t border-stone-100"><div class="text-[12px]"><b>{{ item.kode }}</b> · {{ item.nama }}<small class="block text-stone-400">Maks: {{ item.maks }}</small></div><input v-model="nilaiForm.k[item.kode].v" :placeholder="`0–${item.maks}`" class="rounded-lg border-stone-200 text-[12px]"><input v-model="nilaiForm.k[item.kode].ket" placeholder="Keterangan" class="rounded-lg border-stone-200 text-[12px]"><input v-model="nilaiForm.k[item.kode].bukti" placeholder="Bukti objektif" class="rounded-lg border-stone-200 text-[12px]"></div></div><button class="eq-btn-utama">Simpan penilaian</button></form></section>

    <section v-if="props.mode === 'temuan'" class="space-y-4"><div class="flex flex-wrap gap-2"><button type="button" class="eq-btn-utama" @click="angkatTemuan">Angkat seluruh temuan usulan ({{ props.usulan?.length ?? 0 }})</button><Link :href="`/smkp/${audit.id}`" class="eq-btn-lain">Kembali ke ringkasan</Link></div><div v-for="item in temuan" :key="item.id" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5"><div class="flex justify-between gap-3"><div><h3 class="font-bold text-[13px]">{{ item.kode_kriteria }} · {{ item.jenis }}</h3><p class="text-[12px] text-stone-500 mt-1">{{ item.uraian }}</p></div><button type="button" class="text-red-600 text-[11px]" @click="hapusTemuan(item)">Hapus</button></div><div class="grid gap-3 md:grid-cols-3 mt-4"><textarea v-model="temuanForm[item.id].akar_masalah" placeholder="Akar masalah" class="rounded-lg border-stone-200 text-[12px]"></textarea><textarea v-model="temuanForm[item.id].tindakan" placeholder="Tindakan perbaikan" class="rounded-lg border-stone-200 text-[12px]"></textarea><div class="space-y-2"><input v-model="temuanForm[item.id].penanggung_jawab" placeholder="Penanggung jawab" class="w-full rounded-lg border-stone-200 text-[12px]"><input v-model="temuanForm[item.id].target_selesai" type="date" class="w-full rounded-lg border-stone-200 text-[12px]" aria-label="Tenggat"><select v-model="temuanForm[item.id].status" class="w-full rounded-lg border-stone-200 text-[12px]" aria-label="Status"><option>Open</option><option>In Progress</option><option>Closed</option></select></div></div><button type="button" class="eq-btn-lain mt-3" @click="simpanTemuan(item)">Simpan tindakan</button></div><div v-if="!temuan.length" class="rounded-2xl bg-white p-8 text-center text-stone-500">Belum ada temuan.</div></section>
  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>

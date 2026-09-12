<script setup lang="ts">
/**
 * Kontrak kerja: PKWT, PKWTT, dan rantai perpanjangannya.
 *
 * TEMUANNYA MENYEBUT AKIBATNYA, BUKAN SEKADAR "TIDAK SESUAI". Melanggar
 * batas PKWT tidak berbuah denda melainkan perubahan jenis hubungan
 * kerja — dan perbedaan antara "kontrak ini perlu ditinjau" dengan
 * "orang ini sudah karyawan tetap sejak Maret" adalah perbedaan antara
 * catatan yang diabaikan dan catatan yang ditindaklanjuti hari itu juga.
 *
 * MASA RANTAI DITAMPILKAN DI SAMPING MASA KONTRAKNYA. Angka yang
 * menentukan bukan panjang kontrak yang sedang dibuka, melainkan
 * jumlah seluruh rangkaiannya; ditampilkan sendirian, tiap baris
 * tampak patuh sementara jumlahnya sudah lama tidak.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { computed, h, ref } from 'vue';
import { propHalaman } from '../../../halaman';
import KopHalaman from '../../../Components/KopHalaman.vue';
import UbinAngka from '../../../Components/UbinAngka.vue';
import Dialog from '../../../Components/Dialog.vue';
import { useDialog } from '../../../dialog';

const IKON: Record<string, string[]> = {
  berjalan: ['M7 3.5h7L18 8v12.5H7a1 1 0 0 1-1-1v-15a1 1 0 0 1 1-1Z', 'M14 3.5V8h4', 'M9 13h6M9 16.5h4'],
  pkwt:     ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'M12 7.5v5l3.2 1.9'],
  pkwtt:    ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'm8.5 12.2 2.4 2.4 4.6-4.9'],
  gawat:    ['M12 9.4v4.2', 'M12 17h.01', 'M10.4 4.1 2.6 17.8a1.8 1.8 0 0 0 1.6 2.7h15.6a1.8 1.8 0 0 0 1.6-2.7L13.6 4.1a1.8 1.8 0 0 0-3.2 0Z'],
  berakhir: ['M8 3v3m8-3v3M3.5 9.5h17M5 5.5h14a1.5 1.5 0 0 1 1.5 1.5v12a1.5 1.5 0 0 1-1.5 1.5H5A1.5 1.5 0 0 1 3.5 19V7A1.5 1.5 0 0 1 5 5.5Z'],
  uang:     ['M3.5 8.5h17a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1h-17a1 1 0 0 1-1-1v-8a1 1 0 0 1 1-1Z', 'M3.5 8.5 17 5.2l.9 3.3', 'M17.5 13.5h.01'],
};

const props = propHalaman();
const { dialog, tanya, minta, batal, lanjut } = useDialog();

/** Pembungkus kecil supaya tiap ubin cukup menyebut nama ikonnya. */
const Ikon = (p: { nama: string }) => h('svg', {
  viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 1.9,
  'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'aria-hidden': 'true',
}, (IKON[p.nama] ?? []).map((d) => h('path', { d })));

const baris        = computed<any[]>(() => (props.baris ?? []) as any[]);
const temuan       = computed<any[]>(() => (props.temuan ?? []) as any[]);
const akanBerakhir = computed<any[]>(() => (props.akanBerakhir ?? []) as any[]);
const ringkas      = computed<any>(() => props.ringkas ?? {});
const acuan        = computed<any>(() => props.ACUAN ?? {});
const daftarPekerja= computed<any[]>(() => (props.pekerja ?? []) as any[]);

const JENIS  = computed<Record<string, string>>(() => (props.JENIS  ?? {}) as any);
const ALASAN = computed<Record<string, string>>(() => (props.ALASAN ?? {}) as any);
const STATUS = computed<Record<string, string>>(() => (props.STATUS ?? {}) as any);

const gawat = computed(() => temuan.value.filter((t) => t.berat === 'gawat'));
const ingat = computed(() => temuan.value.filter((t) => t.berat !== 'gawat'));

/** Temuan sebuah kontrak, untuk digambar di barisnya sendiri. */
function temuanBaris(id: number) {
  return temuan.value.filter((t) => t.kontrak_id === id);
}

function rupiah(n: number | null | undefined): string {
  if (n === null || n === undefined) return '—';
  return 'Rp ' + Math.round(Number(n)).toLocaleString('id-ID');
}

function bulanTeks(n: number | null | undefined): string {
  if (n === null || n === undefined) return '—';
  return Number(n).toFixed(1).replace('.', ',') + ' bln';
}

/* ─────────── formulir kontrak baru ─────────── */

const form = useForm<Record<string, any>>({
  pekerja_id: '', nomor: '', jenis: 'pkwt_jangka', alasan: 'tidak_lama',
  mulai: '', selesai: '', batasan_selesai: '', masa_percobaan_hari: 0,
  ditandatangani_pada: '', catatan: '', berkas: null as File | null,
});

const perluAlasan  = computed(() => form.jenis === 'pkwt_jangka');
const perluBatasan = computed(() => form.jenis === 'pkwt_selesai');
const perluSelesai = computed(() => form.jenis !== 'pkwtt' && form.jenis !== 'pkwt_selesai');

function simpan() {
  form.post('/hris/kontrak', {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => form.reset('nomor', 'mulai', 'selesai', 'batasan_selesai', 'catatan', 'berkas'),
  });
}

/* ─────────── tindakan ─────────── */

function kirim(id: number, aksi: string, isi: Record<string, any> = {}) {
  useForm(isi).post(`/hris/kontrak/${id}/${aksi}`, { preserveScroll: true });
}

async function terbitkan(k: any) {
  if (!await tanya({
    judul: `Terbitkan kontrak ${k.nomor}?`,
    pesan: `${k.pekerja} · ${JENIS.value[k.jenis]} · mulai ${k.mulai}.`,
    labelAksi: 'Terbitkan',
  })) return;

  kirim(k.id, 'terbitkan');
}

async function perpanjang(k: any) {
  const nomor = await minta({
    judul: `Perpanjang ${k.nomor}`,
    pesan: `Rangkaian berjalan ${bulanTeks(k.bulanRantai)} dari batas ${acuan.value.maks_bulan} bulan. `
      + 'Periode yang ditutup akan dihitung uang kompensasinya.',
    label: 'Nomor kontrak perpanjangan',
    labelAksi: 'Lanjut',
  });

  if (!nomor) return;

  const mulai = await minta({
    judul: 'Mulai perpanjangan', label: 'Tanggal mulai (YYYY-MM-DD)',
    nilai: k.selesai ?? '', labelAksi: 'Lanjut',
  });

  if (!mulai) return;

  const selesai = await minta({
    judul: 'Selesai perpanjangan', label: 'Tanggal selesai (YYYY-MM-DD)',
    labelAksi: 'Simpan draf',
  });

  if (!selesai) return;

  kirim(k.id, 'perpanjang', { nomor, mulai, selesai });
}

async function akhiri(k: any, status: 'selesai' | 'diputus') {
  const pesan = status === 'diputus'
    ? 'Uang kompensasi dihitung sebesar masa yang SUDAH DIJALANI sampai tanggal ini — PP 35/2021 pasal 17.'
    : 'Uang kompensasi dihitung atas seluruh masa kontraknya.';

  const pada = await minta({
    judul: status === 'diputus' ? `Putus ${k.nomor} di tengah` : `Akhiri ${k.nomor}`,
    pesan,
    label: 'Tanggal berakhir (YYYY-MM-DD)',
    nilai: k.selesai ?? '',
    labelAksi: status === 'diputus' ? 'Putus' : 'Akhiri',
  });

  if (!pada) return;

  kirim(k.id, 'akhiri', { status, pada });
}

async function jadikanPkwtt(k: any) {
  const sebab = await minta({
    judul: `Catat ${k.nomor} berubah menjadi PKWTT`,
    pesan: 'Baris PKWT-nya tetap berdiri sebagaimana ditandatangani; PKWTT baru dibuat di sampingnya '
      + 'dengan tanggal mulai pada saat aturannya terlampaui — bukan hari ini.',
    label: 'Sebab perubahannya',
    labelAksi: 'Catat',
  });

  if (!sebab) return;

  kirim(k.id, 'pkwtt', { sebab });
}

const sedangPilih = ref<number | null>(null);
</script>

<template>
  <Head :title="props.judul as string" />

  <div class="space-y-4">
    <KopHalaman :judul="props.judul as string" :subjudul="props.subjudul as string"
                tagline="Signed And Sound"
                :remah="[['HRIS', '/hris'], ['Kontrak Kerja', null], ['PKWT & PKWTT', null]]" ringkas />

    <section class="grid gap-2 grid-cols-2 sm:grid-cols-3 lg:grid-cols-6">
      <UbinAngka :angka="ringkas.berjalan ?? 0" label="Kontrak berjalan">
        <template #ikon><Ikon nama="berjalan" /></template>
      </UbinAngka>

      <UbinAngka :angka="ringkas.pkwt ?? 0" label="PKWT" nada="serius"
                 :dari="ringkas.berjalan ?? 0">
        <template #ikon><Ikon nama="pkwt" /></template>
      </UbinAngka>

      <UbinAngka :angka="ringkas.pkwtt ?? 0" label="PKWTT" nada="baik"
                 :dari="ringkas.berjalan ?? 0">
        <template #ikon><Ikon nama="pkwtt" /></template>
      </UbinAngka>

      <UbinAngka :angka="ringkas.gawat ?? 0" label="Pelanggaran berat" nada="gawat">
        <template #ikon><Ikon nama="gawat" /></template>
      </UbinAngka>

      <UbinAngka :angka="ringkas.berakhir ?? 0" label="Berakhir 60 hari" nada="ingat">
        <template #ikon><Ikon nama="berakhir" /></template>
      </UbinAngka>

      <UbinAngka :angka="rupiah(ringkas.kompensasi)" label="Kompensasi belum dibayar" nada="luar">
        <template #ikon><Ikon nama="uang" /></template>
      </UbinAngka>
    </section>

    <!-- ── temuan ── -->
    <section v-if="gawat.length" class="rounded-2xl border border-red-200 bg-red-50/60 p-4">
      <h2 class="text-[13px] font-bold text-red-800">
        {{ gawat.length }} kontrak sudah berubah jenisnya demi hukum
      </h2>

      <p class="text-[11.5px] text-red-700 mt-1 leading-relaxed">
        Yang tercatat di bawah bukan kelalaian administrasi. Pada tiap barisnya, hubungan kerjanya
        sudah bukan PKWT lagi sejak tanggal yang disebutkan — dengan pesangon dan segala yang
        mengikutinya — dan tidak ada satu pun dokumen di sistem ini yang menyebutkannya.
      </p>

      <ul class="mt-3 space-y-1.5">
        <li v-for="(t, i) in gawat" :key="i" class="text-[11.5px] text-red-800">
          <b>{{ t.pekerja }}</b>
          <span class="text-red-700"> · {{ t.nomor }} — {{ t.pesan }}</span>
          <span class="text-red-600"> ({{ t.dasar }})</span>
        </li>
      </ul>
    </section>

    <section v-if="ingat.length" class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4">
      <h2 class="text-[13px] font-bold text-amber-800">{{ ingat.length }} hal menunggu dibereskan</h2>

      <ul class="mt-2 space-y-1.5">
        <li v-for="(t, i) in ingat" :key="i" class="text-[11.5px] text-amber-800">
          <b>{{ t.pekerja }}</b>
          <span class="text-amber-700"> · {{ t.nomor }} — {{ t.pesan }}</span>
          <span class="text-amber-600"> ({{ t.dasar }})</span>
        </li>
      </ul>
    </section>

    <!-- ── akan berakhir ── -->
    <section v-if="akanBerakhir.length"
             class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
      <h2 class="text-[13px] font-bold">Berakhir dalam 60 hari <span class="text-stone-400 font-normal">| {{ akanBerakhir.length }} kontrak</span></h2>

      <div class="mt-3 flex flex-wrap gap-2">
        <span v-for="k in akanBerakhir" :key="k.id"
              class="rounded-lg px-2.5 py-1.5 text-[11.5px]"
              :class="k.sisa <= 14 ? 'bg-red-50 text-red-700' : 'bg-stone-50 text-stone-600'">
          <b>{{ k.pekerja }}</b> · {{ k.selesai }}
          <span class="num">({{ k.sisa }} hari)</span>
        </span>
      </div>
    </section>

    <!-- ── daftar ── -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-4 pt-4 pb-3">
        <h2 class="text-[13px] font-bold">
          Kontrak <span class="text-stone-400 font-normal">| {{ baris.length }} baris</span>
        </h2>
      </header>

      <div class="overflow-x-auto">
        <table class="w-full text-[12px]">
          <thead class="bg-stone-50/70 text-[11px] text-stone-500">
            <tr>
              <th class="text-left font-medium px-4 py-2.5">Pekerja</th>
              <th class="text-left font-medium px-3 py-2.5">Nomor</th>
              <th class="text-left font-medium px-3 py-2.5">Jenis</th>
              <th class="text-left font-medium px-3 py-2.5">Rentang</th>
              <th class="text-right font-medium px-3 py-2.5">Masa</th>
              <th class="text-right font-medium px-3 py-2.5">Rangkaian</th>
              <th class="text-right font-medium px-3 py-2.5">Kompensasi</th>
              <th class="text-left font-medium px-3 py-2.5">Status</th>
              <th class="px-4 py-2.5"></th>
            </tr>
          </thead>

          <tbody>
            <template v-for="k in baris" :key="k.id">
              <tr class="border-t border-stone-100"
                  :class="temuanBaris(k.id).some((t) => t.berat === 'gawat') ? 'bg-red-50/40' : null">
                <td class="px-4 py-2.5">
                  <div class="font-semibold">{{ k.pekerja }}</div>
                  <div class="text-[10.5px] text-stone-500">{{ k.jabatan ?? '—' }}</div>
                </td>

                <td class="px-3 py-2.5">
                  <div class="num">{{ k.nomor }}</div>
                  <div v-if="k.induk" class="text-[10.5px] text-stone-500">
                    perpanjangan ke-{{ k.urutan - 1 }} dari {{ k.induk }}
                  </div>
                </td>

                <td class="px-3 py-2.5">
                  <div>{{ JENIS[k.jenis] }}</div>
                  <div v-if="k.alasan" class="text-[10.5px] text-stone-500">{{ ALASAN[k.alasan] }}</div>
                </td>

                <td class="px-3 py-2.5 num text-[11.5px]">
                  {{ k.mulai }}
                  <span class="text-stone-400">–</span>
                  {{ k.selesai ?? 'tanpa batas' }}
                </td>

                <td class="px-3 py-2.5 text-right num">{{ bulanTeks(k.bulan) }}</td>

                <td class="px-3 py-2.5 text-right num"
                    :class="k.bulanRantai !== null && k.bulanRantai > acuan.maks_bulan ? 'text-red-700 font-bold' : null">
                  {{ bulanTeks(k.bulanRantai) }}
                </td>

                <td class="px-3 py-2.5 text-right">
                  <div v-if="k.kompensasi !== null" class="num">{{ rupiah(k.kompensasi) }}</div>
                  <div v-else class="text-[10.5px] text-stone-400">{{ k.kompensasiAlasan ?? '—' }}</div>
                  <div v-if="k.dibayar" class="text-[10.5px] text-emerald-700">dibayar {{ k.dibayar }}</div>
                </td>

                <td class="px-3 py-2.5">
                  <span class="kn-lencana" :class="'kn-' + k.status">{{ STATUS[k.status] }}</span>
                </td>

                <td class="px-4 py-2.5 text-right whitespace-nowrap">
                  <button type="button" class="text-[11px] text-stone-500 hover:text-stone-800"
                          @click="sedangPilih = sedangPilih === k.id ? null : k.id">
                    {{ sedangPilih === k.id ? 'Tutup' : 'Tindakan' }}
                  </button>
                </td>
              </tr>

              <tr v-if="sedangPilih === k.id" class="border-t border-stone-100 bg-stone-50/60">
                <td colspan="9" class="px-4 py-3">
                  <div class="flex flex-wrap items-center gap-2">
                    <button v-if="k.status === 'draft'" type="button" class="eq-btn-utama"
                            @click="terbitkan(k)">Terbitkan</button>

                    <button v-if="k.status === 'berjalan' && k.jenis !== 'pkwtt'" type="button"
                            class="eq-btn-lain" @click="perpanjang(k)">Perpanjang</button>

                    <button v-if="k.status === 'berjalan'" type="button" class="eq-btn-lain"
                            @click="akhiri(k, 'selesai')">Akhiri</button>

                    <button v-if="k.status === 'berjalan'" type="button" class="eq-btn-lain"
                            @click="akhiri(k, 'diputus')">Putus di tengah</button>

                    <button v-if="k.status === 'berjalan' && k.jenis !== 'pkwtt'" type="button"
                            class="eq-btn-lain" @click="jadikanPkwtt(k)">Catat jadi PKWTT</button>

                    <a v-if="k.berkas" :href="k.berkas" target="_blank" rel="noopener"
                       class="text-[11.5px] text-sky-700 hover:underline">Buka naskah</a>

                    <span v-if="k.ditandatangani" class="text-[11px] text-stone-500">
                      Ditandatangani {{ k.ditandatangani }}<span v-if="k.dicatatkan">, dicatatkan {{ k.dicatatkan }}</span>
                    </span>
                  </div>

                  <ul v-if="temuanBaris(k.id).length" class="mt-2.5 space-y-1">
                    <li v-for="(t, i) in temuanBaris(k.id)" :key="i"
                        class="text-[11.5px]"
                        :class="t.berat === 'gawat' ? 'text-red-700' : 'text-amber-700'">
                      {{ t.pesan }} <span class="text-stone-500">({{ t.dasar }})</span>
                    </li>
                  </ul>
                </td>
              </tr>
            </template>

            <tr v-if="!baris.length">
              <td colspan="9" class="px-4 py-10 text-center text-[12px] text-stone-400">
                Belum ada kontrak tercatat.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- ── kontrak baru ── -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
      <h2 class="text-[13px] font-bold">Kontrak baru</h2>

      <p class="text-[11.5px] text-stone-500 mt-1 leading-relaxed">
        Tercatat sebagai draf lebih dulu. Yang membuatnya mengikat adalah penerbitan, dan pada saat
        itulah tumpang tindih dengan kontrak lain yang masih berjalan diperiksa.
      </p>

      <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <label class="block lg:col-span-2">
          <span class="block text-[11px] text-stone-500">Pekerja</span>
          <select v-model="form.pekerja_id" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option value="">—</option>
            <option v-for="p in daftarPekerja" :key="p.id" :value="p.id">{{ p.label }}</option>
          </select>
          <span v-if="form.errors.pekerja_id" class="block text-[11px] text-red-600">{{ form.errors.pekerja_id }}</span>
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Nomor kontrak</span>
          <input v-model="form.nomor" type="text" placeholder="PKWT/2026/001"
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
          <span v-if="form.errors.nomor" class="block text-[11px] text-red-600">{{ form.errors.nomor }}</span>
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Jenis</span>
          <select v-model="form.jenis" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option v-for="(label, kunci) in JENIS" :key="kunci" :value="kunci">{{ label }}</option>
          </select>
        </label>

        <label v-if="perluAlasan" class="block lg:col-span-2">
          <span class="block text-[11px] text-stone-500">Alasan — PP 35/2021 pasal 5</span>
          <select v-model="form.alasan" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option v-for="(label, kunci) in ALASAN" :key="kunci" :value="kunci">{{ label }}</option>
          </select>
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Mulai</span>
          <input v-model="form.mulai" type="date" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
          <span v-if="form.errors.mulai" class="block text-[11px] text-red-600">{{ form.errors.mulai }}</span>
        </label>

        <label v-if="perluSelesai" class="block">
          <span class="block text-[11px] text-stone-500">Selesai</span>
          <input v-model="form.selesai" type="date" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
          <span v-if="form.errors.selesai" class="block text-[11px] text-red-600">{{ form.errors.selesai }}</span>
        </label>

        <label v-if="perluBatasan" class="block sm:col-span-2 lg:col-span-4">
          <span class="block text-[11px] text-stone-500">
            Batasan pekerjaan dinyatakan selesai — wajib, pasal 10 ayat (1)
          </span>
          <input v-model="form.batasan_selesai" type="text"
                 placeholder="Pekerjaan selesai saat seluruh pit 3 selesai direklamasi dan diserahterimakan."
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Ditandatangani</span>
          <input v-model="form.ditandatangani_pada" type="date"
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Masa percobaan (hari)</span>
          <input v-model="form.masa_percobaan_hari" type="number" min="0" max="365"
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block lg:col-span-2">
          <span class="block text-[11px] text-stone-500">Naskah kontrak</span>
          <input type="file" class="mt-1 w-full text-[12px]"
                 @change="form.berkas = ($event.target as HTMLInputElement).files?.[0] ?? null">
          <span v-if="form.errors.berkas" class="block text-[11px] text-red-600">{{ form.errors.berkas }}</span>
        </label>

        <label class="block sm:col-span-2 lg:col-span-4">
          <span class="block text-[11px] text-stone-500">Catatan</span>
          <input v-model="form.catatan" type="text"
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>
      </div>

      <button type="button" class="eq-btn-utama mt-4" :disabled="form.processing" @click="simpan">
        Simpan draf
      </button>
    </section>

    <!-- ── dasar ── -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
      <h2 class="text-[13px] font-bold">Dasar pemeriksaan</h2>

      <ul class="mt-2 space-y-1 text-[11.5px] text-stone-600 list-disc pl-4">
        <li>
          Jangka waktu keseluruhan PKWT beserta perpanjangannya paling lama
          {{ acuan.maks_bulan }} bulan — pasal 8. Yang dihitung seluruh rangkaiannya, bukan tiap
          kontrak sendiri-sendiri.
        </li>
        <li>PKWT jangka waktu wajib berdasar salah satu alasan pasal 5.</li>
        <li>PKWT selesainya pekerjaan wajib menyebut batasan selesainya — pasal 10 ayat (1).</li>
        <li>PKWT tidak boleh mensyaratkan masa percobaan; syaratnya batal demi hukum — pasal 12.</li>
        <li>
          PKWT harian: kurang dari {{ acuan.harian_hari }} hari sebulan. Bekerja
          {{ acuan.harian_hari }} hari atau lebih selama {{ acuan.harian_bulan }} bulan
          berturut-turut membuatnya berubah menjadi PKWTT — pasal 10 ayat (2) dan (3). Dihitung dari
          absensi yang benar-benar tercatat, bukan dari roster.
        </li>
        <li>Pencatatan ke instansi ketenagakerjaan paling lama {{ acuan.catat_hari }} hari kerja — pasal 14.</li>
        <li>
          Uang kompensasi: masa kerja paling sedikit {{ acuan.min_bulan }} bulan, sebesar
          (masa kerja ÷ 12) × upah pokok dan tunjangan tetap — pasal 15 sampai 17. Diputus di tengah,
          yang dihitung adalah masa yang sudah dijalani.
        </li>
        <li>Masa percobaan PKWTT paling lama {{ acuan.percobaan }} hari — UU 13/2003 pasal 60.</li>
      </ul>
    </section>
  </div>

  <Dialog :model="dialog" @batal="batal" @lanjut="lanjut" />
</template>

<style>
.kn-lencana {
  display: inline-block; border-radius: 9999px; padding: 0 .4rem;
  font-size: 10px; font-weight: 600; white-space: nowrap;
}

.kn-draft      { background: #F5F5F4; color: #57534E; }
.kn-berjalan   { background: #D1FAE5; color: #065F46; }
.kn-selesai    { background: #E7E5E4; color: #44403C; }
.kn-diputus    { background: #FEE2E2; color: #991B1B; }
.kn-jadi_pkwtt { background: #DBEAFE; color: #1E3A5F; }

:root[data-tema="gelap"] .kn-draft      { background: #1C262B; color: #A8B2B8; }
:root[data-tema="gelap"] .kn-berjalan   { background: #143A2C; color: #8FE3BE; }
:root[data-tema="gelap"] .kn-selesai    { background: #232F35; color: #C7D0D5; }
:root[data-tema="gelap"] .kn-diputus    { background: #4E1D1D; color: #F5A9A9; }
:root[data-tema="gelap"] .kn-jadi_pkwtt { background: #1E3F5E; color: #A8CDF0; }
</style>

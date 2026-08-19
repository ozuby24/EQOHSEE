<script setup lang="ts">
/**
 * Daftar acuan jenis unit SPIP.
 *
 * Gunanya menyeragamkan penulisan. Tanpa daftar ini "Dump Truck",
 * "Dumptruck", dan "DT" adalah tiga jenis berbeda di mata sistem: rekap
 * per jenis tidak dapat dipercaya dan penyaringan kehilangan sebagian
 * barisnya — tanpa galat, hanya angka yang salah.
 *
 * Jumlah unit yang belum tertaut diletakkan di ATAS daftar, bukan di
 * bawah. Daftar acuan yang rapi tetapi tidak dipakai satu unit pun
 * adalah pekerjaan yang terlihat selesai dan tidak mengubah apa-apa;
 * angka itulah satu-satunya ukuran bahwa penyeragamannya berjalan.
 */
import { computed, reactive } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import { KEADAAN } from '../../Grafik/warna';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
const { dialog, tanya, batal, lanjut } = useDialog();


const props = propHalaman();

const unit = computed(() => props.unit ?? []);
const buka = reactive({ form: false });

const f = useForm<Record<string, any>>({
  kode: '', unit: '', kategori: 'Sarana', interval_tahun: 1, keterangan: '', urutan: 0,
});

function simpan() {
  f.post('/ko/unit', { preserveScroll: true, onSuccess: () => { f.reset(); buka.form = false; } });
}

/**
 * Menghapus jenis yang masih dipakai justru menonaktifkannya di server.
 * Kalimat konfirmasinya mengatakan itu di muka, supaya hasilnya tidak
 * mengejutkan orang yang mengira barisnya benar-benar hilang.
 */
async function hapus(u: any) {
  const pesan = u.dipakai
    ? `"${u.unit}" masih dipakai ${u.dipakai} unit, jadi akan dinonaktifkan — bukan dihapus. Lanjutkan?`
    : `Hapus jenis "${u.unit}"?`;

  if (await tanya(pesan)) router.delete(`/ko/unit/${u.id}`, { preserveScroll: true });
}

function ubahAktif(u: any) {
  router.put(`/ko/unit/${u.id}`, {
    kode: u.kode, unit: u.unit, kategori: u.kategori,
    interval_tahun: u.interval, keterangan: u.keterangan,
    urutan: u.urutan, aktif: !u.aktif,
  }, { preserveScroll: true });
}
</script>

<template>
  <Head title="Jenis Unit SPIP" />

  <div class="max-w-[1400px] mx-auto space-y-5">

    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-cam-ink">Jenis Unit SPIP</h2>
        <p class="text-[12.5px] text-stone-500 mt-1">
          Daftar acuan yang dipelihara sendiri — bukan daftar tetap dari peraturan.
          Menyeragamkan penulisan jenis supaya rekap dan penyaringan dapat dipercaya.
        </p>
      </div>
      <div class="flex gap-2">
        <Link href="/ko/kelayakan" class="eq-btn-lain">Kelayakan</Link>
        <Link href="/ko/uji" class="eq-btn-lain">Uji Kelayakan</Link>
        <button v-if="props.bolehUbah" type="button" class="eq-btn-utama"
                @click="buka.form = !buka.form">
          {{ buka.form ? 'Batal' : 'Tambah jenis' }}
        </button>
      </div>
    </section>

    <!-- ukuran bahwa penyeragamannya berjalan -->
    <section v-if="props.belumTertaut"
             class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 flex items-center gap-4">
      <span class="text-[26px] font-bold leading-none num" :style="{ color: KEADAAN.ingat }">
        {{ props.belumTertaut }}
      </span>
      <p class="text-[12px] text-stone-600">
        unit masih memakai jenis yang diketik bebas, belum tertaut ke daftar ini.
        <Link href="/ko/register" class="text-cam-lime-deep font-semibold">Buka register</Link>
        untuk menautkannya lewat formulir ubah objek.
      </p>
    </section>

    <form v-if="buka.form"
          class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 grid gap-3 md:grid-cols-5"
          @submit.prevent="simpan">
      <input v-model="f.kode" required placeholder="Kode (mis. DT)"
             class="rounded-lg border-stone-200 text-[12px]">
      <input v-model="f.unit" required placeholder="Nama jenis unit"
             class="rounded-lg border-stone-200 text-[12px] md:col-span-2">
      <select v-model="f.kategori" class="rounded-lg border-stone-200 text-[12px]" aria-label="Kategori">
        <option v-for="k in ['Sarana','Prasarana','Instalasi','Peralatan']" :key="k">{{ k }}</option>
      </select>
      <input v-model="f.interval_tahun" type="number" min="1" max="20" title="Interval uji (tahun)"
             class="rounded-lg border-stone-200 text-[12px]">
      <input v-model="f.keterangan" placeholder="Keterangan"
             class="rounded-lg border-stone-200 text-[12px] md:col-span-4">
      <button class="eq-btn-utama" :disabled="f.processing">Simpan</button>

      <p v-for="(e, k) in f.errors" :key="k" class="text-[11px] text-red-600 md:col-span-5">{{ e }}</p>
    </form>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[12px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-100">
              <th class="px-5 py-3">Kode</th><th class="px-5 py-3">Jenis unit</th>
              <th class="px-5 py-3">Kategori</th><th class="px-5 py-3">Interval uji</th>
              <th class="px-5 py-3">Dipakai</th><th class="px-5 py-3">Keadaan</th>
              <th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="u in unit" :key="u.id" class="border-b border-stone-50"
                :class="{ 'opacity-50': !u.aktif }">
              <td class="px-5 py-3 font-semibold">{{ u.kode }}</td>
              <td class="px-5 py-3">
                {{ u.unit }}
                <!-- Baris milik bersama tidak dapat disunting satu
                     perusahaan; dikatakan, bukan hanya tombolnya
                     disembunyikan. -->
                <small v-if="u.bersama" class="block text-[10px] text-stone-400">
                  acuan bersama
                </small>
              </td>
              <td class="px-5 py-3">{{ u.kategori || '—' }}</td>
              <td class="px-5 py-3 num">{{ u.interval }} tahun</td>
              <td class="px-5 py-3 num">{{ u.dipakai }}</td>
              <td class="px-5 py-3">
                <span :style="{ color: u.aktif ? KEADAAN.baik : KEADAAN.netral }" class="font-semibold">
                  {{ u.aktif ? 'Aktif' : 'Nonaktif' }}
                </span>
              </td>
              <td class="px-5 py-3 text-right whitespace-nowrap">
                <template v-if="props.bolehUbah && !u.bersama">
                  <button type="button" class="text-[11px] text-cam-lime-deep"
                          @click="ubahAktif(u)">{{ u.aktif ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                  <button type="button" class="text-[11px] text-red-600 ml-3"
                          @click="hapus(u)">Hapus</button>
                </template>
              </td>
            </tr>
            <tr v-if="!unit.length">
              <td colspan="7" class="px-5 py-10 text-center text-stone-400">
                Belum ada jenis unit. Muat data contoh atau tambahkan sendiri.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>

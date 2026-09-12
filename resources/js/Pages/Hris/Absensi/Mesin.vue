<script setup lang="ts">
/**
 * Mesin absensi lapangan.
 *
 * TOKEN HANYA TAMPIL SEKALI, dan halaman ini mengatakannya demikian
 * sebelum menampilkannya. Yang tersimpan di basis data hanyalah
 * hash-nya — tidak ada cara membacanya kembali, dan memang itu
 * maksudnya: alat yang dicabut dari dinding pos jaga membawa tokennya,
 * dan yang tersimpan tidak boleh dapat dibaca balik oleh siapa pun
 * yang membuka basis datanya.
 */
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { propHalaman } from '../../../halaman';
import KopHalaman from '../../../Components/KopHalaman.vue';
import Dialog from '../../../Components/Dialog.vue';
import { useDialog } from '../../../dialog';

const props = propHalaman();
const page  = usePage();
const { dialog, tanya, batal, lanjut } = useDialog();

const mesin = computed<any[]>(() => (props.mesin ?? []) as any[]);

/* Token yang baru terbit datang lewat flash, bukan lewat prop halaman:
   ia tidak boleh ikut terkirim lagi pada muat ulang berikutnya. */
const token = computed<any>(() => (page.props as any)?.flash?.token ?? null);

const form = useForm({ nama: '', merek: 'zkteco', nomor_seri: '', ip: '', blok_id: '', aktif: true });

function simpan() {
  form.post('/hris/absensi/mesin', { preserveScroll: true, onSuccess: () => form.reset() });
}

const ubahId = ref<number | null>(null);
const ubah   = useForm({ nama: '', merek: 'zkteco', nomor_seri: '', ip: '', blok_id: '', aktif: true });

function bukaUbah(m: any) {
  ubahId.value   = m.id;
  ubah.nama      = m.nama ?? '';
  ubah.merek     = m.merek ?? 'zkteco';
  ubah.nomor_seri= m.nomor_seri ?? '';
  ubah.ip        = m.ip ?? '';
  ubah.blok_id   = m.blok_id ? String(m.blok_id) : '';
  ubah.aktif     = !!m.aktif;
  ubah.clearErrors();
}

function simpanUbah() {
  if (ubahId.value === null) return;

  ubah.put(`/hris/absensi/mesin/${ubahId.value}`, {
    preserveScroll: true,
    onSuccess: () => { ubahId.value = null; },
  });
}

async function terbitkan(m: any) {
  if (!await tanya({
    judul: `Terbitkan token baru untuk ${m.nama}?`,
    pesan: 'Token lamanya berhenti berlaku seketika. Alat yang masih memakainya akan berhenti mengirim sampai token barunya dipasang.',
    labelAksi: 'Terbitkan',
  })) return;

  useForm({}).post(`/hris/absensi/mesin/${m.id}/token`, { preserveScroll: true });
}

async function hapus(m: any) {
  if (!await tanya({
    judul: `Hapus mesin ${m.nama}?`,
    pesan: 'Jejak pindaian yang sudah masuk tetap tersimpan.',
    nada: 'bahaya',
    labelAksi: 'Hapus',
  })) return;

  useForm({}).delete(`/hris/absensi/mesin/${m.id}`, { preserveScroll: true });
}

const contoh = computed(() => `POST /api/v1/absensi
X-Mesin-Seri: <nomor seri>
Authorization: Bearer <token>

{"jejak":[{"kunci":"POS1-000123","pekerja":"REG-0001",
  "terjadi":"2026-09-12T07:02:00+08:00","arah":"masuk"}]}`);
</script>

<template>
  <Head :title="props.judul" />
  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />

  <div class="max-w-[1100px] mx-auto space-y-5">
    <KopHalaman :judul="props.judul as string" :subjudul="props.subjudul as string"
                tagline="Always Connected"
                :remah="[['HRIS', '/hris'], ['Absensi', null], ['Mesin Lapangan', null]]" ringkas />

    <section class="-mt-2 flex flex-wrap items-end justify-end gap-3">

      <Link href="/hris/absensi" class="eq-btn-lain">Pemantauan harian</Link>
    </section>

    <section v-if="token" class="rounded-2xl bg-amber-50 border border-amber-200 p-4">
      <h3 class="text-[13px] font-bold text-amber-800">Token {{ token.mesin }} — salin sekarang</h3>
      <p class="text-[11.5px] text-amber-800 mt-0.5">
        Hanya ditampilkan kali ini. Yang tersimpan di basis data hanyalah hash-nya, dan hash
        tidak dapat dibaca balik menjadi token.
      </p>
      <code class="mt-2 block break-all rounded-lg bg-white border border-amber-200 px-3 py-2 text-[12px] text-cam-ink">{{ token.nilai }}</code>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[11.5px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
              <th class="px-4 py-2 font-semibold">Mesin</th>
              <th class="px-4 py-2 font-semibold">Merek</th>
              <th class="px-4 py-2 font-semibold">Nomor seri</th>
              <th class="px-4 py-2 font-semibold">Area</th>
              <th class="px-4 py-2 font-semibold">Token</th>
              <th class="px-4 py-2 font-semibold">Terakhir terhubung</th>
              <th class="px-4 py-2 font-semibold text-right">Jejak</th>
              <th class="px-4 py-2 font-semibold"></th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="m in mesin" :key="m.id" class="border-b border-stone-100" :class="m.aktif ? '' : 'opacity-60'">
              <td class="px-4 py-2.5">
                <div class="font-semibold text-cam-ink">{{ m.nama }}</div>
                <div v-if="m.ip" class="text-[10.5px] text-stone-400">{{ m.ip }}</div>
              </td>
              <td class="px-4 py-2.5 text-stone-600">{{ props.MEREK?.[m.merek] ?? m.merek }}</td>
              <td class="px-4 py-2.5 num text-stone-600">{{ m.nomor_seri || '—' }}</td>
              <td class="px-4 py-2.5 text-stone-600">{{ m.blok || '—' }}</td>
              <td class="px-4 py-2.5">
                <span v-if="m.bertoken" class="text-emerald-700">terbit</span>
                <span v-else class="text-red-600">belum ada</span>
                <span v-if="!m.aktif" class="text-stone-400"> · nonaktif</span>
              </td>
              <td class="px-4 py-2.5 num text-stone-500">{{ m.terakhir || 'belum pernah' }}</td>
              <td class="px-4 py-2.5 num text-right">{{ m.jejak }}</td>
              <td class="px-4 py-2.5 text-right whitespace-nowrap">
                <button type="button" class="text-[11px] text-sky-700 hover:underline" @click="bukaUbah(m)">Ubah</button>
                <button type="button" class="text-[11px] text-sky-700 hover:underline ml-3" @click="terbitkan(m)">Token</button>
                <button type="button" class="text-[11px] text-red-600 hover:underline ml-3" @click="hapus(m)">Hapus</button>
              </td>
            </tr>

            <tr v-if="!mesin.length">
              <td colspan="8" class="px-4 py-10 text-center text-stone-400">
                Belum ada mesin terdaftar.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section v-if="ubahId !== null" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="text-[13.5px] font-bold text-cam-ink mb-3">Ubah mesin</h3>

      <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="simpanUbah">
        <label class="block">
          <span class="block text-[11px] text-stone-500">Nama</span>
          <input v-model="ubah.nama" type="text" required class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Merek</span>
          <select v-model="ubah.merek" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option v-for="(label, kode) in (props.MEREK ?? {})" :key="kode" :value="kode">{{ label }}</option>
          </select>
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Nomor seri</span>
          <input v-model="ubah.nomor_seri" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
          <span v-if="ubah.errors.nomor_seri" class="block text-[11px] text-red-600">{{ ubah.errors.nomor_seri }}</span>
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Alamat IP</span>
          <input v-model="ubah.ip" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Area</span>
          <select v-model="ubah.blok_id" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option value="">—</option>
            <option v-for="(nama, id) in (props.blok ?? {})" :key="id" :value="id">{{ nama }}</option>
          </select>
        </label>

        <label class="flex items-center gap-2 sm:col-span-2">
          <input v-model="ubah.aktif" type="checkbox" class="rounded border-stone-300">
          <span class="text-[11.5px] text-stone-600">Aktif — mesin nonaktif ditolak seperti mesin yang tidak terdaftar</span>
        </label>

        <div class="sm:col-span-2 lg:col-span-5 flex gap-2">
          <button type="submit" class="eq-btn-utama" :disabled="ubah.processing">Simpan</button>
          <button type="button" class="eq-btn-lain" @click="ubahId = null">Batal</button>
        </div>
      </form>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="text-[13.5px] font-bold text-cam-ink mb-3">Tambah mesin</h3>

      <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="simpan">
        <label class="block">
          <span class="block text-[11px] text-stone-500">Nama</span>
          <input v-model="form.nama" type="text" required placeholder="Pos Jaga 1"
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Merek</span>
          <select v-model="form.merek" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option v-for="(label, kode) in (props.MEREK ?? {})" :key="kode" :value="kode">{{ label }}</option>
          </select>
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Nomor seri</span>
          <input v-model="form.nomor_seri" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
          <span v-if="form.errors.nomor_seri" class="block text-[11px] text-red-600">{{ form.errors.nomor_seri }}</span>
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Alamat IP</span>
          <input v-model="form.ip" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Area</span>
          <select v-model="form.blok_id" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option value="">—</option>
            <option v-for="(nama, id) in (props.blok ?? {})" :key="id" :value="id">{{ nama }}</option>
          </select>
        </label>

        <div class="sm:col-span-2 lg:col-span-5">
          <button type="submit" class="eq-btn-utama" :disabled="form.processing">Simpan</button>
        </div>
      </form>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="text-[13.5px] font-bold text-cam-ink mb-1">Cara alat mengirim</h3>
      <p class="text-[11.5px] text-stone-500 mb-3">
        Nomor seri menyebut siapa, token membuktikannya. Tiap peristiwa membawa
        <code class="text-cam-ink">kunci</code> buatan sisi pengirim: batch yang sama dikirim
        berkali-kali sampai satu kali berhasil, dan yang kedua tidak menambah apa pun.
        Paling banyak {{ props.MAKS_BATCH }} peristiwa sekali kirim.
      </p>

      <pre class="overflow-x-auto rounded-lg bg-stone-50 border border-stone-200 px-3 py-2 text-[11px] text-stone-700">{{ contoh }}</pre>
    </section>
  </div>
</template>

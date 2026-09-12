<script setup lang="ts">
/**
 * Pemantauan absensi harian.
 *
 * YANG DITAMPILKAN ADALAH GABUNGAN roster dan absensi, bukan salah
 * satunya. Diambil dari absensi saja, orang yang tidak datang tidak
 * muncul sama sekali dan layar ketidakhadiran menjadi layar kosong
 * yang terlihat seperti kabar baik. Diambil dari roster saja, orang
 * yang menempelkan kartunya pada hari liburnya tidak muncul di mana
 * pun — padahal justru itu yang perlu dilihat.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, h, ref } from 'vue';
import { propHalaman } from '../../../halaman';
import UbinAngka from '../../../Components/UbinAngka.vue';
import Keadaan from './Keadaan.vue';

const props = propHalaman();

const baris   = computed<any[]>(() => (props.baris ?? []) as any[]);
const ringkas = computed<any>(() => props.ringkas ?? {});

const tanggal = ref(String(props.tanggal ?? ''));
const blok    = ref(String(props.terpilihBlok ?? ''));

function muat() {
  router.get('/hris/absensi', {
    tanggal: tanggal.value || undefined,
    blok: blok.value || undefined,
  }, { preserveState: true, preserveScroll: true, replace: true });
}

/* ── rekonsiliasi ulang satu hari ── */

const ulang = useForm({});

function rekonsiliasi() {
  ulang.post(`/hris/absensi/rekonsiliasi?dari=${tanggal.value}&sampai=${tanggal.value}`,
    { preserveScroll: true });
}

/* ── catat pindaian manual ── */

const catat = useForm({ pekerja_id: '', tanggal: String(props.tanggal ?? ''), jam: '', arah: 'masuk', catatan: '' });

function simpanCatat() {
  catat.post('/hris/absensi/catat', {
    preserveScroll: true,
    onSuccess: () => catat.reset('pekerja_id', 'jam', 'catatan'),
  });
}

/* ── koreksi jam ── */

const koreksiId = ref<number | null>(null);
const koreksi   = useForm({ masuk: '', keluar: '', alasan: '' });

function bukaKoreksi(b: any) {
  koreksiId.value = b.id;
  koreksi.masuk   = b.masuk ?? '';
  koreksi.keluar  = b.keluar ?? '';
  koreksi.alasan  = '';
  koreksi.clearErrors();
}

function simpanKoreksi() {
  if (koreksiId.value === null) return;

  koreksi.put(`/hris/absensi/${koreksiId.value}`, {
    preserveScroll: true,
    onSuccess: () => { koreksiId.value = null; koreksi.reset(); },
  });
}

/**
 * Keterlambatan ditulis apa adanya, dan nol ditulis sebagai "tepat".
 *
 * Dibiarkan kosong, "tepat waktu" tidak dapat dibedakan dari "belum
 * ada datanya" — dan keduanya berarti tindakan yang berlawanan.
 */
function telatTeks(m: number | null) {
  if (m === null || m === undefined) return '—';
  if (m === 0) return 'tepat';
  return `${m} mnt`;
}

/**
 * Ikon ubin, digambar sebagai path inline.
 *
 * Bukan dari pustaka ikon: repo ini sudah sekali melepas pustaka dari
 * CDN, dan ikon yang gagal dimuat pada jaringan site tambang
 * meninggalkan kotak kosong di tempat angka yang seharusnya terbaca.
 */
const IKON: Record<string, string[]> = {
  jadwal: ['M8 3v3m8-3v3M3.5 9.5h17M5 5.5h14a1.5 1.5 0 0 1 1.5 1.5v12A1.5 1.5 0 0 1 19 20.5H5A1.5 1.5 0 0 1 3.5 19V7A1.5 1.5 0 0 1 5 5.5Z'],
  hadir:  ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'm8.5 12.2 2.4 2.4 4.6-4.9'],
  telat:  ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'M12 7.5v5l3.2 1.9'],
  belum:  ['M3.5 12h6l2-3 2.5 6 2-3h4.5'],
  absen:  ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'm9 9 6 6M15 9l-6 6'],
  luar:   ['M16 20v-1.5a4 4 0 0 0-8 0V20', 'M12 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z', 'M19 7.5 21 5M21 9.5 19 12'],
  area:   ['M12 21s7-5.4 7-11a7 7 0 1 0-14 0c0 5.6 7 11 7 11Z', 'M12 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z'],
};

const Ikon = (p: { nama: string }) => h('svg', {
  viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 1.9,
  'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'aria-hidden': 'true',
}, (IKON[p.nama] ?? []).map((d) => h('path', { d })));

const kartu = computed(() => {
  const n = ringkas.value.dijadwalkan ?? 0;

  return [
    { kunci: 'dijadwalkan', label: 'Dijadwalkan kerja', nada: 'serius', ikon: 'jadwal', dari: null },
    { kunci: 'hadir',       label: 'Hadir',             nada: 'baik',   ikon: 'hadir',  dari: n },
    { kunci: 'terlambat',   label: 'Terlambat',         nada: 'ingat',  ikon: 'telat',  dari: n },
    { kunci: 'belum_pulang',label: 'Belum tap pulang',  nada: 'serius', ikon: 'belum',  dari: n },
    { kunci: 'absen',       label: 'Absen',             nada: 'gawat',  ikon: 'absen',  dari: n },
    { kunci: 'luar_roster', label: 'Di luar roster',    nada: 'luar',   ikon: 'luar',   dari: null },
    { kunci: 'luar_area',   label: 'Di luar geofence',  nada: 'gawat',  ikon: 'area',   dari: null },
  ] as const;
});
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1280px] mx-auto space-y-5">

    <section class="-mt-2 flex flex-wrap items-end justify-end gap-3">

      <div class="flex gap-2">
        <Link href="/hris/absensi/rekap" class="eq-btn-lain">Rekap periode</Link>
        <Link href="/hris/absensi/mesin" class="eq-btn-lain">Mesin lapangan</Link>
      </div>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
      <div class="flex flex-wrap items-end gap-3">
        <label class="block">
          <span class="block text-[11px] text-stone-500">Tanggal</span>
          <input v-model="tanggal" type="date" class="mt-1 rounded-lg border-stone-200 text-[12px]" @change="muat">
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Area</span>
          <select v-model="blok" class="mt-1 rounded-lg border-stone-200 text-[12px]" @change="muat">
            <option value="">Semua area</option>
            <option v-for="(nama, id) in (props.blok ?? {})" :key="id" :value="id">{{ nama }}</option>
          </select>
        </label>

        <button type="button" class="eq-btn-lain" :disabled="ulang.processing" @click="rekonsiliasi">
          Rekonsiliasi ulang hari ini
        </button>
      </div>

      <div class="mt-4 grid gap-2.5 grid-cols-2 sm:grid-cols-4 lg:grid-cols-7">
        <UbinAngka v-for="k in kartu" :key="k.kunci"
                   :angka="ringkas[k.kunci] ?? 0" :label="k.label" :nada="k.nada" :dari="k.dari">
          <template #ikon><Ikon :nama="k.ikon" /></template>
        </UbinAngka>
      </div>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[11.5px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
              <th class="px-4 py-2 font-semibold">Pekerja</th>
              <th class="px-4 py-2 font-semibold">Area</th>
              <th class="px-4 py-2 font-semibold">Roster</th>
              <th class="px-4 py-2 font-semibold">Jadwal</th>
              <th class="px-4 py-2 font-semibold">Masuk</th>
              <th class="px-4 py-2 font-semibold">Keluar</th>
              <th class="px-4 py-2 font-semibold text-right">Jam</th>
              <th class="px-4 py-2 font-semibold text-right">Telat</th>
              <th class="px-4 py-2 font-semibold">Keadaan</th>
              <th class="px-4 py-2 font-semibold">Catatan</th>
              <th class="px-4 py-2 font-semibold"></th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="(b, i) in baris" :key="i" class="border-b border-stone-100">
              <td class="px-4 py-2.5">
                <div class="font-semibold text-cam-ink">{{ b.pekerja || '—' }}</div>
                <div class="text-[10.5px] text-stone-400">{{ b.nomor }}<span v-if="b.jabatan"> · {{ b.jabatan }}</span></div>
              </td>
              <td class="px-4 py-2.5 text-stone-600">{{ b.blok || '—' }}</td>
              <td class="px-4 py-2.5 text-stone-600">
                {{ b.roster ? (props.KEADAAN_ROSTER?.[b.roster] ?? b.roster) : '—' }}
                <span v-if="b.shift" class="text-stone-400">· {{ props.SHIFT?.[b.shift] ?? b.shift }}</span>
              </td>
              <td class="px-4 py-2.5 num text-stone-500">{{ b.jadwal || '—' }}</td>
              <td class="px-4 py-2.5 num" :class="b.masuk ? 'text-cam-ink' : 'text-stone-300'">{{ b.masuk || '—' }}</td>
              <td class="px-4 py-2.5 num" :class="b.keluar ? 'text-cam-ink' : 'text-stone-300'">{{ b.keluar || '—' }}</td>
              <td class="px-4 py-2.5 num text-right">{{ b.jam ? b.jam.toFixed(2) : '—' }}</td>
              <td class="px-4 py-2.5 num text-right" :class="(b.telat ?? 0) > 0 ? 'text-amber-700 font-semibold' : 'text-stone-400'">
                {{ telatTeks(b.telat) }}
              </td>
              <td class="px-4 py-2.5">
                <Keadaan :keadaan="b.keadaan" :label="props.KEADAAN?.[b.keadaan]" />
              </td>
              <td class="px-4 py-2.5 text-[10.5px] text-stone-500 space-y-0.5">
                <div v-if="b.area === false" class="text-red-600">di luar geofence</div>
                <div v-if="b.luring">kiriman luring</div>
                <div v-if="b.dikoreksi">dikoreksi manual</div>
                <div v-if="b.halangan" class="text-red-600">terhalang {{ b.halangan }}</div>
                <div v-if="b.sumber" class="text-stone-400">{{ b.sumber }}</div>
              </td>
              <td class="px-4 py-2.5 text-right">
                <button v-if="b.id" type="button" class="text-[11px] text-sky-700 hover:underline"
                        @click="bukaKoreksi(b)">Koreksi</button>
              </td>
            </tr>

            <tr v-if="!baris.length">
              <td colspan="11" class="px-4 py-10 text-center text-stone-400">
                Tidak ada roster maupun pindaian pada tanggal ini.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section v-if="koreksiId !== null" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="text-[13.5px] font-bold text-cam-ink mb-1">Koreksi jam</h3>
      <p class="text-[11.5px] text-stone-500 mb-3">
        Jejak pindaian aslinya tidak berubah. Baris ini ditandai sudah dikoreksi, sehingga
        rekonsiliasi berikutnya tidak menimpanya kembali.
      </p>

      <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="simpanKoreksi">
        <label class="block">
          <span class="block text-[11px] text-stone-500">Jam masuk</span>
          <input v-model="koreksi.masuk" type="time" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Jam keluar</span>
          <input v-model="koreksi.keluar" type="time" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block lg:col-span-2">
          <span class="block text-[11px] text-stone-500">Alasan koreksi</span>
          <input v-model="koreksi.alasan" type="text" required minlength="5"
                 placeholder="Mesin pos 2 mati; jam dicatat pengawas shift."
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
          <span v-if="koreksi.errors.alasan" class="block text-[11px] text-red-600">{{ koreksi.errors.alasan }}</span>
        </label>

        <div class="sm:col-span-2 lg:col-span-4 flex gap-2">
          <button type="submit" class="eq-btn-utama" :disabled="koreksi.processing">Simpan koreksi</button>
          <button type="button" class="eq-btn-lain" @click="koreksiId = null">Batal</button>
        </div>
      </form>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="text-[13.5px] font-bold text-cam-ink mb-1">Catat pindaian manual</h3>
      <p class="text-[11.5px] text-stone-500 mb-3">
        Tercatat sebagai jejak bersumber “manual”, bukan sebagai pindaian mesin — supaya
        asal tiap angka tetap dapat ditelusuri.
      </p>

      <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="simpanCatat">
        <label class="block lg:col-span-2">
          <span class="block text-[11px] text-stone-500">Pekerja</span>
          <select v-model="catat.pekerja_id" required class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option value="">—</option>
            <option v-for="p in (props.pekerja ?? [])" :key="p.id" :value="p.id">{{ p.nama }} · {{ p.nik }}</option>
          </select>
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Tanggal</span>
          <input v-model="catat.tanggal" type="date" required class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Jam</span>
          <input v-model="catat.jam" type="time" required class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Arah</span>
          <select v-model="catat.arah" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option v-for="(label, kode) in (props.ARAH ?? {})" :key="kode" :value="kode">{{ label }}</option>
          </select>
        </label>

        <label class="block sm:col-span-2 lg:col-span-4">
          <span class="block text-[11px] text-stone-500">Catatan</span>
          <input v-model="catat.catatan" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <div class="sm:col-span-2 lg:col-span-5">
          <button type="submit" class="eq-btn-utama" :disabled="catat.processing">Catat</button>
        </div>
      </form>
    </section>
  </div>
</template>



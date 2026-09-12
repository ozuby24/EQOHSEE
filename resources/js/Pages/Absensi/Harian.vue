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
import { computed, ref } from 'vue';
import { propHalaman } from '../../halaman';
import Keadaan from './Keadaan.vue';

const props = propHalaman();

const baris   = computed<any[]>(() => (props.baris ?? []) as any[]);
const ringkas = computed<any>(() => props.ringkas ?? {});

const tanggal = ref(String(props.tanggal ?? ''));
const blok    = ref(String(props.terpilihBlok ?? ''));

function muat() {
  router.get('/absensi', {
    tanggal: tanggal.value || undefined,
    blok: blok.value || undefined,
  }, { preserveState: true, preserveScroll: true, replace: true });
}

/* ── rekonsiliasi ulang satu hari ── */

const ulang = useForm({});

function rekonsiliasi() {
  ulang.post(`/absensi/rekonsiliasi?dari=${tanggal.value}&sampai=${tanggal.value}`,
    { preserveScroll: true });
}

/* ── catat pindaian manual ── */

const catat = useForm({ pekerja_id: '', tanggal: String(props.tanggal ?? ''), jam: '', arah: 'masuk', catatan: '' });

function simpanCatat() {
  catat.post('/absensi/catat', {
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

  koreksi.put(`/absensi/${koreksiId.value}`, {
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

const kartu = computed(() => [
  { kunci: 'dijadwalkan', label: 'Dijadwalkan kerja', kelas: 'ab-k-netral' },
  { kunci: 'hadir',       label: 'Hadir',             kelas: 'ab-k-baik' },
  { kunci: 'terlambat',   label: 'Terlambat',         kelas: 'ab-k-ingat' },
  { kunci: 'belum_pulang',label: 'Belum tap pulang',  kelas: 'ab-k-serius' },
  { kunci: 'absen',       label: 'Absen',             kelas: 'ab-k-gawat' },
  { kunci: 'luar_roster', label: 'Di luar roster',    kelas: 'ab-k-luar' },
  { kunci: 'luar_area',   label: 'Di luar geofence',  kelas: 'ab-k-gawat' },
]);
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1280px] mx-auto space-y-5">
    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-cam-ink">{{ props.judul }}</h2>
        <p class="text-[12.5px] text-stone-500 mt-0.5">{{ props.subjudul }}</p>
      </div>

      <div class="flex gap-2">
        <Link href="/absensi/rekap" class="eq-btn-lain">Rekap periode</Link>
        <Link href="/absensi/mesin" class="eq-btn-lain">Mesin lapangan</Link>
      </div>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
      <div class="flex flex-wrap items-end gap-3">
        <label class="block">
          <span class="text-[11px] text-stone-500">Tanggal</span>
          <input v-model="tanggal" type="date" class="mt-1 rounded-lg border-stone-200 text-[12px]" @change="muat">
        </label>

        <label class="block">
          <span class="text-[11px] text-stone-500">Area</span>
          <select v-model="blok" class="mt-1 rounded-lg border-stone-200 text-[12px]" @change="muat">
            <option value="">Semua area</option>
            <option v-for="(nama, id) in (props.blok ?? {})" :key="id" :value="id">{{ nama }}</option>
          </select>
        </label>

        <button type="button" class="eq-btn-lain" :disabled="ulang.processing" @click="rekonsiliasi">
          Rekonsiliasi ulang hari ini
        </button>
      </div>

      <div class="mt-4 grid gap-2 grid-cols-2 sm:grid-cols-4 lg:grid-cols-7">
        <div v-for="k in kartu" :key="k.kunci" class="ab-kartu" :class="k.kelas">
          <div class="num text-[17px] font-bold leading-none">{{ ringkas[k.kunci] ?? 0 }}</div>
          <div class="text-[10.5px] mt-1 leading-tight">{{ k.label }}</div>
        </div>
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
          <span class="text-[11px] text-stone-500">Jam masuk</span>
          <input v-model="koreksi.masuk" type="time" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="text-[11px] text-stone-500">Jam keluar</span>
          <input v-model="koreksi.keluar" type="time" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block lg:col-span-2">
          <span class="text-[11px] text-stone-500">Alasan koreksi</span>
          <input v-model="koreksi.alasan" type="text" required minlength="5"
                 placeholder="Mesin pos 2 mati; jam dicatat pengawas shift."
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
          <span v-if="koreksi.errors.alasan" class="text-[11px] text-red-600">{{ koreksi.errors.alasan }}</span>
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
          <span class="text-[11px] text-stone-500">Pekerja</span>
          <select v-model="catat.pekerja_id" required class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option value="">—</option>
            <option v-for="p in (props.pekerja ?? [])" :key="p.id" :value="p.id">{{ p.nama }} · {{ p.nik }}</option>
          </select>
        </label>

        <label class="block">
          <span class="text-[11px] text-stone-500">Tanggal</span>
          <input v-model="catat.tanggal" type="date" required class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="text-[11px] text-stone-500">Jam</span>
          <input v-model="catat.jam" type="time" required class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="text-[11px] text-stone-500">Arah</span>
          <select v-model="catat.arah" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option v-for="(label, kode) in (props.ARAH ?? {})" :key="kode" :value="kode">{{ label }}</option>
          </select>
        </label>

        <label class="block sm:col-span-2 lg:col-span-4">
          <span class="text-[11px] text-stone-500">Catatan</span>
          <input v-model="catat.catatan" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <div class="sm:col-span-2 lg:col-span-5">
          <button type="submit" class="eq-btn-utama" :disabled="catat.processing">Catat</button>
        </div>
      </form>
    </section>
  </div>
</template>

<style>
/**
 * Kartu ringkasan, sadar tema.
 *
 * Ditulis sebagai kelas dan bukan gaya sebaris supaya aturan mode
 * gelap dapat menimpanya. Nilai gelapnya dipilih agar tetap terbaca
 * pada latar #0D1417 tanpa menyilaukan.
 */
.ab-kartu { border-radius: 0.75rem; padding: 0.625rem 0.75rem; }

.ab-k-netral { background: #F5F5F4; color: #44403C; }
.ab-k-baik   { background: #D1FAE5; color: #065F46; }
.ab-k-ingat  { background: #FEF3C7; color: #78350F; }
.ab-k-serius { background: #DBEAFE; color: #1E3A5F; }
.ab-k-gawat  { background: #FEE2E2; color: #7F1D1D; }
.ab-k-luar   { background: #EDE9FE; color: #4C1D95; }

:root[data-tema="gelap"] .ab-k-netral { background: #1C262B; color: #C7D0D5; }
:root[data-tema="gelap"] .ab-k-baik   { background: #143A2C; color: #8FE3BE; }
:root[data-tema="gelap"] .ab-k-ingat  { background: #4A3810; color: #F6D488; }
:root[data-tema="gelap"] .ab-k-serius { background: #1E3F5E; color: #A8CDF0; }
:root[data-tema="gelap"] .ab-k-gawat  { background: #4E1D1D; color: #F5A9A9; }
:root[data-tema="gelap"] .ab-k-luar   { background: #34255E; color: #C8B6F5; }
</style>

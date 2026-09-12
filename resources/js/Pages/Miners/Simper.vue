<script setup lang="ts">
/**
 * SIMPER — izin mengemudikan unit.
 *
 * TIGA SUMBER MASA BERLAKU, dan yang paling awal yang menang: kartunya
 * sendiri, Mine Permit yang mendasarinya, dan SIM Kepolisian. Layar ini
 * menyebut yang mana — "SIMPOL habis 1 Oktober" memberi tahu apa yang
 * harus diperbarui, sedangkan "kartu habis 1 Oktober" tidak.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { propHalaman } from '../../halaman';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
import Lencana from './Lencana.vue';
import Langkah from './Langkah.vue';

const props = propHalaman();
const { dialog, tanya, batal, lanjut } = useDialog();

const baris = computed<any[]>(() => (props.baris ?? []) as any[]);
const buka  = ref<number | null>(null);

const buat = useForm({
  permit_id: '', no_simper: '', tanggal: '', kelas: 'F',
  no_simpol: '', jenis_simpol: '', simpol_berlaku_sampai: '', pengalaman_kerja: '',
});

function simpan() {
  buat.post('/miners/simper', { preserveScroll: true, onSuccess: () => buat.reset() });
}

const unit = useForm({
  kendaraan_id: '', jenis_unit_id: '', kewenangan: 'operator',
  nilai_p2h: '', nilai_praktek: '', nilai_teori: '', nilai_rambu: '',
});

function tambahUnit(id: number) {
  unit.post(`/miners/simper/${id}/unit`, { preserveScroll: true, onSuccess: () => unit.reset() });
}

const ajuan = useForm({ jenis: 'penambahan', no_registrasi: '', tanggal: '', pengalaman_kerja: '' });

function buatAjuan(id: number) {
  ajuan.post(`/miners/simper/${id}/ajuan`, { preserveScroll: true, onSuccess: () => ajuan.reset() });
}

const tindakan = useForm({ keadaan: 'setuju', catatan: '' });

function tindak(id: number, keadaan: string) {
  tindakan.keadaan = keadaan;
  tindakan.post(`/miners/simper/${id}/tindak`, { preserveScroll: true });
}

async function hapus(id: number, nomor: string) {
  if (!await tanya(`Hapus SIMPER ${nomor || 'ini'} beserta seluruh unitnya?`)) return;

  useForm({}).delete(`/miners/simper/${id}`, { preserveScroll: true });
}

async function hapusUnit(simperId: number, unitId: number, nama: string) {
  if (!await tanya(`Hapus unit ${nama} dari kartu ini?`)) return;

  useForm({}).delete(`/miners/simper/${simperId}/unit/${unitId}`, { preserveScroll: true });
}

const bolehTindak = computed(() =>
  ((props.peranSaya ?? []) as string[]).some((p) => p !== 'pjo'));

const SEBAB: Record<string, string> = {
  simpol: 'SIMPOL habis lebih dahulu',
  permit: 'Mine Permit habis lebih dahulu',
  simper: 'masa berlaku kartunya sendiri',
};

/** Kelas SIM yang dituntut golongan yang sedang dipilih. */
const simpolDituntut = computed(() =>
  ((props.golongan ?? []) as any[]).find((g) => String(g.id) === String(unit.kendaraan_id))?.kelas_simpol ?? null);
</script>

<template>
  <Head :title="props.judul" />
  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />

  <div class="max-w-[1400px] mx-auto space-y-5">

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="text-[13.5px] font-bold text-cam-ink mb-3">SIMPER baru</h3>

      <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="simpan">
        <label class="block lg:col-span-2">
          <span class="block text-[11px] text-stone-500">Mine Permit yang mendasarinya</span>
          <select v-model="buat.permit_id" required class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option value="">Pilih kartu…</option>
            <option v-for="p in (props.permit ?? [])" :key="p.id" :value="p.id">
              {{ p.pekerja }} — {{ p.nomor || 'tanpa nomor' }}
            </option>
          </select>
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Nomor SIMPER</span>
          <input v-model="buat.no_simper" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Tanggal terbit</span>
          <input v-model="buat.tanggal" type="date" required
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Kelas SIMPER</span>
          <select v-model="buat.kelas" required class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option v-for="(label, kode) in (props.KELAS ?? {})" :key="kode" :value="kode">{{ label }}</option>
          </select>
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Kelas SIM Kepolisian</span>
          <select v-model="buat.jenis_simpol" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option value="">Pilih kelas…</option>
            <option v-for="s in (props.SIMPOL ?? [])" :key="s" :value="s">{{ s }}</option>
          </select>
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Nomor SIM</span>
          <input v-model="buat.no_simpol" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">SIM berlaku sampai</span>
          <input v-model="buat.simpol_berlaku_sampai" type="date"
                 class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <div class="sm:col-span-2 lg:col-span-4">
          <button type="submit" class="eq-btn-utama" :disabled="buat.processing">Buat SIMPER (draf)</button>
          <span v-if="buat.errors.permit_id" class="ml-3 text-[11.5px] text-red-600">{{ buat.errors.permit_id }}</span>
        </div>
      </form>
    </section>

    <section v-for="s in baris" :key="s.id"
             class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100 flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-0">
          <Link v-if="s.pekerja_id" :href="`/miners/${s.pekerja_id}`"
                class="text-[13.5px] font-bold text-cam-lime-deep hover:underline">{{ s.pekerja || '—' }}</Link>
          <p class="text-[11px] text-stone-500">
            <span class="num">{{ s.nomor || 'tanpa nomor' }}</span> · kelas {{ s.kelas }}
            · SIM {{ s.simpol || '—' }} · {{ props.STATUS?.[s.status] ?? s.status }}
          </p>
        </div>

        <Lencana :keadaan="s.keadaan" :label="props.KEADAAN?.[s.keadaan]" :nada="props.NADA" :sisa="s.sisa" />

        <button class="eq-btn-lain" @click="buka = buka === s.id ? null : s.id">
          {{ buka === s.id ? 'Tutup' : 'Kelola' }}
        </button>
      </header>

      <div class="px-5 py-3 border-b border-stone-100 space-y-2">
        <p class="text-[11.5px] text-stone-500">
          Berlaku sampai <span class="num">{{ s.efektif || '—' }}</span>
          <span v-if="s.sebab"> — yang menghentikannya: <strong>{{ SEBAB[s.sebab] ?? s.sebab }}</strong></span>
          <span v-if="s.simpolSampai"> · SIM habis <span class="num">{{ s.simpolSampai }}</span></span>
        </p>

        <Langkah :alur="s.alur" />
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[11.5px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
              <th class="px-4 py-2 font-semibold">Unit</th>
              <th class="px-4 py-2 font-semibold">Golongan</th>
              <th class="px-4 py-2 font-semibold">Kewenangan</th>
              <th class="px-4 py-2 font-semibold">Asal</th>
              <th class="px-4 py-2 font-semibold text-right">P2H</th>
              <th class="px-4 py-2 font-semibold text-right">Praktik</th>
              <th class="px-4 py-2 font-semibold text-right">Teori</th>
              <th class="px-4 py-2 font-semibold text-right">Rambu</th>
              <th v-if="buka === s.id" class="px-4 py-2 font-semibold"></th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="u in s.unit" :key="u.id" class="border-b border-stone-100"
                :class="u.lulus ? '' : 'bg-red-50/50'">
              <td class="px-4 py-2.5 font-semibold text-cam-ink">{{ u.unit || '—' }}</td>
              <td class="px-4 py-2.5 text-stone-600">{{ u.golongan || '—' }}</td>
              <td class="px-4 py-2.5 text-stone-600">{{ props.KEWENANGAN?.[u.kewenangan] ?? u.kewenangan ?? '—' }}</td>
              <td class="px-4 py-2.5 text-stone-500">{{ props.ASAL?.[u.asal] ?? u.asal }}</td>
              <td class="px-4 py-2.5 num text-right">{{ u.nilai_p2h ?? '—' }}</td>
              <td class="px-4 py-2.5 num text-right">{{ u.nilai_praktek ?? '—' }}</td>
              <td class="px-4 py-2.5 num text-right">{{ u.nilai_teori ?? '—' }}</td>
              <td class="px-4 py-2.5 num text-right">{{ u.nilai_rambu ?? '—' }}</td>
              <td v-if="buka === s.id" class="px-4 py-2.5">
                <button class="text-[11px] text-red-600 hover:underline"
                        @click="hapusUnit(s.id, u.id, u.unit || u.golongan || 'ini')">Hapus</button>
              </td>
            </tr>

            <tr v-if="!s.unit?.length">
              <td :colspan="buka === s.id ? 9 : 8" class="px-4 py-6 text-center text-stone-400">
                Belum ada unit pada kartu ini.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer v-if="buka === s.id" class="px-5 py-4 border-t border-stone-100 space-y-4">
        <form class="space-y-2" @submit.prevent="tambahUnit(s.id)">
          <h4 class="text-[12px] font-bold text-cam-ink">Tambah unit</h4>

          <div class="flex flex-wrap items-end gap-2">
            <select v-model="unit.kendaraan_id" class="rounded-lg border-stone-200 text-[11px] w-44"
                    aria-label="Golongan unit">
              <option value="">Golongan…</option>
              <option v-for="g in (props.golongan ?? [])" :key="g.id" :value="g.id">
                {{ g.nama }}<span v-if="g.kelas_simpol"> — SIM {{ g.kelas_simpol }}</span>
              </option>
            </select>

            <select v-model="unit.jenis_unit_id" class="rounded-lg border-stone-200 text-[11px] w-44"
                    aria-label="Jenis unit">
              <option value="">Unit…</option>
              <option v-for="j in (props.unit ?? [])" :key="j.id" :value="j.id">{{ j.nama }}</option>
            </select>

            <select v-model="unit.kewenangan" class="rounded-lg border-stone-200 text-[11px]"
                    aria-label="Kewenangan">
              <option v-for="(label, kode) in (props.KEWENANGAN ?? {})" :key="kode" :value="kode">{{ label }}</option>
            </select>

            <input v-model="unit.nilai_p2h" type="number" min="0" max="100" placeholder="P2H"
                   class="w-20 rounded-lg border-stone-200 text-[11px]" aria-label="Nilai P2H">
            <input v-model="unit.nilai_praktek" type="number" min="0" max="100" placeholder="Praktik"
                   class="w-20 rounded-lg border-stone-200 text-[11px]" aria-label="Nilai praktik">
            <input v-model="unit.nilai_teori" type="number" min="0" max="100" placeholder="Teori"
                   class="w-20 rounded-lg border-stone-200 text-[11px]" aria-label="Nilai teori">
            <input v-model="unit.nilai_rambu" type="number" min="0" max="100" placeholder="Rambu"
                   class="w-20 rounded-lg border-stone-200 text-[11px]" aria-label="Nilai rambu">

            <button type="submit" class="eq-btn-lain">Tambahkan</button>
          </div>

          <p v-if="simpolDituntut && simpolDituntut !== s.simpol" class="text-[11px] text-amber-700">
            Golongan ini menuntut SIM {{ simpolDituntut }}, sedangkan kartu ini tercatat
            {{ s.simpol || 'tanpa SIM' }}. Penambahannya akan ditolak.
          </p>

          <p v-if="unit.errors.kendaraan_id" class="text-[11.5px] text-red-600">{{ unit.errors.kendaraan_id }}</p>
          <p class="text-[11px] text-stone-500">Ambang kelulusan {{ props.NILAI_LULUS }}.</p>
        </form>

        <form class="space-y-2 border-t border-stone-100 pt-3" @submit.prevent="buatAjuan(s.id)">
          <h4 class="text-[12px] font-bold text-cam-ink">Pengajuan lanjutan</h4>

          <div class="flex flex-wrap items-end gap-2">
            <select v-model="ajuan.jenis" class="rounded-lg border-stone-200 text-[11px]" aria-label="Jenis pengajuan">
              <option v-for="(label, kode) in (props.JENIS_AJUAN ?? {})" :key="kode" :value="kode">{{ label }}</option>
            </select>

            <input v-model="ajuan.no_registrasi" placeholder="Nomor"
                   class="w-32 rounded-lg border-stone-200 text-[11px]" aria-label="Nomor pengajuan">
            <input v-model="ajuan.tanggal" type="date" required
                   class="rounded-lg border-stone-200 text-[11px]" aria-label="Tanggal pengajuan">

            <button type="submit" class="eq-btn-lain">Ajukan</button>
          </div>

          <p v-if="ajuan.errors.jenis" class="text-[11.5px] text-red-600">{{ ajuan.errors.jenis }}</p>
        </form>

        <div v-if="bolehTindak" class="flex flex-wrap items-end gap-2 border-t border-stone-100 pt-3">
          <input v-model="tindakan.catatan" placeholder="Catatan peninjau (opsional)"
                 class="rounded-lg border-stone-200 text-[12px] w-72" aria-label="Catatan peninjau">
          <button class="eq-btn-utama" @click="tindak(s.id, 'setuju')">Setujui</button>
          <button class="eq-btn-lain" @click="tindak(s.id, 'dikembalikan')">Kembalikan</button>
          <button class="eq-btn-lain" @click="tindak(s.id, 'tolak')">Tolak</button>
        </div>

        <p v-if="tindakan.errors.keadaan" class="text-[11.5px] text-red-600">{{ tindakan.errors.keadaan }}</p>

        <button class="text-[11.5px] text-red-600 hover:underline" @click="hapus(s.id, s.nomor)">
          Hapus SIMPER ini
        </button>
      </footer>
    </section>

    <p v-if="!baris.length" class="rounded-2xl bg-white border border-stone-100 shadow-card px-5 py-10 text-center text-stone-400">
      Belum ada SIMPER.
    </p>
  </div>
</template>

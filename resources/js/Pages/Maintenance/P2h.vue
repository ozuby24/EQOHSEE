<script setup lang="ts">
/**
 * P2H Unit — sisi web.
 *
 * Pengisian P2H ada di mode lapangan, di samping unitnya. Halaman ini
 * untuk yang mengelola: daftar unit yang wajib diperiksa tiap shift,
 * unit yang sedang ditahan beserta alasannya, riwayat pemeriksaan, dan
 * pelepasan tahan oleh orang yang berwenang.
 */
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import UbinAngka from '../../Components/UbinAngka.vue';
import IkonLapangan from '../../Components/IkonLapangan.vue';

type Unit = {
  id: number; kode: string; nama: string; jenis: string; jenisNama: string; keterangan: string | null; hm: number | null;
  status: 'laik' | 'ditahan'; aktif: boolean; ko_object_id: number | null; objek: string | null;
  ditahanSejak: string | null; ditahanKarena: string | null; dilepas: string | null; sudahShiftIni: boolean; terakhir: string | null;
};
type Periksa = {
  id: number; tanggal: string; shift: string; unit: string | null; operator: string; hm: number | null; hasil: string;
  ok: number; tidak: number; na: number; gagal: string[]; catatan: string | null; foto: string[]; wo: string | null;
};

const p = defineProps<{
  unit: Unit[];
  periksa: Periksa[];
  jenis: Record<string, string>;
  objek: { id: number; nama: string }[];
  ringkas: { unit: number; ditahan: number; belum: number; shift: string };
  dapatMelepas: boolean;
  tautan: { simpan: string; ubah: string; lepas: string; lapangan: string };
}>();

const untuk = (pola: string, id: number) => pola.replace('__ID__', String(id));
const isian = 'ring-focus w-full rounded-lg border border-stone-200 px-3 py-2 text-[12.5px] transition';

/* ═══════════ tambah & ubah unit ═══════════ */
const kosong = { kode: '', nama: '', jenis: 'dump_truck', keterangan: '', hm: '' as string | number, ko_object_id: '' as string | number, aktif: true };
const form = useForm({ ...kosong });
const ubahId = ref<number | null>(null);

function mulaiUbah(u: Unit) {
  ubahId.value = u.id;
  form.defaults({ kode: u.kode, nama: u.nama, jenis: u.jenis, keterangan: u.keterangan ?? '', hm: u.hm ?? '', ko_object_id: u.ko_object_id ?? '', aktif: u.aktif });
  form.reset();
  form.clearErrors();
  document.getElementById('form-unit')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function batalUbah() {
  ubahId.value = null;
  form.defaults({ ...kosong });
  form.reset();
  form.clearErrors();
}

function simpan() {
  const kirim = form.transform((d) => ({ ...d, hm: d.hm === '' ? null : d.hm, ko_object_id: d.ko_object_id === '' ? null : d.ko_object_id }));
  if (ubahId.value) kirim.put(untuk(p.tautan.ubah, ubahId.value), { preserveScroll: true, onSuccess: batalUbah });
  else kirim.post(p.tautan.simpan, { preserveScroll: true, onSuccess: () => form.reset() });
}

/* ═══════════ lepas tahan ═══════════ */
const lepasId = ref<number | null>(null);
const lepas = useForm({ catatan: '' });
function simpanLepas(u: Unit) {
  lepas.post(untuk(p.tautan.lepas, u.id), { preserveScroll: true, onSuccess: () => { lepasId.value = null; lepas.reset(); } });
}

const aktif = computed(() => p.unit.filter((u) => u.aktif));
const nonaktif = computed(() => p.unit.filter((u) => !u.aktif));
</script>

<template>
  <Head title="P2H Unit" />

  <div class="space-y-5">
    <section class="ubin-kisi">
      <UbinAngka :angka="ringkas.unit" label="Unit wajib P2H" nada="netral" catatan="Unit aktif di armada">
        <template #ikon><IkonLapangan nama="truk" /></template>
      </UbinAngka>
      <UbinAngka :angka="ringkas.ditahan" label="Unit ditahan" :dari="ringkas.unit" :nada="ringkas.ditahan ? 'gawat' : 'baik'"
                 :catatan="ringkas.ditahan ? 'Butir kritis gagal — tidak boleh beroperasi' : 'Tidak ada unit ditahan'">
        <template #ikon><IkonLapangan nama="bahaya" /></template>
      </UbinAngka>
      <UbinAngka :angka="ringkas.belum" :label="`Belum P2H ${ringkas.shift.toLowerCase()}`" :dari="ringkas.unit" :nada="ringkas.belum ? 'ingat' : 'baik'"
                 catatan="Unit laik yang belum diperiksa shift ini">
        <template #ikon><IkonLapangan nama="jam" /></template>
      </UbinAngka>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 flex flex-wrap items-center gap-4 justify-between">
      <div class="min-w-0">
        <h3 class="text-[14px] font-bold text-cam-ink">P2H diisi di mode lapangan</h3>
        <p class="text-[12px] text-stone-500 mt-1 max-w-2xl leading-relaxed">
          Operator mengisi daftar periksa dari ponsel di samping unitnya — tetap bisa tanpa sinyal. Satu butir KRITIS yang gagal
          langsung menahan unit dan menerbitkan perintah kerja korektif berprioritas kritis untuk mekanik.
        </p>
      </div>
      <a :href="tautan.lapangan" class="eq-btn-utama">Buka mode lapangan</a>
    </section>

    <!-- Daftar unit -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <div class="px-5 py-4 border-b border-stone-100">
        <h3 class="font-bold text-[14px]">Unit</h3>
        <p class="text-[11px] text-stone-400">{{ aktif.length }} aktif<template v-if="nonaktif.length"> · {{ nonaktif.length }} nonaktif</template></p>
      </div>
      <div v-if="!unit.length" class="px-5 py-8 text-center text-[12.5px] text-stone-500">Belum ada unit. Tambahkan unit pertama di formulir di bawah.</div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-left text-[12px]">
          <thead>
            <tr class="border-b border-stone-100 text-stone-400">
              <th class="px-5 py-3">Unit</th><th class="px-5 py-3">Jenis</th><th class="px-5 py-3">Status</th>
              <th class="px-5 py-3">P2H terakhir</th><th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody>
            <template v-for="u in unit" :key="u.id">
              <tr class="border-b border-stone-50 align-top" :class="{ 'opacity-60': !u.aktif }">
                <td class="px-5 py-3">
                  <b class="font-mono">{{ u.kode }}</b> — {{ u.nama }}
                  <div class="text-[11px] text-stone-400">{{ [u.keterangan, u.objek ? `KO ${u.objek}` : null, u.hm ? `HM ${u.hm}` : null].filter(Boolean).join(' · ') }}</div>
                </td>
                <td class="px-5 py-3">{{ u.jenisNama }}</td>
                <td class="px-5 py-3">
                  <span v-if="!u.aktif" class="rounded-full bg-stone-100 text-stone-500 px-2 py-1 text-[10.5px] font-bold">Nonaktif</span>
                  <span v-else-if="u.status === 'ditahan'" class="rounded-full bg-red-100 text-red-700 px-2 py-1 text-[10.5px] font-bold">Ditahan</span>
                  <span v-else class="rounded-full bg-emerald-100 text-emerald-700 px-2 py-1 text-[10.5px] font-bold">Laik operasi</span>
                  <div v-if="u.status === 'ditahan'" class="text-[11px] text-red-700 mt-1.5 max-w-xs leading-snug">Sejak {{ u.ditahanSejak }} — {{ u.ditahanKarena }}</div>
                  <div v-else-if="u.dilepas" class="text-[11px] text-stone-400 mt-1.5 max-w-xs leading-snug">Dilepas {{ u.dilepas }}</div>
                </td>
                <td class="px-5 py-3">
                  {{ u.terakhir ?? '—' }}
                  <div v-if="u.aktif && u.status !== 'ditahan'" class="text-[11px] mt-1" :class="u.sudahShiftIni ? 'text-emerald-700' : 'text-amber-700'">
                    {{ u.sudahShiftIni ? 'Sudah diperiksa shift ini' : 'Belum diperiksa shift ini' }}
                  </div>
                </td>
                <td class="px-5 py-3 whitespace-nowrap text-right">
                  <button v-if="dapatMelepas && u.status === 'ditahan'" type="button" class="text-[11.5px] font-bold text-red-700 py-1.5 mr-3"
                          @click="lepasId = lepasId === u.id ? null : u.id; lepas.reset(); lepas.clearErrors()">Lepas tahan</button>
                  <button type="button" class="text-[11.5px] font-bold text-cam-lime-deep py-1.5" @click="mulaiUbah(u)">Ubah</button>
                </td>
              </tr>
              <tr v-if="lepasId === u.id" class="bg-red-50/40">
                <td colspan="5" class="px-5 py-4">
                  <form class="flex flex-wrap items-end gap-3" @submit.prevent="simpanLepas(u)">
                    <label class="flex-1 min-w-[240px]">
                      <span class="block text-[11px] font-bold text-stone-600 mb-1">Apa yang sudah diperbaiki? (wajib)</span>
                      <input v-model="lepas.catatan" :class="isian" maxlength="500" placeholder="mis. Retarder diganti, uji jalan normal">
                      <span v-if="lepas.errors.catatan" class="block text-[11px] text-red-600 mt-1">{{ lepas.errors.catatan }}</span>
                    </label>
                    <button type="submit" class="eq-btn-utama" :disabled="lepas.processing">{{ lepas.processing ? 'Menyimpan…' : 'Laik operasi' }}</button>
                    <button type="button" class="eq-btn-lain" @click="lepasId = null">Batal</button>
                  </form>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Tambah / ubah unit -->
    <section id="form-unit" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="font-bold text-[14px]">{{ ubahId ? `Ubah unit ${form.kode}` : 'Tambah unit' }}</h3>
      <p class="text-[11px] text-stone-400 mt-1">Daftar periksanya mengikuti jenis unit. Butir kritis ditetapkan sistem, tidak dapat diturunkan dari layar.</p>
      <form class="grid gap-3 md:grid-cols-3 mt-4" @submit.prevent="simpan">
        <label><span class="block text-[11px] font-bold text-stone-600 mb-1">Kode unit</span>
          <input v-model="form.kode" :class="isian" maxlength="30" placeholder="DT-1142" required>
          <span v-if="form.errors.kode" class="block text-[11px] text-red-600 mt-1">{{ form.errors.kode }}</span></label>
        <label><span class="block text-[11px] font-bold text-stone-600 mb-1">Nama / model</span>
          <input v-model="form.nama" :class="isian" maxlength="120" placeholder="Komatsu HD785-7" required>
          <span v-if="form.errors.nama" class="block text-[11px] text-red-600 mt-1">{{ form.errors.nama }}</span></label>
        <label><span class="block text-[11px] font-bold text-stone-600 mb-1">Jenis unit</span>
          <select v-model="form.jenis" :class="isian"><option v-for="(n, k) in jenis" :key="k" :value="k">{{ n }}</option></select>
          <span v-if="form.errors.jenis" class="block text-[11px] text-red-600 mt-1">{{ form.errors.jenis }}</span></label>
        <label><span class="block text-[11px] font-bold text-stone-600 mb-1">Keterangan</span>
          <input v-model="form.keterangan" :class="isian" maxlength="150" placeholder="Dump truck 91 t"></label>
        <label><span class="block text-[11px] font-bold text-stone-600 mb-1">Hour meter</span>
          <input v-model="form.hm" :class="isian" inputmode="decimal" placeholder="18442">
          <span v-if="form.errors.hm" class="block text-[11px] text-red-600 mt-1">{{ form.errors.hm }}</span></label>
        <label><span class="block text-[11px] font-bold text-stone-600 mb-1">Objek KO (opsional)</span>
          <select v-model="form.ko_object_id" :class="isian"><option value="">— tidak ditautkan —</option><option v-for="o in objek" :key="o.id" :value="o.id">{{ o.nama }}</option></select>
          <span v-if="form.errors.ko_object_id" class="block text-[11px] text-red-600 mt-1">{{ form.errors.ko_object_id }}</span></label>
        <label v-if="ubahId" class="flex items-center gap-2 text-[12px] text-stone-600 md:col-span-3">
          <input v-model="form.aktif" type="checkbox" class="accent-[#F57C00]"> Unit aktif — wajib P2H tiap shift
        </label>
        <div class="md:col-span-3 flex gap-2">
          <button type="submit" class="eq-btn-utama" :disabled="form.processing">{{ form.processing ? 'Menyimpan…' : (ubahId ? 'Simpan perubahan' : 'Tambah unit') }}</button>
          <button v-if="ubahId" type="button" class="eq-btn-lain" @click="batalUbah">Batal</button>
        </div>
      </form>
    </section>

    <!-- Riwayat -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <div class="px-5 py-4 border-b border-stone-100">
        <h3 class="font-bold text-[14px]">Riwayat P2H</h3>
        <p class="text-[11px] text-stone-400">40 pemeriksaan terakhir</p>
      </div>
      <div v-if="!periksa.length" class="px-5 py-8 text-center text-[12.5px] text-stone-500">Belum ada P2H yang dikirim.</div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-left text-[12px]">
          <thead>
            <tr class="border-b border-stone-100 text-stone-400">
              <th class="px-5 py-3">Tanggal</th><th class="px-5 py-3">Unit</th><th class="px-5 py-3">Operator</th>
              <th class="px-5 py-3">Hasil</th><th class="px-5 py-3">Temuan</th><th class="px-5 py-3">Foto</th><th class="px-5 py-3">Perintah kerja</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="x in periksa" :key="x.id" class="border-b border-stone-50 align-top">
              <td class="px-5 py-3 whitespace-nowrap">{{ x.tanggal }}<div class="text-[11px] text-stone-400">{{ x.shift }}</div></td>
              <td class="px-5 py-3 font-mono">{{ x.unit }}<div v-if="x.hm" class="text-[11px] text-stone-400">HM {{ x.hm }}</div></td>
              <td class="px-5 py-3">{{ x.operator }}</td>
              <td class="px-5 py-3 whitespace-nowrap">
                <span class="rounded-full px-2 py-1 text-[10.5px] font-bold" :class="x.hasil === 'ditahan' ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'">{{ x.hasil === 'ditahan' ? 'Ditahan' : 'Laik' }}</span>
                <div class="text-[11px] text-stone-400 mt-1">{{ x.ok }} OK · {{ x.tidak }} tidak · {{ x.na }} N/A</div>
              </td>
              <td class="px-5 py-3 max-w-xs">
                <ul v-if="x.gagal.length" class="space-y-0.5"><li v-for="g in x.gagal" :key="g" :class="g.startsWith('[KRITIS]') ? 'text-red-700 font-semibold' : 'text-stone-600'">{{ g }}</li></ul>
                <span v-else class="text-stone-400">—</span>
                <div v-if="x.catatan" class="text-[11px] text-stone-500 mt-1 italic">“{{ x.catatan }}”</div>
              </td>
              <td class="px-5 py-3">
                <div class="flex gap-1.5">
                  <a v-for="f in x.foto" :key="f" :href="f" target="_blank" rel="noopener"><img :src="f" alt="Foto P2H" class="w-10 h-10 rounded-md object-cover border border-stone-200" loading="lazy"></a>
                  <span v-if="!x.foto.length" class="text-stone-400">—</span>
                </div>
              </td>
              <td class="px-5 py-3 whitespace-nowrap">{{ x.wo ?? '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

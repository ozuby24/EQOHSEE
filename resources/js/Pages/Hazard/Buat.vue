<script setup lang="ts">
/**
 * Hazard Report — buat laporan.
 *
 * Identitas pelapor sudah terisi dari akun yang sedang masuk, dan dapat
 * diganti ke orang lain lewat daftar man power — pelaporan di lapangan
 * kerap dititipkan kepada yang memegang perangkat.
 *
 * Memilih dari daftar, bukan mengetik bebas, disengaja: di isian bebas
 * itulah ejaan nama mulai bervariasi, dan satu orang pecah menjadi
 * beberapa di perhitungan KPI.
 */
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import type { HalamanBuatBahaya } from '../../types';

const props = defineProps<HalamanBuatBahaya>();

const form = useForm<Record<string, any>>({
  ...props.awal,
  terlapor: '',
  lokasi: '',
  lokasi_lain: '',
  kategori: '',
  deskripsi: '',
  unsafe_action: [] as string[],
  unsafe_condition: [] as string[],
  hirarki: '',
  rekomendasi: '',
  foto: [] as File[],
});

/* Pemilih man power. Menyalin seluruh identitasnya sekaligus, bukan
   namanya saja — NRP, jabatan, dan departemen ikut menentukan golongan
   dan target KPI orang itu. */
const cariOrang = ref('');

const orangCocok = computed(() => {
  const q = cariOrang.value.trim().toLowerCase();
  if (!q) return [];

  return props.manpower
    .filter((m) => m.nama.toLowerCase().includes(q) || (m.nrp ?? '').toLowerCase().includes(q))
    .slice(0, 6);
});

function pilihOrang(m: HalamanBuatBahaya['manpower'][number]) {
  form.pelapor_nama       = m.nama;
  form.pelapor_nrp        = m.nrp;
  form.pelapor_jabatan    = m.jabatan;
  form.pelapor_departemen = m.dept;
  form.pelapor_perusahaan = m.perusahaan;
  cariOrang.value = '';
}

function alih(daftar: 'unsafe_action' | 'unsafe_condition', nilai: string) {
  const kini = form[daftar];
  form[daftar] = kini.includes(nilai) ? kini.filter((x: string) => x !== nilai) : [...kini, nilai];
}

function pilihFoto(e: Event) {
  form.foto = Array.from((e.target as HTMLInputElement).files ?? []);
}

function simpan() {
  form.post(props.tautan.simpan, { forceFormData: true });
}

const isian = 'ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px] transition';
const label = 'block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5';
</script>

<template>
  <Head title="Buat Laporan Bahaya" />

  <form class="max-w-4xl mx-auto space-y-5" @submit.prevent="simpan">

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-3.5">Pelapor</h3>

      <div class="relative mb-4">
        <input v-model="cariOrang" :class="isian" placeholder="Cari nama atau NRP untuk mengisi otomatis…">

        <div v-if="orangCocok.length"
             class="absolute z-20 left-0 right-0 mt-1 bg-white rounded-xl border border-stone-200
                    shadow-lg overflow-hidden">
          <button v-for="(m, i) in orangCocok" :key="i" type="button" @click="pilihOrang(m)"
                  class="w-full text-left px-3.5 py-2.5 hover:bg-stone-50 border-b border-stone-50 last:border-0">
            <p class="text-[12.5px] font-bold text-cam-ink">{{ m.nama }}</p>
            <p class="text-[11px] text-stone-400">
              {{ m.nrp ?? '—' }} · {{ m.jabatan ?? '—' }}<template v-if="m.perusahaan"> · {{ m.perusahaan }}</template>
            </p>
          </button>
        </div>
      </div>

      <div class="grid gap-3 sm:grid-cols-2">
        <div>
          <label :class="label">Nama <span class="text-red-500">*</span></label>
          <input v-model="form.pelapor_nama" :class="isian">
          <p v-if="form.errors.pelapor_nama" class="text-[11.5px] text-red-600 mt-1">{{ form.errors.pelapor_nama }}</p>
        </div>
        <div>
          <label :class="label">NRP</label>
          <input v-model="form.pelapor_nrp" :class="isian">
        </div>
        <div>
          <label :class="label">Jabatan <span class="text-red-500">*</span></label>
          <input v-model="form.pelapor_jabatan" :class="isian">
          <p v-if="form.errors.pelapor_jabatan" class="text-[11.5px] text-red-600 mt-1">{{ form.errors.pelapor_jabatan }}</p>
        </div>
        <div>
          <label :class="label">Departemen</label>
          <input v-model="form.pelapor_departemen" :class="isian">
        </div>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-3.5">Temuan</h3>

      <div class="grid gap-3 sm:grid-cols-2">
        <div>
          <label :class="label">Ditujukan kepada <span class="text-red-500">*</span></label>
          <select v-model="form.company_id" :class="isian" aria-label="Perusahaan">
            <option value="">— pilih perusahaan —</option>
            <option v-for="c in opsi.perusahaan" :key="c.id" :value="String(c.id)">{{ c.nama }}</option>
          </select>
          <p v-if="form.errors.company_id" class="text-[11.5px] text-red-600 mt-1">{{ form.errors.company_id }}</p>
        </div>
        <div>
          <label :class="label">Nama terlapor / kontraktor</label>
          <input v-model="form.terlapor" :class="isian">
        </div>

        <div>
          <label :class="label">Tanggal <span class="text-red-500">*</span></label>
          <input v-model="form.tanggal" type="date" :class="isian" aria-label="Tanggal">
          <p v-if="form.errors.tanggal" class="text-[11.5px] text-red-600 mt-1">{{ form.errors.tanggal }}</p>
        </div>
        <div>
          <label :class="label">Waktu</label>
          <input v-model="form.waktu" type="time" :class="isian">
        </div>

        <div>
          <label :class="label">Lokasi</label>
          <select v-model="form.lokasi" :class="isian" aria-label="Lokasi">
            <option value="">— pilih lokasi —</option>
            <option v-for="l in opsi.lokasi" :key="l" :value="l">{{ l }}</option>
            <option value="Lainnya">Lainnya…</option>
          </select>
        </div>
        <div v-if="form.lokasi === 'Lainnya'">
          <label :class="label">Sebutkan lokasinya</label>
          <input v-model="form.lokasi_lain" :class="isian">
        </div>

        <div>
          <label :class="label">Risiko <span class="text-red-500">*</span></label>
          <select v-model="form.risiko" :class="isian" aria-label="Tingkat risiko">
            <option v-for="r in opsi.risiko" :key="r" :value="r">{{ r }}</option>
          </select>
        </div>
        <div>
          <label :class="label">Kategori <span class="text-red-500">*</span></label>
          <select v-model="form.kategori" :class="isian" aria-label="Kategori">
            <option value="">— pilih kategori —</option>
            <option v-for="k in opsi.kategori" :key="k" :value="k">{{ k }}</option>
          </select>
          <p v-if="form.errors.kategori" class="text-[11.5px] text-red-600 mt-1">{{ form.errors.kategori }}</p>
        </div>
      </div>

      <div class="mt-3">
        <label :class="label">Uraian temuan <span class="text-red-500">*</span></label>
        <textarea v-model="form.deskripsi" rows="4" :class="isian" style="resize:none"
                  placeholder="Jelaskan apa yang dilihat, di mana, dan mengapa berbahaya."></textarea>
        <p v-if="form.errors.deskripsi" class="text-[11.5px] text-red-600 mt-1">{{ form.errors.deskripsi }}</p>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-1">Penyebab</h3>
      <p class="text-[11.5px] text-stone-400 mb-3.5">Boleh lebih dari satu.</p>

      <div class="grid gap-5 sm:grid-cols-2">
        <div>
          <p :class="label">Tindakan tidak aman</p>
          <div class="space-y-1.5 max-h-56 overflow-y-auto pr-1">
            <label v-for="x in opsi.unsafeAction" :key="x"
                   class="flex items-start gap-2 text-[12px] text-stone-600 cursor-pointer">
              <input type="checkbox" :checked="form.unsafe_action.includes(x)"
                     class="mt-0.5 accent-[color:var(--eq-aksen,#F57C00)]"
                     @change="alih('unsafe_action', x)">
              <span>{{ x }}</span>
            </label>
          </div>
        </div>

        <div>
          <p :class="label">Kondisi tidak aman</p>
          <div class="space-y-1.5 max-h-56 overflow-y-auto pr-1">
            <label v-for="x in opsi.unsafeCondition" :key="x"
                   class="flex items-start gap-2 text-[12px] text-stone-600 cursor-pointer">
              <input type="checkbox" :checked="form.unsafe_condition.includes(x)"
                     class="mt-0.5 accent-[color:var(--eq-aksen,#F57C00)]"
                     @change="alih('unsafe_condition', x)">
              <span>{{ x }}</span>
            </label>
          </div>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-3.5">Pengendalian &amp; Bukti</h3>

      <div class="grid gap-3 sm:grid-cols-2">
        <div>
          <label :class="label">Hirarki pengendalian</label>
          <select v-model="form.hirarki" :class="isian" aria-label="Hirarki pengendalian">
            <option value="">— pilih —</option>
            <option v-for="h in opsi.hirarki" :key="h" :value="h">{{ h }}</option>
          </select>
        </div>
        <div>
          <label :class="label">Foto</label>
          <input type="file" multiple accept="image/*" class="text-[12px] text-stone-500 py-2.5"
                 @change="pilihFoto">
        </div>
      </div>

      <div class="mt-3">
        <label :class="label">Rekomendasi perbaikan</label>
        <textarea v-model="form.rekomendasi" rows="3" :class="isian" style="resize:none"></textarea>
      </div>
    </div>

    <div class="flex items-center gap-2">
      <button type="submit" :disabled="form.processing"
              class="eq-btn-utama disabled:opacity-40" style="flex:none;padding:11px 22px">
        {{ form.processing ? 'Menyimpan…' : 'Kirim Laporan' }}
      </button>
      <a :href="tautan.batal" class="px-4 py-2.5 text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink">
        Batal
      </a>
    </div>
  </form>
</template>

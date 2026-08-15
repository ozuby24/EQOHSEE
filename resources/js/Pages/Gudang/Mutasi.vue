<script setup lang="ts">
/**
 * Gudang — pencatatan dan riwayat mutasi.
 *
 * Sisa stok ikut di daftar pilihan barang supaya pencatat tahu batas
 * pengeluarannya sebelum menekan simpan, bukan setelah ditolak server.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import BarisMutasi from '../../Components/BarisMutasi.vue';
import { angka } from '../../angka';
import type { HalamanMutasiGudang } from '../../types';

const props = defineProps<HalamanMutasiGudang>();

const form = useForm({
  jenis: 'masuk',
  barang_id: '',
  jumlah: '',
  tanggal: props.hariIni,
  pihak: '',
  batch: '',
  kadaluarsa: '',
  keterangan: '',
});

function catat() {
  form.post(props.tautan.simpan, {
    preserveScroll: true,
    onSuccess: () => form.reset('barang_id', 'jumlah', 'pihak', 'batch', 'kadaluarsa', 'keterangan'),
  });
}

const saring = useForm({ ...props.f });

function terapkan() {
  // Penyaring kosong dibuang dari alamatnya; lihat catatan yang sama di
  // Gudang/Barang.vue.
  saring
    .transform((d) => Object.fromEntries(Object.entries(d).filter(([, v]) => v !== '')))
    .get(props.tautan.daftar, { preserveState: true, preserveScroll: true, replace: true });
}

const labelPihak = computed(() => (form.jenis === 'masuk' ? 'Pemasok' : 'Penerima / Bagian'));

const isian = 'w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px] ring-focus transition';
const kecil = 'rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] ring-focus transition';
const label = 'block text-[12px] font-semibold text-cam-ink mb-1.5';
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-[1400px] mx-auto space-y-5">
    <div class="grid gap-5 lg:grid-cols-3">

      <section v-if="bolehCatat" class="eq-panel lg:col-span-1 self-start">
        <div class="eq-panel-kepala"><h3>Catat Mutasi</h3></div>

        <form class="space-y-3.5" @submit.prevent="catat">
          <div>
            <label :class="label">Jenis <span class="text-red-500">*</span></label>
            <div class="grid grid-cols-3 gap-1.5">
              <label v-for="o in opsi.jenis" :key="o.nilai" class="cursor-pointer">
                <input v-model="form.jenis" type="radio" :value="o.nilai" class="sr-only">
                <span class="block text-center py-2 rounded-xl text-[12px] font-semibold border transition"
                      :class="form.jenis === o.nilai
                        ? 'eq-jenis-aktif'
                        : 'border-stone-200 text-stone-500 hover:bg-stone-50'">{{ o.label }}</span>
              </label>
            </div>
          </div>

          <div>
            <label :class="label">Barang <span class="text-red-500">*</span></label>
            <select v-model="form.barang_id" :class="isian">
              <option value="">— pilih barang —</option>
              <option v-for="b in barang" :key="b.id" :value="String(b.id)">
                {{ b.nama }} — sisa {{ angka(b.stok) }} {{ b.satuan }}
              </option>
            </select>
            <p v-if="form.errors.barang_id" class="text-[11.5px] text-red-600 mt-1">{{ form.errors.barang_id }}</p>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label :class="label">Jumlah <span class="text-red-500">*</span></label>
              <input v-model="form.jumlah" type="number" step="0.01" min="0.01" :class="isian">
              <p v-if="form.errors.jumlah" class="text-[11.5px] text-red-600 mt-1">{{ form.errors.jumlah }}</p>
            </div>
            <div>
              <label :class="label">Tanggal <span class="text-red-500">*</span></label>
              <input v-model="form.tanggal" type="date" :class="isian">
            </div>
          </div>

          <div>
            <label :class="label">{{ labelPihak }}</label>
            <input v-model="form.pihak" :class="isian">
          </div>

          <!-- Batch dan kedaluwarsa hanya bermakna saat menerima barang. -->
          <div v-show="form.jenis === 'masuk'" class="grid grid-cols-2 gap-3">
            <div>
              <label :class="label">Batch</label>
              <input v-model="form.batch" :class="isian">
            </div>
            <div>
              <label :class="label">Kedaluwarsa</label>
              <input v-model="form.kadaluarsa" type="date" :class="isian">
            </div>
          </div>

          <div>
            <label :class="label">Keterangan</label>
            <textarea v-model="form.keterangan" rows="2" :class="isian" style="resize:none"></textarea>
          </div>

          <button type="submit" :disabled="form.processing"
                  class="eq-btn-utama w-full disabled:opacity-40" style="padding:11px">
            {{ form.processing ? 'Menyimpan…' : 'Catat Mutasi' }}
          </button>
          <p class="text-[11px] text-stone-400 text-center">
            Nomor dibuat otomatis dan berurut per jenis tiap bulan.
          </p>
        </form>
      </section>

      <section class="eq-panel" :class="bolehCatat ? 'lg:col-span-2' : 'lg:col-span-3'">
        <div class="eq-panel-kepala"><h3>Riwayat Mutasi</h3></div>

        <form class="grid gap-2 sm:grid-cols-4 mb-4" @submit.prevent="terapkan">
          <select v-model="saring.jenis" :class="kecil" @change="terapkan">
            <option value="">Semua jenis</option>
            <option v-for="o in opsi.jenisSaring" :key="o.nilai" :value="o.nilai">{{ o.label }}</option>
          </select>
          <input v-model="saring.dari" type="date" :class="kecil">
          <input v-model="saring.sampai" type="date" :class="kecil">
          <button type="submit" class="eq-btn-utama" style="padding:8px 16px">Saring</button>
        </form>

        <div v-if="!mutasi.length" class="eq-kosong">
          <strong>Belum ada mutasi</strong>
          <p>Penerimaan dan pengeluaran yang dicatat akan muncul di sini.</p>
        </div>

        <template v-else>
          <ul class="space-y-1">
            <BarisMutasi v-for="m in mutasi" :key="m.id" :m="m" />
          </ul>

          <nav v-if="halaman.akhir > 1" class="mt-4 flex flex-wrap gap-1.5">
            <component v-for="(t, i) in halaman.tautan" :key="i"
                       :is="t.url ? Link : 'span'" :href="t.url ?? undefined"
                       class="px-3 py-1.5 rounded-lg text-[12px] font-semibold border transition"
                       :class="t.aktif
                         ? 'border-transparent text-white eq-jenis-aktif'
                         : t.url ? 'border-stone-200 text-stone-600 hover:bg-stone-50'
                                 : 'border-stone-100 text-stone-300'"
                       v-html="t.label" />
          </nav>
        </template>
      </section>
    </div>
  </div>
</template>

<style scoped>
.eq-jenis-aktif {
  background: linear-gradient(135deg, var(--eq-aksen, #F57C00), #DC6E00);
  color: #fff !important;
  border-color: transparent !important;
}
</style>

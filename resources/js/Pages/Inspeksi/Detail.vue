<script setup lang="ts">
/**
 * Inspeksi — pelaksanaan pemeriksaan.
 *
 * Seluruh baris parameter disimpan dalam satu kiriman, berkunci id
 * masing-masing. Menyimpan per baris berarti orang yang mengisi dua
 * puluh parameter menunggu dua puluh kali, dan kehilangan sebagian
 * isian setiap kali sambungan lapangan terputus di tengah.
 */
import { reactive, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import type { HalamanDetailInspeksi } from '../../types';

const props = defineProps<HalamanDetailInspeksi>();

/* Salinan lokal tiap baris. Dikirim sebagai item[id][kolom] — bentuk
   yang sama dengan yang diterima controller sejak versi Blade-nya. */
const baris = reactive<Record<number, Record<string, string>>>(
  Object.fromEntries(props.item.map((x) => [x.id, {
    kondisi: x.kondisi ?? '', risiko: x.risiko ?? '',
    temuan: x.temuan ?? '', tindakan: x.tindakan ?? '',
  }])),
);

const status = ref(props.i.status);
const menyimpan = ref(false);

function simpanSemua() {
  menyimpan.value = true;
  router.post(props.tautan.simpanItem, { item: baris, status: status.value }, {
    preserveScroll: true,
    onFinish: () => { menyimpan.value = false; },
  });
}

function angkat(url: string) {
  if (!confirm('Naikkan temuan ini menjadi Hazard Report?')) return;
  router.post(url, {}, { preserveScroll: true });
}

function hapusItem(url: string) {
  if (!confirm('Hapus parameter ini?')) return;
  router.delete(url, { preserveScroll: true });
}

/* Tambah parameter dan tambah petugas berdiri sendiri — keduanya
   menambah baris baru, bukan menyunting yang sudah ada. */
const formItem = useForm({ uraian: '', kelompok: '', risiko: '' });
const formPetugas = useForm({ user_id: '', nama: '', jabatan: '', peran: 'Anggota' });

const isian = 'ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition';
const label = 'block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1';
</script>

<template>
  <Head :title="`Inspeksi ${i.kode}`" />

  <div class="max-w-6xl mx-auto space-y-5">

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="text-[10px] font-bold bg-cam-ink text-white px-2 py-0.5 rounded num">{{ i.kode }}</span>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                  :class="i.status === 'Selesai' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
              {{ i.status }}
            </span>
            <span v-if="i.template" class="text-[10px] font-semibold bg-stone-100 text-stone-500 px-2 py-0.5 rounded-full">
              {{ i.template }}
            </span>
          </div>
          <p class="text-[15px] font-bold text-cam-ink mt-2.5">{{ i.judul }}</p>
          <div class="flex flex-wrap gap-x-3 gap-y-1 text-[11.5px] text-stone-400 mt-1.5">
            <span>📍 {{ i.lokasi ?? '—' }}</span>
            <span>{{ i.tanggal }}</span>
            <span v-if="i.perusahaan">{{ i.perusahaan }}</span>
            <span v-if="i.pembuat">Dibuat: {{ i.pembuat }}</span>
          </div>
        </div>

        <div class="flex flex-wrap gap-2 shrink-0">
          <a :href="tautan.ubah"
             class="rounded-xl border border-stone-200 px-3.5 py-2 text-[12px] font-bold text-stone-600
                    hover:bg-stone-50 transition">Ubah</a>
          <a :href="tautan.cetak" target="_blank" rel="noopener"
             class="rounded-xl border border-stone-200 px-3.5 py-2 text-[12px] font-bold text-stone-600
                    hover:bg-stone-50 transition">⎙ Cetak</a>
          <a :href="tautan.kembali"
             class="px-3 py-2 text-[12px] font-semibold text-stone-400 hover:text-cam-ink">← Kembali</a>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-3">Inspektur</h3>

      <div class="flex flex-wrap gap-2 mb-4">
        <span v-for="p in inspektur" :key="p.id"
              class="inline-flex items-center gap-2 bg-stone-50 border border-stone-200 rounded-xl px-3 py-1.5">
          <span class="text-[12px] font-bold text-cam-ink">{{ p.nama }}</span>
          <span class="text-[10.5px] text-stone-400">{{ p.jabatan ?? '—' }} · {{ p.peran }}</span>
        </span>
        <span v-if="!inspektur.length" class="text-[12px] text-stone-400">Belum ada inspektur.</span>
      </div>

      <form class="grid gap-2 sm:grid-cols-[1fr_150px_120px_auto] items-end"
            @submit.prevent="formPetugas.post(tautan.tambahPetugas, {
              preserveScroll: true, onSuccess: () => formPetugas.reset() })">
        <div>
          <label :class="label">Tambah dari pengguna</label>
          <select v-model="formPetugas.user_id" :class="isian">
            <option value="">— pilih —</option>
            <option v-for="k in opsi.kandidat" :key="k.id" :value="String(k.id)">{{ k.nama }}</option>
          </select>
        </div>
        <div>
          <label :class="label">Atau nama</label>
          <input v-model="formPetugas.nama" :class="isian">
        </div>
        <div>
          <label :class="label">Peran</label>
          <select v-model="formPetugas.peran" :class="isian">
            <option v-for="r in opsi.peran" :key="r" :value="r">{{ r }}</option>
          </select>
        </div>
        <button type="submit" :disabled="formPetugas.processing"
                class="rounded-xl border border-stone-200 px-3.5 py-2 text-[12px] font-bold
                       text-stone-600 hover:bg-stone-50 transition disabled:opacity-40">Tambah</button>
      </form>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      <div class="px-5 py-4 border-b border-stone-100 flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-[13px] font-bold text-cam-ink">Parameter Pemeriksaan ({{ item.length }})</h3>
        <div class="flex items-center gap-2">
          <select v-model="status"
                  class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12px] font-semibold text-stone-600">
            <option v-for="s in opsi.status" :key="s" :value="s">{{ s }}</option>
          </select>
          <button type="button" :disabled="menyimpan" @click="simpanSemua"
                  class="eq-btn-utama disabled:opacity-40" style="flex:none;padding:8px 16px">
            {{ menyimpan ? 'Menyimpan…' : 'Simpan Hasil' }}
          </button>
        </div>
      </div>

      <div class="divide-y divide-stone-100">
        <div v-for="x in item" :key="x.id" class="p-4">
          <div class="flex items-start justify-between gap-3 mb-2">
            <div class="min-w-0">
              <p class="text-[12.5px] font-semibold text-cam-ink">{{ x.uraian }}</p>
              <p v-if="x.kelompok || x.acuan" class="text-[11px] text-stone-400 mt-0.5">
                <template v-if="x.kelompok">{{ x.kelompok }}</template>
                <template v-if="x.acuan"> · Acuan: {{ x.acuan }}</template>
              </p>
            </div>

            <div class="flex items-center gap-2 shrink-0">
              <a v-if="x.hazard" :href="x.urlHazard!"
                 class="text-[10.5px] font-bold bg-red-100 text-red-700 px-2 py-1 rounded-full hover:underline">
                {{ x.hazard }}
              </a>
              <button v-else-if="baris[x.id].kondisi === 'Tidak Sesuai'" type="button"
                      class="text-[10.5px] font-bold border border-amber-200 bg-amber-50 text-amber-700
                             px-2 py-1 rounded-full hover:bg-amber-100 transition"
                      @click="angkat(x.urlAngkat)">Naikkan ke Hazard</button>

              <button type="button" class="text-[11px] text-stone-300 hover:text-red-500"
                      @click="hapusItem(x.urlHapus)">✕</button>
            </div>
          </div>

          <div class="grid gap-2 sm:grid-cols-[130px_130px_1fr_1fr]">
            <select v-model="baris[x.id].kondisi" :class="isian">
              <option value="">— kondisi —</option>
              <option v-for="k in opsi.kondisi" :key="k" :value="k">{{ k }}</option>
            </select>
            <select v-model="baris[x.id].risiko" :class="isian">
              <option value="">— risiko —</option>
              <option v-for="r in opsi.risiko" :key="r" :value="r">{{ r }}</option>
            </select>
            <input v-model="baris[x.id].temuan" :class="isian" placeholder="Temuan">
            <input v-model="baris[x.id].tindakan" :class="isian" placeholder="Tindakan">
          </div>

          <div v-if="x.foto.length" class="flex gap-2 mt-2">
            <a v-for="(f, n) in x.foto" :key="n" :href="f" target="_blank" rel="noopener">
              <img :src="f" alt="" class="w-16 h-16 object-cover rounded-lg border border-stone-200">
            </a>
          </div>
        </div>

        <p v-if="!item.length" class="px-5 py-10 text-center text-[12.5px] text-stone-400">
          Belum ada parameter. Tambahkan di bawah, atau pilih jenis inspeksi saat membuat.
        </p>
      </div>

      <form class="p-4 bg-stone-50/60 border-t border-stone-100 grid gap-2 sm:grid-cols-[1fr_180px_130px_auto] items-end"
            @submit.prevent="formItem.post(tautan.tambahItem, {
              preserveScroll: true, onSuccess: () => formItem.reset() })">
        <div>
          <label :class="label">Tambah parameter</label>
          <input v-model="formItem.uraian" :class="isian" placeholder="Uraian yang diperiksa">
        </div>
        <div>
          <label :class="label">Kelompok</label>
          <input v-model="formItem.kelompok" :class="isian">
        </div>
        <div>
          <label :class="label">Risiko</label>
          <select v-model="formItem.risiko" :class="isian">
            <option value="">—</option>
            <option v-for="r in opsi.risiko" :key="r" :value="r">{{ r }}</option>
          </select>
        </div>
        <button type="submit" :disabled="formItem.processing || !formItem.uraian.trim()"
                class="rounded-xl border border-stone-200 bg-white px-3.5 py-2 text-[12px] font-bold
                       text-stone-600 hover:bg-stone-50 transition disabled:opacity-40">Tambah</button>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Gudang — stok opname.
 *
 * Selisih dihitung di peramban hanya sebagai bantuan baca. Yang
 * menentukan tetap server: stok buku dapat berubah oleh mutasi orang
 * lain sementara halaman ini terbuka, dan angka di layar tidak boleh
 * dipercaya sebagai dasar penyimpanan.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import BarisMutasi from '../../Components/BarisMutasi.vue';
import { angka, bertanda } from '../../angka';
import type { HalamanOpnameGudang } from '../../types';

const props = defineProps<HalamanOpnameGudang>();

/**
 * Jumlah fisik per id barang.
 *
 * v-model pada <input type="number"> mengembalikan angka, bukan teks,
 * dan mengosongkannya menghasilkan '' — jadi isinya bisa berupa
 * keduanya. Semua pembacaan lewat isi() supaya perbedaan itu tidak
 * merembes ke tempat lain; sempat terjadi .trim() dipanggil atas angka
 * dan seluruh tabelnya lenyap saat isian pertama diketik.
 */
const fisik = reactive<Record<number, string | number>>(
  Object.fromEntries(props.barang.map((b) => [b.id, ''])),
);

const isi = (id: number): string => String(fisik[id] ?? '').trim();

const selisih = (id: number, buku: number): number | null => {
  if (isi(id) === '') return null;

  const n = parseFloat(isi(id));
  return isFinite(n) ? Math.round((n - buku) * 100) / 100 : null;
};

const terisi = computed(() => props.barang.filter((b) => isi(b.id) !== '').length);

const berselisih = computed(() =>
  props.barang.filter((b) => {
    const s = selisih(b.id, b.buku);
    return s !== null && Math.abs(s) >= 0.005;
  }).length,
);

const form = useForm({ tanggal: props.hariIni, keterangan: '', fisik: {} as Record<number, string> });

function simpan() {
  // Baris kosong tidak dikirim: server memaknai kosong sebagai "tidak
  // dihitung", dan mengirimnya sebagai string kosong menambah beban
  // permintaan tanpa mengubah hasilnya.
  form.fisik = Object.fromEntries(
    props.barang.filter((b) => isi(b.id) !== '').map((b) => [b.id, isi(b.id)]),
  );

  form.post(props.tautan.simpan);
}

const isian = 'rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px] ring-focus transition';
const label = 'block text-[12px] font-semibold text-cam-ink mb-1.5';
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-[1200px] mx-auto space-y-5">

    <p v-if="form.errors.fisik" class="rounded-xl bg-red-50 border border-red-100 px-4 py-3
                                       text-[12.5px] font-semibold text-red-700">
      {{ form.errors.fisik }}
    </p>

    <form v-if="bolehCatat" class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden"
          @submit.prevent="simpan">

      <div class="px-6 py-5 border-b border-stone-100 flex flex-wrap items-end gap-4">
        <div>
          <label :class="label">Tanggal Opname</label>
          <input v-model="form.tanggal" type="date" :class="isian" aria-label="Tanggal">
        </div>
        <div class="flex-1 min-w-[220px]">
          <label :class="label">Keterangan</label>
          <input v-model="form.keterangan" :class="[isian, 'w-full']"
                 placeholder="Opname bulanan, pemeriksaan mendadak, dll.">
        </div>
      </div>

      <div class="px-6 py-3.5 bg-stone-50 border-b border-stone-100">
        <p class="text-[12px] text-stone-600">
          Isi <b>jumlah fisik</b> hanya untuk barang yang dihitung. Baris yang dikosongkan
          tidak diubah, dan baris yang jumlahnya sama dengan stok buku tidak dicatat —
          opname tanpa selisih tidak mengubah apa pun.
        </p>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-[12.5px]">
          <thead>
            <tr class="text-left text-stone-500 border-b border-stone-100">
              <th class="py-3 px-4 font-semibold">Barang</th>
              <th class="py-3 px-3 font-semibold">Lokasi</th>
              <th class="py-3 px-3 font-semibold text-right">Stok Buku</th>
              <th class="py-3 px-3 font-semibold text-right w-40">Jumlah Fisik</th>
              <th class="py-3 px-4 font-semibold text-right">Selisih</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in barang" :key="b.id" class="border-b border-stone-50 last:border-0">
              <td class="py-2.5 px-4">
                <span class="font-semibold text-cam-ink">{{ b.nama }}</span>
                <span class="block text-[11px] text-stone-400">{{ b.kode }}</span>
              </td>
              <td class="py-2.5 px-3 text-stone-500">{{ b.lokasi ?? '—' }}</td>
              <td class="py-2.5 px-3 text-right font-bold tabular-nums text-cam-ink">
                {{ angka(b.buku) }} <span class="text-stone-400 font-normal">{{ b.satuan }}</span>
              </td>
              <td class="py-2.5 px-3">
                <input v-model="fisik[b.id]" type="number" step="0.01" min="0"
                       class="w-full rounded-lg border border-stone-200 px-3 py-2 text-[12.5px]
                              text-right ring-focus transition">
              </td>
              <td class="py-2.5 px-4 text-right font-bold tabular-nums"
                  :class="selisih(b.id, b.buku) === null ? 'text-stone-300'
                        : selisih(b.id, b.buku) === 0 ? 'text-stone-400'
                        : selisih(b.id, b.buku)! > 0 ? 'text-[#4A8E2C]' : 'text-[#C03A3A]'">
                <template v-if="selisih(b.id, b.buku) === null">—</template>
                <template v-else-if="selisih(b.id, b.buku) === 0">cocok</template>
                <template v-else>{{ bertanda(selisih(b.id, b.buku)!) }}</template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="px-6 py-4 bg-stone-50 border-t border-stone-100
                  flex items-center justify-between gap-3 flex-wrap">
        <p class="text-[12px] text-stone-500">
          <b class="text-cam-ink">{{ berselisih }}</b> baris berselisih dari
          <b class="text-cam-ink">{{ terisi }}</b> yang dihitung
        </p>
        <button type="submit" :disabled="form.processing || terisi === 0"
                class="eq-btn-utama disabled:opacity-40" style="flex:none;padding:10px 22px">
          {{ form.processing ? 'Menyimpan…' : 'Simpan Opname' }}
        </button>
      </div>
    </form>

    <section class="eq-panel">
      <div class="eq-panel-kepala"><h3>Opname Terakhir</h3></div>

      <div v-if="!lalu.length" class="eq-kosong">
        <strong>Belum pernah ada opname</strong>
        <p>Hasil hitung fisik yang berselisih akan tercatat di sini.</p>
      </div>

      <ul v-else class="space-y-1">
        <BarisMutasi v-for="m in lalu" :key="m.id" :m="m" />
      </ul>
    </section>

  </div>
</template>

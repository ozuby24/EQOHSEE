<script setup lang="ts">
/**
 * Daftar harga — layar penjual.
 *
 * ── SATU BARIS SEKALI SIMPAN ──
 *
 * Bukan satu tombol simpan untuk seluruh tabel. Dua puluh dua baris
 * yang dikirim serentak berarti satu galat pada baris ke sembilan
 * menggagalkan seluruhnya, dan yang mengisinya tidak punya cara tahu
 * baris mana yang sudah tersimpan dan mana yang belum — sedangkan yang
 * salah di sini adalah harga.
 *
 * ── NOL TIDAK DAPAT AKTIF ──
 *
 * Butir aktif berharga nol dapat dipesan, ditagihkan, "dibayar", dan
 * lisensinya terbit tanpa satu rupiah pun masuk. Server yang menolaknya;
 * layar ini hanya mengatakannya lebih dulu, supaya penolakannya tidak
 * terbaca sebagai tombol yang rusak.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { propHalaman } from '../../halaman';

const props = propHalaman();

type Baris = {
  id: number; kode: string; nama: string; jenis: string;
  keterangan: string | null; harga: number; masa_bulan: number;
  aktif: boolean; bolehAktif: boolean;
};

/** Salinan yang disunting. Yang asli tetap utuh sampai simpanannya kembali. */
const draf = reactive<Record<number, { harga: number; masa_bulan: number; aktif: boolean }>>({});

const baris = computed<Baris[]>(() => props.baris ?? []);

for (const b of (props.baris ?? []) as Baris[]) {
  draf[b.id] = { harga: b.harga, masa_bulan: b.masa_bulan, aktif: b.aktif };
}

const menyimpan = reactive<Record<number, boolean>>({});

const jumlahAktif = computed(() => baris.value.filter((b) => b.aktif).length);

function rupiah(n: number) {
  return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
}

function simpan(b: Baris) {
  const d = draf[b.id];

  menyimpan[b.id] = true;

  router.put(`/pembelian/produk/${b.id}`, {
    harga: Number(d.harga) || 0,
    masa_bulan: Number(d.masa_bulan) || 0,
    aktif: d.aktif,
  }, {
    preserveScroll: true,
    onFinish: () => { menyimpan[b.id] = false; },
  });
}

function berubah(b: Baris) {
  const d = draf[b.id];

  return Number(d.harga) !== b.harga
    || Number(d.masa_bulan) !== b.masa_bulan
    || d.aktif !== b.aktif;
}
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1400px] mx-auto space-y-5">

    <section class="flex flex-wrap items-end gap-3">
      <div>
      </div>
      <a :href="props.tautanEtalase" target="_blank" rel="noopener"
         class="ml-auto eq-btn-lain">Lihat etalase publik</a>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card px-5 py-3.5
                    flex flex-wrap items-center gap-x-6 gap-y-1.5">
      <span class="text-[12.5px] text-stone-500">
        <span class="num font-bold text-cam-ink">{{ jumlahAktif }}</span> dari
        <span class="num font-bold text-cam-ink">{{ baris.length }}</span> butir dijual
      </span>
      <span class="text-[11.5px] text-stone-400">
        Butir tak aktif tidak muncul di etalase dan tidak dapat dipesan.
      </span>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-[12.5px]">
          <thead>
            <tr class="text-left text-[11px] uppercase tracking-wide text-stone-400 border-b border-stone-100">
              <th class="px-5 py-3 font-bold">Butir</th>
              <th class="px-3 py-3 font-bold w-40">Harga (Rp)</th>
              <th class="px-3 py-3 font-bold w-32">Masa (bulan)</th>
              <th class="px-3 py-3 font-bold w-24">Dijual</th>
              <th class="px-5 py-3 font-bold w-28"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100">
            <tr v-for="b in baris" :key="b.id" :class="b.aktif ? '' : 'bg-stone-50/50'">
              <td class="px-5 py-3">
                <span class="block font-semibold text-cam-ink">{{ b.nama }}</span>
                <span class="block num text-[10.5px] text-stone-400 mt-0.5">
                  {{ b.kode }} · {{ b.jenis === 'website' ? 'Paket' : 'Aplikasi' }}
                </span>
              </td>

              <td class="px-3 py-3">
                <input v-model.number="draf[b.id].harga" type="number" min="0" step="1000"
                       class="beli-isian num" />
                <span class="block num text-[10.5px] text-stone-400 mt-1">
                  {{ rupiah(draf[b.id].harga) }}
                </span>
              </td>

              <td class="px-3 py-3">
                <input v-model.number="draf[b.id].masa_bulan" type="number" min="0" max="600"
                       class="beli-isian num" />
                <span class="block text-[10.5px] text-stone-400 mt-1">
                  {{ draf[b.id].masa_bulan === 0 ? 'selamanya' : draf[b.id].masa_bulan + ' bulan' }}
                </span>
              </td>

              <!-- Sakelar, bukan kotak centang.

                   Preflight Tailwind memasang appearance:none pada
                   kendali formulir, sehingga centang bawaan peramban
                   tidak digambar sama sekali; pada mode gelap yang
                   tersisa kotak kosong abu tua di atas kartu abu tua —
                   dua puluh dua butir yang semuanya terjual terbaca
                   sebagai dua puluh dua butir yang tidak satu pun
                   terjual. Sakelar ini menyatakan keadaannya dengan kata
                   dan warna, dua hal yang tidak bergantung pada
                   penggambaran bawaan peramban. -->
              <td class="px-3 py-3">
                <button type="button" role="switch"
                        :aria-checked="draf[b.id].aktif && Number(draf[b.id].harga) > 0"
                        :disabled="Number(draf[b.id].harga) <= 0"
                        class="harga-sakelar"
                        :class="draf[b.id].aktif && Number(draf[b.id].harga) > 0
                          ? 'harga-sakelar-hidup' : 'harga-sakelar-mati'"
                        @click="draf[b.id].aktif = !draf[b.id].aktif">
                  {{ draf[b.id].aktif && Number(draf[b.id].harga) > 0 ? 'Dijual' : 'Tidak' }}
                </button>
                <span v-if="Number(draf[b.id].harga) <= 0"
                      class="block text-[10.5px] text-amber-700 mt-1 leading-snug">
                  Isi harganya lebih dulu
                </span>
              </td>

              <td class="px-5 py-3 text-right">
                <button type="button" class="eq-btn-utama"
                        :disabled="!berubah(b) || menyimpan[b.id]"
                        @click="simpan(b)">
                  Simpan
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <p class="text-[11.5px] text-stone-400 leading-relaxed max-w-3xl">
      Daftar butirnya diturunkan dari menu aplikasi lewat
      <span class="num font-semibold">php artisan pembelian:katalog</span> — menjalankannya ulang
      menambahkan modul baru dan menonaktifkan modul yang sudah tidak ada, tanpa menyentuh harga
      yang sudah diisi.
    </p>
  </div>
</template>

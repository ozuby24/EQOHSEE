<script setup lang="ts">
/**
 * Katalog: website sebagai satu kesatuan, dan aplikasi satuan.
 *
 * ── TOTALNYA DIHITUNG DI LAYAR HANYA UNTUK DIBACA ──
 *
 * Angka di bawah ini pengingat bagi yang memilih, BUKAN nilai yang
 * dikirim. Yang dikirim hanya daftar id beserta banyaknya; harganya
 * dibaca ulang server dari basis data. Kalau totalnya ikut dikirim,
 * menyunting satu medan tersembunyi cukup untuk memesan seluruh
 * katalog seharga nol rupiah — dan tagihannya akan terlihat wajar
 * sepenuhnya di layar admin, sebab nol itu memang yang tersimpan.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { propHalaman } from '../../halaman';

const props = propHalaman();

/** id produk => banyaknya. Nol berarti tidak dipilih. */
const pilih = reactive<Record<number, number>>({});

const semua = computed<any[]>(() => [
  ...(props.website ?? []),
  ...(props.aplikasi ?? []),
]);

const terpilih = computed(() =>
  semua.value.filter((p) => (pilih[p.id] ?? 0) > 0));

const total = computed(() =>
  terpilih.value.reduce((n, p) => n + p.harga * (pilih[p.id] ?? 0), 0));

function rupiah(n: number) {
  return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
}

function ubah(id: number, n: number) {
  pilih[id] = Math.max(0, Math.min(99, (pilih[id] ?? 0) + n));
}

const f = useForm<Record<string, any>>({
  pembeli_nama: '', pembeli_perusahaan: '', pembeli_email: '',
  pembeli_telepon: '', catatan: '', produk: {} as Record<number, number>,
});

function kirim() {
  f.produk = Object.fromEntries(
    terpilih.value.map((p) => [p.id, pilih[p.id]]));

  f.post('/pembelian/pesan', { preserveScroll: true });
}
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1400px] mx-auto space-y-5">


    <!-- Katalog kosong menyebut SEBABNYA. Halaman putih tanpa
         keterangan terbaca sebagai aplikasi yang rusak. -->
    <section v-if="props.kosong"
             class="rounded-2xl bg-white border border-amber-300 shadow-card px-5 py-4">
      <p class="text-[13px] font-bold text-amber-700">Katalog masih kosong</p>
      <p class="text-[12.5px] text-stone-600 mt-1">
        Jalankan <span class="num font-semibold">php artisan pembelian:katalog</span> untuk memasang
        daftar produknya, lalu isi harga tiap butir dan aktifkan. Butir berharga nol
        sengaja tidak aktif supaya tidak dapat terjual tanpa disengaja.
      </p>
    </section>

    <template v-else>
      <div class="grid gap-4 lg:grid-cols-[1.35fr_.65fr] items-start">

        <div class="grid gap-4">
          <!-- paket menyeluruh -->
          <section v-if="(props.website ?? []).length"
                   class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
            <header class="px-5 py-3.5 border-b border-stone-100">
              <h3 class="text-[13.5px] font-bold text-cam-ink">Website</h3>
              <p class="text-[11.5px] text-stone-500 mt-0.5">Seluruh aplikasi dalam satu paket</p>
            </header>

            <ul class="divide-y divide-stone-100">
              <li v-for="p in props.website" :key="p.id" class="px-5 py-4 flex items-start gap-4">
                <span class="min-w-0 flex-1">
                  <span class="block text-[13px] font-bold text-cam-ink">{{ p.nama }}</span>
                  <span v-if="p.keterangan" class="block text-[11.5px] text-stone-500 mt-0.5">
                    {{ p.keterangan }}
                  </span>
                  <span class="block text-[11px] text-stone-400 mt-1">{{ p.masa }}</span>
                </span>

                <span class="shrink-0 text-right">
                  <span class="block text-[14px] font-bold num text-cam-ink">{{ rupiah(p.harga) }}</span>
                  <span class="inline-flex items-center gap-2 mt-1.5">
                    <button type="button" class="beli-plusmin" aria-label="Kurangi"
                            @click="ubah(p.id, -1)">−</button>
                    <span class="num w-6 text-center text-[13px] font-semibold">{{ pilih[p.id] ?? 0 }}</span>
                    <button type="button" class="beli-plusmin" aria-label="Tambah"
                            @click="ubah(p.id, 1)">+</button>
                  </span>
                </span>
              </li>
            </ul>
          </section>

          <!-- aplikasi satuan -->
          <section v-if="(props.aplikasi ?? []).length"
                   class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
            <header class="px-5 py-3.5 border-b border-stone-100">
              <h3 class="text-[13.5px] font-bold text-cam-ink">
                Aplikasi satuan
                <span class="font-normal text-stone-400">| {{ props.aplikasi.length }} aplikasi</span>
              </h3>
            </header>

            <ul class="divide-y divide-stone-100">
              <li v-for="p in props.aplikasi" :key="p.id" class="px-5 py-3 flex items-center gap-4">
                <span class="min-w-0 flex-1">
                  <span class="block text-[12.5px] font-semibold text-cam-ink">{{ p.nama }}</span>
                  <span class="block text-[11px] text-stone-400">{{ p.masa }}</span>
                </span>
                <span class="num text-[12.5px] font-bold text-cam-ink shrink-0">{{ rupiah(p.harga) }}</span>
                <span class="inline-flex items-center gap-2 shrink-0">
                  <button type="button" class="beli-plusmin" aria-label="Kurangi"
                          @click="ubah(p.id, -1)">−</button>
                  <span class="num w-6 text-center text-[13px] font-semibold">{{ pilih[p.id] ?? 0 }}</span>
                  <button type="button" class="beli-plusmin" aria-label="Tambah"
                          @click="ubah(p.id, 1)">+</button>
                </span>
              </li>
            </ul>
          </section>
        </div>

        <!-- ringkasan dan data pembeli -->
        <form class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden
                     lg:sticky lg:top-4"
              @submit.prevent="kirim">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">Ringkasan</h3>
          </header>

          <ul v-if="terpilih.length" class="px-5 py-3 space-y-1.5 border-b border-stone-100">
            <li v-for="p in terpilih" :key="p.id" class="flex items-baseline gap-2 text-[12px]">
              <span class="min-w-0 flex-1 text-stone-600 truncate">{{ p.nama }}</span>
              <span class="num text-stone-400">×{{ pilih[p.id] }}</span>
              <span class="num font-semibold text-cam-ink">{{ rupiah(p.harga * pilih[p.id]) }}</span>
            </li>
          </ul>
          <p v-else class="px-5 py-4 text-[12px] text-stone-400">Belum ada yang dipilih.</p>

          <div class="px-5 py-3 flex items-baseline justify-between border-b border-stone-100">
            <span class="text-[12px] font-semibold text-stone-500">Total</span>
            <span class="num text-[17px] font-bold text-cam-ink">{{ rupiah(total) }}</span>
          </div>

          <div class="px-5 py-4 grid gap-2.5">
            <input v-model="f.pembeli_nama" required placeholder="Nama pembeli"
                   class="beli-isian" />
            <input v-model="f.pembeli_perusahaan" placeholder="Perusahaan (opsional)"
                   class="beli-isian" />
            <input v-model="f.pembeli_email" type="email" placeholder="Email (opsional)"
                   class="beli-isian" />
            <input v-model="f.pembeli_telepon" placeholder="Telepon / WhatsApp (opsional)"
                   class="beli-isian" />
            <textarea v-model="f.catatan" rows="2" placeholder="Catatan (opsional)"
                      class="beli-isian"></textarea>

            <p v-if="f.errors.produk" class="text-[11.5px] text-red-600">{{ f.errors.produk }}</p>

            <button type="submit" class="eq-btn-utama justify-center"
                    :disabled="!terpilih.length || f.processing">
              Buat tagihan
            </button>

            <p class="text-[10.5px] text-stone-400 leading-snug">
              Tagihan dibuat lebih dulu. Pembayaran dilakukan lewat tautan yang muncul
              sesudahnya, dan lisensinya terbit setelah bukti bayar diperiksa.
            </p>
          </div>
        </form>
      </div>
    </template>
  </div>
</template>

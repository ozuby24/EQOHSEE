<script setup lang="ts">
/**
 * Satu tagihan: rincian, bukti bayar, verifikasi, dan lisensinya.
 *
 * Tombol verifikasi hanya muncul bagi admin. Menyembunyikannya dari
 * yang lain bukan penjagaan — penjagaannya di controller; ini hanya
 * supaya orang tidak menekan tombol yang pasti ditolak.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { propHalaman } from '../../halaman';
import { useDialog } from '../../dialog';
import Dialog from '../../Components/Dialog.vue';

const props = propHalaman();
const { dialog, tanya, minta, batal, lanjut } = useDialog();

const p = () => props.pesanan ?? {};
const admin = () => props.pengguna?.admin === true;
const bukaTolak = ref(false);
const alasan = ref('');

function rupiah(n: number) {
  return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
}

const basis = () => `/pembelian/tagihan/${p().id}`;

async function verifikasi() {
  if (await tanya(`Nyatakan tagihan ${p().nomor} LUNAS senilai ${rupiah(p().total)}? `
    + 'Lisensinya langsung terbit dan tindakan ini tidak dapat diurungkan.')) {
    router.post(`${basis()}/verifikasi`, {}, { preserveScroll: true });
  }
}

function tolak() {
  router.post(`${basis()}/tolak`, { alasan: alasan.value }, {
    preserveScroll: true,
    onSuccess: () => { bukaTolak.value = false; alasan.value = ''; },
  });
}

async function batalkan() {
  if (await tanya(`Batalkan tagihan ${p().nomor}?`)) {
    router.post(`${basis()}/batal`, {}, { preserveScroll: true });
  }
}

async function salin(teks: string) {
  try { await navigator.clipboard.writeText(teks); } catch { /* peramban lama */ }
}
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1100px] mx-auto space-y-5">

    <section class="flex flex-wrap items-start justify-between gap-4">
      <div class="min-w-0">
        <p class="text-[11.5px] text-stone-400">
          <Link href="/pembelian/tagihan" class="hover:underline">← Semua tagihan</Link>
        </p>
        <h2 class="text-xl font-bold text-cam-ink">{{ props.judul }}</h2>
        <p class="text-[12.5px] text-stone-500 mt-0.5">{{ p().pembeli }}
          <span v-if="p().perusahaan">· {{ p().perusahaan }}</span>
        </p>
      </div>
      <span class="rounded-lg px-3 py-2 text-[12px] font-bold shrink-0"
            style="background:#F6EEDF;color:#0F766E">{{ p().statusLabel }}</span>
    </section>

    <div class="grid gap-4 lg:grid-cols-[1.3fr_.7fr] items-start">
      <div class="grid gap-4">

        <!-- rincian -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">Rincian</h3>
          </header>
          <ul class="divide-y divide-stone-100">
            <li v-for="i in (p().items ?? [])" :key="i.id"
                class="px-5 py-2.5 flex items-baseline gap-3 text-[12.5px]">
              <span class="min-w-0 flex-1">{{ i.nama }}</span>
              <span class="num text-stone-400">×{{ i.jumlah }}</span>
              <span class="num font-semibold">{{ rupiah(i.subtotal) }}</span>
            </li>
          </ul>
          <div class="px-5 py-3 flex items-baseline justify-between border-t border-stone-100"
               style="background:#FBF5EA">
            <span class="text-[12.5px] font-semibold text-stone-600">Total</span>
            <span class="num text-[18px] font-bold text-cam-ink">{{ rupiah(p().total) }}</span>
          </div>
        </section>

        <!-- bukti bayar -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">
              Bukti pembayaran
              <span class="font-normal text-stone-400">| {{ (p().pembayaran ?? []).length }} kiriman</span>
            </h3>
          </header>

          <ul v-if="(p().pembayaran ?? []).length" class="divide-y divide-stone-100">
            <li v-for="b in p().pembayaran" :key="b.id" class="px-5 py-3">
              <p class="text-[12.5px] font-semibold text-cam-ink">
                {{ b.metode }} · <span class="num">{{ rupiah(b.jumlah) }}</span>
              </p>
              <p class="text-[11.5px] text-stone-500 mt-0.5">
                a.n. {{ b.atasNama }} <span v-if="b.tanggal">· <span class="num">{{ b.tanggal }}</span></span>
              </p>
              <p v-if="b.catatan" class="text-[11.5px] text-stone-500 mt-0.5">{{ b.catatan }}</p>
              <a v-if="b.bukti" :href="b.bukti" target="_blank" rel="noopener"
                 class="inline-block mt-1.5 text-[11.5px] font-semibold" style="color:#0F766E">
                Buka berkas buktinya →
              </a>
            </li>
          </ul>
          <p v-else class="px-5 py-4 text-[12.5px] text-stone-400">Pembeli belum mengirim bukti.</p>
        </section>

        <!-- lisensi -->
        <section v-if="(p().lisensi ?? []).length"
                 class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">Lisensi terbit</h3>
          </header>
          <ul class="divide-y divide-stone-100">
            <li v-for="l in p().lisensi" :key="l.id" class="px-5 py-3 flex items-start gap-3">
              <span class="min-w-0 flex-1">
                <span class="block text-[12.5px] font-semibold text-cam-ink">{{ l.produk }}</span>
                <span class="block num text-[13px] tracking-wider mt-0.5" style="color:#0F766E">{{ l.kunci }}</span>
                <span class="block text-[11px] text-stone-400 mt-0.5">
                  {{ l.mulai }} → {{ l.berakhir ?? 'tanpa batas' }}
                </span>
              </span>
              <button type="button" class="text-[11px] font-semibold shrink-0"
                      style="color:#0F766E" @click="salin(l.kunci)">salin</button>
            </li>
          </ul>
        </section>
      </div>

      <!-- kolom kanan -->
      <div class="grid gap-4">

        <!-- tautan bayar -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">Tautan bayar</h3>
            <p class="text-[11.5px] text-stone-500 mt-0.5">Kirimkan ini kepada pembeli</p>
          </header>
          <div class="px-5 py-4">
            <p class="text-[11px] text-stone-500 break-all">{{ props.tautanBayar }}</p>
            <button type="button" class="eq-btn-lain mt-2" @click="salin(props.tautanBayar)">
              Salin tautan
            </button>
            <p class="text-[10.5px] text-stone-400 mt-2 leading-snug">
              Tautannya memuat token acak dan tidak dapat ditebak. Siapa pun yang
              memegangnya dapat membuka tagihan ini — kirimkan hanya kepada pembelinya.
            </p>
          </div>
        </section>

        <!-- keputusan -->
        <section v-if="admin() && !p().selesai"
                 class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">Keputusan</h3>
            <p class="text-[11.5px] text-stone-500 mt-0.5">
              Periksa buktinya di rekening lebih dulu, bukan hanya gambarnya
            </p>
          </header>

          <div class="px-5 py-4 grid gap-2">
            <button type="button" class="eq-btn-utama justify-center" @click="verifikasi">
              Nyatakan lunas
            </button>

            <button v-if="!bukaTolak" type="button" class="eq-btn-lain justify-center"
                    @click="bukaTolak = true">Tolak buktinya</button>

            <div v-else class="grid gap-2">
              <textarea v-model="alasan" rows="2" required
                        placeholder="Alasan penolakan — dibaca pembeli"
                        class="beli-isian"></textarea>
              <div class="flex gap-2">
                <button type="button" class="eq-btn-lain flex-1 justify-center"
                        @click="bukaTolak = false">Batal</button>
                <button type="button" class="eq-btn-utama flex-1 justify-center"
                        :disabled="!alasan.trim()" @click="tolak">Kirim penolakan</button>
              </div>
            </div>

            <button type="button" class="text-[11.5px] text-red-600 mt-1" @click="batalkan">
              Batalkan tagihan
            </button>
          </div>
        </section>

        <section v-if="p().alasanTolak"
                 class="rounded-2xl px-5 py-4 border border-red-300 bg-red-50">
          <p class="text-[12.5px] font-bold text-red-700">Bukti ditolak</p>
          <p class="text-[12px] text-stone-700 mt-1">{{ p().alasanTolak }}</p>
        </section>

        <section v-if="p().diverifikasiOleh"
                 class="rounded-2xl bg-white border border-stone-100 shadow-card px-5 py-4">
          <p class="text-[11px] uppercase tracking-wide text-stone-400">Diverifikasi oleh</p>
          <p class="text-[12.5px] font-semibold text-cam-ink mt-0.5">{{ p().diverifikasiOleh }}</p>
        </section>
      </div>
    </div>
  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>

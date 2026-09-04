<script setup lang="ts">
/**
 * Halaman bayar — dibuka pembeli lewat tautan bertoken, tanpa login.
 *
 * ── QR DIGAMBAR DI PERAMBAN, DARI PUSTAKA YANG IKUT DIBUNDEL ──
 *
 * Payload-nya datang dari server; yang digambar di sini hanya gambarnya.
 * Pustakanya diimpor, bukan diambil dari CDN saat halaman terbuka.
 *
 * Sempat memakai CDN, dan sekali dicoba langsung terlihat akibatnya:
 * di jaringan yang memblokir CDN, halaman bayarnya sampai ke pembeli
 * tanpa QR sama sekali. Tambang kerap berada di jaringan seperti itu —
 * dan pembeli yang gagal membayar tidak akan melaporkannya, ia hanya
 * berhenti.
 *
 * Penjagaan lamanya tetap dipertahankan: bila penggambaran gagal karena
 * sebab apa pun, halamannya MENYEBUTKAN sebabnya dan menawarkan salinan
 * kodenya. Kotak kosong tanpa keterangan terbaca sebagai ponsel yang
 * bermasalah, dan yang membacanya mencoba berkali-kali sebelum
 * menyerah.
 *
 * ── YANG TIDAK ADA DI HALAMAN INI ──
 *
 * Tidak ada bilah samping, tidak ada tautan ke bagian mana pun dari
 * aplikasi, dan tidak ada tagihan lain. Tanpa sesi, tidak ada yang
 * dapat dipakai memastikan siapa yang membuka tautannya — jadi yang
 * ditampilkan hanya tagihan itu sendiri.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import QRCode from 'qrcode';
import { propHalaman } from '../../halaman';
import PublicLayout from '../../Layouts/PublicLayout.vue';

/* Kerangka publik, BUKAN AppLayout. Yang membuka halaman ini belum
   tentu punya akun; menggambarkan bilah samping berisi dua puluh empat
   modul kepada orang yang hanya hendak membayar tagihan memperlihatkan
   seluruh isi aplikasi kepada orang luar. */
defineOptions({ layout: PublicLayout });

const props = propHalaman();

const kanvas = ref<HTMLCanvasElement | null>(null);
const gagalQr = ref<string | null>(null);

function rupiah(n: number) {
  return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
}

/** Gambar QR-nya ke kanvas. */
onMounted(async () => {
  const payload = props.tujuan?.qris?.payload;

  if (!payload || !kanvas.value) return;

  try {
    await QRCode.toCanvas(kanvas.value, payload, {
      width: 240,
      margin: 1,

      /* Tingkat koreksi M. Payload QRIS panjang — 250-400 karakter —
         dan tingkat H membuat modulnya begitu rapat sehingga kamera
         ponsel lama gagal membacanya pada layar berukuran sedang. */
      errorCorrectionLevel: 'M',
      color: { dark: '#0F1720', light: '#FFFFFF' },
    });
  } catch {
    gagalQr.value = 'Gambar QR gagal dibuat. Salin kode di bawah lalu tempel '
      + 'di aplikasi pembayaran yang mendukung QRIS.';
  }
});

const f = useForm<Record<string, any>>({
  metode: 'qris', atas_nama: '', tanggal_bayar: '', catatan: '',
  bukti: null as File | null,
});

function kirim() {
  f.post(`/bayar/${props.token}/bukti`, {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => f.reset(),
  });
}

async function salin(teks: string) {
  try { await navigator.clipboard.writeText(teks); } catch { /* peramban lama */ }
}
</script>

<template>
  <Head :title="`Pembayaran ${props.pesanan?.nomor ?? ''}`" />

  <div class="max-w-[760px] mx-auto space-y-4 py-6 px-4">

    <!-- ══════════ kepala ══════════ -->
    <section class="text-center">
      <h2 class="text-xl font-bold text-cam-ink">Pembayaran</h2>
      <p class="text-[12.5px] text-stone-500 mt-1">
        Tagihan <span class="num font-semibold">{{ props.pesanan?.nomor }}</span>
        atas nama {{ props.pesanan?.pembeli }}
      </p>
    </section>

    <!-- ══════════ rincian tagihan ══════════ -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <ul class="divide-y divide-stone-100">
        <li v-for="(i, n) in (props.pesanan?.items ?? [])" :key="n"
            class="px-5 py-2.5 flex items-baseline gap-3 text-[12.5px]">
          <span class="min-w-0 flex-1 text-stone-700">{{ i.nama }}</span>
          <span class="num text-stone-400">×{{ i.jumlah }}</span>
          <span class="num font-semibold text-cam-ink">{{ rupiah(i.subtotal) }}</span>
        </li>
      </ul>

      <div class="px-5 py-3.5 flex items-baseline justify-between border-t border-stone-100"
           style="background:#FBF5EA">
        <span class="text-[12.5px] font-semibold text-stone-600">Total tagihan</span>
        <span class="num text-[19px] font-bold text-cam-ink">{{ rupiah(props.pesanan?.total) }}</span>
      </div>
    </section>

    <!-- ══════════ keadaan yang menutup pembayaran ══════════ -->
    <section v-if="!props.pesanan?.bolehDibayar"
             class="rounded-2xl px-5 py-4 border"
             :class="props.pesanan?.status === 'lunas'
               ? 'border-green-300 bg-green-50' : 'border-stone-200 bg-stone-50'">
      <p class="text-[13px] font-bold"
         :class="props.pesanan?.status === 'lunas' ? 'text-green-700' : 'text-stone-700'">
        {{ props.pesanan?.statusLabel }}
      </p>
      <p v-if="props.pesanan?.status === 'lunas'" class="text-[12.5px] text-stone-600 mt-1">
        Pembayaran sudah diterima dan diperiksa. Lisensinya sudah diterbitkan.
      </p>
      <p v-else-if="props.pesanan?.status === 'menunggu_verifikasi'" class="text-[12.5px] text-stone-600 mt-1">
        Bukti pembayaran sudah kami terima dan sedang diperiksa. Halaman ini akan
        menyebutkan hasilnya.
      </p>
      <p v-else-if="props.pesanan?.status === 'kedaluwarsa'" class="text-[12.5px] text-stone-600 mt-1">
        Tagihan ini melewati batas waktunya. Hubungi penjual untuk tagihan baru.
      </p>
    </section>

    <template v-else>
      <!-- Bukti sebelumnya ditolak — sebabnya disebut, bukan disembunyikan. -->
      <section v-if="props.pesanan?.alasanTolak"
               class="rounded-2xl px-5 py-4 border border-red-300 bg-red-50">
        <p class="text-[13px] font-bold text-red-700">Bukti sebelumnya belum dapat diterima</p>
        <p class="text-[12.5px] text-stone-700 mt-1">{{ props.pesanan.alasanTolak }}</p>
        <p class="text-[11.5px] text-stone-500 mt-1">Silakan kirim ulang lewat formulir di bawah.</p>
      </section>

      <!-- ══════════ QRIS ══════════ -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <header class="px-5 py-3.5 border-b border-stone-100">
          <h3 class="text-[13.5px] font-bold text-cam-ink">Bayar dengan QRIS</h3>
          <p class="text-[11.5px] text-stone-500 mt-0.5">
            Pindai dengan aplikasi bank atau dompet digital apa pun yang mendukung QRIS
          </p>
        </header>

        <div v-if="props.tujuan?.qris?.ada" class="px-5 py-5 text-center">
          <canvas ref="kanvas" class="inline-block bg-white p-2 rounded-xl border border-stone-200"
                  role="img" aria-label="Kode QRIS pembayaran"></canvas>

          <p v-if="props.tujuan.qris.merchant" class="text-[12px] text-stone-600 mt-3">
            Pastikan nama yang muncul di aplikasi Anda:
            <span class="font-bold text-cam-ink">{{ props.tujuan.qris.merchant }}</span>
          </p>

          <p v-if="props.tujuan.qris.dinamis" class="text-[12px] mt-1.5">
            Nominal <span class="num font-bold">{{ rupiah(props.pesanan?.total) }}</span>
            sudah tercantum di dalam kode — tidak perlu diketik.
          </p>
          <p v-else class="text-[12px] mt-1.5 text-amber-700">
            Masukkan nominal <span class="num font-bold">{{ rupiah(props.pesanan?.total) }}</span>
            sendiri saat memindai.
          </p>

          <p v-if="gagalQr" class="text-[11.5px] text-amber-700 mt-3">{{ gagalQr }}</p>

          <button v-if="gagalQr" type="button" class="eq-btn-lain mt-2"
                  @click="salin(props.tujuan.qris.payload)">Salin kode QRIS</button>
        </div>

        <!-- Konfigurasi belum diisi: sebabnya ditulis, bukan kotak kosong. -->
        <div v-else class="px-5 py-4" style="background:#FEF3C7">
          <p class="text-[12.5px] font-bold text-amber-800">QRIS belum tersedia</p>
          <p class="text-[12px] text-stone-700 mt-1">{{ props.tujuan?.qris?.sebab }}</p>
          <p class="text-[11.5px] text-stone-600 mt-1">
            Sementara ini, gunakan transfer bank di bawah.
          </p>
        </div>
      </section>

      <!-- ══════════ transfer bank ══════════ -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <header class="px-5 py-3.5 border-b border-stone-100">
          <h3 class="text-[13.5px] font-bold text-cam-ink">Atau transfer bank</h3>
        </header>

        <div v-if="props.tujuan?.bank?.ada" class="px-5 py-4">
          <p class="text-[12px] text-stone-500">{{ props.tujuan.bank.nama }}</p>
          <p class="num text-[19px] font-bold text-cam-ink tracking-wide mt-0.5">
            {{ props.tujuan.bank.rekening }}
          </p>
          <p class="text-[12.5px] text-stone-600">a.n. {{ props.tujuan.bank.atasNama }}</p>

          <button type="button" class="eq-btn-lain mt-2.5"
                  @click="salin(props.tujuan.bank.rekening)">Salin nomor rekening</button>
        </div>

        <div v-else class="px-5 py-4" style="background:#FEF3C7">
          <p class="text-[12.5px] font-bold text-amber-800">Rekening belum diatur</p>
          <p class="text-[12px] text-stone-700 mt-1">
            Isi BANK_REKENING dan BANK_ATAS_NAMA pada berkas .env.
          </p>
        </div>
      </section>

      <!-- ══════════ unggah bukti ══════════ -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <header class="px-5 py-3.5 border-b border-stone-100">
          <h3 class="text-[13.5px] font-bold text-cam-ink">Kirim bukti pembayaran</h3>
          <p class="text-[11.5px] text-stone-500 mt-0.5">
            Tagihan dinyatakan lunas setelah buktinya diperiksa — bukan otomatis
          </p>
        </header>

        <form class="px-5 py-4 grid gap-2.5" @submit.prevent="kirim">
          <select v-model="f.metode" aria-label="Metode pembayaran" class="beli-isian">
            <option value="qris">QRIS</option>
            <option value="transfer">Transfer bank</option>
          </select>

          <input v-model="f.atas_nama" required placeholder="Nama pengirim / pemilik rekening"
                 class="beli-isian" />

          <input v-model="f.tanggal_bayar" type="date" required
                 aria-label="Tanggal pembayaran" class="beli-isian" />

          <label class="text-[11.5px] text-stone-500">
            Bukti pembayaran — gambar atau PDF, maksimal 5 MB
            <input type="file" required accept=".jpg,.jpeg,.png,.webp,.pdf"
                   class="beli-isian mt-1 block w-full"
                   @change="f.bukti = ($event.target as HTMLInputElement).files?.[0] ?? null" />
          </label>

          <textarea v-model="f.catatan" rows="2" placeholder="Catatan (opsional)"
                    class="beli-isian"></textarea>

          <p v-for="(pesan, k) in f.errors" :key="k" class="text-[11.5px] text-red-600">{{ pesan }}</p>

          <button type="submit" class="eq-btn-utama justify-center" :disabled="f.processing">
            Kirim bukti pembayaran
          </button>
        </form>
      </section>
    </template>

  </div>
</template>

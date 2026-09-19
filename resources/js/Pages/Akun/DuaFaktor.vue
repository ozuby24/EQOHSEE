<script setup lang="ts">
/**
 * Verifikasi dua langkah — sisi pemilik akunnya sendiri.
 *
 * Tiga keadaan, dan halaman ini hanya pernah menggambar satu:
 *
 *   mati        → penjelasan + tombol mulai
 *   disiapkan   → QR, rahasia tertulis, dan kolom kode pembuktian
 *   menyala     → keterangan, kode pemulihan, tombol matikan
 *
 * Keadaan kedua ada supaya lapisan ini TIDAK menyala hanya karena QR
 * tersimpan. Orang yang memindai lalu menutup halaman karena ada
 * panggilan radio tidak boleh menemukan dirinya terkunci pada percobaan
 * masuk berikutnya, oleh fitur yang ia sendiri tidak yakin sudah ia
 * pasang.
 */
import { computed, nextTick, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import InputSandi from '../../Components/InputSandi.vue';

const props = defineProps<{
  judul: string;
  subjudul: string;
  menyala: boolean;
  aktifSejak?: string | null;
  penyiapan?: { rahasia: string | null; uri: string | null } | null;
  sisaPemulihan: number;
  pemulihan?: string[] | null;
  jumlahPemulihan: number;
  angkaKode: number;
}>();

const halaman = usePage<any>();
const galat = computed<Record<string, string>>(() => halaman.props.errors ?? {});

const kanvas = ref<HTMLCanvasElement | null>(null);
const rahasiaTerlihat = ref(false);

const mulai   = useForm({});
const sahkan  = useForm({ kode: '' });
const matikan = useForm({ password: '' });
const ulang   = useForm({ password: '' });

/* QR digambar di peramban, dari otpauth:// yang disusun server.
   Digambar di server sebagai gambar, ia akan tersimpan di cache proksi
   dan di riwayat peramban sebagai berkas yang memuat rahasianya. */
async function gambarQr() {
  await nextTick();

  if (!kanvas.value || !props.penyiapan?.uri) return;

  await QRCode.toCanvas(kanvas.value, props.penyiapan.uri, {
    width: 190, margin: 1, color: { dark: '#1C1917', light: '#FFFFFF' },
  });
}

watch(() => props.penyiapan?.uri, gambarQr, { immediate: true });

function salin(teks: string) {
  navigator.clipboard?.writeText(teks);
}

function unduhPemulihan() {
  if (!props.pemulihan) return;

  const isi = 'Kode pemulihan EQOHSEE\n'
    + 'Simpan di tempat aman. Setiap kode hanya berlaku sekali.\n\n'
    + props.pemulihan.join('\n') + '\n';

  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([isi], { type: 'text/plain' }));
  a.download = 'kode-pemulihan-eqohsee.txt';
  a.click();
  URL.revokeObjectURL(a.href);
}
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-3xl mx-auto space-y-5">

    <section class="brand-gradient rounded-2xl p-6 text-white shadow-card relative overflow-hidden">
      <div class="absolute -right-20 -top-20 w-56 h-56 rounded-full bg-cam-lime/20 blur-3xl"></div>
      <div class="relative">
        <span class="text-[10px] font-bold uppercase tracking-[0.22em] text-cam-lime-light">Akun</span>
        <p class="text-[12.5px] text-white/70 mt-2 max-w-xl leading-relaxed">{{ subjudul }}</p>
        <p class="text-[11.5px] mt-3" :class="menyala ? 'text-cam-lime-light' : 'text-white/60'">
          <span v-if="menyala">● Menyala<span v-if="aktifSejak"> sejak {{ aktifSejak }}</span></span>
          <span v-else>○ Belum menyala</span>
        </p>
      </div>
    </section>

    <div v-if="Object.keys(galat).length"
         class="rounded-2xl border border-red-100 bg-red-50 px-5 py-4 text-[12.5px] text-red-700 leading-relaxed">
      <p v-for="(pesan, k) in galat" :key="k">{{ pesan }}</p>
    </div>

    <!-- Kode pemulihan: muncul sekali sesudah dinyalakan atau diterbitkan ulang -->
    <section v-if="pemulihan?.length"
             class="rounded-2xl border-2 border-amber-200 bg-amber-50 p-5">
      <h2 class="font-semibold text-[15px] text-amber-800">Simpan kode pemulihan ini sekarang</h2>
      <p class="text-[12.5px] text-amber-800 mt-1 leading-relaxed">
        Kode ini jalan masuk Anda ketika ponselnya hilang, rusak, atau diganti.
        Setiap kode hanya berlaku sekali. Simpan di tempat yang tidak ikut hilang
        bersama ponselnya — dompet, brankas, atau pengelola kata sandi.
      </p>

      <ul class="grid grid-cols-2 gap-2 mt-4 font-mono text-[13px] text-amber-800">
        <li v-for="k in pemulihan" :key="k" class="rounded-lg bg-white/70 px-3 py-2 text-center tracking-wide">{{ k }}</li>
      </ul>

      <div class="flex gap-2 mt-4 flex-wrap">
        <button type="button" class="eq-btn-utama" @click="unduhPemulihan">Unduh sebagai berkas</button>
        <button type="button" class="eq-btn-lain" @click="salin(pemulihan.join('\n'))">Salin semua</button>
      </div>
    </section>

    <!-- MATI -->
    <section v-if="!menyala && !penyiapan?.rahasia"
             class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h2 class="font-semibold text-[15px] text-cam-ink">Kenapa ini perlu</h2>
      <p class="text-[12.5px] text-stone-600 mt-1.5 leading-relaxed">
        Kata sandi membuktikan Anda <em>tahu</em> sesuatu. Kode dari ponsel membuktikan
        Anda <em>memegang</em> sesuatu. Orang yang mendapatkan kata sandi Anda — dari
        catatan di meja, dari situs lain yang bocor, atau dari halaman tiruan — tetap
        tidak dapat masuk tanpa ponsel yang ada di tangan Anda.
      </p>
      <p class="text-[12.5px] text-stone-600 mt-2.5 leading-relaxed">
        Anda perlu aplikasi autentikator di ponsel: Google Authenticator, Authy,
        Microsoft Authenticator, atau pengelola kata sandi yang Anda pakai.
      </p>

      <button type="button" class="eq-btn-utama mt-4" :disabled="mulai.processing"
              @click="mulai.post('/akun/dua-faktor/mulai', { preserveScroll: true })">
        {{ mulai.processing ? 'Menyiapkan…' : 'Mulai pasang' }}
      </button>
    </section>

    <!-- DISIAPKAN, BELUM DISAHKAN -->
    <section v-else-if="!menyala && penyiapan?.rahasia"
             class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h2 class="font-semibold text-[15px] text-cam-ink">Pindai, lalu buktikan</h2>
      <p class="text-[12.5px] text-stone-600 mt-1.5 leading-relaxed">
        Belum menyala. Lapisan ini baru berlaku sesudah Anda memasukkan satu kode
        yang benar di bawah — supaya tidak ada yang terkunci di luar oleh QR yang
        ternyata tidak pernah berhasil dipindai.
      </p>

      <div class="flex gap-5 mt-4 flex-wrap">
        <div class="rounded-xl border border-stone-200 p-2.5 bg-white">
          <canvas ref="kanvas" width="190" height="190" aria-label="Kode QR untuk aplikasi autentikator"></canvas>
        </div>

        <div class="flex-1 min-w-[230px]">
          <p class="text-[12px] font-semibold text-cam-ink">Tidak bisa memindai?</p>
          <p class="text-[11.5px] text-stone-500 mt-1 leading-relaxed">
            Masukkan kunci ini ke aplikasi Anda secara manual.
          </p>

          <div class="mt-2 flex items-center gap-2 flex-wrap">
            <code class="rounded-lg bg-stone-100 px-3 py-2 font-mono text-[12px] break-all">
              {{ rahasiaTerlihat ? penyiapan.rahasia : '•••• •••• •••• ••••' }}
            </code>
            <button type="button" class="eq-btn-lain" @click="rahasiaTerlihat = !rahasiaTerlihat">
              {{ rahasiaTerlihat ? 'Sembunyikan' : 'Tampilkan' }}
            </button>
            <button v-if="rahasiaTerlihat" type="button" class="eq-btn-lain"
                    @click="salin(penyiapan.rahasia ?? '')">Salin</button>
          </div>

          <form class="mt-4" @submit.prevent="sahkan.post('/akun/dua-faktor/sahkan', { preserveScroll: true, onSuccess: () => sahkan.reset() })">
            <label for="kode" class="block text-[12px] font-semibold text-cam-ink mb-1.5">
              Kode {{ angkaKode }} angka dari aplikasi
            </label>
            <input id="kode" v-model="sahkan.kode" required autocomplete="one-time-code"
                   placeholder="123456"
                   class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 font-mono tracking-[.22em] text-center text-[15px]">
            <p v-if="sahkan.errors.kode" class="text-[11.5px] text-red-600 mt-1">{{ sahkan.errors.kode }}</p>

            <button type="submit" class="eq-btn-utama mt-3 w-full" :disabled="sahkan.processing">
              {{ sahkan.processing ? 'Memeriksa…' : 'Nyalakan' }}
            </button>
          </form>
        </div>
      </div>
    </section>

    <!-- MENYALA -->
    <section v-else class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h2 class="font-semibold text-[15px] text-cam-ink">Kode pemulihan</h2>
      <p class="text-[12.5px] text-stone-600 mt-1.5 leading-relaxed">
        Tersisa <strong>{{ sisaPemulihan }}</strong> dari {{ jumlahPemulihan }} kode.
        Menerbitkan ulang akan membatalkan seluruh kode lama — lakukan bila Anda
        menduga daftarnya pernah terlihat orang lain, atau bila sisanya tinggal sedikit.
      </p>

      <form class="mt-3 flex gap-2 flex-wrap items-start"
            @submit.prevent="ulang.post('/akun/dua-faktor/pemulihan', { preserveScroll: true, onSuccess: () => ulang.reset() })">
        <div class="flex-1 min-w-[200px]">
          <InputSandi v-model="ulang.password" required autocomplete="current-password"
                      label="Anda" placeholder="Kata sandi Anda"
                      kelas="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]" />
          <p v-if="ulang.errors.password" class="text-[11.5px] text-red-600 mt-1">{{ ulang.errors.password }}</p>
        </div>
        <button type="submit" class="eq-btn-lain" :disabled="ulang.processing">Terbitkan ulang</button>
      </form>

      <hr class="my-5 border-stone-100">

      <h2 class="font-semibold text-[15px] text-cam-ink">Matikan verifikasi dua langkah</h2>
      <p class="text-[12.5px] text-stone-600 mt-1.5 leading-relaxed">
        Sesudah dimatikan, kata sandi kembali menjadi satu-satunya penghalang akun ini.
        Kata sandi diminta lagi di sini karena mematikan lapisan kedua adalah tindakan
        pertama orang yang baru saja mengambil alih sebuah akun.
      </p>

      <form class="mt-3 flex gap-2 flex-wrap items-start"
            @submit.prevent="matikan.delete('/akun/dua-faktor', { preserveScroll: true, onSuccess: () => matikan.reset() })">
        <div class="flex-1 min-w-[200px]">
          <InputSandi v-model="matikan.password" required autocomplete="current-password"
                      label="Anda" placeholder="Kata sandi Anda"
                      kelas="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]" />
          <p v-if="matikan.errors.password" class="text-[11.5px] text-red-600 mt-1">{{ matikan.errors.password }}</p>
        </div>
        <button type="submit" class="rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-[12.5px] font-semibold text-red-700 hover:bg-red-100 transition"
                :disabled="matikan.processing">Matikan</button>
      </form>
    </section>

  </div>
</template>

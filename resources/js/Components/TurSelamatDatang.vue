<script setup lang="ts">
/**
 * Pengenalan situs untuk akun yang baru mendaftar.
 *
 * ── Kenapa berlangkah, bukan satu halaman panjang ──
 *
 * Pengenalan yang menumpahkan delapan aspek, dua puluh enam modul, dan
 * tiga langkah pertama sekaligus dibaca persis sebanyak nol kali. Yang
 * dibaca adalah yang muat di satu layar, dan yang berikutnya menunggu
 * sampai orangnya menekan lanjut. Jumlah langkahnya terlihat sejak awal
 * supaya jelas ini berapa lama — pengenalan yang tidak ketahuan
 * ujungnya ditutup di langkah kedua.
 *
 * ── Menutup juga dihitung selesai ──
 *
 * Yang menekan "Lewati" sudah menyatakan pendapatnya. Menampilkannya
 * lagi pada pembukaan halaman berikutnya mengubah sambutan menjadi
 * gangguan, dan mengajari orangnya menutup apa pun yang muncul tanpa
 * membaca — termasuk peringatan yang suatu saat benar-benar penting.
 *
 * Tidak ada yang hilang karenanya: menu akun memuat "Pengenalan fitur"
 * yang membukanya kembali kapan saja.
 *
 * ── Dua jalur isi, dan bedanya disengaja ──
 *
 * Sambutan OTOMATIS bagi akun baru memakai `bawaan` — langkahnya sudah
 * ikut bersama halaman, sehingga tidak ada permintaan jaringan dan
 * karena itu tidak ada yang dapat gagal.
 *
 * Buka-ulang MANUAL dari menu akun mengambilnya lewat /tur. Di sana
 * orangnya memang baru saja menekan sesuatu dan sedang menunggu, jadi
 * kegagalan pantas diberitahukan kepadanya.
 *
 * Semula keduanya lewat fetch, dan itu salah: begitu permintaannya
 * gagal di produksi, kotak "Pengenalan gagal dimuat" muncul lagi pada
 * SETIAP penyegaran halaman — sambutan berubah menjadi penghalang yang
 * tidak bisa disingkirkan pemakainya. Lihat App\Support\Tur.
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import IkonPilar from './IkonPilar.vue';

const props = defineProps<{
  terbuka: boolean;

  /**
   * Sampul modul yang sedang dibuka, bila halamannya membawanya.
   *
   * Dipakai mengisi rongga di bawah daftar langkah pada rel. Boleh null
   * — subhalaman tidak membawa sampul, dan rel tanpa foto tetap utuh.
   */
  sampul?: { gambar: string; webp?: string | null; keterangan?: string | null } | null;

  /**
   * Langkah yang sudah dibawa halaman.
   *
   * Bila ada, tidak ada yang perlu diambil dari jaringan dan karena itu
   * tidak ada yang dapat gagal — inilah jalur sambutan otomatis bagi
   * akun baru. Bila null, isinya diambil lewat /tur; itu jalur "buka
   * ulang dari menu akun", tempat orangnya memang sedang menunggu
   * sesuatu dan pantas diberi tahu bila gagal.
   */
  bawaan?: Langkah[] | null;
}>();

const emit = defineEmits<{ tutup: [] }>();

type Langkah = {
  kunci: string;
  judul: string;
  teks: string;
  poin?:  [string, string][];
  butir?: [string, string, string][];
  pilar?: { kunci: string; nama: string; ket: string; ringkas: string; warna: string; ikon: string }[];
  modul?: { kunci: string; label: string; semboyan: string; ikon: string; url: string | null }[];
};

const langkah = ref<Langkah[]>([]);
const ke      = ref(0);
const memuat  = ref(false);
const gagal   = ref(false);

/**
 * SEBAB kegagalannya, bukan sekadar bahwa ia gagal.
 *
 * Kotaknya dulu selalu berbunyi "Sambungannya terputus" — kalimat yang
 * menyebut satu sebab tertentu untuk kegagalan apa pun. Pada tangkapan
 * layar dari produksi ia menutup satu-satunya keterangan yang dapat
 * dipakai menelusurinya: 404 (rutenya tidak sampai ke peladen), 419
 * (sesinya kedaluwarsa), 429 (tertahan pembatas laju), dan 500
 * seluruhnya terbaca sebagai gangguan jaringan — dan yang membacanya
 * menekan "Coba lagi" untuk sesuatu yang akan gagal dengan cara yang
 * sama persis.
 */
const sebab = ref<string>('');
const panel   = ref<HTMLElement | null>(null);

const kini    = computed(() => langkah.value[ke.value] ?? null);
const terakhir = computed(() => ke.value >= langkah.value.length - 1);

/* Nama pendek tiap langkah untuk rel penunjuk jalan di sisi kiri.
 *
 * Dipetakan dari KUNCI, bukan dari judulnya. Judul langkah pertama
 * berbunyi "Selamat datang, Bambang" — menaruhnya di rel berarti nama
 * orangnya tercetak dua kali di satu layar, dan rel yang seharusnya
 * menjawab "saya di mana" berubah menjadi sapaan kedua.
 *
 * Kunci yang tidak dikenal jatuh ke nomor urut. Langkah baru yang
 * ditambahkan di App\Support\Tur karena itu tetap tergambar — tanpa
 * nama, tetapi tidak menghilang dan tidak mengosongkan relnya. */
const NAMA_LANGKAH: Record<string, string> = {
  sambutan: 'Selamat datang',
  pilar:    'Delapan aspek',
  modul:    'Modul Anda',
  mulai:    'Langkah pertama',
};

const relLangkah = computed(() => langkah.value.map((l, i) => ({
  kunci: l.kunci,
  nama:  NAMA_LANGKAH[l.kunci] ?? `Langkah ${i + 1}`,
  usai:  i < ke.value,
  kini:  i === ke.value,
})));

/* Melompat ke langkah yang sudah dilewati. Rel yang menampilkan
   keempat langkah tetapi tidak dapat diklik hanya menggoda; yang BELUM
   dibaca sengaja tidak dapat dilompati supaya urutannya tetap berarti. */
function keLangkah(i: number): void {
  if (i < ke.value) ke.value = i;
}

/**
 * Status yang punya arti tersendiri bagi yang membacanya.
 *
 * Yang tidak terdaftar disebut apa adanya beserta angkanya — angka yang
 * tidak dikenali tetap jauh lebih berguna daripada kalimat yang
 * menyebut sebab yang salah.
 */
const PESAN_STATUS: Record<number, string> = {
  401: 'Sesi Anda sudah habis. Muat ulang halaman, lalu coba lagi.',
  419: 'Sesi Anda sudah habis. Muat ulang halaman, lalu coba lagi.',
  403: 'Pengenalan ini tidak terbuka bagi akun Anda.',
  404: 'Alamat pengenalannya tidak ditemukan di peladen.',
  429: 'Terlalu banyak permintaan. Tunggu sebentar, lalu coba lagi.',
  500: 'Peladen gagal menyusun pengenalannya.',
  502: 'Peladen tidak menjawab.',
  503: 'Layanan sedang tidak tersedia.',
  504: 'Peladen tidak menjawab tepat waktu.',
};

async function ambilIsi(): Promise<void> {
  /* Yang sudah dibawa halaman dipakai apa adanya. Inilah sebabnya
     sambutan bagi akun baru tidak pernah lagi bisa gagal dimuat. */
  if (props.bawaan && props.bawaan.length) {
    langkah.value = props.bawaan;
    ke.value = 0;
    gagal.value = false;
    return;
  }

  memuat.value = true;
  gagal.value  = false;

  sebab.value = '';

  try {
    const r = await fetch('/tur', {
      headers: { Accept: 'application/json' },

      /* Disebut tegas, tidak diandalkan pada bawaan peramban: tanpa
         cookie sesi, /tur memulangkan pengalihan ke halaman masuk dan
         jawabannya bukan JSON sama sekali. */
      credentials: 'same-origin',
    });

    if (!r.ok) {
      sebab.value = PESAN_STATUS[r.status] ?? `Peladen menjawab ${r.status}.`;
      throw new Error(String(r.status));
    }

    /* Jawaban 200 yang BUKAN JSON tetap kegagalan, dan sebabnya berbeda
       lagi: yang sampai biasanya halaman masuk karena sesinya habis,
       atau halaman galat dari pelantara di depan peladen. */
    const jenis = r.headers.get('content-type') ?? '';

    if (!jenis.includes('json')) {
      sebab.value = 'Jawabannya bukan data pengenalan — sesi Anda mungkin sudah habis.';
      throw new Error('bukan-json');
    }

    langkah.value = (await r.json()).langkah ?? [];
    ke.value = 0;
  } catch {
    /* Hanya terjadi pada buka-ulang manual. Pengenalan yang gagal dimuat
       tidak boleh menyandera halamannya: yang ditawarkan cuma dua —
       coba lagi, atau tutup dan bekerja. */
    if (sebab.value === '') sebab.value = 'Sambungannya terputus.';

    gagal.value = true;
  } finally {
    memuat.value = false;
  }
}

/**
 * Menandai selesai di server, lalu menutup.
 *
 * Penutupannya TIDAK menunggu jawaban server. Yang menekan Lewati ingin
 * segera bekerja; membuatnya menunggu perjalanan jaringan — yang di site
 * tambang bisa beberapa detik — hanya untuk mencatat bahwa ia tidak
 * ingin menunggu adalah kebalikan dari maksudnya.
 *
 * Gagalnya pun tidak diberitakan: akibat terburuknya pengenalan ini
 * muncul sekali lagi nanti, dan sebuah pesan galat soal itu jauh lebih
 * mengganggu daripada akibatnya sendiri.
 */
/** Token CSRF dari kuki yang dipasang Laravel. */
function xsrf(): string {
  const k = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='));

  return k ? decodeURIComponent(k.slice('XSRF-TOKEN='.length)) : '';
}

function selesai(): void {
  /* TIDAK lewat router Inertia, dan itu diperbaiki setelah terlihat
   * di peramban.
   *
   * `/tur/selesai` memulangkan 204 No Content — jawaban yang tepat,
   * sebab menutup sambutan memang tidak mengubah apa pun di halaman
   * yang sedang dibuka. Tetapi router Inertia MEMERIKSA tiap jawaban:
   * yang tidak bertajuk `X-Inertia` dianggapnya halaman galat, dan ia
   * membuka dialog galat selayar penuh di atas situsnya.
   *
   * Akibatnya persis kebalikan dari maksud sambutan: orang yang baru
   * mendaftar menekan "Lewati pengenalan" dan yang muncul adalah kotak
   * galat hitam. Penandanya TERSIMPAN — jadi tidak ada yang rusak di
   * basis data, dan dialognya hilang begitu halaman disegarkan. Itulah
   * sebabnya ia lolos dari tangkapan layar saya sebelumnya: yang saya
   * potret adalah keadaan SESUDAH penyegaran.
   *
   * `keepalive` bukan hiasan. Menekan tautan pada langkah terakhir
   * memanggil selesai() lalu berpindah halaman pada napas yang sama;
   * tanpa keepalive, peramban membatalkan permintaan yang belum selesai
   * dan penandanya tidak pernah tersimpan — sambutan muncul lagi justru
   * bagi orang yang sudah membacanya sampai habis. */
  fetch('/tur/selesai', {
    method: 'POST',
    credentials: 'same-origin',
    keepalive: true,
    headers: {
      'X-XSRF-TOKEN': xsrf(),
      'X-Requested-With': 'XMLHttpRequest',
      Accept: 'application/json',
    },
  }).catch(() => {
    /* Sengaja dibiarkan. Akibat terburuknya sambutan muncul sekali lagi
       nanti, dan pesan galat soal itu lebih mengganggu daripada
       akibatnya sendiri. */
  });

  emit('tutup');
}

function maju(): void {
  if (terakhir.value) selesai();
  else ke.value += 1;
}

function mundur(): void {
  if (ke.value > 0) ke.value -= 1;
}

/* Esc didengarkan di tingkat dokumen, bukan pada kotaknya.
   Pendengar yang menempel pada elemen hanya menyala bila fokus sedang
   berada di dalamnya — dan begitu orangnya mengklik latar gelap di
   luar kotak, Esc berhenti bekerja tanpa alasan yang terlihat. */
function tekanTombol(e: KeyboardEvent): void {
  if (e.key === 'Escape') { e.preventDefault(); selesai(); return; }
  if (e.key === 'ArrowRight') maju();
  if (e.key === 'ArrowLeft')  mundur();
}

/* `immediate` WAJIB, dan itu bukan kehati-hatian berlebih.
 *
 * Akun yang baru mendaftar mendarat di halaman pertamanya dengan
 * pengenalan SUDAH terbuka — tata letak menyalakannya saat disusun,
 * bukan sesudahnya. Tanpa `immediate`, pengawas ini menunggu sebuah
 * PERUBAHAN yang tidak pernah datang: isinya tidak pernah diambil, dan
 * yang dilihat pengguna barunya adalah kotak kosong tanpa judul, tanpa
 * tombol, dan tanpa satu pun galat di konsol.
 *
 * Persis itu yang terjadi pada uji peramban pertama komponen ini. */
watch(() => props.terbuka, async (buka) => {
  if (buka) {
    document.addEventListener('keydown', tekanTombol);
    await ambilIsi();
    await nextTick();
    panel.value?.focus();
  } else {
    document.removeEventListener('keydown', tekanTombol);
  }
}, { immediate: true });

/* Pendengar dokumen ikut dilepas saat komponennya dibuang. Tanpa ini,
   pindah halaman dengan pengenalan masih terbuka meninggalkan pendengar
   yang menahan komponen lama — dan Esc pada halaman berikutnya memanggil
   selesai() milik kotak yang sudah tidak ada. */
onBeforeUnmount(() => document.removeEventListener('keydown', tekanTombol));
</script>

<template>
  <div v-if="terbuka" class="eq-tur-tirai" role="dialog" aria-modal="true"
       aria-labelledby="eq-tur-judul">
    <div ref="panel" class="eq-tur" tabindex="-1">

      <!-- ══ rel penunjuk jalan ══════════════════════════════════
           Bukan hiasan. Pengenalan yang tidak ketahuan ujungnya
           ditutup di langkah kedua; rel ini menjawab "berapa lama
           lagi" dan "tadi saya sudah lihat apa" sekaligus, tanpa
           satu pun kata tambahan di badan isinya. -->
      <aside class="eq-tur-rel" aria-hidden="true">
        <div class="eq-tur-merek">
          <img src="/brand/eqohsee-mark-128.png" alt="" width="40" height="40">
          <span>
            <strong>E<em>Q</em>OHSEE</strong>
            <small>Safe Today &middot; Sustainable Tomorrow</small>
          </span>
        </div>

        <ol class="eq-tur-tangga">
          <li v-for="(l, i) in relLangkah" :key="l.kunci"
              :class="{ 'is-usai': l.usai, 'is-kini': l.kini }">
            <button type="button" class="eq-tur-tangga-btn"
                    :disabled="!l.usai" :tabindex="l.usai ? 0 : -1"
                    @click="keLangkah(i)">
              <span class="eq-tur-tangga-tanda">
                <svg v-if="l.usai" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                  <path d="m5 13 4 4L19 7"/>
                </svg>
                <template v-else>{{ i + 1 }}</template>
              </span>
              <span class="eq-tur-tangga-nama">{{ l.nama }}</span>
            </button>
          </li>
        </ol>

        <!-- Foto pengisi rongga.
             `aria-hidden` pada seluruh rel sudah menutupinya dari
             pembaca layar; alt dikosongkan supaya tidak dibacakan dua
             kali oleh peramban yang mengabaikan aria-hidden. -->
        <figure v-if="props.sampul?.gambar" class="eq-tur-rel-foto">
          <picture>
            <source v-if="props.sampul.webp" :srcset="props.sampul.webp" type="image/webp">
            <img :src="props.sampul.gambar" alt="" loading="lazy" decoding="async">
          </picture>
          <span class="eq-tur-rel-tirai" />
          <figcaption v-if="props.sampul.keterangan">{{ props.sampul.keterangan }}</figcaption>
        </figure>

        <p class="eq-tur-rel-kaki">
          Pengenalan ini selalu dapat dibuka lagi dari menu akun.
        </p>
      </aside>

      <!-- ══ badan ═══════════════════════════════════════════════ -->
      <div class="eq-tur-badan">

        <div class="eq-tur-kepala">
          <div class="eq-tur-larik" aria-hidden="true">
            <span v-for="(l, i) in langkah" :key="l.kunci"
                  class="eq-tur-titik" :class="{ 'is-kini': i === ke, 'is-lewat': i < ke }" />
          </div>

          <p v-if="langkah.length" class="eq-tur-hitung">
            Langkah {{ ke + 1 }} dari {{ langkah.length }}
          </p>

          <button type="button" class="eq-tur-tutup" aria-label="Tutup pengenalan"
                  @click="selesai">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                 stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
          </button>
        </div>

        <div class="eq-tur-isi">
          <p v-if="memuat" class="eq-tur-tunggu">
            <span class="eq-tur-putar" aria-hidden="true" />
            Menyiapkan pengenalan…
          </p>

          <div v-else-if="gagal" class="eq-tur-gagal">
            <h2 id="eq-tur-judul" class="eq-tur-judul">Pengenalan gagal dimuat</h2>
            <p class="eq-tur-teks">
              {{ sebab }} Anda tetap dapat memakai situs seperti biasa —
              pengenalan ini ada di menu akun bila ingin dibuka lagi nanti.
            </p>
            <button type="button" class="eq-btn-lain" @click="ambilIsi">Coba lagi</button>
          </div>

          <Transition name="eq-tur-geser" mode="out-in">
            <div v-if="!memuat && !gagal && kini" :key="kini.kunci">
              <h2 id="eq-tur-judul" class="eq-tur-judul">{{ kini.judul }}</h2>
              <p class="eq-tur-teks">{{ kini.teks }}</p>

              <!-- sambutan -->
              <ul v-if="kini.poin" class="eq-tur-poin">
                <li v-for="[kepala, isi] in kini.poin" :key="kepala">
                  <strong>{{ kepala }}</strong>
                  <span>{{ isi }}</span>
                </li>
              </ul>

              <!-- delapan pilar -->
              <ul v-if="kini.pilar" class="eq-tur-pilar">
                <li v-for="p in kini.pilar" :key="p.kunci" :title="p.ringkas">
                  <span class="eq-tur-lencana" :style="{ background: p.warna }">
                    <IkonPilar :nama="p.ikon" :ukuran="17" />
                  </span>
                  <span class="min-w-0">
                    <strong>{{ p.nama }}</strong>
                    <small>{{ p.ket }}</small>
                  </span>
                </li>
              </ul>

              <!-- modul yang terbuka -->
              <ul v-if="kini.modul" class="eq-tur-modul">
                <li v-for="m in kini.modul" :key="m.kunci">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                       stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path :d="m.ikon" />
                  </svg>
                  <span class="min-w-0">
                    <strong>{{ m.label }}</strong>
                    <small v-if="m.semboyan">{{ m.semboyan }}</small>
                  </span>
                </li>
              </ul>

              <!-- langkah pertama -->
              <ol v-if="kini.butir" class="eq-tur-mulai">
                <li v-for="([judul, isi, url], i) in kini.butir" :key="judul">
                  <span class="eq-tur-angka" aria-hidden="true">{{ i + 1 }}</span>
                  <span class="min-w-0">
                    <a :href="url" class="eq-tur-tautan" @click="selesai">
                      {{ judul }}
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                           stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M5 12h13M13 6l6 6-6 6"/>
                      </svg>
                    </a>
                    <small>{{ isi }}</small>
                  </span>
                </li>
              </ol>
            </div>
          </Transition>
        </div>

        <div v-if="!memuat && !gagal && kini" class="eq-tur-kaki">
          <button type="button" class="eq-tur-lewati" @click="selesai">
            {{ terakhir ? 'Tutup' : 'Lewati pengenalan' }}
          </button>

          <span class="eq-tur-sela" />

          <button v-if="ke > 0" type="button" class="eq-btn-lain" @click="mundur">Kembali</button>

          <button type="button" class="eq-tur-maju" @click="maju">
            {{ terakhir ? 'Mulai bekerja' : 'Lanjut' }}
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M5 12h13M13 6l6 6-6 6"/>
            </svg>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ══════════════════════════════════════════════════════════════
   Pengenalan situs — rel gelap di kiri, isi terang di kanan.

   Rancangan satu kolom yang lama menggambar kotak putih polos:
   kabar yang disampaikannya benar, tetapi kesan pertamanya adalah
   kotak pemberitahuan peramban, bukan halaman sambutan sebuah
   sistem yang mengurus keselamatan tambang. Rel gelapnya memakai
   gradien yang sama persis dengan bilah samping situs (.brand-gradient),
   sehingga sambutan ini terbaca sebagai bagian dari aplikasinya —
   bukan sebagai sesuatu yang ditempelkan di atasnya.
   ══════════════════════════════════════════════════════════════ */

.eq-tur-tirai {
  position: fixed;
  inset: 0;
  z-index: 70;
  display: grid;
  place-items: center;
  padding: 1rem;
  background:
    radial-gradient(70rem 40rem at 50% -10%, rgb(245 124 0 / .16), transparent 60%),
    rgb(9 13 20 / .72);
  backdrop-filter: blur(4px);
  animation: eq-tur-tirai-masuk .22s ease-out;
}

@keyframes eq-tur-tirai-masuk { from { opacity: 0; } }

.eq-tur {
  width: 100%;
  max-width: 56rem;
  max-height: min(90vh, 47rem);
  display: grid;
  grid-template-columns: 15.5rem 1fr;
  background: #FFFFFF;
  border-radius: 1.35rem;
  box-shadow:
    0 0 0 1px rgb(255 255 255 / .06),
    0 32px 80px -12px rgb(0 0 0 / .55);
  overflow: hidden;
  animation: eq-tur-masuk .26s cubic-bezier(.2, .8, .3, 1);
}

@keyframes eq-tur-masuk {
  from { opacity: 0; transform: translateY(1rem) scale(.975); }
}

.eq-tur:focus { outline: none; }

/* ══ rel penunjuk jalan ══ */
.eq-tur-rel {
  display: flex;
  flex-direction: column;
  padding: 1.5rem 1.25rem 1.25rem;
  color: #E7E5E4;

  /* Gradien yang sama dengan bilah samping situs. */
  background: linear-gradient(160deg, #0B1117 0%, #141C25 55%, #1B2530 100%);
  position: relative;
}

/* Cahaya jingga tipis di sudut atas — menandai ini "milik EQOHSEE"
   tanpa menambah satu elemen pun yang harus dibaca. */
.eq-tur-rel::before {
  content: '';
  position: absolute;
  inset: 0 0 auto;
  height: 11rem;
  background: radial-gradient(18rem 9rem at 18% 0%, rgb(255 152 0 / .22), transparent 70%);
  pointer-events: none;
}

/* LOGO YANG SAMA DENGAN SELURUH SITUS — lambang berwarna beserta
 * wordmark dan semboyannya, persis seperti yang tergambar di kepala
 * bilah samping (lihat .eq-merek pada partials/eq-visual.blade.php).
 *
 * Sempat dipakai lockup tersendiri berupa satu berkas PNG. Ia memuat
 * huruf bergaya stensil dan semboyan "Sustaining Performance, Shaping
 * the Future" — dua-duanya BUKAN yang dipakai situs ini. Akibatnya
 * pengenalan yang seharusnya memperkenalkan EQOHSEE justru membuka
 * dengan merek yang tidak akan ditemukan lagi di halaman mana pun
 * sesudahnya: bentuk hurufnya lain, semboyannya lain.
 *
 * Satu identitas, bukan tiga. Yang dipakai di sini karena itu berkas
 * lambang yang sama dan teks yang sama dengan bilah sampingnya.
 */
.eq-tur-merek {
  position: relative;
  display: flex;
  align-items: center;
  gap: .7rem;
}

.eq-tur-merek img {
  display: block;
  flex: none;
  width: 2.5rem;
  height: 2.5rem;
}

.eq-tur-merek span {
  display: flex;
  flex-direction: column;
  line-height: 1.12;
  min-width: 0;
}

.eq-tur-merek strong {
  font-size: 1.25rem;
  font-weight: 900;
  letter-spacing: -.015em;
  color: #E8ECF0;
}

/* Q jingga — satu-satunya huruf berwarna, sama seperti di bilah
   samping. Dimatikan gaya miringnya: <em> dipakai sebagai penanda
   warna, bukan sebagai penekanan yang dibaca. */
.eq-tur-merek strong em {
  font-style: normal;
  color: #F57C00;
}

/* Satu baris, bukan dua. Rel ini 15,5rem — lebih sempit daripada bilah
   samping — dan semboyan yang membungkus menjadi "Safe Today ·
   Sustainable / Tomorrow" memisahkan satu kalimat pendek di tempat yang
   tidak berarti apa-apa. */
.eq-tur-merek small {
  font-size: .56rem;
  color: rgb(255 255 255 / .42);
  margin-top: .19rem;
  letter-spacing: 0;
  white-space: nowrap;
}

.eq-tur-tangga {
  list-style: none;
  margin: 1.75rem 0 0;
  padding: 0;
  display: grid;
  gap: .15rem;
  position: relative;
}

.eq-tur-tangga-btn {
  width: 100%;
  display: flex;
  align-items: center;
  gap: .65rem;
  padding: .5rem .55rem;
  border: 0;
  border-radius: .6rem;
  background: transparent;
  text-align: left;
  cursor: default;
  transition: background-color .16s;
}

.is-usai .eq-tur-tangga-btn { cursor: pointer; }
.is-usai .eq-tur-tangga-btn:hover { background: rgb(255 255 255 / .06); }

.eq-tur-tangga-btn:focus-visible {
  outline: 2px solid #FF9800;
  outline-offset: -2px;
}

.eq-tur-tangga-tanda {
  flex: none;
  display: grid;
  place-items: center;
  width: 1.4rem;
  height: 1.4rem;
  border-radius: 99px;
  border: 1.5px solid rgb(231 229 228 / .24);
  font-size: 10.5px;
  font-weight: 700;
  color: rgb(231 229 228 / .5);
  transition: all .18s;
}

.eq-tur-tangga-tanda svg { width: .72rem; height: .72rem; }

.eq-tur-tangga-nama {
  font-size: 12px;
  font-weight: 600;
  color: rgb(231 229 228 / .5);
  transition: color .18s;
}

.is-usai .eq-tur-tangga-tanda {
  background: rgb(255 152 0 / .16);
  border-color: rgb(255 152 0 / .45);
  color: #FFB74D;
}

.is-usai .eq-tur-tangga-nama { color: rgb(231 229 228 / .8); }

.is-kini .eq-tur-tangga-tanda {
  background: linear-gradient(135deg, #DC6E00, #FF9800);
  border-color: transparent;
  color: #FFFFFF;
  box-shadow: 0 0 0 4px rgb(255 152 0 / .16);
}

.is-kini .eq-tur-tangga-nama { color: #FFFFFF; font-weight: 700; }

/* ══ pengisi rongga rel ══
 *
 * Rel setinggi panel, daftar langkahnya setinggi empat baris. Selisihnya
 * — terukur 240px pada 1440x900 — adalah bidang navy kosong di antara
 * langkah terakhir dan catatan kakinya, dan yang terbaca dari situ
 * bukan kelapangan melainkan ada sesuatu yang gagal dimuat.
 *
 * Diisi sampul modul yang sedang dibuka, bukan satu foto tetap: yang
 * membuka pengenalan dari Peledakan melihat sampul Peledakan. `flex`
 * 1 1 auto membuatnya MEMUAI mengisi berapa pun sisanya dan MENYUSUT
 * ketika tidak ada sisa, jadi ia tidak pernah mendorong catatan kaki
 * keluar dari rel.
 */
.eq-tur-rel-foto {
  flex: 1 1 auto;
  min-height: 0;
  margin: 1.5rem 0 0;
  position: relative;
  border-radius: .85rem;
  overflow: hidden;
  isolation: isolate;
  background: #0A1114;
  display: flex;
  align-items: flex-end;
}

.eq-tur-rel-foto picture,
.eq-tur-rel-foto img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  z-index: -2;
}

/* Tirai gelap dari bawah: keterangan kecil di atas foto senja tidak
   terbaca tanpa sesuatu yang menahannya. */
.eq-tur-rel-tirai {
  position: absolute;
  inset: 0;
  z-index: -1;
  background: linear-gradient(0deg, rgb(8 14 17 / .92) 16%, rgb(8 14 17 / .45) 62%,
                                    rgb(8 14 17 / .12) 100%);
}

.eq-tur-rel-foto figcaption {
  position: relative;
  padding: .6rem .7rem;
  font-size: .625rem;
  line-height: 1.35;
  color: rgb(255 255 255 / .82);
  text-shadow: 0 1px 8px rgb(0 0 0 / .55);
}

/* Rel yang pendek tidak menyisakan ruang untuk foto sama sekali:
   digambar setinggi 40px ia berhenti menjadi foto dan menjadi garis
   berwarna. Di bawah ambang itu ia tidak digambar. */
@media (max-height: 640px) {
  .eq-tur-rel-foto { display: none; }
}

.eq-tur-rel-kaki {
  margin: auto 0 0;
  padding-top: 1.25rem;
  font-size: 10.5px;
  line-height: 1.5;
  color: rgb(231 229 228 / .42);
  position: relative;
}

/* Tanpa foto, catatan kaki yang dipaku ke dasar meninggalkan rongga di
   TENGAH rel — bentuk yang sama yang baru saja dihilangkan, hanya lebih
   kecil. Dilepas pakunya, ia menempel di bawah daftar langkah dan sisa
   ruangnya jatuh di bawah keduanya, tempat ia terbaca sebagai jarak
   biasa.

   DITULIS SESUDAH aturan dasarnya, bukan di dalam blok media di atas:
   kekhususan keduanya sama persis, dan `margin: auto 0 0` yang tertulis
   belakangan akan menimpanya tanpa jejak apa pun. Sempat begitu — blok
   medianya terpasang rapi dan tidak mengerjakan apa-apa. */
@media (max-height: 640px) {
  .eq-tur-rel-kaki { margin-top: 1.25rem; }
}

/* ══ badan ══ */
.eq-tur-badan {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.eq-tur-kepala {
  display: flex;
  align-items: center;
  gap: .8rem;
  padding: .9rem 1.2rem;
  border-bottom: 1px solid #F5F5F4;
}

/* Larik titik ini KEMBAR dengan tangga di rel, dan itu disengaja:
   pada layar sempit relnya tersembunyi, dan tanpa larik ini kemajuan
   langkah menghilang sama sekali di tempat ia paling dibutuhkan. */
.eq-tur-larik { display: none; gap: .3rem; }

.eq-tur-titik {
  width: 1.45rem;
  height: .28rem;
  border-radius: 99px;
  background: #E7E5E4;
  transition: background-color .2s;
}

.eq-tur-titik.is-lewat { background: #FDBA74; }
.eq-tur-titik.is-kini  { background: #F57C00; }

.eq-tur-hitung {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: #A8A29E;
  margin: 0;
}

.eq-tur-tutup {
  margin-left: auto;
  display: grid;
  place-items: center;
  width: 1.95rem;
  height: 1.95rem;
  border: 0;
  border-radius: .6rem;
  background: transparent;
  color: #A8A29E;
  cursor: pointer;
  transition: all .16s;
}

.eq-tur-tutup svg { width: 1rem; height: 1rem; }
.eq-tur-tutup:hover { background: #F5F5F4; color: #292524; }

/* ══ isi ══ */
.eq-tur-isi {
  padding: 1.6rem 1.7rem;
  overflow-y: auto;
  flex: 1;
}

.eq-tur-judul {
  font-size: 1.5rem;
  font-weight: 800;
  letter-spacing: -.015em;
  line-height: 1.2;
  color: #1C1917;
  margin: 0 0 .5rem;
}

.eq-tur-teks {
  font-size: 13.5px;
  line-height: 1.65;
  color: #57534E;
  margin: 0;
  max-width: 34rem;
}

.eq-tur-tunggu {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .55rem;
  font-size: 13px;
  color: #78716C;
  margin: 0;
  padding: 3rem 0;
}

.eq-tur-putar {
  width: .9rem;
  height: .9rem;
  border-radius: 99px;
  border: 2px solid rgb(245 124 0 / .25);
  border-top-color: #F57C00;
  animation: eq-tur-putar .62s linear infinite;
}

@keyframes eq-tur-putar { to { transform: rotate(360deg); } }

.eq-tur-gagal .eq-btn-lain { margin-top: 1rem; }

/* Pergantian langkah bergeser mendatar — arahnya menegaskan bahwa
   yang berganti adalah HALAMAN pengenalan, bukan isinya yang berubah
   sendiri di tempat. */
.eq-tur-geser-enter-active { transition: opacity .2s ease-out, transform .2s ease-out; }
.eq-tur-geser-leave-active { transition: opacity .12s ease-in,  transform .12s ease-in; }
.eq-tur-geser-enter-from { opacity: 0; transform: translateX(.9rem); }
.eq-tur-geser-leave-to   { opacity: 0; transform: translateX(-.6rem); }

/* ══ sambutan ══ */
.eq-tur-poin {
  list-style: none;
  margin: 1.4rem 0 0;
  padding: 0;
  display: grid;
  gap: .55rem;
}

.eq-tur-poin li {
  padding: .7rem .85rem .7rem 1rem;
  border-radius: .7rem;
  background: linear-gradient(90deg, rgb(255 152 0 / .07), transparent 70%);
  border-left: 3px solid #F57C00;
}

.eq-tur-poin strong {
  display: block;
  font-size: 12.5px;
  font-weight: 700;
  color: #1C1917;
}

.eq-tur-poin span {
  display: block;
  font-size: 12px;
  line-height: 1.55;
  color: #78716C;
  margin-top: .15rem;
}

/* ══ pilar dan modul ══ */
.eq-tur-pilar,
.eq-tur-modul {
  list-style: none;
  margin: 1.4rem 0 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(14.5rem, 1fr));
  gap: .5rem;
}

.eq-tur-pilar li,
.eq-tur-modul li {
  display: flex;
  align-items: center;
  gap: .65rem;
  padding: .6rem .7rem;
  border: 1px solid #EFEDEB;
  border-radius: .75rem;
  background: #FFFFFF;
  transition: border-color .16s, box-shadow .16s, transform .16s;
}

.eq-tur-pilar li:hover,
.eq-tur-modul li:hover {
  border-color: #FDBA74;
  box-shadow: 0 4px 14px -4px rgb(245 124 0 / .3);
  transform: translateY(-1px);
}

.eq-tur-pilar strong,
.eq-tur-modul strong {
  display: block;
  font-size: 12.5px;
  font-weight: 700;
  color: #1C1917;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.eq-tur-pilar small,
.eq-tur-modul small {
  display: block;
  font-size: 11px;
  color: #A8A29E;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.eq-tur-lencana {
  flex: none;
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: .6rem;
  color: #FFFFFF;
  box-shadow: 0 2px 8px -2px rgb(0 0 0 / .35);
}

.eq-tur-modul svg {
  flex: none;
  width: 1.2rem;
  height: 1.2rem;
  color: #C7C2BD;
}

.eq-tur-modul li:hover svg { color: #F57C00; }

/* ══ langkah pertama ══ */
.eq-tur-mulai {
  list-style: none;
  margin: 1.4rem 0 0;
  padding: 0;
  display: grid;
  gap: .5rem;
}

.eq-tur-mulai li {
  display: flex;
  align-items: flex-start;
  gap: .75rem;
  padding: .75rem .85rem;
  border: 1px solid #EFEDEB;
  border-radius: .75rem;
  transition: border-color .16s, box-shadow .16s;
}

.eq-tur-mulai li:hover {
  border-color: #FDBA74;
  box-shadow: 0 4px 14px -4px rgb(245 124 0 / .28);
}

.eq-tur-angka {
  flex: none;
  display: grid;
  place-items: center;
  width: 1.6rem;
  height: 1.6rem;
  border-radius: 99px;
  background: linear-gradient(135deg, #DC6E00, #FF9800);
  color: #FFFFFF;
  font-size: 11.5px;
  font-weight: 800;
  box-shadow: 0 2px 8px -2px rgb(245 124 0 / .6);
}

.eq-tur-tautan {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  font-size: 13px;
  font-weight: 700;
  color: #C2410C;
  text-decoration: none;
}

.eq-tur-tautan svg {
  width: .85rem;
  height: .85rem;
  transition: transform .16s;
}

.eq-tur-tautan:hover { text-decoration: underline; }
.eq-tur-mulai li:hover .eq-tur-tautan svg { transform: translateX(.15rem); }

.eq-tur-mulai small {
  display: block;
  font-size: 12px;
  line-height: 1.55;
  color: #78716C;
  margin-top: .15rem;
}

/* ══ kaki ══ */
.eq-tur-kaki {
  display: flex;
  align-items: center;
  gap: .5rem;
  flex-wrap: wrap;
  padding: .9rem 1.2rem;
  border-top: 1px solid #F5F5F4;
  background: #FCFBFA;
}

.eq-tur-sela { flex: 1 1 auto; }

.eq-tur-lewati {
  border: 0;
  background: transparent;
  padding: .5rem .2rem;
  font-size: 12px;
  font-weight: 600;
  color: #A8A29E;
  cursor: pointer;
}

.eq-tur-lewati:hover { color: #292524; text-decoration: underline; }

.eq-tur-maju {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  padding: .55rem 1.05rem;
  border: 0;
  border-radius: .65rem;
  background: linear-gradient(135deg, #DC6E00, #FF9800);
  color: #FFFFFF;
  font-size: 12.5px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 4px 14px -3px rgb(245 124 0 / .55);
  transition: box-shadow .16s, transform .16s, filter .16s;
}

.eq-tur-maju svg { width: .9rem; height: .9rem; transition: transform .16s; }

.eq-tur-maju:hover {
  filter: brightness(1.06);
  box-shadow: 0 6px 18px -3px rgb(245 124 0 / .65);
}

.eq-tur-maju:hover svg { transform: translateX(.15rem); }
.eq-tur-maju:active { transform: translateY(1px); }

.eq-tur-maju:focus-visible {
  outline: 2px solid #C2410C;
  outline-offset: 2px;
}

/* ══ mode gelap ══ */
:global([data-tema='gelap']) .eq-tur { background: #14202F; }

:global([data-tema='gelap']) .eq-tur-kepala,
:global([data-tema='gelap']) .eq-tur-kaki { border-color: #1E2E42; }

:global([data-tema='gelap']) .eq-tur-kaki { background: #101A26; }

:global([data-tema='gelap']) .eq-tur-judul,
:global([data-tema='gelap']) .eq-tur-poin strong,
:global([data-tema='gelap']) .eq-tur-pilar strong,
:global([data-tema='gelap']) .eq-tur-modul strong { color: #F5F5F4; }

:global([data-tema='gelap']) .eq-tur-teks,
:global([data-tema='gelap']) .eq-tur-poin span,
:global([data-tema='gelap']) .eq-tur-pilar small,
:global([data-tema='gelap']) .eq-tur-modul small,
:global([data-tema='gelap']) .eq-tur-mulai small,
:global([data-tema='gelap']) .eq-tur-hitung,
:global([data-tema='gelap']) .eq-tur-tunggu,
:global([data-tema='gelap']) .eq-tur-lewati { color: #A8A29E; }

:global([data-tema='gelap']) .eq-tur-pilar li,
:global([data-tema='gelap']) .eq-tur-modul li,
:global([data-tema='gelap']) .eq-tur-mulai li {
  background: #101A26;
  border-color: #1E2E42;
}

:global([data-tema='gelap']) .eq-tur-titik { background: #2A3D55; }
:global([data-tema='gelap']) .eq-tur-tautan { color: #FDBA74; }
:global([data-tema='gelap']) .eq-tur-tutup { color: #A8A29E; }

:global([data-tema='gelap']) .eq-tur-tutup:hover {
  background: rgb(255 255 255 / .08);
  color: #F5F5F4;
}

:global([data-tema='gelap']) .eq-tur-lewati:hover { color: #F5F5F4; }

/* ══ layar sempit ══

   Relnya disembunyikan, BUKAN ditumpuk di atas isinya. Ditumpuk, ia
   memakan sepertiga layar 360 piksel untuk mengulang kabar yang sudah
   tertulis di kepala — dan yang tergeser turun adalah tombol "Lanjut",
   satu-satunya yang benar-benar dipakai. Larik titik di kepala
   mengambil alih tugasnya. */
@media (max-width: 52rem) {
  .eq-tur { grid-template-columns: 1fr; max-width: 34rem; }
  .eq-tur-rel { display: none; }
  .eq-tur-larik { display: flex; }
}

@media (max-width: 30rem) {
  .eq-tur-tirai { padding: .5rem; }
  .eq-tur { max-height: 94vh; border-radius: 1rem; }
  .eq-tur-isi { padding: 1.15rem 1.2rem; }
  .eq-tur-judul { font-size: 1.22rem; }
  .eq-tur-pilar,
  .eq-tur-modul { grid-template-columns: 1fr; }

  .eq-tur-sela { display: none; }
  .eq-tur-kaki { justify-content: flex-end; }
  .eq-tur-lewati { order: 3; width: 100%; text-align: center; }
}

/* ══ yang meminta gerakan seminimal mungkin ══ */
@media (prefers-reduced-motion: reduce) {
  .eq-tur,
  .eq-tur-tirai { animation: none; }
  .eq-tur-putar { animation: none; }

  .eq-tur-geser-enter-active,
  .eq-tur-geser-leave-active { transition: opacity .1s linear; }

  .eq-tur-geser-enter-from,
  .eq-tur-geser-leave-to { transform: none; }

  .eq-tur-pilar li:hover,
  .eq-tur-modul li:hover,
  .eq-tur-maju:hover,
  .eq-tur-maju:active { transform: none; }
}
</style>

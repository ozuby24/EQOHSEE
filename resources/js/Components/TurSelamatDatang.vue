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
 * ── Isinya diambil saat dibuka ──
 *
 * Bukan dititipkan pada prop bersama. Sembilan setengah kilobita yang
 * ikut pada tiap pembukaan halaman adalah harga yang dibayar terus-
 * menerus untuk sesuatu yang dibaca sekali. Lihat App\Support\Tur.
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import IkonPilar from './IkonPilar.vue';

const props = defineProps<{ terbuka: boolean }>();
const emit  = defineEmits<{ tutup: [] }>();

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
const panel   = ref<HTMLElement | null>(null);

const kini    = computed(() => langkah.value[ke.value] ?? null);
const terakhir = computed(() => ke.value >= langkah.value.length - 1);

async function ambilIsi(): Promise<void> {
  memuat.value = true;
  gagal.value  = false;

  try {
    const r = await fetch('/tur', { headers: { Accept: 'application/json' } });
    if (!r.ok) throw new Error(String(r.status));

    langkah.value = (await r.json()).langkah ?? [];
    ke.value = 0;
  } catch {
    /* Pengenalan yang gagal dimuat tidak boleh menyandera halamannya.
       Yang ditawarkan cuma dua: coba lagi, atau tutup dan bekerja. */
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
function selesai(): void {
  const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

  void fetch('/tur/selesai', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
  }).catch(() => {});

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

      <!-- ── kepala ─────────────────────────────────────────────── -->
      <div class="eq-tur-kepala">
        <div class="eq-tur-langkah" aria-hidden="true">
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

      <!-- ── isi ────────────────────────────────────────────────── -->
      <div class="eq-tur-isi">
        <p v-if="memuat" class="eq-tur-tunggu">Menyiapkan pengenalan…</p>

        <div v-else-if="gagal" class="eq-tur-gagal">
          <h2 id="eq-tur-judul" class="eq-tur-judul">Pengenalan gagal dimuat</h2>
          <p class="eq-tur-teks">
            Sambungannya terputus. Anda tetap dapat memakai situs seperti biasa —
            pengenalan ini ada di menu akun bila ingin dibuka lagi nanti.
          </p>
          <button type="button" class="eq-btn-lain" @click="ambilIsi">Coba lagi</button>
        </div>

        <template v-else-if="kini">
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
                <a :href="url" class="eq-tur-tautan" @click="selesai">{{ judul }}</a>
                <small>{{ isi }}</small>
              </span>
            </li>
          </ol>
        </template>
      </div>

      <!-- ── kaki ───────────────────────────────────────────────── -->
      <div v-if="!memuat && !gagal && kini" class="eq-tur-kaki">
        <button type="button" class="eq-tur-lewati" @click="selesai">
          {{ terakhir ? 'Tutup' : 'Lewati pengenalan' }}
        </button>

        <span class="eq-tur-sela" />

        <button v-if="ke > 0" type="button" class="eq-btn-lain" @click="mundur">Kembali</button>

        <button type="button" class="eq-btn-utama" @click="maju">
          {{ terakhir ? 'Mulai bekerja' : 'Lanjut' }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.eq-tur-tirai {
  position: fixed;
  inset: 0;
  z-index: 70;
  display: grid;
  place-items: center;
  padding: 1rem;
  background: rgb(12 18 32 / .62);
  backdrop-filter: blur(2px);
}

.eq-tur {
  width: 100%;
  max-width: 41rem;
  max-height: min(88vh, 46rem);
  display: flex;
  flex-direction: column;
  background: #FFFFFF;
  border: 1px solid #E7E5E4;
  border-radius: 1.15rem;
  box-shadow: 0 22px 60px rgb(0 0 0 / .3);
  overflow: hidden;
}

.eq-tur:focus {
  outline: none;
}

/* ── kepala ── */
.eq-tur-kepala {
  display: flex;
  align-items: center;
  gap: .8rem;
  padding: .85rem 1.15rem;
  border-bottom: 1px solid #F5F5F4;
}

.eq-tur-langkah {
  display: flex;
  gap: .3rem;
}

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
  font-size: 11.5px;
  font-weight: 600;
  color: #78716C;
  margin: 0;
}

.eq-tur-tutup {
  margin-left: auto;
  display: grid;
  place-items: center;
  width: 1.9rem;
  height: 1.9rem;
  border: 0;
  border-radius: .55rem;
  background: transparent;
  color: #78716C;
  cursor: pointer;
}

.eq-tur-tutup svg { width: 1rem; height: 1rem; }
.eq-tur-tutup:hover { background: rgb(0 0 0 / .05); color: #292524; }

/* ── isi ── */
.eq-tur-isi {
  padding: 1.35rem 1.45rem;
  overflow-y: auto;
}

.eq-tur-judul {
  font-size: 1.32rem;
  font-weight: 700;
  line-height: 1.25;
  color: #1C1917;
  margin: 0 0 .45rem;
}

.eq-tur-teks {
  font-size: 13px;
  line-height: 1.6;
  color: #57534E;
  margin: 0;
}

.eq-tur-tunggu {
  font-size: 13px;
  color: #78716C;
  margin: 0;
  padding: 2rem 0;
  text-align: center;
}

.eq-tur-gagal .eq-btn-lain { margin-top: 1rem; }

/* ── sambutan ── */
.eq-tur-poin {
  list-style: none;
  margin: 1.15rem 0 0;
  padding: 0;
  display: grid;
  gap: .7rem;
}

.eq-tur-poin li {
  border-left: 3px solid #F57C00;
  padding-left: .8rem;
}

.eq-tur-poin strong {
  display: block;
  font-size: 12.5px;
  color: #1C1917;
}

.eq-tur-poin span {
  display: block;
  font-size: 12px;
  line-height: 1.55;
  color: #78716C;
  margin-top: .12rem;
}

/* ── pilar dan modul ── */
.eq-tur-pilar,
.eq-tur-modul {
  list-style: none;
  margin: 1.15rem 0 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr));
  gap: .5rem;
}

.eq-tur-pilar li,
.eq-tur-modul li {
  display: flex;
  align-items: center;
  gap: .6rem;
  padding: .5rem .6rem;
  border: 1px solid #F5F5F4;
  border-radius: .7rem;
  background: #FAFAF9;
}

.eq-tur-pilar strong,
.eq-tur-modul strong {
  display: block;
  font-size: 12.5px;
  color: #1C1917;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.eq-tur-pilar small,
.eq-tur-modul small {
  display: block;
  font-size: 11px;
  color: #78716C;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.eq-tur-lencana {
  flex: none;
  display: grid;
  place-items: center;
  width: 1.85rem;
  height: 1.85rem;
  border-radius: .55rem;
  color: #FFFFFF;
}

.eq-tur-modul svg {
  flex: none;
  width: 1.15rem;
  height: 1.15rem;
  color: #A8A29E;
}

/* ── langkah pertama ── */
.eq-tur-mulai {
  list-style: none;
  margin: 1.15rem 0 0;
  padding: 0;
  display: grid;
  gap: .75rem;
}

.eq-tur-mulai li {
  display: flex;
  align-items: flex-start;
  gap: .7rem;
}

.eq-tur-angka {
  flex: none;
  display: grid;
  place-items: center;
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 99px;
  background: #F57C00;
  color: #FFFFFF;
  font-size: 11.5px;
  font-weight: 700;
}

.eq-tur-tautan {
  font-size: 13px;
  font-weight: 700;
  color: #C2410C;
  text-decoration: none;
}

.eq-tur-tautan:hover { text-decoration: underline; }

.eq-tur-mulai small {
  display: block;
  font-size: 12px;
  line-height: 1.55;
  color: #78716C;
  margin-top: .15rem;
}

/* ── kaki ── */
.eq-tur-kaki {
  display: flex;
  align-items: center;
  gap: .5rem;
  flex-wrap: wrap;
  padding: .85rem 1.15rem;
  border-top: 1px solid #F5F5F4;
  background: #FAFAF9;
}

.eq-tur-sela { flex: 1 1 auto; }

.eq-tur-lewati {
  border: 0;
  background: transparent;
  padding: .5rem .2rem;
  font-size: 12px;
  font-weight: 600;
  color: #78716C;
  cursor: pointer;
}

.eq-tur-lewati:hover { color: #292524; text-decoration: underline; }

/* ── mode gelap ── */
:global([data-tema='gelap']) .eq-tur {
  background: #14202F;
  border-color: #24364C;
}

:global([data-tema='gelap']) .eq-tur-kepala,
:global([data-tema='gelap']) .eq-tur-kaki {
  border-color: #1E2E42;
}

:global([data-tema='gelap']) .eq-tur-kaki { background: #101A26; }

:global([data-tema='gelap']) .eq-tur-judul,
:global([data-tema='gelap']) .eq-tur-poin strong,
:global([data-tema='gelap']) .eq-tur-pilar strong,
:global([data-tema='gelap']) .eq-tur-modul strong {
  color: #F5F5F4;
}

:global([data-tema='gelap']) .eq-tur-teks,
:global([data-tema='gelap']) .eq-tur-poin span,
:global([data-tema='gelap']) .eq-tur-pilar small,
:global([data-tema='gelap']) .eq-tur-modul small,
:global([data-tema='gelap']) .eq-tur-mulai small,
:global([data-tema='gelap']) .eq-tur-hitung,
:global([data-tema='gelap']) .eq-tur-tunggu,
:global([data-tema='gelap']) .eq-tur-lewati {
  color: #A8A29E;
}

:global([data-tema='gelap']) .eq-tur-pilar li,
:global([data-tema='gelap']) .eq-tur-modul li {
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

/* ── layar sempit ── */
@media (max-width: 30rem) {
  .eq-tur-tirai { padding: .5rem; }
  .eq-tur { max-height: 94vh; }
  .eq-tur-isi { padding: 1.1rem; }
  .eq-tur-judul { font-size: 1.15rem; }

  /* Kaki ikut menumpuk: pada 360px, tiga tombol berdampingan membuat
     yang paling kanan — "Lanjut", satu-satunya yang benar-benar
     dipakai — terpotong tepi layar. */
  .eq-tur-sela { display: none; }
  .eq-tur-kaki { justify-content: flex-end; }
  .eq-tur-lewati { order: 3; width: 100%; text-align: center; }
}
</style>

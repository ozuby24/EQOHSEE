<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import KartuCuaca from './KartuCuaca.vue';

/**
 * Kop halaman bergambar — remah, judul, dan tagline.
 *
 * GAMBARNYA SELALU DITUTUP LAPISAN GELAP, bukan dipasang apa adanya.
 * Foto tambang berlatar matahari terbenam punya bagian terang dan
 * gelap sekaligus; tulisan putih di atasnya terbaca pada separuh
 * lebarnya dan lenyap pada separuh yang lain. Lapisan itu yang
 * membuat kontrasnya dapat dijamin, bukan pilihan fotonya.
 *
 * TIDAK PERNAH MEMBAWA ANGKA. Kop ini hiasan yang menyebut DI MANA
 * pembacanya berada; angka yang perlu ditindak tinggal di kartu di
 * bawahnya, yang dapat dibaca ulang, disalin, dan diurutkan. Angka di
 * dalam gambar tidak dapat diperlakukan begitu — dan yang paling
 * penting selalu berakhir menjadi yang paling sulit dibaca.
 *
 * SATU RUPA DI SELURUH APLIKASI. Ditulis ulang tiap halaman, kopnya
 * akan berselisih tingginya, jarak remahnya, dan ukuran judulnya —
 * dan selisih itu terbaca sebagai halaman yang dikerjakan orang
 * berbeda pada aplikasi yang sama.
 */
const props = withDefaults(defineProps<{
  judul: string;
  subjudul?: string | null;

  /**
   * Label kecil beraksen di atas judul — nama modulnya.
   *
   * Judul halaman menyebut HALAMANNYA; label ini menyebut DI MANA
   * halaman itu berada. Tanpa keduanya, "Rekapitulasi" pada modul
   * penilaian dan "Rekapitulasi" pada modul gudang tercetak sama
   * persis, dan yang membedakannya hanyalah remah roti setinggi
   * sebelas piksel di atasnya.
   */
  label?: string | null;

  /** Remah roti: [label, alamat]. Alamat null berarti halaman ini. */
  remah?: [string, string | null][];

  /** Tagline tulisan tangan di kanan atas. */
  tagline?: string | null;

  /** Pil fitur di bawah subjudul: [judul, keterangan, nada]. */
  pil?: [string, string, ('lime' | 'sky' | 'orange')?][];

  /**
   * Satu angka utama di dalam kop, beserta angka pendampingnya.
   *
   * HANYA SATU ANGKA, dan hanya angka yang memang menjadi jawaban
   * halaman itu. Kop adalah tempat yang paling menarik perhatian dan
   * paling sulit dibaca ulang: tulisannya putih di atas foto, tidak
   * dapat disalin dengan rapi, tidak dapat diurutkan, dan tidak dapat
   * dibandingkan dengan baris di sebelahnya. Angka yang perlu
   * ditindak tinggal di kartu di bawahnya.
   *
   * Diisi dua atau tiga angka, yang terjadi bukan kop yang lebih
   * informatif melainkan kop yang tidak punya jawaban.
   */
  angka?: { label: string; nilai: string; catatan?: string | null } | null;

  /** Angka pendamping di sebelahnya: [label, nilai, nada?]. */
  sisi?: [string, string, ('baik' | 'ingat' | 'gawat')?][];

  /**
   * Satu tindakan utama, di dalam kop.
   *
   * SATU SAJA. Kop yang memuat tiga tombol berhenti menjadi kop dan
   * menjadi bilah perkakas — dan bilah perkakas yang bertumpuk di atas
   * foto adalah tempat terburuk untuk menaruh tombol yang benar-benar
   * ditekan orang. Tindakan lain tinggal di kartu yang memilikinya.
   */
  aksi?: { label: string; url: string } | null;

  /** Keterangan kecil di kanan — tanggal, periode, jumlah. */
  kanan?: string | null;
  kananKecil?: string | null;

  /** Lebih pendek: untuk halaman daftar yang isinya panjang. */
  ringkas?: boolean;

  /**
   * Sampul modul: foto beserta keadaan situs, digambar DI DALAM kop ini.
   *
   * Semula keduanya berdiri sebagai bilah sendiri tepat di atas kop.
   * Hasilnya dua pita berfoto bertumpuk setinggi hampir separuh layar
   * sebelum satu baris isi pun terlihat — dan keduanya menjawab hal yang
   * sama: di mana kita, sedang melihat apa.
   *
   * Disatukan di sini, bukan disatukan di AppLayout, sebab kop inilah
   * yang sudah memiliki fotonya sendiri, tirainya, dan aturan tingginya.
   * Menyusun pita kedua yang meniru ketiganya berarti dua tempat yang
   * harus dijaga tetap sama selamanya.
   */
  gambar?: string | null;
  webp?: string | null;
  keterangan?: string | null;
  kondisi?: {
    lokasi: { nama: string | null; perusahaan: string; koordinat: string | null } | null;
    cuaca: {
      kunci: string; label: string; hujanMm: number;
      tingkat: number; skala: number;
      tanggal: string | null; hariIni: boolean;
    } | null;
    waktu: { jam: string; zona: string; tanggal: string };
  } | null;
}>(), {
  subjudul: null, label: null, remah: () => [], tagline: null, pil: () => [],
  angka: null, sisi: () => [], aksi: null,
  kanan: null, kananKecil: null, ringkas: false,
  gambar: null, webp: null, keterangan: null, kondisi: null,
});

/* ─────────── Garis aksen di bawah semboyan ───────────
 *
 * Lebarnya DIUKUR dari tulisannya, tidak ditetapkan sebagai persentase.
 *
 * Semula `width: 78%` — 78% dari KOTAK semboyan, bukan dari tulisannya.
 * Keduanya hanya sama panjang bila semboyannya muat satu baris; begitu
 * ia membungkus, kotaknya melebar sampai batas maksimum sementara
 * barisnya tetap pendek. Terukur di peramban:
 *
 *   "Proven On Paper"        tulisan 197,2  garis 153,8   (44 px kurang)
 *   "Clean Water Downstream" tulisan 148,0  garis 191,9   (44 px lebih)
 *   "Systems That Hold"      tulisan 220,2  garis 171,7   (49 px kurang)
 *
 * Jadi garisnya meleset ke DUA arah, dan tidak satu pun kelipatan tetap
 * dapat membetulkan keduanya sekaligus. Dari 27 semboyan modul, yang
 * terpanjang 36 karakter — ia pasti membungkus, sehingga "buat saja
 * semuanya satu baris" bukan jalan keluar.
 *
 * Range.getClientRects() memberi kotak TIAP BARIS; yang dipakai baris
 * terlebar, sebab semboyannya rata kanan dan garis itu duduk di
 * bawahnya. Diukur ulang saat lebarnya berubah dan saat semboyannya
 * berganti (berpindah modul tanpa memuat ulang halaman).
 */
const semboyan = ref<HTMLSpanElement | null>(null);

/* 100% sebagai cadangan, bukan 0: bila pengukurannya tidak pernah
   berjalan — skrip gagal, mesin gambar lain — yang tampak adalah garis
   selebar kotaknya, bukan garis yang hilang sama sekali. */
const lebarGaris = ref('100%');

function ukurGaris() {
  const el = semboyan.value;
  if (!el) return;

  /* Semboyannya dibungkus <span> SEBARIS, dan getClientRects() pada
     elemen sebaris memberi satu kotak per BARIS — persis yang dicari.
     Range.selectNodeContents() memberi hal yang sama, tetapi menuntut
     simpul DOM mentah dan tidak menjelaskan dirinya sendiri saat
     dibaca ulang. */
  const baris = [...el.getClientRects()].filter((r) => r.width > 1);

  if (!baris.length) return;

  lebarGaris.value = `${Math.round(Math.max(...baris.map((r) => r.width)))}px`;
}

let pengawas: ResizeObserver | null = null;

onMounted(() => {
  ukurGaris();

  /* Diukur ULANG setelah fontnya benar-benar terpasang. Semboyan ini
     memakai muka huruf miring yang belum tentu sudah siap saat komponen
     dipasang, dan huruf cadangan lebarnya berbeda — garis yang diukur
     terhadap huruf cadangan akan meleset begitu huruf aslinya masuk. */
  document.fonts?.ready.then(ukurGaris).catch(() => {});

  const el = semboyan.value;

  /* ResizeObserver, bukan penangan resize jendela: bilah samping dapat
     dilipat tanpa jendelanya berubah ukuran sedikit pun, dan lebar kop
     ikut berubah karenanya. Penangan jendela akan melewatkan persis
     perubahan yang paling sering terjadi.

     Cast-nya artefak tipe, bukan keraguan: vue-tsc di sini menyelesaikan
     dua salinan pustaka DOM, sehingga HTMLSpanElement dari template ref
     tidak nominal cocok dengan Element milik ResizeObserver. Nilainya
     sendiri memang elemen <span> yang sama. */
  if (typeof ResizeObserver !== 'undefined' && el) {
    pengawas = new ResizeObserver(ukurGaris);
    pengawas.observe(el as unknown as Element);
  }
});

onBeforeUnmount(() => pengawas?.disconnect());

watch(() => props.tagline, () => nextTick(ukurGaris));
</script>

<template>
  <section class="kop" :class="[ringkas ? 'kop-ringkas' : null, kondisi ? 'kop-bersitus' : null]">
    <!-- Foto modulnya bila ada; foto merek bila belum. Bukan dua pita.
         `eager` untuk yang bermodul: ia berada di puncak halaman, dan
         `lazy` pada gambar paling atas justru menunda yang pasti
         terlihat. -->
    <picture v-if="gambar">
      <source v-if="webp" :srcset="webp" type="image/webp">
      <img class="kop-gambar" :src="gambar" alt="" aria-hidden="true"
           fetchpriority="high" decoding="async">
    </picture>
    <img v-else class="kop-gambar" src="/brand/tambang.jpg" alt="" aria-hidden="true"
         loading="lazy" decoding="async">

    <div class="kop-tirai" aria-hidden="true" />

    <div class="kop-isi">
      <nav v-if="remah.length" class="kop-remah" aria-label="Remah roti">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="m3 10.5 9-7 9 7V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1Z"/>
        </svg>

        <template v-for="([label, alamat], i) in remah" :key="i">
          <span v-if="i" class="kop-panah" aria-hidden="true">›</span>
          <a v-if="alamat" :href="alamat">{{ label }}</a>
          <span v-else class="kop-kini">{{ label }}</span>
        </template>
      </nav>

      <div class="kop-baris">
        <div class="min-w-0">
          <p v-if="label" class="kop-label">{{ label }}</p>
          <h1 class="kop-judul">{{ judul }}</h1>
          <p v-if="subjudul" class="kop-subjudul">{{ subjudul }}</p>

          <!-- Geo tag. Koordinatnya datang dari titik tengah layer peta
               yang sudah digambar, bukan diketik terpisah — yang diketik
               terpisah akan berselisih dengan petanya sendiri. Kosong
               bila petanya belum diikat ke koordinat sesungguhnya. -->
          <p v-if="kondisi?.lokasi" class="kop-lokasi">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z"/>
              <circle cx="12" cy="10" r="2.6"/>
            </svg>
            <b>{{ kondisi.lokasi.nama ?? kondisi.lokasi.perusahaan }}</b>
            <span v-if="kondisi.lokasi.koordinat" class="kop-koordinat">
              {{ kondisi.lokasi.koordinat }}
            </span>
            <span class="kop-tanggal-situs">{{ kondisi.waktu.tanggal }}</span>
          </p>

          <!-- Keterangan foto. Tanpa ini, rekaman umum pada halaman resmi
               mudah disangka foto situs perusahaan yang sedang membukanya.

               Di kolom KIRI, bukan kanan. Tirai kop gelap penuh di sisi
               ini dan menipis sampai bening di sisi seberang — tepat di
               tempat sebagian foto sampul paling terang: matahari
               terbenam di dermaga, langit pagi di atas pit. Terukur di
               sana tulisannya praktis hilang. Di sini ia juga berdampingan
               dengan geo tag, dan keduanya memang menjawab hal yang
               sama: di mana ini, dan yang terlihat apa. -->
          <p v-if="keterangan" class="kop-keterangan">{{ keterangan }}</p>

          <div v-if="pil.length" class="kop-pil-baris">
            <span v-for="([atas, bawah, nada], i) in pil" :key="i" class="kop-pil">
              <span class="kop-pil-ikon" :class="'kop-pil-' + (nada ?? 'lime')" aria-hidden="true">
                <slot :name="'pil-' + i">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                       stroke-linecap="round" stroke-linejoin="round">
                    <path d="m5 12.5 4.5 4.5L19 7.5"/>
                  </svg>
                </slot>
              </span>
              <span class="min-w-0">
                <strong>{{ atas }}</strong>
                <small>{{ bawah }}</small>
              </span>
            </span>
          </div>

          <!-- Panel angka. Di dalam kolom kiri, di bawah subjudul: ia
               menjawab judulnya, jadi ia harus terbaca sesudah judulnya
               — bukan di seberang halaman. -->
          <div v-if="angka || sisi.length" class="kop-angka-baris">
            <div v-if="angka" class="kop-angka">
              <span class="kop-angka-ikon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                     stroke-linecap="round" stroke-linejoin="round">
                  <path d="M5 20V11M12 20V5M19 20v-6"/>
                </svg>
              </span>

              <span class="min-w-0">
                <small>{{ angka.label }}</small>
                <strong class="num">{{ angka.nilai }}</strong>
                <em v-if="angka.catatan">{{ angka.catatan }}</em>
              </span>
            </div>

            <div v-if="sisi.length" class="kop-sisi">
              <span v-for="([l, v, nada], i) in sisi" :key="i" class="kop-sisi-butir">
                <small>{{ l }}</small>
                <strong class="num" :class="nada ? 'kop-sisi-' + nada : null">{{ v }}</strong>
              </span>
            </div>
          </div>
        </div>

        <div class="kop-kanan">
          <!-- Jam dan cuaca situs. Jamnya berdiri sendiri dari kartu
               cuaca: ia selalu ada, sedangkan cuacanya bergantung pada
               catatan hujan. Menyatukan keduanya membuat jam ikut hilang
               pada situs yang belum mencatat apa pun. -->
          <div v-if="kondisi" class="kop-situs">
            <div class="eq-cuaca kop-jam">
              <b>{{ kondisi.waktu.jam }}</b>
              <small>{{ kondisi.waktu.zona }}</small>
            </div>

            <KartuCuaca :cuaca="kondisi.cuaca" />
          </div>

          <span v-if="kanan" class="kop-cap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M8 3v3m8-3v3M3.5 9.5h17M5 5.5h14a1.5 1.5 0 0 1 1.5 1.5v12A1.5 1.5 0 0 1 19 20.5H5A1.5 1.5 0 0 1 3.5 19V7A1.5 1.5 0 0 1 5 5.5Z"/>
            </svg>
            <span>
              <strong>{{ kanan }}</strong>
              <small v-if="kananKecil">{{ kananKecil }}</small>
            </span>
          </span>

          <p v-if="tagline" class="kop-tagline" aria-hidden="true"
             :style="{ '--kop-garis': lebarGaris }"><span ref="semboyan">{{ tagline }}</span></p>

          <a v-if="aksi" :href="aksi.url" class="kop-aksi">
            {{ aksi.label }}
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M5 12h13M13 6.5 18.5 12 13 17.5"/>
            </svg>
          </a>
        </div>
      </div>
    </div>
  </section>
</template>

<style>
/**
 * Palet kop, sadar tema.
 *
 * Ditulis sebagai kelas dan bukan gaya sebaris supaya aturan mode
 * gelap dapat menimpanya. Latarnya sendiri memang selalu gelap —
 * itulah sebabnya warna tulisannya tidak ikut berganti tema; yang
 * berganti hanyalah pekat tidaknya tirai di atas fotonya, sebab
 * halaman terang di sekelilingnya membuat kop yang sama terbaca
 * lebih mencolok.
 */
.kop {
  position: relative;
  overflow: hidden;
  border-radius: 1rem;
  min-height: 176px;
  display: flex;
  align-items: stretch;
  isolation: isolate;

  /* Latar gelapnya sendiri, bukan warisan fotonya. Tirai di atas
     gambar menipis sampai bening di ujung kanan — tepat di tempat
     taglinenya berdiri — sehingga bila fotonya gagal dimuat, tulisan
     putih itu jatuh di atas latar halaman yang krem dan lenyap. Foto
     adalah hiasan di atas dasar ini, bukan dasarnya. */
  background: #0A1114;
}

.kop-ringkas { min-height: 150px; }

.kop-gambar {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  filter: saturate(1.08) brightness(1.06);
  /* Sunsetnya berada di sepertiga ATAS gambar potret ini; diambil
     dari tengah, yang tampak hanyalah tumpukan batu gelap. */
  object-position: 62% 26%;
  z-index: -2;
}

/* Foto modul dipotong dari TENGAH, bukan dari 62%/26% di atas.
   Angka itu disetel untuk satu foto potret bermatahari terbenam di
   sepertiga atasnya; keenam foto sampul berbentuk 16∶9 mendatar, dan
   memotongnya dengan angka yang sama membuang justru bagian yang
   menerangkan modulnya — excavator, dermaga, jalan angkut. */
picture > .kop-gambar { object-position: 50% 50%; }

/* Keterangan foto: sekecil mungkin, sebab ia keterangan sumber dan
   bukan isi halaman. */
.kop-keterangan {
  margin: .3rem 0 0;
  font-size: 9.5px;
  line-height: 1.3;
  /* Tirai kop MENIPIS ke kanan, tepat di tempat baris ini berdiri, dan
     sebagian foto sampul terang benderang di sisi itu — matahari
     terbenam di dermaga, langit pagi di atas pit. Pada .45 tanpa bayang
     tulisannya hilang sama sekali di sana. */
  color: rgba(255,255,255,.5);
  max-width: 40ch;
}

/* Jam dan kartu cuaca, berdampingan di kanan atas. Membungkus ke bawah
   pada layar sempit alih-alih memeras keduanya. */
/* Kop yang membawa keadaan situs berdiri lebih tinggi.
   Jam, kartu cuaca, tagline, dan keterangan foto bertumpuk di satu
   kolom kanan; pada tinggi 176px keempatnya berdesakan sampai saling
   menimpa — terukur pada halaman awal PJP. */
.kop-bersitus { min-height: 212px; }

.kop-situs {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  align-items: stretch;
  gap: .5rem;
}

.kop-jam {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  justify-content: center;
  border-radius: .75rem;
  padding: .5rem .75rem;
  line-height: 1;
}

.kop-jam b {
  font-size: 15px;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  color: #fff;
}

.kop-jam small {
  margin-top: .25rem;
  font-size: 9.5px;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: rgba(255,255,255,.6);
}

/* Geo tag, tepat di bawah subjudul: ia menerangkan JUDULNYA — di mana
   angka-angka halaman ini diambil — jadi ia dibaca sesudahnya. */
.kop-lokasi {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: .25rem .6rem;
  margin: .55rem 0 0;
  font-size: 11px;
  color: rgba(255,255,255,.8);
}

.kop-lokasi svg { width: .95rem; height: .95rem; flex: none; }
.kop-lokasi b   { font-weight: 700; }

.kop-koordinat {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 10.5px;
  color: rgba(255,255,255,.62);
}

.kop-tanggal-situs { color: rgba(255,255,255,.55); }

/* Tirai: gelap penuh di kiri tempat tulisannya, menipis ke kanan
   tempat fotonya dibiarkan terlihat. */
.kop-tirai {
  position: absolute;
  inset: 0;
  z-index: -1;
  background:
    linear-gradient(100deg, rgba(10,17,20,.92) 0%, rgba(10,17,20,.78) 32%, rgba(10,17,20,.26) 60%, rgba(10,17,20,0) 100%);
}

:root[data-tema="gelap"] .kop-tirai {
  background:
    linear-gradient(100deg, rgba(6,11,13,.94) 0%, rgba(6,11,13,.84) 32%, rgba(6,11,13,.40) 60%, rgba(6,11,13,.12) 100%);
}

.kop-isi {
  position: relative;
  width: 100%;
  padding: 1.15rem 1.35rem 1.3rem;
  display: flex;
  flex-direction: column;
  gap: .55rem;
  color: #fff;
}

.kop-remah {
  display: flex;
  align-items: center;
  gap: .4rem;
  font-size: 11.5px;
  color: rgba(255,255,255,.72);
}

.kop-remah svg { width: 14px; height: 14px; }
.kop-remah a { color: rgba(255,255,255,.78); text-decoration: none; }
.kop-remah a:hover { color: #fff; text-decoration: underline; }
.kop-kini { color: #fff; font-weight: 600; }
.kop-panah { opacity: .55; }

.kop-baris {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1.5rem;

  /* TIDAK MEMBUNGKUS. Dibiarkan membungkus, kolom kanan — cap tanggal
     dan tagline — jatuh ke barisnya sendiri begitu kolom kiri melebar,
     dan kopnya tumbuh setinggi kedua kolom dijumlahkan: 370px pada
     layar 1024px, dengan rongga foto kosong menganga di tengahnya.
     Keduanya kini menyusut bersama; taglinenya memang sudah berbatas
     lebar, dan di bawah 760px ia disembunyikan sama sekali. */
  flex-wrap: nowrap;
}

.kop-label {
  display: flex;
  align-items: center;
  gap: .45rem;
  margin: 0 0 .4rem;
  font-size: 10.5px;
  font-weight: 800;
  letter-spacing: .16em;
  text-transform: uppercase;
  color: var(--eq-aksen, #F57C00);
}

.kop-label::before {
  content: "";
  width: 14px;
  height: 2px;
  border-radius: 999px;
  background: currentColor;
  flex: none;
}

.kop-judul {
  font-size: clamp(1.4rem, 2.8vw, 2.05rem);
  font-weight: 800;
  line-height: 1.15;
  letter-spacing: -.02em;
  margin: 0;
}

.kop-subjudul {
  margin: .3rem 0 0;
  font-size: 12.5px;
  color: rgba(255,255,255,.82);
  max-width: 62ch;
}

.kop-pil-baris { display: flex; flex-wrap: wrap; gap: .55rem; margin-top: .75rem; }

.kop-pil {
  display: inline-flex;
  align-items: center;
  gap: .5rem;
  border-radius: .7rem;
  padding: .38rem .7rem .38rem .4rem;
  background: rgba(255,255,255,.10);
  border: 1px solid rgba(255,255,255,.14);
  backdrop-filter: blur(2px);
}

.kop-pil strong { display: block; font-size: 11.5px; font-weight: 700; line-height: 1.2; }
.kop-pil small  { display: block; font-size: 10.5px; color: rgba(255,255,255,.68); line-height: 1.25; }

.kop-pil-ikon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 26px; height: 26px;
  border-radius: .55rem;
  flex: none;
}

.kop-pil-ikon svg { width: 15px; height: 15px; }

.kop-pil-lime   { background: rgba(163,209,54,.22);  color: #C7E86B; }
.kop-pil-sky    { background: rgba(56,141,219,.24);  color: #9CC9F5; }
.kop-pil-orange { background: rgba(245,124,0,.24);   color: #FFB870; }

.kop-angka-baris {
  display: flex;
  flex-wrap: wrap;
  align-items: stretch;
  gap: .7rem;
  margin-top: .9rem;
}

.kop-angka {
  display: flex;
  align-items: center;
  gap: .75rem;
  border-radius: .9rem;
  padding: .65rem .95rem .7rem;
  background: rgba(255,255,255,.11);
  border: 1px solid rgba(255,255,255,.15);
  backdrop-filter: blur(3px);
}

.kop-angka-ikon {
  display: inline-flex; align-items: center; justify-content: center;
  width: 38px; height: 38px; border-radius: .7rem; flex: none;
  background: rgba(245,124,0,.26); color: #FFB870;
}

.kop-angka-ikon svg { width: 19px; height: 19px; }

.kop-angka small {
  display: block; font-size: 9.5px; font-weight: 800;
  letter-spacing: .13em; text-transform: uppercase;
  color: #FFB870;
}

.kop-angka strong {
  display: block; font-size: 26px; font-weight: 800;
  line-height: 1.08; letter-spacing: -.02em; color: #fff;
}

.kop-angka em {
  display: block; font-style: normal; font-size: 10px;
  color: rgba(255,255,255,.6);
}

.kop-sisi {
  display: flex; flex-wrap: wrap; align-items: center; gap: 1.4rem;
  padding: .65rem .3rem;
}

.kop-sisi-butir small {
  display: block; font-size: 10px; color: rgba(255,255,255,.62);
}

.kop-sisi-butir strong {
  display: block; font-size: 15px; font-weight: 800;
  line-height: 1.15; color: #fff;
}

.kop-sisi-baik  { color: #8FE3BE; }
.kop-sisi-ingat { color: #F6D488; }
.kop-sisi-gawat { color: #F5A9A9; }

.kop-kanan {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: .6rem;
  margin-left: auto;
}

.kop-cap {
  display: inline-flex;
  align-items: center;
  gap: .5rem;
  border-radius: .7rem;
  padding: .4rem .7rem;
  background: rgba(255,255,255,.12);
  border: 1px solid rgba(255,255,255,.16);
  white-space: nowrap;
}

.kop-cap svg { width: 15px; height: 15px; opacity: .85; }
.kop-cap strong { display: block; font-size: 11.5px; font-weight: 700; }
.kop-cap small  { display: block; font-size: 10.5px; color: rgba(255,255,255,.7); }

/* Tagline tulisan tangan. Miring dan berbobot ringan supaya ia
   terbaca sebagai hiasan, bukan sebagai kalimat yang harus dibaca —
   karena itu pula ia aria-hidden. */
.kop-tagline {
  margin: 0;
  font-family: ui-rounded, "Segoe UI", system-ui, sans-serif;
  font-style: italic;
  font-weight: 700;
  font-size: clamp(.95rem, 1.7vw, 1.3rem);
  line-height: 1.15;
  text-align: right;
  color: rgba(255,255,255,.92);
  text-shadow: 0 1px 12px rgba(0,0,0,.45);

  /* Cukup lebar untuk semboyan empat kata tanpa memecahnya menjadi
     empat baris, dan tetap dibatasi lebar layar supaya ia tidak pernah
     menyeberangi judul di sebelah kirinya. */
  max-width: min(17ch, 30vw);

  /* Barisnya dibagi rata, bukan diisi penuh lalu sisanya menggantung.
     "Reliable System For A Safer Tomorrow" tanpa ini terpecah menjadi
     satu baris panjang dan satu kata tersisa — dan garis di bawahnya,
     yang mengikuti baris terlebar, jadi jauh lebih panjang daripada
     kesan seimbang yang dituju. */
  text-wrap: balance;
}

.kop-aksi {
  display: inline-flex;
  align-items: center;
  gap: .5rem;
  padding: .6rem 1rem;
  border-radius: .75rem;
  font-size: 12.5px;
  font-weight: 700;
  text-decoration: none;
  white-space: nowrap;
  color: #fff;
  background: var(--eq-aksen, #F57C00);
  box-shadow: 0 4px 16px -4px rgba(245,124,0,.6);
  transition: transform .15s ease, box-shadow .15s ease;
}

.kop-aksi:hover { transform: translateY(-1px); box-shadow: 0 6px 20px -4px rgba(245,124,0,.7); }
.kop-aksi svg { width: 15px; height: 15px; }

.kop-tagline::after {
  content: "";
  display: block;
  height: 3px;

  /* Selebar baris terlebar semboyannya — diukur, lihat ukurGaris().
     Cadangannya 100%, bukan persentase sembarang: bila pengukurannya
     tidak berjalan, yang tampak garis selebar kotaknya, bukan garis
     yang meleset separuh. */
  width: var(--kop-garis, 100%);

  /* Rata kanan bersama semboyannya. Keduanya berbagi satu tepi kanan,
     dan justru tepi itulah yang membuat pasangan ini terbaca rapi. */
  margin: .45rem 0 0 auto;
  border-radius: 999px;
  background: #F57C00;
}

/* ── Layar sempit ──
   TINGGINYA DIKURANGI, BUKAN DIBIARKAN. Taglinenya disembunyikan di
   bawah 760px karena ia tidak muat di samping judul; tanpa mengurangi
   tingginya, yang tersisa adalah kop setinggi 176px yang separuh
   kanannya foto kosong — judul pendek di kiri dan tidak ada apa pun di
   sisa lebarnya. Yang terbaca bukan kop yang lapang melainkan kop yang
   isinya gagal dimuat. */
@media (max-width: 760px) {
  .kop { min-height: 132px; }
  .kop-ringkas { min-height: 118px; }
  .kop-isi { padding: .95rem 1.1rem 1.05rem; }
  .kop-tagline { display: none; }
  .kop-kanan { align-items: flex-start; margin-left: 0; }

  /* Di bawah ambang ini taglinenya sudah hilang, sehingga kolom kanan
     tinggal satu cap kecil — dan membungkus menjadi pilihan yang lebih
     baik daripada memeras judulnya. */
  .kop-baris { flex-wrap: wrap; }

  /* Tirainya dipekatkan sampai ujung kanan: tanpa tagline, sisi itu
     tidak lagi punya tulisan yang perlu kontras — tetapi fotonya
     menjadi terlalu terang di sebelah judul yang mengecil. */
  .kop-tirai {
    background:
      linear-gradient(100deg, rgba(10,17,20,.94) 0%, rgba(10,17,20,.86) 45%, rgba(10,17,20,.55) 100%);
  }
}

@media (max-width: 560px) {
  .kop-angka-baris { gap: .5rem; }
  .kop-angka { padding: .55rem .75rem .6rem; }
  .kop-angka strong { font-size: 20px; }
  .kop-angka-ikon { width: 32px; height: 32px; }
  .kop-angka-ikon svg { width: 16px; height: 16px; }
  .kop-sisi { gap: 1rem; padding: .4rem .2rem; }
}
</style>

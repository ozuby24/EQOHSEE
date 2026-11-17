<script setup lang="ts">
/**
 * Satu materi: ikhtisar lebih dulu, lalu isinya.
 *
 * ── Kenapa ikhtisar mendahului isi ──
 *
 * Sebelum halaman ini ada, materi hanyalah sebaris tautan di dalam
 * daftar modul. Yang menekannya tidak tahu apa pun sebelum berkasnya
 * terbuka: berapa lama, apa yang akan dikuasainya, dan — yang paling
 * merugikan pada pelatihan K3 — apakah ada SOP yang seharusnya dibaca
 * lebih dulu. Materi praktik yang dibuka mendahului prosedurnya adalah
 * urutan yang justru hendak dicegah pelatihannya.
 *
 * ── Satu halaman, bukan dua ──
 *
 * Ikhtisarnya keadaan AWAL halaman ini, bukan halaman tersendiri. Dua
 * alamat berarti dua klik untuk sampai ke isi setiap kali materi yang
 * sama dibuka lagi, dan materi yang sedang dikerjakan memang dibuka
 * berulang kali. Materi yang sudah selesai membuka isinya langsung:
 * yang mengulang sesuatu tidak perlu diperkenalkan lagi kepadanya.
 *
 * ── Tab, dan apa yang TIDAK ada di dalamnya ──
 *
 * Catatan melekat pada MODUL, bukan pada materi, dan tabnya mengatakan
 * itu apa adanya. Catatan yang tampak milik satu materi tetapi diam-diam
 * dibagi sepuluh materi lain adalah kejutan yang baru ketahuan ketika
 * seseorang mengira catatannya hilang.
 */
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import type { HalamanMateriBelajar } from '../../types';
import Dialog from '../../Components/Dialog.vue';
import IkonStat from '../../Components/IkonStat.vue';
import KurikulumKursus from '../../Components/KurikulumKursus.vue';
import Putaran from '../../Components/Putaran.vue';
import { useDialog } from '../../dialog';

/* `tanya` diberi nama lain: halaman ini SUDAH punya prop bernama
   `tanya` — utas pertanyaan pada materinya — dan destrukturisasi dengan
   nama bawaan menutupinya diam-diam. TypeScript menangkapnya sebagai
   `never`, tetapi tanpa tipe yang ketat cacatnya akan tampak sebagai
   daftar tanya jawab yang tiba-tiba kosong. */
const { dialog, tanya: konfirmasi, batal, lanjut } = useDialog();

const props = defineProps<HalamanMateriBelajar>();

/**
 * Isinya sudah terbuka?
 *
 * Materi yang sudah selesai membukanya langsung. Sisanya menunggu
 * tombol "Mulai materi" — itulah yang membuat ikhtisarnya benar-benar
 * dibaca dan bukan dilewati begitu saja.
 */
const mulai = ref(props.materi.selesai);

/* Dipulihkan pada TIAP perpindahan materi. Tombol "Berikutnya" adalah
   kunjungan Inertia yang mempertahankan komponen ini, sehingga tanpa
   watch ini materi berikutnya akan terbuka langsung isinya — melewati
   ikhtisar yang justru menjadi maksud halaman ini. */
watch(() => props.materi.id, () => {
  mulai.value = props.materi.selesai;
  tab.value = 'ikhtisar';
  tanyaKe.value = null;
});

/**
 * Panggungnya hanya berisi satu kartu tautan?
 *
 * Kartu tautan sudah punya bingkainya sendiri, dan bingkai panggung di
 * sekelilingnya menghasilkan kotak di dalam kotak — dua garis sejajar
 * berjarak satu setengah sentimeter yang tidak memisahkan apa pun.
 * Panggung baru menggambar bingkainya sendiri ketika ada yang perlu
 * dibingkai: layar video, atau bacaan.
 */
const tautanSaja = computed(
  () => !props.materi.semat && !props.materi.bacaan && Boolean(props.materi.tautan),
);

type Tab = 'ikhtisar' | 'catatan' | 'lampiran' | 'tanya';
const tab = ref<Tab>('ikhtisar');

const daftarTab = computed(() => [
  { kunci: 'ikhtisar' as Tab, label: 'Ikhtisar', jumlah: null },
  { kunci: 'catatan'  as Tab, label: 'Catatan',  jumlah: null },
  { kunci: 'lampiran' as Tab, label: 'Lampiran', jumlah: props.materi.lampiran.length + (props.materi.sop ? 1 : 0) },
  { kunci: 'tanya'    as Tab, label: 'Tanya Jawab', jumlah: props.tanya.length },
]);

/* ── tandai selesai ── */
const menandai = ref(false);

function tandaiSelesai() {
  if (props.materi.selesai || menandai.value) return;

  menandai.value = true;

  router.post(props.materi.urlSelesai, {}, {
    preserveScroll: true,
    onFinish: () => { menandai.value = false; },
  });
}

/* ── catatan ──
   Penanda tersimpan yang HILANG SENDIRI sesudah beberapa detik. Tanda
   permanen "tersimpan" masih terpampang ketika orangnya sudah mengetik
   tiga kalimat baru, dan sejak saat itu ia berbohong. */
const catatanIsi = ref(props.catatan.isi);
const menyimpan  = ref(false);
const tersimpan  = ref(false);
let jamTersimpan: ReturnType<typeof setTimeout> | null = null;

watch(() => props.catatan.isi, (baru) => { catatanIsi.value = baru; });

watch(catatanIsi, () => {
  /* Mengetik lagi memadamkan tandanya seketika. Tanpa ini, tanda
     "tersimpan 2 detik lalu" bertahan di samping teks yang justru belum
     tersimpan. */
  tersimpan.value = false;
  if (jamTersimpan) { clearTimeout(jamTersimpan); jamTersimpan = null; }
});

function simpanCatatan() {
  if (!props.catatan.url || menyimpan.value) return;

  menyimpan.value = true;

  router.post(props.catatan.url, { content: catatanIsi.value }, {
    preserveScroll: true,
    preserveState: true,
    only: ['catatan'],
    onSuccess: () => {
      tersimpan.value = true;
      jamTersimpan = setTimeout(() => { tersimpan.value = false; }, 4000);
    },
    onFinish: () => { menyimpan.value = false; },
  });
}

/* ── tanya jawab ── */
const tanyaKe   = ref<number | null>(null);
const formTanya = useForm({ body: '', parent_id: null as number | null });

function kirimTanya(indukId: number | null) {
  formTanya.parent_id = indukId;

  formTanya.post(props.materi.urlTanya, {
    preserveScroll: true,
    onSuccess: () => { formTanya.reset(); tanyaKe.value = null; },
  });
}

/**
 * Menghapus selalu bertanya lebih dulu.
 *
 * Tombol "Hapus" pada kepala tiap pesan duduk sejauh beberapa milimeter
 * dari "Balas" di layar ponsel, dan pertanyaan yang lenyap pada ketukan
 * pertama tidak menimbulkan galat apa pun — ia hanya hilang, bersama
 * seluruh jawabannya. Tidak ada jalan mengembalikannya.
 *
 * useDialog(), bukan confirm(): pada webview ponsel panggilan bawaan
 * peramban dibungkam aplikasi induknya dan mengembalikan false, sehingga
 * tindakannya diam-diam tidak pernah terjadi.
 */
async function hapusTanya(url: string, milikSendiri: boolean) {
  if (!await konfirmasi(milikSendiri
    ? 'Hapus tulisan Anda? Jawaban yang menempel padanya ikut terhapus.'
    : 'Hapus tulisan ini? Jawaban yang menempel padanya ikut terhapus.')) return;

  router.delete(url, { preserveScroll: true });
}
</script>

<template>
  <Head :title="materi.judul" />

  <div class="eq-mt">
    <div class="eq-mt-utama">

      <!-- ═══ Panggung ═══ -->
      <section class="eq-mt-panggung" :class="{ 'is-tautan-saja': tautanSaja }">

        <!-- Ikhtisar: keadaan awal, sebelum isinya dibuka. -->
        <div v-if="!mulai" class="eq-mt-ikhtisar">
          <!-- Tanpa remah di sini: kop halaman di atasnya sudah menyebut
               kursus dan modulnya, dan dua baris yang menyebut tempat
               yang sama berurutan hanya menggeser judulnya ke bawah. -->
          <div class="eq-mt-lencana">
            <span class="eq-mt-pil" :class="`t-${materi.jenisNada}`">
              <IkonStat :nama="materi.jenisIkon" :ukuran="12" /> {{ materi.jenisLabel }}
            </span>
            <span v-if="materi.durasi" class="eq-mt-menit">{{ materi.durasi }}</span>
            <span v-if="materi.nomor" class="eq-mt-menit">Materi {{ materi.nomor }} dari {{ materi.dari }}</span>
          </div>

          <h1 class="eq-mt-judul">{{ materi.judul }}</h1>
          <p v-if="materi.keterangan" class="eq-mt-ket">{{ materi.keterangan }}</p>

          <!-- "Yang akan Anda pelajari" hanya digambar bila memang diisi.
               Kotak kosong berjudul demikian menjanjikan sesuatu yang
               tidak ada di baliknya. -->
          <div v-if="materi.hasil.length" class="eq-mt-blok">
            <h2 class="eq-mt-blok-judul">Yang akan Anda pelajari</h2>
            <ul class="eq-mt-hasil">
              <li v-for="(h, i) in materi.hasil" :key="i">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
                {{ h }}
              </li>
            </ul>
          </div>

          <div v-if="materi.isiRingkas.length" class="eq-mt-blok">
            <h2 class="eq-mt-blok-judul">Isi materi ini</h2>
            <ul class="eq-mt-isi">
              <li v-for="(b, i) in materi.isiRingkas" :key="i">
                <span class="eq-mt-isi-ikon" aria-hidden="true"><IkonStat :nama="b.ikon" :ukuran="15" /></span>
                <span><strong>{{ b.label }}</strong><small>{{ b.ket }}</small></span>
              </li>
            </ul>
          </div>

          <div v-if="materi.prasyarat" class="eq-mt-syarat">
            <strong>Sebelum mulai</strong>
            <p>{{ materi.prasyarat }}</p>
          </div>

          <div class="eq-mt-aksi">
            <button type="button" class="eq-btn-utama" @click="mulai = true">Mulai materi</button>
            <Link v-if="jelajah.sesudah" :href="jelajah.sesudah.url" class="eq-mt-lewati">
              Lewati ke materi berikutnya →
            </Link>
          </div>
        </div>

        <!-- Isi materinya. -->
        <div v-else class="eq-mt-isi-wadah">
          <template v-if="materi.semat">
            <div class="eq-mt-layar">
              <!-- allow= dibatasi. Bawaan iframe mewariskan izin kamera dan
                   mikrofon halaman induknya kepada inang luar; pemutar
                   video tidak membutuhkan keduanya. -->
              <iframe :src="materi.semat" :title="materi.judul"
                      allow="accelerometer; encrypted-media; picture-in-picture; fullscreen"
                      referrerpolicy="strict-origin-when-cross-origin"
                      allowfullscreen loading="lazy"></iframe>
            </div>

            <!--
              Jalan keluar ketika sematannya tidak dapat dimuat.

              Jaringan site tambang kerap menutup YouTube di tingkat
              proxy, dan iframe yang diblokir tidak memberi tahu apa pun
              kepada halaman induknya — tidak ada peristiwa galat yang
              dapat ditangkap dari asal yang berbeda. Yang dilihat
              pesertanya hanyalah kotak abu-abu tanpa sebab dan tanpa
              jalan keluar.

              Tautannya karena itu SELALU digambar, bukan hanya ketika
              gagal: yang tidak dapat dideteksi tidak dapat dijadikan
              syarat. Satu baris kecil di bawah layar jauh lebih murah
              daripada satu peserta yang menyerah.
            -->
            <p class="eq-mt-cadangan">
              Tidak muncul? Jaringan di sebagian site menutup pemutar video.
              <a :href="materi.tautan ?? materi.semat" target="_blank" rel="noopener noreferrer">
                Buka di tab baru
              </a>
            </p>
          </template>

          <!-- Tautan yang TIDAK boleh disemat digambar sebagai kartu,
               bukan dipaksa masuk iframe. rel=noopener wajib: tanpa itu
               halaman yang dibuka dapat mengarahkan ulang tab asalnya. -->
          <a v-else-if="materi.tautan" :href="materi.tautan" target="_blank" rel="noopener noreferrer"
             class="eq-mt-tautan">
            <span class="eq-mt-tautan-ikon" aria-hidden="true"><IkonStat :nama="materi.jenisIkon" :ukuran="20" /></span>
            <span>
              <strong>Buka {{ materi.jenisLabel.toLowerCase() }} di tab baru</strong>
              <small>{{ materi.tautan }}</small>
            </span>
          </a>

          <div v-if="materi.bacaan" class="eq-mt-bacaan">{{ materi.bacaan }}</div>

          <p v-if="!materi.semat && !materi.tautan && !materi.bacaan" class="eq-mt-hampa">
            Materi ini belum berisi tautan maupun bacaan. Hubungi trainer Anda.
          </p>
        </div>
      </section>

      <!-- ═══ Judul, jelajah, dan tombol selesai ═══ -->
      <section v-if="mulai" class="eq-mt-bawah">
        <div class="eq-mt-bawah-kepala">
          <div class="min-w-0">
            <h1 class="eq-mt-judul-kecil">{{ materi.judul }}</h1>
            <p class="eq-mt-meta">
              <template v-if="modul">Modul {{ modul.urutan }}</template>
              <template v-if="modul && materi.nomor"> · </template>
              <template v-if="materi.nomor">Materi {{ materi.nomor }} dari {{ materi.dari }}</template>
              <template v-if="materi.durasi"> · {{ materi.durasi }}</template>
            </p>
          </div>

          <button v-if="!materi.selesai" type="button" class="eq-btn-utama eq-mt-selesai"
                  :disabled="menandai" @click="tandaiSelesai">
            <Putaran v-if="menandai" :ukuran="13" />
            {{ menandai ? 'Menandai…' : 'Tandai selesai' }}
          </button>

          <span v-else class="eq-mt-sudah">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
            Selesai
          </span>
        </div>

        <!-- ═══ Tab ═══ -->
        <div class="eq-mt-tab" role="tablist">
          <button v-for="t in daftarTab" :key="t.kunci" type="button" role="tab"
                  :aria-selected="tab === t.kunci"
                  :class="{ 'is-aktif': tab === t.kunci }"
                  @click="tab = t.kunci">
            {{ t.label }}<span v-if="t.jumlah" class="eq-mt-tab-jml">{{ t.jumlah }}</span>
          </button>
        </div>

        <div class="eq-mt-panel" role="tabpanel">

          <!-- Ikhtisar -->
          <template v-if="tab === 'ikhtisar'">
            <p v-if="materi.keterangan" class="eq-mt-teks">{{ materi.keterangan }}</p>

            <template v-if="materi.hasil.length">
              <h3 class="eq-mt-sub">Yang akan Anda pelajari</h3>
              <ul class="eq-mt-hasil">
                <li v-for="(h, i) in materi.hasil" :key="i">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"
                       stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
                  {{ h }}
                </li>
              </ul>
            </template>

            <template v-if="materi.prasyarat">
              <h3 class="eq-mt-sub">Sebelum mulai</h3>
              <p class="eq-mt-teks">{{ materi.prasyarat }}</p>
            </template>

            <p v-if="!materi.keterangan && !materi.hasil.length && !materi.prasyarat" class="eq-mt-hampa">
              Ikhtisar materi ini belum diisi.
            </p>
          </template>

          <!-- Catatan -->
          <template v-else-if="tab === 'catatan'">
            <p class="eq-mt-teks eq-mt-kecil">
              Catatan ini milik Anda sendiri dan berlaku untuk seluruh
              <strong v-if="catatan.modul">modul {{ catatan.modul }}</strong>
              <strong v-else>modul ini</strong>, bukan hanya materi ini.
            </p>

            <textarea v-model="catatanIsi" rows="8" class="eq-mt-catatan"
                      placeholder="Tulis catatan…" :disabled="!catatan.url"></textarea>

            <div class="eq-mt-catatan-kaki">
              <button type="button" class="eq-btn-lain" :disabled="menyimpan || !catatan.url"
                      @click="simpanCatatan">
                <Putaran v-if="menyimpan" :ukuran="12" />
                {{ menyimpan ? 'Menyimpan…' : 'Simpan catatan' }}
              </button>

              <!-- role=status: dibacakan tanpa merebut fokus dari kotak
                   isian yang mungkin sedang dipakai lagi. -->
              <span v-if="tersimpan" class="eq-mt-tersimpan" role="status">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
                Tersimpan
              </span>
            </div>
          </template>

          <!-- Lampiran -->
          <template v-else-if="tab === 'lampiran'">
            <ul v-if="materi.sop || materi.lampiran.length" class="eq-mt-lampiran">
              <li v-if="materi.sop">
                <a :href="materi.sop" target="_blank" rel="noopener noreferrer">
                  <span class="eq-mt-lampiran-ikon" aria-hidden="true"><IkonStat nama="sop" :ukuran="16" /></span>
                  <span><strong>SOP terkait</strong><small>Prosedur yang mendasari materi ini</small></span>
                </a>
              </li>
              <li v-for="l in materi.lampiran" :key="l.id">
                <a :href="l.url" target="_blank" rel="noopener noreferrer">
                  <span class="eq-mt-lampiran-ikon" aria-hidden="true"><IkonStat nama="materi" :ukuran="16" /></span>
                  <span><strong>{{ l.judul }}</strong><small>{{ l.url }}</small></span>
                </a>
              </li>
            </ul>

            <p v-else class="eq-mt-hampa">Tidak ada lampiran pada materi ini.</p>
          </template>

          <!-- Tanya jawab -->
          <template v-else>
            <form class="eq-mt-tanya-form" @submit.prevent="kirimTanya(null)">
              <textarea v-model="formTanya.body" rows="3" class="eq-mt-catatan"
                        placeholder="Ada yang ingin ditanyakan tentang materi ini?"></textarea>
              <p v-if="formTanya.errors.body" class="eq-mt-galat">{{ formTanya.errors.body }}</p>

              <button type="submit" class="eq-btn-utama eq-mt-kirim"
                      :disabled="formTanya.processing || !formTanya.body.trim()">
                <Putaran v-if="formTanya.processing && formTanya.parent_id === null" :ukuran="12" />
                {{ formTanya.processing && formTanya.parent_id === null ? 'Mengirim…' : 'Kirim pertanyaan' }}
              </button>
            </form>

            <ul v-if="tanya.length" class="eq-mt-utas">
              <li v-for="t in tanya" :key="t.id">
                <article class="eq-mt-pesan">
                  <div class="eq-mt-pesan-kepala">
                    <strong>{{ t.nama }}</strong>
                    <small v-if="t.jabatan">{{ t.jabatan }}</small>
                    <time>{{ t.waktu }}</time>
                    <button v-if="t.bolehHapus" type="button" class="eq-mt-hapus"
                            @click="hapusTanya(t.urlHapus, t.milikku)">Hapus</button>
                  </div>
                  <p>{{ t.isi }}</p>
                </article>

                <ul v-if="t.jawaban.length" class="eq-mt-jawab">
                  <li v-for="j in t.jawaban" :key="j.id">
                    <article class="eq-mt-pesan">
                      <div class="eq-mt-pesan-kepala">
                        <strong>{{ j.nama }}</strong>
                        <small v-if="j.jabatan">{{ j.jabatan }}</small>
                        <time>{{ j.waktu }}</time>
                        <button v-if="j.bolehHapus" type="button" class="eq-mt-hapus"
                                @click="hapusTanya(j.urlHapus, false)">Hapus</button>
                      </div>
                      <p>{{ j.isi }}</p>
                    </article>
                  </li>
                </ul>

                <button v-if="tanyaKe !== t.id" type="button" class="eq-mt-balas"
                        @click="tanyaKe = t.id; formTanya.reset()">Balas</button>

                <form v-else class="eq-mt-tanya-form eq-mt-balas-form" @submit.prevent="kirimTanya(t.id)">
                  <textarea v-model="formTanya.body" rows="2" class="eq-mt-catatan"
                            placeholder="Tulis jawaban…"></textarea>
                  <div class="eq-mt-balas-kaki">
                    <button type="submit" class="eq-btn-utama eq-mt-kirim"
                            :disabled="formTanya.processing || !formTanya.body.trim()">
                      <Putaran v-if="formTanya.processing" :ukuran="12" />
                      {{ formTanya.processing ? 'Mengirim…' : 'Kirim jawaban' }}
                    </button>
                    <button type="button" class="eq-mt-batal" @click="tanyaKe = null">Batal</button>
                  </div>
                </form>
              </li>
            </ul>

            <p v-else class="eq-mt-hampa">Belum ada pertanyaan pada materi ini.</p>
          </template>
        </div>

        <!-- ═══ Sebelumnya / berikutnya ═══ -->
        <nav class="eq-mt-jelajah">
          <Link v-if="jelajah.sebelum" :href="jelajah.sebelum.url" class="eq-mt-nav">
            <small>← Sebelumnya</small><strong>{{ jelajah.sebelum.judul }}</strong>
          </Link>
          <span v-else></span>

          <Link v-if="jelajah.sesudah" :href="jelajah.sesudah.url" class="eq-mt-nav is-kanan">
            <small>Berikutnya →</small><strong>{{ jelajah.sesudah.judul }}</strong>
          </Link>
        </nav>
      </section>
    </div>

    <KurikulumKursus :modul="kurikulum" :kursus="kursus" />
  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>

<style scoped>
/*
  Dua kolom di layar lebar, satu di bawahnya. `minmax(0, 1fr)` pada kolom
  pertama, bukan `1fr`: kolom grid berukuran `1fr` TIDAK mengerut di bawah
  isinya, jadi satu tautan panjang tanpa spasi di kartu tautan akan
  melebarkan kolomnya dan mendorong kurikulum keluar layar.
*/
.eq-mt {
  display: grid;
  gap: 1.1rem;
  grid-template-columns: minmax(0, 1fr);
  align-items: start;
}

@media (min-width: 1024px) {
  .eq-mt { grid-template-columns: minmax(0, 1fr) 330px; }
}

.eq-mt-utama { display: grid; gap: 1rem; min-width: 0; }

/* ── panggung ── */
.eq-mt-panggung {
  border: 1px solid #E7E5E4;
  border-radius: 18px;
  background: #fff;
  overflow: hidden;
}

.eq-mt-ikhtisar { padding: 1.5rem 1.6rem 1.6rem; }

.eq-mt-lencana { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-top: .75rem; }

.eq-mt-pil {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  padding: .22rem .55rem;
  border-radius: 99px;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: var(--eq-aksen, #D96500);
  background: color-mix(in srgb, var(--eq-aksen, #F57C00) 13%, transparent);
}

.eq-mt-pil.t-biru   { color: #0369A1; background: rgb(11 165 233 / .13); }
.eq-mt-pil.t-merah  { color: #B91C1C; background: rgb(225 29 47 / .12); }
.eq-mt-pil.t-abu    { color: #57534E; background: rgb(28 25 23 / .08); }

.eq-mt-menit { font-size: 11.5px; font-weight: 600; color: #A8A29E; }

.eq-mt-judul {
  margin: .6rem 0 0;
  font-size: 26px;
  font-weight: 800;
  line-height: 1.24;
  color: #1B2024;
}

.eq-mt-ket { margin: .6rem 0 0; font-size: 13.5px; line-height: 1.7; color: #57534E; }

.eq-mt-blok { margin-top: 1.5rem; }

.eq-mt-blok-judul,
.eq-mt-sub {
  margin: 0 0 .7rem;
  font-size: 13px;
  font-weight: 800;
  color: #1B2024;
}

.eq-mt-sub { margin-top: 1.25rem; }

/* Dua kolom bagi daftar hasil belajar — pola yang sama dipakai katalog
   pelatihan mana pun, dan alasannya bukan selera: enam butir sebaris
   ke bawah menjadi kolom sempit sepanjang layar, dan yang dibaca orang
   dari daftar semacam itu hanya dua butir pertama. */
.eq-mt-hasil {
  display: grid;
  gap: .5rem .9rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

@media (min-width: 640px) { .eq-mt-hasil { grid-template-columns: 1fr 1fr; } }

.eq-mt-hasil li {
  display: flex;
  align-items: flex-start;
  gap: .45rem;
  font-size: 12.5px;
  line-height: 1.6;
  color: #44403C;
}

.eq-mt-hasil svg { flex: none; width: .85rem; height: .85rem; margin-top: .2rem; color: #0F9D52; }

.eq-mt-isi { display: grid; gap: .45rem; margin: 0; padding: 0; list-style: none; }

.eq-mt-isi li {
  display: flex;
  align-items: center;
  gap: .65rem;
  padding: .55rem .7rem;
  border: 1px solid #F5F5F4;
  border-radius: 12px;
}

.eq-mt-isi-ikon {
  display: grid;
  place-items: center;
  flex: none;
  width: 2rem;
  height: 2rem;
  border-radius: 9px;
  color: var(--eq-aksen, #D96500);
  background: color-mix(in srgb, var(--eq-aksen, #F57C00) 12%, transparent);
}

.eq-mt-isi strong { display: block; font-size: 12.5px; font-weight: 700; color: #1B2024; }
.eq-mt-isi small { display: block; font-size: 11px; color: #A8A29E; margin-top: 1px; }

.eq-mt-syarat {
  margin-top: 1.4rem;
  padding: .8rem .95rem;
  border-radius: 12px;
  border: 1px solid #FDE68A;
  background: #FFFBEB;
}

.eq-mt-syarat strong { display: block; font-size: 12px; font-weight: 800; color: #92400E; }
.eq-mt-syarat p { margin: .3rem 0 0; font-size: 12.5px; line-height: 1.65; color: #A16207; }

.eq-mt-aksi { display: flex; flex-wrap: wrap; align-items: center; gap: .9rem; margin-top: 1.6rem; }
.eq-mt-lewati { font-size: 12px; font-weight: 700; color: #A8A29E; text-decoration: none; }
.eq-mt-lewati:hover { color: #1B2024; }

/* ── isi ── */
.eq-mt-isi-wadah { display: grid; }

.eq-mt-layar {
  aspect-ratio: 16 / 9;
  background: #0B1117;
}

.eq-mt-layar iframe { width: 100%; height: 100%; border: 0; display: block; }

.eq-mt-cadangan {
  margin: 0;
  padding: .55rem 1.3rem .75rem;
  font-size: 11.5px;
  color: #A8A29E;
}

.eq-mt-cadangan a { font-weight: 700; color: var(--eq-aksen, #D96500); }

/* Tanpa bingkai panggung ketika isinya hanya kartu tautan. */
.eq-mt-panggung.is-tautan-saja {
  border: 0;
  background: none;
  border-radius: 0;
}

.eq-mt-panggung.is-tautan-saja .eq-mt-tautan { margin: 0; }

.eq-mt-tautan {
  display: flex;
  align-items: center;
  gap: .8rem;
  margin: 1.2rem 1.3rem;
  padding: .85rem 1rem;
  border: 1px solid #E7E5E4;
  border-radius: 14px;
  text-decoration: none;
  transition: border-color .15s ease;
}

.eq-mt-tautan:hover { border-color: var(--eq-aksen, #F57C00); }

.eq-mt-tautan-ikon {
  display: grid;
  place-items: center;
  flex: none;
  width: 2.4rem;
  height: 2.4rem;
  border-radius: 11px;
  color: var(--eq-aksen, #D96500);
  background: color-mix(in srgb, var(--eq-aksen, #F57C00) 12%, transparent);
}

.eq-mt-tautan strong { display: block; font-size: 13px; font-weight: 700; color: #1B2024; }

/* Tautan panjang tanpa spasi harus BOLEH diputus, kalau tidak ia
   melebarkan kolomnya dan mendorong kurikulum keluar layar. */
.eq-mt-tautan small {
  display: block;
  font-size: 11px;
  color: #A8A29E;
  margin-top: 1px;
  overflow-wrap: anywhere;
}

.eq-mt-bacaan {
  padding: 1.4rem 1.6rem;
  font-size: 13.5px;
  line-height: 1.85;
  color: #44403C;
  white-space: pre-line;
}

/* ── bawah panggung ── */
.eq-mt-bawah {
  border: 1px solid #E7E5E4;
  border-radius: 18px;
  background: #fff;
  padding: 1.1rem 1.3rem 1.2rem;
}

.eq-mt-bawah-kepala {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: .8rem;
}

.eq-mt-judul-kecil { margin: 0; font-size: 18px; font-weight: 800; color: #1B2024; line-height: 1.3; }
.eq-mt-meta { margin: .25rem 0 0; font-size: 11.5px; font-weight: 600; color: #A8A29E; }

.eq-mt-selesai { margin-left: auto; padding: 8px 15px; font-size: 12px; }

.eq-mt-sudah {
  margin-left: auto;
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  font-size: 12px;
  font-weight: 800;
  color: #0F9D52;
}

.eq-mt-sudah svg { width: .95rem; height: .95rem; }

/* ── tab ── */
.eq-mt-tab {
  display: flex;
  flex-wrap: wrap;
  gap: .2rem;
  margin-top: 1rem;
  border-bottom: 1px solid #F5F5F4;
}

.eq-mt-tab button {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  padding: .55rem .8rem;
  border: 0;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  background: none;
  font-size: 12.5px;
  font-weight: 700;
  color: #A8A29E;
  cursor: pointer;
}

.eq-mt-tab button:hover { color: #1B2024; }

.eq-mt-tab button.is-aktif {
  color: var(--eq-aksen, #D96500);
  border-bottom-color: var(--eq-aksen, #F57C00);
}

.eq-mt-tab-jml {
  padding: .05rem .35rem;
  border-radius: 99px;
  font-size: 10px;
  font-weight: 800;
  background: rgb(28 25 23 / .07);
  color: #57534E;
}

.eq-mt-panel { padding-top: 1.05rem; }

.eq-mt-teks { margin: 0 0 .5rem; font-size: 13px; line-height: 1.75; color: #57534E; }
.eq-mt-kecil { font-size: 12px; color: #A8A29E; }
.eq-mt-hampa { margin: 0; font-size: 12.5px; color: #A8A29E; font-style: italic; }
.eq-mt-galat { margin: .35rem 0 0; font-size: 11.5px; color: #DC2626; }

.eq-mt-catatan {
  width: 100%;
  border: 1px solid #E7E5E4;
  border-radius: 12px;
  padding: .7rem .85rem;
  font: inherit;
  font-size: 12.5px;
  line-height: 1.7;
  resize: vertical;
}

.eq-mt-catatan:focus { outline: 2px solid color-mix(in srgb, var(--eq-aksen, #F57C00) 45%, transparent); outline-offset: 1px; }

.eq-mt-catatan-kaki { display: flex; align-items: center; gap: .7rem; margin-top: .6rem; }

.eq-mt-tersimpan {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  font-size: 11.5px;
  font-weight: 700;
  color: #0F9D52;
}

.eq-mt-tersimpan svg { width: .85rem; height: .85rem; }

/* ── lampiran ── */
.eq-mt-lampiran { display: grid; gap: .45rem; margin: 0; padding: 0; list-style: none; }

.eq-mt-lampiran a {
  display: flex;
  align-items: center;
  gap: .65rem;
  padding: .6rem .75rem;
  border: 1px solid #E7E5E4;
  border-radius: 12px;
  text-decoration: none;
  transition: border-color .15s ease;
}

.eq-mt-lampiran a:hover { border-color: var(--eq-aksen, #F57C00); }

.eq-mt-lampiran-ikon {
  display: grid;
  place-items: center;
  flex: none;
  width: 2rem;
  height: 2rem;
  border-radius: 9px;
  color: var(--eq-aksen, #D96500);
  background: color-mix(in srgb, var(--eq-aksen, #F57C00) 12%, transparent);
}

.eq-mt-lampiran strong { display: block; font-size: 12.5px; font-weight: 700; color: #1B2024; }
.eq-mt-lampiran small { display: block; font-size: 11px; color: #A8A29E; overflow-wrap: anywhere; }

/* ── tanya jawab ── */
.eq-mt-tanya-form { margin-bottom: 1.1rem; }
.eq-mt-kirim { margin-top: .55rem; padding: 7px 14px; font-size: 11.5px; }

.eq-mt-utas { display: grid; gap: 1rem; margin: 0; padding: 0; list-style: none; }

.eq-mt-pesan {
  padding: .7rem .85rem;
  border: 1px solid #F5F5F4;
  border-radius: 12px;
  background: #FCFCFB;
}

.eq-mt-pesan-kepala {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: .45rem;
  margin-bottom: .3rem;
}

.eq-mt-pesan-kepala strong { font-size: 12px; font-weight: 800; color: #1B2024; }
.eq-mt-pesan-kepala small { font-size: 10.5px; color: #A8A29E; }
.eq-mt-pesan-kepala time { font-size: 10.5px; color: #C4BFBA; }

.eq-mt-hapus {
  margin-left: auto;
  border: 0;
  background: none;
  font: inherit;
  font-size: 10.5px;
  font-weight: 700;
  color: #C4BFBA;
  cursor: pointer;
}

.eq-mt-hapus:hover { color: #DC2626; }

.eq-mt-pesan p { margin: 0; font-size: 12.5px; line-height: 1.7; color: #44403C; white-space: pre-line; }

.eq-mt-jawab {
  display: grid;
  gap: .45rem;
  margin: .45rem 0 0 1.4rem;
  padding: 0;
  list-style: none;
  border-left: 2px solid #F5F5F4;
  padding-left: .75rem;
}

.eq-mt-balas, .eq-mt-batal {
  margin-top: .4rem;
  border: 0;
  background: none;
  font: inherit;
  font-size: 11.5px;
  font-weight: 700;
  color: var(--eq-aksen, #D96500);
  cursor: pointer;
}

.eq-mt-batal { color: #A8A29E; }
.eq-mt-balas-form { margin: .5rem 0 0 1.4rem; }
.eq-mt-balas-kaki { display: flex; align-items: center; gap: .7rem; }

/* ── jelajah ── */
.eq-mt-jelajah {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: .6rem;
  margin-top: 1.3rem;
  padding-top: 1rem;
  border-top: 1px solid #F5F5F4;
}

.eq-mt-nav {
  display: grid;
  gap: .1rem;
  min-width: 0;
  padding: .6rem .75rem;
  border: 1px solid #E7E5E4;
  border-radius: 12px;
  text-decoration: none;
  transition: border-color .15s ease;
}

.eq-mt-nav:hover { border-color: var(--eq-aksen, #F57C00); }
.eq-mt-nav.is-kanan { text-align: right; }
.eq-mt-nav small { font-size: 10.5px; font-weight: 700; color: #A8A29E; }
.eq-mt-nav strong {
  font-size: 12.5px;
  font-weight: 700;
  color: #1B2024;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* ── mode gelap ── */
:global([data-tema='gelap']) .eq-mt-panggung,
:global([data-tema='gelap']) .eq-mt-bawah { background: #141F23; border-color: #223238; }
:global([data-tema='gelap']) .eq-mt-judul,
:global([data-tema='gelap']) .eq-mt-judul-kecil,
:global([data-tema='gelap']) .eq-mt-blok-judul,
:global([data-tema='gelap']) .eq-mt-sub,
:global([data-tema='gelap']) .eq-mt-isi strong,
:global([data-tema='gelap']) .eq-mt-lampiran strong,
:global([data-tema='gelap']) .eq-mt-nav strong,
:global([data-tema='gelap']) .eq-mt-pesan-kepala strong { color: #E8EFF2; }
:global([data-tema='gelap']) .eq-mt-ket,
:global([data-tema='gelap']) .eq-mt-teks,
:global([data-tema='gelap']) .eq-mt-bacaan,
:global([data-tema='gelap']) .eq-mt-hasil li,
:global([data-tema='gelap']) .eq-mt-pesan p { color: #C4CDD2; }
:global([data-tema='gelap']) .eq-mt-isi li,
:global([data-tema='gelap']) .eq-mt-lampiran a,
:global([data-tema='gelap']) .eq-mt-tautan,
:global([data-tema='gelap']) .eq-mt-nav,
:global([data-tema='gelap']) .eq-mt-pesan { border-color: #223238; }
:global([data-tema='gelap']) .eq-mt-pesan { background: #0F1A1E; }
:global([data-tema='gelap']) .eq-mt-catatan { background: #0F1A1E; border-color: #223238; color: #E8EFF2; }
:global([data-tema='gelap']) .eq-mt-tab,
:global([data-tema='gelap']) .eq-mt-jelajah { border-color: #223238; }
:global([data-tema='gelap']) .eq-mt-syarat { background: rgb(146 64 14 / .18); border-color: rgb(253 230 138 / .3); }
:global([data-tema='gelap']) .eq-mt-syarat strong { color: #FCD34D; }
:global([data-tema='gelap']) .eq-mt-syarat p { color: #FDE68A; }
</style>

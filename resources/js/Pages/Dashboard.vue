<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import IkonStat from '../Components/IkonStat.vue';
import KartuGrafik from '../Grafik/KartuGrafik.vue';
import Garis from '../Grafik/Garis.vue';
import Batang from '../Grafik/Batang.vue';
import Donat from '../Grafik/Donat.vue';
import Legenda from './Dasbor/Legenda.vue';

interface Enrollment {
  judul: string; deskripsi: string | null; sampul: string | null; kategori: string | null;
  nada: string | null; status: string; progress: number; modul: number; menit: number;
  belajar: string; detail: string;
}
interface NewsItem { judul: string; cuplikan: string; tanggal: string | null; url: string }
interface ModuleItem { nama: string; ket: string; nilai: number; total: number; warna: string; url: string; ikon: string }

const props = defineProps<{
  judul: string; subjudul: string; sapa: string; nama: string; hero: string | null; lanjut: string;
  enrollments: Enrollment[]; certificates: number; ringkas: { total: number; diikuti: number; selesai: number; berjalan: number; kemajuan: number; belum: number };
  news: NewsItem[]; modul: ModuleItem[]; kategori: Array<{ nama: string; jumlah: number; nada?: string }>;
  admin: { users: number; courses: number; procedures: number; certs: number } | null;
  hari: number; opsiHari: number[];
  grafik: {
    kegiatan: { label: string[]; modul: number[]; uji: number[] };
    kemajuan: Array<{ label: string; nilai: number; keadaan: string; url: string }>;
    status: Array<{ label: string; nilai: number; keadaan: string }>;
    nilai: Array<{ label: string; nilai: number; keadaan: string }>;
    sertifikat: Array<{ label: string; nilai: number }>;
    penyelesaian: Array<{ label: string; nilai: number; keadaan: string; peserta: number }> | null;
  };
}>();

/* ── grafik ──

   Rentang berpindah lewat URL, bukan di peramban: hasilnya dapat
   ditautkan, dan yang menempelkannya ke laporan menempelkan rentang
   yang sama pula. `preserveScroll` supaya halaman tidak melompat ke
   puncak tiap kali rentangnya diubah — grafiknya ada di tengah
   halaman, dan melompat berarti harus menggulir turun lagi untuk
   melihat hasil klik sendiri. */
function pilihHari(h: number) {
  router.get('/dashboard', { hari: h }, { preserveScroll: true, preserveState: true });
}

const g = computed(() => props.grafik);

/* Dua deret pada satu sumbu, dan keduanya memang sebanding: masing-masing
   menghitung BANYAKNYA kejadian per hari, bukan dua besaran berbeda
   satuan. */
const deretKegiatan = computed(() => [
  { nama: 'Modul diselesaikan', nilai: g.value.kegiatan?.modul ?? [], warna: '#16A34A' },
  { nama: 'Kuis & evaluasi',    nilai: g.value.kegiatan?.uji ?? [],   warna: '#C47000' },
]);

const adaKegiatan = computed(() =>
  [...(g.value.kegiatan?.modul ?? []), ...(g.value.kegiatan?.uji ?? [])].some((n) => n > 0));

const totalKegiatan = computed(() =>
  [...(g.value.kegiatan?.modul ?? []), ...(g.value.kegiatan?.uji ?? [])]
    .reduce((j, n) => j + n, 0));

const totalNilai = computed(() =>
  (g.value.nilai ?? []).reduce((j, b) => j + b.nilai, 0));

const totalSertifikat = computed(() =>
  (g.value.sertifikat ?? []).reduce((j, b) => j + b.nilai, 0));

const kpi: Array<{ label: string; value: () => number | string; hint: string; tone: string; ikon: string }> = [
  { label: 'Total Kursus', value: () => props.ringkas.total, hint: 'Semua kursus tersedia', tone: 'hijau', ikon: 'pustaka' },
  { label: 'Kursus Selesai', value: () => props.ringkas.selesai, hint: 'Kursus telah selesai', tone: 'biru', ikon: 'tuntas' },
  { label: 'Progress Belajar', value: () => `${props.ringkas.kemajuan}%`, hint: 'Rata-rata progress', tone: 'toska', ikon: 'laju' },
  { label: 'Sertifikat', value: () => props.certificates, hint: 'Sertifikat diperoleh', tone: 'kuning', ikon: 'medali' },
  { label: 'Belum Diikuti', value: () => props.ringkas.belum, hint: 'Menunggu untuk dimulai', tone: 'ungu', ikon: 'menunggu' },
];
</script>

<template>
  <Head title="Dashboard" />
  <div class="max-w-[1400px] mx-auto space-y-5">
    <div class="eq-kpi-baris">
      <article v-for="item in kpi" :key="item.label" class="eq-kpi"><span class="eq-kpi-ikon" :class="`t-${item.tone}`"><IkonStat :nama="item.ikon" :ukuran="18" /></span><span class="eq-kpi-isi"><span class="eq-kpi-label">{{ item.label }}</span><span class="eq-kpi-nilai">{{ item.value() }}</span><span class="eq-kpi-ket">{{ item.hint }}</span></span></article>
    </div>

    <div class="eq-kisi-utama">
      <section class="eq-panel"><div class="eq-panel-kepala"><h3>Kursus Saya</h3><Link href="/courses" class="eq-tautan">Lihat Semua →</Link></div>
        <div v-if="enrollments.length" class="eq-kursus-kisi"><article v-for="kursus in enrollments" :key="kursus.judul" class="eq-kursus"><div class="eq-kursus-gambar"><img v-if="kursus.sampul" :src="kursus.sampul" alt="" loading="lazy"><span class="eq-kursus-lencana"><i v-if="kursus.kategori" class="l-utama" :class="`k-${kursus.nada}`">{{ kursus.kategori.toUpperCase() }}</i><i v-if="kursus.status === 'ongoing'" class="l-ikut">DIIKUTI</i><i v-else-if="kursus.status === 'finished'" class="l-selesai">SELESAI</i></span></div><div class="eq-kursus-isi"><h4>{{ kursus.judul }}</h4><p>{{ kursus.deskripsi }}</p><div class="eq-kursus-meta"><span>{{ kursus.modul }} Modul</span><span>{{ kursus.menit }} Menit</span></div><div class="eq-kursus-maju"><span>Progress Anda</span><b>{{ kursus.progress }}%</b></div><div class="eq-bilah"><i :style="{ width: `${kursus.progress}%` }"></i></div><div class="eq-kursus-aksi"><Link :href="kursus.belajar" class="eq-btn-utama">▶ {{ kursus.progress > 0 ? 'Lanjut Belajar' : 'Mulai Belajar' }}</Link><Link :href="kursus.detail" class="eq-btn-lain">Detail</Link></div></div></article></div>
        <div v-else class="eq-kosong"><p><strong>Belum ada kursus yang diikuti.</strong></p><p>Mulai dari katalog kursus dan pilih yang paling dibutuhkan pekerjaan Anda.</p><Link href="/courses" class="eq-btn-utama">Jelajahi Kursus</Link></div>
      </section>

      <div class="eq-kolom-sisi"><!-- Menggantikan "Progress Mingguan" yang digambar tangan: tujuh
             batang div bertinggi `nilai * 18px`, tanpa sumbu, tanpa
             satuan, dan tanpa apa pun yang dapat disentuh. Satu modul
             menjadi batang 18px dan dua modul menjadi 36px — tidak ada
             yang menyatakan bahwa itu skalanya, dan tidak ada cara
             membaca angka selain yang dicetak di atas batangnya.

             Donat ini menjawab pertanyaan yang lebih dulu ditanyakan
             orang saat membuka dasbornya: dari seluruh kursus yang ada,
             sudah di mana saya. Kegiatan hariannya pindah ke grafik
             garis di bawah, lengkap dengan rentang yang dapat dipilih. -->
        <KartuGrafik judul="Status kursus saya"
                     catatan="Seluruh kursus yang tersedia, termasuk yang belum diikuti."
                     :angka="`${ringkas.diikuti ?? 0} diikuti`">
          <Donat :bagian="g.status ?? []" :tengah="String(ringkas.total ?? 0)" tengah-label="kursus" />

          <template #tabel>
            <table>
              <thead><tr><th>Keadaan</th><th>Kursus</th></tr></thead>
              <tbody>
                <tr v-for="b in g.status ?? []" :key="b.label">
                  <td>{{ b.label }}</td><td class="num">{{ b.nilai }}</td>
                </tr>
              </tbody>
            </table>
          </template>
        </KartuGrafik>
        <section class="eq-panel"><div class="eq-panel-kepala"><h3>Pengumuman</h3><Link href="/news" class="eq-tautan">Lihat Semua →</Link></div><ul v-if="news.length" class="eq-warta"><li v-for="item in news" :key="item.url"><Link :href="item.url"><span class="eq-warta-teks"><strong>{{ item.judul }}</strong><small>{{ item.cuplikan }}</small></span><time>{{ item.tanggal }}</time></Link></li></ul><div v-else class="eq-kosong eq-kosong-kecil"><p>Belum ada pengumuman.</p></div></section></div>
    </div>

    <!-- ═══ Grafik pembelajaran ═══

         DIKELOMPOKKAN SESUDAH kartu kursus, bukan di antaranya: yang
         membuka dasbor ini pertama-tama hendak melanjutkan kursusnya,
         dan grafik yang menyela di atas tombol "Lanjut Belajar"
         menjadikan pekerjaan utamanya harus dicari.

         SELURUHNYA TENTANG ORANG INI. Angka seluruh situs punya
         rumahnya sendiri di /dasbor; satu-satunya kartu yang melihat
         orang lain adalah yang terakhir, dan ia hanya muncul bagi
         pelatih dan administrator. -->
    <section class="eq-panel">
      <div class="eq-panel-kepala">
        <h3>Kegiatan belajar</h3>

        <!-- Rentang: pilihan tertutup, berpindah lewat URL supaya
             hasilnya dapat ditautkan. Bentuknya sama persis dengan
             pemilih rentang di /dasbor — dua bentuk berbeda untuk satu
             kendali yang sama membuat orang mengira keduanya bekerja
             berbeda. -->
        <div class="flex items-center gap-1.5" role="group" aria-label="Rentang grafik">
          <button v-for="h in opsiHari" :key="h" type="button"
                  class="rounded-lg px-2.5 py-1 text-[11.5px] font-semibold border transition num"
                  :class="h === hari
                    ? 'bg-cam-ink text-white border-transparent'
                    : 'bg-white text-stone-600 border-stone-200 hover:border-stone-300'"
                  :aria-pressed="h === hari" @click="pilihHari(h)">{{ h }} hari</button>
        </div>
      </div>

      <!-- `items-start`: kartu setinggi ISINYA sendiri. Tanpa ini, kartu
           batang berbaris tunggal direntangkan setinggi kartu garis di
           sebelahnya, dan kolong kosong 150px yang terbentuk terbaca
           sebagai data yang gagal dimuat. -->
      <div class="grid gap-4 lg:grid-cols-2 items-start mt-3">
        <KartuGrafik judul="Modul dan uji yang dikerjakan"
                     catatan="Modul yang dituntaskan berdampingan dengan kuis dan evaluasi SOP yang dikerjakan — membaca saja bukan belajar, dan mengulang kuis tanpa modul baru bukan pula."
                     :angka="`${totalKegiatan} kegiatan`" :tinggi="200">
          <template v-if="adaKegiatan">
            <Garis :label="g.kegiatan?.label ?? []" :deret="deretKegiatan" satuan="kegiatan" />
            <Legenda :deret="deretKegiatan" />
          </template>

          <div v-else class="h-full min-h-[176px] grid place-items-center text-center px-4">
            <p class="text-[12px] text-stone-400">
              Belum ada modul maupun uji yang dikerjakan pada rentang ini.
            </p>
          </div>

          <template #tabel>
            <table>
              <thead><tr><th>Tanggal</th><th>Modul</th><th>Kuis &amp; evaluasi</th></tr></thead>
              <tbody>
                <tr v-for="(l, i) in g.kegiatan?.label ?? []" :key="l">
                  <td>{{ l }}</td>
                  <td class="num">{{ g.kegiatan.modul[i] }}</td>
                  <td class="num">{{ g.kegiatan.uji[i] }}</td>
                </tr>
              </tbody>
            </table>
          </template>
        </KartuGrafik>

        <KartuGrafik judul="Kemajuan tiap kursus"
                     catatan="Dari yang paling tertinggal — itu yang perlu dikerjakan lebih dulu. Klik barisnya untuk langsung melanjutkan."
                     :angka="`${ringkas.kemajuan}% rata-rata`">
          <Batang :baris="g.kemajuan ?? []" satuan="%" satuan-tampak
                  :maks-tetap="100" apa-adanya />

          <template #tabel>
            <table>
              <thead><tr><th>Kursus</th><th>Kemajuan</th></tr></thead>
              <tbody>
                <tr v-for="b in g.kemajuan ?? []" :key="b.label">
                  <td>{{ b.label }}</td><td class="num">{{ b.nilai }}%</td>
                </tr>
              </tbody>
            </table>
          </template>
        </KartuGrafik>

        <KartuGrafik judul="Sebaran nilai kuis dan evaluasi SOP"
                     catatan="Seluruh percobaan, bukan yang terbaik saja: yang terbaik menjawab apakah sudah lulus, sebaran menjawab seberapa jauh dari lulus."
                     :angka="`${totalNilai} percobaan`">
          <Batang :baris="g.nilai ?? []" satuan="percobaan" apa-adanya />

          <template #tabel>
            <table>
              <thead><tr><th>Pita nilai</th><th>Percobaan</th></tr></thead>
              <tbody>
                <tr v-for="b in g.nilai ?? []" :key="b.label">
                  <td>{{ b.label }}</td><td class="num">{{ b.nilai }}</td>
                </tr>
              </tbody>
            </table>
          </template>
        </KartuGrafik>

        <KartuGrafik judul="Sertifikat terbit per bulan"
                     catatan="Enam bulan ke belakang, menurut tanggal terbitnya."
                     :angka="`${totalSertifikat} sertifikat`">
          <Batang :baris="g.sertifikat ?? []" satuan="sertifikat" apa-adanya />

          <template #tabel>
            <table>
              <thead><tr><th>Bulan</th><th>Sertifikat</th></tr></thead>
              <tbody>
                <tr v-for="b in g.sertifikat ?? []" :key="b.label">
                  <td>{{ b.label }}</td><td class="num">{{ b.nilai }}</td>
                </tr>
              </tbody>
            </table>
          </template>
        </KartuGrafik>

        <!-- SELURUH PESERTA, dan karena itu hanya bagi yang memang
             bertugas melihat orang lain. Kursus tanpa satu pun
             pendaftar sengaja tidak muncul: "0% selesai" di sana
             menuduh kursusnya, padahal yang terjadi tidak ada yang
             mendaftar. -->
        <KartuGrafik v-if="grafik.penyelesaian"
                     judul="Penyelesaian kursus seluruh peserta"
                     catatan="Persen peserta terdaftar yang telah menyelesaikan, dari yang terendah. Kursus tanpa pendaftar tidak ditampilkan."
                     :angka="`${grafik.penyelesaian.length} kursus`">
          <Batang :baris="grafik.penyelesaian" satuan="%" satuan-tampak
                  :maks-tetap="100" apa-adanya />

          <template #tabel>
            <table>
              <thead><tr><th>Kursus</th><th>Peserta</th><th>Selesai</th></tr></thead>
              <tbody>
                <tr v-for="b in grafik.penyelesaian" :key="b.label">
                  <td>{{ b.label }}</td>
                  <td class="num">{{ b.peserta }}</td>
                  <td class="num">{{ b.nilai }}%</td>
                </tr>
              </tbody>
            </table>
          </template>
        </KartuGrafik>
      </div>
    </section>

    <section v-if="kategori.length" class="eq-panel"><div class="eq-panel-kepala"><h3>Kategori Kursus</h3><Link href="/courses" class="eq-tautan">Lihat Semua →</Link></div><div class="eq-kategori"><Link v-for="item in kategori" :key="item.nama" :href="`/courses?kategori=${encodeURIComponent(item.nama)}`"><span class="eq-kategori-ikon" :class="`t-${item.nada ?? 'biru'}`"><IkonStat nama="kursus" :ukuran="18" /></span><span><strong>{{ item.nama }}</strong><small>{{ item.jumlah }} Kursus</small></span></Link></div></section>
    <section class="eq-panel"><div class="eq-panel-kepala"><h3>Modul Lainnya</h3><span class="eq-panel-ket">Angka yang ditampilkan adalah yang butuh perhatian.</span></div><div class="eq-modul"><Link v-for="item in modul" :key="item.nama" :href="item.url"><span class="eq-modul-atas"><span class="eq-modul-nilai" :style="{ color: item.warna }">{{ item.nilai }}</span><span class="eq-modul-ikon" :style="{ background: `${item.warna}18`, color: item.warna }"><IkonStat :nama="item.ikon" :ukuran="18" /></span></span><strong>{{ item.nama }}</strong><small>{{ item.ket }}<template v-if="item.total > 0"> · dari {{ item.total }}</template></small></Link></div></section>
    <section v-if="admin" class="eq-panel"><div class="eq-panel-kepala"><h3>Ringkasan Sistem</h3></div><div class="eq-kategori"><div v-for="item in [['Pengguna', admin.users], ['Kursus', admin.courses], ['Prosedur', admin.procedures], ['Sertifikat terbit', admin.certs]]" :key="item[0]" class="eq-admin-angka"><span class="eq-kpi-nilai">{{ item[1] }}</span><small>{{ item[0] }}</small></div></div></section>
  </div>
</template>

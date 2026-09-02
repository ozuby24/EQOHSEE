<script setup lang="ts">
/**
 * Kerangka halaman Inertia.
 *
 * Menggambar bilah samping dan bilah atas yang sama dengan
 * layouts/app.blade.php, memakai kelas CSS yang sama pula (eq-*, dari
 * partials/eq-visual.blade.php). Gayanya sengaja tidak ditulis ulang di
 * sini: dua salinan aturan warna yang panjang pasti berbeda isinya cepat
 * atau lambat, dan bedanya baru ketahuan saat orang membandingkan dua
 * halaman berdampingan.
 */
import { computed, onMounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import type { PropBersama } from '../types';

/*
  `<Link>` HANYA untuk tujuan yang benar-benar dirender Inertia.

  `<a href>` selalu memicu navigasi peramban penuh — Inertia tidak
  mencegat klik anchor apa pun kecuali lewat komponennya sendiri. Ini
  pernah tertinggal: sidebar Vue ini menyalin markup sidebar Blade apa
  adanya, termasuk `<a href>`-nya, sehingga berpindah antar DUA halaman
  yang sama-sama Inertia pun tetap memuat ulang penuh — persis cacat
  yang seharusnya sudah tidak ada begitu keduanya sama-sama Vue.

  Dugaan bahwa <Link> otomatis jatuh ke navigasi penuh ketika tanggapannya
  bukan Inertia itu KELIRU, dan keliru dengan cara yang mahal: ia mengirim
  permintaan ber-header X-Inertia, menerima HTML utuh, lalu menampilkan
  modal galat dan tetap diam. Sempat terjadi persis begitu — seluruh menu
  memakai <Link>, dan dari halaman Vue tidak satu pun menu bisa diklik.

  Karena itu server menandai tiap tautan (`inertia: true/false`, dari
  App\Support\RuteInertia) dan komponen di bawah memilih bentuk tautannya
  sesuai tanda itu.
*/
const tautan = (inertia: boolean) => (inertia ? Link : 'a');

const halaman = usePage<PropBersama>();

const judul      = computed(() => (halaman.props as Record<string, unknown>).judul as string ?? 'Dashboard');
const subjudul   = computed(() => (halaman.props as Record<string, unknown>).subjudul as string | undefined);
const pengguna   = computed(() => halaman.props.pengguna);
const menu       = computed(() => halaman.props.menu);
const kilat      = computed(() => halaman.props.kilat);
const pengumuman = computed(() => halaman.props.pengumuman ?? 0);

const lacisTerbuka = ref(false);
const sempit       = ref(false);

/**
 * Panel pemindah modul.
 *
 * Menggantikan kisi 22 ikon telanjang yang dulu duduk di atas menu.
 * Kisi itu padat dan tidak dapat dibaca: ikon tanpa label memaksa orang
 * menghafal posisi, dan yang tidak hafal menekan satu per satu sampai
 * ketemu. Sekarang yang terlihat hanya NAMA modul yang sedang dibuka;
 * daftar lengkapnya — beserta labelnya — muncul saat diminta.
 */
const modulTerbuka = ref(false);

/**
 * Sapaan menurut jam setempat.
 *
 * Dihitung di peramban, bukan di server. Server berjalan pada UTC,
 * sedangkan yang membaca sapaan ini duduk di lokasi tambang: sapaan
 * "Selamat malam" pada pukul sembilan pagi terbaca sebagai jam aplikasi
 * yang salah, bukan sebagai basa-basi yang keliru.
 */
const sapaan = computed(() => {
  const j = new Date().getHours();

  if (j < 11) return 'Selamat pagi';
  if (j < 15) return 'Selamat siang';
  if (j < 19) return 'Selamat sore';

  return 'Selamat malam';
});

/**
 * Nama depan saja — sapaan bernama lengkap berbunyi seperti surat resmi.
 *
 * GELARNYA DILEWATI. Memenggal pada spasi pertama terlihat benar sampai
 * ada nama yang berawalan gelar: "Miss Fleta Lehner" menghasilkan
 * sapaan "Selamat siang, Miss" — terbaca sebagai aplikasi yang tidak
 * tahu siapa yang sedang memakainya. Daftarnya sengaja pendek dan hanya
 * berisi gelar yang benar-benar muncul di depan nama; kata yang tidak
 * dikenali diperlakukan sebagai nama, bukan dibuang.
 */
const GELAR = ['mr', 'mrs', 'ms', 'miss', 'dr', 'drs', 'ir', 'h', 'hj', 'prof'];

const namaDepan = computed(() => {
  const kata = (pengguna.value?.nama ?? '').trim().split(/\s+/).filter(Boolean);

  for (const k of kata) {
    if (!GELAR.includes(k.toLowerCase().replace(/\.$/, ''))) return k;
  }

  return kata[0] || 'Anda';
});

const cari = ref('');

/**
 * Pencarian menuju daftar pekerja, sebab hanya itu yang benar-benar
 * dapat dicari hari ini — nama, NIK, dan jabatan, lewat `q` pada
 * /miners. Kotak cari yang tidak menuju ke mana-mana lebih buruk
 * daripada tidak ada: ia menjanjikan sesuatu lalu diam.
 */
function kirimCari() {
  const q = cari.value.trim();

  router.get('/miners', q ? { q } : {}, { preserveState: false });
}

onMounted(() => {
  try {
    sempit.value = localStorage.getItem('eq-sisi-sempit') === '1';
  } catch { /* mode privat */ }

  if (sempit.value) document.body.classList.add('eq-sempit');
});

function lipat() {
  sempit.value = !sempit.value;
  document.body.classList.toggle('eq-sempit', sempit.value);

  try {
    localStorage.setItem('eq-sisi-sempit', sempit.value ? '1' : '0');
  } catch { /* mode privat */ }
}

/**
 * Sakelar tema.
 *
 * Tampilan berubah lebih dulu, pilihannya dikirim ke server sesudahnya —
 * menunggu jawaban server membuat tombolnya terasa macet pada sambungan
 * lapangan yang lambat. Kalau pengirimannya gagal, yang hilang hanya
 * keawetan pilihan antar perangkat, bukan sakelarnya sendiri.
 */
function gantiTema() {
  const akar = document.documentElement;
  const kini = akar.getAttribute('data-tema')
    ?? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'gelap' : 'terang');
  const baru = kini === 'gelap' ? 'terang' : 'gelap';

  akar.setAttribute('data-tema', baru);
  try { localStorage.setItem('eqTema', baru); } catch { /* mode privat */ }

  router.post('/personalia/tema', { tema: baru }, {
    preserveScroll: true,
    preserveState: true,
    only: [],
  });
}

function keluar() {
  router.post('/logout');
}
</script>

<template>
  <div class="min-h-screen flex">

    <!-- ═══════════ BILAH SAMPING ═══════════ -->
    <div v-show="lacisTerbuka" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-30 lg:hidden"
         @click="lacisTerbuka = false"></div>

    <aside id="eqSidebar"
           class="brand-gradient fixed lg:static inset-y-0 left-0 z-40 w-[248px] shrink-0 flex flex-col
                  text-white/70 transition-transform duration-300"
           :class="lacisTerbuka ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">

      <a href="/dashboard" class="eq-merek">
        <!-- Varian 128 px, bukan berkas 512 px seberat 217 KB untuk kotak 40 px. -->
        <img src="/brand/eqohsee-mark-128.png" alt="" width="40" height="40">
        <span>
          <strong>E<em>Q</em>OHSEE</strong>
          <small>Safe Today · Sustainable Tomorrow</small>
        </span>
      </a>

      <!-- ── Pemindah modul ──

           Dulu di sini duduk kisi 22 ikon telanjang. Ikon tanpa label
           memaksa orang menghafal posisinya, dan yang belum hafal
           menekan satu per satu sampai ketemu — dua puluh dua kotak
           yang seluruhnya terlihat sama.

           Sekarang yang terlihat hanya modul yang sedang dibuka.
           Daftar lengkapnya muncul saat diminta, dengan LABELNYA, jadi
           tidak ada yang perlu dihafal. -->
      <div class="px-3 pt-3.5">
        <button type="button" class="eq-modul-pilih" :aria-expanded="modulTerbuka"
                aria-label="Pindah modul" @click="modulTerbuka = !modulTerbuka">
          <span class="eq-modul-nama">{{ menu.label }}</span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
               :style="modulTerbuka ? 'transform:rotate(180deg)' : ''">
            <path d="m6 9 6 6 6-6"/>
          </svg>
        </button>

        <div v-if="modulTerbuka" class="eq-modul-daftar">
          <component :is="tautan(m.inertia)"
             v-for="m in menu.modul" :key="m.kunci" :href="m.url"
             class="eq-modul-butir" :class="m.aktif ? 'eq-modul-aktif' : ''">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path :d="m.ikon"/>
            </svg>
            <span>{{ m.label }}</span>
            <span v-if="m.lencana" class="eq-modul-titik"></span>
          </component>
        </div>
      </div>

      <nav class="min-h-0 flex-1 overflow-y-auto px-3 py-3">
        <template v-for="(g, i) in menu.grup" :key="i">
          <p v-if="g.nama" class="px-3 mt-3 mb-1 text-[9.5px] font-semibold uppercase
                                  tracking-[0.12em] text-white/55">{{ g.nama }}</p>
          <div class="space-y-0.5">
            <component :is="tautan(b.inertia)"
               v-for="b in g.butir" :key="b.url" :href="b.url"
               class="relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-[12.5px]
                      font-semibold hover:bg-white/5 hover:text-white transition"
               :class="b.aktif ? 'nav-active' : ''">
              <span class="nav-accent absolute left-0 top-1/2 -translate-y-1/2 w-[3px] h-5
                           rounded-r-full bg-cam-lime-light opacity-0"></span>
              <svg class="eq-navico" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                   stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path :d="b.ikon"/>
              </svg>
              <span>{{ b.label }}</span>
              <span v-if="b.lencana" class="ml-auto text-[10px] font-bold leading-none px-1.5 py-1
                                            rounded-full bg-cam-lime-light text-cam-ink">
                {{ b.lencana > 99 ? '99+' : b.lencana }}
              </span>
            </component>
          </div>
        </template>
      </nav>

      <!-- ── Kaki bilah samping ──

           Chip pengguna turun ke sini dari kepala halaman. Kepala
           halaman sekarang menyapa orangnya dengan namanya; menaruh
           nama yang sama sekali lagi di sebelah kanan membuatnya
           tercetak dua kali pada satu baris pandang.

           Kartu "Butuh Bantuan?" yang dulu di sini dipindah menjadi
           satu tombol saja. Kartu setinggi 96px yang isinya tidak
           pernah berubah memakan ruang yang dibutuhkan menu, dan menu
           yang terpotong membuat butir terbawahnya tidak pernah
           ditemukan. -->
      <div class="eq-sisi-kaki">
        <Link href="/bantuan" class="eq-bantuan-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9 9 0 0 1-3.2-.6L3.5 21l1.7-4.6A8.2 8.2 0 0 1 4 11.5a8.4 8.4 0 0 1 9-8.4 8.4 8.4 0 0 1 8 8.4Z"/>
          </svg>
          Butuh bantuan?
        </Link>

        <div v-if="pengguna" class="eq-sisi-akun">
          <a href="/personalia" class="eq-sisi-akun-tautan" title="Data diri">
            <img v-if="pengguna.avatar" class="eq-sisi-avatar eq-sisi-avatar-foto"
                 :src="pengguna.avatar" alt="" width="34" height="34">
            <span v-else class="eq-sisi-avatar">{{ pengguna.nama.charAt(0).toUpperCase() }}</span>
            <span class="eq-sisi-akun-teks">
              <strong>{{ pengguna.nama }}</strong>
              <small>{{ pengguna.peran }}</small>
            </span>
          </a>

          <button type="button" class="eq-sisi-keluar" aria-label="Keluar" @click="keluar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M15 17l5-5-5-5M20 12H9M12 19H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h6"/>
            </svg>
          </button>
        </div>

        <div class="eq-sisi-bawah">
          <small>&copy; {{ new Date().getFullYear() }} EQOHSEE</small>
          <button type="button" class="eq-lipat" aria-label="Lipat bilah samping" @click="lipat">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M13 7l-5 5 5 5M18 7l-5 5 5 5"/>
            </svg>
          </button>
        </div>
      </div>
    </aside>

    <!-- ═══════════ ISI ═══════════ -->
    <div class="flex-1 flex flex-col min-w-0">
      <header class="eq-topbar">
        <button class="eq-menu-btn" aria-label="Buka menu" @click="lacisTerbuka = !lacisTerbuka">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>

        <!-- ── Sapaan, bukan judul halaman ──

             Judul halaman TIDAK lagi dicetak di sini. Tiap halaman
             sudah mencetak judulnya sendiri di badan halaman, dan
             keduanya bersebelahan membuat kalimat yang sama muncul dua
             kali dengan jarak dua sentimeter — terlihat pada
             /miners/dasbor: "Miners — Ringkasan" tercetak di kepala
             halaman dan langsung diulang di bawahnya. -->
        <div class="eq-sapa min-w-0">
          <small>{{ sapaan }},</small>
          <strong>{{ namaDepan }} <span aria-hidden="true">&#128075;</span></strong>
        </div>

        <!-- Kotak cari menuju daftar pekerja: nama, NIK, jabatan.
             Hanya itu yang benar-benar dapat dicari hari ini, dan
             menjanjikan lebih dari itu berarti kotak yang diam. -->
        <form class="eq-cari" @submit.prevent="kirimCari">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" aria-hidden="true">
            <circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/>
          </svg>
          <input v-model="cari" type="search" aria-label="Cari pekerja"
                 placeholder="Cari pekerja menurut nama, NIK, atau jabatan…">
        </form>

        <div class="eq-topbar-aksi">
          <button type="button" class="eq-bulat eq-tema-btn" title="Tema terang / gelap"
                  aria-label="Ganti tema terang atau gelap" @click="gantiTema">
            <svg class="eq-ikon-terang" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.9" stroke-linecap="round" aria-hidden="true">
              <circle cx="12" cy="12" r="4.2"/>
              <path d="M12 2.5v2.2M12 19.3v2.2M4.2 4.2l1.6 1.6M18.2 18.2l1.6 1.6M2.5 12h2.2M19.3 12h2.2M4.2 19.8l1.6-1.6M18.2 5.8l1.6-1.6"/>
            </svg>
            <svg class="eq-ikon-gelap" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M20.5 14.3A8.6 8.6 0 0 1 9.7 3.5a8.6 8.6 0 1 0 10.8 10.8Z"/>
            </svg>
          </button>

          <div class="eq-lonceng">
            <a href="/news" class="eq-bulat" aria-label="Pengumuman">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                   stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 3.2a5.3 5.3 0 0 0-5.3 5.3v3.7l-1.9 3.1h14.4l-1.9-3.1V8.5A5.3 5.3 0 0 0 12 3.2Z"/>
                <path d="M9.9 18.4a2.2 2.2 0 0 0 4.2 0"/>
              </svg>
              <span v-if="pengumuman > 0" class="eq-lonceng-titik">
                {{ pengumuman > 9 ? '9+' : pengumuman }}
              </span>
            </a>
          </div>

          <!-- Perusahaan yang sedang dilihat. Bukan pemilih: lingkup
               perusahaan ditentukan akun, bukan dipilih di layar, dan
               tombol yang terlihat dapat ditekan tetapi tidak mengubah
               apa pun lebih membingungkan daripada label biasa.

               Pengguna lintas perusahaan berbunyi "Semua perusahaan",
               bukan kosong — kotak kosong terbaca sebagai data hilang. -->
          <span v-if="pengguna" class="eq-perusahaan" :title="pengguna.perusahaan ?? 'Semua perusahaan'">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M4 20V7.5L12 4l8 3.5V20M9 20v-4.5h6V20M8 10.5h.01M12 10.5h.01M16 10.5h.01"/>
            </svg>
            <span>{{ pengguna.perusahaan ?? 'Semua perusahaan' }}</span>
          </span>
        </div>
      </header>

      <!-- Kelas tema datang dari modulnya, bukan dari halamannya.
           Modul yang menyatakan `tema` di Menu::all() berganti palet
           seluruhnya — termasuk halaman yang belum ditulis. Dipasang di
           <main>, bukan di pembungkus terluar, supaya bilah samping dan
           kepala halaman tetap satu rupa di seluruh aplikasi: yang
           berganti isinya, bukan kerangkanya. -->
      <main class="flex-1 p-4 lg:p-6" :class="menu.tema ? `tema-${menu.tema}` : null">
        <div v-if="kilat.sukses" class="max-w-[1400px] mx-auto mb-5">
          <div class="rounded-xl px-4 py-3 text-[12.5px] font-semibold flex items-center gap-2.5"
               style="background:var(--eq-aksen-tipis,rgba(14,116,126,.12));color:var(--eq-aksen,#F57C00)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                 stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 shrink-0" aria-hidden="true">
              <path d="m5 12.5 4.5 4.5L19 7.5"/>
            </svg>
            {{ kilat.sukses }}
          </div>
        </div>

        <slot />
      </main>
    </div>
  </div>
</template>

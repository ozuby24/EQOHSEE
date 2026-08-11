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
        <img src="/brand/eqohsee-mark-white.svg" alt="" width="38" height="42">
        <span>
          <strong>E<em>Q</em>OHSEE</strong>
          <small>Safety is Our Priority</small>
        </span>
      </a>

      <!-- Pemilih modul -->
      <div class="px-3 pt-3.5">
        <div class="glass rounded-xl p-1 grid grid-cols-3 gap-1">
          <component :is="tautan(m.inertia)"
             v-for="m in menu.modul" :key="m.kunci" :href="m.url" :title="m.label"
             class="grid place-items-center py-2 rounded-lg transition"
             :class="m.aktif ? 'lime-gradient text-white shadow-glow'
                             : 'text-white/40 hover:text-white hover:bg-white/5'">
            <svg class="w-[17px] h-[17px]" fill="none" stroke="currentColor" stroke-width="2.2"
                 viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" :d="m.ikon"/>
            </svg>
          </component>
        </div>
        <div class="mt-2.5 px-1 text-[10px] font-bold uppercase tracking-[0.18em] text-cam-lime-light">
          {{ menu.label }}
        </div>
      </div>

      <nav class="flex-1 overflow-y-auto px-3 py-3">
        <template v-for="(g, i) in menu.grup" :key="i">
          <p v-if="g.nama" class="px-3 mt-3 mb-1 text-[9.5px] font-semibold uppercase
                                  tracking-[0.12em] text-white/20">{{ g.nama }}</p>
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
            </component>
          </div>
        </template>
      </nav>

      <div class="eq-sisi-kaki">
        <div class="eq-bantuan">
          <span class="eq-bantuan-ikon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 3.5a8.5 8.5 0 0 1 8.5 8.5v4.8a2.7 2.7 0 0 1-2.7 2.7h-1.3v-7.2h4M3.5 16.8V12A8.5 8.5 0 0 1 12 3.5"/>
              <path d="M3.5 12.3h3.9v7.2H6.2a2.7 2.7 0 0 1-2.7-2.7Z"/>
            </svg>
          </span>
          <span class="eq-bantuan-teks">
            <strong>Butuh Bantuan?</strong>
            <small>Kami siap membantu Anda kapan saja.</small>
          </span>
        </div>
        <a href="/news" class="eq-bantuan-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9 9 0 0 1-3.2-.6L3.5 21l1.7-4.6A8.2 8.2 0 0 1 4 11.5a8.4 8.4 0 0 1 9-8.4 8.4 8.4 0 0 1 8 8.4Z"/>
          </svg>
          Hubungi Kami
        </a>

        <div class="eq-sisi-bawah">
          <small>© {{ new Date().getFullYear() }} EQOHSEE<br>All rights reserved.</small>
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

        <!-- Judul datang dari server, bukan dari slot bernama. Tata letak
             Inertia dipasang otomatis di inertia.ts dan halaman masuk
             sebagai slot bawaan, sehingga slot bernama tidak menembus ke
             sini. Cara ini juga sejalan dengan @yield('subjudul') pada
             sisi Blade: judul halaman ditentukan controller. -->
        <div class="eq-judul min-w-0 flex-1">
          <h1>{{ judul }}</h1>
          <p v-if="subjudul">{{ subjudul }}</p>
        </div>

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

          <a v-if="pengguna" href="/personalia" class="eq-profil" title="Data diri">
            <img v-if="pengguna.avatar" class="eq-avatar eq-avatar-foto" :src="pengguna.avatar"
                 alt="" width="38" height="38">
            <span v-else class="eq-avatar">{{ pengguna.nama.charAt(0).toUpperCase() }}</span>
            <span class="eq-profil-teks">
              <strong>{{ pengguna.nama }}</strong>
              <small>{{ pengguna.peran }}</small>
            </span>
          </a>

          <button v-if="pengguna" class="eq-keluar" aria-label="Keluar" @click="keluar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M15 17l5-5-5-5M20 12H9M12 19H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h6"/>
            </svg>
            <span class="hidden sm:inline">Keluar</span>
          </button>
        </div>
      </header>

      <main class="flex-1 p-4 lg:p-6">
        <div v-if="kilat.sukses" class="max-w-[1400px] mx-auto mb-5">
          <div class="rounded-xl px-4 py-3 text-[12.5px] font-semibold flex items-center gap-2.5"
               style="background:var(--eq-aksen-tipis,rgba(14,116,126,.12));color:var(--eq-aksen,#0E747E)">
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

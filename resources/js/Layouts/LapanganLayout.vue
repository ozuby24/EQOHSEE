<script setup lang="ts">
import '../../css/lapangan.css';
import { computed, onBeforeUnmount, onMounted, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import IkonLapangan from '../Components/IkonLapangan.vue';
import { jaringan, pasangSinkron } from '../lapangan/kotakKeluar';
import { hangatkan, pasangPekerja } from '../lapangan/pekerja';
import { kabar, kabarKini, tutupKabar } from '../lapangan/kabar';

/**
 * Cangkang mode lapangan: satu kolom selebar ponsel, tab bawah lima
 * pintu, kabar singkat, dan pengirim otomatis kiriman tertunda.
 *
 * Tab bawah disembunyikan pada layar yang punya aksi utamanya sendiri di
 * sepertiga bawah (lapor, detail laporan, P2H) — dua bilah bertumpuk di
 * bawah ibu jari adalah dua kesempatan menekan yang salah.
 */
const halaman = usePage<any>();

const TANPA_TAB = ['Lapangan/Lapor', 'Lapangan/Laporan', 'Lapangan/P2h'];
const PUTIH = ['Lapangan/Lapor', 'Lapangan/Laporan'];

const komponen = computed(() => halaman.component as string);
const bertab = computed(() => !TANPA_TAB.includes(komponen.value));
const lencana = computed(() => Number(halaman.props?.lencana?.tugas ?? 0));
const pengguna = computed(() => halaman.props?.pengguna?.id as number | undefined);

const aktif = computed(() => ({
  beranda: komponen.value === 'Lapangan/Beranda',
  modul: ['Lapangan/Modul', 'Lapangan/Izin', 'Lapangan/Sertifikat', 'Lapangan/P2hDaftar'].includes(komponen.value),
  tugas: komponen.value === 'Lapangan/Tugas',
  profil: komponen.value === 'Lapangan/Profil',
}));

/* Pesan kilat dari server — dibaca tiap kali halaman berganti. */
watch(() => halaman.props?.kilat, (k: any) => {
  if (k?.sukses) kabar(k.sukses);
  else if (k?.galat) kabar(k.galat, true);
}, { immediate: true });

let lepas: Array<() => void> = [];

onMounted(() => {
  void pasangPekerja().then(() => hangatkan(halaman.version ?? null));

  if (pengguna.value) {
    pasangSinkron(pengguna.value, (n) => {
      kabar(n === 1 ? '1 kiriman tertunda sudah terkirim.' : `${n} kiriman tertunda sudah terkirim.`);
      router.reload();
    });
  }

  lepas = [
    router.on('networkError', () => {
      kabar(jaringan.daring ? 'Sambungan terputus. Coba lagi sebentar.' : 'Luring — layar ini belum tersimpan di perangkat.', true);
    }),
  ];
});

onBeforeUnmount(() => lepas.forEach((f) => f()));
</script>

<template>
  <Head>
    <meta name="theme-color" content="#0B1117">
  </Head>

  <div class="lp">
    <div class="lp-kolom" :class="{ 'ber-tab': bertab, 'ber-aksi': !bertab, putih: PUTIH.includes(komponen) }">
      <slot />
    </div>

    <div v-if="kabarKini.pesan" :key="kabarKini.n" class="lp-kabar lp-muncul" :class="{ galat: kabarKini.galat, 'atas-aksi': !bertab }"
         :role="kabarKini.galat ? 'alert' : 'status'">
      <span>{{ kabarKini.pesan }}</span>
      <button type="button" aria-label="Tutup pesan" @click="tutupKabar">✕</button>
    </div>

    <nav v-if="bertab" class="lp-tab" aria-label="Navigasi mode lapangan">
      <Link href="/lapangan" :aria-current="aktif.beranda ? 'page' : undefined">
        <IkonLapangan nama="beranda" :tebal="aktif.beranda ? 2 : 1.8" />Beranda
      </Link>
      <Link href="/lapangan/modul" :aria-current="aktif.modul ? 'page' : undefined">
        <IkonLapangan nama="modul" :tebal="aktif.modul ? 2 : 1.8" />Modul
      </Link>
      <Link href="/lapangan/lapor" class="lapor">
        <span class="lp-tab-lapor"><IkonLapangan nama="bahaya" :tebal="2.1" /></span>Lapor
      </Link>
      <Link href="/lapangan/tugas" :aria-current="aktif.tugas ? 'page' : undefined">
        <span class="lp-tab-ikon">
          <IkonLapangan nama="tugas" :tebal="aktif.tugas ? 2 : 1.8" />
          <span v-if="lencana + jaringan.gagal > 0" class="lp-lencana">{{ lencana + jaringan.gagal > 99 ? '99+' : lencana + jaringan.gagal }}</span>
        </span>Tugas
      </Link>
      <Link href="/lapangan/profil" :aria-current="aktif.profil ? 'page' : undefined">
        <IkonLapangan nama="profil" :tebal="aktif.profil ? 2 : 1.8" />Profil
      </Link>
    </nav>
  </div>
</template>

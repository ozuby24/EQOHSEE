<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import LapanganLayout from '../../Layouts/LapanganLayout.vue';
import IkonLapangan from '../../Components/IkonLapangan.vue';
import BilahLuring from '../../Components/BilahLuring.vue';
import { jaringan } from '../../lapangan/kotakKeluar';
import { bersihkanSimpanan } from '../../lapangan/pekerja';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';

defineOptions({ layout: LapanganLayout });

const p = defineProps<{
  saya: { nama: string; inisial: string; jabatan: string | null; perusahaan: string | null; email: string; nik: string | null; departemen: string | null };
  tautan: { lengkap: string; akun: string | null; hapusAkun: string | null; privasi: string; keluar: string; sertifikat: string; izin: string; p2h: string };
}>();

/* Di dalam aplikasi Android, jembatan flutter_inappwebview tersedia dan
   pengaturan perangkat (kunci sidik jari, izin lokasi) dibuka dari sini.
   Di peramban biasa barisnya tidak ada sama sekali. */
type Jembatan = { callHandler: (nama: string, ...arg: unknown[]) => Promise<any> };
const jembatan = ref<Jembatan | null>(null);
const versiAplikasi = ref<string | null>(null);

function kenaliAplikasi() {
  const j = (window as any).flutter_inappwebview as Jembatan | undefined;
  if (!j?.callHandler) return;
  jembatan.value = j;
  j.callHandler('aplikasi').then((x: any) => { versiAplikasi.value = x?.versi ?? null; }).catch(() => {});
}

function bukaPengaturan() {
  void jembatan.value?.callHandler('pengaturan');
}

const { dialog, tanya, batal, lanjut } = useDialog();
const luringSiap = ref(false);
onMounted(() => {
  luringSiap.value = Boolean(navigator.serviceWorker?.controller);
  kenaliAplikasi();
  window.addEventListener('flutterInAppWebViewPlatformReady', kenaliAplikasi, { once: true });
});

async function keluar() {
  const n = jaringan.menunggu + jaringan.gagal;
  if (n && !await tanya({
    judul: 'Tetap keluar?',
    pesan: `${n} kiriman belum sampai ke server. Kiriman itu tetap tersimpan di ponsel ini dan baru terkirim saat Anda masuk lagi dengan akun ini.`,
    labelAksi: 'Keluar',
  })) return;
  await bersihkanSimpanan();
  router.post(p.tautan.keluar);
}
</script>

<template>
  <Head title="Profil" />

  <div class="lp-atas"><h1 class="lp-judul">Profil</h1></div>
  <div style="height:12px"></div>
  <BilahLuring />

  <section class="lp-kartu kartu-saya">
    <span class="avatar">{{ saya.inisial || '·' }}</span>
    <div class="saya-isi">
      <div class="nama">{{ saya.nama }}</div>
      <div class="sub">{{ [saya.jabatan, saya.departemen].filter(Boolean).join(' · ') || '—' }}</div>
      <div class="sub">{{ saya.perusahaan || 'Semua perusahaan' }}</div>
      <div class="lp-mono id">{{ saya.nik ? `NIK ${saya.nik} · ` : '' }}{{ saya.email }}</div>
    </div>
  </section>

  <div class="lp-bagian"><h2>Layar lapangan</h2></div>
  <nav class="lp-kartu lp-daftar">
    <Link :href="tautan.sertifikat" class="lp-baris"><span class="lp-petak nada-aman"><IkonLapangan nama="sertifikat" /></span><span class="lp-baris-isi judul">Sertifikat saya</span><IkonLapangan nama="kanan" :tebal="2.2" class="lp-baris-panah" /></Link>
    <Link :href="tautan.izin" class="lp-baris"><span class="lp-petak nada-biru"><IkonLapangan nama="izin" /></span><span class="lp-baris-isi judul">Izin kerja hari ini</span><IkonLapangan nama="kanan" :tebal="2.2" class="lp-baris-panah" /></Link>
    <Link :href="tautan.p2h" class="lp-baris"><span class="lp-petak nada-jingga"><IkonLapangan nama="truk" /></span><span class="lp-baris-isi judul">P2H unit</span><IkonLapangan nama="kanan" :tebal="2.2" class="lp-baris-panah" /></Link>
  </nav>

  <div class="lp-bagian"><h2>Perangkat ini</h2></div>
  <div class="lp-kartu lp-daftar">
    <div class="lp-baris">
      <span class="lp-petak" :class="luringSiap ? 'nada-aman' : 'nada-netral'"><IkonLapangan :nama="luringSiap ? 'perisai' : 'luring'" /></span>
      <span class="lp-baris-isi">
        <span class="judul" style="display:block">{{ luringSiap ? 'Siap dipakai tanpa sinyal' : 'Mode luring belum siap' }}</span>
        <span class="ket">{{ luringSiap ? 'Layar yang pernah dibuka tersimpan. Laporan dan P2H disimpan dulu di ponsel bila sinyal hilang.' : 'Buka mode lapangan sekali lagi saat ada sinyal supaya layarnya tersimpan.' }}</span>
      </span>
    </div>
    <Link href="/lapangan/tugas" class="lp-baris">
      <span class="lp-petak" :class="jaringan.gagal ? 'nada-bahaya' : jaringan.menunggu ? 'nada-jingga' : 'nada-netral'"><IkonLapangan nama="kirimUlang" /></span>
      <span class="lp-baris-isi">
        <span class="judul" style="display:block">Kiriman tertunda</span>
        <span class="ket">{{ jaringan.menunggu || jaringan.gagal ? `${jaringan.menunggu} menunggu · ${jaringan.gagal} ditolak` : 'Tidak ada — semuanya sudah terkirim' }}</span>
      </span>
      <IkonLapangan nama="kanan" :tebal="2.2" class="lp-baris-panah" />
    </Link>
  </div>

  <template v-if="jembatan">
    <div class="lp-bagian"><h2>Aplikasi</h2><span v-if="versiAplikasi" class="lp-mono lp-hitung">v{{ versiAplikasi }}</span></div>
    <div class="lp-kartu lp-daftar">
      <button type="button" class="lp-baris baris-tombol" @click="bukaPengaturan">
        <span class="lp-petak nada-jingga"><IkonLapangan nama="perisai" /></span>
        <span class="lp-baris-isi">
          <span class="judul" style="display:block">Pengaturan aplikasi</span>
          <span class="ket">Kunci sidik jari, izin lokasi, dan pintasan</span>
        </span>
        <IkonLapangan nama="kanan" :tebal="2.2" class="lp-baris-panah" />
      </button>
    </div>
  </template>

  <div class="lp-bagian"><h2>Lainnya</h2></div>
  <nav class="lp-kartu lp-daftar">
    <a :href="tautan.lengkap" class="lp-baris"><span class="lp-petak nada-netral"><IkonLapangan nama="layar" /></span><span class="lp-baris-isi judul">Buka versi web lengkap</span><IkonLapangan nama="kanan" :tebal="2.2" class="lp-baris-panah" /></a>
    <a v-if="tautan.akun" :href="tautan.akun" class="lp-baris"><span class="lp-petak nada-netral"><IkonLapangan nama="sandi" /></span><span class="lp-baris-isi judul">Akun &amp; kata sandi</span><IkonLapangan nama="kanan" :tebal="2.2" class="lp-baris-panah" /></a>
    <a :href="tautan.privasi" class="lp-baris"><span class="lp-petak nada-netral"><IkonLapangan nama="perisai" /></span><span class="lp-baris-isi judul">Kebijakan privasi</span><IkonLapangan nama="kanan" :tebal="2.2" class="lp-baris-panah" /></a>
    <a v-if="tautan.hapusAkun" :href="tautan.hapusAkun" class="lp-baris"><span class="lp-petak nada-netral"><IkonLapangan nama="tutup" /></span><span class="lp-baris-isi judul">Hapus akun</span><IkonLapangan nama="kanan" :tebal="2.2" class="lp-baris-panah" /></a>
    <button type="button" class="lp-baris keluar" @click="keluar"><span class="lp-petak nada-bahaya"><IkonLapangan nama="keluar" /></span><span class="lp-baris-isi judul">Keluar</span></button>
  </nav>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>

<style scoped>
.kartu-saya { margin: 4px 20px 0; padding: 16px; display: flex; gap: 14px; align-items: center; }
.avatar { width: 56px; height: 56px; border-radius: 28px; background: var(--ink); color: #FFFFFF; display: grid; place-items: center; font-size: 19px; font-weight: 700; flex: none; }
.saya-isi { min-width: 0; }
.nama { font-size: 19px; font-weight: 700; color: var(--ink); overflow-wrap: anywhere; }
.sub { font-size: 13px; color: var(--abu2); margin-top: 2px; }
.id { font-size: 11.5px; color: var(--abu3); margin-top: 6px; overflow-wrap: anywhere; }
.judul { font-size: 15px; font-weight: 600; color: var(--ink); }
.ket { display: block; font-size: 12.5px; line-height: 1.4; color: var(--abu2); margin-top: 2px; }
.keluar, .baris-tombol { width: 100%; border: 0; border-top: 1px solid var(--garis3); background: none; text-align: left; font: inherit; }
.baris-tombol { border-top: 0; }
.keluar .judul { color: var(--bahaya-teks); }
</style>

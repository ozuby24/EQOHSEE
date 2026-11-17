<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import LapanganLayout from '../../Layouts/LapanganLayout.vue';
import IkonLapangan from '../../Components/IkonLapangan.vue';
import BilahLuring from '../../Components/BilahLuring.vue';
import { cobaLagi, hapusKiriman, jaringan, kirimSemua } from '../../lapangan/kotakKeluar';
import { kabar } from '../../lapangan/kabar';
import type { NamaIkon } from '../../lapangan/ikon';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';

defineOptions({ layout: LapanganLayout });

type Tindakan = { jenis: 'hazard' | 'izin' | 'p2h'; kode: string; judul: string; ket: string; nada: string; url: string };
type Laporan = { id: number; kode: string; judul: string; lokasi: string | null; kategori: string; risiko: string; pita: string; skor: number | null; status: string; tanggal: string; batas: string | null; sisaHari: number | null; url: string };

defineProps<{ tindakan: Tindakan[]; laporanSaya: Laporan[] }>();

/* Bukan window.confirm(): di WebView ponsel dialog bawaan itu dibungkam
   dan tindakannya diam-diam tidak jadi. */
const { dialog, tanya, batal, lanjut } = useDialog();
const halaman = usePage<any>();
const uid = computed(() => Number(halaman.props?.pengguna?.id ?? 0));

const tab = ref<'tindakan' | 'laporan'>('tindakan');
const IKON: Record<Tindakan['jenis'], NamaIkon> = { hazard: 'bahaya', izin: 'api', p2h: 'tugas' };

const STATUS: Record<string, { label: string; nada: string }> = {
  Open: { label: 'Menunggu tindak lanjut', nada: 'nada-waspada' },
  'In Progress': { label: 'Dalam perbaikan', nada: 'nada-biru' },
  Closed: { label: 'Ditutup', nada: 'nada-aman' },
};
const PITA: Record<string, string> = { Ekstrem: 'kuat-bahaya', Tinggi: 'kuat-jingga', Sedang: 'kuat-waspada', Rendah: 'kuat-aman' };

const waktu = (iso: string) => new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }).format(new Date(iso));

async function kirimSekarang() {
  const n = await kirimSemua(uid.value);
  if (n) { kabar(`${n} kiriman terkirim.`); router.reload(); }
  else if (jaringan.menunggu) kabar('Belum dapat terkirim — sinyal masih lemah.', true);
}

async function ulang(id: string) {
  const h = await cobaLagi(id, uid.value);
  if (h?.status === 'terkirim') { kabar('Kiriman terkirim.'); router.reload(); }
  else if (h?.status === 'ditolak') kabar(h.sebab, true);
  else if (h) kabar(h.sebab, true);
}

async function buang(id: string, judul: string) {
  if (!await tanya({ judul: 'Hapus dari perangkat?', pesan: `"${judul}" belum sampai ke server. Setelah dihapus, kiriman ini tidak dapat dipulihkan.`, labelAksi: 'Hapus', nada: 'bahaya' })) return;
  await hapusKiriman(id, uid.value);
  kabar('Kiriman dihapus dari perangkat.');
}
</script>

<template>
  <Head title="Tugas" />

  <div class="lp-atas">
    <div>
      <h1 class="lp-judul">Tugas</h1>
      <div class="lp-subjudul">Yang menunggu Anda, berurut menurut tenggat</div>
    </div>
  </div>
  <div style="height:12px"></div>
  <BilahLuring />

  <!-- Antrean perangkat: kiriman yang belum sampai ke server. -->
  <section v-if="jaringan.daftar.length" aria-labelledby="judul-antre">
    <div class="lp-bagian" style="padding-top:6px">
      <h2 id="judul-antre">Di perangkat</h2>
      <button v-if="jaringan.menunggu && jaringan.daring" type="button" class="lp-tautan kirim-sekarang" :disabled="jaringan.mengirim" @click="kirimSekarang">
        {{ jaringan.mengirim ? 'Mengirim…' : 'Kirim sekarang' }}
      </button>
    </div>
    <div class="lp-kartu lp-daftar">
      <div v-for="k in jaringan.daftar" :key="k.klien_id" class="antre">
        <div class="lp-baris">
          <span class="lp-petak" :class="k.galat ? 'nada-bahaya' : 'nada-jingga'"><IkonLapangan :nama="k.galat ? 'bahaya' : 'luring'" /></span>
          <span class="lp-baris-isi">
            <span class="lp-mono lp-baris-kode" style="display:block">{{ k.jenis === 'p2h' ? 'P2H' : 'LAPORAN BAHAYA' }} · {{ waktu(k.dibuat) }}</span>
            <span class="lp-baris-judul" style="display:block">{{ k.judul }}</span>
            <span class="lp-baris-ket" :class="k.galat ? 'nada-bahaya' : ''" style="display:block;white-space:normal">
              {{ k.galat ? `Ditolak: ${k.galat}` : (jaringan.daring ? 'Menunggu giliran kirim' : 'Menunggu sinyal') }}
            </span>
          </span>
        </div>
        <div v-if="k.galat" class="antre-aksi">
          <button type="button" class="lp-tombol garis kecil" @click="buang(k.klien_id, k.judul)">Hapus</button>
          <button type="button" class="lp-tombol kecil" :disabled="!jaringan.daring" @click="ulang(k.klien_id)">Kirim ulang</button>
        </div>
      </div>
    </div>
  </section>

  <div class="lp-segmen dua" role="group" aria-label="Tampilan">
    <button type="button" :aria-pressed="tab === 'tindakan'" @click="tab = 'tindakan'">Perlu tindakan<small>{{ tindakan.length }}</small></button>
    <button type="button" :aria-pressed="tab === 'laporan'" @click="tab = 'laporan'">Laporan saya<small>{{ laporanSaya.length }}</small></button>
  </div>

  <template v-if="tab === 'tindakan'">
    <div v-if="tindakan.length" class="lp-kartu lp-daftar">
      <Link v-for="t in tindakan" :key="t.kode + t.judul" :href="t.url" class="lp-baris">
        <span class="lp-petak" :class="'nada-' + t.nada"><IkonLapangan :nama="IKON[t.jenis]" /></span>
        <span class="lp-baris-isi">
          <span class="lp-mono lp-baris-kode" style="display:block">{{ t.kode }}</span>
          <span class="lp-baris-judul" style="display:block">{{ t.judul }}</span>
          <span class="lp-baris-ket" :class="'nada-' + t.nada" style="display:block">{{ t.ket }}</span>
        </span>
        <IkonLapangan nama="kanan" :tebal="2.2" class="lp-baris-panah" />
      </Link>
    </div>
    <div v-else class="lp-kosong"><strong>Tidak ada yang menunggu Anda</strong>Laporan, uji gas, dan P2H shift ini sudah beres.</div>
  </template>

  <template v-else>
    <div v-if="laporanSaya.length" class="lp-kartu lp-daftar">
      <Link v-for="r in laporanSaya" :key="r.id" :href="r.url" class="lp-baris">
        <span class="lp-baris-isi">
          <span class="laporan-atas">
            <span class="lp-mono lp-baris-kode">{{ r.kode }}</span>
            <span class="lp-pil" :class="PITA[r.pita] ?? ''">{{ r.pita }}<template v-if="r.skor"> · {{ r.skor }}</template></span>
            <span class="lp-pil" :class="STATUS[r.status]?.nada">{{ STATUS[r.status]?.label ?? r.status }}</span>
          </span>
          <span class="lp-baris-judul" style="display:block">{{ r.judul }}</span>
          <span class="lp-baris-ket" style="display:block;font-weight:500">{{ r.tanggal }}<template v-if="r.lokasi"> · {{ r.lokasi }}</template></span>
        </span>
        <IkonLapangan nama="kanan" :tebal="2.2" class="lp-baris-panah" />
      </Link>
    </div>
    <div v-else class="lp-kosong"><strong>Belum ada laporan</strong>Laporan bahaya yang Anda kirim akan tampil di sini beserta tindak lanjutnya.</div>
  </template>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>

<style scoped>
.lp-segmen.dua { grid-template-columns: repeat(2, minmax(0, 1fr)); margin: 16px 20px 0; }
.antre { border-top: 1px solid var(--garis3); }
.antre:first-child { border-top: 0; }
.antre .lp-baris { border-top: 0; }
.antre-aksi { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr); gap: 8px; padding: 0 14px 14px 64px; }
.kirim-sekarang { background: none; border: 0; padding: 0; }
.laporan-atas { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
</style>

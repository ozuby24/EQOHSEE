<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import LapanganLayout from '../../Layouts/LapanganLayout.vue';
import IkonLapangan from '../../Components/IkonLapangan.vue';
import BilahLuring from '../../Components/BilahLuring.vue';
import { jaringan } from '../../lapangan/kotakKeluar';

defineOptions({ layout: LapanganLayout });

type Unit = {
  id: number; kode: string; nama: string; jenis: string; ket: string | null; hm: number | null; status: string;
  ditahanSejak: string | null; ditahanKarena: string | null; sudahShiftIni: boolean;
  terakhir: { tanggal: string; shift: string; hasil: string; operator: string } | null;
  url: string; lepas: string;
};

const p = defineProps<{ shift: string; unit: Unit[]; dapatMelepas: boolean }>();

const ditahan = computed(() => p.unit.filter((u) => u.status === 'ditahan'));
const belum = computed(() => p.unit.filter((u) => u.status !== 'ditahan' && !u.sudahShiftIni));
const sudah = computed(() => p.unit.filter((u) => u.status !== 'ditahan' && u.sudahShiftIni));

/* Lepas tahan: satu unit terbuka sekaligus, dengan alasan tertulis. */
const buka = ref<number | null>(null);
const catatan = ref('');
const galat = ref('');
const memproses = ref(false);

function lepas(u: Unit) {
  if (catatan.value.trim().length < 5) { galat.value = 'Tulis apa yang sudah diperbaiki (minimal 5 huruf).'; return; }
  memproses.value = true;
  router.post(u.lepas, { catatan: catatan.value.trim() }, {
    preserveScroll: true,
    onSuccess: () => { buka.value = null; catatan.value = ''; galat.value = ''; },
    onError: (e) => { galat.value = String(Object.values(e)[0] ?? 'Gagal melepas tahan.'); },
    onFinish: () => { memproses.value = false; },
  });
}

const terakhir = (u: Unit) => (u.terakhir ? `${u.terakhir.tanggal} · ${u.terakhir.shift.toLowerCase()} · ${u.terakhir.operator}` : 'Belum pernah diperiksa');
</script>

<template>
  <Head title="P2H unit" />

  <div class="lp-atas">
    <div>
      <h1 class="lp-judul">P2H unit</h1>
      <div class="lp-subjudul">{{ shift }} · {{ unit.length }} unit aktif</div>
    </div>
  </div>
  <div style="height:12px"></div>
  <BilahLuring />

  <div v-if="!unit.length" class="lp-kosong"><strong>Belum ada unit terdaftar</strong>Admin menambahkan unit di web: Maintenance → P2H Unit.</div>

  <section v-if="ditahan.length" aria-labelledby="judul-ditahan">
    <div class="lp-bagian"><h2 id="judul-ditahan">Ditahan</h2><span class="lp-mono lp-hitung">{{ ditahan.length }}</span></div>
    <div class="lp-kartu lp-daftar">
      <div v-for="u in ditahan" :key="u.id" class="tahan-baris">
        <Link :href="u.url" class="lp-baris">
          <span class="lp-petak nada-bahaya"><IkonLapangan nama="truk" /></span>
          <span class="lp-baris-isi">
            <span class="lp-mono lp-baris-kode" style="display:block">{{ u.kode }} · DITAHAN<template v-if="u.ditahanSejak"> SEJAK {{ u.ditahanSejak.toUpperCase() }}</template></span>
            <span class="lp-baris-judul" style="display:block">{{ u.nama }}</span>
            <span class="lp-baris-ket nada-bahaya alasan" style="display:block">{{ u.ditahanKarena }}</span>
          </span>
          <IkonLapangan nama="kanan" :tebal="2.2" class="lp-baris-panah" />
        </Link>
        <div v-if="dapatMelepas" class="lepas">
          <button v-if="buka !== u.id" type="button" class="lp-tombol garis kecil" :disabled="!jaringan.daring" @click="buka = u.id; catatan = ''; galat = ''">
            {{ jaringan.daring ? 'Lepas tahan' : 'Lepas tahan perlu sinyal' }}
          </button>
          <form v-else class="lepas-isi" @submit.prevent="lepas(u)">
            <label :for="`lepas-${u.id}`" class="lp-label">Apa yang sudah diperbaiki?</label>
            <textarea :id="`lepas-${u.id}`" v-model="catatan" class="lp-masukan" rows="2" maxlength="500" placeholder="mis. Retarder diganti, uji jalan di R-04 normal"></textarea>
            <p v-if="galat" class="lp-salah">{{ galat }}</p>
            <div class="lepas-aksi">
              <button type="button" class="lp-tombol garis kecil" @click="buka = null">Batal</button>
              <button type="submit" class="lp-tombol kecil" :disabled="memproses">{{ memproses ? 'Menyimpan…' : 'Laik operasi' }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </section>

  <section v-if="belum.length" aria-labelledby="judul-belum">
    <div class="lp-bagian"><h2 id="judul-belum">Belum diperiksa {{ shift.toLowerCase() }}</h2><span class="lp-mono lp-hitung">{{ belum.length }}</span></div>
    <div class="lp-kartu lp-daftar">
      <Link v-for="u in belum" :key="u.id" :href="u.url" class="lp-baris">
        <span class="lp-petak nada-netral"><IkonLapangan nama="tugas" /></span>
        <span class="lp-baris-isi">
          <span class="lp-mono lp-baris-kode" style="display:block">{{ u.kode }} · {{ u.jenis.toUpperCase() }}</span>
          <span class="lp-baris-judul" style="display:block">{{ u.nama }}</span>
          <span class="lp-baris-ket" style="display:block;font-weight:500">{{ terakhir(u) }}</span>
        </span>
        <span class="mulai">Mulai</span>
      </Link>
    </div>
  </section>

  <section v-if="sudah.length" aria-labelledby="judul-sudah">
    <div class="lp-bagian"><h2 id="judul-sudah">Sudah diperiksa</h2><span class="lp-mono lp-hitung">{{ sudah.length }}</span></div>
    <div class="lp-kartu lp-daftar">
      <Link v-for="u in sudah" :key="u.id" :href="u.url" class="lp-baris">
        <span class="lp-petak nada-aman"><IkonLapangan nama="centang" :tebal="2.4" /></span>
        <span class="lp-baris-isi">
          <span class="lp-mono lp-baris-kode" style="display:block">{{ u.kode }} · LAIK OPERASI</span>
          <span class="lp-baris-judul" style="display:block">{{ u.nama }}</span>
          <span class="lp-baris-ket" style="display:block;font-weight:500">{{ terakhir(u) }}</span>
        </span>
        <IkonLapangan nama="kanan" :tebal="2.2" class="lp-baris-panah" />
      </Link>
    </div>
  </section>
</template>

<style scoped>
.tahan-baris { border-top: 1px solid var(--garis3); }
.tahan-baris:first-child { border-top: 0; }
.tahan-baris .lp-baris { border-top: 0; }
.alasan { white-space: normal; font-weight: 600; line-height: 1.35; }
.lepas { padding: 0 14px 14px 64px; }
.lepas-isi .lp-masukan { margin-top: 6px; }
.lepas-aksi { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr); gap: 8px; margin-top: 10px; }
.mulai { font-size: 13.5px; font-weight: 700; color: var(--jingga); padding-left: 4px; }
</style>

<script setup lang="ts">
/**
 * Pilihan penyusunan Register Tindakan Perbaikan sebelum diunduh.
 *
 * ── Kenapa berupa dialog, bukan tombol unduh biasa ──
 *
 * Register adalah lembar terkendali yang dicetak, ditandatangani, dan
 * dibawa ke rapat bulanan. Ia bukan ekspor data: urutan barisnya adalah
 * urutan pembahasannya, dan penyaringnya menentukan siapa yang hadir
 * karena namanya disebut. Keduanya keputusan yang diambil SEBELUM
 * berkasnya dibuat — bukan sesudahnya, dengan menyortir ulang di Excel,
 * karena gambar pada Excel mengambang di atas lembar dan tidak ikut
 * berpindah saat barisnya diurutkan ulang.
 *
 * ── Kenapa saringannya sendiri, bukan warisan dari layar ──
 *
 * Monitor punya kotak cari dan lima saringan yang dipakai orang untuk
 * menelusuri. Bila register mewarisinya, kotak cari yang kebetulan
 * masih terisi "tanggul" akan diam-diam memotong lembar yang diserahkan
 * ke rapat menjadi tiga baris — dan yang menerimanya tidak punya cara
 * mengetahui bahwa ada yang hilang. Karena itu dialog ini berangkat
 * dari keadaan kosong tiap kali dibuka.
 *
 * ── Kenapa jumlahnya dihitung sebelum diunduh ──
 *
 * Register berfoto tertanam berukuran beberapa megabita dan butuh
 * beberapa detik untuk disusun. Tanpa angka di depan, satu-satunya cara
 * mengetahui bahwa saringannya terlalu sempit adalah menunggu unduhan
 * selesai lalu membuka berkasnya — dan mendapati satu baris.
 */
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import type { OpsiBahaya } from '../types';
import Putaran from './Putaran.vue';

const props = defineProps<{
  terbuka: boolean;
  opsi: OpsiBahaya;
  urlUnduh: string;
  urlJumlah: string;
}>();

const emit = defineEmits<{ tutup: [] }>();

const isi = reactive({ urutan: 'bulan', bulan: '', lokasi: '', perusahaan: '' });

const jumlah = ref<number | null>(null);
const menghitung = ref(false);
const gagal = ref(false);
const menyusun = ref(false);

/** Saringan terpilih sebagai pasangan kunci-nilai, yang kosong dibuang. */
const parameter = computed(() => {
  const p: Record<string, string> = { urutan: isi.urutan };
  if (isi.bulan) p.bulan = isi.bulan;
  if (isi.lokasi) p.lokasi = isi.lokasi;
  if (isi.perusahaan) p.perusahaan = isi.perusahaan;
  return p;
});

const alamatUnduh = computed(
  () => `${props.urlUnduh}?${new URLSearchParams(parameter.value).toString()}`,
);

/* Permintaan lama DIBATALKAN, bukan dibiarkan selesai.
   Empat pilihan yang digeser cepat berturut-turut mengirim empat
   permintaan, dan jawabannya tidak dijamin kembali sesuai urutan
   berangkatnya — tanpa pembatalan, angka dari pilihan KETIGA dapat tiba
   terakhir dan menimpa angka pilihan keempat yang sedang terlihat. */
let batal: AbortController | null = null;
let jeda: ReturnType<typeof setTimeout> | null = null;

async function hitung() {
  batal?.abort();
  batal = new AbortController();

  menghitung.value = true;
  gagal.value = false;

  try {
    const r = await fetch(
      `${props.urlJumlah}?${new URLSearchParams(parameter.value).toString()}`,
      { headers: { Accept: 'application/json' }, signal: batal.signal },
    );
    if (!r.ok) throw new Error(String(r.status));

    jumlah.value = Number((await r.json()).jumlah ?? 0);
  } catch (e) {
    /* Pembatalan bukan kegagalan: yang membatalkannya adalah permintaan
       berikutnya, yang sebentar lagi mengisi angkanya sendiri. Menandai
       gagal di sini membuat pesan galat berkedip pada tiap pilihan yang
       digeser. */
    if ((e as Error)?.name === 'AbortError') return;

    gagal.value = true;
    jumlah.value = null;
  } finally {
    if (!batal?.signal.aborted) menghitung.value = false;
  }
}

/* Ditunda sebentar. Tanpa jeda, memilih bulan lalu lokasi berturut-turut
   mengirim dua permintaan yang keduanya terbuang. */
function jadwalkan() {
  if (jeda) clearTimeout(jeda);
  jeda = setTimeout(hitung, 220);
}

watch(() => ({ ...parameter.value }), jadwalkan, { deep: true });

watch(() => props.terbuka, (buka) => {
  if (!buka) {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', tekan);
    return;
  }

  /* Dikosongkan tiap kali dibuka. Pilihan yang tertinggal dari unduhan
     sebelumnya membuat register kedua diam-diam terbatas pada bulan yang
     dipilih setengah jam lalu. */
  Object.assign(isi, { urutan: 'bulan', bulan: '', lokasi: '', perusahaan: '' });
  menyusun.value = false;

  document.body.style.overflow = 'hidden';
  window.addEventListener('keydown', tekan);

  hitung();
});

function tekan(e: KeyboardEvent) {
  if (e.key === 'Escape') { e.preventDefault(); emit('tutup'); }
}

/* Diberesi juga saat komponennya dilepas: berpindah halaman selagi
   dialog terbuka tidak pernah menjalankan cabang penutupnya, dan halaman
   berikutnya mewarisi body yang terkunci gulirannya. */
onBeforeUnmount(() => {
  batal?.abort();
  if (jeda) clearTimeout(jeda);
  document.body.style.overflow = '';
  window.removeEventListener('keydown', tekan);
});

/* Unduhan berupa tautan biasa, jadi tidak ada peristiwa "selesai" yang
   dapat ditunggu. Penanda sibuknya karena itu dipadamkan oleh waktu —
   cukup lama untuk terlihat, dan tidak menghalangi apa pun bila
   penyusunannya ternyata lebih lama. */
function tandaiMenyusun() {
  menyusun.value = true;
  setTimeout(() => { menyusun.value = false; }, 4000);
}

const kosong = computed(() => jumlah.value === 0);

const isian =
  'ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] font-semibold text-stone-600';
const label = 'block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5';
</script>

<template>
  <Transition name="eq-pop">
    <div v-if="terbuka" class="eq-pop-latar" role="dialog" aria-modal="true"
         aria-label="Pilihan Register Tindakan Perbaikan" @click.self="emit('tutup')">

      <div class="eq-reg" tabindex="-1">
        <header class="eq-reg-kepala">
          <div class="min-w-0">
            <span class="eq-pop-pil">Lembar terkendali</span>
            <h2 class="eq-reg-judul">Register Tindakan Perbaikan</h2>
            <p class="eq-reg-ket">
              Berkas Excel berisi temuan, rekomendasi, penanggung jawab, batas akhir,
              serta foto temuan dan perbaikan yang tertanam di dalamnya.
            </p>
          </div>

          <button type="button" class="eq-pop-silang" aria-label="Tutup pilihan register"
                  @click="emit('tutup')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                 stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
          </button>
        </header>

        <div class="eq-reg-isi">
          <div>
            <label :class="label" for="reg-urutan">Urutkan berdasarkan</label>
            <select id="reg-urutan" v-model="isi.urutan" :class="isian">
              <option v-for="u in opsi.urutan" :key="u.nilai" :value="u.nilai">{{ u.label }}</option>
            </select>
            <p class="eq-reg-bantu">
              Urutan baris pada lembarnya — dan karenanya urutan pembahasannya di rapat.
            </p>
          </div>

          <div class="grid sm:grid-cols-3 gap-3">
            <div>
              <label :class="label" for="reg-bulan">Bulan</label>
              <select id="reg-bulan" v-model="isi.bulan" :class="isian">
                <option value="">Semua bulan</option>
                <option v-for="b in opsi.bulan" :key="b.nilai" :value="b.nilai">{{ b.label }}</option>
              </select>
            </div>

            <div>
              <label :class="label" for="reg-lokasi">Lokasi / Area</label>
              <select id="reg-lokasi" v-model="isi.lokasi" :class="isian">
                <option value="">Semua lokasi</option>
                <option v-for="l in opsi.lokasi" :key="l" :value="l">{{ l }}</option>
              </select>
            </div>

            <div>
              <label :class="label" for="reg-perusahaan">Perusahaan terlapor</label>
              <select id="reg-perusahaan" v-model="isi.perusahaan" :class="isian">
                <option value="">Semua perusahaan</option>
                <option v-for="c in opsi.perusahaan" :key="c.id" :value="String(c.id)">{{ c.nama }}</option>
              </select>
            </div>
          </div>

          <!-- Angka yang menentukan apakah tombolnya layak ditekan.
               Ia berdiri sendiri, bukan tersembunyi sebagai keterangan
               kecil di bawah tombol: inilah satu-satunya hal di dialog
               ini yang berubah saat pilihannya digeser. -->
          <p class="eq-reg-jumlah" :class="{ 'is-kosong': kosong, 'is-gagal': gagal }" aria-live="polite">
            <Putaran v-if="menghitung" :ukuran="13" />
            <template v-if="gagal">Jumlahnya tidak dapat dihitung — saringannya tetap dapat diunduh.</template>
            <template v-else-if="jumlah === null">Menghitung temuan…</template>
            <template v-else-if="kosong">Tidak ada temuan yang cocok dengan saringan ini.</template>
            <template v-else><strong>{{ jumlah }}</strong> temuan akan masuk ke dalam register.</template>
          </p>
        </div>

        <footer class="eq-reg-kaki">
          <!-- Tombol unduh DIMATIKAN saat hasilnya nol. Register tanpa
               satu baris pun tetap terbit sebagai lembar berkop lengkap
               dengan tanda tangan — dan lembar kosong yang terlanjur
               tercetak sulit dibedakan dari lembar yang datanya hilang. -->
          <span v-if="kosong" class="eq-reg-unduh is-mati" aria-disabled="true">⤓ Unduh Register (.xlsx)</span>
          <a v-else :href="alamatUnduh" class="eq-reg-unduh" @click="tandaiMenyusun">
            <Putaran v-if="menyusun" :ukuran="13" />
            {{ menyusun ? 'Menyusun berkas…' : '⤓ Unduh Register (.xlsx)' }}
          </a>

          <button type="button" class="eq-reg-batal" @click="emit('tutup')">Batal</button>
        </footer>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.eq-reg {
  width: min(680px, 100%);
  max-height: min(88vh, 720px);
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 24px 60px rgb(0 0 0 / 28%);
  display: grid;

  /* minmax(0,1fr) pada baris tengah, bukan 1fr.
     Dengan 1fr, baris grid tidak pernah menyusut di bawah tinggi
     isinya, sehingga kaki dialognya terdorong keluar layar pada
     perusahaan yang punya tiga puluh lokasi. */
  grid-template-rows: auto minmax(0, 1fr) auto;
  overflow: hidden;
  position: relative;
}

.eq-reg-kepala {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 20px 22px 14px;
  border-bottom: 1px solid #f0efed;
}

.eq-reg-judul {
  font-size: 18px;
  font-weight: 800;
  color: var(--cam-ink, #26211d);
  margin: 8px 0 4px;
  line-height: 1.25;
}

.eq-reg-ket {
  font-size: 12px;
  color: #a8a29e;
  line-height: 1.55;
  margin: 0;
}

.eq-reg-isi {
  padding: 18px 22px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 16px;

  /* min-height: 0 — tanpa ini elemen yang dapat digulir di dalam grid
     tetap melebar mengikuti isinya alih-alih menggulir. */
  min-height: 0;
}

.eq-reg-bantu {
  font-size: 11px;
  color: #a8a29e;
  margin-top: 6px;
  line-height: 1.5;
}

.eq-reg-jumlah {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12.5px;
  color: #44403c;
  background: #f5f5f4;
  border-radius: 12px;
  padding: 11px 14px;
  margin: 0;
}

.eq-reg-jumlah strong { font-weight: 800; font-size: 14px; }
.eq-reg-jumlah.is-kosong { background: #fef2f2; color: #b91c1c; }
.eq-reg-jumlah.is-gagal  { background: #fffbeb; color: #b45309; }

.eq-reg-kaki {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 22px 18px;
  border-top: 1px solid #f0efed;
}

.eq-reg-unduh {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: linear-gradient(135deg, #8bc34a, #689f38);
  color: #fff;
  font-size: 12.5px;
  font-weight: 800;
  border-radius: 12px;
  padding: 11px 18px;
  transition: filter .15s;
}

.eq-reg-unduh:hover { filter: brightness(1.06); }

.eq-reg-unduh.is-mati {
  background: #e7e5e4;
  color: #a8a29e;
  cursor: not-allowed;
  filter: none;
}

.eq-reg-batal {
  font-size: 12.5px;
  font-weight: 700;
  color: #a8a29e;
  padding: 11px 8px;
}

.eq-reg-batal:hover { color: var(--cam-ink, #26211d); }

@media (prefers-reduced-motion: reduce) {
  .eq-reg-unduh { transition: none; }
}

/* Tema gelap. Dialog yang tidak mengenalnya tergambar sebagai kotak
   putih menyilaukan di tengah halaman gelap — dan yang membukanya pada
   gilir malam menutupnya kembali sebelum sempat membaca pilihannya. */
:global([data-tema='gelap']) .eq-reg { background: #111C27; }
:global([data-tema='gelap']) .eq-reg-kepala { border-bottom-color: #22303F; }
:global([data-tema='gelap']) .eq-reg-kaki { border-top-color: #22303F; }
:global([data-tema='gelap']) .eq-reg-judul { color: #F5F5F4; }
:global([data-tema='gelap']) .eq-reg-jumlah { background: #0D1621; color: #D6D3D1; }
:global([data-tema='gelap']) .eq-reg-jumlah.is-kosong { background: #2A1517; color: #FCA5A5; }
:global([data-tema='gelap']) .eq-reg-jumlah.is-gagal { background: #2A2213; color: #FCD34D; }
:global([data-tema='gelap']) .eq-reg-unduh.is-mati { background: #22303F; color: #78716C; }

/* Sejajar dengan pop-out lain: di layar sempit ia menempel ke bawah,
   bukan melayang di tengah. */
@media (max-width: 520px) {
  .eq-reg {
    width: 100%;
    max-height: 92dvh;
    border-radius: 20px 20px 0 0;
  }
  .eq-reg-kepala { padding: 18px 16px 12px; }
  .eq-reg-isi { padding: 16px; }
  .eq-reg-kaki { padding: 12px 16px 16px; }
}
</style>

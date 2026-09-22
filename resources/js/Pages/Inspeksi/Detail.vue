<script setup lang="ts">
/**
 * Inspeksi — pelaksanaan pemeriksaan.
 *
 * Seluruh baris parameter disimpan dalam satu kiriman, berkunci id
 * masing-masing. Menyimpan per baris berarti orang yang mengisi dua
 * puluh parameter menunggu dua puluh kali, dan kehilangan sebagian
 * isian setiap kali sambungan lapangan terputus di tengah.
 */
import { computed, reactive, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import type { HalamanDetailInspeksi } from '../../types';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
const { dialog, tanya, batal, lanjut } = useDialog();


const props = defineProps<HalamanDetailInspeksi>();

/* Salinan lokal tiap baris. Dikirim sebagai item[id][kolom] — bentuk
   yang sama dengan yang diterima controller sejak versi Blade-nya. */
const baris = reactive<Record<number, Record<string, string>>>(
  Object.fromEntries(props.item.map((x) => [x.id, {
    kondisi: x.kondisi ?? '', risiko: x.risiko ?? '',
    temuan: x.temuan ?? '', tindakan: x.tindakan ?? '',
  }])),
);

const status = ref(props.i.status);
const menyimpan = ref(false);

/* ── Butir dikelompokkan, bukan dituang sebagai satu daftar panjang ──

   Daftar periksa kantor berisi dua puluh delapan butir. Sebagai satu
   gulungan datar, yang mengisinya kehilangan tempatnya begitu layar
   digulir: tidak ada apa pun yang memberi tahu bahwa lima butir
   berikutnya masih tentang proteksi kebakaran, atau bahwa bagian
   listrik sudah selesai seluruhnya.

   Urutannya mengikuti urutan butir dari server — order_index — bukan
   diurutkan ulang menurut abjad kelompoknya. Urutan itu adalah urutan
   BERJALAN KAKI yang disusun penyusun daftar periksanya: panel listrik
   dulu karena ada di koridor masuk, titik kumpul terakhir karena ada di
   luar. Mengurutkannya menurut abjad membuat pemeriksanya bolak-balik. */
const kelompok = computed(() => {
  const urut: string[] = [];
  const peta = new Map<string, typeof props.item>();

  for (const x of props.item) {
    const nama = x.kelompok || 'Umum';
    if (!peta.has(nama)) { peta.set(nama, []); urut.push(nama); }
    peta.get(nama)!.push(x);
  }

  return urut.map((nama) => ({ nama, butir: peta.get(nama)! }));
});

/** Berapa butir yang sudah punya keputusan — apa pun keputusannya. */
function dinilai(butir: { id: number }[]) {
  return butir.filter((x) => baris[x.id]?.kondisi).length;
}

const rekap = computed(() => {
  const semua = props.item.map((x) => baris[x.id]?.kondisi ?? '');

  return {
    total: semua.length,
    dinilai: semua.filter(Boolean).length,
    tidakSesuai: semua.filter((k) => k === 'Tidak Sesuai').length,
  };
});

const persenTerisi = computed(
  () => (rekap.value.total ? Math.round((rekap.value.dinilai / rekap.value.total) * 100) : 0),
);

/* Menandai seluruh butir satu kelompok "Sesuai" sekaligus.

   Ini yang paling sering terjadi sebenarnya: pada pemeriksaan yang
   sehat, dua puluh dari dua puluh delapan butir memang sesuai. Menekan
   dua puluh kali untuk mengatakan "tidak ada masalah" membuat orang
   berhenti memeriksa satu per satu dan mulai menekan asal — yaitu
   persoalan keselamatan, bukan persoalan tampilan.

   Butir yang SUDAH dinilai tidak ditimpa. Yang menekan tombol ini
   biasanya sudah menandai satu-dua ketidaksesuaian lebih dulu, dan
   menimpanya akan menghapus justru satu-satunya isian yang penting di
   kelompok itu. */
function semuaSesuai(butir: { id: number }[]) {
  for (const x of butir) {
    if (!baris[x.id].kondisi) baris[x.id].kondisi = 'Sesuai';
  }
}

function kosongkan(butir: { id: number }[]) {
  for (const x of butir) {
    Object.assign(baris[x.id], { kondisi: '', temuan: '', tindakan: '' });
  }
}

/* Unggahan foto per butir berdiri sendiri, tidak lewat form besar.
   Fotonya bertambah, bukan menggantikan — satu temuan kerap difoto
   dari beberapa sudut. */
const mengunggah = ref<number | null>(null);

function unggahFoto(id: number, url: string, e: Event) {
  const input = e.target as HTMLInputElement;
  if (!input.files?.length) return;

  const data = new FormData();
  for (const f of Array.from(input.files)) data.append('foto[]', f);

  mengunggah.value = id;

  router.post(url, data, {
    preserveScroll: true,
    forceFormData: true,
    onFinish: () => {
      mengunggah.value = null;

      /* Dikosongkan supaya memilih berkas yang SAMA sesudah gagal tetap
         memicu perubahan. Input berkas yang nilainya tidak berubah tidak
         menerbitkan event apa pun, dan yang mencobanya lagi mengira
         unggahannya diabaikan. */
      input.value = '';
    },
  });
}

function simpanSemua() {
  menyimpan.value = true;
  router.post(props.tautan.simpanItem, { item: baris, status: status.value }, {
    preserveScroll: true,
    onFinish: () => { menyimpan.value = false; },
  });
}

async function angkat(url: string) {
  if (!await tanya('Naikkan temuan ini menjadi Hazard Report?')) return;
  router.post(url, {}, { preserveScroll: true });
}

async function hapusItem(url: string) {
  if (!await tanya('Hapus parameter ini?')) return;
  router.delete(url, { preserveScroll: true });
}

/* Tambah parameter dan tambah petugas berdiri sendiri — keduanya
   menambah baris baru, bukan menyunting yang sudah ada. */
const formItem = useForm({ uraian: '', kelompok: '', risiko: '' });
const formPetugas = useForm({ user_id: '', nama: '', jabatan: '', peran: 'Anggota' });

const isian = 'ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition';
const label = 'block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1';
</script>

<template>
  <Head :title="`Inspeksi ${i.kode}`" />

  <div class="space-y-5">

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="text-[10px] font-bold bg-cam-ink text-white px-2 py-0.5 rounded num">{{ i.kode }}</span>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                  :class="i.status === 'Selesai' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
              {{ i.status }}
            </span>
            <span v-if="i.template" class="text-[10px] font-semibold bg-stone-100 text-stone-500 px-2 py-0.5 rounded-full">
              {{ i.template }}
            </span>
          </div>
          <p class="text-[15px] font-bold text-cam-ink mt-2.5">{{ i.judul }}</p>
          <div class="flex flex-wrap gap-x-3 gap-y-1 text-[11.5px] text-stone-400 mt-1.5">
            <span>📍 {{ i.lokasi ?? '—' }}</span>
            <span>{{ i.tanggal }}</span>
            <span v-if="i.perusahaan">{{ i.perusahaan }}</span>
            <span v-if="i.pembuat">Dibuat: {{ i.pembuat }}</span>
          </div>
        </div>

        <div class="flex flex-wrap gap-2 shrink-0">
          <a :href="tautan.ubah"
             class="rounded-xl border border-stone-200 px-3.5 py-2 text-[12px] font-bold text-stone-600
                    hover:bg-stone-50 transition">Ubah</a>
          <a :href="tautan.lembar" target="_blank" rel="noopener"
             class="lime-gradient shadow-glow rounded-xl text-white px-3.5 py-2 text-[12px]
                    font-bold hover:brightness-105 transition">⎙ Cetak Lembar Inspeksi</a>
          <a :href="tautan.register" target="_blank" rel="noopener"
             class="rounded-xl border border-stone-200 px-3.5 py-2 text-[12px] font-bold text-stone-600
                    hover:bg-stone-50 transition">Register</a>
          <a :href="tautan.kembali"
             class="px-3 py-2 text-[12px] font-semibold text-stone-400 hover:text-cam-ink">← Kembali</a>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-3">Inspektur</h3>

      <div class="flex flex-wrap gap-2 mb-4">
        <span v-for="p in inspektur" :key="p.id"
              class="inline-flex items-center gap-2 bg-stone-50 border border-stone-200 rounded-xl px-3 py-1.5">
          <span class="text-[12px] font-bold text-cam-ink">{{ p.nama }}</span>
          <span class="text-[10.5px] text-stone-400">{{ p.jabatan ?? '—' }} · {{ p.peran }}</span>
        </span>
        <span v-if="!inspektur.length" class="text-[12px] text-stone-400">Belum ada inspektur.</span>
      </div>

      <form class="grid gap-2 sm:grid-cols-[1fr_150px_120px_auto] items-end"
            @submit.prevent="formPetugas.post(tautan.tambahPetugas, {
              preserveScroll: true, onSuccess: () => formPetugas.reset() })">
        <div>
          <label :class="label">Tambah dari pengguna</label>
          <select v-model="formPetugas.user_id" :class="isian" aria-label="Pengguna">
            <option value="">— pilih —</option>
            <option v-for="k in opsi.kandidat" :key="k.id" :value="String(k.id)">{{ k.nama }}</option>
          </select>
        </div>
        <div>
          <label :class="label">Atau nama</label>
          <input v-model="formPetugas.nama" :class="isian">
        </div>
        <div>
          <label :class="label">Peran</label>
          <select v-model="formPetugas.peran" :class="isian" aria-label="Peran">
            <option v-for="r in opsi.peran" :key="r" :value="r">{{ r }}</option>
          </select>
        </div>
        <button type="submit" :disabled="formPetugas.processing"
                class="rounded-xl border border-stone-200 px-3.5 py-2 text-[12px] font-bold
                       text-stone-600 hover:bg-stone-50 transition disabled:opacity-40">Tambah</button>
      </form>
    </div>

    <!-- `overflow-hidden` sengaja TIDAK dipakai di sini.

         Kartu lain memakainya untuk memangkas sudut membulat, tetapi
         nenek-moyang ber-overflow selain `visible` menjadi wadah gulir
         terdekat bagi `position: sticky` di dalamnya — dan karena wadah
         itu sendiri tidak pernah bergulir, kepala yang melekat tidak
         pernah melekat. Sudutnya dibulatkan pada anak pertama dan
         terakhir. -->
    <div class="bg-white rounded-2xl shadow-card border border-stone-100">
      <!-- Kepala yang MELEKAT saat digulir.

           Daftar dua puluh delapan butir jauh lebih panjang daripada
           satu layar, dan tombol Simpan yang tertinggal di puncaknya
           berarti yang selesai mengisi butir terakhir harus menggulir
           kembali ke atas untuk menyimpannya. Penanda kemajuan ikut
           melekat karena pertanyaan "tinggal berapa lagi" muncul justru
           di tengah pengisian. -->
      <div class="eq-kepala-isi">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div class="min-w-0">
            <h3 class="text-[13px] font-bold text-cam-ink">Daftar Periksa</h3>
            <p class="text-[11.5px] text-stone-500 mt-0.5">
              <strong class="num">{{ rekap.dinilai }}</strong> dari
              <strong class="num">{{ rekap.total }}</strong> butir dinilai
              <template v-if="rekap.tidakSesuai">
                · <span class="text-red-600 font-bold num">{{ rekap.tidakSesuai }}</span> tidak sesuai
              </template>
            </p>
          </div>

          <div class="flex items-center gap-2 shrink-0">
            <select v-model="status"
                    class="ring-focus rounded-xl border border-stone-200 pl-3 pr-9 py-2 text-[12px] font-semibold text-stone-600"
                    aria-label="Status inspeksi">
              <option v-for="s in opsi.status" :key="s" :value="s">{{ s }}</option>
            </select>
            <button type="button" :disabled="menyimpan" @click="simpanSemua"
                    class="eq-btn-utama disabled:opacity-40" style="flex:none;padding:8px 16px">
              {{ menyimpan ? 'Menyimpan…' : 'Simpan Hasil' }}
            </button>
          </div>
        </div>

        <!-- Bilah kemajuan: satu garis, dibaca tanpa membaca angka.

             Memakai komponen bawaan `.eq-bilah > i` dari eq-visual,
             bukan salinan sendiri. Salinan berkelas sama akan mewarisi
             diam-diam properti yang kebetulan tidak ditulis ulang, dan
             warnanya berhenti mengikuti warna aksen perusahaan. -->
        <div class="eq-bilah" role="progressbar" :aria-valuenow="persenTerisi"
             aria-valuemin="0" aria-valuemax="100"
             :aria-label="`Kemajuan pengisian ${persenTerisi} persen`">
          <i :style="{ width: persenTerisi + '%' }"></i>
        </div>
      </div>

      <section v-for="g in kelompok" :key="g.nama" class="eq-kelompok">
        <header class="eq-kelompok-kepala">
          <div class="min-w-0">
            <h4>{{ g.nama }}</h4>
            <small>{{ dinilai(g.butir) }} / {{ g.butir.length }} dinilai</small>
          </div>

          <div class="flex items-center gap-1.5 shrink-0">
            <button type="button" class="eq-massal" @click="semuaSesuai(g.butir)">
              Semua sesuai
            </button>
            <button type="button" class="eq-massal eq-massal-sunyi" @click="kosongkan(g.butir)">
              Kosongkan
            </button>
          </div>
        </header>

        <div v-for="x in g.butir" :key="x.id" class="eq-butir"
             :class="{ 'is-nok': baris[x.id].kondisi === 'Tidak Sesuai' }">

          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="text-[12.5px] font-semibold text-cam-ink leading-snug">{{ x.uraian }}</p>
              <p v-if="x.acuan" class="text-[10.5px] text-stone-400 mt-0.5">Acuan: {{ x.acuan }}</p>
            </div>

            <div class="flex items-center gap-2 shrink-0">
              <a v-if="x.hazard" :href="x.urlHazard!"
                 class="text-[10.5px] font-bold bg-red-100 text-red-700 px-2 py-1 rounded-full hover:underline">
                {{ x.hazard }}
              </a>
              <button v-else-if="baris[x.id].kondisi === 'Tidak Sesuai'" type="button"
                      class="text-[10.5px] font-bold border border-amber-200 bg-amber-50 text-amber-700
                             px-2 py-1 rounded-full hover:bg-amber-100 transition"
                      @click="angkat(x.urlAngkat)">Naikkan ke Hazard</button>

              <button type="button" class="text-[11px] text-stone-300 hover:text-red-500"
                      :aria-label="`Hapus butir ${x.uraian}`" @click="hapusItem(x.urlHapus)">✕</button>
            </div>
          </div>

          <!-- Kondisi sebagai TOMBOL, bukan daftar pilih.

               Daftar pilih menuntut tiga ketukan di ponsel — buka,
               gulir, pilih — dikali dua puluh delapan butir. Tombol
               menuntut satu, dan jawabannya terbaca tanpa dibuka. -->
          <div class="eq-pilih" role="group" :aria-label="`Kondisi: ${x.uraian}`">
            <button v-for="k in opsi.kondisi" :key="k" type="button"
                    class="eq-pilih-tombol"
                    :class="[`is-${k === 'Sesuai' ? 'ok' : k === 'Tidak Sesuai' ? 'nok' : 'na'}`,
                             { 'is-aktif': baris[x.id].kondisi === k }]"
                    :aria-pressed="baris[x.id].kondisi === k"
                    @click="baris[x.id].kondisi = baris[x.id].kondisi === k ? '' : k">
              {{ k }}
            </button>
          </div>

          <!-- Temuan, tindakan, risiko, dan foto HANYA pada yang tidak
               sesuai. Pada butir yang sesuai, keempatnya adalah empat
               medan kosong yang tidak akan pernah diisi — dikali dua
               puluh butir, itulah yang membuat daftar periksa terasa
               seperti formulir pajak. -->
          <div v-if="baris[x.id].kondisi === 'Tidak Sesuai'" class="eq-temuan">
            <div class="grid gap-2 sm:grid-cols-[150px_1fr]">
              <div>
                <label :class="label" :for="`risiko-${x.id}`">Risiko</label>
                <select :id="`risiko-${x.id}`" v-model="baris[x.id].risiko" :class="isian">
                  <option value="">— pilih —</option>
                  <option v-for="r in opsi.risiko" :key="r" :value="r">{{ r }}</option>
                </select>
              </div>

              <div>
                <label :class="label" :for="`temuan-${x.id}`">Temuan</label>
                <textarea :id="`temuan-${x.id}`" v-model="baris[x.id].temuan" rows="2"
                          :class="isian" placeholder="Apa yang ditemukan, dan di mana tepatnya"></textarea>
              </div>
            </div>

            <div>
              <label :class="label" :for="`tindakan-${x.id}`">Tindakan</label>
              <textarea :id="`tindakan-${x.id}`" v-model="baris[x.id].tindakan" rows="2"
                        :class="isian" placeholder="Apa yang akan dilakukan, dan sampai kapan"></textarea>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <label class="eq-foto-tombol">
                <input type="file" accept="image/*" capture="environment" multiple class="sr-only"
                       :disabled="mengunggah === x.id"
                       @change="unggahFoto(x.id, x.urlFoto, $event)">
                {{ mengunggah === x.id ? 'Mengunggah…' : '📷 Tambah foto' }}
              </label>

              <span v-if="!x.foto.length" class="text-[11px] text-stone-400">
                Belum ada foto bukti.
              </span>
            </div>
          </div>

          <div v-if="x.foto.length" class="flex flex-wrap gap-2 mt-2">
            <a v-for="(f, n) in x.foto" :key="n" :href="f" target="_blank" rel="noopener">
              <img :src="f" :alt="`Foto butir ${n + 1}`"
                   class="w-16 h-16 object-cover rounded-lg border border-stone-200">
            </a>
          </div>
        </div>
      </section>

      <div>
        <p v-if="!item.length" class="px-5 py-10 text-center text-[12.5px] text-stone-400">
          Belum ada parameter. Tambahkan di bawah, atau pilih jenis inspeksi saat membuat.
        </p>
      </div>

      <form class="p-4 bg-stone-50/60 border-t border-stone-100 rounded-b-2xl
                   grid gap-2 sm:grid-cols-[1fr_180px_130px_auto] items-end"
            @submit.prevent="formItem.post(tautan.tambahItem, {
              preserveScroll: true, onSuccess: () => formItem.reset() })">
        <div>
          <label :class="label">Tambah parameter</label>
          <input v-model="formItem.uraian" :class="isian" placeholder="Uraian yang diperiksa">
        </div>
        <div>
          <label :class="label">Kelompok</label>
          <input v-model="formItem.kelompok" :class="isian">
        </div>
        <div>
          <label :class="label">Risiko</label>
          <select v-model="formItem.risiko" :class="isian" aria-label="Tingkat risiko">
            <option value="">—</option>
            <option v-for="r in opsi.risiko" :key="r" :value="r">{{ r }}</option>
          </select>
        </div>
        <button type="submit" :disabled="formItem.processing || !formItem.uraian.trim()"
                class="rounded-xl border border-stone-200 bg-white px-3.5 py-2 text-[12px] font-bold
                       text-stone-600 hover:bg-stone-50 transition disabled:opacity-40">Tambah</button>
      </form>
    </div>
  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>

<style scoped>
/**
 * Pengisian di lapangan: satu ibu jari, sambil berdiri, sering di
 * bawah matahari. Ukuran sasaran ketuk dan kontras di sini bukan
 * hiasan — keduanya yang menentukan daftar periksa diisi sungguhan
 * atau diisi belakangan dari ingatan.
 */

/* ── kepala yang melekat ───────────────────────────────────────── */

.eq-kepala-isi {
  position: sticky;
  /* Setinggi bilah atas aplikasi, yang mengukur dirinya sendiri dan
     menerbitkan hasilnya. Angka cadangannya untuk render pertama,
     sebelum pengukurnya sempat berjalan. */
  top: var(--eq-topbar-h, 66px);
  z-index: 20;
  padding: 1rem 1.25rem .75rem;
  background: rgba(255, 255, 255, .96);
  border-bottom: 1px solid #f5f5f4;
  border-top-left-radius: 1rem;
  border-top-right-radius: 1rem;
}

@supports (backdrop-filter: blur(2px)) or (-webkit-backdrop-filter: blur(2px)) {
  .eq-kepala-isi {
    background: rgba(255, 255, 255, .84);
    -webkit-backdrop-filter: saturate(180%) blur(12px);
            backdrop-filter: saturate(180%) blur(12px);
  }
}

/* ── kelompok ──────────────────────────────────────────────────── */

.eq-kelompok + .eq-kelompok { border-top: 1px solid #f5f5f4; }

.eq-kelompok-kepala {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: .75rem;
  padding: .7rem 1.25rem;
  background: #fafaf9;
  border-bottom: 1px solid #f5f5f4;
}

.eq-kelompok-kepala h4 {
  font-size: 11px;
  font-weight: 800;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: #0F1720;
}

.eq-kelompok-kepala small {
  display: block;
  margin-top: .1rem;
  font-size: 10.5px;
  color: #a8a29e;
  font-variant-numeric: tabular-nums;
}

.eq-massal {
  flex: none;
  border-radius: .7rem;
  border: 1px solid #d6d3d1;
  background: #fff;
  padding: .3rem .6rem;
  font-size: 10.5px;
  font-weight: 700;
  color: #57534e;
  transition: border-color .16s, color .16s, background-color .16s;
}
.eq-massal:hover { border-color: #22C55E; color: #15803d; background: #f0fdf4; }
.eq-massal-sunyi:hover { border-color: #d6d3d1; color: #0F1720; background: #f5f5f4; }

/* ── butir ─────────────────────────────────────────────────────── */

.eq-butir {
  padding: .85rem 1.25rem;
  /* Garis penanda di tepi kiri; lebarnya tetap ada walau tak berwarna,
     supaya butir tidak bergeser sepersekian detik saat ditandai. */
  border-left: 3px solid transparent;
}
.eq-butir + .eq-butir { border-top: 1px solid #f5f5f4; }

.eq-butir.is-nok {
  border-left-color: #EF4444;
  background: #fffafa;
}

/* ── tombol kondisi ────────────────────────────────────────────── */

.eq-pilih {
  display: flex;
  gap: .4rem;
  margin-top: .6rem;
  /* Dibatasi, tidak dibiarkan melebar sepenuh kartu.

     Di layar 1440px ketiganya menjadi tombol selebar lima ratus piksel
     untuk jawaban satu kata. Yang dibutuhkan sasaran ketuk adalah
     TINGGI dan jarak antar tombol, bukan lebar sejauh itu — dan mata
     yang menyusuri dua puluh delapan baris membaca kolom yang rapi
     lebih cepat daripada bidang yang membentang. */
  max-width: 32rem;
}

.eq-pilih-tombol {
  flex: 1 1 0;
  min-height: 2.5rem;
  border-radius: .75rem;
  border: 1px solid #e7e5e4;
  background: #fff;
  font-size: 12px;
  font-weight: 700;
  color: #78716c;
  transition: border-color .16s, background-color .16s, color .16s, box-shadow .16s;
}
.eq-pilih-tombol:hover { border-color: #d6d3d1; color: #0F1720; }

.eq-pilih-tombol.is-ok.is-aktif {
  background: #22C55E; border-color: #16a34a; color: #fff;
  box-shadow: 0 6px 16px -10px rgba(34, 197, 94, .9);
}
.eq-pilih-tombol.is-nok.is-aktif {
  background: #EF4444; border-color: #DC2626; color: #fff;
  box-shadow: 0 6px 16px -10px rgba(239, 68, 68, .9);
}
.eq-pilih-tombol.is-na.is-aktif {
  background: #57534e; border-color: #44403c; color: #fff;
}

/* ── blok temuan ───────────────────────────────────────────────── */

.eq-temuan {
  display: grid;
  gap: .6rem;
  margin-top: .7rem;
  padding: .8rem;
  border-radius: .9rem;
  border: 1px solid #fee2e2;
  background: #fff;
}

.eq-foto-tombol {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  min-height: 2.5rem;
  padding: 0 .9rem;
  border-radius: .75rem;
  border: 1px dashed #d6d3d1;
  background: #fff;
  font-size: 12px;
  font-weight: 700;
  color: #57534e;
  cursor: pointer;
  transition: border-color .16s, color .16s, background-color .16s;
}
.eq-foto-tombol:hover { border-color: #DC6E00; color: #DC6E00; background: #FFF2E2; }
.eq-foto-tombol:focus-within { outline: 2px solid #F57C00; outline-offset: 2px; }
.eq-foto-tombol:has(input:disabled) { opacity: .5; cursor: progress; }

/* ── ponsel ────────────────────────────────────────────────────── */

@media (max-width: 40rem) {
  .eq-kepala-isi { padding: .85rem 1rem .7rem; }
  .eq-kelompok-kepala { padding: .6rem 1rem; }
  .eq-butir { padding: .8rem 1rem; }

  /* Sasaran ketuk 44px: ukuran yang dipakai ibu jari bersarung tangan,
     bukan ujung jari di dalam ruangan. */
  .eq-pilih-tombol,
  .eq-foto-tombol { min-height: 2.75rem; }
}
</style>

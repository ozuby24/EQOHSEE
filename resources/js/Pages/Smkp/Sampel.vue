<script setup lang="ts">
/**
 * Matriks Metode dan Sampel Audit — komponen ke-8 Rencana Audit.
 *
 * Delapan puluh kriteria × tiga metode adalah enam ratus kotak isian.
 * Menggambarnya sekaligus adalah halaman yang tidak dapat dikerjakan
 * siapa pun: yang mengisi kehilangan tempatnya pada gulungan ketiga.
 *
 * Karena itu halaman ini SATU ELEMEN PADA SATU WAKTU. Auditor memilih
 * elemen, mengisi kriterianya, lalu pindah. Tujuh langkah pendek, bukan
 * satu gulungan sepanjang delapan puluh baris.
 *
 * SELURUH ISIAN TETAP TERSIMPAN SEKALIGUS. Berpindah elemen hanya
 * mengubah yang terlihat, bukan yang ada di dalam formulir — memisah
 * simpanan per elemen akan membuat tujuh simpanan parsial yang
 * masing-masing dapat gagal sendiri-sendiri.
 *
 * PENGECUALIAN DITETAPKAN DI SINI, SEKALI. Kriteria yang tidak berlaku
 * bagi auditi — gudang bahan peledak pada perusahaan jasa pengeboran,
 * penunjukan kepala kapal keruk pada tambang darat — ditandai di sini,
 * dan formulir penilaian membacanya. Menyatakannya ulang butir demi
 * butir di sana membuka jalan bagi butir yang sudah dikecualikan untuk
 * tetap ikut membagi nilai akhir hanya karena penandanya terlewat.
 */
import { computed, h, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import UbinAngka from '../../Components/UbinAngka.vue';

const IKON: Record<string, string[]> = {
  belum:  ['M12 9.4v4.2', 'M12 17h.01', 'M10.4 4.1 2.6 17.8a1.8 1.8 0 0 0 1.6 2.7h15.6a1.8 1.8 0 0 0 1.6-2.7L13.6 4.1a1.8 1.8 0 0 0-3.2 0Z'],
  siap:   ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'm8.5 12.2 2.4 2.4 4.6-4.9'],
  kecuali:['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'M8.5 12h7'],
};

/** Pembungkus kecil supaya tiap ubin cukup menyebut nama ikonnya. */
const Ikon = (p: { nama: string }) => h('svg', {
  viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 1.9,
  'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'aria-hidden': 'true',
}, (IKON[p.nama] ?? []).map((d) => h('path', { d })));

type Kriteria = {
  kode: string; nama: string;
  elemen: string; elemenNama: string;
  induk: string | null;
};
type Baris = { na: boolean; dokumen: string; wawancara: string; observasi: string; ket: string };

const props = propHalaman();

const kriteria = computed<Kriteria[]>(() => (props.kriteria ?? []) as Kriteria[]);
const metode   = computed<Record<string, string>>(() => (props.metode ?? {}) as Record<string, string>);
const elemen   = computed<any[]>(() => (props.elemen ?? []) as any[]);

/* Seluruh kriteria disiapkan, termasuk yang belum pernah disentuh:
   v-model pada kunci yang belum ada akan membuat baris muncul dan
   hilang saat diketik, dan Vue tidak dapat melacak yang belum ada. */
const awal: Record<string, Baris> = {};
for (const k of kriteria.value) {
  const b = ((props.audit?.sampel ?? {}) as Record<string, any>)[k.kode] ?? {};
  awal[k.kode] = {
    na:        !!b.na,
    dokumen:   b.dokumen   ?? '',
    wawancara: b.wawancara ?? '',
    observasi: b.observasi ?? '',
    ket:       b.ket       ?? '',
  };
}

const form = useForm<{ sampel: Record<string, Baris> }>({ sampel: awal });

const elemenAktif = ref<string>(elemen.value[0]?.kode ?? 'I');
const tampak = computed(() => kriteria.value.filter((k) => k.elemen === elemenAktif.value));

/** Terisi berarti punya sekurang-kurangnya satu metode, atau dinyatakan tidak berlaku. */
function terisi(kode: string): boolean {
  const b = form.sampel[kode];
  if (!b) return false;
  if (b.na) return true;
  return !!(b.dokumen.trim() || b.wawancara.trim() || b.observasi.trim());
}

/* Hitungan hidup, bukan angka simpanan: yang mengisi harus melihat
   sisanya berkurang sambil mengetik, bukan sesudah menekan simpan. */
const hitung = computed(() => {
  let isi = 0; let na = 0;
  for (const k of kriteria.value) {
    if (form.sampel[k.kode]?.na) na++;
    if (terisi(k.kode)) isi++;
  }
  return { total: kriteria.value.length, isi, na, kurang: kriteria.value.length - isi };
});

function kurangDiElemen(kode: string): number {
  return kriteria.value.filter((k) => k.elemen === kode && !terisi(k.kode)).length;
}

function simpan() { form.post(props.tautan.simpan, { preserveScroll: true }); }

const isian = 'ring-focus w-full rounded-lg border border-stone-200 px-3 py-2 text-[12px] transition';
</script>

<template>
  <Head title="Matriks Metode & Sampel Audit" />

  <div class="space-y-5">
    <!-- Ringkasan hidup. Angka yang paling dibutuhkan bukan berapa yang
         sudah diisi melainkan berapa yang BELUM — itulah pekerjaan yang
         tersisa sebelum tim turun ke lapangan. -->
    <section class="ubin-kisi ubin-kisi-lebar">
      <UbinAngka :angka="hitung.kurang" label="Belum terencana" :dari="hitung.total"
                 :nada="hitung.kurang ? 'gawat' : 'baik'"
                 catatan="Kriteria tanpa metode pembuktian">
        <template #ikon><Ikon nama="belum" /></template>
      </UbinAngka>

      <UbinAngka :angka="hitung.isi" label="Sudah terencana" :dari="hitung.total" nada="baik"
                 :catatan="`dari ${hitung.total} kriteria`">
        <template #ikon><Ikon nama="siap" /></template>
      </UbinAngka>

      <UbinAngka :angka="hitung.na" label="Tidak berlaku" :dari="hitung.total" nada="netral"
                 catatan="Dikeluarkan dari pembagi nilai">
        <template #ikon><Ikon nama="kecuali" /></template>
      </UbinAngka>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 class="font-bold text-[14px]">Metode dan sampel tiap kriteria</h3>
          <p class="text-[12px] text-stone-500 mt-1 max-w-2xl leading-relaxed">
            Isi sampelnya pada metode yang dipakai; metode yang dikosongkan berarti
            tidak dipakai untuk kriteria itu. Kriteria yang tidak berlaku bagi auditi
            ditandai <b>tidak berlaku</b> di sini — formulir penilaian membacanya dan
            mengeluarkannya dari pembagi nilai.
          </p>
        </div>
        <div class="flex gap-2">
          <Link :href="props.tautan.rencana" class="eq-btn-lain">Rencana Audit</Link>
          <Link :href="props.tautan.cetak" class="eq-btn-lain" target="_blank">Cetak</Link>
        </div>
      </div>

      <!-- Pemilih elemen. Angka di tiap pil adalah sisa pekerjaannya, jadi
           yang mengisi tahu ke mana harus pergi berikutnya tanpa membuka
           ketujuhnya satu per satu. -->
      <div class="eq-pindah mt-4">
        <button v-for="e in elemen" :key="e.kode" type="button" class="eq-pindah-pil"
                :class="e.kode === elemenAktif ? 'eq-pindah-kini' : ''"
                @click="elemenAktif = e.kode">
          <span>{{ e.kode }}. {{ e.nama }}</span>
          <span v-if="kurangDiElemen(e.kode)" class="eq-pindah-lencana">{{ kurangDiElemen(e.kode) }}</span>
        </button>
      </div>

      <form class="mt-5 space-y-3" @submit.prevent="simpan">
        <article v-for="k in tampak" :key="k.kode"
                 class="rounded-xl border p-4 transition"
                 :class="form.sampel[k.kode].na ? 'border-stone-200 bg-stone-50'
                         : terisi(k.kode) ? 'border-cam-lime-soft bg-white' : 'border-stone-200 bg-white'">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="text-[12.5px] font-bold text-cam-ink">{{ k.kode }} · {{ k.nama }}</p>
              <p v-if="k.induk" class="text-[11px] text-stone-400 mt-0.5">{{ k.induk }}</p>
            </div>
            <label class="flex items-center gap-2 text-[11.5px] font-semibold text-stone-500 shrink-0">
              <input v-model="form.sampel[k.kode].na" type="checkbox" class="accent-[#F57C00]">
              <span>Tidak berlaku bagi auditi</span>
            </label>
          </div>

          <!-- Yang tidak berlaku tidak perlu sampel; yang diminta darinya
               justru alasannya, sebab itulah yang dibaca inspektur saat
               mempertanyakan mengapa sebuah kriteria dikeluarkan. -->
          <div v-if="form.sampel[k.kode].na" class="mt-3">
            <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">
              Alasan pengecualian
            </label>
            <input v-model="form.sampel[k.kode].ket" :class="isian"
                   placeholder="mis. auditi tidak memiliki gudang bahan peledak">
          </div>

          <div v-else class="grid gap-3 lg:grid-cols-3 mt-3">
            <div v-for="(label, kunci) in metode" :key="kunci">
              <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">
                {{ label }}
              </label>
              <textarea v-model="form.sampel[k.kode][kunci as 'dokumen']" rows="3" :class="isian"
                        placeholder="satu sampel per baris"></textarea>
            </div>
          </div>
        </article>

        <p v-if="!tampak.length" class="text-[12.5px] text-stone-500 py-8 text-center">
          Elemen ini tidak memiliki kriteria.
        </p>

        <!-- BUKAN WADAH FLEX, dan itu disengaja. `.eq-btn-utama` membawa
             `flex:1` supaya dua tombol berdampingan membagi barisnya rata.
             Di samping kalimat keterangan, aturan yang sama membuat
             tombolnya memuai sepanjang baris dan terbaca sebagai bilah.
             Di dalam wadah block, `flex:1` tidak berlaku sama sekali. -->
        <div class="pt-2">
          <button class="eq-btn-utama" :disabled="form.processing">
            {{ form.processing ? 'Menyimpan…' : 'Simpan Matriks' }}
          </button>
          <p class="text-[11.5px] text-stone-500 mt-2">
            Menyimpan seluruh elemen sekaligus, bukan hanya yang terlihat.
          </p>
        </div>
      </form>
    </section>
  </div>
</template>

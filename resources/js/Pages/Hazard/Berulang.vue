<script setup lang="ts">
/**
 * Analisa kecenderungan temuan berulang.
 *
 * Halaman Evaluasi Temuan menjawab "berapa banyak dan di mana". Yang di
 * sini menjawab pertanyaan yang berbeda: temuan mana yang TERUS KEMBALI
 * di tempat yang sama, dan apakah perbaikannya bertahan.
 *
 * Kolom KAMBUH berada sebelum kolom JUMLAH, dan itu disengaja. Lima
 * temuan sejenis pada satu bulan boleh jadi satu sapuan inspeksi; satu
 * temuan yang kembali sesudah dinyatakan selesai adalah perbaikan yang
 * gagal. Yang kedua lebih mendesak meski angkanya lebih kecil, dan
 * kolom yang lebih kiri dibaca lebih dulu.
 */
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import type { HalamanTemuanBerulang } from '../../types';
import DonatKategori from '../../Components/DonatKategori.vue';

const props = defineProps<HalamanTemuanBerulang>();

function ubahSaring(ubah: Record<string, string | number | null>) {
  router.get('/hazard/berulang', { ...props.saring, ...ubah },
             { preserveState: true, replace: true });
}

const NADA: Record<string, { latar: string; huruf: string; garis: string }> = {
  baik:      { latar: '#F0FDF4', huruf: '#15803D', garis: '#BBF7D0' },
  perhatian: { latar: '#FFFBEB', huruf: '#92400E', garis: '#FDE68A' },
  bahaya:    { latar: '#FEF2F2', huruf: '#B91C1C', garis: '#FECACA' },
  netral:    { latar: '#FAFAF9', huruf: '#57534E', garis: '#E7E5E4' },
};

const nada = (k: string) => NADA[k] ?? NADA.netral;

/* Garis bulanan diskalakan terhadap puncak SELURUH halaman, bukan
   terhadap barisnya sendiri. Diskalakan per baris, kelompok yang muncul
   sekali sebulan dan kelompok yang muncul sepuluh kali sebulan
   menggambar garis yang bentuknya sama persis. */
const puncak = computed(() =>
  Math.max(1, ...props.kelompok.flatMap((g) => g.bulanan.map((b) => b.nilai))));

function tinggiTitik(n: number): string {
  return n ? `${Math.max(3, (n / puncak.value) * 22)}px` : '1px';
}

/* Perusahaan hanya ditampilkan bila daftarnya memang memuat lebih
   dari satu. Ditampilkan selalu, ia menjadi kolom berisi satu nilai
   yang sama pada pengguna perusahaan tunggal, yang hanya memakan
   tempat pada tabel yang sudah berkolom sembilan. */
const banyakPerusahaan = computed(() => {
  const nama = new Set(props.kelompok.map((g) => g.perusahaan ?? '—'));
  return nama.size > 1;
});

const buka = ref<number | null>(null);

const pilih = 'ring-focus rounded-xl border border-stone-200 bg-white pl-3 pr-9 py-2 text-[12px] font-semibold text-stone-600';
</script>

<template>
  <Head title="Temuan Berulang" />

  <div class="space-y-5">

    <!-- ══════ saringan ══════ -->
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-4">
      <div class="flex flex-wrap items-end gap-3">
        <label class="grid gap-1">
          <span class="block text-[10.5px] font-bold uppercase tracking-wide text-stone-400">Sumber temuan</span>
          <select :class="pilih" :value="saring.sumber ?? ''"
                  @change="ubahSaring({ sumber: ($event.target as HTMLSelectElement).value })">
            <option value="">Hazard &amp; Inspeksi</option>
            <option v-for="s in opsi.sumber" :key="s" :value="s">{{ s }}</option>
          </select>
        </label>

        <label class="grid gap-1">
          <span class="block text-[10.5px] font-bold uppercase tracking-wide text-stone-400">Perusahaan</span>
          <select :class="pilih" :value="saring.perusahaan ?? ''"
                  @change="ubahSaring({ perusahaan: ($event.target as HTMLSelectElement).value })">
            <option value="">Semua perusahaan</option>
            <option v-for="p in opsi.perusahaan" :key="p.id" :value="p.id">{{ p.nama }}</option>
          </select>
        </label>

        <p class="text-[11.5px] text-stone-500 max-w-md leading-relaxed">
          Ditinjau <b class="num">{{ bulanTinjau }}</b> bulan ke belakang, dan sengaja tidak
          disaring per bulan: pengulangan hanya terlihat pada rentang panjang.
        </p>
      </div>
    </div>

    <!-- ══════ empat kartu ══════ -->
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <div v-for="k in kartu" :key="k.kunci" class="blg-kartu"
           :style="{ background: nada(k.nada).latar, borderColor: nada(k.nada).garis }">
        <span class="blg-label">{{ k.label }}</span>
        <p class="blg-angka num" :style="{ color: nada(k.nada).huruf }">
          {{ k.nilai }}<span v-if="k.satuan" class="blg-satuan">{{ k.satuan }}</span>
        </p>
        <p class="blg-ket">{{ k.ket }}</p>
      </div>
    </div>

    <!-- ══════ sorotan ══════ -->
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-3">Yang Perlu Diperhatikan</h3>
      <ul class="space-y-2">
        <li v-for="(s, i) in sorotan" :key="i" class="blg-sorot"
            :style="{ background: nada(s.nada).latar, borderColor: nada(s.nada).garis }">
          <span class="blg-titik" :style="{ background: nada(s.nada).huruf }"></span>
          <span class="text-[12.5px] leading-relaxed" :style="{ color: nada(s.nada).huruf }">
            {{ s.teks }}
          </span>
        </li>
      </ul>
    </div>

    <!-- ══════ hirarki pengendalian ══════ -->
    <div v-if="hirarki.total" class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[14px] font-bold text-cam-ink">Hirarki Pengendalian pada Temuan Berulang</h3>
      <p class="text-[11.5px] text-stone-400 mt-0.5 mb-4 max-w-2xl leading-relaxed">
        Inilah yang menjelaskan mengapa ia berulang. Administratif dan APD menuntut orang
        lebih berhati-hati, dan kehati-hatian tidak bertahan melewati pergantian regu.
        <br>
        Dihitung atas <b class="num">{{ hirarki.total }}</b> temuan yang mencatat hirarki
        pengendaliannya — butir inspeksi tidak mencatatnya, jadi ia memang tidak ikut,
        bukan hilang.
      </p>
      <DonatKategori :potong="hirarki.potong" satuan="temuan" />
    </div>

    <!-- ══════ tabel kelompok ══════ -->
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      <div class="px-5 py-4 border-b border-stone-100">
        <h3 class="text-[14px] font-bold text-cam-ink">Kelompok Temuan Berulang</h3>
        <p class="text-[11.5px] text-stone-400 mt-0.5 max-w-3xl leading-relaxed">
          Dikelompokkan menurut <b>lokasi dan perihal</b> — kategori bagi laporan bahaya,
          uraian butir bagi inspeksi. Diurutkan menurut kambuh, lalu jumlah bulan berbeda,
          baru jumlahnya: lima temuan dalam satu bulan boleh jadi satu sapuan inspeksi,
          sedangkan satu temuan yang kembali sesudah ditangani adalah perbaikan yang gagal.
        </p>
      </div>

      <!-- `relative` BUKAN hiasan.

           Sel pola bulanan memuat <span class="sr-only"> pembaca layar,
           dan .sr-only berposisi ABSOLUTE. Elemen absolute hanya
           terpotong oleh leluhur yang BERPOSISI — pembungkus
           overflow-x-auto yang static bukan blok penampungnya, jadi
           span itu melayang di posisi statisnya sejauh ~700px di dalam
           tabel selebar 922px dan MELEBARKAN DOKUMEN. Akibatnya seluruh
           halaman dapat digeser mendatar di layar ponsel — termasuk
           bilah atas dan judulnya — sementara tabelnya sendiri terlihat
           baik-baik saja. -->
      <div class="overflow-x-auto relative">
        <table class="w-full text-[12.5px]">
          <thead>
            <tr class="bg-stone-50 border-b border-stone-100">
              <th class="blg-th text-left">Lokasi &amp; Perihal</th>
              <th v-if="banyakPerusahaan" class="blg-th text-left">Perusahaan</th>
              <th class="blg-th text-left">Sumber</th>
              <th class="blg-th text-right">Kambuh</th>
              <th class="blg-th text-right">Bulan</th>
              <th class="blg-th text-right">Jumlah</th>
              <th class="blg-th text-right">Tinggi</th>
              <th class="blg-th text-right">Rentang</th>
              <th class="blg-th text-left">Pola 12 Bulan</th>
              <th class="blg-th text-right">Terakhir</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="(g, i) in kelompok" :key="i">
              <tr class="border-b border-stone-50 hover:bg-stone-50/60 cursor-pointer"
                  @click="buka = buka === i ? null : i">
                <td class="px-4 py-3">
                  <span class="block font-bold text-cam-ink">{{ g.perihal }}</span>
                  <span class="block text-[11px] text-stone-400 mt-0.5">{{ g.lokasi }}</span>
                </td>
                <td v-if="banyakPerusahaan" class="px-4 py-3 text-stone-500">
                  {{ g.perusahaan ?? '—' }}
                </td>
                <td class="px-4 py-3">
                  <span class="blg-sumber" :class="g.sumber === 'Hazard' ? 'blg-hz' : 'blg-in'">
                    {{ g.sumber }}
                  </span>
                </td>
                <td class="px-4 py-3 num text-right font-bold"
                    :class="g.kambuh ? 'text-red-700' : 'text-stone-300'">{{ g.kambuh || '—' }}</td>
                <td class="px-4 py-3 num text-right font-semibold"
                    :class="g.bulan >= 3 ? 'text-amber-700' : 'text-stone-500'">{{ g.bulan }}</td>
                <td class="px-4 py-3 num text-right font-semibold text-cam-ink">{{ g.jumlah }}</td>
                <td class="px-4 py-3 num text-right"
                    :class="g.tinggi ? 'text-red-600 font-semibold' : 'text-stone-300'">
                  {{ g.tinggi || '—' }}
                </td>
                <td class="px-4 py-3 num text-right text-stone-500 whitespace-nowrap">
                  {{ g.rentang === 0 ? 'sehari' : `${g.rentang} hr` }}
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-end gap-[2px] h-6" aria-hidden="true">
                    <span v-for="(b, j) in g.bulanan" :key="j" class="blg-titik-bulan"
                          :style="{ height: tinggiTitik(b.nilai),
                                    background: b.nilai ? '#DC6E00' : '#E7E5E4' }"></span>
                  </div>
                  <span class="sr-only">
                    {{ g.bulanan.map((b) => `${b.label} ${b.nilai}`).join(', ') }}
                  </span>
                </td>
                <td class="px-4 py-3 num text-right text-stone-500 whitespace-nowrap">{{ g.terakhir }}</td>
              </tr>

              <tr v-if="buka === i" class="border-b border-stone-100 bg-stone-50/70">
                <td :colspan="banyakPerusahaan ? 10 : 9" class="px-4 py-3">
                  <div class="grid gap-3 sm:grid-cols-3 text-[11.5px]">
                    <p><b class="text-stone-500">Pertama muncul</b><br>
                       <span class="num text-cam-ink">{{ g.pertama }}</span></p>
                    <p><b class="text-stone-500">Sudah ditangani</b><br>
                       <span class="num text-cam-ink">{{ g.selesai }} dari {{ g.jumlah }}</span></p>
                    <p><b class="text-stone-500">Contoh nomor temuan</b><br>
                       <span class="num text-cam-ink">{{ g.contoh.join(', ') || '—' }}</span></p>
                  </div>

                  <p v-if="Object.keys(g.hirarki).length" class="mt-3 text-[11.5px] text-stone-600">
                    <b class="text-stone-500">Hirarki pengendalian yang dipakai:</b>
                    <span v-for="(n, h) in g.hirarki" :key="h" class="ml-2">
                      {{ h }} <span class="num text-stone-400">({{ n }})</span>
                    </span>
                  </p>
                  <p v-else class="mt-3 text-[11.5px] text-stone-400">
                    Butir inspeksi tidak mencatat hirarki pengendalian — kolomnya memang
                    kosong, bukan datanya yang hilang.
                  </p>
                </td>
              </tr>
            </template>

            <tr v-if="!kelompok.length">
              <td :colspan="banyakPerusahaan ? 10 : 9"
                  class="px-4 py-12 text-center text-stone-400 text-[12.5px]">
                Tidak ada temuan yang berulang di lokasi dan perihal yang sama.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Berawalan blg- supaya tidak menabrak kelas global .eq-*. Kelas scoped
   yang senama dengan kelas global diam-diam mewarisi setiap sifat yang
   tidak ditulis ulang di sini. */
.blg-kartu { border: 1px solid; border-radius: 16px; padding: .85rem 1rem; }
.blg-label {
  display: block; font-size: 9.5px; font-weight: 800; letter-spacing: .04em;
  text-transform: uppercase; color: #78716C;
}
.blg-angka { font-size: 25px; font-weight: 800; line-height: 1.15; margin-top: .3rem; }
.blg-satuan { font-size: 11.5px; font-weight: 700; margin-left: .28rem; opacity: .72; }
.blg-ket { font-size: 11px; color: #78716C; margin-top: .2rem; line-height: 1.45; }

.blg-sorot {
  display: flex; align-items: flex-start; gap: .6rem;
  border: 1px solid; border-radius: 12px; padding: .6rem .8rem;
}
.blg-titik { width: 7px; height: 7px; border-radius: 999px; margin-top: .42rem; flex: none; }

.blg-th {
  font-weight: 700; color: #78716C; text-transform: uppercase;
  letter-spacing: .04em; font-size: 10px; padding: .625rem 1rem; white-space: nowrap;
}

.blg-sumber {
  font-size: 9.5px; font-weight: 800; padding: .12rem .45rem; border-radius: 999px;
}
.blg-hz { background: #FEF3C7; color: #92400E; }
.blg-in { background: #E0F2FE; color: #075985; }

.blg-titik-bulan { width: 5px; border-radius: 2px 2px 0 0; flex: none; }
</style>

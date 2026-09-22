<script setup lang="ts">
/**
 * Hazard Report — analitik dan capaian KPI.
 *
 * Seluruh angka datang jadi dari server, termasuk persentase capaian
 * dan kalimat sorotannya: pembagian terhadap target bernilai nol hanya
 * perlu dijaga di satu tempat, dan tabel yang menghitung sendiri cepat
 * atau lambat ada yang lupa menjaganya.
 *
 * Susunannya menjawab tiga pertanyaan berurutan, dari atas ke bawah:
 * bagaimana keadaannya (enam kartu), apa yang perlu dikerjakan
 * (sorotan), dan siapa yang mengerjakannya (golongan lalu pelapor).
 * Grafik berada di antaranya sebagai penjelas, bukan sebagai pembuka —
 * halaman yang dibuka dengan grafik menuntut orang menafsirkannya
 * sendiri sebelum diberi satu angka pun.
 */
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import type { HalamanAnalitikBahaya } from '../../types';
import DonatKategori from '../../Components/DonatKategori.vue';

const props = defineProps<HalamanAnalitikBahaya>();

const bulan = ref<string | null>(props.bulan);

function pilihBulan() {
  router.get('/hazard/analitik', bulan.value ? { bulan: bulan.value } : {}, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
}

/** Tinggi batang tren — murni penataan, jadi dihitung di sini. */
const TINGGI_TREN = 132;

function tinggi(nilai: number, maks: number): string {
  return nilai ? `${Math.max(5, (nilai / maks) * TINGGI_TREN)}px` : '2px';
}

/**
 * Tinggi satu pita di dalam batang bertumpuk.
 *
 * Diskalakan terhadap MAKS SELURUH TREN, bukan terhadap batangnya
 * sendiri: diskalakan per batang, setiap bulan akan setinggi penuh dan
 * grafiknya berhenti memperlihatkan bulan mana yang lebih ramai.
 */
function tinggiPita(nilai: number, maks: number): string {
  return nilai ? `${Math.max(3, (nilai / maks) * TINGGI_TREN)}px` : '0px';
}

/* Perusahaan hanya ditampilkan bila memang ada lebih dari satu di
   dalam daftarnya. Satu nama yang sama muncul tujuh kali tanpa kolom
   pembeda terbaca sebagai penggabungan yang gagal — padahal mereka
   memang tujuh orang berbeda di tujuh perusahaan. Ditampilkan selalu,
   ia menjadi kolom berisi satu nilai yang sama pada pengguna
   perusahaan tunggal, yang hanya memakan tempat. */
const banyakPerusahaan = computed(() => {
  const nama = new Set(props.pelapor.map((p) => p.perusahaan ?? '—'));
  return nama.size > 1;
});

const NADA: Record<string, { latar: string; huruf: string; garis: string }> = {
  baik:      { latar: '#F0FDF4', huruf: '#15803D', garis: '#BBF7D0' },
  perhatian: { latar: '#FFFBEB', huruf: '#92400E', garis: '#FDE68A' },
  bahaya:    { latar: '#FEF2F2', huruf: '#B91C1C', garis: '#FECACA' },
  netral:    { latar: '#FAFAF9', huruf: '#57534E', garis: '#E7E5E4' },
};

const nada = (k: string) => NADA[k] ?? NADA.netral;

const kepalaGolongan = ['Golongan', 'Orang', 'Target', 'Aktual', 'Capaian', 'Tercapai'];
const kananGolongan  = ['Orang', 'Target', 'Aktual', 'Tercapai'];

const semuaPelapor = ref(false);

const tampil = computed(() => (semuaPelapor.value ? props.pelapor : props.teratas));
</script>

<template>
  <Head title="Analitik & KPI" />

  <div class="space-y-5">

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-3 flex flex-wrap items-center gap-2">
      <span class="block text-[12.5px] font-semibold text-stone-500 px-1">Periode</span>
      <select v-model="bulan" @change="pilihBulan"
              class="ring-focus rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px]
                     font-semibold text-stone-600" aria-label="Bulan">
        <option :value="null">Semua bulan (akumulasi)</option>
        <option v-for="b in opsiBulan" :key="b.nilai" :value="b.nilai">{{ b.label }}</option>
      </select>

      <span class="text-[11.5px] text-stone-400 ml-auto">
        target dihitung atas <span class="num font-bold text-stone-600">{{ bulanAktif }}</span> bulan
      </span>

      <a :href="ekspor" class="eq-btn-lain" style="flex:none"
         title="Angka ringkas, capaian per golongan, dan capaian tiap pelapor">
        ⬇ Unduh Statistik (.csv)
      </a>
    </div>

    <!-- ══════ enam kartu ══════ -->
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
      <div v-for="k in kartu" :key="k.kunci" class="anl-kartu"
           :style="{ background: nada(k.nada).latar, borderColor: nada(k.nada).garis }">
        <span class="anl-label">{{ k.label }}</span>
        <p class="anl-angka num" :style="{ color: nada(k.nada).huruf }">
          {{ k.nilai }}<span v-if="k.satuan" class="anl-satuan">{{ k.satuan }}</span>
        </p>
        <p class="anl-ket">{{ k.ket }}</p>
      </div>
    </div>

    <!-- ══════ sorotan ══════ -->
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-3">Yang Perlu Diperhatikan</h3>
      <ul class="space-y-2">
        <li v-for="(s, i) in sorotan" :key="i" class="anl-sorot"
            :style="{ background: nada(s.nada).latar, borderColor: nada(s.nada).garis }">
          <span class="anl-titik" :style="{ background: nada(s.nada).huruf }"></span>
          <span class="text-[12.5px] leading-relaxed" :style="{ color: nada(s.nada).huruf }">
            {{ s.teks }}
          </span>
        </li>
      </ul>
    </div>

    <!-- ══════ donat sebaran ══════ -->
    <div class="grid gap-4 lg:grid-cols-2">
      <div v-for="d in donat" :key="d.judul"
           class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
        <h3 class="text-[13px] font-bold text-cam-ink mb-3">{{ d.judul }}</h3>
        <DonatKategori :potong="d.potong" satuan="laporan" />
      </div>
    </div>

    <!-- ══════ tren bertumpuk ══════ -->
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
        <h3 class="text-[14px] font-bold text-cam-ink">Tren Laporan 12 Bulan</h3>
        <div class="flex items-center gap-3">
          <span v-for="p in (tren[0]?.tumpuk ?? [])" :key="p.label"
                class="flex items-center gap-1.5 text-[11px] text-stone-500">
            <span class="h-2.5 w-2.5 rounded-full" :style="{ background: p.warna }"></span>
            {{ p.label }}
          </span>
        </div>
      </div>
      <p class="text-[11.5px] text-stone-400 mb-4">
        Seluruh laporan, tidak mengikuti saringan periode di atas.
      </p>

      <div class="flex items-end gap-1.5" :style="{ height: `${TINGGI_TREN + 34}px` }">
        <div v-for="(t, i) in tren" :key="i" class="flex-1 flex flex-col items-center gap-1.5">
          <span class="num text-[10.5px] font-bold text-stone-500">{{ t.nilai || '' }}</span>

          <!-- Bertumpuk: pita risiko tertinggi di atas, supaya yang
               paling perlu dilihat berada di puncak batangnya. -->
          <div v-if="t.tumpuk && t.nilai" class="w-full flex flex-col-reverse overflow-hidden rounded-t-lg">
            <div v-for="p in [...t.tumpuk].reverse()" :key="p.label"
                 :style="{ height: tinggiPita(p.nilai, t.maks), background: p.warna }">
              <span class="sr-only">{{ p.label }}: {{ p.nilai }}</span>
            </div>
          </div>
          <div v-else class="w-full rounded-t-lg bg-stone-200"
               :style="{ height: tinggi(t.nilai, t.maks) }"></div>

          <span class="text-[9px] text-stone-400">{{ t.label }}</span>
        </div>
      </div>
    </div>

    <!-- ══════ capaian per golongan ══════ -->
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      <div class="px-5 py-4 border-b border-stone-100">
        <h3 class="text-[14px] font-bold text-cam-ink">Capaian KPI per Golongan Jabatan</h3>
        <p class="text-[11.5px] text-stone-400 mt-0.5">
          Target: General Manager &amp; Manager 1 · Superintendent 3 · lainnya 4 laporan per bulan.
          Yang paling tertinggal berada di baris teratas.
        </p>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-[12.5px]">
          <thead>
            <tr class="bg-stone-50 border-b border-stone-100">
              <th v-for="h in kepalaGolongan" :key="h"
                  class="font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-2.5"
                  :class="kananGolongan.includes(h) ? 'text-right' : 'text-left'">{{ h }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="g in golongan" :key="g.nama" class="border-b border-stone-50 last:border-0">
              <td class="px-4 py-3 font-bold text-cam-ink">{{ g.nama }}</td>
              <td class="px-4 py-3 num text-right text-stone-500">{{ g.orang }}</td>
              <td class="px-4 py-3 num text-right text-stone-500">{{ g.target }}</td>
              <td class="px-4 py-3 num text-right font-semibold text-cam-ink">{{ g.aktual }}</td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <div class="flex-1 h-1.5 rounded-full bg-stone-100 overflow-hidden min-w-[60px]">
                    <div class="h-full rounded-full"
                         :class="g.pct >= 100 ? 'lime-gradient' : 'bg-amber-400'"
                         :style="{ width: Math.min(100, g.pct) + '%' }"></div>
                  </div>
                  <span class="num font-bold" :class="g.pct >= 100 ? 'text-cam-lime-deep' : 'text-amber-600'">
                    {{ g.pct }}%
                  </span>
                </div>
              </td>
              <td class="px-4 py-3 num text-right text-stone-500">{{ g.tercapai }}/{{ g.orang }}</td>
            </tr>
            <tr v-if="!golongan.length">
              <td colspan="6" class="px-4 py-10 text-center text-stone-400">Belum ada data.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ══════ sebaran ══════ -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div v-for="s in sebaran" :key="s.judul"
           class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
        <h3 class="text-[13px] font-bold text-cam-ink mb-3">{{ s.judul }}</h3>
        <div class="space-y-2">
          <div v-for="(b, i) in s.baris" :key="i">
            <div class="flex items-center justify-between text-[11.5px] mb-1">
              <span class="text-stone-600 truncate pr-2">{{ b.label }}</span>
              <span class="num font-bold text-stone-500">{{ b.nilai }}</span>
            </div>
            <div class="h-1.5 rounded-full bg-stone-100 overflow-hidden">
              <div class="h-full rounded-full lime-gradient"
                   :style="{ width: (b.nilai / s.maks) * 100 + '%' }"></div>
            </div>
          </div>
          <p v-if="!s.baris.length" class="text-[12px] text-stone-300">Belum ada data.</p>
        </div>
      </div>
    </div>

    <!-- ══════ pelapor ══════ -->
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      <div class="px-5 py-4 border-b border-stone-100 flex flex-wrap items-center justify-between gap-2">
        <div>
          <h3 class="text-[14px] font-bold text-cam-ink">
            {{ semuaPelapor ? 'Capaian per Pelapor' : 'Sepuluh Pelapor Teratas' }}
          </h3>
          <p class="text-[11.5px] text-stone-400 mt-0.5">
            Diurutkan menurut jumlah laporan, bukan capaiannya — target tiap golongan berbeda.
          </p>
        </div>

        <button v-if="pelapor.length > teratas.length" type="button" class="eq-btn-lain"
                style="flex:none" @click="semuaPelapor = !semuaPelapor">
          {{ semuaPelapor ? '▴ Ringkas ke sepuluh teratas' : `▾ Tampilkan seluruh ${pelapor.length} pelapor` }}
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-[12.5px]">
          <thead>
            <tr class="bg-stone-50 border-b border-stone-100">
              <th class="anl-th text-left">Nama</th>
              <th v-if="banyakPerusahaan" class="anl-th text-left">Perusahaan</th>
              <th class="anl-th text-left">Jabatan</th>
              <th class="anl-th text-left">Golongan</th>
              <th class="anl-th text-right">Target</th>
              <th class="anl-th text-right">Aktual</th>
              <th class="anl-th text-right">Capaian</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(o, i) in tampil" :key="i" class="border-b border-stone-50 last:border-0">
              <td class="px-4 py-2.5 font-semibold text-cam-ink">{{ o.nama }}</td>
              <td v-if="banyakPerusahaan" class="px-4 py-2.5 text-stone-500">
                {{ o.perusahaan ?? '—' }}
              </td>
              <td class="px-4 py-2.5 text-stone-500">{{ o.jabatan ?? '—' }}</td>
              <td class="px-4 py-2.5">
                <span class="text-[10px] font-bold bg-stone-100 text-stone-500 px-2 py-0.5 rounded-full">
                  {{ o.gol }}
                </span>
              </td>
              <td class="px-4 py-2.5 num text-right text-stone-500">{{ o.target }}</td>
              <td class="px-4 py-2.5 num text-right font-bold text-cam-ink">{{ o.aktual }}</td>
              <td class="px-4 py-2.5 num text-right font-bold"
                  :class="o.pct >= 100 ? 'text-cam-lime-deep' : 'text-amber-600'">{{ o.pct }}%</td>
            </tr>
            <tr v-if="!tampil.length">
              <td :colspan="banyakPerusahaan ? 7 : 6"
                  class="px-4 py-10 text-center text-stone-400">Belum ada data.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Berawalan anl- supaya tidak menabrak kelas global .eq-*. Kelas
   scoped yang senama dengan kelas global diam-diam mewarisi setiap
   sifat yang tidak ditulis ulang di sini — dan kegagalannya tidak
   memulangkan galat apa pun, hanya tata letak yang bergeser. */
.anl-kartu {
  border: 1px solid; border-radius: 16px; padding: .85rem 1rem;
}
.anl-label {
  display: block; font-size: 9.5px; font-weight: 800; letter-spacing: .04em;
  text-transform: uppercase; color: #78716C;
}
.anl-angka {
  font-size: 25px; font-weight: 800; line-height: 1.15; margin-top: .3rem;
}
.anl-satuan {
  font-size: 11.5px; font-weight: 700; margin-left: .28rem; opacity: .72;
}
.anl-ket {
  font-size: 11px; color: #78716C; margin-top: .2rem; line-height: 1.45;
}

.anl-sorot {
  display: flex; align-items: flex-start; gap: .6rem;
  border: 1px solid; border-radius: 12px; padding: .6rem .8rem;
}
.anl-titik {
  width: 7px; height: 7px; border-radius: 999px; margin-top: .42rem; flex: none;
}

.anl-th {
  font-weight: 700; color: #78716C; text-transform: uppercase;
  letter-spacing: .04em; font-size: 10px; padding: .625rem 1rem;
}
</style>

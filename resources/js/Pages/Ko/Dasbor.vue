<script setup lang="ts">
/**
 * Dasbor Keselamatan Operasi.
 *
 * Indeks KO adalah rerata lima sub-elemen (Kepmen ESDM 1827 K/2018 ·
 * Kepdirjen 185.K/2019). Yang menggantikannya di sini: lima kartu
 * berdampingan, masing-masing satu persen, tanpa satu pun yang
 * mengatakan mana yang menarik indeksnya ke bawah — padahal justru
 * itulah satu-satunya keputusan yang diambil dari halaman ini.
 *
 * BERAPA YANG TERUKUR SELALU DISEBUT. Sub-elemen tanpa data tidak
 * dihitung nol dan tidak dihitung seratus; ia tidak dihitung sama
 * sekali. Indeks 92% dari dua sub-elemen dan indeks 92% dari lima
 * adalah dua pernyataan yang sangat berbeda, dan yang pertama tidak
 * boleh terbaca seperti yang kedua — angka ini masuk ke laporan kepada
 * inspektur tambang.
 */
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import KartuGrafik from '../../Grafik/KartuGrafik.vue';
import Batang from '../../Grafik/Batang.vue';
import Donat from '../../Grafik/Donat.vue';
import Meter from '../../Grafik/Meter.vue';
import { KEADAAN } from '../../Grafik/warna';

const props = defineProps<{
  c: Record<string, any>;
  sub: Record<string, any>;
  peringatan: any[];
  objek: any[];
  aksiTerbuka: number;
  set: Record<string, any>;
}>();

/* ── indeks ── */

const indeks  = computed(() => props.sub?.indeks ?? null);
const terukur = computed(() => props.sub?.terukur ?? 0);

/**
 * Sub-elemen yang belum terukur disebut namanya, bukan sekadar
 * dihitung. "3 dari 5 terukur" memberi tahu ada yang kurang; menyebut
 * mana yang kurang memberi tahu apa yang harus dikerjakan.
 */
const belumTerukur = computed(() =>
  (props.sub?.items ?? []).filter((i: any) => i.pct === null).map((i: any) => i.nama));

/* ── lima sub-elemen ── */

const target = computed(() => Number(props.set?.ko_target_layak ?? 95));

/**
 * Sub-elemen digambar berdampingan pada satu sumbu 0–100, DIURUTKAN
 * apa adanya menurut nomor Kepmen — bukan menurut nilainya.
 *
 * Ini satu-satunya grafik di aplikasi ini yang sengaja tidak diurutkan
 * dari yang terbesar: nomor sub-elemennya adalah bagian dari
 * penamaannya, dan auditor mencarinya menurut nomor itu. Mengurutkan
 * ulang membuat "sub-elemen 3" berada di baris kelima.
 */
const subBatang = computed(() =>
  (props.sub?.items ?? [])
    .filter((i: any) => i.pct !== null)
    .map((i: any) => ({
      label: `${i.n}. ${i.nama}`,
      nilai: Number(i.pct),
      keadaan: (Number(i.pct) >= target.value ? 'baik'
        : Number(i.pct) >= target.value - 10 ? 'ingat'
        : Number(i.pct) >= target.value - 25 ? 'serius'
        : 'gawat') as 'baik' | 'ingat' | 'serius' | 'gawat',
    })));

/**
 * Yang paling menarik indeks ke bawah, di antara yang TERUKUR.
 * Sub-elemen kosong bukan sub-elemen terburuk — ia sub-elemen yang
 * belum dikerjakan, dan keduanya menuntut tindakan yang berbeda.
 */
const terlemah = computed(() => {
  const ada = (props.sub?.items ?? []).filter((i: any) => i.pct !== null);
  if (!ada.length) return null;

  return ada.reduce((a: any, b: any) => (b.pct < a.pct ? b : a));
});

/* ── sebaran kelayakan ── */

const WARNA_STATUS: Record<string, 'baik' | 'ingat' | 'gawat' | 'netral'> = {
  'Layak':            'baik',
  'Akan Jatuh Tempo': 'ingat',
  'Kadaluarsa':       'gawat',
  'Dalam Perbaikan':  'netral',
};

const sebaran = computed(() =>
  Object.entries((props.c?.byStat ?? {}) as Record<string, number>)
    .filter(([, n]) => n > 0)
    .map(([label, n]) => ({ label, nilai: n, keadaan: WARNA_STATUS[label] ?? 'netral' })));

/* ── peringatan ── */

/**
 * Diurutkan menurut mendesaknya, lalu dikelompokkan menurut sumbernya.
 * Daftar campur yang urutannya sembarang membuat sertifikat yang sudah
 * kadaluarsa berada di bawah pengaman yang baru perlu disetel.
 */
const peringatanUrut = computed(() =>
  [...props.peringatan].sort((a, b) => (a.pr - b.pr) || ((a.hari ?? 0) - (b.hari ?? 0))));

const warnaPeringatan = (pr: number) =>
  pr === 0 ? KEADAAN.gawat : pr === 1 ? KEADAAN.ingat : pr === 2 ? KEADAAN.serius : KEADAAN.netral;

const kartu = computed(() => [
  { label: 'Objek terdaftar', nilai: props.c?.total ?? 0,
    ket: 'sarana, prasarana, instalasi, peralatan' },
  { label: 'PM terlewat', nilai: props.c?.overdue ?? 0,
    ket: 'perawatan melewati jadwalnya',
    gawat: (props.c?.overdue ?? 0) > 0 },
  { label: 'Pengaman tidak berfungsi', nilai: (props.c?.pgTot ?? 0) - (props.c?.pgOk ?? 0),
    ket: `dari ${props.c?.pgTot ?? 0} perangkat terperiksa`,
    gawat: (props.c?.pgTot ?? 0) - (props.c?.pgOk ?? 0) > 0 },
  { label: 'Tindak lanjut terbuka', nilai: props.aksiTerbuka ?? 0,
    ket: 'belum selesai atau masih berjalan',
    gawat: (props.aksiTerbuka ?? 0) > 0 },
]);
</script>

<template>
  <div class="space-y-4">

    <!-- indeks dan angka pokok -->
    <section class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
      <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h3 class="text-[14px] font-bold text-cam-ink">Indeks Keselamatan Operasi</h3>
            <p class="text-[11.5px] text-stone-500 mt-0.5">
              Rerata lima sub-elemen · Kepmen ESDM 1827 K/2018
            </p>
          </div>

          <span v-if="sub?.level"
                class="text-[10.5px] font-bold px-2 py-1 rounded-full shrink-0 bg-stone-100 text-stone-600">
            {{ sub.level }}
          </span>
        </div>

        <div class="mt-4">
          <Meter :nilai="indeks" :target="target" :maks="100" satuan="%" />
        </div>

        <!-- Pernyataan yang membedakan indeks dari lima sub-elemen dan
             indeks dari dua. Tanpa ini keduanya terbaca sama. -->
        <p class="text-[11px] mt-3 leading-snug"
           :class="terukur === 5 ? 'text-stone-400' : 'text-amber-700'">
          <template v-if="terukur === 0">
            Belum ada satu pun sub-elemen yang terukur, jadi indeksnya belum ada —
            bukan nol.
          </template>
          <template v-else-if="terukur < 5">
            Dihitung dari <b>{{ terukur }} dari 5</b> sub-elemen.
            Yang belum terisi: {{ belumTerukur.join(', ') }}.
          </template>
          <template v-else>
            Kelima sub-elemen terukur.
          </template>
        </p>
      </div>

      <div class="grid gap-3 sm:grid-cols-2">
        <article v-for="k in kartu" :key="k.label"
                 class="bg-white rounded-2xl shadow-card border border-stone-100 p-4">
          <p class="text-[11px] font-semibold text-stone-500">{{ k.label }}</p>

          <p class="text-[22px] font-bold leading-none num mt-1.5"
             :style="{ color: k.gawat ? KEADAAN.gawat : '#292524' }">{{ k.nilai }}</p>

          <p class="text-[10.5px] text-stone-400 mt-1.5 leading-snug">{{ k.ket }}</p>
        </article>
      </div>
    </section>

    <!-- sub-elemen dan sebaran -->
    <section class="grid gap-4 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
      <KartuGrafik judul="Lima sub-elemen"
                   :catatan="terlemah
                     ? `Yang paling menarik indeks ke bawah: ${terlemah.nama} pada ${terlemah.pct}%.`
                     : 'Belum ada sub-elemen yang dapat diukur.'"
                   :angka="`target ${target}%`">
        <Batang :baris="subBatang" satuan="%" :maks-tetap="100" apa-adanya />

        <template #tabel>
          <table>
            <thead><tr><th>Sub-elemen</th><th>Capaian</th><th>Dasar hitungannya</th></tr></thead>
            <tbody>
              <tr v-for="i in (sub?.items ?? [])" :key="i.n">
                <td>{{ i.n }}. {{ i.nama }}</td>
                <td class="num">{{ i.pct === null ? 'belum terukur' : i.pct + '%' }}</td>
                <td style="text-align:left">{{ i.ket }}</td>
              </tr>
            </tbody>
          </table>
        </template>
      </KartuGrafik>

      <KartuGrafik judul="Sebaran kelayakan"
                   catatan="Menurut masa berlaku sertifikat dan keadaan operasinya."
                   :angka="`${c?.total ?? 0} objek`" :tinggi="170">
        <Donat :bagian="sebaran" :tengah="String(c?.total ?? 0)" tengah-label="objek" />

        <template #tabel>
          <table>
            <thead><tr><th>Status</th><th>Objek</th></tr></thead>
            <tbody>
              <tr v-for="(n, label) in (c?.byStat ?? {})" :key="label">
                <td>{{ label }}</td>
                <td class="num">{{ n }}</td>
              </tr>
            </tbody>
          </table>
        </template>
      </KartuGrafik>
    </section>

    <!-- peringatan -->
    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <div class="flex items-center justify-between gap-3 mb-3">
        <div>
          <h3 class="text-[14px] font-bold text-cam-ink">Peringatan yang perlu ditindak</h3>
          <p class="text-[11.5px] text-stone-500 mt-0.5">
            Diurutkan dari yang paling mendesak, bukan dari yang paling baru.
          </p>
        </div>

        <Link href="/ko/tindak" class="text-[11px] font-semibold text-cam-lime-deep shrink-0 py-1.5 -my-1.5">
          Tindak lanjut
        </Link>
      </div>

      <ul v-if="peringatanUrut.length" class="divide-y divide-stone-100">
        <li v-for="(p, i) in peringatanUrut" :key="i" class="py-2.5 flex items-center gap-3">
          <span class="w-1.5 h-1.5 rounded-full shrink-0"
                :style="{ background: warnaPeringatan(p.pr) }"></span>

          <span class="text-[12px] font-bold text-cam-ink shrink-0 w-24 truncate">
            {{ p.objek?.kode ?? '—' }}
          </span>

          <span class="text-[12.5px] text-stone-600 min-w-0 flex-1 truncate">{{ p.jenis }}</span>

          <span class="text-[11px] text-stone-400 shrink-0 hidden sm:block">{{ p.sumber }}</span>

          <!-- Hari negatif berarti sudah lewat; ditulis begitu, bukan
               sebagai angka minus yang harus ditafsirkan sendiri. -->
          <span class="text-[11px] font-semibold shrink-0 w-28 text-right"
                :style="{ color: warnaPeringatan(p.pr) }">
            <template v-if="p.hari === null || p.hari === undefined">—</template>
            <template v-else-if="p.hari < 0">lewat {{ Math.abs(p.hari) }} hari</template>
            <template v-else>{{ p.hari }} hari lagi</template>
          </span>
        </li>
      </ul>

      <p v-else class="text-[12px] text-stone-400 py-4 text-center">
        Tidak ada peringatan terbuka pada lingkup ini.
      </p>
    </section>
  </div>
</template>

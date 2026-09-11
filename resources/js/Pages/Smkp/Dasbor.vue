<script setup lang="ts">
/**
 * Performa SMKP satu perusahaan, dari tahun ke tahun.
 *
 * HALAMAN AWAL MODUL, dan itu keputusan yang disengaja. Yang dibuka
 * manajemen bukan daftar periode audit melainkan pertanyaan "apakah
 * kami membaik". Daftar periode menjawab "audit mana yang ada" —
 * pertanyaan yang hanya ditanyakan orang yang sudah tahu jawabannya.
 *
 * TIGA HAL YANG SENGAJA DIKERJAKAN BEGINI:
 *
 * 1. YANG DIBANDINGKAN CAPAIAN, BUKAN POIN. Pembagi berubah antar tahun
 *    — butir yang tahun lalu N/A dapat menjadi berlaku ketika tambang
 *    membuka bagian bawah tanah. Membandingkan poin mutlak di situ
 *    melaporkan penurunan yang sesungguhnya perluasan lingkup.
 *
 * 2. LUBANG DATA DIGAMBAR PUTUS, bukan nol. Elemen yang seluruh
 *    butirnya N/A pada sebuah tahun tidak "jatuh ke nol"; ia tidak
 *    dinilai. Garis yang menyambungnya lewat nol menggambarkan
 *    keruntuhan yang tidak pernah terjadi.
 *
 * 3. TIAP GRAFIK MENYEDIAKAN TABELNYA. Tiga warna kategori yang dipakai
 *    di sini kontrasnya di bawah 3:1 pada latar putih — sah dipakai
 *    hanya bila angkanya juga terbaca tanpa bergantung pada warna.
 *    KartuGrafik yang menyediakannya, dan tombolnya tidak dapat
 *    dimatikan pemanggil.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import KartuGrafik from '../../Grafik/KartuGrafik.vue';
import Garis from '../../Grafik/Garis.vue';
import Batang from '../../Grafik/Batang.vue';
import Donat from '../../Grafik/Donat.vue';
import Meter from '../../Grafik/Meter.vue';
import { propHalaman } from '../../halaman';
import { KEADAAN, warnaDeret } from '../../Grafik/warna';

const props = propHalaman();

const tahun   = computed<string[]>(() => (props.tahun ?? []) as string[]);
const akhir   = computed<number[]>(() => (props.akhir ?? []) as number[]);
const periode = computed<any[]>(() => (props.periode ?? []) as any[]);
const elemen  = computed<any[]>(() => (props.elemen ?? []) as any[]);

const terakhir = computed(() => periode.value[periode.value.length - 1] ?? null);
const banding  = computed<any>(() => props.banding ?? null);

const angka = (v: unknown) =>
  typeof v === 'number' ? v.toLocaleString('id-ID', { maximumFractionDigits: 2 }) : (v ?? '—');

/** Hijau naik, merah turun, abu tidak dapat dibandingkan. */
function nadaSelisih(d: number | null | undefined): string {
  if (d === null || d === undefined || d === 0) return KEADAAN.netral;
  return d > 0 ? KEADAAN.baik : KEADAAN.gawat;
}

const tandaSelisih = (d: number | null | undefined) =>
  d === null || d === undefined ? '' : d > 0 ? `+${angka(d)}` : angka(d);

/* ---------- kartu ringkas ---------- */

const kartu = computed(() => {
  const t = terakhir.value;
  const b = banding.value;

  const naik  = b?.ada ? Object.values(b.elemen).filter((e: any) => (e.selisih ?? 0) > 0).length : 0;
  const turun = b?.ada ? Object.values(b.elemen).filter((e: any) => (e.selisih ?? 0) < 0).length : 0;

  return [
    {
      label: 'Nilai akhir terakhir',
      nilai: t ? `${angka(t.skor)}%` : '—',
      ket: t ? `${t.tingkat?.label ?? '—'} · audit ${t.tahun}` : 'belum ada audit',
      warna: null as string | null,
    },
    {
      label: b?.ada ? `Selisih dari ${b.tahun}` : 'Selisih antar tahun',
      nilai: b?.ada && b.akhir?.selisih !== null ? tandaSelisih(b.akhir.selisih) : '—',
      ket: b?.ada ? `dari ${angka(b.akhir?.lalu)}% menjadi ${angka(b.akhir?.kini)}%` : 'perlu dua audit atau lebih',
      warna: b?.ada ? nadaSelisih(b.akhir?.selisih) : null,
    },
    {
      label: 'Elemen membaik',
      nilai: `${naik}`,
      ket: `${turun} elemen menurun · dari ${elemen.value.length} elemen`,
      warna: naik > turun ? KEADAAN.baik : turun > naik ? KEADAAN.gawat : null,
    },
    {
      label: 'Ketidaksesuaian terakhir',
      nilai: t ? `${t.mayor + t.minor}` : '—',
      ket: t ? `${t.mayor} mayor · ${t.minor} minor · ${t.obs} observasi` : 'belum ada audit',
      warna: t && t.mayor > 0 ? KEADAAN.gawat : null,
    },
  ];
});

/* ---------- deret grafik ---------- */

const deretAkhir = computed(() => [
  { nama: 'Nilai akhir', nilai: akhir.value.map((v) => (v === null ? null : v)) },
]);

/* Tujuh elemen, tujuh deret — tepat di bawah batas delapan yang dijaga
   palet. Urutannya mengikuti urutan elemen pada acuan, bukan
   peringkatnya: warna mengikuti ENTITASNYA, dan warna yang berpindah
   saat peringkat berubah membuat pembacanya salah membandingkan. */
const deretElemen = computed(() =>
  elemen.value.map((e: any, i: number) => ({
    nama: `${e.kode} · ${e.nama}`,
    nilai: e.nilai,
    warna: warnaDeret(i),
  })));

/** Perubahan capaian per elemen — yang turun lebih dahulu. */
const perubahanElemen = computed(() => {
  const b = banding.value;
  if (!b?.ada) return [];

  return Object.entries(b.elemen)
    .map(([kode, e]: [string, any]) => ({
      label: `${kode} · ${e.nama}`,
      nilai: e.selisih ?? 0,
      keadaan: (e.selisih ?? 0) < 0 ? 'gawat' : (e.selisih ?? 0) > 0 ? 'baik' : 'netral',
    }))
    .sort((a, b2) => a.nilai - b2.nilai);
});

const sebaranTemuan = computed(() => {
  const t = terakhir.value;
  if (!t) return [];

  return [
    { label: 'Mayor',     nilai: t.mayor, keadaan: 'gawat' as const },
    { label: 'Minor',     nilai: t.minor, keadaan: 'ingat' as const },
    { label: 'Observasi', nilai: t.obs,   keadaan: 'netral' as const },
  ].filter((b) => b.nilai > 0);
});

/* ---------- pemilih perusahaan ---------- */

function gantiPerusahaan(ev: Event) {
  const id = (ev.target as HTMLSelectElement).value;
  router.get('/smkp/dasbor', id ? { perusahaan: id } : {}, { preserveScroll: true });
}

const adaData = computed(() => periode.value.length > 0);
</script>

<template>
  <Head title="Performa SMKP" />

  <div class="max-w-[1400px] mx-auto space-y-5">

    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-cam-ink">Performa SMKP</h2>
        <p class="text-[12.5px] text-stone-500 mt-1">
          <template v-if="props.perusahaan?.name">{{ props.perusahaan.name }} · </template>
          penerapan sistem manajemen keselamatan pertambangan dari tahun ke tahun.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <label v-if="(props.pilihan ?? []).length" class="text-[11.5px] font-semibold text-stone-600">
          Perusahaan
          <select class="ml-2 rounded-lg border-stone-200 text-[12px]"
                  :value="props.perusahaan?.id ?? ''" @change="gantiPerusahaan">
            <option value="">Tanpa perusahaan</option>
            <option v-for="c in props.pilihan" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </label>

        <Link href="/smkp" class="eq-btn-lain">Daftar periode</Link>
        <Link v-if="props.terbaru" :href="`/smkp/${props.terbaru.id}/penilaian`" class="eq-btn-utama">
          Lanjutkan audit {{ props.terbaru.tahun }}
        </Link>
        <Link v-else href="/smkp/buat" class="eq-btn-utama">Buat periode audit</Link>
      </div>
    </section>

    <p v-if="!adaData"
       class="rounded-2xl bg-white border border-stone-100 shadow-card p-10 text-center text-[13px] text-stone-500">
      Belum ada periode audit untuk perusahaan ini. Tren antar tahun muncul setelah
      audit pertama dinilai.
    </p>

    <template v-else>

      <!-- ══════════ angka yang dibaca lebih dulu ══════════ -->
      <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <article v-for="k in kartu" :key="k.label"
                 class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
          <p class="text-[10px] uppercase tracking-wider font-bold text-stone-400">{{ k.label }}</p>
          <strong class="block text-[26px] leading-none mt-1.5 num"
                  :style="k.warna ? { color: k.warna } : undefined">{{ k.nilai }}</strong>
          <p class="text-[11px] text-stone-500 mt-1.5 leading-snug">{{ k.ket }}</p>
        </article>
      </section>

      <!-- ══════════ tren nilai akhir ══════════ -->
      <div class="grid gap-4 lg:grid-cols-[1.4fr_.6fr] items-start">
        <KartuGrafik
          judul="Nilai akhir audit tiap tahun"
          :catatan="`Skala 100, dinormalkan memakai bobot elemen yang benar-benar terpakai — sehingga tetap sebanding meski lingkup auditnya berubah.`"
          :angka="terakhir ? `${angka(terakhir.skor)}% pada ${terakhir.tahun}` : null"
          :tinggi="200">
          <Garis :label="tahun" :deret="deretAkhir" satuan="%" bidang :tinggi="200" />

          <template #tabel>
            <table>
              <thead>
                <tr><th>Tahun</th><th>Nilai akhir</th><th>Tingkat penerapan</th><th>Butir dinilai</th></tr>
              </thead>
              <tbody>
                <tr v-for="p in periode" :key="p.id">
                  <td>{{ p.tahun }}</td>
                  <td class="num">{{ angka(p.skor) }}%</td>
                  <td>{{ p.tingkat?.label ?? '—' }}</td>
                  <td class="num">{{ p.dinilai }} / {{ p.berlaku }}</td>
                </tr>
              </tbody>
            </table>
          </template>
        </KartuGrafik>

        <KartuGrafik
          judul="Tingkat penerapan terakhir"
          catatan="Meter ini membaca nilai akhir audit terbaru terhadap skala 100."
          :angka="terakhir?.tingkat?.label ?? null"
          :tinggi="200">
          <Meter :nilai="terakhir?.skor ?? null" :maks="100" satuan="%"
                 :target="banding?.ada ? banding.akhir?.lalu : null" />

          <template #tabel>
            <table>
              <thead><tr><th>Tingkat</th><th>Nilai minimum</th></tr></thead>
              <tbody>
                <tr v-for="t in (props.tingkat ?? [])" :key="t.label">
                  <td>{{ t.label }}</td>
                  <td class="num">{{ t.min }}</td>
                </tr>
              </tbody>
            </table>
          </template>
        </KartuGrafik>
      </div>

      <!-- ══════════ per elemen ══════════ -->
      <KartuGrafik
        judul="Capaian tiap elemen dari tahun ke tahun"
        catatan="Capaian, bukan poin: pembagi berubah ketika butir berpindah antara berlaku dan tidak berlaku, dan poin mutlak akan melaporkan perluasan lingkup sebagai penurunan. Tahun yang seluruh butirnya tidak berlaku digambar putus, bukan nol."
        :tinggi="240">
        <Garis :label="tahun" :deret="deretElemen" satuan="%" :tinggi="240" />

        <template #tabel>
          <table>
            <thead>
              <tr>
                <th>Elemen</th>
                <th>Bobot</th>
                <th v-for="t in tahun" :key="t">{{ t }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="e in elemen" :key="e.kode">
                <td>{{ e.kode }} · {{ e.nama }}</td>
                <td class="num">{{ e.bobot }}%</td>
                <td v-for="(v, i) in e.nilai" :key="i" class="num">
                  {{ v === null ? '—' : `${angka(v)}%` }}
                </td>
              </tr>
            </tbody>
          </table>
        </template>
      </KartuGrafik>

      <div class="grid gap-4 lg:grid-cols-2 items-start">
        <KartuGrafik
          v-if="banding?.ada"
          judul="Perubahan tiap elemen"
          :catatan="`Selisih capaian terhadap audit ${banding.tahun}, dalam poin persen. Yang menurun diurutkan lebih dahulu — itu pertanyaan pertama pada audit ulangan.`"
          :tinggi="220">
          <Batang :baris="perubahanElemen" satuan=" poin" />

          <template #tabel>
            <table>
              <thead>
                <tr><th>Elemen</th><th>{{ banding.tahun }}</th><th>{{ banding.tahunKini }}</th><th>Selisih</th></tr>
              </thead>
              <tbody>
                <tr v-for="(e, kode) in banding.elemen" :key="kode">
                  <td>{{ kode }} · {{ e.nama }}</td>
                  <td class="num">{{ e.lalu === null ? '—' : `${angka(e.lalu)}%` }}</td>
                  <td class="num">{{ angka(e.kini) }}%</td>
                  <td class="num">{{ e.selisih === null ? '—' : tandaSelisih(e.selisih) }}</td>
                </tr>
              </tbody>
            </table>
          </template>
        </KartuGrafik>

        <KartuGrafik
          judul="Ketidaksesuaian audit terakhir"
          :catatan="`Kategori diturunkan dari nilai capaian sub-elemen, bukan dipilih auditor. Peluang perbaikan (OFI) tidak termasuk di sini — ia bukan ketidaksesuaian.`"
          :angka="terakhir ? `audit ${terakhir.tahun}` : null"
          :tinggi="220">
          <Donat v-if="sebaranTemuan.length" :bagian="sebaranTemuan"
                 :tengah="String((terakhir?.mayor ?? 0) + (terakhir?.minor ?? 0) + (terakhir?.obs ?? 0))"
                 tengah-label="temuan" />

          <p v-else class="text-center text-[12px] text-stone-400 py-12">
            Tidak ada ketidaksesuaian tercatat pada audit terakhir.
          </p>

          <template #tabel>
            <table>
              <thead><tr><th>Tahun</th><th>Mayor</th><th>Minor</th><th>Observasi</th><th>Jumlah</th></tr></thead>
              <tbody>
                <tr v-for="p in periode" :key="p.id">
                  <td>{{ p.tahun }}</td>
                  <td class="num">{{ p.mayor }}</td>
                  <td class="num">{{ p.minor }}</td>
                  <td class="num">{{ p.obs }}</td>
                  <td class="num">{{ p.mayor + p.minor + p.obs }}</td>
                </tr>
              </tbody>
            </table>
          </template>
        </KartuGrafik>
      </div>

      <!-- ══════════ konsistensi sub-elemen ══════════ -->
      <section v-if="props.konsistensi" class="grid gap-3 lg:grid-cols-3">
        <article v-for="g in [
                   { kunci: 'turun',        judul: `Turun dari ${banding?.tahun}`, warna: KEADAAN.gawat,
                     ket: 'Sub-elemen yang capaiannya lebih rendah daripada audit sebelumnya.' },
                   { kunci: 'tetap_rendah', judul: 'Rendah dua tahun berturut', warna: KEADAAN.serius,
                     ket: 'Di bawah ambang mayor pada kedua audit — tidak memburuk, tetapi tidak pernah diperbaiki.' },
                   { kunci: 'tetap_baik',   judul: 'Sempurna dua tahun berturut', warna: KEADAAN.baik,
                     ket: 'Bahan lembar peluang perbaikan (OFI).' },
                 ]" :key="g.kunci"
                 class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-4 py-3 border-b border-stone-100">
            <h3 class="text-[12.5px] font-bold flex items-center gap-2">
              <i class="titik" :style="{ background: g.warna }"></i>
              {{ g.judul }}
              <span class="font-normal text-stone-400">
                {{ (props.konsistensi as any)[g.kunci]?.length ?? 0 }}
              </span>
            </h3>
            <p class="text-[10.5px] text-stone-500 mt-0.5 leading-snug">{{ g.ket }}</p>
          </header>

          <ul v-if="(props.konsistensi as any)[g.kunci]?.length"
              class="divide-y divide-stone-100 max-h-60 overflow-y-auto">
            <li v-for="b in (props.konsistensi as any)[g.kunci].slice(0, 14)" :key="b.kode"
                class="px-4 py-2 flex items-baseline gap-2 text-[11.5px]">
              <b class="shrink-0">{{ b.kode }}</b>
              <span class="min-w-0 flex-1 truncate text-stone-600" :title="b.nama">{{ b.nama }}</span>
              <span class="shrink-0 num text-stone-400">{{ angka(b.lalu) }}%</span>
              <span class="shrink-0 text-stone-300">→</span>
              <span class="shrink-0 num font-bold" :style="{ color: g.warna }">{{ angka(b.kini) }}%</span>
            </li>
          </ul>

          <p v-else class="px-4 py-8 text-center text-[11.5px] text-stone-400">Tidak ada.</p>
        </article>
      </section>

      <!-- ══════════ daftar periode ══════════ -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <header class="px-5 py-3.5 border-b border-stone-100">
          <h3 class="text-[13.5px] font-bold text-cam-ink">
            Periode audit <span class="font-normal text-stone-400">| {{ periode.length }} tahun</span>
          </h3>
          <p class="text-[11px] text-stone-500 mt-0.5">
            Urut tahun menaik, sama dengan sumbu grafik di atas.
          </p>
        </header>

        <div class="overflow-x-auto">
          <table class="min-w-full text-left text-[11.5px]">
            <thead>
              <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
                <th class="px-4 py-2 font-semibold">Tahun</th>
                <th class="px-4 py-2 font-semibold whitespace-nowrap text-right">Nilai akhir</th>
                <th class="px-4 py-2 font-semibold whitespace-nowrap">Tingkat penerapan</th>
                <th class="px-4 py-2 font-semibold whitespace-nowrap text-right">Butir dinilai</th>
                <th class="px-4 py-2 font-semibold whitespace-nowrap text-right">Mayor</th>
                <th class="px-4 py-2 font-semibold whitespace-nowrap text-right">Minor</th>
                <th class="px-4 py-2 font-semibold whitespace-nowrap">Status</th>
                <th class="px-4 py-2 font-semibold"></th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="p in periode" :key="p.id" class="border-b border-stone-100 hover:bg-stone-50/60">
                <td class="px-4 py-2.5 num font-bold">{{ p.tahun }}</td>
                <td class="px-4 py-2.5 num text-right">{{ angka(p.skor) }}%</td>
                <td class="px-4 py-2.5 whitespace-nowrap">{{ p.tingkat?.label ?? '—' }}</td>
                <td class="px-4 py-2.5 num text-right">{{ p.dinilai }} / {{ p.berlaku }}</td>
                <td class="px-4 py-2.5 num text-right"
                    :style="p.mayor > 0 ? { color: KEADAAN.gawat } : undefined">{{ p.mayor }}</td>
                <td class="px-4 py-2.5 num text-right">{{ p.minor }}</td>
                <td class="px-4 py-2.5 capitalize whitespace-nowrap text-stone-500">{{ p.status }}</td>
                <td class="px-4 py-2.5 whitespace-nowrap">
                  <Link :href="`/smkp/${p.id}`" class="font-semibold text-cam-lime-deep hover:underline">
                    Buka
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </div>
</template>

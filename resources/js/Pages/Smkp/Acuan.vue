<script setup lang="ts">
/**
 * Acuan kriteria Kepdirjen 185.K/37.04/DJB/2019 — dapat ditelusuri.
 *
 * Sebelumnya halaman ini tujuh kartu berisi nama elemen, bobotnya, dan
 * jumlah sub-elemennya: tiga angka yang sudah diketahui siapa pun yang
 * membuka form penilaian. Yang sesungguhnya dicari orang di acuan
 * adalah bunyi SATU BUTIR — "apa persisnya yang dituntut III.12.4, dan
 * apa bedanya nilai 2 dari nilai 3". Pertanyaan itu tidak terjawab oleh
 * daftar yang berhenti di tingkat elemen, dan yang menjawabnya selama
 * ini adalah salinan PDF lampiran di luar aplikasi.
 *
 * DUA HAL YANG SENGAJA DIKERJAKAN BEGINI:
 *
 * 1. BUNYI RUBRIK DIMUAT SAAT DIBUKA, bukan ikut dikirim bersama
 *    halaman. Seluruhnya sekitar 260 ribu aksara — lebih besar daripada
 *    seluruh sisa halaman — dan sebagian besar tidak pernah dibaca pada
 *    satu kunjungan. Alamat yang dipakai sama dengan yang dipakai form
 *    penilaian, sehingga tidak ada dua sumber untuk teks yang sama.
 *
 * 2. NILAI AUDIT BERJALAN IKUT DISANDINGKAN bila ada. Acuan yang dibaca
 *    sambil menilai lebih berguna daripada acuan yang dibaca sendirian:
 *    yang dicari auditor adalah butir yang BELUM ia nilai, dan itu tidak
 *    terlihat pada daftar tanpa angka.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { propHalaman } from '../../halaman';
import { KEADAAN } from '../../Grafik/warna';

const props = propHalaman();

type Bunyi = { nilai: number; ket: string; ada: boolean; persen: number; label: string; kategori: any };

const elemen = computed<any[]>(() => (props.elemen ?? []) as any[]);

/* ---------- penelusuran ---------- */

const cari        = ref('');
const elemenAktif = ref('semua');
const hanyaBelum  = ref(false);

const tampil = computed<any[]>(() => {
  const q = cari.value.trim().toLowerCase();

  const lolosButir = (b: any) => {
    if (hanyaBelum.value && b.nilai !== null && b.nilai !== undefined) return false;
    if (!q) return true;

    return `${b.kode} ${b.nama}`.toLowerCase().includes(q);
  };

  return elemen.value
    .filter((e: any) => elemenAktif.value === 'semua' || e.kode === elemenAktif.value)
    .map((e: any) => ({
      ...e,
      sub: e.sub
        .map((s: any) => {
          /* Pencarian yang cocok pada NAMA SUB-ELEMEN menahan seluruh
             butirnya, bukan menyaringnya habis. Mencari "manajemen
             risiko" lalu memperoleh judul tanpa satu pun pertanyaan di
             bawahnya adalah hasil yang secara teknis benar dan tidak
             berguna sama sekali. */
          const cocokSub = q && `${s.kode} ${s.nama}`.toLowerCase().includes(q);

          return { ...s, butir: cocokSub ? s.butir.filter((b: any) => !hanyaBelum.value || b.nilai === null) : s.butir.filter(lolosButir) };
        })
        .filter((s: any) => s.butir.length > 0),
    }))
    .filter((e: any) => e.sub.length > 0);
});

const jumlahTampil = computed(() =>
  tampil.value.reduce((n: number, e: any) => n + e.sub.reduce((m: number, s: any) => m + s.butir.length, 0), 0));

/* ---------- bunyi rubrik, diambil saat butirnya dibuka ---------- */

const terbuka = reactive<Record<string, boolean>>({});
const bunyi   = reactive<Record<string, Bunyi[]>>({});
const memuat  = ref(0);

async function ambil(kode: string[]) {
  const perlu = [...new Set(kode)].filter((k) => !bunyi[k]);
  if (!perlu.length) return;

  memuat.value++;
  try {
    /* Dipotong per 60 kode: sakelar "buka semua" dapat meminta seratus
       sekaligus, dan satu alamat sepanjang itu tidak dijamin dilayani. */
    for (let i = 0; i < perlu.length; i += 60) {
      const potong = perlu.slice(i, i + 60);
      const r = await fetch(`${props.urlRubrik}?butir=${encodeURIComponent(potong.join(','))}`,
                           { headers: { Accept: 'application/json' } });
      if (!r.ok) throw new Error(String(r.status));

      const isi = await r.json();
      for (const [k, v] of Object.entries(isi.tangga ?? {})) bunyi[k] = v as Bunyi[];

      /* Butir yang tidak dijawab ditandai kosong supaya tidak diminta
         berulang setiap kali panelnya digambar ulang. */
      for (const k of potong) bunyi[k] ??= [];
    }
  } catch {
    for (const k of perlu) bunyi[k] ??= [];
  } finally {
    memuat.value--;
  }
}

function buka(b: any) {
  terbuka[b.kode] = !terbuka[b.kode];
  if (terbuka[b.kode]) ambil([b.kode]);
}

const semuaTerbuka = ref(false);

watch([semuaTerbuka, tampil], () => {
  if (!semuaTerbuka.value) return;
  ambil(tampil.value.flatMap((e: any) => e.sub.flatMap((s: any) => s.butir.map((b: any) => b.kode))));
});

const tampilkan = (b: any) => semuaTerbuka.value || !!terbuka[b.kode];
const siap      = (b: any) => Array.isArray(bunyi[b.kode]);

/* ---------- warna ---------- */

function warnaCapaian(p: number | null): string {
  if (p === null || p === undefined) return KEADAAN.netral;
  if (p >= 100) return KEADAAN.baik;
  if (p >= 50)  return KEADAAN.ingat;
  return KEADAAN.gawat;
}

function gantiAudit(ev: Event) {
  const id = (ev.target as HTMLSelectElement).value;
  router.get('/smkp/acuan', id ? { audit: id } : {}, { preserveState: false, preserveScroll: true });
}

const angka = (v: unknown) =>
  typeof v === 'number' ? v.toLocaleString('id-ID', { maximumFractionDigits: 1 }) : (v ?? '—');
</script>

<template>
  <Head title="Acuan Kriteria SMKP" />

  <div class="max-w-[1400px] mx-auto space-y-5">

    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-cam-ink">Acuan Kriteria SMKP</h2>
        <p class="text-[12.5px] text-stone-500 mt-1">{{ props.meta?.basis }}</p>
      </div>

      <label v-if="(props.daftarAudit ?? []).length" class="text-[11.5px] font-semibold text-stone-600">
        Sandingkan nilai audit
        <select class="ml-2 rounded-lg border-stone-200 text-[12px]"
                :value="props.audit?.id ?? ''" @change="gantiAudit">
          <option value="">Tanpa nilai</option>
          <option v-for="a in props.daftarAudit" :key="a.id" :value="a.id">
            {{ a.tahun }}<template v-if="a.judul"> — {{ a.judul }}</template>
          </option>
        </select>
      </label>
    </section>

    <section class="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
      <article v-for="k in [
                 { label: 'Elemen',   nilai: props.ringkas?.elemen, ket: 'tujuh elemen SMKP' },
                 { label: 'Sub-elemen', nilai: props.ringkas?.sub,  ket: 'satuan temuan yang sah' },
                 { label: 'Butir kriteria', nilai: props.ringkas?.butir, ket: 'yang benar-benar dinilai' },
                 { label: 'Nilai penuh', nilai: props.ringkas?.nilai, ket: 'bila seluruh butir berlaku' },
                 { label: 'Rubrik lengkap', nilai: props.ringkas?.rubrik, ket: 'berbunyi sampai nilai maksimum' },
                 { label: 'Rubrik tanggung', nilai: props.ringkas?.tanggung, ket: 'lampiran berhenti sebelum maksimum' },
               ]" :key="k.label"
               class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
        <p class="text-[10px] uppercase tracking-wider font-bold text-stone-400">{{ k.label }}</p>
        <strong class="block text-xl mt-1">{{ k.nilai ?? '—' }}</strong>
        <p class="text-[11px] text-stone-500 mt-1 leading-snug">{{ k.ket }}</p>
      </article>
    </section>

    <!-- ══════════ penelusuran ══════════ -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 space-y-3">
      <div class="flex flex-wrap items-center gap-2">
        <span class="text-[11px] uppercase tracking-wider font-bold text-stone-400 mr-1">Elemen</span>

        <button type="button" class="eq-saring" :class="{ aktif: elemenAktif === 'semua' }"
                @click="elemenAktif = 'semua'">Semua</button>

        <button v-for="e in elemen" :key="e.kode" type="button"
                class="eq-saring" :class="{ aktif: elemenAktif === e.kode }"
                @click="elemenAktif = e.kode"
                :title="`${e.nama} · bobot ${e.bobot}% · ${e.maks} poin`">
          {{ e.kode }}
        </button>

        <input v-model="cari" type="search" placeholder="Cari kode, sub-elemen, atau bunyi butir…"
               class="ml-auto w-full sm:w-80 rounded-xl border-stone-200 text-[12.5px]">
      </div>

      <div class="flex flex-wrap items-center gap-4 pt-2 border-t border-stone-100">
        <label class="flex items-center gap-2 text-[12px] font-semibold cursor-pointer">
          <input v-model="semuaTerbuka" type="checkbox" class="accent-[#F57C00]">
          Buka bunyi rubrik semua butir yang tampil
        </label>

        <label v-if="props.audit" class="flex items-center gap-2 text-[12px] font-semibold cursor-pointer">
          <input v-model="hanyaBelum" type="checkbox" class="accent-[#F57C00]">
          Hanya butir yang belum dinilai
        </label>

        <p class="text-[11px] text-stone-500 ml-auto">
          Menampilkan {{ jumlahTampil }} dari {{ props.ringkas?.butir }} butir
          <span v-if="memuat"> · mengambil bunyi rubrik…</span>
        </p>
      </div>
    </section>

    <!-- ══════════ isi acuan ══════════ -->
    <article v-for="e in tampil" :key="e.kode"
             class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">

      <header class="px-5 py-4 border-b border-stone-100 flex flex-wrap items-baseline gap-x-4 gap-y-1">
        <h3 class="font-bold text-[15px]">{{ e.kode }} · {{ e.nama }}</h3>
        <p class="text-[11.5px] text-stone-500">
          Bobot {{ e.bobot }}% · nilai penuh {{ e.maks }} poin · {{ e.sub.length }} sub-elemen
        </p>
      </header>

      <section v-for="s in e.sub" :key="s.kode" class="border-b border-stone-100 last:border-b-0">
        <div class="px-5 py-3 bg-stone-100 flex flex-wrap items-center gap-x-3 gap-y-1">
          <div class="min-w-0 flex-1">
            <h4 class="font-bold text-[12.5px]">{{ s.kode }} · {{ s.nama }}</h4>
            <p class="text-[11px] text-stone-500 mt-0.5">
              {{ s.rinci ? `${s.butir.length} rincian` : 'dinilai langsung' }}
              · nilai penuh {{ s.maks }} poin
              <span v-if="s.ref"> · {{ s.ref }}</span>
            </p>
          </div>

          <span v-if="s.capaian !== null && s.capaian !== undefined"
                class="eq-keadaan"
                :style="{ color: warnaCapaian(s.capaian), borderColor: warnaCapaian(s.capaian) }"
                :title="`Capaian pada audit ${props.audit?.tahun}`">
            {{ angka(s.capaian) }}% · {{ s.dinilai }}/{{ s.berlaku }} dinilai
          </span>
        </div>

        <div v-for="b in s.butir" :key="b.kode"
             class="px-5 py-3 border-t border-stone-100 first:border-t-0"
             :class="s.rinci ? 'pl-12' : ''">

          <div class="flex flex-wrap items-start gap-x-3 gap-y-1">
            <div class="min-w-0 flex-1">
              <!-- Sub-elemen tanpa rincian dinilai LANGSUNG: butirnya
                   adalah dirinya sendiri, dan kepala di atas sudah
                   menyebut kode dan namanya. Diulang di sini, tiap
                   butir semacam itu tercetak dua kali berturut-turut —
                   dan dari 51 sub-elemen, sebagian besar tanpa
                   rincian. -->
              <p v-if="s.rinci" class="text-[12.5px] leading-snug"><b>{{ b.kode }}</b> · {{ b.nama }}</p>

              <p class="text-[11px] text-stone-500" :class="s.rinci && 'mt-0.5'">
                Nilai 0–{{ b.maks }}
                <span v-if="b.halaman"> · rubrik {{ b.halaman }}</span>
                <span v-if="!b.rubrik" class="text-amber-700"> · rubrik belum tercantum</span>
                <span v-else-if="b.tanggung" class="text-amber-700"> · bunyi rubrik berhenti sebelum maksimum</span>
              </p>
            </div>

            <span v-if="props.audit" class="eq-keadaan"
                  :style="{ color: b.nilai === null || b.nilai === undefined ? KEADAAN.netral : warnaCapaian(String(b.nilai).toUpperCase() === 'N/A' ? null : (Number(b.nilai) / b.maks) * 100),
                            borderColor: b.nilai === null || b.nilai === undefined ? KEADAAN.netral : warnaCapaian(String(b.nilai).toUpperCase() === 'N/A' ? null : (Number(b.nilai) / b.maks) * 100) }">
              {{ b.nilai === null || b.nilai === undefined ? 'belum dinilai' : `${b.nilai} / ${b.maks}` }}
            </span>

            <button type="button" class="eq-btn-mini" @click="buka(b)">
              {{ tampilkan(b) ? 'Tutup bunyi' : 'Bunyi rubrik' }}
            </button>
          </div>

          <!-- Tangga nilai beserta bunyinya. Inilah yang membuat
               penilaian dapat diulang auditor lain dan
               dipertanggungjawabkan ke inspektur tambang. -->
          <div v-if="tampilkan(b)" class="mt-2 rounded-xl border border-stone-200 overflow-hidden">
            <p class="px-3 py-2 bg-stone-50 text-[10.5px] text-stone-600">
              Arti tiap nilai <b>0–{{ b.maks }}</b>
              <span v-if="b.halaman"> · {{ b.halaman }}</span>
              <span v-if="props.sumber"> · {{ props.sumber }}</span>
            </p>

            <p v-if="!siap(b)" class="px-3 py-3 text-[11px] text-stone-500">Mengambil bunyi rubrik…</p>

            <p v-else-if="!bunyi[b.kode].length" class="px-3 py-3 text-[11px] text-amber-700">
              Butir ini belum tercantum pada berkas rubrik.
            </p>

            <template v-else>
              <div v-for="a in bunyi[b.kode]" :key="a.nilai"
                   class="flex gap-3 px-3 py-2 border-t border-stone-100 text-[11px]">
                <b class="w-6 shrink-0 text-center text-[13px]"
                   :style="{ color: warnaCapaian(a.persen) }">{{ a.nilai }}</b>

                <div class="min-w-0">
                  <p class="font-semibold">
                    {{ a.label }}
                    <span class="text-stone-500">
                      · {{ angka(a.persen) }}%
                      <template v-if="a.kategori?.label"> · {{ a.kategori.label }}</template>
                    </span>
                  </p>
                  <p v-if="a.ada" class="text-stone-600 mt-0.5 leading-relaxed whitespace-pre-line">{{ a.ket }}</p>
                  <p v-else class="text-amber-700 mt-0.5">Tidak berbunyi pada lampiran.</p>
                </div>
              </div>
            </template>
          </div>
        </div>
      </section>
    </article>

    <p v-if="!tampil.length"
       class="rounded-2xl bg-white border border-stone-100 shadow-card p-10 text-center text-[13px] text-stone-500">
      Tidak ada butir yang cocok dengan penelusuran ini.
    </p>

    <!-- ══════════ ambang yang dipakai seluruh hitungan ══════════ -->
    <section class="grid gap-4 lg:grid-cols-2 items-start">
      <article class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <header class="px-5 py-3.5 border-b border-stone-100">
          <h3 class="text-[13.5px] font-bold text-cam-ink">Kategori temuan menurut capaian</h3>
          <p class="text-[11px] text-stone-500 mt-0.5">
            Diturunkan dari nilai, bukan dipilih auditor. Ambangnya hanya ada di berkas acuan —
            tidak ada satu pun hitungan yang menuliskannya ulang.
          </p>
        </header>

        <ul class="divide-y divide-stone-100">
          <li v-for="k in (props.kategori ?? [])" :key="k.kode"
              class="px-5 py-3 flex items-baseline gap-3 text-[12px]">
            <i class="titik" :style="{ background: k.warna }"></i>
            <b class="min-w-0 flex-1">{{ k.label }}</b>
            <span class="text-stone-500 num">
              {{ k.min === null ? 'tidak berlaku' : `≥ ${k.min}%` }}
            </span>
          </li>
        </ul>
      </article>

      <article class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <header class="px-5 py-3.5 border-b border-stone-100">
          <h3 class="text-[13.5px] font-bold text-cam-ink">Tingkat penerapan menurut nilai akhir</h3>
          <p class="text-[11px] text-stone-500 mt-0.5">
            Nilai akhir dinormalkan ke skala 100 memakai bobot elemen yang benar-benar terpakai.
          </p>
        </header>

        <ul class="divide-y divide-stone-100">
          <li v-for="t in (props.tingkat ?? [])" :key="t.label"
              class="px-5 py-3 flex items-baseline gap-3 text-[12px]">
            <b class="min-w-0 flex-1">{{ t.label }}</b>
            <span class="text-stone-500 num">≥ {{ t.min }}</span>
          </li>
        </ul>
      </article>
    </section>

    <p class="text-[11px] text-stone-500 leading-relaxed">
      {{ props.meta?.catatan }}
    </p>
  </div>
</template>

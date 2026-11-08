<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import KepalaAudit from '../../Components/Smkp/KepalaAudit.vue';
import Ikon from '../../Components/Smkp/Ikon.vue';
import { angka } from '../../smkp/lib/format';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
import type { RingkasAudit } from '../../smkp/types';

/* Penegasan memakai dialog EQOHSEE (useDialog), pengganti konfirmasi() Aurorasys. */
const { dialog, tanya, batal, lanjut } = useDialog();

interface Langkah { kunci: string; judul: string; ket: string; jenis: string; url: string; selesai: boolean; status: string }
interface Babak { kunci: string; nomor: string; judul: string; ket: string; warna: string; langkah: Langkah[] }

const props = defineProps<{
  audit: RingkasAudit;
  rekap: { skor: number; tingkat: { label: string; warna: string }; dinilai: number; berlaku: number; total: number; nilai: number; maks: number };
  hitung: { mayor: number; minor: number; terbuka: number; ditutup: number; diangkat: number; belum: number };
  elemen: Array<{ kode: string; nama: string; bobot: number; dinilai: number; berlaku: number; total: number; capaian: number; skor: number; nilai: number; maks: number; ulang: number; url: string }>;
  ulang: Array<{ kode: string; v: number; maks: number; maks_lama: number | null; koreksi: number | null; setara: number; elemen: string }>;
  alur: Babak[];
  tahap: Array<{ kode: number; label: string; ket: string }>;
  siapTahapDua: boolean;
  bisaSunting: boolean;
}>();

const kemajuan = computed(() => (props.rekap.berlaku ? props.rekap.dinilai / props.rekap.berlaku : 0));
const busur = computed(() => {
  const r = 52, k = 2 * Math.PI * r;
  return { k, isi: k * Math.min(1, props.rekap.skor / 100) };
});

const tahapBerikut = computed(() => props.tahap.find((t) => t.kode === props.audit.tahap + 1) ?? null);

async function naikTahap() {
  const t = tahapBerikut.value;
  if (!t) return;
  if (await tanya({ judul: `Lanjut ke ${t.label}?`, pesan: t.ket, labelAksi: 'Lanjutkan' })) {
    router.post(`/smkp/${props.audit.id}/tahap`, { tahap: t.kode }, { preserveScroll: true });
  }
}

/* Warna capaian elemen mengikuti ambang tingkat penerapan (≥85 baik, ≥60 perlu perbaikan). */
const warnaCapaian = (c: number) => (c >= 0.85 ? 'from-teal-500 to-hijau-500' : c >= 0.6 ? 'from-amber-400 to-amber-500' : 'from-rose-500 to-rose-600');

const persen = (n: number, dari: number) => `${dari ? Math.round((n / dari) * 100) : 0}%`;

const selesaiBabak = (b: Babak) => {
  const kerja = b.langkah.filter((l) => l.jenis === 'kerja');
  return { n: kerja.filter((l) => l.selesai).length, dari: kerja.length };
};
</script>

<template>
  <div class="smkp-ui">
    <Head :title="audit.judul" />
    <KepalaAudit :audit="audit" aktif="ringkasan" :bisa-sunting="bisaSunting" :jumlah-temuan="hitung.diangkat">
      <template #aksi>
        <button v-if="bisaSunting && tahapBerikut" type="button" class="btn-utama btn-kecil py-2" @click="naikTahap">
          Lanjut ke {{ tahapBerikut.label.split('—')[0].trim() }} <Ikon nama="panah-kanan" class="w-4 h-4" />
        </button>
      </template>
    </KepalaAudit>

    <!-- Ringkasan nilai -->
    <section class="grid md:grid-cols-[264px_1fr] lg:grid-cols-[300px_1fr] gap-4">
      <div class="kartu p-5 flex flex-col items-center text-center">
        <p class="text-[13px] font-semibold text-slate-500 self-start">Nilai akhir audit</p>
        <div class="relative mt-2 w-[124px] h-[124px]">
          <svg viewBox="0 0 120 120" class="w-full h-full -rotate-90">
            <circle cx="60" cy="60" r="52" fill="none" stroke="#EEF2F7" stroke-width="10" />
            <circle cx="60" cy="60" r="52" fill="none" :stroke="rekap.tingkat.warna" stroke-width="10" stroke-linecap="round"
                    :stroke-dasharray="`${busur.isi} ${busur.k}`" class="transition-all duration-700" />
          </svg>
          <div class="absolute inset-0 grid place-items-center">
            <div><p class="text-[26px] font-bold text-navy-900 leading-none tabular-nums">{{ angka(rekap.skor) }}</p><p class="text-[11px] text-slate-400 mt-1">dari 100</p></div>
          </div>
        </div>
        <span class="mt-3 lencana text-white px-3 py-1" :style="{ background: rekap.tingkat.warna }">{{ rekap.tingkat.label }}</span>
        <div class="mt-4 w-full">
          <div class="flex justify-between text-[12px] text-slate-500"><span>Butir dinilai</span><b class="text-navy-900 tabular-nums">{{ rekap.dinilai }}/{{ rekap.berlaku }}</b></div>
          <div class="bilah mt-1.5"><i class="bg-gradient-to-r from-brand-500 to-hijau-500" :style="{ width: `${(kemajuan * 100).toFixed(1)}%` }" /></div>
          <p class="mt-2 text-[11.5px] text-slate-400">Butir belum dinilai dihitung nol; butir N/A dikeluarkan dari pembagi.</p>
        </div>
      </div>

      <div class="grid sm:grid-cols-2 gap-3 sm:gap-4">
        <!-- Ketidaksesuaian menurut nilai -->
        <div class="kartu p-5 flex flex-col">
          <div class="flex items-center justify-between gap-3">
            <p class="text-[13px] font-semibold text-slate-500">Ketidaksesuaian</p>
            <span class="text-[11.5px] text-slate-400">menurut hasil penilaian</span>
          </div>
          <p class="mt-2 text-[30px] font-bold text-navy-900 leading-none tabular-nums">{{ hitung.mayor + hitung.minor }}</p>
          <div class="mt-3 flex h-2 overflow-hidden rounded-full bg-slate-100">
            <i class="bg-rose-500" :style="{ width: persen(hitung.mayor, hitung.mayor + hitung.minor) }" />
            <i class="bg-amber-400" :style="{ width: persen(hitung.minor, hitung.mayor + hitung.minor) }" />
          </div>
          <dl class="mt-4 grid grid-cols-2 gap-3">
            <div v-for="k in [
                  ['Mayor', hitung.mayor, 'Sub-elemen capaian < 50%', 'bg-rose-50/70 ring-rose-100', 'bg-rose-500', 'text-rose-700'],
                  ['Minor', hitung.minor, 'Capaian 50% – < 100%, termasuk rincian', 'bg-amber-50/70 ring-amber-100', 'bg-amber-400', 'text-amber-700'],
                ]" :key="String(k[0])" class="rounded-xl px-3 py-2.5 ring-1 ring-inset" :class="String(k[3])">
              <dt class="flex items-center gap-1.5 text-[12px] font-semibold" :class="String(k[5])"><i class="w-2 h-2 rounded-full" :class="String(k[4])" />{{ k[0] }}</dt>
              <dd class="mt-1 text-[22px] font-bold text-navy-900 leading-none tabular-nums">{{ k[1] }}</dd>
              <dd class="mt-1 text-[11px] text-slate-500 leading-snug">{{ k[2] }}</dd>
            </div>
          </dl>
        </div>

        <!-- Tindakan perbaikan (CAR) -->
        <div class="kartu p-5 flex flex-col">
          <div class="flex items-center justify-between gap-3">
            <p class="text-[13px] font-semibold text-slate-500">Tindakan perbaikan</p>
            <span class="text-[11.5px] text-slate-400 tabular-nums">{{ persen(hitung.ditutup, hitung.diangkat) }} ditutup</span>
          </div>
          <p class="mt-2 text-[30px] font-bold text-navy-900 leading-none tabular-nums">{{ hitung.diangkat }} <span class="text-[13px] font-semibold text-slate-400">temuan diangkat</span></p>
          <div class="bilah mt-3"><i class="bg-gradient-to-r from-teal-500 to-hijau-500" :style="{ width: persen(hitung.ditutup, hitung.diangkat) }" /></div>
          <dl class="mt-4 grid grid-cols-2 gap-3">
            <div v-for="k in [
                  ['Terbuka', hitung.terbuka, 'Menunggu tindak lanjut', 'muat', 'text-brand-600'],
                  ['Ditutup', hitung.ditutup, 'Terverifikasi & selesai', 'centang-bulat', 'text-hijau-600'],
                ]" :key="String(k[0])" class="rounded-xl bg-slate-50 px-3 py-2.5 ring-1 ring-inset ring-slate-100">
              <dt class="flex items-center gap-1.5 text-[12px] font-semibold" :class="String(k[4])"><Ikon :nama="String(k[3])" class="w-3.5 h-3.5" />{{ k[0] }}</dt>
              <dd class="mt-1 text-[22px] font-bold text-navy-900 leading-none tabular-nums">{{ k[1] }}</dd>
              <dd class="mt-1 text-[11px] text-slate-500 leading-snug">{{ k[2] }}</dd>
            </div>
          </dl>
          <Link v-if="hitung.belum" :href="`/smkp/${audit.id}/temuan`" class="mt-3 flex items-center justify-between gap-2 rounded-lg bg-amber-50 px-3 py-2 text-[12px] font-semibold text-amber-800 ring-1 ring-inset ring-amber-200 hover:bg-amber-100 transition">
            <span><span class="tabular-nums">{{ hitung.belum }}</span> ketidaksesuaian belum diangkat menjadi temuan</span><Ikon nama="panah-kanan" class="w-4 h-4 shrink-0" />
          </Link>
        </div>
      </div>
    </section>

    <!-- Butir yang dinilai dengan nilai maksimum lama -->
    <section v-if="ulang.length" class="mt-4 rounded-kartu border border-amber-200 bg-amber-50/80 p-4 sm:p-5">
      <div class="flex items-start gap-3">
        <span class="shrink-0 grid place-items-center w-9 h-9 rounded-lg bg-amber-500 text-white"><Ikon nama="peringatan" class="w-[18px] h-[18px]" /></span>
        <div class="min-w-0 flex-1">
          <p class="text-[14px] font-bold text-amber-900">{{ ulang.length }} butir perlu dinilai ulang</p>
          <p class="mt-0.5 text-[12.5px] text-amber-800 leading-relaxed">Nilai maksimal atau deskripsi tingkat butir berikut telah dikoreksi (Kepdirjen 185.K/37.04/DJB/2019 beserta Daftar Koreksi dan Klarifikasi Lampiran II) setelah nilainya diberikan. Sampai dinilai ulang, nilai pada skala lama dihitung setara (mis. 3 dari 4 menjadi 1,5 dari 2) sehingga capaian dan kategori temuannya tidak berubah; periksa kriteria lalu pilih nilai baru.</p>
          <div class="mt-3 flex flex-wrap gap-1.5">
            <Link v-for="u in ulang" :key="u.kode" :href="`/smkp/${audit.id}/elemen/${u.elemen}`"
                  class="lencana bg-white border border-amber-200 text-amber-900 hover:border-amber-400 tabular-nums">{{ u.kode }} · {{ u.maks_lama ? `${angka(u.v, u.v % 1 ? 1 : 0)}/${u.maks_lama} → setara ${angka(u.setara, u.setara % 1 ? 1 : 0)}/${u.maks}` : u.koreksi ? `${angka(u.v, 0)}/${u.maks} · koreksi no. ${u.koreksi}` : `${angka(u.v, 0)} → maks ${u.maks}` }}</Link>
          </div>
        </div>
      </div>
    </section>

    <section class="mt-4 kartu p-4 sm:p-5">
      <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <div><h2 class="kartu-judul">Tujuh Elemen SMKP</h2><p class="kartu-sub">Klik elemen untuk menilai butir kriterianya.</p></div>
        <Link :href="`/smkp/${audit.id}/elemen/I`" class="btn-utama btn-kecil"><Ikon nama="clipboard-check" class="w-4 h-4" /> Mulai menilai</Link>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-1">
        <Link v-for="e in elemen" :key="e.kode" :href="e.url" class="min-w-0 flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-slate-50 transition group">
          <span class="shrink-0 grid place-items-center w-8 h-8 rounded-lg bg-navy-900 text-white text-[11px] font-bold tabular-nums">{{ e.kode }}</span>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <p class="text-[13px] font-semibold text-navy-900 truncate group-hover:text-brand-700" :title="e.nama">{{ e.nama }}</p>
              <span v-if="e.ulang" class="shrink-0 lencana bg-amber-100 text-amber-800" :title="`${e.ulang} butir perlu dinilai ulang`"><Ikon nama="peringatan" class="w-3 h-3" /> {{ e.ulang }}</span>
              <span class="shrink-0 ml-auto text-[12px] font-semibold text-navy-900 tabular-nums">{{ Math.round(e.capaian * 100) }}%</span>
            </div>
            <div class="mt-1 flex items-center gap-2">
              <div class="bilah h-1.5 flex-1"><i class="bg-gradient-to-r" :class="e.dinilai ? warnaCapaian(e.capaian) : 'from-slate-300 to-slate-300'" :style="{ width: `${(e.capaian * 100).toFixed(1)}%` }" /></div>
              <span class="shrink-0 text-[11px] text-slate-400 tabular-nums">{{ e.dinilai }}/{{ e.berlaku }} butir · nilai {{ angka(e.skor) }}/{{ e.bobot }}</span>
            </div>
          </div>
        </Link>
      </div>
    </section>

    <!-- Alur audit -->
    <section class="mt-5">
      <div class="flex flex-wrap items-end justify-between gap-3 mb-3">
        <div><h2 class="text-[16px] font-bold text-navy-900">Alur Audit</h2><p class="text-[13px] text-slate-500">Sedang berjalan: <b class="text-brand-600">{{ audit.labelTahap }}</b></p></div>
        <p v-if="!siapTahapDua && audit.tahap < 2" class="text-[12.5px] text-amber-700 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2">Tahap II terbuka setelah kecukupan dokumentasi & Rencana Audit lengkap.</p>
      </div>
      <div class="grid md:grid-cols-2 gap-4">
        <article v-for="b in alur" :key="b.kunci" class="kartu overflow-hidden">
          <div class="px-4 sm:px-5 py-3.5 flex items-start gap-3 border-b border-slate-100">
            <span class="shrink-0 rounded-lg px-2.5 py-1 text-[11px] font-bold uppercase tracking-[0.12em] text-white" :style="{ background: b.warna }">{{ b.nomor }}</span>
            <div class="flex-1 min-w-0">
              <h3 class="text-[14.5px] font-bold text-navy-900">{{ b.judul }}</h3>
              <p class="text-[12px] text-slate-500 mt-0.5 leading-relaxed">{{ b.ket }}</p>
            </div>
            <span class="text-[13px] font-bold tabular-nums" :style="{ color: b.warna }">{{ selesaiBabak(b).n }}/{{ selesaiBabak(b).dari }}</span>
          </div>
          <div class="h-1 bg-slate-100"><div class="h-full transition-all duration-700" :style="{ width: `${selesaiBabak(b).dari ? (selesaiBabak(b).n / selesaiBabak(b).dari) * 100 : 0}%`, background: b.warna }" /></div>
          <ol class="divide-y divide-slate-100">
            <li v-for="(l, i) in b.langkah" :key="l.kunci">
              <Link :href="l.url" class="flex items-start gap-3 px-4 sm:px-5 py-2.5 hover:bg-slate-50/70 transition group">
                <span class="shrink-0 mt-0.5 grid place-items-center w-6 h-6 rounded-full text-[11px] font-bold"
                      :class="l.selesai ? 'text-white' : 'bg-slate-100 text-slate-400'" :style="l.selesai ? { background: b.warna } : {}">
                  <Ikon v-if="l.selesai" nama="centang" class="w-3.5 h-3.5" :tebal="3" /><template v-else>{{ i + 1 }}</template>
                </span>
                <div class="flex-1 min-w-0">
                  <p class="text-[13px] font-semibold text-navy-900 flex flex-wrap items-center gap-2">
                    {{ l.judul }}
                    <span v-if="l.jenis === 'cetak'" class="lencana bg-slate-100 text-slate-600 py-0 text-[11px]"><Ikon nama="cetak" class="w-3 h-3" /> Cetak</span>
                  </p>
                  <p v-if="l.status" class="text-[11.5px] mt-0.5" :class="l.selesai ? 'text-hijau-700' : 'text-slate-400'">{{ l.status }}</p>
                </div>
                <Ikon nama="chevron-right" class="w-4 h-4 text-slate-300 group-hover:text-brand-500 mt-1" />
              </Link>
            </li>
          </ol>
        </article>
      </div>
    </section>
    <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
  </div>
</template>

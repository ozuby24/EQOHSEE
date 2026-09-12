<script setup lang="ts">
/**
 * Kalender roster — regu × tanggal.
 *
 * SATU SEL PER ORANG PER HARI, dan itu bentuk yang memang dipakai
 * orang: penyusun roster membaca BARISNYA untuk melihat siklus
 * seseorang, dan KOLOMNYA untuk melihat berapa orang di site pada satu
 * tanggal. Daftar yang berbaris ke bawah per tanggal tidak dapat
 * menjawab pertanyaan pertama sama sekali.
 *
 * Sel yang TERHALANG diberi tanda tersendiri, bukan sekadar diwarnai
 * merah. Merah pada kalender terbaca sebagai "hari libur" oleh siapa
 * pun yang belum membaca legendanya — dan yang membacanya di lapangan
 * jarang membaca legenda.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { propHalaman } from '../../../halaman';
import { KEADAAN } from '../../../Grafik/warna';

const props = propHalaman();

const baris  = computed<any[]>(() => (props.baris ?? []) as any[]);
const hari   = computed<any[]>(() => (props.hari ?? []) as any[]);
const temuan = computed<any[]>(() => (props.temuan ?? []) as any[]);

const dari   = ref(String(props.rentang?.dari ?? ''));
const sampai = ref(String(props.rentang?.sampai ?? ''));
const regu   = ref(String(props.terpilih ?? ''));

function muat() {
  router.get('/hris/roster', {
    regu: regu.value || undefined,
    dari: dari.value || undefined,
    sampai: sampai.value || undefined,
  }, { preserveState: true, preserveScroll: true, replace: true });
}

const HURUF: Record<string, string> = {
  kerja: 'K', libur: '·', cuti: 'C', sakit: 'S', izin: 'I',
};

/**
 * Kelas sel, BUKAN gaya sebaris.
 *
 * Gaya sebaris mengalahkan aturan `[data-tema="gelap"]` apa pun, dan
 * akibatnya sudah pernah terjadi di aplikasi ini: angka besar pada
 * Grafik/Meter tak terbaca di mode gelap selama berbulan-bulan karena
 * warnanya dipaku sebaris. Di sini petaknya lebih banyak — satu per
 * orang per hari — sehingga sebidang warna terang di atas latar gelap
 * bukan sekadar tidak terbaca melainkan menyilaukan.
 */
function kelas(sel: any) {
  return sel ? `rs-sel rs-${sel.keadaan}` : 'rs-sel rs-kosong';
}

/** Ringkasan per tanggal: berapa orang bekerja pada kolom itu. */
const perTanggal = computed(() =>
  hari.value.map((_, i) =>
    baris.value.reduce((n, b) => n + (b.sel?.[i]?.keadaan === 'kerja' ? 1 : 0), 0)));

const terhalang = computed(() =>
  hari.value.map((_, i) =>
    baris.value.reduce((n, b) => n + (b.sel?.[i]?.halangan ? 1 : 0), 0)));

const susun = useForm({});
const terbit = useForm({ terbit: '' });

function jalankanSusun() {
  if (!regu.value) return;

  susun.post(`/hris/roster/regu/${regu.value}/susun?dari=${dari.value}&sampai=${sampai.value}`,
    { preserveScroll: true });
}

function jalankanTerbit() {
  if (!regu.value) return;

  terbit.post(`/hris/roster/regu/${regu.value}/terbitkan?dari=${dari.value}&sampai=${sampai.value}`,
    { preserveScroll: true });
}

const menolak = computed(() => temuan.value.filter((t) => t.menolak));

/**
 * Catatan yang tidak memblokir DIRINGKAS PER JENIS, tidak dirinci.
 *
 * Satu regu berisi tiga orang pada pola 14:7 menghasilkan dua belas
 * baris "Melebihi 40 jam seminggu" untuk dua bulan — seluruhnya
 * wajar, seluruhnya tidak memblokir, dan seluruhnya mendorong
 * kalendernya keluar layar. Daftar sepanjang itu berhenti dibaca
 * orang, dan yang ikut berhenti dibaca adalah pelanggaran yang
 * sesungguhnya menolak penerbitan.
 */
const catatan = computed(() => {
  const per = new Map<string, { label: string; jumlah: number; contoh: any }>();

  for (const t of temuan.value) {
    if (t.menolak) continue;

    const ada = per.get(t.label);

    if (ada) ada.jumlah++;
    else per.set(t.label, { label: t.label, jumlah: 1, contoh: t });
  }

  return [...per.values()];
});

const rinciCatatan = ref(false);

const memberitahu = computed(() => temuan.value.filter((t) => !t.menolak));

const ringkas = computed<any>(() => props.ringkas ?? {});
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1600px] mx-auto space-y-5">

    <section class="-mt-2 flex flex-wrap items-end justify-end gap-3">

      <Link href="/hris/roster/pola" class="eq-btn-lain">Pola &amp; regu</Link>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
      <div class="flex flex-wrap items-end gap-3">
        <label class="block">
          <span class="block text-[11px] text-stone-500">Regu</span>
          <select v-model="regu" class="mt-1 rounded-lg border-stone-200 text-[12px] w-56" @change="muat">
            <option value="">Pilih regu…</option>
            <option v-for="g in (props.regu ?? [])" :key="g.id" :value="g.id">
              {{ g.nama }}<span v-if="g.pola"> — {{ g.pola }}</span>
            </option>
          </select>
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Dari</span>
          <input v-model="dari" type="date" class="mt-1 rounded-lg border-stone-200 text-[12px]" @change="muat">
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Sampai</span>
          <input v-model="sampai" type="date" class="mt-1 rounded-lg border-stone-200 text-[12px]" @change="muat">
        </label>

        <button class="eq-btn-lain" :disabled="!regu || susun.processing" @click="jalankanSusun">
          Susun baseline
        </button>

        <button class="eq-btn-utama" :disabled="!regu || terbit.processing" @click="jalankanTerbit">
          Terbitkan
        </button>
      </div>

      <p v-if="terbit.errors.terbit" class="mt-3 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-[12px] text-red-700">
        {{ terbit.errors.terbit }}
      </p>

      <dl v-if="ringkas.baris" class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-[11.5px]">
        <div><dt class="text-stone-400 inline">Baris</dt> <dd class="num font-semibold inline">{{ ringkas.baris }}</dd></div>
        <div><dt class="text-stone-400 inline">Hari kerja</dt> <dd class="num font-semibold inline">{{ ringkas.kerja }}</dd></div>
        <div><dt class="text-stone-400 inline">Total jam</dt> <dd class="num font-semibold inline">{{ ringkas.jam }}</dd></div>
        <div><dt class="text-stone-400 inline">Terbit</dt> <dd class="num font-semibold inline">{{ ringkas.terbit }}</dd></div>
        <div v-if="ringkas.halangan">
          <dt class="text-stone-400 inline">Terhalang berkas</dt>
          <dd class="num font-semibold inline text-red-600">{{ ringkas.halangan }}</dd>
        </div>
      </dl>
    </section>

    <section v-if="menolak.length || catatan.length"
             class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100">
        <h3 class="text-[13.5px] font-bold text-cam-ink">Pemeriksaan batas waktu kerja</h3>
        <p class="text-[11px] text-stone-500 mt-0.5">
          Kepmenakertrans 234/2003 — maks {{ props.BATAS?.jam_hari }} jam sehari,
          {{ props.BATAS?.jam_14_hari }} jam per 14 hari,
          {{ props.BATAS?.hari_beruntun }} hari berturut-turut, istirahat ≥{{ props.BATAS?.istirahat }} hari.
        </p>
      </header>

      <ul class="divide-y divide-stone-100">
        <li v-for="(t, i) in menolak" :key="'m' + i" class="px-5 py-2.5 flex flex-wrap items-center gap-2">
          <span class="rounded-full bg-red-100 text-red-700 px-2 py-0.5 text-[10.5px] font-semibold">Menolak</span>
          <span class="text-[12px] font-semibold text-cam-ink">{{ t.nama }}</span>
          <span class="text-[11.5px] text-stone-600">{{ t.label }}</span>
          <span class="num text-[11px] text-stone-400">{{ t.tanggal }} · {{ t.nilai }} (batas {{ t.batas }})</span>
        </li>

        <li v-for="c in catatan" :key="c.label" class="px-5 py-2.5 flex flex-wrap items-center gap-2">
          <span class="rounded-full bg-amber-100 text-amber-800 px-2 py-0.5 text-[10.5px] font-semibold">Catatan</span>
          <span class="text-[11.5px] text-stone-600">{{ c.label }}</span>
          <span class="num text-[11px] text-stone-400">
            {{ c.jumlah }}× · contoh {{ c.contoh.nama }} {{ c.contoh.tanggal }}
            ({{ c.contoh.nilai }}, batas {{ c.contoh.batas }})
          </span>
        </li>

        <li v-if="catatan.length" class="px-5 py-2">
          <button class="text-[11px] text-stone-500 hover:underline"
                  @click="rinciCatatan = !rinciCatatan">
            {{ rinciCatatan ? 'Sembunyikan rinciannya' : `Lihat rincian ${memberitahu.length} catatan` }}
          </button>

          <ul v-if="rinciCatatan" class="mt-2 space-y-1">
            <li v-for="(t, i) in memberitahu" :key="'i' + i"
                class="text-[11px] text-stone-500 flex flex-wrap gap-2">
              <span class="font-semibold text-stone-600">{{ t.nama }}</span>
              <span>{{ t.label }}</span>
              <span class="num text-stone-400">{{ t.tanggal }} · {{ t.nilai }} (batas {{ t.batas }})</span>
            </li>
          </ul>
        </li>
      </ul>

      <p v-if="!menolak.length" class="px-5 py-2.5 text-[11px] text-stone-500 border-t border-stone-100">
        Tidak ada pelanggaran yang menolak penerbitan. Batas 40 jam seminggu memang dilampaui pola
        tambang yang sah — justru itu yang dikecualikan Kepmenakertrans 234/2003 — jadi ia dicatat,
        bukan memblokir.
      </p>
    </section>

    <section v-if="baris.length" class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[11px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
              <th class="px-3 py-2 font-semibold sticky left-0 bg-stone-50 z-10 min-w-[180px]">Nama</th>
              <th v-for="h in hari" :key="h.tanggal"
                  class="px-0 py-2 font-semibold text-center w-7"
                  :class="h.akhirPekan ? 'text-stone-300' : ''">{{ h.hari }}</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="b in baris" :key="b.pekerja_id" class="border-b border-stone-100">
              <td class="px-3 py-1.5 sticky left-0 bg-white z-10">
                <Link :href="`/miners/${b.pekerja_id}`" class="font-semibold text-cam-lime-deep hover:underline">
                  {{ b.nama }}
                </Link>
                <div class="text-[10px] text-stone-400">{{ b.jabatan || '—' }}</div>
              </td>

              <td v-for="(s, i) in b.sel" :key="i" class="px-0 py-1.5 text-center">
                <span class="relative inline-flex h-5 w-5 items-center justify-center rounded text-[9.5px] font-bold"
                      :class="kelas(s)"
                      :title="s ? `${props.KEADAAN?.[s.keadaan] ?? s.keadaan}${s.shift ? ' · ' + s.shift : ''}${s.jam ? ' · ' + s.jam + ' jam' : ''}${s.halangan ? ' · TERHALANG: ' + s.halangan : ''}` : 'belum disusun'">
                  {{ s ? (HURUF[s.keadaan] ?? '?') : '' }}

                  <span v-if="s?.halangan"
                        class="absolute -top-0.5 -right-0.5 h-1.5 w-1.5 rounded-full ring-1 ring-white"
                        :style="{ backgroundColor: KEADAAN.gawat }" />
                </span>
              </td>
            </tr>

            <tr class="border-t-2 border-stone-200 bg-stone-50/60">
              <td class="px-3 py-1.5 sticky left-0 bg-stone-50 z-10 font-semibold text-stone-500">
                Di site
              </td>
              <td v-for="(n, i) in perTanggal" :key="i"
                  class="px-0 py-1.5 text-center num font-semibold"
                  :class="n === 0 ? 'text-stone-300' : 'text-cam-ink'">{{ n }}</td>
            </tr>

            <tr class="bg-stone-50/60">
              <td class="px-3 py-1.5 sticky left-0 bg-stone-50 z-10 font-semibold text-stone-500">
                Terhalang
              </td>
              <td v-for="(n, i) in terhalang" :key="i"
                  class="px-0 py-1.5 text-center num font-semibold"
                  :class="n === 0 ? 'text-stone-300' : 'text-red-600'">{{ n || '' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer class="px-5 py-3 border-t border-stone-100 flex flex-wrap items-center gap-4 text-[11px] text-stone-500">
        <span v-for="(label, kode) in (props.KEADAAN ?? {})" :key="kode" class="inline-flex items-center gap-1.5">
          <span class="inline-flex h-4 w-4 items-center justify-center rounded text-[9px] font-bold"
                :class="kelas({ keadaan: kode })">{{ HURUF[kode] }}</span>
          {{ label }}
        </span>

        <span class="inline-flex items-center gap-1.5">
          <span class="h-1.5 w-1.5 rounded-full" :style="{ backgroundColor: KEADAAN.gawat }" />
          Terhalang berkas kelayakan
        </span>
      </footer>
    </section>

    <p v-else class="rounded-2xl bg-white border border-stone-100 shadow-card px-5 py-10 text-center text-stone-400">
      {{ props.regu?.length
        ? 'Regu ini belum punya anggota, atau rosternya belum disusun. Tekan "Susun baseline".'
        : 'Belum ada regu. Buat pola dan regu lebih dahulu di halaman Pola & Regu.' }}
    </p>
  </div>
</template>

<style>
/**
 * Palet sel kalender, sadar tema.
 *
 * Ditulis sebagai kelas dan bukan gaya sebaris supaya aturan mode
 * gelap dapat menimpanya — lihat catatan pada kelas() di atas.
 * Nilai terangnya mengikuti palet aplikasi; nilai gelapnya dipilih
 * agar tetap terbaca tanpa menyilaukan pada latar #0D1417.
 */
.rs-sel        { background: #E7E5E4; color: #44403C; }
.rs-kerja      { background: #1E3A5F; color: #FFFFFF; }
.rs-libur      { background: #E7E5E4; color: #78716C; }
.rs-cuti       { background: #93C5FD; color: #1E3A5F; }
.rs-sakit      { background: #FCA5A5; color: #7F1D1D; }
.rs-izin       { background: #FDE68A; color: #78350F; }
.rs-kosong     { background: #FAFAF9; color: #A8A29E; }

:root[data-tema="gelap"] .rs-sel    { background: #2A3439; color: #D6DEE2; }
:root[data-tema="gelap"] .rs-kerja  { background: #2F6F8F; color: #FFFFFF; }
:root[data-tema="gelap"] .rs-libur  { background: #1C262B; color: #6B7A82; }
:root[data-tema="gelap"] .rs-cuti   { background: #1E4E7A; color: #CFE6FF; }
:root[data-tema="gelap"] .rs-sakit  { background: #6B2A2A; color: #FFD9D9; }
:root[data-tema="gelap"] .rs-izin   { background: #5A4410; color: #FFE9B0; }
:root[data-tema="gelap"] .rs-kosong { background: #141C20; color: #46545B; }
</style>

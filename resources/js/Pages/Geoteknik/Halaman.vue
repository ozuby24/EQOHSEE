<script setup lang="ts">
import { computed, reactive } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
const { dialog, tanya, minta, batal, lanjut } = useDialog();


/*
  Prop halaman diambil lewat propHalaman(), bukan defineProps.

  Bentuk `defineProps<{ mode: string; [key: string]: any }>()` yang
  dipakai sebelumnya terbaca seolah menerima apa saja. Yang sebenarnya
  terjadi: penyusun Vue tidak dapat menurunkan nama prop dari sebuah
  index signature, sehingga HANYA `mode` yang benar-benar terdaftar
  sebagai prop. Seluruh sisanya jatuh ke $attrs — dan karena template
  ini berakar jamak (<Head> beserta pembungkusnya), atribut itu bahkan
  tidak tersangkut di mana pun.

  Akibatnya halaman merender kosong seluruhnya: tidak ada galat, tidak
  ada peringatan pada build produksi, hanya data yang dikirim server dan
  tidak pernah sampai ke tampilan. Uji sisi server tetap hijau, sebab
  yang salah bukan propnya melainkan penerimaannya.

  propHalaman() mengambil prop halaman apa adanya — termasuk yang
  dibagikan middleware — sehingga tidak ada daftar nama yang harus
  dirawat sejajar dengan controller-nya, dan tidak ada nama yang dapat
  hilang diam-diam. Dibacanya hidup: lihat resources/js/halaman.ts.
*/
const props = propHalaman();
const isAdmin = computed(() => Boolean(props.pengguna?.admin));

const judul: Record<string, string> = {
  dashboard: 'Pemantauan Kestabilan Lereng',
  bacaan: 'Pembacaan Alat Pantau',
  lereng: 'Lereng & Instrumen',
};

const angka = (v: unknown, d = 0) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: d, minimumFractionDigits: d }).format(Number(v || 0));
const label = (v: string) => String(v || '').replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());

const tautan = computed(() => props.tautan || {});
const untuk = (pola: string | undefined, id: number | string) => String(pola || '').replace('__ID__', String(id));
const sibuk = reactive<Record<number, boolean>>({});

const lereng = useForm<any>({
  company_id: '', kode: '', nama: '', jenis: 'highwall', lokasi: '', litologi: '',
  tinggi_rencana_m: '', sudut_rencana_deg: '', tinggi_jenjang_rencana_m: '', lebar_berm_rencana_m: '',
  tinggi_aktual_m: '', sudut_aktual_deg: '', tinggi_jenjang_aktual_m: '', lebar_berm_aktual_m: '',
  fk_rencana: '', ppa_rencana_persen: '', kajian_oleh: '', kajian_tanggal: '', interval_kajian_hari: '',
  ambang_waspada_mm_hari: '', ambang_siaga_mm_hari: '', ambang_awas_mm_hari: '',
  status: 'aktif', catatan: '',
});

const bacaan = useForm<any>({
  company_id: '', geo_lereng_id: '', geo_instrumen_id: '',
  tanggal: new Date().toISOString().slice(0, 10),
  perpindahan_mm: 0, retakan_mm: '', muka_air_m: '', curah_hujan_mm: '',
  ada_gejala: false, gejala: '', catatan: '',
});

const instrumen = useForm<any>({ kode: '', jenis: 'prisma', status: 'siap', elevasi_m: '', kalibrasi_terakhir: '' });
const tindak = useForm<any>({ company_id: '', kode_pemicu: '', judul: '', prioritas: 'sedang', penanggung_jawab: '', target_selesai: '', uraian: '' });

const ditangani = computed(() => new Set(props.kodeDitangani || []));

/** Alat pada lereng yang sedang dipilih di formulir pembacaan. */
const alatPilihan = computed(() => {
  const l = (props.lereng || []).find((x: any) => String(x.id) === String(bacaan.geo_lereng_id));
  return l?.instrumenDaftar || [];
});

function rentang() {
  router.get(window.location.pathname, { dari: props.dari, sampai: props.sampai }, { preserveState: true, replace: true });
}
function simpanLereng() { lereng.post(tautan.value.lerengSimpan, { preserveScroll: true, onSuccess: () => lereng.reset('kode', 'nama', 'lokasi', 'litologi', 'catatan') }); }
async function hapusLereng(l: any) { if (await tanya(`Hapus lereng ${l.kode} beserta seluruh pembacaannya?`)) router.delete(untuk(tautan.value.lerengHapus, l.id), { preserveScroll: true }); }
function simpanInstrumen(l: any) { instrumen.post(untuk(tautan.value.instrumenSimpan, l.id), { preserveScroll: true, onSuccess: () => instrumen.reset() }); }
function ubahInstrumen(i: any, status: string) { router.put(untuk(tautan.value.instrumenUbah, i.id), { status }, { preserveScroll: true }); }

function simpanBacaan() {
  bacaan.post(tautan.value.bacaanSimpan, {
    preserveScroll: true,
    onSuccess: () => bacaan.reset('retakan_mm', 'muka_air_m', 'curah_hujan_mm', 'ada_gejala', 'gejala', 'catatan'),
  });
}
async function hapusBacaan(b: any) { if (await tanya(`Hapus pembacaan ${b.tanggalLabel}?`)) router.delete(untuk(tautan.value.bacaanHapus, b.id), { preserveScroll: true }); }

function ajukan(b: any) { sibuk[b.id] = true; router.post(untuk(tautan.value.ajukan, b.id), {}, { preserveScroll: true, onFinish: () => { sibuk[b.id] = false; } }); }
async function setujui(b: any) {
  if (!await tanya(`Setujui pembacaan ${b.tanggalLabel}? Setelah disetujui tidak dapat diubah.`)) return;
  sibuk[b.id] = true; router.post(untuk(tautan.value.setujui, b.id), {}, { preserveScroll: true, onFinish: () => { sibuk[b.id] = false; } });
}
async function tolak(b: any) {
  const alasan = await minta({ judul: `Tolak pembacaan ${b.tanggalLabel}?`, label: 'Alasan penolakan',
    jenis: 'panjang', min: 5, labelAksi: 'Tolak', nada: 'bahaya' });
  if (alasan === null) return;
  sibuk[b.id] = true; router.post(untuk(tautan.value.tolak, b.id), { alasan_tolak: alasan }, { preserveScroll: true, onFinish: () => { sibuk[b.id] = false; } });
}

function tindakDari(a: any) {
  tindak.kode_pemicu = a.kode; tindak.judul = a.judul;
  tindak.prioritas = a.level === 'tinggi' ? 'tinggi' : 'sedang'; tindak.uraian = a.saran || '';
  document.getElementById('form-tindak')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
function simpanTindak() { tindak.post(tautan.value.tindakSimpan, { preserveScroll: true, onSuccess: () => tindak.reset('kode_pemicu', 'judul', 'penanggung_jawab', 'target_selesai', 'uraian') }); }
function ubahTindak(t: any, status: string) { router.put(untuk(tautan.value.tindakUbah, t.id), { status }, { preserveScroll: true }); }

const warnaStatus: Record<string, string> = {
  draf: 'bg-stone-100 text-stone-600', diajukan: 'bg-amber-100 text-amber-700',
  disetujui: 'bg-emerald-100 text-emerald-700', ditolak: 'bg-red-100 text-red-700',
};
const warnaTingkat: Record<string, string> = {
  awas: 'bg-red-100 text-red-700', siaga: 'bg-orange-100 text-orange-700',
  waspada: 'bg-amber-100 text-amber-700', normal: 'bg-emerald-100 text-emerald-700',
  'tanpa-data': 'bg-stone-100 text-stone-500',
};
const warnaAlat: Record<string, string> = {
  siap: 'text-emerald-600', rusak: 'text-red-600', perawatan: 'text-amber-600', arsip: 'text-stone-400',
};
const arahTren: Record<string, string> = {
  menderas: 'Menderas', melambat: 'Melambat', tetap: 'Tetap', 'belum-cukup': 'Belum cukup titik',
};
</script>

<template>
  <Head :title="judul[props.mode]" />

  <div class="max-w-[1400px] mx-auto space-y-5">
    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-[10px] font-bold uppercase tracking-[.16em] text-cam-orange">Engineering · Geotechnical</p>
        
        <p class="text-[12px] text-stone-500 mt-1">Gerakan lereng, geometri terbangun, dan acuan kajian geoteknik dalam satu tempat.</p>
      </div>
      <div class="flex gap-2">
        <input v-model="props.dari" type="date" class="rounded-lg border-stone-200 text-[11px]" aria-label="Tanggal mulai">
        <input v-model="props.sampai" type="date" class="rounded-lg border-stone-200 text-[11px]" aria-label="Tanggal akhir">
        <button class="eq-btn-lain" type="button" @click="rentang">Terapkan</button>
      </div>
    </section>

    <!--
      Batas kewenangan modul ini dinyatakan di halamannya sendiri, bukan
      hanya di dokumentasi. Yang membacanya adalah orang yang sedang
      memutuskan boleh atau tidaknya bekerja di bawah sebuah lereng.
    -->
    <section class="rounded-2xl border border-cam-orange/30 bg-cam-orange-soft px-4 py-3">
      <p class="text-[11.5px] text-stone-700 leading-relaxed">
        <b>Alat bantu keputusan.</b> Angka di halaman ini — laju gerakan, kecenderungan, dan perkiraan
        dari kebalikan laju — adalah pengamatan dan turunannya, bukan hasil kajian kestabilan lereng.
        Penetapan aman atau tidaknya sebuah lereng tetap menjadi kewenangan tenaga kompeten geoteknik.
      </p>
    </section>

    <section v-if="(props.alerts || []).length" class="grid gap-3 md:grid-cols-2">
      <div v-for="a in props.alerts" :key="a.kode" class="rounded-2xl border p-4"
           :class="a.level === 'tinggi' ? 'border-red-100 bg-red-50' : 'border-amber-100 bg-amber-50'">
        <b class="text-[12px]" :class="a.level === 'tinggi' ? 'text-red-700' : 'text-amber-700'">{{ a.judul }}</b>
        <p class="text-[11px] text-stone-600 mt-1">{{ a.ket }}</p>
        <p v-if="a.saran" class="text-[11px] text-stone-700 mt-2 pt-2 border-t border-black/5"><span class="font-bold">Tindakan: </span>{{ a.saran }}</p>
        <div class="mt-2">
          <span v-if="ditangani.has(a.kode)" class="inline-block rounded-full bg-emerald-100 text-emerald-700 px-2 py-1 text-[10px] font-bold">Sedang ditangani</span>
          <button v-else type="button" class="text-[11px] font-bold text-cam-orange-dark py-1.5" @click="tindakDari(a)">+ Buat tindak lanjut</button>
        </div>
      </div>
    </section>

    <nav class="flex flex-wrap gap-2">
      <Link v-for="i in [['dashboard','Ringkasan',tautan.dashboard],['bacaan','Pembacaan',tautan.bacaan],['lereng','Lereng & Instrumen',tautan.lereng]]"
            :key="i[0]" :href="i[2] as string" class="rounded-full px-4 py-2 text-[11px] font-bold"
            :class="props.mode === i[0] ? 'bg-cam-ink text-white' : 'bg-white text-stone-500 border border-stone-200'">{{ i[1] }}</Link>
      <a :href="tautan.cetak" class="ml-auto rounded-full px-4 py-2 text-[11px] font-bold bg-white text-cam-orange border border-cam-orange/40">Cetak laporan</a>
    </nav>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
      <article v-for="c in [
        { l: 'Lereng dipantau', v: String(props.ringkas?.lereng || 0), c: 'text-cam-ink' },
        { l: 'Laju tertinggi', v: props.ringkas?.lajuTertinggi === null ? '—' : `${angka(props.ringkas?.lajuTertinggi, 1)} mm/h`, c: 'text-orange-700' },
        { l: 'Perkiraan terdekat', v: props.ringkas?.ttfTerdekat === null ? '—' : `${angka(props.ringkas?.ttfTerdekat, 1)} hari`, c: props.ringkas?.ttfTerdekat === null ? 'text-stone-400' : 'text-red-600' },
        { l: 'Geometri menyimpang', v: String(props.ringkas?.penyimpangan || 0), c: props.ringkas?.penyimpangan ? 'text-red-600' : 'text-emerald-600' },
        { l: 'Alat rusak', v: String(props.ringkas?.instrumenRusak || 0), c: props.ringkas?.instrumenRusak ? 'text-red-600' : 'text-emerald-600' },
      ]" :key="c.l" class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
        <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">{{ c.l }}</p>
        <p class="mt-2 text-xl font-extrabold" :class="c.c">{{ c.v }}</p>
      </article>
    </section>

    <!-- ═══════════ RINGKASAN ═══════════ -->
    <template v-if="props.mode === 'dashboard'">
      <section class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        <div v-for="l in props.lereng || []" :key="l.id" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <b class="text-[14px]">{{ l.kode }}</b>
              <p class="text-[11px] text-stone-400">{{ l.nama }} · {{ label(l.jenis) }}</p>
            </div>
            <span class="rounded-full px-2 py-1 text-[10px] font-bold" :class="warnaTingkat[l.gerakan?.tingkat]">
              {{ label(l.gerakan?.tingkat) }}
            </span>
          </div>

          <div class="mt-4 grid grid-cols-2 gap-3 text-[11.5px]">
            <div>
              <p class="text-stone-400">Laju terakhir</p>
              <p class="font-bold text-[15px]">{{ l.gerakan?.laju === null ? '—' : `${angka(l.gerakan?.laju, 2)} mm/hari` }}</p>
            </div>
            <div>
              <p class="text-stone-400">Kecenderungan</p>
              <p class="font-bold text-[15px]" :class="l.gerakan?.tren?.arah === 'menderas' ? 'text-red-600' : 'text-stone-700'">
                {{ arahTren[l.gerakan?.tren?.arah] || '—' }}
              </p>
            </div>
          </div>

          <!--
            Perkiraan waktu runtuh sengaja tidak ditampilkan sebagai
            tanggal. Tanggal terbaca sebagai janji; rentang hari beserta
            keterangan keyakinannya terbaca sebagai perkiraan, dan itulah
            yang memang dihasilkan metode kebalikan laju.
          -->
          <div class="mt-3 rounded-xl px-3 py-2.5"
               :class="l.gerakan?.ttf?.dapatDipakai ? 'bg-red-50 border border-red-100' : 'bg-stone-50 border border-stone-100'">
            <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">Kebalikan laju</p>
            <p v-if="l.gerakan?.ttf?.dapatDipakai" class="text-[13px] font-extrabold text-red-700 mt-0.5">
              ± {{ angka(l.gerakan?.ttf?.hari, 1) }} hari lagi
              <span class="font-semibold text-[11px] text-stone-500">(R² {{ angka(l.gerakan?.ttf?.r2, 2) }})</span>
            </p>
            <p v-else class="text-[12px] text-stone-500 mt-0.5">Tidak dapat diperkirakan</p>
            <p class="text-[10.5px] text-stone-500 mt-1 leading-relaxed">{{ l.gerakan?.ttf?.alasan }}</p>
          </div>

          <div v-if="(l.penyimpangan || []).length" class="mt-3 rounded-xl bg-red-50 border border-red-100 px-3 py-2.5">
            <p class="text-[10px] uppercase tracking-wide font-bold text-red-600">Menyimpang dari rancangan</p>
            <p v-for="s in l.penyimpangan" :key="s.hal" class="text-[11.5px] text-stone-700 mt-1">
              {{ s.hal }}: <b>{{ s.aktual }}{{ s.satuan }}</b> terhadap rancangan {{ s.rencana }}{{ s.satuan }}
            </p>
          </div>

          <div class="mt-3 pt-3 border-t border-stone-100 grid grid-cols-3 gap-2 text-[11px]">
            <div><p class="text-stone-400">FK rancangan</p><p class="font-bold">{{ l.rencana?.fk ?? '—' }}</p></div>
            <div><p class="text-stone-400">Alat siap</p><p class="font-bold">{{ l.instrumen?.siap }}/{{ l.instrumen?.jumlah }}</p></div>
            <div>
              <p class="text-stone-400">Kajian</p>
              <p class="font-bold" :class="(l.kajian?.sisaHari ?? 1) < 0 ? 'text-red-600' : ''">
                {{ l.kajian?.sisaHari === null ? '—' : `${l.kajian.sisaHari} hari` }}
              </p>
            </div>
          </div>
          <p v-if="l.ambang?.khusus" class="mt-2 text-[10.5px] text-stone-400">
            Ambang khusus lereng ini: {{ l.ambang.waspada }} / {{ l.ambang.siaga }} / {{ l.ambang.awas }} mm/hari.
          </p>
        </div>
      </section>

      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="font-bold text-[14px]">Kelengkapan pembacaan</h3>
        <p class="text-[11.5px] text-stone-500 mt-1">
          Laju dihitung dari deret waktu; lubang pada deretnya membuat percepatan terbaca lebih landai daripada sebenarnya.
        </p>
        <div class="mt-3 h-2 rounded-full bg-stone-100 overflow-hidden">
          <div class="h-full rounded-full bg-cam-lime"
               :style="{ width: `${Math.min(100, Number(props.kelengkapan?.persen || 0))}%` }"></div>
        </div>
        <div class="mt-3 grid grid-cols-2 gap-4 text-[11.5px]">
          <div class="rounded-xl bg-stone-50 px-3 py-2">
            <p class="text-stone-400">Belum dilaporkan</p>
            <p class="text-[15px] font-extrabold">{{ props.kelengkapan?.belumDilaporkan || 0 }}</p>
            <p class="text-[10.5px] text-stone-500">tagih ke pengawas geoteknik</p>
          </div>
          <div class="rounded-xl bg-stone-50 px-3 py-2">
            <p class="text-stone-400">Menunggu tinjauan</p>
            <p class="text-[15px] font-extrabold">{{ props.kelengkapan?.menungguTinjauan || 0 }}</p>
            <p class="text-[10.5px] text-stone-500">tagih ke Kepala Teknik Tambang</p>
          </div>
        </div>
      </section>
    </template>

    <!-- ═══════════ PEMBACAAN ═══════════ -->
    <template v-if="props.mode === 'bacaan'">
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="font-bold text-[14px]">Catat pembacaan</h3>
        <form class="mt-3 grid gap-3 md:grid-cols-4" @submit.prevent="simpanBacaan">
          <label class="text-[11px] font-bold text-stone-500">Lereng
            <select v-model="bacaan.geo_lereng_id" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required>
              <option value="">— pilih —</option>
              <option v-for="l in props.lereng || []" :key="l.id" :value="l.id">{{ l.kode }} — {{ l.nama }}</option>
            </select>
          </label>
          <label class="text-[11px] font-bold text-stone-500">Alat pantau
            <select v-model="bacaan.geo_instrumen_id" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
              <option value="">— tanpa alat —</option>
              <option v-for="i in alatPilihan" :key="i.id" :value="i.id">{{ i.kode }} ({{ label(i.jenis) }})</option>
            </select>
          </label>
          <label class="text-[11px] font-bold text-stone-500">Tanggal
            <input v-model="bacaan.tanggal" type="date" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required>
          </label>
          <label class="text-[11px] font-bold text-stone-500">Perpindahan kumulatif (mm)
            <input v-model="bacaan.perpindahan_mm" type="number" step="0.001" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required>
          </label>

          <label class="text-[11px] font-bold text-stone-500">Lebar retakan (mm)
            <input v-model="bacaan.retakan_mm" type="number" step="0.001" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
          </label>
          <label class="text-[11px] font-bold text-stone-500">Muka air (m)
            <input v-model="bacaan.muka_air_m" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
          </label>
          <label class="text-[11px] font-bold text-stone-500">Curah hujan (mm)
            <input v-model="bacaan.curah_hujan_mm" type="number" step="0.1" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
          </label>
          <label class="text-[11px] font-bold text-stone-500 flex items-end gap-2 pb-2">
            <input v-model="bacaan.ada_gejala" type="checkbox" class="rounded border-stone-300">
            <span>Ada gejala di lapangan</span>
          </label>

          <label class="md:col-span-2 text-[11px] font-bold text-stone-500">Uraian gejala
            <input v-model="bacaan.gejala" type="text" placeholder="retakan baru, gugur batu, rembesan…"
                   class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
          </label>
          <label class="md:col-span-2 text-[11px] font-bold text-stone-500">Catatan
            <input v-model="bacaan.catatan" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
          </label>

          <p class="md:col-span-3 text-[11px] text-stone-500 self-center">
            Perpindahan diisi <b>kumulatif sejak titik nol alat</b>, bukan selisih harian — lajunya dihitung sendiri.
            Gejala yang ditandai langsung masuk peringatan tanpa menunggu tinjauan.
          </p>
          <button class="eq-btn-utama self-end" :disabled="bacaan.processing">Simpan sebagai draf</button>
        </form>
        <p v-for="(e, k) in bacaan.errors" :key="k" class="mt-2 text-[11px] text-red-600">{{ e }}</p>
      </section>

      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <div class="px-5 py-4 border-b border-stone-100"><h3 class="font-bold text-[14px]">Pembacaan pada periode ini</h3></div>
        <div class="overflow-x-auto">
          <table class="w-full text-[11.5px]">
            <thead class="bg-stone-50 text-left text-stone-400 uppercase text-[10px] tracking-wide">
              <tr>
                <th class="px-5 py-2.5">Tanggal</th><th class="px-5 py-2.5">Lereng</th>
                <th class="px-5 py-2.5">Alat</th><th class="px-5 py-2.5">Perpindahan</th>
                <th class="px-5 py-2.5">Gejala</th><th class="px-5 py-2.5">Status</th>
                <th class="px-5 py-2.5 text-right">Alur</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="b in props.bacaan || []" :key="b.id" class="border-b border-stone-50">
                <td class="px-5 py-3 font-semibold">{{ b.tanggalLabel }}</td>
                <td class="px-5 py-3">{{ b.lereng }}</td>
                <td class="px-5 py-3 text-stone-500">{{ b.instrumen || '—' }}</td>
                <td class="px-5 py-3">{{ angka(b.perpindahan_mm, 2) }} mm</td>
                <td class="px-5 py-3">
                  <span v-if="b.ada_gejala" class="rounded-full bg-red-100 text-red-700 px-2 py-0.5 text-[10px] font-bold">Ada</span>
                  <span v-else class="text-stone-300">—</span>
                </td>
                <td class="px-5 py-3">
                  <span class="rounded-full px-2 py-0.5 text-[10px] font-bold" :class="warnaStatus[b.status]">{{ b.statusLabel }}</span>
                  <p v-if="b.alur?.alasanTolak" class="text-[10.5px] text-red-600 mt-1">{{ b.alur.alasanTolak }}</p>
                </td>
                <td class="px-5 py-3 text-right whitespace-nowrap">
                  <button v-if="b.alur?.dapatDiajukan" type="button" class="text-[11px] font-bold text-cam-orange-dark disabled:opacity-40"
                          :disabled="sibuk[b.id]" @click="ajukan(b)">Ajukan</button>
                  <template v-if="b.alur?.dapatDitinjau">
                    <button type="button" class="ml-3 text-[11px] font-bold text-emerald-700 disabled:opacity-40"
                            :disabled="sibuk[b.id]" @click="setujui(b)">Setujui</button>
                    <button type="button" class="ml-3 text-[11px] font-bold text-red-600 disabled:opacity-40"
                            :disabled="sibuk[b.id]" @click="tolak(b)">Tolak</button>
                  </template>
                  <button v-if="isAdmin && b.status !== 'disetujui'" type="button" class="ml-3 text-[11px] font-bold text-stone-400" @click="hapusBacaan(b)">Hapus</button>
                </td>
              </tr>
              <tr v-if="!(props.bacaan || []).length"><td colspan="7" class="px-5 py-8 text-center text-stone-400">Belum ada pembacaan pada rentang ini.</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>

    <!-- ═══════════ LERENG & INSTRUMEN ═══════════ -->
    <template v-if="props.mode === 'lereng'">
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="font-bold text-[14px]">Daftarkan lereng</h3>
        <p class="text-[11.5px] text-stone-500 mt-1">
          Faktor keamanan dan probabilitas longsor diisikan <b>dari dokumen kajian geoteknik yang berlaku</b>,
          bukan dihitung di sini. Penyusun dan tanggalnya dicatat supaya jelas keputusan siapa yang dipakai sebagai acuan.
        </p>
        <form class="mt-3 grid gap-3 md:grid-cols-4" @submit.prevent="simpanLereng">
          <label class="text-[11px] font-bold text-stone-500">Kode
            <input v-model="lereng.kode" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required>
          </label>
          <label class="text-[11px] font-bold text-stone-500">Nama
            <input v-model="lereng.nama" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required>
          </label>
          <label class="text-[11px] font-bold text-stone-500">Jenis
            <select v-model="lereng.jenis" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
              <option v-for="j in props.opsi?.jenis || []" :key="j" :value="j">{{ label(j) }}</option>
            </select>
          </label>
          <label class="text-[11px] font-bold text-stone-500">Litologi
            <input v-model="lereng.litologi" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
          </label>

          <p class="md:col-span-4 text-[10px] font-bold uppercase tracking-wide text-stone-400 pt-1">Geometri rancangan</p>
          <label class="text-[11px] font-bold text-stone-500">Tinggi (m)
            <input v-model="lereng.tinggi_rencana_m" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Sudut (°)
            <input v-model="lereng.sudut_rencana_deg" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Tinggi jenjang (m)
            <input v-model="lereng.tinggi_jenjang_rencana_m" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Lebar berm (m)
            <input v-model="lereng.lebar_berm_rencana_m" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>

          <p class="md:col-span-4 text-[10px] font-bold uppercase tracking-wide text-stone-400 pt-1">Geometri terbangun</p>
          <label class="text-[11px] font-bold text-stone-500">Tinggi (m)
            <input v-model="lereng.tinggi_aktual_m" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Sudut (°)
            <input v-model="lereng.sudut_aktual_deg" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Tinggi jenjang (m)
            <input v-model="lereng.tinggi_jenjang_aktual_m" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Lebar berm (m)
            <input v-model="lereng.lebar_berm_aktual_m" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>

          <p class="md:col-span-4 text-[10px] font-bold uppercase tracking-wide text-stone-400 pt-1">Acuan kajian geoteknik</p>
          <label class="text-[11px] font-bold text-stone-500">FK rancangan
            <input v-model="lereng.fk_rencana" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">PPA rancangan (%)
            <input v-model="lereng.ppa_rencana_persen" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Disusun oleh
            <input v-model="lereng.kajian_oleh" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Tanggal kajian
            <input v-model="lereng.kajian_tanggal" type="date" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>

          <p class="md:col-span-4 text-[10px] font-bold uppercase tracking-wide text-stone-400 pt-1">
            Ambang laju (mm/hari) — kosongkan untuk memakai bawaan
            {{ props.opsi?.ambangBawaan?.waspada }} / {{ props.opsi?.ambangBawaan?.siaga }} / {{ props.opsi?.ambangBawaan?.awas }}
          </p>
          <label class="text-[11px] font-bold text-stone-500">Waspada
            <input v-model="lereng.ambang_waspada_mm_hari" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Siaga
            <input v-model="lereng.ambang_siaga_mm_hari" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Awas
            <input v-model="lereng.ambang_awas_mm_hari" type="number" step="0.01" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-bold text-stone-500">Interval kajian (hari)
            <input v-model="lereng.interval_kajian_hari" type="number" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>

          <label class="md:col-span-3 text-[11px] font-bold text-stone-500">Catatan
            <input v-model="lereng.catatan" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
          <button class="eq-btn-utama self-end" :disabled="lereng.processing">Simpan lereng</button>
        </form>
        <p v-for="(e, k) in lereng.errors" :key="k" class="mt-2 text-[11px] text-red-600">{{ e }}</p>
      </section>

      <section class="grid gap-5 md:grid-cols-2">
        <div v-for="l in props.lereng || []" :key="l.id" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <b class="text-[14px]">{{ l.kode }}</b>
              <p class="text-[11px] text-stone-400">{{ l.nama }} · {{ label(l.jenis) }} · {{ l.status }}</p>
              <p v-if="l.kajian?.oleh" class="text-[10.5px] text-stone-400 mt-0.5">
                Kajian oleh {{ l.kajian.oleh }}{{ l.kajian.tanggal ? ` · ${l.kajian.tanggal}` : '' }}
              </p>
            </div>
            <button v-if="isAdmin" type="button" class="text-[11px] font-bold text-stone-400 py-1.5" @click="hapusLereng(l)">Hapus</button>
          </div>

          <table class="w-full mt-3 text-[11.5px]">
            <thead class="text-left text-stone-400 uppercase text-[10px]">
              <tr><th class="py-1">Alat</th><th class="py-1">Jenis</th><th class="py-1">Status</th><th class="py-1 text-right">Ubah</th></tr>
            </thead>
            <tbody>
              <tr v-for="i in l.instrumenDaftar || []" :key="i.id" class="border-t border-stone-50">
                <td class="py-2 font-semibold">{{ i.kode }}</td>
                <td class="py-2 text-stone-500">{{ label(i.jenis) }}</td>
                <td class="py-2 font-bold" :class="warnaAlat[i.status]">{{ label(i.status) }}</td>
                <td class="py-2 text-right">
                  <select class="rounded-lg border-stone-200 text-[11px]" :value="i.status"
                          @change="ubahInstrumen(i, ($event.target as HTMLSelectElement).value)" aria-label="Status">
                    <option v-for="s in props.opsi?.statusInstrumen || []" :key="s" :value="s">{{ label(s) }}</option>
                  </select>
                </td>
              </tr>
              <tr v-if="!(l.instrumenDaftar || []).length"><td colspan="4" class="py-3 text-stone-400">Belum ada alat pantau.</td></tr>
            </tbody>
          </table>

          <form class="mt-3 grid grid-cols-4 gap-2" @submit.prevent="simpanInstrumen(l)">
            <input v-model="instrumen.kode" type="text" placeholder="Kode alat" class="rounded-lg border-stone-200 text-[11px]" required>
            <select v-model="instrumen.jenis" class="rounded-lg border-stone-200 text-[11px]" aria-label="Jenis">
              <option v-for="j in props.opsi?.jenisInstrumen || []" :key="j" :value="j">{{ label(j) }}</option>
            </select>
            <select v-model="instrumen.status" class="rounded-lg border-stone-200 text-[11px]" aria-label="Status">
              <option v-for="s in props.opsi?.statusInstrumen || []" :key="s" :value="s">{{ label(s) }}</option>
            </select>
            <button class="eq-btn-lain text-[11px]" :disabled="instrumen.processing">+ Alat</button>
          </form>
        </div>
      </section>
    </template>

    <!-- ═══════════ TINDAK LANJUT ═══════════ -->
    <section id="form-tindak" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="font-bold text-[14px]">Tindak lanjut</h3>
      <form class="mt-3 grid gap-3 md:grid-cols-5" @submit.prevent="simpanTindak">
        <label class="md:col-span-2 text-[11px] font-bold text-stone-500">Judul
          <input v-model="tindak.judul" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]" required></label>
        <label class="text-[11px] font-bold text-stone-500">Prioritas
          <select v-model="tindak.prioritas" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option v-for="p in props.opsi?.prioritasTindak || []" :key="p" :value="p">{{ label(p) }}</option>
          </select></label>
        <label class="text-[11px] font-bold text-stone-500">Penanggung jawab
          <input v-model="tindak.penanggung_jawab" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-bold text-stone-500">Target selesai
          <input v-model="tindak.target_selesai" type="date" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
        <label class="md:col-span-4 text-[11px] font-bold text-stone-500">Uraian
          <input v-model="tindak.uraian" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]"></label>
        <button class="eq-btn-utama self-end" :disabled="tindak.processing">Tambah</button>
      </form>

      <table class="w-full mt-4 text-[11.5px]">
        <thead class="text-left text-stone-400 uppercase text-[10px]">
          <tr><th class="py-1">Judul</th><th class="py-1">PJ</th><th class="py-1">Target</th><th class="py-1">Status</th><th class="py-1 text-right">Ubah</th></tr>
        </thead>
        <tbody>
          <tr v-for="t in props.tindak || []" :key="t.id" class="border-t border-stone-50">
            <td class="py-2">
              <b>{{ t.judul }}</b>
              <span v-if="t.terlambat" class="ml-2 rounded-full bg-red-100 text-red-700 px-2 py-0.5 text-[10px] font-bold">Terlambat</span>
            </td>
            <td class="py-2 text-stone-500">{{ t.penanggung_jawab || '—' }}</td>
            <td class="py-2 text-stone-500">{{ t.target_selesai || '—' }}</td>
            <td class="py-2">{{ label(t.status) }}</td>
            <td class="py-2 text-right">
              <select class="rounded-lg border-stone-200 text-[11px]" :value="t.status"
                      @change="ubahTindak(t, ($event.target as HTMLSelectElement).value)" aria-label="Status">
                <option v-for="s in props.opsi?.statusTindak || []" :key="s" :value="s">{{ label(s) }}</option>
              </select>
            </td>
          </tr>
          <tr v-if="!(props.tindak || []).length"><td colspan="5" class="py-6 text-center text-stone-400">Belum ada tindak lanjut.</td></tr>
        </tbody>
      </table>
    </section>
  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>

<script setup lang="ts">
/**
 * Berkas satu perusahaan jasa.
 *
 * Tiga skor digambar berdampingan di atas, dan masing-masing menyebut
 * ANGKANYA SENDIRI, bukan hanya achievement. Achievement adalah yang
 * terendah di antara ketiganya; ditampilkan sendirian, ia memberi tahu
 * bahwa ada yang kurang tanpa memberi tahu di mana.
 *
 * "Layak s.d. risiko" ditulis lengkap seperti itu, tidak disingkat
 * menjadi label telanjang. Nilai "Kritis" pada skor 100% berarti mitra
 * ini layak menangani pekerjaan berisiko kritis — bukan bahwa keadaannya
 * kritis, yang persis kebalikannya, dan yang akan disimpulkan setiap
 * orang yang membaca satu kata itu sendirian.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
import { propHalaman } from '../../halaman';
import { KEADAAN } from '../../Grafik/warna';

const props = propHalaman();
const { dialog, tanya, batal, lanjut } = useDialog();

const id = computed(() => props.pjp?.id);

/* Tombol hapus hanya untuk administrator, sejalan dengan rutenya yang
   berjaga `can:admin`. Menyembunyikannya di sini BUKAN penjagaannya —
   penjagaannya di rute — melainkan supaya tombol yang tidak akan pernah
   berhasil tidak dipertontonkan sebagai pilihan. */
const admin = computed(() => Boolean(props.pengguna?.admin));

function nada(skor: number | null | undefined): string {
  if (skor === null || skor === undefined) return KEADAAN.netral;
  if (skor >= 80) return KEADAAN.baik;
  if (skor >= 55) return KEADAAN.ingat;
  return KEADAAN.gawat;
}

const angka = (v: number | null | undefined) => (v === null || v === undefined ? '—' : v);

const ukuran = (b: number) => (b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB');

const tanggal = (v: string | null) =>
  v ? new Date(v).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

/* ── unggah dokumen ── */
const unggah = useForm<{ jenis: string; periode: string; catatan: string; berkas: File | null }>({
  jenis: 'laporan_bulanan', periode: '', catatan: '', berkas: null,
});

const berkasInput = ref<HTMLInputElement | null>(null);

function pilihBerkas(e: Event) {
  unggah.berkas = (e.target as HTMLInputElement).files?.[0] ?? null;
}

function kirimDokumen() {
  unggah.post(`/pjp/${id.value}/laporan`, {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => {
      unggah.reset();
      if (berkasInput.value) berkasInput.value.value = '';
    },
  });
}

function nilaiDokumen(laporanId: number, nilai: string) {
  router.patch(`/pjp/${id.value}/laporan/${laporanId}`,
    { kesesuaian_isi: nilai || null }, { preserveScroll: true });
}

async function hapusDokumen(l: any) {
  if (!await tanya({
    judul: 'Hapus dokumen ini?',
    pesan: `${l.file_name} akan dibuang beserta berkasnya, dan skor pelaporan ikut dihitung ulang.`,
    labelAksi: 'Hapus', nada: 'bahaya',
  })) return;

  router.delete(`/pjp/${id.value}/laporan/${l.id}`, { preserveScroll: true });
}

/* ── evaluasi semesteran ── */
const evaluasi = useForm({
  tahun: new Date().getFullYear(),
  semester: new Date().getMonth() < 6 ? 1 : 2,
  skor_teknis: 0, skor_keselamatan_kesehatan: 0, skor_lingkungan: 0,
  catatan: '',
});

function kirimEvaluasi() {
  evaluasi.post(`/pjp/${id.value}/evaluasi`, { preserveScroll: true });
}

async function hapusEvaluasi(e: any) {
  if (!await tanya({
    judul: 'Hapus penilaian ini?',
    pesan: `Evaluasi ${e.tahun} semester ${e.semester} akan dibuang.`,
    labelAksi: 'Hapus', nada: 'bahaya',
  })) return;

  router.delete(`/pjp/${id.value}/evaluasi/${e.id}`, { preserveScroll: true });
}

async function hapusMitra() {
  if (!await tanya({
    judul: 'Hapus perusahaan jasa ini?',
    pesan: 'Seluruh dokumen, jawaban daftar periksa, dan evaluasinya ikut terhapus. Tindakan ini tidak dapat dibatalkan.',
    labelAksi: 'Hapus', nada: 'bahaya',
    tegasNama: props.pjp?.nama_perusahaan,
  })) return;

  router.delete(`/pjp/${id.value}`);
}
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1200px] mx-auto space-y-5">

    <!-- ══════════ kepala ══════════ -->
    <section class="flex flex-wrap items-start justify-between gap-4">
      <div class="min-w-0">
        <p class="text-[11px] text-stone-400">
          <Link href="/pjp/daftar" class="hover:underline">Perusahaan Jasa</Link> ›
        </p>
        <h2 class="text-xl font-bold text-cam-ink">{{ props.pjp?.nama_perusahaan }}</h2>
        <p class="text-[12.5px] text-stone-500 mt-0.5">
          <span class="num">{{ props.pjp?.nib || 'NIB belum diisi' }}</span>
          · {{ props.pjp?.penanggung_jawab || 'penanggung jawab belum diisi' }}
        </p>
      </div>

      <span class="inline-flex flex-wrap gap-2">
        <Link :href="`/pjp/${id}/checklist`" class="eq-btn-lain">Daftar periksa SMKP</Link>
        <Link :href="`/pjp/${id}/ubah`" class="eq-btn-lain">Ubah data</Link>
        <button v-if="admin" type="button" class="eq-btn-tolak" @click="hapusMitra">Hapus</button>
      </span>
    </section>

    <!-- ══════════ tiga skor + achievement ══════════ -->
    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <Link :href="`/pjp/${id}/checklist`"
            class="rounded-2xl bg-white border border-stone-100 shadow-card px-4 py-3.5 hover:border-stone-200 transition">
        <p class="text-[10.5px] uppercase tracking-wide text-stone-400">Prakualifikasi SMKP</p>
        <p class="text-[26px] font-bold leading-none mt-1 num" :style="{ color: nada(props.smkp?.persentase) }">
          {{ angka(props.smkp?.persentase) }}<span class="text-[13px] font-semibold">%</span>
        </p>
        <p class="text-[11px] text-stone-500 mt-1">
          Layak s.d. risiko <b>{{ props.smkp?.kelayakan }}</b>
        </p>
      </Link>

      <div class="rounded-2xl bg-white border border-stone-100 shadow-card px-4 py-3.5">
        <p class="text-[10.5px] uppercase tracking-wide text-stone-400">Kepatuhan pelaporan</p>
        <p class="text-[26px] font-bold leading-none mt-1 num" :style="{ color: nada(props.pelaporan) }">
          {{ angka(props.pelaporan) }}<span v-if="props.pelaporan !== null" class="text-[13px] font-semibold">%</span>
        </p>
        <p class="text-[11px] text-stone-500 mt-1">
          {{ props.pelaporan === null ? 'Belum ada dokumen yang diunggah' : 'Ketepatan waktu dan kesesuaian isi' }}
        </p>
      </div>

      <div class="rounded-2xl bg-white border border-stone-100 shadow-card px-4 py-3.5">
        <p class="text-[10.5px] uppercase tracking-wide text-stone-400">Evaluasi terakhir</p>
        <p class="text-[26px] font-bold leading-none mt-1 num"
           :style="{ color: nada(props.evaluasi?.[0]?.skor_rata_rata) }">
          {{ angka(props.evaluasi?.[0]?.skor_rata_rata) }}
        </p>
        <p class="text-[11px] text-stone-500 mt-1">
          {{ props.evaluasi?.[0] ? `${props.evaluasi[0].tahun} semester ${props.evaluasi[0].semester}` : 'Belum pernah dinilai' }}
        </p>
      </div>

      <div class="rounded-2xl bg-white border border-stone-100 shadow-card px-4 py-3.5">
        <p class="text-[10.5px] uppercase tracking-wide text-stone-400">Achievement</p>
        <p class="text-[26px] font-bold leading-none mt-1 num" :style="{ color: nada(props.achievement) }">
          {{ angka(props.achievement) }}
        </p>
        <p class="text-[11px] text-stone-500 mt-1">
          Terendah dari ketiganya, ambang {{ props.ambang }}
        </p>
      </div>
    </section>

    <!-- ══════════ syarat wajib legalitas ══════════ -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card px-5 py-4 flex flex-wrap items-center gap-4">
      <div class="flex-1 min-w-0">
        <h3 class="text-[13.5px] font-bold text-cam-ink">Dokumen legalitas</h3>
        <p class="text-[11px] text-stone-500 mt-0.5">
          Syarat wajib, terpisah dari skor berbobot — tidak punya nilai tengah.
        </p>
      </div>

      <p class="text-[15px] font-bold num"
         :style="{ color: props.legalitas?.lengkap === props.legalitas?.total ? KEADAAN.baik : KEADAAN.serius }">
        {{ props.legalitas?.lengkap ?? 0 }} / {{ props.legalitas?.total ?? 0 }}
      </p>
    </section>

    <div class="grid gap-4 lg:grid-cols-[1.15fr_.85fr] items-start">

      <!-- ══════════ dokumen berkala ══════════ -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <header class="px-5 py-3.5 border-b border-stone-100">
          <h3 class="text-[13.5px] font-bold text-cam-ink">
            Dokumen berkala <span class="font-normal text-stone-400">| {{ (props.laporan ?? []).length }} berkas</span>
          </h3>
          <p class="text-[11px] text-stone-500 mt-0.5">
            Tepat waktu berarti terkirim tanggal 1–{{ props.batasTanggal }} menurut waktu tambang.
          </p>
        </header>

        <form class="px-5 py-4 grid gap-3 border-b border-stone-100 bg-stone-50/50"
              @submit.prevent="kirimDokumen">
          <div class="grid gap-3 sm:grid-cols-2">
            <label class="grid gap-1">
              <span class="text-[11.5px] font-semibold text-stone-600">Jenis dokumen</span>
              <select v-model="unggah.jenis" class="rounded-lg border-stone-200 text-[12.5px]">
                <option v-for="(label, kode) in (props.JENIS ?? {})" :key="kode" :value="kode">{{ label }}</option>
              </select>
            </label>

            <label class="grid gap-1">
              <span class="text-[11.5px] font-semibold text-stone-600">Periode</span>
              <input v-model="unggah.periode" maxlength="60" placeholder="mis. September 2026"
                     class="rounded-lg border-stone-200 text-[12.5px]">
            </label>
          </div>

          <p v-if="unggah.jenis === 'laporan_triwulan' && !props.triwulanDibuka"
             class="rounded-lg bg-amber-50 px-3 py-2 text-[11.5px] text-amber-800">
            Laporan Triwulan hanya dapat diunggah pada bulan {{ props.bulanTriwulan }}.
          </p>

          <label class="grid gap-1">
            <span class="text-[11.5px] font-semibold text-stone-600">Berkas</span>
            <input ref="berkasInput" type="file" required class="text-[12px]" @change="pilihBerkas">
            <small v-if="unggah.errors.berkas" class="text-[11px] text-red-600">{{ unggah.errors.berkas }}</small>
          </label>

          <label class="grid gap-1">
            <span class="text-[11.5px] font-semibold text-stone-600">Catatan</span>
            <textarea v-model="unggah.catatan" rows="2" class="rounded-lg border-stone-200 text-[12.5px]" />
          </label>

          <span>
            <button type="submit" class="eq-btn-utama" :disabled="unggah.processing">Unggah dokumen</button>
          </span>
        </form>

        <ul v-if="(props.laporan ?? []).length" class="divide-y divide-stone-100">
          <li v-for="l in props.laporan" :key="l.id" class="px-5 py-3.5 grid gap-2">
            <div class="flex flex-wrap items-start gap-2">
              <div class="flex-1 min-w-0">
                <p class="text-[12.5px] font-semibold text-cam-ink">{{ l.jenis_label }}</p>
                <p class="text-[11px] text-stone-500">
                  {{ l.periode || 'tanpa periode' }} · diunggah {{ tanggal(l.dibuat) }} · {{ ukuran(l.file_size) }}
                </p>
                <p v-if="l.catatan" class="text-[11px] text-stone-500 mt-0.5">{{ l.catatan }}</p>
              </div>

              <span class="rounded px-1.5 py-0.5 text-[10px] font-bold shrink-0"
                    :style="{ background: (l.tepat_waktu ? KEADAAN.baik : KEADAAN.gawat) + '1F',
                              color: l.tepat_waktu ? KEADAAN.baik : KEADAAN.gawat }">
                {{ l.tepat_waktu ? 'Tepat waktu' : 'Terlambat' }}
              </span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <a v-if="l.url" :href="l.url" target="_blank" rel="noopener"
                 class="text-[11.5px] font-semibold text-cam-lime-deep hover:underline">{{ l.file_name }}</a>
              <span v-else class="text-[11.5px] text-stone-400">{{ l.file_name }}</span>

              <select class="rounded-lg border-stone-200 text-[11.5px] ml-auto"
                      :value="l.kesesuaian_isi ?? ''"
                      aria-label="Kesesuaian isi dokumen"
                      @change="nilaiDokumen(l.id, ($event.target as HTMLSelectElement).value)">
                <option value="">Belum diperiksa</option>
                <option v-for="(label, kode) in (props.KESESUAIAN ?? {})" :key="kode" :value="kode">{{ label }}</option>
              </select>

              <button v-if="admin" type="button" class="eq-btn-mini" @click="hapusDokumen(l)">Hapus</button>
            </div>
          </li>
        </ul>

        <p v-else class="px-5 py-10 text-center text-[12px] text-stone-400">
          Belum ada dokumen yang diunggah.
        </p>
      </section>

      <!-- ══════════ evaluasi kinerja ══════════ -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <header class="px-5 py-3.5 border-b border-stone-100">
          <h3 class="text-[13.5px] font-bold text-cam-ink">
            Evaluasi kinerja <span class="font-normal text-stone-400">| {{ (props.evaluasi ?? []).length }} semester</span>
          </h3>
          <p class="text-[11px] text-stone-500 mt-0.5">
            Menyimpan pada semester yang sama akan menimpa penilaian sebelumnya.
          </p>
        </header>

        <form class="px-5 py-4 grid gap-3 border-b border-stone-100 bg-stone-50/50"
              @submit.prevent="kirimEvaluasi">
          <div class="grid gap-3 sm:grid-cols-2">
            <label class="grid gap-1">
              <span class="text-[11.5px] font-semibold text-stone-600">Tahun</span>
              <input v-model.number="evaluasi.tahun" type="number" min="2000" max="2100"
                     class="rounded-lg border-stone-200 text-[12.5px] num">
            </label>

            <label class="grid gap-1">
              <span class="text-[11.5px] font-semibold text-stone-600">Semester</span>
              <select v-model.number="evaluasi.semester" class="rounded-lg border-stone-200 text-[12.5px]">
                <option v-for="(label, kode) in (props.SEMESTER ?? {})" :key="kode" :value="Number(kode)">{{ label }}</option>
              </select>
            </label>
          </div>

          <label v-for="s in [
                   ['skor_teknis', 'Teknis'],
                   ['skor_keselamatan_kesehatan', 'Keselamatan & kesehatan'],
                   ['skor_lingkungan', 'Lingkungan'],
                 ]" :key="s[0]" class="grid gap-1">
            <span class="text-[11.5px] font-semibold text-stone-600">{{ s[1] }} (0–100)</span>
            <input v-model.number="(evaluasi as any)[s[0]]" type="number" min="0" max="100"
                   class="rounded-lg border-stone-200 text-[12.5px] num">
            <small v-if="(evaluasi.errors as any)[s[0]]" class="text-[11px] text-red-600">{{ (evaluasi.errors as any)[s[0]] }}</small>
          </label>

          <label class="grid gap-1">
            <span class="text-[11.5px] font-semibold text-stone-600">Catatan</span>
            <textarea v-model="evaluasi.catatan" rows="2" class="rounded-lg border-stone-200 text-[12.5px]" />
          </label>

          <span>
            <button type="submit" class="eq-btn-utama" :disabled="evaluasi.processing">Simpan evaluasi</button>
          </span>
        </form>

        <ul v-if="(props.evaluasi ?? []).length" class="divide-y divide-stone-100">
          <li v-for="e in props.evaluasi" :key="e.id" class="px-5 py-3.5">
            <div class="flex items-start gap-3">
              <div class="flex-1 min-w-0">
                <p class="text-[12.5px] font-semibold text-cam-ink">
                  {{ e.tahun }} · {{ e.semester_label }}
                </p>
                <p class="text-[11px] text-stone-500 mt-0.5">
                  Teknis <b class="num">{{ e.skor_teknis }}</b> ·
                  K3 <b class="num">{{ e.skor_keselamatan_kesehatan }}</b> ·
                  Lingkungan <b class="num">{{ e.skor_lingkungan }}</b>
                </p>
                <p v-if="e.catatan" class="text-[11px] text-stone-500 mt-0.5">{{ e.catatan }}</p>
              </div>

              <p class="text-[16px] font-bold num shrink-0" :style="{ color: nada(e.skor_rata_rata) }">
                {{ e.skor_rata_rata }}
              </p>

              <button v-if="admin" type="button" class="eq-btn-mini shrink-0" @click="hapusEvaluasi(e)">Hapus</button>
            </div>
          </li>
        </ul>

        <p v-else class="px-5 py-10 text-center text-[12px] text-stone-400">
          Belum ada penilaian kinerja.
        </p>
      </section>
    </div>

    <section v-if="props.pjp?.alamat || props.pjp?.catatan"
             class="rounded-2xl bg-white border border-stone-100 shadow-card px-5 py-4 grid gap-2">
      <p v-if="props.pjp?.alamat" class="text-[12px] text-stone-600">
        <span class="text-stone-400">Alamat ·</span> {{ props.pjp.alamat }}
      </p>
      <p v-if="props.pjp?.catatan" class="text-[12px] text-stone-600">
        <span class="text-stone-400">Catatan ·</span> {{ props.pjp.catatan }}
      </p>
    </section>
  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>

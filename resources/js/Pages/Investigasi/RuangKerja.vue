<script setup lang="ts">
/**
 * Ruang kerja investigasi — satu layar untuk seluruh berkas.
 *
 * SATU LAYAR, BUKAN ENAM TAB. Yang dikerjakan di sini adalah satu
 * rantai: bukti menyokong akar masalah, akar melahirkan temuan, temuan
 * melahirkan tindakan perbaikan. Memecahnya menjadi tab membuat rantai
 * itu tidak pernah terlihat utuh — dan rantai yang tidak terlihat adalah
 * rantai yang putus tanpa ada yang menyadarinya.
 *
 * Kolom kanan berisi apa yang MENJELASKAN berkas ini: bukti, tim,
 * keterangan, pembelajaran, dan ringkasan rantainya. Kolom kiri berisi
 * apa yang DIKERJAKAN: kronologi, akar masalah, temuan, tindakan.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import { propHalaman } from '../../halaman';
import { KEADAAN } from '../../Grafik/warna';
import { useDialog } from '../../dialog';
import Dialog from '../../Components/Dialog.vue';
import RelTahap from './RelTahap.vue';

const props = propHalaman();
const { dialog, tanya, batal, lanjut } = useDialog();

const basis = () => `/investigasi/berkas/${props.inv.id}`;

/** Berkas tertutup tidak menerima perubahan — formulirnya pun disembunyikan. */
const bisaUbah = () => props.inv?.status === 'berjalan';

/* Satu formulir per blok, bukan satu bersama. Formulir bersama membuat
   isian yang belum disimpan di satu blok muncul di blok berikutnya yang
   dibuka — dan pada berkas investigasi, kalimat yang nyasar ke temuan
   lain bukan gangguan kecil. */
const fKronologi   = useForm<Record<string, any>>({ waktu: '', peristiwa: '', keterangan: '', penyebab: false });
const fBukti       = useForm<Record<string, any>>({ jenis: 'foto', judul: '', sumber: '', keterangan: '', berkas: null as File | null });
const fAkar        = useForm<Record<string, any>>({ uraian: '', metode: '5why', taksonomi_id: '', bukti: [] as number[] });
const fTemuan      = useForm<Record<string, any>>({ uraian: '', rekomendasi: '', tingkat: 'sedang', akar_id: '' });
const fTim         = useForm<Record<string, any>>({ user_id: '', peran_tim: '' });
const fKeterangan  = useForm<Record<string, any>>({
  ketua_id: props.inv?.ketuaId ?? '', target_selesai: props.inv?.target ?? '',
  prioritas: props.inv?.prioritas ?? 'sedang',
  tujuan: props.inv?.tujuan ?? '', ruang_lingkup: props.inv?.ruangLingkup ?? '',
});
const fBelajar     = useForm<Record<string, any>>({ judul: '', ringkasan: '', pesan_kunci: '' });

/** Formulir tindakan per temuan — dibuka satu per satu. */
const bukaTindakan = ref<number | null>(null);
const fTindakan = reactive<Record<number, any>>({});

function mulaiTindakan(temuanId: number) {
  bukaTindakan.value = bukaTindakan.value === temuanId ? null : temuanId;
  fTindakan[temuanId] ??= { uraian: '', hierarki_id: '', pic_id: '', tenggat: '' };
}

const buka = reactive<Record<string, boolean>>({});

function kirim(f: any, sisa: string, opsi: Record<string, any> = {}) {
  f.post(`${basis()}/${sisa}`, { preserveScroll: true, onSuccess: () => f.reset(), ...opsi });
}

function aksi(sisa: string, isi: Record<string, any> = {}) {
  router.post(`${basis()}/${sisa}`, isi, { preserveScroll: true });
}

async function hapus(sisa: string, apa: string) {
  if (await tanya(`Hapus ${apa}?`)) aksi(sisa);
}

/**
 * Aksi yang menanyakan lebih dulu, dengan kalimatnya sendiri.
 *
 * Terpisah dari `hapus` karena bunyi pertanyaannya berbeda. Mengunci
 * bukti bukan penghapusan, tetapi akibatnya sama tidak dapat diurungkan —
 * dan pertanyaan "Hapus …?" pada tombol Kunci akan membuat orang menekan
 * Batal pada tindakan yang justru diinginkannya.
 */
async function konfirmasi(sisa: string, pesan: string) {
  if (await tanya(pesan)) aksi(sisa);
}

const WARNA_STATUS: Record<string, string> = {
  terbuka:      '#B45309',
  berjalan:     '#0F766E',
  selesai:      KEADAAN.baik,
  diverifikasi: KEADAAN.baik,
  ditutup:      '#78716C',
};
</script>

<template>
  <Head :title="props.inv?.nomor ?? 'Investigasi'" />

  <div class="max-w-[1500px] mx-auto space-y-5">

    <!-- ══════════ kepala ══════════ -->
    <section class="flex flex-wrap items-start justify-between gap-4">
      <div class="min-w-0">
        <p class="text-[11.5px] text-stone-400">
          <Link :href="`/investigasi/insiden/${props.inv?.insiden?.id}`" class="num hover:underline">
            {{ props.inv?.insiden?.nomor }}
          </Link>
          · {{ props.inv?.insiden?.tanggal }}
        </p>
        <h2 class="text-xl font-bold text-cam-ink">{{ props.inv?.judul }}</h2>
        <p class="text-[12.5px] text-stone-500 mt-0.5">
          <span class="num font-semibold">{{ props.inv?.nomor }}</span>
          · Ketua: {{ props.inv?.ketua || '—' }}
          <span v-if="props.inv?.target"> · Target: {{ props.inv.target }}</span>
        </p>
      </div>

      <div class="text-right shrink-0">
        <span class="rounded-lg px-2.5 py-1.5 text-[12px] font-bold"
              :style="{ background: props.inv?.warnaPita + '1F', color: props.inv?.warnaPita }">
          {{ props.inv?.levelNama }}
        </span>
        <p class="text-[11px] text-stone-500 mt-1 num">Skor risiko {{ props.inv?.skor }}</p>
      </div>
    </section>

    <!-- ══════════ rel tahap ══════════ -->
    <RelTahap :rel="props.inv?.rel ?? []" :tugas="props.inv?.tugas"
              :kurang="props.inv?.kurang ?? []" :boleh-maju="props.inv?.bolehMaju && bisaUbah()"
              @maju="aksi('tahap/maju')" @mundur="aksi('tahap/mundur')" />

    <!-- ══════════ dua layar kerja yang dipisahkan ══════════

         Analisis SCAT dan wawancara punya halamannya sendiri: keduanya
         menampung katalog panjang — 252 butir penyebab dan puluhan
         pertanyaan — dan menempelkannya ke sini membuat halaman yang
         sudah sepuluh blok tidak terbaca.

         Tautannya ditaruh DI ATAS, sejajar rel tahap, bukan di dalam
         blok analisis di bawah. Yang di bawah baru ditemukan sesudah
         digulir melewati bukti dan kronologi, dan tahap Analisis
         justru yang paling sering ditinggalkan setengah jalan. -->
    <section class="grid gap-3 sm:grid-cols-2">
      <Link :href="`/investigasi/berkas/${props.inv?.id}/analisis`"
            class="rounded-2xl bg-white border border-stone-100 shadow-card px-5 py-3.5 hover:border-stone-200">
        <p class="text-[13px] font-bold text-cam-ink">Analisis penyebab — SCAT →</p>
        <p class="text-[11.5px] text-stone-500 mt-0.5">
          Tiga lapis: tindakan &amp; kondisi, sebab dasar, lack of control.
          Lapis berikutnya diusulkan dari pilihan lapis sebelumnya.
        </p>
      </Link>

      <Link :href="`/investigasi/berkas/${props.inv?.id}/wawancara`"
            class="rounded-2xl bg-white border border-stone-100 shadow-card px-5 py-3.5 hover:border-stone-200">
        <p class="text-[13px] font-bold text-cam-ink">Wawancara saksi →</p>
        <p class="text-[11.5px] text-stone-500 mt-0.5">
          Panduan pertanyaan yang menyesuaikan diri pada peran narasumber
          dan kewenangannya atas hierarki pengendalian.
        </p>
      </Link>
    </section>

    <!-- Metode wajib menurut levelnya — disebut, bukan dipaksakan.
         Menilai kecocokan metode adalah pekerjaan KTT, bukan palang
         yang menghentikan orang di tengah pekerjaan. -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card px-5 py-3.5">
      <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
        <div>
          <p class="text-[10px] uppercase tracking-wide text-stone-400">Metode yang diwajibkan level ini</p>
          <div class="flex flex-wrap gap-1.5 mt-1">
            <span v-for="m in (props.inv?.metodeWajib ?? [])" :key="m"
                  class="rounded-md px-2 py-1 text-[10.5px] font-bold uppercase"
                  style="background:#F6EEDF;color:#0F766E">{{ m }}</span>
          </div>
        </div>
        <div class="ml-auto text-right">
          <p class="text-[10px] uppercase tracking-wide text-stone-400">Penyetuju penutupan</p>
          <p class="text-[12px] font-semibold text-cam-ink">{{ props.inv?.penyetuju || '—' }}</p>
        </div>
      </div>
    </section>

    <div class="grid gap-4 lg:grid-cols-[1.3fr_.7fr] items-start">

      <!-- ══════════════ KOLOM KIRI — yang dikerjakan ══════════════ -->
      <div class="grid gap-4">

        <!-- kronologi -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">
              Rekonstruksi kronologi
              <span class="font-normal text-stone-400">| {{ (props.inv?.kronologi ?? []).length }} peristiwa</span>
            </h3>
          </header>

          <ul v-if="(props.inv?.kronologi ?? []).length" class="divide-y divide-stone-100">
            <li v-for="k in props.inv.kronologi" :key="k.id" class="px-5 py-3 flex items-start gap-3">
              <span class="text-[11px] text-stone-400 num w-28 shrink-0">{{ k.waktu || '—' }}</span>
              <span class="min-w-0 flex-1">
                <span class="text-[12.5px] font-semibold text-cam-ink">
                  {{ k.peristiwa }}
                  <!-- Peristiwa yang diduga penyebab ditandai: kata pada
                       peristiwa inilah yang dibaca mesin saran SCAT. -->
                  <span v-if="k.penyebab" class="ml-1.5 rounded px-1.5 py-0.5 text-[10px] font-bold align-middle"
                        :style="{ background: KEADAAN.serius + '1F', color: KEADAAN.serius }">penyebab</span>
                </span>
                <span v-if="k.keterangan" class="block text-[11.5px] text-stone-500 mt-0.5">{{ k.keterangan }}</span>
              </span>

              <button v-if="bisaUbah()" type="button" class="shrink-0 text-red-600 text-[11px]"
                      @click="hapus(`kronologi/${k.id}/hapus`, 'peristiwa ini')">Hapus</button>
            </li>
          </ul>

          <p v-else class="px-5 py-8 text-center text-[12px] text-stone-400">Belum ada peristiwa dicatat.</p>

          <form v-if="bisaUbah()" class="px-5 py-4 border-t border-stone-100 grid gap-3"
                @submit.prevent="kirim(fKronologi, 'kronologi')">
            <div class="grid gap-3 md:grid-cols-[180px_1fr]">
              <label class="grid gap-1">
                <span class="text-[10px] uppercase tracking-wide text-stone-400">Waktu</span>
                <input v-model="fKronologi.waktu" type="datetime-local" class="rounded-lg border-stone-200 text-[12px]">
              </label>
              <label class="grid gap-1">
                <span class="text-[10px] uppercase tracking-wide text-stone-400">
                  Peristiwa<span class="text-red-600">*</span>
                </span>
                <input v-model="fKronologi.peristiwa" class="rounded-lg border-stone-200 text-[12px]"
                       placeholder="Operator mengerem memasuki tikungan">
              </label>
            </div>

            <label class="grid gap-1">
              <span class="text-[10px] uppercase tracking-wide text-stone-400">Keterangan</span>
              <input v-model="fKronologi.keterangan" class="rounded-lg border-stone-200 text-[12px]">
            </label>

            <div class="flex flex-wrap items-center gap-3">
              <!-- Tanda "penyebab" bukan hiasan: kata pada peristiwa
                   bertanda inilah yang dibaca mesin saran penyebab. -->
              <label class="flex items-center gap-2 text-[12px] cursor-pointer">
                <input v-model="fKronologi.penyebab" type="checkbox" class="rounded border-stone-300">
                <span>Diduga menjadi penyebab</span>
              </label>

              <span class="inline-flex ml-auto">
                <button class="eq-btn-utama" :disabled="fKronologi.processing || !fKronologi.peristiwa">
                  + Tambah peristiwa
                </button>
              </span>
            </div>
          </form>
        </section>

        <!-- akar masalah -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">
              Akar masalah <span class="font-normal text-stone-400">| {{ (props.inv?.akar ?? []).length }} akar</span>
            </h3>
            <p class="text-[11px] text-stone-500 mt-0.5">
              Setiap akar masalah harus dapat ditelusuri ke bukti. Akar tanpa bukti adalah pendapat,
              dan pendapat tidak bertahan di depan Inspektur Tambang.
            </p>
          </header>

          <ul v-if="(props.inv?.akar ?? []).length" class="divide-y divide-stone-100">
            <li v-for="a in props.inv.akar" :key="a.id" class="px-5 py-3">
              <p class="text-[12.5px] font-semibold text-cam-ink">{{ a.uraian }}</p>

              <div class="flex flex-wrap items-center gap-2 mt-1.5">
                <span v-if="a.taksonomi" class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase"
                      style="background:#F6EEDF;color:#0F766E">{{ a.taksonomi.metode }}</span>
                <span v-if="a.taksonomi" class="text-[11px] text-stone-500 num">
                  {{ a.taksonomi.kode }} — {{ a.taksonomi.label }}
                </span>
                <span v-else class="text-[11px] text-stone-400">5 Why</span>
              </div>

              <div class="flex items-center gap-3 mt-1">
                <p class="text-[11px]" :style="{ color: a.disokong ? KEADAAN.baik : KEADAAN.ingat }">
                  {{ a.disokong ? '✓ disokong ' + a.bukti.join(', ') : '⚠ belum disokong bukti' }}
                </p>
                <button v-if="bisaUbah()" type="button" class="text-red-600 text-[11px] ml-auto"
                        @click="hapus(`akar/${a.id}/hapus`, 'akar masalah ini')">Hapus</button>
              </div>
            </li>
          </ul>

          <p v-else class="px-5 py-8 text-center text-[12px] text-stone-400">Akar masalah belum dirumuskan.</p>

          <form v-if="bisaUbah()" class="px-5 py-4 border-t border-stone-100 grid gap-3"
                @submit.prevent="kirim(fAkar, 'akar')">
            <label class="grid gap-1">
              <span class="text-[10px] uppercase tracking-wide text-stone-400">
                Akar masalah<span class="text-red-600">*</span>
              </span>
              <textarea v-model="fAkar.uraian" rows="2" class="rounded-lg border-stone-200 text-[12px]"
                        placeholder="Interval penggantian kampas rem tidak disesuaikan dengan gradien jalan."></textarea>
            </label>

            <!-- Bukti yang menyokongnya dipilih DI SINI, bukan belakangan.
                 Akar tanpa bukti adalah pendapat, dan pendapat yang sudah
                 tercatat jarang kembali dilengkapi. -->
            <div v-if="(props.inv?.bukti ?? []).length">
              <p class="text-[10px] uppercase tracking-wide text-stone-400 mb-1.5">Disokong bukti</p>
              <div class="flex flex-wrap gap-2">
                <label v-for="b in props.inv.bukti" :key="b.id"
                       class="flex items-center gap-1.5 rounded-lg border border-stone-200 px-2.5 py-1.5 text-[11px] cursor-pointer">
                  <input v-model="fAkar.bukti" :value="b.id" type="checkbox" class="rounded border-stone-300">
                  <span class="num">{{ b.nomor }}</span>
                </label>
              </div>
            </div>

            <div class="flex items-center gap-3">
              <span class="inline-flex">
                <button class="eq-btn-utama" :disabled="fAkar.processing || !fAkar.uraian">+ Tambah akar masalah</button>
              </span>
              <p v-if="!fAkar.bukti.length" class="text-[11px] text-stone-400">
                Belum memilih bukti — akarnya akan ditandai belum disokong.
              </p>
            </div>
          </form>
        </section>

        <!-- temuan dan tindakan -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">
              Temuan &amp; tindakan perbaikan
              <span class="font-normal text-stone-400">| {{ (props.inv?.temuan ?? []).length }} temuan</span>
            </h3>
          </header>

          <ul v-if="(props.inv?.temuan ?? []).length" class="divide-y divide-stone-100">
            <li v-for="t in props.inv.temuan" :key="t.id" class="px-5 py-4">
              <div class="flex items-start gap-2 flex-wrap">
                <span class="rounded px-1.5 py-0.5 text-[10px] font-bold num"
                      style="background:#F5F5F4;color:#78716C">{{ t.nomor }}</span>
                <span class="rounded px-1.5 py-0.5 text-[10px] font-bold capitalize"
                      :style="{ background: (t.tingkat === 'tinggi' ? KEADAAN.gawat : KEADAAN.ingat) + '1F',
                                color: t.tingkat === 'tinggi' ? KEADAAN.gawat : KEADAAN.ingat }">
                  {{ t.tingkat }}
                </span>
              </div>

              <p class="text-[12.5px] font-semibold text-cam-ink mt-1.5">{{ t.uraian }}</p>
              <p v-if="t.rekomendasi" class="text-[11.5px] text-stone-500 italic mt-0.5">
                Rekomendasi: {{ t.rekomendasi }}
              </p>

              <ul class="mt-2.5 grid gap-1.5">
                <li v-for="x in t.tindakan" :key="x.id"
                    class="rounded-xl border border-stone-100 px-3 py-2 flex flex-wrap items-center gap-2">
                  <span class="rounded px-1.5 py-0.5 text-[10px] font-bold num"
                        style="background:#F5F5F4;color:#78716C">{{ x.nomor }}</span>

                  <span class="text-[12px] text-cam-ink flex-1 min-w-0">{{ x.uraian }}</span>

                  <!-- Tingkat hierarki pengendalian tercantum: berkas
                       yang seluruh tindakannya bertingkat 4 dan 5 adalah
                       berkas yang tidak mengubah apa pun di lapangan. -->
                  <span v-if="x.hierarki" class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                        :style="{ background: (x.tingkat <= 3 ? KEADAAN.baik : '#B45309') + '1F',
                                  color: x.tingkat <= 3 ? KEADAAN.baik : '#B45309' }">
                    {{ x.hierarki }}
                  </span>

                  <span class="rounded px-1.5 py-0.5 text-[10px] font-bold"
                        :style="{ background: (WARNA_STATUS[x.status] ?? '#78716C') + '1F',
                                  color: WARNA_STATUS[x.status] ?? '#78716C' }">
                    {{ x.statusLabel }}
                  </span>

                  <span class="text-[10.5px] text-stone-400 num whitespace-nowrap">
                    {{ x.pic || '—' }} · {{ x.tenggat || 'tanpa tenggat' }}
                  </span>

                  <span v-if="x.telat" class="text-[10.5px] font-bold num" :style="{ color: KEADAAN.gawat }">
                    telat {{ x.telat }} hari
                  </span>

                  <!-- Status diubah lewat menu, bukan tombol berderet:
                       lima status berderet menjadi lima tombol yang
                       sebagian besar tidak masuk akal pada saat itu. -->
                  <select v-if="bisaUbah()" :value="x.status"
                          class="rounded-lg border-stone-200 text-[11px] py-1"
                          aria-label="Ubah status tindakan"
                          @change="aksi(`tindakan/${x.id}/status`, { status: ($event.target as HTMLSelectElement).value })">
                    <option v-for="(nama, k) in (props.opsi?.statusTindakan ?? {})" :key="k" :value="k">
                      {{ nama }}
                    </option>
                  </select>
                </li>
              </ul>

              <div v-if="bisaUbah()" class="mt-2">
                <button type="button" class="text-[11.5px] font-semibold text-cam-lime-deep"
                        @click="mulaiTindakan(t.id)">
                  {{ bukaTindakan === t.id ? 'Tutup' : '+ Tindakan perbaikan' }}
                </button>

                <form v-if="bukaTindakan === t.id" class="mt-2 grid gap-2.5 rounded-xl border border-stone-100 p-3"
                      @submit.prevent="router.post(`${basis()}/temuan/${t.id}/tindakan`, fTindakan[t.id],
                                                   { preserveScroll: true, onSuccess: () => { bukaTindakan = null; } })">
                  <label class="grid gap-1">
                    <span class="text-[10px] uppercase tracking-wide text-stone-400">
                      Tindakan<span class="text-red-600">*</span>
                    </span>
                    <input v-model="fTindakan[t.id].uraian" class="rounded-lg border-stone-200 text-[12px]"
                           placeholder="Revisi jadwal perawatan sistem rem berdasarkan gradien jalan">
                  </label>

                  <div class="grid gap-2.5 md:grid-cols-3">
                    <!-- Tingkat pengendaliannya diminta, dan itu bukan
                         kelengkapan: investigasi yang seluruh tindakannya
                         bertingkat 4 dan 5 tidak mengubah apa pun di
                         lapangan, dan hanya kolom ini yang membuatnya
                         terlihat dari jauh. -->
                    <label class="grid gap-1">
                      <span class="text-[10px] uppercase tracking-wide text-stone-400">Hierarki kendali</span>
                      <select v-model="fTindakan[t.id].hierarki_id" class="rounded-lg border-stone-200 text-[12px]">
                        <option value="">—</option>
                        <option v-for="h in (props.opsi?.hierarki ?? [])" :key="h.id" :value="h.id">
                          {{ h.tingkat }} · {{ h.nama }}
                        </option>
                      </select>
                    </label>

                    <label class="grid gap-1">
                      <span class="text-[10px] uppercase tracking-wide text-stone-400">PIC</span>
                      <select v-model="fTindakan[t.id].pic_id" class="rounded-lg border-stone-200 text-[12px]">
                        <option value="">—</option>
                        <option v-for="o in (props.opsi?.orang ?? [])" :key="o.id" :value="o.id">{{ o.name }}</option>
                      </select>
                    </label>

                    <label class="grid gap-1">
                      <span class="text-[10px] uppercase tracking-wide text-stone-400">Tenggat</span>
                      <input v-model="fTindakan[t.id].tenggat" type="date" class="rounded-lg border-stone-200 text-[12px]">
                    </label>
                  </div>

                  <span class="inline-flex">
                    <button class="eq-btn-utama" :disabled="!fTindakan[t.id].uraian">Simpan tindakan</button>
                  </span>
                </form>
              </div>

              <button v-if="bisaUbah()" type="button" class="mt-2 text-red-600 text-[11px]"
                      @click="hapus(`temuan/${t.id}/hapus`, `temuan ${t.nomor}`)">Hapus temuan</button>
            </li>
          </ul>

          <p v-else class="px-5 py-8 text-center text-[12px] text-stone-400">Belum ada temuan dirumuskan.</p>

          <form v-if="bisaUbah()" class="px-5 py-4 border-t border-stone-100 grid gap-3"
                @submit.prevent="kirim(fTemuan, 'temuan')">
            <label class="grid gap-1">
              <span class="text-[10px] uppercase tracking-wide text-stone-400">
                Temuan<span class="text-red-600">*</span>
              </span>
              <textarea v-model="fTemuan.uraian" rows="2" class="rounded-lg border-stone-200 text-[12px]"></textarea>
            </label>

            <label class="grid gap-1">
              <span class="text-[10px] uppercase tracking-wide text-stone-400">Rekomendasi</span>
              <input v-model="fTemuan.rekomendasi" class="rounded-lg border-stone-200 text-[12px]">
            </label>

            <div class="grid gap-3 md:grid-cols-[160px_1fr_auto] items-end">
              <label class="grid gap-1">
                <span class="text-[10px] uppercase tracking-wide text-stone-400">Tingkat</span>
                <select v-model="fTemuan.tingkat" class="rounded-lg border-stone-200 text-[12px]">
                  <option v-for="(nama, k) in (props.opsi?.tingkatTemuan ?? {})" :key="k" :value="k">{{ nama }}</option>
                </select>
              </label>

              <label class="grid gap-1">
                <span class="text-[10px] uppercase tracking-wide text-stone-400">Dari akar masalah</span>
                <select v-model="fTemuan.akar_id" class="rounded-lg border-stone-200 text-[12px]">
                  <option value="">—</option>
                  <option v-for="a in (props.inv?.akar ?? [])" :key="a.id" :value="a.id">
                    {{ a.uraian.slice(0, 70) }}
                  </option>
                </select>
              </label>

              <span class="inline-flex">
                <button class="eq-btn-utama" :disabled="fTemuan.processing || !fTemuan.uraian">+ Tambah temuan</button>
              </span>
            </div>
          </form>
        </section>
      </div>

      <!-- ══════════════ KOLOM KANAN — yang menjelaskan ══════════════ -->
      <div class="grid gap-4">

        <!-- bukti -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">
              Bukti <span class="font-normal text-stone-400">| {{ (props.inv?.bukti ?? []).length }} berkas</span>
            </h3>
            <p class="text-[11px] text-stone-500 mt-0.5">
              Tiap bukti disidik jarinya dengan SHA-256 lalu dikunci. Bukti yang bisa diam-diam diganti
              sesudah dikumpulkan tidak ada nilainya saat pemeriksaan.
            </p>
          </header>

          <ul v-if="(props.inv?.bukti ?? []).length" class="divide-y divide-stone-100">
            <li v-for="b in props.inv.bukti" :key="b.id" class="px-5 py-3">
              <div class="flex items-start gap-2">
                <span class="min-w-0 flex-1">
                  <span class="text-[12px] font-semibold text-cam-ink block">{{ b.judul }}</span>
                  <span class="text-[10.5px] text-stone-400 num block">
                    {{ b.nomor }} · {{ b.jenisLabel }}<span v-if="b.tanggal"> · {{ b.tanggal }}</span>
                  </span>
                  <span v-if="b.sidik" class="text-[10px] text-stone-300 num block truncate">
                    sha256 {{ b.sidik }}…
                  </span>
                </span>

                <span class="shrink-0 flex items-center gap-1.5">
                  <a v-if="b.url" :href="b.url" target="_blank" rel="noopener"
                     class="text-[11px] font-bold text-cam-lime-deep hover:underline">Buka</a>

                  <!-- Mengunci SATU ARAH: tidak ada tombol membukanya
                       lagi. Kunci yang dapat dibuka kembali tidak
                       menjamin apa pun — ia hanya menambah satu langkah
                       bagi siapa pun yang hendak mengganti isinya, dan
                       langkah yang dapat dilewati bukan penjagaan. -->
                  <span v-if="b.dikunci" :title="`Dikunci oleh ${b.pengunci ?? '—'}`"
                        class="text-[12px]" :style="{ color: KEADAAN.baik }">🔒</span>

                  <template v-else-if="bisaUbah()">
                    <button type="button" class="text-[11px] font-bold" :style="{ color: KEADAAN.baik }"
                            @click="konfirmasi(`bukti/${b.id}/kunci`,
                                      `Kunci bukti ${b.nomor}? Sesudah dikunci ia tidak dapat dihapus maupun diganti berkasnya.`)">
                      Kunci
                    </button>
                    <button type="button" class="text-red-600 text-[11px]"
                            @click="hapus(`bukti/${b.id}/hapus`, `bukti ${b.nomor}`)">Hapus</button>
                  </template>
                </span>
              </div>
            </li>
          </ul>

          <p v-else class="px-5 py-8 text-center text-[12px] text-stone-400">Belum ada bukti dikumpulkan.</p>

          <form v-if="bisaUbah()" class="px-5 py-4 border-t border-stone-100 grid gap-3"
                @submit.prevent="kirim(fBukti, 'bukti', { forceFormData: true })">
            <div class="grid gap-3 md:grid-cols-[130px_1fr]">
              <label class="grid gap-1">
                <span class="text-[10px] uppercase tracking-wide text-stone-400">Jenis</span>
                <select v-model="fBukti.jenis" class="rounded-lg border-stone-200 text-[12px]">
                  <option v-for="(nama, k) in (props.opsi?.jenisBukti ?? {})" :key="k" :value="k">{{ nama }}</option>
                </select>
              </label>

              <label class="grid gap-1">
                <span class="text-[10px] uppercase tracking-wide text-stone-400">
                  Judul<span class="text-red-600">*</span>
                </span>
                <input v-model="fBukti.judul" class="rounded-lg border-stone-200 text-[12px]"
                       placeholder="Foto posisi akhir unit dan bekas ban">
              </label>
            </div>

            <label class="grid gap-1">
              <span class="text-[10px] uppercase tracking-wide text-stone-400">Sumber</span>
              <input v-model="fBukti.sumber" class="rounded-lg border-stone-200 text-[12px]">
            </label>

            <label class="grid gap-1">
              <span class="text-[10px] uppercase tracking-wide text-stone-400">Berkas</span>
              <input type="file" class="w-full rounded-lg border-stone-200 text-[11.5px]"
                     @change="fBukti.berkas = ($event.target as HTMLInputElement).files?.[0] ?? null">
              <span class="text-[10.5px] text-stone-400">
                Sidik jari SHA-256 dihitung sekali saat berkasnya masuk, lalu tidak pernah dihitung ulang.
              </span>
            </label>

            <span class="inline-flex">
              <button class="eq-btn-utama" :disabled="fBukti.processing || !fBukti.judul">+ Tambah bukti</button>
            </span>
          </form>
        </section>

        <!-- tim -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">
              Tim investigasi <span class="font-normal text-stone-400">| {{ (props.inv?.tim ?? []).length }} anggota</span>
            </h3>
          </header>

          <ul v-if="(props.inv?.tim ?? []).length" class="divide-y divide-stone-100">
            <li v-for="t in props.inv.tim" :key="t.id" class="px-5 py-2.5 flex items-center justify-between gap-3">
              <span class="text-[12px] font-semibold text-cam-ink">{{ t.nama }}</span>
              <span class="text-[11px] text-stone-400">{{ t.peran || '—' }}</span>
              <button v-if="bisaUbah()" type="button" class="text-red-600 text-[11px]"
                      @click="hapus(`tim/${t.id}/hapus`, `${t.nama} dari tim`)">Lepas</button>
            </li>
          </ul>

          <p v-else class="px-5 py-6 text-center text-[12px] text-stone-400">Tim belum dibentuk.</p>

          <form v-if="bisaUbah()" class="px-5 py-4 border-t border-stone-100 grid gap-2.5"
                @submit.prevent="kirim(fTim, 'tim')">
            <div class="grid gap-2.5 sm:grid-cols-2">
              <select v-model="fTim.user_id" class="rounded-lg border-stone-200 text-[12px]" aria-label="Pilih orang">
                <option value="">— pilih orang —</option>
                <option v-for="o in (props.opsi?.orang ?? [])" :key="o.id" :value="o.id">{{ o.name }}</option>
              </select>
              <select v-model="fTim.peran_tim" class="rounded-lg border-stone-200 text-[12px]" aria-label="Peran tim">
                <option value="">— peran —</option>
                <option v-for="p in (props.opsi?.peranTim ?? [])" :key="p" :value="p">{{ p }}</option>
              </select>
            </div>
            <span class="inline-flex">
              <button class="eq-btn-utama" :disabled="fTim.processing || !fTim.user_id">+ Tambah anggota</button>
            </span>
          </form>
        </section>

        <!-- keterangan -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">
              Keterangan investigasi <span class="font-normal text-stone-400">| syarat tahap Perencanaan</span>
            </h3>
          </header>

          <form v-if="bisaUbah()" class="px-5 py-4 grid gap-3"
                @submit.prevent="fKeterangan.post(`${basis()}/keterangan`, { preserveScroll: true })">
            <div class="grid gap-3 sm:grid-cols-2">
              <label class="grid gap-1">
                <span class="text-[10px] uppercase tracking-wide text-stone-400">Ketua investigasi</span>
                <select v-model="fKeterangan.ketua_id" class="rounded-lg border-stone-200 text-[12px]">
                  <option value="">—</option>
                  <option v-for="o in (props.opsi?.orang ?? [])" :key="o.id" :value="o.id">{{ o.name }}</option>
                </select>
              </label>

              <label class="grid gap-1">
                <span class="text-[10px] uppercase tracking-wide text-stone-400">Target selesai</span>
                <input v-model="fKeterangan.target_selesai" type="date" class="rounded-lg border-stone-200 text-[12px]">
              </label>
            </div>

            <label class="grid gap-1">
              <span class="text-[10px] uppercase tracking-wide text-stone-400">Prioritas</span>
              <select v-model="fKeterangan.prioritas" class="rounded-lg border-stone-200 text-[12px]">
                <option value="rendah">Rendah</option>
                <option value="sedang">Sedang</option>
                <option value="tinggi">Tinggi</option>
              </select>
            </label>

            <label class="grid gap-1">
              <span class="text-[10px] uppercase tracking-wide text-stone-400">Tujuan</span>
              <textarea v-model="fKeterangan.tujuan" rows="2" class="rounded-lg border-stone-200 text-[12px]"></textarea>
            </label>

            <label class="grid gap-1">
              <span class="text-[10px] uppercase tracking-wide text-stone-400">Ruang lingkup</span>
              <textarea v-model="fKeterangan.ruang_lingkup" rows="2" class="rounded-lg border-stone-200 text-[12px]"></textarea>
            </label>

            <span class="inline-flex">
              <button class="eq-btn-utama" :disabled="fKeterangan.processing">Simpan keterangan</button>
            </span>
          </form>

          <dl v-else class="px-5 py-4 grid gap-2.5">
            <div>
              <dt class="text-[10px] uppercase tracking-wide text-stone-400">Tujuan</dt>
              <dd class="text-[12px] text-cam-ink">{{ props.inv?.tujuan || '—' }}</dd>
            </div>
            <div>
              <dt class="text-[10px] uppercase tracking-wide text-stone-400">Ruang lingkup</dt>
              <dd class="text-[12px] text-cam-ink">{{ props.inv?.ruangLingkup || '—' }}</dd>
            </div>
            <div>
              <dt class="text-[10px] uppercase tracking-wide text-stone-400">Prioritas</dt>
              <dd class="text-[12px] text-cam-ink capitalize">{{ props.inv?.prioritas }}</dd>
            </div>
          </dl>
        </section>

        <!-- pembelajaran -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">
              Pembelajaran <span class="font-normal text-stone-400">| {{ (props.inv?.pembelajaran ?? []).length }} terbit</span>
            </h3>
            <p class="text-[11px] text-stone-500 mt-0.5">
              Yang dibaca site lain bukan seluruh berkas ini, melainkan satu paragraf. Tanpa
              pembelajaran yang disebarkan, penyebab yang sama akan ditemukan lagi di tempat lain
              dari awal.
            </p>
          </header>

          <ul v-if="(props.inv?.pembelajaran ?? []).length" class="divide-y divide-stone-100">
            <li v-for="p in props.inv.pembelajaran" :key="p.id" class="px-5 py-3">
              <p class="text-[12.5px] font-semibold text-cam-ink">{{ p.judul }}</p>
              <p class="text-[11.5px] text-stone-500 mt-1">{{ p.ringkasan }}</p>
              <p v-if="p.pesanKunci" class="text-[11.5px] mt-1.5 rounded-lg px-3 py-2"
                 style="background:#F6EEDF;color:#0F766E">{{ p.pesanKunci }}</p>
            </li>
          </ul>

          <p v-else class="px-5 py-6 text-center text-[12px] text-stone-400">Belum ada pembelajaran terbit.</p>

          <!-- Boleh terbit pada berkas yang SUDAH DITUTUP, dan hanya ini
               yang boleh. Menutup berkasnya lebih dahulu adalah urutan
               yang wajar; memaksa pembelajaran terbit sebelum penutupan
               berarti ia ditulis sebelum kesimpulannya matang. -->
          <form class="px-5 py-4 border-t border-stone-100 grid gap-2.5"
                @submit.prevent="kirim(fBelajar, 'pembelajaran')">
            <input v-model="fBelajar.judul" class="rounded-lg border-stone-200 text-[12px]"
                   placeholder="Judul pembelajaran">
            <textarea v-model="fBelajar.ringkasan" rows="3" class="rounded-lg border-stone-200 text-[12px]"
                      placeholder="Satu paragraf yang dapat dibaca site lain tanpa membuka berkas ini."></textarea>
            <input v-model="fBelajar.pesan_kunci" class="rounded-lg border-stone-200 text-[12px]"
                   placeholder="Pesan kunci — apa yang harus diperiksa pembacanya di tempatnya sendiri">
            <span class="inline-flex">
              <button class="eq-btn-utama" :disabled="fBelajar.processing || !fBelajar.judul || !fBelajar.ringkasan">
                Terbitkan pembelajaran
              </button>
            </span>
          </form>
        </section>

        <!-- ringkasan rantai -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">Ringkasan rantai</h3>
          </header>

          <div class="px-5 py-4">
            <div class="flex flex-wrap items-center gap-1.5 text-[11px]">
              <span v-for="(x, i) in [
                      { n: props.inv?.rantai?.bukti, l: 'bukti' },
                      { n: props.inv?.rantai?.akar, l: 'akar' },
                      { n: props.inv?.rantai?.temuan, l: 'temuan' },
                      { n: props.inv?.rantai?.tindakan, l: 'tindakan' },
                    ]" :key="x.l" class="flex items-center gap-1.5">
                <span class="rounded-md px-2 py-1 font-bold num"
                      style="background:#F6EEDF;color:#0F766E">{{ x.n ?? 0 }} {{ x.l }}</span>
                <span v-if="i < 3" class="text-stone-300">→</span>
              </span>
            </div>

            <p class="text-[11.5px] text-stone-500 mt-3">
              Investigasi hanya boleh ditutup bila setiap temuan sudah punya sedikitnya satu tindakan,
              dan setiap tindakan sudah diverifikasi efektif.
            </p>

            <ul v-if="(props.inv?.kurangTutup ?? []).length && bisaUbah()" class="mt-2.5 grid gap-1">
              <li v-for="k in props.inv.kurangTutup" :key="k" class="text-[11px]" style="color:#92400E">
                · {{ k }}
              </li>
            </ul>

            <div class="mt-3 pt-3 border-t border-stone-100">
              <span v-if="bisaUbah() && !(props.inv?.kurangTutup ?? []).length" class="inline-flex">
                <button type="button" class="eq-btn-utama"
                        @click="konfirmasi('tutup', `Tutup investigasi ${props.inv.nomor}? Sesudah ditutup, berkasnya tidak menerima perubahan.`)">
                  Tutup investigasi
                </button>
              </span>

              <template v-else-if="!bisaUbah()">
                <p class="text-[11.5px] font-semibold mb-2" :style="{ color: KEADAAN.baik }">
                  ✓ Ditutup {{ props.inv?.ditutup }}
                </p>
                <button type="button" class="text-[11.5px] font-semibold text-cam-lime-deep"
                        @click="konfirmasi('buka-lagi', 'Buka kembali investigasi ini?')">
                  Buka kembali
                </button>
              </template>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>

<script setup lang="ts">
/**
 * Wawancara saksi — panduan yang menyesuaikan diri pada perannya.
 *
 * ── MENGAPA PERTANYAANNYA TIDAK SAMA UNTUK SEMUA NARASUMBER ──
 *
 * Satu daftar pertanyaan untuk semua orang terlihat adil dan merusak
 * dua hal sekaligus. Ia membuang waktu — saksi tidak langsung ditanyai
 * rincian yang tidak dilihatnya, dan berita acara yang isinya "kurang
 * tahu" tidak dipakai siapa pun. Dan ia MENGGESER TANGGUNG JAWAB:
 * menanyai korban mengapa perusahaan tidak mengganti alat yang aus
 * adalah pertanyaan yang tidak dapat dijawabnya, dan satu-satunya
 * jawaban yang mungkin diberikannya terdengar seperti pengakuan.
 *
 * Karena itu pertanyaan bertingkat Eliminasi, Substitusi, dan Rekayasa
 * hanya muncul untuk pengawas dan manajemen. Aturannya bukan sopan
 * santun melainkan hierarki pengendalian: yang ditanya adalah yang
 * punya kewenangan mengubah tingkat itu. Layar ini menyebut aturan
 * tersebut di depan mata, bukan menyembunyikannya di server — pewawancara
 * yang tahu alasannya tidak akan mengakalinya.
 *
 * ── PERTANYAAN KONTEKSTUAL ──
 *
 * Sebagian pertanyaan hanya muncul bila kronologi memuat kata kuncinya,
 * dan ditandai supaya bedanya terlihat. Menampilkan semuanya sekaligus
 * berarti daftar empat puluh pertanyaan yang dibaca sekali lalu tidak
 * pernah lagi.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import { propHalaman } from '../../halaman';
import { useDialog } from '../../dialog';
import Dialog from '../../Components/Dialog.vue';

const props = propHalaman();
const { dialog, tanya, batal, lanjut } = useDialog();

const basis = () => `/investigasi/berkas/${props.inv.id}`;
const bisaUbah = () => props.inv?.berjalan === true;

const fBaru = useForm<Record<string, any>>({
  narasumber: '', jabatan: '', peran: props.peran ?? 'korban',
  tanggal: '', tempat: '', catatan: '',
});

/** Berita acara yang sedang diisi jawabannya. */
const bukaBa = ref<number | null>(null);

/**
 * Isian jawaban per berita acara.
 *
 * Terpisah per id, bukan satu bersama: kalimat yang belum tersimpan pada
 * satu berita acara tidak boleh muncul di berita acara berikutnya yang
 * dibuka — pada berkas yang dapat diminta Inspektur Tambang, keterangan
 * yang nyasar ke saksi lain bukan gangguan kecil.
 */
const fJawab = reactive<Record<number, any>>({});

function mulaiJawab(id: number) {
  bukaBa.value = bukaBa.value === id ? null : id;
  fJawab[id] ??= { pertanyaan_id: '', pertanyaan: '', jawaban: '' };
}

/** Menyalin bunyi pertanyaan dari panduan ke kolom isian. */
function ambilPanduan(id: number, p: any) {
  fJawab[id] ??= { pertanyaan_id: '', pertanyaan: '', jawaban: '' };
  fJawab[id].pertanyaan_id = p.id;
  fJawab[id].pertanyaan = p.pertanyaan;
}

function simpanJawab(id: number) {
  router.post(`${basis()}/wawancara/${id}/jawab`, fJawab[id], {
    preserveScroll: true,
    onSuccess: () => { fJawab[id] = { pertanyaan_id: '', pertanyaan: '', jawaban: '' }; },
  });
}

function gantiPeran(p: string) {
  router.get(`${basis()}/wawancara`, { peran: p },
    { preserveScroll: true, preserveState: true });
}

async function hapusBa(id: number, nama: string) {
  if (await tanya(`Hapus berita acara wawancara ${nama}? Jawaban di dalamnya ikut terhapus.`)) {
    router.post(`${basis()}/wawancara/${id}/hapus`, {}, { preserveScroll: true });
  }
}

const NAMA_TINGKAT: Record<number, string> = {
  1: 'Eliminasi', 2: 'Substitusi', 3: 'Rekayasa', 4: 'Administrasi', 5: 'APD',
};
</script>

<template>
  <Head :title="`Wawancara · ${props.inv?.nomor ?? ''}`" />

  <div class="max-w-[1500px] mx-auto space-y-5">

    <!-- ══════════ kepala ══════════ -->
    <section class="flex flex-wrap items-start justify-between gap-4">
      <div class="min-w-0">
        <p class="text-[11.5px] text-stone-400">
          <Link :href="`/investigasi/berkas/${props.inv?.id}`" class="hover:underline">← Ruang kerja</Link>
          · <span class="num">{{ props.inv?.nomor }}</span>
        </p>
        <h2 class="text-xl font-bold text-cam-ink">Wawancara saksi</h2>
        <p class="text-[12.5px] text-stone-500 mt-0.5">{{ props.inv?.judul }}</p>
      </div>

      <Link :href="`/investigasi/berkas/${props.inv?.id}/analisis`"
            class="shrink-0 rounded-xl px-3.5 py-2 text-[12px] font-semibold border border-stone-200 text-cam-ink hover:bg-stone-50">
        Analisis SCAT
      </Link>
    </section>

    <div class="grid gap-4 lg:grid-cols-[.9fr_1.1fr] items-start">

      <!-- ══════════════ panduan per peran ══════════════ -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <header class="px-5 py-3.5 border-b border-stone-100">
          <h3 class="text-[13.5px] font-bold text-cam-ink">
            Panduan pertanyaan
            <span class="font-normal text-stone-400">| {{ (props.panduan ?? []).length }} pertanyaan</span>
          </h3>
        </header>

        <div class="px-5 py-3 border-b border-stone-100 flex flex-wrap gap-1.5">
          <button v-for="(label, kunci) in (props.daftarPeran ?? {})" :key="kunci" type="button"
                  class="rounded-lg px-2.5 py-1.5 text-[11.5px] font-semibold border"
                  :style="kunci === props.peran
                    ? { background: '#0F766E', color: '#fff', borderColor: '#0F766E' }
                    : { background: '#fff', color: '#44403C', borderColor: '#E7E5E4' }"
                  @click="gantiPeran(String(kunci))">{{ label }}</button>
        </div>

        <!-- Aturannya disebut di depan mata, bukan disembunyikan. -->
        <p class="px-5 py-2.5 text-[11.5px] border-b border-stone-100"
           style="background:#F6EEDF;color:#3F3F46">
          <template v-if="(props.tingkatBoleh ?? []).length">
            Tingkat pengendalian yang boleh ditanyakan kepada peran ini:
            <span class="font-semibold">
              {{ (props.tingkatBoleh ?? []).map((t: number) => NAMA_TINGKAT[t]).join(', ') }}.
            </span>
            Tingkat di atasnya adalah kewenangan orang lain — menanyakannya di sini
            memindahkan tanggung jawab organisasi ke narasumber.
          </template>
          <template v-else>
            Peran ini tidak ditanyai soal pengendalian sama sekali: ia tidak melihat
            kejadiannya, dan pendapatnya tentang mengapa pengendaliannya gagal akan
            tercatat sebagai keterangan.
          </template>
        </p>

        <ul class="divide-y divide-stone-100 max-h-[32rem] overflow-y-auto">
          <li v-for="p in (props.panduan ?? [])" :key="p.id" class="px-5 py-2.5 flex items-start gap-3">
            <span class="min-w-0 flex-1">
              <span class="text-[12.5px] text-cam-ink">{{ p.pertanyaan }}</span>
              <span class="block mt-1 flex flex-wrap gap-1.5">
                <span v-if="p.tingkat" class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                      style="background:#E0F2F1;color:#0B5A54">{{ NAMA_TINGKAT[p.tingkat] }}</span>
                <span v-if="p.kontekstual" class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                      style="background:#FEF3C7;color:#92400E">muncul dari kronologi</span>
              </span>
            </span>

            <!-- Tetap terlihat meski belum ada berita acara yang dibuka, hanya
                 dimatikan. Tombol yang HILANG membuat orang mengira panduannya
                 memang tidak dapat dipakai; tombol yang mati mengajari bahwa
                 berita acaranya perlu dibuka lebih dahulu. -->
            <button v-if="bisaUbah()" type="button"
                    class="shrink-0 text-[11px] font-semibold"
                    style="color:#0F766E"
                    :disabled="bukaBa === null"
                    :class="bukaBa === null ? 'opacity-40 cursor-not-allowed' : ''"
                    :title="bukaBa === null ? 'Buka “isi jawaban” pada salah satu berita acara lebih dahulu.' : ''"
                    @click="bukaBa !== null && ambilPanduan(bukaBa, p)">pakai</button>
          </li>
        </ul>

        <p v-if="!(props.panduan ?? []).length" class="px-5 py-4 text-[12px] text-stone-400">
          Belum ada pertanyaan untuk peran ini. Jalankan <span class="num">investigasi:pasang</span>
          bila bank soalnya belum terpasang.
        </p>
      </section>

      <!-- ══════════════ berita acara ══════════════ -->
      <div class="grid gap-4">

        <section v-if="bisaUbah()" class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">Berita acara baru</h3>
          </header>

          <form class="px-5 py-4 grid gap-2.5 sm:grid-cols-2"
                @submit.prevent="fBaru.post(`${basis()}/wawancara`, {
                  preserveScroll: true, onSuccess: () => fBaru.reset() })">
            <input v-model="fBaru.narasumber" placeholder="Nama narasumber" required
                   class="rounded-lg border border-stone-200 px-3 py-1.5 text-[12.5px]" />
            <input v-model="fBaru.jabatan" placeholder="Jabatan"
                   class="rounded-lg border border-stone-200 px-3 py-1.5 text-[12.5px]" />

            <select v-model="fBaru.peran" aria-label="Peran narasumber"
                    class="rounded-lg border border-stone-200 px-3 py-1.5 text-[12.5px]">
              <option v-for="(label, kunci) in (props.daftarPeran ?? {})" :key="kunci" :value="kunci">
                {{ label }}
              </option>
            </select>

            <input v-model="fBaru.tanggal" type="date" aria-label="Tanggal wawancara"
                   class="rounded-lg border border-stone-200 px-3 py-1.5 text-[12.5px]" />
            <input v-model="fBaru.tempat" placeholder="Tempat wawancara"
                   class="rounded-lg border border-stone-200 px-3 py-1.5 text-[12.5px] sm:col-span-2" />
            <textarea v-model="fBaru.catatan" rows="2" placeholder="Catatan pewawancara (opsional)"
                      class="rounded-lg border border-stone-200 px-3 py-1.5 text-[12.5px] sm:col-span-2"></textarea>

            <div class="sm:col-span-2">
              <button type="submit" :disabled="fBaru.processing"
                      class="rounded-xl px-3.5 py-2 text-[12px] font-semibold text-white"
                      style="background:#0F766E">Buat berita acara</button>
            </div>
          </form>
        </section>

        <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">
              Berita acara
              <span class="font-normal text-stone-400">| {{ (props.wawancara ?? []).length }} narasumber</span>
            </h3>
          </header>

          <ul v-if="(props.wawancara ?? []).length" class="divide-y divide-stone-100">
            <li v-for="w in props.wawancara" :key="w.id" class="px-5 py-3.5">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="text-[12.5px] font-bold text-cam-ink">
                    {{ w.narasumber }}
                    <span class="font-normal text-stone-400">{{ w.jabatan ? '· ' + w.jabatan : '' }}</span>
                  </p>
                  <p class="text-[11px] text-stone-500 mt-0.5">
                    <span class="rounded px-1.5 py-0.5 font-semibold"
                          style="background:#E0F2F1;color:#0B5A54">{{ w.peranLabel }}</span>
                    <span v-if="w.tanggal" class="num ml-1.5">{{ w.tanggal }}</span>
                    <span v-if="w.tempat"> · {{ w.tempat }}</span>
                    <span v-if="w.pewawancara"> · oleh {{ w.pewawancara }}</span>
                  </p>
                </div>

                <div class="shrink-0 flex items-center gap-2.5">
                  <button v-if="bisaUbah()" type="button" class="text-[11px] font-semibold"
                          style="color:#0F766E" @click="mulaiJawab(w.id)">
                    {{ bukaBa === w.id ? 'tutup' : 'isi jawaban' }}
                  </button>
                  <button v-if="bisaUbah()" type="button" class="text-red-600 text-[11px]"
                          @click="hapusBa(w.id, w.narasumber)">hapus</button>
                </div>
              </div>

              <p v-if="w.catatan" class="text-[11.5px] text-stone-500 mt-1.5 whitespace-pre-line">{{ w.catatan }}</p>

              <ol v-if="w.jawaban.length" class="mt-2.5 space-y-2">
                <li v-for="j in w.jawaban" :key="j.id" class="rounded-lg px-3 py-2" style="background:#FBF5EA">
                  <p class="text-[11.5px] font-semibold text-cam-ink">{{ j.pertanyaan }}</p>
                  <p class="text-[12px] text-stone-600 mt-0.5 whitespace-pre-line">{{ j.jawaban }}</p>
                </li>
              </ol>
              <p v-else class="text-[11.5px] text-stone-400 mt-1.5">Belum ada jawaban tercatat.</p>

              <!-- isian jawaban -->
              <form v-if="bisaUbah() && bukaBa === w.id" class="mt-3 grid gap-2"
                    @submit.prevent="simpanJawab(w.id)">
                <p class="text-[11px] text-stone-400">
                  Tekan “pakai” pada panduan di sebelah kiri untuk menyalin bunyi pertanyaannya,
                  atau tulis sendiri.
                </p>
                <textarea v-model="fJawab[w.id].pertanyaan" rows="2" required
                          placeholder="Pertanyaan"
                          class="rounded-lg border border-stone-200 px-3 py-1.5 text-[12.5px]"></textarea>
                <textarea v-model="fJawab[w.id].jawaban" rows="3" required
                          placeholder="Jawaban narasumber, ditulis sedekat mungkin dengan kalimatnya sendiri"
                          class="rounded-lg border border-stone-200 px-3 py-1.5 text-[12.5px]"></textarea>
                <div>
                  <button type="submit" class="rounded-xl px-3.5 py-2 text-[12px] font-semibold text-white"
                          style="background:#0F766E">Catat jawaban</button>
                </div>
              </form>
            </li>
          </ul>

          <p v-else class="px-5 py-4 text-[12px] text-stone-400">
            Belum ada berita acara wawancara pada berkas ini.
          </p>
        </section>
      </div>
    </div>
  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>

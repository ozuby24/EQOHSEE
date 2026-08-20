<script setup lang="ts">
/**
 * Kuesioner PTPKKP — sisi admin.
 *
 * Tautan publiknya bertoken dan bebas akses, jadi mengganti token
 * langsung memutus tautan yang sudah tersebar. Menariknya ke skor KS juga
 * menimpa nilai yang sudah ada. Keduanya dijaga konfirmasi, dan yang
 * mengganti token menyebut akibatnya, bukan sekadar bertanya "yakin?".
 *
 * Pemilih perusahaan sudah lama didukung controller lewat ?company=,
 * tapi tidak pernah punya tempat di layar — daftarnya dihitung lalu
 * dibuang. Sekarang dipakai.
 */
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PickerTpkkp from '../../Components/PickerTpkkp.vue';
import type { HalamanKuesioner } from '../../types';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
const { dialog, tanya, minta, batal, lanjut } = useDialog();


const props = defineProps<HalamanKuesioner>();

const tersalin = ref(false);

async function salin() {
  try {
    await navigator.clipboard.writeText(props.urlPublik ?? "");
    tersalin.value = true;
    setTimeout(() => { tersalin.value = false; }, 2000);
  } catch {
    /* Clipboard ditolak peramban (bukan konteks aman, atau izin dicabut).
       Cadangannya menampilkan tautannya di kolom yang sudah tersorot,
       supaya masih dapat disalin sendiri. Dulu ini memakai `prompt()` —
       yang pada webview ponsel tidak muncul sama sekali, sehingga
       cadangannya sendiri ikut lenyap justru di perangkat yang paling
       sering menolak clipboard. */
    await minta({ judul: 'Salin tautan kuesioner', label: 'Tautan publik',
      nilai: props.urlPublik ?? '', baca: true, wajib: false, labelAksi: 'Selesai' });
  }
}

const banyakPerusahaan = computed(() => props.daftarPerusahaan.length > 1);

function gantiPerusahaan(id: number | string) {
  router.get('/tpkkp/kuesioner', { company: id }, { preserveScroll: true });
}

async function gantiTautan() {
  if (!await tanya('Buat tautan baru? Tautan lama langsung tidak berlaku, termasuk yang sudah tersebar.')) return;

  router.post("/kuesioner/token", { company_id: props.perusahaan?.id }, { preserveScroll: true });
}

async function tarik() {
  if (!await tanya('Tarik seluruh jawaban kuesioner menjadi skor metode KS? Nilai KS yang ada akan ditimpa.')) return;

  router.post('/kuesioner/tarik', {}, { preserveScroll: true });
}

async function hapus(id: number) {
  if (!await tanya('Hapus responden?')) return;

  router.delete(`/kuesioner/${id}`, { preserveScroll: true });
}
</script>

<template>
  <Head title="Kuesioner PTPKKP" />

  <div class="max-w-5xl mx-auto space-y-5">

    <PickerTpkkp v-bind="picker" />

    <div v-if="!perusahaan || !urlPublik"
         class="bg-white rounded-2xl border border-stone-200 px-6 py-12 text-center">
      <h3 class="text-[14px] font-bold text-cam-ink">Belum ada perusahaan terdaftar</h3>
      <p class="text-[12.5px] text-stone-500 mt-1.5 max-w-md mx-auto">
        Tautan kuesioner terbit per perusahaan, jadi setidaknya satu perusahaan harus ada
        lebih dulu. Tambahkan lewat Pengaturan → Perusahaan, lalu kembali ke halaman ini.
      </p>
    </div>

    <template v-else>
    <div class="brand-gradient rounded-2xl p-6 text-white shadow-card relative overflow-hidden">
      <div class="absolute -right-20 -top-20 w-56 h-56 rounded-full bg-cam-lime/20 blur-3xl"></div>

      <div class="relative">
        <span class="text-[10px] font-bold uppercase tracking-[0.22em] text-cam-lime-light">
          Tautan Publik — Bebas Akses
        </span>
        <h2 class="stat stat-sm mt-1.5">Sebarkan ke pekerja &amp; pimpinan</h2>
        <p class="text-[12px] text-white/50 mt-1.5">
          Penerima tidak perlu akun. Cukup buka tautan, pilih kategori, lalu isi.
        </p>

        <div v-if="banyakPerusahaan" class="mt-4">
          <label class="block text-[10px] font-bold uppercase tracking-wider text-white/50 mb-1.5">
            Perusahaan
          </label>
          <select :value="perusahaan.id"
                  @change="gantiPerusahaan(($event.target as HTMLSelectElement).value)"
                  class="ring-focus rounded-lg bg-white/15 border border-white/20 pl-3 pr-8 py-1.5
                         text-[12px] font-semibold text-white" aria-label="Perusahaan">
            <option v-for="c in daftarPerusahaan" :key="c.id" :value="c.id" class="text-cam-ink">
              {{ c.nama }}
            </option>
          </select>
        </div>

        <div class="glass rounded-xl px-4 py-3 mt-4 flex flex-wrap items-center gap-2">
          <code class="text-[12px] font-mono text-cam-lime-light break-all flex-1 min-w-0 basis-[200px]">
            {{ urlPublik }}
          </code>
          <button type="button" @click="salin"
                  class="rounded-lg bg-white/15 hover:bg-white/25 px-3 py-1.5 text-[11.5px] font-bold transition">
            {{ tersalin ? 'Tersalin ✓' : 'Salin' }}
          </button>
          <a :href="urlPublik" target="_blank" rel="noopener"
             class="rounded-lg bg-white/15 hover:bg-white/25 px-3 py-1.5 text-[11.5px] font-bold transition">
            Buka
          </a>
        </div>

        <div class="flex flex-wrap gap-2 mt-3">
          <a v-for="k in ringkas" :key="k.kunci" :href="k.url" target="_blank" rel="noopener"
             class="glass rounded-lg px-3 py-1.5 text-[11.5px] font-bold hover:bg-white/15 transition">
            Tautan langsung: {{ k.label }}
          </a>

          <button v-if="bisaTarik" type="button" @click="tarik"
                  class="rounded-lg bg-white/15 hover:bg-white/25 px-3 py-1.5 text-[11.5px] font-bold transition">
            ↧ Tarik ke skor KS
          </button>
          <button type="button" @click="gantiTautan"
                  class="rounded-lg bg-white/10 hover:bg-white/20 px-3 py-1.5 text-[11.5px] font-bold transition">
            Ganti tautan
          </button>
        </div>
      </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
      <div v-for="r in ringkas" :key="r.kunci"
           class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
        <div class="flex items-center justify-between mb-3">
          <div>
            <div class="text-[13.5px] font-bold text-cam-ink">{{ r.label }}</div>
            <div class="text-[11px] text-stone-400 mt-0.5">{{ r.jumlah }} responden</div>
          </div>
          <div class="text-right">
            <div class="stat text-cam-lime-deep leading-none">{{ r.rerata ?? '—' }}</div>
            <div class="text-[9.5px] uppercase tracking-wide text-stone-400 mt-1">rerata 1–5</div>
          </div>
        </div>

        <div class="space-y-2 pt-3 border-t border-stone-100">
          <div v-for="p in r.params" :key="p.kode">
            <div class="flex items-center justify-between text-[11.5px] mb-1">
              <span class="text-stone-600 truncate pr-2">{{ p.kode }} {{ p.nama }}</span>
              <span class="shrink-0 font-bold text-stone-500 num">{{ p.rerata ?? '—' }}</span>
            </div>
            <div class="h-1.5 rounded-full bg-stone-100 overflow-hidden">
              <div class="h-full rounded-full lime-gradient" :style="{ width: `${p.pct}%` }"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <p class="text-[11.5px] text-stone-400 leading-relaxed">
      Hasil kuesioner adalah <b>data pendukung</b> (Metode B) untuk mengisi Formulir Nilai —
      bukan nilai otomatis, sesuai prosedur penilaian PTPKKP.
    </p>

    <!--
      RESPONS MITRA KERJA — analisa, bukan nilai.

      Tidak masuk skor KS: kematangan yang dinilai adalah milik pemegang
      IUP, dan persepsi orang yang bekerja di perusahaan lain, dengan
      pengawas lain dan aturan internal lain, akan menaikkan atau
      menurunkan nilai itu oleh keadaan yang bukan miliknya.

      Tetapi TIDAK dibuang. Mitra yang persepsi keselamatannya rendah
      adalah mitra yang perlu dibina — temuan tersendiri yang tidak
      muncul di mana pun bila datanya hanya disingkirkan diam-diam.
    -->
    <section v-if="(mitra ?? []).length"
             class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      <div class="px-5 py-4 border-b border-stone-100">
        <h3 class="text-[14px] font-bold text-cam-ink">
          Persepsi mitra kerja
          <span class="font-normal text-[11.5px] text-stone-400">
            | analisa saja — tidak masuk Summary maupun nilai total
          </span>
        </h3>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[12px]">
          <thead class="text-[10px] uppercase tracking-wider text-stone-400 bg-stone-50">
            <tr>
              <th class="px-5 py-2.5 font-bold">Perusahaan</th>
              <th class="px-3 py-2.5 font-bold text-right">Responden</th>
              <th class="px-5 py-2.5 font-bold text-right">Rerata persepsi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in mitra" :key="m.perusahaan" class="border-t border-stone-50">
              <td class="px-5 py-2.5 font-semibold text-cam-ink">{{ m.perusahaan }}</td>
              <td class="px-3 py-2.5 text-right num">{{ m.jumlah }}</td>
              <td class="px-5 py-2.5 text-right num">
                {{ m.rerata === null ? '—' : Number(m.rerata).toFixed(2) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      <div class="px-5 py-4 border-b border-stone-100">
        <h3 class="text-[14px] font-bold text-cam-ink">Responden ({{ responden.length }})</h3>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-[12.5px]">
          <thead>
            <tr class="bg-stone-50 border-b border-stone-100">
              <th v-for="h in ['Kategori','NRP','Jabatan','Departemen','Jawaban','Waktu','']" :key="h"
                  class="text-left font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-2.5">
                {{ h }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in responden" :key="r.id"
                class="border-b border-stone-50 last:border-0 hover:bg-stone-50/60">
              <td class="px-4 py-2.5">
                <span class="text-[10px] font-bold bg-cam-lime-soft text-cam-lime-deep px-2 py-0.5 rounded-full">
                  {{ r.kategoriLabel }}
                </span>
              </td>
              <td class="px-4 py-2.5 font-semibold text-cam-ink">{{ r.nrp ?? '—' }}</td>
              <td class="px-4 py-2.5 text-stone-500">{{ r.jabatan ?? '—' }}</td>
              <td class="px-4 py-2.5 text-stone-500">{{ r.dept ?? '—' }}</td>
              <td class="px-4 py-2.5 text-stone-500 num">{{ r.jumlahJawaban }}</td>
              <td class="px-4 py-2.5 text-[11px] text-stone-400">{{ r.waktu ?? '—' }}</td>
              <td class="px-4 py-2.5 text-right">
                <button type="button" @click="hapus(r.id)"
                        class="text-[11.5px] font-semibold text-red-500 hover:bg-red-50 px-2.5 py-1 rounded-lg">
                  Hapus
                </button>
              </td>
            </tr>
            <tr v-if="!responden.length">
              <td colspan="7" class="px-4 py-12 text-center text-stone-400">
                Belum ada yang mengisi kuesioner.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    </template>
  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>

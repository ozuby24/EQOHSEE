<script setup lang="ts">
/**
 * Peluang perbaikan — Opportunity For Improvement.
 *
 * BUKAN TEMUAN RINGAN, dan halaman ini dibuat justru supaya keduanya
 * tidak pernah tertukar. Temuan mencatat ketidaksesuaian: ia menurunkan
 * nilai, menuntut akar masalah, penanggung jawab, dan tenggat. OFI
 * kebalikannya — hanya lahir dari butir yang capaiannya PENUH, tidak
 * menurunkan apa pun, dan tidak wajib dikerjakan.
 *
 * Karena itu daftar kiri berisi butir yang BERHAK, bukan seluruh butir.
 * Formulir yang menawarkan seluruh butir menyerahkan syaratnya kepada
 * ingatan auditor, dan lembar OFI yang memuat butir bernilai 40%
 * menyatakan kebalikan dari keadaan sebenarnya — ditandatangani ketua
 * tim, lalu diserahkan kepada Inspektur Tambang. Syarat yang sama juga
 * ditegakkan server; yang di sini hanya menghemat satu penolakan.
 *
 * Baris yang butirnya BERHENTI sempurna tidak dihapus diam-diam,
 * melainkan ditandai. Lembar yang menyusut sendiri antar cetakan
 * membuat penyusunnya mengira ada yang hilang.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
import { propHalaman } from '../../halaman';
import { KEADAAN } from '../../Grafik/warna';

const props = propHalaman();
const { dialog, tanya, batal, lanjut } = useDialog();

const baris   = computed<any[]>(() => (props.baris ?? []) as any[]);
const berhak  = computed<any[]>(() => (props.berhak ?? []) as any[]);
const sudah   = computed(() => new Set(baris.value.map((b: any) => b.kode)));

/** Butir sempurna yang belum punya catatan peluang. */
const tersisa = computed(() => berhak.value.filter((b: any) => !sudah.value.has(b.kode)));

const cari = ref('');

const tersaring = computed(() => {
  const q = cari.value.trim().toLowerCase();
  if (!q) return tersisa.value;

  return tersisa.value.filter((b: any) => `${b.kode} ${b.nama}`.toLowerCase().includes(q));
});

/* ---------- formulir ---------- */

const f = useForm({
  kode: '', uraian: '', saran: '',
  penanggung_jawab: '', target: '', status: 'terbuka',
});

const dipilih = computed(() => berhak.value.find((b: any) => b.kode === f.kode) ?? null);

function pilih(kode: string) {
  const ada = baris.value.find((b: any) => b.kode === kode);

  f.kode             = kode;
  f.uraian           = ada?.uraian ?? '';
  f.saran            = ada?.saran ?? '';
  f.penanggung_jawab = ada?.penanggung_jawab ?? '';
  f.target           = ada?.target ?? '';
  f.status           = ada?.status ?? 'terbuka';
}

function simpan() {
  f.post(`/smkp/${props.audit?.id}/ofi`, {
    preserveScroll: true,
    onSuccess: () => f.reset(),
  });
}

async function hapus(b: any) {
  if (!await tanya({
    judul: 'Hapus peluang perbaikan ini?',
    pesan: `Catatan pada butir ${b.kode} akan dibuang dari lembar OFI.`,
    labelAksi: 'Hapus', nada: 'bahaya',
  })) return;

  router.delete(`/smkp/${props.audit?.id}/ofi/${b.id}`, { preserveScroll: true });
}

const WARNA_STATUS: Record<string, string> = {
  terbuka:         KEADAAN.ingat,
  ditindaklanjuti: '#0F766E',
  ditutup:         KEADAAN.baik,
};

const warnaStatus = (s: string) => WARNA_STATUS[s] ?? KEADAAN.netral;
</script>

<template>
  <Head title="Peluang Perbaikan (OFI) — Audit SMKP" />

  <div class="max-w-[1400px] mx-auto space-y-5">

    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-cam-ink">Peluang Perbaikan (OFI)</h2>
        <p class="text-[12.5px] text-stone-500 mt-1">
          {{ props.audit?.judul || `Audit ${props.audit?.tahun}` }}
          <span v-if="props.audit?.company?.name"> · {{ props.audit.company.name }}</span>
          · hanya untuk butir yang capaiannya sudah 100%.
        </p>
      </div>

      <div class="flex flex-wrap gap-2">
        <Link :href="props.tautan?.penilaian" class="eq-btn-lain">Form penilaian</Link>
        <Link :href="props.tautan?.cetak" class="eq-btn-lain">Cetak lembar OFI</Link>
        <a :href="props.tautan?.ekspor" class="eq-btn-lain">Unduh CSV</a>
      </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <article v-for="k in [
                 { label: 'Butir sempurna', nilai: berhak.length, ket: 'berhak memperoleh peluang perbaikan' },
                 { label: 'Sudah dicatat',  nilai: baris.length,  ket: 'masuk lembar OFI' },
                 { label: 'Belum dicatat',  nilai: tersisa.length, ket: 'sempurna tetapi belum ditulis' },
                 { label: 'Nilai akhir audit', nilai: `${props.rekap?.skor ?? 0}%`, ket: props.rekap?.tingkat?.label ?? 'belum dinilai' },
               ]" :key="k.label"
               class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
        <p class="text-[10px] uppercase tracking-wider font-bold text-stone-400">{{ k.label }}</p>
        <strong class="block text-xl mt-1">{{ k.nilai }}</strong>
        <p class="text-[11px] text-stone-500 mt-1 leading-snug">{{ k.ket }}</p>
      </article>
    </section>

    <div class="grid gap-4 lg:grid-cols-[380px_1fr] items-start">

      <!-- ══════════ butir yang berhak ══════════ -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <header class="px-5 py-3.5 border-b border-stone-100">
          <h3 class="text-[13.5px] font-bold text-cam-ink">
            Butir sempurna <span class="font-normal text-stone-400">| {{ tersaring.length }} tersisa</span>
          </h3>
          <p class="text-[11px] text-stone-500 mt-0.5">
            Sub-elemen dan rinciannya yang seluruh butirnya bernilai maksimum.
          </p>

          <input v-model="cari" type="search" placeholder="Cari kode atau uraian…"
                 class="mt-2 w-full rounded-lg border-stone-200 text-[12px]">
        </header>

        <ul v-if="tersaring.length" class="divide-y divide-stone-100 max-h-[520px] overflow-y-auto">
          <li v-for="b in tersaring" :key="b.kode">
            <button type="button" class="w-full text-left px-5 py-3 hover:bg-stone-50/70 transition"
                    :class="f.kode === b.kode ? 'bg-stone-50' : ''"
                    @click="pilih(b.kode)">
              <p class="text-[12px] font-bold">
                {{ b.kode }}
                <span class="ml-1 rounded px-1 py-0.5 text-[9.5px] font-bold uppercase tracking-wide"
                      :style="{ background: KEADAAN.baik + '1F', color: KEADAAN.baik }">
                  {{ b.lingkup === 'sub' ? 'sub-elemen' : 'rincian' }}
                </span>
              </p>
              <p class="text-[11.5px] text-stone-600 mt-0.5 leading-snug">{{ b.nama }}</p>
              <p class="text-[10.5px] text-stone-400 mt-0.5">{{ b.elemen }} · {{ b.elemen_nama }}</p>
            </button>
          </li>
        </ul>

        <p v-else class="px-5 py-10 text-center text-[12px] text-stone-400">
          {{ berhak.length
             ? 'Seluruh butir sempurna sudah punya catatan peluang.'
             : 'Belum ada butir yang capaiannya 100%.' }}
        </p>
      </section>

      <!-- min-w-0 pada kolom kanan, dan itu bukan hiasan: jalur grid
           `1fr` tidak menyusut di bawah lebar isinya, sebab butir grid
           berbawaan `min-width: auto`. Tabel lembar OFI karena itu
           mendorong jalurnya melebihi layar — formulir dan tombolnya
           terpotong di sebelah kanan, dan pembungkus `overflow-x-auto`
           di dalamnya tidak pernah terpakai karena yang meluap justru
           jalurnya, bukan tabelnya. -->
      <div class="space-y-4 min-w-0">

        <!-- ══════════ formulir ══════════ -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">
              {{ f.kode ? `Peluang untuk ${f.kode}` : 'Catat peluang perbaikan' }}
            </h3>
            <p v-if="dipilih" class="text-[11px] text-stone-500 mt-0.5">
              {{ dipilih.nama }} · {{ dipilih.elemen }}. {{ dipilih.elemen_nama }}
              <span v-if="dipilih.sub"> · {{ dipilih.sub }}</span>
            </p>
            <p v-else class="text-[11px] text-stone-500 mt-0.5">
              Pilih satu butir di sebelah kiri lebih dahulu.
            </p>
          </header>

          <form class="px-5 py-4 grid gap-3" @submit.prevent="simpan">
            <label class="grid gap-1">
              <span class="text-[11.5px] font-semibold text-stone-600">Peluang perbaikan yang diamati</span>
              <textarea v-model="f.uraian" rows="3" :disabled="!f.kode" required
                        placeholder="Apa yang sudah baik, dan di mana ia masih dapat ditingkatkan."
                        class="rounded-lg border-stone-200 text-[12.5px]" />
              <small v-if="f.errors.uraian" class="text-[11px] text-red-600">{{ f.errors.uraian }}</small>
            </label>

            <label class="grid gap-1">
              <span class="text-[11.5px] font-semibold text-stone-600">Saran auditor</span>
              <textarea v-model="f.saran" rows="2" :disabled="!f.kode"
                        placeholder="Langkah yang disarankan — tidak wajib dikerjakan auditi."
                        class="rounded-lg border-stone-200 text-[12.5px]" />
            </label>

            <div class="grid gap-3 sm:grid-cols-3">
              <label class="grid gap-1">
                <span class="text-[11.5px] font-semibold text-stone-600">Penanggung jawab</span>
                <input v-model="f.penanggung_jawab" :disabled="!f.kode" maxlength="150"
                       class="rounded-lg border-stone-200 text-[12.5px]">
              </label>

              <label class="grid gap-1">
                <span class="text-[11.5px] font-semibold text-stone-600">Target</span>
                <input v-model="f.target" type="date" :disabled="!f.kode"
                       class="rounded-lg border-stone-200 text-[12.5px]">
              </label>

              <label class="grid gap-1">
                <span class="text-[11.5px] font-semibold text-stone-600">Status</span>
                <select v-model="f.status" :disabled="!f.kode" class="rounded-lg border-stone-200 text-[12.5px]">
                  <option v-for="(label, kode) in (props.STATUS ?? {})" :key="kode" :value="kode">{{ label }}</option>
                </select>
              </label>
            </div>

            <p v-if="f.errors.kode" class="text-[11.5px] text-red-600">{{ f.errors.kode }}</p>

            <span>
              <button type="submit" class="eq-btn-utama" :disabled="!f.kode || f.processing">
                Simpan peluang perbaikan
              </button>
            </span>
          </form>
        </section>

        <!-- ══════════ lembar OFI ══════════ -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">
              Lembar OFI <span class="font-normal text-stone-400">| {{ baris.length }} baris</span>
            </h3>
            <p class="text-[11px] text-stone-500 mt-0.5">
              Urut mengikuti berkas kriteria, bukan waktu pencatatan — lembar ini dibaca
              berdampingan dengan Formulir Kriteria.
            </p>
          </header>

          <div v-if="baris.length" class="overflow-x-auto">
            <table class="min-w-full text-left text-[11.5px]">
              <thead>
                <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
                  <th class="px-4 py-2 font-semibold w-10">No</th>
                  <th class="px-4 py-2 font-semibold whitespace-nowrap">Kode</th>
                  <th class="px-4 py-2 font-semibold">Peluang perbaikan</th>
                  <th class="px-4 py-2 font-semibold w-28">PIC</th>
                  <th class="px-4 py-2 font-semibold whitespace-nowrap">Target</th>
                  <th class="px-4 py-2 font-semibold whitespace-nowrap">Status</th>
                  <th class="px-4 py-2 font-semibold w-20"></th>
                </tr>
              </thead>

              <tbody>
                <tr v-for="b in baris" :key="b.id" class="border-b border-stone-100 align-top">
                  <td class="px-4 py-2.5 num text-stone-400">{{ b.no }}</td>

                  <td class="px-4 py-2.5 whitespace-nowrap">
                    <b>{{ b.kode }}</b>
                    <span class="block text-[10px] text-stone-400">{{ b.lingkup_label }}</span>

                    <!-- Butir yang berhenti sempurna ditandai, tidak
                         dihapus. Lembar yang menyusut sendiri antar
                         cetakan membuat penyusunnya mengira ada yang
                         hilang. -->
                    <span v-if="b.gugur"
                          class="block mt-1 rounded px-1 py-0.5 text-[9.5px] font-bold"
                          :style="{ background: KEADAAN.ingat + '1F', color: KEADAAN.ingat }"
                          title="Capaian butir ini sudah tidak 100% lagi">
                      tak lagi sempurna
                    </span>
                  </td>

                  <td class="px-4 py-2.5">
                    <p class="text-cam-ink">{{ b.uraian }}</p>
                    <p v-if="b.saran" class="text-stone-500 mt-0.5">Saran: {{ b.saran }}</p>
                    <p class="text-[10.5px] text-stone-400 mt-0.5">{{ b.sub }}</p>
                  </td>

                  <td class="px-4 py-2.5 text-stone-500">{{ b.penanggung_jawab || '—' }}</td>
                  <td class="px-4 py-2.5 num text-stone-500 whitespace-nowrap">{{ b.target || '—' }}</td>

                  <td class="px-4 py-2.5 whitespace-nowrap">
                    <span class="rounded px-1.5 py-0.5 text-[10px] font-bold"
                          :style="{ background: warnaStatus(b.status) + '1F', color: warnaStatus(b.status) }">
                      {{ b.status_label }}
                    </span>
                  </td>

                  <td class="px-4 py-2.5 whitespace-nowrap">
                    <button type="button" class="eq-btn-mini" @click="pilih(b.kode)">Ubah</button>
                    <button type="button" class="eq-btn-mini mt-1" @click="hapus(b)">Hapus</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <p v-else class="px-5 py-10 text-center text-[12px] text-stone-400">
            Belum ada peluang perbaikan yang dicatat.
          </p>
        </section>
      </div>
    </div>
  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>

<script setup lang="ts">
/**
 * Pengujian PTPKKP — sisi admin.
 *
 * Metode PJ menilai satu item: 1.1.1, kesadaran pekerja terhadap risiko
 * keselamatan pertambangan. Yang diukur adalah kesadaran ORANG BANYAK,
 * jadi halaman ini menyusun tiga hal sekaligus: apa yang akan masuk ke
 * nilai (tingkat gabungan), dari mana angka itu datang (sebaran per
 * tingkat), dan apa yang perlu diragukan tentangnya (berapa peserta
 * yang mengerjakannya sambil berpindah layar).
 *
 * Nilai perorangan ditampilkan DI SINI, dan hanya di sini. Peserta
 * tidak pernah melihat nilainya sendiri — nilai yang terlihat mengubah
 * kuis menjadi ujian perorangan, dan yang diukur bukan itu — tetapi
 * yang menyimpulkan sebaran mustahil bekerja tanpa angkanya.
 */
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PickerTpkkp from '../../Components/PickerTpkkp.vue';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
import type { HalamanPengujian } from '../../types';

const { dialog, tanya, minta, batal, lanjut } = useDialog();

const props = defineProps<HalamanPengujian>();

const tersalin = ref(false);

async function salin() {
  try {
    await navigator.clipboard.writeText(props.urlPublik ?? '');
    tersalin.value = true;
    setTimeout(() => { tersalin.value = false; }, 2000);
  } catch {
    await minta({ judul: 'Salin tautan pengujian', label: 'Tautan publik',
      nilai: props.urlPublik ?? '', baca: true, wajib: false, labelAksi: 'Selesai' });
  }
}

const banyakPerusahaan = computed(() => props.daftarPerusahaan.length > 1);

function gantiPerusahaan(id: number | string) {
  router.get('/tpkkp/pengujian', { company: id }, { preserveScroll: true });
}

/** Sebaran siap gambar: selalu lima batang, termasuk yang nol. */
const sebaran = computed(() => {
  const n = Math.max(1, props.ringkas.peserta);

  return [1, 2, 3, 4, 5].map((t) => {
    const jumlah = Number(props.ringkas.sebaran?.[String(t)] ?? 0);

    return {
      tingkat: t,
      nama: props.namaTingkat[t - 1] ?? `Tingkat ${t}`,
      warna: props.warnaTingkat[t - 1] ?? '#C4C7CD',
      jumlah,
      /* Persentasenya dari jumlah peserta, bukan dari batang terpanjang.
         Menskalakan ke batang terpanjang membuat sebaran 1-0-0-0-0 dan
         50-0-0-0-0 tampak persis sama. */
      pct: (jumlah / n) * 100,
    };
  });
});

const adaPeserta = computed(() => props.ringkas.peserta > 0);

/** Peserta yang berpindah layar — indikasi, bukan tuduhan. */
const berpindah = computed(() => props.peserta.filter((p) => p.pindahLayar > 0).length);

/* "d" untuk detik terbaca sebagai "hari" oleh siapa pun yang pernah
   melihat notasi durasi bahasa Inggris — dan durasi 5 hari untuk
   mengerjakan 15 soal terbaca sebagai kesalahan sistem. Ditulis
   panjang. */
function durasi(detik: number): string {
  const m = Math.floor(detik / 60);
  const s = detik % 60;
  return m ? `${m} mnt ${s} dtk` : `${s} dtk`;
}

async function terapkan() {
  if (!adaPeserta.value) return;

  const kini = props.skorKini?.nilai;
  const catatan = kini === null || kini === undefined
    ? ''
    : ` Nilai PJ yang sekarang (tingkat ${kini}) akan ditimpa.`;

  if (!await tanya(
    `Terapkan hasil ${props.ringkas.peserta} peserta sebagai skor PJ — tingkat `
    + `${props.ringkas.tingkat}?${catatan}`
  )) return;

  router.post('/pengujian/terapkan',
    { company_id: props.perusahaan?.id }, { preserveScroll: true });
}

async function hapus(p: { id: number; nama: string }) {
  if (!await tanya(`Hapus hasil pengujian ${p.nama}? Skor PJ tidak ikut berubah sampai diterapkan ulang.`)) return;

  router.delete(`/pengujian/${p.id}`, { preserveScroll: true });
}
</script>

<template>
  <Head title="Pengujian PTPKKP" />

  <div class="max-w-5xl mx-auto space-y-5">
    <PickerTpkkp v-bind="picker" />

    <div v-if="!perusahaan || !urlPublik"
         class="bg-white rounded-2xl border border-stone-200 px-6 py-12 text-center">
      <h3 class="text-[14px] font-bold text-cam-ink">Belum ada perusahaan terdaftar</h3>
      <p class="text-[12.5px] text-stone-500 mt-1.5 max-w-md mx-auto">
        Tautan pengujian terbit per perusahaan, jadi setidaknya satu perusahaan harus ada
        lebih dulu. Tambahkan lewat Pengaturan → Perusahaan, lalu kembali ke halaman ini.
      </p>
    </div>

    <template v-else>
      <!-- ══════ TAUTAN PUBLIK ══════ -->
      <div class="brand-gradient rounded-2xl p-6 text-white shadow-card relative overflow-hidden">
        <div class="absolute -right-20 -top-20 w-56 h-56 rounded-full bg-cam-lime/20 blur-3xl"></div>

        <div class="relative">
          <span class="text-[10px] font-bold uppercase tracking-[0.22em] text-cam-lime-light">
            Metode PJ — Pengujian · tautan publik
          </span>
          <h2 class="stat stat-sm mt-1.5">Kuis kesadaran risiko untuk pekerja</h2>
          <p class="text-[12px] text-white/50 mt-1.5">
            {{ jumlahSoal }} soal acak dari bank {{ bank }} soal · {{ detikPerSoal }} detik per soal ·
            satu soal per layar, tidak dapat kembali. Peserta tidak melihat nilainya.
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
            <button v-if="bisaTerapkan" type="button" @click="terapkan" :disabled="!adaPeserta"
                    class="rounded-lg bg-white/15 hover:bg-white/25 disabled:opacity-40
                           disabled:hover:bg-white/15 px-3 py-1.5 text-[11.5px] font-bold transition">
              ↧ Terapkan ke skor PJ
            </button>
          </div>
        </div>
      </div>

      <!-- ══════ RINGKAS ══════ -->
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
          <div class="text-[10px] uppercase tracking-wide text-stone-400">Peserta</div>
          <div class="stat text-cam-ink leading-none mt-1.5 num">{{ ringkas.peserta }}</div>
        </div>

        <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
          <div class="text-[10px] uppercase tracking-wide text-stone-400">Tingkat gabungan</div>
          <div class="stat leading-none mt-1.5 num"
               :style="{ color: ringkas.tingkat ? warnaTingkat[ringkas.tingkat - 1] : '#C4C7CD' }">
            {{ ringkas.tingkat ?? '—' }}
          </div>
          <div class="text-[11px] text-stone-400 mt-1">
            {{ ringkas.tingkat ? namaTingkat[ringkas.tingkat - 1] : 'belum ada peserta' }}
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
          <div class="text-[10px] uppercase tracking-wide text-stone-400">Rerata tingkat</div>
          <div class="stat text-cam-ink leading-none mt-1.5 num">
            {{ ringkas.rerataTingkat === null ? '—' : Number(ringkas.rerataTingkat).toFixed(2) }}
          </div>
          <!--
            Rerata mentahnya ditampilkan di sebelah tingkat bulatnya
            supaya pembulatan setengah-ke-bawah terlihat: 2,50 yang
            menjadi 2 tampak seperti kekeliruan sampai angka mentahnya
            ikut kelihatan.
          -->
          <div class="text-[11px] text-stone-400 mt-1">dibulatkan setengah ke bawah</div>
        </div>

        <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
          <div class="text-[10px] uppercase tracking-wide text-stone-400">Rerata jawaban benar</div>
          <div class="stat text-cam-ink leading-none mt-1.5 num">
            {{ ringkas.rerataPct === null ? '—' : `${Number(ringkas.rerataPct).toFixed(1)}%` }}
          </div>
          <div class="text-[11px] text-stone-400 mt-1">dari {{ jumlahSoal }} soal per peserta</div>
        </div>
      </div>

      <!-- ══════ SEBARAN ══════ -->
      <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
        <h3 class="text-[14px] font-bold text-cam-ink">Sebaran tingkat peserta</h3>
        <p class="text-[11.5px] text-stone-400 mt-0.5">
          Nilai tiap peserta dikonversi ke tingkat lebih dulu, baru dirata-rata — bukan
          persentasenya yang dirata-rata, sebab pita konversinya tidak linear.
        </p>

        <div class="grid gap-2.5 mt-4">
          <div v-for="s in sebaran" :key="s.tingkat" class="flex items-center gap-3">
            <span class="w-28 shrink-0 text-[11.5px] font-semibold text-stone-600">
              {{ s.tingkat }} · {{ s.nama }}
            </span>
            <div class="flex-1 h-3 rounded-full bg-stone-100 overflow-hidden">
              <div class="h-full rounded-full transition-[width]"
                   :style="{ width: `${s.pct}%`, background: s.warna }"></div>
            </div>
            <span class="w-16 shrink-0 text-right text-[11.5px] num text-stone-500">
              {{ s.jumlah }}<span v-if="adaPeserta" class="text-stone-300"> · {{ s.pct.toFixed(0) }}%</span>
            </span>
          </div>
        </div>

        <div class="mt-4 pt-4 border-t border-stone-100 flex flex-wrap gap-x-5 gap-y-1 text-[11px] text-stone-400">
          <span v-for="b in pita" :key="b.tingkat">
            &lt; {{ (b.batas * 100).toFixed(0) }}% → tingkat {{ b.tingkat }}
          </span>
          <span>≥ {{ (pita.length ? pita[pita.length - 1].batas * 100 : 85).toFixed(0) }}% → tingkat 5</span>
        </div>
      </section>

      <!-- ══════ SKOR PJ SAAT INI ══════ -->
      <section v-if="skorKini"
               class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
        <h3 class="text-[14px] font-bold text-cam-ink">
          Skor PJ pada penilaian
          <span class="font-normal text-[11.5px] text-stone-400">
            | {{ skorKini.kode }} {{ skorKini.nama }}
          </span>
        </h3>

        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 mt-2">
          <span class="stat stat-sm num"
                :style="{ color: skorKini.nilai ? warnaTingkat[skorKini.nilai - 1] : '#C4C7CD' }">
            {{ skorKini.nilai ?? 'belum diisi' }}
          </span>
          <span v-if="skorKini.nilai" class="text-[12px] text-stone-500">
            {{ namaTingkat[skorKini.nilai - 1] }}
          </span>
          <span v-if="skorKini.ket" class="text-[11.5px] text-stone-400">{{ skorKini.ket }}</span>
        </div>

        <p v-if="adaPeserta && skorKini.nilai !== ringkas.tingkat"
           class="text-[11.5px] mt-2 rounded-lg px-3 py-2" style="background:#FEF3C7;color:#92400E">
          Hasil pengujian sekarang menunjukkan tingkat {{ ringkas.tingkat }}, berbeda dari yang
          tercatat. Tekan “Terapkan ke skor PJ” bila hasil ini yang ingin dipakai.
        </p>
      </section>

      <!-- ══════ PESERTA ══════ -->
      <section class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-stone-100">
          <h3 class="text-[14px] font-bold text-cam-ink">
            Hasil peserta
            <span class="font-normal text-[11.5px] text-stone-400">
              | {{ ringkas.peserta }} orang<span v-if="berpindah">, {{ berpindah }} berpindah layar saat mengerjakan</span>
            </span>
          </h3>
        </div>

        <div v-if="!adaPeserta" class="px-5 py-10 text-center text-[12.5px] text-stone-400">
          Belum ada yang mengerjakan. Sebarkan tautan di atas kepada pekerja.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full text-left text-[12px]">
            <thead class="text-[10px] uppercase tracking-wider text-stone-400 bg-stone-50">
              <tr>
                <th class="px-5 py-2.5 font-bold">Peserta</th>
                <th class="px-3 py-2.5 font-bold">Jabatan</th>
                <th class="px-3 py-2.5 font-bold text-right">Benar</th>
                <th class="px-3 py-2.5 font-bold text-right">Tingkat</th>
                <th class="px-3 py-2.5 font-bold text-right">Durasi</th>
                <th class="px-3 py-2.5 font-bold text-right" title="Berapa kali peserta meninggalkan layar pengujian">
                  Pindah layar
                </th>
                <th class="px-3 py-2.5 font-bold">Waktu</th>
                <th class="px-5 py-2.5"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in peserta" :key="p.id" class="border-t border-stone-50">
                <td class="px-5 py-2.5">
                  <div class="font-semibold text-cam-ink">{{ p.nama }}</div>
                  <div class="text-[10.5px] text-stone-400">
                    {{ [p.nrp, p.dept, p.perusahaan].filter(Boolean).join(' · ') || '—' }}
                  </div>
                </td>
                <td class="px-3 py-2.5 text-stone-500">{{ p.jabatan ?? '—' }}</td>
                <td class="px-3 py-2.5 text-right num">{{ p.benar }} / {{ p.total }}</td>
                <td class="px-3 py-2.5 text-right">
                  <span class="inline-block rounded-md px-2 py-0.5 text-[11px] font-bold text-white"
                        :style="{ background: warnaTingkat[p.tingkat - 1] ?? '#C4C7CD' }">
                    {{ p.tingkat }} · {{ p.label }}
                  </span>
                </td>
                <td class="px-3 py-2.5 text-right num text-stone-500">{{ durasi(p.durasi) }}</td>
                <td class="px-3 py-2.5 text-right num"
                    :class="p.pindahLayar > 0 ? 'text-amber-600 font-bold' : 'text-stone-300'">
                  {{ p.pindahLayar }}
                </td>
                <td class="px-3 py-2.5 text-stone-400">{{ p.waktu ?? '—' }}</td>
                <td class="px-5 py-2.5 text-right">
                  <button v-if="bisaTerapkan" type="button" @click="hapus(p)"
                          class="text-[11px] font-bold text-red-600 hover:underline">Hapus</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <p class="px-5 py-3 border-t border-stone-100 text-[11px] text-stone-400 leading-relaxed">
          <b>Pindah layar</b> menghitung berapa kali peserta meninggalkan halaman pengujian saat
          mengerjakan. Angka di atas nol bukan bukti kecurangan — notifikasi masuk, panggilan
          telepon, dan layar yang mati sendiri semuanya terhitung — tetapi ia menjelaskan
          nilai yang tampak menyimpang, dan tanpa angka ini penjelasan itu tidak ada.
        </p>
      </section>
    </template>

    <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
  </div>
</template>

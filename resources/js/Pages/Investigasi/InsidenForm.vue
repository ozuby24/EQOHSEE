<script setup lang="ts">
/**
 * Formulir lapor insiden.
 *
 * ── ENAM PEMICU DI BAGIAN BAWAH ──
 *
 * Blok "keadaan saat kejadian" bukan pertanyaan tambahan yang boleh
 * dilewati. Keenamnya adalah pemicu lapis ketiga mesin saran SCAT:
 * shift malam, jam kerja panjang, SOP tidak ada, pekerja belum dilatih,
 * inspeksi tidak pernah, kejadian berulang. Tanpa medan yang
 * menanyakannya, seluruh kode lapis 3 tetap benar dan tidak pernah
 * berjalan sekali pun — cacat yang paling mudah lolos, sebab ujinya
 * hijau memakai data contoh yang mengisi kolomnya langsung.
 *
 * Sesudah tersimpan, halaman ini mengalihkan LANGSUNG ke triase.
 * Insiden yang tercatat tanpa level adalah insiden yang belum dinilai
 * siapa pun, dan langkah yang dipisahkan satu klik dari penyimpanan
 * adalah langkah yang paling sering tidak pernah dikerjakan.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';

const props = propHalaman();

const f = useForm<Record<string, any>>({
  judul: '', tanggal_kejadian: '', waktu_kejadian: '',
  lokasi_id: '', lokasi_rinci: '', aktivitas: '',
  jenis_insiden_id: '', klasifikasi_cedera_id: '',
  kronologi: '', tindakan_segera: '',
  p_shift_malam: false, p_lembur_panjang: false, p_sop_tidak_ada: false,
  p_belum_dilatih: false, p_inspeksi_absen: false, p_insiden_berulang: false,
});

const PEMICU: Array<[string, string, string]> = [
  ['p_shift_malam',      'Shift malam',                   'Kejadian berlangsung pada giliran malam.'],
  ['p_lembur_panjang',   'Jam kerja panjang / lembur',    'Pekerja sudah bertugas melewati jam normalnya.'],
  ['p_sop_tidak_ada',    'SOP tidak tersedia',            'Tidak ada prosedur tertulis untuk pekerjaan itu.'],
  ['p_belum_dilatih',    'Pekerja belum dilatih',         'Belum ada catatan pelatihan untuk tugas itu.'],
  ['p_inspeksi_absen',   'Inspeksi tidak pernah',         'Area atau alatnya tidak pernah diinspeksi.'],
  ['p_insiden_berulang', 'Kejadian serupa pernah terjadi', 'Pernah ada kejadian sejenis sebelumnya.'],
];

function simpan() {
  f.post('/investigasi/insiden', { preserveScroll: true });
}
</script>

<template>
  <Head title="Lapor Insiden" />

  <div class="max-w-[1000px] mx-auto space-y-5">
    <section>
      <h2 class="text-xl font-bold text-cam-ink">Lapor Insiden</h2>
      <p class="text-[12.5px] text-stone-500 mt-0.5">
        Catat kejadiannya lebih dahulu; penilaian tingkat keseriusan dikerjakan pada langkah berikutnya.
      </p>
    </section>

    <form class="grid gap-4" @submit.prevent="simpan">

      <!-- ══════════ apa yang terjadi ══════════ -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <header class="px-5 py-3.5 border-b border-stone-100">
          <h3 class="text-[13.5px] font-bold text-cam-ink">
            Kejadian <span class="font-normal text-stone-400">| apa, kapan, di mana</span>
          </h3>
        </header>

        <div class="px-5 py-4 grid gap-3">
          <label class="grid gap-1">
            <span class="text-[10px] uppercase tracking-wide text-stone-400">
              Judul kejadian<span class="text-red-600">*</span>
            </span>
            <input v-model="f.judul" class="rounded-lg border-stone-200 text-[12.5px]"
                   placeholder="Dump truck menabrak tanggul pengaman di jalan hauling KM 4">
            <span v-if="f.errors.judul" class="text-[11px] text-red-600">{{ f.errors.judul }}</span>
          </label>

          <div class="grid gap-3 md:grid-cols-3">
            <label class="grid gap-1">
              <span class="text-[10px] uppercase tracking-wide text-stone-400">
                Tanggal<span class="text-red-600">*</span>
              </span>
              <input v-model="f.tanggal_kejadian" type="date" class="rounded-lg border-stone-200 text-[12.5px]">
              <span v-if="f.errors.tanggal_kejadian" class="text-[11px] text-red-600">{{ f.errors.tanggal_kejadian }}</span>
            </label>

            <label class="grid gap-1">
              <span class="block text-[10px] uppercase tracking-wide text-stone-400">Waktu</span>
              <input v-model="f.waktu_kejadian" type="time" class="rounded-lg border-stone-200 text-[12.5px]">
              <!-- Tenggat regulasi dihitung dari waktu ini, bukan dari
                   waktu pelaporan — dan itu disebutkan supaya yang
                   mengisinya tahu mengapa jamnya diminta. -->
              <span class="text-[10.5px] text-stone-400">Tenggat lapor 24 jam dihitung dari jam ini.</span>
            </label>

            <label class="grid gap-1">
              <span class="block text-[10px] uppercase tracking-wide text-stone-400">Jenis kejadian</span>
              <select v-model="f.jenis_insiden_id" class="rounded-lg border-stone-200 text-[12.5px]">
                <option value="">—</option>
                <option v-for="j in (props.opsi?.jenisInsiden ?? [])" :key="j.id" :value="j.id">{{ j.nama }}</option>
              </select>
            </label>
          </div>

          <div class="grid gap-3 md:grid-cols-3">
            <label class="grid gap-1">
              <span class="block text-[10px] uppercase tracking-wide text-stone-400">Lokasi</span>
              <select v-model="f.lokasi_id" class="rounded-lg border-stone-200 text-[12.5px]">
                <option value="">—</option>
                <option v-for="l in (props.opsi?.lokasi ?? [])" :key="l.id" :value="l.id">{{ l.nama }}</option>
              </select>
            </label>

            <label class="grid gap-1 md:col-span-2">
              <span class="block text-[10px] uppercase tracking-wide text-stone-400">Titik rinci</span>
              <input v-model="f.lokasi_rinci" class="rounded-lg border-stone-200 text-[12.5px]"
                     placeholder="Tikungan menurun sesudah simpang workshop">
            </label>
          </div>

          <div class="grid gap-3 md:grid-cols-2">
            <label class="grid gap-1">
              <span class="block text-[10px] uppercase tracking-wide text-stone-400">Aktivitas saat kejadian</span>
              <input v-model="f.aktivitas" class="rounded-lg border-stone-200 text-[12.5px]"
                     placeholder="Hauling overburden ke disposal">
            </label>

            <label class="grid gap-1">
              <span class="block text-[10px] uppercase tracking-wide text-stone-400">Klasifikasi cedera</span>
              <select v-model="f.klasifikasi_cedera_id" class="rounded-lg border-stone-200 text-[12.5px]">
                <option value="">—</option>
                <option v-for="c in (props.opsi?.cedera ?? [])" :key="c.id" :value="c.id">{{ c.nama }}</option>
              </select>
            </label>
          </div>

          <label class="grid gap-1">
            <span class="block text-[10px] uppercase tracking-wide text-stone-400">Uraian kejadian</span>
            <textarea v-model="f.kronologi" rows="5" class="rounded-lg border-stone-200 text-[12.5px]"
                      placeholder="Ceritakan urutannya dari sebelum sampai sesudah kejadian."></textarea>
            <span class="text-[10.5px] text-stone-400">
              Kata pada uraian ini dibaca mesin saran penyebab — semakin apa adanya, semakin berguna.
            </span>
          </label>

          <label class="grid gap-1">
            <span class="block text-[10px] uppercase tracking-wide text-stone-400">Tindakan segera yang sudah dilakukan</span>
            <textarea v-model="f.tindakan_segera" rows="2" class="rounded-lg border-stone-200 text-[12.5px]"></textarea>
          </label>
        </div>
      </section>

      <!-- ══════════ keadaan saat kejadian ══════════ -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <header class="px-5 py-3.5 border-b border-stone-100">
          <h3 class="text-[13.5px] font-bold text-cam-ink">
            Keadaan saat kejadian <span class="font-normal text-stone-400">| centang yang benar</span>
          </h3>
          <p class="text-[11px] text-stone-500 mt-0.5">
            Enam keadaan ini dibaca mesin saran penyebab. Yang tidak dicentang tidak dianggap tidak ada —
            hanya tidak ikut mempersempit daftar.
          </p>
        </header>

        <div class="px-5 py-4 grid gap-2 sm:grid-cols-2">
          <label v-for="[kunci, label, ket] in PEMICU" :key="kunci"
                 class="flex items-start gap-2.5 rounded-xl border border-stone-100 px-3.5 py-2.5 cursor-pointer hover:border-stone-200">
            <input v-model="f[kunci]" type="checkbox" class="mt-0.5 rounded border-stone-300">
            <span class="min-w-0">
              <span class="text-[12px] font-semibold text-cam-ink block">{{ label }}</span>
              <span class="text-[10.5px] text-stone-400">{{ ket }}</span>
            </span>
          </label>
        </div>
      </section>

      <div class="flex items-center gap-3">
        <span class="inline-flex">
          <button class="eq-btn-utama" :disabled="f.processing">Simpan lalu lanjut ke triase →</button>
        </span>
        <p class="text-[11px] text-stone-400">
          Triase menentukan level investigasi, dan level menentukan berapa tahap yang harus dilalui.
        </p>
      </div>
    </form>
  </div>
</template>

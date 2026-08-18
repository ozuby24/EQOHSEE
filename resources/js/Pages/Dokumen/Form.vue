<script setup lang="ts">
/**
 * Formulir dokumen terkendali.
 *
 * Centang klausul ISO dikirim sebagai keadaan lengkap, bukan tambahan:
 * server menulis ulang seluruh pemetaannya tiap simpan, sehingga klausul
 * yang dicabut centangnya benar-benar terlepas.
 */
import { Head, useForm } from '@inertiajs/vue3';
import type { HalamanFormDokumen } from '../../types';

const props = defineProps<HalamanFormDokumen>();

/** Klausul tercentang per standar, berangkat dari yang sudah tersimpan. */
const isoAwal: Record<string, string[]> = Object.fromEntries(
  props.standar.map((s) => [s.kode, [...(props.isoAwal[s.kode] ?? [])]]),
);

/**
 * Standar mana yang terbuka saat halaman dibuka.
 *
 * Dihitung sekali dan tidak reaktif. Mengikatnya ke jumlah centang yang
 * berjalan membuat panelnya menutup sendiri begitu centang terakhir
 * dilepas — tepat ketika pengguna masih memilih, dan tepat pada panel
 * yang sedang dikerjakannya.
 */
const terbukaAwal: Record<string, boolean> = Object.fromEntries(
  props.standar.map((s) => [s.kode, isoAwal[s.kode].length > 0]),
);

const form = useForm<Record<string, any>>({ ...props.awal, iso: isoAwal, berkas: null as File | null });

function pilihBerkas(e: Event) {
  const f = (e.target as HTMLInputElement).files;
  form.berkas = f && f.length ? f[0] : null;
}

/**
 * Centang dikerjakan langsung atas form.iso, bukan atas salinan lain.
 *
 * useForm menyalin dalam data awalnya, jadi objek yang diserahkan
 * kepadanya bukan lagi objek yang sama. Sempat terjadi persis begitu:
 * centangnya berubah di layar, tombol simpan berhasil, dan pemetaan
 * klausulnya tidak bergerak sama sekali.
 */
function centang(kode: string, no: string) {
  const daftar = form.iso[kode];
  const i = daftar.indexOf(no);

  if (i === -1) daftar.push(no);
  else daftar.splice(i, 1);
}

function simpan() {
  if (props.tersimpan) form.put(props.tautan.simpan);
  else form.post(props.tautan.simpan);
}

const isian = 'ring-focus w-full rounded-xl border border-stone-200 px-4 py-3 text-[13.5px] transition';
const label = 'block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5';
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-2xl mx-auto space-y-5">

    <div v-if="Object.keys(form.errors).length"
         class="rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]">
      <ul class="space-y-0.5">
        <li v-for="(pesan, k) in form.errors" :key="k">• {{ pesan }}</li>
      </ul>
    </div>

    <form class="bg-white rounded-2xl shadow-card border border-stone-100 p-6 space-y-4"
          @submit.prevent="simpan">

      <div class="grid gap-3 sm:grid-cols-3">
        <div>
          <label :class="label">Kode <span class="text-red-500">*</span></label>
          <input v-model="form.kode" placeholder="SOP-K3-001" :class="isian">
        </div>
        <div class="sm:col-span-2">
          <label :class="label">Judul <span class="text-red-500">*</span></label>
          <input v-model="form.judul" :class="isian">
        </div>
      </div>

      <div class="grid gap-3 sm:grid-cols-3">
        <div>
          <label :class="label">Jenis <span class="text-red-500">*</span></label>
          <select v-model="form.jenis" :class="isian" aria-label="Jenis">
            <option v-for="j in opsi.jenis" :key="j" :value="j">{{ j }}</option>
          </select>
        </div>
        <div>
          <label :class="label">Status <span class="text-red-500">*</span></label>
          <select v-model="form.status" :class="isian" aria-label="Status">
            <option v-for="s in opsi.status" :key="s" :value="s">{{ s[0].toUpperCase() + s.slice(1) }}</option>
          </select>
        </div>
        <div>
          <label :class="label">Revisi</label>
          <input v-model="form.revisi" type="number" min="0" max="999" :class="isian">
        </div>
      </div>

      <div class="grid gap-3 sm:grid-cols-2">
        <div>
          <label :class="label">Departemen pemilik</label>
          <input v-model="form.departemen" :class="isian">
        </div>
        <div>
          <label :class="label">Klasifikasi</label>
          <select v-model="form.klasifikasi" :class="isian" aria-label="Klasifikasi">
            <option value="">—</option>
            <option v-for="k in opsi.klasifikasi" :key="k" :value="k">{{ k }}</option>
          </select>
        </div>
      </div>

      <div>
        <label :class="label">Perusahaan</label>
        <select v-model="form.company_id" :class="isian" aria-label="Perusahaan">
          <option value="">— seluruh perusahaan —</option>
          <option v-for="c in opsi.perusahaan" :key="c.nilai" :value="c.nilai">{{ c.label }}</option>
        </select>
      </div>

      <div>
        <label :class="label">Tautkan ke prosedur LMS</label>
        <select v-model="form.procedure_id" :class="isian" aria-label="Tautkan ke prosedur LMS">
          <option value="">— tidak ditautkan —</option>
          <option v-for="p in opsi.prosedur" :key="p.nilai" :value="p.nilai">{{ p.label }}</option>
        </select>
        <p class="text-[11px] text-stone-400 mt-1">
          Agar SOP tidak dicatat dua kali: dokumen terkendali di sini, materi pelatihannya di modul LMS.
        </p>
      </div>

      <div class="grid gap-3 sm:grid-cols-3">
        <div>
          <label :class="label">Tanggal terbit</label>
          <input v-model="form.tanggal_terbit" type="date" :class="isian" aria-label="Tanggal terbit">
        </div>
        <div>
          <label :class="label">Mulai berlaku</label>
          <input v-model="form.tanggal_berlaku" type="date" :class="isian" aria-label="Mulai berlaku">
        </div>
        <div>
          <label :class="label">Jatuh tempo tinjau</label>
          <input v-model="form.tanggal_tinjau" type="date" :class="isian" aria-label="Jatuh tempo tinjau">
        </div>
      </div>

      <div class="grid gap-3 sm:grid-cols-2">
        <div>
          <label :class="label">Acuan klausul</label>
          <input v-model="form.acuan" placeholder="ISO 45001 klausul 7.5 / SMKP elemen VI" :class="isian">
        </div>
        <div>
          <label :class="label">Disetujui oleh</label>
          <input v-model="form.disetujui_oleh" :class="isian">
        </div>
      </div>

      <div>
        <label :class="label">Ringkasan</label>
        <textarea v-model="form.ringkasan" rows="3" :class="isian"></textarea>
      </div>

      <div>
        <label :class="label">Berkas dokumen</label>
        <input type="file" @change="pilihBerkas"
               class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-3 text-[12.5px]
                      transition file:mr-3 file:rounded-lg file:border-0 file:bg-cam-lime-soft
                      file:px-3 file:py-1.5 file:text-[12px] file:font-bold file:text-cam-lime-deep">
        <p v-if="adaBerkas" class="text-[11px] text-stone-400 mt-1">
          Berkas saat ini tersimpan. Unggah baru untuk menggantinya.
        </p>
        <p v-if="form.progress" class="text-[11px] text-stone-500 mt-1">
          Mengunggah {{ form.progress.percentage }}%
        </p>
      </div>

      <div>
        <label :class="label">Klausul ISO yang dipenuhi</label>
        <p class="text-[11.5px] text-stone-400 mb-3 leading-relaxed">
          Centang klausul yang benar-benar dijawab dokumen ini. Dari sinilah halaman
          pemenuhan mengetahui klausul mana yang masih tanpa dokumen.
        </p>

        <div class="space-y-2.5">
          <details v-for="s in standar" :key="s.kode"
                   class="rounded-xl border border-stone-200 overflow-hidden" :open="terbukaAwal[s.kode]">
            <summary class="px-4 py-3 cursor-pointer flex flex-wrap items-center gap-2.5
                            hover:bg-stone-50 transition">
              <span class="shrink-0 w-2.5 h-2.5 rounded-full" :style="{ background: s.warna }"></span>
              <span class="text-[12.5px] font-bold text-cam-ink">{{ s.nama }}</span>
              <span class="text-[11.5px] text-stone-400">{{ s.judul }}</span>
              <span v-if="form.iso[s.kode].length" class="ml-auto text-[10.5px] font-bold text-cam-lime-deep">
                {{ form.iso[s.kode].length }} klausul
              </span>
            </summary>

            <div class="px-4 pb-3.5 pt-1 grid gap-1.5 sm:grid-cols-2">
              <label v-for="k in s.butir" :key="k.no"
                     class="flex items-start gap-2 rounded-lg px-2.5 py-1.5 hover:bg-cam-sand/30
                            cursor-pointer transition">
                <input type="checkbox" class="mt-0.5 shrink-0 accent-[color:var(--eq-aksen,#F57C00)]"
                       :checked="form.iso[s.kode].includes(k.no)" @change="centang(s.kode, k.no)">
                <span class="text-[11.5px] text-stone-600 leading-snug">
                  <span class="num font-semibold text-cam-ink">{{ k.no }}</span> {{ k.judul }}
                </span>
              </label>
            </div>
          </details>
        </div>
      </div>

      <div class="flex flex-wrap gap-2 pt-2">
        <button type="submit" :disabled="form.processing"
                class="lime-gradient shadow-glow rounded-xl text-white px-5 py-3 text-[13px]
                       font-bold hover:brightness-105 transition disabled:opacity-40">
          {{ form.processing ? 'Menyimpan…' : (tersimpan ? 'Simpan Perubahan' : 'Daftarkan Dokumen') }}
        </button>
        <a :href="tautan.batal"
           class="rounded-xl border border-stone-200 px-5 py-3 text-[13px] font-bold
                  text-stone-500 hover:bg-stone-50 transition">Batal</a>
      </div>
    </form>
  </div>
</template>

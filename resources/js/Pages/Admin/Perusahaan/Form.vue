<script setup lang="ts">
/**
 * Formulir perusahaan.
 *
 * Kendali dokumen di sini bukan hiasan: divisi, departemen, prefiks, dan
 * tanggal-tanggalnya tercetak pada kop tiap berkas audit, jadi isian yang
 * kosong akan terlihat di lembar yang keluar dari printer.
 */
import { ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import type { HalamanFormPerusahaan } from '../../../types';

const props = defineProps<HalamanFormPerusahaan>();

const form = useForm<Record<string, any>>({ ...props.awal, logo: null as File | null });

/* ── Dasar hari kerja audit SMKP ──
 *
 * Jumlah pekerja dan kelas risiko adalah sifat perusahaan, dan keduanya
 * diisi di halaman ini. Konsekuensinya — berapa hari kerja yang dituntut
 * sebuah audit SMKP — dulu baru terlihat berhalaman-halaman kemudian, di
 * Tahap I audit, tempat angkanya harus diketik ulang dan karena itu dapat
 * berselisih dengan profil yang baru saja diisi.
 *
 * Sekarang konsekuensinya terlihat di tempat sebabnya diketik.
 *
 * TABELNYA TIDAK DISALIN KE SINI. Dua puluh baris angka yang menagih hari
 * kerja auditor, hidup di dua bahasa sekaligus, akan berselisih pada baris
 * yang paling jarang dilihat. Jawabannya datang dari tempat yang sama
 * dengan yang dipakai audit.
 */
const mandays = ref({ ...props.mandays });
let jeda: ReturnType<typeof setTimeout> | undefined;
let permintaanKe = 0;

async function hitungMandays() {
  const ini = ++permintaanKe;

  try {
    const r = await fetch(props.tautan.mandays, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
      },
      body: JSON.stringify({
        workers_employee: Number(form.workers_employee) || 0,
        workers_sub:      Number(form.workers_sub) || 0,
        risk_class:       form.risk_class,
      }),
    });
    if (!r.ok) return;

    /* Jawaban yang datang terlambat dibuang: mengetik 1, 10, lalu 109
       melahirkan tiga permintaan, dan yang tiba terakhir belum tentu yang
       terbaru. */
    const hasil = await r.json();
    if (ini === permintaanKe) mandays.value = hasil;
  } catch {
    /* Panelnya menahan angka sah terakhir, bukan menampilkan nol. */
  }
}

watch(
  () => [form.workers_employee, form.workers_sub, form.risk_class],
  () => { clearTimeout(jeda); jeda = setTimeout(hitungMandays, 300); },
);

function pilihLogo(e: Event) {
  const f = (e.target as HTMLInputElement).files;
  form.logo = f && f.length ? f[0] : null;
}

function simpan() {
  if (props.tersimpan) form.put(props.tautan.simpan);
  else form.post(props.tautan.simpan);
}

const isian = 'ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition';
const label = 'block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5';
const kepala = 'text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400';
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-2xl mx-auto">
    <form class="bg-white rounded-2xl shadow-card border border-stone-100 p-6 space-y-6"
          @submit.prevent="simpan">

      <div v-if="Object.keys(form.errors).length"
           class="rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]">
        <ul class="space-y-0.5">
          <li v-for="(pesan, k) in form.errors" :key="k">• {{ pesan }}</li>
        </ul>
      </div>

      <div class="space-y-4">
        <p :class="kepala">Identitas</p>
        <div class="grid sm:grid-cols-3 gap-4">
          <div class="sm:col-span-2">
            <label :class="label">Nama perusahaan</label>
            <input v-model="form.name" :class="isian">
          </div>
          <div>
            <label :class="label">Kode</label>
            <input v-model="form.code" placeholder="CDI" :class="isian">
          </div>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label :class="label">Pemilik izin / Induk</label>
            <select v-model="form.parent_id" :class="isian" aria-label="Pemilik izin / Induk">
              <option value="">— berdiri sendiri (IUP/Owner) —</option>
              <option v-for="o in opsi.induk" :key="o.nilai" :value="o.nilai">{{ o.label }}</option>
            </select>
            <p class="text-[10.5px] text-stone-400 mt-1">
              Perusahaan jasa (IUJP) memakai logo pemiliknya bila logonya kosong.
            </p>
          </div>
          <div>
            <label :class="label">Prefiks nomor dokumen</label>
            <input v-model="form.doc_no_prefix" :placeholder="contoh.prefiks" :class="isian">
            <p class="text-[10.5px] text-stone-500 mt-1">
              Singkatan perusahaan pada nomor dokumen — bagian <b>CAM</b> pada
              <span class="font-mono">FRM/CAM/OHSE/001</span>. Dibiarkan kosong berarti berkas
              yang dicetak keluar <b>tanpa nomor</b>; nomor tidak dikarang dari nama perusahaan,
              sebab nomor karangan bertabrakan dengan penomoran Anda sendiri di daftar induk.
            </p>
          </div>
        </div>
      </div>

      <div class="space-y-4">
        <p :class="kepala">Kendali Dokumen</p>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label :class="label">Divisi</label>
            <input v-model="form.divisi" :placeholder="contoh.divisi" :class="isian">
          </div>
          <div>
            <label :class="label">Departemen</label>
            <input v-model="form.departemen" :placeholder="contoh.departemen" :class="isian">
            <p class="text-[10.5px] text-stone-500 mt-1">Nama panjang, dicetak pada kop.</p>
          </div>
          <div>
            <label :class="label">Singkatan departemen</label>
            <input v-model="form.dept_kode" placeholder="OHSE" maxlength="12" :class="isian">
            <p class="text-[10.5px] text-stone-500 mt-1">
              Bagian <b>OHSE</b> pada <span class="font-mono">FRM/CAM/OHSE/001</span>.
              Kosong berarti OHSE.
            </p>
          </div>
          <div>
            <label :class="label">Tanggal penerbitan</label>
            <input v-model="form.doc_terbit" type="date" :class="isian" aria-label="Tanggal penerbitan">
          </div>
          <div>
            <label :class="label">Tanggal persetujuan</label>
            <input v-model="form.doc_setuju" type="date" :class="isian" aria-label="Tanggal persetujuan">
          </div>
          <div>
            <label :class="label">Nomor revisi</label>
            <input v-model="form.doc_revisi" inputmode="numeric" :class="[isian, 'num']">
          </div>
        </div>
      </div>

      <div class="space-y-4">
        <p :class="kepala">Spesifikasi Pertambangan</p>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label :class="label">Jenis izin</label>
            <input v-model="form.izin_type" placeholder="IUP OP Batubara" :class="isian">
          </div>
          <div>
            <label :class="label">Komoditas</label>
            <input v-model="form.commodity" :class="isian">
          </div>
          <div>
            <label :class="label">Site / lokasi</label>
            <input v-model="form.location" :class="isian">
          </div>
          <div>
            <label :class="label">Kelas risiko</label>
            <select v-model="form.risk_class" :class="isian" aria-label="Kelas risiko">
              <option v-for="r in opsi.risiko" :key="r" :value="r">{{ r }}</option>
            </select>
          </div>
          <div class="sm:col-span-2">
            <label :class="label">Alamat</label>
            <input v-model="form.address" :class="isian">
          </div>
        </div>
      </div>

      <div class="space-y-4">
        <p :class="kepala">Penanggung Jawab &amp; Tenaga Kerja</p>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label :class="label">KTT (Kepala Teknik Tambang)</label>
            <input v-model="form.ktt" :class="isian">
          </div>
          <div>
            <label :class="label">PJO</label>
            <input v-model="form.pjo" :class="isian">
          </div>
          <div>
            <label :class="label">Pekerja perusahaan</label>
            <input v-model="form.workers_employee" type="number" min="0" :class="isian">
          </div>
          <div>
            <label :class="label">Pekerja jasa pertambangan</label>
            <input v-model="form.workers_sub" type="number" min="0" :class="isian">
          </div>
        </div>

        <!-- Akibat dari dua angka di atas, diperlihatkan di tempat
             keduanya diketik. -->
        <div class="rounded-xl border border-cam-lime-soft bg-cam-lime-soft/40 p-4">
          <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-cam-lime-deep">
            Dasar Hari Kerja Audit SMKP
          </p>

          <div class="mt-3 flex flex-wrap items-end gap-x-6 gap-y-3">
            <p class="leading-none">
              <strong class="text-[26px] font-black text-cam-ink">{{ mandays.dasar }}</strong>
              <span class="text-[12px] font-semibold text-stone-500"> mandays</span>
            </p>
            <dl class="text-[11.5px] text-stone-600 leading-relaxed">
              <div class="flex gap-1.5">
                <dt class="text-stone-400">Total pekerja</dt>
                <dd class="font-semibold">{{ mandays.pekerja }}</dd>
                <dd class="text-stone-400">(baris tabel {{ mandays.rentang }})</dd>
              </div>
              <div class="flex gap-1.5">
                <dt class="text-stone-400">Kelas risiko</dt>
                <dd class="font-semibold">{{ mandays.kelas }}</dd>
              </div>
            </dl>
          </div>

          <p class="text-[11px] text-stone-500 mt-3 leading-relaxed">
            Angka dasar, sebelum tujuh faktor penyesuaian. Faktor-faktor itu milik
            tiap audit — jarak antar objek audit dan kinerja keselamatan pada periode
            audit berubah tiap tahun — dan ditetapkan pada Tahap I audit, yang membaca
            jumlah pekerja serta kelas risiko dari halaman ini.
          </p>
        </div>

        <div class="grid sm:grid-cols-3 gap-4 pt-2 border-t border-stone-100">
          <div class="sm:col-span-3">
            <p :class="kepala">PIC Tindak Lanjut Temuan</p>
            <p class="text-[11px] text-stone-400 mt-1">
              Tujuan pengingat email &amp; WhatsApp untuk temuan yang belum ditutup.
            </p>
          </div>
          <div>
            <label :class="label">Nama PIC</label>
            <input v-model="form.pic_name" :class="isian">
          </div>
          <div>
            <label :class="label">Email PIC</label>
            <input v-model="form.pic_email" type="email" placeholder="pic@perusahaan.co.id" :class="isian">
          </div>
          <div>
            <label :class="label">WhatsApp PIC</label>
            <input v-model="form.pic_phone" placeholder="08123456789" :class="isian">
          </div>
        </div>

        <div>
          <label :class="label">Logo</label>
          <img v-if="logo" :src="logo" class="h-12 mb-2 rounded-lg" alt="Logo perusahaan">
          <input type="file" accept="image/*" class="text-[12px] text-stone-500" @change="pilihLogo">
          <p v-if="form.progress" class="text-[11px] text-stone-500 mt-1">
            Mengunggah {{ form.progress.percentage }}%
          </p>
        </div>
      </div>

      <div class="flex items-center gap-3 pt-1">
        <button type="submit" :disabled="form.processing"
                class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2.5 text-[12.5px]
                       font-bold hover:brightness-105 transition disabled:opacity-40">
          {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
        </button>
        <a :href="tautan.batal" class="text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink">Batal</a>
      </div>
    </form>
  </div>
</template>

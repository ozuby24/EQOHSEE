<script setup lang="ts">
/**
 * Formulir perusahaan.
 *
 * Kendali dokumen di sini bukan hiasan: divisi, departemen, prefiks, dan
 * tanggal-tanggalnya tercetak pada kop tiap berkas audit, jadi isian yang
 * kosong akan terlihat di lembar yang keluar dari printer.
 */
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import type { HalamanFormPerusahaan } from '../../../types';

const props = defineProps<HalamanFormPerusahaan>();

const form = useForm<Record<string, any>>({ ...props.awal, logo: null as File | null });

function pilihLogo(e: Event) {
  const f = (e.target as HTMLInputElement).files;
  form.logo = f && f.length ? f[0] : null;
}

function simpan() {
  if (props.tersimpan) form.put(props.tautan.simpan);
  else form.post(props.tautan.simpan);
}

/* ── Kop formulir audit SMKP (porting Aurorasys) ──
 *
 * Kosong berarti kop bawaan EQOHSEE. Kode yang diisi mengganti nomor
 * dokumennya menjadi bentuk Aurorasys — Kode singkat perusahaan + kode
 * formulir, mis. CAM-HSE-IV.067h — supaya berkas cetak dapat disamakan
 * dengan penomoran yang sudah beredar. Pratinjaunya mengikuti isian yang
 * sedang diketik, bukan yang tersimpan.
 */
const kunciFormulir = Object.keys(props.daftarFormulir);
const pratinjauKunci = ref<string>(kunciFormulir[0] ?? '');
const prefiksKode = computed(() => String(form.code ?? '').trim() || props.contoh.prefiks);

function nomorBawaan(k: string): string {
  const d = props.daftarFormulir[k];
  const pt = String(form.doc_no_prefix ?? '').trim();
  if (!d || !pt) return '';
  const dept = String(form.dept_kode ?? '').trim() || 'OHSE';
  return `${d.jenisNomor}/${pt}/${dept}/${String(d.urut).padStart(3, '0')}`;
}

const kopTampil = computed(() => {
  const k = pratinjauKunci.value;
  const bawaan = props.daftarFormulir[k];
  const ubah = form.kop_formulir?.[k] ?? { jenis: '', judul: '', kode: '' };
  const kode = String(ubah.kode ?? '').trim();
  return {
    jenis: String(ubah.jenis ?? '').trim() || bawaan?.jenis || '',
    judul: String(ubah.judul ?? '').trim() || bawaan?.judul || '',
    nomor: kode ? `${prefiksKode.value}-${kode}` : nomorBawaan(k) || '—',
  };
});
const tgl = (v: string) => (v ? new Date(`${v}T00:00:00`).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }) : '—');

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
            <label :class="label">Kategori (audit SMKP)</label>
            <select v-model="form.kategori" :class="isian" aria-label="Kategori perusahaan">
              <option v-for="o in opsi.kategori" :key="o.nilai" :value="o.nilai">{{ o.label }}</option>
            </select>
            <p class="text-[10.5px] text-stone-500 mt-1">
              Menentukan pengesah Rencana Audit (PJO wajib bagi PJP), distribusi laporan, dan
              penanda tangan berkas audit SMKP.
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

      <div class="space-y-3">
        <p :class="kepala">Kop Formulir Audit SMKP</p>
        <p class="text-[11px] text-stone-500 leading-relaxed">
          Kosongkan untuk memakai kop bawaan. <b>Kode</b> yang diisi mengganti nomor dokumen menjadi
          Kode singkat ({{ prefiksKode }}) + kode formulir, mis.
          <span class="font-mono">{{ prefiksKode }}-HSE-IV.067h</span> — bentuk penomoran Aurorasys.
          Klik baris untuk melihat pratinjau kopnya.
        </p>
        <div class="tabel-scroll rounded-xl border border-stone-200">
          <table class="w-full min-w-[640px] text-[12px]" data-tabel-tetap>
            <thead>
              <tr class="text-left text-[10px] font-bold uppercase tracking-wide text-stone-500 bg-stone-50">
                <th class="px-3 py-2 w-[30%]">Formulir</th>
                <th class="px-3 py-2 w-[24%]">Kode</th>
                <th class="px-3 py-2">Judul pada kop</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(d, k) in daftarFormulir" :key="k" class="border-t border-stone-100 cursor-pointer"
                  :class="pratinjauKunci === k ? 'bg-cam-orange-soft' : ''" @click="pratinjauKunci = String(k)">
                <td class="px-3 py-2 font-semibold text-stone-700">{{ d.nama }}</td>
                <td class="px-3 py-1.5">
                  <input v-model="form.kop_formulir[k].kode" maxlength="40" :placeholder="nomorBawaan(String(k)) || d.kode"
                         :class="[isian, 'py-1.5 text-[12px] num']" :aria-label="`Kode ${d.judul}`">
                </td>
                <td class="px-3 py-1.5">
                  <input v-model="form.kop_formulir[k].judul" maxlength="190" :placeholder="d.judul"
                         :class="[isian, 'py-1.5 text-[12px]']" :aria-label="`Judul ${d.judul}`">
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <p :class="label">Pratinjau kop</p>
        <div class="tabel-scroll rounded-xl border border-stone-200 bg-stone-50 p-3">
          <table class="w-full min-w-[520px] border-collapse bg-white text-[10px] text-stone-800" data-tabel-tetap>
            <tbody>
              <tr>
                <td class="border border-stone-300 p-2 w-[17%] text-center align-middle">
                  <img v-if="logo" :src="logo" alt="" class="h-10 w-auto max-w-full object-contain mx-auto">
                  <span v-else class="font-bold text-[11px] leading-tight">{{ form.name || 'Nama perusahaan' }}</span>
                </td>
                <td class="border border-stone-300 p-2 text-center align-middle">
                  <p class="font-bold uppercase tracking-wide text-stone-500">{{ kopTampil.jenis }}</p>
                  <p class="font-bold uppercase leading-snug mt-1 text-[11px]">{{ kopTampil.judul }}</p>
                </td>
                <td class="border border-stone-300 p-2 w-[34%] align-middle">
                  <p v-for="r in [['No. Dokumen', kopTampil.nomor], ['Tgl Penerbitan', tgl(form.doc_terbit)], ['Tgl Persetujuan', tgl(form.doc_setuju)], ['No. Revisi', String(form.doc_revisi ?? 0).padStart(2, '0')], ['Halaman', '1 dari 1']]"
                     :key="r[0]" class="flex gap-1">
                    <span class="w-20 text-stone-500">{{ r[0] }}</span>:<b class="num">{{ r[1] }}</b>
                  </p>
                </td>
              </tr>
              <tr>
                <td colspan="3" class="border border-stone-300 px-2 py-1.5">
                  <p><span class="inline-block w-20 text-stone-500">Divisi</span>: {{ form.divisi || contoh.divisi }}</p>
                  <p><span class="inline-block w-20 text-stone-500">Departemen</span>: {{ form.departemen || contoh.departemen }}</p>
                </td>
              </tr>
            </tbody>
          </table>
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

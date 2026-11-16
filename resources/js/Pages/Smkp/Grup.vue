<script setup lang="ts">
/**
 * Audit SMKP → Perusahaan Grup (port DBEST #21, khusus administrator).
 *
 * Audit perusahaan lain satu grup dengan nama diketik manual. Isinya sama
 * lengkapnya dengan audit biasa (dibuka lewat halaman audit yang sama),
 * tetapi terpisah: tidak ikut Daftar Audit, Dasbor, statistik, tindak
 * lanjut lintas audit, lonceng, maupun pengingat. Ringkasannya hanya di sini.
 */
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import KepalaHalaman from '../../Components/Smkp/KepalaHalaman.vue';
import Ikon from '../../Components/Smkp/Ikon.vue';
import Kosong from '../../Components/Smkp/Kosong.vue';
import { angka } from '../../smkp/lib/format';

interface Baris {
  id: number; perusahaan: string; jenis: string; labelJenis: string; tahun: number; judul: string | null;
  status: string; labelStatus: string; tahap: string; ketua: string | null; mulai: string | null; selesai: string | null;
  skor: number | null; tingkat: string; temuan: number; terbuka: number;
}

const props = defineProps<{
  baris: Baris[];
  ringkas: { audit: number; dinilai: number; rata: number | null; terbuka: number };
  jenis: Record<string, string>;
  status: Record<string, string>;
  ubah: number | null;
  tahunKini: number;
}>();

const kosong = () => ({ perusahaan_luar: '', jenis_luar: 'iup', tahun: props.tahunKini, judul: '', status: 'draft', tanggal_mulai: '', tanggal_selesai: '', ketua_auditor: '' });
const edit = ref<number | null>(props.ubah);
const awal = props.ubah ? props.baris.find((b) => b.id === props.ubah) : undefined;
const form = useForm(awal
  ? { perusahaan_luar: awal.perusahaan, jenis_luar: awal.jenis, tahun: awal.tahun, judul: awal.judul ?? '', status: awal.status,
      tanggal_mulai: awal.mulai ?? '', tanggal_selesai: awal.selesai ?? '', ketua_auditor: awal.ketua ?? '' }
  : kosong());

function mulaiUbah(b: Baris) {
  edit.value = b.id;
  Object.assign(form, { perusahaan_luar: b.perusahaan, jenis_luar: b.jenis, tahun: b.tahun, judul: b.judul ?? '', status: b.status,
    tanggal_mulai: b.mulai ?? '', tanggal_selesai: b.selesai ?? '', ketua_auditor: b.ketua ?? '' });
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
function batalUbah() { edit.value = null; Object.assign(form, kosong()); form.clearErrors(); }
function simpan() {
  if (edit.value) form.put(`/smkp/grup/${edit.value}`, { preserveScroll: true, onSuccess: batalUbah });
  else form.post('/smkp/grup');
}
const err = computed(() => form.errors as Record<string, string>);
</script>

<template>
  <div class="smkp-ui">
    <Head title="Audit Perusahaan Grup" />
    <KepalaHalaman judul="Audit Perusahaan Grup" sub="Perusahaan lain satu grup dengan nama diketik manual — terpisah dari dasbor, statistik, dan pengingat."
                   :jejak="[{ label: 'Audit SMKP', url: '/smkp' }, { label: 'Perusahaan Grup' }]" />

    <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
      <div class="kartu p-4"><p class="text-[12px] text-slate-500">Audit grup</p><p class="text-[24px] font-bold text-navy-900 tabular-nums">{{ ringkas.audit }}</p></div>
      <div class="kartu p-4"><p class="text-[12px] text-slate-500">Sudah dinilai</p><p class="text-[24px] font-bold text-navy-900 tabular-nums">{{ ringkas.dinilai }}</p></div>
      <div class="kartu p-4"><p class="text-[12px] text-slate-500">Rata-rata nilai akhir</p><p class="text-[24px] font-bold text-navy-900 tabular-nums">{{ ringkas.rata !== null ? angka(ringkas.rata, 1) + '%' : '—' }}</p></div>
      <div class="kartu p-4"><p class="text-[12px] text-slate-500">Temuan terbuka</p><p class="text-[24px] font-bold text-navy-900 tabular-nums">{{ ringkas.terbuka }}</p></div>
    </section>

    <form class="kartu p-5 sm:p-6 mb-5" @submit.prevent="simpan">
      <h2 class="kartu-judul mb-3">{{ edit ? 'Ubah audit perusahaan grup' : 'Audit perusahaan grup baru' }}</h2>
      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <label class="block sm:col-span-2"><span class="label block">Nama perusahaan</span>
          <input v-model="form.perusahaan_luar" class="input" maxlength="190" placeholder="Mis. PT Bara Nusantara" required>
          <span v-if="err.perusahaan_luar" class="galat block">{{ err.perusahaan_luar }}</span></label>
        <label class="block"><span class="label block">Jenis</span>
          <select v-model="form.jenis_luar" class="input"><option v-for="(l, k) in jenis" :key="k" :value="k">{{ l }}</option></select></label>
        <label class="block"><span class="label block">Tahun</span>
          <input v-model.number="form.tahun" type="number" min="2000" max="2100" class="input" required>
          <span v-if="err.tahun" class="galat block">{{ err.tahun }}</span></label>
        <label class="block sm:col-span-2"><span class="label block">Judul (opsional)</span>
          <input v-model="form.judul" class="input" maxlength="200" :placeholder="`Audit Internal SMKP ${form.tahun}`"></label>
        <label class="block"><span class="label block">Status</span>
          <select v-model="form.status" class="input"><option v-for="(l, k) in status" :key="k" :value="k">{{ l }}</option></select></label>
        <label class="block"><span class="label block">Ketua tim audit</span>
          <input v-model="form.ketua_auditor" class="input" maxlength="150"></label>
        <label class="block"><span class="label block">Tanggal mulai</span><input v-model="form.tanggal_mulai" type="date" class="input"></label>
        <label class="block"><span class="label block">Tanggal selesai</span><input v-model="form.tanggal_selesai" type="date" class="input">
          <span v-if="err.tanggal_selesai" class="galat block">{{ err.tanggal_selesai }}</span></label>
      </div>
      <p class="mt-3 text-[12px] text-slate-500">Jenis menentukan rubrik penilaian dan kalimat kedudukan di dokumen (jasa pertambangan memakai PJO). Nomor temuan memakai inisial nama perusahaannya.</p>
      <div class="mt-4 flex gap-2">
        <button class="btn-utama" :disabled="form.processing"><Ikon nama="simpan" class="w-4 h-4" /> {{ edit ? 'Simpan perubahan' : 'Buat audit' }}</button>
        <button v-if="edit" type="button" class="btn-garis" @click="batalUbah">Batal</button>
      </div>
    </form>

    <div class="kartu overflow-hidden">
      <div v-if="baris.length" class="gulir-x">
        <table class="tabel tabel-hover min-w-[900px]">
          <thead><tr><th>Perusahaan</th><th>Tahun</th><th>Tahap</th><th>Status</th><th>Nilai</th><th>Temuan</th><th class="text-right">Aksi</th></tr></thead>
          <tbody>
            <tr v-for="b in baris" :key="b.id">
              <td><Link :href="`/smkp/${b.id}`" class="font-semibold text-navy-900 hover:text-brand-600">{{ b.perusahaan }}</Link>
                <span class="block text-[12px] text-slate-500">{{ b.labelJenis }}{{ b.ketua ? ` · Ketua tim ${b.ketua}` : '' }}</span></td>
              <td class="tabular-nums">{{ b.tahun }}</td>
              <td class="text-[12.5px] text-slate-600">{{ b.tahap.split('—')[0].trim() }}</td>
              <td class="text-[12.5px]">{{ b.labelStatus }}</td>
              <td><b class="tabular-nums">{{ b.skor !== null ? angka(b.skor, 1) + '%' : '—' }}</b><span class="block text-[11px] text-slate-400">{{ b.tingkat }}</span></td>
              <td class="text-[12.5px]"><b class="tabular-nums">{{ b.terbuka }}</b> <span class="text-slate-400">terbuka / {{ b.temuan }}</span></td>
              <td class="text-right whitespace-nowrap">
                <Link :href="`/smkp/${b.id}`" class="btn-garis btn-kecil">Buka</Link>
                <button type="button" class="btn-hantu btn-kecil" @click="mulaiUbah(b)">Ubah</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <Kosong v-else judul="Belum ada audit perusahaan grup" pesan="Buat audit untuk perusahaan satu grup yang tidak terdaftar sebagai perusahaan." ikon="gedung" />
    </div>
  </div>
</template>

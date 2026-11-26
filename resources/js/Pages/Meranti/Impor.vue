<script setup lang="ts">
/*
  Impor lembar Google Sheets MERANTI.

  Dua jalan masuk: berkas .xlsx hasil unduhan Google Sheets (atau CSV
  dari tombol Export CSV MERANTI), atau tautan lembar yang dibagikan.
  Keduanya aman diulang — baris yang sudah pernah dimuat diperbarui,
  tidak digandakan. Lembar Users tidak pernah dimuat.
*/
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import PilihPerusahaan from '../../Components/Energi/PilihPerusahaan.vue';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
import { n } from '../../energi';

const props = propHalaman();
const { dialog, tanya, batal, lanjut } = useDialog();
const tujuan = computed(() => props.tujuan ?? { id: null, nama: '' });
const form = useForm<{ sumber: string; berkas: File | null; tautan: string }>({ sumber: 'berkas', berkas: null, tautan: '' });
const kotakBerkas = ref<HTMLInputElement | null>(null);

async function jalankan(sumber: 'berkas' | 'tautan') {
  const asal = sumber === 'berkas' ? (form.berkas?.name ?? 'berkas') : 'tautan Google Sheets';
  const ya = await tanya({ judul: `Impor ke ${tujuan.value.nama}?`, pesan: `Data dari ${asal} dimuat ke MERANTI ${tujuan.value.nama}. Baris yang sudah pernah dimuat diperbarui, bukan digandakan; data yang diketik langsung di EQOHSEE tidak disentuh.`, labelAksi: 'Impor sekarang' });
  if (!ya) return;
  form.sumber = sumber;
  form.post('/meranti/impor', { forceFormData: true, preserveScroll: true, onSuccess: () => { form.reset('berkas', 'tautan'); if (kotakBerkas.value) kotakBerkas.value.value = ''; } });
}
</script>

<template>
  <Head title="Impor Google Sheets MERANTI" />
  <div class="space-y-4">
    <section class="flex flex-wrap items-center justify-between gap-3">
      <p class="text-[12.5px] text-stone-600">Tujuan impor: <b class="text-cam-ink">{{ tujuan.nama }}</b></p>
      <PilihPerusahaan :daftar="props.daftarPerusahaan" :terpilih="props.perusahaan" />
    </section>

    <p v-if="!tujuan.id" class="im-peringatan">Pilih perusahaan tujuan lebih dulu — data MERANTI selalu milik satu perusahaan.</p>
    <p v-if="form.errors.sumber || form.errors.berkas || form.errors.tautan" class="im-galat">{{ form.errors.sumber || form.errors.berkas || form.errors.tautan }}</p>

    <Kartu v-if="props.hasil" judul="Hasil impor terakhir" :ket="`${props.hasil.sumber} → ${props.hasil.perusahaan}`" ikon="circle-check" warna="hijau" rapat>
      <div class="px-5 pb-3 flex flex-wrap gap-2 text-[12px]">
        <span class="im-pil im-hijau">{{ props.hasil.baru }} baru</span>
        <span class="im-pil im-biru">{{ props.hasil.diperbarui }} diperbarui</span>
        <span class="im-pil im-abu">{{ props.hasil.dilewati }} dilewati</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-[12.5px] im-tabel">
          <thead><tr><th>Lembar</th><th>Masuk ke</th><th class="text-right">Baru</th><th class="text-right">Diperbarui</th><th class="text-right">Dilewati</th><th>Catatan</th></tr></thead>
          <tbody class="divide-y divide-stone-100">
            <tr v-for="r in props.hasil.lembar" :key="r.lembar">
              <td class="whitespace-nowrap">{{ r.lembar }}</td><td class="font-bold text-cam-ink whitespace-nowrap">{{ r.judul }}</td>
              <td class="text-right num">{{ r.baru }}</td><td class="text-right num">{{ r.diperbarui }}</td><td class="text-right num">{{ r.dilewati }}</td>
              <td class="text-[11.5px] text-stone-500">{{ (r.catatan ?? []).filter(Boolean).join(' ') || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </Kartu>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)] items-start">
      <Kartu judul="Memindahkan data MERANTI" ket="Dari lembar Google Sheets basis data MERANTI" ikon="list-checks" warna="hijau">
        <ol class="im-langkah">
          <li>Buka lembar Google Sheets <b>MERANTI</b> di Google Drive.</li>
          <li><b>File → Download → Microsoft Excel (.xlsx)</b>. Di aplikasi Google Sheets ponsel: <b>⋮ → Bagikan &amp; ekspor → Simpan sebagai → Excel</b>.</li>
          <li>Unggah berkasnya di kartu sebelah, lalu tekan <b>Impor berkas</b>.</li>
          <li>Periksa hasilnya di tabel di atas dan di Dashboard Analitik.</li>
        </ol>
        <p class="mt-3 text-[11.5px] text-stone-500">Setiap lembar ("MERANTI - Stok Bibit", "MERANTI - Peta Spasial", …) masuk ke modulnya sendiri; nama lembar yang terpotong 31 karakter tetap dikenali. Lembar <b>Users</b> tidak dimuat — akun MERANTI lama tidak dipindahkan, beri peran kepada akun EQOHSEE di Pengguna &amp; Peran.</p>
      </Kartu>

      <div class="space-y-4">
        <Kartu judul="Unggah berkas .xlsx atau .csv" ket="Google Sheets: File → Download → Microsoft Excel · atau CSV dari tombol Export CSV MERANTI" ikon="file-spreadsheet" warna="amber">
          <div class="space-y-3">
            <input ref="kotakBerkas" type="file" accept=".xlsx,.csv" class="text-[12px]" aria-label="Berkas xlsx atau csv MERANTI" @input="form.berkas = ($event.target as HTMLInputElement).files?.[0] ?? null">
            <div v-if="form.progress" class="im-progres"><i :style="{ width: `${form.progress.percentage}%` }" /></div>
            <button type="button" class="eq-btn-utama w-full" :disabled="!tujuan.id || !form.berkas || form.processing" @click="jalankan('berkas')">Impor berkas</button>
            <p class="text-[11px] text-stone-500">CSV dikenali dari nama berkasnya (mis. <b>Progres_Mingguan.csv</b>, <b>Target_Blok_(Rencana).csv</b>) — satu modul per berkas, seperti hasil Export CSV di MERANTI versi Supabase.</p>
          </div>
        </Kartu>

        <Kartu judul="Tautan Google Sheets" ket="Lembar harus dibagikan: siapa saja yang memiliki link — Pelihat" ikon="globe" warna="biru">
          <div class="space-y-3">
            <input v-model="form.tautan" type="url" class="im-i" placeholder="https://docs.google.com/spreadsheets/d/…" aria-label="Tautan Google Sheets">
            <button type="button" class="eq-btn-utama w-full" :disabled="!tujuan.id || !form.tautan || form.processing" @click="jalankan('tautan')">Ambil &amp; impor</button>
            <p class="text-[11px] text-stone-500">Bila lembarnya tidak dibagikan, unduh sebagai .xlsx lalu unggah di atas.</p>
          </div>
        </Kartu>
      </div>
    </section>

    <Kartu judul="Isi MERANTI saat ini" :ket="tujuan.nama" ikon="layers" rapat>
      <div class="grid sm:grid-cols-2 xl:grid-cols-3">
        <Link v-for="m in props.isi ?? []" :key="m.kunci" :href="`/meranti/${m.slug}`" class="px-5 py-2.5 flex items-center justify-between gap-3 im-baris">
          <span class="min-w-0"><span class="block text-[12.5px] font-bold text-cam-ink truncate">{{ m.judul }}</span><span class="block text-[11px] text-stone-500">{{ m.grup }}</span></span>
          <span class="text-right"><b class="num">{{ n(m.n, 0) }}</b><span v-if="m.impor" class="block text-[10.5px] text-stone-500 num">{{ m.impor }} dari lembar</span></span>
        </Link>
      </div>
    </Kartu>

    <p class="text-[11.5px] text-stone-500 flex items-center gap-2"><Ikon nama="info" :ukuran="14" />Dari terminal VPS: <code class="im-kode">php artisan meranti:impor /jalur/MERANTI.xlsx --perusahaan="{{ tujuan.nama }}"</code></p>

    <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
  </div>
</template>

<style scoped>
.im-i { width: 100%; border-radius: 10px; border: 1px solid #E7E5E4; font-size: 12.5px; padding: 7px 10px; background: #fff; }
.im-peringatan { padding: 10px 14px; border-radius: 12px; background: #FEF7E6; border: 1px solid #F3D9A0; color: #7A5D0B; font-size: 12.5px; font-weight: 700; }
.im-galat { padding: 10px 14px; border-radius: 12px; background: #FDECEC; border: 1px solid #F8C9C9; color: #991B1B; font-size: 12.5px; font-weight: 700; }
.im-tabel thead th { text-align: left; font-size: 11px; font-weight: 700; color: #78716C; padding: 8px 12px; background: #FAFAF9; white-space: nowrap; }
.im-tabel thead th.text-right { text-align: right; }
.im-tabel td { padding: 7px 12px; vertical-align: top; }
.im-tabel th:first-child, .im-tabel td:first-child { padding-left: 20px; }
.im-pil { padding: 3px 10px; border-radius: 999px; font-weight: 800; }
.im-hijau { background: #E8F5EC; color: #1F6B42; } .im-biru { background: #EAF1FB; color: #1E4E8C; } .im-abu { background: #F1F5F9; color: #475569; }
.im-progres { height: 6px; border-radius: 999px; background: #F1F5F9; overflow: hidden; }
.im-progres i { display: block; height: 100%; background: #2E7D55; }
.im-baris:hover { background: #F7FBF8; }
.im-langkah { list-style: decimal; padding-left: 20px; display: grid; gap: 6px; font-size: 12.5px; color: #44403C; }
.im-kode { padding: 2px 6px; border-radius: 6px; background: #F5F5F4; font-size: 11px; overflow-wrap: anywhere; }
:root[data-tema="gelap"] .im-i { background: #20272B; border-color: #2E373C; color: #D5DDE1; }
:root[data-tema="gelap"] .im-tabel thead th { background: #20272B; color: #93A1A8; }
:root[data-tema="gelap"] .im-baris:hover { background: #20272B; }
:root[data-tema="gelap"] .im-langkah { color: #D5DDE1; }
:root[data-tema="gelap"] .im-kode { background: #20272B; color: #D5DDE1; }
:root[data-tema="gelap"] .im-peringatan { background: #3A2F14; border-color: #6B5520; color: #F3D9A0; }
:root[data-tema="gelap"] .im-galat { background: #4E1D1D; border-color: #7F2F2F; color: #F5A9A9; }
</style>

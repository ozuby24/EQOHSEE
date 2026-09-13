<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import PjpBilahStatus from '../../Components/PjpBilahStatus.vue';
import PjpLencanaStatus from '../../Components/PjpLencanaStatus.vue';
import PjpNav from '../../Components/PjpNav.vue';
import PjpSaring from '../../Components/PjpSaring.vue';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';

const { dialog, tanya, batal, lanjut } = useDialog();

const props = defineProps<{
  judul: string;
  subjudul: string;
  pjps: {
    data: Array<Record<string, any>>;
    links: Array<{ url: string | null; label: string; active: boolean }>;
    total: number;
    from: number | null;
    to: number | null;
  };
  saring: { cari: string; status: string };
  statusJumlah: Record<string, number>;
  statusOpsi: Record<string, string>;
  tautan: Record<string, string>;
}>();

const untuk = (pola: string, id: number) => String(pola).replace('__ID__', String(id));

/* Nama perusahaannya harus diketik ulang sebelum tombolnya hidup.
   Yang terhapus di sini bukan satu baris melainkan seluruh riwayat
   pemantauannya — dokumen yang diunggah berbulan-bulan, 126 jawaban
   daftar periksa, dan evaluasi tiap semester — dan di daftar yang
   barisnya berdempetan, perusahaan yang salah tertekan terlihat persis
   sama dengan yang benar sampai penegasannya menyebut namanya. */
async function hapus(pjp: Record<string, any>) {
  if (!await tanya({
    judul: `Hapus data PJP “${pjp.nama_perusahaan}”?`,
    pesan: 'Seluruh dokumen, checklist, dan evaluasinya ikut terhapus. '
      + 'Tindakan ini tidak dapat dibatalkan.',
    nada: 'bahaya',
    labelAksi: 'Hapus',
    tegasNama: pjp.nama_perusahaan,
  })) return;

  router.delete(untuk(props.tautan.hapusPola, pjp.id), { preserveScroll: true });
}
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1400px] mx-auto space-y-5">
    <!--
      Judul dan tombol menumpuk pada layar sempit. Dipaksa sebaris,
      deretan tombol ini meluber keluar layar di lebar ~390px — tanpa
      galat, hanya teks yang terpotong di tepi kanan.
    -->
    <!-- Judul dan subjudulnya ada di kop kerangka; yang tersisa di sini
         hanya tindakannya. -->
    <div class="flex flex-wrap items-end justify-end gap-4">
      <div class="flex w-full flex-wrap gap-2 sm:w-auto">
        <a :href="props.tautan.ekspor" class="eq-btn-lain px-5 !flex-none">Ekspor CSV</a>
        <Link :href="props.tautan.baru" class="eq-btn-utama px-5 !flex-none">+ Tambah PJP</Link>
      </div>
    </div>

    <PjpNav :tautan="props.tautan" aktif="daftar" />

    <PjpBilahStatus judul="Sebaran Status Seluruh PJP" :jumlah="props.statusJumlah" :opsi="props.statusOpsi" />

    <PjpSaring :aksi="props.tautan.daftar" :awal="props.saring" :status-opsi="props.statusOpsi" />

    <section v-if="props.pjps.data.length" class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[12px]">
          <thead>
            <tr class="border-b border-stone-100 text-stone-400">
              <th class="px-5 py-3">Nama Perusahaan</th>
              <th class="px-5 py-3">NIB</th>
              <th class="px-5 py-3">Penanggung Jawab</th>
              <th class="px-5 py-3">Status</th>
              <th class="px-5 py-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="pjp in props.pjps.data" :key="pjp.id" class="border-b border-stone-50">
              <td class="px-5 py-3 font-semibold text-stone-700">
                {{ pjp.nama_perusahaan }}
                <small v-if="pjp.company" class="block text-[10px] text-stone-400">{{ pjp.company.name }}</small>
              </td>
              <td class="px-5 py-3 text-stone-500">{{ pjp.nib || '—' }}</td>
              <td class="px-5 py-3 text-stone-500">{{ pjp.penanggung_jawab || '—' }}</td>
              <td class="px-5 py-3">
                <PjpLencanaStatus :status="pjp.status" :label="props.statusOpsi[pjp.status] ?? pjp.status" />
              </td>
              <!-- Ketiganya setinggi 24px, batas terendah WCAG 2.2 AA
                   (2.5.8), dan berjarak — bukan kerapian melainkan sebab
                   "Hapus" bertetangga dengan "Detail" dalam satu baris.
                   Terukur sebelum ini: 14px dan 17px berdempetan, dan
                   ketukan yang meleset sejauh tiga piksel membuka
                   penegasan penghapusan perusahaan yang salah. -->
              <td class="px-5 py-3 text-right whitespace-nowrap">
                <div class="inline-flex items-center gap-1">
                  <Link :href="untuk(props.tautan.detail, pjp.id)"
                        class="inline-flex min-h-[24px] items-center rounded px-2 text-[11px] font-bold text-cam-orange">Detail</Link>
                  <Link :href="untuk(props.tautan.checklistUntuk, pjp.id)"
                        class="inline-flex min-h-[24px] items-center rounded px-2 text-[11px] font-bold text-stone-500">Checklist</Link>
                  <button type="button" @click="hapus(pjp)"
                          class="inline-flex min-h-[24px] items-center rounded px-2 text-[11px] font-bold text-red-600">Hapus</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="props.pjps.links.length > 3" class="flex flex-wrap items-center justify-between gap-3 border-t border-stone-100 px-5 py-3">
        <span class="text-[11px] text-stone-400">
          Menampilkan {{ props.pjps.from ?? 0 }}–{{ props.pjps.to ?? 0 }} dari {{ props.pjps.total }} PJP
        </span>
        <div class="flex flex-wrap gap-1">
          <!--
            Label halaman datang dari Laravel dan memuat entitas HTML
            (&laquo;, &raquo;), jadi digambar dengan v-html. Aman: isinya
            dibuat paginator, bukan masukan pengguna.
          -->
          <component
            :is="tautan.url ? Link : 'span'"
            v-for="(tautan, i) in props.pjps.links"
            :key="i"
            :href="tautan.url || undefined"
            class="rounded-lg px-3 py-1.5 text-[11px] font-bold"
            :class="tautan.active
              ? 'bg-cam-ink text-white'
              : tautan.url ? 'bg-white text-stone-500 border border-stone-200' : 'text-stone-300'"
            v-html="tautan.label"
          />
        </div>
      </div>
    </section>

    <p v-else class="rounded-2xl bg-white border border-stone-100 shadow-card p-10 text-center text-[12px] text-stone-400">
      {{ props.saring.cari || props.saring.status
        ? 'Tidak ada PJP yang cocok dengan penyaring.'
        : 'Belum ada data PJP. Tambahkan data untuk mulai memantau dan mengelola PJP.' }}
    </p>

    <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
  </div>
</template>

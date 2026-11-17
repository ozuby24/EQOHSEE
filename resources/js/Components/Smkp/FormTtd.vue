<script setup lang="ts">
/**
 * Baris tanda tangan formulir resmi: Nama · Tanda Tangan · Tanggal.
 * Tim audit dapat mengisi tanggal dan mengunggah tanda tangan digital
 * langsung di halaman cetak; kendali tidak ikut tercetak.
 *
 * Bagian `manual` (tindak lanjut Formulir Ketidaksesuaian): tanda tangan
 * tidak tampil otomatis — pihaknya sendiri menekan "Tandatangani" setelah
 * syaratnya terpenuhi (`syarat` berisi alasannya bila belum).
 */
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Dialog from '../Dialog.vue';
import { useDialog } from '../../dialog';
import type { PihakTtd, TtdForm } from '../../smkp/types';

/* Penegasan memakai dialog EQOHSEE (useDialog), pengganti window.confirm
   Aurorasys: confirm() bawaan peramban dibungkam pada webview ponsel. */
const { dialog, tanya, batal, lanjut } = useDialog();

const props = defineProps<{
  /** `kunci` kosong: baris dicetak dengan kotak tanda tangan kosong, tanpa tanda tangan digital. */
  pihak: Array<{ peran: string; kunci?: PihakTtd | null; nama?: string | null; ket?: string | null }>;
  ttd: TtdForm;
  /** Kunci bagian formulir untuk tanggal; kosong = tanggal tidak dapat diisi. */
  bagian?: string;
  manual?: boolean;
  syarat?: string | null;
  /** Halaman tempat syarat dipenuhi (mis. temuan). */
  tautan?: string;
  /**
   * Tata letak Berita Acara resmi: baris judul pihak, lalu satu baris
   * (Nama) · (Tanda Tangan) · (Tanggal).
   */
  blok?: boolean;
  /** Tata letak Laporan Audit: No · Fungsi · Nama · Tanda Tangan, tanpa tanggal. */
  daftar?: boolean;
}>();

const galat = ref('');
const proses = ref(false);
const opsi = {
  preserveScroll: true, preserveState: true, only: ['ttd'],
  onStart: () => { proses.value = true; galat.value = ''; },
  onError: (e: Record<string, string>) => { galat.value = Object.values(e).filter(Boolean).join(' '); },
  onFinish: () => { proses.value = false; },
};

const tanggal = (k: PihakTtd) => (props.bagian ? props.ttd.tanggal[props.bagian]?.[k] : undefined);
const ditandatangani = (k: PihakTtd) => !!(props.bagian && props.ttd.bubuh[props.bagian]?.[k]);
const tampilGambar = (k: PihakTtd) => !!props.ttd.gambar[k] && (!props.manual || ditandatangani(k));
const bisaTanggal = (k: PihakTtd) => !!props.bagian && (props.manual ? props.ttd.bisaBubuh[k] || props.ttd.bisaSunting : props.ttd.bisaSunting);
// Tanggal yang baru dipilih tetap tampil selama dikirim (render ulang tidak mengosongkannya).
const dipilih = reactive<Partial<Record<PihakTtd, string>>>({});

function simpanTanggal(k: PihakTtd, nilai: string) {
  dipilih[k] = nilai;
  router.post(`${props.ttd.url}/tanggal`, { bagian: props.bagian, pihak: k, tanggal: nilai || null }, {
    ...opsi, onFinish: () => { proses.value = false; delete dipilih[k]; },
  });
}

function unggah(k: PihakTtd, e: Event) {
  const input = e.target as HTMLInputElement;
  const berkas = input.files?.[0];
  input.value = '';
  if (berkas) router.post(`${props.ttd.url}/ttd`, { pihak: k, berkas }, { ...opsi, forceFormData: true });
}

async function bubuh(k: PihakTtd, ttd: boolean, peran: string) {
  if (!ttd && !await tanya({
    judul: `Batalkan tanda tangan ${peran.replace(/^Nama /, '')} pada bagian ini?`,
    pesan: 'Tanggalnya ikut dihapus.',
    labelAksi: 'Batalkan tanda tangan',
    nada: 'bahaya',
  })) return;
  router.post(`${props.ttd.url}/ttd/bubuh`, { bagian: props.bagian, pihak: k, ttd }, opsi);
}

async function hapus(k: PihakTtd, peran: string) {
  if (await tanya({
    judul: `Hapus tanda tangan digital ${peran.replace(/^Nama /, '')}?`,
    pesan: 'Tanda tangan ini dipakai di semua formulir audit ini.',
    labelAksi: 'Hapus',
    nada: 'bahaya',
  })) {
    router.delete(`${props.ttd.url}/ttd/${k}`, opsi);
  }
}
</script>

<template>
  <table class="tabel-form hindari-putus mt-4">
    <thead v-if="daftar"><tr><th class="w-[7%]">No</th><th class="w-[25%]">Fungsi</th><th class="w-[36%]">Nama</th><th>Tanda Tangan</th></tr></thead>
    <tbody>
      <template v-for="(p, i) in pihak" :key="`${i}-${p.peran}`">
      <tr v-if="blok"><td colspan="3" class="text-slate-800">{{ p.peran }}<span v-if="p.ket" class="block text-[10.5px] text-slate-500">({{ p.ket }})</span></td></tr>
      <tr>
        <td v-if="daftar" class="text-center align-middle tabular-nums">{{ i + 1 }}</td>
        <td v-if="!blok" class="text-slate-700 align-middle" :class="{ 'w-[16%]': !daftar }">{{ p.peran }}</td>
        <td v-if="blok" class="w-[40%] h-20 !align-bottom">
          <b class="text-slate-900">{{ p.nama || '……………………………' }}</b><span class="block text-[9.5px] text-slate-500">(Nama)</span>
        </td>
        <td v-else class="font-semibold text-slate-900 align-middle" :class="{ 'w-[24%]': !daftar }">{{ p.nama || '' }}</td>
        <td v-if="!blok && !daftar" class="w-[14%] whitespace-nowrap text-slate-700 align-middle">Tanda Tangan</td>
        <td :class="blok ? 'w-[30%] h-20 !align-bottom text-center' : daftar ? 'h-16 align-middle text-center' : 'w-[20%] h-12 align-middle text-center'">
          <template v-if="p.kunci">
          <img v-if="tampilGambar(p.kunci)" :src="ttd.gambar[p.kunci]!" alt="Tanda tangan" class="max-h-11 max-w-full mx-auto mix-blend-multiply">
          <!-- Bagian biasa: gambar tanda tangan dipakai otomatis. -->
          <div v-if="!manual && ttd.bisaGambar[p.kunci]" class="no-print flex flex-wrap justify-center gap-x-2 text-[10.5px] font-semibold">
            <label class="text-navy-700 hover:underline cursor-pointer" :class="{ 'opacity-50 pointer-events-none': proses }">
              {{ ttd.gambar[p.kunci] ? 'Ganti' : 'Unggah TTD' }}
              <input type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="unggah(p.kunci, $event)">
            </label>
            <button v-if="ttd.gambar[p.kunci]" type="button" class="text-red-600 hover:underline" :disabled="proses" @click="hapus(p.kunci, p.peran)">Hapus</button>
          </div>
          <!-- Bagian tindak lanjut: dibubuhkan sendiri oleh pihaknya setelah syaratnya terpenuhi. -->
          <div v-else-if="manual && bagian" class="no-print text-[10.5px] leading-snug">
            <template v-if="ditandatangani(p.kunci)">
              <button v-if="ttd.bisaBubuh[p.kunci]" type="button" class="font-semibold text-red-600 hover:underline" :disabled="proses" @click="bubuh(p.kunci, false, p.peran)">Batalkan</button>
            </template>
            <span v-else-if="syarat" class="text-slate-400">{{ syarat }}<template v-if="tautan"> <a :href="tautan" class="font-semibold text-navy-700 hover:underline">Buka temuan</a></template></span>
            <template v-else-if="ttd.bisaBubuh[p.kunci]">
              <label v-if="!ttd.gambar[p.kunci]" class="font-semibold text-navy-700 hover:underline cursor-pointer" :class="{ 'opacity-50 pointer-events-none': proses }">
                Unggah TTD
                <input type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="unggah(p.kunci, $event)">
              </label>
              <button v-else type="button" class="btn-utama btn-kecil" :disabled="proses" @click="bubuh(p.kunci, true, p.peran)">Tandatangani</button>
            </template>
            <span v-else class="text-slate-400">Belum ditandatangani</span>
          </div>
          </template>
          <span v-if="blok" class="block text-[9.5px] text-slate-500">(Tanda Tangan)</span>
        </td>
        <td v-if="!blok && !daftar" class="w-[9%] text-slate-700 align-middle">Tanggal</td>
        <td v-if="!daftar" :class="blok ? 'h-20 !align-bottom' : 'align-middle'">
          <template v-if="p.kunci && bisaTanggal(p.kunci)">
            <input type="date" :aria-label="`Tanggal tanda tangan ${p.peran}`" class="no-print w-full border-0 bg-amber-50/60 p-0.5 text-[11px] focus:ring-1 focus:ring-navy-400"
                   :value="dipilih[p.kunci] ?? tanggal(p.kunci)?.nilai ?? ''" :disabled="proses" @change="simpanTanggal(p.kunci, ($event.target as HTMLInputElement).value)">
            <span class="hidden print:inline">{{ tanggal(p.kunci)?.teks ?? '' }}</span>
          </template>
          <template v-else-if="p.kunci">{{ tanggal(p.kunci)?.teks ?? '' }}</template>
          <span v-if="blok" class="block text-[9.5px] text-slate-500">(Tanggal)</span>
        </td>
      </tr>
      </template>
    </tbody>
  </table>
  <p v-if="galat" class="no-print mt-1 text-[11px] text-red-600">{{ galat }}</p>
  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>

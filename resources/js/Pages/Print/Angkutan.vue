<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';

defineOptions({ layout: BlankLayout });

const props = defineProps<{
  dok: any; dari: string; sampai: string;
  ringkas: Record<string, any>;
  kepatuhan: Record<string, any>;
  regu: Array<Record<string, any>>;
  alat: Array<Record<string, any>>;
  tindak: Array<Record<string, any>>;
  kembali?: string;
}>();

const angka = (v: unknown, d = 0) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: d, minimumFractionDigits: d }).format(Number(v || 0));
const tanggal = (v: unknown) => v ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(String(v))) : '—';
const label = (v: string) => String(v || '').replaceAll('-', ' ').replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
const nilai = (v: unknown, d = 2) => v === null || v === undefined ? '—' : angka(v, d);
</script>

<template>
  <Head title="Laporan Pengangkutan dan Pengaturan Armada" />

  <PrintShell title="Laporan Pengangkutan dan Pengaturan Armada" :kembali="props.kembali">
    <div class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0">

      <table class="w-full border-collapse text-[10px] mb-5">
        <tbody><tr>
          <td class="border border-stone-300 p-2 w-[18%] text-center font-bold">{{ props.dok?.perusahaan || 'EQOHSEE' }}</td>
          <td class="border border-stone-300 p-2 text-center">
            <div class="text-[9px] tracking-wide text-stone-500">{{ props.dok?.jenis }}</div>
            <div class="font-bold">{{ props.dok?.judul }}</div>
          </td>
          <td class="border border-stone-300 p-0 w-[30%]">
            <table class="w-full border-collapse"><tbody>
              <tr><td class="border-b border-r border-stone-300 p-1">No. Dokumen</td><td class="border-b border-stone-300 p-1 font-semibold">{{ props.dok?.nomor }}</td></tr>
              <tr><td class="border-b border-r border-stone-300 p-1">Tgl Penerbitan</td><td class="border-b border-stone-300 p-1">{{ tanggal(props.dok?.terbit) }}</td></tr>
              <tr><td class="border-b border-r border-stone-300 p-1">Tgl Persetujuan</td><td class="border-b border-stone-300 p-1">{{ tanggal(props.dok?.setuju) }}</td></tr>
              <tr><td class="border-r border-stone-300 p-1">No. Revisi</td><td class="p-1">{{ props.dok?.revisi }}</td></tr>
            </tbody></table>
          </td>
        </tr></tbody>
      </table>

      <header class="text-center border-b border-stone-200 pb-4 mb-5">
        <h1 class="font-bold text-[16px] uppercase">Laporan Pengangkutan dan Pengaturan Armada</h1>
        <p class="text-[11px] text-stone-500 mt-1">Periode {{ tanggal(props.dari) }} — {{ tanggal(props.sampai) }}</p>
        <p class="text-[10px] text-stone-400 mt-1">{{ props.dok?.divisi }} · {{ props.dok?.departemen }}</p>
      </header>

      <!--
        Dua hal dinyatakan sebelum angka mana pun dibaca: dari mana
        angkanya berasal, dan bahwa tonasenya bukan tambahan bagi laporan
        produksi. Tanpa yang kedua, dua laporan yang benar dapat
        dijumlahkan menjadi satu angka yang salah.
      -->
      <section class="mb-5 rounded border border-stone-300 p-3 text-[10px] print:rounded-none"
               :class="Number(props.ringkas?.menunggu) ? 'bg-amber-50' : ''">
        <b class="block mb-1">Dasar laporan</b>
        Disusun dari <b>{{ props.ringkas?.disetujui ?? 0 }}</b> catatan regu angkut yang telah disetujui,
        dari <b>{{ props.ringkas?.regu ?? 0 }}</b> catatan pada periode ini.
        <template v-if="Number(props.ringkas?.menunggu)">
          <b>{{ props.ringkas.menunggu }}</b> catatan masih menunggu tinjauan dan tidak ikut dihitung.
        </template>
        Tonase pada laporan ini adalah rincian dari tonase pit yang sama pada Laporan Kinerja Operasi
        Penambangan — bukan tambahannya, dan keduanya tidak boleh dijumlahkan.
      </section>

      <h3 class="font-bold text-[13px] mb-2">A. Ringkasan Pengangkutan</h3>
      <table class="w-full border-collapse text-[11px] mb-6"><tbody>
        <tr v-for="b in [
          { k: 'Catatan regu disetujui', v: String(props.ringkas.disetujui ?? 0), s: `Dari ${props.ringkas.regu ?? 0} catatan pada periode` },
          { k: 'Ritase', v: angka(props.ringkas.ritase), s: '' },
          { k: 'Tonase terinci', v: `${angka(props.ringkas.tonase)} ton`, s: 'Bagian dari tonase pit, bukan tambahan' },
          { k: 'Jam kerja armada', v: `${angka(props.ringkas.jamKerja, 1)} jam`, s: `Delay ${angka(props.ringkas.jamDelay, 1)} jam` },
          { k: 'Utilisasi jam terjadwal', v: props.ringkas.utilisasi === null ? '—' : `${angka(props.ringkas.utilisasi, 1)}%`, s: '' },
          { k: 'Match factor rata-rata', v: nilai(props.ringkas.mfRata, 2), s: 'Dihitung dari waktu edar tanpa antre' },
          { k: 'Regu tidak seimbang', v: String(props.ringkas.takSeimbang ?? 0), s: 'Di luar rentang 0,85–1,15' },
          { k: 'Porsi antre rata-rata', v: props.ringkas.antreRata === null ? '—' : `${angka(props.ringkas.antreRata, 1)}%`, s: '' },
          { k: 'Tonase hilang karena antre', v: `${angka(props.ringkas.hilangAntre)} ton`, s: 'Perkiraan dari waktu antre dan muatan rata-rata' },
          { k: 'Regu melampaui batas kecepatan', v: String(props.ringkas.lampauiKecepatan ?? 0), s: 'Hanya rute yang batasnya sudah ditetapkan' },
        ]" :key="b.k" class="border-b border-stone-200">
          <td class="p-2 w-[32%]">{{ b.k }}</td>
          <td class="p-2 font-semibold w-[18%]">{{ b.v }}</td>
          <td class="p-2 text-stone-500">{{ b.s }}</td>
        </tr>
      </tbody></table>

      <h3 class="font-bold text-[13px] mb-2">B. Kepatuhan Muatan 10/10/20</h3>
      <section class="mb-3 rounded border border-stone-300 p-3 text-[10px] print:rounded-none">
        Kaidah pabrikan truk angkut: rata-rata seluruh muatan tidak melebihi 100% kapasitas nominal,
        tidak lebih dari 10% muatan berada di atas 110%, dan tidak satu pun muatan berada di atas 120%.
        Yang ketiga bersifat mutlak justru karena yang pertama tidak — rata-rata yang rapi menyembunyikan
        satu truk bermuatan berlebih yang menuruni jalan angkut dengan retarder di ambangnya.
      </section>
      <table class="w-full border-collapse text-[11px] mb-6"><tbody>
        <tr v-for="b in [
          { k: 'Penimbangan dinilai', v: String(props.kepatuhan.n ?? 0), s: props.kepatuhan.cukupData ? 'Cukup untuk menilai sebaran' : 'Belum cukup untuk menilai sebaran' },
          { k: 'Rata-rata muatan', v: props.kepatuhan.rata === null ? '—' : `${angka(props.kepatuhan.rata, 1)}%`, s: 'Terhadap kapasitas nominal masing-masing unit' },
          { k: 'Muatan di atas 110%', v: `${props.kepatuhan.lebih110 ?? 0} rit`, s: props.kepatuhan.porsi110 === null ? '' : `${angka(props.kepatuhan.porsi110, 1)}% dari seluruh penimbangan` },
          { k: 'Muatan di atas 120%', v: `${props.kepatuhan.lebih120 ?? 0} rit`, s: 'Batas mutlak, berlaku per muatan' },
          { k: 'Muatan tertinggi', v: props.kepatuhan.tertinggi === null ? '—' : `${angka(props.kepatuhan.tertinggi, 1)}%`, s: '' },
          { k: 'Kesimpulan', v: props.kepatuhan.patuh === true ? 'Patuh' : props.kepatuhan.patuh === false ? 'Melanggar' : 'Belum dapat dinilai', s: props.kepatuhan.alasan },
        ]" :key="b.k" class="border-b border-stone-200">
          <td class="p-2 w-[32%]">{{ b.k }}</td>
          <td class="p-2 font-semibold w-[18%]">{{ b.v }}</td>
          <td class="p-2 text-stone-500">{{ b.s }}</td>
        </tr>
      </tbody></table>

      <h3 class="font-bold text-[13px] mb-2">C. Regu Angkut yang Disetujui</h3>
      <table class="w-full border-collapse text-[10px] mb-6">
        <thead class="bg-stone-100">
          <tr>
            <th class="border border-stone-300 p-1.5 text-left">Kode</th>
            <th class="border border-stone-300 p-1.5 text-left">Tanggal</th>
            <th class="border border-stone-300 p-1.5 text-left">Pit</th>
            <th class="border border-stone-300 p-1.5 text-right">Truk</th>
            <th class="border border-stone-300 p-1.5 text-right">Edar (mnt)</th>
            <th class="border border-stone-300 p-1.5 text-right">MF</th>
            <th class="border border-stone-300 p-1.5 text-right">Antre</th>
            <th class="border border-stone-300 p-1.5 text-right">Ritase</th>
            <th class="border border-stone-300 p-1.5 text-right">Tonase</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in props.regu" :key="r.id">
            <td class="border border-stone-300 p-1.5 font-semibold">{{ r.kode }}</td>
            <td class="border border-stone-300 p-1.5">{{ tanggal(r.tanggal) }} · S{{ r.shift }}</td>
            <td class="border border-stone-300 p-1.5">{{ r.pit || '—' }}</td>
            <td class="border border-stone-300 p-1.5 text-right">{{ r.jumlahTruk }}</td>
            <td class="border border-stone-300 p-1.5 text-right">{{ nilai(r.edar?.nyata, 1) }}</td>
            <td class="border border-stone-300 p-1.5 text-right font-semibold">{{ nilai(r.matchFactor, 2) }}</td>
            <td class="border border-stone-300 p-1.5 text-right">{{ r.porsiAntre === null ? '—' : `${angka(r.porsiAntre, 1)}%` }}</td>
            <td class="border border-stone-300 p-1.5 text-right">{{ angka(r.ritase) }}</td>
            <td class="border border-stone-300 p-1.5 text-right">{{ angka(r.tonase) }}</td>
          </tr>
          <tr v-if="!props.regu.length"><td colspan="9" class="border border-stone-300 p-3 text-center text-stone-400">Belum ada catatan regu disetujui.</td></tr>
        </tbody>
      </table>

      <h3 class="font-bold text-[13px] mb-2">D. Armada Terdaftar</h3>
      <table class="w-full border-collapse text-[10px] mb-6">
        <thead class="bg-stone-100">
          <tr>
            <th class="border border-stone-300 p-1.5 text-left">Kode</th>
            <th class="border border-stone-300 p-1.5 text-left">Nama</th>
            <th class="border border-stone-300 p-1.5 text-left">Kelas</th>
            <th class="border border-stone-300 p-1.5 text-left">Tipe</th>
            <th class="border border-stone-300 p-1.5 text-right">Kapasitas</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="a in props.alat" :key="a.id">
            <td class="border border-stone-300 p-1.5 font-semibold">{{ a.kode }}</td>
            <td class="border border-stone-300 p-1.5">{{ a.nama || '—' }}</td>
            <td class="border border-stone-300 p-1.5">{{ label(a.kelas) }}</td>
            <td class="border border-stone-300 p-1.5">{{ a.tipe || '—' }}</td>
            <td class="border border-stone-300 p-1.5 text-right">
              <template v-if="a.kelas === 'truk'">
                {{ a.kapasitasDitetapkan ? `${angka(a.kapasitas, 1)} ton` : '— belum diisi —' }}
              </template>
              <template v-else>{{ a.bucket === null ? '—' : `${angka(a.bucket, 1)} m³` }}</template>
            </td>
          </tr>
          <tr v-if="!props.alat.length"><td colspan="5" class="border border-stone-300 p-3 text-center text-stone-400">Belum ada unit terdaftar.</td></tr>
        </tbody>
      </table>

      <h3 class="font-bold text-[13px] mb-2">E. Tindak Lanjut Terbuka</h3>
      <table class="w-full border-collapse text-[10px] mb-6">
        <thead class="bg-stone-100">
          <tr>
            <th class="border border-stone-300 p-1.5 text-left">Uraian</th>
            <th class="border border-stone-300 p-1.5 text-left">Penanggung jawab</th>
            <th class="border border-stone-300 p-1.5 text-left">Target</th>
            <th class="border border-stone-300 p-1.5 text-left">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in props.tindak" :key="t.id">
            <td class="border border-stone-300 p-1.5">{{ t.judul }}</td>
            <td class="border border-stone-300 p-1.5">{{ t.penanggung_jawab || '—' }}</td>
            <td class="border border-stone-300 p-1.5">{{ tanggal(t.target_selesai) }}</td>
            <td class="border border-stone-300 p-1.5">{{ label(t.status) }}{{ t.terlambat ? ' · terlambat' : '' }}</td>
          </tr>
          <tr v-if="!props.tindak.length"><td colspan="4" class="border border-stone-300 p-3 text-center text-stone-400">Tidak ada tindak lanjut terbuka.</td></tr>
        </tbody>
      </table>

      <section class="mt-8 grid grid-cols-3 gap-6 text-[10px] break-inside-avoid">
        <div v-for="p in ['Disusun oleh', 'Diperiksa oleh', 'Disetujui oleh']" :key="p" class="text-center">
          <p class="mb-14">{{ p }}</p>
          <div class="border-t border-stone-400 pt-1">
            <p class="text-stone-500">{{ p === 'Disusun oleh' ? 'Pengawas Dispatch' : p === 'Diperiksa oleh' ? 'Pengawas Operasi Produksi' : 'Kepala Teknik Tambang' }}</p>
          </div>
        </div>
      </section>
    </div>
  </PrintShell>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';
import KopCetak from '../../Components/KopCetak.vue';
defineOptions({ layout: BlankLayout });
const props = defineProps<{ 
  dok?: Record<string, any> | null;data: any[]; kembali?: string }>();
const warnaRisiko: Record<string, string> = { Tinggi: '#ef4444', Sedang: '#f59e0b', Rendah: '#84cc16' };
const warnaStatus: Record<string, string> = { Open: '#f59e0b', Closed: '#84cc16', 'In Progress': '#0ea5e9' };
const tanggal = (v: unknown) => v ? new Date(String(v)).toLocaleDateString('id-ID') : '—';
</script>
<template>
  <Head title="Register Hazard Report" /><PrintShell title="Register Hazard Report" :kembali="props.kembali">
    <section class="bg-white p-5 print:p-0">
      <KopCetak :dok="props.dok" />
      <header class="flex justify-between items-end border-b-[3px] border-cam-lime-deep pb-3 mb-4"><div class="flex items-center gap-3"><div class="w-8 h-8 rounded-lg lime-gradient text-white grid place-items-center font-black">E</div><div><h1 class="text-lg font-bold">Register Hazard Report</h1><p class="text-[11px] text-stone-500">EQOHSEE · HSE Platform</p></div></div><div class="text-right text-[10px] text-stone-500">Dicetak {{ new Date().toLocaleString('id-ID') }}<br>Total <b>{{ props.data.length }}</b> laporan</div></header>
      <!-- Lebar kolom ditetapkan, bukan dibiarkan ditawar isinya.

           Tanpa colgroup, tabel sembilan kolom ini membagi ruang menurut
           teks terpanjang tiap kolom: Lokasi dan Ditujukan yang berisi
           nama panjang merebut ruang dari Deskripsi, yang justru satu-
           satunya kolom bercerita. Hasilnya uraian temuan pecah menjadi
           lima baris sementara kolom di sebelahnya separuh kosong, dan
           tiap baris setinggi lima baris teks membuat register 45 laporan
           menjadi dokumen belasan halaman. -->
      <table class="eq-reg-tabel">
        <colgroup>
          <!-- Jumlahnya tepat 100, dan persentasenya dihitung terhadap
               LEBAR KERTAS, bukan lebar layar.

               A4 tegak dikurangi tepian adalah sekitar 718px, sementara
               halaman ini di layar selebar 810px. Selisih 92px itu cukup
               membuat kolom yang lolos diperiksa di peramban tetap pecah
               di atas kertas — dan kertaslah satu-satunya tempat halaman
               ini dibaca. Ketiga angka di bawah diturunkan dari lebar
               yang benar-benar dibutuhkan isinya pada 718px: Kode 13% supaya FRM/ABG/OHSE/101 muat
               satu baris — pada 11% ia pecah menjadi "FRM/ABG/O" dan
               "HSE/101", yaitu nomor dokumen yang terbelah di tengah.
               Tanggal 10% supaya "17/9/2026" yang tidak boleh membungkus
               tidak meluber menempel ke kolom Lokasi di sebelahnya.
               Status 9%, bukan 7%: lencana "In Progress" yang tidak
               boleh terbelah selebar 70px, sementara 7% hanya 55px. -->
          <col style="width:16%"><col style="width:10%"><col style="width:8%">
          <col style="width:8%"><col style="width:9%"><col style="width:18%">
          <col style="width:9%"><col style="width:10%"><col style="width:12%">
        </colgroup>

        <thead>
          <tr>
            <th>Kode</th><th>Tanggal</th><th>Lokasi</th><th>Risiko</th><th>Kategori</th>
            <th>Deskripsi</th><th>Pelapor</th><th>Ditujukan</th><th>Status</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="h in props.data" :key="h.id">
            <td class="font-bold">{{ h.kode }}</td>
            <td class="eq-reg-nowrap">{{ tanggal(h.tanggal) }}</td>
            <td>{{ h.lokasi || '—' }}</td>
            <td>
              <span class="eq-reg-pil" :style="{ background: warnaRisiko[h.risiko] || '#a8a29e' }">{{ h.risiko }}</span>
            </td>
            <td>{{ h.kategori || '—' }}</td>
            <td>
              {{ h.deskripsi }}
              <small v-if="h.unsafe_action_list?.length" class="block text-stone-500">UA: {{ h.unsafe_action_list.join(', ') }}</small>
              <small v-if="h.unsafe_condition_list?.length" class="block text-stone-500">UC: {{ h.unsafe_condition_list.join(', ') }}</small>
            </td>
            <td>{{ h.pelapor_nama }}<small class="block text-stone-500">{{ h.pelapor_jabatan }}</small></td>
            <td>{{ h.company?.name || h.terlapor || '—' }}</td>
            <td>
              <span class="eq-reg-pil" :style="{ background: warnaStatus[h.status] || '#a8a29e' }">{{ h.status }}</span>
            </td>
          </tr>

          <tr v-if="!props.data.length"><td colspan="9" class="p-8 text-center text-stone-400">Tidak ada data.</td></tr>
        </tbody>
      </table>
    </section>
  </PrintShell>
</template>

<style scoped>
.eq-reg-tabel {
  width: 100%;

  /* Tetap, supaya colgroup di atas benar-benar berlaku. Dengan
     `auto`, lebar yang ditulis hanya jadi usulan dan kolom berisi nama
     panjang tetap melebar melampauinya. */
  table-layout: fixed;
  border-collapse: collapse;
  font-size: 11px;
}

.eq-reg-tabel thead th {
  background: #F5F5F4;
  color: #78716C;
  font-size: 9px;
  text-transform: uppercase;
  letter-spacing: .03em;
  text-align: left;
  padding: .4rem .45rem;
}

.eq-reg-tabel tbody td {
  padding: .4rem .45rem;
  border-bottom: 1px solid #F5F5F4;
  vertical-align: top;
  overflow-wrap: break-word;
}

.eq-reg-nowrap { white-space: nowrap; }

/* Nomor dokumen muat satu baris.

   Kode terpanjang di sini — FRM/ABG/OHSE/101 — selebar 100px pada 11px,
   sementara kolomnya 13% dari lebar cetak A4, yaitu 99px. Selisih satu
   piksel itu cukup memecahnya menjadi "FRM/ABG/OH" dan "SE/101": nomor
   dokumen yang terbelah di tengah, yang dibaca orang sebagai dua hal.
   Pada lebar kertas yang sebenarnya, kolomnya perlu 16%.

   Hurufnya dikecilkan DAN kolomnya dilebarkan ke 15%. Mengecilkan huruf
   saja menyisakan selisih empat piksel — cukup untuk tetap pecah, dan
   terlalu tipis untuk terlihat saat diperiksa sekilas. Ruangnya diambil
   dari kolom Pelapor, yang isinya nama orang dan memang boleh turun
   baris. */
.eq-reg-tabel tbody td:first-child { font-size: 10px; letter-spacing: -.01em; }

/* Lencana tidak boleh terbelah. "In Progress" yang membungkus menjadi
   "In" di atas "Progress" tergambar sebagai dua lencana bertumpuk, dan
   pada kolom selebar tujuh persen itulah yang terjadi tanpa aturan
   ini. */
.eq-reg-pil {
  display: inline-block;
  white-space: nowrap;
  border-radius: 99px;
  padding: .1rem .4rem;
  color: #fff;
  font-size: 9px;
  font-weight: 700;
}

@media print {
  /* Judul kolom diulang tiap halaman: register 45 laporan pasti
     melewati satu halaman, dan halaman kedua tanpa judul kolom adalah
     sembilan kolom yang harus ditebak. */
  .eq-reg-tabel thead { display: table-header-group; }
  .eq-reg-tabel tr { break-inside: avoid; page-break-inside: avoid; }
}
</style>

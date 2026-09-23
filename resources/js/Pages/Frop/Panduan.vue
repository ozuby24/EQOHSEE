<script setup lang="ts">
/**
 * Panduan & acuan FROP — materi belajar observer dan GL.
 *
 * Seluruh angka di halaman ini dikirim server dari konstanta yang
 * dipakai penilaiannya sendiri (App\Support\Frop\Penilaian). Ditulis
 * ulang di sini, acuan yang dibaca orang dan aturan yang menilai
 * mereka akan berselisih pada revisi pertama.
 */
import { Head } from '@inertiajs/vue3';
import type { TautanFrop } from './tipe';
import { KATEGORI_WARNA } from './tipe';
import { detik, persen } from './fmt';
import './frop.css';

defineProps<{
  judul: string;
  plan: Array<{ kunci: string; label: string; contoh: string; plan: number; komponen: Record<string, number> }>;
  komponen: Array<{ kunci: string; label: string; ket: string; rasio: number }>;
  ambang: { loading: string; passing: number; spotting: number; digging: number; spot_rata: number; pty: number; pty_cukup: number };
  kpi: { sesi_mingguan: number; on_target: number; pty: number; ca_closed: number; berulang: number; operator: number };
  kategori: Array<{ kunci: string; nama: string }>;
  tautan: TautanFrop;
}>();
</script>

<template>
  <Head :title="judul" />

  <div class="space-y-4">
    <section class="fr-kartu fr-isi text-[12.5px] leading-relaxed text-stone-700">
      <h3 class="text-[14px] font-extrabold text-stone-900">Apa itu observasi FROP</h3>
      <p class="mt-1">
        FROP (Field Reliability &amp; Operator Performance) adalah program pembinaan operator excavator di lapangan.
        Observer mengamati satu operator selama satu jam kerja, mengukur komponen cycle time dengan stopwatch,
        mencatat kondisi front, lalu membandingkan hasilnya dengan Plan CT material yang sedang digali. Hasilnya
        dibahas langsung dengan operator, dan temuan yang menghambat ditindaklanjuti sebagai corrective action.
      </p>
      <ol class="list-decimal pl-5 mt-2 space-y-1">
        <li>Catat identitas: tanggal, shift, jam, unit, operator, GL front, observer.</li>
        <li>Tentukan <b>jenis material</b> — ini yang menentukan Plan CT.</li>
        <li>Ukur beberapa siklus, isi rata-rata tiap komponen dalam detik.</li>
        <li>Ukur loading time satu hauler, hitung jumlah passing, amati bucket heap.</li>
        <li>Minta aktual PTY jam itu dari tim Engineering.</li>
        <li>Tulis temuan (pisahkan dengan titik koma) dan corrective action; bahas dengan operator.</li>
        <li>Bila perlu, catat coaching dari halaman rincian sesi.</li>
      </ol>
    </section>

    <div class="fr-kisi fr-kisi-2">
      <section class="fr-kartu overflow-hidden">
        <div class="fr-kartu-kepala"><h3>Komponen Cycle Time</h3><span class="fr-ket">Aktual CT = Digging + SWL + Dump + SWE</span></div>
        <div class="fr-gulir">
          <table class="fr-tabel">
            <thead><tr><th>Komponen</th><th>Arti</th><th class="kanan">Porsi plan</th></tr></thead>
            <tbody>
              <tr><td><b>Spotting</b></td><td>Waktu hauler bermanuver ke posisi muat. <b>Tidak</b> masuk CT — dinilai terpisah,
                tinggi bila &gt; {{ ambang.spotting }} dtk.</td><td class="kanan fr-redup">–</td></tr>
              <tr v-for="k in komponen" :key="k.kunci">
                <td><b>{{ k.label }}</b></td><td>{{ k.ket }}</td>
                <td class="kanan num">{{ k.rasio }}/{{ komponen.reduce((a, b) => a + b.rasio, 0) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="fr-kartu overflow-hidden">
        <div class="fr-kartu-kepala"><h3>Plan CT per Jenis Material</h3><span class="fr-ket">ON TARGET bila Aktual CT ≤ Plan</span></div>
        <div class="fr-gulir">
          <table class="fr-tabel">
            <thead><tr><th>Material</th><th class="kanan">Plan</th><th class="kanan">Dig</th><th class="kanan">SWL</th>
                       <th class="kanan">Dump</th><th class="kanan">SWE</th></tr></thead>
            <tbody>
              <tr v-for="p in plan" :key="p.kunci">
                <td><b>{{ p.label }}</b><span class="block fr-ket" style="max-width: 18rem">{{ p.contoh }}</span></td>
                <td class="kanan num"><b>{{ p.plan }}</b></td>
                <td class="kanan num">{{ detik(p.komponen.digging, 1) }}</td>
                <td class="kanan num">{{ detik(p.komponen.swl, 1) }}</td>
                <td class="kanan num">{{ detik(p.komponen.dump, 1) }}</td>
                <td class="kanan num">{{ detik(p.komponen.swe, 1) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <div class="fr-kisi fr-kisi-2">
      <section class="fr-kartu">
        <div class="fr-kartu-kepala"><h3>Kesimpulan Hasil Observasi</h3><span class="fr-ket">diperiksa berurutan, yang pertama cocok menang</span></div>
        <ol class="fr-isi list-decimal pl-9 space-y-1.5 text-[12px]">
          <li><b>CT lewat plan</b> → "CT belum tercapai – fokus perbaikan digging/spotting".</li>
          <li><b>CT tercapai, PTY belum diisi</b> → produksi belum dapat dinilai.</li>
          <li><b>PTY ≥ {{ persen(ambang.pty) }}</b> → "CT &amp; Produksi tercapai – pertahankan".</li>
          <li>PTY &lt; {{ persen(ambang.pty) }} dan loading &gt; {{ ambang.loading }}, passing &gt; {{ ambang.passing }}, bucket tidak heap → ketiganya disebut.</li>
          <li>Loading time &gt; {{ ambang.loading }} per hauler.</li>
          <li>N passing &gt; {{ ambang.passing }} (rentang "5-6" dibaca 6).</li>
          <li>Bucket tidak heap.</li>
          <li>Selain itu → cek faktor lain (cuaca, jarak hauling).</li>
        </ol>
      </section>

      <section class="fr-kartu">
        <div class="fr-kartu-kepala"><h3>Rekomendasi Coaching &amp; Status Sesi</h3></div>
        <div class="fr-isi text-[12px] space-y-2">
          <p><b>CT ≤ plan</b> → Pertahankan Performa. <b>CT &gt; plan</b> dan digging &gt; {{ ambang.digging }} dtk →
            Teknik Digging; spotting &gt; {{ ambang.spotting }} dtk → Spotting / hauler rotation; selain itu → cek metode &amp; posisi track.</p>
          <table class="fr-tabel mt-2">
            <thead><tr><th>Status sesi</th><th>Syarat</th></tr></thead>
            <tbody>
              <tr><td><span class="fr-lencana fr-baik">EXCELLENT</span></td><td>ON TARGET dan PTY ≥ {{ persen(ambang.pty) }}</td></tr>
              <tr><td><span class="fr-lencana fr-baik">GOOD</span></td><td>ON TARGET dan PTY ≥ {{ persen(ambang.pty_cukup) }}</td></tr>
              <tr><td><span class="fr-lencana fr-ingat">CT OK – PTY LOW</span></td><td>ON TARGET, PTY di bawahnya</td></tr>
              <tr><td><span class="fr-lencana fr-ingat">GOOD – PTY OK</span></td><td>OVER TARGET tetapi PTY ≥ {{ persen(ambang.pty) }}</td></tr>
              <tr><td><span class="fr-lencana fr-gawat">CRITICAL</span></td><td>OVER TARGET dan PTY di bawah {{ persen(ambang.pty) }}</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <div class="fr-kisi fr-kisi-2">
      <section class="fr-kartu">
        <div class="fr-kartu-kepala"><h3>Performa Operator &amp; KPI Program</h3></div>
        <div class="fr-isi text-[12px] space-y-2">
          <p><b>Top Performer</b>: ≥ 80% sesi on target dan PTY rata-rata ≥ 80% · <b>Perlu Coaching</b>: &lt; 50% sesi on target
            atau PTY rata-rata &lt; 60% · selain itu <b>Average</b>.
            Tren = CT sesi terakhir − CT sesi pertama (negatif = membaik). Spotting rata-rata &gt; {{ ambang.spot_rata }} dtk ditandai.</p>
          <ul class="list-disc pl-5 space-y-1">
            <li>Sesi observasi ≥ {{ kpi.sesi_mingguan }} per pekan</li>
            <li>Sesi ON TARGET ≥ {{ persen(kpi.on_target) }}</li>
            <li>Pencapaian PTY rata-rata ≥ {{ persen(kpi.pty) }}</li>
            <li>Temuan dengan CA Closed ≥ {{ persen(kpi.ca_closed) }}</li>
            <li>Masalah berulang ≤ {{ persen(kpi.berulang) }}</li>
            <li>Operator terobservasi ≥ {{ kpi.operator }} per bulan</li>
          </ul>
        </div>
      </section>

      <section class="fr-kartu">
        <div class="fr-kartu-kepala"><h3>Kategori Temuan</h3><span class="fr-ket">dikenali otomatis dari kata kunci tiap butir</span></div>
        <div class="fr-isi flex flex-wrap gap-2">
          <span v-for="k in kategori" :key="k.kunci" class="fr-kat" :style="{ background: KATEGORI_WARNA[k.kunci] }">{{ k.nama }}</span>
          <p class="fr-ket w-full mt-1">Masalah disebut <b>berulang</b> bila kategori yang sama pernah tercatat pada unit yang sama
            sebelumnya.</p>
        </div>
      </section>
    </div>

    <section class="fr-kartu">
      <div class="fr-kartu-kepala"><h3>Perbedaan dari Berkas Excel Asal</h3>
        <span class="fr-ket">rumus berkas kerja yang dikoreksi — diukur pada 101 baris observasinya</span></div>
      <ol class="fr-isi list-decimal pl-9 space-y-1.5 text-[12px] text-stone-700">
        <li>Rekomendasi coaching dibandingkan dengan Plan CT material sesi itu, bukan angka tetap 22 detik
          (di berkas asal 52 sesi berstatus ON TARGET sekaligus disarankan coaching).</li>
        <li>"CT vs sesi sebelumnya" dibandingkan dengan sesi sebelumnya dari <b>operator yang sama</b>, bukan baris di atasnya.</li>
        <li>Tren operator = CT sesi terakhir − CT sesi pertama; rumus asal (terbaik − terburuk) selalu menyatakan "membaik".</li>
        <li>% ON TARGET dibagi dengan sesi yang benar-benar ada (asal: 30,2%; seharusnya 88,1%).</li>
        <li>Status rata-rata CT dinilai dari rata-rata selisih terhadap plan masing-masing sesi, bukan dari 22 detik.</li>
        <li>Sesi tanpa data PTY tidak lagi dianggap "produksi tercapai".</li>
        <li>Tanda "berulang" dihitung dari kategori yang benar-benar muncul lagi pada unit yang sama; di berkas asal seluruh temuan
          bertanda berulang.</li>
        <li>Status sesi dinilai dari CT sesi itu sendiri, bukan CT terbaik operator dari sesi lain.</li>
        <li>N passing berupa teks ("5") tidak lagi dianggap melebihi standar 5.</li>
        <li>Label performer memakai ambang PTY yang sama dengan catatan coaching-nya, sehingga "Top Performer" tidak lagi
          bersanding dengan catatan "Perlu Coaching – PTY rendah".</li>
      </ol>
    </section>
  </div>
</template>

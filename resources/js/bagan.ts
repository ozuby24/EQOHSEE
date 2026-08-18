/*
  Chart.js dan setelan temanya, dibundel — bukan diambil dari CDN.

  Sebelumnya pustaka ini dimuat lewat <script src="https://cdn.jsdelivr.net/…">
  dari partial Blade yang disisipkan ke SETIAP halaman Inertia. Tiga hal
  salah sekaligus di situ, dan yang paling penting bukan yang paling
  menakutkan:

  1. Di jaringan tambang yang tertutup — tempat aplikasi ini justru
     dipakai — skripnya gagal dimuat dan grafiknya kosong tanpa satu pun
     penjelasan. Ini bukan dugaan: satu halaman sudah pernah ditulis
     ulang menjadi SVG karena persis itu (lihat Pages/Admin/Sistem.vue),
     dan alasannya tercatat di sana sejak sebelum berkas ini ada.

  2. Kode pihak ketiga berjalan dengan akses penuh ke halaman, tanpa
     Subresource Integrity, pada tiap halaman — termasuk halaman
     administrator. Yang menguasai alamat itu menguasai sesi siapa pun
     yang membukanya.

  3. Ia menuntut Content-Security-Policy melonggarkan `script-src` untuk
     asal luar, yang membuang sebagian besar gunanya.

  Ketiganya hilang begitu pustakanya ikut dibundel. Ukurannya bertambah,
  dan itu harga yang murah: grafik yang tidak pernah muncul di jaringan
  tertutup bukan grafik yang lambat, ia grafik yang tidak ada.
*/
import Chart from 'chart.js/auto';

let sudahDitema = false;

function pasangTema(): void {
  if (sudahDitema) return;

  Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
  Chart.defaults.font.size = 11;
  Chart.defaults.color = '#78716c';
  /* Tipenya membolehkan `animation` bernilai false, jadi menulis
     `.duration` langsung tidak sah menurut TypeScript meski selalu bekerja
     saat dijalankan. Ditulis sebagai objek utuh — lebih jujur pada
     tipenya, dan tetap satu setelan. */
  Chart.defaults.animation = { duration: 700 };
  Chart.defaults.plugins.legend.labels.boxWidth = 10;
  Chart.defaults.plugins.legend.labels.boxHeight = 10;
  Chart.defaults.plugins.legend.labels.usePointStyle = true;
  Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(27,32,36,.94)';
  Chart.defaults.plugins.tooltip.padding = 10;
  Chart.defaults.plugins.tooltip.cornerRadius = 8;
  Chart.defaults.plugins.tooltip.titleFont = { weight: 'bold' };

  sudahDitema = true;
}

declare global {
  interface Window {
    Chart: typeof Chart;
    eqChartSiap: (cb: () => void) => void;
    eqWarnaLevel: string[];
  }
}

/*
  Dimuat saat dibutuhkan, bukan di muka.

  Berkas ini pernah diimpor langsung oleh inertia.ts, yang menaruh
  Chart.js di dalam bundel masuk — bundel yang diunduh SETIAP halaman
  Inertia sebelum layar pertamanya muncul. Dua dari 147 halaman memakai
  Chart.js. Seratus empat puluh lima sisanya mengunduh 68 kB (terkempa)
  yang tidak pernah mereka sentuh.

  Alasannya sama dengan alasan halaman-halamannya dipecah: aplikasi ini
  dipakai di site tambang, di ujung sambungan yang lambat. Yang dihemat
  bukan ruang cakram melainkan waktu sebelum sesuatu muncul di layar.

  Sekarang inertia.ts hanya memasang `window.eqChartSiap`, dan berkas ini
  baru ditarik ketika salah satu dari dua halaman itu benar-benar
  memanggilnya. Efek sampingnya — tema, `window.Chart`, `eqWarnaLevel` —
  berjalan sekali saat modulnya tiba.

  Yang TIDAK berubah: pustakanya tetap ikut terbundel, tetap satu asal
  dengan aplikasinya, dan tetap tidak menuntut `script-src` dilonggarkan.
  Ketiga alasan di atas berdiri utuh; hanya waktu pengunduhannya yang
  bergeser dari "selalu, di muka" menjadi "ketika dipakai".
*/
window.Chart = Chart;
window.eqWarnaLevel = ['#E5484D', '#F5760A', '#C7DE30', '#1EE699', '#47CEFF'];
pasangTema();

export { Chart, pasangTema };

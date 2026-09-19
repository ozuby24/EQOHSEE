@extends('hukum.tata', [
  'lang'    => 'id',
  'judul'   => 'Kebijakan Privasi',
  'tanggal' => 'Berlaku sejak 16 September 2026',
  'ringkas' => 'Bagaimana EQOHSEE mengumpulkan, memakai, dan melindungi data pada aplikasi HSE dan K3 pertambangan.',
  'alih'    => 'English version: <a href="/privacy-policy">Privacy Policy</a>',
  'kaki'    => 'Aplikasi pengelolaan HSE dan K3 pertambangan',
])

@section('isi')

<p>Kebijakan ini menjelaskan bagaimana <strong>EQOHSEE</strong> — aplikasi
web di <code>eqohsee.id</code> dan aplikasi Android <code>id.eqohsee.eqohsee</code>
— mengumpulkan, memakai, menyimpan, dan melindungi data pribadi.</p>

<div class="kotak">
  <p><strong>Siapa yang bertanggung jawab atas data Anda</strong></p>
  <p>EQOHSEE dipakai oleh perusahaan pertambangan untuk mengelola data
  pekerjanya sendiri. Dalam hampir semua hal, <strong>perusahaan tempat Anda
  bekerja adalah Pengendali Data</strong> — merekalah yang menentukan data apa
  yang dikumpulkan dan berapa lama disimpan. EQOHSEE bertindak sebagai
  <strong>Prosesor Data</strong>: kami mengolah data itu atas perintah mereka,
  bukan untuk kepentingan kami sendiri.</p>
  <p>Artinya, bila Anda pekerja dan ingin melihat, memperbaiki, atau menghapus
  data Anda, permintaan itu <strong>ditujukan kepada perusahaan Anda</strong>
  (biasanya lewat HRD atau Departemen HSE), bukan kepada kami. Kami tidak
  berwenang menghapus catatan milik sebuah perusahaan atas permintaan
  perorangan.</p>
</div>

<h2>1. Data yang dikumpulkan</h2>

<p>Yang berikut ini adalah daftar sebenarnya, disusun dari apa yang benar-benar
disimpan aplikasi — bukan daftar umum.</p>

<table>
  <tr><th>Jenis</th><th>Isinya</th><th>Dari mana</th></tr>
  <tr>
    <td>Akun</td>
    <td>Nama, alamat surel, kata sandi terenkripsi, jabatan, perusahaan, peran akses</td>
    <td>Diisi saat akun dibuat</td>
  </tr>
  <tr>
    <td>Data pekerja</td>
    <td>Nama, NIK, nomor induk, jabatan, kompetensi, masa berlaku sertifikat dan izin</td>
    <td>Dimasukkan HRD/HSE perusahaan</td>
  </tr>
  <tr>
    <td><strong>Lokasi presisi</strong></td>
    <td>Koordinat lintang dan bujur setiap pindaian absensi, jarak ke area kerja,
        dan apakah pindaian terjadi di dalam area</td>
    <td>Mesin absensi di lapangan</td>
  </tr>
  <tr>
    <td><strong>Foto orang</strong></td>
    <td>Swafoto saat absen masuk atau keluar</td>
    <td>Mesin absensi atau aplikasi</td>
  </tr>
  <tr>
    <td><strong>Penanda biometrik</strong></td>
    <td>Nomor rujukan pendaftaran sidik jari pada mesin absensi.
        <em>Templat sidik jarinya sendiri tidak disimpan di EQOHSEE</em> —
        ia tinggal di dalam mesin.</td>
    <td>Mesin absensi</td>
  </tr>
  <tr>
    <td><strong>Data kesehatan</strong></td>
    <td>Pengajuan dan status pemeriksaan kesehatan (MCU), beserta dokumen
        yang dilampirkan</td>
    <td>Diunggah perusahaan atau klinik</td>
  </tr>
  <tr>
    <td>Kehadiran dan jam kerja</td>
    <td>Jadwal roster, jam masuk dan keluar, keterlambatan, cuti, lembur,
        dan perhitungan gaji yang mengikutinya</td>
    <td>Mesin absensi dan entri HRD</td>
  </tr>
  <tr>
    <td>Dokumen unggahan</td>
    <td>Laporan bahaya, hasil inspeksi, bukti audit, izin kerja, prosedur,
        beserta foto yang dilampirkan</td>
    <td>Diunggah pemakai</td>
  </tr>
  <tr>
    <td>Catatan teknis</td>
    <td>Alamat IP, jenis peramban atau perangkat, waktu masuk, dan jejak
        tindakan pada data penting</td>
    <td>Tercatat otomatis</td>
  </tr>
</table>

<div class="kotak">
  <p><strong>Data pribadi yang bersifat spesifik</strong></p>
  <p>Menurut Undang-Undang Nomor 27 Tahun 2022 tentang Pelindungan Data
  Pribadi, <strong>data kesehatan dan data biometrik termasuk data pribadi
  yang bersifat spesifik</strong> dan menuntut perlindungan lebih ketat
  daripada data biasa. Di EQOHSEE, keduanya hanya dapat dibuka oleh pemegang
  peran yang memang membutuhkannya, dan setiap pembukaannya tercatat.</p>
</div>

<h2>2. Untuk apa data dipakai</h2>

<ul>
  <li>Membuat dan mengelola akun serta hak aksesnya</li>
  <li>Menjalankan modul yang dipakai perusahaan: laporan bahaya, inspeksi,
      audit SMKP, izin kerja, roster, absensi, cuti, lembur, dan penggajian</li>
  <li>Memastikan pekerja memenuhi syarat sebelum masuk area kerja —
      kompetensi berlaku, MCU berlaku, izin lengkap</li>
  <li>Memverifikasi kehadiran di lokasi yang benar pada waktu yang benar</li>
  <li>Memenuhi kewajiban pelaporan kepada instansi yang berwenang</li>
  <li>Menjaga keamanan sistem dan menelusuri penyalahgunaan</li>
  <li>Memperbaiki gangguan teknis</li>
</ul>

<p><strong>Kami tidak menjual data pribadi, dan tidak memakainya untuk iklan
atau pemasaran pihak mana pun.</strong></p>

<h2>3. Dasar pemrosesan</h2>

<p>Pemrosesan dilakukan atas dasar pelaksanaan kewajiban hukum di bidang
keselamatan pertambangan, pelaksanaan hubungan kerja antara pekerja dan
perusahaannya, dan — untuk data yang bersifat spesifik seperti kesehatan dan
biometrik — <strong>persetujuan yang wajib diperoleh perusahaan dari pekerja
yang bersangkutan</strong>.</p>

<h2>4. Pemantauan lokasi dan swafoto saat absen</h2>

<p>Kami menyebutkannya terpisah karena ini yang paling sering tidak disadari
orang yang datanya dicatat.</p>

<p>Setiap kali seorang pekerja melakukan absensi melalui mesin di lapangan,
EQOHSEE menyimpan titik koordinat kejadian itu, jarak ke batas area kerja,
dan — bila mesinnya mengambilnya — sebuah swafoto. Tujuannya satu: memastikan
absensi memang dilakukan orang yang bersangkutan di lokasi kerjanya, bukan
dititipkan.</p>

<p>Perekaman ini terjadi <strong>pada saat absensi saja</strong>. EQOHSEE tidak
melacak posisi siapa pun secara terus-menerus, dan aplikasi Android-nya tidak
meminta izin lokasi sama sekali.</p>

<h2>5. Penyimpanan dan keamanan</h2>

<ul>
  <li>Seluruh lalu lintas berjalan melalui HTTPS</li>
  <li>Kata sandi disimpan dalam bentuk hash, tidak pernah sebagai teks biasa</li>
  <li>Data tiap perusahaan dipisahkan satu sama lain pada tingkat basis data;
      satu perusahaan tidak dapat membaca data perusahaan lain</li>
  <li>Akses dibatasi menurut peran, dan tindakan pada data penting dicatat</li>
  <li>Tersedia verifikasi dua langkah untuk akun</li>
  <li>Data disimpan pada server di Indonesia</li>
</ul>

<p>Meski begitu, tidak ada sistem elektronik yang dapat dijamin aman
sepenuhnya.</p>

<h2>6. Pembagian data</h2>

<p>Data dibagikan hanya kepada:</p>

<ul>
  <li><strong>Perusahaan pemilik data</strong> dan pemakai yang diberinya hak akses</li>
  <li><strong>Penyedia infrastruktur</strong> yang menjalankan server, penyimpanan,
      dan pengiriman surel — sebatas yang diperlukan untuk menjalankan layanan</li>
  <li><strong>Instansi berwenang</strong>, bila diwajibkan peraturan
      perundang-undangan atau permintaan resmi yang sah</li>
</ul>

<p>Kami tidak membagikan data kepada pihak lain untuk kepentingan komersial.</p>

<h2>7. Berapa lama disimpan</h2>

<p>Lamanya ditentukan perusahaan pemilik data dan oleh peraturan yang berlaku.
Sebagian catatan keselamatan dan ketenagakerjaan wajib disimpan bertahun-tahun
setelah hubungan kerja berakhir, sehingga tidak dapat dihapus atas permintaan
perorangan.</p>

<h2>8. Hak Anda</h2>

<p>Berdasarkan UU No. 27 Tahun 2022, Anda berhak meminta penjelasan atas data
yang diproses, memperoleh salinannya, memperbaiki yang keliru, menarik
persetujuan, mengajukan keberatan, dan meminta penghapusan sejauh tidak
bertentangan dengan kewajiban penyimpanan.</p>

<p><strong>Cara menggunakannya:</strong></p>

<ul>
  <li><strong>Bila Anda pekerja</strong> — hubungi HRD atau Departemen HSE
      perusahaan Anda. Merekalah Pengendali Data dan merekalah yang berwenang
      memutuskan.</li>
  <li><strong>Bila Anda pemilik akun</strong> yang mendaftar sendiri —
      hubungi kami di alamat pada bagian 11.</li>
</ul>

<h2>9. Penghapusan akun</h2>

<p>Permintaan penghapusan akun beserta data pribadi yang melekat padanya dapat
diajukan melalui alamat pada bagian 11, atau melalui administrator perusahaan
Anda. Permintaan diproses dalam waktu wajar setelah identitas pemohon
dipastikan.</p>

<p>Catatan yang wajib disimpan menurut peraturan — misalnya catatan kecelakaan
kerja, pemeriksaan kesehatan, dan dokumen ketenagakerjaan — tetap disimpan
sampai masa wajib simpannya berakhir, meski akunnya sudah dihapus.</p>

<h2>10. Anak-anak</h2>

<p>EQOHSEE adalah aplikasi tempat kerja untuk industri pertambangan dan tidak
ditujukan bagi anak-anak. Kami tidak mengumpulkan data anak secara sengaja.
Usia kerja minimum pada sektor ini diatur peraturan ketenagakerjaan yang
berlaku.</p>

<h2>11. Menghubungi kami</h2>

<p>Untuk pertanyaan mengenai kebijakan ini, permintaan penghapusan data, atau
laporan dugaan kebocoran data:</p>

<div class="kotak">
  <p><strong>EQOHSEE</strong></p>
  <p>Surel: <a href="mailto:{{ $surel }}">{{ $surel }}</a><br>
  Situs: <a href="https://eqohsee.id">eqohsee.id</a></p>
</div>

<h2>12. Perubahan kebijakan</h2>

<p>Kebijakan ini dapat diperbarui. Perubahan diumumkan pada halaman ini beserta
tanggal berlakunya yang baru. Perubahan yang berdampak besar akan diberitahukan
kepada perusahaan pengguna sebelum diberlakukan.</p>

@endsection

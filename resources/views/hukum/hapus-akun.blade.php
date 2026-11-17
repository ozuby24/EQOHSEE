@extends('hukum.tata', [
  'lang'    => 'id',
  'judul'   => 'Hapus Akun',
  'tanggal' => 'Berlaku sejak 27 September 2026',
  'ringkas' => 'Cara menghapus akun EQOHSEE beserta data pribadi yang melekat padanya.',
  'alih'    => 'English version: <a href="/delete-account">Delete Account</a>',
  'kaki'    => 'Aplikasi pengelolaan HSE dan K3 pertambangan',
])

@section('isi')

<p>Halaman ini menjelaskan cara menghapus akun <strong>EQOHSEE</strong> — aplikasi
web di <code>eqohsee.id</code> dan aplikasi Android <code>id.eqohsee.eqohsee</code>
— dan data apa yang ikut terhapus.</p>

<h2>1. Hapus sendiri dari aplikasi</h2>

<ol>
  <li>Masuk ke EQOHSEE dengan akun yang ingin dihapus.</li>
  <li>Buka <strong>Profil → Akun &amp; kata sandi</strong>
      (di versi web: menu akun → <strong>Profil</strong>).</li>
  <li>Pada bagian <strong>Hapus Akun</strong>, tekan <strong>Hapus Akun</strong>,
      ketik kata sandi Anda, lalu tegaskan.</li>
</ol>

<p>Akun terhapus <strong>seketika</strong>, sesi di seluruh perangkat berakhir,
dan Anda tidak dapat masuk lagi dengan akun itu.</p>

<h2>2. Minta dihapuskan</h2>

<p>Bila Anda tidak dapat masuk lagi — lupa kata sandi, perangkat hilang, atau
sudah tidak bekerja di perusahaan yang memakai EQOHSEE — kirim permintaan ke:</p>

<div class="kotak">
  <p><strong>Permintaan hapus akun</strong></p>
  <p>Surel: <a href="mailto:{{ $surel }}?subject=Permintaan%20hapus%20akun%20EQOHSEE&amp;body=Alamat%20surel%20akun%3A%20%0APerusahaan%3A%20%0ANama%3A%20">{{ $surel }}</a><br>
  Sebutkan alamat surel akun, nama, dan perusahaan tempat Anda bekerja.</p>
</div>

<p>Kami memastikan identitas pemohon lebih dulu, lalu menghapus akunnya
<strong>paling lambat 30 hari</strong> sejak permintaan diterima. Anda akan
menerima balasan ketika penghapusan selesai.</p>

<p>Bila akun Anda dibuat oleh perusahaan (HRD atau Departemen HSE), permintaan
juga dapat diajukan kepada administrator perusahaan Anda.</p>

<h2>3. Data yang dihapus</h2>

<ul>
  <li>Akun: nama, alamat surel, kata sandi terenkripsi, jabatan, foto profil,
      pengaturan, dan verifikasi dua langkah</li>
  <li>Sesi masuk dan penanda perangkat</li>
  <li>Keterkaitan akun dengan data yang pernah Anda isi — catatan itu tidak lagi
      menunjuk ke akun Anda</li>
  <li>Data di perangkat: laporan yang belum terkirim, draf, dan salinan layar
      mode lapangan terhapus saat Anda keluar atau mencopot aplikasi</li>
</ul>

<h2>4. Data yang tetap disimpan</h2>

<p>Sebagian catatan keselamatan dan ketenagakerjaan <strong>wajib disimpan
perusahaan</strong> menurut peraturan, sehingga tidak ikut terhapus bersama
akun:</p>

<ul>
  <li>Laporan bahaya, inspeksi, izin kerja, dan P2H yang sudah dikirim — tetap
      menjadi catatan K3 perusahaan, dengan nama pelapor sebagaimana tertulis
      pada laporannya</li>
  <li>Catatan kecelakaan kerja dan investigasinya</li>
  <li>Hasil pemeriksaan kesehatan (MCU) dan dokumen ketenagakerjaan</li>
</ul>

<p>Catatan itu disimpan selama masa wajib simpannya menurut peraturan yang
berlaku bagi perusahaan Anda, lalu dihapus oleh perusahaan. Jejak tindakan pada
data penting (siapa mengubah apa dan kapan) ikut disimpan bersama catatan yang
dijejakinya.</p>

<p>Penjelasan lengkap tentang data yang dikumpulkan ada pada
<a href="/kebijakan-privasi">Kebijakan Privasi</a>.</p>

@endsection

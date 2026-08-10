{{--
  Latar hero.

  Tiga tingkat, dipilih menurut berkas yang benar-benar ada:
  video bila tersedia, foto bila hanya foto, panorama SVG bila belum ada
  keduanya. Halaman depan tidak boleh menunggu berkas media untuk terlihat
  utuh — dan tidak boleh menampilkan kotak gambar rusak.

  Video hanya dipasang di layar lebar dan hanya bila perangkat tidak
  meminta gerak dikurangi. Sumbernya ditulis lewat JavaScript, bukan lewat
  atribut src: peramban mengunduh video begitu src-nya ada, jadi menulisnya
  di markup berarti ponsel tetap menanggung belasan megabita meski
  videonya tidak pernah tampil.
--}}
@php
  use App\Support\Media;
  $video  = Media::heroVideo();
  $poster = Media::heroPoster();
@endphp

@if($video || $poster)
  <div class="absolute inset-0">
    @if($poster)
      <img src="{{ $poster }}" alt="" class="w-full h-full object-cover" fetchpriority="high">
    @endif

    @if($video)
      <video class="absolute inset-0 w-full h-full object-cover opacity-0 transition-opacity duration-[1200ms]"
             data-hero-video="{{ $video }}"
             @if($poster) poster="{{ $poster }}" @endif
             muted playsinline loop preload="none" aria-hidden="true"></video>
    @endif
  </div>
@else
  {{-- Panorama mandiri; tetap tampil penuh saat jaringan site terputus.
       Dijangkarkan ke bawah supaya alat berat berhenti di pita bawah dan
       bidang atas jadi milik langit — di situlah judulnya duduk. --}}
  <div class="absolute inset-0" data-parallax="0.14">
    @include('partials.art-mine', ['jangkar' => 'xMidYMax'])
  </div>
@endif

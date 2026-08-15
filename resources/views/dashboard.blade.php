@extends('layouts.app')
@section('title','Dashboard')
@section('subjudul','Kelola pembelajaran dan tingkatkan kompetensi Anda')

@section('content')
@php
  use App\Support\Media;

  use App\Support\Sampul;
  use App\Support\Waktu;

  $nama = trim(explode(' ', auth()->user()->name)[0]);
  $sapa = Waktu::sapaan();

  $sampul = fn ($course) => Sampul::untuk($course);
@endphp

<div class="max-w-[1400px] mx-auto space-y-5">

  {{-- ══════════ SAMBUTAN ══════════ --}}
  <section class="eq-hero">
    @if($foto = Media::url('galeri/budaya.jpg'))
      <img class="eq-hero-foto" src="{{ $foto }}" alt="" loading="lazy">
    @endif

    <div class="eq-hero-isi">
      <h2>{{ $sapa }}, {{ $nama }} <span aria-hidden="true">👋</span></h2>
      <p>Tingkatkan kompetensi dan budaya keselamatan Anda setiap hari.</p>

      @php
        $lanjut = $enrollments->firstWhere('status','ongoing') ?? $enrollments->first();
      @endphp
      <a href="{{ $lanjut ? route('learn.show', $lanjut->course) : route('courses.index') }}" class="eq-hero-btn">
        {{ $lanjut ? 'Lanjutkan Pembelajaran' : 'Jelajahi Kursus' }}
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
      </a>
    </div>
  </section>

  {{-- ══════════ KARTU ANGKA ══════════ --}}
  <div class="eq-kpi-baris">
    @foreach ([
      ['Total Kursus',    $ringkas['total'],    'Semua kursus tersedia', 'hijau',
       '<path d="M12 3.5 2.8 8 12 12.5 21.2 8Zm-5.6 6.4v4.4c0 1.6 2.5 2.9 5.6 2.9s5.6-1.3 5.6-2.9V9.9"/>'],
      ['Kursus Selesai',  $ringkas['selesai'],  'Kursus telah selesai', 'biru',
       '<path d="M5 4.5A1.5 1.5 0 0 1 6.5 3H18a1 1 0 0 1 1 1v13.5H6.5A1.5 1.5 0 0 0 5 19Zm0 14.5A1.5 1.5 0 0 0 6.5 20.5H19"/><path d="M9 8.5l2 2 3.5-3.6"/>'],
      ['Progress Belajar', $ringkas['kemajuan'].'%', 'Rata-rata progress', 'toska',
       '<path d="M12 3.5a8.5 8.5 0 1 0 8.5 8.5"/><path d="M12 3.5A8.5 8.5 0 0 1 20.5 12"/><path d="M12 8v4l2.6 2.6"/>'],
      ['Sertifikat',      $certificates,        'Sertifikat diperoleh', 'kuning',
       '<path d="M12 3.2a4.6 4.6 0 1 0 0 9.2 4.6 4.6 0 0 0 0-9.2Zm-3.6 8.6L7 20.8l5-2.6 5 2.6-1.4-9"/>'],
      ['Belum Diikuti',   $ringkas['belum'],    'Menunggu untuk dimulai', 'ungu',
       '<path d="M5 6.5h14a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-11a1 1 0 0 1 1-1ZM8 4v4m8-4v4M4 11h16"/>'],
    ] as [$label, $nilai, $ket, $nada, $ikon])
      <article class="eq-kpi">
        <span class="eq-kpi-ikon t-{{ $nada }}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $ikon !!}</svg>
        </span>
        <span class="eq-kpi-isi">
          <span class="eq-kpi-label">{{ $label }}</span>
          <span class="eq-kpi-nilai">{{ is_int($nilai) ? sprintf('%02d', $nilai) : $nilai }}</span>
          <span class="eq-kpi-ket">{{ $ket }}</span>
        </span>
      </article>
    @endforeach
  </div>

  {{-- ══════════ KURSUS + SISI KANAN ══════════ --}}
  <div class="eq-kisi-utama">

    {{-- ---------- Kursus saya ---------- --}}
    <section class="eq-panel">
      <div class="eq-panel-kepala">
        <h3>Kursus Saya</h3>
        <a href="{{ route('courses.index') }}" class="eq-tautan">
          Lihat Semua
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
      </div>

      @if($enrollments->count())
        <div class="eq-kursus-kisi">
          @foreach($enrollments->take(3) as $e)
            @php
              $c = $e->course;
              // Nama variabel di sini harus khas: $modul sudah dipakai
              // controller untuk daftar pintasan modul, dan blok @php
              // berbagi ruang nama dengan seluruh view.
              $jumlahModul = $c?->modules()->count() ?? 0;
              $menit = max(10, $jumlahModul * 15);
              $p = (int) $e->progress;
            @endphp
            <article class="eq-kursus">
              <div class="eq-kursus-gambar">
                @if($g = $sampul($c))
                  <img src="{{ $g }}" alt="" loading="lazy">
                @endif
                <span class="eq-kursus-lencana">
                  @if($c?->category)
                    <i class="l-utama k-{{ \App\Support\Kategori::nada($c->category) }}">{{ strtoupper($c->category) }}</i>
                  @endif
                  @if($e->status === 'ongoing')<i class="l-ikut">DIIKUTI</i>@endif
                  @if($e->status === 'finished')<i class="l-selesai">SELESAI</i>@endif
                </span>
              </div>

              <div class="eq-kursus-isi">
                <h4>{{ $c?->title ?? 'Kursus' }}</h4>
                <p>{{ \Illuminate\Support\Str::limit($c?->description, 78) }}</p>

                <div class="eq-kursus-meta">
                  <span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                      <path d="M5 4.5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-13a1 1 0 0 1 1-1ZM4 9h16"/></svg>
                    {{ $jumlahModul }} Modul
                  </span>
                  <span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                      <circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg>
                    {{ $menit }} Menit
                  </span>
                </div>

                <div class="eq-kursus-maju">
                  <span>Progress Anda</span>
                  <b>{{ $p }}%</b>
                </div>
                <div class="eq-bilah"><i style="width:{{ $p }}%"></i></div>

                <div class="eq-kursus-aksi">
                  <a href="{{ route('learn.show', $c) }}" class="eq-btn-utama">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                    {{ $p > 0 ? 'Lanjut Belajar' : 'Mulai Belajar' }}
                  </a>
                  <a href="{{ route('courses.show', $c) }}" class="eq-btn-lain">Detail</a>
                </div>
              </div>
            </article>
          @endforeach
        </div>
      @else
        <div class="eq-kosong">
          <p><strong>Belum ada kursus yang diikuti.</strong></p>
          <p>Mulai dari katalog kursus dan pilih yang paling dibutuhkan pekerjaan Anda.</p>
          <a href="{{ route('courses.index') }}" class="eq-btn-utama">Jelajahi Kursus</a>
        </div>
      @endif
    </section>

    {{-- ---------- Kolom kanan ---------- --}}
    <div class="eq-kolom-sisi">

      <section class="eq-panel">
        <div class="eq-panel-kepala">
          <h3>Progress Mingguan</h3>
          <span class="eq-chip">7 Hari</span>
        </div>
        @php
          $nilaiPekan = array_column($pekan, 'nilai');
          $adaPekan   = array_sum($nilaiPekan) > 0;
        @endphp

        @if($adaPekan)
          <x-garis :titik="$nilaiPekan" :tinggi="150" satuan="modul" :desimal="0" />
          <div class="eq-pekan-label">
            @foreach($pekan as $h)<span>{{ $h['label'] }}</span>@endforeach
          </div>
          <p class="eq-panel-kaki">
            {{ array_sum($nilaiPekan) }} modul diselesaikan dalam tujuh hari terakhir.
          </p>
        @else
          <div class="eq-kosong eq-kosong-kecil">
            <p>Belum ada modul yang diselesaikan pekan ini.</p>
            <p class="halus">Grafik muncul setelah ada modul yang dituntaskan.</p>
          </div>
        @endif
      </section>

      <section class="eq-panel">
        <div class="eq-panel-kepala">
          <h3>Pengumuman</h3>
          <a href="{{ route('news.index') }}" class="eq-tautan">
            Lihat Semua
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </a>
        </div>

        @if($news->count())
          <ul class="eq-warta">
            @foreach($news as $i => $n)
              @php $nada = ['kuning','biru','toska'][$i % 3]; @endphp
              <li>
                <a href="{{ route('news.show', $n) }}">
                  <span class="eq-warta-ikon t-{{ $nada }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M4 9.5h3l6-4v13l-6-4H4Z"/><path d="M17 9a4 4 0 0 1 0 6"/></svg>
                  </span>
                  <span class="eq-warta-teks">
                    <strong>{{ $n->title }}</strong>
                    <small>{{ \Illuminate\Support\Str::limit(strip_tags($n->content), 74) }}</small>
                  </span>
                  <time>{{ optional($n->published_at ?? $n->created_at)->translatedFormat('d M') }}</time>
                </a>
              </li>
            @endforeach
          </ul>
          <a href="{{ route('news.index') }}" class="eq-btn-lain eq-btn-blok">Lihat Semua Pengumuman</a>
        @else
          <div class="eq-kosong eq-kosong-kecil"><p>Belum ada pengumuman.</p></div>
        @endif
      </section>
    </div>
  </div>

  {{-- ══════════ KATEGORI ══════════ --}}
  @if(count($kategori))
    <section class="eq-panel">
      <div class="eq-panel-kepala">
        <h3>Kategori Kursus</h3>
        <a href="{{ route('courses.index') }}" class="eq-tautan">
          Lihat Semua
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
      </div>

      <div class="eq-kategori">
        @foreach($kategori as $i => $k)
          @php $nada = \App\Support\Kategori::nada($k['nama']); @endphp
          <a href="{{ route('courses.index', ['kategori' => $k['nama']]) }}">
            <span class="eq-kategori-ikon t-{{ $nada }}">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                   stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 3.2 19.2 6.4v5.2c0 4.3-3 8.3-7.2 9.6-4.2-1.3-7.2-5.3-7.2-9.6V6.4Z"/></svg>
            </span>
            <span>
              <strong>{{ $k['nama'] }}</strong>
              <small>{{ $k['jumlah'] }} Kursus</small>
            </span>
          </a>
        @endforeach
      </div>
    </section>
  @endif

  {{-- ══════════ PINTASAN MODUL ══════════ --}}
  <section class="eq-panel">
    <div class="eq-panel-kepala">
      <h3>Modul Lainnya</h3>
      <span class="eq-panel-ket">Angka yang ditampilkan adalah yang butuh perhatian.</span>
    </div>

    <div class="eq-modul">
      @foreach($modul as $m)
        <a href="{{ route($m['rute']) }}">
          <span class="eq-modul-atas">
            <span class="eq-modul-nilai" style="color:{{ $m['warna'] }}">{{ $m['nilai'] }}</span>
            <span class="eq-modul-ikon" style="background:{{ $m['warna'] }}18;color:{{ $m['warna'] }}">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                   stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="{{ \App\Support\Ikon::untuk($m['ikon']) }}"/></svg>
            </span>
          </span>
          <strong>{{ $m['nama'] }}</strong>
          <small>{{ $m['ket'] }}@if(($m['total'] ?? 0) > 0) · dari {{ $m['total'] }}@endif</small>
        </a>
      @endforeach
    </div>
  </section>

  @if($admin)
    <section class="eq-panel">
      <div class="eq-panel-kepala"><h3>Ringkasan Sistem</h3></div>
      <div class="eq-kategori">
        @foreach ([
          ['Pengguna', $admin['users']], ['Kursus', $admin['courses']],
          ['Prosedur', $admin['procedures']], ['Sertifikat terbit', $admin['certs']],
        ] as $i => [$l, $v])
          <div class="eq-admin-angka">
            <span class="eq-kpi-nilai">{{ $v }}</span>
            <small>{{ $l }}</small>
          </div>
        @endforeach
      </div>
    </section>
  @endif

</div>
@endsection

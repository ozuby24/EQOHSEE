@extends('layouts.app')
@section('title','Regulations & Standards')

@section('content')
<div class="max-w-6xl mx-auto space-y-5">

  <section class="kartu-lux rounded-2xl p-6">
    <h2 class="font-display text-[20px] font-black text-cam-ink">Regulations &amp; Standards</h2>
    <p class="text-[12.5px] text-stone-500 mt-1.5 leading-relaxed max-w-3xl">
      Acuan yang dipakai platform ini. Yang dicatat hanya identitas dokumen — nomor, tahun, penerbit,
      dan satu kalimat ruang lingkup. Isi pasalnya sengaja tidak disalin: teks standar ISO berhak
      cipta, dan regulasi harus dibaca dari sumber resminya agar revisinya tidak tertinggal.
    </p>

    <div class="flex flex-wrap gap-1.5 mt-5 pt-5 hairline border-b-0">
      @foreach($semuaKategori as $k)
        <a href="{{ route('meh.regulations', $k === 'Semua' ? [] : ['kategori'=>$k]) }}"
           class="text-[11.5px] font-bold px-3.5 py-1.5 rounded-xl transition
                  {{ $kategori === $k ? 'bg-cam-ink text-white' : 'bg-stone-100 text-stone-500 hover:bg-stone-200' }}">{{ $k }}</a>
      @endforeach
    </div>
  </section>

  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @foreach($daftar as $r)
      <article class="kartu-lux rounded-2xl p-5 flex flex-col">
        <div class="flex items-start justify-between gap-3">
          <span class="text-[9.5px] font-bold uppercase tracking-wider px-2 py-1 rounded-lg bg-cam-lime-soft text-cam-lime-deep">
            {{ $r['kategori'] }}</span>
          <span class="num text-[12px] text-stone-400">{{ $r['tahun'] }}</span>
        </div>
        <h3 class="text-[13.5px] font-bold text-cam-ink leading-snug mt-3">{{ $r['judul'] }}</h3>
        <p class="text-[11.5px] text-stone-400 mt-1">{{ $r['penerbit'] }}</p>
        <p class="text-[12px] text-stone-500 mt-3 leading-relaxed">{{ $r['ket'] }}</p>
        <div class="mt-auto pt-4">
          <a href="{{ $r['sumber'] }}" target="_blank" rel="noopener noreferrer"
             class="inline-flex items-center gap-2 text-[12px] font-bold text-cam-lime-deep hover:underline">
            Lihat sumber resmi
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M14 5h5v5M19 5l-7 7M18 14v5H5V6h5"/>
            </svg>
          </a>
        </div>
      </article>
    @endforeach
  </div>

  @if(!count($daftar))
    <p class="text-[12px] text-stone-400">Tidak ada acuan pada kategori {{ $kategori }}.</p>
  @endif

</div>
@endsection

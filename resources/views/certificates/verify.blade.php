@extends('kuesioner.layout')
@section('title','Verifikasi Sertifikat')

@section('content')
@if($c)
  <div class="bg-white rounded-2xl shadow-card border border-cam-lime/30 p-8 text-center animate-pop">
    <div class="w-16 h-16 mx-auto rounded-full lime-gradient shadow-glow grid place-items-center text-white text-[28px]">✓</div>
    <h1 class="font-display text-[22px] font-black text-cam-ink mt-4">Sertifikat Sah</h1>
    <p class="text-[12.5px] text-stone-500 mt-1">Data berikut tercatat pada sistem EQOHSEE.</p>

    <div class="text-left mt-6 space-y-2.5">
      @foreach ([
        ['Nama penerima', $c->recipient_name],
        ['Pelatihan',     $c->course_title],
        ['Perusahaan',    $c->company?->ownerName() ?: '—'],
        ['Nomor',         $c->certificate_number],
        ['Kode verifikasi', $c->verification_code],
        ['Nilai akhir',   $c->final_score ?: '—'],
        ['Diterbitkan',   optional($c->issued_at)->format('d F Y')],
        ['Ditandatangani', $c->signed_by_name ?: '—'],
      ] as [$l,$v])
        <div class="flex items-start justify-between gap-4 border-b border-stone-100 pb-2 last:border-0">
          <span class="text-[11.5px] text-stone-400 shrink-0">{{ $l }}</span>
          <span class="text-[12.5px] font-semibold text-cam-ink text-right num">{{ $v }}</span>
        </div>
      @endforeach
    </div>
  </div>
@else
  <div class="bg-white rounded-2xl shadow-card border border-stone-200 p-10 text-center">
    <div class="w-16 h-16 mx-auto rounded-full bg-red-50 grid place-items-center text-red-500 text-[28px]">✕</div>
    <h1 class="font-display text-[22px] font-black text-cam-ink mt-4">Tidak Ditemukan</h1>
    <p class="text-[12.5px] text-stone-500 mt-2">Kode <span class="font-mono font-bold">{{ $kode }}</span> tidak terdaftar pada sistem.</p>
  </div>
@endif
@endsection

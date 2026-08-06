@extends('kuesioner.layout')
@section('title','Terima kasih')

@section('content')
<div class="bg-white rounded-2xl shadow-card border border-stone-100 p-10 text-center animate-pop">
  <div class="w-20 h-20 mx-auto rounded-full lime-gradient shadow-glow grid place-items-center text-white text-[34px]">✓</div>
  <h1 class="stat text-cam-ink mt-5">Terima kasih!</h1>
  <p class="text-[13px] text-stone-500 mt-2 leading-relaxed">
    Jawaban Anda sudah tersimpan dan akan digunakan untuk penilaian PTPKKP di {{ $company->name }}.
  </p>
  <a href="{{ route('kuesioner.pilih', $token) }}" class="inline-block mt-6 text-[12.5px] font-bold text-cam-lime-deep hover:underline">Isi kuesioner lain</a>
</div>
@endsection

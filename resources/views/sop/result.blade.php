@extends('layouts.app')
@section('title','Hasil Evaluasi SOP')

@section('content')
<div class="max-w-md mx-auto">
  <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-9 text-center animate-pop">
    <div class="w-24 h-24 mx-auto rounded-full grid place-items-center {{ $attempt->passed ? 'lime-gradient shadow-glow' : 'bg-stone-100' }}">
      <span class="stat stat-lg {{ $attempt->passed ? 'text-white' : 'text-stone-400' }}">{{ $attempt->score }}</span>
    </div>
    <h2 class="text-[19px] font-extrabold mt-5 text-cam-ink">{{ $attempt->passed ? 'Lulus!' : 'Belum lulus' }}</h2>
    <p class="text-[12.5px] text-stone-400 mt-1.5">Benar {{ $attempt->correct }} dari {{ $attempt->total }} soal · batas lulus {{ $evaluation->passing_score }}</p>
    <p class="text-[11px] text-stone-300 mt-3">{{ $evaluation->title }}</p>

    <div class="flex gap-2.5 justify-center mt-7">
      <a href="{{ route('sop.show', $evaluation) }}" class="rounded-xl border border-stone-200 px-5 py-2.5 text-[12.5px] font-bold text-stone-600 hover:bg-stone-50 transition">Ulangi</a>
      <a href="{{ route('sop.index') }}" class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">Daftar Evaluasi</a>
    </div>
  </div>
</div>
@endsection

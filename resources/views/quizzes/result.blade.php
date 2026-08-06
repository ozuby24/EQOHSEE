@extends('layouts.app')
@section('title','Hasil Kuis')

@section('content')
<div class="max-w-md mx-auto">
  <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-9 text-center animate-pop">
    <div class="w-24 h-24 mx-auto rounded-full grid place-items-center {{ $attempt->passed ? 'lime-gradient shadow-glow' : 'bg-stone-100' }}">
      <span class="stat stat-lg {{ $attempt->passed ? 'text-white' : 'text-stone-400' }}">{{ $attempt->score }}</span>
    </div>
    <h2 class="text-[19px] font-extrabold mt-5 text-cam-ink">{{ $attempt->passed ? 'Selamat, kamu lulus!' : 'Belum lulus' }}</h2>
    <p class="text-[12.5px] text-stone-400 mt-1.5">Benar {{ $correct }} dari {{ $total }} soal · batas lulus {{ $quiz->pass_score }}</p>

    <div class="flex gap-2.5 justify-center mt-7">
      <a href="{{ route('quizzes.show', $quiz) }}" class="rounded-xl border border-stone-200 px-5 py-2.5 text-[12.5px] font-bold text-stone-600 hover:bg-stone-50 transition">Ulangi</a>
      <a href="{{ route('dashboard') }}" class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">Ke Dashboard</a>
    </div>
  </div>
</div>
@endsection

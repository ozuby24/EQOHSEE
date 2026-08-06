@extends('layouts.app')
@section('title', $quiz->title)

@section('content')
<div class="max-w-3xl mx-auto">
  <div class="brand-gradient rounded-2xl p-6 mb-5 text-white shadow-card">
    <h2 class="stat leading-tight">{{ $quiz->title }}</h2>
    <p class="text-[12px] text-white/50 mt-1.5">{{ $quiz->questions->count() }} soal · nilai lulus {{ $quiz->pass_score }}</p>
  </div>

  <form action="{{ route('quizzes.submit', $quiz) }}" method="POST" class="space-y-3">
    @csrf
    @foreach($quiz->questions as $i => $q)
      <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
        <p class="text-[13.5px] font-bold text-cam-ink mb-3 leading-relaxed">{{ $i+1 }}. {{ $q->question }}</p>
        <div class="space-y-2">
          @foreach(($q->options ?? []) as $idx => $opt)
            <label class="flex items-center gap-3 rounded-xl border border-stone-200 px-4 py-2.5 cursor-pointer hover:border-cam-lime hover:bg-cam-lime-soft/50 transition">
              <input type="radio" name="answers[{{ $q->id }}]" value="{{ $idx }}" required class="text-cam-lime-dark focus:ring-cam-lime/40">
              <span class="text-[13px] text-stone-600">{{ $opt }}</span>
            </label>
          @endforeach
        </div>
      </div>
    @endforeach
    <button class="lime-gradient shadow-glow w-full rounded-xl text-white py-3 text-[13.5px] font-bold hover:brightness-105 transition">Kirim Jawaban</button>
  </form>
</div>
@endsection

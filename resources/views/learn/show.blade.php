@extends('layouts.app')
@section('title', $course->title)

@section('content')
<div class="max-w-4xl mx-auto space-y-5">
  @if(session('ok'))
    <div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>
  @endif

  {{-- Progres --}}
  <div class="brand-gradient rounded-2xl p-6 text-white shadow-card relative overflow-hidden">
    <div class="absolute -right-20 -top-20 w-56 h-56 rounded-full bg-cam-lime/20 blur-3xl"></div>
    <div class="relative">
      <h2 class="stat leading-tight">{{ $course->title }}</h2>
      <p class="text-[12.5px] text-white/50 mt-1.5 leading-relaxed">{{ $course->description }}</p>

      <div class="flex items-center gap-3 mt-5">
        <div class="flex-1 h-2 rounded-full bg-white/10 overflow-hidden">
          <div class="h-full rounded-full lime-gradient transition-all" style="width: {{ $enrollment->progress }}%"></div>
        </div>
        <span class="text-[13px] font-bold text-cam-lime-light">{{ $enrollment->progress }}%</span>
      </div>

      @if($enrollment->progress >= 100)
        <form action="{{ route('certificates.store', $course) }}" method="POST" class="mt-4">
          @csrf
          <button class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">🎓 Terbitkan Sertifikat</button>
        </form>
      @endif
    </div>
  </div>

  {{-- Modul --}}
  <div class="space-y-2.5">
    @forelse($course->modules as $module)
      @php $selesai = in_array($module->id, $done); @endphp
      <div class="bg-white rounded-2xl shadow-card border {{ $selesai ? 'border-cam-lime/35' : 'border-stone-100' }} p-5">
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2.5">
              @if($selesai)
                <span class="w-5 h-5 rounded-full lime-gradient text-white grid place-items-center text-[10px] font-bold shrink-0">✓</span>
              @else
                <span class="w-5 h-5 rounded-full border-2 border-stone-200 shrink-0"></span>
              @endif
              <h3 class="text-[14px] font-bold text-cam-ink">{{ $module->order_index }}. {{ $module->title }}</h3>
            </div>
            @if($module->description)
              <p class="text-[12.5px] text-stone-400 mt-1.5 ml-[30px] leading-relaxed">{{ $module->description }}</p>
            @endif
          </div>
          @unless($selesai)
            <form action="{{ route('modules.complete', $module) }}" method="POST">
              @csrf
              <button class="shrink-0 lime-gradient rounded-lg text-white px-3 py-1.5 text-[11px] font-bold hover:brightness-105 transition">Tandai selesai</button>
            </form>
          @endunless
        </div>

        @if($module->materials->count())
        <ul class="mt-3.5 ml-[30px] space-y-1.5">
          @foreach($module->materials as $mat)
            <li class="flex items-center gap-2 text-[12.5px]">
              <span class="text-[9.5px] uppercase font-bold bg-cam-lime-soft text-cam-lime-deep px-1.5 py-0.5 rounded tracking-wide">{{ $mat->type ?: 'file' }}</span>
              @if($mat->url)
                <a href="{{ $mat->url }}" target="_blank" rel="noopener" class="text-stone-600 hover:text-cam-lime-deep hover:underline">{{ $mat->title }}</a>
              @else
                <span class="text-stone-600">{{ $mat->title }}</span>
              @endif
            </li>
          @endforeach
        </ul>
        @endif

        <details class="mt-3.5 ml-[30px]">
          <summary class="text-[12px] font-bold text-cam-lime-deep cursor-pointer hover:underline">Catatan saya</summary>
          <form action="{{ route('notes.save', $module) }}" method="POST" class="mt-2">
            @csrf
            <textarea name="content" rows="3" placeholder="Tulis catatan..."
              class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] leading-relaxed transition">{{ $notes[$module->id] ?? '' }}</textarea>
            <button class="mt-2 rounded-lg bg-cam-ink text-white px-3 py-1.5 text-[11px] font-bold hover:bg-cam-panel transition">Simpan catatan</button>
          </form>
        </details>
      </div>
    @empty
      <div class="bg-white rounded-2xl border border-dashed border-stone-200 p-12 text-center text-[13px] text-stone-400">Kursus ini belum memiliki modul.</div>
    @endforelse
  </div>

  @if($course->quizzes->count())
    <h3 class="text-[15px] font-bold text-cam-ink pt-1">Kuis</h3>
    <div class="space-y-2">
      @foreach($course->quizzes as $quiz)
        <a href="{{ route('quizzes.show', $quiz) }}"
           class="flex items-center justify-between bg-white rounded-xl shadow-soft border border-stone-100 px-5 py-4 hover:border-cam-lime/40 transition">
          <span class="text-[13px] font-bold text-cam-ink">{{ $quiz->title }}</span>
          <span class="text-[11.5px] text-stone-400">Lulus ≥ {{ $quiz->pass_score }} →</span>
        </a>
      @endforeach
    </div>
  @endif
</div>
@endsection

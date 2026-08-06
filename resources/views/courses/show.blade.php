@extends('layouts.app')
@section('title', $course->title)

@section('content')
<div class="max-w-4xl mx-auto space-y-5">
  <div class="brand-gradient rounded-2xl overflow-hidden text-white shadow-card">
    <div class="md:flex">
      <div class="md:w-52 h-36 md:h-auto bg-black/25 shrink-0">
        @if($course->image)<img src="{{ asset('storage/'.$course->image) }}" class="w-full h-full object-cover">@endif
      </div>
      <div class="p-6 flex-1">
        @if($course->category)
          <span class="glass rounded-full text-[10px] font-bold px-2.5 py-1">{{ $course->category }}</span>
        @endif
        <h2 class="stat mt-2.5 leading-tight">{{ $course->title }}</h2>
        <p class="text-[13px] text-white/50 mt-2 leading-relaxed">{{ $course->description ?: '—' }}</p>
        <div class="flex items-center gap-3 mt-5">
          <a href="{{ route('learn.show', $course) }}"
             class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">Mulai Belajar</a>
          @can('admin')
            <a href="{{ route('courses.edit', $course) }}" class="text-[12.5px] font-bold text-cam-lime-light hover:underline">Edit kursus</a>
          @endcan
        </div>
      </div>
    </div>
  </div>

  <div>
    <h3 class="text-[15px] font-bold text-cam-ink mb-3">Modul &amp; Materi</h3>
    <div class="space-y-2.5">
      @forelse($course->modules as $module)
        <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
          <div class="text-[14px] font-bold text-cam-ink">{{ $module->order_index }}. {{ $module->title }}</div>
          @if($module->description)<p class="text-[12.5px] text-stone-400 mt-1 leading-relaxed">{{ $module->description }}</p>@endif
          @if($module->materials->count())
            <ul class="mt-3 space-y-1.5">
              @foreach($module->materials as $mat)
                <li class="flex items-center gap-2 text-[12.5px] text-stone-500">
                  <span class="text-[9.5px] uppercase font-bold bg-cam-lime-soft text-cam-lime-deep px-1.5 py-0.5 rounded tracking-wide">{{ $mat->type ?: 'file' }}</span>
                  {{ $mat->title }}
                </li>
              @endforeach
            </ul>
          @endif
        </div>
      @empty
        <div class="bg-white rounded-2xl border border-dashed border-stone-200 p-10 text-center text-[13px] text-stone-400">Belum ada modul.</div>
      @endforelse
    </div>
  </div>

  <a href="{{ route('courses.index') }}" class="inline-block text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink">← Kembali ke daftar</a>
</div>
@endsection

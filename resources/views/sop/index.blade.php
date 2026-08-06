@extends('layouts.app')
@section('title','Evaluasi SOP')

@section('content')
<div class="max-w-4xl mx-auto">
  <div class="glass-light rounded-xl border border-stone-200/60 px-4 py-3 mb-5">
    <p class="text-[12px] text-stone-500 leading-relaxed">
      Uji pemahamanmu terhadap prosedur kerja. <span class="font-semibold text-cam-lime-deep">Penilaian dilakukan di server</span> — kunci jawaban tidak pernah dikirim ke perangkat.
    </p>
  </div>

  <div class="space-y-2.5">
    @forelse($evaluations as $ev)
      @php $b = $best[$ev->id] ?? null; @endphp
      <a href="{{ route('sop.show', $ev) }}"
         class="block bg-white rounded-2xl shadow-card border border-stone-100 p-5 hover:border-cam-lime/40 card-hover transition">
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0">
            <h3 class="text-[14px] font-bold text-cam-ink">{{ $ev->title }}</h3>
            @if($ev->procedure)
              <div class="text-[11px] text-stone-400 mt-0.5">{{ $ev->procedure->code }} · {{ $ev->procedure->title }}</div>
            @endif
            @if($ev->description)<p class="text-[12.5px] text-stone-400 mt-1.5 leading-relaxed">{{ $ev->description }}</p>@endif
            <div class="flex gap-3 mt-2.5 text-[10.5px] text-stone-400">
              <span>⏱ {{ $ev->duration_minutes }} menit</span>
              <span>🎯 Lulus ≥ {{ $ev->passing_score }}</span>
            </div>
          </div>
          <div class="shrink-0 text-right">
            @if($b)
              <div class="stat leading-none {{ $b->lulus ? 'text-cam-lime-dark' : 'text-stone-300' }}">{{ $b->best }}</div>
              <div class="text-[9.5px] uppercase tracking-wide text-stone-400 mt-1">{{ $b->lulus ? 'Lulus' : 'Terbaik' }}</div>
            @else
              <span class="text-[10px] font-bold bg-cam-lime-soft text-cam-lime-deep px-2.5 py-1 rounded-full">Belum dikerjakan</span>
            @endif
          </div>
        </div>
      </a>
    @empty
      <div class="bg-white rounded-2xl border border-dashed border-stone-200 p-14 text-center text-[13px] text-stone-400">Belum ada evaluasi SOP aktif.</div>
    @endforelse
  </div>
</div>
@endsection

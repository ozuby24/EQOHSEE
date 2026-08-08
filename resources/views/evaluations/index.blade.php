@extends('layouts.app')
@section('title', $bolehMenilai ? 'Evaluasi Pelatihan' : 'Evaluasi Saya')

@section('content')
<div class="max-w-5xl mx-auto">
  @if(session('ok'))
    <div class="mb-5 rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>
  @endif

  @if($bolehMenilai)
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
      <p class="text-[12.5px] text-stone-400">Penilaian peserta setelah pelatihan selesai.</p>
      <a href="{{ route('evaluations.create') }}"
         class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">+ Nilai Peserta</a>
    </div>
  @endif

  {{-- Menunggu dievaluasi (berdasarkan kursus yang sudah diselesaikan) --}}
  @if($bolehMenilai && $menunggu->count())
    <div class="bg-cam-lime-soft border border-cam-lime/25 rounded-2xl p-5 mb-5">
      <h3 class="text-[13px] font-bold text-cam-lime-deep mb-1">Menunggu dievaluasi</h3>
      <p class="text-[11.5px] text-stone-500 mb-3">Peserta berikut sudah menyelesaikan kursusnya tetapi belum dinilai trainer.</p>
      <div class="space-y-2">
        @foreach($menunggu as $en)
          <div class="flex flex-wrap items-center justify-between gap-3 bg-white rounded-xl px-4 py-3">
            <div class="min-w-0">
              <div class="text-[13px] font-bold text-cam-ink">{{ $en->user->name ?? '—' }}</div>
              <div class="text-[11.5px] text-stone-400">{{ $en->course->title ?? '—' }}</div>
            </div>
            <a href="{{ route('evaluations.create', ['user' => $en->user_id, 'course' => $en->course_id]) }}"
               class="lime-gradient rounded-lg text-white px-3.5 py-1.5 text-[11.5px] font-bold hover:brightness-105 transition shrink-0">Nilai sekarang</a>
          </div>
        @endforeach
      </div>
    </div>
  @endif

  <div class="space-y-2.5">
    @forelse($evaluations as $ev)
      <a href="{{ route('evaluations.show', $ev) }}"
         class="block bg-white rounded-2xl shadow-card border border-stone-100 p-5 hover:border-cam-lime/40 card-hover transition">
        <div class="flex items-center justify-between gap-4">
          <div class="min-w-0">
            <div class="text-[14px] font-bold text-cam-ink">{{ $ev->user->name ?? '—' }}</div>
            <div class="text-[12px] text-stone-400 mt-0.5 clamp-1">{{ optional($ev->course)->title ?: 'Tanpa kursus' }}</div>
            <div class="text-[11px] text-stone-300 mt-1.5">
              Dinilai oleh {{ $ev->trainer_name ?: optional($ev->trainer)->name ?: '—' }} · {{ $ev->created_at->format('d M Y') }}
            </div>
          </div>
          <div class="text-right shrink-0">
            <div class="stat leading-none {{ $ev->overall_score >= 70 ? 'text-cam-lime-dark' : 'text-stone-300' }}">
              {{ $ev->overall_score }}
            </div>
            @if($ev->recommendation)
              <div class="text-[9.5px] font-bold uppercase tracking-wide text-stone-400 mt-1.5 max-w-[120px]">{{ $ev->recommendation }}</div>
            @endif
          </div>
        </div>
      </a>
    @empty
      <div class="bg-white rounded-2xl border border-dashed border-stone-200 p-14 text-center">
        <p class="text-[13px] text-stone-400">Belum ada evaluasi.</p>
        @if($bolehMenilai)<p class="text-[12px] text-stone-300 mt-1">Klik "Nilai Peserta" untuk membuat evaluasi pertama.</p>@endif
      </div>
    @endforelse
  </div>

  <div class="mt-5">{{ $evaluations->links() }}</div>
</div>
@endsection

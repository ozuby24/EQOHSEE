@extends('layouts.app')
@section('title','Hasil Evaluasi')

@section('content')
<div class="max-w-3xl mx-auto space-y-5">
  @if(session('ok'))
    <div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>
  @endif

  {{-- Ringkasan --}}
  <div class="brand-gradient rounded-2xl p-7 text-white shadow-card relative overflow-hidden">
    <div class="absolute -right-20 -top-20 w-56 h-56 rounded-full bg-cam-lime/20 blur-3xl"></div>
    <div class="relative flex flex-wrap items-center justify-between gap-6">
      <div>
        <span class="text-[10px] font-bold uppercase tracking-[0.22em] text-cam-lime-light">Evaluasi Pasca-Pelatihan</span>
        <h2 class="stat mt-2 leading-tight">{{ $evaluation->user->name ?? '—' }}</h2>
        <p class="text-[12.5px] text-white/50 mt-1">{{ optional($evaluation->course)->title ?: 'Tanpa kursus' }}</p>
        <p class="text-[11px] text-white/35 mt-2.5">
          Dinilai oleh {{ $evaluation->trainer_name ?: optional($evaluation->trainer)->name ?: '—' }}
          · {{ $evaluation->created_at->format('d F Y') }}
        </p>
      </div>
      <div class="glass rounded-2xl px-7 py-5 text-center">
        <div class="stat stat-xl leading-none text-cam-lime-light">{{ $evaluation->overall_score }}</div>
        <div class="text-[9.5px] uppercase tracking-[0.15em] text-white/40 mt-2 font-bold">Nilai Akhir</div>
      </div>
    </div>

    @if($evaluation->recommendation)
      <div class="relative mt-5 inline-block glass rounded-xl px-4 py-2 text-[12px] font-bold">
        📋 {{ $evaluation->recommendation }}
      </div>
    @endif
  </div>

  {{-- Rincian nilai --}}
  <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-6">
    <h3 class="text-[14px] font-bold text-cam-ink mb-4">Rincian Penilaian</h3>
    <div class="space-y-4">
      @foreach ([
        ['Pengetahuan', $evaluation->knowledge_score],
        ['Keterampilan', $evaluation->skill_score],
        ['Sikap', $evaluation->attitude_score],
        ['Keselamatan', $evaluation->safety_score],
      ] as [$label,$val])
        <div>
          <div class="flex items-center justify-between text-[12.5px] mb-1.5">
            <span class="font-semibold text-stone-600">{{ $label }}</span>
            <span class="font-bold {{ $val >= 70 ? 'text-cam-lime-deep' : 'text-stone-400' }}">{{ $val }}</span>
          </div>
          <div class="h-2 rounded-full bg-stone-100 overflow-hidden">
            <div class="h-full rounded-full lime-gradient transition-all" style="width: {{ (int) $val }}%"></div>
          </div>
        </div>
      @endforeach
    </div>
  </div>

  {{-- Catatan --}}
  @if($evaluation->strengths || $evaluation->improvements || $evaluation->notes)
    <div class="grid gap-3 sm:grid-cols-2">
      @if($evaluation->strengths)
        <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
          <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-cam-lime-deep">Kekuatan</div>
          <p class="text-[13px] text-stone-600 mt-2 leading-relaxed whitespace-pre-line">{{ $evaluation->strengths }}</p>
        </div>
      @endif
      @if($evaluation->improvements)
        <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
          <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">Perlu Ditingkatkan</div>
          <p class="text-[13px] text-stone-600 mt-2 leading-relaxed whitespace-pre-line">{{ $evaluation->improvements }}</p>
        </div>
      @endif
      @if($evaluation->notes)
        <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 sm:col-span-2">
          <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">Catatan Tambahan</div>
          <p class="text-[13px] text-stone-600 mt-2 leading-relaxed whitespace-pre-line">{{ $evaluation->notes }}</p>
        </div>
      @endif
    </div>
  @endif

  <div class="flex items-center gap-2.5">
    <a href="{{ route('evaluations.index') }}" class="rounded-xl border border-stone-200 px-5 py-2.5 text-[12.5px] font-bold text-stone-600 hover:bg-stone-50 transition">← Kembali</a>
    @can('trainer')
      <a href="{{ route('evaluations.edit', $evaluation) }}" class="rounded-xl border border-stone-200 px-5 py-2.5 text-[12.5px] font-bold text-cam-lime-deep hover:bg-cam-lime-soft transition">Edit</a>
      <form action="{{ route('evaluations.destroy', $evaluation) }}" method="POST" onsubmit="return confirm('Hapus evaluasi ini?')">
        @csrf @method('DELETE')
        <button class="rounded-xl px-5 py-2.5 text-[12.5px] font-bold text-red-500 hover:bg-red-50 transition">Hapus</button>
      </form>
    @endcan
  </div>
</div>
@endsection

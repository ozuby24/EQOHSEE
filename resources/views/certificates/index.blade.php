@extends('layouts.app')
@section('title','Sertifikat')

@section('content')
<div class="max-w-4xl mx-auto">
  @if(session('ok'))
    <div class="mb-5 rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>
  @endif

  @if($errors->any())
    <div class="mb-5 rounded-xl bg-amber-50 border border-amber-100 text-amber-800 px-4 py-3 text-[12.5px]">
      @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
  @endif

  @if($menungguEvaluasi->count())
    <div class="bg-white rounded-2xl shadow-card border border-amber-200 p-5 mb-5">
      <div class="flex items-start gap-3">
        <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 grid place-items-center shrink-0">
          <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div class="min-w-0">
          <h3 class="text-[13px] font-bold text-cam-ink">Menunggu evaluasi trainer</h3>
          <p class="text-[11.5px] text-stone-500 mt-0.5 leading-relaxed">
            Kursus berikut sudah kamu selesaikan. Sertifikat terbit otomatis setelah trainer menyelesaikan penilaian.
          </p>
          <ul class="mt-2.5 space-y-1.5">
            @foreach($menungguEvaluasi as $en)
              <li class="text-[12.5px] font-semibold text-stone-600 bg-stone-50 rounded-lg px-3 py-2">{{ $en->course->title }}</li>
            @endforeach
          </ul>
        </div>
      </div>
    </div>
  @endif

  @if($claimable->count())
    <div class="bg-cam-lime-soft border border-cam-lime/25 rounded-2xl p-5 mb-5">
      <h3 class="text-[13px] font-bold text-cam-lime-deep mb-3">🎓 Siap diterbitkan</h3>
      @foreach($claimable as $en)
        <form action="{{ route('certificates.store', $en->course) }}" method="POST"
              class="flex items-center justify-between gap-3 bg-white rounded-xl px-4 py-3 mb-2 last:mb-0">
          @csrf
          <span class="text-[13px] font-semibold text-cam-ink">{{ $en->course->title }}</span>
          <button class="lime-gradient rounded-lg text-white px-3.5 py-1.5 text-[11.5px] font-bold hover:brightness-105 transition">Terbitkan</button>
        </form>
      @endforeach
    </div>
  @endif

  <div class="space-y-2.5">
    @forelse($certificates as $c)
      <a href="{{ route('certificates.show', $c) }}"
         class="block bg-white rounded-2xl shadow-card border border-stone-100 p-5 hover:border-cam-lime/40 card-hover transition">
        <div class="flex items-center justify-between gap-4">
          <div class="min-w-0">
            <div class="text-[14px] font-bold text-cam-ink clamp-1">{{ $c->course_title }}</div>
            <div class="text-[11px] text-stone-400 mt-0.5">{{ $c->certificate_number }} · {{ optional($c->issued_at)->format('d M Y') }}</div>
            @if(auth()->user()->isAdmin())
              <div class="text-[11px] text-stone-400 mt-0.5">{{ $c->recipient_name }}</div>
            @endif
          </div>
          <span class="text-[22px]">🎓</span>
        </div>
      </a>
    @empty
      <div class="bg-white rounded-2xl border border-dashed border-stone-200 p-14 text-center">
        <p class="text-[13px] text-stone-400">Belum ada sertifikat.</p>
        <p class="text-[12px] text-stone-300 mt-1">Selesaikan kursus untuk mendapatkannya.</p>
      </div>
    @endforelse
  </div>
</div>
@endsection

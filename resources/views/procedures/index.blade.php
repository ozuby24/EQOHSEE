@extends('layouts.app')
@section('title','Prosedur & SOP')

@section('content')
<div class="max-w-5xl mx-auto">
  @if(session('ok'))
    <div class="mb-5 rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>
  @endif

  <div class="flex flex-wrap items-center gap-2.5 mb-5">
    <form method="GET" class="flex-1 min-w-0 basis-[200px]">
      <input name="q" value="{{ $q }}" placeholder="Cari prosedur atau kode..."
             class="ring-focus w-full rounded-xl border border-stone-200 bg-white px-4 py-2.5 text-[13px] transition">
    </form>
    @can('admin')
      <a href="{{ route('procedures.create') }}"
         class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">+ Tambah Prosedur</a>
    @endcan
  </div>

  <div class="space-y-2.5">
    @forelse($procedures as $p)
      <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 hover:border-cam-lime/30 transition">
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              @if($p->code)<span class="text-[10px] font-bold bg-cam-ink text-white px-2 py-0.5 rounded tracking-wide">{{ $p->code }}</span>@endif
              @if($p->category)<span class="text-[10px] font-semibold bg-cam-lime-soft text-cam-lime-deep px-2 py-0.5 rounded-full">{{ $p->category }}</span>@endif
            </div>
            <h3 class="text-[14px] font-bold text-cam-ink mt-2">{{ $p->title }}</h3>
            @if($p->description)<p class="text-[12.5px] text-stone-400 mt-1 leading-relaxed">{{ $p->description }}</p>@endif
          </div>
          <div class="flex items-center gap-1 shrink-0">
            @if($p->url)<a href="{{ $p->url }}" target="_blank" rel="noopener" class="px-3 py-1.5 text-[11.5px] font-semibold rounded-lg text-stone-500 hover:bg-stone-50">Buka</a>@endif
            @can('admin')
              <a href="{{ route('procedures.edit', $p) }}" class="px-3 py-1.5 text-[11.5px] font-semibold rounded-lg text-cam-lime-deep hover:bg-cam-lime-soft">Edit</a>
              <form action="{{ route('procedures.destroy', $p) }}" method="POST" onsubmit="return confirm('Hapus prosedur ini?')">
                @csrf @method('DELETE')
                <button class="px-3 py-1.5 text-[11.5px] font-semibold rounded-lg text-red-500 hover:bg-red-50">Hapus</button>
              </form>
            @endcan
          </div>
        </div>
      </div>
    @empty
      <div class="bg-white rounded-2xl border border-dashed border-stone-200 p-14 text-center text-[13px] text-stone-400">Belum ada prosedur.</div>
    @endforelse
  </div>

  <div class="mt-6">{{ $procedures->links() }}</div>
</div>
@endsection

@extends('layouts.app')
@section('title','Jenis Inspeksi')

@section('content')
<div class="max-w-5xl mx-auto space-y-5">
  @if(session('ok'))<div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>@endif

  <div class="glass-light rounded-xl border border-stone-200/60 px-4 py-3 flex flex-wrap items-center justify-between gap-3">
    <p class="text-[12px] text-stone-500 leading-relaxed max-w-xl">
      Tentukan <b>jenis inspeksi</b> beserta <b>parameter</b> yang diperiksa. Saat inspeksi dijalankan,
      parameter ini otomatis tersalin menjadi daftar periksa yang tinggal diisi.
    </p>
    <a href="{{ route('inspeksi.template.create') }}" class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition shrink-0">+ Jenis Inspeksi</a>
  </div>

  <div class="grid gap-3 sm:grid-cols-2">
    @forelse($templates as $t)
      <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 card-hover">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              @if($t->jenis)<span class="text-[10px] font-bold bg-cam-ink text-white px-2 py-0.5 rounded">{{ $t->jenis }}</span>@endif
              @if($t->kategori)<span class="text-[10px] font-semibold bg-stone-100 text-stone-500 px-2 py-0.5 rounded-full">{{ $t->kategori }}</span>@endif
              @if(!$t->is_active)<span class="text-[10px] font-bold bg-stone-100 text-stone-400 px-2 py-0.5 rounded">Nonaktif</span>@endif
            </div>
            <h3 class="text-[14px] font-bold text-cam-ink mt-2">{{ $t->nama }}</h3>
            @if($t->deskripsi)<p class="text-[12px] text-stone-400 mt-1 clamp-2 leading-relaxed">{{ $t->deskripsi }}</p>@endif
          </div>
        </div>
        <div class="flex items-center justify-between mt-4 pt-3 border-t border-stone-100">
          <span class="text-[11px] text-stone-400"><span class="num font-semibold text-stone-600">{{ $t->items_count }}</span> parameter · <span class="num">{{ $t->inspections_count }}</span> pelaksanaan</span>
          <div class="flex gap-1">
            <a href="{{ route('inspeksi.template.edit', $t) }}" class="px-3 py-1.5 text-[11.5px] font-semibold rounded-lg text-cam-lime-deep hover:bg-cam-lime-soft">Kelola</a>
            <form action="{{ route('inspeksi.template.destroy', $t) }}" method="POST" onsubmit="return confirm('Hapus jenis inspeksi ini?')">
              @csrf @method('DELETE')
              <button class="px-3 py-1.5 text-[11.5px] font-semibold rounded-lg text-red-500 hover:bg-red-50">Hapus</button>
            </form>
          </div>
        </div>
      </div>
    @empty
      <div class="sm:col-span-2 bg-white rounded-2xl border border-dashed border-stone-200 p-14 text-center">
        <p class="text-[13px] text-stone-400">Belum ada jenis inspeksi.</p>
        <a href="{{ route('inspeksi.template.create') }}" class="inline-block mt-3 text-[12.5px] font-bold text-cam-lime-deep hover:underline">Buat jenis pertama →</a>
      </div>
    @endforelse
  </div>
</div>
@endsection

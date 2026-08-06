@extends('layouts.app')
@section('title','Berita')

@section('content')
<div class="max-w-3xl mx-auto">
  @if(session('ok'))
    <div class="mb-5 rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>
  @endif

  @can('admin')
    <div class="flex justify-end mb-5">
      <a href="{{ route('news.create') }}"
         class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">+ Tulis Berita</a>
    </div>
  @endcan

  <div class="space-y-3">
    @forelse($items as $item)
      <article class="bg-white rounded-2xl shadow-card border border-stone-100 p-6 hover:border-cam-lime/30 transition">
        <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-cam-lime-dark">{{ optional($item->published_at)->format('d M Y') }}</div>
        <h2 class="text-[17px] font-bold text-cam-ink mt-1.5 leading-snug">
          <a href="{{ route('news.show', $item) }}" class="hover:text-cam-lime-deep transition">{{ $item->title }}</a>
        </h2>
        <p class="text-[12.5px] text-stone-400 mt-2 clamp-3 leading-relaxed">{{ \Illuminate\Support\Str::limit(strip_tags($item->content), 220) }}</p>
        @can('admin')
          <div class="flex gap-1 mt-3.5 pt-3 border-t border-stone-100">
            <a href="{{ route('news.edit', $item) }}" class="px-3 py-1.5 text-[11.5px] font-semibold rounded-lg text-cam-lime-deep hover:bg-cam-lime-soft">Edit</a>
            <form action="{{ route('news.destroy', $item) }}" method="POST" onsubmit="return confirm('Hapus berita ini?')">
              @csrf @method('DELETE')
              <button class="px-3 py-1.5 text-[11.5px] font-semibold rounded-lg text-red-500 hover:bg-red-50">Hapus</button>
            </form>
          </div>
        @endcan
      </article>
    @empty
      <div class="bg-white rounded-2xl border border-dashed border-stone-200 p-14 text-center text-[13px] text-stone-400">Belum ada berita.</div>
    @endforelse
  </div>

  <div class="mt-6">{{ $items->links() }}</div>
</div>
@endsection

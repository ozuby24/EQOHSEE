@extends('layouts.app')
@section('title', $item->exists ? 'Edit Berita' : 'Tulis Berita')

@section('content')
<div class="max-w-2xl mx-auto">
  <form action="{{ $item->exists ? route('news.update',$item) : route('news.store') }}" method="POST"
        class="bg-white rounded-2xl shadow-card border border-stone-100 p-6 space-y-5">
    @csrf
    @if($item->exists) @method('PUT') @endif

    @if($errors->any())
      <div class="rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]">
        <ul class="space-y-0.5">@foreach($errors->all() as $e)<li>• {{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    <div>
      <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Judul</label>
      <input name="title" value="{{ old('title',$item->title) }}" required
             class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
    </div>
    <div>
      <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Tanggal terbit</label>
      <input type="date" name="published_at" value="{{ old('published_at', optional($item->published_at)->format('Y-m-d') ?: date('Y-m-d')) }}"
             class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
    </div>
    <div>
      <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Isi</label>
      <textarea name="content" rows="10"
                class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] leading-relaxed transition">{{ old('content',$item->content) }}</textarea>
    </div>

    <div class="flex items-center gap-3 pt-1">
      <button class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">Simpan</button>
      <a href="{{ route('news.index') }}" class="text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink">Batal</a>
    </div>
  </form>
</div>
@endsection

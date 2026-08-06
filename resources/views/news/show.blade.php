@extends('layouts.app')
@section('title', $item->title)

@section('content')
<article class="max-w-3xl mx-auto bg-white rounded-2xl shadow-card border border-stone-100 p-7 md:p-9">
  <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-cam-lime-dark">{{ optional($item->published_at)->format('d F Y') }}</div>
  <h1 class="font-display text-[28px] md:text-[32px] font-black text-cam-ink mt-2.5 leading-tight">{{ $item->title }}</h1>
  <div class="text-[13.5px] text-stone-600 leading-[1.75] mt-5 whitespace-pre-line">{{ $item->content }}</div>
  <a href="{{ route('news.index') }}" class="inline-block mt-8 text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink">← Semua berita</a>
</article>
@endsection

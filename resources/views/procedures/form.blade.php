@extends('layouts.app')
@section('title', $procedure->exists ? 'Edit Prosedur' : 'Prosedur Baru')

@section('content')
<div class="max-w-2xl mx-auto">
  <form action="{{ $procedure->exists ? route('procedures.update',$procedure) : route('procedures.store') }}" method="POST"
        class="bg-white rounded-2xl shadow-card border border-stone-100 p-6 space-y-5">
    @csrf
    @if($procedure->exists) @method('PUT') @endif

    @if($errors->any())
      <div class="rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]">
        <ul class="space-y-0.5">@foreach($errors->all() as $e)<li>• {{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    <div class="grid sm:grid-cols-2 gap-4">
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Kode</label>
        <input name="code" value="{{ old('code',$procedure->code) }}" placeholder="SOP-001"
               class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
      </div>
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Kategori</label>
        <input name="category" value="{{ old('category',$procedure->category) }}"
               class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
      </div>
    </div>
    <div>
      <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Judul</label>
      <input name="title" value="{{ old('title',$procedure->title) }}" required
             class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
    </div>
    <div>
      <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Deskripsi</label>
      <textarea name="description" rows="3"
                class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] leading-relaxed transition">{{ old('description',$procedure->description) }}</textarea>
    </div>
    <div class="grid sm:grid-cols-2 gap-4">
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Tautan dokumen</label>
        <input name="url" value="{{ old('url',$procedure->url) }}" placeholder="https://..."
               class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
      </div>
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Urutan</label>
        <input type="number" name="position" min="1" value="{{ old('position',$procedure->position ?: 1) }}"
               class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
      </div>
    </div>

    <div class="flex items-center gap-3 pt-1">
      <button class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">Simpan</button>
      <a href="{{ route('procedures.index') }}" class="text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink">Batal</a>
    </div>
  </form>
</div>
@endsection

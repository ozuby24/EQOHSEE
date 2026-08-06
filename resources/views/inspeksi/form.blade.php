@extends('layouts.app')
@section('title', $inspection->exists ? 'Ubah Inspeksi' : 'Inspeksi Baru')

@section('content')
@php use App\Support\Hazard; @endphp
<div class="max-w-2xl mx-auto">
  <form action="{{ $inspection->exists ? route('inspeksi.update',$inspection) : route('inspeksi.store') }}" method="POST"
        class="bg-white rounded-2xl shadow-card border border-stone-100 p-6 space-y-4">
    @csrf
    @if($inspection->exists) @method('PUT') @endif

    @if($errors->any())
      <div class="rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]">
        @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
      </div>
    @endif

    @unless($inspection->exists)
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Jenis inspeksi</label>
        <select name="template_id" required class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
          <option value="">— pilih jenis inspeksi —</option>
          @foreach($templates as $t)
            <option value="{{ $t->id }}" @selected(old('template_id')==$t->id)>{{ $t->nama }} ({{ $t->items_count }} parameter)</option>
          @endforeach
        </select>
        <p class="text-[11px] text-stone-400 mt-1">Parameter dari jenis ini otomatis tersalin menjadi daftar periksa.</p>
        @if($templates->isEmpty())
          <p class="text-[11.5px] text-amber-600 mt-1.5">Belum ada jenis inspeksi aktif. <a href="{{ route('inspeksi.template.create') }}" class="font-bold hover:underline">Buat dulu →</a></p>
        @endif
      </div>
    @endunless

    <div>
      <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Judul inspeksi</label>
      <input name="judul" value="{{ old('judul',$inspection->judul) }}" required placeholder="Inspeksi APAR Area Workshop — Juli 2026"
             class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
    </div>

    <div class="grid sm:grid-cols-2 gap-3">
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Tanggal</label>
        <input type="date" name="tanggal" value="{{ old('tanggal', optional($inspection->tanggal)->format('Y-m-d') ?: date('Y-m-d')) }}" required
               class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
      </div>
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Frekuensi</label>
        <select name="jenis" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
          <option value="">— pilih —</option>
          @foreach(Hazard::JENIS_INSPEKSI as $j)<option value="{{ $j }}" @selected(old('jenis',$inspection->jenis)===$j)>{{ $j }}</option>@endforeach
        </select>
      </div>
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Perusahaan</label>
        <select name="company_id" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
          <option value="">— pilih —</option>
          @foreach($companies as $c)<option value="{{ $c->id }}" @selected(old('company_id',$inspection->company_id)==$c->id)>{{ $c->name }}</option>@endforeach
        </select>
      </div>
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Lokasi</label>
        <input name="lokasi" value="{{ old('lokasi',$inspection->lokasi) }}" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
      </div>
    </div>

    @if($inspection->exists)
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Status</label>
        <select name="status" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
          <option value="Berjalan" @selected($inspection->status==='Berjalan')>Berjalan</option>
          <option value="Selesai"  @selected($inspection->status==='Selesai')>Selesai</option>
        </select>
      </div>
    @endif

    <div>
      <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Catatan</label>
      <textarea name="catatan" rows="2" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">{{ old('catatan',$inspection->catatan) }}</textarea>
    </div>

    <div class="flex gap-2.5 pt-1">
      <button class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">Simpan</button>
      <a href="{{ route('inspeksi.index') }}" class="text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink self-center">Batal</a>
    </div>
  </form>
</div>
@endsection

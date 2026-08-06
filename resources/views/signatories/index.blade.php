@extends('layouts.app')
@section('title','Penanda Tangan Sertifikat')

@section('content')
<div class="max-w-3xl mx-auto space-y-5">
  @if(session('ok'))
    <div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>
  @endif

  <p class="text-[12.5px] text-stone-400">Nama yang tercetak pada sertifikat. Yang berstatus <span class="font-semibold text-cam-lime-deep">aktif</span> dipakai otomatis saat sertifikat diterbitkan.</p>

  <div class="space-y-2.5">
    @forelse($items as $s)
      <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
        <form action="{{ route('signatories.update',$s) }}" method="POST" enctype="multipart/form-data" class="grid sm:grid-cols-[1fr_1fr_auto] gap-3 items-end">
          @csrf @method('PUT')
          <div>
            <label class="block text-[10.5px] font-bold uppercase tracking-wide text-stone-500 mb-1">Nama</label>
            <input name="name" value="{{ $s->name }}" required class="ring-focus w-full rounded-lg border border-stone-200 px-3 py-2 text-[12.5px]">
          </div>
          <div>
            <label class="block text-[10.5px] font-bold uppercase tracking-wide text-stone-500 mb-1">Jabatan</label>
            <input name="title" value="{{ $s->title }}" class="ring-focus w-full rounded-lg border border-stone-200 px-3 py-2 text-[12.5px]">
          </div>
          <div class="flex items-center gap-2">
            <label class="flex items-center gap-1.5 text-[12px] text-stone-600">
              <input type="hidden" name="is_active" value="0">
              <input type="checkbox" name="is_active" value="1" @checked($s->is_active) class="rounded border-stone-300 text-cam-lime-dark focus:ring-cam-lime/40">
              Aktif
            </label>
            <button class="lime-gradient rounded-lg text-white px-3 py-2 text-[11.5px] font-bold hover:brightness-105">Simpan</button>
          </div>
          <div class="sm:col-span-3 flex items-center justify-between gap-3 pt-2 border-t border-stone-100">
            <div class="flex items-center gap-3">
              @if($s->signature)<img src="{{ asset('storage/'.$s->signature) }}" class="h-9">@endif
              <input type="file" name="signature" accept="image/*" class="text-[11.5px] text-stone-500">
            </div>
          </div>
        </form>
        <form action="{{ route('signatories.destroy',$s) }}" method="POST" onsubmit="return confirm('Hapus penanda tangan ini?')" class="mt-2">
          @csrf @method('DELETE')
          <button class="text-[11.5px] font-semibold text-red-500 hover:bg-red-50 px-2.5 py-1 rounded-lg">Hapus</button>
        </form>
      </div>
    @empty
      <div class="bg-white rounded-2xl border border-dashed border-stone-200 p-12 text-center text-[13px] text-stone-400">Belum ada penanda tangan.</div>
    @endforelse
  </div>

  <form action="{{ route('signatories.store') }}" method="POST" enctype="multipart/form-data"
        class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 grid sm:grid-cols-2 gap-3">
    @csrf
    <div class="sm:col-span-2 text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">Tambah Baru</div>
    <input name="name" placeholder="Nama lengkap" required class="ring-focus rounded-lg border border-stone-200 px-3 py-2 text-[12.5px]">
    <input name="title" placeholder="Jabatan" class="ring-focus rounded-lg border border-stone-200 px-3 py-2 text-[12.5px]">
    <input type="file" name="signature" accept="image/*" class="text-[11.5px] text-stone-500 sm:col-span-2">
    <label class="flex items-center gap-1.5 text-[12px] text-stone-600">
      <input type="hidden" name="is_active" value="0">
      <input type="checkbox" name="is_active" value="1" checked class="rounded border-stone-300 text-cam-lime-dark focus:ring-cam-lime/40"> Aktif
    </label>
    <button class="lime-gradient shadow-glow rounded-xl text-white py-2.5 text-[12.5px] font-bold hover:brightness-105">+ Tambah</button>
  </form>
</div>
@endsection

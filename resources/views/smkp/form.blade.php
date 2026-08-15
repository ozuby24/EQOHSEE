@extends('layouts.app')
@section('title', $audit->exists ? 'Ubah Periode Audit' : 'Periode Audit Baru')

@section('content')
<div class="max-w-2xl mx-auto space-y-5">
  @if($errors->any())
    <div class="rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]">
      <ul class="space-y-0.5">@foreach($errors->all() as $e)<li>• {{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ $audit->exists ? route('smkp.update',$audit) : route('smkp.store') }}"
        class="bg-white rounded-2xl shadow-card border border-stone-100 p-6 space-y-4">
    @csrf
    @if($audit->exists) @method('PUT') @endif

    <div>
      <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Judul audit</label>
      <input name="judul" value="{{ old('judul', $audit->judul) }}" placeholder="Audit Internal SMKP {{ old('tahun', $audit->tahun) }}"
             class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-3 text-[13.5px] transition">
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Tahun <span class="text-red-500">*</span></label>
        <input type="number" name="tahun" value="{{ old('tahun', $audit->tahun) }}" required min="2000" max="2100"
               class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-3 text-[13.5px] transition">
      </div>
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Status <span class="text-red-500">*</span></label>
        <select name="status" required class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-3 text-[13.5px] transition">
          @foreach(['draft'=>'Draft','berjalan'=>'Berjalan','selesai'=>'Selesai'] as $v=>$l)
            <option value="{{ $v }}" @selected(old('status',$audit->status ?: 'draft')===$v)>{{ $l }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div>
      <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Perusahaan</label>
      <select name="company_id" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-3 text-[13.5px] transition">
        <option value="">— seluruh perusahaan —</option>
        @foreach($companies as $c)
          <option value="{{ $c->id }}" @selected(old('company_id',$audit->company_id)==$c->id)>{{ $c->name }}</option>
        @endforeach
      </select>
      <p class="text-[11px] text-stone-400 mt-1">Satu periode audit per perusahaan per tahun.</p>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Tanggal mulai</label>
        <input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai', $audit->tanggal_mulai?->format('Y-m-d')) }}"
               class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-3 text-[13.5px] transition">
      </div>
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Tanggal selesai</label>
        <input type="date" name="tanggal_selesai" value="{{ old('tanggal_selesai', $audit->tanggal_selesai?->format('Y-m-d')) }}"
               class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-3 text-[13.5px] transition">
      </div>
    </div>

    <div>
      <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Ketua auditor</label>
      <input name="ketua_auditor" value="{{ old('ketua_auditor', $audit->ketua_auditor) }}"
             class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-3 text-[13.5px] transition">
    </div>

    <div class="flex flex-wrap gap-2 pt-2">
      <button class="lime-gradient shadow-glow rounded-xl text-white px-5 py-3 text-[13px] font-bold hover:brightness-105 transition">
        {{ $audit->exists ? 'Simpan Perubahan' : 'Buat Periode Audit' }}
      </button>
      <a href="{{ $audit->exists ? route('smkp.show',$audit) : route('smkp.index') }}"
         class="rounded-xl border border-stone-200 px-5 py-3 text-[13px] font-bold text-stone-500 hover:bg-stone-50 transition">Batal</a>
    </div>
  </form>
</div>
@endsection

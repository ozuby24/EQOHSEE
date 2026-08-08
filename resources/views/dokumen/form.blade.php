@extends('layouts.app')
@section('title', $document->exists ? 'Ubah Dokumen' : 'Dokumen Baru')

@section('content')
@php use App\Support\Dokumen; @endphp
<div class="max-w-2xl mx-auto space-y-5">
  @if($errors->any())
    <div class="rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]">
      <ul class="space-y-0.5">@foreach($errors->all() as $e)<li>• {{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" enctype="multipart/form-data"
        action="{{ $document->exists ? route('dokumen.update',$document) : route('dokumen.store') }}"
        class="bg-white rounded-2xl shadow-card border border-stone-100 p-6 space-y-4">
    @csrf
    @if($document->exists) @method('PUT') @endif

    <div class="grid gap-3 sm:grid-cols-3">
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Kode <span class="text-red-500">*</span></label>
        <input name="kode" value="{{ old('kode',$document->kode) }}" required placeholder="SOP-K3-001"
               class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-3 text-[13.5px] transition">
      </div>
      <div class="sm:col-span-2">
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Judul <span class="text-red-500">*</span></label>
        <input name="judul" value="{{ old('judul',$document->judul) }}" required
               class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-3 text-[13.5px] transition">
      </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Jenis <span class="text-red-500">*</span></label>
        <select name="jenis" required class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-3 text-[13.5px] transition">
          @foreach(Dokumen::JENIS as $j)<option value="{{ $j }}" @selected(old('jenis',$document->jenis)===$j)>{{ $j }}</option>@endforeach
        </select>
      </div>
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Status <span class="text-red-500">*</span></label>
        <select name="status" required class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-3 text-[13.5px] transition">
          @foreach(Dokumen::STATUS as $s)<option value="{{ $s }}" @selected(old('status',$document->status)===$s)>{{ ucfirst($s) }}</option>@endforeach
        </select>
      </div>
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Revisi</label>
        <input type="number" name="revisi" min="0" max="999" value="{{ old('revisi',$document->revisi ?? 0) }}"
               class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-3 text-[13.5px] transition">
      </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Departemen pemilik</label>
        <input name="departemen" value="{{ old('departemen',$document->departemen) }}"
               class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-3 text-[13.5px] transition">
      </div>
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Klasifikasi</label>
        <select name="klasifikasi" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-3 text-[13.5px] transition">
          <option value="">—</option>
          @foreach(Dokumen::KLASIFIKASI as $k)<option value="{{ $k }}" @selected(old('klasifikasi',$document->klasifikasi)===$k)>{{ $k }}</option>@endforeach
        </select>
      </div>
    </div>

    <div>
      <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Perusahaan</label>
      <select name="company_id" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-3 text-[13.5px] transition">
        <option value="">— seluruh perusahaan —</option>
        @foreach($companies as $c)<option value="{{ $c->id }}" @selected(old('company_id',$document->company_id)==$c->id)>{{ $c->name }}</option>@endforeach
      </select>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
      @foreach ([
        ['tanggal_terbit','Tanggal terbit'],
        ['tanggal_berlaku','Mulai berlaku'],
        ['tanggal_tinjau','Jatuh tempo tinjau'],
      ] as [$nama,$label])
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">{{ $label }}</label>
          <input type="date" name="{{ $nama }}" value="{{ old($nama, $document->$nama?->format('Y-m-d')) }}"
                 class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-3 text-[13.5px] transition">
        </div>
      @endforeach
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Acuan klausul</label>
        <input name="acuan" value="{{ old('acuan',$document->acuan) }}" placeholder="ISO 45001 klausul 7.5 / SMKP elemen VI"
               class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-3 text-[13.5px] transition">
      </div>
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Disetujui oleh</label>
        <input name="disetujui_oleh" value="{{ old('disetujui_oleh',$document->disetujui_oleh) }}"
               class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-3 text-[13.5px] transition">
      </div>
    </div>

    <div>
      <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Ringkasan</label>
      <textarea name="ringkasan" rows="3"
                class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-3 text-[13.5px] transition">{{ old('ringkasan',$document->ringkasan) }}</textarea>
    </div>

    <div>
      <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Berkas dokumen</label>
      <input type="file" name="berkas"
             class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-3 text-[12.5px] transition file:mr-3 file:rounded-lg file:border-0 file:bg-cam-lime-soft file:px-3 file:py-1.5 file:text-[12px] file:font-bold file:text-cam-lime-deep">
      @if($document->berkas)
        <p class="text-[11px] text-stone-400 mt-1">Berkas saat ini tersimpan. Unggah baru untuk menggantinya.</p>
      @endif
    </div>

    <div class="flex flex-wrap gap-2 pt-2">
      <button class="lime-gradient shadow-glow rounded-xl text-white px-5 py-3 text-[13px] font-bold hover:brightness-105 transition">
        {{ $document->exists ? 'Simpan Perubahan' : 'Daftarkan Dokumen' }}
      </button>
      <a href="{{ $document->exists ? route('dokumen.show',$document) : route('dokumen.index') }}"
         class="rounded-xl border border-stone-200 px-5 py-3 text-[13px] font-bold text-stone-500 hover:bg-stone-50 transition">Batal</a>
    </div>
  </form>
</div>
@endsection

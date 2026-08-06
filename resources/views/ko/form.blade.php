@extends('layouts.app')
@section('title', $o->exists ? 'KO/SPIP — Edit Objek' : 'KO/SPIP — Objek Baru')

@section('content')
@php $K = \App\Support\Ko::class; $edit = $o->exists; @endphp
<div class="max-w-3xl mx-auto space-y-5">
  @include('ko._flash')
  @include('ko._picker')

  <form method="POST" action="{{ $edit ? route('ko.update', $o) : route('ko.store') }}"
        class="bg-white rounded-2xl border border-stone-200 p-6 space-y-4">
    @csrf
    @if($edit) @method('PUT') @endif

    <h3 class="text-[14px] font-bold text-cam-ink">{{ $edit ? 'Edit Objek '.$o->kode : 'Daftarkan Objek SPIP Baru' }}</h3>

    <div class="grid sm:grid-cols-2 gap-3">
      @php
        $teks = [
          ['kode','No. Register *','cth. EX-04'], ['nama','Nama Objek *','cth. Excavator PC400-8'],
          ['jenis','Jenis','cth. Dump Truck'], ['merk','Merk / Tipe','cth. Komatsu HD785-7'],
          ['serial_number','Serial Number','cth. HD785-7099'], ['lokasi','Lokasi','cth. Pit Selatan'],
          ['no_sertifikat','No. Sertifikat','cth. KO/PER/26/001'], ['lembaga_uji','Lembaga Penguji','cth. PJIT Eksternal'],
          ['pm_jenis','Jenis Perawatan (PM)','cth. Preventive 500HM'],
        ];
      @endphp

      <div>
        <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">No. Register *</label>
        <input name="kode" value="{{ old('kode', $o->kode) }}" required @readonly($edit)
               class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px] {{ $edit ? 'bg-stone-50 text-stone-500' : '' }}">
      </div>
      <div>
        <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Nama Objek *</label>
        <input name="nama" value="{{ old('nama', $o->nama) }}" required
               class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
      </div>

      <div>
        <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Kategori *</label>
        <select name="kategori" class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px] font-semibold">
          @foreach($K::KATEGORI as $v)<option value="{{ $v }}" @selected(old('kategori', $o->kategori ?: 'Peralatan') === $v)>{{ $v }}</option>@endforeach
        </select>
      </div>
      <div>
        <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Perusahaan</label>
        <select name="company_id" class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
          <option value="">— tidak ditentukan —</option>
          @foreach($perusahaan as $co)
            <option value="{{ $co->id }}" @selected(old('company_id', $o->company_id) == $co->id)>{{ $co->name }}</option>
          @endforeach
        </select>
      </div>

      @foreach(array_slice($teks, 2) as $f)
        <div>
          <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">{{ $f[1] }}</label>
          <input name="{{ $f[0] }}" value="{{ old($f[0], $o->{$f[0]}) }}" placeholder="{{ $f[2] }}"
                 class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
        </div>
      @endforeach

      <div>
        <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Kritikalitas *</label>
        <select name="kritikalitas" class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px] font-semibold">
          @foreach($K::KRITIS as $v)<option value="{{ $v }}" @selected(old('kritikalitas', $o->kritikalitas ?: 'Sedang') === $v)>{{ $v }}</option>@endforeach
        </select>
      </div>
      <div>
        <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Status Operasi *</label>
        <select name="status_operasi" class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px] font-semibold">
          @foreach($K::OPERASI as $v)<option value="{{ $v }}" @selected(old('status_operasi', $o->status_operasi ?: 'Aktif') === $v)>{{ $v }}</option>@endforeach
        </select>
      </div>

      <div>
        <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Tgl Sertifikasi</label>
        <input type="date" name="tgl_sertifikasi" value="{{ old('tgl_sertifikasi', $o->tgl_sertifikasi?->format('Y-m-d')) }}"
               class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
      </div>
      <div>
        <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Interval (tahun) *</label>
        <select name="interval_tahun" class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px] font-semibold">
          @foreach($K::INTERVAL as $v)<option value="{{ $v }}" @selected((int) old('interval_tahun', $o->interval_tahun ?: 3) === $v)>{{ $v }}</option>@endforeach
        </select>
        <p class="text-[10.5px] text-stone-400 mt-1">Bawaan: peralatan {{ $set['ko_iv_peralatan'] }} th · instalasi {{ $set['ko_iv_instalasi'] }} th</p>
      </div>

      <div>
        <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">PM Terakhir</label>
        <input type="date" name="pm_terakhir" value="{{ old('pm_terakhir', $o->pm_terakhir?->format('Y-m-d')) }}"
               class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
      </div>
      <div>
        <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">PM Berikutnya</label>
        <input type="date" name="pm_berikutnya" value="{{ old('pm_berikutnya', $o->pm_berikutnya?->format('Y-m-d')) }}"
               class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
      </div>
    </div>

    <label class="flex items-center gap-2.5 text-[12.5px] text-stone-600">
      <input type="checkbox" name="lapor_kait" value="1" @checked(old('lapor_kait', $o->lapor_kait)) class="w-4 h-4 accent-cam-lime-deep">
      Sudah dilaporkan ke KaIT (≤ 14 hari sejak sertifikasi)
    </label>

    <div>
      <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Keterangan</label>
      <textarea name="keterangan" rows="2" class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">{{ old('keterangan', $o->keterangan) }}</textarea>
    </div>

    <div class="flex flex-wrap items-center gap-2 pt-1">
      <button class="lime-gradient shadow-glow rounded-xl text-white px-6 py-2.5 text-[13px] font-bold hover:brightness-105 transition">
        {{ $edit ? 'Simpan perubahan' : 'Daftarkan objek' }}
      </button>
      <a href="{{ route('ko.register') }}" class="rounded-xl border border-stone-200 px-5 py-2.5 text-[13px] font-semibold text-stone-600">Batal</a>
    </div>
  </form>
</div>
@endsection

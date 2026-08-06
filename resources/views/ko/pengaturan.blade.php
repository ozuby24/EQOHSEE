@extends('layouts.app')
@section('title','KO/SPIP — Pengaturan')

@section('content')
@php $K = \App\Support\Ko::class; @endphp
<div class="max-w-2xl mx-auto space-y-5">
  @include('ko._flash')
  @include('ko._picker')

  <form method="POST" action="{{ route('ko.pengaturan.simpan') }}" class="bg-white rounded-2xl border border-stone-200 p-6 space-y-4">
    @csrf
    <h3 class="text-[14px] font-bold text-cam-ink">Ambang &amp; Target KO</h3>
    <p class="text-[11.5px] text-stone-500">Ambang ini mengubah status kelayakan seluruh objek secara langsung.</p>

    <div class="grid sm:grid-cols-2 gap-3">
      <div>
        <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Ambang jatuh tempo (hari)</label>
        <input type="number" name="ko_warn_days" min="1" max="365" value="{{ $set['ko_warn_days'] }}"
               class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px] num">
      </div>
      <div>
        <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Target objek layak (%)</label>
        <input type="number" name="ko_target_layak" min="1" max="100" value="{{ $set['ko_target_layak'] }}"
               class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px] num">
      </div>
      <div>
        <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Target PM Compliance (%)</label>
        <input type="number" name="ko_target_pmc" min="1" max="100" value="{{ $set['ko_target_pmc'] }}"
               class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px] num">
      </div>
      <div>
        <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Interval bawaan peralatan (tahun)</label>
        <select name="ko_iv_peralatan" class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px] font-semibold">
          @foreach($K::INTERVAL as $v)<option value="{{ $v }}" @selected($set['ko_iv_peralatan'] === $v)>{{ $v }}</option>@endforeach
        </select>
      </div>
      <div>
        <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Interval bawaan instalasi (tahun)</label>
        <select name="ko_iv_instalasi" class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px] font-semibold">
          @foreach($K::INTERVAL as $v)<option value="{{ $v }}" @selected($set['ko_iv_instalasi'] === $v)>{{ $v }}</option>@endforeach
        </select>
      </div>
    </div>

    <button class="lime-gradient shadow-glow rounded-xl text-white px-6 py-2.5 text-[13px] font-bold hover:brightness-105 transition">Simpan pengaturan</button>
  </form>

  <div class="bg-white rounded-2xl border border-stone-200 p-5">
    <h3 class="text-[13px] font-bold text-cam-ink mb-2">Peran &amp; akses</h3>
    <ul class="text-[12px] text-stone-600 space-y-1 list-disc pl-4">
      <li><b>Admin EQOHSEE</b> — semua perusahaan, boleh hapus, ubah pengaturan.</li>
      <li><b>ko_role = pengawas</b> — boleh menambah dan mengubah data, tidak boleh menghapus.</li>
      <li><b>Lainnya</b> — hanya membaca, terbatas pada perusahaannya sendiri.</li>
    </ul>
    <p class="text-[11.5px] text-stone-500 mt-3">
      Peran diatur di Kelola Pengguna. Jejak perubahan masuk ke log aktivitas modul <span class="num">ko</span>.
    </p>
  </div>
</div>
@endsection

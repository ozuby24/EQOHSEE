@extends('layouts.app')
@section('title','KO/SPIP — Register')

@section('content')
@php $K = \App\Support\Ko::class; @endphp
<div class="max-w-6xl mx-auto space-y-5">
  @include('ko._flash')
  @include('ko._picker')

  <form method="GET" class="bg-white rounded-2xl border border-stone-200 p-4 flex flex-wrap items-center gap-2">
    @if(request('perusahaan'))<input type="hidden" name="perusahaan" value="{{ request('perusahaan') }}">@endif
    <input name="q" value="{{ $q }}" placeholder="Cari kode, nama, jenis, SN, lokasi…"
           class="ring-focus rounded-xl border border-stone-200 px-3.5 py-2 text-[12.5px] w-64">
    <select name="kat" class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] font-semibold">
      <option value="">Semua kategori</option>
      @foreach($K::KATEGORI as $v)<option value="{{ $v }}" @selected($kat === $v)>{{ $v }}</option>@endforeach
    </select>
    <select name="st" class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] font-semibold">
      <option value="">Semua status</option>
      @foreach($K::STATUS as $v)<option value="{{ $v }}" @selected($st === $v)>{{ $v }}</option>@endforeach
    </select>
    <select name="ops" class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] font-semibold">
      <option value="">Semua operasi</option>
      @foreach($K::OPERASI as $v)<option value="{{ $v }}" @selected($ops === $v)>{{ $v }}</option>@endforeach
    </select>
    <button class="rounded-xl bg-cam-ink text-white px-4 py-2 text-[12.5px] font-bold">Saring</button>
    <a href="{{ route('ko.register') }}" class="text-[12px] text-stone-500 underline">Reset</a>
    @if($bolehUbah)
      <a href="{{ route('ko.create') }}"
         class="ml-auto lime-gradient shadow-glow rounded-xl text-white px-4 py-2 text-[12.5px] font-bold hover:brightness-105 transition">+ Objek SPIP</a>
    @endif
  </form>

  <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-stone-100 flex items-center justify-between">
      <h3 class="text-[13px] font-bold text-cam-ink">Register Objek SPIP</h3>
      <span class="text-[11px] text-stone-400 num">{{ $objek->count() }} objek</span>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-[12px] min-w-[860px]">
        <thead class="text-[10px] uppercase tracking-wider text-stone-400 bg-stone-50">
          <tr>
            <th class="text-left px-5 py-2.5 font-bold">Kode</th>
            <th class="text-left px-3 py-2.5 font-bold">Objek</th>
            <th class="text-left px-3 py-2.5 font-bold">Kategori</th>
            <th class="text-left px-3 py-2.5 font-bold">Perusahaan</th>
            <th class="text-left px-3 py-2.5 font-bold">Lokasi</th>
            <th class="text-right px-3 py-2.5 font-bold">Kadaluarsa</th>
            <th class="text-left px-5 py-2.5 font-bold">Status</th>
          </tr>
        </thead>
        <tbody>
          @forelse($objek as $o)
            <tr class="border-b border-stone-50 hover:bg-stone-50/70 transition">
              <td class="px-5 py-2.5"><a href="{{ route('ko.show', $o) }}" class="num font-bold text-cam-lime-deep hover:underline">{{ $o->kode }}</a></td>
              <td class="px-3 py-2.5">
                <div class="font-semibold text-cam-ink">{{ $o->nama }}</div>
                <div class="text-[10.5px] text-stone-400">{{ $o->jenis }} · {{ $o->merk }}</div>
              </td>
              <td class="px-3 py-2.5"><span class="text-[10.5px] px-2 py-0.5 rounded-md bg-stone-100 text-stone-600 font-semibold">{{ $o->kategori }}</span></td>
              <td class="px-3 py-2.5 text-stone-600">{{ $o->company?->code ?: '—' }}</td>
              <td class="px-3 py-2.5 text-stone-500">{{ $o->lokasi ?: '—' }}</td>
              <td class="px-3 py-2.5 text-right num text-stone-500 whitespace-nowrap">
                {{ $o->kadaluarsa?->format('d M Y') ?: '—' }}
              </td>
              <td class="px-5 py-2.5">@include('ko._badge', ['st' => $o->status_ko])</td>
            </tr>
          @empty
            <tr><td colspan="7" class="px-5 py-10 text-center text-[12.5px] text-stone-400">Tidak ada objek yang cocok.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@extends('layouts.app')
@section('title','PTPKKP — Profil')

@section('content')
@php $p = $a->profil ?? []; $bisa = auth()->user()->isAdmin(); @endphp
<div class="max-w-3xl mx-auto space-y-5">
  @include('tpkkp._flash')
  @include('tpkkp._picker')

  <form method="POST" action="{{ route('tpkkp.profile.save', ['tahun' => $a->tahun]) }}"
        class="bg-white rounded-2xl border border-stone-200 p-6 space-y-4">
    @csrf
    <h3 class="text-[14px] font-bold text-cam-ink">Profil Penilaian {{ $a->tahun }}</h3>

    @php
      $isian = [
        ['judul','Judul penilaian', $a->judul],
        ['organisasi','Organisasi yang dinilai', $p['organisasi'] ?? ''],
        ['site','Lokasi / site', $p['site'] ?? ''],
        ['komoditas','Komoditas', $p['komoditas'] ?? ''],
        ['ktt','Kepala Teknik Tambang', $p['ktt'] ?? ''],
        ['basis','Dasar hukum / acuan', $p['basis'] ?? ''],
      ];
    @endphp

    @foreach($isian as $f)
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">{{ $f[1] }}</label>
        <input type="text" name="{{ $f[0] }}" value="{{ old($f[0], $f[2]) }}" @disabled(!$bisa)
               class="ring-focus w-full rounded-xl border border-stone-200 bg-white px-3.5 py-2.5 text-[13px]">
      </div>
    @endforeach

    <div>
      <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Status</label>
      <select name="status" @disabled(!$bisa)
              class="ring-focus w-full rounded-xl border border-stone-200 bg-white px-3.5 py-2.5 text-[13px] font-semibold">
        @foreach(['draft'=>'Draft','aktif'=>'Aktif','selesai'=>'Selesai'] as $k => $v)
          <option value="{{ $k }}" @selected($a->status === $k)>{{ $v }}</option>
        @endforeach
      </select>
    </div>

    @if($bisa)
      <button class="lime-gradient shadow-glow rounded-xl text-white px-6 py-2.5 text-[13px] font-bold hover:brightness-105 transition">Simpan profil</button>
    @endif
  </form>

  <div class="bg-white rounded-2xl border border-stone-200 p-6">
    <h3 class="text-[13px] font-bold text-cam-ink mb-3">Roster entitas</h3>
    <div class="space-y-3">
      @foreach(\App\Support\Tpkkp::methods() as $k => $m)
        @php $ents = $a->entitiesOf($k); @endphp
        @if(count($ents))
          <div>
            <div class="text-[11px] font-bold text-stone-500 mb-1.5">{{ $k }} · {{ $m['entityLabel'] }} ({{ count($ents) }})</div>
            <div class="flex flex-wrap gap-1.5">
              @foreach($ents as $e)
                <span class="text-[11px] px-2 py-1 rounded-lg bg-stone-50 border border-stone-200 text-stone-600">{{ $e }}</span>
              @endforeach
            </div>
          </div>
        @endif
      @endforeach
    </div>
  </div>
</div>
@endsection

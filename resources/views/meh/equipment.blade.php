@extends('layouts.app')
@section('title','Mining Equipment')

@section('content')
@php use App\Support\Engineering as E; @endphp

<div class="max-w-6xl mx-auto space-y-5">

  <section class="kartu-lux rounded-2xl p-6">
    <h2 class="font-display text-[20px] font-black text-cam-ink">Mining Equipment</h2>
    <p class="text-[12.5px] text-stone-500 mt-1.5 leading-relaxed max-w-3xl">
      Data induk alat beserta ketersediaan dan konsumsi bahan bakarnya. Tiap unit dinilai terhadap
      rata-rata kelasnya sendiri — ambangnya 1,05 kali acuan untuk efisien dan 1,20 kali untuk perlu
      dipantau.
    </p>
    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mt-6 pt-6 hairline border-b-0">
      @foreach ([
        ['Unit Terdaftar', $a['jumlah'], 'unit'],
        ['Sedang Beroperasi', $a['beroperasi'], 'unit'],
        ['Jam Kerja Armada', number_format($a['kerja']), 'jam'],
        ['Solar Terpakai', number_format($a['liter']), 'L'],
      ] as [$l,$v,$s])
        <div>
          <div class="stat stat-sm text-cam-lime-deep">{{ $v }}<span class="stat-unit">{{ $s }}</span></div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">{{ $l }}</div>
        </div>
      @endforeach
    </div>
  </section>

  <div class="grid gap-4 grid-cols-2 lg:grid-cols-4">
    @foreach($acuan as $kelas => $nilai)
      <x-kpi :label="$kelas" :nilai="number_format($nilai,1)" satuan="L/jam"
             :ket="count(array_filter(E::armada(), fn($u) => $u['kelas'] === $kelas)).' unit · acuan kelas'" />
    @endforeach
  </div>

  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @foreach($unit as $u)
      @php
        $fr = E::fuelRate($u); $ac = $acuan[$u['kelas']] ?? 0;
        $st = E::statusBoros($fr, $ac); $rasio = $ac > 0 ? $fr / $ac : 0;
        $w = ['Operating'=>'#0F766E','Standby'=>'#4C9AFF','Maintenance'=>'#C08A3E','Breakdown'=>'#E2663A'][$u['status']];
      @endphp
      <article class="kartu-lux rounded-2xl p-5">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="text-[14px] font-bold text-cam-ink">{{ $u['kode'] }}</div>
            <div class="text-[11.5px] text-stone-500">{{ $u['tipe'] }} · {{ $u['kelas'] }}</div>
          </div>
          <span class="shrink-0 text-[9.5px] font-bold uppercase tracking-wide text-white px-2 py-1 rounded-lg"
                style="background:{{ $w }}">{{ $u['status'] }}</span>
        </div>

        <div class="grid grid-cols-3 gap-2 mt-4">
          @foreach ([['L/jam', number_format($fr,1), $st['warna']], ['MA', number_format(E::ma($u),1).'%', '#22312F'],
                     ['HM', number_format($u['hm']), '#22312F']] as [$l,$v,$c])
            <div>
              <div class="num text-[15px] font-bold" style="color:{{ $c }}">{{ $v }}</div>
              <div class="text-[10px] text-stone-400 mt-0.5">{{ $l }}</div>
            </div>
          @endforeach
        </div>

        <div class="mt-4 h-1.5 rounded-full bg-stone-100 overflow-hidden">
          <div class="h-full rounded-full transition-all duration-700"
               style="width:{{ max(6, min(100, $rasio / 1.5 * 100)) }}%; background:{{ $st['warna'] }}"></div>
        </div>
        <div class="flex justify-between text-[10.5px] mt-2">
          <span style="color:{{ $st['warna'] }}" class="font-semibold">{{ $st['label'] }}</span>
          <span class="text-stone-400">Acuan {{ $u['kelas'] }}: {{ number_format($ac,1) }} L/jam</span>
        </div>
      </article>
    @endforeach
  </div>

</div>
@endsection

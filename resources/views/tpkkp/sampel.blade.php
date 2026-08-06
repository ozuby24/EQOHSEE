@extends('layouts.app')
@section('title','PTPKKP — Rencana Sampel')

@section('content')
@php
  $co  = $ref['companies'] ?? [];
  $pop = $ref['population'] ?? [];
  $blok = $ref[$metodeAktif] ?? [];
@endphp
<div class="max-w-6xl mx-auto space-y-5">
  @include('tpkkp._flash')
  @include('tpkkp._picker')

  <div class="bg-white rounded-2xl border border-stone-200 p-5">
    <h3 class="text-[13px] font-bold text-cam-ink mb-1">Rencana Sampel per Perusahaan</h3>
    <p class="text-[11.5px] text-stone-500">
      Populasi acuan: Management {{ number_format($pop['Management'] ?? 0) }} ·
      Employee {{ number_format($pop['Employee'] ?? 0) }} ·
      total {{ number_format($pop['Total'] ?? 0) }} orang.
    </p>
    <div class="flex flex-wrap gap-2 mt-3">
      @foreach($daftarMetode as $m)
        <a href="{{ route('tpkkp.sampel', ['m' => $m]) }}"
           class="text-[12px] font-semibold px-3 py-1.5 rounded-lg border transition
                  {{ $m === $metodeAktif ? 'bg-cam-ink text-white border-cam-ink' : 'bg-white text-stone-600 border-stone-200 hover:border-stone-400' }}">{{ $m }}</a>
      @endforeach
    </div>
  </div>

  <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-stone-100">
      <h3 class="text-[13px] font-bold text-cam-ink">Alokasi metode {{ $metodeAktif }}</h3>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-[12px] min-w-[640px]">
        <thead class="text-[10px] uppercase tracking-wider text-stone-400 bg-stone-50">
          <tr>
            <th class="text-left px-5 py-2.5 font-bold">Perusahaan</th>
            <th class="text-right px-3 py-2.5 font-bold">Management</th>
            <th class="text-right px-3 py-2.5 font-bold">Employee</th>
            <th class="text-right px-5 py-2.5 font-bold">Jumlah</th>
          </tr>
        </thead>
        <tbody>
          @foreach($co as $c)
            @php
              $mg = (float) ($blok['Management'][$c] ?? 0);
              $em = (float) ($blok['Employee'][$c] ?? 0);
            @endphp
            <tr class="border-b border-stone-50">
              <td class="px-5 py-2.5">{{ $c }}</td>
              <td class="px-3 py-2.5 text-right num">{{ $mg ? number_format(ceil($mg)) : '—' }}</td>
              <td class="px-3 py-2.5 text-right num">{{ $em ? number_format(ceil($em)) : '—' }}</td>
              <td class="px-5 py-2.5 text-right num font-semibold">{{ ($mg + $em) ? number_format(ceil($mg) + ceil($em)) : '—' }}</td>
            </tr>
          @endforeach
          <tr class="bg-stone-50 font-bold">
            <td class="px-5 py-2.5">Total rencana</td>
            <td class="px-3 py-2.5 text-right num">{{ isset($blok['totalMgm']) ? number_format(ceil($blok['totalMgm'])) : '—' }}</td>
            <td class="px-3 py-2.5 text-right num">{{ isset($blok['totalEmp']) ? number_format(ceil($blok['totalEmp'])) : '—' }}</td>
            <td class="px-5 py-2.5 text-right num">
              {{ (isset($blok['totalMgm']) || isset($blok['totalEmp'])) ? number_format(ceil($blok['totalMgm'] ?? 0) + ceil($blok['totalEmp'] ?? 0)) : '—' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <p class="px-5 py-3 text-[11px] text-stone-400 border-t border-stone-100">
      Angka rencana dibulatkan ke atas dari alokasi proporsional instrumen. Hitungan Slovin ada di halaman Sampel.
    </p>
  </div>
</div>
@endsection

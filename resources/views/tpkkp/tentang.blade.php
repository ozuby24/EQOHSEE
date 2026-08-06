@extends('layouts.app')
@section('title','PTPKKP — Instrumen')

@section('content')
@php $T = \App\Support\Tpkkp::class; @endphp
<div class="max-w-5xl mx-auto space-y-5">
  @include('tpkkp._flash')
  @include('tpkkp._picker')

  <div class="bg-white rounded-2xl border border-stone-200 p-6">
    <h2 class="text-[17px] font-bold text-cam-ink">{{ $meta['title'] ?? 'Instrumen PTPKKP' }}</h2>
    <p class="text-[12.5px] text-stone-500 mt-1">{{ $meta['basis'] ?? '' }}</p>

    @php
      $ringkas = [
        ['Indikator',    count($T::indicators())],
        ['Parameter',    collect($T::indicators())->sum(fn($i) => count($i['params']))],
        ['Item',         $T::totalItems()],
        ['Target total', number_format($T::totalTarget(), 2)],
      ];
    @endphp
    <div class="grid sm:grid-cols-4 gap-3 mt-5">
      @foreach($ringkas as $r)
        <div class="rounded-xl bg-stone-50 border border-stone-200 px-4 py-3">
          <div class="stat text-[20px] leading-none">{{ $r[1] }}</div>
          <div class="text-[10px] uppercase tracking-wider text-stone-400 font-bold mt-1.5">{{ $r[0] }}</div>
        </div>
      @endforeach
    </div>

    <h3 class="text-[13px] font-bold text-cam-ink mt-7 mb-2">Ambang kategori</h3>
    <div class="flex flex-wrap gap-2">
      @foreach($T::ref()['thresholds'] as $i => $t)
        <span class="text-[11px] font-semibold px-2.5 py-1 rounded-lg text-white"
              style="background: {{ $T::levelHex($i + 1) }}">
          {{ $t['label'] }} &lt; {{ rtrim(rtrim(number_format($t['lt'], 4, '.', ''), '0'), '.') }}
        </span>
      @endforeach
    </div>
    <p class="text-[11.5px] text-stone-500 mt-2">Ambang yang sama dipakai untuk item, parameter, indikator, dan total.</p>

    <h3 class="text-[13px] font-bold text-cam-ink mt-6 mb-2">Cara nilai dihitung</h3>
    <ul class="text-[12px] text-stone-600 space-y-1 list-disc pl-4">
      <li>Skor satu metode = rerata seluruh entitas yang terisi.</li>
      <li>Nilai item = jumlah skor metodenya; maks item = 5 × jumlah metode.</li>
      <li>Metode yang belum diisi dihitung <b>nol</b>, bukan diabaikan.</li>
      <li>Nilai parameter = (Σ nilai item ÷ Σ maks item) × bobot parameter.</li>
      <li>Nilai total = jumlah nilai seluruh indikator, maksimum 1,000.</li>
    </ul>
  </div>

  @foreach($T::indicators() as $ind)
    <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
      <div class="px-5 py-3 bg-stone-50 border-b border-stone-200 flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-[13px] font-bold text-cam-ink">{{ $ind['code'] }}. {{ ucwords(strtolower($ind['name'])) }}</h3>
        <span class="num text-[12px] text-stone-500">bobot {{ number_format(collect($ind['params'])->sum('weight'), 2) }}</span>
      </div>
      <table class="w-full text-[12px]">
        <thead class="text-[10px] uppercase tracking-wider text-stone-400">
          <tr class="border-b border-stone-100">
            <th class="text-left px-5 py-2 font-bold">Parameter</th>
            <th class="text-right px-3 py-2 font-bold">Bobot</th>
            <th class="text-right px-3 py-2 font-bold">Target</th>
            <th class="text-right px-5 py-2 font-bold">Item</th>
          </tr>
        </thead>
        <tbody>
          @foreach($ind['params'] as $p)
            <tr class="border-b border-stone-50 last:border-0">
              <td class="px-5 py-2"><b class="num">{{ $p['code'] }}</b> · {{ $p['name'] }}</td>
              <td class="px-3 py-2 text-right num">{{ number_format($p['weight'], 2) }}</td>
              <td class="px-3 py-2 text-right num text-stone-500">{{ number_format($T::paramTargets()[$p['code']] ?? 0, 2) }}</td>
              <td class="px-5 py-2 text-right num">{{ count($p['items']) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endforeach
</div>
@endsection

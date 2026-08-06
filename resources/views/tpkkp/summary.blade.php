@extends('layouts.app')
@section('title','PTPKKP — Summary')

@section('content')
@php
  $sgn = function ($x) {
    if ($x === null) return '<span class="text-stone-400">—</span>';
    $w = $x >= 0 ? 'text-emerald-600' : 'text-red-600';
    return '<span class="'.$w.' font-semibold">'.($x >= 0 ? '+' : '').number_format($x, 3).'</span>';
  };
@endphp
<div class="max-w-6xl mx-auto space-y-5">
  @include('tpkkp._flash')
  @include('tpkkp._picker')

  <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-stone-100">
      <h3 class="text-[13px] font-bold text-cam-ink">Rekap Nilai per Parameter — lawan target</h3>
      <p class="text-[11px] text-stone-500 mt-0.5">Gap negatif berarti capaian masih di bawah target.</p>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-[12px] min-w-[760px]">
        <thead class="text-[10px] uppercase tracking-wider text-stone-400 bg-stone-50">
          <tr>
            <th class="text-left px-5 py-2.5 font-bold">No</th>
            <th class="text-left px-3 py-2.5 font-bold">Indikator / Parameter</th>
            <th class="text-right px-3 py-2.5 font-bold">Maks</th>
            <th class="text-right px-3 py-2.5 font-bold">Capaian</th>
            <th class="text-right px-3 py-2.5 font-bold">Ach</th>
            <th class="text-left px-3 py-2.5 font-bold">Kategori</th>
            <th class="text-right px-3 py-2.5 font-bold">Target</th>
            <th class="text-right px-5 py-2.5 font-bold">Gap</th>
          </tr>
        </thead>
        <tbody>
          @foreach($hasil['indicators'] as $I)
            <tr class="bg-stone-50/70 border-y border-stone-100 font-bold">
              <td class="px-5 py-2 num">{{ $I['code'] }}</td>
              <td class="px-3 py-2 text-cam-ink">{{ ucwords(strtolower($I['name'])) }}</td>
              <td class="px-3 py-2 text-right num">{{ number_format($I['weight'], 2) }}</td>
              <td class="px-3 py-2 text-right num">{{ $I['score'] === null ? '—' : number_format($I['score'], 3) }}</td>
              <td class="px-3 py-2 text-right num">{{ $I['ratio'] === null ? '—' : number_format($I['ratio'] * 100, 1) . '%' }}</td>
              <td class="px-3 py-2">@include('tpkkp._badge', ['cat' => $I['category']])</td>
              <td class="px-3 py-2 text-right num">{{ number_format($I['target'], 2) }}</td>
              <td class="px-5 py-2 text-right num">{!! $sgn($I['score'] === null ? null : $I['score'] - $I['target']) !!}</td>
            </tr>
            @foreach($I['params'] as $P)
              <tr class="border-b border-stone-50">
                <td class="px-5 py-2 num text-stone-500">{{ $P['code'] }}</td>
                <td class="px-3 py-2">{{ $P['name'] }}</td>
                <td class="px-3 py-2 text-right num text-stone-400">{{ number_format($P['weight'], 2) }}</td>
                <td class="px-3 py-2 text-right num font-semibold">{{ $P['score'] === null ? '—' : number_format($P['score'], 3) }}</td>
                <td class="px-3 py-2 text-right num">{{ $P['ratio'] === null ? '—' : number_format($P['ratio'] * 100, 1) . '%' }}</td>
                <td class="px-3 py-2">@include('tpkkp._badge', ['cat' => $P['category']])</td>
                <td class="px-3 py-2 text-right num text-stone-400">{{ $P['target'] === null ? '—' : number_format($P['target'], 2) }}</td>
                <td class="px-5 py-2 text-right num">{!! $sgn(($P['score'] === null || $P['target'] === null) ? null : $P['score'] - $P['target']) !!}</td>
              </tr>
            @endforeach
          @endforeach

          <tr class="bg-cam-ink text-white font-bold">
            <td class="px-5 py-2.5"></td>
            <td class="px-3 py-2.5">NILAI TOTAL PENCAPAIAN KINERJA</td>
            <td class="px-3 py-2.5 text-right num">1,00</td>
            <td class="px-3 py-2.5 text-right num text-cam-lime-light">{{ $hasil['score'] === null ? '—' : number_format($hasil['score'], 3) }}</td>
            <td class="px-3 py-2.5 text-right num">{{ $hasil['score'] === null ? '—' : number_format($hasil['score'] * 100, 1) . '%' }}</td>
            <td class="px-3 py-2.5">@include('tpkkp._badge', ['cat' => $hasil['category']])</td>
            <td class="px-3 py-2.5 text-right num">{{ number_format($hasil['target'], 2) }}</td>
            <td class="px-5 py-2.5 text-right num">{!! $sgn($hasil['score'] === null ? null : $hasil['score'] - $hasil['target']) !!}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="bg-white rounded-2xl border border-stone-200 p-6">
    <h3 class="text-[13px] font-bold text-cam-ink mb-4">Capaian vs Target per Parameter</h3>
    <div class="space-y-2.5">
      @foreach($hasil['indicators'] as $I)
        @foreach($I['params'] as $P)
          @php
            $w  = $P['weight'] ?: 1;
            $pc = max(0, min(100, ($P['score'] ?? 0) / $w * 100));
            $pt = max(0, min(100, ($P['target'] ?? 0) / $w * 100));
          @endphp
          <div>
            <div class="flex justify-between text-[11px] mb-1">
              <span class="text-stone-600"><b class="num">{{ $P['code'] }}</b> {{ \Illuminate\Support\Str::limit($P['name'], 50) }}</span>
              <span class="num text-stone-500">{{ $P['score'] === null ? '—' : number_format($P['score'], 3) }} / {{ $P['target'] === null ? '—' : number_format($P['target'], 2) }}</span>
            </div>
            <div class="relative h-2 rounded-full bg-stone-100 overflow-hidden">
              <div class="h-full rounded-full" style="width: {{ round($pc) }}%; background: {{ \App\Support\Tpkkp::levelHex(\App\Support\Tpkkp::level($P['category'])) }}"></div>
              <div class="absolute top-0 bottom-0 w-0.5 bg-cam-ink/70" style="left: {{ round($pt) }}%"></div>
            </div>
          </div>
        @endforeach
      @endforeach
    </div>
  </div>
</div>
@endsection

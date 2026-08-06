@extends('layouts.app')
@section('title','PTPKKP — Hasil')

@section('content')
@php $T = \App\Support\Tpkkp::class; @endphp
<div class="max-w-6xl mx-auto space-y-5">
  @include('tpkkp._flash')
  @include('tpkkp._picker')

  <div class="grid lg:grid-cols-3 gap-5">
    <div class="bg-white rounded-2xl border border-stone-200 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-3">Kategori Tingkat Pencapaian</h3>
      <table class="w-full text-[12px]">
        <tbody>
          @php
            $ranges = [['x < 0,5','Dasar'],['0,5 ≤ x < 0,7','Reaktif'],['0,7 ≤ x < 0,8','Terencana'],['0,8 ≤ x < 0,9','Proaktif'],['0,9 ≤ x ≤ 1,0','Resilient']];
          @endphp
          @foreach($ranges as $r)
            <tr class="border-b border-stone-50 last:border-0">
              <td class="py-1.5 num text-stone-600">{{ $r[0] }}</td>
              <td class="py-1.5 text-right">@include('tpkkp._badge', ['cat' => $r[1]])</td>
            </tr>
          @endforeach
        </tbody>
      </table>

      <div class="mt-5 pt-4 border-t border-stone-100">
        <div class="text-[10px] uppercase tracking-wider text-stone-400 font-bold">Tingkat Pencapaian Kinerja KP</div>
        <div class="stat stat-xl leading-none mt-1.5">{{ $hasil['score'] === null ? '—' : number_format($hasil['score'], 3) }}</div>
        <div class="mt-2">@include('tpkkp._badge', ['cat' => $hasil['category']])</div>
        <div class="h-2 rounded-full bg-stone-100 mt-3 overflow-hidden relative">
          <div class="h-full rounded-full bg-cam-lime" style="width: {{ round(($hasil['score'] ?? 0) * 100) }}%"></div>
          <div class="absolute top-0 bottom-0 w-0.5 bg-cam-ink" style="left: {{ round($hasil['target'] * 100) }}%"></div>
        </div>
        <div class="text-[11px] text-stone-500 mt-1.5 num">target {{ number_format($hasil['target'], 2) }}</div>
      </div>
    </div>

    <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden lg:col-span-2">
      <div class="px-5 py-3 border-b border-stone-100">
        <h3 class="text-[13px] font-bold text-cam-ink">Pencapaian per Indikator</h3>
      </div>
      <table class="w-full text-[12px]">
        <thead class="text-[10px] uppercase tracking-wider text-stone-400 bg-stone-50">
          <tr>
            <th class="text-left px-5 py-2.5 font-bold">Indikator</th>
            <th class="text-right px-3 py-2.5 font-bold">Nilai Maks</th>
            <th class="text-right px-3 py-2.5 font-bold">Pencapaian</th>
            <th class="text-right px-3 py-2.5 font-bold">% Terpenuhi</th>
            <th class="text-left px-5 py-2.5 font-bold">Tingkat</th>
          </tr>
        </thead>
        <tbody>
          @foreach($hasil['indicators'] as $I)
            <tr class="border-b border-stone-50">
              <td class="px-5 py-2.5"><b class="num">{{ $I['code'] }}</b> {{ ucwords(strtolower($I['name'])) }}</td>
              <td class="px-3 py-2.5 text-right num text-stone-400">{{ number_format($I['weight'], 2) }}</td>
              <td class="px-3 py-2.5 text-right num font-semibold">{{ $I['score'] === null ? '—' : number_format($I['score'], 3) }}</td>
              <td class="px-3 py-2.5 text-right num">{{ $I['ratio'] === null ? '—' : number_format($I['ratio'] * 100, 1) . '%' }}</td>
              <td class="px-5 py-2.5">@include('tpkkp._badge', ['cat' => $I['category']])</td>
            </tr>
          @endforeach
          <tr class="bg-stone-50 font-bold">
            <td class="px-5 py-2.5">Total</td>
            <td class="px-3 py-2.5 text-right num">1,00</td>
            <td class="px-3 py-2.5 text-right num">{{ $hasil['score'] === null ? '—' : number_format($hasil['score'], 3) }}</td>
            <td class="px-3 py-2.5 text-right num">{{ $hasil['score'] === null ? '—' : number_format($hasil['score'] * 100, 1) . '%' }}</td>
            <td class="px-5 py-2.5">@include('tpkkp._badge', ['cat' => $hasil['category']])</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-stone-100 flex flex-wrap items-center justify-between gap-2">
      <h3 class="text-[13px] font-bold text-cam-ink">Pencapaian per Metode Pengukuran</h3>
      <span class="text-[11px] text-stone-400">maks mengikuti pemetaan metode pada instrumen</span>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-[12px] min-w-[720px]">
        <thead class="text-[10px] uppercase tracking-wider text-stone-400 bg-stone-50">
          <tr>
            <th class="text-left px-5 py-2.5 font-bold">Metode</th>
            <th class="text-right px-3 py-2.5 font-bold">Item</th>
            <th class="text-right px-3 py-2.5 font-bold">Terisi</th>
            <th class="text-right px-3 py-2.5 font-bold">Maks</th>
            <th class="text-right px-3 py-2.5 font-bold">Pencapaian</th>
            <th class="text-right px-3 py-2.5 font-bold">% Terpenuhi</th>
            <th class="text-left px-3 py-2.5 font-bold">Tingkat</th>
            <th class="text-left px-5 py-2.5 font-bold" style="width:180px">Progres</th>
          </tr>
        </thead>
        <tbody>
          @foreach($metode as $m)
            <tr class="border-b border-stone-50">
              <td class="px-5 py-2.5"><b class="num text-cam-lime-deep">{{ $m['key'] }}</b> {{ $m['name'] }}</td>
              <td class="px-3 py-2.5 text-right num">{{ $m['items'] }}</td>
              <td class="px-3 py-2.5 text-right num">{{ $m['filled'] }}</td>
              <td class="px-3 py-2.5 text-right num text-stone-400">{{ $m['max'] }}</td>
              <td class="px-3 py-2.5 text-right num font-semibold">{{ $m['sum'] ? number_format($m['sum'], 1) : '—' }}</td>
              <td class="px-3 py-2.5 text-right num">{{ $m['ratio'] === null ? '—' : number_format($m['ratio'] * 100, 1) . '%' }}</td>
              <td class="px-3 py-2.5">@include('tpkkp._badge', ['cat' => $m['category']])</td>
              <td class="px-5 py-2.5">
                <div class="h-1.5 rounded-full bg-stone-100 overflow-hidden">
                  <div class="h-full rounded-full" style="width: {{ round(($m['ratio'] ?? 0) * 100) }}%; background: {{ $T::levelHex($T::level($m['category'])) }}"></div>
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

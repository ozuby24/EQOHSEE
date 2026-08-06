@extends('layouts.app')
@section('title','PTPKKP — Sampel')

@section('content')
@php $bisa = auth()->user()->isAdmin(); @endphp
<div class="max-w-4xl mx-auto space-y-5">
  @include('tpkkp._flash')
  @include('tpkkp._picker')

  <form method="POST" action="{{ route('tpkkp.sampling.save', ['tahun' => $a->tahun]) }}"
        class="bg-white rounded-2xl border border-stone-200 p-6">
    @csrf
    <h3 class="text-[13px] font-bold text-cam-ink mb-1">Penentuan Jumlah Sampel — Slovin</h3>
    <p class="text-[11.5px] text-stone-500 mb-4">n = N / (1 + N·e²), dialokasikan proporsional per strata.</p>

    <div class="grid sm:grid-cols-3 gap-3 mb-4">
      @foreach($strata as $s)
        <div>
          <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Populasi {{ $s['j'] }}</label>
          <input type="number" min="0" name="N[{{ $s['j'] }}]" value="{{ $s['N'] }}" @disabled(!$bisa)
                 class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[13px] num">
        </div>
      @endforeach
      <div>
        <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Margin galat (e)</label>
        <input type="number" step="0.01" min="0.01" max="0.2" name="e" value="{{ $e }}" @disabled(!$bisa)
               class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[13px] num">
      </div>
    </div>

    @if($bisa)
      <button class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2.5 text-[13px] font-bold hover:brightness-105 transition">Hitung & simpan</button>
    @endif
  </form>

  <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-stone-100 flex items-center justify-between">
      <h3 class="text-[13px] font-bold text-cam-ink">Hasil Perhitungan</h3>
      <span class="num text-[12px] font-bold text-cam-ink">N {{ $alokasi['N'] }} → n {{ $alokasi['n'] }}</span>
    </div>
    <table class="w-full text-[12px]">
      <thead class="text-[10px] uppercase tracking-wider text-stone-400 bg-stone-50">
        <tr>
          <th class="text-left px-5 py-2.5 font-bold">Strata</th>
          <th class="text-right px-3 py-2.5 font-bold">Populasi</th>
          <th class="text-right px-5 py-2.5 font-bold">Sampel</th>
        </tr>
      </thead>
      <tbody>
        @foreach($alokasi['rows'] as $r)
          <tr class="border-b border-stone-50">
            <td class="px-5 py-2.5">{{ $r['j'] }}</td>
            <td class="px-3 py-2.5 text-right num">{{ $r['N'] }}</td>
            <td class="px-5 py-2.5 text-right num font-bold">{{ $r['nh'] }}</td>
          </tr>
        @endforeach
        <tr class="bg-stone-50 font-bold">
          <td class="px-5 py-2.5">Total</td>
          <td class="px-3 py-2.5 text-right num">{{ $alokasi['N'] }}</td>
          <td class="px-5 py-2.5 text-right num">{{ $alokasi['total'] }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
@endsection

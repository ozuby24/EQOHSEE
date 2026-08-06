@extends('layouts.app')
@section('title','PTPKKP — Rekapitulasi')

@section('content')
@php $T = \App\Support\Tpkkp::class; @endphp
<div class="max-w-6xl mx-auto space-y-5">
  @include('tpkkp._flash')
  @include('tpkkp._picker')

  <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-stone-100 flex flex-wrap items-center justify-between gap-3">
      <h3 class="text-[13px] font-bold text-cam-ink">Rekapitulasi Nilai per Parameter</h3>
      <span class="num text-[12px] font-bold text-cam-ink">
        Total {{ $hasil['score'] === null ? '—' : number_format($hasil['score'], 3) }} / target {{ number_format($hasil['target'], 2) }}
      </span>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-[12px] min-w-[720px]">
        <thead class="text-[10px] uppercase tracking-wider text-stone-400 bg-stone-50">
          <tr>
            <th class="text-left px-5 py-2.5 font-bold">Parameter</th>
            <th class="text-right px-3 py-2.5 font-bold">Nilai</th>
            <th class="text-right px-3 py-2.5 font-bold">Maks</th>
            <th class="text-right px-3 py-2.5 font-bold">Rasio</th>
            <th class="text-right px-3 py-2.5 font-bold">Bobot</th>
            <th class="text-right px-3 py-2.5 font-bold">Capaian</th>
            <th class="text-right px-3 py-2.5 font-bold">Target</th>
            <th class="text-right px-5 py-2.5 font-bold">Kategori</th>
          </tr>
        </thead>
        <tbody>
          @foreach($hasil['indicators'] as $ind)
            <tr class="bg-stone-50/70 border-y border-stone-100">
              <td class="px-5 py-2 font-bold text-cam-ink">{{ $ind['code'] }}. {{ ucwords(strtolower($ind['name'])) }}</td>
              <td colspan="3"></td>
              <td class="px-3 py-2 text-right num font-bold">{{ number_format($ind['weight'], 2) }}</td>
              <td class="px-3 py-2 text-right num font-bold">{{ $ind['score'] === null ? '—' : number_format($ind['score'], 3) }}</td>
              <td class="px-3 py-2 text-right num">{{ number_format($ind['target'], 2) }}</td>
              <td class="px-5 py-2 text-right">@include('tpkkp._badge', ['cat' => $ind['category']])</td>
            </tr>
            @foreach($ind['params'] as $p)
              <tr class="border-b border-stone-50">
                <td class="px-5 py-2 pl-9"><b class="num">{{ $p['code'] }}</b> · {{ $p['name'] }}</td>
                <td class="px-3 py-2 text-right num">{{ $p['nilai'] === null ? '—' : number_format($p['nilai'], 1) }}</td>
                <td class="px-3 py-2 text-right num text-stone-400">{{ $p['max'] }}</td>
                <td class="px-3 py-2 text-right num">{{ $p['ratio'] === null ? '—' : number_format($p['ratio'], 3) }}</td>
                <td class="px-3 py-2 text-right num text-stone-400">{{ number_format($p['weight'], 2) }}</td>
                <td class="px-3 py-2 text-right num font-semibold">{{ $p['score'] === null ? '—' : number_format($p['score'], 3) }}</td>
                <td class="px-3 py-2 text-right num text-stone-400">{{ $p['target'] === null ? '—' : number_format($p['target'], 2) }}</td>
                <td class="px-5 py-2 text-right">@include('tpkkp._badge', ['cat' => $p['category']])</td>
              </tr>
            @endforeach
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  {{-- rekap per perusahaan --}}
  <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-stone-100 flex flex-wrap items-center justify-between gap-3">
      <h3 class="text-[13px] font-bold text-cam-ink">Rekap per Perusahaan</h3>
      <form method="GET">
        <input type="hidden" name="tahun" value="{{ $a->tahun }}">
        <select name="entitas" onchange="this.form.submit()"
                class="ring-focus rounded-xl border border-stone-200 bg-white px-3.5 py-2 text-[12.5px] font-semibold">
          <option value="">— pilih perusahaan —</option>
          @foreach($perusahaan as $c)
            <option value="{{ $c }}" @selected($entitas === $c)>{{ $c }}</option>
          @endforeach
        </select>
      </form>
    </div>

    @if(!$entitas)
      <p class="px-5 py-8 text-[12.5px] text-stone-400">
        Pilih perusahaan untuk melihat rincian. Nilai dihitung dengan mengisolasi kolom entitas perusahaan itu pada metode {{ implode(', ', $T::perCompanyMethods()) }} — skala 1–5, bukan capaian berbobot.
      </p>
    @else
      <table class="w-full text-[12px]">
        <tbody>
          @foreach($rincian as $ind)
            <tr class="bg-stone-50/70 border-y border-stone-100">
              <td class="px-5 py-2 font-bold text-cam-ink">{{ $ind['code'] }}. {{ ucwords(strtolower($ind['name'])) }}</td>
              <td class="px-5 py-2 text-right num font-bold">{{ $ind['avg'] === null ? '—' : number_format($ind['avg'], 2) }}</td>
            </tr>
            @foreach($ind['params'] as $p)
              <tr class="border-b border-stone-50">
                <td class="px-5 py-2 pl-9"><b class="num">{{ $p['code'] }}</b> · {{ $p['name'] }}</td>
                <td class="px-5 py-2 text-right num">
                  {{ $p['avg'] === null ? '—' : number_format($p['avg'], 2) }}
                  <span class="text-stone-400">({{ $p['count'] }})</span>
                </td>
              </tr>
            @endforeach
          @endforeach
        </tbody>
      </table>

      <div class="px-5 py-4 border-t border-stone-100">
        <h4 class="text-[12px] font-bold text-cam-ink mb-2">10 item terlemah — {{ $entitas }}</h4>
        <div class="space-y-1">
          @foreach($lemah as $g)
            <div class="flex justify-between text-[11.5px] gap-3">
              <span class="text-stone-600"><b class="num">{{ $g['code'] }}</b> {{ \Illuminate\Support\Str::limit($g['name'] ?? '', 60) }}</span>
              <span class="num font-semibold whitespace-nowrap">{{ number_format($g['avg'], 2) }}</span>
            </div>
          @endforeach
        </div>
      </div>
    @endif
  </div>
</div>
@endsection

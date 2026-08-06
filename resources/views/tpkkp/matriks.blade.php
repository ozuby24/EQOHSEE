@extends('layouts.app')
@section('title','PTPKKP — Matriks')

@section('content')
@php $T = \App\Support\Tpkkp::class; $M = array_keys($T::methods()); @endphp
<div class="max-w-full mx-auto space-y-5">
  @include('tpkkp._flash')
  @include('tpkkp._picker')

  <form method="GET" class="bg-white rounded-2xl border border-stone-200 p-4 flex flex-wrap items-center gap-2">
    <input type="hidden" name="tahun" value="{{ $a->tahun }}">
    <input name="q" value="{{ $q }}" placeholder="Cari kode atau item…"
           class="ring-focus rounded-xl border border-stone-200 px-3.5 py-2 text-[12.5px] w-64">
    <select name="ind" class="ring-focus rounded-xl border border-stone-200 px-3.5 py-2 text-[12.5px] font-semibold">
      <option value="">Semua indikator</option>
      @foreach($T::indicators() as $I)
        <option value="{{ $I['code'] }}" @selected((string) $ind === (string) $I['code'])>
          {{ $I['code'] }} — {{ \Illuminate\Support\Str::limit(ucwords(strtolower($I['name'])), 38) }}
        </option>
      @endforeach
    </select>
    <button class="rounded-xl bg-cam-ink text-white px-4 py-2 text-[12.5px] font-bold">Saring</button>
    @if($q !== '' || $ind !== '')
      <a href="{{ route('tpkkp.matriks') }}" class="text-[12px] text-stone-500 underline">Reset</a>
    @endif
    <span class="ml-auto text-[11px] text-stone-400">titik · = metode berlaku tapi belum dinilai · — = metode tidak berlaku</span>
  </form>

  <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-[11.5px] min-w-[980px]">
        <thead class="text-[10px] uppercase tracking-wider text-stone-400 bg-stone-50 sticky top-0">
          <tr>
            <th class="text-left px-4 py-2.5 font-bold">No</th>
            <th class="text-left px-3 py-2.5 font-bold" style="min-width:300px">Item Pengukuran</th>
            @foreach($M as $m)
              <th class="text-right px-2 py-2.5 font-bold">{{ $m }}</th>
            @endforeach
            <th class="text-right px-3 py-2.5 font-bold">Nilai</th>
            <th class="text-right px-2 py-2.5 font-bold">Maks</th>
            <th class="text-right px-3 py-2.5 font-bold">Achv</th>
            <th class="text-left px-4 py-2.5 font-bold">Kategori</th>
          </tr>
        </thead>
        <tbody>
          @forelse($baris as $b)
            @php $I = $b['ind']; @endphp
            <tr class="bg-cam-ink/5 border-y border-stone-200 font-bold">
              <td class="px-4 py-2 num">{{ $I['code'] }}</td>
              <td class="px-3 py-2 text-cam-ink" colspan="{{ count($M) + 1 }}">{{ ucwords(strtolower($I['name'])) }}</td>
              <td class="px-2 py-2 text-right num">{{ number_format($I['weight'], 2) }}</td>
              <td class="px-3 py-2 text-right num">{{ $I['ratio'] === null ? '—' : number_format($I['ratio'] * 100, 1) . '%' }}</td>
              <td class="px-4 py-2">@include('tpkkp._badge', ['cat' => $I['category']])</td>
            </tr>

            @foreach($b['params'] as $bp)
              @php $P = $bp['par']; @endphp
              <tr class="bg-stone-50 border-b border-stone-100">
                <td class="px-4 py-1.5 num text-stone-500">{{ $P['code'] }}</td>
                <td class="px-3 py-1.5 font-semibold text-stone-700" colspan="{{ count($M) }}">
                  {{ $P['name'] }}
                  <span class="font-normal text-stone-400 num">· bobot {{ number_format($P['weight'], 2) }} · target {{ $P['target'] === null ? '—' : number_format($P['target'], 2) }}</span>
                </td>
                <td class="px-3 py-1.5 text-right num">{{ $P['nilai'] === null ? '—' : number_format($P['nilai'], 1) }}</td>
                <td class="px-2 py-1.5 text-right num text-stone-400">{{ $P['max'] }}</td>
                <td class="px-3 py-1.5 text-right num">{{ $P['ratio'] === null ? '—' : number_format($P['ratio'] * 100, 1) . '%' }}</td>
                <td class="px-4 py-1.5">@include('tpkkp._badge', ['cat' => $P['category']])</td>
              </tr>

              @foreach($bp['items'] as $c)
                <tr class="border-b border-stone-50 hover:bg-stone-50/60">
                  <td class="px-4 py-1.5 num text-stone-400">{{ $c['code'] }}</td>
                  <td class="px-3 py-1.5 text-stone-700">{{ $c['name'] }}</td>
                  @foreach($M as $m)
                    @if(in_array($m, $c['methods'], true))
                      <td class="px-2 py-1.5 text-right num {{ ($c['perMethod'][$m] ?? null) === null ? 'text-stone-300' : 'text-cam-lime-deep font-semibold' }}">
                        {{ ($c['perMethod'][$m] ?? null) === null ? '·' : number_format($c['perMethod'][$m], 1) }}
                      </td>
                    @else
                      <td class="px-2 py-1.5 text-right text-stone-200">—</td>
                    @endif
                  @endforeach
                  <td class="px-3 py-1.5 text-right num">{{ $c['nilai'] === null ? '—' : number_format($c['nilai'], 1) }}</td>
                  <td class="px-2 py-1.5 text-right num text-stone-400">{{ $c['max'] }}</td>
                  <td class="px-3 py-1.5 text-right num">{{ $c['achv'] === null ? '—' : number_format($c['achv'] * 100, 1) . '%' }}</td>
                  <td class="px-4 py-1.5">@include('tpkkp._badge', ['cat' => $c['complete'] ? $c['category'] : null])</td>
                </tr>
              @endforeach
            @endforeach
          @empty
            <tr><td colspan="{{ count($M) + 6 }}" class="px-5 py-10 text-center text-[12.5px] text-stone-400">Tidak ada baris yang cocok.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

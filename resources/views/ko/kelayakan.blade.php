@extends('layouts.app')
@section('title','KO/SPIP — Kelayakan')

@section('content')
@php $K = \App\Support\Ko::class; @endphp
<div class="max-w-6xl mx-auto space-y-5">
  @include('ko._flash')
  @include('ko._picker')

  <div class="grid sm:grid-cols-4 gap-3">
    @foreach($K::STATUS as $s)
      <div class="bg-white rounded-2xl border border-stone-200 px-4 py-3.5">
        <div class="stat text-[24px] leading-none" style="color: {{ $K::warna($s) }}">{{ $c['byStat'][$s] }}</div>
        <div class="text-[11px] font-bold text-cam-ink mt-1.5">{{ $s }}</div>
      </div>
    @endforeach
  </div>

  <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-stone-100 flex flex-wrap items-center justify-between gap-2">
      <h3 class="text-[13px] font-bold text-cam-ink">Kelayakan Sertifikat — terurut paling mendesak</h3>
      <span class="text-[11px] num" style="color: {{ $c['layakPct'] >= $set['ko_target_layak'] ? '#16A34A' : '#D97706' }}">
        Layak {{ $c['layakPct'] }}% · target {{ $set['ko_target_layak'] }}%
      </span>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-[12px] min-w-[820px]">
        <thead class="text-[10px] uppercase tracking-wider text-stone-400 bg-stone-50">
          <tr>
            <th class="text-left px-5 py-2.5 font-bold">Kode</th>
            <th class="text-left px-3 py-2.5 font-bold">Objek</th>
            <th class="text-left px-3 py-2.5 font-bold">No. Sertifikat</th>
            <th class="text-left px-3 py-2.5 font-bold">Lembaga Uji</th>
            <th class="text-right px-3 py-2.5 font-bold">Kadaluarsa</th>
            <th class="text-right px-3 py-2.5 font-bold">Sisa</th>
            <th class="text-left px-3 py-2.5 font-bold">KaIT</th>
            <th class="text-left px-5 py-2.5 font-bold">Status</th>
          </tr>
        </thead>
        <tbody>
          @forelse($objek as $o)
            <tr class="border-b border-stone-50 hover:bg-stone-50/70">
              <td class="px-5 py-2.5"><a href="{{ route('ko.show', $o) }}" class="num font-bold text-cam-lime-deep hover:underline">{{ $o->kode }}</a></td>
              <td class="px-3 py-2.5 text-cam-ink">{{ $o->nama }}</td>
              <td class="px-3 py-2.5 num text-stone-500">{{ $o->no_sertifikat ?: '—' }}</td>
              <td class="px-3 py-2.5 text-stone-500">{{ $o->lembaga_uji ?: '—' }}</td>
              <td class="px-3 py-2.5 text-right num text-stone-500 whitespace-nowrap">{{ $o->kadaluarsa?->format('d M Y') ?: '—' }}</td>
              <td class="px-3 py-2.5 text-right num whitespace-nowrap {{ ($o->sisa_hari ?? 0) < 0 ? 'text-red-600 font-bold' : 'text-stone-600' }}">
                {{ $o->sisa_hari === null ? '—' : ($o->sisa_hari < 0 ? abs($o->sisa_hari).' hari lewat' : $o->sisa_hari.' hari') }}
              </td>
              <td class="px-3 py-2.5">
                <span class="text-[10.5px] font-bold px-2 py-0.5 rounded-full {{ $o->lapor_kait ? 'bg-emerald-50 text-emerald-700' : 'bg-stone-100 text-stone-500' }}">{{ $o->lapor_kait ? 'Sudah' : 'Belum' }}</span>
              </td>
              <td class="px-5 py-2.5">@include('ko._badge', ['st' => $o->status_ko])</td>
            </tr>
          @empty
            <tr><td colspan="8" class="px-5 py-10 text-center text-[12.5px] text-stone-400">Belum ada objek.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

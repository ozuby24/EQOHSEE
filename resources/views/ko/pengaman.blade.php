@extends('layouts.app')
@section('title','KO/SPIP — Pengaman')

@section('content')
<div class="max-w-6xl mx-auto space-y-5">
  @include('ko._flash')
  @include('ko._picker')

  <div class="bg-white rounded-2xl border border-stone-200 p-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h3 class="text-[13px] font-bold text-cam-ink">Pengamanan Instalasi — sub-elemen 2</h3>
        <p class="text-[11.5px] text-stone-500 mt-0.5 num">{{ $c['pgOk'] }} dari {{ $c['pgTot'] }} perangkat berfungsi</p>
      </div>
      <div class="text-right">
        <div class="stat text-[24px] leading-none" style="color: {{ \App\Support\Ko::warnaPersen($c['pgPct']) }}">{{ $c['pgPct'] }}%</div>
      </div>
    </div>
    <div class="h-2 rounded-full bg-stone-100 mt-3 overflow-hidden">
      <div class="h-full rounded-full transition-all duration-500"
           style="width: {{ $c['pgPct'] }}%; background: {{ \App\Support\Ko::warnaPersen($c['pgPct']) }}"></div>
    </div>
    @if($sigapAda)
      <p class="text-[11px] text-stone-500 mt-3">APAR dan proteksi kebakaran dikelola di modul SIGAP, tidak didaftarkan ulang di sini.</p>
    @endif
  </div>

  @forelse($objek as $o)
    <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
      <div class="px-5 py-3 bg-stone-50 border-b border-stone-200 flex flex-wrap items-center justify-between gap-2">
        <div>
          <a href="{{ route('ko.show', $o) }}" class="num text-[11px] font-bold text-cam-lime-deep hover:underline">{{ $o->kode }}</a>
          <span class="text-[12.5px] font-bold text-cam-ink ml-1.5">{{ $o->nama }}</span>
        </div>
        <span class="text-[11px] text-stone-500 num">
          {{ $o->safeguards->where('status','Berfungsi')->count() }}/{{ $o->safeguards->count() }} berfungsi
        </span>
      </div>
      <table class="w-full text-[12px]">
        <tbody>
          @foreach($o->safeguards as $p)
            <tr class="border-b border-stone-50 last:border-0">
              <td class="px-5 py-2.5 font-semibold text-cam-ink">{{ $p->nama }}</td>
              <td class="px-3 py-2.5 text-stone-500 num">{{ $p->spesifikasi ?: '—' }}</td>
              <td class="px-3 py-2.5 text-stone-500 num">{{ $p->tgl_periksa?->format('d M Y') ?: '—' }}</td>
              <td class="px-5 py-2.5 text-right">
                <span class="text-[10.5px] font-bold px-2 py-0.5 rounded-full {{ $p->berfungsi ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">{{ $p->status }}</span>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @empty
    <div class="bg-white rounded-2xl border border-stone-200 px-5 py-10 text-center text-[12.5px] text-stone-400">
      Belum ada objek yang punya perangkat pengaman. Tambahkan dari halaman rincian objek.
    </div>
  @endforelse
</div>
@endsection

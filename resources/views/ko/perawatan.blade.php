@extends('layouts.app')
@section('title','KO/SPIP — Perawatan')

@section('content')
<div class="max-w-6xl mx-auto space-y-5">
  @include('ko._flash')
  @include('ko._picker')

  <div class="grid sm:grid-cols-3 gap-3">
    @foreach([['Objek dipantau', $c['total'], '#0B6E99'], ['PM terlewat', $c['overdue'], '#D92D20'],
              ['PM Compliance', $c['pmc'].'%', $c['pmc'] >= $set['ko_target_pmc'] ? '#16A34A' : '#D97706']] as $k)
      <div class="bg-white rounded-2xl border border-stone-200 px-4 py-3.5">
        <div class="stat text-[24px] leading-none" style="color: {{ $k[2] }}">{{ $k[1] }}</div>
        <div class="text-[11px] font-bold text-cam-ink mt-1.5">{{ $k[0] }}</div>
      </div>
    @endforeach
  </div>

  <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-stone-100">
      <h3 class="text-[13px] font-bold text-cam-ink">Jadwal Perawatan — terurut paling mendesak</h3>
      <p class="text-[11px] text-stone-500 mt-0.5">Target PMC {{ $set['ko_target_pmc'] }}%</p>
    </div>
    <div class="divide-y divide-stone-50">
      @forelse($objek as $o)
        @php $sisa = \App\Support\Ko::sisaPm($o); @endphp
        <div class="px-5 py-3.5">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <a href="{{ route('ko.show', $o) }}" class="num text-[11px] font-bold text-cam-lime-deep hover:underline">{{ $o->kode }}</a>
              <div class="text-[12.5px] font-semibold text-cam-ink">{{ $o->nama }}</div>
              <div class="text-[11px] text-stone-500 mt-0.5">
                {{ $o->pm_jenis ?: 'PM belum ditentukan' }} ·
                terakhir {{ $o->pm_terakhir?->format('d M Y') ?: '—' }} ·
                berikutnya {{ $o->pm_berikutnya?->format('d M Y') ?: '—' }}
              </div>
            </div>
            <div class="text-right">
              <span class="num text-[12px] font-bold {{ $sisa !== null && $sisa < 0 ? 'text-red-600' : 'text-stone-600' }}">
                {{ $sisa === null ? '—' : ($sisa < 0 ? abs($sisa).' hari lewat' : $sisa.' hari lagi') }}
              </span>
              @if($bolehUbah)
                <details class="mt-1.5">
                  <summary class="text-[11px] font-bold text-cam-lime-deep cursor-pointer">Catat PM</summary>
                  <form method="POST" action="{{ route('ko.perawatan.catat', $o) }}" class="mt-2 grid gap-2 text-left" style="min-width:280px">
                    @csrf
                    <div class="grid grid-cols-2 gap-2">
                      <input type="date" name="tanggal" required value="{{ now()->toDateString() }}"
                             class="ring-focus rounded-lg border border-stone-200 px-2.5 py-1.5 text-[12px]">
                      <input type="date" name="berikutnya" class="ring-focus rounded-lg border border-stone-200 px-2.5 py-1.5 text-[12px]">
                    </div>
                    <input name="hasil" placeholder="Hasil (cth. Baik)" class="ring-focus rounded-lg border border-stone-200 px-2.5 py-1.5 text-[12px]">
                    <input name="catatan" placeholder="Catatan" class="ring-focus rounded-lg border border-stone-200 px-2.5 py-1.5 text-[12px]">
                    <button class="rounded-lg bg-cam-ink text-white px-3 py-1.5 text-[12px] font-bold">Simpan</button>
                  </form>
                </details>
              @endif
            </div>
          </div>
        </div>
      @empty
        <p class="px-5 py-10 text-center text-[12.5px] text-stone-400">Belum ada objek.</p>
      @endforelse
    </div>
  </div>
</div>
@endsection

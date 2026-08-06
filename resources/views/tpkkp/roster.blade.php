@extends('layouts.app')
@section('title','PTPKKP — Mitra & Akses')

@section('content')
@php $bisa = auth()->user()->isAdmin(); $M = \App\Support\Tpkkp::methods(); @endphp
<div class="max-w-4xl mx-auto space-y-5">
  @include('tpkkp._flash')
  @include('tpkkp._picker')

  <div class="bg-white rounded-2xl border border-stone-200 p-5">
    <h3 class="text-[13px] font-bold text-cam-ink">Mitra Kerja & Entitas Penilaian</h3>
    <p class="text-[11.5px] text-stone-500 mt-1">
      Entitas menentukan kolom nilai di halaman Penilaian. Menghapus entitas tidak menghapus
      nilai yang sudah ada — nilainya hanya berhenti ikut dirata-rata.
    </p>
  </div>

  <form method="POST" action="{{ route('tpkkp.roster.save', ['tahun' => $a->tahun]) }}" class="space-y-4">
    @csrf
    @foreach($M as $k => $m)
      @php $ents = $a->entitiesOf($k); @endphp
      <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
        <div class="px-5 py-3 bg-stone-50 border-b border-stone-200 flex flex-wrap items-center justify-between gap-2">
          <h4 class="text-[12.5px] font-bold text-cam-ink">{{ $k }} · {{ $m['name'] }}</h4>
          <span class="text-[11px] text-stone-500">{{ $m['entityLabel'] ?: 'Tanpa entitas' }} · {{ count($ents) }} entitas</span>
        </div>

        @if(!($m['entityLabel'] ?? null))
          <p class="px-5 py-4 text-[12px] text-stone-400">Metode ini dinilai satu nilai untuk seluruh organisasi — tidak punya entitas.</p>
        @else
          <div class="p-5">
            <textarea name="roster[{{ $k }}]" rows="{{ max(3, min(10, count($ents))) }}" @disabled(!$bisa)
                      class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] font-mono leading-relaxed"
                      placeholder="Satu entitas per baris">{{ implode("\n", $ents) }}</textarea>
            <p class="text-[10.5px] text-stone-400 mt-1.5">Satu entitas per baris. Kosongkan untuk memakai daftar bawaan instrumen.</p>
          </div>
        @endif
      </div>
    @endforeach

    @if($bisa)
      <button class="lime-gradient shadow-glow rounded-xl text-white px-6 py-2.5 text-[13px] font-bold hover:brightness-105 transition">
        Simpan roster
      </button>
    @endif
  </form>
</div>
@endsection

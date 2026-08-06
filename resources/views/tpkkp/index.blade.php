@extends('layouts.app')
@section('title','PTPKKP — Beranda')

@section('content')
<div class="max-w-5xl mx-auto space-y-5">
  @if(session('ok'))
    <div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>
  @endif

  @include('tpkkp._picker')

  {{-- Kartu capaian --}}
  <div class="brand-gradient rounded-2xl p-7 text-white shadow-card relative overflow-hidden">
    <div class="absolute -right-24 -top-24 w-72 h-72 rounded-full bg-cam-lime/20 blur-3xl"></div>
    <div class="relative flex flex-wrap items-center justify-between gap-6">
      <div>
        <span class="text-[10px] font-bold uppercase tracking-[0.22em] text-cam-lime-light">Tingkat Pencapaian Kinerja</span>
        <h2 class="font-display text-[26px] font-black mt-2 leading-tight">{{ $company->name }}</h2>
        <p class="text-[12px] text-white/45 mt-1">{{ $company->location ?: '—' }} · {{ $company->commodity ?: '—' }}</p>
      </div>

      <div class="glass rounded-2xl px-8 py-5 text-center">
        <div class="font-display text-[44px] font-black leading-none text-cam-lime-light">{{ $hasil['pct'] }}<span class="text-[20px]">%</span></div>
        <div class="text-[9.5px] uppercase tracking-[0.15em] text-white/40 mt-2 font-bold">Capaian</div>
      </div>
    </div>

    {{-- Level --}}
    <div class="relative mt-6">
      <div class="flex justify-between text-[10px] font-bold uppercase tracking-wide mb-2">
        @foreach(\App\Support\Tpkkp::levelLabels() as $i => $lv)
          <span class="{{ $i === $hasil['levelIndex'] ? 'text-cam-lime-light' : 'text-white/25' }}">{{ $lv }}</span>
        @endforeach
      </div>
      <div class="h-2 rounded-full bg-white/10 overflow-hidden">
        <div class="h-full rounded-full lime-gradient transition-all" style="width: {{ $hasil['pct'] }}%"></div>
      </div>
      <div class="mt-3 inline-block glass rounded-xl px-4 py-2 text-[12.5px] font-bold">
        Level: {{ $hasil['levelLabel'] }}
      </div>
    </div>
  </div>

  {{-- Aksi --}}
  <div class="grid gap-3 sm:grid-cols-4">
    {{-- eq-ic-tpkkp --}}
    @foreach ([
      ['tpkkp.assess','Isi Penilaian','24 parameter','#0E747E','<rect x=\'5\' y=\'4\' width=\'13\' height=\'17\' rx=\'2\'/><path d=\'M8 4V3h5v1\'/><path d=\'M8.5 10h6M8.5 13.5h6M8.5 17h3.5\'/>'],
      ['tpkkp.profile','Profil & Strata','data perusahaan','#14385A','<path d=\'M4 20V7l7-4 7 4v13\'/><path d=\'M4 20h16\'/><path d=\'M9 20v-5h4v5\'/><path d=\'M8.5 9.5h.5M15 9.5h.5M8.5 12.5h.5M15 12.5h.5\'/>'],
      ['tpkkp.responses','Kuesioner',$jmlResponden.' responden','#B4791A','<path d=\'M4 5h16v11H8l-4 4z\'/><path d=\'M8.5 9h7M8.5 12h4\'/>'],
      ['tpkkp.rekap','Rekapitulasi','hasil lengkap','#0E747E','<path d=\'M4 20V4\'/><path d=\'M4 20h16\'/><rect x=\'7\' y=\'12\' width=\'3\' height=\'5\'/><rect x=\'12.5\' y=\'8\' width=\'3\' height=\'9\'/><rect x=\'18\' y=\'5\' width=\'0.01\' height=\'12\'/>'],
    ] as [$r,$l,$sub,$icl,$isvg])
      <a href="{{ route($r) }}" class="bg-white rounded-2xl shadow-card border border-stone-100 p-4 hover:border-cam-lime/40 card-hover transition">
        <span style="display:inline-flex;width:40px;height:40px;border-radius:12px;align-items:center;justify-content:center;background:rgba(21,141,153,.10);margin-bottom:10px">
          <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="{{ $icl }}" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">{!! $isvg !!}</svg>
        </span>
        <div class="text-[13px] font-bold text-cam-ink">{{ $l }}</div>
        <div class="text-[11px] text-stone-400 mt-0.5">{{ $sub }}</div>
      </a>
    @endforeach
  </div>

  {{-- Capaian per indikator --}}
  <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-6">
    <h3 class="text-[14px] font-bold text-cam-ink mb-4">Capaian per Indikator</h3>
    <div class="space-y-4">
      @foreach($hasil['indicators'] as $ind)
        <div>
          <div class="flex items-start justify-between gap-3 text-[12.5px] mb-1.5">
            <span class="font-semibold text-stone-600">{{ $ind['id'] }}. {{ $ind['name'] }}</span>
            <span class="shrink-0 font-bold {{ $ind['pct'] >= 70 ? 'text-cam-lime-deep' : 'text-stone-400' }}">{{ $ind['pct'] }}%</span>
          </div>
          <div class="h-2 rounded-full bg-stone-100 overflow-hidden">
            <div class="h-full rounded-full lime-gradient" style="width: {{ $ind['pct'] }}%"></div>
          </div>
          <div class="text-[10.5px] text-stone-400 mt-1">Bobot {{ $ind['weight']*100 }}% · tercapai {{ round($ind['achieved']*100,2) }} poin</div>
        </div>
      @endforeach
    </div>
  </div>
</div>
@endsection

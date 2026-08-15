@extends('layouts.app')
@section('title','Energy Performance Center')

@section('content')
@php
  use App\Support\Energi;
  $intensitas = $r['intensitas'];
  $turun = $baseline ? $baseline->penurunanTercapai($intensitas) : 0.0;
@endphp

<div class="max-w-6xl mx-auto space-y-5">

  {{-- Kepala halaman --}}
  <section class="brand-gradient rounded-2xl p-6 md:p-7 text-white relative overflow-hidden">
    <div class="absolute -right-16 -top-16 w-64 h-64 rounded-full"
         style="background:radial-gradient(circle,rgba(42,157,143,.45),transparent 70%)"></div>

    <div class="relative flex flex-wrap items-start justify-between gap-4">
      <div class="min-w-0">
        <div class="text-[10.5px] font-bold uppercase tracking-[.18em] text-white/55">Website #6</div>
        <h2 class="font-display text-[23px] md:text-[27px] font-black leading-tight mt-1">Energy Performance Center</h2>
        <p class="text-[12.5px] text-white/70 mt-2 leading-relaxed max-w-xl">
          Seluruh sumber energi dibawa ke satu satuan sebelum dijumlahkan. Tanpa itu liter solar
          dan kilowatt-jam tidak dapat dibandingkan, apalagi ditotal — dan intensitas energi
          kehilangan artinya.
        </p>
      </div>

      <div class="glass-panel rounded-2xl px-5 py-4 text-right shrink-0">
        <div class="text-[10px] font-bold uppercase tracking-wide text-white/55">Current Energy Intensity</div>
        <div class="stat stat-xl text-white mt-1.5">{{ number_format($intensitas, 2) }}<span class="stat-unit">GJ/ton</span></div>
        @if($baseline)
          <div class="text-[11.5px] font-bold mt-2 {{ $turun >= 0 ? 'text-cam-sage' : 'text-cam-coral' }}">
            {{ $turun >= 0 ? '▼' : '▲' }} {{ number_format(abs($turun), 1) }}% vs Baseline {{ $baseline->tahun }}
          </div>
        @else
          <div class="text-[11.5px] text-white/50 mt-2">Baseline belum ditetapkan</div>
        @endif
      </div>
    </div>

    <div class="relative mt-5 pt-5 border-t border-white/10">
      <x-rentang :dari="$dari" :sampai="$sampai" :rute="route('energi.index')" gelap />
    </div>
  </section>

  {{-- Kartu ringkasan --}}
  <div class="grid gap-4 grid-cols-2 lg:grid-cols-4">
    <x-kpi label="Fuel Consumption" :nilai="Energi::ringkas($r['liter'], 2)" satuan="L"
           :ket="'Alat '.number_format($r['liter_alat']).' L · genset '.number_format($r['liter_genset']).' L'" />
    <x-kpi label="Energy Cost" :nilai="'Rp '.Energi::ringkas($r['rupiah'], 2)"
           ket="Solar, listrik, dan gas pada rentang ini" warna="#E2663A" />
    <x-kpi label="Emission" :nilai="Energi::ringkas($r['tco2e'], 1)" satuan="tCO₂e"
           :ket="$r['ton'] > 0 ? number_format($r['tco2e'] / $r['ton'], 4).' tCO₂e per ton produksi' : 'Belum ada produksi tercatat'"
           warna="#22312F" />
    <x-kpi label="Total Energy" :nilai="Energi::ringkas($r['gj'], 1)" satuan="GJ"
           :ket="number_format($r['ton']).' ton produksi · '.$r['hari'].' hari'" warna="#FF9800" />
  </div>

  {{-- Tren --}}
  <section class="kartu-lux rounded-2xl p-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h3 class="font-display text-[16px] font-black text-cam-ink">Energy Trend</h3>
        <p class="text-[11.5px] text-stone-500 mt-1">
          Intensitas harian, gigajoule per ton produksi.
          @if($baseline)<span class="text-cam-coral font-semibold">Garis putus-putus adalah sasaran tahun {{ $baseline->tahun }}.</span>@endif
        </p>
      </div>
      <div class="text-right">
        <div class="num text-[19px] font-bold text-cam-lime-deep">{{ count($tren) }}</div>
        <div class="text-[10.5px] text-stone-400">hari tercatat</div>
      </div>
    </div>

    <div class="mt-5">
      <x-garis :titik="array_column($tren,'intensitas')" :target="$baseline?->target_gj_ton"
               :tinggi="170" satuan="GJ/ton" :desimal="3" />
    </div>

    @if(count($tren))
      <div class="flex justify-between text-[10.5px] text-stone-400 mt-2">
        <span>{{ $tren[0]['tanggal']->translatedFormat('d M Y') }}</span>
        <span>{{ end($tren)['tanggal']->translatedFormat('d M Y') }}</span>
      </div>
    @endif
  </section>

  <div class="grid gap-4 lg:grid-cols-2">

    {{-- Rincian sumber --}}
    <section class="kartu-lux rounded-2xl p-6">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Fuel &amp; Energy Mix</h3>
      <p class="text-[11.5px] text-stone-500 mt-1">Porsi tiap sumber setelah disamakan ke gigajoule.</p>

      <div class="space-y-3.5 mt-5">
        @foreach($r['rincian'] as $nama => $x)
          @php $porsi = $r['gj'] > 0 ? $x['gj'] / $r['gj'] : 0; @endphp
          <div>
            <div class="flex items-baseline justify-between gap-3">
              <span class="text-[12.5px] font-semibold text-cam-ink">{{ $nama }}</span>
              <span class="num text-[12px] text-stone-500">
                {{ number_format($x['jumlah']) }} {{ $x['satuan'] }}
                <span class="text-stone-300 mx-1">·</span>
                <span class="font-bold text-cam-ink">{{ number_format($porsi * 100, 1) }}%</span>
              </span>
            </div>
            <div class="mt-1.5 h-2 rounded-full bg-stone-100 overflow-hidden">
              <div class="h-full rounded-full lime-gradient transition-all duration-700" style="width:{{ $porsi * 100 }}%"></div>
            </div>
            <div class="text-[10.5px] text-stone-400 mt-1">
              {{ number_format($x['gj'], 1) }} GJ · {{ number_format($x['tco2e'], 2) }} tCO₂e · Rp {{ Energi::ringkas($x['rupiah'], 1) }}
            </div>
          </div>
        @endforeach
      </div>
    </section>

    {{-- Peringkat unit --}}
    <section class="kartu-lux rounded-2xl p-6">
      <div class="flex items-start justify-between gap-3">
        <div>
          <h3 class="font-display text-[16px] font-black text-cam-ink">Equipment Ranking</h3>
          <p class="text-[11.5px] text-stone-500 mt-1">Liter per jam operasi, dibanding rata-rata kategorinya.</p>
        </div>
        <a href="{{ route('energi.equipment', ['dari'=>$dari->format('Y-m-d'),'sampai'=>$sampai->format('Y-m-d')]) }}"
           class="shrink-0 text-[11.5px] font-bold text-cam-lime-deep hover:underline">Semua unit →</a>
      </div>

      @if(count($teratas))
        <div class="space-y-2.5 mt-5">
          @foreach($teratas as $b)
            <a href="{{ route('energi.equipment.show', [$b['unit'], 'dari'=>$dari->format('Y-m-d'),'sampai'=>$sampai->format('Y-m-d')]) }}"
               class="flex items-center gap-3 rounded-xl border border-stone-100 px-3.5 py-2.5 hover:border-cam-lime/40 hover:bg-cam-lime-soft/40 transition">
              <span class="w-1.5 h-9 rounded-full shrink-0" style="background:{{ $b['status']['warna'] }}"></span>
              <span class="min-w-0 flex-1">
                <span class="block text-[12.5px] font-bold text-cam-ink truncate">{{ $b['unit']->kode }} — {{ $b['unit']->nama }}</span>
                <span class="block text-[10.5px] text-stone-400">{{ $b['unit']->labelKategori() }} · {{ $b['status']['label'] }}</span>
              </span>
              <span class="text-right shrink-0">
                <span class="num block text-[14px] font-bold" style="color:{{ $b['status']['warna'] }}">{{ number_format($b['l_hm'], 1) }}</span>
                <span class="block text-[10px] text-stone-400">L/HM</span>
              </span>
            </a>
          @endforeach
        </div>
      @else
        <p class="text-[12px] text-stone-400 mt-6">Belum ada catatan bahan bakar pada rentang ini.</p>
      @endif
    </section>
  </div>

  {{-- Peluang penghematan --}}
  <section class="kartu-lux rounded-2xl p-6">
    <div class="flex items-start justify-between gap-3">
      <div>
        <h3 class="font-display text-[16px] font-black text-cam-ink">Saving Opportunities</h3>
        <p class="text-[11.5px] text-stone-500 mt-1">Yang sudah disetujui atau sedang berjalan.</p>
      </div>
      <a href="{{ route('energi.hemat') }}" class="shrink-0 text-[11.5px] font-bold text-cam-lime-deep hover:underline">Kelola →</a>
    </div>

    @if($peluang->count())
      <div class="grid gap-3 sm:grid-cols-2 mt-5">
        @foreach($peluang as $o)
          <div class="rounded-xl border border-stone-100 p-4">
            <div class="flex items-start justify-between gap-2">
              <span class="text-[12.5px] font-bold text-cam-ink leading-snug">{{ $o->judul }}</span>
              <span class="shrink-0 text-[9.5px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-lg bg-cam-lime-soft text-cam-lime-deep">{{ $o->status }}</span>
            </div>
            <div class="text-[11px] text-stone-400 mt-2">
              Rp {{ Energi::ringkas($o->rupiah(), 1) }}/bulan · {{ number_format($o->tco2e(), 2) }} tCO₂e/bulan
            </div>
          </div>
        @endforeach
      </div>
    @else
      <p class="text-[12px] text-stone-400 mt-6">Belum ada peluang yang disetujui atau berjalan.</p>
    @endif
  </section>

</div>
@endsection

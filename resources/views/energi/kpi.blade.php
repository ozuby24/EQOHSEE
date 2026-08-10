@extends('layouts.app')
@section('title','Energy KPI')

@section('content')
@php
  use App\Support\Energi;
  $capai = $baseline ? $baseline->kemajuan($r['intensitas']) : null;
  $turun = $baseline ? $baseline->penurunanTercapai($r['intensitas']) : 0.0;
@endphp

<div class="max-w-5xl mx-auto space-y-5">

  <x-energi.kepala judul="Energy KPI"
      ket="Enam angka yang dipakai menilai kinerja energi. Semuanya diturunkan dari catatan
           harian yang sama, jadi tidak ada angka yang perlu dicocokkan belakangan."
      :dari="$dari" :sampai="$sampai" :rute="route('energi.kpi')" />

  {{-- Sasaran --}}
  @if($baseline)
    <section class="brand-gradient rounded-2xl p-6 text-white relative overflow-hidden">
      <div class="absolute -right-10 -bottom-16 w-56 h-56 rounded-full"
           style="background:radial-gradient(circle,rgba(42,157,143,.4),transparent 70%)"></div>

      <div class="relative flex flex-wrap items-end justify-between gap-5">
        <div>
          <div class="text-[10.5px] font-bold uppercase tracking-[.18em] text-white/55">Kemajuan Menuju Sasaran {{ $baseline->tahun }}</div>
          <div class="stat stat-xl text-white mt-2">{{ number_format($capai * 100, 1) }}<span class="stat-unit">%</span></div>
          <p class="text-[11.5px] text-white/60 mt-2 max-w-md leading-relaxed">
            Diukur pada rentang baseline→target, bukan terhadap baseline saja: yang ingin
            diketahui adalah seberapa jauh perjalanan yang sudah ditempuh dari seluruh jarak
            yang direncanakan.
          </p>
        </div>

        <div class="flex gap-6 text-right">
          @foreach ([
            ['Baseline', number_format($baseline->baseline_gj_ton, 3)],
            ['Sekarang', number_format($r['intensitas'], 3)],
            ['Target', number_format($baseline->target_gj_ton, 3)],
          ] as [$l, $v])
            <div>
              <div class="num text-[17px] font-bold text-white">{{ $v }}</div>
              <div class="text-[10px] text-white/50 mt-1">{{ $l }}</div>
            </div>
          @endforeach
        </div>
      </div>

      <div class="relative mt-5 h-2.5 rounded-full bg-white/15 overflow-hidden">
        <div class="h-full rounded-full bg-cam-sage transition-all duration-700" style="width:{{ $capai * 100 }}%"></div>
      </div>
    </section>
  @else
    <div class="rounded-2xl bg-cam-sand/60 border border-cam-sand px-5 py-4 text-[12.5px] text-cam-ink leading-relaxed">
      Baseline belum ditetapkan, jadi capaian belum dapat dinilai — angka intensitas tanpa
      pembandingnya hanya menjadi bilangan.
      <a href="{{ route('energi.baseline') }}" class="font-bold text-cam-lime-deep hover:underline">Tetapkan baseline →</a>
    </div>
  @endif

  {{-- Enam KPI --}}
  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <x-kpi label="Energy Intensity" :nilai="number_format($r['intensitas'], 3)" satuan="GJ/ton"
           :ket="'Total '.number_format($r['gj'], 1).' GJ untuk '.number_format($r['ton']).' ton'" />

    <x-kpi label="Penurunan vs Baseline" :nilai="number_format($turun, 1).'%'"
           :warna="$turun >= 0 ? '#0F766E' : '#E2663A'"
           :ket="$baseline ? 'Terhadap baseline '.$baseline->tahun : 'Baseline belum ditetapkan'" />

    <x-kpi label="Fuel Ratio" :nilai="number_format($r['l_ton'], 3)" satuan="L/ton"
           :ket="number_format($r['liter']).' L solar terpakai'" warna="#2A9D8F" />

    <x-kpi label="Electricity Ratio" :nilai="number_format($r['kwh_ton'], 3)" satuan="kWh/ton"
           :ket="number_format($r['kwh']).' kWh terpakai'" warna="#22312F" />

    <x-kpi label="Carbon Intensity" :nilai="$r['ton'] > 0 ? number_format($r['tco2e'] / $r['ton'], 4) : '0'" satuan="tCO₂e/ton"
           :ket="'Total '.number_format($r['tco2e'], 1).' tCO₂e'" warna="#22312F" />

    <x-kpi label="Energy Cost Ratio" :nilai="$r['ton'] > 0 ? 'Rp '.number_format($r['rupiah'] / $r['ton']) : 'Rp 0'" satuan="/ton"
           :ket="'Total Rp '.Energi::ringkas($r['rupiah'], 2)" warna="#E2663A" />
  </div>

  {{-- Penghematan terwujud --}}
  <section class="kartu-lux rounded-2xl p-6">
    <div class="flex items-start justify-between gap-3">
      <div>
        <h3 class="font-display text-[16px] font-black text-cam-ink">Penghematan yang Sudah Berjalan</h3>
        <p class="text-[11.5px] text-stone-500 mt-1">
          Hanya peluang berstatus berjalan atau selesai yang dihitung — usulan yang belum
          dikerjakan bukan penghematan.
        </p>
      </div>
      <a href="{{ route('energi.hemat') }}" class="shrink-0 text-[11.5px] font-bold text-cam-lime-deep hover:underline">Kelola →</a>
    </div>

    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mt-5">
      <x-kpi label="Program Berjalan" :nilai="$hemat['jumlah']" satuan="program" ket="Berstatus berjalan atau selesai" />
      <x-kpi label="Energi Dihemat" :nilai="number_format($hemat['gj'], 1)" satuan="GJ/bulan" warna="#2A9D8F" ket="Perkiraan per bulan" />
      <x-kpi label="Emisi Dihindari" :nilai="number_format($hemat['tco2e'], 2)" satuan="tCO₂e/bulan" warna="#22312F" ket="Perkiraan per bulan" />
      <x-kpi label="Biaya Dihemat" :nilai="'Rp '.Energi::ringkas($hemat['rupiah'], 2)" satuan="/bulan" warna="#E2663A" ket="Perkiraan per bulan" />
    </div>
  </section>

</div>
@endsection

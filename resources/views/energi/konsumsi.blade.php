@extends('layouts.app')
@section('title','Energy Consumption')

@section('content')
@php use App\Support\Energi; @endphp

<div class="max-w-5xl mx-auto space-y-5">

  <x-energi.kepala judul="Energy Consumption"
      ket="Seluruh sumber energi disandingkan setelah disamakan ke gigajoule. Angka mentahnya
           tetap ditampilkan pada satuan asalnya — liter, kilowatt-jam, meter kubik — karena
           itulah yang dicatat orang lapangan."
      :dari="$dari" :sampai="$sampai" :rute="route('energi.konsumsi')">

    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mt-6 pt-6 hairline border-b-0">
      @foreach ([
        ['Total Energi', number_format($r['gj'], 1), 'GJ', '#F57C00'],
        ['Intensitas', number_format($r['intensitas'], 3), 'GJ/ton', '#FF9800'],
        ['Emisi', number_format($r['tco2e'], 1), 'tCO₂e', '#22312F'],
        ['Biaya', 'Rp '.Energi::ringkas($r['rupiah'], 2), null, '#E2663A'],
      ] as [$l, $v, $s, $w])
        <div>
          <div class="stat stat-sm" style="color:{{ $w }}">{{ $v }}@if($s)<span class="stat-unit">{{ $s }}</span>@endif</div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">{{ $l }}</div>
        </div>
      @endforeach
    </div>
  </x-energi.kepala>

  {{-- Tren harian tiap sumber --}}
  <div class="grid gap-4 lg:grid-cols-2">
    @foreach ([
      ['Solar Harian', 'liter', 'L', 0, '#F57C00'],
      ['Listrik Harian', 'kwh', 'kWh', 0, '#FF9800'],
    ] as [$judul, $kunci, $satuan, $desimal, $warna])
      <section class="kartu-lux rounded-2xl p-6">
        <h3 class="font-display text-[16px] font-black text-cam-ink">{{ $judul }}</h3>
        <p class="text-[11.5px] text-stone-500 mt-1">
          Garis putus-putus adalah rata-rata harian pada rentang ini.
        </p>
        <div class="mt-5">
          @php $deret = array_column($tren, $kunci); @endphp
          <x-garis :titik="$deret" :warna="$warna" :satuan="$satuan" :desimal="$desimal" :tinggi="140"
                   :target="count($deret) ? array_sum($deret) / count($deret) : null" />
        </div>
      </section>
    @endforeach
  </div>

  {{-- Rincian per sumber --}}
  <section class="kartu-lux rounded-2xl p-6">
    <h3 class="font-display text-[16px] font-black text-cam-ink">Rincian per Sumber</h3>

    <div class="overflow-x-auto mt-4 -mx-1">
      <table class="w-full text-[12.5px] min-w-[560px]">
        <thead>
          <tr class="text-[10.5px] font-bold uppercase tracking-wide text-stone-400 hairline">
            <th class="text-left py-2.5">Sumber</th>
            <th class="num py-2.5">Jumlah</th>
            <th class="num py-2.5">Gigajoule</th>
            <th class="num py-2.5">Porsi</th>
            <th class="num py-2.5">tCO₂e</th>
            <th class="num py-2.5">Biaya</th>
          </tr>
        </thead>
        <tbody>
          @foreach($r['rincian'] as $nama => $x)
            <tr class="hairline">
              <td class="py-2.5 font-semibold text-cam-ink">{{ $nama }}</td>
              <td class="num py-2.5">{{ number_format($x['jumlah'], 1) }} <span class="text-stone-400">{{ $x['satuan'] }}</span></td>
              <td class="num py-2.5">{{ number_format($x['gj'], 2) }}</td>
              <td class="num py-2.5 font-bold text-cam-lime-deep">{{ $r['gj'] > 0 ? number_format($x['gj'] / $r['gj'] * 100, 1) : '0,0' }}%</td>
              <td class="num py-2.5">{{ number_format($x['tco2e'], 2) }}</td>
              <td class="num py-2.5">Rp {{ number_format($x['rupiah']) }}</td>
            </tr>
          @endforeach
          <tr class="font-bold text-cam-ink">
            <td class="py-3">Total</td>
            <td class="num py-3">—</td>
            <td class="num py-3">{{ number_format($r['gj'], 2) }}</td>
            <td class="num py-3">100%</td>
            <td class="num py-3">{{ number_format($r['tco2e'], 2) }}</td>
            <td class="num py-3">Rp {{ number_format($r['rupiah']) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  {{-- Rasio terhadap produksi --}}
  <section class="kartu-lux rounded-2xl p-6">
    <h3 class="font-display text-[16px] font-black text-cam-ink">Rasio terhadap Produksi</h3>
    <p class="text-[11.5px] text-stone-500 mt-1">
      Pemakaian yang naik belum tentu buruk bila produksinya naik lebih cepat. Rasio inilah
      yang membedakan keduanya.
    </p>

    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mt-5">
      <x-kpi label="Liter per Ton" :nilai="number_format($r['l_ton'], 3)" satuan="L/ton"
             :ket="number_format($r['ton']).' ton produksi'" />
      <x-kpi label="Liter per BCM" :nilai="number_format($r['l_bcm'], 3)" satuan="L/BCM"
             :ket="number_format($r['bcm']).' BCM'" warna="#FF9800" />
      <x-kpi label="kWh per Ton" :nilai="number_format($r['kwh_ton'], 3)" satuan="kWh/ton"
             :ket="number_format($r['kwh']).' kWh terpakai'" warna="#22312F" />
      <x-kpi label="Solar per Hari" :nilai="Energi::ringkas($r['liter_hari'], 1)" satuan="L/hari"
             :ket="'Rata-rata '.$r['hari'].' hari'" warna="#E2663A" />
    </div>

    @if($baseline)
      @php $turun = $baseline->penurunanTercapai($r['intensitas']); @endphp
      <div class="rounded-xl {{ $turun >= 0 ? 'bg-cam-lime-soft border-cam-lime/25' : 'bg-cam-coral-soft border-cam-coral/25' }} border px-4 py-3 mt-5 text-[12px] leading-relaxed">
        Intensitas rentang ini <strong class="num">{{ number_format($r['intensitas'], 3) }}</strong> GJ/ton, terhadap
        baseline {{ $baseline->tahun }} sebesar <strong class="num">{{ number_format($baseline->baseline_gj_ton, 3) }}</strong> —
        <strong>{{ $turun >= 0 ? 'turun' : 'naik' }} {{ number_format(abs($turun), 1) }}%</strong>.
        Target tahun ini <span class="num">{{ number_format($baseline->target_gj_ton, 3) }}</span> GJ/ton.
      </div>
    @endif
  </section>

</div>
@endsection

@extends('layouts.app')
@section('title','Electricity Management')

@section('content')
@php use App\Support\Energi; @endphp

<div class="max-w-5xl mx-auto space-y-5">

  <x-energi.kepala judul="Electricity Management"
      ket="Listrik dipisah menurut area dan sumbernya. Genset dan PLN sengaja tidak dijumlahkan
           begitu saja: biaya dan emisi tiap kilowatt-jamnya berbeda, dan genset masih menenggak
           solar dari tangki yang sama dengan alat berat."
      :dari="$dari" :sampai="$sampai" :rute="route('energi.listrik')">

    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mt-6 pt-6 hairline border-b-0">
      @foreach ([
        ['Total Listrik', number_format($total['kwh']), 'kWh', '#F57C00'],
        ['Setara Energi', number_format($total['gj'], 1), 'GJ', '#FF9800'],
        ['Emisi Listrik', number_format($total['tco2e'], 2), 'tCO₂e', '#22312F'],
        ['Biaya Listrik', 'Rp '.Energi::ringkas($total['rupiah'], 2), null, '#E2663A'],
      ] as [$l, $v, $s, $w])
        <div>
          <div class="stat stat-sm" style="color:{{ $w }}">{{ $v }}@if($s)<span class="stat-unit">{{ $s }}</span>@endif</div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">{{ $l }}</div>
        </div>
      @endforeach
    </div>
  </x-energi.kepala>

  {{-- PLN vs genset --}}
  <div class="grid gap-4 lg:grid-cols-2">
    <section class="kartu-lux rounded-2xl p-6">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Sumber Daya</h3>
      @php $tot = max(1e-9, $total['kwh']); @endphp

      <div class="space-y-4 mt-5">
        @foreach ([
          ['PLN', $pln['kwh'], '#F57C00'],
          ['Genset', $genset['kwh'], '#D9993A'],
        ] as [$nama, $kwh, $warna])
          <div>
            <div class="flex items-baseline justify-between gap-3">
              <span class="text-[12.5px] font-bold text-cam-ink">{{ $nama }}</span>
              <span class="num text-[12px] text-stone-500">
                {{ number_format($kwh) }} kWh
                <span class="text-stone-300 mx-1">·</span>
                <span class="font-bold text-cam-ink">{{ number_format($kwh / $tot * 100, 1) }}%</span>
              </span>
            </div>
            <div class="mt-1.5 h-2 rounded-full bg-stone-100 overflow-hidden">
              <div class="h-full rounded-full transition-all duration-700" style="width:{{ $kwh / $tot * 100 }}%; background:{{ $warna }}"></div>
            </div>
          </div>
        @endforeach
      </div>

      <div class="rounded-xl bg-cam-sand/50 border border-cam-sand px-4 py-3 mt-5 text-[11.5px] leading-relaxed text-cam-ink">
        Genset menghasilkan <strong class="num">{{ number_format($genset['efisiensi'], 2) }}</strong> kWh tiap liter solar
        selama <span class="num">{{ number_format($genset['jam'], 1) }}</span> jam operasi.
        Genset sehat umumnya berada di sekitar 3–4 kWh per liter; angka yang jauh di bawahnya
        menandakan beban terlalu ringan atau mesin yang perlu diperiksa.
      </div>
    </section>

    <section class="kartu-lux rounded-2xl p-6">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Angka Kunci</h3>
      <div class="grid gap-4 grid-cols-2 mt-5">
        <x-kpi label="kWh per Ton" :nilai="number_format($total['perTon'], 3)" satuan="kWh/ton"
               ket="Listrik yang dipakai untuk tiap ton produksi" />
        <x-kpi label="Solar Genset" :nilai="number_format($genset['liter'])" satuan="L"
               ket="Sudah ikut terhitung pada total solar" warna="#D9993A" />
        <x-kpi label="Efisiensi Genset" :nilai="number_format($genset['efisiensi'], 2)" satuan="kWh/L"
               ket="Semakin tinggi semakin baik" warna="#FF9800" />
        <x-kpi label="Area Tercatat" :nilai="count($perArea)" satuan="area"
               ket="Area tanpa catatan tidak ditampilkan" warna="#22312F" />
      </div>
    </section>
  </div>

  {{-- Per area --}}
  <section class="kartu-lux rounded-2xl p-6">
    <h3 class="font-display text-[16px] font-black text-cam-ink">Pemakaian per Area</h3>
    <p class="text-[11.5px] text-stone-500 mt-1 leading-relaxed">
      Faktor beban membandingkan pemakaian rata-rata terhadap beban puncaknya. Faktor rendah
      berarti kapasitas terpasang jauh melebihi pemakaian biasanya — langganan terbayar tanpa
      terpakai.
    </p>

    @if(count($perArea))
      <div class="overflow-x-auto mt-4 -mx-1">
        <table class="w-full text-[12.5px] min-w-[600px]">
          <thead>
            <tr class="text-[10.5px] font-bold uppercase tracking-wide text-stone-400 hairline">
              <th class="text-left py-2.5">Area</th>
              <th class="num py-2.5">kWh</th>
              <th class="num py-2.5">GJ</th>
              <th class="num py-2.5">Puncak (kW)</th>
              <th class="num py-2.5">Jam</th>
              <th class="num py-2.5">Faktor Beban</th>
              <th class="num py-2.5">Biaya</th>
            </tr>
          </thead>
          <tbody>
            @foreach($perArea as $a)
              <tr class="hairline">
                <td class="py-2.5 font-semibold text-cam-ink">{{ $a['nama'] }}</td>
                <td class="num py-2.5">{{ number_format($a['kwh']) }}</td>
                <td class="num py-2.5">{{ number_format($a['gj'], 2) }}</td>
                <td class="num py-2.5">{{ number_format($a['puncak'], 1) }}</td>
                <td class="num py-2.5">{{ number_format($a['jam'], 1) }}</td>
                <td class="num py-2.5 font-bold {{ $a['faktor'] > 0 && $a['faktor'] < 40 ? 'text-cam-coral' : 'text-cam-lime-deep' }}">
                  {{ number_format($a['faktor'], 1) }}%
                </td>
                <td class="num py-2.5">Rp {{ number_format($a['rupiah']) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <p class="text-[12px] text-stone-400 mt-6">Belum ada catatan listrik pada rentang ini.</p>
    @endif
  </section>

</div>
@endsection

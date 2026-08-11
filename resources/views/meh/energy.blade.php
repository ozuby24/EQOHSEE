@extends('layouts.app')
@section('title','Energy Dashboard')

@section('content')
@php use App\Support\Engineering as E; @endphp

<div class="max-w-6xl mx-auto space-y-5">

  <section class="kartu-lux rounded-2xl p-6">
    <h2 class="font-display text-[20px] font-black text-cam-ink">Energy Performance</h2>
    <p class="text-[12.5px] text-stone-500 mt-1.5 leading-relaxed max-w-3xl">
      Seluruh sumber energi disamakan ke gigajoule sebelum dijumlahkan. Tanpa itu liter solar dan
      kilowatt-jam tidak dapat dibandingkan, apalagi ditotal — dan intensitas energi kehilangan artinya.
    </p>
    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mt-6 pt-6 hairline border-b-0">
      @foreach ([
        ['Total Energy', number_format($e['gj'],1), 'GJ', '#0F766E'],
        ['Energy Intensity', number_format($e['intensitas'],5), 'GJ/ton', '#2A9D8F'],
        ['Emisi', number_format($e['tco2e'],2), 'tCO₂e', '#22312F'],
        ['Biaya Energi', 'Rp '.number_format($e['rupiah']/1e6,1).' jt', null, '#E2663A'],
      ] as [$l,$v,$s,$w])
        <div>
          <div class="stat stat-sm" style="color:{{ $w }}">{{ $v }}@if($s)<span class="stat-unit">{{ $s }}</span>@endif</div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">{{ $l }}</div>
        </div>
      @endforeach
    </div>
  </section>

  <div class="grid gap-4 grid-cols-2 lg:grid-cols-3">
    <x-kpi label="Energy Consumption" :nilai="number_format($e['gjHari'],1)" satuan="GJ/hari" ket="Rata-rata harian" />
    <x-kpi label="Diesel Consumption" :nilai="number_format($e['literHari'])" satuan="L/hari"
           :ket="number_format($e['liter']).' L pada periode ini'" warna="#C08A3E" />
    <x-kpi label="Electricity Consumption" :nilai="number_format($e['kwhHari'])" satuan="kWh/hari"
           :ket="number_format($e['kwh']).' kWh pada periode ini'" warna="#4C9AFF" />
    <x-kpi label="Fuel Ratio" :nilai="number_format($e['fuelRatio'],4)" satuan="L/ton"
           ket="Solar ÷ produksi" warna="#2A9D8F" />
    <x-kpi label="Renewable Energy" :nilai="number_format($e['terbarukanPersen'],1)" satuan="%"
           :ket="number_format(E::TERBARUKAN_KWH).' kWh/hari dari PLTS site'" :rasio="$e['terbarukanPersen']/100" warna="#0F766E" />
    <x-kpi label="Energy Saving" :nilai="number_format(abs($e['penurunan']),1)" satuan="%"
           :ket="$e['penurunan'] >= 0 ? 'Di bawah baseline tahun lalu' : 'Di atas baseline — perlu ditelusuri'"
           :warna="$e['penurunan'] >= 0 ? '#0F766E' : '#E2663A'" />
  </div>

  <div class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
    <section class="kartu-lux rounded-2xl p-6">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Energy Consumption Trend</h3>
      <p class="text-[11.5px] text-stone-500 mt-1">Intensitas energi harian terhadap sasaran.</p>
      <div class="mt-5">
        <x-garis :titik="array_column($e['deret'],'intensitas')" :target="$e['target']"
                 satuan="GJ/ton" :desimal="4" :tinggi="250" />
      </div>
      <div class="flex flex-wrap gap-x-6 gap-y-2 mt-4 text-[11.5px] text-stone-500">
        <span>Baseline <b class="num text-cam-ink">{{ number_format($e['baseline'],4) }}</b> GJ/ton</span>
        <span>Sekarang <b class="num text-cam-ink">{{ number_format($e['intensitas'],5) }}</b> GJ/ton</span>
        <span>Target <b class="num text-cam-coral">{{ number_format($e['target'],4) }}</b> GJ/ton</span>
      </div>
    </section>

    <section class="kartu-lux rounded-2xl p-6">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Bauran Energi</h3>
      <p class="text-[11.5px] text-stone-500 mt-1">Porsi tiap sumber setelah disamakan ke gigajoule.</p>
      <x-donat :data="[
        ['nama'=>'Solar','nilai'=>$e['liter']*E::GJ_PER_LITER,'warna'=>'#0F766E'],
        ['nama'=>'Listrik','nilai'=>$e['kwh']*E::GJ_PER_KWH,'warna'=>'#4C9AFF'],
      ]" :desimal="1" :tengah="number_format($e['gj'])" tengahKet="GJ total" :tinggi="215" />
    </section>
  </div>

  <section class="kartu-lux rounded-2xl p-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h3 class="font-display text-[16px] font-black text-cam-ink">Program Penghematan</h3>
        <p class="text-[11.5px] text-stone-500 mt-1 max-w-2xl">
          Dicatat pada satuan asalnya — liter dan kilowatt-jam. Rupiah dan karbonnya dihitung saat dibaca,
          jadi perubahan harga bahan bakar tidak membuat angka lama menjadi keliru.
        </p>
      </div>
      <div class="text-right shrink-0">
        <div class="num text-[19px] font-bold text-cam-lime-deep">Rp {{ number_format($terwujud['rupiah']/1e6,1) }} jt</div>
        <div class="text-[10.5px] text-stone-400">{{ $terwujud['jumlah'] }} program berjalan · per bulan</div>
      </div>
    </div>

    <div class="overflow-x-auto mt-5 -mx-1">
      <table class="w-full text-[12.5px] min-w-[620px]">
        <thead>
          <tr class="text-[10.5px] font-bold uppercase tracking-wide text-stone-400 hairline">
            <th class="text-left py-2.5">Program</th>
            <th class="num py-2.5">Hemat</th>
            <th class="num py-2.5">GJ/bln</th>
            <th class="num py-2.5">tCO₂e/bln</th>
            <th class="num py-2.5">Nilai/bln</th>
            <th class="text-left py-2.5 pl-3">Status</th>
          </tr>
        </thead>
        <tbody>
          @foreach($program as $x)
            <tr class="hairline">
              <td class="py-2.5 font-semibold text-cam-ink">{{ $x['judul'] }}</td>
              <td class="num py-2.5">{{ $x['liter'] ? number_format($x['liter']).' L' : number_format($x['kwh']).' kWh' }}</td>
              <td class="num py-2.5">{{ number_format($x['gj'],1) }}</td>
              <td class="num py-2.5">{{ number_format($x['tco2e'],2) }}</td>
              <td class="num py-2.5">Rp {{ number_format($x['rupiah']) }}</td>
              <td class="py-2.5 pl-3">
                <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-1 rounded-lg
                             {{ in_array($x['status'],['Berjalan','Selesai']) ? 'bg-cam-lime-soft text-cam-lime-deep' : 'bg-stone-100 text-stone-500' }}">
                  {{ $x['status'] }}</span>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>

</div>
@endsection

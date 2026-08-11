@extends('layouts.app')
@section('title','Engineering KPI')

@section('content')
@php
  use App\Support\Engineering as E;
  $pr = E::PRODUKTIVITAS;
  $payloadUnit = array_filter(E::armada(), fn($u) => $u['payload']);

  // Tiap indikator membawa rumusnya. Angka tanpa rumus tidak dapat
  // diperiksa siapa pun, dan indikator yang tidak dapat diperiksa
  // cepat atau lambat dihitung berbeda oleh dua orang.
  $kelompok = [
    'Equipment' => [
      ['Physical Availability',   number_format($a['pa'],1),        '%',       '(Kerja + Standby) ÷ Jam terjadwal × 100'],
      ['Mechanical Availability', number_format($a['ma'],1),        '%',       'Jam kerja ÷ (Jam kerja + Jam rusak) × 100'],
      ['Use of Availability',     number_format($a['ua'],1),        '%',       'Jam kerja ÷ (Kerja + Standby) × 100'],
      ['Utilization',             number_format($a['utilisasi'],1), '%',       'Jam kerja ÷ Jam terjadwal × 100'],
      ['Productivity',            number_format(E::bagi($p['ton'],$a['kerja']),1), 'ton/jam', 'Produksi ÷ Jam kerja armada'],
      ['Fuel Ratio',              number_format(E::bagi($a['liter'],$p['ton']),4), 'L/ton',   'Solar terpakai ÷ Produksi'],
    ],
    'Mining' => [
      ['Production',       number_format($p['tonHari']),          'ton/hari', 'Rata-rata ROM terangkut per hari'],
      ['Stripping Ratio',  number_format($p['sr'],2),             'BCM/ton',  'Overburden dipindahkan ÷ Batu bara terangkut'],
      ['Cycle Time',       number_format($pr['cycle_menit'],1),   'menit',    'Rata-rata satu siklus muat–angkut–buang–kembali'],
      ['Hauling Distance', number_format($pr['jarak_km'],1),      'km',       'Jarak rata-rata front ke disposal atau ROM'],
      ['Truck Factor',     number_format($pr['truck_factor'],2),  '',         'Muatan nyata ÷ Kapasitas nominal vessel'],
      ['Payload',          number_format(E::bagi(array_sum(array_column($payloadUnit,'payload')), count($payloadUnit)),1), 'ton', 'Rata-rata muatan nominal armada angkut'],
    ],
    'Energy' => [
      ['Energy Consumption',      number_format($e['gjHari'],1),      'GJ/hari',  'Solar × 0,0358 + Listrik × 0,0036'],
      ['Energy Intensity',        number_format($e['intensitas'],5),  'GJ/ton',   'Energi total ÷ Produksi'],
      ['Fuel Consumption',        number_format($a['fuelRate'],1),    'L/jam',    'Solar terpakai ÷ Jam kerja armada'],
      ['Electricity Consumption', number_format($e['kwhHari']),       'kWh/hari', 'Rata-rata pemakaian listrik harian'],
      ['Energy Saving',           number_format($e['penurunan'],1),   '%',        '(Baseline − Sekarang) ÷ Baseline × 100'],
    ],
  ];
@endphp

<div class="max-w-6xl mx-auto space-y-5">

  <section class="kartu-lux rounded-2xl p-6">
    <h2 class="font-display text-[20px] font-black text-cam-ink">Engineering KPI</h2>
    <p class="text-[12.5px] text-stone-500 mt-1.5 leading-relaxed max-w-3xl">
      Tiap indikator membawa rumusnya. Angka tanpa rumus tidak dapat diperiksa siapa pun — dan
      indikator yang tidak dapat diperiksa cepat atau lambat dihitung berbeda oleh dua orang.
    </p>
  </section>

  @foreach($kelompok as $nama => $daftar)
    <section>
      <h3 class="flex items-center gap-3 text-[11.5px] font-bold uppercase tracking-[.12em] text-stone-400 mb-3">
        {{ $nama }}<span class="flex-1 h-px bg-stone-200"></span>
      </h3>
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($daftar as [$judul,$nilai,$satuan,$rumus])
          <article class="kartu-lux rounded-2xl p-5">
            <div class="text-[12.5px] font-bold text-cam-ink">{{ $judul }}</div>
            <div class="stat stat-sm text-cam-lime-deep mt-2">{{ $nilai }}@if($satuan)<span class="stat-unit">{{ $satuan }}</span>@endif</div>
            <div class="text-[10.5px] text-stone-400 mt-3 pt-3 border-t border-dashed border-stone-200 leading-relaxed">
              {{ $rumus }}
            </div>
          </article>
        @endforeach
      </div>
    </section>
  @endforeach

</div>
@endsection

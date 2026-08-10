@extends('layouts.app')
@section('title','Carbon & Emission')

@section('content')
@php
  use App\Support\Energi;
  // Setara pohon: satu pohon tropis menyerap ±22 kg CO₂ per tahun.
  $pohon = $r['tco2e'] > 0 ? $r['tco2e'] * 1000 / 22 : 0;
@endphp

<div class="max-w-5xl mx-auto space-y-5">

  <x-energi.kepala judul="Carbon &amp; Emission"
      ket="Emisi dihitung dari catatan energi yang sama, bukan dicatat terpisah. Itu menutup
           celah yang lazim: laporan energi dan laporan emisi yang tidak pernah cocok karena
           masing-masing punya sumber angkanya sendiri."
      :dari="$dari" :sampai="$sampai" :rute="route('energi.karbon')">

    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mt-6 pt-6 hairline border-b-0">
      @foreach ([
        ['Total Emisi', number_format($r['tco2e'], 2), 'tCO₂e', '#22312F'],
        ['Intensitas Karbon', $r['ton'] > 0 ? number_format($r['tco2e'] / $r['ton'], 4) : '0', 'tCO₂e/ton', '#0F766E'],
        ['Emisi Solar', number_format(Energi::literKeCo2($r['liter']), 2), 'tCO₂e', '#E2663A'],
        ['Emisi Listrik', number_format(Energi::kwhKeCo2($r['kwh']), 2), 'tCO₂e', '#2A9D8F'],
      ] as [$l, $v, $s, $w])
        <div>
          <div class="stat stat-sm" style="color:{{ $w }}">{{ $v }}<span class="stat-unit">{{ $s }}</span></div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">{{ $l }}</div>
        </div>
      @endforeach
    </div>
  </x-energi.kepala>

  <div class="grid gap-4 lg:grid-cols-2">

    {{-- Sumber emisi --}}
    <section class="kartu-lux rounded-2xl p-6">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Sumber Emisi</h3>
      <p class="text-[11.5px] text-stone-500 mt-1">Porsi tiap sumber terhadap seluruh emisi rentang ini.</p>

      <div class="space-y-3.5 mt-5">
        @foreach($r['rincian'] as $nama => $x)
          @php $porsi = $r['tco2e'] > 0 ? $x['tco2e'] / $r['tco2e'] : 0; @endphp
          <div>
            <div class="flex items-baseline justify-between gap-3">
              <span class="text-[12.5px] font-semibold text-cam-ink">{{ $nama }}</span>
              <span class="num text-[12px] text-stone-500">
                {{ number_format($x['tco2e'], 2) }} tCO₂e
                <span class="text-stone-300 mx-1">·</span>
                <span class="font-bold text-cam-ink">{{ number_format($porsi * 100, 1) }}%</span>
              </span>
            </div>
            <div class="mt-1.5 h-2 rounded-full bg-stone-100 overflow-hidden">
              <div class="h-full rounded-full transition-all duration-700"
                   style="width:{{ $porsi * 100 }}%; background:#22312F"></div>
            </div>
          </div>
        @endforeach
      </div>

      <div class="rounded-xl bg-cam-sand/50 border border-cam-sand px-4 py-3 mt-5 text-[11.5px] leading-relaxed text-cam-ink">
        Faktor yang dipakai: <span class="num">{{ Energi::TCO2E_PER_LITER * 1000 }}</span> kg CO₂e tiap liter solar,
        <span class="num">{{ Energi::TCO2E_PER_KWH * 1000 }}</span> kg tiap kilowatt-jam listrik jaringan, dan
        <span class="num">{{ Energi::TCO2E_PER_M3_GAS * 1000 }}</span> kg tiap meter kubik gas. Faktor disimpan di satu
        tempat, jadi memperbaruinya langsung tercermin ke seluruh riwayat.
      </div>
    </section>

    {{-- Lingkup dan setara --}}
    <section class="kartu-lux rounded-2xl p-6">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Lingkup Emisi</h3>
      <p class="text-[11.5px] text-stone-500 mt-1">
        Pembakaran di lokasi masuk Lingkup 1; listrik jaringan yang dibeli masuk Lingkup 2.
        Solar genset tetap Lingkup 1 karena dibakar sendiri, meski keluarannya berupa listrik.
      </p>

      @php
        $s1 = Energi::literKeCo2($r['liter']) + Energi::m3KeCo2($r['m3']);
        $s2 = Energi::kwhKeCo2($r['kwh']);
        $st = max(1e-9, $s1 + $s2);
      @endphp

      <div class="grid gap-4 grid-cols-2 mt-5">
        <x-kpi label="Lingkup 1 — Langsung" :nilai="number_format($s1, 2)" satuan="tCO₂e"
               :rasio="$s1 / $st" :ket="number_format($s1 / $st * 100, 1).'% dari total'" warna="#E2663A" />
        <x-kpi label="Lingkup 2 — Listrik" :nilai="number_format($s2, 2)" satuan="tCO₂e"
               :rasio="$s2 / $st" :ket="number_format($s2 / $st * 100, 1).'% dari total'" warna="#2A9D8F" />
      </div>

      <div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 px-4 py-3 mt-4 text-[11.5px] leading-relaxed text-cam-lime-deep">
        Emisi rentang ini setara dengan serapan tahunan sekitar
        <strong class="num">{{ number_format($pohon) }}</strong> pohon tropis dewasa.
        Perbandingan ini untuk memberi ukuran, bukan untuk menggantikan penghitungan resmi.
      </div>
    </section>
  </div>

  {{-- Emisi yang dihindari --}}
  <section class="kartu-lux rounded-2xl p-6">
    <div class="flex items-start justify-between gap-3">
      <div>
        <h3 class="font-display text-[16px] font-black text-cam-ink">Emisi yang Dihindari</h3>
        <p class="text-[11.5px] text-stone-500 mt-1">Dari program penghematan yang benar-benar berjalan atau sudah selesai.</p>
      </div>
      <a href="{{ route('energi.hemat') }}" class="shrink-0 text-[11.5px] font-bold text-cam-lime-deep hover:underline">Program →</a>
    </div>

    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mt-5">
      <x-kpi label="Program Berjalan" :nilai="$hemat['jumlah']" satuan="program" />
      <x-kpi label="Emisi Dihindari" :nilai="number_format($hemat['tco2e'], 2)" satuan="tCO₂e/bulan" warna="#22312F" />
      <x-kpi label="Setara Setahun" :nilai="number_format($hemat['tco2e'] * 12, 1)" satuan="tCO₂e/tahun" warna="#2A9D8F"
             ket="Bila laju bulanan bertahan" />
      <x-kpi label="Nilai Penghematan" :nilai="'Rp '.Energi::ringkas($hemat['rupiah'] * 12, 2)" satuan="/tahun" warna="#E2663A" />
    </div>
  </section>

</div>
@endsection

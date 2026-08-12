@extends('layouts.app')
@section('title','Mining Engineering Hub')

@section('content')
@php use App\Support\Engineering as E; @endphp

<div class="max-w-6xl mx-auto space-y-5">

  {{-- Kepala: satu kalimat pembuka dan angka yang paling sering ditanya dulu --}}
  <section class="brand-gradient rounded-2xl p-6 md:p-7 text-white relative overflow-hidden">
    <div class="absolute -right-16 -top-16 w-64 h-64 rounded-full"
         style="background:radial-gradient(circle,rgba(42,157,143,.45),transparent 70%)"></div>

    <div class="relative">
      <div class="text-[10.5px] font-bold uppercase tracking-[.18em] text-white/55">Website #7 · Data Acuan</div>
      <h2 class="font-display text-[23px] md:text-[27px] font-black leading-tight mt-1">Mining Engineering Hub</h2>
      <p class="text-[12.5px] text-white/70 mt-2 leading-relaxed max-w-2xl">
        Produksi hari terakhir {{ number_format($p['terakhir']['ton']) }} ton terhadap target
        {{ number_format($p['terakhir']['target']) }} ton — capaian
        {{ number_format(E::bagi($p['terakhir']['ton'], $p['terakhir']['target']) * 100, 1) }}%.
        {{ $a['beroperasi'] }} dari {{ $a['jumlah'] }} unit sedang beroperasi.
      </p>

      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6 pt-5 border-t border-white/10">
        @foreach ([
          [number_format($p['capaian'],1).'%', 'Capaian pekan ini'],
          [number_format($p['sr'],2), 'Stripping ratio'],
          [number_format($a['pa'],1).'%', 'Physical availability'],
          [number_format(abs($e['penurunan']),1).'%', 'Intensitas '.($e['penurunan']>=0?'turun':'naik').' vs baseline'],
        ] as [$v,$l])
          <div>
            <div class="stat stat-sm text-white">{{ $v }}</div>
            <div class="text-[10.5px] text-white/50 mt-1.5 leading-snug">{{ $l }}</div>
          </div>
        @endforeach
      </div>
    </div>
  </section>

  {{-- KPI utama --}}
  <div class="grid gap-4 grid-cols-2 lg:grid-cols-3">
    <x-kpi label="Production" :nilai="number_format($p['terakhir']['ton'])" satuan="ton/hari"
           :ket="($tren>=0?'Naik ':'Turun ').number_format(abs($tren),1).'% vs rata-rata pekan ini'" />
    <x-kpi label="Fuel Consumption" :nilai="number_format($a['fuelRate'],1)" satuan="L/jam"
           :ket="number_format($a['liter']).' L pada '.number_format($a['kerja']).' jam kerja'" warna="#FF9800" />
    <x-kpi label="Energy Intensity" :nilai="number_format($e['intensitas'],5)" satuan="GJ/ton"
           :ket="'Baseline '.number_format($e['baseline'],4).' · sasaran '.number_format($e['target'],4)"
           :warna="$e['penurunan'] >= 0 ? '#F57C00' : '#E2663A'" />
    <x-kpi label="Fleet Availability" :nilai="number_format($a['pa'],1)" satuan="%"
           :ket="$a['beroperasi'].' dari '.$a['jumlah'].' unit beroperasi'" :rasio="$a['pa']/100" warna="#F57C00" />
    <x-kpi label="Mechanical Availability" :nilai="number_format($a['ma'],1)" satuan="%"
           ket="Kerja ÷ (kerja + perbaikan)" :rasio="$a['ma']/100" warna="#FF9800" />
    <x-kpi label="Utilization" :nilai="number_format($a['utilisasi'],1)" satuan="%"
           ket="Kerja ÷ jam terjadwal" :rasio="$a['utilisasi']/100" warna="#C08A3E" />
  </div>

  <div class="grid gap-4 lg:grid-cols-2">
    <section class="kartu-lux rounded-2xl p-6">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Daily Production</h3>
      <p class="text-[11.5px] text-stone-500 mt-1">Realisasi terhadap target, ton ROM.</p>
      <div class="mt-5">
        <x-batang
          :label="array_map(fn($x) => \Illuminate\Support\Carbon::parse($x['tgl'])->translatedFormat('d M'), $p['harian'])"
          :seri="[
            ['nama'=>'Aktual','data'=>array_column($p['harian'],'ton'),'warna'=>'#F57C00'],
            ['nama'=>'Target','data'=>array_column($p['harian'],'target'),'warna'=>'#D6D3CB'],
          ]" satuan="ton" :tinggi="240" />
      </div>
    </section>

    <section class="kartu-lux rounded-2xl p-6">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Status Armada</h3>
      <p class="text-[11.5px] text-stone-500 mt-1">Sebaran unit menurut keadaannya hari ini.</p>
      @php
        $status = ['Operating'=>'#F57C00','Standby'=>'#4C9AFF','Maintenance'=>'#C08A3E','Breakdown'=>'#E2663A'];
        $sebaran = [];
        foreach ($status as $s => $w) {
          $j = count(array_filter(E::armada(), fn($u) => $u['status'] === $s));
          if ($j) $sebaran[] = ['nama'=>$s,'nilai'=>$j,'warna'=>$w];
        }
      @endphp
      <x-donat :data="$sebaran" :tengah="(string) $a['jumlah']" tengahKet="unit terdaftar" :tinggi="215" />
    </section>
  </div>

  <div class="grid gap-4 lg:grid-cols-2">
    <section class="kartu-lux rounded-2xl p-6">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Energy Consumption Trend</h3>
      <p class="text-[11.5px] text-stone-500 mt-1">
        Intensitas harian, gigajoule per ton.
        <span class="text-cam-coral font-semibold">Garis putus-putus adalah sasaran tahun berjalan.</span>
      </p>
      <div class="mt-5">
        <x-garis :titik="array_column($e['deret'],'intensitas')" :target="$e['target']"
                 satuan="GJ/ton" :desimal="4" :tinggi="200" />
      </div>
    </section>

    <section class="kartu-lux rounded-2xl p-6">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Perhatian Hari Ini</h3>
      <p class="text-[11.5px] text-stone-500 mt-1">Disusun dari keadaan data, bukan daftar tetap.</p>
      @php
        $boros = collect(E::armada())->sortByDesc(fn($u) => E::fuelRate($u))->first();
        $rusak = array_values(array_filter(E::armada(), fn($u) => $u['status'] === 'Breakdown'));
        $perhatian = [];
        if ($m['kritis'])  $perhatian[] = ['#E2663A', $m['kritis'].' pekerjaan Critical belum ditutup', 'Lihat daftar pada halaman Maintenance.'];
        if (count($rusak)) $perhatian[] = ['#E2663A', count($rusak).' unit berstatus Breakdown', implode(', ', array_column($rusak,'kode')).' — menekan MA armada.'];
        $perhatian[] = ['#C08A3E', $boros['kode'].' paling haus: '.number_format(E::fuelRate($boros),1).' L/jam',
                        $boros['tipe'].' · rata-rata armada '.number_format($a['fuelRate'],1).' L/jam.'];
        $perhatian[] = [$e['penurunan'] >= 0 ? '#F57C00' : '#E2663A',
                        'Intensitas energi '.($e['penurunan']>=0?'turun':'naik').' '.number_format(abs($e['penurunan']),1).'% dari baseline',
                        'Sekarang '.number_format($e['intensitas'],5).' GJ/ton, sasaran '.number_format($e['target'],4).' GJ/ton.'];
        $perhatian[] = [$p['capaian'] >= 100 ? '#F57C00' : '#C08A3E',
                        'Capaian produksi '.number_format($p['capaian'],1).'% terhadap target',
                        number_format($p['ton']).' ton dari sasaran '.number_format($p['target']).' ton pekan ini.'];
      @endphp
      <ul class="mt-4">
        @foreach($perhatian as [$w,$judul,$ket])
          <li class="flex gap-3 py-3 hairline last:border-b-0">
            <span class="shrink-0 w-1.5 h-9 rounded-full mt-0.5" style="background:{{ $w }}"></span>
            <span class="min-w-0">
              <span class="block text-[12.5px] font-bold text-cam-ink">{{ $judul }}</span>
              <span class="block text-[11.5px] text-stone-500 mt-0.5 leading-relaxed">{{ $ket }}</span>
            </span>
          </li>
        @endforeach
      </ul>
    </section>
  </div>

</div>
@endsection

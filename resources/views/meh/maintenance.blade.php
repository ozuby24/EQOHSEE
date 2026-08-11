@extends('layouts.app')
@section('title','Maintenance')

@section('content')
<div class="max-w-6xl mx-auto space-y-5">

  <section class="kartu-lux rounded-2xl p-6">
    <h2 class="font-display text-[20px] font-black text-cam-ink">Maintenance Overview</h2>
    <p class="text-[12.5px] text-stone-500 mt-1.5 leading-relaxed max-w-3xl">
      Kepatuhan pemeliharaan terencana dan keandalan alat. Preventif yang naik sementara breakdown
      turun adalah arah yang dituju — bukan sekadar total jam yang mengecil.
    </p>
  </section>

  <div class="grid gap-4 grid-cols-2 lg:grid-cols-3">
    <x-kpi label="PM Compliance" :nilai="number_format($m['pm_compliance'],1)" satuan="%"
           ket="Pemeliharaan terencana yang terlaksana" :rasio="$m['pm_compliance']/100" />
    <x-kpi label="MTBF" :nilai="number_format($m['mtbf_jam'],1)" satuan="jam"
           ket="Rata-rata antar kerusakan" warna="#4C9AFF" />
    <x-kpi label="MTTR" :nilai="number_format($m['mttr_jam'],1)" satuan="jam"
           ket="Rata-rata waktu perbaikan" warna="#C08A3E" />
    <x-kpi label="Breakdown Frequency" :nilai="$m['breakdown']" satuan="/bulan"
           :ket="$m['terbuka'].' pekerjaan belum ditutup'" warna="#E2663A" />
    <x-kpi label="Maintenance Cost" :nilai="'Rp '.number_format($m['biaya_rp']/1e9,2).' M'"
           ket="Perkiraan bulan berjalan" warna="#22312F" />
    <x-kpi label="Porsi Preventif" :nilai="number_format($m['porsiPreventif'],1)" satuan="%"
           :ket="'dari '.number_format($m['totalJam']).' jam pemeliharaan bulan ini'"
           :rasio="$m['porsiPreventif']/100" warna="#2A9D8F" />
  </div>

  <div class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
    <section class="kartu-lux rounded-2xl p-6">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Jam Pemeliharaan menurut Jenis</h3>
      <p class="text-[11.5px] text-stone-500 mt-1">Enam bulan terakhir, ditumpuk.</p>
      <div class="mt-5">
        <x-batang :label="array_column($m['bulanan'],'label')" tumpuk
          :seri="[
            ['nama'=>'Preventif','data'=>array_column($m['bulanan'],'preventif'),'warna'=>'#0F766E'],
            ['nama'=>'Korektif','data'=>array_column($m['bulanan'],'korektif'),'warna'=>'#C08A3E'],
            ['nama'=>'Breakdown','data'=>array_column($m['bulanan'],'breakdown'),'warna'=>'#E2663A'],
          ]" satuan="jam" :tinggi="250" />
      </div>
    </section>

    <section class="kartu-lux rounded-2xl p-6">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Komposisi Bulan Ini</h3>
      <x-donat :data="[
        ['nama'=>'Preventif','nilai'=>$m['bulanIni']['preventif'],'warna'=>'#0F766E'],
        ['nama'=>'Korektif','nilai'=>$m['bulanIni']['korektif'],'warna'=>'#C08A3E'],
        ['nama'=>'Breakdown','nilai'=>$m['bulanIni']['breakdown'],'warna'=>'#E2663A'],
      ]" :tengah="number_format($m['porsiPreventif'],0).'%'" tengahKet="preventif" :tinggi="215" />
    </section>
  </div>

  <section class="kartu-lux rounded-2xl p-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Daftar Pekerjaan</h3>
      <div class="flex flex-wrap gap-1.5">
        @foreach (['semua'=>'Semua','Critical'=>'Critical','High'=>'High','Medium'=>'Medium','Low'=>'Low'] as $k => $l)
          <a href="{{ route('meh.maintenance', $k === 'semua' ? [] : ['prioritas'=>$k]) }}"
             class="text-[11.5px] font-bold px-3 py-1.5 rounded-xl transition
                    {{ $prioritas === $k ? 'bg-cam-ink text-white' : 'bg-stone-100 text-stone-500 hover:bg-stone-200' }}">{{ $l }}</a>
        @endforeach
      </div>
    </div>

    @if(count($kerja))
      <div class="overflow-x-auto mt-5 -mx-1">
        <table class="w-full text-[12.5px] min-w-[680px]">
          <thead>
            <tr class="text-[10.5px] font-bold uppercase tracking-wide text-stone-400 hairline">
              <th class="text-left py-2.5">Unit</th><th class="text-left py-2.5">Masalah</th>
              <th class="text-left py-2.5">Prioritas</th><th class="text-left py-2.5">Status</th>
              <th class="text-left py-2.5">PIC</th><th class="num py-2.5">Tanggal</th>
            </tr>
          </thead>
          <tbody>
            @foreach($kerja as $x)
              @php $w = ['Critical'=>'#E2663A','High'=>'#C08A3E','Medium'=>'#4C9AFF','Low'=>'#9AA3AE'][$x['prioritas']]; @endphp
              <tr class="hairline">
                <td class="py-2.5 font-bold text-cam-ink">{{ $x['unit'] }}</td>
                <td class="py-2.5 text-stone-600">{{ $x['masalah'] }}</td>
                <td class="py-2.5">
                  <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-1 rounded-lg text-white"
                        style="background:{{ $w }}">{{ $x['prioritas'] }}</span>
                </td>
                <td class="py-2.5 text-stone-500">{{ $x['status'] }}</td>
                <td class="py-2.5 text-stone-500">{{ $x['pic'] }}</td>
                <td class="num py-2.5">{{ \Illuminate\Support\Carbon::parse($x['tanggal'])->translatedFormat('d M Y') }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <p class="text-[12px] text-stone-400 mt-6">Tidak ada pekerjaan berprioritas {{ $prioritas }}.</p>
    @endif
  </section>

</div>
@endsection

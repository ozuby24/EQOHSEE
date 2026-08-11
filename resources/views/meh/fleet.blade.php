@extends('layouts.app')
@section('title','Fleet & Productivity')

@section('content')
@php use App\Support\Engineering as E; $pr = E::PRODUKTIVITAS; @endphp

<div class="max-w-6xl mx-auto space-y-5">

  <section class="kartu-lux rounded-2xl p-6">
    <h2 class="font-display text-[20px] font-black text-cam-ink">Fleet &amp; Productivity</h2>
    <p class="text-[12.5px] text-stone-500 mt-1.5 leading-relaxed max-w-3xl">
      Ketersediaan dihitung dari jam kerja, jam standby, dan jam perbaikan — bukan diketik terpisah.
      Ketiganya menjumlah menjadi jam terjadwal, sehingga PA, MA, UA, dan utilisasi selalu saling
      konsisten.
    </p>
  </section>

  <div class="grid gap-4 grid-cols-2 lg:grid-cols-3">
    <x-kpi label="Physical Availability" :nilai="number_format($a['pa'],1)" satuan="%"
           ket="(Kerja + standby) ÷ terjadwal" :rasio="$a['pa']/100" />
    <x-kpi label="Mechanical Availability" :nilai="number_format($a['ma'],1)" satuan="%"
           ket="Kerja ÷ (kerja + perbaikan)" :rasio="$a['ma']/100" warna="#2A9D8F" />
    <x-kpi label="Use of Availability" :nilai="number_format($a['ua'],1)" satuan="%"
           ket="Kerja ÷ (kerja + standby)" :rasio="$a['ua']/100" warna="#4C9AFF" />
    <x-kpi label="Utilization" :nilai="number_format($a['utilisasi'],1)" satuan="%"
           ket="Kerja ÷ jam terjadwal" :rasio="$a['utilisasi']/100" warna="#C08A3E" />
    <x-kpi label="Productivity" :nilai="number_format(E::bagi($p['ton'],$a['kerja']),1)" satuan="ton/jam"
           :ket="number_format($p['ton']).' ton ÷ '.number_format($a['kerja']).' jam'" warna="#22312F" />
    <x-kpi label="Fuel Ratio" :nilai="number_format(E::bagi($a['liter'],$p['ton']),4)" satuan="L/ton"
           ket="Solar ÷ produksi" warna="#E2663A" />
  </div>

  <section class="kartu-lux rounded-2xl p-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h3 class="font-display text-[16px] font-black text-cam-ink">Daftar Unit</h3>
        <p class="text-[11.5px] text-stone-500 mt-1">Diurutkan dari yang paling haus per jam operasi.</p>
      </div>
      <div class="flex flex-wrap gap-1.5">
        @foreach (['semua'=>'Semua','Operating'=>'Operating','Standby'=>'Standby','Maintenance'=>'Maintenance','Breakdown'=>'Breakdown'] as $k => $l)
          <a href="{{ route('meh.fleet', $k === 'semua' ? [] : ['status'=>$k]) }}"
             class="text-[11.5px] font-bold px-3 py-1.5 rounded-xl transition
                    {{ $status === $k ? 'bg-cam-ink text-white' : 'bg-stone-100 text-stone-500 hover:bg-stone-200' }}">{{ $l }}</a>
        @endforeach
      </div>
    </div>

    @if(count($unit))
      <div class="overflow-x-auto mt-5 -mx-1">
        <table class="w-full text-[12.5px] min-w-[720px]">
          <thead>
            <tr class="text-[10.5px] font-bold uppercase tracking-wide text-stone-400 hairline">
              <th class="text-left py-2.5">Unit</th><th class="text-left py-2.5">Tipe</th>
              <th class="text-left py-2.5">Status</th>
              <th class="num py-2.5">PA</th><th class="num py-2.5">MA</th><th class="num py-2.5">Utilisasi</th>
              <th class="num py-2.5">L/jam</th><th class="num py-2.5">HM</th>
              <th class="text-left py-2.5 pl-3">Keborosan</th>
            </tr>
          </thead>
          <tbody>
            @foreach($unit as $u)
              @php $fr = E::fuelRate($u); $st = E::statusBoros($fr, $acuan[$u['kelas']] ?? 0); @endphp
              <tr class="hairline hover:bg-cam-lime-soft/30 transition">
                <td class="py-2.5"><span class="font-bold text-cam-ink">{{ $u['kode'] }}</span>
                    <span class="block text-[10.5px] text-stone-400">{{ $u['kelas'] }}</span></td>
                <td class="py-2.5 text-stone-500">{{ $u['tipe'] }}</td>
                <td class="py-2.5">
                  @php $w = ['Operating'=>'#0F766E','Standby'=>'#4C9AFF','Maintenance'=>'#C08A3E','Breakdown'=>'#E2663A'][$u['status']]; @endphp
                  <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-1 rounded-lg text-white whitespace-nowrap"
                        style="background:{{ $w }}">{{ $u['status'] }}</span>
                </td>
                <td class="num py-2.5">{{ number_format(E::pa($u),1) }}%</td>
                <td class="num py-2.5">{{ number_format(E::ma($u),1) }}%</td>
                <td class="num py-2.5">{{ number_format(E::utilisasi($u),1) }}%</td>
                <td class="num py-2.5 font-bold" style="color:{{ $st['warna'] }}">{{ number_format($fr,1) }}</td>
                <td class="num py-2.5">{{ number_format($u['hm']) }}</td>
                <td class="py-2.5 pl-3 text-[11px]" style="color:{{ $st['warna'] }}">{{ $st['label'] }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <p class="text-[10.5px] text-stone-400 mt-4 leading-relaxed">
        Keborosan dinilai terhadap rata-rata kelasnya sendiri, bukan angka mutlak: excavator dan dump
        truck memang berbeda haus, dan mengurutkan liter mentah akan selalu menaruh alat bertenaga
        besar di puncak daftar boros.
      </p>
    @else
      <p class="text-[12px] text-stone-400 mt-6">Tidak ada unit berstatus {{ $status }}.</p>
    @endif
  </section>

  <div class="grid gap-4 lg:grid-cols-2">
    <section class="kartu-lux rounded-2xl p-6">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Konsumsi per Kelas Alat</h3>
      <div class="mt-5">
        <x-batang :label="array_keys($acuan)"
                  :seri="[['nama'=>'Liter per jam','data'=>array_values($acuan),'warna'=>'#0F766E']]"
                  satuan="L/jam" :desimal="1" :tinggi="230" />
      </div>
    </section>

    <section class="kartu-lux rounded-2xl p-6">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Produktivitas Hauling</h3>
      <div class="grid gap-4 grid-cols-2 mt-5">
        @foreach ([
          ['Cycle Time', number_format($pr['cycle_menit'],1), 'menit'],
          ['Hauling Distance', number_format($pr['jarak_km'],1), 'km'],
          ['Truck Factor', number_format($pr['truck_factor'],2), ''],
          ['Match Factor', number_format($pr['match_factor'],2), ''],
          ['Kecepatan Rata-rata', number_format($pr['kecepatan_kmh'],1), 'km/jam'],
          ['Stripping Ratio', number_format($p['sr'],2), 'BCM/ton'],
        ] as [$l,$v,$s])
          <div>
            <div class="text-[10.5px] font-bold uppercase tracking-wide text-stone-500">{{ $l }}</div>
            <div class="stat stat-sm text-cam-ink mt-1.5">{{ $v }}@if($s)<span class="stat-unit">{{ $s }}</span>@endif</div>
          </div>
        @endforeach
      </div>
    </section>
  </div>

</div>
@endsection

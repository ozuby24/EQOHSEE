@extends('layouts.app')
@section('title', $unit->kode.' — Kinerja Energi')

@section('content')
@php use App\Support\Energi; @endphp

<div class="max-w-5xl mx-auto space-y-5">

  {{-- Kepala unit --}}
  <section class="kartu-lux rounded-2xl p-6">
    <a href="{{ route('energi.equipment', ['dari'=>$dari->format('Y-m-d'),'sampai'=>$sampai->format('Y-m-d')]) }}"
       class="text-[11.5px] font-bold text-cam-lime-deep hover:underline">← Semua unit</a>

    <div class="flex flex-wrap items-start justify-between gap-4 mt-3">
      <div class="min-w-0">
        <div class="flex items-center gap-2.5 flex-wrap">
          <h2 class="font-display text-[21px] font-black text-cam-ink leading-tight">{{ $unit->kode }}</h2>
          <span class="text-[9.5px] font-bold uppercase tracking-wide text-white px-2 py-1 rounded-lg"
                style="background:{{ $status['warna'] }}">{{ $status['label'] }}</span>
        </div>
        <p class="text-[12.5px] text-stone-500 mt-1">
          {{ $unit->nama }} · {{ $unit->labelKategori() }}
          @if($unit->merek) · {{ $unit->merek }} @endif
          @if($unit->daya_hp) · {{ number_format($unit->daya_hp) }} HP @endif
          @if($unit->payload_ton) · payload {{ number_format($unit->payload_ton, 1) }} ton @endif
        </p>
      </div>

      <x-rentang :dari="$dari" :sampai="$sampai" :rute="route('energi.equipment.show', $unit)" />
    </div>

    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mt-6 pt-6 hairline border-b-0">
      @foreach ([
        ['Liter per Jam', number_format($m['l_hm'], 2), 'L/HM', $status['warna']],
        ['Acuan Kategori', number_format($acuan, 2), 'L/HM', '#9AA3AE'],
        ['Total Solar', number_format($m['liter']), 'L', '#F57C00'],
        ['Biaya Solar', 'Rp '.Energi::ringkas($m['rupiah'], 2), null, '#E2663A'],
      ] as [$l, $v, $s, $w])
        <div>
          <div class="stat stat-sm" style="color:{{ $w }}">{{ $v }}@if($s)<span class="stat-unit">{{ $s }}</span>@endif</div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">{{ $l }}</div>
        </div>
      @endforeach
    </div>
  </section>

  {{-- Angka operasi --}}
  <div class="grid gap-4 grid-cols-2 lg:grid-cols-4">
    <x-kpi label="Jam Operasi" :nilai="number_format($m['hm'], 1)" satuan="HM"
           :ket="$m['idle'] > 0 ? number_format($m['idle'], 1).' jam di antaranya idle' : 'Tidak ada catatan idle'" />
    <x-kpi label="Porsi Idle" :nilai="number_format($m['idle_persen'], 1).'%'"
           :warna="$m['idle_persen'] > 25 ? '#E2663A' : '#F57C00'"
           :rasio="min(1, $m['idle_persen'] / 100)"
           :ket="$m['idle_persen'] > 25 ? 'Di atas 25% — solar terbakar tanpa hasil' : 'Dalam batas wajar'" />
    <x-kpi label="Liter per Ton" :nilai="number_format($m['l_ton'], 3)" satuan="L/ton"
           :ket="number_format($m['ton']).' ton terangkut'" warna="#FF9800" />
    <x-kpi label="Liter per BCM" :nilai="number_format($m['l_bcm'], 3)" satuan="L/BCM"
           :ket="number_format($m['bcm']).' BCM'" warna="#22312F" />
  </div>

  {{-- Tren harian --}}
  <section class="kartu-lux rounded-2xl p-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h3 class="font-display text-[16px] font-black text-cam-ink">Pemakaian Harian</h3>
        <p class="text-[11.5px] text-stone-500 mt-1">
          Liter per hari.
          <span class="text-cam-coral font-semibold">Garis putus-putus adalah rata-rata unit ini sendiri.</span>
        </p>
      </div>
      <div class="text-right">
        <div class="num text-[19px] font-bold text-cam-lime-deep">{{ $log->count() }}</div>
        <div class="text-[10.5px] text-stone-400">hari beroperasi</div>
      </div>
    </div>

    <div class="mt-5">
      <x-garis :titik="$log->pluck('liter')->map(fn($v) => (float) $v)->all()"
               :target="$log->count() ? (float) $log->avg('liter') : null"
               :tinggi="150" satuan="L" :desimal="0" />
    </div>
  </section>

  {{-- Dampak dan lain-lain --}}
  <div class="grid gap-4 lg:grid-cols-2">
    <section class="kartu-lux rounded-2xl p-6">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Dampak Energi</h3>
      <div class="grid gap-4 grid-cols-2 mt-5">
        <x-kpi label="Energi" :nilai="number_format($m['gj'], 2)" satuan="GJ" ket="Solar disamakan ke gigajoule" />
        <x-kpi label="Emisi" :nilai="number_format($m['tco2e'], 2)" satuan="tCO₂e" ket="2,68 kg CO₂e tiap liter" warna="#22312F" />
      </div>

      @if($acuan > 0 && $m['l_hm'] > $acuan)
        @php
          $berlebih = ($m['l_hm'] - $acuan) * $m['hm'];
        @endphp
        <div class="rounded-xl bg-cam-coral-soft border border-cam-coral/25 px-4 py-3 mt-5 text-[11.5px] leading-relaxed text-cam-ink">
          Bila unit ini berjalan pada acuan kategorinya, pemakaiannya akan lebih hemat sekitar
          <strong class="num">{{ number_format($berlebih) }}</strong> liter pada rentang ini —
          setara <strong>Rp {{ number_format(Energi::literKeRp($berlebih)) }}</strong> dan
          <strong class="num">{{ number_format(Energi::literKeCo2($berlebih), 2) }}</strong> tCO₂e.
        </div>
      @endif
    </section>

    <section class="kartu-lux rounded-2xl p-6">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Pola Kerja</h3>
      <div class="grid gap-4 grid-cols-2 mt-5">
        <x-kpi label="Jarak Tempuh" :nilai="number_format($m['jarak'], 1)" satuan="km" ket="Hanya terisi untuk unit angkut" warna="#FF9800" />
        <x-kpi label="Kecepatan Rata-rata" :nilai="number_format($m['kecepatan'], 1)" satuan="km/jam" ket="Jarak dibagi jam operasi" />
        <x-kpi label="Cycle Time" :nilai="$m['cycle'] ? number_format($m['cycle'], 2) : '—'" satuan="menit" ket="Rata-rata satu siklus" warna="#D9993A" />
        <x-kpi label="Solar per Hari" :nilai="$log->count() ? number_format($m['liter'] / $log->count(), 1) : '0'" satuan="L/hari"
               ket="Rata-rata hari beroperasi" warna="#E2663A" />
      </div>
    </section>
  </div>

  {{-- Catatan harian --}}
  <section class="kartu-lux rounded-2xl p-6">
    <h3 class="font-display text-[16px] font-black text-cam-ink">Catatan Harian</h3>

    @if($log->count())
      <div class="overflow-x-auto mt-4 -mx-1">
        <table class="w-full text-[12.5px] min-w-[620px]">
          <thead>
            <tr class="text-[10.5px] font-bold uppercase tracking-wide text-stone-400 hairline">
              <th class="text-left py-2.5">Tanggal</th>
              <th class="num py-2.5">HM</th>
              <th class="num py-2.5">Liter</th>
              <th class="num py-2.5">L/HM</th>
              <th class="num py-2.5">Idle</th>
              <th class="num py-2.5">Ton</th>
              <th class="num py-2.5">BCM</th>
            </tr>
          </thead>
          <tbody>
            @foreach($log as $x)
              @php $lhm = Energi::rasio((float) $x->liter, (float) $x->hm); @endphp
              <tr class="hairline">
                <td class="py-2.5 font-semibold text-cam-ink">{{ $x->tanggal->translatedFormat('d M Y') }}</td>
                <td class="num py-2.5">{{ number_format($x->hm, 1) }}</td>
                <td class="num py-2.5">{{ number_format($x->liter, 1) }}</td>
                <td class="num py-2.5 font-bold {{ $acuan > 0 && $lhm > $acuan * 1.2 ? 'text-cam-coral' : 'text-cam-lime-deep' }}">
                  {{ number_format($lhm, 2) }}
                </td>
                <td class="num py-2.5">{{ number_format($x->idle_jam, 1) }}</td>
                <td class="num py-2.5">{{ number_format($x->ton, 1) }}</td>
                <td class="num py-2.5">{{ number_format($x->bcm, 1) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <p class="text-[12px] text-stone-400 mt-6">Belum ada catatan pada rentang ini.</p>
    @endif
  </section>

</div>
@endsection

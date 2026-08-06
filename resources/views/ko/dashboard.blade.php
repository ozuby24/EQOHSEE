@extends('layouts.app')
@section('title','KO/SPIP — Dashboard')

@section('content')
@php $K = \App\Support\Ko::class; @endphp

<div class="max-w-6xl mx-auto space-y-5">
  @include('ko._flash')
  @include('ko._picker')
  @include('ko._chart')

  {{-- ── Indeks KO ── --}}
  <div class="brand-gradient rounded-2xl p-7 text-white shadow-card relative overflow-hidden">
    <div class="absolute -right-24 -top-24 w-72 h-72 rounded-full bg-cam-lime/20 blur-3xl"></div>
    <div class="relative flex flex-wrap items-center justify-between gap-6">
      <div>
        <span class="text-[10px] font-bold uppercase tracking-[0.22em] text-cam-lime-light">Keselamatan Operasi — Sarana · Prasarana · Instalasi · Peralatan</span>
        <h2 class="stat mt-2 leading-tight">Indeks Kepatuhan KO</h2>
        <p class="text-[12px] text-white/45 mt-1">
          Rerata lima sub-elemen · Kepmen ESDM 1827 K/2018 &amp; Kepdirjen 185.K/2019
        </p>
      </div>
      <div class="glass rounded-2xl px-8 py-5 text-center">
        <div class="stat stat-xl leading-none text-cam-lime-light">{{ $sub['indeks'] }}<small class="text-[18px]">%</small></div>
        <div class="text-[9.5px] uppercase tracking-[0.15em] text-white/40 mt-2 font-bold">{{ $sub['level'] }}</div>
      </div>
    </div>
  </div>

  @if($c['total'] === 0)
    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-[12.5px] text-amber-800">
      Belum ada objek SPIP terdaftar. Mulai dari
      <a href="{{ route('ko.register') }}" class="font-bold underline">Register SPIP</a>.
    </div>
  @endif

  {{-- ── KPI ── --}}
  @php
    $kpi = [
      ['Total Objek SPIP', $c['total'], '', '4 kategori terdaftar', '#0B6E99'],
      ['Layak Operasi', $c['layakPct'], '%', $c['byStat'][$K::ST_LAYAK].' objek layak', '#16A34A'],
      ['Akan Jatuh Tempo', $c['byStat'][$K::ST_TEMPO], '', '≤ '.$set['ko_warn_days'].' hari lagi', '#D97706'],
      ['Kadaluarsa', $c['byStat'][$K::ST_EXPIRE], '', 'Stop operasi s/d re-sertifikasi', '#D92D20'],
      ['Perawatan Overdue', $c['overdue'], '', 'PMC '.$c['pmc'].'%', '#B54708'],
      ['Pengaman Berfungsi', $c['pgPct'], '%', $c['pgOk'].'/'.$c['pgTot'].' perangkat', '#0B6E99'],
    ];
  @endphp
  <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
    @foreach($kpi as $k)
      <div class="bg-white rounded-2xl border border-stone-200 px-4 py-3.5 card-hover">
        <div class="stat text-[24px] leading-none" style="color: {{ $k[4] }}">{{ $k[1] }}{{ $k[2] }}</div>
        <div class="text-[11px] font-bold text-cam-ink mt-1.5">{{ $k[0] }}</div>
        <div class="text-[10.5px] text-stone-400 mt-0.5">{{ $k[3] }}</div>
      </div>
    @endforeach
  </div>

  <div class="grid lg:grid-cols-2 gap-5">
    {{-- ── Lima sub-elemen ── --}}
    <div class="bg-white rounded-2xl border border-stone-200 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-4">Lima Sub-elemen Keselamatan Operasi</h3>
      <div class="space-y-3.5">
        @foreach($sub['items'] as $s)
          <div>
            <div class="flex items-center justify-between text-[11.5px] mb-1.5">
              <span class="flex items-center gap-2 font-semibold text-stone-700">
                <span class="w-5 h-5 rounded-md bg-stone-100 text-stone-500 text-[10px] font-bold grid place-items-center">{{ $s['n'] }}</span>
                {{ $s['nama'] }}
              </span>
              <span class="num font-bold" style="color: {{ $K::warnaPersen($s['pct']) }}">{{ $s['pct'] }}%</span>
            </div>
            <div class="h-1.5 rounded-full bg-stone-100 overflow-hidden">
              <div class="h-full rounded-full transition-all duration-500"
                   style="width: {{ $s['pct'] }}%; background: {{ $K::warnaPersen($s['pct']) }}"></div>
            </div>
            <div class="text-[10.5px] text-stone-400 mt-1">{{ $s['ket'] }}</div>
          </div>
        @endforeach
      </div>
    </div>

    {{-- ── Grafik ── --}}
    <div class="bg-white rounded-2xl border border-stone-200 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-3">Status Kelayakan Objek</h3>
      <div class="relative" style="height:220px"><canvas id="koDonat"></canvas></div>
      <h3 class="text-[13px] font-bold text-cam-ink mt-5 mb-3">Objek per Kategori</h3>
      <div class="relative" style="height:180px"><canvas id="koKat"></canvas></div>
    </div>
  </div>

  {{-- ── Peringatan ── --}}
  <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-stone-100 flex flex-wrap items-center justify-between gap-3">
      <h3 class="text-[13px] font-bold text-cam-ink">Peringatan Prioritas</h3>
      <div class="flex items-center gap-2">
        <span class="text-[11px] text-stone-400 num">{{ $aksiTerbuka }} tindak lanjut terbuka</span>
        @if($bolehUbah)
          <form method="POST" action="{{ route('ko.tindak.tarik') }}">
            @csrf
            <button class="rounded-lg bg-cam-ink text-white px-3 py-1.5 text-[11.5px] font-bold hover:brightness-110 transition">
              ↧ Jadikan tindak lanjut
            </button>
          </form>
        @endif
      </div>
    </div>

    @if(count($peringatan) === 0)
      <p class="px-5 py-8 text-[12.5px] text-stone-400">Tidak ada peringatan aktif.</p>
    @else
      <div class="divide-y divide-stone-50">
        @foreach($peringatan as $p)
          <a href="{{ route('ko.show', $p['objek']) }}" class="flex items-start gap-3 px-5 py-3 hover:bg-stone-50/70 transition">
            <span class="w-2 h-2 rounded-full mt-1.5 flex-none"
                  style="background: {{ $p['pr'] === 0 ? '#D92D20' : ($p['pr'] === 1 ? '#D97706' : '#B54708') }}"></span>
            <div class="flex-1 min-w-0">
              <div class="text-[12.5px] font-semibold text-cam-ink">{{ $p['jenis'] }}</div>
              <div class="text-[11px] text-stone-500">{{ $p['ket'] }}</div>
            </div>
            @if($p['hari'] !== null)
              <span class="text-[11px] num whitespace-nowrap {{ $p['hari'] < 0 ? 'text-red-600 font-bold' : 'text-stone-500' }}">
                {{ $p['hari'] < 0 ? abs($p['hari']).' hari lewat' : $p['hari'].' hari lagi' }}
              </span>
            @endif
          </a>
        @endforeach
      </div>
    @endif
  </div>

  @php
    $donatLbl = []; $donatVal = []; $donatCol = [];
    foreach ($c['byStat'] as $st => $n) { $donatLbl[] = $st; $donatVal[] = $n; $donatCol[] = $K::warna($st); }
    $katVal = [];
    foreach ($K::KATEGORI as $kk) $katVal[] = $objek->where('kategori', $kk)->count();
  @endphp
  <script>
    koChartSiap(function () {
      new Chart(document.getElementById('koDonat'), {
        type: 'doughnut',
        data: { labels: @json($donatLbl), datasets: [{ data: @json($donatVal), backgroundColor: @json($donatCol), borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '62%',
          plugins: { legend: { position: 'bottom' } } }
      });
      new Chart(document.getElementById('koKat'), {
        type: 'bar',
        data: { labels: @json($K::KATEGORI),
                datasets: [{ data: @json($katVal), backgroundColor: '#0B6E99', borderRadius: 6, maxBarThickness: 38 }] },
        options: { responsive: true, maintainAspectRatio: false,
          scales: { y: { beginAtZero: true, grid: { color: '#f5f5f4' } }, x: { grid: { display: false } } },
          plugins: { legend: { display: false } } }
      });
    });
  </script>
</div>
@endsection

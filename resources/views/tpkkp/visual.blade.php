@extends('layouts.app')
@section('title','PTPKKP — Visualisasi')

@section('content')
@php
  $T = \App\Support\Tpkkp::class;

  $indLbl = []; $indCap = []; $indTgt = [];
  foreach ($hasil['indicators'] as $I) {
    $indLbl[] = 'Ind. ' . $I['code'];
    $indCap[] = round($I['score'] ?? 0, 4);
    $indTgt[] = round($I['target'] ?? 0, 4);
  }

  $parLbl = []; $parCap = []; $parTgt = []; $parWarna = [];
  foreach ($hasil['indicators'] as $I) {
    foreach ($I['params'] as $P) {
      $parLbl[]   = $P['code'];
      $parCap[]   = round($P['score'] ?? 0, 4);
      $parTgt[]   = round($P['target'] ?? 0, 4);
      $parWarna[] = $T::levelHex($T::level($P['category']));
    }
  }

  $mLbl = []; $mVal = []; $mWarna = [];
  foreach ($metode as $m) {
    $mLbl[]   = $m['key'];
    $mVal[]   = $m['ratio'] === null ? 0 : round($m['ratio'] * 100, 1);
    $mWarna[] = $T::levelHex($T::level($m['category']));
  }

  $donat = [];
  foreach ($T::LV as $i => $n) $donat[] = $sebaran[$i + 1] ?? 0;
  $donat[] = $sebaran['none'] ?? 0;
@endphp

<div class="max-w-6xl mx-auto space-y-5">
  @include('tpkkp._flash')
  @include('tpkkp._picker')
  @include('tpkkp._chart')

  <div class="grid lg:grid-cols-2 gap-5">
    <div class="bg-white rounded-2xl border border-stone-200 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-3">Capaian vs Target per Indikator</h3>
      <div class="relative" style="height:280px"><canvas id="vInd"></canvas></div>
    </div>
    <div class="bg-white rounded-2xl border border-stone-200 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-3">Sebaran Kategori Item</h3>
      <div class="relative" style="height:280px"><canvas id="vDonat"></canvas></div>
    </div>
  </div>

  <div class="bg-white rounded-2xl border border-stone-200 p-5">
    <h3 class="text-[13px] font-bold text-cam-ink mb-1">Capaian per Parameter</h3>
    <p class="text-[11px] text-stone-500 mb-3">Batang berwarna kategori, garis putus menandai target parameter.</p>
    <div class="relative" style="height:340px"><canvas id="vPar"></canvas></div>
  </div>

  <div class="bg-white rounded-2xl border border-stone-200 p-5">
    <h3 class="text-[13px] font-bold text-cam-ink mb-3">Keterpenuhan per Metode (%)</h3>
    <div class="relative" style="height:260px"><canvas id="vMet"></canvas></div>
  </div>

  <script>
    eqChartSiap(function () {
      new Chart(document.getElementById('vInd'), {
        type: 'bar',
        data: { labels: @json($indLbl), datasets: [
          { label: 'Capaian', data: @json($indCap), backgroundColor: '#12897F', borderRadius: 6, maxBarThickness: 44 },
          { label: 'Target',  data: @json($indTgt), backgroundColor: '#d6d3d1', borderRadius: 6, maxBarThickness: 44 }
        ]},
        options: { responsive: true, maintainAspectRatio: false,
          scales: { y: { beginAtZero: true, grid: { color: '#f5f5f4' } }, x: { grid: { display: false } } },
          plugins: { legend: { position: 'bottom' } } }
      });

      new Chart(document.getElementById('vDonat'), {
        type: 'doughnut',
        data: { labels: @json(array_merge($T::LV, ['Belum lengkap'])),
                datasets: [{ data: @json($donat),
                  backgroundColor: window.eqWarnaLevel.concat(['#e7e5e4']), borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '60%',
          plugins: { legend: { position: 'bottom' },
                     tooltip: { callbacks: { label: function (c) { return c.label + ': ' + c.raw + ' item'; } } } } }
      });

      new Chart(document.getElementById('vPar'), {
        type: 'bar',
        data: { labels: @json($parLbl), datasets: [
          { label: 'Capaian', data: @json($parCap), backgroundColor: @json($parWarna), borderRadius: 5, maxBarThickness: 26 },
          { label: 'Target', type: 'line', data: @json($parTgt), borderColor: '#1B2024',
            borderDash: [5,4], borderWidth: 1.5, pointRadius: 0, tension: 0 }
        ]},
        options: { responsive: true, maintainAspectRatio: false,
          scales: { y: { beginAtZero: true, grid: { color: '#f5f5f4' } },
                    x: { grid: { display: false }, ticks: { maxRotation: 90, minRotation: 0 } } },
          plugins: { legend: { position: 'bottom' } } }
      });

      new Chart(document.getElementById('vMet'), {
        type: 'bar',
        data: { labels: @json($mLbl),
                datasets: [{ label: '% terpenuhi', data: @json($mVal),
                  backgroundColor: @json($mWarna), borderRadius: 6, maxBarThickness: 40 }] },
        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false,
          scales: { x: { beginAtZero: true, max: 100, grid: { color: '#f5f5f4' } }, y: { grid: { display: false } } },
          plugins: { legend: { display: false } } }
      });
    });
  </script>
</div>
@endsection

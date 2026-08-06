@extends('layouts.app')
@section('title','PTPKKP — Beranda')

@section('content')
@php
  $T     = \App\Support\Tpkkp::class;
  $skor  = $hasil['score'];
  $lv    = $hasil['level'];
  $lengkap = $hasil['completeness'];
@endphp

<div class="max-w-5xl mx-auto space-y-5">
  @include('tpkkp._flash')
  @include('tpkkp._picker')

  {{-- ── Kartu capaian ── --}}
  <div class="brand-gradient rounded-2xl p-7 text-white shadow-card relative overflow-hidden">
    <div class="absolute -right-24 -top-24 w-72 h-72 rounded-full bg-cam-lime/20 blur-3xl"></div>

    <div class="relative flex flex-wrap items-center justify-between gap-6">
      <div>
        <span class="text-[10px] font-bold uppercase tracking-[0.22em] text-cam-lime-light">Tingkat Pencapaian Kinerja Keselamatan Pertambangan</span>
        <h2 class="stat mt-2 leading-tight">{{ $a->profil['organisasi'] ?? $a->judul }}</h2>
        <p class="text-[12px] text-white/45 mt-1">
          {{ $a->profil['site'] ?? '—' }} ·
          {{ $a->profil['komoditas'] ?? '—' }} ·
          Periode {{ $a->tahun }}
        </p>
      </div>
      <div class="glass rounded-2xl px-8 py-5 text-center">
        <div class="stat stat-xl leading-none text-cam-lime-light">{{ $skor === null ? '—' : number_format($skor, 3) }}</div>
        <div class="text-[9.5px] uppercase tracking-[0.15em] text-white/40 mt-2 font-bold">Nilai Capaian (maks 1,000)</div>
      </div>
    </div>

    {{-- strata --}}
    <div class="relative mt-6">
      <div class="flex justify-between text-[10px] font-bold uppercase tracking-wide mb-2">
        @foreach($T::LV as $i => $nama)
          <span class="{{ ($i + 1) === $lv ? 'text-cam-lime-light' : 'text-white/25' }}">{{ $nama }}</span>
        @endforeach
      </div>
      <div class="h-2.5 rounded-full bg-white/10 overflow-hidden">
        <div class="h-full rounded-full bg-cam-lime" style="width: {{ max(1, round(($skor ?? 0) * 100)) }}%"></div>
      </div>
      <div class="flex flex-wrap items-center gap-2 mt-4">
        <span class="glass-light rounded-lg px-3 py-1.5 text-[11.5px] font-semibold">Kategori: {{ $hasil['category'] ?? 'Belum dinilai' }}</span>
        <span class="glass-light rounded-lg px-3 py-1.5 text-[11.5px] font-semibold num">{{ $hasil['filledCells'] }} / {{ $hasil['totalCells'] }} sel metode terisi</span>
        <span class="glass-light rounded-lg px-3 py-1.5 text-[11.5px] font-semibold num">Target {{ number_format($hasil['target'], 2) }}</span>
        @if($skor !== null)
          <span class="rounded-lg px-3 py-1.5 text-[11.5px] font-bold {{ $skor >= $hasil['target'] ? 'bg-cam-lime text-cam-ink' : 'bg-white/15 text-white/80' }}">
            {{ $skor >= $hasil['target'] ? 'Target tercapai' : 'Kurang ' . number_format($hasil['target'] - $skor, 3) }}
          </span>
        @endif
      </div>
    </div>
  </div>

  @if($hasil['filledCells'] === 0)
    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-[12.5px] text-amber-800">
      Belum ada nilai yang masuk. Mulai dari <a href="{{ route('tpkkp.assess') }}" class="font-bold underline">Penilaian</a> — pilih metode, lalu isi nilai per entitas.
      Ingat: metode yang belum diisi dihitung nol, jadi angka akan naik seiring kelengkapan.
    </div>
  @endif

  {{-- ── Capaian per indikator ── --}}
  <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-stone-100 flex items-center justify-between">
      <h3 class="text-[13px] font-bold text-cam-ink">Capaian per Indikator</h3>
      <span class="text-[11px] text-stone-400 num">kelengkapan {{ number_format($lengkap * 100, 1) }}%</span>
    </div>

    <div class="divide-y divide-stone-50">
      @foreach($hasil['indicators'] as $ind)
        @php $rasio = $ind['ratio']; @endphp
        <div class="px-5 py-4">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="text-[12.5px] font-semibold text-cam-ink pr-3">{{ $ind['code'] }}. {{ ucwords(strtolower($ind['name'])) }}</div>
            <div class="flex items-center gap-2">
              @include('tpkkp._badge', ['cat' => $ind['category']])
              <span class="num text-[13px] font-bold text-cam-ink">{{ $ind['score'] === null ? '—' : number_format($ind['score'], 3) }}</span>
            </div>
          </div>
          <div class="h-1.5 rounded-full bg-stone-100 mt-2.5 overflow-hidden">
            <div class="h-full rounded-full" style="width: {{ max(0, min(100, round(($rasio ?? 0) * 100))) }}%; background: {{ $T::levelHex($T::level($ind['category'])) }}"></div>
          </div>
          <div class="flex justify-between text-[10.5px] text-stone-400 mt-1.5 num">
            <span>bobot {{ number_format($ind['weight'], 2) }} · target {{ number_format($ind['target'], 2) }}</span>
            <span>{{ $ind['filledCells'] }}/{{ $ind['totalCells'] }} sel</span>
          </div>
        </div>
      @endforeach
    </div>
  </div>


  {{-- ── Grafik ── --}}
  @include('tpkkp._chart')
  <div class="grid md:grid-cols-2 gap-5">
    <div class="bg-white rounded-2xl border border-stone-200 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-3">Capaian vs Target per Indikator</h3>
      <div class="relative" style="height:260px"><canvas id="cRadar"></canvas></div>
    </div>
    <div class="bg-white rounded-2xl border border-stone-200 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-3">Sebaran Kategori Item</h3>
      <div class="relative" style="height:260px"><canvas id="cDonat"></canvas></div>
    </div>
  </div>

  @php
    $lbl = []; $capaian = []; $tgt = [];
    foreach ($hasil['indicators'] as $I) {
      $w = $I['weight'] ?: 1;
      $lbl[]     = 'Indikator ' . $I['code'];
      $capaian[] = round((($I['score'] ?? 0) / $w) * 100, 1);
      $tgt[]     = round((($I['target'] ?? 0) / $w) * 100, 1);
    }
    $donat = [];
    foreach ($T::LV as $i => $n) $donat[] = $sebaran[$i + 1] ?? 0;
  @endphp

  <script>
    eqChartSiap(function () {
      new Chart(document.getElementById('cRadar'), {
        type: 'radar',
        data: {
          labels: @json($lbl),
          datasets: [
            { label: 'Capaian %', data: @json($capaian),
              borderColor: '#12897F', backgroundColor: 'rgba(18,137,127,.18)',
              pointBackgroundColor: '#12897F', borderWidth: 2 },
            { label: 'Target %', data: @json($tgt),
              borderColor: '#1B2024', borderDash: [5,4], backgroundColor: 'transparent',
              pointBackgroundColor: '#1B2024', borderWidth: 1.5 }
          ]
        },
        options: { responsive: true, maintainAspectRatio: false,
          scales: { r: { min: 0, max: 100, ticks: { stepSize: 25, backdropColor: 'transparent' },
                         grid: { color: '#e7e5e4' }, angleLines: { color: '#e7e5e4' } } },
          plugins: { legend: { position: 'bottom' } } }
      });

      new Chart(document.getElementById('cDonat'), {
        type: 'doughnut',
        data: { labels: @json($T::LV),
                datasets: [{ data: @json($donat), backgroundColor: window.eqWarnaLevel, borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '62%',
          plugins: { legend: { position: 'bottom' },
                     tooltip: { callbacks: { label: function (c) { return c.label + ': ' + c.raw + ' item'; } } } } }
      });
    });
  </script>

  <div class="grid md:grid-cols-2 gap-5">
    {{-- ── Rekap metode ── --}}
    <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-stone-100">
        <h3 class="text-[13px] font-bold text-cam-ink">Keterpenuhan per Metode</h3>
      </div>
      <table class="w-full text-[12px]">
        <tbody>
          @foreach($metode as $m)
            <tr class="border-b border-stone-50 last:border-0">
              <td class="px-5 py-2.5">
                <b>{{ $m['key'] }}</b>
                <span class="text-stone-500">· {{ $m['name'] }}</span>
              </td>
              <td class="px-3 py-2.5 text-right num text-stone-500 whitespace-nowrap">{{ $m['filled'] }}/{{ $m['items'] }}</td>
              <td class="px-5 py-2.5 text-right num font-bold">{{ $m['ratio'] === null ? '—' : number_format($m['ratio'], 3) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    {{-- ── Sebaran kategori item ── --}}
    <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-stone-100">
        <h3 class="text-[13px] font-bold text-cam-ink">Sebaran Kategori Item</h3>
      </div>
      <div class="p-5 space-y-2.5">
        @foreach($T::LV as $i => $nama)
          @php
            $n   = $sebaran[$i + 1] ?? 0;
            $pct = $T::totalItems() ? $n / $T::totalItems() * 100 : 0;
          @endphp
          <div>
            <div class="flex justify-between text-[11.5px] mb-1">
              <span class="font-semibold text-stone-600">{{ $nama }}</span>
              <span class="num text-stone-500">{{ $n }}</span>
            </div>
            <div class="h-1.5 rounded-full bg-stone-100 overflow-hidden">
              <div class="h-full rounded-full" style="width: {{ max(0, round($pct)) }}%; background: {{ $T::levelHex($i + 1) }}"></div>
            </div>
          </div>
        @endforeach
        <div class="flex justify-between text-[11.5px] pt-1 border-t border-stone-100">
          <span class="text-stone-400">Belum lengkap</span>
          <span class="num text-stone-400">{{ $sebaran['none'] ?? 0 }}</span>
        </div>
      </div>
    </div>
  </div>

  {{-- ── Gap terbesar ── --}}
  <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-stone-100">
      <h3 class="text-[13px] font-bold text-cam-ink">10 Item dengan Capaian Terendah</h3>
    </div>
    @if(count($gaps) === 0)
      <p class="px-5 py-6 text-[12.5px] text-stone-400">Belum ada item yang dinilai.</p>
    @else
      <table class="w-full text-[12px]">
        <tbody>
          @foreach($gaps as $g)
            <tr class="border-b border-stone-50 last:border-0">
              <td class="px-5 py-2.5 num font-bold text-stone-500 w-16">{{ $g['code'] }}</td>
              <td class="px-2 py-2.5 text-stone-700">{{ \Illuminate\Support\Str::limit($g['name'] ?? '', 70) }}</td>
              <td class="px-3 py-2.5 text-right num text-stone-500 whitespace-nowrap">{{ number_format($g['nilai'] ?? 0, 1) }}/{{ $g['max'] }}</td>
              <td class="px-5 py-2.5 text-right">@include('tpkkp._badge', ['cat' => $g['category']])</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>
</div>
@endsection

@extends('layouts.app')
@section('title','Analitik & KPI')

@section('content')
@php use App\Support\Hazard; $maksTren = max(array_values($tren) ?: [1]) ?: 1; @endphp
<div class="max-w-6xl mx-auto space-y-5">

  <form method="GET" class="bg-white rounded-2xl shadow-card border border-stone-100 p-3 flex flex-wrap items-center gap-2">
    <span class="text-[12.5px] font-semibold text-stone-500 px-1">Periode</span>
    <select name="bulan" onchange="this.form.submit()" class="ring-focus rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] font-semibold text-stone-600">
      <option value="">Semua bulan (akumulasi)</option>
      @foreach($bulanOpsi as $b)<option value="{{ $b }}" @selected($bulan===$b)>{{ \Carbon\Carbon::parse($b.'-01')->translatedFormat('F Y') }}</option>@endforeach
    </select>
    <span class="text-[11.5px] text-stone-400 ml-auto"><span class="num font-bold text-stone-600">{{ $total }}</span> laporan · target dihitung atas <span class="num font-bold text-stone-600">{{ $bulanAktif }}</span> bulan</span>
  </form>

  {{-- KPI per golongan --}}
  <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
    <div class="px-5 py-4 border-b border-stone-100">
      <h3 class="text-[14px] font-bold text-cam-ink">Capaian KPI per Golongan Jabatan</h3>
      <p class="text-[11.5px] text-stone-400 mt-0.5">Target: General Manager &amp; Manager 1 · Superintendent 3 · lainnya 4 laporan per bulan.</p>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-[12.5px]">
        <thead><tr class="bg-stone-50 border-b border-stone-100">
          @foreach (['Golongan','Orang','Target','Aktual','Capaian','Tercapai'] as $h)
            <th class="{{ in_array($h, ['Orang','Target','Aktual','Tercapai']) ? 'text-right' : 'text-left' }} font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-2.5">{{ $h }}</th>
          @endforeach
        </tr></thead>
        <tbody>
          @forelse($perGolongan as $gol => $g)
            @php $pct = $g['target'] ? round($g['aktual']/$g['target']*100) : 0; @endphp
            <tr class="border-b border-stone-50 last:border-0">
              <td class="px-4 py-3 font-bold text-cam-ink">{{ $gol }}</td>
              <td class="px-4 py-3 num text-right text-stone-500">{{ $g['orang'] }}</td>
              <td class="px-4 py-3 num text-right text-stone-500">{{ $g['target'] }}</td>
              <td class="px-4 py-3 num text-right font-semibold text-cam-ink">{{ $g['aktual'] }}</td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <div class="flex-1 h-1.5 rounded-full bg-stone-100 overflow-hidden min-w-[60px]">
                    <div class="h-full rounded-full {{ $pct >= 100 ? 'lime-gradient' : 'bg-amber-400' }}" style="width: {{ min(100,$pct) }}%"></div>
                  </div>
                  <span class="num font-bold {{ $pct >= 100 ? 'text-cam-lime-deep' : 'text-amber-600' }}">{{ $pct }}%</span>
                </div>
              </td>
              <td class="px-4 py-3 num text-right text-stone-500">{{ $g['tercapai'] }}/{{ $g['orang'] }}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="px-4 py-10 text-center text-stone-400">Belum ada data.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Tren 12 bulan --}}
  <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
    <h3 class="text-[14px] font-bold text-cam-ink mb-4">Tren Laporan 12 Bulan</h3>
    <div class="flex items-end gap-1.5 h-40">
      @foreach($tren as $k => $v)
        <div class="flex-1 flex flex-col items-center gap-1.5">
          <span class="num text-[10.5px] font-bold text-stone-500">{{ $v ?: '' }}</span>
          <div class="w-full rounded-t-lg lime-gradient transition-all" style="height: {{ $v ? max(6, $v/$maksTren*112) : 2 }}px; {{ $v ? '' : 'background:#e7e5e4' }}"></div>
          <span class="text-[9px] text-stone-400">{{ \Carbon\Carbon::parse($k.'-01')->translatedFormat('M') }}</span>
        </div>
      @endforeach
    </div>
  </div>

  {{-- Distribusi --}}
  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @foreach ([['Status',$distStatus],['Risiko',$distRisiko],['Kategori',$distKategori],['Lokasi teratas',$distLokasi]] as [$judul,$dist])
      <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
        <h3 class="text-[13px] font-bold text-cam-ink mb-3">{{ $judul }}</h3>
        @php $maks = $dist->max() ?: 1; @endphp
        <div class="space-y-2">
          @forelse($dist as $k => $v)
            <div>
              <div class="flex items-center justify-between text-[11.5px] mb-1">
                <span class="text-stone-600 truncate pr-2">{{ $k ?: '—' }}</span>
                <span class="num font-bold text-stone-500">{{ $v }}</span>
              </div>
              <div class="h-1.5 rounded-full bg-stone-100 overflow-hidden">
                <div class="h-full rounded-full lime-gradient" style="width: {{ $v/$maks*100 }}%"></div>
              </div>
            </div>
          @empty
            <p class="text-[12px] text-stone-300">Belum ada data.</p>
          @endforelse
        </div>
      </div>
    @endforeach
  </div>

  {{-- KPI per orang --}}
  <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
    <div class="px-5 py-4 border-b border-stone-100"><h3 class="text-[14px] font-bold text-cam-ink">Capaian per Pelapor</h3></div>
    <div class="overflow-x-auto">
      <table class="w-full text-[12.5px]">
        <thead><tr class="bg-stone-50 border-b border-stone-100">
          @foreach (['Nama','Jabatan','Golongan','Target','Aktual','Capaian'] as $h)
            <th class="{{ in_array($h, ['Target','Aktual','Capaian']) ? 'text-right' : 'text-left' }} font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-2.5">{{ $h }}</th>
          @endforeach
        </tr></thead>
        <tbody>
          @forelse($perOrang as $o)
            @php $pct = $o['target'] ? round($o['aktual']/$o['target']*100) : 0; @endphp
            <tr class="border-b border-stone-50 last:border-0">
              <td class="px-4 py-2.5 font-semibold text-cam-ink">{{ $o['nama'] }}</td>
              <td class="px-4 py-2.5 text-stone-500">{{ $o['jabatan'] ?: '—' }}</td>
              <td class="px-4 py-2.5"><span class="text-[10px] font-bold bg-stone-100 text-stone-500 px-2 py-0.5 rounded-full">{{ $o['gol'] }}</span></td>
              <td class="px-4 py-2.5 num text-right text-stone-500">{{ $o['target'] }}</td>
              <td class="px-4 py-2.5 num text-right font-bold text-cam-ink">{{ $o['aktual'] }}</td>
              <td class="px-4 py-2.5 num text-right font-bold {{ $pct >= 100 ? 'text-cam-lime-deep' : 'text-amber-600' }}">{{ $pct }}%</td>
            </tr>
          @empty
            <tr><td colspan="6" class="px-4 py-10 text-center text-stone-400">Belum ada data.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

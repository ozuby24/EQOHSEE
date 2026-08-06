@extends('layouts.app')
@section('title','Evaluasi Temuan')

@section('content')
@php
  use App\Support\Hazard;
  $maksTren = max(array_map(fn($t) => $t['hazard'] + $t['inspeksi'], $tren) ?: [1]) ?: 1;
  $bar = function($dist, $warna = null) {
      $maks = collect($dist)->max() ?: 1;
      return [$maks, $warna];
  };
@endphp

<div class="max-w-6xl mx-auto space-y-5">

  {{-- Penyaring --}}
  <form method="GET" class="bg-white rounded-2xl shadow-card border border-stone-100 p-3 flex flex-wrap items-center gap-2">
    <span class="text-[12.5px] font-semibold text-stone-500 px-1">Evaluasi</span>
    <select name="bulan" onchange="this.form.submit()" class="ring-focus rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] font-semibold text-stone-600">
      <option value="">Semua periode</option>
      @foreach($bulanOpsi as $b)<option value="{{ $b }}" @selected($bulan===$b)>{{ \Carbon\Carbon::parse($b.'-01')->translatedFormat('F Y') }}</option>@endforeach
    </select>
    <select name="perusahaan" onchange="this.form.submit()" class="ring-focus rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] font-semibold text-stone-600">
      <option value="">Semua perusahaan</option>
      @foreach($companies as $c)<option value="{{ $c->id }}" @selected($perusahaan==$c->id)>{{ $c->name }}</option>@endforeach
    </select>
    @if($bulan || $perusahaan)
      <a href="{{ route('hazard.evaluasi') }}" class="px-3 py-2.5 text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink">Reset</a>
    @endif
    <div class="ml-auto flex gap-2">
      <a href="{{ route('hazard.ekspor.csv', request()->query()) }}" class="rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12px] font-bold text-stone-600 hover:bg-stone-50 transition">⤓ Excel</a>
      <a href="{{ route('hazard.ekspor.cetak', request()->query()) }}" target="_blank" class="rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12px] font-bold text-stone-600 hover:bg-stone-50 transition">⎙ PDF</a>
    </div>
  </form>

  {{-- Ringkasan utama --}}
  <div class="grid gap-3 grid-cols-2 lg:grid-cols-4">
    @foreach ([
      ['Total temuan', $ringkas['totalTemuan'], 'text-cam-ink', 'Hazard + inspeksi tidak sesuai'],
      ['Belum ditutup', $ringkas['belumTutup'], 'text-red-500', 'Perlu tindak lanjut'],
      ['Risiko tinggi', $ringkas['risikoTinggi'], 'text-red-600', 'Prioritas utama'],
      ['Lokasi terdampak', $perLokasi->count(), 'text-cam-lime-deep', 'Titik berbeda'],
    ] as [$l,$v,$c,$sub])
      <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
        <div class="stat {{ $c }}">{{ $v }}</div>
        <div class="text-[12px] font-semibold text-stone-600 mt-2">{{ $l }}</div>
        <div class="text-[10.5px] text-stone-400 mt-0.5">{{ $sub }}</div>
      </div>
    @endforeach
  </div>

  {{-- Sumber temuan --}}
  <div class="grid gap-4 lg:grid-cols-3">
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-3.5">Sumber Temuan</h3>
      @php $tot = max(1, $ringkas['hazard'] + $ringkas['temuanInspeksi']); @endphp
      <div class="space-y-3">
        @foreach ([['Hazard Report', $ringkas['hazard'], '#84cc16'], ['Temuan Inspeksi', $ringkas['temuanInspeksi'], '#f59e0b']] as [$l,$v,$w])
          <div>
            <div class="flex items-center justify-between text-[12px] mb-1">
              <span class="font-semibold text-stone-600">{{ $l }}</span>
              <span class="num font-bold text-cam-ink">{{ $v }} <span class="text-stone-400 font-normal">({{ round($v/$tot*100) }}%)</span></span>
            </div>
            <div class="h-2 rounded-full bg-stone-100 overflow-hidden">
              <div class="h-full rounded-full" style="width: {{ $v/$tot*100 }}%; background: {{ $w }}"></div>
            </div>
          </div>
        @endforeach
      </div>
      <div class="grid grid-cols-2 gap-2 mt-4 pt-3.5 border-t border-stone-100 text-center">
        <div><div class="stat stat-sm text-stone-600">{{ $ringkas['itemDiperiksa'] }}</div><div class="text-[10.5px] text-stone-400 mt-1">Item diperiksa</div></div>
        <div><div class="stat stat-sm text-amber-600">{{ $ringkas['naikJadiHazard'] }}</div><div class="text-[10.5px] text-stone-400 mt-1">Naik ke Hazard</div></div>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 lg:col-span-2">
      <h3 class="text-[13px] font-bold text-cam-ink mb-3.5">Lokasi dengan Temuan Terbanyak</h3>
      @php $maksLok = $perLokasi->max() ?: 1; @endphp
      <div class="space-y-2">
        @forelse($perLokasi->take(8) as $lok => $n)
          <div>
            <div class="flex items-center justify-between text-[12px] mb-1">
              <span class="text-stone-600 truncate pr-2">📍 {{ $lok }}</span>
              <span class="num font-bold text-stone-600">{{ $n }}</span>
            </div>
            <div class="h-1.5 rounded-full bg-stone-100 overflow-hidden">
              <div class="h-full rounded-full lime-gradient" style="width: {{ $n/$maksLok*100 }}%"></div>
            </div>
          </div>
        @empty
          <p class="text-[12.5px] text-stone-300 text-center py-6">Belum ada data lokasi.</p>
        @endforelse
      </div>
    </div>
  </div>

  {{-- Penyebab temuan --}}
  <div>
    <h3 class="text-[14px] font-bold text-cam-ink mb-3">Penyebab Temuan Terbanyak</h3>
    <div class="grid gap-4 lg:grid-cols-3">
      @foreach ([
        ['Bentuk Unsafe Action', $topUA, '#ef4444'],
        ['Bentuk Unsafe Condition', $topUC, '#f59e0b'],
        ['Parameter Inspeksi Tidak Sesuai', $paramSering, '#84cc16'],
      ] as [$judul,$dist,$warna])
        <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
          <h4 class="text-[12.5px] font-bold text-cam-ink mb-3">{{ $judul }}</h4>
          @php $mx = collect($dist)->max() ?: 1; @endphp
          <div class="space-y-2">
            @forelse($dist as $k => $v)
              <div>
                <div class="flex items-start justify-between gap-2 text-[11.5px] mb-1">
                  <span class="text-stone-600 leading-snug">{{ \Illuminate\Support\Str::limit($k, 46) }}</span>
                  <span class="num font-bold text-stone-600 shrink-0">{{ $v }}</span>
                </div>
                <div class="h-1.5 rounded-full bg-stone-100 overflow-hidden">
                  <div class="h-full rounded-full" style="width: {{ $v/$mx*100 }}%; background: {{ $warna }}"></div>
                </div>
              </div>
            @empty
              <p class="text-[12px] text-stone-300 text-center py-4">Belum ada data.</p>
            @endforelse
          </div>
        </div>
      @endforeach
    </div>
  </div>

  {{-- Tren gabungan --}}
  <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-[14px] font-bold text-cam-ink">Tren Temuan 12 Bulan</h3>
      <div class="flex gap-3 text-[11px]">
        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded bg-cam-lime"></span> Hazard Report</span>
        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded bg-amber-500"></span> Temuan Inspeksi</span>
      </div>
    </div>
    <div class="flex items-end gap-1.5 h-44">
      @foreach($tren as $k => $t)
        @php $jml = $t['hazard'] + $t['inspeksi']; @endphp
        <div class="flex-1 flex flex-col items-center gap-1.5">
          <span class="num text-[10.5px] font-bold text-stone-500">{{ $jml ?: '' }}</span>
          <div class="w-full flex flex-col justify-end" style="height: 112px">
            @if($t['inspeksi'])<div class="w-full rounded-t-lg bg-amber-500" style="height: {{ $t['inspeksi']/$maksTren*112 }}px"></div>@endif
            @if($t['hazard'])<div class="w-full {{ $t['inspeksi'] ? '' : 'rounded-t-lg' }} lime-gradient" style="height: {{ $t['hazard']/$maksTren*112 }}px"></div>@endif
            @if(!$jml)<div class="w-full rounded bg-stone-100" style="height:2px"></div>@endif
          </div>
          <span class="text-[9px] text-stone-400">{{ \Carbon\Carbon::parse($k.'-01')->translatedFormat('M') }}</span>
        </div>
      @endforeach
    </div>
  </div>

  {{-- Distribusi + kecepatan --}}
  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @foreach ([['Kategori',$perKategori],['Risiko',$perRisiko],['Status',$perStatus],['Hirarki Pengendalian',$perHirarki]] as [$judul,$dist])
      <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
        <h3 class="text-[12.5px] font-bold text-cam-ink mb-3">{{ $judul }}</h3>
        @php $mx = collect($dist)->max() ?: 1; @endphp
        <div class="space-y-2">
          @forelse($dist as $k => $v)
            <div>
              <div class="flex items-center justify-between text-[11.5px] mb-1">
                <span class="text-stone-600 truncate pr-2">{{ $k ?: '—' }}</span>
                <span class="num font-bold text-stone-500">{{ $v }}</span>
              </div>
              <div class="h-1.5 rounded-full bg-stone-100 overflow-hidden">
                <div class="h-full rounded-full" style="width: {{ $v/$mx*100 }}%; background: {{ \App\Support\Hazard::WARNA_RISIKO[$k] ?? (\App\Support\Hazard::WARNA_STATUS[$k] ?? '#84cc16') }}"></div>
              </div>
            </div>
          @empty
            <p class="text-[12px] text-stone-300 text-center py-3">—</p>
          @endforelse
        </div>
      </div>
    @endforeach
  </div>

  {{-- Per perusahaan terlapor --}}
  <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-stone-100">
      <div>
        <h3 class="text-[14px] font-bold text-cam-ink">Temuan per Perusahaan Terlapor</h3>
        @if($rerataHari !== null)
          <p class="text-[11.5px] text-stone-400 mt-0.5">Rata-rata waktu penutupan: <span class="num font-bold text-cam-ink">{{ $rerataHari }}</span> hari</p>
        @endif
      </div>
      <a href="{{ route('hazard.pengingat') }}" class="text-[11.5px] font-bold text-cam-lime-deep hover:underline">Kirim pengingat →</a>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-[12.5px]">
        <thead><tr class="bg-stone-50 border-b border-stone-100">
          <th class="text-left font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-2.5">Perusahaan</th>
          @foreach (['Total','Ditutup','Risiko Tinggi','Penyelesaian'] as $h)
            <th class="text-right font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-2.5">{{ $h }}</th>
          @endforeach
        </tr></thead>
        <tbody>
          @forelse($perPerusahaan as $nama => $d)
            @php $pct = $d['total'] ? round($d['tutup']/$d['total']*100) : 0; @endphp
            <tr class="border-b border-stone-50 last:border-0">
              <td class="px-4 py-3 font-bold text-cam-ink">{{ $nama }}</td>
              <td class="px-4 py-3 num text-right text-stone-600">{{ $d['total'] }}</td>
              <td class="px-4 py-3 num text-right text-cam-lime-deep font-semibold">{{ $d['tutup'] }}</td>
              <td class="px-4 py-3 num text-right {{ $d['tinggi'] ? 'text-red-500 font-bold' : 'text-stone-400' }}">{{ $d['tinggi'] }}</td>
              <td class="px-4 py-3 num text-right font-bold {{ $pct >= 80 ? 'text-cam-lime-deep' : 'text-amber-600' }}">{{ $pct }}%</td>
            </tr>
          @empty
            <tr><td colspan="5" class="px-4 py-10 text-center text-stone-400">Belum ada data.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

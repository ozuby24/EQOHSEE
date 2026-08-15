@props(['titik', 'warna' => '#F57C00', 'target' => null, 'tinggi' => 150, 'satuan' => null, 'desimal' => 2])

{{-- Grafik garis sederhana.
     Digambar sebagai SVG dari data, bukan lewat pustaka grafik: satu
     berkas JavaScript tambahan tidak sepadan untuk satu garis, dan
     halaman ini harus tetap terbaca di jaringan site tambang.

     Skala ditulis di tepi kiri. Tanpa angka acuan, garis hanya
     memperlihatkan naik-turun tanpa memberi tahu naik-turun berapa —
     dan itu belum cukup untuk mengambil keputusan. --}}
@php
  $nilai = array_values(array_map('floatval', $titik));
  $n     = count($nilai);

  $puncak = $n ? max(max($nilai), (float) ($target ?? 0)) : 0.0;
  $skala  = $puncak > 0 ? $puncak * 1.12 : 1.0;          // ruang di atas puncak
  $L = 600; $T = $tinggi;

  $koordinat = [];
  foreach ($nilai as $i => $v) {
      $x = $n > 1 ? ($i / ($n - 1)) * $L : $L / 2;
      $koordinat[] = round($x, 1).' '.round($T - ($v / $skala) * $T, 1);
  }
  $garis = $koordinat ? 'M'.implode(' L', $koordinat) : '';
  $isi   = $koordinat ? $garis." L{$L} {$T} L0 {$T} Z" : '';
  $uid   = 'g'.substr(md5($garis.$warna.$T), 0, 8);

  $yTarget = ($target !== null && $skala > 0) ? round($T - ((float) $target / $skala) * $T, 1) : null;
  $tulis   = fn ($v) => number_format($v, $desimal).($satuan ? ' '.$satuan : '');
@endphp

@if($n)
  <div class="flex gap-3">
    {{-- Skala; angkanya di luar SVG agar tidak ikut teregang oleh preserveAspectRatio. --}}
    <div class="shrink-0 flex flex-col justify-between text-[10px] num text-stone-400 text-right"
         style="height:{{ $T }}px">
      <span>{{ $tulis($skala) }}</span>
      <span>{{ $tulis($skala / 2) }}</span>
      <span>0</span>
    </div>

    <div class="flex-1 min-w-0 relative">
      <svg viewBox="0 0 {{ $L }} {{ $T }}" preserveAspectRatio="none" class="w-full block" style="height:{{ $T }}px">
        <defs>
          <linearGradient id="{{ $uid }}" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="{{ $warna }}" stop-opacity=".22"/>
            <stop offset="1" stop-color="{{ $warna }}" stop-opacity="0"/>
          </linearGradient>
        </defs>

        {{-- Garis bantu setengah dan penuh --}}
        @foreach ([0, $T / 2] as $y)
          <line x1="0" y1="{{ $y }}" x2="{{ $L }}" y2="{{ $y }}" stroke="#E7E2D8" stroke-width="1" vector-effect="non-scaling-stroke"/>
        @endforeach
        <line x1="0" y1="{{ $T }}" x2="{{ $L }}" y2="{{ $T }}" stroke="#D8D2C6" stroke-width="1" vector-effect="non-scaling-stroke"/>

        <path d="{{ $isi }}" fill="url(#{{ $uid }})"/>

        @if($yTarget !== null)
          <line x1="0" y1="{{ $yTarget }}" x2="{{ $L }}" y2="{{ $yTarget }}"
                stroke="#E2663A" stroke-width="1.5" stroke-dasharray="6 5" vector-effect="non-scaling-stroke"/>
        @endif

        <path d="{{ $garis }}" fill="none" stroke="{{ $warna }}" stroke-width="2.2"
              stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
      </svg>

      @if($yTarget !== null)
        <span class="absolute right-0 text-[10px] num font-bold text-cam-coral bg-white px-1 rounded shadow-sm pointer-events-none"
              style="top:{{ max(0, min($T - 16, $yTarget - 17)) }}px">Target {{ $tulis($target) }}</span>
      @endif
    </div>
  </div>
@else
  <div class="grid place-items-center text-[12px] text-stone-400" style="height:{{ $tinggi }}px">
    Belum ada data pada rentang ini.
  </div>
@endif

@props(['label', 'nilai', 'satuan' => null, 'ket' => null, 'warna' => '#0F766E', 'rasio' => null])

{{-- Kartu angka tunggal. Bilah di bawahnya hanya muncul bila ada rasio
     yang benar-benar bermakna — bilah kosong memberi kesan data hilang. --}}
<div class="kartu-lux rounded-2xl p-4">
  <div class="text-[10.5px] font-bold uppercase tracking-wide text-stone-500">{{ $label }}</div>
  <div class="mt-2 leading-none">
    <span class="stat stat-sm" style="color:{{ $warna }}">{{ $nilai }}</span>
    @if($satuan)<span class="text-[11px] font-semibold text-stone-400 ml-1">{{ $satuan }}</span>@endif
  </div>
  @if($ket)<div class="text-[10.5px] text-stone-400 mt-1.5 leading-relaxed">{{ $ket }}</div>@endif
  @if($rasio !== null)
    <div class="mt-3 h-1.5 rounded-full bg-stone-100 overflow-hidden">
      <div class="h-full rounded-full transition-all duration-700"
           style="width: {{ max(0, min(100, $rasio*100)) }}%; background:{{ $warna }}"></div>
    </div>
  @endif
</div>

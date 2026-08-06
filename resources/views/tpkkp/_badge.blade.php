@php
  $lv    = \App\Support\Tpkkp::level($cat ?? null);
  $warna = $lv ? \App\Support\Tpkkp::levelHex($lv) : '#a8a29e';
@endphp
<span class="inline-block text-[10px] font-bold px-2 py-0.5 rounded-full text-white whitespace-nowrap"
      style="background: {{ $warna }}">{{ $cat ?? 'Belum dinilai' }}</span>

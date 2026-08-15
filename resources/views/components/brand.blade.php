@props(['variant' => 'lockup', 'size' => 26])
@php
    // atribut 'dark' (bare) atau tone=white/dark => versi latar gelap
    $onDark = $attributes->has('dark')
        || in_array($attributes->get('tone'), ['white','dark'], true);
    // Satu berkas untuk kedua latar: lambangnya jingga-perak di atas
    // transparan, terbaca sama baiknya pada navy gelap maupun off-white.
    $file = 'eqohsee-mark.png';
    $showWordmark = $variant !== 'mark';
    $wmColor = $onDark ? '#FFFFFF' : '#0F1720';
    $qColor  = '#F57C00';
@endphp
<span {{ $attributes->except(['dark','tone'])->merge(['class' => 'inline-flex items-center gap-2.5 leading-none']) }}>
    <img src="{{ asset('brand/'.$file) }}" alt="EQOHSEE" style="height:{{ $size }}px;width:auto;display:block">
    @if($showWordmark)
        <span style="font-weight:800;font-size:{{ round($size*0.62) }}px;letter-spacing:-.01em;color:{{ $wmColor }}">E<span style="color:{{ $qColor }}">Q</span>OHSEE</span>
    @endif
</span>

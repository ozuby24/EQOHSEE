@props(['variant' => 'lockup', 'size' => 26])
@php
    // atribut 'dark' (bare) atau tone=white/dark => versi latar gelap
    $onDark = $attributes->has('dark')
        || in_array($attributes->get('tone'), ['white','dark'], true);
    // Dua berkas, dipilih menurut latarnya. Sebelumnya satu PNG dipakai
    // untuk keduanya dengan alasan ia "terbaca sama baiknya" — tetapi
    // varian putihnya memang ada, dan bidang navy pada versi berwarna
    // hampir hilang di atas latar navy gelap.
    //
    // Vektor, bukan PNG: berkasnya 764 byte melawan 217 KB, dan pada
    // jaringan site tambang lambang seberat itu adalah yang paling akhir
    // sampai — meninggalkan kotak kosong persis di kop halaman.
    $file = $onDark ? 'eqohsee-mark-white.svg' : 'eqohsee-mark.svg';
    $showWordmark = $variant !== 'mark';
    $wmColor = $onDark ? '#FFFFFF' : '#0F1720';
    $qColor  = '#F57C00';
@endphp
<span {{ $attributes->except(['dark','tone'])->merge(['class' => 'inline-flex items-center gap-2.5 leading-none']) }}>
    <img src="{{ \App\Support\Aset::v('brand/'.$file) }}" alt="EQOHSEE" style="height:{{ $size }}px;width:auto;display:block">
    @if($showWordmark)
        <span style="font-weight:800;font-size:{{ round($size*0.62) }}px;letter-spacing:-.01em;color:{{ $wmColor }}">E<span style="color:{{ $qColor }}">Q</span>OHSEE</span>
    @endif
</span>

@php
  /* Warna dan kata kerja mengikuti jenisnya. Opname sengaja tidak diberi
     panah arah — ia menetapkan saldo, bukan menambah atau mengurangi,
     dan panah akan membuatnya terbaca sebagai penerimaan. */
  $rupa = [
    'masuk'  => ['t-hijau',  'Masuk',  'M12 19V5M6 11l6-6 6 6'],
    'keluar' => ['t-biru',   'Keluar', 'M12 5v14M6 13l6 6 6-6'],
    'rusak'  => ['t-merah',  'Rusak',  'M12 9.3v4.2m0 3.3h.01M10.4 4 2.5 17.8A1.8 1.8 0 0 0 4.1 20.5h15.8a1.8 1.8 0 0 0 1.6-2.7L13.6 4a1.8 1.8 0 0 0-3.2 0Z'],
    'opname' => ['t-kuning', 'Opname', 'M9 11l3 3 7-7M4 12h.01M4 17h.01M4 7h.01'],
  ][$m->jenis] ?? ['t-toska', ucfirst($m->jenis), 'M5 12h14'];
@endphp
<li class="flex items-center gap-3 py-2 border-b border-stone-50 last:border-0">
  <span class="w-9 h-9 rounded-xl grid place-items-center shrink-0 {{ $rupa[0] }}">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
         stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4" aria-hidden="true">
      <path d="{{ $rupa[2] }}"/></svg>
  </span>

  <div class="min-w-0 flex-1">
    <p class="text-[12.5px] font-semibold text-[#0F1720] truncate">{{ $m->barang?->nama ?? '—' }}</p>
    <p class="text-[11px] text-stone-400 truncate">
      {{ $m->nomor }} · {{ $m->tanggal?->format('d M Y') }}@if($m->pihak) · {{ $m->pihak }}@endif
    </p>
  </div>

  <span class="text-[12.5px] font-bold tabular-nums shrink-0">
    @if($m->jenis === 'opname')
      <span class="text-stone-500">jadi {{ rtrim(rtrim(number_format($m->stok_fisik, 2, ',', '.'), '0'), ',') }}</span>
    @else
      <span class="{{ $m->jenis === 'masuk' ? 'text-[#4A8E2C]' : 'text-[#C03A3A]' }}">
        {{ $m->jenis === 'masuk' ? '+' : '−' }}{{ rtrim(rtrim(number_format($m->jumlah, 2, ',', '.'), '0'), ',') }}
      </span>
    @endif
    <span class="text-stone-400 font-normal">{{ $m->barang?->satuan }}</span>
  </span>
</li>

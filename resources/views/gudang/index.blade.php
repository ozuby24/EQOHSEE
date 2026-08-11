@extends('layouts.app')
@section('title','Gudang & Penyimpanan')
@section('subjudul','Persediaan B3, material, dan APD dalam satu register')

@section('content')
@php use App\Support\Gudang; @endphp

<div class="max-w-[1400px] mx-auto space-y-5">

  @include('gudang.partials.pesan')

  {{-- ══════════ ANGKA UTAMA ══════════ --}}
  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @foreach([
      ['Jenis Barang', $r['jumlah'],  'Terdaftar dan aktif',        'toska'],
      ['Stok Aman',    $r['aman'],    'Di atas batas minimum',      'hijau'],
      ['Menipis',      $r['menipis'], 'Perlu segera dipesan',       'kuning'],
      ['Habis',        $r['habis'],   'Tidak ada di rak',           'merah'],
    ] as [$label, $nilai, $ket, $nada])
      <div class="eq-kpi">
        <span class="eq-kpi-ikon t-{{ $nada }}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10"/></svg>
        </span>
        <div class="min-w-0">
          <p class="eq-kpi-label">{{ $label }}</p>
          <p class="eq-kpi-nilai">{{ str_pad((string) $nilai, 2, '0', STR_PAD_LEFT) }}</p>
          <p class="eq-kpi-ket">{{ $ket }}</p>
        </div>
      </div>
    @endforeach
  </div>

  {{-- ══════════ PERINGATAN PENYIMPANAN B3 ══════════ --}}
  @if(count($langgar))
    <section class="rounded-2xl border border-red-200 bg-red-50 p-5">
      <div class="flex items-start gap-3">
        <svg viewBox="0 0 24 24" fill="none" stroke="#C03A3A" stroke-width="1.9"
             stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6 shrink-0 mt-0.5" aria-hidden="true">
          <path d="M12 9.3v4.2m0 3.3h.01M10.4 4 2.5 17.8A1.8 1.8 0 0 0 4.1 20.5h15.8a1.8 1.8 0 0 0 1.6-2.7L13.6 4a1.8 1.8 0 0 0-3.2 0Z"/>
        </svg>
        <div class="min-w-0 flex-1">
          <h3 class="text-[14px] font-bold text-red-800">
            {{ count($langgar) }} pasang bahan berpantangan disimpan bersama
          </h3>
          <p class="text-[12px] text-red-700 mt-0.5">
            Pisahkan sebelum inspeksi berikutnya — dasar: PP 74/2001 dan Permenaker No. 5 Tahun 2018.
          </p>

          <ul class="mt-3 space-y-2">
            @foreach($langgar as $x)
              <li class="rounded-xl bg-white/70 border border-red-100 px-3.5 py-2.5">
                <p class="text-[12.5px] font-bold text-red-800">
                  {{ $x['a'] }} <span class="font-normal text-red-500">&times;</span> {{ $x['b'] }}
                </p>
                <p class="text-[11.5px] text-red-700 mt-0.5">{{ $x['alasan'] }}</p>
                <p class="text-[11px] text-red-500 mt-1">Lokasi: {{ $x['lokasi'] }}</p>
              </li>
            @endforeach
          </ul>
        </div>
      </div>
    </section>
  @endif

  <div class="grid gap-5 lg:grid-cols-3">

    {{-- ══════════ STOK KRITIS ══════════ --}}
    <section class="eq-panel lg:col-span-2">
      <div class="eq-panel-kepala">
        <h3>Perlu Ditindak</h3>
        <a href="{{ route('gudang.barang', ['status' => 'menipis']) }}" class="eq-panel-lihat">Lihat Semua &rarr;</a>
      </div>

      @if($kritis->isEmpty())
        <div class="eq-kosong">
          <strong>Seluruh stok di atas batas minimum</strong>
          <p>Tidak ada barang yang perlu dipesan hari ini.</p>
        </div>
      @else
        <div class="overflow-x-auto">
          <table class="w-full text-[12.5px]">
            <thead>
              <tr class="text-left text-stone-500 border-b border-stone-100">
                <th class="py-2.5 pr-3 font-semibold">Barang</th>
                <th class="py-2.5 px-3 font-semibold">Lokasi</th>
                <th class="py-2.5 px-3 font-semibold text-right">Stok</th>
                <th class="py-2.5 px-3 font-semibold text-right">Minimum</th>
                <th class="py-2.5 pl-3 font-semibold">Status</th>
              </tr>
            </thead>
            <tbody>
              @foreach($kritis as $b)
                @php $s = Gudang::statusStok($b); @endphp
                <tr class="border-b border-stone-50 last:border-0">
                  <td class="py-2.5 pr-3">
                    <span class="font-semibold text-[#14385A]">{{ $b->nama }}</span>
                    <span class="block text-[11px] text-stone-400">{{ $b->kode }}</span>
                  </td>
                  <td class="py-2.5 px-3 text-stone-500">{{ $b->lokasi?->nama ?? '—' }}</td>
                  <td class="py-2.5 px-3 text-right font-bold tabular-nums">
                    {{ rtrim(rtrim(number_format(Gudang::stok($b), 2, ',', '.'), '0'), ',') }}
                    <span class="text-stone-400 font-normal">{{ $b->satuan }}</span>
                  </td>
                  <td class="py-2.5 px-3 text-right text-stone-500 tabular-nums">
                    {{ rtrim(rtrim(number_format($b->stok_min, 2, ',', '.'), '0'), ',') }}
                  </td>
                  <td class="py-2.5 pl-3">
                    <span class="eq-lencana-kat k-{{ $s['nada'] }}">{{ $s['nama'] }}</span>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </section>

    {{-- ══════════ KEDALUWARSA ══════════ --}}
    <section class="eq-panel">
      <div class="eq-panel-kepala">
        <h3>Mendekati Kedaluwarsa</h3>
        <span class="text-[11px] text-stone-400">{{ Gudang::AMBANG_KEDALUWARSA_HARI }} hari</span>
      </div>

      @if(!count($kedaluwarsa))
        <div class="eq-kosong">
          <strong>Tidak ada yang mendekati kedaluwarsa</strong>
          <p>Batch yang tersimpan masih dalam masa berlaku.</p>
        </div>
      @else
        <ul class="space-y-2.5">
          @foreach($kedaluwarsa as $k)
            <li class="flex items-start gap-3">
              <span class="w-11 h-11 rounded-xl grid place-items-center shrink-0 text-[11px] font-bold
                           {{ $k['sisa'] < 0 ? 't-merah' : ($k['sisa'] <= 30 ? 't-kuning' : 't-biru') }}">
                {{ $k['sisa'] < 0 ? 'Lewat' : $k['sisa'].'h' }}
              </span>
              <div class="min-w-0">
                <p class="text-[12.5px] font-bold text-[#14385A] truncate">{{ $k['barang']->nama }}</p>
                <p class="text-[11.5px] text-stone-500">
                  {{ $k['tanggal']->format('d M Y') }}@if($k['batch']) · Batch {{ $k['batch'] }}@endif
                </p>
              </div>
            </li>
          @endforeach
        </ul>
      @endif
    </section>
  </div>

  <div class="grid gap-5 lg:grid-cols-3">

    {{-- ══════════ SEBARAN KATEGORI ══════════ --}}
    <section class="eq-panel">
      <div class="eq-panel-kepala"><h3>Menurut Kategori</h3></div>

      <div class="space-y-3">
        @foreach(Gudang::KATEGORI as $kode => $k)
          @php
            $n = $r['kategori'][$kode] ?? 0;
            $lebar = $r['jumlah'] > 0 ? round($n / $r['jumlah'] * 100) : 0;
          @endphp
          <a href="{{ route('gudang.barang', ['kategori' => $kode]) }}" class="block group">
            <div class="flex items-baseline justify-between gap-3">
              <span class="text-[12.5px] font-semibold text-[#14385A] group-hover:underline">{{ $k['nama'] }}</span>
              <span class="text-[12.5px] font-bold tabular-nums" style="color:var(--eq-aksen,#0E747E)">{{ $n }}</span>
            </div>
            <div class="eq-bilah mt-1.5"><i style="width:{{ $lebar }}%"></i></div>
          </a>
        @endforeach
      </div>

      @if($r['tanpa_msds'] > 0)
        <div class="mt-4 rounded-xl bg-amber-50 border border-amber-100 px-3.5 py-3">
          <p class="text-[12px] font-bold text-amber-800">{{ $r['tanpa_msds'] }} bahan B3 belum berlembar data</p>
          <p class="text-[11.5px] text-amber-700 mt-0.5">
            Tanpa LDK, petugas tidak punya rujukan penanganan tumpahan dan pertolongan pertama.
          </p>
        </div>
      @endif
    </section>

    {{-- ══════════ MUTASI TERAKHIR ══════════ --}}
    <section class="eq-panel lg:col-span-2">
      <div class="eq-panel-kepala">
        <h3>Mutasi Terakhir</h3>
        <a href="{{ route('gudang.mutasi') }}" class="eq-panel-lihat">Lihat Semua &rarr;</a>
      </div>

      @if($terakhir->isEmpty())
        <div class="eq-kosong">
          <strong>Belum ada mutasi tercatat</strong>
          <p>Penerimaan dan pengeluaran akan muncul di sini.</p>
        </div>
      @else
        <ul class="space-y-1">
          @foreach($terakhir as $m)
            @include('gudang.partials.baris-mutasi', ['m' => $m])
          @endforeach
        </ul>
      @endif
    </section>
  </div>

</div>
@endsection

@extends('layouts.app')
@section('title','Daftar Barang')
@section('subjudul','Register B3, material, dan APD beserta saldo berjalannya')

@section('content')
@php use App\Support\Gudang; @endphp

<div class="max-w-[1400px] mx-auto space-y-5">

  @include('gudang.partials.pesan')

  {{-- ══════════ PENYARING ══════════ --}}
  <form method="GET" class="bg-white rounded-2xl shadow-card border border-stone-100 p-3
                            grid gap-2.5 sm:grid-cols-2 lg:grid-cols-5">
    <input name="cari" value="{{ $f['cari'] ?? '' }}" placeholder="Cari nama, kode, part number…"
           class="lg:col-span-2 rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]
                  focus:border-[color:var(--eq-aksen,#F57C00)] focus:ring-0">

    <select name="kategori" class="rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
      <option value="">Semua kategori</option>
      @foreach(Gudang::KATEGORI as $k => $x)
        <option value="{{ $k }}" @selected(($f['kategori'] ?? '') === $k)>{{ $x['nama'] }}</option>
      @endforeach
    </select>

    <select name="status" class="rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
      <option value="">Semua status</option>
      @foreach(['aman' => 'Aman', 'menipis' => 'Menipis', 'habis' => 'Habis'] as $k => $n)
        <option value="{{ $k }}" @selected(($f['status'] ?? '') === $k)>{{ $n }}</option>
      @endforeach
    </select>

    <div class="flex gap-2">
      <button class="eq-btn-utama" style="padding:10px 20px">Saring</button>
      @if(array_filter($f))
        <a href="{{ route('gudang.barang') }}"
           class="px-3 py-2.5 text-[12.5px] font-semibold text-stone-500 hover:underline self-center">Bersihkan</a>
      @endif
    </div>
  </form>

  <div class="flex items-center justify-between gap-3 flex-wrap">
    <p class="text-[12.5px] text-stone-500">
      <b class="text-[#0F1720]">{{ $barang->count() }}</b> barang ditemukan
    </p>
    @can('admin')
      <a href="{{ route('gudang.barang.baru') }}" class="eq-btn-utama" style="flex:none;padding:10px 20px">
        + Tambah Barang
      </a>
    @endcan
  </div>

  {{-- ══════════ TABEL ══════════ --}}
  @if($barang->isEmpty())
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 px-6 py-12 text-center">
      <p class="text-[13.5px] font-bold text-[#0F1720]">Belum ada barang yang cocok</p>
      <p class="text-[12.5px] text-stone-500 mt-1">Ubah penyaring, atau tambahkan barang baru.</p>
    </div>
  @else
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-[12.5px]">
          <thead>
            <tr class="text-left text-stone-500 bg-stone-50 border-b border-stone-100">
              <th class="py-3 px-4 font-semibold">Barang</th>
              <th class="py-3 px-3 font-semibold">Kategori</th>
              <th class="py-3 px-3 font-semibold">Lokasi</th>
              <th class="py-3 px-3 font-semibold text-right">Stok</th>
              <th class="py-3 px-3 font-semibold text-right">Min.</th>
              <th class="py-3 px-3 font-semibold">Status</th>
              @can('admin')<th class="py-3 px-4"></th>@endcan
            </tr>
          </thead>
          <tbody>
            @foreach($barang as $b)
              @php $s = Gudang::statusStok($b); @endphp
              <tr class="border-b border-stone-50 last:border-0">
                <td class="py-3 px-4">
                  <span class="font-semibold text-[#0F1720]">{{ $b->nama }}</span>
                  <span class="block text-[11px] text-stone-400">
                    {{ $b->kode }}@if($b->part_number) · {{ $b->part_number }}@endif
                  </span>

                  @if($b->kategori === 'b3' && $b->kelas_b3)
                    <span class="inline-block mt-1 text-[10.5px] font-bold px-2 py-0.5 rounded-md
                                 bg-red-50 text-red-700">{{ Gudang::namaKelas($b->kelas_b3) }}</span>
                  @endif
                  @if($b->kategori === 'b3' && !$b->msds)
                    <span class="inline-block mt-1 ml-1 text-[10.5px] font-bold px-2 py-0.5 rounded-md
                                 bg-amber-50 text-amber-700">Tanpa LDK</span>
                  @endif
                </td>
                <td class="py-3 px-3">
                  <span class="eq-lencana-kat k-{{ Gudang::nadaKategori($b->kategori) }}">
                    {{ Gudang::namaKategori($b->kategori) }}
                  </span>
                </td>
                <td class="py-3 px-3 text-stone-500">{{ $b->lokasi?->nama ?? '—' }}</td>
                <td class="py-3 px-3 text-right font-bold tabular-nums text-[#0F1720]">
                  {{ rtrim(rtrim(number_format(Gudang::stok($b), 2, ',', '.'), '0'), ',') }}
                  <span class="text-stone-400 font-normal">{{ $b->satuan }}</span>
                </td>
                <td class="py-3 px-3 text-right text-stone-500 tabular-nums">
                  {{ rtrim(rtrim(number_format($b->stok_min, 2, ',', '.'), '0'), ',') }}
                </td>
                <td class="py-3 px-3">
                  <span class="eq-lencana-kat k-{{ $s['nada'] }}">{{ $s['nama'] }}</span>
                </td>
                @can('admin')
                  <td class="py-3 px-4 text-right whitespace-nowrap">
                    <a href="{{ route('gudang.barang.edit', $b) }}"
                       class="text-[12px] font-semibold" style="color:var(--eq-aksen,#F57C00)">Ubah</a>
                    <form action="{{ route('gudang.barang.hapus', $b) }}" method="POST" class="inline ml-2"
                          onsubmit="return confirm('Hapus atau nonaktifkan barang ini?')">
                      @csrf @method('DELETE')
                      <button class="text-[12px] font-semibold text-red-600">Hapus</button>
                    </form>
                  </td>
                @endcan
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

</div>
@endsection

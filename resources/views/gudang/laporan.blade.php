@extends('layouts.app')
@section('title','Laporan Stok')
@section('subjudul','Kartu stok per barang untuk periode terpilih')

@section('content')
@php
  use App\Support\Gudang;
  $angka = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
@endphp

<div class="max-w-[1400px] mx-auto space-y-5">

  <form method="GET" class="bg-white rounded-2xl shadow-card border border-stone-100 p-3
                            flex flex-wrap items-end gap-2.5 cetak-sembunyi">
    <div>
      <label class="block text-[11.5px] font-semibold text-[#0F1720] mb-1">Dari</label>
      <input name="dari" type="date" value="{{ $dari }}"
             class="rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
    </div>
    <div>
      <label class="block text-[11.5px] font-semibold text-[#0F1720] mb-1">Sampai</label>
      <input name="sampai" type="date" value="{{ $sampai }}"
             class="rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
    </div>
    <button class="eq-btn-utama" style="flex:none;padding:10px 20px">Tampilkan</button>
    <button type="button" onclick="window.print()"
            class="px-4 py-2.5 rounded-xl border border-stone-200 text-[12.5px] font-semibold text-stone-600">
      Cetak
    </button>
  </form>

  <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">

    <div class="px-6 py-5 border-b border-stone-100">
      <h3 class="text-[16px] font-bold text-[#0F1720]">Laporan Persediaan</h3>
      <p class="text-[12.5px] text-stone-500 mt-1">
        Periode {{ \App\Support\Waktu::lokal($dari)?->format('d M Y') }}
        &ndash; {{ \App\Support\Waktu::lokal($sampai)?->format('d M Y') }}
        · {{ count($baris) }} barang bermutasi atau bersaldo
      </p>
    </div>

    @if(!count($baris))
      <div class="px-6 py-12 text-center">
        <p class="text-[13.5px] font-bold text-[#0F1720]">Tidak ada mutasi pada periode ini</p>
        <p class="text-[12.5px] text-stone-500 mt-1">Pilih rentang tanggal yang lain.</p>
      </div>
    @else
      <div class="overflow-x-auto">
        <table class="w-full text-[12.5px]">
          <thead>
            <tr class="text-left text-stone-500 bg-stone-50 border-b border-stone-100">
              <th class="py-3 px-4 font-semibold">Barang</th>
              <th class="py-3 px-3 font-semibold">Kategori</th>
              <th class="py-3 px-3 font-semibold text-right">Saldo Awal</th>
              <th class="py-3 px-3 font-semibold text-right">Masuk</th>
              <th class="py-3 px-3 font-semibold text-right">Keluar</th>
              <th class="py-3 px-3 font-semibold text-right">Penyesuaian</th>
              <th class="py-3 px-4 font-semibold text-right">Saldo Akhir</th>
            </tr>
          </thead>
          <tbody>
            @foreach($baris as $x)
              <tr class="border-b border-stone-50 last:border-0">
                <td class="py-2.5 px-4">
                  <span class="font-semibold text-[#0F1720]">{{ $x['barang']->nama }}</span>
                  <span class="block text-[11px] text-stone-400">
                    {{ $x['barang']->kode }} · {{ $x['barang']->satuan }}
                  </span>
                </td>
                <td class="py-2.5 px-3">
                  <span class="eq-lencana-kat k-{{ Gudang::nadaKategori($x['barang']->kategori) }}">
                    {{ Gudang::namaKategori($x['barang']->kategori) }}
                  </span>
                </td>
                <td class="py-2.5 px-3 text-right tabular-nums text-stone-600">{{ $angka($x['awal']) }}</td>
                <td class="py-2.5 px-3 text-right tabular-nums text-[#4A8E2C] font-semibold">
                  {{ $x['masuk'] > 0 ? '+'.$angka($x['masuk']) : '—' }}
                </td>
                <td class="py-2.5 px-3 text-right tabular-nums text-[#C03A3A] font-semibold">
                  {{ $x['keluar'] > 0 ? '−'.$angka($x['keluar']) : '—' }}
                </td>
                <td class="py-2.5 px-3 text-right tabular-nums">
                  @if($x['penyesuaian'] == 0)
                    <span class="text-stone-300">—</span>
                  @else
                    <span class="font-semibold {{ $x['penyesuaian'] > 0 ? 'text-[#4A8E2C]' : 'text-[#C03A3A]' }}"
                          title="Selisih hasil stok opname">
                      {{ $x['penyesuaian'] > 0 ? '+' : '−' }}{{ $angka(abs($x['penyesuaian'])) }}
                    </span>
                  @endif
                </td>
                <td class="py-2.5 px-4 text-right tabular-nums font-bold text-[#0F1720]">
                  {{ $angka($x['akhir']) }}
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="px-6 py-4 bg-stone-50 border-t border-stone-100">
        <p class="text-[11.5px] text-stone-500 leading-relaxed">
          Saldo awal dan akhir dihitung dengan memutar ulang seluruh mutasi sampai tanggal
          bersangkutan, bukan dengan mengurangkan yang satu dari yang lain — sehingga periode
          yang memuat stok opname tetap benar. Kolom <b>penyesuaian</b> berisi selisih hasil
          opname; tiap baris berlaku
          <span class="whitespace-nowrap">awal + masuk − keluar + penyesuaian = akhir</span>.
        </p>
      </div>
    @endif
  </div>

</div>

<style>
  @media print{
    .cetak-sembunyi, #eqSidebar, .eq-topbar, #eqOverlay{display:none!important}
    main{padding:0!important}
    .shadow-card{box-shadow:none!important}
    table{font-size:10.5px}
  }
</style>
@endsection

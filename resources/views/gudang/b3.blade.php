@extends('layouts.app')
@section('title','Register B3')
@section('subjudul','Bahan berbahaya dan beracun beserta pantangan penyimpanannya')

@section('content')
@php use App\Support\Gudang; @endphp

<div class="max-w-[1400px] mx-auto space-y-5">

  @include('gudang.partials.pesan')

  {{-- ══════════ MATRIKS PANTANGAN ══════════ --}}
  <section class="eq-panel">
    <div class="eq-panel-kepala">
      <div>
        <h3>Matriks Pantangan Penyimpanan</h3>
        <p class="text-[11.5px] text-stone-400 mt-0.5">
          Merah berarti kedua kelas tidak boleh disimpan berdekatan.
        </p>
      </div>
    </div>

    @php $kelas = array_keys(Gudang::KELAS_B3); @endphp

    <div class="overflow-x-auto">
      <table class="text-[11px] border-collapse">
        <thead>
          <tr>
            <th class="p-2"></th>
            @foreach($kelas as $k)
              <th class="p-1.5 align-bottom">
                {{-- Judul kolom ditegakkan: sepuluh nama kelas mendatar
                     membuat tabelnya jauh lebih lebar daripada layar. --}}
                <span class="block whitespace-nowrap text-stone-500 font-semibold"
                      style="writing-mode:vertical-rl;transform:rotate(180deg);max-height:120px">
                  {{ Gudang::namaKelas($k) }}
                </span>
              </th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @foreach($kelas as $a)
            <tr>
              <th class="p-2 text-right whitespace-nowrap text-stone-600 font-semibold">
                {{ Gudang::namaKelas($a) }}
              </th>
              @foreach($kelas as $b)
                @php $alasan = $a === $b ? null : Gudang::pantangan($a, $b); @endphp
                <td class="p-0">
                  <span class="block w-7 h-7 m-0.5 rounded-md
                               {{ $a === $b ? 'bg-stone-100'
                                  : ($alasan ? 'bg-red-500' : 'bg-emerald-50 border border-emerald-100') }}"
                        @if($alasan) title="{{ $alasan }}" @endif></span>
                </td>
              @endforeach
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <p class="text-[11px] text-stone-400 mt-3">
      Dasar: PP No. 74 Tahun 2001, PP No. 22 Tahun 2021, dan Permenaker No. 5 Tahun 2018.
      Arahkan penunjuk ke kotak merah untuk melihat alasannya.
    </p>
  </section>

  {{-- ══════════ PELANGGARAN NYATA ══════════ --}}
  @if(count($langgar))
    <section class="rounded-2xl border border-red-200 bg-red-50 p-5">
      <h3 class="text-[14px] font-bold text-red-800">
        {{ count($langgar) }} pelanggaran penyimpanan sedang berlangsung
      </h3>
      <p class="text-[12px] text-red-700 mt-0.5">
        Hanya bahan yang benar-benar bersaldo yang dihitung.
      </p>
      <ul class="mt-3 grid gap-2 sm:grid-cols-2">
        @foreach($langgar as $x)
          <li class="rounded-xl bg-white/70 border border-red-100 px-3.5 py-2.5">
            <p class="text-[12.5px] font-bold text-red-800">{{ $x['a'] }} &times; {{ $x['b'] }}</p>
            <p class="text-[11.5px] text-red-700 mt-0.5">{{ $x['alasan'] }}</p>
            <p class="text-[11px] text-red-500 mt-1">Lokasi: {{ $x['lokasi'] }}</p>
          </li>
        @endforeach
      </ul>
    </section>
  @endif

  {{-- ══════════ DAFTAR B3 ══════════ --}}
  <section class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-stone-100">
      <h3 class="text-[15px] font-bold text-[#0F1720]">Daftar Bahan</h3>
    </div>

    @if($b3->isEmpty())
      <div class="px-6 py-12 text-center">
        <p class="text-[13.5px] font-bold text-[#0F1720]">Belum ada bahan B3 terdaftar</p>
        <p class="text-[12.5px] text-stone-500 mt-1">
          Tambahkan lewat Daftar Barang dengan kategori Bahan Berbahaya.
        </p>
      </div>
    @else
      <div class="overflow-x-auto">
        <table class="w-full text-[12.5px]">
          <thead>
            <tr class="text-left text-stone-500 bg-stone-50 border-b border-stone-100">
              <th class="py-3 px-4 font-semibold">Bahan</th>
              <th class="py-3 px-3 font-semibold">Kelas Bahaya</th>
              <th class="py-3 px-3 font-semibold">Wujud</th>
              <th class="py-3 px-3 font-semibold">Lokasi</th>
              <th class="py-3 px-3 font-semibold text-right">Stok</th>
              <th class="py-3 px-4 font-semibold">LDK</th>
            </tr>
          </thead>
          <tbody>
            @foreach($b3 as $b)
              <tr class="border-b border-stone-50 last:border-0">
                <td class="py-3 px-4">
                  <span class="font-semibold text-[#0F1720]">{{ $b->nama }}</span>
                  <span class="block text-[11px] text-stone-400">
                    {{ $b->kode }}@if($b->un_number) · UN {{ $b->un_number }}@endif
                  </span>
                </td>
                <td class="py-3 px-3">
                  @if($b->kelas_b3)
                    <span class="font-semibold text-red-700">{{ Gudang::namaKelas($b->kelas_b3) }}</span>
                    <span class="block text-[11px] text-stone-400 max-w-[240px]">
                      {{ Gudang::KELAS_B3[$b->kelas_b3]['simpan'] }}
                    </span>
                  @else
                    <span class="text-amber-700 font-semibold">Belum digolongkan</span>
                  @endif
                </td>
                <td class="py-3 px-3 text-stone-500">{{ $b->wujud ? ucfirst($b->wujud) : '—' }}</td>
                <td class="py-3 px-3 text-stone-500">{{ $b->lokasi?->nama ?? '—' }}</td>
                <td class="py-3 px-3 text-right font-bold tabular-nums text-[#0F1720]">
                  {{ rtrim(rtrim(number_format(Gudang::stok($b), 2, ',', '.'), '0'), ',') }}
                  <span class="text-stone-400 font-normal">{{ $b->satuan }}</span>
                </td>
                <td class="py-3 px-4">
                  @if($b->msds)
                    <a href="{{ asset('storage/'.$b->msds) }}" target="_blank" rel="noopener"
                       class="text-[12px] font-semibold" style="color:var(--eq-aksen,#F57C00)">Buka</a>
                  @else
                    <span class="text-[11px] font-bold px-2 py-1 rounded-md bg-amber-50 text-amber-700">Belum ada</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </section>

  {{-- ══════════ KEDALUWARSA ══════════ --}}
  @if(count($kedaluwarsa))
    <section class="eq-panel">
      <div class="eq-panel-kepala"><h3>Batch Mendekati Kedaluwarsa</h3></div>
      <ul class="grid gap-2 sm:grid-cols-2">
        @foreach($kedaluwarsa as $k)
          <li class="flex items-center gap-3 rounded-xl border border-stone-100 px-3.5 py-2.5">
            <span class="w-12 h-12 rounded-xl grid place-items-center shrink-0 text-[11px] font-bold
                         {{ $k['sisa'] < 0 ? 't-merah' : ($k['sisa'] <= 30 ? 't-kuning' : 't-biru') }}">
              {{ $k['sisa'] < 0 ? 'Lewat' : $k['sisa'].'h' }}
            </span>
            <div class="min-w-0">
              <p class="text-[12.5px] font-bold text-[#0F1720] truncate">{{ $k['barang']->nama }}</p>
              <p class="text-[11.5px] text-stone-500">
                {{ $k['tanggal']->format('d M Y') }}@if($k['batch']) · Batch {{ $k['batch'] }}@endif
              </p>
            </div>
          </li>
        @endforeach
      </ul>
    </section>
  @endif

</div>
@endsection

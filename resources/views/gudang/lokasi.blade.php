@extends('layouts.app')
@section('title','Lokasi Penyimpanan')
@section('subjudul','Gudang, rak, dan syarat penyimpanannya')

@section('content')
@php use App\Support\Gudang; @endphp

<div class="max-w-[1200px] mx-auto space-y-5">

  @include('gudang.partials.pesan')

  @can('admin')
    <details class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      <summary class="px-6 py-4 cursor-pointer text-[13px] font-bold text-[#14385A]">
        + Tambah Lokasi Penyimpanan
      </summary>

      <form method="POST" action="{{ route('gudang.lokasi.simpan') }}" class="px-6 pb-6 pt-2 border-t border-stone-100">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Kode <span class="text-red-500">*</span></label>
            <input name="kode" required class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
          </div>
          <div>
            <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Jenis <span class="text-red-500">*</span></label>
            <select name="jenis" required class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
              @foreach(['umum' => 'Umum', 'b3' => 'Khusus B3', 'material' => 'Material', 'apd' => 'APD'] as $k => $n)
                <option value="{{ $k }}">{{ $n }}</option>
              @endforeach
            </select>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Nama <span class="text-red-500">*</span></label>
            <input name="nama" required class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
          </div>
          <div>
            <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Letak</label>
            <input name="lokasi" placeholder="Blok, area, atau koordinat"
                   class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
          </div>
          <div>
            <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Penanggung Jawab</label>
            <input name="penanggung_jawab" class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
          </div>
        </div>

        <p class="text-[12px] font-semibold text-[#14385A] mt-5 mb-2">Syarat Penyimpanan</p>
        <div class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
          @foreach([
            'berventilasi' => 'Berventilasi',
            'tahan_api'    => 'Tahan api',
            'ada_tanggul'  => 'Bertanggul (secondary containment)',
            'ada_apar'     => 'Tersedia APAR',
            'ada_eyewash'  => 'Tersedia eyewash',
          ] as $k => $n)
            <label class="inline-flex items-center gap-2.5">
              <input type="checkbox" name="{{ $k }}" value="1" class="rounded border-stone-300">
              <span class="text-[12.5px] text-stone-600">{{ $n }}</span>
            </label>
          @endforeach
        </div>

        <div class="mt-5 flex justify-end">
          <button class="eq-btn-utama" style="flex:none;padding:10px 22px">Simpan Lokasi</button>
        </div>
      </form>
    </details>
  @endcan

  @if($lokasi->isEmpty())
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 px-6 py-12 text-center">
      <p class="text-[13.5px] font-bold text-[#14385A]">Belum ada lokasi penyimpanan</p>
      <p class="text-[12.5px] text-stone-500 mt-1">Tambahkan gudang atau rak untuk mulai menata barang.</p>
    </div>
  @else
    <div class="grid gap-4 lg:grid-cols-2">
      @foreach($lokasi as $l)
        @php $bermasalah = $langgar[$l->id] ?? []; @endphp
        <section class="eq-panel">
          <div class="eq-panel-kepala">
            <div class="min-w-0">
              <h3 class="truncate">{{ $l->nama }}</h3>
              <p class="text-[11.5px] text-stone-400 mt-0.5">
                {{ $l->kode }}@if($l->lokasi) · {{ $l->lokasi }}@endif
              </p>
            </div>
            <span class="eq-lencana-kat k-{{ $l->jenis === 'b3' ? 'merah' : ($l->jenis === 'apd' ? 'hijau' : 'biru') }}">
              {{ $l->jenis === 'b3' ? 'B3' : ucfirst($l->jenis) }}
            </span>
          </div>

          {{-- Syarat yang sudah dan belum dipenuhi --}}
          <div class="flex flex-wrap gap-1.5">
            @foreach([
              'berventilasi' => 'Ventilasi', 'tahan_api' => 'Tahan api',
              'ada_tanggul' => 'Tanggul', 'ada_apar' => 'APAR', 'ada_eyewash' => 'Eyewash',
            ] as $k => $n)
              <span class="text-[10.5px] font-bold px-2 py-1 rounded-md
                           {{ $l->$k ? 'bg-emerald-50 text-emerald-700' : 'bg-stone-100 text-stone-400' }}">
                {{ $l->$k ? '✓' : '×' }} {{ $n }}
              </span>
            @endforeach
          </div>

          @if($l->penanggung_jawab)
            <p class="text-[11.5px] text-stone-500 mt-3">Penanggung jawab: {{ $l->penanggung_jawab }}</p>
          @endif

          {{-- Pantangan penyimpanan --}}
          @if(count($bermasalah))
            <div class="mt-3.5 rounded-xl bg-red-50 border border-red-100 px-3.5 py-3">
              <p class="text-[12px] font-bold text-red-800">
                {{ count($bermasalah) }} pasang bahan berpantangan di lokasi ini
              </p>
              @foreach($bermasalah as $x)
                <p class="text-[11.5px] text-red-700 mt-1.5">
                  <b>{{ $x['a'] }}</b> &times; <b>{{ $x['b'] }}</b> — {{ $x['alasan'] }}
                </p>
              @endforeach
            </div>
          @endif

          <p class="text-[11.5px] text-stone-400 mt-3.5">
            {{ $l->barang->count() }} jenis barang tersimpan di sini
          </p>
        </section>
      @endforeach
    </div>
  @endif

</div>
@endsection

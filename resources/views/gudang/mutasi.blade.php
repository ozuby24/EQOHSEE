@extends('layouts.app')
@section('title','Mutasi Keluar Masuk')
@section('subjudul','Penerimaan, pengeluaran, dan barang rusak')

@section('content')
@php use App\Support\Gudang; @endphp

<div class="max-w-[1400px] mx-auto space-y-5">

  @include('gudang.partials.pesan')

  <div class="grid gap-5 lg:grid-cols-3">

    {{-- ══════════ FORMULIR ══════════ --}}
    @can('admin')
      <section class="eq-panel lg:col-span-1 self-start">
        <div class="eq-panel-kepala"><h3>Catat Mutasi</h3></div>

        <form method="POST" action="{{ route('gudang.mutasi.simpan') }}" class="space-y-3.5"
              x-data="{ jenis: '{{ old('jenis', 'masuk') }}' }">
          @csrf

          <div>
            <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Jenis <span class="text-red-500">*</span></label>
            <div class="grid grid-cols-3 gap-1.5">
              @foreach(['masuk' => 'Masuk', 'keluar' => 'Keluar', 'rusak' => 'Rusak'] as $k => $n)
                <label class="cursor-pointer">
                  <input type="radio" name="jenis" value="{{ $k }}" x-model="jenis" class="sr-only peer">
                  <span class="block text-center py-2 rounded-xl text-[12px] font-semibold border
                               border-stone-200 text-stone-500
                               peer-checked:border-transparent peer-checked:text-white"
                        :class="jenis === '{{ $k }}' ? 'eq-jenis-aktif' : ''">{{ $n }}</span>
                </label>
              @endforeach
            </div>
          </div>

          <div>
            <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Barang <span class="text-red-500">*</span></label>
            <select name="barang_id" required class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
              <option value="">— pilih barang —</option>
              @foreach($barang as $b)
                <option value="{{ $b->id }}" @selected(old('barang_id') == $b->id)>
                  {{ $b->nama }} — sisa {{ rtrim(rtrim(number_format(Gudang::stok($b), 2, ',', '.'), '0'), ',') }} {{ $b->satuan }}
                </option>
              @endforeach
            </select>
            @error('barang_id')<p class="text-[11.5px] text-red-600 mt-1">{{ $message }}</p>@enderror
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Jumlah <span class="text-red-500">*</span></label>
              <input name="jumlah" type="number" step="0.01" min="0.01" required value="{{ old('jumlah') }}"
                     class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
              @error('jumlah')<p class="text-[11.5px] text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
              <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Tanggal <span class="text-red-500">*</span></label>
              <input name="tanggal" type="date" required
                     value="{{ old('tanggal', \App\Support\Waktu::kini()->toDateString()) }}"
                     class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
            </div>
          </div>

          <div>
            <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5"
                   x-text="jenis === 'masuk' ? 'Pemasok' : 'Penerima / Bagian'">Pemasok</label>
            <input name="pihak" value="{{ old('pihak') }}"
                   class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
          </div>

          {{-- Batch dan kedaluwarsa hanya bermakna saat menerima barang. --}}
          <div x-show="jenis === 'masuk'" x-cloak class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Batch</label>
              <input name="batch" value="{{ old('batch') }}"
                     class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
            </div>
            <div>
              <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Kedaluwarsa</label>
              <input name="kadaluarsa" type="date" value="{{ old('kadaluarsa') }}"
                     class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
            </div>
          </div>

          <div>
            <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Keterangan</label>
            <textarea name="keterangan" rows="2"
                      class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">{{ old('keterangan') }}</textarea>
          </div>

          <button class="eq-btn-utama w-full" style="padding:11px">Catat Mutasi</button>
          <p class="text-[11px] text-stone-400 text-center">
            Nomor dibuat otomatis dan berurut per jenis tiap bulan.
          </p>
        </form>
      </section>
    @endcan

    {{-- ══════════ RIWAYAT ══════════ --}}
    <section class="eq-panel @can('admin') lg:col-span-2 @else lg:col-span-3 @endcan">
      <div class="eq-panel-kepala"><h3>Riwayat Mutasi</h3></div>

      <form method="GET" class="grid gap-2 sm:grid-cols-4 mb-4">
        <select name="jenis" class="rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]">
          <option value="">Semua jenis</option>
          @foreach(['masuk' => 'Masuk', 'keluar' => 'Keluar', 'rusak' => 'Rusak', 'opname' => 'Opname'] as $k => $n)
            <option value="{{ $k }}" @selected(($f['jenis'] ?? '') === $k)>{{ $n }}</option>
          @endforeach
        </select>
        <input name="dari" type="date" value="{{ $f['dari'] ?? '' }}"
               class="rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]">
        <input name="sampai" type="date" value="{{ $f['sampai'] ?? '' }}"
               class="rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]">
        <button class="eq-btn-utama" style="padding:8px 16px">Saring</button>
      </form>

      @if($mutasi->isEmpty())
        <div class="eq-kosong">
          <strong>Belum ada mutasi</strong>
          <p>Penerimaan dan pengeluaran yang dicatat akan muncul di sini.</p>
        </div>
      @else
        <ul class="space-y-1">
          @foreach($mutasi as $m)
            @include('gudang.partials.baris-mutasi', ['m' => $m])
          @endforeach
        </ul>

        <div class="mt-4">{{ $mutasi->links() }}</div>
      @endif
    </section>
  </div>

</div>

<style>
  .eq-jenis-aktif{background:linear-gradient(135deg,var(--eq-aksen,#0E747E),#12897F);
    color:#fff!important;border-color:transparent!important}
</style>
@endsection

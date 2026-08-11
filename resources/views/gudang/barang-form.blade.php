@extends('layouts.app')
@section('title', $b->exists ? 'Ubah Barang' : 'Barang Baru')
@section('subjudul','Kolom tambahan menyesuaikan kategori yang dipilih')

@section('content')
@php use App\Support\Gudang; @endphp

<div class="max-w-[900px] mx-auto space-y-5" x-data="{ kategori: '{{ old('kategori', $b->kategori ?: 'material') }}' }">

  @include('gudang.partials.pesan')

  <form method="POST" enctype="multipart/form-data"
        action="{{ $b->exists ? route('gudang.barang.ubah', $b) : route('gudang.barang.simpan') }}"
        class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
    @csrf
    @if($b->exists) @method('PUT') @endif

    <div class="px-6 py-5 border-b border-stone-100">
      <h3 class="text-[15px] font-bold text-[#14385A]">{{ $b->exists ? $b->nama : 'Barang Baru' }}</h3>
      <p class="text-[12.5px] text-stone-500 mt-1">
        Stok tidak diisi di sini — saldo berjalan dihitung dari mutasi penerimaan dan pengeluaran.
      </p>
    </div>

    {{-- ── Umum ── --}}
    <div class="px-6 py-5 grid gap-4 sm:grid-cols-2">
      <div>
        <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Kode <span class="text-red-500">*</span></label>
        <input name="kode" value="{{ old('kode', $b->kode) }}" required
               class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
        @error('kode')<p class="text-[11.5px] text-red-600 mt-1">{{ $message }}</p>@enderror
      </div>

      <div>
        <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Kategori <span class="text-red-500">*</span></label>
        <select name="kategori" x-model="kategori" required
                class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
          @foreach(Gudang::KATEGORI as $k => $x)
            <option value="{{ $k }}">{{ $x['nama'] }}</option>
          @endforeach
        </select>
      </div>

      <div class="sm:col-span-2">
        <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Nama Barang <span class="text-red-500">*</span></label>
        <input name="nama" value="{{ old('nama', $b->nama) }}" required
               class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
        @error('nama')<p class="text-[11.5px] text-red-600 mt-1">{{ $message }}</p>@enderror
      </div>

      <div>
        <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Satuan <span class="text-red-500">*</span></label>
        <input name="satuan" value="{{ old('satuan', $b->satuan ?: 'pcs') }}" required
               class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
      </div>

      <div>
        <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Stok Minimum</label>
        <input name="stok_min" type="number" step="0.01" min="0" value="{{ old('stok_min', $b->stok_min ?: 0) }}"
               class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
        <p class="text-[11px] text-stone-400 mt-1">Nol berarti belum ditetapkan — barangnya tidak akan ditandai menipis.</p>
      </div>

      <div class="sm:col-span-2">
        <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Lokasi Penyimpanan</label>
        <select name="lokasi_id" class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
          <option value="">— belum ditentukan —</option>
          @foreach($lokasi as $l)
            <option value="{{ $l->id }}" @selected(old('lokasi_id', $b->lokasi_id) == $l->id)>
              {{ $l->nama }} ({{ $l->kode }})
            </option>
          @endforeach
        </select>
      </div>
    </div>

    {{-- ── B3 ── --}}
    <div x-show="kategori === 'b3'" x-cloak class="px-6 py-5 border-t border-stone-100 bg-red-50/40">
      <h4 class="text-[13px] font-bold text-red-800 mb-3">Penggolongan B3</h4>

      <div class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
          <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Kelas Bahaya</label>
          <select name="kelas_b3" class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
            <option value="">— belum digolongkan —</option>
            @foreach(Gudang::KELAS_B3 as $k => $x)
              <option value="{{ $k }}" @selected(old('kelas_b3', $b->kelas_b3) === $k)>{{ $x['nama'] }}</option>
            @endforeach
          </select>
          <p class="text-[11px] text-stone-500 mt-1">
            Dipakai memeriksa pantangan penyimpanan antar bahan di lokasi yang sama.
          </p>
        </div>

        <div>
          <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Wujud</label>
          <select name="wujud" class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
            <option value="">—</option>
            @foreach(['padat','cair','gas'] as $w)
              <option value="{{ $w }}" @selected(old('wujud', $b->wujud) === $w)>{{ ucfirst($w) }}</option>
            @endforeach
          </select>
        </div>

        <div>
          <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Nomor UN</label>
          <input name="un_number" value="{{ old('un_number', $b->un_number) }}"
                 class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
        </div>

        <div class="sm:col-span-2">
          <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">
            Lembar Data Keselamatan (LDK / MSDS)
          </label>
          @if($b->msds)
            <p class="text-[12px] mb-1.5">
              <a href="{{ asset('storage/'.$b->msds) }}" target="_blank" rel="noopener"
                 class="font-semibold" style="color:var(--eq-aksen,#0E747E)">Lihat berkas tersimpan &rarr;</a>
            </p>
          @endif
          <input type="file" name="msds" accept="application/pdf"
                 class="block w-full text-[12.5px] text-stone-600
                        file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0
                        file:text-[12px] file:font-semibold file:bg-stone-100 file:text-[#14385A]">
          <p class="text-[11px] text-stone-500 mt-1">PDF, paling besar 5 MB.</p>
          @error('msds')<p class="text-[11.5px] text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
      </div>
    </div>

    {{-- ── APD ── --}}
    <div x-show="kategori === 'apd'" x-cloak class="px-6 py-5 border-t border-stone-100 bg-emerald-50/40">
      <h4 class="text-[13px] font-bold text-emerald-800 mb-3">Alat Pelindung Diri</h4>

      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Masa Pakai (bulan)</label>
          <input name="masa_pakai_bulan" type="number" min="1" max="600"
                 value="{{ old('masa_pakai_bulan', $b->masa_pakai_bulan) }}"
                 class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
        </div>
        <div>
          <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Ukuran</label>
          <input name="ukuran" value="{{ old('ukuran', $b->ukuran) }}" placeholder="S / M / L / 42"
                 class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
        </div>
      </div>
    </div>

    {{-- ── Material ── --}}
    <div x-show="kategori === 'material'" x-cloak class="px-6 py-5 border-t border-stone-100 bg-sky-50/40">
      <h4 class="text-[13px] font-bold text-sky-800 mb-3">Material &amp; Suku Cadang</h4>

      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Part Number</label>
          <input name="part_number" value="{{ old('part_number', $b->part_number) }}"
                 class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
        </div>
        <div>
          <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Merk</label>
          <input name="merk" value="{{ old('merk', $b->merk) }}"
                 class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
        </div>
      </div>
    </div>

    <div class="px-6 py-5 border-t border-stone-100">
      <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Keterangan</label>
      <textarea name="keterangan" rows="3"
                class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">{{ old('keterangan', $b->keterangan) }}</textarea>

      <label class="inline-flex items-center gap-2.5 mt-4">
        <input type="checkbox" name="aktif" value="1" @checked(old('aktif', $b->exists ? $b->aktif : true))
               class="rounded border-stone-300">
        <span class="text-[12.5px] text-stone-600">Barang aktif</span>
      </label>
    </div>

    <div class="px-6 py-4 bg-stone-50 border-t border-stone-100 flex justify-end gap-2.5">
      <a href="{{ route('gudang.barang') }}"
         class="px-4 py-2.5 text-[12.5px] font-semibold text-stone-500 self-center hover:underline">Batal</a>
      <button class="eq-btn-utama" style="flex:none;padding:10px 22px">Simpan</button>
    </div>
  </form>

</div>
@endsection

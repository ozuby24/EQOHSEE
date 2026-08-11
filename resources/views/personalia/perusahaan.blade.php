@extends('layouts.app')
@section('title','Data Perusahaan')
@section('subjudul','Identitas, kontak, dan logo yang mewarnai tampilan aplikasi')

@section('content')
<div class="max-w-[900px] mx-auto space-y-5">

  @include('personalia.partials.pesan')

  @if(!$p)
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 px-6 py-10 text-center">
      <p class="text-[14px] font-bold text-[#14385A]">Akun Anda belum terhubung ke perusahaan</p>
      <p class="text-[12.5px] text-stone-500 mt-1.5">
        Administrator dapat menautkannya lewat Administrasi &rarr; Kelola Pengguna.
      </p>
    </div>
  @else

  <form method="POST" action="{{ route('personalia.perusahaan.simpan') }}" enctype="multipart/form-data"
        class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
    @csrf

    <div class="px-6 py-5 border-b border-stone-100">
      <h3 class="text-[15px] font-bold text-[#14385A]">{{ $p->name }}</h3>
      <p class="text-[12.5px] text-stone-500 mt-1">
        @if($boleh)
          Data ini tercetak pada kop dokumen, laporan audit, dan sertifikat.
        @else
          Hanya administrator atau PIC perusahaan yang dapat mengubah data ini.
        @endif
      </p>
    </div>

    {{-- Logo dan warna yang diturunkannya ─────────────────── --}}
    <div class="px-6 py-5 border-b border-stone-100">
      <div class="flex flex-wrap items-center gap-5">
        <div class="w-20 h-20 rounded-xl border border-stone-200 grid place-items-center bg-stone-50 shrink-0">
          @if($p->effectiveLogo())
            <img src="{{ asset('storage/'.$p->effectiveLogo()) }}" alt="" class="max-w-[68px] max-h-[68px] object-contain">
          @else
            <span class="text-[11px] text-stone-400">Belum ada</span>
          @endif
        </div>

        <div class="flex-1 min-w-[220px]">
          <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Logo Perusahaan</label>
          <input type="file" name="logo" accept="image/jpeg,image/png,image/webp,image/svg+xml"
                 @disabled(!$boleh)
                 class="block w-full text-[12.5px] text-stone-600
                        file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0
                        file:text-[12px] file:font-semibold file:bg-stone-100 file:text-[#14385A]">
          <p class="text-[11.5px] text-stone-400 mt-1.5">
            Warna khas logo diambil otomatis dan dipakai sebagai aksen tampilan.
            SVG dan logo hitam-putih tetap tersimpan, hanya tidak mengubah warna.
          </p>
          @error('logo')<p class="text-[11.5px] text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        @if($p->logo && $boleh)
          <button type="submit" form="hapusLogo"
                  class="text-[12px] font-semibold text-red-600 hover:underline">Hapus logo</button>
        @endif
      </div>

      @if($p->theme_color)
        <div class="mt-4 flex items-center gap-3 flex-wrap">
          <span class="text-[11.5px] text-stone-500">Warna yang terbaca dari logo:</span>
          @foreach([['Aksen', $p->theme_color], ['Dasar', $p->theme_dark]] as [$nama, $w])
            @if($w)
              <span class="inline-flex items-center gap-2 text-[11.5px] text-stone-600">
                <i class="w-5 h-5 rounded-md border border-stone-200 inline-block"
                   style="background:{{ $w }}"></i>{{ $nama }} {{ $w }}
              </span>
            @endif
          @endforeach
        </div>
      @endif
    </div>

    {{-- Identitas ─────────────────────────────────────────── --}}
    <div class="px-6 py-5 grid gap-4 sm:grid-cols-2">
      @foreach([
        ['name','Nama Perusahaan',true],
        ['code','Kode',false],
        ['izin_type','Jenis Izin (IUP / IUJP)',false],
        ['commodity','Komoditas',false],
        ['location','Lokasi',false],
        ['ktt','Kepala Teknik Tambang',false],
        ['pjo','Penanggung Jawab Operasional',false],
        ['pic_name','Nama PIC',false],
        ['pic_email','Surel PIC',false],
        ['pic_phone','Telepon PIC',false],
      ] as [$k, $label, $wajib])
        <div @class(['sm:col-span-2' => $k === 'name'])>
          <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">
            {{ $label }} @if($wajib)<span class="text-red-500">*</span>@endif
          </label>
          <input type="text" name="{{ $k }}" value="{{ old($k, $p->$k) }}"
                 @if($wajib) required @endif @disabled(!$boleh)
                 class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]
                        disabled:bg-stone-50 disabled:text-stone-500
                        focus:border-[color:var(--eq-aksen,#0E747E)] focus:ring-0">
          @error($k)<p class="text-[11.5px] text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
      @endforeach

      <div class="sm:col-span-2">
        <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Alamat</label>
        <textarea name="address" rows="3" @disabled(!$boleh)
                  class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]
                         disabled:bg-stone-50 disabled:text-stone-500
                         focus:border-[color:var(--eq-aksen,#0E747E)] focus:ring-0">{{ old('address', $p->address) }}</textarea>
      </div>
    </div>

    @if($boleh)
      <div class="px-6 py-4 bg-stone-50 border-t border-stone-100 flex justify-end">
        <button class="eq-btn-utama" style="flex:none;padding:10px 22px">Simpan Perubahan</button>
      </div>
    @endif
  </form>

  @if($p->logo && $boleh)
    <form id="hapusLogo" method="POST" action="{{ route('personalia.logo.hapus') }}" class="hidden">
      @csrf @method('DELETE')
    </form>
  @endif

  @endif
</div>
@endsection

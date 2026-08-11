@extends('layouts.app')
@section('title','Data Diri')
@section('subjudul','Kontak, jabatan, dan foto yang melekat pada akun Anda')

@section('content')
<div class="max-w-[900px] mx-auto space-y-5">

  @include('personalia.partials.pesan')

  <form method="POST" action="{{ route('personalia.simpan') }}" enctype="multipart/form-data"
        class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
    @csrf

    <div class="px-6 py-5 border-b border-stone-100">
      <h3 class="text-[15px] font-bold text-[#14385A]">Data Diri</h3>
      <p class="text-[12.5px] text-stone-500 mt-1">
        Nama dan jabatan di sini yang tercetak pada sertifikat serta laporan yang Anda terbitkan.
      </p>
    </div>

    {{-- Foto ──────────────────────────────────────────────── --}}
    <div class="px-6 py-5 border-b border-stone-100 flex flex-wrap items-center gap-5">
      @if($u->avatar)
        <img src="{{ asset('storage/'.$u->avatar) }}" alt=""
             class="w-20 h-20 rounded-full object-cover border border-stone-200">
      @else
        <span class="w-20 h-20 rounded-full grid place-items-center text-white text-2xl font-black"
              style="background:linear-gradient(135deg,var(--eq-aksen,#0E747E),#2CB0BC)">
          {{ strtoupper(substr($u->name, 0, 1)) }}
        </span>
      @endif

      <div class="flex-1 min-w-[220px]">
        <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Foto Profil</label>
        <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp"
               class="block w-full text-[12.5px] text-stone-600
                      file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0
                      file:text-[12px] file:font-semibold file:bg-stone-100 file:text-[#14385A]">
        <p class="text-[11.5px] text-stone-400 mt-1.5">JPG, PNG, atau WebP — paling besar 2 MB.</p>
        @error('avatar')<p class="text-[11.5px] text-red-600 mt-1">{{ $message }}</p>@enderror
      </div>

      @if($u->avatar)
        {{-- Formulir hapus berdiri sendiri: tombol di dalam formulir induk
             akan ikut mengirim seluruh isian sebagai efek samping. --}}
        <button type="submit" form="hapusAvatar"
                class="text-[12px] font-semibold text-red-600 hover:underline">Hapus foto</button>
      @endif
    </div>

    {{-- Isian ─────────────────────────────────────────────── --}}
    <div class="px-6 py-5 grid gap-4 sm:grid-cols-2">
      @foreach([
        ['name','Nama Lengkap','text',true],
        ['email','Surel','email',true],
        ['employee_id','NIK / Nomor Induk','text',false],
        ['position','Jabatan','text',false],
        ['department','Departemen','text',false],
        ['phone','Telepon','text',false],
        ['whatsapp','WhatsApp','text',false],
      ] as [$k, $label, $tipe, $wajib])
        <div>
          <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">
            {{ $label }} @if($wajib)<span class="text-red-500">*</span>@endif
          </label>
          <input type="{{ $tipe }}" name="{{ $k }}" value="{{ old($k, $u->$k) }}"
                 @if($wajib) required @endif
                 class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]
                        focus:border-[color:var(--eq-aksen,#0E747E)] focus:ring-0">
          @error($k)<p class="text-[11.5px] text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
      @endforeach

      <div class="sm:col-span-2">
        <label class="block text-[12px] font-semibold text-[#14385A] mb-1.5">Keterangan Singkat</label>
        <textarea name="bio" rows="3" maxlength="300"
                  class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]
                         focus:border-[color:var(--eq-aksen,#0E747E)] focus:ring-0"
                  placeholder="Ringkasan peran atau kompetensi Anda.">{{ old('bio', $u->bio) }}</textarea>
        @error('bio')<p class="text-[11.5px] text-red-600 mt-1">{{ $message }}</p>@enderror
      </div>
    </div>

    {{-- Perusahaan (hanya bacaan) ─────────────────────────── --}}
    <div class="px-6 pb-5">
      <div class="rounded-xl bg-stone-50 border border-stone-100 px-4 py-3.5 flex items-center gap-3">
        @if($u->company?->effectiveLogo())
          <img src="{{ asset('storage/'.$u->company->effectiveLogo()) }}" alt=""
               class="w-10 h-10 object-contain">
        @endif
        <div class="min-w-0">
          <p class="text-[12.5px] font-bold text-[#14385A]">
            {{ $u->company?->name ?? 'Belum terhubung ke perusahaan' }}
          </p>
          <p class="text-[11.5px] text-stone-500">
            @if($u->company)
              {{ $u->company->izin_type ?: 'Perusahaan' }}
              @if($u->company->location) · {{ $u->company->location }} @endif
            @else
              Hubungi administrator untuk menautkan akun Anda.
            @endif
          </p>
        </div>
        <a href="{{ route('personalia.perusahaan') }}"
           class="ml-auto text-[12px] font-semibold shrink-0"
           style="color:var(--eq-aksen,#0E747E)">Lihat &rarr;</a>
      </div>
    </div>

    <div class="px-6 py-4 bg-stone-50 border-t border-stone-100 flex justify-end">
      <button class="eq-btn-utama" style="flex:none;padding:10px 22px">Simpan Perubahan</button>
    </div>
  </form>

  @if($u->avatar)
    <form id="hapusAvatar" method="POST" action="{{ route('personalia.avatar.hapus') }}" class="hidden">
      @csrf @method('DELETE')
    </form>
  @endif

</div>
@endsection

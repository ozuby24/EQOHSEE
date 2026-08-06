@extends('layouts.guest')
@section('title','Daftar')

@section('form')
<h1 class="text-[26px] font-extrabold text-cam-ink tracking-tight leading-tight">Buat akun</h1>
<p class="text-[13px] text-stone-500 mt-1.5 mb-7">Daftar untuk mulai mengikuti pelatihan.</p>

@if ($errors->any())
  <div class="mb-4 rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]">
    <ul class="space-y-0.5">@foreach($errors->all() as $e)<li>• {{ $e }}</li>@endforeach</ul>
  </div>
@endif

<form method="POST" action="{{ route('register') }}" class="space-y-4">
  @csrf
  <div>
    <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Nama lengkap</label>
    <input name="name" value="{{ old('name') }}" required autofocus
           class="ring-focus w-full rounded-xl border border-stone-200 bg-white px-4 py-3 text-[13.5px] transition">
  </div>
  <div>
    <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Email</label>
    <input type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
           class="ring-focus w-full rounded-xl border border-stone-200 bg-white px-4 py-3 text-[13.5px] transition">
  </div>
  <div class="grid grid-cols-2 gap-3">
    <div>
      <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">NRP / Employee ID</label>
      <input name="employee_id" value="{{ old('employee_id') }}"
             class="ring-focus w-full rounded-xl border border-stone-200 bg-white px-4 py-3 text-[13.5px] transition">
    </div>
    <div>
      <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Departemen</label>
      <input name="department" value="{{ old('department') }}" list="dlDept"
             class="ring-focus w-full rounded-xl border border-stone-200 bg-white px-4 py-3 text-[13.5px] transition">
      <datalist id="dlDept">@foreach(\App\Support\Hazard::DEPARTEMEN as $d)<option value="{{ $d }}">@endforeach</datalist>
    </div>
  </div>

  <div>
    <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Jabatan</label>
    <select name="position" required class="ring-focus w-full rounded-xl border border-stone-200 bg-white px-4 py-3 text-[13.5px] transition">
      <option value="">— pilih jabatan —</option>
      @foreach(\App\Support\Hazard::JABATAN as $j)
        <option value="{{ $j }}" @selected(old('position')===$j)>{{ $j }}</option>
      @endforeach
    </select>
    <p class="text-[11px] text-stone-400 mt-1">Menentukan target KPI Hazard Report &amp; Inspeksi Anda.</p>
  </div>

  <div>
    <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Perusahaan</label>
    <select name="company_id" class="ring-focus w-full rounded-xl border border-stone-200 bg-white px-4 py-3 text-[13.5px] transition">
      <option value="">— pilih perusahaan —</option>
      @foreach($companies ?? [] as $c)
        <option value="{{ $c->id }}" @selected(old('company_id')==$c->id)>{{ $c->name }}</option>
      @endforeach
    </select>
  </div>

  <div>
    <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Kata sandi</label>
    <input type="password" name="password" required autocomplete="new-password"
           class="ring-focus w-full rounded-xl border border-stone-200 bg-white px-4 py-3 text-[13.5px] transition">
  </div>
  <div>
    <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Ulangi kata sandi</label>
    <input type="password" name="password_confirmation" required autocomplete="new-password"
           class="ring-focus w-full rounded-xl border border-stone-200 bg-white px-4 py-3 text-[13.5px] transition">
  </div>

  <button class="lime-gradient shadow-glow w-full rounded-xl text-white py-3 text-[13.5px] font-bold hover:brightness-105 active:brightness-95 transition">
    Daftar
  </button>
</form>

<p class="text-center text-[12.5px] text-stone-500 mt-6">
  Sudah punya akun? <a href="{{ route('login') }}" class="font-bold text-cam-lime-deep hover:underline">Masuk</a>
</p>
@endsection

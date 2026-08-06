@extends('layouts.app')
@section('title', $user->exists ? 'Edit Pengguna' : 'Pengguna Baru')

@section('content')
<div class="max-w-2xl mx-auto">
  <form action="{{ $user->exists ? route('admin.users.update',$user) : route('admin.users.store') }}" method="POST"
        class="bg-white rounded-2xl shadow-card border border-stone-100 p-6 space-y-6">
    @csrf
    @if($user->exists) @method('PUT') @endif

    @if($errors->any())
      <div class="rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]">
        <ul class="space-y-0.5">@foreach($errors->all() as $e)<li>• {{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    {{-- Identitas --}}
    <div class="space-y-4">
      <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">Identitas</p>
      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Nama lengkap</label>
          <input name="name" value="{{ old('name',$user->name) }}" required
                 class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
        </div>
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Email</label>
          <input type="email" name="email" value="{{ old('email',$user->email) }}" required
                 class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
        </div>
      </div>
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">
          Kata sandi {{ $user->exists ? '(kosongkan bila tidak diubah)' : '' }}
        </label>
        <input type="password" name="password" autocomplete="new-password"
               class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
        <p class="text-[11px] text-stone-400 mt-1">Minimal 8 karakter. Default bila kosong: <code class="text-cam-lime-deep">password</code></p>
      </div>
    </div>

    {{-- Peran --}}
    <div class="space-y-4 pt-1">
      <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">Peran &amp; Akses</p>
      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Peran LMS</label>
          <select name="lms_role" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
            <option value="">— tanpa peran —</option>
            @foreach (['trainee'=>'Peserta','trainer'=>'Trainer','ktt'=>'KTT'] as $v=>$l)
              <option value="{{ $v }}" @selected(old('lms_role',$user->lms_role)===$v)>{{ $l }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Peran Audit</label>
          <select name="audit_role" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
            <option value="">— tanpa peran —</option>
            @foreach (['auditor'=>'Auditor','company'=>'Perusahaan'] as $v=>$l)
              <option value="{{ $v }}" @selected(old('audit_role',$user->audit_role)===$v)>{{ $l }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Perusahaan</label>
        <select name="company_id" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
          <option value="">— tidak ada —</option>
          @foreach($companies as $c)
            <option value="{{ $c->id }}" @selected(old('company_id',$user->company_id)==$c->id)>{{ $c->name }}</option>
          @endforeach
        </select>
      </div>

      <div class="flex flex-wrap gap-5 pt-1">
        <label class="flex items-center gap-2.5 text-[13px] text-stone-600 cursor-pointer">
          <input type="hidden" name="is_admin" value="0">
          <input type="checkbox" name="is_admin" value="1" @checked(old('is_admin',$user->is_admin))
                 class="rounded border-stone-300 text-cam-lime-dark focus:ring-cam-lime/40">
          <span class="font-semibold">Administrator</span>
          <span class="text-[11px] text-stone-400">(akses penuh semua modul)</span>
        </label>
        <label class="flex items-center gap-2.5 text-[13px] text-stone-600 cursor-pointer">
          <input type="hidden" name="active" value="0">
          <input type="checkbox" name="active" value="1" @checked(old('active', $user->exists ? $user->active : true))
                 class="rounded border-stone-300 text-cam-lime-dark focus:ring-cam-lime/40">
          <span class="font-semibold">Akun aktif</span>
        </label>
      </div>
    </div>

    {{-- Data pegawai --}}
    <div class="space-y-4 pt-1">
      <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">Data Pegawai</p>
      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">NRP / NIK</label>
          <input name="employee_id" value="{{ old('employee_id',$user->employee_id) }}"
                 class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
        </div>
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Jabatan</label>
          <select name="position" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
            <option value="">— pilih jabatan —</option>
            @foreach(\App\Support\Hazard::JABATAN as $j)
              <option value="{{ $j }}" @selected(old('position',$user->position)===$j)>{{ $j }}</option>
            @endforeach
          </select>
          <p class="text-[10.5px] text-stone-400 mt-1">Menentukan golongan &amp; target KPI.</p>
        </div>
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Departemen</label>
          <input name="department" value="{{ old('department',$user->department) }}" list="dlDeptAdmin"
                 class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
          <datalist id="dlDeptAdmin">@foreach(\App\Support\Hazard::DEPARTEMEN as $d)<option value="{{ $d }}">@endforeach</datalist>
        </div>
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Telepon</label>
          <input name="phone" value="{{ old('phone',$user->phone) }}"
                 class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
        </div>
      </div>
    </div>

    <div class="flex items-center gap-3 pt-1">
      <button class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">Simpan</button>
      <a href="{{ route('admin.users.index') }}" class="text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink">Batal</a>
    </div>
  </form>
</div>
@endsection

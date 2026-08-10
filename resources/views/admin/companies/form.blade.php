@extends('layouts.app')
@section('title', $company->exists ? 'Edit Perusahaan' : 'Perusahaan Baru')

@section('content')
<div class="max-w-2xl mx-auto">
  <form action="{{ $company->exists ? route('admin.companies.update',$company) : route('admin.companies.store') }}"
        method="POST" enctype="multipart/form-data"
        class="bg-white rounded-2xl shadow-card border border-stone-100 p-6 space-y-6">
    @csrf
    @if($company->exists) @method('PUT') @endif

    @if($errors->any())
      <div class="rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]">
        <ul class="space-y-0.5">@foreach($errors->all() as $e)<li>• {{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    <div class="space-y-4">
      <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">Identitas</p>
      <div class="grid sm:grid-cols-3 gap-4">
        <div class="sm:col-span-2">
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Nama perusahaan</label>
          <input name="name" value="{{ old('name',$company->name) }}" required class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
        </div>
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Kode</label>
          <input name="code" value="{{ old('code',$company->code) }}" placeholder="CDI" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
        </div>
      </div>
      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Pemilik izin / Induk</label>
          <select name="parent_id" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
            <option value="">— berdiri sendiri (IUP/Owner) —</option>
            @foreach(\App\Models\Company::whereKeyNot($company->id ?? 0)->orderBy('name')->get() as $o)
              <option value="{{ $o->id }}" @selected(old('parent_id',$company->parent_id)==$o->id)>{{ $o->name }}</option>
            @endforeach
          </select>
          <p class="text-[10.5px] text-stone-400 mt-1">Perusahaan jasa (IUJP) memakai logo pemiliknya bila logonya kosong.</p>
        </div>
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Prefiks nomor dokumen</label>
          <input name="doc_no_prefix" value="{{ old('doc_no_prefix',$company->doc_no_prefix) }}"
                 placeholder="{{ \App\Support\KopDokumen::prefiksDari($company->name) }}"
                 class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
          <p class="text-[10.5px] text-stone-400 mt-1">Dipakai pada kop berkas audit, mis. CAM-OHSE-IV.067h. Kosong berarti diturunkan dari nama.</p>
        </div>
      </div>
    </div>

    {{-- Kendali dokumen: tampil pada kop tiap berkas audit yang dicetak --}}
    <div class="space-y-4">
      <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">Kendali Dokumen</p>
      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Divisi</label>
          <input name="divisi" value="{{ old('divisi',$company->divisi) }}"
                 placeholder="{{ \App\Support\KopDokumen::DIVISI }}"
                 class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
        </div>
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Departemen</label>
          <input name="departemen" value="{{ old('departemen',$company->departemen) }}"
                 placeholder="{{ \App\Support\KopDokumen::DEPARTEMEN }}"
                 class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
        </div>
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Tanggal penerbitan</label>
          <input type="date" name="doc_terbit" value="{{ old('doc_terbit',$company->doc_terbit?->format('Y-m-d')) }}" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
        </div>
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Tanggal persetujuan</label>
          <input type="date" name="doc_setuju" value="{{ old('doc_setuju',$company->doc_setuju?->format('Y-m-d')) }}" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
        </div>
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Nomor revisi</label>
          <input name="doc_revisi" value="{{ old('doc_revisi',$company->doc_revisi) }}" inputmode="numeric" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] num">
        </div>
      </div>
    </div>

    <div class="space-y-4">
      <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">Spesifikasi Pertambangan</p>
      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Jenis izin</label>
          <input name="izin_type" value="{{ old('izin_type',$company->izin_type) }}" placeholder="IUP OP Batubara" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
        </div>
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Komoditas</label>
          <input name="commodity" value="{{ old('commodity',$company->commodity) }}" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
        </div>
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Site / lokasi</label>
          <input name="location" value="{{ old('location',$company->location) }}" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
        </div>
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Kelas risiko</label>
          <select name="risk_class" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
            @foreach (['Rendah','Sedang','Tinggi'] as $r)
              <option value="{{ $r }}" @selected(old('risk_class',$company->risk_class ?: 'Tinggi')===$r)>{{ $r }}</option>
            @endforeach
          </select>
        </div>
        <div class="sm:col-span-2">
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Alamat</label>
          <input name="address" value="{{ old('address',$company->address) }}" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
        </div>
      </div>
    </div>

    <div class="space-y-4">
      <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">Penanggung Jawab &amp; Tenaga Kerja</p>
      <div class="grid sm:grid-cols-2 gap-4">
        @foreach ([['ktt','KTT (Kepala Teknik Tambang)'],['pjo','PJO']] as [$f,$l])
          <div>
            <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">{{ $l }}</label>
            <input name="{{ $f }}" value="{{ old($f,$company->$f) }}" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
          </div>
        @endforeach
        @foreach ([['workers_employee','Pekerja perusahaan'],['workers_sub','Pekerja jasa pertambangan']] as [$f,$l])
          <div>
            <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">{{ $l }}</label>
            <input type="number" min="0" name="{{ $f }}" value="{{ old($f,$company->$f ?: 0) }}" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
          </div>
        @endforeach
      </div>
      <div class="sm:col-span-2 grid sm:grid-cols-3 gap-4 pt-2 border-t border-stone-100">
        <div class="sm:col-span-3">
          <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">PIC Tindak Lanjut Temuan</p>
          <p class="text-[11px] text-stone-400 mt-1">Tujuan pengingat email &amp; WhatsApp untuk temuan yang belum ditutup.</p>
        </div>
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Nama PIC</label>
          <input name="pic_name" value="{{ old('pic_name',$company->pic_name) }}" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
        </div>
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Email PIC</label>
          <input type="email" name="pic_email" value="{{ old('pic_email',$company->pic_email) }}" placeholder="pic@perusahaan.co.id" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
        </div>
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">WhatsApp PIC</label>
          <input name="pic_phone" value="{{ old('pic_phone',$company->pic_phone) }}" placeholder="08123456789" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
        </div>
      </div>

      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Logo</label>
        @if($company->logo)<img src="{{ asset('storage/'.$company->logo) }}" class="h-12 mb-2 rounded-lg">@endif
        <input type="file" name="logo" accept="image/*" class="text-[12px] text-stone-500">
      </div>
    </div>

    <div class="flex items-center gap-3 pt-1">
      <button class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">Simpan</button>
      <a href="{{ route('admin.companies.index') }}" class="text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink">Batal</a>
    </div>
  </form>
</div>
@endsection

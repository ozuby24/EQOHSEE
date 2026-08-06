@extends('layouts.app')
@section('title','Buat Hazard Report')

@section('content')
@php use App\Support\Hazard; @endphp
<div class="max-w-3xl mx-auto">
  <form action="{{ route('hazard.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
    @csrf

    @if($errors->any())
      <div class="rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]">
        <ul class="space-y-0.5">@foreach($errors->all() as $e)<li>• {{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    {{-- Pelapor --}}
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 space-y-4">
      <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">Data Pelapor</p>
      @if($manpower->count())
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Pilih pelapor tersimpan</label>
          <select id="mpPicker" onchange="isiPelapor()" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
            <option value="">— ketik manual / pilih dari daftar —</option>
            @foreach($manpower as $mp)
              <option value="{{ $mp['id'] }}"
                      data-nama="{{ $mp['nama'] }}" data-nrp="{{ $mp['nrp'] }}"
                      data-dept="{{ $mp['dept'] }}" data-jabatan="{{ $mp['jabatan'] }}"
                      data-perusahaan="{{ $mp['perusahaan'] }}">
                {{ $mp['nama'] }}{{ $mp['nrp'] ? ' · '.$mp['nrp'] : '' }}{{ $mp['jabatan'] ? ' · '.$mp['jabatan'] : '' }}
              </option>
            @endforeach
          </select>
          <p class="text-[11px] text-stone-400 mt-1">Memilih dari daftar akan mengisi NRP, departemen, jabatan, dan perusahaan secara otomatis.</p>
        </div>
      @endif

      <div class="grid sm:grid-cols-2 gap-3">
        <div class="sm:col-span-2">
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Nama lengkap</label>
          <input id="fNama" name="pelapor_nama" value="{{ old('pelapor_nama', $me->name) }}" required
                 class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
        </div>
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">NRP / Employee ID</label>
          <input id="fNrp" name="pelapor_nrp" value="{{ old('pelapor_nrp', $me->employee_id) }}"
                 class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
        </div>
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Perusahaan pelapor</label>
          <input id="fPerusahaan" name="pelapor_perusahaan" value="{{ old('pelapor_perusahaan', $me->company?->name) }}" list="dlPerusahaan"
                 class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
          <datalist id="dlPerusahaan">@foreach($companies as $c)<option value="{{ $c->name }}">@endforeach</datalist>
        </div>
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Departemen</label>
          <input id="fDept" name="pelapor_departemen" value="{{ old('pelapor_departemen', $me->department) }}" list="dlDepartemen"
                 class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
          <datalist id="dlDepartemen">@foreach(Hazard::DEPARTEMEN as $d)<option value="{{ $d }}">@endforeach</datalist>
        </div>
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Jabatan</label>
          <select id="fJabatan" name="pelapor_jabatan" required class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
            <option value="">— pilih jabatan —</option>
            @foreach(Hazard::JABATAN as $j)
              <option value="{{ $j }}" @selected(old('pelapor_jabatan', $me->position)===$j)>{{ $j }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 px-3.5 py-2.5">
        <p class="text-[11.5px] text-cam-lime-deep leading-relaxed">
          <b>Jabatan menentukan target KPI bulanan Anda</b> — General Manager &amp; Manager 1 laporan,
          Superintendent 3, lainnya 4. Karena itu jabatan dipilih dari daftar, bukan diketik bebas.
        </p>
      </div>
    </div>

    {{-- Temuan --}}
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 space-y-4">
      <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">Temuan Bahaya</p>

      <div class="grid sm:grid-cols-3 gap-3">
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Tanggal</label>
          <input type="date" name="tanggal" value="{{ old('tanggal', date('Y-m-d')) }}" required class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
        </div>
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Waktu</label>
          <input type="time" name="waktu" value="{{ old('waktu', date('H:i')) }}" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
        </div>
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Tingkat risiko</label>
          <select name="risiko" required class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
            @foreach(Hazard::RISIKO as $r)<option value="{{ $r }}" @selected(old('risiko','Sedang')===$r)>{{ $r }}</option>@endforeach
          </select>
        </div>
      </div>

      <div class="grid sm:grid-cols-2 gap-3">
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Lokasi bahaya</label>
          <select id="fLokasi" name="lokasi" onchange="cekLokasi()" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
            <option value="">— pilih lokasi —</option>
            @foreach(Hazard::LOKASI as $lo)
              <option value="{{ $lo }}" @selected(old('lokasi')===$lo)>{{ $lo }}</option>
            @endforeach
          </select>
          <input id="fLokasiLain" name="lokasi_lain" value="{{ old('lokasi_lain') }}" placeholder="Sebutkan lokasi lainnya..."
                 class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] mt-2 hidden">
        </div>
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">
            Kategori hazard <span class="text-red-500">*</span>
          </label>
          <select id="fKategori" name="kategori" onchange="cekKategori()" required class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
            <option value="">— pilih kategori —</option>
            @foreach(Hazard::KATEGORI as $k)<option value="{{ $k }}" @selected(old('kategori')===$k)>{{ $k }}</option>@endforeach
          </select>
        </div>
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">
            Perusahaan terlapor <span class="text-red-500">*</span>
          </label>
          <select name="company_id" required class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
            <option value="">— pilih perusahaan terlapor —</option>
            @foreach($companies as $c)<option value="{{ $c->id }}" @selected(old('company_id')==$c->id)>{{ $c->name }}</option>@endforeach
          </select>
        </div>
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Unit/bagian terlapor</label>
          <input name="terlapor" value="{{ old('terlapor') }}" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
        </div>
      </div>

      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Deskripsi bahaya</label>
        <textarea name="deskripsi" rows="3" required placeholder="Jelaskan bahaya yang ditemukan..."
                  class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] leading-relaxed">{{ old('deskripsi') }}</textarea>
      </div>

      {{-- Bentuk Unsafe Action — tampil bila kategori mengandung Unsafe Action --}}
      <div id="boxUA" class="hidden rounded-xl border border-stone-200 p-4">
        <div class="flex items-baseline justify-between mb-2.5">
          <p class="text-[11.5px] font-bold uppercase tracking-wide text-stone-500">Bentuk Unsafe Action</p>
          <span class="text-[10.5px] text-stone-400">boleh pilih lebih dari satu</span>
        </div>
        <div class="flex flex-wrap gap-1.5">
          @foreach(Hazard::UNSAFE_ACTION as $ua)
            <label class="cursor-pointer">
              <input type="checkbox" name="unsafe_action[]" value="{{ $ua }}" class="peer sr-only"
                     @checked(in_array($ua, (array) old('unsafe_action', [])))>
              <span class="block rounded-lg border border-stone-200 bg-white px-3 py-2 text-[11.5px] font-semibold text-stone-600
                           peer-checked:bg-lime-grad peer-checked:text-white peer-checked:border-transparent peer-checked:shadow-glow
                           hover:border-cam-lime transition">{{ $ua }}</span>
            </label>
          @endforeach
        </div>
      </div>

      {{-- Bentuk Unsafe Condition --}}
      <div id="boxUC" class="hidden rounded-xl border border-stone-200 p-4">
        <div class="flex items-baseline justify-between mb-2.5">
          <p class="text-[11.5px] font-bold uppercase tracking-wide text-stone-500">Bentuk Unsafe Condition</p>
          <span class="text-[10.5px] text-stone-400">boleh pilih lebih dari satu</span>
        </div>
        <div class="flex flex-wrap gap-1.5">
          @foreach(Hazard::UNSAFE_CONDITION as $uc)
            <label class="cursor-pointer">
              <input type="checkbox" name="unsafe_condition[]" value="{{ $uc }}" class="peer sr-only"
                     @checked(in_array($uc, (array) old('unsafe_condition', [])))>
              <span class="block rounded-lg border border-stone-200 bg-white px-3 py-2 text-[11.5px] font-semibold text-stone-600
                           peer-checked:bg-lime-grad peer-checked:text-white peer-checked:border-transparent peer-checked:shadow-glow
                           hover:border-cam-lime transition">{{ $uc }}</span>
            </label>
          @endforeach
        </div>
      </div>

      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Foto temuan</label>
        <input type="file" name="foto[]" accept="image/*" multiple class="text-[12.5px] text-stone-500">
        <p class="text-[11px] text-stone-400 mt-1">Bisa pilih lebih dari satu foto.</p>
      </div>
    </div>

    {{-- Rekomendasi --}}
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 space-y-4">
      <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">Rekomendasi Perbaikan</p>
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Hirarki pengendalian</label>
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
          @foreach(Hazard::HIRARKI as $h)
            <label class="cursor-pointer">
              <input type="radio" name="hirarki" value="{{ $h }}" class="peer sr-only" @checked(old('hirarki')===$h)>
              <span class="block text-center rounded-xl border border-stone-200 bg-white px-2 py-2.5 text-[11.5px] font-bold text-stone-500
                           peer-checked:bg-lime-grad peer-checked:text-white peer-checked:border-transparent hover:border-cam-lime transition">{{ $h }}</span>
            </label>
          @endforeach
        </div>
        <p class="text-[11px] text-stone-400 mt-1.5">Urutan dari paling efektif (Eliminasi) ke paling lemah (APD).</p>
      </div>
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Penjelasan rekomendasi</label>
        <textarea name="rekomendasi" rows="3" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] leading-relaxed">{{ old('rekomendasi') }}</textarea>
      </div>
    </div>

    <div class="sticky bottom-4 flex gap-2.5">
      <button class="lime-gradient shadow-glow flex-1 rounded-xl text-white py-3.5 text-[13.5px] font-bold hover:brightness-105 transition">Kirim Laporan</button>
      <a href="{{ route('hazard.index') }}" class="rounded-xl bg-white border border-stone-200 px-6 py-3.5 text-[13px] font-bold text-stone-500 hover:bg-stone-50 transition">Batal</a>
    </div>
  </form>
</div>
@push('scripts')
<script>
  // Isi data pelapor dari daftar man power
  function isiPelapor(){
    const o = document.getElementById('mpPicker').selectedOptions[0];
    if (!o || !o.value) return;
    const set = (id, v) => { const el = document.getElementById(id); if (el && v) el.value = v; };
    set('fNama', o.dataset.nama);
    set('fNrp', o.dataset.nrp);
    set('fDept', o.dataset.dept);
    set('fPerusahaan', o.dataset.perusahaan);
    const j = document.getElementById('fJabatan');
    if (j && o.dataset.jabatan) {
      let cocok = Array.from(j.options).find(x => x.value === o.dataset.jabatan);
      if (!cocok) {                       // jabatan di luar daftar baku → tambahkan sebagai opsi
        cocok = document.createElement('option');
        cocok.value = cocok.text = o.dataset.jabatan;
        j.appendChild(cocok);
      }
      j.value = o.dataset.jabatan;
    }
  }

  // Lokasi "Lainnya" → tampilkan isian bebas
  function cekLokasi(){
    const sel = document.getElementById('fLokasi');
    const lain = document.getElementById('fLokasiLain');
    lain.classList.toggle('hidden', sel.value !== 'Lainnya');
    if (sel.value !== 'Lainnya') lain.value = '';
  }

  // Kategori menentukan daftar bentuk yang tampil
  function cekKategori(){
    const k = document.getElementById('fKategori').value;
    document.getElementById('boxUA').classList.toggle('hidden', !k.includes('Unsafe Action'));
    document.getElementById('boxUC').classList.toggle('hidden', !k.includes('Unsafe Condition'));
  }

  document.addEventListener('DOMContentLoaded', () => { cekLokasi(); cekKategori(); });
</script>
@endpush
@endsection

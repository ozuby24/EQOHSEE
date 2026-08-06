@extends('layouts.app')
@section('title', $course->exists ? 'Edit Kursus' : 'Kursus Baru')

@section('content')
<div class="max-w-2xl mx-auto">
  <form action="{{ $course->exists ? route('courses.update', $course) : route('courses.store') }}"
        method="POST" enctype="multipart/form-data"
        class="bg-white rounded-2xl shadow-card border border-stone-100 p-6 space-y-5">
    @csrf
    @if($course->exists) @method('PUT') @endif

    @if($errors->any())
      <div class="rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]">
        <ul class="space-y-0.5">@foreach($errors->all() as $e)<li>• {{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    <div>
      <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Judul</label>
      <input name="title" value="{{ old('title', $course->title) }}" required
             class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
    </div>
    <div>
      <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Kategori</label>
      <input name="category" value="{{ old('category', $course->category) }}"
             class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
    </div>
    <div>
      <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Deskripsi</label>
      <textarea name="description" rows="4"
                class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] leading-relaxed transition">{{ old('description', $course->description) }}</textarea>
    </div>
    <div>
      <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Gambar sampul</label>
      @if($course->image)<img src="{{ asset('storage/'.$course->image) }}" class="w-28 h-16 object-cover rounded-lg mb-2">@endif
      <input type="file" name="image" accept="image/*" class="block text-[12.5px] text-stone-500">
      <p class="text-[11px] text-stone-400 mt-1">Maks 2 MB. Kosongkan jika tak ingin mengubah.</p>
    </div>

    {{-- Kode akses --}}
    <div class="rounded-xl border border-stone-200 p-4 space-y-3">
      <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">Kode Akses Kursus</p>
      <div class="grid sm:grid-cols-[1fr_auto] gap-3 items-end">
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Kode dari trainer</label>
          <input name="access_code" value="{{ old('access_code', $course->access_code ?: \App\Models\Course::kodeBaru()) }}"
                 maxlength="20" oninput="this.value=this.value.toUpperCase()"
                 class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[15px] font-bold tracking-[0.25em] uppercase num transition">
        </div>
        <button type="button" onclick="acakKode()" class="rounded-xl border border-stone-200 px-4 py-2.5 text-[12px] font-bold text-stone-600 hover:border-cam-lime hover:bg-cam-lime-soft transition">Acak</button>
      </div>
      <label class="flex items-center gap-2.5 text-[13px] text-stone-600 cursor-pointer">
        <input type="hidden" name="require_code" value="0">
        <input type="checkbox" name="require_code" value="1" @checked(old('require_code', $course->require_code))
               class="rounded border-stone-300 text-cam-lime-dark focus:ring-cam-lime/40">
        <span class="font-semibold">Wajib memasukkan kode untuk mengambil kursus</span>
      </label>
      <p class="text-[11px] text-stone-400 leading-relaxed">
        Bila aktif, peserta harus memasukkan kode ini sebelum bisa membuka kursus.
        Admin dan trainer tetap bisa masuk tanpa kode.
      </p>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Ragam sertifikat</label>
        <select name="cert_template" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
          @foreach (\App\Http\Controllers\CertificateController::TEMPLATE as $k => $l)
            <option value="{{ $k }}" @selected(old('cert_template', $course->cert_template ?: 'klasik')===$k)>{{ $l }}</option>
          @endforeach
        </select>
      </div>
      <div class="flex flex-col justify-end gap-2 pb-1">
        <label class="flex items-center gap-2.5 text-[13px] text-stone-600 cursor-pointer">
          <input type="hidden" name="auto_certificate" value="0">
          <input type="checkbox" name="auto_certificate" value="1" @checked(old('auto_certificate', $course->exists ? $course->auto_certificate : true))
                 class="rounded border-stone-300 text-cam-lime-dark focus:ring-cam-lime/40">
          <span class="font-semibold">Terbitkan sertifikat otomatis</span>
        </label>
        <label class="flex items-center gap-2.5 text-[13px] text-stone-600 cursor-pointer">
          <input type="hidden" name="require_evaluation" value="0">
          <input type="checkbox" name="require_evaluation" value="1" @checked(old('require_evaluation', $course->exists ? $course->require_evaluation : true))
                 class="rounded border-stone-300 text-cam-lime-dark focus:ring-cam-lime/40">
          <span class="font-semibold">Tunggu evaluasi trainer</span>
        </label>
      </div>
    </div>
    <p class="text-[11px] text-stone-400 -mt-2 leading-relaxed">
      Alur: peserta menyelesaikan kursus &amp; kuis → muncul di daftar tunggu trainer →
      trainer menilai → sertifikat terbit dengan nilai dari evaluasi.
    </p>

    <div class="flex items-center gap-3 pt-1">
      <button class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">Simpan</button>
      <a href="{{ route('courses.index') }}" class="text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink">Batal</a>
    </div>
  </form>
</div>
@push('scripts')
<script>
  function acakKode(){
    const c='ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; let k='';
    for (let i=0;i<6;i++) k += c[Math.floor(Math.random()*c.length)];
    document.querySelector('[name=access_code]').value = k;
  }
</script>
@endpush
@endsection

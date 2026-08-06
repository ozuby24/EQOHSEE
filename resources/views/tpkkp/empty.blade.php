@extends('layouts.app')
@section('title','PTPKKP')
@section('content')
<div class="max-w-xl mx-auto bg-white rounded-2xl border border-dashed border-stone-200 p-14 text-center">
  <p class="text-[14px] font-bold text-cam-ink">Belum ada perusahaan</p>
  <p class="text-[12.5px] text-stone-400 mt-1.5">Tambahkan perusahaan lebih dulu untuk memulai penilaian PTPKKP.</p>
  @can('admin')
    <a href="{{ route('admin.users.index') }}" class="inline-block mt-5 text-[12.5px] font-bold text-cam-lime-deep hover:underline">Ke Kelola Pengguna →</a>
  @endcan
</div>
@endsection

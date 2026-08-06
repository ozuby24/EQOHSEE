@extends('kuesioner.layout')
@section('title','Pilih Kuesioner')

@section('content')
<div class="text-center mb-7">
  <h1 class="stat text-cam-ink">Kuesioner Persepsi Keselamatan</h1>
  <p class="text-[13px] text-stone-500 mt-2">{{ $company->name }}</p>
</div>

<p class="text-[12.5px] text-stone-500 text-center mb-5">Pilih kuesioner sesuai posisi Anda:</p>

<div class="grid gap-3 sm:grid-cols-2">
  @foreach (\App\Http\Controllers\KuesionerController::KATEGORI as $key => $k)
    <a href="{{ route('kuesioner.form', [$token, $key]) }}"
       class="bg-white rounded-2xl shadow-card border border-stone-100 p-6 text-center hover:border-cam-lime/50 card-hover transition">
      <div class="w-12 h-12 mx-auto rounded-2xl lime-gradient grid place-items-center text-white text-[20px] shadow-glow">
        {{ $key === 'pekerja' ? '⛑' : '👔' }}
      </div>
      <div class="text-[15px] font-bold text-cam-ink mt-3.5">{{ $k['label'] }}</div>
      <div class="text-[11.5px] text-stone-400 mt-1">Indikator {{ $k['indicator'] }}</div>
    </a>
  @endforeach
</div>
@endsection

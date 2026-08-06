@extends('kuesioner.layout')
@section('title', 'Kuesioner — ' . $kategori['label'])

@section('content')
<div class="max-w-3xl mx-auto">
  <div class="brand-gradient rounded-2xl p-6 text-white mb-5">
    <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-cam-lime-light">Kuesioner Keselamatan Pertambangan</span>
    <h1 class="stat mt-2 leading-tight">{{ $kategori['label'] }}</h1>
    <p class="text-[12px] text-white/55 mt-1">{{ $company->name }} · {{ count($params) }} parameter · {{ collect($params)->sum(fn($p) => count($p['items'])) }} pertanyaan</p>
  </div>

  @if($errors->any())
    <div class="rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px] mb-4">
      <ul>@foreach($errors->all() as $e)<li>• {{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('kuesioner.submit', [$token, $cat]) }}" class="space-y-5">
    @csrf

    <div class="bg-white rounded-2xl border border-stone-200 p-5">
      <h2 class="text-[13px] font-bold text-cam-ink mb-3">Identitas (opsional)</h2>
      <div class="grid sm:grid-cols-2 gap-3">
        <input name="nrp" placeholder="NRP / ID Karyawan"
               class="ring-focus rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
        <input name="perusahaan" value="{{ $company->name }}" placeholder="Perusahaan"
               class="ring-focus rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
        <input name="jabatan" placeholder="Jabatan"
               class="ring-focus rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
        <input name="dept" placeholder="Departemen"
               class="ring-focus rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
      </div>
    </div>

    <div class="bg-white rounded-2xl border border-stone-200 p-5">
      <h2 class="text-[13px] font-bold text-cam-ink mb-2">Skala penilaian</h2>
      <div class="grid sm:grid-cols-5 gap-2">
        @foreach($skala as $s)
          <div class="rounded-xl border border-stone-200 bg-stone-50 px-3 py-2">
            <div class="flex items-center gap-2">
              <span class="w-5 h-5 rounded-md text-white text-[10px] font-bold grid place-items-center"
                    style="background: {{ \App\Support\Tpkkp::levelHex($s['value']) }}">{{ $s['value'] }}</span>
              <span class="text-[11.5px] font-bold text-stone-700">{{ $s['name'] }}</span>
            </div>
            <p class="text-[10.5px] text-stone-500 mt-1 leading-snug">{{ $s['hint'] ?? '' }}</p>
          </div>
        @endforeach
      </div>
    </div>

    @foreach($params as $p)
      <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
        <div class="px-5 py-3 bg-stone-50 border-b border-stone-200">
          <h3 class="text-[12.5px] font-bold text-cam-ink"><span class="num">{{ $p['code'] }}</span> · {{ $p['name'] }}</h3>
        </div>
        <div class="divide-y divide-stone-100">
          @foreach($p['items'] as $b)
            <div class="px-5 py-4">
              <div class="text-[12.5px] text-cam-ink leading-snug mb-3">
                <span class="num text-[10.5px] font-bold text-stone-400">{{ $b['code'] }}</span><br>
                {{ $b['q'] }}
              </div>
              <div class="flex flex-wrap gap-2">
                @foreach($skala as $s)
                  <label class="cursor-pointer">
                    <input type="radio" name="answers[{{ $b['code'] }}]" value="{{ $s['value'] }}" class="peer sr-only" required>
                    <span class="block rounded-xl border border-stone-200 bg-white px-3.5 py-2 text-[12px] font-semibold text-stone-600
                                 peer-checked:bg-lime-grad peer-checked:text-white peer-checked:border-transparent transition">
                      {{ $s['value'] }} · {{ $s['name'] }}
                    </span>
                  </label>
                @endforeach
              </div>
            </div>
          @endforeach
        </div>
      </div>
    @endforeach

    <button class="lime-gradient shadow-glow rounded-xl text-white w-full py-3.5 text-[14px] font-bold hover:brightness-105 transition">
      Kirim jawaban
    </button>
    <p class="text-center text-[11px] text-stone-400 pb-6">Jawaban dikirim anonim dan dipakai sebagai skor metode Kuesioner (KS).</p>
  </form>
</div>
@endsection

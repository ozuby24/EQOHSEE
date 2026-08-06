@extends('layouts.app')
@section('title','Isi Penilaian PTPKKP')

@section('content')
<div class="max-w-4xl mx-auto space-y-5">
  <div class="glass-light rounded-xl border border-stone-200/60 px-4 py-3">
    <p class="text-[12px] text-stone-500 leading-relaxed">
      Isi nilai <span class="font-semibold">0–100</span> untuk tiap parameter.
      Capaian dihitung otomatis dari bobot masing-masing parameter.
      Perusahaan: <span class="font-bold text-cam-ink">{{ $company->name }}</span>
    </p>
  </div>

  <form action="{{ route('tpkkp.assess.save') }}" method="POST" class="space-y-4">
    @csrf
    @foreach($indicators as $ind)
      <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-6">
        <div class="flex items-start justify-between gap-3 mb-4">
          <h3 class="text-[14px] font-bold text-cam-ink">{{ $ind['id'] }}. {{ $ind['name'] }}</h3>
          <span class="shrink-0 text-[10px] font-bold bg-cam-lime-soft text-cam-lime-deep px-2 py-1 rounded">Bobot {{ $ind['weight']*100 }}%</span>
        </div>

        <div class="space-y-3">
          @foreach($ind['params'] as $p)
            <div class="grid sm:grid-cols-[1fr_110px] gap-3 items-center rounded-xl border border-stone-100 p-3.5">
              <div class="min-w-0">
                <div class="text-[12.5px] font-semibold text-stone-700">{{ $p['code'] }} — {{ $p['name'] }}</div>
                <div class="text-[10.5px] text-stone-400 mt-0.5">{{ $p['dim'] }} · bobot {{ $p['weight']*100 }}% · {{ count($p['items'] ?? []) }} item</div>
              </div>
              <input type="number" min="0" max="100" name="scores[{{ $p['code'] }}]"
                     value="{{ $scores[$p['code']] ?? 0 }}"
                     class="ring-focus w-full rounded-lg border border-stone-200 px-3 py-2 text-[13.5px] font-bold text-center transition">
            </div>
          @endforeach
        </div>
      </div>
    @endforeach

    <div class="sticky bottom-4">
      <button class="lime-gradient shadow-glow w-full rounded-xl text-white py-3.5 text-[13.5px] font-bold hover:brightness-105 transition">
        Simpan Penilaian
      </button>
    </div>
  </form>
</div>
@endsection

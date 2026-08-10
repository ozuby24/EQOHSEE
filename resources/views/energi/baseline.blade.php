@extends('layouts.app')
@section('title','Baseline & Target')

@section('content')
@php use App\Support\Energi; @endphp

<div class="max-w-5xl mx-auto space-y-5">

  @if(session('ok'))
    <div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>
  @endif

  <x-energi.kepala judul="Energy Baseline &amp; Target"
      ket="Garis dasar adalah intensitas energi yang menjadi pembanding seluruh capaian tahun
           berjalan. Tanpa garis dasar, angka intensitas hanya menjadi bilangan tanpa arah —
           tidak ada yang bisa disebut membaik atau memburuk."
      :dari="$dari" :sampai="$sampai" :rute="route('energi.baseline')">

    @if($baseline)
      @php $capai = $baseline->kemajuan($r['intensitas']); @endphp
      <div class="mt-6 pt-6 hairline border-b-0">
        <div class="flex flex-wrap items-end justify-between gap-4">
          <div>
            <div class="text-[10.5px] font-bold uppercase tracking-wide text-stone-500">Baseline Berlaku — {{ $baseline->tahun }}</div>
            <div class="stat stat-lg text-cam-lime-deep mt-1.5">{{ number_format($r['intensitas'], 3) }}<span class="stat-unit">GJ/ton sekarang</span></div>
          </div>
          <div class="text-right">
            <div class="num text-[17px] font-bold text-cam-ink">{{ number_format($capai * 100, 1) }}%</div>
            <div class="text-[10.5px] text-stone-400 mt-1">kemajuan menuju target</div>
          </div>
        </div>

        {{-- Bilah baseline → target --}}
        <div class="mt-4 h-2.5 rounded-full bg-stone-100 overflow-hidden">
          <div class="h-full rounded-full lime-gradient transition-all duration-700" style="width:{{ $capai * 100 }}%"></div>
        </div>
        <div class="flex justify-between text-[10.5px] text-stone-400 mt-2">
          <span>Baseline <span class="num font-bold text-cam-ink">{{ number_format($baseline->baseline_gj_ton, 3) }}</span></span>
          <span>Target <span class="num font-bold text-cam-coral">{{ number_format($baseline->target_gj_ton, 3) }}</span>
                (turun {{ number_format($baseline->penurunanTarget(), 1) }}%)</span>
        </div>
      </div>
    @endif
  </x-energi.kepala>

  <div class="grid gap-4 lg:grid-cols-5">

    {{-- Formulir --}}
    <section class="kartu-lux rounded-2xl p-6 lg:col-span-2">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Tetapkan Baseline</h3>
      <p class="text-[11.5px] text-stone-500 mt-1">Satu baseline untuk tiap tahun; mengisi ulang tahun yang sama akan memperbaruinya.</p>

      <form method="POST" action="{{ route('energi.baseline.simpan') }}" class="space-y-3.5 mt-5">
        @csrf

        <div>
          <label class="block text-[10px] font-bold uppercase tracking-wide text-stone-500 mb-1">Perusahaan</label>
          <select name="company_id" class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition">
            <option value="">Seluruh perusahaan</option>
            @foreach($companies as $c)
              <option value="{{ $c->id }}" @selected(old('company_id') == $c->id)>{{ $c->name }}</option>
            @endforeach
          </select>
        </div>

        <div>
          <label class="block text-[10px] font-bold uppercase tracking-wide text-stone-500 mb-1">Tahun</label>
          <input type="number" name="tahun" value="{{ old('tahun', now()->year) }}" min="2000" max="2100" required
                 class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition">
          <x-input-error :messages="$errors->get('tahun')" class="mt-1.5" />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-wide text-stone-500 mb-1">Baseline (GJ/ton)</label>
            <input type="number" step="0.000001" name="baseline_gj_ton" value="{{ old('baseline_gj_ton') }}" required
                   class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition">
          </div>
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-wide text-stone-500 mb-1">Target (GJ/ton)</label>
            <input type="number" step="0.000001" name="target_gj_ton" value="{{ old('target_gj_ton') }}" required
                   class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition">
          </div>
        </div>
        <x-input-error :messages="$errors->get('baseline_gj_ton')" />
        <x-input-error :messages="$errors->get('target_gj_ton')" />

        <div>
          <label class="block text-[10px] font-bold uppercase tracking-wide text-stone-500 mb-1">Catatan</label>
          <textarea name="catatan" rows="3" placeholder="Dasar penetapan, misalnya rata-rata realisasi tahun sebelumnya."
                    class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition">{{ old('catatan') }}</textarea>
        </div>

        <button class="w-full rounded-xl bg-cam-ink text-white px-4 py-2.5 text-[12.5px] font-bold hover:brightness-110 transition">
          Simpan Baseline
        </button>

        <p class="text-[10.5px] text-stone-400 leading-relaxed">
          Target harus lebih rendah daripada baseline. Sasaran energi berarti turun, bukan naik.
        </p>
      </form>
    </section>

    {{-- Daftar --}}
    <section class="kartu-lux rounded-2xl p-6 lg:col-span-3">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Riwayat Baseline</h3>

      @if($daftar->count())
        <div class="space-y-3 mt-5">
          @foreach($daftar as $b)
            @php $ini = $baseline && $b->is($baseline); @endphp
            <div class="rounded-xl border p-4 {{ $ini ? 'border-cam-lime/40 bg-cam-lime-soft/40' : 'border-stone-100' }}">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <span class="text-[13px] font-bold text-cam-ink">{{ $b->tahun }}</span>
                  @if($ini)<span class="ml-2 text-[9.5px] font-bold uppercase tracking-wide text-white px-2 py-0.5 rounded-lg bg-cam-lime-deep">Berlaku</span>@endif
                  <div class="text-[11px] text-stone-400 mt-0.5">{{ $b->company?->name ?? 'Seluruh perusahaan' }}</div>
                </div>
                <div class="flex gap-5 text-right">
                  <div>
                    <div class="num text-[14px] font-bold text-cam-ink">{{ number_format($b->baseline_gj_ton, 3) }}</div>
                    <div class="text-[10px] text-stone-400">baseline</div>
                  </div>
                  <div>
                    <div class="num text-[14px] font-bold text-cam-coral">{{ number_format($b->target_gj_ton, 3) }}</div>
                    <div class="text-[10px] text-stone-400">target</div>
                  </div>
                  <div>
                    <div class="num text-[14px] font-bold text-cam-lime-deep">{{ number_format($b->penurunanTarget(), 1) }}%</div>
                    <div class="text-[10px] text-stone-400">penurunan</div>
                  </div>
                </div>
              </div>
              @if($b->catatan)
                <p class="text-[11.5px] text-stone-500 mt-2.5 leading-relaxed">{{ $b->catatan }}</p>
              @endif
            </div>
          @endforeach
        </div>
      @else
        <p class="text-[12px] text-stone-400 mt-6">Belum ada baseline yang ditetapkan.</p>
      @endif
    </section>
  </div>

</div>
@endsection

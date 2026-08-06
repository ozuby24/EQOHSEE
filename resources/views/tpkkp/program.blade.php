@extends('layouts.app')
@section('title','PTPKKP — Program Improvement')

@section('content')
@php $bisa = auth()->user()->isAdmin(); $rows = $a->programs ?? []; @endphp
<div class="max-w-5xl mx-auto space-y-5">
  @include('tpkkp._flash')
  @include('tpkkp._picker')

  <div class="bg-white rounded-2xl border border-stone-200 p-5">
    <h3 class="text-[13px] font-bold text-cam-ink mb-3">Parameter dengan selisih target terbesar</h3>
    @if(count($saran) === 0)
      <p class="text-[12.5px] text-stone-400">Belum ada nilai yang bisa dibandingkan dengan target.</p>
    @else
      <div class="space-y-1.5">
        @foreach($saran as $s)
          <div class="flex justify-between text-[12px] gap-3">
            <span class="text-stone-600"><b class="num">{{ $s['code'] }}</b> · {{ \Illuminate\Support\Str::limit($s['name'], 58) }}</span>
            <span class="num font-bold whitespace-nowrap {{ $s['gap'] < 0 ? 'text-red-600' : 'text-cam-lime-deep' }}">
              {{ $s['gap'] >= 0 ? '+' : '' }}{{ number_format($s['gap'], 3) }}
            </span>
          </div>
        @endforeach
      </div>
    @endif
  </div>

  @if($bisa)
    <form method="POST" action="{{ route('tpkkp.program.store', ['tahun' => $a->tahun]) }}"
          class="bg-white rounded-2xl border border-stone-200 p-5 space-y-3">
      @csrf
      <h3 class="text-[13px] font-bold text-cam-ink">Tambah program</h3>
      <div class="grid sm:grid-cols-4 gap-3">
        <input name="param" placeholder="Parameter (mis. 2.1)" required
               class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]">
        <input name="durasi" placeholder="Durasi"
               class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]">
        <input name="sasaran" placeholder="Sasaran"
               class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]">
        <input name="target" placeholder="Target"
               class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]">
      </div>
      <textarea name="opsi" rows="2" required placeholder="Opsi perbaikan"
                class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]"></textarea>
      <button class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2 text-[12.5px] font-bold hover:brightness-105 transition">Tambah</button>
    </form>
  @endif

  <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-stone-100">
      <h3 class="text-[13px] font-bold text-cam-ink">Daftar Program ({{ count($rows) }})</h3>
    </div>
    @if(count($rows) === 0)
      <p class="px-5 py-8 text-[12.5px] text-stone-400">Belum ada program.</p>
    @else
      <div class="divide-y divide-stone-100">
        @foreach($rows as $r)
          <div class="px-5 py-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="pr-3">
                <span class="num text-[11px] font-bold text-stone-400">Parameter {{ $r['param'] ?? '—' }}</span>
                <div class="text-[12.5px] text-cam-ink leading-snug mt-0.5">{{ $r['opsi'] ?? '' }}</div>
                <div class="text-[11px] text-stone-500 mt-1.5">
                  @if(!empty($r['durasi'])) durasi: {{ $r['durasi'] }} @endif
                  @if(!empty($r['sasaran'])) · sasaran: {{ $r['sasaran'] }} @endif
                  @if(!empty($r['target'])) · target: {{ $r['target'] }} @endif
                </div>
              </div>
              <div class="flex items-center gap-2">
                @if($bisa)
                  <form method="POST" action="{{ route('tpkkp.program.status', ['id' => $r['id'], 'tahun' => $a->tahun]) }}" class="flex items-center gap-1.5">
                    @csrf @method('PUT')
                    <select name="status" class="ring-focus rounded-lg border border-stone-200 px-2 py-1 text-[11.5px] font-semibold">
                      @foreach(['Rencana','Berjalan','Selesai','Ditunda'] as $s)
                        <option value="{{ $s }}" @selected(($r['status'] ?? '') === $s)>{{ $s }}</option>
                      @endforeach
                    </select>
                    <input type="number" name="progress" min="0" max="100" value="{{ $r['progress'] ?? 0 }}"
                           class="ring-focus w-16 rounded-lg border border-stone-200 px-2 py-1 text-[11.5px] num">
                    <button class="rounded-lg bg-cam-ink text-white px-2.5 py-1 text-[11.5px] font-bold">Simpan</button>
                  </form>
                  <form method="POST" action="{{ route('tpkkp.program.destroy', ['id' => $r['id'], 'tahun' => $a->tahun]) }}"
                        onsubmit="return confirm('Hapus program ini?')">
                    @csrf @method('DELETE')
                    <button class="rounded-lg border border-stone-200 px-2.5 py-1 text-[11.5px] text-stone-500">Hapus</button>
                  </form>
                @else
                  <span class="text-[11.5px] font-semibold text-stone-500">{{ $r['status'] ?? '—' }} · {{ $r['progress'] ?? 0 }}%</span>
                @endif
              </div>
            </div>
            <div class="h-1.5 rounded-full bg-stone-100 mt-3 overflow-hidden">
              <div class="h-full rounded-full bg-cam-lime" style="width: {{ (int) ($r['progress'] ?? 0) }}%"></div>
            </div>
          </div>
        @endforeach
      </div>
    @endif
  </div>
</div>
@endsection

@extends('layouts.app')
@section('title','PTPKKP — Metode')

@section('content')
<div class="max-w-5xl mx-auto space-y-5">
  @include('tpkkp._flash')
  @include('tpkkp._picker')

  <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-stone-100">
      <h3 class="text-[13px] font-bold text-cam-ink">Tujuh Metode Pengukuran</h3>
    </div>
    <div class="divide-y divide-stone-100">
      @foreach($metode as $m)
        @php $inf = $info[$m['key']]; @endphp
        <div class="px-5 py-4">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <span class="text-[12px] font-bold text-cam-ink">{{ $m['key'] }} · {{ $m['name'] }}</span>
              <div class="text-[11px] text-stone-500 mt-0.5">
                {{ $inf['entityLabel'] ?: 'Tanpa entitas' }} ·
                {{ count($inf['entities'] ?? []) }} entitas ·
                {{ $m['items'] }} item
              </div>
            </div>
            <div class="text-right">
              @include('tpkkp._badge', ['cat' => $m['category']])
              <div class="num text-[11px] text-stone-400 mt-1">{{ $m['filled'] }}/{{ $m['items'] }} terisi</div>
            </div>
          </div>
          @if(count($inf['entities'] ?? []))
            <div class="flex flex-wrap gap-1.5 mt-2.5">
              @foreach($inf['entities'] as $e)
                <span class="text-[10.5px] px-2 py-0.5 rounded-md bg-stone-50 border border-stone-200 text-stone-600">{{ $e }}</span>
              @endforeach
            </div>
          @endif
          <a href="{{ route('tpkkp.assess', ['m' => $m['key']]) }}"
             class="inline-block mt-3 text-[11.5px] font-bold text-cam-lime-deep hover:underline">Isi nilai metode ini →</a>
        </div>
      @endforeach
    </div>
  </div>
</div>
@endsection

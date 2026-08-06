@extends('layouts.app')
@section('title','PTPKKP — Jadwal')

@section('content')
@php $bisa = auth()->user()->isAdmin(); @endphp
<div class="max-w-6xl mx-auto space-y-5">
  @include('tpkkp._flash')
  @include('tpkkp._picker')

  <div class="bg-white rounded-2xl border border-stone-200 p-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h3 class="text-[13px] font-bold text-cam-ink">Jadwal Penilaian — 30 hari, 4 tahap</h3>
        <p class="text-[11.5px] text-stone-500 mt-0.5 num">{{ $selesai }} dari {{ $jumlah }} kegiatan selesai</p>
      </div>
      <div class="w-48">
        <div class="h-2 rounded-full bg-stone-100 overflow-hidden">
          <div class="h-full rounded-full bg-cam-lime" style="width: {{ $jumlah ? round($selesai / $jumlah * 100) : 0 }}%"></div>
        </div>
      </div>
    </div>
  </div>

  <form method="POST" action="{{ route('tpkkp.jadwal.save', ['tahun' => $a->tahun]) }}">
    @csrf
    @foreach($tahap as $namaTahap => $rows)
      <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden mb-5">
        <div class="px-5 py-3 bg-cam-ink text-white">
          <h3 class="text-[12.5px] font-bold">{{ $namaTahap }}</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-[12px] min-w-[720px]">
            <thead class="text-[10px] uppercase tracking-wider text-stone-400 bg-stone-50">
              <tr>
                <th class="text-left px-4 py-2 font-bold" style="width:34px"></th>
                <th class="text-left px-3 py-2 font-bold">Kegiatan</th>
                <th class="text-left px-3 py-2 font-bold">Keluaran</th>
                <th class="text-left px-4 py-2 font-bold" style="width:230px">Hari 1 – 30</th>
              </tr>
            </thead>
            <tbody>
              @foreach($rows as $r)
                @if(!empty($r['header']))
                  @continue
                @endif
                @php
                  $s = max(1, (int) ($r['start'] ?? 1));
                  $e = min(30, (int) ($r['end'] ?? 30));
                  $kiri = ($s - 1) / 30 * 100;
                  $lebar = max(3, ($e - $s + 1) / 30 * 100);
                @endphp
                <tr class="border-b border-stone-50 {{ !empty($r['done']) ? 'bg-cam-lime-soft/40' : '' }}">
                  <td class="px-4 py-2">
                    <input type="checkbox" name="done[]" value="{{ $r['idx'] }}" @checked(!empty($r['done'])) @disabled(!$bisa)
                           class="w-4 h-4 accent-cam-lime-deep">
                  </td>
                  <td class="px-3 py-2 {{ !empty($r['done']) ? 'text-stone-400 line-through' : 'text-stone-700' }}">{{ $r['kegiatan'] }}</td>
                  <td class="px-3 py-2 text-stone-500">{{ $r['output'] ?? '' }}</td>
                  <td class="px-4 py-2">
                    <div class="relative h-2.5 rounded-full bg-stone-100">
                      <div class="absolute h-2.5 rounded-full {{ !empty($r['done']) ? 'bg-cam-lime' : 'bg-cam-ink/45' }}"
                           style="left: {{ $kiri }}%; width: {{ $lebar }}%"></div>
                    </div>
                    <div class="text-[10px] text-stone-400 mt-1 num">hari {{ $s }}–{{ $e }}</div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endforeach

    @if($bisa)
      <button class="lime-gradient shadow-glow rounded-xl text-white px-6 py-2.5 text-[13px] font-bold hover:brightness-105 transition">
        Simpan tanda selesai
      </button>
    @endif
  </form>
</div>
@endsection

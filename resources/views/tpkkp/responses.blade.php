@extends('layouts.app')
@section('title','Kuesioner PTPKKP')

@section('content')
<div class="max-w-4xl mx-auto space-y-5">
  @if(session('ok'))
    <div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>
  @endif

  <form action="{{ route('tpkkp.responses.store') }}" method="POST"
        class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 grid sm:grid-cols-5 gap-2">
    @csrf
    <div class="sm:col-span-5 text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">Tambah Responden</div>
    <input name="nrp" placeholder="NRP" class="ring-focus rounded-lg border border-stone-200 px-3 py-2 text-[12.5px]">
    <input name="jabatan" placeholder="Jabatan" class="ring-focus rounded-lg border border-stone-200 px-3 py-2 text-[12.5px]">
    <input name="dept" placeholder="Departemen" class="ring-focus rounded-lg border border-stone-200 px-3 py-2 text-[12.5px]">
    <input name="cat" placeholder="Kategori" class="ring-focus rounded-lg border border-stone-200 px-3 py-2 text-[12.5px]">
    <button class="lime-gradient rounded-lg text-white px-3 py-2 text-[12px] font-bold hover:brightness-105">+ Tambah</button>
  </form>

  <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-[12.5px]">
        <thead>
          <tr class="bg-stone-50 border-b border-stone-100">
            @foreach (['NRP','Jabatan','Departemen','Kategori','Waktu',''] as $h)
              <th class="text-left font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-3">{{ $h }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @forelse($responses as $r)
            <tr class="border-b border-stone-50 last:border-0 hover:bg-stone-50/60">
              <td class="px-4 py-3 font-semibold text-cam-ink">{{ $r->nrp ?: '—' }}</td>
              <td class="px-4 py-3 text-stone-600">{{ $r->jabatan ?: '—' }}</td>
              <td class="px-4 py-3 text-stone-600">{{ $r->dept ?: '—' }}</td>
              <td class="px-4 py-3">
                @if($r->cat)<span class="text-[10px] font-bold bg-cam-lime-soft text-cam-lime-deep px-2 py-0.5 rounded-full">{{ $r->cat }}</span>@else — @endif
              </td>
              <td class="px-4 py-3 text-[11px] text-stone-400">{{ optional($r->ts)->format('d M Y · H:i') }}</td>
              <td class="px-4 py-3 text-right">
                <form action="{{ route('tpkkp.responses.destroy',$r) }}" method="POST" onsubmit="return confirm('Hapus responden?')">
                  @csrf @method('DELETE')
                  <button class="text-[11.5px] font-semibold text-red-500 hover:bg-red-50 px-2.5 py-1 rounded-lg">Hapus</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="px-4 py-12 text-center text-[13px] text-stone-400">Belum ada responden.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div>{{ $responses->links() }}</div>
</div>
@endsection

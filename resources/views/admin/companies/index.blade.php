@extends('layouts.app')
@section('title','Kelola Perusahaan')

@section('content')
<div class="max-w-6xl mx-auto">
  @if(session('ok'))<div class="mb-5 rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>@endif
  @if($errors->any())<div class="mb-5 rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]">@foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach</div>@endif

  <div class="flex flex-wrap items-center gap-2.5 mb-5">
    <form method="GET" class="flex-1 min-w-0 basis-[200px]">
      <input name="q" value="{{ $q }}" placeholder="Cari nama atau kode perusahaan..."
             class="ring-focus w-full rounded-xl border border-stone-200 bg-white px-4 py-2.5 text-[13px]">
    </form>
    <a href="{{ route('admin.companies.create') }}" class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">+ Tambah Perusahaan</a>
  </div>

  <div class="grid gap-3 sm:grid-cols-2">
    @forelse($companies as $c)
      <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 card-hover">
        <div class="flex items-start gap-3">
          <div class="w-11 h-11 rounded-xl {{ $c->logo ? '' : 'lime-gradient' }} grid place-items-center shrink-0 overflow-hidden">
            @if($c->logo)<img src="{{ asset('storage/'.$c->logo) }}" class="w-full h-full object-cover">
            @else<span class="font-black text-white text-[15px]">{{ strtoupper(substr($c->code ?: $c->name,0,2)) }}</span>@endif
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2 flex-wrap">
              @if($c->code)<span class="text-[9.5px] font-bold bg-cam-ink text-white px-1.5 py-0.5 rounded tracking-wide">{{ $c->code }}</span>@endif
              <span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded {{ $c->risk_class === 'Tinggi' ? 'bg-red-100 text-red-700' : ($c->risk_class === 'Sedang' ? 'bg-amber-100 text-amber-700' : 'bg-cam-lime-soft text-cam-lime-deep') }}">Risiko {{ $c->risk_class }}</span>
            </div>
            <h3 class="text-[14px] font-bold text-cam-ink mt-1.5">{{ $c->name }}</h3>
            <div class="text-[11.5px] text-stone-400 mt-0.5">{{ $c->izin_type ?: '—' }} · {{ $c->commodity ?: '—' }}</div>
            <div class="text-[11.5px] text-stone-400">{{ $c->location ?: '—' }}</div>
          </div>
        </div>

        <div class="grid grid-cols-3 gap-2 mt-4 pt-3 border-t border-stone-100 text-center">
          @foreach ([['KTT',$c->ktt ?: '—'],['PJO',$c->pjo ?: '—'],['Pekerja',$c->totalWorkers()]] as [$l,$v])
            <div><div class="text-[9.5px] uppercase tracking-wide text-stone-400 font-bold">{{ $l }}</div>
                 <div class="text-[12px] font-semibold text-stone-600 truncate">{{ $v }}</div></div>
          @endforeach
        </div>

        <div class="flex items-center justify-between mt-3 pt-3 border-t border-stone-100">
          <span class="text-[11px] text-stone-400">{{ $c->users_count }} pengguna</span>
          <div class="flex gap-1">
            <a href="{{ route('tpkkp.index', ['company'=>$c->id]) }}" class="px-2.5 py-1.5 text-[11.5px] font-semibold rounded-lg text-stone-500 hover:bg-stone-50">PTPKKP</a>
            <a href="{{ route('admin.companies.edit',$c) }}" class="px-2.5 py-1.5 text-[11.5px] font-semibold rounded-lg text-cam-lime-deep hover:bg-cam-lime-soft">Edit</a>
            <form action="{{ route('admin.companies.destroy',$c) }}" method="POST" onsubmit="return confirm('Hapus perusahaan ini? Data penilaian & kuesionernya ikut terhapus.')">
              @csrf @method('DELETE')
              <button class="px-2.5 py-1.5 text-[11.5px] font-semibold rounded-lg text-red-500 hover:bg-red-50">Hapus</button>
            </form>
          </div>
        </div>
      </div>
    @empty
      <div class="sm:col-span-2 bg-white rounded-2xl border border-dashed border-stone-200 p-14 text-center text-[13px] text-stone-400">Belum ada perusahaan.</div>
    @endforelse
  </div>

  <div class="mt-5">{{ $companies->links() }}</div>
</div>
@endsection

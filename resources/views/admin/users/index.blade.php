@extends('layouts.app')
@section('title','Kelola Pengguna')

@section('content')
<div class="max-w-6xl mx-auto">
  @if(session('ok'))
    <div class="mb-5 rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>
  @endif
  @if($errors->any())
    <div class="mb-5 rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]">
      @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
    </div>
  @endif

  <div class="flex flex-wrap items-center gap-2.5 mb-5">
    <form method="GET" class="flex-1 min-w-0 basis-[200px]">
      <input name="q" value="{{ $q }}" placeholder="Cari nama atau email..."
             class="ring-focus w-full rounded-xl border border-stone-200 bg-white px-4 py-2.5 text-[13px] transition">
    </form>
    <a href="{{ route('admin.users.create') }}"
       class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">+ Tambah Pengguna</a>
  </div>

  <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-[12.5px]">
        <thead>
          <tr class="bg-stone-50 border-b border-stone-100">
            <th class="text-left font-bold text-stone-500 uppercase tracking-wide text-[10px] px-5 py-3">Pengguna</th>
            <th class="text-left font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-3">Peran</th>
            <th class="text-left font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-3 hidden md:table-cell">Jabatan</th>
            <th class="text-left font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-3">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody>
          @forelse($users as $u)
            <tr class="border-b border-stone-50 last:border-0 hover:bg-stone-50/60 transition">
              <td class="px-5 py-3.5">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-xl {{ $u->is_admin ? 'lime-gradient text-white' : 'bg-stone-100 text-stone-500' }} grid place-items-center font-bold text-[12px] shrink-0">
                    {{ strtoupper(substr($u->name,0,1)) }}
                  </div>
                  <div class="min-w-0">
                    <div class="font-bold text-cam-ink clamp-1">{{ $u->name }}</div>
                    <div class="text-[11px] text-stone-400 clamp-1">{{ $u->email }}</div>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3.5">
                <div class="flex flex-wrap gap-1">
                  @if($u->is_admin)
                    <span class="text-[9.5px] font-bold bg-cam-ink text-white px-2 py-0.5 rounded tracking-wide">ADMIN</span>
                  @endif
                  @if($u->lms_role)
                    <span class="text-[9.5px] font-bold bg-cam-lime-soft text-cam-lime-deep px-2 py-0.5 rounded tracking-wide uppercase">{{ $u->lms_role }}</span>
                  @endif
                  @if($u->audit_role)
                    <span class="text-[9.5px] font-bold bg-stone-100 text-stone-500 px-2 py-0.5 rounded tracking-wide uppercase">{{ $u->audit_role }}</span>
                  @endif
                  @if(!$u->is_admin && !$u->lms_role && !$u->audit_role)
                    <span class="text-[11px] text-stone-300">—</span>
                  @endif
                </div>
              </td>
              <td class="px-4 py-3.5 hidden md:table-cell">
                <div class="text-stone-600">{{ $u->position ?: '—' }}</div>
                @if($u->department)<div class="text-[11px] text-stone-400">{{ $u->department }}</div>@endif
              </td>
              <td class="px-4 py-3.5">
                @if($u->active)
                  <span class="inline-flex items-center gap-1.5 text-[11.5px] font-semibold text-cam-lime-deep">
                    <span class="w-1.5 h-1.5 rounded-full bg-cam-lime"></span> Aktif
                  </span>
                @else
                  <span class="inline-flex items-center gap-1.5 text-[11.5px] font-semibold text-stone-400">
                    <span class="w-1.5 h-1.5 rounded-full bg-stone-300"></span> Nonaktif
                  </span>
                @endif
              </td>
              <td class="px-4 py-3.5">
                <div class="flex items-center justify-end gap-1">
                  <a href="{{ route('admin.users.edit', $u) }}" class="px-3 py-1.5 text-[11.5px] font-semibold rounded-lg text-cam-lime-deep hover:bg-cam-lime-soft">Edit</a>
                  @if($u->id !== auth()->id())
                    <form action="{{ route('admin.users.destroy', $u) }}" method="POST" onsubmit="return confirm('Hapus pengguna ini?')">
                      @csrf @method('DELETE')
                      <button class="px-3 py-1.5 text-[11.5px] font-semibold rounded-lg text-red-500 hover:bg-red-50">Hapus</button>
                    </form>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="px-5 py-12 text-center text-[13px] text-stone-400">Tidak ada pengguna.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-5">{{ $users->links() }}</div>
</div>
@endsection

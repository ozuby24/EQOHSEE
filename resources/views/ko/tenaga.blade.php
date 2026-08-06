@extends('layouts.app')
@section('title','KO/SPIP — Tenaga Teknis')

@section('content')
<div class="max-w-5xl mx-auto space-y-5">
  @include('ko._flash')
  @include('ko._picker')

  @php $aktif = $tenaga->filter(fn($t) => $t->aktif)->count(); @endphp
  <div class="bg-white rounded-2xl border border-stone-200 p-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h3 class="text-[13px] font-bold text-cam-ink">Kompetensi Tenaga Teknik — sub-elemen 4</h3>
        <p class="text-[11.5px] text-stone-500 mt-0.5 num">{{ $aktif }} dari {{ $tenaga->count() }} sertifikat masih berlaku</p>
      </div>
      @php $pct = $tenaga->count() ? (int) round($aktif / $tenaga->count() * 100) : 100; @endphp
      <div class="stat text-[24px] leading-none" style="color: {{ \App\Support\Ko::warnaPersen($pct) }}">{{ $pct }}%</div>
    </div>
  </div>

  @if($bolehUbah)
    <form method="POST" action="{{ route('ko.tenaga.simpan') }}" class="bg-white rounded-2xl border border-stone-200 p-5 space-y-3">
      @csrf
      <h3 class="text-[13px] font-bold text-cam-ink">Tambah tenaga teknis</h3>
      <div class="grid sm:grid-cols-3 gap-2">
        <input name="nama" required placeholder="Nama lengkap" class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]">
        <input name="jabatan" placeholder="Jabatan" class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]">
        <select name="company_id" class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]">
          <option value="">— perusahaan —</option>
          @foreach($perusahaan as $co)<option value="{{ $co->id }}">{{ $co->name }}</option>@endforeach
        </select>
        <input name="sertifikasi" placeholder="Sertifikasi" class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]">
        <input name="no_sertifikat" placeholder="No. sertifikat" class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]">
        <input type="date" name="tgl_kadaluarsa" class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]">
        <select name="user_id" class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] sm:col-span-3">
          <option value="">— tautkan ke akun EQOHSEE (opsional) —</option>
          @foreach($user as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
        </select>
      </div>
      <button class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2 text-[12.5px] font-bold hover:brightness-105 transition">Simpan</button>
    </form>
  @endif

  <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-[12px] min-w-[720px]">
        <thead class="text-[10px] uppercase tracking-wider text-stone-400 bg-stone-50">
          <tr>
            <th class="text-left px-5 py-2.5 font-bold">Nama</th>
            <th class="text-left px-3 py-2.5 font-bold">Jabatan</th>
            <th class="text-left px-3 py-2.5 font-bold">Perusahaan</th>
            <th class="text-left px-3 py-2.5 font-bold">Sertifikasi</th>
            <th class="text-right px-3 py-2.5 font-bold">Berlaku s/d</th>
            <th class="text-left px-5 py-2.5 font-bold">Status</th>
          </tr>
        </thead>
        <tbody>
          @forelse($tenaga as $t)
            <tr class="border-b border-stone-50">
              <td class="px-5 py-2.5 font-semibold text-cam-ink">{{ $t->nama }}</td>
              <td class="px-3 py-2.5 text-stone-600">{{ $t->jabatan ?: '—' }}</td>
              <td class="px-3 py-2.5 text-stone-500">{{ $t->company?->code ?: '—' }}</td>
              <td class="px-3 py-2.5 text-stone-600">
                {{ $t->sertifikasi ?: '—' }}
                @if($t->no_sertifikat)<span class="text-stone-400 num"> · {{ $t->no_sertifikat }}</span>@endif
              </td>
              <td class="px-3 py-2.5 text-right num text-stone-500 whitespace-nowrap">{{ $t->tgl_kadaluarsa?->format('d M Y') ?: '—' }}</td>
              <td class="px-5 py-2.5">
                @php $s = $t->sisa_hari; @endphp
                <span class="text-[10.5px] font-bold px-2 py-0.5 rounded-full
                      {{ $s === null ? 'bg-stone-100 text-stone-500' : ($s < 0 ? 'bg-red-50 text-red-700' : ($s <= 90 ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700')) }}">
                  {{ $s === null ? 'Tanpa tanggal' : ($s < 0 ? 'Kadaluarsa' : ($s <= 90 ? $s.' hari lagi' : 'Berlaku')) }}
                </span>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="px-5 py-10 text-center text-[12.5px] text-stone-400">Belum ada tenaga teknis terdaftar.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

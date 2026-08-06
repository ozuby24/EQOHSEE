@extends('layouts.app')
@section('title','Kuesioner PTPKKP')

@section('content')
@php $url = route('kuesioner.pilih', $token); @endphp
<div class="max-w-5xl mx-auto space-y-5">
  @if(session('ok'))<div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>@endif
  @include('tpkkp._picker')

  {{-- Tautan sebar --}}
  <div class="brand-gradient rounded-2xl p-6 text-white shadow-card relative overflow-hidden">
    <div class="absolute -right-20 -top-20 w-56 h-56 rounded-full bg-cam-lime/20 blur-3xl"></div>
    <div class="relative">
      <span class="text-[10px] font-bold uppercase tracking-[0.22em] text-cam-lime-light">Tautan Publik — Bebas Akses</span>
      <h2 class="stat stat-sm mt-1.5">Sebarkan ke pekerja &amp; pimpinan</h2>
      <p class="text-[12px] text-white/50 mt-1.5">Penerima tidak perlu akun. Cukup buka tautan, pilih kategori, lalu isi.</p>

      <div class="glass rounded-xl px-4 py-3 mt-4 flex flex-wrap items-center gap-2">
        <code id="qurl" class="text-[12px] font-mono text-cam-lime-light break-all flex-1 min-w-[200px]">{{ $url }}</code>
        <button type="button" onclick="salin()" class="rounded-lg bg-white/15 hover:bg-white/25 px-3 py-1.5 text-[11.5px] font-bold transition">Salin</button>
        <a href="{{ $url }}" target="_blank" rel="noopener" class="rounded-lg bg-white/15 hover:bg-white/25 px-3 py-1.5 text-[11.5px] font-bold transition">Buka</a>
      </div>

      <div class="flex flex-wrap gap-2 mt-3">
        @foreach (\App\Http\Controllers\KuesionerController::KATEGORI as $key => $k)
          <a href="{{ route('kuesioner.form', [$token,$key]) }}" target="_blank" rel="noopener"
             class="glass rounded-lg px-3 py-1.5 text-[11.5px] font-bold hover:bg-white/15 transition">Tautan langsung: {{ $k['label'] }}</a>
        @endforeach
        @if(auth()->user()->isAdmin())
          <form action="{{ route('kuesioner.tarik') }}" method="POST"
                onsubmit="return confirm('Tarik seluruh jawaban kuesioner menjadi skor metode KS? Nilai KS yang ada akan ditimpa.')">
            @csrf
            <button class="rounded-lg bg-white/15 hover:bg-white/25 px-3 py-1.5 text-[11.5px] font-bold transition">↧ Tarik ke skor KS</button>
          </form>
        @endif
        <form action="{{ route('kuesioner.token.reset') }}" method="POST" onsubmit="return confirm('Buat tautan baru? Tautan lama langsung tidak berlaku.')">
          @csrf <input type="hidden" name="company_id" value="{{ $company->id }}">
          <button class="rounded-lg bg-white/10 hover:bg-white/20 px-3 py-1.5 text-[11.5px] font-bold transition">Ganti tautan</button>
        </form>
      </div>
    </div>
  </div>

  {{-- Ringkasan per kategori --}}
  <div class="grid gap-4 sm:grid-cols-2">
    @foreach($ringkas as $key => $r)
      <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
        <div class="flex items-center justify-between mb-3">
          <div>
            <div class="text-[13.5px] font-bold text-cam-ink">{{ $r['label'] }}</div>
            <div class="text-[11px] text-stone-400 mt-0.5">{{ $r['jumlah'] }} responden</div>
          </div>
          <div class="text-right">
            <div class="stat text-cam-lime-deep leading-none">{{ $r['rerata'] ?? '—' }}</div>
            <div class="text-[9.5px] uppercase tracking-wide text-stone-400 mt-1">rerata 1–5</div>
          </div>
        </div>
        <div class="space-y-2 pt-3 border-t border-stone-100">
          @foreach($r['params'] as $p)
            <div>
              <div class="flex items-center justify-between text-[11.5px] mb-1">
                <span class="text-stone-600 truncate pr-2">{{ $p['code'] }} {{ \Illuminate\Support\Str::limit($p['name'], 40) }}</span>
                <span class="shrink-0 font-bold text-stone-500 num">{{ $p['rerata'] ?? '—' }}</span>
              </div>
              <div class="h-1.5 rounded-full bg-stone-100 overflow-hidden">
                <div class="h-full rounded-full lime-gradient" style="width: {{ $p['pct'] ?? 0 }}%"></div>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    @endforeach
  </div>

  <p class="text-[11.5px] text-stone-400 leading-relaxed">
    Hasil kuesioner adalah <b>data pendukung</b> (Metode B) untuk mengisi Formulir Nilai — bukan nilai otomatis,
    sesuai prosedur penilaian PTPKKP.
  </p>

  {{-- Daftar responden --}}
  <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
    <div class="px-5 py-4 border-b border-stone-100"><h3 class="text-[14px] font-bold text-cam-ink">Responden ({{ $responses->count() }})</h3></div>
    <div class="overflow-x-auto">
      <table class="w-full text-[12.5px]">
        <thead><tr class="bg-stone-50 border-b border-stone-100">
          @foreach (['Kategori','NRP','Jabatan','Departemen','Jawaban','Waktu',''] as $h)
            <th class="text-left font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-2.5">{{ $h }}</th>
          @endforeach
        </tr></thead>
        <tbody>
          @forelse($responses as $r)
            <tr class="border-b border-stone-50 last:border-0 hover:bg-stone-50/60">
              <td class="px-4 py-2.5">
                <span class="text-[10px] font-bold bg-cam-lime-soft text-cam-lime-deep px-2 py-0.5 rounded-full">
                  {{ \App\Http\Controllers\KuesionerController::KATEGORI[$r->cat]['label'] ?? $r->cat }}
                </span>
              </td>
              <td class="px-4 py-2.5 font-semibold text-cam-ink">{{ $r->nrp ?: '—' }}</td>
              <td class="px-4 py-2.5 text-stone-500">{{ $r->jabatan ?: '—' }}</td>
              <td class="px-4 py-2.5 text-stone-500">{{ $r->dept ?: '—' }}</td>
              <td class="px-4 py-2.5 text-stone-500 num">{{ count((array) $r->answers) }}</td>
              <td class="px-4 py-2.5 text-[11px] text-stone-400">{{ optional($r->ts)->format('d M · H:i') }}</td>
              <td class="px-4 py-2.5 text-right">
                <form action="{{ route('kuesioner.response.destroy',$r) }}" method="POST" onsubmit="return confirm('Hapus responden?')">
                  @csrf @method('DELETE')
                  <button class="text-[11.5px] font-semibold text-red-500 hover:bg-red-50 px-2.5 py-1 rounded-lg">Hapus</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="px-4 py-12 text-center text-stone-400">Belum ada yang mengisi kuesioner.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

@push('scripts')
<script>
function salin(){
  const t = document.getElementById('qurl').textContent.trim();
  navigator.clipboard?.writeText(t).then(()=>alert('Tautan disalin:\n'+t)).catch(()=>prompt('Salin tautan:', t));
}
</script>
@endpush
@endsection

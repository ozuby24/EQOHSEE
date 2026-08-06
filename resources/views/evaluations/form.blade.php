@extends('layouts.app')
@section('title', $evaluation->exists ? 'Edit Evaluasi' : 'Nilai Peserta')

@section('content')
<div class="max-w-2xl mx-auto">
  <form action="{{ $evaluation->exists ? route('evaluations.update',$evaluation) : route('evaluations.store') }}" method="POST"
        class="bg-white rounded-2xl shadow-card border border-stone-100 p-6 space-y-6">
    @csrf
    @if($evaluation->exists) @method('PUT') @endif

    @if($errors->any())
      <div class="rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]">
        <ul class="space-y-0.5">@foreach($errors->all() as $e)<li>• {{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    {{-- Sasaran --}}
    <div class="space-y-4">
      <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">Peserta &amp; Pelatihan</p>
      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Peserta</label>
          <select name="user_id" id="userSel" required onchange="saringKursus()" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
            <option value="">— pilih peserta —</option>
            @foreach($peserta as $p)
              <option value="{{ $p->id }}" @selected(old('user_id',$evaluation->user_id)==$p->id)>{{ $p->name }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Kursus</label>
          <select name="course_id" id="courseSel" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
            <option value="">— tanpa kursus —</option>
            @foreach($courses as $c)
              <option value="{{ $c->id }}" @selected(old('course_id',$evaluation->course_id)==$c->id)>{{ $c->title }}</option>
            @endforeach
          </select>
          <p class="text-[10.5px] text-stone-400 mt-1">Otomatis menyaring kursus yang diambil peserta.</p>
        </div>
      </div>
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Nama trainer</label>
        <input name="trainer_name" value="{{ old('trainer_name', $evaluation->trainer_name ?: auth()->user()->name) }}"
               class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
      </div>
    </div>

    {{-- Penilaian --}}
    <div class="space-y-4 pt-1">
      <div class="flex items-baseline justify-between">
        <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">Penilaian (0–100)</p>
        <p class="text-[11px] text-stone-400">Nilai akhir = rata-rata otomatis</p>
      </div>
      <div class="grid sm:grid-cols-2 gap-4">
        @foreach ([
          ['knowledge_score','Pengetahuan','Pemahaman materi & teori'],
          ['skill_score','Keterampilan','Penerapan praktik di lapangan'],
          ['attitude_score','Sikap','Disiplin, kerja sama, inisiatif'],
          ['safety_score','Keselamatan','Kepatuhan prosedur & APD'],
        ] as [$f,$l,$hint])
          <div>
            <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">{{ $l }}</label>
            <input type="number" name="{{ $f }}" min="0" max="100" required
                   value="{{ old($f, $evaluation->$f ?? 80) }}"
                   class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
            <p class="text-[10.5px] text-stone-400 mt-1">{{ $hint }}</p>
          </div>
        @endforeach
      </div>
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Rekomendasi</label>
        <select name="recommendation" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
          <option value="">— pilih —</option>
          @foreach($rekomendasi as $r)
            <option value="{{ $r }}" @selected(old('recommendation',$evaluation->recommendation)===$r)>{{ $r }}</option>
          @endforeach
        </select>
      </div>
    </div>

    {{-- Catatan --}}
    <div class="space-y-4 pt-1">
      <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">Catatan Trainer</p>
      @foreach ([['strengths','Kekuatan','Hal yang sudah baik...'],['improvements','Perlu ditingkatkan','Area yang perlu diperbaiki...'],['notes','Catatan tambahan','Catatan lain...']] as [$f,$l,$ph])
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">{{ $l }}</label>
          <textarea name="{{ $f }}" rows="3" placeholder="{{ $ph }}"
                    class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] leading-relaxed transition">{{ old($f,$evaluation->$f) }}</textarea>
        </div>
      @endforeach
    </div>

    <div class="flex items-center gap-3 pt-1">
      <button class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">Simpan Evaluasi</button>
      <a href="{{ route('evaluations.index') }}" class="text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink">Batal</a>
    </div>
  </form>
</div>
@push('scripts')
<script>
  const PETA = @json($kursusPeserta ?? []);
  const SEMUA = Array.from(document.getElementById('courseSel').options).map(o => ({v:o.value, t:o.text}));
  function saringKursus(){
    const uid = document.getElementById('userSel').value;
    const sel = document.getElementById('courseSel');
    const kini = sel.value;
    const daftar = PETA[uid] || null;
    sel.innerHTML = '<option value="">— tanpa kursus —</option>';
    (daftar ? daftar.map(c => ({v:String(c.id), t:c.title})) : SEMUA.filter(o => o.v))
      .forEach(o => { const el=document.createElement('option'); el.value=o.v; el.text=o.t; sel.appendChild(el); });
    sel.value = kini;
  }
  document.addEventListener('DOMContentLoaded', saringKursus);
</script>
@endpush
@endsection

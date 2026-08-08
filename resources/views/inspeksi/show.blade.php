@extends('layouts.app')
@section('title', $i->kode)

@section('content')
@php use App\Support\Hazard; $r = $i->ringkas(); @endphp
<div class="max-w-4xl mx-auto space-y-4">
  @if(session('ok'))<div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>@endif

  {{-- Kepala --}}
  <div class="brand-gradient rounded-2xl p-6 text-white shadow-card relative overflow-hidden">
    <div class="absolute -right-20 -top-20 w-56 h-56 rounded-full bg-cam-lime/20 blur-3xl"></div>
    <div class="relative flex flex-wrap items-start justify-between gap-5">
      <div class="min-w-0">
        <div class="flex items-center gap-2 flex-wrap">
          <span class="text-[11px] font-bold glass px-2.5 py-1 rounded num">{{ $i->kode }}</span>
          @if($i->template)<span class="text-[11px] font-bold glass px-2.5 py-1 rounded">{{ $i->template->nama }}</span>@endif
          <span class="text-[11px] font-bold px-2.5 py-1 rounded-full {{ $i->status === 'Selesai' ? 'bg-cam-lime text-white' : 'bg-amber-500 text-white' }}">{{ $i->status }}</span>
        </div>
        <h2 class="font-display text-[22px] font-black mt-3 leading-tight">{{ $i->judul }}</h2>
        <p class="text-[12px] text-white/50 mt-1.5">
          📍 {{ $i->lokasi ?: '—' }} · {{ optional($i->tanggal)->format('d M Y') }} · {{ $i->company?->name ?: '—' }}
        </p>
      </div>
      <div class="glass rounded-2xl px-6 py-4 text-center">
        <div class="stat text-cam-lime-light">{{ $r['total'] ? round(($r['total']-$r['belum'])/$r['total']*100) : 0 }}<span class="stat-unit">%</span></div>
        <div class="text-[9.5px] uppercase tracking-wide text-white/40 mt-1.5">terisi</div>
      </div>
    </div>
  </div>

  {{-- Ringkasan --}}
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
    @foreach ([['Sesuai',$r['sesuai'],'text-cam-lime-deep'],['Tidak sesuai',$r['tidak'],'text-red-500'],['N/A',$r['na'],'text-stone-400'],['Belum diisi',$r['belum'],'text-amber-500']] as [$l,$v,$c])
      <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-4">
        <div class="stat stat-sm {{ $c }}">{{ $v }}</div>
        <div class="text-[11px] text-stone-400 mt-1.5">{{ $l }}</div>
      </div>
    @endforeach
  </div>

  {{-- Inspektur --}}
  <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
    <h3 class="text-[13px] font-bold text-cam-ink mb-3">Tim Inspektur</h3>
    <div class="flex flex-wrap gap-2 mb-3.5">
      @forelse($i->inspectors as $p)
        <div class="flex items-center gap-2.5 bg-stone-50 rounded-xl pl-3 pr-2 py-2">
          <div class="w-8 h-8 rounded-lg lime-gradient text-white grid place-items-center font-bold text-[12px]">{{ strtoupper(substr($p->nama,0,1)) }}</div>
          <div class="min-w-0">
            <div class="text-[12.5px] font-bold text-cam-ink">{{ $p->nama }}</div>
            <div class="text-[10.5px] text-stone-400">{{ $p->jabatan ?: '—' }} · {{ $p->peran }}</div>
          </div>
          <form action="{{ route('inspeksi.inspector.destroy', $p) }}" method="POST">
            @csrf @method('DELETE')
            <button class="text-[12px] text-stone-300 hover:text-red-500 px-1">✕</button>
          </form>
        </div>
      @empty
        <p class="text-[12.5px] text-stone-400">Belum ada inspektur.</p>
      @endforelse
    </div>

    <form action="{{ route('inspeksi.inspector.store', $i) }}" method="POST" class="grid sm:grid-cols-5 gap-2 pt-3.5 border-t border-stone-100">
      @csrf
      <select name="user_id" class="ring-focus sm:col-span-2 rounded-lg border border-stone-200 px-3 py-2 text-[12.5px]">
        <option value="">— pilih pengguna —</option>
        @foreach($kandidat as $u)<option value="{{ $u->id }}">{{ $u->name }}{{ $u->position ? ' · '.$u->position : '' }}</option>@endforeach
      </select>
      <input name="nama" placeholder="atau ketik nama" class="ring-focus rounded-lg border border-stone-200 px-3 py-2 text-[12.5px]">
      <input name="jabatan" placeholder="Jabatan" class="ring-focus rounded-lg border border-stone-200 px-3 py-2 text-[12.5px]">
      <div class="flex gap-2">
        <select name="peran" class="ring-focus flex-1 rounded-lg border border-stone-200 px-2 py-2 text-[12.5px]">
          <option value="Anggota">Anggota</option><option value="Ketua">Ketua</option>
        </select>
        <button class="lime-gradient rounded-lg text-white px-3 py-2 text-[12px] font-bold hover:brightness-105">+</button>
      </div>
    </form>
    <p class="text-[11px] text-stone-400 mt-2">Jabatan menentukan target KPI inspeksi tiap inspektur.</p>
  </div>

  {{-- Daftar periksa --}}
  <form action="{{ route('inspeksi.items.save', $i) }}" method="POST" class="space-y-3">
    @csrf
    @php $grup = $i->items->groupBy(fn($x) => $x->kelompok ?: 'Umum'); @endphp

    @forelse($grup as $namaGrup => $items)
      <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
        <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-cam-lime-deep mb-3">{{ $namaGrup }}</p>
        <div class="space-y-3">
          @foreach($items as $it)
            <div class="rounded-xl border border-stone-200 p-4">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <div class="text-[13px] font-semibold text-stone-700">{{ $it->order_index }}. {{ $it->uraian }}</div>
                  @if($it->acuan)<div class="text-[10.5px] text-stone-400 mt-0.5">Acuan: {{ $it->acuan }}</div>@endif
                </div>
                @if($it->hazard_report_id)
                  <a href="{{ route('hazard.show', $it->hazard_report_id) }}" class="shrink-0 text-[10px] font-bold bg-red-100 text-red-700 px-2 py-1 rounded">→ Hazard Report</a>
                @endif
              </div>

              <div class="grid sm:grid-cols-[auto_auto_1fr] gap-2.5 mt-3">
                <div class="flex gap-1">
                  @foreach(Hazard::KONDISI as $k)
                    <label class="cursor-pointer">
                      <input type="radio" name="item[{{ $it->id }}][kondisi]" value="{{ $k }}" class="peer sr-only" @checked($it->kondisi === $k)>
                      <span class="block rounded-lg border border-stone-200 bg-white px-2.5 py-1.5 text-[11px] font-bold text-stone-500
                                   peer-checked:bg-lime-grad peer-checked:text-white peer-checked:border-transparent hover:border-cam-lime transition">{{ $k }}</span>
                    </label>
                  @endforeach
                </div>
                <select name="item[{{ $it->id }}][risiko]" class="ring-focus rounded-lg border border-stone-200 px-2.5 py-1.5 text-[11.5px]">
                  <option value="">Risiko —</option>
                  @foreach(Hazard::RISIKO as $rs)<option value="{{ $rs }}" @selected($it->risiko === $rs)>{{ $rs }}</option>@endforeach
                </select>
                <input name="item[{{ $it->id }}][temuan]" value="{{ $it->temuan }}" placeholder="Temuan (bila tidak sesuai)"
                       class="ring-focus rounded-lg border border-stone-200 px-3 py-1.5 text-[12px]">
                <input name="item[{{ $it->id }}][tindakan]" value="{{ $it->tindakan }}" placeholder="Tindakan perbaikan"
                       class="ring-focus sm:col-span-3 rounded-lg border border-stone-200 px-3 py-1.5 text-[12px]">
              </div>

              @if(!$it->hazard_report_id)
                <div class="mt-2.5 text-right">
                  <button type="button" onclick="naikkan({{ $it->id }})"
                          class="text-[11px] font-bold text-red-500 hover:bg-red-50 px-2.5 py-1 rounded-lg transition">Naikkan jadi Hazard Report →</button>
                </div>
              @endif
            </div>
          @endforeach
        </div>
      </div>
    @empty
      <div class="bg-white rounded-2xl border border-dashed border-stone-200 p-12 text-center">
        <p class="text-[13px] text-stone-400">Belum ada parameter pemeriksaan.</p>
        <p class="text-[12px] text-stone-300 mt-1">Pilih jenis inspeksi bertemplat, atau tambahkan item manual di bawah.</p>
      </div>
    @endforelse

    <div class="sticky bottom-4 flex gap-2.5">
      <select name="status" class="ring-focus rounded-xl border border-stone-200 bg-white px-4 py-3 text-[12.5px] font-semibold">
        <option value="Berjalan" @selected($i->status==='Berjalan')>Berjalan</option>
        <option value="Selesai"  @selected($i->status==='Selesai')>Selesai</option>
      </select>
      <button class="lime-gradient shadow-glow flex-1 rounded-xl text-white py-3 text-[13px] font-bold hover:brightness-105 transition">Simpan Hasil Pemeriksaan</button>
    </div>
  </form>

  {{-- Tambah item manual --}}
  <details class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
    <summary class="text-[12.5px] font-bold text-cam-lime-deep cursor-pointer hover:underline">+ Tambah item pemeriksaan manual</summary>
    <form action="{{ route('inspeksi.item.store', $i) }}" method="POST" enctype="multipart/form-data" class="grid sm:grid-cols-4 gap-2 mt-3">
      @csrf
      <input name="kelompok" placeholder="Kelompok" class="ring-focus rounded-lg border border-stone-200 px-3 py-2 text-[12.5px]">
      <input name="uraian" placeholder="Uraian pemeriksaan" required class="ring-focus sm:col-span-3 rounded-lg border border-stone-200 px-3 py-2 text-[12.5px]">
      <input name="temuan" placeholder="Temuan" class="ring-focus sm:col-span-2 rounded-lg border border-stone-200 px-3 py-2 text-[12.5px]">
      <input name="tindakan" placeholder="Tindakan" class="ring-focus sm:col-span-2 rounded-lg border border-stone-200 px-3 py-2 text-[12.5px]">
      <input type="file" name="foto[]" accept="image/*" multiple class="sm:col-span-3 text-[12px] text-stone-500 self-center">
      <button class="lime-gradient rounded-lg text-white py-2 text-[12px] font-bold hover:brightness-105">+ Tambah</button>
    </form>
  </details>

  @php
    $tidak = $i->items->where('kondisi','Tidak Sesuai');
    $pesanIns = "*Hasil Inspeksi {$i->kode}*\n\n"
      ."{$i->judul}\n"
      .($i->template ? "Jenis: {$i->template->nama}\n" : '')
      ."Tanggal: ".optional($i->tanggal)->format('d/m/Y')."\n"
      ."Lokasi: ".($i->lokasi ?: '-')."\n"
      ."Perusahaan: ".($i->company?->name ?: '-')."\n"
      ."Inspektur: ".($i->inspectors->map(fn($p) => $p->nama)->implode(', ') ?: '-')."\n\n"
      ."Ringkasan: {$r['sesuai']} sesuai · {$r['tidak']} tidak sesuai · {$r['na']} N/A\n"
      .($tidak->count() ? "\n*Temuan tidak sesuai:*\n".$tidak->take(15)->map(fn($x,$n) =>
          ($n+1).". ".$x->uraian.($x->temuan ? " — {$x->temuan}" : '')
          .($x->tindakan ? " (tindakan: {$x->tindakan})" : ''))->implode("\n") : '')
      ."\n\nMohon ditindaklanjuti. — EQOHSEE";
  @endphp

  <div class="flex flex-wrap gap-2.5">
    <a href="{{ route('inspeksi.index') }}" class="rounded-xl border border-stone-200 px-5 py-2.5 text-[12.5px] font-bold text-stone-600 hover:bg-stone-50 transition">← Daftar</a>
    <a href="{{ \App\Support\Ekspor::waLink($pesanIns, $i->company?->waNumber()) }}" target="_blank" rel="noopener"
       class="rounded-xl bg-[#25D366] text-white px-4 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">Bagikan WhatsApp</a>
    <a href="{{ route('inspeksi.ekspor.cetak', ['status' => $i->status]) }}" target="_blank" rel="noopener"
       class="rounded-xl border border-stone-200 px-4 py-2.5 text-[12.5px] font-bold text-stone-600 hover:bg-stone-50 transition">⎙ Cetak</a>
    <a href="{{ route('inspeksi.edit', $i) }}" class="rounded-xl border border-stone-200 px-5 py-2.5 text-[12.5px] font-bold text-cam-lime-deep hover:bg-cam-lime-soft transition">Ubah info</a>
    <form action="{{ route('inspeksi.destroy', $i) }}" method="POST" onsubmit="return confirm('Hapus inspeksi ini?')" class="ml-auto">
      @csrf @method('DELETE')
      <button class="rounded-xl px-4 py-2.5 text-[12.5px] font-bold text-red-500 hover:bg-red-50 transition">Hapus</button>
    </form>
  </div>
</div>
{{-- Form terpisah untuk menaikkan temuan (di luar form checklist) --}}
<form id="formNaikkan" method="POST" class="hidden">@csrf</form>

@push('scripts')
<script>
  function naikkan(id){
    if (!confirm('Naikkan temuan ini menjadi Hazard Report?')) return;
    const f = document.getElementById('formNaikkan');
    f.action = "{{ url('inspeksi/item') }}/" + id + "/angkat";
    f.submit();
  }
</script>
@endpush
@endsection

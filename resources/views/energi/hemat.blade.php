@extends('layouts.app')
@section('title','Energy Saving Opportunities')

@section('content')
@php use App\Support\Energi; @endphp

<div class="max-w-5xl mx-auto space-y-5">

  @if(session('ok'))
    <div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>
  @endif

  <x-energi.kepala judul="Energy Saving Opportunities"
      ket="Peluang penghematan dicatat pada satuan asalnya — liter dan kilowatt-jam — bukan
           dalam rupiah. Rupiah dan karbonnya dihitung ulang saat dibaca, jadi perubahan harga
           bahan bakar tidak membuat angka lama menjadi keliru.">

    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mt-6 pt-6 hairline border-b-0">
      @foreach ([
        ['Total Peluang', $daftar->count(), 'usulan', '#F57C00'],
        ['Sudah Berjalan', $terwujud['jumlah'], 'program', '#FF9800'],
        ['Potensi Penuh', 'Rp '.Energi::ringkas($potensi['rupiah'], 2), '/bulan', '#D9993A'],
        ['Sudah Terwujud', 'Rp '.Energi::ringkas($terwujud['rupiah'], 2), '/bulan', '#E2663A'],
      ] as [$l, $v, $s, $w])
        <div>
          <div class="stat stat-sm" style="color:{{ $w }}">{{ $v }}<span class="stat-unit">{{ $s }}</span></div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">{{ $l }}</div>
        </div>
      @endforeach
    </div>
  </x-energi.kepala>

  <div class="grid gap-4 lg:grid-cols-5">

    {{-- Formulir --}}
    <section class="kartu-lux rounded-2xl p-6 lg:col-span-2">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Catat Peluang</h3>

      <form method="POST" action="{{ route('energi.hemat.simpan') }}" class="space-y-3.5 mt-5">
        @csrf

        <div>
          <label class="block text-[10px] font-bold uppercase tracking-wide text-stone-500 mb-1">Judul</label>
          <input name="judul" value="{{ old('judul') }}" required maxlength="200"
                 placeholder="Batasi idle dump truck di area loading"
                 class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition">
          <x-input-error :messages="$errors->get('judul')" class="mt-1.5" />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-wide text-stone-500 mb-1">Area</label>
            <input name="area" value="{{ old('area') }}" placeholder="Pit / Workshop / Camp"
                   class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition">
          </div>
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-wide text-stone-500 mb-1">Status</label>
            <select name="status" class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition">
              @foreach(Energi::STATUS_PELUANG as $s)
                <option value="{{ $s }}" @selected(old('status','usulan') === $s)>{{ ucfirst($s) }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-wide text-stone-500 mb-1">Hemat Solar (L/bulan)</label>
            <input type="number" step="0.01" name="hemat_liter" value="{{ old('hemat_liter') }}"
                   class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition">
          </div>
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-wide text-stone-500 mb-1">Hemat Listrik (kWh/bulan)</label>
            <input type="number" step="0.01" name="hemat_kwh" value="{{ old('hemat_kwh') }}"
                   class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition">
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-wide text-stone-500 mb-1">Penanggung Jawab</label>
            <input name="penanggung_jawab" value="{{ old('penanggung_jawab') }}"
                   class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition">
          </div>
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-wide text-stone-500 mb-1">Target Selesai</label>
            <input type="date" name="target_selesai" value="{{ old('target_selesai') }}"
                   class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition">
          </div>
        </div>

        <div>
          <label class="block text-[10px] font-bold uppercase tracking-wide text-stone-500 mb-1">Uraian</label>
          <textarea name="uraian" rows="3" placeholder="Apa yang dikerjakan dan dari mana perkiraan penghematannya."
                    class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition">{{ old('uraian') }}</textarea>
        </div>

        <button class="w-full rounded-xl bg-cam-ink text-white px-4 py-2.5 text-[12.5px] font-bold hover:brightness-110 transition">
          Simpan Peluang
        </button>
      </form>
    </section>

    {{-- Daftar --}}
    <section class="kartu-lux rounded-2xl p-6 lg:col-span-3">
      <div class="flex items-baseline justify-between gap-3">
        <h3 class="font-display text-[16px] font-black text-cam-ink">Daftar Peluang</h3>
        <span class="text-[11.5px] text-stone-400 shrink-0">Urut dari yang terbesar penghematannya</span>
      </div>

      @if($daftar->count())
        <div class="space-y-3 mt-5">
          @foreach($daftar as $o)
            @php
              $warna = match($o->status) {
                'selesai'  => '#F57C00',
                'berjalan' => '#FF9800',
                'disetujui'=> '#D9993A',
                'ditolak'  => '#9AA3AE',
                default    => '#22312F',
              };
            @endphp
            <div class="rounded-xl border {{ $o->terwujud() ? 'border-cam-lime/40 bg-cam-lime-soft/30' : 'border-stone-100' }} p-4">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                  <div class="text-[13px] font-bold text-cam-ink leading-snug">{{ $o->judul }}</div>
                  <div class="text-[10.5px] text-stone-400 mt-1">
                    {{ $o->area ?: 'Tanpa area' }}
                    @if($o->penanggung_jawab) · {{ $o->penanggung_jawab }} @endif
                    @if($o->target_selesai) · target {{ $o->target_selesai->translatedFormat('d M Y') }} @endif
                  </div>
                </div>
                <span class="shrink-0 text-[9.5px] font-bold uppercase tracking-wide text-white px-2 py-1 rounded-lg"
                      style="background:{{ $warna }}">{{ $o->status }}</span>
              </div>

              @if($o->uraian)
                <p class="text-[11.5px] text-stone-500 mt-2.5 leading-relaxed">{{ $o->uraian }}</p>
              @endif

              <div class="grid grid-cols-4 gap-2 mt-3.5 pt-3.5 hairline border-b-0">
                @foreach ([
                  ['Solar', number_format($o->hemat_liter).' L'],
                  ['Listrik', number_format($o->hemat_kwh).' kWh'],
                  ['Emisi', number_format($o->tco2e(), 2).' t'],
                  ['Nilai', 'Rp '.Energi::ringkas($o->rupiah(), 1)],
                ] as [$l, $v])
                  <div>
                    <div class="num text-[12.5px] font-bold text-cam-ink">{{ $v }}</div>
                    <div class="text-[10px] text-stone-400 mt-0.5">{{ $l }}/bulan</div>
                  </div>
                @endforeach
              </div>

              <div class="flex flex-wrap items-center gap-2 mt-3.5">
                <form method="POST" action="{{ route('energi.hemat.ubah', $o) }}" class="flex items-center gap-2">
                  @csrf @method('PUT')
                  <select name="status" onchange="this.form.submit()"
                          class="ring-focus rounded-xl border border-stone-200 px-2.5 py-1.5 text-[11.5px] transition">
                    @foreach(Energi::STATUS_PELUANG as $s)
                      <option value="{{ $s }}" @selected($o->status === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                  </select>
                  <noscript><button class="text-[11.5px] font-bold text-cam-lime-deep">Ubah</button></noscript>
                </form>

                <form method="POST" action="{{ route('energi.hemat.hapus', $o) }}"
                      onsubmit="return confirm('Hapus peluang ini?')">
                  @csrf @method('DELETE')
                  <button class="text-[11.5px] font-bold text-stone-400 hover:text-cam-coral transition px-2 py-1.5">Hapus</button>
                </form>
              </div>
            </div>
          @endforeach
        </div>
      @else
        <p class="text-[12px] text-stone-400 mt-6">Belum ada peluang yang dicatat.</p>
      @endif
    </section>
  </div>

</div>
@endsection

@extends('layouts.app')
@section('title','KO/SPIP — '.$o->kode)

@section('content')
@php $K = \App\Support\Ko::class; $st = $o->status_ko; @endphp
<div class="max-w-5xl mx-auto space-y-5">
  @include('ko._flash')
  @include('ko._picker')

  <div class="bg-white rounded-2xl border border-stone-200 p-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <div class="flex items-center gap-2">
          <span class="num text-[12px] font-bold text-stone-400">{{ $o->kode }}</span>
          <span class="text-[10.5px] px-2 py-0.5 rounded-md bg-stone-100 text-stone-600 font-semibold">{{ $o->kategori }}</span>
          @include('ko._badge', ['st' => $st])
        </div>
        <h2 class="text-[19px] font-bold text-cam-ink mt-1.5">{{ $o->nama }}</h2>
        <p class="text-[12px] text-stone-500 mt-0.5">
          {{ $o->jenis ?: '—' }} · {{ $o->merk ?: '—' }} · SN {{ $o->serial_number ?: '—' }}
        </p>
      </div>
      @if($bolehUbah)
        <div class="flex gap-2">
          <a href="{{ route('ko.edit', $o) }}" class="rounded-xl border border-stone-200 px-4 py-2 text-[12.5px] font-bold text-stone-600 hover:border-cam-ink">Edit</a>
          @if(auth()->user()->isAdmin())
            <form method="POST" action="{{ route('ko.destroy', $o) }}" onsubmit="return confirm('Hapus objek {{ $o->kode }} beserta pengaman, riwayat, dan tindak lanjutnya?')">
              @csrf @method('DELETE')
              <button class="rounded-xl border border-red-200 text-red-600 px-4 py-2 text-[12.5px] font-bold hover:bg-red-50">Hapus</button>
            </form>
          @endif
        </div>
      @endif
    </div>

    @php
      $info = [
        ['Perusahaan', $o->company?->name ?: '—'], ['Lokasi', $o->lokasi ?: '—'],
        ['Kritikalitas', $o->kritikalitas], ['Status Operasi', $o->status_operasi],
        ['Tgl Sertifikasi', $o->tgl_sertifikasi?->format('d M Y') ?: '—'],
        ['Kadaluarsa', $o->kadaluarsa?->format('d M Y') ?: '—'],
        ['Interval', $o->interval_tahun.' tahun'], ['No. Sertifikat', $o->no_sertifikat ?: '—'],
        ['Lembaga Uji', $o->lembaga_uji ?: '—'], ['Lapor KaIT', $o->lapor_kait ? 'Sudah' : 'Belum'],
        ['PM Terakhir', $o->pm_terakhir?->format('d M Y') ?: '—'],
        ['PM Berikutnya', $o->pm_berikutnya?->format('d M Y') ?: '—'],
      ];
    @endphp
    <div class="grid sm:grid-cols-3 gap-x-5 gap-y-3 mt-5 pt-5 border-t border-stone-100">
      @foreach($info as $i)
        <div>
          <div class="text-[10px] uppercase tracking-wider text-stone-400 font-bold">{{ $i[0] }}</div>
          <div class="text-[12.5px] text-cam-ink mt-0.5">{{ $i[1] }}</div>
        </div>
      @endforeach
    </div>

    @if($o->sisa_hari !== null)
      <div class="mt-5 pt-4 border-t border-stone-100 flex flex-wrap items-center gap-2 text-[12px]">
        <span class="num font-bold" style="color: {{ $K::warna($st) }}">
          {{ $o->sisa_hari < 0 ? abs($o->sisa_hari).' hari lewat' : $o->sisa_hari.' hari lagi' }}
        </span>
        <span class="text-stone-400">sampai sertifikat kadaluarsa</span>
        @if($o->pm_terlewat)
          <span class="ml-2 text-[11px] font-bold text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-2 py-0.5">PM terlewat jadwal</span>
        @endif
      </div>
    @endif

    @if($o->keterangan)
      <p class="mt-4 text-[12.5px] text-stone-600 bg-stone-50 rounded-xl p-3.5 border border-stone-100">{{ $o->keterangan }}</p>
    @endif
  </div>

  {{-- ── Pengaman ── --}}
  <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-stone-100 flex items-center justify-between">
      <h3 class="text-[13px] font-bold text-cam-ink">Perangkat Pengaman</h3>
      <span class="text-[11px] text-stone-400 num">{{ $o->safeguards->where('status','Berfungsi')->count() }}/{{ $o->safeguards->count() }} berfungsi</span>
    </div>

    @if($o->safeguards->count())
      <div class="tabel-scroll"><table class="w-full text-[12px]">
        <tbody>
          @foreach($o->safeguards as $p)
            <tr class="border-b border-stone-50 last:border-0">
              <td class="px-5 py-2.5 font-semibold text-cam-ink">{{ $p->nama }}</td>
              <td class="px-3 py-2.5 text-stone-500 num">{{ $p->spesifikasi ?: '—' }}</td>
              <td class="px-3 py-2.5 text-stone-500 num">{{ $p->tgl_periksa?->format('d M Y') ?: '—' }}</td>
              <td class="px-3 py-2.5">
                <span class="text-[10.5px] font-bold px-2 py-0.5 rounded-full {{ $p->berfungsi ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">{{ $p->status }}</span>
              </td>
              <td class="px-5 py-2.5 text-right">
                @if(auth()->user()->isAdmin())
                  <form method="POST" action="{{ route('ko.pengaman.hapus', [$o, $p]) }}" onsubmit="return confirm('Hapus perangkat ini?')">
                    @csrf @method('DELETE')
                    <button class="text-[11px] text-stone-400 hover:text-red-600">hapus</button>
                  </form>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table></div>
    @else
      <p class="px-5 py-6 text-[12.5px] text-stone-400">Belum ada perangkat pengaman terdaftar.</p>
    @endif

    @if($bolehUbah)
      <form method="POST" action="{{ route('ko.pengaman.simpan', $o) }}" class="px-5 py-4 border-t border-stone-100 bg-stone-50">
        @csrf
        <div class="grid sm:grid-cols-5 gap-2">
          <input name="nama" required placeholder="Nama perangkat" class="ring-focus rounded-lg border border-stone-200 px-3 py-2 text-[12px] sm:col-span-2">
          <input name="spesifikasi" placeholder="Nilai ukur" class="ring-focus rounded-lg border border-stone-200 px-3 py-2 text-[12px]">
          <input type="date" name="tgl_periksa" class="ring-focus rounded-lg border border-stone-200 px-3 py-2 text-[12px]">
          <select name="status" class="ring-focus rounded-lg border border-stone-200 px-3 py-2 text-[12px] font-semibold">
            @foreach($K::PENGAMAN_STATUS as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach
          </select>
        </div>
        <div class="flex flex-wrap items-center gap-2 mt-2">
          <button class="rounded-lg bg-cam-ink text-white px-4 py-2 text-[12px] font-bold">Tambah perangkat</button>
          <span class="text-[11px] text-stone-500">
            APAR &amp; proteksi kebakaran dikelola di modul SIGAP — jangan didaftarkan ulang di sini.
          </span>
        </div>
      </form>
    @endif
  </div>

  {{-- ── Riwayat pemeriksaan ── --}}
  <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-stone-100"><h3 class="text-[13px] font-bold text-cam-ink">Riwayat Pemeriksaan</h3></div>
    @if($o->inspections->count())
      <div class="tabel-scroll"><table class="w-full text-[12px]">
        <tbody>
          @foreach($o->inspections as $i)
            <tr class="border-b border-stone-50 last:border-0">
              <td class="px-5 py-2.5 num text-stone-500 whitespace-nowrap">{{ $i->tanggal?->format('d M Y') }}</td>
              <td class="px-3 py-2.5"><span class="text-[10.5px] px-2 py-0.5 rounded-md bg-stone-100 text-stone-600 font-semibold">{{ $i->jenis }}</span></td>
              <td class="px-3 py-2.5 text-stone-600">{{ $i->hasil ?: '—' }} @if($i->nilai_ukur)<span class="text-stone-400 num">· {{ $i->nilai_ukur }}</span>@endif</td>
              <td class="px-5 py-2.5 text-stone-500">{{ $i->personnel?->nama ?: '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table></div>
    @else
      <p class="px-5 py-6 text-[12.5px] text-stone-400">Belum ada riwayat.</p>
    @endif
  </div>

  {{-- ── Kajian & tindak lanjut ── --}}
  <div class="grid md:grid-cols-2 gap-5">
    <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-stone-100"><h3 class="text-[13px] font-bold text-cam-ink">Kajian Teknis</h3></div>
      @forelse($o->reviews as $k)
        <div class="px-5 py-3 border-b border-stone-50 last:border-0">
          <div class="text-[12.5px] font-semibold text-cam-ink">{{ $k->judul }}</div>
          <div class="text-[11px] text-stone-500 mt-0.5">
            {{ $k->pemicu ?: '—' }} · {{ $k->tanggal?->format('d M Y') ?: '—' }} · {{ $k->personnel?->nama ?: $k->oleh ?: '—' }}
          </div>
          <span class="inline-block mt-1.5 text-[10.5px] font-bold px-2 py-0.5 rounded-full {{ $k->status === 'Dilaporkan' ? 'bg-emerald-50 text-emerald-700' : 'bg-stone-100 text-stone-600' }}">{{ $k->status }}</span>
        </div>
      @empty
        <p class="px-5 py-6 text-[12.5px] text-stone-400">Belum ada kajian teknis.</p>
      @endforelse
    </div>

    <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-stone-100"><h3 class="text-[13px] font-bold text-cam-ink">Tindak Lanjut</h3></div>
      @forelse($o->actions as $a)
        <div class="px-5 py-3 border-b border-stone-50 last:border-0">
          <div class="text-[12.5px] text-cam-ink">{{ $a->uraian }}</div>
          <div class="text-[11px] text-stone-500 mt-0.5">
            {{ $a->sumber }} · target {{ $a->target_tgl?->format('d M Y') ?: '—' }} · {{ $a->pic?->name ?: $a->pic_nama ?: 'belum ada PIC' }}
          </div>
          <span class="inline-block mt-1.5 text-[10.5px] font-bold px-2 py-0.5 rounded-full {{ $a->status === 'Selesai' ? 'bg-emerald-50 text-emerald-700' : ($a->terlambat ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700') }}">
            {{ $a->status }}{{ $a->terlambat ? ' · terlambat' : '' }}
          </span>
        </div>
      @empty
        <p class="px-5 py-6 text-[12.5px] text-stone-400">Belum ada tindak lanjut.</p>
      @endforelse
    </div>
  </div>
</div>
@endsection

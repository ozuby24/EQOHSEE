<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Register Hazard Report — EQOHSEE</title>
<style>
  *{box-sizing:border-box}
  body{font-family:'Inter',Arial,sans-serif;margin:0;padding:24px;color:#1b1817;font-size:11px}
  h1{font-size:18px;margin:0 0 2px}
  .sub{color:#78716c;font-size:11px;margin-bottom:16px}
  table{width:100%;border-collapse:collapse}
  th{background:#f5f5f4;text-align:left;font-size:9px;text-transform:uppercase;letter-spacing:.05em;
     color:#57534e;padding:7px 6px;border-bottom:2px solid #e7e5e4}
  td{padding:7px 6px;border-bottom:1px solid #f5f5f4;vertical-align:top}
  .num{text-align:right;font-variant-numeric:tabular-nums}
  .b{font-weight:700}
  .tag{display:inline-block;padding:1px 6px;border-radius:99px;color:#fff;font-size:9px;font-weight:700}
  .kop{display:flex;justify-content:space-between;align-items:flex-end;border-bottom:3px solid #84cc16;padding-bottom:10px;margin-bottom:14px}
  .logo{width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,#65a30d,#a3e635);
        color:#fff;display:grid;place-items:center;font-weight:800;font-size:14px}
  @media print{ .noprint{display:none} body{padding:0} }
</style>
  <link rel="icon" href="{{ \App\Support\Aset::v('favicon.ico') }}" sizes="any">
  <link rel="icon" type="image/png" href="{{ \App\Support\Aset::v('favicon-32.png') }}">
  <link rel="apple-touch-icon" href="{{ \App\Support\Aset::v('apple-touch-icon.png') }}">
</head>
<body>
<div class="noprint" style="margin-bottom:14px">
  <button onclick="window.print()" style="background:#65a30d;color:#fff;border:0;padding:9px 18px;border-radius:9px;font-weight:700;cursor:pointer">
    Cetak / Simpan PDF
  </button>
  <span style="color:#78716c;font-size:11px;margin-left:8px">Pilih "Simpan sebagai PDF" pada dialog cetak.</span>
</div>

<div class="kop">
  <div style="display:flex;gap:9px;align-items:center">
    <div class="logo">E</div>
    <div>
      <h1>Register Hazard Report</h1>
      <div class="sub" style="margin:0">EQOHSEE · HSE Platform</div>
    </div>
  </div>
  <div style="text-align:right;font-size:10px;color:#78716c">
    Dicetak {{ now()->translatedFormat('d F Y · H:i') }}<br>
    Total <b>{{ $data->count() }}</b> laporan
  </div>
</div>

<table>
  <thead>
    <tr>
      <th>Kode</th><th>Tanggal</th><th>Lokasi</th><th>Risiko</th><th>Kategori</th>
      <th>Deskripsi</th><th>Pelapor</th><th>Ditujukan</th><th>Status</th>
    </tr>
  </thead>
  <tbody>
    @forelse($data as $h)
      <tr>
        <td class="b">{{ $h->kode }}</td>
        <td>{{ optional($h->tanggal)->format('d/m/Y') }}</td>
        <td>{{ $h->lokasi ?: '—' }}</td>
        <td><span class="tag" style="background: {{ \App\Support\Hazard::WARNA_RISIKO[$h->risiko] ?? '#a8a29e' }}">{{ $h->risiko }}</span></td>
        <td>{{ $h->kategori ?: '—' }}</td>
        <td style="max-width:220px">
          {{ $h->deskripsi }}
          @if(count($h->unsafe_action_list))<br><span style="color:#78716c;font-size:9.5px">UA: {{ implode(', ', $h->unsafe_action_list) }}</span>@endif
          @if(count($h->unsafe_condition_list))<br><span style="color:#78716c;font-size:9.5px">UC: {{ implode(', ', $h->unsafe_condition_list) }}</span>@endif
        </td>
        <td>{{ $h->pelapor_nama }}<br><span style="color:#78716c;font-size:9.5px">{{ $h->pelapor_jabatan }}</span></td>
        <td>{{ $h->company?->name ?: ($h->terlapor ?: '—') }}</td>
        <td><span class="tag" style="background: {{ \App\Support\Hazard::WARNA_STATUS[$h->status] ?? '#a8a29e' }}">{{ $h->status }}</span></td>
      </tr>
    @empty
      <tr><td colspan="9" style="text-align:center;padding:30px;color:#a8a29e">Tidak ada data.</td></tr>
    @endforelse
  </tbody>
</table>
</body>
</html>

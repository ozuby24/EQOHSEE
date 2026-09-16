<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <style>
        body { font-family: "Helvetica Neue", Arial, sans-serif; font-size: 12px; color: #1e293b; }
        h1 { font-size: 18px; margin-bottom: 2px; }
        .subtitle { color: #64748b; margin-top: 0; margin-bottom: 16px; }
        .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .info-table td { padding: 3px 0; vertical-align: top; }
        .info-table td.label { width: 140px; color: #64748b; }
        h2 { font-size: 14px; margin-top: 22px; margin-bottom: 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        table.laporan { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table.laporan th, table.laporan td { border: 1px solid #e2e8f0; padding: 5px 7px; text-align: left; font-size: 11px; }
        table.laporan th { background: #f8fafc; color: #475569; }
        .badge { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 10px; }
        .badge-green { background: #f0fdf4; color: #15803d; }
        .badge-red { background: #fef2f2; color: #b91c1c; }
        .badge-amber { background: #fffbeb; color: #b45309; }
        .badge-slate { background: #f1f5f9; color: #475569; }
        .empty { color: #94a3b8; font-style: italic; }
        .footer { margin-top: 24px; color: #94a3b8; font-size: 10px; }
    </style>
</head>
<body>
    <h1>Laporan Evaluasi PJP</h1>
    <p class="subtitle">{{ $pjp->nama_perusahaan }}</p>

    <table class="info-table">
        <tr>
            <td class="label">Status</td>
            <td>{{ $statusLabel }}</td>
        </tr>
        <tr>
            <td class="label">NIB</td>
            <td>{{ $pjp->nib ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Penanggung Jawab</td>
            <td>{{ $pjp->penanggung_jawab ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Alamat</td>
            <td>{{ $pjp->alamat ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Catatan</td>
            <td>{{ $pjp->catatan ?? '-' }}</td>
        </tr>
    </table>

    @foreach ($jenisOptions as $jenis => $label)
        <h2>{{ $label }}</h2>
        @php $items = $laporans->where('jenis', $jenis); @endphp
        @if ($items->isEmpty())
            <p class="empty">Belum ada dokumen diunggah.</p>
        @else
            <table class="laporan">
                <thead>
                    <tr>
                        <th>Nama File</th>
                        <th>Periode</th>
                        <th>Diunggah</th>
                        <th>Ketepatan Waktu</th>
                        <th>Kesesuaian Isi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td>{{ $item->file_name }}</td>
                            <td>{{ $item->periode ?? '-' }}</td>
                            <td>{{ $item->created_at->format('d-m-Y') }}</td>
                            <td>
                                <span class="badge {{ $item->tepat_waktu ? 'badge-green' : 'badge-red' }}">
                                    {{ $item->tepat_waktu ? 'Tepat Waktu' : 'Terlambat' }}
                                </span>
                            </td>
                            <td>
                                @if ($item->kesesuaian_isi === 'sesuai')
                                    <span class="badge badge-green">Sesuai</span>
                                @elseif ($item->kesesuaian_isi === 'tidak_sesuai')
                                    <span class="badge badge-amber">Tidak Sesuai</span>
                                @else
                                    <span class="badge badge-slate">Belum Dievaluasi</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach

    <p class="footer">Dicetak pada {{ now()->format('d-m-Y H:i') }} WIB &mdash; Pemantauan &amp; Pengelolaan PJP</p>
</body>
</html>

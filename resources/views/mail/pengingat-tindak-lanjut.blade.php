<x-mail::message>
# Pengingat Tindak Lanjut Temuan Audit

Yth. Auditi **{{ $perusahaan->name }}**,

batas waktu tindak lanjut {{ count($temuan) }} temuan audit SMKP berikut sudah dekat. Mohon sampaikan tindakan perbaikan beserta buktinya sebelum target penyelesaian.

<x-mail::table>
| Temuan | Kategori | Target | Sisa waktu |
|:-------|:---------|:-------|:-----------|
@foreach ($temuan as $t)
| [{{ $t['nomor'] }}]({{ $t['url'] }}) · {{ $t['kode'] }} | {{ $t['kategori'] }} | {{ $t['target'] }} | {{ $t['sisa'] }} hari |
@endforeach
</x-mail::table>

@foreach ($temuan as $t)
**{{ $t['nomor'] }} · {{ $t['kode'] }}** ({{ $t['audit'] }}, status {{ $t['status'] }})<br>
{{ \Illuminate\Support\Str::limit($t['uraian'], 300) }}

@endforeach
<x-mail::button :url="$daftar">
Buka Tindak Lanjut Temuan
</x-mail::button>

Abaikan email ini bila tindak lanjutnya sudah dikirim — auditor akan memverifikasi lalu menutup temuannya. Bila target penyelesaian perlu disesuaikan, hubungi tim auditor.

Salam,<br>
Tim Audit SMKP — {{ config('app.name') }}

<x-mail::subcopy>
Email ini dikirim otomatis ke akun auditi {{ $perusahaan->name }} di {{ config('app.name') }} {{ $jadwal }} sebelum batas waktu tindak lanjut setiap temuan. Jangan membalas email ini.
</x-mail::subcopy>
</x-mail::message>

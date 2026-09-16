<?php

namespace App\Imports;

use App\Models\Pjp;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import massal PJP dari Excel/CSV. Sengaja per-baris (bukan gagal semua
 * kalau satu baris salah): baris valid tetap disimpan, baris tidak valid
 * dilewati dan dicatat di `$gagal` supaya penggunanya tahu persis baris
 * mana yang perlu diperbaiki — daripada satu kesalahan ketik membatalkan
 * seluruh proses impor puluhan baris lainnya.
 */
class PjpImport implements ToCollection, WithHeadingRow
{
    public int $berhasil = 0;

    /** @var array<int, array{baris: int, pesan: string}> */
    public array $gagal = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $data = [
                'nama_perusahaan' => $this->stringOrNull($row['nama_perusahaan'] ?? null),
                'nib' => $this->stringOrNull($row['nib'] ?? null),
                'penanggung_jawab' => $this->stringOrNull($row['penanggung_jawab'] ?? null),
                'alamat' => $this->stringOrNull($row['alamat'] ?? null),
                'status' => $this->normalizeStatus($row['status'] ?? null),
                'catatan' => $this->stringOrNull($row['catatan'] ?? null),
            ];

            $validator = Validator::make($data, [
                'nama_perusahaan' => ['required', 'string', 'max:255'],
                'nib' => ['nullable', 'digits:13'],
                'penanggung_jawab' => ['nullable', 'string', 'max:255'],
                'alamat' => ['nullable', 'string'],
                'status' => ['required', 'string', 'in:'.implode(',', array_keys(Pjp::STATUS))],
                'catatan' => ['nullable', 'string'],
            ], [
                'nib.digits' => 'NIB harus terdiri dari 13 digit angka.',
            ]);

            // +1 karena $index 0-based, +1 lagi karena baris pertama file adalah heading.
            $baris = $index + 2;

            if ($validator->fails()) {
                $this->gagal[] = [
                    'baris' => $baris,
                    'pesan' => $validator->errors()->first(),
                ];

                continue;
            }

            Pjp::create($validator->validated());
            $this->berhasil++;
        }
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Menerima nilai status sebagai key mentah ('aktif') maupun label
     * manusiawinya ('Aktif Dipantau') — supaya orang yang isi templatenya
     * tidak wajib tahu nama key internal di database.
     */
    private function normalizeStatus(mixed $value): ?string
    {
        $value = $this->stringOrNull($value);

        if ($value === null) {
            return 'aktif';
        }

        if (array_key_exists($value, Pjp::STATUS)) {
            return $value;
        }

        $labelToKey = array_flip(array_map('strtolower', Pjp::STATUS));

        return $labelToKey[strtolower($value)] ?? $value;
    }
}

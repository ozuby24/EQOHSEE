<?php

namespace App\Models\Meranti;

/** MERANTI — Pengajuan dana & control budget. */
class PengajuanDana extends Baris
{
    public const KUNCI = 'pengajuan_dana';

    protected $table = 'meranti_pengajuan_dana';
}

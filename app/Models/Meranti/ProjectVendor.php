<?php

namespace App\Models\Meranti;

/** MERANTI — Pasangan vendor dan project. */
class ProjectVendor extends Baris
{
    public const KUNCI = 'project_vendor';

    protected $table = 'meranti_project_vendor';
}

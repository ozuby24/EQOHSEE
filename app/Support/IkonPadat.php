<?php

namespace App\Support;

/**
 * Glyph padat untuk ubin ikon berdimensi.
 *
 * ── KENAPA ADA DUA SET IKON ──
 *
 * Menu::all() menyimpan glyph GARIS: satu jalur, digambar dengan
 * stroke, dipakai bilah samping dan bilah pindah sub-halaman. Di sana
 * garis memang yang benar — ikon kecil berdampingan dengan teks, di
 * atas latar polos, dan garis tipis tidak berebut perhatian dengan
 * tulisan di sebelahnya.
 *
 * Di atas ubin bergradien, glyph garis kalah. Yang terlihat tinggal
 * beberapa garis rambut putih di atas bidang berwarna; ubinnya sendiri
 * terbaca sebagai kotak warna, bukan sebagai ikon. Yang dibutuhkan di
 * sana bentuk PADAT — siluet putih penuh yang punya berat sendiri dan
 * tetap terbaca dari jarak satu meter, seperti ikon pada layar depan
 * telepon.
 *
 * Karena itu set ini terpisah, bukan menggantikan. Keduanya hidup
 * berdampingan dan masing-masing dipakai di tempat yang cocok.
 *
 * ── ASAL GEOMETRINYA ──
 *
 * Set ikon EQOHSEE v1.0 — dua puluh delapan bentuk yang digambar
 * khusus untuk pertambangan dan K3: helm, beliung bersilang, truk
 * jungkit, kubah gudang, tetes air, bongkah minerba. Bentuk sebuah
 * beliung tidak ada di pustaka ikon umum mana pun, dan itulah yang
 * membuat set ini pantas disalin ke dalam repo.
 *
 * Aslinya sprite <symbol> berisi path, rect, circle, dan ellipse.
 * Semuanya sudah diubah menjadi jalur 'd' supaya penggambarnya cukup
 * satu bentuk elemen. Hasil ubahannya dibandingkan berdampingan dengan
 * aslinya lebih dulu — dan memang perlu: ic-operations sempat berubah
 * jadi coretan karena batang beliungnya digambar dengan GARIS, bukan
 * isian, dan salah satu bagiannya diputar 45 derajat. Keduanya kini
 * dibawa apa adanya lewat kunci 'garis' dan 'putar'.
 *
 * Kanvasnya 0 0 24 24.
 *
 * ── DUA MODUL MASIH MEMINJAM ──
 *
 * Keselamatan Operasi dan Kestabilan Lereng belum punya bentuk di set
 * EQOHSEE, jadi keduanya masih memakai glyph Heroicons (MIT, © Tailwind
 * Labs). Ditandai pada komentarnya masing-masing supaya terlihat mana
 * yang masih pinjaman ketika set berikutnya datang.
 *
 * ── evenodd ITU BUKAN HIASAN ──
 *
 * Bentuk yang punya lubang — gerigi roda gigi, jendela gedung, kaca
 * pembesar — menyimpan lubang itu sebagai sub-jalur di dalam jalur
 * yang sama. Tanpa fill-rule="evenodd" lubangnya ikut terisi, dan
 * hasilnya bukan galat melainkan gumpalan putih yang masih terlihat
 * seperti ikon sampai seseorang memperhatikannya.
 */
class IkonPadat
{
    /**
     * Kunci modul ke daftar jalurnya.
     *
     * Tiap jalur: ['d' => string, 'evenodd' => bool] dan boleh membawa
     * 'garis' (tebal stroke, bila digambar dengan garis) serta 'putar'
     * (nilai transform SVG).
     *
     * @var array<string, array<int, array{d: string, evenodd: bool,
     *      garis?: float, putar?: string}>>
     */
    public const PETA = [
        /* ic-laporan */
        'dasbor' => [
            ['d' => 'M4.4 13H5.6A1.6 1.6 0 0 1 7.2 14.6V19.6A1.6 1.6 0 0 1 5.6 21.2H4.4A1.6 1.6 0 0 1 2.8 19.6V14.6A1.6 1.6 0 0 1 4.4 13Z', 'evenodd' => false],
            ['d' => 'M11.4 7.6H12.6A1.6 1.6 0 0 1 14.2 9.2V19.6A1.6 1.6 0 0 1 12.6 21.2H11.4A1.6 1.6 0 0 1 9.8 19.6V9.2A1.6 1.6 0 0 1 11.4 7.6Z', 'evenodd' => false],
            ['d' => 'M18.4 2.8H19.6A1.6 1.6 0 0 1 21.2 4.4V19.6A1.6 1.6 0 0 1 19.6 21.2H18.4A1.6 1.6 0 0 1 16.8 19.6V4.4A1.6 1.6 0 0 1 18.4 2.8Z', 'evenodd' => false],
        ],
        /* ic-personalia */
        'personalia' => [
            ['d' => 'M5.2 7.2A3.8 3.8 0 1 0 12.8 7.2A3.8 3.8 0 1 0 5.2 7.2Z', 'evenodd' => false],
            ['d' => 'M1.8 20.6c0-3.7 3.2-5.9 7.2-5.9s7.2 2.2 7.2 5.9v1.2H1.8z', 'evenodd' => false],
            ['d' => 'M14.6 8.4A3 3 0 1 0 20.6 8.4A3 3 0 1 0 14.6 8.4Z', 'evenodd' => false],
            ['d' => 'M17.6 13.4c-.9 0-1.8.2-2.6.5a9.4 9.4 0 0 1 2.8 6.7v1.2h4.4v-3c0-3-2.1-5.4-4.6-5.4z', 'evenodd' => false],
        ],
        /* ic-learning */
        'lms' => [
            ['d' => 'M12 2.6 1.4 7.9 12 13.2l10.6-5.3z', 'evenodd' => false],
            ['d' => 'M5.8 11.9v4.5c0 2 2.8 3.6 6.2 3.6s6.2-1.6 6.2-3.6v-4.5L12 15z', 'evenodd' => false],
            ['d' => 'M21.45 9.4H21.45A0.95 0.95 0 0 1 22.4 10.35V15.85A0.95 0.95 0 0 1 21.45 16.8H21.45A0.95 0.95 0 0 1 20.5 15.85V10.35A0.95 0.95 0 0 1 21.45 9.4Z', 'evenodd' => false],
        ],
        /* ic-hse */
        'miners' => [
            ['d' => 'M12 3A7 7 0 0 0 5 10v4.1h14V10A7 7 0 0 0 12 3zm-.65 3.6h1.3v7.5h-1.3z', 'evenodd' => true],
            ['d' => 'M3.9 14H20.1A1.7 1.7 0 0 1 21.8 15.7V15.7A1.7 1.7 0 0 1 20.1 17.4H3.9A1.7 1.7 0 0 1 2.2 15.7V15.7A1.7 1.7 0 0 1 3.9 14Z', 'evenodd' => false],
        ],
        /* ic-karyawan */
        'hris' => [
            ['d' => 'M7.4 8.4V8a4.6 4.6 0 0 1 9.2 0v.4z', 'evenodd' => false],
            ['d' => 'M7.05 8.4H16.95A1.05 1.05 0 0 1 18 9.45V9.45A1.05 1.05 0 0 1 16.95 10.5H7.05A1.05 1.05 0 0 1 6 9.45V9.45A1.05 1.05 0 0 1 7.05 8.4Z', 'evenodd' => false],
            ['d' => 'M7.9 11.9h8.2v.5a4.1 4.1 0 0 1-8.2 0z', 'evenodd' => false],
            ['d' => 'M3.6 22v-1.4c0-2.6 3.8-4.1 8.4-4.1s8.4 1.5 8.4 4.1V22z', 'evenodd' => false],
        ],
        /* ic-investigasi */
        'investigasi' => [
            ['d' => 'M10.4 1.8a8.6 8.6 0 1 0 5.1 15.5l4.2 4.2 2.3-2.3-4.2-4.2A8.6 8.6 0 0 0 10.4 1.8zm0 3.2a5.4 5.4 0 1 1 0 10.8 5.4 5.4 0 0 1 0-10.8z', 'evenodd' => true],
        ],
        /* ic-kontraktor */
        'pjp' => [
            ['d' => 'M9.4 2.2h5.2a2.5 2.5 0 0 1 2.5 2.5v1.5h-2.7V5.3H9.6v.9H6.9V4.7a2.5 2.5 0 0 1 2.5-2.5z', 'evenodd' => false],
            ['d' => 'M4 7.4h16a2.5 2.5 0 0 1 2.5 2.5v9.2a2.5 2.5 0 0 1-2.5 2.5H4a2.5 2.5 0 0 1-2.5-2.5V9.9A2.5 2.5 0 0 1 4 7.4zm11.6 5-1.8-1.8-3.2 3.2-1.5-1.5-1.8 1.8 3.3 3.3z', 'evenodd' => true],
        ],
        /* ic-maturity */
        'tpkkp' => [
            ['d' => 'M12 1.8 3.5 5v6.6c0 5 3.6 9.5 8.5 10.6 4.9-1.1 8.5-5.6 8.5-10.6V5zm4.4 7.6-1.9-1.8-3.8 4-1.9-1.9-1.8 1.8 3.7 3.8z', 'evenodd' => true],
        ],
        /* ic-hazard */
        'hazrep' => [
            ['d' => 'M9.2 1.8h5.6a1.7 1.7 0 0 1 1.7 1.7v1.8a1 1 0 0 1-1 1H8.5a1 1 0 0 1-1-1V3.5a1.7 1.7 0 0 1 1.7-1.7z', 'evenodd' => false],
            ['d' => 'M6.2 4.4H5.8A2.4 2.4 0 0 0 3.4 6.8v12.8A2.4 2.4 0 0 0 5.8 22h12.4a2.4 2.4 0 0 0 2.4-2.4V6.8a2.4 2.4 0 0 0-2.4-2.4h-.4v1a2.4 2.4 0 0 1-2.4 2.4H8.6a2.4 2.4 0 0 1-2.4-2.4zm4.6 5h2.4v5.8h-2.4zm1.2 7.6a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3z', 'evenodd' => true],
        ],
        /* ShieldExclamation (Heroicons — belum ada padanannya di set EQOHSEE) */
        'ko' => [
            ['d' => 'M11.484 2.17a.75.75 0 0 1 1.032 0 11.209 11.209 0 0 0 7.877 3.08.75.75 0 0 1 .722.515 12.74 12.74 0 0 1 .635 3.985c0 5.942-4.064 10.933-9.563 12.348a.749.749 0 0 1-.374 0C6.314 20.683 2.25 15.692 2.25 9.75c0-1.39.223-2.73.635-3.985a.75.75 0 0 1 .722-.516l.143.001c2.996 0 5.718-1.17 7.734-3.08ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75ZM12 15a.75.75 0 0 0-.75.75v.008c0 .414.336.75.75.75h.008a.75.75 0 0 0 .75-.75v-.008a.75.75 0 0 0-.75-.75H12Z', 'evenodd' => true],
        ],
        /* ic-audit */
        'smkp' => [
            ['d' => 'M9.2 1.8h5.6a1.7 1.7 0 0 1 1.7 1.7v1.8a1 1 0 0 1-1 1H8.5a1 1 0 0 1-1-1V3.5a1.7 1.7 0 0 1 1.7-1.7z', 'evenodd' => false],
            ['d' => 'M6.2 4.4H5.8A2.4 2.4 0 0 0 3.4 6.8v12.8A2.4 2.4 0 0 0 5.8 22h12.4a2.4 2.4 0 0 0 2.4-2.4V6.8a2.4 2.4 0 0 0-2.4-2.4h-.4v1a2.4 2.4 0 0 1-2.4 2.4H8.6a2.4 2.4 0 0 1-2.4-2.4zm10.5 6.6-1.8-1.8-3.8 3.8-1.8-1.8-1.8 1.8 3.6 3.6z', 'evenodd' => true],
        ],
        /* ic-energy */
        'energi' => [
            ['d' => 'M14.2 1.8 5.2 13.4h5.4L9.2 22.2l9.6-12.1h-5.7z', 'evenodd' => false],
        ],
        /* ic-minerba */
        'konservasi' => [
            ['d' => 'M7.7 2.4h8.6l5 6.3-9.3 12.9L2.7 8.7zm-.3 4.3-1.6 2h12.4l-1.6-2z', 'evenodd' => true],
        ],
        /* ic-operations */
        'operasi' => [
            ['d' => 'M6.2 17.8 16 8M17.8 17.8 8 8', 'evenodd' => false, 'garis' => 2.5],
            ['d' => 'M15.4 4.6H19.4A1.2 1.2 0 0 1 20.6 5.8V7.4A1.2 1.2 0 0 1 19.4 8.6H15.4A1.2 1.2 0 0 1 14.2 7.4V5.8A1.2 1.2 0 0 1 15.4 4.6Z', 'evenodd' => false, 'putar' => 'rotate(45 17.4 6.6)'],
            ['d' => 'M2.4 2.4c4.9 0 8.9 2.6 10.6 6.6l-2.6 2.6C9.2 8.1 6.3 5.9 2.4 5.6z', 'evenodd' => false],
        ],
        /* ic-water */
        'air' => [
            ['d' => 'M12 1.8S4.2 9.1 4.2 14.4a7.8 7.8 0 0 0 15.6 0C19.8 9.1 12 1.8 12 1.8zm0 16.8a4.4 4.4 0 0 1-4.4-4.4H10a2 2 0 0 0 2 2z', 'evenodd' => true],
        ],
        /* ic-lingkungan */
        'lingkungan' => [
            ['d' => 'M3.8 20.2C3.8 10.6 10.6 3.8 20.2 3.8c0 9.6-6.8 16.4-16.4 16.4zm1.6-2.1L18.1 5.4l.9.9L6.3 19z', 'evenodd' => true],
        ],
        /* ic-drill */
        'peledakan' => [
            ['d' => 'M12 1.4l2.4 5.3 5.3-2.4-2.4 5.3 5.3 2.4-5.3 2.4 2.4 5.3-5.3-2.4L12 22.6l-2.4-5.3-5.3 2.4 2.4-5.3L1.4 12l5.3-2.4-2.4-5.3 5.3 2.4z', 'evenodd' => false],
        ],
        /* ic-hauling */
        'angkutan' => [
            ['d' => 'M1.6 6.9h9.7l3 6.5H1.6z', 'evenodd' => false],
            ['d' => 'M13.4 8.4h3.3l3.7 3.5v1.5h-7z', 'evenodd' => false],
            ['d' => 'M2.75 14.3H21.25A1.15 1.15 0 0 1 22.4 15.45V15.45A1.15 1.15 0 0 1 21.25 16.6H2.75A1.15 1.15 0 0 1 1.6 15.45V15.45A1.15 1.15 0 0 1 2.75 14.3Z', 'evenodd' => false],
            ['d' => 'M4.4 19.3A2.6 2.6 0 1 0 9.6 19.3A2.6 2.6 0 1 0 4.4 19.3Z', 'evenodd' => false],
            ['d' => 'M14.8 19.3A2.6 2.6 0 1 0 20 19.3A2.6 2.6 0 1 0 14.8 19.3Z', 'evenodd' => false],
        ],
        /* ic-biaya */
        'biaya' => [
            ['d' => 'M3.8 5.8A8.2 3.4 0 1 0 20.2 5.8A8.2 3.4 0 1 0 3.8 5.8Z', 'evenodd' => false],
            ['d' => 'M3.8 9.2v3.1c0 1.9 3.7 3.4 8.2 3.4s8.2-1.5 8.2-3.4V9.2c0 1.9-3.7 3.4-8.2 3.4S3.8 11.1 3.8 9.2z', 'evenodd' => false],
            ['d' => 'M3.8 15.5v3.1c0 1.9 3.7 3.4 8.2 3.4s8.2-1.5 8.2-3.4v-3.1c0 1.9-3.7 3.4-8.2 3.4s-8.2-1.5-8.2-3.4z', 'evenodd' => false],
        ],
        /* ic-permit */
        'izin' => [
            ['d' => 'M5.9 4.1A2.1 2.1 0 0 1 8 2h5.5v4.3a1.9 1.9 0 0 0 1.9 1.9H20v11.7A2.1 2.1 0 0 1 17.9 22H8a2.1 2.1 0 0 1-2.1-2.1zm3.3 7.5h7.2v2.2H9.2zm0 4.3h4.8v2.2H9.2z', 'evenodd' => true],
            ['d' => 'M15.2 2.4 19.6 6.8h-3.5a.9.9 0 0 1-.9-.9z', 'evenodd' => false],
        ],
        /* Square3Stack3D (Heroicons — belum ada padanannya di set EQOHSEE) */
        'geoteknik' => [
            ['d' => 'M11.644 1.59a.75.75 0 0 1 .712 0l9.75 5.25a.75.75 0 0 1 0 1.32l-9.75 5.25a.75.75 0 0 1-.712 0l-9.75-5.25a.75.75 0 0 1 0-1.32l9.75-5.25Z', 'evenodd' => false],
            ['d' => 'm3.265 10.602 7.668 4.129a2.25 2.25 0 0 0 2.134 0l7.668-4.13 1.37.739a.75.75 0 0 1 0 1.32l-9.75 5.25a.75.75 0 0 1-.71 0l-9.75-5.25a.75.75 0 0 1 0-1.32l1.37-.738Z', 'evenodd' => false],
            ['d' => 'm10.933 19.231-7.668-4.13-1.37.739a.75.75 0 0 0 0 1.32l9.75 5.25c.221.12.489.12.71 0l9.75-5.25a.75.75 0 0 0 0-1.32l-1.37-.738-7.668 4.13a2.25 2.25 0 0 1-2.134-.001Z', 'evenodd' => false],
        ],
        /* ic-maintenance */
        'maintenance' => [
            ['d' => 'M15 1.8a6.2 6.2 0 0 0-5.7 8.6l-6.6 6.5a2.1 2.1 0 0 0 0 3l1.4 1.4a2.1 2.1 0 0 0 3 0l6.5-6.6A6.2 6.2 0 0 0 22.2 9l-3.5 3.5-3.2-.9-.9-3.2L18.1 5A6.2 6.2 0 0 0 15 1.8z', 'evenodd' => false],
        ],
        /* ic-engineering */
        'meh' => [
            ['d' => 'M12 2.6 22.6 21.4H1.4zm0 5.6-5.8 9.9h11.6z', 'evenodd' => true],
        ],
        /* ic-gudang */
        'gudang' => [
            ['d' => 'M12 2.4 1.9 7.5V21.6h4.4v-8.4h11.4v8.4h4.4V7.5z', 'evenodd' => false],
            ['d' => 'M8.5 15.2h7v6.4h-7z', 'evenodd' => false],
        ],
        /* ic-iso */
        'dokumen' => [
            ['d' => 'M4.6 5.6v12.8A3.6 3.6 0 0 0 8.2 22h7.2v-2.4H8.2a1.2 1.2 0 0 1-1.2-1.2V5.6z', 'evenodd' => false],
            ['d' => 'M10.2 1.8h5.2l4.4 4.4v9.6a2.3 2.3 0 0 1-2.3 2.3h-7.3A2.3 2.3 0 0 1 8 15.8V4.1a2.3 2.3 0 0 1 2.2-2.3zm5.6 6.9-1.5-1.5-2.8 2.8-1.2-1.2-1.5 1.5 2.7 2.7z', 'evenodd' => true],
        ],
        /* ic-pembelian */
        'pembelian' => [
            ['d' => 'M2.6 2.6h2.7a1.6 1.6 0 0 1 1.6 1.3l.3 1.6h13a1.3 1.3 0 0 1 1.3 1.6l-1.7 6.5a2.2 2.2 0 0 1-2.1 1.6H9.5a2.2 2.2 0 0 1-2.2-1.8L5.2 5.4H2.6a1.4 1.4 0 0 1 0-2.8z', 'evenodd' => false],
            ['d' => 'M7.4 19.4A2.2 2.2 0 1 0 11.8 19.4A2.2 2.2 0 1 0 7.4 19.4Z', 'evenodd' => false],
            ['d' => 'M15.6 19.4A2.2 2.2 0 1 0 20 19.4A2.2 2.2 0 1 0 15.6 19.4Z', 'evenodd' => false],
        ],
        /* ic-pengaturan */
        'admin' => [
            ['d' => 'M13.7 1.6h-3.4l-.5 2.7c-.7.2-1.4.5-2 .8L5.6 3.4 3.4 5.6l1.7 2.2c-.3.6-.6 1.3-.8 2l-2.7.5v3.4l2.7.5c.2.7.5 1.4.8 2l-1.7 2.2 2.2 2.2 2.2-1.7c.6.3 1.3.6 2 .8l.5 2.7h3.4l.5-2.7c.7-.2 1.4-.5 2-.8l2.2 1.7 2.2-2.2-1.7-2.2c.3-.6.6-1.3.8-2l2.7-.5v-3.4l-2.7-.5c-.2-.7-.5-1.4-.8-2l1.7-2.2-2.2-2.2-2.2 1.7c-.6-.3-1.3-.6-2-.8zM12 8.1a3.9 3.9 0 1 1 0 7.8 3.9 3.9 0 0 1 0-7.8z', 'evenodd' => true],
        ],
    ];

    /**
     * Jalur padat untuk satu modul, atau [] bila belum ada.
     *
     * Memulangkan larik kosong dan bukan null supaya pemanggilnya dapat
     * langsung mengulanginya. Modul yang belum punya glyph padat
     * menggambar ubin berwarna tanpa isi — kurang bagus, tetapi tetap
     * utuh; itu lebih baik daripada halaman yang berhenti.
     *
     * @return array<int, array{d: string, evenodd: bool, garis?: float, putar?: string}>
     */
    public static function untuk(?string $modul): array
    {
        return $modul === null ? [] : (self::PETA[$modul] ?? []);
    }

    /** Modul yang belum punya glyph padat. Dipakai uji penjagaan. */
    public static function belumPunya(): array
    {
        return array_values(array_diff(array_keys(Menu::all()), array_keys(self::PETA)));
    }
}

/**
 * Glyph PADAT untuk ubin berdimensi di halaman depan.
 *
 * Kembaran sisi-peramban dari App\Support\IkonPadat, dan sengaja
 * terpisah darinya: yang di PHP dikunci pada kunci MODUL — satu modul,
 * satu lambang, di mana pun ia muncul — sedangkan yang di sini dinamai
 * menurut maknanya ('gembok', 'globe', 'orang') karena dipakai kartu
 * bahan jualan yang tidak mewakili modul mana pun.
 *
 * Menyatukan keduanya akan memaksa salah satunya berbohong: entah
 * kartu "Keamanan data" harus mengaku sebagai sebuah modul, atau kunci
 * modul harus ikut dinamai menurut bentuk gambarnya — dan yang kedua
 * itulah yang membuat ikon modul berubah diam-diam saat seseorang
 * mengganti gambarnya.
 *
 * Geometrinya dari set ikon EQOHSEE v1.0, kanvas 0 0 24 24. Tiga nama
 * — gembok, kisi, globe — belum punya bentuk di set itu dan masih
 * memakai Heroicons (MIT, (c) Tailwind Labs); ditandai pada
 * komentarnya masing-masing.
 */
export type JalurPadat = {
  d: string;
  evenodd?: boolean;
  /** Tebal garis, bila bagian ini digambar dengan garis dan bukan diisi. */
  garis?: number;
  /** Nilai transform SVG, mis. 'rotate(45 17.4 6.6)'. */
  putar?: string;
};

export const IKON_PADAT: Record<string, JalurPadat[]> = {
  /* ic-energy */
  bolt: [
    { d: 'M14.2 1.8 5.2 13.4h5.4L9.2 22.2l9.6-12.1h-5.7z' },
  ],
  /* ic-audit */
  gem: [
    { d: 'M9.2 1.8h5.6a1.7 1.7 0 0 1 1.7 1.7v1.8a1 1 0 0 1-1 1H8.5a1 1 0 0 1-1-1V3.5a1.7 1.7 0 0 1 1.7-1.7z' },
    { d: 'M6.2 4.4H5.8A2.4 2.4 0 0 0 3.4 6.8v12.8A2.4 2.4 0 0 0 5.8 22h12.4a2.4 2.4 0 0 0 2.4-2.4V6.8a2.4 2.4 0 0 0-2.4-2.4h-.4v1a2.4 2.4 0 0 1-2.4 2.4H8.6a2.4 2.4 0 0 1-2.4-2.4zm10.5 6.6-1.8-1.8-3.8 3.8-1.8-1.8-1.8 1.8 3.6 3.6z', evenodd: true },
  ],
  /* ic-kesehatan */
  health: [
    { d: 'M9.6 1.8h4.8a1.8 1.8 0 0 1 1.8 1.8v1.2h4A2.4 2.4 0 0 1 22.6 7.2v11.4a2.4 2.4 0 0 1-2.4 2.4H3.8a2.4 2.4 0 0 1-2.4-2.4V7.2a2.4 2.4 0 0 1 2.4-2.4h4V3.6a1.8 1.8 0 0 1 1.8-1.8zm1.2 3h2.4v-1h-2.4zm-.2 4.6v2.4H8.2v2.9h2.4v2.4h2.8v-2.4h2.4v-2.9h-2.4V9.4z', evenodd: true },
  ],
  /* ic-water */
  droplet: [
    { d: 'M12 1.8S4.2 9.1 4.2 14.4a7.8 7.8 0 0 0 15.6 0C19.8 9.1 12 1.8 12 1.8zm0 16.8a4.4 4.4 0 0 1-4.4-4.4H10a2 2 0 0 0 2 2z', evenodd: true },
  ],
  /* ic-maturity */
  shield: [
    { d: 'M12 1.8 3.5 5v6.6c0 5 3.6 9.5 8.5 10.6 4.9-1.1 8.5-5.6 8.5-10.6V5zm4.4 7.6-1.9-1.8-3.8 4-1.9-1.9-1.8 1.8 3.7 3.8z', evenodd: true },
  ],
  /* ic-lingkungan */
  leaf: [
    { d: 'M3.8 20.2C3.8 10.6 10.6 3.8 20.2 3.8c0 9.6-6.8 16.4-16.4 16.4zm1.6-2.1L18.1 5.4l.9.9L6.3 19z', evenodd: true },
  ],
  /* ic-minerba */
  layers: [
    { d: 'M7.7 2.4h8.6l5 6.3-9.3 12.9L2.7 8.7zm-.3 4.3-1.6 2h12.4l-1.6-2z', evenodd: true },
  ],
  /* ic-pengaturan */
  gear: [
    { d: 'M13.7 1.6h-3.4l-.5 2.7c-.7.2-1.4.5-2 .8L5.6 3.4 3.4 5.6l1.7 2.2c-.3.6-.6 1.3-.8 2l-2.7.5v3.4l2.7.5c.2.7.5 1.4.8 2l-1.7 2.2 2.2 2.2 2.2-1.7c.6.3 1.3.6 2 .8l.5 2.7h3.4l.5-2.7c.7-.2 1.4-.5 2-.8l2.2 1.7 2.2-2.2-1.7-2.2c.3-.6.6-1.3.8-2l2.7-.5v-3.4l-2.7-.5c-.2-.7-.5-1.4-.8-2l1.7-2.2-2.2-2.2-2.2 1.7c-.6-.3-1.3-.6-2-.8zM12 8.1a3.9 3.9 0 1 1 0 7.8 3.9 3.9 0 0 1 0-7.8z', evenodd: true },
  ],
  /* ic-iso */
  dokumen: [
    { d: 'M4.6 5.6v12.8A3.6 3.6 0 0 0 8.2 22h7.2v-2.4H8.2a1.2 1.2 0 0 1-1.2-1.2V5.6z' },
    { d: 'M10.2 1.8h5.2l4.4 4.4v9.6a2.3 2.3 0 0 1-2.3 2.3h-7.3A2.3 2.3 0 0 1 8 15.8V4.1a2.3 2.3 0 0 1 2.2-2.3zm5.6 6.9-1.5-1.5-2.8 2.8-1.2-1.2-1.5 1.5 2.7 2.7z', evenodd: true },
  ],
  /* ic-personalia */
  orang: [
    { d: 'M5.2 7.2A3.8 3.8 0 1 0 12.8 7.2A3.8 3.8 0 1 0 5.2 7.2Z' },
    { d: 'M1.8 20.6c0-3.7 3.2-5.9 7.2-5.9s7.2 2.2 7.2 5.9v1.2H1.8z' },
    { d: 'M14.6 8.4A3 3 0 1 0 20.6 8.4A3 3 0 1 0 14.6 8.4Z' },
    { d: 'M17.6 13.4c-.9 0-1.8.2-2.6.5a9.4 9.4 0 0 1 2.8 6.7v1.2h4.4v-3c0-3-2.1-5.4-4.6-5.4z' },
  ],
  /* LockClosed (Heroicons — belum ada padanannya di set EQOHSEE) */
  gembok: [
    { d: 'M12 1.5a5.25 5.25 0 0 0-5.25 5.25v3a3 3 0 0 0-3 3v6.75a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3v-6.75a3 3 0 0 0-3-3v-3c0-2.9-2.35-5.25-5.25-5.25Zm3.75 8.25v-3a3.75 3.75 0 1 0-7.5 0v3h7.5Z', evenodd: true },
  ],
  /* Squares2X2 (Heroicons — belum ada padanannya di set EQOHSEE) */
  kisi: [
    { d: 'M3 6a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3V6ZM3 15.75a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-2.25Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3v-2.25Z', evenodd: true },
  ],
  /* ic-permit */
  regulasi: [
    { d: 'M5.9 4.1A2.1 2.1 0 0 1 8 2h5.5v4.3a1.9 1.9 0 0 0 1.9 1.9H20v11.7A2.1 2.1 0 0 1 17.9 22H8a2.1 2.1 0 0 1-2.1-2.1zm3.3 7.5h7.2v2.2H9.2zm0 4.3h4.8v2.2H9.2z', evenodd: true },
    { d: 'M15.2 2.4 19.6 6.8h-3.5a.9.9 0 0 1-.9-.9z' },
  ],
  /* GlobeAlt (Heroicons — belum ada padanannya di set EQOHSEE) */
  globe: [
    { d: 'M21.721 12.752a9.711 9.711 0 0 0-.945-5.003 12.754 12.754 0 0 1-4.339 2.708 18.991 18.991 0 0 1-.214 4.772 17.165 17.165 0 0 0 5.498-2.477ZM14.634 15.55a17.324 17.324 0 0 0 .332-4.647c-.952.227-1.945.347-2.966.347-1.021 0-2.014-.12-2.966-.347a17.515 17.515 0 0 0 .332 4.647 17.385 17.385 0 0 0 5.268 0ZM9.772 17.119a18.963 18.963 0 0 0 4.456 0A17.182 17.182 0 0 1 12 21.724a17.18 17.18 0 0 1-2.228-4.605ZM7.777 15.23a18.87 18.87 0 0 1-.214-4.774 12.753 12.753 0 0 1-4.34-2.708 9.711 9.711 0 0 0-.944 5.004 17.165 17.165 0 0 0 5.498 2.477ZM21.356 14.752a9.765 9.765 0 0 1-7.478 6.817 18.64 18.64 0 0 0 1.988-4.718 18.627 18.627 0 0 0 5.49-2.098ZM2.644 14.752c1.682.971 3.53 1.688 5.49 2.099a18.64 18.64 0 0 0 1.988 4.718 9.765 9.765 0 0 1-7.478-6.816ZM13.878 2.43a9.755 9.755 0 0 1 6.116 3.986 11.267 11.267 0 0 1-3.746 2.504 18.63 18.63 0 0 0-2.37-6.49ZM12 2.276a17.152 17.152 0 0 1 2.805 7.121c-.897.23-1.837.353-2.805.353-.968 0-1.908-.122-2.805-.353A17.151 17.151 0 0 1 12 2.276ZM10.122 2.43a18.629 18.629 0 0 0-2.37 6.49 11.266 11.266 0 0 1-3.746-2.504 9.754 9.754 0 0 1 6.116-3.985Z' },
  ],
};

/** Jalur untuk satu nama, atau [] bila belum dipetakan. */
export function ikonPadat(nama: string | null | undefined): JalurPadat[] {
  return (nama && IKON_PADAT[nama]) || [];
}

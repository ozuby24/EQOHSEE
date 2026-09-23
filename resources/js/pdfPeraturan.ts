/**
 * Membaca PDF peraturan DI PERAMBAN, bukan di server.
 *
 * Sebelumnya PDF-nya diunggah utuh lalu dibaca server. Pada jaringan
 * lapangan yang lambat, unggahan beberapa megabita itu putus di tengah —
 * nginx memutus badan permintaan yang diam lebih dari 15 detik — dan yang
 * terlihat hanya "Tidak tersambung ke server". PDF hasil pindaian lebih
 * buruk lagi: tiap dua halaman yang dibaca, SELURUH berkasnya dikirim ulang.
 *
 * Di sini teks tiap halaman diambil di peramban, dan yang dikirim ke
 * server hanya teksnya — puluhan kilobita. Halaman yang berupa gambar
 * digambar ke kanvas dan dikirim sebagai JPEG satu per satu, bukan
 * sebagai PDF utuh.
 *
 * pdf.js dimuat hanya saat dipakai: ±1,7 MB yang tidak perlu ikut
 * dimuat pada halaman lain. pdf.js versi ini tidak memakai eval, jadi
 * CSP aplikasi tidak perlu dilonggarkan.
 */
import type { PDFDocumentProxy } from 'pdfjs-dist';

/** Halaman dengan teks sependek ini dianggap gambar (hasil pindaian). */
const HALAMAN_KOSONG = 40;

/* Build "legacy": pdf.js versi ini memakai fitur JavaScript yang baru
   saja ada (mis. Map.getOrInsertComputed). Build biasanya tetap dapat
   mengambil teks di peramban lama — tetapi GAGAL menggambar halaman,
   sehingga halaman pindaian tidak pernah terbaca di ponsel lapangan
   dan aplikasi Android. Build legacy membawa penggantinya sendiri. */
let pustaka: Promise<{ p: typeof import('pdfjs-dist'); pengurai: string }> | null = null;

async function pdfjs() {
  pustaka ??= (async () => {
    const [p, pekerja] = await Promise.all([
      import('pdfjs-dist/legacy/build/pdf.mjs'),
      import('pdfjs-dist/legacy/build/pdf.worker.min.mjs?url'),
    ]);
    const alamatPekerja = new URL(pekerja.default, location.href);
    p.GlobalWorkerOptions.workerSrc = alamatPekerja.href;

    /* Pengurai JBIG2/JPEG 2000 — halaman hasil pindaian. Disalin ke
       samping pekerjanya saat build (vite.config.js); saat pengembangan
       diambil langsung dari paketnya. Harus alamat utuh: pekerjanya
       memuatnya dari alamatnya sendiri, bukan dari alamat halaman. */
    const pengurai = new URL(import.meta.env.DEV ? '../../wasm/' : `pdfjs-${p.version}/`, alamatPekerja).href;

    return { p, pengurai };
  })();
  return pustaka;
}

export interface PdfTerbaca {
  dokumen: PDFDocumentProxy;
  halaman: string[];
  /** Nomor halaman (mulai 1) yang tidak memuat teks. */
  gambar: number[];
}

/** Baris-baris teks satu halaman, dari urutan butir teks pdf.js. */
function susunTeks(items: Array<{ str?: string; hasEOL?: boolean; transform?: number[] }>): string {
  let out = '';
  let yLalu: number | null = null;

  for (const it of items) {
    if (typeof it.str !== 'string') continue;
    const y = it.transform?.[5] ?? null;

    /* Pindah baris bila posisi tegaknya berubah, walau pdf.js tidak
       menandainya: sebagian PDF tidak pernah memberi hasEOL. */
    if (yLalu !== null && y !== null && Math.abs(y - yLalu) > 2 && out !== '' && !out.endsWith('\n')) out += '\n';

    out += it.str;
    if (it.hasEOL) out += '\n';
    if (y !== null) yLalu = y;
  }

  return out.replace(/[ \t]+\n/g, '\n').trim();
}

export async function bacaPdf(
  berkas: File,
  kemajuan?: (selesai: number, total: number) => void,
): Promise<PdfTerbaca> {
  const { p, pengurai } = await pdfjs();
  const data = new Uint8Array(await berkas.arrayBuffer());

  const dokumen = await p.getDocument({
    data,
    wasmUrl: pengurai,
    useSystemFonts: true,
    disableFontFace: true,
    verbosity: 0,
  }).promise;

  const halaman: string[] = [];
  const gambar: number[] = [];

  for (let n = 1; n <= dokumen.numPages; n++) {
    const hal = await dokumen.getPage(n);
    const isi = await hal.getTextContent();
    const teks = susunTeks(isi.items as Array<{ str?: string; hasEOL?: boolean; transform?: number[] }>);
    halaman.push(teks);
    if (teks.replace(/\s+/g, '').length < HALAMAN_KOSONG) gambar.push(n);
    hal.cleanup();
    kemajuan?.(n, dokumen.numPages);
  }

  return { dokumen, halaman, gambar };
}

/** Lepaskan PDF-nya beserta pekerjanya. */
export async function tutupPdf(dokumen: PDFDocumentProxy): Promise<void> {
  await dokumen.loadingTask.destroy().catch(() => {});
}

/**
 * Satu halaman sebagai JPEG base64 (tanpa awalan data:), untuk dibaca
 * analisis otomatis. Lebar ±1400 px: cukup tajam untuk huruf naskah
 * peraturan, dan tetap di bawah ±600 kB.
 */
export async function gambarHalaman(dokumen: PDFDocumentProxy, no: number): Promise<string> {
  const hal = await dokumen.getPage(no);
  const dasar = hal.getViewport({ scale: 1 });
  const skala = Math.min(2.5, 1400 / dasar.width);
  const tampak = hal.getViewport({ scale: skala });

  const kanvas = document.createElement('canvas');
  kanvas.width = Math.ceil(tampak.width);
  kanvas.height = Math.ceil(tampak.height);
  const ctx = kanvas.getContext('2d')!;
  ctx.fillStyle = '#fff';
  ctx.fillRect(0, 0, kanvas.width, kanvas.height);

  await hal.render({ canvas: kanvas, canvasContext: ctx, viewport: tampak }).promise;
  hal.cleanup();

  const url = kanvas.toDataURL('image/jpeg', 0.78);
  kanvas.width = kanvas.height = 0;

  return url.slice(url.indexOf(',') + 1);
}

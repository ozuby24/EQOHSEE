import { reactive } from 'vue';

/**
 * Kotak keluar mode lapangan — kiriman yang menunggu sinyal.
 *
 * Laporan bahaya dan P2H disusun di muka tambang, dan di sana sinyal
 * datang dan pergi. Kiriman yang gagal karena jaringan TIDAK dibuang:
 * ia disimpan di IndexedDB perangkat — isian dan fotonya sekaligus — lalu
 * dikirim ulang begitu perangkat tersambung lagi, tanpa orangnya harus
 * ingat.
 *
 * ── TIGA KEMUNGKINAN SATU KIRIMAN ──
 *
 *   terkirim  → server menjawab 2xx; kirimannya dihapus dari perangkat.
 *   tertahan  → jaringan putus, server 5xx, atau sesi habis; disimpan
 *               dan dicoba lagi. Tidak ada yang hilang.
 *   ditolak   → server menjawab isiannya salah (422, 403, 413). Mencoba
 *               lagi tidak akan mengubah jawabannya, jadi kirimannya
 *               ditandai dan ditampilkan di Tugas beserta alasannya.
 *
 * ── KIRIMAN YANG TERULANG ──
 *
 * Setiap kiriman membawa klien_id yang dibuat saat disusun. Bila sinyal
 * putus tepat ketika jawaban server sedang dalam perjalanan, perangkat
 * tidak tahu kirimannya sudah tercatat dan akan mengirim lagi — server
 * mengenali klien_id yang sama dan menjawab dengan catatan yang sudah
 * ada, bukan membuat salinan kedua.
 *
 * ── MILIK SIAPA ──
 *
 * Tiap kiriman menyimpan id penggunanya, dan hanya dikirim ketika orang
 * yang sama sedang masuk. Ponsel site sering dipakai bergantian; tanpa
 * ini laporan orang pertama akan terkirim atas nama orang kedua.
 */

export type Pasangan = [string, string];

export type Kiriman = {
  klien_id: string;
  jenis: 'lapor' | 'p2h';
  judul: string;
  url: string;
  isian: Pasangan[];
  foto: { nama: string; tipe: string; data: Blob }[];
  pengguna: number;
  dibuat: string;
  percobaan: number;
  galat: string | null;
};

export type HasilKirim =
  | { status: 'terkirim'; url: string | null; data: any }
  | { status: 'tertahan'; sebab: string }
  | { status: 'ditolak'; sebab: string; errors: Record<string, string> };

/** Keadaan yang dibaca layar: bilah luring, lencana, daftar Tugas. */
export const jaringan = reactive({
  daring: typeof navigator === 'undefined' ? true : navigator.onLine,
  menunggu: 0,
  gagal: 0,
  mengirim: false,
  daftar: [] as Omit<Kiriman, 'foto' | 'isian'>[],
});

const NAMA_DB = 'eqohsee-lapangan';
let db: Promise<IDBDatabase> | null = null;

function buka(): Promise<IDBDatabase> {
  db ??= new Promise((ok, gagal) => {
    const r = indexedDB.open(NAMA_DB, 1);
    r.onupgradeneeded = () => {
      const d = r.result;
      if (!d.objectStoreNames.contains('keluar')) d.createObjectStore('keluar', { keyPath: 'klien_id' });
      if (!d.objectStoreNames.contains('draf')) d.createObjectStore('draf', { keyPath: 'kunci' });
    };
    r.onsuccess = () => ok(r.result);
    r.onerror = () => { db = null; gagal(r.error); };
  });
  return db;
}

async function lakukan<T>(simpanan: string, mode: IDBTransactionMode, kerja: (s: IDBObjectStore) => IDBRequest | void): Promise<T> {
  const d = await buka();
  return new Promise<T>((ok, gagal) => {
    const t = d.transaction(simpanan, mode);
    const r = kerja(t.objectStore(simpanan));
    t.oncomplete = () => ok((r ? r.result : undefined) as T);
    t.onerror = () => gagal(t.error);
    t.onabort = () => gagal(t.error);
  });
}

/** Penanda kiriman: unik per perangkat, aman untuk aturan server [A-Za-z0-9_-]. */
export function klienId(): string {
  const c = (globalThis.crypto as Crypto | undefined);
  if (c?.randomUUID) return c.randomUUID();
  const acak = new Uint8Array(16);
  c?.getRandomValues?.(acak);
  return Date.now().toString(36) + '-' + Array.from(acak, (b) => (b % 36).toString(36)).join('');
}

/* ═══════════ kotak keluar ═══════════ */

export async function simpanKiriman(k: Kiriman): Promise<void> {
  await lakukan('keluar', 'readwrite', (s) => s.put(k));
  await hitungUlang(k.pengguna);
}

export async function hapusKiriman(id: string, pengguna: number): Promise<void> {
  await lakukan('keluar', 'readwrite', (s) => s.delete(id));
  await hitungUlang(pengguna);
}

async function semuaKiriman(pengguna: number): Promise<Kiriman[]> {
  const semua = await lakukan<Kiriman[]>('keluar', 'readonly', (s) => s.getAll());
  return (semua ?? []).filter((k) => k.pengguna === pengguna).sort((a, b) => a.dibuat.localeCompare(b.dibuat));
}

export async function hitungUlang(pengguna: number): Promise<void> {
  try {
    const k = await semuaKiriman(pengguna);
    jaringan.menunggu = k.filter((x) => !x.galat).length;
    jaringan.gagal = k.filter((x) => x.galat).length;
    jaringan.daftar = k.map(({ foto: _f, isian: _i, ...sisa }) => sisa);
  } catch {
    /* IndexedDB dapat ditolak (mode penyamaran di sebagian peramban).
       Tanpa penyimpanan, yang tersisa adalah kirim langsung. */
  }
}

/** Kirim ulang satu kiriman yang pernah ditolak — setelah orangnya membacanya. */
export async function cobaLagi(id: string, pengguna: number): Promise<HasilKirim | null> {
  const k = (await semuaKiriman(pengguna)).find((x) => x.klien_id === id);
  if (!k) return null;
  k.galat = null;
  await simpanKiriman(k);
  return kirimSatu(k);
}

/* ═══════════ pengiriman ═══════════ */

function tokenXsrf(): string {
  const m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
  return m ? decodeURIComponent(m[1]) : '';
}

/** Kirim satu kiriman ke server. Tidak mengubah kotak keluar. */
export async function kirim(k: Kiriman): Promise<HasilKirim> {
  const data = new FormData();
  data.append('klien_id', k.klien_id);
  data.append('disusun_pada', k.dibuat);
  for (const [kunci, nilai] of k.isian) data.append(kunci, nilai);
  k.foto.forEach((f) => data.append('foto[]', new File([f.data], f.nama, { type: f.tipe })));

  let r: Response;
  try {
    r = await fetch(k.url, {
      method: 'POST',
      body: data,
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': tokenXsrf(),
      },
    });
  } catch {
    return { status: 'tertahan', sebab: 'Tidak ada sinyal.' };
  }

  let j: any = null;
  try { j = await r.json(); } catch { /* HTML dari nginx atau halaman galat */ }

  /* Terkirim HANYA bila server menjawab dengan tanda terimanya sendiri.
     Status 200 saja tidak cukup: sesi yang habis dialihkan ke halaman
     masuk, fetch mengikutinya, dan halaman masuk itu berstatus 200 —
     laporan yang tidak pernah tercatat akan terhapus dari perangkat. */
  if (r.ok && j?.ok === true) return { status: 'terkirim', url: j?.url ?? null, data: j };
  if (r.ok || r.redirected) return { status: 'tertahan', sebab: 'Sesi berakhir — masuk lagi untuk mengirim.' };

  if (r.status === 422) {
    const errors: Record<string, string> = {};
    for (const [kunci, pesan] of Object.entries(j?.errors ?? {})) {
      errors[kunci] = Array.isArray(pesan) ? String(pesan[0]) : String(pesan);
    }
    return { status: 'ditolak', sebab: Object.values(errors)[0] ?? j?.message ?? 'Isian ditolak server.', errors };
  }
  if (r.status === 413) return { status: 'ditolak', sebab: 'Foto terlalu besar untuk dikirim.', errors: {} };
  if (r.status === 403 || r.status === 404) {
    return { status: 'ditolak', sebab: j?.message || 'Anda tidak berwenang mengirim ini.', errors: {} };
  }
  if (r.status === 401 || r.status === 419) {
    return { status: 'tertahan', sebab: 'Sesi berakhir — masuk lagi untuk mengirim.' };
  }

  return { status: 'tertahan', sebab: `Server sedang bermasalah (${r.status}).` };
}

/** Kirim dan catat hasilnya di kotak keluar. */
async function kirimSatu(k: Kiriman): Promise<HasilKirim> {
  const h = await kirim(k);

  if (h.status === 'terkirim') {
    await hapusKiriman(k.klien_id, k.pengguna);
  } else if (h.status === 'ditolak') {
    await simpanKiriman({ ...k, galat: h.sebab, percobaan: k.percobaan + 1 });
  } else {
    await simpanKiriman({ ...k, percobaan: k.percobaan + 1 });
  }

  return h;
}

/**
 * Kirim seluruh kiriman tertunda milik pengguna ini, satu per satu.
 *
 * Berurutan, bukan serentak: di sinyal yang tipis, enam unggahan foto
 * bersamaan membuat keenamnya habis waktu; satu per satu, yang pertama
 * sampai.
 */
export async function kirimSemua(pengguna: number): Promise<number> {
  if (jaringan.mengirim || !navigator.onLine) return 0;
  jaringan.mengirim = true;
  let terkirim = 0;

  try {
    for (const k of await semuaKiriman(pengguna)) {
      if (k.galat) continue;
      const h = await kirimSatu(k);
      if (h.status === 'terkirim') terkirim++;
      if (h.status === 'tertahan') break;
    }
  } catch {
    /* penyimpanan tidak tersedia — tidak ada yang bisa dikirim */
  } finally {
    jaringan.mengirim = false;
    await hitungUlang(pengguna);
  }

  return terkirim;
}

let terpasang = false;

/**
 * Pasang pengirim otomatis: saat sinyal kembali, saat aplikasi dibuka
 * lagi, dan tiap menit selama masih ada yang menunggu.
 */
export function pasangSinkron(pengguna: number, sesudahKirim?: (n: number) => void): void {
  jaringan.daring = navigator.onLine;
  void hitungUlang(pengguna).then(() => kirimSemua(pengguna).then((n) => n && sesudahKirim?.(n)));

  if (terpasang) return;
  terpasang = true;

  const coba = () => kirimSemua(pengguna).then((n) => n && sesudahKirim?.(n));

  window.addEventListener('online', () => { jaringan.daring = true; void coba(); });
  window.addEventListener('offline', () => { jaringan.daring = false; });
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && navigator.onLine) void coba();
  });
  window.setInterval(() => { if (jaringan.menunggu > 0 && navigator.onLine) void coba(); }, 60_000);
}

/* ═══════════ draf ═══════════ */

type Draf<T> = { kunci: string; nilai: T; disimpan: string };

export async function simpanDraf<T>(kunci: string, nilai: T): Promise<void> {
  try {
    await lakukan('draf', 'readwrite', (s) => s.put({ kunci, nilai, disimpan: new Date().toISOString() } as Draf<T>));
  } catch { /* tanpa penyimpanan, draf hidup selama halaman terbuka */ }
}

export async function ambilDraf<T>(kunci: string): Promise<Draf<T> | null> {
  try {
    return (await lakukan<Draf<T> | undefined>('draf', 'readonly', (s) => s.get(kunci))) ?? null;
  } catch {
    return null;
  }
}

export async function hapusDraf(kunci: string): Promise<void> {
  try { await lakukan('draf', 'readwrite', (s) => s.delete(kunci)); } catch { /* abaikan */ }
}

/* ═══════════ foto ═══════════ */

/**
 * Perkecil foto kamera sebelum disimpan dan dikirim.
 *
 * Foto ponsel 12 MP berukuran 4–8 MB — melewati batas 4 MB server, dan
 * mengirimnya di satu bar sinyal hampir pasti habis waktu. Sisi terpanjang
 * 1600 px sudah cukup untuk membaca retakan tanggul atau kebocoran oli.
 * Bila peramban tidak sanggup mengolahnya, berkas aslinya yang dipakai.
 */
export async function perkecilFoto(berkas: File, sisi = 1600, mutu = 0.82): Promise<{ nama: string; tipe: string; data: Blob }> {
  const asli = { nama: berkas.name || 'foto.jpg', tipe: berkas.type || 'image/jpeg', data: berkas as Blob };

  try {
    const bmp = await createImageBitmap(berkas);
    const skala = Math.min(1, sisi / Math.max(bmp.width, bmp.height));
    if (skala === 1 && berkas.size < 900_000) { bmp.close?.(); return asli; }

    const kanvas = document.createElement('canvas');
    kanvas.width = Math.round(bmp.width * skala);
    kanvas.height = Math.round(bmp.height * skala);
    kanvas.getContext('2d')!.drawImage(bmp, 0, 0, kanvas.width, kanvas.height);
    bmp.close?.();

    const blob = await new Promise<Blob | null>((ok) => kanvas.toBlob(ok, 'image/jpeg', mutu));
    if (!blob) return asli;

    return { nama: (berkas.name || 'foto').replace(/\.[a-z0-9]+$/i, '') + '.jpg', tipe: 'image/jpeg', data: blob };
  } catch {
    return asli;
  }
}

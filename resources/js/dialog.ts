/**
 * Pengganti `confirm()` dan `prompt()` yang berjalan di dalam halaman.
 *
 * Kedua dialog bawaan peramban tidak dapat diandalkan di tempat aplikasi
 * ini justru dipakai. Pada webview ponsel dan tablet — aplikasi induk
 * yang tidak memasang penangan `onJsConfirm`/`onJsPrompt` — keduanya
 * langsung memulangkan `false` dan `null` tanpa menampilkan apa pun.
 * Kode pemanggilnya membaca itu sebagai "pengguna menekan Batal", lalu
 * berhenti diam-diam.
 *
 * Tidak ada galat, tidak ada pesan, tidak ada yang terjadi. Dari sisi
 * pemakainya fiturnya sekadar mati — dan hanya di perangkat itu, jadi ia
 * tidak pernah terlihat oleh siapa pun yang memeriksanya di layar lebar.
 *
 * Bentuk pemanggilannya sengaja dibuat semirip mungkin dengan yang
 * digantikannya, supaya penggantian di puluhan berkas menjadi perubahan
 * kecil yang dapat dibaca sekilas, bukan penulisan ulang tiap penangan:
 *
 *     if (!confirm('Hapus?')) return;
 *     if (!await tanya('Hapus?')) return;
 *
 *     const a = prompt('Alasan:');
 *     const a = await minta('Alasan:');
 *
 * Keduanya memulangkan janji. `tanya` memulangkan boolean; `minta`
 * memulangkan teksnya, atau `null` bila dibatalkan — sama seperti
 * `prompt`, sehingga penjagaan `if (a === null) return;` yang sudah ada
 * tetap benar.
 */
import { reactive } from 'vue';

export type JenisIsian = 'teks' | 'panjang' | 'tanggal' | 'angka';

export interface OpsiTanya {
  judul: string;
  pesan?: string;
  labelAksi?: string;
  nada?: 'utama' | 'bahaya';
  /** Pengguna harus mengetik ulang teks ini sebelum tombolnya hidup. */
  tegasNama?: string | null;
}

export interface OpsiMinta extends OpsiTanya {
  label: string;
  jenis?: JenisIsian;
  nilai?: string;
  /** Bawaannya wajib: alasan penolakan yang kosong bukan alasan. */
  wajib?: boolean;
  /** Panjang minimum, disamakan dengan aturan di server. */
  min?: number;
  /** Kolom hanya untuk disalin. */
  baca?: boolean;
}

interface Keadaan {
  terbuka: boolean;
  judul: string;
  pesan: string;
  tegasNama: string | null;
  isianLabel: string | null;
  isianJenis: JenisIsian;
  isianNilai: string;
  isianWajib: boolean;
  isianMin: number;
  isianBaca: boolean;
  labelAksi: string;
  nada: 'utama' | 'bahaya';
  sibuk: boolean;
}

const KOSONG: Omit<Keadaan, 'terbuka'> = {
  judul: '',
  pesan: '',
  tegasNama: null,
  isianLabel: null,
  isianJenis: 'teks',
  isianNilai: '',
  isianWajib: false,
  isianMin: 0,
  isianBaca: false,
  labelAksi: 'Lanjutkan',
  nada: 'utama',
  sibuk: false,
};

export function useDialog() {
  const dialog = reactive<Keadaan>({ terbuka: false, ...KOSONG });

  /* Satu dialog pada satu waktu. Janji yang tertunda disimpan di sini
     supaya `batal` maupun `lanjut` selalu menyelesaikannya — janji yang
     tidak pernah selesai membuat `await` di pemanggilnya menggantung
     tanpa jejak. */
  let selesaikan: ((n: string | null) => void) | null = null;

  function buka(o: OpsiMinta | OpsiTanya, isian: Partial<Keadaan>): Promise<string | null> {
    /* Bila masih ada yang tertunda, batalkan lebih dulu — dua dialog
       yang saling menimpa meninggalkan satu janji yatim. */
    selesaikan?.(null);

    Object.assign(dialog, KOSONG, isian, {
      terbuka: true,
      judul: o.judul,
      pesan: o.pesan ?? '',
      labelAksi: o.labelAksi ?? (isian.isianLabel ? 'Kirim' : 'Lanjutkan'),
      nada: o.nada ?? 'utama',
      tegasNama: o.tegasNama ?? null,
    });

    return new Promise((res) => { selesaikan = res; });
  }

  /** Pengganti `confirm()`. */
  async function tanya(o: OpsiTanya | string): Promise<boolean> {
    const opsi = typeof o === 'string' ? { judul: o } : o;
    return (await buka(opsi, {})) !== null;
  }

  /** Pengganti `prompt()`. Memulangkan `null` bila dibatalkan. */
  async function minta(o: OpsiMinta | string, nilai = ''): Promise<string | null> {
    const opsi: OpsiMinta = typeof o === 'string'
      ? { judul: o, label: o, nilai }
      : o;

    return buka(opsi, {
      isianLabel: opsi.label,
      isianJenis: opsi.jenis ?? 'teks',
      isianNilai: opsi.nilai ?? '',
      isianWajib: opsi.wajib ?? true,
      isianMin: opsi.min ?? 0,
      isianBaca: opsi.baca ?? false,
    });
  }

  function batal() {
    dialog.terbuka = false;
    const f = selesaikan;
    selesaikan = null;
    f?.(null);
  }

  function lanjut(nilai: string) {
    dialog.terbuka = false;
    const f = selesaikan;
    selesaikan = null;
    f?.(nilai);
  }

  return { dialog, tanya, minta, batal, lanjut };
}

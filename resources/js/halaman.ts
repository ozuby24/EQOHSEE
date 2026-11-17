import { usePage } from '@inertiajs/vue3';

/**
 * Prop halaman yang dibaca hidup, bukan disalin sekali.
 *
 * `usePage().props` mengembalikan objek prop yang berlaku SAAT ITU.
 * Menyalinnya ke sebuah konstanta —
 *
 *     const props = usePage<any>().props as any;
 *
 * — bekerja selama Inertia memasang ulang komponennya tiap berpindah
 * halaman, dan berhenti bekerja begitu komponennya dipertahankan
 * (`preserveState: true`, atau kembalinya galat validasi). Sesudah itu
 * halamannya membaca prop lama selamanya: penyaring mengubah URL,
 * server mengirim daftar yang benar, dan layar tetap memperlihatkan
 * daftar sebelumnya — tanpa satu pun pesan galat, tanpa satu pun baris
 * yang tampak salah. Yang terlihat hanya penyaring yang "tidak jalan".
 *
 * Yang dikembalikan di sini objek perantara: tiap pembacaan diteruskan
 * ke prop yang berlaku sekarang. Karena pembacaannya terjadi di dalam
 * render, Vue mencatatnya sebagai kebergantungan dan memperbarui
 * layarnya sendiri. Semua pemanggilan lama — `props.baris`,
 * `props.saring?.q` — tetap tertulis sama.
 *
 * Sengaja hanya dapat dibaca. Prop datang dari server; menulisinya di
 * peramban mengubah tampilan tanpa mengubah data, dan selisihnya baru
 * ketahuan saat halaman dimuat ulang.
 */
export function propHalaman<T = any>(): T {
  const halaman = usePage<any>();

  return new Proxy({} as any, {
    get: (_, kunci) => (halaman.props as any)[kunci],
    has: (_, kunci) => kunci in (halaman.props as any),
    ownKeys: () => Reflect.ownKeys(halaman.props as any),

    getOwnPropertyDescriptor: (_, kunci) => ({
      enumerable: true,
      configurable: true,
      value: (halaman.props as any)[kunci],
    }),

    set: (_, kunci) => {
      throw new Error(`Prop halaman "${String(kunci)}" datang dari server dan tidak dapat ditulisi.`);
    },
  }) as T;
}

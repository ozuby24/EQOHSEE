/**
 * Satu jalan mengunggah berkas lampiran Miners.
 *
 * Tiga layar memakainya — lampiran kartu, berkas uji unit SIMPER, dan
 * berkas sertifikat — dan sebelum berkas ini ada, dua di antaranya akan
 * lahir dengan menyalin yang pertama. Salinan itu bukan sekadar
 * pengulangan: yang tersalin termasuk pengambilan token CSRF dan
 * penanganan galatnya, dan yang paling mudah tertinggal saat menyalin
 * justru penanganan galatnya. Unggahan yang gagal tanpa keterangan
 * terbaca sebagai unggahan yang berhasil — dan yang mengiranya berhasil
 * tidak akan mencoba lagi.
 *
 * Yang dikembalikan JALUR berkasnya, bukan berkasnya. Berkasnya sudah
 * tersimpan di disk tertutup oleh server; jalurnya baru melekat pada
 * barisnya setelah formulir yang memanggil ini disimpan.
 */
export type HasilUnggah = { jalur: string; nama: string };

export async function unggahLampiran(kolom: string, berkas: File): Promise<HasilUnggah> {
  const data = new FormData();
  data.append('kolom', kolom);
  data.append('berkas', berkas);

  const r = await fetch('/miners/lampiran', {
    method: 'POST',
    body: data,
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
    },
  });

  /* Jawaban yang bukan JSON tetap harus punya sebab yang terbaca.
     Halaman galat Laravel dan halaman 413 dari Nginx keduanya HTML,
     dan JSON.parse atasnya melempar "Unexpected token <" — pesan yang
     tidak memberi tahu siapa pun bahwa berkasnya kebesaran. */
  let j: any = null;
  try { j = await r.json(); } catch { /* biarkan null */ }

  if (!r.ok) {
    throw new Error(j?.pesan
      ?? (r.status === 413 ? 'Berkas terlalu besar untuk diunggah.' : `Gagal mengunggah (HTTP ${r.status}).`));
  }

  return { jalur: j.jalur, nama: j.nama };
}

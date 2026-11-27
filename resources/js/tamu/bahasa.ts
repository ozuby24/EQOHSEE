import { ref } from 'vue';

/**
 * Bahasa halaman tamu (masuk, daftar, lupa sandi, atur ulang sandi,
 * verifikasi, dua faktor, konfirmasi sandi): Indonesia atau Inggris.
 *
 * Pilihannya disimpan di kuki `eq_bahasa` — bukan di localStorage —
 * supaya server ikut membacanya dan pesan validasinya (sandi salah,
 * kolom wajib, batas percobaan) keluar dalam bahasa yang sama dengan
 * halamannya (App\Http\Middleware\BahasaTamu). Aplikasi di balik login
 * tetap berbahasa Indonesia.
 *
 * Kunci yang tidak ada di kamus Inggris jatuh ke teks Indonesia, jadi
 * kunci yang terlupa tidak pernah menampilkan nama kuncinya di layar.
 */
export type Bahasa = 'id' | 'en';

export const KUKI_BAHASA = 'eq_bahasa';

const ID = {
  // ── tata letak ──
  'merek.ke_depan': 'EQOHSEE — ke halaman depan',
  'hero.sub': 'Satu platform terintegrasi untuk keselamatan, lingkungan, kepatuhan, dan kinerja operasional pertambangan.',
  'stat.sistem_aktif': 'Sistem aktif',
  'stat.pengguna': 'Pengguna',
  'stat.pengunjung': 'Pengunjung',
  'stat.modul': 'Modul',
  'stat.aspek': 'Aspek OHSE',
  'stat.tren': 'Pengunjung tujuh hari terakhir: {daftar}',
  'stat.hari_ini': 'hari ini',
  'stat.modul_pilar': 'Modul per pilar: {daftar}',
  'stat.ringkas': 'Ringkasan platform EQOHSEE',
  'pilar.label': 'Pilar EQOHSEE: {daftar}',
  'pilar.konservasi': 'Konservasi',
  'aman.koneksi': 'Koneksi terenkripsi',
  'aman.turnstile': 'Dilindungi Cloudflare Turnstile',
  'turnstile.halang': 'Verifikasi keamanan tidak dapat dimuat. Biasanya ini karena pemblokir iklan atau penyaring jaringan yang menutup',
  'turnstile.halang_saran': 'Matikan pemblokirnya untuk situs ini, atau hubungi administrator jaringan Anda.',
  'kaki.depan': '← eqohsee.id',
  'kaki.privasi': 'Kebijakan privasi',
  'bahasa.label': 'Bahasa: {nama}. Ganti bahasa',
  'bahasa.id': 'Bahasa Indonesia',
  'bahasa.en': 'English',

  // ── masuk ──
  'masuk.judul_tab': 'Masuk',
  'masuk.judul': 'Masuk ke',
  'masuk.sub': 'Kelola audit, kepatuhan, dan kinerja OHSE perusahaan Anda dalam satu platform.',
  'masuk.email': 'Email',
  'masuk.email_contoh': 'nama@perusahaan.co.id',
  'masuk.sandi': 'Kata Sandi',
  'masuk.sandi_isi': 'Masukkan kata sandi',
  'masuk.ingat': 'Ingat saya',
  'masuk.lupa': 'Lupa kata sandi?',
  'masuk.tombol': 'Masuk',
  'masuk.memeriksa': 'Memeriksa kredensial…',
  'masuk.atau': 'atau masuk dengan',
  'masuk.google': 'Masuk dengan Google',
  'masuk.microsoft': 'Masuk dengan Microsoft',
  'masuk.belum_punya': 'Belum memiliki akun?',
  'masuk.daftar': 'Daftar sekarang',

  // ── daftar ──
  'daftar.judul_tab': 'Daftar',
  'daftar.judul': 'Buat akun',
  'daftar.sub': 'Daftar untuk mulai mengikuti pelatihan di perusahaan Anda.',
  'daftar.nama': 'Nama lengkap',
  'daftar.nrp': 'NRP / Employee ID',
  'daftar.departemen': 'Departemen',
  'daftar.jabatan': 'Jabatan',
  'daftar.pilih_jabatan': '— pilih jabatan —',
  'daftar.perusahaan_demo': 'Perusahaan demo',
  'daftar.pilih_perusahaan': '— pilih perusahaan demo —',
  'daftar.petunjuk_demo': 'Akun baru dimulai di perusahaan demo berisi data contoh. Administrator yang memindahkan akun Anda ke perusahaan Anda sendiri.',
  'daftar.petunjuk_perusahaan': 'Perusahaan untuk akun Anda ditetapkan administrator sesudah pendaftaran.',
  'daftar.sandi': 'Kata sandi',
  'daftar.petunjuk_sandi': 'Minimal {n} huruf, tanpa syarat huruf besar atau angka. Kalimat pendek seperti «kopi pagi di tambang» lebih mudah diingat sekaligus lebih sulit ditebak daripada satu kata bercampur angka.',
  'daftar.ulangi_sandi': 'Ulangi kata sandi',
  'daftar.tombol': 'Daftar',
  'daftar.memproses': 'Mendaftarkan…',
  'daftar.sudah_punya': 'Sudah punya akun?',
  'daftar.masuk': 'Masuk',

  // ── lupa sandi ──
  'lupa.judul_tab': 'Lupa Kata Sandi',
  'lupa.judul': 'Lupa kata sandi?',
  'lupa.sub': 'Masukkan email Anda untuk menerima tautan pengaturan ulang.',
  'lupa.terkirim': 'Tautan reset sudah dikirim jika email terdaftar.',
  'lupa.tombol': 'Kirim tautan',
  'lupa.memproses': 'Mengirim…',
  'lupa.kembali': 'Kembali ke masuk',

  // ── atur ulang sandi ──
  'atur.judul_tab': 'Atur Ulang Kata Sandi',
  'atur.judul': 'Atur ulang kata sandi',
  'atur.sub': 'Buat kata sandi baru untuk akun Anda.',
  'atur.sandi_baru': 'Kata sandi baru',
  'atur.tombol': 'Atur ulang',

  // ── konfirmasi sandi ──
  'konfirmasi.judul_tab': 'Konfirmasi Kata Sandi',
  'konfirmasi.judul': 'Konfirmasi kata sandi',
  'konfirmasi.sub': 'Ini area aman. Konfirmasi kata sandi sebelum melanjutkan.',
  'konfirmasi.tombol': 'Konfirmasi',

  // ── dua faktor ──
  'dua.judul_tab': 'Verifikasi Dua Langkah',
  'dua.judul': 'Satu langkah lagi',
  'dua.sub': 'Masukkan kode dari aplikasi autentikator Anda',
  'dua.untuk': 'untuk',
  'dua.kode': 'Kode verifikasi',
  'dua.petunjuk_1': 'Tidak memegang ponselnya? Masukkan salah satu',
  'dua.kode_pemulihan': 'kode pemulihan',
  'dua.petunjuk_2': 'yang Anda simpan saat menyalakan verifikasi ini — bentuknya seperti',
  'dua.petunjuk_3': 'Setiap kode pemulihan hanya berlaku sekali.',
  'dua.tombol': 'Lanjutkan',
  'dua.memproses': 'Memeriksa…',
  'dua.sisa_waktu': 'Sisa waktu pengisian',
  'dua.habis': 'Waktu pengisian sudah habis — silakan masuk lagi dari awal.',
  'dua.akun_lain': 'Masuk dengan akun lain',

  // ── verifikasi surel ──
  'verif.judul_tab': 'Verifikasi Email',
  'verif.judul': 'Periksa surel Anda',
  'verif.terkirim': 'Kami mengirim kode 6 angka ke',
  'verif.kode_untuk': 'Kode 6 angka untuk',
  'verif.surel_mati': 'Pengiriman surel belum aktif di server ini',
  'verif.surel_mati_ket': 'Kodenya sudah dibuat tetapi tidak dikirim ke mana pun. Hubungi administrator untuk memperoleh kodenya, atau agar akun Anda diverifikasi langsung.',
  'verif.berlaku': 'Kode berlaku {n} menit.',
  'verif.tombol': 'Verifikasi',
  'verif.memproses': 'Memverifikasi kode…',
  'verif.mengirim': 'Mengirim kode baru…',
  'verif.kirim_dalam': 'Kirim ulang dalam {n} detik',
  'verif.kirim_ulang': 'Kirim ulang kode',
  'verif.terkunci': 'Kode terkunci karena terlalu banyak percobaan. Minta kode baru untuk mencoba lagi.',
};

export type KunciBahasa = keyof typeof ID;

const EN: Partial<Record<KunciBahasa, string>> = {
  'merek.ke_depan': 'EQOHSEE — go to homepage',
  'hero.sub': 'One integrated platform for mining safety, environment, compliance, and operational performance.',
  'stat.sistem_aktif': 'System online',
  'stat.pengguna': 'Users',
  'stat.pengunjung': 'Visitors',
  'stat.modul': 'Modules',
  'stat.aspek': 'OHSE aspects',
  'stat.tren': 'Visitors over the last seven days: {daftar}',
  'stat.hari_ini': 'today',
  'stat.modul_pilar': 'Modules by pillar: {daftar}',
  'stat.ringkas': 'EQOHSEE platform at a glance',
  'pilar.label': 'EQOHSEE pillars: {daftar}',
  'pilar.konservasi': 'Conservation',
  'aman.koneksi': 'Encrypted connection',
  'aman.turnstile': 'Protected by Cloudflare Turnstile',
  'turnstile.halang': 'The security check could not load. This is usually caused by an ad blocker or a network filter that blocks',
  'turnstile.halang_saran': 'Turn off the blocker for this site, or contact your network administrator.',
  'kaki.privasi': 'Privacy policy',
  'bahasa.label': 'Language: {nama}. Change language',

  'masuk.judul_tab': 'Sign in',
  'masuk.judul': 'Sign in to',
  'masuk.sub': "Manage your company's audits, compliance, and OHSE performance in one platform.",
  'masuk.email_contoh': 'name@company.com',
  'masuk.sandi': 'Password',
  'masuk.sandi_isi': 'Enter your password',
  'masuk.ingat': 'Remember me',
  'masuk.lupa': 'Forgot password?',
  'masuk.tombol': 'Sign in',
  'masuk.memeriksa': 'Checking credentials…',
  'masuk.atau': 'or sign in with',
  'masuk.google': 'Sign in with Google',
  'masuk.microsoft': 'Sign in with Microsoft',
  'masuk.belum_punya': "Don't have an account?",
  'masuk.daftar': 'Sign up now',

  'daftar.judul_tab': 'Sign up',
  'daftar.judul': 'Create an account',
  'daftar.sub': "Sign up to start your company's training.",
  'daftar.nama': 'Full name',
  'daftar.nrp': 'Employee ID',
  'daftar.departemen': 'Department',
  'daftar.jabatan': 'Position',
  'daftar.pilih_jabatan': '— select a position —',
  'daftar.perusahaan_demo': 'Demo company',
  'daftar.pilih_perusahaan': '— select a demo company —',
  'daftar.petunjuk_demo': 'New accounts start in a demo company with sample data. An administrator moves your account to your own company.',
  'daftar.petunjuk_perusahaan': 'Your company is assigned by an administrator after you sign up.',
  'daftar.sandi': 'Password',
  'daftar.petunjuk_sandi': 'At least {n} characters, no uppercase or number required. A short phrase such as «morning coffee at the pit» is easier to remember and harder to guess than one word mixed with numbers.',
  'daftar.ulangi_sandi': 'Repeat password',
  'daftar.tombol': 'Sign up',
  'daftar.memproses': 'Creating your account…',
  'daftar.sudah_punya': 'Already have an account?',
  'daftar.masuk': 'Sign in',

  'lupa.judul_tab': 'Forgot Password',
  'lupa.judul': 'Forgot your password?',
  'lupa.sub': 'Enter your email to receive a reset link.',
  'lupa.terkirim': 'If the email is registered, a reset link has been sent.',
  'lupa.tombol': 'Send link',
  'lupa.memproses': 'Sending…',
  'lupa.kembali': 'Back to sign in',

  'atur.judul_tab': 'Reset Password',
  'atur.judul': 'Reset your password',
  'atur.sub': 'Create a new password for your account.',
  'atur.sandi_baru': 'New password',
  'atur.tombol': 'Reset password',

  'konfirmasi.judul_tab': 'Confirm Password',
  'konfirmasi.judul': 'Confirm your password',
  'konfirmasi.sub': 'This is a secure area. Confirm your password before continuing.',
  'konfirmasi.tombol': 'Confirm',

  'dua.judul_tab': 'Two-Step Verification',
  'dua.judul': 'One more step',
  'dua.sub': 'Enter the code from your authenticator app',
  'dua.untuk': 'for',
  'dua.kode': 'Verification code',
  'dua.petunjuk_1': "Don't have your phone? Enter one of the",
  'dua.kode_pemulihan': 'recovery codes',
  'dua.petunjuk_2': 'you saved when you turned this on — it looks like',
  'dua.petunjuk_3': 'Each recovery code works only once.',
  'dua.tombol': 'Continue',
  'dua.memproses': 'Checking…',
  'dua.sisa_waktu': 'Time remaining',
  'dua.habis': 'Time is up — please sign in again from the start.',
  'dua.akun_lain': 'Sign in with another account',

  'verif.judul_tab': 'Verify Email',
  'verif.judul': 'Check your email',
  'verif.terkirim': 'We sent a 6-digit code to',
  'verif.kode_untuk': '6-digit code for',
  'verif.surel_mati': 'Email delivery is not enabled on this server',
  'verif.surel_mati_ket': 'The code was created but not sent anywhere. Contact your administrator for the code, or to have your account verified directly.',
  'verif.berlaku': 'The code is valid for {n} minutes.',
  'verif.tombol': 'Verify',
  'verif.memproses': 'Verifying code…',
  'verif.mengirim': 'Sending a new code…',
  'verif.kirim_dalam': 'Resend in {n} seconds',
  'verif.kirim_ulang': 'Resend code',
  'verif.terkunci': 'The code is locked after too many attempts. Request a new code to try again.',
};

const KAMUS: Record<Bahasa, Partial<Record<KunciBahasa, string>>> = { id: ID, en: EN };

function bacaKuki(): Bahasa {
  if (typeof document === 'undefined') return 'id';
  const m = document.cookie.match(/(?:^|;\s*)eq_bahasa=(id|en)\b/);

  return (m?.[1] as Bahasa | undefined) ?? 'id';
}

/* Satu nilai untuk seluruh halaman tamu: tata letak dan halamannya
   membaca bahasa yang sama, dan mengganti di satu tempat mengganti
   semuanya sekaligus. */
const aktif = ref<Bahasa>(bacaKuki());

export function useBahasa() {
  function pilih(b: Bahasa): void {
    aktif.value = b;
    const aman = typeof location !== 'undefined' && location.protocol === 'https:' ? '; secure' : '';
    document.cookie = `${KUKI_BAHASA}=${b}; path=/; max-age=31536000; samesite=lax${aman}`;
    document.documentElement.lang = b;
  }

  function t(kunci: KunciBahasa, isi: Record<string, string | number> = {}): string {
    const teks = KAMUS[aktif.value][kunci] ?? ID[kunci];

    return teks.replace(/\{(\w+)\}/g, (_, k: string) => String(isi[k] ?? ''));
  }

  /** Angka berformat sesuai bahasa: 1.248 (id) atau 1,248 (en). */
  function angka(n: number): string {
    return new Intl.NumberFormat(aktif.value === 'en' ? 'en-US' : 'id-ID').format(n);
  }

  return { bahasa: aktif, pilih, t, angka };
}

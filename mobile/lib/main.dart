import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'kunci.dart';
import 'layar_utama.dart';
import 'pengenalan.dart';
import 'tema.dart';

/// EQOHSEE untuk Android.
///
/// ── APA YANG DIKERJAKAN BERKAS INI, DAN APA YANG TIDAK ──
///
/// Ini pembungkus, bukan penulisan ulang. Dua puluh tujuh modul EQOHSEE
/// — roster, absensi, payroll, SMKP, izin kerja, dan seterusnya — hidup
/// di server dan digambar sebagai halaman web. Aplikasi ini menyajikan
/// halaman itu di dalam WebView, lalu menambahkan hal-hal yang TIDAK
/// dapat dilakukan peramban seluler biasa:
///
///   • tombol kembali perangkat yang menyusuri riwayat halaman,
///   • unggahan foto dari kamera dan galeri — tanpa izin kamera/galeri,
///   • unduhan yang membawa sesi masuk, ke folder Download,
///   • titik GPS laporan bahaya, dengan penjelasan sebelum izin diminta,
///   • pintasan ikon: Lapor bahaya, P2H unit, Tugas saya,
///   • kunci sidik jari opsional untuk data pekerja,
///   • layar "belum ada sinyal" yang jujur, bukan galat peramban,
///   • tautan telepon, surel, dan WhatsApp yang keluar ke aplikasinya.
///
/// Menulis ulang dua puluh tujuh modul dalam Dart akan memakan waktu
/// berbulan-bulan dan melahirkan salinan kedua dari setiap aturan —
/// aturan lembur PP 35/2021, TER PPh 21, batas fatigue roster — yang
/// sejak hari pertama boleh berbeda dari aslinya tanpa ada yang tahu.
/// Satu sumber kebenaran lebih berharga daripada layar yang asli.
Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Tepi-ke-tepi (wajib sejak Android 15): aplikasi menggambar di balik
  // bilah sistem, dan tiap layar menyetel warna ikon bilahnya sendiri.
  SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
  SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
    statusBarColor: Colors.transparent,
    statusBarIconBrightness: Brightness.light,
  ));

  final pref = await SharedPreferences.getInstance();
  final info = await PackageInfo.fromPlatform();

  runApp(AplikasiEqohsee(pengaturan: Pengaturan(pref), versi: info.version));
}

/// Alamat situsnya.
///
/// Dapat diganti tanpa menyunting berkas ini:
///   flutter build apk --dart-define=EQOHSEE_URL=https://staging.eqohsee.id
///
/// Berguna untuk menguji APK terhadap server uji tanpa membuat cabang
/// kode tersendiri — cabang seperti itu selalu tertinggal.
const String alamatSitus = String.fromEnvironment(
  'EQOHSEE_URL',
  defaultValue: 'https://eqohsee.id',
);

/// Halaman pertama: mode lapangan — beranda awal shift, lapor bahaya,
/// P2H, izin kerja. Yang belum masuk dibawa ke halaman masuk lalu
/// kembali ke sini; versi web lengkap tetap terbuka dari tab Profil.
const String alamatAwal = '$alamatSitus/lapangan';

/// Inang yang boleh dibuka DI DALAM aplikasi.
///
/// Selain ini dilempar ke peramban. Tanpa daftar ini, satu tautan ke
/// situs luar mengubah aplikasi perusahaan menjadi peramban umum —
/// lengkap dengan sesi yang masih login di baliknya.
const Set<String> inangSendiri = {'eqohsee.id', 'www.eqohsee.id'};

/// Apakah alamat ini boleh dibuka DI DALAM aplikasi?
///
/// Dipisahkan menjadi fungsi murni supaya dapat diuji tanpa WebView.
/// Aturannya kecil tetapi memikul beban: satu kesalahan di sini membuat
/// aplikasi perusahaan berubah menjadi peramban umum — dengan sesi yang
/// masih login di baliknya, di perangkat yang mungkin dipegang
/// bergantian satu regu.
///
/// Dua hal yang mudah terlewat, dan keduanya diuji:
///
///   • Cocoknya harus PERSIS. 'eqohsee.id.penyerang.com' berakhiran sama
///     dan akan lolos kalau dicocokkan dengan endsWith().
///   • Penjaga skemanya BUKAN hiasan, meski tel: dan mailto: sudah
///     tertolak sendiri karena inangnya kosong. Yang ditahannya
///     'ftp://eqohsee.id/x' — inangnya eqohsee.id, persis ada di daftar,
///     jadi tanpa penjaga ini ia dijawab boleh.
///
/// Tidak ada toLowerCase() di sini: Uri milik Dart sudah menormalkan
/// inangnya ke huruf kecil saat diurai. Sempat ada, dan sebuah uji
/// tampak menjaganya — padahal membuangnya tidak mengubah apa pun.
/// Kode mati yang seolah teruji lebih buruk daripada kode mati biasa.
bool bukaDiDalam(Uri alamat) {
  if (alamat.scheme != 'http' && alamat.scheme != 'https') return false;

  return inangSendiri.contains(alamat.host);
}

class AplikasiEqohsee extends StatefulWidget {
  const AplikasiEqohsee({super.key, required this.pengaturan, required this.versi});

  final Pengaturan pengaturan;
  final String versi;

  @override
  State<AplikasiEqohsee> createState() => _AplikasiEqohseeState();
}

class _AplikasiEqohseeState extends State<AplikasiEqohsee> with WidgetsBindingObserver {
  late bool _terkunci = widget.pengaturan.kunciAktif;
  DateTime? _keLatar;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    widget.pengaturan.addListener(_segarkan);
  }

  @override
  void dispose() {
    widget.pengaturan.removeListener(_segarkan);
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  void _segarkan() => setState(() {});

  @override
  void didChangeAppLifecycleState(AppLifecycleState keadaan) {
    if (keadaan == AppLifecycleState.paused) {
      _keLatar = DateTime.now();
    } else if (keadaan == AppLifecycleState.resumed) {
      if (perluKunci(aktif: widget.pengaturan.kunciAktif, keLatar: _keLatar, kini: DateTime.now())) {
        setState(() => _terkunci = true);
      }
      _keLatar = null;
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = widget.pengaturan;

    return MaterialApp(
      title: 'EQOHSEE',
      debugShowCheckedModeBanner: false,
      theme: temaEqohsee(),
      home: !p.sudahPengenalan
          ? LayarPengenalan(selesai: p.selesaiPengenalan)
          /* Layar kunci DITUMPUK di atas WebView, bukan menggantikannya:
             halaman yang sedang diisi — P2H setengah jadi, laporan dengan
             tiga foto — tetap utuh di baliknya. */
          : Stack(children: [
              LayarUtama(pengaturan: p, versi: widget.versi),
              if (_terkunci && p.kunciAktif)
                Positioned.fill(child: LayarKunci(terbuka: () => setState(() => _terkunci = false))),
            ]),
    );
  }
}

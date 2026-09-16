import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_inappwebview/flutter_inappwebview.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:url_launcher/url_launcher.dart';

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
///   • unggahan berkas dari kamera dan galeri,
///   • unduhan yang menghormati Content-Disposition,
///   • layar "tidak ada jaringan" yang jujur, bukan galat peramban,
///   • tautan telepon, surel, dan WhatsApp yang keluar ke aplikasinya.
///
/// Menulis ulang dua puluh tujuh modul dalam Dart akan memakan waktu
/// berbulan-bulan dan melahirkan salinan kedua dari setiap aturan —
/// aturan lembur PP 35/2021, TER PPh 21, batas fatigue roster — yang
/// sejak hari pertama boleh berbeda dari aslinya tanpa ada yang tahu.
/// Satu sumber kebenaran lebih berharga daripada layar yang asli.
void main() {
  WidgetsFlutterBinding.ensureInitialized();
  SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
    statusBarColor: Color(0xFF0B1117),
    statusBarIconBrightness: Brightness.light,
  ));
  runApp(const AplikasiEqohsee());
}

/// Alamat situsnya.
///
/// Dapat diganti tanpa menyunting berkas ini:
///   flutter build apk --dart-define=EQOHSEE_URL=https://staging.eqohsee.id
///
/// Berguna untuk menguji APK terhadap server uji tanpa membuat cabang
/// kode tersendiri — cabang seperti itu selalu tertinggal.
const String alamatAwal = String.fromEnvironment(
  'EQOHSEE_URL',
  defaultValue: 'https://eqohsee.id',
);

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

const Color oranye = Color(0xFFF57C00);
const Color gelap = Color(0xFF0B1117);

class AplikasiEqohsee extends StatelessWidget {
  const AplikasiEqohsee({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'EQOHSEE',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: oranye),
        useMaterial3: true,
      ),
      home: const LayarUtama(),
    );
  }
}

class LayarUtama extends StatefulWidget {
  const LayarUtama({super.key});

  @override
  State<LayarUtama> createState() => _LayarUtamaState();
}

class _LayarUtamaState extends State<LayarUtama> {
  InAppWebViewController? _web;
  PullToRefreshController? _tarikSegar;

  bool _memuat = true;
  bool _gagal = false;
  String _sebabGagal = '';
  double _laju = 0;

  @override
  void initState() {
    super.initState();
    _tarikSegar = PullToRefreshController(
      settings: PullToRefreshSettings(color: oranye),
      onRefresh: () async => _web?.reload(),
    );
  }

  /// Izin diminta saat aplikasi siap, bukan saat kamera dibuka.
  ///
  /// Halaman web tidak dapat menampilkan dialog izin Android sendiri:
  /// ketika pemakai menekan "ambil foto" pada laporan bahaya, WebView
  /// menanyakannya ke aplikasi, dan aplikasi yang belum berizin hanya
  /// dapat menjawab tidak — tanpa satu pun tanda kenapa.
  Future<void> _mintaIzin() async {
    await [Permission.camera, Permission.microphone].request();
  }

  Future<bool> _adaJaringan() async {
    final hasil = await Connectivity().checkConnectivity();
    return !hasil.contains(ConnectivityResult.none);
  }

  Future<void> _muatUlang() async {
    if (!await _adaJaringan()) {
      setState(() {
        _gagal = true;
        _sebabGagal = 'Tidak ada sambungan internet.';
        _memuat = false;
      });
      return;
    }
    setState(() {
      _gagal = false;
      _memuat = true;
    });
    if (_web == null) return;
    await _web!.loadUrl(
      urlRequest: URLRequest(url: WebUri(alamatAwal)),
    );
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      // Tombol kembali perangkat menyusuri riwayat HALAMAN lebih dulu.
      // Tanpa ini ia langsung menutup aplikasi, dan orang yang masuk
      // tiga tingkat ke dalam modul kehilangan seluruh jalannya sekali
      // tekan — keluhan pertama pada tiap pembungkus WebView.
      canPop: false,
      onPopInvokedWithResult: (sudahKeluar, _) async {
        if (sudahKeluar) return;
        if (_web != null && await _web!.canGoBack()) {
          _web!.goBack();
          return;
        }
        if (!mounted) return;
        final keluar = await _tanyaKeluar();
        if (keluar && mounted) SystemNavigator.pop();
      },
      child: Scaffold(
        backgroundColor: gelap,
        body: SafeArea(
          bottom: false,
          child: _gagal ? _layarGagal() : _layarWeb(),
        ),
      ),
    );
  }

  Widget _layarWeb() {
    return Stack(
      children: [
        InAppWebView(
          initialUrlRequest: URLRequest(url: WebUri(alamatAwal)),
          pullToRefreshController: _tarikSegar,
          initialSettings: InAppWebViewSettings(
            // Diperlukan agar <input type="file"> dan unduhan bekerja.
            useOnDownloadStart: true,
            useShouldOverrideUrlLoading: true,
            javaScriptEnabled: true,
            supportZoom: false,
            // Halaman EQOHSEE sudah punya tata letak selulernya sendiri;
            // "desktop mode" justru mengecilkan tulisannya sampai tidak
            // terbaca di lapangan.
            useWideViewPort: false,
            mediaPlaybackRequiresUserGesture: false,
            allowsInlineMediaPlayback: true,
            // Unggahan foto dari kamera lewat halaman web.
            javaScriptCanOpenWindowsAutomatically: true,
            userAgent: 'EQOHSEE-Android/1.0 (WebView)',
          ),
          onWebViewCreated: (c) async {
            _web = c;
            await _mintaIzin();
          },
          onLoadStart: (_, __) => setState(() => _memuat = true),
          onLoadStop: (_, __) async {
            _tarikSegar?.endRefreshing();
            setState(() => _memuat = false);
          },
          onProgressChanged: (_, laju) {
            if (laju == 100) _tarikSegar?.endRefreshing();
            setState(() => _laju = laju / 100);
          },
          onReceivedError: (_, permintaan, galat) {
            // Hanya kegagalan BINGKAI UTAMA yang menutup layar. Satu
            // gambar yang gagal dimuat bukan alasan menyembunyikan
            // seluruh halaman yang sudah tergambar baik-baik saja.
            if (!permintaan.isForMainFrame!) return;
            setState(() {
              _gagal = true;
              _sebabGagal = galat.description;
              _memuat = false;
            });
          },
          onPermissionRequest: (_, permintaan) async {
            return PermissionResponse(
              resources: permintaan.resources,
              action: PermissionResponseAction.GRANT,
            );
          },
          shouldOverrideUrlLoading: (c, aksi) async {
            final url = aksi.request.url;
            if (url == null) return NavigationActionPolicy.ALLOW;

            // tel:, mailto:, wa.me, dan situs luar keluar ke aplikasinya.
            if (!bukaDiDalam(Uri.parse(url.toString()))) {
              await _bukaDiLuar(url);
              return NavigationActionPolicy.CANCEL;
            }
            return NavigationActionPolicy.ALLOW;
          },
          onDownloadStartRequest: (c, unduhan) async {
            // Unduhan (CSV ekspor, lampiran berkas, cetak PDF) diserahkan
            // ke pengelola unduhan sistem: ia menaruhnya di folder Unduhan
            // dan menampilkan pemberitahuan, yang keduanya tidak dapat
            // dilakukan WebView sendiri.
            await _bukaDiLuar(unduhan.url);
          },
        ),
        if (_memuat)
          LinearProgressIndicator(
            value: _laju == 0 ? null : _laju,
            minHeight: 2.5,
            backgroundColor: Colors.transparent,
            valueColor: const AlwaysStoppedAnimation(oranye),
          ),
      ],
    );
  }

  Future<void> _bukaDiLuar(WebUri url) async {
    final uri = Uri.parse(url.toString());
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  Future<bool> _tanyaKeluar() async {
    final jawab = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('Keluar dari EQOHSEE?'),
        content: const Text('Anda akan menutup aplikasi.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(c, true),
            child: const Text('Keluar'),
          ),
        ],
      ),
    );
    return jawab ?? false;
  }

  Widget _layarGagal() {
    return Container(
      color: gelap,
      width: double.infinity,
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.cloud_off_rounded, size: 58, color: oranye),
          const SizedBox(height: 18),
          const Text(
            'Tidak dapat memuat EQOHSEE',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: Colors.white,
              fontSize: 18,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            _sebabGagal.isEmpty
                ? 'Periksa sambungan internet Anda, lalu coba lagi.'
                : _sebabGagal,
            textAlign: TextAlign.center,
            style: const TextStyle(color: Color(0xFF9AA6B0), fontSize: 13.5),
          ),
          const SizedBox(height: 22),
          FilledButton.icon(
            onPressed: _muatUlang,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Coba lagi'),
            style: FilledButton.styleFrom(backgroundColor: oranye),
          ),
        ],
      ),
    );
  }
}

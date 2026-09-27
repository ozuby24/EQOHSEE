import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_inappwebview/flutter_inappwebview.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:quick_actions/quick_actions.dart';
import 'package:url_launcher/url_launcher.dart';

import 'kunci.dart';
import 'lukisan.dart';
import 'main.dart';
import 'pengaturan.dart';
import 'tema.dart';

/// Pintasan ikon (tekan lama ikon EQOHSEE di layar utama ponsel).
///
/// Tiga pekerjaan yang paling sering dilakukan di lapangan, masing-masing
/// langsung ke layarnya tanpa melewati beranda.
const Map<String, String> pintasan = {
  'lapor': '/lapangan/lapor',
  'p2h': '/lapangan/p2h',
  'tugas': '/lapangan/tugas',
};

/// Alamat lengkap sebuah pintasan, atau null bila jenisnya tidak dikenal.
String? alamatPintasan(String? jenis) {
  final jalur = pintasan[jenis];
  return jalur == null ? null : '$alamatSitus$jalur';
}

class LayarUtama extends StatefulWidget {
  const LayarUtama({super.key, required this.pengaturan, required this.versi});

  final Pengaturan pengaturan;
  final String versi;

  @override
  State<LayarUtama> createState() => _LayarUtamaState();
}

class _LayarUtamaState extends State<LayarUtama> {
  static const _saluranUnduh = MethodChannel('id.eqohsee/unduh');

  InAppWebViewController? _web;
  PullToRefreshController? _tarikSegar;
  StreamSubscription<List<ConnectivityResult>>? _pantauJaringan;

  String _alamatAwal = alamatAwal;
  bool _memuat = true;
  bool _gagal = false;
  String _sebabGagal = '';
  double _laju = 0;
  bool _lokasiDijelaskan = false;

  @override
  void initState() {
    super.initState();
    _tarikSegar = PullToRefreshController(
      settings: PullToRefreshSettings(color: Warna.sinyal),
      onRefresh: () async => _web?.reload(),
    );
    _pasangPintasan();

    // Sinyal kembali sesudah layar gagal: muat ulang sendiri, tidak perlu
    // menunggu orangnya menekan "Coba lagi".
    _pantauJaringan = Connectivity().onConnectivityChanged.listen((hasil) {
      if (_gagal && !hasil.contains(ConnectivityResult.none)) _muatUlang();
    });
  }

  @override
  void dispose() {
    _pantauJaringan?.cancel();
    super.dispose();
  }

  /* ═══════════ pintasan ikon ═══════════ */

  void _pasangPintasan() {
    const qa = QuickActions();
    qa.initialize((jenis) {
      final url = alamatPintasan(jenis);
      if (url == null) return;
      if (_web == null) {
        // Dibuka dingin dari pintasan: WebView belum ada, alamat awalnya
        // yang diganti.
        setState(() => _alamatAwal = url);
      } else {
        _buka(url);
      }
    });
    qa.setShortcutItems(const [
      ShortcutItem(type: 'lapor', localizedTitle: 'Lapor bahaya', icon: 'ic_pintasan_lapor'),
      ShortcutItem(type: 'p2h', localizedTitle: 'P2H unit', icon: 'ic_pintasan_p2h'),
      ShortcutItem(type: 'tugas', localizedTitle: 'Tugas saya', icon: 'ic_pintasan_tugas'),
    ]);
  }

  void _buka(String url) {
    _web?.loadUrl(urlRequest: URLRequest(url: WebUri(url)));
  }

  /* ═══════════ muat & gagal ═══════════ */

  Future<void> _muatUlang() async {
    setState(() { _gagal = false; _memuat = true; });
    if (_web == null) return;
    // Tanpa sinyal pun tetap dicoba: mode lapangan menyimpan layarnya di
    // perangkat (service worker), jadi yang pernah dibuka tetap terbuka.
    final url = await _web!.getUrl();
    await _web!.loadUrl(urlRequest: URLRequest(url: url ?? WebUri(_alamatAwal)));
  }

  /* ═══════════ lokasi ═══════════ */

  /// Pastikan izin lokasi sistem, dengan penjelasan lebih dulu bila
  /// orangnya melewati layar izin saat pengenalan.
  Future<bool> _pastikanLokasi() async {
    var s = await Permission.locationWhenInUse.status;
    if (s.isGranted || s.isLimited) return true;

    if (s.isPermanentlyDenied || s.isRestricted) {
      _kabar('Izin lokasi ditolak. Laporan tetap dapat dikirim dengan menulis lokasinya.',
          aksi: SnackBarAction(label: 'Setelan', onPressed: openAppSettings));
      return false;
    }

    if (!_lokasiDijelaskan && mounted) {
      _lokasiDijelaskan = true;
      final setuju = await showDialog<bool>(
        context: context,
        builder: (c) => AlertDialog(
          icon: const Align(alignment: Alignment.centerLeft, child: PinLokasi(ukuran: 34)),
          title: const Text('Catat lokasi temuan?'),
          content: const Text(
            'EQOHSEE mengumpulkan titik lokasi saat Anda membuat laporan bahaya, supaya '
            'pengawas tahu persis di mana temuannya. Lokasi hanya dibaca saat aplikasi '
            'dipakai dan hanya tersimpan pada laporan itu.',
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('Tidak')),
            FilledButton(
              onPressed: () => Navigator.pop(c, true),
              style: FilledButton.styleFrom(minimumSize: const Size(120, 44)),
              child: const Text('Lanjut'),
            ),
          ],
        ),
      );
      if (setuju != true) return false;
    }

    s = await Permission.locationWhenInUse.request();
    return s.isGranted || s.isLimited;
  }

  /* ═══════════ unduhan ═══════════ */

  Future<void> _unduh(DownloadStartRequest r) async {
    final url = r.url;

    if (url.scheme == 'blob' || url.scheme == 'data') {
      _kabar('Berkas ini dibuat di halaman dan belum dapat disimpan dari aplikasi. Buka versi web di peramban untuk mengunduhnya.');
      return;
    }
    if (!bukaDiDalam(url)) {
      await _bukaDiLuar(url);
      return;
    }

    try {
      final nama = await _saluranUnduh.invokeMethod<String>('unduh', {
        'url': url.toString(),
        'userAgent': r.userAgent,
        'disposisi': r.contentDisposition,
        'mime': r.mimeType,
        'nama': r.suggestedFilename,
      });
      HapticFeedback.lightImpact();
      _kabar('Mengunduh ${nama ?? 'berkas'} — lihat notifikasi, tersimpan di folder Download.');
    } on PlatformException catch (e) {
      _kabar('Unduhan gagal: ${e.message ?? 'coba lagi'}');
    }
  }

  /* ═══════════ jembatan halaman ═══════════ */

  /// Halaman web boleh memanggil dua hal: membuka lembar pengaturan dan
  /// menanyakan versi aplikasi. Keduanya hanya dijawab bila halaman yang
  /// memanggil memang halaman EQOHSEE.
  Future<bool> _halamanSendiri() async {
    final url = await _web?.getUrl();
    return url != null && bukaDiDalam(url);
  }

  void _pasangJembatan(InAppWebViewController c) {
    c.addJavaScriptHandler(
      handlerName: 'aplikasi',
      callback: (_) async => await _halamanSendiri()
          ? {'versi': widget.versi, 'kunci': widget.pengaturan.kunciAktif}
          : null,
    );
    c.addJavaScriptHandler(
      handlerName: 'pengaturan',
      callback: (_) async {
        if (!await _halamanSendiri() || !mounted) return null;
        await bukaLembarPengaturan(
          context,
          pengaturan: widget.pengaturan,
          versi: widget.versi,
          alamatSitus: alamatSitus,
          bukaDiWebView: _buka,
        );
        return null;
      },
    );
  }

  /* ═══════════ lain-lain ═══════════ */

  void _kabar(String pesan, {SnackBarAction? aksi}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(pesan), action: aksi, duration: const Duration(seconds: 5)));
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
        content: const Text('Laporan yang belum terkirim tetap tersimpan dan terkirim saat aplikasi dibuka lagi.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('Batal')),
          FilledButton(
            onPressed: () => Navigator.pop(c, true),
            style: FilledButton.styleFrom(minimumSize: const Size(100, 44)),
            child: const Text('Keluar'),
          ),
        ],
      ),
    );
    return jawab ?? false;
  }

  @override
  Widget build(BuildContext context) {
    final bawah = MediaQuery.paddingOf(context).bottom;
    final atas = MediaQuery.paddingOf(context).top;

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (sudah, _) async {
        if (sudah) return;
        if (_web != null && await _web!.canGoBack()) {
          _web!.goBack();
          return;
        }
        if (!mounted) return;
        if (await _tanyaKeluar() && mounted) SystemNavigator.pop();
      },
      child: AnnotatedRegion<SystemUiOverlayStyle>(
        value: const SystemUiOverlayStyle(
          statusBarColor: Colors.transparent,
          statusBarIconBrightness: Brightness.light,
          systemNavigationBarColor: Colors.white,
          systemNavigationBarIconBrightness: Brightness.dark,
        ),
        child: Scaffold(
          backgroundColor: Colors.white,
          body: Column(
            children: [
              // Bilah status di atas halaman: ink, seperti kepala beranda lapangan.
              Container(height: atas, color: Warna.ink),
              /* WebView tetap di pohon saat layar gagal tampil di atasnya:
                 dibuang, pengendalinya ikut mati dan "Coba lagi" tidak
                 punya apa pun untuk dimuat ulang. */
              Expanded(
                child: Stack(children: [
                  Positioned.fill(child: _layarWeb()),
                  if (_gagal) Positioned.fill(child: _layarGagal()),
                ]),
              ),
              // Di bawah halaman: putih, menyambung dengan tab bawah lapangan.
              Container(height: bawah, color: Colors.white),
            ],
          ),
        ),
      ),
    );
  }

  Widget _layarWeb() {
    return Stack(
      children: [
        InAppWebView(
          initialUrlRequest: URLRequest(url: WebUri(_alamatAwal)),
          pullToRefreshController: _tarikSegar,
          initialSettings: InAppWebViewSettings(
            useOnDownloadStart: true,
            useShouldOverrideUrlLoading: true,
            javaScriptEnabled: true,
            supportZoom: false,
            useWideViewPort: false,
            mediaPlaybackRequiresUserGesture: false,
            allowsInlineMediaPlayback: true,
            javaScriptCanOpenWindowsAutomatically: true,
            geolocationEnabled: true,
            databaseEnabled: true,
            domStorageEnabled: true,
            // Berkas lokal perangkat tidak boleh dibaca halaman mana pun.
            allowFileAccess: false,
            mixedContentMode: MixedContentMode.MIXED_CONTENT_NEVER_ALLOW,
            // DITAMBAHKAN ke user agent WebView bawaan, bukan menggantinya.
            // UA buatan tanpa "Mozilla/… Chrome/…" dianggap bot oleh
            // Cloudflare — Turnstile di halaman masuk dapat menolak
            // peninjau Play maupun pekerja sungguhan. Server cukup mencari
            // "EQOHSEE-Android" (App\Support\Lapangan::dariAplikasi).
            applicationNameForUserAgent: 'EQOHSEE-Android/${widget.versi}',
          ),
          onWebViewCreated: (c) {
            _web = c;
            _pasangJembatan(c);
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
            // Hanya kegagalan BINGKAI UTAMA yang menutup layar, dan bukan
            // navigasi yang sengaja dibatalkan (tautan yang dibuka di luar).
            if (permintaan.isForMainFrame != true) return;
            if (galat.type == WebResourceErrorType.CANCELLED) return;
            _tarikSegar?.endRefreshing();
            setState(() {
              _gagal = true;
              _sebabGagal = galat.description;
              _memuat = false;
            });
          },
          // Halaman EQOHSEE tidak memakai kamera/mikrofon langsung
          // (getUserMedia); foto lewat pemilih berkas. Tolak semuanya.
          onPermissionRequest: (_, permintaan) async => PermissionResponse(
            resources: permintaan.resources,
            action: PermissionResponseAction.DENY,
          ),
          onGeolocationPermissionsShowPrompt: (_, asal) async {
            final izinkan = bukaDiDalam(Uri.parse(asal)) && await _pastikanLokasi();
            return GeolocationPermissionShowPromptResponse(origin: asal, allow: izinkan, retain: izinkan);
          },
          shouldOverrideUrlLoading: (c, aksi) async {
            final url = aksi.request.url;
            if (url == null) return NavigationActionPolicy.ALLOW;
            if (url.scheme == 'about' || url.scheme == 'blob' || url.scheme == 'data') {
              return NavigationActionPolicy.ALLOW;
            }
            // tel:, mailto:, wa.me, dan situs luar keluar ke aplikasinya.
            if (!bukaDiDalam(Uri.parse(url.toString()))) {
              await _bukaDiLuar(url);
              return NavigationActionPolicy.CANCEL;
            }
            return NavigationActionPolicy.ALLOW;
          },
          onDownloadStartRequest: (_, r) => _unduh(r),
        ),
        if (_memuat)
          LinearProgressIndicator(
            value: _laju == 0 ? null : _laju,
            minHeight: 2.5,
            backgroundColor: Colors.transparent,
            valueColor: const AlwaysStoppedAnimation(Warna.sinyal),
          ),
      ],
    );
  }

  Widget _layarGagal() {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: const SystemUiOverlayStyle(systemNavigationBarColor: Warna.ink),
      child: Material(
        color: Warna.ink,
        child: Kontur(
          pusat: const Offset(.15, .3),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(22, 22, 22, 22),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(children: [
                  const Wordmark(ukuran: 17),
                  const Spacer(),
                  Text('LURING', style: gayaMono(ukuran: 11)),
                ]),
                const Spacer(),
                const BatangSinyal(hidup: 0, ukuran: 52),
                const SizedBox(height: 22),
                Row(children: [
                  Container(width: 18, height: 2, color: Warna.sinyal),
                  const SizedBox(width: 8),
                  Text('HALAMAN INI BELUM TERSIMPAN', style: gayaMono(ukuran: 11)),
                ]),
                const SizedBox(height: 10),
                Text('Belum ada sinyal.', style: gayaJudul(ukuran: 38)),
                const SizedBox(height: 10),
                Text(
                  'Laporan dan P2H yang sudah disusun tetap aman di ponsel dan terkirim '
                  'sendiri begitu sinyal kembali. Layar ini dicoba lagi otomatis.',
                  style: gayaIsi(ukuran: 15),
                ),
                if (_sebabGagal.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  Text(_sebabGagal.toUpperCase(), style: gayaMono(ukuran: 9.5, warna: Warna.putih50, tebal: FontWeight.w500)),
                ],
                const Spacer(),
                FilledButton(
                  onPressed: _muatUlang,
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: const [Icon(Icons.refresh_rounded, size: 20), SizedBox(width: 8), Text('Coba lagi')],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

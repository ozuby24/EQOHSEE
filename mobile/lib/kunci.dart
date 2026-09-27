import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:local_auth/local_auth.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'tema.dart';

/// Pengaturan perangkat yang disimpan di ponsel: sudah melihat pengenalan
/// atau belum, dan apakah aplikasi dikunci sidik jari.
class Pengaturan extends ChangeNotifier {
  Pengaturan(this._pref);

  final SharedPreferences _pref;

  static const _kPengenalan = 'pengenalan_selesai_v1';
  static const _kKunci = 'kunci_biometrik';

  bool get sudahPengenalan => _pref.getBool(_kPengenalan) ?? false;
  bool get kunciAktif => _pref.getBool(_kKunci) ?? false;

  Future<void> selesaiPengenalan() async {
    await _pref.setBool(_kPengenalan, true);
    notifyListeners();
  }

  Future<void> aturKunci(bool aktif) async {
    await _pref.setBool(_kKunci, aktif);
    notifyListeners();
  }
}

/// Jeda sebelum aplikasi yang ditinggal di latar dikunci lagi.
///
/// Dua menit: cukup untuk menoleh ke radio atau membuka aplikasi kamera
/// tanpa harus memindai sidik jari lagi, tetapi ponsel yang tertinggal di
/// meja kantin tidak terbuka untuk siapa pun yang memungutnya.
const Duration jedaKunci = Duration(minutes: 2);

/// Apakah aplikasi yang baru kembali dari latar harus dikunci?
///
/// Fungsi murni supaya dapat diuji: satu kesalahan tanda di sini membuat
/// kunci tidak pernah menyala — atau menyala tiap kali aplikasi kamera
/// selesai mengambil foto.
bool perluKunci({required bool aktif, required DateTime? keLatar, required DateTime kini, Duration jeda = jedaKunci}) {
  if (!aktif || keLatar == null) return false;
  return !kini.difference(keLatar).isNegative && kini.difference(keLatar) >= jeda;
}

/// Coba buka kunci dengan sidik jari atau kunci layar perangkat.
Future<bool> bukaKunciBiometrik({String alasan = 'Buka EQOHSEE'}) async {
  try {
    return await LocalAuthentication().authenticate(
      localizedReason: alasan,
      biometricOnly: false,
      persistAcrossBackgrounding: true,
    );
  } on PlatformException {
    return false;
  } catch (_) {
    return false;
  }
}

Future<bool> biometrikTersedia() async {
  try {
    return await LocalAuthentication().isDeviceSupported();
  } catch (_) {
    return false;
  }
}

class LayarKunci extends StatefulWidget {
  const LayarKunci({super.key, required this.terbuka});

  final VoidCallback terbuka;

  @override
  State<LayarKunci> createState() => _LayarKunciState();
}

class _LayarKunciState extends State<LayarKunci> {
  bool _mencoba = false;
  bool _gagal = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _coba());
  }

  Future<void> _coba() async {
    if (_mencoba) return;
    setState(() { _mencoba = true; _gagal = false; });
    final ok = await bukaKunciBiometrik();
    if (!mounted) return;
    if (ok) {
      HapticFeedback.lightImpact();
      widget.terbuka();
    } else {
      setState(() { _mencoba = false; _gagal = true; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.light,
        systemNavigationBarColor: Warna.ink,
        systemNavigationBarIconBrightness: Brightness.light,
      ),
      child: Material(
        color: Warna.ink,
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(26, 18, 26, 22),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Wordmark(ukuran: 17),
                const Spacer(),
                Container(
                  width: 72,
                  height: 72,
                  decoration: BoxDecoration(color: Warna.sinyal, borderRadius: BorderRadius.circular(20)),
                  child: const Icon(Icons.lock_outline_rounded, size: 34, color: Warna.ink),
                ),
                const SizedBox(height: 26),
                const Text('Aplikasi terkunci',
                    style: TextStyle(color: Colors.white, fontSize: 29, fontWeight: FontWeight.w800, letterSpacing: -.6)),
                const SizedBox(height: 10),
                Text(
                  _gagal
                      ? 'Belum terbuka. Coba lagi dengan sidik jari atau kunci layar ponsel.'
                      : 'Data pekerja dan laporan dilindungi sidik jari atau kunci layar ponsel ini.',
                  style: const TextStyle(color: Color(0xB3FFFFFF), fontSize: 15, height: 1.5),
                ),
                const Spacer(),
                FilledButton(
                  onPressed: _mencoba ? null : _coba,
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: const [
                      Icon(Icons.fingerprint_rounded, size: 22),
                      SizedBox(width: 8),
                      Text('Buka kunci'),
                    ],
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

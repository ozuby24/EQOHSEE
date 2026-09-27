import 'package:flutter/material.dart';

/// Warna mode lapangan — sama persis dengan halaman web /lapangan,
/// supaya perpindahan dari layar native ke WebView tidak terasa berganti
/// aplikasi.
///
/// Aturan yang sama dengan web:
///   • tombol jingga memakai teks ink (kontras 7:1; putih hanya 2,7:1),
///   • warna status hanya untuk status,
///   • tanpa gradien, pendar, atau kaca buram.
class Warna {
  static const ink = Color(0xFF0B1117);
  static const inkLembut = Color(0xFF151D26);
  static const garisGelap = Color(0x1FFFFFFF);
  static const kertas = Color(0xFFF5F7F9);
  static const sinyal = Color(0xFFF57C00);
  static const sinyalTerang = Color(0xFFFF9800);
  static const jingga = Color(0xFFA85400);
  static const abu1 = Color(0xFF3A4450);
  static const abu3 = Color(0xFF667080);
  static const abuTerang = Color(0xFFB8C0C9);
  static const aman = Color(0xFF22C55E);
  static const bahaya = Color(0xFFDC2626);
}

ThemeData temaEqohsee() {
  final dasar = ThemeData(
    useMaterial3: true,
    // Huruf sistem Android, disebut namanya supaya tombol dan teks
    // memakai keluarga yang sama di semua layar native.
    fontFamily: 'Roboto',
    colorScheme: ColorScheme.fromSeed(
      seedColor: Warna.sinyal,
      primary: Warna.sinyal,
      onPrimary: Warna.ink,
      surface: Colors.white,
    ),
    scaffoldBackgroundColor: Warna.ink,
  );

  return dasar.copyWith(
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: Warna.sinyal,
        foregroundColor: Warna.ink,
        minimumSize: const Size.fromHeight(54),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        textStyle: const TextStyle(fontFamily: 'Roboto', fontSize: 16, fontWeight: FontWeight.w700),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: Colors.white,
        minimumSize: const Size.fromHeight(52),
        side: const BorderSide(color: Color(0x3DFFFFFF)),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        textStyle: const TextStyle(fontFamily: 'Roboto', fontSize: 15, fontWeight: FontWeight.w600),
      ),
    ),
    snackBarTheme: const SnackBarThemeData(
      behavior: SnackBarBehavior.floating,
      backgroundColor: Warna.ink,
      contentTextStyle: TextStyle(color: Colors.white, fontSize: 14),
      actionTextColor: Warna.sinyalTerang,
    ),
  );
}

/// Wordmark: E(Q)OHSEE dengan Q jingga, seperti di web.
class Wordmark extends StatelessWidget {
  const Wordmark({super.key, this.ukuran = 18, this.terang = true});

  final double ukuran;
  final bool terang;

  @override
  Widget build(BuildContext context) {
    final warna = terang ? Colors.white : Warna.ink;
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Image.asset('assets/merek.png', height: ukuran * 1.7, semanticLabel: 'EQOHSEE'),
        const SizedBox(width: 9),
        Text.rich(
          TextSpan(children: [
            TextSpan(text: 'E', style: TextStyle(color: warna)),
            const TextSpan(text: 'Q', style: TextStyle(color: Warna.sinyalTerang)),
            TextSpan(text: 'OHSEE', style: TextStyle(color: warna)),
          ]),
          style: TextStyle(fontSize: ukuran, fontWeight: FontWeight.w800, letterSpacing: -.2),
        ),
      ],
    );
  }
}

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
  static const inkTepi = Color(0xFF1C2631);
  static const garisGelap = Color(0x1FFFFFFF);
  static const garisTerang = Color(0xFFE2E6EB);
  static const kertas = Color(0xFFF5F7F9);
  static const sinyal = Color(0xFFF57C00);
  static const sinyalTerang = Color(0xFFFF9800);
  static const jingga = Color(0xFFA85400);
  static const jinggaLunak = Color(0xFFFFF0DE);
  static const abu1 = Color(0xFF3A4450);
  static const abu3 = Color(0xFF667080);
  static const abu4 = Color(0xFF9AA3AE);
  static const abuTerang = Color(0xFFB8C0C9);
  static const aman = Color(0xFF22C55E);
  static const waspada = Color(0xFFEAB308);
  static const bahaya = Color(0xFFDC2626);
  static const putih70 = Color(0xB3FFFFFF);
  static const putih50 = Color(0x80FFFFFF);
}

/// Keluarga huruf yang dibundel (lihat pubspec.yaml).
class Huruf {
  static const isi = 'Archivo';
  static const judul = 'ArchivoRapat';
  static const mono = 'PlexMono';
}

/// Judul besar: Archivo rapat, tebal, spasi huruf negatif — bentuk yang
/// sama dengan `.lp-judul` di web.
TextStyle gayaJudul({double ukuran = 32, Color warna = Colors.white, double tinggi = 1.0}) => TextStyle(
      fontFamily: Huruf.judul,
      fontSize: ukuran,
      height: tinggi,
      fontWeight: FontWeight.w800,
      letterSpacing: -ukuran * .022,
      color: warna,
    );

/// Label teknis: Plex Mono huruf kapital berjarak — nomor unit, kode
/// laporan, koordinat, langkah.
TextStyle gayaMono({double ukuran = 12, Color warna = Warna.sinyalTerang, FontWeight tebal = FontWeight.w600, double jarak = .08}) =>
    TextStyle(fontFamily: Huruf.mono, fontSize: ukuran, fontWeight: tebal, letterSpacing: ukuran * jarak, color: warna, height: 1.3);

TextStyle gayaIsi({double ukuran = 15.5, Color warna = Warna.putih70, double tinggi = 1.5, FontWeight tebal = FontWeight.w400}) =>
    TextStyle(fontFamily: Huruf.isi, fontSize: ukuran, height: tinggi, color: warna, fontWeight: tebal);

ThemeData temaEqohsee() {
  final dasar = ThemeData(
    useMaterial3: true,
    fontFamily: Huruf.isi,
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
        disabledBackgroundColor: const Color(0xFF6B4A24),
        disabledForegroundColor: const Color(0x99000000),
        minimumSize: const Size.fromHeight(56),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        textStyle: const TextStyle(fontFamily: Huruf.isi, fontSize: 16, fontWeight: FontWeight.w700, letterSpacing: -.1),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: Colors.white,
        minimumSize: const Size.fromHeight(52),
        side: const BorderSide(color: Color(0x3DFFFFFF)),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        textStyle: const TextStyle(fontFamily: Huruf.isi, fontSize: 15, fontWeight: FontWeight.w600),
      ),
    ),
    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(
        foregroundColor: Warna.jingga,
        textStyle: const TextStyle(fontFamily: Huruf.isi, fontSize: 15, fontWeight: FontWeight.w700),
      ),
    ),
    dialogTheme: DialogThemeData(
      backgroundColor: Colors.white,
      surfaceTintColor: Colors.transparent,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      titleTextStyle: const TextStyle(fontFamily: Huruf.isi, fontSize: 19, fontWeight: FontWeight.w800, color: Warna.ink, letterSpacing: -.3),
      contentTextStyle: const TextStyle(fontFamily: Huruf.isi, fontSize: 14.5, height: 1.5, color: Warna.abu1),
    ),
    snackBarTheme: const SnackBarThemeData(
      behavior: SnackBarBehavior.floating,
      backgroundColor: Warna.ink,
      contentTextStyle: TextStyle(fontFamily: Huruf.isi, color: Colors.white, fontSize: 14, height: 1.4),
      actionTextColor: Warna.sinyalTerang,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.all(Radius.circular(10))),
    ),
    dividerTheme: const DividerThemeData(color: Warna.garisTerang, thickness: 1, space: 1),
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
          style: TextStyle(fontFamily: Huruf.isi, fontSize: ukuran, fontWeight: FontWeight.w800, letterSpacing: -.3),
        ),
      ],
    );
  }
}

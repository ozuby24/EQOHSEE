import 'dart:math' as math;
import 'dart:typed_data';

import 'package:flutter/material.dart';

import 'tema.dart';

/// Gambar-gambar yang dilukis sendiri, bukan ikon bawaan.
///
/// Semuanya diambil dari benda yang memang ada di tambang dan di aplikasi
/// ini: peta kontur pit, matriks risiko 5×5, stempel "ditahan", retikel
/// GPS, batang sinyal. Tidak ada yang sekadar hiasan — tiap gambar
/// menerangkan sesuatu yang dilakukan aplikasi.

/* ═══════════ kontur topografi ═══════════ */

/// Latar bertekstur garis ketinggian sebuah pit terbuka: jenjang yang
/// melingkar dari satu pusat, sedikit terdistorsi seperti peta sungguhan.
///
/// Dihitung dengan marching squares atas medan halus, digambar sekali
/// (RepaintBoundary) — hampir tidak ada biaya saat layar lain beranimasi.
class Kontur extends StatelessWidget {
  const Kontur({
    super.key,
    this.pusat = const Offset(.84, .16),
    this.warna = const Color(0x14FFFFFF),
    this.warnaIndeks = const Color(0x30FFFFFF),
    this.warnaSorot = const Color(0x8CF57C00),
    this.tingkat = 16,
    this.sorot = 5,
    this.child,
  });

  /// Titik terdalam pit, dalam pecahan lebar/tinggi.
  final Offset pusat;
  final Color warna;
  /// Garis indeks: tiap garis kelima ditebalkan, seperti peta topografi.
  final Color warnaIndeks;
  /// Satu garis diberi warna jingga — supaya matanya punya pegangan.
  final Color warnaSorot;
  final int tingkat;
  final int sorot;
  final Widget? child;

  @override
  Widget build(BuildContext context) {
    return RepaintBoundary(
      child: CustomPaint(
        painter: _PelukisKontur(pusat, warna, warnaIndeks, warnaSorot, tingkat, sorot),
        child: child ?? const SizedBox.expand(),
      ),
    );
  }
}

class _PelukisKontur extends CustomPainter {
  _PelukisKontur(this.pusat, this.warna, this.warnaIndeks, this.warnaSorot, this.tingkat, this.sorot);

  final Offset pusat;
  final Color warna, warnaIndeks, warnaSorot;
  final int tingkat, sorot;

  static const _sel = 6.0;

  @override
  void paint(Canvas canvas, Size size) {
    final w = size.width, h = size.height;
    if (w <= 0 || h <= 0) return;
    final s = math.max(w, h);
    final cx = pusat.dx * w, cy = pusat.dy * h;

    double medan(double x, double y) {
      final dx = x - cx, dy = y - cy;
      final r = math.sqrt(dx * dx + dy * dy) / s;
      final t = math.atan2(dy, dx);
      return r +
          .075 * math.sin(3 * t + r * 7) +
          .05 * math.sin(x / s * 9.1 + 1.7) * math.cos(y / s * 7.3 - .4) +
          .03 * math.sin((x + y) / s * 12.0 + .9);
    }

    final nx = (w / _sel).ceil() + 1, ny = (h / _sel).ceil() + 1;
    final v = Float32List(nx * ny);
    var lo = double.infinity, hi = -double.infinity;
    for (var j = 0; j < ny; j++) {
      for (var i = 0; i < nx; i++) {
        final f = medan(i * _sel, j * _sel);
        v[j * nx + i] = f;
        if (f < lo) lo = f;
        if (f > hi) hi = f;
      }
    }

    final tipis = Path(), tebal = Path(), jingga = Path();
    for (var k = 1; k <= tingkat; k++) {
      final lvl = lo + (hi - lo) * k / (tingkat + 1);
      final ke = k == sorot ? jingga : (k % 5 == 0 ? tebal : tipis);
      for (var j = 0; j < ny - 1; j++) {
        for (var i = 0; i < nx - 1; i++) {
          final tl = v[j * nx + i], tr = v[j * nx + i + 1];
          final bl = v[(j + 1) * nx + i], br = v[(j + 1) * nx + i + 1];
          final idx = (tl > lvl ? 8 : 0) | (tr > lvl ? 4 : 0) | (br > lvl ? 2 : 0) | (bl > lvl ? 1 : 0);
          if (idx == 0 || idx == 15) continue;
          final x0 = i * _sel, y0 = j * _sel, x1 = x0 + _sel, y1 = y0 + _sel;
          Offset t() => Offset(x0 + _sel * (lvl - tl) / (tr - tl), y0);
          Offset b() => Offset(x0 + _sel * (lvl - bl) / (br - bl), y1);
          Offset l() => Offset(x0, y0 + _sel * (lvl - tl) / (bl - tl));
          Offset r() => Offset(x1, y0 + _sel * (lvl - tr) / (br - tr));
          void seg(Offset a, Offset c) => ke..moveTo(a.dx, a.dy)..lineTo(c.dx, c.dy);
          switch (idx) {
            case 1: case 14: seg(l(), b());
            case 2: case 13: seg(b(), r());
            case 3: case 12: seg(l(), r());
            case 4: case 11: seg(t(), r());
            case 6: case 9: seg(t(), b());
            case 7: case 8: seg(t(), l());
            case 5: seg(t(), r()); seg(l(), b());
            case 10: seg(t(), l()); seg(b(), r());
          }
        }
      }
    }

    final cat = Paint()..style = PaintingStyle.stroke..strokeCap = StrokeCap.round;
    canvas.drawPath(tipis, cat..color = warna..strokeWidth = 1);
    canvas.drawPath(tebal, cat..color = warnaIndeks..strokeWidth = 1.6);
    canvas.drawPath(jingga, cat..color = warnaSorot..strokeWidth = 1.4);
  }

  @override
  bool shouldRepaint(_PelukisKontur o) =>
      o.pusat != pusat || o.warna != warna || o.warnaIndeks != warnaIndeks || o.tingkat != tingkat || o.sorot != sorot;
}

/* ═══════════ bingkai teknis ═══════════ */

/// Bidang gambar dengan tanda sudut seperti bidikan kamera atau lembar
/// gambar teknik, di atas kisi garis rambut.
class BingkaiTeknis extends StatelessWidget {
  const BingkaiTeknis({super.key, required this.child, this.label, this.kisi = true});

  final Widget child;
  final String? label;
  final bool kisi;

  @override
  Widget build(BuildContext context) {
    return CustomPaint(
      painter: _PelukisBingkai(kisi),
      child: Stack(
        children: [
          Positioned.fill(child: child),
          if (label != null)
            Positioned(
              right: 12,
              bottom: 8,
              child: Text(label!, style: gayaMono(ukuran: 9.5, warna: Warna.putih50, tebal: FontWeight.w500)),
            ),
        ],
      ),
    );
  }
}

class _PelukisBingkai extends CustomPainter {
  _PelukisBingkai(this.kisi);
  final bool kisi;

  @override
  void paint(Canvas canvas, Size size) {
    if (kisi) {
      final k = Paint()..color = const Color(0x0DFFFFFF)..strokeWidth = 1;
      for (var x = 24.0; x < size.width; x += 24) {
        canvas.drawLine(Offset(x, 0), Offset(x, size.height), k);
      }
      for (var y = 24.0; y < size.height; y += 24) {
        canvas.drawLine(Offset(0, y), Offset(size.width, y), k);
      }
    }
    final p = Paint()..color = const Color(0x73FFFFFF)..strokeWidth = 1.5..strokeCap = StrokeCap.square;
    const l = 14.0;
    final w = size.width, h = size.height;
    for (final (x, y, sx, sy) in [(0.0, 0.0, 1, 1), (w, 0.0, -1, 1), (0.0, h, 1, -1), (w, h, -1, -1)]) {
      canvas.drawLine(Offset(x, y), Offset(x + l * sx, y), p);
      canvas.drawLine(Offset(x, y), Offset(x, y + l * sy), p);
    }
  }

  @override
  bool shouldRepaint(_PelukisBingkai o) => o.kisi != kisi;
}

/* ═══════════ matriks risiko 5×5 ═══════════ */

/// Pita risiko dan warnanya — sama dengan App\Support\RisikoLapangan dan
/// WARNA pada Lapor.vue. Skor = kemungkinan × keparahan.
class PitaRisiko {
  const PitaRisiko._(this.nama, this.lunak, this.teks, this.kuat, this.di, this.tenggat);
  final String nama;
  final Color lunak, teks, kuat, di;
  final String tenggat;

  static const ekstrem = PitaRisiko._('Ekstrem', Color(0xFFFDDEDE), Color(0xFFB91C1C), Color(0xFFDC2626), Colors.white, 'HARI INI');
  static const tinggi = PitaRisiko._('Tinggi', Color(0xFFFFE4C7), Color(0xFF9A4A00), Color(0xFFF57C00), Warna.ink, '24 JAM');
  static const sedang = PitaRisiko._('Sedang', Color(0xFFFBF1C7), Color(0xFF854D0E), Color(0xFFFACC15), Warna.ink, '72 JAM');
  static const rendah = PitaRisiko._('Rendah', Color(0xFFDDF3E4), Color(0xFF166534), Color(0xFF22C55E), Warna.ink, '7 HARI');

  static PitaRisiko dari(int skor) => skor >= 15 ? ekstrem : skor >= 8 ? tinggi : skor >= 4 ? sedang : rendah;
}

/// Matriks 5×5 seperti di layar Lapor bahaya, dengan satu sel dipilih.
class Matriks5x5 extends StatelessWidget {
  const Matriks5x5({super.key, this.kemungkinan = 4, this.keparahan = 4, this.denyut = 0, this.sumbu = true});

  final int kemungkinan, keparahan;
  /// 0..1 — cincin sorot mengembang, dipakai animasi.
  final double denyut;
  final bool sumbu;

  @override
  Widget build(BuildContext context) {
    return CustomPaint(painter: _PelukisMatriks(kemungkinan, keparahan, denyut, sumbu));
  }
}

class _PelukisMatriks extends CustomPainter {
  _PelukisMatriks(this.k, this.s, this.denyut, this.sumbu);
  final int k, s;
  final double denyut;
  final bool sumbu;

  @override
  void paint(Canvas canvas, Size size) {
    final tepiKiri = sumbu ? 14.0 : 0.0, tepiBawah = sumbu ? 16.0 : 0.0;
    final sel = math.min((size.width - tepiKiri) / 5, (size.height - tepiBawah) / 5);
    const celah = 2.5;
    final x0 = tepiKiri, y0 = 0.0;

    for (var baris = 0; baris < 5; baris++) {
      final kem = 5 - baris;
      for (var kol = 0; kol < 5; kol++) {
        final kep = kol + 1;
        final skor = kem * kep;
        final pita = PitaRisiko.dari(skor);
        final dipilih = kem == k && kep == s;
        final r = Rect.fromLTWH(x0 + kol * sel + celah / 2, y0 + baris * sel + celah / 2, sel - celah, sel - celah);
        final rr = RRect.fromRectAndRadius(r, const Radius.circular(4));
        canvas.drawRRect(rr, Paint()..color = dipilih ? pita.kuat : pita.lunak);
        _teks(canvas, '$skor', gayaMono(ukuran: sel * .34, warna: dipilih ? pita.di : pita.teks, jarak: 0), r.center);
        if (dipilih) {
          canvas.drawRRect(rr, Paint()..style = PaintingStyle.stroke..strokeWidth = 2..color = Warna.ink);
          final cincin = rr.inflate(3 + 5 * denyut);
          canvas.drawRRect(
            cincin,
            Paint()
              ..style = PaintingStyle.stroke
              ..strokeWidth = 1.5
              ..color = Warna.sinyalTerang.withValues(alpha: 1 - .8 * denyut),
          );
        }
      }
    }

    if (sumbu) {
      final g = gayaMono(ukuran: 7.5, warna: Warna.putih50, tebal: FontWeight.w500, jarak: .04);
      // Sumbu seperti pada lembar: angka tiap kolom, nama sumbu di ujung.
      for (var kol = 0; kol < 4; kol++) {
        _teks(canvas, '${kol + 1}', g, Offset(x0 + kol * sel + sel / 2, y0 + 5 * sel + 8));
      }
      _teks(canvas, '5 KEPARAHAN', g, Offset(x0 + 4 * sel + sel / 2 + 10, y0 + 5 * sel + 8));
      canvas.save();
      canvas.translate(4, y0 + 2.5 * sel);
      canvas.rotate(-math.pi / 2);
      _teks(canvas, 'KEMUNGKINAN', g, Offset.zero);
      canvas.restore();
    }
  }

  void _teks(Canvas c, String t, TextStyle g, Offset pusat) {
    final tp = TextPainter(text: TextSpan(text: t, style: g), textDirection: TextDirection.ltr)..layout();
    tp.paint(c, pusat - Offset(tp.width / 2, tp.height / 2));
  }

  @override
  bool shouldRepaint(_PelukisMatriks o) => o.k != k || o.s != s || o.denyut != denyut || o.sumbu != sumbu;
}

/* ═══════════ retikel GPS ═══════════ */

class Retikel extends StatelessWidget {
  const Retikel({super.key, this.ukuran = 64, this.warna = Warna.sinyalTerang, this.denyut = 0});
  final double ukuran;
  final Color warna;
  final double denyut;

  @override
  Widget build(BuildContext context) =>
      SizedBox.square(dimension: ukuran, child: CustomPaint(painter: _PelukisRetikel(warna, denyut)));
}

class _PelukisRetikel extends CustomPainter {
  _PelukisRetikel(this.warna, this.denyut);
  final Color warna;
  final double denyut;

  @override
  void paint(Canvas canvas, Size size) {
    final c = size.center(Offset.zero);
    final r = size.shortestSide / 2 - 2;
    final p = Paint()..style = PaintingStyle.stroke..strokeWidth = 1.5..color = warna..strokeCap = StrokeCap.round;
    canvas.drawCircle(c, r * .62, p);
    for (final a in [0.0, math.pi / 2, math.pi, 3 * math.pi / 2]) {
      final d = Offset(math.cos(a), math.sin(a));
      canvas.drawLine(c + d * (r * .62 - 7), c + d * (r * .62 + 8), p);
    }
    canvas.drawCircle(c, 2.6, Paint()..color = warna);
    // Cincin akurasi yang mengembang: "± 4 m" sedang dicari.
    canvas.drawCircle(c, r * (.62 + .38 * denyut),
        Paint()..style = PaintingStyle.stroke..strokeWidth = 1..color = warna.withValues(alpha: .7 * (1 - denyut)));
  }

  @override
  bool shouldRepaint(_PelukisRetikel o) => o.warna != warna || o.denyut != denyut;
}

/* ═══════════ stempel ═══════════ */

/// Stempel karet: dua garis tepi, huruf mono berjarak, sedikit miring.
class Stempel extends StatelessWidget {
  const Stempel(this.teks, {super.key, this.warna = Warna.bahaya, this.sudut = -.12, this.ukuran = 13});
  final String teks;
  final Color warna;
  final double sudut, ukuran;

  @override
  Widget build(BuildContext context) {
    return Transform.rotate(
      angle: sudut,
      child: Container(
        padding: const EdgeInsets.all(2.5),
        decoration: BoxDecoration(border: Border.all(color: warna, width: 2.5), borderRadius: BorderRadius.circular(5)),
        child: Container(
          padding: EdgeInsets.symmetric(horizontal: ukuran * .9, vertical: ukuran * .42),
          decoration: BoxDecoration(border: Border.all(color: warna, width: 1), borderRadius: BorderRadius.circular(3)),
          child: Text(teks, style: gayaMono(ukuran: ukuran, warna: warna, jarak: .16)),
        ),
      ),
    );
  }
}

/* ═══════════ batang sinyal ═══════════ */

class BatangSinyal extends StatelessWidget {
  const BatangSinyal({super.key, this.hidup = 0, this.ukuran = 40, this.warna = Warna.sinyalTerang, this.mati = const Color(0x40FFFFFF)});
  final int hidup;
  final double ukuran;
  final Color warna, mati;

  @override
  Widget build(BuildContext context) =>
      SizedBox(width: ukuran, height: ukuran, child: CustomPaint(painter: _PelukisSinyal(hidup, warna, mati)));
}

class _PelukisSinyal extends CustomPainter {
  _PelukisSinyal(this.hidup, this.warna, this.mati);
  final int hidup;
  final Color warna, mati;

  @override
  void paint(Canvas canvas, Size size) {
    final lebar = size.width / 4 * .62, celah = size.width / 4 * .38;
    for (var i = 0; i < 4; i++) {
      final t = (i + 1) / 4;
      final x = i * (lebar + celah);
      final r = RRect.fromRectAndRadius(
          Rect.fromLTWH(x, size.height * (1 - t), lebar, size.height * t), Radius.circular(lebar * .3));
      if (i < hidup) {
        canvas.drawRRect(r, Paint()..color = warna);
      } else {
        canvas.drawRRect(r.deflate(.75), Paint()..style = PaintingStyle.stroke..strokeWidth = 1.5..color = mati);
      }
    }
    if (hidup == 0) {
      canvas.drawLine(Offset(size.width * .08, size.height * .92), Offset(size.width * .92, size.height * .08),
          Paint()..color = warna..strokeWidth = 2.2..strokeCap = StrokeCap.round);
    }
  }

  @override
  bool shouldRepaint(_PelukisSinyal o) => o.hidup != hidup || o.warna != warna;
}

/* ═══════════ sidik jari ═══════════ */

class SidikJari extends StatelessWidget {
  const SidikJari({super.key, this.ukuran = 96, this.warna = Warna.sinyalTerang, this.kemajuan = 1});
  final double ukuran;
  final Color warna;
  /// 0..1 — berapa bagian guratan yang sudah "terbaca".
  final double kemajuan;

  @override
  Widget build(BuildContext context) =>
      SizedBox.square(dimension: ukuran, child: CustomPaint(painter: _PelukisSidikJari(warna, kemajuan)));
}

class _PelukisSidikJari extends CustomPainter {
  _PelukisSidikJari(this.warna, this.kemajuan);
  final Color warna;
  final double kemajuan;

  @override
  void paint(Canvas canvas, Size size) {
    final c = size.center(Offset.zero) + Offset(0, size.height * .04);
    final r0 = size.shortestSide * .085;
    final p = Paint()..style = PaintingStyle.stroke..strokeWidth = size.shortestSide * .04..strokeCap = StrokeCap.round;
    // Guratan: lengkung sepusat yang tiap lapisnya terputus di tempat
    // berbeda — jari, bukan sasaran tembak.
    const gurat = [
      [(-2.6, 2.6)],
      [(-2.9, -.2), (.4, 2.9)],
      [(-3.0, 1.4), (1.9, 3.0)],
      [(-2.4, -1.1), (-.7, 2.2), (2.6, 3.1)],
      [(-3.1, .2), (.9, 2.6)],
      [(-2.7, -1.6), (-1.1, 1.7), (2.2, 2.9)],
    ];
    final n = gurat.length;
    for (var i = 0; i < n; i++) {
      final r = r0 + i * size.shortestSide * .068;
      final terbaca = kemajuan * n > i;
      p.color = warna.withValues(alpha: terbaca ? (1 - i * .08) : .18);
      for (final (a, b) in gurat[i]) {
        canvas.drawArc(Rect.fromCircle(center: c, radius: r), a + math.pi / 2, b - a, false, p);
      }
    }
  }

  @override
  bool shouldRepaint(_PelukisSidikJari o) => o.warna != warna || o.kemajuan != kemajuan;
}

/* ═══════════ pin lokasi ═══════════ */

class PinLokasi extends StatelessWidget {
  const PinLokasi({super.key, this.ukuran = 72, this.denyut = 0});
  final double ukuran;
  final double denyut;

  @override
  Widget build(BuildContext context) =>
      SizedBox(width: ukuran, height: ukuran * 1.3, child: CustomPaint(painter: _PelukisPin(denyut)));
}

class _PelukisPin extends CustomPainter {
  _PelukisPin(this.denyut);
  final double denyut;

  @override
  void paint(Canvas canvas, Size size) {
    final r = size.width * .36;
    final c = Offset(size.width / 2, r + 2);
    final ujung = Offset(size.width / 2, size.height - 6);

    // Lingkar akurasi di tanah, mengembang.
    final tanah = Rect.fromCenter(center: ujung, width: size.width * (.55 + .45 * denyut), height: size.width * (.2 + .16 * denyut));
    canvas.drawOval(tanah, Paint()..style = PaintingStyle.stroke..strokeWidth = 1.2..color = Warna.sinyalTerang.withValues(alpha: .8 * (1 - denyut)));
    canvas.drawOval(Rect.fromCenter(center: ujung, width: size.width * .3, height: size.width * .1), Paint()..color = const Color(0x40000000));

    final jalur = Path()
      ..moveTo(ujung.dx, ujung.dy)
      ..lineTo(c.dx - r * .82, c.dy + r * .57)
      ..arcToPoint(Offset(c.dx + r * .82, c.dy + r * .57), radius: Radius.circular(r), largeArc: true)
      ..close();
    canvas.drawPath(jalur, Paint()..color = Warna.sinyal);
    canvas.drawCircle(c, r * .42, Paint()..color = Warna.ink);
    canvas.drawCircle(c, r * .16, Paint()..color = Warna.sinyalTerang);
  }

  @override
  bool shouldRepaint(_PelukisPin o) => o.denyut != denyut;
}

/* ═══════════ garis bahaya ═══════════ */

/// Pita belang jingga–ink seperti rambu di site. Dipakai tipis, satu
/// tempat per layar — bukan pola latar.
class GarisBahaya extends StatelessWidget {
  const GarisBahaya({super.key, this.tinggi = 5, this.lebar});
  final double tinggi;
  final double? lebar;

  @override
  Widget build(BuildContext context) =>
      SizedBox(height: tinggi, width: lebar ?? double.infinity, child: const CustomPaint(painter: _PelukisBelang()));
}

class _PelukisBelang extends CustomPainter {
  const _PelukisBelang();

  @override
  void paint(Canvas canvas, Size size) {
    canvas.clipRect(Offset.zero & size);
    canvas.drawRect(Offset.zero & size, Paint()..color = Warna.sinyal);
    final p = Paint()..color = Warna.ink..strokeWidth = size.height * 1.2;
    for (var x = -size.height * 2; x < size.width + size.height * 2; x += size.height * 3.2) {
      canvas.drawLine(Offset(x, size.height + 1), Offset(x + size.height * 1.4, -1), p);
    }
  }

  @override
  bool shouldRepaint(_PelukisBelang o) => false;
}

/* ═══════════ denyut ═══════════ */

/// Nilai 0→1→0 yang berulang pelan, untuk cincin akurasi dan sorot sel.
///
/// Diam bila sistem meminta animasi dimatikan (aksesibilitas) — dan itu
/// pula yang membuat tangkapan layar uji dapat "settle".
class Denyut extends StatefulWidget {
  const Denyut({super.key, required this.builder, this.lama = const Duration(milliseconds: 1900)});
  final Widget Function(BuildContext, double) builder;
  final Duration lama;

  @override
  State<Denyut> createState() => _DenyutState();
}

class _DenyutState extends State<Denyut> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: widget.lama);
  bool _jalan = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final diam = MediaQuery.disableAnimationsOf(context);
    if (!diam && !_jalan) {
      _jalan = true;
      _c.repeat();
    } else if (diam && _jalan) {
      _jalan = false;
      _c.stop();
    }
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _c,
      builder: (context, _) => widget.builder(context, Curves.easeOut.transform(_c.value)),
    );
  }
}

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:permission_handler/permission_handler.dart';

import 'lukisan.dart';
import 'tema.dart';

/// Pengenalan pertama kali: tiga hal yang membedakan aplikasi ini dari
/// membuka situsnya di peramban, lalu SATU layar izin yang menjelaskan
/// untuk apa izinnya dipakai sebelum sistem menanyakannya.
///
/// Layar izin itu bukan hiasan. Kebijakan Play meminta pengungkapan yang
/// jelas sebelum permintaan izin lokasi, dan pekerja yang ditanya "izinkan
/// lokasi?" tanpa alasan lebih sering menolak — lalu laporan bahayanya
/// terkirim tanpa titik GPS.
///
/// Tiap slide bergambar benda yang sungguh ada di aplikasi — matriks
/// yang sama, stempel yang sama — bukan ikon di dalam kotak.
class LayarPengenalan extends StatefulWidget {
  const LayarPengenalan({super.key, required this.selesai});

  /// Dipanggil sekali, sesudah layar izin dijawab (diizinkan atau tidak).
  final VoidCallback selesai;

  @override
  State<LayarPengenalan> createState() => _LayarPengenalanState();
}

class _Slide {
  const _Slide(this.label, this.judul, this.isi, this.spek, this.gambar);
  final String label;
  final String judul;
  final String isi;
  final List<(String, String)> spek;
  final Widget gambar;
}

const _slide = <_Slide>[
  _Slide(
    '01 · LAPOR BAHAYA',
    'Tanggul tergerus? Foto, ketuk satu sel, kirim.',
    'Titik GPS terisi sendiri. Satu sel pada matriks 5×5 menetapkan tingkat '
        'risiko dan tenggat tindak lanjutnya — pelapor tidak perlu menghafal '
        'aturan, pengawas tidak perlu menebak.',
    [('MATRIKS', '5×5, tenggat otomatis'), ('LOKASI', 'GPS ± akurasi, boleh ditulis'), ('FOTO', 'kamera ponsel, tanpa izin')],
    _GambarLapor(),
  ),
  _Slide(
    '02 · P2H UNIT',
    'Rem parkir gagal. Unit ditahan, bukan dicatat.',
    'Daftar periksa pra-operasi per jenis unit, tombol besar untuk jari '
        'bersarung tangan. Satu butir kritis gagal langsung menahan unit dan '
        'menerbitkan perintah kerja untuk mekanik.',
    [('JAWABAN', 'OK · Tidak · N/A'), ('BUTIR KRITIS', 'menahan unit seketika'), ('TINDAK LANJUT', 'perintah kerja mekanik')],
    _GambarP2h(),
  ),
  _Slide(
    '03 · TANPA SINYAL',
    'Sinyal hilang di pit. Laporan tetap jalan.',
    'Laporan dan P2H tersimpan di ponsel beserta fotonya, lalu terkirim '
        'sendiri begitu sinyal kembali. Tidak ada yang hilang, tidak ada yang '
        'terkirim dua kali.',
    [('DRAF', 'tersimpan tiap ketikan'), ('ANTREAN', 'foto ikut, urut waktu'), ('KIRIM', 'otomatis saat sinyal')],
    _GambarLuring(),
  ),
];

class _LayarPengenalanState extends State<LayarPengenalan> {
  final _halaman = PageController();
  int _ke = 0;
  bool _meminta = false;

  int get _jumlah => _slide.length + 1;
  bool get _diIzin => _ke == _slide.length;

  void _lanjut() {
    HapticFeedback.selectionClick();
    _halaman.nextPage(duration: const Duration(milliseconds: 320), curve: Curves.easeOutCubic);
  }

  void _lewati() => _halaman.animateToPage(_slide.length, duration: const Duration(milliseconds: 380), curve: Curves.easeOutCubic);

  Future<void> _izinkan() async {
    setState(() => _meminta = true);
    await Permission.locationWhenInUse.request();
    if (!mounted) return;
    widget.selesai();
  }

  @override
  void dispose() {
    _halaman.dispose();
    super.dispose();
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
      child: Scaffold(
        backgroundColor: Warna.ink,
        body: Stack(
          children: [
            // Kontur hanya tampak di layar izin: yang lain latarnya gambar sendiri.
            Positioned.fill(
              child: AnimatedOpacity(
                opacity: _diIzin ? 1 : 0,
                duration: const Duration(milliseconds: 420),
                child: const Kontur(),
              ),
            ),
            SafeArea(
              child: Column(
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(22, 14, 10, 0),
                    child: Row(
                      children: [
                        const Wordmark(ukuran: 17),
                        const Spacer(),
                        Text('${_ke + 1}'.padLeft(2, '0'), style: gayaMono(ukuran: 12, warna: Colors.white)),
                        Text(' / ${'$_jumlah'.padLeft(2, '0')}', style: gayaMono(ukuran: 12, warna: Warna.putih50)),
                        const SizedBox(width: 6),
                        AnimatedOpacity(
                          opacity: _diIzin ? 0 : 1,
                          duration: const Duration(milliseconds: 200),
                          child: TextButton(
                            onPressed: _diIzin ? null : _lewati,
                            style: TextButton.styleFrom(foregroundColor: Colors.white70, minimumSize: const Size(56, 44)),
                            child: const Text('Lewati', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600)),
                          ),
                        ),
                      ],
                    ),
                  ),
                  Expanded(
                    child: PageView.builder(
                      controller: _halaman,
                      itemCount: _jumlah,
                      onPageChanged: (i) => setState(() => _ke = i),
                      itemBuilder: (_, i) => i < _slide.length ? _SlideTampil(s: _slide[i]) : const _LayarIzin(),
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(22, 6, 22, 16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Row(
                          children: List.generate(_jumlah, (i) {
                            return AnimatedContainer(
                              duration: const Duration(milliseconds: 240),
                              margin: const EdgeInsets.only(right: 5),
                              width: i == _ke ? 30 : 14,
                              height: 3,
                              color: i == _ke ? Warna.sinyal : (i < _ke ? Warna.putih50 : const Color(0x33FFFFFF)),
                            );
                          }),
                        ),
                        const SizedBox(height: 16),
                        if (!_diIzin)
                          FilledButton(
                            onPressed: _lanjut,
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Flexible(child: Text(_ke == _slide.length - 1 ? 'Satu langkah lagi' : 'Lanjut', overflow: TextOverflow.ellipsis)),
                                const SizedBox(width: 8),
                                const Icon(Icons.arrow_forward_rounded, size: 20),
                              ],
                            ),
                          )
                        else ...[
                          FilledButton(
                            onPressed: _meminta ? null : _izinkan,
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: const [
                                Icon(Icons.my_location_rounded, size: 20),
                                SizedBox(width: 8),
                                Flexible(child: Text('Izinkan lokasi & mulai', overflow: TextOverflow.ellipsis)),
                              ],
                            ),
                          ),
                          const SizedBox(height: 6),
                          TextButton(
                            onPressed: _meminta ? null : widget.selesai,
                            style: TextButton.styleFrom(foregroundColor: Colors.white70, minimumSize: const Size.fromHeight(46)),
                            child: const Text('Nanti saja', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600)),
                          ),
                        ],
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/* ═══════════ slide ═══════════ */

class _SlideTampil extends StatelessWidget {
  const _SlideTampil({required this.s});
  final _Slide s;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(builder: (context, batas) {
      final tinggiGambar = (batas.maxHeight * .43).clamp(220.0, 316.0);
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(22, 12, 22, 0),
            child: SizedBox(
              height: tinggiGambar,
              width: double.infinity,
              child: BingkaiTeknis(label: s.label.split(' · ').last, child: s.gambar),
            ),
          ),
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(22, 22, 22, 8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(children: [
                    Container(width: 18, height: 2, color: Warna.sinyal),
                    const SizedBox(width: 8),
                    Text(s.label, style: gayaMono(ukuran: 11.5)),
                  ]),
                  const SizedBox(height: 10),
                  Text(s.judul, style: gayaJudul(ukuran: 33)),
                  const SizedBox(height: 12),
                  Text(s.isi, style: gayaIsi(ukuran: 15)),
                  const SizedBox(height: 16),
                  _TabelSpek(s.spek),
                ],
              ),
            ),
          ),
        ],
      );
    });
  }
}

/// Tiga baris "label — nilai" dengan garis rambut, seperti kolom
/// spesifikasi pada lembar unit. Bukan chip centang.
class _TabelSpek extends StatelessWidget {
  const _TabelSpek(this.baris);
  final List<(String, String)> baris;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        for (final (i, (label, nilai)) in baris.indexed)
          Container(
            padding: const EdgeInsets.symmetric(vertical: 8),
            decoration: BoxDecoration(
              border: Border(
                top: BorderSide(color: i == 0 ? const Color(0x40FFFFFF) : Warna.garisGelap),
                bottom: i == baris.length - 1 ? const BorderSide(color: Warna.garisGelap) : BorderSide.none,
              ),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.baseline,
              textBaseline: TextBaseline.alphabetic,
              children: [
                SizedBox(width: 108, child: Text(label, style: gayaMono(ukuran: 10.5, warna: Warna.putih50))),
                Expanded(child: Text(nilai, style: gayaIsi(ukuran: 14, warna: Colors.white, tebal: FontWeight.w600, tinggi: 1.3))),
              ],
            ),
          ),
      ],
    );
  }
}

/* ═══════════ gambar 01: matriks + retikel ═══════════ */

class _GambarLapor extends StatelessWidget {
  const _GambarLapor();

  @override
  Widget build(BuildContext context) {
    return Denyut(builder: (context, d) {
      return Padding(
        padding: const EdgeInsets.fromLTRB(18, 18, 18, 22),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Expanded(flex: 11, child: Matriks5x5(kemungkinan: 4, keparahan: 4, denyut: d)),
            const SizedBox(width: 16),
            Expanded(
              flex: 9,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(children: [
                    Retikel(ukuran: 46, denyut: d),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text('-2.98712', style: gayaMono(ukuran: 10.5, warna: Colors.white, jarak: .02)),
                        Text('115.44128', style: gayaMono(ukuran: 10.5, warna: Colors.white, jarak: .02)),
                        Text('± 4 m', style: gayaMono(ukuran: 9.5, warna: Warna.putih50, tebal: FontWeight.w500)),
                      ]),
                    ),
                  ]),
                  const Spacer(),
                  Text('4 × 4', style: gayaMono(ukuran: 10, warna: Warna.putih50, tebal: FontWeight.w500)),
                  const SizedBox(height: 2),
                  Text('16', style: gayaJudul(ukuran: 40)),
                  const SizedBox(height: 6),
                  _Pil('EKSTREM', PitaRisiko.ekstrem.kuat, Colors.white),
                  const SizedBox(height: 6),
                  Text('TINDAK LANJUT', style: gayaMono(ukuran: 8.5, warna: Warna.putih50, tebal: FontWeight.w500)),
                  Text('HARI INI', style: gayaMono(ukuran: 11.5, warna: Warna.sinyalTerang)),
                ],
              ),
            ),
          ],
        ),
      );
    });
  }
}

class _Pil extends StatelessWidget {
  const _Pil(this.teks, this.latar, this.warna);
  final String teks;
  final Color latar, warna;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(color: latar, borderRadius: BorderRadius.circular(4)),
      child: Text(teks, style: gayaMono(ukuran: 10, warna: warna, jarak: .12)),
    );
  }
}

/* ═══════════ gambar 02: lembar P2H + stempel ═══════════ */

class _GambarP2h extends StatelessWidget {
  const _GambarP2h();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(18, 18, 18, 18),
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Container(
            decoration: BoxDecoration(
              color: Warna.inkLembut,
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: const Color(0x33FFFFFF)),
            ),
            clipBehavior: Clip.antiAlias,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const GarisBahaya(tinggi: 4),
                Padding(
                  padding: const EdgeInsets.fromLTRB(12, 10, 12, 8),
                  child: Row(children: [
                    Text('LV-018', style: gayaMono(ukuran: 11.5, warna: Colors.white)),
                    Text('  ·  HM 61.230', style: gayaMono(ukuran: 10, warna: Warna.putih50, tebal: FontWeight.w500)),
                    const Spacer(),
                    Text('SHIFT PAGI', style: gayaMono(ukuran: 9, warna: Warna.putih50, tebal: FontWeight.w500)),
                  ]),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(12, 0, 12, 8),
                  child: Text('Toyota Hilux 4×4 — light vehicle', style: gayaIsi(ukuran: 12.5, warna: Colors.white, tebal: FontWeight.w600, tinggi: 1.2)),
                ),
                const Divider(color: Color(0x26FFFFFF)),
                const _BarisP2h('Rem berfungsi normal', _Jawab.ok),
                const Divider(color: Color(0x26FFFFFF)),
                const _BarisP2h('Rem parkir menahan', _Jawab.tidak, kritis: true),
                const Divider(color: Color(0x26FFFFFF)),
                const _BarisP2h('Kemudi tanpa kelonggaran', _Jawab.ok),
                const Divider(color: Color(0x26FFFFFF)),
                const _BarisP2h('Lampu kerja & rotari', _Jawab.na),
              ],
            ),
          ),
          const Positioned(right: -6, bottom: 4, child: Stempel('UNIT DITAHAN', ukuran: 14)),
          Positioned(
            left: 12,
            bottom: 10,
            child: Text('WO-0412 › mekanik', style: gayaMono(ukuran: 9.5, warna: Warna.sinyalTerang, tebal: FontWeight.w500)),
          ),
        ],
      ),
    );
  }
}

enum _Jawab { ok, tidak, na }

class _BarisP2h extends StatelessWidget {
  const _BarisP2h(this.teks, this.jawab, {this.kritis = false});
  final String teks;
  final _Jawab jawab;
  final bool kritis;

  @override
  Widget build(BuildContext context) {
    final (label, latar, warna) = switch (jawab) {
      _Jawab.ok => ('OK', Warna.aman, Warna.ink),
      _Jawab.tidak => ('TIDAK', Warna.bahaya, Colors.white),
      _Jawab.na => ('N/A', const Color(0x26FFFFFF), Warna.putih70),
    };
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
      child: Row(children: [
        Expanded(child: Text(teks, style: gayaIsi(ukuran: 12.5, warna: Colors.white, tebal: FontWeight.w500, tinggi: 1.2))),
        if (kritis) ...[
          Text('KRITIS', style: gayaMono(ukuran: 8.5, warna: const Color(0xFFFF8A80))),
          const SizedBox(width: 8),
        ],
        _Pil(label, latar, warna),
      ]),
    );
  }
}

/* ═══════════ gambar 03: antrean tanpa sinyal ═══════════ */

class _GambarLuring extends StatelessWidget {
  const _GambarLuring();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(18, 18, 18, 18),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(
            width: 86,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const BatangSinyal(hidup: 0, ukuran: 40),
                const SizedBox(height: 10),
                Text('0 BAR', style: gayaMono(ukuran: 11, warna: Colors.white)),
                Text('PIT UTARA', style: gayaMono(ukuran: 9, warna: Warna.putih50, tebal: FontWeight.w500)),
                Text('RL +128', style: gayaMono(ukuran: 9, warna: Warna.putih50, tebal: FontWeight.w500)),
                const Spacer(),
                Text('DISIMPAN', style: gayaMono(ukuran: 8.5, warna: Warna.putih50, tebal: FontWeight.w500)),
                Text('DI PONSEL', style: gayaMono(ukuran: 8.5, warna: Warna.putih50, tebal: FontWeight.w500)),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Stack(
              children: [
                Positioned(left: 16, right: 0, top: 0, child: _Tiket(redup: 2, waktu: '06:52', isi: 'P2H · DT-1142')),
                Positioned(left: 8, right: 4, top: 26, child: _Tiket(redup: 1, waktu: '07:40', isi: 'HZD · Jalan R-04')),
                Positioned(left: 0, right: 8, top: 52, child: _Tiket(redup: 0, waktu: '08:15', isi: 'HZD · Tanggul KM 2,3')),
                Positioned(
                  left: 0,
                  right: 8,
                  bottom: 0,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(children: [
                        Container(width: 6, height: 6, decoration: const BoxDecoration(color: Warna.sinyalTerang, shape: BoxShape.circle)),
                        const SizedBox(width: 6),
                        Text('3 MENUNGGU · 7 FOTO · 4,2 MB', style: gayaMono(ukuran: 9, warna: Colors.white, tebal: FontWeight.w500)),
                      ]),
                      const SizedBox(height: 6),
                      Text('terkirim otomatis saat sinyal kembali — urut waktu, tidak dua kali',
                          style: gayaIsi(ukuran: 11.5, warna: Warna.putih50, tinggi: 1.35)),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _Tiket extends StatelessWidget {
  const _Tiket({required this.redup, required this.waktu, required this.isi});
  final int redup;
  final String waktu, isi;

  @override
  Widget build(BuildContext context) {
    final alpha = redup == 0 ? 1.0 : (redup == 1 ? .55 : .3);
    return Opacity(
      opacity: alpha,
      child: Container(
        padding: const EdgeInsets.fromLTRB(10, 8, 10, 8),
        decoration: BoxDecoration(
          color: Warna.inkLembut,
          borderRadius: BorderRadius.circular(6),
          border: Border.all(color: redup == 0 ? const Color(0x80F57C00) : const Color(0x33FFFFFF)),
        ),
        child: Row(children: [
          Text(waktu, style: gayaMono(ukuran: 10, warna: Colors.white)),
          const SizedBox(width: 8),
          Expanded(child: Text(isi, maxLines: 1, overflow: TextOverflow.ellipsis, style: gayaIsi(ukuran: 11.5, warna: Colors.white, tebal: FontWeight.w600, tinggi: 1.2))),
          if (redup == 0) ...[
            const SizedBox(width: 6),
            Text('MENUNGGU', style: gayaMono(ukuran: 8, warna: Warna.sinyalTerang)),
          ],
        ]),
      ),
    );
  }
}

/* ═══════════ layar izin ═══════════ */

class _LayarIzin extends StatelessWidget {
  const _LayarIzin();

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(22, 10, 22, 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Denyut(builder: (context, d) => PinLokasi(ukuran: 64, denyut: d)),
          const SizedBox(height: 14),
          Row(children: [
            Container(width: 18, height: 2, color: Warna.sinyal),
            const SizedBox(width: 8),
            Text('04 · IZIN & PRIVASI', style: gayaMono(ukuran: 11.5)),
          ]),
          const SizedBox(height: 10),
          Text('Satu izin. Ini alasannya.', style: gayaJudul(ukuran: 34)),
          const SizedBox(height: 12),
          Text(
            'Titik lokasi dilekatkan pada laporan bahaya supaya pengawas tahu persis '
            'di mana temuannya. Dibaca hanya saat Anda membuka layar laporan, tidak '
            'pernah di latar belakang. Menolak pun boleh — tulis nama lokasinya sendiri.',
            style: gayaIsi(ukuran: 14.5),
          ),
          const SizedBox(height: 18),
          const _Manifes(),
          const SizedBox(height: 12),
          Text('Dapat diubah kapan saja: Profil › Pengaturan aplikasi.',
              style: gayaIsi(ukuran: 12.5, warna: Warna.putih50, tinggi: 1.4)),
        ],
      ),
    );
  }
}

/// Daftar izin apa adanya — yang diminta dan yang TIDAK diminta, dalam
/// satu tabel. Lebih jujur daripada tiga kartu berikon.
class _Manifes extends StatelessWidget {
  const _Manifes();

  static const _baris = [
    ('LOKASI', 'Saat aplikasi dipakai', 'DIMINTA', true),
    ('LATAR BELAKANG', 'Tidak pernah dibaca', '—', false),
    ('KAMERA', 'Lewat aplikasi kamera ponsel', 'TANPA IZIN', false),
    ('GALERI', 'Hanya foto yang Anda pilih', 'TIDAK DIBACA', false),
    ('IKLAN · DIJUAL', 'Data untuk keselamatan kerja', 'TIDAK ADA', false),
  ];

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: const Color(0xCC0B1117),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: const Color(0x40FFFFFF)),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const GarisBahaya(tinggi: 4),
          for (final (i, (izin, ket, status, diminta)) in _baris.indexed)
            Container(
              padding: const EdgeInsets.fromLTRB(12, 9, 12, 9),
              decoration: BoxDecoration(
                color: diminta ? const Color(0x1AF57C00) : null,
                border: Border(top: BorderSide(color: i == 0 ? Colors.transparent : Warna.garisGelap)),
              ),
              child: Row(
                children: [
                  SizedBox(width: 104, child: Text(izin, style: gayaMono(ukuran: 9.5, warna: diminta ? Warna.sinyalTerang : Warna.putih70))),
                  Expanded(child: Text(ket, style: gayaIsi(ukuran: 12.5, warna: Colors.white, tebal: FontWeight.w500, tinggi: 1.25))),
                  const SizedBox(width: 8),
                  Text(status, style: gayaMono(ukuran: 9, warna: diminta ? Warna.sinyalTerang : Warna.putih50, tebal: diminta ? FontWeight.w600 : FontWeight.w500)),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

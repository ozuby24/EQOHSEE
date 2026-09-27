import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:permission_handler/permission_handler.dart';

import 'tema.dart';

/// Pengenalan pertama kali: tiga hal yang membedakan aplikasi ini dari
/// membuka situsnya di peramban, lalu SATU layar izin yang menjelaskan
/// untuk apa izinnya dipakai sebelum sistem menanyakannya.
///
/// Layar izin itu bukan hiasan. Kebijakan Play meminta pengungkapan yang
/// jelas sebelum permintaan izin lokasi, dan pekerja yang ditanya "izinkan
/// lokasi?" tanpa alasan lebih sering menolak — lalu laporan bahayanya
/// terkirim tanpa titik GPS.
class LayarPengenalan extends StatefulWidget {
  const LayarPengenalan({super.key, required this.selesai});

  /// Dipanggil sekali, sesudah layar izin dijawab (diizinkan atau tidak).
  final VoidCallback selesai;

  @override
  State<LayarPengenalan> createState() => _LayarPengenalanState();
}

class _Slide {
  const _Slide(this.ikon, this.label, this.judul, this.isi, this.butir);
  final IconData ikon;
  final String label;
  final String judul;
  final String isi;
  final List<String> butir;
}

const _slide = <_Slide>[
  _Slide(
    Icons.warning_amber_rounded,
    '01 · LAPOR BAHAYA',
    'Lapor bahaya dalam hitungan detik',
    'Foto dari kamera, titik GPS terisi sendiri, dan matriks risiko 5×5. '
        'Tingkat risiko dan tenggat tindak lanjut ditentukan otomatis — '
        'pelapor tidak perlu menghafal aturannya.',
    ['Matriks 5×5', 'Foto & GPS', 'Tenggat otomatis'],
  ),
  _Slide(
    Icons.fact_check_outlined,
    '02 · P2H UNIT',
    'P2H di samping unitnya',
    'Daftar periksa pra-operasi per jenis unit, tombol besar untuk jari '
        'bersarung tangan. Satu butir kritis gagal langsung menahan unit '
        'dan menerbitkan perintah kerja untuk mekanik.',
    ['OK · Tidak · N/A', 'Butir kritis', 'Perintah kerja'],
  ),
  _Slide(
    Icons.cloud_off_rounded,
    '03 · TANPA SINYAL',
    'Tetap jalan di pit tanpa sinyal',
    'Laporan dan P2H tersimpan di ponsel beserta fotonya, lalu terkirim '
        'otomatis begitu sinyal kembali. Tidak ada yang hilang, tidak ada '
        'yang terkirim dua kali.',
    ['Draf otomatis', 'Antrean kiriman', 'Sinkron sendiri'],
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
        body: SafeArea(
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(22, 14, 12, 0),
                child: Row(
                  children: [
                    const Wordmark(ukuran: 17),
                    const Spacer(),
                    if (!_diIzin)
                      TextButton(
                        onPressed: () => _halaman.animateToPage(_slide.length,
                            duration: const Duration(milliseconds: 380), curve: Curves.easeOutCubic),
                        style: TextButton.styleFrom(foregroundColor: Colors.white70, minimumSize: const Size(64, 44)),
                        child: const Text('Lewati', style: TextStyle(fontSize: 14.5, fontWeight: FontWeight.w600)),
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
                padding: const EdgeInsets.fromLTRB(22, 4, 22, 18),
                child: Column(
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: List.generate(_jumlah, (i) {
                        final aktif = i == _ke;
                        return AnimatedContainer(
                          duration: const Duration(milliseconds: 220),
                          margin: const EdgeInsets.symmetric(horizontal: 3),
                          width: aktif ? 26 : 8,
                          height: 6,
                          decoration: BoxDecoration(
                            color: aktif ? Warna.sinyal : const Color(0x33FFFFFF),
                            borderRadius: BorderRadius.circular(3),
                          ),
                        );
                      }),
                    ),
                    const SizedBox(height: 18),
                    if (!_diIzin)
                      FilledButton(
                        onPressed: _lanjut,
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          mainAxisAlignment: MainAxisAlignment.center,
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
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: const [
                            Icon(Icons.my_location_rounded, size: 20),
                            SizedBox(width: 8),
                            Flexible(child: Text('Izinkan lokasi & mulai', overflow: TextOverflow.ellipsis)),
                          ],
                        ),
                      ),
                      const SizedBox(height: 8),
                      TextButton(
                        onPressed: _meminta ? null : widget.selesai,
                        style: TextButton.styleFrom(
                          foregroundColor: Colors.white70,
                          minimumSize: const Size.fromHeight(46),
                        ),
                        child: const Text('Nanti saja', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600)),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SlideTampil extends StatelessWidget {
  const _SlideTampil({required this.s});
  final _Slide s;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(builder: (context, batas) {
      return SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(26, 12, 26, 12),
        child: ConstrainedBox(
          constraints: BoxConstraints(minHeight: batas.maxHeight - 24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _Lambang(ikon: s.ikon),
              const SizedBox(height: 34),
              Text(s.label,
                  style: const TextStyle(
                      color: Warna.sinyalTerang, fontSize: 12, fontWeight: FontWeight.w700, letterSpacing: 1.4)),
              const SizedBox(height: 10),
              Text(s.judul,
                  style: const TextStyle(
                      color: Colors.white, fontSize: 31, height: 1.08, fontWeight: FontWeight.w800, letterSpacing: -.8)),
              const SizedBox(height: 14),
              Text(s.isi, style: const TextStyle(color: Color(0xB3FFFFFF), fontSize: 15.5, height: 1.5)),
              const SizedBox(height: 22),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: s.butir
                    .map((b) => Container(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                          decoration: BoxDecoration(
                            color: Warna.inkLembut,
                            borderRadius: BorderRadius.circular(18),
                            border: Border.all(color: Warna.garisGelap),
                          ),
                          child: Row(mainAxisSize: MainAxisSize.min, children: [
                            const Icon(Icons.check_rounded, size: 15, color: Warna.sinyalTerang),
                            const SizedBox(width: 6),
                            Text(b, style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w600)),
                          ]),
                        ))
                    .toList(),
              ),
            ],
          ),
        ),
      );
    });
  }
}

/// Lambang slide: petak jingga di atas kisi garis rambut — bidang datar,
/// tanpa gradien, sesuai rancangan lapangan.
class _Lambang extends StatelessWidget {
  const _Lambang({required this.ikon});
  final IconData ikon;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 132,
      height: 132,
      child: Stack(
        children: [
          Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(30),
                border: Border.all(color: Warna.garisGelap),
              ),
            ),
          ),
          Positioned(
            left: 18,
            top: 18,
            right: 18,
            bottom: 18,
            child: Container(
              decoration: BoxDecoration(color: Warna.sinyal, borderRadius: BorderRadius.circular(24)),
              child: Icon(ikon, size: 48, color: Warna.ink),
            ),
          ),
        ],
      ),
    );
  }
}

class _LayarIzin extends StatelessWidget {
  const _LayarIzin();

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(26, 18, 26, 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: const [
          Text('IZIN & PRIVASI',
              style: TextStyle(color: Warna.sinyalTerang, fontSize: 12, fontWeight: FontWeight.w700, letterSpacing: 1.4)),
          SizedBox(height: 10),
          Text('Satu izin, beserta alasannya',
              style: TextStyle(color: Colors.white, fontSize: 29, height: 1.1, fontWeight: FontWeight.w800, letterSpacing: -.7)),
          SizedBox(height: 22),
          _ButirIzin(
            ikon: Icons.my_location_rounded,
            judul: 'Lokasi — hanya saat aplikasi dipakai',
            isi: 'EQOHSEE mengumpulkan titik lokasi saat Anda membuat laporan bahaya, '
                'supaya pengawas tahu persis di mana temuannya. Lokasi tidak pernah '
                'dibaca di latar belakang dan hanya tersimpan pada laporan itu.',
            kunci: true,
          ),
          _ButirIzin(
            ikon: Icons.photo_camera_outlined,
            judul: 'Kamera — tanpa izin',
            isi: 'Foto diambil lewat aplikasi kamera ponsel Anda. Hanya foto yang '
                'Anda pilih yang dikirim; galeri Anda tidak dibaca.',
          ),
          _ButirIzin(
            ikon: Icons.shield_outlined,
            judul: 'Tanpa iklan, tanpa dijual',
            isi: 'Data dipakai untuk keselamatan kerja di perusahaan Anda — tidak '
                'untuk iklan, dan tidak dijual kepada siapa pun.',
          ),
          SizedBox(height: 4),
          Text('Izin dapat diubah kapan saja lewat Profil, bagian Pengaturan aplikasi.',
              style: TextStyle(color: Color(0x99FFFFFF), fontSize: 13, height: 1.45)),
        ],
      ),
    );
  }
}

class _ButirIzin extends StatelessWidget {
  const _ButirIzin({required this.ikon, required this.judul, required this.isi, this.kunci = false});
  final IconData ikon;
  final String judul;
  final String isi;
  final bool kunci;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Warna.inkLembut,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: kunci ? const Color(0x80F57C00) : Warna.garisGelap),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: kunci ? Warna.sinyal : const Color(0x14FFFFFF),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(ikon, size: 20, color: kunci ? Warna.ink : Colors.white),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(judul, style: const TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w700)),
                const SizedBox(height: 4),
                Text(isi, style: const TextStyle(color: Color(0xB3FFFFFF), fontSize: 13.5, height: 1.45)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

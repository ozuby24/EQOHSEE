import 'package:flutter/material.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:url_launcher/url_launcher.dart';

import 'kunci.dart';
import 'tema.dart';

/// Lembar pengaturan aplikasi — dibuka dari Profil di mode lapangan.
///
/// Hanya berisi yang memang milik PERANGKAT: kunci sidik jari, izin
/// lokasi, dan tautan ke halaman yang diwajibkan Play (privasi, hapus
/// akun). Pengaturan akun sendiri tetap di web supaya tidak ada dua
/// tempat yang dapat berselisih.
Future<void> bukaLembarPengaturan(
  BuildContext context, {
  required Pengaturan pengaturan,
  required String versi,
  required String alamatSitus,
  required void Function(String url) bukaDiWebView,
}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.white,
    showDragHandle: true,
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
    builder: (_) => _LembarPengaturan(
      pengaturan: pengaturan,
      versi: versi,
      alamatSitus: alamatSitus,
      bukaDiWebView: bukaDiWebView,
    ),
  );
}

class _LembarPengaturan extends StatefulWidget {
  const _LembarPengaturan({
    required this.pengaturan,
    required this.versi,
    required this.alamatSitus,
    required this.bukaDiWebView,
  });

  final Pengaturan pengaturan;
  final String versi;
  final String alamatSitus;
  final void Function(String url) bukaDiWebView;

  @override
  State<_LembarPengaturan> createState() => _LembarPengaturanState();
}

class _LembarPengaturanState extends State<_LembarPengaturan> {
  bool? _bisaBiometrik;
  PermissionStatus? _lokasi;

  @override
  void initState() {
    super.initState();
    _muat();
  }

  Future<void> _muat() async {
    final b = await biometrikTersedia();
    PermissionStatus l;
    try {
      l = await Permission.locationWhenInUse.status;
    } catch (_) {
      l = PermissionStatus.denied;
    }
    if (!mounted) return;
    setState(() { _bisaBiometrik = b; _lokasi = l; });
  }

  Future<void> _ubahKunci(bool nyala) async {
    /* Menyalakan kunci menuntut pembuktian lebih dulu: kunci yang
       dinyalakan pada perangkat tanpa sidik jari maupun kunci layar
       mengunci pemiliknya sendiri di luar aplikasinya. */
    if (nyala && !await bukaKunciBiometrik(alasan: 'Nyalakan kunci EQOHSEE')) return;
    await widget.pengaturan.aturKunci(nyala);
    if (mounted) setState(() {});
  }

  Future<void> _aturLokasi() async {
    final s = _lokasi;
    if (s == null) return;
    if (s.isGranted || s.isLimited || s.isPermanentlyDenied || s.isRestricted) {
      await openAppSettings();
    } else {
      await Permission.locationWhenInUse.request();
    }
    await _muat();
  }

  void _buka(String jalur) {
    Navigator.of(context).pop();
    widget.bukaDiWebView('${widget.alamatSitus}$jalur');
  }

  @override
  Widget build(BuildContext context) {
    final lokasiAda = _lokasi?.isGranted == true || _lokasi?.isLimited == true;

    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Expanded(
                  child: Text('Pengaturan aplikasi',
                      style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: Warna.ink, letterSpacing: -.3)),
                ),
                Text('Versi ${widget.versi}', style: const TextStyle(color: Warna.abu3, fontSize: 12.5, fontWeight: FontWeight.w600)),
              ],
            ),
            const SizedBox(height: 14),
            _Kartu(children: [
              SwitchListTile.adaptive(
                value: widget.pengaturan.kunciAktif,
                onChanged: _bisaBiometrik == true ? _ubahKunci : null,
                activeThumbColor: Warna.sinyal,
                contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
                secondary: const _Petak(ikon: Icons.fingerprint_rounded),
                title: const Text('Kunci dengan sidik jari', style: _judul),
                subtitle: Text(
                  _bisaBiometrik == false
                      ? 'Ponsel ini belum punya sidik jari atau kunci layar.'
                      : 'Diminta saat aplikasi dibuka lagi setelah 2 menit di latar.',
                  style: _ket,
                ),
              ),
              const Divider(height: 1, indent: 14, endIndent: 14),
              ListTile(
                onTap: _aturLokasi,
                contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
                leading: const _Petak(ikon: Icons.my_location_rounded),
                title: const Text('Izin lokasi', style: _judul),
                subtitle: Text(
                  _lokasi == null
                      ? 'Memeriksa…'
                      : lokasiAda
                          ? 'Diizinkan saat aplikasi dipakai — untuk titik laporan bahaya.'
                          : 'Belum diizinkan. Laporan tetap dapat dikirim dengan menulis lokasinya.',
                  style: _ket,
                ),
                trailing: Text(lokasiAda ? 'Atur' : 'Izinkan',
                    style: const TextStyle(color: Warna.jingga, fontWeight: FontWeight.w700, fontSize: 14)),
              ),
            ]),
            const SizedBox(height: 12),
            _Kartu(children: [
              _Tautan(ikon: Icons.privacy_tip_outlined, judul: 'Kebijakan privasi', onTap: () => _buka('/kebijakan-privasi')),
              const Divider(height: 1, indent: 14, endIndent: 14),
              _Tautan(ikon: Icons.person_remove_outlined, judul: 'Hapus akun', onTap: () => _buka('/profile#hapus-akun')),
              const Divider(height: 1, indent: 14, endIndent: 14),
              _Tautan(
                ikon: Icons.mail_outline_rounded,
                judul: 'Hubungi dukungan',
                onTap: () => launchUrl(
                  Uri.parse('mailto:privasi@eqohsee.id?subject=${Uri.encodeComponent('Bantuan aplikasi EQOHSEE v${widget.versi}')}'),
                  mode: LaunchMode.externalApplication,
                ),
              ),
            ]),
          ],
        ),
      ),
    );
  }
}

const _judul = TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: Warna.ink);
const _ket = TextStyle(fontSize: 12.5, height: 1.4, color: Warna.abu1);

class _Kartu extends StatelessWidget {
  const _Kartu({required this.children});
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E6EB)),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(mainAxisSize: MainAxisSize.min, children: children),
    );
  }
}

class _Petak extends StatelessWidget {
  const _Petak({required this.ikon});
  final IconData ikon;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 38,
      height: 38,
      decoration: BoxDecoration(color: const Color(0xFFFFF0DE), borderRadius: BorderRadius.circular(10)),
      child: Icon(ikon, size: 20, color: Warna.jingga),
    );
  }
}

class _Tautan extends StatelessWidget {
  const _Tautan({required this.ikon, required this.judul, required this.onTap});
  final IconData ikon;
  final String judul;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      onTap: onTap,
      minTileHeight: 56,
      contentPadding: const EdgeInsets.symmetric(horizontal: 14),
      leading: Icon(ikon, color: Warna.abu1),
      title: Text(judul, style: _judul),
      trailing: const Icon(Icons.chevron_right_rounded, color: Color(0xFF9AA3AE)),
    );
  }
}

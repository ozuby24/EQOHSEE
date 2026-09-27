import 'package:flutter/material.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:url_launcher/url_launcher.dart';

import 'kunci.dart';
import 'lukisan.dart';
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
    showDragHandle: false,
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(18))),
    clipBehavior: Clip.antiAlias,
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
    final kunci = widget.pengaturan.kunciAktif;

    return SafeArea(
      top: false,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const GarisBahaya(tinggi: 5),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 18, 20, 4),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text('PERANGKAT INI', style: gayaMono(ukuran: 10.5, warna: Warna.jingga)),
                    const SizedBox(height: 4),
                    Text('Pengaturan aplikasi', style: gayaJudul(ukuran: 26, warna: Warna.ink)),
                  ]),
                ),
                Text('v${widget.versi}', style: gayaMono(ukuran: 11, warna: Warna.abu3, tebal: FontWeight.w500)),
              ],
            ),
          ),
          const SizedBox(height: 10),
          _Baris(
            ikon: Icons.fingerprint_rounded,
            judul: 'Kunci sidik jari',
            ket: _bisaBiometrik == false
                ? 'Ponsel ini belum punya sidik jari atau kunci layar.'
                : 'Diminta saat aplikasi dibuka lagi setelah 2 menit di latar.',
            status: _bisaBiometrik == false ? 'TIDAK ADA' : (kunci ? 'AKTIF' : 'MATI'),
            statusKuat: kunci,
            onTap: _bisaBiometrik == true ? () => _ubahKunci(!kunci) : null,
            ekor: Switch.adaptive(
              value: kunci,
              onChanged: _bisaBiometrik == true ? _ubahKunci : null,
              activeTrackColor: Warna.sinyal,
              activeThumbColor: Warna.ink,
            ),
          ),
          _Baris(
            ikon: Icons.my_location_rounded,
            judul: 'Izin lokasi',
            ket: _lokasi == null
                ? 'Memeriksa…'
                : lokasiAda
                    ? 'Saat aplikasi dipakai — untuk titik laporan bahaya.'
                    : 'Belum diizinkan. Laporan tetap dapat dikirim dengan menulis lokasinya.',
            status: _lokasi == null ? '' : (lokasiAda ? 'DIIZINKAN' : 'DITOLAK'),
            statusKuat: lokasiAda,
            onTap: _aturLokasi,
            ekor: Text(lokasiAda ? 'Atur' : 'Izinkan',
                style: const TextStyle(fontFamily: Huruf.isi, color: Warna.jingga, fontWeight: FontWeight.w700, fontSize: 14)),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 18, 20, 6),
            child: Text('TAUTAN', style: gayaMono(ukuran: 10.5, warna: Warna.abu3, tebal: FontWeight.w500)),
          ),
          _Tautan(ikon: Icons.privacy_tip_outlined, judul: 'Kebijakan privasi', ket: 'eqohsee.id/kebijakan-privasi', onTap: () => _buka('/kebijakan-privasi')),
          _Tautan(ikon: Icons.person_remove_outlined, judul: 'Hapus akun', ket: 'Profil › Akun & kata sandi', onTap: () => _buka('/profile#hapus-akun')),
          _Tautan(
            ikon: Icons.mail_outline_rounded,
            judul: 'Hubungi dukungan',
            ket: 'privasi@eqohsee.id',
            onTap: () => launchUrl(
              Uri.parse('mailto:privasi@eqohsee.id?subject=${Uri.encodeComponent('Bantuan aplikasi EQOHSEE v${widget.versi}')}'),
              mode: LaunchMode.externalApplication,
            ),
          ),
          const SizedBox(height: 10),
        ],
      ),
    );
  }
}

class _Baris extends StatelessWidget {
  const _Baris({
    required this.ikon,
    required this.judul,
    required this.ket,
    required this.status,
    required this.statusKuat,
    required this.onTap,
    required this.ekor,
  });

  final IconData ikon;
  final String judul, ket, status;
  final bool statusKuat;
  final VoidCallback? onTap;
  final Widget ekor;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.fromLTRB(20, 12, 14, 12),
        decoration: const BoxDecoration(border: Border(top: BorderSide(color: Warna.garisTerang))),
        child: Row(
          children: [
            Icon(ikon, size: 22, color: Warna.ink),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(children: [
                    Text(judul, style: const TextStyle(fontFamily: Huruf.isi, fontSize: 15, fontWeight: FontWeight.w700, color: Warna.ink)),
                    if (status.isNotEmpty) ...[
                      const SizedBox(width: 8),
                      Text(status, style: gayaMono(ukuran: 9, warna: statusKuat ? Warna.jingga : Warna.abu4)),
                    ],
                  ]),
                  const SizedBox(height: 2),
                  Text(ket, style: const TextStyle(fontFamily: Huruf.isi, fontSize: 12.5, height: 1.4, color: Warna.abu1)),
                ],
              ),
            ),
            const SizedBox(width: 8),
            ekor,
          ],
        ),
      ),
    );
  }
}

class _Tautan extends StatelessWidget {
  const _Tautan({required this.ikon, required this.judul, required this.ket, required this.onTap});
  final IconData ikon;
  final String judul, ket;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.fromLTRB(20, 11, 14, 11),
        decoration: const BoxDecoration(border: Border(top: BorderSide(color: Warna.garisTerang))),
        child: Row(children: [
          Icon(ikon, size: 21, color: Warna.abu1),
          const SizedBox(width: 14),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(judul, style: const TextStyle(fontFamily: Huruf.isi, fontSize: 15, fontWeight: FontWeight.w700, color: Warna.ink)),
              Text(ket, style: gayaMono(ukuran: 10, warna: Warna.abu3, tebal: FontWeight.w500, jarak: .02)),
            ]),
          ),
          const Icon(Icons.arrow_outward_rounded, size: 18, color: Warna.abu4),
        ]),
      ),
    );
  }
}

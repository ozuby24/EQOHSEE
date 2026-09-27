// Tangkapan layar native untuk ditinjau dan untuk listing Play Store.
//
// Dijalankan terpisah dari uji biasa:
//   flutter test test_tangkapan/tangkapan_test.dart
// Hasilnya: build/tangkapan/*.png (1170×2532, rasio layar 390×844 × 3).
import 'dart:io';
import 'dart:ui' as ui;

import 'package:eqohsee/kunci.dart';
import 'package:eqohsee/pengaturan.dart';
import 'package:eqohsee/pengenalan.dart';
import 'package:eqohsee/tema.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

const _huruf = '/opt/sdk/flutter/bin/cache/artifacts/material_fonts';

// ignore_for_file: invalid_use_of_visible_for_testing_member

Future<void> _muatHuruf() async {
  final roboto = FontLoader('Roboto');
  for (final b in ['Regular', 'Medium', 'Bold', 'Black']) {
    roboto.addFont(Future.value(ByteData.sublistView(File('$_huruf/Roboto-$b.ttf').readAsBytesSync())));
  }
  await roboto.load();
  final ikon = FontLoader('MaterialIcons')
    ..addFont(Future.value(ByteData.sublistView(File('$_huruf/MaterialIcons-Regular.otf').readAsBytesSync())));
  await ikon.load();
}

final _kunciTangkap = GlobalKey();

Future<void> _simpan(WidgetTester tester, String nama) async {
  await tester.runAsync(() async {
    final r = _kunciTangkap.currentContext!.findRenderObject()! as RenderRepaintBoundary;
    final gambar = await r.toImage(pixelRatio: 3);
    final data = await gambar.toByteData(format: ui.ImageByteFormat.png);
    Directory('build/tangkapan').createSync(recursive: true);
    File('build/tangkapan/$nama.png').writeAsBytesSync(data!.buffer.asUint8List());
  });
}

Widget _bungkus(Widget anak) => RepaintBoundary(
      key: _kunciTangkap,
      child: MaterialApp(debugShowCheckedModeBanner: false, theme: temaEqohsee(), home: anak),
    );

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() async {
    await _muatHuruf();
    final m = TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
    // Biometrik tersedia, izin lokasi diizinkan — keadaan yang dilihat pemakai.
    m.setMockMessageHandler('dev.flutter.pigeon.local_auth_android.LocalAuthApi.isDeviceSupported',
        (_) async => const StandardMessageCodec().encodeMessage(<Object?>[true]));
    m.setMockMethodCallHandler(const MethodChannel('plugins.flutter.io/local_auth'),
        (c) async => c.method == 'isDeviceSupported' ? true : null);
    m.setMockMethodCallHandler(const MethodChannel('flutter.baseflow.com/permissions/methods'),
        (c) async => c.method == 'checkPermissionStatus' ? 1 : null);
  });

  Future<void> ukuran(WidgetTester tester) async {
    tester.view.physicalSize = const Size(390 * 3, 844 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);
  }

  testWidgets('pengenalan', (tester) async {
    await ukuran(tester);
    await tester.runAsync(() async {
      await precacheImage(const AssetImage('assets/merek.png'), tester.binding.rootElement!);
    });
    await tester.pumpWidget(_bungkus(LayarPengenalan(selesai: () {})));
    await tester.pumpAndSettle();
    await _simpan(tester, 'n1-pengenalan-lapor');
    for (final n in ['n2-pengenalan-p2h', 'n3-pengenalan-luring', 'n4-izin']) {
      await tester.tap(find.byType(FilledButton));
      await tester.pumpAndSettle();
      await _simpan(tester, n);
    }
  });

  testWidgets('kunci', (tester) async {
    await ukuran(tester);
    await tester.runAsync(() async {
      await precacheImage(const AssetImage('assets/merek.png'), tester.binding.rootElement!);
    });
    await tester.pumpWidget(_bungkus(LayarKunci(terbuka: () {})));
    await tester.pumpAndSettle();
    await _simpan(tester, 'n5-kunci');
  });

  testWidgets('pengaturan', (tester) async {
    await ukuran(tester);
    SharedPreferences.setMockInitialValues({'kunci_biometrik': true, 'pengenalan_selesai_v1': true});
    final p = Pengaturan(await SharedPreferences.getInstance());
    await tester.pumpWidget(_bungkus(Builder(builder: (c) {
      return Scaffold(
        backgroundColor: Warna.kertas,
        body: Center(
          child: FilledButton(
            onPressed: () => bukaLembarPengaturan(c, pengaturan: p, versi: '1.1.0', alamatSitus: 'https://eqohsee.id', bukaDiWebView: (_) {}),
            child: const Text('buka'),
          ),
        ),
      );
    })));
    await tester.tap(find.text('buka'));
    await tester.pumpAndSettle();
    for (var i = 0; i < 5; i++) {
      await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 50)));
      await tester.pump(const Duration(milliseconds: 50));
    }
    await tester.pumpAndSettle();
    await _simpan(tester, 'n6-pengaturan');
  });
}

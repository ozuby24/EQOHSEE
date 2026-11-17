import 'package:eqohsee/kunci.dart';
import 'package:eqohsee/layar_utama.dart';
import 'package:eqohsee/main.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('bukaDiDalam — alamat mana yang tetap di dalam aplikasi', () {
    test('alamat EQOHSEE sendiri dibuka di dalam', () {
      expect(bukaDiDalam(Uri.parse('https://eqohsee.id/dashboard')), isTrue);
      expect(bukaDiDalam(Uri.parse('https://www.eqohsee.id/hazard')), isTrue);
    });

    test('situs luar dilempar keluar', () {
      expect(bukaDiDalam(Uri.parse('https://google.com')), isFalse);
      expect(bukaDiDalam(Uri.parse('https://facebook.com/eqohsee')), isFalse);
    });

    test('inang berakhiran sama TIDAK lolos', () {
      // Pencocokan dengan endsWith() akan meloloskan ketiganya, dan
      // halaman penyerang terbuka di dalam aplikasi yang sesinya masih
      // hidup. Cocoknya harus persis.
      expect(bukaDiDalam(Uri.parse('https://eqohsee.id.penyerang.com')), isFalse);
      expect(bukaDiDalam(Uri.parse('https://bukan-eqohsee.id')), isFalse);
      expect(bukaDiDalam(Uri.parse('https://eqohsee.id.co')), isFalse);
    });

    test('inang huruf besar tetap dikenali', () {
      // Uri milik Dart yang menormalkannya, bukan kode kita. Ditulis di
      // sini sebagai pernyataan atas kenyataan yang kita andalkan: kalau
      // suatu hari tidak lagi begitu, uji ini yang memberi tahu.
      expect(bukaDiDalam(Uri.parse('https://EQOHSEE.ID/dashboard')), isTrue);
    });

    test('skema bukan web selalu keluar', () {
      // tel: dan mailto: harus sampai ke aplikasi telepon dan surel.
      // Keduanya sebenarnya sudah tertolak karena inangnya kosong.
      expect(bukaDiDalam(Uri.parse('tel:+62811000000')), isFalse);
      expect(bukaDiDalam(Uri.parse('mailto:hse@eqohsee.id')), isFalse);
      expect(bukaDiDalam(Uri.parse('intent://scan#Intent;scheme=zxing;end')), isFalse);

      // INI yang benar-benar menguji penjaga skemanya: inangnya
      // eqohsee.id, persis ada di daftar putih. Tanpa penjaga itu,
      // WebView diminta memuat ftp — dan uji lain tidak ada yang
      // menangkapnya, karena tel: dan mailto: tertolak lewat jalan lain.
      expect(bukaDiDalam(Uri.parse('ftp://eqohsee.id/x')), isFalse);
      expect(bukaDiDalam(Uri.parse('javascript:alert(1)')), isFalse);
    });
  });

  group('alamatPintasan — pintasan ikon membuka layar yang benar', () {
    test('ketiga pintasan menuju mode lapangan di situs sendiri', () {
      for (final jenis in ['lapor', 'p2h', 'tugas']) {
        final url = alamatPintasan(jenis)!;
        expect(url, startsWith('$alamatSitus/lapangan/'));
        expect(bukaDiDalam(Uri.parse(url)), isTrue, reason: 'pintasan $jenis harus terbuka di dalam aplikasi');
      }
      expect(alamatPintasan('lapor'), endsWith('/lapangan/lapor'));
    });

    test('jenis tak dikenal tidak membuka apa pun', () {
      expect(alamatPintasan('hapus'), isNull);
      expect(alamatPintasan(null), isNull);
    });
  });

  group('perluKunci — kapan aplikasi dikunci lagi', () {
    final t = DateTime(2026, 9, 27, 7, 0);

    test('kunci mati: tidak pernah dikunci', () {
      expect(perluKunci(aktif: false, keLatar: t, kini: t.add(const Duration(hours: 3))), isFalse);
    });

    test('di bawah jeda: tidak dikunci — kembali dari aplikasi kamera', () {
      expect(perluKunci(aktif: true, keLatar: t, kini: t.add(const Duration(seconds: 40))), isFalse);
    });

    test('tepat di jeda dan sesudahnya: dikunci', () {
      expect(perluKunci(aktif: true, keLatar: t, kini: t.add(jedaKunci)), isTrue);
      expect(perluKunci(aktif: true, keLatar: t, kini: t.add(const Duration(minutes: 30))), isTrue);
    });

    test('tanpa catatan ke latar atau jam mundur: tidak dikunci', () {
      expect(perluKunci(aktif: true, keLatar: null, kini: t), isFalse);
      expect(perluKunci(aktif: true, keLatar: t, kini: t.subtract(const Duration(minutes: 5))), isFalse);
    });
  });
}


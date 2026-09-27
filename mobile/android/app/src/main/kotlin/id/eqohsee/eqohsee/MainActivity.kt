package id.eqohsee.eqohsee

import android.app.DownloadManager
import android.content.Context
import android.net.Uri
import android.os.Build
import android.os.Environment
import android.webkit.CookieManager
import android.webkit.URLUtil
import io.flutter.embedding.android.FlutterFragmentActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

/**
 * FlutterFragmentActivity, bukan FlutterActivity: kunci sidik jari
 * (local_auth) menampilkan BiometricPrompt, dan prompt itu hanya dapat
 * dipasang pada FragmentActivity.
 */
class MainActivity : FlutterFragmentActivity() {

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)

        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "id.eqohsee/unduh")
            .setMethodCallHandler { panggilan, hasil ->
                if (panggilan.method != "unduh") {
                    hasil.notImplemented()
                    return@setMethodCallHandler
                }
                try {
                    hasil.success(
                        unduh(
                            panggilan.argument<String>("url")!!,
                            panggilan.argument<String>("userAgent"),
                            panggilan.argument<String>("disposisi"),
                            panggilan.argument<String>("mime"),
                            panggilan.argument<String>("nama"),
                        )
                    )
                } catch (e: Exception) {
                    hasil.error("GAGAL_UNDUH", e.message, null)
                }
            }
    }

    /**
     * Unduhan lewat DownloadManager sistem, MEMBAWA sesi WebView.
     *
     * Sebelumnya unduhan dilempar ke peramban luar. Peramban itu tidak
     * punya kuki sesi EQOHSEE, jadi setiap ekspor, lampiran, dan cetak
     * PDF yang dijaga login berakhir sebagai berkas berisi halaman masuk.
     * Di sini kuki WebView untuk alamat itu ikut dikirim, dan sistem yang
     * menampilkan kemajuannya di bilah notifikasi.
     *
     * Tanpa izin penyimpanan: Android 10+ mengizinkan DownloadManager
     * menulis ke folder Download publik; di bawahnya berkas ditulis ke
     * folder unduhan milik aplikasi dan tetap dibuka dari notifikasinya.
     */
    private fun unduh(url: String, ua: String?, disposisi: String?, mime: String?, nama: String?): String {
        val berkas = (nama?.takeIf { it.isNotBlank() } ?: URLUtil.guessFileName(url, disposisi, mime))
            .replace(Regex("[\\\\/:*?\"<>|]"), "_")

        val permintaan = DownloadManager.Request(Uri.parse(url)).apply {
            CookieManager.getInstance().getCookie(url)?.let { addRequestHeader("Cookie", it) }
            if (!ua.isNullOrBlank()) addRequestHeader("User-Agent", ua)
            if (!mime.isNullOrBlank()) setMimeType(mime)
            setTitle(berkas)
            setDescription("EQOHSEE")
            setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, "EQOHSEE/$berkas")
            } else {
                setDestinationInExternalFilesDir(this@MainActivity, Environment.DIRECTORY_DOWNLOADS, berkas)
            }
        }

        (getSystemService(Context.DOWNLOAD_SERVICE) as DownloadManager).enqueue(permintaan)
        return berkas
    }
}

import java.io.FileInputStream
import java.util.Properties

plugins {
    id("com.android.application")
    id("kotlin-android")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

android {
    namespace = "id.eqohsee.eqohsee"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_11
        targetCompatibility = JavaVersion.VERSION_11
    }

    kotlinOptions {
        jvmTarget = JavaVersion.VERSION_11.toString()
    }

    defaultConfig {
        // TODO: Specify your own unique Application ID (https://developer.android.com/studio/build/application-id.html).
        applicationId = "id.eqohsee.eqohsee"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    /*
     * Penandatanganan rilis.
     *
     * Kuncinya TIDAK ada di dalam repo, dan memang tidak boleh: siapa
     * pun yang memegangnya dapat menerbitkan pembaruan yang diterima
     * ponsel pemakai sebagai EQOHSEE yang asli. Ia dibaca dari
     * android/key.properties, yang masuk .gitignore.
     *
     * Tanpa berkas itu — di mesin mana pun yang belum disiapkan —
     * build rilis jatuh ke kunci DEBUG. Itu tetap menghasilkan APK yang
     * dapat dipasang untuk uji coba, tetapi Play Store menolaknya, dan
     * APK berkunci debug TIDAK dapat diperbarui oleh APK berkunci asli:
     * tanda tangannya berbeda, jadi pemakainya harus mencopot dulu.
     *
     * Peringatan di bawah dicetak supaya keadaan itu tidak lewat diam-
     * diam. Berkas APK yang salah tanda tangan kelihatan persis sama
     * dengan yang benar sampai seseorang mencoba memasang pembaruannya.
     */
    val berkasKunci = rootProject.file("key.properties")
    val kunci = Properties()
    if (berkasKunci.exists()) {
        kunci.load(FileInputStream(berkasKunci))
    } else {
        /* println, BUKAN logger.warn.
         *
         * Flutter menyaring keluaran Gradle dan logger.warn tidak pernah
         * sampai ke layar — peringatannya terpasang rapi dan tidak
         * pernah terbaca siapa pun. Tertangkap dengan menghitung
         * barisnya pada keluaran build, bukan dengan membacanya sekilas. */
        println(
            "\n  ==================================================================\n" +
            "   PERINGATAN: android/key.properties tidak ada.\n" +
            "   Build rilis ini ditandatangani KUNCI DEBUG.\n" +
            "   Dapat dipasang untuk uji coba; DITOLAK Play Store, dan tidak\n" +
            "   dapat memperbarui APK yang bertanda tangan asli.\n" +
            "   Cara membuat kuncinya: mobile/README.md\n" +
            "  ==================================================================\n"
        )
    }

    signingConfigs {
        if (berkasKunci.exists()) {
            create("release") {
                keyAlias = kunci["keyAlias"] as String
                keyPassword = kunci["keyPassword"] as String
                storeFile = file(kunci["storeFile"] as String)
                storePassword = kunci["storePassword"] as String
            }
        }
    }

    buildTypes {
        release {
            signingConfig = if (berkasKunci.exists()) {
                signingConfigs.getByName("release")
            } else {
                signingConfigs.getByName("debug")
            }
        }
    }
}

flutter {
    source = "../.."
}

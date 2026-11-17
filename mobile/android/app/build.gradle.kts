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
        applicationId = "id.eqohsee.eqohsee"
        minSdk = flutter.minSdkVersion
        /* Ditulis angkanya, bukan diwarisi dari Flutter: Play menolak
           unggahan yang target API-nya di bawah batas tahun berjalan, dan
           batas itu tidak boleh ikut turun diam-diam bila versi Flutter di
           mesin build lebih tua. */
        targetSdk = 36
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    /* Pustaka native tidak dikompres dan disejajarkan 16 KB — syarat Play
       untuk aplikasi bertarget Android 15+ sejak November 2025. */
    packaging {
        jniLibs {
            useLegacyPackaging = false
        }
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

dependencies {
    // Tema AppCompat untuk BiometricPrompt pada Android 8 ke bawah.
    implementation("androidx.appcompat:appcompat:1.7.1")
}

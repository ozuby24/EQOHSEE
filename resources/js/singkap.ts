import type { Directive, DirectiveBinding } from 'vue';

/**
 * v-singkap — unsur muncul saat masuk ke layar.
 *
 * ── SATU PENGAMAT, BUKAN SATU PER UNSUR ──
 *
 * Halaman etalase menyingkap lebih dari lima puluh unsur. Satu
 * IntersectionObserver per unsur berarti lima puluh pengamat yang
 * masing-masing dipanggil ulang tiap kali halaman digulir; pada ponsel
 * kelas menengah — perangkat yang benar-benar dipakai di site — itu
 * terasa sebagai gulir yang tersendat, dan yang tersendat justru
 * gerakan yang dimaksudkan menghaluskan.
 *
 * ── SEKALI SINGKAP, LALU LEPAS ──
 *
 * Unsur yang sudah tersingkap tidak diamati lagi. Tanpa itu, menggulir
 * naik-turun membuat halaman berkedip-kedip: setiap kali sesuatu keluar
 * layar ia menghilang lagi, dan kembalinya terbaca sebagai kerusakan,
 * bukan sebagai gerakan.
 *
 * ── YANG TIDAK PUNYA JAVASCRIPT TETAP MELIHAT ISINYA ──
 *
 * Keadaan awal (tak terlihat, tergeser ke bawah) dipasang oleh
 * DIREKTIF INI, bukan oleh kelas CSS yang tertulis di markah. Kalau
 * ditulis di CSS, halaman yang gagal menjalankan skripnya — peramban
 * lama, galat pada modul lain, pemblokir yang terlalu bersemangat —
 * akan tampil kosong seluruhnya, sebab tidak ada yang pernah datang
 * mengembalikan opacity-nya. Dipasang dari sini, kegagalan skrip
 * berarti isinya tampil apa adanya tanpa gerakan: hilangnya hiasan,
 * bukan hilangnya halaman.
 *
 * ── GERAKAN BOLEH DIMINTA BERHENTI ──
 *
 * `prefers-reduced-motion: reduce` bukan soal selera: gerakan memicu
 * mual dan pusing pada sebagian orang, dan halaman jual adalah tempat
 * mereka tidak punya pilihan untuk menghindarinya. Bila diminta, tidak
 * ada satu pun keadaan awal yang dipasang — bukan sekadar transisinya
 * yang dipercepat.
 */

/** Jeda antar unsur dalam satu rombongan, milidetik. */
const JEDA = 70;

/** Sejauh apa unsur digeser sebelum menetap. */
const GESER = '18px';

/**
 * Lengkung waktu yang sama untuk seluruh halaman.
 *
 * Melambat di ujung dan tidak pernah melampaui tujuannya. Pantulan —
 * back, elastic, dan sejenisnya — membaca sebagai mainan; pada halaman
 * yang menyebut angka delapan digit itu bukan kesan yang dituju.
 */
const LENGKUNG = 'cubic-bezier(.22,1,.36,1)';

let pengamat: IntersectionObserver | null = null;

/** Berapa unsur sudah tersingkap dalam rombongan yang sedang berjalan. */
let rombongan = 0;
let rombonganWaktu = 0;

function diamSaja(): boolean {
  return typeof window === 'undefined'
    || typeof IntersectionObserver === 'undefined'
    || window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;
}

function siapkanPengamat(): IntersectionObserver {
  pengamat ??= new IntersectionObserver((butir, obs) => {
    const sekarang = performance.now();

    /* Rombongan disetel ulang bila sudah ada jeda dari singkapan
       terakhir. Tanpa ini, unsur ke lima puluh mewarisi tundaan
       3,5 detik dari unsur pertama dan tidak pernah terlihat muncul —
       ia sudah lama berada di layar ketika gilirannya tiba. */
    if (sekarang - rombonganWaktu > 220) rombongan = 0;

    for (const b of butir) {
      if (! b.isIntersecting) continue;

      const el = b.target as HTMLElement;

      const jaring = el.dataset.singkapJaring;

      if (jaring) {
        window.clearTimeout(Number(jaring));
        delete el.dataset.singkapJaring;
      }

      el.style.transitionDelay = `${Math.min(rombongan, 6) * JEDA}ms`;
      el.style.opacity = '1';
      el.style.transform = 'none';

      rombongan++;
      rombonganWaktu = sekarang;

      obs.unobserve(el);
    }
  }, {
    /* Disingkap sedikit SEBELUM tepi layar. Tepat di tepi, unsur yang
       digulir cepat sudah terbaca sebelum gerakannya selesai, sehingga
       yang terlihat bukan kemunculan melainkan kedipan. */
    rootMargin: '0px 0px -12% 0px',
    threshold: 0.08,
  });

  return pengamat;
}

/**
 * Berapa lama menunggu sebelum unsur disingkap paksa.
 *
 * Bukan bagian dari gerakannya — jaring pengaman. Lihat catatan pada
 * `jaring()` di bawah.
 */
const BATAS_TUNGGU = 2600;

/**
 * Singkap paksa, apa pun yang terjadi pada pengamatnya.
 *
 * ── MENGAPA INI ADA ──
 *
 * IntersectionObserver dapat tidak pernah memanggil balik meski
 * unsurnya sudah lama terlihat. Yang sudah terjadi: unsur yang saat
 * dipasang berada di dalam induk `display:none`, halaman yang
 * dibuka lewat jangkar sehingga peramban melompat sebelum pengamatnya
 * sempat terpasang, dan halaman sangat panjang yang digulir dengan
 * pemulihan posisi.
 *
 * Kegagalannya DIAM dan TOTAL: tidak ada galat, tidak ada bagian yang
 * setengah tergambar — hanya bidang kosong di tengah halaman jual, pada
 * bagian yang justru menyebut harga. Yang membacanya tidak menduga ada
 * yang rusak; ia menduga memang tidak ada apa-apa di sana.
 *
 * Karena itu tiap unsur membawa penghitung waktunya sendiri. Kalau
 * pengamatnya bekerja seperti seharusnya — dan hampir selalu begitu —
 * penghitungnya dibatalkan sebelum sempat berbunyi.
 */
/**
 * Lepaskan seluruh gaya sebaris yang dipasang direktif ini.
 *
 * ── BUKAN SEKADAR KERAPIAN ──
 *
 * Gaya sebaris MENGALAHKAN lembar gaya. Selama `transition` bawaan
 * direktif ini masih menempel, tiap perubahan opacity atau transform
 * berikutnya pada unsur yang sama ikut memakai lamanya — 620 milidetik,
 * bukan 300 yang tertulis untuk sentuhan.
 *
 * Terukur begitu: kartu yang diangkat saat disentuh bergerak dua kali
 * lebih lambat daripada yang dirancang, di seluruh halaman, dan tidak
 * ada satu baris CSS pun yang menjelaskan mengapa — sebab penyebabnya
 * tidak ada di CSS.
 *
 * Dilepas, unsurnya kembali sepenuhnya diatur lembar gaya: opacity dan
 * transform tidak ditetapkan di sana, jadi ia tetap terlihat dan tidak
 * tergeser.
 */
function bersihkan(el: HTMLElement) {
  el.style.removeProperty('opacity');
  el.style.removeProperty('transform');
  el.style.removeProperty('transition');
  el.style.removeProperty('transition-delay');
  el.style.removeProperty('will-change');
}

function bukaPaksa(el: HTMLElement) {
  el.style.transitionDelay = '';
  el.style.opacity = '1';
  el.style.transform = 'none';

  /* Kalau transisinya memang berjalan, transitionend yang membersihkan.
     Kalau tidak — unsurnya sudah terlihat, atau transisinya tidak pernah
     mulai — penghitung ini yang mengambil alih, supaya gaya sebarisnya
     tidak menetap selamanya. */
  window.setTimeout(() => bersihkan(el), 900);
}

export const singkap: Directive<HTMLElement, number | undefined> = {
  mounted(el: HTMLElement, ikatan: DirectiveBinding<number | undefined>) {
    if (diamSaja()) return;

    const lama = 620 + (ikatan.value ?? 0);

    el.style.opacity = '0';

    /* translateY, BUKAN translate3d.
       Sufiks 3d memaksa unsurnya naik ke lapisan penggambarnya sendiri
       sejak dipasang — dan halaman ini menyingkap lebih dari lima puluh
       unsur, sehingga yang terbentuk lima puluh lapisan sekaligus.
       Peramban tetap menggabungkan transisi transform tanpa dipaksa;
       yang hilang hanya lapisan yang tidak diminta siapa pun. */
    el.style.transform = `translateY(${GESER})`;
    el.style.transition = `opacity ${lama}ms ${LENGKUNG}, transform ${lama}ms ${LENGKUNG}`;

    /* ── TANPA will-change, DAN ITU DISENGAJA ──
     *
     * Sebelumnya baris ini memasang `will-change: opacity, transform`
     * saat unsurnya DIPASANG, lalu melepasnya pada transitionend. Yang
     * terjadi bukan seperti yang dimaksudkan: unsur yang belum tersingkap
     * belum bertransisi sama sekali, jadi ia menyandang will-change sejak
     * halaman dimuat sampai gilirannya tiba — dan diukur pada gulir biasa,
     * puncaknya 65 lapisan penggambar serentak.
     *
     * Enam puluh lima lapisan pada peramban meja kerja hanya boros. Pada
     * tablet — yang memang dipakai membuka halaman ini — ia melampaui
     * memori penggambarnya, dan yang tergambar kotak putih di tempat
     * kartu seharusnya berada: lapisan yang digambar pada kedudukan
     * sebelum transformnya, lalu tidak pernah diperbarui.
     *
     * Petunjuknya dilepas seluruhnya. Transisi opacity dan transform
     * sudah digabungkan peramban tanpa diminta; yang hilang paling-paling
     * satu bingkai pertama, dan yang didapat halaman yang tergambar benar
     * pada perangkat yang benar-benar memakainya.
     */
    el.addEventListener('transitionend', () => bersihkan(el), { once: true });

    const jaring = window.setTimeout(() => {
      if (el.style.opacity === '0') bukaPaksa(el);
    }, BATAS_TUNGGU);

    /* Disimpan pada unsurnya sendiri supaya pengamatnya dapat
       membatalkannya, dan supaya unmount tidak meninggalkan penghitung
       yang menyentuh unsur yang sudah tidak ada. */
    el.dataset.singkapJaring = String(jaring);

    siapkanPengamat().observe(el);
  },

  unmounted(el: HTMLElement) {
    pengamat?.unobserve(el);

    const jaring = el.dataset.singkapJaring;

    if (jaring) window.clearTimeout(Number(jaring));
  },
};

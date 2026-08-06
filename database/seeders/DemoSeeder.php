<?php

namespace Database\Seeders;

use App\Models\{Company, Course, Module, Material, Quiz, QuizQuestion, Procedure,
    SopEvaluation, SopEvaluationQuestion, Signatory, News};
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Perusahaan contoh (dipakai LMS & TPKKP)
        Company::firstOrCreate(['name' => 'PT Citra Dayak Indah'], [
            'code'=>'CDI','izin_type'=>'IUP OP Batubara','location'=>'Kalimantan Tengah',
            'commodity'=>'Batubara','risk_class'=>'Tinggi','workers_employee'=>120,'workers_sub'=>340,
        ]);
        Company::firstOrCreate(['name' => 'PT Borneo Mandiri Utama'], [
            'code'=>'BMU','izin_type'=>'IUP OP Mineral','location'=>'Kalimantan Timur',
            'commodity'=>'Nikel','risk_class'=>'Tinggi','workers_employee'=>85,'workers_sub'=>210,
        ]);

        // Penanda tangan sertifikat
        Signatory::firstOrCreate(['name' => 'Ir. Budi Santoso'],
            ['title' => 'Kepala Teknik Tambang', 'is_active' => true]);

        // ---- Kursus 1 ----
        $c1 = Course::firstOrCreate(['title' => 'Dasar Keselamatan Pertambangan (SMKP)'], [
            'category' => 'Wajib',
            'description' => 'Pengenalan 7 elemen SMKP Minerba sesuai Kepdirjen 185.K/2019.',
        ]);
        $m1 = Module::firstOrCreate(['course_id'=>$c1->id,'order_index'=>1],
            ['title'=>'Kebijakan Keselamatan','description'=>'Komitmen manajemen & kebijakan K3.']);
        $m2 = Module::firstOrCreate(['course_id'=>$c1->id,'order_index'=>2],
            ['title'=>'Identifikasi Bahaya & Penilaian Risiko','description'=>'HIRADC dan matriks risiko.']);
        $m3 = Module::firstOrCreate(['course_id'=>$c1->id,'order_index'=>3],
            ['title'=>'Pelaporan Insiden','description'=>'Alur pelaporan dan investigasi.']);

        Material::firstOrCreate(['module_id'=>$m1->id,'title'=>'Slide Kebijakan K3'],
            ['course_id'=>$c1->id,'type'=>'pptx','order_index'=>1,'description'=>'Materi presentasi.']);
        Material::firstOrCreate(['module_id'=>$m2->id,'title'=>'Panduan HIRADC'],
            ['course_id'=>$c1->id,'type'=>'pdf','order_index'=>1,'description'=>'Dokumen panduan.']);

        $q1 = Quiz::firstOrCreate(['course_id'=>$c1->id,'title'=>'Kuis Dasar SMKP'], ['pass_score'=>70]);
        QuizQuestion::firstOrCreate(['quiz_id'=>$q1->id,'order_index'=>1], [
            'question'=>'Berapa jumlah elemen SMKP Minerba?',
            'options'=>['5 elemen','6 elemen','7 elemen','8 elemen'],
            'correct_index'=>2,
        ]);
        QuizQuestion::firstOrCreate(['quiz_id'=>$q1->id,'order_index'=>2], [
            'question'=>'Kepanjangan dari KTT adalah?',
            'options'=>['Kepala Teknik Tambang','Ketua Tim Tambang','Koordinator Teknis Tambang','Kepala Tim Teknik'],
            'correct_index'=>0,
        ]);
        QuizQuestion::firstOrCreate(['quiz_id'=>$q1->id,'order_index'=>3], [
            'question'=>'HIRADC digunakan untuk?',
            'options'=>['Menghitung produksi','Identifikasi bahaya & penilaian risiko','Menyusun laporan keuangan','Merekrut karyawan'],
            'correct_index'=>1,
        ]);

        // ---- Kursus 2 ----
        $c2 = Course::firstOrCreate(['title' => 'Keselamatan Berkendara di Area Tambang'], [
            'category' => 'Operasional',
            'description' => 'Aturan lalu lintas tambang, jarak aman, dan defensive driving.',
        ]);
        Module::firstOrCreate(['course_id'=>$c2->id,'order_index'=>1],
            ['title'=>'Rambu & Aturan Jalan Tambang','description'=>'Rambu wajib dan batas kecepatan.']);
        Module::firstOrCreate(['course_id'=>$c2->id,'order_index'=>2],
            ['title'=>'Interaksi Unit Besar & Kecil','description'=>'Blind spot dan jarak aman.']);

        // ---- Prosedur + Evaluasi SOP ----
        $p1 = Procedure::firstOrCreate(['code'=>'SOP-001'], [
            'title'=>'Prosedur Lock Out Tag Out (LOTO)','category'=>'Keselamatan Kerja',
            'description'=>'Prosedur isolasi energi sebelum perawatan alat.','position'=>1,
        ]);
        $p2 = Procedure::firstOrCreate(['code'=>'SOP-002'], [
            'title'=>'Prosedur Bekerja di Ketinggian','category'=>'Keselamatan Kerja',
            'description'=>'Persyaratan APD dan izin kerja di ketinggian.','position'=>2,
        ]);

        $e1 = SopEvaluation::firstOrCreate(['procedure_id'=>$p1->id], [
            'title'=>'Evaluasi SOP LOTO','description'=>'Uji pemahaman prosedur isolasi energi.',
            'passing_score'=>70,'duration_minutes'=>10,'is_active'=>true,'position'=>1,
        ]);
        SopEvaluationQuestion::firstOrCreate(['evaluation_id'=>$e1->id,'order_index'=>1], [
            'question'=>'Kapan LOTO wajib diterapkan?',
            'options'=>['Saat alat beroperasi normal','Sebelum perawatan/perbaikan alat','Saat pergantian shift','Saat pengisian bahan bakar'],
            'correct_index'=>1,
        ]);
        SopEvaluationQuestion::firstOrCreate(['evaluation_id'=>$e1->id,'order_index'=>2], [
            'question'=>'Siapa yang boleh melepas tag LOTO?',
            'options'=>['Siapa saja','Pengawas shift berikutnya','Petugas yang memasang','Operator alat'],
            'correct_index'=>2,
        ]);
        SopEvaluationQuestion::firstOrCreate(['evaluation_id'=>$e1->id,'order_index'=>3], [
            'question'=>'Tujuan utama LOTO adalah?',
            'options'=>['Mempercepat perbaikan','Menghemat energi','Mencegah pelepasan energi tak terduga','Menandai alat rusak'],
            'correct_index'=>2,
        ]);

        $e2 = SopEvaluation::firstOrCreate(['procedure_id'=>$p2->id], [
            'title'=>'Evaluasi SOP Bekerja di Ketinggian','description'=>'Uji pemahaman kerja di ketinggian.',
            'passing_score'=>75,'duration_minutes'=>10,'is_active'=>true,'position'=>2,
        ]);
        SopEvaluationQuestion::firstOrCreate(['evaluation_id'=>$e2->id,'order_index'=>1], [
            'question'=>'Mulai ketinggian berapa izin kerja di ketinggian diwajibkan?',
            'options'=>['0,5 meter','1,8 meter','5 meter','10 meter'],
            'correct_index'=>1,
        ]);
        SopEvaluationQuestion::firstOrCreate(['evaluation_id'=>$e2->id,'order_index'=>2], [
            'question'=>'APD utama untuk bekerja di ketinggian adalah?',
            'options'=>['Sarung tangan katun','Full body harness','Masker debu','Sepatu karet'],
            'correct_index'=>1,
        ]);

        // ---- Berita ----
        News::firstOrCreate(['title'=>'Bulan K3 Nasional 2026 Dimulai'], [
            'content'=>"EQOHSEE memulai rangkaian Bulan K3 Nasional dengan apel bersama di seluruh site.\n\nKegiatan meliputi lomba safety patrol, pelatihan P3K, dan simulasi tanggap darurat.",
            'published_at'=>now()->subDays(2),
        ]);
        News::firstOrCreate(['title'=>'Pembaruan Prosedur LOTO'], [
            'content'=>"Prosedur LOTO (SOP-001) telah diperbarui. Seluruh karyawan wajib mengikuti evaluasi ulang melalui menu Evaluasi SOP.",
            'published_at'=>now()->subDay(),
        ]);
    }
}

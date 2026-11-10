-- =======================================================
-- OneSign - Database Migration v2 (CRUD Master Data & Manajemen Akun)
-- Politeknik Negeri Jakarta - Jurusan TIK
-- =======================================================

USE onesign_db;

-- 1. Tambah kolom is_active pada tabel users (Soft Delete / Status Akun)
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER department;

-- 2. Buat tabel document_classifications (Klasifikasi Dokumen)
CREATE TABLE IF NOT EXISTS document_classifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    badge_label VARCHAR(50) NOT NULL DEFAULT 'Khusus Sivitas',
    description TEXT NULL,
    verification_level VARCHAR(100) NOT NULL DEFAULT 'Level Verifikasi: Standar',
    tier_code VARCHAR(20) NOT NULL DEFAULT 'TIER-1',
    tier_level INT NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Buat tabel document_categories (Kategori Dokumen)
CREATE TABLE IF NOT EXISTS document_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    description TEXT NULL,
    default_classification ENUM('publik', 'internal', 'rahasia') NOT NULL DEFAULT 'internal',
    icon_name VARCHAR(50) NOT NULL DEFAULT 'description',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 4. Seed Data document_classifications (Sesuai Stitch Design)
INSERT INTO document_classifications (name, code, badge_label, description, verification_level, tier_code, tier_level, is_active)
VALUES 
('Publik Terbuka', 'publik', 'Bebas Akses', 'Dokumen pengumuman, SK umum, atau brosur yang dapat diverifikasi publik tanpa otentikasi login portal akademis.', 'Level Verifikasi: QR Code Terbuka', 'TIER-0', 0, 1),
('Internal Kampus', 'internal', 'Khusus Sivitas', 'Hanya dapat diunduh dan dilihat oleh akun mahasiswa, dosen, atau tenaga kependidikan aktif institusi via SSO.', 'Level Verifikasi: SSO Edu-ID Required', 'TIER-1', 1, 1),
('Rahasia Terbatas', 'rahasia', 'Restricted', 'Transkrip nilai, data pribadi, atau evaluasi akademik yang memerlukan otorisasi peran berwenang dan izin akses temporer.', 'Level Verifikasi: Dual-Factor Auth + Watermark', 'TIER-2', 2, 1)
ON DUPLICATE KEY UPDATE 
    name = VALUES(name),
    badge_label = VALUES(badge_label),
    description = VALUES(description),
    verification_level = VALUES(verification_level),
    tier_code = VALUES(tier_code),
    tier_level = VALUES(tier_level);

-- 5. Seed Data document_categories (Sesuai Stitch Design)
INSERT INTO document_categories (name, code, description, default_classification, icon_name, is_active)
VALUES
('Tugas Akhir / Skripsi', 'SK-THESIS-FINAL', 'Naskah final kelulusan sarjana & pascasarjana berserta lembar pengesahan dewan penguji', 'internal', 'school', 1),
('Surat Rekomendasi Magang & MBKM', 'SR-MBKM-DIR', 'Rekomendasi resmi magang mandiri & industri kementerian bertandatangan dekanat', 'publik', 'assignment_turned_in', 1),
('Lembar Pengesahan Laporan', 'LP-LAP-PRAK', 'Pengesahan laporan praktikum laboratorium dan proyek akhir semester mahasiswa', 'internal', 'verified', 1),
('Nota Dinas Dekanat', 'ND-DEK-TIK', 'Surat dinas internal kedinasan antar pimpinan departemen dan program studi', 'rahasia', 'gavel', 1),
('Berita Acara Sidang', 'BA-SID-KOMP', 'Berita acara resmi pelaksanaan sidang proposal dan sidang komprehensif kelulusan akhir', 'rahasia', 'fact_check', 1),
('Administrasi Akademik Lainnya', 'ADM-AKAD-GEN', 'Dokumen pengajuan berkas administrasi umum dan pengantar perkuliahan', 'internal', 'description', 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    default_classification = VALUES(default_classification),
    icon_name = VALUES(icon_name);

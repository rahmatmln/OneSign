-- =======================================================
-- OneSign - Database Schema & Seed Data
-- Politeknik Negeri Jakarta - Jurusan TIK
-- =======================================================

CREATE DATABASE IF NOT EXISTS onesign_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE onesign_db;

-- 1. Tabel users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('pemohon', 'penandatangan', 'admin') NOT NULL,
    identifier_number VARCHAR(30) NULL,
    department VARCHAR(100) DEFAULT 'Teknik Informatika dan Komputer',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    saved_signature_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 1b. Tabel document_classifications (Klasifikasi Dokumen)
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

-- 1c. Tabel document_categories (Kategori Dokumen)
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

-- 2. Tabel documents
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requester_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    category VARCHAR(100) NOT NULL,
    confidentiality ENUM('publik', 'internal', 'rahasia') NOT NULL DEFAULT 'internal',
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    signed_file_path VARCHAR(255) NULL,
    file_size INT NOT NULL,
    status ENUM('menunggu', 'disetujui', 'ditolak') NOT NULL DEFAULT 'menunggu',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 3. Tabel document_signers
CREATE TABLE IF NOT EXISTS document_signers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    signer_id INT NOT NULL,
    page_number INT NOT NULL DEFAULT 1,
    pos_x FLOAT NOT NULL DEFAULT 0,
    pos_y FLOAT NOT NULL DEFAULT 0,
    box_width FLOAT NOT NULL DEFAULT 170,
    box_height FLOAT NOT NULL DEFAULT 80,
    signature_type ENUM('canvas', 'paraf') NULL,
    signature_image_path VARCHAR(255) NULL,
    status ENUM('menunggu', 'disetujui', 'ditolak') NOT NULL DEFAULT 'menunggu',
    rejection_note TEXT NULL,
    signed_at DATETIME NULL,
    verify_token VARCHAR(64) NULL,
    signer_ip VARCHAR(45) NULL,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (signer_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 4. Tabel audit_logs (Immutable Log)
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    document_id INT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NOT NULL,
    created_at TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 5. Tabel notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    document_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Indeks Tambahan untuk Query Cepat
CREATE INDEX idx_doc_requester ON documents(requester_id);
CREATE INDEX idx_doc_status ON documents(status);
CREATE INDEX idx_signer_status ON document_signers(signer_id, status);
CREATE INDEX idx_audit_created ON audit_logs(created_at);
CREATE INDEX idx_verify_token ON document_signers(verify_token);

-- =======================================================
-- SEED DATA PENGGUNA DEMO
-- Password default: password123
-- =======================================================

INSERT INTO users (full_name, email, password_hash, role, identifier_number, department)
VALUES 
-- Akun Pemohon (Mahasiswa)
('Rahmat Maulana', 'rahmat.maulana@stu.pnj.ac.id', '$2y$10$7RFM5CbuvKDHLydvKv/yeOAEuSHlZWwKarpiCUo/D8/J40hskRIgO', 'pemohon', '2207411000', 'Teknik Informatika'),

-- Akun Penandatangan (Dosen Pembimbing)
('Dr. Ir. Bagus Wijaya, M.Kom', 'bagus.wijaya@pnj.ac.id', '$2y$10$7RFM5CbuvKDHLydvKv/yeOAEuSHlZWwKarpiCUo/D8/J40hskRIgO', 'penandatangan', '198502152010121003', 'Teknik Informatika'),

-- Akun Staf Administrasi / Arsip
('Rini Maharani, A.Md', 'rini.maharani@pnj.ac.id', '$2y$10$7RFM5CbuvKDHLydvKv/yeOAEuSHlZWwKarpiCUo/D8/J40hskRIgO', 'admin', '199208102018032001', 'Tata Usaha TIK')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

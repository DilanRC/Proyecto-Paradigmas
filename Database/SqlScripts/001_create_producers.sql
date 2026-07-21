CREATE DATABASE IF NOT EXISTS tinder_cows
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE tinder_cows;

CREATE TABLE IF NOT EXISTS producers (
    producer_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identification_type ENUM('NATIONAL_ID', 'LEGAL_ID', 'DIMEX', 'NITE') NOT NULL,
    identification_number VARCHAR(20) NOT NULL,
    name VARCHAR(150) NOT NULL,
    farm_name VARCHAR(150) NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(150) NOT NULL,
    address VARCHAR(255) NOT NULL,
    status ENUM('ACTIVE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_producers_identification UNIQUE (identification_number),
    CONSTRAINT uq_producers_email UNIQUE (email),
    INDEX idx_producers_name (name),
    INDEX idx_producers_status (status)
) ENGINE=InnoDB;

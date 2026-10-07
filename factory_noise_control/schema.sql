-- ============================================================================
-- FACTORY ACOUSTIC MONITORING AND MATERIAL COMPLIANCE SYSTEM
-- Database Schema: schema.sql
-- Adheres to: 3rd Normal Form (3NF), ISO 1996 & OSHA 1910.95 Standards
-- ============================================================================

-- Create database if not exists and select it
CREATE DATABASE IF NOT EXISTS `factory_acoustic_db`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `factory_acoustic_db`;

-- ----------------------------------------------------------------------------
-- 1. Table: factory_zones
-- Stores physical plant sectors, regulatory dB thresholds, and material specifications
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `maintenance_requests`;
DROP TABLE IF EXISTS `noise_logs`;
DROP TABLE IF EXISTS `factory_zones`;

CREATE TABLE `factory_zones` (
  `zone_id` INT AUTO_INCREMENT PRIMARY KEY,
  `zone_code` VARCHAR(30) NOT NULL UNIQUE COMMENT 'Unique zone designator, e.g., ZN-STAMP-01',
  `zone_name` VARCHAR(100) NOT NULL COMMENT 'Descriptive quadrant/department name',
  `floor_area` VARCHAR(100) NOT NULL COMMENT 'Facility building and floor location',
  `max_decibel_limit` DECIMAL(5,2) NOT NULL DEFAULT 85.00 COMMENT 'OSHA/Internal threshold limit in dBA',
  `dampener_material_type` VARCHAR(150) NOT NULL COMMENT 'Installed sound absorption/damping barrier specs',
  `health_score` TINYINT UNSIGNED NOT NULL DEFAULT 100 COMMENT '0-100 material integrity score',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_zone_code` (`zone_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. Table: noise_logs
-- Stores high-frequency IoT acoustic telemetry readings with foreign key to factory_zones
-- ----------------------------------------------------------------------------
CREATE TABLE `noise_logs` (
  `log_id` INT AUTO_INCREMENT PRIMARY KEY,
  `zone_id` INT NOT NULL COMMENT 'Foreign key to factory_zones',
  `sensor_code` VARCHAR(50) NOT NULL COMMENT 'Hardware sensor identifier, e.g., MIC-STAMP-01A',
  `decibel_level` DECIMAL(5,2) NOT NULL COMMENT 'Recorded decibel reading in dBA',
  `frequency_hz` INT UNSIGNED NOT NULL COMMENT 'Dominant frequency peak in Hertz',
  `compliance_status` ENUM('COMPLIANT', 'ELEVATED', 'CRITICAL') NOT NULL DEFAULT 'COMPLIANT' COMMENT 'Categorization based on limit',
  `material_observation` VARCHAR(255) DEFAULT 'Nominal attenuation' COMMENT 'Field diagnostic notes or material condition',
  `logged_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_zone_log` (`zone_id`),
  INDEX `idx_logged_at` (`logged_at`),
  INDEX `idx_compliance` (`compliance_status`),
  CONSTRAINT `fk_noise_zone`
    FOREIGN KEY (`zone_id`) REFERENCES `factory_zones` (`zone_id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. Table: maintenance_requests
-- Stores corrective work orders dispatched when acoustic damping fails
-- ----------------------------------------------------------------------------
CREATE TABLE `maintenance_requests` (
  `request_id` INT AUTO_INCREMENT PRIMARY KEY,
  `zone_id` INT NOT NULL COMMENT 'Foreign key to factory_zones',
  `equipment_tag` VARCHAR(50) NOT NULL COMMENT 'Target machine or acoustic baffle tag, e.g., STAMP-HYD-04',
  `priority` ENUM('LOW', 'MEDIUM', 'HIGH', 'EMERGENCY') NOT NULL DEFAULT 'MEDIUM',
  `issue_description` TEXT NOT NULL COMMENT 'Detailed technical failure description',
  `reported_by` VARCHAR(120) NOT NULL COMMENT 'Technician name or badge identifier',
  `status` ENUM('PENDING', 'IN_PROGRESS', 'RESOLVED', 'CANCELLED') NOT NULL DEFAULT 'PENDING',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_maint_zone` (`zone_id`),
  INDEX `idx_maint_priority` (`priority`),
  INDEX `idx_maint_status` (`status`),
  CONSTRAINT `fk_maintenance_zone`
    FOREIGN KEY (`zone_id`) REFERENCES `factory_zones` (`zone_id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SEED DATA: Realistic Industrial Telemetry
-- ============================================================================

INSERT INTO `factory_zones` (`zone_id`, `zone_code`, `zone_name`, `floor_area`, `max_decibel_limit`, `dampener_material_type`, `health_score`) VALUES
(1, 'ZN-STAMP-01', 'Heavy Stamping & Press Bay', 'Building A - Floor 1', 85.00, 'Resonant Cavity & Elastomer Pads', 68),
(2, 'ZN-CNC-02', 'Precision CNC Milling Floor', 'Building A - Floor 2', 80.00, 'Acoustic Perforated Baffles', 92),
(3, 'ZN-TURB-03', 'Turbine & Auxiliary Generator Room', 'Powerhouse Utility Sub-Level', 85.00, 'Composite Rockwool Sound Barriers', 54),
(4, 'ZN-ASSY-04', 'Robotic Chassis Assembly Line', 'Building B - Bay 1', 75.00, 'Micro-Perforated Absorber Panels', 96),
(5, 'ZN-PAINT-05', 'High-Velocity Paint & Coating Booth', 'Building B - Bay 3', 75.00, 'Silenced Vent Hoods & Polyurethane Foam', 89),
(6, 'ZN-MET-06', 'Metallurgical Testing & QC Lab', 'Quality Assurance Wing', 65.00, 'Double-Glazed Acoustic Partitioning', 98);

INSERT INTO `noise_logs` (`zone_id`, `sensor_code`, `decibel_level`, `frequency_hz`, `compliance_status`, `material_observation`, `logged_at`) VALUES
(1, 'MIC-STAMP-01A', 88.60, 120, 'CRITICAL', 'Elastomer Wear: High (82%)', '2026-10-05 09:32:14'),
(1, 'MIC-STAMP-01B', 86.20, 145, 'CRITICAL', 'Cavity Resonance Misalignment', '2026-10-05 09:34:02'),
(2, 'MIC-CNC-02A', 76.40, 420, 'COMPLIANT', 'Baffle Absorption Nominal', '2026-10-05 09:35:10'),
(2, 'MIC-CNC-02B', 81.20, 380, 'ELEVATED', 'Spindle Acoustic Leakage', '2026-10-05 09:36:44'),
(3, 'MIC-TURB-03A', 92.40, 60, 'CRITICAL', 'Rockwool Barrier Delamination', '2026-10-05 09:37:05'),
(3, 'MIC-TURB-03B', 89.10, 180, 'CRITICAL', 'Structural Flanking Transmission', '2026-10-05 09:37:55'),
(4, 'MIC-ASSY-04A', 68.70, 250, 'COMPLIANT', 'Absorber Panels Pristine', '2026-10-05 09:38:12'),
(5, 'MIC-PAINT-05A', 73.10, 820, 'COMPLIANT', 'Foam Attenuation Nominal', '2026-10-05 09:38:50'),
(6, 'MIC-MET-06A', 54.30, 500, 'COMPLIANT', 'Double-Glazing Fully Sealed', '2026-10-05 09:39:15');

INSERT INTO `maintenance_requests` (`zone_id`, `equipment_tag`, `priority`, `issue_description`, `reported_by`, `status`, `created_at`) VALUES
(3, 'TURB-GEN-01', 'EMERGENCY', 'Severe 60Hz hum exceeding 92 dB; rockwool acoustic blanket detached from turbine enclosure.', 'S. Vance (Acoustic Safety Lead)', 'IN_PROGRESS', '2026-10-05 08:45:00'),
(1, 'STAMP-HYD-04', 'HIGH', 'Hydraulic impact damper compression failure causing repeated peak readings > 88 dB.', 'M. Gallagher (Shift Engineer)', 'PENDING', '2026-10-05 09:10:15');

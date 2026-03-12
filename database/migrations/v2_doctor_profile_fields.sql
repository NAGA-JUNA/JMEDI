-- =============================================================
-- JMedi v2 Migration: Doctor Profile Fields
-- Run this in phpMyAdmin / MySQL CLI on existing databases
-- MySQL 8.0+: IF NOT EXISTS supported
-- MySQL 5.7:  Remove IF NOT EXISTS from ADD COLUMN lines
-- =============================================================

-- New doctor columns
ALTER TABLE `doctors` ADD COLUMN IF NOT EXISTS `slug`               VARCHAR(150) UNIQUE            AFTER `name`;
ALTER TABLE `doctors` ADD COLUMN IF NOT EXISTS `languages`          VARCHAR(255) DEFAULT 'English' AFTER `specialization`;
ALTER TABLE `doctors` ADD COLUMN IF NOT EXISTS `certifications`     TEXT                           AFTER `bio`;
ALTER TABLE `doctors` ADD COLUMN IF NOT EXISTS `services`           TEXT                           AFTER `certifications`;
ALTER TABLE `doctors` ADD COLUMN IF NOT EXISTS `video_consultation` TINYINT(1)   DEFAULT 0         AFTER `consultation_types`;
ALTER TABLE `doctors` ADD COLUMN IF NOT EXISTS `clinic_name`        VARCHAR(200)                   AFTER `clinic_address`;
ALTER TABLE `doctors` ADD COLUMN IF NOT EXISTS `clinic_location`    TEXT                           AFTER `clinic_name`;
ALTER TABLE `doctors` ADD COLUMN IF NOT EXISTS `rating`             DECIMAL(3,1) DEFAULT 5.0       AFTER `clinic_location`;
ALTER TABLE `doctors` ADD COLUMN IF NOT EXISTS `reviews_count`      INT          DEFAULT 0         AFTER `rating`;
ALTER TABLE `doctors` ADD COLUMN IF NOT EXISTS `patients_treated`   INT          DEFAULT 0         AFTER `reviews_count`;
ALTER TABLE `doctors` ADD COLUMN IF NOT EXISTS `success_rate`       INT          DEFAULT 98        AFTER `patients_treated`;
ALTER TABLE `doctors` ADD COLUMN IF NOT EXISTS `profile_template`   TINYINT      DEFAULT 1         AFTER `success_rate`;

-- doctor_reviews table
CREATE TABLE IF NOT EXISTS `doctor_reviews` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `doctor_id`    INT NOT NULL,
    `patient_name` VARCHAR(100) NOT NULL,
    `rating`       TINYINT NOT NULL DEFAULT 5,
    `comment`      TEXT,
    `is_verified`  TINYINT DEFAULT 1,
    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_reviews_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`doctor_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Set slugs for the seeded doctors (safe to re-run — WHERE slug IS NULL)
UPDATE `doctors` SET slug='dr-james-wilson',    languages='English',          patients_treated=3200,  success_rate=98, rating=4.9, reviews_count=128, clinic_name='JMedi Cardiology Center',     certifications='Board Certified Interventional Cardiologist, FACC, FSCAI', services='Cardiac Catheterization,Coronary Angioplasty,Stent Placement,Echocardiography,Heart Failure Management,Preventive Cardiology,Arrhythmia Treatment'   WHERE email='wilson@jmedi.com'    AND slug IS NULL;
UPDATE `doctors` SET slug='dr-sarah-chen',      languages='English, Mandarin',patients_treated=2400,  success_rate=97, rating=4.8, reviews_count=96,  clinic_name='JMedi Neurology Center',      certifications='Board Certified Neurologist, PhD Neuroscience, FAAN',     services='Stroke Treatment,Epilepsy Management,Migraine Therapy,Multiple Sclerosis Care,Parkinson\'s Disease,Dementia Evaluation,Nerve Conduction Studies'   WHERE email='chen@jmedi.com'      AND slug IS NULL;
UPDATE `doctors` SET slug='dr-michael-roberts', languages='English',          patients_treated=5200,  success_rate=99, rating=4.9, reviews_count=214, clinic_name='JMedi Orthopedic Institute',  certifications='Board Certified Orthopedic Surgeon, FAAOS, FACS',         services='Total Knee Replacement,Hip Replacement,Robotic Surgery,ACL Reconstruction,Fracture Care,Arthroscopy,Spinal Surgery,Sports Injury Treatment'          WHERE email='roberts@jmedi.com'   AND slug IS NULL;
UPDATE `doctors` SET slug='dr-emily-johnson',   languages='English, Spanish', patients_treated=1800,  success_rate=98, rating=4.8, reviews_count=72,  clinic_name='JMedi Children\'s Clinic',    certifications='Board Certified Pediatrician, FAAP, MPH',                 services='Well-Child Visits,Immunizations,Developmental Screening,Asthma Management,Nutrition Counseling,Adolescent Medicine,Infectious Disease Treatment'     WHERE email='johnson@jmedi.com'   AND slug IS NULL;
UPDATE `doctors` SET slug='dr-david-park',      languages='English, Korean',  patients_treated=1400,  success_rate=97, rating=4.7, reviews_count=58,  clinic_name='JMedi Skin & Wellness Center',certifications='Board Certified Dermatologist, FAAD, FACMS',               services='Skin Cancer Screening,Mohs Surgery,Acne Treatment,Eczema Care,Psoriasis Management,Cosmetic Dermatology,Botox & Fillers,Laser Treatments'            WHERE email='park@jmedi.com'      AND slug IS NULL;
UPDATE `doctors` SET slug='dr-lisa-anderson',   languages='English',          patients_treated=2100,  success_rate=98, rating=4.8, reviews_count=84,  clinic_name='JMedi Dental & Implant Center',certifications='Board Certified Prosthodontist, DDS, MS, FACD',            services='Dental Implants,Crowns & Bridges,Full Mouth Rehabilitation,Veneers,Teeth Whitening,Invisalign,Root Canal,Dental Cleanings,Wisdom Tooth Extraction'   WHERE email='anderson@jmedi.com'  AND slug IS NULL;
UPDATE `doctors` SET slug='dr-rachel-martinez', languages='English, Spanish', patients_treated=1900,  success_rate=97, rating=4.8, reviews_count=76,  clinic_name='JMedi Heart Rhythm Center',   certifications='Board Certified Cardiac Electrophysiologist, FACC',       services='Atrial Fibrillation Ablation,Pacemaker Implantation,Defibrillator Implantation,Heart Rhythm Monitoring,EP Studies,Cardiac Resynchronization Therapy'  WHERE email='martinez@jmedi.com'  AND slug IS NULL;
UPDATE `doctors` SET slug='dr-andrew-thompson', languages='English',          patients_treated=2000,  success_rate=98, rating=4.9, reviews_count=91,  clinic_name='JMedi Neurosurgery Center',   certifications='Dual Board Certified Neurosurgeon, MD, DO, FAAN',         services='Brain Tumor Surgery,Spine Surgery,Minimally Invasive Neurosurgery,Deep Brain Stimulation,Cervical Disc Replacement,Lumbar Fusion,Skull Base Surgery'   WHERE email='thompson@jmedi.com'  AND slug IS NULL;

-- Sample reviews (only insert if doctor_reviews is empty for each doctor)
INSERT INTO `doctor_reviews` (`doctor_id`, `patient_name`, `rating`, `comment`, `is_verified`)
SELECT d.doctor_id, 'John Martinez', 5, 'Dr. Wilson is absolutely incredible. After my heart attack, he performed an emergency angioplasty and explained every step. The care I received was exceptional and his follow-up was thorough. I owe him my life.', 1 FROM `doctors` d WHERE d.email='wilson@jmedi.com' AND NOT EXISTS (SELECT 1 FROM `doctor_reviews` r WHERE r.doctor_id = d.doctor_id);
INSERT INTO `doctor_reviews` (`doctor_id`, `patient_name`, `rating`, `comment`, `is_verified`)
SELECT d.doctor_id, 'Sarah Thompson', 5, 'Dr. Chen diagnosed my migraines correctly after years of misdiagnosis. Her treatment plan worked within weeks. She is patient, knowledgeable, and genuinely cares about her patients.', 1 FROM `doctors` d WHERE d.email='chen@jmedi.com' AND NOT EXISTS (SELECT 1 FROM `doctor_reviews` r WHERE r.doctor_id = d.doctor_id);
INSERT INTO `doctor_reviews` (`doctor_id`, `patient_name`, `rating`, `comment`, `is_verified`)
SELECT d.doctor_id, 'Robert Kim', 5, 'After suffering knee pain for years, Dr. Roberts did my total knee replacement using robotic technology. Within 3 months I was hiking again. His skill and the rehab team are world-class.', 1 FROM `doctors` d WHERE d.email='roberts@jmedi.com' AND NOT EXISTS (SELECT 1 FROM `doctor_reviews` r WHERE r.doctor_id = d.doctor_id);
INSERT INTO `doctor_reviews` (`doctor_id`, `patient_name`, `rating`, `comment`, `is_verified`)
SELECT d.doctor_id, 'Maria Garcia', 5, 'Dr. Johnson has been my daughter\'s pediatrician since birth. She is so gentle, thorough, and really listens. I trust her completely with my child\'s health.', 1 FROM `doctors` d WHERE d.email='johnson@jmedi.com' AND NOT EXISTS (SELECT 1 FROM `doctor_reviews` r WHERE r.doctor_id = d.doctor_id);

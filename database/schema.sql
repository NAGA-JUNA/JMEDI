SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `admins` (
    `admin_id`   INT AUTO_INCREMENT PRIMARY KEY,
    `username`   VARCHAR(50)  UNIQUE NOT NULL,
    `password`   VARCHAR(255) NOT NULL,
    `email`      VARCHAR(100),
    `full_name`  VARCHAR(100),
    `role`       VARCHAR(20)  DEFAULT 'admin',
    `permissions` TEXT         DEFAULT '{}',
    `avatar`     VARCHAR(255) DEFAULT '',
    `doctor_id`  INT          DEFAULT NULL,
    `last_login` TIMESTAMP    NULL DEFAULT NULL,
    `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `departments` (
    `department_id` INT AUTO_INCREMENT PRIMARY KEY,
    `name`          VARCHAR(100) NOT NULL,
    `slug`          VARCHAR(100) UNIQUE NOT NULL,
    `description`   TEXT,
    `icon`          VARCHAR(50)  DEFAULT 'fa-heartbeat',
    `services`      TEXT,
    `image`         VARCHAR(255),
    `status`        SMALLINT     DEFAULT 1,
    `sort_order`    INT          DEFAULT 0,
    `created_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `doctors` (
    `doctor_id`           INT AUTO_INCREMENT PRIMARY KEY,
    `name`                VARCHAR(100) NOT NULL,
    `slug`                VARCHAR(150) UNIQUE,
    `photo`               VARCHAR(255),
    `department_id`       INT          DEFAULT NULL,
    `qualification`       VARCHAR(255),
    `experience`          VARCHAR(100),
    `specialization`      VARCHAR(255),
    `languages`           VARCHAR(255) DEFAULT 'English',
    `bio`                 TEXT,
    `certifications`      TEXT,
    `services`            TEXT,
    `email`               VARCHAR(100),
    `phone`               VARCHAR(30),
    `available_days`      VARCHAR(255),
    `available_time`      VARCHAR(100),
    `consultation_fee`    DECIMAL(10,2) DEFAULT 500.00,
    `consultation_types`  VARCHAR(20)  DEFAULT 'both',
    `video_consultation`  TINYINT(1)   DEFAULT 0,
    `clinic_name`         VARCHAR(200),
    `clinic_address`      TEXT,
    `clinic_location`     TEXT,
    `rating`              DECIMAL(3,1) DEFAULT 5.0,
    `reviews_count`       INT          DEFAULT 0,
    `patients_treated`    INT          DEFAULT 0,
    `success_rate`        INT          DEFAULT 98,
    `profile_template`    TINYINT      DEFAULT 1,
    `status`              SMALLINT     DEFAULT 1,
    `sort_order`          INT          DEFAULT 0,
    `created_at`          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_doctors_dept` FOREIGN KEY (`department_id`) REFERENCES `departments`(`department_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS `patients` (
    `patient_id`    INT AUTO_INCREMENT PRIMARY KEY,
    `name`          VARCHAR(100) NOT NULL,
    `email`         VARCHAR(100) UNIQUE NOT NULL,
    `phone`         VARCHAR(20),
    `password`      VARCHAR(255) NOT NULL,
    `date_of_birth` DATE,
    `gender`        VARCHAR(10),
    `address`       TEXT,
    `avatar`        VARCHAR(255) DEFAULT '',
    `created_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `last_login`    TIMESTAMP    NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `appointments` (
    `appointment_id`    INT AUTO_INCREMENT PRIMARY KEY,
    `patient_name`      VARCHAR(100) NOT NULL,
    `email`             VARCHAR(100) NOT NULL,
    `phone`             VARCHAR(30)  NOT NULL,
    `department_id`     INT          DEFAULT NULL,
    `doctor_id`         INT          DEFAULT NULL,
    `appointment_date`  DATE         NOT NULL,
    `appointment_time`  TIME         NOT NULL,
    `message`           TEXT,
    `consultation_type` VARCHAR(20)  DEFAULT 'clinic',
    `admin_notes`       TEXT,
    `patient_id`        INT          DEFAULT NULL,
    `status`            VARCHAR(20)  DEFAULT 'pending',
    `created_at`        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_apts_dept`   FOREIGN KEY (`department_id`) REFERENCES `departments`(`department_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_apts_doctor` FOREIGN KEY (`doctor_id`)     REFERENCES `doctors`(`doctor_id`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `posts` (
    `post_id`        INT AUTO_INCREMENT PRIMARY KEY,
    `title`          VARCHAR(255) NOT NULL,
    `slug`           VARCHAR(255) UNIQUE NOT NULL,
    `content`        TEXT,
    `featured_image` VARCHAR(255),
    `author`         VARCHAR(100) DEFAULT 'Admin',
    `status`         VARCHAR(20)  DEFAULT 'draft',
    `created_at`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `testimonials` (
    `testimonial_id` INT AUTO_INCREMENT PRIMARY KEY,
    `patient_name`   VARCHAR(100) NOT NULL,
    `photo`          VARCHAR(255),
    `content`        TEXT NOT NULL,
    `rating`         SMALLINT  DEFAULT 5,
    `status`         SMALLINT  DEFAULT 1,
    `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
    `setting_id`    INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key`   VARCHAR(100) UNIQUE NOT NULL,
    `setting_value` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hero_slides` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `title`            VARCHAR(255) NOT NULL,
    `subtitle`         VARCHAR(255),
    `description`      TEXT,
    `button_text`      VARCHAR(100) DEFAULT 'See All Services',
    `button_link`      VARCHAR(255) DEFAULT '/public/departments.php',
    `background_image` VARCHAR(255),
    `overlay_color`    VARCHAR(50)  DEFAULT 'rgba(15,33,55,0.7)',
    `text_animation`   VARCHAR(50)  DEFAULT 'fadeIn',
    `transition_effect` VARCHAR(50) DEFAULT 'slide',
    `sort_order`       INT          DEFAULT 0,
    `status`           SMALLINT     DEFAULT 1,
    `created_at`       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `menus` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `menu_name`  VARCHAR(100) NOT NULL,
    `menu_link`  VARCHAR(255) NOT NULL,
    `menu_icon`  VARCHAR(50)  DEFAULT 'fa-link',
    `menu_order` INT          DEFAULT 0,
    `status`     SMALLINT     DEFAULT 1,
    `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pages` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `page_name`    VARCHAR(100) NOT NULL,
    `page_slug`    VARCHAR(100) UNIQUE NOT NULL,
    `page_title`   VARCHAR(255),
    `page_content` TEXT,
    `page_meta`    TEXT,
    `page_icon`    VARCHAR(50)  DEFAULT 'fa-file',
    `updated_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `home_sections` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `section_key`  VARCHAR(100) UNIQUE NOT NULL,
    `section_data` TEXT NOT NULL,
    `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `doctor_schedules` (
    `id`                    INT AUTO_INCREMENT PRIMARY KEY,
    `doctor_id`             INT         NOT NULL,
    `day_of_week`           SMALLINT    NOT NULL,
    `session_label`         VARCHAR(50) NOT NULL DEFAULT 'Morning',
    `start_time`            TIME        NOT NULL,
    `end_time`              TIME        NOT NULL,
    `slot_duration_minutes` INT         NOT NULL DEFAULT 15,
    `is_active`             SMALLINT    DEFAULT 1,
    `created_at`            TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_schedule` (`doctor_id`, `day_of_week`, `session_label`),
    CONSTRAINT `fk_sched_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`doctor_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

CREATE INDEX IF NOT EXISTS `idx_doctor_schedules_doctor` ON `doctor_schedules`(`doctor_id`);
CREATE INDEX IF NOT EXISTS `idx_doctor_schedules_day`    ON `doctor_schedules`(`doctor_id`, `day_of_week`);
CREATE INDEX IF NOT EXISTS `idx_doctors_department`      ON `doctors`(`department_id`);
CREATE INDEX IF NOT EXISTS `idx_appointments_status`     ON `appointments`(`status`);
CREATE INDEX IF NOT EXISTS `idx_appointments_date`       ON `appointments`(`appointment_date`);
CREATE INDEX IF NOT EXISTS `idx_posts_status`            ON `posts`(`status`);
CREATE INDEX IF NOT EXISTS `idx_departments_slug`        ON `departments`(`slug`);

INSERT IGNORE INTO `admins` (`username`, `password`, `email`, `full_name`, `role`, `permissions`) VALUES
('admin',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@jmedi.com',   'Administrator',    'superadmin', '{"all":true}'),
('manager',   '$2y$10$ONVeUR4pDKbxLE5HrM0icuJObqXwoDS7/f1jt8pmXUjDW.1JVs15S', 'manager@jmedi.com', 'Site Manager',     'admin',      '{"doctors":true,"departments":true,"appointments":true,"blog":true,"testimonials":true,"settings":true}');

INSERT IGNORE INTO `admins` (`username`, `password`, `email`, `full_name`, `role`, `permissions`, `doctor_id`) VALUES
('dr.wilson', '$2y$10$EV5OGDxGwy/ghNNpzwNGquHyZGTU6LYBzY4CzRpTKiA3Djss.7OM6', 'wilson@jmedi.com',  'Dr. James Wilson', 'doctor',     '{}', 1);

INSERT IGNORE INTO `patients` (`name`, `email`, `phone`, `password`, `date_of_birth`, `gender`, `address`) VALUES
('John Patient', 'patient@jmedi.com', '+1-800-555-0100', '$2y$10$qvLYKh66Xv1WY01.qdAZW.laGmGYMLZwfnrSMHJ2TOSDb8d7OMe36', '1990-05-15', 'Male', '456 Patient Lane, Health City, HC 10002');

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_name',        'JMedi – Smart Medical Platform'),
('tagline',          'Powered by JNVWeb'),
('phone',            '+1 (800) 123-4567'),
('emergency_phone',  '+1 (800) 911-0000'),
('email',            'info@jmedi.com'),
('address',          '123 Medical Center Drive, Health City, HC 10001'),
('facebook',         'https://facebook.com/jmedi'),
('twitter',          'https://twitter.com/jmedi'),
('instagram',        'https://instagram.com/jmedi'),
('linkedin',         'https://linkedin.com/company/jmedi'),
('primary_color',    '#0D6EFD'),
('secondary_color',  '#20C997'),
('footer_text',      '© 2026 JMedi. All Rights Reserved. Powered by JNVWeb'),
('whatsapp_number',  '918001234567'),
('currency_symbol',  '₹'),
('meta_description', 'JMedi Smart Medical Platform — Advanced healthcare with board-certified doctors.'),
('google_maps_embed', '')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

INSERT IGNORE INTO `departments` (`name`, `slug`, `description`, `icon`, `services`) VALUES
('Cardiology',  'cardiology',  'Our Cardiology department is equipped with the latest cardiac diagnostic and interventional technology. From routine heart screenings to complex cardiac surgeries, our team of experienced cardiologists provides comprehensive cardiovascular care. We specialize in preventive cardiology, interventional procedures, electrophysiology, and cardiac rehabilitation to ensure optimal heart health for every patient.', 'fa-heartbeat', 'ECG & Echocardiography, Cardiac Catheterization, Coronary Angioplasty & Stenting, Pacemaker & ICD Implantation, Cardiac Rehabilitation Programs, Heart Failure Management, Preventive Cardiology Screenings'),
('Neurology',   'neurology',   'The Neurology department at JMedi offers expert diagnosis and treatment for a wide range of neurological conditions. Our neurologists utilize advanced neuroimaging and electrophysiological testing to accurately diagnose disorders of the brain, spinal cord, and peripheral nerves. We are dedicated to providing personalized treatment plans that improve quality of life for patients with complex neurological conditions.', 'fa-brain', 'EEG & Nerve Conduction Studies, Stroke Treatment & Prevention, Epilepsy Management, Multiple Sclerosis Treatment, Headache & Migraine Clinic, Movement Disorder Treatment, Neurosurgery Consultation'),
('Orthopedics', 'orthopedics', 'JMedi\'s Orthopedic department provides world-class musculoskeletal care using the latest surgical and non-surgical techniques. Our board-certified orthopedic surgeons specialize in joint replacement, sports medicine, spine surgery, and trauma care. With state-of-the-art robotic-assisted surgery and minimally invasive techniques, we help patients return to active, pain-free lives.', 'fa-bone', 'Total Joint Replacement (Hip, Knee, Shoulder), Arthroscopic Surgery, Sports Injury Treatment, Spine Surgery & Disc Repair, Fracture & Trauma Care, Physical Therapy & Rehabilitation, Robotic-Assisted Surgery'),
('Pediatrics',  'pediatrics',  'Our Pediatrics department provides compassionate, comprehensive healthcare for children from birth through adolescence. Our pediatricians are specially trained to address the unique medical needs of growing children. We offer well-child visits, vaccination programs, developmental assessments, and treatment for acute and chronic childhood conditions in a warm, child-friendly environment.', 'fa-baby', 'Well-Child Visits & Growth Monitoring, Immunization Programs, Newborn & Neonatal Care, Childhood Asthma Management, Developmental & Behavioral Pediatrics, Pediatric Emergency Care, Adolescent Health Services'),
('Dermatology', 'dermatology', 'The Dermatology department at JMedi offers complete skin care solutions from medical dermatology to advanced cosmetic treatments. Our experienced dermatologists diagnose and treat conditions affecting the skin, hair, and nails using cutting-edge technology. Whether you need a routine skin cancer screening or advanced laser therapy, our team delivers results with precision and care.', 'fa-allergies', 'Skin Cancer Screening & Treatment, Acne & Rosacea Treatment, Eczema & Psoriasis Management, Laser Therapy & Skin Resurfacing, Cosmetic Dermatology, Allergy Patch Testing, Mole Mapping & Removal'),
('Dental Care', 'dental',      'JMedi\'s Dental Care department offers a full range of dental services in a modern, comfortable environment. From preventive dental care and cosmetic dentistry to complex oral surgery, our team of skilled dentists and oral surgeons is committed to giving you a healthy, beautiful smile. We use digital imaging and minimally invasive techniques for the best patient outcomes.', 'fa-tooth', 'Dental Cleanings & Preventive Care, Root Canal Therapy, Dental Implants & Bridges, Cosmetic Dentistry & Veneers, Orthodontics & Invisalign, Wisdom Tooth Extraction, Teeth Whitening & Smile Makeover');

INSERT IGNORE INTO `doctors` (`name`, `photo`, `department_id`, `qualification`, `experience`, `specialization`, `bio`, `email`, `phone`, `available_days`, `available_time`) VALUES
('Dr. James Wilson',    'https://randomuser.me/api/portraits/men/32.jpg',    1, 'MD, FACC, FSCAI', '15 Years', 'Interventional Cardiology',            'Dr. James Wilson is a board-certified interventional cardiologist with over 15 years of experience in diagnosing and treating complex cardiovascular conditions. He completed his fellowship at Johns Hopkins Hospital and has performed over 3,000 cardiac catheterizations and coronary interventions. Dr. Wilson is known for his patient-centered approach and expertise in minimally invasive cardiac procedures.',                                                                                                                     'wilson@jmedi.com',    '+1-800-101-0001', 'Monday, Tuesday, Wednesday, Thursday, Friday', '9:00 AM - 5:00 PM'),
('Dr. Sarah Chen',      'https://randomuser.me/api/portraits/women/44.jpg',  2, 'MD, PhD, FAAN',   '12 Years', 'Clinical Neurology & Stroke Medicine', 'Dr. Sarah Chen is a fellowship-trained neurologist specializing in stroke prevention, epilepsy management, and neurodegenerative disorders. She earned her PhD in Neuroscience from Stanford University and completed her residency at Massachusetts General Hospital. Dr. Chen has pioneered several innovative treatment protocols for acute stroke patients and leads JMedi\'s Comprehensive Stroke Center.',                                                                                                                             'chen@jmedi.com',      '+1-800-101-0002', 'Monday, Wednesday, Thursday, Friday',          '10:00 AM - 6:00 PM'),
('Dr. Michael Roberts', 'https://randomuser.me/api/portraits/men/45.jpg',    3, 'MD, FAAOS, FACS', '20 Years', 'Joint Replacement & Sports Medicine',  'Dr. Michael Roberts is an internationally recognized orthopedic surgeon with over 20 years of experience specializing in total joint replacement and sports medicine. He trained at the Hospital for Special Surgery in New York and has performed more than 5,000 joint replacement procedures using robotic-assisted technology. Dr. Roberts is a team physician for several professional sports teams.',                                                                                                                                  'roberts@jmedi.com',   '+1-800-101-0003', 'Tuesday, Wednesday, Thursday, Saturday',       '8:00 AM - 4:00 PM'),
('Dr. Emily Johnson',   'https://randomuser.me/api/portraits/women/65.jpg',  4, 'MD, FAAP, MPH',   '10 Years', 'General & Developmental Pediatrics',   'Dr. Emily Johnson is a compassionate pediatrician dedicated to providing exceptional care for children from newborns to adolescents. She completed her residency at Boston Children\'s Hospital and holds a Master\'s degree in Public Health. Dr. Johnson has a special interest in childhood development, preventive medicine, and nutrition.',                                                                                                                                                                                          'johnson@jmedi.com',   '+1-800-101-0004', 'Monday, Tuesday, Wednesday, Thursday',         '9:00 AM - 5:00 PM'),
('Dr. David Park',      'https://randomuser.me/api/portraits/men/75.jpg',    5, 'MD, FAAD, FACMS', '8 Years',  'Medical & Cosmetic Dermatology',       'Dr. David Park is a board-certified dermatologist and fellowship-trained Mohs surgeon specializing in skin cancer treatment, medical dermatology, and advanced cosmetic procedures. He completed his dermatology residency at the University of California, San Francisco, and is a Fellow of the American College of Mohs Surgery.',                                                                                                                                    'park@jmedi.com',      '+1-800-101-0005', 'Monday, Wednesday, Thursday, Friday',          '10:00 AM - 6:00 PM'),
('Dr. Lisa Anderson',   'https://randomuser.me/api/portraits/women/33.jpg',  6, 'DDS, MS, FACD',   '14 Years', 'Prosthodontics & Implant Dentistry',   'Dr. Lisa Anderson is an expert prosthodontist and implant dentist with 14 years of experience restoring smiles with dental implants, crowns, bridges, and full-mouth rehabilitations. She earned her Doctor of Dental Surgery degree from Columbia University and completed advanced training in prosthodontics at the University of Pennsylvania.',                                                                                                              'anderson@jmedi.com',  '+1-800-101-0006', 'Tuesday, Wednesday, Thursday, Friday',         '9:00 AM - 4:00 PM'),
('Dr. Rachel Martinez', 'https://randomuser.me/api/portraits/women/28.jpg',  1, 'MD, FACC',        '11 Years', 'Cardiac Electrophysiology',            'Dr. Rachel Martinez is a cardiac electrophysiologist specializing in the diagnosis and treatment of heart rhythm disorders. She completed her cardiology fellowship at Cleveland Clinic and further subspecialized in electrophysiology at Mayo Clinic. Dr. Martinez has extensive experience in catheter ablation procedures, pacemaker implantation, and management of atrial fibrillation.',                                                                     'martinez@jmedi.com',  '+1-800-101-0007', 'Monday, Tuesday, Thursday, Friday',            '9:00 AM - 5:00 PM'),
('Dr. Andrew Thompson', 'https://randomuser.me/api/portraits/men/52.jpg',    2, 'MD, DO, FAAN',    '16 Years', 'Neurosurgery & Spine',                 'Dr. Andrew Thompson is a dual board-certified neurosurgeon with 16 years of experience in complex brain and spine surgery. He trained at Johns Hopkins Neurosurgery and completed a fellowship in minimally invasive spine surgery at Cedars-Sinai Medical Center. Dr. Thompson has performed over 2,000 neurosurgical procedures and specializes in brain tumor removal and spinal fusion.',                                                          'thompson@jmedi.com',  '+1-800-101-0008', 'Monday, Wednesday, Thursday, Friday',          '8:00 AM - 4:00 PM');

INSERT IGNORE INTO `testimonials` (`patient_name`, `content`, `rating`) VALUES
('John Martinez',  'The cardiac team at JMedi literally saved my life. After my heart attack, Dr. Wilson performed an emergency angioplasty and the care I received during recovery was exceptional. The nurses were attentive around the clock and the follow-up cardiac rehabilitation program helped me get back to my normal life within weeks. I cannot recommend this hospital highly enough.', 5),
('Sarah Thompson', 'I brought my 3-year-old daughter to the Pediatrics department for recurrent ear infections and Dr. Johnson was incredible. She took the time to explain everything in terms I could understand and made my daughter feel completely at ease. The child-friendly waiting area and the gentle approach of the entire staff made what could have been a stressful experience into a positive one.', 5),
('Robert Kim',     'After suffering from chronic knee pain for years, I finally decided to have a total knee replacement with Dr. Roberts. The surgery was performed using robotic-assisted technology and my recovery was remarkably smooth. Within three months I was hiking again — something I thought I would never be able to do. The orthopedic team and physical therapists at JMedi are truly world-class.', 5),
('Maria Garcia',   'Dr. Park at the Dermatology department diagnosed a suspicious mole that turned out to be early-stage melanoma. Thanks to his thorough skin screening and quick action, it was caught early and treated successfully. His expertise in Mohs surgery meant minimal scarring and a complete recovery. I now make sure to get my annual skin check without fail. Excellent care from start to finish.', 5);

INSERT IGNORE INTO `hero_slides` (`title`, `subtitle`, `description`, `button_text`, `button_link`, `text_animation`, `transition_effect`, `sort_order`, `status`) VALUES
('Advanced Medical Care You Can Trust', 'Trusted Healthcare Professionals', 'Experience comprehensive healthcare with our team of board-certified physicians, cutting-edge diagnostic technology, and personalized treatment plans designed to help you achieve optimal health and well-being.', 'Book Appointment', '/public/appointment.php', 'fadeIn',    'slide', 1, 1),
('Modern Diagnostic Services',          'Accurate & Fast Results',          'Our state-of-the-art diagnostic center offers advanced imaging, laboratory testing, and specialized screening services. Get accurate results quickly so you can start your treatment without delay.',                   'Explore Services',  '/public/departments.php', 'slideUp',   'fade',  2, 1),
('Your Health Is Our Priority',         'Experienced Doctors & Specialists', 'With over 35 specialists across 12 departments, JMedi Central Hospital delivers exceptional medical care 24 hours a day. From routine checkups to complex procedures, your health is in the best hands.',             'Meet Our Doctors',  '/public/doctors.php',     'slideLeft', 'zoom',  3, 1);

INSERT IGNORE INTO `menus` (`menu_name`, `menu_link`, `menu_icon`, `menu_order`, `status`) VALUES
('Home',        '/',                        'fa-home',            1, 1),
('Departments', '/public/departments.php',  'fa-hospital',        2, 1),
('Doctors',     '/public/doctors.php',      'fa-user-md',         3, 1),
('Blog',        '/public/blog.php',         'fa-newspaper',       4, 1),
('Appointment', '/public/appointment.php',  'fa-calendar-check',  5, 1),
('Contact',     '/public/contact.php',      'fa-envelope',        6, 1);

INSERT IGNORE INTO `pages` (`page_name`, `page_slug`, `page_title`, `page_content`, `page_meta`, `page_icon`) VALUES
('Home Page',     'home',        'JMedi Smart Medical Platform | Advanced Healthcare',    '', 'JMedi delivers comprehensive healthcare with board-certified physicians, cutting-edge diagnostics, and personalized treatment plans.',   'fa-home'),
('About Us',      'about',       'About JMedi | Our Story & Mission',                    '', 'Learn about JMedi\'s commitment to delivering exceptional healthcare, our history, values, and the experienced medical team behind our success.', 'fa-info-circle'),
('Doctors',       'doctors',     'Our Doctors | JMedi Medical Specialists',               '', 'Meet JMedi\'s team of highly qualified medical specialists dedicated to providing expert care across a wide range of specialties.',        'fa-user-md'),
('Departments',   'departments', 'Medical Departments | JMedi Hospital',                  '', 'Explore JMedi\'s comprehensive range of medical departments, each staffed by experienced specialists using the latest technology.',         'fa-hospital'),
('Blog',          'blog',        'Health Blog | Medical Tips & News | JMedi',             '', 'Stay informed with health tips, medical news, and expert advice from JMedi\'s team of physicians and health professionals.',               'fa-newspaper'),
('Contact Us',    'contact',     'Contact JMedi | Get In Touch',                          '', 'Reach out to JMedi Hospital for appointments, inquiries, emergency services, or general information. We are here to help you 24/7.',      'fa-envelope'),
('Appointment',   'appointment', 'Book an Appointment | JMedi',                           '', 'Book an appointment with JMedi\'s medical specialists quickly and easily. Same-day and emergency appointments available.',                 'fa-calendar-check');

INSERT IGNORE INTO `posts` (`title`, `slug`, `content`, `author`, `status`) VALUES
('10 Essential Heart Health Tips Every Adult Should Know',           '10-essential-heart-health-tips',                 'Heart disease remains the leading cause of death worldwide, but the good news is that up to 80% of cardiovascular disease is preventable through lifestyle changes. Regular exercise, a heart-healthy diet, blood pressure monitoring, cholesterol management, maintaining a healthy weight, quitting smoking, managing stress, limiting alcohol, getting adequate sleep, and scheduling regular checkups with your cardiologist can significantly reduce your risk.',                                                                                                                                'Dr. James Wilson',    'published'),
('Why Regular Health Check-ups Can Save Your Life',                  'why-regular-health-checkups-save-lives',         'Many people only visit the doctor when they feel sick, but regular health check-ups are one of the most important things you can do for your long-term health. Early detection saves lives — colorectal cancer caught at Stage 1 has a 90% survival rate compared to just 14% at Stage 4. Know your numbers, follow recommended screenings by age, keep vaccinations current, and monitor mental health.',                                                                                                                                                                                  'Admin',               'published'),
('Robotic-Assisted Joint Replacement: The Future of Orthopedic Surgery', 'robotic-assisted-joint-replacement-future', 'Orthopedic surgery has undergone a remarkable transformation with robotic-assisted surgical systems. At JMedi, our Orthopedics department utilizes the latest robotic technology to deliver unprecedented precision in joint replacement. Benefits include more accurate implant placement, smaller incisions, reduced blood loss, shorter hospital stays, faster recovery, less pain, and greater implant longevity.',                                                                                                                                                                              'Dr. Michael Roberts', 'published'),
('A Parent\'s Complete Guide to Child Healthcare',                   'parents-complete-guide-child-healthcare',        'Ensuring your child receives proper healthcare from infancy through adolescence is one of the most important parenting responsibilities. This guide covers immunization schedules, well-child visits, nutrition essentials, common childhood illnesses, and mental health awareness. Dr. Emily Johnson and our Pediatrics team at JMedi are dedicated to partnering with parents to ensure every child thrives.',                                                                                                                                                                                 'Dr. Emily Johnson',   'published'),
('Understanding and Managing Diabetes: A Comprehensive Approach',    'understanding-managing-diabetes-comprehensive',  'Diabetes affects over 37 million Americans, and an estimated 96 million adults have prediabetes. Understanding the types of diabetes and key management strategies — blood sugar monitoring, nutrition planning, physical activity, medication management, and complication prevention — is crucial for maintaining quality of life.',                                                                                                                                                                                                                                                        'Admin',               'published'),
('Dental Care Essentials: Building a Lifetime of Healthy Smiles',    'dental-care-essentials-healthy-smiles',          'Good dental health is about more than a beautiful smile — it is directly connected to your overall health. Poor oral health has been linked to heart disease, diabetes, and respiratory infections. Daily oral hygiene, professional dental care every six months, and understanding common dental procedures are essential for maintaining optimal dental health throughout your life.',                                                                                                                                                                                                     'Dr. Lisa Anderson',   'published');

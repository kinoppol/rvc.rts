-- =====================================================
-- RVC RTS — ระบบสารบรรณ วิทยาลัยอาชีวศึกษาร้อยเอ็ด
-- PHP 8 + MariaDB 10
-- =====================================================

CREATE DATABASE IF NOT EXISTS rvc_rts
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE rvc_rts;

-- ------------------------------------------------
-- Users
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username   VARCHAR(50)  NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,
  name       VARCHAR(100) NOT NULL,
  nickname   VARCHAR(50)  DEFAULT '',
  title      VARCHAR(100) DEFAULT '',
  role       ENUM('admin','director','deputy','head','dept_head','teacher','staff') NOT NULL DEFAULT 'staff',
  dept       VARCHAR(150) DEFAULT '',
  active     TINYINT(1)   NOT NULL DEFAULT 1,
  email      VARCHAR(150) DEFAULT '',
  avatar     VARCHAR(255) DEFAULT '',
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Add columns for existing installations (safe to re-run)
ALTER TABLE users ADD COLUMN IF NOT EXISTS nickname    VARCHAR(50)  DEFAULT '' AFTER name;
ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar      VARCHAR(255) DEFAULT '' AFTER email;
ALTER TABLE users ADD COLUMN IF NOT EXISTS extra_roles JSON         DEFAULT NULL AFTER role;

CREATE TABLE IF NOT EXISTS user_departments (
  id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id  INT UNSIGNED NOT NULL,
  dep_name VARCHAR(200) NOT NULL,
  dep_id   INT UNSIGNED DEFAULT NULL,
  UNIQUE KEY uq_user_dep (user_id, dep_name),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------
-- Incoming documents
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS documents_in (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  doc_number      VARCHAR(40)  NOT NULL UNIQUE,
  received_date   DATE         NOT NULL,
  from_org        VARCHAR(250) NOT NULL,
  from_short      VARCHAR(80)  DEFAULT '',
  subject         TEXT         NOT NULL,
  doc_type        ENUM('ราชการ','เอกชน','บุคคล') NOT NULL DEFAULT 'ราชการ',
  pages           SMALLINT     DEFAULT 0,
  urgency         ENUM('normal','urgent','critical') NOT NULL DEFAULT 'normal',
  secrecy         ENUM('none','secret','top_secret') NOT NULL DEFAULT 'none',
  status          ENUM('pending_annotation','pending_deputy','pending_director','assigned','in_progress','done','blocked') NOT NULL DEFAULT 'pending_annotation',
  file_path       VARCHAR(500) DEFAULT NULL,
  annotation      TEXT         DEFAULT NULL,
  deputy_note     TEXT         DEFAULT NULL,
  director_note   TEXT         DEFAULT NULL,
  reply_text      TEXT         DEFAULT NULL,
  created_by      INT UNSIGNED DEFAULT NULL,
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------
-- Document ↔ Department (many-to-many)
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS document_departments (
  doc_id    INT UNSIGNED NOT NULL,
  dept_name VARCHAR(150) NOT NULL,
  PRIMARY KEY (doc_id, dept_name),
  FOREIGN KEY (doc_id) REFERENCES documents_in(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------
-- Outgoing documents
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS documents_out (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  doc_number  VARCHAR(40)  NOT NULL UNIQUE,
  sent_date   DATE         NOT NULL,
  to_org      VARCHAR(250) NOT NULL,
  subject     TEXT         NOT NULL,
  urgency     ENUM('normal','urgent','critical') NOT NULL DEFAULT 'normal',
  status      ENUM('draft','sent') NOT NULL DEFAULT 'sent',
  created_by  INT UNSIGNED DEFAULT NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------
-- Tasks
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS tasks (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task_code       VARCHAR(20)  NOT NULL UNIQUE,
  doc_id          INT UNSIGNED DEFAULT NULL,
  title           VARCHAR(250) NOT NULL,
  assigned_by_id  INT UNSIGNED DEFAULT NULL,
  assigned_to_id  INT UNSIGNED DEFAULT NULL,
  dept            VARCHAR(150) DEFAULT '',
  due_date        DATE         DEFAULT NULL,
  urgency         ENUM('normal','urgent','critical') NOT NULL DEFAULT 'normal',
  status          ENUM('todo','in_progress','blocked','done') NOT NULL DEFAULT 'todo',
  note            TEXT         DEFAULT NULL,
  response        TEXT         DEFAULT NULL,
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (doc_id)          REFERENCES documents_in(id) ON DELETE SET NULL,
  FOREIGN KEY (assigned_by_id)  REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (assigned_to_id)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------
-- Settings
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
  setting_key   VARCHAR(100) NOT NULL PRIMARY KEY,
  setting_value TEXT         DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS departments (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dep_id      INT UNSIGNED NOT NULL UNIQUE,
  depgroup_id INT UNSIGNED NOT NULL DEFAULT 0,
  name        VARCHAR(200) NOT NULL,
  active      TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ================================================
-- SEED DATA
-- ================================================

-- Users (password = "password" for all)
INSERT INTO users (username, password, name, title, role, dept, email) VALUES
('director',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ดร.วิจิตร สุขสม',          'ผู้อำนวยการ',                    'director',  'สถานศึกษา',                          'director@roi.ac.th'),
('deputy1',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นางสาวมาลี รักดี',          'รองผู้อำนวยการ',                'deputy',    'ฝ่ายบริหารทรัพยากร',               'mali@roi.ac.th'),
('deputy2',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นายชาติชาย บุญมี',         'รองผู้อำนวยการ',                'deputy',    'ฝ่ายยุทธศาสตร์และแผนงาน',         'chatchai@roi.ac.th'),
('deputy3',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นางวิไล สดใส',              'รองผู้อำนวยการ',                'deputy',    'ฝ่ายกิจการนักเรียน นักศึกษา',    'vilai@roi.ac.th'),
('deputy4',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นายประสิทธิ์ เก่งงาน',    'รองผู้อำนวยการ',                'deputy',    'ฝ่ายวิชาการ',                       'prasit@roi.ac.th'),
('head1',     '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นางสมจิตร ใจดี',            'หัวหน้างานบริหารงานทั่วไป',    'head',      'ฝ่ายบริหารทรัพยากร',               'somjit@roi.ac.th'),
('head2',     '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นายสมศักดิ์ มั่นคง',      'หัวหน้างานบริหารทรัพยากรบุคคล','head',      'ฝ่ายบริหารทรัพยากร',               'somsak@roi.ac.th'),
('head3',     '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นางรุ่งทิพย์ สว่างใจ',    'หัวหน้างานการเงิน',             'head',      'ฝ่ายบริหารทรัพยากร',               'rungtip@roi.ac.th'),
('dept_h1',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นายอนุรักษ์ รักษาสิ่งแวดล้อม','หัวหน้าแผนกช่างยนต์',        'dept_head', 'ฝ่ายวิชาการ',                       'anurak@roi.ac.th'),
('teacher1',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ครูนันทนา ฉลาดงาน',        'ครู',                            'teacher',   'แผนกช่างอิเล็กทรอนิกส์',          'nanthana@roi.ac.th'),
('teacher2',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ครูพรทิพย์ งามดี',          'ครู',                            'teacher',   'แผนกคอมพิวเตอร์ธุรกิจ',          'porntip@roi.ac.th'),
('staff1',    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นายสมชาย เจ้าหน้าที่',     'เจ้าหน้าที่สารบรรณ',           'staff',     'งานบริหารงานทั่วไป',               'somchai@roi.ac.th'),
('staff2',    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นางสาวพิมพ์ใจ บริการดี',  'เจ้าหน้าที่การเงิน',           'staff',     'งานการเงิน',                        'pimjai@roi.ac.th'),
('staff3',    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นางสมหมาย ขยันทำงาน',     'เจ้าหน้าที่ทะเบียน',           'staff',     'งานทะเบียน',                        'sommai@roi.ac.th')
ON DUPLICATE KEY UPDATE id=id;

-- Incoming documents
INSERT INTO documents_in (doc_number, received_date, from_org, from_short, subject, urgency, secrecy, status, annotation, deputy_note, director_note, reply_text, created_by) VALUES
('รบ.001/2568','2025-06-20','สำนักงานคณะกรรมการการอาชีวศึกษา','สอศ.','แนวปฏิบัติการประเมินผลการเรียนตามหลักสูตร ปวช.2556 (ฉบับปรับปรุง)','normal','none','pending_annotation',NULL,NULL,NULL,NULL,6),
('รบ.002/2568','2025-06-20','สำนักงานคณะกรรมการการอาชีวศึกษา','สอศ.','ประกาศผลการคัดเลือกนักเรียน นักศึกษา เข้าศึกษาต่อ ปีการศึกษา 2568','critical','none','pending_director','เรียน รองฯ ฝ่ายบริหารฯ — หนังสือนี้เกี่ยวข้องกับการประกาศผลคัดเลือก ขอเสนอมอบหมายงานทะเบียนและฝ่ายวิชาการดำเนินการต่อไปโดยด่วน','เห็นควรมอบฝ่ายวิชาการร่วมกับงานทะเบียนดำเนินการเป็นการด่วนที่สุด',NULL,NULL,6),
('รบ.003/2568','2025-06-19','กระทรวงศึกษาธิการ','ศธ.','โครงการทุนการศึกษาเพื่อความเสมอภาคทางการศึกษา ปีการศึกษา 2568','normal','none','assigned','เรียน รองฯ ฝ่ายบริหารฯ — โครงการทุน ศธ. กรุณาพิจารณามอบหมายฝ่ายที่เกี่ยวข้อง','เห็นด้วย','มอบ รองฯ ฝ่ายวิชาการ และ รองฯ ฝ่ายกิจการฯ รวบรวมข้อมูลนักเรียนผู้มีสิทธิ์ ภายใน 25 มิ.ย. 68',NULL,6),
('รบ.004/2568','2025-06-18','กรมบัญชีกลาง','กรมบัญชีกลาง','ซ้อมความเข้าใจการเบิกค่าใช้จ่ายในการเดินทางไปราชการในราชอาณาจักร','normal','none','done','เรียน รองฯ ฝ่ายบริหารฯ — หนังสือซ้อมความเข้าใจ ขอเสนอมอบงานการเงินแจ้งเวียน',NULL,'มอบงานการเงินแจ้งเวียนให้บุคลากรทราบและปฏิบัติ','ดำเนินการแจ้งเวียนบุคลากรทั้งหมดแล้ว เมื่อ 19 มิ.ย. 68',6),
('รบ.005/2568','2025-06-18','สถาบันคุณวุฒิวิชาชีพ (องค์การมหาชน)','สคช.','การรับรองมาตรฐานอาชีพและคุณวุฒิวิชาชีพ สาขาวิชาชีพอุตสาหกรรม ปี 2568','urgent','secret','in_progress','เรียน รองฯ ฝ่ายบริหารฯ — เรื่องนี้มีระดับลับ เกี่ยวข้องกับการรับรองมาตรฐาน ควรเร่งดำเนินการ','เห็นควรเร่งดำเนินการ','มอบ รองฯ ฝ่ายวิชาการ และ รองฯ ฝ่ายยุทธศาสตร์ ดำเนินการโดยเร่งด่วน',NULL,6),
('รบ.006/2568','2025-06-17','สำนักงานตรวจเงินแผ่นดิน','สตง.','แจ้งผลการตรวจสอบงบการเงิน ประจำปีงบประมาณ 2567','critical','top_secret','pending_director','เรียน รองฯ ฝ่ายบริหารฯ — ผลการตรวจสอบงบการเงิน ระดับลับที่สุด ขอเสนอผู้อำนวยการพิจารณาเป็นการด่วนที่สุด','กราบเรียน ท่านผู้อำนวยการ — เรื่องนี้ต้องการการพิจารณาโดยเร่งด่วน',NULL,NULL,6),
('รบ.007/2568','2025-06-17','องค์การบริหารส่วนจังหวัดร้อยเอ็ด','อบจ.ร้อยเอ็ด','ขอความร่วมมือในการจัดกิจกรรมวันเยาวชนแห่งชาติ ประจำปี 2568','normal','none','assigned','เรียน รองฯ ฝ่ายบริหารฯ — หนังสือขอความร่วมมือ ขอเสนอมอบฝ่ายกิจการฯ',NULL,'มอบฝ่ายกิจการนักเรียนฯ จัดกิจกรรมและรายงานผล',NULL,6),
('รบ.008/2568','2025-06-16','บริษัท ไทยซอฟต์แวร์ เน็ตเวิร์ค จำกัด','บ.ไทยซอฟต์ฯ','เสนอขอทำบันทึกข้อตกลงความร่วมมือทางวิชาการ (MOU) ด้านเทคโนโลยีดิจิทัล','normal','none','pending_annotation',NULL,NULL,NULL,NULL,6)
ON DUPLICATE KEY UPDATE id=id;

-- Document departments
INSERT IGNORE INTO document_departments (doc_id, dept_name) VALUES
(2,'ฝ่ายวิชาการ'),(2,'งานทะเบียน'),
(3,'ฝ่ายวิชาการ'),(3,'ฝ่ายกิจการนักเรียนฯ'),
(4,'งานการเงิน'),
(5,'ฝ่ายวิชาการ'),(5,'ฝ่ายยุทธศาสตร์ฯ'),
(7,'ฝ่ายกิจการนักเรียนฯ');

-- Outgoing documents
INSERT INTO documents_out (doc_number, sent_date, to_org, subject, urgency, status, created_by) VALUES
('ส.001/2568','2025-06-21','สำนักงานคณะกรรมการการอาชีวศึกษา','ส่งรายงานผลการดำเนินงานโครงการ ปี 2568','normal','sent',6),
('ส.002/2568','2025-06-20','จังหวัดร้อยเอ็ด','ขอสนับสนุนงบประมาณโครงการพัฒนาสถานศึกษา','normal','sent',6),
('ส.003/2568','2025-06-19','กระทรวงศึกษาธิการ','รายงานข้อมูลนักเรียน นักศึกษา ภาคเรียนที่ 1/2568','urgent','sent',6),
('ส.004/2568','2025-06-17','สถาบันคุณวุฒิวิชาชีพ','ส่งเอกสารประกอบการขอรับรองมาตรฐาน','urgent','sent',6),
('ส.005/2568','2025-06-15','กรมบัญชีกลาง','ขอรับการสนับสนุนงบประมาณพิเศษ','normal','sent',6)
ON DUPLICATE KEY UPDATE id=id;

-- Tasks
INSERT INTO tasks (task_code, doc_id, title, assigned_by_id, assigned_to_id, dept, due_date, urgency, status, note, response) VALUES
('T001',2,'ประกาศผลการคัดเลือกนักเรียน',1,5,'ฝ่ายวิชาการ','2025-06-23','critical','todo','ให้ตรวจสอบรายชื่อและประกาศผ่านบอร์ดประชาสัมพันธ์',''),
('T002',3,'โครงการทุนการศึกษา (ฝ่ายกิจการ)',1,4,'ฝ่ายกิจการนักเรียนฯ','2025-06-25','normal','in_progress','ประสานงานนักเรียนที่มีสิทธิ์รับทุน',''),
('T003',3,'โครงการทุนการศึกษา (ฝ่ายวิชาการ)',1,5,'ฝ่ายวิชาการ','2025-06-25','normal','done','ตรวจสอบเกรดเฉลี่ยนักเรียน','รวบรวมข้อมูลเกรดเฉลี่ยนักเรียน 45 คน ส่งให้ฝ่ายกิจการฯ แล้ว'),
('T004',5,'การรับรองมาตรฐานอาชีพ สคช.',1,3,'ฝ่ายยุทธศาสตร์ฯ','2025-06-21','urgent','blocked','เร่งดำเนินการประสานงานหน่วยงาน','ติดขัด — รอเอกสารประกอบจากแผนกช่างอิเล็กทรอนิกส์'),
('T005',7,'กิจกรรมวันเยาวชนแห่งชาติ',1,4,'ฝ่ายกิจการนักเรียนฯ','2025-06-28','normal','in_progress','ประสานงานจัดกิจกรรมและเตรียมนักเรียน',''),
('T006',4,'แจ้งเวียนระเบียบค่าเดินทาง',1,8,'งานการเงิน','2025-06-19','normal','done','แจ้งเวียนบุคลากรทราบ','แจ้งเวียนคำสั่งให้บุคลากรทราบแล้ว เมื่อ 19 มิ.ย. 68')
ON DUPLICATE KEY UPDATE id=id;

-- Settings
INSERT INTO settings (setting_key, setting_value) VALUES
('school_name',   'วิทยาลัยอาชีวศึกษาร้อยเอ็ด'),
('school_under',  'สำนักงานคณะกรรมการการอาชีวศึกษา (สอศ.)'),
('school_prov',   'ร้อยเอ็ด'),
('academic_year', '2568'),
('notif_new_doc', '1'),
('notif_task',    '1'),
('notif_due',     '1'),
('notif_assign',  '1')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

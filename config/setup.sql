CREATE TABLE IF NOT EXISTS app_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  control VARCHAR(10) DEFAULT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  nombre VARCHAR(120) NOT NULL,
  pass_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','regional','estacion','viewer') NOT NULL DEFAULT 'viewer',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_control (control)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS estaciones (
  id_estacion INT NOT NULL PRIMARY KEY,
  oaci CHAR(4) DEFAULT NULL,
  nombre VARCHAR(100) DEFAULT NULL,
  region ENUM('JRTIJ','JRSJD') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_station_perms (
  user_id BIGINT UNSIGNED NOT NULL,
  oaci CHAR(4) NOT NULL,
  can_view TINYINT(1) NOT NULL DEFAULT 1,
  can_edit TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (user_id, oaci)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS documentos_personal (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  control VARCHAR(10) NOT NULL,
  tipo ENUM('licencia','examen_medico','rtari','certificado','doc_licencia') NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  thumb_path VARCHAR(255) DEFAULT NULL,
  mime VARCHAR(64) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  width INT UNSIGNED DEFAULT NULL,
  height INT UNSIGNED DEFAULT NULL,
  hash_sha1 CHAR(40) NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by VARCHAR(80) DEFAULT NULL,
  UNIQUE KEY u_control_tipo (control, tipo),
  KEY idx_control (control)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO estaciones (id_estacion, oaci, nombre, region) VALUES
  (1,'MMSD','San José del Cabo','JRSJD'),
  (2,'MMLP','La Paz','JRSJD'),
  (3,'MMSL','Cabo San Lucas','JRSJD'),
  (4,'MMLT','Loreto','JRSJD'),
  (5,'MMTJ','Tijuana','JRTIJ'),
  (6,'MMML','Mexicali','JRTIJ'),
  (7,'MMPE','Puerto Peñasco','JRTIJ'),
  (8,'MMHO','Hermosillo','JRTIJ'),
  (9,'MMGM','Guaymas','JRTIJ')
ON DUPLICATE KEY UPDATE oaci=VALUES(oaci), nombre=VALUES(nombre), region=VALUES(region);
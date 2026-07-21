USE supplierhub_db;

ALTER TABLE users MODIFY COLUMN role ENUM('umkm','supplier','integrator') NOT NULL;

INSERT INTO users(name,email,password,role)
VALUES('Integration Operator','integrator@b2blink.com','$2y$10$mWm02z6ClvVWIBal.MbSbOl0b5gCKdALG6LHWXIJlZvEeqaf/WWZW','integrator')
ON DUPLICATE KEY UPDATE name=VALUES(name),role=VALUES(role);


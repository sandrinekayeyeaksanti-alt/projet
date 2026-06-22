-- =============================================================================
--  BASE DE DONNÉES : bellevue_db
--  École Belle Vue — Système de gestion scolaire
--  Mise à jour : 2026-06-16
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 0. Création et sélection de la base de données
-- -----------------------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS bellevue_db
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE bellevue_db;

-- =============================================================================
--  SECTION 1 : TABLES STRUCTURELLES
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1.1  classes — Niveaux et options disponibles dans l'école
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS classes (
    id          INT          AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(100) NOT NULL,
    niveau      VARCHAR(50)  NOT NULL,
    option_nom  VARCHAR(100) NOT NULL DEFAULT 'Generale',
    annee       VARCHAR(10)  NOT NULL DEFAULT '2025-2026'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 1.2  admins — Personnel administratif et enseignants
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
    id            INT          AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('admin','prefet','secretariat','enseignant') NOT NULL DEFAULT 'enseignant',
    nom_complet   VARCHAR(150) DEFAULT NULL,
    email         VARCHAR(150) DEFAULT NULL,
    statut        VARCHAR(20)  NOT NULL DEFAULT 'En attente',
    classe_id     INT          DEFAULT NULL,  -- Titulaire d'une classe (enseignants)
    FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 1.3  eleves — Dossiers d'inscription des élèves
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS eleves (
    id                  INT          AUTO_INCREMENT PRIMARY KEY,
    nom                 VARCHAR(100) NOT NULL,
    post_nom_prenom     VARCHAR(150) NOT NULL,
    lieu_date_naissance VARCHAR(150) NOT NULL,
    sexe                ENUM('M','F') NOT NULL,
    classe_id           INT          DEFAULT NULL,
    ecole_provenance    VARCHAR(150) DEFAULT NULL,
    bulletin_path       TEXT         DEFAULT NULL,      -- Chemin du bulletin uploadé
    tuteur_nom          VARCHAR(150) NOT NULL,
    tuteur_tel          VARCHAR(50)  NOT NULL,
    tuteur_email        VARCHAR(150) DEFAULT NULL,
    tuteur_adresse      VARCHAR(255) NOT NULL,
    code_pin            VARCHAR(255) NOT NULL,          -- Stocké hashé (bcrypt)
    statut_paiement     VARCHAR(50)  NOT NULL DEFAULT 'En attente',
    statut_inscription  ENUM('En attente','Validé','Refusé') NOT NULL DEFAULT 'En attente',
    date_inscription    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 1.4  cours — Matières par classe avec enseignant référent
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cours (
    id            INT          AUTO_INCREMENT PRIMARY KEY,
    nom           VARCHAR(150) NOT NULL,
    classe_id     INT          NOT NULL,
    enseignant_id INT          DEFAULT NULL,
    FOREIGN KEY (classe_id)     REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (enseignant_id) REFERENCES admins(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  SECTION 2 : TABLES D'ÉVALUATION
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 2.1  notes — Notes individuelles par élève, cours et période
--       Périodes : '1ère Période' | 'Examen 1er Semestre'
--                  '3ème Période' | 'Examen 2ème Semestre'
--                  'Examen de Repechage'
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notes (
    id        INT            AUTO_INCREMENT PRIMARY KEY,
    eleve_id  INT            NOT NULL,
    cours_id  INT            NOT NULL,
    periode   VARCHAR(100)   NOT NULL,
    note      DECIMAL(5,2)   NOT NULL DEFAULT 0,
    note_max  DECIMAL(5,2)   NOT NULL DEFAULT 20,
    UNIQUE KEY uk_note (eleve_id, cours_id, periode),
    FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
    FOREIGN KEY (cours_id) REFERENCES cours(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2.2  bulletins — Récapitulatif semestriel / annuel par élève
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bulletins (
    id              INT            AUTO_INCREMENT PRIMARY KEY,
    eleve_id        INT            NOT NULL,
    periode         VARCHAR(100)   NOT NULL,           -- ex : '1er Semestre', 'Annuel'
    points_obtenus  DECIMAL(8,2)   NOT NULL DEFAULT 0,
    points_max      DECIMAL(8,2)   NOT NULL DEFAULT 0,
    pourcentage     DECIMAL(5,2)   NOT NULL DEFAULT 0,
    statut          VARCHAR(100)   NOT NULL DEFAULT 'En cours',
    application     VARCHAR(100)   NOT NULL DEFAULT 'Bonne',
    conduite        VARCHAR(100)   NOT NULL DEFAULT 'TB',
    decision        VARCHAR(255)   NOT NULL DEFAULT 'Bonne progression',
    place           SMALLINT       DEFAULT NULL,        -- Classement dans la classe
    nb_eleves       SMALLINT       DEFAULT NULL,        -- Effectif de la classe
    province        VARCHAR(100)   DEFAULT 'LOMAMI',
    ville           VARCHAR(100)   DEFAULT 'MWENE-DITU',
    commune         VARCHAR(100)   DEFAULT 'BONDYOI',
    ecole           VARCHAR(150)   DEFAULT 'ECOLE BELLE VUE',
    code_ecole      VARCHAR(50)    DEFAULT '9006613',
    annee_scolaire  VARCHAR(20)    DEFAULT '2025-2026',
    no_id           VARCHAR(50)    DEFAULT NULL,
    statut_jury     VARCHAR(50)    DEFAULT NULL,
    UNIQUE KEY uk_bulletin (eleve_id, periode),
    FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  SECTION 3 : TABLES DE SUIVI
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 3.1  presences — Registre des présences journalières
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS presences (
    id             INT  AUTO_INCREMENT PRIMARY KEY,
    eleve_id       INT  NOT NULL,
    date_presence  DATE NOT NULL,
    statut         ENUM('Présent','Absent','Justifié') NOT NULL DEFAULT 'Présent',
    commentaire    TEXT DEFAULT NULL,
    FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3.2  paiements — Suivi des paiements de frais scolaires
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS paiements (
    id             INT            AUTO_INCREMENT PRIMARY KEY,
    eleve_id       INT            NOT NULL,
    type_frais     VARCHAR(100)   NOT NULL,
    montant        DECIMAL(10,2)  NOT NULL DEFAULT 0,
    date_paiement  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    methode_paiement VARCHAR(50)  NOT NULL DEFAULT 'Espèces',
    reference_transaction VARCHAR(100) DEFAULT NULL,
    statut         ENUM('Payé','En attente','Échoué') NOT NULL DEFAULT 'En attente',
    note           TEXT           DEFAULT NULL,
    FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3.3  horaires — Emplois du temps par classe
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS horaires (
    id         INT         AUTO_INCREMENT PRIMARY KEY,
    classe_id  INT         NOT NULL,
    cours_id   INT         DEFAULT NULL,
    jour       ENUM('Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi') NOT NULL,
    heure_debut TIME        NOT NULL,
    heure_fin  TIME        NOT NULL,
    salle      VARCHAR(50) DEFAULT NULL,
    FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (cours_id)  REFERENCES cours(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  SECTION 4 : DONNÉES DE RÉFÉRENCE — CLASSES
-- =============================================================================

INSERT IGNORE INTO classes (nom, niveau, option_nom) VALUES
-- Maternelle
('1ere Maternelle', 'Maternelle', 'Generale'),
('2eme Maternelle', 'Maternelle', 'Generale'),
('3eme Maternelle', 'Maternelle', 'Generale'),

-- Primaire
('1ere Primaire', 'Primaire', 'Generale'),
('2eme Primaire', 'Primaire', 'Generale'),
('3eme Primaire', 'Primaire', 'Generale'),
('4eme Primaire', 'Primaire', 'Generale'),
('5eme Primaire', 'Primaire', 'Generale'),
('6eme Primaire', 'Primaire', 'Generale'),

-- Secondaire — Éducation de Base
('7eme', 'Secondaire', 'Generale'),
('8eme', 'Secondaire', 'Generale'),

-- Secondaire — Humanités Littéraires
('1ere Humanites', 'Secondaire', 'Literaire'),
('2eme Humanites', 'Secondaire', 'Literaire'),
('3eme Humanites', 'Secondaire', 'Literaire'),
('4eme Humanites', 'Secondaire', 'Literaire'),

-- Secondaire — Humanités Pédagogiques (HP)
('1ere Humanites', 'Secondaire', 'HP'),
('2eme Humanites', 'Secondaire', 'HP'),
('3eme Humanites', 'Secondaire', 'HP'),
('4eme Humanites', 'Secondaire', 'HP'),

-- Secondaire — Sciences
('1ere Humanites', 'Secondaire', 'Scientifique'),
('2eme Humanites', 'Secondaire', 'Scientifique'),
('3eme Humanites', 'Secondaire', 'Scientifique'),
('4eme Humanites', 'Secondaire', 'Scientifique'),

-- Secondaire — Biologie-Chimie
('1ere Humanites', 'Secondaire', 'Biologie Chimie'),
('2eme Humanites', 'Secondaire', 'Biologie Chimie'),
('3eme Humanites', 'Secondaire', 'Biologie Chimie'),
('4eme Humanites', 'Secondaire', 'Biologie Chimie'),

-- Secondaire — Commercial et Gestion
('1ere Humanites', 'Secondaire', 'Commercial et Gestion'),
('2eme Humanites', 'Secondaire', 'Commercial et Gestion'),
('3eme Humanites', 'Secondaire', 'Commercial et Gestion'),
('4eme Humanites', 'Secondaire', 'Commercial et Gestion'),

-- Secondaire — Technique
('1ere Humanites', 'Secondaire', 'Technique'),
('2eme Humanites', 'Secondaire', 'Technique'),
('3eme Humanites', 'Secondaire', 'Technique'),
('4eme Humanites', 'Secondaire', 'Technique'),

-- Secondaire — Hôtellerie
('1ere Humanites', 'Secondaire', 'Hotellerie'),
('2eme Humanites', 'Secondaire', 'Hotellerie'),
('3eme Humanites', 'Secondaire', 'Hotellerie'),
('4eme Humanites', 'Secondaire', 'Hotellerie');

-- =============================================================================
--  SECTION 5 : NETTOYAGE / INITIALISATION VIDE
-- =============================================================================
-- La base est initialisée vide pour la production. Le premier compte préfet est créé au premier démarrage.


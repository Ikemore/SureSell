/* ============================================================
    SureSell / Nokware Market - Database Schema
    A trust-first local marketplace.
    ============================================================ */

CREATE DATABASE IF NOT EXISTS nokware_market
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE nokware_market;

-- ------------------------------------------------------------
-- USERS
-- ------------------------------------------------------------
CREATE TABLE users (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name           VARCHAR(100)        NOT NULL,
    business_name       VARCHAR(120)        DEFAULT NULL,
    email               VARCHAR(190)        NOT NULL UNIQUE,
    phone               VARCHAR(20)         NOT NULL UNIQUE,
    password_hash       VARCHAR(255)        NOT NULL,
    role                ENUM('trader','buyer','admin') NOT NULL DEFAULT 'trader',
    town                VARCHAR(100)        NOT NULL,
    region              VARCHAR(40)         NOT NULL DEFAULT 'Unspecified',
    bio                 VARCHAR(500)        DEFAULT NULL,
    avatar_path         VARCHAR(255)        DEFAULT NULL,

    -- trust / verification
    phone_verified      TINYINT(1)          NOT NULL DEFAULT 0,
    email_verified      TINYINT(1)          NOT NULL DEFAULT 0,
    id_verified         TINYINT(1)          NOT NULL DEFAULT 0,
    id_document_path    VARCHAR(255)        DEFAULT NULL,
    trust_score         INT                 NOT NULL DEFAULT 0,
    deals_completed     INT UNSIGNED        NOT NULL DEFAULT 0,

    -- account security
    failed_logins       TINYINT UNSIGNED    NOT NULL DEFAULT 0,
    locked_until        DATETIME            DEFAULT NULL,
    status              ENUM('active','suspended','banned') NOT NULL DEFAULT 'active',

    created_at          TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_town (town),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- OTP CODES (phone verification)
-- ------------------------------------------------------------
CREATE TABLE otp_codes (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    code_hash     VARCHAR(255) NOT NULL,
    purpose       ENUM('phone_verify','email_verify','password_reset') NOT NULL DEFAULT 'phone_verify',
    expires_at    DATETIME NOT NULL,
    attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    used          TINYINT(1) NOT NULL DEFAULT 0,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_purpose (user_id, purpose)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- CATEGORIES
-- ------------------------------------------------------------
CREATE TABLE categories (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name    VARCHAR(80) NOT NULL UNIQUE,
    slug    VARCHAR(80) NOT NULL UNIQUE,
    icon    VARCHAR(50) DEFAULT NULL
) ENGINE=InnoDB;

INSERT INTO categories (name, slug, icon) VALUES
 ('Household Goods', 'household-goods', 'home'),
 ('Electronics', 'electronics', 'bolt'),
 ('Fashion & Textiles', 'fashion-textiles', 'shirt'),
 ('Building Materials', 'building-materials', 'bricks'),
 ('Farm Produce', 'farm-produce', 'leaf'),
 ('Vehicles & Parts', 'vehicles-parts', 'car'),
 ('Artisan Craft & Furniture', 'artisan-craft-furniture', 'hammer'),
 ('Services', 'services', 'wrench');

-- ------------------------------------------------------------
-- LISTINGS
-- ------------------------------------------------------------
CREATE TABLE listings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    category_id     INT UNSIGNED NOT NULL,
    title           VARCHAR(140) NOT NULL,
    description     TEXT NOT NULL,
    price           DECIMAL(12,2) NOT NULL,
    negotiable      TINYINT(1) NOT NULL DEFAULT 0,
    town             VARCHAR(100) NOT NULL,
    region           VARCHAR(40) NOT NULL DEFAULT 'Unspecified',
    status          ENUM('pending','active','sold','removed') NOT NULL DEFAULT 'pending',
    views           INT UNSIGNED NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FULLTEXT INDEX idx_search (title, description),
    INDEX idx_town_status (town, status),
    INDEX idx_region_status (region, status),
    INDEX idx_category (category_id)
) ENGINE=InnoDB;

CREATE TABLE listing_images (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    listing_id  INT UNSIGNED NOT NULL,
    image_path  VARCHAR(255) NOT NULL,
    sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- DEALS / TRANSACTIONS (buyer confirms a completed deal)
-- ------------------------------------------------------------
CREATE TABLE deals (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    listing_id      INT UNSIGNED NOT NULL,
    seller_id       INT UNSIGNED NOT NULL,
    buyer_id        INT UNSIGNED NOT NULL,
    status          ENUM('requested','confirmed','disputed','cancelled') NOT NULL DEFAULT 'requested',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    confirmed_at    DATETIME DEFAULT NULL,

    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- REVIEWS (only tied to confirmed deals)
-- ------------------------------------------------------------
CREATE TABLE reviews (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    deal_id       INT UNSIGNED NOT NULL UNIQUE,
    reviewer_id   INT UNSIGNED NOT NULL,
    reviewee_id   INT UNSIGNED NOT NULL,
    rating        TINYINT UNSIGNED NOT NULL,
    comment       VARCHAR(500) DEFAULT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (deal_id) REFERENCES deals(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewee_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- REPORTS (abuse / scam flagging)
-- ------------------------------------------------------------
CREATE TABLE reports (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reporter_id         INT UNSIGNED NOT NULL,
    reported_user_id    INT UNSIGNED DEFAULT NULL,
    reported_listing_id INT UNSIGNED DEFAULT NULL,
    reason              VARCHAR(100) NOT NULL,
    details             VARCHAR(500) DEFAULT NULL,
    status              ENUM('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open',
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at         DATETIME DEFAULT NULL,

    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- CONTACT REVEAL LOG (audit trail — who viewed whose phone number)
-- ------------------------------------------------------------
CREATE TABLE contact_reveals (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    viewer_id     INT UNSIGNED NOT NULL,
    listing_id    INT UNSIGNED NOT NULL,
    revealed_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address    VARCHAR(45) DEFAULT NULL,

    FOREIGN KEY (viewer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- LOGIN AUDIT LOG
-- ------------------------------------------------------------
CREATE TABLE login_audit (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED DEFAULT NULL,
    email_tried   VARCHAR(190) DEFAULT NULL,
    success       TINYINT(1) NOT NULL,
    ip_address    VARCHAR(45) DEFAULT NULL,
    user_agent    VARCHAR(255) DEFAULT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Seed an admin account (password: ChangeMe!2026 — CHANGE AFTER FIRST LOGIN)
-- Hash generated with PHP password_hash() — see README for how to regenerate
-- ------------------------------------------------------------
INSERT INTO users (full_name, email, phone, password_hash, role, town, phone_verified, id_verified, status)
VALUES ('Site Admin', 'apponly79@gmail.com', '0000000000',
'$2y$10$sSKn6ZJ0dg2fZ2wD7oZ8SOEXHXTsMz64Q1H2gV0kXe5Y4G1YlwsSK', -- placeholder, regenerate via README
'admin', 'Nkawie', 1, 1, 'active');

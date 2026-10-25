CREATE TABLE IF NOT EXISTS settings (`key` VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, value TEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS accounts (
 id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 email VARCHAR(254) NULL UNIQUE, name VARCHAR(100) NOT NULL, phone VARCHAR(30) NOT NULL DEFAULT '',
 role VARCHAR(16) NOT NULL DEFAULT 'reseller', username VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 password_hash VARCHAR(255) CHARACTER SET ascii NOT NULL, login_version INT NOT NULL DEFAULT 1,
 active TINYINT NOT NULL DEFAULT 1, created_at VARCHAR(30) NOT NULL, updated_at VARCHAR(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS products (
 sku VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 name VARCHAR(150) NOT NULL, en VARCHAR(180) NOT NULL DEFAULT '', step VARCHAR(80) NOT NULL DEFAULT '', role VARCHAR(100) NOT NULL DEFAULT '',
 description TEXT NOT NULL, tags TEXT NOT NULL, image VARCHAR(500) NOT NULL,
 retail_price BIGINT NOT NULL, reseller_price BIGINT NULL, active TINYINT NOT NULL DEFAULT 1, bundle TINYINT NOT NULL DEFAULT 0,
 sort_order INT NOT NULL DEFAULT 0, revision INT NOT NULL DEFAULT 1, updated_at VARCHAR(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS hero_banners (
 id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, desktop_image VARCHAR(500) NOT NULL, mobile_image VARCHAR(500) NOT NULL,
 alt VARCHAR(200) NOT NULL, kicker VARCHAR(100) NOT NULL, title VARCHAR(150) NOT NULL, body VARCHAR(500) NOT NULL, cta VARCHAR(60) NOT NULL,
 href VARCHAR(80) NOT NULL, active TINYINT NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0, revision INT NOT NULL DEFAULT 1, updated_at VARCHAR(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS orders (
 id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 idempotency_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 request_hash CHAR(64) CHARACTER SET ascii NOT NULL, customer_name VARCHAR(80) NOT NULL, phone VARCHAR(8) NOT NULL,
 address VARCHAR(500) NOT NULL, note TEXT NOT NULL, payment VARCHAR(20) NOT NULL, items MEDIUMTEXT NOT NULL,
 subtotal BIGINT NOT NULL, delivery BIGINT NOT NULL, total BIGINT NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'new',
 notification_status VARCHAR(20) NOT NULL DEFAULT 'pending', created_at VARCHAR(30) NOT NULL,
 channel VARCHAR(20) NOT NULL DEFAULT 'retail', reseller_id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NULL,
 payment_status VARCHAR(20) NOT NULL DEFAULT 'unverified', paid_at VARCHAR(30) NULL,
 INDEX orders_channel_created (channel,created_at), INDEX orders_reseller_created (reseller_id,created_at),
 CONSTRAINT orders_reseller_fk FOREIGN KEY (reseller_id) REFERENCES accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS login_sessions (
 token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 account_id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, credential_version INT NOT NULL,
 created_at BIGINT NOT NULL, expires_at BIGINT NOT NULL, INDEX sessions_expiry (expires_at),
 CONSTRAINT sessions_account_fk FOREIGN KEY (account_id) REFERENCES accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS login_limits (
 `key` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, window_start BIGINT NOT NULL, attempts INT NOT NULL DEFAULT 0,
 INDEX limits_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS payment_attempts (
 id VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, order_id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 provider VARCHAR(20) NOT NULL DEFAULT 'qpay', environment VARCHAR(20) NOT NULL DEFAULT 'sandbox', amount BIGINT NOT NULL,
 currency CHAR(3) NOT NULL DEFAULT 'MNT', status VARCHAR(20) NOT NULL DEFAULT 'prepared',
 provider_invoice_id VARCHAR(200) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE,
 provider_payment_id VARCHAR(200) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE,
 created_at VARCHAR(30) NOT NULL, updated_at VARCHAR(30) NOT NULL, paid_at VARCHAR(30) NULL,
 CONSTRAINT payments_order_fk FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

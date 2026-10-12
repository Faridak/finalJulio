-- Migration 035: own shipping calculation (DHL-style + Google Maps distance).
CREATE TABLE IF NOT EXISTS shipping_distance_cache (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    route_hash   CHAR(32) NOT NULL,           -- md5(origin|destination)
    origin_text  VARCHAR(255) NOT NULL,
    dest_text    VARCHAR(255) NOT NULL,
    distance_km  DECIMAL(8,1) NOT NULL,
    duration_min INT NULL,
    source       ENUM('google','estimate') NOT NULL DEFAULT 'estimate',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_route (route_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

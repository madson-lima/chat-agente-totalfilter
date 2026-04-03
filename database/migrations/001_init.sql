CREATE TABLE IF NOT EXISTS chat_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_token VARCHAR(64) NOT NULL UNIQUE,
    visitor_id VARCHAR(64) NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'active',
    lead_status VARCHAR(40) NOT NULL DEFAULT 'none',
    page_url VARCHAR(255) DEFAULT NULL,
    referrer_url VARCHAR(255) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(64) DEFAULT NULL,
    locale VARCHAR(12) DEFAULT 'pt-BR',
    summary TEXT DEFAULT NULL,
    last_topic VARCHAR(120) DEFAULT NULL,
    last_user_message TEXT DEFAULT NULL,
    last_assistant_message TEXT DEFAULT NULL,
    message_count INT UNSIGNED NOT NULL DEFAULT 0,
    metadata_json JSON DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_chat_sessions_visitor (visitor_id),
    INDEX idx_chat_sessions_status (status),
    INDEX idx_chat_sessions_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id BIGINT UNSIGNED NOT NULL,
    role ENUM('system', 'assistant', 'user') NOT NULL,
    content MEDIUMTEXT NOT NULL,
    meta_json JSON DEFAULT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_chat_messages_session FOREIGN KEY (session_id) REFERENCES chat_sessions (id) ON DELETE CASCADE,
    INDEX idx_chat_messages_session (session_id),
    INDEX idx_chat_messages_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS faq_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(100) DEFAULT NULL,
    question VARCHAR(255) NOT NULL,
    answer TEXT NOT NULL,
    keywords VARCHAR(255) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_faq_items_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS knowledge_pages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(160) NOT NULL UNIQUE,
    title VARCHAR(180) NOT NULL,
    excerpt TEXT DEFAULT NULL,
    content MEDIUMTEXT NOT NULL,
    source_type VARCHAR(50) NOT NULL DEFAULT 'manual',
    source_url VARCHAR(255) DEFAULT NULL,
    keywords VARCHAR(255) DEFAULT NULL,
    priority INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_knowledge_pages_active (is_active, priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_index (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_code VARCHAR(100) DEFAULT NULL,
    product_name VARCHAR(180) NOT NULL,
    category VARCHAR(120) DEFAULT NULL,
    application_summary TEXT DEFAULT NULL,
    technical_notes TEXT DEFAULT NULL,
    status_label VARCHAR(60) DEFAULT NULL,
    product_url VARCHAR(255) DEFAULT NULL,
    keywords VARCHAR(255) DEFAULT NULL,
    is_launch TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_product_index_active (is_active, is_launch),
    INDEX idx_product_index_name (product_name),
    INDEX idx_product_index_code (product_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id BIGINT UNSIGNED DEFAULT NULL,
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    email VARCHAR(190) DEFAULT NULL,
    company VARCHAR(120) DEFAULT NULL,
    city_state VARCHAR(120) DEFAULT NULL,
    product_interest VARCHAR(255) DEFAULT NULL,
    message TEXT DEFAULT NULL,
    source VARCHAR(60) NOT NULL DEFAULT 'chat-widget',
    status VARCHAR(40) NOT NULL DEFAULT 'new',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_leads_session FOREIGN KEY (session_id) REFERENCES chat_sessions (id) ON DELETE SET NULL,
    INDEX idx_leads_status (status),
    INDEX idx_leads_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS human_handoff_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id BIGINT UNSIGNED DEFAULT NULL,
    name VARCHAR(120) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    email VARCHAR(190) DEFAULT NULL,
    reason TEXT DEFAULT NULL,
    preferred_channel VARCHAR(60) DEFAULT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_handoff_session FOREIGN KEY (session_id) REFERENCES chat_sessions (id) ON DELETE SET NULL,
    INDEX idx_handoff_status (status),
    INDEX idx_handoff_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assistant_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(120) NOT NULL UNIQUE,
    setting_value MEDIUMTEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(64) NOT NULL UNIQUE,
    request_count INT UNSIGNED NOT NULL DEFAULT 0,
    window_start DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

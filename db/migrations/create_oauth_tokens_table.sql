-- Migration: Create oauth_tokens table
-- Description: This table stores OAuth tokens for different cloud service providers

CREATE TABLE IF NOT EXISTS oauth_tokens (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    provider VARCHAR(50) NOT NULL,
    access_token TEXT NOT NULL,
    refresh_token TEXT,
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign key constraint (assuming users table exists)
    CONSTRAINT fk_oauth_tokens_user_id 
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Unique constraint to prevent duplicate tokens for same user and provider
    CONSTRAINT uk_oauth_tokens_user_provider 
        UNIQUE (user_id, provider)
);

-- Index for better performance on lookups
CREATE INDEX IF NOT EXISTS idx_oauth_tokens_user_provider 
    ON oauth_tokens(user_id, provider);

-- Index for token expiration checks
CREATE INDEX IF NOT EXISTS idx_oauth_tokens_expires_at 
    ON oauth_tokens(expires_at) WHERE expires_at IS NOT NULL; 
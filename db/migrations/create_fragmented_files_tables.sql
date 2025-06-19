-- Migration: Create fragmented files tables
-- Description: Tables to manage file fragmentation and redundancy across cloud services

-- Create the trigger function if it doesn't exist
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Table to track fragmented files metadata
CREATE TABLE IF NOT EXISTS fragmented_files (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    original_size BIGINT NOT NULL,
    mime_type VARCHAR(100),
    file_hash VARCHAR(64) NOT NULL, -- SHA-256 hash of original file
    chunk_size INTEGER NOT NULL DEFAULT 1048576, -- Default 1MB chunks
    total_chunks INTEGER NOT NULL,
    redundancy_level INTEGER NOT NULL DEFAULT 2, -- How many copies of each chunk
    status VARCHAR(20) NOT NULL DEFAULT 'uploading', -- uploading, complete, error
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign key constraint
    CONSTRAINT fk_fragmented_files_user_id 
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table to track individual file fragments and their storage locations
CREATE TABLE IF NOT EXISTS file_fragments (
    id SERIAL PRIMARY KEY,
    fragmented_file_id INTEGER NOT NULL,
    chunk_index INTEGER NOT NULL, -- Order of chunk in original file (0-based)
    chunk_hash VARCHAR(64) NOT NULL, -- SHA-256 hash of chunk content
    chunk_size INTEGER NOT NULL, -- Actual size of this chunk
    storage_locations JSONB NOT NULL, -- Array of storage info: [{"provider": "dropbox", "file_id": "xxx", "path": "/fragments/..."}, ...]
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign key constraint
    CONSTRAINT fk_file_fragments_fragmented_file_id 
        FOREIGN KEY (fragmented_file_id) REFERENCES fragmented_files(id) ON DELETE CASCADE,
    
    -- Unique constraint for chunk within a file
    CONSTRAINT uk_file_fragments_file_chunk 
        UNIQUE (fragmented_file_id, chunk_index)
);

-- Indexes for better performance
CREATE INDEX IF NOT EXISTS idx_fragmented_files_user_id 
    ON fragmented_files(user_id);

CREATE INDEX IF NOT EXISTS idx_fragmented_files_status 
    ON fragmented_files(status);

CREATE INDEX IF NOT EXISTS idx_fragmented_files_hash 
    ON fragmented_files(file_hash);

CREATE INDEX IF NOT EXISTS idx_file_fragments_fragmented_file_id 
    ON file_fragments(fragmented_file_id);

CREATE INDEX IF NOT EXISTS idx_file_fragments_chunk_index 
    ON file_fragments(fragmented_file_id, chunk_index);

-- Drop existing triggers if they exist
DROP TRIGGER IF EXISTS update_fragmented_files_updated_at ON fragmented_files;
DROP TRIGGER IF EXISTS update_file_fragments_updated_at ON file_fragments;

-- Create triggers to update updated_at column
CREATE TRIGGER update_fragmented_files_updated_at 
    BEFORE UPDATE ON fragmented_files 
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_file_fragments_updated_at 
    BEFORE UPDATE ON file_fragments 
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column(); 
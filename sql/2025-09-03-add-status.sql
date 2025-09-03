-- Migrazione: aggiunta colonna 'status' ai libri
ALTER TABLE books
  ADD COLUMN IF NOT EXISTS status ENUM('non_letto','in_lettura','letto') NOT NULL DEFAULT 'non_letto';

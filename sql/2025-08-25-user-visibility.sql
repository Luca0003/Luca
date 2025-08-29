-- Migrazione: visibilità per utente
-- Assicura che la tabella books abbia il campo user_id e FK verso users(id)

ALTER TABLE books
  ADD COLUMN IF NOT EXISTS user_id INT NOT NULL;

-- Se esistono righe senza user_id, assegnale temporaneamente all'utente 1 (modifica se diverso)
UPDATE books SET user_id = 1 WHERE user_id IS NULL;

-- Aggiungi FK e indice (se non già presenti)
ALTER TABLE books
  ADD CONSTRAINT fk_books_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

CREATE INDEX idx_books_user ON books(user_id);

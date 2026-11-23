-- Login Google para clientes.
-- Idempotente: pode ser executada de novo sem erro.
ALTER TABLE `usuario`
    ADD COLUMN IF NOT EXISTS `google_sub` VARCHAR(255) NULL AFTER `senha_hash`,
    ADD UNIQUE KEY IF NOT EXISTS `un_usuario_google_sub` (`google_sub`);

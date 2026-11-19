-- Login Google para clientes.
-- Execute uma única vez no banco existente antes de implantar o novo código.
ALTER TABLE `usuario`
    ADD COLUMN `google_sub` VARCHAR(255) NULL AFTER `senha_hash`,
    ADD UNIQUE KEY `un_usuario_google_sub` (`google_sub`);

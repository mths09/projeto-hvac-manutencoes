-- Registro do aceite da política de privacidade e dos termos.
-- Idempotente: pode ser executada de novo sem erro nem perda de dados.
CREATE TABLE IF NOT EXISTS `aceite_politica` (
    `id_aceite` BIGINT NOT NULL AUTO_INCREMENT,
    `id_usuario` BIGINT NOT NULL,
    `versao` VARCHAR(20) NOT NULL,
    `aceito_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ip_hash` CHAR(64) NOT NULL,
    `user_agent_hash` CHAR(64) NOT NULL,
    PRIMARY KEY (`id_aceite`),
    UNIQUE KEY `un_aceite_usuario_versao` (`id_usuario`, `versao`),
    CONSTRAINT `fk_aceite_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Confirmação de posse do endereço antes de criar a conta.
-- A tabela de aceite é incluída como IF NOT EXISTS para instalações que ainda não aplicaram a migração 002.
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

CREATE TABLE IF NOT EXISTS `cadastro_pendente` (
    `id_cadastro_pendente` BIGINT NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(254) NOT NULL,
    `telefone` VARCHAR(25) NOT NULL,
    `dados_json` LONGTEXT NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `ip_hash` CHAR(64) NOT NULL,
    `criada_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expira_em` DATETIME NOT NULL,
    `enviado_em` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id_cadastro_pendente`),
    UNIQUE KEY `un_cadastro_pendente_email` (`email`),
    UNIQUE KEY `un_cadastro_pendente_telefone` (`telefone`),
    UNIQUE KEY `un_cadastro_pendente_token_hash` (`token_hash`),
    KEY `idx_cadastro_pendente_ip_data` (`ip_hash`, `criada_em`),
    KEY `idx_cadastro_pendente_expira` (`expira_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
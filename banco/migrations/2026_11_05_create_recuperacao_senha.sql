-- Migração idempotente para bancos HVAC já existentes.
-- Pode ser executada novamente sem apagar os dados da tabela.
USE `hvac_db`;

CREATE TABLE IF NOT EXISTS `recuperacao_senha` (
  `id_recuperacao` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_usuario` bigint(20) DEFAULT NULL,
  `email_hash` char(64) NOT NULL,
  `ip_hash` char(64) NOT NULL,
  `token_hash` char(64) DEFAULT NULL,
  `criada_em` datetime NOT NULL DEFAULT current_timestamp(),
  `expira_em` datetime DEFAULT NULL,
  `utilizada_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id_recuperacao`),
  UNIQUE KEY `un_recuperacao_token_hash` (`token_hash`),
  KEY `idx_recuperacao_email_data` (`email_hash`, `criada_em`),
  KEY `idx_recuperacao_ip_data` (`ip_hash`, `criada_em`),
  KEY `idx_recuperacao_usuario_status` (`id_usuario`, `utilizada_em`),
  CONSTRAINT `fk_recuperacao_usuario`
    FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recuperacao_senha (
    id_recuperacao BIGINT NOT NULL AUTO_INCREMENT,
    id_usuario BIGINT NULL,
    email_hash CHAR(64) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    criada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expira_em DATETIME NOT NULL,
    utilizada_em DATETIME NULL,
    PRIMARY KEY (id_recuperacao),
    UNIQUE KEY un_recuperacao_token_hash (token_hash),
    KEY ix_recuperacao_usuario (id_usuario),
    KEY ix_recuperacao_email_criada (email_hash, criada_em),
    KEY ix_recuperacao_ip_criada (ip_hash, criada_em),
    CONSTRAINT fk_recuperacao_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
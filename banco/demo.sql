-- Usuário administrador inicial para instalações limpas
-- A senha armazenada é um hash bcrypt compatível com password_verify().
--
INSERT INTO `usuario` (`id_usuario`, `nome`, `email`, `senha_hash`, `telefone`, `data_nascimento`, `perfil`, `ativo`) VALUES
(1, 'Administrador HVAC', 'admin@hvac.com', '$2y$12$Bjj6NvWQcn8gcnhFUz46iu9dp7WEWlWd73NaeizCnPC3xJXriYG/C', '15999999999', NULL, 'admin', 1);

-- Bloqueia telefones duplicados, inclusive quando escritos com máscaras diferentes.
-- Idempotente: pode ser executada de novo sem erro.
-- A base local tem conflito: corrija o telefone duplicado antes de aplicar.
-- Para listar os IDs e telefones normalizados em conflito:
-- SELECT REGEXP_REPLACE(telefone, '[^0-9]', '') AS telefone_normalizado,
--        GROUP_CONCAT(id_usuario ORDER BY id_usuario) AS contas
-- FROM usuario
-- WHERE REGEXP_REPLACE(telefone, '[^0-9]', '') <> ''
-- GROUP BY telefone_normalizado
-- HAVING COUNT(*) > 1;

ALTER TABLE `usuario`
  ADD COLUMN IF NOT EXISTS `telefone_normalizado` varchar(25)
    GENERATED ALWAYS AS (NULLIF(REGEXP_REPLACE(`telefone`, '[^0-9]', ''), '')) VIRTUAL,
  ADD UNIQUE KEY IF NOT EXISTS `un_usuario_telefone_normalizado` (`telefone_normalizado`);

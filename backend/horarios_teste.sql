-- Dados de demonstração para testar o formulário de agendamento local.
-- Cria dois horários de amanhã sem apagar nem alterar os já cadastrados.
USE hvac_db;
SET time_zone = '-03:00';

INSERT INTO disponibilidade (inicio_previsto, fim_previsto, situacao, capacidade)
SELECT horarios.inicio, DATE_ADD(horarios.inicio, INTERVAL 1 HOUR), 'LIVRE', 1
FROM (
    SELECT TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY), '09:00:00') AS inicio
    UNION ALL
    SELECT TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY), '10:00:00')
) AS horarios
WHERE NOT EXISTS (
    SELECT 1
    FROM disponibilidade AS existente
    WHERE existente.inicio_previsto = horarios.inicio
);

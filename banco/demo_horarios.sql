-- Horários de disponibilidade: dias úteis dos próximos 20 dias, 4 faixas por dia.
-- Mesmo padrão de banco/demo_horarios.sql, só que para 20 dias em vez de 14.
-- Pode ser executado de novo sem duplicar: só cria o que ainda não existe.

SET time_zone = '-03:00';

INSERT INTO disponibilidade (inicio_previsto, fim_previsto, situacao, capacidade)
SELECT d.inicio, DATE_ADD(d.inicio, INTERVAL 1 HOUR), 'LIVRE', 1
FROM (
    SELECT TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL dia.n DAY), hora.h) AS inicio
    FROM (
        SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
        UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8
        UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12
        UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL SELECT 16
        UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20
    ) AS dia
    CROSS JOIN (
        SELECT '09:00:00' AS h UNION ALL SELECT '10:00:00'
        UNION ALL SELECT '14:00:00' UNION ALL SELECT '15:00:00'
    ) AS hora
) AS d
WHERE DAYOFWEEK(d.inicio) NOT IN (1, 7)
  AND NOT EXISTS (
      SELECT 1 FROM disponibilidade AS existente
      WHERE existente.inicio_previsto = d.inicio
  );

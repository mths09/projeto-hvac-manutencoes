-- Triagem MVP para a instalação local do HVAC.
-- Execute depois de banco/hvac_db.sql. Não apaga nem altera questionários existentes.
USE hvac_db;
SET NAMES utf8mb4;
START TRANSACTION;

INSERT INTO questionario (id_tipo_servico, titulo, versao, status, publicado_em)
SELECT ts.id_tipo_servico, dados.titulo, 1, 'PUBLICADO', NOW()
FROM tipo_servico AS ts
JOIN (
    SELECT 'AR_CONDICIONADO' AS codigo, 'Triagem MVP - Ar Condicionado' AS titulo
    UNION ALL SELECT 'REFRIGERACAO', 'Triagem MVP - Refrigeração'
    UNION ALL SELECT 'ILUMINACAO', 'Triagem MVP - Iluminação'
    UNION ALL SELECT 'MANUTENCAO_PREVENTIVA', 'Triagem MVP - Manutenção Preventiva'
    UNION ALL SELECT 'ELETROMECANICA', 'Triagem MVP - Eletromecânica'
) AS dados ON dados.codigo = ts.codigo
WHERE ts.ativo = 1
  AND NOT EXISTS (
      SELECT 1 FROM questionario AS existente
      WHERE existente.id_tipo_servico = ts.id_tipo_servico
        AND existente.versao = 1
  );

INSERT INTO pergunta_triagem
    (id_questionario, codigo, enunciado, ajuda, tipo_resposta, obrigatoria, ordem)
SELECT q.id_questionario, dados.codigo, dados.enunciado, dados.ajuda,
       'OPCAO_UNICA', 1, dados.ordem
FROM questionario AS q
JOIN tipo_servico AS ts ON ts.id_tipo_servico = q.id_tipo_servico
JOIN (
    SELECT 'AR_CONDICIONADO' AS servico, 'ambiente' AS codigo,
           'Onde está instalado o equipamento?' AS enunciado,
           'Isso ajuda a equipe a entender o local do atendimento.' AS ajuda, 1 AS ordem
    UNION ALL SELECT 'AR_CONDICIONADO', 'necessidade_principal',
           'Qual situação descreve melhor o ar-condicionado?', NULL, 2
    UNION ALL SELECT 'AR_CONDICIONADO', 'prazo',
           'Quando você precisa do atendimento?', NULL, 3
    UNION ALL SELECT 'AR_CONDICIONADO', 'funcionamento',
           'Como o equipamento está funcionando agora?', NULL, 4
    UNION ALL SELECT 'REFRIGERACAO', 'ambiente',
           'Onde está instalado o equipamento?', 'Isso ajuda a equipe a entender o local do atendimento.', 1
    UNION ALL SELECT 'REFRIGERACAO', 'necessidade_principal',
           'Qual situação descreve melhor a refrigeração?', NULL, 2
    UNION ALL SELECT 'REFRIGERACAO', 'prazo',
           'Quando você precisa do atendimento?', NULL, 3
    UNION ALL SELECT 'REFRIGERACAO', 'funcionamento',
           'Como o equipamento está funcionando agora?', NULL, 4
    UNION ALL SELECT 'ILUMINACAO', 'ambiente',
           'Onde está instalado o sistema de iluminação?', 'Isso ajuda a equipe a entender o local do atendimento.', 1
    UNION ALL SELECT 'ILUMINACAO', 'necessidade_principal',
           'Qual situação descreve melhor a iluminação?', NULL, 2
    UNION ALL SELECT 'ILUMINACAO', 'prazo',
           'Quando você precisa do atendimento?', NULL, 3
    UNION ALL SELECT 'ILUMINACAO', 'funcionamento',
           'Como o sistema está funcionando agora?', NULL, 4
    UNION ALL SELECT 'MANUTENCAO_PREVENTIVA', 'ambiente',
           'Onde está instalado o equipamento?', 'Isso ajuda a equipe a entender o local do atendimento.', 1
    UNION ALL SELECT 'MANUTENCAO_PREVENTIVA', 'necessidade_principal',
           'O que você deseja incluir na manutenção preventiva?', NULL, 2
    UNION ALL SELECT 'MANUTENCAO_PREVENTIVA', 'prazo',
           'Quando você gostaria de realizar a manutenção?', NULL, 3
    UNION ALL SELECT 'MANUTENCAO_PREVENTIVA', 'funcionamento',
           'Como os equipamentos estão funcionando agora?', NULL, 4
    UNION ALL SELECT 'ELETROMECANICA', 'ambiente',
           'Onde está instalado o equipamento?', 'Isso ajuda a equipe a entender o local do atendimento.', 1
    UNION ALL SELECT 'ELETROMECANICA', 'necessidade_principal',
           'Qual situação descreve melhor o equipamento eletromecânico?', NULL, 2
    UNION ALL SELECT 'ELETROMECANICA', 'prazo',
           'Quando você precisa do atendimento?', NULL, 3
    UNION ALL SELECT 'ELETROMECANICA', 'funcionamento',
           'Como o equipamento está funcionando agora?', NULL, 4
) AS dados ON dados.servico = ts.codigo
WHERE q.versao = 1
  AND q.status = 'PUBLICADO'
  AND q.titulo LIKE 'Triagem MVP - %'
  AND NOT EXISTS (
      SELECT 1 FROM pergunta_triagem AS existente
      WHERE existente.id_questionario = q.id_questionario
        AND existente.codigo = dados.codigo
  );

INSERT INTO opcao_pergunta (id_pergunta, codigo, rotulo, ordem)
SELECT p.id_pergunta, dados.codigo_opcao, dados.rotulo, dados.ordem
FROM pergunta_triagem AS p
JOIN questionario AS q ON q.id_questionario = p.id_questionario
JOIN (
    SELECT 'ambiente' AS pergunta, 'residencial' AS codigo_opcao, 'Residencial' AS rotulo, 1 AS ordem
    UNION ALL SELECT 'ambiente', 'comercial', 'Comercial', 2
    UNION ALL SELECT 'ambiente', 'industrial', 'Industrial', 3
    UNION ALL SELECT 'prazo', 'o_mais_rapido', 'O quanto antes', 1
    UNION ALL SELECT 'prazo', 'esta_semana', 'Ainda esta semana', 2
    UNION ALL SELECT 'prazo', 'flexivel', 'Tenho flexibilidade de data', 3
    UNION ALL SELECT 'funcionamento', 'normal', 'Está funcionando normalmente', 1
    UNION ALL SELECT 'funcionamento', 'com_falhas', 'Está funcionando com falhas', 2
    UNION ALL SELECT 'funcionamento', 'parado', 'Está parado ou desligado', 3
) AS dados ON dados.pergunta = p.codigo
WHERE q.titulo LIKE 'Triagem MVP - %'
  AND q.versao = 1
  AND NOT EXISTS (
      SELECT 1 FROM opcao_pergunta AS existente
      WHERE existente.id_pergunta = p.id_pergunta
        AND existente.codigo = dados.codigo_opcao
  );

INSERT INTO opcao_pergunta (id_pergunta, codigo, rotulo, ordem)
SELECT p.id_pergunta, dados.codigo_opcao, dados.rotulo, dados.ordem
FROM pergunta_triagem AS p
JOIN questionario AS q ON q.id_questionario = p.id_questionario
JOIN tipo_servico AS ts ON ts.id_tipo_servico = q.id_tipo_servico
JOIN (
    SELECT 'AR_CONDICIONADO' AS servico, 'nao_liga' AS codigo_opcao, 'Não liga' AS rotulo, 1 AS ordem
    UNION ALL SELECT 'AR_CONDICIONADO', 'pouca_refrigeracao', 'Liga, mas refrigera pouco', 2
    UNION ALL SELECT 'AR_CONDICIONADO', 'vazamento_ruido', 'Apresenta vazamento ou ruído', 3
    UNION ALL SELECT 'REFRIGERACAO', 'nao_liga', 'Não liga', 1
    UNION ALL SELECT 'REFRIGERACAO', 'temperatura_instavel', 'Não mantém a temperatura adequada', 2
    UNION ALL SELECT 'REFRIGERACAO', 'vazamento_ruido', 'Apresenta vazamento ou ruído', 3
    UNION ALL SELECT 'ILUMINACAO', 'nao_acende', 'Uma ou mais luminárias não acendem', 1
    UNION ALL SELECT 'ILUMINACAO', 'pisca_baixa', 'A luz pisca ou está fraca', 2
    UNION ALL SELECT 'ILUMINACAO', 'circuito_comando', 'Há falha no circuito, comando ou disjuntor', 3
    UNION ALL SELECT 'MANUTENCAO_PREVENTIVA', 'limpeza_inspecao', 'Limpeza e inspeção de rotina', 1
    UNION ALL SELECT 'MANUTENCAO_PREVENTIVA', 'revisao_equipamentos', 'Revisão de equipamentos existentes', 2
    UNION ALL SELECT 'MANUTENCAO_PREVENTIVA', 'plano_periodico', 'Planejamento de um plano periódico', 3
    UNION ALL SELECT 'ELETROMECANICA', 'motor_bomba', 'Motor ou bomba não liga', 1
    UNION ALL SELECT 'ELETROMECANICA', 'vibracao_aquecimento', 'Apresenta vibração ou aquecimento', 2
    UNION ALL SELECT 'ELETROMECANICA', 'painel_automacao', 'Há falha no painel ou na automação', 3
) AS dados ON dados.servico = ts.codigo
WHERE p.codigo = 'necessidade_principal'
  AND q.titulo LIKE 'Triagem MVP - %'
  AND q.versao = 1
  AND NOT EXISTS (
      SELECT 1 FROM opcao_pergunta AS existente
      WHERE existente.id_pergunta = p.id_pergunta
        AND existente.codigo = dados.codigo_opcao
  );

COMMIT;
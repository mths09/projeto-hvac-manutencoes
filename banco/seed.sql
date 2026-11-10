--
-- Despejando dados para a tabela `status_solicitacao`
--

INSERT INTO `status_solicitacao` (`id_status`, `codigo`, `nome`, `ordem`, `terminal`, `ativo`) VALUES
(1, 'SOLICITADO', 'Solicitado', 1, 0, 1),
(2, 'AGENDADO', 'Agendado', 2, 0, 1),
(3, 'EM_ANDAMENTO', 'Em andamento', 3, 0, 1),
(4, 'CONCLUIDO', 'Concluído', 4, 1, 1),
(5, 'CANCELADO', 'Cancelado', 5, 1, 1);

-- --------------------------------------------------------

--
-- Despejando dados para a tabela `tipo_servico`
--

INSERT INTO `tipo_servico` (`id_tipo_servico`, `codigo`, `nome`, `descricao`, `icone`, `ordem`, `ativo`) VALUES
(1, 'AR_CONDICIONADO', 'Ar Condicionado', 'Instalação, manutenção e higienização de splits, cassetes e VRF', 'ar-condicionado.svg', 1, 1),
(2, 'REFRIGERACAO', 'Frigorífico / Geladeira', 'Conserto e manutenção de câmaras frias e refrigeradores comerciais', 'frigorifico.svg', 2, 1),
(3, 'ILUMINACAO', 'Iluminação', 'Instalação de LED, luminárias industriais e sistemas de emergência', 'iluminacao.svg', 3, 1),
(4, 'MANUTENCAO_PREVENTIVA', 'Manutenção Preventiva', 'Planos periódicos para equipamentos industriais e comerciais', 'manutencao.svg', 4, 1),
(5, 'ELETROMECANICA', 'Eletromecânica', 'Motores, bombas, quadros elétricos e automação industrial', 'raio.svg', 5, 1);

-- --------------------------------------------------------
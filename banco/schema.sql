--
-- Estrutura para tabela `agendamento`
--


CREATE TABLE `agendamento` (
  `id_agendamento` bigint(20) NOT NULL,
  `id_solicitacao` bigint(20) NOT NULL,
  `id_disponibilidade` bigint(20) NOT NULL,
  `id_atribuicao` bigint(20) DEFAULT NULL,
  `id_anterior` bigint(20) DEFAULT NULL,
  `status` varchar(20) NOT NULL,
  `inicio_real` datetime DEFAULT NULL,
  `fim_real` datetime DEFAULT NULL,
  `motivo_cancelamento` text DEFAULT NULL,
  `observacao` text DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `anexo_solicitacao`
--


CREATE TABLE `anexo_solicitacao` (
  `id_anexo` bigint(20) NOT NULL,
  `id_solicitacao` bigint(20) NOT NULL,
  `id_arquivo` bigint(20) NOT NULL,
  `id_resposta` bigint(20) DEFAULT NULL,
  `finalidade` varchar(20) NOT NULL,
  `legenda` varchar(255) DEFAULT NULL,
  `ordem` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `arquivo`
--


CREATE TABLE `arquivo` (
  `id_arquivo` bigint(20) NOT NULL,
  `nome_original` varchar(255) NOT NULL,
  `chave_armazenamento` varchar(500) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `tamanho_bytes` bigint(20) NOT NULL,
  `sha256` char(64) DEFAULT NULL,
  `id_autor` bigint(20) DEFAULT NULL,
  `enviado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `atribuicao_tecnica`
--


CREATE TABLE `atribuicao_tecnica` (
  `id_atribuicao` bigint(20) NOT NULL,
  `id_solicitacao` bigint(20) NOT NULL,
  `id_tecnico` bigint(20) NOT NULL,
  `id_responsavel` bigint(20) DEFAULT NULL,
  `inicio_em` datetime NOT NULL DEFAULT current_timestamp(),
  `fim_em` datetime DEFAULT NULL,
  `motivo` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `avaliacao_atendimento`
--


CREATE TABLE `avaliacao_atendimento` (
  `id_avaliacao` bigint(20) NOT NULL,
  `id_agendamento` bigint(20) NOT NULL,
  `id_autor` bigint(20) NOT NULL,
  `nota` int(11) NOT NULL,
  `comentario` text DEFAULT NULL,
  `avaliada_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `categoria_portfolio`
--


CREATE TABLE `categoria_portfolio` (
  `id_categoria` bigint(20) NOT NULL,
  `codigo` varchar(60) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `cor` varchar(30) DEFAULT NULL,
  `ordem` int(11) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `condicao_pergunta`
--


CREATE TABLE `condicao_pergunta` (
  `id_condicao` bigint(20) NOT NULL,
  `id_pergunta_destino` bigint(20) NOT NULL,
  `id_pergunta_origem` bigint(20) NOT NULL,
  `grupo` int(11) NOT NULL,
  `operador` varchar(20) NOT NULL,
  `id_opcao_esperada` bigint(20) DEFAULT NULL,
  `valor_comparacao` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `disponibilidade`
--


CREATE TABLE `disponibilidade` (
  `id_disponibilidade` bigint(20) NOT NULL,
  `inicio_previsto` datetime NOT NULL,
  `fim_previsto` datetime NOT NULL,
  `situacao` varchar(15) NOT NULL,
  `capacidade` int(11) NOT NULL DEFAULT 1,
  `motivo_bloqueio` text DEFAULT NULL,
  `id_editor` bigint(20) DEFAULT NULL,
  `atualizada_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `documento`
--


CREATE TABLE `documento` (
  `id_documento` bigint(20) NOT NULL,
  `id_solicitacao` bigint(20) NOT NULL,
  `id_arquivo` bigint(20) NOT NULL,
  `tipo` varchar(25) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `numero` varchar(60) DEFAULT NULL,
  `emitido_em` datetime NOT NULL DEFAULT current_timestamp(),
  `garantia_inicio` date DEFAULT NULL,
  `garantia_fim` date DEFAULT NULL,
  `disponivel_cliente` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `endereco_atendimento`
--


CREATE TABLE `endereco_atendimento` (
  `id_solicitacao` bigint(20) NOT NULL,
  `endereco_informado` text NOT NULL,
  `cep` varchar(8) DEFAULT NULL,
  `logradouro` varchar(180) DEFAULT NULL,
  `numero` varchar(20) DEFAULT NULL,
  `complemento` varchar(120) DEFAULT NULL,
  `bairro` varchar(100) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `uf` char(2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `endereco_usuario`
--


CREATE TABLE `endereco_usuario` (
  `id_endereco_usuario` bigint(20) NOT NULL,
  `id_usuario` bigint(20) NOT NULL UNIQUE,
  `rotulo` varchar(60) DEFAULT NULL,
  `endereco_informado` text NOT NULL,
  `cep` varchar(8) DEFAULT NULL,
  `logradouro` varchar(180) DEFAULT NULL,
  `numero` varchar(20) DEFAULT NULL,
  `complemento` varchar(120) DEFAULT NULL,
  `bairro` varchar(100) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `uf` char(2) DEFAULT NULL,
  `principal` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `historico_status`
--


CREATE TABLE `historico_status` (
  `id_historico` bigint(20) NOT NULL,
  `id_solicitacao` bigint(20) NOT NULL,
  `id_status_anterior` bigint(20) DEFAULT NULL,
  `id_status_novo` bigint(20) NOT NULL,
  `id_responsavel` bigint(20) DEFAULT NULL,
  `alterado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `observacao` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `item_estoque`
--


CREATE TABLE `item_estoque` (
  `id_item_estoque` bigint(20) NOT NULL,
  `codigo` varchar(60) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `descricao` text DEFAULT NULL,
  `unidade_medida` varchar(15) NOT NULL,
  `estoque_minimo` decimal(12,3) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `item_portfolio`
--


CREATE TABLE `item_portfolio` (
  `id_item_portfolio` bigint(20) NOT NULL,
  `id_categoria` bigint(20) NOT NULL,
  `id_solicitacao` bigint(20) DEFAULT NULL,
  `id_arquivo_capa` bigint(20) DEFAULT NULL,
  `titulo` varchar(150) NOT NULL,
  `descricao` text DEFAULT NULL,
  `localidade_publica` varchar(150) DEFAULT NULL,
  `ordem` int(11) NOT NULL,
  `publicado` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `publicado_em` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `item_solicitacao`
--


CREATE TABLE `item_solicitacao` (
  `id_item_solicitacao` bigint(20) NOT NULL,
  `id_solicitacao` bigint(20) NOT NULL,
  `id_item_estoque` bigint(20) NOT NULL,
  `quantidade_utilizada` decimal(12,3) NOT NULL,
  `custo_unitario_aplicado` decimal(12,2) DEFAULT NULL,
  `observacao` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `movimentacao_estoque`
--


CREATE TABLE `movimentacao_estoque` (
  `id_movimentacao` bigint(20) NOT NULL,
  `id_item_estoque` bigint(20) NOT NULL,
  `id_item_solicitacao` bigint(20) DEFAULT NULL,
  `id_responsavel` bigint(20) DEFAULT NULL,
  `tipo` varchar(20) NOT NULL,
  `quantidade_delta` decimal(12,3) NOT NULL,
  `custo_unitario` decimal(12,2) DEFAULT NULL,
  `id_estorno_de` bigint(20) DEFAULT NULL,
  `movimentada_em` datetime NOT NULL DEFAULT current_timestamp(),
  `observacao` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `notificacao`
--


CREATE TABLE `notificacao` (
  `id_notificacao` bigint(20) NOT NULL,
  `id_destinatario` bigint(20) NOT NULL,
  `id_solicitacao` bigint(20) DEFAULT NULL,
  `id_agendamento` bigint(20) DEFAULT NULL,
  `id_documento` bigint(20) DEFAULT NULL,
  `tipo_evento` varchar(40) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `mensagem` text NOT NULL,
  `canal` varchar(15) NOT NULL,
  `status_envio` varchar(15) NOT NULL,
  `criada_em` datetime NOT NULL DEFAULT current_timestamp(),
  `enviada_em` datetime DEFAULT NULL,
  `lida_em` datetime DEFAULT NULL,
  `erro_envio` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `opcao_pergunta`
--


CREATE TABLE `opcao_pergunta` (
  `id_opcao` bigint(20) NOT NULL,
  `id_pergunta` bigint(20) NOT NULL,
  `codigo` varchar(60) NOT NULL,
  `rotulo` varchar(200) NOT NULL,
  `ordem` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `orcamento`
--


CREATE TABLE `orcamento` (
  `id_orcamento` bigint(20) NOT NULL,
  `id_solicitacao` bigint(20) NOT NULL,
  `versao` int(11) NOT NULL,
  `status` varchar(15) NOT NULL,
  `valor_servico` decimal(12,2) DEFAULT NULL,
  `valor_deslocamento` decimal(12,2) DEFAULT NULL,
  `valor_materiais` decimal(12,2) DEFAULT NULL,
  `desconto` decimal(12,2) NOT NULL DEFAULT 0.00,
  `validade` date DEFAULT NULL,
  `observacao` text DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `emitido_em` datetime DEFAULT NULL,
  `decidido_em` datetime DEFAULT NULL,
  `id_decisor` bigint(20) DEFAULT NULL,
  `evidencia_decisao` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pergunta_triagem`
--


CREATE TABLE `pergunta_triagem` (
  `id_pergunta` bigint(20) NOT NULL,
  `id_questionario` bigint(20) NOT NULL,
  `codigo` varchar(60) NOT NULL,
  `enunciado` text NOT NULL,
  `ajuda` text DEFAULT NULL,
  `tipo_resposta` varchar(25) NOT NULL,
  `obrigatoria` tinyint(1) NOT NULL DEFAULT 0,
  `ordem` int(11) NOT NULL,
  `minimo` decimal(14,2) DEFAULT NULL,
  `maximo` decimal(14,2) DEFAULT NULL,
  `limite_caracteres` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `questionario`
--


CREATE TABLE `questionario` (
  `id_questionario` bigint(20) NOT NULL,
  `id_tipo_servico` bigint(20) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `versao` int(11) NOT NULL,
  `status` varchar(15) NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `publicado_em` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `resposta_opcao`
--


CREATE TABLE `resposta_opcao` (
  `id_resposta` bigint(20) NOT NULL,
  `id_opcao` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `resposta_triagem`
--


CREATE TABLE `resposta_triagem` (
  `id_resposta` bigint(20) NOT NULL,
  `id_triagem` bigint(20) NOT NULL,
  `id_pergunta` bigint(20) NOT NULL,
  `valor_texto` text DEFAULT NULL,
  `valor_numero` decimal(14,2) DEFAULT NULL,
  `valor_data` date DEFAULT NULL,
  `valor_booleano` tinyint(1) DEFAULT NULL,
  `respondida_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `solicitacao`
--


CREATE TABLE `solicitacao` (
  `id_solicitacao` bigint(20) NOT NULL,
  `protocolo` varchar(30) NOT NULL,
  `id_cliente` bigint(20) DEFAULT NULL,
  `id_criador` bigint(20) DEFAULT NULL,
  `id_tipo_servico` bigint(20) NOT NULL,
  `id_status_atual` bigint(20) NOT NULL,
  `nome_contato` varchar(150) NOT NULL,
  `telefone_contato` varchar(25) DEFAULT NULL,
  `email_contato` varchar(254) DEFAULT NULL,
  `descricao_problema` text DEFAULT NULL,
  `urgente` tinyint(1) NOT NULL DEFAULT 0,
  `origem` varchar(20) NOT NULL,
  `criada_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizada_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `arquivada_em` datetime DEFAULT NULL,
  `id_arquivador` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `status_solicitacao`
--


CREATE TABLE `status_solicitacao` (
  `id_status` bigint(20) NOT NULL,
  `codigo` varchar(30) NOT NULL,
  `nome` varchar(60) NOT NULL,
  `ordem` int(11) NOT NULL,
  `terminal` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Estrutura para tabela `tecnico`
--


CREATE TABLE `tecnico` (
  `id_tecnico` bigint(20) NOT NULL,
  `id_usuario` bigint(20) DEFAULT NULL,
  `nome` varchar(150) NOT NULL,
  `telefone` varchar(25) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `tecnico_especialidade`
--


CREATE TABLE `tecnico_especialidade` (
  `id_tecnico` bigint(20) NOT NULL,
  `id_tipo_servico` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `tipo_servico`
--


CREATE TABLE `tipo_servico` (
  `id_tipo_servico` bigint(20) NOT NULL,
  `codigo` varchar(60) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `icone` varchar(100) DEFAULT NULL,
  `ordem` int(11) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Estrutura para tabela `triagem`
--


CREATE TABLE `triagem` (
  `id_triagem` bigint(20) NOT NULL,
  `id_solicitacao` bigint(20) NOT NULL,
  `id_questionario` bigint(20) NOT NULL,
  `id_respondente` bigint(20) DEFAULT NULL,
  `status` varchar(15) NOT NULL,
  `iniciada_em` datetime NOT NULL DEFAULT current_timestamp(),
  `enviada_em` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuario`
--


CREATE TABLE `usuario` (
  `id_usuario` bigint(20) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `email` varchar(254) NOT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `telefone` varchar(25) NOT NULL,
  `data_nascimento` date DEFAULT NULL,
  `perfil` varchar(20) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `agendamento`
--
ALTER TABLE `agendamento`
  ADD PRIMARY KEY (`id_agendamento`),
  ADD UNIQUE KEY `un_agendamento_anterior` (`id_anterior`),
  ADD KEY `fk_agendamento_solicitacao` (`id_solicitacao`),
  ADD KEY `fk_agendamento_disponibilidade` (`id_disponibilidade`),
  ADD KEY `fk_agendamento_atribuicao` (`id_atribuicao`);

--
-- Índices de tabela `anexo_solicitacao`
--
ALTER TABLE `anexo_solicitacao`
  ADD PRIMARY KEY (`id_anexo`),
  ADD UNIQUE KEY `un_anexo_solicitacao_arquivo` (`id_solicitacao`,`id_arquivo`),
  ADD KEY `fk_anexo_solicitacao_arquivo` (`id_arquivo`),
  ADD KEY `fk_anexo_solicitacao_resposta` (`id_resposta`);

--
-- Índices de tabela `arquivo`
--
ALTER TABLE `arquivo`
  ADD PRIMARY KEY (`id_arquivo`),
  ADD UNIQUE KEY `un_arquivo_chave_armazenamento` (`chave_armazenamento`),
  ADD KEY `fk_arquivo_autor` (`id_autor`);

--
-- Índices de tabela `atribuicao_tecnica`
--
ALTER TABLE `atribuicao_tecnica`
  ADD PRIMARY KEY (`id_atribuicao`),
  ADD KEY `fk_atribuicao_solicitacao` (`id_solicitacao`),
  ADD KEY `fk_atribuicao_tecnico` (`id_tecnico`),
  ADD KEY `fk_atribuicao_responsavel` (`id_responsavel`);

--
-- Índices de tabela `avaliacao_atendimento`
--
ALTER TABLE `avaliacao_atendimento`
  ADD PRIMARY KEY (`id_avaliacao`),
  ADD UNIQUE KEY `un_avaliacao_agendamento` (`id_agendamento`),
  ADD KEY `fk_avaliacao_autor` (`id_autor`);

--
-- Índices de tabela `categoria_portfolio`
--
ALTER TABLE `categoria_portfolio`
  ADD PRIMARY KEY (`id_categoria`),
  ADD UNIQUE KEY `un_categoria_portfolio_codigo` (`codigo`);

--
-- Índices de tabela `condicao_pergunta`
--
ALTER TABLE `condicao_pergunta`
  ADD PRIMARY KEY (`id_condicao`),
  ADD KEY `fk_condicao_pergunta_destino` (`id_pergunta_destino`),
  ADD KEY `fk_condicao_pergunta_origem` (`id_pergunta_origem`),
  ADD KEY `fk_condicao_opcao_esperada` (`id_opcao_esperada`);

--
-- Índices de tabela `disponibilidade`
--
ALTER TABLE `disponibilidade`
  ADD PRIMARY KEY (`id_disponibilidade`),
  ADD KEY `idx_disponibilidade_inicio` (`inicio_previsto`),
  ADD KEY `fk_disponibilidade_editor` (`id_editor`);

--
-- Índices de tabela `documento`
--
ALTER TABLE `documento`
  ADD PRIMARY KEY (`id_documento`),
  ADD KEY `fk_documento_solicitacao` (`id_solicitacao`),
  ADD KEY `fk_documento_arquivo` (`id_arquivo`);

--
-- Índices de tabela `endereco_atendimento`
--
ALTER TABLE `endereco_atendimento`
  ADD PRIMARY KEY (`id_solicitacao`);

--
-- Índices de tabela `endereco_usuario`
--
ALTER TABLE `endereco_usuario`
  ADD PRIMARY KEY (`id_endereco_usuario`),
  ADD KEY `fk_endereco_usuario_usuario` (`id_usuario`);

--
-- Índices de tabela `historico_status`
--
ALTER TABLE `historico_status`
  ADD PRIMARY KEY (`id_historico`),
  ADD KEY `fk_historico_solicitacao` (`id_solicitacao`),
  ADD KEY `fk_historico_status_anterior` (`id_status_anterior`),
  ADD KEY `fk_historico_status_novo` (`id_status_novo`),
  ADD KEY `fk_historico_responsavel` (`id_responsavel`);

--
-- Índices de tabela `item_estoque`
--
ALTER TABLE `item_estoque`
  ADD PRIMARY KEY (`id_item_estoque`),
  ADD UNIQUE KEY `un_item_estoque_codigo` (`codigo`);

--
-- Índices de tabela `item_portfolio`
--
ALTER TABLE `item_portfolio`
  ADD PRIMARY KEY (`id_item_portfolio`),
  ADD KEY `fk_item_portfolio_categoria` (`id_categoria`),
  ADD KEY `fk_item_portfolio_solicitacao` (`id_solicitacao`),
  ADD KEY `fk_item_portfolio_capa` (`id_arquivo_capa`);

--
-- Índices de tabela `item_solicitacao`
--
ALTER TABLE `item_solicitacao`
  ADD PRIMARY KEY (`id_item_solicitacao`),
  ADD UNIQUE KEY `un_item_solicitacao_item` (`id_solicitacao`,`id_item_estoque`),
  ADD KEY `fk_item_solicitacao_estoque` (`id_item_estoque`);

--
-- Índices de tabela `movimentacao_estoque`
--
ALTER TABLE `movimentacao_estoque`
  ADD PRIMARY KEY (`id_movimentacao`),
  ADD UNIQUE KEY `un_movimentacao_estorno` (`id_estorno_de`),
  ADD KEY `fk_movimentacao_item_estoque` (`id_item_estoque`),
  ADD KEY `fk_movimentacao_item_solicitacao` (`id_item_solicitacao`),
  ADD KEY `fk_movimentacao_responsavel` (`id_responsavel`);

--
-- Índices de tabela `notificacao`
--
ALTER TABLE `notificacao`
  ADD PRIMARY KEY (`id_notificacao`),
  ADD KEY `fk_notificacao_destinatario` (`id_destinatario`),
  ADD KEY `fk_notificacao_solicitacao` (`id_solicitacao`),
  ADD KEY `fk_notificacao_agendamento` (`id_agendamento`),
  ADD KEY `fk_notificacao_documento` (`id_documento`);

--
-- Índices de tabela `opcao_pergunta`
--
ALTER TABLE `opcao_pergunta`
  ADD PRIMARY KEY (`id_opcao`),
  ADD UNIQUE KEY `un_opcao_pergunta_codigo` (`id_pergunta`,`codigo`);

--
-- Índices de tabela `orcamento`
--
ALTER TABLE `orcamento`
  ADD PRIMARY KEY (`id_orcamento`),
  ADD UNIQUE KEY `un_orcamento_solicitacao_versao` (`id_solicitacao`,`versao`),
  ADD KEY `fk_orcamento_decisor` (`id_decisor`);

--
-- Índices de tabela `pergunta_triagem`
--
ALTER TABLE `pergunta_triagem`
  ADD PRIMARY KEY (`id_pergunta`),
  ADD UNIQUE KEY `un_pergunta_questionario_codigo` (`id_questionario`,`codigo`);

--
-- Índices de tabela `questionario`
--
ALTER TABLE `questionario`
  ADD PRIMARY KEY (`id_questionario`),
  ADD UNIQUE KEY `un_questionario_tipo_versao` (`id_tipo_servico`,`versao`);

--
-- Índices de tabela `resposta_opcao`
--
ALTER TABLE `resposta_opcao`
  ADD PRIMARY KEY (`id_resposta`,`id_opcao`),
  ADD KEY `fk_resposta_opcao_opcao` (`id_opcao`);

--
-- Índices de tabela `resposta_triagem`
--
ALTER TABLE `resposta_triagem`
  ADD PRIMARY KEY (`id_resposta`),
  ADD UNIQUE KEY `un_resposta_triagem_pergunta` (`id_triagem`,`id_pergunta`),
  ADD KEY `fk_resposta_triagem_pergunta` (`id_pergunta`);

--
-- Índices de tabela `solicitacao`
--
ALTER TABLE `solicitacao`
  ADD PRIMARY KEY (`id_solicitacao`),
  ADD UNIQUE KEY `un_solicitacao_protocolo` (`protocolo`),
  ADD KEY `fk_solicitacao_cliente` (`id_cliente`),
  ADD KEY `fk_solicitacao_criador` (`id_criador`),
  ADD KEY `fk_solicitacao_tipo_servico` (`id_tipo_servico`),
  ADD KEY `fk_solicitacao_status` (`id_status_atual`),
  ADD KEY `fk_solicitacao_arquivador` (`id_arquivador`);

--
-- Índices de tabela `status_solicitacao`
--
ALTER TABLE `status_solicitacao`
  ADD PRIMARY KEY (`id_status`),
  ADD UNIQUE KEY `un_status_solicitacao_codigo` (`codigo`);

--
-- Índices de tabela `tecnico`
--
ALTER TABLE `tecnico`
  ADD PRIMARY KEY (`id_tecnico`),
  ADD UNIQUE KEY `un_tecnico_usuario` (`id_usuario`);

--
-- Índices de tabela `tecnico_especialidade`
--
ALTER TABLE `tecnico_especialidade`
  ADD PRIMARY KEY (`id_tecnico`,`id_tipo_servico`),
  ADD KEY `fk_tecnico_especialidade_tipo_servico` (`id_tipo_servico`);

--
-- Índices de tabela `tipo_servico`
--
ALTER TABLE `tipo_servico`
  ADD PRIMARY KEY (`id_tipo_servico`),
  ADD UNIQUE KEY `un_tipo_servico_codigo` (`codigo`);

--
-- Índices de tabela `triagem`
--
ALTER TABLE `triagem`
  ADD PRIMARY KEY (`id_triagem`),
  ADD KEY `fk_triagem_solicitacao` (`id_solicitacao`),
  ADD KEY `fk_triagem_questionario` (`id_questionario`),
  ADD KEY `fk_triagem_respondente` (`id_respondente`);

--
-- Índices de tabela `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `un_usuario_email` (`email`);
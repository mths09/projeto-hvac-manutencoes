(() => {
  "use strict";

  const API = "../../../backend/dashboard_cliente.php";
  const elemento = (seletor) => document.querySelector(seletor);
  const statusDashboard = elemento("#status-dashboard");
  const botaoRecarregar = elemento("#recarregar-dashboard");
  const botaoSair = elemento("#botao-sair");
  const botaoNotificacoes = elemento("#btn-notificacoes");
  const painelNotificacoes = elemento("#painel-notificacoes");
  const listaNotificacoes = elemento("#lista-notificacoes");
  const estadoNotificacoesVazio = elemento("#notificacoes-vazias");
  const avisoNotificacoes = elemento("#status-notificacoes");
  const botaoMarcarLidas = elemento("#marcar-lidas");
  const botaoLimparLidas = elemento("#limpar-lidas");
  const listaAndamento = elemento("#servicos-andamento");
  const listaPedidos = elemento(".lista-servicos");
  const listaDocumentos = elemento(".documentos");
  const dialogoDetalhes = elemento("#dialog-detalhes");
  let dadosAtuais = null;
  let carregando = false;
  let csrf = "";
  let ultimaAtualizacao = 0;

  function criar(tag, classe, texto) {
    const no = document.createElement(tag);
    if (classe) no.className = classe;
    if (texto !== undefined && texto !== null) no.textContent = String(texto);
    return no;
  }

  function formatarData(valor) {
    const data = String(valor || "").slice(0, 10);
    return /^\d{4}-\d{2}-\d{2}$/.test(data)
      ? data.split("-").reverse().join("/")
      : "—";
  }

  function formatarDataHora(valor) {
    if (!valor) return "Ainda não agendado";
    const texto = String(valor).replace("T", " ");
    return `${formatarData(texto)} às ${texto.slice(11, 16)}`;
  }

  function iniciais(nome) {
    const partes = String(nome || "")
      .trim()
      .split(/\s+/)
      .filter(Boolean);
    if (!partes.length) return "?";
    return `${partes[0][0]}${partes.length > 1 ? partes.at(-1)[0] : ""}`.toUpperCase();
  }

  function atualizarPerfil(usuario) {
    const nome = String(usuario.nome || "Cliente").trim() || "Cliente";
    document.querySelectorAll(".nome-usuario").forEach((no) => {
      no.textContent = nome;
    });
    document.querySelector(".usuario .email-usuario").textContent =
      usuario.email || "";
    elemento(".coluna-lateral .email-usuario").textContent =
      `📧 ${usuario.email || "E-mail não informado"}`;
    elemento(".telefone-usuario").textContent =
      `📱 ${usuario.telefone || "Telefone não informado"}`;
    elemento(".endereco-usuario").textContent =
      `📍 ${usuario.endereco || "Endereço não cadastrado"}`;
    elemento("#avatar-usuario").textContent = iniciais(nome);
    elemento("#avatar-meu-perfil").textContent = iniciais(nome);
    elemento("#titulo-dashboard").textContent =
      `Bem-vindo, ${nome.split(/\s+/)[0]}! 👋`;
    elemento(".tempo-cliente").textContent =
      `Cliente desde: ${formatarData(usuario.criado_em)}`;
  }

  function classeStatus(status) {
    if (status === "EM_ANDAMENTO") return "etiqueta-laranja";
    if (status === "CONCLUIDO") return "etiqueta-verde";
    if (status === "CANCELADO") return "etiqueta-cancelado";
    return "etiqueta-ciano";
  }

  function criarEtapas(status) {
    const etapas = ["Solicitado", "Agendado", "Em andamento", "Concluído"];
    const indiceAtual = {
      SOLICITADO: 0,
      AGENDADO: 1,
      EM_ANDAMENTO: 2,
      CONCLUIDO: 3,
    }[status];
    if (indiceAtual === undefined) return null;
    const lista = criar("ol", "progresso-servico");
    lista.setAttribute("aria-label", "Etapas do pedido");
    etapas.forEach((nome, indice) => {
      const etapa = criar(
        "li",
        indice <= indiceAtual ? "etapa etapa-preenchida" : "etapa",
        nome,
      );
      if (indice === indiceAtual) {
        etapa.setAttribute("aria-current", "step");
      }
      lista.append(etapa);
    });
    return lista;
  }

  function criarServico(servico, destaque = false) {
    const cartao = criar(
      "article",
      destaque ? "cartao servico-ativo" : "servico-historico",
    );
    cartao.dataset.servicoId = servico.id;
    const cabecalho = criar("div", "cabecalho-servico");
    cabecalho.append(
      criar("h3", destaque ? "titulo-cartao" : "titulo-servico", servico.tipo),
      criar("span", classeStatus(servico.status), servico.status_nome),
    );
    if (servico.urgente)
      cabecalho.append(criar("span", "etiqueta-cancelado", "Urgente"));
    cartao.append(cabecalho);
    cartao.append(
      criar(
        "p",
        "servico-cartao",
        `${servico.protocolo} · Pedido em ${formatarData(servico.criado_em)}`,
      ),
    );
    if (servico.descricao)
      cartao.append(criar("p", "descricao-cartao", servico.descricao));

    const informacoes = criar("div", "informacoes-servico");
    informacoes.append(
      criar(
        "p",
        "descricao-servico",
        `Agendamento: ${formatarDataHora(servico.inicio_previsto)}`,
      ),
      criar(
        "p",
        "descricao-servico",
        `Endereço: ${servico.endereco || "Não informado"}`,
      ),
    );
    cartao.append(informacoes);

    if (servico.tecnico) {
      const tecnico = criar("div", "tecnico");
      tecnico.append(
        criar("span", "foto-tecnico", iniciais(servico.tecnico)),
        criar("span", "nome-tecnico", servico.tecnico),
        criar("p", "descricao-tecnico", "Técnico responsável"),
      );
      const telefoneInformado = String(servico.telefone_tecnico || "").replace(
        /\D/g,
        "",
      );
      const telefone =
        telefoneInformado && !telefoneInformado.startsWith("55")
          ? `55${telefoneInformado}`
          : telefoneInformado;
      if (telefone) {
        const link = criar("a", "", "WhatsApp");
        link.href = `https://wa.me/${telefone}`;
        link.target = "_blank";
        link.rel = "noopener noreferrer";
        tecnico.append(link);
      }
      cartao.append(tecnico);
    }

    const progresso = criarEtapas(servico.status);
    if (progresso) cartao.append(progresso);
    const botaoDetalhes = criar(
      "button",
      "botao-detalhes",
      "Ver detalhes completos →",
    );
    botaoDetalhes.type = "button";
    botaoDetalhes.dataset.detalhesId = servico.id;
    cartao.append(botaoDetalhes);
    return cartao;
  }

  function renderizarServicos(servicos) {
    listaAndamento.replaceChildren();
    listaPedidos.replaceChildren();
    const andamento = servicos.filter((item) => item.status === "EM_ANDAMENTO");
    const demais = servicos.filter((item) => item.status !== "EM_ANDAMENTO");
    andamento.forEach((item) =>
      listaAndamento.append(criarServico(item, true)),
    );
    demais.forEach((item) => {
      const linha = criar("li");
      linha.append(criarServico(item));
      listaPedidos.append(linha);
    });
    elemento("#andamento-vazio").hidden = andamento.length > 0;
    listaPedidos.hidden = demais.length === 0;
    const pedidosVazios = elemento("#historico-vazio");
    pedidosVazios.hidden = demais.length > 0;
    pedidosVazios.querySelector("p").textContent =
      servicos.length === 0
        ? "Você ainda não possui pedidos."
        : "Não há outros pedidos além dos serviços em andamento.";
  }

  function renderizarDocumentos(documentos) {
    listaDocumentos.replaceChildren();
    documentos.forEach((documento) => {
      const linha = criar("article", "documento-cliente");
      linha.append(
        criar("strong", "", documento.titulo),
        criar(
          "span",
          "",
          `${documento.protocolo} · ${formatarData(documento.emitido_em)}`,
        ),
        criar("small", "", documento.tipo),
      );
      listaDocumentos.append(linha);
    });
    listaDocumentos.hidden = documentos.length === 0;
    elemento("#documentos-vazios").hidden = documentos.length > 0;
  }

  function renderizarNotificacoes(notificacoes) {
    listaNotificacoes.replaceChildren();
    notificacoes.forEach((notificacao) => {
      const linha = criar("li", "item-notificacao");
      linha.classList.toggle("nao-lida", !notificacao.lida);
      linha.append(
        criar("h3", "", notificacao.titulo),
        criar("p", "", notificacao.mensagem),
        criar(
          "span",
          "estado-notificacao",
          notificacao.lida ? "Lida" : "Não lida",
        ),
      );
      listaNotificacoes.append(linha);
    });
    const naoLidas = notificacoes.filter((item) => !item.lida).length;
    const lidas = notificacoes.length - naoLidas;
    elemento(".contador-notificacoes").textContent = String(naoLidas);
    elemento(".contador-notificacoes").hidden = naoLidas === 0;
    listaNotificacoes.hidden = notificacoes.length === 0;
    estadoNotificacoesVazio.hidden = notificacoes.length > 0;
    botaoMarcarLidas.hidden = naoLidas === 0;
    botaoLimparLidas.hidden = lidas === 0;
    botaoNotificacoes.setAttribute(
      "aria-label",
      `Notificações: ${naoLidas} não lidas`,
    );
    avisoNotificacoes.textContent = "";
  }

  function renderizar(dados) {
    dadosAtuais = dados;
    csrf = dados.csrf;
    atualizarPerfil(dados.usuario);
    for (const chave of ["total", "andamento", "concluidos", "agendados"]) {
      elemento(`.indicador-${chave} strong`).textContent = String(
        dados.resumo[chave],
      );
    }
    renderizarServicos(dados.servicos);
    renderizarDocumentos(dados.documentos);
    renderizarNotificacoes(dados.notificacoes);
    statusDashboard.textContent =
      dados.servicos.length === 0
        ? "Seu painel está pronto. Quando você fizer um pedido, ele aparecerá aqui."
        : "Seus pedidos e atendimentos estão atualizados.";
    ultimaAtualizacao = Date.now();
  }

  async function carregarDashboard(mostrarCarregamento = false) {
    if (carregando) return;
    carregando = true;
    botaoRecarregar.hidden = true;
    if (mostrarCarregamento)
      statusDashboard.textContent = "Carregando seus pedidos…";
    try {
      const resposta = await fetch(API, {
        credentials: "same-origin",
        cache: "no-store",
        headers: { Accept: "application/json" },
      });
      const dados = await resposta.json();
      if (resposta.status === 401) {
        window.location.replace("./login.html");
        return;
      }
      if (!resposta.ok || dados?.sucesso !== true) {
        throw new Error(
          dados?.mensagem || "Não foi possível carregar o painel.",
        );
      }
      const contadores = ["total", "andamento", "concluidos", "agendados"];
      if (
        !dados.usuario ||
        !dados.resumo ||
        !contadores.every(
          (chave) =>
            Number.isSafeInteger(dados.resumo[chave]) &&
            dados.resumo[chave] >= 0,
        ) ||
        ![dados.servicos, dados.documentos, dados.notificacoes].every(
          Array.isArray,
        ) ||
        typeof dados.csrf !== "string"
      ) {
        throw new Error("O servidor retornou dados incompletos.");
      }
      renderizar(dados);
    } catch (erro) {
      statusDashboard.textContent =
        "Não foi possível carregar seus dados. Verifique o servidor e tente novamente.";
      botaoRecarregar.hidden = false;
      console.error(erro);
    } finally {
      carregando = false;
    }
  }

  function abrirDetalhes(id) {
    const servico = dadosAtuais?.servicos.find((item) => item.id === id);
    if (!servico) return;
    const conteudo = elemento("#conteudo-detalhes");
    conteudo.replaceChildren();
    const campos = [
      ["Protocolo", servico.protocolo],
      ["Serviço", servico.tipo],
      ["Status", servico.status_nome],
      ["Solicitado em", formatarData(servico.criado_em)],
      ["Data e horário", formatarDataHora(servico.inicio_previsto)],
      ["Endereço de atendimento", servico.endereco || "Não informado"],
      ["Técnico", servico.tecnico || "A equipe ainda não atribuiu um técnico."],
      ["Descrição", servico.descricao || "Sem descrição adicional."],
    ];
    for (const [titulo, valor] of campos) {
      const linha = criar("p", "detalhe-linha");
      linha.append(
        criar("strong", "", `${titulo}: `),
        document.createTextNode(String(valor)),
      );
      conteudo.append(linha);
    }
    dialogoDetalhes.showModal();
  }

  async function marcarNotificacoesLidas() {
    if (!csrf) return;
    botaoMarcarLidas.disabled = true;
    avisoNotificacoes.textContent = "Atualizando…";
    try {
      const resposta = await fetch("../../../backend/notificacoes_lidas.php", {
        method: "POST",
        credentials: "same-origin",
        cache: "no-store",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
        },
        body: new URLSearchParams({ csrf }),
      });
      const resultado = await resposta.json();
      if (resposta.status === 401)
        return window.location.replace("./login.html");
      if (!resposta.ok || resultado?.sucesso !== true)
        throw new Error(resultado?.mensagem || "Falha ao atualizar.");
      await carregarDashboard();
      avisoNotificacoes.textContent = "Todas as notificações foram lidas.";
    } catch (erro) {
      avisoNotificacoes.textContent =
        erro.message || "Não foi possível atualizar as notificações.";
    } finally {
      botaoMarcarLidas.disabled = false;
    }
  }

  async function limparNotificacoesLidas() {
    if (!csrf) return;
    botaoLimparLidas.disabled = true;
    avisoNotificacoes.textContent = "Limpando notificações lidas…";
    try {
      const resposta = await fetch("../../../backend/notificacoes_limpar.php", {
        method: "POST",
        credentials: "same-origin",
        cache: "no-store",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
        },
        body: new URLSearchParams({ csrf }),
      });
      const resultado = await resposta.json();
      if (resposta.status === 401)
        return window.location.replace("./login.html");
      if (!resposta.ok || resultado?.sucesso !== true)
        throw new Error(resultado?.mensagem || "Falha ao limpar.");
      await carregarDashboard();
      avisoNotificacoes.textContent =
        resultado.removidas > 0
          ? `${resultado.removidas} notificação(ões) lida(s) removida(s).`
          : "Não havia notificações lidas para limpar.";
    } catch (erro) {
      avisoNotificacoes.textContent =
        erro.message || "Não foi possível limpar as notificações lidas.";
    } finally {
      botaoLimparLidas.disabled = false;
    }
  }

  function fecharNotificacoes(devolverFoco = false) {
    painelNotificacoes.hidden = true;
    botaoNotificacoes.setAttribute("aria-expanded", "false");
    if (devolverFoco) botaoNotificacoes.focus();
  }

  botaoNotificacoes.addEventListener("click", () => {
    painelNotificacoes.hidden = !painelNotificacoes.hidden;
    botaoNotificacoes.setAttribute(
      "aria-expanded",
      String(!painelNotificacoes.hidden),
    );
    if (!painelNotificacoes.hidden) elemento("#fechar-notificacoes").focus();
  });
  elemento("#fechar-notificacoes").addEventListener("click", () =>
    fecharNotificacoes(true),
  );
  document.addEventListener("click", (evento) => {
    if (!elemento(".area-notificacoes").contains(evento.target))
      fecharNotificacoes();
  });
  document.addEventListener("keydown", (evento) => {
    if (evento.key === "Escape" && !painelNotificacoes.hidden)
      fecharNotificacoes(true);
  });
  botaoMarcarLidas.addEventListener("click", marcarNotificacoesLidas);
  botaoLimparLidas.addEventListener("click", limparNotificacoesLidas);
  botaoRecarregar.addEventListener("click", () => carregarDashboard(true));
  listaAndamento.addEventListener("click", (evento) => {
    const botao = evento.target.closest("[data-detalhes-id]");
    if (botao) abrirDetalhes(botao.dataset.detalhesId);
  });
  listaPedidos.addEventListener("click", (evento) => {
    const botao = evento.target.closest("[data-detalhes-id]");
    if (botao) abrirDetalhes(botao.dataset.detalhesId);
  });
  botaoSair.addEventListener("click", async () => {
    botaoSair.disabled = true;
    try {
      await fetch("../../../backend/logout.php", {
        method: "POST",
        credentials: "same-origin",
        cache: "no-store",
      });
    } finally {
      window.location.replace("./login.html");
    }
  });

  carregarDashboard(true);
  document.addEventListener("visibilitychange", () => {
    if (
      document.visibilityState === "visible" &&
      Date.now() - ultimaAtualizacao > 10000
    )
      carregarDashboard();
  });
  window.setInterval(() => {
    if (document.visibilityState === "visible") carregarDashboard();
  }, 30000);
})();

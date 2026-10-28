(() => {
  "use strict";

  const API = "../../../backend/dashboard_cliente.php";

  const elemento = (seletor, raiz = document) => raiz.querySelector(seletor);

  const elementos = (seletor, raiz = document) => [
    ...raiz.querySelectorAll(seletor),
  ];

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
  const listaHistorico = elemento("#historico-lista");
  const listaDocumentos = elemento("#documentos");
  const dialogoDetalhes = elemento("#dialog-detalhes");
  const conteudoDetalhes = elemento("#detalhes-servico");

  const linksMenu = elementos(".menu-sidebar .link-menu[data-view]");

  const views = elementos(".dashboard-view[data-view-content]");

  const breadcrumbView = elemento("#breadcrumb-view");

  const nomesViews = {
    "visao-geral": "Visão geral",
    "meus-pedidos": "Meus pedidos",
    perfil: "Perfil e endereços",
  };

  let dadosAtuais = null;
  let carregando = false;
  let csrf = "";
  let ultimaAtualizacao = 0;

  // =========================================
  // FUNÇÕES AUXILIARES
  // =========================================

  function criar(tag, classe = "", texto) {
    const no = document.createElement(tag);

    if (classe) {
      no.className = classe;
    }

    if (texto !== undefined && texto !== null) {
      no.textContent = String(texto);
    }

    return no;
  }

  function definirTexto(seletor, texto) {
    elementos(seletor).forEach((no) => {
      no.textContent = texto;
    });
  }

  function formatarData(valor) {
    if (!valor) return "—";

    const texto = String(valor).slice(0, 10);

    return /^\d{4}-\d{2}-\d{2}$/.test(texto)
      ? texto.split("-").reverse().join("/")
      : texto;
  }

  function formatarDataHora(valor) {
    if (!valor) return "Ainda não agendado";

    const texto = String(valor).replace("T", " ");
    const data = formatarData(texto);
    const hora = texto.length >= 16 ? texto.slice(11, 16) : "";

    return hora ? `${data} às ${hora}` : data;
  }

  function iniciais(nome) {
    const partes = String(nome || "")
      .trim()
      .split(/\s+/)
      .filter(Boolean);

    if (!partes.length) return "CL";

    return (
      partes[0][0] + (partes.length > 1 ? partes[partes.length - 1][0] : "")
    ).toUpperCase();
  }

  // =========================================
  // SISTEMA DE VIEWS / ABAS
  // =========================================

  function trocarView(viewNome) {
    const viewAtiva = elemento(
      `.dashboard-view[data-view-content="${viewNome}"]`,
    );

    const linkAtivo = elemento(`.link-menu[data-view="${viewNome}"]`);

    if (!viewAtiva || !linkAtivo) {
      console.warn(`View "${viewNome}" não encontrada.`);
      return;
    }

    // Exibe somente a view selecionada.
    views.forEach((view) => {
      const ativa = view === viewAtiva;

      view.hidden = !ativa;
      view.classList.toggle("ativo", ativa);
    });

    // Atualiza o destaque da sidebar.
    linksMenu.forEach((link) => {
      const ativo = link === linkAtivo;

      link.classList.toggle("ativo", ativo);

      if (ativo) {
        link.setAttribute("aria-current", "page");
      } else {
        link.removeAttribute("aria-current");
      }
    });

    // Atualiza o breadcrumb.
    if (breadcrumbView) {
      breadcrumbView.textContent = nomesViews[viewNome] || viewNome;
    }

    // Volta ao topo sem navegar para uma âncora.
    window.scrollTo({
      top: 0,
      left: 0,
      behavior: "auto",
    });
  }

  function iniciarViews() {
    linksMenu.forEach((link) => {
      link.addEventListener("click", (evento) => {
        evento.preventDefault();

        trocarView(link.dataset.view);
      });
    });

    // Permite trocar de view pelo botão "Ver todos".
    elementos("[data-view-target]").forEach((botao) => {
      botao.addEventListener("click", () => {
        trocarView(botao.dataset.viewTarget);
      });
    });

    trocarView("visao-geral");
  }

  // =========================================
  // PERFIL DO CLIENTE
  // =========================================

  function atualizarPerfil(usuario = {}) {
    const nome = String(usuario.nome || "Cliente").trim() || "Cliente";

    definirTexto(".nome-usuario", nome);

    definirTexto(".usuario .email-usuario", usuario.email || "Sua conta");

    definirTexto(
      ".dados-perfil .email-usuario",
      usuario.email || "E-mail não informado",
    );

    definirTexto(
      ".telefone-usuario",
      usuario.telefone || "Telefone não informado",
    );

    definirTexto(
      ".endereco-usuario",
      usuario.endereco || "Endereço não cadastrado",
    );

    const avatar = elemento("#avatar-usuario");
    const avatarPerfil = elemento("#avatar-meu-perfil");
    const titulo = elemento("#titulo-dashboard");

    if (avatar) {
      avatar.textContent = iniciais(nome);
    }

    if (avatarPerfil) {
      avatarPerfil.textContent = iniciais(nome);
    }

    if (titulo) {
      titulo.textContent = `Bem-vindo, ${nome.split(/\s+/)[0]}! 👋`;
    }

    definirTexto(
      ".tempo-cliente",
      `Cliente desde: ${formatarData(usuario.criado_em)}`,
    );
  }

  // =========================================
  // STATUS DOS SERVIÇOS
  // =========================================

  function classeStatus(status) {
    if (status === "EM_ANDAMENTO") {
      return "etiqueta-status etiqueta-laranja";
    }

    if (status === "CONCLUIDO") {
      return "etiqueta-status etiqueta-verde";
    }

    if (status === "CANCELADO") {
      return "etiqueta-status etiqueta-cancelado";
    }

    return "etiqueta-status etiqueta-ciano";
  }

  // =========================================
  // CRIAÇÃO DOS CARDS DE SERVIÇO
  // =========================================

  function criarServico(servico) {
    const cartao = criar("article", "cartao-servico");

    cartao.dataset.servicoId = String(servico.id ?? "");

    const cabecalho = criar("div", "cabecalho-servico");
    const titulo = criar("div");

    titulo.append(
      criar("h3", "", servico.tipo || "Serviço de manutenção"),
      criar(
        "p",
        "",
        `${servico.protocolo || "Sem protocolo"} · Pedido em ${formatarData(servico.criado_em)}`,
      ),
    );

    cabecalho.append(
      titulo,
      criar(
        "span",
        classeStatus(servico.status),
        servico.status_nome || servico.status || "Pendente",
      ),
    );

    cartao.append(cabecalho);

    if (servico.descricao) {
      cartao.append(criar("p", "descricao-cartao", servico.descricao));
    }

    const informacoes = criar("div", "detalhes-servico-resumo");

    const campos = [
      ["Agendamento", formatarDataHora(servico.inicio_previsto)],
      ["Endereço", servico.endereco || "Não informado"],
    ];

    campos.forEach(([rotulo, valor]) => {
      const campo = criar("div", "detalhe-servico");

      campo.append(criar("span", "", rotulo), criar("strong", "", valor));

      informacoes.append(campo);
    });

    if (servico.tecnico) {
      const campo = criar("div", "detalhe-servico");

      campo.append(
        criar("span", "", "Técnico responsável"),
        criar("strong", "", servico.tecnico),
      );

      informacoes.append(campo);
    }

    cartao.append(informacoes);

    const acoes = criar("div", "acoes-servico");

    const botao = criar("button", "botao-detalhes", "Ver detalhes completos →");

    botao.type = "button";
    botao.dataset.detalhesId = String(servico.id ?? "");

    acoes.append(botao);

    // Link opcional para o WhatsApp do técnico.
    const telefoneInformado = String(servico.telefone_tecnico || "").replace(
      /\D/g,
      "",
    );

    if (telefoneInformado) {
      const telefone = telefoneInformado.startsWith("55")
        ? telefoneInformado
        : `55${telefoneInformado}`;

      const link = criar("a", "botao-detalhes", "WhatsApp do técnico");

      link.href = `https://wa.me/${telefone}`;
      link.target = "_blank";
      link.rel = "noopener noreferrer";

      acoes.append(link);
    }

    cartao.append(acoes);

    return cartao;
  }

  // =========================================
  // RENDERIZAÇÃO DOS SERVIÇOS
  // =========================================

  function renderizarServicos(servicos = []) {
    if (!listaAndamento || !listaHistorico) return;

    listaAndamento.replaceChildren();
    listaHistorico.replaceChildren();

    const andamento = servicos.filter((item) => item.status === "EM_ANDAMENTO");

    const historico = servicos.filter((item) => item.status !== "EM_ANDAMENTO");

    if (andamento.length) {
      andamento.forEach((item) => {
        listaAndamento.append(criarServico(item));
      });
    } else {
      listaAndamento.append(
        criar(
          "div",
          "estado-vazio",
          "Você não tem serviços em andamento no momento.",
        ),
      );
    }

    if (historico.length) {
      historico.forEach((item) => {
        listaHistorico.append(criarServico(item));
      });
    } else {
      listaHistorico.append(
        criar(
          "div",
          "estado-vazio",
          servicos.length
            ? "Não há outros pedidos no histórico."
            : "Você ainda não possui pedidos.",
        ),
      );
    }

    const total = elemento("#total-pedidos");
    const totalAndamento = elemento("#total-andamento");
    const totalConcluidos = elemento("#total-concluidos");

    if (total) {
      total.textContent = String(servicos.length);
    }

    if (totalAndamento) {
      totalAndamento.textContent = String(andamento.length);
    }

    if (totalConcluidos) {
      totalConcluidos.textContent = String(
        servicos.filter((item) => item.status === "CONCLUIDO").length,
      );
    }
  }

  // =========================================
  // DOCUMENTOS
  // =========================================

  function renderizarDocumentos(documentos = []) {
    if (!listaDocumentos) return;

    listaDocumentos.replaceChildren();

    if (!documentos.length) {
      listaDocumentos.append(
        criar("div", "estado-vazio", "Nenhum documento disponível no momento."),
      );

      return;
    }

    documentos.forEach((documento) => {
      const cartao = criar("article", "cartao-documento");

      cartao.append(criar("span", "icone-documento", "📄"));

      const dados = criar("div", "dados-documento");

      dados.append(
        criar("strong", "", documento.titulo || documento.nome || "Documento"),
        criar(
          "p",
          "",
          `${documento.protocolo || ""}${documento.emitido_em ? ` · ${formatarData(documento.emitido_em)}` : ""}`,
        ),
      );

      cartao.append(dados);

      const url = documento.url || documento.arquivo_url || documento.caminho;

      if (url) {
        const link = criar("a", "link-documento", "Abrir");

        link.href = String(url);
        link.target = "_blank";
        link.rel = "noopener noreferrer";

        link.setAttribute(
          "aria-label",
          `Abrir documento ${documento.titulo || documento.nome || ""}`,
        );

        cartao.append(link);
      }

      listaDocumentos.append(cartao);
    });
  }

  // =========================================
  // NOTIFICAÇÕES
  // =========================================

  function renderizarNotificacoes(notificacoes = []) {
    if (!listaNotificacoes) return;

    listaNotificacoes.replaceChildren();

    notificacoes.forEach((notificacao) => {
      const linha = criar("article", "notificacao-item");

      linha.classList.toggle("nao-lida", !notificacao.lida);

      linha.append(criar("span", "notificacao-icone", "🔔"));

      const texto = criar("div", "notificacao-texto");

      texto.append(
        criar("strong", "", notificacao.titulo || "Notificação"),
        criar("p", "", notificacao.mensagem || ""),
        criar(
          "time",
          "",
          notificacao.criado_em
            ? formatarDataHora(notificacao.criado_em)
            : notificacao.lida
              ? "Lida"
              : "Não lida",
        ),
      );

      linha.append(texto);
      listaNotificacoes.append(linha);
    });

    const naoLidas = notificacoes.filter((item) => !item.lida).length;

    const lidas = notificacoes.length - naoLidas;

    const contador = elemento("#contador-notificacoes");

    if (contador) {
      contador.textContent = String(naoLidas);
      contador.hidden = naoLidas === 0;
    }

    if (estadoNotificacoesVazio) {
      estadoNotificacoesVazio.hidden = notificacoes.length > 0;
    }

    if (botaoMarcarLidas) {
      botaoMarcarLidas.hidden = naoLidas === 0;
    }

    if (botaoLimparLidas) {
      botaoLimparLidas.hidden = lidas === 0;
    }

    if (botaoNotificacoes) {
      botaoNotificacoes.setAttribute(
        "aria-label",
        `Notificações: ${naoLidas} não lidas`,
      );
    }

    if (avisoNotificacoes) {
      avisoNotificacoes.textContent = `${naoLidas} não lida(s)`;
    }
  }

  // =========================================
  // ATUALIZAÇÃO GERAL DO DASHBOARD
  // =========================================

  function renderizar(dados) {
    dadosAtuais = dados;
    csrf = dados.csrf || "";

    atualizarPerfil(dados.usuario || {});

    renderizarServicos(Array.isArray(dados.servicos) ? dados.servicos : []);

    renderizarDocumentos(
      Array.isArray(dados.documentos) ? dados.documentos : [],
    );

    renderizarNotificacoes(
      Array.isArray(dados.notificacoes) ? dados.notificacoes : [],
    );

    if (statusDashboard) {
      statusDashboard.classList.remove("erro");

      const mensagem = dados.servicos?.length
        ? "Seus pedidos e atendimentos estão atualizados."
        : "Seu painel está pronto. Quando você fizer um pedido, ele aparecerá aqui.";

      const paragrafo = elemento(".status-conteudo p", statusDashboard);

      if (paragrafo) {
        paragrafo.textContent = mensagem;
      }
    }

    ultimaAtualizacao = Date.now();
  }

  async function carregarDashboard(mostrarCarregamento = false) {
    if (carregando) return;

    carregando = true;

    if (botaoRecarregar) {
      botaoRecarregar.disabled = true;
    }

    if (mostrarCarregamento && statusDashboard) {
      const paragrafo = elemento(".status-conteudo p", statusDashboard);

      if (paragrafo) {
        paragrafo.textContent = "Carregando seus dados…";
      }
    }

    try {
      const resposta = await fetch(API, {
        credentials: "same-origin",
        cache: "no-store",
        headers: {
          Accept: "application/json",
        },
      });

      if (resposta.status === 401) {
        window.location.replace("./login.html");
        return;
      }

      const dados = await resposta.json();

      if (!resposta.ok || dados?.sucesso !== true) {
        throw new Error(
          dados?.mensagem || "Não foi possível carregar o painel.",
        );
      }

      if (
        !dados.usuario ||
        !Array.isArray(dados.servicos) ||
        !Array.isArray(dados.documentos) ||
        !Array.isArray(dados.notificacoes)
      ) {
        throw new Error(
          "O servidor retornou dados incompletos ou em formato inesperado.",
        );
      }

      renderizar(dados);
    } catch (erro) {
      console.error("Erro ao carregar o dashboard:", erro);

      if (statusDashboard) {
        statusDashboard.classList.add("erro");

        const paragrafo = elemento(".status-conteudo p", statusDashboard);

        if (paragrafo) {
          paragrafo.textContent =
            "Não foi possível carregar seus dados. Verifique o servidor e tente novamente.";
        }
      }
    } finally {
      carregando = false;

      if (botaoRecarregar) {
        botaoRecarregar.disabled = false;
      }
    }
  }

  // =========================================
  // DETALHES DO SERVIÇO
  // =========================================

  function abrirDetalhes(id) {
    const servico = dadosAtuais?.servicos?.find(
      (item) => String(item.id) === String(id),
    );

    if (!servico || !dialogoDetalhes || !conteudoDetalhes) {
      return;
    }

    conteudoDetalhes.replaceChildren();

    const campos = [
      ["Protocolo", servico.protocolo || "—"],
      ["Serviço", servico.tipo || "—"],
      ["Status", servico.status_nome || servico.status || "—"],
      ["Solicitado em", formatarData(servico.criado_em)],
      ["Data e horário", formatarDataHora(servico.inicio_previsto)],
      ["Endereço de atendimento", servico.endereco || "Não informado"],
      ["Técnico", servico.tecnico || "Ainda não atribuído"],
      ["Descrição", servico.descricao || "Sem descrição adicional."],
    ];

    campos.forEach(([rotulo, valor]) => {
      const linha = criar("div", "detalhe-servico");

      linha.append(criar("span", "", rotulo), criar("strong", "", valor));

      conteudoDetalhes.append(linha);
    });

    if (typeof dialogoDetalhes.showModal === "function") {
      dialogoDetalhes.showModal();
    } else {
      dialogoDetalhes.setAttribute("open", "");
    }
  }

  // =========================================
  // AÇÕES DAS NOTIFICAÇÕES
  // =========================================

  async function enviarAcaoNotificacao(
    url,
    mensagemSucesso,
    confirmarRemocao = false,
  ) {
    if (!csrf) {
      if (avisoNotificacoes) {
        avisoNotificacoes.textContent =
          "Token de segurança indisponível. Atualize o painel.";
      }

      return;
    }

    const botao = confirmarRemocao ? botaoLimparLidas : botaoMarcarLidas;

    if (botao) botao.disabled = true;

    if (avisoNotificacoes) {
      avisoNotificacoes.textContent = "Processando…";
    }

    try {
      const resposta = await fetch(url, {
        method: "POST",
        credentials: "same-origin",
        cache: "no-store",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
        },
        body: new URLSearchParams({ csrf }),
      });

      if (resposta.status === 401) {
        window.location.replace("./login.html");
        return;
      }

      const resultado = await resposta.json();

      if (!resposta.ok || resultado?.sucesso !== true) {
        throw new Error(
          resultado?.mensagem || "Não foi possível concluir a ação.",
        );
      }

      await carregarDashboard();

      if (avisoNotificacoes) {
        avisoNotificacoes.textContent =
          confirmarRemocao && Number(resultado.removidas) > 0
            ? `${resultado.removidas} notificação(ões) lida(s) removida(s).`
            : mensagemSucesso;
      }
    } catch (erro) {
      if (avisoNotificacoes) {
        avisoNotificacoes.textContent = erro.message || "Ocorreu um erro.";
      }
    } finally {
      if (botao) botao.disabled = false;
    }
  }

  function fecharNotificacoes(devolverFoco = false) {
    if (painelNotificacoes) {
      painelNotificacoes.hidden = true;
    }

    if (botaoNotificacoes) {
      botaoNotificacoes.setAttribute("aria-expanded", "false");
    }

    if (devolverFoco && botaoNotificacoes) {
      botaoNotificacoes.focus();
    }
  }

  // =========================================
  // EVENTOS DAS NOTIFICAÇÕES
  // =========================================

  if (botaoNotificacoes && painelNotificacoes) {
    botaoNotificacoes.addEventListener("click", () => {
      painelNotificacoes.hidden = !painelNotificacoes.hidden;

      botaoNotificacoes.setAttribute(
        "aria-expanded",
        String(!painelNotificacoes.hidden),
      );

      if (!painelNotificacoes.hidden) {
        elemento("#fechar-notificacoes")?.focus();
      }
    });
  }

  elemento("#fechar-notificacoes")?.addEventListener("click", () =>
    fecharNotificacoes(true),
  );

  document.addEventListener("click", (evento) => {
    const wrapper = elemento(".notificacoes-wrapper");

    if (wrapper && !wrapper.contains(evento.target)) {
      fecharNotificacoes();
    }
  });

  document.addEventListener("keydown", (evento) => {
    if (
      evento.key === "Escape" &&
      painelNotificacoes &&
      !painelNotificacoes.hidden
    ) {
      fecharNotificacoes(true);
    }
  });

  botaoMarcarLidas?.addEventListener("click", () => {
    enviarAcaoNotificacao(
      "../../../backend/notificacoes_lidas.php",
      "Todas as notificações foram lidas.",
    );
  });

  botaoLimparLidas?.addEventListener("click", () => {
    enviarAcaoNotificacao(
      "../../../backend/notificacoes_limpar.php",
      "Não havia notificações lidas para limpar.",
      true,
    );
  });

  botaoRecarregar?.addEventListener("click", () => {
    carregarDashboard(true);
  });

  // Delegação de eventos: funciona nos cards criados dinamicamente.
  document.addEventListener("click", (evento) => {
    const botao = evento.target.closest("[data-detalhes-id]");

    if (botao) {
      abrirDetalhes(botao.dataset.detalhesId);
    }
  });

  elemento("#fechar-dialog")?.addEventListener("click", () =>
    dialogoDetalhes?.close(),
  );

  dialogoDetalhes?.addEventListener("click", (evento) => {
    if (evento.target === dialogoDetalhes) {
      dialogoDetalhes.close();
    }
  });

  // =========================================
  // ENCERRAR SESSÃO
  // =========================================

  botaoSair?.addEventListener("click", async () => {
    botaoSair.disabled = true;

    try {
      await fetch("../../../backend/logout.php", {
        method: "POST",
        credentials: "same-origin",
        cache: "no-store",
      });
    } catch (erro) {
      console.error("Erro ao encerrar sessão:", erro);
    } finally {
      window.location.replace("./login.html");
    }
  });

  // =========================================
  // INICIALIZAÇÃO E ATUALIZAÇÃO AUTOMÁTICA
  // =========================================

  iniciarViews();
  carregarDashboard(true);

  document.addEventListener("visibilitychange", () => {
    if (
      document.visibilityState === "visible" &&
      Date.now() - ultimaAtualizacao > 10000
    ) {
      carregarDashboard();
    }
  });

  window.setInterval(() => {
    if (document.visibilityState === "visible") {
      carregarDashboard();
    }
  }, 30000);
})();

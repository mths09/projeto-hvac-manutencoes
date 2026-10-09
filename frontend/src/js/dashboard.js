const areaNotificacoes = document.querySelector(".area-notificacoes");
const botaoNotificacoes = document.querySelector("#btn-notificacoes");
const painelNotificacoes = document.querySelector("#painel-notificacoes");
const botaoFechar = document.querySelector("#fechar-notificacoes");
const listaNotificacoes = document.querySelector("#lista-notificacoes");
const contadorNotificacoes = document.querySelector(".contador-notificacoes");
const statusNotificacoes = document.querySelector("#status-notificacoes");

const listaHistorico = document.querySelector(".lista-servicos");
const listaAndamento = document.querySelector("#servicos-andamento");
const listaDocumentos = document.querySelector(".documentos");

const statusDashboard = document.querySelector("#status-dashboard");
const botaoRecarregar = document.querySelector("#recarregar-dashboard");

let carregandoDashboard = false;
let controleDashboard = null;

// Cria elementos usando texto, sem interpretar os dados como HTML.
function criarElemento(tag, classe, texto) {
  const elemento = document.createElement(tag);

  if (classe) {
    elemento.className = classe;
  }

  if (texto !== undefined && texto !== null) {
    elemento.textContent = texto;
  }

  return elemento;
}

function formatarData(valor) {
  const data = String(valor || "").slice(0, 10);

  if (!/^\d{4}-\d{2}-\d{2}$/.test(data)) {
    return "Data não informada";
  }

  return data.split("-").reverse().join("/");
}

function criarEtiqueta(servico) {
  const classes = {
    EM_ANDAMENTO: "etiqueta-laranja",
    CONCLUIDO: "etiqueta-verde",
    AGENDADO: "etiqueta-ciano",
    SOLICITADO: "etiqueta-ciano",
  };

  return criarElemento(
    "p",
    classes[servico.status] || "descricao-servico",
    servico.status_nome
  );
}

// Mostra apenas os serviços recebidos do banco.
function renderizarServicos(servicos) {
  listaAndamento.replaceChildren();
  listaHistorico.replaceChildren();

  for (const servico of servicos) {
    const emAndamento = servico.status === "EM_ANDAMENTO";

    const cartao = criarElemento(
      "article",
      emAndamento ? "cartao servico-ativo" : "servico-historico"
    );

    cartao.dataset.servicoId = servico.id;

    cartao.append(
      criarElemento(
        "h3",
        emAndamento ? "titulo-cartao" : "titulo-servico",
        servico.tipo
      ),
      criarEtiqueta(servico),
      criarElemento(
        "p",
        "descricao-servico",
        `${servico.protocolo} · Solicitado em ${formatarData(servico.criado_em)}`
      )
    );

    if (servico.descricao) {
      cartao.append(
        criarElemento("p", "descricao-cartao", servico.descricao)
      );
    }

    if (emAndamento) {
      listaAndamento.append(cartao);
    } else {
      const item = criarElemento("li");
      item.append(cartao);
      listaHistorico.append(item);
    }
  }

  document.querySelector("#andamento-vazio").hidden =
    listaAndamento.children.length > 0;

  listaHistorico.hidden = listaHistorico.children.length === 0;

  const historicoVazio = document.querySelector("#historico-vazio");

  historicoVazio.hidden = listaHistorico.children.length > 0;

  historicoVazio.querySelector("p").textContent =
    servicos.length === 0
      ? "Você ainda não possui pedidos."
      : "Você não possui outros pedidos além dos serviços em andamento.";
}

function renderizarDocumentos(documentos) {
  listaDocumentos.replaceChildren();

  for (const documento of documentos) {
    const item = criarElemento("article", "estado-vazio");

    item.append(
      criarElemento("strong", "", documento.titulo),
      criarElemento(
        "p",
        "",
        `${documento.protocolo} · ${formatarData(documento.emitido_em)}`
      )
    );

    listaDocumentos.append(item);
  }

  listaDocumentos.hidden = documentos.length === 0;

  document.querySelector("#documentos-vazios").hidden =
    documentos.length > 0;
}

function renderizarNotificacoes(notificacoes) {
  listaNotificacoes.replaceChildren();

  for (const notificacao of notificacoes) {
    const item = criarElemento("li", "item-notificacao");

    item.classList.toggle("nao-lida", !notificacao.lida);

    item.append(
      criarElemento("h3", "", notificacao.titulo),
      criarElemento("p", "", notificacao.mensagem),
      criarElemento(
        "span",
        "estado-notificacao",
        notificacao.lida ? "Lida" : "Não lida"
      )
    );

    listaNotificacoes.append(item);
  }

  const naoLidas = notificacoes.filter((item) => !item.lida).length;

  contadorNotificacoes.textContent = naoLidas;
  contadorNotificacoes.hidden = naoLidas === 0;

  listaNotificacoes.hidden = notificacoes.length === 0;

  document.querySelector("#notificacoes-vazias").hidden =
    notificacoes.length > 0;

  botaoNotificacoes.setAttribute(
    "aria-label",
    `Notificações: ${naoLidas} não lidas`
  );

  statusNotificacoes.textContent = "";
}

// Remove dados antigos durante saída ou falha de carregamento.
// O traço significa que os dados ainda não foram confirmados.
function limparDashboard() {
  document.querySelectorAll(".card-indicador strong").forEach((campo) => {
    campo.textContent = "—";
  });

  [
    listaAndamento,
    listaHistorico,
    listaDocumentos,
    listaNotificacoes,
  ].forEach((lista) => {
    lista.replaceChildren();
  });

  document
    .querySelectorAll(
      "#andamento-vazio, #historico-vazio, #documentos-vazios, #notificacoes-vazias"
    )
    .forEach((elemento) => {
      elemento.hidden = true;
    });

  contadorNotificacoes.hidden = true;
}

// Busca os pedidos do usuário conectado.
async function carregarDashboard(exibirCarregamento = false) {
  if (carregandoDashboard) {
    return;
  }

  carregandoDashboard = true;
  controleDashboard = new AbortController();

  if (exibirCarregamento) {
    statusDashboard.textContent = "Carregando seus pedidos…";
  }

  botaoRecarregar.hidden = true;

  try {
    const resposta = await fetch("../../../backend/dashboard_cliente.php", {
      credentials: "same-origin",
      cache: "no-store",
      headers: {
        Accept: "application/json",
      },
      signal: controleDashboard.signal,
    });

    if (resposta.status === 401) {
      limparDashboard();
      limparDadosPerfil();
      window.location.replace("./login.html");
      return;
    }

    if (!resposta.ok) {
      throw new Error(`Falha ao carregar o painel: ${resposta.status}`);
    }

    const dados = await resposta.json();

    const chaves = [
      "total",
      "andamento",
      "concluidos",
      "agendados",
    ];

    const resumoValido =
      dados?.resumo &&
      chaves.every(
        (chave) =>
          Number.isSafeInteger(dados.resumo[chave]) &&
          dados.resumo[chave] >= 0
      );

    const listasValidas =
      Array.isArray(dados?.servicos) &&
      Array.isArray(dados?.documentos) &&
      Array.isArray(dados?.notificacoes);

    if (dados?.sucesso !== true || !resumoValido || !listasValidas) {
      throw new Error("Resposta inválida do painel.");
    }

    // Atualiza os quatro contadores com os valores do banco.
    for (const chave of chaves) {
      document.querySelector(
        `.indicador-${chave} strong`
      ).textContent = dados.resumo[chave];
    }

    renderizarServicos(dados.servicos);
    renderizarDocumentos(dados.documentos);
    renderizarNotificacoes(dados.notificacoes);

    statusDashboard.textContent = "";
  } catch (erro) {
    if (erro.name === "AbortError") {
      return;
    }

    // Falha de conexão não deve aparecer como zero pedidos.
    limparDashboard();

    statusDashboard.textContent =
      "Não foi possível carregar seus pedidos. Tente novamente.";

    statusNotificacoes.textContent =
      "Não foi possível carregar as notificações.";

    botaoRecarregar.hidden = false;

    console.error(erro);
  } finally {
    carregandoDashboard = false;
  }
}

// Painel de notificações.
function fecharPainel(devolverFoco = false) {
  painelNotificacoes.hidden = true;
  botaoNotificacoes.setAttribute("aria-expanded", "false");

  if (devolverFoco) {
    botaoNotificacoes.focus();
  }
}

botaoNotificacoes.addEventListener("click", () => {
  if (!painelNotificacoes.hidden) {
    fecharPainel(true);
    return;
  }

  painelNotificacoes.hidden = false;
  botaoNotificacoes.setAttribute("aria-expanded", "true");
  botaoFechar.focus();
});

botaoFechar.addEventListener("click", () => {
  fecharPainel(true);
});

document.addEventListener("click", (evento) => {
  if (!areaNotificacoes.contains(evento.target)) {
    fecharPainel();
  }
});

document.addEventListener("keydown", (evento) => {
  if (evento.key === "Escape" && !painelNotificacoes.hidden) {
    fecharPainel(true);
  }
});

areaNotificacoes.addEventListener("focusout", (evento) => {
  if (
    evento.relatedTarget &&
    !areaNotificacoes.contains(evento.relatedTarget)
  ) {
    fecharPainel();
  }
});

// Preenche o perfil com os dados recebidos do PHP.
function exibirPerfilDashboard(perfil) {
  const nome = perfil.nome.trim() || "Cliente";

  document.querySelectorAll(".nome-usuario").forEach((elemento) => {
    elemento.textContent = nome;
  });

  document.querySelector(".usuario .email-usuario").textContent =
    perfil.email;

  document.querySelector(".coluna-lateral .email-usuario").textContent =
    `📧 ${perfil.email}`;

  document.querySelector(".telefone-usuario").textContent =
    `📱 ${perfil.telefone}`;

  document.querySelector(".endereco-usuario").textContent =
    `📍 ${perfil.endereco}`;

  document.querySelector("#avatar-usuario").textContent =
    obterIniciais(nome);

  document.querySelector("#avatar-meu-perfil").textContent =
    obterIniciais(nome);

  document.querySelector("#titulo-dashboard").textContent =
    `Bem-vindo, ${nome.split(/\s+/)[0]}! 👋`;
}

async function atualizarPerfilDashboard() {
  const mensagem = document.querySelector("#status-perfil");

  mensagem.textContent = "Carregando perfil…";

  try {
    const perfil = await carregarPerfil();

    exibirPerfilDashboard(perfil);
    mensagem.textContent = "";
  } catch (erro) {
    mensagem.textContent =
      "Não foi possível carregar seu perfil. Recarregue a página.";

    console.error(erro);
  }
}

botaoRecarregar.addEventListener("click", () => {
  carregarDashboard(true);
});

// Carregamento inicial.
atualizarPerfilDashboard();
carregarDashboard(true);

// Atualiza ao retornar à aba.
document.addEventListener("visibilitychange", () => {
  if (document.visibilityState === "visible") {
    carregarDashboard();
  }
});

// Atualiza quando a página é restaurada pelo navegador.
window.addEventListener("pageshow", (evento) => {
  if (evento.persisted) {
    atualizarPerfilDashboard();
    carregarDashboard(true);
  }
});

// Limpa os dados ao sair da página.
window.addEventListener("pagehide", () => {
  controleDashboard?.abort();
  limparDashboard();
  limparDadosPerfil();
});

// Atualiza automaticamente enquanto o painel estiver visível.
setInterval(() => {
  if (document.visibilityState === "visible") {
    carregarDashboard();
  }
}, 30000);
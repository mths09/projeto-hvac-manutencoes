// dados ficticios, apenas para demonstrar
const notificacoes = [
  {
    titulo: "Técnico atribuído",
    mensagem: "Roberto Silva será responsável pelo serviço SRV-002.",
    lida: false,
  },
  {
    titulo: "Serviço em andamento",
    mensagem: "O atendimento de eletromecânica foi iniciado.",
    lida: false,
  },
  {
    titulo: "Documento disponível",
    mensagem: "A nota fiscal do serviço SRV-001 está disponível.",
    lida: false,
  },
];

// Elementos do HTML que serão controlados.
const areaNotificacoes = document.querySelector(".area-notificacoes");
const botaoNotificacoes = document.querySelector("#btn-notificacoes");
const painelNotificacoes = document.querySelector("#painel-notificacoes");
const listaNotificacoes = document.querySelector("#lista-notificacoes");
const contadorNotificacoes = document.querySelector(".contador-notificacoes");
const botaoFechar = document.querySelector("#fechar-notificacoes");
const botaoMarcarLidas = document.querySelector("#marcar-lidas");
const statusNotificacoes = document.querySelector("#status-notificacoes");
const mensagemNotificacoesVazias = document.querySelector(
  "#notificacoes-vazias",
);

// Criando itens da lista usando dados do HTML
function renderizarNotificacoes() {
  listaNotificacoes.replaceChildren();
  const semNotificacoes = notificacoes.length === 0;

  mensagemNotificacoesVazias.hidden = !semNotificacoes;
  listaNotificacoes.hidden = semNotificacoes;
  botaoMarcarLidas.hidden = semNotificacoes;

  // limpa uma confirmação de renderização anterior
  statusNotificacoes.textContent = "";

  notificacoes.forEach((notificacao) => {
    const item = document.createElement("li");
    item.classList.add("item-notificacao");
    item.classList.toggle("nao-lida", !notificacao.lida); // adiciona ou remove classe

    const titulo = document.createElement("h3");
    titulo.textContent = notificacao.titulo;

    const mensagem = document.createElement("p");
    mensagem.textContent = notificacao.mensagem;

    const estado = document.createElement("span");
    estado.className = "estado-notificacao";
    estado.textContent = notificacao.lida ? "Lida" : "Não Lida";

    item.append(titulo, mensagem, estado);
    listaNotificacoes.append(item);
  });

  const quantidade = notificacoes.filter(
    (notificacao) => !notificacao.lida,
  ).length;

  contadorNotificacoes.textContent = quantidade;
  contadorNotificacoes.hidden = quantidade === 0; // esconde se as notificacoes forem 0
  botaoMarcarLidas.disabled = quantidade === 0; // desativa o botao se não tem notificacoes lidas

  botaoNotificacoes.setAttribute(
    "aria-label",
    `Notificações: ${quantidade} não lidas`,
  );
}

function abrirPainel() {
  painelNotificacoes.hidden = false;
  botaoNotificacoes.setAttribute("aria-expanded", "true");
  botaoFechar.focus();
}

function fecharPainel(devolverFoco = false) {
  painelNotificacoes.hidden = true;
  botaoNotificacoes.setAttribute("aria-expanded", "false");

  if (devolverFoco) {
    botaoNotificacoes.focus();
  }
}

// Clique no sino alterna entre aberto e fechado
botaoNotificacoes.addEventListener("click", () => {
  if (painelNotificacoes.hidden) {
    abrirPainel();
  } else {
    fecharPainel();
  }
});

botaoFechar.addEventListener("click", () => {
  fecharPainel(true);
});

// Fecha ao clicar fora da area de notificacao
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

// não puxa o foco de volta quando sair usando tab
areaNotificacoes.addEventListener("focusout", (evento) => {
  if (
    evento.relatedTarget &&
    !areaNotificacoes.contains(evento.relatedTarget)
  ) {
    fecharPainel();
  }
});

// marca como lida quando o botao é acionado
botaoMarcarLidas.addEventListener("click", () => {
  notificacoes.forEach((notificacao) => {
    notificacao.lida = true;
  });

  botaoFechar.focus();
  renderizarNotificacoes();

  statusNotificacoes.textContent = "Todas as notificações foram lidas.";
});

// preenche a lista ao carregar pagina
renderizarNotificacoes();

// ESTADOS DOS SERVIÇOS

function atualizarEstadosDashboard() {
  const servicoAtivo = document.querySelector(".servico-ativo");
  const listaHistorico = document.querySelector(".lista-servicos");
  const listaDocumentos = document.querySelector(".documentos");

  const itensHistorico = listaHistorico.querySelectorAll(
    ":scope > li[data-status]",
  );

  const documentos = listaDocumentos.querySelectorAll(".btn-documento");

  // serviço em andamento: verifica se existe cartão
  document.querySelector("#andamento-vazio").hidden = servicoAtivo !== null;

  // histórico: mostra a mensagem somente se não houver itens
  const semHistorico = itensHistorico.length === 0;

  listaHistorico.hidden = semHistorico;
  document.querySelector("#historico-vazio").hidden = !semHistorico;

  // documentos: verifica a quantidade de botões existentes
  const semDocumentos = documentos.length === 0;

  listaDocumentos.hidden = semDocumentos;
  document.querySelector("#documentos-vazios").hidden = !semDocumentos;

  // cartoes para calcular indicadores
  const servicos = [...itensHistorico];

  if (servicoAtivo) {
    servicos.push(servicoAtivo);
  }

  function contarStatus(status) {
    return servicos.filter((servico) => servico.dataset.status === status)
      .length;
  }

  document.querySelector(".indicador-andamento strong").textContent =
    contarStatus("andamento");

  document.querySelector(".indicador-concluidos strong").textContent =
    contarStatus("concluido");

  document.querySelector(".indicador-agendados strong").textContent =
    contarStatus("agendado");
}

atualizarEstadosDashboard();

// coloca dados do perfil no html
function exibirPerfilDashboard(perfil) {
  const nomeExibido = perfil.nome.trim() || "Cliente";
  const primeiroNome = nomeExibido.split(/\s+/)[0];
  const iniciais = obterIniciais(perfil.nome);

  // atualizando nome no cabeçalho e card perfil

  document.querySelectorAll(".nome-usuario").forEach((elemento) => {
    elemento.textContent = nomeExibido;
  });

  // email no cabecalho
  document.querySelector(".usuario .email-usuario").textContent = perfil.email;

  // email no cartão perfil
  document.querySelector(".coluna-lateral .email-usuario").textContent =
    `📧 ${perfil.email}`;

  document.querySelector(".telefone-usuario").textContent =
    `📱 ${perfil.telefone}`;

  document.querySelector(".endereco-usuario").textContent =
    `📍 ${perfil.endereco}`;

  document.querySelector("#avatar-usuario").textContent = iniciais;
  document.querySelector("#avatar-meu-perfil").textContent = iniciais;

  document.querySelector("#titulo-dashboard").textContent =
    `Bem-vindo, ${primeiroNome}! 👋`;
}

// carrega os dados e trata erros
async function atualizarPerfilDashboard() {
  const mensagem = document.querySelector("#status-perfil");

  mensagem.textContent = "Carregando Perfil...";

  try {
    const perfil = await carregarPerfil();

    exibirPerfilDashboard(perfil);
    mensagem.textContent = "";
  } catch (erro) {
    mensagem.textContent =
      "Não foi possível atualizar os dados do perfil. Recarregue a página.";

    console.error(erro);
  }
}

atualizarPerfilDashboard();

// atualiza quando retorna pelo navegador
window.addEventListener("pageshow", (evento) => {
  if (evento.persisted) {
    atualizarPerfilDashboard();
  }
});

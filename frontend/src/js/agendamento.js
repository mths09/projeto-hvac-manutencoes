let servicoSelecionado = "Ar Condicionado";
let dataSelecionada = "";
let horarioSelecionado = "";

/* ========================= */
/* ELEMENTOS */
/* ========================= */

const cardsServico = document.querySelectorAll(".servico-card");

const botaoContinuar = document.getElementById("botaoContinuar");

const telaEtapa1 = document.getElementById("tela-etapa-1");
const telaEtapa2 = document.getElementById("tela-etapa-2");
const telaEtapa3 = document.getElementById("tela-etapa-3");

const etapa1 = document.getElementById("etapa-1");
const etapa2 = document.getElementById("etapa-2");
const etapa3 = document.getElementById("etapa-3");

const nomeServicoEscolhido = document.getElementById("nomeServicoEscolhido");

const alterarServico = document.getElementById("alterarServico");

const calendario = document.getElementById("calendario");

const horariosContainer = document.getElementById("horarios");

const tituloHorarios = document.getElementById("tituloHorarios");

const hojeTexto = document.getElementById("hojeTexto");

const botaoVoltar = document.getElementById("botaoVoltar");

const botaoRevisar = document.getElementById("botaoRevisar");

const voltarDetalhes = document.getElementById("voltarDetalhes");

const confirmarAgendamento = document.getElementById("confirmarAgendamento");

const endereco = document.getElementById("endereco");

const descricao = document.getElementById("descricao");

const nomeCompleto = document.getElementById("nomeCompleto");

const telefone = document.getElementById("telefone");

/* ========================= */
/* MÁSCARA DO TELEFONE */
/* ========================= */

telefone.addEventListener("input", () => {
  let numeros = telefone.value.replace(/\D/g, "");

  // Limita a 11 números: DDD + celular
  numeros = numeros.slice(0, 11);

  if (numeros.length > 7) {
    telefone.value = `(${numeros.slice(0, 2)}) ${numeros.slice(2, 7)}-${numeros.slice(7)}`;
  } else if (numeros.length > 2) {
    telefone.value = `(${numeros.slice(0, 2)}) ${numeros.slice(2)}`;
  } else if (numeros.length > 0) {
    telefone.value = `(${numeros}`;
  } else {
    telefone.value = "";
  }
});

/* ========================= */
/* DATA ATUAL */
/* ========================= */

const hoje = new Date();

const anoAtual = hoje.getFullYear();

const mesAtual = hoje.getMonth();

const diaHoje = hoje.getDate();

const nomesMeses = [
  "Janeiro",
  "Fevereiro",
  "Março",
  "Abril",
  "Maio",
  "Junho",
  "Julho",
  "Agosto",
  "Setembro",
  "Outubro",
  "Novembro",
  "Dezembro",
];

hojeTexto.textContent = `${String(diaHoje).padStart(
  2,
  "0",
)}/${String(mesAtual + 1).padStart(2, "0")}`;

/* ========================= */
/* SELEÇÃO DE SERVIÇO */
/* ========================= */

cardsServico.forEach((card) => {
  card.addEventListener("click", () => {
    cardsServico.forEach((item) => {
      item.classList.remove("selecionado");
    });

    card.classList.add("selecionado");

    servicoSelecionado = card.dataset.servico;

    botaoContinuar.textContent = `Continuar — ${servicoSelecionado} →`;
  });
});

/* ========================= */
/* IR PARA ETAPA 2 */
/* ========================= */

botaoContinuar.addEventListener("click", () => {
  nomeServicoEscolhido.textContent = servicoSelecionado;

  telaEtapa1.classList.add("escondido");

  telaEtapa2.classList.remove("escondido");

  telaEtapa3.classList.add("escondido");

  etapa1.classList.remove("ativa");
  etapa1.classList.add("concluida");

  etapa2.classList.add("ativa");

  etapa3.classList.remove("ativa");

  gerarCalendario();

  atualizarIcones();
});

/* ========================= */
/* ALTERAR SERVIÇO */
/* ========================= */

alterarServico.addEventListener("click", () => {
  telaEtapa2.classList.add("escondido");

  telaEtapa1.classList.remove("escondido");

  etapa2.classList.remove("ativa");

  etapa1.classList.remove("concluida");
  etapa1.classList.add("ativa");
});

botaoVoltar.addEventListener("click", () => {
  telaEtapa2.classList.add("escondido");

  telaEtapa1.classList.remove("escondido");

  etapa2.classList.remove("ativa");

  etapa1.classList.remove("concluida");
  etapa1.classList.add("ativa");
});

/* ========================= */
/* CALENDÁRIO */
/* ========================= */

function gerarCalendario() {
  calendario.innerHTML = "";

  const tituloMes = document.getElementById("tituloMes");

  tituloMes.textContent = `${nomesMeses[mesAtual]} ${anoAtual}`;

  const primeiroDia = new Date(anoAtual, mesAtual, 1).getDay();

  const ultimoDia = new Date(anoAtual, mesAtual + 1, 0).getDate();

  /* ESPAÇOS ANTES DO DIA 1 */

  for (let i = 0; i < primeiroDia; i++) {
    const espaco = document.createElement("div");

    calendario.appendChild(espaco);
  }

  /* DIAS */

  for (let dia = 1; dia <= ultimoDia; dia++) {
    const botao = document.createElement("button");

    botao.type = "button";

    botao.textContent = dia;

    botao.classList.add("dia");

    /*
      Por enquanto estamos simulando
      os dias disponíveis.

      Depois vamos puxar isso
      diretamente do banco.
    */

    const disponivel = dia >= diaHoje && dia <= diaHoje + 14;

    if (disponivel) {
      botao.classList.add("disponivel");
    } else {
      botao.disabled = true;
    }

    botao.addEventListener("click", () => selecionarDia(dia, botao));

    calendario.appendChild(botao);
  }
}

/* ========================= */
/* SELECIONAR DIA */
/* ========================= */

function selecionarDia(dia, botao) {
  document.querySelectorAll(".dia").forEach((item) => {
    item.classList.remove("selecionado");
  });

  botao.classList.add("selecionado");

  dataSelecionada = new Date(anoAtual, mesAtual, dia);

  horarioSelecionado = "";

  tituloHorarios.textContent = `Horários para ${String(dia).padStart(
    2,
    "0",
  )}/${String(mesAtual + 1).padStart(2, "0")}`;

  gerarHorarios();
}

/* ========================= */
/* HORÁRIOS */
/* ========================= */

function gerarHorarios() {
  horariosContainer.innerHTML = "";

  /*
    Horários simulados.

    Posteriormente estes horários
    serão buscados do banco de dados.
  */

  const horarios = [
    "09:00",
    "10:00",
    "11:00",
    "14:00",
    "15:00",
    "16:00",
    "17:00",
  ];

  horarios.forEach((horario) => {
    const botao = document.createElement("button");

    botao.type = "button";

    botao.textContent = horario;

    botao.classList.add("horario");

    botao.addEventListener("click", () => {
      document.querySelectorAll(".horario").forEach((item) => {
        item.classList.remove("selecionado");
      });

      botao.classList.add("selecionado");

      horarioSelecionado = horario;
    });

    horariosContainer.appendChild(botao);
  });
}

/* ========================= */
/* REVISAR AGENDAMENTO */
/* ========================= */

botaoRevisar.addEventListener("click", () => {
  // Validação do nome completo
  if (nomeCompleto.value.trim() === "") {
    alert("Informe seu nome completo.");
    nomeCompleto.focus();
    return;
  }

  // Verifica se foi informado nome e sobrenome
  const partesNome = nomeCompleto.value.trim().split(/\s+/);

  if (partesNome.length < 2) {
    alert("Informe seu nome completo, incluindo nome e sobrenome.");
    nomeCompleto.focus();
    return;
  }

  // Validação do telefone
  const numerosTelefone = telefone.value.replace(/\D/g, "");

  if (numerosTelefone.length !== 11) {
    alert("Informe um telefone válido com DDD. Exemplo: (15) 99999-9999.");
    telefone.focus();
    return;
  }

  // Continua com as validações que já existiam
  if (!dataSelecionada) {
    alert("Selecione uma data para o atendimento.");
    return;
  }
  if (!dataSelecionada) {
    alert("Selecione uma data para o atendimento.");

    return;
  }

  if (!horarioSelecionado) {
    alert("Selecione um horário.");

    return;
  }

  if (endereco.value.trim() === "") {
    alert("Informe o endereço do atendimento.");

    return;
  }

  if (descricao.value.trim().length < 10) {
    alert("Descreva o problema com pelo menos 10 caracteres.");

    return;
  }

  preencherResumo();

  telaEtapa2.classList.add("escondido");

  telaEtapa3.classList.remove("escondido");

  etapa2.classList.remove("ativa");

  etapa2.classList.add("concluida");

  etapa3.classList.add("ativa");

  atualizarIcones();
});

/* ========================= */
/* PREENCHER RESUMO */
/* ========================= */

function preencherResumo() {
  document.getElementById("resumoNomeCompleto").textContent =
    nomeCompleto.value.trim();

  document.getElementById("resumoTelefone").textContent = telefone.value.trim();

  document.getElementById("resumoServico").textContent = servicoSelecionado;
  document.getElementById("resumoServico").textContent = servicoSelecionado;

  document.getElementById("resumoData").textContent =
    dataSelecionada.toLocaleDateString("pt-BR");

  document.getElementById("resumoHorario").textContent = horarioSelecionado;

  document.getElementById("resumoEndereco").textContent = endereco.value;

  document.getElementById("resumoDescricao").textContent = descricao.value;
}

/* ========================= */
/* VOLTAR PARA DETALHES */
/* ========================= */

voltarDetalhes.addEventListener("click", () => {
  telaEtapa3.classList.add("escondido");

  telaEtapa2.classList.remove("escondido");

  etapa3.classList.remove("ativa");

  etapa2.classList.remove("concluida");

  etapa2.classList.add("ativa");

  atualizarIcones();
});

/* ========================= */
/* CONFIRMAR */
/* ========================= */

confirmarAgendamento.addEventListener("click", () => {
  alert(
    "Agendamento confirmado!\n\n" +
      "Serviço: " +
      servicoSelecionado +
      "\nData: " +
      dataSelecionada.toLocaleDateString("pt-BR") +
      "\nHorário: " +
      horarioSelecionado,
  );

  /*
      PRÓXIMA ETAPA:

      Aqui vamos substituir o alert
      por uma requisição para o PHP.

      O PHP irá salvar:

      - usuário
      - serviço
      - data
      - horário
      - endereço
      - descrição
      - arquivos
      - status do agendamento
    */
});

/* ========================= */
/* ATUALIZAR ÍCONES */
/* ========================= */

function atualizarIcones() {
  if (typeof lucide !== "undefined") {
    lucide.createIcons();
  }
}

function validarDescricao(valor) {
  const tamanho = Array.from(valor.trim()).length;

  if (tamanho < 10) {
    return "Descreva o problema com pelo menos 10 caracteres.";
  }

  if (tamanho > 2000) {
    return "Use no máximo 2.000 caracteres na descrição.";
  }

  return "";
}

// impedir horario que ja passou
function validarHorarioSelecionado() {
  if (
    !(dataSelecionada instanceof Date) ||
    Number.isNaN(dataSelecionada.getTime())
  ) {
    return "Selecione uma data válida.";
  }

  if (!/^([01]\d|2[0-3]):[0-5]\d$/.test(horarioSelecionado)) {
    return "Selecione um horário válido.";
  }

  const [hora, minuto] = horarioSelecionado.split(":").map(Number);
  const atendimento = new Date(dataSelecionada);

  atendimento.setHours(hora, minuto, 0, 0);

  if (atendimento <= new Date()) {
    return "Escolha uma data e um horário futuros.";
  }

  return "";
}

function validarArquivos(lista) {
  const formatos = /\.(jpe?g|png|mp4)$/i;
  const limitePorArquivo = 5 * 1024 * 1024;

  for (const arquivo of lista) {
    if (!formatos.test(arquivo.name)) {
      return `O arquivo "${arquivo.name}" deve ser JPG, PNG ou MP4.`;
    }

    if (arquivo.size === 0) {
      return `O arquivo "${arquivo.name}" está vazio.`;
    }

    if (arquivo.size > limitePorArquivo) {
      return `O arquivo "${arquivo.name}" ultrapassa 5 MB.`;
    }
  }

  return "";
}

const campoArquivo = document.querySelector("#arquivo");

campoArquivo.addEventListener("change", () => {
  mostrarErro(campoArquivo, validarArquivos(campoArquivo.files));
});

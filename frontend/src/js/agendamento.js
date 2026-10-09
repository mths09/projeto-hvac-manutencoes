(() => {
  "use strict";

  const API = "../../../backend/agendamento.php";

  const elemento = (id) => document.getElementById(id);

  const cards = [
    ...document.querySelectorAll(".servico-card")
  ];

  const tipos = {
    "Ar Condicionado": "AR_CONDICIONADO",
    "Frigorífico / Geladeira": "REFRIGERACAO",
    "Iluminação": "ILUMINACAO",
    "Manutenção Preventiva": "MANUTENCAO_PREVENTIVA",
    "Eletromecânica": "ELETROMECANICA"
  };

  let dados = null;
  let servico = "AR_CONDICIONADO";
  let urgente = false;
  let dia = "";
  let horario = "";
  let mes = null;
  let pedido = null;
  let enviando = false;
  let carregando = false;
  let incerto = false;
  let concluido = false;

  // Mensagens exibidas na própria página.
  const aviso = document.createElement("p");

  aviso.id = "mensagem-agendamento";
  aviso.setAttribute("role", "status");
  aviso.tabIndex = -1;
  aviso.hidden = true;

  aviso.style.cssText =
    "padding:14px;margin:16px 0;border:1px solid;" +
    "border-radius:10px;line-height:1.6";

  document.querySelector(".titulo-area").append(aviso);

  const tentar = document.createElement("button");

  tentar.type = "button";
  tentar.className = "botao-voltar";
  tentar.textContent = "Recarregar";
  tentar.hidden = true;

  aviso.after(tentar);

  const linkPedidos = document.createElement("a");

  linkPedidos.href = "./dashboard.html";
  linkPedidos.textContent = "Consultar Meus Pedidos →";
  linkPedidos.style.color = "#8ff3d5";
  linkPedidos.hidden = true;

  tentar.after(linkPedidos);

  function mensagem(texto, erro = false, focar = true) {
    aviso.textContent = texto;
    aviso.hidden = !texto;
    aviso.style.color = erro ? "#ffb4b4" : "#b7fff0";

    if (focar && texto) {
      aviso.focus();
    }
  }

  function etapa(numero) {
    for (let i = 1; i <= 3; i++) {
      elemento(`tela-etapa-${i}`).classList.toggle(
        "escondido",
        i !== numero
      );

      elemento(`etapa-${i}`).classList.toggle(
        "ativa",
        i === numero
      );

      elemento(`etapa-${i}`).classList.toggle(
        "concluida",
        i < numero
      );
    }

    window.lucide?.createIcons();
  }

  function nomeServico() {
    return dados?.servicos.find(
      (item) => item.codigo === servico
    )?.nome || "Ar Condicionado";
  }

  function atualizarServico() {
    for (const card of cards) {
      const marcado =
        tipos[card.dataset.servico] === servico ||
        (!tipos[card.dataset.servico] && urgente);

      card.classList.toggle("selecionado", marcado);

      card.setAttribute(
        "aria-pressed",
        String(marcado)
      );
    }

    const texto =
      nomeServico() + (urgente ? " · Urgente" : "");

    elemento("nomeServicoEscolhido").textContent = texto;

    elemento("botaoContinuar").textContent =
      `Continuar — ${texto} →`;
  }

  // Urgência é uma prioridade adicional ao tipo de serviço.
  for (const card of cards) {
    if (!tipos[card.dataset.servico]) {
      card.querySelector("h3").textContent =
        "Prioridade urgente";

      card.querySelector("p").textContent =
        "Marque junto com o tipo de serviço. A equipe avaliará a urgência.";
    }

    card.addEventListener("click", () => {
      if (tipos[card.dataset.servico]) {
        servico = tipos[card.dataset.servico];
      } else {
        urgente = !urgente;
      }

      atualizarServico();
    });
  }

  // A equipe definirá o técnico.
  elemento("tecnico").disabled = true;

  elemento("tecnico").options[0].textContent =
    "A equipe definirá o técnico";

  elemento("resumoTecnico").textContent =
    "A equipe definirá o técnico";

  // Upload será implementado em outra etapa.
  elemento("arquivo").disabled = true;

  const upload = document.querySelector(".upload-area");
  const avisoAnexo = document.createElement("p");

  avisoAnexo.textContent =
    "Fotos e vídeos ainda não são enviados nesta etapa.";

  avisoAnexo.style.cssText =
    "padding:16px;font-size:12px;text-align:center";

  upload.replaceChildren(avisoAnexo);

  elemento("nomeCompleto").maxLength = 150;
  elemento("endereco").maxLength = 500;
  elemento("descricao").maxLength = 2000;

  elemento("telefone").addEventListener("input", (evento) => {
    evento.target.value = evento.target.value.replace(
      /[^\d()+\s-]/g,
      ""
    );
  });

  // Navegação entre os meses.
  const navegacao = document.createElement("div");

  navegacao.style.cssText =
    "display:flex;justify-content:space-between;margin-bottom:12px";

  const botoesMes = [
    ["← Mês anterior", -1],
    ["Próximo mês →", 1]
  ];

  for (const [rotulo, deslocamento] of botoesMes) {
    const botao = document.createElement("button");

    botao.type = "button";
    botao.className = "alterar-servico";
    botao.textContent = rotulo;

    botao.addEventListener("click", () => {
      if (!dados) return;

      const proximo = new Date(
        mes.getFullYear(),
        mes.getMonth() + deslocamento,
        1
      );

      const [ano, numeroMes] = dados.hoje
        .split("-")
        .map(Number);

      const minimo = new Date(ano, numeroMes - 1, 1);
      const maximo = new Date(ano, numeroMes + 1, 1);

      if (proximo >= minimo && proximo <= maximo) {
        mes = proximo;
        calendario();
      }
    });

    navegacao.append(botao);
  }

  elemento("tituloMes").before(navegacao);

  function calendario() {
    if (!dados) return;

    const area = elemento("calendario");

    area.replaceChildren();

    elemento("tituloMes").textContent =
      mes.toLocaleDateString("pt-BR", {
        month: "long",
        year: "numeric"
      });

    for (let i = 0; i < mes.getDay(); i++) {
      area.append(document.createElement("span"));
    }

    const ultimo = new Date(
      mes.getFullYear(),
      mes.getMonth() + 1,
      0
    ).getDate();

    for (let numero = 1; numero <= ultimo; numero++) {
      const ano = mes.getFullYear();

      const numeroMes = String(
        mes.getMonth() + 1
      ).padStart(2, "0");

      const numeroDia = String(numero).padStart(2, "0");

      const data = `${ano}-${numeroMes}-${numeroDia}`;

      const livre = dados.horarios.some(
        (item) => item.inicio.slice(0, 10) === data
      );

      const botao = document.createElement("button");

      botao.type = "button";
      botao.className = "dia";
      botao.textContent = numero;
      botao.disabled = !livre;

      botao.classList.toggle("disponivel", livre);

      botao.classList.toggle(
        "selecionado",
        data === dia
      );

      botao.setAttribute(
        "aria-label",
        data.split("-").reverse().join("/")
      );

      botao.addEventListener("click", () => {
        dia = data;
        horario = "";

        calendario();
        horarios();
      });

      area.append(botao);
    }
  }

  function horarios() {
    const area = elemento("horarios");

    area.replaceChildren();

    elemento("tituloHorarios").textContent = dia
      ? `Horários para ${dia.split("-").reverse().join("/")}`
      : "Selecione um dia primeiro";

    const horariosDoDia = dados.horarios.filter(
      (item) => item.inicio.slice(0, 10) === dia
    );

    for (const item of horariosDoDia) {
      const botao = document.createElement("button");

      botao.type = "button";
      botao.className = "horario";
      botao.textContent = item.inicio.slice(11, 16);

      botao.classList.toggle(
        "selecionado",
        horario === item.id
      );

      botao.addEventListener("click", () => {
        horario = item.id;
        horarios();
      });

      area.append(botao);
    }
  }

  async function requisicao(opcoes = {}) {
    const controle = new AbortController();

    const limite = setTimeout(
      () => controle.abort(),
      20000
    );

    try {
      const resposta = await fetch(API, {
        credentials: "same-origin",
        cache: "no-store",
        ...opcoes,
        headers: {
          Accept: "application/json"
        },
        signal: controle.signal
      });

      const json = await resposta.json();

      if (!resposta.ok || json.sucesso !== true) {
        const erro = new Error(
          json.mensagem ||
          "Não foi possível concluir a solicitação."
        );

        erro.status = resposta.status;
        erro.codigo = json.codigo;

        throw erro;
      }

      return json;
    } finally {
      clearTimeout(limite);
    }
  }

  async function carregar() {
    if (carregando) return false;

    carregando = true;
    elemento("botaoContinuar").disabled = true;
    tentar.hidden = true;

    try {
      dados = await requisicao();

      if (
        !Array.isArray(dados.horarios) ||
        !Array.isArray(dados.servicos) ||
        !dados.usuario?.id
      ) {
        throw new Error(
          "O servidor retornou dados incompletos."
        );
      }

      const [ano, numeroMes] = dados.hoje
        .split("-")
        .map(Number);

      mes ||= new Date(ano, numeroMes - 1, 1);

      elemento("hojeTexto").textContent =
        dados.hoje.split("-").reverse().join("/");

      if (!elemento("nomeCompleto").value) {
        elemento("nomeCompleto").value = dados.usuario.nome;
      }

      if (!elemento("telefone").value) {
        elemento("telefone").value =
          dados.usuario.telefone || "";
      }

      for (const card of cards) {
        if (tipos[card.dataset.servico]) {
          card.disabled = !dados.servicos.some(
            (tipo) =>
              tipo.codigo === tipos[card.dataset.servico]
          );
        }
      }

      if (
        !dados.servicos.some(
          (tipo) => tipo.codigo === servico
        )
      ) {
        servico = dados.servicos[0]?.codigo || "";
      }

      atualizarServico();
      calendario();
      horarios();

      mensagem(
        dados.horarios.length
          ? "Selecione um horário. O pedido ficará aguardando aprovação da equipe."
          : "Não há horários disponíveis nos próximos 60 dias. Entre em contato com a equipe.",
        false,
        false
      );

      elemento("botaoContinuar").disabled = !servico;

      return true;
    } catch (erro) {
      if (erro.status === 401) {
        window.location.replace("./login.html");
      }

      mensagem(
        erro.message ||
        "Não foi possível carregar os horários.",
        true
      );

      tentar.hidden = false;

      return false;
    } finally {
      carregando = false;
    }
  }

  // Guarda somente um envio pendente por cliente nesta aba.
  function guardarPendente(valor) {
    const chave =
      `hvac.agendamento.pendente.${dados.usuario.id}`;

    if (valor) {
      sessionStorage.setItem(
        chave,
        JSON.stringify(valor)
      );
    } else {
      sessionStorage.removeItem(chave);
    }
  }

  function resumo() {
    const valores = {
      resumoNomeCompleto: pedido.campos.nome,
      resumoTelefone: pedido.campos.telefone,

      resumoServico:
        pedido.nomeServico +
        (pedido.campos.urgente === "1" ? " · Urgente" : ""),

      resumoData: pedido.inicio
        .slice(0, 10)
        .split("-")
        .reverse()
        .join("/"),

      resumoHorario: pedido.inicio.slice(11, 16),
      resumoEndereco: pedido.campos.endereco,
      resumoDescricao: pedido.campos.descricao
    };

    for (const [id, valor] of Object.entries(valores)) {
      elemento(id).textContent = valor;
    }

    elemento("confirmarAgendamento").textContent =
      "Enviar pedido ✓";

    elemento("voltarDetalhes").disabled = incerto;

    etapa(3);
  }

  elemento("botaoContinuar").addEventListener(
    "click",
    () => {
      if (dados) etapa(2);
    }
  );

  for (const id of ["botaoVoltar", "alterarServico"]) {
    elemento(id).addEventListener(
      "click",
      () => etapa(1)
    );
  }

  elemento("voltarDetalhes").addEventListener(
    "click",
    () => {
      if (!enviando && !incerto && !concluido) {
        etapa(2);
      }
    }
  );

  elemento("botaoRevisar").addEventListener(
    "click",
    async () => {
      if (carregando || enviando) return;

      const nome = elemento("nomeCompleto").value
        .trim()
        .replace(/\s+/g, " ");

      const telefone = elemento("telefone").value
        .replace(/\D/g, "");

      const endereco = elemento("endereco").value.trim();
      const descricao = elemento("descricao").value.trim();

      let erro = "";

      if (
        nome.split(" ").length < 2 ||
        [...nome].length > 150
      ) {
        erro =
          "Informe nome e sobrenome, com até 150 caracteres.";
      } else if (!/^\d{10,11}$/.test(telefone)) {
        erro =
          "Informe o telefone com DDD, com 10 ou 11 números.";
      } else if (
        [...endereco].length < 10 ||
        [...endereco].length > 500
      ) {
        erro =
          "Informe o endereço completo, entre 10 e 500 caracteres.";
      } else if (
        [...descricao].length < 10 ||
        [...descricao].length > 2000
      ) {
        erro =
          "Descreva o problema usando entre 10 e 2.000 caracteres.";
      } else if (!horario) {
        erro = "Selecione a data e o horário.";
      }

      if (erro) {
        mensagem(erro, true);
        return;
      }

      elemento("botaoRevisar").disabled = true;

      const ok = await carregar();

      elemento("botaoRevisar").disabled = false;

      if (!ok) return;

      const vaga = dados.horarios.find(
        (item) => item.id === horario
      );

      if (!vaga) {
        horario = "";

        mensagem(
          "Esse horário não está mais disponível. Escolha outro.",
          true
        );

        return;
      }

      pedido = {
        protocolo: dados.protocolo,
        inicio: vaga.inicio,
        nomeServico: nomeServico(),

        campos: {
          tipo_servico: servico,
          disponibilidade: horario,
          nome,
          telefone,
          endereco,
          descricao,
          urgente: urgente ? "1" : "0"
        }
      };

      mensagem(
        "Confira os dados. O horário ficará pendente de aprovação.",
        false,
        false
      );

      linkPedidos.hidden = true;

      resumo();
    }
  );

  elemento("confirmarAgendamento").addEventListener(
    "click",
    async () => {
      if (enviando || !pedido) return;

      if (concluido) {
        window.location.assign("./dashboard.html");
        return;
      }

      try {
        guardarPendente(pedido);
      } catch {
        mensagem(
          "Permita o armazenamento deste site no navegador para enviar o pedido com segurança contra duplicação.",
          true
        );

        return;
      }

      enviando = true;
      incerto = true;

      elemento("confirmarAgendamento").disabled = true;
      elemento("voltarDetalhes").disabled = true;

      elemento("confirmarAgendamento").textContent =
        "Enviando…";

      mensagem(
        "Enviando seu pedido…",
        false,
        false
      );

      const formulario = new FormData();

      for (const [chave, valor] of Object.entries(pedido.campos)) {
        formulario.append(chave, valor);
      }

      formulario.append("protocolo", pedido.protocolo);
      formulario.append("csrf", dados.csrf);

      try {
        const retorno = await requisicao({
          method: "POST",
          body: formulario
        });

        concluido = true;
        incerto = false;

        try {
          guardarPendente(null);
        } catch {
          // O pedido já foi confirmado pelo servidor.
        }

        mensagem(
          `${retorno.mensagem} Protocolo: ${retorno.protocolo}`
        );

        elemento("confirmarAgendamento").textContent =
          "Ver meus pedidos →";

        elemento("voltarDetalhes").hidden = true;
      } catch (erro) {
        if (erro.status === 401) {
          window.location.replace("./login.html");
          return;
        }

        if (
          [400, 422].includes(erro.status) ||
          erro.codigo === "HORARIO_INDISPONIVEL"
        ) {
          incerto = false;

          try {
            guardarPendente(null);
          } catch {
            // A interface ainda permite corrigir os dados.
          }

          elemento("voltarDetalhes").disabled = false;

          mensagem(
            erro.message +
            " Clique em Alterar para corrigir e revisar novamente.",
            true
          );
        } else {
          linkPedidos.hidden = false;

          const texto = erro.status === 403
            ? erro.message
            : "Não foi possível confirmar a resposta do servidor.";

          mensagem(
            texto +
            " Consulte Meus Pedidos no dashboard ou tente reenviar este mesmo pedido. Não crie outro antes de conferir.",
            true
          );
        }

        elemento("confirmarAgendamento").textContent =
          "Tentar enviar novamente";
      } finally {
        enviando = false;
        elemento("confirmarAgendamento").disabled = false;
      }
    }
  );

  tentar.addEventListener("click", () => carregar());

  async function iniciar() {
    if (!(await carregar())) return;

    try {
      const chave =
        `hvac.agendamento.pendente.${dados.usuario.id}`;

      const salvo = sessionStorage.getItem(chave);

      if (!salvo) return;

      const anterior = JSON.parse(salvo);

      if (
        !/^SRV-[a-f0-9]{24}$/.test(anterior.protocolo) ||
        !anterior.campos ||
        typeof anterior.inicio !== "string"
      ) {
        return;
      }

      pedido = anterior;
      incerto = true;
      linkPedidos.hidden = false;

      resumo();

      elemento("confirmarAgendamento").textContent =
        "Verificar / reenviar pedido";

      mensagem(
        "Há um envio sem confirmação neste navegador. Verifique ou reenvie o mesmo pedido para evitar duplicação."
      );
    } catch {
      mensagem(
        "Não foi possível recuperar o último envio. Confira Meus Pedidos antes de enviar novamente.",
        true
      );
    }
  }

  iniciar();
})();
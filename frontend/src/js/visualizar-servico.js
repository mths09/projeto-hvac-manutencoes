(() => {
  "use strict";

  const API = "../../../backend/dashboard_cliente.php";
  const PAGINA_LOGIN = "./login.html";

  const $ = (seletor) => document.querySelector(seletor);

  const idServico = new URLSearchParams(window.location.search).get("id");

  // =========================================
  // FUNÇÕES AUXILIARES
  // =========================================

  function criar(tag, classe = "", texto) {
    const no = document.createElement(tag);

    if (classe) no.className = classe;
    if (texto !== undefined && texto !== null) no.textContent = String(texto);

    return no;
  }

  function texto(seletor, valor, padrao = "—") {
    const no = $(seletor);

    if (no) no.textContent = String(valor ?? "").trim() || padrao;
  }

  function formatarData(valor) {
    if (!valor) return "—";

    const dia = String(valor).slice(0, 10);

    return /^\d{4}-\d{2}-\d{2}$/.test(dia)
      ? dia.split("-").reverse().join("/")
      : dia;
  }

  function formatarDataHora(valor) {
    if (!valor) return "Ainda não agendado";

    const bruto = String(valor).replace("T", " ");
    const hora = bruto.length >= 16 ? bruto.slice(11, 16) : "";

    return hora ? `${formatarData(bruto)} às ${hora}` : formatarData(bruto);
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
  // CABEÇALHO (usuário logado)
  // =========================================

  function preencherUsuario(usuario = {}) {
    const nome = String(usuario.nome || "Cliente").trim() || "Cliente";

    texto(".usuario .nome-usuario", nome);
    texto(".usuario .email-usuario", usuario.email, "Sua conta");
    texto("#avatar-usuario", iniciais(nome));
  }

  // =========================================
  // LINHA DO TEMPO (montada a partir do status)
  // =========================================

  function criarEtapa(titulo, detalhe, estado) {
    const etapa = criar("li", `etapa ${estado}`);
    const corpo = criar("div");
    const h3 = criar("h3", "", titulo);

    if (estado === "atual") {
      h3.append(criar("span", "selo-agora", "Agora"));
    }

    corpo.append(h3);

    if (detalhe) corpo.append(criar("p", "", detalhe));

    etapa.append(criar("span", "marcador"), corpo);

    return etapa;
  }

  function montarLinhaTempo(servico) {
    const lista = $(".timeline");

    if (!lista) return;

    lista.replaceChildren();

    if (servico.status === "CANCELADO") {
      lista.append(
        criarEtapa(
          "Solicitação recebida",
          formatarData(servico.criado_em),
          "concluida",
        ),
        criarEtapa("Serviço cancelado", "", "atual"),
      );

      return;
    }

    const etapas = [
      ["Solicitação recebida", formatarData(servico.criado_em)],
      [
        "Atendimento agendado",
        servico.inicio_previsto
          ? formatarDataHora(servico.inicio_previsto)
          : "",
      ],
      ["Em andamento", servico.tecnico ? `Técnico: ${servico.tecnico}` : ""],
      ["Concluído", ""],
    ];

    let atual = 0;

    if (servico.status === "CONCLUIDO") atual = 3;
    else if (servico.status === "EM_ANDAMENTO") atual = 2;
    else if (servico.status === "AGENDADO" || servico.inicio_previsto)
      atual = 1;

    etapas.forEach(([titulo, detalhe], indice) => {
      let estado = "pendente";

      if (indice < atual) estado = "concluida";
      else if (indice === atual) {
        estado = servico.status === "CONCLUIDO" ? "concluida" : "atual";
      }

      lista.append(criarEtapa(titulo, detalhe, estado));
    });
  }

  // =========================================
  // DADOS DO SERVIÇO
  // =========================================

  function criarVisualizadorAnexo(anexo) {
    if (!anexo || typeof anexo !== "object") return null;

    const id = String(anexo.id ?? "");
    if (!/^\d+$/.test(id)) return null;

    const url = `../../../backend/anexo_imagem.php?id=${encodeURIComponent(id)}`;
    const figura = document.createElement("figure");

    if (anexo.mime === "image/jpeg" || anexo.mime === "image/png") {
      const imagem = document.createElement("img");
      imagem.src = url;
      imagem.alt = anexo.legenda || "Imagem anexada ao pedido";
      imagem.loading = "lazy";
      figura.append(imagem);
    } else if (anexo.mime === "video/mp4") {
      const video = document.createElement("video");
      video.controls = true;
      video.playsInline = true;
      video.preload = "metadata";

      const fonte = document.createElement("source");
      fonte.src = url;
      fonte.type = anexo.mime;
      video.append(fonte);
      figura.append(video);
    } else {
      return null;
    }

    if (anexo.legenda) {
      const legenda = document.createElement("figcaption");
      legenda.textContent = anexo.legenda;
      figura.append(legenda);
    }

    return figura;
  }

  function renderizarAnexos(anexos) {
    const lista = $("#lista-fotos-atendimento");
    if (!lista) return;

    lista.replaceChildren();

    const arquivos = Array.isArray(anexos) ? anexos : [];
    const galeria = document.createElement("div");
    galeria.className = "galeria-anexos";

    for (const anexo of arquivos) {
      const visualizador = criarVisualizadorAnexo(anexo);
      if (visualizador) galeria.append(visualizador);
    }

    if (galeria.childElementCount === 0) {
      lista.className = "estado-vazio";
      lista.append(criar("p", "", "Não há anexos deste serviço por enquanto."));
      return;
    }

    lista.className = "";
    lista.append(galeria);
  }

  function preencherServico(servico) {
    // Título: protocolo + tipo do serviço
    const titulo = $("#titulo-pedido");

    if (titulo) {
      titulo.replaceChildren(
        document.createTextNode(servico.protocolo || "Serviço"),
        criar("span", "", servico.tipo || ""),
      );
    }

    document.title = `${servico.protocolo || "Serviço"} | HVAC Manutenções`;

    // Etiqueta de status
    const etiqueta = $("#etiqueta-status-pedido");

    if (etiqueta) {
      etiqueta.className = "status-servico";

      if (servico.status === "CONCLUIDO") etiqueta.classList.add("concluido");
      if (servico.status === "CANCELADO") etiqueta.classList.add("cancelado");

      etiqueta.replaceChildren(
        criar("span"),
        document.createTextNode(
          servico.status_nome || servico.status || "Pendente",
        ),
      );
      etiqueta.firstChild.setAttribute("aria-hidden", "true");
    }

    texto("#tipo-servico", servico.tipo);
    texto("#agendamento-servico", formatarDataHora(servico.inicio_previsto));
    texto("#endereco-servico", servico.endereco, "Não informado");
    texto("#valor-servico", servico.valor, "Não informado");
    texto("#descricao-problema", servico.descricao, "Sem descrição adicional.");
    texto("#descricao-problema", servico.descricao, "Sem descrição adicional.");

    renderizarAnexos(servico.anexos);

    montarLinhaTempo(servico);
  }

  function mostrarErro(mensagem) {
    const titulo = $("#titulo-pedido");

    if (titulo) titulo.textContent = "Serviço não encontrado";

    const etiqueta = $("#etiqueta-status-pedido");

    if (etiqueta) etiqueta.hidden = true;

    const layout = $(".layout-servico");

    if (!layout) return;

    layout.replaceChildren();

    const aviso = criar("section", "card");
    const p = criar("p", "", mensagem);
    const voltar = criar("a", "btn-agendamento", "← Voltar ao painel");

    voltar.href = "./dashboard.html";
    voltar.style.marginTop = "16px";

    aviso.append(p, voltar);
    layout.append(aviso);
  }

  // =========================================
  // CARREGAMENTO
  // =========================================

  async function carregar() {
    if (!idServico) {
      mostrarErro("Nenhum serviço foi informado.");
      return;
    }

    try {
      const resposta = await fetch(API, {
        credentials: "same-origin",
        cache: "no-store",
        headers: { Accept: "application/json" },
      });

      if (resposta.status === 401) {
        window.location.replace(PAGINA_LOGIN);
        return;
      }

      const dados = await resposta.json();

      if (!resposta.ok || dados?.sucesso !== true) {
        throw new Error(dados?.mensagem || "Não foi possível carregar.");
      }

      preencherUsuario(dados.usuario || {});

      // O PHP só devolve os serviços do cliente logado, então um id de
      // outro cliente simplesmente não é encontrado aqui.
      const servico = (dados.servicos || []).find(
        (item) => String(item.id) === String(idServico),
      );

      if (!servico) {
        mostrarErro("Não encontramos este serviço na sua conta.");
        return;
      }

      preencherServico(servico);
    } catch (erro) {
      console.error("Erro ao carregar o serviço:", erro);
      mostrarErro(
        "Não foi possível carregar os dados do serviço. Verifique o servidor e tente novamente.",
      );
    }
  }

  carregar();
})();

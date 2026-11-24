// Página Projetos: mostra os serviços publicados no painel admin (portfólio).
// Enquanto a empresa não publicar nenhum, ficam os 4 exemplos demonstrativos do HTML.

const API_PORTFOLIO = "../../../backend/portfolio_publico.php";
const RAIZ_SITE = new URL("../../../", location.href);

const grade = document.getElementById("grade-projetos");
const grupoFiltros = document.getElementById("filtros-projetos");
const resultadoFiltros = document.getElementById("resultado-filtros");

// "exemplo demonstrativo" (casos fictícios) ou "serviço realizado" (portfólio real).
let nomeItem = ["exemplo demonstrativo", "exemplos demonstrativos"];

document.querySelectorAll("[data-ano-atual]").forEach((elemento) => {
  elemento.textContent = String(new Date().getFullYear());
});

function atualizarFiltroProjeto(categoriaSelecionada) {
  let quantidadeVisivel = 0;

  grade.querySelectorAll("[data-categoria]").forEach((cartao) => {
    const corresponde =
      categoriaSelecionada === "todos" ||
      cartao.dataset.categoria === categoriaSelecionada;

    cartao.hidden = !corresponde;
    if (corresponde) quantidadeVisivel += 1;
  });

  grupoFiltros.querySelectorAll("[data-filtro]").forEach((botao) => {
    const selecionado = botao.dataset.filtro === categoriaSelecionada;
    botao.classList.toggle("ativo", selecionado);
    botao.setAttribute("aria-pressed", String(selecionado));
  });

  if (resultadoFiltros) {
    resultadoFiltros.textContent = `${quantidadeVisivel} ${
      quantidadeVisivel === 1 ? nomeItem[0] : nomeItem[1]
    }`;
  }
}

// Um único ouvinte serve tanto para os filtros do HTML quanto para os criados pelo JS.
grupoFiltros.addEventListener("click", (evento) => {
  const botao = evento.target.closest("[data-filtro]");
  if (botao) atualizarFiltroProjeto(botao.dataset.filtro);
});

atualizarFiltroProjeto("todos");

// -----------------------------------------
// Portfólio real (itens publicados no painel)
// -----------------------------------------

function criar(tag, classe, texto) {
  const no = document.createElement(tag);
  if (classe) no.className = classe;
  if (texto) no.textContent = texto;
  return no;
}

function criarBotaoFiltro(codigo, rotulo) {
  const botao = criar("button", "filtro-projeto", rotulo);
  botao.type = "button";
  botao.dataset.filtro = codigo;
  botao.setAttribute("aria-pressed", "false");
  return botao;
}

function criarCartaoPortfolio(item) {
  const cartao = criar("article", "cartao-projeto");
  cartao.dataset.categoria = item.categoria_codigo;

  const imagem = document.createElement("img");
  // A API devolve o caminho a partir da raiz do site (./backend/...).
  imagem.src = new URL(item.imagem, RAIZ_SITE).href;
  imagem.alt = item.titulo;
  imagem.loading = "lazy";

  const conteudo = criar("div", "cartao-projeto-conteudo");
  const categoria = criar("span", "categoria-projeto", item.categoria);
  if (/^#[0-9a-f]{3,8}$/i.test(item.cor_categoria || "")) {
    categoria.style.color = item.cor_categoria;
  }
  conteudo.append(categoria, criar("h3", "", item.titulo));
  if (item.descricao) conteudo.append(criar("p", "", item.descricao));
  if (item.localidade) {
    conteudo.append(criar("p", "local-projeto", `📍 ${item.localidade}`));
  }

  cartao.append(imagem, conteudo);
  return cartao;
}

function mostrarPortfolio(itens) {
  const categorias = new Map();
  for (const item of itens) categorias.set(item.categoria_codigo, item.categoria);

  grade.replaceChildren(...itens.map(criarCartaoPortfolio));
  grupoFiltros.replaceChildren(
    criarBotaoFiltro("todos", "Todos"),
    ...[...categorias].map(([codigo, nome]) => criarBotaoFiltro(codigo, nome)),
  );
  grupoFiltros.setAttribute("aria-label", "Filtrar serviços por categoria");
  // Com uma categoria só, os filtros não ajudam em nada.
  grupoFiltros.hidden = categorias.size < 2;

  document.getElementById("titulo-casos").textContent = "Serviços realizados";
  document.getElementById("intro-projetos").textContent =
    "Trabalhos executados pela equipe e publicados pela empresa.";
  document.getElementById("aviso-demonstrativo").hidden = true;

  nomeItem = ["serviço realizado", "serviços realizados"];
  atualizarFiltroProjeto("todos");
}

async function carregarPortfolio() {
  try {
    const resposta = await fetch(API_PORTFOLIO, {
      headers: { Accept: "application/json" },
      cache: "no-store",
    });
    const dados = await resposta.json();
    if (!resposta.ok || dados?.sucesso !== true || !Array.isArray(dados.itens)) {
      return;
    }

    const itens = dados.itens
      .filter((item) => item && item.titulo && item.imagem)
      .map((item) => ({
        titulo: String(item.titulo),
        descricao: String(item.descricao || ""),
        localidade: String(item.localidade || ""),
        categoria: String(item.categoria || "Serviço"),
        categoria_codigo: String(item.categoria_codigo || item.categoria || "servico"),
        cor_categoria: String(item.cor_categoria || ""),
        imagem: String(item.imagem),
      }));

    if (itens.length > 0) mostrarPortfolio(itens);
  } catch (erro) {
    // Sem conexão com o servidor: os exemplos demonstrativos continuam na tela.
    console.warn("Portfólio indisponível; mantendo os exemplos.", erro);
  }
}

carregarPortfolio();

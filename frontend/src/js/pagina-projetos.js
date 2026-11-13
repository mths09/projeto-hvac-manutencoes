const filtrosProjeto = document.querySelectorAll("[data-filtro]");
const cartoesProjeto = document.querySelectorAll("[data-categoria]");
const resultadoFiltros = document.getElementById("resultado-filtros");

document.querySelectorAll("[data-ano-atual]").forEach((elemento) => {
  elemento.textContent = String(new Date().getFullYear());
});

function atualizarFiltroProjeto(categoriaSelecionada) {
  let quantidadeVisivel = 0;

  cartoesProjeto.forEach((cartao) => {
    const corresponde =
      categoriaSelecionada === "todos" ||
      cartao.dataset.categoria === categoriaSelecionada;

    cartao.hidden = !corresponde;
    if (corresponde) quantidadeVisivel += 1;
  });

  filtrosProjeto.forEach((botao) => {
    const selecionado = botao.dataset.filtro === categoriaSelecionada;
    botao.classList.toggle("ativo", selecionado);
    botao.setAttribute("aria-pressed", String(selecionado));
  });

  if (resultadoFiltros) {
    resultadoFiltros.textContent =
      quantidadeVisivel === 1
        ? "1 exemplo demonstrativo"
        : `${quantidadeVisivel} exemplos demonstrativos`;
  }
}

filtrosProjeto.forEach((botao) => {
  botao.addEventListener("click", () =>
    atualizarFiltroProjeto(botao.dataset.filtro),
  );
});

atualizarFiltroProjeto("todos");

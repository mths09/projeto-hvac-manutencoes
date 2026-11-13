// Menu de navegação mobile: o CSS cuida do visual, o JS alterna o estado.
const menuToggle = document.querySelector(".menu-toggle");
const menuPrincipal = document.getElementById("menu-principal");
const telaMobile = window.matchMedia("(max-width: 860px)");

if (menuToggle && menuPrincipal) {
  function definirMenuAberto(aberto) {
    menuPrincipal.classList.toggle("open", aberto);
    menuToggle.setAttribute("aria-expanded", String(aberto));
    menuToggle.setAttribute(
      "aria-label",
      aberto ? "Fechar menu de navegação" : "Abrir menu de navegação",
    );
  }

  menuToggle.addEventListener("click", () => {
    definirMenuAberto(menuToggle.getAttribute("aria-expanded") !== "true");
  });

  menuPrincipal.addEventListener("click", (event) => {
    if (event.target.closest("a")) definirMenuAberto(false);
  });

  document.addEventListener("keydown", (event) => {
    if (
      event.key === "Escape" &&
      menuToggle.getAttribute("aria-expanded") === "true"
    ) {
      definirMenuAberto(false);
      menuToggle.focus();
    }
  });

  document.addEventListener("click", (event) => {
    if (
      !menuPrincipal.contains(event.target) &&
      !menuToggle.contains(event.target)
    ) {
      definirMenuAberto(false);
    }
  });

  telaMobile.addEventListener("change", () => definirMenuAberto(false));
}

function escaparPortfolio(valor) {
  return String(valor).replace(
    /[&<>"']/g,
    (caractere) =>
      ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      })[caractere],
  );
}

function renderizarPortfolio(itens) {
  const grid = document.getElementById("portfolio-grid");
  grid.innerHTML =
    itens
      .map((item) => {
        const cor = /^#[0-9a-f]{6}$/i.test(String(item.cor_categoria || ""))
          ? item.cor_categoria
          : "#00d8f3";
        return `
      <article class="card-portfolio">
        <img class="imagem-portfolio" src="${escaparPortfolio(item.imagem)}" alt="${escaparPortfolio(item.titulo)}" loading="lazy">
        <span class="badge-categoria" style="background-color:${cor}">${escaparPortfolio(item.categoria)}</span>
        <div class="overlay-portfolio">
          <h3 class="titulo-portfolio">${escaparPortfolio(item.titulo)}</h3>
          ${item.localidade ? `<p class="local-portfolio">📍 ${escaparPortfolio(item.localidade)}</p>` : ""}
          ${item.descricao ? `<p class="local-portfolio">${escaparPortfolio(item.descricao)}</p>` : ""}
        </div>
      </article>`;
      })
      .join("") ||
    '<p class="portfolio-vazio">Em breve, novos serviços realizados aparecerão aqui.</p>';
}

async function carregarPortfolio() {
  const grid = document.querySelector("#portfolio-grid");

  if (!grid) {
    return;
  }

  grid.setAttribute("aria-busy", "true");
  grid.textContent = "Carregando projetos...";

  try {
    const resposta = await fetch("./backend/portfolio_publico.php", {
      cache: "no-store",
      headers: { Accept: "application/json" },
    });
    const dados = await resposta.json();
    if (
      !resposta.ok ||
      dados?.sucesso !== true ||
      !Array.isArray(dados.itens)
    ) {
      throw new Error(
        dados?.mensagem || "Não foi possível carregar os projetos.",
      );
    }
    renderizarPortfolio(dados.itens.slice(0, 6));
  } catch (erro) {
    grid.textContent =
      "Não foi possível carregar os projetos. Tente novamente mais tarde.";

    console.error(erro);
  } finally {
    grid.setAttribute("aria-busy", "false");
  }
}

document.addEventListener("DOMContentLoaded", carregarPortfolio);

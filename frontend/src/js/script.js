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

// Mapa de ícones para #form-icone-svg, um por serviço
const icones = {
  "ar-condicionado": document.querySelector(".svg-ar-condicionado"),
  frigorifico: document.querySelector(".svg-frigorifico"),
  iluminacao: document.querySelector(".svg-iluminacao"),
  manutencao: document.querySelector(".svg-manutencao"),
  eletromecanica: document.querySelector(".svg-raio"),
  "atendimento-urgente": document.querySelector(".svg-atendimento-urgente"),
};

const cards = document.querySelectorAll(".card-item");
const painel = document.getElementById("painel-formulario");
const formIcone = document.getElementById("form-icone-svg");
const formSubtitulo = document.querySelector(
  '[data-card-atributo="form-subtitulo"]',
);

let servicoSelecionadoId = null;

// --- Abrir: tira o display:none e dispara a transição ---
function abrirPainel() {
  painel.classList.remove("escondido");
  // Sem isso, o navegador aplica display:flex E max-height final no
  // mesmo frame, e a transição é "pulada" (não há estado anterior pra animar a partir).
  // Dois requestAnimationFrame garantem que o navegador já pintou o estado
  // colapsado antes da classe "mostrar" mudar o alvo da transição.
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      painel.classList.add("mostrar");
      painel.focus();
    });
  });
}

// Fechar formulário
function fecharPainel() {
  painel.classList.remove("mostrar");
}

// Quando a transição de max-height terminar, SE ainda estiver fechado,
// aplica display:none de verdade (remove da árvore de acessibilidade).
// Checamos propertyName porque várias propriedades transicionam juntas
// e não queremos rodar isso 5 vezes.
painel.addEventListener("transitionend", (e) => {
  if (e.propertyName !== "max-height") return;
  if (!painel.classList.contains("mostrar")) {
    painel.classList.add("escondido");
  }
});

cards.forEach((card) => {
  card.addEventListener("click", () => {
    const servico = card.dataset.servico;

    // Clicou de novo no card já aberto - fecha
    if (servico === servicoSelecionadoId) {
      card.classList.remove("selecionado");
      fecharPainel();
      servicoSelecionadoId = null;
      return;
    }

    cards.forEach((c) => c.classList.remove("selecionado"));
    card.classList.add("selecionado");

    const tituloVisivel = card
      .querySelector(".titulo-servico")
      .textContent.trim();
    formSubtitulo.textContent = `${tituloVisivel} ── Quanto mais detalhes, mais rápido enviamos um técnico.`;

    const iconeOriginal = icones[servico];
    formIcone.innerHTML = iconeOriginal ? iconeOriginal.outerHTML : "";

    // Só roda a animação de abrir se o painel estava de fato fechado.
    // Se o usuário está só trocando de um serviço aberto pra outro,
    // o conteúdo troca instantaneamente, sem "piscar" a animação de novo.
    if (servicoSelecionadoId === null) {
      abrirPainel();
    }

    servicoSelecionadoId = servico;
    painel.scrollIntoView({ behavior: "smooth", block: "nearest" });
  });
});

function escaparPortfolio(valor) {
  return String(valor).replace(/[&<>"']/g, (caractere) => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
  })[caractere]);
}

function renderizarPortfolio(itens) {
  const grid = document.getElementById("portfolio-grid");
  grid.innerHTML = itens.map((item) => {
    const cor = /^#[0-9a-f]{6}$/i.test(String(item.cor_categoria || "")) ? item.cor_categoria : "#00d8f3";
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
  }).join("") || '<p class="portfolio-vazio">Em breve, novos serviços realizados aparecerão aqui.</p>';
}

async function carregarPortfolio() {
  const grid = document.querySelector("#portfolio-grid");

  if (!grid) {
    return;
  }

  grid.setAttribute("aria-busy", "true");
  grid.textContent = "Carregando portfólio...";

  try {
    const resposta = await fetch("./backend/portfolio_publico.php", {
      cache: "no-store",
      headers: { Accept: "application/json" },
    });
    const dados = await resposta.json();
    if (!resposta.ok || dados?.sucesso !== true || !Array.isArray(dados.itens)) {
      throw new Error(dados?.mensagem || "Não foi possível carregar o portfólio.");
    }
    renderizarPortfolio(dados.itens.slice(0, 6));
  } catch (erro) {
    grid.textContent =
      "Não foi possível carregar o portfólio. Tente novamente mais tarde.";

    console.error(erro);
  } finally {
    grid.setAttribute("aria-busy", "false");
  }
}

document.addEventListener("DOMContentLoaded", carregarPortfolio);

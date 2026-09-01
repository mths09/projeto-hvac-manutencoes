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

// PORTFOLIO
const PORTFOLIO = [
  {
    img: "https://images.unsplash.com/photo-1757219525975-03b5984bc6e8?w=700&h=500&fit=crop&auto=format",
    title: "Instalação Split Residencial 18.000 BTU",
    location: "Sorocaba, SP",
    category: "Ar Condicionado",
    accent: C.cyan,
  },
  {
    img: "https://images.unsplash.com/photo-1785682118449-21da8825754d?w=700&h=500&fit=crop&auto=format",
    title: "Manutenção Preventiva Industrial",
    location: "Votorantim, SP",
    category: "Eletromecânica",
    accent: C.flameMagenta,
  },
  {
    img: "https://images.unsplash.com/photo-1698479603408-1a66a6d9e80f?w=700&h=500&fit=crop&auto=format",
    title: "Sistema VRF Comercial — 60 TR",
    location: "São Roque, SP",
    category: "Refrigeração",
    accent: C.flameBlue,
  },
  {
    img: "https://images.unsplash.com/photo-1773844389459-110d2b5e18e2?w=700&h=500&fit=crop&auto=format",
    title: "Equipa Técnica Certificada",
    location: "Sorocaba, SP",
    category: "Equipa",
    accent: C.flameViolet,
  },
  {
    img: "https://images.unsplash.com/photo-1681042803902-f79c240d8f03?w=700&h=500&fit=crop&auto=format",
    title: "Climatização Comercial — Rede de Lojas",
    location: "Itu, SP",
    category: "Ar Condicionado",
    accent: C.cyan,
  },
];

// RENDERIZANDO O PORTFOLIO:
function renderizarPortfolio() {
  const grid = document.getElementById("portfolio-grid");

  const html = PORTFOLIO.map(
    (item) => `
    <article class="card-portfolio">
      <img class="imagem-portfolio" src="${item.imagem}" alt="${item.titulo}" loading="lazy">

      <span class="badge-categoria ${item.corBadge}">${item.categoria}</span>

      <div class="overlay-portfolio">
        <h3 class="titulo-portfolio">${item.titulo}</h3>
        <p class="local-portfolio">
          <svg class="icone-pin" viewBox="0 0 24 24" width="14" height="14" fill="currentColor">
            <path d="M12 2C8.1 2 5 5.1 5 9c0 5.3 7 13 7 13s7-7.7 7-13c0-3.9-3.1-7-7-7zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5z"/>
          </svg>
          ${item.local}
        </p>
      </div>
    </article>
  `,
  ).join("");

  grid.innerHTML = html;
}

document.addEventListener("DOMContentLoaded", renderizarPortfolio);

// Selecionar botões dentro de uma div com classe "grade-cards"
const botoesServico = document.querySelectorAll(".grade-cards button");

let servicoSelecionadoId = null;

// Guardar evento click e executar uma ação
botoesServico.forEach(function (botaoAtual) {
  botaoAtual.addEventListener("click", function (e) {
    const botaoClicado = e.currentTarget; // "botaoClicado" passa a ser o botao clicado

    const servicoId = botaoClicado.getAttribute("data-servico"); // Pegar ID do serviço

    // Buscar dados no Objeto serviço
    const dadosServico = SERVICOS[servicoId];
    const svgIcone = pegarIconeTemplate(servicoId); // Pegar SVG

    // Selecionando elementos do painel
    const painel = document.getElementById("painel-formulario");
    const icone = document.getElementById("form-icone-svg");
    const subtitulo = document.querySelector(".subtitulo-painel");

    // Verificar se ja esta aberto
    if (servicoId === servicoSelecionadoId) {
      painel.classList.add("escondido");
      servicoSelecionadoId = null;
      return;
    }

    // Colocando dados no painel
    icone.innerHTML = svgIcone;
    subtitulo.textContent = dadosServico.subtitulo;

    painel.classList.remove("escondido"); // Mostra o painel e remove a classe que o esconde
    servicoSelecionadoId = servicoId;
  });
});

function pegarIconeTemplate(servicoId) {
  // Buscar elemento template pelo ID
  const template = document.getElementById(`tpl-${servicoId}`); // pega o template + servicoId

  // Pegar conteudo do template
  const clone = template.content.cloneNode(true);

  // Div que nunca vai para a tela
  const divTemporaria = document.createElement("div");

  // Colocar clone na div temporaria
  divTemporaria.appendChild(clone);

  return divTemporaria.innerHTML;
}

// Objeto com os dados de cada operação
const SERVICOS = {
  "ar-condicionado": {
    titulo: "Descreva o Problema",
    subtitulo:
      "Ar Condicionado — Quanto mais detalhes, mais rápido enviamos um técnico.",
  },

  frigorifico: {
    titulo: "Descreva o Problema",
    subtitulo:
      "Frigorífico — Quanto mais detalhes, mais rápido enviamos um técnico.",
  },

  iluminacao: {
    titulo: "Descreva o Problema",
    subtitulo:
      "Iluminação — Quanto mais detalhes, mais rápido enviamos um técnico.",
  },

  manutencao: {
    titulo: "Descreva o Problema",
    subtitulo:
      "Manutenção — Quanto mais detalhes, mais rápido enviamos um técnico.",
  },

  eletromecanica: {
    titulo: "Descreva o Problema",
    subtitulo:
      "Eletromecânica — Quanto mais detalhes, mais rápido enviamos um técnico.",
  },

  "atendimento-urgente": {
    titulo: "Descreva o Problema",
    subtitulo:
      "Atendimento Urgente  — Quanto mais detalhes, mais rápido enviamos um técnico.",
  },
};

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

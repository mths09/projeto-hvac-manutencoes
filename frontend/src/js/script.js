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

document.addEventListener("DOMContentLoaded", carregarPortfolio);

// VALIDAÇÃO DO FORMULÁRIO

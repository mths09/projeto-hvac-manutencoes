document.querySelectorAll("[data-ano-atual]").forEach((elemento) => {
  elemento.textContent = String(new Date().getFullYear());
});

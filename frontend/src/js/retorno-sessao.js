document.querySelectorAll("[data-retorno-sessao]").forEach((link) => {
  link.addEventListener("click", async (evento) => {
    evento.preventDefault();

    const paginaInicial = "../../../index.html";
    const dashboard = "./dashboard.html";
    let destinoSemSessao = paginaInicial;

    try {
      const origem = new URL(document.referrer);

      const origemDoProjeto =
        origem.origin === window.location.origin &&
        (origem.pathname.includes("/frontend/") ||
          origem.pathname.endsWith("/index.html"));

      if (origemDoProjeto) {
        destinoSemSessao = origem.href;
      }
    } catch {
      // Sem referrer válido: usar a página inicial.
    }

    try {
      const resposta = await fetch("../../../backend/perfil_cliente.php", {
        method: "GET",
        credentials: "same-origin",
        cache: "no-store",
        headers: { Accept: "application/json" },
      });

      window.location.assign(resposta.ok ? dashboard : destinoSemSessao);
    } catch {
      // Se a API não responder, não deixar a pessoa presa na tela atual.
      window.location.assign(destinoSemSessao);
    }
  });
});

const mensagem = document.getElementById("mensagem-confirmacao");
const botaoConfirmar = document.getElementById("botao-confirmar");
const linkLogin = document.getElementById("link-login");
const parametros = new URLSearchParams(window.location.search);
const token = parametros.get("token");

// Retira o token do histórico visível e evita enviá-lo como referência externa.
history.replaceState(null, "", window.location.pathname);

if (!token || !/^[a-f0-9]{64}$/.test(token)) {
  mensagem.textContent = "Este link de confirmação é inválido ou está incompleto. Solicite um novo cadastro.";
} else {
  mensagem.textContent = "Clique no botão para confirmar que você controla este endereço de e-mail.";
  botaoConfirmar.hidden = false;
  botaoConfirmar.addEventListener("click", async () => {
    botaoConfirmar.disabled = true;
    botaoConfirmar.textContent = "Confirmando…";
    try {
      const resposta = await fetch("../../../backend/confirmar_cadastro.php", {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({ token }),
      });
      const dados = await resposta.json();
      mensagem.textContent = typeof dados.mensagem === "string"
        ? dados.mensagem
        : "Não foi possível confirmar o e-mail. Tente novamente.";
      if (resposta.ok && dados.sucesso === true) {
        botaoConfirmar.hidden = true;
        linkLogin.hidden = false;
        linkLogin.focus();
      } else {
        botaoConfirmar.disabled = false;
        botaoConfirmar.textContent = "Tentar novamente →";
      }
    } catch (erro) {
      console.error("Falha ao confirmar e-mail do cadastro:", erro);
      mensagem.textContent = "Não foi possível confirmar o e-mail agora. Verifique sua conexão e tente novamente.";
      botaoConfirmar.disabled = false;
      botaoConfirmar.textContent = "Tentar novamente →";
    }
  });
}
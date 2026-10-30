const formularioRecuperacao = document.querySelector("#form-recuperacao");
const campoEmailRecuperacao = document.querySelector("#email-recuperacao");
const mensagemRecuperacao = document.querySelector("#mensagem-recuperacao");
const botaoRecuperacao = document.querySelector("#btn-recuperacao");

function mostrarMensagemRecuperacao(texto, tipo) {
  mensagemRecuperacao.textContent = texto;
  mensagemRecuperacao.dataset.tipo = tipo;
  mensagemRecuperacao.hidden = false;
}

formularioRecuperacao.addEventListener("submit", async (evento) => {
  evento.preventDefault();
  if (!formularioRecuperacao.reportValidity()) return;

  botaoRecuperacao.disabled = true;
  botaoRecuperacao.textContent = "Enviando…";
  mensagemRecuperacao.hidden = true;

  try {
    const resposta = await fetch(formularioRecuperacao.action, {
      method: "POST",
      body: new FormData(formularioRecuperacao),
      credentials: "same-origin",
      cache: "no-store",
      headers: { Accept: "application/json" },
    });
    const dados = await resposta.json();
    if (!resposta.ok || dados?.sucesso !== true) {
      throw new Error(dados?.mensagem || "Não foi possível enviar a solicitação.");
    }
    mostrarMensagemRecuperacao(dados.mensagem, "sucesso");
    campoEmailRecuperacao.value = "";
  } catch (erro) {
    mostrarMensagemRecuperacao(
      erro.message || "Não foi possível conectar ao servidor. Tente novamente.",
      "erro",
    );
  } finally {
    botaoRecuperacao.disabled = false;
    botaoRecuperacao.textContent = "Enviar link →";
  }
});

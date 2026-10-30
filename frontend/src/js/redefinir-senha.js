const formularioNovaSenha = document.querySelector("#form-nova-senha");
const campoNovaSenha = document.querySelector("#nova-senha");
const campoConfirmarSenha = document.querySelector("#confirmar-senha");
const mensagemNovaSenha = document.querySelector("#mensagem-nova-senha");
const botaoNovaSenha = document.querySelector("#btn-nova-senha");
const tokenRecuperacao = new URLSearchParams(window.location.search).get("token") || "";

function mostrarMensagemNovaSenha(texto, tipo) {
  mensagemNovaSenha.textContent = texto;
  mensagemNovaSenha.dataset.tipo = tipo;
  mensagemNovaSenha.hidden = false;
}

if (!/^[a-f0-9]{64}$/i.test(tokenRecuperacao)) {
  mostrarMensagemNovaSenha("Este link é inválido. Solicite uma nova recuperação de senha.", "erro");
  botaoNovaSenha.disabled = true;
}

formularioNovaSenha.addEventListener("submit", async (evento) => {
  evento.preventDefault();
  if (!formularioNovaSenha.reportValidity()) return;
  if (campoNovaSenha.value !== campoConfirmarSenha.value) {
    mostrarMensagemNovaSenha("As senhas não conferem.", "erro");
    campoConfirmarSenha.focus();
    return;
  }

  botaoNovaSenha.disabled = true;
  botaoNovaSenha.textContent = "Salvando…";
  mensagemNovaSenha.hidden = true;

  try {
    const resposta = await fetch("../../../backend/redefinir_senha.php", {
      method: "POST",
      credentials: "same-origin",
      cache: "no-store",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ token: tokenRecuperacao, senha: campoNovaSenha.value }),
    });
    const dados = await resposta.json();
    if (!resposta.ok || dados?.sucesso !== true) {
      throw new Error(dados?.mensagem || "Não foi possível redefinir sua senha.");
    }
    mostrarMensagemNovaSenha(dados.mensagem + " Você será levado ao login em alguns segundos.", "sucesso");
    formularioNovaSenha.reset();
    botaoNovaSenha.disabled = true;
    window.setTimeout(() => window.location.assign("./login.html"), 2500);
  } catch (erro) {
    mostrarMensagemNovaSenha(
      erro.message || "Não foi possível conectar ao servidor. Tente novamente.",
      "erro",
    );
    botaoNovaSenha.disabled = false;
  } finally {
    if (!botaoNovaSenha.disabled) botaoNovaSenha.textContent = "Salvar nova senha →";
  }
});

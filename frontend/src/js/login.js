const formularioLogin = document.querySelector("#form-login");
const mensagemLogin = document.querySelector("#mensagem-login");
const botaoLogin = document.querySelector("#btn-login");

let enviandoLogin = false;

function mostrarMensagemLogin(texto, tipo) {
  mensagemLogin.textContent = texto;
  mensagemLogin.dataset.tipo = tipo;
  mensagemLogin.hidden = false;
}

formularioLogin.addEventListener("submit", async (evento) => {
  // Enviar com JavaScript para permanecer na tela de login.
  evento.preventDefault();

  if (enviandoLogin || !formularioLogin.reportValidity()) {
    return;
  }

  enviandoLogin = true;
  let redirecionando = false;

  botaoLogin.disabled = true;
  botaoLogin.textContent = "Entrando...";
  formularioLogin.setAttribute("aria-busy", "true");

  mostrarMensagemLogin("Verificando seus dados...", "info");

  try {
    const resposta = await fetch(formularioLogin.action, {
      method: "POST",
      body: new FormData(formularioLogin),
      credentials: "same-origin",
      headers: {
        Accept: "application/json"
      }
    });

    const tipoConteudo = resposta.headers.get("content-type") || "";

    if (!tipoConteudo.includes("application/json")) {
      throw new Error(
        "O servidor retornou uma resposta inesperada. Confira o arquivo login.php."
      );
    }

    let dados;

    try {
      dados = await resposta.json();
    } catch {
      throw new Error(
        "Não foi possível ler a resposta do servidor. Tente novamente."
      );
    }

    // Ler a mensagem também quando o PHP retornar 401 ou 422.
    if (!resposta.ok || dados?.sucesso !== true) {
      throw new Error(
        dados?.mensagem || "Não foi possível entrar na conta."
      );
    }

    mostrarMensagemLogin(
      "Login realizado! Abrindo seu painel...",
      "sucesso"
    );

    window.location.replace("./dashboard.html");
    redirecionando = true;

  } catch (erro) {
    const texto = erro instanceof TypeError
      ? "Não foi possível conectar ao servidor. Confira se o PHP está rodando."
      : erro.message;

    mostrarMensagemLogin(texto, "erro");

  } finally {
    formularioLogin.setAttribute("aria-busy", "false");

    if (!redirecionando) {
      enviandoLogin = false;
      botaoLogin.disabled = false;
      botaoLogin.textContent = "Entrar na conta →";
    }
  }
});
const formularioLogin = document.querySelector("#form-login");
const mensagemLogin = document.querySelector("#mensagem-login");
const contadorLogin = document.querySelector("#contador-login");
const botaoLogin = document.querySelector("#btn-login");
const campoEmail = formularioLogin.elements.email;
const campoSenha = formularioLogin.elements.senha;
const erroEmail = document.querySelector("#erro-email");
const erroSenha = document.querySelector("#erro-senha");

let validacaoTentada = false;
let enviandoLogin = false;
let liberarLoginEm = 0;
let temporizadorLogin = null;

function definirErroCampo(campo, elementoErro, texto) {
  elementoErro.textContent = texto;
  elementoErro.hidden = !texto;
  campo.setAttribute("aria-invalid", String(Boolean(texto)));
}

function validarCamposLogin() {
  campoEmail.value = campoEmail.value.trim();

  let emailValido = true;
  let senhaValida = true;

  if (!campoEmail.value) {
    definirErroCampo(campoEmail, erroEmail, "Informe seu e-mail.");
    emailValido = false;
  } else if (campoEmail.validity.typeMismatch) {
    definirErroCampo(campoEmail, erroEmail, "Informe um e-mail válido.");
    emailValido = false;
  } else if (new TextEncoder().encode(campoEmail.value).length > 254) {
    definirErroCampo(
      campoEmail,
      erroEmail,
      "O e-mail excede o limite permitido.",
    );
    emailValido = false;
  } else {
    definirErroCampo(campoEmail, erroEmail, "");
  }

  const tamanhoSenhaBytes = new TextEncoder().encode(campoSenha.value).length;

  if (!campoSenha.value) {
    definirErroCampo(campoSenha, erroSenha, "Informe sua senha.");
    senhaValida = false;
  } else if (tamanhoSenhaBytes > 72) {
    definirErroCampo(
      campoSenha,
      erroSenha,
      "A senha excede o limite aceito. Use até 72 bytes.",
    );
    senhaValida = false;
  } else {
    definirErroCampo(campoSenha, erroSenha, "");
  }

  return emailValido && senhaValida;
}

for (const campo of [campoEmail, campoSenha]) {
  campo.addEventListener("input", () => {
    if (validacaoTentada) validarCamposLogin();
  });
}

function mostrarMensagemLogin(texto, tipo) {
  mensagemLogin.textContent = texto;
  mensagemLogin.dataset.tipo = tipo;
  mensagemLogin.hidden = false;
}

function formatarTempo(segundos) {
  const minutos = Math.floor(segundos / 60);
  const restante = segundos % 60;
  return String(minutos).padStart(2, "0") + ":" + String(restante).padStart(2, "0");
}

function atualizarContagemLogin() {
  const segundos = Math.max(0, Math.ceil((liberarLoginEm - Date.now()) / 1000));
  if (segundos > 0) {

    botaoLogin.disabled = true;
    botaoLogin.textContent = "Aguarde " + formatarTempo(segundos);
    return;
  }

  if (temporizadorLogin !== null) window.clearInterval(temporizadorLogin);
  temporizadorLogin = null;
  liberarLoginEm = 0;
  contadorLogin.hidden = true;
  contadorLogin.textContent = "";
  botaoLogin.disabled = false;
  botaoLogin.textContent = "Entrar na conta →";
  mostrarMensagemLogin("O bloqueio terminou. Você já pode tentar entrar novamente.", "info");
}

function iniciarContagemLogin(segundos) {
  liberarLoginEm = Date.now() + segundos * 1000;
  mostrarMensagemLogin("Muitas tentativas. Aguarde o contador terminar antes de tentar novamente.", "erro");
  if (temporizadorLogin !== null) window.clearInterval(temporizadorLogin);
  atualizarContagemLogin();
  temporizadorLogin = window.setInterval(atualizarContagemLogin, 1000);
}

document.getElementById("btn-cadastro")?.addEventListener("click", () => {
  window.location.href = "./cadastro.html";
});

formularioLogin.addEventListener("submit", async (evento) => {
  evento.preventDefault();

  if (liberarLoginEm > Date.now()) {
    atualizarContagemLogin();
    return;
  }
  if (enviandoLogin) return;

  campoEmail.value = campoEmail.value.trim();

  if (enviandoLogin) return;

  validacaoTentada = true;

  if (!validarCamposLogin()) {
    formularioLogin.querySelector('[aria-invalid="true"]')?.focus();
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
      headers: { Accept: "application/json" },
    });

    const tipoConteudo = resposta.headers.get("content-type") || "";
    if (!tipoConteudo.includes("application/json")) {
      throw new Error(
        "Não foi possível entrar agora. Tente novamente mais tarde.",
      );
    }

    const dados = await resposta.json();
    if (!resposta.ok || dados?.sucesso !== true) {
      const erro = new Error(dados?.mensagem || "Não foi possível entrar na conta.");
      erro.aguardarSegundos = Number(dados?.aguardar_segundos) || 0;
      throw erro;
    }

    const destinosPermitidos = {
      admin: "./tela-adm.html",
      cliente: "./dashboard.html",
    };

    if (
      !Object.prototype.hasOwnProperty.call(destinosPermitidos, dados.area) ||
      dados.destino !== destinosPermitidos[dados.area]
    ) {
      throw new Error("O servidor retornou um destino de acesso inválido.");
    }

    mostrarMensagemLogin("Login realizado! Abrindo seu painel...", "sucesso");
    redirecionando = true;
    window.location.replace(dados.destino);
  } catch (erro) {
    if (erro.aguardarSegundos > 0) {
      iniciarContagemLogin(erro.aguardarSegundos);
    } else {
      const texto =
        erro instanceof TypeError
          ? "Não foi possível conectar ao servidor."
          : erro.message;
      mostrarMensagemLogin(texto, "erro");
    }
  } finally {
    formularioLogin.setAttribute("aria-busy", "false");
    if (!redirecionando) {
      enviandoLogin = false;
      if (liberarLoginEm > Date.now()) {
        atualizarContagemLogin();
      } else {
        botaoLogin.disabled = false;
        botaoLogin.textContent = "Entrar na conta →";
      }
    }
  }
});

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

async function prepararLoginGoogle() {
  const container = document.getElementById("google-login-container");
  if (!container) return;

  try {
    const configUrl = new URL("../../../backend/google_preparar_login.php", location.href);
    const configResponse = await fetch(configUrl, { credentials: "same-origin", headers: { Accept: "application/json" } });
    const config = await configResponse.json();
    if (!configResponse.ok || config.sucesso !== true) return;

    const script = document.createElement("script");
    script.src = "https://accounts.google.com/gsi/client";
    script.async = true;
    script.defer = true;
    await new Promise((resolve, reject) => {
      script.onload = resolve;
      script.onerror = reject;
      document.head.append(script);
    });

    if (!window.google?.accounts?.id || typeof config.client_id !== "string" || !config.client_id) return;

    // Login padrão do Google: só nome e e-mail, sem permissões extras.
    // Telefone, endereço e nascimento ficam no perfil.
    window.google.accounts.id.initialize({
      client_id: config.client_id,
      nonce: config.nonce,
      callback: async ({ credential }) => {
        if (!credential) {
          mostrarMensagemLogin("O Google não retornou uma credencial válida.", "erro");
          return;
        }
        mostrarMensagemLogin("Validando sua conta Google...", "info");
        try {
          const resposta = await fetch(new URL("../../../backend/login_google.php", location.href), {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/json", Accept: "application/json" },
            body: JSON.stringify({ credential, csrf: config.csrf }),
          });
          const dados = await resposta.json();
          if (!resposta.ok || dados.sucesso !== true) throw new Error(dados.mensagem || "Não foi possível entrar com Google.");
          const destinosGoogle = ["./dashboard.html"];
          if (dados.area !== "cliente" || !destinosGoogle.includes(dados.destino)) {
            throw new Error("O servidor retornou um destino de acesso inválido.");
          }
          window.location.replace(dados.destino);
        } catch (erro) {
          mostrarMensagemLogin(erro instanceof TypeError ? "Não foi possível conectar ao servidor." : erro.message, "erro");
        }
      },
    });
    window.google.accounts.id.renderButton(container, {
      type: "standard",
      theme: "outline",
      size: "large",
      text: "continue_with",
      shape: "rectangular",
      width: Math.min(360, Math.max(220, Math.floor(container.clientWidth || 320))),
      logo_alignment: "left",
    });
    container.hidden = false;
    const avisoPolitica = document.getElementById("aviso-politica-google");
    if (avisoPolitica) avisoPolitica.hidden = false;
  } catch (erro) {
    console.error("Não foi possível carregar o login Google:", erro);
  }
}

prepararLoginGoogle();
import {
  mostrarErro,
  validarNome,
  validarEmail,
  validarTelefone,
  validadoresEndereco,
  validarCamposEndereco,
  ativarBuscaCep,
} from "./validacoes.js";

const formCadastro = document.getElementById("form-cadastro");
const nomeCadastro = formCadastro.elements.namedItem("nome");
const sobrenomeCadastro = formCadastro.elements.namedItem("sobrenome");
const emailCadastro = formCadastro.elements.namedItem("email");
const telefoneCadastro = formCadastro.elements.namedItem("telefone");
const nascimento = document.getElementById("data_nascimento");
const senhaCadastro = document.getElementById("senha");
const confirmarSenha = document.getElementById("confirmar_senha");
const mensagem = document.getElementById("mensagem-cadastro");
const botao = formCadastro.querySelector('[type="submit"]');
const textoOriginalBotao = botao.textContent;

const camposEndereco = {
  cep: document.getElementById("cep"),
  logradouro: document.getElementById("logradouro"),
  numero: document.getElementById("numero"),
  complemento: document.getElementById("complemento"),
  bairro: document.getElementById("bairro"),
  cidade: document.getElementById("cidade"),
  uf: document.getElementById("uf"),
};

const statusCep = document.getElementById("status-cep");
const buscaCep = ativarBuscaCep(camposEndereco, statusCep);

let enviandoCadastro = false;
let cadastroConcluido = false;
const cadastroGoogle = new URLSearchParams(location.search).get("google") === "1";
let csrfCadastroGoogle = "";

const hoje = new Date();
nascimento.max = [
  hoje.getFullYear(),
  String(hoje.getMonth() + 1).padStart(2, "0"),
  String(hoje.getDate()).padStart(2, "0"),
].join("-");

formCadastro.noValidate = true;

function exibirMensagem(texto, sucesso = false) {
  mensagem.textContent = texto;
  mensagem.classList.toggle("sucesso", sucesso);
  mensagem.hidden = false;
}

function validarNascimento(campo) {
  if (campo.validity.badInput) {
    return "Informe uma data válida.";
  }

  if (campo.value === "") {
    return "Informe sua data de nascimento.";
  }

  const data = campo.valueAsDate;

  if (!data || Number.isNaN(data.getTime())) {
    return "Informe uma data válida.";
  }

  if (data.getUTCFullYear() < 1000) {
    return "Informe um ano válido, com quatro dígitos.";
  }

  const agora = new Date();
  const limite = Date.UTC(
    agora.getFullYear(),
    agora.getMonth(),
    agora.getDate(),
  );

  if (data.getTime() > limite) {
    return "A data de nascimento não pode estar no futuro.";
  }

  return "";
}

function validarSenhas() {
  const senha = senhaCadastro.value;
  let erroSenha = "";

  if (Array.from(senha).length < 8) {
    erroSenha = "Use uma senha com pelo menos 8 caracteres.";
  } else if (new TextEncoder().encode(senha).length > 72) {
    erroSenha = "A senha ultrapassou o limite permitido. Reduza o tamanho.";
  } else if (senha.includes("\0")) {
    erroSenha = "A senha contém um caractere não permitido.";
  }

  mostrarErro(senhaCadastro, erroSenha);
  mostrarErro(
    confirmarSenha,
    confirmarSenha.value === "" || confirmarSenha.value !== senha
      ? "A confirmação precisa ser igual à senha."
      : "",
  );
}

function validarCadastroNaTela() {
  mostrarErro(nomeCadastro, validarNome(nomeCadastro.value, 74));
  mostrarErro(sobrenomeCadastro, validarNome(sobrenomeCadastro.value, 74));
  mostrarErro(emailCadastro, validarEmail(emailCadastro.value));
  mostrarErro(telefoneCadastro, validarTelefone(telefoneCadastro.value));
  mostrarErro(nascimento, validarNascimento(nascimento));
  if (!cadastroGoogle) validarSenhas();
  validarCamposEndereco(camposEndereco);

  const primeiroInvalido = formCadastro.querySelector(":invalid");

  if (primeiroInvalido) {
    exibirMensagem(primeiroInvalido.validationMessage);
    primeiroInvalido.focus();
    return false;
  }

  return true;
}

function aplicarErrosRecebidos(erros) {
  if (!erros || typeof erros !== "object" || Array.isArray(erros)) {
    return;
  }

  let primeiroCampo = null;

  for (const [nome, texto] of Object.entries(erros)) {
    const campo = formCadastro.elements.namedItem(nome);

    if (!(campo instanceof HTMLInputElement) || typeof texto !== "string") {
      continue;
    }

    mostrarErro(campo, texto);
    if (texto) primeiroCampo ??= campo;
  }

  primeiroCampo?.focus();
}

function acompanharCampo(campo, validar) {
  campo.addEventListener("blur", () => {
    if (!enviandoCadastro && !cadastroConcluido) {
      mostrarErro(campo, validar());
    }
  });
  campo.addEventListener("input", () => {
    if (!cadastroConcluido && campo.getAttribute("aria-invalid") === "true") {
      mostrarErro(campo, validar());
    }
  });
}

acompanharCampo(nomeCadastro, () => validarNome(nomeCadastro.value, 74));
acompanharCampo(sobrenomeCadastro, () =>
  validarNome(sobrenomeCadastro.value, 74),
);
acompanharCampo(emailCadastro, () => validarEmail(emailCadastro.value));
acompanharCampo(telefoneCadastro, () =>
  validarTelefone(telefoneCadastro.value),
);
acompanharCampo(nascimento, () => validarNascimento(nascimento));

for (const [chave, validar] of Object.entries(validadoresEndereco)) {
  acompanharCampo(camposEndereco[chave], () =>
    validar(camposEndereco[chave].value),
  );
}

if (!cadastroGoogle) senhaCadastro.addEventListener("input", validarSenhas);
confirmarSenha.addEventListener("input", validarSenhas);

// Uma nova edição permite iniciar outro cadastro.
formCadastro.addEventListener("input", () => {
  if (cadastroConcluido) {
    cadastroConcluido = false;
    mensagem.hidden = true;
    mensagem.classList.remove("sucesso");
  }
});

formCadastro.addEventListener("submit", async (event) => {
  event.preventDefault();

  if (enviandoCadastro || cadastroConcluido) return;
  if (cadastroGoogle && !csrfCadastroGoogle) {
    exibirMensagem("A etapa de cadastro Google expirou. Volte ao login e tente novamente.");
    return;
  }

  mensagem.hidden = true;
  mensagem.classList.remove("sucesso");

  if (!validarCadastroNaTela()) return;

  enviandoCadastro = true;
  botao.disabled = true;
  botao.textContent = cadastroGoogle ? "Criando sua conta…" : "Enviando confirmação…";
  formCadastro.setAttribute("aria-busy", "true");

  try {
    const dadosFormulario = new FormData(formCadastro);
    const urlCadastro = cadastroGoogle
      ? new URL("../../../backend/cadastro_google.php", location.href)
      : formCadastro.action;
    if (cadastroGoogle) dadosFormulario.set("csrf", csrfCadastroGoogle);
    const resposta = await fetch(urlCadastro, {
      method: "POST",
      credentials: "same-origin",
      body: dadosFormulario,
      headers: { Accept: "application/json" },
    });

    const tipoConteudo = resposta.headers.get("content-type") || "";

    if (!tipoConteudo.includes("application/json")) {
      throw new Error("O servidor não retornou JSON no cadastro.");
    }

    const dados = await resposta.json();

    if (!dados || typeof dados !== "object" || Array.isArray(dados)) {
      throw new Error("O servidor retornou uma resposta inválida.");
    }

    if (!resposta.ok || dados.sucesso !== true) {
      aplicarErrosRecebidos(dados.erros);
      exibirMensagem(
        typeof dados.mensagem === "string"
          ? dados.mensagem
          : "Não foi possível concluir o cadastro.",
      );
      return;
    }

    if (cadastroGoogle) {
      if (dados.area !== "cliente" || dados.destino !== "./dashboard.html") {
        throw new Error("O servidor retornou um destino de acesso inválido.");
      }
      window.location.replace(dados.destino);
      return;
    }

    // O servidor só cria a conta após a confirmação do e-mail.
    cadastroConcluido = true;
    formCadastro.reset();
    buscaCep.cancelar();
    formCadastro.querySelectorAll("input").forEach((campo) => {
      mostrarErro(campo, "");
    });

    exibirMensagem(
      "Enviamos um link de confirmação para seu e-mail. Abra-o em até 24 horas para concluir o cadastro.",
      true,
    );


  } catch (erro) {
    console.error("Falha ao confirmar cadastro:", erro);
    exibirMensagem(
      "Não foi possível confirmar o cadastro. Verifique sua conexão. " +
        "Se o e-mail chegou, abra o link para concluir o cadastro.",
    );
  } finally {
    enviandoCadastro = false;
    botao.disabled = false;
    botao.textContent = textoOriginalBotao;
    formCadastro.setAttribute("aria-busy", "false");
  }
});

async function prepararCadastroGoogle() {
  if (!cadastroGoogle) return;
  const aviso = document.getElementById("aviso-cadastro-google");
  aviso.textContent = "Complete seus dados para criar sua conta de cliente com Google. Sua conta será criada após aceitar a Política de Privacidade.";
  aviso.hidden = false;

  for (const id of ["senha", "confirmar_senha"]) {
    const campo = document.getElementById(id);
    campo.required = false;
    campo.disabled = true;
    campo.closest("div")?.setAttribute("hidden", "");
  }
  document.getElementById("ajuda-senha")?.setAttribute("hidden", "");

  try {
    const resposta = await fetch(new URL("../../../backend/cadastro_google.php", location.href), {
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    });
    const dados = await resposta.json();
    if (!resposta.ok || typeof dados.csrf !== "string" || !dados.google) {
      throw new Error(dados.mensagem || "A etapa de cadastro Google expirou.");
    }
    csrfCadastroGoogle = dados.csrf;
    const nomes = String(dados.google.nome || "").trim().split(/\s+/u).filter(Boolean);
    nomeCadastro.value = nomes.shift() || "";
    sobrenomeCadastro.value = nomes.join(" ");
    emailCadastro.value = String(dados.google.email || "");
    emailCadastro.readOnly = true;
    emailCadastro.setAttribute("aria-readonly", "true");
  } catch (erro) {
    botao.disabled = true;
    exibirMensagem(erro instanceof TypeError ? "Não foi possível conectar ao servidor." : erro.message);
  }
}

prepararCadastroGoogle();
const formCadastro = document.getElementById("form-cadastro");
const senhaCadastro = document.getElementById("senha");
const confirmarSenha = document.getElementById("confirmar_senha");
const nascimento = document.getElementById("data_nascimento");
const mensagem = document.getElementById("mensagem-cadastro");

const hoje = new Date();
nascimento.max = `${hoje.getFullYear()}-${String(hoje.getMonth() + 1).padStart(2, "0")}-${String(hoje.getDate()).padStart(2, "0")}`;

function validarSenhas() {
  const senha = senhaCadastro.value;
  const quantidadeCaracteres = Array.from(senha).length;
  const quantidadeBytes = new TextEncoder().encode(senha).length;

  let erroSenha = "";

  if (quantidadeCaracteres < 8) {
    erroSenha = "Use uma senha com pelo menos 8 caracteres.";
  } else if (quantidadeBytes > 72) {
    erroSenha = "A senha ultrapassou o limite permitido. Reduza o tamanho.";
  } else if (senha.includes("\0")) {
    erroSenha = "A senha contém um caractere não permitido.";
  }

  mostrarErro(senhaCadastro, erroSenha);

  const erroConfirmacao =
    confirmarSenha.value === "" || confirmarSenha.value !== senha
      ? "A confirmação precisa ser igual à senha."
      : "";

  mostrarErro(confirmarSenha, erroConfirmacao);
}

senhaCadastro.addEventListener("input", validarSenhas);
confirmarSenha.addEventListener("input", validarSenhas);

formCadastro.addEventListener("submit", async (event) => {
  event.preventDefault();

  if (!validarCadastroNaTela()) {
    return;
  }

  const nome = formCadastro.elements.namedItem("nome");
  const telefone = formCadastro.elements.namedItem("telefone");

  mostrarErro(nome, validarNome(nome.value));
  mostrarErro(telefone, validarTelefone(telefone.value));

  validarSenhas();

  if (!formCadastro.reportValidity()) {
    return;
  }

  let enviandoCadastro = false;

  const botao = formCadastro.querySelector('[type="submit"]');
  botao.disabled = true;
  botao.textContent = "Criando sua conta…";
  mensagem.hidden = true;
  mensagem.classList.remove("sucesso");
  try {
    const resposta = await fetch(formCadastro.action, {
      method: "POST",
      body: new FormData(formCadastro),
      headers: { Accept: "application/json" },
    });

    if (!resposta.headers.get("content-type")?.includes("application/json")) {
      throw new Error(
        "Não foi possível acessar o cadastro. Abra o site pelo servidor PHP (XAMPP).",
      );
    }

    const dados = await resposta.json();

    if (!resposta.ok || dados?.sucesso !== true) {
      aplicarErrosRecebidos(formCadastro, dados?.erros);

      mensagem.textContent =
        typeof dados?.mensagem === "string"
          ? dados.mensagem
          : "Não foi possível concluir o cadastro.";

      mensagem.hidden = false;
      return;
    }
  } catch (erro) {
    mensagem.textContent =
      "Não foi possível concluir a solicitação. Tente novamente.";

    mensagem.hidden = false;
  } finally {
    enviandoCadastro = false;
    botao.disabled = false;
    formCadastro.setAttribute("aria-busy", "false");
  }
});

// Tratando Falha no envio
function aplicarErrosRecebidos(formulario, erros) {
  if (!erros || typeof erros !== "object" || Array.isArray(erros)) {
    return;
  }

  let primeiroCampo = null;

  for (const [nome, texto] of Object.entries(erros)) {
    const campo = formulario.elements.namedItem(nome);

    if (!(campo instanceof HTMLInputElement)) {
      continue;
    }

    if (typeof texto !== "string") {
      continue;
    }

    mostrarErro(campo, texto);
    primeiroCampo ??= campo;
  }

  primeiroCampo?.focus();
}

// VALIDANDO FORMULÁRIO

function validarNome(valor) {
  const nome = valor.trim(); // remove espaços das extremidades
  const tamanho = Array.from(nome).length;

  if (tamanho < 2 || tamanho > 150) {
    return "Informe um nome entre 2 e 150 caracteres.";
  }

  if (/[\p{C}<>]/u.test(nome)) {
    return "O nome contém caracteres não permitidos.";
  }

  // /[\p{C}<>]/u.test(nome): verifica caracteres rejeitados pelo php.

  return "";
}

function validarTelefone(valor) {
  const telefone = valor.trim();

  if (telefone === "") {
    return "Informe seu telefone com DDD.";
  }

  if (!/^[0-9()\s-]+$/.test(telefone)) {
    return "Use somente números, espaços, parênteses e hífen.";
  }

  const numeros = telefone.replace(/[^0-9]/g, "");

  if (!/^[1-9][0-9]{9,10}$/.test(numeros)) {
    return "Informe o telefone com DDD: 10 ou 11 dígitos.";
  }

  return "";
}

function validarEmail(campo) {
  campo.value = campo.value.trim();

  if (campo.value === "") {
    return "Informe seu e-mail.";
  }

  const tamanhoBytes = new TextEncoder().encode(campo.value).length;

  if (tamanhoBytes > 254) {
    return "O e-mail ultrapassa o tamanho permitido.";
  }

  if (campo.validity.typeMismatch) {
    return "Informe um e-mail válido: Ex: nome@empresa.com";
  }

  return "";

  // typeMissmatch: informa se o valor não corresponde ao tipo do campo
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

  if (data.getUTCFullYear() < 100) {
    return "Informe um ano válido, com quatro dígitos.";
  }

  const agora = new Date();

  // compara o dia
  const hoje = Date.UTC(agora.getFullYear(), agora.getMonth(), agora.getDate());

  if (data.getTime() > hoje) {
    return "A data de nascimento não pode estar no futuro.";
  }

  return "";
}

function validarCadastroNaTela() {
  const campos = formCadastro.elements;

  mostrarErro(
    campos.namedItem("nome"),
    validarNome(campos.namedItem("nome").value),
  );

  mostrarErro(
    campos.namedItem("email"),
    validarEmail(campos.namedItem("email")),
  );

  mostrarErro(
    campos.namedItem("telefone"),
    validarTelefone(campos.namedItem("telefone").value),
  );

  mostrarErro(nascimento, validarNascimento(nascimento));

  validarSenhas();

  const primeiroInvalido = formCadastro.querySelector(":invalid");

  if (primeiroInvalido) {
    primeiroInvalido.focus();
    return false;
  }

  return true;
}

// Apresentando o erro
function mostrarErro(campo, mensagem) {
  const elementoErro = document.getElementById(`erro-${campo.id}`);

  campo.setCustomValidity(mensagem);

  if (mensagem) {
    campo.setAttribute("aria-invalid", "true");
  } else {
    campo.removeAttribute("aria-invalid");
  }

  if (elementoErro) {
    elementoErro.textContent = mensagem;
  }
}

formCadastro.noValidate = true;

// tratar erros em tempo real
function acompanharCampo(campo, validar) {
  campo.addEventListener("blur", () => {
    mostrarErro(campo, validar());
  });

  campo.addEventListener("input", () => {
    if (campo.getAttribute("aria-invalid") === true) {
      mostrarErro(campo, validar());
    }
  });
}

const emailCadastro = formCadastro.elements.namedItem("email");
const telefoneCadastro = formCadastro.elements.namedItem("telefone");

acompanharCampo(emailCadastro, () => validarEmail(emailCadastro));

acompanharCampo(telefoneCadastro, () =>
  validarTelefone(telefoneCadastro.value),
);

acompanharCampo(nascimento, () => validarNascimento(nascimento));

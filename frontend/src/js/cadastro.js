const formCadastro = document.getElementById("form-cadastro");
const nomeCadastro = formCadastro.elements.namedItem("nome");
const emailCadastro = formCadastro.elements.namedItem("email");
const telefoneCadastro = formCadastro.elements.namedItem("telefone");
const nascimento = document.getElementById("data_nascimento");
const senhaCadastro = document.getElementById("senha");
const confirmarSenha = document.getElementById("confirmar_senha");
// endereco
const sobrenomeCadastro = formCadastro.elements.namedItem("sobrenome");
const cepCadastro = document.getElementById("cep");
const logradouroCadastro = document.getElementById("logradouro");
const numeroCadastro = document.getElementById("numero");
const complementoCadastro = document.getElementById("complemento");
const bairroCadastro = document.getElementById("bairro");
const cidadeCadastro = document.getElementById("cidade");
const ufCadastro = document.getElementById("uf");
const statusCep = document.getElementById("status-cep");
const mensagem = document.getElementById("mensagem-cadastro");
const botao = formCadastro.querySelector('[type="submit"]');
const textoOriginalBotao = botao.textContent;

let consultaCepAtual = 0;
let enviandoCadastro = false;
let cadastroConcluido = false;

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

function mostrarErro(campo, texto) {
  const elementoErro = document.getElementById(`erro-${campo.id}`);
  campo.setCustomValidity(texto);

  if (texto) {
    campo.setAttribute("aria-invalid", "true");
  } else {
    campo.removeAttribute("aria-invalid");
  }

  if (elementoErro) {
    elementoErro.textContent = texto;
  }
}

function validarNome(valor) {
  const nome = valor.trim();
  const tamanho = Array.from(nome).length;

  if (tamanho < 2 || tamanho > 74) {
    return "Informe de 2 a 74 caracteres.";
  }

  if (/[\p{C}<>]/u.test(nome)) {
    return "Há caracteres não permitidos.";
  }

  return "";
}

function validarEmail(campo) {
  campo.value = campo.value.trim();

  if (campo.value === "") {
    return "Informe seu e-mail.";
  }

  if (new TextEncoder().encode(campo.value).length > 254) {
    return "O e-mail ultrapassa o tamanho permitido.";
  }

  if (campo.validity.typeMismatch) {
    return "Informe um e-mail válido. Ex.: nome@empresa.com";
  }

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

function validarCep(valor) {
  if (!/^[0-9]{5}-?[0-9]{3}$/.test(valor.trim())) {
    return "Informe um CEP válido com 8 dígitos.";
  }

  return "";
}

function validarTextoEndereco(valor, rotulo, min, max, obrigatorio) {
  const texto = valor.trim();

  if (texto === "") {
    return obrigatorio ? `Informe ${rotulo}.` : "";
  }

  const tamanho = Array.from(texto).length;

  if (tamanho < min || tamanho > max || /[\p{C}<>]/u.test(texto)) {
    return `Informe ${rotulo} com ${min} a ${max} caracteres, sem < ou >.`;
  }

  return "";
}

// O servidor confere a lista completa das 27 siglas; aqui só o formato.
function validarUf(valor) {
  return /^[A-Za-z]{2}$/.test(valor.trim())
    ? ""
    : "Informe a sigla do estado (ex.: SP).";
}

function formatarCep(valor) {
  const digitos = valor.replace(/\D/g, "").slice(0, 8);

  return digitos.length > 5
    ? `${digitos.slice(0, 5)}-${digitos.slice(5)}`
    : digitos;
}

async function buscarCep() {
  const cep = cepCadastro.value.replace(/\D/g, "");

  if (cep.length !== 8) {
    return;
  }

  const consulta = ++consultaCepAtual;
  statusCep.textContent = "Buscando endereço…";

  try {
    const resposta = await fetch(`https://viacep.com.br/ws/${cep}/json/`, {
      signal: AbortSignal.timeout(6000),
    });

    if (!resposta.ok) {
      throw new Error(`ViaCEP respondeu ${resposta.status}`);
    }

    const dados = await resposta.json();

    // Começou uma busca mais nova: esta resposta chegou atrasada.
    if (consulta !== consultaCepAtual) {
      return;
    }

    // CEP com formato válido, mas que não existe.
    if (dados.erro) {
      statusCep.textContent =
        "CEP não encontrado. Preencha o endereço manualmente.";
      return;
    }

    const recebidos = [
      [logradouroCadastro, dados.logradouro],
      [bairroCadastro, dados.bairro],
      [cidadeCadastro, dados.localidade],
      [ufCadastro, dados.uf],
    ];

    for (const [campo, valor] of recebidos) {
      if (typeof valor === "string" && valor !== "") {
        campo.value = valor;
        mostrarErro(campo, "");
      }
    }

    statusCep.textContent =
      "Endereço preenchido. Confira os dados e informe o número.";

    // Só move o foco se a pessoa ainda estiver no CEP.
    if (document.activeElement === cepCadastro) {
      (logradouroCadastro.value === ""
        ? logradouroCadastro
        : numeroCadastro
      ).focus();
    }
  } catch (erro) {
    if (consulta !== consultaCepAtual) {
      return;
    }

    console.error("Falha ao buscar o CEP:", erro);
    statusCep.textContent =
      "Não foi possível buscar o CEP agora. Preencha o endereço manualmente.";
  }
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
  mostrarErro(nomeCadastro, validarNome(nomeCadastro.value));
  mostrarErro(sobrenomeCadastro, validarNome(sobrenomeCadastro.value));
  mostrarErro(emailCadastro, validarEmail(emailCadastro));
  mostrarErro(telefoneCadastro, validarTelefone(telefoneCadastro.value));
  mostrarErro(nascimento, validarNascimento(nascimento));
  validarSenhas();

  mostrarErro(cepCadastro, validarCep(cepCadastro.value));
  mostrarErro(
    logradouroCadastro,
    validarTextoEndereco(
      logradouroCadastro.value,
      "o logradouro",
      2,
      180,
      true,
    ),
  );
  mostrarErro(
    numeroCadastro,
    validarTextoEndereco(numeroCadastro.value, "o número", 1, 20, true),
  );
  mostrarErro(
    complementoCadastro,
    validarTextoEndereco(
      complementoCadastro.value,
      "o complemento",
      1,
      120,
      false,
    ),
  );
  mostrarErro(
    bairroCadastro,
    validarTextoEndereco(bairroCadastro.value, "o bairro", 2, 100, true),
  );
  mostrarErro(
    cidadeCadastro,
    validarTextoEndereco(cidadeCadastro.value, "a cidade", 2, 100, true),
  );
  mostrarErro(ufCadastro, validarUf(ufCadastro.value));

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

acompanharCampo(nomeCadastro, () => validarNome(nomeCadastro.value));
acompanharCampo(emailCadastro, () => validarEmail(emailCadastro));
acompanharCampo(telefoneCadastro, () =>
  validarTelefone(telefoneCadastro.value),
);
acompanharCampo(nascimento, () => validarNascimento(nascimento));
acompanharCampo(sobrenomeCadastro, () => validarNome(sobrenomeCadastro.value));
acompanharCampo(cepCadastro, () => validarCep(cepCadastro.value));
acompanharCampo(logradouroCadastro, () =>
  validarTextoEndereco(logradouroCadastro.value, "o logradouro", 2, 180, true),
);
acompanharCampo(numeroCadastro, () =>
  validarTextoEndereco(numeroCadastro.value, "o número", 1, 20, true),
);
acompanharCampo(complementoCadastro, () =>
  validarTextoEndereco(
    complementoCadastro.value,
    "o complemento",
    1,
    120,
    false,
  ),
);
acompanharCampo(bairroCadastro, () =>
  validarTextoEndereco(bairroCadastro.value, "o bairro", 2, 100, true),
);
acompanharCampo(cidadeCadastro, () =>
  validarTextoEndereco(cidadeCadastro.value, "a cidade", 2, 100, true),
);
acompanharCampo(ufCadastro, () => validarUf(ufCadastro.value));

cepCadastro.addEventListener("input", () => {
  cepCadastro.value = formatarCep(cepCadastro.value);

  if (cepCadastro.value.replace(/\D/g, "").length === 8) {
    buscarCep();
  } else {
    // CEP incompleto: cancela qualquer resposta pendente.
    consultaCepAtual++;
    statusCep.textContent = "";
  }
});

ufCadastro.addEventListener("input", () => {
  ufCadastro.value = ufCadastro.value.toUpperCase();
});

senhaCadastro.addEventListener("input", validarSenhas);
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

  mensagem.hidden = true;
  mensagem.classList.remove("sucesso");

  if (!validarCadastroNaTela()) return;

  enviandoCadastro = true;
  botao.disabled = true;
  botao.textContent = "Criando sua conta…";
  formCadastro.setAttribute("aria-busy", "true");

  try {
    const resposta = await fetch(formCadastro.action, {
      method: "POST",
      credentials: "same-origin",
      body: new FormData(formCadastro),
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

    // Só confirma depois que o PHP informa que salvou a conta.
    cadastroConcluido = true;
    formCadastro.reset();
    statusCep.textContent = "";
    consultaCepAtual++;

    formCadastro.querySelectorAll("input").forEach((campo) => {
      mostrarErro(campo, "");
    });

    exibirMensagem(
      "Cadastro realizado com sucesso! Clique em Entrar na conta abaixo.",
      true,
    );

    formCadastro.querySelector('a[href="./login.html"]')?.focus();
  } catch (erro) {
    console.error("Falha ao confirmar cadastro:", erro);
    exibirMensagem(
      "Não foi possível confirmar o cadastro. Verifique sua conexão. " +
        "Se você já enviou seus dados, tente entrar na conta antes de cadastrar novamente.",
    );
  } finally {
    enviandoCadastro = false;
    botao.disabled = false;
    botao.textContent = textoOriginalBotao;
    formCadastro.setAttribute("aria-busy", "false");
  }
});

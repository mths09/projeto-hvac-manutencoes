// formulario
const formularioPerfil = document.querySelector("#form-perfil");
// aviso e botao
const avisoSalvamento = document.querySelector("#aviso-salvamento");
const botaoSalvar = formularioPerfil.querySelector('[type="submit"]');
// campos
const campoNome = document.querySelector("#nome");
const campoEmail = document.querySelector("#email");
const campoTelefone = document.querySelector("#telefone");
const campoCep = document.querySelector("#cep");
const campoNumero = document.querySelector("#numero");
const campoLogradouro = document.querySelector("#logradouro");
const campoComplemento = document.querySelector("#complemento");
const campoBairro = document.querySelector("#bairro");
const campoCidade = document.querySelector("#cidade");
const campoUf = document.querySelector("#uf");
const statusCep = document.querySelector("#status-cep");

// dialogo
const dialogoSucesso = document.querySelector("#dialogo-sucesso");
const botaoContinuarEditando = document.querySelector("#continuar-editando");

// chave usada em "erros" pelo servidor
const camposPorNome = {
  nome: campoNome,
  email: campoEmail,
  telefone: campoTelefone,
  cep: campoCep,
  numero: campoNumero,
  logradouro: campoLogradouro,
  complemento: campoComplemento,
  bairro: campoBairro,
  cidade: campoCidade,
  uf: campoUf,
};

let perfilCarregado = false;
let salvando = false;
let consultaCepAtual = 0;

// erro por campo
function mostrarErro(campo, mensagem) {
  const aviso = document.querySelector(`#erro-${campo.id}`);

  // submit usa :invalid para achar o primeiro campo com problema
  campo.setCustomValidity(mensagem);
  campo.setAttribute("aria-invalid", mensagem ? "true" : "false");

  if (aviso) {
    aviso.textContent = mensagem;
  }
}

// validações
function validarNome(valor) {
  const nome = valor.trim().replace(/\s+/g, " ");
  const tamanho = Array.from(nome).length;

  if (tamanho < 2 || tamanho > 150) {
    return "O nome deve ter de 2 a 150 caracteres.";
  }

  if (/[\p{C}<>]/u.test(nome)) {
    return "O nome não pode conter < ou >.";
  }

  return "";
}

function validarTelefone(valor) {
  const entrada = valor.trim();
  const digitos = entrada.replace(/\D/g, "");

  if (!/^[0-9()\s.-]+$/.test(entrada) || !/^[1-9][0-9]{9,10}$/.test(digitos)) {
    return "Informe um telefone válido com DDD.";
  }

  return "";
}

function validarCep(valor) {
  if (!/^[0-9]{5}-?[0-9]{3}$/.test(valor.trim())) {
    return "Informe um CEP válido com 8 dígitos.";
  }

  return "";
}

function validarTextoEndereco(valor, rotuno, min, max, obrigatorio) {
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

// confere só o formato, não as 27 siglas
function validarUf(valor) {
  return /^[A-Za-z]{2}$/.test(valor.trim())
    ? ""
    : "Informe a sigla do estado (ex.: SP).";
}

function validarFormulario() {
  mostrarErro(campoNome, validarNome(campoNome.value));
  mostrarErro(campoEmail, validarEmail(campoEmail.value));
  mostrarErro(campoTelefone, validarTelefone(campoTelefone.value));
  mostrarErro(campoCep, validarCep(campoCep.value));
  mostrarErro(
    campoLogradouro,
    validarTextoEndereco(campoLogradouro.value, "o logradouro", 2, 180, true),
  );
  mostrarErro(
    campoNumero,
    validarTextoEndereco(campoNumero.value, "o número", 1, 20, true),
  );
  mostrarErro(
    campoComplemento,
    validarTextoEndereco(
      campoComplemento.value,
      "o complemento",
      1,
      120,
      false,
    ),
  );
  mostrarErro(
    campoBairro,
    validarTextoEndereco(campoBairro.value, "o bairro", 2, 100, true),
  );
  mostrarErro(
    campoCidade,
    validarTextoEndereco(campoCidade.value, "a cidade", 2, 100, true),
  );
  mostrarErro(campoUf, validarUf(campoUf.value));
}

// formatação
function formatarTelefone(valor) {
  const d = String(valor || "")
    .replace(/\D/g, "")
    .slice(0, 11);

  if (d.length <= 2) return d;
  if (d.length <= 6) return `(${d.slice(0, 2)}) ${d.slice(2)}`;
  if (d.length <= 10) {
    return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;
  }

  return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
}

function formatarCep(valor) {
  const digitos = String(valor || "")
    .replace(/\D/g, "")
    .slice(0, 8);

  return digitos.length > 5
    ? `${digitos.slice(0, 5)}-${digitos.slice(5)}`
    : digitos;
}

function textoClienteDesde(criadoEm) {
  const data = String(criadoEm || "").slice(0, 10);

  if (!/^\d{4}-\d{2}-\d{2}$/.test(data)) {
    return "";
  }

  const [ano, mes, dia] = data.split("-");
  return `Cliente desde ${dia}/${mes}/${ano}`;
}

const textoSeguro = (valor) => (typeof valor === "string" ? valor : "");

// buscar CEP
async function buscarCep() {
  const cep = campoCep.value.replace(/\D/g, "");

  if (cep.length !== 8) {
    return;
  }

  const consulta = ++consultaCepAtual;
  statusCep.textContent = "Buscando endereço...";
  setTimeout(5000);

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

    if (dados.erro) {
      statusCep.textContent =
        "CEP não encontrado. Preencha o endereço manualmente.";
      return;
    }

    const recebidos = [
      [campoLogradouro, dados.logradouro],
      [campoBairro, dados.bairro],
      [campoCidade, dados.localidade],
      [campoUf, dados.uf],
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
    if (document.activeElement === campoCep) {
      (campoLogradouro.value === "" ? campoLogradouro : campoNumero).focus();
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

// preencher a tela
function preencherCampos(perfil) {
  const endereco =
    perfil.endereco && typeof perfil.endereco === "object"
      ? perfil.endereco
      : {};

  campoNome.value = textoSeguro(perfil.nome);
  campoEmail.value = textoSeguro(perfil.email);
  campoTelefone.value = formatarTelefone(perfil.telefone);

  campoCep.value = formatarCep(textoSeguro(endereco.cep));
  campoLogradouro.value = textoSeguro(endereco.logradouro);
  campoNumero.value = textoSeguro(endereco.numero);
  campoComplemento.value = textoSeguro(endereco.complemento);
  campoBairro.value = textoSeguro(endereco.bairro);
  campoCidade.value = textoSeguro(endereco.cidade);
  campoUf.value = textoSeguro(endereco.uf);

  document.querySelector(".resumo-perfil h2").textContent =
    perfil.nome || "Cliente";

  document.querySelector(".resumo-perfil .avatar-perfil").textContent =
    obterIniciais(perfil.nome);
}

async function preencherFormulario() {
  if (dialogoSucesso.open) {
    dialogoSucesso.close();
  }

  perfilCarregado = false;
  salvando = false;
  consultaCepAtual++;
  statusCep.textContent = "";
  botaoSalvar.disabled = true;
  formularioPerfil.setAttribute("aria-busy", "true");
  avisoSalvamento.textContent = "Carregando seus dados…";

  try {
    const perfil = await carregarPerfil();

    preencherCampos(perfil);

    for (const campo of Object.values(camposPorNome)) {
      mostrarErro(campo, "");
    }

    const tempoCliente = document.querySelector(".tempo-cliente");

    if (tempoCliente) {
      tempoCliente.textContent = textoClienteDesde(perfil.criado_em);
    }

    perfilCarregado = true;
    botaoSalvar.disabled = false;

    // Quem ainda não tem endereço precisa cadastrá-lo ao salvar.
    avisoSalvamento.textContent =
      campoCep.value === ""
        ? "Você ainda não tem um endereço cadastrado. Preencha os campos de endereço e salve."
        : "";
  } catch (erro) {
    avisoSalvamento.textContent =
      "Não foi possível carregar seu perfil. Recarregue a página para tentar novamente.";

    console.error(erro);
  } finally {
    formularioPerfil.setAttribute("aria-busy", "false");
  }
}

// ===== Erros vindos do servidor =====
// Mostra cada erro no seu campo e devolve os que não têm campo.
function aplicarErrosDoServidor(erros) {
  let primeiro = null;
  const semCampo = [];

  for (const [chave, texto] of Object.entries(erros)) {
    if (typeof texto !== "string") {
      continue;
    }

    const campo = camposPorNome[chave];

    if (campo) {
      mostrarErro(campo, texto);
      primeiro ??= campo;
    } else {
      semCampo.push(texto);
    }
  }

  primeiro?.focus();

  return semCampo;
}
function tratarErroAoSalvar(erro) {
  // Sem status = a requisição nem chegou ao servidor.
  if (erro.status === undefined) {
    avisoSalvamento.textContent =
      "Não foi possível conectar. Verifique sua internet e tente novamente.";
    return;
  }

  switch (erro.status) {
    case 401:
      return;
    case 403:
      avisoSalvamento.textContent =
        "Sua sessão expirou. Clique em salvar novamente para renová-la.";
      return;
    case 409:
    case 422: {
      const semCampo = erro.erros
        ? aplicarErrosDoServidor(erro.erros)
        : [erro.message];

      avisoSalvamento.textContent = semCampo.length
        ? semCampo.join(" ")
        : "Confira os campos destacados.";
      return;
    }

    default: {
      const codigo = erro.referencia ? ` (código: ${erro.referencia})` : "";

      avisoSalvamento.textContent =
        "Ocorreu um erro no servidor. Seus dados continuam no formulário, tente novamente." +
        codigo;
    }
  }
}

// envio
formularioPerfil.addEventListener("submit", async (evento) => {
  evento.preventDefault();

  if (!perfilCarregado || salvando) {
    return;
  }

  const perfilAtualizado = {
    nome: campoNome.value.trim(),
    email: campoEmail.value.trim(),
    telefone: campoTelefone.value.trim(),
    endereco: {
      cep: campoCep.value.trim(),
      logradouro: campoLogradouro.value.trim(),
      numero: campoNumero.value.trim(),
      complemento: campoComplemento.value.trim(),
      bairro: campoBairro.value.trim(),
      cidade: campoCidade.value.trim(),
      uf: campoUf.value.trim(),
    },
  };

  salvando = true;
  botaoSalvar.disabled = true;
  formularioPerfil.setAttribute("aria-busy", "true");
  avisoSalvamento.textContent = "Salvando Perfil...";

  try {
    const usuarioSalvo = await salvarPerfil(perfilAtualizado);

    preencherCampos(usuarioSalvo);
    avisoSalvamento.textContent = "";
    dialogoSucesso.showModal();
  } catch (erro) {
    console.error("Erro ao salvar o perfil.", erro);
    tratarErroAoSalvar(erro);
  } finally {
    salvando = false;
    botaoSalvar.disabled = false;
    formularioPerfil.setAttribute("aria-busy", "false");
  }
});

// eventos dos campos
// ao digitar, o erro some
for (const campo of Object.values(camposPorNome)) {
  campo.addEventListener("input", () => {
    mostrarErro(campo, "");
  });
}

campoCep.addEventListener("input", () => {
  campoCep.value = formatarCep(campoCep.value);

  if (campoCep.value.replace(/\D/g, "").length === 8) {
    buscarCep();
  } else {
    consultaCepAtual++;
    statusCep.textContent = "";
  }
});

campoUf.addEventListener("input", () => {
  campoUf.value = campoUf.value.toUpperCase();
});

botaoContinuarEditando.addEventListener("click", () => {
  dialogoSucesso.close();
});

preencherFormulario();

window.addEventListener("pageshow", (evento) => {
  if (evento.persisted) {
    preencherFormulario();
  }
});

// formulario
const formularioPerfil = document.querySelector("#form-perfil");
// aviso e botao
const avisoSalvamento = document.querySelector("#aviso-salvamento");
const botaoSalvar = formularioPerfil.querySelector('[type="submit"]');
// campos
const campoNome = document.querySelector("#nome");
const campoEmail = document.querySelector("#email");
const campoTelefone = document.querySelector("#telefone");
const campoEndereco = document.querySelector("#endereco");

// dialogo
const dialogoSucesso = document.querySelector("#dialogo-sucesso");
const botaoContinuarEditando = document.querySelector("#continuar-editando");

let perfilCarregado = false;
let salvando = false;

// regras do servidor
function validarEndereco(valor) {
  const endereco = valor.trim();

  if (endereco === "") {
    return "";
  }

  const tamanho = Array.from(endereco).length;

  if (tamanho < 10 || tamanho > 500) {
    return "O endereço deve ter de 10 a 500 caracteres ou ficar vazio.";
  }

  if (/[<>]/.test(endereco)) {
    return "O endereço não pode conter os símbolos < ou >.";
  }

  return "";
}

function mostrarErro(campo, mensagem) {
  const aviso = document.querySelector(`#erro-${campo.id}`);

  // :invalid para achar o primeiro campo com problema
  campo.setCustomValidity(mensagem);
  campo.setAttribute("aria-invalid", mensagem ? "true" : "false");

  if (aviso) {
    aviso.textContent = mensagem;
  }
}

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

function validarEmail(valor) {
  const email = valor.trim();

  if (email.length > 254 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    return "Informe um e-mail válido.";
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

// montar mascara em cima de resposta do servidor
function formatarTelefone(valor) {
  const d = String(valor || "")
    .replace(/\D/g, "")
    .slice(0, 11);

  if (d.length <= 2) return d;
  if (d.length <= 6) return `(${d.slice(0, 2)}) ${d.slice(2)}`;
  if (d.length <= 10)
    return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;

  return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
}

function textoClienteDesde(criadoEm) {
  const data = String(criadoEm || "").slice(0, 10);
  if (!/^\d{4}-\d{2}-\d{2}$/.test(data)) {
    return "";
  }

  const [ano, mes, dia] = data.split("-");

  return `Cliente desde ${dia}/${mes}/${ano}`;
}

function preencherCampos(perfil) {
  campoNome.value = perfil.nome;
  campoEmail.value = perfil.email;
  campoTelefone.value = formatarTelefone(perfil.telefone);
  campoEndereco.value = perfil.endereco;

  document.querySelector(".resumo-perfil h2").textContent =
    perfil.nome || "Cliente";

  document.querySelector(".resumo-perfil .avatar-perfil").textContent =
    obterIniciais(perfil.nome);
}

async function preencherFormulario() {
  perfilCarregado = false;
  salvando = false;
  botaoSalvar.disabled = true;
  formularioPerfil.setAttribute("aria-busy", "true");
  avisoSalvamento.textContent = "Carregando seus dados…";

  try {
    const perfil = await carregarPerfil();

    preencherCampos(perfil);

    document.querySelector(".resumo-perfil h2").textContent =
      perfil.nome || "Cliente";

    document.querySelector(".resumo-perfil .avatar-perfil").textContent =
      obterIniciais(perfil.nome);

    const tempoCliente = document.querySelector(".tempo-cliente");
    if (tempoCliente) {
      tempoCliente.textContent = textoClienteDesde(perfil.criado_em);
    }

    perfilCarregado = true;
    botaoSalvar.disabled = false;
    avisoSalvamento.textContent = "";
  } catch (erro) {
    avisoSalvamento.textContent =
      "Não foi possível carregar seu perfil. Recarregue a página para tentar novamente.";
    console.error(erro);
  } finally {
    formularioPerfil.setAttribute("aria-busy", "false");
  }
}

function tratarErroAoSalvar(erro) {
  // sem status, requisição não chegou ao servidor
  if (erro.status === undefined) {
    avisoSalvamento.textContent =
      "Não foi possível conectar. Verifique sua internet e tente novamente.";
    return;
  }

  switch (erro.status) {
    case 401:
      // redireciona ao login
      return;
    case 403:
      avisoSalvamento.textContent =
        "Sua sessão expirou. Clique em salvar novamente para renová-la.";
      return;
    case 409:
      mostrarErro(campoEmail, erro.message);
      avisoSalvamento.textContent = "Confira o campo destacado.";
      campoEmail.focus();
      return;
    case 422:
      avisoSalvamento.textContent = erro.message;
      return;

    default: {
      const codigo = erro.referencia ? `(código: ${erro.referencia})` : "";

      avisoSalvamento.textContent =
        "Ocorreu um erro no servidor. Seus dados continuam no formulário, tente novamente." +
        codigo;
    }
  }
}

formularioPerfil.addEventListener("submit", async (evento) => {
  evento.preventDefault();

  if (!perfilCarregado || salvando) return;

  mostrarErro(campoNome, validarNome(campoNome.value));
  mostrarErro(campoEmail, validarEmail(campoEmail.value));
  mostrarErro(campoTelefone, validarTelefone(campoTelefone.value));
  mostrarErro(campoEndereco, validarEndereco(campoEndereco.value));

  const primeiroInvalido = formularioPerfil.querySelector(":invalid");

  if (primeiroInvalido) {
    avisoSalvamento.textContent = "Confira os campos destacados.";
    primeiroInvalido.focus();
    return;
  }

  const perfilAtualizado = {
    nome: campoNome.value.trim(),
    email: campoEmail.value.trim(),
    telefone: campoTelefone.value.trim(),
    endereco: campoEndereco.value.trim(),
  };

  salvando = true;
  botaoSalvar.disabled = true;
  formularioPerfil.setAttribute("aria-busy", "true");
  avisoSalvamento.textContent = "Salvando perfil…";

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

for (const campo of [campoNome, campoEmail, campoTelefone, campoEndereco]) {
  campo.addEventListener("input", () => {
    mostrarErro(campo, "");
  });
}

botaoContinuarEditando.addEventListener("click", () => {
  dialogoSucesso.close();
});

preencherFormulario();
// atualiza no botão do navegador
window.addEventListener("pageshow", (evento) => {
  if (evento.persisted) preencherFormulario();
});

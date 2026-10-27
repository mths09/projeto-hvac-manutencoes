import {
  mostrarErro,
  validarNome,
  validarEmail,
  validarTelefone,
  validarCamposEndereco,
  formatarTelefone,
  formatarCep,
  ativarBuscaCep,
} from "./validacoes.js";

// ===== Elementos =====
const formularioPerfil = document.querySelector("#form-perfil");
const avisoSalvamento = document.querySelector("#aviso-salvamento");
const botaoSalvar = formularioPerfil.querySelector('[type="submit"]');

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

const dialogoSucesso = document.querySelector("#dialogo-sucesso");
const botaoContinuarEditando = document.querySelector("#continuar-editando");

const camposEndereco = {
  cep: campoCep,
  logradouro: campoLogradouro,
  numero: campoNumero,
  complemento: campoComplemento,
  bairro: campoBairro,
  cidade: campoCidade,
  uf: campoUf,
};

// Chave que o servidor usa em "erros" => campo da tela.
const camposPorNome = {
  nome: campoNome,
  email: campoEmail,
  telefone: campoTelefone,
  ...camposEndereco,
};

const buscaCep = ativarBuscaCep(camposEndereco, statusCep);

let perfilCarregado = false;
let salvando = false;

function validarFormulario() {
  mostrarErro(campoNome, validarNome(campoNome.value));
  mostrarErro(campoEmail, validarEmail(campoEmail.value));
  mostrarErro(campoTelefone, validarTelefone(campoTelefone.value));
  validarCamposEndereco(camposEndereco);
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

// ===== Preencher a tela =====
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
  buscaCep.cancelar();
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
      // Já estamos sendo redirecionados para o login.
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

// ===== Envio =====
formularioPerfil.addEventListener("submit", async (evento) => {
  evento.preventDefault();

  if (!perfilCarregado || salvando) {
    return;
  }

  validarFormulario();

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

// Ao digitar em um campo, o erro dele some.
for (const campo of Object.values(camposPorNome)) {
  campo.addEventListener("input", () => {
    mostrarErro(campo, "");
  });
}

botaoContinuarEditando.addEventListener("click", () => {
  dialogoSucesso.close();
});

preencherFormulario();

// Atualiza quando o usuário volta pelo botão do navegador.
window.addEventListener("pageshow", (evento) => {
  if (evento.persisted) {
    preencherFormulario();
  }
});

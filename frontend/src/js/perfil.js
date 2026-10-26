const PERFIL_API = "../../../backend/perfil_cliente.php";
const ATUALIZAR_PERFIL_API = "../../../backend/atualizar_perfil.php";

let csrfPerfil = "";

async function carregarPerfil() {
  const resposta = await fetch(PERFIL_API, {
    credentials: "same-origin",
    cache: "no-store",
    headers: { Accept: "application/json" },
  });
  const dados = await resposta.json();
  if (resposta.status === 401) {
    window.location.replace("./login.html");
    throw new Error("Entre na sua conta novamente.");
  }
  if (
    !resposta.ok ||
    dados?.sucesso !== true ||
    !dados.usuario ||
    typeof dados.csrf !== "string"
  ) {
    throw new Error(dados?.mensagem || "Não foi possível carregar o perfil.");
  }
  csrfPerfil = dados.csrf;
  return dados.usuario;
}

async function salvarPerfil(perfil) {
  if (!csrfPerfil) {
    await carregarPerfil();
  }

  const resposta = await fetch(ATUALIZAR_PERFIL_API, {
    method: "PATCH",
    credentials: "same-origin",
    cache: "no-store",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      nome: perfil.nome,
      email: perfil.email,
      telefone: perfil.telefone,
      endereco: perfil.endereco,
      csrf: csrfPerfil,
    }),
  });

  let dados = null;

  try {
    dados = await resposta.json();
  } catch {
    // tratado pelo status
  }

  if (resposta.status === 401) {
    window.location.replace("../paginas/login.html");
  }

  if (resposta.status === 403) {
    csrfPerfil = "";
  }

  if (!resposta.ok || dados?.sucesso !== true) {
    const erro = new Error(
      dados?.mensagem || "Não foi possível salvar o perfil.",
    );
    erro.status = resposta.status;
    erro.referencia = dados?.referencia ?? null;
    erro.erros = dados?.erros ?? null;
    throw erro;
  }

  return dados.usuario;
}

function obterIniciais(nome) {
  const partes = String(nome || "")
    .trim()
    .split(/\s+/)
    .filter(Boolean);
  if (partes.length === 0) return "?";
  return `${partes[0][0]}${partes.length > 1 ? partes[partes.length - 1][0] : ""}`.toUpperCase();
}

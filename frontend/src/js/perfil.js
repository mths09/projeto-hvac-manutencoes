const PERFIL_API = "../../../backend/perfil_cliente.php";
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
  if (!resposta.ok || dados?.sucesso !== true || !dados.usuario || typeof dados.csrf !== "string") {
    throw new Error(dados?.mensagem || "Não foi possível carregar o perfil.");
  }
  csrfPerfil = dados.csrf;
  return dados.usuario;
}

async function salvarPerfil(perfil) {
  if (!csrfPerfil) await carregarPerfil();
  const formulario = new FormData();
  formulario.set("csrf", csrfPerfil);
  for (const campo of ["nome", "email", "telefone", "endereco"]) {
    formulario.set(campo, String(perfil[campo] || ""));
  }

  const resposta = await fetch(PERFIL_API, {
    method: "POST",
    credentials: "same-origin",
    cache: "no-store",
    headers: { Accept: "application/json" },
    body: formulario,
  });
  const dados = await resposta.json();
  if (resposta.status === 401) {
    window.location.replace("./login.html");
    throw new Error("Entre na sua conta novamente.");
  }
  if (!resposta.ok || dados?.sucesso !== true) {
    throw new Error(dados?.mensagem || "Não foi possível salvar o perfil.");
  }
  if (typeof dados.csrf === "string") csrfPerfil = dados.csrf;
  return dados.usuario;
}

function obterIniciais(nome) {
  const partes = String(nome || "").trim().split(/\s+/).filter(Boolean);
  if (partes.length === 0) return "?";
  return `${partes[0][0]}${partes.length > 1 ? partes[partes.length - 1][0] : ""}`.toUpperCase();
}

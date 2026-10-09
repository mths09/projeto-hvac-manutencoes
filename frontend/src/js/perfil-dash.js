const CAMPOS_PERFIL = ["nome", "email", "telefone", "endereco"];

// Confere o formato dos dados recebidos do PHP.
function temEstruturaDePerfil(perfil) {
  return (
    perfil !== null &&
    typeof perfil === "object" &&
    !Array.isArray(perfil) &&
    CAMPOS_PERFIL.every((campo) => typeof perfil[campo] === "string")
  );
}

// Limpa os dados anteriores enquanto carrega o cliente conectado.
function limparDadosPerfil() {
  const seletores = [
    ".nome-usuario",
    ".email-usuario",
    ".telefone-usuario",
    ".endereco-usuario",
    ".tempo-cliente",
    "#avatar-usuario",
    "#avatar-meu-perfil",
  ];

  document.querySelectorAll(seletores.join(",")).forEach((elemento) => {
    elemento.textContent = "—";
  });

  const titulo = document.querySelector("#titulo-dashboard");

  if (titulo) {
    titulo.textContent = "Bem-vindo!";
  }
}

// Busca o perfil no banco por meio da sessão criada no login.
async function carregarPerfil() {
  limparDadosPerfil();

  const resposta = await fetch("../../../backend/perfil.php", {
    method: "GET",
    credentials: "same-origin",
    cache: "no-store",
    headers: {
      Accept: "application/json",
    },
  });

  // Se a sessão não estiver válida, volta ao login.
  if (resposta.status === 401) {
    window.location.replace("./login.html");
    throw new Error("Faça login para acessar seu perfil.");
  }

  if (!resposta.ok) {
    throw new Error(`Erro ao carregar o perfil: ${resposta.status}`);
  }

  const dados = await resposta.json();

  if (dados?.sucesso !== true || !temEstruturaDePerfil(dados.usuario)) {
    throw new Error("O servidor retornou um perfil inválido.");
  }

  const perfil = dados.usuario;

  // Atualiza a data de cadastro exibida no cartão do cliente.
  const tempoCliente = document.querySelector(".tempo-cliente");
  const dataCadastro = String(perfil.criado_em || "").slice(0, 10);

  if (tempoCliente) {
    if (/^\d{4}-\d{2}-\d{2}$/.test(dataCadastro)) {
      const [ano, mes, dia] = dataCadastro.split("-");

      tempoCliente.textContent = `Cliente desde: ${dia}/${mes}/${ano}`;
    } else {
      tempoCliente.textContent = "Data de cadastro não informada";
    }
  }

  return perfil;
}

// Gera as iniciais para o avatar.
function obterIniciais(nome) {
  const partes = nome.trim().split(/\s+/).filter(Boolean);

  if (partes.length === 0) {
    return "?";
  }

  const primeiraInicial = partes[0][0];

  if (partes.length === 1) {
    return primeiraInicial.toUpperCase();
  }

  const ultimaInicial = partes[partes.length - 1][0];

  return (primeiraInicial + ultimaInicial).toUpperCase();
}
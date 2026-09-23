// trocar esse ID para testar outro usuario
// é apenas demonstração", não um sistema de login - AINDA
const ID_PERFIL_ATIVO = "perfil-001";

const CHAVE_PERFIL = `hvac.perfil.demo.v2.${ID_PERFIL_ATIVO}`;

const CAMPOS_PERFIL = ["nome", "email", "telefone", "endereco"];

// conferindo formato dos dados (enquanto não tem validação)
function temEstruturaDePerfil(perfil) {
  return (
    perfil !== null &&
    typeof perfil === "object" &&
    !Array.isArray(perfil) &&
    CAMPOS_PERFIL.every((campo) => typeof perfil[campo] === "string")
  );
}

async function carregarPerfil() {
  // tenta recuperar alterações feitas
  try {
    const textoSalvo = localStorage.getItem(CHAVE_PERFIL);

    if (textoSalvo !== null) {
      return perfilSalvo;
    }
  } catch (erro) {
    console.warn("Não foi possível ler o perfil local.", erro);
  }

  // sem alterações locais, busca os dados iniciais.
  const resposta = await fetch("../dados/perfil.json");

  if (!resposta.ok) {
    throw new Error(`Erro ao carregar os perfis: ${resposta.status}`);
  }

  const perfis = await resposta.json();

  if (!Array.isArray(perfis)) {
    throw new Error("O arquivo de perfis precisa conter uma lista.");
  }

  const perfil = perfis.find(
    (item) => item !== null && item.id === ID_PERFIL_ATIVO,
  );

  if (!temEstruturaDePerfil(perfil)) {
    throw new Error("Perfil não encontrado ou com estrutura inválida.");
  }

  return perfil;
}

function salvarPerfil(perfil) {
  const dados = {
    ...perfil,
    id: ID_PERFIL_ATIVO,
  };

  localStorage.setItem(CHAVE_PERFIL, JSON.stringify(dados));
}

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

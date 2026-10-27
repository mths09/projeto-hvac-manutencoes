// Validações e utilitários de formulário, usado pelo cadastro e edição.
// Regras espelham a do servidor

export function mostrarErro(campo, mensagem) {
  const aviso = document.getElementById(`erro-${campo.id}`);

  campo.setCustomValidity(mensagem); // :invalid no envio

  if (mensagem) {
    campo.setAttribute("aria-invalid", "true");
  } else {
    campo.removeAttribute("aria-invalid");
  }

  if (aviso) {
    aviso.textContent = mensagem;
  }
}

// Validadores
export function validarNome(valor, max = 150) {
  const nome = valor.trim().replace(/\s+/g, " ");
  const tamanho = Array.from(nome).length;

  if (tamanho < 2 || tamanho > max) {
    return `Informe de 2 a ${max} caracteres.`;
  }

  if (/[\p{C}<>]/u.test(nome)) {
    return "Há caracteres não permitidos.";
  }

  return "";
}

export function validarEmail(valor) {
  const email = valor.trim();

  if (email === "") {
    return "Informe seu email";
  }

  if (
    new TextEncoder().encode(email).length > 254 ||
    !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
  ) {
    return "Informe um e-mail válido: Ex: joão@hvac.com";
  }

  return "";
}

export function validarTelefone(valor) {
  const telefone = valor.trim();

  if (telefone === "") {
    return "Informe seu telefone com DDD.";
  }

  const digitos = telefone.replace(/\D/g, "");

  if (!/^[0-9()\s.-]+$/.test(telefone) || !/^[1-9][0-9]{9,10}$/.test(digitos)) {
    return "Informe o telefone com DDD: 10 ou 11 dígitos.";
  }

  return "";
}

export function validarCep(valor) {
  if (!/^[0-9]{5}-?[0-9]{3}$/.test(valor.trim())) {
    return "Informe um CEP válido com 8 dígitos";
  }

  return "";
}

export function validarTextoEndereco(valor, rotulo, min, max, obrigatorio) {
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

export function validarUf(valor) {
  return /^[A-Za-z]{2}$/.test(valor.trim())
    ? ""
    : "Informe a sigla do estado (ex.: SP).";
}

// tabela com regras do endereço
export const validadoresEndereco = {
  cep: validarCep,
  logradouro: (v) => validarTextoEndereco(v, "o logradouro", 2, 180, true),
  numero: (v) => validarTextoEndereco(v, "o número", 1, 20, true),
  complemento: (v) => validarTextoEndereco(v, "o complemento", 1, 120, false),
  bairro: (v) => validarTextoEndereco(v, "o bairro", 2, 100, true),
  cidade: (v) => validarTextoEndereco(v, "a cidade", 2, 100, true),
  uf: validarUf,
};

export function validarCamposEndereco(campos) {
  for (const [chave, validar] of Object.entries(validadoresEndereco)) {
    mostrarErro(campos[chave], validar(campos[chave].value));
  }
}

// Formatação
export function formatarCep(valor) {
  const digitos = String(valor || "")
    .replace(/\D/g, "")
    .slice(0, 8);

  return digitos.length > 5
    ? `${digitos.slice(0, 5)}-${digitos.slice(5)}`
    : digitos;
}

export function formatarTelefone(valor) {
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

// CEP: liga eventos de CEP e UF
export function ativarBuscaCep(campos, statusCep) {
  let consultaAtual = 0;

  async function buscar() {
    const cep = campos.cep.value.replace(/\D/g, "");

    if (cep.length !== 8) {
      return;
    }

    const consulta = ++consultaAtual;
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
      if (consulta !== consultaAtual) {
        return;
      }

      if (dados.erro) {
        statusCep.textContent =
          "CEP não encontrado. Preencha o endereço manualmente.";
        return;
      }

      const recebidos = [
        [campos.logradouro, dados.logradouro],
        [campos.bairro, dados.bairro],
        [campos.cidade, dados.localidade],
        [campos.uf, dados.uf],
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
      if (document.activeElement === campos.cep) {
        (campos.logradouro.value === ""
          ? campos.logradouro
          : campos.numero
        ).focus();
      }
    } catch (erro) {
      if (consulta !== consultaAtual) {
        return;
      }

      console.error("Falha ao buscar o CEP:", erro);
      statusCep.textContent =
        "Não foi possível buscar o CEP agora. Preencha o endereço manualmente.";
    }
  }

  campos.cep.addEventListener("input", () => {
    campos.cep.value = formatarCep(campos.cep.value);

    if (campos.cep.value.replace(/\D/g, "").length === 8) {
      buscar();
    } else {
      // CEP incompleto: cancela qualquer resposta pendente.
      consultaAtual++;
      statusCep.textContent = "";
    }
  });

  campos.uf.addEventListener("input", () => {
    campos.uf.value = campos.uf.value.toUpperCase();
  });

  return {
    cancelar() {
      consultaAtual++;
      statusCep.textContent = "";
    },
  };
}

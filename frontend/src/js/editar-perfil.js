// Formulario
const formularioPerfil = document.querySelector("#form-perfil");
// Botão
const avisoSalvamento = document.querySelector("#aviso-salvamento");
const botaoSalvar = formularioPerfil.querySelector('[type="submit"]');

// Campos formulário
const campoNome = document.querySelector("#nome");
const campoEmail = document.querySelector("#email");
const campoTelefone = document.querySelector("#telefone");
const campoEndereco = document.querySelector("#endereco");

async function preencherFormulario() {
  perfilCarregado = false;
  botaoSalvar.disabled = true;
  formularioPerfil.setAttribute("aria-busy", "true");
  avisoSalvamento.textContent = "Carregamento perfil...";

  try {
    const perfil = await carregarPerfil();

    // puxar dados do perfil do perfil salvo localmente
    // NOTA: Ajustar para puxar do banco de dados no futuro
    campoNome.value = perfil.nome;
    campoEmail.value = perfil.email;
    campoTelefone.value = perfil.telefone;
    campoEndereco.value = perfil.endereco;

    document.querySelector(".resumo-perfil h2").textContent =
      perfil.nome || "Cliente";

    document.querySelector(".resumo-perfil .avatar-perfil").textContent =
      obterIniciais(perfil.nome);

    perfilCarregado = true;
    botaoSalvar.disabled = false;

    avisoSalvamento.textContent =
      "As alterações serão salvas apenas neste navegador.";
  } catch (erro) {
    avisoSalvamento.textContent =
      "Não foi possível carregar o perfil. Recarregue a página para tentar novamente.";

    console.error(erro);
  } finally {
    formularioPerfil.setAttribute("aria-busy", "false");
  }
}

formularioPerfil.addEventListener("submit", (evento) => {
  // impede envio tradicional e recarregamento do formulario
  evento.preventDefault();

  if (!perfilCarregado) {
    return;
  }

  const perfilAtualizado = {
    nome: campoNome.value.trim(),
    email: campoEmail.value.trim(),
    telefone: campoTelefone.value.trim(),
    endereco: campoEndereco.value.trim(),
  };

  try {
    salvarPerfil(perfilAtualizado);
  } catch (erro) {
    avisoSalvamento.textContent =
      "Não foi possível salvar neste navegador, suas alterações ainda continuam nos campos.";

    console.error("Erro ao salvar o perfil.", erro);
    return;
  }

  // só retorna ao dashboard se o salvamento funcionar
  window.location.href = "../paginas/dashboard.html";
});

preencherFormulario();

// atualiza quando clica no botao voltar da pagina
window.addEventListener("pageshow", (evento) => {
  if (evento.persisted) {
    preencherFormulario();
  }
});

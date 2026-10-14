const formularioPerfil = document.querySelector("#form-perfil");
const avisoSalvamento = document.querySelector("#aviso-salvamento");
const botaoSalvar = formularioPerfil.querySelector('[type="submit"]');
const campoNome = document.querySelector("#nome");
const campoEmail = document.querySelector("#email");
const campoTelefone = document.querySelector("#telefone");
const campoEndereco = document.querySelector("#endereco");

let perfilCarregado = false;

async function preencherFormulario() {
  perfilCarregado = false;
  botaoSalvar.disabled = true;
  formularioPerfil.setAttribute("aria-busy", "true");
  avisoSalvamento.textContent = "Carregando seus dados…";
  try {
    const perfil = await carregarPerfil();
    campoNome.value = perfil.nome || "";
    campoEmail.value = perfil.email || "";
    campoTelefone.value = perfil.telefone || "";
    campoEndereco.value = perfil.endereco || "";
    document.querySelector(".resumo-perfil h2").textContent = perfil.nome || "Cliente";
    document.querySelector(".resumo-perfil .avatar-perfil").textContent = obterIniciais(perfil.nome);
    perfilCarregado = true;
    avisoSalvamento.textContent = "As alterações serão salvas na sua conta.";
  } catch (erro) {
    avisoSalvamento.textContent = erro.message || "Não foi possível carregar seu perfil.";
  } finally {
    botaoSalvar.disabled = !perfilCarregado;
    formularioPerfil.setAttribute("aria-busy", "false");
  }
}

formularioPerfil.addEventListener("submit", async (evento) => {
  evento.preventDefault();
  if (!perfilCarregado || !formularioPerfil.reportValidity()) return;

  botaoSalvar.disabled = true;
  formularioPerfil.setAttribute("aria-busy", "true");
  avisoSalvamento.textContent = "Salvando seu perfil…";
  try {
    await salvarPerfil({
      nome: campoNome.value.trim(),
      email: campoEmail.value.trim(),
      telefone: campoTelefone.value.trim(),
      endereco: campoEndereco.value.trim(),
    });
    avisoSalvamento.textContent = "Perfil atualizado. Voltando ao painel…";
    window.location.replace("./dashboard.html");
  } catch (erro) {
    avisoSalvamento.textContent = erro.message || "Não foi possível salvar seu perfil.";
  } finally {
    formularioPerfil.setAttribute("aria-busy", "false");
    botaoSalvar.disabled = false;
  }
});

preencherFormulario();
window.addEventListener("pageshow", (evento) => {
  if (evento.persisted) preencherFormulario();
});

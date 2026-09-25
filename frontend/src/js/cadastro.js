const formCadastro = document.getElementById("form-cadastro");
const senhaCadastro = document.getElementById("senha");
const confirmarSenha = document.getElementById("confirmar_senha");
const nascimento = document.getElementById("data_nascimento");
const mensagem = document.getElementById("mensagem-cadastro");
const hoje = new Date();
nascimento.max = `${hoje.getFullYear()}-${String(hoje.getMonth() + 1).padStart(2, "0")}-${String(hoje.getDate()).padStart(2, "0")}`;
function validarSenhas() {
  confirmarSenha.setCustomValidity(confirmarSenha.value !== senhaCadastro.value ? "As senhas precisam ser iguais." : "");
  senhaCadastro.setCustomValidity(new TextEncoder().encode(senhaCadastro.value).length > 72 ? "Sua senha é muito longa. Use no máximo 72 bytes (acentos podem ocupar mais de um)." : "");
}
senhaCadastro.addEventListener("input", validarSenhas);
confirmarSenha.addEventListener("input", validarSenhas);
formCadastro.addEventListener("submit", async (event) => {
  event.preventDefault();
  validarSenhas();
  if (!formCadastro.reportValidity()) return;
  const botao = formCadastro.querySelector('[type="submit"]');
  botao.disabled = true;
  botao.textContent = "Criando sua conta…";
  mensagem.hidden = true;
  mensagem.classList.remove("sucesso");
  try {
    const resposta = await fetch(formCadastro.action, {
      method: "POST",
      body: new FormData(formCadastro),
      headers: { Accept: "application/json" },
    });
    if (!resposta.headers.get("content-type")?.includes("application/json")) {
      throw new Error("Não foi possível acessar o cadastro. Abra o site pelo servidor PHP (XAMPP).");
    }
    const dados = await resposta.json();
    mensagem.textContent = dados.mensagem;
    mensagem.hidden = false;
    if (resposta.ok) {
      mensagem.classList.add("sucesso");
      formCadastro.reset();
      botao.textContent = "Conta criada";
      // A pessoa pode ler a confirmação e seguir pelo link de login.
      return;
    }
  } catch (erro) {
    mensagem.textContent = erro instanceof TypeError
      ? "Não foi possível conectar ao servidor. Verifique a conexão e tente novamente."
      : erro.message;
    mensagem.hidden = false;
  }
  botao.disabled = false;
  botao.textContent = "Criar minha conta →";
});

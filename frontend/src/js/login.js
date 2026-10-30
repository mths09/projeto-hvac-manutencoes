const formularioLogin = document.querySelector('#form-login');
const mensagemLogin = document.querySelector('#mensagem-login');
const botaoLogin = document.querySelector('#btn-login');

let enviandoLogin = false;

function mostrarMensagemLogin(texto, tipo) {
  mensagemLogin.textContent = texto;
  mensagemLogin.dataset.tipo = tipo;
  mensagemLogin.hidden = false;
}

formularioLogin.addEventListener('submit', async (evento) => {
  evento.preventDefault();

  if (enviandoLogin || !formularioLogin.reportValidity()) return;

  enviandoLogin = true;
  let redirecionando = false;
  botaoLogin.disabled = true;
  botaoLogin.textContent = 'Entrando...';
  formularioLogin.setAttribute('aria-busy', 'true');
  mostrarMensagemLogin('Verificando seus dados...', 'info');

  try {
    const resposta = await fetch(formularioLogin.action, {
      method: 'POST',
      body: new FormData(formularioLogin),
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    });

    const tipoConteudo = resposta.headers.get('content-type') || '';
    if (!tipoConteudo.includes('application/json')) {
      throw new Error('Não foi possível entrar agora. Tente novamente mais tarde.');
    }

    const dados = await resposta.json();
    if (!resposta.ok || dados?.sucesso !== true) {
      throw new Error(dados?.mensagem || 'Não foi possível entrar na conta.');
    }

    const destinosPermitidos = {
      admin: './tela-adm.html',
      tecnico: './tela-tecnico.html',
      cliente: './dashboard.html'
    };

    if (
      !Object.prototype.hasOwnProperty.call(destinosPermitidos, dados.area)
      || dados.destino !== destinosPermitidos[dados.area]
    ) {
      throw new Error('O servidor retornou um destino de acesso inválido.');
    }

    mostrarMensagemLogin('Login realizado! Abrindo seu painel...', 'sucesso');
    redirecionando = true;
    window.location.replace(dados.destino);
  } catch (erro) {
    const texto = erro instanceof TypeError
      ? 'Não foi possível conectar ao servidor.'
      : erro.message;
    mostrarMensagemLogin(texto, 'erro');
  } finally {
    formularioLogin.setAttribute('aria-busy', 'false');
    if (!redirecionando) {
      enviandoLogin = false;
      botaoLogin.disabled = false;
      botaoLogin.textContent = 'Entrar na conta →';
    }
  }
});

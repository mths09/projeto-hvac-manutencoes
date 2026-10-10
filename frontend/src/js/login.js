const formularioLogin = document.querySelector('#form-login');
const mensagemLogin = document.querySelector('#mensagem-login');
const botaoLogin = document.querySelector('#btn-login');

const areaSolicitada = new URLSearchParams(window.location.search).get('area') === 'admin'
  ? 'admin'
  : 'cliente';

let enviandoLogin = false;

function mostrarMensagemLogin(texto, tipo) {
  mensagemLogin.textContent = texto;
  mensagemLogin.dataset.tipo = tipo;
  mensagemLogin.hidden = false;
}

function prepararTelaDeLogin() {
  const titulo = document.querySelector('.card h1');
  const subtitulo = titulo?.nextElementSibling;
  const divisor = formularioLogin.querySelector('.divisor');
  const textoCadastro = divisor?.nextElementSibling;
  const botaoCadastro = formularioLogin.querySelector('.btn-cadastro');
  const linkArea = document.createElement('a');

  linkArea.style.cssText =
    'display:block;margin-top:18px;text-align:center;color:#67e8f9;font-size:14px';

  if (areaSolicitada === 'admin') {
    document.title = 'Acesso administrativo | HVAC Lex Company';
    if (titulo) titulo.textContent = 'Acesso do administrador';
    if (subtitulo) subtitulo.textContent = 'Entre com sua conta administrativa.';
    if (divisor) divisor.hidden = true;
    if (textoCadastro) textoCadastro.hidden = true;
    if (botaoCadastro) botaoCadastro.hidden = true;
    botaoLogin.textContent = 'Entrar no painel administrativo →';
    linkArea.href = './login.html';
    linkArea.textContent = 'Voltar ao login do cliente';
  } else {
    linkArea.href = './login.html?area=admin';
    linkArea.textContent = 'Acesso administrativo';
  }

  formularioLogin.after(linkArea);
}

prepararTelaDeLogin();

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
    const dadosFormulario = new FormData(formularioLogin);
    dadosFormulario.set('area', areaSolicitada);

    const resposta = await fetch(formularioLogin.action, {
      method: 'POST',
      body: dadosFormulario,
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
      cliente: './dashboard.html'
    };

    if (
      dados.area !== areaSolicitada
      || dados.destino !== destinosPermitidos[areaSolicitada]
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
      botaoLogin.textContent = areaSolicitada === 'admin'
        ? 'Entrar no painel administrativo →'
        : 'Entrar na conta →';
    }
  }
});

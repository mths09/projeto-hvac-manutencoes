document.documentElement.style.visibility = 'hidden';

const botaoSairAdmin = document.querySelector('#logout-btn');
if (botaoSairAdmin) {
  botaoSairAdmin.addEventListener('click', async () => {
    botaoSairAdmin.disabled = true;
    try {
      await fetch('../../../backend/logout.php', {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store'
      });
    } finally {
      window.location.replace('./login.html?area=admin');
    }
  });
}

(async () => {
  try {
    const resposta = await fetch('../../../backend/verificar_admin.php', {
      method: 'GET',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { Accept: 'application/json' }
    });

    const dados = await resposta.json();
    if (!resposta.ok || dados?.sucesso !== true) {
      window.location.replace('./login.html?area=admin');
      return;
    }

    const nome = String(dados.usuario?.nome || 'Administrador');
    const perfilNome = document.querySelector('.profile strong');
    const avatar = document.querySelector('.profile .avatar');

    if (perfilNome) perfilNome.textContent = nome;
    if (avatar) {
      const partes = nome.trim().split(/\s+/).filter(Boolean);
      avatar.textContent = partes.length > 1
        ? (partes[0][0] + partes[partes.length - 1][0]).toUpperCase()
        : (partes[0]?.[0] || 'A').toUpperCase();
    }

    document.documentElement.style.visibility = 'visible';
  } catch {
    window.location.replace('./login.html?area=admin');
  }
})();

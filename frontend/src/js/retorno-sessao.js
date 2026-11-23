const parametrosRetorno = new URLSearchParams(location.search);
const idPedido = parametrosRetorno.get("id");

const destinosRetorno = new Map([
  ["inicio", ["../../../index.html", "Voltar ao início"]],
  ["dashboard", ["./dashboard.html", "Voltar ao painel"]],
  ["projetos", ["./pagina-projetos.html", "Voltar aos projetos"]],
  ["sobre", ["./pagina-sobre.html", "Voltar para Sobre nós"]],
  ["termos", ["./privacidade-termos.html", "Voltar aos termos"]],
]);

if (/^[1-9]\d*$/.test(idPedido ?? "")) {
  destinosRetorno.set("servico", [
    `./visualizar-servico.html?id=${idPedido}`,
    "Voltar aos detalhes do pedido",
  ]);
}

const [enderecoRetorno, textoRetorno] =
  destinosRetorno.get(parametrosRetorno.get("origem")) ??
  destinosRetorno.get("inicio");

const botaoVoltar = document.querySelector(".voltar-site");
if (botaoVoltar) {
  botaoVoltar.href = enderecoRetorno;
  botaoVoltar.textContent = `← ${textoRetorno}`;
}

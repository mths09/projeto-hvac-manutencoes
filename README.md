# HVAC Lex Company

Projeto web para cadastro, acesso do cliente e administração de solicitações de manutenção.

## Rodar localmente com XAMPP

1. Inicie Apache e MySQL no XAMPP.
2. Importe `hvac_db.sql` no phpMyAdmin e confira se o banco se chama `hvac_db`.
3. Confira usuário, senha e nome do banco em `backend/conexao.php`.
4. Na pasta do projeto, inicie o servidor PHP:

   ```powershell
   & 'C:\xampp\php\php.exe' -S 127.0.0.1:8081
   ```

5. Acesse `http://127.0.0.1:8081/frontend/src/paginas/login.html`.

O Live Server do VS Code serve arquivos estáticos, mas não executa os endpoints PHP.

## Fluxos conectados ao banco

- `backend/cadastro.php`: valida o formulário e cria uma conta de cliente com senha em hash.
- `backend/login.php`: inicia a sessão e direciona para o painel adequado ao perfil do usuário.
- `backend/dashboard_cliente.php`: devolve os dados do perfil, pedidos, contadores, documentos liberados e notificações da sessão autenticada.
- `backend/perfil_cliente.php`: consulta e atualiza os dados do cliente e seu endereço principal.
- `backend/agendamento.php`: lista horários disponíveis e grava o pedido, endereço, agendamento, histórico e notificação em uma transação.
- `backend/notificacoes_lidas.php`: marca como lidas as notificações da própria conta.
- `backend/logout.php`: encerra a sessão.

O painel começa com contadores zerados quando a conta ainda não tem solicitações. Os pedidos são vinculados ao cliente autenticado no servidor; o navegador não escolhe o ID do usuário.

## Horários de demonstração

O formulário de agendamento lê horários da tabela `disponibilidade`. Para adicionar dois horários de amanhã durante o teste, execute `backend/horarios_teste.sql` na aba SQL do phpMyAdmin. O script não apaga nem altera horários existentes.

## Testar o cadastro

```powershell
node tests/cadastro.mjs
```

O teste requer PHP 8.1+, MySQL/MariaDB ligado e a tabela `usuario` no mecanismo InnoDB. Usa contas fictícias dentro de transações revertidas; o contador `AUTO_INCREMENT` pode avançar.

Os parâmetros de conexão local ficam em `backend/conexao.php`. Use dados fictícios durante o desenvolvimento.

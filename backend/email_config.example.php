<?php

// Copie para email_config.local.php e preencha com uma senha de app do Gmail.
// Nunca use a senha normal da conta Google nem envie este arquivo local ao Git.
// Em produção, prefira variáveis de ambiente e não inclua segredos no repositório:
// HVAC_SMTP_HOST, HVAC_SMTP_PORT, HVAC_SMTP_SECURITY, HVAC_SMTP_USER,
// HVAC_SMTP_APP_PASSWORD, HVAC_EMAIL_FROM, HVAC_EMAIL_FROM_NAME e HVAC_APP_URL.
return [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'security' => 'tls',
    'username' => '',
    'app_password' => '',
    'from_email' => '',
    'from_name' => 'HVAC Lex Company',
    'app_url' => '', // URL pública do projeto, sem barra final
    'notify_to' => '',
];


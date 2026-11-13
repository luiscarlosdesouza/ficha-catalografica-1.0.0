# Ficha Catalográfica — IME-USP

Sistema web desenvolvido em **Laravel** para geração automática de **fichas catalográficas** de trabalhos acadêmicos (Graduação e Pós-Graduação) da Biblioteca Carlos Benjamin de Lyra do **Instituto de Matemática, Estatística e Ciência da Computação da Universidade de São Paulo (IME-USP)**.

---

## Sumário

- [Funcionalidades](#funcionalidades)
- [Arquitetura e Tecnologias](#arquitetura-e-tecnologias)
- [Pré-requisitos](#pré-requisitos)
- [Instalação e Execução Local (Docker)](#instalação-e-execução-local-docker)
- [Deploy em Produção / Homologação (Portainer + Nginx Proxy Manager)](#deploy-em-produção--homologação-portainer--nginx-proxy-manager)
- [Variáveis de Ambiente](#variáveis-de-ambiente)
- [Área Administrativa (Senha Única USP)](#área-administrativa-senha-única-usp)
- [Estrutura de Diretórios](#estrutura-de-diretórios)
- [Documentação Complementar](#documentação-complementar)

---

## Funcionalidades

1. **Emissão Pública de Ficha Catalográfica (Autoatendimento)**:
   - Preenchimento de dados do autor, título/subtítulo, grau acadêmico (TCC de Graduação, Dissertação de Mestrado, Mestrado Profissional ou Tese de Doutorado), departamento ou programa de pós-graduação, orientação/coorientação, ano, paginação, ilustrações e assuntos (1 a 5).
   - **Saída em PDF**: geração imediata de folha A4 formatada com cabeçalho e rodapé institucionais da Biblioteca do IME-USP.
   - **Saída em LaTeX**: geração de trecho de código LaTeX pronto para inclusão no modelo LaTeX do IME-USP.
2. **Área Administrativa (`/configs`)**:
   - Restrita a administradores autenticados via **Senha Única USP** (`uspdev/senhaunica-socialite`).
   - Edição do cabeçalho e rodapé institucionais impressos nos PDFs (`/configs/edit`).
   - Painel de estatísticas com contagem mensal e anual de fichas geradas.

---

## Arquitetura e Tecnologias

| Camada | Tecnologia |
|---|---|
| **Backend** | PHP 8.5 (`php:8.5.12-fpm`) + Laravel 13 |
| **Servidor Web** | Nginx (`nginx:1.31.8-alpine`) |
| **Banco de Dados** | MySQL 8.0 (`mysql:8.0`) |
| **Geração de PDF** | Cezpdf (`rospdf/pdf-php` / `ros/ezpdf`) e DomPDF (`barryvdh/laravel-dompdf`) |
| **Autenticação / Tema** | `uspdev/senhaunica-socialite` (OAuth 1.0a USP) + `uspdev/laravel-usp-theme` |
| **Containers** | Docker Compose com volumes nomeados (`mysql-data`, `storage-data`, `public-data`, `vendor-data`) |

### Fluxo dos Containers

```text
[Cliente / Navegador]
        │ (HTTPS / 443)
        ▼
[Nginx Proxy Manager]  ──(rede externa "web")──►  [ficha-catalografica-nginx :80]
                                                          │
                                                          │ (rede interna "ficha-catalografica")
                                                          ▼
                                                  [ficha-catalografica-app :9000 (PHP-FPM)]
                                                          │
                                                          ▼
                                                  [ficha-catalografica-mysql :3306 (MySQL 8.0)]
```

No primeiro boot do container `app`, o script [`docker/entrypoint.sh`](docker/entrypoint.sh) executa automaticamente:
1. Sincronização dos arquivos públicos para o volume compartilhado com o Nginx (`public-data`).
2. Geração e persistência da `APP_KEY` (em `storage/app/app_key`), caso não tenha sido definida no ambiente.
3. Instalação das dependências do Composer (`composer install --no-dev`) e publicação dos assets do `laravel-usp-theme`.
4. Espera ativa pela disponibilidade do banco MySQL e execução das migrations (`php artisan migrate --force`).
5. Criação automática do registro inicial de cabeçalho e rodapé na tabela `configs` (caso esteja vazia), garantindo que a geração de PDF funcione imediatamente após a instalação.

---

## Pré-requisitos

- **Docker Engine** 24+ e **Docker Compose** v2+
- Rede externa `web` criada no Docker:
  ```bash
  docker network create web
  ```

---

## Instalação e Execução Local (Docker)

1. **Clone o repositório**:
   ```bash
   git clone https://github.com/luiscarlosdesouza/ficha-catalografica-1.0.0.git
   cd ficha-catalografica-1.0.0
   ```

2. **Crie a rede externa `web`** (caso ainda não exista na sua máquina):
   ```bash
   docker network create web
   ```

3. **Exponha a porta 80 para acesso local direto** (se não estiver usando Nginx Proxy Manager localmente):
   No arquivo [`docker-compose.yml`](docker-compose.yml), descomente o bloco `ports:` do serviço `nginx`:
   ```yaml
   ports:
       - "80:80"
   ```

4. **Suba os containers com build das imagens**:
   ```bash
   docker compose up -d --build
   ```

5. **Acompanhe a inicialização (instalação do Composer e migrations)**:
   ```bash
   docker compose logs -f app
   ```
   Aguarde até aparecer a mensagem `NOTICE: ready to handle connections`.

6. **Acesse no navegador**:
   - Aplicação: `http://localhost`
   - Healthcheck do Nginx: `http://localhost/health`

---

## Deploy em Produção / Homologação (Portainer + Nginx Proxy Manager)

O projeto está preparado para deploy direto via **Portainer Stacks** a partir do repositório Git, sem depender de *bind mounts* no sistema de arquivos do host.

### Passo 1 — Rede Externa no Servidor
Certifique-se de que a rede `web` (compartilhada com o Nginx Proxy Manager) existe no host:
```bash
docker network create web
```

### Passo 2 — Criar a Stack no Portainer
1. No Portainer, acesse **Stacks** → **Add stack**.
2. Defina o nome (ex.: `ficha-catalografica`) e selecione **Repository**.
3. Configure:
   - **Repository URL**: `https://github.com/luiscarlosdesouza/ficha-catalografica-1.0.0`
   - **Repository reference**: `refs/heads/main`
   - **Compose path**: `docker-compose.yml`
4. Na seção **Environment variables**, clique em **Advanced mode** e cole as variáveis de ambiente (veja a seção [Variáveis de Ambiente](#variáveis-de-ambiente) abaixo).
5. Clique em **Deploy the stack**.

### Passo 3 — Configurar o Proxy Host no Nginx Proxy Manager
1. Em **Proxy Hosts**, clique em **Add Proxy Host**:
   - **Domain Names**: `ficha.seu-dominio.br` (substitua pelo domínio desejado)
   - **Scheme**: `http`
   - **Forward Hostname / IP**: `ficha-catalografica-nginx`
   - **Forward Port**: `80`
   - Marque **Block Common Exploits**.
2. Salve primeiro sem SSL e verifique se o domínio responde em HTTP.
3. Edite o Proxy Host criado, vá na aba **SSL**, selecione **Request a new SSL Certificate**, marque **Force SSL** e **I Agree to the Let's Encrypt Terms of Service** e clique em **Save**.

---

## Variáveis de Ambiente

Bloco modelo para uso no modo avançado (**Advanced mode**) do Portainer. **Substitua todos os valores de exemplo/placeholder por credenciais fortes antes de publicar em produção/homologação:**

```dotenv
# Aplicação
APP_NAME="Ficha Catalográfica"
APP_ENV=production
APP_DEBUG=false
APP_KEY=
APP_URL=https://ficha.seu-dominio.br
ASSET_URL=
FORCE_HTTPS=true
TZ=America/Sao_Paulo
APP_VERSION=1.0.2

# Permissões de usuário/grupo no container
UID=1000
GID=1000

# Logs
LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=warning

# Banco de Dados (defina usuário e senhas fortes no Portainer)
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=ficha_catalografica
DB_USERNAME=seu_usuario_db
DB_PASSWORD=sua_senha_segura_db
DB_ROOT_PASSWORD=sua_senha_root_segura

# Cache, Sessão e Filas
BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DRIVER=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=false

# E-mail
MAIL_MAILER=log
MAIL_HOST=smtp.seu-dominio.br
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@seu-dominio.br
MAIL_FROM_NAME="Ficha Catalográfica"

# Senha Única USP (OAuth 1.0a - preencha com as credenciais da sua unidade)
SENHAUNICA_KEY=
SENHAUNICA_SECRET=
SENHAUNICA_CALLBACK_ID=
SENHAUNICA_DEV=no
SENHAUNICA_ADMINS=
SENHAUNICA_GERENTES=
SENHAUNICA_USERS=
SENHAUNICA_DROP_PERMISSIONS=false
SENHAUNICA_DEBUG=false

# Tema USP e Flags de Inicialização
USP_THEME_SKIN=uspdev
RUN_MIGRATIONS=true
RUN_SEEDERS=false
```

> **Importante sobre HTTPS**: Sempre que o sistema estiver publicado atrás de um proxy HTTPS (como o Nginx Proxy Manager com SSL), mantenha `APP_URL=https://seu-dominio` (com `https://`) e `FORCE_HTTPS=true`.

---

## Área Administrativa (Senha Única USP)

Para habilitar o login administrativo em `/login` e o acesso às configurações em `/configs`:
1. Cadastre a aplicação na **Senha Única USP** e obtenha `SENHAUNICA_KEY`, `SENHAUNICA_SECRET` e `SENHAUNICA_CALLBACK_ID`.
2. Informe os números USP (separados por vírgula) dos administradores na variável `SENHAUNICA_ADMINS` (ex.: `SENHAUNICA_ADMINS=1234567,7654321`).
3. Após autenticar, o ícone de engrenagem no canto superior direito leva à tela `/configs`, onde é possível:
   - Editar o cabeçalho e o rodapé das fichas em PDF (`/configs/edit`);
   - Consultar o histórico de fichas emitidas por mês e por ano.

---

## Estrutura de Diretórios

```text
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── ConfigController.php   # Configuração de cabeçalho/rodapé e estatísticas
│   │   │   └── FichaController.php    # Montagem da ficha e geração de PDF/LaTeX
│   │   └── Middleware/
│   │       └── TrustProxies.php       # Confiança nos cabeçalhos X-Forwarded-* do proxy reverso
│   ├── Models/
│   │   ├── Config.php                 # Cabeçalho e rodapé institucionais
│   │   └── Ficha.php                  # Registro temporal de cada ficha emitida
│   └── Providers/
│       └── AppServiceProvider.php     # Força esquema HTTPS atrás de proxy reverso
├── config/                            # Configurações do Laravel, Tema USP e Senha Única
├── database/migrations/               # Migrations das tabelas users, permissions, configs e fichas
├── docker/
│   ├── entrypoint.sh                  # Script de inicialização do container PHP-FPM
│   ├── nginx/
│   │   ├── Dockerfile                 # Imagem customizada do Nginx com default.conf embutido
│   │   └── default.conf               # VirtualHost Nginx encaminhando PHP para app:9000
│   └── php/
│       ├── local.ini                  # Limites de upload, memória e timezone do PHP
│       └── www.conf                   # Pool PHP-FPM (clear_env = no)
├── docs/
│   ├── alteracoes-deploy-portainer.md # Registro detalhado das alterações feitas para Docker/Portainer/HTTPS
│   └── relatorio-tecnico.md           # Análise técnica detalhada do sistema
├── resources/views/
│   ├── config/                        # Telas administrativas (configs e edit)
│   ├── ficha/                         # Formulário principal (ficha) e saída LaTeX (fichaLatex)
│   └── main.blade.php                 # Layout base integrado ao laravel-usp-theme
├── Dockerfile                         # Build da imagem PHP 8.5 FPM da aplicação
└── docker-compose.yml                 # Orquestração dos serviços app, nginx e mysql
```

---

## Documentação Complementar

- [Relatório de Alterações (Docker, Portainer e HTTPS)](docs/alteracoes-deploy-portainer.md): resumo e *diffs* de todas as adaptações realizadas sobre a versão original para funcionamento em containers, Portainer e proxy reverso HTTPS.
- [Relatório Técnico do Sistema](docs/relatorio-tecnico.md): documentação da arquitetura, regras de negócio e análise de código.

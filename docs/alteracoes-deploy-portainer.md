# Relatório de Alterações — Adaptação para Docker, Portainer e Proxy Reverso HTTPS

**Projeto:** `ficha-catalografica-1.0.0`  
**Repositório:** `https://github.com/luiscarlosdesouza/ficha-catalografica-1.0.0`  
**Data:** 11/11/2026  

Este documento descreve todas as alterações realizadas em relação ao pacote original recebido do desenvolvedor, detalhando **o problema encontrado**, **a solução aplicada** e **os arquivos modificados** para que possam ser incorporados de volta ao repositório de origem.

---

## Resumo dos Problemas Corrigidos

1. **Erro de montagem de diretórios/arquivos no Portainer (`not a directory: Are you trying to mount a directory onto a file`)**:
   - **Causa:** O `docker-compose.yml` original usava *bind mounts* relativos ao diretório do host (`- .:/var/www`, `- ./storage:/var/www/storage`, `- ./docker/php/local.ini:...`, `- ./docker/php/www.conf:...`, `- ./docker/nginx/default.conf:...`). Quando o Portainer implanta uma Stack via repositório Git, o clone fica dentro do container do Portainer (`/data/compose/<id>`), mas o daemon do Docker resolve os *bind mounts* no sistema de arquivos do **host** (onde esses arquivos não existem). O Docker então criava diretórios vazios no host e falhava ao tentar montar o diretório vazio `/data/compose/<id>/docker/php/www.conf` sobre o arquivo `/usr/local/etc/php-fpm.d/www.conf` da imagem PHP.
   - **Solução:** O código da aplicação e os arquivos de configuração (`local.ini`, `www.conf`, `entrypoint.sh` e `default.conf` do Nginx) passaram a ser copiados para dentro das imagens durante o `docker build` (`COPY`), e os *bind mounts* relativos foram substituídos por **volumes nomeados do Docker** (`storage-data`, `public-data`, `vendor-data` e `mysql-data`).

2. **Banco de dados MySQL comentado no `docker-compose.yml`**:
   - **Causa:** O serviço `mysql` e o `depends_on` correspondente no serviço `app` estavam comentados no `docker-compose.yml`, fazendo o container `app` aguardar indefinidamente por um host `mysql` inexistente quando não havia banco externo.
   - **Solução:** O serviço `mysql` (`mysql:8.0`) e o `depends_on` com `condition: service_healthy` foram habilitados no `docker-compose.yml`.

3. **Erro ao gerar PDF em instalação limpa (`"Não existem configurações salvas."`)**:
   - **Causa:** O método `FichaController::montarFicha()` exige que exista ao menos um registro na tabela `configs` (cabeçalho e rodapé) para gerar o PDF. Em uma instalação nova, a tabela `configs` inicia vazia até que um administrador configure a Senha Única USP e salve manualmente em `/configs/edit`.
   - **Solução:** No [`docker/entrypoint.sh`](../docker/entrypoint.sh), logo após rodar `php artisan migrate`, foi adicionada uma verificação idempotente que insere o cabeçalho e o rodapé padrão da Biblioteca do IME-USP caso a tabela `configs` esteja vazia.

4. **Erro `405 Method Not Allowed` ao gerar PDF ou LaTeX em ambiente com HTTPS (Nginx Proxy Manager)**:
   - **Causa:** O pacote `laravel-usp-theme` gera no `<head>` a tag `<base href="{{ config('laravel-usp-theme.app_url') }}/">`. Além disso, o Laravel não confiava nos cabeçalhos `X-Forwarded-*` do proxy reverso (`TrustProxies::$proxies` estava `null`) e a variável `FORCE_HTTPS` do `docker-compose.yml` não era lida no `AppServiceProvider`. Quando a tag `<base>` ou a URL da aplicação saía com `http://`, o formulário `<form action="/ficha" method="POST">` era submetido para `http://seu-dominio/ficha`. O proxy reverso respondia com um redirecionamento `301 Moved Permanently` para `https://...`, fazendo o navegador converter a requisição `POST` em `GET` — o que resultava em `405 Method Not Allowed` na rota `POST /ficha`.
   - **Solução:**
     - Configurado `protected $proxies = '*';` em [`app/Http/Middleware/TrustProxies.php`](../app/Http/Middleware/TrustProxies.php).
     - Implementado o `boot()` em [`app/Providers/AppServiceProvider.php`](../app/Providers/AppServiceProvider.php) para forçar o esquema `https` (`URL::forceScheme('https')`) e atualizar `app.url` / `laravel-usp-theme.app_url` para `https://` sempre que `FORCE_HTTPS=true` ou `X-Forwarded-Proto: https`.
     - Atualizado o atributo `action` dos formulários em [`resources/views/ficha/ficha.blade.php`](../resources/views/ficha/ficha.blade.php) e [`resources/views/config/edit.blade.php`](../resources/views/config/edit.blade.php) para usar `{{ url('/ficha') }}` e `{{ url('/configs/save') }}`.

---

## Detalhamento das Alterações por Arquivo

### 1. `Dockerfile`
Foram adicionadas as instruções `COPY` para embutir as configurações do PHP, o `entrypoint.sh` e o código-fonte na imagem, além de guardar uma cópia de `public/` em `/usr/src/public-init` para sincronização com o volume compartilhado do Nginx:

```diff
-ARG uid=1001
-ARG gid=1001
+ARG uid=1000
+ARG gid=1000
...
 # Fuso horário do container
 RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime \
     && echo $TZ > /etc/timezone
 
+# Configurações do PHP e PHP-FPM embutidas na imagem (compatível com Portainer)
+COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini
+COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
+COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
+RUN chmod +x /usr/local/bin/entrypoint.sh
+
 WORKDIR /var/www
 
+# Código-fonte da aplicação
+COPY --chown=www-data:www-data . /var/www
+RUN cp -a /var/www/public /usr/src/public-init
+
 EXPOSE 9000
+ENTRYPOINT ["sh", "/usr/local/bin/entrypoint.sh"]
 CMD ["php-fpm"]
```

---

### 2. `docker/nginx/Dockerfile` *(Novo arquivo)*
Criado para embutir o arquivo `docker/nginx/default.conf` diretamente na imagem do Nginx sem precisar de *bind mount* no host:

```dockerfile
FROM nginx:1.31.8-alpine

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
```

---

### 3. `docker-compose.yml`
Substituídos os *bind mounts* relativos por volumes nomeados (`storage-data`, `public-data`, `vendor-data`), habilitado o serviço `mysql` e configurado o build da imagem do `nginx`:

```diff
-    APP_URL: "${APP_URL:-http://ficha.dev.ime}"
+    APP_URL: "${APP_URL:-http://localhost}"
...
             args:
-                uid: "${UID:-1001}"
-                gid: "${GID:-1001}"
-        image: "ficha-catalografica:${APP_VERSION:-latest}"
+                uid: "${UID:-1000}"
+                gid: "${GID:-1000}"
+        image: "ficha-catalografica:${APP_VERSION:-1.0.2}"
         container_name: ficha-catalografica-app
         restart: unless-stopped
         working_dir: /var/www
         volumes:
-            - .:/var/www
-            - ./storage:/var/www/storage
-            - ./docker/php/local.ini:/usr/local/etc/php/conf.d/local.ini:ro
-            - ./docker/php/www.conf:/usr/local/etc/php-fpm.d/www.conf:ro
-        entrypoint: ["sh", "./docker/entrypoint.sh"]
+            - storage-data:/var/www/storage
+            - public-data:/var/www/public
+            - vendor-data:/var/www/vendor
+        entrypoint: ["sh", "/usr/local/bin/entrypoint.sh"]
         environment:
             <<: *app-environment
             CONTAINER_ROLE: app
         networks:
             - ficha-catalografica
-        #depends_on:
-        #    mysql:
-        #        condition: service_healthy
+        depends_on:
+            mysql:
+                condition: service_healthy
...
     nginx:
-        image: nginx:1.31.8-alpine
+        build:
+            context: .
+            dockerfile: docker/nginx/Dockerfile
+        image: "ficha-catalografica-nginx:${APP_VERSION:-1.0.2}"
         container_name: ficha-catalografica-nginx
         restart: unless-stopped
-        ports:
-            - "80:80"    # <-- Adicione esta linha
+        #ports:
+        #    - "80:80"    # Descomente apenas para acesso direto local sem Proxy Manager
         expose:
             - "80"
         volumes:
-            - .:/var/www:ro
-            - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
+            - public-data:/var/www/public:ro
...
     # MySQL (interno; não publicado no host)
-    # (bloco mysql descomentado)
+    mysql:
+        image: mysql:8.0
...
 volumes:
     mysql-data:
         name: ficha-catalografica-mysql-data
+    storage-data:
+        name: ficha-catalografica-storage-data
+    public-data:
+        name: ficha-catalografica-public-data
+    vendor-data:
+        name: ficha-catalografica-vendor-data
```

---

### 4. `docker/entrypoint.sh`
Adicionada a cópia inicial dos arquivos estáticos para `/var/www/public` (volume compartilhado com o Nginx) e a criação automática do registro inicial na tabela `configs`:

```diff
+# Sincroniza arquivos estáticos da imagem para o volume compartilhado com o Nginx
+if [ -d /usr/src/public-init ]; then
+    mkdir -p /var/www/public
+    cp -af /usr/src/public-init/. /var/www/public/
+fi
+
 # Dependências PHP: instala no primeiro boot e sempre que o composer.lock mudar.
...
 if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
     echo "[ficha-catalografica] Executando migrations..."
     php artisan migrate --force --no-interaction
+
+    # Garante configuração inicial de cabeçalho/rodapé caso a tabela configs esteja vazia
+    php -r '
+    require "vendor/autoload.php";
+    $app = require_once "bootstrap/app.php";
+    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
+    if (!App\Models\Config::count()) {
+        App\Models\Config::create([
+            "cabecalho" => "Ficha catalográfica elaborada com dados inseridos pelo(a) autor(a)\nBiblioteca Carlos Benjamin de Lyra\nInstituto de Matemática, Estatística e Ciência da Computação\nUniversidade de São Paulo",
+            "rodape" => "Bibliotecárias do Serviço de Informação e Biblioteca\nCarlos Benjamin de Lyra do IME-USP, responsáveis pela\nestrutura de catalogação da publicação de acordo com a AACR2:\nMaria Lúcia Ribeiro CRB-8/2766; Stela do Nascimento Madruga CRB 8/7534."
+        ]);
+        echo "[ficha-catalografica] Configuração inicial de cabeçalho/rodapé criada.\n";
+    }
+    ' || true
 fi
```

---

### 5. `app/Http/Middleware/TrustProxies.php`
Habilitada a confiança nos cabeçalhos `X-Forwarded-*` enviados pelo proxy reverso (Nginx Proxy Manager):

```diff
-    protected $proxies;
+    protected $proxies = '*';
```

---

### 6. `app/Providers/AppServiceProvider.php`
Configurado o método `boot()` para respeitar `FORCE_HTTPS=true` e o cabeçalho `X-Forwarded-Proto: https`, forçando a geração de URLs e da tag `<base>` do `laravel-usp-theme` em `https://`:

```diff
 namespace App\Providers;
 
+use Illuminate\Support\Facades\URL;
 use Illuminate\Support\ServiceProvider;
 
 class AppServiceProvider extends ServiceProvider
...
     public function boot()
     {
-        //
+        if (
+            filter_var(env('FORCE_HTTPS', false), FILTER_VALIDATE_BOOLEAN)
+            || request()->header('X-Forwarded-Proto') === 'https'
+            || request()->isSecure()
+        ) {
+            URL::forceScheme('https');
+            $appUrl = config('app.url');
+            if (is_string($appUrl) && str_starts_with($appUrl, 'http://')) {
+                $httpsUrl = 'https://' . substr($appUrl, 7);
+                config([
+                    'app.url' => $httpsUrl,
+                    'laravel-usp-theme.app_url' => $httpsUrl,
+                ]);
+            }
+        }
     }
```

---

### 7. `resources/views/ficha/ficha.blade.php` e `resources/views/config/edit.blade.php`
Substituídos os caminhos fixos `/ficha` e `/configs/save` pelo helper `url(...)` do Laravel para respeitar o esquema HTTPS ativo:

```diff
--- a/resources/views/ficha/ficha.blade.php
+++ b/resources/views/ficha/ficha.blade.php
@@ -4,1 +4,1 @@
-<form action="/ficha" method="POST" target="_blank">
+<form action="{{ url('/ficha') }}" method="POST" target="_blank">
```

```diff
--- a/resources/views/config/edit.blade.php
+++ b/resources/views/config/edit.blade.php
@@ -4,1 +4,1 @@
-<form action="/configs/save" method="POST">
+<form action="{{ url('/configs/save') }}" method="POST">
```


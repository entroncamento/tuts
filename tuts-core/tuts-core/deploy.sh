#!/bin/bash

# Script de deploy para TUT'S (Laravel)
# Este script deve ser executado dentro do contentor ou como parte do pipeline de CI/CD.

echo "🚀 Iniciando processo de deploy..."

# 1. Garantir dependências do Composer (se não for feito no build)
# composer install --no-dev --optimize-autoloader

# 2. Correr migrações
echo "📦 Correndo migrações..."
echo '[TUTS][Deploy] Skipping automatic migrations in production. Run migrations manually only after explicit approval.'

# 3. Otimizações de Cache
echo "⚡ Otimizando caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 4. Links de storage
echo "🔗 Criando links de storage..."
php artisan storage:link --force

# 5. Permissões de pastas críticas
echo "🔐 Ajustando permissões..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo "✅ Deploy concluído com sucesso!"

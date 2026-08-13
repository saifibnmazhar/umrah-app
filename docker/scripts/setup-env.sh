#!/bin/bash
set -euo pipefail

# Setup script - run after first git clone on production server
# This initializes the production environment

echo "🔧 Setting up production environment..."

# Create .env.production if not exists
if [ ! -f .env.production ]; then
    cp .env.production.sample .env.production
    echo "✅ Created .env.production from template"
    echo ""
    echo "⚠️  Please edit .env.production with real values:"
    echo "   - DB_PASSWORD"
    echo "   - APP_KEY"
    echo "   - APP_URL"
    echo ""
    echo "Then run: php artisan key:generate --force"
    echo "And paste the APP_KEY value back into .env.production"
    exit 1
fi

# Make deploy script executable
chmod +x deploy-prod.sh
echo "✅ Made deploy-prod.sh executable"

# Validate required variables
REQUIRED_VARS=("DB_PASSWORD" "APP_KEY" "APP_URL")
for var in "${REQUIRED_VARS[@]}"; do
    if grep -q "^${var}=" .env.production 2>/dev/null; then
        VALUE=$(grep "^${var}=" .env.production | cut -d'=' -f2-)
        if [ -z "$VALUE" ] || [ "$VALUE" = "***" ]; then
            echo "❌ $var is not set in .env.production"
            MISSING=true
        fi
    fi
done

if [ -n "${MISSING:-}" ]; then
    echo ""
    echo "⚠️  Missing required environment variables"
    echo "Please update .env.production with real values"
    exit 1
fi

# Regenerate .env.production.sample from current .env.production
# Strips actual values but preserves variable names and structure
sed -E 's/^([^=]+=).*/\1***/' .env.production | \
    sed -E 's/^APP_KEY=.*/APP_KEY=base64:GENERATE_THIS_ON_DEPLOY/' > .env.production.sample
echo "✅ Refreshed .env.production.sample"
echo ""

echo "✅ Environment is ready!"
echo "🔄 Run './deploy-prod.sh' to deploy"

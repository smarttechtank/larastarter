#!/bin/bash

# Set colors
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${GREEN}Setting up LaraStarter package for development...${NC}"

# Make sure migrations are ready
echo -e "${YELLOW}Copying migrations...${NC}"
cp database/migrations/create_roles_table.php.stub database/migrations/
cp database/migrations/add_role_id_to_users_table.php.stub database/migrations/

# Make sure stub directories exist
echo -e "${YELLOW}Creating stub directories...${NC}"
mkdir -p stubs/app/Models
mkdir -p stubs/app/Repositories
mkdir -p stubs/app/Policies
mkdir -p stubs/app/Http/Controllers/API
mkdir -p stubs/app/Http/Controllers/Auth
mkdir -p stubs/app/Http/Middleware
mkdir -p stubs/app/Http/Requests
mkdir -p stubs/app/Http/Requests/Auth
mkdir -p stubs/app/Notifications
mkdir -p stubs/bootstrap
mkdir -p stubs/database/migrations
mkdir -p stubs/database/seeders
mkdir -p stubs/routes

echo -e "${GREEN}Setup completed!${NC}"

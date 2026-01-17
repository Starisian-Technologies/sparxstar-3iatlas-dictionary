# Local Development

```yaml
version: '3.9'
services:
    wordpress:
        image: wordpress:6.4-php8.2
        ports:
            - '8000:80'
        environment:
            WORDPRESS_DB_HOST: db
            WORDPRESS_DB_USER: wordpress
            WORDPRESS_DB_PASSWORD: wordpress
            WORDPRESS_DB_NAME: wordpress
        volumes:
            - ./:/var/www/html/wp-content/plugins/starisian-plugin
    db:
        image: mysql:8
        environment:
            MYSQL_DATABASE: wordpress
            MYSQL_USER: wordpress
            MYSQL_PASSWORD: wordpress
            MYSQL_RANDOM_ROOT_PASSWORD: '1'
        volumes:
            - db_data:/var/lib/mysql
volumes:
    db_data:
```

## Commands

- `composer install`
- `npm install`
- `docker compose up -d`
- Run unit tests: `composer run test:php`
- Lint PHP: `composer run lint:php`
- Analyze: `composer run analyze:php`
- Build assets: `npm run build`
